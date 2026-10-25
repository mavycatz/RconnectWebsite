<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

if (!Auth::check()) {
    send_json(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user = Auth::user();
if (!isset($user['role']) || !in_array(strtolower($user['role']), ['admin', 'super_admin'])) {
    send_json(['success' => false, 'message' => 'Forbidden'], 403);
}

if (!function_exists('send_json')) {
    function send_json($data, int $code = 200): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        if ($code !== 200) {
            http_response_code($code);
        }
        echo json_encode($data);
        exit;
    }
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

require_once __DIR__ . '/driver-perf-occupancy.php';
require_once __DIR__ . '/driver-perf-metrics-core.php';

/**
 * Period resolver for the per-driver analytics feed. Delegates to the shared
 * dpm_period_range() so Annually / Semi-Annually / Quarterly / Monthly /
 * Custom all resolve to the same date ranges everywhere.
 */
function dpa_period_range(array $get): array
{
    return dpm_period_range($get);
}

function dpa_driver_buses(PDO $db, string $driverId): array
{
    $stmt = $db->prepare("SELECT bus_id FROM driver_bus_assignment WHERE driver_id = ?");
    $stmt->execute([$driverId]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Latest remittance log for a driver within the period (source of truth for remittance status).
 * Returns null when the driver has no finalized remittance in the period.
 */
function dpa_remittance_period(PDO $db, string $driverId, string $start, string $end): ?array
{
    $stmt = $db->prepare("
        SELECT expected_cash, actual_cash, discrepancy_amount, status
        FROM remittance_logs
        WHERE driver_id = ? AND DATE(created_at) BETWEEN DATE(?) AND DATE(?)
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$driverId, $start, $end]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function dpa_compute_driver(PDO $db, array $driver, string $start, string $end, ?string $period = null, ?array &$tripSeries = null, ?array &$fareSeries = null): array
{
    $did = (string)$driver['driver_id'];
    $buses = dpa_driver_buses($db, $did);

    $live = [
        'total_trips' => 0, 'completed' => 0, 'cancelled' => 0, 'pending' => 0,
        'passenger_records' => 0, 'passenger_count' => 0, 'other_count' => 0,
        'other_occupied' => 0, 'passenger_occupied' => 0, 'regular' => 0, 'special' => 0,
        'fare_total' => 0, 'other_fare' => 0, 'passenger_fare' => 0, 'paid' => 0, 'pending_fare' => 0,
        'gross_paid' => 0, 'gross_total' => 0, 'remitted_paid' => 0,
    ];

    // Live metrics are computed for every driver; a bus-assignment row must never
    // gate out trips that passenger_trips already records for that driver.
    $sql = "
        SELECT
            COUNT(DISTINCT pt.trip_no) AS total_trips,
            COUNT(DISTINCT CASE WHEN LOWER(pt.payment_status)='paid' THEN pt.trip_no END) AS completed,
            COUNT(DISTINCT CASE WHEN LOWER(pt.payment_status)='rejected' THEN pt.trip_no END) AS cancelled,
            COUNT(DISTINCT CASE WHEN LOWER(pt.payment_status) IN ('pending','ongoing') THEN pt.trip_no END) AS pending,
            COUNT(pt.id) AS passenger_records,
            SUM(CASE WHEN COALESCE(p.type,'')='other' THEN 0 ELSE 1 END) AS passenger_count,
            SUM(CASE WHEN COALESCE(p.type,'')='other' THEN 1 ELSE 0 END) AS other_count,
            SUM(CASE WHEN COALESCE(p.type,'')='other' THEN COALESCE(p.space_occupied,1) ELSE 0 END) AS other_occupied,
            SUM(CASE WHEN COALESCE(p.type,'')!='other' THEN COALESCE(p.space_occupied,1) ELSE 0 END) AS passenger_occupied,
            SUM(CASE WHEN p.discount_type IS NULL OR p.discount_type='' OR LOWER(p.discount_type)='regular' THEN 1 ELSE 0 END) AS regular,
            SUM(CASE WHEN p.discount_type IS NOT NULL AND p.discount_type!='' AND LOWER(p.discount_type)!='regular' THEN 1 ELSE 0 END) AS special,
            SUM(CASE WHEN COALESCE(pt.remittance_status,'Pending') <> 'Pending' THEN COALESCE(pt.fare,0) ELSE 0 END) AS fare_total,
            SUM(CASE WHEN COALESCE(p.type,'')='other' AND LOWER(COALESCE(pt.payment_status,'')) <> 'rejected' THEN COALESCE(pt.fare,0) ELSE 0 END) AS other_fare,
            SUM(CASE WHEN COALESCE(p.type,'')!='other' AND LOWER(COALESCE(pt.payment_status,'')) <> 'rejected' THEN COALESCE(pt.fare,0) ELSE 0 END) AS passenger_fare,
            SUM(CASE WHEN COALESCE(pt.remittance_status,'Pending') <> 'Pending' AND LOWER(pt.payment_status)='paid' THEN COALESCE(pt.fare,0) ELSE 0 END) AS paid,
            SUM(CASE WHEN LOWER(pt.payment_status) IN ('pending','ongoing') THEN COALESCE(pt.fare,0) ELSE 0 END) AS pending_fare,
            SUM(CASE WHEN LOWER(pt.payment_status)='paid' THEN COALESCE(pt.fare,0) ELSE 0 END) AS gross_paid,
            SUM(CASE WHEN LOWER(COALESCE(pt.payment_status,'')) <> 'rejected' THEN COALESCE(pt.fare,0) ELSE 0 END) AS gross_total,
            SUM(CASE WHEN COALESCE(pt.remittance_status,'Pending') <> 'Pending' AND LOWER(pt.payment_status)='paid' THEN COALESCE(pt.fare,0) ELSE 0 END) AS remitted_paid
        FROM passenger_trips pt
        LEFT JOIN passengers p ON p.id = pt.passenger_id
        WHERE pt.driver_id = ? AND pt.boarding_time BETWEEN ? AND ?
          AND COALESCE(pt.archived_status,'no') <> 'yes'
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$did, $start, $end]);
    $live = array_map('floatval', $stmt->fetch(PDO::FETCH_ASSOC) ?: $live);

    $arch = [
        'total_trips' => 0, 'completed' => 0, 'cancelled' => 0, 'pending' => 0,
        'passenger_records' => 0, 'passenger_count' => 0, 'other_count' => 0,
        'other_occupied' => 0, 'passenger_occupied' => 0, 'regular' => 0, 'special' => 0,
        'fare_total' => 0, 'other_fare' => 0, 'passenger_fare' => 0, 'paid' => 0, 'pending_fare' => 0, 'gross_paid' => 0,
    ];
    $stmtA = $db->prepare("
        SELECT
            COUNT(DISTINCT pth.trip_id) AS total_trips,
            COUNT(DISTINCT pth.trip_id) AS completed,
            COUNT(pth.history_id) AS passenger_records,
            SUM(CASE WHEN COALESCE(pth.type,'')='other' THEN 0 ELSE 1 END) AS passenger_count,
            SUM(CASE WHEN COALESCE(pth.type,'')='other' THEN 1 ELSE 0 END) AS other_count,
            SUM(CASE WHEN COALESCE(pth.type,'')='other' THEN COALESCE(pth.space_occupied,1) ELSE 0 END) AS other_occupied,
            SUM(CASE WHEN COALESCE(pth.type,'')!='other' THEN COALESCE(pth.space_occupied,1) ELSE 0 END) AS passenger_occupied,
            SUM(CASE WHEN pth.passenger_type IS NULL OR pth.passenger_type='' OR LOWER(pth.passenger_type)='regular' THEN 1 ELSE 0 END) AS regular,
            SUM(CASE WHEN pth.passenger_type IS NOT NULL AND pth.passenger_type!='' AND LOWER(pth.passenger_type)!='regular' THEN 1 ELSE 0 END) AS special,
            SUM(COALESCE(pth.fare,0)) AS fare_total,
            SUM(CASE WHEN COALESCE(pth.type,'')='other' THEN COALESCE(pth.fare,0) ELSE 0 END) AS other_fare,
            SUM(CASE WHEN COALESCE(pth.type,'')!='other' THEN COALESCE(pth.fare,0) ELSE 0 END) AS passenger_fare,
            SUM(COALESCE(pth.fare,0)) AS paid,
            SUM(COALESCE(pth.fare,0)) AS gross_paid
        FROM passenger_trip_history pth
        WHERE pth.driver_id = ? AND pth.trip_date BETWEEN ? AND ?
    ");
    $stmtA->execute([$did, $start, $end]);
    $arch = array_map('floatval', $stmtA->fetch(PDO::FETCH_ASSOC) ?: $arch);

    /* Chart series are accumulated from the same per-driver predicates used by the
       KPIs and the table, so the chart can never disagree with them (or claim "no
       activity" while a driver row shows trips). Rejected trip fares are excluded
       from the income line; trip counts keep every recorded trip. */
    if ($tripSeries !== null && $fareSeries !== null) {
        $serLive = $db->prepare("
            SELECT DATE(pt.boarding_time) AS day,
                   COUNT(DISTINCT pt.trip_no) AS trips,
                   SUM(CASE WHEN LOWER(COALESCE(pt.payment_status,'')) <> 'rejected' THEN COALESCE(pt.fare,0) ELSE 0 END) AS fare
            FROM passenger_trips pt
            WHERE pt.driver_id = ? AND pt.boarding_time BETWEEN ? AND ?
              AND COALESCE(pt.archived_status,'no') <> 'yes'
            GROUP BY day
        ");
        $serLive->execute([$did, $start, $end]);
        foreach ($serLive->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tripSeries[$r['day']] = ($tripSeries[$r['day']] ?? 0) + (int)$r['trips'];
            $fareSeries[$r['day']] = round(($fareSeries[$r['day']] ?? 0) + (float)$r['fare'], 2);
        }

        $serArch = $db->prepare("
            SELECT pth.trip_date AS day,
                   COUNT(DISTINCT pth.trip_id) AS trips,
                   SUM(COALESCE(pth.fare,0)) AS fare
            FROM passenger_trip_history pth
            WHERE pth.driver_id = ? AND pth.trip_date BETWEEN ? AND ?
            GROUP BY day
        ");
        $serArch->execute([$did, $start, $end]);
        foreach ($serArch->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tripSeries[$r['day']] = ($tripSeries[$r['day']] ?? 0) + (int)$r['trips'];
            $fareSeries[$r['day']] = round(($fareSeries[$r['day']] ?? 0) + (float)$r['fare'], 2);
        }
    }

    /* GAS: fuel_logs filtered by driver_id and the global reporting period (created_at) */
    $gasLiters = 0.0;
    $gasAmount = 0.0;
    $stmtGas = $db->prepare("
        SELECT
            IFNULL(SUM(liters), 0) AS liters,
            IFNULL(SUM(cost), 0) AS amount
        FROM fuel_logs
        WHERE driver_id = ? AND created_at BETWEEN ? AND ?
    ");
    $stmtGas->execute([$did, $start, $end]);
    $gasRow = $stmtGas->fetch(PDO::FETCH_ASSOC);
    if ($gasRow) {
        $gasLiters = (float)$gasRow['liters'];
        $gasAmount = (float)$gasRow['amount'];
    }

    $att = dpm_attendance($db, $did, $buses, $start, $end);
    $presentDays = $att['present'];
    $scheduledDays = $att['scheduled'];
    $absentDays = $att['absent'];
    $attRate = $att['rate'];

    $occ = dpm_occupancy($db, $did, $start, $end);
    $occupiedPassenger = $occ['occupied_passenger'];
    $occupiedOther = $occ['occupied_other'];
    $capacity = $occ['vehicle_capacity'];
    $occupancy = $occ['occupancy'];

    $totalTrips = $live['total_trips'] + $arch['total_trips'];
    $completed = $live['completed'] + $arch['completed'];
    $cancelled = $live['cancelled'] + $arch['cancelled'];
    $pending = $live['pending'] + $arch['pending'];
    $passengers = $live['passenger_count'] + $arch['passenger_count'];
    $other = $live['other_count'] + $arch['other_count'];
    $regular = $live['regular'] + $arch['regular'];
    $special = $live['special'] + $arch['special'];
    $grossTotal = $live['gross_total'] + $arch['fare_total'];
    $fareTotal = $grossTotal;
    $otherFare = $live['other_fare'] + $arch['other_fare'];
    $passFare = $live['passenger_fare'] + $arch['passenger_fare'];
    $paid = $live['paid'] + $arch['paid'];
    $pendingFare = $live['pending_fare'] + $arch['pending_fare'];

    /* Fixed costs are operating costs of running a driver. A driver with no trips in
       the selected period has no activity to charge against, so they receive no
       allocation and their net income is zero instead of a misleading negative. */
    $hasActivity = $totalTrips > 0;
    $fixed = $hasActivity
        ? dpm_fixed_costs(dpm_period_months($period, $start, $end), ['gross' => $grossTotal, 'trips' => $totalTrips])
        : ['amortization' => 0.0, 'insurance' => 0.0, 'gps' => 0.0, 'optr' => 0.0, 'total' => 0.0, 'monthly_total' => 0.0, 'months' => 0.0];
    $grossIncome = round($grossTotal, 2);
    $netIncome = $hasActivity ? round($grossIncome - $fixed['total'] - $gasAmount, 2) : 0.0;

    /* Remittance (source of truth: passenger_trips.remittance_status + remittance_logs).
       remitted_amount = paid income already marked remitted (live) plus archived income
       (history carries no remittance tracking and is settled by definition).
       paid_expected = all paid income for the period. The rate is clamped at 100% so an
       inconsistent row can never produce a misleading over-100% figure. */
    $remittedAmount = round((float)$live['remitted_paid'] + (float)$arch['fare_total'], 2);
    $paidExpected = round((float)$live['gross_paid'] + (float)$arch['gross_paid'], 2);
    $remitRate = $paidExpected > 0 ? min(100.0, round(($remittedAmount / $paidExpected) * 100, 1)) : null;

    $rlog = dpa_remittance_period($db, $did, $start, $end);

    /* Remittance status hierarchy (per driver):
         - NO live passenger_trips in the period (everything from history/bus) ... Remitted if any archive activity, else No Activity.
           This dominates and ignores any remittance log, because passenger_trip_history has no remittance tracking.
         - Has a remittance log .................... its status (Completed/Discrepancy/etc.)
         - All live paid trips already remitted .... Remitted
         - Had live trips to remit but ABSENT ...... Absent
         - Had live trips to remit, present, no log  No Remit
    */
    $hasLive = (int)$live['total_trips'] > 0;                                // needs remittance (live passenger_trips)
    $hasArchive = (int)$arch['total_trips'] > 0 || (float)$arch['gross_paid'] > 0; // history only
    $present = (int)$presentDays > 0;
    $allLivePaidRemitted = $hasLive && (float)$live['gross_paid'] > 0 && (float)$live['paid'] >= (float)$live['gross_paid'];

    if (!$hasLive) {
        // Nothing came from live passenger_trips -> no remittance obligation.
        // Everything came from passenger_trip_history, so mark it as "History".
        $remitStatus = $hasArchive ? 'History' : 'No Activity';
        $remittedVariance = 0.0;
        $remittedActual = 0.0;
        $remittedExpected = 0.0;
    } elseif ($rlog) {
        $remitStatus = in_array($rlog['status'], ['Completed', 'Matched', 'Paid', 'Remitted'], true)
            ? 'Remitted'
            : ($rlog['status'] === 'Discrepancy' ? 'Discrepancy' : ($rlog['status'] ?? 'Remitted'));
        $remittedVariance = round((float)($rlog['discrepancy_amount'] ?? abs((float)($rlog['expected_cash'] ?? 0) - (float)($rlog['actual_cash'] ?? 0))), 2);
        $remittedActual = round((float)($rlog['actual_cash'] ?? 0), 2);
        $remittedExpected = round((float)($rlog['expected_cash'] ?? 0), 2);
    } else {
        $remitStatus = $allLivePaidRemitted ? 'Remitted'
            : ($present ? 'No Remit' : 'Absent');
        $remittedVariance = 0.0;
        $remittedActual = 0.0;
        $remittedExpected = 0.0;
    }

    return [
        'driver_id' => $did,
        'name' => $driver['full_name'] ?? 'Unknown',
        'photo' => $driver['photo'] ?? '',
        'attendance' => [
            'present' => $presentDays,
            'absent' => $absentDays,
            'scheduled' => $scheduledDays,
            'rate' => $attRate,
            'max' => $att['max'] ?? $scheduledDays,
            'extra_days' => $att['extra_days'] ?? 0,
        ],
        'trips' => [
            'total' => (int)$totalTrips,
            'completed' => (int)$completed,
            'cancelled' => (int)$cancelled,
            'pending' => (int)$pending,
        ],
        'passengers' => [
            'total' => (int)$passengers,
            'regular' => (int)$regular,
            'special' => (int)$special,
        ],
        'other' => [
            'records' => (int)$other,
            'occupied_spaces' => (int)$occupiedOther,
        ],
        'fare' => [
            'total' => round($fareTotal, 2),
            'passenger' => round($passFare, 2),
            'other' => round($otherFare, 2),
            'paid' => round($paid, 2),
            'pending' => round($pendingFare, 2),
            'remitted' => round($remittedAmount, 2),
        ],
        'capacity' => [
            'vehicle_capacity' => (int)$capacity,
            'occupied_passenger' => (int)$occupiedPassenger,
            'occupied_other' => (int)$occupiedOther,
            'occupancy' => $occupancy,
            'overcapacity_spaces' => $occ['overcapacity_spaces'] ?? 0,
        ],
        'cancelled_trips' => (int)$cancelled,
        'gross_income' => $grossIncome,
        'fixed_costs' => $fixed,
        'net_income' => $netIncome,
        'gas' => [
            'liters' => round($gasLiters, 2),
            'amount' => round($gasAmount, 2),
        ],
        'remittance_status' => $remitStatus,
        'no_activity' => !$hasActivity,
        'remitted_amount' => $remittedAmount,
        'remitted_expected' => $remittedExpected,
        'remitted_actual' => $remittedActual,
        'remitted_variance' => $remittedVariance,
        'remittance_rate' => $remitRate,
        'paid_expected' => $paidExpected,
        '_dbg' => [
            'hasLiveTrips' => (int)$hasLive,
            'liveTotalTrips' => (int)$live['total_trips'],
            'hasArchive' => (int)$hasArchive,
            'archTotalTrips' => (int)$arch['total_trips'],
            'archGrossPaid' => (float)$arch['gross_paid'],
            'present' => (int)$present,
            'presentDays' => (int)$presentDays,
            'hasRlog' => $rlog ? 1 : 0,
            'rlogStatus' => $rlog['status'] ?? null,
            'allLivePaidRemitted' => (int)$allLivePaidRemitted,
        ],
    ];
}

try {
    $db = Database::get();
    try {
        $db->exec("ALTER TABLE passenger_trip_history ADD COLUMN IF NOT EXISTS space_occupied INT NOT NULL DEFAULT 1 AFTER type");
    } catch (Throwable $e) {
        // column may already exist; ignore
    }
    [$start, $end, $period] = dpa_period_range($_GET);

    $drivers = $db->query(
        "SELECT d.driver_id, MAX(d.full_name) AS full_name, MAX(d.photo) AS photo
         FROM drivers d
         WHERE COALESCE(d.status,'') NOT IN ('Archived','Inactive')
         GROUP BY d.driver_id
         ORDER BY full_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $tripSeries = [];
    $fareSeries = [];
    $rows = [];
    foreach ($drivers as $d) {
        // The chart series are accumulated inside the per-driver computation so they
        // use exactly the same predicates as the KPIs and the driver table.
        $rows[] = dpa_compute_driver($db, $d, $start, $end, $period, $tripSeries, $fareSeries);
    }
    ksort($tripSeries);
    ksort($fareSeries);

    /* Safety fallback: if the driver-scoped accumulation produced no points but
       trips exist in the period, build the series from the period alone (archived
       rows still excluded). The chart must never claim "no activity" while data
       exists — e.g. when trips belong to a driver with no current active row. */
    if (empty($tripSeries) && empty($fareSeries)) {
        $fbLive = $db->prepare("
            SELECT DATE(pt.boarding_time) AS day,
                   COUNT(DISTINCT pt.trip_no) AS trips,
                   SUM(CASE WHEN LOWER(COALESCE(pt.payment_status,'')) <> 'rejected' THEN COALESCE(pt.fare,0) ELSE 0 END) AS fare
            FROM passenger_trips pt
            WHERE pt.boarding_time BETWEEN ? AND ?
              AND COALESCE(pt.archived_status,'no') <> 'yes'
            GROUP BY day
        ");
        $fbLive->execute([$start, $end]);
        foreach ($fbLive->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tripSeries[$r['day']] = ($tripSeries[$r['day']] ?? 0) + (int)$r['trips'];
            $fareSeries[$r['day']] = round(($fareSeries[$r['day']] ?? 0) + (float)$r['fare'], 2);
        }

        $fbArch = $db->prepare("
            SELECT pth.trip_date AS day,
                   COUNT(DISTINCT pth.trip_id) AS trips,
                   SUM(COALESCE(pth.fare,0)) AS fare
            FROM passenger_trip_history pth
            WHERE pth.trip_date BETWEEN ? AND ?
            GROUP BY day
        ");
        $fbArch->execute([$start, $end]);
        foreach ($fbArch->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tripSeries[$r['day']] = ($tripSeries[$r['day']] ?? 0) + (int)$r['trips'];
            $fareSeries[$r['day']] = round(($fareSeries[$r['day']] ?? 0) + (float)$r['fare'], 2);
        }

        ksort($tripSeries);
        ksort($fareSeries);
    }

    $poStmt = $db->prepare("
        SELECT
            SUM(CASE WHEN COALESCE(p.type,'')='other' THEN 0 ELSE 1 END) AS passenger,
            SUM(CASE WHEN COALESCE(p.type,'')='other' THEN 1 ELSE 0 END) AS other
        FROM passenger_trips pt LEFT JOIN passengers p ON p.id = pt.passenger_id
        WHERE pt.boarding_time BETWEEN ? AND ?
    ");
    $poStmt->execute([$start, $end]);
    $poLive = $poStmt->fetch(PDO::FETCH_ASSOC);
    $poA = $db->prepare("
        SELECT
            SUM(CASE WHEN COALESCE(type,'')='other' THEN 0 ELSE 1 END) AS passenger,
            SUM(CASE WHEN COALESCE(type,'')='other' THEN 1 ELSE 0 END) AS other
        FROM passenger_trip_history WHERE trip_date BETWEEN ? AND ?
    ");
    $poA->execute([$start, $end]);
    $poArch = $poA->fetch(PDO::FETCH_ASSOC);
    $passengerTotal = (int)($poLive['passenger'] ?? 0) + (int)($poArch['passenger'] ?? 0);
    $otherTotal = (int)($poLive['other'] ?? 0) + (int)($poArch['other'] ?? 0);

    $sStmt = $db->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN LOWER(payment_status)='paid' THEN trip_no END) AS completed,
            COUNT(DISTINCT CASE WHEN LOWER(payment_status)='rejected' THEN trip_no END) AS cancelled,
            COUNT(DISTINCT CASE WHEN LOWER(payment_status) IN ('pending','ongoing') THEN trip_no END) AS pending
        FROM passenger_trips WHERE boarding_time BETWEEN ? AND ?
    ");
    $sStmt->execute([$start, $end]);
    $statusLive = $sStmt->fetch(PDO::FETCH_ASSOC);
    $completedTotal = (int)$statusLive['completed'];
    $cancelledTotal = (int)$statusLive['cancelled'];
    $pendingTotal = (int)$statusLive['pending'];

    $routeStmt = $db->prepare("
        SELECT CONCAT(boarding_point,' -> ',destination) AS route, COUNT(*) AS c
        FROM passenger_trips WHERE boarding_time BETWEEN ? AND ? AND boarding_point IS NOT NULL AND destination IS NOT NULL
        GROUP BY route
        UNION ALL
        SELECT CONCAT(boarding_point,' -> ',destination) AS route, COUNT(*) AS c
        FROM passenger_trip_history WHERE trip_date BETWEEN ? AND ? AND boarding_point IS NOT NULL AND destination IS NOT NULL
        GROUP BY route
        ORDER BY c DESC LIMIT 10
    ");
    $routeStmt->execute([$start, $end, $start, $end]);
    $routeAgg = [];
    foreach ($routeStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $routeAgg[$r['route']] = ($routeAgg[$r['route']] ?? 0) + (int)$r['c'];
    }
    arsort($routeAgg);
    $mostUsedRoutes = array_slice($routeAgg, 0, 10, true);

    $boardStmt = $db->prepare("
        SELECT boarding_point AS point, COUNT(*) AS c FROM passenger_trips WHERE boarding_time BETWEEN ? AND ? AND boarding_point IS NOT NULL GROUP BY point
        UNION ALL
        SELECT boarding_point AS point, COUNT(*) AS c FROM passenger_trip_history WHERE trip_date BETWEEN ? AND ? AND boarding_point IS NOT NULL GROUP BY point
    ");
    $boardStmt->execute([$start, $end, $start, $end]);
    $boardAgg = [];
    foreach ($boardStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $boardAgg[$r['point']] = ($boardAgg[$r['point']] ?? 0) + (int)$r['c'];
    }
    $boardingDist = $boardAgg;

    $destStmt = $db->prepare("
        SELECT destination AS dest, COUNT(*) AS c FROM passenger_trips WHERE boarding_time BETWEEN ? AND ? AND destination IS NOT NULL GROUP BY dest
        UNION ALL
        SELECT destination AS dest, COUNT(*) AS c FROM passenger_trip_history WHERE trip_date BETWEEN ? AND ? AND destination IS NOT NULL GROUP BY dest
    ");
    $destStmt->execute([$start, $end, $start, $end]);
    $destAgg = [];
    foreach ($destStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $destAgg[$r['dest']] = ($destAgg[$r['dest']] ?? 0) + (int)$r['c'];
    }
    $destDist = $destAgg;

    $aggAtt = ['present' => 0, 'absent' => 0, 'scheduled' => 0, 'max' => 0];
    $aggCap = ['occupied_passenger' => 0, 'occupied_other' => 0, 'vehicle_capacity' => 0];
    $fleetCapResolved = false;
    foreach ($rows as $r) {
        $aggAtt['present'] += $r['attendance']['present'];
        $aggAtt['absent'] += $r['attendance']['absent'];
        $aggAtt['scheduled'] += $r['attendance']['scheduled'];
        $aggAtt['max'] += $r['attendance']['max'] ?? $r['attendance']['scheduled'];
        if (empty($r['capacity']['capacity_unavailable']) && $r['capacity']['vehicle_capacity'] > 0) {
            $aggCap['occupied_passenger'] += $r['capacity']['occupied_passenger'];
            $aggCap['occupied_other'] += $r['capacity']['occupied_other'];
            $aggCap['vehicle_capacity'] += $r['capacity']['vehicle_capacity'];
            $fleetCapResolved = true;
        }
    }
    $aggOccupancy = $fleetCapResolved
        ? min(100.0, round((($aggCap['occupied_passenger'] + $aggCap['occupied_other']) / $aggCap['vehicle_capacity']) * 100, 1))
        : null;

    /* Aggregate remittance (source of truth: remittance_status filter above) */
    $aggRemitted = 0.0;
    $aggPaidExpected = 0.0;
    $aggFinancial = ['gross_income' => 0.0, 'fuel' => 0.0, 'other_expenses' => 0.0, 'net_income' => 0.0];
    foreach ($rows as $r) {
        $aggRemitted += (float)$r['remitted_amount'];
        $aggPaidExpected += (float)$r['paid_expected'];
        $aggFinancial['gross_income'] += (float)$r['gross_income'];
        $aggFinancial['fuel'] += (float)($r['gas']['amount'] ?? 0);
        $aggFinancial['other_expenses'] += (float)($r['fixed_costs']['total'] ?? 0);
        $aggFinancial['net_income'] += (float)$r['net_income'];
    }
    foreach ($aggFinancial as $k => $v) {
        $aggFinancial[$k] = round($v, 2);
    }
    $aggRemitRate = $aggPaidExpected > 0 ? min(100.0, round(($aggRemitted / $aggPaidExpected) * 100, 1)) : null;
    $fullyRemitted = array_filter($rows, fn($r) => in_array($r['remittance_status'], ['Remitted', 'Discrepancy'], true));

    $debug = !empty($_GET['debug']);
    $rowsOut = array_map(function ($r) use ($debug) {
        return $debug ? $r : array_filter($r, fn($k) => $k !== '_dbg', ARRAY_FILTER_USE_KEY);
    }, $rows);

    send_json([
        'success' => true,
        'period' => $period,
        'range' => ['start' => $start, 'end' => $end],
        'drivers' => $rowsOut,
        'remittance' => [
            'remitted_amount' => round($aggRemitted, 2),
            'paid_expected' => round($aggPaidExpected, 2),
            'remittance_rate' => $aggRemitRate,
            'fully_remitted_drivers' => count($fullyRemitted),
            'active_drivers' => count($rows),
        ],
        'financial' => $aggFinancial,
        'charts' => [
            'trips_over_time' => $tripSeries,
            'fare_over_time' => $fareSeries,
            'passenger_other' => ['passenger' => $passengerTotal, 'other' => $otherTotal],
            'trip_status' => ['completed' => $completedTotal, 'cancelled' => $cancelledTotal, 'pending' => $pendingTotal],
            'attendance' => $aggAtt,
            'capacity' => [
                'occupied_passenger' => $aggCap['occupied_passenger'],
                'occupied_other' => $aggCap['occupied_other'],
                'vehicle_capacity' => $aggCap['vehicle_capacity'],
                'occupancy' => $aggOccupancy,
                'capacity_unavailable' => !$fleetCapResolved,
            ],
            'most_used_routes' => $mostUsedRoutes,
            'boarding_distribution' => $boardingDist,
            'destination_distribution' => $destDist,
        ],
    ]);
} catch (Throwable $e) {
    send_json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
}

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
 * Ensure the daily_reports persistence table exists.
 */
function ddr_ensure_table(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS daily_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_date DATE NOT NULL,
            status ENUM('Pending','Finalized') NOT NULL DEFAULT 'Pending',
            report_data LONGTEXT NOT NULL,
            generated_by VARCHAR(100) DEFAULT NULL,
            remittance_log_ref VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_report_date (report_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    try {
        $db->exec("ALTER TABLE passenger_trip_history ADD COLUMN IF NOT EXISTS space_occupied INT NOT NULL DEFAULT 1 AFTER type");
    } catch (Throwable $e) {
        // column may already exist; ignore
    }
}

/**
 * Load the stored report snapshot for a date (if any).
 */
function ddr_load_stored(PDO $db, string $reportDate): ?array
{
    $stmt = $db->prepare("SELECT id, report_date, status, report_data, generated_by, created_at FROM daily_reports WHERE report_date = ? LIMIT 1");
    $stmt->execute([$reportDate]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['report_data'] = json_decode($row['report_data'], true);
    return $row;
}

/**
 * Persist (or update) the report snapshot for a date.
 */
function ddr_save(PDO $db, string $reportDate, string $status, array $snapshot, ?string $generatedBy): int
{
    $json = json_encode($snapshot);
    $stmt = $db->prepare("
        INSERT INTO daily_reports (report_date, status, report_data, generated_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            report_data = VALUES(report_data),
            generated_by = VALUES(generated_by),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$reportDate, $status, $json, $generatedBy]);
    $id = (int)$db->lastInsertId();
    if ($id <= 0) {
        $idStmt = $db->prepare("SELECT id FROM daily_reports WHERE report_date = ? LIMIT 1");
        $idStmt->execute([$reportDate]);
        $id = (int)$idStmt->fetchColumn();
    }
    return $id;
}

/**
 * Drivers that operated on the report date, from bus_trips and passenger activity.
 * These are the drivers expected to have finalized their remittance that day.
 */
function ddr_active_drivers(PDO $db, string $reportDate): array
{
    $drivers = [];

    // bus_trips are scheduling records and do NOT carry per-passenger remittance, so a driver with only
    // a bus_trips entry is included for the report but is NOT required to have a remittance log.
    $btStmt = $db->prepare("
        SELECT DISTINCT d.driver_id, d.full_name, COALESCE(d.photo,'') AS photo
        FROM bus_trips bt
        JOIN drivers d ON d.driver_id = bt.driver_id
        WHERE bt.trip_date = ?
    ");
    $btStmt->execute([$reportDate]);
    foreach ($btStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $drivers[$d['driver_id']] = ['driver_id' => $d['driver_id'], 'full_name' => $d['full_name'], 'photo' => $d['photo'], 'remit_required' => false];
    }

    // Remittance obligation comes ONLY from live passenger_trips (they carry remittance_status etc).
    $ptStmt = $db->prepare("
        SELECT DISTINCT d.driver_id, d.full_name, COALESCE(d.photo,'') AS photo
        FROM passenger_trips pt
        JOIN drivers d ON d.driver_id = pt.driver_id
        WHERE DATE(pt.boarding_time) = ?
    ");
    $ptStmt->execute([$reportDate]);
    foreach ($ptStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $drivers[$d['driver_id']] = ['driver_id' => $d['driver_id'], 'full_name' => $d['full_name'], 'photo' => $d['photo'], 'remit_required' => true];
    }

    // passenger_trip_history is the ARCHIVE exception: it has NO remittance columns, so a driver who
    // appears ONLY in history is not required to have a remittance log. It's still included in the report.
    $pthStmt = $db->prepare("
        SELECT DISTINCT d.driver_id, d.full_name, COALESCE(d.photo,'') AS photo
        FROM passenger_trip_history pth
        JOIN drivers d ON d.driver_id = pth.driver_id
        WHERE pth.trip_date = ?
    ");
    $pthStmt->execute([$reportDate]);
    foreach ($pthStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        if (!isset($drivers[$d['driver_id']])) {
            $drivers[$d['driver_id']] = ['driver_id' => $d['driver_id'], 'full_name' => $d['full_name'], 'photo' => $d['photo'], 'remit_required' => false];
        }
    }

    return array_values($drivers);
}

/**
 * Remittance log for a driver on the report date (source of truth for remittance).
 */
function ddr_remittance_log(PDO $db, string $driverId, string $reportDate): ?array
{
    $stmt = $db->prepare("
        SELECT id, driver_id, total_collection, expected_cash, actual_cash, discrepancy_amount, status, confirmed_by, created_at
        FROM remittance_logs
        WHERE driver_id = ? AND DATE(created_at) = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$driverId, $reportDate]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Determine whether the report date can be finalized, and if not what is missing.
 *
 * A day is finalizable when:
 *   - every paid passenger_trips record that day has remittance_status != 'Pending'
 *   - every active driver has a remittance_log for that day
 */
function ddr_complete_check(PDO $db, string $reportDate): array
{
    $missing = ['unremitted_trips' => [], 'drivers_without_log' => [], 'unfinalized_drivers' => []];

    // 1. paid passenger_trips on the day that are still Pending remittance
    $stmt = $db->prepare("
        SELECT pt.trip_no, pt.driver_id, COUNT(*) AS recs, SUM(pt.fare) AS fare
        FROM passenger_trips pt
        WHERE DATE(pt.boarding_time) = ?
          AND LOWER(pt.payment_status) = 'paid'
          AND COALESCE(pt.remittance_status,'Pending') = 'Pending'
        GROUP BY pt.trip_no, pt.driver_id
    ");
    $stmt->execute([$reportDate]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $missing['unremitted_trips'][] = [
            'trip_no' => $r['trip_no'],
            'driver_id' => $r['driver_id'],
            'records' => (int)$r['recs'],
            'fare' => round((float)$r['fare'], 2),
        ];
    }

    // 2. active drivers (with LIVE records requiring remittance) that have not finalized a remittance log.
    //    Drivers present ONLY via passenger_trip_history (archive, no remittance columns) are exempt.
    $active = ddr_active_drivers($db, $reportDate);
    foreach ($active as $d) {
        if (empty($d['remit_required'])) continue;
        $log = ddr_remittance_log($db, $d['driver_id'], $reportDate);
        if (!$log) {
            $missing['drivers_without_log'][] = $d['driver_id'];
            $missing['unfinalized_drivers'][] = $d['full_name'] . ' (' . $d['driver_id'] . ')';
        }
    }

    // 3. drivers with a remittance log whose passenger_trips still contain Pending records
    foreach ($active as $d) {
        if (empty($d['remit_required'])) continue;
        $log = ddr_remittance_log($db, $d['driver_id'], $reportDate);
        if (!$log) continue;
        $still = $db->prepare("
            SELECT COUNT(*) FROM passenger_trips
            WHERE driver_id = ? AND DATE(boarding_time) = ?
              AND LOWER(payment_status) = 'paid'
              AND COALESCE(remittance_status,'Pending') = 'Pending'
        ");
        $still->execute([$d['driver_id'], $reportDate]);
        if ((int)$still->fetchColumn() > 0) {
            $pendingName = $d['full_name'] . ' (' . $d['driver_id'] . ')';
            if (!in_array($pendingName, $missing['unfinalized_drivers'], true)) {
                $missing['unfinalized_drivers'][] = $pendingName;
            }
        }
    }

    $complete = empty($missing['unremitted_trips']) && empty($missing['drivers_without_log']);
    return ['complete' => $complete, 'missing' => $missing, 'active_drivers' => count($active)];
}

/**
 * Build a per-category validation checklist for the report date using actual DB state.
 * Each category is marked Complete / Incomplete based on real records (no fabrication).
 */
function ddr_validation(PDO $db, string $reportDate, array $check): array
{
    $v = [];

    // Operations: any bus/passenger/"other" activity recorded for the day.
    $opsStmt = $db->prepare("
        SELECT
            (SELECT COUNT(*) FROM bus_trips WHERE trip_date = ?) +
            (SELECT COUNT(*) FROM passenger_trips WHERE DATE(boarding_time) = ?) +
            (SELECT COUNT(*) FROM passenger_trip_history WHERE trip_date = ?)
    ");
    $opsStmt->execute([$reportDate, $reportDate, $reportDate]);
    $ops = (int)$opsStmt->fetchColumn();
    $v['operations'] = ['complete' => $ops > 0, 'note' => $ops > 0 ? ($ops . ' operational record(s) recorded') : 'No operations recorded for this day'];

    // Passenger/Trip: no paid passenger records still pending remittance.
    $unrem = count($check['missing']['unremitted_trips'] ?? []);
    $v['passenger'] = ['complete' => $unrem === 0, 'note' => $unrem === 0 ? 'All paid trips accounted for' : ($unrem . ' paid trip(s) still pending remittance')];

    // Fuel: fuel logs present for the day.
    $fuelStmt = $db->prepare("SELECT COUNT(*) FROM fuel_logs WHERE DATE(created_at) = ?");
    $fuelStmt->execute([$reportDate]);
    $fuel = (int)$fuelStmt->fetchColumn();
    $v['fuel'] = ['complete' => $fuel > 0, 'note' => $fuel > 0 ? ($fuel . ' fuel log(s) recorded') : 'No fuel records recorded for this day'];

    // Attendance: at least one day with recorded activity (present-days basis).
    $attStmt = $db->prepare("
        SELECT COUNT(*) FROM (
            SELECT DATE(boarding_time) AS d FROM passenger_trips WHERE DATE(boarding_time) = ?
            UNION SELECT trip_date AS d FROM passenger_trip_history WHERE trip_date = ?
            UNION SELECT trip_date AS d FROM bus_trips WHERE trip_date = ?
        ) x
    ");
    $attStmt->execute([$reportDate, $reportDate, $reportDate]);
    $att = (int)$attStmt->fetchColumn();
    $v['attendance'] = ['complete' => $att > 0, 'note' => $att > 0 ? ($att . ' day with recorded activity') : 'No attendance recorded for this day'];

    // Remittance: every active (live) driver has a finalized remittance log.
    $noLog = count($check['missing']['drivers_without_log'] ?? []);
    $v['remittance'] = ['complete' => $noLog === 0, 'note' => $noLog === 0 ? 'All drivers finalized their remittance' : ($noLog . ' active driver(s) without a remittance log')];

    // Financial: income & remittance able to be reconciled.
    $finComplete = $v['passenger']['complete'] && $v['remittance']['complete'] && $v['operations']['complete'];
    $v['financial'] = ['complete' => $finComplete, 'note' => $finComplete ? 'Income, fuel and remittance reconciled' : 'Waiting on passenger / remittance / operations data'];

    $ready = true;
    foreach ($v as $row) {
        if (!$row['complete']) {
            $ready = false;
            break;
        }
    }
    $v['ready'] = $ready;
    $v['ready_note'] = $ready ? 'Report Generation Ready' : 'Complete the missing items above';

    return $v;
}

/**
 * Per-driver breakdown + aggregates for a finalized daily report.
 * Reuses dpm_compute (remittance-filtered income) + remittance_logs.
 */
function ddr_build_report(PDO $db, string $reportDate): array
{
    $start = $reportDate . ' 00:00:00';
    $end = $reportDate . ' 23:59:59';
    $period = 'today';

    $active = ddr_active_drivers($db, $reportDate);

    $drivers = [];
    $totals = [
        'trips' => 0, 'passengers' => 0, 'other' => 0,
        'gross_income' => 0.0, 'remittance' => 0.0, 'fuel' => 0.0,
        'fixed_costs' => 0.0, 'net_income' => 0.0, 'variance' => 0.0,
        'attendance_present' => 0, 'attendance_scheduled' => 0,
    ];

    foreach ($active as $d) {
        $m = dpm_compute($db, $d['driver_id'], $start, $end, $period);
        if (isset($m['error'])) {
            $m = ddr_empty($d['driver_id'], $d['full_name'], $d['photo']);
        }
        $rlog = ddr_remittance_log($db, $d['driver_id'], $reportDate);

        // Remittance figures from the authoritative remittance_logs (per driver/day).
        $expected = (float)($rlog['expected_cash'] ?? 0);
        $actual = (float)($rlog['actual_cash'] ?? 0);
        $variance = (float)($rlog['discrepancy_amount'] ?? abs($expected - $actual));
        // Remittance status hierarchy (per driver):
        //   - has remittance log ................... its status
        //   - only archived/bus activity .......... Remitted (no-remit exception)
        //   - was required to remit but ABSENT ..... Absent
        //   - required to remit, present, no log ... No Remit
        $remitRequired = !empty($d['remit_required']);
        $present = (int)($m['attendance']['present'] ?? 0);
        if (!$remitRequired) {
            // Only archived/bus activity (no live passenger_trips) -> no remittance obligation.
            // Dominates any remittance log, since history has no remittance tracking.
            $remitStatus = 'History';
        } elseif ($rlog) {
            $remitStatus = in_array($rlog['status'], ['Completed', 'Matched', 'Paid', 'Remitted'], true)
                ? 'Remitted'
                : ($rlog['status'] === 'Discrepancy' ? 'Discrepancy' : ($rlog['status'] ?? 'Remitted'));
        } elseif (!$present) {
            $remitStatus = 'Absent';
        } else {
            $remitStatus = 'No Remit';
        }
        $remitted = (float)($rlog['actual_cash'] ?? 0);

        // Debug signals: distinguish live passenger_trips from history-only activity.
        $dbgLive = $dbgHistory = 0;
        if (!empty($_GET['debug'])) {
            $dbgLiveStmt = $db->prepare("SELECT COUNT(*) FROM passenger_trips WHERE driver_id = ? AND DATE(boarding_time) = ?");
            $dbgLiveStmt->execute([$d['driver_id'], $reportDate]);
            $dbgLive = (int)$dbgLiveStmt->fetchColumn();
            $dbgHistStmt = $db->prepare("SELECT COUNT(*) FROM passenger_trip_history WHERE driver_id = ? AND trip_date = ?");
            $dbgHistStmt->execute([$d['driver_id'], $reportDate]);
            $dbgHistory = (int)$dbgHistStmt->fetchColumn();
        }

        $fixed = $m['fixed_costs'] ?? dpm_fixed_costs(1);
        $gas = $m['gas'] ?? ['liters' => 0, 'amount' => 0];
        $gross = (float)$m['gross_income'];
        $fixedTotal = (float)$fixed['total'];
        $fuelAmt = (float)$gas['amount'];

        // Net Income = Gross Income (remitted) - fixed costs - fuel (same principle as DPA).
        $net = round($gross - $fixedTotal - $fuelAmt, 2);

        $trips = (int)$m['trips']['total'];
        $pax = (int)$m['passengers']['total'];
        $oth = (int)$m['other']['records'];

        // Suppress "no activity" drivers that truly had nothing (no trips, no pax, no fare) for insight accuracy.
        $noActivity = ($trips === 0 && $pax === 0 && $oth === 0 && $gross <= 0 && $fuelAmt <= 0 && !$rlog);

        $drivers[] = [
            'driver_id' => $d['driver_id'],
            'name' => $d['full_name'],
            'photo' => $d['photo'],
            'attendance' => [
                'present' => (int)$m['attendance']['present'],
                'absent' => (int)($m['attendance']['absent'] ?? 0),
                'scheduled' => (int)$m['attendance']['scheduled'],
                'rate' => $m['attendance']['rate'],
                'max' => (int)($m['attendance']['max'] ?? $m['attendance']['scheduled']),
            ],
            'trips' => $trips,
            'passengers' => $pax,
            'other' => $oth,
            'gross_income' => $gross,
            'remittance' => $remitted,
            'expected_cash' => $expected,
            'actual_cash' => $actual,
            'variance' => $variance,
            'fuel_cost' => $fuelAmt,
            'expenses' => $fixedTotal,
            'net_income' => $net,
            'remittance_status' => $remitStatus,
            'no_activity' => $noActivity,
            '_dbg' => [
                'remit_required' => (int)$remitRequired,
                'live_trips' => $dbgLive,
                'history_trips' => $dbgHistory,
                'present' => $present,
                'has_rlog' => $rlog ? 1 : 0,
                'rlog_status' => $rlog['status'] ?? null,
            ],
        ];

        $totals['trips'] += $trips;
        $totals['passengers'] += $pax;
        $totals['other'] += $oth;
        $totals['gross_income'] += $gross;
        $totals['remittance'] += $remitted;
        $totals['fuel'] += $fuelAmt;
        $totals['fixed_costs'] += $fixedTotal;
        $totals['net_income'] += $net;
        $totals['variance'] += $variance;
        $totals['attendance_present'] += (int)$m['attendance']['present'];
        $totals['attendance_scheduled'] += (int)$m['attendance']['scheduled'];
    }

    foreach (['gross_income', 'remittance', 'fuel', 'fixed_costs', 'net_income', 'variance'] as $k) {
        $totals[$k] = round($totals[$k], 2);
    }
    $totals['active_drivers'] = count($active);
    $totals['remittance_rate'] = $totals['gross_income'] > 0 ? round(($totals['remittance'] / $totals['gross_income']) * 100, 1) : 100.0;
    $totals['financial'] = [
        'gross_income' => $totals['gross_income'],
        'fuel' => $totals['fuel'],
        'other_expenses' => $totals['fixed_costs'],
        'net_income' => $totals['net_income'],
    ];

    // Daily insights derived from the computed breakdown.
    $insights = ddr_insights($db, $reportDate, $drivers, $totals);

    // Validation checklist (finalized => all expected complete).
    $completeCheck = ddr_complete_check($db, $reportDate);

    $debug = !empty($_GET['debug']);
    $driversOut = array_map(function ($d) use ($debug) {
        return $debug ? $d : array_filter($d, fn($k) => $k !== '_dbg', ARRAY_FILTER_USE_KEY);
    }, $drivers);

    return [
        'report_date' => $reportDate,
        'status' => 'Finalized',
        'totals' => $totals,
        'drivers' => $driversOut,
        'insights' => $insights,
        'active_drivers' => count($active),
        'validation' => ddr_validation($db, $reportDate, $completeCheck),
    ];
}

function ddr_empty(string $driverId, string $name, string $photo): array
{
    return [
        'driver_id' => $driverId, 'name' => $name, 'photo' => $photo,
        'attendance' => ['present' => 0, 'scheduled' => 0, 'rate' => '0.0'],
        'trips' => ['total' => 0, 'completed' => 0, 'cancelled' => 0, 'pending' => 0],
        'passengers' => ['total' => 0, 'regular' => 0, 'special' => 0],
        'other' => ['records' => 0, 'occupied_spaces' => 0],
        'fare' => ['total' => 0, 'passenger' => 0, 'other' => 0, 'paid' => 0, 'pending' => 0],
        'capacity' => ['vehicle_capacity' => 0, 'occupied_passenger' => 0, 'occupied_other' => 0, 'occupancy' => null, 'overcapacity_spaces' => 0],
        'gross_income' => 0, 'fixed_costs' => dpm_fixed_costs(1), 'net_income' => 0,
        'gas' => ['liters' => 0, 'amount' => 0],
    ];
}

function ddr_insights(PDO $db, string $reportDate, array $drivers, array $totals): array
{
    $insights = [];

    $withPax = array_values(array_filter($drivers, fn($d) => $d['passengers'] > 0));
    if ($withPax) {
        usort($withPax, fn($a, $b) => $b['passengers'] <=> $a['passengers']);
        $insights['highest_passenger_volume'] = $withPax[0]['name'] . ' (' . $withPax[0]['passengers'] . ' passengers)';
    }

    $withGross = array_values(array_filter($drivers, fn($d) => $d['gross_income'] > 0));
    if ($withGross) {
        usort($withGross, fn($a, $b) => $b['gross_income'] <=> $a['gross_income']);
        $insights['highest_gross_income'] = $withGross[0]['name'] . ' (₱' . number_format($withGross[0]['gross_income'], 2) . ')';
    }

    $noActivity = array_values(array_filter($drivers, fn($d) => $d['no_activity']));
    $insights['no_activity'] = array_map(fn($d) => $d['name'] . ' (' . $d['driver_id'] . ')', $noActivity);

    $attendanceIssues = array_values(array_filter($drivers, fn($d) => $d['attendance']['present'] === 0 && $d['attendance']['scheduled'] > 0));
    $insights['attendance_issues'] = array_map(fn($d) => $d['name'] . ' (' . $d['driver_id'] . ')', $attendanceIssues);

    $discrepancies = array_values(array_filter($drivers, fn($d) => $d['variance'] > 0 || in_array(strtolower((string)$d['remittance_status']), ['discrepancy', 'no remit'], true)));
    $insights['remittance_discrepancies'] = array_map(
        fn($d) => $d['name'] . ' (' . $d['driver_id'] . ') — status ' . $d['remittance_status'] . ', variance ₱' . number_format($d['variance'], 2),
        $discrepancies
    );

    $incomplete = array_values(array_filter($drivers, fn($d) => $d['remittance_status'] === 'No Remit' || $d['variance'] > 0));
    $insights['incomplete_records'] = array_map(fn($d) => $d['name'] . ' (' . $d['driver_id'] . ')', $incomplete);

    return $insights;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {

try {
    $db = Database::get();
    ddr_ensure_table($db);

    $reportDate = trim((string)($_GET['report_date'] ?? ''));
    if (empty($reportDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
        date_default_timezone_set('Asia/Manila');
        $reportDate = date('Y-m-d', strtotime('yesterday'));
    }

    $generatedBy = ($user['username'] ?? ($user['name'] ?? 'admin'));

    $forceGenerate = (isset($_GET['generate']) && (int)$_GET['generate'] === 1);

    // If a finalized snapshot already exists, return it (stable finalized report) — unless regenerating.
    $stored = ddr_load_stored($db, $reportDate);
    if (!$forceGenerate && $stored && $stored['status'] === 'Finalized') {
        $stored['report_data']['stored_id'] = $stored['id'];
        $stored['report_data']['generated_at'] = $stored['created_at'];
        $stored['report_data']['generated_by'] = $stored['generated_by'];
        send_json(['success' => true, 'stored' => true, 'data' => $stored['report_data']]);
    }

    $check = ddr_complete_check($db, $reportDate);

    // Dry-run "check only" mode: report the true closing status WITHOUT generating/finalizing.
    // Used by the closing-status card and validation checklist so the UI can show
    // Pending Closing / Ready to Close before the user explicitly generates the report.
    if (isset($_GET['check']) && (int)$_GET['check'] === 1) {
        $snapshot = [
            'report_date' => $reportDate,
            'status' => $check['complete'] ? 'Ready' : 'Pending',
            'active_drivers' => $check['active_drivers'],
            'missing' => $check['missing'],
            'validation' => ddr_validation($db, $reportDate, $check),
            'totals' => null,
            'drivers' => [],
            'insights' => [],
        ];
        send_json(['success' => true, 'stored' => false, 'data' => $snapshot]);
    }

    // If incomplete, return Pending Closing with what's missing.
    if (!$check['complete']) {
        $snapshot = [
            'report_date' => $reportDate,
            'status' => 'Pending',
            'active_drivers' => $check['active_drivers'],
            'missing' => $check['missing'],
            'validation' => ddr_validation($db, $reportDate, $check),
            'totals' => null,
            'drivers' => [],
            'insights' => [],
        ];
        // Persist pending state so 'Generate Daily Report' can become available once complete.
        if ($stored && $stored['status'] === 'Pending') {
            $snapshot['stored_id'] = $stored['id'];
            $snapshot['generated_at'] = $stored['created_at'];
        } else {
            $snapshot['stored_id'] = ddr_save($db, $reportDate, 'Pending', $snapshot, $generatedBy);
        }
        send_json(['success' => true, 'stored' => true, 'data' => $snapshot]);
    }

    // Complete -> build + persist the finalized report.
    $report = ddr_build_report($db, $reportDate);
    $report['stored_id'] = ddr_save($db, $reportDate, 'Finalized', $report, $generatedBy);
    $report['generated_at'] = date('Y-m-d H:i:s');
    $report['generated_by'] = $generatedBy;

    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/services/AuditService.php';
        $driverCount = count($report['drivers'] ?? []);
        \AuditService::log(
            'Driver Performance',
            'Daily Report Generated',
            "Finalized daily closing report for {$reportDate} with {$driverCount} driver(s)",
            $reportDate,
            null,
            ['report_date' => $reportDate, 'generated_by' => $generatedBy, 'status' => 'Finalized'],
            'Success'
        );
    } catch (Throwable $e) {
        error_log('Audit log error (daily report): ' . $e->getMessage());
    }

    send_json(['success' => true, 'stored' => true, 'data' => $report]);
} catch (Throwable $e) {
    send_json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
}

}

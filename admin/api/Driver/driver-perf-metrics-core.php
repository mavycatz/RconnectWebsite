<?php
declare(strict_types=1);

use App\Core\Database;

require_once __DIR__ . '/driver-perf-occupancy.php';

function dpm_period_range(array $get, ?DateTime $now = null): array
{
    $period = strtolower(trim((string)($get['period'] ?? 'monthly')));
    if (!$now) {
        $now = new DateTime();
    }
    switch ($period) {
        case 'today':
            return [$now->format('Y-m-d 00:00:00'), $now->format('Y-m-d 23:59:59'), $period];
        case 'daily':
            $d = trim((string)($get['date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $d = $now->format('Y-m-d');
            }
            return [$d . ' 00:00:00', $d . ' 23:59:59', $period];
        case 'weekly':
            $mon = clone $now;
            $wd = (int)$now->format('N');
            $mon->modify('-' . ($wd - 1) . ' days');
            return [$mon->format('Y-m-d 00:00:00'), $mon->modify('+6 days')->format('Y-m-d 23:59:59'), $period];
        case 'annually':
        case 'annual':
            return [$now->format('Y-01-01 00:00:00'), $now->format('Y-12-31 23:59:59'), 'annually'];
        case 'semi_annually':
        case 'semi-annually':
        case 'semiannual':
            $half = ((int)$now->format('n') <= 6) ? 1 : 2;
            $hStart = new DateTime($now->format('Y') . ($half === 1 ? '-01-01' : '-07-01'));
            $hEnd = (clone $hStart)->modify('+6 months')->modify('-1 day');
            return [$hStart->format('Y-m-d 00:00:00'), $hEnd->format('Y-m-d 23:59:59'), 'semi_annually'];
        case 'quarterly':
        case 'quarter':
            $q = (int)ceil(((int)$now->format('n')) / 3);
            $qStart = new DateTime($now->format('Y') . '-' . str_pad((string)(($q - 1) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01');
            $qEnd = (clone $qStart)->modify('+3 months')->modify('-1 day');
            return [$qStart->format('Y-m-d 00:00:00'), $qEnd->format('Y-m-d 23:59:59'), 'quarterly'];
        case 'yearly':
            return [$now->format('Y-01-01 00:00:00'), $now->format('Y-12-31 23:59:59'), $period];
        case 'custom':
            $s = trim((string)($get['start'] ?? ''));
            $e = trim((string)($get['end'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $e)) {
                if ($s > $e) {
                    [$s, $e] = [$e, $s];
                }
                return [$s . ' 00:00:00', $e . ' 23:59:59', $period];
            }
        default:
            return [$now->format('Y-m-01 00:00:00'), $now->format('Y-m-t 23:59:59'), 'monthly'];
    }
}

/**
 * Human-readable label for a reporting period (used in report titles).
 */
function dpm_period_label(?string $period, string $start, string $end): string
{
    $labels = [
        'today' => 'Today', 'daily' => 'Daily', 'weekly' => 'Weekly',
        'monthly' => 'Monthly', 'quarterly' => 'Quarterly',
        'semi_annually' => 'Semi-Annually', 'yearly' => 'Yearly',
        'annually' => 'Annually', 'custom' => 'Custom Range',
    ];
    $p = strtolower((string)$period);
    $label = $labels[$p] ?? ucfirst($p);
    $s = substr($start, 0, 10);
    $e = substr($end, 0, 10);
    return $label . ' (' . $s . ' to ' . $e . ')';
}

function dpm_compute(PDO $db, string $driverId, string $start, string $end, ?string $period = null): array
{
    try {
        $db->exec("ALTER TABLE passenger_trip_history ADD COLUMN IF NOT EXISTS space_occupied INT NOT NULL DEFAULT 1 AFTER type");
    } catch (Throwable $e) {
        // column may already exist; ignore
    }

    $did = $driverId;
    $stmtD = $db->prepare("SELECT driver_id, full_name, photo FROM drivers WHERE driver_id = ? LIMIT 1");
    $stmtD->execute([$did]);
    $driver = $stmtD->fetch(PDO::FETCH_ASSOC);
    if (!$driver) {
        return ['error' => 'Driver not found'];
    }

    $busesStmt = $db->prepare("SELECT DISTINCT bus_id FROM driver_bus_assignment WHERE driver_id = ?");
    $busesStmt->execute([$did]);
    $buses = array_map('strval', $busesStmt->fetchAll(PDO::FETCH_COLUMN));
    $busPlaces = $buses ? implode(',', array_fill(0, count($buses), '?')) : null;

    $live = $db->prepare("
        SELECT
            COUNT(DISTINCT pt.trip_no) AS total_trips,
            COUNT(DISTINCT CASE WHEN LOWER(pt.payment_status)='paid' THEN pt.trip_no END) AS completed,
            COUNT(DISTINCT CASE WHEN LOWER(pt.payment_status)='rejected' THEN pt.trip_no END) AS cancelled,
            COUNT(DISTINCT CASE WHEN LOWER(pt.payment_status) IN ('pending','ongoing') THEN pt.trip_no END) AS pending,
            SUM(CASE WHEN COALESCE(p.type,'')='other' THEN 0 ELSE 1 END) AS passenger_count,
            SUM(CASE WHEN COALESCE(p.type,'')='other' THEN 1 ELSE 0 END) AS other_count,
            SUM(CASE WHEN COALESCE(p.type,'')='other' THEN COALESCE(p.space_occupied,1) ELSE 0 END) AS other_occupied,
            SUM(CASE WHEN COALESCE(p.type,'')!='other' THEN COALESCE(p.space_occupied,1) ELSE 0 END) AS passenger_occupied,
            SUM(CASE WHEN p.discount_type IS NULL OR p.discount_type='' OR LOWER(p.discount_type)='regular' THEN 1 ELSE 0 END) AS regular,
            SUM(CASE WHEN p.discount_type IS NOT NULL AND p.discount_type!='' AND LOWER(p.discount_type)!='regular' THEN 1 ELSE 0 END) AS special,
            SUM(CASE WHEN COALESCE(pt.remittance_status,'Pending') <> 'Pending' THEN COALESCE(pt.fare,0) ELSE 0 END) AS fare_total,
            SUM(CASE WHEN COALESCE(pt.remittance_status,'Pending') <> 'Pending' AND COALESCE(p.type,'')='other' THEN COALESCE(pt.fare,0) ELSE 0 END) AS other_fare,
            SUM(CASE WHEN COALESCE(pt.remittance_status,'Pending') <> 'Pending' AND COALESCE(p.type,'')!='other' THEN COALESCE(pt.fare,0) ELSE 0 END) AS passenger_fare,
            SUM(CASE WHEN COALESCE(pt.remittance_status,'Pending') <> 'Pending' AND LOWER(pt.payment_status)='paid' THEN COALESCE(pt.fare,0) ELSE 0 END) AS paid,
            SUM(CASE WHEN LOWER(pt.payment_status) IN ('pending','ongoing') THEN COALESCE(pt.fare,0) ELSE 0 END) AS pending_fare
        FROM passenger_trips pt
        LEFT JOIN passengers p ON p.id = pt.passenger_id
        WHERE pt.driver_id = ? AND pt.boarding_time BETWEEN ? AND ?
    ");
    $live->execute([$did, $start, $end]);
    $live = $live->fetch(PDO::FETCH_ASSOC) ?: [];

    $arch = $db->prepare("
        SELECT
            COUNT(DISTINCT pth.trip_id) AS total_trips,
            COUNT(DISTINCT pth.trip_id) AS completed,
            SUM(CASE WHEN COALESCE(pth.type,'')='other' THEN 0 ELSE 1 END) AS passenger_count,
            SUM(CASE WHEN COALESCE(pth.type,'')='other' THEN 1 ELSE 0 END) AS other_count,
            SUM(CASE WHEN COALESCE(pth.type,'')='other' THEN COALESCE(pth.space_occupied,1) ELSE 0 END) AS other_occupied,
            SUM(CASE WHEN COALESCE(pth.type,'')!='other' THEN COALESCE(pth.space_occupied,1) ELSE 0 END) AS passenger_occupied,
            SUM(CASE WHEN pth.passenger_type IS NULL OR pth.passenger_type='' OR LOWER(pth.passenger_type)='regular' THEN 1 ELSE 0 END) AS regular,
            SUM(CASE WHEN pth.passenger_type IS NOT NULL AND pth.passenger_type!='' AND LOWER(pth.passenger_type)!='regular' THEN 1 ELSE 0 END) AS special,
            SUM(COALESCE(pth.fare,0)) AS fare_total,
            SUM(CASE WHEN COALESCE(pth.type,'')='other' THEN COALESCE(pth.fare,0) ELSE 0 END) AS other_fare,
            SUM(CASE WHEN COALESCE(pth.type,'')!='other' THEN COALESCE(pth.fare,0) ELSE 0 END) AS passenger_fare,
            SUM(COALESCE(pth.fare,0)) AS paid
        FROM passenger_trip_history pth
        WHERE pth.driver_id = ? AND pth.trip_date BETWEEN ? AND ?
    ");
    $arch->execute([$did, $start, $end]);
    $arch = $arch->fetch(PDO::FETCH_ASSOC) ?: [];

    /* GAS: fuel_logs filtered by driver_id and the global reporting period (created_at) */
    $gasLiters = 0.0;
    $gasAmount = 0.0;
    $stmtGas = $db->prepare("
        SELECT IFNULL(SUM(liters), 0) AS liters, IFNULL(SUM(cost), 0) AS amount
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
    $occupiedPassenger = (float)$occ['occupied_passenger'];
    $occupiedOther = (float)$occ['occupied_other'];
    $capacity = (float)$occ['vehicle_capacity'];
    $occupancy = $occ['occupancy'];

    $grossIncome = round((float)($live['fare_total'] ?? 0) + (float)($arch['fare_total'] ?? 0), 2);
    $periodTrips = (int)(($live['total_trips'] ?? 0) + ($arch['total_trips'] ?? 0));
    $fixed = dpm_fixed_costs(dpm_period_months($period, $start, $end), ['gross' => $grossIncome, 'trips' => $periodTrips]);
    $netIncome = round($grossIncome - $fixed['total'] - $gasAmount, 2);

    $data = [
        'driver_id' => $did,
        'name' => $driver['full_name'],
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
            'total' => (int)((float)($live['total_trips'] ?? 0) + (float)($arch['total_trips'] ?? 0)),
            'completed' => (int)((float)($live['completed'] ?? 0) + (float)($arch['completed'] ?? 0)),
            'cancelled' => (int)((float)($live['cancelled'] ?? 0) + (float)($arch['cancelled'] ?? 0)),
            'pending' => (int)((float)($live['pending'] ?? 0) + (float)($arch['pending'] ?? 0)),
        ],
        'passengers' => [
            'total' => (int)((float)($live['passenger_count'] ?? 0) + (float)($arch['passenger_count'] ?? 0)),
            'regular' => (int)((float)($live['regular'] ?? 0) + (float)($arch['regular'] ?? 0)),
            'special' => (int)((float)($live['special'] ?? 0) + (float)($arch['special'] ?? 0)),
        ],
        'other' => [
            'records' => (int)((float)($live['other_count'] ?? 0) + (float)($arch['other_count'] ?? 0)),
            'occupied_spaces' => (int)$occupiedOther,
        ],
        'fare' => [
            'total' => round((float)($live['fare_total'] ?? 0) + (float)($arch['fare_total'] ?? 0), 2),
            'passenger' => round((float)($live['passenger_fare'] ?? 0) + (float)($arch['passenger_fare'] ?? 0), 2),
            'other' => round((float)($live['other_fare'] ?? 0) + (float)($arch['other_fare'] ?? 0), 2),
            'paid' => round((float)($live['paid'] ?? 0) + (float)($arch['paid'] ?? 0), 2),
            'pending' => round((float)($live['pending_fare'] ?? 0) + (float)($arch['pending_fare'] ?? 0), 2),
        ],
        'capacity' => [
            'vehicle_capacity' => (int)$capacity,
            'occupied_passenger' => (int)$occupiedPassenger,
            'occupied_other' => (int)$occupiedOther,
            'occupancy' => $occupancy,
            'overcapacity_spaces' => $occ['overcapacity_spaces'] ?? 0,
        ],
        'gross_income' => $grossIncome,
        'fixed_costs' => $fixed,
        'net_income' => $netIncome,
        'gas' => [
            'liters' => round($gasLiters, 2),
            'amount' => round($gasAmount, 2),
        ],
    ];

    $daily = $db->prepare("
        SELECT DATE(boarding_time) AS day, COUNT(DISTINCT trip_no) AS trips, COUNT(id) AS passengers, SUM(fare) AS fare
        FROM passenger_trips WHERE driver_id = ? AND boarding_time BETWEEN ? AND ? GROUP BY day
    ");
    $daily->execute([$did, $start, $end]);
    $dailyRows = $daily->fetchAll(PDO::FETCH_ASSOC);
    $dailyA = $db->prepare("
        SELECT trip_date AS day, COUNT(DISTINCT trip_id) AS trips, COUNT(history_id) AS passengers, SUM(fare) AS fare
        FROM passenger_trip_history WHERE driver_id = ? AND trip_date BETWEEN ? AND ? GROUP BY day
    ");
    $dailyA->execute([$did, $start, $end]);
    foreach ($dailyA->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $found = false;
        foreach ($dailyRows as &$dr) {
            if ($dr['day'] === $r['day']) {
                $dr['trips'] += (int)$r['trips'];
                $dr['passengers'] += (int)$r['passengers'];
                $dr['fare'] = round((float)($dr['fare'] ?? 0) + (float)$r['fare'], 2);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $dailyRows[] = $r;
        }
    }
    $data['daily_performance'] = $dailyRows;

    $tripRecords = $db->prepare("
        SELECT trip_no AS trip, DATE(boarding_time) AS day, COUNT(id) AS records, SUM(fare) AS fare, MAX(payment_status) AS payment_status
        FROM passenger_trips WHERE driver_id = ? AND boarding_time BETWEEN ? AND ? GROUP BY trip_no, day
    ");
    $tripRecords->execute([$did, $start, $end]);
    $data['trip_records'] = $tripRecords->fetchAll(PDO::FETCH_ASSOC);

    $passRecords = $db->prepare("
        SELECT p.full_name, COALESCE(p.type,'passenger') AS type, COALESCE(p.space_occupied,1) AS space_occupied, pt.fare, pt.payment_method, DATE(pt.boarding_time) AS day
        FROM passenger_trips pt LEFT JOIN passengers p ON p.id = pt.passenger_id
        WHERE pt.driver_id = ? AND pt.boarding_time BETWEEN ? AND ?
    ");
    $passRecords->execute([$did, $start, $end]);
    $data['passengers_records'] = $passRecords->fetchAll(PDO::FETCH_ASSOC);

    $otherItems = $db->prepare("
        SELECT p.full_name, COALESCE(p.type,'passenger') AS type, COALESCE(p.space_occupied,1) AS space_occupied, pt.fare, pt.boarding_point, pt.destination, DATE(pt.boarding_time) AS day
        FROM passenger_trips pt LEFT JOIN passengers p ON p.id = pt.passenger_id
        WHERE pt.driver_id = ? AND pt.boarding_time BETWEEN ? AND ? AND COALESCE(p.type,'')='other'
    ");
    $otherItems->execute([$did, $start, $end]);
    $otherLive = $otherItems->fetchAll(PDO::FETCH_ASSOC);
    $otherA = $db->prepare("
        SELECT passenger_name AS full_name, COALESCE(type,'passenger') AS type, COALESCE(space_occupied,1) AS space_occupied, fare, boarding_point, destination, trip_date AS day
        FROM passenger_trip_history WHERE driver_id = ? AND trip_date BETWEEN ? AND ? AND COALESCE(type,'')='other'
    ");
    $otherA->execute([$did, $start, $end]);
    $data['other_items'] = array_merge($otherLive, $otherA->fetchAll(PDO::FETCH_ASSOC));

    $pay = $db->prepare("
        SELECT payment_method, LOWER(payment_status) AS status, COUNT(*) AS count, SUM(fare) AS amount
        FROM passenger_trips WHERE driver_id = ? AND boarding_time BETWEEN ? AND ? GROUP BY payment_method, status
    ");
    $pay->execute([$did, $start, $end]);
    $data['payments'] = $pay->fetchAll(PDO::FETCH_ASSOC);

    $routeStmt = $db->prepare("
        SELECT CONCAT(boarding_point,' -> ',destination) AS route, COUNT(*) AS c
        FROM passenger_trips WHERE driver_id = ? AND boarding_time BETWEEN ? AND ? AND boarding_point IS NOT NULL AND destination IS NOT NULL
        GROUP BY route
        UNION ALL
        SELECT CONCAT(boarding_point,' -> ',destination) AS route, COUNT(*) AS c
        FROM passenger_trip_history WHERE driver_id = ? AND trip_date BETWEEN ? AND ? AND boarding_point IS NOT NULL AND destination IS NOT NULL
        GROUP BY route
        ORDER BY c DESC LIMIT 5
    ");
    $routeStmt->execute([$did, $start, $end, $did, $start, $end]);
    $routeAgg = [];
    foreach ($routeStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $routeAgg[$r['route']] = ($routeAgg[$r['route']] ?? 0) + (int)$r['c'];
    }
    arsort($routeAgg);
    $data['top_routes'] = array_slice($routeAgg, 0, 5, true);

    return $data;
}

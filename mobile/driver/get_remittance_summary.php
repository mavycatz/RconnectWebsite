<?php

header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$driver_id = $_GET['driver_id'] ?? '';
$bus_no = $_GET['bus_no'] ?? '';
$trip_no = $_GET['trip_no'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$remittance_status = $_GET['remittance_status'] ?? '';

$where = [];
$params = [];
$types = '';

if (!empty($driver_id)) {
    $where[] = "pt.driver_id = ?";
    $params[] = $driver_id;
    $types .= 's';
}

if (!empty($bus_no)) {
    $where[] = "pt.bus_no = ?";
    $params[] = $bus_no;
    $types .= 's';
}

if (!empty($trip_no)) {
    $where[] = "pt.trip_no = ?";
    $params[] = $trip_no;
    $types .= 's';
}

if (!empty($remittance_status)) {
    $where[] = "pt.remittance_status = ?";
    $params[] = $remittance_status;
    $types .= 's';
}

$phtTz = new DateTimeZone('Asia/Manila');

if (!empty($date_from) && !empty($date_to)) {
    $from = new DateTime($date_from);
    $to = new DateTime($date_to);
    $diff = $from->diff($to);
    if ($diff->days > 90) {
        echo json_encode([
            "success" => false,
            "message" => "Date range exceeds 90-day limit. Please narrow your date range."
        ]);
        exit;
    }
    $where[] = "pt.boarding_time >= ?";
    $params[] = "$date_from 00:00:00";
    $types .= 's';
    $where[] = "pt.boarding_time <= ?";
    $params[] = "$date_to 23:59:59";
    $types .= 's';
} elseif (!empty($date_from)) {
    $where[] = "pt.boarding_time >= ?";
    $params[] = "$date_from 00:00:00";
    $types .= 's';
} elseif (!empty($date_to)) {
    $where[] = "pt.boarding_time <= ?";
    $params[] = "$date_to 23:59:59";
    $types .= 's';
} else {
    $today = (new DateTime('now', $phtTz))->format('Y-m-d');
    $where[] = "pt.boarding_time >= ?";
    $params[] = "$today 00:00:00";
    $types .= 's';
    $where[] = "pt.boarding_time <= ?";
    $params[] = "$today 23:59:59";
    $types .= 's';
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$selectSql = "
    SELECT
        pt.id,
        pt.passenger_id,
        pt.bus_no,
        pt.trip_no,
        pt.driver_id,
        pt.boarding_point,
        pt.destination,
        pt.fare,
        pt.payment_method,
        pt.payment_status,
        pt.cash_amount,
        pt.gcash_amount,
        pt.boarding_time,
        pt.dropoff_time,
        pt.remittance_status,
        pt.expected_cash,
        pt.actual_cash,
        pt.discrepancy_amount,
        pt.finance_note,
        pt.remitted_by,
        pt.remitted_at,
        (pt.expected_cash - pt.actual_cash) AS computed_discrepancy
    FROM passenger_trips pt
    $whereClause
    ORDER BY pt.boarding_time DESC
";

$stmt = $conn->prepare($selectSql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Query preparation failed: " . $conn->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$trips = [];
$total_fare = 0;
$total_cash_amount = 0;
$total_cash_collected = 0;
$total_gcash_amount = 0;
$total_expected_cash = 0;
$total_actual_cash = 0;
$total_discrepancy = 0;
$by_driver = [];
$by_bus = [];

while ($row = $result->fetch_assoc()) {
    $row['discrepancy_amount'] = round((float)$row['expected_cash'] - (float)$row['actual_cash'], 2);
    $trips[] = $row;

    $fare = (float)$row['fare'];
    $payment_method = $row['payment_method'] ?? 'cash';
    $payment_status = strtolower($row['payment_status'] ?? 'pending');
    $cash = (float)$row['cash_amount'];
    $gcash = (float)$row['gcash_amount'];
    $exp = (float)$row['expected_cash'];
    $act = (float)$row['actual_cash'];
    $disc = (float)$row['discrepancy_amount'];

    $isPaid = $payment_status === 'paid';
    if ($isPaid && $payment_method === 'cash') {
        $cash_collected = $fare;
    } elseif ($isPaid && $payment_method === 'hybrid') {
        $cash_collected = $cash;
    } else {
        $cash_collected = 0;
    }

    if ($isPaid) {
        $total_fare += $fare;
    }
    $total_cash_amount += $cash;
    $total_cash_collected += $cash_collected;
    $total_gcash_amount += $gcash;
    $total_expected_cash += $exp;
    $total_actual_cash += $act;
    $total_discrepancy += $disc;

    $drv = $row['driver_id'] ?? 'unknown';
    if (!isset($by_driver[$drv])) {
        $by_driver[$drv] = ['total_fare' => 0, 'total_cash_amount' => 0, 'total_cash_collected' => 0, 'total_gcash_amount' => 0, 'total_expected_cash' => 0, 'total_actual_cash' => 0, 'total_discrepancy' => 0, 'trip_count' => 0];
    }
    if ($isPaid) {
        $by_driver[$drv]['total_fare'] += $fare;
    }
    $by_driver[$drv]['total_cash_amount'] += $cash;
    $by_driver[$drv]['total_cash_collected'] += $cash_collected;
    $by_driver[$drv]['total_gcash_amount'] += $gcash;
    $by_driver[$drv]['total_expected_cash'] += $exp;
    $by_driver[$drv]['total_actual_cash'] += $act;
    $by_driver[$drv]['total_discrepancy'] += $disc;
    if ($isPaid) {
        $by_driver[$drv]['trip_count']++;
    }

    $bus = $row['bus_no'] ?? 'unknown';
    if (!isset($by_bus[$bus])) {
        $by_bus[$bus] = ['total_fare' => 0, 'total_cash_amount' => 0, 'total_cash_collected' => 0, 'total_gcash_amount' => 0, 'total_expected_cash' => 0, 'total_actual_cash' => 0, 'total_discrepancy' => 0, 'trip_count' => 0];
    }
    if ($isPaid) {
        $by_bus[$bus]['total_fare'] += $fare;
    }
    $by_bus[$bus]['total_cash_amount'] += $cash;
    $by_bus[$bus]['total_cash_collected'] += $cash_collected;
    $by_bus[$bus]['total_gcash_amount'] += $gcash;
    $by_bus[$bus]['total_expected_cash'] += $exp;
    $by_bus[$bus]['total_actual_cash'] += $act;
    $by_bus[$bus]['total_discrepancy'] += $disc;
    $by_bus[$bus]['trip_count']++;
}

/*
|--------------------------------------------------------------------------
| GET TODAY'S BUS TRIPS FOR CONTEXT
|--------------------------------------------------------------------------
*/
$bus_trips_today = [];
if (!empty($driver_id)) {
    $bt_date_from = !empty($date_from) ? $date_from : (new DateTime('now', $phtTz))->format('Y-m-d');
    $bt_date_to = !empty($date_to) ? $date_to : (new DateTime('now', $phtTz))->format('Y-m-d');

    $bt_sql = "
        SELECT
            id, bus_id, route_name, trip_date, trip_number,
            departure_time, arrival_time, total_passengers,
            total_revenue, status, trip_type
        FROM bus_trips
        WHERE driver_id = ?
        AND trip_date >= ?
        AND trip_date <= ?
        ORDER BY trip_date DESC, departure_time DESC
    ";
    $bt_stmt = $conn->prepare($bt_sql);
    if ($bt_stmt) {
        $bt_stmt->bind_param("sss", $driver_id, $bt_date_from, $bt_date_to);
        $bt_stmt->execute();
        $bt_result = $bt_stmt->get_result();
        while ($bt_row = $bt_result->fetch_assoc()) {
            $bus_trips_today[] = $bt_row;
        }
        $bt_stmt->close();
    }
}

$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "summary" => [
        "total_trips" => count($trips),
        "total_fare" => round($total_fare, 2),
        "total_cash_amount" => round($total_cash_amount, 2),
        "total_cash_collected" => round($total_cash_collected, 2),
        "total_gcash_amount" => round($total_gcash_amount, 2),
        "total_expected_cash" => round($total_expected_cash, 2),
        "total_actual_cash" => round($total_actual_cash, 2),
        "total_discrepancy" => round($total_discrepancy, 2),
        "by_driver" => $by_driver,
        "by_bus" => $by_bus
    ],
    "trips" => $trips,
    "bus_trips" => $bus_trips_today,
    "filters" => [
        "driver_id" => $driver_id,
        "bus_no" => $bus_no,
        "trip_no" => $trip_no,
        "date_from" => $date_from,
        "date_to" => $date_to,
        "remittance_status" => $remittance_status
    ]
]);

<?php

header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');

require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$driver_id = $_GET['driver_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if (!$driver_id) {
    echo json_encode(["success" => false, "message" => "Driver ID required"]);
    exit;
}

$has_reviewed_by = false;
$colCheck = $conn->query("
    SELECT COUNT(*) AS c FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'passenger_trips'
      AND COLUMN_NAME = 'reviewed_by'
");
if ($colCheck) {
    $colRow = $colCheck->fetch_assoc();
    $has_reviewed_by = (int)$colRow['c'] > 0;
}

$batchDateExpr = "COALESCE(pt.remitted_at, pt.boarding_time)";

$where = ["pt.driver_id = ?"];
$params = [$driver_id];
$types = 's';

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
    $where[] = "$batchDateExpr >= ?";
    $params[] = "$date_from 00:00:00";
    $types .= 's';
    $where[] = "$batchDateExpr <= ?";
    $params[] = "$date_to 23:59:59";
    $types .= 's';
} elseif (!empty($date_from)) {
    $where[] = "$batchDateExpr >= ?";
    $params[] = "$date_from 00:00:00";
    $types .= 's';
} elseif (!empty($date_to)) {
    $where[] = "$batchDateExpr <= ?";
    $params[] = "$date_to 23:59:59";
    $types .= 's';
}

$whereClause = "WHERE " . implode(" AND ", $where);

$reviewedExpr = $has_reviewed_by ? "MAX(pt.reviewed_by)" : "'' AS reviewed_by";

$sql = "
    SELECT
        COALESCE(pt.remitted_by, '') AS remitted_by,
        MAX(COALESCE(pt.remitted_at, pt.boarding_time)) AS batch_time,
        MAX(pt.remittance_status) AS remittance_status,
        $reviewedExpr,
        MAX(pt.finance_note) AS finance_note,
        COUNT(*) AS trip_count,
        SUM(CASE WHEN LOWER(pt.payment_status) = 'paid' THEN 1 ELSE 0 END) AS paid_count,
        SUM(pt.fare) AS total_fare,
        SUM(pt.cash_amount) AS total_cash,
        SUM(pt.gcash_amount) AS total_gcash,
        SUM(CASE
            WHEN LOWER(pt.payment_status) = 'paid' AND pt.payment_method = 'cash' THEN pt.fare
            WHEN LOWER(pt.payment_status) = 'paid' AND pt.payment_method = 'hybrid' THEN pt.cash_amount
            ELSE 0
        END) AS total_cash_collected,
        SUM(pt.expected_cash) AS expected_cash,
        SUM(pt.actual_cash) AS actual_cash,
        SUM(pt.expected_cash) - SUM(pt.actual_cash) AS discrepancy_amount,
        GROUP_CONCAT(DISTINCT pt.payment_method) AS payment_methods
    FROM passenger_trips pt
    $whereClause
    GROUP BY pt.remittance_status, COALESCE(pt.remitted_at, DATE(pt.boarding_time))
    ORDER BY MAX(COALESCE(pt.remitted_at, pt.boarding_time)) DESC
    LIMIT 500
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Query preparation failed: " . $conn->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$batches = [];
while ($row = $result->fetch_assoc()) {
    $row['trip_count'] = (int)$row['trip_count'];
    $row['paid_count'] = (int)$row['paid_count'];
    $row['total_fare'] = round((float)$row['total_fare'], 2);
    $row['total_cash'] = round((float)$row['total_cash'], 2);
    $row['total_gcash'] = round((float)$row['total_gcash'], 2);
    $row['total_cash_collected'] = round((float)$row['total_cash_collected'], 2);
    $row['expected_cash'] = round((float)$row['expected_cash'], 2);
    $row['actual_cash'] = round((float)$row['actual_cash'], 2);
    $row['discrepancy_amount'] = round((float)$row['discrepancy_amount'], 2);
    $batches[] = $row;
}

echo json_encode([
    "success" => true,
    "count" => count($batches),
    "batches" => $batches
]);

$stmt->close();
$conn->close();

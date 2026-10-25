<?php

header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$bus_id = $_GET['bus_id'] ?? '';
$date = $_GET['date'] ?? '';

if (!$bus_id) {
    echo json_encode(["success" => false, "message" => "Bus ID required"]);
    exit;
}

$sql = "
    SELECT
        refuel_id AS receipt_id,
        CONCAT(
            'FUEL-',
            DATE_FORMAT(COALESCE(created_at, NOW()), '%Y%m%d'),
            '-',
            LPAD(refuel_id, 4, '0')
        ) AS receipt_number,
        bus_id, driver_id, liters, total_cost AS cost, price_per_liter, created_at, fuel_proof_url
    FROM fuel_refuels
    WHERE bus_id = ?
";
$params = [$bus_id];
$types = "s";

if ($date) {
    $sql .= " AND DATE(created_at) = ?";
    $params[] = $date;
    $types .= "s";
}

$sql .= " ORDER BY created_at DESC, refuel_id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$result = $stmt->get_result();

$receipts = [];
while ($row = $result->fetch_assoc()) {
    $row['liters'] = (float)$row['liters'];
    $row['cost'] = (float)$row['cost'];
    $row['price_per_liter'] = (float)$row['price_per_liter'];
    if (!isset($row['fuel_proof_url'])) {
        $row['fuel_proof_url'] = null;
    }
    $receipts[] = $row;
}

echo json_encode([
    "success" => true,
    "count" => count($receipts),
    "receipts" => $receipts
]);

$stmt->close();
$conn->close();

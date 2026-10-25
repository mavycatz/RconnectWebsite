<?php

header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$bus_id = $_GET['bus_id'] ?? '';

if (!$bus_id) {
    echo json_encode(["success" => false, "message" => "Bus ID required"]);
    exit;
}

$today = date("Y-m-d");

$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(liters), 0) AS total_liters,
        COALESCE(SUM(total_cost), 0) AS total_cost,
        COUNT(*) AS log_count
    FROM fuel_refuels
    WHERE bus_id = ?
    AND DATE(created_at) = ?
");

$stmt->bind_param("ss", $bus_id, $today);
$stmt->execute();

$result = $stmt->get_result()->fetch_assoc();

echo json_encode([
    "success" => true,
    "total_liters" => (float)$result['total_liters'],
    "total_cost" => (float)$result['total_cost'],
    "log_count" => (int)$result['log_count'],
    "date" => $today
]);

$stmt->close();
$conn->close();

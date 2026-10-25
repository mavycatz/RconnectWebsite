<?php

header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$bus_id = trim($_GET['bus_id'] ?? '');

if (!$bus_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Bus ID required"]);
    exit;
}

$today = date("Y-m-d");

$stmt = dispatcher_statement($conn, "
    SELECT
        COALESCE(SUM(liters), 0) AS total_liters,
        COALESCE(SUM(cost), 0) AS total_cost,
        COUNT(*) AS log_count
    FROM fuel_logs
    WHERE bus_id = ?
    AND DATE(created_at) = ?
", "ss", [$bus_id, $today]);

if (!$stmt) dispatcher_json_error(500, "Failed to load fuel summary");

$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    "success" => true,
    "total_liters" => (float)$result['total_liters'],
    "total_cost" => (float)$result['total_cost'],
    "log_count" => (int)$result['log_count'],
    "date" => $today
]);

$conn->close();

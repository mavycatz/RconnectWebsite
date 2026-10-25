<?php

header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$driver_id = trim($_GET['driver_id'] ?? '');

if (!$driver_id) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Driver ID required"]);
    exit;
}

$stmt = dispatcher_statement($conn, "
    SELECT
        bt.bus_id,
        b.plate_number,
        bt.route_name
    FROM bus_trips bt
    LEFT JOIN buses b ON b.bus_id = bt.bus_id
    WHERE bt.driver_id = ?
    AND bt.status IN ('Departed', 'On Route', 'Scheduled', 'Interrupted')
    ORDER BY bt.id DESC
    LIMIT 1
", "s", [$driver_id]);

if (!$stmt) dispatcher_json_error(500, "Failed to load trip");

$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result) {
    echo json_encode([
        "success" => true,
        "bus_id" => $result['bus_id'],
        "plate_number" => $result['plate_number'] ?? '',
        "route_name" => $result['route_name']
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "No active trip found"
    ]);
}

$conn->close();

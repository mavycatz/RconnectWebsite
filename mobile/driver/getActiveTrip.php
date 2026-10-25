<?php

header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$driver_id = $_GET['driver_id'] ?? '';

if (!$driver_id) {
    echo json_encode(["success" => false, "message" => "Driver ID required"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        bt.id AS trip_id,
        bt.bus_id,
        b.plate_number,
        bt.route_name,
        bt.arrival_reported_at,
        bt.arrival_report_source
    FROM bus_trips bt
    LEFT JOIN buses b ON b.bus_id = bt.bus_id
    WHERE bt.driver_id = ?
    AND bt.status IN ('Departed', 'On Route', 'Scheduled')
    ORDER BY bt.id DESC
    LIMIT 1
");

/* Schema-tolerant fallback for installs without the arrival report columns. */
if (!$stmt) {
    $stmt = $conn->prepare("
        SELECT
            bt.id AS trip_id,
            bt.bus_id,
            b.plate_number,
            bt.route_name
        FROM bus_trips bt
        LEFT JOIN buses b ON b.bus_id = bt.bus_id
        WHERE bt.driver_id = ?
        AND bt.status IN ('Departed', 'On Route', 'Scheduled')
        ORDER BY bt.id DESC
        LIMIT 1
    ");
}

$stmt->bind_param("s", $driver_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result) {
    $arrivalReported = !empty($result['arrival_reported_at']);
    echo json_encode([
        "success" => true,
        "trip_id" => $result['trip_id'],
        "bus_id" => $result['bus_id'],
        "plate_number" => $result['plate_number'] ?? '',
        "route_name" => $result['route_name'],
        "arrival_reported" => $arrivalReported,
        "awaiting_confirmation" => $arrivalReported
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "No active trip found"
    ]);
}

$stmt->close();
$conn->close();

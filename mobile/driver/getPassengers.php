<?php

header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$bus_no = $_GET['bus_no'] ?? $_GET['bus_id'] ?? '';
$driver_id = $_GET['driver_id'] ?? '';

if (empty($bus_no) || empty($driver_id)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing bus_no or driver_id"
    ]);
    exit;
}

// Get active trip from bus_trips
$tripStmt = $conn->prepare("
    SELECT id FROM bus_trips
    WHERE driver_id = ? AND status IN ('Departed', 'Started', 'Active', 'Ongoing')
    ORDER BY id DESC
    LIMIT 1
");

if (!$tripStmt) {
    echo json_encode([
        "success" => false,
        "message" => "bus_trips query failed: " . $conn->error
    ]);
    exit;
}

$tripStmt->bind_param("s", $driver_id);
$tripStmt->execute();
$tripResult = $tripStmt->get_result();
$activeTrip = $tripResult->fetch_assoc();
$tripStmt->close();

if (!$activeTrip) {
    echo json_encode([
        "success" => true,
        "count" => 0,
        "passengers" => [],
        "trip_active" => false,
        "message" => "No active trip found"
    ]);
    exit;
}

$activeTripId = $activeTrip['id'];

// Get passengers for the active trip only
$stmt = $conn->prepare("
    SELECT
        pt.id,
        pt.passenger_id,
        pt.bus_no,
        pt.trip_no,
        pt.boarding_point,
        pt.destination,
        pt.fare,
        pt.payment_method,
        pt.payment_status,
        pt.boarding_time,
        pt.latitude,
        pt.longitude,
        p.full_name
    FROM passenger_trips pt
    LEFT JOIN passengers p ON pt.passenger_id = p.id
    WHERE
        pt.trip_no = ?
        AND pt.bus_no = ?
        AND pt.driver_id = ?
        AND pt.dropoff_time IS NULL
        AND pt.archived_status = 'no'
    ORDER BY pt.boarding_time DESC
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => $conn->error
    ]);
    exit;
}

$stmt->bind_param("iss", $activeTripId, $bus_no, $driver_id);
$stmt->execute();

$result = $stmt->get_result();

$passengers = [];

while ($row = $result->fetch_assoc()) {
    $passengers[] = $row;
}

echo json_encode([
    "success" => true,
    "count" => count($passengers),
    "passengers" => $passengers,
    "trip_active" => true,
    "trip_id" => $activeTripId
]);

$stmt->close();
$conn->close();

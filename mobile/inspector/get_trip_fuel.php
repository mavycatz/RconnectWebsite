<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$trip_id = (int)($_GET['trip_id'] ?? 0);
$bus_id = trim($_GET['bus_id'] ?? '');

if (!$trip_id && !$bus_id) {
    echo json_encode(["success" => false, "message" => "trip_id or bus_id required"]);
    exit;
}

$fuelLog = null;
if ($trip_id > 0) {
    $stmt = $conn->prepare("SELECT fl.*, b.plate_number, d.full_name AS driver_name
                            FROM fuel_logs fl
                            LEFT JOIN buses b ON b.bus_id = fl.bus_id
                            LEFT JOIN drivers d ON d.driver_id = fl.driver_id
                            WHERE fl.trip_id = ? ORDER BY fl.id DESC LIMIT 1");
    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    $fuelLog = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif ($bus_id !== '') {
    $stmt = $conn->prepare("SELECT fl.*, b.plate_number, d.full_name AS driver_name
                            FROM fuel_logs fl
                            LEFT JOIN buses b ON b.bus_id = fl.bus_id
                            LEFT JOIN drivers d ON d.driver_id = fl.driver_id
                            WHERE fl.bus_id = ? ORDER BY fl.id DESC LIMIT 1");
    $stmt->bind_param("s", $bus_id);
    $stmt->execute();
    $fuelLog = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$conn->close();

if (!$fuelLog) {
    echo json_encode(["success" => false, "message" => "No fuel record found"]);
    exit;
}

echo json_encode([
    "success" => true,
    "data" => $fuelLog
]);

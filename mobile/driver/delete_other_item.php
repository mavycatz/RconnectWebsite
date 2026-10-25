<?php

header("Content-Type: application/json");

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$passenger_id = isset($data['passenger_id']) ? (int)$data['passenger_id'] : 0;
$bus_no      = trim($data['bus_no'] ?? '');
$trip_no     = trim($data['trip_no'] ?? '');
$driver_id   = trim($data['driver_id'] ?? '');
$token       = $data['token'] ?? '';

require_once __DIR__ . '/lib/driver_auth.php';
require_driver_auth($conn, $driver_id, $token);

if (empty($passenger_id) || empty($bus_no) || empty($trip_no) || empty($driver_id)) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Ensure it is an 'other' record before removing
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("SELECT p.id FROM passengers p WHERE p.id = ? AND p.type = 'other' LIMIT 1");
$stmt->bind_param("i", $passenger_id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rec) {
    echo json_encode(["success" => false, "message" => "Other item not found"]);
    exit;
}

$conn->begin_transaction();
try {
    // Soft-delete the trip record (frees capacity immediately via archived_status filter)
    $stmt = $conn->prepare("UPDATE passenger_trips SET archived_status = 'yes' WHERE passenger_id = ? AND trip_no = ? AND bus_no = ? AND driver_id = ?");
    if (!$stmt) throw new Exception("Trip update prepare failed: " . $conn->error);
    $stmt->bind_param("iiss", $passenger_id, $trip_no, $bus_no, $driver_id);
    if (!$stmt->execute()) throw new Exception("Trip update failed: " . $stmt->error);
    $stmt->close();

    // Remove the orphaned passenger row
    $stmt = $conn->prepare("DELETE FROM passengers WHERE id = ? AND type = 'other'");
    if (!$stmt) throw new Exception("Passenger delete prepare failed: " . $conn->error);
    $stmt->bind_param("i", $passenger_id);
    if (!$stmt->execute()) throw new Exception("Passenger delete failed: " . $stmt->error);
    $stmt->close();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Other item removed successfully",
        "passenger_id" => $passenger_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

$conn->close();

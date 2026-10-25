<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();
    if (!$conn) throw new Exception("Database connection failed");

    $input = json_decode(file_get_contents("php://input"), true);

    $driverId     = trim($input['driver_id'] ?? '');
    $token        = trim($input['token'] ?? '');
    $passengerCode = trim($input['passenger_code'] ?? '');
    $tripNo       = trim($input['trip_no'] ?? '');
    $unverified   = (int)($input['unverified'] ?? 0) === 1 ? 1 : 0;

    require_once __DIR__ . '/lib/driver_auth.php';
    require_driver_auth($conn, $driverId, $token);

    // Resolve the driver's ACTIVE trip from the authenticated driver. The
    // client trip_no is only validated against it; the backend is the source
    // of truth so a stale trip_no never causes a false "no active trip".
    require_once __DIR__ . '/lib/active_trip.php';
    $activeTrip = resolve_active_trip($conn, $driverId);
    if (!$activeTrip) {
        echo json_encode([
            "success" => false,
            "code" => "NO_ACTIVE_TRIP",
            "message" => "No active trip found",
        ]);
        exit;
    }
    $submittedTripNo = trim($input['trip_no'] ?? '');
    if ($submittedTripNo !== '' && !trip_matches_identifier($submittedTripNo, $activeTrip)) {
        error_log("[get_passenger_trip_status] Ignoring stale trip_no '$submittedTripNo' for driver $driverId; using active trip {$activeTrip['id']}");
    }
    $tripNo = (string)$activeTrip['id'];

    if (!$unverified && empty($passengerCode)) {
        echo json_encode([
            "success" => false,
            "message" => "passenger_code and trip_no are required",
        ]);
        exit;
    }

    if ($unverified) {
        // Resolve unverified trip state FIFO: earliest ACTIVE unverified
        // boarding wins first; only when none remain do we report completed.
        $stmt = $conn->prepare(
            "SELECT passenger_code, boarding_point, destination, fare, boarding_time, dropoff_time, archived_status
             FROM passenger_trips
             WHERE trip_no = ? AND driver_id = ? AND unverified = 1
             ORDER BY (dropoff_time IS NULL OR dropoff_time = '') DESC, id ASC
             LIMIT 1"
        );
        if (!$stmt) throw new Exception("passenger_trips query failed: " . $conn->error);
        $stmt->bind_param("ss", $tripNo, $driverId);
    } else {
        // Match the passenger_code or, when that is absent (manually added /
        // one-time passengers have passenger_code = NULL), the numeric
        // passenger_id the app falls back to. Mirrors the early_dropoff lookup.
        $stmt = $conn->prepare(
            "SELECT passenger_code, boarding_point, destination, fare, boarding_time, dropoff_time, archived_status
             FROM passenger_trips
             WHERE (passenger_code = ? OR passenger_id = ?) AND trip_no = ? AND driver_id = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        if (!$stmt) throw new Exception("passenger_trips query failed: " . $conn->error);
        $stmt->bind_param("siss", $passengerCode, $passengerCode, $tripNo, $driverId);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode([
            "success" => true,
            "state" => "none",
            "boarding" => null,
        ]);
        exit;
    }

    $droppedOff = ($row['dropoff_time'] !== null && $row['dropoff_time'] !== '') || $row['archived_status'] === 'yes';

    echo json_encode([
        "success" => true,
        "state" => $droppedOff ? "completed" : "boarding_active",
        "boarding" => $row,
    ]);

    $conn->close();

} catch (Exception $e) {
    error_log("[get_passenger_trip_status] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "code" => "SERVER_ERROR", "message" => "Something went wrong. Please try again."]);
}
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

    $input = json_decode(file_get_contents("php://input"), true) ?? [];

    $passengerId          = (int)($input['passenger_id'] ?? 0);
    $passengerCode        = trim($input['passenger_code'] ?? '');
    $busNo                = trim($input['bus_no'] ?? '');
    $tripNo               = trim($input['trip_no'] ?? '');
    $inspectorId          = trim($input['inspector_id'] ?? '');
    $driverId             = trim($input['driver_id'] ?? '');
    $boardingPoint        = trim($input['boarding_point'] ?? $input['boarding'] ?? '');
    $destination          = trim($input['destination'] ?? '');
    $fare                 = floatval($input['fare'] ?? 0);
    $identificationSource = trim($input['identification_source'] ?? 'inspector');
    $photoUri             = trim($input['passenger_photo'] ?? $input['photo_url'] ?? '');

    if (empty($busNo) || empty($tripNo)) {
        echo json_encode(["success" => false, "message" => "bus_no and trip_no are required"]);
        exit;
    }

    if (empty($boardingPoint) || empty($destination)) {
        echo json_encode(["success" => false, "message" => "boarding_point and destination are required"]);
        exit;
    }

    if ($passengerId <= 0 && empty($passengerCode)) {
        echo json_encode(["success" => false, "message" => "passenger_id or passenger_code is required"]);
        exit;
    }

    // Resolve passenger_id if missing
    if ($passengerId <= 0 && !empty($passengerCode)) {
        $pStmt = $conn->prepare("SELECT id FROM passengers WHERE passenger_code = ? LIMIT 1");
        if ($pStmt) {
            $pStmt->bind_param("s", $passengerCode);
            $pStmt->execute();
            $pRow = $pStmt->get_result()->fetch_assoc();
            $pStmt->close();
            if ($pRow) {
                $passengerId = (int)$pRow['id'];
            }
        }
    }

    // Check duplicate active boarding on this trip
    $dupStmt = $conn->prepare("
        SELECT id FROM passenger_trips
        WHERE (passenger_id = ? OR (passenger_code IS NOT NULL AND passenger_code = ?))
          AND trip_no = ?
          AND (dropoff_time IS NULL OR dropoff_time = '')
          AND archived_status = 'no'
        LIMIT 1
    ");
    if ($dupStmt) {
        $dupStmt->bind_param("iss", $passengerId, $passengerCode, $tripNo);
        $dupStmt->execute();
        $dupRow = $dupStmt->get_result()->fetch_assoc();
        $dupStmt->close();
        if ($dupRow) {
            echo json_encode([
                "success" => false,
                "code"    => "ALREADY_BOARDED",
                "message" => "Passenger is already boarded on this trip"
            ]);
            exit;
        }
    }

    // Insert into existing passenger_trips table
    $tripRecordId = null;
    $insStmt = $conn->prepare("
        INSERT INTO passenger_trips
        (
            passenger_id,
            passenger_code,
            inspector_id,
            bus_no,
            trip_no,
            driver_id,
            boarding_point,
            destination,
            fare,
            payment_method,
            payment_status,
            boarding_time,
            passenger_photo,
            identification_source,
            updated_by
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            'Cash',
            'Pending',
            NOW(),
            ?,
            ?,
            ?
        )
    ");

    if ($insStmt) {
        $insStmt->bind_param(
            "isssssssdsss",
            $passengerId,
            $passengerCode,
            $inspectorId,
            $busNo,
            $tripNo,
            $driverId,
            $boardingPoint,
            $destination,
            $fare,
            $photoUri,
            $identificationSource,
            $inspectorId
        );
        if ($insStmt->execute()) {
            $tripRecordId = $conn->insert_id;
        }
        $insStmt->close();
    }

    if (!$tripRecordId) {
        // Fallback insert with standard columns
        $fbStmt = $conn->prepare("
            INSERT INTO passenger_trips
            (passenger_id, inspector_id, bus_no, trip_no, driver_id, boarding_point, destination, fare, payment_method, payment_status, boarding_time, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Cash', 'Pending', NOW(), ?)
        ");
        if ($fbStmt) {
            $fbStmt->bind_param("issssssds", $passengerId, $inspectorId, $busNo, $tripNo, $driverId, $boardingPoint, $destination, $fare, $inspectorId);
            if ($fbStmt->execute()) {
                $tripRecordId = $conn->insert_id;
            }
            $fbStmt->close();
        }
    }

    echo json_encode([
        "success"        => true,
        "trip_id"        => $tripRecordId,
        "passenger_id"   => $passengerId,
        "passenger_code" => $passengerCode,
        "boarding_point" => $boardingPoint,
        "destination"    => $destination,
        "fare"           => $fare,
        "message"        => "Passenger boarded successfully"
    ]);

    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "code"    => "SERVER_ERROR",
        "message" => $e->getMessage()
    ]);
}

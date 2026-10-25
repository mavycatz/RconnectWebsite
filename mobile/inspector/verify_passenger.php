<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
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
    $rawCode = trim($input['passenger_code'] ?? $_GET['passenger_code'] ?? '');
    $trip_no = trim($input['trip_no'] ?? $_GET['trip_no'] ?? '');
    $bus_no  = trim($input['bus_no'] ?? $_GET['bus_no'] ?? '');

    if (empty($rawCode)) {
        echo json_encode(["success" => false, "registered" => false, "message" => "passenger_code is required"]);
        exit;
    }

    // Check if rawCode is a JSON QR payload
    $parsedCode = $rawCode;
    $parsedName = '';
    $parsedType = '';
    if (str_starts_with($rawCode, '{') && str_ends_with($rawCode, '}')) {
        $qrObj = json_decode($rawCode, true);
        if ($qrObj && !empty($qrObj['passenger_code'])) {
            $parsedCode = trim($qrObj['passenger_code']);
            $parsedName = trim($qrObj['full_name'] ?? '');
            $parsedType = trim($qrObj['passenger_type'] ?? '');
        }
    }

    // 1) Find passenger in `passengers` table
    $stmt = $conn->prepare("
        SELECT id, passenger_code, full_name, type
        FROM passengers
        WHERE passenger_code = ? OR full_name = ?
        LIMIT 1
    ");
    if (!$stmt) throw new Exception("Database prepare failed: " . $conn->error);
    $stmt->bind_param("ss", $parsedCode, $parsedName);
    $stmt->execute();
    $pRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 2) If not found in `passengers`, check `passenger_trips`
    if (!$pRow && !empty($parsedCode)) {
        $tCheck = $conn->prepare("
            SELECT passenger_id, passenger_code, passenger_photo
            FROM passenger_trips
            WHERE passenger_code = ?
            ORDER BY id DESC LIMIT 1
        ");
        if ($tCheck) {
            $tCheck->bind_param("s", $parsedCode);
            $tCheck->execute();
            $tFound = $tCheck->get_result()->fetch_assoc();
            $tCheck->close();
            if ($tFound && !empty($tFound['passenger_id'])) {
                $pId = (int)$tFound['passenger_id'];
                $pStmt = $conn->prepare("SELECT id, passenger_code, full_name, type FROM passengers WHERE id = ? LIMIT 1");
                if ($pStmt) {
                    $pStmt->bind_param("i", $pId);
                    $pStmt->execute();
                    $pRow = $pStmt->get_result()->fetch_assoc();
                    $pStmt->close();
                }
            }
        }
    }

    if (!$pRow) {
        echo json_encode([
            "success"        => true,
            "registered"     => false,
            "passenger_code" => $parsedCode,
            "full_name"      => $parsedName ?: 'Unregistered Passenger',
            "passenger_type" => $parsedType ?: 'Regular',
            "message"        => "Passenger is not registered yet"
        ]);
        exit;
    }

    $passengerId = (int)$pRow['id'];
    $passengerCode = $pRow['passenger_code'] ?: $parsedCode;

    // Check active trip status & resolve passenger_photo from passenger_trips
    $activeTrip = null;
    $photo = null;

    $tripStmt = $conn->prepare("
        SELECT id, bus_no, trip_no, boarding_point, destination, fare, payment_status, payment_method, boarding_time, dropoff_time, passenger_photo
        FROM passenger_trips
        WHERE (passenger_id = ? OR (passenger_code IS NOT NULL AND passenger_code = ?))
        ORDER BY id DESC LIMIT 1
    ");
    if ($tripStmt) {
        $tripStmt->bind_param("is", $passengerId, $passengerCode);
        $tripStmt->execute();
        $tRow = $tripStmt->get_result()->fetch_assoc();
        $tripStmt->close();
        if ($tRow) {
            $photo = $tRow['passenger_photo'] ?? null;
            $isDropoff = (!empty($tRow['dropoff_time']));
            if (!empty($trip_no) && (string)$tRow['trip_no'] === (string)$trip_no && !$isDropoff) {
                $activeTrip = [
                    "trip_record_id" => (int)$tRow['id'],
                    "is_boarded"     => true,
                    "bus_no"         => $tRow['bus_no'],
                    "trip_no"        => $tRow['trip_no'],
                    "boarding_point" => $tRow['boarding_point'],
                    "destination"    => $tRow['destination'],
                    "fare"           => floatval($tRow['fare']),
                    "payment_status" => $tRow['payment_status'] ?? 'Pending',
                    "payment_method" => $tRow['payment_method'] ?? 'Cash',
                    "boarding_time"  => $tRow['boarding_time'],
                    "passenger_photo"=> $photo,
                ];
            }
        }
    }

    echo json_encode([
        "success"        => true,
        "registered"     => true,
        "passenger"      => [
            "id"             => $passengerId,
            "passenger_code" => $passengerCode,
            "full_name"      => $pRow['full_name'] ?? 'Passenger',
            "passenger_type" => $pRow['type'] ?? 'Regular',
            "photo_url"      => $photo,
        ],
        "active_trip"    => $activeTrip,
        "is_boarded"     => $activeTrip !== null,
        "message"        => $activeTrip ? "Passenger is already boarded on this trip" : "Passenger verified and ready to board"
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

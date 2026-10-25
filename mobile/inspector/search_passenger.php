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
    $query   = trim($_GET['query'] ?? $input['query'] ?? '');
    $mode    = strtolower(trim($_GET['mode'] ?? $input['mode'] ?? 'code'));
    $trip_no = trim($_GET['trip_no'] ?? $input['trip_no'] ?? '');
    $bus_no  = trim($_GET['bus_no'] ?? $input['bus_no'] ?? '');

    if ($mode !== 'code' && $mode !== 'name') {
        $mode = 'code';
    }

    if ($query === '') {
        echo json_encode(["success" => true, "passengers" => [], "message" => "query is required"]);
        exit;
    }

    $passengers = [];

    if ($mode === 'code') {
        if (strlen($query) < 2) {
            echo json_encode(["success" => true, "passengers" => [], "message" => "Enter at least 2 characters"]);
            exit;
        }

        $rows = [];
        $stmt = $conn->prepare(
            "SELECT id, passenger_code, full_name, type
             FROM passengers
             WHERE passenger_code IS NOT NULL AND LOWER(passenger_code) = LOWER(?)
             LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param("s", $query);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $rows[] = $row;
            }
        }

        if (empty($rows)) {
            $like = strtolower($query) . '%';
            $stmt = $conn->prepare(
                "SELECT id, passenger_code, full_name, type
                 FROM passengers
                 WHERE passenger_code IS NOT NULL AND LOWER(passenger_code) LIKE ?
                 ORDER BY passenger_code ASC
                 LIMIT 15"
            );
            if ($stmt) {
                $stmt->bind_param("s", $like);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $rows[] = $r;
                }
                $stmt->close();
            }
        }
    } else {
        if (strlen($query) < 2) {
            echo json_encode(["success" => true, "passengers" => [], "message" => "Enter at least 2 characters"]);
            exit;
        }

        $rows = [];
        $like = '%' . strtolower($query) . '%';
        $stmt = $conn->prepare(
            "SELECT id, passenger_code, full_name, type
             FROM passengers
             WHERE full_name IS NOT NULL AND LOWER(full_name) LIKE ?
             ORDER BY full_name ASC
             LIMIT 20"
        );
        if ($stmt) {
            $stmt->bind_param("s", $like);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
            $stmt->close();
        }
    }

    foreach ($rows as $row) {
        $pId = (int)$row['id'];
        $pCode = $row['passenger_code'] ?? '';

        // Check active boarding on trip_no and resolve latest passenger_photo from passenger_trips
        $activeTrip = null;
        $photo = null;

        $tripStmt = $conn->prepare(
            "SELECT id, bus_no, trip_no, boarding_point, destination, fare, payment_status, payment_method, boarding_time, dropoff_time, passenger_photo
             FROM passenger_trips
             WHERE (passenger_id = ? OR (passenger_code IS NOT NULL AND passenger_code = ?))
             ORDER BY id DESC LIMIT 1"
        );
        if ($tripStmt) {
            $tripStmt->bind_param("is", $pId, $pCode);
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

        $passengers[] = [
            "id"             => $pId,
            "passenger_code" => $pCode,
            "full_name"      => $row['full_name'] ?? 'Unknown',
            "passenger_type" => normalizeType($row['type'] ?? 'Regular'),
            "photo_url"      => $photo,
            "active_trip"    => $activeTrip,
        ];
    }

    echo json_encode([
        "success"    => true,
        "mode"       => $mode,
        "count"      => count($passengers),
        "passengers" => $passengers,
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

function normalizeType($type)
{
    $key = strtolower(trim((string)$type));
    switch ($key) {
        case 'pwd':     return 'PWD';
        case 'student': return 'Student';
        case 'senior':  return 'Senior';
        case 'special': return 'Special';
        case 'teacher': return 'Teacher';
        case 'nurse':   return 'Nurse';
        case 'regular': return 'Regular';
        default:        return 'Regular';
    }
}

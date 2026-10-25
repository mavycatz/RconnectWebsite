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

if (!defined('VEHICLE_CAPACITY')) {
    define('VEHICLE_CAPACITY', 10);
}

$data = json_decode(file_get_contents("php://input"), true);

$passenger_id = isset($data['passenger_id']) ? (int)$data['passenger_id'] : 0;
$bus_no      = trim($data['bus_no'] ?? '');
$trip_no     = trim($data['trip_no'] ?? '');
$driver_id   = trim($data['driver_id'] ?? '');
$token       = $data['token'] ?? '';

require_once __DIR__ . '/lib/driver_auth.php';
require_driver_auth($conn, $driver_id, $token);

require_once __DIR__ . '/lib/passenger_photo.php';

$details     = trim($data['details'] ?? '');
$space_occupied = isset($data['space_occupied']) ? (int)$data['space_occupied'] : 0;
$boarding_point  = trim($data['boarding_point'] ?? '');
$input_destination = trim($data['destination'] ?? '');

$passengerPhotoDataUri = trim($data['passenger_photo'] ?? '');

if (empty($passenger_id) || empty($bus_no) || empty($trip_no) || empty($driver_id)) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
}
if ($details === '') {
    echo json_encode(["success" => false, "message" => "Item details are required"]);
    exit;
}
if ($space_occupied < 1) {
    echo json_encode(["success" => false, "message" => "Space occupied must be at least 1"]);
    exit;
}
if ($boarding_point === '') {
    echo json_encode(["success" => false, "message" => "Boarding point is required"]);
    exit;
}
if ($input_destination === '') {
    echo json_encode(["success" => false, "message" => "Destination is required"]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Load the existing Other record (type must be 'other')
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT p.id, p.space_occupied, p.type
    FROM passengers p
    WHERE p.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $passenger_id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rec || ($rec['type'] ?? '') !== 'other') {
    echo json_encode(["success" => false, "message" => "Other item not found"]);
    exit;
}

$oldSpace = (int)($rec['space_occupied'] ?? 1);

/*
|--------------------------------------------------------------------------
| Resolve route_name for this trip
|--------------------------------------------------------------------------
*/
$trip_id_in   = $data['trip_id'] ?? null;
$client_route = trim($data['route_name'] ?? '');

$trip = null;
$candidateTripId = !empty($trip_id_in) ? (int)$trip_id_in : (is_numeric($trip_no) ? (int)$trip_no : 0);

// Check 1: Match by (id or trip_number) AND driver_id
if ($candidateTripId > 0) {
    $stmt = $conn->prepare("
        SELECT id, route_name, trip_number, bus_id, driver_id, status
        FROM bus_trips
        WHERE (id = ? OR trip_number = ?) AND driver_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("iis", $candidateTripId, $candidateTripId, $driver_id);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Check 2: Match active trip for driver_id
if (!$trip) {
    $stmt = $conn->prepare("
        SELECT id, route_name, trip_number, bus_id, driver_id, status
        FROM bus_trips
        WHERE driver_id = ? AND status IN ('Departed', 'Started', 'Active', 'Ongoing', 'On Route', 'Scheduled')
        ORDER BY id DESC LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("s", $driver_id);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Check 3: Match active trip for bus_no
if (!$trip && !empty($bus_no)) {
    $stmt = $conn->prepare("
        SELECT id, route_name, trip_number, bus_id, driver_id, status
        FROM bus_trips
        WHERE bus_id = ? AND status IN ('Departed', 'Started', 'Active', 'Ongoing', 'On Route', 'Scheduled')
        ORDER BY id DESC LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("s", $bus_no);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Check 4: Match by candidate id/trip_number alone
if (!$trip && $candidateTripId > 0) {
    $stmt = $conn->prepare("
        SELECT id, route_name, trip_number, bus_id, driver_id, status
        FROM bus_trips
        WHERE id = ? OR trip_number = ?
        ORDER BY id DESC LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $candidateTripId, $candidateTripId);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Check 5: Latest non-completed trip for this driver
if (!$trip) {
    $stmt = $conn->prepare("
        SELECT id, route_name, trip_number, bus_id, driver_id, status
        FROM bus_trips
        WHERE driver_id = ? AND status NOT IN ('Completed', 'Cancelled')
        ORDER BY id DESC LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("s", $driver_id);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (!$trip) {
    if (!empty($client_route)) {
        $route_name = $client_route;
    } else {
        echo json_encode(["success" => false, "message" => "No route_name for this trip", "debug" => ["bus_no" => $bus_no, "trip_no" => $trip_no]]);
        exit;
    }
} else {
    $route_name = $trip['route_name'];
    $trip_no = (string)$trip['id'];
}

/*
|--------------------------------------------------------------------------
| Load route stops from fare_pricing
|--------------------------------------------------------------------------
*/
$checkStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM fare_pricing WHERE route_name = ?");
$checkStmt->bind_param("s", $route_name);
$checkStmt->execute();
$checkResult = $checkStmt->get_result()->fetch_assoc();
$routeStopCount = (int)$checkResult['cnt'];
$checkStmt->close();

if ($routeStopCount === 0) {
    echo json_encode(["success" => false, "message" => "No fare_pricing records for route", "debug" => ["route_name" => $route_name]]);
    exit;
}

$stmt = $conn->prepare("SELECT id, destination, display_order, regular_fare, student_fare, special_fare FROM fare_pricing WHERE route_name = ? ORDER BY display_order ASC");
if (!$stmt) { echo json_encode(["success" => false, "message" => "fare_pricing query failed", "debug" => $conn->error]); exit; }
$stmt->bind_param("s", $route_name);
$stmt->execute();
$routeStops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$routeStops) {
    echo json_encode(["success" => false, "message" => "No fare_pricing records for route: $route_name"]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Resolve boarding stop + destination by name (exact match expected)
|--------------------------------------------------------------------------
*/
$boardStop = null;
$destStop  = null;
foreach ($routeStops as $s) {
    if (strtolower($s['destination']) === strtolower($boarding_point))    $boardStop = $s;
    if (strtolower($s['destination']) === strtolower($input_destination)) $destStop  = $s;
}
if (!$boardStop || !$destStop) {
    echo json_encode([
        "success" => false,
        "message" => "Boarding point or destination is not a valid stop on this route",
        "debug"   => ["boarding" => $boarding_point, "destination" => $input_destination]
    ]);
    exit;
}

$boardingOrder    = (int)$boardStop['display_order'];
$destinationOrder = (int)$destStop['display_order'];

if ($destinationOrder <= $boardingOrder) {
    echo json_encode([
        "success" => false,
        "message" => "Destination must be ahead of boarding point on the route",
        "debug"   => [
            "boarding"        => $boardStop['destination'],
            "boarding_order"  => $boardingOrder,
            "destination"     => $destStop['destination'],
            "destination_order" => $destinationOrder
        ]
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Compute regular fare (boarding -> destination difference)
|--------------------------------------------------------------------------
*/
$boardingFare    = (float)$boardStop['regular_fare'];
$destinationFare = (float)$destStop['regular_fare'];

if ($boardStop['id'] == $destStop['id']) {
    $fare = 15;
} elseif ($boardingOrder === 1) {
    $fare = $destinationFare;
} else {
    $computedFare = $destinationFare - $boardingFare;
    $fare = $computedFare < 15 ? 15 : $computedFare;
}
$fare = round($fare, 2);

/* Other fare = regular boarding->destination fare x space occupied */
$fare = round($fare * $space_occupied, 2);

$destination    = $destStop['destination'];
$destination_id = (int)$destStop['id'];

/*
|--------------------------------------------------------------------------
| Capacity check (only if increasing)
|--------------------------------------------------------------------------
*/
function getOccupied($conn, $tripNo, $busNo, $driverId) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(CASE WHEN p.space_occupied IS NOT NULL THEN p.space_occupied ELSE 1 END), 0) AS occ
        FROM passenger_trips pt
        LEFT JOIN passengers p ON pt.passenger_id = p.id
        WHERE pt.trip_no = ?
          AND pt.bus_no = ?
          AND pt.driver_id = ?
          AND (pt.dropoff_time IS NULL OR LOWER(pt.payment_status) = 'pending')
          AND pt.archived_status = 'no'
    ");
    $stmt->bind_param("iss", $tripNo, $busNo, $driverId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['occ'] ?? 0);
}

$capacity = VEHICLE_CAPACITY;
$capStmt = $conn->prepare("SELECT COALESCE(seats, 0) + COALESCE(standing_capacity, 0) AS cap FROM buses WHERE bus_id = ? OR plate_number = ? LIMIT 1");
if ($capStmt) {
    $capStmt->bind_param("ss", $bus_no, $bus_no);
    $capStmt->execute();
    $capRow = $capStmt->get_result()->fetch_assoc();
    $capStmt->close();
    if ($capRow && (int)$capRow['cap'] > 0) {
        $capacity = (int)$capRow['cap'];
    }
}
if ($space_occupied > $oldSpace) {
    $occupied = getOccupied($conn, $trip_no, $bus_no, $driver_id);
    $wouldBe = $occupied - $oldSpace + $space_occupied;
    if ($wouldBe > $capacity) {
        $remaining = max(0, $capacity - ($occupied - $oldSpace));
        $remainingWord = $remaining === 1 ? '1 space is' : $remaining . ' spaces are';
        echo json_encode([
            "success" => false,
            "message" => "Not enough available space. Only $remainingWord remaining."
        ]);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Update passenger row + passenger_trips fare/route
|--------------------------------------------------------------------------
*/
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE passengers SET full_name = ?, space_occupied = ? WHERE id = ? AND type = 'other'");
    if (!$stmt) throw new Exception("Update prepare failed: " . $conn->error);
    $stmt->bind_param("sii", $details, $space_occupied, $passenger_id);
    if (!$stmt->execute()) throw new Exception("Update failed: " . $stmt->error);
    $stmt->close();

    $stmt = $conn->prepare("
        UPDATE passenger_trips
        SET boarding_point = ?, destination = ?, destination_id = ?, fare = ?
        WHERE passenger_id = ?
          AND trip_no = ?
          AND bus_no = ?
          AND driver_id = ?
          AND archived_status = 'no'
        ORDER BY id DESC
        LIMIT 1
    ");
    if (!$stmt) throw new Exception("Trip update prepare failed: " . $conn->error);
    $stmt->bind_param("ssidisss", $boarding_point, $destination, $destination_id, $fare, $passenger_id, $trip_no, $bus_no, $driver_id);
    if (!$stmt->execute()) throw new Exception("Trip update failed: " . $stmt->error);
    $stmt->close();

    $conn->commit();

    // Best-effort verification photo storage (never fails the update).
    $photoUrl = null;
    if ($passengerPhotoDataUri !== '') {
        $findStmt = $conn->prepare(
            "SELECT id FROM passenger_trips
             WHERE passenger_id = ? AND trip_no = ? AND bus_no = ? AND driver_id = ?
               AND archived_status = 'no'
             ORDER BY id DESC LIMIT 1"
        );
        if ($findStmt) {
            $findStmt->bind_param("isss", $passenger_id, $trip_no, $bus_no, $driver_id);
            $findStmt->execute();
            $tripRow = $findStmt->get_result()->fetch_assoc();
            $findStmt->close();
            if ($tripRow) {
                $photoUrl = save_passenger_photo($passengerPhotoDataUri, (string)$tripRow['id']);
                if ($photoUrl !== null) {
                    $phStmt = $conn->prepare("UPDATE passenger_trips SET passenger_photo = ? WHERE id = ?");
                    if ($phStmt) {
                        $tripId = (int)$tripRow['id'];
                        $phStmt->bind_param("si", $photoUrl, $tripId);
                        $phStmt->execute();
                        $phStmt->close();
                    }
                }
            }
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Other item updated successfully",
        "passenger_id" => $passenger_id,
        "passenger_photo" => $photoUrl,
        "type" => "other",
        "space_occupied" => $space_occupied,
        "boarding_point" => $boarding_point,
        "destination" => $destination,
        "fare" => $fare
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

$conn->close();

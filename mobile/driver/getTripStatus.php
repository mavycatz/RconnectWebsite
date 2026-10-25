<?php

header("Content-Type: application/json");
require_once "../config/database.php";
require_once __DIR__ . "/lib/arrival_report.php";

$db = new Database();
$conn = $db->connect();

$driver_id = $_GET['driver_id'] ?? '';

if (!$driver_id) {
    echo json_encode([
        "success" => false,
        "message" => "Driver ID required"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| HAVERSINE DISTANCE FUNCTION
|--------------------------------------------------------------------------
*/

function haversineMeters($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

/*
|--------------------------------------------------------------------------
| GET DRIVER BUS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT bus_id
    FROM driver_bus_assignment
    WHERE driver_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $driver_id);
$stmt->execute();

$bus = $stmt->get_result()->fetch_assoc();

if (!$bus) {
    echo json_encode([
        "success" => true,
        "trip_active" => false,
        "current_station" => null,
        "next_stop" => null,
        "eta" => null
    ]);
    exit;
}

$bus_id = $bus['bus_id'];

/*
|--------------------------------------------------------------------------
| GET ACTIVE TRIP WITH DESTINATION COORDINATES
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        bt.id,
        bt.route_name,
        bt.trip_date,
        bt.departure_time,
        bt.status,
        bt.from_terminal_id,
        bt.to_terminal_id,

        ft.terminal_code AS current_station,
        tt.terminal_code AS next_stop,
        tt.latitude AS dest_lat,
        tt.longitude AS dest_lng

    FROM bus_trips bt

    LEFT JOIN terminals ft
        ON ft.id = bt.from_terminal_id

    LEFT JOIN terminals tt
        ON tt.id = bt.to_terminal_id

    WHERE bt.bus_id = ?
    AND bt.status = 'Departed'

    ORDER BY bt.id DESC
    LIMIT 1
");

$stmt->bind_param("s", $bus_id);
$stmt->execute();

$trip = $stmt->get_result()->fetch_assoc();

if (!$trip) {
    echo json_encode([
        "success" => true,
        "trip_active" => false,
        "current_station" => null,
        "next_stop" => null,
        "eta" => null
    ]);
    exit;
}

/* A reported arrival (driver GPS, driver end-trip, or receiving-terminal
   staff) ends the driver's navigation; the record stays Departed until the
   receiving terminal confirms it with the actual ending odometer. */
$arrivalReport = driver_trip_arrival_report($conn, (int)$trip['id']);
if ($arrivalReport['reported']) {
    echo json_encode([
        "success" => true,
        "trip_active" => false,
        "arrival_reported" => true,
        "awaiting_confirmation" => true,
        "message" => "Arrival reported - awaiting terminal confirmation",
        "current_station" => null,
        "next_stop" => null,
        "eta" => null
    ]);
    exit;
}

$trip_start = $trip['trip_date'] . ' ' . $trip['departure_time'];

/*
|--------------------------------------------------------------------------
| GET DRIVER CURRENT LOCATION + SPEED
|--------------------------------------------------------------------------
*/

$locStmt = $conn->prepare("
    SELECT latitude, longitude, speed
    FROM driver_current_location
    WHERE driver_id = ?
    ORDER BY updated_at DESC
    LIMIT 1
");
$locStmt->bind_param("s", $driver_id);
$locStmt->execute();
$driverLoc = $locStmt->get_result()->fetch_assoc();
$locStmt->close();

/*
|--------------------------------------------------------------------------
| DISTANCE-BASED ETA
|--------------------------------------------------------------------------
*/

$remaining_distance_km = null;
$avg_speed_kmh = null;
$eta = '';
$completed = false;

if ($driverLoc && $trip['dest_lat'] && $trip['dest_lng']) {
    $currentLat = (float)$driverLoc['latitude'];
    $currentLng = (float)$driverLoc['longitude'];
    $destLat = (float)$trip['dest_lat'];
    $destLng = (float)$trip['dest_lng'];

    $distanceM = haversineMeters($currentLat, $currentLng, $destLat, $destLng);
    $remaining_distance_km = round($distanceM / 1000, 2);

    $speed = $driverLoc['speed'] !== null ? (float)$driverLoc['speed'] : 11.1;
    if ($speed <= 0) $speed = 11.1;
    $avg_speed_kmh = round($speed * 3.6, 1);

    if ($distanceM < driver_arrival_report_distance_m()) {
        $completed = true;
    } else {
        $remainingSeconds = $distanceM / $speed;
        $hours = floor($remainingSeconds / 3600);
        $minutes = floor(($remainingSeconds % 3600) / 60);
        if ($minutes < 1) $minutes = 1;

        if ($hours > 0) {
            $eta .= $hours . 'h ';
        }
        $eta .= $minutes . 'min';
    }
}

/*
|--------------------------------------------------------------------------
| REPORT ARRIVAL WHEN NEAR DESTINATION (never completes the trip)
|--------------------------------------------------------------------------
*/

if ($completed && $trip['status'] !== 'Interrupted') {
    driver_report_arrival($conn, (int)$trip['id'], 'driver_gps', $driver_id);
    $arrivalReport = driver_trip_arrival_report($conn, (int)$trip['id']);
    if ($arrivalReport['reported']) {
        echo json_encode([
            "success" => true,
            "trip_active" => false,
            "arrival_reported" => true,
            "awaiting_confirmation" => true,
            "message" => "Arrival reported - awaiting terminal confirmation",
            "current_station" => null,
            "next_stop" => null,
            "eta" => null
        ]);
        exit;
    }
    /* The arrival columns are unavailable on an unmigrated install: fall
       through to the normal active-trip response instead of guessing. */
}

/*
|--------------------------------------------------------------------------
| PASSENGER COUNT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) total
    FROM passenger_trips
    WHERE trip_no = ?
");
$stmt->bind_param("i", $trip['id']);
$stmt->execute();
$totalPassengers = (int)$stmt->get_result()->fetch_assoc()['total'];

$trip_end = $eta !== ''
    ? date("Y-m-d H:i:s", time() + $remainingSeconds)
    : null;

/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "trip_active" => true,
    "arrival_reported" => false,
    "awaiting_confirmation" => false,
    "trip_id" => $trip['id'],
    "route_name" => $trip['route_name'],
    "current_station" => $trip['current_station'],
    "next_stop" => $trip['next_stop'],
    "eta" => $eta ?: null,
    "remaining_distance_km" => $remaining_distance_km,
    "avg_speed_kmh" => $avg_speed_kmh,
    "trip_start" => $trip_start,
    "trip_end" => $trip_end,
    "total_passengers" => $totalPassengers
]);

$conn->close();

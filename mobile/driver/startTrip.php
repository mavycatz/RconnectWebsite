<?php

header("Content-Type: application/json");
require_once "../config/database.php";
require_once __DIR__ . "/lib/arrival_report.php";

$db = new Database();
$conn = $db->connect();

$data = json_decode(file_get_contents("php://input"), true);

$driver_id = $data['driver_id'] ?? '';
$bus_id = $data['bus_id'] ?? '';

if (!$driver_id || !$bus_id) {
    echo json_encode([
        "success" => false,
        "message" => "Missing driver_id or bus_id"
    ]);
    exit;
}

/*
--------------------------------
REPORT ARRIVAL FOR OLD TRIPS
--------------------------------

Overtime trips are reported as arrived instead of completed: the receiving
terminal still confirms the arrival with the actual ending odometer.
*/

driver_report_expired_arrivals($conn, 120);

/*
--------------------------------
CHECK ACTIVE TRIP
--------------------------------
*/

$activeTrip = $conn->prepare("
    SELECT id
    FROM bus_trips
    WHERE bus_id = ?
    AND status = 'Departed'
    AND TIMESTAMPDIFF(
        MINUTE,
        CONCAT(trip_date,' ',departure_time),
        NOW()
    ) < 120
    LIMIT 1
");

$activeTrip->bind_param("s", $bus_id);
$activeTrip->execute();

if ($activeTrip->get_result()->num_rows > 0) {

    echo json_encode([
        "success" => false,
        "message" => "Bus already has an active trip"
    ]);

    exit;
}

/*
--------------------------------
GET DRIVER CURRENT LOCATION
--------------------------------
*/

$loc = $conn->prepare("
    SELECT latitude, longitude
    FROM driver_current_location
    WHERE driver_id = ?
    ORDER BY updated_at DESC
    LIMIT 1
");

$loc->bind_param("s", $driver_id);
$loc->execute();

$driverLoc = $loc->get_result()->fetch_assoc();

if (!$driverLoc) {

    echo json_encode([
        "success" => false,
        "message" => "No driver location found"
    ]);

    exit;
}

$driverLat = (float)$driverLoc['latitude'];
$driverLng = (float)$driverLoc['longitude'];

/*
--------------------------------
DISTANCE FUNCTION
--------------------------------
*/

function distance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a =
        sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) *
        cos(deg2rad($lat2)) *
        sin($dLon / 2) *
        sin($dLon / 2);

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    return $earthRadius * $c;
}

/*
--------------------------------
GET TERMINALS
--------------------------------
*/

$terminals = $conn->query("
    SELECT
        id,
        terminal_code,
        latitude,
        longitude
    FROM terminals
    WHERE status = 'Active'
");

$nearestTerminal = null;
$minDistance = PHP_FLOAT_MAX;

while ($terminal = $terminals->fetch_assoc()) {

    $distanceKm = distance(
        $driverLat,
        $driverLng,
        (float)$terminal['latitude'],
        (float)$terminal['longitude']
    );

    if ($distanceKm < $minDistance) {

        $minDistance = $distanceKm;
        $nearestTerminal = $terminal;
    }
}

/*
--------------------------------
VALIDATE DISTANCE
--------------------------------
*/

if ($minDistance > 0.5) {

    echo json_encode([
        "success" => false,
        "message" => "Driver not near any terminal"
    ]);

    exit;
}

$from_terminal_id = $nearestTerminal['id'];

/*
--------------------------------
GET DESTINATION TERMINAL
--------------------------------
*/

$toQuery = $conn->prepare("
    SELECT id
    FROM terminals
    WHERE id != ?
    LIMIT 1
");

$toQuery->bind_param("i", $from_terminal_id);
$toQuery->execute();

$to = $toQuery
    ->get_result()
    ->fetch_assoc();

$to_terminal_id = $to['id'] ?? null;

/*
--------------------------------
GET ROUTE
--------------------------------
*/

$routeQuery = $conn->prepare("
    SELECT route_name
    FROM buses
    WHERE bus_id = ?
    LIMIT 1
");

$routeQuery->bind_param("s", $bus_id);
$routeQuery->execute();

$route = $routeQuery
    ->get_result()
    ->fetch_assoc();

$route_name = $route['route_name'] ?? null;

if (!$route_name) {

    echo json_encode([
        "success" => false,
        "message" => "Bus route not found"
    ]);

    exit;
}

/*
--------------------------------
START NEW TRIP
--------------------------------
*/

$stmt = $conn->prepare("
    INSERT INTO bus_trips
    (
        bus_id,
        driver_id,
        route_name,
        from_terminal_id,
        to_terminal_id,
        trip_date,
        departure_time,
        status
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        CURDATE(),
        CURTIME(),
        'Departed'
    )
");

$stmt->bind_param(
    "sssii",
    $bus_id,
    $driver_id,
    $route_name,
    $from_terminal_id,
    $to_terminal_id
);

if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" => "Trip started successfully",
        "trip_id" => $conn->insert_id,
        "route_name" => $route_name,
        "from_terminal" => $nearestTerminal['terminal_code'],
        "distance_km" => round($minDistance, 3)
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Failed to start trip",
        "error" => $stmt->error
    ]);
}

$conn->close();
?>
<?php

header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$driver_id = $_GET['driver_id'] ?? '';
$token = $_GET['token'] ?? '';

require_once __DIR__ . '/lib/driver_auth.php';
require_driver_auth($conn, $driver_id, $token);

if (!$driver_id) {
    echo json_encode([
        "success" => false,
        "message" => "Driver ID required"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| GET BUS + DRIVER LOCATION
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        b.bus_id,
        b.plate_number,

        dcl.latitude AS current_lat,
        dcl.longitude AS current_lng

    FROM driver_bus_assignment dba

    INNER JOIN buses b
        ON b.bus_id = dba.bus_id

    LEFT JOIN driver_current_location dcl
        ON dcl.driver_id = dba.driver_id

    WHERE dba.driver_id = ?
    LIMIT 1
");

$stmt->bind_param("s", $driver_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "No bus assigned to driver"
    ]);
    exit;
}

$data = $result->fetch_assoc();

$currentLat = isset($data['current_lat'])
    ? (float)$data['current_lat']
    : 0;

$currentLng = isset($data['current_lng'])
    ? (float)$data['current_lng']
    : 0;

/*
|--------------------------------------------------------------------------
| SIMULATION OVERRIDE
|--------------------------------------------------------------------------
| While a session is active for this driver/bus, the simulated position is
| the bus position this endpoint reports. Read-only and additive.
*/

$simulationActive = false;
$simulationEngineCandidates = array(
    __DIR__ . '/../simulation/lib/simulation_engine.php',
    __DIR__ . '/simulation/lib/simulation_engine.php',
);

foreach ($simulationEngineCandidates as $simulationEngine) {
    if (file_exists($simulationEngine)) {
        require_once $simulationEngine;
        break;
    }
}

if (function_exists('simulation_override_for')) {
    $override = simulation_override_for(
        $conn,
        isset($data['bus_id']) ? (string)$data['bus_id'] : null,
        (string)$driver_id
    );

    if (($override['active'] ?? false) === true) {
        $currentLat = (float)$override['latitude'];
        $currentLng = (float)$override['longitude'];
        $simulationActive = true;
    }
}

/*
|--------------------------------------------------------------------------
| CHECK ACTIVE TRIP FIRST
|--------------------------------------------------------------------------
*/

$tripStmt = $conn->prepare("
    SELECT id, route_name, from_terminal_id, to_terminal_id, trip_number
    FROM bus_trips
    WHERE driver_id = ? AND status IN ('Departed', 'Interrupted')
    ORDER BY id DESC
    LIMIT 1
");

$tripStmt->bind_param("s", $driver_id);
$tripStmt->execute();
$tripResult = $tripStmt->get_result();
$activeTrip = $tripResult->fetch_assoc();

if ($activeTrip) {
    $routeName = $activeTrip['route_name'];

    $fromTerminalStmt = $conn->prepare("
        SELECT id, terminal_code, terminal_name, latitude, longitude
        FROM terminals WHERE id = ?
    ");
    $fromTerminalStmt->bind_param("i", $activeTrip['from_terminal_id']);
    $fromTerminalStmt->execute();
    $origin = $fromTerminalStmt->get_result()->fetch_assoc();

    $toTerminalStmt = $conn->prepare("
        SELECT id, terminal_code, terminal_name, latitude, longitude
        FROM terminals WHERE id = ?
    ");
    $toTerminalStmt->bind_param("i", $activeTrip['to_terminal_id']);
    $toTerminalStmt->execute();
    $destination = $toTerminalStmt->get_result()->fetch_assoc();

    $currentLocation = ($currentLat != 0 && $currentLng != 0)
        ? ["lat" => $currentLat, "lng" => $currentLng]
        : null;

    echo json_encode([
        "success" => true,
        "current_location" => $currentLocation,
        "simulation" => $simulationActive,
        "origin" => $origin ? [
            "id" => $origin['id'],
            "code" => $origin['terminal_code'],
            "name" => $origin['terminal_name'],
            "lat" => $origin['latitude'],
            "lng" => $origin['longitude']
        ] : null,
        "destination" => $destination ? [
            "id" => $destination['id'],
            "code" => $destination['terminal_code'],
            "name" => $destination['terminal_name'],
            "lat" => $destination['latitude'],
            "lng" => $destination['longitude']
        ] : null,
        "route" => [
            "bus_id" => $data['bus_id'],
            "plate_number" => $data['plate_number'],
            "route_name" => $routeName,
            "from_terminal_id" => $activeTrip['from_terminal_id'],
            "to_terminal_id" => $activeTrip['to_terminal_id'],
            "trip_id" => $activeTrip['id'],
            "trip_no" => $activeTrip['trip_number']
        ]
    ]);

    $tripStmt->close();
    $stmt->close();
    $conn->close();
    exit;
}

/*
|--------------------------------------------------------------------------
| NO ACTIVE TRIP — FALL BACK TO GPS PROXIMITY
|--------------------------------------------------------------------------
*/

if ($currentLat == 0 || $currentLng == 0) {
    $stmt2 = $conn->prepare("
        SELECT route_name FROM fare_pricing ORDER BY id ASC LIMIT 1
    ");
    $stmt2->execute();
    $routeRow = $stmt2->get_result()->fetch_assoc();
    $routeName = $routeRow['route_name'] ?? 'Rizal-Cabanatuan';

    echo json_encode([
        "success" => true,
        "current_location" => null,
        "simulation" => $simulationActive,
        "origin" => null,
        "destination" => null,
        "route" => [
            "bus_id" => $data['bus_id'],
            "plate_number" => $data['plate_number'],
            "route_name" => $routeName
        ]
    ]);

    $stmt2->close();
    $stmt->close();
    $conn->close();
    exit;
}

/*
|--------------------------------------------------------------------------
| DISTANCE FUNCTION
|--------------------------------------------------------------------------
*/

function distanceKm($lat1, $lon1, $lat2, $lon2)
{
    $earth = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth * $c;
}

/*
|--------------------------------------------------------------------------
| FIND NEAREST TERMINAL
|--------------------------------------------------------------------------
*/

$terminals = $conn->query("
    SELECT id, terminal_code, terminal_name, latitude, longitude
    FROM terminals WHERE status = 'Active'
");

$nearestTerminal = null;
$nearestDistance = PHP_FLOAT_MAX;
$allTerminals = [];

while ($terminal = $terminals->fetch_assoc()) {
    $allTerminals[] = $terminal;
    $distance = distanceKm(
        $currentLat, $currentLng,
        (float)$terminal['latitude'], (float)$terminal['longitude']
    );
    if ($distance < $nearestDistance) {
        $nearestDistance = $distance;
        $nearestTerminal = $terminal;
    }
}

if (!$nearestTerminal) {
    echo json_encode(["success" => false, "message" => "No active terminals found"]);
    exit;
}

/*
|--------------------------------------------------------------------------
| FIND DESTINATION TERMINAL
|--------------------------------------------------------------------------
*/

$destinationTerminal = null;
foreach ($allTerminals as $terminal) {
    if ($terminal['id'] != $nearestTerminal['id']) {
        $destinationTerminal = $terminal;
        break;
    }
}

if (!$destinationTerminal) {
    echo json_encode(["success" => false, "message" => "Destination terminal not found"]);
    exit;
}

/*
|--------------------------------------------------------------------------
| GET ROUTE NAME FROM FARE_PRICING
|--------------------------------------------------------------------------
*/

$fromCode = strtoupper($nearestTerminal['terminal_code']);
$toCode = strtoupper($destinationTerminal['terminal_code']);

if (strpos($fromCode, 'CAB') !== false && strpos($toCode, 'RIZAL') !== false) {
    $routeLookup = $conn->prepare("SELECT route_name FROM fare_pricing WHERE route_name = 'Cabanatuan-Rizal' LIMIT 1");
} else {
    $routeLookup = $conn->prepare("SELECT route_name FROM fare_pricing WHERE route_name = 'Rizal-Cabanatuan' LIMIT 1");
}

$routeLookup->execute();
$routeRow = $routeLookup->get_result()->fetch_assoc();
$routeName = $routeRow['route_name'] ?? (
    strpos($fromCode, 'CAB') !== false ? 'Cabanatuan-Rizal' : 'Rizal-Cabanatuan'
);

/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "current_location" => ["lat" => $currentLat, "lng" => $currentLng],
    "simulation" => $simulationActive,
    "origin" => [
        "id" => $nearestTerminal['id'],
        "code" => $nearestTerminal['terminal_code'],
        "name" => $nearestTerminal['terminal_name'],
        "lat" => $nearestTerminal['latitude'],
        "lng" => $nearestTerminal['longitude'],
        "distance_km" => round($nearestDistance, 3)
    ],
    "destination" => [
        "id" => $destinationTerminal['id'],
        "code" => $destinationTerminal['terminal_code'],
        "name" => $destinationTerminal['terminal_name'],
        "lat" => $destinationTerminal['latitude'],
        "lng" => $destinationTerminal['longitude']
    ],
    "route" => [
        "bus_id" => $data['bus_id'],
        "plate_number" => $data['plate_number'],
        "route_name" => $routeName,
        "from_terminal_id" => $nearestTerminal['id'],
        "to_terminal_id" => $destinationTerminal['id']
    ]
]);

$stmt->close();
$conn->close();

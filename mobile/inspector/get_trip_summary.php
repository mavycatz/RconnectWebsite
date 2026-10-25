<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('Asia/Manila');

error_reporting(E_ALL);
ini_set('display_errors', 0);

try {

    $conn = null;

    $configPaths = array(
        __DIR__ . "/../config/database.php",
        __DIR__ . "/../../config/database.php",
        __DIR__ . "/config/database.php",
        $_SERVER['DOCUMENT_ROOT'] . "/mobile/config/database.php",
        $_SERVER['DOCUMENT_ROOT'] . "/config/database.php",
        $_SERVER['DOCUMENT_ROOT'] . "/app/Core/Database.php",
    );

    foreach ($configPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            if (class_exists('Database')) {
                $db = new Database();
                $conn = $db->connect();
                if ($conn) {
                    break;
                }
            }
        }
    }

    if (!$conn && file_exists(__DIR__ . "/db_config.php")) {
        require_once __DIR__ . "/db_config.php";
    }

    if (!$conn) {
        echo json_encode([
            "status" => "error",
            "has_active_trip" => false,
            "message" => "Database connection failed"
        ]);
        exit;
    }

    @$conn->query("SET time_zone = '+08:00'");

    $bus_id = trim($_GET['bus_id'] ?? '');
    $inspector_id_param = trim($_GET['inspector_id'] ?? '');
    $param_lat = isset($_GET['lat']) && is_numeric($_GET['lat']) ? (float)$_GET['lat'] : null;
    $param_lng = isset($_GET['lng']) && is_numeric($_GET['lng']) ? (float)$_GET['lng'] : null;

    if (!$bus_id) {
        echo json_encode([
            "status" => "error",
            "has_active_trip" => false,
            "message" => "bus_id required"
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET ACTIVE TRIP
    |--------------------------------------------------------------------------
    | Prefer explicitly active/departed status. Fall back to any open (not
    | completed/cancelled) trip, and finally the latest trip overall.
    */

    $stmt = $conn->prepare("
        SELECT *
        FROM bus_trips
        WHERE bus_id = ?
        AND status IN ('Departed', 'Started', 'Active', 'Ongoing', 'On Route')
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->bind_param("s", $bus_id);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$trip) {
        $stmt = $conn->prepare("
            SELECT *
            FROM bus_trips
            WHERE bus_id = ?
            AND status NOT IN ('Completed', 'Cancelled')
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->bind_param("s", $bus_id);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$trip) {
        $stmt = $conn->prepare("
            SELECT *
            FROM bus_trips
            WHERE bus_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->bind_param("s", $bus_id);
        $stmt->execute();
        $trip = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$trip) {
        echo json_encode([
            "status" => "error",
            "has_active_trip" => false,
            "message" => "No trip found"
        ]);
        exit;
    }

    $trip_id = (int)$trip['id'];
    $route_name = $trip['route_name'] ?? '';
    $trip_status = $trip['status'] ?? 'Departed';
    $driver_id = $trip['driver_id'] ?? null;
    $is_closed = in_array(strtolower($trip_status), array('completed', 'cancelled'));
    $has_active_trip = !$is_closed;

    /*
    |--------------------------------------------------------------------------
    | GET LATEST ASSIGNMENT FOR THIS TRIP
    |--------------------------------------------------------------------------
    | Checked as metadata. The presence of an active trip on the bus does NOT
    | depend on whether the inspector has checked in.
    */

    $assignment = null;
    if (!empty($inspector_id_param)) {
        $stmt = $conn->prepare("
            SELECT inspector_id, status, assigned_at
            FROM inspector_assignments
            WHERE bus_id = ?
            AND trip_id = ?
            AND inspector_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->bind_param("sis", $bus_id, $trip_id, $inspector_id_param);
        $stmt->execute();
        $assignment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$assignment) {
        $stmt = $conn->prepare("
            SELECT inspector_id, status, assigned_at
            FROM inspector_assignments
            WHERE bus_id = ?
            AND trip_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->bind_param("si", $bus_id, $trip_id);
        $stmt->execute();
        $assignment = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $inspector_id = $assignment['inspector_id'] ?? ($inspector_id_param ?: null);
    $inspector_status = $assignment['status'] ?? 'Checked-Out';

    /*
    |--------------------------------------------------------------------------
    | RESOLVE COORDINATES (QUERY PARAMS -> INSPECTOR GPS -> DRIVER GPS)
    |--------------------------------------------------------------------------
    */

    $lat = null;
    $lng = null;

    if ($param_lat !== null && $param_lng !== null) {
        $lat = $param_lat;
        $lng = $param_lng;
    }

    if (($lat === null || $lng === null) && !empty($inspector_id)) {
        $stmt = $conn->prepare("
            SELECT latitude, longitude
            FROM inspectors_location
            WHERE inspector_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->bind_param("s", $inspector_id);
        $stmt->execute();
        $inspectorLoc = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($inspectorLoc && isset($inspectorLoc['latitude']) && isset($inspectorLoc['longitude'])) {
            $lat = (float)$inspectorLoc['latitude'];
            $lng = (float)$inspectorLoc['longitude'];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SIMULATION OVERRIDE (DRIVER/BUS FALLBACK)
    |--------------------------------------------------------------------------
    | The inspector's own device position above is never simulated. When a
    | simulation is active for the bus/driver, its position replaces the
    | driver_current_location fallback so stop detection stays consistent with
    | what the apps display. Read-only and additive.
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
        $simulationOverride = simulation_override_for($conn, $bus_id, $driver_id);

        if (($simulationOverride['active'] ?? false) === true && ($lat === null || $lng === null)) {
            $lat = (float)$simulationOverride['latitude'];
            $lng = (float)$simulationOverride['longitude'];
        }

        $simulationActive = ($simulationOverride['active'] ?? false) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | SIMULATION OVERRIDE (INSPECTOR)
    |--------------------------------------------------------------------------
    | When a simulation controls this inspector, the simulated coordinates win
    | over the inspector's own device position read above, so stop detection and
    | the app agree with the bus. Additive and read-only.
    */

    $simulationInspector = array('active' => false);

    if (function_exists('simulation_inspector_override_for') && !empty($inspector_id)) {
        $simulationInspector = simulation_inspector_override_for($conn, $inspector_id);
    }

    if (($simulationInspector['active'] ?? false) === true) {
        $lat = (float)$simulationInspector['latitude'];
        $lng = (float)$simulationInspector['longitude'];
        $simulationActive = true;
    }

    if (($lat === null || $lng === null) && !empty($driver_id)) {
        $stmt = $conn->prepare("
            SELECT latitude, longitude
            FROM driver_current_location
            WHERE driver_id = ?
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ");

        $stmt->bind_param("s", $driver_id);
        $stmt->execute();
        $driverLoc = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($driverLoc && isset($driverLoc['latitude']) && isset($driverLoc['longitude'])) {
            $lat = (float)$driverLoc['latitude'];
            $lng = (float)$driverLoc['longitude'];
        }
    }

    if ($lat === null || $lng === null) {
        $stmt = $conn->prepare("
            SELECT latitude, longitude
            FROM driver_current_location
            WHERE bus_id = ?
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ");

        $stmt->bind_param("s", $bus_id);
        $stmt->execute();
        $busLoc = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($busLoc && isset($busLoc['latitude']) && isset($busLoc['longitude'])) {
            $lat = (float)$busLoc['latitude'];
            $lng = (float)$busLoc['longitude'];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STOP DETECTION & UPCOMING STOPS
    |--------------------------------------------------------------------------
    | Stop detection uses the route's fare_pricing display_order as the single
    | source of truth.
    */

    require_once __DIR__ . '/lib/stop_detection.php';

    // The trip, fare and stop-map tables do not agree on the route-name separator
    // spelling ("Rizal - Cabanatuan" vs "Rizal-Cabanatuan"), so the fare lookup
    // uses the canonical fare name. The response still reports the trip's own
    // route_name, and only an unrecognised name is passed through unchanged.
    $fare_route_name = stop_detection_fare_route_name($route_name);

    $stmt = $conn->prepare("
        SELECT *
        FROM fare_pricing
        WHERE route_name = ?
        ORDER BY display_order ASC
    ");

    $stmt->bind_param("s", $fare_route_name);
    $stmt->execute();

    $stops = $stmt->get_result();
    $allStops = [];

    while ($stop = $stops->fetch_assoc()) {
        $allStops[] = $stop;
    }
    $stmt->close();

    $routeStops = [];
    $landmarkRoute = stop_detection_route_for_fare($route_name);

    if ($landmarkRoute !== null) {
        $stmt = $conn->prepare("
            SELECT id, route_name, sequence_order, stop_name, latitude, longitude, fare_pricing_id
            FROM route_stops
            WHERE route_name = ?
        ");

        $stmt->bind_param("s", $landmarkRoute);
        $stmt->execute();

        $landmarks = $stmt->get_result();
        while ($landmark = $landmarks->fetch_assoc()) {
            $routeStops[] = $landmark;
        }
        $stmt->close();
    }

    if ($lat !== null && $lng !== null) {
        $detected = stop_detection_resolve(
            $allStops,
            $route_name,
            $routeStops,
            (float) $lat,
            (float) $lng
        );
    } else {
        $detected = array(
            'current_stop' => null,
            'current_display_order' => null,
            'next_stop' => null,
            'destination' => !empty($allStops) ? end($allStops)['destination'] : null,
            'upcoming_stops' => stop_detection_upcoming_stops($allStops, 0, 3),
            'eta_minutes' => null,
            'stop_source' => 'none',
            'stop_distance_m' => null,
            'stop_radius_m' => null,
            'stop_route_matched' => $landmarkRoute !== null,
            'stop_landmarks_read' => count($routeStops),
            'stop_landmarks_matched' => 0,
            'stop_nearest_m' => null,
            'stop_order_source' => null,
            'stop_link_fallbacks' => 0,
        );
    }

    $currentStop = $detected['current_stop'];
    $currentDisplayOrder = $detected['current_display_order'];
    $nextStop = $detected['next_stop'];
    $finalStop = $detected['destination'] ?? (!empty($allStops) ? end($allStops)['destination'] : null);
    $upcomingStops = !empty($detected['upcoming_stops'])
        ? $detected['upcoming_stops']
        : stop_detection_upcoming_stops($allStops, $currentDisplayOrder ?? 0, 3);

    // The Inspector map draws only the short upcoming segment, so each upcoming
    // stop needs its own coordinates. They resolve through the same landmark map
    // the current/next/destination coordinates use - never by scanning the whole
    // route and never by proximity. A display_order with no landmark (see the
    // known gaps in lib/stop_detection.php) reports null, and the client drops
    // that point rather than drawing a straight line past it.
    foreach ($upcomingStops as $upcomingIndex => $upcomingStop) {
        $upcomingOrder = isset($upcomingStop['display_order']) ? (int) $upcomingStop['display_order'] : null;
        $upcomingName = isset($upcomingStop['name']) ? $upcomingStop['name'] : null;
        $upcomingCoord = stop_detection_find_coordinate($routeStops, $route_name, $upcomingOrder, $upcomingName);
        $upcomingStops[$upcomingIndex]['latitude'] = $upcomingCoord ? $upcomingCoord['latitude'] : null;
        $upcomingStops[$upcomingIndex]['longitude'] = $upcomingCoord ? $upcomingCoord['longitude'] : null;
    }

    $eta = $detected['eta_minutes'];

    $nextDisplayOrder = ($currentDisplayOrder !== null ? $currentDisplayOrder + 1 : 1);
    $finalDisplayOrder = !empty($allStops) ? (int)end($allStops)['display_order'] : null;

    $nextStopCoord = stop_detection_find_coordinate($routeStops, $route_name, $nextDisplayOrder, $nextStop);
    $destCoord = stop_detection_find_coordinate($routeStops, $route_name, $finalDisplayOrder, $finalStop);
    $currentStopCoord = stop_detection_find_coordinate($routeStops, $route_name, $currentDisplayOrder, $currentStop);

    $nextStopLat = $nextStopCoord ? $nextStopCoord['latitude'] : null;
    $nextStopLng = $nextStopCoord ? $nextStopCoord['longitude'] : null;

    $destLat = $destCoord ? $destCoord['latitude'] : null;
    $destLng = $destCoord ? $destCoord['longitude'] : null;

    $currentStopLat = $currentStopCoord ? $currentStopCoord['latitude'] : null;
    $currentStopLng = $currentStopCoord ? $currentStopCoord['longitude'] : null;

    $stopSource = $detected['stop_source'];
    $stopDistance = $detected['stop_distance_m'];
    $stopRadius = $detected['stop_radius_m'];
    $stopRouteMatched = $detected['stop_route_matched'];
    $stopLandmarksRead = $detected['stop_landmarks_read'];
    $stopLandmarksMatched = $detected['stop_landmarks_matched'];
    $stopNearest = $detected['stop_nearest_m'];
    $stopOrderSource = $detected['stop_order_source'];
    $stopLinkFallbacks = $detected['stop_link_fallbacks'];

    /*
    |--------------------------------------------------------------------------
    | PASSENGER COUNTS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) total
        FROM passenger_trips
        WHERE trip_no = ?
    ");

    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    $totalPassengers = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN p.space_occupied IS NOT NULL THEN p.space_occupied ELSE 1 END), 0) AS occupied_capacity_sum
        FROM passenger_trips pt
        LEFT JOIN passengers p ON pt.passenger_id = p.id
        WHERE pt.trip_no = ?
        AND pt.dropoff_time IS NULL
    ");

    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    $onboardRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $onboard = (int)($onboardRow['total'] ?? 0);
    // Use passenger count for occupied seats (verified boarded passengers)
    $occupiedCapacity = $onboard;

    $stmt = $conn->prepare("
        SELECT COUNT(*) total
        FROM passenger_trips
        WHERE trip_no = ?
        AND (
            payment_status IS NULL
            OR payment_status = ''
            OR LOWER(payment_status) != 'paid'
        )
    ");

    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    $unpaid = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | BUS CAPACITY (SEATS + STANDING)
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT seats, standing_capacity
        FROM buses
        WHERE bus_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $bus_id);
    $stmt->execute();
    $bus = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $totalSeats = (int)($bus['seats'] ?? 0);
    $standingCapacity = 0;
    $totalCapacity = $totalSeats;
    $availableSeats = $totalSeats > 0 ? max(0, $totalSeats - $occupiedCapacity) : 0;
    $standingPassengers = ($totalSeats > 0 && $occupiedCapacity > $totalSeats) ? ($occupiedCapacity - $totalSeats) : 0;

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        "status" => "success",
        "has_active_trip" => $has_active_trip,
        "tracking_available" => $has_active_trip,
        "simulation" => $simulationActive,
        "simulation_inspector" => ($simulationInspector['active'] ?? false) === true ? [
            "active" => true,
            "inspector_id" => $simulationInspector['inspector_id'],
            "bus_id" => $simulationInspector['bus_id'],
            "session_id" => $simulationInspector['session_id'],
            "latitude" => $simulationInspector['latitude'],
            "longitude" => $simulationInspector['longitude'],
        ] : null,

        "trip_id" => $trip_id,
        "bus_id" => $bus_id,

        "route_name" => $route_name,
        "trip_status" => $trip_status,

        "trip" => [
            "trip_id" => $trip_id,
            "bus_id" => $bus_id,
            "route" => $route_name,
            "status" => $trip_status,
            "driver_id" => $driver_id,
        ],

        "inspector_id" => $inspector_id,
        "inspector_status" => $inspector_status,

        "current_stop" => $currentStop,
        "current_stop_lat" => $currentStopLat,
        "current_stop_lng" => $currentStopLng,
        "current_display_order" => $currentDisplayOrder,

        "next_stop" => $nextStop,
        "next_stop_lat" => $nextStopLat,
        "next_stop_lng" => $nextStopLng,

        "destination" => $finalStop,
        "destination_lat" => $destLat,
        "destination_lng" => $destLng,

        "upcoming_stops" => $upcomingStops,

        "eta_minutes" => $eta,

        "stop_source" => $stopSource,
        "stop_distance_m" => $stopDistance,
        "stop_radius_m" => $stopRadius,
        "stop_route_matched" => $stopRouteMatched,
        "stop_landmarks_read" => $stopLandmarksRead,
        "stop_landmarks_matched" => $stopLandmarksMatched,
        "stop_nearest_m" => $stopNearest,
        "stop_order_source" => $stopOrderSource,
        "stop_link_fallbacks" => $stopLinkFallbacks,

        "total_passengers" => $totalPassengers,
        "onboard_passengers" => $onboard,
        "occupied_capacity" => $occupiedCapacity,
        "unpaid_passengers" => $unpaid,

        "total_seats" => $totalSeats,
        "standing_capacity" => $standingCapacity,
        "total_capacity" => $totalCapacity,
        "available_seats" => $availableSeats,
        "standing_passengers" => $standingPassengers,
        "occupied_seats" => $occupiedCapacity,
    ]);

    $conn->close();

} catch (Throwable $e) {
    error_log("[get_trip_summary] Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        "status" => "error",
        "has_active_trip" => false,
        "message" => $e->getMessage(),
        "file" => basename($e->getFile()),
        "line" => $e->getLine()
    ]);
    exit;
}

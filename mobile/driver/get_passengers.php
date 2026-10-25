<?php

header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$bus_no = $_GET['bus_no'] ?? $_GET['bus_id'] ?? '';
$driver_id = $_GET['driver_id'] ?? '';

if (empty($bus_no) || empty($driver_id)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing bus_no or driver_id"
    ]);
    exit;
}

// Get active trip from bus_trips
$tripStmt = $conn->prepare("
    SELECT id, route_name FROM bus_trips
    WHERE driver_id = ? AND status IN ('Departed', 'Started', 'Active', 'Ongoing', 'Interrupted')
    ORDER BY id DESC
    LIMIT 1
");

if (!$tripStmt) {
    echo json_encode([
        "success" => false,
        "message" => "bus_trips query failed: " . $conn->error
    ]);
    exit;
}

$tripStmt->bind_param("s", $driver_id);
$tripStmt->execute();
$tripResult = $tripStmt->get_result();
$activeTrip = $tripResult->fetch_assoc();
$tripStmt->close();

if (!$activeTrip) {
    echo json_encode([
        "success" => true,
        "count" => 0,
        "passengers" => [],
        "trip_active" => false,
        "message" => "No active trip found"
    ]);
    exit;
}

$activeTripId = $activeTrip['id'];
$tripRouteName = $activeTrip['route_name'] ?? '';

require_once __DIR__ . "/lib/actual_destination.php";

// Near-destination alert resolver. Deployed it is a sibling in lib/; in the
// repository it lives under api/driver/lib/.
$nearStopLib = __DIR__ . '/lib/near_stop.php';
if (!is_file($nearStopLib)) {
    $nearStopLib = __DIR__ . '/../api/driver/lib/near_stop.php';
}
require_once $nearStopLib;

$nearStopCtx = near_stop_prepare_context($conn, $tripRouteName, $driver_id);

// Optional short-distance early drop-off columns. They are present after the
// add_short_distance_early_dropoff migration; NULL placeholders keep the
// onboard list working if the code is deployed before the migration runs.
$passengerTripColumns = [];
$chkCols = $conn->query("SHOW COLUMNS FROM passenger_trips");
if ($chkCols) {
    while ($c = $chkCols->fetch_assoc()) {
        $passengerTripColumns[$c['Field']] = true;
    }
}
$optionalColumn = function ($name) use ($passengerTripColumns) {
    return isset($passengerTripColumns[$name]) ? "pt.$name," : "NULL AS $name,";
};
$colDropoffType = $optionalColumn('dropoff_type');
$colActualDropoff = $optionalColumn('actual_dropoff_point');
$colDropoffLat = $optionalColumn('dropoff_latitude');
$colDropoffLng = $optionalColumn('dropoff_longitude');
$colDistance = $optionalColumn('distance_traveled_km');
$colThreshold = $optionalColumn('dropoff_threshold_km');
$colReason = $optionalColumn('dropoff_reason');
$colBoardingLocStatus = $optionalColumn('boarding_location_status');
$colDropoffLocStatus = $optionalColumn('dropoff_location_status');
$colDestinationId = $optionalColumn('destination_id');
$colSelectedDestinationId = $optionalColumn('selected_destination_id');
$colSelectedDestination = $optionalColumn('selected_destination');
$colActualDestination = $optionalColumn('actual_destination');
$colPlannedDropoffLat = $optionalColumn('planned_dropoff_lat');
$colPlannedDropoffLng = $optionalColumn('planned_dropoff_lng');
$colPlannedDropoffName = $optionalColumn('planned_dropoff_name');
$colPlannedDropoffSetAt = $optionalColumn('planned_dropoff_set_at');

// Get passengers for the active trip only
$stmt = $conn->prepare("
    SELECT
        pt.id,
        pt.passenger_id,
        pt.passenger_code,
        pt.bus_no,
        pt.trip_no,
        pt.boarding_point,
        pt.destination,
        $colDestinationId
        $colSelectedDestinationId
        $colSelectedDestination
        $colActualDestination
        $colPlannedDropoffLat
        $colPlannedDropoffLng
        $colPlannedDropoffName
        $colPlannedDropoffSetAt
        pt.fare,
        pt.payment_method,
        pt.payment_status,
        pt.batch_id,
        pt.boarding_time,
        pt.dropoff_time,
        $colDropoffType
        $colActualDropoff
        $colDropoffLat
        $colDropoffLng
        $colDistance
        $colThreshold
        $colReason
        $colBoardingLocStatus
        $colDropoffLocStatus
        pt.latitude,
        pt.longitude,
        pt.passenger_photo,
        p.full_name,
        p.registration_type,
        COALESCE(p.type, 'passenger') AS type,
        CASE WHEN p.space_occupied IS NOT NULL THEN p.space_occupied ELSE 1 END AS space_occupied
    FROM passenger_trips pt
    LEFT JOIN passengers p ON pt.passenger_id = p.id
    WHERE
        pt.trip_no = ?
        AND pt.bus_no = ?
        AND pt.driver_id = ?
        AND pt.archived_status = 'no'
    ORDER BY (pt.dropoff_time IS NOT NULL) ASC, pt.boarding_time DESC
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => $conn->error
    ]);
    exit;
}

$stmt->bind_param("iss", $activeTripId, $bus_no, $driver_id);
$stmt->execute();

$result = $stmt->get_result();

$passengers = [];

while ($row = $result->fetch_assoc()) {
    // Server-computed actual destination (actual route stop, not selected destination)
    $actualStop = resolve_passenger_actual_stop($conn, $row, $tripRouteName);

    $row['actual_stop_id'] = $actualStop ? (int)$actualStop['id'] : null;
    $row['actual_stop_name'] = $actualStop ? $actualStop['stop_name'] : null;
    $row['actual_km_marker'] = $actualStop ? (int)$actualStop['km_marker'] : null;

    // Near-destination alert fields (read-only; defaults mean "not near").
    $row = array_merge($row, near_stop_row_fields($nearStopCtx, $row));

    $row['final_fare'] = null;
    if ($actualStop) {
        $row['final_fare'] = compute_final_fare(
            $conn,
            $tripRouteName,
            $row['boarding_point'] ?? '',
            $actualStop['stop_name'],
            null,
            isset($row['fare']) ? $row['fare'] : null,
            $row['destination'] ?? null
        );
    }

    $passengers[] = $row;
}

if (!defined('VEHICLE_CAPACITY')) {
    define('VEHICLE_CAPACITY', 10);
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
$occupiedCapacity = 0;
$passengerCount = 0;
$otherCount = 0;
foreach ($passengers as $p) {
    // Dropped-off passengers stay listed but no longer occupy space.
    if (!empty($p['dropoff_time'])) continue;
    $occ = (int)($p['space_occupied'] ?? 1);
    $occupiedCapacity += $occ;
    if (($p['type'] ?? 'passenger') === 'passenger') {
        $passengerCount++;
    } else {
        $otherCount++;
    }
}
$remainingCapacity = max(0, $capacity - $occupiedCapacity);

echo json_encode([
    "success" => true,
    "count" => count($passengers),
    "passengers" => $passengers,
    "trip_active" => true,
    "trip_id" => $activeTripId,
    "capacity" => $capacity,
    "occupied_capacity" => $occupiedCapacity,
    "passenger_count" => $passengerCount,
    "other_count" => $otherCount,
    "remaining_capacity" => $remainingCapacity,
    "near_destination_threshold_m" => near_stop_get_threshold_m()
]);

$stmt->close();
$conn->close();

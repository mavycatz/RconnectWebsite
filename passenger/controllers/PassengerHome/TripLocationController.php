<?php

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/matchRouteStop.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/ContinuationJourneyService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/DriverLocationService.php';
require_once __DIR__ . '/TripSchemaHelper.php';

use App\Core\Database;

if (!isset($_SESSION['passenger_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$passenger_id = (int)$_SESSION['passenger_id'];

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$lat = isset($data['latitude']) ? (float)$data['latitude'] : null;
$lng = isset($data['longitude']) ? (float)$data['longitude'] : null;
$accuracy = isset($data['accuracy']) ? (float)$data['accuracy'] : null;

if ($lat === null || $lng === null) {
    echo json_encode(['success' => false, 'message' => 'latitude and longitude are required']);
    exit;
}

$conn = Database::tryGet();
if (!$conn) {
    http_response_code(503);
    echo json_encode(['success' => false, 'code' => 'SERVICE_UNAVAILABLE', 'message' => 'Database connection temporarily unavailable']);
    exit;
}

// Destination alerts claim additive trip columns; initialize them here so a
// trip created through any path can still record its once-per-destination
// alerts. Idempotent and best-effort: a schema problem never breaks the
// location ping.
ensureTripFlowSchema($conn);

// =========================
// ACTIVE TRIP (must exist and be in progress)
// =========================
$activeTrip = passengerActiveTripForAlerts($conn, $passenger_id);

if (!$activeTrip) {
    echo json_encode([
        'success' => false,
        'message' => 'No in-progress trip found.'
    ]);
    exit;
}

// =========================
// ROUTE POSITION ASSOCIATION (optional, when determinable)
// =========================
$routePointId = null;
$routeKm = null;
$matchedStop = null;
$routeName = trim((string) ($activeTrip['route_name'] ?? ''));

try {
    if ($routeName !== '') {
        $matchedStop = matchRouteStop($lat, $lng, $routeName, $conn);
        if ($matchedStop) {
            $routePointId = (int)$matchedStop['id'];
            $routeKm = $matchedStop['km_marker'] !== null ? (int)$matchedStop['km_marker'] : null;
        }
    }
} catch (\Throwable $e) {
    // Route resolution is best-effort; still record the raw GPS point.
}

// =========================
// UPSERT LOCATION RECORD (one row per trip: first + last location)
// =========================
try {
    ensureLocationHistorySchema($conn);

    $stmt = $conn->prepare("SELECT location_id, latitude, longitude FROM passenger_location_history WHERE passenger_trip_id = ? LIMIT 1");
    $stmt->execute([(int)$activeTrip['id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $conn->prepare("
            UPDATE passenger_location_history
            SET latitude = ?, longitude = ?, accuracy = ?, recorded_at = NOW(), route_point_id = ?, route_km = ?
            WHERE location_id = ?
        ");
        $stmt->execute([$lat, $lng, $accuracy, $routePointId, $routeKm, (int)$existing['location_id']]);
        $locationId = (int)$existing['location_id'];

        // Remove duplicate rows left by the old per-fix recording behavior
        $stmt = $conn->prepare("DELETE FROM passenger_location_history WHERE passenger_trip_id = ? AND location_id <> ?");
        $stmt->execute([(int)$activeTrip['id'], $locationId]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO passenger_location_history
            (
                passenger_trip_id,
                first_latitude,
                first_longitude,
                first_recorded_at,
                latitude,
                longitude,
                accuracy,
                recorded_at,
                route_point_id,
                route_km
            )
            VALUES (?, ?, ?, NOW(), ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([
            (int)$activeTrip['id'],
            $lat,
            $lng,
            $lat,
            $lng,
            $accuracy,
            $routePointId,
            $routeKm
        ]);
        $locationId = (int)$conn->lastInsertId();
    }
} catch (\PDOException $e) {
    error_log('Passenger trip/location failed: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Could not record your location right now.'
    ]);
    exit;
}

// =========================
// DESTINATION ALERTS (proximity + passed)
// =========================
// DESTINATION ALERTS (bus location is the movement source)
// =========================
// The passenger's fix above is recorded for location history only. Movement
// toward the destination is evaluated from the live location of the bus on
// the passenger's active trip (existing Rconnect-Drive pipeline), so alerts
// work even when passenger phone GPS is unavailable. A failure here must
// never break location recording.
$destinationAlert = null;
$driverLocation = null;
$destinationAlertReason = null;
try {
    $driverLocation = passengerDriverLocationForTrip($conn, $activeTrip);
    $alertResult = passengerDestinationAlertForTrip($conn, $activeTrip, $driverLocation);
    $destinationAlert = $alertResult['alert'];
    $destinationAlertReason = $alertResult['reason'];
} catch (\Throwable $e) {
    $destinationAlert = null;
    $destinationAlertReason = 'error';
}

echo json_encode([
    'success' => true,
    'location_id' => $locationId,
    'passenger_trip_id' => (int)$activeTrip['id'],
    'first_latitude' => $existing ? $existing['first_latitude'] ?? null : $lat,
    'first_longitude' => $existing ? $existing['first_longitude'] ?? null : $lng,
    'latitude' => $lat,
    'longitude' => $lng,
    'accuracy' => $accuracy,
    'recorded_at' => date('Y-m-d H:i:s'),
    'route_point_id' => $routePointId,
    'route_km' => $routeKm,
    'nearest_stop' => $matchedStop ? [
        'stop_name' => $matchedStop['stop_name'],
        'distance_m' => $matchedStop['distance_m']
    ] : null,
    'gps_source' => is_array($driverLocation) ? ($driverLocation['source'] ?? null) : null,
    'bus_location' => $driverLocation,
    'destination_alert' => $destinationAlert,
    'destination_alert_reason' => $destinationAlertReason,
    'reason' => $destinationAlertReason
]);

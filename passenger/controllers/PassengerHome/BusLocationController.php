<?php

/**
 * Bus-current-location destination alerts for the passenger PWA.
 *
 * The passenger's destination comes from the active passenger_trips record
 * (planned_dropoff_*, or the locked selected_destination_id); the bus
 * position comes from the existing Rconnect-Drive GPS pipeline, newest
 * driver_location_history record first with driver_current_location as the
 * fallback. This endpoint lets the passenger page poll the alert state without
 * sending its own GPS, so destination alerts still work when passenger phone
 * location is disabled or unavailable.
 *
 * It never completes the trip or changes fare, payment, boarding, or
 * drop-off state; it only evaluates and claims the once-per-trip alerts.
 */

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/matchRouteStop.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/ContinuationJourneyService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/DriverLocationService.php';
require_once __DIR__ . '/TripSchemaHelper.php';

use App\Core\Database;

if (!isset($_SESSION['passenger_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$passenger_id = (int) $_SESSION['passenger_id'];

$conn = Database::get();

// Destination alerts claim additive trip columns; initialize them here so a
// trip created through any path can still record its once-per-destination
// alerts. Idempotent and best-effort.
ensureTripFlowSchema($conn);

$activeTrip = passengerActiveTripForAlerts($conn, $passenger_id);

if (!$activeTrip) {
    echo json_encode([
        'success' => true,
        'has_active_trip' => false,
        'gps_source' => null,
        'bus_location' => null,
        'destination_alert' => null,
        'reason' => 'no_active_trip',
    ]);
    exit;
}

$driverLocation = passengerDriverLocationForTrip($conn, $activeTrip);
$result = passengerDestinationAlertForTrip($conn, $activeTrip, $driverLocation);

echo json_encode([
    'success' => true,
    'has_active_trip' => true,
    'passenger_trip_id' => (int) $activeTrip['id'],
    'trip_no' => $activeTrip['trip_no'] ?? null,
    'gps_source' => $driverLocation['source'] ?? null,
    'bus_location' => $result['bus_location'] ?? $driverLocation,
    'destination_alert' => $result['alert'],
    'destination_alert_reason' => $result['reason'],
    'reason' => $result['reason'],
]);

<?php

/**
 * Read-only destination route map coverage for the logged-in passenger.
 *
 * The official destination is always the active trip's stored
 * selected_destination_id; no request input is read and nothing is written.
 */

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/RouteMapService.php';
require_once __DIR__ . '/TripSchemaHelper.php';

use App\Core\Database;

if (!isset($_SESSION['passenger_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'code' => 'NOT_AUTHENTICATED',
        'message' => 'Not authenticated',
    ]);
    exit;
}

$passenger_id = (int) $_SESSION['passenger_id'];

$conn = Database::tryGet();
if ($conn === null) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'code' => 'DB_UNAVAILABLE',
        'message' => 'We could not load the route map right now. Please try again.',
    ]);
    exit;
}

// The planned get-off columns are lazily added; ensure them so a stored point
// is returned with the map instead of silently missing.
try {
    ensureTripFlowSchema($conn);
} catch (\Throwable $e) {
    // The map itself only needs reads; a schema failure must not hide it.
}

try {
    $stmt = $conn->prepare("
        SELECT pt.*, bt.route_name AS bus_route_name
        FROM passenger_trips pt
        LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
        WHERE pt.passenger_id = ?
        AND pt.dropoff_time IS NULL
        AND (
            pt.trip_status IS NULL
            OR LOWER(pt.trip_status) NOT IN ('completed', 'cancelled')
        )
        AND (
            bt.id IS NULL
            OR LOWER(bt.status) NOT IN ('completed', 'cancelled')
        )
        ORDER BY pt.id DESC
        LIMIT 1
    ");
    $stmt->execute([$passenger_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log('Passenger route-map trip lookup failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'code' => 'SERVER_ERROR',
        'message' => 'We could not load the route map right now. Please try again.',
    ]);
    exit;
}

if (!$trip) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'code' => 'NO_ACTIVE_TRIP',
        'message' => 'No in-progress trip found.',
    ]);
    exit;
}

try {
    $result = passengerBuildRouteMapCoverage($conn, $trip);
} catch (\Throwable $e) {
    error_log('Passenger route-map build failed: ' . $e->getMessage());
    $result = [
        'success' => false,
        'code' => 'ROUTE_MAP_UNAVAILABLE',
        'message' => 'Route map is not available right now.',
    ];
}

if (!is_array($result)) {
    $result = [
        'success' => false,
        'code' => 'ROUTE_MAP_UNAVAILABLE',
        'message' => 'Route map is not available right now.',
    ];
}

http_response_code(200);
echo json_encode($result);

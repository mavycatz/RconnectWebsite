<?php

/**
 * Planned get-off point for the logged-in passenger's active trip.
 *
 * The passenger taps the destination map to choose where they will get off.
 * The point is validated against the selected destination's geographic area
 * (computed by the route-map service, the same area the map draws) and stored
 * on the trip. This endpoint never changes the official destination, fare,
 * payment, or drop-off/geofence behavior; it writes only the planned
 * get-off columns.
 */

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/PlannedDropoffService.php';
require_once __DIR__ . '/TripSchemaHelper.php';

use App\Core\Database;

function passengerDropoffFail($code, $message, $status = 400, array $extra = [])
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => false,
        'code' => $code,
        'message' => $message,
    ], $extra));
    exit;
}

if (!isset($_SESSION['passenger_id'])) {
    passengerDropoffFail('NOT_AUTHENTICATED', 'Not authenticated', 401);
}

$passenger_id = (int) $_SESSION['passenger_id'];

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    passengerDropoffFail('INVALID_JSON', 'Invalid request.', 400);
}

$latitude = isset($data['latitude']) && is_numeric($data['latitude']) ? (float) $data['latitude'] : null;
$longitude = isset($data['longitude']) && is_numeric($data['longitude']) ? (float) $data['longitude'] : null;

if ($latitude === null || $longitude === null || !is_finite($latitude) || !is_finite($longitude)) {
    passengerDropoffFail('INVALID_POINT', 'A valid map point is required.', 400);
}

if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    passengerDropoffFail('INVALID_POINT', 'A valid map point is required.', 400);
}

$conn = Database::tryGet();
if ($conn === null) {
    passengerDropoffFail('DB_UNAVAILABLE', 'We could not save your get-off point right now. Please try again.', 503);
}

ensureTripFlowSchema($conn);

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
    error_log('Passenger get-off point trip lookup failed: ' . $e->getMessage());
    passengerDropoffFail('SERVER_ERROR', 'We could not save your get-off point right now. Please try again.', 500);
}

if (!$trip) {
    passengerDropoffFail('NO_ACTIVE_TRIP', 'No in-progress trip found.', 409);
}

$result = passengerSavePlannedDropoff($conn, $trip, $latitude, $longitude);

if (!is_array($result) || ($result['success'] ?? false) !== true) {
    $code = (string) ($result['code'] ?? 'PERSIST_FAILED');
    $status = 422;
    if ($code === 'INVALID_POINT') {
        $status = 400;
    } elseif (in_array($code, ['SERVER_ERROR', 'PERSIST_FAILED', 'DB_UNAVAILABLE'], true)) {
        $status = 500;
    }

    $extra = [];
    if (isset($result['destination_area'])) {
        $extra['destination_area'] = $result['destination_area'];
        $extra['destination_area_radius_meters'] = $result['destination_area_radius_meters'] ?? null;
    }

    passengerDropoffFail($code, (string) ($result['message'] ?? 'We could not save your get-off point right now. Please try again.'), $status, $extra);
}

http_response_code(200);
echo json_encode($result);

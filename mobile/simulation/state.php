<?php

/**
 * Shared simulation read endpoint for the website and both mobile apps.
 *
 *   GET ?bus_id=<bus> | ?driver_id=<driver> | ?session_id=<id>  (any one)
 *
 * With no parameter, the latest non-disabled session is returned.
 *
 * RESPONSE
 *   {
 *     "success": true,
 *     "message": "Simulation state" | "No active simulation",
 *     "data": {
 *       "active": bool,
 *       "simulation": true,
 *       "session_id": int,
 *       "bus_id": string,
 *       "driver_id": string|null,
 *       "trip_id": int|null,
 *       "route_name": string,
 *       "route_direction": string,
 *       "status": "idle"|"running"|"paused"|"completed",
 *       "latitude": float,
 *       "longitude": float,
 *       "speed_kmh": float|null,
 *       "progress_pct": float,
 *       "current_stop": string|null,
 *       "next_stop": string|null,
 *       "arrived_stops": int,
 *       "total_stops": int,
 *       "updated_at": string
 *     } | {"active": false}
 *
 *   Read-only: nothing in this endpoint writes, and when no simulation exists
 *   the client must keep using the real GPS read paths.
 */

header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/lib/simulation_engine.php';

$db = new Database();
$conn = $db->connect();

$session = null;

try {
    $sessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
    $busId = isset($_GET['bus_id']) ? trim((string) $_GET['bus_id']) : '';
    $driverId = isset($_GET['driver_id']) ? trim((string) $_GET['driver_id']) : '';

    if ($sessionId > 0) {
        $session = simulation_find_session($conn, $sessionId);
    } elseif ($busId !== '' || $driverId !== '') {
        $session = simulation_find_active_session(
            $conn,
            $busId !== '' ? $busId : null,
            $driverId !== '' ? $driverId : null
        );
    } else {
        $session = simulation_latest_active_session($conn);
    }
} catch (\Throwable $error) {
    $session = null;
}

if (!$session) {
    echo json_encode([
        "success" => true,
        "message" => "No active simulation",
        "data" => ["active" => false],
    ]);
    $conn->close();
    exit;
}

$payload = simulation_session_payload($conn, $session);
$payload['inspector'] = simulation_inspector_payload_for_session($conn, $session);

echo json_encode([
    "success" => true,
    "message" => "Simulation state",
    "data" => $payload,
]);

$conn->close();

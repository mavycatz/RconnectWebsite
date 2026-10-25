<?php
header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$bus_id = trim($_GET['bus_id'] ?? '');
if (!$bus_id) dispatcher_json_error(400, "bus_id required");

$stmt = dispatcher_statement($conn, "SELECT b.bus_id, b.plate_number, b.bus_type, b.seats, b.standing_capacity,
                                            b.route_name, b.status, b.qr_code, b.schedule,
                                            d.full_name AS driver_name, d.driver_id
                                     FROM buses b LEFT JOIN drivers d ON d.driver_id = b.driver
                                     WHERE b.bus_id = ? LIMIT 1", "s", [$bus_id]);
if (!$stmt) dispatcher_json_error(500, "Failed to load bus");
$bus = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$bus) dispatcher_json_error(404, "Bus not found");

$tripStmt = dispatcher_statement($conn, "SELECT id, route_name, departure_time, arrival_time, status
                                         FROM bus_trips WHERE bus_id = ? AND trip_date = CURDATE()
                                         ORDER BY departure_time DESC LIMIT 1", "s", [$bus_id]);
$trip = null;
if ($tripStmt) {
    $trip = $tripStmt->get_result()->fetch_assoc();
    $tripStmt->close();
}
$bus['current_trip'] = $trip;

echo json_encode(["success" => true, "data" => $bus]);
$conn->close();

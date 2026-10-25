<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();
dispatcher_require_csrf();

$db = new Database();
$conn = $db->connect();

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    $data = [];
}
dispatcher_assert_identity($data, $dispatcher_user);

$bus_id = trim((string)($data['bus_id'] ?? ''));
$date = date('Y-m-d');
$terminal_id = (int)($_SESSION['terminal_id'] ?? 0);
if ($terminal_id <= 0) {
    dispatcher_json_error(400, "No terminal selected");
}
if ($bus_id === '') {
    dispatcher_json_error(400, "Missing bus_id");
}

if (!dispatcher_table_exists($conn, 'buses')) {
    dispatcher_json_error(500, "Queue unavailable");
}
if (!dispatcher_bus_in_terminal_queue($conn, $bus_id, $terminal_id, $date)) {
    dispatcher_json_error(403, "This bus is not part of your terminal queue");
}

/* Persist the destination terminal derived from the bus's current terminal
   before the trip is prepared, so the dispatch step and every downstream
   consumer (driver app, GPS tracking, arrival detection) see the same
   destination. Unverifiable terminals block with a clear error. */
$destination = dispatcher_ensure_bus_destination($conn, $bus_id, $terminal_id, $date);
if (!$destination['success']) {
    dispatcher_json_error(409, $destination['error']);
}

/* Persist the route the same way: the bus's own route, a route already
   recorded for this direction, or the default terminal-pair route. */
$route = dispatcher_ensure_bus_route($conn, $bus_id, $terminal_id, (int)$destination['terminal_id']);
if (!$route['success']) {
    dispatcher_json_error(409, $route['error']);
}

$driver = dispatcher_resolve_day_driver($conn, $bus_id, $date);
if ($driver === null || empty($driver['driver_id'])) {
    dispatcher_json_error(400, dispatcher_t('prepare.no_driver', 'Assign a driver before preparing this trip.'));
}

/* Prepare Trip follows check-in in the sequence. */
$checkinMap = dispatcher_queue_checkin_map($conn, $date, [$bus_id]);
$checkin = $checkinMap[$bus_id] ?? null;
if ($checkin === null || (string)($checkin['driver_id'] ?? '') !== (string)$driver['driver_id']) {
    dispatcher_json_error(409, dispatcher_t('prepare.check_in_first', 'Check in the driver before preparing this trip.'));
}

$preparedMap = dispatcher_queue_prepared_map($conn, $date, [$bus_id]);
$prepared = $preparedMap[$bus_id] ?? null;
if ($prepared !== null && (string)($prepared['driver_id'] ?? '') === (string)$driver['driver_id']) {
    echo json_encode([
        "success" => true,
        "message" => dispatcher_t('prepare.already', 'This trip is already prepared.'),
        "data" => [
            "bus_id" => $bus_id,
            "driver_id" => (string)$driver['driver_id'],
            "driver_name" => (string)$driver['name'],
            "already_prepared" => true,
        ],
    ]);
    $conn->close();
    exit;
}

$recorded = dispatcher_queue_record_prepared($conn, $dispatcher_user, $bus_id, $driver['driver_id'], $driver['name'], $date);
if (!$recorded) {
    error_log("Failed to record trip preparation for bus $bus_id");
    dispatcher_json_error(500, dispatcher_t('prepare.error', 'The trip preparation could not be saved. Please try again.'));
}

echo json_encode([
    "success" => true,
    "message" => dispatcher_t('prepare.success', 'Trip prepared'),
    "data" => [
        "bus_id" => $bus_id,
        "driver_id" => (string)$driver['driver_id'],
        "driver_name" => (string)$driver['name'],
        "prepared_by" => (string)($dispatcher_user['name'] ?? ''),
        "prepared_at" => date('Y-m-d H:i:s'),
    ],
]);
$conn->close();

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

$driver = dispatcher_resolve_day_driver($conn, $bus_id, $date);
if ($driver === null || empty($driver['driver_id'])) {
    dispatcher_json_error(400, dispatcher_t('checkout.no_driver', 'No driver is assigned to this bus.'));
}

/* Check-out follows check-in: the driver on the bus must have been checked in
   for today before a check-out can be recorded. */
$checkinMap = dispatcher_queue_checkin_map($conn, $date, [$bus_id]);
$checkin = $checkinMap[$bus_id] ?? null;
if ($checkin === null
    || (string)($checkin['driver_id'] ?? '') === ''
    || (string)$checkin['driver_id'] !== (string)$driver['driver_id']) {
    dispatcher_json_error(409, dispatcher_t('checkout.check_in_first', 'Check in the driver before recording a check-out.'));
}

$checkoutMap = dispatcher_queue_checkout_map($conn, $date, [$bus_id]);
$existingCheckout = $checkoutMap[$bus_id] ?? null;
if ($existingCheckout !== null
    && (string)($existingCheckout['driver_id'] ?? '') !== ''
    && (string)$existingCheckout['driver_id'] === (string)$driver['driver_id']) {
    dispatcher_json_error(409, dispatcher_t('checkout.already', 'This driver has already been checked out.'));
}

$recorded = dispatcher_queue_record_checkout($conn, $dispatcher_user, $bus_id, $driver['driver_id'], $driver['name'], $date);
if (!$recorded) {
    error_log("Failed to record driver check-out for bus $bus_id");
    dispatcher_json_error(500, dispatcher_t('checkout.error', 'The driver check-out could not be saved. Please try again.'));
}

echo json_encode([
    "success" => true,
    "message" => dispatcher_t('checkout.success_title', 'Driver Checked Out'),
    "data" => [
        "bus_id" => $bus_id,
        "driver_id" => $driver['driver_id'],
        "driver_name" => $driver['name'],
        "checked_out_by" => "dispatcher",
        "checked_out_by_name" => (string)($dispatcher_user['name'] ?? ''),
        "checked_out_at" => date('Y-m-d H:i:s'),
    ],
]);
$conn->close();

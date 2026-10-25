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
    dispatcher_json_error(400, dispatcher_t('checkin.no_driver', 'No driver is assigned to this bus.'));
}

$checkinMap = dispatcher_queue_checkin_map($conn, $date, [$bus_id]);
$existingCheckin = $checkinMap[$bus_id] ?? null;
if ($existingCheckin !== null
    && (string)($existingCheckin['driver_id'] ?? '') !== ''
    && (string)$existingCheckin['driver_id'] === (string)$driver['driver_id']) {
    dispatcher_json_error(409, dispatcher_t('checkin.already', 'This driver is already checked in.'));
}

$conn->begin_transaction();
try {
    /* A driver resolved from the operating record becomes the formal day
       assignment on the first dispatcher action. */
    if ((string)($driver['source'] ?? '') === 'trip_record') {
        if (!dispatcher_ensure_bus_assignment($conn, $bus_id, $driver['driver_id'])) {
            throw new Exception('assignment sync failed');
        }
        dispatcher_audit_log(
            $conn,
            $dispatcher_user,
            'Driver Assigned',
            null,
            "Driver " . $driver['name'] . " assigned to Bus $bus_id from today's trip record",
            [],
            [
                'bus_id'          => $bus_id,
                'driver_id'       => (string)$driver['driver_id'],
                'driver_name'     => (string)$driver['name'],
                'assignment_date' => $date,
                'assigned_by'     => (string)($dispatcher_user['name'] ?? ''),
                'assigned_at'     => date('Y-m-d H:i:s'),
                'source'          => 'trip_record',
            ]
        );
    }

    $recorded = dispatcher_queue_record_checkin($conn, $dispatcher_user, $bus_id, $driver['driver_id'], $driver['name'], $date);
    if (!$recorded) {
        throw new Exception('check-in failed');
    }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Failed to record driver check-in for bus $bus_id: " . $e->getMessage());
    dispatcher_json_error(500, dispatcher_t('checkin.error', 'The driver check-in could not be saved. Please try again.'));
}

echo json_encode([
    "success" => true,
    "message" => dispatcher_t('checkin.success_title', 'Driver Checked In'),
    "data" => [
        "bus_id" => $bus_id,
        "driver_id" => $driver['driver_id'],
        "driver_name" => $driver['name'],
        "checked_in_by" => "dispatcher",
        "checked_in_by_name" => (string)($dispatcher_user['name'] ?? ''),
        "checked_in_at" => date('Y-m-d H:i:s'),
    ],
]);
$conn->close();

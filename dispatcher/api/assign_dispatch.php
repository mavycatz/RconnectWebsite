<?php
header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";
require_once __DIR__ . "/../../app/Models/Notification.php";

dispatcher_require_auth();
dispatcher_require_csrf();

$db = new Database();
$conn = $db->connect();

$data = json_decode(file_get_contents("php://input"), true);
dispatcher_assert_identity($data, $dispatcher_user);
$bus_id = trim($data['bus_id'] ?? '');
$driver_id = trim($data['driver_id'] ?? '');
$route_name = trim($data['route_name'] ?? '');
$departure_time = trim($data['departure_time'] ?? '');
$trip_date = trim($data['trip_date'] ?? date('Y-m-d'));

if (!$bus_id || !$route_name || !$departure_time) {
    dispatcher_json_error(400, "Missing required fields");
}

// Validate that the selected bus exists and is active.
if (!dispatcher_valid_bus($conn, $bus_id)) {
    dispatcher_json_error(400, "Selected bus is not available");
}
if (!empty($driver_id) && !dispatcher_valid_driver($conn, $driver_id)) {
    dispatcher_json_error(400, "Selected driver is not available");
}

/* The trip records its terminals so the receiving terminal can see it and
   offer the Arrive action. The origin is the dispatcher's session terminal;
   the destination is derived from the bus's current terminal, falling back to
   the opposite terminal of the configured pair. */
$from_terminal_id = (int)($_SESSION['terminal_id'] ?? 0);
if ($from_terminal_id <= 0) {
    dispatcher_json_error(400, "No terminal selected");
}
$destination = dispatcher_resolve_destination($conn, $bus_id, $from_terminal_id, $trip_date);
$to_terminal_id = $destination['terminal_id'] !== null ? (int)$destination['terminal_id'] : 0;
if ($to_terminal_id <= 0) {
    $to_terminal_id = (int)dispatcher_destination_from_pair(dispatcher_terminal_pair($conn), $from_terminal_id, 0);
}
if ($to_terminal_id > 0 && !dispatcher_valid_terminal($conn, $to_terminal_id)) {
    dispatcher_json_error(409, "The destination terminal for this bus is not active. Check the terminal configuration.");
}

$check = dispatcher_statement($conn, "SELECT id FROM bus_trips WHERE bus_id = ? AND trip_date = ? AND status IN ('Scheduled','Departed','Interrupted') LIMIT 1", "ss", [$bus_id, $trip_date]);
if (!$check) dispatcher_json_error(500, "Assignment check failed");
if ($check->get_result()->num_rows > 0) {
    $check->close();
    dispatcher_json_error(409, "Bus already assigned to a trip today");
}
$check->close();

if (!empty($driver_id)) {
    $dc = dispatcher_statement($conn, "SELECT id FROM bus_trips WHERE driver_id = ? AND trip_date = ? AND status IN ('Scheduled','Departed','Interrupted') LIMIT 1", "ss", [$driver_id, $trip_date]);
    if (!$dc) dispatcher_json_error(500, "Assignment check failed");
    if ($dc->get_result()->num_rows > 0) {
        $dc->close();
        dispatcher_json_error(409, "Driver already assigned to another trip today");
    }
    $dc->close();
}

$conn->begin_transaction();
try {
    $arrival = date('H:i:s', strtotime($departure_time) + 7200);
    if (!empty($driver_id)) {
        $ins = dispatcher_statement($conn, "INSERT INTO bus_trips (bus_id, driver_id, route_name, from_terminal_id, to_terminal_id, trip_date, departure_time, arrival_time, status)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled')", "sssiisss", [$bus_id, $driver_id, $route_name, $from_terminal_id, $to_terminal_id, $trip_date, $departure_time, $arrival]);
    } else {
        $ins = dispatcher_statement($conn, "INSERT INTO bus_trips (bus_id, route_name, from_terminal_id, to_terminal_id, trip_date, departure_time, arrival_time, status)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, 'Scheduled')", "ssiisss", [$bus_id, $route_name, $from_terminal_id, $to_terminal_id, $trip_date, $departure_time, $arrival]);
    }
    if (!$ins) throw new Exception("insert failed");
    $dispatch_id = $conn->insert_id;
    $ins->close();

    if (!empty($driver_id)) {
        $upd = dispatcher_statement($conn, "UPDATE buses SET driver = ?, route_name = ? WHERE bus_id = ?", "sss", [$driver_id, $route_name, $bus_id]);
        if (!$upd) throw new Exception("bus update failed");
        $upd->close();
    }
    dispatcher_audit_log(
        $conn,
        $dispatcher_user,
        'Dispatch Scheduled',
        $dispatch_id,
        "Trip #$dispatch_id scheduled on route $route_name (Bus $bus_id)",
        [],
        [
            'trip_id'        => $dispatch_id,
            'bus_id'         => $bus_id,
            'driver_id'      => $driver_id !== '' ? $driver_id : null,
            'route_name'     => $route_name,
            'trip_date'      => $trip_date,
            'departure_time' => $departure_time,
            'from_terminal_id' => $from_terminal_id,
            'to_terminal_id'   => $to_terminal_id > 0 ? $to_terminal_id : null,
            'status'         => 'Scheduled',
        ]
    );

    $conn->commit();

    try {
        $notif = new \App\Models\Notification();
        $notif->createForAdminRoles(
            'New Trip Scheduled',
            "New trip assigned on route $route_name (Bus $bus_id)",
            'info',
            'trip',
            (int)$dispatch_id
        );
    } catch (\Throwable $e) {
        error_log("Trip notification error: " . $e->getMessage());
    }

    echo json_encode(["success" => true, "message" => "Dispatch created", "trip_no" => $dispatch_id, "dispatch_id" => $dispatch_id]);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Dispatch assign error: " . $e->getMessage());
    dispatcher_json_error(500, "Failed to create dispatch. Please try again.");
}
$conn->close();

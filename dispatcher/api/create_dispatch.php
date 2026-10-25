<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";
require_once __DIR__ . "/../../app/Core/Database.php";
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
$passenger_count = max(0, (int)($data['passenger_count'] ?? 0));
$trip_date = date('Y-m-d');

// Terminal comes from the authenticated session ONLY — never trust the body.
$from_terminal_id = (int)($_SESSION['terminal_id'] ?? 0);
$to_terminal_id = (int)($data['to_terminal_id'] ?? 0);

if (!$bus_id) {
    dispatcher_json_error(400, "Missing required fields: bus_id");
}
if ($from_terminal_id <= 0) {
    dispatcher_json_error(400, "No terminal selected");
}

/* The starting odometer is the dispatching terminal's actual reading and the
   authoritative basis for the trip's distance. Fuel liters and a manual KPL
   are no longer part of dispatch. */
$starting_odo = dispatcher_normalize_starting_odo($data['starting_odo'] ?? null);
if ($starting_odo === null) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "code" => "starting_odo_required",
        "message" => "Starting odometer (km) is required. Enter the bus's actual odometer reading before dispatching.",
    ]);
    $conn->close();
    exit;
}

// Validate that the selected records actually exist and are active.
if (!dispatcher_valid_bus($conn, $bus_id)) {
    dispatcher_json_error(400, "Selected bus is not available");
}

/* The day assignment is the source of truth: dispatch always uses the driver
   assigned to the bus, falling back to the driver on the bus's latest trip
   today. A client-supplied driver must match that driver. */
$assignedDriver = dispatcher_resolve_day_driver($conn, $bus_id, $trip_date);
if ($assignedDriver === null || empty($assignedDriver['driver_id'])) {
    dispatcher_json_error(409, "No driver is assigned to this bus. Assign a driver before dispatching.");
}
if ($driver_id !== '' && $driver_id !== (string)$assignedDriver['driver_id']) {
    dispatcher_json_error(409, "The selected driver does not match the driver assigned to this bus.");
}
$driver_id = (string)$assignedDriver['driver_id'];
if (!dispatcher_valid_driver($conn, $driver_id)) {
    dispatcher_json_error(400, "The driver assigned to this bus is not available");
}
/* Destination: derive it from the bus's current terminal and persist it on the
   bus before the trip row is written, so dispatch, the driver app, GPS
   tracking, and arrival detection all agree on it. A bus whose current
   terminal cannot be verified is never given a guessed destination. */
$destination = dispatcher_ensure_bus_destination($conn, $bus_id, $from_terminal_id, $trip_date);
if (!$destination['success']) {
    dispatcher_json_error(409, $destination['error']);
}
$resolved_to_terminal_id = (int)$destination['terminal_id'];
if ($to_terminal_id > 0 && $to_terminal_id !== $resolved_to_terminal_id) {
    dispatcher_json_error(409, "The selected destination terminal does not match the bus's current terminal. Refresh the queue and try again.");
}
$to_terminal_id = $resolved_to_terminal_id;
if (!dispatcher_valid_terminal($conn, $to_terminal_id)) {
    dispatcher_json_error(409, "The destination terminal for this bus is not active. Check the terminal configuration.");
}

/* Route: keep the dispatcher's explicit selection when provided, otherwise use
   the bus's stored route or the recorded/default route for this terminal
   direction, and persist it on the bus when it has none. */
$routeResolution = dispatcher_ensure_bus_route($conn, $bus_id, $from_terminal_id, $to_terminal_id, $route_name);
if ($route_name === '' || strcasecmp($route_name, 'TBD') === 0) {
    if (!$routeResolution['success']) {
        dispatcher_json_error(409, $routeResolution['error']);
    }
    $route_name = (string)$routeResolution['route'];
}
if ($route_name === '') {
    dispatcher_json_error(409, 'A route is required to dispatch this bus.');
}

/* Terminal-based queue entry: the bus must have physically entered this
   terminal's queue - through its operating-date terminal assignment or a
   confirmed trip record - and its driver must be assigned to this terminal
   too. Installations without configured terminals keep the legacy behavior. */
if (!empty(dispatcher_active_terminal_ids($conn))) {
    $gateCols = dispatcher_table_columns($conn, 'buses');
    $gateSelect = ['bus_id'];
    foreach (['from_terminal_id', 'to_terminal_id'] as $gateCandidate) {
        if (isset($gateCols[$gateCandidate])) {
            $gateSelect[] = $gateCandidate;
        }
    }
    $gateBusRow = ['bus_id' => $bus_id];
    $gateStmt = dispatcher_statement($conn, 'SELECT ' . implode(', ', $gateSelect) . ' FROM buses WHERE bus_id = ? LIMIT 1', 's', [$bus_id]);
    if ($gateStmt) {
        $loadedBusRow = $gateStmt->get_result()->fetch_assoc();
        if ($loadedBusRow) {
            $gateBusRow = $loadedBusRow;
        }
        $gateStmt->close();
    }
    $gateTripData = dispatcher_queue_trip_data($conn, [$bus_id], $trip_date, $from_terminal_id);
    $confirmedTerminalId = dispatcher_bus_confirmed_terminal_id($gateBusRow, $bus_id, $gateTripData);
    if ($confirmedTerminalId <= 0) {
        dispatcher_json_error(409, "Bus $bus_id has not entered a terminal queue for this operating date. Assign the bus to a terminal before dispatching.");
    }
    $driverTerminalId = dispatcher_driver_effective_terminal_id($conn, $driver_id, $gateBusRow, $bus_id, $gateTripData, $trip_date);
    if ($driverTerminalId <= 0 || $driverTerminalId !== $from_terminal_id) {
        dispatcher_json_error(409, "Driver $driver_id is not assigned to this terminal. Assign the driver to this terminal before dispatching.");
    }
}

/* Return dispatches must have passengers recorded: a trip that originates
   away from the starting terminal only leaves with an on-board count. */
$startTerminalId = dispatcher_queue_start_terminal($conn);
if ($startTerminalId > 0 && $from_terminal_id !== $startTerminalId && $passenger_count <= 0) {
    dispatcher_json_error(409, "Record the passengers on board before dispatching a return trip from this terminal.");
}

/* Queue context: re-validate the sequential readiness step and enforce the
   out-of-order reason requirement for planned queue dispatches. */
$queue_position = isset($data['queue_position']) && $data['queue_position'] !== '' ? (int)$data['queue_position'] : null;
$overtake_reason = trim((string)($data['overtake_reason'] ?? ''));
$override_note = trim((string)($data['override_note'] ?? ''));
if ($override_note !== '') {
    $override_note = function_exists('mb_substr') ? mb_substr($override_note, 0, 300) : substr($override_note, 0, 300);
}
$queue_entry = null;
$queue_head = null;
$earliest_ready = null;
try {
    $queue = dispatcher_queue_build($conn, $from_terminal_id, $trip_date);
    foreach ($queue['entries'] as $entry) {
        $entryPosition = (int)($entry['queue_position'] ?? 0);
        if ((string)$entry['bus_id'] === $bus_id) {
            $queue_entry = $entry;
            if ($queue_position === null && $entryPosition > 0) {
                $queue_position = $entryPosition;
            }
        }
        /* The queue head is the first bus in the official order that has not
           departed - the only bus the dispatcher may dispatch next. */
        if ($queue_head === null && (string)($entry['status'] ?? '') !== 'departed') {
            $queue_head = $entry;
        }
        if (($entry['status'] ?? '') === 'ready' && $entryPosition > 0) {
            if ($earliest_ready === null || $entryPosition < (int)$earliest_ready['queue_position']) {
                $earliest_ready = $entry;
            }
        }
    }
    if ($queue_entry !== null && ($queue_entry['status'] ?? '') !== 'ready') {
        $pendingReason = trim((string)($queue_entry['short_reason'] ?? ''));
        dispatcher_json_error(409, "This trip cannot be dispatched yet: " . ($pendingReason !== '' ? $pendingReason : 'complete the required pre-trip steps first'));
    }
} catch (\Throwable $e) {
    error_log("Dispatch queue validation failed: " . $e->getMessage());
}

/* The Admin-published order is official at the starting terminal only: there
   the dispatcher may only dispatch the queue head bus - no out-of-order
   dispatches and no overtake reasons. Other terminals run their own FIFO. */
$adminLock = dispatcher_queue_admin_lock($conn, $from_terminal_id, $trip_date);
$adminGate = dispatcher_admin_dispatch_gate(
    $adminLock !== null,
    $bus_id,
    $queue_head !== null ? (string)$queue_head['bus_id'] : '',
    $queue_head !== null ? (int)$queue_head['queue_position'] : 0
);
if ($adminGate !== null) {
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "code" => $adminGate['code'],
        "message" => $adminGate['message'],
        "data" => [
            "target_bus_id" => $bus_id,
            "target_position" => $queue_position,
            "next_bus_id" => $adminGate['next_bus_id'],
            "next_position" => $adminGate['next_position'],
        ],
    ]);
    $conn->close();
    exit;
}

$is_overtake = $adminLock === null
    && $earliest_ready !== null
    && $queue_position !== null
    && (int)$queue_position > (int)$earliest_ready['queue_position'];
if ($is_overtake && $overtake_reason === '') {
    http_response_code(409);
    echo json_encode([
        "success" => false,
        "message" => "Bus " . $earliest_ready['bus_id'] . " is ready at queue position #" . (int)$earliest_ready['queue_position'] . ". Please provide a reason to dispatch out of order.",
        "code" => "queue_overtake_required",
        "data" => [
            "target_bus_id" => $bus_id,
            "target_position" => $queue_position,
            "ready_bus_id" => $earliest_ready['bus_id'],
            "ready_position" => (int)$earliest_ready['queue_position'],
        ],
    ]);
    $conn->close();
    exit;
}

$conn->begin_transaction();
try {
    // Atomic duplicate prevention — lock the activity check rows.
    $busCheck = dispatcher_statement($conn, "SELECT id, departure_time FROM bus_trips WHERE bus_id = ? AND trip_date = ? AND status IN ('Scheduled','Departed','Interrupted') LIMIT 1 FOR UPDATE", "ss", [$bus_id, $trip_date]);
    if (!$busCheck) throw new Exception("duplicate check failed");
    $existingBusTrip = $busCheck->get_result()->fetch_assoc();
    $busCheck->close();
    if ($existingBusTrip) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "code" => "bus_already_dispatched",
            "message" => "Bus $bus_id was already dispatched today.",
            "data" => [
                "bus_id" => $bus_id,
                "departure_time" => $existingBusTrip['departure_time'] ?? null,
            ],
        ]);
        $conn->close();
        exit;
    }

    $driverCheck = dispatcher_statement($conn, "SELECT id FROM bus_trips WHERE driver_id = ? AND trip_date = ? AND status IN ('Scheduled','Departed','Interrupted') LIMIT 1 FOR UPDATE", "ss", [$driver_id, $trip_date]);
    if (!$driverCheck) throw new Exception("duplicate check failed");
    $driverBusy = $driverCheck->get_result()->fetch_assoc();
    $driverCheck->close();
    if ($driverBusy) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "code" => "driver_already_dispatched",
            "message" => "This driver is already on another trip today.",
            "data" => [
                "driver_id" => $driver_id,
            ],
        ]);
        $conn->close();
        exit;
    }

    // Auto-increment trip_number per bus/date
    $result = dispatcher_statement($conn, "SELECT COALESCE(MAX(trip_number), 0) + 1 AS next FROM bus_trips WHERE bus_id = ? AND trip_date = ?", "ss", [$bus_id, $trip_date]);
    if (!$result) throw new Exception("trip number failed");
    $trip_number = (int)$result->get_result()->fetch_assoc()['next'];
    $result->close();

    /* A driver resolved from the operating record becomes the formal day
       assignment at dispatch, so every later trip and the driver app reuse it. */
    if ((string)($assignedDriver['source'] ?? '') === 'trip_record') {
        if (dispatcher_ensure_bus_assignment($conn, $bus_id, $driver_id)) {
            dispatcher_audit_log(
                $conn,
                $dispatcher_user,
                'Driver Assigned',
                null,
                "Driver $driver_id assigned to Bus $bus_id from today's trip record at dispatch",
                [],
                [
                    'bus_id'          => $bus_id,
                    'driver_id'       => $driver_id,
                    'driver_name'     => (string)($assignedDriver['name'] ?? $driver_id),
                    'assignment_date' => $trip_date,
                    'assigned_by'     => (string)($dispatcher_user['name'] ?? ''),
                    'assigned_at'     => date('Y-m-d H:i:s'),
                    'source'          => 'trip_record',
                ]
            );
        }
    }

    $dispatcher_id = $dispatcher_user['id'];
    $departure_time = date('H:i:s');

    /* Dispatch records the starting odometer only. Fuel liters and an editable
       KPL are no longer collected, so no fuel quantity, cost, or consumption
       is derived from the dispatch form (bus_trips.fuel_cost stays 0). */
    $price_per_liter = dispatcher_get_current_diesel_price($conn);
    $fuel_cost = 0.0;

    $ins = dispatcher_statement(
        $conn,
        "INSERT INTO bus_trips (bus_id, driver_id, route_name, from_terminal_id, to_terminal_id, trip_date, trip_number, departure_time, total_passengers, status, dispatcher_id, fuel_cost)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Departed', ?, ?)",
        "sssiisisisd",
        [$bus_id, $driver_id, $route_name, $from_terminal_id, $to_terminal_id, $trip_date, $trip_number, $departure_time, $passenger_count, $dispatcher_id, $fuel_cost]
    );
    if (!$ins) throw new Exception("insert failed");
    $dispatch_id = $conn->insert_id;
    $ins->close();

    /* Actual dispatch order derived from the recorded dispatch time. */
    $dispatch_order = dispatcher_queue_dispatch_order($conn, $dispatch_id, $from_terminal_id, $trip_date);

    /* Linked fuel_logs record: the starting odometer is recorded now, the
       ending odometer only when the receiving terminal confirms arrival. No
       fuel quantity and no KPL measurement is fabricated at dispatch. */
    $fuel_log_id = null;
    $newFuelLogId = dispatcher_insert_dispatch_fuel_log(
        $conn,
        $bus_id,
        $driver_id,
        $dispatch_id,
        $starting_odo,
        $price_per_liter
    );
    if ($newFuelLogId !== null) {
        $fuel_log_id = $newFuelLogId;
    }

    /* Accountability trail (same transaction as the dispatch). */
    dispatcher_audit_log(
        $conn,
        $dispatcher_user,
        'Dispatch Created',
        $dispatch_id,
        "Trip #$dispatch_id dispatched on route $route_name (Bus $bus_id, Driver $driver_id)",
        [],
        [
            'trip_id'          => $dispatch_id,
            'trip_number'      => $trip_number,
            'bus_id'           => $bus_id,
            'driver_id'        => $driver_id,
            'route_name'       => $route_name,
            'from_terminal_id' => $from_terminal_id,
            'to_terminal_id'   => $to_terminal_id,
            'trip_date'        => $trip_date,
            'departure_time'   => $departure_time,
            'queue_position'   => $queue_position,
            'dispatch_order'   => $dispatch_order,
            'override_note'    => $override_note !== '' ? $override_note : null,
        ]
    );
    dispatcher_audit_log(
        $conn,
        $dispatcher_user,
        'Passenger Count Recorded',
        $dispatch_id,
        "Dispatcher recorded $passenger_count passenger(s) at dispatch",
        [],
        ['dispatcher_count' => (int)$passenger_count, 'trip_id' => $dispatch_id]
    );
    if ($fuel_log_id !== null) {
        dispatcher_audit_log(
            $conn,
            $dispatcher_user,
            'Fuel Record Saved',
            $dispatch_id,
            'Trip odometer record created at dispatch',
            [],
            [
                'fuel_log_id'    => $fuel_log_id,
                'starting_odo'   => $starting_odo,
                'liters'         => 0.0,
                'price_per_liter'=> $price_per_liter,
                'cost'           => 0.0,
            ]
        );
    }

    if ($is_overtake) {
        dispatcher_audit_log(
            $conn,
            $dispatcher_user,
            'Queue Overtake',
            $dispatch_id,
            "Trip #$dispatch_id dispatched from queue position #" . (int)$queue_position
                . " while Bus " . $earliest_ready['bus_id'] . " (position #" . (int)$earliest_ready['queue_position'] . ") was ready: " . $overtake_reason,
            [],
            [
                'trip_id'        => $dispatch_id,
                'bus_id'         => $bus_id,
                'queue_position' => (int)$queue_position,
                'ready_bus_id'   => $earliest_ready['bus_id'],
                'ready_position' => (int)$earliest_ready['queue_position'],
                'reason'         => $overtake_reason,
            ]
        );
    }

    $conn->commit();

    try {
        $notif = new \App\Models\Notification();
        $notif->createForAdminRoles(
            'New Ongoing Trip',
            "New trip dispatched on route $route_name (Bus $bus_id)",
            'info',
            'trip',
            (int)$dispatch_id
        );
    } catch (\Throwable $e) {
        error_log("Trip notification error: " . $e->getMessage());
    }

    echo json_encode([
        "success" => true,
        "message" => "Dispatched",
        "trip_no" => $dispatch_id,
        "dispatch_id" => $dispatch_id,
        "trip_number" => $trip_number,
        "dispatch_time" => $departure_time,
        "fuel_log_id" => $fuel_log_id,
        "starting_odo" => $starting_odo,
        "kpl" => null,
        "fuel_liters" => 0.0,
        "fuel_cost" => $fuel_cost,
        "price_per_liter" => $price_per_liter,
        "dispatch_order" => $dispatch_order,
        "queue_position" => $queue_position,
        "original_position" => $queue_position,
        "overtake_reason" => $is_overtake ? $overtake_reason : null
    ]);
} catch (RuntimeException $e) {
    $conn->rollback();
    dispatcher_json_error(409, $e->getMessage());
} catch (Exception $e) {
    $conn->rollback();
    error_log("Dispatch create error: " . $e->getMessage());
    dispatcher_json_error(500, "Dispatch failed. Please try again.");
}
$conn->close();

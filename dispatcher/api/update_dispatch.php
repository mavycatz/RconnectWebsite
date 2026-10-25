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
dispatcher_ensure_arrival_schema($conn);

$data = json_decode(file_get_contents("php://input"), true);
dispatcher_assert_identity($data, $dispatcher_user);
$dispatch_id = (int)($data['dispatch_id'] ?? 0);
$action = $data['action'] ?? '';
$status = trim($data['status'] ?? '');

if (!$dispatch_id) {
    dispatcher_json_error(400, "Missing dispatch_id");
}

$stmt = dispatcher_statement($conn, "SELECT * FROM bus_trips WHERE id = ? LIMIT 1", "i", [$dispatch_id]);
if (!$stmt) dispatcher_json_error(500, "Failed to load dispatch");
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) {
    dispatcher_json_error(404, "Dispatch not found");
}

$userTerminal = (int)($_SESSION['terminal_id'] ?? 0);
$fromTerminal = (int)($current['from_terminal_id'] ?? 0);
$destTerminal = (int)($current['to_terminal_id'] ?? 0);

// ── Arrival reporting — Departed → arrived awaiting confirmation, destination terminal only ──
if ($action === 'report_arrival') {
    if ($current['status'] !== 'Departed') {
        dispatcher_json_error(403, "Only on-route trips can report an arrival");
    }
    if ($userTerminal > 0 && $destTerminal > 0 && $userTerminal !== $destTerminal) {
        dispatcher_json_error(403, "Only the destination terminal can report this arrival");
    }
    $source = trim((string)($data['source'] ?? 'staff'));
    if (!in_array($source, ['staff', 'driver_gps', 'driver_app_manual', 'driver_app_timeout'], true)) {
        $source = 'staff';
    }
    $reportedAt = date('Y-m-d H:i:s');
    $recorded = dispatcher_report_trip_arrival(
        $conn,
        $dispatch_id,
        $source,
        (string)($dispatcher_user['id'] ?? ''),
        $reportedAt
    );
    if ($recorded) {
        dispatcher_audit_log(
            $conn,
            $dispatcher_user,
            'Arrival Reported',
            $dispatch_id,
            "Trip #$dispatch_id reported arrived (source: $source); awaiting terminal confirmation",
            [],
            [
                'trip_id' => $dispatch_id,
                'arrival_reported_at' => $reportedAt,
                'arrival_report_source' => $source,
            ]
        );
    }
    echo json_encode([
        "success" => true,
        "message" => $recorded ? "Arrival reported" : "Arrival already reported",
        "already_reported" => !$recorded,
        "arrival_reported_at" => $recorded ? $reportedAt : ($current['arrival_reported_at'] ?? null),
        "arrival_report_source" => $recorded ? $source : ($current['arrival_report_source'] ?? null),
    ]);
    $conn->close();
    exit;
}

// ── Arrival recording — Departed → Completed, only at the destination terminal ──
if ($action === 'arrive' || ($current['status'] === 'Departed' && $status === 'Completed')) {
    if ($current['status'] === 'Completed') {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "code" => "already_confirmed",
            "message" => "This arrival is already confirmed.",
        ]);
        $conn->close();
        exit;
    }
    if ($current['status'] !== 'Departed') {
        dispatcher_json_error(403, "Only on-route trips can be marked as arrived");
    }
    if ($userTerminal > 0 && $destTerminal > 0 && $userTerminal !== $destTerminal) {
        dispatcher_json_error(403, "Only the destination terminal can record arrival");
    }
    /* Arrival information is required: reject when it is missing or malformed. */
    $arrival_time = trim((string)($data['arrival_time'] ?? ''));
    if ($arrival_time === '' || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $arrival_time) || strtotime($arrival_time) === false) {
        dispatcher_json_error(400, "Arrival time is required (HH:MM)");
    }

    /* The actual ending odometer is required to confirm an arrival. */
    $ending_odo = (isset($data['ending_odo']) && $data['ending_odo'] !== '' && is_numeric($data['ending_odo']))
        ? (float)$data['ending_odo']
        : null;
    if ($ending_odo === null || $ending_odo <= 0) {
        http_response_code(422);
        echo json_encode([
            "success" => false,
            "code" => "ending_odo_required",
            "message" => "Ending odometer (km) is required to confirm the arrival.",
        ]);
        $conn->close();
        exit;
    }

    // Count actual passengers from passenger_trips
    $bus_id = $current['bus_id'];
    $trip_date = $current['trip_date'];
    $paxResult = dispatcher_statement($conn, "SELECT COUNT(*) as c FROM passenger_trips WHERE bus_no = ? AND DATE(boarding_time) = ?", "ss", [$bus_id, $trip_date]);
    $total_pax = 0;
    if ($paxResult) {
        $total_pax = (int)$paxResult->get_result()->fetch_assoc()['c'];
        $paxResult->close();
    }

    /* The dispatcher's recorded count is never silently replaced: the operational
       total only reconciles upward and the change is preserved in the audit trail. */
    $storedCount = (int)($current['total_passengers'] ?? 0);
    $newTotal = max($storedCount, $total_pax);

    // Calculate duration in minutes
    $departure = $current['departure_time'];
    $duration = 0;
    if ($departure && $arrival_time) {
        $t1 = strtotime($departure);
        $t2 = strtotime($arrival_time);
        if ($t2 > $t1) $duration = round(($t2 - $t1) / 60);
    }

    /* Strict confirmation: validates the ending odometer against the trip's
       recorded starting odometer, records the ending odometer and distance on
       the linked fuel record (an estimate from the historical KPL only), and
       completes the trip with the confirmation stamps. No record changes when
       validation fails. */
    $confirmation = dispatcher_confirm_trip_arrival(
        $conn,
        $current,
        $ending_odo,
        $arrival_time,
        $newTotal,
        $duration,
        (string)($dispatcher_user['id'] ?? ''),
        date('Y-m-d H:i:s')
    );
    if (empty($confirmation['success'])) {
        $code = (string)($confirmation['code'] ?? 'confirm_failed');
        $message = (string)($confirmation['error'] ?? 'The arrival could not be confirmed.');
        if ($code === 'ending_odo_below_starting' || $code === 'ending_odo_required') {
            http_response_code(422);
        } elseif ($code === 'already_confirmed') {
            http_response_code(409);
        } else {
            http_response_code(500);
            error_log("Failed to record arrival for trip $dispatch_id: $message");
        }
        echo json_encode(["success" => false, "code" => $code, "message" => $message]);
        $conn->close();
        exit;
    }
    $fuelData = $confirmation['fuel_data'] ?? null;

    /* Arrival terminal for the terminal and queue sync: the trip's recorded
       destination, or - when the trip was created without one - the terminal
       the driver's latest location is within the arrival radius (3 km) of,
       falling back to the confirming dispatcher's terminal. A missing arrival
       terminal is written back to the trip so the receiving terminal's lists
       and queues can see it. */
    $arrivalTerminalId = $destTerminal;
    $arrivalTerminalSource = $destTerminal > 0 ? 'trip_destination' : '';
    if ($arrivalTerminalId <= 0) {
        $arrivalTerminalId = dispatcher_arrival_terminal_from_location($conn, (string)($current['driver_id'] ?? ''));
        $arrivalTerminalSource = $arrivalTerminalId > 0 ? 'driver_location' : '';
    }
    if ($arrivalTerminalId <= 0 && $userTerminal > 0) {
        $arrivalTerminalId = $userTerminal;
        $arrivalTerminalSource = 'confirming_terminal';
    }
    if ($destTerminal <= 0 && $arrivalTerminalId > 0) {
        dispatcher_statement(
            $conn,
            "UPDATE bus_trips SET to_terminal_id = ? WHERE id = ? AND (to_terminal_id IS NULL OR to_terminal_id = 0)",
            "ii",
            [$arrivalTerminalId, $dispatch_id]
        );
    }

    /* The bus has physically arrived: move its recorded terminal to the arrival
       terminal (and set the opposite as its next destination) so the next
       prepare/dispatch (even on a later date) no longer sees a stale origin. */
    dispatcher_sync_bus_after_arrival($conn, (string)$bus_id, $arrivalTerminalId);

    /* The bus joins the receiving terminal's queue for the operating date and
       the operating driver's day assignment moves with it. Automatic
       membership sync: positions and publication stamps are never rewritten. */
    dispatcher_sync_queue_after_arrival($conn, (string)$bus_id, (string)$trip_date, $arrivalTerminalId, (string)($current['driver_id'] ?? ''));

    if ($newTotal !== $storedCount) {
        dispatcher_audit_log(
            $conn,
            $dispatcher_user,
            'Record Edited',
            $dispatch_id,
            "Arrival reconciliation changed total passengers from $storedCount to $newTotal",
            ['total_passengers' => $storedCount],
            [
                'total_passengers' => $newTotal,
                'boarded_count'    => $total_pax,
                'reason'           => 'Arrival reconciliation',
            ]
        );
    }

    dispatcher_audit_log(
        $conn,
        $dispatcher_user,
        'Trip Completed',
        $dispatch_id,
        "Trip #$dispatch_id completed with $newTotal passenger(s) in {$duration}m",
        [],
        [
            'trip_id'           => $dispatch_id,
            'arrival_time'      => $arrival_time,
            'duration_minutes'  => $duration,
            'total_passengers'  => $newTotal,
            'dispatcher_count'  => $storedCount,
            'boarded_count'     => $total_pax,
            'arrival_terminal_id' => $arrivalTerminalId,
            'arrival_terminal_source' => $arrivalTerminalSource,
        ]
    );

    /* Cross-validate the dispatcher, driver, and inspector counts now that the
       trip is terminal. Existing records are read only; nothing is corrected. */
    try {
        require_once __DIR__ . '/../../admin/models/Inspectors/DispatcherMonitoringModel.php';
        $pdo = \App\Core\Database::tryGet();
        if ($pdo !== null) {
            $monitor = new DispatcherMonitoringModel($pdo);
            $monitor->evaluateTripCounts((int)$dispatch_id, $dispatcher_user);
        }
    } catch (\Throwable $e) {
        error_log('Dispatcher count evaluation failed: ' . $e->getMessage());
    }

    try {
        if (class_exists('\App\Core\Database') && \App\Core\Database::tryGet() !== null) {
            $notif = new \App\Models\Notification();
            $notif->createForAdminRoles(
                'Trip Completed',
                "Trip #$dispatch_id completed with $total_pax passenger(s) in {$duration}m",
                'success',
                'trip',
                (int)$dispatch_id
            );
        }
    } catch (\Throwable $e) {
        error_log("Trip notification error: " . $e->getMessage());
    }

    $resp = [
        "success" => true,
        "message" => "Arrival recorded",
        "total_passengers" => $total_pax,
        "duration_minutes" => $duration,
        "arrival_time" => $arrival_time
    ];
    if ($fuelData) $resp["fuel_data"] = $fuelData;
    echo json_encode($resp);
    $conn->close();
    exit;
}

// ── Cancellation — Scheduled → Cancelled, only at the origin terminal ──
if ($action === 'cancel') {
    if ($current['status'] !== 'Scheduled') {
        dispatcher_json_error(403, "Only pending trips can be cancelled");
    }
    if ($userTerminal > 0 && $fromTerminal > 0 && $userTerminal !== $fromTerminal) {
        dispatcher_json_error(403, "Only the origin terminal can cancel this trip");
    }
    $upd = dispatcher_statement($conn, "UPDATE bus_trips SET status = 'Cancelled' WHERE id = ? AND status = 'Scheduled'", "i", [$dispatch_id]);
    if (!$upd) dispatcher_json_error(500, "Failed to cancel trip");
    dispatcher_audit_log(
        $conn,
        $dispatcher_user,
        'Trip Cancelled',
        $dispatch_id,
        "Trip #$dispatch_id cancelled",
        ['status' => $current['status']],
        ['status' => 'Cancelled']
    );
    echo json_encode(["success" => true, "message" => "Cancelled"]);
    $conn->close();
    exit;
}

// ── Depart — Scheduled → Departed, only at the origin terminal ──
if ($action === 'depart' || $status === 'Departed') {
    if ($current['status'] !== 'Scheduled') {
        dispatcher_json_error(403, "Only pending trips can be departed");
    }
    if ($userTerminal > 0 && $fromTerminal > 0 && $userTerminal !== $fromTerminal) {
        dispatcher_json_error(403, "Only the origin terminal can depart this trip");
    }
    $departure_time = trim($data['departure_time'] ?? date('H:i:s'));
    $upd = dispatcher_statement($conn, "UPDATE bus_trips SET status = 'Departed', departure_time = COALESCE(departure_time, ?) WHERE id = ? AND status = 'Scheduled'", "si", [$departure_time, $dispatch_id]);
    if (!$upd) dispatcher_json_error(500, "Failed to update trip");
    dispatcher_audit_log(
        $conn,
        $dispatcher_user,
        'Trip Departed',
        $dispatch_id,
        "Trip #$dispatch_id departed",
        ['status' => 'Scheduled'],
        ['status' => 'Departed', 'departure_time' => $departure_time]
    );
    echo json_encode(["success" => true, "message" => "Departed"]);
    $conn->close();
    exit;
}

dispatcher_json_error(400, "Invalid action");

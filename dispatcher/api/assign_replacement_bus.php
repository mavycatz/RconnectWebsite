<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();
dispatcher_require_csrf();

$db = new Database();
$conn = $db->connect();

dispatcher_ensure_bpr_schema($conn);

$input = array_merge($_GET, $_POST);
$json = file_get_contents("php://input");
if ($json && !empty($json)) {
    $jsonData = json_decode($json, true);
    if (is_array($jsonData)) {
        $input = array_merge($input, $jsonData);
    }
}

$report_id = isset($input['report_id']) ? (int)$input['report_id'] : 0;
$bus_id = trim($input['bus_id'] ?? '');
$driver_id = trim($input['driver_id'] ?? '');

if ($report_id <= 0) {
    dispatcher_json_error(400, "report_id is required");
}
if ($bus_id === '') {
    dispatcher_json_error(400, "bus_id is required");
}

// Load report
$checkStmt = dispatcher_statement($conn, "SELECT id, status, trip_id, bus_id, replacement_bus_id FROM bus_problem_reports WHERE id = ?", "i", [$report_id]);
if (!$checkStmt) {
    dispatcher_json_error(500, "Failed to retrieve report");
}
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows == 0) {
    $checkStmt->close();
    dispatcher_json_error(404, "Report not found");
}
$report = $checkResult->fetch_assoc();
$checkStmt->close();

if ($report['status'] !== 'acknowledged') {
    dispatcher_json_error(400, "Report must be in 'acknowledged' status before assigning a replacement bus");
}
if ($bus_id === (string)$report['bus_id']) {
    dispatcher_json_error(400, "The affected bus cannot be assigned as its own replacement");
}

// Bus must exist and not be archived
$busStmt = dispatcher_statement($conn, "SELECT bus_id, status FROM buses WHERE bus_id = ?", "s", [$bus_id]);
if (!$busStmt) {
    dispatcher_json_error(500, "Failed to retrieve bus");
}
$busResult = $busStmt->get_result();
if ($busResult->num_rows == 0) {
    $busStmt->close();
    dispatcher_json_error(404, "Replacement bus not found");
}
$bus = $busResult->fetch_assoc();
$busStmt->close();
if (!empty($bus['status']) && $bus['status'] !== 'Active') {
    dispatcher_json_error(400, "Replacement bus is not active");
}

// Bus must not be occupied by another trip today
$busyStmt = dispatcher_statement(
    $conn,
    "SELECT id FROM bus_trips WHERE bus_id = ? AND trip_date = CURDATE() AND status IN ('Scheduled','Departed','Interrupted') LIMIT 1",
    "s",
    [$bus_id]
);
if (!$busyStmt) {
    dispatcher_json_error(500, "Failed to check bus availability");
}
if ($busyStmt->get_result()->num_rows > 0) {
    $busyStmt->close();
    dispatcher_json_error(400, "Replacement bus is already assigned to an active trip today");
}
$busyStmt->close();

// Affected trip must exist and not be in a terminal state
$trip_id = (int)$report['trip_id'];
$tripStmt = dispatcher_statement($conn, "SELECT id, status FROM bus_trips WHERE id = ?", "i", [$trip_id]);
if (!$tripStmt) {
    dispatcher_json_error(500, "Failed to retrieve affected trip");
}
$tripResult = $tripStmt->get_result();
if ($tripResult->num_rows == 0) {
    $tripStmt->close();
    dispatcher_json_error(404, "Associated trip not found");
}
$trip = $tripResult->fetch_assoc();
$tripStmt->close();
if (in_array((string)$trip['status'], ['Completed', 'Cancelled'], true)) {
    dispatcher_json_error(400, "Associated trip is already finished; no replacement bus is needed");
}

// Resolve replacement driver: explicit param, else the bus's assigned driver (driver_bus_assignment)
if ($driver_id === '') {
    $assignmentMap = dispatcher_queue_driver_assignment_map($conn, [$bus_id]);
    $driver_id = trim((string)($assignmentMap[$bus_id] ?? ''));
    if ($driver_id === '') {
        $legacyStmt = dispatcher_statement($conn, "SELECT driver FROM buses WHERE bus_id = ?", "s", [$bus_id]);
        if ($legacyStmt) {
            $legacyRow = $legacyStmt->get_result()->fetch_assoc();
            $legacyStmt->close();
            $driver_id = trim((string)($legacyRow['driver'] ?? ''));
        }
    }
}

// Validate an explicitly chosen replacement driver exists
if ($driver_id !== '') {
    $dStmt = dispatcher_statement($conn, "SELECT driver_id FROM drivers WHERE driver_id = ?", "s", [$driver_id]);
    if (!$dStmt) {
        dispatcher_json_error(500, "Failed to validate replacement driver");
    }
    if ($dStmt->get_result()->num_rows === 0) {
        $dStmt->close();
        dispatcher_json_error(400, "Replacement driver does not exist");
    }
    $dStmt->close();
}

$assigned_by = (string)($dispatcher_user['id'] ?? ($_SESSION['inspector_id'] ?? ''));

// Persist assignment on the report
$assignStmt = dispatcher_statement(
    $conn,
    "UPDATE bus_problem_reports
     SET replacement_bus_id = ?, replacement_driver_id = ?, replacement_assigned_by = ?, replacement_assigned_at = NOW()
     WHERE id = ?",
    "sssi",
    [$bus_id, $driver_id, $assigned_by, $report_id]
);
if (!$assignStmt) {
    dispatcher_json_error(500, "Failed to assign replacement bus: " . ($dispatcher_last_sql_error ?? "Unknown error"));
}
$assignStmt->close();

// Mark the affected trip as interrupted and requiring replacement
$tripUpdateStmt = dispatcher_statement(
    $conn,
    "UPDATE bus_trips SET status = 'Interrupted', replacement_required = 1
     WHERE id = ? AND status NOT IN ('Completed','Cancelled')",
    "i",
    [$trip_id]
);
if (!$tripUpdateStmt) {
    dispatcher_json_error(500, "Replacement assigned, but failed to update the affected trip");
}
$tripUpdateStmt->close();
    // Auto-transfer active passengers to replacement bus
    try {
        $tripForNotif = null;
        $tn = dispatcher_statement($conn, "SELECT route_name, trip_number, bus_id FROM bus_trips WHERE id = ?", "i", array($trip_id));
        if ($tn) {
            $tripForNotif = $tn->get_result()->fetch_assoc();
            $tn->close();
        }

        $passStmt = dispatcher_statement($conn, "SELECT id, passenger_id, boarding_point, destination FROM passenger_trips WHERE trip_no = ? AND dropoff_time IS NULL AND archived_status = \"no\"", "s", array((string)$trip_id));
        if ($passStmt) {
            $passRes = $passStmt->get_result();
            $passengers = array();
            while ($pr = $passRes->fetch_assoc()) {
                $passengers[] = $pr;
            }
            $passRes->free();
            $passStmt->close();

            if (!empty($passengers)) {
                $updatePass = dispatcher_statement($conn, "UPDATE passenger_trips SET bus_no = ?, driver_id = ? WHERE trip_no = ? AND dropoff_time IS NULL AND archived_status = \"no\"", "sss", array($bus_id, $driver_id !== "" ? $driver_id : null, (string)$trip_id));
                if ($updatePass) {
                    $updatePass->close();
                }

                try {
                    require_once __DIR__ . "/../../app/Models/Notification.php";
                    $notif = new \App\Models\Notification();
                    $routeName = isset($tripForNotif["route_name"]) ? $tripForNotif["route_name"] : "";
                    foreach ($passengers as $p) {
                        $passengerId = (int)$p["passenger_id"];
                        $title = "Bus Replacement";
                        $body = "Your current bus has been replaced. You are now continuing your trip on Bus-" . $bus_id . ".";
                        $dest = isset($p["destination"]) && $p["destination"] ? $p["destination"] : ($routeName ?: "N/A");
                        $board = isset($p["boarding_point"]) && $p["boarding_point"] ? $p["boarding_point"] : "Current stop";
                        $desc = $body . " Route: " . ($routeName ?: "N/A") . ", Destination: " . $dest . ", Transfer location: " . $board;
                        if (method_exists($notif, "create")) {
                            try {
                                $notif->create($passengerId, $title, $desc, "info", "trip", $trip_id);
                            } catch (\Throwable $e) {
                                error_log("Notification create error: " . $e->getMessage());
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("Passenger transfer notification error: " . $e->getMessage());
                }
            }
        }
    } catch (\Throwable $e) {
        error_log("Passenger transfer error: " . $e->getMessage());
    }

// Existing Admin notification system: replacement assigned and passengers
// transferred. The transfer is performed here atomically with the assignment, so
// the two stages are reported as two notifications (distinct titles -> no dedupe
// collision). Best effort — a notification must never fail the assignment.
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . "/app/Core/Database.php";
    require_once $_SERVER['DOCUMENT_ROOT'] . "/app/Models/Notification.php";

    $ctxStmt = dispatcher_statement($conn, "
        SELECT bpr.problem_type, bpr.trip_no, bpr.driver_id, d.full_name AS driver_name
        FROM bus_problem_reports bpr
        LEFT JOIN drivers d ON d.driver_id = bpr.driver_id
        WHERE bpr.id = ?
    ", "i", [$report_id]);
    $ctx = [];
    if ($ctxStmt) {
        $ctx = $ctxStmt->get_result()->fetch_assoc() ?: [];
        $ctxStmt->close();
    }

    $affectedDriver = (string)($ctx['driver_name'] ?? '');
    if ($affectedDriver === '') {
        $affectedDriver = (string)($ctx['driver_id'] ?? '');
    }
    $transferred = isset($passengers) && is_array($passengers) ? count($passengers) : 0;
    $replacementDriver = $driver_id !== '' ? $driver_id : 'unassigned';

    $notifier = new \App\Models\Notification();

    $notifier->createForAdminRoles(
        'Replacement Bus Assigned',
        'BUS ' . (string)$report['bus_id'] . ' · ' . (string)($ctx['problem_type'] ?? '')
            . ' · TRIP #' . (string)($ctx['trip_no'] ?? $trip_id)
            . ' · Driver ' . $affectedDriver
            . ' — replacement BUS ' . $bus_id . ' / Driver ' . $replacementDriver
            . ' assigned by ' . ($assigned_by !== '' ? $assigned_by : 'dispatcher') . '.',
        'warning',
        'bus_problem',
        $report_id
    );

    if ($transferred > 0) {
        $notifier->createForAdminRoles(
            'Passengers Transferred',
            $transferred . ' passenger(s) transferred from BUS ' . (string)$report['bus_id']
                . ' to replacement BUS ' . $bus_id
                . ' (TRIP #' . (string)($ctx['trip_no'] ?? $trip_id) . ').',
            'info',
            'bus_problem',
            $report_id
        );
    }
} catch (\Throwable $e) {
    error_log('[bus_problem] admin notification skipped (replacement assigned): ' . $e->getMessage());
}

$conn->close();

echo json_encode([
    "success" => true,
    "message" => "Replacement bus assigned successfully",
    "report_id" => $report_id,
    "replacement_bus_id" => $bus_id,
    "replacement_driver_id" => $driver_id,
    "trip_id" => $trip_id,
    "trip_status" => "Interrupted"
]);

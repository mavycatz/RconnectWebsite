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
$driver_id = trim((string)($data['driver_id'] ?? ''));
$action = trim((string)($data['action'] ?? 'assign'));
$terminal_id = (int)($_SESSION['terminal_id'] ?? 0);

if ($terminal_id <= 0) {
    dispatcher_json_error(400, "No terminal selected");
}
if ($bus_id === '') {
    dispatcher_json_error(400, "Missing bus_id");
}
if ($driver_id === '') {
    dispatcher_json_error(400, "Missing driver_id");
}
if (!in_array($action, ['assign', 'change'], true)) {
    $action = 'assign';
}

/* The Admin owns driver assignment while its published master order is
   official; the master queue spans both terminals. */
if (dispatcher_queue_admin_lock($conn, $terminal_id, date('Y-m-d')) !== null) {
    dispatcher_json_error(409, "Drivers are assigned by the Admin for this operating date.");
}

/* The bus must be an Active bus currently in this terminal's queue. */
if (!dispatcher_table_exists($conn, 'buses')) {
    dispatcher_json_error(500, "Queue unavailable");
}
$busCols = dispatcher_table_columns($conn, 'buses');
$where = "bus_id = ?";
$types = "s";
$params = [$bus_id];
if (isset($busCols['status'])) {
    $where .= " AND status = 'Active'";
}
$busStmt = dispatcher_statement($conn, "SELECT bus_id FROM buses WHERE $where LIMIT 1", $types, $params);
if (!$busStmt) {
    dispatcher_json_error(500, "Queue unavailable");
}
$busFound = $busStmt->get_result()->num_rows > 0;
$busStmt->close();
if (!$busFound || !dispatcher_bus_in_terminal_queue($conn, $bus_id, $terminal_id, date('Y-m-d'))) {
    dispatcher_json_error(403, "This bus is not part of your terminal queue");
}

/* The driver must exist and be Active. */
if (!dispatcher_valid_driver($conn, $driver_id)) {
    dispatcher_json_error(400, dispatcher_t('assign.driver_unavailable', 'The selected driver is not available.'));
}

$current = dispatcher_queue_bus_driver($conn, $bus_id);
$hasCurrent = $current !== null && !empty($current['driver_id']);

if ($action === 'assign') {
    if ($hasCurrent) {
        dispatcher_json_error(409, dispatcher_t('assign.already_assigned', 'This bus already has a driver. Use Change Bus Assignment.'));
    }
} else {
    if (!$hasCurrent) {
        dispatcher_json_error(409, dispatcher_t('assign.no_assignment', 'This bus has no driver assignment to change.'));
    }
    if ((string)$current['driver_id'] === $driver_id) {
        dispatcher_json_error(409, dispatcher_t('assign.same_driver', 'This driver is already assigned to this bus.'));
    }
}

/* The driver may not already be assigned to another bus (day assignment). */
$availableIds = array_map(function ($row) {
    return (string)($row['driver_id'] ?? '');
}, dispatcher_available_drivers($conn));
if (!in_array($driver_id, $availableIds, true)) {
    dispatcher_json_error(409, dispatcher_t('assign.driver_busy', 'This driver is already assigned to another bus today.'));
}

$driverName = $driver_id;
$nameStmt = dispatcher_statement($conn, "SELECT full_name FROM drivers WHERE driver_id = ? LIMIT 1", "s", [$driver_id]);
if ($nameStmt) {
    $nameRow = $nameStmt->get_result()->fetch_assoc();
    $nameStmt->close();
    if ($nameRow && trim((string)($nameRow['full_name'] ?? '')) !== '') {
        $driverName = trim((string)$nameRow['full_name']);
    }
}

$conn->begin_transaction();
try {
    if (!dispatcher_table_exists($conn, 'driver_bus_assignment')) {
        throw new Exception('assignment table missing');
    }
    $assignCols = dispatcher_table_columns($conn, 'driver_bus_assignment');
    if (!isset($assignCols['bus_id'], $assignCols['driver_id'])) {
        throw new Exception('assignment schema missing');
    }

    $existingStmt = dispatcher_statement($conn, "SELECT driver_id FROM driver_bus_assignment WHERE bus_id = ? LIMIT 1", "s", [$bus_id]);
    $hasRow = $existingStmt !== false && $existingStmt->get_result()->num_rows > 0;
    if ($existingStmt) {
        $existingStmt->close();
    }

    if ($hasRow) {
        $upd = dispatcher_statement($conn, "UPDATE driver_bus_assignment SET driver_id = ? WHERE bus_id = ?", "ss", [$driver_id, $bus_id]);
        if (!$upd) {
            throw new Exception('update failed');
        }
        $upd->close();
    } else {
        $ins = dispatcher_statement($conn, "INSERT INTO driver_bus_assignment (driver_id, bus_id) VALUES (?, ?)", "ss", [$driver_id, $bus_id]);
        if (!$ins) {
            throw new Exception('insert failed');
        }
        $ins->close();
    }

    $isChange = $action === 'change' && $hasCurrent;
    $previousRef = $hasCurrent ? (string)$current['driver_id'] : '';
    $previousName = $hasCurrent ? (string)($current['name'] ?? '') : '';
    dispatcher_audit_log(
        $conn,
        $dispatcher_user,
        $isChange ? 'Bus Assignment Changed' : 'Driver Assigned',
        null,
        $isChange
            ? "Bus $bus_id driver changed from " . ($previousName !== '' ? $previousName : $previousRef) . " to $driverName"
            : "Driver $driverName assigned to Bus $bus_id for the day",
        $isChange ? ['driver_id' => $previousRef, 'driver_name' => $previousName] : [],
        [
            'bus_id'          => $bus_id,
            'driver_id'       => $driver_id,
            'driver_name'     => $driverName,
            'previous_driver' => $isChange ? ($previousName !== '' ? $previousName : $previousRef) : null,
            'assignment_date' => date('Y-m-d'),
            'assigned_by'     => (string)($dispatcher_user['name'] ?? ''),
            'assigned_at'     => date('Y-m-d H:i:s'),
        ]
    );

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => $isChange ? "Bus assignment changed" : "Driver assigned",
        "data" => [
            "bus_id" => $bus_id,
            "driver_id" => $driver_id,
            "driver_name" => $driverName,
            "previous_driver_id" => $isChange ? $previousRef : null,
            "previous_driver_name" => $isChange ? $previousName : null,
            "action" => $isChange ? 'change' : 'assign',
            "assigned_at" => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Assign driver error: " . $e->getMessage());
    dispatcher_json_error(500, "The driver assignment could not be saved. Please try again.");
}
$conn->close();

<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$bus_id = trim($_GET['bus_id'] ?? '');
$status = trim($_GET['status'] ?? '');
$dispatcher_id = trim($_GET['dispatcher_id'] ?? $dispatcher_user['id'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, (int)($_GET['limit'] ?? 20));
$offset = ($page - 1) * $limit;

$where = [];
$types = "";
$params = [];
if (!empty($date_from)) { $where[] = "bt.trip_date >= ?"; $types .= "s"; $params[] = $date_from; }
if (!empty($date_to)) { $where[] = "bt.trip_date <= ?"; $types .= "s"; $params[] = $date_to; }
if (!empty($bus_id)) { $where[] = "bt.bus_id LIKE ?"; $types .= "s"; $params[] = "%$bus_id%"; }
if (!empty($status)) { $where[] = "bt.status = ?"; $types .= "s"; $params[] = $status; }
if (!empty($dispatcher_id)) { $where[] = "bt.dispatcher_id = ?"; $types .= "s"; $params[] = $dispatcher_id; }
$whereClause = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$total = 0;
$stmt = dispatcher_statement($conn, "SELECT COUNT(*) as c FROM bus_trips bt $whereClause", $types, $params);
if ($stmt) {
    $total = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}

dispatcher_ensure_fuel_schema($conn);
$fuelCols = dispatcher_fuel_logs_columns($conn);
$fuelIdCol = dispatcher_fuel_id_column($conn);
$hasTripId = isset($fuelCols['trip_id']);

/* Optional joins are only added when the referenced columns exist on this install. */
$inspectorJoin = '';
$dispatcherNameExpr = 'NULL AS dispatcher_name';
$inspectorCols = dispatcher_table_columns($conn, 'inspectors');
$accountCols = dispatcher_table_columns($conn, 'inspector_accounts');
if (isset($inspectorCols['account_id'], $inspectorCols['inspector_name']) && isset($accountCols['inspector_id'], $accountCols['account_id'])) {
    $inspectorJoin = " LEFT JOIN inspector_accounts ia ON ia.inspector_id = bt.dispatcher_id
                       LEFT JOIN inspectors insp ON insp.account_id = ia.account_id";
    $dispatcherNameExpr = "insp.inspector_name AS dispatcher_name";
}
$terminalJoin = '';
$fromTerminalExpr = 'NULL AS from_terminal_name';
$toTerminalExpr = 'NULL AS to_terminal_name';
$terminalCols = dispatcher_table_columns($conn, 'terminals');
if (isset($terminalCols['id'], $terminalCols['terminal_name'])) {
    $terminalJoin = " LEFT JOIN terminals tfrom ON tfrom.id = bt.from_terminal_id
                      LEFT JOIN terminals tto ON tto.id = bt.to_terminal_id";
    $fromTerminalExpr = "tfrom.terminal_name AS from_terminal_name";
    $toTerminalExpr = "tto.terminal_name AS to_terminal_name";
}
/* Who confirmed the arrival is recorded in the accountability trail. */
$auditJoin = '';
$arrivalByExpr = 'NULL AS arrival_by';
$arrivalAtExpr = 'NULL AS arrival_recorded_at';
if (dispatcher_table_exists($conn, 'audit_trail')) {
    $auditCols = dispatcher_table_columns($conn, 'audit_trail');
    if (isset($auditCols['id'], $auditCols['module'], $auditCols['action'], $auditCols['record_id'], $auditCols['username'])) {
        $auditJoin = " LEFT JOIN audit_trail atr ON atr.id = (SELECT MAX(a.id) FROM audit_trail a WHERE a.module = 'Dispatcher' AND a.action = 'Trip Completed' AND a.record_id = CAST(bt.id AS CHAR))";
        $arrivalByExpr = "atr.username AS arrival_by";
        $arrivalAtExpr = isset($auditCols['created_at']) ? "atr.created_at AS arrival_recorded_at" : "NULL AS arrival_recorded_at";
    }
}

$useFuel = $hasTripId && $fuelIdCol !== null;
if ($useFuel) {
    $startOdoExpr = dispatcher_fuel_coalesce_expr($fuelCols, ['starting_odo', 'starting_odometer', 'start_km']);
    $endOdoExpr = dispatcher_fuel_coalesce_expr($fuelCols, ['ending_odo', 'ending_odometer', 'end_km']);
    $distanceExpr = dispatcher_fuel_coalesce_expr($fuelCols, ['distance_traveled', 'distance_km']);
    $flFields = [
        "fl.$fuelIdCol AS fuel_log_id",
        isset($fuelCols['liters']) ? "fl.liters AS fuel_liters" : "NULL AS fuel_liters",
        isset($fuelCols['cost']) ? "fl.cost AS logged_fuel_cost" : "NULL AS logged_fuel_cost",
        isset($fuelCols['price_per_liter']) ? "fl.price_per_liter" : "NULL AS price_per_liter",
        "$startOdoExpr AS starting_odo",
        "$endOdoExpr AS ending_odo",
        isset($fuelCols['kpl']) ? "fl.kpl" : "NULL AS kpl",
        "$distanceExpr AS distance_traveled",
        isset($fuelCols['expected_fuel']) ? "fl.expected_fuel" : "NULL AS expected_fuel"
    ];

    $sql = "SELECT bt.id, bt.bus_id, bt.route_name, bt.trip_date, bt.trip_number, bt.departure_time, bt.arrival_time,
                   bt.status, bt.total_passengers, bt.duration_minutes, bt.fuel_cost, b.plate_number, d.full_name AS driver_name,
                   $dispatcherNameExpr,
                   $fromTerminalExpr,
                   $toTerminalExpr,
                   $arrivalByExpr,
                   $arrivalAtExpr,
                   " . implode(", ", $flFields) . "
            FROM bus_trips bt
            LEFT JOIN buses b ON b.bus_id = bt.bus_id
            LEFT JOIN drivers d ON d.driver_id = bt.driver_id
            $inspectorJoin
            $terminalJoin
            $auditJoin
            LEFT JOIN fuel_logs fl ON fl.$fuelIdCol = (SELECT MAX(fl_sub.$fuelIdCol) FROM fuel_logs fl_sub WHERE fl_sub.trip_id = bt.id)
            $whereClause ORDER BY bt.trip_date DESC, bt.departure_time DESC LIMIT ? OFFSET ?";
} else {
    $sql = "SELECT bt.id, bt.bus_id, bt.route_name, bt.trip_date, bt.trip_number, bt.departure_time, bt.arrival_time,
                   bt.status, bt.total_passengers, bt.duration_minutes, bt.fuel_cost, b.plate_number, d.full_name AS driver_name,
                   $dispatcherNameExpr,
                   $fromTerminalExpr,
                   $toTerminalExpr,
                   $arrivalByExpr,
                   $arrivalAtExpr,
                   NULL AS fuel_log_id, NULL AS fuel_liters, NULL AS logged_fuel_cost,
                   NULL AS price_per_liter, NULL AS starting_odo, NULL AS ending_odo, NULL AS kpl,
                   NULL AS distance_traveled, NULL AS expected_fuel
            FROM bus_trips bt
            LEFT JOIN buses b ON b.bus_id = bt.bus_id
            LEFT JOIN drivers d ON d.driver_id = bt.driver_id
            $inspectorJoin
            $terminalJoin
            $auditJoin
            $whereClause ORDER BY bt.trip_date DESC, bt.departure_time DESC LIMIT ? OFFSET ?";
}

$dataParams = array_merge($params, [$limit, $offset]);
$stmt = dispatcher_statement($conn, $sql, $types . "ii", $dataParams);
if (!$stmt) {
    // Fallback without fuel_logs join
    $fallbackSql = "SELECT bt.id, bt.bus_id, bt.route_name, bt.trip_date, bt.trip_number, bt.departure_time, bt.arrival_time,
                           bt.status, bt.total_passengers, bt.duration_minutes, bt.fuel_cost, b.plate_number, d.full_name AS driver_name,
                           $dispatcherNameExpr,
                           $fromTerminalExpr,
                           $toTerminalExpr,
                           $arrivalByExpr,
                           $arrivalAtExpr,
                           NULL AS fuel_log_id, NULL AS fuel_liters, NULL AS logged_fuel_cost,
                           NULL AS price_per_liter, NULL AS starting_odo, NULL AS ending_odo, NULL AS kpl,
                           NULL AS distance_traveled, NULL AS expected_fuel
                    FROM bus_trips bt
                    LEFT JOIN buses b ON b.bus_id = bt.bus_id
                    LEFT JOIN drivers d ON d.driver_id = bt.driver_id
                    $inspectorJoin
                    $terminalJoin
                    $auditJoin
                    $whereClause ORDER BY bt.trip_date DESC, bt.departure_time DESC LIMIT ? OFFSET ?";
    $stmt = dispatcher_statement($conn, $fallbackSql, $types . "ii", $dataParams);
}
if (!$stmt) {
    global $dispatcher_last_sql_error;
    $detail = $dispatcher_last_sql_error ?: ($conn ? $conn->error : "Query execution failed");
    dispatcher_json_error(500, "Failed to load history: " . $detail);
}

$result = $stmt->get_result();
$dispatches = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

/* Actual KPL from recorded fuel consumption only: distance / liters, never
   from odometer readings alone. */
foreach ($dispatches as &$dispatchRow) {
    $dispatchRow['actual_kpl'] = dispatcher_derive_actual_kpl(
        $dispatchRow['distance_traveled'] ?? null,
        $dispatchRow['fuel_liters'] ?? null
    );
}
unset($dispatchRow);

echo json_encode(["success" => true, "data" => $dispatches, "total" => $total, "page" => $page, "pages" => ceil($total / $limit)]);
$conn->close();

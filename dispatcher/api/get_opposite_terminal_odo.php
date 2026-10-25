<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$bus_id = trim($_GET['bus_id'] ?? '');
$terminal_id = trim($_GET['terminal_id'] ?? '');

if ($bus_id === '') {
    dispatcher_json_error(400, "Missing bus_id parameter");
}
if ($terminal_id === '' || !is_numeric($terminal_id) || (int)$terminal_id <= 0) {
    dispatcher_json_error(400, "Missing or invalid terminal_id parameter");
}

dispatcher_ensure_fuel_schema($conn);

// D1 — latest ending_odo recorded at the trip's destination (opposite) terminal.
// Column names vary between installs, so resolve them from the live schema.
$fuelCols = dispatcher_fuel_logs_columns($conn);
$fuelIdCol = dispatcher_fuel_id_column($conn);
$endExpr = dispatcher_fuel_coalesce_expr($fuelCols, ['ending_odo', 'ending_odometer', 'end_km']);
$orderCol = $fuelIdCol !== null ? "fl.$fuelIdCol" : "fl.created_at";

$start_edo = null;
$stmt = dispatcher_statement(
    $conn,
    "SELECT $endExpr AS ending_odo
     FROM fuel_logs fl
     INNER JOIN bus_trips bt ON bt.id = fl.trip_id
     WHERE fl.bus_id = ?
       AND bt.to_terminal_id = ?
       AND $endExpr IS NOT NULL
       AND $endExpr > 0
     ORDER BY $orderCol DESC
     LIMIT 1",
    "si",
    [$bus_id, (int)$terminal_id]
);
if ($stmt) {
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && $row['ending_odo'] !== null) {
        $start_edo = (float)$row['ending_odo'];
    }
}

$conn->close();

echo json_encode([
    "success" => true,
    "data" => [
        "start_edo" => $start_edo
    ]
]);
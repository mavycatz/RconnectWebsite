<?php
header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$today = date('Y-m-d');
$terminal_id = (int)($_SESSION['terminal_id'] ?? 0);

$stats = [];

function dispatcher_count($conn, $sql, $types, $params)
{
    $stmt = dispatcher_statement($conn, $sql, $types, $params);
    if (!$stmt) return 0;
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $count;
}

$terminalFilter = "";
$types = "s";
$params = [$today];
if ($terminal_id > 0) {
    $terminalFilter = " AND (from_terminal_id = ? OR to_terminal_id = ?)";
    $types .= "ii";
    $params[] = $terminal_id;
    $params[] = $terminal_id;
}

$stats['on_route'] = dispatcher_count($conn, "SELECT COUNT(*) as c FROM bus_trips WHERE trip_date = ? AND status = 'Departed' $terminalFilter", $types, $params);
$stats['pending'] = dispatcher_count($conn, "SELECT COUNT(*) as c FROM bus_trips WHERE trip_date = ? AND status = 'Scheduled' $terminalFilter", $types, $params);
$stats['arrived'] = dispatcher_count($conn, "SELECT COUNT(*) as c FROM bus_trips WHERE trip_date = ? AND status = 'Completed' $terminalFilter", $types, $params);
$stats['cancelled'] = dispatcher_count($conn, "SELECT COUNT(*) as c FROM bus_trips WHERE trip_date = ? AND status = 'Cancelled' $terminalFilter", $types, $params);
$stats['total'] = dispatcher_count($conn, "SELECT COUNT(*) as c FROM bus_trips WHERE trip_date = ? $terminalFilter", $types, $params);
$stats['active_drivers'] = dispatcher_count($conn, "SELECT COUNT(DISTINCT driver_id) as c FROM bus_trips WHERE trip_date = ? $terminalFilter", $types, $params);

echo json_encode(["success" => true, "data" => $stats]);
$conn->close();

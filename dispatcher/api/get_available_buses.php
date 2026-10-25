<?php
header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$search = trim($_GET['search'] ?? '');

$sql = "SELECT b.bus_id, b.plate_number, b.bus_type, b.seats, b.route_name, b.status,
               b.from_terminal_id, b.to_terminal_id, b.driver AS legacy_driver_ref,
               d.full_name AS assigned_driver
        FROM buses b LEFT JOIN drivers d ON d.driver_id = b.driver
        WHERE b.bus_id NOT IN (
            SELECT bus_id FROM bus_trips WHERE trip_date = CURDATE() AND status IN ('Scheduled','Departed','Interrupted')
        )";

$types = "";
$params = [];
if (!empty($search)) {
    $sql .= " AND (b.bus_id LIKE ? OR b.plate_number LIKE ?)";
    $types .= "ss";
    $params = ["%$search%", "%$search%"];
}

$stmt = dispatcher_statement($conn, $sql, $types, $params);
if (!$stmt) dispatcher_json_error(500, "Failed to load buses");
$buses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// The bus's assigned driver is the authoritative pairing from
// driver_bus_assignment; fall back to the legacy buses.driver reference.
$assignmentMap = dispatcher_queue_driver_assignment_map($conn, array_column($buses, 'bus_id'));
foreach ($buses as &$bus) {
    $busId = (string)($bus['bus_id'] ?? '');
    $assignedId = $assignmentMap[$busId] ?? null;
    if ($assignedId === null || $assignedId === '') {
        $legacyRef = trim((string)($bus['legacy_driver_ref'] ?? ''));
        $assignedId = $legacyRef !== '' ? $legacyRef : null;
    }
    $bus['assigned_driver_id'] = $assignedId;
    unset($bus['legacy_driver_ref']);
}
unset($bus);

echo json_encode(["success" => true, "data" => $buses]);
$conn->close();

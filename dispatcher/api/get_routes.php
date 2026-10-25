<?php
header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

/* Stored fleet routes; the TBD/Unassigned placeholders are never offered. */
$stmt = dispatcher_statement(
    $conn,
    "SELECT DISTINCT route_name FROM buses
     WHERE route_name IS NOT NULL AND TRIM(route_name) != ''
       AND UPPER(TRIM(route_name)) NOT IN ('TBD', 'UNASSIGNED')
       AND status = 'Active'
     ORDER BY route_name ASC",
    "",
    []
);
if (!$stmt) dispatcher_json_error(500, "Failed to load routes");
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$routes = [];
foreach ($rows as $row) $routes[] = $row['route_name'];

/* Include the automatic route for this dispatcher's terminal direction so the
   dispatch modal can hold the same route the queue resolves by itself. */
$terminal_id = (int)($_SESSION['terminal_id'] ?? 0);
if ($terminal_id > 0) {
    $pair = dispatcher_terminal_pair($conn);
    $destinationId = 0;
    if ($pair !== null) {
        if ($terminal_id === $pair['rizal']) {
            $destinationId = $pair['cabanatuan'];
        } elseif ($terminal_id === $pair['cabanatuan']) {
            $destinationId = $pair['rizal'];
        }
    }
    if ($destinationId > 0) {
        $autoRoutes = [
            dispatcher_default_route_name($conn, $terminal_id, $destinationId),
            dispatcher_recorded_route_name($conn, $terminal_id, $destinationId),
        ];
        foreach ($autoRoutes as $autoRoute) {
            if ($autoRoute !== '' && !in_array($autoRoute, $routes, true)) {
                $routes[] = $autoRoute;
            }
        }
    }
}
echo json_encode(["success" => true, "data" => $routes]);
$conn->close();

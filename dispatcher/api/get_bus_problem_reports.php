<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

dispatcher_ensure_bpr_schema($conn);

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$trip_id = trim($_GET['trip_id'] ?? '');
$driver_id = trim($_GET['driver_id'] ?? '');
$bus_id = trim($_GET['bus_id'] ?? '');

$where = [];
$types = "";
$params = [];

if (!empty($status)) {
    $where[] = "bpr.status = ?";
    $types .= "s";
    $params[] = $status;
}
if (!empty($trip_id)) {
    $where[] = "bpr.trip_id = ?";
    $types .= "i";
    $params[] = (int)$trip_id;
}
if (!empty($driver_id)) {
    $where[] = "bpr.driver_id = ?";
    $types .= "s";
    $params[] = $driver_id;
}
if (!empty($bus_id)) {
    $where[] = "bpr.bus_id = ?";
    $types .= "s";
    $params[] = $bus_id;
}
if (!empty($search)) {
    $where[] = "(bpr.driver_id LIKE ? OR bpr.bus_id LIKE ? OR bpr.trip_no LIKE ? OR bpr.problem_type LIKE ?)";
    $types .= "ssss";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);

$sql = "SELECT 
            bpr.*,
            bt.route_name,
            bt.departure_time,
            d.full_name AS driver_name,
            b.plate_number
        FROM bus_problem_reports bpr
        LEFT JOIN bus_trips bt ON bt.id = bpr.trip_id
        LEFT JOIN drivers d ON d.driver_id = bpr.driver_id
        LEFT JOIN buses b ON b.bus_id = bpr.bus_id
        $whereClause
        ORDER BY bpr.created_at DESC
        LIMIT 1000";

$stmt = dispatcher_statement($conn, $sql, $types, $params);
if (!$stmt) {
    dispatcher_json_error(500, "Failed to fetch problem reports: " . ($dispatcher_last_sql_error ?? "Unknown error"));
}

$result = $stmt->get_result();
$reports = [];
while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}
$stmt->close();
$conn->close();

echo json_encode([
    "success" => true,
    "reports" => $reports,
    "count" => count($reports)
]);

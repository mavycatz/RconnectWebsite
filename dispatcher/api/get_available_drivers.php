<?php
header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        d.driver_id,
        d.full_name,
        d.status,
        d.license_type,
        d.experience_years
    FROM drivers d
    WHERE d.driver_id NOT IN (
        SELECT bt.driver_id
        FROM bus_trips bt
        WHERE bt.driver_id IS NOT NULL
          AND bt.trip_date = CURDATE()
          AND bt.status IN ('Scheduled', 'Departed', 'Interrupted')
    )
";

$types = "";
$params = [];
if (!empty($search)) {
    $sql .= " AND (d.full_name LIKE ? OR d.driver_id LIKE ?)";
    $types .= "ss";
    $params = ["%$search%", "%$search%"];
}

$sql .= " ORDER BY d.full_name ASC";

$stmt = dispatcher_statement($conn, $sql, $types, $params);
if (!$stmt) dispatcher_json_error(500, "Failed to load drivers");
$drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(["success" => true, "data" => $drivers]);
$conn->close();

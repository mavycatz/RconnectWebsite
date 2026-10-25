<?php
header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$type = trim($_GET['type'] ?? '');

$sql = "SELECT id, title, description, type, reference_type, reference_id, is_read, created_at FROM notifications WHERE user_id = 0";
$types = "";
$params = [];
if (!empty($type)) {
    $sql .= " AND type = ?";
    $types .= "s";
    $params[] = $type;
}
$sql .= " ORDER BY created_at DESC LIMIT 50";

$stmt = dispatcher_statement($conn, $sql, $types, $params);
if (!$stmt) dispatcher_json_error(500, "Failed to load notifications");
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(["success" => true, "data" => $notifications]);
$conn->close();

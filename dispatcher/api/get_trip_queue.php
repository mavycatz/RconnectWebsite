<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$terminal_id = (int)($_SESSION['terminal_id'] ?? 0);
if ($terminal_id <= 0) {
    dispatcher_json_error(400, "No terminal selected");
}

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

try {
    $data = dispatcher_queue_build($conn, $terminal_id, $date);
} catch (\Throwable $e) {
    error_log("Trip queue build failed: " . $e->getMessage());
    dispatcher_json_error(500, "Unable to load the queue. Please try again.");
}

echo json_encode(["success" => true, "data" => $data]);
$conn->close();

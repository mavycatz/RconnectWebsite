<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$bus_id = trim($_GET['bus_id'] ?? '');
if (!$bus_id) {
    dispatcher_json_error(400, "Missing bus_id parameter");
}

$data = dispatcher_bus_fuel_reference($conn, $bus_id);
$conn->close();

echo json_encode([
    "success" => true,
    "data" => $data
]);

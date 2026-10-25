<?php

header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$bus_id = $_GET['bus_id'] ?? '';

if (empty($bus_id)) {
    echo json_encode([
        "success" => false,
        "message" => "bus_id required"
    ]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON",
        "raw" => $raw
    ]);
    exit;
}

if (!isset($data['id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Passenger trip ID missing"
    ]);
    exit;
}

$id = (int)$data['id'];

error_log("Updating payment for passenger trip ID: {$id}, Bus: {$bus_id}");

$sql = "
UPDATE passenger_trips
SET payment_status = 'paid'
WHERE id = ?
AND bus_no = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Prepare failed",
        "error" => $conn->error
    ]);
    exit;
}

$stmt->bind_param("is", $id, $bus_id);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Execute failed",
        "error" => $stmt->error
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "updated_id" => $id,
    "bus_id" => $bus_id,
    "rows_affected" => $stmt->affected_rows
]);

$stmt->close();
$conn->close();
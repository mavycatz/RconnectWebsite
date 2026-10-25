<?php
/**
 * get_drivers.php
 * Lists drivers for the Inspector fuel-recording Driver picker.
 *
 * Response envelope:
 *   { success, drivers: [ { driver_id, full_name } ] }
 */

header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

try {
    $result = $conn->query("SELECT driver_id, full_name FROM drivers ORDER BY full_name");

    if (!$result) {
        throw new Exception($conn->error);
    }

    $drivers = [];
    while ($row = $result->fetch_assoc()) {
        $drivers[] = [
            "driver_id" => $row['driver_id'],
            "full_name" => $row['full_name'],
        ];
    }

    echo json_encode(["success" => true, "drivers" => $drivers]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

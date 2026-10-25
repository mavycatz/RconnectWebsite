<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ]);
    exit;
}

try {

    $sql = "
SELECT
    id,
    type,
    title,
    message,
    recording_url,
    bus_id,
    driver_name,
    reported_by,
    priority,
    created_at,
    status,

    COALESCE(driver_name, reported_by, 'System') AS full_name

FROM announcements
WHERE status = 'Active'
ORDER BY created_at DESC
";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception($conn->error);
    }

    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data" => $data
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);

}
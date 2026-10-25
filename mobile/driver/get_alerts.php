<?php
header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

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

    /* DISPLAY NAME PRIORITY */
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

    // Optional: return a full URL instead of just the relative path
    /*
    if (!empty($row['recording_url'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $row['recording_url'] = $protocol . '://' . $host . '/' . ltrim($row['recording_url'], '/');
    }
    */

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
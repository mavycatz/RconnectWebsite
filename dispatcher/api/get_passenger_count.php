<?php
header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$bus_id = trim($_GET['bus_id'] ?? '');
if (!$bus_id) dispatcher_json_error(400, "bus_id required");

$tripStmt = dispatcher_statement($conn, "SELECT id FROM bus_trips WHERE bus_id = ? AND trip_date = CURDATE() ORDER BY id DESC LIMIT 1", "s", [$bus_id]);
$trip = null;
if ($tripStmt) {
    $trip = $tripStmt->get_result()->fetch_assoc();
    $tripStmt->close();
}
$trip_id = $trip ? (int)$trip['id'] : 0;

$r = ["total_passengers" => 0, "onboard" => 0, "dropped_off" => 0, "unpaid" => 0];
if ($trip_id) {
    $counts = [
        "total_passengers" => "SELECT COUNT(*) as c FROM passenger_trips WHERE trip_no = ?",
        "onboard" => "SELECT COUNT(*) as c FROM passenger_trips WHERE trip_no = ? AND dropoff_time IS NULL",
        "unpaid" => "SELECT COUNT(*) as c FROM passenger_trips WHERE trip_no = ? AND (payment_status IS NULL OR payment_status != 'Paid')",
    ];
    foreach ($counts as $key => $sql) {
        $stmt = dispatcher_statement($conn, $sql, "i", [$trip_id]);
        if ($stmt) {
            $r[$key] = (int)$stmt->get_result()->fetch_assoc()['c'];
            $stmt->close();
        }
    }
    $r['dropped_off'] = $r['total_passengers'] - $r['onboard'];
}
echo json_encode(["success" => true, "data" => $r]);
$conn->close();

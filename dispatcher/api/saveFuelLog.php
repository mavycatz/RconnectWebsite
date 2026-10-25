<?php

header("Content-Type: application/json");
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();
dispatcher_require_csrf();

$db = new Database();
$conn = $db->connect();

$input = json_decode(file_get_contents("php://input"), true);
dispatcher_assert_identity($input, $dispatcher_user);
$bus_id = trim($input['bus_id'] ?? '');
$driver_id = trim($input['driver_id'] ?? '');
$liters = (float)($input['liters'] ?? 0);
$price_per_liter = (float)($input['price_per_liter'] ?? 0);
$starting_odo = isset($input['starting_odo']) && $input['starting_odo'] !== '' ? (float)$input['starting_odo'] : 0.0;
$trip_id = (int)($input['trip_id'] ?? 0);

if (!$bus_id || !$driver_id) {
    dispatcher_json_error(400, "Bus and driver are required for a fuel record");
}
if ($liters <= 0) {
    dispatcher_json_error(400, "Liters must be greater than 0");
}
if ($starting_odo <= 0) {
    dispatcher_json_error(400, "Starting ODO is required");
}
if ($price_per_liter <= 0) {
    dispatcher_json_error(400, "Price per liter is required");
}

if (!dispatcher_valid_bus($conn, $bus_id)) {
    dispatcher_json_error(400, "Selected bus is not available");
}

$cost = round($liters * $price_per_liter, 2);
$tripRef = $trip_id > 0 ? $trip_id : null;

$stmt = dispatcher_statement($conn, "
    INSERT INTO fuel_logs (bus_id, driver_id, trip_id, liters, cost, price_per_liter, starting_odo, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
", "ssidddd", [$bus_id, $driver_id, $tripRef, $liters, $cost, $price_per_liter, $starting_odo]);

if ($stmt) {
    $log_id = $stmt->insert_id;
    $stmt->close();
    dispatcher_audit_log(
        $conn,
        $dispatcher_user,
        'Fuel Record Saved',
        $tripRef !== null ? $tripRef : $log_id,
        'Fuel record saved',
        [],
        [
            'fuel_log_id'     => $log_id,
            'trip_id'         => $tripRef,
            'bus_id'          => $bus_id,
            'driver_id'       => $driver_id,
            'starting_odo'    => $starting_odo,
            'liters'          => $liters,
            'price_per_liter' => $price_per_liter,
            'cost'            => $cost,
        ]
    );
    echo json_encode([
        "success" => true,
        "message" => "Fuel log saved",
        "log_id" => $log_id,
        "cost" => $cost
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to save fuel log"
    ]);
}

$conn->close();

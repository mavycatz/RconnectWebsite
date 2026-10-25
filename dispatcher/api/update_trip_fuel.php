<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();
dispatcher_require_csrf();

$db = new Database();
$conn = $db->connect();

$data = json_decode(file_get_contents("php://input"), true);
dispatcher_assert_identity($data, $dispatcher_user);
$trip_id = (int)($data['trip_id'] ?? 0);
$fuel_log_id = (int)($data['fuel_log_id'] ?? 0);

if (!$trip_id && !$fuel_log_id) {
    dispatcher_json_error(400, "trip_id or fuel_log_id is required");
}

dispatcher_ensure_fuel_schema($conn);
$fuelIdCol = dispatcher_fuel_id_column($conn);

// 1. Locate the existing fuel log record
$existing = null;
if ($fuel_log_id > 0 && $fuelIdCol !== null) {
    $stmt = dispatcher_statement($conn, "SELECT * FROM fuel_logs WHERE $fuelIdCol = ? LIMIT 1", "i", [$fuel_log_id]);
    if ($stmt) {
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
} elseif ($trip_id > 0) {
    $orderBy = $fuelIdCol !== null ? " ORDER BY $fuelIdCol DESC" : "";
    $stmt = dispatcher_statement($conn, "SELECT * FROM fuel_logs WHERE trip_id = ?$orderBy LIMIT 1", "i", [$trip_id]);
    if ($stmt) {
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// If no existing fuel log, check trip details from bus_trips
$tripRow = null;
if ($trip_id > 0) {
    $tripStmt = dispatcher_statement($conn, "SELECT * FROM bus_trips WHERE id = ? LIMIT 1", "i", [$trip_id]);
    if ($tripStmt) {
        $tripRow = $tripStmt->get_result()->fetch_assoc();
        $tripStmt->close();
    }
}

if (!$existing && !$tripRow) {
    dispatcher_json_error(404, "Fuel log or trip not found");
}

$bus_id = $existing['bus_id'] ?? $tripRow['bus_id'] ?? '';
$driver_id = $existing['driver_id'] ?? $tripRow['driver_id'] ?? '';
$current_trip_id = $existing['trip_id'] ?? $trip_id;

if ($bus_id === '' || $driver_id === '') {
    dispatcher_json_error(400, "Bus and driver are required for a fuel record");
}

/* Odometer-only corrections are supported: liters and KPL are optional, and
   no KPL is inferred when they are omitted. The edit reason stays mandatory,
   and the values are validated in one shared helper. */
$correction = dispatcher_trip_fuel_correction($conn, $existing, is_array($data) ? $data : []);
if (empty($correction['success'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "code" => $correction['code'] ?? 'invalid_fuel_record',
        "message" => $correction['error'] ?? 'The fuel record could not be saved',
    ]);
    $conn->close();
    exit;
}
$reason = (string)$correction['reason'];
$values = $correction['values'];
$starting_odo = $values['starting_odo'];
$ending_odo = $values['ending_odo'];
$kpl = $values['kpl'];
$distance_traveled = $values['distance_traveled'];
$expected_fuel = $values['expected_fuel'];
$actual_liters = $values['liters'];
$price_per_liter = $values['price_per_liter'];
$fuel_cost = $values['cost'];

if ($existing) {
    $log_id = (int)($existing[$fuelIdCol] ?? $existing['fuel_log_id'] ?? $existing['id'] ?? 0);
    $updateSql = "UPDATE fuel_logs
         SET starting_odo = ?, ending_odo = ?, kpl = ?, distance_traveled = ?,
             expected_fuel = ?, liters = ?, cost = ?, price_per_liter = ?, trip_id = COALESCE(trip_id, ?)
         WHERE ";
    $updateTypes = "ddddddddii";
    if ($fuelIdCol !== null && $log_id > 0) {
        $updateSql .= "$fuelIdCol = ?";
        $updateParams = [$starting_odo, $ending_odo, $kpl, $distance_traveled, $expected_fuel, $actual_liters, $fuel_cost, $price_per_liter, $current_trip_id, $log_id];
    } else {
        $updateSql .= "trip_id = ?";
        $updateParams = [$starting_odo, $ending_odo, $kpl, $distance_traveled, $expected_fuel, $actual_liters, $fuel_cost, $price_per_liter, $current_trip_id, $current_trip_id];
    }
    $upd = dispatcher_statement($conn, $updateSql, $updateTypes, $updateParams);
    if (!$upd) dispatcher_json_error(500, "Failed to update fuel log");
    $upd->close();
} else {
    // Insert new fuel_logs row if one wasn't created at dispatch
    $ins = dispatcher_statement(
        $conn,
        "INSERT INTO fuel_logs (bus_id, driver_id, trip_id, liters, cost, price_per_liter, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        "ssisddddddd",
        [$bus_id, $driver_id, $current_trip_id, $actual_liters, $fuel_cost, $price_per_liter, $starting_odo, $ending_odo, $kpl, $distance_traveled, $expected_fuel]
    );
    if (!$ins) dispatcher_json_error(500, "Failed to save fuel log");
    $log_id = $conn->insert_id;
    $ins->close();
}

// Keep bus_trips.fuel_cost in sync
if ($current_trip_id > 0) {
    @$conn->query("UPDATE bus_trips SET fuel_cost = $fuel_cost WHERE id = $current_trip_id");
}

$fuelNew = [
    'fuel_log_id'       => $log_id,
    'trip_id'           => $current_trip_id,
    'bus_id'            => $bus_id,
    'driver_id'         => $driver_id,
    'starting_odo'      => $starting_odo,
    'ending_odo'        => $ending_odo,
    'kpl'               => $kpl,
    'distance_traveled' => $distance_traveled,
    'expected_fuel'     => $expected_fuel,
    'liters'            => $actual_liters,
    'cost'              => $fuel_cost,
    'price_per_liter'   => $price_per_liter,
];

if ($existing) {
    $prevEnd = $existing['ending_odo'] ?? $existing['ending_odometer'] ?? $existing['end_km'] ?? null;
    $prevDist = $existing['distance_traveled'] ?? $existing['distance_km'] ?? null;
    $fuelPrev = [
        'starting_odo'      => (float)($existing['starting_odo'] ?? $existing['starting_odometer'] ?? $existing['start_km'] ?? 0),
        'ending_odo'        => $prevEnd !== null ? (float)$prevEnd : null,
        'kpl'               => (float)($existing['kpl'] ?? 0),
        'distance_traveled' => $prevDist !== null ? (float)$prevDist : null,
        'expected_fuel'     => isset($existing['expected_fuel']) && $existing['expected_fuel'] !== null ? (float)$existing['expected_fuel'] : null,
        'liters'            => (float)($existing['liters'] ?? 0),
        'cost'              => (float)($existing['cost'] ?? 0),
        'price_per_liter'   => (float)($existing['price_per_liter'] ?? 0),
    ];
    dispatcher_audit_log(
        $conn,
        $dispatcher_user,
        'Fuel Record Edited',
        $current_trip_id > 0 ? $current_trip_id : $log_id,
        'Fuel record edited: ' . $reason,
        $fuelPrev,
        $fuelNew + ['reason' => $reason]
    );
} else {
    dispatcher_audit_log(
        $conn,
        $dispatcher_user,
        'Fuel Record Saved',
        $current_trip_id > 0 ? $current_trip_id : $log_id,
        'Fuel record saved',
        [],
        $fuelNew
    );
}

$conn->close();

echo json_encode([
    "success" => true,
    "message" => "Fuel & odometer record saved",
    "data" => [
        "fuel_log_id" => $log_id,
        "trip_id" => $current_trip_id,
        "bus_id" => $bus_id,
        "driver_id" => $driver_id,
        "starting_odo" => $starting_odo,
        "ending_odo" => $ending_odo,
        "kpl" => $kpl,
        "actual_kpl" => $values['actual_kpl'] ?? null,
        "distance_traveled" => $distance_traveled,
        "expected_fuel" => $expected_fuel,
        "actual_liters" => $actual_liters,
        "fuel_cost" => $fuel_cost,
        "price_per_liter" => $price_per_liter
    ]
]);

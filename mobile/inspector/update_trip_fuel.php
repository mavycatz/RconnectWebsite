<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$data = json_decode(file_get_contents("php://input"), true);
$trip_id = (int)($data['trip_id'] ?? 0);
$fuel_log_id = (int)($data['fuel_log_id'] ?? 0);
$bus_id = trim($data['bus_id'] ?? '');

if (!$trip_id && !$fuel_log_id && !$bus_id) {
    echo json_encode(["success" => false, "message" => "trip_id, fuel_log_id, or bus_id required"]);
    exit;
}

// Find fuel log
$existing = null;
if ($fuel_log_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM fuel_logs WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $fuel_log_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif ($trip_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM fuel_logs WHERE trip_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif ($bus_id !== '') {
    $stmt = $conn->prepare("SELECT * FROM fuel_logs WHERE bus_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $bus_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$existing) {
    echo json_encode(["success" => false, "message" => "No existing fuel log found for this trip"]);
    exit;
}

$startOdo = (isset($data['starting_odo']) && $data['starting_odo'] !== '')
    ? (float)$data['starting_odo']
    : (float)($existing['starting_odo'] ?? 0);

$endingOdo = (isset($data['ending_odo']) && $data['ending_odo'] !== '')
    ? (float)$data['ending_odo']
    : ($existing['ending_odo'] !== null ? (float)$existing['ending_odo'] : null);

$kpl = (isset($data['kpl']) && (float)$data['kpl'] > 0)
    ? (float)$data['kpl']
    : ((float)($existing['kpl'] ?? 0) > 0 ? (float)$existing['kpl'] : 9.0);

$actualLiters = (isset($data['actual_liters']) && $data['actual_liters'] !== '')
    ? max(0.0, (float)$data['actual_liters'])
    : (float)($existing['liters'] ?? 0);

if ($kpl <= 0) {
    echo json_encode(["success" => false, "message" => "KPL must be greater than 0"]);
    exit;
}

$distanceTraveled = null;
$expectedFuel = null;

if ($endingOdo !== null) {
    if ($endingOdo < $startOdo) {
        echo json_encode(["success" => false, "message" => "Ending ODO cannot be less than Starting ODO"]);
        exit;
    }
    $distanceTraveled = round($endingOdo - $startOdo, 2);
    $expectedFuel = round($distanceTraveled / $kpl, 2);
}

$pricePerLiter = (float)($existing['price_per_liter'] ?? 0);
$fuelCost = round($actualLiters * $pricePerLiter, 2);
$logId = (int)$existing['id'];

$stmt = $conn->prepare("UPDATE fuel_logs SET starting_odo = ?, ending_odo = ?, kpl = ?, distance_traveled = ?, expected_fuel = ?, liters = ?, cost = ? WHERE id = ?");
$stmt->bind_param("dddddddi", $startOdo, $endingOdo, $kpl, $distanceTraveled, $expectedFuel, $actualLiters, $fuelCost, $logId);
$ok = $stmt->execute();
$stmt->close();

$currentTripId = (int)($existing['trip_id'] ?? $trip_id);
if ($currentTripId > 0) {
    @$conn->query("UPDATE bus_trips SET fuel_cost = $fuelCost WHERE id = $currentTripId");
}

$conn->close();

if ($ok) {
    echo json_encode([
        "success" => true,
        "message" => "Fuel and odometer record updated",
        "data" => [
            "fuel_log_id" => $logId,
            "trip_id" => $currentTripId,
            "starting_odo" => $startOdo,
            "ending_odo" => $endingOdo,
            "distance_traveled" => $distanceTraveled,
            "kpl" => $kpl,
            "expected_fuel" => $expectedFuel,
            "liters" => $actualLiters,
            "cost" => $fuelCost,
            "price_per_liter" => $pricePerLiter
        ]
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to update fuel log"]);
}

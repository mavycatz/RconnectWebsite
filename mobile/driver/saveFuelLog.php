<?php

header("Content-Type: application/json");
require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

$input = json_decode(file_get_contents("php://input"), true);

$bus_id = $input['bus_id'] ?? '';
$driver_id = $input['driver_id'] ?? '';
$trip_id = trim((string)($input['trip_id'] ?? ''));
$liters = (float)($input['liters'] ?? 0);
$price_per_liter = (float)($input['price_per_liter'] ?? 0);
$total_cost = (float)($input['total_cost'] ?? ($input['cost'] ?? 0));
$refuel_odo = (float)($input['refuel_odo'] ?? 0);
$is_full_tank = array_key_exists('is_full_tank', $input) ? ((int)$input['is_full_tank'] ? 1 : 0) : 1;
$refuel_type = trim((string)($input['refuel_type'] ?? 'Full Tank'));
$notes = trim((string)($input['notes'] ?? ''));
$fuel_proof_photo = $input['fuel_proof_photo'] ?? ($input['photo'] ?? ($input['fuel_photo'] ?? ''));

$token = $input['token'] ?? '';

require_once __DIR__ . '/lib/driver_auth.php';
require_driver_auth($conn, $driver_id, $token);

if (!$bus_id || $liters <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Bus ID and valid liters amount required"]);
    exit;
}

if ($total_cost <= 0 && $price_per_liter > 0) {
    $total_cost = $liters * $price_per_liter;
}

$allowedRefuelTypes = ['Full Tank', 'Partial'];
if (!in_array($refuel_type, $allowedRefuelTypes, true)) {
    $refuel_type = 'Full Tank';
}

$trip_id = $trip_id !== '' ? $trip_id : null;
$notes = $notes !== '' ? $notes : null;

if (!function_exists('save_fuel_proof_photo')) {
    function save_fuel_proof_photo($dataUri, $recordId) {
        if (!is_string($dataUri) || $dataUri === '') {
            return null;
        }
        if (strpos($dataUri, 'data:image') !== 0) {
            return $dataUri;
        }
        $prefix = 'data:image/jpeg;base64,';
        if (strncmp($dataUri, $prefix, strlen($prefix)) !== 0) {
            if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $dataUri, $matches)) {
                $prefix = $matches[0];
            } else {
                return null;
            }
        }
        $base64 = preg_replace('/\s+/', '', substr($dataUri, strlen($prefix)));
        $bytes = base64_decode($base64, true);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $uploadDir = dirname(__DIR__) . '/fuel_proofs/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $filename = 'fuel_proof_' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$recordId) . '_' . date('Ymd_His') . '.jpg';
        $destination = $uploadDir . $filename;
        if (@file_put_contents($destination, $bytes) === false) {
            return null;
        }
        return 'fuel_proofs/' . $filename;
    }
}

$fuel_proof_url = null;
if (!empty($fuel_proof_photo)) {
    $fuel_proof_url = save_fuel_proof_photo($fuel_proof_photo, $bus_id);
}

// Every driver fuel log is written to fuel_refuels; the summary, history, and
// receipt views read from this same table.
$stmt = $conn->prepare("
    INSERT INTO fuel_refuels
        (bus_id, driver_id, trip_id, refuel_odo, liters, price_per_liter, total_cost,
         is_full_tank, refuel_type, notes, fuel_proof_url, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
");

$stmt->bind_param(
    "sssddddisss",
    $bus_id,
    $driver_id,
    $trip_id,
    $refuel_odo,
    $liters,
    $price_per_liter,
    $total_cost,
    $is_full_tank,
    $refuel_type,
    $notes,
    $fuel_proof_url
);

if ($stmt->execute()) {
    $refuel_id = $stmt->insert_id;
    $receipt_number = "FUEL-" . date("Ymd") . "-" . str_pad($refuel_id, 4, "0", STR_PAD_LEFT);

    $response = [
        "success" => true,
        "message" => "Fuel log saved",
        "log_id" => $refuel_id,
        "refuel_id" => $refuel_id,
        "receipt_id" => $refuel_id,
        "receipt_number" => $receipt_number,
    ];

    if ($fuel_proof_url) {
        $response["fuel_proof_url"] = $fuel_proof_url;
    }

    echo json_encode($response);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to save fuel log"
    ]);
}

$stmt->close();
$conn->close();

<?php
/**
 * save_fuel_verification.php
 * Inserts or updates an Inspector's verification of a fuel record.
 *
 * The Inspector supplies ODO/KPL inputs and all computed fuel-consumption
 * values (which the app calculates live). This endpoint persists the
 * decision so it becomes a durable audit record.
 *
 * Usage (POST, JSON or form):
 *   {
 *     "fuel_receipt_id": 12,
 *     "inspector_id": "5",
 *     "start_odo": 120000,
 *     "end_odo": 121500,
 *     "bus_kpl": 6.5,
 *     "distance": 1500,
 *     "expected_fuel": 230.77,
 *     "actual_fuel": 250,
 *     "actual_kpl": 6.0,
 *     "fuel_cost": 17862.50,
 *     "variance": 19.23,
 *     "status": "verified" | "flagged",
 *     "reason": "Heavy traffic"   // required when status = "flagged"
 *   }
 */

header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

$db = new Database();
$conn = $db->connect();

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$fuelReceiptId = (int)($input['fuel_receipt_id'] ?? 0);
$inspectorId = trim($input['inspector_id'] ?? '');
$status = trim($input['status'] ?? '');
$reason = trim($input['reason'] ?? '');

$startOdo = isset($input['start_odo']) && $input['start_odo'] !== '' ? (float)$input['start_odo'] : null;
$endOdo = isset($input['end_odo']) && $input['end_odo'] !== '' ? (float)$input['end_odo'] : null;
$busKpl = isset($input['bus_kpl']) && $input['bus_kpl'] !== '' ? (float)$input['bus_kpl'] : null;
$distance = isset($input['distance']) && $input['distance'] !== '' ? (float)$input['distance'] : null;
$expectedFuel = isset($input['expected_fuel']) && $input['expected_fuel'] !== '' ? (float)$input['expected_fuel'] : null;
$actualFuel = isset($input['actual_fuel']) && $input['actual_fuel'] !== '' ? (float)$input['actual_fuel'] : null;
$actualKpl = isset($input['actual_kpl']) && $input['actual_kpl'] !== '' ? (float)$input['actual_kpl'] : null;
$fuelCost = isset($input['fuel_cost']) && $input['fuel_cost'] !== '' ? (float)$input['fuel_cost'] : null;
$variance = isset($input['variance']) && $input['variance'] !== '' ? (float)$input['variance'] : null;

if ($fuelReceiptId <= 0 || empty($inspectorId)) {
    echo json_encode(["success" => false, "message" => "fuel_receipt_id and inspector_id are required"]);
    exit;
}

if ($status !== 'verified' && $status !== 'flagged') {
    echo json_encode(["success" => false, "message" => "status must be 'verified' or 'flagged'"]);
    exit;
}

// A flagged record must carry a reason.
if ($status === 'flagged' && $reason === '') {
    echo json_encode(["success" => false, "message" => "A reason is required when flagging a fuel record"]);
    exit;
}

$reason = $reason === '' ? null : $reason;

try {
    // Re-verify: if the inspector already verified this record, update it.
    $stmt = $conn->prepare("
        INSERT INTO fuel_verifications
            (fuel_receipt_id, inspector_id, start_odo, end_odo, bus_kpl, distance,
             expected_fuel, actual_fuel, actual_kpl, fuel_cost, variance, status, reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            start_odo = VALUES(start_odo),
            end_odo = VALUES(end_odo),
            bus_kpl = VALUES(bus_kpl),
            distance = VALUES(distance),
            expected_fuel = VALUES(expected_fuel),
            actual_fuel = VALUES(actual_fuel),
            actual_kpl = VALUES(actual_kpl),
            fuel_cost = VALUES(fuel_cost),
            variance = VALUES(variance),
            status = VALUES(status),
            reason = VALUES(reason),
            updated_at = CURRENT_TIMESTAMP
    ");

    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Failed to prepare verification insert"]);
        exit;
    }

    $stmt->bind_param(
        "issddddddddds",
        $fuelReceiptId,
        $inspectorId,
        $startOdo,
        $endOdo,
        $busKpl,
        $distance,
        $expectedFuel,
        $actualFuel,
        $actualKpl,
        $fuelCost,
        $variance,
        $status,
        $reason
    );

    $stmt->execute();
    $insertId = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        "success" => true,
        "message" => $status === 'verified' ? "Fuel record verified" : "Fuel record flagged for review",
        "verification_id" => $insertId
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

$conn->close();

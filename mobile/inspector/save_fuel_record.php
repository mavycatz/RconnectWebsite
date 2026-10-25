<?php
/**
 * save_fuel_record.php
 * Creates or updates an Inspector-owned fuel record.
 *
 * The Inspector is the recorder of fuel info. The screen computes the metrics
 * live; this endpoint re-computes them server-side for audit integrity.
 *
 * Usage (POST, JSON or form):
 *   {
 *     "record_id": 12,            // omit/0 for a new record
 *     "inspector_id": "5",
 *     "bus_id": "B-001",
 *     "driver_id": "D-9",
 *     "start_odo": 120000,
 *     "end_odo": 121500,
 *     "fuel_liters": 250,
 *     "price_per_liter": 62.5,
 *     "bus_kpl": 6.5,
 *     "receipt_number": "FUEL-20260909-0001",
 *     "receipt_image": "receipts/receipt_20260909_123456_ab12.jpg",
 *     "notes": "Heavy traffic along EDSA",
 *     "status": "recorded" | "verified" | "flagged",
 *     "reason": "Heavy traffic"   // required when status = "flagged"
 *   }
 *
 * Response envelope:
 *   { success, message, record_id }
 */

header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    $input = $_POST;
}

$recordId = (int)($input['record_id'] ?? 0);
$inspectorId = trim($input['inspector_id'] ?? '');
$busId = trim($input['bus_id'] ?? '');
$driverId = trim($input['driver_id'] ?? '');
$status = trim($input['status'] ?? 'recorded');
$reason = trim($input['reason'] ?? '');
$receiptNumber = trim($input['receipt_number'] ?? '');
$receiptImage = trim($input['receipt_image'] ?? '');
$notes = trim($input['notes'] ?? '');

$num = function ($k) use ($input) {
    return isset($input[$k]) && $input[$k] !== '' && $input[$k] !== null ? (float)$input[$k] : null;
};
$startOdo = $num('start_odo');
$endOdo = $num('end_odo');
$fuelLiters = $num('fuel_liters');
$price = $num('price_per_liter');
$busKpl = $num('bus_kpl');

if (empty($inspectorId) || empty($busId) || empty($driverId)) {
    echo json_encode(["success" => false, "message" => "inspector_id, bus_id, and driver_id are required"]);
    exit;
}

if (!$fuelLiters || $fuelLiters <= 0) {
    echo json_encode(["success" => false, "message" => "Fuel added must be greater than zero"]);
    exit;
}
if (!$price || $price <= 0) {
    echo json_encode(["success" => false, "message" => "Diesel price must be greater than zero"]);
    exit;
}
if (($startOdo !== null && $endOdo !== null) && $endOdo <= $startOdo) {
    echo json_encode(["success" => false, "message" => "Ending ODO must be greater than Starting ODO"]);
    exit;
}

$validStatus = ['recorded', 'verified', 'flagged'];
if (!in_array($status, $validStatus, true)) {
    echo json_encode(["success" => false, "message" => "status must be recorded, verified, or flagged"]);
    exit;
}
if ($status === 'flagged' && $reason === '') {
    echo json_encode(["success" => false, "message" => "A reason is required when flagging a fuel record"]);
    exit;
}

$distance = ($startOdo !== null && $endOdo !== null) ? ($endOdo - $startOdo) : null;
$expectedFuel = ($distance !== null && $busKpl && $busKpl > 0) ? ($distance / $busKpl) : null;
$actualKpl = ($distance !== null && $distance > 0 && $fuelLiters > 0) ? ($distance / $fuelLiters) : null;
$fuelCost = $fuelLiters * $price;
$variance = ($expectedFuel !== null) ? ($fuelLiters - $expectedFuel) : null;
$variancePct = ($expectedFuel !== null && $expectedFuel > 0) ? (($fuelLiters - $expectedFuel) / $expectedFuel) * 100 : null;

$reason = $reason === '' ? null : $reason;
$receiptNumber = $receiptNumber === '' ? null : $receiptNumber;
$receiptImage = $receiptImage === '' ? null : $receiptImage;
$notes = $notes === '' ? null : $notes;

try {
    if ($recordId > 0) {
        // Update existing record (e.g. save edits or Verify/Flag decision).
        $stmt = $conn->prepare("
            UPDATE inspector_fuel_records
            SET bus_id = ?, driver_id = ?, start_odo = ?, end_odo = ?,
                fuel_liters = ?, price_per_liter = ?, bus_kpl = ?,
                fuel_cost = ?, receipt_number = ?, receipt_image = ?, notes = ?,
                distance = ?, expected_fuel = ?, actual_kpl = ?,
                variance = ?, variance_pct = ?, status = ?, reason = ?
            WHERE record_id = ? AND inspector_id = ?
        ");
        if (!$stmt) {
            throw new Exception("Failed to prepare fuel record update");
        }
        $stmt->bind_param(
            "ssddddddsssdddddssis",
            $busId, $driverId, $startOdo, $endOdo,
            $fuelLiters, $price, $busKpl,
            $fuelCost, $receiptNumber, $receiptImage, $notes,
            $distance, $expectedFuel, $actualKpl,
            $variance, $variancePct, $status, $reason,
            $recordId, $inspectorId
        );
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $insertId = $recordId;
        } else {
            // Nothing changed or record not found; confirm ownership.
            $insertId = $recordId;
        }
        $stmt->close();
        $message = $status === 'flagged' ? "Fuel record flagged for review" : ($status === 'verified' ? "Fuel record verified" : "Fuel record saved");
    } else {
        // Insert a new record.
        $stmt = $conn->prepare("
            INSERT INTO inspector_fuel_records
                (inspector_id, bus_id, driver_id, start_odo, end_odo,
                 fuel_liters, price_per_liter, bus_kpl, fuel_cost,
                 receipt_number, receipt_image, notes, distance, expected_fuel, actual_kpl,
                 variance, variance_pct, status, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            throw new Exception("Failed to prepare fuel record insert");
        }
        $stmt->bind_param(
            "sssddddddsssdddddss",
            $inspectorId, $busId, $driverId, $startOdo, $endOdo,
            $fuelLiters, $price, $busKpl, $fuelCost,
            $receiptNumber, $receiptImage, $notes, $distance, $expectedFuel, $actualKpl,
            $variance, $variancePct, $status, $reason
        );
        $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();
        $message = $status === 'flagged' ? "Fuel record flagged for review" : ($status === 'verified' ? "Fuel record verified" : "Fuel record saved");
    }

    echo json_encode([
        "success" => true,
        "message" => $message,
        "record_id" => $insertId,
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

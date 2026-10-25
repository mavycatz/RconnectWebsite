<?php
/**
 * get_fuel_verifications.php
 * Returns any existing inspector verification for a given fuel record
 * (fuel_receipt id) so previously reviewed records show their prior decision.
 *
 * Usage (GET):
 *   ?fuel_receipt_id=12
 *
 * Response envelope:
 *   { success, verification: { ... } | null, message }
 */

header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

$db = new Database();
$conn = $db->connect();

$fuelReceiptId = (int)($_GET['fuel_receipt_id'] ?? 0);

if ($fuelReceiptId <= 0) {
    echo json_encode(["success" => false, "message" => "fuel_receipt_id is required"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        fv.verification_id,
        fv.fuel_receipt_id,
        fv.inspector_id,
        fv.start_odo,
        fv.end_odo,
        fv.bus_kpl,
        fv.distance,
        fv.expected_fuel,
        fv.actual_fuel,
        fv.actual_kpl,
        fv.fuel_cost,
        fv.variance,
        fv.status,
        fv.reason,
        fv.created_at
    FROM fuel_verifications fv
    WHERE fv.fuel_receipt_id = ?
    ORDER BY fv.verification_id DESC
    LIMIT 1
");

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Failed to load verification"]);
    exit;
}

$stmt->bind_param("i", $fuelReceiptId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(["success" => true, "verification" => null]);
    exit;
}

$row['verification_id'] = (int)$row['verification_id'];
$row['fuel_receipt_id'] = (int)$row['fuel_receipt_id'];
$row['start_odo'] = is_null($row['start_odo']) ? null : (float)$row['start_odo'];
$row['end_odo'] = is_null($row['end_odo']) ? null : (float)$row['end_odo'];
$row['bus_kpl'] = is_null($row['bus_kpl']) ? null : (float)$row['bus_kpl'];
$row['distance'] = is_null($row['distance']) ? null : (float)$row['distance'];
$row['expected_fuel'] = is_null($row['expected_fuel']) ? null : (float)$row['expected_fuel'];
$row['actual_fuel'] = is_null($row['actual_fuel']) ? null : (float)$row['actual_fuel'];
$row['actual_kpl'] = is_null($row['actual_kpl']) ? null : (float)$row['actual_kpl'];
$row['fuel_cost'] = is_null($row['fuel_cost']) ? null : (float)$row['fuel_cost'];
$row['variance'] = is_null($row['variance']) ? null : (float)$row['variance'];

echo json_encode(["success" => true, "verification" => $row]);

$conn->close();

<?php
/**
 * get_fuel_records.php
 * Returns the Inspector's saved fuel records (newest first) and the Inspector's
 * currently assigned bus/driver (from the active Departed trip) for
 * pre-selection on the Fuel Recording screen.
 *
 * Usage (GET):
 *   ?inspector_id=5
 *
 * Response envelope:
 *   {
 *     success,
 *     assigned: { bus_id, plate_number, driver_id, driver_name } | null,
 *    records: [ { record_id, bus_id, plate_number, driver_id, driver_name,
 *                  start_odo, end_odo, fuel_liters, price_per_liter, fuel_cost,
 *                  receipt_number, receipt_image, notes, distance, expected_fuel, bus_kpl,
 *                  actual_kpl, variance, variance_pct, status, reason, created_at } ]
 *   }
 */

header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

$inspectorId = trim($_GET['inspector_id'] ?? '');

if (empty($inspectorId)) {
    echo json_encode(["success" => false, "message" => "inspector_id is required"]);
    exit;
}

try {
    // Resolve the assigned bus/driver from the active (Departed) trip.
    $assigned = null;
    $tripStmt = $conn->prepare("
        SELECT
            bt.bus_id,
            bt.driver_id,
            b.plate_number,
            d.full_name AS driver_name
        FROM inspector_assignments ia
        INNER JOIN bus_trips bt ON bt.id = ia.trip_id
        LEFT JOIN buses b ON b.bus_id = bt.bus_id
        LEFT JOIN drivers d ON d.driver_id = bt.driver_id
        WHERE ia.inspector_id = ?
          AND ia.status = 'Active'
          AND bt.status = 'Departed'
          AND bt.trip_date = CURDATE()
        ORDER BY bt.id DESC
        LIMIT 1
    ");
    if ($tripStmt) {
        $tripStmt->bind_param("s", $inspectorId);
        $tripStmt->execute();
        $trip = $tripStmt->get_result()->fetch_assoc();
        $tripStmt->close();
        if ($trip && !empty($trip['bus_id'])) {
            $assigned = [
                "bus_id" => $trip['bus_id'],
                "plate_number" => $trip['plate_number'] ?? '',
                "driver_id" => $trip['driver_id'] ?? '',
                "driver_name" => $trip['driver_name'] ?? '',
            ];
        }
    }

    // List the Inspector's saved fuel records, newest first.
    $stmt = $conn->prepare("
        SELECT
            fr.record_id,
            fr.bus_id,
            fr.driver_id,
            b.plate_number,
            d.full_name AS driver_name,
            fr.start_odo,
            fr.end_odo,
            fr.fuel_liters,
            fr.price_per_liter,
            fr.fuel_cost,
            fr.receipt_number,
            fr.receipt_image,
            fr.notes,
            fr.distance,
            fr.expected_fuel,
            fr.bus_kpl,
            fr.actual_kpl,
            fr.variance,
            fr.variance_pct,
            fr.status,
            fr.reason,
            fr.created_at
        FROM inspector_fuel_records fr
        LEFT JOIN buses b ON b.bus_id = fr.bus_id
        LEFT JOIN drivers d ON d.driver_id = fr.driver_id
        WHERE fr.inspector_id = ?
        ORDER BY fr.created_at DESC, fr.record_id DESC
    ");
    if (!$stmt) {
        throw new Exception("Failed to prepare fuel records query");
    }
    $stmt->bind_param("s", $inspectorId);
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = [
            "record_id" => (int)$row['record_id'],
            "bus_id" => $row['bus_id'],
            "plate_number" => $row['plate_number'] ?? '',
            "driver_id" => $row['driver_id'],
            "driver_name" => $row['driver_name'] ?? '',
            "start_odo" => is_null($row['start_odo']) ? null : (float)$row['start_odo'],
            "end_odo" => is_null($row['end_odo']) ? null : (float)$row['end_odo'],
            "fuel_liters" => (float)$row['fuel_liters'],
            "price_per_liter" => (float)$row['price_per_liter'],
            "fuel_cost" => is_null($row['fuel_cost']) ? null : (float)$row['fuel_cost'],
            "receipt_number" => $row['receipt_number'],
            "receipt_image" => $row['receipt_image'],
            "notes" => $row['notes'],
            "distance" => is_null($row['distance']) ? null : (float)$row['distance'],
            "expected_fuel" => is_null($row['expected_fuel']) ? null : (float)$row['expected_fuel'],
            "bus_kpl" => is_null($row['bus_kpl']) ? null : (float)$row['bus_kpl'],
            "actual_kpl" => is_null($row['actual_kpl']) ? null : (float)$row['actual_kpl'],
            "variance" => is_null($row['variance']) ? null : (float)$row['variance'],
            "variance_pct" => is_null($row['variance_pct']) ? null : (float)$row['variance_pct'],
            "status" => $row['status'],
            "reason" => $row['reason'],
            "created_at" => $row['created_at'],
        ];
    }
    $stmt->close();

    echo json_encode([
        "success" => true,
        "assigned" => $assigned,
        "records" => $records,
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

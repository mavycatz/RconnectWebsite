<?php

header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

$bus_id = trim($data['bus_id'] ?? $data['bus_no'] ?? '');
$driver_id = trim($data['driver_id'] ?? '');
$token = $data['token'] ?? '';

require_once __DIR__ . '/lib/driver_auth.php';
require_driver_auth($conn, $driver_id, $token);

$stop_name = trim($data['stop_name'] ?? $data['destination'] ?? '');
$passenger_ids = $data['passenger_ids'] ?? [];

$missing = [];
if (empty($bus_id))        $missing[] = 'bus_id';
if (empty($driver_id))     $missing[] = 'driver_id';
if (empty($stop_name))     $missing[] = 'stop_name';
if (empty($passenger_ids) || !is_array($passenger_ids)) $missing[] = 'passenger_ids (non-empty array)';

if (!empty($missing)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields: " . implode(', ', $missing),
        "debug" => ["missing" => $missing]
    ]);
    exit;
}

$passenger_ids = array_values(array_unique(array_map('intval', $passenger_ids)));
$passenger_ids = array_filter($passenger_ids, fn($id) => $id > 0);

if (empty($passenger_ids)) {
    echo json_encode(["success" => false, "message" => "No valid passenger_ids provided"]);
    exit;
}

/*
|--------------------------------------------------------------------------
| 1. GET ACTIVE TRIP FOR THE DRIVER
|--------------------------------------------------------------------------
*/

$tripStmt = $conn->prepare("
    SELECT id FROM bus_trips
    WHERE driver_id = ? AND status IN ('Departed', 'Started', 'Active', 'Ongoing')
    ORDER BY id DESC
    LIMIT 1
");
if (!$tripStmt) {
    echo json_encode(["success" => false, "message" => "bus_trips query failed: " . $conn->error]);
    exit;
}
$tripStmt->bind_param("s", $driver_id);
$tripStmt->execute();
$tripResult = $tripStmt->get_result();
$activeTrip = $tripResult->fetch_assoc();
$tripStmt->close();

if (!$activeTrip) {
    echo json_encode([
        "success" => false,
        "message" => "No active trip found for driver"
    ]);
    exit;
}

$activeTripId = $activeTrip['id'];

/*
|--------------------------------------------------------------------------
| 2. FETCH THE PASSENGERS AND FILTER ELIGIBILITY
|   (current trip, Cash, Pending, not yet in a batch, matching destination)
|--------------------------------------------------------------------------
*/

$placeholders = implode(',', array_fill(0, count($passenger_ids), '?'));
$types = str_repeat('i', count($passenger_ids));

$stmt = $conn->prepare("
    SELECT id, trip_no, bus_no, driver_id, payment_method, payment_status,
           destination, fare, batch_id
    FROM passenger_trips
    WHERE id IN ($placeholders)
");
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "passenger_trips query failed: " . $conn->error]);
    exit;
}

$stmt->bind_param($types, ...$passenger_ids);
$stmt->execute();
$result = $stmt->get_result();

$eligible = [];
$excluded = [];

while ($row = $result->fetch_assoc()) {
    $rowId = (int)$row['id'];

    if ((int)$row['trip_no'] !== (int)$activeTripId) {
        $excluded[] = ['id' => $rowId, 'reason' => 'not_current_trip'];
        continue;
    }
    if ($row['bus_no'] !== $bus_id) {
        $excluded[] = ['id' => $rowId, 'reason' => 'wrong_bus'];
        continue;
    }
    if (strtolower($row['payment_method']) !== 'cash') {
        $excluded[] = ['id' => $rowId, 'reason' => 'not_cash'];
        continue;
    }
    if (strtolower($row['payment_status']) !== 'pending') {
        $excluded[] = ['id' => $rowId, 'reason' => 'already_paid'];
        continue;
    }
    if ($row['batch_id'] !== null) {
        $excluded[] = ['id' => $rowId, 'reason' => 'already_in_batch'];
        continue;
    }
    if (strtolower(trim($row['destination'])) !== strtolower(trim($stop_name))) {
        $excluded[] = ['id' => $rowId, 'reason' => 'different_destination'];
        continue;
    }

    $eligible[] = [
        'id' => $rowId,
        'fare' => (float)$row['fare']
    ];
}

$stmt->close();

if (empty($eligible)) {
    echo json_encode([
        "success" => false,
        "message" => "No eligible cash passengers found for this stop",
        "eligible_count" => 0,
        "excluded" => $excluded
    ]);
    exit;
}

$expected_amount = round(array_sum(array_column($eligible, 'fare')), 2);
$eligible_ids = array_column($eligible, 'id');
$passenger_count = count($eligible_ids);

/*
|--------------------------------------------------------------------------
| 3. INSERT BATCH + MARK PASSENGERS PAID IN ONE TRANSACTION
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    $insertStmt = $conn->prepare("
        INSERT INTO cash_batches
        (trip_id, driver_id, stop_name, passenger_count, expected_amount, status, collected_at)
        VALUES (?, ?, ?, ?, ?, 'collected', NOW())
    ");
    if (!$insertStmt) throw new Exception("cash_batches insert prepare failed: " . $conn->error);
    $insertStmt->bind_param("issid", $activeTripId, $driver_id, $stop_name, $passenger_count, $expected_amount);
    if (!$insertStmt->execute()) throw new Exception("cash_batches insert failed: " . $insertStmt->error);
    $batch_id = $insertStmt->insert_id;
    $insertStmt->close();

    $updateStmt = $conn->prepare("
        UPDATE passenger_trips
        SET payment_status = 'Paid', batch_id = ?
        WHERE id = ?
    ");
    if (!$updateStmt) throw new Exception("passenger_trips update prepare failed: " . $conn->error);

    $updated_ids = [];
    foreach ($eligible_ids as $pid) {
        $updateStmt->bind_param("ii", $batch_id, $pid);
        if (!$updateStmt->execute()) throw new Exception("passenger_trips update failed for id $pid: " . $updateStmt->error);
        if ($updateStmt->affected_rows > 0) $updated_ids[] = $pid;
    }
    $updateStmt->close();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Batch collection recorded successfully",
        "batch" => [
            "batch_id" => $batch_id,
            "trip_id" => $activeTripId,
            "driver_id" => $driver_id,
            "stop_name" => $stop_name,
            "passenger_count" => $passenger_count,
            "expected_amount" => $expected_amount,
            "status" => "collected"
        ],
        "updated_passenger_ids" => $updated_ids,
        "excluded" => $excluded
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();

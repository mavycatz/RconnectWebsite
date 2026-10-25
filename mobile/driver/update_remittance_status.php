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

$trip_ids = $data['trip_ids'] ?? null;
$remittance_status = trim($data['remittance_status'] ?? '');
$actual_cash = $data['actual_cash'] ?? null;
$remitted_by = trim($data['remitted_by'] ?? '');
$reviewed_by = isset($data['reviewed_by']) ? trim($data['reviewed_by']) : null;
$finance_note = isset($data['finance_note']) ? trim($data['finance_note']) : null;

$missing = [];
if (empty($trip_ids) || !is_array($trip_ids)) $missing[] = 'trip_ids (non-empty array)';
if (empty($remittance_status)) $missing[] = 'remittance_status';
if ($actual_cash === null || !is_numeric($actual_cash)) $missing[] = 'actual_cash (decimal)';
if (empty($remitted_by)) $missing[] = 'remitted_by';

if (!empty($missing)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields: " . implode(', ', $missing)
    ]);
    exit;
}

$validStatuses = ['Pending', 'Remitted', 'Discrepancy'];
if (!in_array($remittance_status, $validStatuses)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid remittance_status. Must be one of: " . implode(', ', $validStatuses)
    ]);
    exit;
}

$actual_cash = round((float)$actual_cash, 2);
$updated_count = 0;
$skipped_ids = [];

$has_reviewed_by = false;
$colCheck = $conn->query("
    SELECT COUNT(*) AS c FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'passenger_trips'
      AND COLUMN_NAME = 'reviewed_by'
");
if ($colCheck) {
    $colRow = $colCheck->fetch_assoc();
    $has_reviewed_by = (int)$colRow['c'] > 0;
}

$conn->begin_transaction();

try {
    $stmt_check = $conn->prepare("SELECT id, expected_cash FROM passenger_trips WHERE id = ?");
    if (!$stmt_check) {
        throw new Exception("Check query prepare failed: " . $conn->error);
    }

    if ($has_reviewed_by) {
        $stmt_update = $conn->prepare("
            UPDATE passenger_trips
            SET
                remittance_status = ?,
                actual_cash = ?,
                discrepancy_amount = expected_cash - ?,
                remitted_by = ?,
                remitted_at = NOW(),
                reviewed_by = COALESCE(?, reviewed_by),
                finance_note = COALESCE(?, finance_note)
            WHERE id = ?
        ");
    } else {
        $stmt_update = $conn->prepare("
            UPDATE passenger_trips
            SET
                remittance_status = ?,
                actual_cash = ?,
                discrepancy_amount = expected_cash - ?,
                remitted_by = ?,
                remitted_at = NOW(),
                finance_note = COALESCE(?, finance_note)
            WHERE id = ?
        ");
    }
    if (!$stmt_update) {
        throw new Exception("Update query prepare failed: " . $conn->error);
    }

    foreach ($trip_ids as $tid) {
        $tid = (int)$tid;
        if ($tid <= 0) continue;

        $stmt_check->bind_param("i", $tid);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();

        if ($check_result->num_rows === 0) {
            $skipped_ids[] = $tid;
            continue;
        }

        if ($has_reviewed_by) {
            $stmt_update->bind_param("sdddsssi",
                $remittance_status,
                $actual_cash,
                $actual_cash,
                $remitted_by,
                $reviewed_by,
                $finance_note,
                $tid
            );
        } else {
            $stmt_update->bind_param("sddssi",
                $remittance_status,
                $actual_cash,
                $actual_cash,
                $remitted_by,
                $finance_note,
                $tid
            );
        }

        if (!$stmt_update->execute()) {
            throw new Exception("Update failed for trip ID $tid: " . $stmt_update->error);
        }
        $updated_count++;
    }

    $stmt_check->close();
    $stmt_update->close();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Remittance status updated successfully",
        "updated_count" => $updated_count,
        "skipped_ids" => $skipped_ids
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();

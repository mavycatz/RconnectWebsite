<?php

/*
|--------------------------------------------------------------------------
| DRIVER MISSED / PASSED DESTINATION MARKER (drop-off flow)
|
| Records the driver's "Passenger Still Onboard" decision for one existing
| passenger_trips record owned by the authenticated driver after the bus has
| passed the passenger's selected destination.
|
| - Only the destination status columns are written; the selected destination,
|   fare, payment status, and drop-off state are never changed here.
| - The action, timestamp, and identity are preserved in
|   missed_destination_audit for accountability.
| - Retried/duplicated submissions for the same identity_key are idempotent.
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {

    require_once __DIR__ . "/../config/database.php";
    require_once __DIR__ . '/lib/driver_auth.php';
    require_once __DIR__ . '/lib/active_trip.php';

    $db = new Database();
    $conn = $db->connect();
    if (!$conn) throw new Exception("Database connection failed");

    $input = json_decode(file_get_contents("php://input"), true);

    $action        = strtolower(trim($input['action'] ?? 'still_onboard'));
    $txId          = trim($input['tx_id'] ?? '');
    $driverId      = trim($input['driver_id'] ?? '');
    $token         = trim($input['token'] ?? '');
    $passengerCode = trim($input['passenger_code'] ?? '');
    $remark        = trim($input['remark'] ?? '');
    $unverified    = (int)($input['unverified'] ?? 0) === 1 ? 1 : 0;

    require_driver_auth($conn, $driverId, $token);

    if ($action !== 'still_onboard') {
        echo json_encode(["success" => false, "message" => "Unsupported action"]);
        exit;
    }

    if ($passengerCode === '') {
        echo json_encode(["success" => false, "message" => "passenger_code is required"]);
        exit;
    }

    // Idempotency: a retried marker with the same tx_id is reported as already
    // recorded instead of writing a duplicate audit row.
    if ($txId !== '') {
        $checkTx = $conn->prepare("SELECT id FROM qr_scan_transactions WHERE tx_id = ? LIMIT 1");
        if ($checkTx) {
            $checkTx->bind_param("s", $txId);
            $checkTx->execute();
            $existingTx = $checkTx->get_result()->fetch_assoc();
            $checkTx->close();
            if ($existingTx) {
                echo json_encode([
                    "success" => true,
                    "already_recorded" => true,
                    "message" => "Passed destination already recorded",
                    "tx_id" => $txId,
                ]);
                exit;
            }
        }
    }

    // The passed-destination columns arrive with the
    // add_missed_destination migration. Detect them once so the endpoint
    // reports the deployment gap instead of failing with a SQL error.
    $hasMissedDestinationColumns = false;
    $chkPtCols = $conn->query("SHOW COLUMNS FROM passenger_trips");
    if ($chkPtCols) {
        while ($c = $chkPtCols->fetch_assoc()) {
            if ($c['Field'] === 'destination_status') $hasMissedDestinationColumns = true;
        }
    }
    if (!$hasMissedDestinationColumns) {
        echo json_encode([
            "success" => false,
            "code" => "MIGRATION_REQUIRED",
            "message" => "Missed destination handling is not available yet. Please contact the administrator.",
        ]);
        exit;
    }

    $activeTrip = resolve_active_trip($conn, $driverId);
    if (!$activeTrip) {
        echo json_encode([
            "success" => false,
            "code" => "NO_ACTIVE_TRIP",
            "message" => "No active trip found",
        ]);
        exit;
    }
    $tripNo = (string)$activeTrip['id'];
    $routeName = $activeTrip['route_name'];
    $busNo = (string)($activeTrip['bus_id'] ?? '');

    if ($unverified) {
        $activeStmt = $conn->prepare(
            "SELECT pt.id, pt.passenger_id, pt.passenger_code, pt.destination, pt.dropoff_time
             FROM passenger_trips pt
             WHERE pt.trip_no = ? AND pt.driver_id = ? AND pt.unverified = 1
               AND (pt.dropoff_time IS NULL OR pt.dropoff_time = '') AND pt.archived_status = 'no'
             ORDER BY pt.id ASC LIMIT 1"
        );
        if (!$activeStmt) throw new Exception("passenger_trips query failed: " . $conn->error);
        $activeStmt->bind_param("ss", $tripNo, $driverId);
    } else {
        $activeStmt = $conn->prepare(
            "SELECT pt.id, pt.passenger_id, pt.passenger_code, pt.destination, pt.dropoff_time
             FROM passenger_trips pt
             WHERE (pt.passenger_code = ? OR pt.passenger_id = ?)
               AND pt.trip_no = ? AND pt.driver_id = ?
               AND (pt.dropoff_time IS NULL OR pt.dropoff_time = '') AND pt.archived_status = 'no'
             ORDER BY pt.id DESC LIMIT 1"
        );
        if (!$activeStmt) throw new Exception("passenger_trips query failed: " . $conn->error);
        $activeStmt->bind_param("siss", $passengerCode, $passengerCode, $tripNo, $driverId);
    }
    $activeStmt->execute();
    $activeRow = $activeStmt->get_result()->fetch_assoc();
    $activeStmt->close();

    if (!$activeRow) {
        $doneStmt = $unverified
            ? $conn->prepare(
                "SELECT id FROM passenger_trips
                 WHERE trip_no = ? AND driver_id = ? AND unverified = 1
                   AND ((dropoff_time IS NOT NULL AND dropoff_time <> '') OR archived_status = 'yes')
                 LIMIT 1"
            )
            : $conn->prepare(
                "SELECT id FROM passenger_trips
                 WHERE (passenger_code = ? OR passenger_id = ?) AND trip_no = ? AND driver_id = ?
                   AND ((dropoff_time IS NOT NULL AND dropoff_time <> '') OR archived_status = 'yes')
                 LIMIT 1"
            );
        $state = 'completed';
        if ($doneStmt) {
            if ($unverified) {
                $doneStmt->bind_param("ss", $tripNo, $driverId);
            } else {
                $doneStmt->bind_param("siss", $passengerCode, $passengerCode, $tripNo, $driverId);
            }
            $doneStmt->execute();
            $doneRow = $doneStmt->get_result()->fetch_assoc();
            $doneStmt->close();
            $state = $doneRow ? 'completed' : 'none';
        }
        echo json_encode([
            "success" => false,
            "code" => $state === 'completed' ? "ALREADY_COMPLETED" : "NO_ACTIVE_BOARDING",
            "message" => $state === 'completed'
                ? "Passenger trip already completed"
                : "No active boarding found for this passenger",
            "state" => $state,
        ]);
        exit;
    }

    $recordId = (int)$activeRow['id'];
    $codeForLedger = $activeRow['passenger_code'] ?? $passengerCode;
    $destination = $activeRow['destination'] ?? null;
    $auditRemark = $remark !== '' ? $remark : 'Passenger kept onboard after destination passed';

    $conn->begin_transaction();

    try {
        $upStmt = $conn->prepare(
            "UPDATE passenger_trips
             SET destination_status = 'destination_passed',
                 destination_passed_at = NOW(),
                 missed_destination_action = 'still_onboard'
             WHERE id = ?"
        );
        if (!$upStmt) throw new Exception("passenger_trips update failed: " . $conn->error);
        $upStmt->bind_param("i", $recordId);
        if (!$upStmt->execute()) throw new Exception("passenger_trips update failed: " . $upStmt->error);
        $upStmt->close();

        // Accountability: one audit row per handled pass.
        try {
            $auditTable = $conn->query("SHOW TABLES LIKE 'missed_destination_audit'");
            if ($auditTable && $auditTable->num_rows > 0) {
                $auditStmt = $conn->prepare(
                    "INSERT INTO missed_destination_audit
                     (passenger_trip_id, passenger_id, passenger_code, driver_id, trip_no, bus_no,
                      destination, actual_dropoff_point, latitude, longitude, location_status,
                      action, remark, identity_key, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, 'still_onboard', ?, ?, NOW())"
                );
                if ($auditStmt) {
                    $auditPassengerId = isset($activeRow['passenger_id']) ? (int)$activeRow['passenger_id'] : null;
                    $auditStmt->bind_param(
                        "iisssssss",
                        $recordId,
                        $auditPassengerId,
                        $codeForLedger,
                        $driverId,
                        $tripNo,
                        $busNo,
                        $destination,
                        $auditRemark,
                        $txId
                    );
                    $auditStmt->execute();
                    $auditStmt->close();
                }
            }
        } catch (Exception $e) {
            // audit is best-effort; never block the marker
        }

        if ($txId !== '') {
            $ledger = $conn->prepare("INSERT INTO qr_scan_transactions (tx_id, action, passenger_code, trip_no, record_id) VALUES (?, ?, ?, ?, ?)");
            if ($ledger) {
                $ledgerAction = 'destination_passed';
                $ledger->bind_param("ssssi", $txId, $ledgerAction, $codeForLedger, $tripNo, $recordId);
                $ledger->execute();
                $ledger->close();
            }
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    echo json_encode([
        "success" => true,
        "message" => "Passenger kept onboard; destination unchanged",
        "state" => "destination_passed",
        "action" => "still_onboard",
        "passenger_code" => $codeForLedger,
        "destination" => $destination,
        "tx_id" => $txId,
    ]);

    $conn->close();

} catch (Exception $e) {
    error_log("[missed_destination] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "code" => "SERVER_ERROR",
        "message" => "Something went wrong. Please try again.",
    ]);
}

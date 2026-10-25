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

$action = strtolower(trim($data['action'] ?? ''));
$batch_id = isset($data['batch_id']) ? (int)$data['batch_id'] : 0;
$bus_id = trim($data['bus_id'] ?? $data['bus_no'] ?? '');
$driver_id = trim($data['driver_id'] ?? '');
$token = $data['token'] ?? '';

require_once __DIR__ . '/lib/driver_auth.php';
require_driver_auth($conn, $driver_id, $token);

$stop_name = trim($data['stop_name'] ?? $data['destination'] ?? '');
$route_stop_id = isset($data['route_stop_id']) ? (int)$data['route_stop_id'] : 0;
$collected_amount = isset($data['collected_amount']) ? (float)$data['collected_amount'] : 0.0;

if (!in_array($action, ['collect', 'cancel'], true)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid action. Must be 'collect' or 'cancel'"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| CANCEL = NO-OP (drafts live client-side only; nothing to cancel server-side)
|--------------------------------------------------------------------------
*/

if ($action === 'cancel') {
    echo json_encode([
        "success" => true,
        "message" => "No action taken",
        "batch_id" => $batch_id,
        "status" => "cancelled_noop",
        "server_version" => "1.4.3"
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| COLLECT - record the actual cash collected for a batch
|--------------------------------------------------------------------------
*/

if (empty($driver_id) || empty($bus_id) || empty($stop_name)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields: driver_id, bus_id, stop_name"
    ]);
    exit;
}

if ($collected_amount <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "collected_amount must be greater than zero"
    ]);
    exit;
}

$conn->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | 1. RESOLVE THE ACTIVE TRIP
    |--------------------------------------------------------------------------
    */

    $tripStmt = $conn->prepare("
        SELECT id, route_name FROM bus_trips
        WHERE driver_id = ? AND status IN ('Departed', 'Started', 'Active', 'Ongoing')
        ORDER BY id DESC
        LIMIT 1
    ");
    if (!$tripStmt) throw new Exception("bus_trips query failed: " . $conn->error);
    $tripStmt->bind_param("s", $driver_id);
    $tripStmt->execute();
    $activeTrip = $tripStmt->get_result()->fetch_assoc();
    $tripStmt->close();

    if (!$activeTrip) {
        throw new Exception("No active trip found for driver");
    }
    $activeTripId = (int)$activeTrip['id'];
    $tripRouteName = $activeTrip['route_name'] ?? '';

    require_once __DIR__ . "/lib/actual_destination.php";

    /*
    |--------------------------------------------------------------------------
    | 2. RESOLVE THE TARGET ROUTE STOP (the ACTUAL stop for this batch)
    |    Validates client-sent route_stop_id/stop_name against the active
    |    trip's route; otherwise resolves from the driver's latest GPS.
    |--------------------------------------------------------------------------
    */

    $targetStop = null;

    if ($route_stop_id > 0) {
        $rsStmt = $conn->prepare("
            SELECT id, route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id
            FROM route_stops WHERE id = ?
        ");
        if (!$rsStmt) throw new Exception("route_stops lookup prepare failed: " . $conn->error);
        $rsStmt->bind_param("i", $route_stop_id);
        $rsStmt->execute();
        $targetStop = $rsStmt->get_result()->fetch_assoc();
        $rsStmt->close();

        if ($targetStop) {
            $tripRouteStopsName = route_stops_route_name_for_trip($conn, $tripRouteName);
            if (strtolower(trim($targetStop['route_name'] ?? '')) !== strtolower(trim($tripRouteStopsName ?? ''))) {
                $targetStop = null; // stop from a different route — not valid for this trip
            }
        }
    }

    if (!$targetStop && $stop_name !== '') {
        $routeStopsRouteName = route_stops_route_name_for_trip($conn, $tripRouteName);
        if ($routeStopsRouteName) {
            $rsStmt = $conn->prepare("
                SELECT id, route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id
                FROM route_stops WHERE route_name = ? AND stop_name = ? LIMIT 1
            ");
            if (!$rsStmt) throw new Exception("route_stops by name prepare failed: " . $conn->error);
            $rsStmt->bind_param("ss", $routeStopsRouteName, $stop_name);
            $rsStmt->execute();
            $targetStop = $rsStmt->get_result()->fetch_assoc();
            $rsStmt->close();
        }
    }

    if (!$targetStop) {
        // Fallback: resolve the driver's upcoming stop from GPS (authoritative server-side)
        $targetStop = resolve_driver_upcoming_stop($conn, $driver_id, $tripRouteName);
    }

    if (!$targetStop) {
        throw new Exception("Could not resolve an upcoming route stop for this trip");
    }
    $stop_name = $targetStop['stop_name']; // ALWAYS the actual route stop name

    /*
    |--------------------------------------------------------------------------
    | 2. RESOLVE THE BATCH: explicit batch_id, otherwise the open batch for
    |    this trip + stop. A partially collected batch accumulates further
    |    collections; a fully collected batch rejects new amounts.
    |--------------------------------------------------------------------------
    */

    $batch = null;

    if ($batch_id > 0) {
        $batchQuery = $conn->prepare("SELECT * FROM cash_batches WHERE batch_id = ?");
        if (!$batchQuery) throw new Exception("batch lookup prepare failed: " . $conn->error);
        $batchQuery->bind_param("i", $batch_id);
        $batchQuery->execute();
        $batch = $batchQuery->get_result()->fetch_assoc();
        $batchQuery->close();

        if (!$batch) {
            throw new Exception("Batch $batch_id not found");
        }
        if ((int)$batch['trip_id'] !== $activeTripId) {
            throw new Exception("Batch does not belong to the driver's active trip");
        }
    } else {
        $openStmt = $conn->prepare("
            SELECT * FROM cash_batches
            WHERE trip_id = ? AND driver_id = ? AND stop_name = ? AND status <> 'cancelled'
            ORDER BY batch_id DESC
            LIMIT 1
        ");
        if (!$openStmt) throw new Exception("open batch lookup prepare failed: " . $conn->error);
        $openStmt->bind_param("iss", $activeTripId, $driver_id, $stop_name);
        $openStmt->execute();
        $batch = $openStmt->get_result()->fetch_assoc();
        $openStmt->close();
    }

    if ($batch) {
        $batchId = (int)$batch['batch_id'];
        $batchStatus = strtolower(trim($batch['status']));
        if ($batchStatus === 'fully_collected') {
            if ($batch_id > 0) {
                throw new Exception("Batch is already fully collected");
            }
            // No explicit batch_id: the most recent batch for this stop is done,
            // but new passengers may have boarded since. Treat it as no open
            // batch so a fresh batch is created for them instead of rejecting.
            $batch = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3. CREATE THE BATCH IF IT DOES NOT EXIST YET
    |    Eligible passengers: current trip, this bus, cash, pending, not yet in
    |    a batch, same destination stop. Passengers are linked via batch_id for
    |    grouping only. Individual passengers are only marked Paid when the FULL
    |    expected amount has been collected - the driver cannot identify who
    |    paid when the collection is partial.
    |--------------------------------------------------------------------------
    */

    if (!$batch) {
        $passStmt = $conn->prepare("
            SELECT id, trip_no, bus_no, payment_method, payment_status,
                   destination, boarding_point, fare, batch_id
            FROM passenger_trips
            WHERE trip_no = ? AND bus_no = ? AND (dropoff_time IS NULL OR LOWER(payment_status) = 'pending') AND archived_status = 'no'
        ");
        if (!$passStmt) throw new Exception("passenger_trips query failed: " . $conn->error);
        $passStmt->bind_param("is", $activeTripId, $bus_id);
        $passStmt->execute();
        $result = $passStmt->get_result();

        $eligible = [];
        while ($row = $result->fetch_assoc()) {
            // Hard filters: current trip cash passenger, still pending, unbatched
            if (strtolower($row['payment_method']) !== 'cash') continue;
            if (strtolower($row['payment_status']) !== 'pending') continue;
            if ($row['batch_id'] !== null) continue;

            // ACTUAL destination (route stop) — server-resolved, never from client
            $actualStop = resolve_passenger_actual_stop($conn, $row, $tripRouteName);
            if (!$actualStop) continue;                       // no valid actual destination
            if ((int)$actualStop['id'] !== (int)$targetStop['id']) continue; // not this stop

            $finalFare = compute_final_fare(
                $conn,
                $tripRouteName,
                $row['boarding_point'] ?? '',
                $actualStop['stop_name'],
                null,
                isset($row['fare']) ? $row['fare'] : null,
                $row['destination'] ?? null
            );
            if ($finalFare === null) continue;

            $eligible[] = ['id' => (int)$row['id'], 'fare' => (float)$finalFare];
        }
        $passStmt->close();

        if (empty($eligible)) {
            throw new Exception("No eligible cash passengers found for this stop");
        }

        $expected_amount = round(array_sum(array_column($eligible, 'fare')), 2);
        $eligible_ids = array_column($eligible, 'id');
        $passenger_count = count($eligible_ids);
        $new_total = min(round($collected_amount, 2), $expected_amount);
        $new_status = $new_total >= $expected_amount ? 'fully_collected' : 'partially_collected';

        $insertStmt = $conn->prepare("
            INSERT INTO cash_batches
            (trip_id, driver_id, stop_name, passenger_count, expected_amount, collected_amount, status, collected_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$insertStmt) throw new Exception("cash_batches insert prepare failed: " . $conn->error);
        $insertStmt->bind_param("issidds", $activeTripId, $driver_id, $stop_name, $passenger_count, $expected_amount, $new_total, $new_status);
        if (!$insertStmt->execute()) throw new Exception("cash_batches insert failed: " . $insertStmt->error);
        $batchId = $insertStmt->insert_id;
        $insertStmt->close();

        $placeholders = implode(',', array_fill(0, count($eligible_ids), '?'));
        $types = str_repeat('i', count($eligible_ids));

        $linkStmt = $conn->prepare("UPDATE passenger_trips SET batch_id = ? WHERE id IN ($placeholders)");
        if (!$linkStmt) throw new Exception("passenger_trips link prepare failed: " . $conn->error);
        $linkStmt->bind_param("i" . $types, $batchId, ...$eligible_ids);
        if (!$linkStmt->execute()) throw new Exception("passenger_trips link failed: " . $linkStmt->error);
        $linkStmt->close();

        $marked_paid = 0;
        if ($new_status === 'fully_collected') {
            $payStmt = $conn->prepare("UPDATE passenger_trips SET payment_status = 'Paid' WHERE id IN ($placeholders)");
            if (!$payStmt) throw new Exception("passenger_trips paid update prepare failed: " . $conn->error);
            $payStmt->bind_param($types, ...$eligible_ids);
            if (!$payStmt->execute()) throw new Exception("passenger_trips paid update failed: " . $payStmt->error);
            $marked_paid = $payStmt->affected_rows;
            $payStmt->close();
        }

        $conn->commit();

        echo json_encode([
            "success" => true,
            "message" => "Cash collection recorded",
            "batch" => [
                "batch_id" => $batchId,
                "trip_id" => $activeTripId,
                "driver_id" => $driver_id,
                "stop_name" => $stop_name,
                "actual_stop_id" => (int)$targetStop['id'],
                "actual_stop_name" => $stop_name,
                "passenger_count" => $passenger_count,
                "expected_amount" => $expected_amount,
                "collected_amount" => $new_total,
                "remaining_amount" => round(max($expected_amount - $new_total, 0), 2),
                "status" => $new_status,
                "collected_at" => date('Y-m-d H:i:s')
            ],
            "linked_passenger_ids" => $eligible_ids,
            "passengers_marked_paid" => $marked_paid
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | 4. BATCH ALREADY EXISTS -> ACCUMULATE THE COLLECTION
    |--------------------------------------------------------------------------
    */

    $batchId = (int)$batch['batch_id'];

    /*
    | The batch is immutable: passenger count and expected amount are fixed at
    | creation. Newly boarded passengers at the same stop form their OWN batch
    | once this one is fully collected - they are never absorbed into an open
    | batch, otherwise a partial collection could swallow and pay for riders
    | who never handed over cash yet.
    */

    $passenger_count = (int)$batch['passenger_count'];
    $expected_amount = round((float)$batch['expected_amount'], 2);
    $accumulated = min(round((float)$batch['collected_amount'] + $collected_amount, 2), $expected_amount);
    $new_status = $accumulated >= $expected_amount ? 'fully_collected' : 'partially_collected';

    $updateStmt = $conn->prepare("
        UPDATE cash_batches
        SET collected_amount = ?, status = ?, passenger_count = ?, expected_amount = ?, collected_at = NOW()
        WHERE batch_id = ?
    ");
    if (!$updateStmt) throw new Exception("cash_batches update prepare failed: " . $conn->error);
    $updateStmt->bind_param("dsidi", $accumulated, $new_status, $passenger_count, $expected_amount, $batchId);
    if (!$updateStmt->execute()) throw new Exception("cash_batches update failed: " . $updateStmt->error);
    $updateStmt->close();

    $marked_paid = 0;
    if ($new_status === 'fully_collected') {
        $payStmt = $conn->prepare("UPDATE passenger_trips SET payment_status = 'Paid' WHERE batch_id = ? AND payment_status <> 'Paid'");
        if (!$payStmt) throw new Exception("passenger_trips paid update prepare failed: " . $conn->error);
        $payStmt->bind_param("i", $batchId);
        if (!$payStmt->execute()) throw new Exception("passenger_trips paid update failed: " . $payStmt->error);
        $marked_paid = $payStmt->affected_rows;
        $payStmt->close();
    }

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Cash collection recorded",
        "batch" => [
            "batch_id" => $batchId,
            "trip_id" => (int)$batch['trip_id'],
            "driver_id" => $batch['driver_id'],
            "stop_name" => $batch['stop_name'],
            "actual_stop_id" => (int)$targetStop['id'],
            "actual_stop_name" => $batch['stop_name'],
            "passenger_count" => $passenger_count,
            "expected_amount" => $expected_amount,
            "collected_amount" => $accumulated,
            "remaining_amount" => round(max($expected_amount - $accumulated, 0), 2),
            "status" => $new_status,
            "collected_at" => date('Y-m-d H:i:s')
        ],
        "passengers_marked_paid" => $marked_paid
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();

<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();

    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception("Only GET requests are allowed");
    }

    $inspector_id = trim($_GET['inspector_id'] ?? '');

    if (empty($inspector_id)) {
        throw new Exception("Missing inspector_id parameter");
    }

    /*
    =====================================
    GET CURRENT CHECKED-IN ASSIGNMENT
    =====================================
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            trip_id,
            bus_id,
            status,
            assigned_at
        FROM inspector_assignments
        WHERE inspector_id = ?
        AND status = 'Checked-In'
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception("Assignment query failed: " . $conn->error);
    }

    $stmt->bind_param("s", $inspector_id);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    /*
    =====================================
    NOT CHECKED-IN (OR BUS OVERRIDE)
    =====================================
    */

    $bus_override = trim($_GET['bus_id'] ?? '');

    if (!$assignment) {
        if (!empty($bus_override)) {
            $stmt = $conn->prepare("
                SELECT id, bus_id, driver_id, status FROM bus_trips 
                WHERE (bus_id = ? OR bus_id = (SELECT id FROM buses WHERE plate_number = ? LIMIT 1))
                AND status != 'Completed'
                ORDER BY id DESC LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param("ss", $bus_override, $bus_override);
                $stmt->execute();
                $tripRow = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($tripRow) {
                    $trip_id = (int)$tripRow['id'];
                    $bus_id = $tripRow['bus_id'];
                    $driver_id = $tripRow['driver_id'];
                }
            }
        }

        if (!isset($trip_id)) {
            echo json_encode([
                "success" => true,
                "inspector_id" => $inspector_id,
                "checked_in" => false,
                "total_passengers" => 0,
                "data" => [],
                "message" => "Inspector is not currently checked in"
            ]);
            exit;
        }
    } else {
        $trip_id = (int)$assignment['trip_id'];
        $bus_id = $assignment['bus_id'];
    }

    /*
    =====================================
    LOOK UP DRIVER ID FOR THE TRIP
    =====================================
    */

    $driver_id = null;
    $stmt = $conn->prepare("
        SELECT driver_id FROM bus_trips WHERE id = ? LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $trip_id);
        if ($stmt->execute()) {
            $tripRow = $stmt->get_result()->fetch_assoc();
            if ($tripRow && isset($tripRow['driver_id'])) {
                $driver_id = $tripRow['driver_id'];
            }
        }
        $stmt->close();
    }

    /*
    =====================================
    CHECK TRIP IS NOT COMPLETED
    =====================================
    */

    $tripStatus = null;
    try {
        $stmt = $conn->prepare("
            SELECT status FROM bus_trips WHERE id = ? LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("i", $trip_id);
            $stmt->execute();
            $tripRow = $stmt->get_result()->fetch_assoc();
            if ($tripRow && isset($tripRow['status'])) {
                $tripStatus = $tripRow['status'];
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("[get_passengers] bus_trips status check skipped: " . $e->getMessage());
        $tripStatus = null;
    }

    if ($tripStatus !== null && strtolower($tripStatus) === 'completed') {
        echo json_encode([
            "success" => true,
            "inspector_id" => $inspector_id,
            "checked_in" => false,
            "total_passengers" => 0,
            "data" => [],
            "message" => "This trip is already completed"
        ]);
        exit;
    }

    /*
    =====================================
    GET PASSENGERS FOR ASSIGNED TRIP
    =====================================
    */

    /*
    =====================================
    DETECT CREATOR / IDENTIFICATION COLUMNS
    =====================================
    */
    $hasPtInspectorId = false;
    $hasPtIdentSource = false;
    $hasPtDriverId = false;
    $hasPtRegType = false;
    $hasPtDropoffType = false;
    $hasPtActualDropoff = false;
    $hasPtDistance = false;
    $hasPtThreshold = false;
    $hasPtReason = false;
    $hasPtBoardingLocStatus = false;
    $hasPtDropoffLocStatus = false;
    $chkCols = $conn->query("SHOW COLUMNS FROM passenger_trips");
    if ($chkCols) {
        while ($c = $chkCols->fetch_assoc()) {
            if ($c['Field'] === 'inspector_id') $hasPtInspectorId = true;
            if ($c['Field'] === 'identification_source') $hasPtIdentSource = true;
            if ($c['Field'] === 'driver_id') $hasPtDriverId = true;
            if ($c['Field'] === 'registration_type') $hasPtRegType = true;
            if ($c['Field'] === 'dropoff_type') $hasPtDropoffType = true;
            if ($c['Field'] === 'actual_dropoff_point') $hasPtActualDropoff = true;
            if ($c['Field'] === 'distance_traveled_km') $hasPtDistance = true;
            if ($c['Field'] === 'dropoff_threshold_km') $hasPtThreshold = true;
            if ($c['Field'] === 'dropoff_reason') $hasPtReason = true;
            if ($c['Field'] === 'boarding_location_status') $hasPtBoardingLocStatus = true;
            if ($c['Field'] === 'dropoff_location_status') $hasPtDropoffLocStatus = true;
        }
    }

    $hasPInspectorId = false;
    $hasPRegType = false;
    $chkPCols = $conn->query("SHOW COLUMNS FROM passengers");
    if ($chkPCols) {
        while ($c = $chkPCols->fetch_assoc()) {
            if ($c['Field'] === 'inspector_id') $hasPInspectorId = true;
            if ($c['Field'] === 'registration_type') $hasPRegType = true;
        }
    }

    $colDriverId = $hasPtDriverId ? "pt.driver_id," : "NULL AS driver_id,";
    $colInspectorId = $hasPtInspectorId ? "pt.inspector_id," : "NULL AS inspector_id,";
    $colIdentSource = $hasPtIdentSource ? "pt.identification_source," : "NULL AS identification_source,";
    $colPInspectorId = $hasPInspectorId ? "p.inspector_id AS p_inspector_id," : "NULL AS p_inspector_id,";
    $colRegType = $hasPtRegType && $hasPRegType 
        ? "COALESCE(pt.registration_type, p.registration_type, 'one_time') AS registration_type," 
        : ($hasPtRegType 
            ? "COALESCE(pt.registration_type, 'one_time') AS registration_type," 
            : ($hasPRegType 
                ? "COALESCE(p.registration_type, 'one_time') AS registration_type," 
                : "'one_time' AS registration_type,"));

    // Short-distance early drop-off fields (present after the
    // add_short_distance_early_dropoff migration).
    $colDropoffType = $hasPtDropoffType ? "pt.dropoff_type," : "NULL AS dropoff_type,";
    $colActualDropoff = $hasPtActualDropoff ? "pt.actual_dropoff_point," : "NULL AS actual_dropoff_point,";
    $colDistance = $hasPtDistance ? "pt.distance_traveled_km," : "NULL AS distance_traveled_km,";
    $colThreshold = $hasPtThreshold ? "pt.dropoff_threshold_km," : "NULL AS dropoff_threshold_km,";
    $colReason = $hasPtReason ? "pt.dropoff_reason," : "NULL AS dropoff_reason,";
    $colBoardingLocStatus = $hasPtBoardingLocStatus ? "pt.boarding_location_status," : "NULL AS boarding_location_status,";
    $colDropoffLocStatus = $hasPtDropoffLocStatus ? "pt.dropoff_location_status," : "NULL AS dropoff_location_status,";

    $stmt = $conn->prepare("
        SELECT
            pt.id,
            pt.passenger_id,
            pt.bus_no,
            pt.trip_no,
            $colDriverId
            $colInspectorId
            $colIdentSource
            $colRegType
            pt.boarding_point,
            pt.destination,
            pt.fare,
            pt.payment_method,
            pt.payment_status,
            pt.cash_amount,
            pt.gcash_amount,
            pt.boarding_time,
            pt.dropoff_time,
            $colDropoffType
            $colActualDropoff
            $colDistance
            $colThreshold
            $colReason
            $colBoardingLocStatus
            $colDropoffLocStatus
            pt.updated_by,
            pt.remitted_by,
            pt.remitted_at,
            pt.expected_cash,
            pt.actual_cash,
            pt.discrepancy_amount,
            pt.finance_note,
            pt.passenger_photo,
            pt.passenger_code,

            p.full_name,
            p.discount_type,
            $colPInspectorId
            p.created_at AS passenger_created_at

        FROM passenger_trips pt

        LEFT JOIN passengers p
            ON p.id = pt.passenger_id

        WHERE pt.trip_no = ?

        ORDER BY pt.boarding_time DESC
    ");

    if (!$stmt) {
        throw new Exception("Passenger query failed: " . $conn->error);
    }

    $stmt->bind_param("i", $trip_id);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    $passengers = [];

    while ($row = $result->fetch_assoc()) {
        $inspId = trim($row['inspector_id'] ?? $row['p_inspector_id'] ?? '');
        $identSource = strtolower(trim($row['identification_source'] ?? ''));
        $drvId = trim($row['driver_id'] ?? '');
        $updatedBy = strtolower(trim($row['updated_by'] ?? ''));

        // Determine whether this passenger record was originally inserted/created by an Inspector
        $isInspectorCreated = false;
        $creatorRole = 'other';

        if (!empty($inspId) || strpos($identSource, 'inspector') !== false || strpos($updatedBy, 'inspector') !== false) {
            $isInspectorCreated = true;
            $creatorRole = 'inspector';
        } elseif (!empty($drvId) || in_array($identSource, ['qr', 'offline_qr', 'manual', 'unverified', 'camera_scan', 'driver'])) {
            $isInspectorCreated = false;
            $creatorRole = 'driver';
        } elseif (in_array($identSource, ['passenger_app', 'online', 'passenger', 'mobile_passenger'])) {
            $isInspectorCreated = false;
            $creatorRole = 'passenger';
        } else {
            $isInspectorCreated = false;
            $creatorRole = 'system';
        }

        $row['is_inspector_created'] = $isInspectorCreated;
        $row['created_by_role'] = $creatorRole;
        $row['can_edit'] = $isInspectorCreated;

        $passengers[] = $row;
    }

    /*
    =====================================
    RESPONSE
    =====================================
    */

    echo json_encode([
        "success" => true,
        "checked_in" => true,
        "inspector_id" => $inspector_id,
        "trip_id" => $trip_id,
        "bus_id" => $bus_id,
        "driver_id" => $driver_id,
        "assignment_status" => $assignment['status'],
        "assigned_at" => $assignment['assigned_at'],
        "total_passengers" => count($passengers),
        "data" => $passengers
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "type" => get_class($e),
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
}

<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

    $db = new Database();
    $conn = $db->connect();

    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST requests are allowed");
    }

    $raw = file_get_contents("php://input");

    $data = json_decode($raw, true);

    if (!$data) {
        throw new Exception("Invalid JSON");
    }

    $id = (int)($data['id'] ?? 0);
    $boarding = trim($data['boarding'] ?? '');
    $destination = trim($data['destination'] ?? '');
    $updated_by = trim($data['updated_by'] ?? '');
    $fare = isset($data['fare'])
        ? (float)$data['fare']
        : 0;
    $edit_reason = trim($data['edit_reason'] ?? '');
    $edit_notes = trim($data['edit_notes'] ?? '');
    $verified_at = trim($data['verified_at'] ?? '');
    if (empty($verified_at)) {
        $verified_at = date('Y-m-d H:i:s');
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($id <= 0) {
        throw new Exception("Passenger trip ID is required");
    }

    if (empty($boarding)) {
        throw new Exception("Boarding point is required");
    }

    if (empty($destination)) {
        throw new Exception("Destination is required");
    }

    if (empty($updated_by)) {
        throw new Exception("updated_by is required");
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK RECORD EXISTS, RETRIEVE PRIOR DATA, & VERIFY CREATOR ORIGIN
    |--------------------------------------------------------------------------
    */

    $hasPtInspectorId = false;
    $hasPtIdentSource = false;
    $hasPtDriverId = false;
    $chkCols = $conn->query("SHOW COLUMNS FROM passenger_trips");
    if ($chkCols) {
        while ($c = $chkCols->fetch_assoc()) {
            if ($c['Field'] === 'inspector_id') $hasPtInspectorId = true;
            if ($c['Field'] === 'identification_source') $hasPtIdentSource = true;
            if ($c['Field'] === 'driver_id') $hasPtDriverId = true;
        }
    }

    $hasPInspectorId = false;
    $chkPCols = $conn->query("SHOW COLUMNS FROM passengers");
    if ($chkPCols) {
        while ($c = $chkPCols->fetch_assoc()) {
            if ($c['Field'] === 'inspector_id') $hasPInspectorId = true;
        }
    }

    $selectInspector = $hasPtInspectorId ? "pt.inspector_id" : "NULL AS inspector_id";
    $selectIdentSource = $hasPtIdentSource ? "pt.identification_source" : "NULL AS identification_source";
    $selectDriver = $hasPtDriverId ? "pt.driver_id" : "NULL AS driver_id";
    $selectPInspector = $hasPInspectorId ? "p.inspector_id AS p_inspector_id" : "NULL AS p_inspector_id";

    $stmt = $conn->prepare("
        SELECT
            pt.id,
            pt.boarding_point,
            pt.destination,
            pt.fare,
            pt.updated_by,
            $selectInspector,
            $selectIdentSource,
            $selectDriver,
            $selectPInspector
        FROM passenger_trips pt
        LEFT JOIN passengers p ON p.id = pt.passenger_id
        WHERE pt.id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            "Prepare failed: " . $conn->error
        );
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $existing =
        $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$existing) {

        echo json_encode([
            "success" => false,
            "message" => "Passenger trip not found",
            "id" => $id
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | ENFORCE RESTRICTION: ONLY INSPECTOR-CREATED RECORDS MAY BE EDITED
    |--------------------------------------------------------------------------
    */
    $inspId = trim($existing['inspector_id'] ?? $existing['p_inspector_id'] ?? '');
    $identSource = strtolower(trim($existing['identification_source'] ?? ''));
    $updatedByExisting = strtolower(trim($existing['updated_by'] ?? ''));
    $driverIdExisting = trim($existing['driver_id'] ?? '');

    $isCreatedByInspector = (
        !empty($inspId) ||
        strpos($identSource, 'inspector') !== false ||
        strpos($updatedByExisting, 'inspector') !== false
    );

    if (!$isCreatedByInspector) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Unauthorized: Inspectors can only edit route records that were originally created by an Inspector.",
            "creator_source" => !empty($driverIdExisting) ? "driver" : ($identSource ?: "system"),
            "id" => $id
        ]);
        exit;
    }

    $old_boarding = $existing['boarding_point'] ?? '';
    $old_destination = $existing['destination'] ?? '';
    $old_fare = isset($existing['fare']) ? (float)$existing['fare'] : 0.0;

    /*
    |--------------------------------------------------------------------------
    | UPDATE PASSENGER TRIP
    |--------------------------------------------------------------------------
    */

    $hasReasonCol = false;
    $hasNotesCol = false;
    $hasVerifiedAtCol = false;

    $chk = $conn->query("SHOW COLUMNS FROM passenger_trips LIKE 'edit_reason'");
    if ($chk && $chk->num_rows > 0) $hasReasonCol = true;
    $chk = $conn->query("SHOW COLUMNS FROM passenger_trips LIKE 'edit_notes'");
    if ($chk && $chk->num_rows > 0) $hasNotesCol = true;
    $chk = $conn->query("SHOW COLUMNS FROM passenger_trips LIKE 'verified_at'");
    if ($chk && $chk->num_rows > 0) $hasVerifiedAtCol = true;

    $updateCols = [
        "boarding_point = ?",
        "destination = ?",
        "fare = ?",
        "updated_by = ?"
    ];
    $types = "ssds";
    $params = [$boarding, $destination, $fare, $updated_by];

    if ($hasReasonCol) {
        $updateCols[] = "edit_reason = ?";
        $types .= "s";
        $params[] = $edit_reason;
    }
    if ($hasNotesCol) {
        $updateCols[] = "edit_notes = ?";
        $types .= "s";
        $params[] = $edit_notes;
    }
    if ($hasVerifiedAtCol) {
        $updateCols[] = "verified_at = ?";
        $types .= "s";
        $params[] = $verified_at;
    }

    $types .= "i";
    $params[] = $id;

    $sql = "UPDATE passenger_trips SET " . implode(", ", $updateCols) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "Prepare failed: " . $conn->error
        );
    }

    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        throw new Exception(
            "Execute failed: " . $stmt->error
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE TO AUDIT TRAIL TABLE
    |--------------------------------------------------------------------------
    */

    $conn->query("
        CREATE TABLE IF NOT EXISTS passenger_route_audit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            passenger_trip_id INT NOT NULL,
            old_boarding VARCHAR(100) NULL,
            new_boarding VARCHAR(100) NULL,
            old_destination VARCHAR(100) NULL,
            new_destination VARCHAR(100) NULL,
            old_fare DECIMAL(10,2) NULL,
            new_fare DECIMAL(10,2) NULL,
            edit_reason VARCHAR(100) NOT NULL,
            edit_notes TEXT NULL,
            verified_by VARCHAR(100) NOT NULL,
            verified_at VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $auditStmt = $conn->prepare("
        INSERT INTO passenger_route_audit (
            passenger_trip_id,
            old_boarding,
            new_boarding,
            old_destination,
            new_destination,
            old_fare,
            new_fare,
            edit_reason,
            edit_notes,
            verified_by,
            verified_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($auditStmt) {
        $auditStmt->bind_param(
            "issssddssss",
            $id,
            $old_boarding,
            $boarding,
            $old_destination,
            $destination,
            $old_fare,
            $fare,
            $edit_reason,
            $edit_notes,
            $updated_by,
            $verified_at
        );
        $auditStmt->execute();
        $auditStmt->close();
    }

    echo json_encode([

        "success" => true,

        "message" =>
            "Passenger trip updated successfully",

        "id" => $id,

        "boarding_point" =>
            $boarding,

        "destination" =>
            $destination,

        "fare" =>
            $fare,

        "updated_by" =>
            $updated_by,

        "edit_reason" =>
            $edit_reason,

        "edit_notes" =>
            $edit_notes,

        "verified_at" =>
            $verified_at,

        "audit_recorded" =>
            true,

        "rows_affected" =>
            $stmt->affected_rows

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

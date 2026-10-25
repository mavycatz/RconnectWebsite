<?php
/**
 * submit_inspection.php
 * Saves a completed, immutable Inspector bus inspection and passenger verification audit trail.
 * Submitted inspections are strictly read-only and cannot be altered by inspectors.
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
    if (!$data || !is_array($data)) {
        throw new Exception("Invalid JSON payload");
    }

    $busNo = trim($data['bus_no'] ?? '');
    $tripNo = trim((string)($data['trip_no'] ?? ''));
    $routeName = trim($data['route_name'] ?? '');
    $inspectorId = trim($data['inspector_id'] ?? '');
    $inspectorName = trim($data['inspector_name'] ?? '');
    $driverId = trim((string)($data['driver_id'] ?? ''));
    $passengers = $data['passenger_records'] ?? [];
    $auditSummary = $data['audit_summary'] ?? [];
    $validationData = $data['validation'] ?? null;

    if ($validationData) {
        $auditSummary['validation'] = $validationData;
    }

    if (empty($busNo)) {
        throw new Exception("Bus number is required for inspection submission");
    }
    if (empty($inspectorId)) {
        throw new Exception("Inspector ID is required");
    }

    // Accountability: resolve the inspector's registered name when the app
    // submitted an empty name or fell back to the inspector id.
    if ($inspectorName === '' || strcasecmp($inspectorName, $inspectorId) === 0) {
        try {
            $iNameStmt = $conn->prepare("
                SELECT i.inspector_name
                FROM inspector_accounts ia
                LEFT JOIN inspectors i ON i.account_id = ia.account_id
                WHERE LOWER(ia.inspector_id) = LOWER(?)
                LIMIT 1
            ");
            if ($iNameStmt) {
                $iNameStmt->bind_param("s", $inspectorId);
                $iNameStmt->execute();
                $iNameRow = $iNameStmt->get_result()->fetch_assoc();
                $iNameStmt->close();
                if ($iNameRow && trim((string)($iNameRow['inspector_name'] ?? '')) !== '') {
                    $inspectorName = trim((string)$iNameRow['inspector_name']);
                }
            }
        } catch (Exception $e) {
            // Inspector tables may not exist yet; keep the submitted value.
        }
    }
    $totalCount = is_array($passengers) ? count($passengers) : 0;
    $headcountExpected = intval($data['headcount_expected'] ?? $totalCount);
    $headcountActual = intval($data['headcount_actual'] ?? $totalCount);
    $overallResult = trim($data['overall_result'] ?? '');

    $validatedCount = 0;
    $needsReviewCount = 0;

    // Validate each passenger record before saving
    foreach ($passengers as $idx => $p) {
        $pName = trim($p['passenger_name'] ?? '');
        $status = strtoupper(trim($p['validation_status'] ?? ''));

        if ($status !== 'VALID' && $status !== 'VALIDATED' && $status !== 'NEEDS REVIEW') {
            throw new Exception("Passenger '" . ($pName ?: "at index $idx") . "' is still UNCHECKED. All passengers must be inspected before submission.");
        }

        if ($status === 'VALID' || $status === 'VALIDATED') {
            $validatedCount++;
        } else {
            $needsReviewCount++;
            $reasons = $p['discrepancy_reasons'] ?? [];
            $remarks = trim($p['remarks'] ?? '');
            if (empty($reasons) && empty($remarks)) {
                throw new Exception("Passenger '" . ($pName ?: "P" . ($idx + 1)) . "' is flagged for review but has no discrepancy reason or remarks. An explanation is required.");
            }
        }
    }

    // Auto-create inspection tables if not already present
    $conn->query("
        CREATE TABLE IF NOT EXISTS `inspector_inspections` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `inspection_id` VARCHAR(64) NOT NULL UNIQUE,
          `bus_no` VARCHAR(64) NOT NULL,
          `trip_no` VARCHAR(64) NOT NULL,
          `route_name` VARCHAR(128) NULL,
          `inspector_id` VARCHAR(64) NOT NULL,
          `inspector_name` VARCHAR(128) NULL,
          `driver_id` VARCHAR(64) NULL,
          `total_passengers` INT NOT NULL DEFAULT 0,
          `checked_count` INT NOT NULL DEFAULT 0,
          `validated_count` INT NOT NULL DEFAULT 0,
          `needs_review_count` INT NOT NULL DEFAULT 0,
          `status` ENUM('In Progress', 'Submitted', 'Archived') NOT NULL DEFAULT 'Submitted',
          `submitted_at` DATETIME NOT NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `audit_summary` JSON NULL,
          INDEX `idx_bus_trip` (`bus_no`, `trip_no`),
          INDEX `idx_inspector` (`inspector_id`),
          INDEX `idx_driver` (`driver_id`),
          INDEX `idx_submitted_at` (`submitted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Existing installations: add the driver link used by the Driver app's
    // "Inspection Completed" notification and result modal.
    $conn->query("
        SET @sql = IF(
            (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'inspector_inspections'
               AND COLUMN_NAME = 'driver_id') = 0,
            'ALTER TABLE inspector_inspections ADD COLUMN driver_id VARCHAR(64) NULL AFTER inspector_name',
            'SELECT 1'
        )
    ");
    $conn->query("PREPARE insp_stmt FROM @sql");
    $conn->query("EXECUTE insp_stmt");
    $conn->query("DEALLOCATE PREPARE insp_stmt");

    $conn->query("
        CREATE TABLE IF NOT EXISTS `inspector_inspection_items` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `inspection_id` VARCHAR(64) NOT NULL,
          `passenger_id` INT NULL,
          `passenger_code` VARCHAR(64) NULL,
          `passenger_name` VARCHAR(128) NOT NULL,
          `passenger_type` VARCHAR(64) NULL,
          `registration_type` VARCHAR(64) NULL,
          `boarding_point` VARCHAR(128) NULL,
          `destination` VARCHAR(128) NULL,
          `fare` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          `payment_status` VARCHAR(64) NULL,
          `validation_status` ENUM('UNVERIFIED', 'VALIDATED', 'NEEDS REVIEW') NOT NULL DEFAULT 'UNVERIFIED',
          `checklist_results` JSON NULL,
          `discrepancy_reasons` JSON NULL,
          `remarks` TEXT NULL,
          `verified_at` DATETIME NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX `idx_inspection_id` (`inspection_id`),
          INDEX `idx_passenger_code` (`passenger_code`),
          INDEX `idx_validation_status` (`validation_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $now = date('Y-m-d H:i:s');
    $inspectionId = 'INSP-' . date('Ymd-His') . '-' . substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6);
    $auditJson = !empty($auditSummary) ? json_encode($auditSummary, JSON_UNESCAPED_UNICODE) : null;
    $status = 'Submitted';

    // Insert main inspection record
    $stmt = $conn->prepare("
        INSERT INTO `inspector_inspections` (
            inspection_id,
            bus_no,
            trip_no,
            route_name,
            inspector_id,
            inspector_name,
            driver_id,
            total_passengers,
            checked_count,
            validated_count,
            needs_review_count,
            status,
            submitted_at,
            audit_summary
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare inspection insert: " . $conn->error);
    }

    $stmt->bind_param(
        "sssssssiiiisss",
        $inspectionId,
        $busNo,
        $tripNo,
        $routeName,
        $inspectorId,
        $inspectorName,
        $driverId,
        $totalCount,
        $totalCount,
        $validatedCount,
        $needsReviewCount,
        $status,
        $now,
        $auditJson
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to record inspection: " . $stmt->error);
    }
    $stmt->close();

    // Insert each passenger inspection item
    $itemStmt = $conn->prepare("
        INSERT INTO `inspector_inspection_items` (
            inspection_id,
            passenger_id,
            passenger_code,
            passenger_name,
            passenger_type,
            registration_type,
            boarding_point,
            destination,
            fare,
            payment_status,
            validation_status,
            checklist_results,
            discrepancy_reasons,
            remarks,
            verified_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$itemStmt) {
        throw new Exception("Failed to prepare inspection items insert: " . $conn->error);
    }

    foreach ($passengers as $p) {
        $pId = !empty($p['passenger_id']) ? (int)$p['passenger_id'] : null;
        $pCode = trim($p['passenger_code'] ?? '');
        $pName = trim($p['passenger_name'] ?? 'Unknown');
        $pType = trim($p['passenger_type'] ?? 'Regular');
        $regType = trim($p['registration_type'] ?? 'Standard');
        $bPoint = trim($p['boarding_point'] ?? '');
        $dest = trim($p['destination'] ?? '');
        $fare = isset($p['fare']) ? (float)$p['fare'] : 0.00;
        $payStatus = trim($p['payment_status'] ?? '');
        $valStatus = strtoupper(trim($p['validation_status'] ?? 'VALIDATED'));
        $chkJson = !empty($p['checklist_results']) ? json_encode($p['checklist_results'], JSON_UNESCAPED_UNICODE) : null;
        $discJson = !empty($p['discrepancy_reasons']) ? json_encode($p['discrepancy_reasons'], JSON_UNESCAPED_UNICODE) : null;
        $remarks = trim($p['remarks'] ?? '');
        $verAt = !empty($p['verified_at']) ? trim($p['verified_at']) : $now;

        $itemStmt->bind_param(
            "sissssssdssssss",
            $inspectionId,
            $pId,
            $pCode,
            $pName,
            $pType,
            $regType,
            $bPoint,
            $dest,
            $fare,
            $payStatus,
            $valStatus,
            $chkJson,
            $discJson,
            $remarks,
            $verAt
        );

        $itemStmt->execute();
    }
    $itemStmt->close();

    // Send Driver notification workflow (announcements table for driver's active trip)
    try {
        $findingsCount = $needsReviewCount;
        if (!empty($auditSummary['findings']) && is_array($auditSummary['findings'])) {
            $findingsCount = count($auditSummary['findings']);
        }
        $annTitle = "Trip Inspection Findings - Bus $busNo";
        $annType = "Inspector Report";
        $annPriority = ($overallResult === 'Discrepancy Found') ? 'High' : 'Normal';
        $annMsg = "Inspector " . ($inspectorName ?: $inspectorId) . " completed trip inspection for Bus-$busNo (Trip #$tripNo). " .
                  "Status: $overallResult. Headcount: $headcountActual/$headcountExpected. " .
                  ($findingsCount > 0 ? "$findingsCount finding(s) recorded." : "No discrepancies found.");

        $annStmt = $conn->prepare("
            INSERT INTO announcements (type, title, message, bus_id, driver_name, reported_by, priority, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')
        ");
        if ($annStmt) {
            $repBy = $inspectorName ?: "Inspector $inspectorId";
            $annStmt->bind_param("sssssss", $annType, $annTitle, $annMsg, $busNo, $driverId, $repBy, $annPriority);
            $annStmt->execute();
            $annStmt->close();
        }
    } catch (Exception $annEx) {
        error_log("[submit_inspection] Announcement notification error: " . $annEx->getMessage());
    }

    echo json_encode([
        "success" => true,
        "inspection_id" => $inspectionId,
        "bus_no" => $busNo,
        "trip_no" => $tripNo,
        "submitted_at" => $now,
        "total_passengers" => $totalCount,
        "validated_count" => $validatedCount,
        "needs_review_count" => $needsReviewCount,
        "validation" => $validationData,
        "message" => "Inspection submitted successfully. Audit trail record created."
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

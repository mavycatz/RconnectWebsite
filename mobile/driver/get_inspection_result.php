<?php
/**
 * get_inspection_result.php
 * Returns the submitted trip inspection result for a driver so the Driver app
 * can display it as a read-only modal overlay.
 *
 * Inspector findings are immutable: this endpoint only reads them and joins the
 * driver acknowledgement (which is stored separately).
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function inspection_finding_category($reasons)
{
    $keywords = [
        'Headcount' => ['headcount'],
        'Payments' => ['payment', 'fare', 'unpaid'],
        'Boarding/Destination' => ['boarding', 'destination', 'alighting', 'station'],
        'Passenger Details' => ['passenger', 'discount', 'duplicate', 'missing', 'details'],
    ];

    foreach ((array)$reasons as $reason) {
        $text = strtolower((string)$reason);
        foreach ($keywords as $category => $needles) {
            foreach ($needles as $needle) {
                if (strpos($text, $needle) !== false) {
                    return $category;
                }
            }
        }
    }
    return 'Passenger Details';
}

try {

    require_once __DIR__ . "/../config/database.php";

    $db = new Database();
    $conn = $db->connect();
    if (!$conn) throw new Exception("Database connection failed");

    $raw = file_get_contents("php://input");
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = [];

    $driverId = trim($_GET['driver_id'] ?? $body['driver_id'] ?? '');
    $token = trim($_GET['token'] ?? $body['token'] ?? '');
    $inspectionId = trim($_GET['inspection_id'] ?? $body['inspection_id'] ?? '');

    require_once __DIR__ . '/lib/driver_auth.php';
    require_driver_auth($conn, $driverId, $token);

    // Resolve the driver's current trip. Inspection lookups without an explicit
    // inspection_id are always scoped to it so a new trip can never show a
    // previous trip's result.
    $activeTripIds = [];
    $activeTripBus = '';
    $activeTripStmt = $conn->prepare("
        SELECT id, trip_number, bus_id
        FROM bus_trips
        WHERE driver_id = ? AND status IN ('Departed', 'Started', 'Active', 'Ongoing', 'Interrupted')
        ORDER BY id DESC
        LIMIT 1
    ");
    if ($activeTripStmt) {
        $activeTripStmt->bind_param("s", $driverId);
        $activeTripStmt->execute();
        $activeTripRow = $activeTripStmt->get_result()->fetch_assoc();
        $activeTripStmt->close();
        if ($activeTripRow) {
            $activeTripIds[] = (string)$activeTripRow['id'];
            $tripNumberValue = trim((string)($activeTripRow['trip_number'] ?? ''));
            if ($tripNumberValue !== '' && $tripNumberValue !== (string)$activeTripRow['id']) {
                $activeTripIds[] = $tripNumberValue;
            }
            $activeTripBus = trim((string)($activeTripRow['bus_id'] ?? ''));
        }
    }
    $activeTripIdA = $activeTripIds[0] ?? '';
    $activeTripIdB = $activeTripIds[1] ?? $activeTripIdA;
    $hasActiveTrip = $activeTripIdA !== '';

    $tableCheck = $conn->query("SHOW TABLES LIKE 'inspector_inspections'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode(["success" => true, "inspection" => null]);
        exit;
    }

    $hasDriverColumn = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM inspector_inspections");
    if ($colCheck) {
        while ($c = $colCheck->fetch_assoc()) {
            if ($c['Field'] === 'driver_id') $hasDriverColumn = true;
        }
    }

    if ($inspectionId !== '') {
        $stmt = $conn->prepare(
            "SELECT * FROM inspector_inspections WHERE inspection_id = ? LIMIT 1"
        );
        if (!$stmt) throw new Exception("inspection query failed: " . $conn->error);
        $stmt->bind_param("s", $inspectionId);
    } elseif ($hasActiveTrip && $hasDriverColumn) {
        $stmt = $conn->prepare(
            "SELECT * FROM inspector_inspections
             WHERE driver_id = ? AND trip_no IN (?, ?)
             ORDER BY submitted_at DESC, id DESC LIMIT 1"
        );
        if (!$stmt) throw new Exception("inspection query failed: " . $conn->error);
        $stmt->bind_param("sss", $driverId, $activeTripIdA, $activeTripIdB);
    } elseif ($hasActiveTrip) {
        // Legacy rows: the submitting inspector stored the driver inside the
        // audit summary validation record. Still scoped to the current trip.
        $stmt = $conn->prepare(
            "SELECT * FROM inspector_inspections
             WHERE JSON_UNQUOTE(JSON_EXTRACT(audit_summary, '$.validation.driver_id')) = ?
               AND trip_no IN (?, ?)
             ORDER BY submitted_at DESC, id DESC LIMIT 1"
        );
        if (!$stmt) throw new Exception("inspection query failed: " . $conn->error);
        $stmt->bind_param("sss", $driverId, $activeTripIdA, $activeTripIdB);
    } else {
        // No active trip: there is no current-trip inspection to show.
        echo json_encode(["success" => true, "inspection" => null]);
        exit;
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // An explicit inspection_id must belong to this driver: never expose
    // another driver's inspection. Any inspection the driver owns can be
    // opened by id (for example from an older notification), regardless of
    // whether it is the current trip.
    if ($row && $inspectionId !== '') {
        $ownsInspection = false;
        $rowDriverId = trim((string)($row['driver_id'] ?? ''));
        if ($rowDriverId !== '' && $rowDriverId === (string)$driverId) {
            $ownsInspection = true;
        }
        if (!$ownsInspection && !empty($row['trip_no'])) {
            $ownsStmt = $conn->prepare(
                "SELECT 1 FROM bus_trips WHERE driver_id = ? AND (id = ? OR trip_no = ?) LIMIT 1"
            );
            if ($ownsStmt) {
                $rowTripNo = (string)$row['trip_no'];
                $ownsStmt->bind_param("sss", $driverId, $rowTripNo, $rowTripNo);
                $ownsStmt->execute();
                $ownsRow = $ownsStmt->get_result()->fetch_assoc();
                $ownsStmt->close();
                if ($ownsRow) $ownsInspection = true;
            }
        }
        if (!$ownsInspection) {
            $row = null;
        }
    }

    // Legacy fallback (no explicit id): rows submitted before the driver link
    // was stored may lack driver_id but still carry the current trip + bus.
    // Never fall back to another trip's inspection.
    if (!$row && $inspectionId === '' && $hasActiveTrip) {
        $busNo = trim($_GET['bus_no'] ?? $body['bus_no'] ?? '');
        if ($busNo === '') {
            $busNo = $activeTripBus;
        }
        if ($busNo !== '') {
            $busStmt = $conn->prepare(
                "SELECT * FROM inspector_inspections
                 WHERE bus_no = ? AND trip_no IN (?, ?)
                 ORDER BY submitted_at DESC, id DESC LIMIT 1"
            );
            if ($busStmt) {
                $busStmt->bind_param("sss", $busNo, $activeTripIdA, $activeTripIdB);
                $busStmt->execute();
                $row = $busStmt->get_result()->fetch_assoc();
                $busStmt->close();
            }
        }
    }

    if (!$row) {
        echo json_encode(["success" => true, "inspection" => null]);
        exit;
    }

    // Accountability: always show the inspector's registered name. The
    // Inspector app falls back to the inspector id when no name was stored,
    // so a value equal to the id is treated as missing and resolved here.
    $inspectorName = trim((string)($row['inspector_name'] ?? ''));
    $inspectorIdValue = trim((string)($row['inspector_id'] ?? ''));
    $nameMissing = $inspectorName === ''
        || ($inspectorIdValue !== '' && strcasecmp($inspectorName, $inspectorIdValue) === 0);
    if ($nameMissing && $inspectorIdValue !== '') {
        try {
            $iNameStmt = $conn->prepare("
                SELECT i.inspector_name
                FROM inspector_accounts ia
                LEFT JOIN inspectors i ON i.account_id = ia.account_id
                WHERE LOWER(ia.inspector_id) = LOWER(?)
                LIMIT 1
            ");
            if ($iNameStmt) {
                $iNameStmt->bind_param("s", $inspectorIdValue);
                $iNameStmt->execute();
                $iNameRow = $iNameStmt->get_result()->fetch_assoc();
                $iNameStmt->close();
                if ($iNameRow && trim((string)($iNameRow['inspector_name'] ?? '')) !== '') {
                    $inspectorName = trim((string)$iNameRow['inspector_name']);
                }
            }
        } catch (Exception $e) {
            // Inspector tables may not exist yet; fall back to stored values.
        }
    }
    if ($inspectorName === '') {
        $inspectorName = $inspectorIdValue;
    }

    $recordInspectionId = $row['inspection_id'];
    $audit = [];
    if (!empty($row['audit_summary'])) {
        $decoded = json_decode($row['audit_summary'], true);
        if (is_array($decoded)) $audit = $decoded;
    }
    $checklist = is_array($audit['trip_checklist'] ?? null) ? $audit['trip_checklist'] : [];
    $validation = is_array($audit['validation'] ?? null) ? $audit['validation'] : [];
    $overallResult = (string)($audit['overall_result'] ?? $row['status'] ?? '');
    $isComplete = strcasecmp(trim($overallResult), 'Inspection Complete') === 0;

    $headcountExpected = (int)($audit['headcount_expected'] ?? $row['total_passengers']);
    $headcountActual = (int)($audit['headcount_actual'] ?? $row['total_passengers']);

    //--------------------------------------------------------------------------
    // Findings (per-passenger NEEDS REVIEW items)
    //--------------------------------------------------------------------------
    $findings = [];
    $allReasons = [];

    $itemsTable = $conn->query("SHOW TABLES LIKE 'inspector_inspection_items'");
    if ($itemsTable && $itemsTable->num_rows > 0) {
        $itemStmt = $conn->prepare(
            "SELECT passenger_code, passenger_name, passenger_type, boarding_point, destination,
                    validation_status, discrepancy_reasons, remarks
             FROM inspector_inspection_items
             WHERE inspection_id = ?
             ORDER BY id ASC"
        );
        if ($itemStmt) {
            $itemStmt->bind_param("s", $recordInspectionId);
            $itemStmt->execute();
            $itemsResult = $itemStmt->get_result();
            while ($item = $itemsResult->fetch_assoc()) {
                $reasons = [];
                if (!empty($item['discrepancy_reasons'])) {
                    $decodedReasons = json_decode($item['discrepancy_reasons'], true);
                    if (is_array($decodedReasons)) $reasons = array_values(array_filter($decodedReasons, 'strval'));
                }
                if (strtoupper(trim((string)$item['validation_status'])) !== 'NEEDS REVIEW') {
                    continue;
                }
                $remark = trim((string)$item['remarks']);
                foreach ($reasons as $r) {
                    $allReasons[] = (string)$r;
                }
                $findings[] = [
                    "passenger_name" => $item['passenger_name'] ?? 'Passenger',
                    "passenger_code" => $item['passenger_code'] ?? '',
                    "category" => inspection_finding_category($reasons),
                    "issue" => !empty($reasons) ? implode(', ', $reasons) : ($remark !== '' ? $remark : 'Flagged by inspector'),
                    "remark" => $remark,
                ];
            }
            $itemStmt->close();
        }
    }

    $hasReasonKeyword = function ($needles) use ($allReasons) {
        foreach ($allReasons as $reason) {
            $text = strtolower($reason);
            foreach ($needles as $needle) {
                if (strpos($text, $needle) !== false) return true;
            }
        }
        return false;
    };

    $headcountFinding = !$isComplete && (
        (array_key_exists('headcount_correct', $checklist) && !$checklist['headcount_correct'])
        || $hasReasonKeyword(['headcount'])
        || $headcountActual !== $headcountExpected
    );
    $passengerFinding = !$isComplete && (
        (array_key_exists('passenger_details_correct', $checklist) && !$checklist['passenger_details_correct'])
        || $hasReasonKeyword(['passenger', 'discount', 'duplicate', 'details'])
    );
    $paymentFinding = !$isComplete && (
        (array_key_exists('payment_information_validated', $validation) && !$validation['payment_information_validated'])
        || $hasReasonKeyword(['payment', 'fare', 'unpaid'])
    );
    $boardingFinding = !$isComplete && (
        (array_key_exists('boarding_alighting_correct', $checklist) && !$checklist['boarding_alighting_correct'])
        || (array_key_exists('boarding_destination_validated', $validation) && !$validation['boarding_destination_validated'])
        || $hasReasonKeyword(['boarding', 'destination', 'alighting', 'station'])
    );

    //--------------------------------------------------------------------------
    // Early drop-off records for the inspected trip (if the feature is deployed)
    //--------------------------------------------------------------------------
    $earlyDropoffs = [];
    $ptCols = [];
    $ptColCheck = $conn->query("SHOW COLUMNS FROM passenger_trips");
    if ($ptColCheck) {
        while ($c = $ptColCheck->fetch_assoc()) {
            $ptCols[$c['Field']] = true;
        }
    }
    if (isset($ptCols['dropoff_type'])) {
        $edStmt = $conn->prepare(
            "SELECT passenger_code, actual_dropoff_point, distance_traveled_km, dropoff_threshold_km,
                    fare, payment_status
             FROM passenger_trips
             WHERE trip_no = ? AND dropoff_type = 'early_short_distance' AND archived_status = 'no'
             ORDER BY id ASC"
        );
        if ($edStmt) {
            $tripNo = (string)$row['trip_no'];
            $edStmt->bind_param("s", $tripNo);
            $edStmt->execute();
            $edResult = $edStmt->get_result();
            while ($ed = $edResult->fetch_assoc()) {
                $earlyDropoffs[] = [
                    "status" => "Valid Early Drop-off",
                    "passenger_code" => $ed['passenger_code'] ?? '',
                    "dropoff_point" => $ed['actual_dropoff_point'] ?? '',
                    "distance_km" => $ed['distance_traveled_km'] !== null ? (float)$ed['distance_traveled_km'] : null,
                    "threshold_km" => $ed['dropoff_threshold_km'] !== null ? (float)$ed['dropoff_threshold_km'] : null,
                    "fare" => (float)($ed['fare'] ?? 0),
                    "payment" => strtolower((string)($ed['payment_status'] ?? '')) === 'no fare'
                        ? 'Not Required'
                        : (string)($ed['payment_status'] ?? 'Not Required'),
                ];
            }
            $edStmt->close();
        }
    }

    //--------------------------------------------------------------------------
    // Driver acknowledgement (read-only here)
    //--------------------------------------------------------------------------
    $acknowledgement = null;
    $ackTable = $conn->query("SHOW TABLES LIKE 'inspection_driver_acknowledgements'");
    if ($ackTable && $ackTable->num_rows > 0) {
        $ackStmt = $conn->prepare(
            "SELECT driver_id, driver_name, acknowledged_at
             FROM inspection_driver_acknowledgements
             WHERE inspection_id = ? LIMIT 1"
        );
        if ($ackStmt) {
            $ackStmt->bind_param("s", $recordInspectionId);
            $ackStmt->execute();
            $ackRow = $ackStmt->get_result()->fetch_assoc();
            $ackStmt->close();
            if ($ackRow) {
                $acknowledgement = [
                    "acknowledged" => true,
                    "acknowledged_by" => $ackRow['driver_name'] ?? $driverId,
                    "acknowledged_at" => $ackRow['acknowledged_at'],
                ];
            }
        }
    }

    $findingsCount = count($findings);

    echo json_encode([
        "success" => true,
        "inspection" => [
            "inspection_id" => $recordInspectionId,
            "bus_no" => $row['bus_no'],
            "trip_no" => $row['trip_no'],
            "route_name" => $row['route_name'],
            "inspector_id" => $row['inspector_id'],
            "inspector_name" => $inspectorName,
            "submitted_at" => $row['submitted_at'],
            "overall_result" => $overallResult,
            "status" => $row['status'],
            "passengers_reviewed" => (int)$row['total_passengers'],
            "validated_count" => (int)$row['validated_count'],
            "needs_review_count" => (int)$row['needs_review_count'],
            "total_findings" => $findingsCount,
            "summary" => [
                "headcount" => $headcountFinding ? "Finding" : "Verified",
                "passenger_details" => $passengerFinding ? "Finding" : "Verified",
                "payments" => $paymentFinding ? "Finding" : "Verified",
                "boarding_destination" => $boardingFinding ? "Finding" : "Verified",
            ],
            "findings" => $findings,
            "early_dropoffs" => $earlyDropoffs,
            "acknowledgement" => $acknowledgement,
        ],
    ]);

} catch (Exception $e) {
    error_log("[get_inspection_result] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "code" => "SERVER_ERROR",
        "message" => "Something went wrong. Please try again.",
    ]);
}

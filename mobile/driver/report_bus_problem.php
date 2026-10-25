<?php

/*
|--------------------------------------------------------------------------
| DRIVER REPORT BUS PROBLEM (Profile -> Report Bus Problem -> Notify)
|
| Stores one bus-problem report for the authenticated driver's active trip
| and moves that trip into the interrupted / replacement-required state.
|
| - Only bus_trips.status + bus_trips.replacement_required are written on the
|   trip. passenger_trips, fare, payment, boarding and destination are never
|   touched here.
| - The photo, driver, bus, trip, authoritative passenger count, GPS position
|   and timestamp are attached automatically; the client never supplies them
|   as authoritative values.
| - Retried/duplicated submissions are idempotent: the same identity_key is
|   reported as already recorded, and a second open report for the same trip
|   is refused.
| - "Dispatcher notified" is only ever reported once the row is committed.
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

const BPR_MAX_PHOTO_BYTES = 1048576; // 1 MB

/**
 * Decodes a base64 data URI (or a plain URL) into bytes and writes it under
 * bus_problem_photos/. Returns the stored relative path, or null when the
 * payload is missing, malformed, oversized, or not an accepted image type.
 * Mirrors save_fuel_proof_photo() in scripts/saveFuelLog.php.
 */
function save_bus_problem_photo($dataUri, $reportRef)
{
    if (!is_string($dataUri) || trim($dataUri) === '') {
        return null;
    }
    if (strpos($dataUri, 'data:image') !== 0) {
        return $dataUri;
    }
    if (!preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $dataUri, $matches)) {
        return null;
    }
    $prefix = $matches[0];
    $base64 = preg_replace('/\s+/', '', substr($dataUri, strlen($prefix)));
    $bytes = base64_decode($base64, true);
    if ($bytes === false || $bytes === '') {
        return null;
    }
    if (strlen($bytes) > BPR_MAX_PHOTO_BYTES) {
        return null;
    }
    if (strlen($bytes) < 4) {
        return null;
    }
    // Cheap magic-number check so a text payload can never be stored as a photo.
    $isJpeg = substr($bytes, 0, 3) === "\xFF\xD8\xFF";
    $isPng = substr($bytes, 0, 4) === "\x89PNG";
    if (!$isJpeg && !$isPng) {
        return null;
    }

    $uploadDir = dirname(__DIR__, 2) . '/bus_problem_photos/';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true)) {
        return null;
    }
    $filename = 'report_' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$reportRef) . '_' . date('Ymd_His') . '.jpg';
    $destination = $uploadDir . $filename;
    if (@file_put_contents($destination, $bytes) === false) {
        return null;
    }
    return 'bus_problem_photos/' . $filename;
}

function bpr_delete_photo($relativePath)
{
    if (!is_string($relativePath) || $relativePath === '' || strpos($relativePath, 'bus_problem_photos/') !== 0) {
        return;
    }
    $full = dirname(__DIR__, 2) . '/' . $relativePath;
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Haversine distance in km, the same formula the route screen uses to find the
 * nearest terminal (scripts/getDriverRoute.php distanceKm()).
 */
function bpr_haversine_km($lat1, $lng1, $lat2, $lng2)
{
    $earth = 6371;
    $dLat = deg2rad((float)$lat2 - (float)$lat1);
    $dLng = deg2rad((float)$lng2 - (float)$lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) *
        sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth * $c;
}

/**
 * Resolves the terminal the report is routed to.
 *
 * - GPS available: the active terminal nearest to the captured fix.
 * - GPS unavailable (or no active terminals): the trip's origin terminal,
 *   then its destination terminal, then none.
 *
 * Uses only existing columns (terminals.id/terminal_name/latitude/longitude/
 * status and bus_trips.from_terminal_id/to_terminal_id). No terminal or
 * dispatcher tables or columns are invented.
 *
 * @param mysqli   $conn
 * @param float|null $lat Latitude stored on the report (null when no fix).
 * @param float|null $lng Longitude stored on the report.
 * @param array    $activeTrip Resolved active trip (has from/to_terminal_id).
 * @return array{id:int|null,name:string|null,source:string|null} source is
 *         'gps', 'trip' or null.
 */
function bpr_resolve_nearest_terminal($conn, $lat, $lng, $activeTrip)
{
    $gpsOk = $lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng);

    if ($gpsOk) {
        $rows = $conn->query(
            "SELECT id, terminal_code, terminal_name, latitude, longitude
             FROM terminals WHERE status = 'Active'"
        );
        if ($rows && $rows->num_rows > 0) {
            $nearest = null;
            $nearestDistance = PHP_FLOAT_MAX;
            while ($t = $rows->fetch_assoc()) {
                $d = bpr_haversine_km($lat, $lng, $t['latitude'], $t['longitude']);
                if ($d < $nearestDistance) {
                    $nearestDistance = $d;
                    $nearest = $t;
                }
            }
            if ($nearest) {
                return [
                    'id' => (int)$nearest['id'],
                    'name' => (string)$nearest['terminal_name'],
                    'source' => 'gps',
                ];
            }
        }
    }

    foreach ([$activeTrip['from_terminal_id'] ?? null, $activeTrip['to_terminal_id'] ?? null] as $candidateId) {
        if (empty($candidateId)) {
            continue;
        }
        $stmt = $conn->prepare("SELECT id, terminal_name FROM terminals WHERE id = ? LIMIT 1");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param("i", $candidateId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return [
                'id' => (int)$row['id'],
                'name' => (string)$row['terminal_name'],
                'source' => 'trip',
            ];
        }
    }

    return ['id' => null, 'name' => null, 'source' => null];
}

try {

    require_once __DIR__ . "/../config/database.php";
    require_once __DIR__ . '/lib/driver_auth.php';
    require_once __DIR__ . '/lib/active_trip.php';

    $db = new Database();
    $conn = $db->connect();
    if (!$conn) throw new Exception("Database connection failed");

    $input = json_decode(file_get_contents("php://input"), true) ?: [];

    $txId        = trim((string)($input['tx_id'] ?? ''));
    $driverId    = trim((string)($input['driver_id'] ?? ''));
    $token       = trim((string)($input['token'] ?? ''));
    $problemType = trim((string)($input['problem_type'] ?? ''));
    $problemDetail = trim((string)($input['problem_detail'] ?? ''));
    $remarks     = trim((string)($input['remarks'] ?? ''));
    $photo       = (string)($input['photo'] ?? '');
    $tripHint    = trim((string)($input['trip_id'] ?? ''));

    $latInput = $input['latitude'] ?? null;
    $lngInput = $input['longitude'] ?? null;
    $latitude = is_numeric($latInput) ? (float)$latInput : null;
    $longitude = is_numeric($lngInput) ? (float)$lngInput : null;
    $locationStatus = ($latitude !== null && $longitude !== null) ? 'ok' : 'unavailable';

    require_driver_auth($conn, $driverId, $token);

    // Deployment guard: report the gap instead of dying on a SQL error.
    $hasTable = $conn->query("SHOW TABLES LIKE 'bus_problem_reports'");
    $hasTripFlag = false;
    $hasProblemDetail = false;
    $chkTripCols = $conn->query("SHOW COLUMNS FROM bus_problem_reports");
    if ($chkTripCols) {
        while ($c = $chkTripCols->fetch_assoc()) {
            if ($c['Field'] === 'replacement_required') $hasTripFlag = true;
            if ($c['Field'] === 'problem_detail') $hasProblemDetail = true;
        }
    }
    $chkTripCols2 = $conn->query("SHOW COLUMNS FROM bus_trips");
    if ($chkTripCols2) {
        while ($c = $chkTripCols2->fetch_assoc()) {
            if ($c['Field'] === 'replacement_required') $hasTripFlag = true;
        }
    }
    if (!$hasTable || $hasTable->num_rows === 0 || !$hasTripFlag || !$hasProblemDetail) {
        echo json_encode([
            "success" => false,
            "code" => "MIGRATION_REQUIRED",
            "message" => "Bus problem reporting is not available yet. Please contact the administrator.",
        ]);
        exit;
    }

    $allowedProblemTypes = [
        'Mechanical Problem',
        'Flat Tire',
        'Overheating',
        'Accident',
        'Fuel Problem',
        'Other',
    ];
    if ($problemType === '' || !in_array($problemType, $allowedProblemTypes, true)) {
        echo json_encode([
            "success" => false,
            "code" => "INVALID_PROBLEM_TYPE",
            "message" => "Please select a problem type.",
        ]);
        exit;
    }

    // "Other" lets the driver type the actual problem; it must be non-empty.
    if ($problemType === 'Other') {
        if ($problemDetail === '') {
            echo json_encode([
                "success" => false,
                "code" => "PROBLEM_DETAIL_REQUIRED",
                "message" => "Please describe the problem.",
            ]);
            exit;
        }
        if (strlen($problemDetail) > 120) {
            echo json_encode([
                "success" => false,
                "code" => "PROBLEM_DETAIL_TOO_LONG",
                "message" => "The problem description is too long.",
            ]);
            exit;
        }
    } elseif ($problemDetail !== '') {
        echo json_encode([
            "success" => false,
            "code" => "PROBLEM_DETAIL_NOT_ALLOWED",
            "message" => "A typed problem is only allowed for 'Other'.",
        ]);
        exit;
    }

    if (trim($photo) === '') {
        echo json_encode([
            "success" => false,
            "code" => "PHOTO_REQUIRED",
            "message" => "A photo of the problem is required.",
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
    if (!trip_matches_identifier($tripHint, $activeTrip)) {
        echo json_encode([
            "success" => false,
            "code" => "TRIP_MISMATCH",
            "message" => "The trip changed. Please try again.",
        ]);
        exit;
    }

    $tripId = (int)$activeTrip['id'];
    $tripNo = (string)$activeTrip['id'];
    $busId = (string)($activeTrip['bus_id'] ?? '');

    // Idempotency layer 1: the same tx_id never writes a second row.
    if ($txId !== '') {
        $byKey = $conn->prepare("SELECT id, status FROM bus_problem_reports WHERE identity_key = ? LIMIT 1");
        if ($byKey) {
            $byKey->bind_param("s", $txId);
            $byKey->execute();
            $existingKey = $byKey->get_result()->fetch_assoc();
            $byKey->close();
            if ($existingKey) {
                echo json_encode([
                    "success" => true,
                    "already_recorded" => true,
                    "message" => "Bus problem already reported for this trip",
                    "report_id" => (int)$existingKey['id'],
                    "status" => $existingKey['status'],
                    "trip_status" => "Interrupted",
                ]);
                exit;
            }
        }
    }

    // Idempotency layer 2: only one open report per trip.
    $openStmt = $conn->prepare(
        "SELECT id, status FROM bus_problem_reports
         WHERE trip_id = ? AND status IN ('waiting', 'acknowledged')
         LIMIT 1"
    );
    if (!$openStmt) throw new Exception("bus_problem_reports query failed: " . $conn->error);
    $openStmt->bind_param("i", $tripId);
    $openStmt->execute();
    $existingOpen = $openStmt->get_result()->fetch_assoc();
    $openStmt->close();
    if ($existingOpen) {
        echo json_encode([
            "success" => true,
            "already_recorded" => true,
            "message" => "A bus problem report is already pending. It must be resolved first before another report can be filed.",
            "report_id" => (int)$existingOpen['id'],
            "status" => $existingOpen['status'],
            "trip_status" => "Interrupted",
        ]);
        exit;
    }

    // Authoritative passenger count: same rule as scripts/get_passengers.php
    // (same trip + bus + driver, not archived, no drop-off recorded).
    $passengerCount = 0;
    $cntStmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM passenger_trips
         WHERE trip_no = ? AND bus_no = ? AND driver_id = ?
           AND archived_status = 'no'
           AND (dropoff_time IS NULL OR dropoff_time = '')"
    );
    if ($cntStmt) {
        $cntStmt->bind_param("sss", $tripNo, $busId, $driverId);
        $cntStmt->execute();
        $cntRow = $cntStmt->get_result()->fetch_assoc();
        $cntStmt->close();
        if ($cntRow) $passengerCount = (int)$cntRow['cnt'];
    }

    $photoUrl = save_bus_problem_photo($photo, $tripId . '_' . $driverId);
    if ($photoUrl === null) {
        echo json_encode([
            "success" => false,
            "code" => "PHOTO_INVALID",
            "message" => "The photo could not be read. Please take it again.",
        ]);
        exit;
    }

    $conn->begin_transaction();

    try {
        // Empty tx_id becomes NULL so the UNIQUE index still allows retries
        // that are caught by the open-report check instead of colliding.
        $identityKey = $txId !== '' ? $txId : null;
        $insStmt = $conn->prepare(
            "INSERT INTO bus_problem_reports
                (trip_id, trip_no, driver_id, bus_id, passenger_count, problem_type, problem_detail,
                 remarks, photo_url, latitude, longitude, location_status, status, identity_key, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting', ?, NOW())"
        );
        if (!$insStmt) throw new Exception("bus_problem_reports insert failed: " . $conn->error);
        $insStmt->bind_param(
            "isssissssssss",
            $tripId,
            $tripNo,
            $driverId,
            $busId,
            $passengerCount,
            $problemType,
            $problemDetail,
            $remarks,
            $photoUrl,
            $latitude,
            $longitude,
            $locationStatus,
            $identityKey
        );
        if (!$insStmt->execute()) throw new Exception("bus_problem_reports insert failed: " . $insStmt->error);
        $insStmt->close();
        $reportId = (int)$conn->insert_id;

        // The trip is now interrupted and awaits a dispatcher-assigned
        // replacement. The driver is never given a way to assign one.
        $tripStmt = $conn->prepare(
            "UPDATE bus_trips SET status = 'Interrupted', replacement_required = 1 WHERE id = ?"
        );
        if (!$tripStmt) throw new Exception("bus_trips update failed: " . $conn->error);
        $tripStmt->bind_param("i", $tripId);
        if (!$tripStmt->execute()) throw new Exception("bus_trips update failed: " . $tripStmt->error);
        $tripStmt->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    // The dispatcher to alert is the one at the terminal nearest to the
    // captured GPS fix; the trip's own terminal is the fallback when the
    // device had no fix. The driver is never asked to pick a terminal.
    $nearestTerminal = bpr_resolve_nearest_terminal($conn, $latitude, $longitude, $activeTrip);

    // -------------------------------------------------------------------------
    // Best-effort notification. A push or announcement failure must never turn
    // a saved report into an error response (same rule as scripts/add_alert.php).
    // -------------------------------------------------------------------------
    $announcementId = null;
    try {
        $driverName = '';
        $nameStmt = $conn->prepare("SELECT full_name FROM drivers WHERE driver_id = ? LIMIT 1");
        if ($nameStmt) {
            $nameStmt->bind_param("s", $driverId);
            $nameStmt->execute();
            $nameRow = $nameStmt->get_result()->fetch_assoc();
            $nameStmt->close();
            if ($nameRow) $driverName = (string)($nameRow['full_name'] ?? '');
        }

        $typeLabel = ($problemType === 'Other' && $problemDetail !== '')
            ? 'Other - ' . $problemDetail
            : $problemType;

        $title = 'Bus Problem: ' . $typeLabel;
        $message = sprintf(
            '%s reported %s on %s (Trip #%d) with %d passenger(s) onboard.',
            $driverName !== '' ? $driverName : $driverId,
            $typeLabel,
            $busId,
            $tripId,
            $passengerCount
        );
        if ($nearestTerminal['name'] !== null) {
            $message .= ' Nearest terminal: ' . $nearestTerminal['name'] . '.';
        }

        $annStmt = $conn->prepare(
            "INSERT INTO announcements (type, title, message, reported_by, driver_name) VALUES (?, ?, ?, ?, ?)"
        );
        if ($annStmt) {
            $annType = 'Bus Problem';
            $annStmt->bind_param("sssss", $annType, $title, $message, $driverId, $driverName);
            if ($annStmt->execute()) {
                $announcementId = (int)$conn->insert_id;
            }
            $annStmt->close();
        }

        // Existing Admin notification system (notifications table): one row per
        // admin/super_admin, deduplicated on (user, reference_type, reference_id,
        // title), so retries of the same report cannot create duplicates.
        $adminNotifModel = $_SERVER['DOCUMENT_ROOT'] . '/app/Models/Notification.php';
        $adminDbClass = $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
        if (file_exists($adminNotifModel) && file_exists($adminDbClass)) {
            require_once $adminDbClass;
            require_once $adminNotifModel;
            if (class_exists('App\\Models\\Notification')) {
                $adminNotifier = new \App\Models\Notification();
                $adminNotifier->createForAdminRoles(
                    'Bus Problem Reported',
                    'BUS ' . $busId . ' · ' . $typeLabel . ' · TRIP #' . $tripNo
                        . ' · Driver ' . ($driverName !== '' ? $driverName : $driverId)
                        . ' · Reported ' . date('M j, Y g:i A'),
                    'warning',
                    'bus_problem',
                    $reportId
                );
            }
        }

        $pushHelperPaths = [
            $_SERVER['DOCUMENT_ROOT'] . '/mobile/inspector/push_helper.php',
            __DIR__ . '/inspector/push_helper.php',
            __DIR__ . '/push_helper.php',
            dirname(__DIR__, 3) . '/Rconnect-Inspect/backend/mobile/inspector/push_helper.php',
        ];
        $pushHelper = null;
        foreach ($pushHelperPaths as $path) {
            if ($path && file_exists($path)) {
                $pushHelper = $path;
                break;
            }
        }
        if ($pushHelper) {
            require_once $pushHelper;
            if (function_exists('sendPushToAllInspectors')) {
                sendPushToAllInspectors($title, $message, [
                    'type' => 'bus_problem',
                    'report_id' => (string)$reportId,
                    'trip_id' => (string)$tripId,
                    'bus_id' => $busId,
                    'photo_url' => (string)$photoUrl,
                    'nearest_terminal' => $nearestTerminal['name'],
                    'nearest_terminal_id' => $nearestTerminal['id'] !== null ? (string)$nearestTerminal['id'] : null,
                ]);
            }
        }
    } catch (Throwable $e) {
        error_log("[report_bus_problem] notification skipped: " . $e->getMessage());
    }

    echo json_encode([
        "success" => true,
        "message" => "Dispatcher notified",
        "report_id" => $reportId,
        "status" => "waiting",
        "trip_status" => "Interrupted",
        "trip_id" => $tripId,
        "bus_id" => $busId,
        "passenger_count" => $passengerCount,
        "photo_url" => $photoUrl,
        "location_status" => $locationStatus,
        "nearest_terminal_id" => $nearestTerminal['id'],
        "nearest_terminal_name" => $nearestTerminal['name'],
        "nearest_terminal_source" => $nearestTerminal['source'],
        "announcement_id" => $announcementId,
    ]);

    $conn->close();

} catch (Exception $e) {
    error_log("[report_bus_problem] " . $e->getMessage());
    // The report did not commit, so its photo must not be left behind.
    if (isset($photoUrl)) {
        bpr_delete_photo($photoUrl);
    }
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "code" => "SERVER_ERROR",
        "message" => "The report could not be sent. Please try again.",
    ]);
}

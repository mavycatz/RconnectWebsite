<?php

/**
 * Rconnect-Drive driver GPS ingestion (existing pipeline, hardened).
 *
 * Payload (JSON):
 *   driver_id  string  required
 *   bus_id     string  optional; the app's resolved bus. The server resolves the
 *                      authoritative bus and reports whether the app's value matched.
 *   trip_id    mixed   optional; the driver-side active trip (bus_trips.id or
 *                      trip_number — NOT passenger_trips.id). Validated against
 *                      the driver's current trip when supplied.
 *   latitude   float   required
 *   longitude  float   required
 *   timestamp  mixed   optional; device time (epoch seconds, epoch milliseconds,
 *                      or an ISO-8601 string). Server time is authoritative.
 *   speed      float   optional; the device speed in m/s (expo-location
 *                      coords.speed). Converted to km/h before storage.
 *   heading    float   optional; degrees.
 *
 * Storage stays in the existing tables only:
 *   driver_location_history (append) and driver_current_location (upsert).
 *   driver_current_location.speed is stored in km/h and is sanitized: values
 *   outside the plausible range (0-150 km/h) are stored as 0. The destination
 *   alert engine never consumes this speed.
 *
 * TIMESTAMPS: both tables are written with an explicit Asia/Manila timestamp
 * rather than letting the column default or the session clock supply one. The
 * reader side (App\Core\Database) pins its own MySQL session to the same zone
 * and measures age with NOW(), so created_at and the clock it is compared
 * against share one basis. Previously the write depended on the session's
 * time_zone and the read depended on the server's global time_zone; the two
 * are independent, and when they differed the passenger API read every live
 * row as exactly 8 hours old, which surfaced as a spurious STALE and a blocked
 * destination alert.
 *
 * Every failure path answers JSON with success:false plus a machine-readable
 * reason and leaves the stored location unchanged.
 */

header("Content-Type: application/json");

/*
 * Bootstrap is guarded so a missing or unloadable dependency answers with a
 * JSON body instead of an unhandled fatal. This exact failure already cost the
 * whole pipeline once: location_helpers.php was never committed, the bare
 * require below threw "Failed opening required", and the endpoint returned an
 * HTML 500 with no body. The client cannot tell that apart from "no fix sent"
 * and discards the update silently. update_location_respond() is declared
 * after this block, so the failure body is emitted inline.
 */
try {
    // Buffer the bootstrap so a failed include's PHP warning (which PHP prints
    // before it throws) never lands in the response body and breaks JSON
    // parsing on servers running display_errors=On.
    ob_start();

    require_once "../config/database.php";
    require_once __DIR__ . '/lib/active_trip.php';
    require_once __DIR__ . '/lib/location_helpers.php';
    require_once __DIR__ . '/lib/driver_auth.php';

    $db = new Database();
    $conn = $db->connect();

    ob_end_clean();
} catch (\Throwable $bootstrapError) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    error_log("[updateLocation] Rejected: bootstrap_failed; " . $bootstrapError->getMessage());

    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: application/json");
    }

    echo json_encode([
        "success" => false,
        "reason" => "bootstrap_failed",
        "message" => "Location service is temporarily unavailable",
    ]);
    exit;
}
if (!function_exists('update_location_respond')) {
    function update_location_respond(array $payload)
    {
        echo json_encode($payload);
        exit;
    }
}

$data = json_decode(file_get_contents("php://input"), true);

    if (!is_array($data)) {
        error_log("[updateLocation] Rejected: invalid_payload; raw=" . substr((string) file_get_contents('php://input'), 0, 300));
        update_location_respond([
            "success" => false,
            "reason" => "invalid_payload",
            "message" => "Invalid JSON payload",
        ]);
    }

$driver_id = trim((string) ($data['driver_id'] ?? ''));
$latitude = $data['latitude'] ?? null;
$longitude = $data['longitude'] ?? null;
$reportedBusId = trim((string) ($data['bus_id'] ?? ''));
$submittedTripId = trim((string) ($data['trip_id'] ?? ''));
$deviceTimestamp = update_location_normalize_timestamp($data['timestamp'] ?? null);
$deviceSpeed = isset($data['speed']) && is_numeric($data['speed']) ? (float) $data['speed'] : null;
$deviceHeading = isset($data['heading']) && is_numeric($data['heading']) ? (float) $data['heading'] : null;

    if ($driver_id === '' || !is_numeric($latitude) || !is_numeric($longitude)) {
        error_log("[updateLocation] Rejected: missing_location_data; driver_id='" . $driver_id . "' lat=" . var_export($latitude, true) . " lng=" . var_export($longitude, true));
        update_location_respond([
            "success" => false,
            "reason" => "missing_location_data",
            "message" => "Missing location data",
        ]);
    }

$latitude = (float) $latitude;
$longitude = (float) $longitude;

if (
    !is_finite($latitude) || !is_finite($longitude)
    || $latitude < -90 || $latitude > 90
    || $longitude < -180 || $longitude > 180
    ) {
        error_log("[updateLocation] Rejected: invalid_coordinates; driver_id='" . $driver_id . "' lat=$latitude lng=$longitude");
        update_location_respond([
            "success" => false,
            "reason" => "invalid_coordinates",
            "message" => "Coordinates out of range",
        ]);
    }

if ($deviceHeading !== null && ($deviceHeading < 0 || $deviceHeading > 360)) {
    $deviceHeading = null;
}

/*
--------------------------------
AUTHENTICATE DRIVER IDENTITY
--------------------------------
*/
$token = trim((string) ($data['token'] ?? ''));
if ($token === '') {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        $token = $matches[1];
    } elseif (!empty($_SERVER['HTTP_X_DRIVER_TOKEN'])) {
        $token = trim((string) $_SERVER['HTTP_X_DRIVER_TOKEN']);
    }
}

require_driver_auth($conn, $driver_id, $token);

/*
--------------------------------
RESOLVE THE ACTIVE TRIP
--------------------------------
The driver-side trip is a bus_trips row (Scheduled/Departed/...), not the
passenger_trips row. A supplied trip_id is validated against it so a location
can never be attributed to a trip the driver is not operating.
*/

$activeTrip = resolve_active_trip($conn, $driver_id);
$tripMatched = $submittedTripId === '' ? null : false;
$tripBusId = null;

if ($submittedTripId !== '') {
    if ($activeTrip) {
        $tripMatched = trip_matches_identifier($submittedTripId, $activeTrip);
        $tripBusId = $activeTrip['bus_id'] ?? null;
    } else {
        // No Scheduled/Departed trip resolved; still accept a verifiable trip
        // for this driver (the passenger trip can be In Progress while
        // bus_trips uses another wording). Driver ownership is the check.
        $tripLookup = $conn->prepare("
            SELECT id, trip_number, bus_id
            FROM bus_trips
            WHERE driver_id = ?
              AND (id = ? OR trip_number = ?)
            ORDER BY id DESC
            LIMIT 1
        ");
        $tripLookup->bind_param("sss", $driver_id, $submittedTripId, $submittedTripId);
        $tripLookup->execute();
        $tripRow = $tripLookup->get_result()->fetch_assoc();
        $tripLookup->close();

        if ($tripRow) {
            $tripMatched = true;
            $tripBusId = $tripRow['bus_id'];
        }
    }

        if ($tripMatched !== true) {
            error_log("[updateLocation] Rejected: trip_mismatch; driver_id='$driver_id' submitted_trip_id='$submittedTripId' active_trip_id='" . ($activeTrip['id'] ?? 'none') . "'");
            update_location_respond([
            "success" => false,
            "reason" => "trip_mismatch",
            "message" => "trip_id does not match the driver's active trip",
            "driver_id" => $driver_id,
            "trip_id" => $submittedTripId,
        ]);
    }
}

/*
--------------------------------
GET BUS ID (assignment -> active trip -> supplied trip)
--------------------------------
*/

$bus_id = null;

$busQuery = $conn->prepare("
    SELECT bus_id
    FROM driver_bus_assignment
    WHERE driver_id=?
    LIMIT 1
");
$busQuery->bind_param("s", $driver_id);
$busQuery->execute();
$busRow = $busQuery->get_result()->fetch_assoc();
$busQuery->close();
$bus_id = $busRow['bus_id'] ?? null;

if (!$bus_id && $activeTrip) {
    $bus_id = $activeTrip['bus_id'] ?? null;
}

if (!$bus_id && $tripBusId) {
    $bus_id = $tripBusId;
}

    if (!$bus_id) {
        error_log("[updateLocation] Rejected: no_assigned_bus; driver_id='$driver_id' submitted_bus_id='$reportedBusId'");
        update_location_respond([
            "success" => false,
            "reason" => "no_assigned_bus",
            "message" => "Driver has no assigned bus",
            "driver_id" => $driver_id,
        ]);
    }

$busIdMatch = $reportedBusId === '' ? null : update_location_bus_matches($reportedBusId, $bus_id);

/*
--------------------------------
SIMULATION GUARD
--------------------------------
While a simulation session is active for this driver/bus, real uploads are
acknowledged but never stored: driver_location_history and
driver_current_location stay untouched, so simulated positions cannot be
overwritten by real GPS and the real tables stay free of simulated data.
With no session the pipeline below is exactly as before.
*/

$simulationGuard = false;
$simulationEngineCandidates = array(
    __DIR__ . '/../simulation/lib/simulation_engine.php',
    __DIR__ . '/simulation/lib/simulation_engine.php',
);

foreach ($simulationEngineCandidates as $simulationEngine) {
    if (file_exists($simulationEngine)) {
        require_once $simulationEngine;
        break;
    }
}

if (function_exists('simulation_upload_blocked')) {
    $simulationGuard = simulation_upload_blocked($conn, $driver_id, $bus_id);
}

if ($simulationGuard) {
    error_log("[updateLocation] Simulation active; upload acknowledged without storing. driver_id='$driver_id' bus_id='$bus_id'");

    $conn->close();

    update_location_respond([
        "success" => true,
        "message" => "Location received (simulation active; not stored)",
        "simulation_active" => true,
        "driver_id" => $driver_id,
        "bus_id" => $bus_id,
        "bus_id_reported" => $reportedBusId !== '' ? $reportedBusId : null,
        "bus_id_match" => $busIdMatch,
        "trip_id" => $submittedTripId !== '' ? $submittedTripId : null,
        "trip_match" => $tripMatched,
        "latitude" => $latitude,
        "longitude" => $longitude,
        "timestamp" => $deviceTimestamp,
        "server_current_time" => update_location_now(),
        "timezone" => update_location_timezone(),
    ]);
}

/*
--------------------------------
GET PREVIOUS LOCATION
--------------------------------
*/

$previousQuery = $conn->prepare("
    SELECT latitude, longitude, created_at
    FROM driver_location_history
    WHERE driver_id=?
    ORDER BY id DESC
    LIMIT 1
");
$previousQuery->bind_param("s", $driver_id);
$previousQuery->execute();
$previous = $previousQuery->get_result()->fetch_assoc();
$previousQuery->close();

/*
--------------------------------
RECORD THE PHILIPPINE CLOCK
--------------------------------
The moment this fix was taken, on the app's one time basis. Written explicitly
into both tables instead of relying on a CURRENT_TIMESTAMP column default, so
the stored value is the same whether the session clock is Manila, UTC, or the
server's host default.
*/

$recordedAt = update_location_now();
$storedAt = $recordedAt;

$previousDistanceKm = 0.0;
$elapsedSeconds = 0.0;

if ($previous) {
    $previousDistanceKm = update_location_distance_km(
        (float) $previous['latitude'],
        (float) $previous['longitude'],
        $latitude,
        $longitude
    );

    // The previous row's created_at is Philippine wall-clock time, and PHP's
    // default timezone is Asia/Manila (set in mobile/config/database.php), so
    // strtotime() reads it on the same basis as time() and the elapsed value
    // is a real duration rather than an 8-hour offset.
    $previousTime = strtotime($previous['created_at']);
    $currentTime = strtotime($recordedAt);
    $elapsedSeconds = $previousTime !== false && $currentTime !== false
        ? (float) ($currentTime - $previousTime)
        : 0.0;
}

/*
--------------------------------
CALCULATE + SANITIZE SPEED
--------------------------------
*/

$speed = update_location_sanitize_speed($deviceSpeed, $previousDistanceKm, $elapsedSeconds);

/*
--------------------------------
SAVE HISTORY + UPSERT CURRENT LOCATION
--------------------------------
*/

try {
    $conn->begin_transaction();

    $history = $conn->prepare("
        INSERT INTO driver_location_history (
            driver_id,
            bus_id,
            latitude,
            longitude,
            created_at
        )
        VALUES (?,?,?,?,?)
    ");
    $history->bind_param("ssdds", $driver_id, $bus_id, $latitude, $longitude, $recordedAt);
    $history->execute();
    $history->close();

    $historyId = (int) $conn->insert_id;

    $check = $conn->prepare("
        SELECT id
        FROM driver_current_location
        WHERE driver_id=?
        LIMIT 1
    ");
    $check->bind_param("s", $driver_id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {
        $stmt = $conn->prepare("
            UPDATE driver_current_location
            SET
                bus_id=?,
                latitude=?,
                longitude=?,
                speed=?,
                updated_at=?
            WHERE driver_id=?
        ");
        $stmt->bind_param("sdddss", $bus_id, $latitude, $longitude, $speed, $recordedAt, $driver_id);
        $stmt->execute();
        $stmt->close();
        $resultLabel = "Location updated";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO driver_current_location (
                driver_id,
                bus_id,
                latitude,
                longitude,
                speed,
                updated_at
            )
            VALUES (?,?,?,?,?,?)
        ");
        $stmt->bind_param("ssddds", $driver_id, $bus_id, $latitude, $longitude, $speed, $recordedAt);
        $stmt->execute();
        $stmt->close();
        $resultLabel = "Location inserted";
    }

    // Read the stored value back rather than echoing what was sent, so the
    // response proves what actually landed in the column.
    $stampStmt = $conn->prepare("
        SELECT updated_at
        FROM driver_current_location
        WHERE driver_id=?
        LIMIT 1
    ");
    $stampStmt->bind_param("s", $driver_id);
    $stampStmt->execute();
    $stampRow = $stampStmt->get_result()->fetch_assoc();
    $stampStmt->close();

    $historyStampStmt = $conn->prepare("
        SELECT created_at
        FROM driver_location_history
        WHERE id=?
        LIMIT 1
    ");
    $historyStampStmt->bind_param("i", $historyId);
    $historyStampStmt->execute();
    $historyStampRow = $historyStampStmt->get_result()->fetch_assoc();
    $historyStampStmt->close();

    $conn->commit();
} catch (\Throwable $e) {
    try {
        $conn->rollback();
    } catch (\Throwable $rollbackError) {
        // The transaction may not have started; the response below is enough.
    }

    error_log("[updateLocation] Rejected: write_failed; driver_id='$driver_id' bus_id='$bus_id' mysqli_errno=" . $conn->errno . " mysqli_error=" . $conn->error . " exception=" . $e->getMessage());
    update_location_respond([
        "success" => false,
        "reason" => "write_failed",
        "message" => "Could not store the driver location",
        "error" => $e->getMessage(),
        "mysqli_error" => $conn->error,
        "driver_id" => $driver_id,
    ]);
}

$storedAt = $historyStampRow['created_at'] ?? $recordedAt;
$updatedAt = $stampRow['updated_at'] ?? $recordedAt;
$serverCurrentTime = update_location_now();
$deviceClockSkew = $deviceTimestamp !== null
    ? (strtotime($deviceTimestamp) - strtotime($serverCurrentTime))
    : null;

$conn->close();

update_location_respond([
    "success" => true,
    "message" => $resultLabel,
    "driver_id" => $driver_id,
    "bus_id" => $bus_id,
    "bus_id_reported" => $reportedBusId !== '' ? $reportedBusId : null,
    "bus_id_match" => $busIdMatch,
    "trip_id" => $submittedTripId !== '' ? $submittedTripId : null,
    "trip_match" => $tripMatched,
    "latitude" => $latitude,
    "longitude" => $longitude,
    "speed" => $speed,
    "heading" => $deviceHeading,
    "timestamp" => $deviceTimestamp,
    "device_clock_skew_seconds" => $deviceClockSkew,
    "server_current_time" => $serverCurrentTime,
    "timezone" => update_location_timezone(),
    "driver_created_at" => $storedAt,
    "driver_location_history_id" => $historyId,
    "updated_at" => $updatedAt,
    "age_seconds" => update_location_age_seconds($storedAt, $serverCurrentTime),
]);

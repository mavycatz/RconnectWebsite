<?php

/**
 * live_map.php - the Inspector's driver-location read.
 *
 * The Inspector app calls this from two screens:
 *   app/inspector/(tabs)/InspectorHome.tsx  (home map)
 *   app/inspector/(tabs)/InspectorGPS.tsx   (Map tab)
 *
 * Both used to call it with only a bus_id, taken from the assigned_bus value
 * returned by inspector_location.php and held in local state. This endpoint
 * resolves the driver from the inspector's own checked-in assignment instead,
 * and uses the client bus_id only as a cross-check and a fallback, so a stale
 * local identifier cannot cause a valid row to be missed.
 *
 * REQUEST
 *   GET ?inspector_id=<id>&bus_id=<bus>&debug=1
 *
 *   inspector_id  Optional. The inspector making the request. When present, the
 *                 read is verified against that inspector's checked-in
 *                 assignment. When absent the request is still served, because
 *                 an app version predating this contract sends only bus_id,
 *                 but assignment_verified is false and the omission is logged.
 *   bus_id        Optional. A hint. Never overrides the assignment.
 *   debug         Optional. "1" adds the resolution trace described below.
 *
 * RESPONSE
 *   {
 *     "success": true,
 *     "inspector_id": string|null,
 *     "trip_id": int|string|null,
 *     "bus_id": string|null,
 *     "driver_id": string|null,
 *     "resolution_source": "inspector_assignment" | "bus_trip_by_bus_id"
 *                        | "driver_bus_assignment" | "none",
 *     "bus_id_mismatch": bool,
 *     "assignment_verified": bool,
 *     "assignment_verify_reason": string,
 *     "matched_on": string|null,
 *     "location_key_column": string|null,
 *     "location_found": bool,
 *     "driver_location": {
 *       "driver_id": string|null,
 *       "latitude": float,
 *       "longitude": float,
 *       "speed": float|null,
 *       "updated_at": string|null,
 *       "age_seconds": int|null,
 *       "is_stale": bool|null
 *     } | null,
 *     "driver_lat": float|null,
 *     "driver_lng": float|null,
 *     "server_current_time": string,
 *     "timezone": string,
 *     "message": string|null
 *   }
 *
 *   driver_lat / driver_lng are the flat keys the currently deployed app reads.
 *   They are the same values as driver_location.latitude / .longitude and are
 *   null when no row was found - never 0, which a truthiness check on the
 *   client would read as a real coordinate off the coast of Africa.
 *
 *   age_seconds is signed and is_stale is its reading. Both are null when
 *   updated_at is absent or unreadable. is_stale is a reported flag only: it
 *   never withholds a location.
 *
 *   message is null when a location was reported, and otherwise names the
 *   reason: a schema problem, driver_not_resolved:<stage>, a read error,
 *   coordinates_unusable, or no_location_row_for_driver.
 *
 * DEBUG=1 RESPONSE
 *   Adds "trace": one entry per resolution stage, each with the value it was
 *   given, whether it was attempted, how many rows it returned, and the column
 *   or value that matched; plus "first_failure", the first stage that produced
 *   nothing (null whenever a driver was resolved). The location stage includes
 *   the probed driver_current_location column list and the emitted SQL, since
 *   the identifier is the whole question. The trace never carries a connection
 *   string or a credential, and it is not an authorization mechanism: a debug
 *   request is verified exactly like any other.
 *
 * THE RULE THIS FILE EXISTS TO KEEP
 *   No timestamp condition may exclude an existing row. There is no staleness
 *   window in the query below, and age is computed in PHP against the clock
 *   basis declared in lib/driver_location_lookup.php rather than against the
 *   MySQL session clock. A UTC session comparing against Manila-stored text is
 *   what previously made every live row read as eight hours old.
 *
 *   Nothing here writes. Driver-side tracking and the writers of
 *   driver_current_location and driver_location_history are untouched.
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/lib/driver_location_lookup.php";

/**
 * Emit a JSON body and stop.
 *
 * @param array $payload Response body.
 * @param int   $status  HTTP status.
 */
function live_map_respond(array $payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

/**
 * Open a connection using the same probe order as the sibling endpoints.
 *
 * @return mysqli|null
 */
function live_map_connect()
{
    $conn = null;

    $configPaths = array(
        __DIR__ . "/../config/database.php",
        __DIR__ . "/../../config/database.php",
        __DIR__ . "/config/database.php",
        $_SERVER['DOCUMENT_ROOT'] . "/mobile/config/database.php",
        $_SERVER['DOCUMENT_ROOT'] . "/config/database.php",
        $_SERVER['DOCUMENT_ROOT'] . "/app/Core/Database.php",
    );

    foreach ($configPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            if (class_exists('Database')) {
                $db = new Database();
                $conn = $db->connect();
                if ($conn) {
                    break;
                }
            }
        }
    }

    if (!$conn && file_exists(__DIR__ . "/../../../scripts/db_config.php")) {
        require_once __DIR__ . "/../../../scripts/db_config.php";
    }

    return $conn ?: null;
}

/**
 * Run a prepared SELECT and return its first row, or null.
 *
 * @param mysqli $conn  Connection.
 * @param string $sql   Statement with a single ? placeholder.
 * @param string $value Value to bind.
 * @return array|null
 */
function live_map_first_row($conn, $sql, $value)
{
    $stmt = @$conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $bound = $value;
    if (!@$stmt->bind_param('s', $bound) || !@$stmt->execute()) {
        return null;
    }

    if (method_exists($stmt, 'get_result')) {
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
    } else {
        $row = @$stmt->fetch_assoc();
    }

    return is_array($row) ? $row : null;
}

/**
 * The inspector's checked-in assignment, if there is one.
 *
 * @param mysqli $conn        Connection.
 * @param string $inspectorId Inspector identifier.
 * @return array|null
 */
function live_map_checked_in_assignment($conn, $inspectorId)
{
    return live_map_first_row(
        $conn,
        "SELECT id, trip_id, bus_id, status
         FROM inspector_assignments
         WHERE inspector_id = ? AND status = 'Checked-In'
         ORDER BY id DESC
         LIMIT 1",
        $inspectorId
    );
}

/**
 * A trip by its own identifier.
 *
 * @param mysqli $conn   Connection.
 * @param mixed  $tripId bus_trips.id.
 * @return array|null
 */
function live_map_trip_by_id($conn, $tripId)
{
    if (!is_numeric($tripId)) {
        return null;
    }

    return live_map_first_row(
        $conn,
        "SELECT id, bus_id, driver_id, status FROM bus_trips WHERE id = ? LIMIT 1",
        (string) (int) $tripId
    );
}

/**
 * The latest open trip for any of the candidate bus values.
 *
 * Each candidate is tried against bus_trips.bus_id first, because that column
 * has been observed holding both the bus code and the numeric id. Failing
 * that, each candidate is resolved against the buses table and the resolved
 * values are tried in turn, which is the join get_passengers.php performs with
 * its plate_number subquery.
 *
 * @param mysqli  $conn       Connection.
 * @param string[] $candidates Candidate bus values.
 * @return array{row: array|null, matched_on: string|null}
 */
function live_map_trip_by_bus($conn, array $candidates)
{
    if (empty($candidates)) {
        return array('row' => null, 'matched_on' => null);
    }

    $tripSql = "SELECT id, bus_id, driver_id, status
                FROM bus_trips
                WHERE bus_id = ? AND status NOT IN ('Completed', 'Cancelled')
                ORDER BY id DESC
                LIMIT 1";

    foreach ($candidates as $candidate) {
        $row = live_map_first_row($conn, $tripSql, (string) $candidate);
        if ($row !== null) {
            return array('row' => $row, 'matched_on' => 'bus_trips.bus_id=' . $candidate);
        }
    }

    // Resolve the candidate to a bus, then try the trip again with that value.
    $busColumns = inspector_location_bus_columns(inspector_location_table_columns($conn, 'buses'));
    foreach ($busColumns as $column) {
        foreach ($candidates as $candidate) {
            $sql = "SELECT bus_id FROM buses WHERE `" . $column . "` = ? LIMIT 1";
            $stmt = @$conn->prepare($sql);
            if (!$stmt) {
                continue;
            }

            $bound = (string) $candidate;
            if (!@$stmt->bind_param('s', $bound) || !@$stmt->execute()) {
                continue;
            }

            $busId = null;
            if (method_exists($stmt, 'get_result')) {
                $result = $stmt->get_result();
                $busRow = $result ? $result->fetch_assoc() : null;
                if (is_array($busRow) && isset($busRow['bus_id'])) {
                    $busId = trim((string) $busRow['bus_id']);
                }
            }

            if ($busId === null || $busId === '') {
                continue;
            }

            $row = live_map_first_row($conn, $tripSql, $busId);
            if ($row !== null) {
                return array(
                    'row' => $row,
                    'matched_on' => 'buses.' . $column . '=' . $candidate . ' -> bus_trips.bus_id=' . $busId,
                );
            }
        }
    }

    return array('row' => null, 'matched_on' => null);
}

/**
 * The standing driver assigned to any of the candidate bus values.
 *
 * Last resort only. driver_bus_assignment reflects a standing assignment
 * rather than who is driving the trip, which is why updateLocation.php prefers
 * the active trip over it.
 *
 * @param mysqli   $conn       Connection.
 * @param string[] $candidates Candidate bus values.
 * @return array{row: string|null, matched_on: string|null}
 */
function live_map_driver_by_bus($conn, array $candidates)
{
    if (empty($candidates)) {
        return array('row' => null, 'matched_on' => null);
    }

    $columns = inspector_location_bus_columns(inspector_location_table_columns($conn, 'driver_bus_assignment'));

    foreach ($columns as $column) {
        foreach ($candidates as $candidate) {
            $sql = "SELECT driver_id FROM driver_bus_assignment WHERE `" . $column . "` = ? LIMIT 1";
            $stmt = @$conn->prepare($sql);
            if (!$stmt) {
                continue;
            }

            $bound = (string) $candidate;
            if (!@$stmt->bind_param('s', $bound) || !@$stmt->execute()) {
                continue;
            }

            if (!method_exists($stmt, 'get_result')) {
                continue;
            }

            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            if (is_array($row) && isset($row['driver_id']) && trim((string) $row['driver_id']) !== '') {
                return array(
                    'row' => trim((string) $row['driver_id']),
                    'matched_on' => 'driver_bus_assignment.' . $column . '=' . $candidate,
                );
            }
        }
    }

    return array('row' => null, 'matched_on' => null);
}

$conn = live_map_connect();
if (!$conn) {
    live_map_respond(array(
        "success" => false,
        "message" => "Database connection failed",
        "driver_location" => null,
        "driver_lat" => null,
        "driver_lng" => null,
    ), 500);
}

$inspectorId = trim((string) ($_GET['inspector_id'] ?? ''));
$busIdParam = trim((string) ($_GET['bus_id'] ?? ''));
$debug = in_array(strtolower(trim((string) ($_GET['debug'] ?? ''))), array('1', 'true', 'yes'), true);

$response = array(
    "success" => true,
    "inspector_id" => $inspectorId === '' ? null : $inspectorId,
    "trip_id" => null,
    "bus_id" => $busIdParam === '' ? null : $busIdParam,
    "driver_id" => null,
    "resolution_source" => 'none',
    "bus_id_mismatch" => false,
    "assignment_verified" => false,
    "assignment_verify_reason" => null,
    "matched_on" => null,
    "location_key_column" => null,
    "location_found" => false,
    "driver_location" => null,
    "driver_lat" => null,
    "driver_lng" => null,
    "simulation" => false,
    "server_current_time" => inspector_location_now(),
    "timezone" => inspector_location_timezone(),
    "message" => null,
);

try {
    $resolution = inspector_location_resolve_driver(
        array(
            'checked_in_assignment' => function ($id) use ($conn) {
                return live_map_checked_in_assignment($conn, $id);
            },
            'trip_by_id' => function ($tripId) use ($conn) {
                return live_map_trip_by_id($conn, $tripId);
            },
            'trip_by_bus' => function ($candidates) use ($conn) {
                return live_map_trip_by_bus($conn, $candidates);
            },
            'driver_by_bus' => function ($candidates) use ($conn) {
                return live_map_driver_by_bus($conn, $candidates);
            },
        ),
        array(
            'inspector_id' => $inspectorId,
            'bus_id' => $busIdParam,
            'bus_candidates' => inspector_location_bus_candidates($busIdParam),
        )
    );

    $response['trip_id'] = $resolution['trip_id'];
    $response['bus_id'] = $resolution['bus_id'] !== null ? $resolution['bus_id'] : $response['bus_id'];
    $response['driver_id'] = $resolution['driver_id'];
    $response['resolution_source'] = $resolution['resolution_source'];
    $response['bus_id_mismatch'] = $resolution['bus_id_mismatch'];
    $response['matched_on'] = $resolution['matched_on'];

    $verification = inspector_location_assignment_verification($inspectorId, $resolution);
    $response['assignment_verified'] = $verification['verified'];
    $response['assignment_verify_reason'] = $verification['reason'];

    if ($verification['should_log']) {
        error_log('[live_map] inspector_id missing; served an unverified driver location. bus_id='
            . ($busIdParam === '' ? '(none)' : $busIdParam));
    }

    $payload = null;
    $readError = null;
    $traceStages = array();

    /*
     * Simulation override (read-only). An active session for the resolved
     * bus/driver is the authoritative bus position and is resolved BEFORE the
     * stored row, so a simulated bus is reported even while the real GPS row
     * is absent. With no session the path below is exactly as before.
     */
    $simulation = array('active' => false);
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

    if (function_exists('simulation_override_for')) {
        $simulation = simulation_override_for(
            $conn,
            $resolution['bus_id'] !== null ? (string) $resolution['bus_id'] : null,
            $resolution['driver_id'] !== null ? (string) $resolution['driver_id'] : null
        );
    }

    if ($simulation['active'] === true) {
        $simulatedAt = isset($simulation['updated_at']) ? (string) $simulation['updated_at'] : null;
        $simulatedAge = inspector_location_age_seconds($simulatedAt, inspector_location_now());

        $payload = array(
            'driver_id' => $resolution['driver_id'] !== null
                ? (string) $resolution['driver_id']
                : (isset($simulation['driver_id']) ? $simulation['driver_id'] : null),
            'latitude' => (float) $simulation['latitude'],
            'longitude' => (float) $simulation['longitude'],
            'speed' => isset($simulation['speed_kmh']) ? (float) $simulation['speed_kmh'] : null,
            'updated_at' => $simulatedAt,
            'age_seconds' => $simulatedAge,
            'is_stale' => inspector_location_is_stale($simulatedAge),
        );

        $response['simulation'] = true;
    }

    $locationColumns = array();
    $schemaProblem = null;
    $keyColumn = null;

    if ($payload === null) {
        $locationColumns = inspector_location_table_columns($conn, INSPECTOR_LOCATION_TABLE);
        $schemaProblem = inspector_location_schema_problem($locationColumns);
        $keyColumn = $schemaProblem === null
            ? inspector_location_location_key_column($locationColumns)
            : null;
    }

    $response['location_key_column'] = $keyColumn;

    if ($payload === null && $schemaProblem === null && $resolution['driver_id'] !== null) {
        $hasUpdatedAt = inspector_location_column_in($locationColumns, 'updated_at') !== null;

        $read = inspector_location_read_row(
            $conn,
            $resolution['driver_id'],
            $keyColumn,
            $hasUpdatedAt,
            $locationColumns
        );

        $readError = $read['error'];
        $payload = inspector_location_location_payload($read['row'], $read['key_column']);

        if ($debug) {
            $traceStages['location_row'] = array(
                'input' => $resolution['driver_id'],
                'attempted' => true,
                'rows' => is_array($read['row']) ? 1 : 0,
                'sql' => $read['sql'],
                'error' => $readError,
            );
        }
    } elseif ($payload === null && $schemaProblem === null) {
        if ($debug) {
            $traceStages['location_row'] = array(
                'input' => null,
                'attempted' => false,
                'skipped' => 'no driver resolved',
            );
        }
    }

    if ($debug) {
        $traceStages['location_table'] = array(
            'columns' => $locationColumns,
            'key_column' => $keyColumn,
            'schema_problem' => $schemaProblem,
        );
    }

    $response['driver_location'] = $payload;
    $response['location_found'] = $payload !== null;

    // The legacy flat keys the deployed app reads, taken from the same payload
    // so the two can never disagree. Null, never 0, when nothing was found.
    $response['driver_lat'] = $payload !== null ? $payload['latitude'] : null;
    $response['driver_lng'] = $payload !== null ? $payload['longitude'] : null;

    if ($payload === null) {
        $response['message'] = inspector_location_absent_reason(
            $schemaProblem,
            $resolution['driver_id'],
            $readError,
            $payload,
            $resolution
        );
    }

    if ($debug) {
        $response['trace'] = inspector_location_trace($resolution, $traceStages);
    }
} catch (Throwable $e) {
    error_log('[live_map] ' . get_class($e) . ': ' . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

live_map_respond($response);

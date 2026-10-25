<?php
/**
 * inspector_location.php
 *
 * Records the signed-in inspector's position, one row per inspector, and answers
 * with the bus that position resolves to.
 *
 * ==========================================================================
 * WHAT THIS ENDPOINT REPLACES, AND WHY
 * ==========================================================================
 *
 * The deployed version of this file appended a row to `inspectors_location` on
 * every call. The app posts on a 20 s / 25 m throttle while a screen is open, so
 * a single trip produced thousands of rows for one inspector, and any consumer
 * that wanted "where is this inspector now" had to guess which row was newest.
 * That guess is `ORDER BY id DESC LIMIT 1` in one reader and `MAX(updated_at)`
 * in another, and the two disagree the moment a clock differs.
 *
 * This version writes through an upsert instead, so the table holds one row per
 * inspector and "now" is the row. The write is
 * `INSERT ... ON DUPLICATE KEY UPDATE` against `inspector_id`.
 *
 * ==========================================================================
 * DEPLOYMENT GATE - READ BEFORE DEPLOYING (task 9.1)
 * ==========================================================================
 *
 * An `INSERT ... ON DUPLICATE KEY UPDATE` is only an upsert when a unique index
 * exists on the key column. This deployment has no schema migration in
 * this repository, so the index cannot be created from here. Without a unique
 * index MySQL accepts the statement and **appends**, which is precisely the
 * behaviour this file exists to replace - it would look correct in testing and
 * silently keep growing the table in production.
 *
 * Confirm before deploying:
 *
 *     SHOW INDEX FROM inspectors_location;
 *
 * A row whose `Key_name` is `PRIMARY` or `uniq` on `inspector_id` is what this
 * endpoint requires.
 *
 * Task 9.1 probes the deployed endpoint to establish whether it appends or
 * upserts, and records the result in
 * `openspec/changes/fix-inspector-location-and-map-resilience/deployed-endpoint-capture.md`.
 *
 * **If that probe shows the deployed file appends AND anything outside this
 * repository reads that history, stop and escalate** rather than deploying this
 * version. Escalating is cheaper than discovering afterwards that a consumer
 * depended on the row-per-post shape.
 *
 * ==========================================================================
 * REQUEST
 * ==========================================================================
 *
 * POST only. A JSON body and a form post are both accepted, because the same
 * file is called from the app and from tools that do not set a content type.
 *
 *   inspector_id   string, required
 *   latitude       number, required
 *   longitude      number, required
 *
 * Coordinate rule: `latitude` and `longitude` must each be a finite number.
 * Absent, non-numeric, NAN and INF are all refused, and **no query is issued**
 * for a refused request - not the write, and not the assignment read. A refused
 * coordinate is never coerced to 0: storing 0 would place the inspector at Null
 * Island, which reads as a real position and is worse than having no row.
 *
 * ==========================================================================
 * UPSERT KEY
 * ==========================================================================
 *
 * `inspector_id`, and only `inspector_id`. It appears in the column list and in
 * the VALUES clause, and it is excluded from the update clause: a key column that
 * updates itself is a no-op at best and a row-moving accident at worst. Every
 * other written column is in the update clause, which is what makes this an
 * upsert rather than an insert-if-absent.
 *
 * Optional columns (`bus_id`, `updated_at`, `accuracy`, `speed`) are written only
 * when `SHOW COLUMNS` reports that this table declares them, so the endpoint works
 * against a table that has never heard of `accuracy`.
 *
 * ==========================================================================
 * RESPONSE
 * ==========================================================================
 *
 * Always JSON, always the same shape. `assigned_bus` is load-bearing: the app
 * gates its trip poll and every route layer on it, so it is read from the server
 * by `inspector_write_assigned_bus()` and is never echoed back from the request.
 *
 * Success:
 *
 *     {
 *       "success": true,
 *       "message": "stored",
 *       "assigned_bus": "BUS-1234" | null,
 *       "latitude": 14.5995,
 *       "longitude": 120.9842,
 *       "updated_at": "2026-10-04 09:15:00"
 *     }
 *
 * Simulation-controlled (task 9.2): while a simulation controls this inspector
 * the device write is refused and the simulated position is returned instead, so
 * the app renders the simulated marker without classifying the response as a
 * failed sync. Additive: the same success shape with `message` `simulation_active`
 * and `simulation: true`.
 *
 *     {
 *       "success": true,
 *       "message": "simulation_active",
 *       "assigned_bus": "BUS-1234" | null,
 *       "latitude": 14.6,
 *       "longitude": 120.9,
 *       "simulation": true,
 *       "updated_at": "2026-10-04 09:15:00"
 *     }
 *
 * Failure - `success` is false, `message` says which of these it was, and the
 * client can act on each one differently:
 *
 *     method_not_allowed      a method other than POST arrived
 *     inspector_id_required   no inspector id on an otherwise valid request
 *     latitude_unusable       latitude absent, non-numeric, NAN or INF
 *     longitude_unusable      longitude absent, non-numeric, NAN or INF
 *     schema_problem          the table does not have the columns this write
 *                             needs; the schema_problem key names them
 *     database_unavailable    no connection could be established
 *     write_failed            the statement would not prepare
 *     storage_failed          the statement prepared but did not store
 *
 * `assigned_bus`, `latitude`, `longitude` and `updated_at` are omitted on
 * failure rather than sent as null, so a caller cannot mistake a failed write
 * for a write that stored nothing because the inspector has no bus.
 *
 * ==========================================================================
 * ERROR HANDLING
 * ==========================================================================
 *
 * `@$conn->prepare($sql)`: a driver error here means the statement text is
 * wrong, and MySQL's message quotes that text back - which includes the table
 * name and every column name. It is suppressed and reported as a code instead.
 *
 * `catch (Throwable $e)` wraps the whole body so no path can end in a PHP fatal
 * or an HTML error page: the client parses every response as JSON, and an HTML
 * error is indistinguishable from a network failure to it.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// A preflight never touches the database: it is answered from the headers above.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Answer with JSON and stop. Every exit from this file goes through here.
 *
 * @param bool   $success Whether the write happened.
 * @param string $message One of the documented messages.
 * @param array  $extra   Additional keys for this outcome.
 */
function inspector_write_answer($success, $message, array $extra = array())
{
    echo json_encode(array_merge(array(
        'success' => $success,
        'message' => $message,
    ), $extra));

    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        inspector_write_answer(false, 'method_not_allowed');
    }

    require_once __DIR__ . '/lib/driver_location_lookup.php';

    // A JSON body and a form post are both accepted. The form values are only
    // consulted when the body was not JSON, so a JSON body is never overwritten
    // by an empty $_POST.
    $raw = file_get_contents('php://input');
    $body = json_decode((string) $raw, true);

    if (!is_array($body)) {
        $body = $_POST;
    }

    // Decided before a connection is opened. A refused request must issue no
    // query at all, and the cheapest way to be sure of that is to never reach the
    // database.
    $request = inspector_location_write_request(
        isset($body['inspector_id']) ? $body['inspector_id'] : null,
        isset($body['latitude']) ? $body['latitude'] : null,
        isset($body['longitude']) ? $body['longitude'] : null
    );

    if (!$request['ok']) {
        // Answered here rather than through the shared answer helper so the
        // refusal names its own reason on the wire: the app classifies a malformed
        // request differently from a write that failed, and it can only do that if
        // the reason survives to the response.
        echo json_encode(array(
            'success' => false,
            'message' => $request['reason'],
        ));

        exit;
    }

    $value = $request['value'];

    $configPaths = array(
        __DIR__ . '/../config/database.php',
        __DIR__ . '/../../config/database.php',
        __DIR__ . '/config/database.php',
        $_SERVER['DOCUMENT_ROOT'] . '/mobile/config/database.php',
        $_SERVER['DOCUMENT_ROOT'] . '/config/database.php',
        $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php',
    );

    $conn = null;

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

    if (!$conn && file_exists(__DIR__ . '/../../../scripts/db_config.php')) {
        require_once __DIR__ . '/../../../scripts/db_config.php';
    }

    if (!$conn) {
        inspector_write_answer(false, 'database_unavailable');
    }

    // Simulation guard (task 9.2). While a simulation controls this inspector the
    // device write is refused and the simulated coordinates are answered, so the
    // app can follow the simulated bus without treating this as a failed sync.
    // Additive: with no controlling simulation the write below runs as before.
    $simulationEngineCandidates = array(
        __DIR__ . '/../simulation/lib/simulation_engine.php',
        __DIR__ . '/simulation/lib/simulation_engine.php',
        __DIR__ . '/../../mobile/simulation/lib/simulation_engine.php',
    );

    foreach ($simulationEngineCandidates as $simulationEngine) {
        if (file_exists($simulationEngine)) {
            require_once $simulationEngine;
            break;
        }
    }

    if (function_exists('simulation_inspector_override_for')) {
        $simulationGuard = simulation_inspector_override_for($conn, $value['inspector_id']);

        if (($simulationGuard['active'] ?? false) === true) {
            inspector_write_answer(true, 'simulation_active', array(
                'assigned_bus' => inspector_write_assigned_bus($conn, $value['inspector_id'], $value['latitude'], $value['longitude']),
                'latitude' => $simulationGuard['latitude'],
                'longitude' => $simulationGuard['longitude'],
                'simulation' => true,
                'updated_at' => inspector_location_now(),
            ));
        }
    }

    // The table is asked what it has, rather than assumed. A missing column would
    // otherwise surface as a driver error naming a column, which says nothing
    // about what the table actually looks like.
    $columns = inspector_location_table_columns($conn, INSPECTOR_WRITE_TABLE);

    $schemaProblem = inspector_location_write_schema_problem($columns);

    if ($schemaProblem !== null) {
        inspector_write_answer(false, 'schema_problem', array(
            'schema_problem' => $schemaProblem,
        ));
    }

    // The key column first, then the required values, then only the optional
    // columns this table declares.
    $columnsOut = array('inspector_id', 'latitude', 'longitude');

    $rows = array(
        'inspector_id' => $value['inspector_id'],
        'latitude' => $value['latitude'],
        'longitude' => $value['longitude'],
    );

    $types = '';
    $bindValues = array();

    foreach ($columnsOut as $column) {
        $bindValues[] = &$rows[$column];

        if (is_int($rows[$column])) {
            $types .= 'i';
        } elseif (is_float($rows[$column])) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }

    // `updated_at` is written from the shared clock basis in the lib, never from
    // the session clock: the readers age rows against that same basis, and a
    // server in one zone writing a stamp in another is what made a fresh row read
    // as stale.
    foreach (inspector_location_write_optional_columns($columns) as $optional) {
        if (array_key_exists($optional, $rows) || strcasecmp($optional, 'updated_at') !== 0) {
            // An optional column the app did not send is left to its column
            // default rather than written as null, so adding one to the table
            // cannot start nulling a column another deployment populates.
            continue;
        }

        $rows[$optional] = inspector_location_now();
        $columnsOut[] = $optional;
        $bindValues[] = &$rows[$optional];
        $types .= 's';
    }

    $placeholders = implode(',', array_fill(0, count($columnsOut), '?'));

    // Every identifier below is either a constant from the lib or a column name
    // this endpoint chose. The guard below is what makes that statement checkable
    // rather than merely intended: a column name that is not a bare identifier
    // cannot reach the query text, which is the only place user input could
    // otherwise become SQL.
    if (!preg_match('/^[A-Za-z0-9_]+$/', INSPECTOR_WRITE_TABLE)) {
        inspector_write_answer(false, 'schema_problem', array(
            'schema_problem' => INSPECTOR_WRITE_TABLE . ' is not a usable table name',
        ));
    }

    foreach ($columnsOut as $column) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            inspector_write_answer(false, 'schema_problem', array(
                'schema_problem' => 'refusing to write a column named ' . $column,
            ));
        }
    }

    $update = array();

    foreach ($columnsOut as $column) {
        if (strcasecmp($column, 'inspector_id') === 0) {
            // The key column is excluded from its own update clause.
            continue;
        }

        $update[] = $column . ' = VALUES(' . $column . ')';
    }

    $sql = 'INSERT INTO ' . INSPECTOR_WRITE_TABLE
        . ' (' . implode(',', $columnsOut) . ')'
        . ' VALUES (' . $placeholders . ')'
        . ' ON DUPLICATE KEY UPDATE ' . implode(',', $update);

    $stmt = @$conn->prepare($sql);

    if (!$stmt) {
        inspector_write_answer(false, 'write_failed');
    }

    $stmt->bind_param($types, ...$bindValues);

    if (!$stmt->execute()) {
        inspector_write_answer(false, 'storage_failed');
    }

    $storedAt = inspector_location_now();

    inspector_write_answer(true, 'stored', array(
        // Read from the server, never echoed from the request: the whole point of
        // this endpoint is that the app learns its assignment from it.
        'assigned_bus' => inspector_write_assigned_bus($conn, $value['inspector_id'], $value['latitude'], $value['longitude']),
        'latitude' => $value['latitude'],
        'longitude' => $value['longitude'],
        'updated_at' => $storedAt,
    ));
} catch (Throwable $e) {
    // Nothing is echoed: the exception text can quote the statement. The client
    // gets a class name it can log and nothing it cannot.
    inspector_write_answer(false, 'storage_failed', array(
        'error' => get_class($e),
    ));
}
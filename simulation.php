<?php

/**
 * R-CONNECT shared GPS route simulation - controller main file.
 *
 * Reachable anytime at the website root (for example:
 *   https://rconnecttms.online/simulation.php
 * ). No login is required - this is a demo/defense tool, not an operational
 * screen. It drives any number of simulated buses at once (one session per
 * bus) and serves its JSON actions:
 *
 *   GET  ?action=options     -> buses with today's Scheduled/Departed trips
 *   GET  ?action=sessions    -> every non-disabled session, newest first
 *   GET  ?action=inspectors  -> inspector ids for the pickup selector
 *   GET  ?action=state       -> current simulation state (session_id | bus_id | driver_id | latest)
 *   POST ?action=control     -> {"control":"enable|disable|start|pause|resume|reset", "session_id":...}
 *   POST ?action=control     -> {"control":"pinagpanaan","inspector_id":..., ...}
 *   GET  ?action=tick        -> advance the running position and record arrivals
 *
 * `enable` replaces a bus's session when it belongs to a different trip. The
 * engine would otherwise reuse the stale session and keep writing simulated
 * boardings under the old trip id - rows the driver and inspector apps never
 * query, which reads as "passengers are not saved". The picker likewise flags
 * a trip as simulated only when the bus's session is for that exact trip.
 *
 * The console page lists every session and polls once per 2 s: each running
 * session is ticked (`tick`), the others are read (`state`); `options` feeds
 * the Enable picker and `sessions` the table. While the page is closed the
 * buses freeze at their last position - reopen it and Start/Resume per row.
 *
 * Simulated positions are stored only in `simulation_sessions` /
 * `simulation_stop_events`. Real GPS tables are never written from here, and
 * the movement source is the hard-coded coordinate config in
 * mobile/simulation/lib/simulation_routes.php.
 *
 * Inspector demo (`pinagpanaan`): per session, arms pickup at the selected
 * inspector's last reported position (from `inspectors_location`), falling back
 * to the Pinagpanaan (7/11) stop only when the inspector has no usable
 * position. The console then stops only that bus when it reaches that point so
 * the inspector can enter it and run the checklist. The bus itself is never
 * moved by this action - it keeps running the route from where it is. This is
 * the one place this tool writes an operational location table, and only when
 * the console asks it to.
 *
 * Remove or rename this file before production.
 */

date_default_timezone_set('Asia/Manila');
header('X-Robots-Tag: noindex, nofollow');

require_once __DIR__ . '/mobile/config/database.php';
require_once __DIR__ . '/mobile/simulation/lib/simulation_engine.php';

/* Inspector demo: the Pinagpanaan (7/11) stop on the Cabanatuan-Rizal route. */
define('SIMULATION_PINAGPANAAN_LAT', 15.548367);
define('SIMULATION_PINAGPANAAN_LNG', 120.933501);
define('SIMULATION_PINAGPANAAN_NAME', 'Pinagpanaan (7/11)');

/* The engine revision this console expects from the deployed engine file. */
define('SIMULATION_CONSOLE_ENGINE_REVISION', '2026-10-17-speed-presets');

$db = new Database();
$conn = $db->connect();

$action = isset($_GET['action']) ? strtolower(trim((string) $_GET['action'])) : '';

if ($action !== '') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $input = array();
    $raw = file_get_contents('php://input');

    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    foreach ($_GET as $key => $value) {
        if ($key !== 'action' && !isset($input[$key])) {
            $input[$key] = $value;
        }
    }

    switch ($action) {
        case 'options':
            echo json_encode(simulation_result(true, 'Options loaded', simulation_options($conn)));
            break;

        case 'inspectors':
            echo json_encode(simulation_result(true, 'Inspectors loaded', simulation_inspector_options($conn)));
            break;

        case 'sessions':
            echo json_encode(simulation_result(true, 'Simulation sessions', simulation_console_sessions($conn)));
            break;

        case 'state':
            $session = simulation_resolve_requested_session($conn, $input);
            echo json_encode(simulation_result(
                true,
                $session ? 'Simulation state' : 'No active simulation',
                $session ? simulation_session_payload($conn, $session) : array('active' => false)
            ));
            break;

        case 'control':
            $controlAction = isset($input['control']) ? $input['control'] : (isset($input['control_action']) ? $input['control_action'] : '');

            if (strtolower(trim((string) $controlAction)) === 'pinagpanaan') {
                echo json_encode(simulation_pinagpanaan($conn, $input));
            } else {
                echo json_encode(simulation_console_control($conn, $controlAction, $input));
            }
            break;

        case 'tick':
            $session = simulation_resolve_requested_session($conn, $input);

            if (!$session) {
                echo json_encode(simulation_result(false, 'No active simulation session'));
                break;
            }

            $tickResult = simulation_tick($conn, $session);

            /* Console-side boarding fallback: the engine file deploys
               separately and can lag behind this console. While it does, this
               pass boards the waiting riders at stops the bus has already
               reached with the corrected passenger-account write. Once the
               engine reports the fixed revision this is skipped entirely. */
            if (!empty($tickResult['success']) && simulation_console_engine_outdated()) {
                $ticked = simulation_find_session($conn, (int) $session['id']);

                if ($ticked && simulation_console_board_arrived_passengers($conn, $ticked)) {
                    $tickResult['data'] = simulation_session_payload($conn, $ticked);
                }
            }

            echo json_encode($tickResult);
            break;

        case 'diagnose':
            /* Read-only diagnostics: why a running session has no passengers.
               Reports the engine/console revisions, schema readiness, and per
               direction whether route stops + fares resolve a boardable pair
               (with the stop and fare names involved), plus the latest
               session's passenger counters. Nothing is written. */
            $report = array(
                'engine_revision' => function_exists('simulation_engine_revision') ? simulation_engine_revision() : null,
                'console_revision' => SIMULATION_CONSOLE_ENGINE_REVISION,
                'tables_ready' => function_exists('simulation_tables_ready') ? simulation_tables_ready($conn) : false,
                'passenger_schema_ready' => function_exists('simulation_passenger_schema_ready') ? simulation_passenger_schema_ready($conn) : false,
                'directions' => array(),
            );

            $config = function_exists('simulation_config') ? simulation_config() : array();

            foreach (array_keys((array) ($config['directions'] ?? array())) as $direction) {
                $entry = array(
                    'direction' => $direction,
                    'route_loaded' => false,
                    'stops' => 0,
                    'fares' => 0,
                    'resolvable_pairs' => 0,
                    'unresolved_stops' => array(),
                    'stop_names' => array(),
                    'fare_destinations' => array(),
                );

                if (function_exists('simulation_load_fare_route') && function_exists('simulation_resolve_passenger_fare')) {
                    $route = simulation_load_fare_route($conn, $direction);

                    if (is_array($route)) {
                        $entry['route_loaded'] = true;
                        $entry['stops'] = count($route['stops']);
                        $entry['fares'] = count($route['fares']);
                        $entry['stop_names'] = array_map(function ($stop) {
                            return (string) ($stop['stop_name'] ?? '');
                        }, $route['stops']);
                        $entry['fare_destinations'] = array_map(function ($fare) {
                            return (string) ($fare['destination'] ?? '');
                        }, $route['fares']);

                        $stopCount = count($route['stops']);

                        for ($i = 0; $i < $stopCount; $i++) {
                            $any = false;

                            for ($j = $i + 1; $j < $stopCount; $j++) {
                                if (simulation_resolve_passenger_fare($route, $route['stops'][$i]['stop_name'], $route['stops'][$j]['stop_name']) !== null) {
                                    $any = true;
                                    $entry['resolvable_pairs']++;
                                }
                            }

                            if (!$any) {
                                $entry['unresolved_stops'][] = (string) $route['stops'][$i]['stop_name'];
                            }
                        }
                    }
                }

                $report['directions'][$direction] = $entry;
            }

            if (function_exists('simulation_latest_active_session') && function_exists('simulation_session_payload')) {
                $session = simulation_latest_active_session($conn);

                if ($session) {
                    $manifest = function_exists('simulation_manifest_load')
                        ? simulation_manifest_load($conn, $session)
                        : null;

                    $direction = (string) ($session['route_direction'] ?? '');
                    $sessionRoute = $direction !== '' && function_exists('simulation_load_fare_route')
                        ? simulation_load_fare_route($conn, $direction)
                        : null;

                    $report['latest_session'] = array(
                        'id' => isset($session['id']) ? (int) $session['id'] : 0,
                        'bus_id' => $session['bus_id'] ?? null,
                        'trip_id' => $session['trip_id'] ?? null,
                        'status' => $session['status'] ?? null,
                        'passenger_demand' => $session['passenger_demand'] ?? null,
                        'manifest_passengers' => is_array($manifest)
                            ? count((array) ($manifest['passengers'] ?? array()))
                            : null,
                        'passenger_counts' => function_exists('simulation_passenger_summary')
                            ? simulation_passenger_summary($conn, $session, is_array($sessionRoute) ? $sessionRoute['stops'] : array())
                            : null,
                        'position' => array(
                            'status' => $session['status'] ?? null,
                            'started_at' => $session['started_at'] ?? null,
                            'paused_ms' => isset($session['paused_ms']) ? (int) $session['paused_ms'] : null,
                            'elapsed_ms' => function_exists('simulation_elapsed_ms')
                                ? simulation_elapsed_ms(
                                    (string) ($session['status'] ?? ''),
                                    !empty($session['started_at']) ? strtotime((string) $session['started_at']) : null,
                                    isset($session['paused_ms']) ? (int) $session['paused_ms'] : 0,
                                    time()
                                )
                                : null,
                            'progress_pct' => $session['progress_pct'] ?? null,
                            'last_tick_at' => $session['last_tick_at'] ?? null,
                        ),
                    );

                    /* The driver/inspector apps only see riders whose trip_no
                       equals the bus's CURRENT trip. Report that bus's recent
                       trips so a stale session trip (regenerated table) is
                       visible instead of guessed. */
                    $busId = trim((string) ($session['bus_id'] ?? ''));

                    if ($busId !== '' && function_exists('simulation_table_exists') && simulation_table_exists($conn, 'bus_trips')) {
                        try {
                            $tripStmt = $conn->prepare("
                                SELECT id, status, trip_date, route_name
                                FROM bus_trips
                                WHERE bus_id = ?
                                ORDER BY id DESC
                                LIMIT 5
                            ");
                            $tripStmt->bind_param("s", $busId);
                            $tripStmt->execute();
                            $tripResult = $tripStmt->get_result();

                            $recentTrips = array();
                            $currentToday = null;
                            $today = date('Y-m-d');

                            while ($tripRow = $tripResult->fetch_assoc()) {
                                $recentTrips[] = array(
                                    'id' => (int) $tripRow['id'],
                                    'status' => (string) $tripRow['status'],
                                    'trip_date' => (string) $tripRow['trip_date'],
                                    'route_name' => (string) $tripRow['route_name'],
                                );

                                if (
                                    $currentToday === null
                                    && $tripRow['trip_date'] === $today
                                    && in_array((string) $tripRow['status'], array('Scheduled', 'Departed'), true)
                                ) {
                                    $currentToday = (int) $tripRow['id'];
                                }
                            }

                            $tripStmt->close();

                            $report['latest_session']['bus_recent_trips'] = $recentTrips;
                            $report['latest_session']['bus_current_trip_today'] = $currentToday;
                            $report['latest_session']['session_trip_matches_current'] = $currentToday !== null
                                && (string) $currentToday === (string) ($session['trip_id'] ?? '');
                        } catch (\Throwable $tripError) {
                            $report['latest_session']['bus_recent_trips'] = 'error: ' . $tripError->getMessage();
                        }
                    }

                    /* Boarding dry-run: attempt the first waiting rider's real
                       boarding write inside a transaction and roll it back, so
                       the exact MySQL error (constraint, missing column) is
                       visible instead of only in the error log. */
                    if (
                        is_array($manifest)
                        && !empty($manifest['passengers'])
                        && function_exists('simulation_board_passenger_record')
                    ) {
                        $firstWaiting = null;

                        foreach ((array) $manifest['passengers'] as $journey) {
                            if ((string) ($journey['status'] ?? '') === 'waiting') {
                                $firstWaiting = $journey;
                                break;
                            }
                        }

                        if ($firstWaiting !== null) {
                            $conn->begin_transaction();

                            try {
                                $recordId = simulation_board_passenger_record(
                                    $conn,
                                    $session,
                                    $firstWaiting,
                                    simulation_now()
                                );

                                $report['boarding_dry_run'] = array(
                                    'passenger_code' => (string) ($firstWaiting['code'] ?? ''),
                                    'record_id' => (int) $recordId,
                                    'ok' => $recordId > 0,
                                    'mysql_errno' => $conn->errno,
                                    'mysql_error' => $conn->error,
                                );
                            } catch (\Throwable $error) {
                                $report['boarding_dry_run'] = array(
                                    'passenger_code' => (string) ($firstWaiting['code'] ?? ''),
                                    'record_id' => 0,
                                    'ok' => false,
                                    'mysql_errno' => $conn->errno,
                                    'mysql_error' => $error->getMessage(),
                                );
                            }

                            $conn->rollback();
                        }
                    }
                }
            }

            echo json_encode(simulation_result(true, 'Simulation diagnostics', $report));
            break;

        default:
            http_response_code(400);
            echo json_encode(simulation_result(false, 'Unknown action: ' . $action));
    }

    $conn->close();
    exit;
}

/**
 * Resolve the session referenced by a request: explicit id, bus, driver, or
 * the latest non-disabled session.
 */
function simulation_resolve_requested_session($conn, $input)
{
    $sessionId = isset($input['session_id']) ? (int) $input['session_id'] : 0;

    if ($sessionId > 0) {
        return simulation_find_session($conn, $sessionId);
    }

    $busId = isset($input['bus_id']) ? trim((string) $input['bus_id']) : '';
    $driverId = isset($input['driver_id']) ? trim((string) $input['driver_id']) : '';

    if ($busId !== '' || $driverId !== '') {
        return simulation_find_active_session($conn, $busId !== '' ? $busId : null, $driverId !== '' ? $driverId : null);
    }

    return simulation_latest_active_session($conn);
}

/**
 * Every session the console controls: all non-disabled sessions, newest first,
 * each with its live payload.
 *
 * Unlike `options`, this does not depend on the trip still being
 * Scheduled/Departed today, so a completed session stays visible until it is
 * disabled or reset.
 */
function simulation_console_sessions($conn)
{
    if (!simulation_tables_ready($conn)) {
        return array();
    }

    $result = $conn->query("
        SELECT *
        FROM simulation_sessions
        WHERE status IN ('idle','running','paused','completed')
        ORDER BY id DESC
    ");

    if (!$result) {
        return array();
    }

    $sessions = array();

    while ($row = $result->fetch_assoc()) {
        $sessions[] = simulation_session_payload($conn, $row);
    }

    $result->free();

    return $sessions;
}

/* -------------------------------------------------------------------------
 * Console-side enable guard
 *
 * The engine's `enable` reuses a bus's existing session no matter which trip
 * that session belongs to. When the trip table is regenerated (trip ids
 * change), the old session blocks today's trip from being enabled and keeps
 * writing its simulated boardings under the old `trip_no`; the driver and
 * inspector apps query the bus's current trip, so those saved riders are
 * invisible and the simulation looks like it is "not saving passengers".
 * ------------------------------------------------------------------------- */

/**
 * Run a control action with the console's stale-session guard on `enable`:
 * a bus session whose trip differs from the requested trip is reset (clearing
 * its own SIM- boardings) and disabled before the new trip's session is
 * created, so boardings always land under the trip being simulated now.
 */
function simulation_console_control($conn, $controlAction, $input)
{
    if (strtolower(trim((string) $controlAction)) !== 'enable') {
        return simulation_control($conn, $controlAction, $input, 'simulation.php');
    }

    $busId = trim((string) ($input['bus_id'] ?? ''));
    $tripId = isset($input['trip_id']) && $input['trip_id'] !== '' ? (int) $input['trip_id'] : 0;

    if ($busId === '' || $tripId <= 0 || !function_exists('simulation_find_active_session')) {
        return simulation_control($conn, $controlAction, $input, 'simulation.php');
    }

    $existing = simulation_find_active_session($conn, $busId);
    $existingTripId = $existing && $existing['trip_id'] !== null ? (int) $existing['trip_id'] : 0;

    if (!$existing || $existingTripId === $tripId) {
        return simulation_control($conn, $controlAction, $input, 'simulation.php');
    }

    /* Reset first (a disabled session can no longer be reset) so the old
       trip's SIM- boardings are cleared, then disable it to free the bus. */
    simulation_control($conn, 'reset', array('session_id' => (int) $existing['id']), 'simulation.php');
    $disabled = simulation_control($conn, 'disable', array('session_id' => (int) $existing['id']), 'simulation.php');

    if (empty($disabled['success'])) {
        /* The engine refuses control when the stored direction has no
           coordinate plan; force the row out of the active set so the new
           session can still be created. */
        $conn->query("UPDATE simulation_sessions SET status = 'disabled', updated_at = NOW() WHERE id = " . (int) $existing['id']);
    }

    $result = simulation_control($conn, $controlAction, $input, 'simulation.php');

    if (!empty($result['success'])) {
        $result['message'] = 'Simulation enabled for trip #' . $tripId
            . ' (replaced the earlier session for trip #' . $existingTripId . ')';
    }

    return $result;
}

/* -------------------------------------------------------------------------
 * Console-side boarding fallback
 *
 * The engine (`mobile/simulation/lib/simulation_engine.php`) deploys
 * separately from this console and can lag behind it. The passenger-account
 * model on this deployment uses a numeric auto-increment `passengers.id` with
 * the `SIM-...` string kept in `passenger_code`; an older engine writes the
 * string code into `passenger_trips.passenger_id`, the insert fails, and every
 * rider stays stuck in "waiting" forever. Until the engine file is updated,
 * the tick action runs this pass: it boards the waiting riders at stops the
 * bus has already reached, using the corrected account-model write. It is
 * idempotent (riders already onboard are skipped) and short-circuits the
 * moment the engine reports the fixed revision, so it can stay in place.
 * ------------------------------------------------------------------------- */

/** Whether the deployed engine file predates the boarding fix. */
function simulation_console_engine_outdated()
{
    if (!function_exists('simulation_engine_revision')) {
        return true;
    }

    return simulation_engine_revision() !== SIMULATION_CONSOLE_ENGINE_REVISION;
}

/**
 * Write one boarding record with the account-model fix: resolve or create the
 * passenger account and store ITS PRIMARY ID (numeric on this deployment, the
 * code on legacy schemas) in `passenger_trips.passenger_id`, always keeping
 * the `SIM-...` code in `passenger_code`. Returns the boarding record id.
 */
function simulation_console_insert_sim_boarding($conn, $session, array $journey, $at)
{
    if (!function_exists('simulation_insert_row') || !function_exists('simulation_table_exists')) {
        return 0;
    }

    $code = (string) ($journey['code'] ?? '');

    if ($code === '') {
        return 0;
    }

    $passengerAccountId = null;

    if (simulation_table_exists($conn, 'passengers') && function_exists('simulation_columns_info')) {
        $accountColumns = simulation_columns_info($conn, 'passengers');
        $hasCodeColumn = isset($accountColumns['passenger_code']);
        $idColumn = isset($accountColumns['id']) ? $accountColumns['id'] : null;
        $idIsAuto = $idColumn !== null
            && strpos(strtolower((string) ($idColumn['Extra'] ?? '')), 'auto_increment') !== false;

        try {
            if ($hasCodeColumn) {
                $check = $conn->prepare("SELECT id FROM passengers WHERE passenger_code = ? LIMIT 1");
                $check->bind_param("s", $code);
                $check->execute();
                $found = $check->get_result()->fetch_assoc();
                $check->close();

                if ($found && isset($found['id'])) {
                    $passengerAccountId = $found['id'];
                }
            }

            if ($passengerAccountId === null && !$idIsAuto) {
                $check = $conn->prepare("SELECT id FROM passengers WHERE id = ? LIMIT 1");
                $check->bind_param("s", $code);
                $check->execute();
                $found = $check->get_result()->fetch_assoc();
                $check->close();

                if ($found && isset($found['id'])) {
                    $passengerAccountId = $found['id'];
                }
            }
        } catch (\Throwable $error) {
            $passengerAccountId = null;
        }

        if ($passengerAccountId === null) {
            $inserted = simulation_insert_row($conn, 'passengers', array(
                'id' => $code,
                'name' => (string) ($journey['name'] ?? $code),
                'full_name' => (string) ($journey['name'] ?? $code),
                'type' => (string) ($journey['type'] ?? 'Regular'),
                'passenger_code' => $hasCodeColumn ? $code : null,
                'registration_type' => 'one_time',
            ));

            if ($inserted > 0) {
                $passengerAccountId = $inserted;
            } elseif (!$idIsAuto) {
                $passengerAccountId = $code;
            }
        }
    }

    if (!simulation_table_exists($conn, 'passenger_trips')) {
        return 0;
    }

    $fare = round((float) ($journey['fare'] ?? 0), 2);
    $busId = (string) ($session['bus_id'] ?? '');
    $tripNo = isset($session['trip_id']) && $session['trip_id'] !== null ? (string) $session['trip_id'] : '';

    return simulation_insert_row($conn, 'passenger_trips', array(
        'passenger_id' => $passengerAccountId !== null ? $passengerAccountId : $code,
        'passenger_code' => $code,
        'passenger_type' => (string) ($journey['type'] ?? 'Regular'),
        'bus_id' => $busId,
        'bus_no' => $busId,
        'trip_no' => $tripNo,
        'driver_id' => $session['driver_id'] !== null ? (string) $session['driver_id'] : null,
        'boarding_point' => (string) ($journey['boarding'] ?? ''),
        'destination' => (string) ($journey['destination'] ?? ''),
        'selected_destination' => (string) ($journey['destination'] ?? ''),
        'fare' => $fare,
        'payment_method' => 'Cash',
        'payment_status' => 'paid',
        'boarding_time' => $at,
        'archived_status' => 'no',
        'scan_location_source' => 'simulation',
        'identification_source' => 'simulation',
        'scan_latitude' => $journey['stop_lat'] ?? null,
        'scan_longitude' => $journey['stop_lng'] ?? null,
        'scan_recorded_at' => $at,
        'scan_stop_id' => $journey['stop_id'] ?? null,
        'latitude' => $journey['stop_lat'] ?? null,
        'longitude' => $journey['stop_lng'] ?? null,
        'verified_cash_amount' => $fare,
        'expected_cash' => $fare,
        'actual_cash' => $fare,
    ));
}

/**
 * Board the waiting riders whose boarding stop has already been reached.
 * Destinations already passed are never boarded, and capacity is enforced.
 * Returns whether the manifest changed.
 */
function simulation_console_board_arrived_passengers($conn, $session)
{
    if (
        !is_array($session)
        || empty($session['id'])
        || !function_exists('simulation_manifest_load')
        || !function_exists('simulation_arrived_sequences')
        || !function_exists('simulation_manifest_save')
    ) {
        return false;
    }

    $manifest = simulation_manifest_load($conn, $session);

    if ($manifest === null || empty($manifest['passengers'])) {
        return false;
    }

    $arrived = simulation_arrived_sequences($conn, (int) $session['id']);

    if ($arrived === array()) {
        return false;
    }

    $arrivedLookup = array();

    foreach ($arrived as $sequence) {
        $arrivedLookup[(string) (int) $sequence] = true;
    }

    $capacity = (int) ($manifest['capacity'] ?? 0);

    if ($capacity <= 0 && function_exists('simulation_capacity_for_bus')) {
        $capacity = simulation_capacity_for_bus($conn, (string) ($session['bus_id'] ?? ''));
    }

    $now = date('Y-m-d H:i:s');
    $onboard = 0;

    foreach ($manifest['passengers'] as $journey) {
        if ((string) ($journey['status'] ?? '') === 'onboard') {
            $onboard++;
        }
    }

    $changed = false;

    foreach ($manifest['passengers'] as $index => $journey) {
        if ($onboard >= $capacity) {
            break;
        }

        if ((string) ($journey['status'] ?? '') !== 'waiting') {
            continue;
        }

        $boardingKey = (string) (int) ($journey['boarding_sequence'] ?? 0);

        if (!isset($arrivedLookup[$boardingKey])) {
            continue;
        }

        /* Never board a rider whose destination stop was already passed. */
        $destinationKey = (string) (int) ($journey['destination_sequence'] ?? 0);

        if ($destinationKey !== $boardingKey && isset($arrivedLookup[$destinationKey])) {
            continue;
        }

        $recordId = simulation_console_insert_sim_boarding($conn, $session, $journey, $now);

        if ($recordId <= 0) {
            continue;
        }

        $manifest['passengers'][$index]['status'] = 'onboard';
        $manifest['passengers'][$index]['boarded_at'] = $now;
        $manifest['passengers'][$index]['trip_record_id'] = $recordId;
        $onboard++;
        $changed = true;
    }

    if ($changed) {
        simulation_manifest_save($conn, (int) $session['id'], $manifest);
    }

    return $changed;
}

/* -------------------------------------------------------------------------
 * Inspector demo: pickup at the inspector's position
 * ------------------------------------------------------------------------- */

/**
 * Inspector ids for the console selector, when the accounts table exists.
 */
function simulation_inspector_options($conn)
{
    $options = array();

    if (!simulation_table_has_column($conn, 'inspector_accounts', 'inspector_id')) {
        return $options;
    }

    try {
        $result = $conn->query("
            SELECT inspector_id
            FROM inspector_accounts
            WHERE inspector_id IS NOT NULL AND inspector_id <> ''
            ORDER BY inspector_id ASC
            LIMIT 200
        ");
    } catch (\Throwable $error) {
        return $options;
    }

    if (!$result) {
        return $options;
    }

    while ($row = $result->fetch_assoc()) {
        $options[] = array('inspector_id' => $row['inspector_id']);
    }

    $result->free();

    return $options;
}

/**
 * The latest inspector assigned to a bus, from `inspector_assignments`.
 */
function simulation_inspector_for_bus($conn, $bus_id)
{
    if ($bus_id === '' || !simulation_table_has_column($conn, 'inspector_assignments', 'inspector_id')) {
        return '';
    }

    try {
        $stmt = $conn->prepare("
            SELECT inspector_id
            FROM inspector_assignments
            WHERE bus_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("s", $bus_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (\Throwable $error) {
        return '';
    }

    return $row && isset($row['inspector_id']) ? trim((string) $row['inspector_id']) : '';
}

/**
 * The selected inspector's last reported position, from `inspectors_location`.
 *
 * The inspector app upserts that table (one row per inspector on the current
 * schema, appended rows on older schemas), so the newest usable row is read
 * with the timestamp column the deployment actually declares. Returns null for
 * a missing table, an unusable coordinate, or the (0, 0) Null Island value so
 * the caller can fall back instead of arming pickup at a bogus point.
 *
 * @return array{latitude:float,longitude:float,updated_at:string|null}|null
 */
function simulation_inspector_last_location($conn, $inspector_id)
{
    $inspector_id = trim((string) $inspector_id);

    if ($inspector_id === '' || !simulation_table_exists($conn, 'inspectors_location')) {
        return null;
    }

    $columns = simulation_columns_info($conn, 'inspectors_location');

    if (!isset($columns['inspector_id'], $columns['latitude'], $columns['longitude'])) {
        return null;
    }

    $hasUpdatedAt = isset($columns['updated_at']);
    $orderBy = array();

    if ($hasUpdatedAt) {
        $orderBy[] = 'updated_at DESC';
    }

    if (isset($columns['recorded_at'])) {
        $orderBy[] = 'recorded_at DESC';
    }

    if (isset($columns['id'])) {
        $orderBy[] = 'id DESC';
    }

    $select = 'latitude, longitude' . ($hasUpdatedAt ? ', updated_at' : '');
    $order = $orderBy === array() ? '' : ' ORDER BY ' . implode(', ', $orderBy);

    try {
        $stmt = $conn->prepare("
            SELECT $select
            FROM inspectors_location
            WHERE inspector_id = ?
            $order
            LIMIT 1
        ");
        $stmt->bind_param("s", $inspector_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (\Throwable $error) {
        return null;
    }

    if (!$row) {
        return null;
    }

    $latitude = filter_var($row['latitude'], FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($row['longitude'], FILTER_VALIDATE_FLOAT);

    if (
        $latitude === false
        || $longitude === false
        || !is_finite((float) $latitude)
        || !is_finite((float) $longitude)
        || $latitude < -90
        || $latitude > 90
        || $longitude < -180
        || $longitude > 180
        || (abs($latitude) < 0.000001 && abs($longitude) < 0.000001)
    ) {
        return null;
    }

    return array(
        'latitude' => (float) $latitude,
        'longitude' => (float) $longitude,
        'updated_at' => $hasUpdatedAt && isset($row['updated_at']) ? (string) $row['updated_at'] : null,
    );
}

/**
 * Arm inspector pickup at the inspector's own position.
 *
 * The inspector's last reported coordinate is used wherever it is; the
 * Pinagpanaan (7/11) stop is only the fallback when no usable position has
 * been reported. The bus is deliberately NOT moved: it keeps running the
 * route, and the console stops it once it reaches that point so the inspector
 * can enter it from the app. Returns the standard simulation result envelope,
 * with a `pickup` block the console arms its auto-stop from.
 */
function simulation_pinagpanaan($conn, $input)
{
    $session = simulation_resolve_requested_session($conn, $input);

    if (!$session) {
        return simulation_result(false, 'No active simulation session - enable a bus first');
    }

    if ($session['status'] === 'disabled') {
        return simulation_result(false, 'Simulation session is disabled');
    }

    $inspectorId = trim((string) ($input['inspector_id'] ?? ''));

    if ($inspectorId === '') {
        $inspectorId = simulation_inspector_for_bus($conn, (string) $session['bus_id']);
    }

    if ($inspectorId === '') {
        return simulation_result(false, 'No inspector id given and none assigned to this bus');
    }

    $reported = simulation_inspector_last_location($conn, $inspectorId);

    if ($reported !== null) {
        $latitude = $reported['latitude'];
        $longitude = $reported['longitude'];
        $pickupName = 'the inspector\'s last reported position';
        $pickupSource = 'inspector_location';
    } else {
        $latitude = SIMULATION_PINAGPANAAN_LAT;
        $longitude = SIMULATION_PINAGPANAAN_LNG;
        $pickupName = SIMULATION_PINAGPANAAN_NAME . ' (no inspector position reported yet)';
        $pickupSource = 'pinagpanaan_fallback';
    }

    $written = simulation_write_inspector_location(
        $conn,
        $inspectorId,
        (string) $session['bus_id'],
        $latitude,
        $longitude
    );

    if (!$written) {
        return simulation_result(false, 'Could not write the inspector location (is inspectors_location installed?)');
    }

    $onboard = simulation_inspector_set_onboard(
        $conn,
        $inspectorId,
        (string) $session['bus_id'],
        (int) $session['id'],
        $latitude,
        $longitude
    );

    $payload = simulation_session_payload($conn, $session);
    $payload['pickup'] = array(
        'inspector_id' => $inspectorId,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'name' => $pickupName,
        'source' => $pickupSource,
        'reported_at' => $reported !== null ? $reported['updated_at'] : null,
    );

    return simulation_result(
        true,
        'Inspector ' . $inspectorId . ' pickup armed at ' . $pickupName
            . ' (' . number_format($latitude, 6, '.', '') . ', ' . number_format($longitude, 6, '.', '') . ')'
            . ' - following the simulated bus'
            . ($onboard ? '' : ' (inspector state table not installed; position not yet linked)'),
        $payload
    );
}

/* Deploy markers shown in the console header, so the loaded files can be
   verified at a glance (and remotely). */
$engineRevision = function_exists('simulation_engine_revision') ? simulation_engine_revision() : '';
$engineOutdated = simulation_console_engine_outdated();

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>R-CONNECT Simulation Console</title>
<style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; background: #0f1720; color: #e8eef5; }
    header { padding: 18px 24px; background: #16222e; border-bottom: 1px solid #24374a; }
    header h1 { margin: 0; font-size: 20px; }
    header p { margin: 6px 0 0; font-size: 12px; color: #9db2c7; }
    main { display: grid; gap: 18px; grid-template-columns: minmax(320px, 420px) 1fr; padding: 20px 24px; }
    @media (max-width: 860px) { main { grid-template-columns: 1fr; } }
    .panel { background: #16222e; border: 1px solid #24374a; border-radius: 10px; padding: 16px 18px; }
    .panel h2 { margin: 0 0 12px; font-size: 14px; text-transform: uppercase; letter-spacing: .08em; color: #9db2c7; }
    label { display: block; font-size: 12px; color: #9db2c7; margin: 0 0 6px; }
    select { width: 100%; padding: 9px 10px; border-radius: 8px; border: 1px solid #2c4258; background: #0f1720; color: #e8eef5; font-size: 13px; }
    input[type=text] { width: 100%; padding: 9px 10px; border-radius: 8px; border: 1px solid #2c4258; background: #0f1720; color: #e8eef5; font-size: 13px; }
    input[type=number] { width: 100%; padding: 9px 10px; border-radius: 8px; border: 1px solid #2c4258; background: #0f1720; color: #e8eef5; font-size: 13px; }
    .demo-box { margin-top: 16px; padding-top: 14px; border-top: 1px dashed #2c4258; }
    .demo-box h3 { margin: 0 0 8px; font-size: 13px; color: #9db2c7; text-transform: uppercase; letter-spacing: .06em; }
    .demo-box p { margin: 8px 0 10px; font-size: 12px; color: #8fa6bc; line-height: 1.45; }
    button { padding: 9px 6px; border-radius: 8px; border: 1px solid #2c4258; background: #1d2f42; color: #e8eef5; font-size: 13px; cursor: pointer; }
    button:hover:not(:disabled) { background: #26405a; }
    button:disabled { opacity: .38; cursor: default; }
    button.primary { background: #0f7b4d; border-color: #12945d; }
    button.danger { background: #7b1f2b; border-color: #9c2a38; }
    button.btn-sm { padding: 4px 8px; font-size: 11.5px; }
    #btn-enable { width: 100%; margin-top: 14px; }
    #message { margin-top: 12px; min-height: 18px; font-size: 12px; color: #ffd479; }
    .sessions-panel { min-width: 0; }
    .panel h2 .count { color: #6f8aa5; font-size: 12px; }
    .sessions-wrap { overflow-x: auto; }
    .sessions-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .sessions-table th { text-align: left; padding: 6px 8px; font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; color: #8fa6bc; border-bottom: 1px solid #24374a; white-space: nowrap; }
    .sessions-table td { padding: 8px; border-bottom: 1px solid #1d2c3c; vertical-align: top; font-variant-numeric: tabular-nums; }
    .sessions-table tr:last-child td { border-bottom: 0; }
    .cell-bus { font-weight: 600; }
    .cell-sub { margin-top: 3px; font-size: 11px; color: #8fa6bc; font-variant-numeric: normal; }
    .row-actions { display: flex; flex-wrap: wrap; gap: 4px; min-width: 190px; }
    .chip { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; background: #22405c; }
    .chip.running { background: #0f7b4d; }
    .chip.paused { background: #8a6a16; }
    .chip.completed { background: #3556a8; }
    .empty { padding: 18px 6px; font-size: 12.5px; color: #8fa6bc; }
    .footer { grid-column: 1 / -1; font-size: 11px; color: #7288a0; }
</style>
</head>
<body>
<header>
    <h1>R-CONNECT - Shared GPS Route Simulation Console</h1>
    <p>Demo tool - moves one or more simulated buses along the routes defined by <code>route_stops</code> and simulates waiting, boarding, and alighting passengers with the real fare matrix in <code>fare_pricing</code>. Boardings are written as real <code>passengers</code> accounts (ids <code>SIM-...</code>) and real <code>passenger_trips</code> rows (<code>scan_location_source = 'simulation'</code>), so the driver and inspector apps see the simulated riders while the <code>SIM-</code> prefix keeps them apart from real passenger activity. Real GPS is paused for each simulated bus while its session is active. Remove this file before production.</p>
    <p style="margin-top:10px;">Console build <code>2026-10-17b</code> &middot; Engine <code><?php echo $engineRevision !== '' ? htmlspecialchars($engineRevision) : 'older file'; ?></code><?php echo $engineOutdated ? ' &middot; <strong style="color:#ff8f8f;">engine file is out of date - boarding runs through the console fallback until <code>mobile/simulation/lib/simulation_engine.php</code> is updated</strong>' : ' &middot; boarding handled by the engine'; ?></p>
</header>
<main>
    <section class="panel">
        <h2>Controller</h2>
        <label for="trip-select">Bus &amp; active trip (today)</label>
        <select id="trip-select"></select>
        <label for="passenger-demand" style="margin-top:12px;">Passenger demand</label>
        <select id="passenger-demand">
            <option value="normal" selected>Normal (about 1-4 waiting per stop)</option>
            <option value="low">Low (about 0-2 waiting per stop)</option>
            <option value="high">High (about 3-8 waiting per stop)</option>
            <option value="off">Off (no simulated passengers)</option>
        </select>
        <label for="speed-preset" style="margin-top:12px;">Simulation speed</label>
        <select id="speed-preset">
            <option value="normal" selected>Normal (route speeds as configured)</option>
            <option value="slow">Slow (x0.5)</option>
            <option value="fast">Fast (x1.5)</option>
            <option value="custom">Custom multiplier</option>
        </select>
        <input id="speed-multiplier" type="number" min="0.25" max="3" step="0.25" value="1" style="display:none;margin-top:8px;" aria-label="Custom speed multiplier">
        <button id="btn-enable" class="primary">Enable selected bus</button>
        <div class="demo-box">
            <h3>Inspector demo - pickup</h3>
            <label for="inspector-id">Inspector id</label>
            <input id="inspector-id" type="text" list="inspector-options" placeholder="Inspector id (blank = the inspector assigned to the bus)">
            <datalist id="inspector-options"></datalist>
            <p>Press <strong>Pickup</strong> on a session row to arm pickup at that inspector's last reported position, wherever it is (Pinagpanaan 7/11 is used only when no position has been reported). That bus keeps driving its route and stops when it reaches the inspector, so the inspector can enter it and run the checklist in the app.</p>
        </div>
        <div id="message"></div>
    </section>

    <section class="panel sessions-panel">
        <h2>Sessions <span class="count" id="session-count"></span></h2>
        <div class="sessions-wrap" id="sessions-container">
            <div class="empty">Loading sessions...</div>
        </div>
    </section>
    <div class="footer">Movement is computed server-side from elapsed running time, so every client polling a session sees the same coordinates. Every running session is advanced once per 2-second poll; closing this page freezes running buses until it is reopened. Waiting passengers come from the route's <code>route_stops</code> order; boardings, alightings, and fares come from the <code>fare_pricing</code> matrix (capacity enforced first-alight-then-board), and each boarding writes a real <code>passenger_trips</code> row for the driver and inspector apps. Restarting a session clears only its own <code>SIM-</code> boardings. The admin Trip Dashboard shows each bus through the same override.</div>
</main>

<script>
(function () {
    var PINAGPANAAN = { lat: 15.548367, lng: 120.933501 };
    var engineOutdated = <?php echo $engineOutdated ? 'true' : 'false'; ?>;
    var state = { sessions: {}, awaitingPickup: {} };
    var els = {
        trip: document.getElementById('trip-select'),
        demand: document.getElementById('passenger-demand'),
        speed: document.getElementById('speed-preset'),
        speedMultiplier: document.getElementById('speed-multiplier'),
        message: document.getElementById('message'),
        sessions: document.getElementById('sessions-container'),
        sessionCount: document.getElementById('session-count'),
        inspectorInput: document.getElementById('inspector-id'),
        inspectorOptions: document.getElementById('inspector-options')
    };
    var buttons = {
        enable: document.getElementById('btn-enable')
    };

    function request(action, options) {
        return fetch('?action=' + action, options || {}).then(function (response) {
            return response.json();
        });
    }

    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function number(value, digits) {
        if (value === null || value === undefined || value === '') {
            return '-';
        }

        return Number(value).toFixed(digits);
    }

    /* The speed tier selected in the controller: preset name plus the custom
       multiplier (only used on the custom tier; the engine clamps it). */
    function currentSpeed() {
        var preset = els.speed ? els.speed.value : 'normal';
        var multiplier = els.speedMultiplier ? Number(els.speedMultiplier.value) : 1;

        if (!(multiplier > 0)) {
            multiplier = 1;
        }

        return { speed_preset: preset, speed_multiplier: multiplier };
    }

    function updateSpeedControls() {
        var custom = !!(els.speed && els.speed.value === 'custom');

        if (els.speedMultiplier) {
            els.speedMultiplier.style.display = custom ? 'block' : 'none';
        }
    }

    /* Apply the selected speed tier to every listed session, so changing the
       control mid-demo speeds up (or slows down) all running buses. */
    function applySpeedToSessions() {
        if (engineOutdated) {
            showMessage('Speed control needs the updated engine file (mobile/simulation/lib/simulation_engine.php).', true);
            return;
        }

        var speed = currentSpeed();

        Object.keys(state.sessions).forEach(function (id) {
            var session = state.sessions[id];

            if (!session || session.status === 'disabled') {
                return;
            }

            postControl('speed', Number(id), speed);
        });
    }

    function renderRow(data) {
        var id = Number(data.session_id);
        var status = String(data.status || '');
        var startable = status === 'idle' || status === 'completed';
        var running = status === 'running';
        var paused = status === 'paused';
        var passengers = data.passengers || {};
        var capacity = Number(passengers.capacity || 0);
        var waitingStops = (passengers.by_stop || []).filter(function (stop) {
            return Number(stop.waiting) > 0;
        }).slice(0, 3).map(function (stop) {
            return esc(stop.stop_name) + ' (' + Number(stop.waiting) + ')';
        }).join(', ');
        var passengerCell = '<div>' + Number(passengers.onboard || 0) + ' / ' + capacity + '</div>' +
            '<div class="cell-sub">waiting ' + Number(passengers.waiting || 0) +
                ' \u00b7 boarded ' + Number(passengers.boarded || 0) +
                ' \u00b7 \u20b1' + Number(passengers.fare_total || 0).toFixed(2) + '</div>' +
            '<div class="cell-sub">demand: ' + esc(passengers.demand || 'normal') +
                (waitingStops ? ' \u00b7 at ' + waitingStops : '') + '</div>';

        var speedPreset = String(data.speed_preset || 'normal');
        var speedLabel = number(data.speed_kmh, 1) + ' km/h'
            + (speedPreset !== 'normal' ? ' \u00b7 ' + esc(speedPreset) : '');

        return '<tr data-session="' + id + '">' +
            '<td><div class="cell-bus">' + esc(data.bus_id) + '</div><div class="cell-sub">trip #' + esc(data.trip_id) + '</div></td>' +
            '<td><div>' + esc(data.route_name) + '</div><div class="cell-sub">' + esc(data.route_direction) + '</div></td>' +
            '<td><span class="chip ' + esc(status) + '">' + esc(status) + '</span></td>' +
            '<td>' + number(data.progress_pct, 1) + '%<div class="cell-sub">' + speedLabel + '</div></td>' +
            '<td>' + number(data.latitude, 6) + '<div class="cell-sub">' + number(data.longitude, 6) + '</div></td>' +
            '<td>' + esc(data.current_stop || '-') + '<div class="cell-sub">next: ' + esc(data.next_stop || '-') + '</div></td>' +
            '<td>' + number(data.arrived_stops, 0) + ' / ' + number(data.total_stops, 0) + '</td>' +
            '<td>' + passengerCell + '</td>' +
            '<td>' + esc(data.last_tick_at || '-') + '</td>' +
            '<td><div class="row-actions">' +
                '<button type="button" class="btn-sm" data-action="start" data-session="' + id + '"' + (startable ? '' : ' disabled') + '>Start</button>' +
                '<button type="button" class="btn-sm" data-action="pause" data-session="' + id + '"' + (running ? '' : ' disabled') + '>Pause</button>' +
                '<button type="button" class="btn-sm" data-action="resume" data-session="' + id + '"' + (paused ? '' : ' disabled') + '>Resume</button>' +
                '<button type="button" class="btn-sm" data-action="reset" data-session="' + id + '">Reset</button>' +
                '<button type="button" class="btn-sm" data-action="pinagpanaan" data-session="' + id + '">Pickup</button>' +
                '<button type="button" class="btn-sm danger" data-action="disable" data-session="' + id + '">Disable</button>' +
            '</div></td>' +
        '</tr>';
    }

    function render() {
        var ids = Object.keys(state.sessions).sort(function (a, b) { return Number(b) - Number(a); });
        els.sessionCount.textContent = ids.length ? '(' + ids.length + ')' : '';

        if (!ids.length) {
            els.sessions.innerHTML = '<div class="empty">No simulated buses yet. Select a trip and press Enable.</div>';
            return;
        }

        els.sessions.innerHTML = '<table class="sessions-table">' +
            '<thead><tr><th>Bus</th><th>Route</th><th>Status</th><th>Progress</th><th>Position</th><th>Current / next stop</th><th>Reached</th><th>Passengers</th><th>Last tick</th><th>Actions</th></tr></thead>' +
            '<tbody>' + ids.map(function (id) { return renderRow(state.sessions[id]); }).join('') + '</tbody>' +
        '</table>';
    }

    /*
     * Once pickup is armed for a session, stop only that bus when it reaches
     * the armed point - the inspector's last reported position, or the
     * Pinagpanaan fallback the server chose. The bus is paused exactly where
     * it is; it is never moved.
     */
    function maybeStopForInspectors() {
        Object.keys(state.awaitingPickup).forEach(function (key) {
            var data = state.sessions[key];
            var pickup = state.awaitingPickup[key];

            if (!data || data.active !== true) {
                return;
            }

            if (typeof data.latitude !== 'number' || typeof data.longitude !== 'number'
                || !isFinite(data.latitude) || !isFinite(data.longitude)) {
                return;
            }

            if (!pickup || typeof pickup.latitude !== 'number' || typeof pickup.longitude !== 'number'
                || !isFinite(pickup.latitude) || !isFinite(pickup.longitude)) {
                return;
            }

            if (distanceKm(data.latitude, data.longitude, pickup.latitude, pickup.longitude) > 0.15) {
                return;
            }

            delete state.awaitingPickup[key];

            var where = pickup.name || 'the inspector';

            if (data.status === 'running') {
                showMessage('Bus ' + data.bus_id + ' reached ' + where + ' - stopping for the inspector to enter.', false);
                postControl('pause', Number(key));
            } else {
                showMessage('Bus ' + data.bus_id + ' is at ' + where + ' - the inspector can now enter the bus.', false);
            }
        });
    }

    function applyControlResult(control, json) {
        if (!json) {
            return;
        }

        if (json.message) {
            showMessage(json.message, json.success === false);
        }

        var data = json.data || null;

        if (json.success && data && data.session_id) {
            if (control === 'disable') {
                delete state.sessions[data.session_id];
                delete state.awaitingPickup[data.session_id];
            } else {
                state.sessions[data.session_id] = data;
            }

            render();
        }
    }

    function postControl(control, sessionId, extra) {
        var body = { control: control };

        if (sessionId) {
            body.session_id = sessionId;
        }

        if (extra) {
            Object.keys(extra).forEach(function (key) { body[key] = extra[key]; });
        }

        return request('control', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (json) {
            applyControlResult(control, json);

            if (control === 'enable' || control === 'disable' || control === 'reset') {
                loadOptions();
            }

            return json;
        });
    }

    function showMessage(text, isError) {
        els.message.textContent = text || '';
        els.message.style.color = isError ? '#ff8f8f' : '#ffd479';
    }

    function updateEnableState() {
        var option = els.trip.options[els.trip.selectedIndex];
        var available = !!(option && option.value);
        var alreadySimulated = available && option.getAttribute('data-simulated') === '1';

        buttons.enable.disabled = !available || alreadySimulated;
    }

    function distanceKm(lat1, lng1, lat2, lng2) {
        var earth = 6371;
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
            * Math.sin(dLng / 2) * Math.sin(dLng / 2);

        return earth * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function loadOptions() {
        return request('options').then(function (json) {
            var trips = (json && json.data) ? json.data : [];
            var previous = els.trip.value;
            els.trip.innerHTML = '';

            if (!trips.length) {
                var empty = document.createElement('option');
                empty.value = '';
                empty.textContent = 'No scheduled/departed trips today';
                els.trip.appendChild(empty);
                updateEnableState();
                return;
            }

            trips.forEach(function (trip) {
                var option = document.createElement('option');
                /* The bus's session only counts as "this trip simulated" when
                   its trip_id matches; a session for an older trip would
                   otherwise block Enable and keep boardings off today's trip. */
                var simulatedTrip = !!(trip.simulation && trip.simulation.active
                    && Number(trip.simulation.trip_id) === Number(trip.trip_id));
                var otherSession = !!(trip.simulation && trip.simulation.active && !simulatedTrip);
                option.value = String(trip.trip_id);
                option.setAttribute('data-bus', trip.bus_id);
                option.setAttribute('data-simulated', simulatedTrip ? '1' : '0');
                option.textContent = trip.bus_id + ' \u00b7 ' + (trip.route_name || 'route?') + ' \u00b7 #' + trip.trip_id + ' \u00b7 ' + trip.status + (trip.driver_name ? ' \u00b7 ' + trip.driver_name : '') + (simulatedTrip ? ' \u00b7 simulated' : (otherSession ? ' \u00b7 replaces earlier session' : ''));
                els.trip.appendChild(option);
            });

            if (previous) {
                els.trip.value = previous;
            }

            updateEnableState();
        });
    }

    function loadInspectors() {
        return request('inspectors').then(function (json) {
            var inspectors = (json && json.data) ? json.data : [];
            els.inspectorOptions.innerHTML = '';

            inspectors.forEach(function (inspector) {
                if (!inspector || !inspector.inspector_id) {
                    return;
                }

                var option = document.createElement('option');
                option.value = inspector.inspector_id;
                els.inspectorOptions.appendChild(option);
            });
        });
    }

    function poll() {
        return request('sessions').then(function (json) {
            var list = (json && json.data) ? json.data : [];
            var listed = {};
            var ticks = [];

            list.forEach(function (data) {
                listed[data.session_id] = true;

                if (data.status === 'running') {
                    ticks.push(request('tick&session_id=' + encodeURIComponent(data.session_id)).then(function (tickJson) {
                        if (tickJson && tickJson.success && tickJson.data && tickJson.data.session_id) {
                            state.sessions[tickJson.data.session_id] = tickJson.data;
                        }
                    }).catch(function () {}));
                } else {
                    state.sessions[data.session_id] = data;
                }
            });

            Object.keys(state.sessions).forEach(function (id) {
                if (!listed[id]) {
                    delete state.sessions[id];
                    delete state.awaitingPickup[id];
                }
            });

            return Promise.all(ticks);
        }).then(function () {
            render();
            maybeStopForInspectors();
        }).catch(function () {
            /* transient failure - the next poll retries */
        });
    }

    els.sessions.addEventListener('click', function (event) {
        var button = event.target.closest('button[data-action]');

        if (!button) {
            return;
        }

        var action = button.getAttribute('data-action');
        var sessionId = Number(button.getAttribute('data-session'));

        if (!sessionId) {
            return;
        }

        if (action === 'pinagpanaan') {
            var inspectorId = els.inspectorInput ? els.inspectorInput.value.trim() : '';

            postControl('pinagpanaan', sessionId, { inspector_id: inspectorId }).then(function (json) {
                if (!json || !json.success) {
                    return;
                }

                var pickup = json.data && json.data.pickup ? json.data.pickup : null;

                state.awaitingPickup[sessionId] = pickup ? {
                    latitude: Number(pickup.latitude),
                    longitude: Number(pickup.longitude),
                    name: String(pickup.name || 'the inspector')
                } : {
                    latitude: PINAGPANAAN.lat,
                    longitude: PINAGPANAAN.lng,
                    name: 'Pinagpanaan (7/11)'
                };
            });

            return;
        }

        postControl(action, sessionId);
    });

    buttons.enable.addEventListener('click', function () {
        var option = els.trip.options[els.trip.selectedIndex];

        if (!option || !option.value) {
            showMessage('Select a bus and active trip first.', true);
            return;
        }

        var speed = currentSpeed();

        postControl('enable', null, {
            trip_id: Number(option.value),
            bus_id: option.getAttribute('data-bus'),
            passenger_demand: els.demand ? els.demand.value : 'normal',
            speed_preset: speed.speed_preset,
            speed_multiplier: speed.speed_multiplier
        }).then(function (json) {
            if (json && json.success) {
                poll();
            }
        });
    });

    els.trip.addEventListener('change', updateEnableState);

    if (els.speed) {
        els.speed.addEventListener('change', function () {
            updateSpeedControls();
            applySpeedToSessions();
        });
    }

    if (els.speedMultiplier) {
        els.speedMultiplier.addEventListener('change', applySpeedToSessions);
    }

    updateSpeedControls();

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            loadOptions();
        }
    });

    loadOptions().then(loadInspectors).then(poll).catch(function () {
        showMessage('Could not load options - check the database connection.', true);
    });

    setInterval(poll, 2000);
})();
</script>
</body>
</html>

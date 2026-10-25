<?php

/**
 * R-CONNECT shared GPS route simulation - engine.
 *
 * Pure movement math plus the mysqli persistence helpers shared by the
 * standalone controller (`simulation.php`, website root), the mobile read
 * endpoint (`mobile/simulation/state.php`), and the read-path overrides in the
 * existing endpoints.
 *
 * Rules this file keeps:
 *   - Movement comes from the stored `route_stops` sequence for the resolved
 *     direction: stops ordered by `sequence_order`, which is the same source
 *     the driver app draws and the arrival detector uses. The hard-coded
 *     `simulation_routes.php` waypoints are only a fallback when a direction
 *     has fewer than two stored stops. `route_points` is not used.
 *   - Passenger simulation reads the real `route_stops` order (waiting and
 *     boarding points) and the real `fare_pricing` matrix (valid
 *     boarding-point/destination pairs and fares); it never invents a fare.
 *     Boarding writes REAL `passengers` accounts (ids `SIM-...`) and REAL
 *     `passenger_trips` rows (`scan_location_source = 'simulation'`), so the
 *     driver and inspector apps see the simulated riders; the `SIM-` prefix
 *     and scan source keep them apart from real passenger activity. The
 *     waiting/onboard/alighted state itself lives in the session's
 *     `passenger_manifest` column, not in a simulation passenger table.
 *   - Simulated positions live only in `simulation_sessions` /
 *     `simulation_stop_events`. Real GPS tables are never touched.
 *   - Position is a function of elapsed running time, so every client polling
 *     the same session sees the same coordinates.
 *
 * Include guard: the file may be required from several endpoints in one
 * request chain; the first include wins.
 */

if (function_exists('simulation_config')) {
    return;
}

/* Constant definitions are deliberately avoided here: PHP binds top-level
   functions at include time, so this guard returns even on the first include
   and any statement after it would never run. */

/**
 * The deployed engine revision, surfaced in every session payload so the live
 * deployment can be verified from the console (`?action=sessions`) without
 * server access. Bump it whenever this file changes.
 */
function simulation_engine_revision()
{
    return '2026-10-17-speed-presets';
}

/**
 * Selectable simulation speed tiers: preset name => multiplier applied to the
 * route's waypoint speeds. Unknown presets fall back to normal.
 */
function simulation_speed_presets()
{
    $config = simulation_config();
    $presets = isset($config['speed_presets']) && is_array($config['speed_presets'])
        ? $config['speed_presets']
        : array();

    $resolved = array('slow' => 0.5, 'normal' => 1.0, 'fast' => 1.5);

    foreach ($presets as $name => $multiplier) {
        $name = strtolower(trim((string) $name));
        $multiplier = (float) $multiplier;

        if ($name !== '' && $multiplier > 0) {
            $resolved[$name] = $multiplier;
        }
    }

    $resolved['normal'] = isset($resolved['normal']) ? (float) $resolved['normal'] : 1.0;

    if ($resolved['normal'] <= 0) {
        $resolved['normal'] = 1.0;
    }

    return $resolved;
}

/** Normalize any speed preset request to a known tier. */
function simulation_normalize_speed_preset($value)
{
    $preset = strtolower(trim((string) $value));
    $known = array_keys(simulation_speed_presets());
    $known[] = 'custom';

    return in_array($preset, $known, true) ? $preset : 'normal';
}

/** Clamp a custom speed multiplier to the configured operating bounds. */
function simulation_normalize_speed_multiplier($value)
{
    $config = simulation_config();
    $min = isset($config['speed_multiplier_min']) ? (float) $config['speed_multiplier_min'] : 0.25;
    $max = isset($config['speed_multiplier_max']) ? (float) $config['speed_multiplier_max'] : 3.0;

    if ($min <= 0) {
        $min = 0.25;
    }

    if ($max < $min) {
        $max = $min;
    }

    $multiplier = (float) $value;

    if (!is_finite($multiplier) || $multiplier <= 0) {
        $multiplier = 1.0;
    }

    return max($min, min($max, $multiplier));
}

/**
 * The movement speed multiplier for a session row: its speed tier's preset
 * multiplier, or the session's own value on the custom tier. Sessions from
 * before the speed columns existed behave as normal.
 */
function simulation_session_speed_multiplier($session)
{
    $preset = isset($session['speed_preset'])
        ? simulation_normalize_speed_preset($session['speed_preset'])
        : 'normal';

    if ($preset === 'custom') {
        return simulation_normalize_speed_multiplier($session['speed_multiplier'] ?? 1.0);
    }

    $presets = simulation_speed_presets();

    return isset($presets[$preset]) ? (float) $presets[$preset] : 1.0;
}

/* -------------------------------------------------------------------------
 * Configuration and route identity
 * ------------------------------------------------------------------------- */

function simulation_config()
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/simulation_routes.php';
    }

    return $config;
}

/**
 * Canonicalize a route name the same way fare-engine/utils/canonicalRouteName.php
 * does, so "Cabanatuan to Rizal Route", "Cabanatuan-Rizal", and "CABANATUAN"
 * compare equal.
 */
function simulation_canonical_route_name($name)
{
    $value = strtolower(trim((string) $name));
    $value = preg_replace('/\s+to\s+/', '-', $value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim($value, '-');
    $value = preg_replace('/-route$/', '', $value);

    return $value;
}

/**
 * Resolve the simulation direction key for a trip route name.
 *
 * Returns null when the name belongs to no known corridor; a null direction is
 * a refusal to start, never a fallback onto another route's coordinates.
 */
function simulation_resolve_direction($route_name)
{
    $canonical = simulation_canonical_route_name($route_name);

    if ($canonical === '') {
        return null;
    }

    if (strpos($canonical, 'rizal-cabanatuan') !== false) {
        return 'rizal-cabanatuan';
    }

    if (strpos($canonical, 'cabanatuan-rizal') !== false) {
        return 'cabanatuan-rizal';
    }

    // Terminal-only names follow the established convention used by
    // mobile/driver/lib/actual_destination.php.
    if ($canonical === 'cabanatuan') {
        return 'cabanatuan-rizal';
    }

    if ($canonical === 'rizal') {
        return 'rizal-cabanatuan';
    }

    return null;
}

function simulation_direction_config($direction)
{
    $config = simulation_config();

    return isset($config['directions'][$direction]) ? $config['directions'][$direction] : null;
}

/**
 * Normalize configured waypoints to [['lat' => float, 'lng' => float, 'speed_kmh' => float]].
 */
function simulation_normalize_waypoints($direction)
{
    $directionConfig = simulation_direction_config($direction);

    if ($directionConfig === null) {
        return array();
    }

    $config = simulation_config();
    $defaultSpeed = isset($config['default_speed_kmh']) ? (float) $config['default_speed_kmh'] : 40.0;
    $waypoints = array();

    foreach ($directionConfig['waypoints'] as $waypoint) {
        $lat = isset($waypoint[0]) ? (float) $waypoint[0] : null;
        $lng = isset($waypoint[1]) ? (float) $waypoint[1] : null;

        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            continue;
        }

        $speed = isset($waypoint[2]) ? (float) $waypoint[2] : $defaultSpeed;

        $waypoints[] = array(
            'lat' => $lat,
            'lng' => $lng,
            'speed_kmh' => $speed > 0 ? $speed : $defaultSpeed,
        );
    }

    return $waypoints;
}

/* -------------------------------------------------------------------------
 * Pure movement math
 * ------------------------------------------------------------------------- */

function simulation_haversine_m($lat1, $lng1, $lat2, $lng2)
{
    $earth = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);

    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Build the segment plan for a waypoint list.
 *
 * Returns ['segments' => [...], 'cumulative_m' => [...], 'total_distance_m',
 * 'total_duration_ms']. Segment i runs from waypoint i to i+1 and its speed is
 * the speed of the destination waypoint, scaled by $speedMultiplier (the
 * session's speed tier) so a higher tier covers the route in less time.
 */
function simulation_build_segments($waypoints, $defaultSpeedKmh = 40.0, $speedMultiplier = 1.0)
{
    $segments = array();
    $cumulative = array(0.0);
    $totalDistance = 0.0;
    $totalDuration = 0.0;
    $count = count($waypoints);
    $multiplier = (float) $speedMultiplier;

    if (!is_finite($multiplier) || $multiplier <= 0) {
        $multiplier = 1.0;
    }

    for ($i = 0; $i + 1 < $count; $i++) {
        $from = $waypoints[$i];
        $to = $waypoints[$i + 1];
        $distance = simulation_haversine_m($from['lat'], $from['lng'], $to['lat'], $to['lng']);
        $speed = isset($to['speed_kmh']) && (float) $to['speed_kmh'] > 0
            ? (float) $to['speed_kmh']
            : (float) $defaultSpeedKmh;
        $speed = max(1.0, $speed * $multiplier);
        $durationMs = $speed > 0 ? ($distance / 1000) / $speed * 3600000.0 : 0.0;

        $segments[] = array(
            'from_index' => $i,
            'to_index' => $i + 1,
            'distance_m' => $distance,
            'duration_ms' => $durationMs,
            'speed_kmh' => $speed,
        );

        $totalDistance += $distance;
        $totalDuration += $durationMs;
        $cumulative[] = $totalDistance;
    }

    return array(
        'segments' => $segments,
        'cumulative_m' => $cumulative,
        'total_distance_m' => $totalDistance,
        'total_duration_ms' => $totalDuration,
    );
}

/**
 * The position at an elapsed running time, in milliseconds.
 *
 * Cancels backwards movement by clamping, and reports completion once the
 * elapsed time reaches the end of the plan.
 */
function simulation_position_at($plan, $waypoints, $elapsedMs)
{
    $count = count($waypoints);

    if ($count === 0) {
        return array(
            'lat' => null,
            'lng' => null,
            'distance_m' => 0.0,
            'progress_pct' => 0.0,
            'point_index' => 0,
            'completed' => false,
        );
    }

    if ($count === 1 || $elapsedMs <= 0 || empty($plan['segments'])) {
        $first = $waypoints[0];

        return array(
            'lat' => $first['lat'],
            'lng' => $first['lng'],
            'distance_m' => 0.0,
            'progress_pct' => 0.0,
            'point_index' => 0,
            'completed' => false,
        );
    }

    if ($elapsedMs >= $plan['total_duration_ms']) {
        $last = $waypoints[$count - 1];
        $progress = $plan['total_distance_m'] > 0 ? 100.0 : 0.0;

        return array(
            'lat' => $last['lat'],
            'lng' => $last['lng'],
            'distance_m' => $plan['total_distance_m'],
            'progress_pct' => $progress,
            'point_index' => $count - 1,
            'completed' => true,
        );
    }

    $elapsedInSegments = 0.0;

    foreach ($plan['segments'] as $segment) {
        $segmentEnd = $elapsedInSegments + $segment['duration_ms'];

        if ($elapsedMs <= $segmentEnd) {
            $ratio = $segment['duration_ms'] > 0
                ? ($elapsedMs - $elapsedInSegments) / $segment['duration_ms']
                : 0.0;
            $ratio = max(0.0, min(1.0, $ratio));

            $from = $waypoints[$segment['from_index']];
            $to = $waypoints[$segment['to_index']];
            $lat = $from['lat'] + ($to['lat'] - $from['lat']) * $ratio;
            $lng = $from['lng'] + ($to['lng'] - $from['lng']) * $ratio;
            $distance = $plan['cumulative_m'][$segment['from_index']]
                + ($plan['cumulative_m'][$segment['to_index']] - $plan['cumulative_m'][$segment['from_index']]) * $ratio;

            return array(
                'lat' => $lat,
                'lng' => $lng,
                'distance_m' => $distance,
                'progress_pct' => $plan['total_distance_m'] > 0 ? ($distance / $plan['total_distance_m']) * 100.0 : 0.0,
                'point_index' => $segment['from_index'],
                'completed' => false,
            );
        }

        $elapsedInSegments = $segmentEnd;
    }

    $last = $waypoints[$count - 1];

    return array(
        'lat' => $last['lat'],
        'lng' => $last['lng'],
        'distance_m' => $plan['total_distance_m'],
        'progress_pct' => 100.0,
        'point_index' => $count - 1,
        'completed' => true,
    );
}

/**
 * Elapsed running milliseconds for a session clock.
 *
 * Running time only accrues in the running state; pause freezes it, resume
 * starts a new running period from the accumulated total.
 */
function simulation_elapsed_ms($status, $startedAtTs, $pausedMs, $nowTs)
{
    $pausedMs = max(0, (int) $pausedMs);

    if ($status === 'running' && $startedAtTs !== null) {
        $current = max(0, ($nowTs - (int) $startedAtTs) * 1000);

        return $pausedMs + $current;
    }

    return $pausedMs;
}

/**
 * The designated stop whose arrival radius currently contains the position.
 */
function simulation_current_stop($stops, $lat, $lng)
{
    if ($lat === null || $lng === null) {
        return null;
    }

    $current = null;
    $nearest = PHP_FLOAT_MAX;

    foreach ($stops as $stop) {
        $distance = simulation_haversine_m($lat, $lng, (float) $stop['latitude'], (float) $stop['longitude']);

        if ($distance <= (float) $stop['radius_m'] && $distance < $nearest) {
            $nearest = $distance;
            $current = $stop;
        }
    }

    return $current;
}

/**
 * The first stop, in sequence order, that has no arrival event yet.
 */
function simulation_next_stop($stops, $arrivedSequences)
{
    $arrived = array();
    foreach ($arrivedSequences as $sequence) {
        $arrived[(string) $sequence] = true;
    }

    foreach ($stops as $stop) {
        if (!isset($arrived[(string) $stop['sequence_order']])) {
            return $stop;
        }
    }

    return null;
}

/* -------------------------------------------------------------------------
 * Database helpers (mysqli)
 * ------------------------------------------------------------------------- */

function simulation_now()
{
    return date('Y-m-d H:i:s');
}

function simulation_now_ts()
{
    return time();
}

function simulation_tables_ready($conn)
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    try {
        $result = $conn->query("SELECT 1 FROM simulation_sessions LIMIT 1");
        $ready = $result !== false;

        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } catch (\Throwable $error) {
        $ready = false;
    }

    return $ready;
}

/**
 * Whether a table/column exists in the connected schema.
 *
 * Used to stay quiet on databases that hold an older partial schema (for
 * example a local dev database whose `route_stops` lacks the later `active`
 * column): the simulator falls back rather than throwing.
 */
function simulation_table_has_column($conn, $table, $column)
{
    static $cache = array();

    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $conn->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $table, $column);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } catch (\Throwable $error) {
        $found = false;
    }

    $cache[$key] = $found;

    return $found;
}

/**
 * Load the direction's movement path from the stored `route_stops` sequence.
 *
 * Stops are ordered by `sequence_order` (then id) - the same order the driver
 * app draws and the arrival detector uses - so the simulated bus follows the
 * real stop-to-stop road path. Consecutive points closer than 15 m collapse so
 * the plan has no zero-length segments (landmark and fare rows can describe
 * the same physical stop). `route_stops` stores no speed, so the shared config
 * default applies.
 */
function simulation_load_route_stops_waypoints($conn, $direction)
{
    $directionConfig = simulation_direction_config($direction);
    $config = simulation_config();
    $defaultSpeed = isset($config['default_speed_kmh']) ? (float) $config['default_speed_kmh'] : 40.0;

    if ($directionConfig === null || empty($directionConfig['route_stops_name'])) {
        return array();
    }

    if (!simulation_table_has_column($conn, 'route_stops', 'route_name')) {
        return array();
    }

    $routeStopsName = (string) $directionConfig['route_stops_name'];
    $activeFilter = simulation_table_has_column($conn, 'route_stops', 'active') ? ' AND active = 1' : '';

    try {
        $stmt = $conn->prepare("
            SELECT id, sequence_order, latitude, longitude
            FROM route_stops
            WHERE route_name = ?" . $activeFilter . "
            ORDER BY sequence_order ASC, id ASC
        ");
        $stmt->bind_param("s", $routeStopsName);
        $stmt->execute();
        $result = $stmt->get_result();
    } catch (\Throwable $error) {
        return array();
    }

    $waypoints = array();
    $duplicateWithinM = 15.0; // consecutive points closer than this collapse

    while ($row = $result->fetch_assoc()) {
        $lat = (float) $row['latitude'];
        $lng = (float) $row['longitude'];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            continue;
        }

        $last = end($waypoints);

        if ($last !== false && simulation_haversine_m($last['lat'], $last['lng'], $lat, $lng) < $duplicateWithinM) {
            continue;
        }

        $waypoints[] = array(
            'lat' => $lat,
            'lng' => $lng,
            'speed_kmh' => $defaultSpeed,
        );
    }

    $stmt->close();

    return $waypoints;
}

/**
 * The movement source for a direction: the stored `route_stops` sequence when
 * it has at least two points, otherwise the configured fallback mirror.
 */
function simulation_direction_waypoints($conn, $direction)
{
    $stored = simulation_load_route_stops_waypoints($conn, $direction);

    if (count($stored) >= 2) {
        return $stored;
    }

    return simulation_normalize_waypoints($direction);
}

/**
 * The route's designated stops with arrival radii.
 *
 * Coordinates come from `route_stops` (the landmark table); radii come from
 * the inspector's stop map when that library is present, else the config
 * default.
 */
function simulation_load_stops($conn, $direction)
{
    $directionConfig = simulation_direction_config($direction);
    $config = simulation_config();
    $defaultRadius = isset($config['default_stop_radius_m']) ? (float) $config['default_stop_radius_m'] : 150.0;

    if ($directionConfig === null || empty($directionConfig['route_stops_name'])) {
        return array();
    }

    /* Flat route layout: route_stops rows with their own coordinates. */
    if (simulation_table_has_column($conn, 'route_stops', 'route_name')) {
        $routeStopsName = $directionConfig['route_stops_name'];
        $radii = simulation_stop_radii_for_route($routeStopsName);

        $stmt = $conn->prepare("
            SELECT sequence_order, stop_name, latitude, longitude
            FROM route_stops
            WHERE route_name = ?
            ORDER BY sequence_order ASC
        ");
        $stmt->bind_param("s", $routeStopsName);
        $stmt->execute();
        $result = $stmt->get_result();
        $stops = array();

        while ($row = $result->fetch_assoc()) {
            $sequence = (int) $row['sequence_order'];
            $stops[] = array(
                'sequence_order' => $sequence,
                'stop_name' => $row['stop_name'],
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
                'radius_m' => isset($radii[$sequence]) ? (float) $radii[$sequence] : $defaultRadius,
            );
        }

        $stmt->close();

        if ($stops !== array()) {
            return $stops;
        }
    }

    /* Fare-matrix layout: the ordered stops resolved from fare_pricing and its
       route_stops rows, with coordinates projected onto the corridor. */
    $route = simulation_load_fare_route($conn, $direction);

    if ($route === null) {
        return array();
    }

    $stops = array();

    foreach ($route['stops'] as $stop) {
        if ($stop['latitude'] === null || $stop['longitude'] === null) {
            continue;
        }

        $stops[] = array(
            'sequence_order' => (int) $stop['sequence_order'],
            'stop_name' => (string) $stop['stop_name'],
            'latitude' => (float) $stop['latitude'],
            'longitude' => (float) $stop['longitude'],
            'radius_m' => $defaultRadius,
        );
    }

    return $stops;
}

/**
 * sequence_order => radius_m from the inspector stop map, when available.
 */
function simulation_stop_radii_for_route($routeStopsName)
{
    $candidates = array(
        __DIR__ . '/../../inspector/lib/stop_detection.php',
    );

    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') . '/mobile/inspector/lib/stop_detection.php';
    }

    $radii = array();

    foreach ($candidates as $mapFn) {
        if (!is_file($mapFn)) {
            continue;
        }

        require_once $mapFn;

        if (function_exists('stop_detection_map')) {
            $map = stop_detection_map();

            if (isset($map[$routeStopsName]) && is_array($map[$routeStopsName])) {
                foreach ($map[$routeStopsName] as $entry) {
                    if (isset($entry[0], $entry[2])) {
                        $radii[(int) $entry[0]] = (float) $entry[2];
                    }
                }
            }
        }

        break;
    }

    return $radii;
}

/* -------------------------------------------------------------------------
 * Fare matrix and passenger simulation
 *
 * `route_stops` decides where the bus stops and where passengers wait;
 * `fare_pricing` decides the valid boarding-point/destination combinations and
 * the actual fares. Two `route_stops` layouts are supported:
 *   - flat route layout: route_name + stop_name + latitude/longitude +
 *     sequence_order (coordinates stored per stop);
 *   - fare-matrix layout: fare_pricing_id + stop_name + km_marker +
 *     display_order (one row group per fare destination; coordinates are
 *     projected along the direction's waypoint corridor).
 * Simulated journeys are written only to `simulation_passengers` with a
 * `SIM-` passenger code, so they never mix with real passenger activity.
 * ------------------------------------------------------------------------- */

/**
 * Whether a table exists in the connected schema.
 */
function simulation_table_exists($conn, $table)
{
    static $cache = array();

    $key = strtolower((string) $table);

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $exists = false;

    try {
        $stmt = $conn->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $table);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } catch (\Throwable $error) {
        $exists = false;
    }

    $cache[$key] = $exists;

    return $exists;
}

/**
 * Stop-name normalization matching passenger/services/BoardingStopOrder.php so
 * "Cabanatuan City Terminal", "Cabanatuan Central Terminal", and "Cabanatuan"
 * compare on the same footing.
 */
function simulation_normalize_stop_name($value)
{
    $value = strtolower(trim((string) $value));
    $value = str_replace(
        array(', nueva ecija', ', cabanatuan city', ', cabanatuan', ' city'),
        '',
        $value
    );
    $value = preg_replace('/[^a-z0-9 ]/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);

    return trim($value);
}

/**
 * Whether two stop names refer to the same place: exact normalized match
 * first, then containment either way (live names are often shorter).
 */
function simulation_stop_name_matches($a, $b)
{
    $left = simulation_normalize_stop_name($a);
    $right = simulation_normalize_stop_name($b);

    if ($left === '' || $right === '') {
        return false;
    }

    if ($left === $right) {
        return true;
    }

    return strpos($left, $right) !== false || strpos($right, $left) !== false;
}

/**
 * The direction's fare rows from `fare_pricing`, matched by canonical route
 * name. Returns null when the table is missing or no row matches.
 *
 * @return array<int,array<string,mixed>>|null
 */
function simulation_fare_rows($conn, $direction)
{
    $directionConfig = simulation_direction_config($direction);

    if ($directionConfig === null || empty($directionConfig['fare_route_name'])) {
        return null;
    }

    if (!simulation_table_exists($conn, 'fare_pricing')) {
        return null;
    }

    $orderBy = simulation_table_has_column($conn, 'fare_pricing', 'display_order')
        ? 'display_order ASC, id ASC'
        : 'id ASC';

    try {
        $result = $conn->query("
            SELECT id, route_name, destination, regular_fare, student_fare, special_fare
            FROM fare_pricing
            ORDER BY $orderBy
        ");
    } catch (\Throwable $error) {
        return null;
    }

    if (!$result) {
        return null;
    }

    $wanted = simulation_canonical_route_name($directionConfig['fare_route_name']);
    $rows = array();

    while ($row = $result->fetch_assoc()) {
        if (simulation_canonical_route_name($row['route_name']) !== $wanted) {
            continue;
        }

        $rows[] = array(
            'id' => (int) $row['id'],
            'destination' => trim((string) $row['destination']),
            'regular' => (float) $row['regular_fare'],
            'student' => (float) $row['student_fare'],
            'special' => (float) $row['special_fare'],
        );
    }

    $result->free();

    return $rows ?: null;
}

/**
 * Position at a kilometer marker along a waypoint corridor.
 *
 * @param array<int,array<string,mixed>> $waypoints
 * @param float $km
 * @param float $maxKm
 * @return array{lat:float,lng:float}
 */
function simulation_project_km_onto_waypoints($waypoints, $km, $maxKm)
{
    $count = count($waypoints);

    if ($count === 0) {
        return array('lat' => null, 'lng' => null);
    }

    if ($count === 1 || $maxKm <= 0.0) {
        return array('lat' => (float) $waypoints[0]['lat'], 'lng' => (float) $waypoints[0]['lng']);
    }

    $lengths = array();
    $total = 0.0;

    for ($i = 0; $i + 1 < $count; $i++) {
        $length = simulation_haversine_m(
            (float) $waypoints[$i]['lat'],
            (float) $waypoints[$i]['lng'],
            (float) $waypoints[$i + 1]['lat'],
            (float) $waypoints[$i + 1]['lng']
        );
        $lengths[] = $length;
        $total += $length;
    }

    if ($total <= 0.0) {
        return array('lat' => (float) $waypoints[0]['lat'], 'lng' => (float) $waypoints[0]['lng']);
    }

    $fraction = max(0.0, min(1.0, (float) $km / (float) $maxKm));
    $target = $fraction * $total;
    $travelled = 0.0;

    for ($i = 0; $i + 1 < $count; $i++) {
        $segment = $lengths[$i];

        if ($travelled + $segment >= $target || $i + 2 === $count) {
            $ratio = $segment > 0.0 ? max(0.0, min(1.0, ($target - $travelled) / $segment)) : 0.0;

            return array(
                'lat' => (float) $waypoints[$i]['lat'] + ((float) $waypoints[$i + 1]['lat'] - (float) $waypoints[$i]['lat']) * $ratio,
                'lng' => (float) $waypoints[$i]['lng'] + ((float) $waypoints[$i + 1]['lng'] - (float) $waypoints[$i]['lng']) * $ratio,
            );
        }

        $travelled += $segment;
    }

    return array('lat' => (float) $waypoints[$count - 1]['lat'], 'lng' => (float) $waypoints[$count - 1]['lng']);
}

/**
 * The direction's ordered stops and fare matrix built from the real tables.
 *
 * Stops come from `route_stops`: the flat route layout (route_name +
 * coordinates + sequence_order) is preferred because it is the same stop list
 * the arrivals use; the fare-matrix layout grouped by `fare_pricing_id` (no
 * route name, coordinates projected along the corridor) is the fallback. A
 * `fare_pricing_id` on a flat-layout row never removes stops or restricts
 * boarding - it is the passenger app's zone annotation, not a boarding gate.
 * Boarding points for each fare row are every stop that precedes the fare's
 * destination in route order; linked rows are only used when no stop names the
 * destination.
 *
 * @return array<string,mixed>|null
 */
function simulation_load_fare_route($conn, $direction)
{
    $directionConfig = simulation_direction_config($direction);

    if ($directionConfig === null || !simulation_table_exists($conn, 'route_stops')) {
        return null;
    }

    $fares = simulation_fare_rows($conn, $direction);

    if ($fares === null) {
        return null;
    }

    $hasFareLink = simulation_table_has_column($conn, 'route_stops', 'fare_pricing_id');
    $hasKmMarker = simulation_table_has_column($conn, 'route_stops', 'km_marker');
    $hasDisplayOrder = simulation_table_has_column($conn, 'route_stops', 'display_order');
    $hasRouteName = simulation_table_has_column($conn, 'route_stops', 'route_name');
    $hasCoordinates = simulation_table_has_column($conn, 'route_stops', 'latitude')
        && simulation_table_has_column($conn, 'route_stops', 'longitude');
    $hasSequence = simulation_table_has_column($conn, 'route_stops', 'sequence_order');

    $fareIds = array();
    foreach ($fares as $fare) {
        $fareIds[] = (int) $fare['id'];
    }

    $orderParts = array();
    if ($hasKmMarker) {
        $orderParts[] = 'km_marker IS NULL ASC';
        $orderParts[] = 'km_marker ASC';
    }
    if ($hasDisplayOrder) {
        $orderParts[] = 'display_order ASC';
    }
    if ($hasSequence) {
        $orderParts[] = 'sequence_order ASC';
    }
    $orderParts[] = 'id ASC';
    $orderBy = implode(', ', $orderParts);

    $coordinateExpr = $hasCoordinates ? ', latitude, longitude' : '';
    $sequenceExpr = $hasSequence ? ', sequence_order' : '';
    $kmExpr = $hasKmMarker ? ', km_marker' : '';

    $rows = array();
    $rowsByFare = array();

    /* Flat route layout first: the stored route_stops sequence for the
       direction is the authoritative stop list - the same order the arrivals
       use. A `fare_pricing_id` left on a row (the passenger app's "which fare
       zone is this landmark" link) is only an annotation and must never
       shrink the passenger stop list: treating linked rows as the only
       boarding points left the terminal and most other stops without a
       reachable destination, so no waiting passengers ever appeared. */
    if ($hasRouteName) {
        $routeStopsName = (string) ($directionConfig['route_stops_name'] ?? '');
        $wanted = simulation_canonical_route_name($routeStopsName);

        try {
            $stmt = $conn->prepare("
                SELECT id, stop_name" . $coordinateExpr . $sequenceExpr . $kmExpr . "
                FROM route_stops
                WHERE route_name = ?
                ORDER BY $orderBy
            ");
            $stmt->bind_param("s", $routeStopsName);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            $stmt->close();
        } catch (\Throwable $error) {
            $rows = array();
        }

        if ($rows === array() && $wanted !== '') {
            try {
                $result = $conn->query("
                    SELECT id, route_name, stop_name" . $coordinateExpr . $sequenceExpr . $kmExpr . "
                    FROM route_stops
                    ORDER BY $orderBy
                ");

                while ($result && $row = $result->fetch_assoc()) {
                    if (simulation_canonical_route_name($row['route_name']) === $wanted) {
                        $rows[] = $row;
                    }
                }
            } catch (\Throwable $error) {
                $rows = array();
            }
        }
    }

    /* Fare-matrix layout fallback: rows grouped by fare_pricing_id, for older
       schemas whose route_stops carries no route name (stops are projected
       onto the corridor below). */
    if ($rows === array() && $hasFareLink && $fareIds !== array()) {
        try {
            $placeholders = implode(',', array_fill(0, count($fareIds), '?'));
            $stmt = $conn->prepare("
                SELECT id, fare_pricing_id, stop_name" . $coordinateExpr . $sequenceExpr . $kmExpr . "
                FROM route_stops
                WHERE fare_pricing_id IN ($placeholders)
                ORDER BY $orderBy
            ");
            $types = str_repeat('i', count($fareIds));
            $params = array();
            foreach ($fareIds as $fareId) {
                $params[] = (int) $fareId;
            }
            $bind = array($types);
            foreach ($params as $index => $value) {
                $bind[] = &$params[$index];
            }
            call_user_func_array(array($stmt, 'bind_param'), $bind);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
                $rowsByFare[(int) $row['fare_pricing_id']][] = $row;
            }

            $stmt->close();
        } catch (\Throwable $error) {
            $rows = array();
            $rowsByFare = array();
        }
    }

    if ($rows === array()) {
        return null;
    }

    /* Ordered unique stops: first appearance in route order wins. */
    $stops = array();
    $seenStops = array();

    foreach ($rows as $row) {
        $name = trim((string) $row['stop_name']);

        if ($name === '') {
            continue;
        }

        $key = simulation_normalize_stop_name($name);

        if ($key === '' || isset($seenStops[$key])) {
            continue;
        }

        $seenStops[$key] = true;
        $stops[] = array(
            'stop_id' => isset($row['id']) ? (int) $row['id'] : null,
            'stop_name' => $name,
            'latitude' => isset($row['latitude']) && $row['latitude'] !== null && $row['latitude'] !== ''
                ? (float) $row['latitude']
                : null,
            'longitude' => isset($row['longitude']) && $row['longitude'] !== null && $row['longitude'] !== ''
                ? (float) $row['longitude']
                : null,
            'km_marker' => isset($row['km_marker']) && $row['km_marker'] !== null && $row['km_marker'] !== ''
                ? (float) $row['km_marker']
                : null,
            'sequence_order' => isset($row['sequence_order']) && (int) $row['sequence_order'] > 0
                ? (int) $row['sequence_order']
                : null,
        );
    }

    if (count($stops) < 2) {
        return null;
    }

    /* Coordinates missing (fare-matrix layout): project by km marker - or even
       spacing - along the direction's corridor. */
    $needsProjection = false;
    $maxKm = 0.0;

    foreach ($stops as $stop) {
        if ($stop['latitude'] === null || $stop['longitude'] === null) {
            $needsProjection = true;
        }
        if ($stop['km_marker'] !== null) {
            $maxKm = max($maxKm, (float) $stop['km_marker']);
        }
    }

    if ($needsProjection) {
        $waypoints = simulation_normalize_waypoints($direction);
        $count = count($stops);
        $useKm = $maxKm > 0.0;

        foreach ($stops as $index => $stop) {
            if ($stop['latitude'] !== null && $stop['longitude'] !== null) {
                continue;
            }

            $km = $stop['km_marker'];
            if ($km === null) {
                $km = $useKm
                    ? ((float) $maxKm * $index / max(1, $count - 1))
                    : (float) $index;
            }

            $point = simulation_project_km_onto_waypoints($waypoints, $km, $useKm ? $maxKm : (float) max(1, $count - 1));

            if ($point['lat'] !== null && $point['lng'] !== null) {
                $stops[$index]['latitude'] = $point['lat'];
                $stops[$index]['longitude'] = $point['lng'];
            }
        }
    }

    foreach ($stops as $index => $stop) {
        if ($stop['sequence_order'] === null) {
            $stops[$index]['sequence_order'] = $index + 1;
        }
    }

    /* Boarding points per fare row. */
    $stopIndexByName = array();
    foreach ($stops as $index => $stop) {
        $stopIndexByName[simulation_normalize_stop_name($stop['stop_name'])] = $index;
    }

    foreach ($fares as $fareIndex => $fare) {
        $boarding = array();

        /* Boarding points are every stop before the fare's destination in
           route order, for both layouts: the fare_pricing_id link says which
           landmark stands for a fare zone, NOT which stops may buy the fare.
           Restricting boarding to the linked rows left the terminal (and most
           other stops) without any reachable destination, so the simulated bus
           picked up nobody along the route. */
        $destinationIndex = null;
        foreach ($stops as $index => $stop) {
            if (simulation_stop_name_matches($stop['stop_name'], $fare['destination'])) {
                $destinationIndex = $index;
                break;
            }
        }

        if ($destinationIndex !== null) {
            for ($index = 0; $index < $destinationIndex; $index++) {
                $boarding[] = $stops[$index]['stop_name'];
            }
        } elseif ($hasFareLink && isset($rowsByFare[(int) $fare['id']]) && $rowsByFare[(int) $fare['id']] !== array()) {
            /* No stop names the destination (fare-matrix layouts can carry
               destinations without a matching landmark): fall back to the
               rows linked to the fare. */
            $seenBoarding = array();
            foreach ($rowsByFare[(int) $fare['id']] as $row) {
                $name = trim((string) $row['stop_name']);
                $key = simulation_normalize_stop_name($name);
                if ($name !== '' && $key !== '' && !isset($seenBoarding[$key])) {
                    $seenBoarding[$key] = true;
                    $boarding[] = $name;
                }
            }
        }

        $fares[$fareIndex]['boarding_stops'] = $boarding;
    }

    return array(
        'route_name' => (string) ($directionConfig['fare_route_name'] ?? ''),
        'stops' => $stops,
        'fares' => $fares,
    );
}

/**
 * The fare_pricing row that covers a boarding stop -> destination pair, with
 * its fares. The destination must occur after the boarding stop in route
 * order, and the boarding stop must be a valid boarding point of the fare
 * row. Returns null (never a guessed fare) when nothing matches.
 *
 * @param array<string,mixed> $route simulation_load_fare_route() payload
 * @return array<string,mixed>|null
 */
function simulation_resolve_passenger_fare($route, $boardingStopName, $destinationStopName)
{
    if (!is_array($route) || empty($route['stops']) || empty($route['fares'])) {
        return null;
    }

    if (simulation_stop_name_matches($boardingStopName, $destinationStopName)) {
        return null;
    }

    $boardingIndex = null;
    $destinationIndex = null;

    foreach ($route['stops'] as $index => $stop) {
        if ($boardingIndex === null && simulation_stop_name_matches($stop['stop_name'], $boardingStopName)) {
            $boardingIndex = $index;
        }
        if ($destinationIndex === null && simulation_stop_name_matches($stop['stop_name'], $destinationStopName)) {
            $destinationIndex = $index;
        }
    }

    if ($boardingIndex === null || $destinationIndex === null || $destinationIndex <= $boardingIndex) {
        return null;
    }

    foreach ($route['fares'] as $fare) {
        if (!simulation_stop_name_matches($fare['destination'], $destinationStopName)) {
            continue;
        }

        $boardsHere = false;
        foreach ((array) ($fare['boarding_stops'] ?? array()) as $boardingName) {
            if (simulation_stop_name_matches($boardingName, $boardingStopName)) {
                $boardsHere = true;
                break;
            }
        }

        if (!$boardsHere) {
            continue;
        }

        return array(
            'fare_pricing_id' => (int) $fare['id'],
            'destination' => (string) $fare['destination'],
            'boarding_stop' => (string) $route['stops'][$boardingIndex]['stop_name'],
            'destination_stop' => (string) $route['stops'][$destinationIndex]['stop_name'],
            'boarding_sequence' => (int) $route['stops'][$boardingIndex]['sequence_order'],
            'destination_sequence' => (int) $route['stops'][$destinationIndex]['sequence_order'],
            'regular' => (float) $fare['regular'],
            'student' => (float) $fare['student'],
            'special' => (float) $fare['special'],
        );
    }

    return null;
}

/* -------------------------------------------------------------------------
 * Simulated passengers: real `passengers` + `passenger_trips` records
 *
 * Waiting/onboard/alighted journey state lives in the session's
 * `passenger_manifest` JSON column (no simulation passenger table). When a
 * rider boards, a real passenger account (`passengers`, id `SIM-...`) and a
 * real boarding record (`passenger_trips`, with trip_no/bus_no/driver_id and
 * `scan_location_source = 'simulation'`) are written, so the driver and
 * inspector apps see the simulated riders. Alighting stamps the boarding
 * record's drop-off columns when the schema has them. The `SIM-` prefix and
 * the simulation scan source keep these rows apart from real activity, and
 * restarting a session deletes only its own SIM rows before rebuilding them.
 * ------------------------------------------------------------------------- */

/** Column metadata (Field/Type/Null/Default/Extra) per table, cached. */
function simulation_columns_info($conn, $table)
{
    static $cache = array();

    $key = (is_object($conn) ? spl_object_id($conn) : 0) . '|' . strtolower((string) $table);

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $columns = array();

    try {
        $result = $conn->query("SHOW COLUMNS FROM `" . str_replace('`', '', (string) $table) . "`");

        while ($result && $row = $result->fetch_assoc()) {
            $columns[strtolower((string) $row['Field'])] = $row;
        }
    } catch (\Throwable $error) {
        $columns = array();
    }

    $cache[$key] = $columns;

    return $columns;
}

/** A safely escaped SQL literal for generated demo values. */
function simulation_sql_literal($conn, $value)
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value)) {
        return (string) $value;
    }
    if (is_float($value)) {
        return var_export($value, true);
    }

    return "'" . $conn->real_escape_string((string) $value) . "'";
}

/** A type-based default for a required column without a default value. */
function simulation_column_default(array $column)
{
    $type = strtolower((string) ($column['Type'] ?? ''));

    if (strpos($type, 'int') !== false) {
        return 0;
    }
    if (strpos($type, 'decimal') !== false || strpos($type, 'double') !== false || strpos($type, 'float') !== false) {
        return 0;
    }
    if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
        return simulation_now();
    }
    if (strpos($type, 'date') !== false) {
        return date('Y-m-d');
    }
    if (strpos($type, 'time') !== false) {
        return date('H:i:s');
    }
    if (strpos($type, 'enum(') !== false && preg_match("/^enum\\('(.*?)'/", $type, $match) === 1) {
        return $match[1];
    }

    return '';
}

/**
 * Schema-adaptive INSERT for the real passenger tables: only columns the
 * deployment actually has are written, required columns without defaults get
 * a safe type-based value, and the generated auto-increment id (if any) is
 * returned (-1 on failure).
 */
function simulation_insert_row($conn, $table, array $values)
{
    $columns = simulation_columns_info($conn, $table);

    if ($columns === array()) {
        return -1;
    }

    $byLower = array();
    foreach ($values as $key => $value) {
        $byLower[strtolower((string) $key)] = $value;
    }

    $insertCols = array();
    $insertVals = array();

    foreach ($columns as $lower => $column) {
        $extra = strtolower((string) ($column['Extra'] ?? ''));

        if (strpos($extra, 'auto_increment') !== false || strpos($extra, 'generated') !== false) {
            continue;
        }

        if (array_key_exists($lower, $byLower)) {
            $insertCols[] = (string) $column['Field'];
            $insertVals[] = simulation_sql_literal($conn, $byLower[$lower]);
            continue;
        }

        $required = strtoupper((string) ($column['Null'] ?? '')) === 'NO';
        $hasDefault = array_key_exists('Default', $column)
            && $column['Default'] !== null
            && strtoupper((string) $column['Default']) !== 'NULL';

        if ($required && !$hasDefault) {
            $insertCols[] = (string) $column['Field'];
            $insertVals[] = simulation_sql_literal($conn, simulation_column_default($column));
        }
    }

    if ($insertCols === array()) {
        return -1;
    }

    $sql = 'INSERT INTO `' . str_replace('`', '', (string) $table) . '` (`' . implode('`, `', $insertCols)
        . '`) VALUES (' . implode(', ', $insertVals) . ')';

    try {
        if (!$conn->query($sql)) {
            return -1;
        }
    } catch (\Throwable $error) {
        error_log('Simulation insert failed for ' . $table . ': ' . $error->getMessage());

        return -1;
    }

    return (int) $conn->insert_id;
}

/** Schema-adaptive UPDATE by a key column (only existing columns are set). */
function simulation_update_row($conn, $table, $keyColumn, $keyValue, array $values)
{
    $columns = simulation_columns_info($conn, $table);
    $keyLower = strtolower((string) $keyColumn);

    if ($columns === array() || !isset($columns[$keyLower])) {
        return false;
    }

    $sets = array();

    foreach ($values as $key => $value) {
        $lower = strtolower((string) $key);

        if (!isset($columns[$lower])) {
            continue;
        }

        $sets[] = '`' . $columns[$lower]['Field'] . '` = ' . simulation_sql_literal($conn, $value);
    }

    if ($sets === array()) {
        return false;
    }

    $sql = 'UPDATE `' . str_replace('`', '', (string) $table) . '` SET ' . implode(', ', $sets)
        . ' WHERE `' . $columns[$keyLower]['Field'] . '` = ' . simulation_sql_literal($conn, $keyValue) . ' LIMIT 1';

    try {
        return (bool) $conn->query($sql);
    } catch (\Throwable $error) {
        error_log('Simulation update failed for ' . $table . ': ' . $error->getMessage());

        return false;
    }
}

/**
 * Idempotently ensure the simulation passenger-state columns on
 * `simulation_sessions` (demand, seat capacity, and the JSON manifest).
 * Passengers themselves live in the real `passengers` / `passenger_trips`
 * tables - no simulation passenger table exists.
 */
function simulation_passenger_schema_ready($conn)
{
    static $ready = array();

    $key = is_object($conn) ? spl_object_id($conn) : 0;

    if (array_key_exists($key, $ready)) {
        return $ready[$key];
    }

    $ok = false;

    try {
        if (!simulation_table_has_column($conn, 'simulation_sessions', 'passenger_demand')) {
            $conn->query("ALTER TABLE `simulation_sessions` ADD COLUMN `passenger_demand` varchar(10) NOT NULL DEFAULT 'normal'");
        }
        if (!simulation_table_has_column($conn, 'simulation_sessions', 'passenger_capacity')) {
            $conn->query("ALTER TABLE `simulation_sessions` ADD COLUMN `passenger_capacity` int(11) NOT NULL DEFAULT 0");
        }
        if (!simulation_table_has_column($conn, 'simulation_sessions', 'passenger_manifest')) {
            $conn->query("ALTER TABLE `simulation_sessions` ADD COLUMN `passenger_manifest` mediumtext NULL");
        }

        $ok = true;
    } catch (\Throwable $error) {
        $ok = false;
    }

    $ready[$key] = $ok;

    return $ok;
}

/**
 * The session's passenger manifest (decoded JSON), or null when none exists.
 */
function simulation_manifest_load($conn, $session)
{
    if (!is_array($session) || empty($session['id'])) {
        return null;
    }

    try {
        $stmt = $conn->prepare("SELECT passenger_manifest FROM simulation_sessions WHERE id = ? LIMIT 1");
        $sessionId = (int) $session['id'];
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (\Throwable $error) {
        return null;
    }

    if (!$row || $row['passenger_manifest'] === null || trim((string) $row['passenger_manifest']) === '') {
        return null;
    }

    $manifest = json_decode((string) $row['passenger_manifest'], true);

    return is_array($manifest) ? $manifest : null;
}

function simulation_manifest_save($conn, $session_id, array $manifest)
{
    $json = json_encode($manifest);
    $now = simulation_now();

    try {
        $stmt = $conn->prepare("UPDATE simulation_sessions SET passenger_manifest = ?, updated_at = ? WHERE id = ?");
        $sessionId = (int) $session_id;
        $stmt->bind_param("ssi", $json, $now, $sessionId);
        $stmt->execute();
        $stmt->close();
    } catch (\Throwable $error) {
        error_log('Simulation manifest save failed: ' . $error->getMessage());
    }
}

/**
 * Normalize a demand key against the configured demand ranges.
 */
function simulation_normalize_demand($demand)
{
    $demand = strtolower(trim((string) $demand));
    $config = simulation_config();
    $ranges = isset($config['passenger_demand']) ? $config['passenger_demand'] : array();

    return isset($ranges[$demand]) ? $demand : 'normal';
}

/**
 * The seat capacity for a bus: the bus record's seats when present, otherwise
 * the configured default.
 */
function simulation_capacity_for_bus($conn, $bus_id)
{
    $config = simulation_config();
    $default = isset($config['passenger_capacity_default']) ? (int) $config['passenger_capacity_default'] : 50;

    if ($bus_id === '' || !simulation_table_exists($conn, 'buses')) {
        return $default;
    }

    try {
        $stmt = $conn->prepare("SELECT seats FROM buses WHERE bus_id = ? LIMIT 1");
        $stmt->bind_param("s", $bus_id);
        $stmt->execute();
        $seats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (\Throwable $error) {
        return $default;
    }

    $seats = $seats && isset($seats['seats']) ? (int) $seats['seats'] : 0;

    return $seats > 0 ? $seats : $default;
}

/**
 * Drop the session's simulated boardings and manifest so a restart (or a new
 * demand level) rebuilds them without duplicates. Only rows belonging to this
 * session's bus trip and to `SIM-` passenger accounts are deleted; real
 * passenger activity is never touched.
 */
function simulation_clear_passengers($conn, $session)
{
    if (!is_array($session) || empty($session['id'])) {
        return;
    }

    $sessionId = (int) $session['id'];
    $tripId = isset($session['trip_id']) && $session['trip_id'] !== null ? (string) $session['trip_id'] : '';

    if (
        $tripId !== ''
        && simulation_table_exists($conn, 'passenger_trips')
        && simulation_table_has_column($conn, 'passenger_trips', 'trip_no')
    ) {
        /* Both account models are matched: the code in `passenger_code`
           (numeric-id deployments) and the code as `passenger_id` (legacy
           deployments). Only this trip's SIM- rows are removed. */
        $codeConditions = array();

        if (simulation_table_has_column($conn, 'passenger_trips', 'passenger_code')) {
            $codeConditions[] = "passenger_code LIKE 'SIM-%'";
        }
        if (simulation_table_has_column($conn, 'passenger_trips', 'passenger_id')) {
            $codeConditions[] = "passenger_id LIKE 'SIM-%'";
        }

        if ($codeConditions !== array()) {
            try {
                $stmt = $conn->prepare(
                    "DELETE FROM passenger_trips WHERE trip_no = ? AND (" . implode(' OR ', $codeConditions) . ")"
                );
                $stmt->bind_param("s", $tripId);
                $stmt->execute();
                $stmt->close();
            } catch (\Throwable $error) {
                error_log('Simulation passenger cleanup failed: ' . $error->getMessage());
            }
        }
    }

    $capacity = (int) ($session['passenger_capacity'] ?? 0);
    if ($capacity <= 0) {
        $capacity = simulation_capacity_for_bus($conn, (string) ($session['bus_id'] ?? ''));
    }

    simulation_manifest_save($conn, $sessionId, array(
        'demand' => simulation_normalize_demand($session['passenger_demand'] ?? 'normal'),
        'capacity' => $capacity,
        'passengers' => array(),
    ));
}

/**
 * Generate the waiting passengers for a session from the route's stop order
 * and the fare matrix, and store them in the session's passenger manifest.
 * Deterministic per session (seeded RNG), so restarting rebuilds the exact
 * same journeys instead of duplicating them. Returns the number of journeys
 * generated, or -1 when the route/fare data cannot support passengers (the
 * reason is logged for diagnosis).
 */
function simulation_generate_passengers($conn, $session, $route = null)
{
    if (!$session || !simulation_passenger_schema_ready($conn)) {
        return -1;
    }

    $sessionId = (int) $session['id'];
    $demand = simulation_normalize_demand($session['passenger_demand'] ?? 'normal');
    $config = simulation_config();
    $ranges = isset($config['passenger_demand']) ? $config['passenger_demand'] : array();
    $range = isset($ranges[$demand]) ? $ranges[$demand] : array('min' => 1, 'max' => 4);

    $capacity = (int) ($session['passenger_capacity'] ?? 0);
    if ($capacity <= 0) {
        $capacity = simulation_capacity_for_bus($conn, (string) ($session['bus_id'] ?? ''));
    }

    if ((int) $range['max'] <= 0) {
        simulation_manifest_save($conn, $sessionId, array(
            'demand' => $demand,
            'capacity' => $capacity,
            'passengers' => array(),
        ));

        return 0;
    }

    if ($route === null) {
        $route = simulation_load_fare_route($conn, (string) $session['route_direction']);
    }

    if ($route === null || count($route['stops']) < 2 || empty($route['fares'])) {
        error_log('Simulation passenger generation skipped: no route_stops/fare_pricing coverage for direction ' . (string) $session['route_direction']);

        return -1;
    }

    $stops = $route['stops'];
    $stopCount = count($stops);
    $originBoost = isset($config['passenger_origin_boost']) ? (int) $config['passenger_origin_boost'] : 2;

    /* Valid (boarding -> destination) pairs and their fares, resolved once
       through the fare matrix; stops without any valid destination are
       reported instead of inventing a fare. */
    $destinationsByBoarding = array();
    $unresolved = array();

    for ($i = 0; $i < $stopCount - 1; $i++) {
        $choices = array();
        for ($j = $i + 1; $j < $stopCount; $j++) {
            $resolved = simulation_resolve_passenger_fare($route, $stops[$i]['stop_name'], $stops[$j]['stop_name']);
            if ($resolved !== null) {
                $choices[] = array('index' => $j, 'fare' => $resolved);
            }
        }
        if ($choices === array()) {
            $unresolved[] = $stops[$i]['stop_name'];
        }
        $destinationsByBoarding[$i] = $choices;
    }

    if ($unresolved !== array()) {
        error_log('Simulation passengers: no valid fare-matrix destination for stop(s) ' . implode(', ', $unresolved) . ' on direction ' . (string) $session['route_direction']);
    }

    mt_srand(100000 + $sessionId);

    $typeProfiles = array(
        array('Regular', 'regular', 68),
        array('Student', 'student', 15),
        array('Senior', 'special', 10),
        array('PWD', 'special', 7),
    );
    $firstNames = array('Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Carlo', 'Liza', 'Mark', 'Grace', 'Paolo', 'Elena', 'Ramon', 'Celia', 'Nico', 'Teresa', 'Miguel', 'Diana', 'Rico', 'Nena', 'Ben', 'Joy', 'Arnel', 'Mila');
    $lastNames = array('Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Bautista', 'Cruz', 'Lopez', 'Tan', 'Ramos', 'Aquino', 'Mendoza', 'Lim', 'Torres', 'Villamor', 'Castro', 'Domingo');

    $now = simulation_now();
    $journeys = array();
    $sequenceNumber = 0;

    for ($i = 0; $i < $stopCount - 1; $i++) {
        $choices = $destinationsByBoarding[$i];
        if ($choices === array()) {
            continue;
        }

        $count = mt_rand((int) $range['min'], (int) $range['max']);
        if ($i === 0) {
            $count += $originBoost;
        }

        for ($n = 0; $n < $count; $n++) {
            /* Fare-aware destination pick: nearer stops carry more riders. */
            $totalWeight = 0.0;
            $weights = array();
            foreach ($choices as $choice) {
                $weight = 1.0 / (1.0 + ((int) $choice['index'] - $i));
                $weights[] = $weight;
                $totalWeight += $weight;
            }
            $roll = (mt_rand(0, 1000000) / 1000000.0) * $totalWeight;
            $picked = $choices[0];
            foreach ($choices as $choiceIndex => $choice) {
                $roll -= $weights[$choiceIndex];
                if ($roll <= 0.0) {
                    $picked = $choice;
                    break;
                }
            }

            $typeRoll = mt_rand(1, 100);
            $type = $typeProfiles[0];
            $accumulated = 0;
            foreach ($typeProfiles as $candidate) {
                $accumulated += $candidate[2];
                if ($typeRoll <= $accumulated) {
                    $type = $candidate;
                    break;
                }
            }

            $fareRow = $picked['fare'];
            $fare = $type[1] === 'student' ? $fareRow['student'] : ($type[1] === 'special' ? $fareRow['special'] : $fareRow['regular']);
            $name = $firstNames[mt_rand(0, count($firstNames) - 1)] . ' ' . $lastNames[mt_rand(0, count($lastNames) - 1)];
            $sequenceNumber++;

            $journeys[] = array(
                'code' => 'SIM-' . $sessionId . '-' . str_pad((string) $sequenceNumber, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'type' => $type[0],
                'fare_type' => $type[1],
                'fare' => round((float) $fare, 2),
                'fare_pricing_id' => (int) $fareRow['fare_pricing_id'],
                'boarding' => (string) $stops[$i]['stop_name'],
                'boarding_sequence' => (int) $stops[$i]['sequence_order'],
                'destination' => (string) $stops[$picked['index']]['stop_name'],
                'destination_sequence' => (int) $stops[$picked['index']]['sequence_order'],
                'stop_id' => isset($stops[$i]['stop_id']) ? (int) $stops[$i]['stop_id'] : null,
                'stop_lat' => $stops[$i]['latitude'] !== null ? (float) $stops[$i]['latitude'] : null,
                'stop_lng' => $stops[$i]['longitude'] !== null ? (float) $stops[$i]['longitude'] : null,
                'status' => 'waiting',
                'waiting_since' => $now,
                'boarded_at' => null,
                'alighted_at' => null,
                'trip_record_id' => null,
            );
        }
    }

    simulation_manifest_save($conn, $sessionId, array(
        'demand' => $demand,
        'capacity' => $capacity,
        'generated_at' => $now,
        'passengers' => $journeys,
    ));

    return count($journeys);
}

/**
 * Write the real passenger account (`passengers`) and the real boarding record
 * (`passenger_trips`) for one boarding journey, so the driver and inspector
 * apps see the simulated rider. Only columns the deployment actually has are
 * written. Returns the boarding record id (0 on failure).
 *
 * The account model differs between deployments: the live passenger app uses a
 * numeric auto-increment `passengers.id` with the `SIM-...` string as
 * `passenger_code`, while older schemas use the code itself as the primary id.
 * The account's own primary id (numeric or code) is what goes into
 * `passenger_trips.passenger_id` when the table expects numbers; the code is
 * always carried in `passenger_code`. Writing the string code into a numeric
 * `passenger_id` failed the whole boarding insert under strict SQL mode, which
 * left every rider stuck in "waiting" forever.
 */
function simulation_board_passenger_record($conn, $session, array $journey, $at)
{
    $code = (string) ($journey['code'] ?? '');

    if ($code === '') {
        return 0;
    }

    /* The passenger account's primary id: numeric on the live model, the code
       itself on older ones. Resolved by code first so restarts reuse the same
       account, then created when missing. */
    $passengerAccountId = null;

    if (simulation_table_exists($conn, 'passengers')) {
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
 * Stamp the boarding record's drop-off when the passenger alights (only
 * columns the deployment has are written; the record itself is history and is
 * never deleted).
 */
function simulation_alight_passenger_record($conn, $session, array $journey, $at)
{
    $recordId = isset($journey['trip_record_id']) ? (int) $journey['trip_record_id'] : 0;

    if ($recordId <= 0 || !simulation_table_exists($conn, 'passenger_trips')) {
        return false;
    }

    return simulation_update_row($conn, 'passenger_trips', 'id', $recordId, array(
        'dropoff_time' => $at,
        'actual_destination' => (string) ($journey['destination'] ?? ''),
    ));
}

/**
 * Process one stop arrival for passengers: alighting first, then boarding the
 * waiting queue up to the bus's seat capacity. Boarding only ever happens for
 * the stop the bus is at (passed stops can never board).
 *
 * @param array<string,mixed> $stop The arrived stop (sequence_order, stop_name)
 * @return array{alighted:int,boarded:int}
 */
function simulation_process_stop_passengers($conn, $session, array $stop)
{
    $result = array('alighted' => 0, 'boarded' => 0);

    if (!$session || !simulation_passenger_schema_ready($conn)) {
        return $result;
    }

    $sequence = (int) ($stop['sequence_order'] ?? 0);

    if ($sequence <= 0) {
        return $result;
    }

    $manifest = simulation_manifest_load($conn, $session);

    if ($manifest === null || empty($manifest['passengers'])) {
        return $result;
    }

    $now = simulation_now();
    $passengers = $manifest['passengers'];

    /* Alight first so seats free up before boarding. */
    foreach ($passengers as $index => $journey) {
        if (
            (string) ($journey['status'] ?? '') === 'onboard'
            && (int) ($journey['destination_sequence'] ?? 0) === $sequence
        ) {
            $passengers[$index]['status'] = 'alighted';
            $passengers[$index]['alighted_at'] = $now;
            simulation_alight_passenger_record($conn, $session, $journey, $now);
            $result['alighted']++;
        }
    }

    $capacity = (int) ($manifest['capacity'] ?? 0);
    if ($capacity <= 0) {
        $capacity = simulation_capacity_for_bus($conn, (string) ($session['bus_id'] ?? ''));
    }

    $onboard = 0;
    foreach ($passengers as $journey) {
        if ((string) ($journey['status'] ?? '') === 'onboard') {
            $onboard++;
        }
    }

    $room = max(0, $capacity - $onboard);

    foreach ($passengers as $index => $journey) {
        if ($room <= 0) {
            break;
        }
        if ((string) ($journey['status'] ?? '') !== 'waiting') {
            continue;
        }
        if ((int) ($journey['boarding_sequence'] ?? 0) !== $sequence) {
            continue;
        }

        $recordId = simulation_board_passenger_record($conn, $session, $journey, $now);

        /* Without the real boarding row there is no onboard state; the rider
           stays waiting instead of faking the record. */
        if ($recordId <= 0) {
            continue;
        }

        $passengers[$index]['status'] = 'onboard';
        $passengers[$index]['boarded_at'] = $now;
        $passengers[$index]['trip_record_id'] = $recordId;
        $result['boarded']++;
        $room--;
    }

    $manifest['passengers'] = $passengers;
    simulation_manifest_save($conn, (int) $session['id'], $manifest);

    return $result;
}

/**
 * The passenger summary for a session payload: totals, fares collected, the
 * onboard list, and waiting counts per upcoming stop, all read from the
 * session's passenger manifest.
 *
 * @param array<int,array<string,mixed>> $stops
 * @return array<string,mixed>
 */
function simulation_passenger_summary($conn, $session, array $stops = array())
{
    $demand = simulation_normalize_demand($session['passenger_demand'] ?? 'normal');
    $capacity = (int) ($session['passenger_capacity'] ?? 0);

    if ($capacity <= 0 && is_array($session)) {
        $capacity = simulation_capacity_for_bus($conn, (string) ($session['bus_id'] ?? ''));
    }

    $summary = array(
        'available' => false,
        'demand' => $demand,
        'capacity' => $capacity,
        'waiting' => 0,
        'onboard' => 0,
        'boarded' => 0,
        'alighted' => 0,
        'fare_total' => 0.0,
        'onboard_list' => array(),
        'waiting_by_sequence' => array(),
        'by_stop' => array(),
    );

    if (!$session || !simulation_passenger_schema_ready($conn)) {
        return $summary;
    }

    $summary['available'] = true;
    $manifest = simulation_manifest_load($conn, $session);

    if ($manifest === null) {
        return $summary;
    }

    if ((int) ($manifest['capacity'] ?? 0) > 0) {
        $summary['capacity'] = (int) $manifest['capacity'];
    }
    $summary['demand'] = simulation_normalize_demand($manifest['demand'] ?? $demand);

    foreach ((array) ($manifest['passengers'] ?? array()) as $journey) {
        $status = (string) ($journey['status'] ?? 'waiting');

        if ($status === 'waiting') {
            $summary['waiting']++;
            $sequenceKey = (string) ((int) ($journey['boarding_sequence'] ?? 0));
            $summary['waiting_by_sequence'][$sequenceKey] = ($summary['waiting_by_sequence'][$sequenceKey] ?? 0) + 1;
            continue;
        }

        if ($status === 'onboard') {
            $summary['onboard']++;
            if (count($summary['onboard_list']) < 12) {
                $summary['onboard_list'][] = array(
                    'passenger_name' => (string) ($journey['name'] ?? ''),
                    'passenger_type' => (string) ($journey['type'] ?? ''),
                    'destination_stop' => (string) ($journey['destination'] ?? ''),
                    'fare' => round((float) ($journey['fare'] ?? 0), 2),
                );
            }
        } elseif ($status === 'alighted') {
            $summary['alighted']++;
        }

        $summary['fare_total'] += (float) ($journey['fare'] ?? 0);
    }

    $summary['boarded'] = $summary['onboard'] + $summary['alighted'];
    $summary['fare_total'] = round($summary['fare_total'], 2);

    foreach ($stops as $stop) {
        $sequence = (int) ($stop['sequence_order'] ?? 0);
        $summary['by_stop'][] = array(
            'sequence_order' => $sequence,
            'stop_name' => (string) ($stop['stop_name'] ?? ''),
            'waiting' => $summary['waiting_by_sequence'][(string) $sequence] ?? 0,
        );
    }

    return $summary;
}

/**
 * Write one inspector's live position to `inspectors_location`.
 *
 * Probe-then-write instead of an upsert: without a unique index on
 * `inspector_id` an `ON DUPLICATE KEY UPDATE` silently appends, and an UPDATE
 * that changes nothing reports zero affected rows. Only columns the table
 * actually has are written.
 *
 * Lives in the engine (not only the console) so the shared tick and the
 * inspector read/guard endpoints can reuse the exact same write. It is additive
 * and never touched by bus movement.
 */
function simulation_write_inspector_location($conn, $inspector_id, $bus_id, $latitude, $longitude)
{
    $inspector_id = trim((string) $inspector_id);

    if ($inspector_id === '' || !simulation_table_has_column($conn, 'inspectors_location', 'inspector_id')) {
        return false;
    }

    $columns = array();

    try {
        $result = $conn->query("SHOW COLUMNS FROM inspectors_location");

        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }

        $result->free();
    } catch (\Throwable $error) {
        return false;
    }

    $now = simulation_now();
    $timestampColumns = array();

    foreach (array('updated_at', 'recorded_at') as $candidate) {
        if (isset($columns[$candidate])) {
            $timestampColumns[] = $candidate;
        }
    }

    $includeBus = isset($columns['bus_id']) && $bus_id !== '';

    try {
        $check = $conn->prepare("SELECT 1 FROM inspectors_location WHERE inspector_id = ? LIMIT 1");
        $check->bind_param("s", $inspector_id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $sets = array('latitude = ?', 'longitude = ?');
            $types = 'dd';
            $values = array($latitude, $longitude);

            foreach ($timestampColumns as $timestampColumn) {
                $sets[] = $timestampColumn . ' = ?';
                $types .= 's';
                $values[] = $now;
            }

            if ($includeBus) {
                $sets[] = 'bus_id = ?';
                $types .= 's';
                $values[] = $bus_id;
            }

            $values[] = $inspector_id;
            $types .= 's';

            $stmt = $conn->prepare("UPDATE inspectors_location SET " . implode(', ', $sets) . " WHERE inspector_id = ?");
            $bind = array($types);

            foreach ($values as $index => $value) {
                $bind[] = &$values[$index];
            }

            call_user_func_array(array($stmt, 'bind_param'), $bind);
            $stmt->execute();
            $stmt->close();

            return true;
        }

        $insertColumns = array('inspector_id', 'latitude', 'longitude');
        $insertValues = array($inspector_id, $latitude, $longitude);
        $insertTypes = 'sdd';

        foreach ($timestampColumns as $timestampColumn) {
            $insertColumns[] = $timestampColumn;
            $insertValues[] = $now;
            $insertTypes .= 's';
        }

        if ($includeBus) {
            $insertColumns[] = 'bus_id';
            $insertValues[] = $bus_id;
            $insertTypes .= 's';
        }

        $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
        $stmt = $conn->prepare("INSERT INTO inspectors_location (" . implode(', ', $insertColumns) . ") VALUES ($placeholders)");
        $bind = array($insertTypes);

        foreach ($insertValues as $index => $value) {
            $bind[] = &$insertValues[$index];
        }

        call_user_func_array(array($stmt, 'bind_param'), $bind);
        $stmt->execute();
        $stmt->close();
    } catch (\Throwable $error) {
        return false;
    }

    return true;
}

/* -------------------------------------------------------------------------
 * Inspector simulation state
 *
 * One additive row per inspector (`simulation_inspector_state`), driven by the
 * console's Pinagpanaan action and synchronized to the bus session on every
 * tick. The helpers are schema-tolerant: on a database without the additive
 * table every one no-ops quietly, exactly like the location write above.
 * ------------------------------------------------------------------------- */

/**
 * Whether the additive inspector-state table is present.
 */
function simulation_inspector_state_ready($conn)
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    $ready = simulation_table_has_column($conn, 'simulation_inspector_state', 'inspector_id');

    return $ready;
}

/**
 * The columns `simulation_inspector_state` declares, or an empty map.
 */
function simulation_inspector_state_columns($conn)
{
    static $columns = null;

    if ($columns !== null) {
        return $columns;
    }

    $columns = array();

    try {
        $result = $conn->query("SHOW COLUMNS FROM simulation_inspector_state");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[$row['Field']] = true;
            }

            $result->free();
        }
    } catch (\Throwable $error) {
        $columns = array();
    }

    return $columns;
}

/**
 * Probe-then-write one inspector's state row, writing only declared columns.
 *
 * `$values` is a column => value map; `NULL` clears a column (used to end the
 * onboard link without touching the inspector's last known coordinates).
 */
function simulation_inspector_state_write($conn, $inspector_id, $values)
{
    $inspector_id = trim((string) $inspector_id);

    if ($inspector_id === '' || !simulation_inspector_state_ready($conn)) {
        return false;
    }

    $columns = simulation_inspector_state_columns($conn);

    if (empty($columns)) {
        return false;
    }

    $now = simulation_now();
    $writable = array();

    foreach ($values as $column => $value) {
        if (isset($columns[$column])) {
            $writable[$column] = $value;
        }
    }

    try {
        $check = $conn->prepare("SELECT 1 FROM simulation_inspector_state WHERE inspector_id = ? LIMIT 1");
        $check->bind_param("s", $inspector_id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            if (isset($columns['updated_at'])) {
                $writable['updated_at'] = $now;
            }

            if (empty($writable)) {
                return true;
            }

            $sets = array();
            $types = '';
            $bindValues = array();

            foreach ($writable as $column => $value) {
                $sets[] = $column . ' = ?';
                $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
                $bindValues[] = $value;
            }

            $types .= 's';
            $bindValues[] = $inspector_id;

            $stmt = $conn->prepare("UPDATE simulation_inspector_state SET " . implode(', ', $sets) . " WHERE inspector_id = ?");
            $bind = array($types);

            foreach ($bindValues as $index => $value) {
                $bind[] = &$bindValues[$index];
            }

            call_user_func_array(array($stmt, 'bind_param'), $bind);
            $stmt->execute();
            $stmt->close();

            return true;
        }

        $insertColumns = array('inspector_id');
        $insertValues = array($inspector_id);
        $insertTypes = 's';

        foreach ($writable as $column => $value) {
            $insertColumns[] = $column;
            $insertValues[] = $value;
            $insertTypes .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }

        if (isset($columns['created_at'])) {
            $insertColumns[] = 'created_at';
            $insertValues[] = $now;
            $insertTypes .= 's';
        }

        if (isset($columns['updated_at'])) {
            $insertColumns[] = 'updated_at';
            $insertValues[] = $now;
            $insertTypes .= 's';
        }

        $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
        $stmt = $conn->prepare("INSERT INTO simulation_inspector_state (" . implode(', ', $insertColumns) . ") VALUES ($placeholders)");
        $bind = array($insertTypes);

        foreach ($insertValues as $index => $value) {
            $bind[] = &$insertValues[$index];
        }

        call_user_func_array(array($stmt, 'bind_param'), $bind);
        $stmt->execute();
        $stmt->close();
    } catch (\Throwable $error) {
        return false;
    }

    return true;
}

/**
 * Record an inspector as onboard a bus session and set their coordinates.
 */
function simulation_inspector_set_onboard($conn, $inspector_id, $bus_id, $session_id, $latitude = null, $longitude = null, $status = 'Onboard', $created_by = null)
{
    $values = array(
        'status' => $status,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'onboard_bus_id' => $bus_id !== '' ? $bus_id : null,
        'onboard_session_id' => $session_id > 0 ? (int) $session_id : null,
    );

    if ($created_by !== null) {
        $values['created_by'] = $created_by;
    }

    return simulation_inspector_state_write($conn, $inspector_id, $values);
}

/**
 * End an inspector's onboard link while keeping their last coordinates.
 */
function simulation_inspector_set_offboard($conn, $inspector_id, $status = 'Offboard')
{
    return simulation_inspector_state_write($conn, $inspector_id, array(
        'status' => $status,
        'onboard_bus_id' => null,
        'onboard_session_id' => null,
    ));
}

/**
 * Every inspector currently onboard the given session (by session id, falling
 * back to the bus id). Read-only.
 */
function simulation_onboard_inspectors_for_session($conn, $session)
{
    if (!$session || !simulation_inspector_state_ready($conn)) {
        return array();
    }

    $sessionId = isset($session['id']) ? (int) $session['id'] : 0;
    $busId = isset($session['bus_id']) ? (string) $session['bus_id'] : '';
    $rows = array();

    try {
        if ($sessionId > 0) {
            $stmt = $conn->prepare("
                SELECT *
                FROM simulation_inspector_state
                WHERE onboard_session_id = ?
                ORDER BY id ASC
            ");
            $stmt->bind_param("i", $sessionId);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            $stmt->close();
        }

        if (empty($rows) && $busId !== '') {
            $stmt = $conn->prepare("
                SELECT *
                FROM simulation_inspector_state
                WHERE onboard_bus_id = ?
                ORDER BY id ASC
            ");
            $stmt->bind_param("s", $busId);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }

            $stmt->close();
        }
    } catch (\Throwable $error) {
        return array();
    }

    return $rows;
}

/**
 * The first inspector onboard the given session, or null.
 */
function simulation_inspector_state_for_session($conn, $session)
{
    $rows = simulation_onboard_inspectors_for_session($conn, $session);

    return empty($rows) ? null : $rows[0];
}

/**
 * Advance every onboard inspector's stored location to the session position.
 *
 * The session's own `current_lat`/`current_lng` is the single movement source;
 * nothing about the bus motion is recomputed here. Returns how many rows were
 * synchronized so a caller/test can tell "no inspector onboard" from "failed".
 */
function simulation_sync_onboard_inspectors($conn, $session)
{
    $rows = simulation_onboard_inspectors_for_session($conn, $session);

    if (empty($rows)
        || !isset($session['current_lat'], $session['current_lng'])
        || $session['current_lat'] === null
        || $session['current_lng'] === null) {
        return 0;
    }

    $latitude = (float) $session['current_lat'];
    $longitude = (float) $session['current_lng'];
    $busId = isset($session['bus_id']) ? (string) $session['bus_id'] : '';
    $synced = 0;

    foreach ($rows as $row) {
        $inspectorId = isset($row['inspector_id']) ? (string) $row['inspector_id'] : '';

        if ($inspectorId === '') {
            continue;
        }

        if (simulation_write_inspector_location($conn, $inspectorId, $busId, $latitude, $longitude)) {
            simulation_inspector_state_write($conn, $inspectorId, array(
                'latitude' => $latitude,
                'longitude' => $longitude,
            ));
            $synced++;
        }
    }

    return $synced;
}

/**
 * End the onboard link for every inspector on the given session, keeping the
 * last coordinates. Used when a session is disabled or reset.
 */
function simulation_inspector_clear_session($conn, $session)
{
    $rows = simulation_onboard_inspectors_for_session($conn, $session);
    $cleared = 0;

    foreach ($rows as $row) {
        $inspectorId = isset($row['inspector_id']) ? (string) $row['inspector_id'] : '';

        if ($inspectorId !== '' && simulation_inspector_set_offboard($conn, $inspectorId)) {
            $cleared++;
        }
    }

    return $cleared;
}

/**
 * The additive `inspector` object served by the shared read, or null.
 *
 * Coordinates come from the session so every client agrees with the bus; the
 * inspector's own stored coordinates are only the fallback. `simulation: true`
 * is what distinguishes it from a real device GPS reading.
 */
function simulation_inspector_payload_for_session($conn, $session, $row = null)
{
    if ($row === null) {
        $row = simulation_inspector_state_for_session($conn, $session);
    }

    if (!$row) {
        return null;
    }

    $latitude = null;
    $longitude = null;

    if (isset($session['current_lat']) && $session['current_lat'] !== null) {
        $latitude = (float) $session['current_lat'];
        $longitude = (float) $session['current_lng'];
    } elseif (isset($row['latitude']) && $row['latitude'] !== null) {
        $latitude = (float) $row['latitude'];
        $longitude = (float) $row['longitude'];
    }

    return array(
        'inspector_id' => $row['inspector_id'],
        'status' => isset($row['status']) ? $row['status'] : null,
        'onboard_bus_id' => isset($row['onboard_bus_id']) ? $row['onboard_bus_id'] : null,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'simulation' => true,
    );
}

/**
 * Whether a simulation currently controls this inspector, for the upload guard.
 *
 * Returns active=false when there is no onboard link or no usable coordinate.
 * Read-only; the name deliberately does not contain `simulation_override_for`,
 * which the existing contract test fixes on the driver path.
 */
function simulation_inspector_override_for($conn, $inspector_id)
{
    $inspector_id = trim((string) $inspector_id);

    if ($inspector_id === '' || !simulation_inspector_state_ready($conn)) {
        return array('active' => false);
    }

    try {
        $stmt = $conn->prepare("
            SELECT *
            FROM simulation_inspector_state
            WHERE inspector_id = ? AND onboard_bus_id IS NOT NULL
            LIMIT 1
        ");
        $stmt->bind_param("s", $inspector_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (\Throwable $error) {
        return array('active' => false);
    }

    if (!$row || empty($row['onboard_bus_id'])) {
        return array('active' => false);
    }

    $session = simulation_find_active_session($conn, (string) $row['onboard_bus_id']);
    $latitude = isset($row['latitude']) && $row['latitude'] !== null ? (float) $row['latitude'] : null;
    $longitude = isset($row['longitude']) && $row['longitude'] !== null ? (float) $row['longitude'] : null;

    if ($session && $session['current_lat'] !== null && $session['current_lng'] !== null) {
        $latitude = (float) $session['current_lat'];
        $longitude = (float) $session['current_lng'];
    }

    if ($latitude === null || $longitude === null) {
        return array('active' => false);
    }

    return array(
        'active' => true,
        'simulation' => true,
        'inspector_id' => $row['inspector_id'],
        'status' => isset($row['status']) ? $row['status'] : null,
        'bus_id' => $row['onboard_bus_id'],
        'session_id' => isset($row['onboard_session_id']) ? (int) $row['onboard_session_id'] : null,
        'latitude' => $latitude,
        'longitude' => $longitude,
    );
}

/* -------------------------------------------------------------------------
 * Session persistence
 * ------------------------------------------------------------------------- */

function simulation_session_active_statuses()
{
    return array('idle', 'running', 'paused', 'completed');
}

function simulation_find_active_session($conn, $bus_id = null, $driver_id = null)
{
    if (!simulation_tables_ready($conn)) {
        return null;
    }

    if ($bus_id !== null && $bus_id !== '') {
        $stmt = $conn->prepare("
            SELECT *
            FROM simulation_sessions
            WHERE bus_id = ? AND status IN ('idle','running','paused','completed')
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("s", $bus_id);
    } elseif ($driver_id !== null && $driver_id !== '') {
        $stmt = $conn->prepare("
            SELECT *
            FROM simulation_sessions
            WHERE driver_id = ? AND status IN ('idle','running','paused','completed')
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("s", $driver_id);
    } else {
        return null;
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function simulation_find_session($conn, $session_id)
{
    if (!simulation_tables_ready($conn)) {
        return null;
    }

    $stmt = $conn->prepare("SELECT * FROM simulation_sessions WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

/**
 * The most recent session that is still in the simulation (not disabled).
 */
function simulation_latest_active_session($conn)
{
    if (!simulation_tables_ready($conn)) {
        return null;
    }

    $result = $conn->query("
        SELECT *
        FROM simulation_sessions
        WHERE status IN ('idle','running','paused','completed')
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$result) {
        return null;
    }

    $row = $result->fetch_assoc();
    $result->free();

    return $row ?: null;
}

/**
 * Create an idle session for a validated trip. Returns the stored row.
 */
function simulation_create_session($conn, $bus_id, $driver_id, $trip_id, $route_name, $direction, $created_by, $passenger_demand = 'normal')
{
    $waypoints = simulation_direction_waypoints($conn, $direction);

    if (count($waypoints) < 2) {
        return null;
    }

    $now = simulation_now();
    $first = $waypoints[0];
    $status = 'idle';
    $pointIndex = 0;
    $progress = 0.0;
    $speed = (float) ($first['speed_kmh'] ?? 0);
    $withPassengers = simulation_passenger_schema_ready($conn);

    if ($withPassengers) {
        $demand = simulation_normalize_demand($passenger_demand);
        $capacity = simulation_capacity_for_bus($conn, $bus_id);
        $stmt = $conn->prepare("
            INSERT INTO simulation_sessions (
                bus_id, driver_id, trip_id, route_name, route_direction, status,
                current_lat, current_lng, current_point_index, progress_pct,
                speed_kmh, started_at, paused_ms, last_tick_at, created_by, created_at, updated_at,
                passenger_demand, passenger_capacity
            )
            VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,0,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "ssisssddiddsssssi",
            $bus_id,
            $driver_id,
            $trip_id,
            $route_name,
            $direction,
            $status,
            $first['lat'],
            $first['lng'],
            $pointIndex,
            $progress,
            $speed,
            $now,
            $created_by,
            $now,
            $now,
            $demand,
            $capacity
        );
        $stmt->execute();
        $sessionId = (int) $conn->insert_id;
        $stmt->close();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO simulation_sessions (
                bus_id, driver_id, trip_id, route_name, route_direction, status,
                current_lat, current_lng, current_point_index, progress_pct,
                speed_kmh, started_at, paused_ms, last_tick_at, created_by, created_at, updated_at
            )
            VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,0,?,?,?,?)
        ");
        $stmt->bind_param(
            "ssisssddiddssss",
            $bus_id,
            $driver_id,
            $trip_id,
            $route_name,
            $direction,
            $status,
            $first['lat'],
            $first['lng'],
            $pointIndex,
            $progress,
            $speed,
            $now,
            $created_by,
            $now,
            $now
        );
        $stmt->execute();
        $sessionId = (int) $conn->insert_id;
        $stmt->close();
    }

    return simulation_find_session($conn, $sessionId);
}

/**
 * Delete the session's stop events (used by start/reset).
 */
function simulation_clear_stop_events($conn, $session_id)
{
    if (!simulation_tables_ready($conn)) {
        return;
    }

    $stmt = $conn->prepare("DELETE FROM simulation_stop_events WHERE session_id = ?");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $stmt->close();
}

function simulation_arrived_sequences($conn, $session_id)
{
    if (!simulation_tables_ready($conn)) {
        return array();
    }

    $stmt = $conn->prepare("SELECT stop_sequence FROM simulation_stop_events WHERE session_id = ? ORDER BY stop_sequence ASC");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sequences = array();

    while ($row = $result->fetch_assoc()) {
        $sequences[] = (int) $row['stop_sequence'];
    }

    $stmt->close();

    return $sequences;
}

function simulation_update_progress($conn, $session_id, $position, $status, $lastTickAt)
{
    $stmt = $conn->prepare("
        UPDATE simulation_sessions
        SET current_lat = ?, current_lng = ?, current_point_index = ?,
            progress_pct = ?, status = ?, speed_kmh = ?, last_tick_at = ?, updated_at = ?
        WHERE id = ?
    ");
    $speed = (float) ($position['speed_kmh'] ?? 0);
    $now = simulation_now();
    $stmt->bind_param(
        "ddidsdssi",
        $position['lat'],
        $position['lng'],
        $position['point_index'],
        $position['progress_pct'],
        $status,
        $speed,
        $lastTickAt,
        $now,
        $session_id
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * The session with its live stop context, as returned to every client.
 */
function simulation_session_payload($conn, $session)
{
    if (!$session) {
        return null;
    }

    $direction = $session['route_direction'];
    $stops = simulation_load_stops($conn, $direction);
    $arrived = simulation_arrived_sequences($conn, (int) $session['id']);
    $lat = $session['current_lat'] !== null ? (float) $session['current_lat'] : null;
    $lng = $session['current_lng'] !== null ? (float) $session['current_lng'] : null;

    $current = simulation_current_stop($stops, $lat, $lng);
    $next = simulation_next_stop($stops, $arrived);
    $passengers = simulation_passenger_summary($conn, $session, $stops);

    return array(
        'active' => in_array($session['status'], simulation_session_active_statuses(), true),
        'simulation' => true,
        'engine_revision' => simulation_engine_revision(),
        'session_id' => (int) $session['id'],
        'bus_id' => $session['bus_id'],
        'driver_id' => $session['driver_id'],
        'trip_id' => $session['trip_id'] !== null ? (int) $session['trip_id'] : null,
        'route_name' => $session['route_name'],
        'route_direction' => $direction,
        'status' => $session['status'],
        'latitude' => $lat,
        'longitude' => $lng,
        'speed_kmh' => $session['speed_kmh'] !== null ? (float) $session['speed_kmh'] : null,
        'speed_preset' => isset($session['speed_preset'])
            ? simulation_normalize_speed_preset($session['speed_preset'])
            : 'normal',
        'speed_multiplier' => simulation_session_speed_multiplier($session),
        'progress_pct' => (float) $session['progress_pct'],
        'current_point_index' => (int) $session['current_point_index'],
        'current_stop' => $current ? $current['stop_name'] : null,
        'next_stop' => $next ? $next['stop_name'] : null,
        'arrived_stops' => count($arrived),
        'total_stops' => count($stops),
        'passenger_demand' => $passengers['demand'],
        'passengers' => $passengers,
        'started_at' => $session['started_at'],
        'last_tick_at' => $session['last_tick_at'],
        'updated_at' => $session['updated_at'],
    );
}

/* -------------------------------------------------------------------------
 * Control actions
 * ------------------------------------------------------------------------- */

function simulation_result($success, $message, $data = null)
{
    return array(
        'success' => $success,
        'message' => $message,
        'data' => $data,
    );
}

/**
 * Enable simulation for a bus + active trip. Idempotent when a session already
 * exists for the bus.
 */
function simulation_enable($conn, $input, $created_by = 'simulation.php')
{
    $busId = trim((string) ($input['bus_id'] ?? ''));
    $tripId = isset($input['trip_id']) && $input['trip_id'] !== '' ? (int) $input['trip_id'] : 0;

    if ($busId === '' || $tripId <= 0) {
        return simulation_result(false, 'bus_id and trip_id are required');
    }

    $existing = simulation_find_active_session($conn, $busId);

    if ($existing) {
        return simulation_result(true, 'Simulation already enabled for this bus', simulation_session_payload($conn, $existing));
    }

    $stmt = $conn->prepare("
        SELECT id, bus_id, driver_id, route_name, status, trip_date
        FROM bus_trips
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $tripId);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$trip) {
        return simulation_result(false, 'Active trip not found');
    }

    if (!in_array($trip['status'], array('Scheduled', 'Departed'), true)) {
        return simulation_result(false, 'Trip is not active (status: ' . $trip['status'] . ')');
    }

    if (trim((string) $trip['bus_id']) !== $busId) {
        return simulation_result(false, 'Selected trip does not belong to the selected bus');
    }

    $direction = simulation_resolve_direction($trip['route_name']);

    if ($direction === null) {
        return simulation_result(false, 'Route not recognized for simulation: ' . (string) $trip['route_name']);
    }

    if (count(simulation_direction_waypoints($conn, $direction)) < 2) {
        return simulation_result(false, 'No route path available for: ' . (string) $trip['route_name']);
    }

    $passengerDemand = simulation_normalize_demand($input['passenger_demand'] ?? 'normal');

    $session = simulation_create_session(
        $conn,
        $busId,
        $trip['driver_id'] !== null ? (string) $trip['driver_id'] : null,
        $tripId,
        $trip['route_name'],
        $direction,
        $created_by,
        $passengerDemand
    );

    if (!$session) {
        return simulation_result(false, 'Could not create the simulation session');
    }

    /* Waiting passengers are generated once per session from route_stops and
       fare_pricing; start/reset rebuild the same deterministic journeys. */
    simulation_generate_passengers($conn, $session);

    /* Optional initial speed tier for the new session (the console sends the
       selected tier with Enable; blank/absent keeps the normal tier). */
    if (isset($input['speed_preset']) && simulation_table_has_column($conn, 'simulation_sessions', 'speed_preset')) {
        $preset = simulation_normalize_speed_preset($input['speed_preset']);
        $multiplier = simulation_normalize_speed_multiplier($input['speed_multiplier'] ?? 1.0);
        $speedNow = simulation_now();
        $sessionId = (int) $session['id'];

        if (simulation_table_has_column($conn, 'simulation_sessions', 'speed_multiplier')) {
            $stmt = $conn->prepare("
                UPDATE simulation_sessions
                SET speed_preset = ?, speed_multiplier = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sdsi", $preset, $multiplier, $speedNow, $sessionId);
        } else {
            $stmt = $conn->prepare("
                UPDATE simulation_sessions
                SET speed_preset = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssi", $preset, $speedNow, $sessionId);
        }

        $stmt->execute();
        $stmt->close();

        $session = simulation_find_session($conn, $sessionId);
    }

    return simulation_result(true, 'Simulation enabled', simulation_session_payload($conn, $session));
}

/**
 * Apply a movement control. Returns ['success', 'message', 'data'].
 */
function simulation_control($conn, $action, $input = array(), $created_by = 'simulation.php')
{
    $action = strtolower(trim((string) $action));

    if (!simulation_tables_ready($conn)) {
        return simulation_result(false, 'Simulation tables are not installed. Run mobile/simulation/scripts/simulation_tables.sql.');
    }

    if ($action === 'enable') {
        return simulation_enable($conn, $input, $created_by);
    }

    $sessionId = isset($input['session_id']) ? (int) $input['session_id'] : 0;
    $busId = trim((string) ($input['bus_id'] ?? ''));

    if ($sessionId > 0) {
        $session = simulation_find_session($conn, $sessionId);
    } elseif ($busId !== '') {
        $session = simulation_find_active_session($conn, $busId);
    } else {
        $session = null;
    }

    if (!$session) {
        return simulation_result(false, 'No active simulation session');
    }

    if ($session['status'] === 'disabled') {
        return simulation_result(false, 'Simulation session is disabled');
    }

    $waypoints = simulation_direction_waypoints($conn, $session['route_direction']);

    if (count($waypoints) < 2) {
        return simulation_result(false, 'No coordinate plan for direction: ' . $session['route_direction']);
    }

    $config = simulation_config();
    $plan = simulation_build_segments(
        $waypoints,
        isset($config['default_speed_kmh']) ? (float) $config['default_speed_kmh'] : 40.0,
        simulation_session_speed_multiplier($session)
    );
    $sessionId = (int) $session['id'];
    $now = simulation_now();
    $nowTs = simulation_now_ts();

    switch ($action) {
        case 'disable':
            $stmt = $conn->prepare("UPDATE simulation_sessions SET status = 'disabled', last_tick_at = ?, updated_at = ? WHERE id = ?");
            $stmt->bind_param("ssi", $now, $now, $sessionId);
            $stmt->execute();
            $stmt->close();

            simulation_inspector_clear_session($conn, array('id' => $sessionId, 'bus_id' => $session['bus_id']));

            return simulation_result(true, 'Simulation disabled', simulation_session_payload($conn, simulation_find_session($conn, $sessionId)));

        case 'start':
            simulation_clear_stop_events($conn, $sessionId);
            simulation_clear_passengers($conn, $session);
            simulation_generate_passengers($conn, $session);
            $first = $waypoints[0];
            $position = array(
                'lat' => $first['lat'],
                'lng' => $first['lng'],
                'point_index' => 0,
                'progress_pct' => 0.0,
                'speed_kmh' => isset($first['speed_kmh']) ? $first['speed_kmh'] : null,
            );

            $stmt = $conn->prepare("
                UPDATE simulation_sessions
                SET status = 'running', started_at = ?, paused_ms = 0,
                    current_lat = ?, current_lng = ?, current_point_index = 0,
                    progress_pct = 0, last_tick_at = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "sddssi",
                $now,
                $position['lat'],
                $position['lng'],
                $now,
                $now,
                $sessionId
            );
            $stmt->execute();
            $stmt->close();

            $started = simulation_find_session($conn, $sessionId);
            simulation_sync_onboard_inspectors($conn, $started);

            return simulation_result(true, 'Simulation started', simulation_session_payload($conn, $started));

        case 'pause':
            if ($session['status'] !== 'running') {
                return simulation_result(false, 'Simulation is not running');
            }

            $elapsed = simulation_elapsed_ms(
                $session['status'],
                $session['started_at'] !== null ? strtotime($session['started_at']) : null,
                (int) $session['paused_ms'],
                $nowTs
            );

            $stmt = $conn->prepare("
                UPDATE simulation_sessions
                SET status = 'paused', paused_ms = ?, last_tick_at = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->bind_param("issi", $elapsed, $now, $now, $sessionId);
            $stmt->execute();
            $stmt->close();

            return simulation_result(true, 'Simulation paused', simulation_session_payload($conn, simulation_find_session($conn, $sessionId)));

        case 'resume':
            if ($session['status'] !== 'paused') {
                return simulation_result(false, 'Simulation is not paused');
            }

            $stmt = $conn->prepare("
                UPDATE simulation_sessions
                SET status = 'running', started_at = ?, last_tick_at = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssi", $now, $now, $now, $sessionId);
            $stmt->execute();
            $stmt->close();

            $resumed = simulation_find_session($conn, $sessionId);
            simulation_sync_onboard_inspectors($conn, $resumed);

            return simulation_result(true, 'Simulation resumed', simulation_session_payload($conn, $resumed));

        case 'reset':
            simulation_clear_stop_events($conn, $sessionId);
            simulation_clear_passengers($conn, $session);
            simulation_generate_passengers($conn, $session);
            $first = $waypoints[0];
            $stmt = $conn->prepare("
                UPDATE simulation_sessions
                SET status = 'idle', started_at = NULL, paused_ms = 0,
                    current_lat = ?, current_lng = ?, current_point_index = 0,
                    progress_pct = 0, last_tick_at = ?, updated_at = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "ddssi",
                $first['lat'],
                $first['lng'],
                $now,
                $now,
                $sessionId
            );
            $stmt->execute();
            $stmt->close();

            simulation_inspector_clear_session($conn, array('id' => $sessionId, 'bus_id' => $session['bus_id']));

            return simulation_result(true, 'Simulation reset', simulation_session_payload($conn, simulation_find_session($conn, $sessionId)));

        case 'speed':
            if (!simulation_table_has_column($conn, 'simulation_sessions', 'speed_preset')) {
                return simulation_result(false, 'This database has no speed_preset column on simulation_sessions - run mobile/simulation/scripts/simulation_tables.sql.');
            }

            $preset = simulation_normalize_speed_preset($input['speed_preset'] ?? 'normal');
            $multiplier = simulation_normalize_speed_multiplier($input['speed_multiplier'] ?? 1.0);
            $oldMultiplier = simulation_session_speed_multiplier($session);

            /* A speed change must not move the bus: rebase the session clock so
               the scaled elapsed keeps the current position, then movement
               continues at the new speed from here. */
            $newStartedAt = $session['started_at'];
            $newPausedMs = (int) $session['paused_ms'];

            if (
                abs($multiplier - $oldMultiplier) > 0.0001
                && in_array($session['status'], array('running', 'paused'), true)
            ) {
                $elapsedOld = simulation_elapsed_ms(
                    $session['status'],
                    $session['started_at'] !== null ? strtotime($session['started_at']) : null,
                    (int) $session['paused_ms'],
                    $nowTs
                );
                $scale = $oldMultiplier > 0 ? ($oldMultiplier / $multiplier) : 1.0;
                $newPausedMs = (int) round(max(0.0, $elapsedOld * $scale));

                if ($session['status'] === 'running') {
                    $newStartedAt = $now;
                }
            }

            if (simulation_table_has_column($conn, 'simulation_sessions', 'speed_multiplier')) {
                $stmt = $conn->prepare("
                    UPDATE simulation_sessions
                    SET speed_preset = ?, speed_multiplier = ?, started_at = ?, paused_ms = ?, updated_at = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("sdsisi", $preset, $multiplier, $newStartedAt, $newPausedMs, $now, $sessionId);
            } else {
                $stmt = $conn->prepare("
                    UPDATE simulation_sessions
                    SET speed_preset = ?, started_at = ?, paused_ms = ?, updated_at = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("ssisi", $preset, $newStartedAt, $newPausedMs, $now, $sessionId);
            }

            $stmt->execute();
            $stmt->close();

            $message = $preset === 'custom'
                ? 'Simulation speed set to custom x' . rtrim(rtrim(number_format($multiplier, 2, '.', ''), '0'), '.')
                : 'Simulation speed set to ' . $preset;

            return simulation_result(true, $message, simulation_session_payload($conn, simulation_find_session($conn, $sessionId)));

        default:
            return simulation_result(false, 'Unknown action: ' . $action);
    }
}

/**
 * Advance (or re-read) the session position and persist arrivals.
 */
function simulation_tick($conn, $session)
{
    if (!$session || $session['status'] === 'disabled') {
        return simulation_result(false, 'No active simulation session');
    }

    $waypoints = simulation_direction_waypoints($conn, $session['route_direction']);

    if (count($waypoints) < 2) {
        return simulation_result(false, 'No coordinate plan for direction: ' . $session['route_direction']);
    }

    $config = simulation_config();
    $plan = simulation_build_segments(
        $waypoints,
        isset($config['default_speed_kmh']) ? (float) $config['default_speed_kmh'] : 40.0,
        simulation_session_speed_multiplier($session)
    );
    $now = simulation_now();
    $nowTs = simulation_now_ts();

    $elapsed = simulation_elapsed_ms(
        $session['status'],
        $session['started_at'] !== null ? strtotime($session['started_at']) : null,
        (int) $session['paused_ms'],
        $nowTs
    );

    $position = simulation_position_at($plan, $waypoints, $elapsed);
    $status = $session['status'];

    if ($status === 'running' && $position['completed']) {
        $status = 'completed';
    }

    $speed = $status === 'running' && isset($plan['segments'][$position['point_index']])
        ? (float) $plan['segments'][$position['point_index']]['speed_kmh']
        : 0.0;

    $position['speed_kmh'] = $speed;
    simulation_update_progress($conn, (int) $session['id'], $position, $status, $now);

    $ticked = simulation_find_session($conn, (int) $session['id']);
    simulation_sync_onboard_inspectors($conn, $ticked);

    if ($status === 'running' || $status === 'completed') {
        simulation_record_arrivals($conn, $session, $position['lat'], $position['lng'], $now);
    }

    return simulation_result(true, 'Simulation tick', simulation_session_payload($conn, $ticked));
}

/**
 * Insert one event per designated stop whose radius the position has entered.
 */
function simulation_record_arrivals($conn, $session, $lat, $lng, $now)
{
    if ($lat === null || $lng === null || !simulation_tables_ready($conn)) {
        return;
    }

    $sessionId = (int) $session['id'];
    $stops = simulation_load_stops($conn, $session['route_direction']);
    $arrived = simulation_arrived_sequences($conn, $sessionId);
    $arrivedLookup = array();
    foreach ($arrived as $sequence) {
        $arrivedLookup[(string) $sequence] = true;
    }

    $insert = $conn->prepare("
        INSERT IGNORE INTO simulation_stop_events
            (session_id, stop_name, stop_sequence, stop_lat, stop_lng, arrived_at, created_at)
        VALUES (?,?,?,?,?,?,?)
    ");

    foreach ($stops as $stop) {
        if (isset($arrivedLookup[(string) $stop['sequence_order']])) {
            continue;
        }

        $distance = simulation_haversine_m($lat, $lng, (float) $stop['latitude'], (float) $stop['longitude']);

        if ($distance <= (float) $stop['radius_m']) {
            $insert->bind_param(
                "isiddss",
                $sessionId,
                $stop['stop_name'],
                $stop['sequence_order'],
                $stop['latitude'],
                $stop['longitude'],
                $now,
                $now
            );
            $insert->execute();

            /* A newly recorded stop boards/alights its passengers once; the
               INSERT IGNORE above keeps repeated ticks from reprocessing. */
            if ($conn->affected_rows > 0) {
                simulation_process_stop_passengers($conn, $session, $stop);
            }
        }
    }

    $insert->close();
}

/* -------------------------------------------------------------------------
 * Read-path override
 * ------------------------------------------------------------------------- */

/**
 * The override payload for a session row, or active=false when unusable.
 *
 * Both the mysqli and PDO readers share this so every client sees the exact
 * same fields for the same session.
 */
function simulation_session_override_payload($session)
{
    $inactive = array('active' => false);

    if (!$session || $session['current_lat'] === null || $session['current_lng'] === null) {
        return $inactive;
    }

    return array(
        'active' => true,
        'simulation' => true,
        'session_id' => (int) $session['id'],
        'bus_id' => $session['bus_id'],
        'driver_id' => $session['driver_id'],
        'trip_id' => $session['trip_id'] !== null ? (int) $session['trip_id'] : null,
        'latitude' => (float) $session['current_lat'],
        'longitude' => (float) $session['current_lng'],
        'speed_kmh' => $session['speed_kmh'] !== null ? (float) $session['speed_kmh'] : 0.0,
        'status' => $session['status'],
        'progress_pct' => (float) $session['progress_pct'],
        'updated_at' => $session['last_tick_at'] !== null ? $session['last_tick_at'] : $session['updated_at'],
    );
}

/**
 * The simulated position for a bus/driver when a session is active.
 *
 * Read-only: never writes, never returns a partially valid coordinate. The
 * returned array carries active=false when there is nothing to override.
 */
function simulation_override_for($conn, $bus_id = null, $driver_id = null)
{
    if (!simulation_tables_ready($conn)) {
        return array('active' => false);
    }

    $session = null;

    if ($bus_id !== null && $bus_id !== '') {
        $session = simulation_find_active_session($conn, $bus_id);
    }

    if (!$session && $driver_id !== null && $driver_id !== '') {
        $session = simulation_find_active_session($conn, null, $driver_id);
    }

    return simulation_session_override_payload($session);
}

/**
 * PDO variant of simulation_override_for for endpoints that do not share the
 * mysqli handle (the admin Trip Dashboard). Same active statuses, same
 * payload; any schema/connection problem degrades to active=false.
 */
function simulation_override_for_pdo($pdo, $bus_id = null, $driver_id = null)
{
    $inactive = array('active' => false);

    if (!($pdo instanceof PDO)) {
        return $inactive;
    }

    $session = null;

    try {
        if ($bus_id !== null && $bus_id !== '') {
            $stmt = $pdo->prepare("
                SELECT *
                FROM simulation_sessions
                WHERE bus_id = ? AND status IN ('idle','running','paused','completed')
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute(array((string) $bus_id));
            $session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$session && $driver_id !== null && $driver_id !== '') {
            $stmt = $pdo->prepare("
                SELECT *
                FROM simulation_sessions
                WHERE driver_id = ? AND status IN ('idle','running','paused','completed')
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute(array((string) $driver_id));
            $session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (\Throwable $error) {
        return $inactive;
    }

    return simulation_session_override_payload($session);
}

/**
 * Whether a real GPS upload for this driver/bus must be refused.
 */
function simulation_upload_blocked($conn, $driver_id, $bus_id = null)
{
    $override = simulation_override_for($conn, $bus_id, $driver_id);

    return $override['active'] === true;
}

/**
 * List buses that have a Scheduled/Departed trip today, for the selector.
 *
 * Optional display columns (`departure_time`, `trip_number`) are selected only
 * when the database actually has them, so an older schema still yields the
 * selector instead of a fatal query.
 */
function simulation_options($conn)
{
    $today = date('Y-m-d');
    $columns = "bt.id AS trip_id, bt.bus_id, bt.driver_id, bt.route_name, bt.status, bt.trip_date";

    if (simulation_table_has_column($conn, 'bus_trips', 'departure_time')) {
        $columns .= ", bt.departure_time";
    }

    if (simulation_table_has_column($conn, 'bus_trips', 'trip_number')) {
        $columns .= ", bt.trip_number";
    }

    $stmt = $conn->prepare("
        SELECT $columns, d.full_name AS driver_name
        FROM bus_trips bt
        LEFT JOIN drivers d ON d.driver_id = bt.driver_id
        WHERE bt.status IN ('Scheduled','Departed') AND bt.trip_date = ?
        ORDER BY bt.bus_id ASC, bt.id ASC
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $trips = array();

    while ($row = $result->fetch_assoc()) {
        $direction = simulation_resolve_direction($row['route_name']);
        $active = simulation_find_active_session($conn, $row['bus_id']);

        $row['route_direction'] = $direction;
        $row['simulation'] = $active ? simulation_session_payload($conn, $active) : null;
        $trips[] = $row;
    }

    $stmt->close();

    return $trips;
}

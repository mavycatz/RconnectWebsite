<?php

/**
 * Road geometry endpoint (driver app) - repo mirror.
 *
 * Mirrors the deployed `mobile/driver/getRoutePath.php`. Returns the street
 * geometry for a trip route as `{"success": true, "route_points": [...]}` so
 * the driver map can draw the route along the road instead of stop chords.
 *
 * Two data realities shape the implementation:
 *   - Trip route names come in aliases (`Cabanatuan-Rizal` vs the stored
 *     `Cabanatuan to Rizal Route`), so the route is resolved with the same
 *     normalized fallback pattern as `get_route_stops.php`.
 *   - `route_points` for one route can hold two sequences that share
 *     `point_order` values (a stray coarse run plus the current detail run),
 *     so the longest strictly increasing run in insertion (`id`) order is
 *     selected. Nothing is written back.
 *
 * The pure helpers below are executable without a database: a test defines
 * `ROUTE_PATH_LIB_ONLY` before including this file so the request handling is
 * skipped and the helpers can be asserted directly.
 *
 *     php tests/get_route_path_contract_test.php
 */

function route_path_haversine_m($lat1, $lng1, $lat2, $lng2)
{
    $earth = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2)
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);

    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Lowercase, alphanumeric-only form used for alias comparison.
 */
function route_path_normalize_name($value)
{
    return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $value));
}

/**
 * Travel direction implied by a normalized route name, or null when the name
 * mentions no known terminal. Terminal-only names follow the convention used
 * by `actual_destination.php` and the shared simulation engine.
 */
function route_path_direction_of($normalized)
{
    $cabanatuan = strpos($normalized, 'cabanatuan');
    $rizal = strpos($normalized, 'rizal');

    if ($cabanatuan === false && $rizal === false) {
        return null;
    }

    if ($cabanatuan === false) {
        return 'rizalcabanatuan';
    }

    if ($rizal === false) {
        return 'cabanatuanrizal';
    }

    return $cabanatuan < $rizal ? 'cabanatuanrizal' : 'rizalcabanatuan';
}

/**
 * Resolve a requested route name against the stored route names.
 *
 * Order: exact match, then direction-token match (alias-safe and never picks
 * the opposite direction), then the normalized `similar_text` fallback that
 * `get_route_stops.php` uses. Returns the best candidate name or null.
 */
function route_path_best_route_name($requested, $candidates)
{
    $requested = trim((string) $requested);

    if ($requested === '') {
        return null;
    }

    foreach ($candidates as $candidate) {
        if ((string) $candidate === $requested) {
            return (string) $candidate;
        }
    }

    $target = route_path_normalize_name($requested);

    if ($target === '') {
        return null;
    }

    $targetDirection = route_path_direction_of($target);

    if ($targetDirection !== null) {
        foreach ($candidates as $candidate) {
            if (route_path_direction_of(route_path_normalize_name($candidate)) === $targetDirection) {
                return (string) $candidate;
            }
        }
    }

    $best = null;
    $bestScore = 0;

    foreach ($candidates as $candidate) {
        $score = similar_text($target, route_path_normalize_name($candidate));

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = (string) $candidate;
        }
    }

    return $best;
}

/**
 * Whether a coordinate is a real map point (finite, in range, not 0,0).
 */
function route_path_is_usable_point($latitude, $longitude)
{
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return false;
    }

    $latitude = (float) $latitude;
    $longitude = (float) $longitude;

    if (!is_finite($latitude) || !is_finite($longitude)) {
        return false;
    }

    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        return false;
    }

    return !($latitude == 0.0 && $longitude == 0.0);
}

/**
 * Select the route's stored street geometry from raw `route_points` rows.
 *
 * Rows are read in insertion (`id`) order and split whenever `point_order`
 * fails to increase, which separates the overlapping sequences described
 * above. The longest run wins; on a length tie the later-stored run (higher
 * first id) wins. Unusable coordinates are dropped and consecutive points
 * closer than 1 m collapse. Returns [['latitude' => float, 'longitude' =>
 * float], ...] in travel order.
 */
function route_path_select_sequence($rows)
{
    $usable = array();

    foreach ($rows as $row) {
        if (!isset($row['point_order'], $row['latitude'], $row['longitude'])) {
            continue;
        }

        if (!route_path_is_usable_point($row['latitude'], $row['longitude'])) {
            continue;
        }

        $usable[] = array(
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'point_order' => (int) $row['point_order'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
        );
    }

    usort($usable, function ($a, $b) {
        return $a['id'] - $b['id'];
    });

    $groups = array();
    $current = array();
    $previousOrder = null;

    foreach ($usable as $row) {
        if ($previousOrder !== null && $row['point_order'] <= $previousOrder) {
            $groups[] = $current;
            $current = array();
        }

        $current[] = $row;
        $previousOrder = $row['point_order'];
    }

    if (!empty($current)) {
        $groups[] = $current;
    }

    $best = array();

    foreach ($groups as $group) {
        if (count($group) > count($best)) {
            $best = $group;
            continue;
        }

        if (count($group) === count($best) && count($best) > 0 && $group[0]['id'] > $best[0]['id']) {
            $best = $group;
        }
    }

    $points = array();

    foreach ($best as $row) {
        $last = end($points);

        if ($last !== false
            && route_path_haversine_m($last['latitude'], $last['longitude'], $row['latitude'], $row['longitude']) < 1.0) {
            continue;
        }

        $points[] = array(
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
        );
    }

    return $points;
}

/**
 * Orient a selected sequence to the requested travel direction.
 *
 * Both travel directions share one corridor, and the live `routes` names are
 * not reliable, so the stored run can come back opposite to the trip. The
 * route's first stop (from `route_stops`) is the travel-order reference: when
 * the sequence's last point is nearer to it than the first point, the points
 * are reversed. Returns the input unchanged when it cannot decide.
 */
function route_path_orient_sequence($points, $startLat, $startLng)
{
    $count = count($points);

    if ($count < 2 || !route_path_is_usable_point($startLat, $startLng)) {
        return $points;
    }

    $first = $points[0];
    $last = $points[$count - 1];
    $firstDistance = route_path_haversine_m($startLat, $startLng, $first['latitude'], $first['longitude']);
    $lastDistance = route_path_haversine_m($startLat, $startLng, $last['latitude'], $last['longitude']);

    return $lastDistance < $firstDistance ? array_reverse($points) : $points;
}

if (defined('ROUTE_PATH_LIB_ONLY')) {
    return;
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "../config/database.php";

$db = new Database();
$conn = $db->connect();

if (!$conn) {
    echo json_encode(array(
        "success" => false,
        "message" => "Database connection failed",
    ));
    exit;
}

$route_name = isset($_GET['route_name']) ? trim($_GET['route_name']) : '';

if ($route_name === '') {
    echo json_encode(array(
        "success" => false,
        "message" => "route_name required",
    ));
    exit;
}

/* Stored route names and ids; the pure resolver picks the best candidate. */
$route_ids = array();
$routeResult = $conn->query("SELECT id, route_name FROM routes ORDER BY id ASC");

if ($routeResult) {
    while ($row = $routeResult->fetch_assoc()) {
        $name = (string) $row['route_name'];

        if (!isset($route_ids[$name])) {
            $route_ids[$name] = (int) $row['id'];
        }
    }

    $routeResult->free();
}

$resolvedName = route_path_best_route_name($route_name, array_keys($route_ids));

if ($resolvedName === null) {
    echo json_encode(array(
        "success" => false,
        "message" => "Route not found",
    ));
    exit;
}

$route_id = $route_ids[$resolvedName];

$stmt = $conn->prepare("
    SELECT id, point_order, latitude, longitude
    FROM route_points
    WHERE route_id = ?
    ORDER BY id ASC
");

if (!$stmt) {
    echo json_encode(array(
        "success" => false,
        "message" => "Route points query prepare failed",
    ));
    exit;
}

$stmt->bind_param("i", $route_id);
$stmt->execute();
$resultPoints = $stmt->get_result();
$rows = array();

while ($row = $resultPoints->fetch_assoc()) {
    $rows[] = $row;
}

$stmt->close();

$points = route_path_select_sequence($rows);

/*
TRAVEL-ORDER ORIENTATION

Both directions share one road corridor and the live `routes` names may be
unusable, so the selected run can be stored opposite to the trip direction.
The requested route's first `route_stops` point decides the travel order; the
stop names are resolved with the same alias-tolerant fallback as the route
name above.
*/

$stopsNames = array();
$stopsResult = $conn->query("SELECT DISTINCT route_name FROM route_stops");

if ($stopsResult) {
    while ($row = $stopsResult->fetch_assoc()) {
        $stopsNames[] = (string) $row['route_name'];
    }

    $stopsResult->free();
}

$resolvedStopsName = route_path_best_route_name($route_name, $stopsNames);

if ($resolvedStopsName !== null) {
    $firstStopStmt = $conn->prepare("
        SELECT latitude, longitude
        FROM route_stops
        WHERE route_name = ?
        ORDER BY sequence_order ASC
        LIMIT 1
    ");

    if ($firstStopStmt) {
        $firstStopStmt->bind_param("s", $resolvedStopsName);
        $firstStopStmt->execute();
        $firstStop = $firstStopStmt->get_result()->fetch_assoc();
        $firstStopStmt->close();

        if ($firstStop
            && route_path_is_usable_point($firstStop['latitude'], $firstStop['longitude'])) {
            $points = route_path_orient_sequence(
                $points,
                (float) $firstStop['latitude'],
                (float) $firstStop['longitude']
            );
        }
    }
}

echo json_encode(array(
    "success" => true,
    "route_points" => $points,
));

$conn->close();

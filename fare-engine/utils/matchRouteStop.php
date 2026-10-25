<?php

/* The constants must be defined before the include guard below: top-level
   function declarations are early-bound, so on the first include the guard
   already sees matchRouteStop() and returns, which would otherwise leave the
   constants undefined and make isRouteStopMatchAcceptable() fatal. */
if (!defined('ROUTE_STOP_MATCH_THRESHOLD_KM')) {
    /**
     * Default maximum distance (km) between a GPS point and a route_stops
     * record for the stop to be considered a valid match.
     */
    define('ROUTE_STOP_MATCH_THRESHOLD_KM', 0.5);
}

if (!defined('ROUTE_STOP_MATCH_HARD_CAP_KM')) {
    /**
     * Absolute ceiling (km) — even with very good accuracy we never accept a
     * stop farther away than this.
     */
    define('ROUTE_STOP_MATCH_HARD_CAP_KM', 2.0);
}

if (function_exists('matchRouteStop')) {
    return;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/canonicalRouteName.php';

use App\Core\Database;

function haversineKm($lat1, $lng1, $lat2, $lng2)
{
    $earthRadius = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLng / 2) * sin($dLng / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}

/**
 * Match a GPS coordinate to the nearest route_stops record belonging to the
 * given route (canonical comparison, so direction is respected — a stop name
 * on the opposite direction is never considered).
 *
 * Returns the nearest route_stops row with distance_km/distance_m added, or
 * null when the route has no stops.
 */
function matchRouteStop($lat, $lng, $routeName, $conn = null)
{
    if ($conn === null) {
        $conn = Database::get();
    }

    $canon = canonicalRouteName($routeName);

    $stmt = $conn->query("
        SELECT
            id,
            route_name,
            stop_name,
            latitude,
            longitude,
            sequence_order,
            km_marker,
            fare_pricing_id
        FROM route_stops
    ");

    $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $nearest = null;
    $nearestDistance = PHP_FLOAT_MAX;

    foreach ($stops as $stop) {
        if (canonicalRouteName($stop['route_name']) !== $canon) {
            continue;
        }

        $distance = haversineKm(
            (float)$lat,
            (float)$lng,
            (float)$stop['latitude'],
            (float)$stop['longitude']
        );

        if ($distance < $nearestDistance) {
            $nearestDistance = $distance;
            $nearest = $stop;
        }
    }

    if ($nearest === null) {
        return null;
    }

    $nearest['distance_km'] = $nearestDistance;
    $nearest['distance_m'] = round($nearestDistance * 1000);

    return $nearest;
}

/**
 * Decide whether a matched route stop is acceptable given the GPS accuracy.
 * Poor accuracy widens the accepted distance, but never beyond the hard cap.
 *
 * @param float|null $distanceKm Distance of the nearest stop.
 * @param float|null $accuracyM  Reported GPS accuracy in meters.
 */
function isRouteStopMatchAcceptable($distanceKm, $accuracyM = null)
{
    if ($distanceKm === null) {
        return false;
    }

    if ($distanceKm > ROUTE_STOP_MATCH_HARD_CAP_KM) {
        return false;
    }

    $threshold = ROUTE_STOP_MATCH_THRESHOLD_KM;

    // Widen the threshold for poor accuracy (up to the hard cap).
    if ($accuracyM !== null && $accuracyM > 50) {
        $threshold = min(
            $threshold + ($accuracyM / 1000),
            ROUTE_STOP_MATCH_HARD_CAP_KM
        );
    }

    return $distanceKm <= $threshold;
}

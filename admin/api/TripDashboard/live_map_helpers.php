<?php

declare(strict_types=1);

/**
 * Live-map validation helpers shared by the Trip Dashboard API.
 *
 * Pure functions only: coordinate validation, route-distance measurement and
 * GPS speed validation/recomputation. Keeping them here makes the map rules
 * testable and identical everywhere they are used.
 */

if (!defined('LIVE_MAP_GPS_ROUTE_TOLERANCE_KM')) {
    define('LIVE_MAP_GPS_ROUTE_TOLERANCE_KM', 2.5);
}
if (!defined('LIVE_MAP_SPEED_MAX_KMH')) {
    define('LIVE_MAP_SPEED_MAX_KMH', 150.0);
}
if (!defined('LIVE_MAP_GPS_STALE_SECONDS')) {
    define('LIVE_MAP_GPS_STALE_SECONDS', 180);
}
if (!defined('LIVE_MAP_SPEED_MIN_SECONDS')) {
    define('LIVE_MAP_SPEED_MIN_SECONDS', 5);
}
if (!defined('LIVE_MAP_SPEED_MAX_JUMP_KM')) {
    define('LIVE_MAP_SPEED_MAX_JUMP_KM', 3.0);
}
/* Expected operating area (Philippine area of responsibility). Coordinates
   outside it are corrupt/foreign data and are rejected, never clamped. */
if (!defined('LIVE_MAP_PH_LAT_MIN')) {
    define('LIVE_MAP_PH_LAT_MIN', 4.4);
    define('LIVE_MAP_PH_LAT_MAX', 21.6);
    define('LIVE_MAP_PH_LNG_MIN', 116.0);
    define('LIVE_MAP_PH_LNG_MAX', 127.0);
}

if (!function_exists('live_map_valid_coord')) {
    /**
     * A coordinate is usable only when both values are finite, inside the
     * geographic range and not the null-island (0,0) sentinel.
     */
    function live_map_valid_coord($lat, $lng): bool
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return false;
        }
        $lat = (float)$lat;
        $lng = (float)$lng;
        if (!is_finite($lat) || !is_finite($lng)) {
            return false;
        }
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return false;
        }
        if (abs($lat) < 0.000001 && abs($lng) < 0.000001) {
            return false;
        }
        return true;
    }
}

if (!function_exists('live_map_valid_ph_coord')) {
    /**
     * True only for a real coordinate inside the expected R-Connect operating
     * area. Used to reject Africa/Asia/ocean junk rows from the live map and
     * from the simulator instead of drawing them anywhere.
     */
    function live_map_valid_ph_coord($lat, $lng): bool
    {
        if (!live_map_valid_coord($lat, $lng)) {
            return false;
        }
        $lat = (float)$lat;
        $lng = (float)$lng;
        return $lat >= LIVE_MAP_PH_LAT_MIN && $lat <= LIVE_MAP_PH_LAT_MAX
            && $lng >= LIVE_MAP_PH_LNG_MIN && $lng <= LIVE_MAP_PH_LNG_MAX;
    }
}

if (!function_exists('live_map_haversine_km')) {
    function live_map_haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }
}

if (!function_exists('live_map_point_segment_km')) {
    /**
     * Shortest distance from a point to a route segment, using an
     * equirectangular projection around the point (accurate for the few-km
     * scale this map works at).
     */
    function live_map_point_segment_km(float $plat, float $plng, float $alat, float $alng, float $blat, float $blng): float
    {
        $cosLat = cos(deg2rad($plat));
        $ax = ($alng - $plng) * $cosLat * 111.32;
        $ay = ($alat - $plat) * 110.57;
        $bx = ($blng - $plng) * $cosLat * 111.32;
        $by = ($blat - $plat) * 110.57;
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lenSq = $dx * $dx + $dy * $dy;
        if ($lenSq <= 0.0000001) {
            return sqrt($ax * $ax + $ay * $ay);
        }
        $t = max(0.0, min(1.0, -($ax * $dx + $ay * $dy) / $lenSq));
        $cx = $ax + $dx * $t;
        $cy = $ay + $dy * $t;
        return sqrt($cx * $cx + $cy * $cy);
    }
}

if (!function_exists('live_map_route_stops')) {
    /**
     * Normalise and validate route_stops rows (ordered by sequence_order ASC
     * by the caller). Invalid rows are dropped, never guessed.
     *
     * @return array<int,array{name:string,lat:float,lng:float,seq:int}>
     */
    function live_map_route_stops(array $rows): array
    {
        $stops = [];
        foreach ($rows as $row) {
            $lat = $row['latitude'] ?? null;
            $lng = $row['longitude'] ?? null;
            if (!live_map_valid_ph_coord($lat, $lng)) {
                continue;
            }
            $stops[] = [
                'name' => trim((string)($row['stop_name'] ?? '')),
                'lat' => (float)$lat,
                'lng' => (float)$lng,
                'seq' => (int)($row['sequence_order'] ?? 0),
            ];
        }
        return $stops;
    }
}

if (!function_exists('live_map_distance_to_route_km')) {
    /**
     * Distance from a point to the route polyline, or null when the route has
     * fewer than two valid stops.
     */
    function live_map_distance_to_route_km(float $lat, float $lng, array $stops): ?float
    {
        if (count($stops) < 2) {
            return null;
        }
        $best = INF;
        for ($i = 0; $i < count($stops) - 1; $i++) {
            $distance = live_map_point_segment_km(
                $lat,
                $lng,
                (float)$stops[$i]['lat'],
                (float)$stops[$i]['lng'],
                (float)$stops[$i + 1]['lat'],
                (float)$stops[$i + 1]['lng']
            );
            if ($distance < $best) {
                $best = $distance;
            }
        }
        return $best === INF ? null : $best;
    }
}

if (!function_exists('live_map_validate_speed')) {
    /**
     * Resolve the km/h a trip row should display.
     *
     * The stored value is trusted only inside 0-150 km/h. Anything missing or
     * impossible (like the 10000 km/h rows) is either recomputed from two real
     * GPS fixes or reported as unavailable - never shown as-is.
     *
     * @param mixed $storedSpeed Raw driver_current_location.speed
     * @param array|null $previous ['lat','lng','at'] of the prior fix
     * @param array|null $current  ['lat','lng','at'] of the latest fix
     * @return array{speed:?float,source:string,rejected:bool,reason:?string}
     */
    function live_map_validate_speed($storedSpeed, ?array $previous, ?array $current): array
    {
        $stored = is_numeric($storedSpeed) ? (float)$storedSpeed : null;
        if ($stored !== null && is_finite($stored) && $stored >= 0.0 && $stored <= LIVE_MAP_SPEED_MAX_KMH) {
            return [
                'speed' => round($stored, 1),
                'source' => 'stored',
                'rejected' => false,
                'reason' => null,
            ];
        }

        $reason = ($stored === null) ? 'missing_speed' : 'implausible_speed';
        $rejected = $stored !== null;

        if ($previous !== null && $current !== null
            && live_map_valid_coord($previous['lat'] ?? null, $previous['lng'] ?? null)
            && live_map_valid_coord($current['lat'] ?? null, $current['lng'] ?? null)) {
            $atPrevious = isset($previous['at']) ? strtotime((string)$previous['at']) : false;
            $atCurrent = isset($current['at']) ? strtotime((string)$current['at']) : false;
            if ($atPrevious !== false && $atCurrent !== false) {
                $elapsed = $atCurrent - $atPrevious;
                if ($elapsed >= LIVE_MAP_SPEED_MIN_SECONDS) {
                    $distance = live_map_haversine_km(
                        (float)$current['lat'],
                        (float)$current['lng'],
                        (float)$previous['lat'],
                        (float)$previous['lng']
                    );
                    if ($distance <= LIVE_MAP_SPEED_MAX_JUMP_KM) {
                        $kmh = $distance / ($elapsed / 3600.0);
                        if (is_finite($kmh) && $kmh >= 0.0 && $kmh <= LIVE_MAP_SPEED_MAX_KMH) {
                            return [
                                'speed' => round($kmh, 1),
                                'source' => 'history',
                                'rejected' => $rejected,
                                'reason' => $reason,
                            ];
                        }
                    }
                    $reason = $rejected ? $reason : 'speed_jump';
                }
            }
        }

        return [
            'speed' => null,
            'source' => 'none',
            'rejected' => $rejected,
            'reason' => $reason,
        ];
    }
}

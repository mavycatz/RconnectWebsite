<?php

/**
 * Route geometry for passenger destination detection.
 *
 * The segments below are the actual R-Connect route sections for both travel
 * directions. They let the destination alerts know where the passenger is
 * along the route (before / near / past the destination) instead of relying
 * only on straight-line distance.
 *
 * The passenger's selected destination remains passenger_trips
 * planned_dropoff_lat/lng/name; the route data supports movement detection
 * and never replaces the planned destination with the nearest route stop.
 *
 * Direction keys match canonicalRouteName() output: "rizal-cabanatuan" is
 * the Rizal -> Cabanatuan trip and "cabanatuan-rizal" is the reverse.
 */

if (defined('PASSENGER_DESTINATION_ROUTE_SEGMENTS')) {
    return;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/canonicalRouteName.php';

if (!function_exists('passengerDestinationHaversineMeters')) {
    /**
     * Haversine distance in metres. Kept local so the geometry file can be
     * loaded on its own (e.g. in a CLI harness).
     */
    function passengerDestinationHaversineMeters($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000.0;

        $dLat = deg2rad((float) $lat2 - (float) $lat1);
        $dLng = deg2rad((float) $lng2 - (float) $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}

if (!function_exists('passengerDestinationRouteNormalizeName')) {
    /**
     * Normalize a stop/area name for matching (lowercase, punctuation and
     * common place suffixes removed).
     */
    function passengerDestinationRouteNormalizeName($value)
    {
        $value = strtolower((string) $value);
        $value = str_replace([
            ', nueva ecija',
            ', cabanatuan city',
            ', cabanatuan',
            ' city',
        ], '', $value);
        $value = preg_replace('/[^a-z0-9 ]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}

if (!defined('PASSENGER_DESTINATION_ROUTE_SEGMENTS')) {
    define('PASSENGER_DESTINATION_ROUTE_SEGMENTS', [
        // Rizal -> Cabanatuan (north to south). The first two entries cover
        // the trip's origin stretch (Rizal/Pob Sur Terminal -> Del Pilar) so a
        // bus still at the Rizal terminal matches the route instead of being
        // rejected as off-route; the coordinates mirror the forward
        // direction's terminal/Del Pilar tail.
        'rizal-cabanatuan' => [
            ['name' => 'Rizal/Pob Sur Terminal', 'start' => [15.705381, 121.099504], 'end' => [15.698276, 121.090763]],
            ['name' => 'Del Pilar, Rizal', 'start' => [15.698276, 121.090763], 'end' => [15.688399, 121.078317]],
            ['name' => 'Aglipay, Rizal', 'start' => [15.688171, 121.078333], 'end' => [15.673471, 121.059871]],
            ['name' => 'Mangahan, Rizal', 'start' => [15.673443, 121.060099], 'end' => [15.663572, 121.048188]],
            ['name' => 'Bicos, Rizal', 'start' => [15.673478, 121.059498], 'end' => [15.655781, 121.039491]],
            ['name' => 'Inanama, Llanera', 'start' => [15.655738, 121.039353], 'end' => [15.643641, 121.026456]],
            ['name' => 'Brgy. Mabini, Llanera, Nueva Ecija', 'start' => [15.643576, 121.026335], 'end' => [15.633923, 121.015010]],
            ['name' => 'General Ricarte, Llanera', 'start' => [15.633678, 121.014894], 'end' => [15.626404, 121.006311]],
            ['name' => 'Mataas na Kahoy, Llanera', 'start' => [15.632328, 121.013177], 'end' => [15.612206, 120.989858]],
            ['name' => 'General Luna, Llanera', 'start' => [15.626572, 121.006171], 'end' => [15.608055, 120.985143]],
            ['name' => 'Casile & San Ricardo, Llanera', 'start' => [15.608055, 120.985143], 'end' => [15.580534, 120.968100]],
            ['name' => 'Homestead II, Talavera', 'start' => [15.580473, 120.968124], 'end' => [15.573156, 120.960269]],
            ['name' => 'Homestead I, Talavera', 'start' => [15.573123, 120.960358], 'end' => [15.565685, 120.952198]],
            ['name' => 'Baluga & Sto. Niño, Talavera', 'start' => [15.565591, 120.952203], 'end' => [15.555213, 120.940824]],
            ['name' => 'Pinaagpanaan, Talavera', 'start' => [15.555195, 120.940781], 'end' => [15.544280, 120.932198]],
            ['name' => 'San Miguel na Munti, Talavera', 'start' => [15.544150, 120.932219], 'end' => [15.530175, 120.934003]],
            ['name' => 'Caalibangbangan, Cabanatuan City', 'start' => [15.530186, 120.934012], 'end' => [15.519605, 120.941484]],
            ['name' => 'Mayapyap, Cabanatuan City', 'start' => [15.519539, 120.941645], 'end' => [15.514590, 120.956121]],
            ['name' => 'Valdefuente & Daan Sarile, Cabanatuan City', 'start' => [15.513837, 120.956471], 'end' => [15.502878, 120.977150]],
            ['name' => 'Bitas, Cabanatuan City', 'start' => [15.502840, 120.977173], 'end' => [15.494304, 120.975876]],
            ['name' => 'Cabanatuan Central Terminal', 'point' => [15.472336, 120.969126]],
        ],

        // Cabanatuan -> Rizal (south to north).
        'cabanatuan-rizal' => [
            ['name' => 'Cabanatuan Central Terminal → Daang Sarile', 'start' => [15.472229, 120.969183], 'end' => [15.506043, 120.969857]],
            ['name' => 'Valdefuente', 'start' => [15.506807, 120.966642], 'end' => [15.513919, 120.956407]],
            ['name' => 'Mayapyap', 'start' => [15.514560, 120.956086], 'end' => [15.519545, 120.941635]],
            ['name' => 'Caalibangbangan', 'start' => [15.519634, 120.941532], 'end' => [15.530168, 120.934014]],
            ['name' => 'San Miguel Munti, Talavera', 'start' => [15.530170, 120.934020], 'end' => [15.544283, 120.932201]],
            ['name' => 'Pinagpanaan, Talavera', 'start' => [15.544254, 120.932203], 'end' => [15.555177, 120.940799]],
            ['name' => 'Baluga/Sto. Niño, Talavera', 'start' => [15.555200, 120.940797], 'end' => [15.565735, 120.952217]],
            ['name' => 'Homestead 1, Talavera', 'start' => [15.565606, 120.952186], 'end' => [15.573054, 120.960252]],
            ['name' => 'Homestead 2, Talavera', 'start' => [15.573090, 120.960372], 'end' => [15.580444, 120.968118]],
            ['name' => 'San Ricardo/Casile, Talavera', 'start' => [15.580415, 120.968036], 'end' => [15.608321, 120.985572]],
            ['name' => 'Gen. Luna, Llanera', 'start' => [15.608243, 120.985589], 'end' => [15.626292, 121.006282]],
            ['name' => 'Mataas na Kahoy, Llanera', 'start' => [15.632328, 121.013177], 'end' => [15.612206, 120.989858]],
            ['name' => 'General Ricarte, Llanera', 'start' => [15.633678, 121.014894], 'end' => [15.626404, 121.006311]],
            ['name' => 'Brgy. Mabini, Llanera, Nueva Ecija', 'start' => [15.643576, 121.026335], 'end' => [15.633923, 121.015010]],
            ['name' => 'Inanama, Llanera', 'start' => [15.655738, 121.039353], 'end' => [15.643641, 121.026456]],
            ['name' => 'Bicos, Rizal', 'start' => [15.673478, 121.059498], 'end' => [15.655781, 121.039491]],
            ['name' => 'Mangahan, Rizal', 'start' => [15.673443, 121.060099], 'end' => [15.663572, 121.048188]],
            ['name' => 'Aglipay, Rizal', 'start' => [15.688171, 121.078333], 'end' => [15.673471, 121.059871]],
            ['name' => 'Del Pilar, Rizal', 'start' => [15.688399, 121.078317], 'end' => [15.698276, 121.090763]],
            ['name' => 'Rizal/Pob Sur Terminal', 'point' => [15.705381, 121.099504]],
        ],
    ]);
}

if (!defined('PASSENGER_DESTINATION_ROUTE_LABELS')) {
    define('PASSENGER_DESTINATION_ROUTE_LABELS', [
        'rizal-cabanatuan' => 'Rizal → Cabanatuan',
        'cabanatuan-rizal' => 'Cabanatuan → Rizal',
    ]);
}

if (!function_exists('passengerDestinationRouteChain')) {
    /**
     * The direction's segments chained in supplied order. A segment whose
     * supplied start/end is reversed relative to the chain is flipped so the
     * polyline follows the travel direction; a lone terminal point becomes a
     * short final link. Progress is cumulative along the chain.
     *
     * @return array<int,array<string,mixed>>|null
     */
    function passengerDestinationRouteChain($directionKey)
    {
        static $cache = [];

        if (isset($cache[$directionKey])) {
            return $cache[$directionKey];
        }

        if (!isset(PASSENGER_DESTINATION_ROUTE_SEGMENTS[$directionKey])) {
            $cache[$directionKey] = null;
            return null;
        }

        $chained = [];
        $total = 0.0;
        $last = null;

        foreach (PASSENGER_DESTINATION_ROUTE_SEGMENTS[$directionKey] as $entry) {
            $name = (string) ($entry['name'] ?? '');

            if (isset($entry['point'])) {
                $start = $last;
                $end = [(float) $entry['point'][0], (float) $entry['point'][1]];

                if ($start === null) {
                    $last = $end;
                    continue;
                }
            } else {
                $start = [(float) $entry['start'][0], (float) $entry['start'][1]];
                $end = [(float) $entry['end'][0], (float) $entry['end'][1]];

                if ($last !== null) {
                    $distanceToStart = passengerDestinationHaversineMeters($last[0], $last[1], $start[0], $start[1]);
                    $distanceToEnd = passengerDestinationHaversineMeters($last[0], $last[1], $end[0], $end[1]);

                    if ($distanceToEnd < $distanceToStart) {
                        $swap = $start;
                        $start = $end;
                        $end = $swap;
                    }
                }
            }

            $length = passengerDestinationHaversineMeters($start[0], $start[1], $end[0], $end[1]);

            if ($length < 0.5) {
                $last = $end;
                continue;
            }

            $chained[] = [
                'name' => $name,
                'start_lat' => $start[0],
                'start_lng' => $start[1],
                'end_lat' => $end[0],
                'end_lng' => $end[1],
                'length_m' => $length,
                'start_m' => $total,
            ];

            $total += $length;
            $last = $end;
        }

        $cache[$directionKey] = $chained;

        return $chained;
    }
}

if (!function_exists('passengerDestinationRouteTotalLength')) {
    /**
     * Total chained length of a direction in metres.
     */
    function passengerDestinationRouteTotalLength($directionKey)
    {
        $chain = passengerDestinationRouteChain($directionKey);

        if ($chain === null || !$chain) {
            return 0.0;
        }

        $last = $chain[count($chain) - 1];

        return $last['start_m'] + $last['length_m'];
    }
}

if (!function_exists('passengerDestinationRouteProjection')) {
    /**
     * Nearest point on a direction's route for a geographic position.
     *
     * @return array{segment_name:string,segment_index:int,lat:float,lng:float,progress_m:float,offset_m:float}|null
     */
    function passengerDestinationRouteProjection($directionKey, $lat, $lng)
    {
        $chain = passengerDestinationRouteChain($directionKey);

        if ($chain === null || !$chain) {
            return null;
        }

        $lat = is_numeric($lat) ? (float) $lat : null;
        $lng = is_numeric($lng) ? (float) $lng : null;

        if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng)) {
            return null;
        }

        $best = null;

        foreach ($chain as $index => $segment) {
            $refLat = deg2rad(($segment['start_lat'] + $segment['end_lat']) / 2);

            $bx = ($segment['end_lng'] - $segment['start_lng']) * 111320.0 * cos($refLat);
            $by = ($segment['end_lat'] - $segment['start_lat']) * 110540.0;
            $px = ($lng - $segment['start_lng']) * 111320.0 * cos($refLat);
            $py = ($lat - $segment['start_lat']) * 110540.0;

            $lengthSquared = ($bx * $bx) + ($by * $by);
            $t = $lengthSquared > 0 ? (($px * $bx) + ($py * $by)) / $lengthSquared : 0.0;
            $t = max(0.0, min(1.0, $t));

            $projectedLat = $segment['start_lat'] + (($segment['end_lat'] - $segment['start_lat']) * $t);
            $projectedLng = $segment['start_lng'] + (($segment['end_lng'] - $segment['start_lng']) * $t);

            $offset = passengerDestinationHaversineMeters($lat, $lng, $projectedLat, $projectedLng);

            if ($best === null || $offset < $best['offset_m']) {
                $best = [
                    'segment_name' => $segment['name'],
                    'segment_index' => (int) $index,
                    'lat' => $projectedLat,
                    'lng' => $projectedLng,
                    'progress_m' => $segment['start_m'] + ($t * $segment['length_m']),
                    'offset_m' => $offset,
                ];
            }
        }

        return $best;
    }
}

if (!function_exists('passengerDestinationRouteSegmentIndexByName')) {
    /**
     * The index of the first chained segment whose name matches the given
     * stop/area name (normalized exact or containment match), or null.
     */
    function passengerDestinationRouteSegmentIndexByName($directionKey, $name)
    {
        $needle = passengerDestinationRouteNormalizeName($name);

        if ($needle === '') {
            return null;
        }

        $chain = passengerDestinationRouteChain($directionKey);

        if ($chain === null) {
            return null;
        }

        foreach ($chain as $index => $segment) {
            $candidate = passengerDestinationRouteNormalizeName($segment['name']);

            if ($candidate === '') {
                continue;
            }

            if (
                $candidate === $needle
                || strpos($candidate, $needle) !== false
                || strpos($needle, $candidate) !== false
            ) {
                return (int) $index;
            }
        }

        return null;
    }
}

if (!function_exists('passengerDestinationRouteAnchorByName')) {
    /**
     * The supplied anchor coordinate of the named route area for a direction:
     * a segment's start point (or a terminal point entry). This is the named
     * place's coordinate as supplied, independent of the chained polyline's
     * orientation, so it can resolve a passenger's selected destination.
     *
     * @return array{name:string,lat:float,lng:float}|null
     */
    function passengerDestinationRouteAnchorByName($directionKey, $name)
    {
        $needle = passengerDestinationRouteNormalizeName($name);

        if ($needle === '' || !isset(PASSENGER_DESTINATION_ROUTE_SEGMENTS[$directionKey])) {
            return null;
        }

        foreach (PASSENGER_DESTINATION_ROUTE_SEGMENTS[$directionKey] as $entry) {
            $candidate = passengerDestinationRouteNormalizeName($entry['name'] ?? '');

            if ($candidate === '') {
                continue;
            }

            if (
                $candidate !== $needle
                && strpos($candidate, $needle) === false
                && strpos($needle, $candidate) === false
            ) {
                continue;
            }

            if (isset($entry['point'])) {
                $lat = (float) $entry['point'][0];
                $lng = (float) $entry['point'][1];
            } else {
                $lat = (float) $entry['start'][0];
                $lng = (float) $entry['start'][1];
            }

            if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }

            return [
                'name' => (string) $entry['name'],
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        return null;
    }
}

if (!function_exists('passengerDestinationRouteAnchorById')) {
    /**
     * Resolve the supplied route coordinate anchor by destination_id (e.g. 113 => General Ricarte, Llanera).
     *
     * @param string $directionKey
     * @param int|string $destinationId
     * @return array{name:string,lat:float,lng:float}|null
     */
    function passengerDestinationRouteAnchorById($directionKey, $destinationId)
    {
        $id = (int) $destinationId;
        if ($id <= 0) {
            return null;
        }

        // Known route destination ID mappings
        if ($id === 113) {
            return passengerDestinationRouteAnchorByName($directionKey, 'General Ricarte, Llanera');
        }

        return null;
    }
}

if (!function_exists('passengerDestinationRouteDirectionKeys')) {
    /**
     * @return array<int,string>
     */
    function passengerDestinationRouteDirectionKeys()
    {
        return array_keys(PASSENGER_DESTINATION_ROUTE_SEGMENTS);
    }
}

if (!function_exists('passengerDestinationRouteDirection')) {
    /**
     * Resolve the trip's route direction. Order of evidence:
     *   1. the trip's route name (canonical),
     *   2. the boarding and destination areas' position on the route,
     *   3. recent GPS movement along the route.
     * The destination name alone never decides the direction.
     *
     * @param array<string,mixed> $trip
     * @param array{lat:float,lng:float}|null $previousLocation
     * @return array{key:string,label:string,source:string}|null
     */
    function passengerDestinationRouteDirection(array $trip, $routeName, $lat = null, $lng = null, $previousLocation = null)
    {
        $keys = passengerDestinationRouteDirectionKeys();

        // 1. Trip route name.
        $canonical = canonicalRouteName($routeName);
        if ($canonical !== '' && in_array($canonical, $keys, true)) {
            return [
                'key' => $canonical,
                'label' => PASSENGER_DESTINATION_ROUTE_LABELS[$canonical] ?? $canonical,
                'source' => 'route_name',
            ];
        }

        // 2. Boarding and destination areas.
        $boarding = trim((string) ($trip['boarding_point'] ?? ''));
        $destination = trim((string) ($trip['planned_dropoff_name'] ?? ''));
        if ($destination === '') {
            $destination = trim((string) ($trip['selected_destination'] ?? ($trip['destination'] ?? '')));
        }

        if ($boarding !== '' && $destination !== '') {
            $candidates = [];

            foreach ($keys as $key) {
                $boardingIndex = passengerDestinationRouteSegmentIndexByName($key, $boarding);
                $destinationIndex = passengerDestinationRouteSegmentIndexByName($key, $destination);

                if ($boardingIndex !== null && $destinationIndex !== null && $boardingIndex < $destinationIndex) {
                    $candidates[$key] = $destinationIndex - $boardingIndex;
                }
            }

            if (count($candidates) === 1) {
                $key = array_key_first($candidates);
                return [
                    'key' => $key,
                    'label' => PASSENGER_DESTINATION_ROUTE_LABELS[$key] ?? $key,
                    'source' => 'areas',
                ];
            }
        }

        // 3. Recent GPS movement along the route.
        $previousLat = is_array($previousLocation) && isset($previousLocation['lat']) && is_numeric($previousLocation['lat'])
            ? (float) $previousLocation['lat']
            : null;
        $previousLng = is_array($previousLocation) && isset($previousLocation['lng']) && is_numeric($previousLocation['lng'])
            ? (float) $previousLocation['lng']
            : null;

        if (
            $previousLat !== null && $previousLng !== null
            && is_numeric($lat) && is_numeric($lng)
            && is_finite($previousLat) && is_finite($previousLng)
            && is_finite((float) $lat) && is_finite((float) $lng)
        ) {
            $movement = passengerDestinationHaversineMeters($previousLat, $previousLng, (float) $lat, (float) $lng);

            if ($movement >= 20.0) {
                $candidates = [];

                foreach ($keys as $key) {
                    $previousProjection = passengerDestinationRouteProjection($key, $previousLat, $previousLng);
                    $currentProjection = passengerDestinationRouteProjection($key, $lat, $lng);

                    if (
                        $previousProjection === null || $currentProjection === null
                        || $previousProjection['offset_m'] > 500.0 || $currentProjection['offset_m'] > 500.0
                    ) {
                        continue;
                    }

                    $delta = $currentProjection['progress_m'] - $previousProjection['progress_m'];

                    if ($delta > 10.0) {
                        $candidates[$key] = $delta;
                    }
                }

                if ($candidates) {
                    arsort($candidates);
                    $key = array_key_first($candidates);
                    return [
                        'key' => $key,
                        'label' => PASSENGER_DESTINATION_ROUTE_LABELS[$key] ?? $key,
                        'source' => 'gps_movement',
                    ];
                }
            }
        }

        return null;
    }
}

if (!function_exists('passengerDestinationRouteState')) {
    /**
     * The passenger's position relative to the destination along the route.
     */
    function passengerDestinationRouteState($passengerProgress, $destinationProgress, $margin = 150.0)
    {
        if ($passengerProgress === null || $destinationProgress === null) {
            return 'unknown';
        }

        if ($passengerProgress < $destinationProgress - $margin) {
            return 'before';
        }

        if ($passengerProgress > $destinationProgress + $margin) {
            return 'past';
        }

        return 'near';
    }
}

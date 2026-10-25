<?php

/**
 * Coordinate reference for the passenger destination route map.
 *
 * The map's stop anchors always come from the database `route_stops` rows for
 * the trip's route; this reference fills in a stop coordinate when the matched
 * route_stops row has no latitude/longitude of its own, and its coordinates
 * also define each destination's geographic area for filtering the existing
 * `route_points` street geometry.
 *
 * Both travel directions are defined here: "cabanatuan-rizal" (Cabanatuan ->
 * Rizal) and "rizal-cabanatuan" (Rizal -> Cabanatuan). Each stop's anchor is
 * its supplied route point; the segment geometry shared with the boarding
 * matcher lives in DestinationRouteGeometry.php.
 */

if (defined('STOP_COORDS') && function_exists('passengerStopCoordsLookup')) {
    return;
}

require_once __DIR__ . '/BoardingStopOrder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/canonicalRouteName.php';

if (!defined('STOP_COORDS')) {
    define('STOP_COORDS', [
        'cabanatuan-rizal' => [
            'Cabanatuan Central Terminal–Daang Sarile' => [
                ['lat' => 15.472438, 'lng' => 120.969040],
            ],
            'Valdefuente' => [
                ['lat' => 15.510157, 'lng' => 120.958587],
            ],
            'Mayapyap' => [
                ['lat' => 15.522, 'lng' => 120.948],
            ],
            'Caalibangbangan' => [
                ['lat' => 15.531, 'lng' => 120.940],
            ],
            'San Miguel Munti, Talavera' => [
                ['lat' => 15.548, 'lng' => 120.948],
            ],
            'Pinagpanaan, Talavera' => [
                ['lat' => 15.555, 'lng' => 120.950],
            ],
            'Baluga/Sto. Niño, Talavera' => [
                ['lat' => 15.561, 'lng' => 120.952],
            ],
            'Homestead 1, Talavera' => [
                ['lat' => 15.566468, 'lng' => 120.953142],
            ],
            'Homestead 2, Talavera' => [
                ['lat' => 15.577435, 'lng' => 120.965010],
            ],
            'San Ricardo/Casile, Talavera' => [
                ['lat' => 15.588, 'lng' => 120.970],
            ],
            'Gen. Luna, Llanera' => [
                ['lat' => 15.605, 'lng' => 120.990],
            ],
            'Mataas na Kahoy, Llanera' => [
                ['lat' => 15.618, 'lng' => 121.005],
            ],
            'Ricarte, Llanera' => [
                ['lat' => 15.633678, 'lng' => 121.014894],
            ],
            'General Ricarte, Llanera' => [
                ['lat' => 15.633678, 'lng' => 121.014894],
            ],
            'Brgy. Mabini, Llanera' => [
                ['lat' => 15.638, 'lng' => 121.025],
            ],
            'Inanama, Llanera' => [
                ['lat' => 15.645, 'lng' => 121.035],
            ],
            'Bicos, Llanera' => [
                ['lat' => 15.652, 'lng' => 121.040],
            ],
            'Mangahan, Llanera' => [
                ['lat' => 15.662, 'lng' => 121.046],
            ],
            'Aglipay, Rizal' => [
                ['lat' => 15.675, 'lng' => 121.060],
            ],
            'Del Pilar, Rizal' => [
                ['lat' => 15.689167, 'lng' => 121.078555],
                ['lat' => 15.702674, 'lng' => 121.096471],
            ],
            'Rizal/Pob Sur Terminal' => [
                ['lat' => 15.689371, 'lng' => 121.078714],
            ],
        ],
        'rizal-cabanatuan' => [
            'Aglipay, Rizal' => [
                ['lat' => 15.688171, 'lng' => 121.078333],
            ],
            'Mangahan, Rizal' => [
                ['lat' => 15.673443, 'lng' => 121.060099],
            ],
            'Bicos, Rizal' => [
                ['lat' => 15.673478, 'lng' => 121.059498],
            ],
            'Inanama, Llanera' => [
                ['lat' => 15.655738, 'lng' => 121.039353],
            ],
            'Brgy. Mabini, Llanera, Nueva Ecija' => [
                ['lat' => 15.643576, 'lng' => 121.026335],
            ],
            'General Ricarte, Llanera' => [
                ['lat' => 15.633678, 'lng' => 121.014894],
            ],
            'Mataas na Kahoy, Llanera' => [
                ['lat' => 15.632328, 'lng' => 121.013177],
            ],
            'General Luna, Llanera' => [
                ['lat' => 15.626572, 'lng' => 121.006171],
            ],
            'Casile & San Ricardo, Llanera' => [
                ['lat' => 15.608055, 'lng' => 120.985143],
            ],
            'Homestead II, Talavera' => [
                ['lat' => 15.580473, 'lng' => 120.968124],
            ],
            'Homestead I, Talavera' => [
                ['lat' => 15.573123, 'lng' => 120.960358],
            ],
            'Baluga & Sto. Niño, Talavera' => [
                ['lat' => 15.565591, 'lng' => 120.952203],
            ],
            'Pinaagpanaan, Talavera' => [
                ['lat' => 15.555195, 'lng' => 120.940781],
            ],
            'San Miguel na Munti, Talavera' => [
                ['lat' => 15.544150, 'lng' => 120.932219],
            ],
            'Caalibangbangan, Cabanatuan City' => [
                ['lat' => 15.530186, 'lng' => 120.934012],
            ],
            'Mayapyap, Cabanatuan City' => [
                ['lat' => 15.519539, 'lng' => 120.941645],
            ],
            'Valdefuente & Daan Sarile, Cabanatuan City' => [
                ['lat' => 15.513837, 'lng' => 120.956471],
            ],
            'Bitas, Cabanatuan City' => [
                ['lat' => 15.502840, 'lng' => 120.977173],
            ],
            'Cabanatuan Central Terminal' => [
                ['lat' => 15.472336, 'lng' => 120.969126],
            ],
        ],
    ]);
}

if (!function_exists('passengerStopCoordsDirectionKey')) {
    /**
     * The direction key this reference can serve, or null when the route has no
     * reference data (for example the reverse Rizal -> Cabanatuan direction).
     */
    function passengerStopCoordsDirectionKey($routeName)
    {
        $canonical = canonicalRouteName($routeName);

        if ($canonical === '' || !array_key_exists($canonical, STOP_COORDS)) {
            return null;
        }

        return $canonical;
    }
}

if (!function_exists('passengerStopCoordsMatch')) {
    /**
     * Match a stop name to its raw reference entries for a direction.
     *
     * Matching is exact after normalization (punctuation/spacing only) so a
     * partial or unrelated name never resolves.
     *
     * @return array<int,array<string,float>>|null
     */
    function passengerStopCoordsMatch($stopName, $directionKey)
    {
        if (!is_string($directionKey) || $directionKey === '' || !isset(STOP_COORDS[$directionKey])) {
            return null;
        }

        $needle = passengerNormalizeStopName($stopName);
        if ($needle === '') {
            return null;
        }

        $containmentMatch = null;
        foreach (STOP_COORDS[$directionKey] as $referenceName => $points) {
            $referenceNorm = passengerNormalizeStopName($referenceName);
            if ($referenceNorm === '') {
                continue;
            }

            if ($referenceNorm === $needle) {
                return (is_array($points) && !empty($points)) ? $points : null;
            }

            // Live names are often shorter than the reference key (for example
            // "Del Pilar" vs "Del Pilar, Rizal"), so the first containment match
            // is used only when no exact match exists.
            if (
                $containmentMatch === null
                && (strpos($referenceNorm, $needle) !== false || strpos($needle, $referenceNorm) !== false)
            ) {
                $containmentMatch = (is_array($points) && !empty($points)) ? $points : null;
            }
        }

        return $containmentMatch;
    }
}

if (!function_exists('passengerStopCoordsLookup')) {
    /**
     * Look up a stop's reference coordinates for a direction.
     *
     * Matching is exact after normalization (punctuation/spacing only) so a
     * partial or unrelated name never resolves. This keeps the historical
     * first-point behavior used for a stop coordinate fallback; use
     * passengerStopCoordsPoints() when the whole area is needed.
     *
     * @return array{lat:float,lng:float,point_count:int}|null
     */
    function passengerStopCoordsLookup($stopName, $directionKey)
    {
        $points = passengerStopCoordsMatch($stopName, $directionKey);
        if ($points === null || !isset($points[0]['lat'], $points[0]['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $points[0]['lat'],
            'lng' => (float) $points[0]['lng'],
            'point_count' => count($points),
        ];
    }
}

if (!function_exists('passengerStopCoordsPoints')) {
    /**
     * Every reference coordinate of a stop for a direction, in stored order.
     *
     * A destination may carry several coordinates (for example Del Pilar's two
     * points); together they define the destination's geographic area. The
     * map uses all of them rather than only the first.
     *
     * @return array<int,array{lat:float,lng:float}>|null
     */
    function passengerStopCoordsPoints($stopName, $directionKey)
    {
        $points = passengerStopCoordsMatch($stopName, $directionKey);
        if ($points === null) {
            return null;
        }

        $result = [];
        foreach ($points as $point) {
            if (!is_array($point) || !isset($point['lat'], $point['lng'])) {
                continue;
            }

            $lat = (float) $point['lat'];
            $lng = (float) $point['lng'];
            if (!is_finite($lat) || !is_finite($lng)) {
                continue;
            }

            $result[] = ['lat' => $lat, 'lng' => $lng];
        }

        return !empty($result) ? $result : null;
    }
}

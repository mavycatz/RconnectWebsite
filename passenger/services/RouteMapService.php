<?php

/**
 * Read-only computation of the passenger destination route map coverage.
 *
 * The covered segment always runs from the passenger's boarding point up to
 * and including the trip's stored selected destination, ordered by the
 * existing route_stops.sequence_order for the trip's route. Nothing here
 * writes to any table, and the official destination is never taken from the
 * client.
 */

if (function_exists('passengerBuildRouteMapCoverage')) {
    return;
}

require_once __DIR__ . '/StopCoordinates.php';
require_once __DIR__ . '/BoardingStopOrder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/canonicalRouteName.php';

if (!defined('ROUTE_MAP_DESTINATION_AREA_METERS')) {
    /**
     * Half-width of a destination's geographic corridor, in meters. Existing
     * route_points within this distance of the polyline through the
     * destination's reference coordinates are drawn as its street geometry.
     */
    define('ROUTE_MAP_DESTINATION_AREA_METERS', 250.0);
}

if (!function_exists('passengerRouteMapFailure')) {
    function passengerRouteMapFailure($code, $message)
    {
        return [
            'success' => false,
            'code' => $code,
            'message' => $message,
        ];
    }
}

if (!function_exists('passengerLoadRouteMapStops')) {
    /**
     * Load the trip route's route_stops rows ordered by their own sequence.
     * Route names differ between tables ("Cabanatuan-Rizal" vs
     * "Cabanatuan to Rizal Route"), so an exact match falls back to a
     * canonical comparison across the table (same approach as matchRouteStop).
     *
     * @return array<int,array<string,mixed>>
     */
    function passengerLoadRouteMapStops($conn, $routeName)
    {
        $columns = 'id, route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id';

        $stmt = $conn->prepare("
            SELECT " . $columns . "
            FROM route_stops
            WHERE route_name = ?
            ORDER BY sequence_order ASC, id ASC
        ");
        $stmt->execute([$routeName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            return $rows;
        }

        $canonical = canonicalRouteName($routeName);
        if ($canonical === '') {
            return [];
        }

        $stmt = $conn->query("SELECT " . $columns . " FROM route_stops ORDER BY sequence_order ASC, id ASC");
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (canonicalRouteName($row['route_name'] ?? '') === $canonical) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('passengerRouteMapRouteId')) {
    /**
     * Resolve the `routes` table id for a trip route name: exact match first,
     * then a canonical comparison across the table (the same tolerance as
     * passengerLoadRouteMapStops).
     *
     * @return int|null
     */
    function passengerRouteMapRouteId($conn, $routeName)
    {
        $routeName = trim((string) $routeName);
        if ($routeName === '') {
            return null;
        }

        $stmt = $conn->prepare("SELECT id FROM routes WHERE route_name = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$routeName]);
        $id = $stmt->fetchColumn();
        if ($id !== false && $id !== null && (int) $id > 0) {
            return (int) $id;
        }

        $canonical = canonicalRouteName($routeName);
        if ($canonical === '') {
            return null;
        }

        $stmt = $conn->query("SELECT id, route_name FROM routes ORDER BY id ASC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (canonicalRouteName($row['route_name'] ?? '') === $canonical) {
                return (int) $row['id'];
            }
        }

        return null;
    }
}

if (!function_exists('passengerLoadRouteMapPoints')) {
    /**
     * Load the trip route's existing route_points in stored order.
     *
     * These are the actual street geometry points; nothing is created here and
     * no point is selected by stop name.
     *
     * @return array<int,array{latitude:float,longitude:float,point_order:int|null}>
     */
    function passengerLoadRouteMapPoints($conn, $routeName, $routeId = null)
    {
        if ($routeId === null) {
            $routeId = passengerRouteMapRouteId($conn, $routeName);
        }
        if ($routeId === null) {
            return [];
        }

        $stmt = $conn->prepare("
            SELECT latitude, longitude, point_order
            FROM route_points
            WHERE route_id = ?
            ORDER BY point_order ASC
        ");
        $stmt->execute([$routeId]);

        $points = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lat = isset($row['latitude']) && $row['latitude'] !== null && $row['latitude'] !== ''
                ? (float) $row['latitude']
                : null;
            $lng = isset($row['longitude']) && $row['longitude'] !== null && $row['longitude'] !== ''
                ? (float) $row['longitude']
                : null;

            if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng)) {
                continue;
            }

            $points[] = [
                'latitude' => $lat,
                'longitude' => $lng,
                'point_order' => isset($row['point_order']) && $row['point_order'] !== null && $row['point_order'] !== ''
                    ? (int) $row['point_order']
                    : null,
            ];
        }

        return $points;
    }
}

if (!function_exists('passengerRouteMapRouteIdFromPoints')) {
    /**
     * Fallback route id when the `routes` table has no name match: pick the
     * route_points whose points overlap the trip's stop corridor best. This
     * covers databases where route_points rows exist without a usable
     * routes.route_name. Both directions share the same corridor, so any
     * overlapping route id yields the correct street section for the
     * geographic filter.
     *
     * @param array<int,array<string,mixed>> $stops
     * @return int|null
     */
    function passengerRouteMapRouteIdFromPoints($conn, array $stops)
    {
        $lats = [];
        $lngs = [];
        foreach ($stops as $stop) {
            $lat = isset($stop['latitude']) && $stop['latitude'] !== null && $stop['latitude'] !== ''
                ? (float) $stop['latitude']
                : null;
            $lng = isset($stop['longitude']) && $stop['longitude'] !== null && $stop['longitude'] !== ''
                ? (float) $stop['longitude']
                : null;
            if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng)) {
                continue;
            }
            $lats[] = $lat;
            $lngs[] = $lng;
        }

        if (!empty($lats)) {
            $stmt = $conn->prepare("
                SELECT route_id, COUNT(*) AS inside_count
                FROM route_points
                WHERE latitude BETWEEN ? AND ?
                  AND longitude BETWEEN ? AND ?
                GROUP BY route_id
                ORDER BY inside_count DESC
                LIMIT 1
            ");
            $stmt->execute([min($lats) - 0.02, max($lats) + 0.02, min($lngs) - 0.02, max($lngs) + 0.02]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && (int) ($row['inside_count'] ?? 0) >= 2) {
                return (int) $row['route_id'];
            }
        }

        $row = $conn->query("
            SELECT route_id
            FROM route_points
            GROUP BY route_id
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['route_id'] : null;
    }
}

if (!function_exists('passengerRouteMapRouteRows')) {
    /**
     * TEMPORARY DIAGNOSTICS: the routes table contents used to debug the live
     * geometry. Remove once the live mapping is confirmed.
     *
     * @return array<int,array<string,mixed>>
     */
    function passengerRouteMapRouteRows($conn)
    {
        $stmt = $conn->query("SELECT id, route_name FROM routes ORDER BY id ASC LIMIT 20");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('passengerRouteMapPointCounts')) {
    /**
     * TEMPORARY DIAGNOSTICS: route_points counts per route_id. Remove once the
     * live mapping is confirmed.
     *
     * @return array<int,array<string,mixed>>
     */
    function passengerRouteMapPointCounts($conn)
    {
        $stmt = $conn->query("
            SELECT route_id, COUNT(*) AS point_count
            FROM route_points
            GROUP BY route_id
            ORDER BY route_id ASC
            LIMIT 20
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('passengerFindRouteMapStopByFareId')) {
    /**
     * @return array<string,mixed>|null
     */
    function passengerFindRouteMapStopByFareId(array $stops, $farePricingId)
    {
        $farePricingId = (int) $farePricingId;
        if ($farePricingId <= 0) {
            return null;
        }

        foreach ($stops as $stop) {
            if ((int) ($stop['fare_pricing_id'] ?? 0) === $farePricingId) {
                return $stop;
            }
        }

        return null;
    }
}

if (!function_exists('passengerFindRouteMapStopByName')) {
    /**
     * Match a stop name against the route's stops: exact normalized match
     * first, then containment either way. Returns the first match in route
     * order so the anchor always follows sequence_order.
     *
     * @return array<string,mixed>|null
     */
    function passengerFindRouteMapStopByName(array $stops, $name)
    {
        $needle = passengerNormalizeStopName($name);
        if ($needle === '') {
            return null;
        }

        foreach ($stops as $stop) {
            if (passengerNormalizeStopName($stop['stop_name'] ?? '') === $needle) {
                return $stop;
            }
        }

        foreach ($stops as $stop) {
            $stopNorm = passengerNormalizeStopName($stop['stop_name'] ?? '');
            if ($stopNorm === '') {
                continue;
            }
            if (strpos($stopNorm, $needle) !== false || strpos($needle, $stopNorm) !== false) {
                return $stop;
            }
        }

        return null;
    }
}

if (!function_exists('passengerResolveRouteMapDestinationName')) {
    /**
     * The destination's display name: the trip's stored name when present,
     * otherwise the selected fare_pricing row's destination.
     */
    function passengerResolveRouteMapDestinationName($conn, $destinationId, $destinationName)
    {
        $destinationName = trim((string) $destinationName);
        if ($destinationName !== '' || (int) $destinationId <= 0) {
            return $destinationName;
        }

        try {
            $stmt = $conn->prepare("SELECT destination FROM fare_pricing WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $destinationId]);
            return trim((string) $stmt->fetchColumn());
        } catch (\Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('passengerResolveRouteMapDestination')) {
    /**
     * Destination anchor chain: fare_pricing_id bridge -> normalized stop-name
     * match -> null (map unavailable, never a guessed position).
     *
     * @return array<string,mixed>|null
     */
    function passengerResolveRouteMapDestination($conn, array $stops, $destinationId, $destinationName)
    {
        $stop = passengerFindRouteMapStopByFareId($stops, $destinationId);
        if ($stop !== null) {
            return $stop;
        }

        $destinationName = passengerResolveRouteMapDestinationName($conn, $destinationId, $destinationName);
        if ($destinationName === '') {
            return null;
        }

        return passengerFindRouteMapStopByName($stops, $destinationName);
    }
}

if (!function_exists('passengerResolveRouteMapBoarding')) {
    /**
     * Boarding anchor chain: boarding_stop_id -> boarding-point name match ->
     * the boarding fare zone's route_stops row -> the route's first stop.
     *
     * @return array<string,mixed>|null
     */
    function passengerResolveRouteMapBoarding($conn, array $stops, array $trip, $routeName)
    {
        $boardingStopId = (int) ($trip['boarding_stop_id'] ?? 0);
        if ($boardingStopId > 0) {
            foreach ($stops as $stop) {
                if ((int) ($stop['id'] ?? 0) === $boardingStopId) {
                    return $stop;
                }
            }
        }

        $boardingPoint = trim((string) ($trip['boarding_point'] ?? ''));
        $byName = passengerFindRouteMapStopByName($stops, $boardingPoint);
        if ($byName !== null) {
            return $byName;
        }

        $zone = passengerResolveBoardingFareZone($conn, $routeName, $boardingPoint, $boardingStopId);
        if ($zone !== null) {
            $byZone = passengerFindRouteMapStopByFareId($stops, (int) ($zone['id'] ?? 0));
            if ($byZone !== null) {
                return $byZone;
            }
        }

        return $stops[0] ?? null;
    }
}

if (!function_exists('passengerRouteMapStopCoordinates')) {
    /**
     * route_stops coordinates first; the Cabanatuan -> Rizal reference is a
     * fallback only, and only for that direction.
     *
     * @return array{lat:float|null,lng:float|null,coordinate_source:string|null}
     */
    function passengerRouteMapStopCoordinates(array $stop, $directionKey)
    {
        $lat = isset($stop['latitude']) && $stop['latitude'] !== null && $stop['latitude'] !== ''
            ? (float) $stop['latitude']
            : null;
        $lng = isset($stop['longitude']) && $stop['longitude'] !== null && $stop['longitude'] !== ''
            ? (float) $stop['longitude']
            : null;

        if ($lat !== null && $lng !== null) {
            return ['lat' => $lat, 'lng' => $lng, 'coordinate_source' => 'route_stops'];
        }

        if ($directionKey !== null) {
            $reference = passengerStopCoordsLookup($stop['stop_name'] ?? '', $directionKey);
            if ($reference !== null) {
                return [
                    'lat' => $reference['lat'],
                    'lng' => $reference['lng'],
                    'coordinate_source' => 'stop_coords',
                ];
            }
        }

        return ['lat' => null, 'lng' => null, 'coordinate_source' => null];
    }
}

if (!function_exists('passengerRouteMapStopPayload')) {
    /**
     * @return array<string,mixed>
     */
    function passengerRouteMapStopPayload(array $stop, $directionKey)
    {
        $coords = passengerRouteMapStopCoordinates($stop, $directionKey);

        return [
            'stop_id' => isset($stop['id']) ? (int) $stop['id'] : null,
            'stop_name' => (string) ($stop['stop_name'] ?? ''),
            'latitude' => $coords['lat'],
            'longitude' => $coords['lng'],
            'sequence_order' => isset($stop['sequence_order']) ? (int) $stop['sequence_order'] : null,
            'km_marker' => isset($stop['km_marker']) && $stop['km_marker'] !== null && $stop['km_marker'] !== ''
                ? (int) $stop['km_marker']
                : null,
            'fare_pricing_id' => isset($stop['fare_pricing_id']) && $stop['fare_pricing_id'] !== null
                ? (int) $stop['fare_pricing_id']
                : null,
            'coordinate_source' => $coords['coordinate_source'],
        ];
    }
}

if (!function_exists('passengerRouteMapPointToSegmentMeters')) {
    /**
     * Distance in meters from a point to a line segment, using a local
     * equirectangular projection. Accurate at destination-area scale (a few
     * kilometers), where a degree of latitude is ~111320 m and a degree of
     * longitude shrinks by cos(latitude).
     */
    function passengerRouteMapPointToSegmentMeters($lat, $lng, $aLat, $aLng, $bLat, $bLng)
    {
        $metersPerDegreeLat = 111320.0;
        $referenceLat = ((float) $aLat + (float) $bLat) / 2.0;
        $metersPerDegreeLng = $metersPerDegreeLat * cos(deg2rad($referenceLat));

        $x = ((float) $lng - (float) $aLng) * $metersPerDegreeLng;
        $y = ((float) $lat - (float) $aLat) * $metersPerDegreeLat;

        $bx = ((float) $bLng - (float) $aLng) * $metersPerDegreeLng;
        $by = ((float) $bLat - (float) $aLat) * $metersPerDegreeLat;

        $lengthSquared = ($bx * $bx) + ($by * $by);
        if ($lengthSquared <= 0.0) {
            return sqrt(($x * $x) + ($y * $y));
        }

        $t = (($x * $bx) + ($y * $by)) / $lengthSquared;
        if ($t < 0.0) {
            $t = 0.0;
        } elseif ($t > 1.0) {
            $t = 1.0;
        }

        $dx = $x - ($t * $bx);
        $dy = $y - ($t * $by);

        return sqrt(($dx * $dx) + ($dy * $dy));
    }
}

if (!function_exists('passengerRouteMapFilterPointsByArea')) {
    /**
     * Keep only existing route points inside the destination's area.
     *
     * The area is a corridor within ROUTE_MAP_DESTINATION_AREA_METERS of the
     * polyline through every reference coordinate of the destination; a single
     * coordinate becomes a radius around that point. Kept points keep their
     * source order and each receives a `run` index so non-adjacent stretches
     * are not bridged by a false straight line.
     *
     * @param array<int,array<string,mixed>> $points     Ordered route points
     * @param array<int,array{lat:float,lng:float}> $areaPoints Destination reference coordinates
     * @return array<int,array<string,mixed>>
     */
    function passengerRouteMapFilterPointsByArea(array $points, array $areaPoints, $radiusMeters = null)
    {
        $radiusMeters = $radiusMeters === null ? (float) ROUTE_MAP_DESTINATION_AREA_METERS : (float) $radiusMeters;

        $area = [];
        foreach ($areaPoints as $areaPoint) {
            if (!is_array($areaPoint) || !isset($areaPoint['lat'], $areaPoint['lng'])) {
                continue;
            }
            $area[] = [(float) $areaPoint['lat'], (float) $areaPoint['lng']];
        }

        if (empty($area) || empty($points)) {
            return [];
        }

        $segmentCount = count($area) - 1;
        $kept = [];
        $runIndex = -1;
        $previousIndex = null;

        foreach ($points as $index => $point) {
            $lat = isset($point['latitude']) ? (float) $point['latitude'] : null;
            $lng = isset($point['longitude']) ? (float) $point['longitude'] : null;
            if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng)) {
                continue;
            }

            $minDistance = INF;
            if ($segmentCount <= 0) {
                $minDistance = passengerRouteMapPointToSegmentMeters($lat, $lng, $area[0][0], $area[0][1], $area[0][0], $area[0][1]);
            } else {
                for ($i = 0; $i < $segmentCount; $i++) {
                    $distance = passengerRouteMapPointToSegmentMeters(
                        $lat,
                        $lng,
                        $area[$i][0],
                        $area[$i][1],
                        $area[$i + 1][0],
                        $area[$i + 1][1]
                    );
                    if ($distance < $minDistance) {
                        $minDistance = $distance;
                    }
                    if ($minDistance <= $radiusMeters) {
                        break;
                    }
                }
            }

            if ($minDistance > $radiusMeters) {
                continue;
            }

            if ($previousIndex === null || (int) $index !== (int) $previousIndex + 1) {
                $runIndex++;
            }

            $point['run'] = $runIndex;
            $kept[] = $point;
            $previousIndex = $index;
        }

        return $kept;
    }
}

if (!function_exists('passengerRouteMapPointInsideArea')) {
    /**
     * Whether a coordinate falls inside the destination area: within the
     * corridor radius of the polyline through the area's reference points
     * ({lat,lng} entries). A single point becomes a radius around it.
     *
     * @param array<int,array<string,mixed>> $areaPoints
     */
    function passengerRouteMapPointInsideArea($latitude, $longitude, array $areaPoints, $radiusMeters = null)
    {
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;
        if (!is_finite($latitude) || !is_finite($longitude)) {
            return false;
        }

        $radiusMeters = $radiusMeters === null ? (float) ROUTE_MAP_DESTINATION_AREA_METERS : (float) $radiusMeters;

        $area = [];
        foreach ($areaPoints as $areaPoint) {
            if (!is_array($areaPoint) || !isset($areaPoint['lat'], $areaPoint['lng'])) {
                continue;
            }
            $lat = (float) $areaPoint['lat'];
            $lng = (float) $areaPoint['lng'];
            if (is_finite($lat) && is_finite($lng)) {
                $area[] = [$lat, $lng];
            }
        }

        if (empty($area)) {
            return false;
        }

        if (count($area) === 1) {
            $distance = passengerRouteMapPointToSegmentMeters(
                $latitude,
                $longitude,
                $area[0][0],
                $area[0][1],
                $area[0][0],
                $area[0][1]
            );
            return is_finite($distance) && $distance <= $radiusMeters + 0.5;
        }

        for ($i = 0, $n = count($area) - 1; $i < $n; $i++) {
            $distance = passengerRouteMapPointToSegmentMeters(
                $latitude,
                $longitude,
                $area[$i][0],
                $area[$i][1],
                $area[$i + 1][0],
                $area[$i + 1][1]
            );
            if (is_finite($distance) && $distance <= $radiusMeters + 0.5) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('passengerBuildRouteMapCoverage')) {
    /**
     * Build the read-only map payload for the trip's stored destination.
     *
     * @param array<string,mixed> $trip Active passenger_trips row (plus trip route_name when available)
     * @return array<string,mixed> success payload or a structured failure
     */
    function passengerBuildRouteMapCoverage($conn, array $trip)
    {
        $destinationId = (int) ($trip['selected_destination_id'] ?? 0);
        $destinationName = trim((string) ($trip['selected_destination'] ?? ''));

        if ($destinationId <= 0) {
            $destinationId = (int) ($trip['destination_id'] ?? 0);
        }
        if ($destinationName === '') {
            $destinationName = trim((string) ($trip['destination'] ?? ''));
        }

        if ($destinationId <= 0 && $destinationName === '') {
            return passengerRouteMapFailure('NO_DESTINATION', 'Select a destination to see the map.');
        }

        $routeName = '';
        $tripNo = (int) ($trip['trip_no'] ?? 0);
        if ($tripNo > 0) {
            try {
                $stmt = $conn->prepare("SELECT route_name FROM bus_trips WHERE id = ? LIMIT 1");
                $stmt->execute([$tripNo]);
                $routeName = trim((string) $stmt->fetchColumn());
            } catch (\Throwable $e) {
                $routeName = '';
            }
        }
        if ($routeName === '') {
            $routeName = trim((string) ($trip['route_name'] ?? ''));
        }

        // The bus trip's own number (distinct from passenger_trips.trip_no,
        // which is the bus trip id). Optional: older schemas may not carry it.
        $tripNumber = null;
        if ($tripNo > 0) {
            try {
                $stmt = $conn->prepare("SELECT trip_number FROM bus_trips WHERE id = ? LIMIT 1");
                $stmt->execute([$tripNo]);
                $tripNumberValue = $stmt->fetchColumn();
                if ($tripNumberValue !== false && $tripNumberValue !== null && $tripNumberValue !== '') {
                    $tripNumber = (string) $tripNumberValue;
                }
            } catch (\Throwable $e) {
                $tripNumber = null;
            }
        }

        if ($routeName === '') {
            return passengerRouteMapFailure('ROUTE_MAP_UNAVAILABLE', 'Route map is not available for this trip right now.');
        }

        try {
            $stops = passengerLoadRouteMapStops($conn, $routeName);
        } catch (\Throwable $e) {
            error_log('Passenger route-map stop load failed: ' . $e->getMessage());
            return passengerRouteMapFailure('ROUTE_MAP_UNAVAILABLE', 'Route map is not available for this trip right now.');
        }

        if (empty($stops)) {
            return passengerRouteMapFailure('ROUTE_MAP_UNAVAILABLE', 'No route stops are available for this trip yet.');
        }

        try {
            $destinationStop = passengerResolveRouteMapDestination($conn, $stops, $destinationId, $destinationName);
            $boardingStop = passengerResolveRouteMapBoarding($conn, $stops, $trip, $routeName);
        } catch (\Throwable $e) {
            error_log('Passenger route-map anchor resolution failed: ' . $e->getMessage());
            return passengerRouteMapFailure('ROUTE_MAP_UNAVAILABLE', 'Route map is not available for this trip right now.');
        }

        if ($destinationStop === null) {
            error_log('Passenger route-map destination not found on route: "' . $destinationName . '" (id ' . $destinationId . ') route "' . $routeName . '"');
            return passengerRouteMapFailure('ROUTE_MAP_UNAVAILABLE', 'Route map is not available for this destination.');
        }

        if ($boardingStop === null) {
            return passengerRouteMapFailure('ROUTE_MAP_UNAVAILABLE', 'Route map is not available for this trip right now.');
        }

        $boardingIndex = null;
        $destinationIndex = null;
        foreach ($stops as $index => $stop) {
            $stopId = (int) ($stop['id'] ?? 0);
            if ($stopId > 0 && $stopId === (int) ($boardingStop['id'] ?? 0)) {
                $boardingIndex = $index;
            }
            if ($stopId > 0 && $stopId === (int) ($destinationStop['id'] ?? 0)) {
                $destinationIndex = $index;
            }
        }

        if ($boardingIndex === null || $destinationIndex === null) {
            return passengerRouteMapFailure('ROUTE_MAP_UNAVAILABLE', 'Route map is not available for this trip right now.');
        }

        // Destination-selection rules already reject stops behind the boarding
        // point; if the stored data disagrees, never draw a reversed segment.
        if ($destinationIndex < $boardingIndex) {
            error_log('Passenger route-map destination precedes boarding point for trip ' . (int) ($trip['id'] ?? 0));
            return passengerRouteMapFailure('ROUTE_MAP_UNAVAILABLE', 'Route map is not available for this destination.');
        }

        $directionKey = passengerStopCoordsDirectionKey($routeName);

        $covered = [];
        for ($index = $boardingIndex; $index <= $destinationIndex; $index++) {
            $payload = passengerRouteMapStopPayload($stops[$index], $directionKey);
            $payload['is_boarding'] = ($index === $boardingIndex);
            $payload['is_destination'] = ($index === $destinationIndex);
            $covered[] = $payload;
        }

        $boardingPayload = null;
        $destinationPayload = null;
        $plottable = false;
        foreach ($covered as $stop) {
            if ($stop['is_boarding']) {
                $boardingPayload = $stop;
            }
            if ($stop['is_destination']) {
                $destinationPayload = $stop;
            }
            if ($stop['latitude'] !== null && $stop['longitude'] !== null) {
                $plottable = true;
            }
        }

        if (
            $destinationPayload === null ||
            $destinationPayload['latitude'] === null ||
            $destinationPayload['longitude'] === null ||
            !$plottable
        ) {
            error_log('Passenger route-map coordinates unavailable for route "' . $routeName . '" (destination "' . $destinationName . '")');
            return passengerRouteMapFailure('ROUTE_MAP_UNAVAILABLE', 'Route map coordinates are not available for this destination yet.');
        }

        // Destination geographic area: every reference coordinate of the
        // selected destination. When no reference entry matches, the resolved
        // destination anchor coordinate is used as a single-point area so the
        // map still shows a destination-sized view instead of the whole route.
        $destinationArea = null;
        $areaSource = null;

        // TEMPORARY DIAGNOSTICS (remove once the live geometry is confirmed).
        $geometryDiagnostics = [
            'area_candidates' => [],
            'area_matched' => false,
            'area_source' => null,
            'route_id' => null,
            'route_id_source' => null,
            'route_points_total' => 0,
            'route_points_kept' => 0,
            'area_buffer_meters' => (float) ROUTE_MAP_DESTINATION_AREA_METERS,
            'routes_rows' => [],
            'route_points_by_route' => [],
            'error' => null,
        ];

        $areaCandidates = [];
        if ($directionKey !== null) {
            $fareDestinationName = passengerResolveRouteMapDestinationName($conn, $destinationId, $destinationName);
            if ($fareDestinationName !== '') {
                $areaCandidates[] = $fareDestinationName;
            }
        }

        $anchorName = trim((string) ($destinationStop['stop_name'] ?? ''));
        if ($anchorName !== '') {
            $areaCandidates[] = $anchorName;
        }

        $geometryDiagnostics['area_candidates'] = $areaCandidates;

        foreach ($areaCandidates as $candidate) {
            if ($directionKey === null) {
                break;
            }
            $candidateArea = passengerStopCoordsPoints($candidate, $directionKey);
            if (!empty($candidateArea)) {
                $destinationArea = $candidateArea;
                $areaSource = 'reference';
                $geometryDiagnostics['area_matched'] = true;
                break;
            }
        }

        if ($destinationArea === null) {
            $anchorLat = $destinationPayload['latitude'];
            $anchorLng = $destinationPayload['longitude'];
            if ($anchorLat !== null && $anchorLng !== null) {
                $destinationArea = [['lat' => (float) $anchorLat, 'lng' => (float) $anchorLng]];
                $areaSource = 'anchor';
            }
        }

        $geometryDiagnostics['area_source'] = $areaSource;

        try {
            $geometryDiagnostics['routes_rows'] = passengerRouteMapRouteRows($conn);
            $geometryDiagnostics['route_points_by_route'] = passengerRouteMapPointCounts($conn);
        } catch (\Throwable $e) {
            // Diagnostics only; ignore.
        }

        // Named places inside the destination area for the map search: the
        // existing route stops, deduplicated by name and kept in route order.
        $areaPlaces = [];
        if ($destinationArea !== null) {
            $seenPlaceKeys = [];
            foreach (passengerRouteMapFilterPointsByArea($stops, $destinationArea) as $placeStop) {
                $placeName = trim((string) ($placeStop['stop_name'] ?? ''));
                if ($placeName === '') {
                    continue;
                }

                $placeLatitude = (float) $placeStop['latitude'];
                $placeLongitude = (float) $placeStop['longitude'];
                $placeKey = strtolower($placeName) . '|' . $placeLatitude . '|' . $placeLongitude;
                if (isset($seenPlaceKeys[$placeKey])) {
                    continue;
                }
                $seenPlaceKeys[$placeKey] = true;

                $areaPlaces[] = [
                    'name' => $placeName,
                    'latitude' => $placeLatitude,
                    'longitude' => $placeLongitude,
                    'stop_id' => isset($placeStop['id']) ? (int) $placeStop['id'] : null,
                ];
            }
        }

        // The planned get-off point is optional and independent of the
        // official destination; it is only restorable when its columns exist.
        $plannedDropoff = null;
        if (
            isset($trip['planned_dropoff_lat'], $trip['planned_dropoff_lng'])
            && $trip['planned_dropoff_lat'] !== null
            && $trip['planned_dropoff_lng'] !== null
        ) {
            $plannedDropoff = [
                'latitude' => (float) $trip['planned_dropoff_lat'],
                'longitude' => (float) $trip['planned_dropoff_lng'],
                'name' => isset($trip['planned_dropoff_name']) && $trip['planned_dropoff_name'] !== null && $trip['planned_dropoff_name'] !== ''
                    ? (string) $trip['planned_dropoff_name']
                    : null,
                'set_at' => $trip['planned_dropoff_set_at'] ?? null,
            ];
        }

        // Visible street geometry: existing route_points inside the destination
        // area. When no usable street geometry exists the map shows only the
        // destination area outline; it never falls back to the covered segment
        // or the full route.
        $routePoints = [];

        if ($destinationArea !== null) {
            try {
                $routeId = passengerRouteMapRouteId($conn, $routeName);
                if ($routeId !== null) {
                    $geometryDiagnostics['route_id_source'] = 'routes_name';
                } else {
                    $routeId = passengerRouteMapRouteIdFromPoints($conn, $stops);
                    if ($routeId !== null) {
                        $geometryDiagnostics['route_id_source'] = 'route_points_heuristic';
                    }
                }

                $geometryDiagnostics['route_id'] = $routeId;

                $allRoutePoints = passengerLoadRouteMapPoints($conn, $routeName, $routeId);
                $geometryDiagnostics['route_points_total'] = count($allRoutePoints);
                if (!empty($allRoutePoints)) {
                    $filteredPoints = passengerRouteMapFilterPointsByArea($allRoutePoints, $destinationArea);
                    $geometryDiagnostics['route_points_kept'] = count($filteredPoints);
                    if (count($filteredPoints) >= 2) {
                        $routePoints = $filteredPoints;
                    }
                }
            } catch (\Throwable $e) {
                $geometryDiagnostics['error'] = $e->getMessage();
                error_log('Passenger route-map route_points load failed: ' . $e->getMessage());
            }
        }

        $geometrySource = !empty($routePoints)
            ? 'route_points'
            : ($destinationArea !== null ? 'destination_area' : 'none');

        // Trip details for the fullscreen information panel: the selected
        // destination, boarding point, bus, and fare already stored on the
        // trip. Read-only; never used for fare or drop-off decisions.
        $tripInfo = [
            'route_name' => $routeName,
            'destination' => $destinationPayload['stop_name'] ?? null,
            'boarding' => $boardingPayload['stop_name'] ?? null,
            'bus_no' => isset($trip['bus_no']) && $trip['bus_no'] !== null && $trip['bus_no'] !== ''
                ? (string) $trip['bus_no']
                : null,
            'trip_number' => $tripNumber,
            'fare' => isset($trip['estimated_fare']) && $trip['estimated_fare'] !== null && $trip['estimated_fare'] !== ''
                ? (float) $trip['estimated_fare']
                : ((isset($trip['fare']) && $trip['fare'] !== null && $trip['fare'] !== '') ? (float) $trip['fare'] : null),
        ];

        return [
            'success' => true,
            'route_name' => $routeName,
            'trip_info' => $tripInfo,
            'geometry_source' => $geometrySource,
            'destination_area' => $destinationArea === null ? [] : $destinationArea,
            'destination_area_radius_meters' => (float) ROUTE_MAP_DESTINATION_AREA_METERS,
            'area_places' => $areaPlaces,
            'planned_dropoff' => $plannedDropoff,
            'geometry_diagnostics' => $geometryDiagnostics,
            'route_points' => $routePoints,
            'boarding' => $boardingPayload,
            'destination' => $destinationPayload,
            'covered_stops' => $covered,
        ];
    }
}

<?php

/**
 * Bus-position boarding selection for the One-Time passenger flow.
 *
 * The bus's matched route segment decides the boarding stop (the supplied
 * rule: bus "at" a stop's zone boards at that stop), and the trip's ordered
 * fare stops decide which destinations remain ahead. Coordinates come from the
 * existing centralized reference in DestinationRouteGeometry.php - the same
 * PASSENGER_DESTINATION_ROUTE_SEGMENTS table the destination-alert geometry
 * uses - so the route data is declared once.
 *
 * Ordering (ahead/behind) comes from the trip's own ordered stop list
 * (fare_pricing.display_order), never from the reference table's row order, so
 * the reference's non-monotonic segments cannot flip the stop sequence.
 */

if (!defined('BUS_SEGMENT_MATCH_MAX_KM')) {
    /**
     * Absolute ceiling (km) for the bus's offset from a route segment to be
     * accepted as "on the route". Aligned with the existing
     * ROUTE_STOP_MATCH_HARD_CAP_KM used by the regular passenger GPS match.
     */
    define('BUS_SEGMENT_MATCH_MAX_KM', 2.0);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/DestinationRouteGeometry.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/BoardingStopOrder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/services/BusPositionService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/canonicalRouteName.php';

if (!function_exists('passengerBusDirectionKey')) {
    /**
     * The reference direction for a trip's route name, or null when the route
     * is not one of the known corridors. A null direction is a refusal, never
     * a fallback onto another route's coordinates.
     */
    function passengerBusDirectionKey($routeName)
    {
        $canonical = canonicalRouteName($routeName);

        if ($canonical === '' || !isset(PASSENGER_DESTINATION_ROUTE_SEGMENTS[$canonical])) {
            return null;
        }

        return $canonical;
    }
}

if (!function_exists('passengerBusReferenceIndex')) {
    /**
     * The index of the raw reference entry for a stop name in a direction:
     * normalized exact match first, then the first containment match (live
     * names are often shorter than the reference key). Null when nothing
     * matches.
     */
    function passengerBusReferenceIndex($directionKey, $stopName)
    {
        if (!isset(PASSENGER_DESTINATION_ROUTE_SEGMENTS[$directionKey])) {
            return null;
        }

        $needle = passengerDestinationRouteNormalizeName($stopName);
        if ($needle === '') {
            return null;
        }

        $containment = null;

        foreach (PASSENGER_DESTINATION_ROUTE_SEGMENTS[$directionKey] as $index => $entry) {
            $candidate = passengerDestinationRouteNormalizeName($entry['name'] ?? '');
            if ($candidate === '') {
                continue;
            }

            if ($candidate === $needle) {
                return (int) $index;
            }

            if (
                $containment === null
                && (strpos($candidate, $needle) !== false || strpos($needle, $candidate) !== false)
            ) {
                $containment = (int) $index;
            }
        }

        return $containment;
    }
}

if (!function_exists('passengerBusReferenceEntry')) {
    /**
     * The raw reference entry for a stop name in a direction, or null when
     * nothing matches.
     *
     * @return array<string,mixed>|null
     */
    function passengerBusReferenceEntry($directionKey, $stopName)
    {
        $index = passengerBusReferenceIndex($directionKey, $stopName);

        if ($index === null) {
            return null;
        }

        return PASSENGER_DESTINATION_ROUTE_SEGMENTS[$directionKey][$index] ?? null;
    }
}

if (!function_exists('passengerBusDirectionLeadSegments')) {
    /**
     * The direction's leading reference segments that precede the first
     * ordered stop. The final leg of a trip's origin (for example the
     * Rizal/Pob Sur Terminal -> Del Pilar stretch of a Rizal -> Cabanatuan
     * trip) is real route coverage but is not always a fare stop of its own;
     * these segments let the bus at the origin match the first ordered stop
     * instead of being rejected as off-route.
     *
     * @param array<int,array<string,mixed>> $orderedStops
     * @return array<int,array{start_lat:float,start_lng:float,end_lat:float,end_lng:float,source:string}>
     */
    function passengerBusDirectionLeadSegments($directionKey, array $orderedStops)
    {
        if (!$orderedStops || !isset(PASSENGER_DESTINATION_ROUTE_SEGMENTS[$directionKey])) {
            return [];
        }

        $orderedStops = array_values($orderedStops);
        $firstIndex = passengerBusReferenceIndex($directionKey, (string) ($orderedStops[0]['name'] ?? ''));

        if ($firstIndex === null || $firstIndex < 1) {
            return [];
        }

        $segments = [];

        foreach (array_slice(PASSENGER_DESTINATION_ROUTE_SEGMENTS[$directionKey], 0, $firstIndex) as $entry) {
            if (isset($entry['point'])) {
                $lat = (float) $entry['point'][0];
                $lng = (float) $entry['point'][1];

                $segments[] = [
                    'start_lat' => $lat,
                    'start_lng' => $lng,
                    'end_lat' => $lat,
                    'end_lng' => $lng,
                    'source' => 'reference',
                ];
                continue;
            }

            if (isset($entry['start'], $entry['end'])) {
                $segments[] = [
                    'start_lat' => (float) $entry['start'][0],
                    'start_lng' => (float) $entry['start'][1],
                    'end_lat' => (float) $entry['end'][0],
                    'end_lng' => (float) $entry['end'][1],
                    'source' => 'reference',
                ];
            }
        }

        return $segments;
    }
}

if (!function_exists('passengerBusStopSegment')) {
    /**
     * The geometric segment for one ordered stop: its reference segment when
     * the reference covers it, otherwise a zero-length segment at the stop's
     * own route_stops coordinate (the reconciliation fallback).
     *
     * @param array<string,mixed> $stop Ordered stop with name plus optional
     *                                   fallback_lat / fallback_lng.
     * @return array{start_lat:float,start_lng:float,end_lat:float,end_lng:float,source:string}|null
     */
    function passengerBusStopSegment(array $stop, $directionKey)
    {
        $entry = passengerBusReferenceEntry($directionKey, (string) ($stop['name'] ?? ''));

        if ($entry !== null) {
            if (isset($entry['point'])) {
                $lat = (float) $entry['point'][0];
                $lng = (float) $entry['point'][1];

                return [
                    'start_lat' => $lat,
                    'start_lng' => $lng,
                    'end_lat' => $lat,
                    'end_lng' => $lng,
                    'source' => 'reference',
                ];
            }

            if (isset($entry['start'], $entry['end'])) {
                return [
                    'start_lat' => (float) $entry['start'][0],
                    'start_lng' => (float) $entry['start'][1],
                    'end_lat' => (float) $entry['end'][0],
                    'end_lng' => (float) $entry['end'][1],
                    'source' => 'reference',
                ];
            }
        }

        $fallbackLat = $stop['fallback_lat'] ?? null;
        $fallbackLng = $stop['fallback_lng'] ?? null;

        if (
            is_numeric($fallbackLat) && is_numeric($fallbackLng)
            && is_finite((float) $fallbackLat) && is_finite((float) $fallbackLng)
        ) {
            return [
                'start_lat' => (float) $fallbackLat,
                'start_lng' => (float) $fallbackLng,
                'end_lat' => (float) $fallbackLat,
                'end_lng' => (float) $fallbackLng,
                'source' => 'route_stops',
            ];
        }

        return null;
    }
}

if (!function_exists('passengerBusPointToSegmentKm')) {
    /**
     * Distance in km from a point to a segment, via the local equirectangular
     * projection the destination-alert geometry uses. A zero-length segment is
     * a plain point distance.
     */
    function passengerBusPointToSegmentKm($lat, $lng, $startLat, $startLng, $endLat, $endLng)
    {
        $refLat = deg2rad((((float) $startLat) + ((float) $endLat)) / 2);

        $bx = ($endLng - $startLng) * 111320.0 * cos($refLat);
        $by = ($endLat - $startLat) * 110540.0;
        $px = ($lng - $startLng) * 111320.0 * cos($refLat);
        $py = ($lat - $startLat) * 110540.0;

        $lengthSquared = ($bx * $bx) + ($by * $by);
        $t = $lengthSquared > 0 ? (($px * $bx) + ($py * $by)) / $lengthSquared : 0.0;
        $t = max(0.0, min(1.0, $t));

        $projectedLat = $startLat + (($endLat - $startLat) * $t);
        $projectedLng = $startLng + (($endLng - $startLng) * $t);

        return passengerDestinationHaversineMeters($lat, $lng, $projectedLat, $projectedLng) / 1000.0;
    }
}

if (!function_exists('passengerMatchBusPositionToOrderedStops')) {
    /**
     * The ordered stop whose route segment is nearest the bus position.
     *
     * Deterministic: the first stop wins an exact distance tie, and a stop
     * with no usable segment is skipped rather than guessed. Returns null when
     * the position is invalid or the nearest segment is farther than $maxKm.
     *
     * @param array<int,array<string,mixed>> $orderedStops
     * @return array{index:int,distance_km:float,segment_source:string}|null
     */
    function passengerMatchBusPositionToOrderedStops(array $orderedStops, $directionKey, $lat, $lng, $maxKm = null)
    {
        $maxKm = $maxKm === null ? (float) BUS_SEGMENT_MATCH_MAX_KM : (float) $maxKm;

        if (
            !is_numeric($lat) || !is_numeric($lng)
            || !is_finite((float) $lat) || !is_finite((float) $lng)
            || (float) $lat < -90 || (float) $lat > 90
            || (float) $lng < -180 || (float) $lng > 180
        ) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        $bestIndex = null;
        $bestDistance = PHP_FLOAT_MAX;
        $bestSource = 'reference';

        $leadSegments = passengerBusDirectionLeadSegments($directionKey, $orderedStops);

        foreach (array_values($orderedStops) as $index => $stop) {
            $candidates = [];
            $segment = passengerBusStopSegment($stop, $directionKey);

            if ($segment !== null) {
                $candidates[] = $segment;
            }

            // The origin stretch before the first ordered stop counts as that
            // stop's zone, so a bus at the starting terminal is not rejected.
            if ($index === 0) {
                foreach ($leadSegments as $lead) {
                    $candidates[] = $lead;
                }
            }

            foreach ($candidates as $candidate) {
                $distance = passengerBusPointToSegmentKm(
                    $lat,
                    $lng,
                    $candidate['start_lat'],
                    $candidate['start_lng'],
                    $candidate['end_lat'],
                    $candidate['end_lng']
                );

                if ($distance < $bestDistance) {
                    $bestIndex = $index;
                    $bestDistance = $distance;
                    $bestSource = $candidate['source'];
                }
            }
        }

        if ($bestIndex === null || $bestDistance > $maxKm) {
            return null;
        }

        return [
            'index' => $bestIndex,
            'distance_km' => $bestDistance,
            'segment_source' => $bestSource,
        ];
    }
}

if (!function_exists('passengerBusRouteStopFallbacks')) {
    /**
     * The route's route_stops rows, keyed for reconciliation with the fare
     * stop list: by fare_pricing_id and by normalized stop name. Rows are
     * matched to the trip's route canonically so the "Cabanatuan to Rizal
     * Route" convention resolves against a "Cabanatuan-Rizal" trip.
     *
     * @return array{by_fare:array<int,array<string,mixed>>,by_name:array<string,array<string,mixed>>}
     */
    function passengerBusRouteStopFallbacks(PDO $conn, $routeName)
    {
        $byFare = [];
        $byName = [];
        $canonical = canonicalRouteName($routeName);

        if ($canonical === '') {
            return ['by_fare' => $byFare, 'by_name' => $byName];
        }

        try {
            $stmt = $conn->query("
                SELECT id, route_name, stop_name, sequence_order, km_marker, fare_pricing_id, latitude, longitude
                FROM route_stops
                ORDER BY sequence_order ASC, id ASC
            ");
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) {
            return ['by_fare' => $byFare, 'by_name' => $byName];
        }

        foreach ($rows as $row) {
            if (canonicalRouteName($row['route_name'] ?? '') !== $canonical) {
                continue;
            }

            $fareId = (int) ($row['fare_pricing_id'] ?? 0);
            if ($fareId > 0 && !isset($byFare[$fareId])) {
                $byFare[$fareId] = $row;
            }

            $normalized = passengerDestinationRouteNormalizeName($row['stop_name'] ?? '');
            if ($normalized !== '' && !isset($byName[$normalized])) {
                $byName[$normalized] = $row;
            }
        }

        return ['by_fare' => $byFare, 'by_name' => $byName];
    }
}

if (!function_exists('passengerBusOrderedStopsWithFallbacks')) {
    /**
     * The trip's ordered fare stops enriched for matching: each stop carries
     * its route_stops id/km/coordinates when a row resolves by fare id first,
     * then by normalized name. Stops the reference does not cover still match
     * through their own route_stops coordinate.
     *
     * @param array<int,array<string,mixed>> $routeStops resolveRouteStops() rows (id, destination, display_order)
     * @param array{by_fare:array<int,array<string,mixed>>,by_name:array<string,array<string,mixed>>} $fallbacks
     * @return array<int,array<string,mixed>>
     */
    function passengerBusOrderedStopsWithFallbacks(array $routeStops, array $fallbacks)
    {
        $ordered = [];

        foreach ($routeStops as $stop) {
            $fareId = (int) ($stop['id'] ?? 0);
            $name = trim((string) ($stop['destination'] ?? ''));

            $row = $fallbacks['by_fare'][$fareId] ?? null;
            if ($row === null && $name !== '') {
                $row = $fallbacks['by_name'][passengerDestinationRouteNormalizeName($name)] ?? null;
            }

            $ordered[] = [
                'fare_id' => $fareId,
                'name' => $name,
                'display_order' => (int) ($stop['display_order'] ?? 0),
                'route_stop_id' => $row ? (int) $row['id'] : null,
                'km_marker' => $row && $row['km_marker'] !== null ? (int) $row['km_marker'] : null,
                'fallback_lat' => $row ? (is_numeric($row['latitude']) ? (float) $row['latitude'] : null) : null,
                'fallback_lng' => $row ? (is_numeric($row['longitude']) ? (float) $row['longitude'] : null) : null,
            ];
        }

        return $ordered;
    }
}

if (!function_exists('passengerBusPositionPayload')) {
    /**
     * The wire shape of a resolved bus position for the trip-info payload.
     *
     * @param array<string,mixed> $position
     * @return array<string,mixed>
     */
    function passengerBusPositionPayload(array $position)
    {
        return [
            'available' => (bool) ($position['available'] ?? false),
            'usable' => (bool) ($position['usable'] ?? false),
            'reason' => $position['reason'] ?? null,
            'source' => $position['source'] ?? null,
            'latitude' => $position['latitude'] ?? null,
            'longitude' => $position['longitude'] ?? null,
            'speed' => $position['speed'] ?? null,
            'updated_at' => $position['updated_at'] ?? null,
            'age_seconds' => $position['age_seconds'] ?? null,
            'freshness' => $position['freshness'] ?? null,
            'matched_distance_km' => null,
        ];
    }
}

if (!function_exists('passengerResolveBusBoarding')) {
    /**
     * The bus-location-derived boarding choice for a trip: the stop of the
     * matched route segment, the stops that remain ahead of it in the trip's
     * display order, or the reason the choice cannot be made.
     *
     * @param array<string,mixed> $busTrip bus_trips row (route_name, bus_id, driver_id)
     * @param array<int,array<string,mixed>> $routeStops Ordered fare stops (resolveRouteStops rows)
     * @param array<string,mixed>|null $position Pre-resolved position to reuse (confirm-time revalidation)
     * @return array{position:array<string,mixed>,boarding:?array<string,mixed>,destinations:array<int,array<string,mixed>>,direction:?string,error:?array{code:string,message:string}}
     */
    function passengerResolveBusBoarding(PDO $conn, array $busTrip, array $routeStops, $position = null)
    {
        $directionKey = passengerBusDirectionKey((string) ($busTrip['route_name'] ?? ''));
        $resolved = $position !== null ? $position : passengerBusPositionForTrip($conn, $busTrip);

        $result = [
            'position' => passengerBusPositionPayload($resolved),
            'boarding' => null,
            'destinations' => [],
            'direction' => $directionKey,
            'error' => null,
        ];

        if (!$routeStops) {
            $result['error'] = [
                'code' => 'NO_ROUTE_STOPS',
                'message' => 'No stops are available for this trip. Please contact the driver.',
            ];

            return $result;
        }

        if ($directionKey === null) {
            $result['error'] = [
                'code' => 'ROUTE_UNSUPPORTED',
                'message' => 'This route is not supported for GPS boarding yet.',
            ];

            return $result;
        }

        if (
            empty($resolved['usable'])
            || !isset($resolved['latitude'], $resolved['longitude'])
            || !is_numeric($resolved['latitude']) || !is_numeric($resolved['longitude'])
        ) {
            $reason = (string) ($resolved['reason'] ?? '');

            $result['error'] = $reason === 'stale'
                ? [
                    'code' => 'BUS_LOCATION_STALE',
                    'message' => 'The bus location is out of date. Please retry in a moment.',
                ]
                : [
                    'code' => 'BUS_LOCATION_UNAVAILABLE',
                    'message' => 'We could not get the bus\'s current location. Please retry in a moment.',
                ];

            return $result;
        }

        $fallbacks = passengerBusRouteStopFallbacks($conn, (string) ($busTrip['route_name'] ?? ''));
        $ordered = passengerBusOrderedStopsWithFallbacks($routeStops, $fallbacks);
        $match = passengerMatchBusPositionToOrderedStops(
            $ordered,
            $directionKey,
            (float) $resolved['latitude'],
            (float) $resolved['longitude']
        );

        if ($match === null) {
            $result['error'] = [
                'code' => 'BUS_OFF_ROUTE',
                'message' => 'The bus position could not be matched to the route. Please retry in a moment.',
            ];

            return $result;
        }

        $result['position']['matched_distance_km'] = round((float) $match['distance_km'], 4);

        $boardingStop = $ordered[$match['index']];
        $result['boarding'] = [
            'id' => (int) $boardingStop['fare_id'],
            'stop_id' => $boardingStop['route_stop_id'] !== null ? (int) $boardingStop['route_stop_id'] : null,
            'destination' => (string) $boardingStop['name'],
            'display_order' => (int) $boardingStop['display_order'],
            'km_marker' => $boardingStop['km_marker'] !== null ? (int) $boardingStop['km_marker'] : null,
            'latitude' => passengerBusStopCoordinate($directionKey, $boardingStop, 'fallback_lat'),
            'longitude' => passengerBusStopCoordinate($directionKey, $boardingStop, 'fallback_lng'),
        ];

        foreach (array_slice($ordered, $match['index'] + 1) as $stop) {
            $result['destinations'][] = [
                'id' => (int) $stop['fare_id'],
                'destination' => (string) $stop['name'],
                'display_order' => (int) $stop['display_order'],
                'km_marker' => $stop['km_marker'] !== null ? (int) $stop['km_marker'] : null,
                'latitude' => passengerBusStopCoordinate($directionKey, $stop, 'fallback_lat'),
                'longitude' => passengerBusStopCoordinate($directionKey, $stop, 'fallback_lng'),
            ];
        }

        // The boarding stop itself is always offered as the local option
        // (same-stop, minimum fare) — the bus is at it by definition — and it
        // is the only option at the end of the trip, where it is flagged for
        // preselection so the flow still completes.
        if ($result['boarding'] !== null) {
            $localOption = [
                'id' => (int) $result['boarding']['id'],
                'destination' => (string) $result['boarding']['destination'],
                'display_order' => (int) $result['boarding']['display_order'],
                'km_marker' => $result['boarding']['km_marker'],
                'latitude' => $result['boarding']['latitude'],
                'longitude' => $result['boarding']['longitude'],
                'is_boarding' => true,
            ];

            if ($result['destinations'] === []) {
                $localOption['same_as_boarding'] = true;
            }

            array_unshift($result['destinations'], $localOption);
        }

        return $result;
    }
}

if (!function_exists('passengerBusStopCoordinate')) {
    /**
     * A stop's display coordinate: its route_stops value when present, else
     * the reference anchor for the stop's name.
     *
     * @param array<string,mixed> $stop
     * @return float|null
     */
    function passengerBusStopCoordinate($directionKey, array $stop, $key)
    {
        if (isset($stop[$key]) && is_numeric($stop[$key])) {
            return (float) $stop[$key];
        }

        $anchor = passengerDestinationRouteAnchorByName($directionKey, (string) ($stop['name'] ?? ''));

        if ($anchor === null) {
            return null;
        }

        return $key === 'fallback_lat' ? (float) $anchor['lat'] : (float) $anchor['lng'];
    }
}

<?php

/**
 * Planned get-off point persistence for the destination map.
 *
 * Validates a chosen point against the selected destination's geographic area
 * (the same area the map draws) and stores it on the trip. This is the only
 * map feature that writes, and it touches only the planned get-off columns:
 * the official destination, fare, payment, trip destination, and
 * drop-off/geofence behavior are never changed.
 */

if (function_exists('passengerSavePlannedDropoff')) {
    return;
}

require_once __DIR__ . '/RouteMapService.php';

if (!function_exists('passengerDropoffNearestPlaceName')) {
    /**
     * Name shown for a selected drop-off point: the nearest named in-area stop,
     * or the destination name when the area has no named stops. Route points
     * carry no names of their own.
     *
     * @param array<int,array<string,mixed>> $places
     */
    function passengerDropoffNearestPlaceName(array $places, $latitude, $longitude)
    {
        $bestName = null;
        $bestDistance = INF;

        foreach ($places as $place) {
            if (!is_array($place) || !isset($place['latitude'], $place['longitude'], $place['name'])) {
                continue;
            }

            $placeLat = (float) $place['latitude'];
            $placeLng = (float) $place['longitude'];
            if (!is_finite($placeLat) || !is_finite($placeLng)) {
                continue;
            }

            $distance = passengerRouteMapPointToSegmentMeters(
                (float) $latitude,
                (float) $longitude,
                $placeLat,
                $placeLng,
                $placeLat,
                $placeLng
            );

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestName = trim((string) $place['name']);
            }
        }

        return ($bestName !== null && $bestName !== '') ? $bestName : null;
    }
}

if (!function_exists('passengerSavePlannedDropoff')) {
    /**
     * @param array<string,mixed> $trip Active passenger_trips row
     * @return array<string,mixed> success payload or structured failure
     */
    function passengerSavePlannedDropoff($conn, array $trip, $latitude, $longitude)
    {
        $latitude = is_numeric($latitude) ? (float) $latitude : null;
        $longitude = is_numeric($longitude) ? (float) $longitude : null;

        if (
            $latitude === null || $longitude === null
            || !is_finite($latitude) || !is_finite($longitude)
            || $latitude < -90 || $latitude > 90
            || $longitude < -180 || $longitude > 180
        ) {
            return [
                'success' => false,
                'code' => 'INVALID_POINT',
                'message' => 'A valid map point is required.',
            ];
        }

        try {
            $coverage = passengerBuildRouteMapCoverage($conn, $trip);
        } catch (\Throwable $e) {
            error_log('Passenger get-off point coverage failed: ' . $e->getMessage());
            return [
                'success' => false,
                'code' => 'SERVER_ERROR',
                'message' => 'We could not save your get-off point right now. Please try again.',
            ];
        }

        if (!is_array($coverage) || ($coverage['success'] ?? false) !== true) {
            return [
                'success' => false,
                'code' => (string) ($coverage['code'] ?? 'DESTINATION_UNAVAILABLE'),
                'message' => (string) ($coverage['message'] ?? 'Select a destination before choosing a get-off point.'),
            ];
        }

        $area = isset($coverage['destination_area']) && is_array($coverage['destination_area'])
            ? $coverage['destination_area']
            : [];
        $radius = isset($coverage['destination_area_radius_meters']) && is_numeric($coverage['destination_area_radius_meters'])
            ? (float) $coverage['destination_area_radius_meters']
            : (float) ROUTE_MAP_DESTINATION_AREA_METERS;

        if (empty($area)) {
            return [
                'success' => false,
                'code' => 'DESTINATION_AREA_UNAVAILABLE',
                'message' => 'The destination area is not available right now, so the get-off point cannot be saved.',
            ];
        }

        if (!passengerRouteMapPointInsideArea($latitude, $longitude, $area, $radius)) {
            return [
                'success' => false,
                'code' => 'POINT_OUTSIDE_AREA',
                'message' => 'Choose a point inside the highlighted destination area.',
                'destination_area' => $area,
                'destination_area_radius_meters' => $radius,
            ];
        }

        $tripId = (int) ($trip['id'] ?? 0);
        if ($tripId <= 0) {
            return [
                'success' => false,
                'code' => 'NO_ACTIVE_TRIP',
                'message' => 'No in-progress trip found.',
            ];
        }

        // A human-readable name for the driver/dispatcher feed: the nearest
        // named stop inside the destination area, or the destination itself.
        $dropoffName = passengerDropoffNearestPlaceName(
            isset($coverage['area_places']) && is_array($coverage['area_places']) ? $coverage['area_places'] : [],
            $latitude,
            $longitude
        );
        if ($dropoffName === null) {
            $dropoffName = isset($coverage['trip_info']['destination']) && $coverage['trip_info']['destination'] !== ''
                ? (string) $coverage['trip_info']['destination']
                : null;
        }

        try {
            $stmt = $conn->prepare("
                UPDATE passenger_trips
                SET planned_dropoff_lat = ?,
                    planned_dropoff_lng = ?,
                    planned_dropoff_name = ?,
                    planned_dropoff_set_at = ?
                WHERE id = ?
            ");
            $stmt->execute([$latitude, $longitude, $dropoffName, date('Y-m-d H:i:s'), $tripId]);
        } catch (\Throwable $e) {
            // Degraded save when the optional name column is unavailable; the
            // coordinates are the essential part of the planned point.
            try {
                $stmt = $conn->prepare("
                    UPDATE passenger_trips
                    SET planned_dropoff_lat = ?,
                        planned_dropoff_lng = ?,
                        planned_dropoff_set_at = ?
                    WHERE id = ?
                ");
                $stmt->execute([$latitude, $longitude, date('Y-m-d H:i:s'), $tripId]);
            } catch (\Throwable $retryError) {
                error_log('Passenger get-off point save failed: ' . $retryError->getMessage());
                return [
                    'success' => false,
                    'code' => 'PERSIST_FAILED',
                    'message' => 'We could not save your get-off point right now. Please try again.',
                ];
            }
        }

        return [
            'success' => true,
            'trip_id' => $tripId,
            'planned_dropoff' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'name' => $dropoffName,
            ],
        ];
    }
}

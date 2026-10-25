<?php

/*
|--------------------------------------------------------------------------
| NEAR-DESTINATION PASSENGER ALERT (shared library)
|
| Read-only helper. For each onboard passenger it resolves the passenger's
| intended destination stop on the active trip's route to route_stops
| coordinates and measures the haversine distance from the driver's latest
| driver_current_location row.
|
| Destination precedence (existing recorded data only):
|   1. recorded destination   (destination_id / destination)
|   2. passenger-selected     (selected_destination_id / selected_destination)
|   3. server-resolved actual (actual_stop_id / actual_destination / actual_stop_name)
|
| The mapping reuses the route_stops.fare_pricing_id bridge and the fuzzy
| fare_pricing matching from actual_destination.php. No writes, no schema
| changes, and no fare computation happen here.
|--------------------------------------------------------------------------
*/

if (!defined('NEAR_STOP_ALERT_THRESHOLD_M')) {
    define('NEAR_STOP_ALERT_THRESHOLD_M', 500.0);
}

if (!defined('NEAR_STOP_LOCATION_MAX_AGE_S')) {
    define('NEAR_STOP_LOCATION_MAX_AGE_S', 600); // stale fixes must not alert
}

/*
|--------------------------------------------------------------------------
| SHARED HELPERS
|
| In the deployed mobile/driver/lib/ directory actual_destination.php is a
| sibling. During repository work it can also be reached through scripts/lib.
| Fallback definitions keep this file self-contained if it is ever loaded
| without the shared library.
|--------------------------------------------------------------------------
*/

if (!function_exists('route_stops_route_name_for_trip')) {
    foreach ([
        __DIR__ . '/actual_destination.php',
        __DIR__ . '/../../scripts/lib/actual_destination.php',
        __DIR__ . '/../../mobile/driver/lib/actual_destination.php',
    ] as $nearStopSharedLib) {
        if (is_file($nearStopSharedLib)) {
            require_once $nearStopSharedLib;
            break;
        }
    }
}

if (!function_exists('near_stop_normalize_text')) {

    /** Lowercase, punctuation-free text used for stop-name matching. */
    function near_stop_normalize_text($value)
    {
        if (function_exists('normalize_dest_text')) {
            return normalize_dest_text($value);
        }
        $value = strtolower((string)$value);
        $value = str_replace([', nueva ecija', ', cabanatuan city', ', cabanatuan', ' city'], '', $value);
        $value = preg_replace('/[^a-z0-9 ]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}

if (!function_exists('near_stop_haversine_meters')) {

    /** Great-circle distance in meters. */
    function near_stop_haversine_meters($lat1, $lng1, $lat2, $lng2)
    {
        if (function_exists('haversine_meters')) {
            return haversine_meters($lat1, $lng1, $lat2, $lng2);
        }
        $R = 6371000;
        $dLat = ((float)$lat2 - (float)$lat1) * M_PI / 180;
        $dLng = ((float)$lng2 - (float)$lng1) * M_PI / 180;
        $a = sin($dLat / 2) ** 2
            + cos((float)$lat1 * M_PI / 180) * cos((float)$lat2 * M_PI / 180) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

if (!function_exists('near_stop_valid_coords')) {

    /** Whether the pair is a usable coordinate (not null/zero/non-numeric). */
    function near_stop_valid_coords($lat, $lng)
    {
        return $lat !== null && $lng !== null
            && is_numeric($lat) && is_numeric($lng)
            && (float)$lat !== 0.0 && (float)$lng !== 0.0;
    }
}

/*
|--------------------------------------------------------------------------
| PUBLIC API
|--------------------------------------------------------------------------
*/

if (!function_exists('near_stop_get_threshold_m')) {

    /** The near-destination alert threshold in meters (500 m default). */
    function near_stop_get_threshold_m()
    {
        return (float)NEAR_STOP_ALERT_THRESHOLD_M;
    }
}

if (!function_exists('near_stop_driver_location')) {

    /**
     * Latest usable driver location for the near-stop rule. Stale fixes are
     * ignored so a backgrounded phone never produces a false "near" alert.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    function near_stop_driver_location($conn, $driverId)
    {
        if (!$conn || $driverId === '' || $driverId === null) return null;

        $maxAge = (int)NEAR_STOP_LOCATION_MAX_AGE_S;
        $stmt = $conn->prepare(
            "SELECT latitude, longitude
             FROM driver_current_location
             WHERE driver_id = ?
               AND (updated_at IS NULL OR updated_at >= (NOW() - INTERVAL {$maxAge} SECOND))
             ORDER BY updated_at DESC
             LIMIT 1"
        );
        if (!$stmt) {
            // Older schema without updated_at still yields the last known fix.
            // This must not reference updated_at either, or the fallback fails
            // to prepare on exactly the schema it exists to serve, and the
            // function degrades to null exactly as it would with no fallback.
            // Ordering by the primary key expresses "most recent known fix"
            // without naming the timestamp column.
            $stmt = $conn->prepare(
                "SELECT latitude, longitude
                 FROM driver_current_location
                 WHERE driver_id = ?
                 ORDER BY id DESC
                 LIMIT 1"
            );
        }
        if (!$stmt) return null;

        $stmt->bind_param("s", $driverId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !near_stop_valid_coords($row['latitude'] ?? null, $row['longitude'] ?? null)) {
            return null;
        }

        return [
            'latitude' => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
        ];
    }
}

if (!function_exists('near_stop_prepare_context')) {

    /**
     * Loads, once per request, everything needed to evaluate every onboard
     * passenger of one trip: the mapped route_stops row set, the fare_pricing
     * rows, and the driver's latest location.
     *
     * @return array
     */
    function near_stop_prepare_context($conn, $tripRouteName, $driverId)
    {
        $ctx = [
            'threshold_m' => near_stop_get_threshold_m(),
            'route_name' => null,
            'stops_by_id' => [],
            'stops_by_fare_id' => [],
            'stops_by_norm_name' => [],
            'fare_by_id' => [],
            'fare_rows' => [],
            'driver' => null,
            'usable' => false,
        ];

        if (!$conn) return $ctx;

        $ctx['driver'] = near_stop_driver_location($conn, $driverId);

        $routeName = $tripRouteName;
        if (function_exists('route_stops_route_name_for_trip')) {
            $resolved = route_stops_route_name_for_trip($conn, $tripRouteName);
            if ($resolved) $routeName = $resolved;
        }
        if ($routeName === null || $routeName === '') return $ctx;
        $ctx['route_name'] = $routeName;

        $stmt = $conn->prepare(
            "SELECT id, route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id
             FROM route_stops
             WHERE route_name = ?
             ORDER BY sequence_order ASC"
        );
        if ($stmt) {
            $stmt->bind_param("s", $routeName);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['latitude'] = (float)$row['latitude'];
                $row['longitude'] = (float)$row['longitude'];
                $stopId = (int)$row['id'];
                $ctx['stops_by_id'][$stopId] = $row;

                $fareId = (int)($row['fare_pricing_id'] ?? 0);
                if ($fareId > 0 && !isset($ctx['stops_by_fare_id'][$fareId])) {
                    $ctx['stops_by_fare_id'][$fareId] = $row;
                }

                $norm = near_stop_normalize_text($row['stop_name'] ?? '');
                if ($norm !== '' && !isset($ctx['stops_by_norm_name'][$norm])) {
                    $ctx['stops_by_norm_name'][$norm] = $row;
                }
            }
            $stmt->close();
        }

        if (function_exists('load_fare_pricing')) {
            $ctx['fare_rows'] = load_fare_pricing($conn, $tripRouteName);
            if (!$ctx['fare_rows'] && $routeName !== $tripRouteName) {
                $ctx['fare_rows'] = load_fare_pricing($conn, $routeName);
            }
            foreach ($ctx['fare_rows'] as $fareRow) {
                $ctx['fare_by_id'][(int)$fareRow['id']] = $fareRow;
            }
        }

        $ctx['usable'] = !empty($ctx['stops_by_id']);
        return $ctx;
    }
}

if (!function_exists('near_stop_match_candidate')) {

    /**
     * Maps one destination reference (fare_pricing id and/or stop text) to a
     * route_stops row, preferring the authoritative fare_pricing_id bridge.
     *
     * @return array|null route_stops row
     */
    function near_stop_match_candidate($ctx, $fareId, $name)
    {
        $fareId = is_numeric($fareId) ? (int)$fareId : 0;
        $name = is_string($name) ? trim($name) : '';

        if ($fareId > 0) {
            if (isset($ctx['stops_by_fare_id'][$fareId])) {
                return $ctx['stops_by_fare_id'][$fareId];
            }
            if ($name === '' && isset($ctx['fare_by_id'][$fareId])) {
                $name = (string)$ctx['fare_by_id'][$fareId]['destination'];
            }
        }
        if ($name === '') return null;

        // Fuzzy fare_pricing match first, then its bridge to route_stops.
        if (!empty($ctx['fare_rows']) && function_exists('find_fare_pricing_row')) {
            $fareRow = find_fare_pricing_row($ctx['fare_rows'], $name);
            if ($fareRow) {
                $mappedFareId = (int)$fareRow['id'];
                if (isset($ctx['stops_by_fare_id'][$mappedFareId])) {
                    return $ctx['stops_by_fare_id'][$mappedFareId];
                }
                $fareNameNorm = near_stop_normalize_text($fareRow['destination'] ?? '');
                if ($fareNameNorm !== '' && isset($ctx['stops_by_norm_name'][$fareNameNorm])) {
                    return $ctx['stops_by_norm_name'][$fareNameNorm];
                }
            }
        }

        $norm = near_stop_normalize_text($name);
        if ($norm === '') return null;
        if (isset($ctx['stops_by_norm_name'][$norm])) {
            return $ctx['stops_by_norm_name'][$norm];
        }

        // Partial match only when it identifies exactly one stop, so a fuzzy
        // name can never point the alert at the wrong destination.
        $partialMatches = [];
        foreach ($ctx['stops_by_id'] as $stop) {
            $stopNorm = near_stop_normalize_text($stop['stop_name'] ?? '');
            if ($stopNorm === '') continue;
            if (strpos($norm, $stopNorm) !== false || strpos($stopNorm, $norm) !== false) {
                $partialMatches[$stop['id']] = $stop;
            }
        }
        if (count($partialMatches) === 1) {
            return reset($partialMatches);
        }

        return null;
    }
}

if (!function_exists('near_stop_resolve_passenger_stop')) {

    /**
     * Resolves the passenger's intended destination route stop.
     *
     * @param array $ctx from near_stop_prepare_context()
     * @param array $row passenger_trips row (missing optional columns allowed)
     * @return array|null route_stops row
     */
    function near_stop_resolve_passenger_stop($ctx, $row)
    {
        if (empty($ctx['stops_by_id'])) return null;

        $candidates = [];
        $push = function ($id, $name) use (&$candidates) {
            $name = is_string($name) ? trim($name) : '';
            $id = is_numeric($id) ? (int)$id : 0;
            if ($id <= 0 && $name === '') return;
            $candidates[] = ['id' => $id, 'name' => $name];
        };

        // 1. Recorded destination.
        $push($row['destination_id'] ?? null, $row['destination'] ?? null);

        // 2. Passenger-selected destination (recorded before QR drop-off writes
        //    passenger_trips.destination).
        $push($row['selected_destination_id'] ?? null, $row['selected_destination'] ?? null);

        // 3. Server-resolved actual stop. actual_stop_id is a route_stops id.
        $actualStopId = is_numeric($row['actual_stop_id'] ?? null) ? (int)$row['actual_stop_id'] : 0;
        if ($actualStopId > 0 && isset($ctx['stops_by_id'][$actualStopId])) {
            return $ctx['stops_by_id'][$actualStopId];
        }
        $push(0, $row['actual_stop_name'] ?? null);
        $push(0, $row['actual_destination'] ?? null);

        foreach ($candidates as $candidate) {
            $stop = near_stop_match_candidate($ctx, $candidate['id'], $candidate['name']);
            if ($stop) return $stop;
        }

        return null;
    }
}

if (!function_exists('near_stop_row_fields')) {

    /**
     * Per-passenger response fields. Defaults mean "not near" so consumers can
     * treat a missing/unmapped destination or an unavailable location safely.
     *
     * @return array
     */
    function near_stop_row_fields($ctx, $row)
    {
        $fields = [
            'destination_stop_id' => null,
            'destination_stop_name' => null,
            'destination_latitude' => null,
            'destination_longitude' => null,
            'distance_to_destination_m' => null,
            'near_destination' => false,
        ];

        if (empty($ctx['usable'])) return $fields;

        $stop = near_stop_resolve_passenger_stop($ctx, $row);
        if (!$stop) return $fields;

        $lat = (float)($stop['latitude'] ?? 0);
        $lng = (float)($stop['longitude'] ?? 0);

        $fields['destination_stop_id'] = (int)$stop['id'];
        $fields['destination_stop_name'] = (string)$stop['stop_name'];

        if (!near_stop_valid_coords($lat, $lng)) {
            return $fields;
        }
        $fields['destination_latitude'] = $lat;
        $fields['destination_longitude'] = $lng;

        if (!empty($ctx['driver'])) {
            $distance = near_stop_haversine_meters(
                $ctx['driver']['latitude'],
                $ctx['driver']['longitude'],
                $lat,
                $lng
            );
            $fields['distance_to_destination_m'] = round($distance, 1);
            $fields['near_destination'] = $distance <= (float)$ctx['threshold_m'];
        }

        return $fields;
    }
}

<?php

/*
|--------------------------------------------------------------------------
| SHORT-DISTANCE EARLY DROP-OFF (shared library)
|
| Server-authoritative rule for the short-distance passenger cancellation:
|   distance = haversine(recorded boarding GPS, actual drop-off GPS)
|   threshold = administrator-configurable system setting (default 1.0 km)
|
| The Driver never supplies the distance. GPS provenance is tracked so a
| synthetic fallback position is never mistaken for a real capture.
|--------------------------------------------------------------------------
*/

if (!function_exists('short_distance_get_threshold_km')) {

    /**
     * Reads the configurable short-distance threshold. Falls back to 1.0 km
     * when the settings table/row is missing or the stored value is invalid,
     * so the rule keeps working before/without the migration.
     *
     * @param mysqli|null $conn
     * @return float
     */
    function short_distance_get_threshold_km($conn)
    {
        $default = 1.0;
        if (!$conn) return $default;

        $table = $conn->query("SHOW TABLES LIKE 'system_settings'");
        if (!$table || $table->num_rows === 0) return $default;

        // A legacy table may exist with a different shape; only query when the
        // columns we need are actually present.
        $hasKey = false;
        $hasValue = false;
        $cols = $conn->query("SHOW COLUMNS FROM system_settings");
        if ($cols) {
            while ($c = $cols->fetch_assoc()) {
                if ($c['Field'] === 'setting_key') $hasKey = true;
                if ($c['Field'] === 'setting_value') $hasValue = true;
            }
        }
        if (!$hasKey || !$hasValue) return $default;

        $stmt = $conn->prepare(
            "SELECT setting_value FROM system_settings
             WHERE setting_key = 'short_distance_early_dropoff_km' LIMIT 1"
        );
        if (!$stmt) return $default;

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) return $default;
        $value = (float)$row['setting_value'];
        return $value > 0 ? $value : $default;
    }
}

if (!function_exists('short_distance_haversine_km')) {

    /**
     * Great-circle distance in kilometers between two coordinates.
     */
    function short_distance_haversine_km($lat1, $lng1, $lat2, $lng2)
    {
        $R = 6371;
        $dLat = ((float)$lat2 - (float)$lat1) * M_PI / 180;
        $dLng = ((float)$lng2 - (float)$lng1) * M_PI / 180;
        $a = sin($dLat / 2) ** 2
            + cos((float)$lat1 * M_PI / 180) * cos((float)$lat2 * M_PI / 180) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

if (!function_exists('short_distance_valid_coords')) {

    /** Whether the pair is a usable real coordinate (not null/zero). */
    function short_distance_valid_coords($lat, $lng)
    {
        return $lat !== null && $lng !== null
            && is_numeric($lat) && is_numeric($lng)
            && (float)$lat !== 0.0 && (float)$lng !== 0.0;
    }
}

if (!function_exists('short_distance_resolve_dropoff_location')) {

    /**
     * Resolves the actual drop-off position following the existing R-Connect
     * fallback order:
     *   1. the position submitted by the client (fresh GPS -> last known ->
     *      client fallback constant),
     *   2. the latest server-known driver location,
     *   3. the shared fallback constant with status `pending`.
     *
     * @return array { latitude, longitude, location_status, source, fallback_used }
     */
    function short_distance_resolve_dropoff_location($conn, $driverId, $lat, $lng, $status)
    {
        $status = strtolower(trim((string)$status));
        if (!in_array($status, ['captured', 'pending'], true)) {
            $status = 'pending';
        }

        if (short_distance_valid_coords($lat, $lng)) {
            return [
                'latitude' => (float)$lat,
                'longitude' => (float)$lng,
                'location_status' => $status,
                'source' => 'request',
                'fallback_used' => $status !== 'captured',
            ];
        }

        if ($driverId !== '') {
            $stmt = $conn->prepare(
                "SELECT latitude, longitude FROM driver_current_location
                 WHERE driver_id = ? ORDER BY updated_at DESC LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param("s", $driverId);
                $stmt->execute();
                $loc = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($loc && short_distance_valid_coords($loc['latitude'], $loc['longitude'])) {
                    return [
                        'latitude' => (float)$loc['latitude'],
                        'longitude' => (float)$loc['longitude'],
                        'location_status' => 'captured',
                        'source' => 'driver_current_location',
                        'fallback_used' => false,
                    ];
                }
            }
        }

        // Same constants the driver app uses when GPS is unavailable.
        return [
            'latitude' => 15.4823,
            'longitude' => 120.975,
            'location_status' => 'pending',
            'source' => 'fallback_constant',
            'fallback_used' => true,
        ];
    }
}

if (!function_exists('short_distance_evaluate')) {

    /**
     * Applies the short-distance rule.
     *
     * Eligibility requires a computable distance and at least one endpoint
     * captured from a real location. When BOTH endpoints are fallback/untrusted
     * the constant-to-constant distance is not a real traveled distance, so the
     * fare is not auto-waived and the event must be reviewed.
     *
     * @return array {
     *   distance_km: float|null,
     *   threshold_km: float,
     *   eligible: bool,
     *   gps_fallback_used: bool,
     *   both_endpoints_untrusted: bool,
     *   verification_status: string
     * }
     */
    function short_distance_evaluate(
        $boardingLat,
        $boardingLng,
        $boardingStatus,
        $dropoffLat,
        $dropoffLng,
        $dropoffStatus,
        $thresholdKm
    ) {
        $boardingCaptured = short_distance_valid_coords($boardingLat, $boardingLng)
            && strtolower(trim((string)$boardingStatus)) === 'captured';
        $dropoffCaptured = short_distance_valid_coords($dropoffLat, $dropoffLng)
            && strtolower(trim((string)$dropoffStatus)) === 'captured';

        $gpsFallbackUsed = !($boardingCaptured && $dropoffCaptured);
        $bothUntrusted = !$boardingCaptured && !$dropoffCaptured;

        $distance = null;
        if (short_distance_valid_coords($boardingLat, $boardingLng)
            && short_distance_valid_coords($dropoffLat, $dropoffLng)) {
            $distance = round(short_distance_haversine_km($boardingLat, $boardingLng, $dropoffLat, $dropoffLng), 3);
        }

        $eligible = $distance !== null && !$bothUntrusted && $distance <= (float)$thresholdKm;

        return [
            'distance_km' => $distance,
            'threshold_km' => (float)$thresholdKm,
            'eligible' => $eligible,
            'gps_fallback_used' => $gpsFallbackUsed,
            'both_endpoints_untrusted' => $bothUntrusted,
            'verification_status' => $gpsFallbackUsed ? 'needs_review' : 'auto_verified',
        ];
    }
}

if (!function_exists('short_distance_write_audit')) {

    /**
     * Writes the accountability record for a short-distance cancellation
     * attempt (eligible, beyond-limit, or needs-verification). Returns false
     * without throwing when the audit table is not present yet, so the
     * drop-off itself is never blocked by a missing audit table.
     *
     * @param mysqli $conn
     * @param array  $data
     * @return bool
     */
    function short_distance_write_audit($conn, array $data)
    {
        $table = $conn->query("SHOW TABLES LIKE 'early_dropoff_audit'");
        if (!$table || $table->num_rows === 0) return false;

        $passengerTripId = (int)($data['passenger_trip_id'] ?? 0);
        $passengerId = isset($data['passenger_id']) && $data['passenger_id'] !== null
            ? (int)$data['passenger_id'] : null;
        $passengerCode = (string)($data['passenger_code'] ?? '');
        $tripNo = (string)($data['trip_no'] ?? '');
        $busNo = (string)($data['bus_no'] ?? '');
        $driverId = (string)($data['driver_id'] ?? '');
        $boardingPoint = (string)($data['boarding_point'] ?? '');
        $originalDestination = $data['original_destination'] ?? null;
        $currentDestination = $data['current_destination'] ?? null;
        $actualDropoffPoint = $data['actual_dropoff_point'] ?? null;
        $boardingLatitude = isset($data['boarding_latitude']) ? (float)$data['boarding_latitude'] : null;
        $boardingLongitude = isset($data['boarding_longitude']) ? (float)$data['boarding_longitude'] : null;
        $boardingLocationStatus = (string)($data['boarding_location_status'] ?? '');
        $dropoffLatitude = isset($data['dropoff_latitude']) ? (float)$data['dropoff_latitude'] : null;
        $dropoffLongitude = isset($data['dropoff_longitude']) ? (float)$data['dropoff_longitude'] : null;
        $dropoffLocationStatus = (string)($data['dropoff_location_status'] ?? '');
        $distanceTraveledKm = $data['distance_traveled_km'] !== null ? (float)$data['distance_traveled_km'] : null;
        $thresholdKm = isset($data['threshold_km']) ? (float)$data['threshold_km'] : null;
        $reason = (string)($data['reason'] ?? '');
        $fareBefore = $data['fare_before'] !== null ? (float)$data['fare_before'] : null;
        $finalFare = $data['final_fare'] !== null ? (float)$data['final_fare'] : null;
        $cancellationStatus = (string)($data['cancellation_status'] ?? '');
        $gpsFallbackUsed = !empty($data['gps_fallback_used']) ? 1 : 0;
        $verificationStatus = (string)($data['verification_status'] ?? 'auto_verified');

        $stmt = $conn->prepare(
            "INSERT INTO early_dropoff_audit
             (passenger_trip_id, passenger_id, passenger_code, trip_no, bus_no, driver_id,
              boarding_point, original_destination, current_destination, actual_dropoff_point,
              boarding_latitude, boarding_longitude, boarding_location_status,
              dropoff_latitude, dropoff_longitude, dropoff_location_status,
              distance_traveled_km, threshold_km, reason, fare_before, final_fare,
              cancellation_status, gps_fallback_used, verification_status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        if (!$stmt) return false;

        $stmt->bind_param(
            "iissssssssddsddsddsddsis",
            $passengerTripId,
            $passengerId,
            $passengerCode,
            $tripNo,
            $busNo,
            $driverId,
            $boardingPoint,
            $originalDestination,
            $currentDestination,
            $actualDropoffPoint,
            $boardingLatitude,
            $boardingLongitude,
            $boardingLocationStatus,
            $dropoffLatitude,
            $dropoffLongitude,
            $dropoffLocationStatus,
            $distanceTraveledKm,
            $thresholdKm,
            $reason,
            $fareBefore,
            $finalFare,
            $cancellationStatus,
            $gpsFallbackUsed,
            $verificationStatus
        );

        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

<?php

if (!function_exists('resolve_qr_fare')) {

    /**
     * Resolves the boarding stop, drop-off/destination stop, and fare for a
     * QR-scan passenger on a route, using the SAME business rules as
     * save_manual_passenger.php / calculate_fare_by_gps.php:
     *
     *   - fare = max(destination_fare - boarding_fare, 15) per passenger type
     *   - boarding stop === destination stop  =>  ₱15 (local trip)
     *   - first stop (display_order 1) triple  =>  destination_fare
     *   - passenger type: student/senior/pwd => student_fare
     *                      special/teacher/nurse => special_fare
     *                      default => regular_fare
     *
     * Note: the live fare_pricing schema has no per-stop coordinates, so the
     * GPS destination matching is best-effort: when stops expose stop_lat /
     * stop_lng they are honored, otherwise the destination resolves by boarding
     * order (next stop after boarding, or last stop when boarding first).
     *
     * @param mysqli $conn
     * @param string $routeName
     * @param string $boardingPoint  Boarding stop name (fare_pricing.destination).
     * @param float  $lat            Driver latitude.
     * @param float  $lng            Driver longitude.
     * @param string $type           Passenger type (lowercase: regular/student/senior/pwd/special/teacher/nurse).
     * @return array|null            { boarding, boarding_id, boarding_order, destination,
     *                                destination_id, destination_order, fare } or null when the
     *                                route has no fare_pricing rows.
     */
    function resolve_qr_fare($conn, $routeName, $boardingPoint, $lat, $lng, $type)
    {
        $stmt = $conn->prepare("SELECT id, destination, display_order, regular_fare, student_fare, special_fare FROM fare_pricing WHERE route_name = ? ORDER BY display_order ASC");
        if (!$stmt) return null;
        $stmt->bind_param("s", $routeName);
        $stmt->execute();
        $routeStops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!$routeStops || count($routeStops) === 0) return null;

        // Boarding stop (default to first stop of the route).
        $boardIdx = 0;
        foreach ($routeStops as $i => $s) {
            if (strtolower((string)$s['destination']) === strtolower(trim((string)$boardingPoint))) {
                $boardIdx = $i;
                break;
            }
        }
        $boardStop = $routeStops[$boardIdx];

        // Destination stop: check Live GPS first (within allowed radius), then Fallback GPS (within allowed radius).
        // Never use the last/final stop as default!
        $destIdx = -1;
        $ALLOWED_RADIUS_KM = 3.0;
        $FALLBACK_DRIVER_LATITUDE = 15.4823;
        $FALLBACK_DRIVER_LONGITUDE = 120.975;

        if ($lat !== null && $lng !== null && (float)$lat !== 0.0 && (float)$lng !== 0.0) {
            $bestDist = $ALLOWED_RADIUS_KM;
            foreach ($routeStops as $i => $s) {
                $sLat = $s['stop_lat'] ?? null;
                $sLng = $s['stop_lng'] ?? null;
                if ($sLat === null || $sLng === null || (float)$sLat === 0.0 || (float)$sLng === 0.0) continue;
                $stopDist = haversine_km((float)$lat, (float)$lng, (float)$sLat, (float)$sLng);
                if ($stopDist === null) continue;
                if ($stopDist < $bestDist) { $bestDist = $stopDist; $destIdx = $i; }
            }
        }

        // If no stop detected from live GPS within radius, check fallback GPS
        if ($destIdx < 0) {
            $fallbackDist = $ALLOWED_RADIUS_KM;
            foreach ($routeStops as $i => $s) {
                $sLat = $s['stop_lat'] ?? null;
                $sLng = $s['stop_lng'] ?? null;
                if ($sLat === null || $sLng === null || (float)$sLat === 0.0 || (float)$sLng === 0.0) continue;
                $stopDist = haversine_km($FALLBACK_DRIVER_LATITUDE, $FALLBACK_DRIVER_LONGITUDE, (float)$sLat, (float)$sLng);
                if ($stopDist === null) continue;
                if ($stopDist < $fallbackDist) { $fallbackDist = $stopDist; $destIdx = $i; }
            }
        }

        if ($destIdx < 0) {
            // Neither GPS source matched a stop within radius. Never use the last/final stop as a default.
            $destIdx = min($boardIdx + 1, count($routeStops) - 1);
        }

        // Effective destination can never be before the boarding stop.
        if ($destIdx < $boardIdx) {
            $destIdx = $boardIdx === count($routeStops) - 1 ? $boardIdx : $boardIdx + 1;
        }

        $destStop = $routeStops[$destIdx];

        switch ($type) {
            case 'student': case 'senior': case 'pwd':
                $boardingFare = (float)$boardStop['student_fare'];
                $destinationFare = (float)$destStop['student_fare'];
                break;
            case 'special': case 'teacher': case 'nurse':
                $boardingFare = (float)$boardStop['special_fare'];
                $destinationFare = (float)$destStop['special_fare'];
                break;
            default:
                $boardingFare = (float)$boardStop['regular_fare'];
                $destinationFare = (float)$destStop['regular_fare'];
        }

        if ($boardStop['id'] == $destStop['id']) {
            $fare = 15;
        } elseif ((int)$boardStop['display_order'] === 1) {
            $fare = $destinationFare;
        } else {
            $computedFare = $destinationFare - $boardingFare;
            $fare = $computedFare < 15 ? 15 : $computedFare;
        }
        $fare = round($fare, 2);

        return [
            'boarding' => $boardStop['destination'],
            'boarding_id' => (int)$boardStop['id'],
            'boarding_order' => (int)$boardStop['display_order'],
            'destination' => $destStop['destination'],
            'destination_id' => (int)$destStop['id'],
            'destination_order' => (int)$destStop['display_order'],
            'fare' => $fare,
        ];
    }
}

if (!function_exists('haversine_km')) {

    /**
     * Great-circle distance between two coordinates.
     *
     * Returns null when fare_pricing has no coordinates for a stop.
     */
    function haversine_km($lat1, $lng1, $lat2, $lng2)
    {
        if (empty($lat2) || empty($lng2)) return null;
        $R = 6371;
        $dLat = ((float)$lat2 - (float)$lat1) * M_PI / 180;
        $dLng = ((float)$lng2 - (float)$lng1) * M_PI / 180;
        $a = sin($dLat / 2) ** 2 + cos((float)$lat1 * M_PI / 180) * cos((float)$lat2 * M_PI / 180) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
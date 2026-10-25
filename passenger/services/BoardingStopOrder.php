<?php

/**
 * Shared passenger-side helpers for resolving a boarding stop to its
 * fare_pricing zone (id + destination + display_order).
 *
 * Primary mapping: the GPS-matched route_stops row's fare_pricing_id (the same
 * bridge calculateFare() uses), accepted only when the mapped fare row belongs
 * to the trip's route direction. Fallback: name matching whose matcher mirrors
 * calculateFare()'s findFareByDestination() so both resolve the same fare row.
 * The zone's display_order is the boarding position used to hide and reject
 * destinations that are at or behind the boarding point.
 */

if (!function_exists('passengerNormalizeStopName')) {
    function passengerNormalizeStopName($value)
    {
        $value = strtolower((string)$value);
        $value = str_replace([
            ', nueva ecija',
            ', cabanatuan city',
            ', cabanatuan',
            ' city'
        ], '', $value);
        $value = preg_replace('/[^a-z0-9 ]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}

if (!function_exists('passengerFareZoneMatchesRoute')) {
    /**
     * Whether a fare zone's route belongs to the trip's route direction.
     *
     * Route names use different conventions across tables (for example
     * "Cabanatuan-Rizal" vs "Cabanatuan to Rizal Route"), so the comparison is
     * canonical. When either side is empty there is nothing to compare and the
     * caller keeps its historical behavior.
     */
    function passengerFareZoneMatchesRoute($fareRoute, $tripRoute)
    {
        $fareRoute = trim((string)$fareRoute);
        $tripRoute = trim((string)$tripRoute);

        if ($fareRoute === '' || $tripRoute === '') {
            return true;
        }

        require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/canonicalRouteName.php';

        return canonicalRouteName($fareRoute) === canonicalRouteName($tripRoute);
    }
}

if (!function_exists('passengerResolveBoardingFareZone')) {
    /**
     * Resolve the boarding stop's fare zone for a trip.
     *
     * The route_stops.fare_pricing_id bridge is used only when the mapped fare
     * row is on the trip's route: a stale or cross-direction mapping would
     * otherwise yield the opposite direction's display_order and mark every
     * stop ahead as "behind" the boarding point. When the mapping is off-route
     * (or missing), the route-scoped name matcher resolves the zone instead.
     *
     * @param int $boardingStopId passenger_trips.boarding_stop_id (route_stops.id)
     * @return array{id:int,destination:string,display_order:int}|null
     */
    function passengerResolveBoardingFareZone($conn, $tripRoute, $boardingPoint, $boardingStopId = 0)
    {
        $boardingStopId = (int)$boardingStopId;

        if ($boardingStopId > 0) {
            try {
                $stmt = $conn->prepare("SELECT fare_pricing_id FROM route_stops WHERE id = ? LIMIT 1");
                $stmt->execute([$boardingStopId]);
                $farePricingId = (int)($stmt->fetchColumn() ?: 0);

                if ($farePricingId > 0) {
                    $stmt = $conn->prepare("SELECT id, destination, display_order, route_name FROM fare_pricing WHERE id = ? LIMIT 1");
                    $stmt->execute([$farePricingId]);
                    $zone = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($zone && passengerFareZoneMatchesRoute($zone['route_name'] ?? '', $tripRoute)) {
                        return $zone;
                    }
                }
            } catch (\PDOException $e) {
                // Fall through to name matching.
            }
        }

        return passengerResolveBoardingFareStop($conn, $tripRoute, $boardingPoint);
    }
}

if (!function_exists('passengerIsSameBoardingLocation')) {
    /**
     * Whether a destination stop name refers to the same location as the
     * boarding stop — same normalized name (or containment), or the name
     * resolves to the boarding stop's own fare zone.
     *
     * @param array<int,string> $boardingNames Boarding point and boarding zone names
     * @param int|null          $boardingFareId Boarding fare zone id
     */
    function passengerIsSameBoardingLocation($conn, $tripRoute, $boardingNames, $boardingFareId, $destinationName)
    {
        $destNorm = passengerNormalizeStopName($destinationName);
        if ($destNorm === '') {
            return false;
        }

        foreach ((array)$boardingNames as $boardingName) {
            $boardNorm = passengerNormalizeStopName($boardingName);
            if ($boardNorm === '') {
                continue;
            }
            if (
                $boardNorm === $destNorm ||
                strpos($boardNorm, $destNorm) !== false ||
                strpos($destNorm, $boardNorm) !== false
            ) {
                return true;
            }
        }

        if ($boardingFareId !== null && (int)$boardingFareId > 0 && trim((string)$tripRoute) !== '') {
            $zone = passengerResolveBoardingFareStop($conn, $tripRoute, $destinationName);
            if ($zone !== null && (int)$zone['id'] === (int)$boardingFareId) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('passengerResolveBoardingFareStop')) {
    /**
     * Resolve the boarding point name to its own fare_pricing row.
     *
     * @return array{id:int,destination:string,display_order:int}|null
     */
    function passengerResolveBoardingFareStop($conn, $tripRoute, $boardingPoint)
    {
        $tripRoute = trim((string)$tripRoute);
        $boardingPoint = trim((string)$boardingPoint);

        if ($tripRoute === '' || $boardingPoint === '') {
            return null;
        }

        require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/canonicalRouteName.php';

        try {
            $stmt = $conn->prepare("
                SELECT id, destination, display_order
                FROM fare_pricing
                WHERE route_name = ?
                ORDER BY display_order ASC
            ");
            $stmt->execute([$tripRoute]);
            $routeStops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $routeStops = [];
        }

        // Exact route name may differ in punctuation/spacing from bus_trips;
        // fall back to a canonical comparison over the fare_pricing table.
        if (empty($routeStops)) {
            try {
                $stmt = $conn->query("
                    SELECT id, route_name, destination, display_order
                    FROM fare_pricing
                    ORDER BY route_name ASC, display_order ASC
                ");
                $allStops = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                return null;
            }

            $tripCanon = canonicalRouteName($tripRoute);
            $routeStops = array_values(array_filter(
                $allStops,
                function ($row) use ($tripCanon) {
                    return canonicalRouteName($row['route_name'] ?? '') === $tripCanon;
                }
            ));
        }

        if (empty($routeStops)) {
            return null;
        }

        $originalValue = strtolower(trim((string)$boardingPoint));
        $boardingNorm = passengerNormalizeStopName($boardingPoint);
        if ($boardingNorm === '') {
            return null;
        }

        // The matching order below mirrors calculateFare()'s
        // findFareByDestination() exactly (exact -> partial -> destination word
        // -> boarding word -> Cabanatuan terminal fallback, first match wins),
        // so the ordering check validates against the same row the fare engine
        // will use. A looser or stricter matcher here would let a selection
        // through whose fare is computed from a different stop.

        // 1. Exact normalized match: the boarding stop's own fare row.
        foreach ($routeStops as $row) {
            if (passengerNormalizeStopName($row['destination']) === $boardingNorm) {
                return $row;
            }
        }

        // 2. Partial match (either direction).
        foreach ($routeStops as $row) {
            $stopNorm = passengerNormalizeStopName($row['destination']);
            if ($stopNorm === '') {
                continue;
            }
            if (
                strpos($boardingNorm, $stopNorm) !== false ||
                strpos($stopNorm, $boardingNorm) !== false
            ) {
                return $row;
            }
        }

        // 3. Destination word match: a fare stop word (>= 4 chars) appears in
        //    the boarding point (e.g. fare "Cabanatuan Central Terminal" vs
        //    boarding "Cabanatuan City Terminal (Main terminal)").
        foreach ($routeStops as $row) {
            $stopNorm = passengerNormalizeStopName($row['destination']);
            foreach (explode(' ', $stopNorm) as $word) {
                if (strlen($word) < 4) {
                    continue;
                }
                if (strpos($boardingNorm, $word) !== false) {
                    return $row;
                }
            }
        }

        // 4. Boarding word match: a boarding point word (>= 4 chars) appears in
        //    the fare stop destination.
        foreach ($routeStops as $row) {
            $stopNorm = passengerNormalizeStopName($row['destination']);
            foreach (explode(' ', $boardingNorm) as $word) {
                if (strlen($word) < 4) {
                    continue;
                }
                if (strpos($stopNorm, $word) !== false) {
                    return $row;
                }
            }
        }

        // 5. Cabanatuan terminal fallback (mirrors the fare engine).
        if (strpos($originalValue, 'cabanatuan') !== false) {
            foreach ($routeStops as $row) {
                if (strpos(strtolower((string)$row['destination']), 'cabanatuan central terminal') !== false) {
                    return $row;
                }
            }
        }

        return null;
    }
}

if (!function_exists('passengerResolveBoardingFarePricingId')) {
    /**
     * @return int|null The boarding stop's own fare_pricing id, or null when
     *                  it cannot be resolved. Passed to calculateFare() as
     *                  $fromId so the fare is based on the true boarding stop.
     */
    function passengerResolveBoardingFarePricingId($conn, $tripRoute, $boardingPoint)
    {
        $stop = passengerResolveBoardingFareStop($conn, $tripRoute, $boardingPoint);
        return $stop !== null && isset($stop['id']) ? (int)$stop['id'] : null;
    }
}

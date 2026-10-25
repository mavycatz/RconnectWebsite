<?php

/**
 * Core backfill logic: populate route_stops.fare_pricing_id.
 *
 * Strategy (per route direction):
 *   1. ANCHORS — route_stops whose name matches a fare_pricing.destination
 *      (exact or contains, digit/roman/alias-aware) define the km → fare zone map.
 *   2. TERMINAL ZONES — the lowest km maps to the display_order-1 fare
 *      (departure terminal), the highest km to the last fare (arrival terminal).
 *   3. ASSIGNMENT per stop (priority):
 *        a. exact name match → that fare
 *        b. contains name match (non-road stops only) → that fare
 *        c. km-zone → the fare of the largest zone km <= its km_marker
 *           (below the first zone → terminal zone fare)
 *      Road waypoints (names containing "junction" or ending in rd/road/hwy)
 *      never hijack a fare that a real stop already names, and only anchor a
 *      fare nobody else names via a strong shared token (e.g. San Ricardo).
 *      Otherwise they are position-only GPS points (e.g. "Junction Pinagpanaan
 *      - Rizal - Pantabangan Rd" appears at many km values and must follow the
 *      km zone, not the road's namesake stop).
 *
 * km_marker is the primary zone signal (hand-set per fare zone: Mayapyap=7,
 * Pinagpanaan=13, Baluga=15, Homestead1=18, Homestead2=19, San Ricardo=20,
 * Gen Luna=25, Bicos=30, Aglipay=34, Del Pilar=35, Rizal=39, mirrored on the
 * return route). If km_marker looks unpopulated (< 3 distinct values in a
 * direction), falls back to name-only matching.
 *
 * $stops: rows with id, route_name, stop_name, km_marker, sequence_order
 * $fares: rows with id, route_name, destination, display_order
 * $updateFn: callable (fareId, stopId) executed for each assignment; when null
 *            the function only computes (dry run).
 *
 * Returns [matched, unmatched, lines[]].
 */

$genericTokens = [
    'street', ' st ', ' rd ', ' road', ' highway', ' hwy', ' junction',
    ' maharlika', ' brgy', ' barangay', ' sitio', ' kanto', ' district',
    ' extension', ' exit', ' arch', ' welcome', ' municipal', ' terminal',
    ' city', ' main', ' nueva ecija', ' talavera', ' llanera', ' rizal',
    ' cabanatuan', ' sto', ' nino'
];

$aliasMap = [
    'daang' => 'daan',
    'gen' => 'general',
    'pinaagpanaan' => 'pinagpanaan',
];

function backfillNormalizeName($value)
{
    global $genericTokens, $aliasMap;

    $value = ' ' . strtolower((string)$value) . ' ';

    $value = str_replace([
        ', nueva ecija', ', cabanatuan city', ', cabanatuan',
        ', talavera', ', llanera', ', rizal',
        ' - ', '-', '/', '&', '.', '(', ')', ',', '7/11', "'",
        ' and ', ' & ', '–', '—'
    ], ' ', $value);

    foreach ($genericTokens as $tok) {
        $value = str_replace(' ' . trim($tok) . ' ', ' ', $value);
    }

    // Roman numerals and aliases run AFTER punctuation/token collapse, so
    // "Homestead II," / "Gen. Luna" / "Pinaagpanaan," normalize like
    // "Homestead 2" / "General Luna" / "Pinagpanaan" (a comma or period
    // directly after the word would block the match otherwise).
    $value = str_replace(
        [' iii ', ' ii ', ' iv ', ' i ', ' v '],
        [' 3 ', ' 2 ', ' 4 ', ' 1 ', ' 5 '],
        $value
    );

    foreach ($aliasMap as $from => $to) {
        $value = str_replace(' ' . $from . ' ', ' ' . $to . ' ', $value);
    }

    $value = preg_replace('/[^a-z0-9 ]/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

function backfillIsRoadWaypoint($rawName)
{
    $v = strtolower(trim((string)$rawName));
    if (strpos($v, 'junction') !== false) {
        return true;
    }
    if (preg_match('/\b(rd|road|highway|hwy)$/', $v)) {
        return true;
    }
    return false;
}

function backfillRouteStopFares(array $stops, array $fares, $updateFn = null)
{
    require_once dirname(__DIR__, 2) . '/fare-engine/utils/canonicalRouteName.php';

    $stopsByRoute = [];
    foreach ($stops as $stop) {
        $canon = canonicalRouteName($stop['route_name']);
        $stopsByRoute[$canon][] = $stop;
    }

    $faresByRoute = [];
    foreach ($fares as $fare) {
        $canon = canonicalRouteName($fare['route_name']);
        $faresByRoute[$canon][] = $fare;
    }

    $matched = 0;
    $unmatched = 0;
    $lines = [];

    foreach ($stopsByRoute as $canon => $routeStops) {

        $routeFares = $faresByRoute[$canon] ?? [];
        if (empty($routeFares)) {
            foreach ($routeStops as $stop) {
                $unmatched++;
                $lines[] = "id={$stop['id']} '{$stop['stop_name']}' -> NO FARE ROUTE '{$stop['route_name']}'";
            }
            continue;
        }

        $kms = array_unique(array_map(fn($s) => (int)$s['km_marker'], $routeStops));
        $kmUsable = count($kms) >= 3;

        // Drop junk fares (zero-fare rows like the stray 'tae' record)
        $routeFares = array_values(array_filter($routeFares, function ($fare) {
            return (float)$fare['regular_fare'] > 0;
        }));

        // Precompute: which fares have a NON-road stop that names them?
        // Road waypoints may only name a fare when NO real stop names it
        // (San Ricardo, Daang Sarile), never when the fare has a real stop
        // (Junction Pinagpanaan must NOT hijack Pinagpanaan's fare).
        $fareHasNonRoadMatch = [];
        foreach ($routeFares as $fare) {
            $fareHasNonRoadMatch[(int)$fare['id']] = false;
        }
        foreach ($routeStops as $stop) {
            if (backfillIsRoadWaypoint($stop['stop_name'])) {
                continue;
            }
            $stopName = backfillNormalizeName($stop['stop_name']);
            if ($stopName === '') {
                continue;
            }
            foreach ($routeFares as $fare) {
                $destName = backfillNormalizeName($fare['destination']);
                if ($destName === '') {
                    continue;
                }
                if (
                    $stopName === $destName ||
                    strpos($stopName, $destName) !== false ||
                    strpos($destName, $stopName) !== false
                ) {
                    $fareHasNonRoadMatch[(int)$fare['id']] = true;
                }
            }
        }

        // PASS A: exact/contains anchors → km zone map
        $zoneMap = [];

        foreach ($routeStops as $stop) {
            $isRoad = backfillIsRoadWaypoint($stop['stop_name']);

            $stopName = backfillNormalizeName($stop['stop_name']);
            if ($stopName === '') {
                continue;
            }

            $bestFare = null;
            $bestScore = 0;

            foreach ($routeFares as $fare) {
                $destName = backfillNormalizeName($fare['destination']);
                if ($destName === '') {
                    continue;
                }

                $score = 0;
                if ($stopName === $destName) {
                    $score = 100;
                } elseif (strpos($stopName, $destName) !== false || strpos($destName, $stopName) !== false) {
                    $score = 50;
                } elseif ($isRoad) {
                    // Token overlap: a road waypoint may anchor a fare when a
                    // strong zone token is shared (e.g. "San Ricardo- Bantug
                    // Hacienda Rd" -> fare "San Ricardo/Casile"). Weak place
                    // tokens never count — otherwise every
                    // "Junction Pinagpanaan - Rizal - Pantabangan Rd" would
                    // anchor the Rizal terminal fare at any km.
                    $weakTokens = ['rizal', 'san', 'pob', 'brgy', 'nino', 'jose'];
                    $stopTokens = preg_split('/\s+/', $stopName);
                    foreach (preg_split('/\s+/', $destName) as $dt) {
                        if (
                            strlen($dt) >= 4 &&
                            !in_array($dt, $weakTokens, true) &&
                            in_array($dt, $stopTokens, true)
                        ) {
                            $score = 30;
                            break;
                        }
                    }
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestFare = $fare;
                }
            }

            $minScore = $isRoad ? 30 : 50;
            if (!$bestFare || $bestScore < $minScore) {
                continue;
            }

            // Road waypoints may only anchor a fare nobody else names
            if ($isRoad && $fareHasNonRoadMatch[(int)$bestFare['id']]) {
                continue;
            }

            $km = (int)$stop['km_marker'];
            if (!isset($zoneMap[$km])) {
                $zoneMap[$km] = (int)$bestFare['id'];
            }
        }

        // PASS B: terminal zones
        $allKm = array_map(fn($s) => (int)$s['km_marker'], $routeStops);
        $minKm = min($allKm);
        $maxKm = max($allKm);

        if (!isset($zoneMap[$minKm])) {
            foreach ($routeFares as $fare) {
                if ((int)$fare['display_order'] === 1) {
                    $zoneMap[$minKm] = (int)$fare['id'];
                    break;
                }
            }
        }

        if (!isset($zoneMap[$maxKm]) && $maxKm !== $minKm) {
            $lastFare = null;
            $lastOrder = -1;
            foreach ($routeFares as $fare) {
                if ((int)$fare['display_order'] > $lastOrder) {
                    $lastOrder = (int)$fare['display_order'];
                    $lastFare = $fare;
                }
            }
            if ($lastFare) {
                $zoneMap[$maxKm] = (int)$lastFare['id'];
            }
        }

        ksort($zoneMap);
        $zoneKms = array_keys($zoneMap);

        // ASSIGN
        foreach ($routeStops as $stop) {
            $stopName = backfillNormalizeName($stop['stop_name']);
            $stopKm = (int)$stop['km_marker'];
            $isRoad = backfillIsRoadWaypoint($stop['stop_name']);

            $fareId = 0;
            $via = '';

            // a) exact name match
            if ($fareId === 0 && $stopName !== '') {
                foreach ($routeFares as $fare) {
                    $destName = backfillNormalizeName($fare['destination']);
                    if ($destName !== '' && $stopName === $destName) {
                        $fareId = (int)$fare['id'];
                        $via = 'exact';
                        break;
                    }
                }
            }

            // b) contains name match (non-road stops only)
            if ($fareId === 0 && $stopName !== '' && !$isRoad) {
                foreach ($routeFares as $fare) {
                    $destName = backfillNormalizeName($fare['destination']);
                    if (
                        $destName !== '' &&
                        (
                            strpos($stopName, $destName) !== false ||
                            strpos($destName, $stopName) !== false
                        )
                    ) {
                        $fareId = (int)$fare['id'];
                        $via = 'contains';
                        break;
                    }
                }
            }

            // c) km-zone assignment
            if ($fareId === 0 && $kmUsable && !empty($zoneMap)) {
                $zoneFare = null;
                foreach ($zoneKms as $zkm) {
                    if ($zkm <= $stopKm) {
                        $zoneFare = $zoneMap[$zkm];
                    } else {
                        break;
                    }
                }
                if ($zoneFare === null) {
                    $zoneFare = $zoneMap[$zoneKms[0]];
                }
                $fareId = $zoneFare;
                $via = 'zone';
            }

            // d) fallback: name-only (km unreliable)
            if ($fareId === 0 && !$kmUsable && $stopName !== '') {
                $bestFare = null;
                $bestScore = 0;
                foreach ($routeFares as $fare) {
                    $destName = backfillNormalizeName($fare['destination']);
                    if ($destName === '') {
                        continue;
                    }
                    $score = 0;
                    if ($stopName === $destName) {
                        $score = 100;
                    } elseif (strpos($stopName, $destName) !== false || strpos($destName, $stopName) !== false) {
                        $score = 50;
                    }
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestFare = $fare;
                    }
                }
                if ($bestFare && $bestScore >= 50) {
                    $fareId = (int)$bestFare['id'];
                    $via = 'name';
                }
            }

            if ($fareId > 0) {
                if ($updateFn !== null) {
                    $updateFn($fareId, $stop['id']);
                }
                $matched++;
                $lines[] = "id={$stop['id']} '{$stop['stop_name']}' (km={$stopKm}) -> fare_pricing_id={$fareId} [{$via}]";
            } else {
                $unmatched++;
                $lines[] = "id={$stop['id']} '{$stop['stop_name']}' (km={$stopKm}) -> UNMATCHED";
            }
        }
    }

    return [$matched, $unmatched, $lines];
}

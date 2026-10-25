<?php

/**
 * Stop detection for the Inspector trip summary.
 *
 * The reported stop is a function of the inspector's coordinates and nothing else.
 * No geocoding service is consulted and no two names are ever compared as strings.
 *
 * ---------------------------------------------------------------------------
 * WHY THERE IS A STOP MAP IN THIS FILE
 * ---------------------------------------------------------------------------
 *
 * `route_stops` is a landmark table: ~64 rows per direction, most of them shops,
 * junctions and street segments rather than fare stops. It carries precise
 * coordinates, but it does NOT know which fare row a landmark corresponds to -
 * `fare_pricing_id` is 0 on every row, which is the table's "unlinked" sentinel,
 * and there is no `geofence_radius_m` column at all.
 *
 * `scripts/sql/set_route_stop_fare_links.sql` writes that link into the database.
 * Until it has been run, this map is what the endpoint uses, so that the fix does
 * not depend on a migration having been applied first. The map and the migration
 * declare the same links; when the migration has run they agree, and the endpoint
 * prefers the database value (see stop_detection_link()).
 *
 * A landmark is identified by (route_name, sequence_order), NOT by stop_name.
 * `sequence_order` is unique within a route, whereas several stop_names repeat:
 * 'Bicos Bridge - 1' is both sequence_order 53 and 55 on Cabanatuan -> Rizal,
 * and 'Junction Pinagpanaan - Rizal - Pantabangan Rd' appears five times. Keying
 * by name would silently pick the wrong landmark.
 *
 * ---------------------------------------------------------------------------
 * KNOWN GAPS - THESE STOPS CANNOT BE REPORTED AS current_stop
 * ---------------------------------------------------------------------------
 *
 * route_stops holds no landmark for 8 of the 20 Cabanatuan-Rizal fares and 8 of
 * the 19 Rizal-Cabanatuan fares, including `Caalibangbani an` and
 * `San Miguel Munti, Talavera` on both directions. Those orders still appear as
 * `next_stop` and `destination` (which are derived from fare_pricing order), but
 * the bus can never be reported as AT them, because there is nothing to measure
 * against. Adding the missing landmarks is a data task, listed in
 * openspec/changes/fix-inspector-stop-detection-by-gps/tasks.md.
 *
 * ---------------------------------------------------------------------------
 * WHY A STOP IS NOT REPORTED OUTSIDE ITS RADIUS
 * ---------------------------------------------------------------------------
 *
 * A position outside every radius reports no stop rather than the closest one.
 * The previous implementation took a global maximum of similar_text() across a
 * geocoded address and every stop name, so a confidently wrong answer beat a
 * correct-but-differently-spelled one at 90.3%. Radius is the only rule here that
 * cannot promote a wrong answer: a stop either is or is not within its own radius.
 */

function stop_detection_distance_meters($lat1, $lon1, $lat2, $lon2)
{
    $earth = 6371000;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a =
        sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) *
        cos(deg2rad($lat2)) *
        sin($dLon / 2) *
        sin($dLon / 2);

    return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Normalize a trip route name to the fare route key the corridor map and
 * fare_pricing use.
 *
 * The trip, fare and stop-map tables do not agree on punctuation:
 * `bus_trips.route_name` may be "Rizal - Cabanatuan" (spaces around the dash)
 * while fare_pricing.route_name and the corridor map use "Rizal-Cabanatuan".
 * An exact match then finds no fares and no landmarks, so every stop reports as
 * unknown and the app shows its loading fallback. Normalizing the separator
 * spacing and case here makes both spellings resolve to the same corridor; an
 * unrecognised name is returned unchanged, so nothing else changes.
 */
function stop_detection_fare_route_name($routeName)
{
    $value = strtolower(trim((string) $routeName));
    $value = preg_replace('/\s*-\s*/', '-', $value);
    $value = preg_replace('/\s+/', ' ', $value);

    $routes = array(
        'cabanatuan-rizal' => 'Cabanatuan-Rizal',
        'rizal-cabanatuan' => 'Rizal-Cabanatuan',
    );

    return isset($routes[$value]) ? $routes[$value] : (string) $routeName;
}

/**
 * fare_pricing.route_name -> route_stops.route_name.
 *
 * The two tables spell the same corridor differently ('Cabanatuan-Rizal' vs
 * 'Cabanatuan to Rizal Route'), so this is an explicit two-entry table rather
 * than a comparison. An unmapped route returns null, which the caller treats as
 * "no stop data" - never as a fallback onto another route's stops.
 *
 * The fare name is normalized first, so a trip whose route name carries a
 * different separator spelling ("Rizal - Cabanatuan") still resolves.
 */
function stop_detection_route_for_fare($fareRouteName)
{
    $fareRouteName = stop_detection_fare_route_name($fareRouteName);

    $routes = array(
        'Cabanatuan-Rizal' => 'Cabanatuan to Rizal Route',
        'Rizal-Cabanatuan' => 'Rizal to Cabanatuan Route',
    );

    return isset($routes[$fareRouteName]) ? $routes[$fareRouteName] : null;
}

/**
 * The declared stop -> fare links, as
 * route_stops.route_name => list of array(sequence_order, display_order, radius_m).
 */
function stop_detection_map()
{
    return array(
        'Cabanatuan to Rizal Route' => array(
            // seq, fare display_order, radius_m
            array(1, 1, 250),   // Cabanatuan City Terminal (Main terminal)
            array(25, 3, 150),  // Mayapyap
            array(33, 6, 125),  // Pinagpanao (7/11)
            array(35, 7, 125),  // Baluga
            array(38, 8, 100),  // Homestead 1
            array(40, 9, 100),  // Homestead 2
            array(43, 10, 175), // San Ricardo- Bantug Hacienda Rd
            array(44, 11, 175), // General Luna
            array(53, 16, 175), // Bicos Bridge - 1
            array(56, 18, 175), // Aglipay (Curva)
            array(59, 19, 200), // Del Pilar
            array(66, 20, 250), // Poblacion Sur
        ),
        'Rizal to Cabanatuan Route' => array(
            array(13, 1, 175),  // Aglipay (Curva)
            array(16, 3, 175),  // Bicos Bridge - 1
            array(25, 8, 175),  // General Luna
            array(26, 9, 175),  // San Ricardo- Bantug Hacienda Rd
            array(29, 10, 100), // Homestead 2
            array(31, 11, 100), // Homestead 1
            array(34, 12, 125), // Baluga
            array(36, 13, 125), // Pinagpanaan (7/11)
            array(44, 16, 150), // Mayapyap
            array(52, 18, 150), // Bitas, Cabanatuan City
            array(68, 19, 250), // Cabanatuan City Terminal (Main terminal)
        ),
    );
}

/**
 * Resolve one declared link against the route_stops rows actually returned by the
 * query, preferring a fare_pricing_id written by set_route_stop_fare_links.sql.
 *
 * Returns null when no landmark matches, so a row that has gone missing degrades
 * to "this stop cannot be detected" instead of to a wrong position.
 */
function stop_detection_link(array $entry, array $rowsBySequence)
{
    $sequence = (int) $entry[0];
    $displayOrder = (int) $entry[1];
    $radius = (float) $entry[2];

    if (!isset($rowsBySequence[$sequence])) {
        return null;
    }

    $row = $rowsBySequence[$sequence];

    return array(
        'sequence_order' => $sequence,
        'declared_display_order' => $displayOrder,
        // A fare_pricing.id written by set_route_stop_fare_links.sql, or 0 while the
        // link still comes from the map. This is an id, not a display_order, so it
        // is resolved against the fare rows before it is used as an order.
        'fare_pricing_id' => isset($row['fare_pricing_id']) ? (int) $row['fare_pricing_id'] : 0,
        'radius_m' => $radius,
        'latitude' => (float) $row['latitude'],
        'longitude' => (float) $row['longitude'],
        'stop_name' => isset($row['stop_name']) ? $row['stop_name'] : '',
    );
}

/**
 * The fare_pricing.display_order a link resolves to.
 *
 * A linked fare_pricing_id is authoritative when present, because it was written
 * from the fare table itself; otherwise the map's declared order is used. Returns
 * null when a linked id is not among this trip's fare rows, which means the link
 * is stale and the stop must not be reported.
 */
/**
 * The fare_pricing.display_order a link resolves to, and where that order came
 * from.
 *
 * 'fare_link'     the row's own fare_pricing_id resolved against this trip's
 *                 fare rows - the authoritative case, written by
 *                 set_route_stop_fare_links.sql from the fare table itself.
 * 'map'           the row carries no link, so the curated map's declared order
 *                 is used. This is the normal state before the migration runs.
 * 'map_fallback'  the row names a fare_pricing_id that this trip does not
 *                 carry, so the declared order is used instead.
 *
 * The third case is a real fault - route_stops and fare_pricing have drifted
 * apart, so the ids in a previously-run migration no longer point at anything.
 * It used to return null, which made stop_detection_choose() skip the candidate
 * outright: a landmark the inspector was 15 m from, comfortably inside its
 * radius, silently reported as "no stop detected". A wrong answer is bad, but a
 * missing answer with no explanation is worse, and the fault is in the link
 * rather than in the position. So the order falls back to the curated map and
 * the caller reports how often it happened.
 *
 * @return array('order' => int|null, 'source' => string)
 */
function stop_detection_resolve_order(array $link, array $fareRows)
{
    if ($link['fare_pricing_id'] > 0) {
        foreach ($fareRows as $row) {
            if ((int) $row['id'] === $link['fare_pricing_id']) {
                return array('order' => (int) $row['display_order'], 'source' => 'fare_link');
            }
        }

        return array('order' => $link['declared_display_order'], 'source' => 'map_fallback');
    }

    return array('order' => $link['declared_display_order'], 'source' => 'map');
}

function stop_detection_display_order(array $link, array $fareRows)
{
    $resolved = stop_detection_resolve_order($link, $fareRows);

    return $resolved['order'];
}

/**
 * Pick the stop the inspector is at.
 *
 * Nearest landmark within its own radius wins. Ties are broken by the smaller
 * display_order so the answer never depends on the order rows came back from the
 * database in.
 *
 * $fareRows is needed here, not just in stop_detection_successors(), because the
 * tie-break compares orders and a link's order may come from its fare_pricing_id.
 *
 * @param int $fallbacks Filled in with the number of candidates whose
 *                         fare_pricing_id did not resolve and whose order came
 *                         from the curated map instead. Passed by reference so
 *                         the caller can report a data fault it cannot otherwise
 *                         see.
 *
 * @return array|null array(sequence_order, display_order, radius_m, distance_m, stop_name, order_source)
 */
function stop_detection_choose(array $links, array $fareRows, $latitude, $longitude, &$fallbacks = 0)
{
    $best = null;

    foreach ($links as $link) {
        if ($link === null) {
            continue;
        }

        $distance = stop_detection_distance_meters(
            $latitude,
            $longitude,
            $link['latitude'],
            $link['longitude']
        );

        if ($distance > $link['radius_m']) {
            continue;
        }

        $resolved = stop_detection_resolve_order($link, $fareRows);
        if ($resolved['order'] === null) {
            continue;
        }

        if ($resolved['source'] === 'map_fallback') {
            $fallbacks++;
        }

        $displayOrder = $resolved['order'];

        if (
            $best === null ||
            $distance < $best['distance_m'] ||
            ($distance === $best['distance_m'] && $displayOrder < $best['display_order'])
        ) {
            $best = array(
                'sequence_order' => $link['sequence_order'],
                'display_order' => $displayOrder,
                'radius_m' => $link['radius_m'],
                'distance_m' => $distance,
                'stop_name' => $link['stop_name'],
                'order_source' => $resolved['source'],
            );
        }
    }

    return $best;
}

/**
 * Resolve the chosen display_order into the names the response reports.
 *
 * All three names come from fare_pricing rows matched on the integer
 * display_order, so no destination string is ever compared with a stop_name.
 * A missing row yields null rather than a substituted name.
 */
function stop_detection_successors(array $fareRows, $displayOrder)
{
    $byOrder = array();
    $highest = null;

    foreach ($fareRows as $row) {
        $order = (int) $row['display_order'];
        $byOrder[$order] = $row['destination'];
        if ($highest === null || $order > $highest) {
            $highest = $order;
        }
    }

    return array(
        'current_stop' => $displayOrder !== null && isset($byOrder[$displayOrder])
            ? $byOrder[$displayOrder]
            : null,
        'next_stop' => $displayOrder !== null && isset($byOrder[$displayOrder + 1])
            ? $byOrder[$displayOrder + 1]
            : null,
        'destination' => $highest !== null && isset($byOrder[$highest])
            ? $byOrder[$highest]
            : null,
    );
}

/**
 * Select the upcoming route stops following the current display_order.
 *
 * Uses ascending display_order as the source of truth.
 *
 * @param array $fareRows List of fare_pricing rows with display_order and destination.
 * @param int|null $currentDisplayOrder Current stop's display_order. If null, starts from before the first stop (0).
 * @param int $limit Maximum number of upcoming stops to return (default 3).
 * @return array List of array('name' => string, 'display_order' => int)
 */
function stop_detection_upcoming_stops(array $fareRows, $currentDisplayOrder, $limit = 3)
{
    $byOrder = array();
    foreach ($fareRows as $row) {
        $order = (int) $row['display_order'];
        $byOrder[$order] = $row['destination'];
    }
    ksort($byOrder);

    $startOrder = $currentDisplayOrder !== null ? (int) $currentDisplayOrder : 0;
    $upcoming = array();

    foreach ($byOrder as $order => $name) {
        if ($order > $startOrder) {
            $upcoming[] = array(
                'name' => $name,
                'display_order' => $order,
            );
            if (count($upcoming) >= $limit) {
                break;
            }
        }
    }

    return $upcoming;
}

/**
 * Resolves the coordinates [latitude, longitude] for a stop on a route.
 *
 * @param array $routeStops List of route_stops rows
 * @param string $fareRouteName 'Cabanatuan-Rizal' or 'Rizal-Cabanatuan'
 * @param int|null $displayOrder fare_pricing.display_order
 * @param string|null $stopName Stop/destination name
 * @return array|null array('latitude' => float, 'longitude' => float) or null
 */
function stop_detection_find_coordinate(array $routeStops, $fareRouteName, $displayOrder = null, $stopName = null)
{
    $fareRouteName = stop_detection_fare_route_name($fareRouteName);
    $landmarkRoute = stop_detection_route_for_fare($fareRouteName);
    if ($landmarkRoute === null) {
        return null;
    }

    $map = stop_detection_map();
    $declared = isset($map[$landmarkRoute]) ? $map[$landmarkRoute] : array();

    // 1. By display_order via stop_detection_map
    if ($displayOrder !== null) {
        foreach ($declared as $entry) {
            if ((int)$entry[1] === (int)$displayOrder) {
                $targetSeq = (int)$entry[0];
                foreach ($routeStops as $rStop) {
                    if ((int)$rStop['sequence_order'] === $targetSeq && isset($rStop['latitude']) && isset($rStop['longitude'])) {
                        return array(
                            'latitude' => (float)$rStop['latitude'],
                            'longitude' => (float)$rStop['longitude'],
                        );
                    }
                }
            }
        }
    }

    // 2. By stop_name match against routeStops
    if (!empty($stopName)) {
        $cleanTarget = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $stopName)));
        if ($cleanTarget !== '') {
            foreach ($routeStops as $rStop) {
                if (!isset($rStop['latitude']) || !isset($rStop['longitude'])) {
                    continue;
                }
                $cleanName = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $rStop['stop_name'])));
                if ($cleanName !== '' && (strpos($cleanName, $cleanTarget) !== false || strpos($cleanTarget, $cleanName) !== false)) {
                    return array(
                        'latitude' => (float)$rStop['latitude'],
                        'longitude' => (float)$rStop['longitude'],
                    );
                }
            }
        }
    }

    // 3. Known terminal coordinates fallback
    if ($fareRouteName === 'Cabanatuan-Rizal') {
        if ($displayOrder === 1) {
            return array('latitude' => 15.472226, 'longitude' => 120.969182);
        }
        if ($displayOrder === 20 || ($stopName !== null && stripos($stopName, 'Rizal') !== false)) {
            return array('latitude' => 15.705381, 'longitude' => 121.099504);
        }
    } elseif ($fareRouteName === 'Rizal-Cabanatuan') {
        if ($displayOrder === 1) {
            return array('latitude' => 15.688171, 'longitude' => 121.078333);
        }
        if ($displayOrder === 19 || ($stopName !== null && stripos($stopName, 'Cabanatuan') !== false)) {
            return array('latitude' => 15.472226, 'longitude' => 120.969182);
        }
    }

    return null;
}

/**
 * The whole decision, given the fare rows for the trip and the route_stops rows
 * for its corridor.
 *
 * Returns the response's stop fields plus the diagnostics that make the choice
 * reconstructible from the response alone:
 *
 *   stop_source     "gps" when a stop matched, "none" when nothing was in range
 *   stop_distance_m metres to the matched stop
 *   stop_radius_m   the radius that was applied to it
 */
/**
 * Index a corridor's landmarks by the sequence key the map uses.
 *
 * A sequence is not guaranteed to be unique in production: running
 * add_stop_geofence_radius.sql inserts a second row per fare stop with
 * sequence_order 1..20, and that script's coordinates are 1.5-2.1 km from the
 * real positions. Indexing by plain assignment would keep whichever row the
 * query happened to return last, which makes the answer depend on row order -
 * the same class of defect this module exists to remove.
 *
 * So duplicates are resolved by a rule that does not depend on order:
 *
 *   1. A row carrying a real fare_pricing_id beats one without. The link is
 *      written only to the precise landmarks, so "linked" identifies the good
 *      row even before the coordinates are compared.
 *   2. Then the lower id, purely as a stable tie-break.
 *
 * Returns array(sequence_order => row).
 */
function stop_detection_index_by_sequence(array $routeStops, $routeName)
{
    $index = array();

    foreach ($routeStops as $row) {
        if (isset($row['route_name']) && $row['route_name'] !== $routeName) {
            continue;
        }
        if (!isset($row['latitude']) || !isset($row['longitude'])) {
            continue;
        }
        if (!isset($row['sequence_order'])) {
            continue;
        }

        $sequence = (int) $row['sequence_order'];
        $candidate = array(
            'linked' => isset($row['fare_pricing_id']) && (int) $row['fare_pricing_id'] > 0 ? 1 : 0,
            'id' => isset($row['id']) ? (int) $row['id'] : PHP_INT_MAX,
        );

        if (!isset($index[$sequence])) {
            $index[$sequence] = $row;
            continue;
        }

        $held = array(
            'linked' => isset($index[$sequence]['fare_pricing_id']) && (int) $index[$sequence]['fare_pricing_id'] > 0 ? 1 : 0,
            'id' => isset($index[$sequence]['id']) ? (int) $index[$sequence]['id'] : PHP_INT_MAX,
        );

        if ($candidate['linked'] > $held['linked']
            || ($candidate['linked'] === $held['linked'] && $candidate['id'] < $held['id'])) {
            $index[$sequence] = $row;
        }
    }

    return $index;
}

/**
 * Resolve one trip's reported stops from the inspector's position.
 *
 * Beyond the reported stops, returns the counts needed to tell the failure modes
 * apart from the response alone: whether the corridor's route name was
 * recognised, how many landmark rows were read, how many of those the map could
 * actually use, and how far the nearest usable landmark was even when it was out
 * of range. Without those, "no stop" is indistinguishable from "wrong data".
 */
function stop_detection_resolve(array $fareRows, $fareRouteName, array $routeStops, $latitude, $longitude)
{
    $result = array(
        'current_stop' => null,
        'current_display_order' => null,
        'next_stop' => null,
        'destination' => null,
        'upcoming_stops' => array(),
        'eta_minutes' => null,
        'stop_source' => 'none',
        'stop_distance_m' => null,
        'stop_radius_m' => null,
        'stop_route_matched' => false,
        'stop_landmarks_read' => 0,
        'stop_landmarks_matched' => 0,
        'stop_nearest_m' => null,
        'stop_order_source' => null,
        'stop_link_fallbacks' => 0,
    );

    $routeName = stop_detection_route_for_fare($fareRouteName);
    if ($routeName === null) {
        return $result;
    }

    $map = stop_detection_map();
    if (!isset($map[$routeName])) {
        return $result;
    }

    $result['stop_route_matched'] = true;

    $read = 0;
    foreach ($routeStops as $row) {
        if (isset($row['route_name']) && $row['route_name'] === $routeName) {
            $read++;
        }
    }
    $result['stop_landmarks_read'] = $read;

    $rowsBySequence = stop_detection_index_by_sequence($routeStops, $routeName);

    $links = array();
    $nearestMeters = null;

    foreach ($map[$routeName] as $entry) {
        $link = stop_detection_link($entry, $rowsBySequence);
        if ($link === null) {
            $links[] = null;
            continue;
        }

        $links[] = $link;
        $result['stop_landmarks_matched']++;

        $distance = stop_detection_distance_meters($latitude, $longitude, $link['latitude'], $link['longitude']);
        if ($nearestMeters === null || $distance < $nearestMeters) {
            $nearestMeters = $distance;
        }
    }

    // Reported even when out of range: a nearest of ~1900 m means the corridor
    // returned the seeded placeholder coordinates rather than the real ones,
    // which is a data fault and not a position fault.
    $result['stop_nearest_m'] = $nearestMeters === null ? null : round($nearestMeters, 1);

    $fallbacks = 0;
    $chosen = stop_detection_choose($links, $fareRows, $latitude, $longitude, $fallbacks);

    // Reported even when nothing matched: a non-zero count means route_stops and
    // fare_pricing disagree about which fare row a landmark is, which is a data
    // fault to fix rather than a position to explain.
    $result['stop_link_fallbacks'] = $fallbacks;

    if ($chosen === null) {
        // Between stops: determine current route position from closest corridor landmark
        $closestOrder = null;
        $closestDist = null;
        foreach ($links as $link) {
            if ($link === null) {
                continue;
            }
            $dist = stop_detection_distance_meters($latitude, $longitude, $link['latitude'], $link['longitude']);
            if ($closestDist === null || $dist < $closestDist) {
                $closestDist = $dist;
                $resolved = stop_detection_resolve_order($link, $fareRows);
                $closestOrder = $resolved['order'];
            }
        }
        $result['current_display_order'] = $closestOrder;
        $result['upcoming_stops'] = stop_detection_upcoming_stops($fareRows, $closestOrder, 3);
        return $result;
    }

    $names = stop_detection_successors($fareRows, $chosen['display_order']);

    $result['current_stop'] = $names['current_stop'];
    $result['current_display_order'] = (int) $chosen['display_order'];
    $result['next_stop'] = $names['next_stop'];
    $result['destination'] = $names['destination'];
    $result['upcoming_stops'] = stop_detection_upcoming_stops($fareRows, $chosen['display_order'], 3);
    $result['stop_source'] = 'gps';
    $result['stop_distance_m'] = round($chosen['distance_m'], 1);
    $result['stop_radius_m'] = $chosen['radius_m'];
    $result['stop_order_source'] = $chosen['order_source'];

    return $result;
}
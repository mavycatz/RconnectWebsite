<?php

/* Included from both __DIR__ and DOCUMENT_ROOT forms; a second include must
   not redeclare the function. */
if (function_exists('canonicalRouteName')) {
    return;
}

/**
 * Canonicalize route names across the two naming conventions used in the DB:
 *   - route_stops.route_name:      "Cabanatuan to Rizal Route" / "Rizal to Cabanatuan Route"
 *   - bus_trips / fare_pricing:    "Cabanatuan-Rizal"          / "Rizal-Cabanatuan"
 *
 * Returns a stable lowercase key so route lookups can compare them.
 */
function canonicalRouteName($name)
{
    $value = strtolower(trim((string)$name));

    // " to " -> separator (e.g. "cabanatuan to rizal route" -> "cabanatuan-rizal route")
    $value = preg_replace('/\s+to\s+/', '-', $value);

    // Normalize punctuation/whitespace to a single "-"
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim($value, '-');

    // Drop the trailing "route" token
    $value = preg_replace('/-route$/', '', $value);

    return $value;
}

/**
 * Resolve the fare_pricing route_name that canonically matches a trip/bus
 * route name.
 *
 * fare_pricing stores the "Cabanatuan-Rizal" convention while route_stops
 * stores "Cabanatuan to Rizal Route"; a trip's route_name may be written in
 * either convention depending on where it was created. Exact equality then
 * finds no fare rows and the passenger sees "no stops available" even though
 * the trip is active. Returns the matching fare_pricing name, or the original
 * name when nothing matches so callers keep their exact lookup.
 */
function resolveFareRouteName($conn, $routeName)
{
    $routeName = trim((string)$routeName);

    if ($routeName === '' || !($conn instanceof PDO)) {
        return $routeName;
    }

    $canonical = canonicalRouteName($routeName);

    try {
        $stmt = $conn->query("
            SELECT DISTINCT route_name
            FROM fare_pricing
            WHERE route_name IS NOT NULL AND route_name <> ''
        ");
        $candidates = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (\PDOException $e) {
        return $routeName;
    }

    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate !== '' && canonicalRouteName($candidate) === $canonical) {
            return $candidate;
        }
    }

    return $routeName;
}

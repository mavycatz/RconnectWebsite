<?php

/**
 * Rconnect-Drive shared driver-location helpers.
 *
 * Used by scripts/updateLocation.php. The endpoint was written against these
 * helpers, but the file was never committed, so the require at
 * updateLocation.php:45 failed at request time and the endpoint never stored a
 * fix. Every contract below is pinned by a call site in that endpoint; see
 * openspec/changes/fix-realtime-location-not-received/design.md for the table.
 *
 * Contract summary:
 *   - Timestamps are formatted as Asia/Manila wall-clock text explicitly via
 *     DateTimeZone, never via the session default. updateLocation.php pins the
 *     reader's MySQL session to the same zone and measures age with NOW(), so
 *     the write and the compare must share one basis. When they did not,
 *     every live row read as 8 hours old and destination alerts were blocked.
 *   - Speed is stored in km/h. The device reports m/s (expo-location
 *     coords.speed). Values outside 0-150 km/h are stored as 0.
 *
 * Every function is guarded with function_exists() so the file is safe to
 * require more than once, matching scripts/lib/active_trip.php. Deliberately
 * does NOT define update_location_respond(): that is defined inline at
 * updateLocation.php:50-56 behind its own guard, and redeclaring it here would
 * be a fatal "cannot redeclare" error.
 */

if (!defined('UPDATE_LOCATION_TIMEZONE')) {
    define('UPDATE_LOCATION_TIMEZONE', 'Asia/Manila');
}

if (!defined('UPDATE_LOCATION_SPEED_MAX_KMH')) {
    define('UPDATE_LOCATION_SPEED_MAX_KMH', 150.0);
}

/*
 * Timestamps at or above this value are treated as milliseconds since epoch
 * rather than seconds. 1e11 seconds is the year 5138, 1e11 milliseconds is
 * 1973, so the boundary is unambiguous for any real device clock: a second
 * value near 1.8e9 stays as-is, a millisecond value near 1.8e12 is divided.
 */
if (!defined('UPDATE_LOCATION_MS_THRESHOLD')) {
    define('UPDATE_LOCATION_MS_THRESHOLD', 100000000000.0);
}

if (!function_exists('update_location_timezone')) {

    /**
     * The single clock basis shared by the writer and the reader.
     *
     * @return string An IANA time zone identifier.
     */
    function update_location_timezone()
    {
        return UPDATE_LOCATION_TIMEZONE;
    }
}

if (!function_exists('update_location_now')) {

    /**
     * The current instant as Asia/Manila wall-clock text.
     *
     * Formatted through an explicit DateTimeZone so the stored value does not
     * depend on the session or host default. The output is intentionally
     * strtotime()-parseable: updateLocation.php:246 and :374 pass it (and a
     * read-back column value) through strtotime(), so a common textual form
     * keeps the elapsed and skew arithmetic internally consistent.
     *
     * @return string 'Y-m-d H:i:s' in the update_location_timezone() zone.
     */
    function update_location_now()
    {
        $tz = new DateTimeZone(update_location_timezone());
        return (new DateTime('now', $tz))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('update_location_normalize_timestamp')) {

    /**
     * Normalise a device-supplied timestamp to strtotime()-parseable text.
     *
     * Accepts epoch seconds, epoch milliseconds, and any ISO-8601 string
     * (including an explicit offset). Anything uninterpretable returns null so
     * the caller records server time instead of failing the fix. The result is
     * formatted in update_location_timezone(), the same basis as
     * update_location_now(), which keeps the device-clock skew subtraction at
     * updateLocation.php:374 meaningful.
     *
     * @param mixed $value Raw payload value.
     * @return string|null 'Y-m-d H:i:s' in the update_location_timezone() zone.
     */
    function update_location_normalize_timestamp($value)
    {
        if ($value === null) {
            return null;
        }

        $tz = new DateTimeZone(update_location_timezone());

        if (is_int($value) || is_float($value)
            || (is_string($value) && $value !== '' && is_numeric($value))) {
            $numeric = (float) $value;
            if (!is_finite($numeric) || $numeric <= 0) {
                return null;
            }
            if ($numeric >= UPDATE_LOCATION_MS_THRESHOLD) {
                $numeric = $numeric / 1000.0;
            }
            $seconds = (int) floor($numeric);
            return (new DateTime('@' . $seconds))->setTimezone($tz)->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            $parsed = strtotime($trimmed);
            if ($parsed === false) {
                return null;
            }
            return (new DateTime('@' . $parsed))->setTimezone($tz)->format('Y-m-d H:i:s');
        }

        return null;
    }
}

if (!function_exists('update_location_distance_km')) {

    /**
     * Great-circle distance between two coordinates, in kilometres.
     *
     * Used at updateLocation.php:234 to derive a speed when the device reports
     * none. Mean Earth radius per IUGG.
     *
     * @return float
     */
    function update_location_distance_km($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadiusKm = 6371.0088;

        $phi1 = deg2rad((float) $lat1);
        $phi2 = deg2rad((float) $lat2);
        $deltaPhi = deg2rad((float) $lat2 - (float) $lat1);
        $deltaLambda = deg2rad((float) $lng2 - (float) $lng1);

        $a = sin($deltaPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($deltaLambda / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}

if (!function_exists('update_location_sanitize_speed')) {

    /**
     * Resolve the stored speed in km/h.
     *
     * Tries the device value first (expo-location reports m/s), falling back to
     * distance-over-elapsed when the device reports none. Anything outside
     * 0-150 km/h is stored as 0 rather than trusted, per updateLocation.php:23-25.
     *
     * @param mixed $deviceSpeed      Device speed in m/s, or null.
     * @param float $previousDistanceKm Distance since the previous fix.
     * @param float $elapsedSeconds     Time since the previous fix.
     * @return float km/h, rounded to 2 decimals.
     */
    function update_location_sanitize_speed($deviceSpeed, $previousDistanceKm, $elapsedSeconds)
    {
        if ($deviceSpeed !== null && is_numeric($deviceSpeed)) {
            $kmh = ((float) $deviceSpeed) * 3.6;
        } else {
            $distance = (float) $previousDistanceKm;
            $elapsed = (float) $elapsedSeconds;
            $kmh = $elapsed > 0.0 ? $distance / ($elapsed / 3600.0) : 0.0;
        }

        if (!is_finite($kmh) || $kmh < 0.0 || $kmh > UPDATE_LOCATION_SPEED_MAX_KMH) {
            $kmh = 0.0;
        }

        return round($kmh, 2);
    }
}

if (!function_exists('update_location_bus_matches')) {

    /**
     * Whether the app-reported bus id matches the server-resolved one.
     *
     * Informational only: the server's resolved bus_id is authoritative and is
     * what gets stored. The result surfaces as bus_id_match in the response
     * (updateLocation.php:385) and never gates the write, because the app
     * resolves its bus id at trip start and a mismatch is expected when a
     * driver's assignment changes mid-trip.
     *
     * @param mixed $reported App-supplied bus id.
     * @param mixed $resolved Server-resolved bus id.
     * @return bool
     */
    function update_location_bus_matches($reported, $resolved)
    {
        $a = trim((string) $reported);
        $b = trim((string) $resolved);
        if ($a === '' || $b === '') {
            return false;
        }
        return $a === $b;
    }
}

if (!function_exists('update_location_age_seconds')) {

    /**
     * Age, in seconds, of a stored timestamp against the server clock.
     *
     * Surfaced as age_seconds in the response (updateLocation.php:399) so a
     * caller can see the write is live. Returns 0.0 rather than a negative,
     * false, or large value when either input cannot be parsed, so an
     * unreadable stamp never reads as stale.
     *
     * @param mixed $storedAt  Stored timestamp text.
     * @param mixed $serverNow Server timestamp text.
     * @return float
     */
    function update_location_age_seconds($storedAt, $serverNow)
    {
        $stored = strtotime((string) $storedAt);
        $now = strtotime((string) $serverNow);
        if ($stored === false || $now === false) {
            return 0.0;
        }
        return (float) ($now - $stored);
    }
}

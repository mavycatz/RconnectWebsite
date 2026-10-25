<?php

/**
 * Shared driver/bus location primitives for the passenger app.
 *
 * These helpers were extracted from DriverLocationService.php so both the
 * passenger-trip-scoped resolver (destination alerts) and the bus-trip-scoped
 * One-Time boarding resolver use one implementation of:
 *   - tolerant bus-id matching across "Bus-12" / "bus 12" / "12" formats,
 *   - coordinate validity checks,
 *   - the per-table record-timestamp rule (append-only history vs rewritten
 *     snapshot),
 *   - the Asia/Manila time basis, age computation, and clock-skew protection,
 *   - the FRESH / AGING / STALE classification.
 *
 * Behavior is unchanged from the original definitions; the guards keep a
 * double include harmless.
 */

if (!defined('FRESHNESS_FRESH_SECONDS')) {
    define('FRESHNESS_FRESH_SECONDS', 30);
}

if (!defined('FRESHNESS_AGING_SECONDS')) {
    define('FRESHNESS_AGING_SECONDS', 120);
}

if (!defined('DRIVER_LOCATION_MAX_AGE_SECONDS')) {
    /**
     * Legacy hard-stop window (admin dashboard's "GPS Online" cutoff). The
     * freshness classification above is the evaluation gate; this constant is
     * kept for consumers that still read it.
     */
    define('DRIVER_LOCATION_MAX_AGE_SECONDS', 180);
}

if (!defined('PASSENGER_DRIVER_CLOCK_SKEW_TOLERANCE_SECONDS')) {
    /**
     * How far ahead of the server clock a stored timestamp may sit and still
     * be read as fresh. The writer and the reader are two clock reads of one
     * instant, so a second or two of disagreement is ordinary. Anything larger
     * is a real stamping fault and is treated as unusable rather than fresh,
     * which keeps stale protection from being bypassed by a bad timestamp.
     */
    define('PASSENGER_DRIVER_CLOCK_SKEW_TOLERANCE_SECONDS', 5);
}

if (!defined('PASSENGER_DRIVER_POSITION_SOURCE_HISTORY')) {
    define('PASSENGER_DRIVER_POSITION_SOURCE_HISTORY', 'driver_location_history');
}

if (!defined('PASSENGER_DRIVER_POSITION_SOURCE_SNAPSHOT')) {
    define('PASSENGER_DRIVER_POSITION_SOURCE_SNAPSHOT', 'driver_current_location');
}

if (!function_exists('passengerBusIdMatches')) {
    /**
     * Tolerant bus-id comparison for the formats used across the driver
     * assignment, scan, and GPS tables ("Bus-12", "bus 12", "12").
     */
    function passengerBusIdMatches($a, $b)
    {
        $a = strtolower(trim((string) $a));
        $b = strtolower(trim((string) $b));

        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        $cleanA = preg_replace('/^bus[\s\-#:]*/i', '', $a);
        $cleanB = preg_replace('/^bus[\s\-#:]*/i', '', $b);

        return $cleanA !== '' && $cleanA === $cleanB;
    }
}

if (!function_exists('passengerDriverLocationCoordinatesValid')) {
    /**
     * @param array<string,mixed> $row
     */
    function passengerDriverLocationCoordinatesValid(array $row)
    {
        $lat = isset($row['latitude']) && is_numeric($row['latitude']) ? (float) $row['latitude'] : null;
        $lng = isset($row['longitude']) && is_numeric($row['longitude']) ? (float) $row['longitude'] : null;

        return $lat !== null && $lng !== null
            && is_finite($lat) && is_finite($lng)
            && $lat >= -90 && $lat <= 90
            && $lng >= -180 && $lng <= 180;
    }
}

if (!function_exists('passengerDriverLocationRecordTimestamp')) {
    /**
     * The timestamp of the location record itself, and the column it came
     * from. Freshness is measured from this and never from the other table's
     * column, so a fresh history row is not judged by a stale snapshot.
     *
     * The preferred column depends on the table, because the two tables carry
     * both columns and they do not mean the same thing:
     * - driver_location_history is append-only, so created_at is when this
     *   reading was actually taken.
     * - driver_current_location is a single rewritten row, so created_at is
     *   when the driver first ever reported and updated_at is the only column
     *   that says how old the stored position is. Preferring created_at there
     *   would make a position that stopped moving look permanently fresh.
     *
     * @param array<string,mixed> $row
     * @param string|null $source
     * @return array{value:string,column:?string}
     */
    function passengerDriverLocationRecordTimestamp(array $row, $source = null)
    {
        $columns = $source === PASSENGER_DRIVER_POSITION_SOURCE_SNAPSHOT
            ? ['updated_at', 'created_at']
            : ['created_at', 'updated_at'];

        foreach ($columns as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '') {
                return ['value' => $value, 'column' => $column];
            }
        }

        return ['value' => '', 'column' => null];
    }
}

if (!function_exists('passengerApplyManilaSessionTimezone')) {
    /**
     * Apply the driver pipeline's Asia/Manila session timezone to the
     * passenger connection once per connection. Narrowly scoped so admin and
     * finance sessions are untouched.
     *
     * App\Core\Database::connect() already pins this on every app connection.
     * This remains as a safety net for callers that hand in a connection from
     * elsewhere (a test harness, or a future entry point that builds its own),
     * because a reader on a UTC session is exactly the condition that made a
     * live bus look 8 hours old.
     */
    function passengerApplyManilaSessionTimezone(PDO $conn)
    {
        static $applied = [];

        $key = spl_object_id($conn);
        if (isset($applied[$key])) {
            return;
        }
        $applied[$key] = true;

        $timezone = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Manila';
        $offset = defined('APP_UTC_OFFSET') ? APP_UTC_OFFSET : '+08:00';

        try {
            if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                // Two independent attempts. A named zone is preferred so the
                // offset follows the Philippines' rules, but a MySQL without
                // the timezone tables loaded rejects it with error 1298, and a
                // throwing statement must not skip the numeric offset - which
                // is exactly equivalent here and always available.
                foreach ([$timezone, $offset] as $candidate) {
                    try {
                        $stmt = $conn->prepare("SET time_zone = ?");
                        $stmt->execute([$candidate]);
                        break;
                    } catch (\Throwable $inner) {
                        continue;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal: the PHP fallback still computes an age, and PHP's
            // default timezone is Asia/Manila via app/Core/Bootstrap.php.
        }
    }
}

if (!function_exists('passengerDriverTimeBasis')) {
    /**
     * The single declared basis for driver GPS timestamps.
     *
     * Driver GPS rows are written by mobile/driver/updateLocation.php with an
     * explicit Asia/Manila value, and the app connection is pinned to the same
     * zone, so there is one basis end to end and no need to infer one.
     *
     * An earlier version of this file detected the basis by comparing
     * MAX(created_at) against NOW() and UTC_TIMESTAMP(). That was treating the
     * symptom: the disagreement was never in the data, it was that the writer
     * pinned +08:00 on its own connection while the reader inherited the
     * server's global time_zone. Guessing also had a failure mode of its own -
     * with no recent row to learn from it fell back to the session clock and
     * reported a UTC-written record as hours older than it was.
     *
     * @return array{timezone:string,offset:string}
     */
    function passengerDriverTimeBasis()
    {
        return [
            'timezone' => defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Manila',
            'offset' => defined('APP_UTC_OFFSET') ? APP_UTC_OFFSET : '+08:00',
        ];
    }
}

if (!function_exists('passengerDriverReferenceTime')) {
    /**
     * Current Philippine time, read from the same session the age is measured
     * on, so the two are guaranteed comparable.
     *
     * @return array{value:?string,source:string}
     */
    function passengerDriverReferenceTime(PDO $conn)
    {
        $basis = passengerDriverTimeBasis();

        $driver = null;
        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            $driver = null;
        }

        if ($driver === 'mysql') {
            try {
                $stmt = $conn->query("SELECT NOW() AS now_value");
                $value = $stmt ? $stmt->fetchColumn() : false;

                if ($value !== false && $value !== null && trim((string) $value) !== '') {
                    return ['value' => (string) $value, 'source' => 'mysql_now'];
                }
            } catch (\Throwable $e) {
                // Fall through to PHP.
            }
        }

        try {
            $moment = new DateTimeImmutable('now', new DateTimeZone($basis['timezone']));

            return ['value' => $moment->format('Y-m-d H:i:s'), 'source' => 'php_now'];
        } catch (\Throwable $e) {
            return ['value' => null, 'source' => 'unavailable'];
        }
    }
}

if (!function_exists('passengerDriverRecordAge')) {
    /**
     * Age of a stored driver timestamp, in seconds, on the app's one basis.
     *
     * Both operands are Philippine wall-clock time: the stored value was
     * written on the driver pipeline's Asia/Manila basis, and "now" is taken on
     * the same zone, so the two are on one basis by construction and their
     * difference is real elapsed time. A fresh fix therefore reads as a small
     * age instead of being 8 hours in the future.
     *
     * A record stamped AHEAD of the server clock is clock skew, not a fresh
     * position, and it is not trusted. Only a few seconds of jitter is forgiven
     * (the writer and the reader are two clock reads of the same instant, so
     * they can disagree slightly); anything further ahead returns a null age,
     * which passengerDriverFreshnessState() treats as STALE, so a wrongly
     * stamped row can never unlock a destination alert. The basis label records
     * the anomaly so it shows up in the diagnostics instead of silently either
     * alerting or blocking.
     *
     * @param string|null $reference Current time on the same basis. Pass the
     *                                value already used for the response's
     *                                server_current_time so the reported age and
     *                                the reported clock are one reading, not two.
     *
     * @return array{age_seconds:?int,basis:?string,skew_seconds:?int}
     */
    function passengerDriverRecordAge(PDO $conn, $timestamp, $reference = null)
    {
        $timestamp = trim((string) $timestamp);
        if ($timestamp === '') {
            return ['age_seconds' => null, 'basis' => null, 'skew_seconds' => null];
        }

        $basis = passengerDriverTimeBasis();

        try {
            $zone = new DateTimeZone($basis['timezone']);
            $moment = new DateTimeImmutable($timestamp, $zone);
        } catch (\Throwable $e) {
            return ['age_seconds' => null, 'basis' => null, 'skew_seconds' => null];
        }

        // Prefer the caller's reference so the age and the reported
        // server_current_time come from the same clock read. Fall back to
        // reading it here, then to PHP only if the database cannot answer.
        $referenceValue = is_array($reference) ? trim((string) ($reference['value'] ?? '')) : trim((string) $reference);
        if ($referenceValue === '') {
            $referenceValue = trim((string) (passengerDriverReferenceTime($conn)['value'] ?? ''));
        }

        if ($referenceValue !== '') {
            try {
                $now = new DateTimeImmutable($referenceValue, $zone);
            } catch (\Throwable $e) {
                $now = new DateTimeImmutable('now', $zone);
            }
        } else {
            try {
                $now = new DateTimeImmutable('now', $zone);
            } catch (\Throwable $e) {
                return ['age_seconds' => null, 'basis' => null, 'skew_seconds' => null];
            }
        }

        $age = $now->getTimestamp() - $moment->getTimestamp();

        if ($age < -PASSENGER_DRIVER_CLOCK_SKEW_TOLERANCE_SECONDS) {
            return [
                'age_seconds' => null,
                'basis' => 'clock_skew',
                'skew_seconds' => $age,
            ];
        }

        return [
            'age_seconds' => $age >= 0 ? $age : 0,
            'basis' => $basis['timezone'],
            'skew_seconds' => $age < 0 ? $age : 0,
        ];
    }
}

if (!function_exists('passengerDriverAgeSeconds')) {
    /**
     * Age of a stored driver timestamp in seconds, computed on one clock.
     * Kept as the scalar form of passengerDriverRecordAge() for callers that
     * only need the number.
     */
    function passengerDriverAgeSeconds(PDO $conn, $updatedAt)
    {
        $age = passengerDriverRecordAge($conn, $updatedAt);

        return $age['age_seconds'];
    }
}

if (!function_exists('passengerDriverFreshnessState')) {
    /**
     * @param int|null $ageSeconds
     * @return string FRESH|AGING|STALE
     */
    function passengerDriverFreshnessState($ageSeconds)
    {
        if ($ageSeconds === null) {
            return 'STALE';
        }

        if ($ageSeconds <= FRESHNESS_FRESH_SECONDS) {
            return 'FRESH';
        }

        if ($ageSeconds <= FRESHNESS_AGING_SECONDS) {
            return 'AGING';
        }

        return 'STALE';
    }
}

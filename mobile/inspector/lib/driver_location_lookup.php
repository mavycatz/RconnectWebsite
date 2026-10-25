<?php

/**
 * Shared lookup helpers for the Inspector driver-location read path.
 *
 * Consumed by backend/mobile/inspector/live_map.php. Every function is guarded
 * with function_exists() so the file is safe to require more than once, and the
 * file must never require anything outside this repository: it is loaded before
 * a database connection exists, and tests/driver_location_lookup_test.php
 * exercises it with no database, no config/database.php, and no network.
 *
 * Clock basis
 * -----------
 * driver_current_location.updated_at is written by the Driver app through
 * Rconnect-Drive/scripts/updateLocation.php, which formats it as Asia/Manila
 * wall-clock text through an explicit DateTimeZone and never through the
 * session default. A reader that compares that text against the MySQL session
 * clock inherits whatever zone the session happens to be in; when the two
 * disagree, a row written seconds ago reads as eight hours old. That failure
 * has already happened once in this codebase (see
 * Rconnect-Drive/scripts/lib/location_helpers.php:13-17).
 *
 * So every clock value produced here is formatted and parsed through the single
 * declared zone in INSPECTOR_LOCATION_TIMEZONE, and no function in this file
 * consults the database clock at all. tests/driver_location_lookup_test.php
 * enforces that with a token-level scan of this file.
 *
 * Staleness
 * ---------
 * Age is reported, never enforced. inspector_location_is_stale() produces a
 * flag for the client to render; nothing in this file or in live_map.php
 * excludes a row on the basis of its age. A row that exists is a row that gets
 * returned.
 */

if (!defined('INSPECTOR_LOCATION_TIMEZONE')) {
    define('INSPECTOR_LOCATION_TIMEZONE', 'Asia/Manila');
}

/*
 * Age, in seconds, past which a reported location is flagged as not fresh.
 * This matches the only staleness precedent in the sibling project
 * (Rconnect-Drive/api/driver/lib/near_stop.php, 600 seconds). It is a reported
 * flag only: no query in this read path filters on it, so the value can be
 * retuned without any risk of hiding a location.
 *
 * A location counts as stale strictly past the threshold: an age of exactly
 * INSPECTOR_LOCATION_STALE_SECONDS is still fresh.
 */
if (!defined('INSPECTOR_LOCATION_STALE_SECONDS')) {
    define('INSPECTOR_LOCATION_STALE_SECONDS', 600);
}

if (!function_exists('inspector_location_timezone')) {
    /**
     * The single clock basis shared with the writer of updated_at.
     *
     * @return string An IANA time zone identifier.
     */
    function inspector_location_timezone()
    {
        return INSPECTOR_LOCATION_TIMEZONE;
    }
}

if (!function_exists('inspector_location_stale_seconds')) {
    /**
     * The reported-staleness threshold, in seconds.
     *
     * @return int
     */
    function inspector_location_stale_seconds()
    {
        return (int) INSPECTOR_LOCATION_STALE_SECONDS;
    }
}

if (!function_exists('inspector_location_now')) {
    /**
     * The current instant as wall-clock text in the declared zone.
     *
     * Formatted through an explicit DateTimeZone so the value never depends on
     * the session or host default. The output is 'Y-m-d H:i:s', which is the
     * same textual form the writer stores in updated_at, so the two can be
     * subtracted without either side being reinterpreted.
     *
     * @return string
     */
    function inspector_location_now()
    {
        $tz = new DateTimeZone(inspector_location_timezone());

        return (new DateTime('now', $tz))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('inspector_location_parse_timestamp')) {
    /**
     * Parse a stored timestamp to a Unix timestamp on the declared basis.
     *
     * A stored 'Y-m-d H:i:s' carries no offset, so it is interpreted in
     * inspector_location_timezone() rather than the session default. Text that
     * does carry an explicit offset keeps it, which is correct: that value
     * already states its own basis.
     *
     * Returns null for anything uninterpretable, including null, an empty or
     * whitespace string, and a MySQL zero date. The caller turns null into an
     * unknown age, never into a stale reading.
     *
     * @param mixed $value Raw stored value.
     * @return int|null Unix timestamp, or null when unparseable.
     */
    function inspector_location_parse_timestamp($value)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_int($value) || is_float($value)) {
            if (!is_finite((float) $value)) {
                return null;
            }
            return (int) floor((float) $value);
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        // A MySQL zero date is a placeholder, not an instant.
        if (strpos($trimmed, '0000-00-00') === 0) {
            return null;
        }

        try {
            $parsed = new DateTime($trimmed, new DateTimeZone(inspector_location_timezone()));
        } catch (Throwable $e) {
            return null;
        }

        // Guard against DateTime silently rolling an impossible date forward.
        $errors = DateTime::getLastErrors();
        if (is_array($errors) && (!empty($errors['warning_count']) || !empty($errors['error_count']))) {
            return null;
        }

        return $parsed->getTimestamp();
    }
}

if (!function_exists('inspector_location_age_seconds')) {
    /**
     * Age, in seconds, of a stored timestamp against the server clock.
     *
     * Both arguments are parsed on the declared basis by
     * inspector_location_parse_timestamp(), so a row written moments ago
     * measures as seconds old rather than as however far the MySQL session
     * zone happens to sit from Manila.
     *
     * The result is signed and is NOT clamped: a stamp ahead of the server
     * clock yields a negative age, which is device or database clock skew and
     * is worth seeing rather than hiding. Clamping happens only in
     * inspector_location_is_stale(), which treats a future stamp as not stale.
     *
     * @param mixed $storedAt  Stored timestamp text.
     * @param mixed $serverNow Server timestamp text; defaults to now.
     * @return int|null Signed age in seconds, or null when either side is
     *                  unparseable.
     */
    function inspector_location_age_seconds($storedAt, $serverNow = null)
    {
        $stored = inspector_location_parse_timestamp($storedAt);
        if ($stored === null) {
            return null;
        }

        if ($serverNow === null) {
            $serverNow = inspector_location_now();
        }

        $now = inspector_location_parse_timestamp($serverNow);
        if ($now === null) {
            return null;
        }

        return $now - $stored;
    }
}

if (!function_exists('inspector_location_is_stale')) {
    /**
     * Whether a reported location should be presented as not fresh.
     *
     * Strictly greater than the threshold, so an age of exactly
     * inspector_location_stale_seconds() is still fresh. A negative age (the
     * stamp is ahead of the server clock) is not stale: forward skew is a
     * clock problem, not an old location.
     *
     * An unknown age is neither fresh nor stale. It returns null so the client
     * can tell "we could not measure this" apart from "this is old", and
     * neither reading causes the row to be withheld.
     *
     * @param int|null $ageSeconds Signed age in seconds.
     * @return bool|null
     */
    function inspector_location_is_stale($ageSeconds)
    {
        if ($ageSeconds === null || $ageSeconds === '') {
            return null;
        }

        if (!is_numeric($ageSeconds)) {
            return null;
        }

        $age = (int) $ageSeconds;
        if ($age < 0) {
            return false;
        }

        return $age > inspector_location_stale_seconds();
    }
}

/*
 * ==========================================================================
 * BUS IDENTITY
 *
 * A bus is addressable by more than one value in this schema. get_passengers.php
 * already carries a workaround for it ("bus_id = ? OR bus_id = (SELECT id FROM
 * buses WHERE plate_number = ?)"), and InspectorGPS.tsx documents that the value
 * returned as assigned_bus sometimes arrives with a "Bus-" display prefix. So a
 * bus reference is treated as a set of candidate values to try, not as one
 * value, and the form that matched is reported back.
 * ==========================================================================
 */

/**
 * Candidate values for a bare 'bus[\s-]*' display prefix, in preference order.
 * Mirrors normalizeBusNumber() in app/inspector/(tabs)/InspectorGPS.tsx.
 */
if (!defined('INSPECTOR_LOCATION_BUS_PREFIX_PATTERN')) {
    define('INSPECTOR_LOCATION_BUS_PREFIX_PATTERN', '/^bus[\s\-_]+/i');
}

/*
 * Columns that may address a bus, in preference order. 'id' is included because
 * get_passengers.php resolves a bus through buses.id. The list is intersected
 * with the columns a table actually has before use, so an absent column is
 * never named in a query.
 */
if (!defined('INSPECTOR_LOCATION_BUS_COLUMNS')) {
    define('INSPECTOR_LOCATION_BUS_COLUMNS', 'bus_id,id,bus_no,plate_number');
}

if (!function_exists('inspector_location_bus_candidates')) {
    /**
     * The ordered candidate values for a bus reference.
     *
     * The raw trimmed value comes first, then the value with any leading
     * 'Bus' display prefix removed. The raw value is kept because the stored
     * column may itself carry the prefix; the stripped value is added because
     * the stored column may be the bare number instead.
     *
     * An empty or absent reference yields no candidates, which callers treat as
     * "no bus to resolve" rather than as a bus named ''.
     *
     * @param mixed $raw Client-supplied bus reference.
     * @return string[] Ordered, de-duplicated candidate values.
     */
    function inspector_location_bus_candidates($raw)
    {
        if ($raw === null || is_array($raw) || is_object($raw)) {
            return array();
        }

        $trimmed = trim((string) $raw);
        if ($trimmed === '') {
            return array();
        }

        $candidates = array($trimmed);

        $stripped = trim(preg_replace(INSPECTOR_LOCATION_BUS_PREFIX_PATTERN, '', $trimmed));
        if ($stripped !== '' && $stripped !== $trimmed) {
            $candidates[] = $stripped;
        }

        return array_values(array_unique($candidates));
    }
}

if (!function_exists('inspector_location_bus_columns')) {
    /**
     * The bus-addressing columns a table actually has, in preference order.
     *
     * @param string[] $columns Column names as reported by the table.
     * @return string[] Declared column names, ordered by preference.
     */
    function inspector_location_bus_columns(array $columns)
    {
        $present = array();
        foreach ($columns as $column) {
            $present[strtolower((string) $column)] = (string) $column;
        }

        $found = array();
        foreach (explode(',', INSPECTOR_LOCATION_BUS_COLUMNS) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && isset($present[strtolower($candidate)])) {
                $found[] = $present[strtolower($candidate)];
            }
        }

        return $found;
    }
}

if (!function_exists('inspector_location_column_in')) {
    /**
     * The declared spelling of a column in $columns, matched case-insensitively.
     *
     * @param string[] $columns Column names as reported by the table.
     * @param string   $wanted  Column to look for.
     * @return string|null The declared spelling, or null when absent.
     */
    function inspector_location_column_in(array $columns, $wanted)
    {
        foreach ($columns as $column) {
            if (strcasecmp((string) $column, $wanted) === 0) {
                return (string) $column;
            }
        }

        return null;
    }
}

/*
 * ==========================================================================
 * SCHEMA PROBING
 *
 * No DDL for driver_current_location exists in any of the three projects, and
 * the identifier it is keyed on has been observed only through the statements
 * Rconnect-Drive/scripts/updateLocation.php issues. So the table is probed and
 * the key column is read from the result rather than assumed, which turns a
 * schema drift into a reported fact instead of a silently empty map.
 * ==========================================================================
 */

/*
 * Key columns for driver_current_location, in preference order. 'driver_id' is
 * what every writer in this codebase uses; the rest are the account-level
 * spellings a differently-keyed deployment might use. These are distinct
 * concepts and are never substituted for one another: whichever column is
 * chosen here is reported as location_key_column so the client and the operator
 * both know which identity the row was matched on.
 */
if (!defined('INSPECTOR_LOCATION_KEY_COLUMNS')) {
    define('INSPECTOR_LOCATION_KEY_COLUMNS', 'driver_id,driver_account_id,driver_id_account,user_id');
}

if (!defined('INSPECTOR_LOCATION_COORDINATE_COLUMNS')) {
    define('INSPECTOR_LOCATION_COORDINATE_COLUMNS', 'latitude,longitude');
}

if (!function_exists('inspector_location_table_columns')) {
    /**
     * The columns a table declares, read with SHOW COLUMNS.
     *
     * Errors are suppressed and reported as "no columns" rather than raised:
     * this is an optional probe, and the caller turns an empty result into a
     * schema-problem message that names what it did find. That is the same
     * optional-column pattern get_passengers.php uses for passenger_trips.
     *
     * $table is interpolated, so it is restricted to a bare identifier here.
     *
     * @param mixed  $conn  mysqli connection, or any object exposing query().
     * @param string $table Table name.
     * @return string[]
     */
    function inspector_location_table_columns($conn, $table)
    {
        if (!is_object($conn) || !method_exists($conn, 'query')) {
            return array();
        }

        $table = trim((string) $table);
        if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return array();
        }

        $columns = array();

        $result = @$conn->query("SHOW COLUMNS FROM `$table`");
        if (!$result || !method_exists($result, 'fetch_assoc')) {
            return $columns;
        }

        while ($row = $result->fetch_assoc()) {
            if (isset($row['Field']) && trim((string) $row['Field']) !== '') {
                $columns[] = trim((string) $row['Field']);
            }
        }

        return $columns;
    }
}

if (!function_exists('inspector_location_location_key_column')) {
    /**
     * The column driver_current_location is actually keyed on.
     *
     * @param string[] $columns Column names as reported by the table.
     * @return string|null Declared column name, or null when none is recognized.
     */
    function inspector_location_location_key_column(array $columns)
    {
        foreach (explode(',', INSPECTOR_LOCATION_KEY_COLUMNS) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $declared = inspector_location_column_in($columns, $candidate);
            if ($declared !== null) {
                return $declared;
            }
        }

        return null;
    }
}

if (!function_exists('inspector_location_coordinate_columns')) {
    /**
     * The coordinate columns a table actually has.
     *
     * @param string[] $columns Column names as reported by the table.
     * @return string[] Declared column names, ordered by preference.
     */
    function inspector_location_coordinate_columns(array $columns)
    {
        $found = array();
        foreach (explode(',', INSPECTOR_LOCATION_COORDINATE_COLUMNS) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $declared = inspector_location_column_in($columns, $candidate);
            if ($declared !== null) {
                $found[] = $declared;
            }
        }

        return $found;
    }
}

if (!function_exists('inspector_location_schema_problem')) {
    /**
     * Why driver_current_location cannot be read, or null when it can be.
     *
     * Reports the columns that were found rather than substituting zeros, so a
     * schema that does not match anything this codebase knows about is visible
     * instead of reading as "no location".
     *
     * @param string[] $columns Column names as reported by the table.
     * @return string|null
     */
    function inspector_location_schema_problem(array $columns)
    {
        $found = empty($columns) ? '(none)' : implode(', ', $columns);

        if (empty($columns)) {
            return "driver_current_location exposes no columns; found: $found";
        }

        if (inspector_location_location_key_column($columns) === null) {
            return 'driver_current_location exposes no recognized driver key column ('
                . INSPECTOR_LOCATION_KEY_COLUMNS . '); found: ' . $found;
        }

        $coordinates = inspector_location_coordinate_columns($columns);
        if (count($coordinates) < 2) {
            return 'driver_current_location is missing a coordinate column ('
                . INSPECTOR_LOCATION_COORDINATE_COLUMNS . '); found: ' . $found;
        }

        return null;
    }
}

/*
 * ==========================================================================
 * DRIVER RESOLUTION
 *
 * The driver is resolved from the requesting inspector's own assignment, then
 * that trip's driver. A bus identifier supplied by the client is only a
 * fallback and a cross-check: the app holds it in local state and it can be
 * stale, so it never overrides what the assignment says.
 *
 * The four lookups are injected as callbacks so the chain can be exercised
 * without a database. Each callback receives the values relevant to its stage
 * and returns either null (nothing found) or an array. An array carrying a
 * 'row' key is the structured form and may also carry 'matched_on'; any other
 * array is treated as the row itself.
 * ==========================================================================
 */

/**
 * Normalise a resolver callback's return value.
 *
 * The 'row' a stage gets back is not always a table row: the standing
 * bus-to-driver stage resolves to a bare driver identifier. So the value is
 * passed through as-is and each stage decides how to read it. Only an absent,
 * empty, or zero-length value counts as "nothing found".
 *
 * @param mixed $result Raw callback return.
 * @return array{row: mixed, matched_on: string|null}
 */
function inspector_location_unwrap_resolver_result($result)
{
    if (!is_array($result)) {
        if ($result === null || $result === '') {
            return array('row' => null, 'matched_on' => null);
        }

        return array('row' => $result, 'matched_on' => null);
    }

    if (array_key_exists('row', $result)) {
        $row = $result['row'];
        $matchedOn = isset($result['matched_on']) ? (string) $result['matched_on'] : null;

        if ($row === null || $row === '' || $row === array()) {
            return array('row' => null, 'matched_on' => $matchedOn);
        }

        return array('row' => $row, 'matched_on' => $matchedOn);
    }

    if ($result === array()) {
        return array('row' => null, 'matched_on' => null);
    }

    return array('row' => $result, 'matched_on' => null);
}

if (!function_exists('inspector_location_resolve_driver')) {
    /**
     * Resolve the driver whose location should be reported for an inspector.
     *
     * Chain, in order:
     *   1. the inspector's checked-in assignment, then that trip's driver
     *   2. the supplied bus identifier, then the latest open trip for it
     *   3. the supplied bus identifier, then the standing bus-to-driver
     *      assignment
     *
     * Steps 2 and 3 run only when step 1 did not produce a driver, so a
     * disagreeing client bus identifier can never redirect the lookup away from
     * the inspector's own trip; it is recorded as bus_id_mismatch instead.
     *
     * @param array $resolvers Callbacks: checked_in_assignment, trip_by_id,
     *                         trip_by_bus, driver_by_bus.
     * @param array $input     inspector_id, bus_id, bus_candidates.
     * @return array Resolution outcome plus a per-stage trace. first_failure is
     *               null whenever a driver was resolved.
     */
    function inspector_location_resolve_driver(array $resolvers, array $input)
    {
        $inspectorId = isset($input['inspector_id']) ? trim((string) $input['inspector_id']) : '';
        $clientBusId = isset($input['bus_id']) ? trim((string) $input['bus_id']) : '';

        if (isset($input['bus_candidates']) && is_array($input['bus_candidates'])) {
            $candidates = array_values($input['bus_candidates']);
        } else {
            $candidates = inspector_location_bus_candidates($clientBusId);
        }

        $result = array(
            'driver_id' => null,
            'trip_id' => null,
            'bus_id' => null,
            'resolution_source' => 'none',
            'bus_id_mismatch' => false,
            'matched_on' => null,
            'first_failure' => null,
            'stages' => array(),
        );

        $stages = &$result['stages'];
        $firstFailure = null;

        $noteFailure = function ($stage) use (&$firstFailure) {
            if ($firstFailure === null) {
                $firstFailure = $stage;
            }
        };

        // ---- stage: inspector identity -------------------------------
        $stages['inspector'] = array(
            'input' => $inspectorId,
            'attempted' => $inspectorId !== '',
            'matched' => $inspectorId !== '',
        );

        // ---- stage: checked-in assignment ----------------------------
        $assignment = array('row' => null, 'matched_on' => null);
        $trip = array('row' => null, 'matched_on' => null);

        if ($inspectorId !== '' && isset($resolvers['checked_in_assignment']) && is_callable($resolvers['checked_in_assignment'])) {
            $assignment = inspector_location_unwrap_resolver_result(
                call_user_func($resolvers['checked_in_assignment'], $inspectorId)
            );

            if ($assignment['row'] !== null && is_array($assignment['row'])) {
                $stages['assignment'] = array(
                    'input' => $inspectorId,
                    'attempted' => true,
                    'rows' => 1,
                    'trip_id' => isset($assignment['row']['trip_id']) ? $assignment['row']['trip_id'] : null,
                    'bus_id' => isset($assignment['row']['bus_id']) ? $assignment['row']['bus_id'] : null,
                );

                $tripId = isset($assignment['row']['trip_id']) ? $assignment['row']['trip_id'] : null;
                if ($tripId !== null && $tripId !== '' && isset($resolvers['trip_by_id']) && is_callable($resolvers['trip_by_id'])) {
                    $trip = inspector_location_unwrap_resolver_result(
                        call_user_func($resolvers['trip_by_id'], $tripId)
                    );
                }
            } else {
                $stages['assignment'] = array('input' => $inspectorId, 'attempted' => true, 'rows' => 0);
                $noteFailure('assignment');
            }
        } else {
            $stages['assignment'] = array('input' => $inspectorId, 'attempted' => false, 'skipped' => 'no inspector_id');
        }

        // ---- stage: trip for the assignment --------------------------
        if ($trip['row'] !== null && is_array($trip['row'])) {
            $tripDriverId = isset($trip['row']['driver_id']) ? trim((string) $trip['row']['driver_id']) : '';
            $stages['trip'] = array(
                'input' => isset($assignment['row']['trip_id']) ? $assignment['row']['trip_id'] : null,
                'attempted' => true,
                'rows' => 1,
                'driver_id' => $tripDriverId === '' ? null : $tripDriverId,
                'bus_id' => isset($trip['row']['bus_id']) ? $trip['row']['bus_id'] : null,
                'status' => isset($trip['row']['status']) ? $trip['row']['status'] : null,
            );

            if ($tripDriverId !== '') {
                $assignmentBusId = isset($assignment['row']['bus_id']) ? trim((string) $assignment['row']['bus_id']) : '';

                $result['driver_id'] = $tripDriverId;
                $result['trip_id'] = isset($trip['row']['id']) ? $trip['row']['id'] : (isset($assignment['row']['trip_id']) ? $assignment['row']['trip_id'] : null);
                $result['bus_id'] = isset($trip['row']['bus_id']) && trim((string) $trip['row']['bus_id']) !== ''
                    ? trim((string) $trip['row']['bus_id'])
                    : $assignmentBusId;
                $result['resolution_source'] = 'inspector_assignment';

                // The client bus identifier is a cross-check only. It agrees
                // with the assignment when any of its candidate forms matches
                // the assignment's bus, so a "Bus-4" display form is not
                // reported as a disagreement with a stored "4". Compared
                // case-insensitively, because a casing difference is noise
                // rather than a second bus.
                if ($clientBusId !== '' && $assignmentBusId !== '') {
                    $assignmentForms = array(
                        strtoupper($assignmentBusId),
                        strtoupper(trim(preg_replace(INSPECTOR_LOCATION_BUS_PREFIX_PATTERN, '', $assignmentBusId))),
                    );

                    $agrees = false;
                    foreach ($candidates as $candidate) {
                        if (in_array(strtoupper((string) $candidate), $assignmentForms, true)) {
                            $agrees = true;
                            break;
                        }
                    }

                    $result['bus_id_mismatch'] = !$agrees;
                }

                $result['first_failure'] = null;
                unset($stages);

                return $result;
            }

            $noteFailure('trip_driver');
        } else {
            $stages['trip'] = array('input' => null, 'attempted' => false, 'skipped' => 'no assignment trip');
        }

        // ---- stage: bus candidates -----------------------------------
        $stages['bus'] = array(
            'input' => $clientBusId,
            'attempted' => !empty($candidates),
            'candidates' => $candidates,
        );

        // ---- stage: latest open trip for the bus ---------------------
        if (!empty($candidates) && isset($resolvers['trip_by_bus']) && is_callable($resolvers['trip_by_bus'])) {
            $busTrip = inspector_location_unwrap_resolver_result(
                call_user_func($resolvers['trip_by_bus'], $candidates)
            );

            if ($busTrip['row'] !== null && is_array($busTrip['row'])) {
                $stages['bus_trip'] = array(
                    'input' => $clientBusId,
                    'attempted' => true,
                    'rows' => 1,
                    'matched_on' => $busTrip['matched_on'],
                    'driver_id' => isset($busTrip['row']['driver_id']) ? $busTrip['row']['driver_id'] : null,
                );

                $busTripDriverId = isset($busTrip['row']['driver_id']) ? trim((string) $busTrip['row']['driver_id']) : '';
                if ($busTripDriverId !== '') {
                    $result['driver_id'] = $busTripDriverId;
                    $result['trip_id'] = isset($busTrip['row']['id']) ? $busTrip['row']['id'] : null;
                    $result['bus_id'] = isset($busTrip['row']['bus_id']) ? $busTrip['row']['bus_id'] : $clientBusId;
                    $result['resolution_source'] = 'bus_trip_by_bus_id';
                    $result['matched_on'] = $busTrip['matched_on'];
                    $result['first_failure'] = null;
                    unset($stages);

                    return $result;
                }

                $noteFailure('bus_trip_driver');
            } else {
                $stages['bus_trip'] = array('input' => $clientBusId, 'attempted' => true, 'rows' => 0);
                $noteFailure('bus_trip');
            }
        } else {
            $stages['bus_trip'] = array('input' => $clientBusId, 'attempted' => false, 'skipped' => 'no bus candidates');
            $noteFailure('bus_trip');
        }

        // ---- stage: standing bus-to-driver assignment -----------------
        if (!empty($candidates) && isset($resolvers['driver_by_bus']) && is_callable($resolvers['driver_by_bus'])) {
            $standing = inspector_location_unwrap_resolver_result(
                call_user_func($resolvers['driver_by_bus'], $candidates)
            );

            if ($standing['row'] !== null) {
                $standingDriverId = trim((string) $standing['row']);
                $stages['driver_assignment'] = array(
                    'input' => $clientBusId,
                    'attempted' => true,
                    'rows' => 1,
                    'matched_on' => $standing['matched_on'],
                    'driver_id' => $standingDriverId,
                );

                if ($standingDriverId !== '') {
                    $result['driver_id'] = $standingDriverId;
                    $result['bus_id'] = $clientBusId;
                    $result['resolution_source'] = 'driver_bus_assignment';
                    $result['matched_on'] = $standing['matched_on'];
                    $result['first_failure'] = null;
                    unset($stages);

                    return $result;
                }

                $noteFailure('driver_assignment');
            } else {
                $stages['driver_assignment'] = array('input' => $clientBusId, 'attempted' => true, 'rows' => 0);
                $noteFailure('driver_assignment');
            }
        } else {
            $stages['driver_assignment'] = array('input' => $clientBusId, 'attempted' => false, 'skipped' => 'no bus candidates');
            $noteFailure('driver_assignment');
        }

        $result['first_failure'] = $firstFailure === null ? 'driver' : $firstFailure;
        unset($stages);

        return $result;
    }
}

/*
 * ==========================================================================
 * READING THE LOCATION ROW
 *
 * The one rule this section exists to enforce: a row that exists is a row that
 * gets returned. There is no time predicate anywhere below, because a
 * staleness window written against the database clock is precisely what made
 * live rows read as eight hours old in this codebase before. Freshness is
 * computed afterwards, in PHP, on the declared clock basis, and reported.
 * ==========================================================================
 */

if (!defined('INSPECTOR_LOCATION_TABLE')) {
    define('INSPECTOR_LOCATION_TABLE', 'driver_current_location');
}

/*
 * The column set every writer in this codebase produces, used when the caller
 * has not probed the table. Probing is preferred; this is the fallback so the
 * read still works against a schema that reports no columns at all.
 */
if (!defined('INSPECTOR_LOCATION_DEFAULT_COLUMNS')) {
    define('INSPECTOR_LOCATION_DEFAULT_COLUMNS', 'id,driver_id,bus_id,latitude,longitude,speed,updated_at');
}

/*
 * ==========================================================================
 * INSPECTOR POSITION WRITE PATH
 *
 * The table the Inspector app writes its own position to. It is a different table
 * from INSPECTOR_LOCATION_TABLE above: that one is the driver's, written by the
 * Driver app. Keeping the two apart is the point - the write path must not assume
 * the read path's key column or column set exists here.
 *
 * Declared rather than assumed: the schema is probed at request time by
 * inspector_location_write_schema_problem() below, and a table that does not match
 * is reported by name rather than written into.
 * ==========================================================================
 */

if (!defined('INSPECTOR_WRITE_TABLE')) {
    define('INSPECTOR_WRITE_TABLE', 'inspectors_location');
}

/** Columns the write path cannot proceed without, in the order they are reported. */
if (!defined('INSPECTOR_WRITE_REQUIRED_COLUMNS')) {
    define('INSPECTOR_WRITE_REQUIRED_COLUMNS', 'inspector_id,latitude,longitude');
}

/** Optional columns, written only when the table actually declares them. */
if (!defined('INSPECTOR_WRITE_OPTIONAL_COLUMNS')) {
    define('INSPECTOR_WRITE_OPTIONAL_COLUMNS', 'bus_id,updated_at,accuracy,speed');
}

if (!function_exists('inspector_location_write_schema_problem')) {
    /**
     * Why inspectors_location cannot be written, or null when it can be.
     *
     * The probe is deliberate: assuming `latitude`, `longitude` and `inspector_id`
     * exist would surface as a SQL error naming a column, which says nothing about
     * what the table actually looks like. Reporting the columns that were found
     * turns the same failure into something a person can act on before any write
     * is attempted.
     *
     * @param string[] $columns Column names as reported by SHOW COLUMNS.
     * @return string|null
     */
    function inspector_location_write_schema_problem(array $columns)
    {
        $found = empty($columns) ? '(none)' : implode(', ', $columns);

        if (empty($columns)) {
            return INSPECTOR_WRITE_TABLE . ' exposes no columns; found: ' . $found;
        }

        $missing = array();
        foreach (explode(',', INSPECTOR_WRITE_REQUIRED_COLUMNS) as $required) {
            $required = trim($required);
            if ($required === '') {
                continue;
            }

            if (inspector_location_column_in($columns, $required) === null) {
                $missing[] = $required;
            }
        }

        if (!empty($missing)) {
            return INSPECTOR_WRITE_TABLE . ' is missing ' . implode(', ', $missing)
                . ' (' . INSPECTOR_WRITE_REQUIRED_COLUMNS . '); found: ' . $found;
        }

        return null;
    }
}

if (!function_exists('inspector_location_write_optional_columns')) {
    /**
     * The optional columns this table declares, in write order.
     *
     * @param string[] $columns Column names as reported by SHOW COLUMNS.
     * @return string[] Declared column names only.
     */
    function inspector_location_write_optional_columns(array $columns)
    {
        $found = array();
        foreach (explode(',', INSPECTOR_WRITE_OPTIONAL_COLUMNS) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $declared = inspector_location_column_in($columns, $candidate);
            if ($declared !== null) {
                $found[] = $declared;
            }
        }

        return $found;
    }
}

if (!function_exists('inspector_location_write_coordinate')) {
    /**
     * Validate one coordinate for writing.
     *
     * A request whose latitude or longitude is absent, non-numeric, or not finite
     * is rejected here and nothing is written. Storing 0 for an unusable reading
     * would place the inspector at Null Island, which reads as a real position and
     * is worse than having no row at all - the map would draw a confident marker
     * in the middle of the ocean.
     *
     * `is_numeric` accepts a numeric string, which is what JSON bodies and form
     * posts both deliver, so the cast happens only after the value has been shown
     * to be a number. INF and NAN are numeric to PHP and are excluded by
     * is_finite, which is the whole reason the second check exists.
     *
     * @param mixed $value Raw request value.
     * @return float|null The finite value, or null when unusable.
     */
    function inspector_location_write_coordinate($value)
    {
        if (is_bool($value) || $value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }
}

if (!function_exists('inspector_location_write_request')) {
    /**
     * The validated write request, or a reason it cannot be one.
     *
     * Kept as a decision function so the rules are assertable without a database
     * and so the endpoint itself contains no branching beyond handling the answer.
     *
     * @param mixed $inspectorId   Raw inspector_id.
     * @param mixed $latitude      Raw latitude.
     * @param mixed $longitude     Raw longitude.
     * @return array{ok: bool, reason: string|null, value: array|null}
     */
    function inspector_location_write_request($inspectorId, $latitude, $longitude)
    {
        $inspectorId = is_scalar($inspectorId) ? trim((string) $inspectorId) : '';

        if ($inspectorId === '') {
            return array(
                'ok' => false,
                // Distinct from the coordinate failures below so a caller can tell
                // a malformed request from an unusable position.
                'reason' => 'inspector_id_required',
                'value' => null,
            );
        }

        $lat = inspector_location_write_coordinate($latitude);
        if ($lat === null) {
            return array('ok' => false, 'reason' => 'latitude_unusable', 'value' => null);
        }

        $lng = inspector_location_write_coordinate($longitude);
        if ($lng === null) {
            return array('ok' => false, 'reason' => 'longitude_unusable', 'value' => null);
        }

        return array(
            'ok' => true,
            'reason' => null,
            'value' => array(
                'inspector_id' => $inspectorId,
                'latitude' => $lat,
                'longitude' => $lng,
            ),
        );
    }
}

if (!function_exists('inspector_write_assigned_bus')) {
    /**
     * The bus this inspector's position resolves to, read from the server.
     *
     * The app gates its trip poll and every route layer on `assigned_bus`, so this
     * is a read of live records and never an echo of anything the request carried.
     * A request that could name its own bus would let any caller assign itself to
     * any bus.
     *
     * The nearest driver within the proximity threshold is the assignment. No
     * driver row, or nothing inside the threshold, yields null - which the app
     * must be able to tell from a failed write, which is why the write path
     * reports that separately (see the response contract in inspector_location.php).
     *
     * @param mysqli  $conn        Connection.
     * @param string  $inspectorId Inspector whose assignment is resolved.
     * @param float   $latitude    Stored latitude.
     * @param float   $longitude   Stored longitude.
     * @return string|null Bus identifier, or null when none resolves.
     */
    function inspector_write_assigned_bus($conn, $inspectorId, $latitude, $longitude)
    {
        if (!is_object($conn) || !method_exists($conn, 'prepare')) {
            return null;
        }

        // Proximity in metres, computed in SQL rather than by fetching candidates:
        // the driver table is the one table here that grows with every posting
        // device, and the inspector's own row is the only thing constraining it.
        $sql = 'SELECT bus_id,'
            . ' (6371000 * ACOS('
            . '  LEAST(1, COS(RADIANS(?)) * COS(RADIANS(latitude))'
            . '  * COS(RADIANS(longitude) - RADIANS(?))'
            . '  + SIN(RADIANS(?)) * SIN(RADIANS(latitude))'
            . ' ))) AS distance'
            . ' FROM driver_current_location'
            . ' WHERE latitude IS NOT NULL AND longitude IS NOT NULL'
            . ' ORDER BY distance ASC'
            . ' LIMIT 1';

        $stmt = @$conn->prepare($sql);

        if (!$stmt) {
            return null;
        }

        $threshold = 300.0;

        $stmt->bind_param('ddd', $latitude, $longitude, $latitude);

        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        if (!is_array($row)) {
            return null;
        }

        $busId = isset($row['bus_id']) ? $row['bus_id'] : null;
        $distance = isset($row['distance']) ? (float) $row['distance'] : null;

        if ($busId === null || $busId === '' || $distance === null) {
            return null;
        }

        // A driver further away than the threshold is not this inspector's bus.
        // Without the check a stale driver row anywhere in the country would
        // assign every inspector to it.
        if ($distance > $threshold) {
            return null;
        }

        return (string) $busId;
    }
}

if (!function_exists('inspector_location_allowed_key_columns')) {
    /**
     * The identifier columns the location table may be keyed on.
     *
     * This is the allow-list the interpolated key column is checked against, so
     * a value that came from anywhere other than this list cannot reach the
     * query text.
     *
     * @return string[] Lowercased key column names.
     */
    function inspector_location_allowed_key_columns()
    {
        $allowed = array();
        foreach (explode(',', INSPECTOR_LOCATION_KEY_COLUMNS) as $candidate) {
            $candidate = strtolower(trim($candidate));
            if ($candidate !== '') {
                $allowed[] = $candidate;
            }
        }

        return $allowed;
    }
}

if (!function_exists('inspector_location_validate_key_column')) {
    /**
     * Accept a key column only if it is a known identifier AND declared by the
     * table.
     *
     * Both checks are required. The allow-list alone would still permit a
     * column this deployment does not have; the declared-columns check alone
     * would permit any column the table happens to expose.
     *
     * @param mixed  $keyColumn         Candidate column name.
     * @param string[] $declaredColumns  Columns reported for the table.
     * @return string|null The accepted column name, or null when rejected.
     */
    function inspector_location_validate_key_column($keyColumn, array $declaredColumns)
    {
        $keyColumn = trim((string) $keyColumn);
        if ($keyColumn === '') {
            return null;
        }

        if (!in_array(strtolower($keyColumn), inspector_location_allowed_key_columns(), true)) {
            return null;
        }

        if (empty($declaredColumns)) {
            return $keyColumn;
        }

        $declared = inspector_location_column_in($declaredColumns, $keyColumn);

        return $declared === null ? null : $declared;
    }
}

if (!function_exists('inspector_location_row_select_columns')) {
    /**
     * The columns to select, restricted to what the table declares.
     *
     * @param string[] $declaredColumns Columns reported for the table.
     * @param string   $keyColumn       Validated key column.
     * @return string[]
     */
    function inspector_location_row_select_columns(array $declaredColumns, $keyColumn)
    {
        $selected = array($keyColumn);

        $wanted = array('latitude', 'longitude', 'speed', 'updated_at', 'id');
        foreach ($wanted as $candidate) {
            $declared = inspector_location_column_in($declaredColumns, $candidate);
            if ($declared !== null) {
                $selected[] = $declared;
            }
        }

        return $selected;
    }
}

if (!function_exists('inspector_location_read_row')) {
    /**
     * Read the most recent location row for a driver.
     *
     * Orders by updated_at then id so the newest row wins, and degrades to id
     * alone when the table has no updated_at. Deliberately carries NO time
     * predicate: a row of any age is returned, and its age is reported
     * separately by inspector_location_location_payload().
     *
     * @param mixed    $conn            mysqli connection.
     * @param string   $driverId        Resolved driver identifier.
     * @param string   $keyColumn       Key column to match on.
     * @param bool     $hasUpdatedAt    Whether the table has updated_at.
     * @param string[] $declaredColumns Columns reported for the table; empty
     *                                 falls back to the documented default set.
     * @return array{row: array|null, sql: string, key_column: string|null,
     *               has_updated_at: bool, error: string|null}
     */
    function inspector_location_read_row($conn, $driverId, $keyColumn, $hasUpdatedAt = true, array $declaredColumns = array())
    {
        $outcome = array(
            'row' => null,
            'sql' => '',
            'key_column' => null,
            'has_updated_at' => (bool) $hasUpdatedAt,
            'error' => null,
        );

        $driverId = trim((string) $driverId);
        if ($driverId === '') {
            $outcome['error'] = 'no driver identifier to read a location for';
            return $outcome;
        }

        if (!is_object($conn) || !method_exists($conn, 'prepare')) {
            $outcome['error'] = 'no database connection';
            return $outcome;
        }

        if (empty($declaredColumns)) {
            $declaredColumns = array_values(array_filter(array_map(
                'trim',
                explode(',', INSPECTOR_LOCATION_DEFAULT_COLUMNS)
            )));
        }

        $acceptedKey = inspector_location_validate_key_column($keyColumn, $declaredColumns);
        if ($acceptedKey === null) {
            $outcome['error'] = 'unusable location key column: ' . (string) $keyColumn;
            return $outcome;
        }

        $outcome['key_column'] = $acceptedKey;

        $selected = inspector_location_row_select_columns($declaredColumns, $acceptedKey);

        $orderParts = array();
        if ($hasUpdatedAt) {
            $updatedAtColumn = inspector_location_column_in($declaredColumns, 'updated_at');
            if ($updatedAtColumn !== null) {
                $orderParts[] = '`' . $updatedAtColumn . '` DESC';
            }
        }

        $idColumn = inspector_location_column_in($declaredColumns, 'id');
        if ($idColumn !== null) {
            $orderParts[] = '`' . $idColumn . '` DESC';
        }

        $sql = 'SELECT ' . implode(', ', $selected)
            . ' FROM `' . INSPECTOR_LOCATION_TABLE . '`'
            . ' WHERE `' . $acceptedKey . '` = ?';

        if (!empty($orderParts)) {
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }

        $sql .= ' LIMIT 1';

        $outcome['sql'] = $sql;

        $stmt = @$conn->prepare($sql);
        if (!$stmt) {
            $outcome['error'] = 'location query could not be prepared';
            return $outcome;
        }

        $bindDriverId = $driverId;
        if (!@$stmt->bind_param('s', $bindDriverId)) {
            $outcome['error'] = 'location query could not bind the driver identifier';
            return $outcome;
        }

        if (!@$stmt->execute()) {
            $outcome['error'] = 'location query could not be executed';
            return $outcome;
        }

        if (method_exists($stmt, 'get_result')) {
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
        } else {
            $row = @$stmt->fetch_assoc();
        }

        $outcome['row'] = is_array($row) ? $row : null;

        return $outcome;
    }
}

if (!function_exists('inspector_location_row_value')) {
    /**
     * Read a value from a fetched row, matching the column case-insensitively.
     *
     * @param array  $row   Fetched row.
     * @param string $column Column to read.
     * @return mixed|null
     */
    function inspector_location_row_value(array $row, $column)
    {
        foreach ($row as $name => $value) {
            if (strcasecmp((string) $name, $column) === 0) {
                return $value;
            }
        }

        return null;
    }
}

if (!function_exists('inspector_location_location_payload')) {
    /**
     * Turn a fetched row into the reported driver location.
     *
     * Age is computed here, in PHP, on the declared clock basis, and an
     * unreadable updated_at yields null age and null staleness rather than
     * excluding the row. Coordinates that are not finite numbers yield null,
     * because a location that cannot be placed on a map is not a location.
     *
     * @param array|null $row      Fetched row, or null when there is none.
     * @param string|null $keyColumn Key column the row was matched on, so the
     *                               reported driver identity is the one the
     *                               row was actually found under.
     * @return array|null
     */
    function inspector_location_location_payload($row, $keyColumn = null)
    {
        if (!is_array($row) || empty($row)) {
            return null;
        }

        $latitude = inspector_location_row_value($row, 'latitude');
        $longitude = inspector_location_row_value($row, 'longitude');

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if (!is_finite($latitude) || !is_finite($longitude)) {
            return null;
        }

        $storedAt = inspector_location_row_value($row, 'updated_at');
        if ($storedAt !== null && trim((string) $storedAt) === '') {
            $storedAt = null;
        }

        $age = inspector_location_age_seconds($storedAt, inspector_location_now());

        $driverId = $keyColumn === null
            ? inspector_location_row_value($row, 'driver_id')
            : inspector_location_row_value($row, $keyColumn);

        if ($driverId === null && $keyColumn !== null) {
            $driverId = inspector_location_row_value($row, 'driver_id');
        }

        $speed = inspector_location_row_value($row, 'speed');

        return array(
            'driver_id' => $driverId === null ? null : trim((string) $driverId),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed' => is_numeric($speed) ? (float) $speed : null,
            'updated_at' => $storedAt === null ? null : (string) $storedAt,
            'age_seconds' => $age,
            'is_stale' => inspector_location_is_stale($age),
        );
    }
}

/*
 * ==========================================================================
 * ASSIGNMENT VERIFICATION
 *
 * Kept as a decision function so the rule can be asserted directly. The caller
 * performs the logging; this only says whether the read was verified and
 * whether the omission is worth recording.
 *
 * A request that supplies no inspector_id is still served, because an app
 * version predating this contract sends only bus_id, but it is reported as
 * unverified and the omission is recorded. That closes once the app ships
 * inspector_id, and it is a visible gap rather than a silent one until then.
 * ==========================================================================
 */

if (!function_exists('inspector_location_assignment_verification')) {
    /**
     * Whether a resolved location may be reported as this inspector's own.
     *
     * @param string $inspectorId Inspector identifier supplied by the caller.
     * @param array  $resolution  Output of inspector_location_resolve_driver().
     * @return array{verified: bool, reason: string, should_log: bool}
     */
    function inspector_location_assignment_verification($inspectorId, array $resolution)
    {
        $inspectorId = trim((string) $inspectorId);

        if ($inspectorId === '') {
            return array(
                'verified' => false,
                'reason' => 'inspector_id_missing',
                'should_log' => true,
            );
        }

        $source = isset($resolution['resolution_source']) ? (string) $resolution['resolution_source'] : 'none';

        if ($source === 'inspector_assignment') {
            return array(
                'verified' => true,
                'reason' => 'checked_in_assignment',
                'should_log' => false,
            );
        }

        return array(
            'verified' => false,
            'reason' => 'resolved_without_checked_in_assignment',
            'should_log' => false,
        );
    }
}

/**
 * The message describing why no location is being reported.
 *
 * The two "nothing was reported" cases are genuinely different and were being
 * reported as one. This function is only reached with a null payload, so a test of
 * `$payload` could only ever answer "coordinates_unusable": a driver who has never
 * been seen by the app was reported as a driver whose row held bad coordinates,
 * and the `no_location_row_for_driver` branch below was unreachable from
 * `live_map.php`. The row's existence is therefore an explicit argument rather
 * than something inferred from the payload that turned out to be absent.
 *
 * @param string|null $schemaProblem  Schema problem, when the table is unreadable.
 * @param string|null $driverId       Resolved driver, when there is one.
 * @param string|null $readError      Error from the row read, when there was one.
 * @param bool        $rowPresent     Whether a row was read for this driver. False
 *                                    means nothing has ever been written for them;
 *                                    true with no payload means the row exists but
 *                                    its coordinates are not usable.
 * @param array       $resolution     Resolution outcome.
 * @return string|null
 */
function inspector_location_absent_reason($schemaProblem, $driverId, $readError, $rowPresent, array $resolution)
{
    if ($schemaProblem !== null) {
        return $schemaProblem;
    }

    if ($driverId === null || $driverId === '') {
        $failed = isset($resolution['first_failure']) ? (string) $resolution['first_failure'] : 'driver';

        return 'driver_not_resolved:' . $failed;
    }

    if ($readError !== null) {
        return $readError;
    }

    if (!$rowPresent) {
        return 'no_location_row_for_driver';
    }

    return 'coordinates_unusable';
}

/*
 * ==========================================================================
 * DIAGNOSTIC TRACE
 *
 * The failing condition for this read path lived in unversioned production code
 * and could not be identified from source. The trace is what makes it
 * identifiable from a single response: every stage, the value it was given,
 * whether it was attempted, how many rows it returned, and the first stage that
 * produced nothing.
 *
 * It is a diagnostic, not an authorization mechanism, and it is scrubbed on the
 * way out: stage payloads pass through a deny-list so a resolver that
 * accidentally hands back a connection detail cannot leak it to a phone.
 * ==========================================================================
 */

if (!function_exists('inspector_location_scrub')) {
    /**
     * Recursively drop credential-shaped entries from a trace payload.
     *
     * @param mixed $value Trace fragment.
     * @return mixed
     */
    function inspector_location_scrub($value)
    {
        $deny = '/(^|_)(pass|passwd|password|secret|token|credential|credentials|dsn|connection_string|conn_str|db_host|db_user|db_pass|db_password|mysql_host|mysql_user|mysql_pass|api_key|apikey)($|_)/i';

        if (!is_array($value)) {
            return $value;
        }

        $clean = array();
        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match($deny, $key) === 1) {
                continue;
            }
            $clean[$key] = is_array($item) ? inspector_location_scrub($item) : $item;
        }

        return $clean;
    }
}

if (!function_exists('inspector_location_trace')) {
    /**
     * Build the debug trace for a resolution.
     *
     * @param array $resolution Output of inspector_location_resolve_driver().
     * @param array $extra      Additional stages to merge in, keyed by stage
     *                          name (the location table and row stages).
     * @return array
     */
    function inspector_location_trace(array $resolution, array $extra = array())
    {
        $stages = isset($resolution['stages']) && is_array($resolution['stages'])
            ? $resolution['stages']
            : array();

        if (!empty($extra)) {
            $stages = array_merge($stages, $extra);
        }

        return array(
            'first_failure' => isset($resolution['first_failure']) ? $resolution['first_failure'] : null,
            'stages' => inspector_location_scrub($stages),
            'server_current_time' => inspector_location_now(),
            'timezone' => inspector_location_timezone(),
        );
    }
}

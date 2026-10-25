<?php

/**
 * Driver/bus location resolution for passenger destination alerts.
 *
 * Reuses the existing Rconnect-Drive GPS pipeline unchanged: the driver app
 * posts to mobile/driver/updateLocation.php, which appends to
 * driver_location_history and upserts driver_current_location
 * (driver_id, bus_id, latitude, longitude, speed, updated_at).
 *
 * Position source priority: driver_location_history is the PRIMARY source for
 * "where the bus is" because it is the live stream the driver app writes every
 * few seconds. driver_current_location is a per-driver snapshot that only
 * advances when that driver posts, so it is the FALLBACK, used only when no
 * usable history record exists. Freshness is always measured from the record
 * that was actually selected (created_at for a history row, updated_at for a
 * snapshot), never from the other table's timestamp.
 *
 * The passenger's destination stays in passenger_trips.planned_dropoff_*
 * selected_destination*; this service supplies WHERE THE BUS IS for the alert
 * engine, matched to the passenger's active trip.
 *
 * Freshness states (calibrated to the ~5 s Rconnect-Drive cadence):
 *   FRESH  0 <= age <= FRESHNESS_FRESH_SECONDS (30 s)
 *   AGING  FRESHNESS_FRESH_SECONDS < age <= FRESHNESS_AGING_SECONDS (120 s)
 *   STALE  age > FRESHNESS_AGING_SECONDS, or no usable timestamp
 * FRESH and AGING locations may be evaluated; STALE locations never claim an
 * alert. Age is computed on the database clock inside the Asia/Manila session
 * the driver writer uses, so a timezone offset can never look like a real age.
 *
 * Trip ID spaces: passenger_trips.id identifies the passenger's trip;
 * bus_trips.id / bus_trips.trip_number identify the driver's trip. They are
 * never compared to each other.
 */

if (function_exists('passengerDriverLocationForTrip')) {
    return;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/fare-engine/utils/matchRouteStop.php';
require_once __DIR__ . '/ContinuationJourneyService.php';
require_once __DIR__ . '/DriverLocationSupport.php';

if (!defined('PASSENGER_DRIVER_HISTORY_CANDIDATE_LIMIT')) {
    /**
     * Newest history rows examined per driver (or per bus) before the next
     * matching strategy runs. Slightly wider than the snapshot path's LIMIT 5
     * so a few unusable leading rows (a lost GPS fix, or a reading written for
     * a bus the trip does not use) cannot hide the live one behind them.
     */
    define('PASSENGER_DRIVER_HISTORY_CANDIDATE_LIMIT', 8);
}

if (!defined('PASSENGER_DRIVER_TRAIL_LIMIT')) {
    /**
     * Recent bus positions kept for passed-destination detection. Bounded so
     * a long trip can never turn the trail into a per-driver table scan.
     */
    define('PASSENGER_DRIVER_TRAIL_LIMIT', 20);
}

if (!defined('PASSENGER_DRIVER_TRAIL_MAX_AGE_SECONDS')) {
    /**
     * Recency bound on the movement trail, matched to the freshness window so
     * a trail can never reach back to an earlier journey.
     */
    define('PASSENGER_DRIVER_TRAIL_MAX_AGE_SECONDS', 120);
}

if (!defined('PASSENGER_DESTINATION_HISTORY_TRAIL_LIMIT')) {
    /**
     * How many driver_location_history rows may be used as destination
     * evidence for one trip. This is a read bound, not a recency window: a bus
     * that reached the destination an hour ago must still be able to prove it.
     */
    define('PASSENGER_DESTINATION_HISTORY_TRAIL_LIMIT', 200);
}

if (!function_exists('passengerActiveTripForAlerts')) {
    /**
     * The passenger's ongoing trip (with its bus trip route/driver/bus),
     * using the same active-trip rules as the passenger trip endpoints.
     *
     * @return array<string,mixed>|null
     */
    function passengerActiveTripForAlerts(PDO $conn, $passengerId)
    {
        try {
            $stmt = $conn->prepare("
                SELECT
                    pt.*,
                    bt.route_name AS route_name,
                    bt.bus_id AS bus_trip_bus_id,
                    bt.driver_id AS bus_trip_driver_id,
                    bt.status AS bus_trip_status
                FROM passenger_trips pt
                LEFT JOIN bus_trips bt ON bt.id = pt.trip_no
                WHERE pt.passenger_id = ?
                AND pt.dropoff_time IS NULL
                AND (
                    pt.trip_status IS NULL
                    OR LOWER(pt.trip_status) NOT IN ('completed', 'cancelled')
                )
                AND (
                    bt.id IS NULL
                    OR LOWER(bt.status) NOT IN ('completed', 'cancelled')
                )
                ORDER BY pt.id DESC
                LIMIT 1
            ");
            $stmt->execute([(int) $passengerId]);
            $trip = $stmt->fetch(PDO::FETCH_ASSOC);

            return $trip ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('passengerDriverMatchTripLocation')) {
    /**
     * Pick the newest candidate that actually belongs to this passenger's
     * trip, using the same ownership rules for every source: valid
     * coordinates, the bus must be the trip's bus, and the reporter must be
     * the trip's driver.
     *
     * $primaryBusOnly mirrors the two existing strategies: the by-driver read
     * only rejects a row when both the row's bus and the trip's bus are known
     * and disagree, while the by-bus read requires an actual bus match and
     * refuses a foreign reporter.
     *
     * @param array<int,array<string,mixed>> $candidates
     * @param array<int,string>              $driverIds
     * @param array<int,string>              $busIds
     * @param bool                           $primaryBusOnly
     * @return array{row:array<string,mixed>|null,matched_by:?string}
     */
    function passengerDriverMatchTripLocation(array $candidates, array $driverIds, array $busIds, $primaryBusOnly)
    {
        foreach ($candidates as $candidate) {
            if (!passengerDriverLocationCoordinatesValid($candidate)) {
                continue;
            }

            if ($primaryBusOnly) {
                $candidateBus = trim((string) ($candidate['bus_id'] ?? ''));
                $tripBus = trim((string) ($busIds[0] ?? ''));

                if ($candidateBus !== '' && $tripBus !== '' && !passengerBusIdMatches($candidateBus, $tripBus)) {
                    // Same driver but a different bus is not this passenger's bus.
                    continue;
                }
            } else {
                $matchesBus = false;
                foreach ($busIds as $busId) {
                    if (passengerBusIdMatches($candidate['bus_id'] ?? '', $busId)) {
                        $matchesBus = true;
                        break;
                    }
                }

                if (!$matchesBus) {
                    continue;
                }

                if ($driverIds) {
                    $candidateDriver = trim((string) ($candidate['driver_id'] ?? ''));
                    if ($candidateDriver !== '' && !in_array($candidateDriver, $driverIds, true)) {
                        // The bus's current reporter is not this trip's driver.
                        continue;
                    }
                }
            }

            return [
                'row' => $candidate,
                'matched_by' => $primaryBusOnly ? 'driver_id' : 'bus',
            ];
        }

        return ['row' => null, 'matched_by' => null];
    }
}

if (!function_exists('passengerDriverHistoryCandidates')) {
    /**
     * Newest driver_location_history rows for the trip's driver.
     *
     * Ordered by created_at DESC, id DESC: the newest fix by the time it was
     * recorded, with the later insertion winning when two rows share a
     * timestamp.
     *
     * `(created_at IS NULL) ASC` leads the sort. In MySQL NULLs sort last under
     * DESC, so a row with a missing created_at would otherwise be pushed past
     * every dated row and the newest readable fix would be hidden behind it -
     * the same failure the previous `ORDER BY id DESC` existed to prevent. This
     * keeps that protection while still selecting on the recorded time. A
     * device clock hours out cannot reorder past the id tiebreaker either, and
     * freshness is judged per record afterwards, so a wrong created_at can at
     * worst make a row stale, never make a stale row look fresh.
     *
     * @param array<int,string> $driverIds
     * @return array<int,array<string,mixed>>
     */
    function passengerDriverHistoryCandidates(PDO $conn, array $driverIds, $limit)
    {
        if (!$driverIds) {
            return [];
        }

        try {
            $placeholders = implode(', ', array_fill(0, count($driverIds), '?'));
            $stmt = $conn->prepare("
                SELECT id, driver_id, bus_id, latitude, longitude, created_at
                FROM driver_location_history
                WHERE driver_id IN ($placeholders)
                ORDER BY (created_at IS NULL) ASC, created_at DESC, id DESC
                LIMIT " . (int) $limit
            );
            $stmt->execute($driverIds);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // Missing table/column: the snapshot fallback still works.
            return [];
        }
    }
}

if (!function_exists('passengerDriverHistoryProbe')) {
    /**
     * Why the newest readings for this driver were or were not usable.
     *
     * The alert payload must be able to answer "why is the reported record not
     * the newest one" without a database console, so each recent row is
     * classified with the same rules the selection uses: coordinate validity,
     * bus ownership, and a usable timestamp.
     *
     * @param array<int,string> $driverIds
     * @param array<int,string> $busIds
     * @param int|null $selectedRowId
     * @return array<int,array<string,mixed>>
     */
    function passengerDriverHistoryProbe(PDO $conn, array $driverIds, array $busIds, $selectedRowId = null, $limit = 5)
    {
        $probe = [];

        foreach (passengerDriverHistoryCandidates($conn, $driverIds, $limit) as $row) {
            $rowId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
            $timestamp = passengerDriverLocationRecordTimestamp($row, PASSENGER_DRIVER_POSITION_SOURCE_HISTORY);

            $entry = [
                'id' => $rowId,
                'created_at' => $timestamp['value'] !== '' ? $timestamp['value'] : null,
                'timestamp_column' => $timestamp['column'],
                'bus_id' => $row['bus_id'] ?? null,
                'latitude' => isset($row['latitude']) && is_numeric($row['latitude']) ? (float) $row['latitude'] : null,
                'longitude' => isset($row['longitude']) && is_numeric($row['longitude']) ? (float) $row['longitude'] : null,
            ];

            if ($selectedRowId !== null && $rowId === $selectedRowId) {
                $entry['status'] = 'selected';
            } elseif (!passengerDriverLocationCoordinatesValid($row)) {
                $entry['status'] = 'rejected_invalid_coordinates';
            } else {
                $busMatches = !$busIds;

                foreach ($busIds as $busId) {
                    if (passengerBusIdMatches($row['bus_id'] ?? '', $busId)) {
                        $busMatches = true;
                        break;
                    }
                }

                if (!$busMatches) {
                    $entry['status'] = 'rejected_other_bus';
                    $entry['trip_bus_id'] = $busIds[0] ?? null;
                } elseif ($timestamp['value'] === '') {
                    $entry['status'] = 'rejected_no_timestamp';
                } else {
                    $entry['status'] = 'newer_but_not_selected';
                }
            }

            if ($entry['created_at'] !== null) {
                $age = passengerDriverRecordAge($conn, $entry['created_at']);
                $entry['age_seconds'] = $age['age_seconds'];
                $entry['time_basis'] = $age['basis'];
                $entry['clock_skew_seconds'] = $age['skew_seconds'] ?? null;
            }

            $probe[] = $entry;
        }

        return $probe;
    }
}

if (!function_exists('passengerDriverHistoryCandidatesForBus')) {
    /**
     * Newest driver_location_history rows for the trip's bus. Best-effort
     * exact-id match, used only when the by-driver read found nothing.
     *
     * @param array<int,string> $busIds
     * @return array<int,array<string,mixed>>
     */
    function passengerDriverHistoryCandidatesForBus(PDO $conn, array $busIds, $limit)
    {
        if (!$busIds) {
            return [];
        }

        try {
            $placeholders = implode(', ', array_fill(0, count($busIds), '?'));
            $stmt = $conn->prepare("
                SELECT id, driver_id, bus_id, latitude, longitude, created_at
                FROM driver_location_history
                WHERE bus_id IN ($placeholders)
                ORDER BY (created_at IS NULL) ASC, created_at DESC, id DESC
                LIMIT " . (int) $limit
            );
            $stmt->execute(array_values($busIds));

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('passengerDriverLocationTrail')) {
    /**
     * Bounded, bus-scoped, time-ordered recent bus positions for the passed
     * -destination detector: newest first, the position that was already used
     * for the current fix excluded, everything older than the recency bound
     * dropped, and every point verified to belong to this trip's bus.
     *
     * Replaces the previous unbounded per-driver "last two rows" read, which
     * could return a position from an unrelated journey or the current fix
     * itself.
     *
     * @return array<int,array{id:?int,lat:float,lng:float,created_at:string,age_seconds:?int}>
     */
    function passengerDriverLocationTrail(
        PDO $conn,
        $driverId,
        $busId,
        $limit = PASSENGER_DRIVER_TRAIL_LIMIT,
        $maxAgeSeconds = PASSENGER_DRIVER_TRAIL_MAX_AGE_SECONDS,
        $excludeRowId = null
    ) {
        $driverId = trim((string) $driverId);
        if ($driverId === '') {
            return [];
        }

        $excludeRowId = $excludeRowId !== null && is_numeric($excludeRowId) ? (int) $excludeRowId : null;
        $rows = [];

        try {
            $stmt = $conn->prepare("
                SELECT id, bus_id, latitude, longitude, created_at
                FROM driver_location_history
                WHERE driver_id = ?
                ORDER BY created_at DESC, id DESC
                LIMIT " . (int) $limit
            );
            $stmt->execute([$driverId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $trail = [];
        $now = time();

        foreach ($rows as $row) {
            $rowId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;

            // The fix already used as the current position is not a previous
            // position, however the two tables happen to diverge.
            if ($excludeRowId !== null && $rowId !== null && $rowId === $excludeRowId) {
                continue;
            }

            if (!passengerDriverLocationCoordinatesValid($row)) {
                continue;
            }

            $rowBus = trim((string) ($row['bus_id'] ?? ''));
            if ($rowBus !== '' && trim((string) $busId) !== '' && !passengerBusIdMatches($rowBus, $busId)) {
                continue;
            }

            $timestamp = passengerDriverLocationRecordTimestamp($row, PASSENGER_DRIVER_POSITION_SOURCE_HISTORY);
            if ($timestamp['value'] === '') {
                continue;
            }

            $age = passengerDriverAgeSeconds($conn, $timestamp['value']);
            if ($age === null || $age > (int) $maxAgeSeconds) {
                continue;
            }

            $trail[] = [
                'id' => $rowId,
                'lat' => (float) $row['latitude'],
                'lng' => (float) $row['longitude'],
                'created_at' => $timestamp['value'],
                'age_seconds' => $age,
            ];
        }

        return $trail;
    }
}

if (!function_exists('passengerDriverCurrentTripIdForGuard')) {
    /**
     * The id of the driver's currently running bus_trips row, used as an extra
     * guard so a location cannot be matched to a different trip on the same
     * bus number. Returns null when no running trip is found.
     */
    function passengerDriverCurrentTripIdForGuard(PDO $conn, $driverId)
    {
        try {
            $stmt = $conn->prepare("
                SELECT id
                FROM bus_trips
                WHERE driver_id = ?
                  AND status IN ('Departed','On Route','Started','Active','Ongoing')
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([(string) $driverId]);
            $id = $stmt->fetchColumn();

            return ($id !== false && $id !== null) ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('passengerDriverLocationForTrip')) {
    /**
     * Freshest usable bus position for the active trip.
     *
     * Reads driver_location_history first, because the snapshot table is
     * only rewritten while a driver is actively reporting: once a trip ends,
     * or between two slow pings, its updated_at keeps moving forward while the
     * reported position stands still. History is therefore the trustworthy
     * record of where the bus actually was. driver_current_location is used
     * only when the trip has no usable history row at all.
     *
     * Every candidate is verified to belong to that trip's driver/bus and is
     * classified by the age of the record that was actually used, so a fresh
     * history row is usable even when the snapshot is old, and an old history
     * row is stale even when the snapshot claims otherwise. Never falls back
     * to another bus or driver.
     *
     * @param array<string,mixed> $trip Active passenger_trips row (with bus trip columns)
     * @param int $freshSeconds
     * @param int $agingSeconds
     * @return array<string,mixed>
     */
    function passengerDriverLocationForTrip(
        PDO $conn,
        array $trip,
        $freshSeconds = FRESHNESS_FRESH_SECONDS,
        $agingSeconds = FRESHNESS_AGING_SECONDS
    ) {
        $location = [
            'available' => false,
            'usable' => false,
            'reason' => 'no_driver_location',
            'matched_by' => null,
            'source' => null,
            'source_row_id' => null,
            'source_timestamp_column' => null,
            'driver_id' => null,
            'bus_id' => null,
            'latitude' => null,
            'longitude' => null,
            'speed' => null,
            'heading' => null,
            'updated_at' => null,
            'age_seconds' => null,
            'time_basis' => null,
            'clock_skew_seconds' => null,
            'freshness' => null,
            'stale' => true,
            'previous' => null,
            'trail' => [],
            'history_probe' => [],
        ];

        // Read the reference clock once here and hand the same reading to
        // every age calculation below, so the age reported for the row, the
        // reference reported to the client, and the freshness decision are all
        // from a single instant rather than three separate clock reads.
        $referenceTime = passengerDriverReferenceTime($conn);
        $location['reference_time'] = $referenceTime['value'];
        $location['reference_time_source'] = $referenceTime['source'];

        $driverIds = [];
        foreach ([$trip['driver_id'] ?? null, $trip['bus_trip_driver_id'] ?? null] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && !in_array($candidate, $driverIds, true)) {
                $driverIds[] = $candidate;
            }
        }

        $busIds = [];
        foreach ([$trip['bus_no'] ?? null, $trip['bus_trip_bus_id'] ?? null] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && !in_array($candidate, $busIds, true)) {
                $busIds[] = $candidate;
            }
        }

        $row = null;
        $matchedBy = null;
        $source = null;

        // 1. PRIMARY: the newest usable driver_location_history record. This
        //    is the live stream the driver app writes every few seconds, so a
        //    bus that is reporting is never judged by an older snapshot.
        $match = passengerDriverMatchTripLocation(
            passengerDriverHistoryCandidates($conn, $driverIds, PASSENGER_DRIVER_HISTORY_CANDIDATE_LIMIT),
            $driverIds,
            $busIds,
            true
        );

        if ($match['row'] !== null) {
            $row = $match['row'];
            $matchedBy = $match['matched_by'];
            $source = PASSENGER_DRIVER_POSITION_SOURCE_HISTORY;
        }

        if ($row === null && $busIds) {
            $match = passengerDriverMatchTripLocation(
                passengerDriverHistoryCandidatesForBus($conn, $busIds, PASSENGER_DRIVER_HISTORY_CANDIDATE_LIMIT),
                $driverIds,
                $busIds,
                false
            );

            if ($match['row'] !== null) {
                $row = $match['row'];
                $matchedBy = $match['matched_by'];
                $source = PASSENGER_DRIVER_POSITION_SOURCE_HISTORY;
            }
        }

        // 2. FALLBACK: the driver_current_location snapshot, only when no
        //    usable history record exists for this trip's bus.
        if ($row === null && $driverIds) {
            try {
                $placeholders = implode(', ', array_fill(0, count($driverIds), '?'));
                $stmt = $conn->prepare("
                    SELECT *
                    FROM driver_current_location
                    WHERE driver_id IN ($placeholders)
                    ORDER BY updated_at DESC
                    LIMIT 5
                ");
                $stmt->execute($driverIds);
                $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $candidates = [];
            }

            $match = passengerDriverMatchTripLocation($candidates, $driverIds, $busIds, true);

            if ($match['row'] !== null) {
                $row = $match['row'];
                $matchedBy = $match['matched_by'];
                $source = PASSENGER_DRIVER_POSITION_SOURCE_SNAPSHOT;
            }
        }

        // 3. Still nothing by driver: scan by bus, still refusing a foreign
        //    driver.
        if ($row === null && $busIds) {
            try {
                $stmt = $conn->query("SELECT * FROM driver_current_location ORDER BY updated_at DESC LIMIT 200");
                $candidates = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            } catch (\Throwable $e) {
                $candidates = [];
            }

            $match = passengerDriverMatchTripLocation($candidates, $driverIds, $busIds, false);

            if ($match['row'] !== null) {
                $row = $match['row'];
                $matchedBy = $match['matched_by'];
                $source = PASSENGER_DRIVER_POSITION_SOURCE_SNAPSHOT;
            }
        }

        if ($row === null) {
            $location['reason'] = 'no_driver_location';
            return $location;
        }

        $timestamp = passengerDriverLocationRecordTimestamp($row, $source);

        // 4. Trip guard: the matched driver must actually be running this
        // passenger trip, not another trip that happens to use the same bus
        // number. Only applied when the passenger trip references a bus_trips
        // row and the driver has a distinctly different running trip.
        $tripNo = isset($trip['trip_no']) && is_numeric($trip['trip_no']) ? (int) $trip['trip_no'] : 0;
        $rowDriver = trim((string) ($row['driver_id'] ?? ''));

        if ($tripNo > 0 && $rowDriver !== '') {
            $runningTripId = passengerDriverCurrentTripIdForGuard($conn, $rowDriver);

            if ($runningTripId !== null && $runningTripId !== $tripNo) {
                $location['available'] = true;
                $location['matched_by'] = 'trip_mismatch';
                $location['reason'] = 'bus_mismatch';
                $location['source'] = $source;
                $location['source_row_id'] = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
                $location['source_timestamp_column'] = $timestamp['column'];
                $location['driver_id'] = $row['driver_id'] ?? null;
                $location['bus_id'] = $row['bus_id'] ?? null;
                $location['latitude'] = (float) $row['latitude'];
                $location['longitude'] = (float) $row['longitude'];
                $location['updated_at'] = $timestamp['value'];
                $age = passengerDriverRecordAge($conn, $location['updated_at'], $referenceTime);
                $location['age_seconds'] = $age['age_seconds'];
                $location['time_basis'] = $age['basis'];
                $location['clock_skew_seconds'] = $age['skew_seconds'] ?? null;
                $location['freshness'] = passengerDriverFreshnessState($location['age_seconds']);
                $location['stale'] = $location['freshness'] === 'STALE';
                $location['history_probe'] = passengerDriverHistoryProbe(
                    $conn,
                    $driverIds,
                    $busIds,
                    $location['source_row_id']
                );

                return $location;
            }
        }

        $location['available'] = true;
        $location['matched_by'] = $matchedBy;
        $location['source'] = $source;
        $location['source_row_id'] = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
        $location['source_timestamp_column'] = $timestamp['column'];
        $location['driver_id'] = $row['driver_id'] ?? null;
        $location['bus_id'] = $row['bus_id'] ?? null;
        $location['latitude'] = (float) $row['latitude'];
        $location['longitude'] = (float) $row['longitude'];
        $location['speed'] = isset($row['speed']) && is_numeric($row['speed']) ? (float) $row['speed'] : null;
        $location['heading'] = isset($row['heading']) && is_numeric($row['heading']) ? (float) $row['heading'] : null;
        $location['updated_at'] = $timestamp['value'];

        // Freshness is the age of the record that was actually used, so a
        // fresh history row is usable even when the snapshot is old, and an
        // old history row is stale even when a snapshot claims otherwise.
        $age = passengerDriverRecordAge($conn, $location['updated_at'], $referenceTime);
        $location['age_seconds'] = $age['age_seconds'];
        $location['time_basis'] = $age['basis'];
        $location['clock_skew_seconds'] = $age['skew_seconds'] ?? null;
        $location['freshness'] = passengerDriverFreshnessState($location['age_seconds']);
        $location['stale'] = $location['freshness'] === 'STALE'
            || ($location['age_seconds'] !== null && $location['age_seconds'] > (int) $agingSeconds);

        $location['history_probe'] = passengerDriverHistoryProbe(
            $conn,
            $driverIds,
            $busIds,
            $location['source_row_id']
        );

        if ($location['stale']) {
            $location['reason'] = 'stale';
            return $location;
        }

        $location['usable'] = true;
        $location['reason'] = null;
        $location['trail'] = passengerDriverLocationTrail(
            $conn,
            $location['driver_id'],
            $location['bus_id'],
            PASSENGER_DRIVER_TRAIL_LIMIT,
            PASSENGER_DRIVER_TRAIL_MAX_AGE_SECONDS,
            $location['source_row_id']
        );

        if (!empty($location['trail'])) {
            $location['previous'] = [
                'lat' => $location['trail'][0]['lat'],
                'lng' => $location['trail'][0]['lng'],
            ];
        }

        return $location;
    }
}

if (!function_exists('passengerDestinationAlertWireState')) {
    /**
     * Map the internal alert state to the documented wire vocabulary.
     *
     * The internal `state` stays as-is for the existing passenger client, which
     * keys off the near/arrival/passed booleans and never parses this string.
     * `destination_alert_state` is the explicit contract for anything
     * integrating with the API, using the destination_reached / passed_destination
     * spellings.
     *
     * @return string
     */
    function passengerDestinationAlertWireState($state)
    {
        switch ((string) $state) {
            case 'reached':
                return 'destination_reached';
            case 'passed':
                return 'passed_destination';
            case 'approaching':
                return 'approaching';
            case 'monitoring':
                return 'monitoring';
            default:
                // Blocked and any future state keep their own explicit name,
                // so a blocked result is never mistaken for a triggered one.
                return (string) $state;
        }
    }
}

if (!function_exists('passengerDriverClockDebug')) {
    /**
     * TEMPORARY. Dump every clock in the pipeline so one response identifies
     * which one produced driver_age_seconds. Read-only; affects no decision.
     *
     * Remove once the basis is confirmed on the live server.
     *
     * @return array<string,mixed>
     */
    function passengerDriverClockDebug(PDO $conn, $driverLocation, $referenceTime)
    {
        $driverName = null;
        try {
            $driverName = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            $driverName = null;
        }

        $sql = [];
        if ($driverName === 'mysql') {
            // One round trip. Each value is read raw, before any PHP parsing,
            // so the numbers can be compared against a direct SQL query.
            //
            // Aliases are backticked because CURRENT_TIMESTAMP is a reserved
            // word and `AS current_timestamp` is a syntax error on MariaDB.
            try {
                $row = $conn->query(
                    'SELECT NOW() AS `mysql_now`, '
                    . 'CURRENT_TIMESTAMP AS `mysql_current_timestamp`, '
                    . 'UTC_TIMESTAMP() AS `mysql_utc_now`, '
                    . 'UNIX_TIMESTAMP() AS `mysql_unix_now`, '
                    . '@@session.time_zone AS `session_tz`, '
                    . '@@global.time_zone AS `global_tz`, '
                    . '@@system_time_zone AS `system_tz`, '
                    . 'TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS `session_offset_seconds`'
                )->fetch(PDO::FETCH_ASSOC);
                $sql = $row === false ? [] : $row;
                if ($sql === []) {
                    $sql = ['error' => 'clock probe returned no rows'];
                }
            } catch (\Throwable $e) {
                $sql = ['error' => $e->getMessage()];
            }
        } else {
            $sql = ['error' => 'not a mysql connection (driver=' . var_export($driverName, true) . ')'];
        }

        $storedAt = (string) ($driverLocation['updated_at'] ?? '');

        // The same stored value differenced against each candidate clock. Only
        // one of these can be right, and the numbers make which obvious.
        $candidates = [];
        foreach (['mysql_now' => $sql['mysql_now'] ?? null, 'current_timestamp' => $sql['current_timestamp'] ?? null, 'php_now' => date('Y-m-d H:i:s')] as $label => $value) {
            if ($value === null || trim((string) $value) === '' || $storedAt === '') {
                continue;
            }
            try {
                $zone = new DateTimeZone(passengerDriverTimeBasis()['timezone']);
                $candidates[$label] = (new DateTimeImmutable((string) $value, $zone))->getTimestamp()
                    - (new DateTimeImmutable($storedAt, $zone))->getTimestamp();
            } catch (\Throwable $e) {
                $candidates[$label] = 'unparseable';
            }
        }

        return [
            // Surface a probe failure instead of silently reporting nulls for
            // every clock, which reads as "no data" rather than "probe broke".
            'probe_error' => $sql['error'] ?? null,
            'stored_value' => $storedAt === '' ? null : $storedAt,
            'stored_source_table' => $driverLocation['source'] ?? null,
            'stored_source_column' => $driverLocation['source_timestamp_column'] ?? null,
            'reported_driver_created_at' => $driverLocation['updated_at'] ?? null,
            'reported_driver_age_seconds' => $driverLocation['age_seconds'] ?? null,
            'reported_time_basis' => $driverLocation['time_basis'] ?? null,
            'reported_freshness' => $driverLocation['freshness'] ?? null,
            'server_current_time' => $referenceTime['value'] ?? null,
            'server_current_time_source' => $referenceTime['source'] ?? null,
            'php_default_timezone' => date_default_timezone_get(),
            'php_now' => date('Y-m-d H:i:s'),
            'php_config_timezone' => ini_get('date.timezone'),
            'declared_timezone' => passengerDriverTimeBasis()['timezone'],
            'declared_utc_offset' => passengerDriverTimeBasis()['offset'],
            'mysql_driver' => $driverName,
            'mysql_now' => $sql['mysql_now'] ?? null,
            'mysql_current_timestamp' => $sql['mysql_current_timestamp'] ?? null,
            'mysql_utc_now' => $sql['mysql_utc_now'] ?? null,
            'mysql_unix_now' => $sql['mysql_unix_now'] ?? null,
            'mysql_session_timezone' => $sql['session_tz'] ?? null,
            'mysql_global_timezone' => $sql['global_tz'] ?? null,
            'mysql_system_timezone' => $sql['system_tz'] ?? null,
            'mysql_session_offset_seconds' => $sql['session_offset_seconds'] ?? null,
            // stored value differenced against each candidate clock
            'age_if_compared_to' => $candidates,
            'thresholds' => [
                'fresh_seconds' => FRESHNESS_FRESH_SECONDS,
                'aging_seconds' => FRESHNESS_AGING_SECONDS,
            ],
        ];
    }
}

if (!function_exists('passengerDriverDestinationHistoryTrail')) {
    /**
     * Chronological GPS trail for the trip's own driver and bus, used as
     * destination EVIDENCE rather than as a live position.
     *
     * This deliberately has no age cutoff. The live-position trail in
     * passengerDriverLocationTrail() is capped at
     * PASSENGER_DRIVER_TRAIL_MAX_AGE_SECONDS because a stale fix must never
     * drive a movement claim, but that same cap is what emptied
     * `previous`/`trail` whenever the newest ping was old. A past fix that
     * stood inside the destination zone is permanent evidence that the bus
     * got there; its age says nothing about whether that happened.
     *
     * Scoped to the matched driver_id AND bus_id so evidence from another
     * driver, bus or trip can never satisfy this passenger's alert.
     *
     * @param int|null $rejected Receives the number of rows that existed but
     *                             were unusable, so the caller can tell "the
     *                             driver sent nothing" apart from "every fix
     *                             was garbage".
     * @return array<int,array<string,mixed>> Oldest first.
     */
    function passengerDriverDestinationHistoryTrail(
        PDO $conn,
        $driverId,
        $busId,
        $afterId = null,
        $limit = PASSENGER_DESTINATION_HISTORY_TRAIL_LIMIT,
        &$rejected = null
    ) {
        $driverId = trim((string) $driverId);
        $busId = trim((string) $busId);

        if ($driverId === '' || $busId === '') {
            return [];
        }

        $afterId = is_numeric($afterId) ? (int) $afterId : 0;

        try {
            // Newest FIRST, then reversed in PHP below.
            //
            // This ordering is load-bearing. Reading chronologically with a
            // LIMIT takes the OLDEST N rows and silently drops everything
            // after them, which is how a live trip ended up being described by
            // points from a fortnight earlier while the newest rows sat
            // unread past the LIMIT. The database must do the cutting so the
            // rows that survive are the recent ones.
            //
            // created_at IS NOT NULL is a filter, not a sort trick. An undated
            // fix cannot be placed on a timeline at all, so ordering it to the
            // end would leave it in the window as the "oldest" point where it
            // still shapes minimum_distance_m and the pass check. An undated
            // row is not evidence about the present, so it is dropped outright.
            $stmt = $conn->prepare("
                SELECT id, bus_id, latitude, longitude, created_at
                FROM driver_location_history
                WHERE driver_id = ?
                  AND bus_id = ?
                  AND id > ?
                  AND latitude IS NOT NULL
                  AND longitude IS NOT NULL
                  AND created_at IS NOT NULL
                ORDER BY created_at DESC, id DESC
                LIMIT " . (int) $limit
            );
            $stmt->execute([$driverId, $busId, $afterId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $trail = [];
        $dropped = 0;
        foreach ($rows as $row) {
            if (!passengerDriverLocationCoordinatesValid($row)) {
                $dropped++;
                continue;
            }
            if (!passengerBusIdMatches(trim((string) ($row['bus_id'] ?? '')), $busId)) {
                $dropped++;
                continue;
            }

            $lat = (float) $row['latitude'];
            $lng = (float) $row['longitude'];

            // Reject the null-island sentinel. 0,0 is a real pair of degrees so
            // it passes every range check, and a device that reports it produces
            // a plausible-looking point thousands of kilometres away, which then
            // poisons the distance for the whole trail. It is never a location.
            if ($lat == 0.0 && $lng == 0.0) {
                $dropped++;
                continue;
            }

            $trail[] = [
                'id' => isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null,
                'lat' => $lat,
                'lng' => $lng,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        // Oldest first, so the caller can read the approach in order.
        $rejected = $dropped;

        return array_reverse($trail);
    }
}

if (!function_exists('passengerDestinationHistoryEvidence')) {
    /**
     * Reduce a chronological GPS trail to the destination facts that survive
     * the newest fix going stale.
     *
     * Walks the trail oldest -> newest and records whether the bus ever
     * entered the 500m zone, ever entered the 150m zone, and then moved back
     * out again. That answers the passenger's actual question - "did the bus
     * reach my stop?" - from permanent evidence, instead of from whatever the
     * last ping happened to be.
     *
     * Mirrors ContinuationJourneyService::trailShowsRecede() so a pass-through
     * is corroborated the same way whether it is seen live or in history.
     *
     * @param array<int,array<string,mixed>> $trail Oldest first.
     * @param array<string,mixed>            $destination ['lat','lng']
     * @return array<string,mixed>
     */
    function passengerDestinationHistoryEvidence(array $trail, array $destination)
    {
        $distances = [];
        $rejected = 0;
        $lastAccepted = null;

        foreach ($trail as $point) {
            if (!is_array($point) || !isset($point['lat'], $point['lng'])) {
                $rejected++;
                continue;
            }
            if (!is_numeric($point['lat']) || !is_numeric($point['lng'])) {
                $rejected++;
                continue;
            }

            $pointLat = (float) $point['lat'];
            $pointLng = (float) $point['lng'];

            if (
                !is_finite($pointLat) || !is_finite($pointLng)
                || $pointLat < -90 || $pointLat > 90
                || $pointLng < -180 || $pointLng > 180
            ) {
                $rejected++;
                continue;
            }

            // Null-island sentinel: passes every range check but is not a place.
            if ($pointLat == 0.0 && $pointLng == 0.0) {
                $rejected++;
                continue;
            }

            $entry = [
                'id' => $point['id'] ?? null,
                'distance_m' => ContinuationJourneyService::haversineMeters(
                    $pointLat,
                    $pointLng,
                    (float) $destination['lat'],
                    (float) $destination['lng']
                ),
            ];
            $distances[] = $entry;
            $lastAccepted = $point;
        }

        $count = count($distances);
        $minimum = $count > 0 ? min(array_column($distances, 'distance_m')) : null;
        $current = $count > 0 ? $distances[$count - 1]['distance_m'] : null;
        $previous = $count > 1 ? $distances[$count - 2]['distance_m'] : null;

        $enteredNear = $minimum !== null
            && $minimum <= ContinuationJourneyService::NEAR_THRESHOLD_METERS;
        $enteredArrival = $minimum !== null
            && $minimum <= ContinuationJourneyService::ARRIVAL_THRESHOLD_METERS;

        // Recede check, matching the live rule: consecutive growing steps
        // ending at the newest point, after a genuine approach into the
        // destination area. A bus that never got near and is still closing is
        // not a pass.
        $passed = false;
        if ($count >= 3) {
            $steps = 0;
            for ($i = $count - 1; $i > 0; $i--) {
                if ($distances[$i]['distance_m'] > $distances[$i - 1]['distance_m'] + ContinuationJourneyService::PASSED_GROWTH_METERS) {
                    $steps++;
                    continue;
                }
                break;
            }

            $passed = $steps >= ContinuationJourneyService::PASSED_TREND_REQUIRED
                && $current >= ContinuationJourneyService::PASSED_FALLBACK_MARGIN_METERS
                && $minimum <= ContinuationJourneyService::PASSED_TREND_START_MAX_METERS;
        }

        $closestIndex = null;
        $closestId = null;
        foreach ($distances as $index => $entry) {
            if ($minimum !== null && abs($entry['distance_m'] - $minimum) < 0.0001) {
                $closestIndex = $index;
                $closestId = $entry['id'];
                break;
            }
        }

        $lastId = $count > 0 ? $distances[$count - 1]['id'] : null;
        $firstId = $count > 0 ? $distances[0]['id'] : null;

        // Why the distances are what they are. A number with no provenance is
        // how a bad row turns into an 11,000 km "distance" that reads like a
        // calculation bug when it is really a bad input.
        if ($count === 0) {
            $status = $rejected > 0 ? 'no_valid_points' : 'no_points';
        } elseif ($rejected > 0) {
            $status = 'ok_with_rejected_points';
        } else {
            $status = 'ok';
        }

        return [
            'history_count' => $count,
            'rejected_point_count' => $rejected,
            'distance_calculation_status' => $status,
            'minimum_distance_m' => $minimum !== null ? round((float) $minimum, 1) : null,
            'minimum_distance_km' => $minimum !== null ? round((float) $minimum / 1000, 3) : null,
            'current_distance_m' => $current !== null ? round((float) $current, 1) : null,
            'current_distance_km' => $current !== null ? round((float) $current / 1000, 3) : null,
            'previous_distance_m' => $previous !== null ? round((float) $previous, 1) : null,
            'previous_distance_km' => $previous !== null ? round((float) $previous / 1000, 3) : null,
            'entered_near' => $enteredNear,
            'entered_arrival' => $enteredArrival,
            'passed' => $passed,
            'closest_history_id' => $closestId,
            'closest_index' => $closestIndex,
            'first_history_id' => $firstId,
            'latest_history_id' => $lastId,
            'latest_history_timestamp' => $count > 0
                ? ($trail[$count - 1]['created_at'] ?? null)
                : null,
            'trail_distances_m' => array_map(static function ($entry) {
                return round((float) $entry['distance_m'], 1);
            }, $distances),
            'closest_lat' => ($closestIndex !== null && isset($trail[$closestIndex]['lat']))
                ? (float) $trail[$closestIndex]['lat'] : null,
            'closest_lng' => ($closestIndex !== null && isset($trail[$closestIndex]['lng']))
                ? (float) $trail[$closestIndex]['lng'] : null,
            // The point the "current" distance was actually measured from, so a
            // surprising number can be traced to a specific row.
            'current_lat' => is_array($lastAccepted) ? (float) $lastAccepted['lat'] : null,
            'current_lng' => is_array($lastAccepted) ? (float) $lastAccepted['lng'] : null,
        ];
    }
}

if (!function_exists('passengerDestinationProcessedHistoryId')) {
    /**
     * Last driver_location_history id already processed for this passenger
     * trip, so repeated polls do not reprocess the same points.
     *
     * Backed by an optional nullable column on passenger_trips. Every access
     * is guarded, so the feature still works (cumulatively, and still exactly
     * once per destination) on a database where the column has not been added.
     *
     * @return int|null
     */
    function passengerDestinationProcessedHistoryId(PDO $conn, $tripId)
    {
        $tripId = (int) $tripId;
        if ($tripId <= 0) {
            return null;
        }

        try {
            $stmt = $conn->prepare("
                SELECT destination_alert_processed_history_id
                FROM passenger_trips
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$tripId]);
            $value = $stmt->fetchColumn();

            return ($value !== false && $value !== null && is_numeric($value)) ? (int) $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('passengerDestinationRememberProcessedHistoryId')) {
    /**
     * Persist the processed high-water mark for this trip. Returns whether it
     * was stored, so the response can be honest about it.
     *
     * @return bool
     */
    function passengerDestinationRememberProcessedHistoryId(PDO $conn, $tripId, $historyId)
    {
        $tripId = (int) $tripId;
        $historyId = is_numeric($historyId) ? (int) $historyId : null;

        if ($tripId <= 0 || $historyId === null || $historyId <= 0) {
            return false;
        }

        try {
            // GREATEST() keeps the mark monotonic: a request that only saw
            // older points must never walk it backwards.
            $stmt = $conn->prepare("
                UPDATE passenger_trips
                SET destination_alert_processed_history_id = GREATEST(
                    COALESCE(destination_alert_processed_history_id, 0),
                    ?
                )
                WHERE id = ?
            ");
            $stmt->execute([$historyId, $tripId]);

            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('passengerDestinationAlertedFlags')) {
    /**
     * Re-read the persisted alert flags for a trip.
     *
     * The caller's $trip array may predate the claims made earlier in this same
     * request, or come from a cache, so it cannot be trusted to say what has
     * already fired. The stored state is the only authority for whether the
     * passenger has already been told.
     *
     * @return array{arrival:bool,passed:bool,near:bool}
     */
    function passengerDestinationAlertedFlags(PDO $conn, $tripId)
    {
        $tripId = (int) $tripId;
        $flags = ['arrival' => false, 'passed' => false, 'near' => false];

        if ($tripId <= 0) {
            return $flags;
        }

        try {
            $stmt = $conn->prepare("
                SELECT destination_near_alerted, destination_arrival_alerted, destination_passed_alerted
                FROM passenger_trips
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$tripId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                $flags['near'] = !empty($row['destination_near_alerted']);
                $flags['arrival'] = !empty($row['destination_arrival_alerted']);
                $flags['passed'] = !empty($row['destination_passed_alerted']);
            }
        } catch (\Throwable $e) {
            // Fall back to whatever the caller already had.
            return $flags;
        }

        return $flags;
    }
}

if (!function_exists('passengerDestinationAlertForTrip')) {
    /**
     * Evaluate the destination alerts for the active trip from the bus
     * location. Passenger GPS is never used here.
     *
     * Always returns a structured `alert` object for an active trip, including
     * blocked states, so a null payload can never hide the reason.
     *
     * @param array<string,mixed>      $trip           Active trip row
     * @param array<string,mixed>|null $driverLocation Pre-resolved location (optional)
     * @return array{alert:array<string,mixed>|null,bus_location:array<string,mixed>,reason:?string}
     */
    function passengerDestinationAlertForTrip(PDO $conn, array $trip, $driverLocation = null)
    {
        if (!is_array($driverLocation)) {
            $driverLocation = passengerDriverLocationForTrip($conn, $trip);
        }

        $tripId = (int) ($trip['id'] ?? 0);

        $timeBasis = passengerDriverTimeBasis();

        // Reuse the reference the location already read, so the clock reported
        // below is the exact instant the reported driver_age_seconds was
        // measured against. Only read a fresh one when the location was passed
        // in from outside and carries none.
        $referenceTime = [
            'value' => is_array($driverLocation) ? ($driverLocation['reference_time'] ?? null) : null,
            'source' => is_array($driverLocation) ? ($driverLocation['reference_time_source'] ?? null) : null,
        ];
        if ($referenceTime['value'] === null || $referenceTime['value'] === '') {
            $referenceTime = passengerDriverReferenceTime($conn);
        }

        $result = [
            'state' => 'monitoring',
            'triggered' => false,
            'distance_m' => null,
            'previous_distance_m' => null,
            'message' => '',
            'trip_id' => $tripId,
            'passenger_trip_id' => $tripId,
            'trip_no' => isset($trip['trip_no']) ? (string) $trip['trip_no'] : null,
            'gps_source' => $driverLocation['source'] ?? null,
            'gps_freshness' => $driverLocation['freshness'] ?? null,
            'driver_id' => $driverLocation['driver_id'] ?? null,
            'bus_id' => $driverLocation['bus_id'] ?? null,
            'driver_lat' => $driverLocation['latitude'] ?? null,
            'driver_lng' => $driverLocation['longitude'] ?? null,
            'driver_updated_at' => $driverLocation['updated_at'] ?? null,
            'driver_created_at' => $driverLocation['updated_at'] ?? null,
            'driver_location_history_id' => $driverLocation['source_row_id'] ?? null,
            'driver_timestamp_column' => $driverLocation['source_timestamp_column'] ?? null,
            'driver_age_seconds' => $driverLocation['age_seconds'] ?? null,
            'driver_time_basis' => $driverLocation['time_basis'] ?? null,
            'driver_clock_skew_seconds' => $driverLocation['clock_skew_seconds'] ?? null,
            'server_current_time' => $referenceTime['value'],
            'server_current_time_source' => $referenceTime['source'],
            'timezone' => $timeBasis['timezone'],
            'utc_offset' => $timeBasis['offset'],
            'driver_history_probe' => $driverLocation['history_probe'] ?? [],
            'driver_speed' => $driverLocation['speed'] ?? null,
            'driver_stale' => (bool) ($driverLocation['stale'] ?? true),
            'bus_match' => $driverLocation['matched_by'] ?? null,
            'destination_id' => (int) ($trip['selected_destination_id'] ?? ($trip['destination_id'] ?? 0)) ?: null,
            'destination_name' => null,
            'destination_lat' => null,
            'destination_lng' => null,
            'destination_source' => 'passenger_trips',
            'destination_coordinates_source' => null,
            'route_direction' => null,
            // Explicit contract names, so an integrator can read one documented
            // field per value without knowing the internal vocabulary. These
            // mirror the fields above and are the ones named in the API docs.
            'driver_location_source' => $driverLocation['source'] ?? null,
            'destination_alert_state' => 'monitoring',
            'destination_alert_reason' => null,
            // Destination-evidence diagnostics. These are answered from the
            // driver's own GPS history, so they stay meaningful even when the
            // newest fix is too old to be used as a live position.
            'latest_history_id' => null,
            'latest_history_timestamp' => null,
            'processed_history_id' => null,
            'new_history_count' => 0,
            'history_count' => 0,
            'minimum_distance_m' => null,
            'minimum_distance_km' => null,
            'current_distance_m' => null,
            'current_distance_km' => null,
            'previous_distance_km' => null,
            'rejected_point_count' => 0,
            'distance_calculation_status' => 'not_evaluated',
            'current_driver_lat' => null,
            'current_driver_lng' => null,
            'history_entered_near' => false,
            'history_entered_arrival' => false,
            'history_passed' => false,
            'history_evidence' => 'unavailable',
            'history_watermark_persisted' => false,
            'alert_evidence_source' => null,
            // TEMPORARY CLOCK DIAGNOSTIC - REMOVE AFTER THE BASIS IS CONFIRMED.
            // Dumps every clock involved, unfiltered, so a single response
            // shows which one produced driver_age_seconds. Read-only: it
            // changes no age, no freshness, and no destination decision.
            '_clock_debug' => passengerDriverClockDebug($conn, $driverLocation, $referenceTime),
        ];

        $reason = $driverLocation['reason'] ?? 'no_driver_location';

        // Resolve the destination and the route direction even when the
        // location is blocked, so the diagnostic always shows what the
        // passenger is monitoring and which way the bus is travelling. Neither
        // depends on the bus position, and a blocked alert that cannot say
        // where the passenger is going is not diagnosable.
        $routeName = trim((string) ($trip['route_name'] ?? ''));
        $coordinates = ContinuationJourneyService::destinationCoordinates($conn, $trip, $routeName);

        if (function_exists('passengerDestinationRouteDirection')) {
            $direction = passengerDestinationRouteDirection($trip, $routeName);
            $result['route_direction'] = is_array($direction) ? ($direction['label'] ?? null) : null;
            $result['route_direction_key'] = is_array($direction) ? ($direction['key'] ?? null) : null;
            $result['route_direction_source'] = is_array($direction) ? ($direction['source'] ?? null) : null;
        }
        $resolvedDestinationName = '';

        if ($coordinates !== null) {
            $resolvedDestinationName = trim((string) ($coordinates['name'] ?? ''));
            if ($resolvedDestinationName === '') {
                $resolvedDestinationName = trim((string) ($trip['planned_dropoff_name'] ?? ''));
            }
            if ($resolvedDestinationName === '') {
                $resolvedDestinationName = trim((string) ($trip['destination_name'] ?? ($trip['selected_destination'] ?? ($trip['destination'] ?? ''))));
            }

            $result['destination_name'] = $resolvedDestinationName !== '' ? $resolvedDestinationName : null;
            $result['destination_lat'] = round((float) $coordinates['lat'], 7);
            $result['destination_lng'] = round((float) $coordinates['lng'], 7);

            // destination_source records where the DESTINATION came from, which
            // is always the passenger's own trip record. The coordinates may be
            // looked up elsewhere, so that lookup is reported separately
            // instead of being presented as the choice of destination.
            $result['destination_source'] = 'passenger_trips';
            $result['destination_coordinates_source'] = $coordinates['source'] ?? null;
        }

        // Destination evidence from the driver's own GPS history, resolved
        // BEFORE the freshness gate below.
        //
        // The gate below exists to stop a stale fix being presented as the
        // bus's current position, which is correct. It must not also throw
        // away the fact that the bus was measurably inside the destination
        // zone earlier in this trip: that is a recorded event, not a live
        // claim, and it is exactly what the passenger needs to be told.
        //
        // The full trail is re-read on purpose. Reducing it is idempotent - the
        // same points always yield the same verdict - so a repeat poll cannot
        // manufacture a new outcome, and the persisted claim flags below keep
        // each alert to once per trip. The watermark tracks how far the trail
        // has been consumed so progress is visible and monotonic.
        $historyTrail = [];
        $historyEvidence = null;
        $historyWatermark = null;
        $historyNewCount = 0;

        if ($coordinates !== null) {
            $historyWatermark = passengerDestinationProcessedHistoryId($conn, $tripId);

            // Read the whole trip trail (after id 0), not only the unprocessed
            // tail: the verdict has to stay cumulative, otherwise a later poll
            // that saw a distant point would forget the arrival.
            $trailRejected = 0;
            $historyTrail = passengerDriverDestinationHistoryTrail(
                $conn,
                $driverLocation['driver_id'] ?? null,
                $driverLocation['bus_id'] ?? null,
                0,
                PASSENGER_DESTINATION_HISTORY_TRAIL_LIMIT,
                $trailRejected
            );

            if (empty($historyTrail) && isset($driverLocation['trail']) && is_array($driverLocation['trail'])) {
                $historyTrail = $driverLocation['trail'];
            }

            if (!empty($historyTrail)) {
                $historyEvidence = passengerDestinationHistoryEvidence($historyTrail, $coordinates);

                foreach ($historyTrail as $point) {
                    if (isset($point['id']) && is_numeric($point['id']) && (int) $point['id'] > (int) $historyWatermark) {
                        $historyNewCount++;
                    }
                }
            }
        }

        if (is_array($historyEvidence)) {
            $result['history_count'] = (int) $historyEvidence['history_count'];
            $result['new_history_count'] = (int) $historyNewCount;
            $result['processed_history_id'] = $historyWatermark;
            $result['minimum_distance_m'] = $historyEvidence['minimum_distance_m'];
            $result['minimum_distance_km'] = $historyEvidence['minimum_distance_km'];
            $result['current_distance_m'] = $historyEvidence['current_distance_m'];
            $result['current_distance_km'] = $historyEvidence['current_distance_km'];
            $result['previous_distance_m'] = $historyEvidence['previous_distance_m'];
            $result['previous_distance_km'] = $historyEvidence['previous_distance_km'];
            $result['rejected_point_count'] = (int) $historyEvidence['rejected_point_count'] + (int) $trailRejected;
            $result['distance_calculation_status'] = $historyEvidence['distance_calculation_status'];
            // The row the reported current distance was measured from, echoed
            // so driver point and destination can be checked by hand.
            $result['current_driver_lat'] = $historyEvidence['current_lat'];
            $result['current_driver_lng'] = $historyEvidence['current_lng'];
            $result['history_entered_near'] = (bool) $historyEvidence['entered_near'];
            $result['history_entered_arrival'] = (bool) $historyEvidence['entered_arrival'];
            $result['history_passed'] = (bool) $historyEvidence['passed'];
            $result['history_evidence'] = 'driver_location_history';
            $result['latest_history_id'] = $historyEvidence['latest_history_id'];
            $result['latest_history_timestamp'] = $historyEvidence['latest_history_timestamp'];

            if ($historyEvidence['latest_history_id'] !== null) {
                $result['processed_history_id'] = $historyEvidence['latest_history_id'];
                $result['history_watermark_persisted'] = passengerDestinationRememberProcessedHistoryId(
                    $conn,
                    $tripId,
                    $historyEvidence['latest_history_id']
                );
            }
        } else {
            $result['history_evidence'] = ($coordinates === null)
                ? 'no_destination'
                : 'no_history_rows';
        }

        // Does the recorded trail prove the bus reached, approached or passed
        // the passenger's destination? Independent of how old the newest fix is.
        $historyReached = is_array($historyEvidence) && !empty($historyEvidence['entered_arrival']);
        $historyNear = is_array($historyEvidence) && !empty($historyEvidence['entered_near']);
        $historyPassed = is_array($historyEvidence) && !empty($historyEvidence['passed']);

        // An unresolved destination is reported before the freshness verdict.
        // Both are true at once when a trip names a destination whose
        // coordinates cannot be read, and "stale" on its own would hide the
        // reason no distance could be computed at all.
        if ($coordinates === null) {
            $result['state'] = 'blocked_destination_unresolved';
            $result['message'] = 'No destination coordinates could be resolved for the active trip';
            $result['destination_alert_state'] = passengerDestinationAlertWireState($result['state']);
            $result['destination_alert_reason'] = 'no_destination_coordinates';
            $result['distance_calculation_status'] = 'no_destination_coordinates';

            return [
                'alert' => $result,
                'bus_location' => $driverLocation,
                'reason' => 'no_destination_coordinates',
            ];
        }

        if (empty($driverLocation['usable'])) {
            // A location belonging to another driver/bus, or no location at
            // all, is still a hard block: there is nothing here to analyse.
            if ($reason !== 'stale') {
                if ($reason === 'bus_mismatch') {
                    $result['state'] = 'blocked_bus_mismatch';
                    $result['message'] = 'Driver location does not belong to the active trip';
                } else {
                    $result['state'] = 'blocked_no_driver_location';
                    $result['message'] = 'No driver/bus location is available for the active trip';
                }

                $result['destination_alert_state'] = passengerDestinationAlertWireState($result['state']);
                $result['destination_alert_reason'] = $reason;

                return [
                    'alert' => $result,
                    'bus_location' => $driverLocation,
                    'reason' => $reason,
                ];
            }

            // Stale newest fix. If the driver's own history already proves the
            // bus got to the destination, that is reported now: the passenger
            // is entitled to know they were dropped off, and the response is
            // explicit that the live position is stale and that the verdict
            // came from history rather than from a current reading.
            if ($coordinates !== null && ($historyReached || $historyPassed)) {
                // The booleans mean "did THIS request fire the alert", so they
                // are the atomic claim result and nothing else. Folding the
                // persisted flag back in here would re-assert the alert on
                // every poll for the rest of the trip, which is exactly the
                // duplicate this is meant to prevent.
                $firedPassed = !empty($historyPassed)
                    && ContinuationJourneyService::claimTripFlag(
                        $conn,
                        $tripId,
                        'destination_passed_alerted',
                        'destination_passed_at',
                        date('Y-m-d H:i:s')
                    );

                $firedArrival = !empty($historyReached)
                    && ContinuationJourneyService::claimTripFlag($conn, $tripId, 'destination_arrival_alerted');

                // The reported state, by contrast, SHOULD reflect what has
                // already been established for this trip: the passenger keeps
                // being told they have arrived even on the poll that did not
                // re-fire it.
                $alertedFlags = passengerDestinationAlertedFlags($conn, $tripId);

                $statePassed = $firedPassed || $alertedFlags['passed'];
                $stateReached = $firedArrival || $statePassed || $alertedFlags['arrival'];

                $state = $statePassed ? 'passed' : ($stateReached ? 'reached' : 'approaching');
                $wire = $statePassed ? 'passed_destination' : ($stateReached ? 'destination_reached' : 'approaching');

                $merged = array_merge($result, [
                    'state' => $state,
                    'destination_alert_state' => $wire,
                    'destination_alert_reason' => $statePassed
                        ? 'history_trail_passed_destination'
                        : ($stateReached ? 'history_trail_reached_destination' : 'history_trail_entered_destination_zone'),
                    'triggered' => $firedArrival || $firedPassed,
                    'near' => $historyNear,
                    'arrival' => $firedArrival,
                    'passed' => $firedPassed,
                    'message' => $statePassed
                        ? 'The driver GPS history shows the bus reached and then left the destination.'
                        : ($stateReached
                            ? 'The driver GPS history shows the bus reached the destination.'
                            : 'The driver GPS history shows the bus inside the destination area.'),
                    // Honest about the live position: still stale, still not a
                    // current reading. Only the arrival proof is asserted.
                    'driver_stale' => true,
                    'bus_stale' => true,
                    'gps_freshness' => $driverLocation['freshness'] ?? null,
                    'driver_age_seconds' => $driverLocation['age_seconds'] ?? null,
                    'driver_lat' => $historyEvidence['closest_lat'] ?? ($driverLocation['latitude'] ?? null),
                    'driver_lng' => $historyEvidence['closest_lng'] ?? ($driverLocation['longitude'] ?? null),
                    'distance_m' => $historyEvidence['minimum_distance_m'],
                    'destination_name' => $result['destination_name'],
                    'destination_lat' => $result['destination_lat'],
                    'destination_lng' => $result['destination_lng'],
                    'alert_evidence_source' => 'driver_location_history',
                    'trail_distances_m' => $historyEvidence['trail_distances_m'],
                ]);

                return [
                    'alert' => $merged,
                    'bus_location' => $driverLocation,
                    'reason' => 'stale',
                ];
            }

            $result['state'] = 'blocked_stale_location';
            $result['message'] = 'Driver location is stale';
            $result['destination_alert_state'] = passengerDestinationAlertWireState($result['state']);
            $result['destination_alert_reason'] = $reason;

            return [
                'alert' => $result,
                'bus_location' => $driverLocation,
                'reason' => $reason,
            ];
        }

        $latitude = (float) $driverLocation['latitude'];
        $longitude = (float) $driverLocation['longitude'];

        $matchedStop = null;
        if ($routeName !== '' && function_exists('matchRouteStop')) {
            try {
                $matchedStop = matchRouteStop($latitude, $longitude, $routeName, $conn);
            } catch (\Throwable $e) {
                $matchedStop = null;
            }
        }

        $alert = ContinuationJourneyService::evaluateDestinationAlerts(
            $conn,
            $trip,
            $routeName,
            $latitude,
            $longitude,
            null,
            $matchedStop,
            isset($driverLocation['previous']) && is_array($driverLocation['previous'])
                ? [
                    'lat' => (float) $driverLocation['previous']['lat'],
                    'lng' => (float) $driverLocation['previous']['lng'],
                ]
                : null,
            isset($driverLocation['trail']) && is_array($driverLocation['trail'])
                ? $driverLocation['trail']
                : null
        );

        if (!is_array($alert)) {
            // Defensive: the fix and destination were already validated, so
            // this should not happen; report monitoring rather than a null.
            $result['state'] = 'monitoring';
            $result['message'] = 'Driver location is fresh; monitoring destination proximity.';
            $result['destination_alert_state'] = 'monitoring';
            $result['destination_alert_reason'] = 'evaluation_returned_no_result';

            return [
                'alert' => $result,
                'bus_location' => $driverLocation,
                'reason' => null,
            ];
        }

        $triggered = !empty($alert['near']) || !empty($alert['arrival']) || !empty($alert['passed']);

        if (!empty($alert['passed']) || !empty($trip['destination_passed_alerted'])) {
            $state = 'passed';
            $message = 'Driver location is fresh; the destination may have been passed.';
        } elseif (
            !empty($alert['arrival'])
            || !empty($trip['destination_arrival_alerted'])
            || (isset($alert['distance_m']) && $alert['distance_m'] !== null && $alert['distance_m'] <= ContinuationJourneyService::ARRIVAL_THRESHOLD_METERS)
            || !empty($historyEvidence['entered_arrival'])
        ) {
            $state = 'reached';
            $message = 'Driver location is fresh; the destination has been reached.';
        } elseif (
            !empty($alert['near'])
            || !empty($trip['destination_near_alerted'])
            || (isset($alert['distance_m']) && $alert['distance_m'] !== null && $alert['distance_m'] <= ContinuationJourneyService::NEAR_THRESHOLD_METERS)
            || !empty($historyEvidence['entered_near'])
        ) {
            $state = 'approaching';
            $message = 'Driver location is fresh; the destination is nearby.';
        } else {
            $state = 'monitoring';
            $message = 'Driver location is fresh; monitoring destination proximity.';
        }

        $merged = array_merge($result, $alert);
        $merged['state'] = $state;
        $merged['triggered'] = $triggered;
        $merged['message'] = $message;
        $merged['gps_source'] = $driverLocation['source'] ?? null;
        $merged['gps_freshness'] = $driverLocation['freshness'] ?? null;
        $merged['driver_lat'] = $latitude;
        $merged['driver_lng'] = $longitude;
        $merged['driver_updated_at'] = $driverLocation['updated_at'];
        $merged['driver_created_at'] = $driverLocation['updated_at'];
        $merged['driver_location_history_id'] = $driverLocation['source_row_id'];
        $merged['driver_timestamp_column'] = $driverLocation['source_timestamp_column'];
        $merged['driver_age_seconds'] = $driverLocation['age_seconds'];
        $merged['driver_time_basis'] = $driverLocation['time_basis'];
        $merged['driver_clock_skew_seconds'] = $driverLocation['clock_skew_seconds'] ?? null;
        $merged['driver_history_probe'] = $driverLocation['history_probe'];
        $merged['driver_stale'] = false;
        $merged['bus_match'] = $driverLocation['matched_by'];
        $merged['destination_name'] = $alert['destination_name'] ?? ($resolvedDestinationName !== '' ? $resolvedDestinationName : null);
        $merged['destination_lat'] = $alert['destination_lat'] ?? round((float) $coordinates['lat'], 7);
        $merged['destination_lng'] = $alert['destination_lng'] ?? round((float) $coordinates['lng'], 7);
        $merged['destination_source'] = $alert['destination_source'] ?? ($coordinates['source'] ?? null);
        // The direction resolved from the trip alone is the fallback; the
        // evaluator's value wins when it managed to use the fix as movement
        // evidence as well.
        $merged['route_direction'] = $alert['direction'] ?? $result['route_direction'] ?? null;
        $merged['previous_distance_m'] = $alert['previous_distance_m'] ?? $result['previous_distance_m'] ?? null;
        $merged['destination_source'] = 'passenger_trips';
        $merged['destination_coordinates_source'] = $result['destination_coordinates_source'] ?? null;
        $merged['bus_updated_at'] = $driverLocation['updated_at'];
        $merged['bus_age_seconds'] = $driverLocation['age_seconds'];
        $merged['bus_stale'] = (bool) $driverLocation['stale'];

        // Destination evidence from history travels with the live verdict too,
        // so one response explains both where the bus is now and what the
        // recorded trail already proved.
        $merged['latest_history_id'] = $result['latest_history_id'];
        $merged['latest_history_timestamp'] = $result['latest_history_timestamp'];
        $merged['processed_history_id'] = $result['processed_history_id'];
        $merged['new_history_count'] = $result['new_history_count'];
        $merged['history_count'] = $result['history_count'];
        $merged['history_entered_near'] = $result['history_entered_near'];
        $merged['history_entered_arrival'] = $result['history_entered_arrival'];
        $merged['history_passed'] = $result['history_passed'];
        $merged['history_evidence'] = $result['history_evidence'];
        $merged['history_watermark_persisted'] = $result['history_watermark_persisted'];
        $merged['alert_evidence_source'] = 'live_driver_location';
        if (is_array($historyEvidence)) {
            // The closest approach the trail ever recorded. With a live fix
            // this is normally the current distance, but it stays available as
            // the running best so a value never regresses between polls.
            $merged['minimum_distance_m'] = $historyEvidence['minimum_distance_m'];
            $merged['current_distance_m'] = (isset($alert['distance_m']) && $alert['distance_m'] !== null)
                ? round((float) $alert['distance_m'], 1)
                : $historyEvidence['current_distance_m'];
        } elseif (isset($alert['distance_m']) && $alert['distance_m'] !== null) {
            $merged['minimum_distance_m'] = round((float) $alert['distance_m'], 1);
            $merged['current_distance_m'] = round((float) $alert['distance_m'], 1);
        }

        // Explicit contract names. Set after the merge so they cannot be
        // overwritten by the evaluator's own keys.
        $merged['driver_location_source'] = $driverLocation['source'] ?? null;
        $merged['destination_alert_state'] = passengerDestinationAlertWireState($state);
        $merged['destination_alert_reason'] = $triggered
            ? $state
            : 'outside_alert_thresholds';

        // Also copy the destination and distance fields into driverLocation/bus_location
        // so whichever property an API consumer reads (destination_alert or bus_location),
        // the resolved destination is consistent and complete.
        $driverLocation['destination_id'] = $merged['destination_id'];
        $driverLocation['destination_name'] = $merged['destination_name'];
        $driverLocation['destination_lat'] = $merged['destination_lat'];
        $driverLocation['destination_lng'] = $merged['destination_lng'];
        $driverLocation['destination_source'] = $merged['destination_source'];
        $driverLocation['destination_coordinates_source'] = $merged['destination_coordinates_source'] ?? null;
        $driverLocation['minimum_distance_m'] = $merged['minimum_distance_m'] ?? null;
        $driverLocation['current_distance_m'] = $merged['current_distance_m'] ?? null;

        return [
            'alert' => $merged,
            'bus_location' => $driverLocation,
            'reason' => null,
        ];
    }
}

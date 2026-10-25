<?php

/**
 * Bus-trip-scoped position resolution for the One-Time passenger boarding flow.
 *
 * Answers "where is the bus of bus_trips #N" before any passenger_trips row
 * exists, so the scan and the registration form can derive the boarding point.
 * Priority mirrors the admin Trip Dashboard's simulation override:
 *
 *   1. An active simulation session for the scanned bus/driver. It is treated
 *      as current exactly like the dashboard override, because the console
 *      freezes simulated positions when its page is closed and the demo would
 *      otherwise see a "stale" bus.
 *   2. The newest usable driver_location_history row for the trip's driver,
 *      then by bus when the driver is unknown or different.
 *   3. The driver_current_location snapshot as the final fallback.
 *
 * Every candidate must belong to the scanned bus (and to the trip's driver
 * when one is known); a foreign row is skipped, never substituted. Stale live
 * positions are returned as unavailable-for-use, not silently replaced.
 *
 * The passenger-trip-scoped resolver in DriverLocationService.php is untouched
 * and keeps serving destination alerts.
 */

if (!defined('BUS_POSITION_HISTORY_LIMIT')) {
    define('BUS_POSITION_HISTORY_LIMIT', 8);
}

if (!defined('BUS_POSITION_SNAPSHOT_LIMIT')) {
    define('BUS_POSITION_SNAPSHOT_LIMIT', 5);
}

if (!defined('BUS_POSITION_BUS_SCAN_LIMIT')) {
    define('BUS_POSITION_BUS_SCAN_LIMIT', 200);
}

require_once __DIR__ . '/DriverLocationSupport.php';

if (!function_exists('passengerBusPositionEmpty')) {
    /**
     * @return array<string,mixed>
     */
    function passengerBusPositionEmpty($busId, $driverId, $tripId)
    {
        return [
            'available' => false,
            'usable' => false,
            'reason' => 'no_driver_location',
            'source' => null,
            'source_row_id' => null,
            'bus_id' => $busId !== '' ? $busId : null,
            'driver_id' => $driverId !== '' ? $driverId : null,
            'trip_id' => $tripId > 0 ? $tripId : null,
            'latitude' => null,
            'longitude' => null,
            'speed' => null,
            'updated_at' => null,
            'age_seconds' => null,
            'time_basis' => null,
            'clock_skew_seconds' => null,
            'freshness' => null,
        ];
    }
}

if (!function_exists('passengerBusSimulationOverride')) {
    /**
     * The active simulation override for the bus/driver, or null. Missing or
     * broken simulation tables degrade to null (the engine function already
     * returns active=false on any error).
     *
     * @return array<string,mixed>|null
     */
    function passengerBusSimulationOverride(PDO $conn, $busId, $driverId)
    {
        $engine = $_SERVER['DOCUMENT_ROOT'] . '/mobile/simulation/lib/simulation_engine.php';

        if (!is_file($engine)) {
            return null;
        }

        try {
            require_once $engine;

            if (!function_exists('simulation_override_for_pdo')) {
                return null;
            }

            $override = simulation_override_for_pdo($conn, $busId !== '' ? $busId : null, $driverId !== '' ? $driverId : null);

            return is_array($override) && ($override['active'] ?? false) === true ? $override : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('passengerBusRowMatchesTrip')) {
    /**
     * Whether a location row belongs to the scanned bus (and to the trip's
     * driver when one is known). Mirrors the passenger resolver's two
     * strategies: a driver-first read only rejects a row when both buses are
     * known and disagree, while a bus-first read requires a real bus match
     * and refuses a foreign reporter.
     */
    function passengerBusRowMatchesTrip(array $row, array $driverIds, $busId, $primaryBusOnly)
    {
        $rowBus = trim((string) ($row['bus_id'] ?? ''));
        $rowDriver = trim((string) ($row['driver_id'] ?? ''));

        if ($primaryBusOnly) {
            if ($busId !== '' && $rowBus !== '' && !passengerBusIdMatches($rowBus, $busId)) {
                return false;
            }

            return true;
        }

        if (!passengerBusIdMatches($rowBus, $busId)) {
            return false;
        }

        if ($driverIds && $rowDriver !== '' && !in_array($rowDriver, $driverIds, true)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('passengerBusHistoryCandidatesByDriver')) {
    /**
     * Recent driver_location_history rows for the trip's drivers, newest
     * first with the recorded time leading, exactly like the passenger
     * resolver's by-driver read.
     *
     * @param array<int,string> $driverIds
     * @return array<int,array<string,mixed>>
     */
    function passengerBusHistoryCandidatesByDriver(PDO $conn, array $driverIds)
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
                LIMIT " . (int) BUS_POSITION_HISTORY_LIMIT
            );
            $stmt->execute($driverIds);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('passengerBusHistoryCandidatesByBus')) {
    /**
     * Recent driver_location_history rows for the scanned bus, used only when
     * the by-driver read found nothing usable.
     *
     * @return array<int,array<string,mixed>>
     */
    function passengerBusHistoryCandidatesByBus(PDO $conn, $busId)
    {
        if ($busId === '') {
            return [];
        }

        try {
            $stmt = $conn->prepare("
                SELECT id, driver_id, bus_id, latitude, longitude, created_at
                FROM driver_location_history
                WHERE bus_id = ?
                ORDER BY (created_at IS NULL) ASC, created_at DESC, id DESC
                LIMIT " . (int) BUS_POSITION_HISTORY_LIMIT
            );
            $stmt->execute([$busId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('passengerBusSnapshotCandidatesByDriver')) {
    /**
     * driver_current_location candidates for the trip's drivers. The snapshot
     * table is rewritten per driver, so the newest updated_at is what
     * "recent" means.
     *
     * @param array<int,string> $driverIds
     * @return array<int,array<string,mixed>>
     */
    function passengerBusSnapshotCandidatesByDriver(PDO $conn, array $driverIds)
    {
        if (!$driverIds) {
            return [];
        }

        try {
            $placeholders = implode(', ', array_fill(0, count($driverIds), '?'));
            $stmt = $conn->prepare("
                SELECT *
                FROM driver_current_location
                WHERE driver_id IN ($placeholders)
                ORDER BY updated_at DESC
                LIMIT " . (int) BUS_POSITION_SNAPSHOT_LIMIT
            );
            $stmt->execute($driverIds);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('passengerBusSnapshotCandidatesByBus')) {
    /**
     * A bounded driver_current_location scan, used only when the by-driver
     * snapshot read found nothing usable.
     *
     * @return array<int,array<string,mixed>>
     */
    function passengerBusSnapshotCandidatesByBus(PDO $conn, $busId)
    {
        if ($busId === '') {
            return [];
        }

        try {
            $stmt = $conn->query("SELECT * FROM driver_current_location ORDER BY updated_at DESC LIMIT " . (int) BUS_POSITION_BUS_SCAN_LIMIT);
            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('passengerBusPositionForTrip')) {
    /**
     * The freshest usable bus position for a bus_trips row.
     *
     * @param array<string,mixed> $busTrip bus_trips row (id, bus_id, driver_id, route_name)
     * @return array<string,mixed>
     */
    function passengerBusPositionForTrip(PDO $conn, array $busTrip)
    {
        $busId = trim((string) ($busTrip['bus_id'] ?? ''));
        $driverId = trim((string) ($busTrip['driver_id'] ?? ''));
        $tripId = isset($busTrip['id']) && is_numeric($busTrip['id']) ? (int) $busTrip['id'] : 0;

        $driverIds = [];
        if ($driverId !== '') {
            $driverIds[] = $driverId;
        }

        passengerApplyManilaSessionTimezone($conn);

        // 1. Simulation override (treated as current, exactly like the admin
        //    dashboard's override).
        $override = passengerBusSimulationOverride($conn, $busId, $driverId);

        if (
            $override !== null
            && isset($override['latitude'], $override['longitude'])
            && is_numeric($override['latitude']) && is_numeric($override['longitude'])
        ) {
            return [
                'available' => true,
                'usable' => true,
                'reason' => null,
                'source' => 'simulation',
                'source_row_id' => isset($override['session_id']) ? (int) $override['session_id'] : null,
                'bus_id' => $override['bus_id'] ?? ($busId !== '' ? $busId : null),
                'driver_id' => $override['driver_id'] ?? ($driverId !== '' ? $driverId : null),
                'trip_id' => $override['trip_id'] ?? ($tripId > 0 ? $tripId : null),
                'latitude' => (float) $override['latitude'],
                'longitude' => (float) $override['longitude'],
                'speed' => isset($override['speed_kmh']) && is_numeric($override['speed_kmh']) ? (float) $override['speed_kmh'] : null,
                'updated_at' => isset($override['updated_at']) ? (string) $override['updated_at'] : null,
                'age_seconds' => 0,
                'time_basis' => passengerDriverTimeBasis()['timezone'],
                'clock_skew_seconds' => 0,
                'freshness' => 'FRESH',
            ];
        }

        // 2. Live GPS: driver history first (loose bus check, mirroring the
        //    passenger resolver), then a bus-scoped history read (strict).
        $reference = passengerDriverReferenceTime($conn);
        $row = null;
        $source = null;

        foreach (passengerBusHistoryCandidatesByDriver($conn, $driverIds) as $candidate) {
            if (
                passengerDriverLocationCoordinatesValid($candidate)
                && passengerBusRowMatchesTrip($candidate, $driverIds, $busId, true)
            ) {
                $row = $candidate;
                $source = PASSENGER_DRIVER_POSITION_SOURCE_HISTORY;
                break;
            }
        }

        if ($row === null) {
            foreach (passengerBusHistoryCandidatesByBus($conn, $busId) as $candidate) {
                if (
                    passengerDriverLocationCoordinatesValid($candidate)
                    && passengerBusRowMatchesTrip($candidate, $driverIds, $busId, false)
                ) {
                    $row = $candidate;
                    $source = PASSENGER_DRIVER_POSITION_SOURCE_HISTORY;
                    break;
                }
            }
        }

        // 3. Snapshot fallback: by driver, then the bounded bus scan.
        if ($row === null) {
            foreach (passengerBusSnapshotCandidatesByDriver($conn, $driverIds) as $candidate) {
                if (
                    passengerDriverLocationCoordinatesValid($candidate)
                    && passengerBusRowMatchesTrip($candidate, $driverIds, $busId, true)
                ) {
                    $row = $candidate;
                    $source = PASSENGER_DRIVER_POSITION_SOURCE_SNAPSHOT;
                    break;
                }
            }
        }

        if ($row === null) {
            foreach (passengerBusSnapshotCandidatesByBus($conn, $busId) as $candidate) {
                if (
                    passengerDriverLocationCoordinatesValid($candidate)
                    && passengerBusRowMatchesTrip($candidate, $driverIds, $busId, false)
                ) {
                    $row = $candidate;
                    $source = PASSENGER_DRIVER_POSITION_SOURCE_SNAPSHOT;
                    break;
                }
            }
        }

        if ($row === null) {
            return passengerBusPositionEmpty($busId, $driverId, $tripId);
        }

        $timestamp = passengerDriverLocationRecordTimestamp($row, $source);
        $coordinates = passengerDriverLocationCoordinatesValid($row);

        $position = [
            'available' => true,
            'usable' => false,
            'reason' => null,
            'source' => $source,
            'source_row_id' => isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null,
            'bus_id' => $row['bus_id'] ?? ($busId !== '' ? $busId : null),
            'driver_id' => $row['driver_id'] ?? ($driverId !== '' ? $driverId : null),
            'trip_id' => $tripId > 0 ? $tripId : null,
            'latitude' => $coordinates ? (float) $row['latitude'] : null,
            'longitude' => $coordinates ? (float) $row['longitude'] : null,
            'speed' => isset($row['speed']) && is_numeric($row['speed']) ? (float) $row['speed'] : null,
            'updated_at' => $timestamp['value'] !== '' ? $timestamp['value'] : null,
            'age_seconds' => null,
            'time_basis' => null,
            'clock_skew_seconds' => null,
            'freshness' => null,
        ];

        if ($timestamp['value'] === '') {
            $position['reason'] = 'no_timestamp';
            return $position;
        }

        $age = passengerDriverRecordAge($conn, $timestamp['value'], $reference);
        $position['age_seconds'] = $age['age_seconds'];
        $position['time_basis'] = $age['basis'];
        $position['clock_skew_seconds'] = $age['skew_seconds'] ?? null;
        $position['freshness'] = passengerDriverFreshnessState($age['age_seconds']);

        if ($position['freshness'] === 'STALE') {
            $position['reason'] = 'stale';
            return $position;
        }

        $position['usable'] = true;
        $position['reason'] = null;

        return $position;
    }
}

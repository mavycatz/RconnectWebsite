<?php

/**
 * Two-step arrival lifecycle support for the driver app.
 *
 * The driver app reports an arrival (GPS proximity, manual end-trip, or the
 * stale-trip sweep); it never completes the trip. Receiving-terminal staff
 * confirm the arrival with the actual ending odometer, which is what moves
 * the trip to Completed and stamps the confirmation. A report that already
 * exists is never overwritten (first report wins).
 */

/**
 * Distance (meters) from the destination terminal within which the driver app
 * reports the arrival automatically. 3 km: terminal coordinates plus phone GPS
 * drift rarely agree within a few hundred meters, so a tighter radius means
 * arrivals are never reported and the receiving terminal never receives the
 * bus. The dispatcher queue uses the same 3 km radius
 * (dispatcher_queue_proximity_km()).
 */
function driver_arrival_report_distance_m()
{
    return 3000.0;
}

/**
 * Record an arrival report for one trip. First report wins; the trip must
 * still be Departed. Returns true when a new report was recorded, false when
 * the trip is missing, already reported, not on route, or the arrival
 * columns are unavailable on this install.
 */
function driver_report_arrival($conn, $trip_id, $source, $driver_id = null)
{
    $trip_id = (int)$trip_id;
    if (!$conn || $trip_id <= 0) {
        return false;
    }
    try {
        $stmt = @$conn->prepare("
            UPDATE bus_trips
            SET arrival_reported_at = COALESCE(arrival_reported_at, NOW()),
                arrival_report_source = COALESCE(arrival_report_source, ?),
                arrival_reported_by = COALESCE(arrival_reported_by, ?)
            WHERE id = ? AND status = 'Departed'
        ");
        if (!$stmt) {
            error_log("driver_report_arrival prepare failed: " . $conn->error);
            return false;
        }
        $driverRef = $driver_id !== null ? (string)$driver_id : null;
        $stmt->bind_param("ssi", $source, $driverRef, $trip_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    } catch (\Throwable $e) {
        error_log("driver_report_arrival error: " . $e->getMessage());
        return false;
    }
}

/**
 * Arrival report state of one trip: ['reported' => bool, 'source' => ?string].
 * Schema-tolerant: installs without the arrival columns report unreported
 * instead of failing the endpoint.
 */
function driver_trip_arrival_report($conn, $trip_id)
{
    $trip_id = (int)$trip_id;
    $state = ['reported' => false, 'source' => null];
    if (!$conn || $trip_id <= 0) {
        return $state;
    }
    try {
        $stmt = @$conn->prepare("SELECT arrival_reported_at, arrival_report_source FROM bus_trips WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return $state;
        }
        $stmt->bind_param("i", $trip_id);
        if (!$stmt->execute()) {
            $stmt->close();
            return $state;
        }
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && !empty($row['arrival_reported_at'])) {
            $state['reported'] = true;
            $state['source'] = $row['arrival_report_source'] ?? null;
        }
    } catch (\Throwable $e) {
        error_log("driver_trip_arrival_report error: " . $e->getMessage());
    }
    return $state;
}

/**
 * Stale-trip sweep: report arrival for trips whose departure is older than
 * $minutes. It never completes them - the receiving terminal still confirms
 * the arrival with the actual ending odometer. Trips that already carry a
 * report are left untouched. Returns the number of newly reported trips.
 */
function driver_report_expired_arrivals($conn, $minutes = 120)
{
    $minutes = max(1, (int)$minutes);
    if (!$conn) {
        return 0;
    }
    try {
        $stmt = @$conn->prepare("
            UPDATE bus_trips
            SET arrival_reported_at = COALESCE(arrival_reported_at, NOW()),
                arrival_report_source = COALESCE(arrival_report_source, 'driver_app_timeout')
            WHERE status = 'Departed'
            AND TIMESTAMPDIFF(MINUTE, CONCAT(trip_date, ' ', departure_time), NOW()) >= ?
        ");
        if (!$stmt) {
            error_log("driver_report_expired_arrivals prepare failed: " . $conn->error);
            return 0;
        }
        $stmt->bind_param("i", $minutes);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    } catch (\Throwable $e) {
        error_log("driver_report_expired_arrivals error: " . $e->getMessage());
        return 0;
    }
}

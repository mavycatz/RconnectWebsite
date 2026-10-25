<?php

if (!function_exists('resolve_active_trip')) {

    /**
     * Resolves the driver's CURRENT active trip from the authenticated driver
     * (driver -> current assignment -> active bus_trips row). This is the
     * backend's source of truth for passenger transactions: any client-supplied
     * trip_no is only validated against this row and is never trusted alone.
     *
     * `bus_trips.status` is an enum of 'Scheduled' | 'Departed' | 'Completed' |
     * 'Cancelled'. A trip is "active" for boarding while it has not yet been
     * Completed or Cancelled, matching the app's current-trip conventions
     * (getActiveTrip.php treats 'Scheduled' and 'Departed' as active).
     *
     * @param mysqli $conn
     * @param string $driverId Authenticated driver ID (require_driver_auth already ran).
     * @return array|null { id, bus_id, driver_id, route_name, from_terminal_id,
     *                      to_terminal_id, trip_date, trip_number, departure_time, status }
     *                     or null when the driver has no active trip.
     */
    function resolve_active_trip($conn, $driverId)
    {
        $stmt = $conn->prepare(
            "SELECT id, bus_id, driver_id, route_name, from_terminal_id, to_terminal_id,
                    trip_date, trip_number, departure_time, status
             FROM bus_trips
             WHERE driver_id = ?
               AND status NOT IN ('Completed', 'Cancelled')
             ORDER BY id DESC
             LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param("s", $driverId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('trip_matches_identifier')) {

    /**
     * Whether a client-submitted trip identifier matches the resolved active
     * trip (by bus_trips.id or by the human-readable trip_number).
     *
     * @param string $submitted
     * @param array  $activeTrip Row from resolve_active_trip().
     * @return bool
     */
    function trip_matches_identifier($submitted, $activeTrip)
    {
        $s = trim((string)$submitted);
        if ($s === '') return true;
        return $s === (string)$activeTrip['id']
            || $s === (string)$activeTrip['trip_number'];
    }
}
<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once __DIR__ . '/live_map_helpers.php';

use App\Core\Database;

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {

    $pdo = Database::tryGet();
    if (!$pdo) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection unavailable',
            'data' => [],
            'recent_activity' => []
        ]);
        exit;
    }

    $today = date('Y-m-d');

    /**
     * =========================
     * LOAD LIVE DRIVER DATA
     * =========================
     */
    $stmt = $pdo->prepare("

        SELECT
            dcl.id,
            dcl.driver_id,
            dcl.driver_id AS gps_driver_id,
            dcl.bus_id AS gps_bus_id,
            d.full_name AS driver_name,
            COALESCE(NULLIF(bt.bus_id, ''), NULLIF(b.bus_id, ''), NULLIF(dcl.bus_id, ''), b.plate_number) AS bus_id,
            dcl.latitude,
            dcl.longitude,
            dcl.speed,
            dcl.heading,
            dcl.updated_at,

            /* =========================
               REAL PASSENGER COUNTS
            ========================= */
            COUNT(pt.id) AS total_passengers,
            SUM(CASE WHEN pt.id IS NOT NULL AND pt.dropoff_time IS NULL THEN 1 ELSE 0 END) AS boarded_passengers,
            SUM(CASE WHEN pt.id IS NOT NULL AND pt.dropoff_time IS NOT NULL THEN 1 ELSE 0 END) AS dropped_off_passengers,
            SUM(CASE WHEN pt.id IS NOT NULL AND pt.dropoff_time IS NULL AND (pt.payment_status IS NULL OR pt.payment_status = '' OR LOWER(pt.payment_status) != 'paid') THEN 1 ELSE 0 END) AS unpaid_passengers,

            /* =========================
               REAL REVENUE
            ========================= */
            COALESCE(
                SUM(pt.fare),
                0
            ) AS total_revenue,

            bt.id AS trip_id,
            bt.fuel_cost,
            bt.route_name,
            bt.status AS trip_status,
            bt.departure_time,
            bt.created_at AS trip_created_at,
            bt.driver_id AS trip_driver_id,

            b.seats,
            b.standing_capacity,
            b.bus_type,
            b.route_name AS bus_route_name,
            COALESCE(b.plate_number, bt.bus_id, 'N/A') AS plate_number

        FROM bus_trips bt

        /* =========================
           BUS DATA
        ========================= */
        LEFT JOIN buses b
            ON (b.bus_id = bt.bus_id OR b.plate_number = bt.bus_id)

        /* =========================
           ACTIVE TRIP ONLY
        ========================= */
        LEFT JOIN driver_current_location dcl
            ON (dcl.bus_id = bt.bus_id OR dcl.bus_id = b.bus_id OR (b.plate_number IS NOT NULL AND dcl.bus_id = b.plate_number))
            AND (bt.driver_id IS NULL OR dcl.driver_id = bt.driver_id)

        /* =========================
           DRIVER DATA
        ========================= */
        LEFT JOIN drivers d
            ON d.driver_id = bt.driver_id

        /* =========================
           PASSENGERS
        ========================= */
        LEFT JOIN passenger_trips pt
            ON pt.trip_no = bt.id

        WHERE
            bt.status IN ('Departed', 'Scheduled')
            AND bt.trip_date = :today

        GROUP BY
            dcl.id,
            dcl.driver_id,
            dcl.bus_id,
            d.full_name,
            bt.bus_id,
            b.bus_id,
            dcl.bus_id,
            dcl.latitude,
            dcl.longitude,
            dcl.speed,
            dcl.heading,
            dcl.updated_at,
            bt.id,
            bt.fuel_cost,
            bt.route_name,
            bt.status,
            bt.departure_time,
            bt.created_at,
            bt.driver_id,
            b.seats,
            b.standing_capacity,
            b.bus_type,
            b.route_name,
            b.plate_number

        ORDER BY
            dcl.updated_at DESC

    ");

    $stmt->bindValue(':today', $today);

    $stmt->execute();

    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /**
     * =========================
     * SCHEMA-ADAPTIVE HELPERS
     * =========================
     */
    $hasTable = function(string $table) use ($pdo): bool {
        try {
            $check = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
            $check->execute([':t' => $table]);
            return (bool)$check->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    };

    $hasInspectorAssignments = $hasTable('inspector_assignments');
    $hasBusInspections = $hasTable('bus_inspections');
    $hasInspectors = $hasTable('inspectors') && $hasTable('inspector_accounts');

    /**
     * =========================
     * FETCH INSPECTOR DATA
     * =========================
     */
    $inspectorMap = [];
    if ($hasInspectorAssignments && !empty($locations)) {
        try {
            $busIds = array_filter(array_unique(array_merge(
                array_column($locations, 'bus_id'),
                array_column($locations, 'plate_number')
            )));
            $tripIds = array_filter(array_unique(array_column($locations, 'trip_id')));

            if (!empty($busIds) || !empty($tripIds)) {
                $conds = [];
                $params = [];
                if (!empty($busIds)) {
                    $inBuses = implode(',', array_fill(0, count($busIds), '?'));
                    $conds[] = "ia.bus_id IN ($inBuses)";
                    $params = array_merge($params, array_values($busIds));
                }
                if (!empty($tripIds)) {
                    $inTrips = implode(',', array_fill(0, count($tripIds), '?'));
                    $conds[] = "ia.trip_id IN ($inTrips)";
                    $params = array_merge($params, array_values($tripIds));
                }

                $nameJoin = $hasInspectors
                    ? "LEFT JOIN inspector_accounts iacc ON iacc.inspector_id = ia.inspector_id
                       LEFT JOIN inspectors insp ON insp.account_id = iacc.account_id"
                    : "";
                $nameSelect = $hasInspectors ? ", insp.inspector_name" : ", NULL AS inspector_name";

                $iaSql = "
                    SELECT ia.id, ia.inspector_id, ia.bus_id, ia.trip_id, ia.status, ia.assigned_at, ia.completed_at $nameSelect
                    FROM inspector_assignments ia
                    $nameJoin
                    WHERE " . implode(' OR ', $conds) . "
                    ORDER BY ia.id DESC
                ";

                $iaStmt = $pdo->prepare($iaSql);
                $iaStmt->execute($params);
                $assignments = $iaStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($assignments as $a) {
                    $bKey = (string)($a['bus_id'] ?? '');
                    $tKey = (string)($a['trip_id'] ?? '');
                    if ($tKey !== '' && !isset($inspectorMap['trip_' . $tKey])) {
                        $inspectorMap['trip_' . $tKey] = $a;
                    }
                    if ($bKey !== '' && !isset($inspectorMap['bus_' . $bKey])) {
                        $inspectorMap['bus_' . $bKey] = $a;
                    }
                }
            }
        } catch (Throwable $e) {
            // Degrade gracefully
        }
    }

    /**
     * =========================
     * FETCH INSPECTION DATA
     * =========================
     */
    $inspectionMap = [];
    if ($hasBusInspections && !empty($locations)) {
        try {
            $busIds = array_filter(array_unique(array_merge(
                array_column($locations, 'bus_id'),
                array_column($locations, 'plate_number')
            )));
            if (!empty($busIds)) {
                $inBuses = implode(',', array_fill(0, count($busIds), '?'));
                $biStmt = $pdo->prepare("
                    SELECT bus_id, result, remarks, issues, inspection_date
                    FROM bus_inspections
                    WHERE bus_id IN ($inBuses) AND DATE(inspection_date) = ?
                    ORDER BY id DESC
                ");
                $params = array_values($busIds);
                $params[] = $today;
                $biStmt->execute($params);
                while ($row = $biStmt->fetch(PDO::FETCH_ASSOC)) {
                    $bKey = (string)$row['bus_id'];
                    if (!isset($inspectionMap[$bKey])) {
                        $inspectionMap[$bKey] = $row;
                    }
                }
            }
        } catch (Throwable $e) {
            // Degrade gracefully
        }
    }

    /**
     * =========================
     * FORMAT VALUES
     * =========================
     */
    foreach ($locations as &$location) {

        // Ensure bus_id is robustly resolved
        $rawBusId = trim((string)($location['bus_id'] ?? ''));
        $rawPlate = trim((string)($location['plate_number'] ?? ''));
        if ($rawBusId === '' || strcasecmp($rawBusId, 'NO BUS') === 0) {
            if ($rawPlate !== '' && strcasecmp($rawPlate, 'N/A') !== 0) {
                $location['bus_id'] = $rawPlate;
            } else {
                $location['bus_id'] = 'Bus #' . ($location['trip_id'] ?? 'Unknown');
            }
        }

        if (empty($location['plate_number']) || $location['plate_number'] === 'N/A') {
            $location['plate_number'] = $location['bus_id'];
        }

        $location['driver_name'] =
            $location['driver_name'] ?? 'Unknown Driver';

        $location['speed'] =
            $location['speed'] !== null
                ? round((float)$location['speed'], 1)
                : null;

        $location['latitude'] =
            $location['latitude'] !== null
                ? (float)$location['latitude']
                : null;

        $location['longitude'] =
            $location['longitude'] !== null
                ? (float)$location['longitude']
                : null;

        $location['updated_at'] =
            $location['updated_at'] ?? null;

        $totalPass = (int)($location['total_passengers'] ?? 0);
        $boardedPass = (int)($location['boarded_passengers'] ?? 0);
        $droppedPass = (int)($location['dropped_off_passengers'] ?? 0);
        $unpaidPass = (int)($location['unpaid_passengers'] ?? 0);

        // If there are zero passenger records on the trip, ensure counts are strictly 0
        if ($totalPass === 0) {
            $boardedPass = 0;
            $droppedPass = 0;
            $unpaidPass = 0;
        }

        $location['total_passengers'] = $totalPass;
        $location['boarded_passengers'] = $boardedPass;
        $location['dropped_off_passengers'] = $droppedPass;
        $location['unpaid_passengers'] = $unpaidPass;

        $location['total_revenue'] =
            round((float)($location['total_revenue'] ?? 0), 2);

        $location['seats'] =
            (int)($location['seats'] ?? 0);

        $location['standing_capacity'] =
            (int)($location['standing_capacity'] ?? 0);

        $location['total_capacity'] =
            $location['seats'] + $location['standing_capacity'];

        $location['trip_id'] =
            $location['trip_id'] ?? null;

        $location['fuel_cost'] =
            round((float)($location['fuel_cost'] ?? 0), 2);

        $location['route_name'] =
            $location['route_name'] ?? 'No Route';

        $location['trip_status'] =
            $location['trip_status'] ?? 'Unknown';

        $location['bus_type'] =
            $location['bus_type'] ?? 'N/A';

        $location['plate_number'] =
            $location['plate_number'] ?? 'N/A';

        // Payment status summary: Separate unpaid count from inspection discrepancies
        $location['payment_status'] = $unpaidPass > 0 ? ($unpaidPass . ' Unpaid') : 'Paid';
        $location['has_discrepancy'] = false;

        // Inspector assignment details
        $tripKey = 'trip_' . ($location['trip_id'] ?? '');
        $busKey = 'bus_' . ($location['bus_id'] ?? '');
        $ia = $inspectorMap[$tripKey] ?? ($inspectorMap[$busKey] ?? null);

        if ($ia) {
            $rawStatus = (string)($ia['status'] ?? 'Waiting');
            $location['inspector_id'] = $ia['inspector_id'] ?? null;
            $location['inspector_name'] = !empty($ia['inspector_name']) ? $ia['inspector_name'] : ($ia['inspector_id'] ?? 'Assigned Inspector');
            $location['inspector_status'] = $rawStatus;
            $location['inspector_on_board'] = (strcasecmp($rawStatus, 'Checked-In') === 0);
            $location['inspector_assigned_at'] = $ia['assigned_at'] ?? null;
            $location['inspector_completed_at'] = $ia['completed_at'] ?? null;
        } else {
            $location['inspector_id'] = null;
            $location['inspector_name'] = 'Not Assigned';
            $location['inspector_status'] = 'Not Assigned';
            $location['inspector_on_board'] = false;
            $location['inspector_assigned_at'] = null;
            $location['inspector_completed_at'] = null;
        }

        // Inspection status details
        $bId = (string)($location['bus_id'] ?? '');
        $bi = $inspectionMap[$bId] ?? null;
        if ($bi) {
            $res = (string)($bi['result'] ?? '');
            $location['inspection_result'] = $res;
            $location['inspection_status'] = strcasecmp($res, 'Passed') === 0 ? 'Completed' : 'Findings Submitted';
            $location['inspection_remarks'] = $bi['remarks'] ?? null;
            $location['inspection_issues'] = $bi['issues'] ?? null;
        } else {
            if ($location['inspector_on_board']) {
                $location['inspection_status'] = 'In Progress';
                $location['inspection_result'] = 'Pending';
            } else {
                $location['inspection_status'] = 'Not Started';
                $location['inspection_result'] = 'None';
            }
            $location['inspection_remarks'] = null;
            $location['inspection_issues'] = null;
        }
    }

    unset($location);

    /**
     * =========================
     * VALIDATE GPS, ROUTE AND SPEED
     * =========================
     * Planned geometry always comes from route_stops (sequence_order ASC).
     * GPS is rejected when missing/invalid/stale/off-route; speed is only
     * trusted inside 0-150 km/h, otherwise recomputed from real fixes or
     * reported as unavailable.
     */
    $routeStopsCache = [];
    $loadRouteStops = function (string $routeName) use ($pdo, &$routeStopsCache): array {
        if (array_key_exists($routeName, $routeStopsCache)) {
            return $routeStopsCache[$routeName];
        }
        $stops = [];
        if ($routeName !== '') {
            try {
                $st = $pdo->prepare("
                    SELECT stop_name, latitude, longitude, sequence_order
                    FROM route_stops
                    WHERE route_name = ?
                    ORDER BY sequence_order ASC
                ");
                $st->execute([$routeName]);
                $stops = live_map_route_stops($st->fetchAll(PDO::FETCH_ASSOC));
            } catch (Throwable $e) {
                $stops = [];
            }
        }
        return $routeStopsCache[$routeName] = $stops;
    };

    $debugMode = isset($_GET['debug']) && (string)$_GET['debug'] === '1';
    $debugRows = [];

    /* GPS is accepted only when it belongs to THIS active trip: same driver,
       same bus, written after the trip started, fresh, inside the Philippine
       operating area and on the trip's route. Anything else is cleared to
       null so the map shows "No GPS" instead of an old/foreign position. */
    foreach ($locations as &$location) {
        $lat = $location['latitude'];
        $lng = $location['longitude'];
        $updatedAt = (string)($location['updated_at'] ?? '');
        $routeName = trim((string)($location['route_name'] ?? ''));
        $stops = $loadRouteStops($routeName);

        $tripDriverId = trim((string)($location['trip_driver_id'] ?? ''));
        $gpsDriverId = trim((string)($location['gps_driver_id'] ?? ''));
        $gpsBusId = trim((string)($location['gps_bus_id'] ?? ''));
        $tripBusId = trim((string)($location['bus_id'] ?? ''));
        $plate = trim((string)($location['plate_number'] ?? ''));
        $tripCreatedAt = (string)($location['trip_created_at'] ?? '');

        $coordsValid = live_map_valid_ph_coord($lat, $lng);

        /* The fix must be THIS trip's own: same driver, same bus. A missing
           driver/bus on either side is rejected rather than guessed. */
        $driverMatches = ($tripDriverId !== '' && $gpsDriverId !== '' && strcasecmp($gpsDriverId, $tripDriverId) === 0);
        $busMatches = ($gpsBusId !== '' && (
            ($tripBusId !== '' && strcasecmp($gpsBusId, $tripBusId) === 0)
            || ($plate !== '' && strcasecmp($gpsBusId, $plate) === 0)
        ));

        $fixAt = $updatedAt !== '' ? strtotime($updatedAt) : false;
        $tripAt = $tripCreatedAt !== '' ? strtotime($tripCreatedAt) : false;
        $fixAfterTripStart = ($fixAt === false || $tripAt === false || $fixAt >= $tripAt - 5);

        $gpsAgeSeconds = $fixAt !== false ? max(0, time() - $fixAt) : null;
        $gpsStale = $gpsAgeSeconds !== null && $gpsAgeSeconds > LIVE_MAP_GPS_STALE_SECONDS;

        $routeDistance = null;
        if ($coordsValid && count($stops) >= 2) {
            $routeDistance = live_map_distance_to_route_km((float)$lat, (float)$lng, $stops);
        }
        $onRoute = ($routeDistance === null || $routeDistance <= LIVE_MAP_GPS_ROUTE_TOLERANCE_KM);

        $gpsReason = null;
        if ($updatedAt === '' || $lat === null || $lng === null) {
            $gpsReason = 'no_fix';
        } elseif (!$coordsValid) {
            $gpsReason = 'outside_route_area';
        } elseif (!$driverMatches) {
            $gpsReason = 'driver_mismatch';
        } elseif (!$busMatches) {
            $gpsReason = 'bus_mismatch';
        } elseif (!$fixAfterTripStart) {
            $gpsReason = 'old_trip_fix';
        } elseif ($gpsStale) {
            $gpsReason = 'stale_fix';
        } elseif (!$onRoute) {
            $gpsReason = 'off_route';
        }
        $gpsValid = $gpsReason === null;

        $speed = ['speed' => null, 'source' => 'none', 'rejected' => false, 'reason' => null];
        if ($gpsValid) {
            /* Trust only the speed stored with this trip's own fix. History is
               NEVER used as a fallback position or speed source. */
            $speed = live_map_validate_speed($location['speed'], null, null);
        } else {
            /* Never fall back to an old / foreign / impossible fix. */
            $location['latitude'] = null;
            $location['longitude'] = null;
        }

        $location['gps_valid'] = $gpsValid;
        $location['gps_status'] = $gpsValid ? 'OK' : 'No GPS';
        $location['gps_reason'] = $gpsReason;
        $location['gps_stale'] = $gpsStale;
        $location['gps_age_seconds'] = $gpsAgeSeconds;
        $location['gps_source'] = $gpsValid ? 'driver_current_location' : 'none';
        $location['gps_updated_at'] = $updatedAt !== '' ? $updatedAt : null;
        $location['distance_from_route_km'] = $routeDistance !== null ? round($routeDistance, 3) : null;
        $location['route_stops_count'] = count($stops);
        $location['speed'] = $gpsValid ? $speed['speed'] : null;
        $location['speed_source'] = $gpsValid ? $speed['source'] : 'none';

        /* Direction and nearest/next stop from the validated route stops. A
           reverse-direction trip travels the same polyline from last to first. */
        $location['direction'] = null;
        $location['current_stop'] = null;
        $location['next_stop'] = null;
        if ($gpsValid && count($stops) >= 2) {
            $nearest = 0;
            $best = INF;
            foreach ($stops as $i => $stop) {
                $distance = live_map_haversine_km((float)$lat, (float)$lng, (float)$stop['lat'], (float)$stop['lng']);
                if ($distance < $best) {
                    $best = $distance;
                    $nearest = $i;
                }
            }
            $physicalKey = (string)preg_replace('/[^a-z0-9]/', '', strtolower($routeName));
            $reverseTrip = false;
            $busRouteName = trim((string)($location['bus_route_name'] ?? ''));
            if ($busRouteName !== '') {
                $tokens = preg_split('/[^a-z0-9]+/', strtolower($busRouteName)) ?: [];
                $tokens = array_values(array_filter($tokens, static function ($token) {
                    return $token !== '' && !in_array($token, ['to', 'via', 'route', 'trip'], true);
                }));
                $reverseKey = count($tokens) >= 2
                    ? (string)preg_replace('/[^a-z0-9]/', '', implode('', array_reverse($tokens)))
                    : '';
                if ($reverseKey !== '' && $reverseKey === $physicalKey) {
                    $reverseTrip = true;
                }
            }
            $lastIndex = count($stops) - 1;
            $location['direction'] = $reverseTrip
                ? $stops[$lastIndex]['name'] . ' -> ' . $stops[0]['name']
                : $stops[0]['name'] . ' -> ' . $stops[$lastIndex]['name'];
            $location['current_stop'] = $stops[$nearest]['name'];
            if ($reverseTrip) {
                $location['next_stop'] = $stops[$nearest - 1]['name'] ?? $stops[0]['name'];
            } else {
                $location['next_stop'] = $stops[$nearest + 1]['name'] ?? $stops[$lastIndex]['name'];
            }
        }

        if (!$gpsValid) {
            error_log(sprintf(
                '[TripDashboard] BUS=%s DRIVER=%s TRIP=%s ROUTE="%s" GPS=No GPS reason=%s lat=%s lng=%s updated=%s',
                (string)($location['bus_id'] ?? ''),
                (string)($location['driver_id'] ?? ''),
                (string)($location['trip_id'] ?? ''),
                $routeName,
                (string)$gpsReason,
                $lat !== null ? (string)$lat : 'null',
                $lng !== null ? (string)$lng : 'null',
                $updatedAt !== '' ? $updatedAt : 'none'
            ));
        }

        if ($debugMode) {
            $debugRows[] = [
                'bus_id' => $location['bus_id'],
                'driver_id' => $location['driver_id'],
                'driver_name' => $location['driver_name'] ?? '',
                'trip_id' => $location['trip_id'],
                'route' => $routeName,
                'direction' => $location['direction'],
                'current_stop' => $location['current_stop'],
                'next_stop' => $location['next_stop'],
                'route_stops' => count($stops),
                'route_coordinates' => array_slice($stops, 0, 25),
                'gps' => [
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'updated_at' => $updatedAt,
                    'age_seconds' => $gpsAgeSeconds,
                    'stale' => $gpsStale,
                    'valid' => $gpsValid,
                    'status' => $location['gps_status'],
                    'source' => $location['gps_source'],
                    'reason' => $gpsReason,
                ],
                'distance_from_route_km' => $routeDistance !== null ? round($routeDistance, 3) : null,
                'speed_kmh' => $speed['speed'],
                'speed_source' => $speed['source'],
                'speed_rejected' => $speed['rejected'],
                'speed_reason' => $speed['reason'],
            ];
        }

        if ($debugMode) {
            error_log(sprintf(
                '[TripDashboard] BUS=%s DRIVER=%s TRIP=%s ROUTE="%s" DIRECTION="%s" ROUTE_STOPS=%d CURRENT_STOP=%s NEXT_STOP=%s LAT=%s LNG=%s SOURCE=%s GPS_AT=%s SPEED=%s STATUS=%s',
                (string)($location['bus_id'] ?? ''),
                (string)($location['driver_id'] ?? ''),
                (string)($location['trip_id'] ?? ''),
                $routeName,
                (string)($location['direction'] ?? ''),
                count($stops),
                (string)($location['current_stop'] ?? ''),
                (string)($location['next_stop'] ?? ''),
                $location['latitude'] !== null ? (string)$location['latitude'] : 'null',
                $location['longitude'] !== null ? (string)$location['longitude'] : 'null',
                (string)($location['gps_source'] ?? 'none'),
                (string)($location['gps_updated_at'] ?? 'none'),
                $speed['speed'] !== null ? (string)$speed['speed'] : 'null',
                (string)($location['gps_status'] ?? '')
            ));
        }
    }
    unset($location);

    /**
     * =========================
     * SIMULATION OVERRIDE
     * =========================
     * While a simulation session is active for a bus/driver, report the
     * simulated position instead of the stored real one and flag the row.
     * Additive: the validation above is untouched and every non-simulated bus
     * keeps its existing values. A missing table or any error degrades to no
     * override.
     */
    try {
        $simulationEngine = __DIR__ . '/../../../mobile/simulation/lib/simulation_engine.php';

        if (file_exists($simulationEngine)) {
            require_once $simulationEngine;

            if (function_exists('simulation_override_for_pdo')) {
                foreach ($locations as &$location) {
                    $override = simulation_override_for_pdo(
                        $pdo,
                        isset($location['bus_id']) ? (string) $location['bus_id'] : null,
                        isset($location['trip_driver_id']) && trim((string) $location['trip_driver_id']) !== ''
                            ? (string) $location['trip_driver_id']
                            : (isset($location['driver_id']) ? (string) $location['driver_id'] : null)
                    );

                    if (($override['active'] ?? false) !== true) {
                        continue;
                    }

                    $location['latitude'] = $override['latitude'];
                    $location['longitude'] = $override['longitude'];
                    $location['speed'] = $override['speed_kmh'];
                    $location['updated_at'] = $override['updated_at'];
                    $location['simulation'] = true;
                    $location['gps_valid'] = true;
                    $location['gps_status'] = 'Simulated';
                    $location['gps_reason'] = null;
                    $location['gps_stale'] = false;
                    $location['gps_age_seconds'] = 0;
                    $location['gps_source'] = 'simulation';
                    $location['gps_updated_at'] = $override['updated_at'];
                    $location['speed_source'] = 'simulation';
                }
                unset($location);
            }
        }
    } catch (Throwable $e) {
        // The dashboard must never fail because of the demo override.
    }

    /**
     * =========================
     * FETCH REAL RECENT ACTIVITY
     * =========================
     */
    $recentActivity = [];
    try {
        // 1. Recent passenger boardings
        $actStmt = $pdo->prepare("
            SELECT
                pt.boarding_time AS event_time,
                'boarding' AS event_type,
                COALESCE(pt.boarding_point, 'Terminal') AS location,
                bt.bus_id,
                bt.route_name,
                COALESCE(p.full_name, 'Passenger') AS actor
            FROM passenger_trips pt
            JOIN bus_trips bt ON bt.id = pt.trip_no
            LEFT JOIN passengers p ON p.id = pt.passenger_id
            WHERE bt.trip_date = :today AND pt.boarding_time IS NOT NULL
            ORDER BY pt.boarding_time DESC
            LIMIT 8
        ");
        $actStmt->execute([':today' => $today]);
        while ($r = $actStmt->fetch(PDO::FETCH_ASSOC)) {
            $recentActivity[] = [
                'time' => $r['event_time'],
                'type' => 'boarding',
                'title' => 'Passenger boarded',
                'description' => 'At ' . $r['location'] . ' (' . $r['bus_id'] . ')',
                'bus_id' => $r['bus_id'],
                'actor' => $r['actor']
            ];
        }

        // 2. Recent passenger dropoffs
        $dropStmt = $pdo->prepare("
            SELECT
                pt.dropoff_time AS event_time,
                'dropoff' AS event_type,
                COALESCE(pt.destination, 'Stop') AS location,
                bt.bus_id,
                bt.route_name,
                COALESCE(p.full_name, 'Passenger') AS actor
            FROM passenger_trips pt
            JOIN bus_trips bt ON bt.id = pt.trip_no
            LEFT JOIN passengers p ON p.id = pt.passenger_id
            WHERE bt.trip_date = :today AND pt.dropoff_time IS NOT NULL
            ORDER BY pt.dropoff_time DESC
            LIMIT 8
        ");
        $dropStmt->execute([':today' => $today]);
        while ($r = $dropStmt->fetch(PDO::FETCH_ASSOC)) {
            $recentActivity[] = [
                'time' => $r['event_time'],
                'type' => 'dropoff',
                'title' => 'Passenger dropped off',
                'description' => 'At ' . $r['location'] . ' (' . $r['bus_id'] . ')',
                'bus_id' => $r['bus_id'],
                'actor' => $r['actor']
            ];
        }

        // 3. Recent inspector check-ins
        if ($hasInspectorAssignments) {
            $inspActStmt = $pdo->prepare("
                SELECT
                    ia.assigned_at AS event_time,
                    'inspector' AS event_type,
                    ia.status,
                    ia.bus_id,
                    ia.inspector_id,
                    " . ($hasInspectors ? "COALESCE(insp.inspector_name, ia.inspector_id)" : "ia.inspector_id") . " AS actor
                FROM inspector_assignments ia
                " . ($hasInspectors ? "LEFT JOIN inspector_accounts iacc ON iacc.inspector_id = ia.inspector_id
                                       LEFT JOIN inspectors insp ON insp.account_id = iacc.account_id" : "") . "
                WHERE DATE(ia.assigned_at) = :today
                ORDER BY ia.id DESC
                LIMIT 5
            ");
            $inspActStmt->execute([':today' => $today]);
            while ($r = $inspActStmt->fetch(PDO::FETCH_ASSOC)) {
                $statusText = $r['status'] === 'Checked-In' ? 'checked in' : ($r['status'] === 'Checked-Out' ? 'checked out' : 'status: ' . $r['status']);
                $recentActivity[] = [
                    'time' => $r['event_time'],
                    'type' => 'inspector',
                    'title' => 'Inspector ' . $statusText,
                    'description' => ($r['actor'] ?? 'Inspector') . ($r['bus_id'] ? ' on ' . $r['bus_id'] : ''),
                    'bus_id' => $r['bus_id'] ?? '',
                    'actor' => $r['actor'] ?? ''
                ];
            }
        }

        // 4. Recent departures
        $depStmt = $pdo->prepare("
            SELECT
                bt.departure_time AS event_time,
                'departure' AS event_type,
                bt.bus_id,
                bt.route_name,
                d.full_name AS actor
            FROM bus_trips bt
            LEFT JOIN drivers d ON d.driver_id = bt.driver_id
            WHERE bt.trip_date = :today AND bt.departure_time IS NOT NULL AND bt.status = 'Departed'
            ORDER BY bt.id DESC
            LIMIT 5
        ");
        $depStmt->execute([':today' => $today]);
        while ($r = $depStmt->fetch(PDO::FETCH_ASSOC)) {
            $recentActivity[] = [
                'time' => $r['event_time'],
                'type' => 'departure',
                'title' => 'Bus departed',
                'description' => ($r['bus_id'] ?? 'Bus') . ' on ' . ($r['route_name'] ?? 'Route'),
                'bus_id' => $r['bus_id'],
                'actor' => $r['actor'] ?? 'Driver'
            ];
        }

        // Sort all activity by time descending
        usort($recentActivity, function($a, $b) {
            $tA = strtotime((string)($a['time'] ?? '0'));
            $tB = strtotime((string)($b['time'] ?? '0'));
            return $tB <=> $tA;
        });

        // Limit to 12 recent events
        $recentActivity = array_slice($recentActivity, 0, 12);

    } catch (Throwable $e) {
        // Degrade gracefully
    }

    echo json_encode([
        'success' => true,
        'data' => $locations,
        'recent_activity' => $recentActivity,
        'debug' => $debugMode ? $debugRows : null
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
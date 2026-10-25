<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

$date = trim($_GET['date'] ?? date('Y-m-d'));
$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$terminal_id = (int)($_SESSION['terminal_id'] ?? $_GET['terminal_id'] ?? 0);

$where = ["bt.trip_date = ?"];
$types = "s";
$params = [$date];

if ($terminal_id > 0) {
    /* Destination-less on-route trips are visible to every terminal: until the
       destination is known, any terminal may be the receiving one and must be
       able to receive the bus. */
    $where[] = "(bt.from_terminal_id = ? OR bt.to_terminal_id = ? OR (bt.status = 'Departed' AND (bt.to_terminal_id IS NULL OR bt.to_terminal_id = 0)))";
    $types .= "ii";
    $params[] = $terminal_id;
    $params[] = $terminal_id;
}
if (!empty($status)) {
    $where[] = "bt.status = ?";
    $types .= "s";
    $params[] = $status;
}
if (!empty($search)) {
    $where[] = "(bt.bus_id LIKE ? OR d.full_name LIKE ? OR bt.trip_number LIKE ?)";
    $types .= "sss";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

dispatcher_ensure_fuel_schema($conn);
$fuelCols = dispatcher_fuel_logs_columns($conn);
$fuelIdCol = dispatcher_fuel_id_column($conn);
$hasTripId = isset($fuelCols['trip_id']);

/* Optional joins are only added when the referenced columns exist on this install. */
$inspectorJoin = '';
$dispatcherNameExpr = 'NULL AS dispatcher_name';
$inspectorCols = dispatcher_table_columns($conn, 'inspectors');
$accountCols = dispatcher_table_columns($conn, 'inspector_accounts');
if (isset($inspectorCols['account_id'], $inspectorCols['inspector_name']) && isset($accountCols['inspector_id'], $accountCols['account_id'])) {
    $inspectorJoin = " LEFT JOIN inspector_accounts ia ON ia.inspector_id = bt.dispatcher_id
                       LEFT JOIN inspectors insp ON insp.account_id = ia.account_id";
    $dispatcherNameExpr = "insp.inspector_name AS dispatcher_name";
}
$terminalJoin = '';
$fromTerminalExpr = 'NULL AS from_terminal_name';
$toTerminalExpr = 'NULL AS to_terminal_name';
$terminalCols = dispatcher_table_columns($conn, 'terminals');
if (isset($terminalCols['id'], $terminalCols['terminal_name'])) {
    $terminalJoin = " LEFT JOIN terminals tfrom ON tfrom.id = bt.from_terminal_id
                      LEFT JOIN terminals tto ON tto.id = bt.to_terminal_id";
    $fromTerminalExpr = "tfrom.terminal_name AS from_terminal_name";
    $toTerminalExpr = "tto.terminal_name AS to_terminal_name";
}
/* Who confirmed the arrival is recorded in the accountability trail. */
$auditJoin = '';
$arrivalByExpr = 'NULL AS arrival_by';
$arrivalAtExpr = 'NULL AS arrival_recorded_at';
if (dispatcher_table_exists($conn, 'audit_trail')) {
    $auditCols = dispatcher_table_columns($conn, 'audit_trail');
    if (isset($auditCols['id'], $auditCols['module'], $auditCols['action'], $auditCols['record_id'], $auditCols['username'])) {
        $auditJoin = " LEFT JOIN audit_trail atr ON atr.id = (SELECT MAX(a.id) FROM audit_trail a WHERE a.module = 'Dispatcher' AND a.action = 'Trip Completed' AND a.record_id = CAST(bt.id AS CHAR))";
        $arrivalByExpr = "atr.username AS arrival_by";
        $arrivalAtExpr = isset($auditCols['created_at']) ? "atr.created_at AS arrival_recorded_at" : "NULL AS arrival_recorded_at";
    }
}

$useFuel = $hasTripId && $fuelIdCol !== null;
/* The planned bus schedule is used to flag overdue (still Scheduled) trips. */
$busScheduleExpr = 'NULL AS bus_schedule';
$busCols = dispatcher_table_columns($conn, 'buses');
if (isset($busCols['schedule'])) {
    $busScheduleExpr = 'b.schedule AS bus_schedule';
}
if ($useFuel) {
    $startOdoExpr = dispatcher_fuel_coalesce_expr($fuelCols, ['starting_odo', 'starting_odometer', 'start_km']);
    $endOdoExpr = dispatcher_fuel_coalesce_expr($fuelCols, ['ending_odo', 'ending_odometer', 'end_km']);
    $distanceExpr = dispatcher_fuel_coalesce_expr($fuelCols, ['distance_traveled', 'distance_km']);
    $flFields = [
        "fl.$fuelIdCol AS fuel_log_id",
        isset($fuelCols['liters']) ? "fl.liters AS fuel_liters" : "NULL AS fuel_liters",
        isset($fuelCols['cost']) ? "fl.cost AS logged_fuel_cost" : "NULL AS logged_fuel_cost",
        isset($fuelCols['price_per_liter']) ? "fl.price_per_liter" : "NULL AS price_per_liter",
        "$startOdoExpr AS starting_odo",
        "$endOdoExpr AS ending_odo",
        isset($fuelCols['kpl']) ? "fl.kpl" : "NULL AS kpl",
        "$distanceExpr AS distance_traveled",
        isset($fuelCols['expected_fuel']) ? "fl.expected_fuel" : "NULL AS expected_fuel"
    ];

    $sql = "SELECT bt.*, d.full_name AS driver_name, b.plate_number,
                   $dispatcherNameExpr,
                   $fromTerminalExpr,
                   $toTerminalExpr,
                   $arrivalByExpr,
                   $arrivalAtExpr,
                   $busScheduleExpr,
                   " . implode(", ", $flFields) . "
            FROM bus_trips bt
            LEFT JOIN drivers d ON d.driver_id = bt.driver_id
            LEFT JOIN buses b ON b.bus_id = bt.bus_id
            $inspectorJoin
            $terminalJoin
            $auditJoin
            LEFT JOIN fuel_logs fl ON fl.$fuelIdCol = (SELECT MAX(fl_sub.$fuelIdCol) FROM fuel_logs fl_sub WHERE fl_sub.trip_id = bt.id)
            WHERE " . implode(" AND ", $where) . "
            ORDER BY CASE WHEN bt.status = 'Departed' THEN 0 ELSE 1 END, bt.trip_number ASC";
} else {
    $sql = "SELECT bt.*, d.full_name AS driver_name, b.plate_number,
                   $dispatcherNameExpr,
                   $fromTerminalExpr,
                   $toTerminalExpr,
                   $arrivalByExpr,
                   $arrivalAtExpr,
                   $busScheduleExpr,
                   NULL AS fuel_log_id, NULL AS fuel_liters, NULL AS logged_fuel_cost,
                   NULL AS price_per_liter, NULL AS starting_odo, NULL AS ending_odo,
                   NULL AS kpl, NULL AS distance_traveled, NULL AS expected_fuel
            FROM bus_trips bt
            LEFT JOIN drivers d ON d.driver_id = bt.driver_id
            LEFT JOIN buses b ON b.bus_id = bt.bus_id
            $inspectorJoin
            $terminalJoin
            $auditJoin
            WHERE " . implode(" AND ", $where) . "
            ORDER BY CASE WHEN bt.status = 'Departed' THEN 0 ELSE 1 END, bt.trip_number ASC";
}

$stmt = dispatcher_statement($conn, $sql, $types, $params);
if (!$stmt) {
    // Ultimate fallback: guaranteed clean query without fuel_logs
    $fallbackSql = "SELECT bt.*, d.full_name AS driver_name, b.plate_number,
                           $dispatcherNameExpr,
                           $fromTerminalExpr,
                           $toTerminalExpr,
                           $arrivalByExpr,
                           $arrivalAtExpr,
                           $busScheduleExpr,
                           NULL AS fuel_log_id, NULL AS fuel_liters, NULL AS logged_fuel_cost,
                           NULL AS price_per_liter, NULL AS starting_odo, NULL AS ending_odo,
                           NULL AS kpl, NULL AS distance_traveled, NULL AS expected_fuel
                    FROM bus_trips bt
                    LEFT JOIN drivers d ON d.driver_id = bt.driver_id
                    LEFT JOIN buses b ON b.bus_id = bt.bus_id
                    $inspectorJoin
                    $terminalJoin
                    $auditJoin
                    WHERE " . implode(" AND ", $where) . "
                    ORDER BY CASE WHEN bt.status = 'Departed' THEN 0 ELSE 1 END, bt.trip_number ASC";
    $stmt = dispatcher_statement($conn, $fallbackSql, $types, $params);
}
if (!$stmt) {
    global $dispatcher_last_sql_error;
    $detail = $dispatcher_last_sql_error ?: ($conn ? $conn->error : "Query execution failed");
    dispatcher_json_error(500, "Failed to load dispatches: " . $detail);
}

$result = $stmt->get_result();
$dispatches = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

/* Additive queue context: original planned queue position and the actual
   dispatch order derived from recorded dispatch times. */
$published = dispatcher_queue_published_positions($conn, $date);
$positionMap = !empty($published) ? $published : dispatcher_queue_entry_position_map($conn, $terminal_id, $date);
$tripIdList = [];
foreach ($dispatches as $dispatchRow) {
    $tripIdList[] = (int)$dispatchRow['id'];
}
$recordedPositions = dispatcher_queue_recorded_positions($conn, $tripIdList);
$orderedTrips = [];
foreach ($dispatches as $dispatchRow) {
    $status = (string)($dispatchRow['status'] ?? '');
    if (!in_array($status, ['Departed', 'Completed'], true) || empty($dispatchRow['departure_time'])) {
        continue;
    }
    if ($terminal_id > 0 && isset($dispatchRow['from_terminal_id']) && (int)$dispatchRow['from_terminal_id'] !== $terminal_id) {
        continue;
    }
    $orderedTrips[] = [
        'id' => (int)$dispatchRow['id'],
        'departure_time' => (string)$dispatchRow['departure_time'],
    ];
}
usort($orderedTrips, function ($a, $b) {
    $timeA = strtotime($a['departure_time']);
    $timeB = strtotime($b['departure_time']);
    if ($timeA === $timeB) {
        return $a['id'] <=> $b['id'];
    }
    return $timeA <=> $timeB;
});
$orderMap = [];
$orderIndex = 1;
foreach ($orderedTrips as $orderedTrip) {
    $orderMap[$orderedTrip['id']] = $orderIndex++;
}
foreach ($dispatches as &$dispatchRow) {
    $busId = (string)($dispatchRow['bus_id'] ?? '');
    $dispatchRow['queue_position'] = $recordedPositions[(int)$dispatchRow['id']] ?? ($positionMap[$busId] ?? ($published[$busId] ?? null));
    $dispatchRow['dispatch_order'] = $orderMap[(int)$dispatchRow['id']] ?? null;
}
unset($dispatchRow);

/* Additive arrival context for every trip: the derived arrival status, and
   the bus's historical KPL reference (the latest qualifying fuel measurement,
   cached per bus). The receiving terminal needs it for the arrival estimate;
   the origin terminal shows the same bus reference instead of a dash. */
$historicalKplByBus = [];
foreach ($dispatches as &$dispatchRow) {
    $dispatchRow['arrival_status'] = dispatcher_trip_arrival_status($dispatchRow);
    $dispatchRow['historical_kpl'] = null;
    /* Actual KPL is only derived when recorded liters exist, never from
       odometer readings alone. */
    $dispatchRow['actual_kpl'] = dispatcher_derive_actual_kpl(
        $dispatchRow['distance_traveled'] ?? null,
        $dispatchRow['fuel_liters'] ?? null
    );
    $dispatchRow['is_inbound'] = dispatcher_trip_is_inbound($dispatchRow, $terminal_id);
    $busId = (string)($dispatchRow['bus_id'] ?? '');
    if ($busId !== '') {
        if (!array_key_exists($busId, $historicalKplByBus)) {
            $historical = dispatcher_bus_historical_kpl($conn, $busId);
            $historicalKplByBus[$busId] = $historical !== null ? (float)$historical['kpl'] : null;
        }
        $dispatchRow['historical_kpl'] = $historicalKplByBus[$busId];
    }
}
unset($dispatchRow);

/* Additive GPS status per trip driver, from the stored current-location record.
   Age is computed server-side on the same Manila clock the writer uses. */
$gpsDriverIds = [];
foreach ($dispatches as $dispatchRow) {
    $driverId = (string)($dispatchRow['driver_id'] ?? '');
    if ($driverId !== '') {
        $gpsDriverIds[] = $driverId;
    }
}
$locationMap = dispatcher_queue_location_map($conn, $gpsDriverIds);
$freshnessSeconds = dispatcher_queue_location_freshness_seconds();
$nowTs = time();
foreach ($dispatches as &$dispatchRow) {
    $driverId = (string)($dispatchRow['driver_id'] ?? '');
    $location = ($driverId !== '' && isset($locationMap[$driverId])) ? $locationMap[$driverId] : null;
    $hasFix = $location !== null
        && is_numeric($location['latitude'] ?? null)
        && is_numeric($location['longitude'] ?? null);
    $updatedAt = $hasFix ? ($location['updated_at'] ?? null) : null;
    $ageSeconds = null;
    $fresh = false;
    if ($hasFix) {
        if ($updatedAt !== null) {
            $updatedTs = strtotime((string)$updatedAt);
            if ($updatedTs !== false) {
                $ageSeconds = max(0, $nowTs - $updatedTs);
                $fresh = $ageSeconds <= $freshnessSeconds;
            } else {
                $fresh = true;
            }
        } else {
            $fresh = true;
        }
    }
    $dispatchRow['gps'] = [
        'available' => $hasFix,
        'latitude' => $hasFix ? (float)$location['latitude'] : null,
        'longitude' => $hasFix ? (float)$location['longitude'] : null,
        'speed_kmh' => ($hasFix && isset($location['speed']) && is_numeric($location['speed'])) ? (float)$location['speed'] : null,
        'bus_id' => $hasFix ? ($location['bus_id'] ?? null) : null,
        'updated_at' => $updatedAt,
        'age_seconds' => $ageSeconds,
        'fresh' => $fresh,
    ];
}
unset($dispatchRow);

/* Additive delay status: recorded Trip Delayed minutes, plus an overdue flag
   for trips still Scheduled past their planned departure plus the grace. */
$delayGraceMinutes = 15;
$delayMinutesByTrip = [];
if (!empty($dispatches) && dispatcher_table_exists($conn, 'audit_trail')) {
    $auditCols = dispatcher_table_columns($conn, 'audit_trail');
    if (isset($auditCols['module'], $auditCols['action'], $auditCols['record_id'], $auditCols['new_values'])) {
        $delayTripIds = [];
        foreach ($dispatches as $dispatchRow) {
            $delayTripIds[] = (int)$dispatchRow['id'];
        }
        $delayTripIds = array_values(array_filter($delayTripIds, function ($value) {
            return $value > 0;
        }));
        if (!empty($delayTripIds)) {
            $delayPlaceholders = implode(',', array_fill(0, count($delayTripIds), '?'));
            $delayOrder = isset($auditCols['id']) ? ' ORDER BY id ASC' : '';
            $delayStmt = dispatcher_statement(
                $conn,
                "SELECT record_id, new_values FROM audit_trail WHERE module = 'Dispatcher' AND action = 'Trip Delayed' AND record_id IN ($delayPlaceholders)$delayOrder",
                str_repeat('s', count($delayTripIds)),
                array_map('strval', $delayTripIds)
            );
            if ($delayStmt) {
                $delayRes = $delayStmt->get_result();
                while ($delayRow = $delayRes->fetch_assoc()) {
                    $payload = json_decode((string)($delayRow['new_values'] ?? ''), true);
                    if (!is_array($payload)) {
                        continue;
                    }
                    $tripId = (int)$delayRow['record_id'];
                    if (isset($payload['delay_minutes']) && is_numeric($payload['delay_minutes'])) {
                        $delayMinutesByTrip[$tripId] = (int)$payload['delay_minutes'];
                    }
                }
                $delayStmt->close();
            }
        }
    }
}
foreach ($dispatches as &$dispatchRow) {
    $tripId = (int)$dispatchRow['id'];
    $recordedMinutes = array_key_exists($tripId, $delayMinutesByTrip) ? $delayMinutesByTrip[$tripId] : null;
    $overdue = false;
    if ((string)($dispatchRow['status'] ?? '') === 'Scheduled') {
        $scheduledRaw = trim((string)($dispatchRow['departure_time'] ?? ''));
        if ($scheduledRaw === '') {
            $scheduledRaw = trim((string)($dispatchRow['bus_schedule'] ?? ''));
        }
        if ($scheduledRaw !== '') {
            $scheduledTs = strtotime(date('Y-m-d') . ' ' . $scheduledRaw);
            if ($scheduledTs !== false && $nowTs > $scheduledTs + ($delayGraceMinutes * 60)) {
                $overdue = true;
            }
        }
    }
    $dispatchRow['delay'] = [
        'recorded_minutes' => $recordedMinutes,
        'overdue' => $overdue,
        'delayed' => $recordedMinutes !== null || $overdue,
        'grace_minutes' => $delayGraceMinutes,
    ];
    unset($dispatchRow['bus_schedule']);
}
unset($dispatchRow);

echo json_encode(["success" => true, "data" => $dispatches]);
$conn->close();

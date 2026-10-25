<?php
header("Content-Type: application/json");
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . "/helpers.php";

dispatcher_require_auth();

$db = new Database();
$conn = $db->connect();

dispatcher_ensure_bpr_schema($conn);

$report_id = isset($_GET['report_id']) ? (int)$_GET['report_id'] : 0;

// Exclude the affected bus of this report (a bus cannot replace itself)
$exclude_bus_id = '';
if ($report_id > 0) {
    $repStmt = dispatcher_statement($conn, "SELECT bus_id FROM bus_problem_reports WHERE id = ?", "i", [$report_id]);
    if ($repStmt) {
        $repRow = $repStmt->get_result()->fetch_assoc();
        $repStmt->close();
        if ($repRow) {
            $exclude_bus_id = (string)($repRow['bus_id'] ?? '');
        }
    }
}

// Candidates: not archived, no occupied trip today (Scheduled/Departed/Interrupted),
// and must have at least one driver assigned via driver_bus_assignment.
$sql = "SELECT b.bus_id, b.plate_number, b.bus_type, b.seats, b.status AS fleet_status, b.route_name
        FROM buses b
        WHERE (b.status IS NULL OR b.status = 'Active')
          AND b.bus_id NOT IN (
              SELECT bus_id FROM bus_trips
              WHERE trip_date = CURDATE()
                AND status IN ('Scheduled','Departed','Interrupted')
          )";

if (dispatcher_table_exists($conn, 'driver_bus_assignment')) {
    $dbaCols = dispatcher_table_columns($conn, 'driver_bus_assignment');
    if (isset($dbaCols['bus_id'], $dbaCols['driver_id'])) {
        $sql .= " AND b.bus_id IN (SELECT DISTINCT bus_id FROM driver_bus_assignment)";
    }
}

$types = "";
$params = [];
if ($exclude_bus_id !== '') {
    $sql .= " AND b.bus_id <> ?";
    $types .= "s";
    $params[] = $exclude_bus_id;
}
$sql .= " ORDER BY b.bus_id ASC";

$stmt = dispatcher_statement($conn, $sql, $types, $params);
if (!$stmt) {
    dispatcher_json_error(500, "Failed to load replacement bus candidates: " . ($dispatcher_last_sql_error ?? "Unknown error"));
}
$buses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// All assigned drivers per bus (driver_bus_assignment is the source of truth)
$assignments = [];
if (!empty($buses)) {
    $ids = array_column($buses, 'bus_id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $aStmt = dispatcher_statement($conn, "SELECT bus_id, driver_id FROM driver_bus_assignment WHERE bus_id IN ($ph) ORDER BY id ASC", str_repeat('s', count($ids)), $ids);
    if ($aStmt) {
        $ares = $aStmt->get_result();
        while ($row = $ares->fetch_assoc()) {
            $assignments[(string)$row['bus_id']][] = (string)$row['driver_id'];
        }
        $aStmt->close();
    }
}

// Driver names
$driverIds = [];
foreach ($assignments as $dlist) {
    foreach ($dlist as $did) {
        $driverIds[$did] = $did;
    }
}
$driverNames = [];
if (!empty($driverIds)) {
    $ph = implode(',', array_fill(0, count($driverIds), '?'));
    $nameStmt = dispatcher_statement($conn, "SELECT driver_id, full_name FROM drivers WHERE driver_id IN ($ph)", str_repeat('s', count($driverIds)), array_values($driverIds));
    if ($nameStmt) {
        $res = $nameStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $driverNames[(string)$row['driver_id']] = (string)($row['full_name'] ?? '');
        }
        $nameStmt->close();
    }
}

// Current location for the bus's primary (first) assigned driver (live GPS upsert table)
$locations = [];
$primaryDriverIds = [];
foreach ($assignments as $dlist) {
    $primary = $dlist[0] ?? null;
    if ($primary !== null && $primary !== '') {
        $primaryDriverIds[$primary] = $primary;
    }
}
if (!empty($primaryDriverIds)) {
    $ph = implode(',', array_fill(0, count($primaryDriverIds), '?'));
    $locStmt = dispatcher_statement($conn, "SELECT driver_id, latitude, longitude, updated_at FROM driver_current_location WHERE driver_id IN ($ph)", str_repeat('s', count($primaryDriverIds)), array_values($primaryDriverIds));
    if ($locStmt) {
        $res = $locStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $driverId = (string)$row['driver_id'];
            if (!isset($locations[$driverId])) {
                $locations[$driverId] = $row;
            }
        }
        $locStmt->close();
    }
}

$candidates = [];
$busIds = [];
foreach ($buses as $bus) {
    $busId = (string)($bus['bus_id'] ?? '');
    $dlist = $assignments[$busId] ?? [];
    $driverId = $dlist[0] ?? null;
    $driverList = [];
    foreach ($dlist as $did) {
        $driverList[] = [
            'driver_id' => $did,
            'driver_name' => isset($driverNames[$did]) ? $driverNames[$did] : '',
        ];
    }
    $loc = ($driverId !== null && $driverId !== '' && isset($locations[$driverId])) ? $locations[$driverId] : null;
    $candidates[] = [
        'bus_id' => $busId,
        'plate_number' => (string)($bus['plate_number'] ?? ''),
        'bus_type' => (string)($bus['bus_type'] ?? ''),
        'seats' => isset($bus['seats']) && $bus['seats'] !== null ? (int)$bus['seats'] : null,
        'status' => 'Available',
        'fleet_status' => (string)($bus['fleet_status'] ?? ''),
        'route_name' => (string)($bus['route_name'] ?? ''),
        'driver_id' => $driverId,
        'driver_name' => $driverId !== null && $driverId !== '' ? ($driverNames[$driverId] ?? '') : '',
        'drivers' => $driverList,
        'latitude' => $loc !== null && $loc['latitude'] !== null ? (string)$loc['latitude'] : null,
        'longitude' => $loc !== null && $loc['longitude'] !== null ? (string)$loc['longitude'] : null,
        'location_updated_at' => $loc !== null ? ($loc['updated_at'] ?? null) : null,
        'location_status' => $loc !== null ? 'located' : 'unavailable',
    ];
    if ($busId !== '') {
        $busIds[] = $busId;
    }
}

// Next scheduled trip today per candidate bus (informational: confirms the bus is free)
$nextTrips = [];
if (!empty($busIds)) {
    $ph = implode(',', array_fill(0, count($busIds), '?'));
    $tStmt = dispatcher_statement(
        $conn,
        "SELECT bus_id, trip_no, route_name, departure_time, status, passenger_count
           FROM bus_trips
          WHERE trip_date = CURDATE() AND bus_id IN ($ph)
          ORDER BY departure_time ASC",
        str_repeat('s', count($busIds)),
        $busIds
    );
    if ($tStmt) {
        $byBus = [];
        $tres = $tStmt->get_result();
        while ($row = $tres->fetch_assoc()) {
            $byBus[(string)$row['bus_id']][] = $row;
        }
        $tStmt->close();
        foreach ($byBus as $id => $trips) {
            $first = $trips[0];
            $nextTrips[$id] = [
                'trip_no' => (string)($first['trip_no'] ?? ''),
                'route_name' => (string)($first['route_name'] ?? ''),
                'departure_time' => (string)($first['departure_time'] ?? ''),
                'status' => (string)($first['status'] ?? ''),
                'passenger_count' => isset($first['passenger_count']) ? (int)$first['passenger_count'] : 0,
                'trip_count_today' => count($trips),
            ];
        }
    }
}
foreach ($candidates as $i => $c) {
    $candidates[$i]['next_trip'] = $nextTrips[$c['bus_id']] ?? null;
    $candidates[$i]['trip_count_today'] = $nextTrips[$c['bus_id']]['trip_count_today'] ?? 0;
}

$conn->close();

echo json_encode([
    "success" => true,
    "report_id" => $report_id,
    "data" => $candidates,
    "count" => count($candidates)
]);

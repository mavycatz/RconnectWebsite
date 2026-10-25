<?php declare(strict_types=1);

/**
 * Dispatcher terminal synchronization checks.
 *
 * Verifies that a bus's current terminal is derived from where it actually is
 * (its latest trip on any date), not from today's trip data alone, and that
 * the verified terminal is persisted on prepare/dispatch and on arrival so
 * validation stops repeating for a legitimately relocated bus.
 *
 * Covers:
 * 1. Cross-date latest-trip lookup and current-terminal resolution.
 * 2. Backward compatibility: no cross-date map -> today-only behavior.
 * 3. Destination resolution and the genuinely-misplaced-bus rejection.
 * 4. Queue membership agreement with destination resolution.
 * 5. Persisted from_terminal_id/to_terminal_id on prepare/dispatch (idempotent).
 * 6. Arrival synchronization moving the bus record to its arrival terminal.
 * 7. Terminal scoping of the published master order (hide/show, no double-listing).
 * 8. Stale/retired terminal ids falling back inside the configured terminals.
 * 9. A completed trip with an unrecorded arrival terminal not being pinned to the opposite terminal.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../../dispatcher/api/helpers.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function sync_test_db(): PDO
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE buses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bus_id TEXT NOT NULL,
        plate_number TEXT,
        bus_type TEXT,
        driver TEXT,
        status TEXT,
        operational_status TEXT,
        schedule TEXT,
        route_name TEXT,
        from_terminal_id INTEGER DEFAULT NULL,
        to_terminal_id INTEGER DEFAULT NULL
    )');
    $db->exec('CREATE TABLE terminals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        terminal_name TEXT,
        terminal_code TEXT,
        status TEXT
    )');
    $db->exec('CREATE TABLE bus_trips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bus_id TEXT,
        driver_id TEXT,
        trip_date TEXT,
        status TEXT,
        trip_number INTEGER,
        departure_time TEXT,
        arrival_time TEXT,
        from_terminal_id INTEGER DEFAULT NULL,
        to_terminal_id INTEGER DEFAULT NULL,
        route_name TEXT,
        dispatcher_id TEXT,
        total_passengers INTEGER
    )');
    $db->exec('CREATE TABLE bus_dispatch_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        operating_date TEXT NOT NULL,
        bus_id TEXT NOT NULL,
        queue_position INTEGER NOT NULL,
        published_at TEXT DEFAULT NULL,
        published_by TEXT DEFAULT NULL,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )');

    return $db;
}

function sync_fixture_db(): PDO
{
    $db = sync_test_db();
    $db->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (1, 'Rizal Terminal', 'RZL', 'Active')");
    $db->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (2, 'Cabanatuan Terminal', 'CBN', 'Active')");

    /* BUS-2001's stale record still shows Rizal (1) as its origin even though it
       returned to Cabanatuan (2) on a prior operating date. */
    $db->exec("INSERT INTO buses (bus_id, plate_number, bus_type, status, operational_status, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-2001', 'ABC-2001', 'Regular AC', 'Active', 'Available', 'Rizal - Cabanatuan', 1, 2)");

    /* Older completion (lower id, older date) arrived at Rizal (1). */
    $db->exec("INSERT INTO bus_trips (bus_id, trip_date, status, departure_time, arrival_time, from_terminal_id, to_terminal_id, route_name) VALUES ('BUS-2001', '2026-10-12', 'Completed', '06:00:00', '08:00:00', 2, 1, 'Cabanatuan-Rizal')");
    /* Latest completion (higher id, prior date) arrived back at Cabanatuan (2). */
    $db->exec("INSERT INTO bus_trips (bus_id, trip_date, status, departure_time, arrival_time, from_terminal_id, to_terminal_id, route_name) VALUES ('BUS-2001', '2026-10-13', 'Completed', '12:00:00', '14:00:00', 1, 2, 'Rizal-Cabanatuan')");

    return $db;
}

$today = '2026-10-14';
$db = sync_fixture_db();

/* ---------------------- cross-date latest-trip lookup ---------------------- */

$latest = dispatcher_queue_latest_trip_map($db, ['BUS-2001']);
check(isset($latest['BUS-2001']), 'The latest-trip map contains the requested bus');
check((string)$latest['BUS-2001']['status'] === 'Completed', 'The latest trip is the completed one');
check((int)$latest['BUS-2001']['to_terminal_id'] === 2, 'The latest trip most-recently arrived at Cabanatuan (2)');
check((string)$latest['BUS-2001']['trip_date'] === '2026-10-13', 'The latest trip is the newest by id, not the older date');

/* --------------------- current-terminal resolution --------------------- */

$tripData = dispatcher_queue_trip_data($db, ['BUS-2001'], $today, 2);
check(empty($tripData['by_bus']), 'No trip today means no active trip entry');
check(empty($tripData['last_completed_by_bus']), 'A prior-date completion is not in today\'s completed map');
check(!empty($tripData['latest_trip_by_bus']['BUS-2001']), 'The cross-date map is populated for the bus');

$busRow = ['bus_id' => 'BUS-2001', 'from_terminal_id' => 1, 'to_terminal_id' => 2];
check(dispatcher_queue_bus_terminal_id($busRow, 'BUS-2001', $tripData) === 2, 'The bus resolves to its prior-date arrival terminal (2), not its stale home (1)');

$legacyTripData = $tripData;
unset($legacyTripData['latest_trip_by_bus']);
check(dispatcher_queue_bus_terminal_id($busRow, 'BUS-2001', $legacyTripData) === 1, 'Without the cross-date map the original today-only behavior is kept');

/* -------------------- destination resolution + rejection -------------------- */

$resolved = dispatcher_resolve_destination($db, 'BUS-2001', 2, $today);
check($resolved['error'] === null, 'A bus that returned to this terminal resolves without error');
check((int)$resolved['terminal_id'] === 1, 'Its next destination is the opposite terminal (Rizal, 1)');
check((int)$resolved['current_terminal_id'] === 2, 'The verified current terminal is the arrival terminal (2)');

$misplaced = dispatcher_resolve_destination($db, 'BUS-2001', 1, $today);
check($misplaced['error'] !== null && strpos($misplaced['error'], 'recorded at another terminal') !== false, 'A genuinely misplaced bus is still rejected');

/* ----------------------- queue membership agreement ----------------------- */

check(dispatcher_bus_in_terminal_queue($db, 'BUS-2001', 2, $today) === true, 'The bus is a member of its arrival terminal queue');
check(dispatcher_bus_in_terminal_queue($db, 'BUS-2001', 1, $today) === false, 'The bus is not a member of the terminal it left');

/* --------------------- persistence on prepare/dispatch --------------------- */

$first = dispatcher_ensure_bus_destination($db, 'BUS-2001', 2, $today);
check($first['success'] === true && $first['error'] === null, 'Preparing the bus at its arrival terminal succeeds');
check((int)$first['terminal_id'] === 1, 'The persisted destination is the opposite terminal (1)');
check($first['updated'] === true, 'The first prepare writes the verified terminals');

$stmt = $db->query("SELECT from_terminal_id, to_terminal_id FROM buses WHERE bus_id = 'BUS-2001'");
$persisted = $stmt->fetch(PDO::FETCH_ASSOC);
check((int)$persisted['from_terminal_id'] === 2, 'The bus record now shows its verified origin (2)');
check((int)$persisted['to_terminal_id'] === 1, 'The bus record now shows its destination (1)');

$second = dispatcher_ensure_bus_destination($db, 'BUS-2001', 2, $today);
check($second['success'] === true && $second['error'] === null, 'A repeated prepare still resolves the same terminal');
check($second['updated'] === false, 'The synchronized writes are idempotent');

$retry = dispatcher_resolve_destination($db, 'BUS-2001', 2, $today);
check($retry['error'] === null && (int)$retry['terminal_id'] === 1, 'Repeated attempts converge with no error');

/* --------------------------- arrival synchronization --------------------------- */

$db->exec("INSERT INTO buses (bus_id, plate_number, bus_type, status, operational_status, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-3001', 'ABC-3001', 'Regular AC', 'Active', 'Available', 'Rizal - Cabanatuan', 1, 2)");

$moved = dispatcher_sync_bus_after_arrival($db, 'BUS-3001', 2);
check($moved === true, 'Arrival synchronization reports a change');
$stmt = $db->query("SELECT from_terminal_id, to_terminal_id FROM buses WHERE bus_id = 'BUS-3001'");
$arrived = $stmt->fetch(PDO::FETCH_ASSOC);
check((int)$arrived['from_terminal_id'] === 2, 'The bus record origin becomes the arrival terminal (2)');
check((int)$arrived['to_terminal_id'] === 1, 'The bus record destination becomes the opposite terminal (1)');

check(dispatcher_sync_bus_after_arrival($db, 'BUS-3001', 2) === false, 'Arrival synchronization is idempotent');
check(dispatcher_sync_bus_after_arrival($db, 'BUS-3001', 0) === false, 'Arrival synchronization without a terminal is a no-op');

/* ---------------- terminal scoping of the published master order ---------------- */

$scopeDb = sync_test_db();
$scopeDb->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (1, 'Rizal Terminal', 'RZL', 'Active')");
$scopeDb->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (2, 'Cabanatuan Terminal', 'CBN', 'Active')");
$scopeDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, status, operational_status, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-A', 'AAA-1', 'Regular AC', 'Active', 'Available', 'Rizal - Cabanatuan', 1, 2)");
$scopeDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, status, operational_status, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-B', 'BBB-1', 'Regular AC', 'Active', 'Available', 'Cabanatuan - Rizal', 2, 1)");
/* BUS-C carries a stale origin (99) from a retired terminal. */
$scopeDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, status, operational_status, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-C', 'CCC-1', 'Regular AC', 'Active', 'Available', 'Rizal - Cabanatuan', 99, 2)");
$scopeDate = '2026-10-14';
foreach ([['BUS-C', 1], ['BUS-B', 2], ['BUS-A', 3]] as $scopeRow) {
    $stmt = $scopeDb->prepare("INSERT INTO bus_dispatch_queue (operating_date, bus_id, queue_position, published_at) VALUES (?, ?, ?, '2026-10-14 05:00:00')");
    $stmt->execute([$scopeDate, $scopeRow[0], $scopeRow[1]]);
}

$scopeIds = static fn (array $d): array => array_map(static fn (array $e): string => (string)$e['bus_id'], $d['entries']);
$scopePos = static fn (array $d): array => array_map(static fn (array $e): int => (int)$e['queue_position'], $d['entries']);

$scoped1 = dispatcher_queue_build($scopeDb, 1, $scopeDate);
$scoped2 = dispatcher_queue_build($scopeDb, 2, $scopeDate);

check($scopeIds($scoped1) === ['BUS-C', 'BUS-A'], 'A terminal lists only the published buses actually at that terminal');
check($scopePos($scoped1) === [1, 3], 'The listed subset keeps the master published positions (no renumbering)');
check($scopeIds($scoped2) === ['BUS-B'], 'A bus based at the other terminal is hidden instead of being shown');
check($scopePos($scoped2) === [2], 'The hidden bus still owns its master position at its own terminal');
check(count($scoped1['entries']) + count($scoped2['entries']) === 3, 'No bus is listed on both terminals at once');

check(dispatcher_bus_in_terminal_queue($scopeDb, 'BUS-A', 1, $scopeDate) === true, 'A bus is a member of the queue for its own terminal');
check(dispatcher_bus_in_terminal_queue($scopeDb, 'BUS-A', 2, $scopeDate) === false, 'The same bus is not a member of the other terminal queue');
check(dispatcher_bus_in_terminal_queue($scopeDb, 'BUS-B', 2, $scopeDate) === true, 'A bus based at the second terminal belongs there');

/* A stale terminal id stays inside the configured terminals: it is not left on a
   retired terminal (hidden from both) and is placed at the starting terminal. */
$staleRow = ['bus_id' => 'BUS-C', 'from_terminal_id' => 99, 'to_terminal_id' => 2];
$emptyTripData = ['by_bus' => [], 'last_completed_by_bus' => [], 'latest_trip_by_bus' => []];
check(dispatcher_bus_current_terminal_id($scopeDb, $staleRow, 'BUS-C', $emptyTripData) === 0, 'A stale configured terminal resolves to unknown rather than the retired id');
check(dispatcher_bus_belongs_to_terminal($scopeDb, $staleRow, 'BUS-C', $emptyTripData, 1, 1) === true, 'A stale-terminal bus is placed at the starting terminal');
check(dispatcher_bus_belongs_to_terminal($scopeDb, $staleRow, 'BUS-C', $emptyTripData, 2, 1) === false, 'A stale-terminal bus is not placed at the other terminal');
check(dispatcher_bus_in_terminal_queue($scopeDb, 'BUS-C', 1, $scopeDate) === true, 'A stale-terminal bus stays visible inside the configured terminals');

$scopedResolve = dispatcher_resolve_destination($scopeDb, 'BUS-C', 1, $scopeDate);
check($scopedResolve['error'] === null, 'A stale-terminal bus can be dispatched from the start terminal');
check((int)$scopedResolve['terminal_id'] === 2, 'Its destination is the opposite terminal (2)');

/* The bus must still be rejected when requested from a terminal it is not at. */
check(dispatcher_bus_in_terminal_queue($scopeDb, 'BUS-A', 2, $scopeDate) === false, 'A bus is still absent from a terminal it is not at');

/* --------- completed trip with an unrecorded arrival terminal --------- */

/* A completed trip with no arrival terminal recorded must not pin the bus to its
   configured destination (the opposite terminal). BUS-4001 sits at Rizal (1) but
   its configured destination is Cabanatuan (2); with a NULL arrival it must stay
   at its origin, not be reported as "recorded at another terminal". */
$missingDb = sync_test_db();
$missingDb->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (1, 'Rizal Terminal', 'RZL', 'Active')");
$missingDb->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (2, 'Cabanatuan Terminal', 'CBN', 'Active')");
$missingDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, status, operational_status, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-4001', 'ABC-4001', 'Regular AC', 'Active', 'Available', 'Rizal - Cabanatuan', 1, 2)");
$missingDb->exec("INSERT INTO bus_trips (bus_id, trip_date, status, departure_time, from_terminal_id, to_terminal_id, route_name) VALUES ('BUS-4001', '2026-10-14', 'Completed', '06:00:00', 1, NULL, 'Rizal-Cabanatuan')");

$missingRow = ['bus_id' => 'BUS-4001', 'from_terminal_id' => 1, 'to_terminal_id' => 2];
$missingTrip = dispatcher_queue_trip_data($missingDb, ['BUS-4001'], '2026-10-14', 1);
check(dispatcher_queue_bus_terminal_id($missingRow, 'BUS-4001', $missingTrip) === 1, 'A completion with no arrival terminal resolves to the origin, not the configured destination');
check(dispatcher_bus_current_terminal_id($missingDb, $missingRow, 'BUS-4001', $missingTrip) === 1, 'The sanitized terminal stays at the origin terminal');
check(dispatcher_bus_in_terminal_queue($missingDb, 'BUS-4001', 1, '2026-10-14') === true, 'The bus is listed at the terminal it is actually at');
check(dispatcher_bus_in_terminal_queue($missingDb, 'BUS-4001', 2, '2026-10-14') === false, 'The bus is not wrongly listed at the opposite terminal');

$missingResolve = dispatcher_resolve_destination($missingDb, 'BUS-4001', 1, '2026-10-14');
check($missingResolve['error'] === null, 'A bus at this terminal is not rejected when its completed-trip arrival is unrecorded');
check((int)$missingResolve['terminal_id'] === 2, 'It resolves the opposite terminal as its destination');

/* The genuinely-misplaced bus is still rejected even with an unrecorded arrival. */
$missingMisplaced = dispatcher_resolve_destination($missingDb, 'BUS-4001', 2, '2026-10-14');
check($missingMisplaced['error'] !== null && strpos($missingMisplaced['error'], 'recorded at another terminal') !== false, 'A misplaced bus is still rejected');

/* --------------- operating-date assignments and arrival membership --------------- */

function sync_assignment_db(): PDO
{
    $db = sync_test_db();
    $db->exec('ALTER TABLE bus_dispatch_queue ADD COLUMN terminal_id INTEGER DEFAULT NULL');
    $db->exec('CREATE TABLE driver_terminal_assignments (
        operating_date TEXT NOT NULL,
        driver_id TEXT NOT NULL,
        terminal_id INTEGER NOT NULL,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL,
        PRIMARY KEY (operating_date, driver_id)
    )');

    return $db;
}

$assignDb = sync_assignment_db();
$assignDb->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (1, 'Rizal Terminal', 'RZL', 'Active')");
$assignDb->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (2, 'Cabanatuan Terminal', 'CBN', 'Active')");
$assignDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, status, operational_status, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-A', 'AAA-1', 'Regular AC', 'Active', 'Available', 'Rizal - Cabanatuan', 1, 2)");
$assignDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, status, operational_status, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-B', 'BBB-1', 'Regular AC', 'Active', 'Available', 'Rizal - Cabanatuan', 1, 2)");
$assignDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, status, operational_status, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-C', 'CCC-1', 'Regular AC', 'Active', 'Available', 'Rizal - Cabanatuan', 1, 2)");
$assignDate = date('Y-m-d');
$assignDb->exec("INSERT INTO bus_dispatch_queue (operating_date, bus_id, queue_position, terminal_id) VALUES ('$assignDate', 'BUS-A', 1, 2)");
$assignDb->exec("INSERT INTO bus_dispatch_queue (operating_date, bus_id, queue_position, terminal_id) VALUES ('$assignDate', 'BUS-B', 2, 1)");

$busA = ['bus_id' => 'BUS-A', 'from_terminal_id' => 1, 'to_terminal_id' => 2];
$busB = ['bus_id' => 'BUS-B', 'from_terminal_id' => 1, 'to_terminal_id' => 2];
$busC = ['bus_id' => 'BUS-C', 'from_terminal_id' => 1, 'to_terminal_id' => 2];

/* An idle bus with an operating-date assignment is at the assigned terminal
   even when its configured origin is elsewhere. */
$assignTrip = dispatcher_queue_trip_data($assignDb, ['BUS-A', 'BUS-B'], $assignDate, 2);
check((int)($assignTrip['assigned_terminal_by_bus']['BUS-A'] ?? 0) === 2, 'The operating-date terminal assignment map is populated');
check(dispatcher_queue_bus_terminal_id($busA, 'BUS-A', $assignTrip) === 2, 'An idle bus is at its assigned terminal');
check(dispatcher_bus_confirmed_terminal_id($busA, 'BUS-A', $assignTrip) === 2, 'The assignment is a confirmed queue entry');

/* An active trip pins the origin; the assignment never places the bus at the
   receiving terminal before the arrival is confirmed. */
$assignDb->exec("INSERT INTO bus_trips (bus_id, driver_id, trip_date, status, trip_number, departure_time, from_terminal_id, to_terminal_id, route_name) VALUES ('BUS-A', 'DRV-A', '$assignDate', 'Departed', 1, '06:00:00', 1, 2, 'Rizal-Cabanatuan')");
$activeAssignTrip = dispatcher_queue_trip_data($assignDb, ['BUS-A'], $assignDate, 2);
check(dispatcher_queue_bus_terminal_id($busA, 'BUS-A', $activeAssignTrip) === 1, 'An active trip keeps the bus at its departure terminal');
check(dispatcher_bus_confirmed_terminal_id($busA, 'BUS-A', $activeAssignTrip) === 1, 'The confirmed position stays the trip origin until arrival');

/* A confirmed arrival today overrides the day's earlier assignment. */
$assignDb->exec("UPDATE bus_trips SET status = 'Completed', arrival_time = '08:00:00' WHERE bus_id = 'BUS-A'");
$arrivedAssignTrip = dispatcher_queue_trip_data($assignDb, ['BUS-A'], $assignDate, 1);
check(dispatcher_queue_bus_terminal_id($busA, 'BUS-A', $arrivedAssignTrip) === 2, 'A confirmed arrival overrides the day assignment');

/* A bus with only a configured origin has no confirmed queue entry. */
$unassignedTrip = dispatcher_queue_trip_data($assignDb, ['BUS-C'], $assignDate, 1);
check(dispatcher_bus_confirmed_terminal_id($busC, 'BUS-C', $unassignedTrip) === 0, 'A never-assigned bus has no confirmed position');
check(dispatcher_queue_bus_terminal_id($busC, 'BUS-C', $unassignedTrip) === 1, 'Its current terminal still falls back to the configured origin for display');

/* Driver effective terminal: day assignment, then the bound bus's terminal. */
$assignDb->exec("INSERT INTO driver_terminal_assignments (operating_date, driver_id, terminal_id) VALUES ('$assignDate', 'DRV-B', 2)");
check(dispatcher_driver_day_terminal_id($assignDb, 'DRV-B', $assignDate) === 2, 'The driver day assignment is read per date');
check(dispatcher_driver_effective_terminal_id($assignDb, 'DRV-B', $busB, 'BUS-B', $unassignedTrip, $assignDate) === 2, 'The driver assignment wins over the bus terminal');
check(dispatcher_driver_effective_terminal_id($assignDb, 'DRV-C', $busB, 'BUS-B', $unassignedTrip, $assignDate) === 1, 'A driver without an assignment inherits the bound bus terminal');
$assignDb->exec("INSERT INTO bus_trips (bus_id, driver_id, trip_date, status, trip_number, departure_time, from_terminal_id, to_terminal_id, route_name) VALUES ('BUS-B', 'DRV-D', '$assignDate', 'Departed', 1, '09:00:00', 1, 2, 'Rizal-Cabanatuan')");
$driverActiveTrip = dispatcher_queue_trip_data($assignDb, ['BUS-B'], $assignDate, 1);
check(dispatcher_driver_effective_terminal_id($assignDb, 'DRV-D', $busB, 'BUS-B', $driverActiveTrip, $assignDate) === 1, 'An active trip pins the driver to its departure terminal');

/* The trip completes, then the arrival synchronization moves the queue entry
   (and driver assignment) to the receiving terminal without renumbering. */
$assignDb->exec("UPDATE bus_trips SET status = 'Completed', arrival_time = '11:00:00' WHERE bus_id = 'BUS-B' AND driver_id = 'DRV-D'");
check(dispatcher_sync_queue_after_arrival($assignDb, 'BUS-B', $assignDate, 2, 'DRV-B') === true, 'Arrival synchronization moves the queue entry and driver assignment');
check((int)$assignDb->query("SELECT terminal_id FROM bus_dispatch_queue WHERE operating_date = '$assignDate' AND bus_id = 'BUS-B'")->fetchColumn() === 2, 'The bus queue entry moves to the receiving terminal');
check((int)$assignDb->query("SELECT queue_position FROM bus_dispatch_queue WHERE operating_date = '$assignDate' AND bus_id = 'BUS-B'")->fetchColumn() === 2, 'The master position is never renumbered by an arrival');
check((int)$assignDb->query("SELECT terminal_id FROM driver_terminal_assignments WHERE operating_date = '$assignDate' AND driver_id = 'DRV-B'")->fetchColumn() === 2, 'The driver assignment moves with the arrival');
check(dispatcher_sync_queue_after_arrival($assignDb, 'BUS-B', $assignDate, 2, 'DRV-B') === false, 'Arrival synchronization is idempotent');
check(dispatcher_bus_in_terminal_queue($assignDb, 'BUS-B', 2, $assignDate) === true, 'The bus joins the receiving terminal queue');
check(dispatcher_bus_in_terminal_queue($assignDb, 'BUS-B', 1, $assignDate) === false, 'The bus leaves the departure terminal queue');

/* A legacy dispatch without a queue entry joins the receiving queue at the end. */
$assignDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, status, operational_status, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-D', 'DDD-1', 'Regular AC', 'Active', 'Available', 'Rizal - Cabanatuan', 1, 2)");
check(dispatcher_sync_queue_after_arrival($assignDb, 'BUS-D', $assignDate, 2, null) === true, 'A bus without a queue entry joins the receiving queue on arrival');
$legacyRows = $assignDb->query("SELECT bus_id, queue_position, terminal_id, published_at FROM bus_dispatch_queue WHERE operating_date = '$assignDate' ORDER BY queue_position")->fetchAll(PDO::FETCH_ASSOC);
check(count($legacyRows) === 3 && $legacyRows[2]['bus_id'] === 'BUS-D' && (int)$legacyRows[2]['queue_position'] === 3, 'The legacy arrival joins at the end of the master order');
check((int)$legacyRows[2]['terminal_id'] === 2, 'The inserted entry carries the receiving terminal');
check($legacyRows[2]['published_at'] === null, 'The inserted entry is not marked published');
check((int)$legacyRows[0]['queue_position'] === 1 && (int)$legacyRows[1]['queue_position'] === 2, 'Existing positions are untouched');

/* The queue build reflects the terminal entry rule. */
$receivingQueue = dispatcher_queue_build($assignDb, 2, $assignDate);
$receivingIds = array_map(static fn (array $e): string => (string)$e['bus_id'], $receivingQueue['entries']);
check(in_array('BUS-B', $receivingIds, true) && in_array('BUS-D', $receivingIds, true), 'The receiving terminal queue lists both arrived buses');

$rizalQueue = dispatcher_queue_build($assignDb, 1, $assignDate);
$busCEntry = null;
foreach ($rizalQueue['entries'] as $entry) {
    if ((string)$entry['bus_id'] === 'BUS-C') {
        $busCEntry = $entry;
    }
}
check($busCEntry !== null && $busCEntry['status'] !== 'ready', 'A bus with no terminal entry is not ready to dispatch');
$terminalEntryItem = null;
foreach (($busCEntry['readiness'] ?? []) as $readinessItem) {
    if (($readinessItem['key'] ?? '') === 'terminal_entry') {
        $terminalEntryItem = $readinessItem;
    }
}
check($terminalEntryItem !== null && $terminalEntryItem['ok'] === false && $terminalEntryItem['blocking'] === true, 'The readiness checklist requires a terminal assignment');

echo "$checks dispatcher terminal sync checks passed.\n";

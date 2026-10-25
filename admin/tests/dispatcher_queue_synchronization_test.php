<?php declare(strict_types=1);

/**
 * End-to-end synchronization test between admin DispatchQueueService
 * and dispatcher dispatcher_queue_build.
 *
 * The Admin publishes ONE master order for the operating date. That order is
 * not terminal-scoped, so each dispatcher terminal shows only the buses that
 * are actually at that terminal, in the same relative order.
 *
 * Verifies:
 * 1. Each terminal shows exactly its own buses, in the published relative order,
 *    with queue_position taken from the master order (never renumbered).
 * 2. Every published bus appears on exactly one terminal (none double-listed).
 * 3. Published positions remain fixed under:
 *    - driver check-in
 *    - trip dispatch (Departed)
 *    - trip completion (Completed, which moves the bus to the arrival terminal)
 *    - operational status change (Under Maintenance)
 *    - page refreshes and queries
 * 4. Retrieval of assigned driver, actual trip number, route, and dispatch status.
 * 5. queue_position is never derived from trip_number.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../services/Dispatch/DispatchQueueService.php';
require_once __DIR__ . '/../../dispatcher/api/helpers.php';

use Admin\Services\Dispatch\DispatchQueueService;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function disp_ids(array $data): array
{
    return array_map(static fn (array $e): string => (string)$e['bus_id'], $data['entries']);
}

function disp_positions(array $data): array
{
    return array_map(static fn (array $e): int => (int)$e['queue_position'], $data['entries']);
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
    $db->exec('CREATE TABLE drivers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        driver_id TEXT,
        full_name TEXT,
        status TEXT,
        availability TEXT,
        assigned_buses TEXT
    )');
    $db->exec('CREATE TABLE driver_bus_assignment (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        driver_id TEXT,
        bus_id TEXT,
        assigned_by TEXT,
        assigned_at TEXT
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
        route_name TEXT
    )');
    $db->exec('CREATE TABLE terminals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        terminal_name TEXT,
        terminal_code TEXT,
        status TEXT
    )');
    $db->exec('CREATE TABLE bus_problem_reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bus_id TEXT,
        problem_type TEXT,
        problem_detail TEXT,
        status TEXT,
        created_at TEXT,
        updated_at TEXT
    )');
    $db->exec('CREATE TABLE audit_trail (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        username TEXT,
        user_role TEXT,
        module TEXT,
        action TEXT,
        record_id TEXT,
        description TEXT,
        previous_values TEXT,
        new_values TEXT,
        ip_address TEXT,
        user_agent TEXT,
        status TEXT,
        created_at TEXT
    )');

    return $db;
}

function sync_fixture_db(): PDO
{
    $db = sync_test_db();
    $db->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (1, 'Rizal Terminal', 'RZL', 'Active')");
    $db->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (2, 'Cabanatuan Terminal', 'CBN', 'Active')");
    $db->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-101', 'ABC-101', 'Regular AC', 'Juan Dela Cruz', 'Active', 'Available', '05:00 AM', 'Rizal - Cabanatuan', 1, 2)");
    $db->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-102', 'ABC-102', 'First Class', 'Pedro Santos', 'Active', 'Available', '06:00 AM', 'Rizal - Cabanatuan', 1, 2)");
    $db->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-103', 'ABC-103', 'Regular AC', 'Mario Reyes', 'Active', 'Available', '07:00 AM', 'Rizal - Cabanatuan', 1, 2)");
    $db->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-104', 'ABC-104', 'First Class', 'Jose Rizal', 'Active', 'Available', '08:00 AM', 'Cabanatuan - Rizal', 2, 1)");
    $db->exec("INSERT INTO drivers (driver_id, full_name, status, availability) VALUES ('DRV-001', 'Juan Dela Cruz', 'Active', 'Available')");
    $db->exec("INSERT INTO drivers (driver_id, full_name, status, availability) VALUES ('DRV-002', 'Pedro Santos', 'Active', 'Available')");
    $db->exec("INSERT INTO drivers (driver_id, full_name, status, availability) VALUES ('DRV-003', 'Mario Reyes', 'Active', 'Available')");
    $db->exec("INSERT INTO drivers (driver_id, full_name, status, availability) VALUES ('DRV-004', 'Jose Rizal', 'Active', 'Available')");
    $db->exec("INSERT INTO driver_bus_assignment (driver_id, bus_id) VALUES ('DRV-001', 'BUS-101')");
    $db->exec("INSERT INTO driver_bus_assignment (driver_id, bus_id) VALUES ('DRV-002', 'BUS-102')");
    $db->exec("INSERT INTO driver_bus_assignment (driver_id, bus_id) VALUES ('DRV-003', 'BUS-103')");
    $db->exec("INSERT INTO driver_bus_assignment (driver_id, bus_id) VALUES ('DRV-004', 'BUS-104')");

    return $db;
}

$db = sync_fixture_db();
$date = date('Y-m-d');
$actor = ['id' => 1, 'username' => 'admin_tester', 'role' => 'Admin'];

$service = new DispatchQueueService($db);

// Step A: Admin sets draft order and publishes a single master order.
// Deliberately publish in non-default order: BUS-103 (#1), BUS-101 (#2), BUS-104 (#3), BUS-102 (#4)
$publishedOrder = ['BUS-103', 'BUS-101', 'BUS-104', 'BUS-102'];
$service->saveDraft($date, $publishedOrder, $actor);
$pubResult = $service->publish($date, $actor);
check($pubResult['success'] === true, 'Admin successfully publishes official queue order');

// Terminal membership from the fixtures: 101/102/103 are at Rizal (1), 104 at Cabanatuan (2).
$expectedTerminal1 = ['BUS-103', 'BUS-101', 'BUS-102'];
$expectedTerminal2 = ['BUS-104'];

// Step B: The master order is global; each terminal shows its own subset in the
// published relative order, with the master queue_position preserved.
$adminData = $service->queueData($date);
$disp1 = dispatcher_queue_build($db, 1, $date);
$disp2 = dispatcher_queue_build($db, 2, $date);

check(count($adminData['entries']) === count($publishedOrder), 'Admin queue has exact count of published buses');
check(disp_ids($adminData) === $publishedOrder, 'Admin queue keeps the exact published master order');
check(disp_ids($disp1) === $expectedTerminal1, 'Rizal shows only its own buses in published order');
check(disp_ids($disp2) === $expectedTerminal2, 'Cabanatuan shows only its own buses in published order');
check(disp_positions($disp1) === [1, 2, 4], 'Rizal keeps the master queue positions (no renumbering)');
check(disp_positions($disp2) === [3], 'Cabanatuan keeps the master queue position');

// Every published bus appears on exactly one terminal.
$combined = array_merge(disp_ids($disp1), disp_ids($disp2));
sort($combined);
$publishedSorted = $publishedOrder;
sort($publishedSorted);
check($combined === $publishedSorted, 'Every published bus appears on exactly one terminal');

// Step C: Driver Check-in does NOT change queue order or positions
$db->exec("INSERT INTO audit_trail (module, action, record_id, username, created_at, new_values) VALUES ('Dispatcher', 'Driver Checked In', 'BUS-101', 'disp_1', '2026-10-15 05:10:00', '{\"driver_id\":\"DRV-001\"}')");

$adminAfterCheckin = $service->queueData($date);
$disp1AfterCheckin = dispatcher_queue_build($db, 1, $date);

check(disp_ids($adminAfterCheckin) === $publishedOrder, 'Check-in: Admin master order unchanged');
check(disp_ids($disp1AfterCheckin) === $expectedTerminal1, 'Check-in: Rizal bus order unchanged');
check(disp_positions($disp1AfterCheckin) === [1, 2, 4], 'Check-in: Rizal positions unchanged');

// Step D: Dispatching a trip (Trip #9 on BUS-103, which is queue position #1).
// A departed bus is still at its departure terminal, so it stays on Rizal.
// Trip number is intentionally 9 to verify queue_position (1) is NOT derived from trip_number (9).
$db->exec("INSERT INTO bus_trips (bus_id, driver_id, trip_date, status, trip_number, departure_time, from_terminal_id, to_terminal_id, route_name) VALUES ('BUS-103', 'DRV-003', '$date', 'Departed', 9, '05:30:00', 1, 2, 'Rizal - Cabanatuan')");

$adminAfterDepart = $service->queueData($date);
$disp1AfterDepart = dispatcher_queue_build($db, 1, $date);

check(disp_ids($disp1AfterDepart) === $expectedTerminal1, 'Departed bus stays on its departure terminal');
check($disp1AfterDepart['entries'][0]['bus_id'] === 'BUS-103', 'Departed bus remains first on Rizal');
check($disp1AfterDepart['entries'][0]['queue_position'] === 1, 'Departed bus maintains queue_position 1');
check($disp1AfterDepart['entries'][0]['actual_trip_number'] === 9, 'Dispatcher retrieves actual trip number 9 from bus_trips');
check($disp1AfterDepart['entries'][0]['dispatch_status'] === 'Departed', 'Dispatcher retrieves dispatch_status Departed');
check($disp1AfterDepart['entries'][0]['status'] === 'departed', 'Dispatcher entry status is departed');

// Step E: Trip completion moves the bus to its arrival terminal (Cabanatuan).
$db->exec("UPDATE bus_trips SET status = 'Completed', arrival_time = '07:30:00' WHERE bus_id = 'BUS-103'");

$disp1AfterComplete = dispatcher_queue_build($db, 1, $date);
$disp2AfterComplete = dispatcher_queue_build($db, 2, $date);

check(disp_ids($disp1AfterComplete) === ['BUS-101', 'BUS-102'], 'Completed bus leaves its arrival terminal');
check(disp_ids($disp2AfterComplete) === ['BUS-103', 'BUS-104'], 'Completed bus now appears at its arrival terminal in published order');
check(disp_positions($disp1AfterComplete) === [2, 4], 'Remaining Rizal positions are not renumbered');
check(disp_positions($disp2AfterComplete) === [1, 3], 'Cabanatuan keeps master positions after the bus arrives');
check($disp2AfterComplete['entries'][0]['dispatch_status'] === 'Completed', 'Dispatcher retrieves dispatch_status Completed');
check(count($adminData['entries']) === 4, 'Admin master order still holds all published buses');

// Step F: Operational status change (BUS-101 marked 'Under Maintenance') keeps it listed
$db->exec("UPDATE buses SET operational_status = 'Under Maintenance' WHERE bus_id = 'BUS-101'");

$adminAfterMaint = $service->queueData($date);
$disp1AfterMaint = dispatcher_queue_build($db, 1, $date);

check(count($adminAfterMaint['entries']) === 4, 'All 4 published buses remain in Admin queue despite Under Maintenance');
check(disp_ids($disp1AfterMaint) === ['BUS-101', 'BUS-102'], 'Maintenance bus remains on its terminal, not hidden');
check(disp_positions($disp1AfterMaint) === [2, 4], 'Maintenance bus keeps its master position');

$maintIdx = array_search('BUS-101', disp_ids($adminAfterMaint), true);
check($maintIdx !== false && $adminAfterMaint['entries'][$maintIdx]['eligible'] === false, 'BUS-101 is marked ineligible in Admin');
check($disp1AfterMaint['entries'][0]['status'] === 'blocked', 'BUS-101 is marked blocked in Dispatcher');

// Step G: Multiple refreshes return identical results
for ($r = 0; $r < 3; $r++) {
    $refreshedAdmin = $service->queueData($date);
    $refreshedDisp1 = dispatcher_queue_build($db, 1, $date);
    check(disp_ids($refreshedAdmin) === $publishedOrder, 'Refresh keeps Admin master order');
    check(disp_positions($refreshedDisp1) === [2, 4], 'Refresh keeps Rizal positions');
}

// Step H: Driver & Route resolution checks (first Rizal entry is BUS-101)
check($disp1AfterMaint['entries'][0]['driver_id'] === 'DRV-001', 'Dispatcher driver DRV-001 resolved');
check($disp1AfterMaint['entries'][0]['driver_name'] === 'Juan Dela Cruz', 'Dispatcher driver name resolved');
check($disp1AfterMaint['entries'][0]['route_name'] === 'Rizal - Cabanatuan', 'Dispatcher route name resolved');

// Step J: Each module's entry set equals the saved bus_dispatch_queue rows where scoped
$savedRows = $db->query(
    "SELECT bus_id, queue_position FROM bus_dispatch_queue WHERE operating_date = '$date' AND published_at IS NOT NULL ORDER BY queue_position ASC"
)->fetchAll(PDO::FETCH_ASSOC);
check(count($savedRows) === 4, 'Database holds the exact 4 published rows for the date');

$adminSaved = $service->queueData($date);
$disp1Saved = dispatcher_queue_build($db, 1, $date);
$disp2Saved = dispatcher_queue_build($db, 2, $date);
check(count($adminSaved['entries']) === count($savedRows), 'Admin entry set equals saved published rows');

$savedByBus = [];
foreach ($savedRows as $savedRow) {
    $savedByBus[(string)$savedRow['bus_id']] = (int)$savedRow['queue_position'];
}
foreach (array_merge($disp1Saved['entries'], $disp2Saved['entries']) as $entry) {
    $entryBus = (string)$entry['bus_id'];
    check(isset($savedByBus[$entryBus]) && (int)$entry['queue_position'] === $savedByBus[$entryBus], "Dispatcher entry $entryBus matches its saved published position");
}
check(count($disp1Saved['entries']) + count($disp2Saved['entries']) === count($savedRows), 'Dispatcher terminals together cover every saved row exactly once');

// Step I: Publishing today when no queue has been saved yet auto-initializes and publishes
$dbClean = sync_fixture_db();
$todayDate = date('Y-m-d');
$dbClean->exec("DELETE FROM bus_dispatch_queue WHERE operating_date = '$todayDate'");
check((int)$dbClean->query("SELECT COUNT(*) FROM bus_dispatch_queue WHERE operating_date = '$todayDate'")->fetchColumn() === 0, 'No draft exists for today initially');

$serviceClean = new DispatchQueueService($dbClean);
$cleanPublish = $serviceClean->publish($todayDate, $actor);
check($cleanPublish['success'] === true, 'Publishing today directly without saving draft succeeds');
check($cleanPublish['data']['published'] !== null, 'Queue is published');
check(count($cleanPublish['data']['entries']) === 4, 'All 4 eligible buses published');
check((int)$dbClean->query("SELECT COUNT(*) FROM bus_dispatch_queue WHERE operating_date = '$todayDate' AND published_at IS NOT NULL")->fetchColumn() === 4, 'Database has 4 published rows for today');

$dispClean1 = dispatcher_queue_build($dbClean, 1, $todayDate);
$dispClean2 = dispatcher_queue_build($dbClean, 2, $todayDate);
check(count($dispClean1['entries']) === 3, 'Rizal dispatcher sees its 3 published buses for today');
check(count($dispClean2['entries']) === 1, 'Cabanatuan dispatcher sees its 1 published bus for today');
check(count($dispClean1['entries']) + count($dispClean2['entries']) === 4, 'All 4 published buses are covered across terminals');

/* ---------- terminal assignment moves a bus between queues on arrival ---------- */

$arrDb = sync_test_db();
$arrDb->exec('ALTER TABLE bus_dispatch_queue ADD COLUMN terminal_id INTEGER DEFAULT NULL');
$arrDb->exec('CREATE TABLE driver_terminal_assignments (
    operating_date TEXT NOT NULL,
    driver_id TEXT NOT NULL,
    terminal_id INTEGER NOT NULL,
    created_at TEXT DEFAULT NULL,
    updated_at TEXT DEFAULT NULL,
    PRIMARY KEY (operating_date, driver_id)
)');

$arrDb->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (1, 'Rizal Terminal', 'RZL', 'Active')");
$arrDb->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (2, 'Cabanatuan Terminal', 'CBN', 'Active')");
$arrDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-201', 'AAA-201', 'Regular AC', 'Driver A', 'Active', 'Available', '05:00 AM', 'Rizal - Cabanatuan', 1, 2)");
$arrDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-202', 'AAA-202', 'Regular AC', 'Driver B', 'Active', 'Available', '06:00 AM', 'Cabanatuan - Rizal', 2, 1)");
$arrDb->exec("INSERT INTO drivers (driver_id, full_name, status, availability) VALUES ('DRV-201', 'Driver A', 'Active', 'Available')");
$arrDb->exec("INSERT INTO drivers (driver_id, full_name, status, availability) VALUES ('DRV-202', 'Driver B', 'Active', 'Available')");
$arrDb->exec("INSERT INTO driver_bus_assignment (driver_id, bus_id) VALUES ('DRV-201', 'BUS-201')");
$arrDb->exec("INSERT INTO driver_bus_assignment (driver_id, bus_id) VALUES ('DRV-202', 'BUS-202')");

$arrDate = date('Y-m-d');
$arrService = new DispatchQueueService($arrDb);
$arrService->saveDraft($arrDate, ['BUS-201', 'BUS-202'], $actor);
$arrService->assignTerminal($arrDate, 'BUS-201', 1, 'DRV-201', $actor);
$arrService->assignTerminal($arrDate, 'BUS-202', 2, 'DRV-202', $actor);
$arrPublish = $arrService->publish($arrDate, $actor);
check($arrPublish['success'] === true, 'The terminal-assigned queue publishes');

$arrBefore1 = dispatcher_queue_build($arrDb, 1, $arrDate);
$arrBefore2 = dispatcher_queue_build($arrDb, 2, $arrDate);
check(disp_ids($arrBefore1) === ['BUS-201'], 'Rizal lists the bus assigned there');
check(disp_ids($arrBefore2) === ['BUS-202'], 'Cabanatuan lists the bus assigned there');

/* BUS-201 arrives at Cabanatuan: membership moves, positions never change. */
check(dispatcher_sync_queue_after_arrival($arrDb, 'BUS-201', $arrDate, 2, 'DRV-201') === true, 'The arrival synchronization reports the membership change');
dispatcher_sync_bus_after_arrival($arrDb, 'BUS-201', 2);
$arrAfter1 = dispatcher_queue_build($arrDb, 1, $arrDate);
$arrAfter2 = dispatcher_queue_build($arrDb, 2, $arrDate);
check(disp_ids($arrAfter1) === [], 'The departure terminal no longer lists the bus');
check(disp_ids($arrAfter2) === ['BUS-201', 'BUS-202'], 'The receiving terminal lists the bus in master order');
check(disp_positions($arrAfter2) === [1, 2], 'Published master positions are unchanged by the arrival');
check((int)$arrDb->query("SELECT terminal_id FROM driver_terminal_assignments WHERE operating_date = '$arrDate' AND driver_id = 'DRV-201'")->fetchColumn() === 2, 'The operating driver assignment moves to the receiving terminal');

$arrRows = $arrDb->query("SELECT bus_id, queue_position, terminal_id, published_at FROM bus_dispatch_queue WHERE operating_date = '$arrDate' ORDER BY queue_position")->fetchAll(PDO::FETCH_ASSOC);
check(count($arrRows) === 2, 'No extra queue rows are created by the arrival');
check($arrRows[0]['bus_id'] === 'BUS-201' && (int)$arrRows[0]['queue_position'] === 1, 'The arrived bus keeps its master position');
check($arrRows[0]['published_at'] !== null && (int)$arrRows[0]['terminal_id'] === 2, 'The published row keeps its stamp and gains the receiving terminal');

/* ---------- publishing defaults bus terminals and follows with the driver ---------- */

$pubDb = sync_test_db();
$pubDb->exec('ALTER TABLE bus_dispatch_queue ADD COLUMN terminal_id INTEGER DEFAULT NULL');
$pubDb->exec('CREATE TABLE driver_terminal_assignments (
    operating_date TEXT NOT NULL,
    driver_id TEXT NOT NULL,
    terminal_id INTEGER NOT NULL,
    created_at TEXT DEFAULT NULL,
    updated_at TEXT DEFAULT NULL,
    PRIMARY KEY (operating_date, driver_id)
)');
$pubDb->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (1, 'Rizal Terminal', 'RZL', 'Active')");
$pubDb->exec("INSERT INTO terminals (id, terminal_name, terminal_code, status) VALUES (2, 'Cabanatuan Terminal', 'CBN', 'Active')");
$pubDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-301', 'AAA-301', 'Regular AC', 'Driver X', 'Active', 'Available', '05:00 AM', 'TBD', 1, 2)");
$pubDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-302', 'AAA-302', 'Regular AC', 'Driver Y', 'Active', 'Available', '06:00 AM', NULL, 2, 1)");
$pubDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-303', 'AAA-303', 'Regular AC', NULL, 'Active', 'Available', '07:00 AM', '2 - 1', 2, 1)");
$pubDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-304', 'AAA-304', 'Regular AC', NULL, 'Active', 'Available', '08:00 AM', 'Rizal - Cabanatuan', 2, 1)");
$pubDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-305', 'AAA-305', 'Regular AC', NULL, 'Active', 'Available', '09:00 AM', NULL, 2, 2)");
$pubDb->exec("INSERT INTO buses (bus_id, plate_number, bus_type, driver, status, operational_status, schedule, route_name, from_terminal_id, to_terminal_id) VALUES ('BUS-306', 'AAA-306', 'Regular AC', NULL, 'Active', 'Available', '10:00 AM', NULL, 2, NULL)");
$pubDb->exec("INSERT INTO drivers (driver_id, full_name, status, availability) VALUES ('DRV-301', 'Driver X', 'Active', 'Available')");
$pubDb->exec("INSERT INTO drivers (driver_id, full_name, status, availability) VALUES ('DRV-302', 'Driver Y', 'Active', 'Available')");
$pubDb->exec("INSERT INTO driver_bus_assignment (driver_id, bus_id) VALUES ('DRV-301', 'BUS-301')");
$pubDb->exec("INSERT INTO driver_bus_assignment (driver_id, bus_id) VALUES ('DRV-302', 'BUS-302')");
/* A route already recorded on the 1 -> 2 direction (for another bus) is
   preferred over the default label for display. */
$pubDb->exec("INSERT INTO bus_trips (bus_id, driver_id, trip_date, status, trip_number, departure_time, route_name, from_terminal_id, to_terminal_id) VALUES ('HIST-1', 'DRV-H', date('now'), 'Completed', 1, '05:00:00', 'Rizal - Cabanatuan', 1, 2)");

$pubDate = date('Y-m-d');
$pubService = new DispatchQueueService($pubDb);
$pubService->saveDraft($pubDate, ['BUS-301', 'BUS-302', 'BUS-303', 'BUS-304', 'BUS-305', 'BUS-306'], $actor);

/* The displayed route always follows the bus's current direction; stored
   placeholder/garbage/stale routes are never rendered. */
$draftRoutes = [];
foreach ($pubService->queueData($pubDate)['entries'] as $draftEntry) {
    $draftRoutes[(string)$draftEntry['bus_id']] = (string)($draftEntry['route_name'] ?? '');
}
check(($draftRoutes['BUS-301'] ?? '') === 'Rizal - Cabanatuan', 'The route recorded for the direction is preferred for display');
check(($draftRoutes['BUS-302'] ?? '') === 'Cabanatuan-Rizal', 'A direction with no recorded route shows the terminal-pair default (Cabanatuan-Rizal)');
check(($draftRoutes['BUS-303'] ?? '') === 'Cabanatuan-Rizal', 'A garbage stored route ("2 - 1") is never displayed');
check(($draftRoutes['BUS-304'] ?? '') === 'Cabanatuan-Rizal', 'A stored route for the wrong direction is not displayed');
check(($draftRoutes['BUS-305'] ?? '') === 'Cabanatuan-Rizal', 'A bus configured with identical terminals still shows the pair direction');
check(($draftRoutes['BUS-306'] ?? '') === 'Cabanatuan-Rizal', 'A bus without a configured destination still shows the pair direction');

$pubService->publish($pubDate, $actor);

check((int)$pubDb->query("SELECT terminal_id FROM bus_dispatch_queue WHERE operating_date = '$pubDate' AND bus_id = 'BUS-301'")->fetchColumn() === 1, 'Publishing defaults an unassigned bus to its configured home terminal');
check((int)$pubDb->query("SELECT terminal_id FROM bus_dispatch_queue WHERE operating_date = '$pubDate' AND bus_id = 'BUS-302'")->fetchColumn() === 2, 'Each bus defaults to its own configured home terminal');
check((int)$pubDb->query("SELECT terminal_id FROM driver_terminal_assignments WHERE operating_date = '$pubDate' AND driver_id = 'DRV-301'")->fetchColumn() === 1, 'The bound driver follows the bus terminal automatically');
check((int)$pubDb->query("SELECT terminal_id FROM driver_terminal_assignments WHERE operating_date = '$pubDate' AND driver_id = 'DRV-302'")->fetchColumn() === 2, 'Every bound driver follows their own bus terminal');

check(trim((string)$pubDb->query("SELECT route_name FROM buses WHERE bus_id = 'BUS-301'")->fetchColumn()) === 'TBD', 'Publishing never rewrites the bus route record (the dispatcher chooses the route)');
check(trim((string)$pubDb->query("SELECT route_name FROM buses WHERE bus_id = 'BUS-302'")->fetchColumn()) === '', 'A bus without a stored route is left untouched by publishing');

/* A driver scoped elsewhere is re-synced with the bus at publication. */
$pubDb->exec("UPDATE driver_terminal_assignments SET terminal_id = 2 WHERE operating_date = '$pubDate' AND driver_id = 'DRV-301'");
$pubService->publish($pubDate, $actor);
check((int)$pubDb->query("SELECT terminal_id FROM driver_terminal_assignments WHERE operating_date = '$pubDate' AND driver_id = 'DRV-301'")->fetchColumn() === 1, 'Republishing re-syncs a mismatched driver terminal with the bus');

$syncEvents = (int)$pubDb->query("SELECT COUNT(*) FROM audit_trail WHERE module = 'DispatchQueue' AND action = 'Terminal Assignments Synchronized'")->fetchColumn();
check($syncEvents >= 1, 'Publication terminal synchronization is audited');

echo "$checks dispatcher queue synchronization checks passed.\n";

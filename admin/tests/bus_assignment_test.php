<?php declare(strict_types=1);

/**
 * BusAssignmentService checks on SQLite: driver/inspector/route/terminal
 * eligibility, canonical assignment with legacy mirrors, conflict handling
 * and moves, unassignment, and isolation from trip tables.
 */

require_once __DIR__ . '/../services/Bus/BusAssignmentService.php';

use Admin\Services\Bus\BusAssignmentService;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec('CREATE TABLE drivers (id INTEGER PRIMARY KEY AUTOINCREMENT, driver_id TEXT, full_name TEXT, status TEXT, assigned_buses TEXT)');
$db->exec('CREATE TABLE driver_bus_assignment (driver_id TEXT, bus_id TEXT)');
$db->exec('CREATE TABLE buses (id INTEGER PRIMARY KEY AUTOINCREMENT, bus_id TEXT, driver TEXT, status TEXT, operational_status TEXT)');
$db->exec('CREATE TABLE fare_pricing (id INTEGER PRIMARY KEY AUTOINCREMENT, route_name TEXT)');
$db->exec('CREATE TABLE route_stops (id INTEGER PRIMARY KEY AUTOINCREMENT, route_name TEXT)');
$db->exec('CREATE TABLE terminals (id INTEGER PRIMARY KEY AUTOINCREMENT, terminal_name TEXT, status TEXT)');
$db->exec('CREATE TABLE inspectors (inspector_pk INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER, inspector_name TEXT)');
$db->exec('CREATE TABLE inspector_accounts (account_id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT)');
$db->exec('CREATE TABLE bus_trips (id INTEGER PRIMARY KEY AUTOINCREMENT, bus_id TEXT, status TEXT)');
$db->exec('CREATE TABLE passenger_trips (id INTEGER PRIMARY KEY AUTOINCREMENT, bus_no TEXT)');

$db->exec("INSERT INTO drivers (driver_id, full_name, status, assigned_buses) VALUES ('D1', 'Juan Dela Cruz', 'Active', NULL)");
$db->exec("INSERT INTO drivers (driver_id, full_name, status, assigned_buses) VALUES ('D2', 'Pedro Reyes', 'Inactive', NULL)");
$db->exec("INSERT INTO buses (bus_id, driver, status) VALUES ('BUS-001', 'Unassigned', 'Active')");
$db->exec("INSERT INTO buses (bus_id, driver, status) VALUES ('BUS-002', 'Unassigned', 'Active')");
$db->exec("INSERT INTO fare_pricing (route_name) VALUES ('Cebu - Danao')");
$db->exec("INSERT INTO terminals (id, terminal_name, status) VALUES (1, 'Cebu South', 'Active')");
$db->exec("INSERT INTO terminals (id, terminal_name, status) VALUES (2, 'Cebu North', 'Inactive')");
$db->exec("INSERT INTO inspector_accounts (account_id, status) VALUES (1, 'active'), (2, 'inactive')");
$db->exec("INSERT INTO inspectors (inspector_pk, account_id, inspector_name) VALUES (1, 1, 'Ana Cruz'), (2, 2, 'Ben Lim')");
$db->exec("INSERT INTO bus_trips (bus_id, status) VALUES ('BUS-001', 'Completed')");
$db->exec("INSERT INTO passenger_trips (bus_no) VALUES ('BUS-001')");

$service = new BusAssignmentService($db);

/* ------------------------------ eligibility ------------------------------ */

check($service->validateDriver('D1') === null, 'An active driver is eligible');
check($service->validateDriver('D2') !== null, 'An inactive driver is rejected');
check($service->validateDriver('D9') !== null, 'A missing driver is rejected');
check($service->validateDriver('') === null, 'An empty driver is allowed (unassigned)');

check($service->validateRoute('Cebu - Danao') === null, 'An existing route is accepted');
check($service->validateRoute('Nope - Nowhere') !== null, 'A non-existent route is rejected');
check($service->validateRoute('') === null, 'An empty route is allowed');

check($service->validateTerminal(1) === null, 'An active terminal is accepted');
check($service->validateTerminal(2) !== null, 'An inactive terminal is rejected');
check($service->validateTerminal(99) !== null, 'A missing terminal is rejected');
check($service->validateTerminal(null) === null, 'No terminal is allowed');

check($service->validateInspector(1) === null, 'An active inspector is accepted');
check($service->validateInspector(2) !== null, 'An inactive inspector is rejected');
check($service->validateInspector(99) !== null, 'A missing inspector is rejected');

/* --------------------------- assignment lifecycle --------------------------- */

$first = $service->assign('BUS-001', 'D1');
check($first['success'] === true, 'A fresh assignment succeeds');
$canonical = $db->query('SELECT driver_id, bus_id FROM driver_bus_assignment')->fetch(PDO::FETCH_ASSOC);
check($canonical['driver_id'] === 'D1' && $canonical['bus_id'] === 'BUS-001', 'The canonical assignment row is written');
check($db->query("SELECT driver FROM buses WHERE bus_id = 'BUS-001'")->fetchColumn() === 'D1', 'The bus legacy mirror is updated');
check($db->query("SELECT assigned_buses FROM drivers WHERE driver_id = 'D1'")->fetchColumn() === 'BUS-001', 'The driver legacy mirror is updated');

$conflict = $service->assign('BUS-002', 'D1');
check($conflict['success'] === false && $conflict['conflict_bus'] === 'BUS-001', 'A driver bound elsewhere is a conflict and reports the other bus');
check($db->query("SELECT driver FROM buses WHERE bus_id = 'BUS-002'")->fetchColumn() === 'Unassigned', 'The conflicted bus is unchanged');

$move = $service->assign('BUS-002', 'D1', true);
check($move['success'] === true && $move['moved_from'] === 'BUS-001', 'A confirmed move reports where the driver came from');
$rows = $db->query('SELECT bus_id FROM driver_bus_assignment')->fetchAll(PDO::FETCH_COLUMN);
check($rows === ['BUS-002'], 'The canonical row moved to the new bus');
check($db->query("SELECT driver FROM buses WHERE bus_id = 'BUS-001'")->fetchColumn() === 'Unassigned', 'The old bus mirror is cleared');
check($db->query("SELECT assigned_buses FROM drivers WHERE driver_id = 'D1'")->fetchColumn() === 'BUS-002', 'The driver mirror points at the new bus');

$service->unassign('BUS-002');
check($db->query('SELECT COUNT(*) FROM driver_bus_assignment WHERE bus_id = \'BUS-002\'')->fetchColumn() == 0, 'Unassign removes the canonical row');
check($db->query("SELECT driver FROM buses WHERE bus_id = 'BUS-002'")->fetchColumn() === 'Unassigned', 'Unassign clears the bus mirror');
check($db->query("SELECT assigned_buses FROM drivers WHERE driver_id = 'D1'")->fetchColumn() === null, 'Unassign clears the driver mirror');

/* ------------------------------ isolation ------------------------------ */

check($db->query('SELECT COUNT(*) FROM bus_trips')->fetchColumn() == 1, 'Trip history is never written by assignments');
check($db->query('SELECT status FROM bus_trips LIMIT 1')->fetchColumn() === 'Completed', 'Existing trip rows keep their values');
check($db->query('SELECT COUNT(*) FROM passenger_trips')->fetchColumn() == 1, 'Passenger trip records are untouched');

echo "$checks bus assignment checks passed.\n";

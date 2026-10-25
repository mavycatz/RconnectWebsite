<?php declare(strict_types=1);

/**
 * DispatchQueueService checks on in-memory SQLite: the queue lists every
 * eligible BUS- bus with a number (no add/remove flow), unavailable buses are
 * reported separately, driver options expose every binding so one driver is
 * never assigned to two buses, publish gating, position overrides, and strict
 * isolation from bus_trips.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../services/Dispatch/DispatchQueueService.php';
/* The confirmed-arrival queue sync the dispatcher runs when an arrival is
   confirmed (moves the bus and its operating driver to the receiving terminal). */
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

function dispatch_test_db(): PDO
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
        created_at TEXT DEFAULT NULL
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
        to_terminal_id INTEGER DEFAULT NULL
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

function dispatch_seed(PDO $db, string $table, array $values): int
{
    $columns = array_keys($values);
    $stmt = $db->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ('
        . implode(', ', array_fill(0, count($columns), '?')) . ')');
    $stmt->execute(array_values($values));

    return (int)$db->lastInsertId();
}

function dispatch_audit_rows(PDO $db, ?string $module = null, ?string $action = null): array
{
    $sql = 'SELECT * FROM audit_trail WHERE 1=1';
    $params = [];
    if ($module !== null) {
        $sql .= ' AND module = ?';
        $params[] = $module;
    }
    if ($action !== null) {
        $sql .= ' AND action = ?';
        $params[] = $action;
    }
    $stmt = $db->prepare($sql . ' ORDER BY id ASC');
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** @return array<string,array<string,mixed>> bus_id => row (entries + unavailable) */
function dispatch_rows_by_bus(array $payload): array
{
    $byId = [];
    foreach (array_merge($payload['entries'], $payload['unavailable']) as $row) {
        $byId[$row['bus_id']] = $row;
    }

    return $byId;
}

$actor = ['id' => 7, 'username' => 'root', 'role' => 'super_admin'];
$date = date('Y-m-d');

/**
 * Standard fixture:
 *   BUS-2001 Active/Available + DRV-001 (Active, Available) assigned
 *   Bus-1001 Active/NULL, on an active Departed trip today
 *   BUS-2002 Active/Under Maintenance, one Completed trip today
 *   T-500 (not a BUS- id, excluded), BUS-9001 Archived (excluded)
 *   DRV-002 Active/On Leave, DRV-003 Inactive/Available
 */
function dispatch_fixture(): PDO
{
    $db = dispatch_test_db();
    dispatch_seed($db, 'buses', ['bus_id' => 'BUS-2001', 'plate_number' => 'NGP-2212', 'bus_type' => 'Coach', 'status' => 'Active', 'operational_status' => 'Available']);
    dispatch_seed($db, 'buses', ['bus_id' => 'Bus-1001', 'plate_number' => 'ABC-1001', 'bus_type' => 'Standard', 'status' => 'Active', 'operational_status' => null]);
    dispatch_seed($db, 'buses', ['bus_id' => 'BUS-2002', 'plate_number' => 'NGP-3385', 'bus_type' => 'Coach', 'status' => 'Active', 'operational_status' => 'Under Maintenance']);
    dispatch_seed($db, 'buses', ['bus_id' => 'T-500', 'plate_number' => 'XYZ-500', 'bus_type' => 'Standard', 'status' => 'Active', 'operational_status' => null]);
    dispatch_seed($db, 'buses', ['bus_id' => 'BUS-9001', 'plate_number' => 'OLD-9001', 'bus_type' => 'Standard', 'status' => 'Archived', 'operational_status' => null]);

    dispatch_seed($db, 'drivers', ['driver_id' => 'DRV-001', 'full_name' => 'Juan Dela Cruz', 'status' => 'Active', 'availability' => 'Available']);
    dispatch_seed($db, 'drivers', ['driver_id' => 'DRV-002', 'full_name' => 'Pedro Reyes', 'status' => 'Active', 'availability' => 'On Leave']);
    dispatch_seed($db, 'drivers', ['driver_id' => 'DRV-003', 'full_name' => 'Ana Santos', 'status' => 'Inactive', 'availability' => 'Available']);

    dispatch_seed($db, 'driver_bus_assignment', ['driver_id' => 'DRV-001', 'bus_id' => 'BUS-2001']);

    dispatch_seed($db, 'bus_trips', ['bus_id' => 'Bus-1001', 'driver_id' => 'DRV-004', 'trip_date' => date('Y-m-d'), 'status' => 'Departed', 'trip_number' => 1, 'departure_time' => '06:00:00']);
    dispatch_seed($db, 'bus_trips', ['bus_id' => 'BUS-2002', 'driver_id' => 'DRV-005', 'trip_date' => date('Y-m-d'), 'status' => 'Completed', 'trip_number' => 1, 'departure_time' => '05:00:00', 'arrival_time' => '07:00:00']);

    return $db;
}

/* --------------------------- listing and eligibility --------------------------- */

$db = dispatch_fixture();
$service = new DispatchQueueService($db);
$data = $service->queueData($date);

check($data['operating_date'] === $date, 'The payload carries the operating date');
check($data['published'] === null, 'A date with no queue is unpublished');
check(count($data['entries']) === 2, 'Every eligible BUS- bus is already in the numbered queue');
check(count($data['unavailable']) === 1, 'Only the maintenance bus is unavailable');

$byId = dispatch_rows_by_bus($data);
check(isset($byId['BUS-2001'], $byId['Bus-1001'], $byId['BUS-2002']), 'BUS- and legacy Bus- identifiers both qualify');
check(!isset($byId['T-500']) && !isset($byId['BUS-9001']), 'Non-BUS identifiers and archived buses never appear');

check($data['entries'][0]['bus_id'] === 'Bus-1001' && $data['entries'][0]['queue_position'] === 1, 'The fallback order numbers the queue from 1 without gaps');
check($data['entries'][1]['bus_id'] === 'BUS-2001' && $data['entries'][1]['queue_position'] === 2, 'Every queued row carries a real position number');
check($data['entries'][0]['persisted'] === false, 'Unpersisted entries are marked as suggestions');

check($byId['BUS-2002']['eligible'] === false && $byId['BUS-2002']['queue_position'] === null, 'Unavailable buses carry no position');
check(strpos($byId['BUS-2002']['eligibility_reason'], 'Under Maintenance') !== false, 'The maintenance block states the reason');
check($byId['Bus-1001']['trip_status'] === 'On Trip', 'An active trip shows as On Trip');
check($byId['Bus-1001']['active_trip']['status'] === 'Departed', 'The active trip detail is exposed');
check($byId['BUS-2002']['trip_status'] === 'Completed 1 trip', 'Completed trips are counted');
check($byId['BUS-2001']['trip_status'] === 'Idle', 'A bus with no trips is Idle');

check($byId['BUS-2001']['assigned_driver']['driver_id'] === 'DRV-001', 'The canonical assignment is the displayed driver');
check($byId['BUS-2001']['assigned_driver']['eligible'] === true, 'An available active driver is eligible');
check($byId['Bus-1001']['assigned_driver']['driver_id'] === null, 'An unassigned bus shows no driver');
check($byId['BUS-2001']['current_location'] === null && $byId['BUS-2001']['origin_terminal'] === null, 'Installations without terminal data simply omit location labels');

check($data['summary']['total'] === 3, 'Summary counts every listed bus');
check($data['summary']['queued'] === 2, 'Summary counts the numbered queue');
check($data['summary']['on_trip'] === 1, 'One bus is on a trip');
check($data['summary']['maintenance'] === 1, 'One bus is unavailable');

$driverOptions = [];
foreach ($data['drivers'] as $driver) {
    $driverOptions[$driver['driver_id']] = $driver;
}
check($driverOptions['DRV-001']['eligible'] === true, 'Available active drivers are selectable');
check($driverOptions['DRV-001']['assigned_buses'] === ['BUS-2001'], 'Driver options list every bus the driver is bound to');
check($driverOptions['DRV-002']['eligible'] === false && strpos($driverOptions['DRV-002']['reason'], 'On Leave') !== false, 'On-leave drivers are blocked with a reason');
check($driverOptions['DRV-003']['eligible'] === false, 'Inactive drivers are blocked');
check($driverOptions['DRV-003']['assigned_buses'] === [], 'Unbound drivers report no assignment');
check($driverOptions['DRV-001']['occupied'] === true && $driverOptions['DRV-001']['occupied_bus'] === 'BUS-2001', 'A driver already assigned to a queue bus is unavailable on page load');
check($driverOptions['DRV-002']['occupied'] === false && $driverOptions['DRV-003']['occupied'] === false, 'Drivers without an assignment start available');

$tripsBefore = (int)$db->query('SELECT COUNT(*) FROM bus_trips')->fetchColumn();

/* ------------------------------- draft ordering ------------------------------- */

$result = $service->saveDraft($date, ['Bus-1001', 'BUS-2001', 'BUS-2002'], $actor);
check($result['success'] === false, 'A draft containing an under-maintenance bus is rejected');
check(strpos((string)$result['error'], 'BUS-2002') !== false, 'The rejection names the blocked bus');

$result = $service->saveDraft($date, ['T-500'], $actor);
check($result['success'] === false && strpos((string)$result['error'], 'BUS-') !== false, 'Non-BUS identifiers cannot be queued');

$result = $service->saveDraft('14-10-2026', ['BUS-2001'], $actor);
check($result['success'] === false, 'Invalid operating dates are rejected');

$result = $service->saveDraft($date, ['Bus-1001', 'BUS-2001'], $actor);
check($result['success'] === true, 'A valid draft saves');
check(count($result['data']['entries']) === 2, 'Both queued buses are returned');
check($result['data']['entries'][0]['bus_id'] === 'Bus-1001' && $result['data']['entries'][0]['queue_position'] === 1, 'Positions follow the submitted order');
check($result['data']['entries'][1]['bus_id'] === 'BUS-2001' && $result['data']['entries'][1]['queue_position'] === 2, 'Second position follows the submitted order');
check($result['data']['entries'][0]['persisted'] === true, 'Saved entries are marked as persisted');

$result = $service->saveDraft($date, ['BUS-2001', 'Bus-1001'], $actor);
check($result['success'] === true, 'A re-order saves');
check($result['data']['entries'][0]['bus_id'] === 'BUS-2001' && $result['data']['entries'][0]['queue_position'] === 1, 'Re-ordering rewrites positions');
check(count($result['data']['unavailable']) === 1 && $result['data']['unavailable'][0]['bus_id'] === 'BUS-2002', 'Unavailable buses stay in the unavailable list');

check((int)$db->query('SELECT COUNT(*) FROM bus_trips')->fetchColumn() === $tripsBefore, 'Draft saves never create trips');
check(count(dispatch_audit_rows($db, 'DispatchQueue', 'Queue Updated')) === 2, 'Each accepted draft update is audited');
$updateAudit = dispatch_audit_rows($db, 'DispatchQueue', 'Queue Updated')[1];
check($updateAudit['record_id'] === $date && $updateAudit['username'] === 'root', 'Queue audits carry the date and actor');
check(strpos((string)$updateAudit['new_values'], 'BUS-2001') !== false, 'Queue audits carry the resulting order');

/* ------------------------------- draft overrides ------------------------------- */

$result = $service->overridePosition($date, 'BUS-2001', 2, 'Depot departure delayed.', $actor);
check($result['success'] === true, 'A position override saves on a draft date');
check($result['data']['entries'][0]['bus_id'] === 'Bus-1001' && $result['data']['entries'][0]['queue_position'] === 1, 'The override keeps the requested order');
check($result['data']['entries'][1]['bus_id'] === 'BUS-2001', 'The displaced bus shifts down');
check($result['data']['published'] === null, 'The overridden date stays a draft');
check(count(dispatch_audit_rows($db, 'DispatchQueue', 'Queue Override')) === 1, 'Overrides are audited');

$result = $service->overridePosition($date, 'Bus-1001', 1, 'x', $actor);
check($result['success'] === false, 'Short override reasons are rejected');
$result = $service->overridePosition($date, 'BUS-2002', 1, 'Not queued at all.', $actor);
check($result['success'] === false, 'Buses outside the queue cannot be overridden');

/* --------------------------------- publishing --------------------------------- */

$result = $service->publish('2099-12-31', $actor);
check($result['success'] === false, 'A date with no saved queue cannot be published');

$result = $service->publish($date, $actor);
check($result['success'] === true, 'A valid draft publishes');
check($result['data']['published'] !== null && $result['data']['published']['by'] === 'root', 'Publication records the actor');
check(count(dispatch_audit_rows($db, 'DispatchQueue', 'Queue Published')) === 1, 'Publication is audited');

// A bus going into maintenance invalidates publishing and leaves the queue.
$db->exec("UPDATE buses SET operational_status = 'Under Maintenance' WHERE bus_id = 'BUS-2001'");
$service2 = new DispatchQueueService($db);
$result = $service2->publish($date, $actor);
check($result['success'] === false, 'Publishing re-validates the queued buses');
check(count($result['blockers']) >= 1 && strpos($result['blockers'][0], 'BUS-2001') !== false, 'The publish report lists the blocked bus');

$stale = $service2->queueData($date);
check(count($stale['entries']) === 2, 'Once published, buses stay in the queue even when status changes');
check($stale['entries'][0]['bus_id'] === 'Bus-1001' && $stale['entries'][0]['queue_position'] === 1, 'Published positions are preserved when a bus changes status');
check($stale['entries'][1]['bus_id'] === 'BUS-2001' && $stale['entries'][1]['queue_position'] === 2, 'Remaining positions are not renumbered');
check($stale['entries'][1]['eligible'] === false, 'The unavailable bus is marked ineligible');
check($stale['entries'][1]['persisted'] === true, 'The unavailable persisted bus is reported as persisted');

$db->exec("UPDATE buses SET operational_status = 'Available' WHERE bus_id = 'BUS-2001'");

/* ------------------ published dates lock every queue change ------------------ */

$result = $service2->saveDraft($date, ['BUS-2001', 'Bus-1001'], $actor);
check($result['success'] === false && stripos((string)$result['error'], 'published') !== false, 'A published date rejects queue saves and reorders');

$result = $service2->overridePosition($date, 'BUS-2001', 1, 'Trying a published override.', $actor);
check($result['success'] === false && stripos((string)$result['error'], 'published') !== false, 'A published date rejects position overrides');

$result = $service2->assignDriver('Bus-1001', $date, 'DRV-001', true, $actor);
check($result['success'] === false && stripos((string)$result['error'], 'published') !== false, 'A published date rejects driver assignment changes');

$result = $service2->setOperationalStatus('BUS-2001', 'Under Maintenance', 'Trying a published change.', $date, $actor);
check($result['success'] === false && stripos((string)$result['error'], 'published') !== false, 'A published date rejects availability changes');

$lockedRows = $db->query('SELECT bus_id, queue_position, published_at FROM bus_dispatch_queue WHERE operating_date = \'' . $date . '\' ORDER BY queue_position')->fetchAll(PDO::FETCH_ASSOC);
check(count($lockedRows) === 2, 'Rejected published-date edits leave the queue rows untouched');
check($lockedRows[0]['bus_id'] === 'Bus-1001' && (int)$lockedRows[0]['queue_position'] === 1, 'Rejected published-date edits leave positions unchanged');
check($lockedRows[0]['published_at'] !== null && $lockedRows[1]['published_at'] !== null, 'Rejected published-date edits keep the publication stamp');

/* ----------------------------- driver assignment ----------------------------- */

$assignDb = dispatch_fixture();
$assignService = new DispatchQueueService($assignDb);
$result = $assignService->saveDraft($date, ['Bus-1001', 'BUS-2001'], $actor);
check($result['success'] === true, 'A fresh draft saves for the driver-assignment checks');

/* The fixture's DRV-001 is bound to the queued BUS-2001 without a completed
   trip, so the assignment is active and the driver is not available. */
$assignOptions = [];
foreach ($assignService->queueData($date)['drivers'] as $option) {
    $assignOptions[$option['driver_id']] = $option;
}
check($assignOptions['DRV-001']['occupied'] === true && $assignOptions['DRV-001']['occupied_bus'] === 'BUS-2001', 'An already-assigned driver is reported unavailable on page load');
check(strpos((string)$assignOptions['DRV-001']['occupied_reason'], 'BUS-2001') !== false, 'The unavailable reason names the assigned bus');
check($assignOptions['DRV-002']['occupied'] === false && $assignOptions['DRV-003']['occupied'] === false, 'Drivers without an assignment stay available');

$result = $assignService->assignDriver('BUS-2001', $date, 'DRV-002', false, $actor);
check($result['success'] === false && strpos((string)$result['error'], 'On Leave') !== false, 'On-leave drivers cannot be assigned');
$result = $assignService->assignDriver('BUS-2001', $date, 'DRV-003', false, $actor);
check($result['success'] === false, 'Inactive drivers cannot be assigned');
$result = $assignService->assignDriver('BUS-2002', $date, 'DRV-001', false, $actor);
check($result['success'] === false, 'A bus under maintenance cannot receive a driver');
$result = $assignService->assignDriver('Bus-1001', $date, 'DRV-001', false, $actor);
check($result['success'] === false && strpos((string)$result['error'], 'active trip') !== false, 'A bus with an active trip cannot have its driver changed');

$assignDb->exec("DELETE FROM bus_trips WHERE bus_id = 'Bus-1001'");
$result = $assignService->assignDriver('Bus-1001', $date, 'DRV-001', false, $actor);
check($result['success'] === false && stripos((string)$result['error'], 'assigned to bus BUS-2001') !== false, 'An occupied driver cannot be assigned to another bus');
check($result['conflict_bus'] === null, 'The occupied-driver rejection is not a movable conflict');

$result = $assignService->assignDriver('Bus-1001', $date, 'DRV-001', true, $actor);
check($result['success'] === false, 'Even a confirmed move cannot take an occupied driver');

/* Completing the assigned bus's trip with a confirmed arrival releases the
   driver again, at the receiving terminal. */
dispatch_seed($assignDb, 'bus_trips', ['bus_id' => 'BUS-2001', 'driver_id' => 'DRV-001', 'trip_date' => $date, 'status' => 'Completed', 'trip_number' => 1, 'departure_time' => '05:00:00', 'arrival_time' => '07:00:00', 'from_terminal_id' => 1, 'to_terminal_id' => 2]);
$releasedOptions = [];
foreach ($assignService->queueData($date)['drivers'] as $option) {
    $releasedOptions[$option['driver_id']] = $option;
}
check($releasedOptions['DRV-001']['occupied'] === false, 'A completed trip with a confirmed arrival releases the driver');

$tripsAfterDelete = (int)$assignDb->query('SELECT COUNT(*) FROM bus_trips')->fetchColumn();
$result = $assignService->assignDriver('Bus-1001', $date, 'DRV-001', false, $actor);
check($result['success'] === false && $result['conflict_bus'] === 'BUS-2001', 'A released driver bound elsewhere reports the move conflict');

$result = $assignService->assignDriver('Bus-1001', $date, 'DRV-001', true, $actor);
check($result['success'] === true && $result['moved_from'] === 'BUS-2001', 'Confirming the move reassigns the released driver');
check($assignDb->query("SELECT assigned_buses FROM drivers WHERE driver_id = 'DRV-001'")->fetchColumn() === 'Bus-1001', 'The legacy driver mirror is updated');
check($assignDb->query("SELECT COUNT(*) FROM driver_bus_assignment WHERE driver_id = 'DRV-001'")->fetchColumn() == 1, 'A driver ends up bound to exactly one bus');

$assigned = dispatch_audit_rows($assignDb, 'Bus', 'Driver Assigned');
check(count($assigned) === 1, 'A new assignment is audited on the bus history');
check($assigned[0]['record_id'] === 'Bus-1001' && strpos((string)$assigned[0]['description'], 'DRV-001') !== false, 'The assignment audit names the bus and driver');

$result = $assignService->assignDriver('Bus-1001', $date, 'DRV-001', false, $actor);
check($result['success'] === true, 'Re-assigning the same driver to the same bus stays valid');
check(count(dispatch_audit_rows($assignDb, 'Bus', 'Driver Reassigned')) === 1, 'A change of driver is audited as a reassignment');

$result = $assignService->assignDriver('T-500', $date, 'DRV-001', true, $actor);
check($result['success'] === false, 'Non-BUS buses cannot be assigned');

check((int)$assignDb->query('SELECT COUNT(*) FROM bus_trips')->fetchColumn() === $tripsAfterDelete, 'Assignment and queue flows never create trips');

/* ------------------------- buses with open problem reports ------------------------- */

$problemDb = dispatch_fixture();
$problemService = new DispatchQueueService($problemDb);
$result = $problemService->saveDraft($date, ['Bus-1001', 'BUS-2001'], $actor);
check($result['success'] === true, 'The queue saves before any problem is reported');

dispatch_seed($problemDb, 'bus_problem_reports', [
    'bus_id' => 'BUS-2001',
    'problem_type' => 'Engine overheating',
    'problem_detail' => 'Coolant leak on the highway shoulder.',
    'status' => 'waiting',
    'created_at' => '2026-10-14 07:30:00',
]);

$data = $problemService->queueData($date);
$problemById = dispatch_rows_by_bus($data);
check(count($data['entries']) === 1 && $data['entries'][0]['bus_id'] === 'Bus-1001', 'A bus with an open problem drops out of the numbered queue');
check(isset($problemById['BUS-2001']) && $problemById['BUS-2001']['eligible'] === false, 'The problem bus is reported as unavailable');
check(strpos($problemById['BUS-2001']['eligibility_reason'], 'Open problem report') !== false && strpos($problemById['BUS-2001']['eligibility_reason'], 'Engine overheating') !== false, 'The unavailable reason names the open problem');
check(($problemById['BUS-2001']['problem']['status'] ?? '') === 'waiting', 'The open problem is exposed on the bus payload');
check((int)$data['summary']['maintenance'] === 2, 'The unavailable summary counts the problem bus');

$result = $problemService->saveDraft($date, ['Bus-1001', 'BUS-2001'], $actor);
check($result['success'] === false && strpos((string)$result['error'], 'Open problem report') !== false, 'A bus with an open problem cannot be queued');

$result = $problemService->assignDriver('BUS-2001', $date, 'DRV-003', false, $actor);
check($result['success'] === false && strpos((string)$result['error'], 'Open problem report') !== false, 'A bus with an open problem cannot receive a driver');

$result = $problemService->publish($date, $actor);
check($result['success'] === false && strpos(implode(' ', $result['blockers']), 'Open problem report') !== false, 'Publishing is blocked while the problem is open');

$problemDb->exec("UPDATE bus_problem_reports SET status = 'acknowledged' WHERE bus_id = 'BUS-2001'");
$data = $problemService->queueData($date);
$problemById = dispatch_rows_by_bus($data);
check($problemById['BUS-2001']['eligible'] === false && strpos($problemById['BUS-2001']['eligibility_reason'], 'acknowledged') !== false, 'An acknowledged but unresolved problem still blocks the bus');

$problemDb->exec("UPDATE bus_problem_reports SET status = 'resolved' WHERE bus_id = 'BUS-2001'");
$data = $problemService->queueData($date);
check(count($data['entries']) === 2, 'Resolving the report returns the bus to the queue');
$result = $problemService->publish($date, $actor);
check($result['success'] === true, 'Publishing succeeds after the report is resolved');

/* ----------------------- admin marks a bus unavailable ----------------------- */

$markDb = dispatch_fixture();
$markService = new DispatchQueueService($markDb);

$result = $markService->setOperationalStatus('BUS-2001', 'Under Maintenance', 'Brake pads worn, pulled from service.', $date, $actor);
check($result['success'] === true && $result['unchanged'] === false, 'The admin can mark a bus unavailable');

$data = $markService->queueData($date);
$markedById = dispatch_rows_by_bus($data);
check($markedById['BUS-2001']['eligible'] === false && strpos($markedById['BUS-2001']['eligibility_reason'], 'Under Maintenance') !== false, 'The marked bus is unavailable with the maintenance reason');
check(count($data['entries']) === 1 && $data['entries'][0]['bus_id'] === 'Bus-1001', 'The marked bus leaves the numbered queue');

$result = $markService->saveDraft($date, ['Bus-1001', 'BUS-2001'], $actor);
check($result['success'] === false, 'A marked-unavailable bus cannot be queued');
$result = $markService->assignDriver('BUS-2001', $date, 'DRV-003', false, $actor);
check($result['success'] === false, 'A marked-unavailable bus cannot receive a driver');

$audit = dispatch_audit_rows($markDb, 'Bus', 'Marked Unavailable');
check(count($audit) === 1 && $audit[0]['record_id'] === 'BUS-2001', 'Marking unavailable is audited on the bus');
check(strpos((string)$audit[0]['new_values'], 'Brake pads worn') !== false, 'The audit carries the reason');

$result = $markService->setOperationalStatus('BUS-2001', 'Under Maintenance', 'No change this time at all.', $date, $actor);
check($result['success'] === true && $result['unchanged'] === true, 'Re-saving the same status is a no-op');
check(count(dispatch_audit_rows($markDb, 'Bus', 'Marked Unavailable')) === 1, 'A no-op writes no extra audit row');

$result = $markService->setOperationalStatus('BUS-2001', 'Available', 'Repairs completed and road-tested.', $date, $actor);
check($result['success'] === true && $result['unchanged'] === false, 'The admin can mark the bus available again');
$data = $markService->queueData($date);
check(count($data['entries']) === 2, 'The restored bus returns to the numbered queue');
check(count(dispatch_audit_rows($markDb, 'Bus', 'Marked Available')) === 1, 'Restoring availability is audited');

$result = $markService->setOperationalStatus('BUS-2001', 'On Trip', 'Trying a system status value.', $date, $actor);
check($result['success'] === false, 'System-managed statuses cannot be set manually');
$result = $markService->setOperationalStatus('BUS-2001', 'Out of Service', 'no', $date, $actor);
check($result['success'] === false, 'Short reasons are rejected');
$result = $markService->setOperationalStatus('T-500', 'Under Maintenance', 'Not a BUS- identifier.', $date, $actor);
check($result['success'] === false, 'Non-BUS identifiers cannot be marked');

/* ---------------------- past dates are read-only history ---------------------- */

$pastDate = '2020-01-01';
check($service->queueData($pastDate)['is_history'] === true, 'A past operating date is reported as history');
check($service->queueData($date)['is_history'] === false, 'The current operating date is not history');

$result = $service->publish($pastDate, $actor);
check($result['success'] === false && strpos((string)$result['error'], 'history') !== false, 'A past operating date cannot be published');

$result = $service->saveDraft($pastDate, ['BUS-2001'], $actor);
check($result['success'] === false && strpos((string)$result['error'], 'history') !== false, 'A past operating date cannot be saved');

$result = $service2->overridePosition($pastDate, 'BUS-2001', 1, 'Trying a history override.', $actor);
check($result['success'] === false && strpos((string)$result['error'], 'history') !== false, 'A past operating date cannot be overridden');

$result = $service2->assignDriver('BUS-2001', $pastDate, 'DRV-001', false, $actor);
check($result['success'] === false && strpos((string)$result['error'], 'history') !== false, 'A past operating date cannot change driver assignments');

$result = $markService->setOperationalStatus('BUS-2001', 'Under Maintenance', 'Trying a history change.', $pastDate, $actor);
check($result['success'] === false && strpos((string)$result['error'], 'history') !== false, 'A past operating date cannot change bus availability');

/* ----------------------- published order for the dispatcher ----------------------- */

$pubDb = dispatch_fixture();
$pubService = new DispatchQueueService($pubDb);
$pubService->saveDraft($date, ['Bus-1001', 'BUS-2001'], $actor);
$pubService->publish($date, $actor);
$published = $pubDb->query('SELECT bus_id, queue_position, published_at, published_by FROM bus_dispatch_queue WHERE operating_date = \'' . $date . '\' ORDER BY queue_position')->fetchAll(PDO::FETCH_ASSOC);
check(count($published) === 2, 'The published queue is persisted per operating date');
check($published[0]['published_at'] !== null && $published[0]['published_by'] === 'root', 'Published rows carry the publication stamp');

/* ---------------------------------- history ---------------------------------- */

$history = $service2->history($date);
$actions = array_map(static fn (array $row): string => (string)$row['action'], $history['activity']);
check(in_array('Queue Updated', $actions, true), 'History lists queue updates for the date');
check(in_array('Queue Published', $actions, true), 'History lists publications for the date');
check(in_array('Queue Override', $actions, true), 'History lists overrides for the date');
check($history['activity'][0]['username'] === 'root', 'History rows carry the acting user');
check(count($history['published_dates']) === 1, 'History lists the recently published orders');
check($history['published_dates'][0]['operating_date'] === $date, 'The published order is keyed by operating date');
check((int)$history['published_dates'][0]['buses'] === 2 && $history['published_dates'][0]['published_by'] === 'root', 'Published orders carry the bus count and publisher');

$freshHistory = (new DispatchQueueService(dispatch_test_db()))->history($date);
check($freshHistory['activity'] === [] && $freshHistory['published_dates'] === [], 'An untouched database reports empty history');

/* ------------- duplicate assignment rows still bind a driver in options ------------- */

$dupDb = dispatch_test_db();
dispatch_seed($dupDb, 'buses', ['bus_id' => 'BUS-3001', 'plate_number' => 'AAA-3001', 'status' => 'Active', 'operational_status' => 'Available']);
dispatch_seed($dupDb, 'buses', ['bus_id' => 'BUS-3002', 'plate_number' => 'AAA-3002', 'status' => 'Active', 'operational_status' => 'Available']);
dispatch_seed($dupDb, 'drivers', ['driver_id' => 'DRV-100', 'full_name' => 'Dup Bound', 'status' => 'Active', 'availability' => 'Available']);
dispatch_seed($dupDb, 'driver_bus_assignment', ['driver_id' => 'DRV-100', 'bus_id' => 'BUS-3001']);
dispatch_seed($dupDb, 'driver_bus_assignment', ['driver_id' => 'DRV-100', 'bus_id' => 'BUS-3002']);
$dupData = (new DispatchQueueService($dupDb))->queueData($date);
$dupOption = null;
foreach ($dupData['drivers'] as $option) {
    if ($option['driver_id'] === 'DRV-100') {
        $dupOption = $option;
    }
}
check($dupOption !== null && $dupOption['assigned_bus'] === 'BUS-3001' && $dupOption['assigned_buses'] === ['BUS-3001'], 'Legacy duplicate rows collapse to a single canonical assignment per driver');
$dupById = dispatch_rows_by_bus($dupData);
check(isset($dupById['BUS-3002']) && $dupById['BUS-3002']['assigned_driver']['driver_id'] === null, 'A driver is never displayed on two buses at once');

/* --------------------- both terminals and live bus locations --------------------- */

$termDb = dispatch_test_db();
dispatch_seed($termDb, 'terminals', ['id' => 1, 'terminal_name' => 'Rizal Central Terminal', 'terminal_code' => 'RZL', 'status' => 'Active']);
dispatch_seed($termDb, 'terminals', ['id' => 2, 'terminal_name' => 'Cabanatuan Central Terminal', 'terminal_code' => 'CBT', 'status' => 'Active']);
dispatch_seed($termDb, 'buses', ['bus_id' => 'BUS-4001', 'plate_number' => 'AAA-4001', 'status' => 'Active', 'operational_status' => 'Available', 'from_terminal_id' => 1, 'to_terminal_id' => 2]);
dispatch_seed($termDb, 'buses', ['bus_id' => 'BUS-4002', 'plate_number' => 'AAA-4002', 'status' => 'Active', 'operational_status' => 'Available', 'from_terminal_id' => 2, 'to_terminal_id' => 1]);
dispatch_seed($termDb, 'buses', ['bus_id' => 'BUS-4003', 'plate_number' => 'AAA-4003', 'status' => 'Active', 'operational_status' => 'Available', 'from_terminal_id' => 1, 'to_terminal_id' => 2]);
dispatch_seed($termDb, 'bus_trips', ['bus_id' => 'BUS-4002', 'driver_id' => 'DRV-401', 'trip_date' => $date, 'status' => 'Departed', 'trip_number' => 1, 'departure_time' => '06:00:00', 'from_terminal_id' => 2, 'to_terminal_id' => 1]);
dispatch_seed($termDb, 'bus_trips', ['bus_id' => 'BUS-4003', 'driver_id' => 'DRV-402', 'trip_date' => $date, 'status' => 'Completed', 'trip_number' => 1, 'departure_time' => '05:00:00', 'arrival_time' => '07:00:00', 'from_terminal_id' => 2, 'to_terminal_id' => 1]);

$termData = (new DispatchQueueService($termDb))->queueData($date);
$termById = dispatch_rows_by_bus($termData);
check(count($termData['entries']) === 3, 'One master queue lists the buses of both terminals together');
check($termData['terminals'] === [
    ['id' => 2, 'name' => 'Cabanatuan Central Terminal'],
    ['id' => 1, 'name' => 'Rizal Central Terminal'],
], 'The queue exposes both origin terminals for filtering');

check($termById['BUS-4001']['origin_terminal'] === 'Rizal Central Terminal' && $termById['BUS-4001']['destination_terminal'] === 'Cabanatuan Central Terminal', 'Departure and arrival terminals resolve by name');
check($termById['BUS-4001']['current_location'] === 'At Rizal Central Terminal', 'An idle bus is parked at its origin terminal');
check(
    $termById['BUS-4002']['trip_status'] === 'On Trip'
    && strpos((string)$termById['BUS-4002']['current_location'], 'En route: Cabanatuan Central Terminal') !== false
    && strpos((string)$termById['BUS-4002']['current_location'], 'Rizal Central Terminal') !== false,
    'An active trip shows the bus en route between the real terminals'
);
check($termById['BUS-4003']['current_location'] === 'At Rizal Central Terminal', 'A completed trip tracks the bus at its actual arrival terminal (not the bus destination)');
check($termById['BUS-4003']['current_terminal'] === 'Rizal Central Terminal', 'The current terminal is exposed for availability tracking');
check($termById['BUS-4001']['effective_terminal_id'] === 1 && $termById['BUS-4001']['dispatch_origin_terminal'] === 'Rizal Central Terminal', 'The dispatch origin is where the bus actually is');
check($termById['BUS-4001']['receiving_terminal_id'] === 2 && $termById['BUS-4001']['receiving_terminal'] === 'Cabanatuan Central Terminal', 'The receiving terminal is the configured counterpart for the direction');
check($termById['BUS-4002']['effective_terminal_id'] === 2 && $termById['BUS-4002']['receiving_terminal_id'] === 1, 'An active trip keeps the origin as current and the trip destination as receiving');
check($termById['BUS-4001']['terminal_id'] === null, 'A bus with no saved assignment has no terminal entry yet');

/* ------------------- terminal assignment and driver terminals ------------------- */

$queueDb = dispatch_test_db();
dispatch_seed($queueDb, 'terminals', ['id' => 1, 'terminal_name' => 'Rizal Central Terminal', 'terminal_code' => 'RZL', 'status' => 'Active']);
dispatch_seed($queueDb, 'terminals', ['id' => 2, 'terminal_name' => 'Cabanatuan Central Terminal', 'terminal_code' => 'CBT', 'status' => 'Active']);
dispatch_seed($queueDb, 'buses', ['bus_id' => 'BUS-5001', 'plate_number' => 'AAA-5001', 'status' => 'Active', 'operational_status' => 'Available', 'from_terminal_id' => 1, 'to_terminal_id' => 2]);
dispatch_seed($queueDb, 'buses', ['bus_id' => 'BUS-5002', 'plate_number' => 'AAA-5002', 'status' => 'Active', 'operational_status' => 'Available', 'from_terminal_id' => 2, 'to_terminal_id' => 1]);
dispatch_seed($queueDb, 'buses', ['bus_id' => 'BUS-5003', 'plate_number' => 'AAA-5003', 'status' => 'Active', 'operational_status' => 'Under Maintenance', 'from_terminal_id' => 1, 'to_terminal_id' => 2]);
dispatch_seed($queueDb, 'buses', ['bus_id' => 'BUS-5004', 'plate_number' => 'AAA-5004', 'status' => 'Active', 'operational_status' => 'Available', 'from_terminal_id' => 2, 'to_terminal_id' => 1]);
dispatch_seed($queueDb, 'buses', ['bus_id' => 'BUS-5005', 'plate_number' => 'AAA-5005', 'status' => 'Active', 'operational_status' => 'Available', 'from_terminal_id' => 1, 'to_terminal_id' => 2]);
dispatch_seed($queueDb, 'bus_trips', ['bus_id' => 'BUS-5004', 'driver_id' => 'DRV-504', 'trip_date' => $date, 'status' => 'Departed', 'trip_number' => 1, 'departure_time' => '06:00:00', 'from_terminal_id' => 2, 'to_terminal_id' => 1]);
dispatch_seed($queueDb, 'drivers', ['driver_id' => 'DRV-501', 'full_name' => 'Terminal Driver', 'status' => 'Active', 'availability' => 'Available']);
dispatch_seed($queueDb, 'drivers', ['driver_id' => 'DRV-502', 'full_name' => 'On Leave Driver', 'status' => 'Active', 'availability' => 'On Leave']);
dispatch_seed($queueDb, 'driver_bus_assignment', ['driver_id' => 'DRV-501', 'bus_id' => 'BUS-5001']);

$queueService = new DispatchQueueService($queueDb);
$result = $queueService->saveDraft($date, ['BUS-5001', 'BUS-5002', 'BUS-5004'], $actor);
check($result['success'] === true, 'A terminal fixture draft saves without assignments');

$result = $queueService->assignTerminal($date, 'BUS-5001', 2, 'DRV-501', $actor);
check($result['success'] === true, 'A bus and driver can be assigned to a terminal for the date');

$queueData = $result['data'];
$queueById = dispatch_rows_by_bus($queueData);
check($queueById['BUS-5001']['terminal_id'] === 2 && $queueById['BUS-5001']['terminal'] === 'Cabanatuan Central Terminal', 'The saved terminal assignment is exposed on the entry');
check($queueById['BUS-5001']['effective_terminal_id'] === 2 && $queueById['BUS-5001']['current_terminal'] === 'Cabanatuan Central Terminal', 'An idle assignment seeds the current terminal');
check($queueById['BUS-5001']['dispatch_origin_terminal_id'] === 2 && $queueById['BUS-5001']['receiving_terminal_id'] === 1, 'The assigned terminal becomes the dispatch origin and the opposite terminal the receiving destination');
check($queueById['BUS-5001']['assigned_driver']['terminal_id'] === 2 && $queueById['BUS-5001']['assigned_driver']['terminal'] === 'Cabanatuan Central Terminal', 'The driver terminal is displayed independently of the bus');
check($queueById['BUS-5001']['assigned_driver']['receiving_terminal'] === 'Rizal Central Terminal', 'The driver receiving terminal follows the assignment direction');

check((int)$queueDb->query("SELECT terminal_id FROM bus_dispatch_queue WHERE operating_date = '$date' AND bus_id = 'BUS-5001'")->fetchColumn() === 2, 'The bus assignment is stored on the queue entry');
check((int)$queueDb->query("SELECT terminal_id FROM driver_terminal_assignments WHERE operating_date = '$date' AND driver_id = 'DRV-501'")->fetchColumn() === 2, 'The driver assignment is stored in its own per-date record');
check(count(dispatch_audit_rows($queueDb, 'DispatchQueue', 'Terminal Assigned')) === 1, 'Terminal assignments are audited on the queue history');

$result = $queueService->assignTerminal($date, 'BUS-5005', 99, null, $actor);
check($result['success'] === false && strpos((string)$result['error'], 'valid terminal') !== false, 'An unknown terminal cannot be assigned');
$result = $queueService->assignTerminal($date, 'BUS-5003', 1, null, $actor);
check($result['success'] === false && strpos((string)$result['error'], 'Under Maintenance') !== false, 'An ineligible bus cannot be assigned to a terminal');
$result = $queueService->assignTerminal($date, 'BUS-5004', 1, null, $actor);
check($result['success'] === false && strpos((string)$result['error'], 'active trip') !== false, 'A bus on an active trip keeps its origin terminal until arrival is confirmed');
$result = $queueService->assignTerminal($date, 'BUS-5005', 1, 'DRV-502', $actor);
check($result['success'] === false && strpos((string)$result['error'], 'On Leave') !== false, 'An ineligible driver cannot be assigned to a terminal');

$result = $queueService->assignDriver('BUS-5005', $date, 'DRV-501', false, $actor);
check($result['success'] === false && strpos((string)$result['error'], 'not to the bus\'s terminal') !== false, 'A driver assigned to another terminal cannot take a bus at this terminal');
$result = $queueService->assignDriver('BUS-5002', $date, 'DRV-501', true, $actor);
check($result['success'] === false && stripos((string)$result['error'], 'stays unavailable') !== false, 'An occupied driver cannot be moved to another bus before the arrival is confirmed');

$driverOptionsById = [];
foreach ($queueService->queueData($date)['drivers'] as $option) {
    $driverOptionsById[$option['driver_id']] = $option;
}
check($driverOptionsById['DRV-501']['occupied'] === true && $driverOptionsById['DRV-501']['occupied_bus'] === 'BUS-5001', 'The terminal-assigned driver is unavailable while assigned');
check($driverOptionsById['DRV-501']['terminal_id'] === 2 && $driverOptionsById['DRV-501']['terminal'] === 'Cabanatuan Central Terminal', 'Driver options expose the operating-date terminal for scoping');

dispatch_seed($queueDb, 'drivers', ['driver_id' => 'DRV-504', 'full_name' => 'En Route Driver', 'status' => 'Active', 'availability' => 'Available']);
$activeOptions = [];
foreach ($queueService->queueData($date)['drivers'] as $option) {
    $activeOptions[$option['driver_id']] = $option;
}
check($activeOptions['DRV-504']['occupied'] === true && strpos((string)$activeOptions['DRV-504']['occupied_reason'], 'active trip') !== false, 'A driver on an active trip is unavailable even without an explicit binding');

/* The bus's trip completes and the arrival is confirmed: the driver is
   released again at the receiving terminal. */
dispatch_seed($queueDb, 'bus_trips', ['bus_id' => 'BUS-5001', 'driver_id' => 'DRV-501', 'trip_date' => $date, 'status' => 'Completed', 'trip_number' => 1, 'departure_time' => '05:00:00', 'arrival_time' => '07:00:00', 'from_terminal_id' => 1, 'to_terminal_id' => 2]);
$releasedQueueOptions = [];
foreach ($queueService->queueData($date)['drivers'] as $option) {
    $releasedQueueOptions[$option['driver_id']] = $option;
}
check($releasedQueueOptions['DRV-501']['occupied'] === false, 'The driver is available again after the confirmed arrival');
$result = $queueService->assignDriver('BUS-5002', $date, 'DRV-501', true, $actor);
check($result['success'] === true, 'The released driver can take a bus at the receiving terminal');

$result = $queueService->assignTerminal($date, 'BUS-5002', 2, 'DRV-501', $actor, 1);
check($result['success'] === true, 'A driver terminal can be assigned independently from the bus');
$independentById = dispatch_rows_by_bus($result['data']);
check($independentById['BUS-5002']['terminal_id'] === 2 && $independentById['BUS-5002']['assigned_driver']['terminal_id'] === 1, 'The driver terminal stores separately from the bus terminal');
check((int)$queueDb->query("SELECT terminal_id FROM bus_dispatch_queue WHERE operating_date = '$date' AND bus_id = 'BUS-5002'")->fetchColumn() === 2, 'The bus keeps its own terminal assignment');
check((int)$queueDb->query("SELECT terminal_id FROM driver_terminal_assignments WHERE operating_date = '$date' AND driver_id = 'DRV-501'")->fetchColumn() === 1, 'The independent driver terminal is stored per date');

$result = $queueService->saveDraft($date, ['BUS-5005', 'BUS-5001', 'BUS-5002'], $actor);
check($result['success'] === true, 'Reordering the draft keeps working with assignments');
$assignmentsAfterSave = [];
foreach ($queueDb->query("SELECT bus_id, terminal_id FROM bus_dispatch_queue WHERE operating_date = '$date'")->fetchAll(PDO::FETCH_ASSOC) as $savedRow) {
    $assignmentsAfterSave[(string)$savedRow['bus_id']] = $savedRow['terminal_id'] !== null ? (int)$savedRow['terminal_id'] : null;
}
check(($assignmentsAfterSave['BUS-5001'] ?? null) === 2, 'A draft reorder preserves the bus terminal assignment');
check(array_key_exists('BUS-5005', $assignmentsAfterSave) && $assignmentsAfterSave['BUS-5005'] === null, 'A newly queued bus enters without a terminal until it is assigned');

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$result = $queueService->assignTerminal($tomorrow, 'BUS-5001', 1, null, $actor);
check($result['success'] === true, 'The same bus can be assigned on another operating date');
check((int)$queueDb->query("SELECT terminal_id FROM bus_dispatch_queue WHERE operating_date = '$date' AND bus_id = 'BUS-5001'")->fetchColumn() === 2, 'Assigning another date leaves this date\'s queue unchanged');
check((int)$queueDb->query("SELECT terminal_id FROM bus_dispatch_queue WHERE operating_date = '$tomorrow' AND bus_id = 'BUS-5001'")->fetchColumn() === 1, 'The other date carries its own assignment');
$tomorrowById = dispatch_rows_by_bus($queueService->queueData($tomorrow));
check($tomorrowById['BUS-5001']['effective_terminal_id'] === 1, 'The other date resolves its own assigned terminal');

$result = $queueService->publish($date, $actor);
check($result['success'] === true, 'The terminal-based draft publishes');
$publishedRow = $queueDb->query("SELECT terminal_id, published_at FROM bus_dispatch_queue WHERE operating_date = '$date' AND bus_id = 'BUS-5001'")->fetch(PDO::FETCH_ASSOC);
check((int)$publishedRow['terminal_id'] === 2 && $publishedRow['published_at'] !== null, 'Publishing preserves the terminal assignment and stamps the row');

$result = $queueService->assignTerminal($date, 'BUS-5001', 1, null, $actor);
check($result['success'] === false && stripos((string)$result['error'], 'published') !== false, 'A published date rejects terminal assignment changes');

$result = $queueService->assignTerminal($tomorrow, 'BUS-5002', 2, null, $actor);
check($result['success'] === true, 'An unpublished date stays editable after another date is published');

/* ------------------ operating-day scoped driver availability ------------------ */

$dayDb = dispatch_test_db();
dispatch_seed($dayDb, 'buses', ['bus_id' => 'BUS-6001', 'plate_number' => 'AAA-6001', 'status' => 'Active', 'operational_status' => 'Available']);
dispatch_seed($dayDb, 'buses', ['bus_id' => 'BUS-6002', 'plate_number' => 'AAA-6002', 'status' => 'Active', 'operational_status' => 'Available']);
dispatch_seed($dayDb, 'drivers', ['driver_id' => 'DRV-601', 'full_name' => 'Stale Bound', 'status' => 'Active', 'availability' => 'Available']);
dispatch_seed($dayDb, 'drivers', ['driver_id' => 'DRV-602', 'full_name' => 'Today Bound', 'status' => 'Active', 'availability' => 'Available']);
dispatch_seed($dayDb, 'drivers', ['driver_id' => 'DRV-603', 'full_name' => 'Unknown Stamp', 'status' => 'Active', 'availability' => 'Available']);
dispatch_seed($dayDb, 'drivers', ['driver_id' => 'DRV-604', 'full_name' => 'Preassigned', 'status' => 'Active', 'availability' => 'Available']);
dispatch_seed($dayDb, 'drivers', ['driver_id' => 'DRV-605', 'full_name' => 'Long Stale', 'status' => 'Active', 'availability' => 'Available']);
dispatch_seed($dayDb, 'driver_bus_assignment', ['driver_id' => 'DRV-601', 'bus_id' => 'BUS-6001', 'created_at' => '2026-09-20 15:41:03']);
dispatch_seed($dayDb, 'driver_bus_assignment', ['driver_id' => 'DRV-602', 'bus_id' => 'BUS-6001', 'created_at' => $date . ' 05:00:00']);
dispatch_seed($dayDb, 'driver_bus_assignment', ['driver_id' => 'DRV-603', 'bus_id' => 'BUS-6002', 'created_at' => null]);
dispatch_seed($dayDb, 'driver_bus_assignment', ['driver_id' => 'DRV-604', 'bus_id' => 'BUS-6001', 'created_at' => $date . ' 06:00:00']);
dispatch_seed($dayDb, 'driver_bus_assignment', ['driver_id' => 'DRV-605', 'bus_id' => 'BUS-6001', 'created_at' => '2026-09-20 15:41:03']);

$dayService = new DispatchQueueService($dayDb);
$dayOptions = [];
foreach ($dayService->queueData($date)['drivers'] as $option) {
    $dayOptions[$option['driver_id']] = $option;
}
check($dayOptions['DRV-601']['occupied'] === false, 'A binding carried over from an earlier day does not hide the driver');
check($dayOptions['DRV-602']['occupied'] === true && $dayOptions['DRV-602']['occupied_bus'] === 'BUS-6001', 'A binding written for the operating date hides the driver');
check(strpos((string)$dayOptions['DRV-602']['occupied_reason'], 'operating date') !== false, 'The unavailable reason explains the day-scoped assignment');
check($dayOptions['DRV-603']['occupied'] === true, 'A binding without a timestamp keeps the strict behavior');

$result = $dayService->assignDriver('BUS-6002', $date, 'DRV-602', true, $actor);
check($result['success'] === false && stripos((string)$result['error'], 'stays unavailable') !== false, 'A driver assigned for the date cannot be moved before the arrival is confirmed');

$result = $dayService->assignDriver('BUS-6002', $date, 'DRV-601', true, $actor);
check($result['success'] === true && $result['moved_from'] === 'BUS-6001', 'A carried-over binding can be reassigned and reports the move');
$reassignedOptions = [];
foreach ($dayService->queueData($date)['drivers'] as $option) {
    $reassignedOptions[$option['driver_id']] = $option;
}
check($reassignedOptions['DRV-601']['occupied'] === true && $reassignedOptions['DRV-601']['occupied_bus'] === 'BUS-6002', 'Reassigning the driver makes them unavailable for the operating date');

dispatch_seed($dayDb, 'bus_trips', ['bus_id' => 'BUS-6002', 'driver_id' => 'DRV-601', 'trip_date' => $date, 'status' => 'Completed', 'trip_number' => 1, 'departure_time' => '06:00:00', 'arrival_time' => '08:00:00']);
$releasedDayOptions = [];
foreach ($dayService->queueData($date)['drivers'] as $option) {
    $releasedDayOptions[$option['driver_id']] = $option;
}
check($releasedDayOptions['DRV-601']['occupied'] === false, 'A completed arrival today releases the driver again');
check($releasedDayOptions['DRV-602']['occupied'] === true, 'Other day-assigned drivers stay unavailable');

$tomorrowOptions = [];
foreach ($dayService->queueData($tomorrow)['drivers'] as $option) {
    $tomorrowOptions[$option['driver_id']] = $option;
}
check($tomorrowOptions['DRV-604']['occupied'] === true, 'An assignment made today hides the driver for a future operating date too');
check($tomorrowOptions['DRV-605']['occupied'] === false, 'A carried-over binding stays available on a future date');

/* ----------- canonical ids, published queues, and arrival terminals ----------- */

$cidDb = dispatch_test_db();
dispatch_seed($cidDb, 'terminals', ['id' => 1, 'terminal_name' => 'Rizal Central Terminal', 'terminal_code' => 'RZL', 'status' => 'Active']);
dispatch_seed($cidDb, 'terminals', ['id' => 2, 'terminal_name' => 'Cabanatuan Central Terminal', 'terminal_code' => 'CBT', 'status' => 'Active']);
dispatch_seed($cidDb, 'buses', ['bus_id' => 'BUS-6101', 'plate_number' => 'AAA-6101', 'status' => 'Active', 'operational_status' => 'Available', 'from_terminal_id' => 1, 'to_terminal_id' => 2]);
dispatch_seed($cidDb, 'buses', ['bus_id' => 'BUS-6102', 'plate_number' => 'AAA-6102', 'status' => 'Active', 'operational_status' => 'Available', 'from_terminal_id' => 2, 'to_terminal_id' => 1]);
dispatch_seed($cidDb, 'drivers', ['driver_id' => 'DRV-611', 'full_name' => 'Casey Driver', 'status' => 'Active', 'availability' => 'Available']);
dispatch_seed($cidDb, 'drivers', ['driver_id' => 'DRV-612', 'full_name' => 'Extra Driver', 'status' => 'Active', 'availability' => 'Available']);
dispatch_seed($cidDb, 'driver_bus_assignment', ['driver_id' => 'DRV-611', 'bus_id' => 'BUS-6101', 'created_at' => '2026-09-20 15:41:03']);

$cidService = new DispatchQueueService($cidDb);
$result = $cidService->saveDraft($date, ['BUS-6101', 'BUS-6102'], $actor);
check($result['success'] === true, 'The published-availability fixture draft saves');

$cidOptions = [];
foreach ($cidService->queueData($date)['drivers'] as $option) {
    $cidOptions[$option['driver_id']] = $option;
}
check($cidOptions['DRV-611']['occupied'] === false, 'A carried-over binding is available while the queue is a draft');

$result = $cidService->assignDriver('BUS-6102', $date, 'extra driver', false, $actor);
check($result['success'] === true, 'An assignment submitted with a display name resolves and succeeds');
check((int)$cidDb->query("SELECT COUNT(*) FROM driver_bus_assignment WHERE driver_id = 'DRV-612' AND bus_id = 'BUS-6102'")->fetchColumn() === 1, 'The assignment record stores the real driver id');
check((int)$cidDb->query("SELECT COUNT(*) FROM driver_bus_assignment WHERE LOWER(driver_id) = 'extra driver'")->fetchColumn() === 0, 'No name-based assignment record is created');

$result = $cidService->publish($date, $actor);
check($result['success'] === true, 'The fixture queue publishes');
$publishedOptions = [];
foreach ($cidService->queueData($date)['drivers'] as $option) {
    $publishedOptions[$option['driver_id']] = $option;
}
check($publishedOptions['DRV-611']['occupied'] === true && $publishedOptions['DRV-611']['occupied_bus'] === 'BUS-6101', 'A published dispatch keeps its assigned driver unavailable');
check(strpos((string)$publishedOptions['DRV-611']['occupied_reason'], 'operating date') !== false, 'The unavailable reason explains the day assignment');
check($publishedOptions['DRV-612']['occupied'] === true, 'A driver assigned today stays unavailable while the queue is published');

/* Trip completed and arrival confirmed: released again at the receiving terminal.
   Publishing scoped the driver to the bus's terminal (Rizal), so the confirmed
   arrival is what moves both the bus and its driver to the receiving terminal. */
dispatch_seed($cidDb, 'bus_trips', ['bus_id' => 'BUS-6101', 'driver_id' => 'DRV-611', 'trip_date' => $date, 'status' => 'Completed', 'trip_number' => 1, 'departure_time' => '06:00:00', 'arrival_time' => '08:00:00', 'from_terminal_id' => 1, 'to_terminal_id' => 2]);
dispatcher_sync_queue_after_arrival($cidDb, 'BUS-6101', $date, 2, 'DRV-611');
$arrivedOptions = [];
foreach ($cidService->queueData($date)['drivers'] as $option) {
    $arrivedOptions[$option['driver_id']] = $option;
}
check($arrivedOptions['DRV-611']['occupied'] === false, 'The completed arrival releases the driver even on a published queue');
check($arrivedOptions['DRV-611']['terminal_id'] === 2 && $arrivedOptions['DRV-611']['terminal'] === 'Cabanatuan Central Terminal', 'The released driver is scoped to the receiving terminal');
check($arrivedOptions['DRV-612']['occupied'] === true, 'Other assigned drivers stay unavailable');

echo "$checks dispatch queue checks passed.\n";

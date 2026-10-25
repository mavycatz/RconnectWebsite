<?php declare(strict_types=1);

/**
 * Official bus reconciliation checks against an in-memory SQLite database.
 *
 * Covers: empty-registry insert of the 18 official buses with NULL unverified
 * fields, in-place updates that preserve id/identifier/unrelated data, plate
 * correction for a number match, conflicts (number held by a different plate,
 * duplicate plates) left untouched, dry runs that write nothing, idempotent
 * re-runs, and rollback on failure.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../migrations/reconcile_official_buses.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function reconcile_test_db(): PDO
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE buses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bus_id TEXT,
        plate_number TEXT,
        bus_type TEXT DEFAULT NULL,
        seats INTEGER DEFAULT NULL,
        standing_capacity INTEGER DEFAULT NULL,
        accessible_features TEXT DEFAULT NULL,
        driver TEXT DEFAULT NULL,
        from_terminal_id INTEGER DEFAULT NULL,
        to_terminal_id INTEGER DEFAULT NULL,
        route_name TEXT DEFAULT NULL,
        terminal TEXT DEFAULT NULL,
        schedule TEXT DEFAULT NULL,
        status TEXT DEFAULT NULL,
        qr_code TEXT DEFAULT NULL,
        qr_data TEXT DEFAULT NULL,
        qr_status TEXT NOT NULL DEFAULT \'Pending\',
        manufacturer TEXT DEFAULT NULL,
        model TEXT DEFAULT NULL,
        year_model TEXT DEFAULT NULL,
        engine_number TEXT DEFAULT NULL,
        chassis_number TEXT DEFAULT NULL,
        fuel_type TEXT DEFAULT NULL,
        transmission_type TEXT DEFAULT NULL,
        air_conditioning TEXT DEFAULT NULL,
        vehicle_ownership TEXT DEFAULT NULL,
        assigned_inspector_id INTEGER DEFAULT NULL,
        operational_status TEXT DEFAULT NULL,
        registration_month TEXT DEFAULT NULL,
        registration_expiry TEXT DEFAULT NULL,
        insurance_expiry TEXT DEFAULT NULL,
        inspection_date TEXT DEFAULT NULL,
        next_inspection_date TEXT DEFAULT NULL,
        created_by TEXT DEFAULT NULL,
        created_at TEXT DEFAULT NULL,
        updated_at TEXT DEFAULT NULL
    )');

    return $db;
}

function reconcile_rows(PDO $db): array
{
    return $db->query('SELECT * FROM buses ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function reconcile_row_by_id(PDO $db, int $id): array
{
    $stmt = $db->prepare('SELECT * FROM buses WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function reconcile_seed(PDO $db, array $values): int
{
    $columns = array_keys($values);
    $sql = 'INSERT INTO buses (' . implode(', ', $columns) . ') VALUES ('
        . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($values));

    return (int)$db->lastInsertId();
}

/* ----------------------- 1. empty registry inserts 18 ----------------------- */

$db = reconcile_test_db();
$report = admin_reconcile_official_buses($db);

check($report['dry_run'] === false, 'A plain run is not a dry run');
check(count($report['inserted']) === 18, 'All 18 official buses are inserted');
check(count($report['updated']) === 0, 'Nothing is updated on an empty registry');
check(count($report['unchanged']) === 0, 'Nothing is unchanged on an empty registry');
check($report['conflicts'] === [], 'An empty registry has no conflicts');
check($report['verified'] === true, 'The run verifies before commit');
check($db->inTransaction() === false, 'The transaction is closed after commit');

$rows = reconcile_rows($db);
check(count($rows) === 18, 'Exactly 18 rows exist after the run');

$expected = [];
foreach (admin_official_buses() as $entry) {
    $expected[$entry['number']] = $entry;
}
$seen = [];
foreach ($rows as $row) {
    $number = admin_normalize_official_bus_number((string)$row['bus_id']);
    check(isset($expected[$number]), 'Every inserted row is an official bus number');
    check(!isset($seen[$number]), 'Official bus numbers are unique');
    $seen[$number] = true;
    check((string)$row['bus_id'] === 'BUS-' . $number, 'Inserted identifiers use the BUS-#### shape');
    check((string)$row['plate_number'] === $expected[$number]['plate'], 'Inserted plate matches the official table exactly');
    check((string)$row['registration_month'] === $expected[$number]['month'], 'Inserted month matches the official table exactly');
    check((string)$row['status'] === 'Active', 'Inserted buses use the standard registration status');
    check((string)$row['qr_code'] === 'Pending', 'Inserted buses start with a pending QR mirror');
    check((string)$row['qr_status'] === 'Pending', 'Inserted buses keep the default pending QR status');
    check($row['bus_type'] === null && $row['seats'] === null && $row['manufacturer'] === null, 'Unverified specifications stay NULL');
    check($row['operational_status'] === null, 'Operational status is never set');
    check($row['registration_expiry'] === null, 'No expiry date is derived from the month');
    check($row['created_by'] === null && $row['updated_at'] === null, 'System inserts leave actor and timestamps empty');
}

/* -------- 2. matched BUS-2001-style row updates in place, re-run no-op -------- */

$db = reconcile_test_db();
$seedId = reconcile_seed($db, [
    'bus_id' => 'BUS-2001',
    'plate_number' => 'NGP-2212',
    'bus_type' => 'Coach',
    'seats' => 49,
    'route_name' => 'Rizal - Cabanatuan',
    'driver' => 'DRV-001',
    'status' => 'Active',
    'operational_status' => 'On Trip',
    'qr_code' => '/uploads/qr/bus2001.png',
    'qr_data' => '{"bus_id":"BUS-2001"}',
    'qr_status' => 'Generated',
    'created_by' => 'admin',
    'created_at' => '2026-01-01 00:00:00',
]);

$report = admin_reconcile_official_buses($db);
check(count($report['inserted']) === 17, 'Only the missing official buses are inserted');
check(count($report['updated']) === 1, 'The matched bus is updated once');
check((int)$report['updated'][0]['id'] === $seedId, 'The existing record is updated in place');
check(array_keys($report['updated'][0]['changes']) === ['registration_month'], 'Only the registration month changes on a matched record');

$row = reconcile_row_by_id($db, $seedId);
check((string)$row['bus_id'] === 'BUS-2001', 'The existing identifier shape is preserved');
check((string)$row['plate_number'] === 'NGP-2212', 'The exact official plate is kept');
check((string)$row['registration_month'] === 'February', 'The official month is recorded');
check((string)$row['status'] === 'Active' && (string)$row['operational_status'] === 'On Trip', 'Lifecycle and operational status are untouched');
check((string)$row['bus_type'] === 'Coach' && (int)$row['seats'] === 49, 'Vehicle specifications are untouched');
check((string)$row['route_name'] === 'Rizal - Cabanatuan' && (string)$row['driver'] === 'DRV-001', 'Assignments are untouched');
check((string)$row['qr_code'] === '/uploads/qr/bus2001.png', 'The QR mirror image is untouched');
check((string)$row['qr_data'] === '{"bus_id":"BUS-2001"}', 'The QR payload is untouched');
check((string)$row['qr_status'] === 'Generated', 'The QR status is untouched');
check((string)$row['created_by'] === 'admin' && (string)$row['created_at'] === '2026-01-01 00:00:00', 'Actor and registration timestamp are untouched');
check($row['updated_at'] !== null, 'A changed record refreshes updated_at');

$rowsBefore = serialize(reconcile_rows($db));
$report = admin_reconcile_official_buses($db);
check(count($report['inserted']) === 0, 'A re-run inserts nothing');
check(count($report['updated']) === 0, 'A re-run updates nothing');
check(count($report['unchanged']) === 18, 'A re-run finds all 18 buses unchanged');
check(serialize(reconcile_rows($db)) === $rowsBefore, 'A re-run changes no stored data, including timestamps');

/* --------- 3. bus-number match with a different plate is corrected --------- */

$db = reconcile_test_db();
$seedId = reconcile_seed($db, ['bus_id' => 'BUS-2417', 'plate_number' => 'OLD-0001', 'status' => 'Active']);
$report = admin_reconcile_official_buses($db);
check(count($report['updated']) === 1 && (int)$report['updated'][0]['id'] === $seedId, 'The number-matched record is updated in place');
check(
    $report['updated'][0]['changes']['plate_number']['from'] === 'OLD-0001'
    && $report['updated'][0]['changes']['plate_number']['to'] === 'CCF-6063',
    'The stale plate is corrected to the official plate'
);
$row = reconcile_row_by_id($db, $seedId);
check((string)$row['bus_id'] === 'BUS-2417', 'The identifier keeps its BUS- prefix shape');
check((string)$row['plate_number'] === 'CCF-6063' && (string)$row['registration_month'] === 'March', 'Plate and month match the official table');

/* ------------- 4. conflicting number-holder is reported untouched ------------- */

$db = reconcile_test_db();
$officialId = reconcile_seed($db, ['bus_id' => 'BUS-2001', 'plate_number' => 'NGP-2212', 'status' => 'Active']);
$conflictId = reconcile_seed($db, ['bus_id' => '2001', 'plate_number' => 'QRP-1235', 'status' => 'Active']);
$report = admin_reconcile_official_buses($db);
check(count($report['conflicts']) === 1, 'The conflicting number-holder is reported once');
check(strpos($report['conflicts'][0], 'QRP-1235') !== false && strpos($report['conflicts'][0], 'left unchanged') !== false, 'The conflict names the record and states it was left unchanged');
$conflict = reconcile_row_by_id($db, $conflictId);
check((string)$conflict['bus_id'] === '2001' && (string)$conflict['plate_number'] === 'QRP-1235', 'The conflicting record is untouched');
check($conflict['registration_month'] === null, 'The conflicting record gains no month');
$official = reconcile_row_by_id($db, $officialId);
check((string)$official['registration_month'] === 'February', 'The official record still gains its month');
check(count(reconcile_rows($db)) === 19, 'No conflicting row is deleted and no duplicate official row is inserted');

/* ------------------ 5. duplicate official plate is reported ------------------ */

$db = reconcile_test_db();
$keepId = reconcile_seed($db, ['bus_id' => 'BUS-2001', 'plate_number' => 'NGP-2212', 'status' => 'Active']);
$dupId = reconcile_seed($db, ['bus_id' => 'BUS-9999', 'plate_number' => 'NGP-2212', 'status' => 'Active']);
$report = admin_reconcile_official_buses($db);
check(count($report['conflicts']) === 1, 'The duplicate plate is reported once');
check(strpos($report['conflicts'][0], 'Duplicate plate NGP-2212') !== false, 'The duplicate plate conflict names the plate');
$dup = reconcile_row_by_id($db, $dupId);
check((string)$dup['bus_id'] === 'BUS-9999' && $dup['registration_month'] === null, 'The duplicate record is untouched');
$keep = reconcile_row_by_id($db, $keepId);
check((string)$keep['registration_month'] === 'February', 'The record also matching the bus number is the one updated');

/* --------------------------- 6. dry run writes nothing --------------------------- */

$db = reconcile_test_db();
$seedId = reconcile_seed($db, ['bus_id' => 'BUS-2001', 'plate_number' => 'NGP-2212', 'status' => 'Active']);
$before = serialize(reconcile_rows($db));
$report = admin_reconcile_official_buses($db, true);
check($report['dry_run'] === true, 'The report marks a dry run');
check(count($report['inserted']) === 17, 'The dry run reports the 17 missing buses');
check(count($report['updated']) === 1, 'The dry run reports the one bus needing its month');
check(serialize(reconcile_rows($db)) === $before, 'The dry run changes no stored data');
check($db->inTransaction() === false, 'The dry run opens no transaction');

$text = admin_reconcile_report_text($report);
check(strpos($text, 'dry run') !== false, 'The report text marks the dry run');
check(strpos($text, 'Summary: 17 inserted, 1 updated, 0 unchanged, 0 conflict(s).') !== false, 'The report summary counts match the plan');

/* --------------------------- 7. forced failure rolls back --------------------------- */

$db = reconcile_test_db();
$db->exec("CREATE TRIGGER abort_official_insert BEFORE INSERT ON buses WHEN NEW.plate_number = 'NID-6560' BEGIN SELECT RAISE(ABORT, 'forced failure'); END;");
$threw = false;
try {
    admin_reconcile_official_buses($db);
} catch (Throwable $e) {
    $threw = true;
}
check($threw === true, 'A write failure surfaces to the caller');
check(count(reconcile_rows($db)) === 0, 'No partial official rows survive a failed run');
check($db->inTransaction() === false, 'The transaction is rolled back and closed');

/* --------------- 8. legacy schema without QR columns still works --------------- */

$legacy = new PDO('sqlite::memory:');
$legacy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$legacy->exec('CREATE TABLE buses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bus_id TEXT NOT NULL UNIQUE,
    plate_number TEXT NOT NULL,
    status TEXT DEFAULT \'Active\',
    registration_month TEXT DEFAULT NULL,
    updated_at TEXT DEFAULT NULL
)');
$report = admin_reconcile_official_buses($legacy);
check(count($report['inserted']) === 18, 'A legacy schema without QR columns still inserts all 18 buses');
check($report['verified'] === true, 'The legacy schema run verifies before commit');
$rows = reconcile_rows($legacy);
check(count($rows) === 18, 'The legacy schema gains exactly 18 rows');
check((string)$rows[0]['bus_id'] !== '' && (string)$rows[0]['registration_month'] !== '', 'Legacy inserts carry identifier and month');
check((string)$rows[0]['status'] === 'Active', 'Legacy inserts keep the standard registration status');

/* ------------------------------ 9. report text ------------------------------ */

$db = reconcile_test_db();
$report = admin_reconcile_official_buses($db);
$text = admin_reconcile_report_text($report);
check(strpos($text, 'Summary: 18 inserted, 0 updated, 0 unchanged, 0 conflict(s).') !== false, 'The committed report summarises the run');
check(strpos($text, 'Verification: all 18 official bus number/plate pairs present (committed)') !== false, 'The committed report confirms verification');
check(strpos($text, 'Unknown fields (not verified by the official registration table):') !== false, 'The report lists the unknown fields');
check(strpos($text, '+ BUS-2001  NGP-2212  February') !== false, 'The report lists inserted buses with their exact values');

echo "$checks official bus reconciliation checks passed.\n";

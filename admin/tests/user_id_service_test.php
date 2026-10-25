<?php declare(strict_types=1);

/**
 * User ID generation, sequencing, uniqueness and validation checks.
 *
 * Runs entirely on in-memory SQLite: no MySQL server, session or HTTP request
 * is required. The same SQL is used against MySQL in production.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../services/RoleManagement/UserIdService.php';
require_once __DIR__ . '/../services/Validation/UserValidation.php';

use Admin\Services\RoleManagement\UserIdService;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function makeDb(): PDO
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE drivers (id INTEGER PRIMARY KEY AUTOINCREMENT, driver_id TEXT, email TEXT)');
    $db->exec('CREATE TABLE inspector_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, inspector_id TEXT)');
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, employee_id TEXT, username TEXT, email TEXT)');
    $db->exec('CREATE TABLE buses (id INTEGER PRIMARY KEY AUTOINCREMENT, bus_id TEXT, plate_number TEXT)');

    return $db;
}

/* ------------------------- scope + format ------------------------- */

check(UserIdService::scopeForRole('driver') === 'driver', 'Driver maps to the driver scope');
check(UserIdService::scopeForRole('inspector') === 'inspector', 'Inspector maps to the inspector scope');
check(UserIdService::scopeForRole('dispatcher') === 'dispatcher', 'Dispatcher has its own scope');
check(UserIdService::scopeForRole('finance_officer') === 'finance', 'Finance officer maps to finance');
check(UserIdService::scopeForRole('finance_manager') === 'finance', 'Finance manager maps to finance');
check(UserIdService::scopeForRole('admin') === 'admin', 'Admin maps to the admin scope');
check(UserIdService::scopeForRole('super_admin') === 'super_admin', 'Super Admin has its own scope');
check(UserIdService::scopeForRole('visitor') === '', 'Unknown roles have no scope');

check(UserIdService::format('driver', 1) === 'DRV-001', 'DRV format starts at three digits');
check(UserIdService::format('driver', 4) === 'DRV-004', 'Sequential driver ID');
check(UserIdService::format('inspector', 12) === 'INS-012', 'INS format');
check(UserIdService::format('dispatcher', 2) === 'DIS-002', 'DIS format');
check(UserIdService::format('finance', 3) === 'FIN-003', 'FIN format');
check(UserIdService::format('admin', 1) === 'ADM-001', 'ADM format');
check(UserIdService::format('super_admin', 1) === 'SUP-001', 'SUP format');
check(UserIdService::format('bus', 1) === 'BUS-001', 'Bus IDs use the BUS prefix');
check(UserIdService::format('bus', 1000) === 'BUS-1000', 'Bus IDs expand beyond 999');
check(UserIdService::format('driver', 1000) === 'DRV-1000', 'IDs expand beyond 999');

/* ------------------------- preview + allocation ------------------------- */

$db = makeDb();
UserIdService::ensureSchema($db);
$service = new UserIdService($db);

check($service->preview('driver') === 'DRV-001', 'First preview is DRV-001');
check($service->preview('driver') === 'DRV-001', 'Preview does not reserve the number');
check($service->allocate('driver') === 'DRV-001', 'First allocation is DRV-001');
check($service->preview('driver') === 'DRV-002', 'Preview moves after allocation');
check($service->allocate('driver') === 'DRV-002', 'Second allocation is DRV-002');

check($service->allocate('inspector') === 'INS-001', 'Inspector numbering is independent');
check($service->allocate('dispatcher') === 'DIS-001', 'Dispatcher numbering is independent');
check($service->allocate('finance') === 'FIN-001', 'Finance numbering is independent');
check($service->allocate('admin') === 'ADM-001', 'Admin numbering is independent');
check($service->allocate('super_admin') === 'SUP-001', 'Super Admin numbering is independent');
check($service->allocate('finance') === 'FIN-002', 'Finance keeps its own sequence');
check($service->allocate('admin') === 'ADM-002', 'Admin keeps its own sequence');

/* --------------- continuing after existing/legacy records --------------- */

$db->exec("INSERT INTO drivers (driver_id, email) VALUES ('DRV-007', 'a@b.com')");
check($service->preview('driver') === 'DRV-008', 'Preview continues after existing records');
check($service->allocate('driver') === 'DRV-008', 'Allocation continues after existing records');
check($service->preview('driver') === 'DRV-009', 'Sequence stays ahead afterwards');

$db->exec("INSERT INTO users (employee_id, username) VALUES ('FIN-050', 'fin')");
check((new UserIdService($db))->preview('finance') === 'FIN-051', 'Legacy four-digit IDs are parsed by suffix');

/* ----------------------------- bus scope ----------------------------- */

check($service->allocate('bus') === 'BUS-001', 'Bus numbering starts at BUS-001');
check($service->allocate('bus') === 'BUS-002', 'Bus numbering increments sequentially');
check($service->preview('bus') === 'BUS-003', 'Bus preview follows allocation');

$db->exec("INSERT INTO buses (bus_id, plate_number) VALUES ('BUS-007', 'ABC 1234')");
check($service->preview('bus') === 'BUS-008', 'Bus preview continues after existing records');
check($service->allocate('bus') === 'BUS-008', 'Bus allocation continues after existing records');

// Archived buses keep their row and ID, so their numbers stay reserved.
$db->exec("INSERT INTO buses (bus_id, plate_number) VALUES ('BUS-010', 'XYZ 9999')");
check((new UserIdService($db))->preview('bus') === 'BUS-011', 'Archived bus IDs are never reused');

$dbBusRace = makeDb();
UserIdService::ensureSchema($dbBusRace);
$busA = new UserIdService($dbBusRace);
$busB = new UserIdService($dbBusRace);
check($busA->preview('bus') === 'BUS-001' && $busB->preview('bus') === 'BUS-001', 'Two admins preview the same bus ID');
$firstBus = $busA->allocateAvailable('bus');
$dbBusRace->exec("INSERT INTO buses (bus_id, plate_number) VALUES ('" . $firstBus . "', 'AAA 111')");
check($busB->allocateAvailable('bus') === 'BUS-002', 'Simultaneous bus registrations never share an ID');
check($busA->idExists('bus', 'BUS-001'), 'Bus idExists finds a stored ID');
check(!$busA->idExists('bus', 'BUS-099'), 'Bus idExists rejects a free ID');

/* ------------- collision: previewed ID taken before save -------------- */

$db2 = makeDb();
UserIdService::ensureSchema($db2);
$service2 = new UserIdService($db2);

// Another request "already received" DRV-001.
$db2->exec("INSERT INTO drivers (driver_id, email) VALUES ('DRV-001', 'taken@b.com')");
$allocated = $service2->allocateAvailable('driver');
check($allocated !== 'DRV-001', 'A taken previewed ID is never issued');
check($allocated === 'DRV-002', 'Allocation moves to the next free number');
check($service2->idExists('driver', 'DRV-001'), 'idExists finds a stored ID');
check(!$service2->idExists('driver', 'DRV-999'), 'idExists rejects a free ID');

/* ------------- two admins preview the same ID, then both save -------------- */

$dbRace = makeDb();
UserIdService::ensureSchema($dbRace);
$serviceA = new UserIdService($dbRace);
$serviceB = new UserIdService($dbRace);

check($serviceA->preview('driver') === 'DRV-001', 'First admin previews DRV-001');
check($serviceB->preview('driver') === 'DRV-001', 'Second admin previews the same DRV-001');

$firstSave = $serviceA->allocateAvailable('driver');
$dbRace->exec("INSERT INTO drivers (driver_id, email) VALUES ('" . $firstSave . "', 'first@b.com')");
$secondSave = $serviceB->allocateAvailable('driver');

check($firstSave === 'DRV-001', 'The first save receives the previewed ID');
check($secondSave === 'DRV-002', 'The second save automatically moves to the next ID');
check($firstSave !== $secondSave, 'Simultaneous creations never share an ID');

/* ------------- transaction rollback reuses the number ----------------- */

$db3 = makeDb();
UserIdService::ensureSchema($db3);
$service3 = new UserIdService($db3);

$db3->beginTransaction();
$during = $service3->allocate('inspector');
check($during === 'INS-001', 'Allocation joins an open transaction');
$db3->rollBack();
check($service3->allocate('inspector') === 'INS-001', 'A rolled-back registration reuses its number');

/* ------------------------- unique constraint ------------------------- */

$db4 = makeDb();
$report = UserIdService::ensureUniqueIndexes($db4);
check(($report['drivers.driver_id'] ?? '') === 'ok', 'Unique index is created for driver IDs');
check(($report['inspector_accounts.inspector_id'] ?? '') === 'ok', 'Unique index is created for inspector IDs');
check(($report['users.employee_id'] ?? '') === 'ok', 'Unique index is created for employee IDs');

$db4->exec("INSERT INTO drivers (driver_id, email) VALUES ('DRV-001', 'a@b.com')");
$duplicateBlocked = false;
try {
    $db4->exec("INSERT INTO drivers (driver_id, email) VALUES ('DRV-001', 'c@d.com')");
} catch (PDOException $e) {
    $duplicateBlocked = true;
}
check($duplicateBlocked, 'The database now rejects duplicate IDs');

$db5 = makeDb();
$db5->exec("INSERT INTO users (employee_id, username) VALUES ('FIN-001', 'a')");
$db5->exec("INSERT INTO users (employee_id, username) VALUES ('FIN-001', 'b')");
$report = UserIdService::ensureUniqueIndexes($db5);
check(($report['users.employee_id'] ?? '') === 'blocked-duplicates', 'Duplicates are reported, never silently changed');

$audit = (new UserIdService($db5))->audit();
check(in_array('FIN-001', $audit['duplicates']['users.employee_id'] ?? [], true), 'Audit lists the duplicate values');
check(isset($audit['next']['driver']), 'Audit lists the next ID per scope');

/* ------------------------- shared validation rules ------------------------- */

check(uv_name('12345', 'Full name') !== null, 'Numbers-only names are rejected');
check(uv_name('---', 'Full name') !== null, 'Symbol-only names are rejected');
check(uv_name('', 'Full name') !== null, 'Empty names are rejected');
check(uv_name('   ', 'Full name') !== null, 'Whitespace-only names are rejected');
check(uv_name("María O'Brien-Dela Cruz", 'Full name') === null, 'Legitimate Unicode names are accepted');
check(uv_name('  Juan Dela Cruz  ', 'Full name') === null, 'Surrounding spaces are trimmed, not rejected');
check(uv_name('Juan D. Cruz', 'Full name') === null, 'Middle initials with periods are accepted');
check(uv_name('Juan Dela Cruz Jr.', 'Full name') === null, 'Suffixes with periods are accepted');
check(uv_name('Juan Dela Cruz, Jr.', 'Full name') === null, 'Comma-separated suffixes are accepted');
check(uv_name('Juan Dela Cruz III', 'Full name') === null, 'Roman-numeral suffixes are accepted');
check(uv_name('Juan.. Cruz', 'Full name') !== null, 'Repeated punctuation is rejected');
check(uv_name('Juan .Cruz', 'Full name') !== null, 'Punctuation floating away from a letter is rejected');
check(uv_name('Juan--Cruz', 'Full name') !== null, 'Repeated hyphens are rejected');
check(uv_name('Juan Cruz-', 'Full name') !== null, 'A trailing hyphen is rejected');
check(uv_name('Juan Cruz,', 'Full name') !== null, 'A trailing comma is rejected');

check(uv_email('not-an-email') !== null, 'Invalid email is rejected');
check(uv_email('juan@example.com') === null, 'Valid email is accepted');

check(uv_phone('09171234567') === null, '09XXXXXXXXX is accepted');
check(uv_phone('+639171234567') === null, '+639XXXXXXXXX is accepted');
check(uv_phone('639171234567') === null, '63XXXXXXXXXX is accepted');
check(uv_phone('12345') !== null, 'Invalid contact numbers are rejected');
check(uv_phone('0917123456') !== null, 'Short contact numbers are rejected');

check(uv_username('ab') !== null, 'Too-short usernames are rejected');
check(uv_username('bad user!') !== null, 'Invalid username characters are rejected');
check(uv_username('juan_01') === null, 'Valid usernames are accepted');

check(uv_password('short', 'Password', true) !== null, 'Short passwords are rejected');
check(uv_password('alllowercase', 'Password', true) !== null, 'Password without a number is rejected');
check(uv_password('passw0rd', 'Password', true) === null, 'Strong passwords are accepted');
check(uv_password('', 'New password', false) === null, 'Optional password may stay blank on edit');

/* ------------------- structured form validation ------------------- */

check(uv_date_not_future('2000-01-01', 'Date of birth') === null, 'Past dates are accepted');
check(uv_date_not_future('2999-01-01', 'Date of birth') !== null, 'Future dates are rejected');
check(uv_date_not_future('not-a-date', 'Date of birth') !== null, 'Invalid dates are rejected');
check(uv_date_not_future('', 'Date hired') === null, 'Optional dates may be empty');

$config = uv_config();
check(uv_enum('Regular', $config['employment_types'], 'employment type') === null, 'Valid employment types pass');
check(uv_enum('Permanent', $config['employment_types'], 'employment type') !== null, 'Unknown employment types are rejected');
check(uv_enum('Available', $config['availability'], 'availability') === null, 'Valid availability values pass');
check(uv_enum('Night', $config['work_shifts'], 'work shift') !== null, 'Non-listed work shifts are rejected');
check(uv_enum('Cashier', $config['finance_positions'], 'finance position') === null, 'Valid finance positions pass');
check(uv_enum('Space Admiral', $config['admin_positions'], 'administrator position') !== null, 'Unknown admin positions are rejected');

check(uv_supervisor('') === null, 'Empty supervisor is allowed');
check(uv_supervisor('abc') !== null, 'Non-numeric supervisor references are rejected');

check(uv_maxlen(str_repeat('A', 501), 500, 'Address') !== null, 'Overlong addresses are rejected');

echo "$checks user ID and validation checks passed.\n";

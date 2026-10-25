<?php declare(strict_types=1);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require_once __DIR__ . '/../services/RoleManagement/ArchiveLifecycle.php';
use Admin\Services\RoleManagement\ArchiveLifecycle;
use Admin\Services\RoleManagement\ArchiveMetadata;
class ArchiveFixturePDO extends PDO {
    private function translate(string $sql): string {
        if (preg_match('/^SHOW TABLES LIKE (.+)$/i', trim($sql), $m)) { return "SELECT name FROM sqlite_master WHERE type='table' AND name=" . $m[1]; }
        if (preg_match('/^SHOW COLUMNS FROM `?([a-z_]+)`?$/i', trim($sql), $m)) { return "SELECT name AS Field FROM pragma_table_info('" . $m[1] . "')"; }
        return str_replace(['CONVERT(', ' USING utf8mb4) COLLATE utf8mb4_general_ci'], ['(', ')'], $sql);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false {
        return $fetchMode === null ? parent::query($this->translate($query)) : parent::query($this->translate($query), $fetchMode, ...$args);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { return parent::prepare($this->translate($query), $options); }
}
$db = new ArchiveFixturePDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
@$db->sqliteCreateFunction('CONCAT', static fn(...$values) => implode('', $values));
$db->exec("CREATE TABLE audit_trail(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INT,username TEXT,user_role TEXT,module TEXT,action TEXT,
    record_id TEXT,description TEXT,previous_values TEXT,new_values TEXT,ip_address TEXT,user_agent TEXT,status TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE users(id INT PRIMARY KEY,role TEXT,status TEXT,is_active INT,password TEXT);
    INSERT INTO users VALUES(1,'admin','inactive',0,'original'),(2,'finance_officer','active',1,'original'),
    (5,'finance_manager','active',1,'original'),(6,'super_admin','active',1,'original'),(9,'super_admin','active',1,'actor');
    CREATE TABLE drivers(id INT PRIMARY KEY,status TEXT,driver_id TEXT); INSERT INTO drivers VALUES(1,'Active','DRV-1');
    CREATE TABLE inspector_accounts(account_id INT PRIMARY KEY,status TEXT,password TEXT,role TEXT); INSERT INTO inspector_accounts VALUES(7,'active','original','inspector'),(8,'active','original','dispatcher');
    CREATE TABLE inspectors(inspector_pk INT PRIMARY KEY,account_id INT); INSERT INTO inspectors VALUES(1,7),(2,8);
    CREATE TABLE buses(id INT PRIMARY KEY,bus_id TEXT,status TEXT,operational_status TEXT,qr_token TEXT); INSERT INTO buses VALUES(1,'BUS-1','Active','Maintenance','original-qr');
    CREATE TABLE driver_bus_assignment(id INT,bus_id TEXT,driver_id TEXT); INSERT INTO driver_bus_assignment VALUES(1,'BUS-1','DRV-1');");
$actor = ['id' => 9, 'username' => 'Responsible Admin', 'role' => 'super_admin'];
$checks = 0;
function check(bool $ok, string $message): void { global $checks; if (!$ok) { throw new RuntimeException($message); } $checks++; }
foreach (['admin', 'finance_officer', 'finance_manager', 'super_admin', 'driver', 'inspector', 'dispatcher', 'bus'] as $role) {
    $ref = match ($role) { 'finance_officer', 'dispatcher' => '2', 'finance_manager' => '5', 'super_admin' => '6', default => '1' };
    $result = ArchiveLifecycle::transition($db, $actor, $role, $ref, 'archive', 'Required archive reason');
    check($result['success'], $role . ' archives through existing source');
    $source = ArchiveLifecycle::source($role);
    if ($source === 'super_admin') { $source = 'admin'; }
    $details = ArchiveMetadata::details($db, $source, $ref);
    check($details['reason'] === 'Required archive reason' && $details['archived_by'] === 'Responsible Admin', $role . ' archive metadata');
    check($details['archived_at'] !== null, $role . ' actual archive date');
    check(ArchiveLifecycle::transition($db, $actor, $role, $ref, 'archive', 'Same record retry')['unchanged'], $role . ' archive retry creates no duplicate');
    check(ArchiveLifecycle::transition($db, $actor, $role, $ref, 'restore', 'Required restore reason')['success'], $role . ' restores original');
    check(ArchiveMetadata::details($db, $source, $ref) === null, $role . ' latest restore clears current archive membership');
}
check($db->query('SELECT status FROM users WHERE id=1')->fetchColumn() === 'inactive', 'Previously inactive admin is not silently activated');
check((int) $db->query('SELECT is_active FROM users WHERE id=1')->fetchColumn() === 0, 'Prior activation flag preserved');
check($db->query('SELECT operational_status FROM buses WHERE id=1')->fetchColumn() === 'Maintenance', 'Bus operational state preserved');
check($db->query('SELECT qr_token FROM buses WHERE id=1')->fetchColumn() === 'original-qr', 'Bus QR preserved');
check($db->query('SELECT password FROM inspector_accounts WHERE account_id=7')->fetchColumn() === 'original', 'Account password preserved');
check((int) $db->query('SELECT COUNT(*) FROM driver_bus_assignment')->fetchColumn() === 1, 'Assignments preserved');
try { ArchiveLifecycle::transition($db, $actor, 'driver', '1', 'archive', '  '); throw new LogicException('Expected reason rejection'); }
catch (InvalidArgumentException $e) { check(true, 'Blank reason rejected'); }
try { ArchiveLifecycle::transition($db, ['id'=>8,'username'=>'Admin','role'=>'admin'], 'admin', '1', 'archive', 'Valid reason'); throw new LogicException('Expected role rejection'); }
catch (RuntimeException $e) { check($db->query('SELECT status FROM users WHERE id=1')->fetchColumn() === 'inactive', 'Unauthorized actor cannot mutate'); }
$db->exec("CREATE TRIGGER deny_audit BEFORE INSERT ON audit_trail BEGIN SELECT RAISE(ABORT,'audit fixture failure'); END");
try { ArchiveLifecycle::transition($db, $actor, 'bus', '1', 'archive', 'Required reason'); throw new LogicException('Expected audit failure'); }
catch (PDOException $e) { check($db->query('SELECT status FROM buses WHERE id=1')->fetchColumn() === 'Active', 'Audit failure rolls back archive'); }
$db->exec('DROP TRIGGER deny_audit');
check((int) $db->query('SELECT COUNT(*) FROM audit_trail')->fetchColumn() === 16, 'Only genuine transitions append history');
check(ArchiveMetadata::details($db, 'driver', '99') === null, 'Unknown metadata is unavailable, not invented');
$db->exec("INSERT INTO users VALUES(3,'admin','inactive',0,'legacy'),(4,'admin','inactive',0,'ordinary');
    INSERT INTO audit_trail(module,action,record_id,new_values,status,username) VALUES('Admin','Archive','3','{\"reason\":\"Legacy archive reason\"}','Success','Legacy Admin');");
require_once __DIR__ . '/../migrations/reconcile_legacy_archives.php';
check(admin_reconcile_legacy_archives($db) === 1, 'Only evidenced inactive legacy archive reconciled');
check($db->query('SELECT status FROM users WHERE id=4')->fetchColumn() === 'inactive', 'Ordinary inactive record remains inactive');
check(admin_reconcile_legacy_archives($db) === 0, 'Legacy reconciliation is idempotent');
check(ArchiveLifecycle::transition($db, $actor, 'admin', '4', 'restore', 'Explicitly reactivate existing inactive user')['success'], 'Existing explicit Reactivate action preserved');
check($db->query('SELECT status FROM users WHERE id=4')->fetchColumn() === 'active', 'Inactive user activates only on an explicit approved action');
check(ArchiveMetadata::details($db, 'admin', '3')['reason'] === 'Legacy archive reason', 'Exact legacy event returns its reason');
require_once __DIR__ . '/../models/RoleManagement/RoleDirectoryModel.php';
require_once __DIR__ . '/../models/Bus/BusDirectoryModel.php';
$db->exec("ALTER TABLE users ADD COLUMN full_name TEXT; UPDATE users SET full_name='Existing User';
    ALTER TABLE drivers ADD COLUMN full_name TEXT; UPDATE drivers SET full_name='Existing Driver';
    ALTER TABLE inspectors ADD COLUMN full_name TEXT; UPDATE inspectors SET full_name='Existing Inspector'");
(new ReflectionProperty(App\Core\Database::class, 'instance'))->setValue(null, $db);
ArchiveLifecycle::transition($db, $actor, 'driver', '1', 'archive', 'Listing verification reason');
ArchiveLifecycle::transition($db, $actor, 'bus', '1', 'archive', 'Listing verification reason');
$roles = new Admin\Models\RoleManagement\RoleDirectoryModel();
$activeRoles = $roles->query(['role' => 'all']);
check($activeRoles['errors'] === [], 'Actual normalized role sources load without errors: ' . json_encode($activeRoles['errors']));
check(count(array_filter($activeRoles['rows'], static fn(array $r): bool => $r['status'] === 'archived')) === 0, 'Default role listing excludes archived users');
$archivedRoles = $roles->query(['role' => 'all', 'status' => 'archived']);
check(count($archivedRoles['rows']) === 2, 'Dedicated archive listing returns only evidenced admin and driver');
check(array_sum($archivedRoles['counts']) === 2, 'Role counts agree with archive listing');
check($roles->query(['role'=>'driver','search'=>'Existing Driver'])['total'] === 0, 'Active search excludes archived driver');
check($roles->query(['role'=>'driver','status'=>'archived','search'=>'Existing Driver'])['total'] === 1, 'Explicit role/search archive mode finds correct driver');
check(count(array_filter($archivedRoles['rows'], static fn(array $r): bool => $r['archive_reason'] === 'Listing verification reason')) === 1, 'Archive directory exposes structured reason');
$buses = new Admin\Models\Bus\BusDirectoryModel();
check($buses->query([])['total'] === 0, 'Default bus listing excludes archived buses');
check($buses->query(['search'=>'BUS-1'])['total'] === 0, 'Default bus search excludes archived bus');
$archivedBuses = $buses->query(['status' => 'archived']);
check($archivedBuses['total'] === 1 && $archivedBuses['rows'][0]['archive_reason'] === 'Listing verification reason', 'Archived Buses retrieves reason and original bus');
ArchiveLifecycle::transition($db, $actor, 'driver', '1', 'restore', 'Restore listing verification');
ArchiveLifecycle::transition($db, $actor, 'bus', '1', 'restore', 'Restore listing verification');
check($roles->query(['role'=>'driver'])['total'] === 1, 'Restored driver returns to its original management page');
check($buses->query([])['total'] === 1, 'Restored bus returns to active page');
try { ArchiveLifecycle::transition($db, $actor, 'super_admin', '9', 'archive', 'Attempt self archive'); throw new LogicException('Expected self restriction'); }
catch (RuntimeException $e) { check(str_contains($e->getMessage(), 'own account'), 'Self-archive is rejected'); }
$db->exec("UPDATE users SET status='inactive' WHERE id=9");
try { ArchiveLifecycle::transition($db, $actor, 'super_admin', '6', 'archive', 'Attempt last admin archive'); throw new LogicException('Expected last admin restriction'); }
catch (RuntimeException $e) { check(str_contains($e->getMessage(), 'last active Super Admin'), 'Last active Super Admin protected'); }
$db->exec("UPDATE users SET status='active' WHERE id=9");
$db->beginTransaction();
$db->exec("INSERT INTO buses VALUES(2,'LEGACY-BUS','Archived','Out of Service','legacy-qr')");
check(ArchiveLifecycle::transition($db, $actor, 'bus', '2', 'restore', 'Restore legacy bus')['success'], 'Legacy archived bus restores without prior metadata');
check($db->query('SELECT operational_status FROM buses WHERE id=2')->fetchColumn() === 'Out of Service', 'Legacy restore retains operational restrictions');
check(ArchiveLifecycle::transition($db, $actor, 'bus', '2', 'restore', 'Repeat legacy restore')['unchanged'], 'Repeated restore does not duplicate record');
$db->rollBack();
$db->exec("CREATE TRIGGER deny_restore_audit BEFORE INSERT ON audit_trail WHEN NEW.action='Restore' BEGIN SELECT RAISE(ABORT,'restore audit failure'); END");
ArchiveLifecycle::transition($db, $actor, 'bus', '1', 'archive', 'Test restore rollback');
try { ArchiveLifecycle::transition($db, $actor, 'bus', '1', 'restore', 'Test restore rollback'); throw new LogicException('Expected restore rollback'); }
catch (PDOException $e) { check($db->query('SELECT status FROM buses WHERE id=1')->fetchColumn() === 'Archived', 'Audit failure rolls back restore'); }
$db->exec('DROP TRIGGER deny_restore_audit');
ArchiveLifecycle::transition($db, $actor, 'bus', '1', 'restore', 'Complete restore verification');
echo "$checks archive lifecycle checks passed.\n";

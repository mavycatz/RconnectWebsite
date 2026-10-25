<?php declare(strict_types=1);

/**
 * Bus restore-details fixture.
 *
 * Verifies the guarded restore transition applies only the supplied detail
 * fields, preserves everything else (driver, route, terminal ids, schedule,
 * QR, operational status), keeps a prior Inactive lifecycle status, and
 * commits nothing when validation, eligibility, or the audit write fails.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require_once __DIR__ . '/../services/RoleManagement/ArchiveLifecycle.php';

use Admin\Services\RoleManagement\ArchiveLifecycle;

class BusRestoreFixturePDO extends PDO
{
    private function translate(string $sql): string
    {
        if (preg_match('/^SHOW TABLES LIKE (.+)$/i', trim($sql), $m)) {
            return "SELECT name FROM sqlite_master WHERE type='table' AND name=" . $m[1];
        }
        if (preg_match('/^SHOW COLUMNS FROM `?([a-z_]+)`?$/i', trim($sql), $m)) {
            return "SELECT name AS Field FROM pragma_table_info('" . $m[1] . "')";
        }

        return str_replace(['CONVERT(', ' USING utf8mb4) COLLATE utf8mb4_general_ci'], ['(', ')'], $sql);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false
    {
        return $fetchMode === null
            ? parent::query($this->translate($query))
            : parent::query($this->translate($query), $fetchMode, ...$args);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare($this->translate($query), $options);
    }
}

$db = new BusRestoreFixturePDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
@$db->sqliteCreateFunction('CONCAT', static fn (...$values) => implode('', $values));
@$db->sqliteCreateFunction('NOW', static fn () => date('Y-m-d H:i:s'));

$db->exec("
    CREATE TABLE audit_trail(
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT, username TEXT, user_role TEXT,
        module TEXT, action TEXT, record_id TEXT, description TEXT, previous_values TEXT,
        new_values TEXT, ip_address TEXT, user_agent TEXT, status TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE buses(
        id INTEGER PRIMARY KEY, bus_id TEXT, plate_number TEXT, bus_type TEXT,
        seats INT, standing_capacity INT, unit_number TEXT, manufacturer TEXT, model TEXT,
        year_model TEXT, engine_number TEXT, chassis_number TEXT, fuel_type TEXT,
        transmission_type TEXT, air_conditioning TEXT, vehicle_ownership TEXT,
        assigned_inspector_id INT, driver TEXT, route_name TEXT, terminal TEXT,
        from_terminal_id INT, to_terminal_id INT, schedule TEXT, accessible_features TEXT,
        registration_month TEXT, registration_expiry TEXT, insurance_expiry TEXT,
        inspection_date TEXT, next_inspection_date TEXT, status TEXT, operational_status TEXT,
        qr_code TEXT, updated_at TEXT
    );
    CREATE TABLE drivers(id INTEGER PRIMARY KEY, driver_id TEXT, full_name TEXT, status TEXT, assigned_buses TEXT);
    CREATE TABLE driver_bus_assignment(id INTEGER PRIMARY KEY AUTOINCREMENT, bus_id TEXT, driver_id TEXT);
    CREATE TABLE terminals(id INTEGER PRIMARY KEY, terminal_name TEXT, status TEXT);

    INSERT INTO drivers VALUES (1, 'DRV-1', 'Juan Dela Cruz', 'Active', 'BUS-1'), (2, 'DRV-2', 'Ana Reyes', 'Active', NULL);
    INSERT INTO driver_bus_assignment(bus_id, driver_id) VALUES ('BUS-1', 'DRV-1');
    INSERT INTO terminals VALUES (1, 'Cubao Terminal', 'Active'), (2, 'Baguio Terminal', 'Active');

    INSERT INTO buses (
        id, bus_id, plate_number, bus_type, seats, standing_capacity, unit_number, manufacturer,
        assigned_inspector_id, driver, route_name, terminal, from_terminal_id, to_terminal_id,
        schedule, accessible_features, registration_month, status, operational_status, qr_code, updated_at
    ) VALUES (
        1, 'BUS-1', 'OLD-111', 'HINO Bus', 24, 11, '1101', 'Hino',
        NULL, 'DRV-1', 'Cubao - Baguio', 'Cubao Terminal', 1, 2,
        '06:00 AM - 06:00 PM', 'Priority seats', 'February', 'Active', 'On Trip', 'qr-credential-1', '2026-01-01 00:00:00'
    ), (
        2, 'BUS-2', 'OLD-222', 'HINO Bus', 24, 11, NULL, NULL,
        NULL, NULL, 'Cubao - Baguio', 'Cubao Terminal', 1, 2,
        '06:00 AM - 06:00 PM', NULL, NULL, 'Inactive', NULL, 'qr-credential-2', '2026-01-01 00:00:00'
    ), (
        3, 'BUS-3', 'OLD-333', 'HINO Bus', 24, 11, NULL, NULL,
        NULL, NULL, 'Cubao - Baguio', 'Cubao Terminal', 1, 2,
        '06:00 AM - 06:00 PM', NULL, NULL, 'Active', NULL, 'qr-credential-3', '2026-01-01 00:00:00'
    );
");

$actor = ['id' => 9, 'username' => 'Responsible Admin', 'role' => 'super_admin'];

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function busRow(PDO $db, int $id): array
{
    $stmt = $db->prepare('SELECT * FROM buses WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* ------------------- supplied details stored, absent preserved ------------------- */

ArchiveLifecycle::transition($db, $actor, 'bus', '1', 'archive', 'Retire unit for detail restore');
$checkArchived = busRow($db, 1);
check($checkArchived['status'] === 'Archived', 'Bus archived before the detailed restore');

$restorePayload = [
    'bus_ref' => '1',
    'reason' => 'Restore with corrected details',
    'security_token' => 'ignored-by-transition',
    'plate_number' => 'NEW-222',
    'unit_number' => '2201',
    'bus_type' => 'HINO Bus',
    'seats' => '24',
    'standing_capacity' => '11',
    'manufacturer' => 'Hino',
    'schedule' => '07:00 AM - 07:00 PM',
    'driver' => 'DRV-1',
];
$result = ArchiveLifecycle::transition($db, $actor, 'bus', '1', 'restore', $restorePayload['reason'], $restorePayload);
check(!empty($result['success']), 'Detailed restore succeeds');

$restored = busRow($db, 1);
check($restored['status'] === 'Active', 'Bus returns to its recorded prior lifecycle status');
check($restored['plate_number'] === 'NEW-222', 'Supplied plate number is stored');
check($restored['unit_number'] === '2201', 'Supplied unit number is stored');
check($restored['manufacturer'] === 'Hino', 'Supplied manufacturer is stored');
check($restored['schedule'] === '07:00 AM - 07:00 PM', 'Supplied schedule is stored');
check((int)$restored['seats'] === 24 && (int)$restored['standing_capacity'] === 11, 'Supplied capacities are stored');
check($restored['route_name'] === 'Cubao - Baguio', 'Absent route is preserved');
check((int)$restored['from_terminal_id'] === 1 && (int)$restored['to_terminal_id'] === 2, 'Absent terminal ids are preserved');
check($restored['accessible_features'] === 'Priority seats', 'Absent accessible features are preserved');
check($restored['operational_status'] === 'On Trip', 'Operational status is untouched by the restore');
check($restored['qr_code'] === 'qr-credential-1', 'QR credential is preserved');
check(($restored['registration_month'] ?? 'February') === 'February', 'Absent registration month is preserved');
check($restored['driver'] === 'DRV-1', 'Unchanged driver stays assigned');
$assignment = $db->query("SELECT driver_id FROM driver_bus_assignment WHERE bus_id = 'BUS-1'")->fetchColumn();
check($assignment === 'DRV-1', 'Unchanged driver assignment row stays intact');
check(str_contains((string)$restored['updated_at'], date('Y-m-d')), 'updated_at is refreshed for the detail write');

/* ------------------------- prior Inactive status retained ------------------------- */

ArchiveLifecycle::transition($db, $actor, 'bus', '2', 'archive', 'Retire inactive unit');
$result = ArchiveLifecycle::transition($db, $actor, 'bus', '2', 'restore', 'Restore inactive unit', [
    'bus_ref' => '2',
    'reason' => 'Restore inactive unit',
    'plate_number' => 'INACT-2',
    'driver' => 'DRV-2',
]);
$busTwo = busRow($db, 2);
check(!empty($result['success']), 'Inactive bus restores with details');
check($busTwo['status'] === 'Inactive', 'A prior Inactive lifecycle status is retained, not reactivated');
check($busTwo['plate_number'] === 'INACT-2', 'Details apply for the inactive restore too');
check($busTwo['driver'] === 'DRV-2', 'A changed driver is assigned during the restore');
$assignmentTwo = $db->query("SELECT driver_id FROM driver_bus_assignment WHERE bus_id = 'BUS-2'")->fetchColumn();
check($assignmentTwo === 'DRV-2', 'Changed driver assignment row is written');
check($busTwo['route_name'] === 'Cubao - Baguio' && (int)$busTwo['from_terminal_id'] === 1, 'Absent route and terminals stay preserved for the inactive restore');

/* --------------------------- rejections commit nothing --------------------------- */

ArchiveLifecycle::transition($db, $actor, 'bus', '3', 'archive', 'Retire validation unit');

try {
    ArchiveLifecycle::transition($db, $actor, 'bus', '3', 'restore', 'Restore invalid plate', [
        'bus_ref' => '3',
        'reason' => 'Restore invalid plate',
        'plate_number' => '',
        'unit_number' => '9999',
    ]);
    throw new LogicException('Expected invalid plate rejection');
} catch (InvalidArgumentException $e) {
    $busThree = busRow($db, 3);
    check($busThree['status'] === 'Archived', 'Invalid plate keeps the bus archived');
    check($busThree['plate_number'] === 'OLD-333' && ($busThree['unit_number'] ?? null) === null, 'Invalid plate changes no details');
}

try {
    ArchiveLifecycle::transition($db, $actor, 'bus', '3', 'restore', 'Restore invalid terminal', [
        'bus_ref' => '3',
        'reason' => 'Restore invalid terminal',
        'from_terminal_id' => '999',
        'plate_number' => 'NEW-333',
    ]);
    throw new LogicException('Expected invalid terminal rejection');
} catch (InvalidArgumentException $e) {
    check(str_contains($e->getMessage(), 'terminal'), 'Invalid terminal is rejected with a clear message');
    $busThree = busRow($db, 3);
    check($busThree['status'] === 'Archived', 'Invalid terminal keeps the bus archived');
    check($busThree['plate_number'] === 'OLD-333', 'Invalid terminal changes no details');
    check((int)$busThree['from_terminal_id'] === 1, 'Invalid terminal leaves the stored terminal id unchanged');
}

try {
    ArchiveLifecycle::transition($db, $actor, 'bus', '3', 'restore', 'Restore ineligible driver', [
        'bus_ref' => '3',
        'reason' => 'Restore ineligible driver',
        'driver' => 'DRV-UNKNOWN',
        'plate_number' => 'NEW-444',
        'from_terminal_id' => '1',
    ]);
    throw new LogicException('Expected ineligible driver rejection');
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'eligible'), 'Ineligible driver is rejected with a clear message');
    $busThree = busRow($db, 3);
    check($busThree['status'] === 'Archived', 'Ineligible driver keeps the bus archived');
    check($busThree['plate_number'] === 'OLD-333', 'Ineligible driver changes no details');
    check(($busThree['driver'] ?? null) === null, 'Ineligible driver changes no assignment');
}

/* ----------------------- audit failure rolls everything back ----------------------- */

$beforeFailure = busRow($db, 3);
$db->exec("CREATE TRIGGER deny_restore_audit BEFORE INSERT ON audit_trail WHEN NEW.action='Restore' BEGIN SELECT RAISE(ABORT,'restore audit failure'); END");
try {
    ArchiveLifecycle::transition($db, $actor, 'bus', '3', 'restore', 'Restore audit rollback', [
        'bus_ref' => '3',
        'reason' => 'Restore audit rollback',
        'plate_number' => 'ROLLBACK-3',
        'schedule' => '08:00 AM - 08:00 PM',
    ]);
    throw new LogicException('Expected audit failure');
} catch (PDOException $e) {
    $afterFailure = busRow($db, 3);
    check($afterFailure['status'] === 'Archived', 'Audit failure keeps the bus archived');
    check($afterFailure['plate_number'] === $beforeFailure['plate_number'], 'Audit failure rolls back the supplied details');
    check($afterFailure['schedule'] === $beforeFailure['schedule'], 'Audit failure rolls back every supplied field');
}
$db->exec('DROP TRIGGER deny_restore_audit');

/* ------------------- payload without details still restores ------------------- */

$result = ArchiveLifecycle::transition($db, $actor, 'bus', '3', 'restore', 'Plain restore without details', ['bus_ref' => '3', 'reason' => 'Plain restore without details']);
$busThree = busRow($db, 3);
check(!empty($result['success']), 'A restore without detail fields still succeeds');
check($busThree['status'] === 'Active', 'Plain restore returns the recorded prior status');
check($busThree['plate_number'] === 'OLD-333', 'Plain restore changes no details');

echo "$checks bus restore detail checks passed.\n";

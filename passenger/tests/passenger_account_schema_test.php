<?php declare(strict_types=1);

/**
 * Additive account-storage readiness and self-heal checks, run against the
 * isolated SQLite fixture so they never touch the application database.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

$dbFile = tempnam(sys_get_temp_dir(), 'account_schema_') . '.sqlite';
putenv('ONE_TIME_TEST_DB=' . $dbFile);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/../services/PassengerAccountSchema.php';
require_once __DIR__ . '/fixtures/one_time_test_db.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

final class RecordingAccountPDO extends OneTimeFixturePDO
{
    public array $execs = [];

    public function exec(string $statement): int|false
    {
        $this->execs[] = $statement;
        return parent::exec($statement);
    }
}

function ensureAgainst(RecordingAccountPDO $db): void
{
    $property = new ReflectionProperty(PassengerAccountSchema::class, 'ready');
    $property->setValue(null, []);
    PassengerAccountSchema::ensure($db);
}

one_time_test_db();

$db = new RecordingAccountPDO('sqlite:' . getenv('ONE_TIME_TEST_DB'));
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// ---- 1. Fully provisioned storage short-circuits without DDL ---------------
ensureAgainst($db);
check($db->execs === [], 'A fully provisioned account schema runs no schema-changing statement');

$ready = (new ReflectionProperty(PassengerAccountSchema::class, 'ready'))->getValue();
check(isset($ready[spl_object_id($db)]), 'The readiness check reports the storage as ready');

// ---- 2. A missing table is detected and healed additively ------------------
$db->exec('DROP TABLE passenger_account_notifications');
$db->execs = [];
ensureAgainst($db);

check($db->execs !== [], 'A missing notification table engages the additive migration path');
$object = $db->query("SELECT type FROM sqlite_master WHERE name = 'passenger_account_notifications'")->fetchColumn();
check($object === 'table', 'The missing notification table is recreated');

$stmt = $db->prepare('SELECT id, message FROM passenger_account_notifications WHERE passenger_id = ? AND read_at IS NULL ORDER BY id DESC LIMIT 3');
$stmt->execute([9]);
check($stmt->fetchAll() === [], 'The notification read succeeds after the heal');

// ---- 3. A legacy-shaped table gains the missing columns, rows intact -------
$db->exec('DROP TABLE passenger_account_notifications');
$db->exec('CREATE TABLE passenger_account_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, passenger_id INT, message TEXT)');
$db->prepare('INSERT INTO passenger_account_notifications (passenger_id, message) VALUES (?, ?)')->execute([9, 'legacy notice']);
ensureAgainst($db);

$columns = array_column($db->query('SHOW COLUMNS FROM passenger_account_notifications')->fetchAll(PDO::FETCH_ASSOC), 'Field');
check(in_array('event_id', $columns, true), 'The missing event_id column is added');
check(in_array('read_at', $columns, true), 'The missing read_at column is added');

$row = $db->query('SELECT passenger_id, message, read_at FROM passenger_account_notifications WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
check($row !== false && (int) $row['passenger_id'] === 9 && $row['message'] === 'legacy notice', 'Existing rows keep their values through provisioning');
check($row['read_at'] === null, 'The added read_at column defaults to NULL');

// ---- 4. A healed schema is idempotent --------------------------------------
$db->execs = [];
ensureAgainst($db);
check($db->execs === [], 'A second readiness run after the heal attempts no schema change');

// ---- 5. Best-effort: unrepairable storage never throws or gets destroyed ---
$db->exec('DROP TABLE passenger_account_notifications');
$db->exec("CREATE VIEW passenger_account_notifications AS SELECT 1 AS id, 'x' AS message");
$db->execs = [];
$threw = false;
try {
    ensureAgainst($db);
} catch (Throwable $e) {
    $threw = true;
}
check(!$threw, 'Provisioning never throws when the storage cannot be repaired');
$object = $db->query("SELECT type FROM sqlite_master WHERE name = 'passenger_account_notifications'")->fetchColumn();
check($object === 'view', 'The unrepairable object is left intact; provisioning is not destructive');

@unlink($dbFile);

echo "{$checks} passenger account schema checks passed.\n";

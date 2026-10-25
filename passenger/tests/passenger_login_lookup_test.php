<?php

/**
 * LoginModel::findPassenger canonical lookup over an isolated SQLite database:
 * stored 09/63/+63 and spaced or dashed phone formats resolve from any
 * equivalent input, padded/mixed-case emails resolve, soft-deleted rows are
 * excluded, unknown identities change nothing, and one-time contact numbers
 * are not login identities.
 *
 * Run: php passenger/tests/passenger_login_lookup_test.php
 */

declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/../services/PassengerValidator.php';
require_once __DIR__ . '/../models/Login_Account/loginmodel.php';

$failures = 0;
$checks = 0;

function check(string $label, bool $condition): void
{
    global $failures, $checks;
    $checks++;

    if ($condition) {
        echo "PASS  $label\n";
    } else {
        echo "FAIL  $label\n";
        $failures++;
    }
}

/**
 * Native (non-emulated) prepared statements — MySQL by default — reject a
 * named placeholder that appears more than once in a query, while SQLite
 * accepts it. This guard mirrors that behavior so the login lookup tests fail
 * on the production failure mode here.
 */
class NativePrepareGuardPDO extends PDO
{
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options ?? []);

        /* phoneColumnExpr() uses MySQL's CONCAT/LEFT; SQLite needs them registered. */
        @$this->sqliteCreateFunction('concat', function (...$parts) {
            return implode('', array_map(static fn ($part) => (string) $part, $parts));
        }, -1);
        @$this->sqliteCreateFunction('left', function ($value, $count) {
            return $value === null ? null : substr((string) $value, 0, (int) $count);
        }, 2);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $query, $names)) {
            foreach (array_count_values($names[1]) as $count) {
                if ($count > 1) {
                    throw new PDOException('SQLSTATE[HY093]: Invalid parameter number');
                }
            }
        }

        return parent::prepare($query, $options);
    }
}

$db = new NativePrepareGuardPDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

(new ReflectionProperty(App\Core\Database::class, 'instance'))->setValue(null, $db);

$db->exec("
    CREATE TABLE passengers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT,
        email TEXT,
        phone TEXT,
        contact_number TEXT,
        password TEXT,
        status TEXT DEFAULT 'Active',
        account_status TEXT DEFAULT 'ACTIVE',
        registration_type TEXT DEFAULT 'regular'
    );
");

$insert = $db->prepare("
    INSERT INTO passengers (full_name, email, phone, contact_number, password, status, account_status, registration_type)
    VALUES (?,?,?,?,?,?,?,?)
");
$insert->execute(['Country Prefix Rider', 'country@example.com', '+63 917 123 4567', null, password_hash('x', PASSWORD_DEFAULT), 'Active', 'ACTIVE', 'regular']);
$insert->execute(['Local Rider', 'local@example.com', '09181234567', null, password_hash('x', PASSWORD_DEFAULT), 'Active', 'ACTIVE', 'regular']);
$insert->execute(['Legacy 63 Rider', 'legacy63@example.com', '639191234567', null, password_hash('x', PASSWORD_DEFAULT), 'Active', 'ACTIVE', 'regular']);
$insert->execute(['Spaced Rider', null, '0921 123 4567', null, password_hash('x', PASSWORD_DEFAULT), 'Active', 'ACTIVE', 'regular']);
$insert->execute(['Dashed Rider', null, '0917-123-4568', null, password_hash('x', PASSWORD_DEFAULT), 'Active', 'ACTIVE', 'regular']);
$insert->execute(['Padded Email Rider', '  Juan@Example.COM ', null, null, password_hash('x', PASSWORD_DEFAULT), 'Active', 'ACTIVE', 'regular']);
$insert->execute(['Deleted Rider', 'deleted@example.com', '09201234567', null, password_hash('x', PASSWORD_DEFAULT), 'deleted', 'DELETED', 'regular']);
$insert->execute(['One Time Rider', null, null, '09331234567', password_hash('x', PASSWORD_DEFAULT), 'Active', 'ACTIVE', 'one_time']);

$model = new LoginModel();

/* --- The guard itself rejects reused named placeholders ------------------- */
$guardRejected = false;
try {
    $db->prepare('SELECT * FROM passengers WHERE email = :raw OR phone = :raw');
} catch (PDOException $e) {
    $guardRejected = str_contains($e->getMessage(), 'HY093');
}
check('native-prepare guard rejects a reused named placeholder', $guardRejected);

/* --- Phone format variants resolve from any equivalent input ------------- */
$byLocal = $model->findPassenger('09171234567');
check('stored +63 number resolves from a 09… login', $byLocal !== null && $byLocal['full_name'] === 'Country Prefix Rider');
check('stored +63 number resolves from a +63 login', ($model->findPassenger('+639171234567')['full_name'] ?? null) === 'Country Prefix Rider');
check('stored 09… number resolves from a +63 login', ($model->findPassenger('+639181234567')['full_name'] ?? null) === 'Local Rider');
check('stored 639… number resolves from a 09… login', ($model->findPassenger('09191234567')['full_name'] ?? null) === 'Legacy 63 Rider');
check('stored spaced number resolves', ($model->findPassenger('+63 921 123 4567')['full_name'] ?? null) === 'Spaced Rider');
check('stored dashed number resolves', ($model->findPassenger('09171234568')['full_name'] ?? null) === 'Dashed Rider');

/* --- Email case/padding variants resolve --------------------------------- */
$byEmail = $model->findPassenger('juan@example.com');
check('padded mixed-case stored email resolves', $byEmail !== null && $byEmail['full_name'] === 'Padded Email Rider');

/* --- Soft-deleted accounts are excluded ---------------------------------- */
check('a deleted account resolves as nonexistent', $model->findPassenger('deleted@example.com') === null);
check('a deleted phone resolves as nonexistent', $model->findPassenger('09201234567') === null);

/* --- One-time contact numbers are not login identities ------------------- */
check('a one-time contact number is not a login identity', $model->findPassenger('09331234567') === null);

/* --- Unknown identities change nothing ----------------------------------- */
$before = (int) $db->query('SELECT COUNT(*) FROM passengers')->fetchColumn();
check('an unknown identity resolves as nonexistent', $model->findPassenger('nobody@example.com') === null);
check('an unknown phone resolves as nonexistent', $model->findPassenger('09999999999') === null);
check('lookups create no rows', (int) $db->query('SELECT COUNT(*) FROM passengers')->fetchColumn() === $before);

echo "\n$checks checks, $failures failure(s)\n";
exit($failures === 0 ? 0 : 1);

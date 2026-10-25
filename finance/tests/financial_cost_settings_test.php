<?php
declare(strict_types=1);

/**
 * Financial Cost Settings — preview, validation, and verification tests.
 *
 * Usage: php finance/tests/financial_cost_settings_test.php
 *
 * Uses an in-memory SQLite fixture for the users and audit tables so the
 * verification logic runs without a MySQL server.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/auth/Auth.php';
require_once __DIR__ . '/../services/FinancialCostSettingsService.php';

use App\Core\Database;
use Finance\Services\FinancialCostSettingsService;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function expectThrow(callable $fn, string $needle, string $message): void
{
    global $checks;
    try {
        $fn();
    } catch (\Exception $e) {
        if (stripos($e->getMessage(), $needle) === false) {
            throw new RuntimeException($message . ' (unexpected message: ' . $e->getMessage() . ')');
        }
        $checks++;
        return;
    }
    throw new RuntimeException($message . ' (no exception thrown)');
}

function asUser(string $role, int $id = 7, string $username = 'finance.tester'): void
{
    $_SESSION['user'] = ['id' => $id, 'username' => $username, 'role' => $role];
}

/* ---------- SQLite fixture for users + audit trail ---------- */

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, email TEXT, password TEXT)");
$db->exec("CREATE TABLE audit_trail (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL, username TEXT NULL, user_role TEXT NULL,
    module TEXT NOT NULL, action TEXT NOT NULL, record_id TEXT NULL,
    description TEXT NULL, previous_values TEXT NULL, new_values TEXT NULL,
    ip_address TEXT NULL, user_agent TEXT NULL,
    status TEXT NOT NULL DEFAULT 'Success', is_read INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE fuel_prices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fuel_type TEXT NOT NULL,
    price_per_liter REAL NOT NULL,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("INSERT INTO fuel_prices (id, fuel_type, price_per_liter) VALUES (1, 'Diesel', 82.70), (2, 'Gasoline', 66.06)");
$insertUser = $db->prepare("INSERT INTO users (id, username, role, email, password) VALUES (?, ?, ?, ?, ?)");
$insertUser->execute([7, 'finance.tester', 'finance_manager', 'manager@example.com', password_hash('Secret123!', PASSWORD_DEFAULT)]);
$insertUser->execute([8, 'ofelia.officer', 'finance_officer', 'officer@example.com', password_hash('OfficerPass1!', PASSWORD_DEFAULT)]);

$instance = new ReflectionProperty(Database::class, 'instance');
$instance->setValue(null, $db);

$validRows = [
    'amortization' => [
        'amount' => '1098.00',
        'rate' => '0',
        'calc_method' => 'fixed_monthly',
        'unit' => 'PHP/month',
        'effective_date' => '2026-10-01',
        'status' => 'Active',
        'notes' => 'Unchanged',
    ],
];

/* ---------- role guard ---------- */

asUser('finance_officer', 8, 'ofelia.officer');
expectThrow(
    fn() => FinancialCostSettingsService::preview($validRows),
    'Forbidden',
    'Finance Officer cannot edit settings'
);
check(FinancialCostSettingsService::canView() === true, 'Finance Officer can still view settings');

asUser('finance_manager');

/* ---------- preview: no changes ---------- */

$currentSettings = FinancialCostSettingsService::getSettings();
$sameRows = [];
foreach ($currentSettings as $key => $row) {
    $sameRows[$key] = [
        'amount' => number_format((float) $row['amount'], 2, '.', ''),
        'rate' => number_format((float) $row['rate'], 2, '.', ''),
        'calc_method' => (string) $row['calc_method'],
        'unit' => (string) $row['unit'],
        'effective_date' => (string) $row['effective_date'],
        'status' => (string) $row['status'],
        'notes' => (string) $row['notes'],
    ];
}
$noChanges = FinancialCostSettingsService::preview($sameRows);
check($noChanges === [], 'Identical values produce an empty change preview');

expectThrow(
    fn() => FinancialCostSettingsService::preview([]),
    'No settings were submitted',
    'Empty payload refused'
);
expectThrow(
    fn() => FinancialCostSettingsService::preview(['unknown_key' => ['amount' => '1']]),
    'Unknown cost setting',
    'Unknown setting key refused'
);

/* ---------- preview: real changes with old and new values ---------- */

$changed = FinancialCostSettingsService::preview([
    'amortization' => array_merge($validRows['amortization'], ['amount' => '1200.50', 'notes' => 'New premium']),
]);
check(isset($changed['amortization']), 'Changed setting appears in the preview');
check($changed['amortization']['label'] === 'Amortization', 'Preview carries the setting label');
check($changed['amortization']['fields']['amount']['old'] == 1098.0, 'Preview carries the previous amount');
check($changed['amortization']['fields']['amount']['new'] == 1200.5, 'Preview carries the new amount');
check($changed['amortization']['fields']['notes']['old'] === '', 'Preview carries the previous notes');
check($changed['amortization']['fields']['notes']['new'] === 'New premium', 'Preview carries the new notes');
check(!isset($changed['amortization']['fields']['status']), 'Unchanged fields are not listed');

$rateChange = FinancialCostSettingsService::preview([
    'optr' => [
        'amount' => '500.00',
        'rate' => '12.5',
        'calc_method' => 'percent_gross',
        'unit' => '% of gross',
        'effective_date' => '2026-10-01',
        'status' => 'Active',
        'notes' => '',
    ],
]);
check(isset($rateChange['optr']['fields']['calc_method']), 'Calculation method change is detected');
check(isset($rateChange['optr']['fields']['rate']), 'Rate change is detected');

/* ---------- validation limits ---------- */

$base = $validRows['amortization'];
expectThrow(
    fn() => FinancialCostSettingsService::preview(['amortization' => array_merge($base, ['amount' => '-1'])]),
    'between 0 and',
    'Negative amount refused'
);
expectThrow(
    fn() => FinancialCostSettingsService::preview(['amortization' => array_merge($base, ['amount' => '99999999'])]),
    'between 0 and',
    'Amount above the maximum refused'
);
expectThrow(
    fn() => FinancialCostSettingsService::preview(['amortization' => array_merge($base, ['calc_method' => 'percent_gross', 'rate' => '150'])]),
    'between 0 and',
    'Rate above 100 refused'
);
expectThrow(
    fn() => FinancialCostSettingsService::preview(['amortization' => array_merge($base, ['calc_method' => 'percent_gross', 'rate' => 'abc'])]),
    'must be a number',
    'Non-numeric rate refused'
);
expectThrow(
    fn() => FinancialCostSettingsService::preview(['amortization' => array_merge($base, ['effective_date' => '2026-13-40'])]),
    'effective date',
    'Invalid effective date refused'
);
expectThrow(
    fn() => FinancialCostSettingsService::preview(['amortization' => array_merge($base, ['calc_method' => 'bogus'])]),
    'invalid calculation method',
    'Invalid calculation method refused'
);

/* ---------- only method-relevant fields can change ---------- */

$tripChange = FinancialCostSettingsService::preview([
    'gps' => array_merge($sameRows['gps'], ['calc_method' => 'per_trip', 'amount' => '2.50']),
]);
check(isset($tripChange['gps']['fields']['calc_method']), 'Calculation method change is detected');
check(isset($tripChange['gps']['fields']['amount']), 'Amount change is detected for fixed methods');
check($tripChange['gps']['fields']['unit']['new'] === 'PHP/trip', 'Unit is derived from the calculation method');

$rateIgnored = FinancialCostSettingsService::preview([
    'amortization' => array_merge($base, ['rate' => '50']),
]);
check(!isset($rateIgnored['amortization']['fields']['rate']), 'Rate is ignored for fixed amount methods');

$amountIgnored = FinancialCostSettingsService::preview([
    'optr' => [
        'calc_method' => 'percent_gross',
        'rate' => '12.5',
        'amount' => '999999',
        'effective_date' => '2026-10-01',
        'status' => 'Active',
        'notes' => '',
    ],
]);
check(isset($amountIgnored['optr']['fields']['rate']), 'Rate change is detected for percent of gross');
check(isset($amountIgnored['optr']['fields']['calc_method']), 'Method change to percent of gross is detected');
check(!isset($amountIgnored['optr']['fields']['amount']), 'Amount is preserved and never applied for percent of gross');

$longNotes = FinancialCostSettingsService::preview(['amortization' => array_merge($base, ['notes' => str_repeat('x', 300)])]);
check(mb_strlen($longNotes['amortization']['fields']['notes']['new']) === 255, 'Overlong notes are truncated to 255 characters');

/* ---------- verification gate ---------- */

unset($_SESSION['fcs_verify'], $_SESSION['fcs_otp'], $_SESSION['fcs_otp_sent_at'], $_SESSION['fcs_auth_throttle']);
$state = FinancialCostSettingsService::verificationState();
check($state['password'] === false && $state['otp'] === false && $state['ready'] === false, 'Verification starts incomplete');
check($state['has_email'] === true && $state['email_masked'] === 'm******@example.com', 'Registered email is available and masked');
check(FinancialCostSettingsService::identityVerified() === false, 'Save is blocked before verification');

expectThrow(
    fn() => FinancialCostSettingsService::save($validRows, 'A valid reason for the change'),
    'verify your account password',
    'Save without verification refused'
);

$_SESSION['fcs_verify'] = ['user_id' => 7, 'password' => true, 'otp' => false, 'at' => time()];
check(FinancialCostSettingsService::identityVerified() === false, 'Password alone does not unlock saving');

$_SESSION['fcs_verify'] = ['user_id' => 7, 'password' => false, 'otp' => true, 'at' => time()];
check(FinancialCostSettingsService::identityVerified() === false, 'Email code alone does not unlock saving');

$_SESSION['fcs_verify'] = ['user_id' => 7, 'password' => true, 'otp' => true, 'at' => time()];
check(FinancialCostSettingsService::identityVerified() === true, 'Password plus email code unlocks saving');

$_SESSION['fcs_verify'] = ['user_id' => 999, 'password' => true, 'otp' => true, 'at' => time()];
check(FinancialCostSettingsService::identityVerified() === false, 'Verification for another user does not unlock saving');

$_SESSION['fcs_verify'] = ['user_id' => 7, 'password' => true, 'otp' => true, 'at' => time() - 700];
check(FinancialCostSettingsService::identityVerified() === false, 'Verification expires after the identity window');

/* ---------- password verification ---------- */

unset($_SESSION['fcs_verify'], $_SESSION['fcs_auth_throttle']);
check(FinancialCostSettingsService::verifyPassword('WrongPassword') === false, 'Incorrect password is rejected');
$failedAudits = (int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'CostSettingVerification' AND status = 'Failed'")->fetchColumn();
check($failedAudits === 1, 'Failed password verification is audited');
check(isset($_SESSION['fcs_auth_throttle']) && $_SESSION['fcs_auth_throttle']['fails'] === 1, 'Failed password attempts are throttled');

check(FinancialCostSettingsService::verifyPassword('Secret123!') === true, 'Correct password is accepted');
check(!isset($_SESSION['fcs_auth_throttle']), 'Successful verification clears the failure throttle');
$state = FinancialCostSettingsService::verificationState();
check($state['password'] === true && $state['otp'] === false, 'Password factor recorded without the email factor');

/* ---------- email code verification ---------- */

unset($_SESSION['fcs_auth_throttle']);
$_SESSION['fcs_otp_sent_at'] = time();
expectThrow(
    fn() => FinancialCostSettingsService::sendOtp(),
    'Please wait',
    'Immediate resend is throttled'
);
unset($_SESSION['fcs_otp_sent_at']);

$_SESSION['fcs_otp'] = ['hash' => password_hash('123456', PASSWORD_DEFAULT), 'expires' => time() + 300, 'attempts' => 0];
check(FinancialCostSettingsService::verifyOtp('000000') === false, 'Incorrect email code is rejected');
check((int) $_SESSION['fcs_otp']['attempts'] === 1, 'Incorrect attempts are counted');
$failedOtpAudits = (int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'CostSettingVerification' AND status = 'Failed'")->fetchColumn();
check($failedOtpAudits >= 2, 'Failed email code verification is audited');

check(FinancialCostSettingsService::verifyOtp('123456') === true, 'Correct email code is accepted');
check(!isset($_SESSION['fcs_otp']), 'Used email code is cleared');
$state = FinancialCostSettingsService::verificationState();
check($state['password'] === true && $state['otp'] === true && $state['ready'] === true, 'Both factors unlock the save');

$_SESSION['fcs_otp'] = ['hash' => password_hash('123456', PASSWORD_DEFAULT), 'expires' => time() - 1, 'attempts' => 0];
expectThrow(
    fn() => FinancialCostSettingsService::verifyOtp('123456'),
    'expired',
    'Expired email code refused'
);

unset($_SESSION['fcs_otp']);
expectThrow(
    fn() => FinancialCostSettingsService::verifyOtp('123456'),
    'No verification code was requested',
    'Verification without a requested code refused'
);

/* ---------- fuel prices (shared fuel_prices table) ---------- */

$fuelPrices = FinancialCostSettingsService::getFuelPrices();
check(count($fuelPrices) === 2, 'Both fuel price records are read from fuel_prices');
check($fuelPrices[0]['fuel_type'] === 'Diesel' && abs($fuelPrices[0]['price_per_liter'] - 82.70) < 0.001, 'Diesel price comes from fuel_prices');
check($fuelPrices[1]['fuel_type'] === 'Gasoline' && abs($fuelPrices[1]['price_per_liter'] - 66.06) < 0.001, 'Gasoline price comes from fuel_prices');

$fuelPreview = FinancialCostSettingsService::preview([], [1 => '85.50']);
check(isset($fuelPreview['fuel_price_1']), 'Fuel price change appears in the preview');
check($fuelPreview['fuel_price_1']['label'] === 'Fuel Price — Diesel', 'Fuel price preview carries the fuel type label');
check(abs($fuelPreview['fuel_price_1']['fields']['price_per_liter']['old'] - 82.70) < 0.001, 'Fuel price preview carries the old price');
check(abs($fuelPreview['fuel_price_1']['fields']['price_per_liter']['new'] - 85.50) < 0.001, 'Fuel price preview carries the new price');

$fuelSame = FinancialCostSettingsService::preview([], [1 => '82.70', 2 => '66.06']);
check($fuelSame === [], 'Unchanged fuel prices produce no preview change');

expectThrow(
    fn() => FinancialCostSettingsService::preview([], []),
    'No settings were submitted',
    'Empty settings and fuel prices refused'
);

expectThrow(
    fn() => FinancialCostSettingsService::preview([], [99 => '80.00']),
    'Unknown fuel price record',
    'Unknown fuel price record refused'
);
expectThrow(
    fn() => FinancialCostSettingsService::preview([], [1 => 'abc']),
    'must be a number',
    'Non-numeric fuel price refused'
);
expectThrow(
    fn() => FinancialCostSettingsService::preview([], [1 => '0']),
    'between 0.01 and',
    'Zero fuel price refused'
);
expectThrow(
    fn() => FinancialCostSettingsService::preview([], [1 => '1001']),
    'between 0.01 and',
    'Fuel price above the maximum refused'
);

asUser('finance_officer', 8, 'ofelia.officer');
expectThrow(
    fn() => FinancialCostSettingsService::preview([], [1 => '85.00']),
    'Forbidden',
    'Finance Officer cannot edit fuel prices'
);
asUser('finance_manager');

$gasTamper = FinancialCostSettingsService::preview([
    'gas' => array_merge($sameRows['gas'], ['amount' => '999999']),
], []);
check($gasTamper === [], 'Gas amount is not editable through the settings payload');

/* ---------- reason validation before any write ---------- */

unset($_SESSION['fcs_verify']);
asUser('finance_officer', 8, 'ofelia.officer');
expectThrow(
    fn() => FinancialCostSettingsService::save($validRows, 'A valid reason'),
    'not authorized',
    'Officer save refused'
);

asUser('finance_manager');
expectThrow(
    fn() => FinancialCostSettingsService::save($validRows, 'short'),
    'verify your account password',
    'Save is refused before the reason check when identity is missing'
);

/* ---------- save writes fuel prices to fuel_prices with audit ---------- */

$_SESSION['fcs_verify'] = ['user_id' => 7, 'password' => true, 'otp' => true, 'at' => time()];
$fuelSave = FinancialCostSettingsService::save($sameRows, 'Adjusting diesel reference price', [1 => '85.00']);
check(isset($fuelSave['fuel_price_1']), 'Fuel price change is reported as saved');
check(abs((float) $db->query('SELECT price_per_liter FROM fuel_prices WHERE id = 1')->fetchColumn() - 85.00) < 0.001, 'Diesel price is written to fuel_prices');

$fuelAudit = $db->query("SELECT previous_values, new_values FROM audit_trail WHERE action = 'FuelPriceUpdated' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check($fuelAudit !== false, 'Fuel price change is audit-logged');
$fuelPrev = json_decode((string) $fuelAudit['previous_values'], true);
$fuelNext = json_decode((string) $fuelAudit['new_values'], true);
check(abs((float) $fuelPrev['price_per_liter'] - 82.70) < 0.001, 'Audit keeps the previous fuel price');
check(abs((float) $fuelNext['price_per_liter'] - 85.00) < 0.001, 'Audit keeps the new fuel price');

$recent = FinancialCostSettingsService::getRecentAudit(20);
$hasFuelAudit = false;
foreach ($recent as $entry) {
    if (($entry['record_id'] ?? '') === 'FUEL-1') {
        $hasFuelAudit = true;
        break;
    }
}
check($hasFuelAudit, 'Fuel price changes appear in the recent changes list');

check(FinancialCostSettingsService::identityVerified() === false, 'Verification is cleared after a successful save');

echo "Financial cost settings tests passed ($checks checks)\n";
exit(0);

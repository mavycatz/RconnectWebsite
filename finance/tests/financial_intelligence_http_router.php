<?php
declare(strict_types=1);

/**
 * TEST-ONLY front controller.
 *
 * Serves finance/api/analytics/financial_intelligence.php against a
 * file-backed SQLite fixture so the endpoint can be exercised over real HTTP
 * without a MySQL server. Never included by production code.
 *
 * Usage (from the repository root):
 *   php -S 127.0.0.1:8138 finance/tests/financial_intelligence_http_router.php
 *
 * The request may carry X-Test-Role to switch the acting role; use `none`
 * to simulate an unauthenticated caller.
 */

require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/auth/Auth.php';
require_once __DIR__ . '/FinancialIntelligenceFixture.php';

use App\Core\Database;

/**
 * TEST-ONLY PDO that can simulate a failing source. The X-Test-Fail request
 * header names a table fragment; matching SELECT statements throw instead of
 * returning rows so the endpoint's partial/error semantics can be exercised.
 */
final class FiFailurePdo extends PDO
{
    public string $failSource = '';

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failSource !== ''
            && stripos($query, 'select') !== false
            && stripos($query, $this->failSource) !== false) {
            throw new PDOException('simulated source failure for ' . $this->failSource);
        }
        return parent::prepare($query, $options);
    }
}

$fixturePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rconnect_financial_intelligence_http_fixture.sqlite';
$fresh = !is_file($fixturePath);

$db = new FiFailurePdo('sqlite:' . $fixturePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
if ($fresh) {
    FinancialIntelligenceFixture::schema($db);
    FinancialIntelligenceFixture::seed($db);
}
$db->failSource = strtolower(trim((string) ($_SERVER['HTTP_X_TEST_FAIL'] ?? '')));

$instanceProperty = new ReflectionProperty(Database::class, 'instance');
$instanceProperty->setValue(null, $db);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = (string) ($_SERVER['HTTP_X_TEST_ROLE'] ?? 'finance_manager');
$usernames = [
    'finance_manager' => 'maria.manager',
    'finance_officer' => 'ofelia.officer',
    'driver' => 'driver.user',
    'admin' => 'admin.user',
    'super_admin' => 'super.user',
];
$_SESSION = [
    'csrf_token' => 'test-csrf-token',
    'user' => $role === 'none' ? [] : [
        'id' => 1,
        'username' => $usernames[$role] ?? 'fi.http',
        'role' => $role,
        'logged_in' => true,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    ],
];

require __DIR__ . '/../api/analytics/financial_intelligence.php';

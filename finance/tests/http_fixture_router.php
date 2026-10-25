<?php
declare(strict_types=1);

/**
 * TEST-ONLY front controller.
 *
 * Serves finance/discrepancies.php actions against a file-backed SQLite
 * fixture so the workflow can be exercised over real HTTP without a MySQL
 * server. Never included by production code.
 *
 * Usage (from the repository root):
 *   php -S 127.0.0.1:8137 finance/tests/http_fixture_router.php
 *
 * The request may carry X-Test-Role to switch the acting role; use `none`
 * to simulate an unauthenticated caller.
 */

require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/auth/Auth.php';
require_once __DIR__ . '/DiscrepancyFixture.php';
require_once __DIR__ . '/../controllers/DiscrepancyController.php';

use App\Core\Database;
use Finance\Controllers\DiscrepancyController;

$fixturePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rconnect_discrepancy_http_fixture.sqlite';

$db = new PDO('sqlite:' . $fixturePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
DiscrepancyFixture::schema($db);

if ((int) $db->query('SELECT COUNT(*) FROM discrepancy_cases')->fetchColumn() === 0) {
    discrepancy_http_fixture_seed($db);
}

$instanceProperty = new ReflectionProperty(Database::class, 'instance');
$instanceProperty->setValue(null, $db);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

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
        'username' => $usernames[$role] ?? 'http.fixture',
        'role' => $role,
        'logged_in' => true,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    ],
];

$action = (string) ($_GET['action'] ?? 'list');
$routes = [
    'index' => 'index',
    'list' => 'list',
    'view' => 'view',
    'assign' => 'assign',
    'status' => 'status',
    'reclassify' => 'reclassify',
    'findings' => 'findings',
    'submit_findings' => 'submitFindings',
    'manager_review' => 'managerReview',
    'manager_decision' => 'managerDecision',
    'evidence' => 'evidence',
    'ledger_propose' => 'ledgerPropose',
    'approval_decide' => 'approvalDecide',
    'ledger_post' => 'ledgerPost',
    'ledger_void' => 'ledgerVoid',
    'rules_get' => 'rulesGet',
    'rules_save' => 'rulesSave',
    'backfill' => 'backfill',
    'finance_users' => 'financeUsers',
    'audit' => 'auditTrail',
    'print' => 'print',
    'export_pdf' => 'exportPdf',
    'export_excel' => 'exportExcel',
];

$method = $routes[$action] ?? 'list';
$controller = new DiscrepancyController();
$controller->{$method}();

/**
 * Fixed dataset: five cases covering every status family, an overdue
 * investigation, a pending approval, posted ledger activity, and both
 * discrepancy directions.
 */
function discrepancy_http_fixture_seed(PDO $db): void
{
    $today = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $daysAgo = static fn(int $days): string => $today->modify('-' . $days . ' days')->format('Y-m-d');
    $future = $today->modify('+3 days')->format('Y-m-d');
    $yesterday = $today->modify('-1 day')->format('Y-m-d');

    foreach ([
        'D1' => 'Alicia Driver',
        'D2' => 'Ben Driver',
        'D3' => 'Cara Driver',
        'D4' => 'Dino Driver',
    ] as $driverId => $name) {
        DiscrepancyFixture::seedDriver($db, $driverId, $name);
    }

    foreach ([
        ['Maria Manager', 'maria.manager', 'finance_manager'],
        ['Ofelia Officer', 'ofelia.officer', 'finance_officer'],
        ['Driver User', 'driver.user', 'driver'],
    ] as [$fullName, $username, $role]) {
        DiscrepancyFixture::insertRow($db, 'users', [
            'username' => $username,
            'role' => $role,
            'full_name' => $fullName,
            'status' => 'active',
        ]);
    }

    $r1 = DiscrepancyFixture::seedRemittanceLog($db, ['driver_id' => 'D1', 'business_date' => '2026-10-01', 'expected_cash' => 500, 'actual_cash' => 450, 'discrepancy_amount' => 50, 'status' => 'Discrepancy']);
    $r2 = DiscrepancyFixture::seedRemittanceLog($db, ['driver_id' => 'D2', 'business_date' => '2026-10-02', 'expected_cash' => 300, 'actual_cash' => 300, 'discrepancy_amount' => 0, 'status' => 'Discrepancy']);
    $r3 = DiscrepancyFixture::seedRemittanceLog($db, ['driver_id' => 'D3', 'business_date' => '2026-09-20', 'expected_cash' => 200, 'actual_cash' => null, 'discrepancy_amount' => 0, 'status' => 'Overdue']);
    $r4 = DiscrepancyFixture::seedRemittanceLog($db, ['driver_id' => 'D4', 'business_date' => '2026-09-01', 'expected_cash' => 400, 'actual_cash' => 460, 'discrepancy_amount' => 0, 'status' => 'Discrepancy']);
    $r5 = DiscrepancyFixture::seedRemittanceLog($db, ['driver_id' => 'D2', 'business_date' => '2026-08-15', 'expected_cash' => 250, 'actual_cash' => 220, 'discrepancy_amount' => 30, 'status' => 'Resolved']);

    $case1 = DiscrepancyFixture::seedCase($db, [
        'remittance_log_id' => $r1, 'driver_id' => 'D1', 'business_date' => '2026-10-01',
        'case_type' => 'Cash Shortage', 'status' => 'Open',
        'expected_amount' => 500, 'actual_amount' => 450, 'discrepancy_amount' => 50, 'excess_amount' => 0,
        'due_date' => $future, 'opened_by' => 'seed', 'opened_at' => $daysAgo(0) . ' 08:00:00',
    ]);
    $case2 = DiscrepancyFixture::seedCase($db, [
        'remittance_log_id' => $r2, 'driver_id' => 'D2', 'business_date' => '2026-10-02',
        'case_type' => 'QR Ph Payment Mismatch', 'status' => 'Awaiting Approval',
        'expected_amount' => 300, 'actual_amount' => 300, 'discrepancy_amount' => 0, 'excess_amount' => 0,
        'assigned_reviewer' => 'maria.manager', 'due_date' => $future,
        'opened_by' => 'seed', 'opened_at' => $daysAgo(0) . ' 09:00:00',
    ]);
    $case3 = DiscrepancyFixture::seedCase($db, [
        'remittance_log_id' => $r3, 'driver_id' => 'D3', 'business_date' => '2026-09-20',
        'case_type' => 'Overdue Remittance', 'status' => 'Under Investigation',
        'expected_amount' => 200, 'actual_amount' => null, 'discrepancy_amount' => 0, 'excess_amount' => 0,
        'assigned_reviewer' => 'maria.manager', 'due_date' => $yesterday,
        'opened_by' => 'seed', 'opened_at' => $daysAgo(10) . ' 08:00:00',
    ]);
    $case4 = DiscrepancyFixture::seedCase($db, [
        'remittance_log_id' => $r4, 'driver_id' => 'D4', 'business_date' => '2026-09-01',
        'case_type' => 'Excess Remittance', 'status' => 'Closed',
        'expected_amount' => 400, 'actual_amount' => 460, 'discrepancy_amount' => 0, 'excess_amount' => 60,
        'due_date' => $daysAgo(17), 'opened_by' => 'seed', 'opened_at' => $daysAgo(20) . ' 08:00:00',
    ]);
    $case5 = DiscrepancyFixture::seedCase($db, [
        'remittance_log_id' => $r5, 'driver_id' => 'D2', 'business_date' => '2026-08-15',
        'case_type' => 'System or Fare Error', 'status' => 'Resolved',
        'expected_amount' => 250, 'actual_amount' => 220, 'discrepancy_amount' => 30, 'excess_amount' => 0,
        'findings' => 'Fare table misconfigured; corrected.', 'resolution_reason' => 'Accounting adjustment approved.',
        'due_date' => $daysAgo(37), 'opened_by' => 'seed', 'opened_at' => $daysAgo(40) . ' 08:00:00',
    ]);

    DiscrepancyFixture::seedLedgerEntry($db, [
        'case_id' => $case1, 'entry_type' => 'additional_payment', 'recovery_class' => 'cash',
        'amount' => 20, 'state' => 'posted', 'reference' => 'OR-2001',
        'proposed_by' => 'seed', 'proposed_at' => $daysAgo(1) . ' 10:00:00',
        'posted_by' => 'seed', 'posted_at' => $daysAgo(1) . ' 10:05:00',
    ]);
    DiscrepancyFixture::seedLedgerEntry($db, [
        'case_id' => $case5, 'entry_type' => 'waiver', 'recovery_class' => 'adjustment',
        'amount' => 30, 'state' => 'posted', 'reference' => 'ADJ-9001',
        'proposed_by' => 'seed', 'proposed_at' => $daysAgo(35) . ' 10:00:00',
        'posted_by' => 'seed', 'posted_at' => $daysAgo(35) . ' 10:05:00',
    ]);

    DiscrepancyFixture::seedApproval($db, [
        'case_id' => $case2, 'ledger_entry_id' => null, 'action' => 'accounting_adjustment',
        'amount' => 10, 'reason' => 'Reclassify QR Ph shortfall.', 'status' => 'pending',
        'requested_by' => 'ofelia.officer', 'requested_at' => $daysAgo(0) . ' 11:00:00',
    ]);
}

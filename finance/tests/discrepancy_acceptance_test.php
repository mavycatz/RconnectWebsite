<?php
declare(strict_types=1);

/**
 * Discrepancy workflow acceptance matrix (task 8.1).
 *
 * Exercises the scenarios the request calls out end to end on one fixture:
 * partial recoveries, incorrect payment records (QR Ph mismatch), duplicate
 * submissions, approved waivers, failed approvals, late remittances, reopened
 * cases, and excess remittances, asserting resulting statuses, balances, and
 * audit entries.
 *
 * Usage: php finance/tests/discrepancy_acceptance_test.php
 */

date_default_timezone_set('Asia/Manila');
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/DiscrepancyFixture.php';
require_once __DIR__ . '/../services/DiscrepancyCaseService.php';
require_once __DIR__ . '/../services/DiscrepancyLedgerService.php';
require_once __DIR__ . '/../services/DiscrepancyMetricsService.php';
require_once __DIR__ . '/../models/DiscrepancyCaseModel.php';

use Finance\Models\DiscrepancyCaseModel;
use Finance\Services\DiscrepancyCaseService;
use Finance\Services\DiscrepancyLedgerService;
use Finance\Services\DiscrepancyMetricsService;

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
        if (strpos($e->getMessage(), $needle) === false) {
            throw new RuntimeException($message . ' (unexpected message: ' . $e->getMessage() . ')');
        }
        $checks++;
        return;
    }
    throw new RuntimeException($message . ' (no exception thrown)');
}

function actor(string $role, string $username): array
{
    $_SESSION['user'] = ['id' => 1, 'username' => $username, 'role' => $role];
    return ['id' => 1, 'username' => $username, 'role' => $role];
}

$db = DiscrepancyFixture::pdo();
$cases = new DiscrepancyCaseService($db);
$ledger = new DiscrepancyLedgerService($db, null, $cases);
$metrics = new DiscrepancyMetricsService($db, $cases);

$officer = ['role' => 'finance_officer', 'username' => 'ofelia.officer'];
$manager = ['role' => 'finance_manager', 'username' => 'maria.manager'];

/* =========================================================
 * Scenario 1: shortage creates a case with immutable snapshots
 * ========================================================= */

actor($officer['role'], $officer['username']);
$remit = DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D1', 'business_date' => '2026-10-01',
    'expected_cash' => 500, 'actual_cash' => 450,
    'discrepancy_amount' => 50, 'status' => 'Discrepancy',
]);
$created = $cases->syncFromRemittance($remit, [
    'driver_id' => 'D1', 'business_date' => '2026-10-01',
    'expected_amount' => 500, 'actual_amount' => 450,
]);
$caseId = (int) $created['case_id'];
check($created['created'] === true && $created['case']['status'] === 'Open', 'Shortage opens a case');
check($created['case']['case_type'] === 'Cash Shortage', 'Shortage classified as Cash Shortage');
$cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Investigation started.', $officer);
$cases->saveFindings($caseId, 'Driver short by ₱50; receipt reviewed.', 'Shortage confirmed.', $officer);

/* =========================================================
 * Scenario 2: failed approval never posts
 * ========================================================= */

$payment = $ledger->propose([
    'case_id' => $caseId, 'entry_type' => 'additional_payment',
    'amount' => 20, 'reference' => 'OR-A1', 'reason' => 'Partial payment received.',
], $officer);

expectThrow(
    fn() => $ledger->decide((int) $payment['approval_id'], 'approved', 'Approve.', $officer),
    'not authorized',
    'Officer cannot approve a resolution'
);
check($db->query("SELECT status FROM discrepancy_approvals WHERE id = {$payment['approval_id']}")->fetchColumn() === 'pending', 'Failed approval leaves the request pending');
check($ledger->outstandingBalance($caseId) === 50.0, 'Failed approval leaves the balance unchanged');
check((int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Resolution Approved' AND status = 'Failed'")->fetchColumn() >= 1, 'Failed approval audited');

$ledger->decide((int) $payment['approval_id'], 'approved', 'Receipt verified.', $manager);
$ledger->post((int) $payment['entry_id'], $manager);
check($ledger->outstandingBalance($caseId) === 30.0, 'First partial recovery reduces the balance');

/* =========================================================
 * Scenario 3: partial recovery #2 counted once
 * ========================================================= */

actor($officer['role'], $officer['username']);
$payment2 = $ledger->propose([
    'case_id' => $caseId, 'entry_type' => 'additional_payment',
    'amount' => 10, 'reference' => 'OR-A2', 'reason' => 'Second partial payment.',
], $officer);
$ledger->decide((int) $payment2['approval_id'], 'approved', 'Verified.', $manager);
$ledger->post((int) $payment2['entry_id'], $manager);
check($ledger->outstandingBalance($caseId) === 20.0, 'Second partial recovery reduces the balance once');

/* =========================================================
 * Scenario 4: incorrect payment records (QR Ph mismatch)
 * ========================================================= */

$reclassified = $cases->reclassify(
    $caseId,
    DiscrepancyCaseModel::TYPE_QRPH,
    'Gateway record shows the QR Ph payment posted twice.',
    $manager
);
check($reclassified['case_type'] === DiscrepancyCaseModel::TYPE_QRPH, 'Case reclassified as QR Ph mismatch');
check((int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Case Reclassified'")->fetchColumn() === 1, 'Reclassification audited');

/* =========================================================
 * Scenario 5: duplicate submissions and duplicate proposals
 * ========================================================= */

$again = $cases->syncFromRemittance($remit, [
    'driver_id' => 'D1', 'business_date' => '2026-10-01',
    'expected_amount' => 500, 'actual_amount' => 450,
]);
check($again['reason'] === 'exists' && (int) $db->query("SELECT COUNT(*) FROM discrepancy_cases WHERE remittance_log_id = $remit")->fetchColumn() === 1, 'Duplicate case creation is a no-op');

actor($officer['role'], $officer['username']);
$waiver = $ledger->propose([
    'case_id' => $caseId, 'entry_type' => 'waiver',
    'amount' => 5, 'notes' => 'Goodwill write-off.', 'reason' => 'Goodwill write-off.',
], $officer);
expectThrow(
    fn() => $ledger->propose([
        'case_id' => $caseId, 'entry_type' => 'additional_payment',
        'amount' => 5, 'reference' => 'OR-A3',
    ], $officer),
    'already pending',
    'Duplicate pending proposal refused'
);

/* =========================================================
 * Scenario 6: approved waiver reduces the balance
 * ========================================================= */

check($ledger->outstandingBalance($caseId) === 20.0, 'Unapproved waiver leaves the balance unchanged');
$ledger->decide((int) $waiver['approval_id'], 'approved', 'Approved as goodwill.', $manager);
$ledger->post((int) $waiver['entry_id'], $manager);
check($ledger->outstandingBalance($caseId) === 15.0, 'Approved waiver reduces the balance');
check($ledger->totalsForCase($caseId)['adjustments'] === 5.0, 'Waiver counted once as an adjustment');

/* =========================================================
 * Scenario 7: late remittance reconciles on the original business date
 * ========================================================= */

actor($officer['role'], $officer['username']);
$overdueLog = DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D9', 'business_date' => '2026-09-20',
    'expected_cash' => 700, 'actual_cash' => null,
    'discrepancy_amount' => 0, 'status' => 'Overdue',
]);
$overdue = $cases->syncFromRemittance($overdueLog, [
    'driver_id' => 'D9', 'business_date' => '2026-09-20',
    'expected_amount' => 700, 'actual_amount' => null,
]);
check($overdue['case']['case_type'] === DiscrepancyCaseModel::TYPE_OVERDUE, 'Overdue flag creates an Overdue Remittance case');

$late = $cases->syncFromRemittance($overdueLog, [
    'driver_id' => 'D9', 'business_date' => '2026-09-20',
    'expected_amount' => 700, 'actual_amount' => 650,
]);
check((int) $late['case_id'] === (int) $overdue['case_id'], 'Late processing reuses the same case');
check($late['case']['case_type'] === DiscrepancyCaseModel::TYPE_SHORTAGE, 'Late mismatch reclassifies the case type');
check($late['case']['business_date'] === '2026-09-20', 'Late processing keeps the original business date');
check((int) $db->query('SELECT COUNT(*) FROM discrepancy_cases WHERE remittance_log_id = ' . $overdueLog)->fetchColumn() === 1, 'Late processing creates no duplicate case');

/* =========================================================
 * Scenario 8: reopen a closed case with a reason
 * ========================================================= */

$historyBefore = count($cases->model()->getHistory($caseId));
$ledgerBefore = count($cases->model()->getLedgerEntries($caseId));

$cases->transition($caseId, DiscrepancyCaseModel::STATUS_RESOLVED, 'Recovery and waiver complete.', $manager);
$cases->transition($caseId, DiscrepancyCaseModel::STATUS_CLOSED, 'All entries posted.', $manager);

expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, '', $manager),
    'reason is required',
    'Reopening a closed case without a reason is refused'
);
$reopened = $cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Driver disputed the waiver.', $manager);
check($reopened['status'] === DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Authorized reopen returns the case to active investigation');
check(count($cases->model()->getHistory($caseId)) === $historyBefore + 3, 'Reopen preserves history and appends');
check(count($cases->model()->getLedgerEntries($caseId)) === $ledgerBefore, 'Reopen preserves the ledger');

/* =========================================================
 * Scenario 9: excess remittance creates no driver debt
 * ========================================================= */

actor($officer['role'], $officer['username']);
$excessLog = DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D4', 'business_date' => '2026-10-05',
    'expected_cash' => 400, 'actual_cash' => 460,
    'discrepancy_amount' => 0, 'status' => 'Discrepancy',
]);
$excess = $cases->syncFromRemittance($excessLog, [
    'driver_id' => 'D4', 'business_date' => '2026-10-05',
    'expected_amount' => 400, 'actual_amount' => 460,
]);
$excessId = (int) $excess['case_id'];
check($excess['case']['case_type'] === DiscrepancyCaseModel::TYPE_EXCESS, 'Overage classified as Excess Remittance');
check((float) $excess['case']['excess_amount'] === 60.0 && (float) $excess['case']['discrepancy_amount'] === 0.0, 'Excess asserts no shortage');

$cases->transition($excessId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Excess under review.', $officer);
$cases->saveFindings($excessId, 'Cashier verified the ₱60 overage.', '', $officer);
$adjustment = $ledger->propose([
    'case_id' => $excessId, 'entry_type' => 'accounting_adjustment',
    'amount' => 60, 'reference' => 'ADJ-EXCESS', 'reason' => 'Settle the verified excess.',
], $officer);
$ledger->decide((int) $adjustment['approval_id'], 'approved', 'Verified.', $manager);
$ledger->post((int) $adjustment['entry_id'], $manager);
check($ledger->outstandingBalance($excessId) === 0.0, 'Approved adjustment settles the excess');
check((int) $db->query("SELECT COUNT(*) FROM discrepancy_ledger_entries WHERE case_id = $excessId AND recovery_class IN ('cash','payroll') AND state = 'posted'")->fetchColumn() === 0, 'Excess records no recovery against the driver');

/* =========================================================
 * Final state: metrics and audit
 * ========================================================= */

$summary = $metrics->summary([]);
check($summary['case_count'] === 3, 'Acceptance fixture ends with three cases');
check($summary['original_discrepancy'] === 100.0, 'Original discrepancy sums the two shortage snapshots only');
check($summary['recovered'] === 30.0, 'Recovered sums the two posted cash recoveries');
check($summary['approved_adjustments'] === 5.0, 'Approved adjustments exclude the excess settlement');
check($summary['outstanding_balance'] === 65.0, 'Outstanding reflects the reopened shortage and the late mismatch');

$actions = $db->query("SELECT DISTINCT action FROM audit_trail WHERE status = 'Success'")->fetchAll(PDO::FETCH_COLUMN);
foreach ([
    'Discrepancy Case Opened',
    'Discrepancy Findings Recorded',
    'Discrepancy Resolution Proposed',
    'Discrepancy Resolution Approved',
    'Discrepancy Resolution Posted',
    'Discrepancy Case Reclassified',
    'Discrepancy Case Reopened',
    'Discrepancy Mismatch Established',
] as $action) {
    check(in_array($action, $actions, true), 'Acceptance audit trail contains ' . $action);
}

echo "Discrepancy acceptance matrix passed ($checks checks)\n";
exit(0);

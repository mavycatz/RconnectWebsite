<?php
declare(strict_types=1);

/**
 * Ledger, approval, authorization, and audit tests (tasks 4.1-4.5, 5.1-5.4).
 *
 * Usage: php finance/tests/discrepancy_ledger_test.php
 * Runs against the in-memory SQLite fixture; exits non-zero on failure.
 */

date_default_timezone_set('Asia/Manila');
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/DiscrepancyFixture.php';
require_once __DIR__ . '/../services/DiscrepancyCaseService.php';
require_once __DIR__ . '/../services/DiscrepancyLedgerService.php';
require_once __DIR__ . '/../services/DiscrepancyAuthorizationService.php';
require_once __DIR__ . '/../models/DiscrepancyCaseModel.php';
require_once __DIR__ . '/../models/DiscrepancyLedgerModel.php';

use Finance\Models\DiscrepancyCaseModel;
use Finance\Services\DiscrepancyAuthorizationService;
use Finance\Services\DiscrepancyCaseService;
use Finance\Services\DiscrepancyLedgerService;

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

function actor(string $role, string $username = 'tester'): array
{
    $_SESSION['user'] = ['id' => 1, 'username' => $username, 'role' => $role];
    return ['id' => 1, 'username' => $username, 'role' => $role];
}

$db = DiscrepancyFixture::pdo();
$cases = new DiscrepancyCaseService($db);
$ledger = new DiscrepancyLedgerService($db, null, $cases);
$auth = new DiscrepancyAuthorizationService($db);

/* ---------- setup: shortage case under investigation ---------- */

actor('finance_officer', 'ofelia.officer');

$remit = DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D1',
    'business_date' => '2026-10-01',
    'expected_cash' => 500,
    'actual_cash' => 450,
    'discrepancy_amount' => 50,
    'status' => 'Discrepancy',
]);
$caseResult = $cases->syncFromRemittance($remit, [
    'driver_id' => 'D1',
    'business_date' => '2026-10-01',
    'expected_amount' => 500,
    'actual_amount' => 450,
]);
$caseId = (int) $caseResult['case_id'];
$cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Investigation started.');
$cases->saveFindings($caseId, 'Driver confirmed a ₱50 cash shortage.', 'Shortage confirmed during investigation.');

/* ---------- 5.1: default authorization rules ---------- */

check($auth->canPropose('additional_payment', 100, 'finance_officer') === true, 'Default rules let an officer propose');
check($auth->canPropose('additional_payment', 100, 'driver') === false, 'Default rules refuse unknown roles');
check($auth->canApprove('additional_payment', 100, 'finance_manager') === true, 'Default rules let a manager approve');
check($auth->canApprove('additional_payment', 100, 'finance_officer') === false, 'Default rules refuse officer approval');
check($auth->allowSelfApproval('additional_payment', 100) === false, 'Default rules disable self-approval');
check($auth->requiresApproval('waiver', 100) === true, 'Material actions require approval by default');
check($auth->canPerform('reopen', 0, 'finance_officer') === true, 'Officers may reopen by default');
check($auth->canEditRules('finance_officer') === false, 'Officers cannot edit rules');
check($auth->canEditRules('finance_manager') === true, 'Managers can edit rules');

expectThrow(
    fn() => $auth->saveRules(DiscrepancyAuthorizationService::defaultRules(), actor('finance_officer', 'ofelia.officer')),
    'not authorized',
    'Unauthorized rules update refused'
);

/* ---------- 4.1: unsupported instrument and amount guards ---------- */

expectThrow(
    fn() => $ledger->propose(['case_id' => $caseId, 'entry_type' => 'bitcoin_refund', 'amount' => 10]),
    'Unsupported resolution instrument',
    'Unsupported instrument refused'
);
expectThrow(
    fn() => $ledger->propose(['case_id' => $caseId, 'entry_type' => 'additional_payment', 'amount' => 0]),
    'greater than zero',
    'Zero amount refused'
);
expectThrow(
    fn() => $ledger->propose(['case_id' => $caseId, 'entry_type' => 'additional_payment', 'amount' => 10, 'reference' => '']),
    'reference is required',
    'Missing reference refused for additional payment'
);

/* 4.3: payroll recovery needs its authorization reference, and it is only
   recorded as a ledger entry (no automatic deduction path exists). */
expectThrow(
    fn() => $ledger->propose([
        'case_id' => $caseId,
        'entry_type' => 'payroll_recovery',
        'amount' => 10,
    ], actor('finance_officer', 'ofelia.officer')),
    'authorization reference is required',
    'Payroll recovery without authorization refused'
);
check((int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Resolution Proposed' AND status = 'Failed'")->fetchColumn() >= 1, 'Refused proposal audited as Failed');

/* ---------- 4.1 + 5.2: propose, decide, post ---------- */

actor('finance_officer', 'ofelia.officer');
$payment = $ledger->propose([
    'case_id' => $caseId,
    'entry_type' => 'additional_payment',
    'amount' => 20,
    'reference' => 'OR-1001',
], actor('finance_officer', 'ofelia.officer'));

$entryRow = $db->query("SELECT * FROM discrepancy_ledger_entries WHERE id = {$payment['entry_id']}")->fetch(PDO::FETCH_ASSOC);
check($entryRow['state'] === 'proposed', 'Proposal creates a proposed entry');
check($entryRow['recovery_class'] === 'cash', 'Additional payment is class cash');
check((int) $db->query("SELECT COUNT(*) FROM discrepancy_approvals WHERE id = {$payment['approval_id']} AND status = 'pending'")->fetchColumn() === 1, 'Proposal creates a pending approval');
check($cases->model()->getById($caseId)['status'] === DiscrepancyCaseModel::STATUS_APPROVAL, 'Proposal moves the case to Awaiting Approval');
check((int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Resolution Proposed' AND status = 'Success'")->fetchColumn() >= 1, 'Proposal audited');

expectThrow(
    fn() => $ledger->propose(['case_id' => $caseId, 'entry_type' => 'waiver', 'amount' => 1], actor('finance_officer', 'ofelia.officer')),
    'already pending',
    'Second pending proposal refused'
);
expectThrow(
    fn() => $ledger->post((int) $payment['entry_id'], actor('finance_manager', 'maria.manager')),
    'Only an approved entry',
    'Posting a proposed entry refused'
);
check($ledger->outstandingBalance($caseId) === 50.0, 'Proposed entries do not change the outstanding balance');

expectThrow(
    fn() => $ledger->decide((int) $payment['approval_id'], 'approved', 'Looks fine.', actor('finance_officer', 'ofelia.officer')),
    'not authorized',
    'Officer approval refused'
);
check($db->query("SELECT status FROM discrepancy_approvals WHERE id = {$payment['approval_id']}")->fetchColumn() === 'pending', 'Refused approval left the request pending');

expectThrow(
    fn() => $ledger->decide((int) $payment['approval_id'], 'approved', ''),
    'reason is required',
    'Decision without a reason refused'
);

/* 5.1: rules validation and configuration changes */
check(is_array($auth->validateRules(DiscrepancyAuthorizationService::defaultRules())), 'Default rules validate');
expectThrow(
    fn() => $auth->validateRules(['actions' => ['additional_payment' => ['propose_roles' => [], 'approve_roles' => []]]]),
    'non-empty',
    'Empty role lists refused'
);
$managerRules = DiscrepancyAuthorizationService::defaultRules();
$managerRules['actions']['additional_payment']['self_approval'] = true;
$managerRules['actions']['additional_payment']['propose_roles'][] = 'admin';
$saved = $auth->saveRules($managerRules, actor('finance_manager', 'maria.manager'));
check($saved['actions']['additional_payment']['self_approval'] === true, 'Manager can enable self-approval');
check($auth->allowSelfApproval('additional_payment', 20) === true, 'Changed configuration takes effect for self-approval');
check($auth->canPropose('additional_payment', 20, 'admin') === true, 'Changed configuration takes effect for proposal roles');

$managerRules['actions']['additional_payment']['self_approval'] = false;
$auth->saveRules($managerRules, actor('finance_manager', 'maria.manager'));
check($auth->allowSelfApproval('additional_payment', 20) === false, 'Self-approval disabled again by configuration');

actor('finance_officer', 'ofelia.officer');
expectThrow(
    fn() => $ledger->decide((int) $payment['approval_id'], 'approved', 'Approved.', actor('finance_manager', 'ofelia.officer')),
    'cannot approve your own proposal',
    'Self-approval refused when the proposer holds an approver role'
);

$decision = $ledger->decide((int) $payment['approval_id'], 'approved', 'Receipt verified.', actor('finance_manager', 'maria.manager'));
check($decision['state'] === 'approved', 'Manager approval moves the entry to approved');
check($cases->model()->getById($caseId)['status'] === DiscrepancyCaseModel::STATUS_RECOVERY, 'Approval moves the case to Awaiting Recovery');
check((int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Resolution Approved' AND status = 'Success'")->fetchColumn() === 1, 'Approval audited');

$posted = $ledger->post((int) $payment['entry_id'], actor('finance_manager', 'maria.manager'));
check($posted['state'] === 'posted', 'Approved entry posts');
check($posted['outstanding_before'] === 50.0 && $posted['outstanding_after'] === 30.0, 'Posting records before and after balances');
check((int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Resolution Posted'")->fetchColumn() === 1, 'Posting audited');

expectThrow(
    fn() => $ledger->post((int) $payment['entry_id'], actor('finance_manager', 'maria.manager')),
    'Only an approved entry',
    'Double posting refused'
);

/* partial recovery #2 counted once */
$second = $ledger->propose([
    'case_id' => $caseId,
    'entry_type' => 'additional_payment',
    'amount' => 10,
    'reference' => 'OR-1002',
], actor('finance_officer', 'ofelia.officer'));
$ledger->decide((int) $second['approval_id'], 'approved', 'Verified.', actor('finance_manager', 'maria.manager'));
$ledger->post((int) $second['entry_id'], actor('finance_manager', 'maria.manager'));
check($ledger->outstandingBalance($caseId) === 20.0, 'Multiple partial recoveries each reduce the balance once');

/* reject never posts */
$third = $ledger->propose([
    'case_id' => $caseId,
    'entry_type' => 'source_correction',
    'amount' => 15,
    'reference' => 'COR-77',
], actor('finance_officer', 'ofelia.officer'));
$rejected = $ledger->decide((int) $third['approval_id'], 'rejected', 'Correction not verified.', actor('finance_manager', 'maria.manager'));
check($rejected['state'] === 'rejected', 'Rejection marks the entry rejected');
check($ledger->outstandingBalance($caseId) === 20.0, 'Rejected entry never affects the balance');
check($cases->model()->getById($caseId)['status'] === DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Rejection returns the case to investigation');
check((int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Resolution Rejected'")->fetchColumn() === 1, 'Rejection audited');

/* 4.1 + 4.2: void excludes a posted entry */
$void = $ledger->void((int) $second['entry_id'], 'Duplicate receipt entry.', actor('finance_manager', 'maria.manager'));
check($void['state'] === 'voided', 'Posted entry can be voided with a reason');
check($ledger->outstandingBalance($caseId) === 30.0, 'Voided entries are excluded from the balance');
expectThrow(
    fn() => $ledger->void((int) $second['entry_id'], ''),
    'reason is required',
    'Void without a reason refused'
);

/* 4.3: approved waiver reduces the balance */
$waiver = $ledger->propose([
    'case_id' => $caseId,
    'entry_type' => 'waiver',
    'amount' => 5,
    'notes' => 'Goodwill write-off proposed.',
], actor('finance_officer', 'ofelia.officer'));
check($ledger->outstandingBalance($caseId) === 30.0, 'Unapproved waiver does not affect the balance');
$ledger->decide((int) $waiver['approval_id'], 'approved', 'Approved as goodwill.', actor('finance_manager', 'maria.manager'));
$ledger->post((int) $waiver['entry_id'], actor('finance_manager', 'maria.manager'));
check($ledger->outstandingBalance($caseId) === 25.0, 'Approved waiver reduces the outstanding balance');

$totals = $ledger->totalsForCase($caseId);
check($totals['cash_received'] === 20.0, 'Cash received totals only posted cash entries');
check($totals['adjustments'] === 5.0, 'Adjustments total posted waivers');
check($totals['payroll_recovery'] === 0.0, 'No payroll recovery recorded without authorization');

/* ---------- 4.5: verified correction preserves originals ---------- */

$correction = $ledger->propose([
    'case_id' => $caseId,
    'entry_type' => 'source_correction',
    'amount' => 5,
    'reference' => 'COR-88',
    'notes' => 'Trip fare record corrected; original retained.',
], actor('finance_officer', 'ofelia.officer'));
$ledger->decide((int) $correction['approval_id'], 'approved', 'Verified against the stored trip.', actor('finance_manager', 'maria.manager'));

$remitBefore = $db->query("SELECT * FROM remittance_logs WHERE id = $remit")->fetch(PDO::FETCH_ASSOC);
$caseBefore = $cases->model()->getById($caseId);
$ledger->post((int) $correction['entry_id'], actor('finance_manager', 'maria.manager'));
$remitAfter = $db->query("SELECT * FROM remittance_logs WHERE id = $remit")->fetch(PDO::FETCH_ASSOC);
$caseAfter = $cases->model()->getById($caseId);

check($remitBefore === $remitAfter, 'Source-record correction never edits the original remittance row');
check((float) $caseBefore['expected_amount'] === (float) $caseAfter['expected_amount']
    && (float) $caseBefore['actual_amount'] === (float) $caseAfter['actual_amount']
    && (float) $caseBefore['discrepancy_amount'] === (float) $caseAfter['discrepancy_amount'],
    'Source-record correction never rewrites the original amounts');
check($ledger->outstandingBalance($caseId) === 20.0, 'Posted correction reduces the balance');

/* ---------- 5.3: reopen a closed case ---------- */

$cases->transition($caseId, DiscrepancyCaseModel::STATUS_RESOLVED, 'Recovery complete.', actor('finance_manager', 'maria.manager'));
$cases->transition($caseId, DiscrepancyCaseModel::STATUS_CLOSED, 'Case closed.', actor('finance_manager', 'maria.manager'));
$historyBefore = count($cases->model()->getHistory($caseId));
$ledgerBefore = count($cases->model()->getLedgerEntries($caseId));

expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, '', actor('finance_manager', 'maria.manager')),
    'reason is required',
    'Reopen without a reason refused'
);
$reopened = $cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'New evidence filed.', actor('finance_manager', 'maria.manager'));
check($reopened['status'] === DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Authorized reopen returns the case to investigation');
check(count($cases->model()->getHistory($caseId)) === $historyBefore + 1, 'Reopen preserves prior history and appends');
check(count($cases->model()->getLedgerEntries($caseId)) === $ledgerBefore, 'Reopen preserves every ledger row');

/* ---------- 5.4: audit completeness and chronology ---------- */

$ref = $cases->refFor($caseId);
$actions = $db->query("SELECT DISTINCT action FROM audit_trail WHERE record_id = '$ref' ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
foreach ([
    'Discrepancy Case Opened',
    'Discrepancy Findings Recorded',
    'Discrepancy Resolution Proposed',
    'Discrepancy Resolution Approved',
    'Discrepancy Resolution Rejected',
    'Discrepancy Resolution Posted',
    'Discrepancy Resolution Voided',
    'Discrepancy Case Reopened',
] as $action) {
    check(in_array($action, $actions, true), 'Audit trail contains ' . $action);
}

$postedAudit = $db->query("SELECT new_values FROM audit_trail WHERE action = 'Discrepancy Resolution Posted' ORDER BY id ASC LIMIT 1")->fetchColumn();
$postedValues = json_decode((string) $postedAudit, true);
check(isset($postedValues['outstanding_balance'], $postedValues['amount'], $postedValues['entry_id']), 'Posted audit records amount and resulting balance');

$approvedAudit = $db->query("SELECT new_values FROM audit_trail WHERE action = 'Discrepancy Resolution Approved' AND status = 'Success' ORDER BY id ASC LIMIT 1")->fetchColumn();
$approvedValues = json_decode((string) $approvedAudit, true);
check(isset($approvedValues['approver'], $approvedValues['reason'], $approvedValues['config_reference']), 'Approval audit records approver, reason, and config reference');

$timeline = $cases->buildTimeline($caseId);
$ats = array_column($timeline, 'at');
$sorted = $ats;
sort($sorted);
check($ats === $sorted, 'Case timeline is chronological');
$kinds = array_unique(array_column($timeline, 'kind'));
check(in_array('history', $kinds, true) && in_array('ledger', $kinds, true) && in_array('approval', $kinds, true), 'Timeline includes status, ledger, and approval events');

/* ---------- 4.4: excess remittance resolution ---------- */

actor('finance_officer', 'ofelia.officer');
$excessRemit = DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D2',
    'business_date' => '2026-10-03',
    'expected_cash' => 400,
    'actual_cash' => 460,
    'discrepancy_amount' => 0,
    'status' => 'Discrepancy',
]);
$excessCase = $cases->syncFromRemittance($excessRemit, [
    'driver_id' => 'D2',
    'business_date' => '2026-10-03',
    'expected_amount' => 400,
    'actual_amount' => 460,
]);
$excessId = (int) $excessCase['case_id'];
$cases->transition($excessId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Excess under review.');
$cases->saveFindings($excessId, 'Driver remitted ₱60 above expected; cashier verified the overage.');
$excessOutstandingBefore = $ledger->outstandingBalance($excessId);
check($excessOutstandingBefore === 60.0, 'Excess case exposure tracks the excess amount');

$excessEntry = $ledger->propose([
    'case_id' => $excessId,
    'entry_type' => 'accounting_adjustment',
    'amount' => 60,
    'reference' => 'ADJ-EXCESS-1',
], actor('finance_officer', 'ofelia.officer'));
$ledger->decide((int) $excessEntry['approval_id'], 'approved', 'Excess verified.', actor('finance_manager', 'maria.manager'));
$ledger->post((int) $excessEntry['entry_id'], actor('finance_manager', 'maria.manager'));
check($ledger->outstandingBalance($excessId) === 0.0, 'Approved instrument reconciles the excess');

$excessTotals = $ledger->totalsForCase($excessId);
check($excessTotals['cash_received'] === 0.0, 'Excess resolution records no cash recovery against the driver');
check($excessTotals['payroll_recovery'] === 0.0, 'Excess resolution records no payroll deduction');
check((int) $db->query("SELECT COUNT(*) FROM discrepancy_ledger_entries WHERE case_id = $excessId AND recovery_class IN ('cash','payroll') AND state = 'posted'")->fetchColumn() === 0, 'No recovery ledger rows exist for the excess case');

require_once __DIR__ . '/../services/DiscrepancyMetricsService.php';
use Finance\Services\DiscrepancyMetricsService;

/* ---------- 4.2: shared metrics ---------- */

$metrics = new DiscrepancyMetricsService($db, $cases);
$summary = $metrics->summary([]);
check($summary['case_count'] === 2, 'Metrics count shortage and excess cases');
check($summary['original_discrepancy'] === 50.0, 'Original discrepancy sums shortage snapshots only');
check($summary['recovered'] === 20.0, 'Recovered sums posted cash entries only');
check($summary['approved_adjustments'] === 5.0, 'Approved adjustments sum posted waivers and adjustments');
check($summary['outstanding_balance'] === 20.0, 'Outstanding balance excludes voided, rejected, proposed, and excess activity');
check($summary['pending_approvals'] === 0, 'No pending approvals remain');
check($summary['overdue_investigations'] === 0, 'Fresh cases are not flagged overdue');

$byDriver = $metrics->summary(['driver' => 'D1']);
check($byDriver['case_count'] === 1 && $byDriver['original_discrepancy'] === 50.0, 'Driver filter narrows the metrics');

$byType = $metrics->summary(['case_type' => 'Excess Remittance']);
check($byType['case_count'] === 1, 'Type filter selects excess cases');
check($byType['original_discrepancy'] === 0.0 && $byType['recovered'] === 0.0 && $byType['outstanding_balance'] === 0.0, 'Excess activity never inflates deficiency metrics');

$exportRows = $metrics->exportRows(['status' => DiscrepancyCaseModel::STATUS_INVESTIGATING]);
check(count($exportRows) === 1 && $exportRows[0]['outstanding_balance'] === 20.0, 'Export rows carry server-computed balances and honor filters');
check(strpos($metrics->describeFilters(['driver' => 'D1', 'age' => '4-7']), 'Driver: D1') !== false, 'Filter description names the applied filters');

echo "Discrepancy ledger tests passed ($checks checks)\n";
exit(0);

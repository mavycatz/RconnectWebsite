<?php
declare(strict_types=1);

/**
 * Investigation and approval workflow tests: Finance Officer prepares
 * findings; Finance Manager reviews and decides. Verifies role separation,
 * the new lifecycle statuses, self-approval prevention, audit records, and
 * amount immutability.
 *
 * Usage: php finance/tests/discrepancy_workflow_test.php
 */

date_default_timezone_set('Asia/Manila');
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/DiscrepancyFixture.php';
require_once __DIR__ . '/../services/DiscrepancyCaseService.php';
require_once __DIR__ . '/../models/DiscrepancyCaseModel.php';

use Finance\Models\DiscrepancyCaseModel;
use Finance\Services\DiscrepancyCaseService;

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

function asUser(string $role, string $username): array
{
    $_SESSION['user'] = ['id' => 1, 'username' => $username, 'role' => $role];
    return ['id' => 1, 'username' => $username, 'role' => $role];
}

$officer = asUser('finance_officer', 'ofelia.officer');
$manager = asUser('finance_manager', 'maria.manager');

$db = DiscrepancyFixture::pdo();
$cases = new DiscrepancyCaseService($db);

/* ---------- setup ---------- */

$remit = DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D1',
    'business_date' => '2026-10-01',
    'expected_cash' => 500,
    'actual_cash' => 450,
    'discrepancy_amount' => 50,
    'status' => 'Discrepancy',
]);
$created = $cases->syncFromRemittance($remit, [
    'driver_id' => 'D1',
    'business_date' => '2026-10-01',
    'expected_amount' => 500,
    'actual_amount' => 450,
]);
$caseId = (int) $created['case_id'];
$snapshot = $cases->model()->getById($caseId);

/* ---------- officer investigation ---------- */

asUser($officer['role'], $officer['username']);
$cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Investigation started.', $officer);
$cases->saveFindings($caseId, 'Reviewed the trip receipts; ₱50 cash is unaccounted for.', 'Shortage confirmed.', $officer);

$driver = $cases->transition($caseId, DiscrepancyCaseModel::STATUS_DRIVER_RESPONSE, 'Requested the driver explanation.', $officer);
check($driver['status'] === DiscrepancyCaseModel::STATUS_DRIVER_RESPONSE, 'Officer can request a driver explanation');

expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_RESOLVED, 'Resolve it.', $officer),
    'Only a Finance Manager',
    'Officer cannot resolve a case'
);
expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_CLOSED, 'Close it.', $officer),
    'Only a Finance Manager',
    'Officer cannot close a case'
);

/* ---------- officer submits findings ---------- */

expectThrow(
    fn() => $cases->submitFindingsForApproval($caseId, 'Findings', '', '', $officer),
    'recommended resolution is required',
    'Submission without a recommendation refused'
);
expectThrow(
    fn() => $cases->submitFindingsForApproval($caseId, '', 'Recommend adjustment', '', $officer),
    'findings are required',
    'Submission without findings refused'
);

$submitted = $cases->submitFindingsForApproval(
    $caseId,
    'Driver confirmed ₱50 was used for an unrecorded expense.',
    'Recover ₱50 from the next remittance.',
    'Driver cooperative; receipts attached.',
    $officer
);
check($submitted['status'] === DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED, 'Submission moves the case to Findings Submitted');
check($submitted['findings_submitted_by'] === 'ofelia.officer', 'Submission records the investigator');
check($submitted['findings_submitted_at'] !== null, 'Submission timestamp recorded');
check($submitted['resolution_reason'] === 'Recover ₱50 from the next remittance.', 'Recommended resolution stored');
check((int) $db->query("SELECT COUNT(*) FROM discrepancy_status_history WHERE case_id = $caseId AND event_type = 'findings_submitted'")->fetchColumn() === 1, 'Submission recorded in history');
check((int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Findings Submitted' AND status = 'Success'")->fetchColumn() === 1, 'Submission audited');

/* Officer cannot start the manager review or decide. */
expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL, 'Review.', $officer),
    'Only a Finance Manager',
    'Officer cannot start the manager review'
);
expectThrow(
    fn() => $cases->managerDecision($caseId, 'approved', 'Looks good.', $officer),
    'Only a Finance Manager can decide',
    'Officer cannot decide submitted findings'
);

/* ---------- manager review + return ---------- */

$review = $cases->transition($caseId, DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL, 'Manager review started.', $manager);
check($review['status'] === DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL, 'Manager starts the approval review');

expectThrow(
    fn() => $cases->managerDecision($caseId, 'maybe', 'Hmm.', $manager),
    'must be approved, returned, or rejected',
    'Unknown decision refused'
);
expectThrow(
    fn() => $cases->managerDecision($caseId, 'approved', '', $manager),
    'documented reason is required',
    'Decision without a reason refused'
);

$returned = $cases->managerDecision($caseId, 'returned', 'Attach the expense receipt before approval.', $manager);
check($returned['status'] === DiscrepancyCaseModel::STATUS_RETURNED, 'Return moves the case to Returned for Further Investigation');
check($returned['manager_decision'] === 'returned', 'Return decision recorded');
check($returned['manager_decision_by'] === 'maria.manager', 'Approver recorded on the decision');
check($returned['manager_decision_reason'] === 'Attach the expense receipt before approval.', 'Decision reason recorded');
check((int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Manager Returned' AND status = 'Success'")->fetchColumn() === 1, 'Return audited');

/* ---------- officer continues and resubmits; manager rejects ---------- */

asUser($officer['role'], $officer['username']);
$continued = $cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Continuing the investigation.', $officer);
check($continued['status'] === DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Returned case can continue under investigation');

$resubmitted = $cases->submitFindingsForApproval(
    $caseId,
    'Receipt obtained; the expense was personal and not business related.',
    'Recover the full ₱50 by payroll.',
    '',
    $officer
);
check($resubmitted['status'] === DiscrepancyCaseModel::STATUS_FINDINGS_SUBMITTED, 'Findings can be resubmitted after a return');

$cases->transition($caseId, DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL, 'Manager review started.', $manager);
$rejected = $cases->managerDecision($caseId, 'rejected', 'Payroll recovery is not permitted under policy.', $manager);
check($rejected['status'] === DiscrepancyCaseModel::STATUS_RETURNED, 'Rejection returns the case for further investigation');
check($rejected['manager_decision'] === 'rejected', 'Rejection decision recorded');

/* ---------- final approval, closure, reopen ---------- */

asUser($officer['role'], $officer['username']);
$cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Investigation reopened after rejection.', $officer);
$cases->submitFindingsForApproval($caseId, 'Final findings: ₱50 recoverable as a cash remittance.', 'Accept a ₱50 additional payment.', '', $officer);

$cases->transition($caseId, DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL, 'Manager review started.', $manager);
$approved = $cases->managerDecision($caseId, 'approved', 'Additional payment accepted.', $manager);
check($approved['status'] === DiscrepancyCaseModel::STATUS_RESOLVED, 'Approval finalizes the case as Resolved');
check($approved['resolved_at'] !== null, 'Resolution timestamp recorded on approval');
check($approved['manager_decision'] === 'approved', 'Approval decision recorded');
check((int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Manager Approved' AND status = 'Success'")->fetchColumn() === 1, 'Approval audited');

$closed = $cases->transition($caseId, DiscrepancyCaseModel::STATUS_CLOSED, 'Recovery confirmed and closed.', $manager);
check($closed['status'] === DiscrepancyCaseModel::STATUS_CLOSED, 'Manager closes a resolved case');

asUser($officer['role'], $officer['username']);
expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Reopen please.', $officer),
    'Only a Finance Manager',
    'Officer cannot reopen a finalized case'
);
$reopened = $cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'New evidence received.', $manager);
check($reopened['status'] === DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Manager reopens a finalized case with a reason');

/* ---------- self-approval prevention ---------- */

$managerSubmit = $cases->submitFindingsForApproval(
    $caseId,
    'Manager-recorded findings for the reopened case.',
    'Recommend a waiver.',
    '',
    $manager
);
check($managerSubmit['findings_submitted_by'] === 'maria.manager', 'Manager submission records the manager as investigator');
$cases->transition($caseId, DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL, 'Manager review started.', $manager);

expectThrow(
    fn() => $cases->managerDecision($caseId, 'approved', 'Approving my own work.', $manager),
    'cannot decide on findings you submitted yourself',
    'Manager cannot decide their own submitted findings'
);
check((int) $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Manager Approved' AND status = 'Failed'")->fetchColumn() >= 1, 'Refused self-approval audited as Failed');
check($cases->model()->getById($caseId)['status'] === DiscrepancyCaseModel::STATUS_PENDING_MANAGER_APPROVAL, 'Refused self-approval left the case pending');

/* A different manager completes the decision. */
$secondManager = asUser('finance_manager', 'jose.manager');
$final = $cases->managerDecision($caseId, 'approved', 'Independently reviewed and approved.', $secondManager);
check($final['status'] === DiscrepancyCaseModel::STATUS_RESOLVED, 'A second manager can approve the submission');
check($final['manager_decision_by'] === 'jose.manager', 'The acting approver is recorded');

/* ---------- financial integrity ---------- */

$finalCase = $cases->model()->getById($caseId);
check((float) $finalCase['expected_amount'] === (float) $snapshot['expected_amount'], 'Original expected amount preserved through the workflow');
check((float) $finalCase['actual_amount'] === (float) $snapshot['actual_amount'], 'Original actual amount preserved through the workflow');
check((float) $finalCase['discrepancy_amount'] === (float) $snapshot['discrepancy_amount'], 'Original shortage preserved through the workflow');
check((float) $finalCase['excess_amount'] === (float) $snapshot['excess_amount'], 'Original excess preserved through the workflow');

$actions = $db->query("SELECT DISTINCT action FROM audit_trail WHERE status = 'Success'")->fetchAll(PDO::FETCH_COLUMN);
foreach ([
    'Discrepancy Findings Submitted',
    'Discrepancy Manager Returned',
    'Discrepancy Manager Rejected',
    'Discrepancy Manager Approved',
    'Discrepancy Case Reopened',
] as $action) {
    check(in_array($action, $actions, true), 'Workflow audit trail contains ' . $action);
}

echo "Discrepancy workflow tests passed ($checks checks)\n";
exit(0);

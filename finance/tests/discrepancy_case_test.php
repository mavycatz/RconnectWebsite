<?php
declare(strict_types=1);

/**
 * Discrepancy case lifecycle tests (tasks 2.1-2.3).
 *
 * Usage: php finance/tests/discrepancy_case_test.php
 * Runs against the in-memory SQLite fixture; exits non-zero on failure.
 */

date_default_timezone_set('Asia/Manila');
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/DiscrepancyFixture.php';
require_once __DIR__ . '/../services/DiscrepancyCaseService.php';
require_once __DIR__ . '/../models/DiscrepancyCaseModel.php';

use Finance\Models\DiscrepancyCaseModel;
use Finance\Services\DiscrepancyCaseService;

$_SESSION['user'] = ['id' => 7, 'username' => 'finance.tester', 'role' => 'finance_officer'];

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

$db = DiscrepancyFixture::pdo();
$cases = new DiscrepancyCaseService($db);

$today = DiscrepancyCaseService::manilaToday();

/* ---------- creation ---------- */

$remit = DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D1',
    'business_date' => '2026-10-01',
    'expected_cash' => 500,
    'actual_cash' => 450,
    'discrepancy_amount' => 50,
    'status' => 'Discrepancy',
]);

$result = $cases->syncFromRemittance($remit, [
    'driver_id' => 'D1',
    'business_date' => '2026-10-01',
    'expected_amount' => 500,
    'actual_amount' => 450,
]);
check($result['created'] === true, 'Shortage remittance creates a case');
check($result['case']['case_type'] === DiscrepancyCaseModel::TYPE_SHORTAGE, 'Shortage classifies as Cash Shortage');
check($result['case']['status'] === DiscrepancyCaseModel::STATUS_OPEN, 'New cases start Open');
check($result['case']['discrepancy_amount'] == 50.0, 'Shortage snapshot stored');
check($result['case']['actual_amount'] == 450.0, 'Actual snapshot stored');
$expectedDue = (new DateTimeImmutable($today, new DateTimeZone('Asia/Manila')))->modify('+3 days')->format('Y-m-d');
check($result['case']['due_date'] === $expectedDue, 'Due date defaults to opened + 3 days');
check($result['case']['case_ref'] === 'DIS-' . str_pad((string) $result['case_id'], 5, '0', STR_PAD_LEFT), 'Case ref is DIS-#####');

/* idempotent sync */
$again = $cases->syncFromRemittance($remit, [
    'driver_id' => 'D1',
    'business_date' => '2026-10-01',
    'expected_amount' => 500,
    'actual_amount' => 450,
]);
check($again['created'] === false && $again['reason'] === 'exists', 'Repeated sync creates no duplicate case');
check((int) $db->query('SELECT COUNT(*) FROM discrepancy_cases')->fetchColumn() === 1, 'Exactly one case for the remittance');
check((int) $db->query('SELECT COUNT(*) FROM discrepancy_cases WHERE remittance_log_id = ' . $remit)->fetchColumn() === 1, 'Case keyed to its remittance log');

/* balanced remittance creates nothing */
$balanced = DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D2',
    'business_date' => '2026-10-01',
    'expected_cash' => 300,
    'actual_cash' => 300,
    'discrepancy_amount' => 0,
    'status' => 'Completed',
]);
$balancedResult = $cases->syncFromRemittance($balanced, [
    'driver_id' => 'D2',
    'business_date' => '2026-10-01',
    'expected_amount' => 300,
    'actual_amount' => 300,
]);
check($balancedResult['created'] === false && $balancedResult['reason'] === 'balanced', 'Balanced remittance creates no case');

/* excess */
$excess = DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D3',
    'business_date' => '2026-10-02',
    'expected_cash' => 400,
    'actual_cash' => 460,
    'discrepancy_amount' => 0,
    'status' => 'Discrepancy',
]);
$excessResult = $cases->syncFromRemittance($excess, [
    'driver_id' => 'D3',
    'business_date' => '2026-10-02',
    'expected_amount' => 400,
    'actual_amount' => 460,
]);
check($excessResult['created'] === true, 'Excess remittance creates a case');
check($excessResult['case']['case_type'] === DiscrepancyCaseModel::TYPE_EXCESS, 'Excess classifies as Excess Remittance');
check((float) $excessResult['case']['excess_amount'] === 60.0, 'Excess amount stored');
check((float) $excessResult['case']['discrepancy_amount'] === 0.0, 'Excess case asserts no shortage');

/* ---------- transition map ---------- */

$caseId = (int) $result['case_id'];

expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_RECOVERY, 'skip ahead'),
    'Invalid status transition',
    'Invalid transition refused'
);
check($cases->model()->getById($caseId)['status'] === DiscrepancyCaseModel::STATUS_OPEN, 'Refused transition left the status unchanged');

$moved = $cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, null);
check($moved['status'] === DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Open -> Under Investigation allowed without a reason');

expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_RESOLVED, ''),
    'reason is required',
    'Resolved without a reason refused'
);
check($cases->model()->getById($caseId)['status'] === DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Reason-less resolve left the status unchanged');

expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_RESOLVED, 'Driver paid the shortage.'),
    'Only a Finance Manager',
    'Officer cannot resolve a case'
);
expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_CLOSED, 'Close it.'),
    'Only a Finance Manager',
    'Officer cannot close a case'
);
check($cases->model()->getById($caseId)['status'] === DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Refused resolution left the status unchanged');

/* Resolving, closing, and reopening are manager-only. */
$_SESSION['user'] = ['id' => 8, 'username' => 'maria.manager', 'role' => 'finance_manager'];

$resolved = $cases->transition($caseId, DiscrepancyCaseModel::STATUS_RESOLVED, 'Driver paid the shortage.');
check($resolved['status'] === DiscrepancyCaseModel::STATUS_RESOLVED, 'Under Investigation -> Resolved with a reason allowed');
check($resolved['resolved_at'] !== null, 'Resolved timestamp recorded');
$mirrored = $db->query("SELECT status, resolved_by FROM remittance_logs WHERE id = $remit")->fetch(PDO::FETCH_ASSOC);
check(strtolower($mirrored['status']) === 'resolved', 'Resolution mirrors to the legacy remittance status');
check($mirrored['resolved_by'] === 'maria.manager', 'Resolution mirrors the acting user to the legacy columns');

$closed = $cases->transition($caseId, DiscrepancyCaseModel::STATUS_CLOSED, 'Recovery posted and verified.');
check($closed['status'] === DiscrepancyCaseModel::STATUS_CLOSED, 'Resolved -> Closed with a reason allowed');
check($closed['closed_at'] !== null, 'Closed timestamp recorded');

expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, ''),
    'reason is required',
    'Reopen without a reason refused'
);
check($cases->model()->getById($caseId)['status'] === DiscrepancyCaseModel::STATUS_CLOSED, 'Reason-less reopen left the case closed');

$_SESSION['user'] = ['id' => 7, 'username' => 'finance.tester', 'role' => 'finance_officer'];
expectThrow(
    fn() => $cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Reopen please.'),
    'Only a Finance Manager',
    'Officer cannot reopen a finalized case'
);

$_SESSION['user'] = ['id' => 8, 'username' => 'maria.manager', 'role' => 'finance_manager'];
$reopened = $cases->transition($caseId, DiscrepancyCaseModel::STATUS_INVESTIGATING, 'New evidence received.');
check($reopened['status'] === DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Closed -> Under Investigation with a reason allowed');
check((int) $db->query("SELECT COUNT(*) FROM discrepancy_status_history WHERE case_id = $caseId AND event_type = 'reopened'")->fetchColumn() === 1, 'Reopen recorded in history');
$restored = $db->query("SELECT status, resolved_by FROM remittance_logs WHERE id = $remit")->fetch(PDO::FETCH_ASSOC);
check(strtolower($restored['status']) === 'discrepancy', 'Reopening restores the legacy remittance status');
check($restored['resolved_by'] === null, 'Reopening clears the legacy resolution columns');

$_SESSION['user'] = ['id' => 7, 'username' => 'finance.tester', 'role' => 'finance_officer'];

/* ---------- immutability ---------- */

$before = $cases->model()->getById($caseId);
$cases->model()->updateCase($caseId, [
    'expected_amount' => 9999,
    'actual_amount' => 1,
    'discrepancy_amount' => 1234,
]);
$after = $cases->model()->getById($caseId);
check((float) $after['expected_amount'] === (float) $before['expected_amount'], 'Expected amount cannot be rewritten');
check((float) $after['actual_amount'] === (float) $before['actual_amount'], 'Actual amount cannot be rewritten');
check((float) $after['discrepancy_amount'] === (float) $before['discrepancy_amount'], 'Discrepancy amount cannot be rewritten');

/* ---------- assignment + timing ---------- */

$assigned = $cases->assign($caseId, 'maria.manager', null);
check($assigned['assigned_reviewer'] === 'maria.manager', 'Reviewer stored');
check($assigned['status'] === DiscrepancyCaseModel::STATUS_INVESTIGATING, 'Assignment keeps the case under investigation');
check((int) $db->query("SELECT COUNT(*) FROM discrepancy_status_history WHERE case_id = $caseId AND event_type = 'assigned'")->fetchColumn() === 1, 'Assignment recorded in history');

$details = $cases->getDetails($caseId);
check($details['next_action'] === 'Record findings', 'Next action reflects the status');
check($details['age_days'] >= 0, 'Case age computed');
check($details['case']['assigned_reviewer'] === 'maria.manager', 'Details show the assigned reviewer');

/* invalid reviewer */
expectThrow(
    fn() => $cases->assign($caseId, '   '),
    'reviewer is required',
    'Empty reviewer refused'
);

/* ---------- findings ---------- */

expectThrow(
    fn() => $cases->saveFindings($caseId, '   '),
    'findings are required',
    'Empty findings refused'
);

$withFindings = $cases->saveFindings($caseId, 'QR Ph payment was recorded twice; fare record is correct.', 'Corrected payment records.');
check($withFindings['findings'] !== null, 'Findings stored');
check((int) $db->query("SELECT COUNT(*) FROM discrepancy_status_history WHERE case_id = $caseId AND event_type = 'findings'")->fetchColumn() === 1, 'Findings recorded in history');

/* ---------- reclassification ---------- */

expectThrow(
    fn() => $cases->reclassify($caseId, DiscrepancyCaseModel::TYPE_SYSTEM, ''),
    'reason is required',
    'Reclassification without a reason refused'
);
$reclassified = $cases->reclassify($caseId, DiscrepancyCaseModel::TYPE_QRPH, 'Verified a QR Ph payment mismatch.');
check($reclassified['case_type'] === DiscrepancyCaseModel::TYPE_QRPH, 'Case reclassified with a reason');

/* ---------- late remittance on an overdue case ---------- */

$overdue = DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D9',
    'business_date' => '2026-09-20',
    'expected_cash' => 700,
    'actual_cash' => null,
    'discrepancy_amount' => 0,
    'status' => 'Overdue',
]);
$overdueResult = $cases->syncFromRemittance($overdue, [
    'driver_id' => 'D9',
    'business_date' => '2026-09-20',
    'expected_amount' => 700,
    'actual_amount' => null,
]);
check($overdueResult['case']['case_type'] === DiscrepancyCaseModel::TYPE_OVERDUE, 'Overdue flag creates an Overdue Remittance case');
check((float) $overdueResult['case']['discrepancy_amount'] === 0.0, 'Overdue case asserts no shortage');
check($overdueResult['case']['business_date'] === '2026-09-20', 'Overdue case keeps the original business date');

$late = $cases->syncFromRemittance($overdue, [
    'driver_id' => 'D9',
    'business_date' => '2026-09-20',
    'expected_amount' => 700,
    'actual_amount' => 650,
]);
check($late['created'] === false && $late['reason'] === 'mismatch_established', 'Late remittance establishes the mismatch on the same case');
check((int) $late['case_id'] === (int) $overdueResult['case_id'], 'Late processing reuses the overdue case');
check($late['case']['case_type'] === DiscrepancyCaseModel::TYPE_SHORTAGE, 'Late shortage reclassifies the case');
check((float) $late['case']['discrepancy_amount'] === 50.0, 'Late mismatch snapshot stored');
check($late['case']['business_date'] === '2026-09-20', 'Late processing preserves the original business date');
check((int) $db->query('SELECT COUNT(*) FROM discrepancy_cases WHERE remittance_log_id = ' . $overdue)->fetchColumn() === 1, 'No duplicate case after late processing');

$lateAgain = $cases->syncFromRemittance($overdue, [
    'driver_id' => 'D9',
    'business_date' => '2026-09-20',
    'expected_amount' => 700,
    'actual_amount' => 650,
]);
check($lateAgain['reason'] === 'exists', 'Repeated late processing is a no-op');

/* balanced late remittance resolves the overdue case */
$overdue2 = DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D10',
    'business_date' => '2026-09-21',
    'expected_cash' => 200,
    'actual_cash' => null,
    'discrepancy_amount' => 0,
    'status' => 'Overdue',
]);
$overdueCase = $cases->syncFromRemittance($overdue2, [
    'driver_id' => 'D10',
    'business_date' => '2026-09-21',
    'expected_amount' => 200,
    'actual_amount' => null,
]);
$lateBalanced = $cases->syncFromRemittance($overdue2, [
    'driver_id' => 'D10',
    'business_date' => '2026-09-21',
    'expected_amount' => 200,
    'actual_amount' => 200,
]);
check($lateBalanced['reason'] === 'late_balanced', 'Balanced late remittance resolves the overdue case');
check($lateBalanced['case']['status'] === DiscrepancyCaseModel::STATUS_RESOLVED, 'Overdue case resolved when the late remittance balances');

/* ---------- backfill ---------- */

$legacyDb = DiscrepancyFixture::pdo();
$legacyCases = new DiscrepancyCaseService($legacyDb);
DiscrepancyFixture::seedRemittanceLog($legacyDb, ['driver_id' => 'L1', 'business_date' => '2026-08-01', 'expected_cash' => 100, 'actual_cash' => 80, 'discrepancy_amount' => 20, 'status' => 'Discrepancy']);
DiscrepancyFixture::seedRemittanceLog($legacyDb, ['driver_id' => 'L2', 'business_date' => '2026-08-02', 'expected_cash' => 100, 'actual_cash' => 130, 'discrepancy_amount' => 0, 'status' => 'Discrepancy']);
DiscrepancyFixture::seedRemittanceLog($legacyDb, ['driver_id' => 'L3', 'business_date' => '2026-08-03', 'expected_cash' => 100, 'actual_cash' => null, 'discrepancy_amount' => 0, 'status' => 'Overdue']);
DiscrepancyFixture::seedRemittanceLog($legacyDb, ['driver_id' => 'L4', 'business_date' => '2026-08-04', 'expected_cash' => 100, 'actual_cash' => 90, 'discrepancy_amount' => 10, 'status' => 'Resolved', 'resolution_type' => 'Driver Paid Shortage', 'resolution_notes' => 'Paid in cash', 'resolved_at' => '2026-08-05 10:00:00']);
DiscrepancyFixture::seedRemittanceLog($legacyDb, ['driver_id' => 'L5', 'business_date' => '2026-08-05', 'expected_cash' => 100, 'actual_cash' => 100, 'discrepancy_amount' => 0, 'status' => 'Completed']);

$dry = $legacyCases->backfillLegacyCases(false);
check($dry['scanned'] === 4, 'Dry run scans shortage, excess, overdue, and resolved legacy rows');
check($dry['created'] === 0, 'Dry run creates nothing');
check($dry['by_status']['discrepancy'] === 2, 'Dry run reports legacy status breakdown');

$applied = $legacyCases->backfillLegacyCases(true);
check($applied['created'] === 4, 'Backfill creates one case per legacy remittance');
check((int) $legacyDb->query('SELECT COUNT(*) FROM discrepancy_cases')->fetchColumn() === 4, 'Only discrepancy and overdue rows get cases');

$secondRun = $legacyCases->backfillLegacyCases(true);
check($secondRun['created'] === 0 && $secondRun['existing'] === 4, 'Second backfill run inserts nothing');

$resolvedCase = $legacyCases->model()->getById((int) $legacyDb->query("SELECT id FROM discrepancy_cases WHERE remittance_log_id = 4")->fetchColumn());
check($resolvedCase['status'] === DiscrepancyCaseModel::STATUS_RESOLVED, 'Legacy Resolved maps to case Resolved');
check($resolvedCase['resolution_reason'] === 'Driver Paid Shortage', 'Legacy resolution type carried over as the resolution reason');
check($resolvedCase['findings'] === 'Paid in cash', 'Legacy resolution notes carried over as findings');
check($resolvedCase['case_type'] === DiscrepancyCaseModel::TYPE_SHORTAGE, 'Legacy resolved shortage classified as Cash Shortage');

$excessCase = $legacyCases->model()->getById((int) $legacyDb->query("SELECT id FROM discrepancy_cases WHERE remittance_log_id = 2")->fetchColumn());
check($excessCase['case_type'] === DiscrepancyCaseModel::TYPE_EXCESS, 'Legacy overage classified as Excess Remittance');
$overdueCase = $legacyCases->model()->getById((int) $legacyDb->query("SELECT id FROM discrepancy_cases WHERE remittance_log_id = 3")->fetchColumn());
check($overdueCase['case_type'] === DiscrepancyCaseModel::TYPE_OVERDUE, 'Legacy overdue classified as Overdue Remittance');

/* ---------- case details assembly (2.4) ---------- */

$detailDb = DiscrepancyFixture::pdo();
$detailCases = new DiscrepancyCaseService($detailDb);
DiscrepancyFixture::seedDriver($detailDb, 'D20', 'Detail Driver');
$detailRemit = DiscrepancyFixture::seedRemittanceLog($detailDb, [
    'driver_id' => 'D20',
    'business_date' => '2026-10-05',
    'expected_cash' => 700,
    'actual_cash' => 600,
    'discrepancy_amount' => 100,
    'status' => 'Discrepancy',
    'total_collection' => 910,
    'total_passengers' => 4,
]);
$detailResult = $detailCases->syncFromRemittance($detailRemit, [
    'driver_id' => 'D20',
    'business_date' => '2026-10-05',
    'expected_amount' => 700,
    'actual_amount' => 600,
]);
$detailCaseId = (int) $detailResult['case_id'];

DiscrepancyFixture::insertRow($detailDb, 'bus_trips', [
    'id' => 501, 'driver_id' => 'D20', 'bus_id' => 'B-101', 'trip_date' => '2026-10-05', 'status' => 'Completed',
]);
DiscrepancyFixture::insertRow($detailDb, 'bus_trips', [
    'id' => 502, 'driver_id' => 'D20', 'bus_id' => 'B-102', 'trip_date' => '2026-10-05', 'status' => 'Completed',
]);
DiscrepancyFixture::insertRow($detailDb, 'passenger_trips', [
    'driver_id' => 'D20', 'bus_no' => 'B-101', 'trip_no' => 501, 'fare' => 250, 'payment_method' => 'cash',
    'payment_status' => 'paid', 'cash_amount' => 250, 'boarding_time' => '2026-10-05 08:00:00',
]);
DiscrepancyFixture::insertRow($detailDb, 'passenger_trips', [
    'driver_id' => 'D20', 'bus_no' => 'B-101', 'trip_no' => 501, 'fare' => 450, 'payment_method' => 'cash',
    'payment_status' => 'paid', 'cash_amount' => 450, 'boarding_time' => '2026-10-05 09:00:00',
]);
DiscrepancyFixture::insertRow($detailDb, 'passenger_trips', [
    'driver_id' => 'D20', 'bus_no' => 'B-101', 'trip_no' => 501, 'fare' => 160, 'payment_method' => 'gcash',
    'payment_status' => 'paid', 'gcash_amount' => 160, 'paymongo_payment_id' => 'pay_123',
    'gcash_reference_no' => 'QR-9001', 'boarding_time' => '2026-10-05 09:30:00',
]);
DiscrepancyFixture::insertRow($detailDb, 'passenger_trips', [
    'driver_id' => 'D20', 'bus_no' => 'B-102', 'trip_no' => 502, 'fare' => 50, 'payment_method' => 'QRPh',
    'payment_status' => 'paid', 'gcash_amount' => 50, 'paymongo_payment_id' => 'pay_456',
    'boarding_time' => '2026-10-05 10:00:00',
]);
DiscrepancyFixture::insertRow($detailDb, 'passenger_trip_history', [
    'driver_id' => 'D20', 'bus_number' => 'B-103', 'trip_id' => 'H-77', 'fare' => 120,
    'payment_method' => 'cash', 'payment_status' => 'paid', 'trip_date' => '2026-10-05',
]);
DiscrepancyFixture::insertRow($detailDb, 'passenger_trip_history', [
    'driver_id' => 'D20', 'bus_number' => 'B-104', 'trip_id' => 'H-78', 'fare' => 30,
    'payment_method' => 'gcash', 'payment_status' => 'paid', 'trip_date' => '2026-10-05',
]);

$details = $detailCases->getDetails($detailCaseId);
check($details['calculation']['expected_amount'] == 700.0, 'Details show the expected snapshot');
check($details['calculation']['actual_amount'] == 600.0, 'Details show the actual snapshot');
check($details['calculation']['shortage'] == 100.0, 'Details show the shortage calculation');
check((float) $details['calculation']['expected_amount'] - (float) $details['calculation']['actual_amount'] === (float) $details['calculation']['shortage'], 'Calculation derives from the stored amounts');
check(count($details['trips']) === 4, 'Details list live and history trips');
check($details['remittance']['ref'] === 'RM-' . str_pad((string) $detailRemit, 5, '0', STR_PAD_LEFT), 'Details link the consolidated remittance');

$cashTotal = 0.0;
$qrphTotal = 0.0;
$buses = [];
foreach ($details['trips'] as $trip) {
    $cashTotal += (float) $trip['cash_total'];
    $qrphTotal += (float) $trip['qrph_total'];
    $buses[] = $trip['bus_id'];
}
check(round($cashTotal, 2) === 820.0, 'Cash breakdown sums cash payments and the cash history row');
check(round($qrphTotal, 2) === 240.0, 'QR Ph breakdown sums gcash and QRPh payments');
check(in_array('B-101', $buses, true) && in_array('B-102', $buses, true) && in_array('B-103', $buses, true) && in_array('B-104', $buses, true), 'Related buses are listed');

check(count($details['payment_references']) === 3, 'Payment references include non-cash live and history rows');
$refs = array_column($details['payment_references'], 'paymongo_payment_id');
check(in_array('pay_123', $refs, true) && in_array('pay_456', $refs, true), 'PayMongo references are included');

/* ---------- evidence upload (2.5) ---------- */

expectThrow(
    fn() => $detailCases->validateEvidenceFile(['tmp_name' => '', 'type' => 'text/plain', 'size' => 100]),
    'Only PDF, PNG, or JPG',
    'Invalid evidence MIME refused'
);
expectThrow(
    fn() => $detailCases->validateEvidenceFile(['tmp_name' => '', 'type' => 'image/png', 'size' => 6 * 1024 * 1024]),
    '5MB or smaller',
    'Oversized evidence refused'
);
check($detailCases->validateEvidenceFile(['tmp_name' => '', 'type' => 'image/png', 'size' => 1024]) === 'png', 'Valid PNG evidence accepted');
check($detailCases->validateEvidenceFile(['tmp_name' => '', 'type' => 'application/pdf', 'size' => 1024]) === 'pdf', 'Valid PDF evidence accepted');

$evidenceId = $detailCases->recordEvidence($detailCaseId, 'uploads/discrepancy-documents/disc-test.png', [
    'original_name' => 'receipt.png',
    'mime' => 'image/png',
    'size' => 1024,
]);
$evidence = $detailCases->model()->getEvidence($detailCaseId);
check(count($evidence) === 1 && (int) $evidence[0]['id'] === $evidenceId, 'Evidence row saved');
check($evidence[0]['uploaded_by'] === 'finance.tester', 'Evidence records the acting uploader');
check((int) $detailDb->query("SELECT COUNT(*) FROM discrepancy_status_history WHERE case_id = $detailCaseId AND event_type = 'evidence'")->fetchColumn() === 1, 'Evidence append recorded in case history');

/* ---------- overage acceptance classification (3.1) ---------- */

require_once __DIR__ . '/../services/RemittanceService.php';
require_once __DIR__ . '/../validators/RemittanceValidator.php';

use Finance\Services\RemittanceService;
use Finance\Validators\RemittanceValidator;

$balancedClass = RemittanceService::classifyAmounts(500, 500);
check($balancedClass['status'] === 'Completed', 'Balanced submissions stay Completed');
check($balancedClass['discrepancy_amount'] === 0.0 && $balancedClass['excess_amount'] === 0.0, 'Balanced submissions assert no discrepancy');

$shortClass = RemittanceService::classifyAmounts(500, 450);
check($shortClass['status'] === 'Discrepancy' && $shortClass['discrepancy_amount'] === 50.0, 'Shortage keeps the shortage amount');
check($shortClass['excess_amount'] === 0.0, 'Shortage asserts no excess');

$excessClass = RemittanceService::classifyAmounts(400, 460);
check($excessClass['status'] === 'Discrepancy', 'Overage is saved as a discrepancy');
check($excessClass['excess_amount'] === 60.0, 'Overage stores the excess amount');
check($excessClass['discrepancy_amount'] === 0.0, 'Overage asserts no shortage');

RemittanceValidator::validateCashAgainstExpected(460, 400);
check(true, 'Validator accepts an actual amount above expected');
expectThrow(
    fn() => RemittanceValidator::validateCashAgainstExpected(-1, 400),
    'negative',
    'Validator still refuses negative amounts'
);

$excessAudit = $db->query("SELECT COUNT(*) FROM audit_trail WHERE action = 'Discrepancy Case Opened' AND new_values LIKE '%Excess Remittance%'")->fetchColumn();
check((int) $excessAudit >= 1, 'Excess case creation is audited');

echo "Discrepancy case tests passed ($checks checks)\n";
exit(0);

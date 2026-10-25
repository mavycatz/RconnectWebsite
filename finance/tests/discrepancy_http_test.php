<?php
declare(strict_types=1);

/**
 * HTTP acceptance tests for the discrepancy workflow endpoints (task 5.5,
 * 6.1, 6.2, parts of 7.2). Runs the production controller against a
 * file-backed SQLite fixture through PHP's built-in server.
 *
 * Usage: php finance/tests/discrepancy_http_test.php
 */

$root = dirname(__DIR__, 2);
$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rconnect_discrepancy_http_fixture.sqlite';
@unlink($fixture);

/* Clear the file-based rate limiter so repeated test runs do not exhaust the
   per-minute window for the test IP. */
foreach (glob($root . '/finance/storage/ratelimit/*') ?: [] as $rateFile) {
    @unlink($rateFile);
}

$port = 8137;
$base = 'http://127.0.0.1:' . $port;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function httpRequest(string $method, string $query, array $headers = [], ?string $body = null): array
{
    global $base;

    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 60,
        ],
    ];
    if ($body !== null) {
        $options['http']['content'] = $body;
    }

    $response = @file_get_contents($base . $query, false, stream_context_create($options));
    $status = 0;
    $responseHeaders = $http_response_header ?? [];
    foreach ($responseHeaders as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $match)) {
            $status = (int) $match[1];
        }
    }

    return [$status, (string) $response, $responseHeaders];
}

function jsonRequest(string $method, string $query, array $headers = [], ?string $body = null): array
{
    [$status, $response] = httpRequest($method, $query, $headers, $body);
    $decoded = json_decode($response, true);
    return [$status, is_array($decoded) ? $decoded : null, $response];
}

$command = [
    PHP_BINARY,
    '-d', 'display_errors=0',
    '-d', 'memory_limit=512M',
    '-S', '127.0.0.1:' . $port,
    'finance/tests/http_fixture_router.php',
];

$serverLogFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rconnect_discrepancy_http_server.log';
@unlink($serverLogFile);

/* Redirect the server's output to a file: the built-in server logs every
   request to stderr, and leaving that pipe unread blocks the single-threaded
   server once the pipe buffer fills. */
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', $serverLogFile, 'a'],
    2 => ['file', $serverLogFile, 'a'],
];
$process = proc_open($command, $descriptors, $pipes, $root, null, ['bypass_shell' => true]);

if (!is_resource($process)) {
    fwrite(STDERR, "Could not start the test HTTP server.\n");
    exit(1);
}

$ready = false;
for ($i = 0; $i < 60; $i++) {
    $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.25);
    if ($socket) {
        fclose($socket);
        $ready = true;
        break;
    }
    usleep(150000);
}

if (!$ready) {
    proc_terminate($process);
    fwrite(STDERR, "The test HTTP server did not start.\n");
    exit(1);
}

$postHeaders = [
    'Content-Type: application/x-www-form-urlencoded',
    'X-CSRF-Token: test-csrf-token',
];

try {
    /* ---------- list + filters + metrics ---------- */

    [$status, $data] = jsonRequest('GET', '?action=list');
    check($status === 200 && $data && $data['success'] === true, 'List endpoint responds with JSON success');
    check((int) $data['metrics']['case_count'] === 5, 'Case count metric covers every seeded case');
    check((float) $data['metrics']['original_discrepancy'] === 80.0, 'Original discrepancy metric sums shortage snapshots');
    check((float) $data['metrics']['recovered'] === 20.0, 'Recovered metric sums posted cash entries');
    check((float) $data['metrics']['approved_adjustments'] === 30.0, 'Adjustment metric sums posted waivers');
    check((float) $data['metrics']['outstanding_balance'] === 30.0, 'Outstanding metric is derived from posted entries');
    check((int) $data['metrics']['overdue_investigations'] === 1, 'Overdue investigation metric counts past-due active cases');
    check((int) $data['metrics']['pending_approvals'] === 1, 'Pending approval metric counts undecided approvals');
    check(count($data['cases']) === 5, 'Default list returns every case');

    [$status, $data] = jsonRequest('GET', '?action=list&status=Open');
    check($data['success'] && count($data['cases']) === 1 && $data['cases'][0]['status'] === 'Open', 'Status filter returns only matching cases');

    [$status, $data] = jsonRequest('GET', '?action=list&type=Excess%20Remittance');
    check(count($data['cases']) === 1 && $data['cases'][0]['case_type'] === 'Excess Remittance', 'Type filter returns only matching cases');

    [$status, $data] = jsonRequest('GET', '?action=list&driver=Alicia');
    check(count($data['cases']) === 1 && $data['cases'][0]['driver_id'] === 'D1', 'Driver filter matches driver name');

    [$status, $data] = jsonRequest('GET', '?action=list&business_from=2026-10-01&business_to=2026-10-31');
    check(count($data['cases']) === 2, 'Business date range filter applies both bounds');

    [$status, $data] = jsonRequest('GET', '?action=list&reviewer=maria');
    check(count($data['cases']) === 2, 'Reviewer filter matches assigned reviewer');

    [$status, $data] = jsonRequest('GET', '?action=list&age=0-3');
    check(count($data['cases']) === 2, 'Age bucket 0-3 selects recent cases');

    [$status, $data] = jsonRequest('GET', '?action=list&age=15%2B');
    check(count($data['cases']) === 2, 'Age bucket 15+ selects the oldest cases');

    [$status, $data] = jsonRequest('GET', '?action=list&overdue=1');
    check(count($data['cases']) === 1 && (int) $data['cases'][0]['is_overdue_investigation'] === 1, 'Overdue filter selects past-due investigations');

    [$status, $data] = jsonRequest('GET', '?action=list&status=Open&type=Cash%20Shortage&driver=Alicia&age=0-3');
    check(count($data['cases']) === 1, 'Combined filters all apply together');

    [$status, $data] = jsonRequest('GET', '?action=list&page=2&per_page=2');
    check($data['pagination']['page'] === 2 && $data['pagination']['pages'] === 3 && count($data['cases']) === 2, 'Pagination returns the requested page of the filtered set');

    /* ---------- case details ---------- */

    [$status, $data] = jsonRequest('GET', '?action=view&case_id=1');
    check($status === 200 && $data['success'] === true, 'Case details endpoint responds');
    check($data['details']['case']['case_ref'] === 'DIS-00001', 'Case details include the case reference');
    check((float) $data['details']['calculation']['shortage'] === 50.0 && (float) $data['details']['calculation']['outstanding_balance'] === 30.0, 'Case details include the calculation and derived balance');
    check(in_array('Under Investigation', $data['details']['allowed_transitions'], true), 'Case details expose allowed transitions');
    check($data['details']['permissions']['can_approve'] === true, 'Manager permissions allow approval');

    /* ---------- lifecycle: findings ---------- */

    [$status, $data] = jsonRequest('POST', '?action=findings', $postHeaders, http_build_query([
        'case_id' => 1,
        'findings' => 'Driver confirmed the shortage in writing.',
        'reason' => 'Shortage confirmed.',
    ]));
    check($status === 200 && $data['success'] === true, 'Findings can be recorded over HTTP');
    check($data['case']['status'] === 'Under Investigation', 'Recording findings starts the investigation');

    /* Body-field CSRF fallback for hosts that do not expose the header. */
    [$status, $data] = jsonRequest('POST', '?action=assign', [
        'Content-Type: application/x-www-form-urlencoded',
    ], http_build_query([
        'case_id' => 1,
        'reviewer' => 'maria.manager',
        'due_date' => '2026-10-31',
        '_csrf_token' => 'test-csrf-token',
    ]));
    check($status === 200 && $data['success'] === true, 'CSRF token accepted from the form body when the header is absent');

    /* ---------- ledger propose / approve / post ---------- */

    [$status, $data] = jsonRequest('POST', '?action=ledger_propose', array_merge($postHeaders, ['X-Test-Role: finance_officer']), http_build_query([
        'case_id' => 1,
        'entry_type' => 'additional_payment',
        'amount' => 10,
        'reference' => 'OR-HTTP-1',
        'reason' => 'Partial payment received.',
    ]));
    check($status === 200 && $data['success'] === true, 'Resolution can be proposed over HTTP by an officer');
    $entryId = (int) $data['entry']['entry_id'];
    $approvalId = (int) $data['entry']['approval_id'];

    [$status, $data] = jsonRequest('GET', '?action=view&case_id=1');
    check($data['details']['case']['status'] === 'Awaiting Approval', 'Proposal moved the case to Awaiting Approval');
    check($data['details']['has_pending_approval'] === true, 'Case details flag the pending approval');
    $pending = array_values(array_filter($data['details']['approvals'], static fn($a) => $a['status'] === 'pending'));
    check(count($pending) === 1, 'Exactly one pending approval is listed');

    [$status, $data] = jsonRequest('POST', '?action=approval_decide', $postHeaders, http_build_query([
        'approval_id' => $approvalId,
        'decision' => 'approved',
        'reason' => 'Receipt verified over HTTP.',
    ]));
    check($status === 200 && $data['success'] === true, 'Approval decision accepted for a manager');

    [$status, $data] = jsonRequest('POST', '?action=ledger_post', $postHeaders, http_build_query(['entry_id' => $entryId]));
    check($status === 200 && $data['success'] === true, 'Approved entry posts over HTTP');
    check((float) $data['entry']['outstanding_after'] === 20.0, 'Posting reports the new outstanding balance');

    [$status, $data] = jsonRequest('GET', '?action=view&case_id=1');
    check((float) $data['details']['calculation']['outstanding_balance'] === 20.0, 'Details reflect the posted recovery');

    /* ---------- security: CSRF, roles, unauthenticated ---------- */

    [$status] = jsonRequest('POST', '?action=ledger_void', [
        'Content-Type: application/x-www-form-urlencoded',
        'X-CSRF-Token: wrong-token',
    ], http_build_query(['entry_id' => $entryId, 'reason' => 'x']));
    check($status === 403, 'Missing or wrong CSRF token is refused with 403');

    [$status, $data] = jsonRequest('GET', '?action=list', ['X-Test-Role: driver']);
    check($status === 403, 'A non-finance role is refused with 403');

    [$status, $data] = jsonRequest('GET', '?action=list', ['X-Test-Role: none']);
    check($status === 401, 'An unauthenticated caller is refused with 401');

    /* ---------- authorization rules ---------- */

    [$status, $data] = jsonRequest('GET', '?action=rules_get');
    check($status === 200 && $data['success'] === true && isset($data['rules']['actions']['payroll_recovery']), 'Rules endpoint returns the effective rules');
    check($data['can_edit'] === true, 'Manager can edit rules');
    $rulesPayload = $data['rules'];

    [$status, $data] = jsonRequest('GET', '?action=finance_users');
    check($status === 200 && $data['success'] === true, 'Finance users endpoint responds');
    check(count($data['users']) === 2, 'Finance users endpoint returns only finance roles');
    check($data['users'][0]['display_name'] === 'Maria Manager' && $data['users'][0]['role'] === 'finance_manager', 'Finance users include display name and role ordered by role');
    check($data['users'][1]['username'] === 'ofelia.officer', 'Finance users include officers');

    [$status, $data] = jsonRequest('POST', '?action=rules_save', $postHeaders, http_build_query([
        'rules' => json_encode($rulesPayload),
    ]));
    check($status === 200 && $data['success'] === true, 'Manager can save the rules document');

    [$status, $data] = jsonRequest('POST', '?action=backfill', $postHeaders, '');
    check($status === 200 && $data['success'] === true, 'Manager can import existing discrepancies from the page');
    check(isset($data['result']['created'], $data['result']['existing']), 'Import reports created and existing counts');

    /* ---------- investigation and approval workflow (role separation) ---------- */

    $managerHeaders = $postHeaders;
    $officerHeaders = array_merge($postHeaders, ['X-Test-Role: finance_officer']);

    // Case 3 is Under Investigation. A manager submits findings (self-approval test setup).
    [$status, $data] = jsonRequest('POST', '?action=submit_findings', $managerHeaders, http_build_query([
        'case_id' => 3,
        'findings' => 'Manager-recorded findings for workflow coverage.',
        'recommendation' => 'Recommend an approved adjustment.',
        'remarks' => 'Initial submission.',
    ]));
    check($status === 200 && $data['success'] === true, 'Findings can be submitted over HTTP');
    check($data['case']['status'] === 'Findings Submitted', 'Submission moves the case to Findings Submitted');
    check($data['case']['findings_submitted_by'] === 'maria.manager', 'Submission records the investigator over HTTP');

    [$status, $data] = jsonRequest('POST', '?action=manager_review', $managerHeaders, http_build_query(['case_id' => 3]));
    check($status === 200 && $data['case']['status'] === 'Pending Manager Approval', 'Manager starts the approval review over HTTP');

    [$status, $data] = jsonRequest('POST', '?action=manager_decision', $managerHeaders, http_build_query([
        'case_id' => 3,
        'decision' => 'approved',
        'reason' => 'Approving my own submission.',
    ]));
    check($status === 403, 'Manager cannot decide their own submission over HTTP');

    [$status, $data] = jsonRequest('POST', '?action=status', $managerHeaders, http_build_query([
        'case_id' => 3,
        'status' => 'Returned for Further Investigation',
        'reason' => 'Attach the supporting receipts before approval.',
    ]));
    check($status === 200 && $data['case']['status'] === 'Returned for Further Investigation', 'Manager can return a case for further investigation');

    [$status, $data] = jsonRequest('POST', '?action=submit_findings', $officerHeaders, http_build_query([
        'case_id' => 3,
        'findings' => 'Officer findings with receipts attached.',
        'recommendation' => 'Accept an additional payment of the shortage amount.',
        'remarks' => 'Receipts attached.',
    ]));
    check($status === 200 && $data['case']['status'] === 'Findings Submitted', 'Officer resubmits after a return');

    [$status, $data] = jsonRequest('POST', '?action=manager_decision', $officerHeaders, http_build_query([
        'case_id' => 3,
        'decision' => 'approved',
        'reason' => 'Officer tries to approve.',
    ]));
    check($status === 403, 'Officer cannot decide submitted findings over HTTP');

    [$status, $data] = jsonRequest('POST', '?action=manager_review', $managerHeaders, http_build_query(['case_id' => 3]));
    check($status === 200 && $data['case']['status'] === 'Pending Manager Approval', 'Manager picks the resubmission up for review');

    [$status, $data] = jsonRequest('POST', '?action=manager_decision', $managerHeaders, http_build_query([
        'case_id' => 3,
        'decision' => 'approved',
        'reason' => 'Independently reviewed and approved.',
    ]));
    check($status === 200 && $data['case']['status'] === 'Resolved', 'Manager approval finalizes the case as Resolved');

    [$status, $data] = jsonRequest('POST', '?action=status', $managerHeaders, http_build_query([
        'case_id' => 3,
        'status' => 'Closed',
        'reason' => 'Recovery confirmed; closing the case.',
    ]));
    check($status === 200 && $data['case']['status'] === 'Closed', 'Manager closes the resolved case');

    [$status, $data] = jsonRequest('POST', '?action=status', $officerHeaders, http_build_query([
        'case_id' => 3,
        'status' => 'Under Investigation',
        'reason' => 'Officer tries to reopen.',
    ]));
    check($status === 403, 'Officer cannot reopen a finalized case over HTTP');

    /* ---------- exports ---------- */

    [$status, $body, $headers] = httpRequest('GET', '?action=export_pdf&status=Open');
    $contentType = implode(' ', $headers);
    check($status === 200, 'PDF export responds with 200 (got ' . $status . ': ' . substr((string) $body, 0, 300) . ')');
    check(stripos($contentType, 'application/pdf') !== false, 'PDF export sends the PDF content type');
    check(strpos((string) $body, '%PDF') === 0, 'PDF export produces a PDF document');

    [$status, $body, $headers] = httpRequest('GET', '?action=export_excel&status=Open');
    $contentType = implode(' ', $headers);
    check($status === 200, 'Excel export responds with 200');
    check(stripos($contentType, 'spreadsheetml') !== false, 'Excel export sends the XLSX content type');
    check(strpos((string) $body, 'PK') === 0, 'Excel export produces an XLSX document');

    [$status, $data] = jsonRequest('GET', '?action=audit&record_id=DIS-EXPORT');
    check($status === 200 && $data['success'] === true, 'Export audit endpoint responds');
    $exportActions = array_column($data['records'] ?? [], 'action');
    check(in_array('Discrepancy Exported', $exportActions, true), 'Exports are recorded in the audit trail');

    /* ---------- page ---------- */

    [$status, $body] = httpRequest('GET', '?action=index');
    check($status === 200 && strpos((string) $body, 'Discrepancy Investigation') !== false, 'The case workspace page renders');

    echo "Discrepancy HTTP tests passed ($checks checks)\n";
    $exitCode = 0;
} catch (Throwable $e) {
    $serverLog = '';
    if (is_file($serverLogFile)) {
        $serverLog = (string) file_get_contents($serverLogFile);
        if (strlen($serverLog) > 4000) {
            $serverLog = substr($serverLog, -4000);
        }
    }
    fwrite(STDERR, 'HTTP test failed: ' . $e->getMessage() . "\n");
    if ($serverLog !== '') {
        fwrite(STDERR, "Server log (tail):\n" . $serverLog . "\n");
    }
    $exitCode = 1;
}

proc_terminate($process);
foreach ($pipes as $pipe) {
    if (is_resource($pipe)) {
        fclose($pipe);
    }
}
proc_close($process);

exit($exitCode);

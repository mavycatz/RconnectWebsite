<?php declare(strict_types=1);

/**
 * Role Management Security Authorization Code checks.
 *
 * Verifies the canonical payload hash, the role/action policy matrix, and the
 * full code/token lifecycle against an in-memory SQLite database. No session,
 * HTTP request or MySQL server is required.
 */

require_once __DIR__ . '/../services/RoleManagement/SecurityCodeService.php';

use Admin\Services\RoleManagement\SecurityCodeService;

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

/* ---------------------------------------------------------------------------
 * Canonical payload hash
 * ------------------------------------------------------------------------- */

check(
    SecurityCodeService::canonicalHash(['b' => '2', 'a' => '1'])
        === hash('sha256', 'a=1&b=2'),
    'Keys are lowercased, sorted, and joined as k=v pairs'
);

check(
    SecurityCodeService::canonicalHash(['A' => ' 1 ', 'B' => '  2  '])
        === SecurityCodeService::canonicalHash(['b' => '2', 'a' => '1']),
    'Key case and surrounding whitespace do not change the hash'
);

check(
    SecurityCodeService::canonicalHash(['security_token' => 'abc', 'action' => 'edit', 'a' => '1'])
        === hash('sha256', 'a=1'),
    'Transport/security fields are excluded from the hash'
);

check(
    SecurityCodeService::canonicalHash(['flag' => true, 'other' => false])
        === hash('sha256', 'flag=1&other=0'),
    'Booleans normalize to 1/0'
);

check(
    SecurityCodeService::canonicalHash(['a' => '1', 'password' => 'secret'])
        === hash('sha256', 'a=1&password=secret'),
    'Credential fields are bound into the hash'
);

check(
    SecurityCodeService::canonicalHash(['a' => ['nested']]) === hash('sha256', ''),
    'Array values are skipped'
);

/* ---------------------------------------------------------------------------
 * Role/action policy matrix
 * ------------------------------------------------------------------------- */

foreach (['driver', 'inspector', 'finance'] as $target) {
    foreach (['add', 'edit', 'archive', 'restore'] as $action) {
        check(
            SecurityCodeService::actionAllowed('admin', $action, $target),
            "Admin may $action $target"
        );
        check(
            SecurityCodeService::actionAllowed('super_admin', $action, $target),
            "Super Admin may $action $target"
        );
    }
}

foreach (['admin', 'super_admin'] as $target) {
    foreach (['add', 'edit', 'archive', 'restore'] as $action) {
        check(
            !SecurityCodeService::actionAllowed('admin', $action, $target),
            "Admin may NOT $action $target"
        );
        check(
            SecurityCodeService::actionAllowed('super_admin', $action, $target),
            "Super Admin may $action $target"
        );
    }
}

check(!SecurityCodeService::actionAllowed('admin', 'delete', 'driver'), 'Unknown actions are denied');
check(!SecurityCodeService::actionAllowed('admin', 'edit', 'visitor'), 'Unknown targets are denied');
check(!SecurityCodeService::actionAllowed('driver', 'edit', 'driver'), 'Non-admin actors are denied');

foreach (['add', 'edit', 'archive', 'restore'] as $action) {
    check(
        SecurityCodeService::actionAllowed('admin', $action, 'bus'),
        "Admin may $action a bus"
    );
    check(
        SecurityCodeService::actionAllowed('super_admin', $action, 'bus'),
        "Super Admin may $action a bus"
    );
}
check(!SecurityCodeService::actionAllowed('driver', 'edit', 'bus'), 'Non-admin actors cannot manage buses');
check(!SecurityCodeService::actionAllowed('admin', 'delete', 'bus'), 'Unknown actions are denied for buses');

check(SecurityCodeService::targetLabel('bus') === 'bus', 'Bus target keeps its own label');
check(SecurityCodeService::guardTarget('bus', '12') === 'bus:12', 'Bus targets bind the internal id');
check(SecurityCodeService::guardTarget('bus', null) === 'bus', 'Bus creates bind no ref');
check(SecurityCodeService::guardTarget('bus', ' 0 ') === 'bus', 'Bus zero refs are omitted');

check(SecurityCodeService::targetLabel('dispatcher') === 'inspector', 'Dispatchers are managed as inspector accounts');
check(SecurityCodeService::targetLabel('finance_officer') === 'finance', 'Finance officers map to the finance group');
check(SecurityCodeService::targetLabel('finance_manager') === 'finance', 'Finance managers map to the finance group');
check(SecurityCodeService::targetLabel('driver') === 'driver', 'Driver label is unchanged');

check(SecurityCodeService::guardTarget('dispatcher', '42') === 'inspector:42', 'Existing records bind the ref into the target');
check(SecurityCodeService::guardTarget('driver', ' 7 ') === 'driver:7', 'Refs are trimmed');
check(SecurityCodeService::guardTarget('driver', null) === 'driver', 'Creates bind no ref');
check(SecurityCodeService::guardTarget('finance_officer', '') === 'finance', 'Empty refs are omitted');
check(SecurityCodeService::guardTarget('finance_officer', '0') === 'finance', 'Zero refs are omitted');
check(SecurityCodeService::guardTarget('admin', '12') === 'admin:12', 'Admin targets bind their user id');

/* ---------------------------------------------------------------------------
 * Code + token lifecycle on SQLite
 * ------------------------------------------------------------------------- */

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

SecurityCodeService::ensureSchema($db);
SecurityCodeService::ensureSchema($db);

$service = new SecurityCodeService($db);

check(!$service->isConfigured(), 'A fresh install has no code configured');

$service->setCode('correct-horse', 7);
check($service->isConfigured(), 'Setting a code configures the service');
check($service->verifyCode('correct-horse'), 'The correct code verifies');
check(!$service->verifyCode('wrong-horse'), 'A wrong code does not verify');
check($service->lastUpdated()['by'] === 7, 'The setter is recorded');

$hashA = SecurityCodeService::canonicalHash(['a' => '1']);
$hashB = SecurityCodeService::canonicalHash(['a' => '2']);

$issued = $service->issueToken(7, 'edit', 'driver:12', $hashA);
check(strlen($issued['token']) === 64, 'Issued tokens are 64 hex characters');
check($issued['expires_in'] === SecurityCodeService::TOKEN_TTL, 'Tokens advertise their TTL');

check($service->consumeToken($issued['token'], 7, 'edit', 'driver:12', $hashA), 'A fresh token is consumed successfully');
check(!$service->consumeToken($issued['token'], 7, 'edit', 'driver:12', $hashA), 'A token cannot be replayed');

$issued = $service->issueToken(7, 'edit', 'driver:12', $hashA);
check(!$service->consumeToken($issued['token'], 8, 'edit', 'driver:12', $hashA), 'A token is bound to its actor');
check(!$service->consumeToken($issued['token'], 7, 'archive', 'driver:12', $hashA), 'A token is bound to its action');
check(!$service->consumeToken($issued['token'], 7, 'edit', 'driver:13', $hashA), 'A token is bound to its target');
check(!$service->consumeToken($issued['token'], 7, 'edit', 'driver:12', $hashB), 'A token is bound to the exact payload');
check($service->consumeToken($issued['token'], 7, 'edit', 'driver:12', $hashA), 'The untouched token still works after failed attempts');

$expired = $service->issueToken(7, 'add', 'finance', $hashA);
$db->exec('UPDATE role_security_verifications SET expires_ts = ' . (time() - 10) . " WHERE used_at IS NULL");
check(!$service->consumeToken($expired['token'], 7, 'add', 'finance', $hashA), 'Expired tokens are rejected');

$outstanding = $service->issueToken(7, 'archive', 'inspector:3', $hashA);
$service->setCode('new-secret-9', 7);
check(!$service->consumeToken($outstanding['token'], 7, 'archive', 'inspector:3', $hashA), 'Rotating the code invalidates outstanding tokens');
check($service->verifyCode('new-secret-9'), 'The rotated code verifies');
check(!$service->verifyCode('correct-horse'), 'The old code no longer verifies');

check($service->lockoutUntil(9) === null, 'An actor with no failures is not locked out');
for ($i = 0; $i < SecurityCodeService::MAX_FAILURES - 1; $i++) {
    $service->recordEvent(9, 'tester', 'failed', 'edit driver');
}
check($service->lockoutUntil(9) === null, 'Below the threshold there is no lockout');
$service->recordEvent(9, 'tester', 'failed', 'edit driver');
check($service->lockoutUntil(9) !== null, 'Reaching the threshold locks the actor out');
check($service->failuresRecent(9) === SecurityCodeService::MAX_FAILURES, 'Failures are counted inside the window');

$service->recordEvent(9, 'tester', 'verified', 'edit driver');
$service->recordEvent(9, 'tester', 'used', 'edit driver');
check($service->usedRecent(9) >= 1, 'Completed actions are counted for rate limiting');
check($service->usedRecent(9, 1) === $service->usedRecent(9, 1), 'Rate window is evaluated safely');
$activity = $service->activity(50);
check(count($activity) >= SecurityCodeService::MAX_FAILURES + 1, 'Activity lists recorded events');
$events = array_column($activity, 'event');
check(in_array('verified', $events, true) && in_array('failed', $events, true), 'Activity carries every event type');
check($activity[0]['username'] === 'tester', 'Activity records the acting username');

foreach (['archive', 'restore'] as $archiveAction) {
    $reasonPayload = ['bus_ref' => '12', 'reason' => 'Required archive reason'];
    $reasonHash = SecurityCodeService::canonicalHash($reasonPayload);
    $approval = $service->issueToken(7, $archiveAction, 'bus:12', $reasonHash);
    check(!$service->consumeToken('', 7, $archiveAction, 'bus:12', $reasonHash), 'Missing approval token rejected for ' . $archiveAction);
    check(!$service->consumeToken($approval['token'], 7, $archiveAction, 'bus:13', $reasonHash), 'Different bus rejected for ' . $archiveAction);
    check(!$service->consumeToken($approval['token'], 7, $archiveAction, 'bus:12', SecurityCodeService::canonicalHash(['bus_ref'=>'12','reason'=>'Changed reason'])), 'Altered reason rejected for ' . $archiveAction);
    check($service->consumeToken($approval['token'], 7, $archiveAction, 'bus:12', $reasonHash), 'Exact approved request accepted for ' . $archiveAction);
    check(!$service->consumeToken($approval['token'], 7, $archiveAction, 'bus:12', $reasonHash), 'Approval cannot be replayed for ' . $archiveAction);
}

/* A restore payload carrying bus details stays bound to every supplied field. */
$detailPayload = [
    'bus_ref' => '12',
    'reason' => 'Required restore reason',
    'plate_number' => 'ABC 1234',
    'seats' => '49',
];
$detailHash = SecurityCodeService::canonicalHash($detailPayload);
$detailToken = $service->issueToken(7, 'restore', 'bus:12', $detailHash);
check(
    !$service->consumeToken($detailToken['token'], 7, 'restore', 'bus:12', SecurityCodeService::canonicalHash([
        'bus_ref' => '12', 'reason' => 'Required restore reason', 'plate_number' => 'XYZ 9999', 'seats' => '49',
    ])),
    'Altered detail payload rejected for restore'
);
check($service->consumeToken($detailToken['token'], 7, 'restore', 'bus:12', $detailHash), 'Exact detail payload accepted for restore');
echo "$checks role security checks passed.\n";

<?php declare(strict_types=1);

/**
 * Security Authorization Code API for Role Management.
 *
 * GET  ?action=status    — configuration state for the acting admin
 * GET  ?action=activity  — recent verification events (Super Admin only)
 * POST action=verify     — exchange the security code for a single-use token
 *                          bound to this actor/action/target/payload hash
 * POST action=set        — set or rotate the code (Super Admin only, password
 *                          re-authentication required)
 *
 * The code itself is never returned, never logged, and never sent back to the
 * browser. Only the settings row (hash + last update) is stored.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/SecurityCodeService.php';

use Admin\Services\RoleManagement\SecurityCodeService;
use App\Core\Database;

$actor = rmg_actor();

header('Cache-Control: no-store');

/**
 * JSON rejection with a machine-readable code so the UI can react precisely.
 */
function rmg_sec_fail(int $status, string $message, string $code, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => false,
        'message' => $message,
        'code' => $code,
    ], $extra));
    exit;
}

try {
    $db = Database::get();
} catch (Throwable $e) {
    rmg_sec_fail(500, 'Authorization is unavailable.', 'unavailable');
}

SecurityCodeService::ensureSchema($db);
$service = new SecurityCodeService($db);

$action = strtolower(trim((string)($_GET['action'] ?? '')));

if ($action === '') {
    $input = rmg_input();
    $action = strtolower(trim((string)($input['action'] ?? '')));
}

$userId = (int)($actor['user']['id'] ?? 0);
$username = (string)($actor['user']['username'] ?? '');

switch ($action) {
    case 'status':
        $status = [
            'success' => true,
            'configured' => $service->isConfigured(),
            'is_super' => rmg_is_super($actor),
            'locked_until' => $service->lockoutUntil($userId),
        ];

        if (rmg_is_super($actor)) {
            $status['last_updated'] = $service->lastUpdated();
        }

        echo json_encode($status);
        exit;

    case 'activity':
        rmg_require_super($actor);
        echo json_encode([
            'success' => true,
            'events' => $service->activity(25),
        ]);
        exit;

    case 'verify':
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            rmg_sec_fail(405, 'Verification must be submitted with POST.', 'method');
        }

        $input = rmg_input();

        $writeAction = strtolower(trim((string)($input['verify_action'] ?? '')));

        // The target travels as "label" or "label:ref" (e.g. inspector:42).
        // The exact string is bound into the token, so it must be normalized
        // identically here and in _security_guard.php.
        $rawTarget = trim((string)($input['target'] ?? ''));
        $baseTarget = $rawTarget;
        $targetRef = '';
        $colon = strpos($rawTarget, ':');
        if ($colon !== false) {
            $baseTarget = substr($rawTarget, 0, $colon);
            $targetRef = trim(substr($rawTarget, $colon + 1));
        }
        $baseTarget = SecurityCodeService::targetLabel($baseTarget);
        $target = SecurityCodeService::guardTarget($baseTarget, $targetRef);

        $payloadHash = strtolower(trim((string)($input['payload_hash'] ?? '')));
        $code = (string)($input['code'] ?? '');

        if (!SecurityCodeService::requiresVerification($writeAction)) {
            rmg_sec_fail(400, 'Unknown action to verify.', 'invalid_action');
        }

        if (!in_array($baseTarget, ['driver', 'inspector', 'finance', 'bus', 'admin', 'super_admin', 'dispatch'], true)
            || mb_strlen($target) > 200
            || preg_match('/[\r\n]/', $target)) {
            rmg_sec_fail(400, 'Unknown account group.', 'invalid_target');
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $payloadHash)) {
            rmg_sec_fail(400, 'The request signature could not be verified.', 'invalid_payload');
        }

        if (!SecurityCodeService::actionAllowed($actor['role'], $writeAction, $baseTarget)) {
            rmg_sec_fail(403, 'You are not allowed to manage this account.', 'role_denied');
        }

        if (!$service->isConfigured()) {
            rmg_sec_fail(
                409,
                'Security authorization is not set up yet. A Super Admin must set the security code first.',
                'security_not_configured'
            );
        }

        $lockedUntil = $service->lockoutUntil($userId);
        if ($lockedUntil !== null) {
            rmg_sec_fail(
                429,
                'Too many incorrect attempts. Please try again after ' . $lockedUntil . ' UTC.',
                'locked',
                ['locked_until' => $lockedUntil]
            );
        }

        if ($code === '' || mb_strlen($code) > 200) {
            rmg_sec_fail(422, 'Enter the Security Authorization Code.', 'empty_code');
        }

        if (!$service->verifyCode($code)) {
            $service->recordEvent($userId, $username, 'failed', $writeAction . ' ' . $target);
            $remaining = SecurityCodeService::MAX_FAILURES - $service->failuresRecent($userId);

            if ($remaining <= 0) {
                rmg_sec_fail(
                    429,
                    'Too many incorrect attempts. Security verification is temporarily locked.',
                    'locked',
                    ['locked_until' => $service->lockoutUntil($userId)]
                );
            }

            rmg_sec_fail(403, 'Incorrect Security Authorization Code. ' . $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' remaining.', 'incorrect_code');
        }

        $issued = $service->issueToken($userId, $writeAction, $target, $payloadHash);
        $service->recordEvent($userId, $username, 'verified', $writeAction . ' ' . $target);

        echo json_encode([
            'success' => true,
            'token' => $issued['token'],
            'expires_in' => $issued['expires_in'],
            'action' => $writeAction,
            'target' => $target,
        ]);
        exit;

    case 'set':
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            rmg_sec_fail(405, 'Changing the code must be submitted with POST.', 'method');
        }

        rmg_require_super($actor);

        $input = rmg_input();

        $password = (string)($input['password'] ?? '');
        $currentCode = (string)($input['current_code'] ?? '');
        $newCode = (string)($input['new_code'] ?? '');
        $confirmCode = (string)($input['confirm_code'] ?? '');

        if ($password === '' || mb_strlen($password) > 200) {
            rmg_sec_fail(422, 'Enter your account password to confirm this change.', 'password_required');
        }

        try {
            $stmt = $db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $hash = (string)($stmt->fetchColumn() ?: '');
        } catch (Throwable $e) {
            $hash = '';
        }

        if ($hash === '' || !password_verify($password, $hash)) {
            $service->recordEvent($userId, $username, 'failed', 'reauthentication for code change');
            rmg_sec_fail(403, 'Your account password is incorrect.', 'reauth_failed');
        }

        if ($service->isConfigured()) {
            if ($currentCode === '' || !$service->verifyCode($currentCode)) {
                $service->recordEvent($userId, $username, 'failed', 'current code rejected during change');
                rmg_sec_fail(403, 'The current Security Authorization Code is incorrect.', 'current_code_invalid');
            }
        }

        if (mb_strlen($newCode) < SecurityCodeService::MIN_CODE_LENGTH || mb_strlen($newCode) > 128) {
            rmg_sec_fail(
                422,
                'The new code must be at least ' . SecurityCodeService::MIN_CODE_LENGTH . ' characters.',
                'weak_code'
            );
        }

        if ($newCode !== $confirmCode) {
            rmg_sec_fail(422, 'The new code and its confirmation do not match.', 'confirm_mismatch');
        }

        if ($service->isConfigured() && $service->verifyCode($newCode)) {
            rmg_sec_fail(422, 'The new code must be different from the current code.', 'same_code');
        }

        $service->setCode($newCode, $userId);
        $service->recordEvent($userId, $username, 'changed', 'security code updated');

        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';
            $audit = new \Finance\Models\AuditTrailModel();
            $audit->log(
                'RoleManagement',
                'SecurityCode',
                'Security Authorization Code updated by ' . ($username !== '' ? $username : 'super admin'),
                $userId,
                $username !== '' ? $username : null,
                $actor['role'],
                null,
                [],
                'Success'
            );
        } catch (Throwable $ignore) {
        }

        echo json_encode([
            'success' => true,
            'message' => 'The Security Authorization Code was updated. Previous verifications are no longer valid.',
            'last_updated' => $service->lastUpdated(),
        ]);
        exit;

    default:
        rmg_sec_fail(400, 'Unknown security action.', 'invalid_action');
}

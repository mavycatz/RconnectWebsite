<?php declare(strict_types=1);

/**
 * Security Authorization Code guard for account and bus write endpoints.
 *
 * Include this before any protected write runs (add / edit / archive /
 * restore) for driver, inspector, finance, administrator accounts, and buses.
 * It:
 *
 *   1. resolves the acting admin from the session (never from the request);
 *   2. rejects actions the actor may never perform (admin/super_admin targets
 *      are Super-Admin-only);
 *   3. requires the managed Security Authorization Code to be configured;
 *   4. verifies a single-use token bound to the actor, action, target and the
 *      canonical hash of the exact request payload.
 *
 * A caller cannot bypass the code by replaying a token, editing the payload
 * mid-flight, or switching action/target.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/SecurityCodeService.php';

use Admin\Services\RoleManagement\SecurityCodeService;
use App\Core\Database;

/**
 * Emit a JSON rejection carrying a machine-readable error code and stop.
 */
function rmg_guard_fail(int $status, string $message, string $code): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    http_response_code($status);

    echo json_encode([
        'success' => false,
        'message' => $message,
        'code' => $code,
    ]);

    exit;
}

/**
 * Absolute path helpers stay local to the guard so a partially loaded
 * framework can never crash an authorization check.
 */
function rmg_guard_payload(): array
{
    $raw = file_get_contents('php://input');

    if (is_string($raw) && trim($raw) !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }

    return $_POST;
}

function rmg_guard_token(array $payload): string
{
    foreach (['security_token'] as $key) {
        if (!empty($payload[$key]) && is_string($payload[$key])) {
            return (string)$payload[$key];
        }
        if (!empty($_POST[$key]) && is_string($_POST[$key])) {
            return (string)$_POST[$key];
        }
        if (!empty($_GET[$key]) && is_string($_GET[$key])) {
            return (string)$_GET[$key];
        }
    }

    return '';
}

/**
 * Build the security target from a displayed role and an optional record ref.
 */
function rmg_guard_target(string $targetRole, ?string $targetRef): string
{
    return SecurityCodeService::guardTarget($targetRole, $targetRef);
}

/**
 * Enforce the full policy for one protected action.
 *
 * @param string      $action     add|edit|archive|restore
 * @param string      $targetRole displayed role of the account being managed
 * @param string|null $targetRef  stored record id when editing an existing row
 */
function rmg_guard_require(string $action, string $targetRole, ?string $targetRef = null): void
{
    $actor = rmg_actor();

    if (!SecurityCodeService::requiresVerification($action)) {
        rmg_guard_fail(400, 'Unsupported security action.', 'invalid_action');
    }

    $label = SecurityCodeService::targetLabel($targetRole);

    if (!SecurityCodeService::actionAllowed($actor['role'], $action, $label)) {
        rmg_guard_fail(403, 'You are not allowed to manage this account.', 'role_denied');
    }

    try {
        $db = Database::get();
    } catch (Throwable $e) {
        rmg_guard_fail(500, 'Authorization is unavailable.', 'unavailable');
    }

    SecurityCodeService::ensureSchema($db);
    $service = new SecurityCodeService($db);

    $user = is_array($actor['user'] ?? null) ? $actor['user'] : [];
    $userId = (int)($user['id'] ?? 0);
    $username = (string)($user['username'] ?? '');

    if (!$service->isConfigured()) {
        $service->recordEvent($userId, $username, 'blocked', 'code not configured for ' . $action . ' ' . $label);
        rmg_guard_fail(
            409,
            'Security authorization is not set up yet. A Super Admin must set the security code in Role Management > Security settings.',
            'security_not_configured'
        );
    }

    // Rate limit: a burst of protected actions is rejected even with valid
    // codes, so a stolen session cannot mass-edit accounts.
    if ($service->usedRecent($userId) >= SecurityCodeService::RATE_MAX) {
        $service->recordEvent($userId, $username, 'blocked', 'rate limit reached for ' . $action . ' ' . $label);
        rmg_guard_fail(
            429,
            'Too many account changes in a short time. Please wait a few minutes and try again.',
            'rate_limited'
        );
    }

    $payload = rmg_guard_payload();
    if (in_array($action, ['archive', 'restore'], true) && mb_strlen(trim((string) ($payload['reason'] ?? ''))) < 5) {
        rmg_guard_fail(422, 'A reason of at least 5 characters is required.', 'reason_required');
    }
    $token = rmg_guard_token($payload);
    $target = rmg_guard_target($targetRole, $targetRef);
    $payloadHash = SecurityCodeService::canonicalHash($payload);

    if ($token === '') {
        $service->recordEvent($userId, $username, 'blocked', 'missing code for ' . $action . ' ' . $target);
        rmg_guard_fail(
            403,
            'Security authorization is required for this action.',
            'security_required'
        );
    }

    if (!$service->consumeToken($token, $userId, $action, $target, $payloadHash)) {
        $service->recordEvent($userId, $username, 'blocked', 'invalid code for ' . $action . ' ' . $target);
        rmg_guard_fail(
            403,
            'The security authorization is invalid, expired, or already used. Please verify the code again.',
            'security_invalid'
        );
    }

    $service->recordEvent($userId, $username, 'used', $action . ' ' . $target);
    if (in_array($action, ['archive', 'restore'], true)) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/ArchiveLifecycle.php';
        try {
            $result = \Admin\Services\RoleManagement\ArchiveLifecycle::transition($db,
                ['id' => $userId, 'username' => $username, 'role' => $actor['role']],
                $targetRole, (string) $targetRef, $action, (string) $payload['reason'], $payload);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result);
            exit;
        } catch (\InvalidArgumentException $e) {
            rmg_guard_fail(422, $e->getMessage(), 'archive_validation');
        } catch (\Throwable $e) {
            error_log('Admin archive transition failed: ' . $e->getMessage());
            rmg_guard_fail(409, $e->getMessage(), 'archive_failed');
        }
    }
}

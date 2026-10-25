<?php declare(strict_types=1);

/**
 * Server-side authorization for the unified Role Management endpoints.
 *
 * Only admin and super_admin sessions may read or change role-management data.
 * Super-admin-only rules are enforced here, never trusted from the client.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/auth/Session.php';

function rmg_fail(int $code, string $message): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Require an authenticated admin/super_admin actor.
 *
 * @return array{role:string,user:array,is_super:bool}
 */
function rmg_actor(): array
{
    \App\Auth\Session::start();

    $user = $_SESSION['user'] ?? null;
    if (!is_array($user) || empty($user)) {
        rmg_fail(401, 'Authentication required.');
    }

    $role = strtolower(trim((string)($user['role'] ?? '')));
    if (!in_array($role, ['admin', 'super_admin'], true)) {
        rmg_fail(403, 'Forbidden');
    }

    return ['role' => $role, 'user' => $user, 'is_super' => $role === 'super_admin'];
}

function rmg_is_super(array $actor): bool
{
    return ($actor['is_super'] ?? false) === true;
}

function rmg_require_super(array $actor): void
{
    if (!rmg_is_super($actor)) {
        rmg_fail(403, 'This action requires a Super Admin account.');
    }
}

/**
 * Whether the actor may manage an account holding the given role.
 * Admin and Super Admin accounts are Super-Admin-only.
 */
function rmg_can_manage_role(array $actor, string $targetRole): bool
{
    $targetRole = strtolower(trim($targetRole));
    if (in_array($targetRole, ['admin', 'super_admin'], true)) {
        return rmg_is_super($actor);
    }
    return true;
}

/**
 * Read a JSON request body, falling back to POST/GET.
 */
function rmg_input(): array
{
    $raw = file_get_contents('php://input');
    $json = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($json)) {
        return $json;
    }
    if (!empty($_POST)) {
        return $_POST;
    }
    return $_GET;
}

/**
 * Write an audit entry for a role-management action without letting an
 * audit failure break the action itself.
 */
function rmg_audit(string $action, string $description, array $actor, array $extra = [], string $status = 'Success', ?string $recordId = null): void
{
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';
        $user = $actor['user'] ?? [];
        $audit = new \Finance\Models\AuditTrailModel();
        $audit->log(
            'RoleManagement',
            $action,
            $description,
            isset($user['id']) ? (int)$user['id'] : null,
            $user['username'] ?? null,
            $user['role'] ?? null,
            $recordId,
            $extra,
            $status
        );
    } catch (Throwable $ignore) {
    }
}

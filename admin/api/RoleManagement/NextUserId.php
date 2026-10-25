<?php declare(strict_types=1);

/**
 * Next User ID preview for the Role Management Add User form.
 *
 * Returns the ID the selected role would receive. The value is a preview
 * only — the real ID is allocated atomically when the account is saved, so a
 * number claimed by another administrator in the meantime is skipped
 * automatically.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/SecurityCodeService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/UserIdService.php';

use Admin\Services\RoleManagement\SecurityCodeService;
use Admin\Services\RoleManagement\UserIdService;
use App\Core\Database;

$actor = rmg_actor();

header('Cache-Control: no-store');

$role = strtolower(trim((string)($_GET['role'] ?? '')));

$label = SecurityCodeService::targetLabel($role);

if (!SecurityCodeService::actionAllowed($actor['role'], 'add', $label)) {
    rmg_fail(403, 'You are not allowed to create this account type.');
}

$scope = UserIdService::scopeForRole($role);

if ($scope === '') {
    rmg_fail(400, 'Unknown role.');
}

try {
    $db = Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'User ID service is unavailable. Please try again.');
}

try {
    UserIdService::ensureSchema($db);
    $service = new UserIdService($db);

    echo json_encode([
        'success' => true,
        'user_id' => $service->preview($scope),
        'role' => $role,
        'preview' => true,
    ]);
} catch (Throwable $e) {
    error_log('NextUserId preview failed: ' . $e->getMessage());
    rmg_fail(500, 'Could not generate the next User ID. Please try again.');
}

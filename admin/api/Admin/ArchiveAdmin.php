<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/api/Admin/_auth_guard.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Admin/ArchiveAdmin.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Admin/ArchiveAdmin.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Admin/ArchiveAdmin.php';

use App\Core\Database;

$actorRole = admin_api_actor_role();

$input = json_decode(file_get_contents('php://input'), true);
$targetId = is_array($input) ? (int)($input['id'] ?? 0) : 0;
if ($targetId <= 0) {
    admin_api_fail(400, 'Invalid administrator ID.');
}

// Only a Super Admin may archive a privileged account.
$targetRole = admin_api_current_role($targetId);
if ($targetRole === admin_api_privileged_role() && !admin_api_is_super($actorRole)) {
    admin_api_fail(403, 'Forbidden');
}

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/api/RoleManagement/_security_guard.php';

rmg_guard_require('archive', $targetRole, (string) $targetId);

header(
    'Content-Type: application/json'
);

$conn =
    Database::get();

$model =
    new ArchiveAdminModel(
        $conn
    );

$service =
    new ArchiveAdminService(
        $model
    );

$controller =
    new ArchiveAdminController(
        $service
    );

$controller
    ->archiveAdmin();
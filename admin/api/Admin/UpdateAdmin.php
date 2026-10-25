<?php

/**
 * Update an administrator account.
 *
 * Authorization is resolved here, before the controller runs:
 *   - a valid session is required;
 *   - the caller must hold an administrator role;
 *   - the target's existing role is read from the database, never from the
 *     request;
 *   - only a super admin may change a super_admin account, including their own;
 *   - only a super admin may grant the super_admin role;
 *   - a submitted role must be one this system supports.
 */

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/api/Admin/_auth_guard.php';

use App\Core\Database;

$actorRole = admin_api_actor_role();

$targetId = (int) ($_POST['id'] ?? 0);

if ($targetId <= 0) {
    admin_api_fail(400, 'Invalid administrator ID.');
}

$currentRole = admin_api_current_role($targetId);

if ($currentRole === '') {
    admin_api_fail(404, 'Administrator not found.');
}

// A super_admin account may only be touched by a super admin. This covers
// promotion, demotion, and edits to the account itself, which would otherwise
// let a normal admin reset a super admin's credentials.
if ($currentRole === admin_api_privileged_role() && !admin_api_is_super($actorRole)) {
    admin_api_fail(403, 'Forbidden');
}

// A role that was actually submitted is client input and must be allow-listed.
// When none is submitted the target keeps the role it already has, which stops
// an edit from silently demoting a super admin back to admin.
if (isset($_POST['role']) && trim((string) $_POST['role']) !== '') {
    $requestedRole = admin_api_clean_role($_POST['role']);

    if ($requestedRole === '') {
        admin_api_fail(400, 'Unsupported role.');
    }

    if (!admin_api_can_assign($actorRole, $requestedRole)) {
        admin_api_fail(403, 'Forbidden');
    }

    $_POST['role'] = $requestedRole;
} else {
    $_POST['role'] = $currentRole;
}

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/api/RoleManagement/_security_guard.php';

rmg_guard_require('edit', $currentRole, (string) $targetId);

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Admin/UpdateAdmin.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Admin/UpdateAdmin.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Admin/UpdateAdmin.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

try{

    $conn =
        Database::get();

    $model =
        new UpdateAdminModel(
            $conn
        );

    $service =
        new UpdateAdminService(
            $model
        );

    $controller =
        new UpdateAdminController(
            $service
        );

    $controller
        ->updateAdmin();

}catch(Throwable $e){

    error_log(
        'UpdateAdmin failed: ' . $e->getMessage()
    );

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header(
        'Content-Type: application/json; charset=utf-8'
    );
    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' =>
            'Unable to update the administrator account.'

    ]);

}

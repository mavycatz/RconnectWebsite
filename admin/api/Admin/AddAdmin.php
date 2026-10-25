<?php

/**
 * Create an administrator account.
 *
 * Authorization is resolved here, before the controller runs:
 *   - a valid session is required;
 *   - the caller must hold an administrator role;
 *   - the submitted role must be one this system supports;
 *   - only a super admin may create another super admin.
 *
 * The role handed to the controller is the validated one, so the value that
 * reaches the INSERT is never the raw request value.
 */

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/api/Admin/_auth_guard.php';

use App\Core\Database;

$actorRole = admin_api_actor_role();

// Absent role means a normal administrator account.
$requestedRole = admin_api_clean_role($_POST['role'] ?? 'admin');

if ($requestedRole === '') {
    admin_api_fail(400, 'Unsupported role.');
}

if (!admin_api_can_assign($actorRole, $requestedRole)) {
    admin_api_fail(403, 'Forbidden');
}

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/api/RoleManagement/_security_guard.php';

rmg_guard_require('add', $requestedRole, null);

$_POST['role'] = $requestedRole;

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Admin/AddAdmin.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Admin/AddAdmin.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Admin/AddAdmin.php';

header(
    'Content-Type: application/json; charset=utf-8'
);

try{

    $conn =
        Database::get();

    $adminModel =
        new AddAdminModel(
            $conn
        );

    $adminService =
        new AddAdminService(
            $adminModel
        );

    $adminController =
        new AddAdminController(
            $adminService
        );

    $adminController
        ->addAdmin();

}catch(Throwable $e){

    error_log(
        'AddAdmin failed: ' . $e->getMessage()
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
            'Unable to create the administrator account.'

    ]);

}

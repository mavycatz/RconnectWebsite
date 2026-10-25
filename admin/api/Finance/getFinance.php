<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Finance/FinanceEmployeeModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Finance/FinanceEmployeeService.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Finance/FinanceEmployeeController.php';

use App\Core\Database;

header(
    'Content-Type: application/json'
);

$conn =
    Database::get();

$model =
    new FinanceEmployeeModel(
        $conn
    );

$service =
    new FinanceEmployeeService(
        $model
    );

$controller =
    new FinanceEmployeeController(
        $service
    );

$action =
    $_REQUEST['action']
    ?? '';

$securityAction = [
    'addEmployee' => 'add',
    'updateEmployee' => 'edit',
    'archiveEmployee' => 'archive',
    'restoreEmployee' => 'restore',
][$action] ?? null;

if ($securityAction !== null) {

    require_once $_SERVER['DOCUMENT_ROOT']
    . '/admin/api/RoleManagement/_security_guard.php';

    rmg_guard_require(
        $securityAction,
        (string)($_POST['role'] ?? 'finance_officer'),
        (string)($_POST['id'] ?? '')
    );
}

switch($action){

    case 'getStats':

        $controller
            ->getStats();

        break;

    case 'getEmployees':

        $controller
            ->getEmployees();

        break;

    case 'getEmployee':

        $controller
            ->getEmployee();

        break;

    case 'getArchivedEmployees':

        $controller
            ->getArchivedEmployees();

        break;

    case 'addEmployee':

        $controller
            ->addEmployee();

        break;

    case 'updateEmployee':

        $controller
            ->updateEmployee();

        break;

    case 'archiveEmployee':

        $controller
            ->archiveEmployee();

        break;

    case 'restoreEmployee':

        $controller
            ->restoreEmployee();

        break;

    default:

        echo json_encode([

            'success' => false,

            'message' => 'Invalid action'

        ]);

        break;
}
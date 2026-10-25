<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Driver/DriverView.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Driver/DriverView.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Driver/DriverView.php';

use App\Core\Database;

header(
    'Content-Type: application/json'
);

$conn =
    Database::get();

$model =
    new DriverModel(
        $conn
    );

$service =
    new DriverService(
        $model
    );

$controller =
    new DriverController(
        $service
    );

$action =
    $_REQUEST['action']
    ?? '';

$securityAction = [
    'addDriver' => 'add',
    'updateDriver' => 'edit',
    'archiveDriver' => 'archive',
    'unarchiveDriver' => 'restore',
][$action] ?? null;

if ($securityAction !== null) {

    require_once $_SERVER['DOCUMENT_ROOT']
    . '/admin/api/RoleManagement/_security_guard.php';

    $securityRef = (string)(
        $_POST['id']
        ?? $_REQUEST['id']
        ?? ''
    );

    rmg_guard_require($securityAction, 'driver', $securityRef);
}

switch($action){

    case 'getDrivers':

        $controller
            ->getDrivers();

        break;

    case 'getDriver':

        $controller
            ->getDriver();

        break;

    case 'addDriver':

        $controller
            ->addDriver();

        break;

    case 'updateDriver':

        $controller
            ->updateDriver();

        break;

    case 'getArchivedDrivers':

        $controller
            ->getArchivedDrivers();

        break;

    case 'archiveDriver':

        $controller
            ->archiveDriver();

        break;
        
        case 'unarchiveDriver':

    $controller
        ->unarchiveDriver();

    break;

    case 'approveDriver':

        $controller
            ->approveDriver();

        break;

    case 'rejectDriver':

        $controller
            ->rejectDriver();

        break;

    default:

        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);

        break;
}
<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Driver/DriverAccountModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Driver/DriverAccountService.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Driver/DriverAccountController.php';

use App\Core\Database;

header(
    'Content-Type: application/json'
);

try{

    $conn =
        Database::get();

    $driverAccountModel =
        new DriverAccountModel(
            $conn
        );

    $driverAccountService =
        new DriverAccountService(
            $driverAccountModel
        );

    $driverAccountController =
        new DriverAccountController(
            $driverAccountService
        );

    $action =
        $_GET['action'] ?? '';

    switch($action){

        case 'add_driver':

            require_once $_SERVER['DOCUMENT_ROOT']
            . '/admin/api/RoleManagement/_security_guard.php';

            rmg_guard_require('add', 'driver', null);

            $driverAccountController
                ->addDriver();

            break;

        default:

            echo json_encode([

                'success' =>
                    false,

                'message' =>
                    'Invalid action'

            ]);

    }

}catch(Throwable $e){

    http_response_code(
        500
    );

    echo json_encode([

        'success' =>
            false,

        'message' =>
            $e->getMessage(),

        'file' =>
            $e->getFile(),

        'line' =>
            $e->getLine()

    ]);
}
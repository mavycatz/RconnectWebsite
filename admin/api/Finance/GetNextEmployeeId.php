<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/RoleManagement/UserIdService.php';

use App\Core\Database;
use Admin\Services\RoleManagement\UserIdService;

header(
    'Content-Type: application/json'
);

try{

    $conn =
        Database::get();

    $service =
        new UserIdService(
            $conn
        );

    $employeeId =
        $service->preview(
            'finance'
        );

    echo json_encode([

        'success' => true,

        'employee_id' =>
            $employeeId

    ]);

}catch(Throwable $e){

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage(),

        'file' =>
            $e->getFile(),

        'line' =>
            $e->getLine()

    ]);

}
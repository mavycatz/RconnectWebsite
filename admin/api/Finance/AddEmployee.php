<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Finance/AddEmployeeModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Finance/AddEmployeeService.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Finance/AddEmployeeController.php';

use App\Core\Database;

header(
    'Content-Type: application/json'
);

try{

    require_once $_SERVER['DOCUMENT_ROOT']
    . '/admin/api/RoleManagement/_security_guard.php';

    rmg_guard_require(
        'add',
        (string)($_POST['role'] ?? 'finance_officer'),
        null
    );

    $conn =
        Database::get();

    $model =
        new AddEmployeeModel(
            $conn
        );

    $service =
        new AddEmployeeService(
            $model
        );

    $controller =
        new AddEmployeeController(
            $service
        );

    $controller
        ->addEmployee();

}catch(Throwable $e){

    error_log(
        'AddEmployee failed: ' . $e->getMessage()
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
            'Unable to create the finance account.'

    ]);

}
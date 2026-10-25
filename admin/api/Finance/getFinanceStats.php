<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Finance/FinanceStats.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Finance/FinanceStats.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Finance/FinanceStats.php';

use App\Core\Database;

header(
    'Content-Type: application/json'
);

$conn =
    Database::get();

$model =
    new FinanceDepartmentModel(
        $conn
    );

$service =
    new FinanceDepartmentService(
        $model
    );

$controller =
    new FinanceDepartmentController(
        $service
    );

$action =
    $_REQUEST['action']
    ?? '';

switch($action){

    case 'getStats':

        $controller
            ->getStats();

        break;

    default:

        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);

        break;
}
<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Inspectors/GetInspectorsModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Inspectors/GetInspectors.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Inspectors/GetInspectors.php';

use App\Core\Database;

header(
    'Content-Type: application/json'
);

try{

    $conn =
        Database::get();

    $model =
        new GetInspectorsModel(
            $conn
        );

    $service =
        new GetInspectorsService(
            $model
        );

    $controller =
        new GetInspectorsController(
            $service
        );

    $controller
        ->getInspectors();

}catch(Throwable $e){

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()

    ]);

}
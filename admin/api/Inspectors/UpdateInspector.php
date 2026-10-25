<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Inspectors/UpdateInspectorModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Inspectors/UpdateInspector.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Inspectors/UpdateInspector.php';

use App\Core\Database;

header(
    'Content-Type: application/json'
);

try{

    require_once $_SERVER['DOCUMENT_ROOT']
    . '/admin/api/RoleManagement/_security_guard.php';

    rmg_guard_require(
        'edit',
        (string)($_POST['role'] ?? 'inspector'),
        (string)($_POST['inspector_pk'] ?? '')
    );

    $conn =
        Database::get();

    $model =
        new UpdateInspectorModel(
            $conn
        );

    $service =
        new UpdateInspectorService(
            $model
        );

    $controller =
        new UpdateInspectorController(
            $service
        );

    $controller
        ->updateInspector();

}catch(Throwable $e){

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()

    ]);

}
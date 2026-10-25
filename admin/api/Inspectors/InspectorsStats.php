<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Inspectors/InspectorsStats.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Inspectors/InspectorsStats.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Inspectors/InspectorsStats.php';

use App\Core\Database;

header(
    'Content-Type: application/json'
);

$conn =
    Database::get();

$model =
    new InspectorModel(
        $conn
    );

$service =
    new InspectorService(
        $model
    );

$controller =
    new InspectorController(
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
        
    case 'addInspector':

    $controller
        ->addInspector();

    break;

    default:

        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);

        break;
}


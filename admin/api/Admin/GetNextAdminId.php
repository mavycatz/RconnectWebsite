<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/admin/models/Admin/GetNextAdminId.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/admin/services/Admin/GetNextAdminId.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/admin/Controllers/Admin/GetNextAdminId.php';

use App\Core\Database;

header('Content-Type: application/json');

try {
    $conn =
        Database::get();

    $model =
        new GetNextAdminIdModel(
            $conn
        );

    $service =
        new GetNextAdminIdService(
            $model
        );

    $controller =
        new GetNextAdminIdController(
            $service
        );

    $controller
        ->getNextId();
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Admin/AdminStats.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Admin/AdminStats.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Admin/AdminStats.php';

use App\Core\Database;

header(
    'Content-Type: application/json'
);

$conn =
    Database::get();

$adminModel =
    new AdminModel(
        $conn
    );

$adminService =
    new AdminService(
        $adminModel
    );

$adminController =
    new AdminController(
        $adminService
    );

$action =
    $_GET['action']
    ?? '';

switch($action){

    case 'getAdminStats':

        $adminController
            ->getAdminStats();

        break;

    default:

        echo json_encode([
            'success' => false,
            'message' => 'Invalid Action'
        ]);

}
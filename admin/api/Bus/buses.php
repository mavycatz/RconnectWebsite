<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Bus/BusModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Bus/BusService.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Bus/BusController.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;

Session::start();
header(
    'Content-Type: application/json'
);

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn =
    Database::get();

$busModel =
    new BusModel(
        $conn
    );

$busService =
    new BusService(
        $busModel
    );

$busController =
    new BusController(
        $busService
    );

$action =
    $_GET['action'] ?? '';

switch($action){

    case 'get_all_buses':

        $busController
            ->getAssignableBuses();

        break;

    default:

        echo json_encode([
            'success' => false
        ]);

}
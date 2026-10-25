<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/Driver/DriverModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Driver/DriverService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/Controllers/Driver/DriverController.php';

use App\Core\Database;

header('Content-Type: application/json');

try{

    $conn =
        Database::get();

    $driverModel =
        new DriverModel(
            $conn
        );

    $driverService =
        new DriverService(
            $driverModel
        );

    $driverController =
        new DriverController(
            $driverService
        );

    $action =
        $_GET['action'] ?? '';

    switch($action){

        case 'get_total_drivers':

            $driverController
                ->getTotalDrivers();

            break;

        case 'get_driver_stats':

            $driverController
                ->getDriverStats();

            break;

        default:

            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);

            break;
    }

}catch(Throwable $e){

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine()
    ]);

}
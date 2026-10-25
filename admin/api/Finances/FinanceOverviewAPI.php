<?php declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ERROR REPORTING
|--------------------------------------------------------------------------
*/

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| JSON HEADER
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| REQUIRE CONTROLLER
|--------------------------------------------------------------------------
*/

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/Controllers/FinanceController.php';

use Admin\Controllers\FinanceController;

try {

    /*
    |--------------------------------------------------------------------------
    | GET FILTERS
    |--------------------------------------------------------------------------
    */

    $filters = [

        'search' => trim($_GET['search'] ?? ''),

        'category' => trim($_GET['category'] ?? ''),

        'from' => trim($_GET['from'] ?? ''),

        'to' => trim($_GET['to'] ?? ''),

    ];

    /*
    |--------------------------------------------------------------------------
    | CONTROLLER
    |--------------------------------------------------------------------------
    */

    $controller = new FinanceController();

    $data = $controller->getFinanceOverview($filters);

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'filters' => $filters,

        'data' => $data

    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage(),

        'file' => $e->getFile(),

        'line' => $e->getLine()

    ]);
}
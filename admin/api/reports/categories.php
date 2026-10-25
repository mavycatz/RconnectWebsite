<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT']
.'/vendor/autoload.php';

use admin\Controllers\CategoriesReportController;

header('Content-Type: application/json');

try{

    /*
    |--------------------------------------------------------------------------
    | CONTROLLER
    |--------------------------------------------------------------------------
    */

    $controller =

        new CategoriesReportController();

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'data' => [

            'generated_reports' =>

                $controller
                    ->generatedReports(),

            'ai_trip_operations_reports' =>

                $controller
                    ->aiTripOperationReports(),

            'trip_operations_reports' =>

                $controller
                    ->tripOperationReports()
        ]
    ]);

}catch(Throwable $e){

    /*
    |--------------------------------------------------------------------------
    | ERROR
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage()
    ]);
}
<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Core\Database;

header('Content-Type: application/json');

try{

    /*
    |--------------------------------------------------------------------------
    | DATABASE CONNECTION
    |--------------------------------------------------------------------------
    */

    $conn = Database::get();


    /*
    |--------------------------------------------------------------------------
    | TRIP DISTRIBUTION
    |--------------------------------------------------------------------------
    */

    $query =
        $conn->query("

            SELECT

                CASE

                    WHEN trip_type = 'Regular'
                    THEN 'Normal Trips'

                    WHEN trip_type = 'Special'
                    THEN 'Special Trips'

                    ELSE 'Other Trips'

                END AS trip_category,

                COUNT(*) AS total

            FROM bus_trips

            GROUP BY trip_type

            ORDER BY total DESC

        ");


    /*
    |--------------------------------------------------------------------------
    | FORMAT RESULTS
    |--------------------------------------------------------------------------
    */

    $distribution = [];


    while(
        $row =
            $query->fetch(PDO::FETCH_ASSOC)
    ){

        $distribution[
            $row['trip_category']
        ] = (int)$row['total'];

    }


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'distribution' =>
            $distribution

    ]);

}catch(Throwable $e){

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage(),

        'line' =>
            $e->getLine(),

        'file' =>
            $e->getFile()

    ]);

}
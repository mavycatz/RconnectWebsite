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
    | PASSENGER DISTRIBUTION
    |--------------------------------------------------------------------------
    */

    $query =
        $conn->query("

            SELECT passenger_type, SUM(total) AS total
            FROM (

                SELECT

                    COALESCE(
                        p.discount_type,
                        'Regular'
                    ) AS passenger_type,

                    COUNT(*) AS total

                FROM passenger_trips pt

                INNER JOIN passengers p
                ON p.id = pt.passenger_id

                GROUP BY p.discount_type

                UNION ALL

                SELECT

                    COALESCE(
                        NULLIF(pth.passenger_type, ''),
                        'Regular'
                    ) AS passenger_type,

                    COUNT(*) AS total

                FROM passenger_trip_history pth

                GROUP BY pth.passenger_type

            ) combined

            GROUP BY passenger_type

            ORDER BY total DESC

        ");


    /*
    |--------------------------------------------------------------------------
    | FORMAT DATA
    |--------------------------------------------------------------------------
    */

    $distribution = [];

    while(
        $row =
            $query->fetch(PDO::FETCH_ASSOC)
    ){

        $type =
            $row['passenger_type']
            ?: 'Regular';

        $distribution[$type] =
            (int)$row['total'];

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
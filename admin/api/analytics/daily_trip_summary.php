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
    | DAILY TRIPS
    |--------------------------------------------------------------------------
    */

    $query =
        $conn->query("

            SELECT

                DAYNAME(trip_date) AS trip_day,

                COUNT(*) AS total_trips

            FROM bus_trips

            WHERE status = 'Completed'

            GROUP BY DAYOFWEEK(trip_date)

            ORDER BY DAYOFWEEK(trip_date)

        ");


    /*
    |--------------------------------------------------------------------------
    | DEFAULT DAYS
    |--------------------------------------------------------------------------
    */

    $days = [

        'Monday' => 0,
        'Tuesday' => 0,
        'Wednesday' => 0,
        'Thursday' => 0,
        'Friday' => 0,
        'Saturday' => 0,
        'Sunday' => 0

    ];


    /*
    |--------------------------------------------------------------------------
    | INSERT DATABASE VALUES
    |--------------------------------------------------------------------------
    */

    while(
        $row =
            $query->fetch(PDO::FETCH_ASSOC)
    ){

        $days[$row['trip_day']] =
            (int)$row['total_trips'];

    }


    /*
    |--------------------------------------------------------------------------
    | TOTAL & MAX
    |--------------------------------------------------------------------------
    */

    $totalTrips =
        array_sum($days);

    $maxTrips =
        max($days);


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'days' =>
            $days,

        'total_trips' =>
            $totalTrips,

        'max_trips' =>
            $maxTrips

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
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
    | MONTHLY FINANCIAL DATA
    |--------------------------------------------------------------------------
    */

    $query =
        $conn->query("

            SELECT month_label, SUM(earnings) AS earnings
            FROM (

                SELECT

                    DATE_FORMAT(
                        boarding_time,
                        '%b %Y'
                    ) AS month_label,

                    YEAR(boarding_time) * 100 + MONTH(boarding_time) AS sort_key,

                    SUM(fare) AS earnings

                FROM passenger_trips

                WHERE payment_status = 'paid'

                GROUP BY
                    YEAR(boarding_time),
                    MONTH(boarding_time)

                UNION ALL

                SELECT

                    DATE_FORMAT(
                        trip_date,
                        '%b %Y'
                    ) AS month_label,

                    YEAR(trip_date) * 100 + MONTH(trip_date) AS sort_key,

                    SUM(fare) AS earnings

                FROM passenger_trip_history

                GROUP BY
                    YEAR(trip_date),
                    MONTH(trip_date)

            ) combined

            GROUP BY month_label

            ORDER BY MIN(sort_key) ASC

        ");


    /*
    |--------------------------------------------------------------------------
    | FORMAT RESULTS
    |--------------------------------------------------------------------------
    */

    $labels = [];

    $earnings = [];

    $expenses = [];


    while(
        $row =
            $query->fetch(PDO::FETCH_ASSOC)
    ){

        $month =
            $row['month_label'];

        $monthlyEarnings =
            (float)$row['earnings'];

        /*
        |--------------------------------------------------------------------------
        | SAMPLE EXPENSE ESTIMATION
        |--------------------------------------------------------------------------
        */

        $monthlyExpenses =
            $monthlyEarnings * 0.35;


        $labels[] =
            $month;

        $earnings[] =
            round($monthlyEarnings,2);

        $expenses[] =
            round($monthlyExpenses,2);

    }


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'labels' =>
            $labels,

        'earnings' =>
            $earnings,

        'expenses' =>
            $expenses

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
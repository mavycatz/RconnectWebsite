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
    | CURRENT PASSENGERS
    |--------------------------------------------------------------------------
    */

    $currentPassengers =
        $conn->query("
            SELECT COUNT(*)
            FROM passenger_trips
        ")->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | HISTORICAL PASSENGERS
    |--------------------------------------------------------------------------
    */

    $historicalPassengers =
        $conn->query("
            SELECT COUNT(*)
            FROM passenger_trip_history
        ")->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL PASSENGERS
    |--------------------------------------------------------------------------
    */

    $totalPassengers =
        $currentPassengers +
        $historicalPassengers;


    /*
    |--------------------------------------------------------------------------
    | CURRENT REVENUE
    |--------------------------------------------------------------------------
    */

    $currentRevenue =
        $conn->query("
            SELECT IFNULL(SUM(fare),0)
            FROM passenger_trips
            WHERE LOWER(TRIM(payment_status)) = 'paid'
        ")->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | HISTORICAL REVENUE
    |--------------------------------------------------------------------------
    */

    $historicalRevenue =
        $conn->query("
            SELECT IFNULL(SUM(fare),0)
            FROM passenger_trip_history
        ")->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL REVENUE
    |--------------------------------------------------------------------------
    */

    $totalRevenue =
        $currentRevenue +
        $historicalRevenue;


    /*
    |--------------------------------------------------------------------------
    | TOTAL TRIPS
    |--------------------------------------------------------------------------
    */

    $totalTrips =
        $conn->query("
            SELECT COUNT(DISTINCT trip_no)
            FROM passenger_trips
        ")->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | PASSENGERS PER TRIP
    |--------------------------------------------------------------------------
    */

    $passengersPerTrip =
        $totalTrips > 0
            ? round(
                $totalPassengers /
                $totalTrips,
                1
            )
            : 0;


    /*
    |--------------------------------------------------------------------------
    | PROFIT MARGIN
    |--------------------------------------------------------------------------
    */

    $estimatedExpenses =
        $totalRevenue * 0.35;

    $profitMargin =
        $totalRevenue > 0
            ? round(
                (
                    ($totalRevenue - $estimatedExpenses)
                    /
                    $totalRevenue
                ) * 100,
                1
            )
            : 0;


    /*
    |--------------------------------------------------------------------------
    | FUEL EFFICIENCY
    |--------------------------------------------------------------------------
    */

    $fuelEfficiency =
        (float)$conn
            ->query("
                SELECT IFNULL(AVG(kpl), 0)
                FROM fuel_logs
                WHERE kpl IS NOT NULL AND kpl > 0
            ")
            ->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL FLEET
    |--------------------------------------------------------------------------
    */

    $totalFleet =
        $conn->query("
            SELECT COUNT(*)
            FROM buses
        ")->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | ACTIVE BUSES
    |--------------------------------------------------------------------------
    */

    $activeBuses =
        $conn->query("
            SELECT COUNT(DISTINCT bus_id)
            FROM bus_trips
            WHERE status IN (
                'Departed',
                'Completed'
            )
        ")->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | FLEET UTILIZATION
    |--------------------------------------------------------------------------
    */

    $fleetUtilization =
        $totalFleet > 0

            ? round(
                (
                    $activeBuses /
                    $totalFleet
                ) * 100,
                1
            )

            : 0;


    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,


        /*
        |--------------------------------------------------------------------------
        | MAIN VALUES
        |--------------------------------------------------------------------------
        */

        'profit_margin' =>
            $profitMargin,

        'passengers_per_trip' =>
            $passengersPerTrip,

        'fuel_efficiency' =>
            $fuelEfficiency,

        'fleet_utilization' =>
            $fleetUtilization,


        /*
        |--------------------------------------------------------------------------
        | DETAILS
        |--------------------------------------------------------------------------
        */

        'details' => [

            /*
            |--------------------------------------------------------------------------
            | PROFIT MARGIN
            |--------------------------------------------------------------------------
            */

            'profit_margin' => [

                'title' =>
                    'Profit Margin Analytics',

                'description' =>
                    'Shows the percentage of revenue retained after estimated operational expenses.',

                'formula' =>
                    '(Revenue - Expenses) / Revenue × 100',

                'breakdown' => '

                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Revenue</span>
                        <strong>₱'.number_format((float)$totalRevenue,2).'</strong>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Estimated Expenses</span>
                        <strong>₱'.number_format((float)$estimatedExpenses,2).'</strong>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span>Final Profit Margin</span>
                        <strong>'.$profitMargin.'%</strong>
                    </div>

                '

            ],


            /*
            |--------------------------------------------------------------------------
            | PASSENGERS PER TRIP
            |--------------------------------------------------------------------------
            */

            'passengers_per_trip' => [

                'title' =>
                    'Passengers Per Trip',

                'description' =>
                    'Measures the average number of passengers per recorded trip.',

                'formula' =>
                    'Total Passengers / Total Trips',

                'breakdown' => '

                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Passengers</span>
                        <strong>'.$totalPassengers.'</strong>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Trips</span>
                        <strong>'.$totalTrips.'</strong>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span>Average Result</span>
                        <strong>'.$passengersPerTrip.'</strong>
                    </div>

                '

            ],


            /*
            |--------------------------------------------------------------------------
            | FUEL EFFICIENCY
            |--------------------------------------------------------------------------
            */

            'fuel_efficiency' => [

                'title' =>
                    'Fuel Efficiency',

                'description' =>
                    'Estimated distance traveled per liter of fuel.',

                'formula' =>
                    'Distance / Fuel Consumption',

                'breakdown' => '

                    <div class="d-flex justify-content-between">
                        <span>Current Estimate</span>
                        <strong>'.$fuelEfficiency.' km/L</strong>
                    </div>

                '

            ],


            /*
            |--------------------------------------------------------------------------
            | FLEET UTILIZATION
            |--------------------------------------------------------------------------
            */

            'fleet_utilization' => [

                'title' =>
                    'Fleet Utilization',

                'description' =>
                    'Measures how many buses in the fleet are actively operating.',

                'formula' =>
                    'Active Buses / Total Fleet × 100',

                'breakdown' => '

                    <div class="d-flex justify-content-between mb-2">
                        <span>Active Buses</span>
                        <strong>'.$activeBuses.'</strong>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Total Fleet</span>
                        <strong>'.$totalFleet.'</strong>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span>Fleet Utilization</span>
                        <strong>'.$fleetUtilization.'%</strong>
                    </div>

                '

            ]

        ]

    ]);

}catch(Throwable $e){

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage(),

        'line' => $e->getLine(),

        'file' => $e->getFile()

    ]);

}
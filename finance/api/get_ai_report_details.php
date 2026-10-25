<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if(!defined('APP_LOADED')){
    define('APP_LOADED', true);
}

require_once __DIR__ .
'/../../app/init.php';

require_once __DIR__ .
'/../middleware/validate_request.php';

use App\Core\Database;

try{

    /*
    |--------------------------------------------------------------------------
    | DATABASE
    |--------------------------------------------------------------------------
    */

    $pdo = Database::get();

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    /*
    |--------------------------------------------------------------------------
    | INPUT
    |--------------------------------------------------------------------------
    */

    $id = (int)(
        $_GET['id'] ?? 0
    );

    if($id <= 0){

        echo json_encode([

            'success' => false,

            'message' =>
                'Invalid report ID.'

        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | GET AI REPORT
    |--------------------------------------------------------------------------
    */

    $sql = "

    SELECT

        id,
        report_title,
        report_content,
        report_type,
        prompt,
        generated_by,
        generated_at,
        created_at,

        'financial_ai'
        AS source_type

    FROM ai_reports

    WHERE id = :id

    LIMIT 1

    ";

    $stmt =
        $pdo->prepare($sql);

    $stmt->execute([

        ':id' => $id
    ]);

    $report =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | REPORT NOT FOUND
    |--------------------------------------------------------------------------
    */

    if(!$report){

        echo json_encode([

            'success' => false,

            'message' =>
                'Report not found.'

        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT REPORT CONTENT
    |--------------------------------------------------------------------------
    */

    $content = strtolower(

        $report['report_content']
        ??
        ''
    );

    $title = strtolower(

        $report['report_title']
        ??
        ''
    );

    /*
    |--------------------------------------------------------------------------
    | FLAGS
    |--------------------------------------------------------------------------
    */

    $needsPassengers =

        str_contains($content,'passenger')
        ||
        str_contains($title,'passenger');

    $needsRevenue =

        str_contains($content,'revenue')
        ||
        str_contains($content,'profit')
        ||
        str_contains($title,'revenue');

    $needsDrivers =

        str_contains($content,'driver')
        ||
        str_contains($title,'driver');

    $needsBuses =

        str_contains($content,'bus')
        ||
        str_contains($title,'bus');

    $needsRoutes =

        str_contains($content,'route')
        ||
        str_contains($title,'route');

    $needsRemittance =

        str_contains($content,'remittance')
        ||
        str_contains($title,'remittance');

    $analytics = [];

    /*
    |--------------------------------------------------------------------------
    | PASSENGERS
    |--------------------------------------------------------------------------
    */

    if($needsPassengers){

        $passengerQuery = "

        SELECT

            COUNT(*) AS total_passengers,

            COUNT(
                DISTINCT passenger_id
            ) AS unique_passengers

        FROM passenger_trips

        ";

        $passengerStmt =
            $pdo->query(
                $passengerQuery
            );

        $passengerData =
            $passengerStmt->fetch(
                PDO::FETCH_ASSOC
            );

        $analytics['total_passengers'] =

            (int)(
                $passengerData['total_passengers']
                ?? 0
            );

        $analytics['unique_passengers'] =

            (int)(
                $passengerData['unique_passengers']
                ?? 0
            );

        /*
        |--------------------------------------------------------------------------
        | PASSENGER TYPES
        |--------------------------------------------------------------------------
        */

        $passengerTypeQuery = "

        SELECT

            CASE

                WHEN passengers.discount_type IS NULL
                OR passengers.discount_type = ''

                THEN 'Regular'

                ELSE passengers.discount_type

            END AS passenger_type,

            COUNT(*) AS total

        FROM passenger_trips

        LEFT JOIN passengers

        ON passenger_trips.passenger_id =
           passengers.id

        GROUP BY passenger_type

        ORDER BY total DESC

        ";

        $passengerTypeStmt =
            $pdo->query(
                $passengerTypeQuery
            );

        $analytics['passenger_types'] =

            $passengerTypeStmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

    /*
    |--------------------------------------------------------------------------
    | REVENUE
    |--------------------------------------------------------------------------
    */

    if($needsRevenue){

        $revenueQuery = "

        SELECT

            COALESCE(
                SUM(fare),
                0
            ) AS total_revenue

        FROM passenger_trips

        WHERE payment_status = 'paid'

        ";

        $revenueStmt =
            $pdo->query(
                $revenueQuery
            );

        $revenueData =
            $revenueStmt->fetch(
                PDO::FETCH_ASSOC
            );

        $analytics['total_revenue'] =

            (float)(
                $revenueData['total_revenue']
                ?? 0
            );

        /*
        |--------------------------------------------------------------------------
        | EXPENSES
        |--------------------------------------------------------------------------
        */

$expenseQuery = "

SELECT

    COALESCE(
        SUM(fuel_cost),
        0
    ) AS total_expenses

FROM bus_trips

";

        $expenseStmt =
            $pdo->query(
                $expenseQuery
            );

        $expenseData =
            $expenseStmt->fetch(
                PDO::FETCH_ASSOC
            );

        $analytics['total_expenses'] =

            (float)(
                $expenseData['total_expenses']
                ?? 0
            );

        $analytics['net_profit'] =

            $analytics['total_revenue']
            -
            $analytics['total_expenses'];
    }

    /*
    |--------------------------------------------------------------------------
    | DRIVERS
    |--------------------------------------------------------------------------
    */

    if($needsDrivers){

        $driverQuery = "

        SELECT

            d.full_name,

            d.contact_number,

            d.license_type,

            d.experience_years,

            COUNT(bt.id) AS total_trips

        FROM drivers d

        LEFT JOIN bus_trips bt

        ON d.driver_id = bt.driver_id

        GROUP BY d.driver_id

        ORDER BY total_trips DESC

        LIMIT 10

        ";

        $driverStmt =
            $pdo->query(
                $driverQuery
            );

        $analytics['drivers'] =

            $driverStmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

    /*
    |--------------------------------------------------------------------------
    | BUSES
    |--------------------------------------------------------------------------
    */

    if($needsBuses){

        $busQuery = "

        SELECT

            b.bus_id,

            b.plate_number,

            b.bus_type,

            b.route_name,

            b.status,

            COUNT(bt.id) AS total_trips

        FROM buses b

        LEFT JOIN bus_trips bt

        ON b.bus_id = bt.bus_id

        GROUP BY b.bus_id

        ORDER BY total_trips DESC

        LIMIT 10

        ";

        $busStmt =
            $pdo->query(
                $busQuery
            );

        $analytics['top_buses'] =

            $busStmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

    /*
    |--------------------------------------------------------------------------
    | ROUTES
    |--------------------------------------------------------------------------
    */

    if($needsRoutes){

        $routeQuery = "

        SELECT

            route_name,

            COUNT(*) AS total_trips

        FROM bus_trips

        GROUP BY route_name

        ORDER BY total_trips DESC

        LIMIT 10

        ";

        $routeStmt =
            $pdo->query(
                $routeQuery
            );

        $analytics['top_routes'] =

            $routeStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        /*
        |--------------------------------------------------------------------------
        | ROUTE STOPS
        |--------------------------------------------------------------------------
        */

        $routeStopQuery = "

        SELECT

            route_name,
            stop_name,
            sequence_order

        FROM route_stops

        ORDER BY route_name,
                 sequence_order ASC

        ";

        $routeStopStmt =
            $pdo->query(
                $routeStopQuery
            );

        $analytics['route_stops'] =

            $routeStopStmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

    /*
    |--------------------------------------------------------------------------
    | REMITTANCES
    |--------------------------------------------------------------------------
    */

    if($needsRemittance){

        $remittanceQuery = "

        SELECT

            status,

            COUNT(*) AS total,

            SUM(total_collection)
            AS total_collection

        FROM remittance_logs

        GROUP BY status

        ";

        $remittanceStmt =
            $pdo->query(
                $remittanceQuery
            );

        $analytics['remittances'] =

            $remittanceStmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

    /*
    |--------------------------------------------------------------------------
    | EVIDENCES
    |--------------------------------------------------------------------------
    */

    $analytics['evidences'] = [

        'bus_trips' => [],
        'buses' => [],
        'drivers' => [],
        'driver_bus_assignment' => [],
        'inspectors' => [],
        'route_stops' => [],
        'remittance_logs' => [],
        'passengers' => [],
        'passenger_trips' => []
    ];

    /*
    |--------------------------------------------------------------------------
    | BUS TRIPS
    |--------------------------------------------------------------------------
    */

    $busTripStmt =
        $pdo->query("

        SELECT *

        FROM bus_trips

        ORDER BY created_at DESC

        LIMIT 10

        ");

    $analytics['evidences']['bus_trips'] =

        $busTripStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | BUSES
    |--------------------------------------------------------------------------
    */

    $busStmt =
        $pdo->query("

        SELECT *

        FROM buses

        LIMIT 10

        ");

    $analytics['evidences']['buses'] =

        $busStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | DRIVERS
    |--------------------------------------------------------------------------
    */

    $driverStmt =
        $pdo->query("

        SELECT *

        FROM drivers

        LIMIT 10

        ");

    $analytics['evidences']['drivers'] =

        $driverStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | DRIVER BUS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    $assignmentStmt =
        $pdo->query("

        SELECT *

        FROM driver_bus_assignment

        LIMIT 10

        ");

    $analytics['evidences']['driver_bus_assignment'] =

        $assignmentStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | INSPECTORS
    |--------------------------------------------------------------------------
    */

    $inspectorStmt =
        $pdo->query("

        SELECT *

        FROM inspectors

        LIMIT 10

        ");

    $analytics['evidences']['inspectors'] =

        $inspectorStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | ROUTE STOPS
    |--------------------------------------------------------------------------
    */

    $routeStopEvidenceStmt =
        $pdo->query("

        SELECT *

        FROM route_stops

        LIMIT 10

        ");

    $analytics['evidences']['route_stops'] =

        $routeStopEvidenceStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | REMITTANCE LOGS
    |--------------------------------------------------------------------------
    */

    $remittanceEvidenceStmt =
        $pdo->query("

        SELECT *

        FROM remittance_logs

        ORDER BY created_at DESC

        LIMIT 10

        ");

    $analytics['evidences']['remittance_logs'] =

        $remittanceEvidenceStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | PASSENGERS
    |--------------------------------------------------------------------------
    */

    $passengerStmt =
        $pdo->query("

        SELECT *

        FROM passengers

        LIMIT 10

        ");

    $analytics['evidences']['passengers'] =

        $passengerStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | PASSENGER TRIPS
    |--------------------------------------------------------------------------
    */

    $passengerTripStmt =
        $pdo->query("

        SELECT *

        FROM passenger_trips

        ORDER BY boarding_time DESC

        LIMIT 10

        ");

    $analytics['evidences']['passenger_trips'] =

        $passengerTripStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | FINAL ANALYTICS
    |--------------------------------------------------------------------------
    */

    $report['analytics'] =
        $analytics;

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'report' => $report

    ],

    JSON_PRETTY_PRINT
    |
    JSON_UNESCAPED_SLASHES

    );

}catch(Throwable $e){

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage(),

        'file' =>
            $e->getFile(),

        'line' =>
            $e->getLine(),

        'trace' =>
            $e->getTraceAsString()

    ],

    JSON_PRETTY_PRINT
    |
    JSON_UNESCAPED_SLASHES

    );
}
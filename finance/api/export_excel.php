<?php

error_reporting(0);

ini_set(
    'display_errors',
    0
);

require_once __DIR__ .
'/../../vendor/autoload.php';

if(!defined('APP_LOADED')){
    define('APP_LOADED', true);
}

require_once __DIR__ .
'/../../app/init.php';

require_once __DIR__ .
'/../middleware/validate_request.php';

use App\Core\Database;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$id =
    (int)(
        $_GET['id']
        ?? 0
    );

if($id <= 0){

    die('Invalid report ID.');
}

try{

    $pdo =
        Database::get();

    /*
    |--------------------------------------------------------------------------
    | GET REPORT
    |--------------------------------------------------------------------------
    */

    $reportQuery = "

    SELECT id, report_title AS title, report_content AS content, report_type AS type, generated_at, 'ai_reports' AS source
    FROM ai_reports
    WHERE id = :id

    ";

    $reportStmt =
        $pdo->prepare(
            $reportQuery
        );

    $reportStmt->execute([

        ':id' => $id
    ]);

    $report =
        $reportStmt->fetch();

    if(!$report){

        $reportQuery2 = "

        SELECT id, title AS title, report_data AS content, report_category AS type, generated_at, 'financial_reports' AS source
        FROM financial_reports
        WHERE id = :id2
        LIMIT 1

        ";

        $reportStmt2 =
            $pdo->prepare(
                $reportQuery2
            );

        $reportStmt2->execute([

            ':id2' => $id
        ]);

        $report =
            $reportStmt2->fetch();
    }

    if(!$report){

        die('Report not found.');
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE SPREADSHEET
    |--------------------------------------------------------------------------
    */

    $spreadsheet =
        new Spreadsheet();

    /*
    |--------------------------------------------------------------------------
    | COVER SHEET
    |--------------------------------------------------------------------------
    */

    $coverSheet =
        $spreadsheet
        ->getActiveSheet();

    $coverSheet->setTitle(
        'AI Report'
    );

    $coverSheet->setCellValue(
        'A1',
        'Transportation Intelligence Report'
    );

    $coverSheet->setCellValue(
        'A3',
        'Report Title'
    );

    $coverSheet->setCellValue(
        'B3',
        $report['report_title']
    );

    $coverSheet->setCellValue(
        'A4',
        'Generated At'
    );

    $coverSheet->setCellValue(
        'B4',
        $report['generated_at']
    );

    $coverSheet->setCellValue(
        'A6',
        'Transportation Analysis'
    );

    $coverSheet->setCellValue(
        'A7',

        str_replace(

            ['***','**','*','##'],

            '',

            $report['report_content']
        )
    );

    $coverSheet
        ->mergeCells(
            'A1:D1'
        );

    $coverSheet
        ->getStyle('A1')
        ->getFont()
        ->setBold(true)
        ->setSize(18);

    $coverSheet
        ->getStyle('A3:A6')
        ->getFont()
        ->setBold(true);

    $coverSheet
        ->getColumnDimension('A')
        ->setWidth(28);

    $coverSheet
        ->getColumnDimension('B')
        ->setWidth(80);

    $coverSheet
        ->getStyle('A7')
        ->getAlignment()
        ->setWrapText(true);

    /*
    |--------------------------------------------------------------------------
    | PASSENGER TRIPS
    |--------------------------------------------------------------------------
    */

    $tripSheet =
        $spreadsheet
        ->createSheet();

    $tripSheet->setTitle(
        'Passenger Trips'
    );

    $tripHeaders = [

        'Trip ID',
        'Passenger ID',
        'Bus Number',
        'Destination',
        'Fare',
        'Payment Method',
        'Payment Status',
        'Boarding Time'

    ];

    $col = 'A';

    foreach($tripHeaders as $header){

        $tripSheet->setCellValue(
            $col . '1',
            $header
        );

        $tripSheet
            ->getStyle(
                $col . '1'
            )
            ->getFont()
            ->setBold(true);

        $tripSheet
            ->getColumnDimension($col)
            ->setWidth(24);

        $col++;
    }

    $tripQuery = "

    SELECT

        id,
        passenger_id,
        bus_no,
        destination,
        fare,
        payment_method,
        payment_status,
        boarding_time

    FROM passenger_trips

    ORDER BY boarding_time DESC

    ";

    $tripStmt =
        $pdo->query(
            $tripQuery
        );

    $trips =
        $tripStmt->fetchAll();

    $row = 2;

    foreach($trips as $trip){

        $tripSheet->setCellValue(
            'A'.$row,
            $trip['id']
        );

        $tripSheet->setCellValue(
            'B'.$row,
            $trip['passenger_id']
        );

        $tripSheet->setCellValue(
            'C'.$row,
            $trip['bus_no']
        );

        $tripSheet->setCellValue(
            'D'.$row,
            $trip['destination']
        );

        $tripSheet->setCellValue(
            'E'.$row,
            $trip['fare']
        );

        $tripSheet->setCellValue(
            'F'.$row,
            $trip['payment_method']
        );

        $tripSheet->setCellValue(
            'G'.$row,
            $trip['payment_status']
        );

        $tripSheet->setCellValue(
            'H'.$row,
            $trip['boarding_time']
        );

        $row++;
    }

    /*
    |--------------------------------------------------------------------------
    | PASSENGER ANALYTICS
    |--------------------------------------------------------------------------
    */

    $analyticsSheet =
        $spreadsheet
        ->createSheet();

    $analyticsSheet->setTitle(
        'Passenger Analytics'
    );

    $analyticsHeaders = [

        'Passenger Type',
        'Total Passengers',
        'Transportation Insight'

    ];

    $col = 'A';

    foreach($analyticsHeaders as $header){

        $analyticsSheet->setCellValue(
            $col . '1',
            $header
        );

        $analyticsSheet
            ->getStyle(
                $col . '1'
            )
            ->getFont()
            ->setBold(true);

        $analyticsSheet
            ->getColumnDimension($col)
            ->setWidth(35);

        $col++;
    }

    $passengerTypeQuery = "

    SELECT

        CASE

            WHEN passengers.discount_type IS NULL
            OR passengers.discount_type = ''
            THEN 'Regular'

            ELSE passengers.discount_type

        END AS passenger_type,

        COUNT(*) as total

    FROM passenger_trips

    LEFT JOIN passengers

    ON passenger_trips.passenger_id =
       passengers.id

    GROUP BY passenger_type

    ORDER BY total DESC

    ";

    $typeStmt =
        $pdo->query(
            $passengerTypeQuery
        );

    $types =
        $typeStmt->fetchAll();

    $row = 2;

    foreach($types as $type){

        $insight =
            $type['total'] > 20

            ? 'High transportation demand detected.'

            : 'Moderate transportation activity recorded.';

        $analyticsSheet->setCellValue(
            'A'.$row,
            $type['passenger_type']
        );

        $analyticsSheet->setCellValue(
            'B'.$row,
            $type['total']
        );

        $analyticsSheet->setCellValue(
            'C'.$row,
            $insight
        );

        $row++;
    }

    /*
    |--------------------------------------------------------------------------
    | ROUTE ANALYTICS
    |--------------------------------------------------------------------------
    */

    $routeSheet =
        $spreadsheet
        ->createSheet();

    $routeSheet->setTitle(
        'Route Analytics'
    );

    $routeHeaders = [

        'Destination',
        'Total Trips',
        'Revenue Generated'

    ];

    $col = 'A';

    foreach($routeHeaders as $header){

        $routeSheet->setCellValue(
            $col . '1',
            $header
        );

        $routeSheet
            ->getStyle(
                $col . '1'
            )
            ->getFont()
            ->setBold(true);

        $routeSheet
            ->getColumnDimension($col)
            ->setWidth(35);

        $col++;
    }

    $routeQuery = "

    SELECT

        destination,

        COUNT(*) as total_trips,

        COALESCE(
            SUM(fare),
            0
        ) as revenue

    FROM passenger_trips

    GROUP BY destination

    ORDER BY total_trips DESC

    ";

    $routeStmt =
        $pdo->query(
            $routeQuery
        );

    $routes =
        $routeStmt->fetchAll();

    $row = 2;

    foreach($routes as $route){

        $routeSheet->setCellValue(
            'A'.$row,
            $route['destination']
        );

        $routeSheet->setCellValue(
            'B'.$row,
            $route['total_trips']
        );

        $routeSheet->setCellValue(
            'C'.$row,
            $route['revenue']
        );

        $row++;
    }

    /*
    |--------------------------------------------------------------------------
    | REMITTANCE LOGS
    |--------------------------------------------------------------------------
    */

    $remitSheet =
        $spreadsheet
        ->createSheet();

    $remitSheet->setTitle(
        'Remittance Logs'
    );

    $remitHeaders = [

        'Remittance ID',
        'Bus Number',
        'Submitted By',
        'Total Collection',
        'Expected Cash',
        'Actual Cash',
        'Discrepancy',
        'Status',
        'Created At'

    ];

    $col = 'A';

    foreach($remitHeaders as $header){

        $remitSheet->setCellValue(
            $col . '1',
            $header
        );

        $remitSheet
            ->getStyle(
                $col . '1'
            )
            ->getFont()
            ->setBold(true);

        $remitSheet
            ->getColumnDimension($col)
            ->setWidth(24);

        $col++;
    }

    $remitQuery = "

    SELECT

        id,
        bus_no,
        submitted_by,
        total_collection,
        expected_cash,
        actual_cash,
        discrepancy_amount,
        status,
        created_at

    FROM remittance_logs

    ORDER BY created_at DESC

    ";

    $remitStmt =
        $pdo->query(
            $remitQuery
        );

    $remittances =
        $remitStmt->fetchAll();

    $row = 2;

    foreach($remittances as $remit){

        $remitSheet->setCellValue(
            'A'.$row,
            $remit['id']
        );

        $remitSheet->setCellValue(
            'B'.$row,
            $remit['bus_no']
        );

        $remitSheet->setCellValue(
            'C'.$row,
            $remit['submitted_by']
        );

        $remitSheet->setCellValue(
            'D'.$row,
            $remit['total_collection']
        );

        $remitSheet->setCellValue(
            'E'.$row,
            $remit['expected_cash']
        );

        $remitSheet->setCellValue(
            'F'.$row,
            $remit['actual_cash']
        );

        $remitSheet->setCellValue(
            'G'.$row,
            $remit['discrepancy_amount']
        );

        $remitSheet->setCellValue(
            'H'.$row,
            $remit['status']
        );

        $remitSheet->setCellValue(
            'I'.$row,
            $remit['created_at']
        );

        $row++;
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE SHEET
    |--------------------------------------------------------------------------
    */

    $spreadsheet
        ->setActiveSheetIndex(0);

    /*
    |--------------------------------------------------------------------------
    | OUTPUT
    |--------------------------------------------------------------------------
    */

    $filename =

        'transportation_report_' .

        $report['id']

        .

        '.xlsx';

    header(
        'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    header(
        'Content-Disposition: attachment; filename="' . $filename . '"'
    );

    header(
        'Cache-Control: max-age=0'
    );

    /*
    |--------------------------------------------------------------------------
    | CLEAN BUFFER
    |--------------------------------------------------------------------------
    */

    if(ob_get_length()){

        ob_end_clean();
    }

    $writer =
        new Xlsx(
            $spreadsheet
        );

    $writer->save(
        'php://output'
    );

    exit;

}catch(Throwable $e){

    die(

        'Excel Export Error: '

        .

        $e->getMessage()
    );
}
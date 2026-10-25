<?php

namespace Finance\Services;

/*
|--------------------------------------------------------------------------
| ERROR REPORTING
|--------------------------------------------------------------------------
*/

ini_set('display_errors', 1);

ini_set('display_startup_errors', 1);

error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| LOAD MODELS
|--------------------------------------------------------------------------
*/

require_once __DIR__ .
    '/../models/TripOperationsReportModel.php';

require_once __DIR__ .
    '/../services/AITripOperationsAnalysisService.php';

use Finance\Models\TripOperationsReportModel;
use Finance\Services\AITripOperationsAnalysisService;

class TripOperationsReportService
{
    /*
    |--------------------------------------------------------------------------
    | MODEL
    |--------------------------------------------------------------------------
    */

    private TripOperationsReportModel $model;

    /*
    |--------------------------------------------------------------------------
    | AI SERVICE
    |--------------------------------------------------------------------------
    */

    private AITripOperationsAnalysisService $aiService;

    /*
    |--------------------------------------------------------------------------
    | UPLOAD DIRECTORY
    |--------------------------------------------------------------------------
    */

    private const UPLOAD_DIRECTORY =
        '/uploads/trip_reports/';

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        $this->model =
            new TripOperationsReportModel();

        $this->aiService =
            new AITripOperationsAnalysisService();
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE MULTIPLE TRIPS
    |--------------------------------------------------------------------------
    */

    public function saveTrips(
        array $trips,
        array $uploadedFiles,
        string $aiNotes
    ): bool
    {
        try{

            /*
            |--------------------------------------------------------------------------
            | EMPTY TRIPS
            |--------------------------------------------------------------------------
            */

            if(empty($trips)){

                throw new \Exception(
                    'No trips submitted.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | GROUP CODE
            |--------------------------------------------------------------------------
            */

            $tripGroupCode =
                $this->generateTripGroupCode();

            /*
            |--------------------------------------------------------------------------
            | DEBUG
            |--------------------------------------------------------------------------
            */

            echo '<pre>';

            echo "GROUP CODE:\n";

            print_r($tripGroupCode);

            /*
            |--------------------------------------------------------------------------
            | UPLOAD FILES
            |--------------------------------------------------------------------------
            */

            $savedFiles =
                $this->uploadFiles(
                    $uploadedFiles
                );

            echo "\nUPLOADED FILES:\n";

            print_r($savedFiles);

            /*
            |--------------------------------------------------------------------------
            | SAVE ALL TRIPS
            |--------------------------------------------------------------------------
            */

            foreach($trips as $index => $trip){

                echo "\n=====================\n";

                echo "PROCESSING TRIP #" .
                    ($index + 1);

                echo "\n=====================\n";

                /*
                |--------------------------------------------------------------------------
                | NET INCOME
                |--------------------------------------------------------------------------
                */

                $netIncome =

                    (float)(
                        $trip['total_revenue']
                        ?? 0
                    )

                    -

                    (

                        (float)(
                            $trip['fuel_cost']
                            ?? 0
                        )

                        +

                        (float)(
                            $trip['other_expenses']
                            ?? 0
                        )
                    );

                /*
                |--------------------------------------------------------------------------
                | PAYLOAD
                |--------------------------------------------------------------------------
                */

                $payload = [

                    'trip_group_code' =>

                        $tripGroupCode,

                    'route_id' =>

                        $trip['route_id']
                        ?? null,

                    'bus_id' =>

                        $trip['bus_id']
                        ?? null,

                    'driver_id' =>

                        $trip['driver_id']
                        ?? null,

                    'inspector_id' =>

                        $trip['inspector_id']
                        ?? null,

                    'trip_date' =>

                        $trip['trip_date']
                        ?? null,

                    'passenger_count' =>

                        (int)(
                            $trip['passenger_count']
                            ?? 0
                        ),

                    'total_revenue' =>

                        (float)(
                            $trip['total_revenue']
                            ?? 0
                        ),

                    'fuel_cost' =>

                        (float)(
                            $trip['fuel_cost']
                            ?? 0
                        ),

                    'other_expenses' =>

                        (float)(
                            $trip['other_expenses']
                            ?? 0
                        ),

                    'net_income' =>

                        $netIncome,

                    'odometer' =>

                        (float)(
                            $trip['odometer']
                            ?? 0
                        ),

                    'notes' =>

                        trim(
                            $trip['notes']
                            ?? ''
                        ),

                    'ai_analysis_notes' =>

                        trim($aiNotes),

                    'evidence_files' =>

                        json_encode(

                            $savedFiles,

                            JSON_UNESCAPED_SLASHES
                        ),

                    'created_at' =>

                        date(
                            'Y-m-d H:i:s'
                        )
                ];

                /*
                |--------------------------------------------------------------------------
                | DEBUG PAYLOAD
                |--------------------------------------------------------------------------
                */

                echo "\nPAYLOAD:\n";

                print_r($payload);

                /*
                |--------------------------------------------------------------------------
                | SAVE REPORT
                |--------------------------------------------------------------------------
                */

                $saved =
                    $this->model
                    ->save($payload);

                /*
                |--------------------------------------------------------------------------
                | FAILED SAVE
                |--------------------------------------------------------------------------
                */

                if(!$saved){

                    throw new \Exception(

                        'Trip report save failed.'
                    );
                }

                echo "\nTRIP REPORT SAVED\n";

                /*
                |--------------------------------------------------------------------------
                | LAST INSERT ID
                |--------------------------------------------------------------------------
                */

                $lastInsertId =

                    $this->model
                    ->getLastInsertId();

                echo "\nLAST INSERT ID:\n";

                print_r($lastInsertId);

                /*
                |--------------------------------------------------------------------------
                | AI ANALYSIS
                |--------------------------------------------------------------------------
                */

                $aiPayload =

                    array_merge(

                        $payload,

                        [
                            'id' =>
                                $lastInsertId
                        ]
                    );

                echo "\nAI PAYLOAD:\n";

                print_r($aiPayload);

                /*
                |--------------------------------------------------------------------------
                | RUN AI
                |--------------------------------------------------------------------------
                */

                $aiSaved =

                    $this->aiService
                    ->analyze($aiPayload);

                /*
                |--------------------------------------------------------------------------
                | AI FAILED
                |--------------------------------------------------------------------------
                */

                if(!$aiSaved){

                    throw new \Exception(

                        'AI analysis save failed.'
                    );
                }

                echo "\nAI REPORT SAVED\n";
            }

            echo "\nALL OPERATIONS COMPLETED\n";

            echo '</pre>';

            return true;

        }catch(\Throwable $e){

            echo '<pre style="
                background:#fff3f3;
                color:#cc0000;
                padding:20px;
                border-radius:14px;
                border:2px solid #ffcccc;
                font-size:14px;
                line-height:1.7;
            ">';

            echo "SERVICE ERROR:\n\n";

            echo $e->getMessage();

            echo "\n\nFILE:\n";

            echo $e->getFile();

            echo "\n\nLINE:\n";

            echo $e->getLine();

            echo "\n\nTRACE:\n";

            print_r(
                $e->getTraceAsString()
            );

            echo '</pre>';

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD FILES
    |--------------------------------------------------------------------------
    */

    private function uploadFiles(
        array $uploadedFiles
    ): array
    {
        $savedFiles = [];

        if(empty($uploadedFiles)){

            return [];
        }

        $uploadPath =

            rtrim(

                $_SERVER['DOCUMENT_ROOT'],

                '/'
            )

            .

            self::UPLOAD_DIRECTORY;

        /*
        |--------------------------------------------------------------------------
        | CREATE DIRECTORY
        |--------------------------------------------------------------------------
        */

        if(!is_dir($uploadPath)){

            mkdir(
                $uploadPath,
                0775,
                true
            );
        }

        foreach($uploadedFiles as $file){

            if(

                empty($file['tmp_name'])

                ||

                !file_exists(
                    $file['tmp_name']
                )
            ){

                continue;
            }

            $originalName =

                preg_replace(

                    '/[^a-zA-Z0-9_\.-]/',

                    '_',

                    basename(
                        $file['name']
                    )
                );

            $fileName =

                uniqid(
                    'trip_',
                    true
                )

                .

                '_'

                .

                $originalName;

            $destination =

                $uploadPath
                .
                $fileName;

            /*
            |--------------------------------------------------------------------------
            | MOVE FILE
            |--------------------------------------------------------------------------
            */

            if(
                move_uploaded_file(

                    $file['tmp_name'],

                    $destination
                )
            ){

                $savedFiles[] = [

                    'file_name' =>

                        $fileName,

                    'original_name' =>

                        $originalName,

                    'path' =>

                        self::UPLOAD_DIRECTORY
                        .
                        $fileName,

                    'uploaded_at' =>

                        date(
                            'Y-m-d H:i:s'
                        )
                ];

            }else{

                echo "<pre>";

                echo "FAILED TO MOVE FILE:\n";

                print_r($file);

                echo "</pre>";
            }
        }

        return $savedFiles;
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE GROUP CODE
    |--------------------------------------------------------------------------
    */

    private function generateTripGroupCode(): string
    {
        return

            'TRIP-'

            .

            strtoupper(

                bin2hex(
                    random_bytes(4)
                )
            )

            .

            '-'

            .

            date('Ymd');
    }
}
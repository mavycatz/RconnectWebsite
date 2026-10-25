<?php

declare(strict_types=1);

namespace admin\models;

require_once $_SERVER['DOCUMENT_ROOT']
.'/app/Core/Database.php';

use App\Core\Database;
use PDO;

class CategoriesReport
{
    /*
    |--------------------------------------------------------------------------
    | GENERATED REPORT FOLDERS
    |--------------------------------------------------------------------------
    */

    public function generatedReports(): array
    {
        $path =

            $_SERVER['DOCUMENT_ROOT']

            .

            '/generated_reports';

        if(!is_dir($path)){
            return [];
        }

        $folders = array_filter(

            scandir($path),

            function($folder) use ($path){

                return

                    $folder !== '.'

                    &&

                    $folder !== '..'

                    &&

                    is_dir($path.'/'.$folder);
            }
        );

        return array_values($folders);
    }

    /*
    |--------------------------------------------------------------------------
    | AI TRIP OPERATION REPORT CATEGORIES
    |--------------------------------------------------------------------------
    */

    public function aiTripOperationReports(): array
    {
        $db = Database::get();

        $stmt = $db->query("

            SELECT DISTINCT report_category

            FROM ai_trip_operations_reports

            WHERE archived = 0

            AND report_category IS NOT NULL

            ORDER BY report_category ASC

        ");

        return $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TRIP OPERATION REPORT CATEGORIES
    |--------------------------------------------------------------------------
    */

    public function tripOperationReports(): array
    {
        $db = Database::get();

        $stmt = $db->query("

            SELECT DISTINCT report_category

            FROM trip_operations_reports

            WHERE archived = 0

            AND report_category IS NOT NULL

            ORDER BY report_category ASC

        ");

        return $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ALL CATEGORIES
    |--------------------------------------------------------------------------
    */

    public function all(): array
    {
        return [

            'generated_reports' =>

                $this->generatedReports(),

            'ai_trip_operations_reports' =>

                $this->aiTripOperationReports(),

            'trip_operations_reports' =>

                $this->tripOperationReports()
        ];
    }
}
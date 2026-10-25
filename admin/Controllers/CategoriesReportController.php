<?php

declare(strict_types=1);

namespace admin\Controllers;

/*
|--------------------------------------------------------------------------
| REQUIRE MODEL
|--------------------------------------------------------------------------
*/

require_once $_SERVER['DOCUMENT_ROOT']
.'/admin/models/CategoriesReportModel.php';

use admin\models\CategoriesReport;

class CategoriesReportController
{
    private CategoriesReport $report;

    public function __construct()
    {
        $this->report =
            new CategoriesReport();
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATED REPORTS
    |--------------------------------------------------------------------------
    */

    public function generatedReports(): array
    {
        return

            $this->report
                ->generatedReports();
    }

    /*
    |--------------------------------------------------------------------------
    | AI TRIP OPERATION REPORTS
    |--------------------------------------------------------------------------
    */

    public function aiTripOperationReports(): array
    {
        return

            $this->report
                ->aiTripOperationReports();
    }

    /*
    |--------------------------------------------------------------------------
    | TRIP OPERATION REPORTS
    |--------------------------------------------------------------------------
    */

    public function tripOperationReports(): array
    {
        return

            $this->report
                ->tripOperationReports();
    }

    /*
    |--------------------------------------------------------------------------
    | ALL
    |--------------------------------------------------------------------------
    */

    public function all(): array
    {
        return

            $this->report
                ->all();
    }
}
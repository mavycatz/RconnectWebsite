<?php

namespace Finance\Services;

require_once __DIR__ .
    '/../models/AITripOperationsReportModel.php';

use Finance\Models\AITripOperationsReportModel;

class AITripOperationsReportService
{
    private AITripOperationsReportModel $model;

    public function __construct()
    {
        $this->model =
            new AITripOperationsReportModel();
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL REPORTS
    |--------------------------------------------------------------------------
    */

    public function getReports(): array
    {
        return
            $this->model
            ->getAll();
    }

    /*
    |--------------------------------------------------------------------------
    | GET SINGLE REPORT
    |--------------------------------------------------------------------------
    */

    public function getReport(
        int $id
    ): ?array
    {
        return
            $this->model
            ->getById($id);
    }
}
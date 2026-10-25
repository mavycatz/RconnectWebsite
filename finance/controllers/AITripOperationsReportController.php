<?php

namespace Finance\Controllers;

require_once __DIR__ .
    '/../services/AITripOperationsReportService.php';

use Finance\Services\AITripOperationsReportService;

class AITripOperationsReportController
{
    private AITripOperationsReportService $service;

    public function __construct()
    {
        $this->service =
            new AITripOperationsReportService();
    }

    /*
    |--------------------------------------------------------------------------
    | GET REPORTS
    |--------------------------------------------------------------------------
    */

    public function getReports(): array
    {
        return
            $this->service
            ->getReports();
    }

    /*
    |--------------------------------------------------------------------------
    | GET REPORT
    |--------------------------------------------------------------------------
    */

    public function getReport(
        int $id
    ): ?array
    {
        return
            $this->service
            ->getReport($id);
    }
}
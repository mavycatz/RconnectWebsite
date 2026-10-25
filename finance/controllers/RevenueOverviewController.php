<?php

namespace Finance\Controllers;

require_once __DIR__ . '/../services/RevenueOverviewService.php';

use Finance\Services\RevenueOverviewService;

class RevenueOverviewController
{
    /**
     * =========================
     * SERVICE INSTANCE
     * =========================
     */
    private RevenueOverviewService $service;

    /**
     * =========================
     * CONSTRUCTOR
     * =========================
     */
    public function __construct()
    {
        $this->service =
            new RevenueOverviewService();
    }

    /**
     * =========================
     * GET OVERVIEW DATA
     * =========================
     */
    public function getOverviewData(): array
    {
        $filters = [];

        if (!empty($_GET['fromDate'])) {
            $filters['fromDate'] = $_GET['fromDate'];
        }
        if (!empty($_GET['toDate'])) {
            $filters['toDate'] = $_GET['toDate'];
        }
        if (!empty($_GET['type'])) {
            $filters['type'] = $_GET['type'];
        }
        if (!empty($_GET['bus'])) {
            $filters['bus'] = $_GET['bus'];
        }
        if (!empty($_GET['filter_route'])) {
            $filters['filter_route'] = $_GET['filter_route'];
        }
        if (!empty($_GET['day'])) {
            $filters['day'] = $_GET['day'];
        }
        if (!empty($_GET['month'])) {
            $filters['month'] = $_GET['month'];
        }
        if (!empty($_GET['year'])) {
            $filters['year'] = $_GET['year'];
        }

        return $this->service
                    ->getOverviewData($filters);
    }

    /**
     * =========================
     * GET BUS PERFORMANCE DATA
     * =========================
     */
    public function getBusPerformanceData(): array
    {
        return $this->service
                    ->getBusPerformanceData();
    }

    /**
     * =========================
     * GET ROUTE PROFIT DATA
     * =========================
     */
    public function getRouteProfitData(): array
    {
        return $this->service
                    ->getRouteProfitData();
    }

    /**
     * =========================
     * GET PEAK HOURS DATA
     * =========================
     */
    public function getPeakHourData(): array
    {
        return $this->service
                    ->getPeakHourData();
    }

    /**
     * =========================
     * GET PASSENGER GROWTH
     * =========================
     */
    public function getPassengerGrowthData(): array
    {
        return $this->service
                    ->getPassengerGrowthData();
    }
}

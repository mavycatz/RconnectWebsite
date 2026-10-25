<?php

namespace Admin\Controllers;

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/DashboardModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/FinancialCosts.php';

use Admin\Models\DashboardModel;
use App\Core\FinancialCosts;
use PDO;

class DashboardController
{
    private DashboardModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new DashboardModel($db);
        $this->model->ensureTripDateIndex();
    }


    /* -----------------------------------------
       HANDLE RANGE → FILTERS
    ----------------------------------------- */

    private function buildFiltersFromRange(string $range): array
    {
        switch($range){

            case 'today':
                return [
                    'day' => date('d'),
                    'month' => date('m'),
                    'year' => date('Y')
                ];

            case 'week':
                return [
                    'fromDate' => date('Y-m-d', strtotime('-6 days')),
                    'toDate'   => date('Y-m-d')
                ];

            case 'year':
                return [
                    'year' => date('Y')
                ];

            case 'custom':
                return [];

            case 'month':
            default:
                return [
                    'month' => date('m'),
                    'year' => date('Y')
                ];
        }
    }


    /* -----------------------------------------
       PREVIOUS PERIOD FILTERS (FOR TRENDS)
    ----------------------------------------- */

    private function buildPreviousFilters(string $range): array
    {
        switch($range){

            case 'today':
                return [
                    'day' => date('d', strtotime('-1 day')),
                    'month' => date('m', strtotime('-1 day')),
                    'year' => date('Y', strtotime('-1 day'))
                ];

            case 'week':
                return [
                    'fromDate' => date('Y-m-d', strtotime('-13 days')),
                    'toDate'   => date('Y-m-d', strtotime('-7 days'))
                ];

            case 'year':
                return [
                    'year' => date('Y', strtotime('-1 year'))
                ];

            case 'month':
            default:
                return [
                    'month' => date('m', strtotime('-1 month')),
                    'year' => date('Y', strtotime('-1 month'))
                ];
        }
    }


    /* -----------------------------------------
       REQUEST FILTERS (CURRENT + PREVIOUS)
       Canonical `period` (shared Finance DateFilterService) wins over the
       legacy `range` selector so every dashboard component resolves the same
       selected window and compares calendar-equivalent previous windows.
       Legacy-only callers keep the historical behaviour.
    ----------------------------------------- */

    private function resolveRequestFilters(): array
    {
        $range = (string)($_GET['range'] ?? '');
        $period = trim((string)($_GET['period'] ?? ''));

        if ($period !== '') {
            $servicePath = $_SERVER['DOCUMENT_ROOT'] . '/finance/services/DateFilterService.php';
            if (is_file($servicePath)) {
                require_once $servicePath;
                $resolved = \Finance\Services\DateFilterService::normalizeRequest($_GET);
                [$prevStart, $prevEnd] = \Finance\Services\DateFilterService::previousRange(
                    $resolved['period'],
                    $resolved['start'],
                    $resolved['end']
                );

                $filters = [
                    'fromDate' => $resolved['start'],
                    'toDate'   => $resolved['end'],
                ];
                $prevFilters = [
                    'fromDate' => $prevStart,
                    'toDate'   => $prevEnd,
                ];

                foreach (['bus', 'route', 'driver', 'category', 'fromTime', 'toTime'] as $key) {
                    if (!empty($_GET[$key])) {
                        $filters[$key] = $_GET[$key];
                        $prevFilters[$key] = $_GET[$key];
                    }
                }

                return [$resolved['period'], $filters, $prevFilters];
            }
        }

        if ($range === '') {
            $range = 'month';
        }

        $filters = $this->buildFiltersFromRange($range);
        $prevFilters = $this->buildPreviousFilters($range);

        // Merge dashboard filter params if present
        foreach (['bus','route','driver','category','day','month','year','fromDate','toDate','fromTime','toTime'] as $key) {
            if (!empty($_GET[$key])) {
                $filters[$key] = $_GET[$key];
            }
        }

        /* A custom date range owns its own previous window: shift the
           same number of days back instead of using a calendar default. */
        if ($range === 'custom'
            && !empty($filters['fromDate'])
            && !empty($filters['toDate'])) {
            $days = (int)round(
                (strtotime($filters['toDate']) - strtotime($filters['fromDate'])) / 86400
            ) + 1;
            $days = max(1, $days);
            $prevFilters = [
                'fromDate' => date('Y-m-d', strtotime($filters['fromDate'] . " -{$days} days")),
                'toDate'   => date('Y-m-d', strtotime($filters['toDate'] . " -{$days} days")),
            ];
        }

        /* Non-period filters apply to both the current and previous
           period so the KPI trend compares like with like. */
        foreach (['bus','route','driver','category','fromTime','toTime'] as $key) {
            if (!empty($filters[$key])) {
                $prevFilters[$key] = $filters[$key];
            }
        }

        return [$range, $filters, $prevFilters];
    }


    /* -----------------------------------------
       CORE STATS CALCULATION
    ----------------------------------------- */

private function computeStats(array $filters): array
{
        $grossIncome = $this->model->totalEarnings($filters);

        /* Fuel cost is now sourced from fuel_logs (created_at),
           NOT from trip_operations_reports. */
        $fuelCost = $this->model->totalFuelFromLogs($filters);

        /* Other expenses from trip_operations_reports are no longer
           included in the net income calculation. */
        $otherExpenses = 0.0;

            $expenses =
                $fuelCost + $otherExpenses;

            /* ----- Driver fixed costs (itemized monthly per driver, scaled to period) ----- */
            $activeDrivers = $this->model->activeDrivers($filters);

            $monthFactor = 1.0;
            if (!empty($filters['day'])) {
                $monthFactor = 1 / 30.4375;
            } elseif (!empty($filters['year']) && empty($filters['month'])) {
                $monthFactor = 12.0;
            } elseif (!empty($filters['fromDate']) && !empty($filters['toDate'])) {
                $days = (strtotime($filters['toDate']) - strtotime($filters['fromDate'])) / 86400 + 1;
                $monthFactor = max(0.0, (float)$days / 30.4375);
            }

            /* Fixed monthly costs per active driver — sourced from Finance →
               Financial Cost Settings (App\Core\FinancialCosts) with legacy
               fallback (amortization 1,098 + insurance 192.89 + gps 6.25 +
               optr 500 = 1,797.14 per driver per month). Per-driver context
               uses period averages so percent/per-trip methods stay exact
               when aggregated across drivers. */
            $tripCount = (int) $this->model->totalTrips($filters);
            $avgGross = $activeDrivers > 0 ? $grossIncome / $activeDrivers : 0.0;
            $avgTrips = $activeDrivers > 0 ? $tripCount / $activeDrivers : 0.0;
            $fixedPerDriver = FinancialCosts::fixedCosts($monthFactor, [
                'gross' => $avgGross,
                'trips' => $avgTrips,
            ]);

            $driverFixedCosts = round($fixedPerDriver['total'] * $activeDrivers, 2);
            $fixedAmortization = round($fixedPerDriver['amortization'] * $activeDrivers, 2);
            $fixedInsurance = round($fixedPerDriver['insurance'] * $activeDrivers, 2);
            $fixedGps = round($fixedPerDriver['gps'] * $activeDrivers, 2);
            $fixedOptr = round($fixedPerDriver['optr'] * $activeDrivers, 2);

            /* Net income = gross - fuel(from logs) - driver fixed costs */
            $netIncome = round($grossIncome - $expenses - $driverFixedCosts, 2);

            $fuelSummary = $this->model->fuelAnalyticsSummary($filters);
            $totalFuelCost = (float)($fuelSummary['total_cost'] ?? 0);
            $totalEstimatedFuelCost = (float)($fuelSummary['total_estimated_fuel_cost'] ?? $totalFuelCost);
            $totalActualFuelCost = (float)($fuelSummary['total_actual_fuel_cost'] ?? 0);
            $fuelTripsCount = (int)($fuelSummary['trip_count'] ?? 0);
            $totalFuelConsumed = (float)($fuelSummary['total_liters'] ?? 0);
            $avgFuelConsumedPerTrip = ($fuelTripsCount > 0 && $totalFuelConsumed > 0)
                ? round($totalFuelConsumed / $fuelTripsCount, 2)
                : null;
            $avgFuelCostPerTrip = ($fuelTripsCount > 0 && $totalFuelCost > 0)
                ? round($totalFuelCost / $fuelTripsCount, 2)
                : null;

            return [

            "gross_income" => $grossIncome,

            "fuel_cost" => $fuelCost,

            "other_expenses" => $otherExpenses,

            "expenses" => $expenses,

            "net_profit" =>
                $grossIncome - $expenses,

        "round_trips" =>
            $this->model->totalTrips($filters),

        "earnings" => $grossIncome,

        "active_drivers" =>
            $activeDrivers,

        "passengers" =>
            $this->model->totalPassengers($filters),

        "passengers_only" =>
            $this->model->totalPassengerRecords($filters),

            "net_income" => $netIncome,

            "driver_fixed_costs" => $driverFixedCosts,

            "fixed_amortization" => $fixedAmortization,
            "fixed_insurance"    => $fixedInsurance,
            "fixed_gps"          => $fixedGps,
            "fixed_optr"         => $fixedOptr,

            "month_factor" => $monthFactor,

        "avg_trip" =>
            $this->model->avgTrip($filters),

        "avg_kpl" =>
            (float)$this->model->fuelAnalyticsSummary($filters)['avg_kpl'],

        "fuel_efficiency" =>
            $this->model->fleetFuelEfficiency($filters),

        "avg_fuel_consumed_per_trip" => $avgFuelConsumedPerTrip,
        "avg_liters_per_trip"        => $avgFuelConsumedPerTrip,
        "total_fuel_consumed"        => $totalFuelConsumed,
        "total_liters"               => $totalFuelConsumed,
        "total_estimated_fuel_cost"  => $totalEstimatedFuelCost,
        "total_actual_fuel_cost"     => $totalActualFuelCost,
        "avg_fuel_cost_per_trip"     => $avgFuelCostPerTrip,
        "fuel_trips_count"           => $fuelTripsCount,
        "total_fuel_cost"            => $totalFuelCost,

        "passenger_trips" =>
            $this->model->totalPassengerTrips($filters),

        "active_buses" =>
            $this->model->activeBuses()
    ];
}


    /* -----------------------------------------
       DASHBOARD STATS (UPDATED)
    ----------------------------------------- */

    public function getStats(): array
    {
        $startTime = microtime(true);

        try {

            $this->model->ensureTripDateIndex();

            [$range, $filters, $prevFilters] = $this->resolveRequestFilters();

            $currentStats = $this->computeStats($filters);
            $previousStats = $this->computeStats($prevFilters);

            return [
                "success" => true,
                "data" => $currentStats,
                "previous" => $previousStats,
                "meta" => [
                    "range" => $range,
                    "timestamp" => date("Y-m-d H:i:s"),
                    "execution_time_ms" => round((microtime(true) - $startTime) * 1000, 2)
                ]
            ];

        } catch (\Throwable $e) {

            return [
                "success" => false,
                "message" => $e->getMessage() // 🔥 show real error for debugging
            ];

        }
    }


    /* -----------------------------------------
       STAT DETAIL (for Finance Dashboard modals)
       Returns period-aware current/previous detail for a single
       statistic card, reusing computeStats() so the figures stay
       identical to the dashboard cards. Passenger type breakdown is
       added from the model (reconciles with totalPassengerRecords).
       ----------------------------------------- */

    public function getStatDetail(string $metric): array
    {
        $allowed = ['gross', 'net', 'trips', 'pax', 'buses', 'fuel'];
        if (!in_array($metric, $allowed, true)) {
            return ["success" => false, "message" => "Invalid metric"];
        }

        [$range, $filters, $prevFilters] = $this->resolveRequestFilters();

        $current = $this->buildDetail($metric, $filters);
        $previous = $this->buildDetail($metric, $prevFilters);

        $out = [
            "success"     => true,
            "metric"      => $metric,
            "range"       => $range,
            "current"     => $current,
            "previous"    => $previous
        ];

        if ($metric === 'pax') {
            $out['breakdown_current']  = $this->model->passengerTypeBreakdown($filters);
            $out['breakdown_previous'] = $this->model->passengerTypeBreakdown($prevFilters);
        }

        return $out;
    }

    private function buildDetail(string $metric, array $filters): array
    {
        $s = $this->computeStats($filters);

        $trips       = (int)   ($s['round_trips'] ?? 0);
        $paxRecords  = (int)   ($s['passengers_only'] ?? 0);
        $gross       = (float) ($s['gross_income'] ?? 0);
        $activeBuses = (int)   ($s['active_buses'] ?? 0);

        $safe = fn($a, $b) => $b > 0 ? round($a / $b, 2) : 0;

        switch ($metric) {

            case 'gross':
                return [
                    'value'                     => $gross,
                    'total_trips'               => $trips,
                    'total_passengers'          => $paxRecords,
                    'avg_fare_per_trip'         => $safe($gross, $trips),
                    'avg_revenue_per_passenger' => $safe($gross, $paxRecords),
                    'avg_passengers_per_trip'   => $safe($paxRecords, $trips),
                ];

            case 'net':
                return [
                    'value'        => $s['net_income'] ?? 0,
                    'gross'        => $gross,
                    'fuel'         => $s['fuel_cost'] ?? 0,
                    'other'        => $s['other_expenses'] ?? 0,
                    'amortization' => $s['fixed_amortization'] ?? 0,
                    'insurance'    => $s['fixed_insurance'] ?? 0,
                    'gps'          => $s['fixed_gps'] ?? 0,
                    'optr'         => $s['fixed_optr'] ?? 0,
                    'fixed_total'  => $s['driver_fixed_costs'] ?? 0,
                    'expenses'     => $s['expenses'] ?? 0,
                ];

            case 'trips':
                return [
                    'value'                     => $trips,
                    'total_passengers'          => $paxRecords,
                    'active_buses'              => $activeBuses,
                    'avg_trips_per_active_bus'  => $safe($trips, $activeBuses),
                    'avg_passengers_per_trip'   => $safe($paxRecords, $trips),
                    'revenue_per_trip'          => $safe($gross, $trips),
                ];

            case 'pax':
                return [
                    'value'                     => $paxRecords,
                    'total_trips'               => $trips,
                    'avg_passengers_per_trip'   => $safe($paxRecords, $trips),
                    'avg_revenue_per_passenger' => $safe($gross, $paxRecords),
                ];

            case 'buses':
                $totalBuses = $this->model->totalBusesCount();
                return [
                    'value'                       => $activeBuses,
                    'total_buses'                 => $totalBuses,
                    'utilization'                 => $totalBuses > 0 ? round($activeBuses / $totalBuses * 100, 1) : 0,
                    'trips_per_active_bus'        => $safe($trips, $activeBuses),
                    'passengers_per_active_bus'   => $safe($paxRecords, $activeBuses),
                ];

            case 'fuel':
                $fSum = $this->model->fuelAnalyticsSummary($filters);
                $fCost = (float)($fSum['total_estimated_fuel_cost'] ?? 0);
                $fActualCost = (float)($fSum['total_actual_fuel_cost'] ?? 0);
                $fTrips = (int)($fSum['trip_count'] ?? 0);
                $fLiters = (float)($fSum['total_liters'] ?? 0);
                $fKpl = (float)($fSum['avg_kpl'] ?? 0);
                $avgLitersTrip = ($fTrips > 0 && $fLiters > 0) ? round($fLiters / $fTrips, 2) : 0;
                $avgCostTrip = ($fTrips > 0 && $fCost > 0) ? round($fCost / $fTrips, 2) : 0;
                return [
                    'value'                      => $fKpl, // Primary KPI is Average Fuel Consumption in km/L.
                    'avg_kpl'                    => $fKpl,
                    'avg_l_per_100km'            => $fKpl > 0 ? round(100 / $fKpl, 2) : 0,
                    'avg_fuel_consumed_per_trip' => $avgLitersTrip,
                    'avg_liters_per_trip'        => $avgLitersTrip,
                    'total_fuel_consumed'        => $fLiters,
                    'total_liters'               => $fLiters,
                    'avg_fuel_cost_per_trip'     => $avgCostTrip,
                    'total_fuel_cost'            => $fCost,
                    'total_estimated_fuel_cost'  => $fCost,
                    'total_actual_fuel_cost'     => $fActualCost,
                    'actual_refuel_events'       => (int)($fSum['refuel_events'] ?? 0),
                    'fuel_trips_count'           => $fTrips,
                ];

            default:
                return [];
        }
    }


    /* -----------------------------------------
       TOP DRIVERS
       ----------------------------------------- */

public function getTopDrivers(array $filters = []): array
{
    try {

        return [
            "success" => true,
            "data" => $this->model->topDrivers($filters)
        ];

    } catch (\Throwable $e) {

        return [
            "success" => false,
            "message" => $e->getMessage(),
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ];

    }
}

    /* -----------------------------------------
       FUEL STATS
       Returns aggregate fuel statistics and the
       per-bus fuel performance breakdown.
    ----------------------------------------- */

public function getFuelStats(array $filters = []): array
{
    try {

        return [
            "success" => true,
            "data" => [
                "summary" => $this->model->fuelAnalyticsSummary($filters),
                "per_bus" => $this->model->fuelAnalyticsPerBus($filters)
            ]
        ];

    } catch (\Throwable $e) {

        return [
            "success" => false,
            "message" => $e->getMessage(),
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ];

    }
}

}
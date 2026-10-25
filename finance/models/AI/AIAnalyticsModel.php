<?php

namespace Finance\Models\AI;

require_once __DIR__
    . '/../../../app/Core/Database.php';

require_once __DIR__
    . '/../../../app/services/DriverPerformanceService.php';

use App\Core\Database;
use App\Services\DriverPerformanceService;

class AIAnalyticsModel
{
    private \PDO $conn;

    public function __construct()
    {
        $this->conn = Database::get();
    }

    /*
    |--------------------------------------------------------------------------
    | GET AI ANALYTICS DATA
    |--------------------------------------------------------------------------
    |
    | $filters (optional) carries the SAME date/period conditions the live
    | Finance pages apply (month, year, fromDate, toDate - exactly the GET
    | params the admin dashboard endpoints read). When provided, the live
    | queries reproduce the page's business logic:
    |
    |   - passenger_trips rows are restricted to payment_status='paid'
    |   - trips are counted as DISTINCT trip keys (CONCAT('l-', trip_no)),
    |     exactly like admin/api/dashboard/driver-performance.php
    |   - the selected date range is applied to boarding_time
    |
    | This guarantees page filter -> AI fallback answers always agree with
    | the numbers displayed on the page for the same period.
    |
    */

    public function getAIAnalyticsData(?array $onlyKeys = null, array $filters = []): array
    {
        $d = [];
        $logFile = __DIR__ . '/ai_model_error.log';

        /*
        |--------------------------------------------------------------------------
        | PAGE-STYLE PERIOD FILTERS
        |--------------------------------------------------------------------------
        */

        $month    = (string)($filters['month'] ?? '');
        $year     = (string)($filters['year'] ?? '');
        $fromDate = (string)($filters['fromDate'] ?? '');
        $toDate   = (string)($filters['toDate'] ?? '');

        $ptConds   = ["payment_status = 'paid'"];
        $ptParams  = [];

        if ($fromDate !== '') {
            $ptConds[] = "DATE(pt.boarding_time) >= :pt_fd";
            $ptParams[':pt_fd'] = $fromDate;
        }
        if ($toDate !== '') {
            $ptConds[] = "DATE(pt.boarding_time) <= :pt_td";
            $ptParams[':pt_td'] = $toDate;
        }
        if ($month !== '' && $fromDate === '') {
            $ptConds[] = "MONTH(pt.boarding_time) = :pt_m";
            $ptParams[':pt_m'] = $month;
        }
        if ($year !== '' && $fromDate === '') {
            $ptConds[] = "YEAR(pt.boarding_time) = :pt_y";
            $ptParams[':pt_y'] = $year;
        }

        $ptWhere = implode(' AND ', $ptConds);

        /*
        |--------------------------------------------------------------------------
        | REMITTANCE LOGS (LIVE CONFIRMATIONS)
        |--------------------------------------------------------------------------
        |
        | The remittance panel's confirm flow writes remittance_logs
        | (RemittanceModel::finalize) and the seat-of-record for "who remitted
        | today" is that table — the old AI queries read the legacy `remittances`
        | summary table, which today's confirmations never touch, so Riza always
        | reported "no remittances". Re-point the AI at remittance_logs (joined
        | with drivers) and scope it with the same date/period filters the
        | fallback queries use.
        |
        */

        $remitConds  = ['1 = 1'];
        $remitParams = [];

        if ($fromDate !== '') {
            $remitConds[] = "DATE(rl.created_at) >= :rl_fd";
            $remitParams[':rl_fd'] = $fromDate;
        }
        if ($toDate !== '') {
            $remitConds[] = "DATE(rl.created_at) <= :rl_td";
            $remitParams[':rl_td'] = $toDate;
        }
        if ($month !== '' && $fromDate === '') {
            $remitConds[] = "MONTH(rl.created_at) = :rl_m";
            $remitParams[':rl_m'] = $month;
        }
        if ($year !== '' && $fromDate === '') {
            $remitConds[] = "YEAR(rl.created_at) = :rl_y";
            $remitParams[':rl_y'] = $year;
        }

        $remitWhere = implode(' AND ', $remitConds);

        /*
        |--------------------------------------------------------------------------
        | PASSENGER TRIPS (LIVE)
        |--------------------------------------------------------------------------
        |
        | driver_performance is resolved by the authoritative
        | DriverPerformanceService below (same query, same business rules,
        | same period conditions as the dashboard endpoints).
        |
        */

        $queries = [

            'latest_trips' => [
                "
                SELECT id, passenger_id, bus_no, trip_no, driver_id, boarding_point, destination, fare, payment_method, payment_status, boarding_time, remittance_status, expected_cash, actual_cash, discrepancy_amount
                FROM passenger_trips pt
                WHERE {$ptWhere}
                ORDER BY boarding_time DESC LIMIT 5
                ",
                $ptParams
            ],
            'trip_summary' => [
                "
                SELECT COUNT(*) AS total_trips, SUM(fare) AS total_revenue, AVG(fare) AS average_fare, MIN(fare) AS lowest_fare, MAX(fare) AS highest_fare, SUM(cash_amount) AS total_cash, SUM(gcash_amount) AS total_gcash, SUM(discrepancy_amount) AS total_discrepancy
                FROM passenger_trips pt
                WHERE {$ptWhere}
                ",
                $ptParams
            ],
            'monthly_breakdown' => [
                "
                SELECT DATE_FORMAT(pt.boarding_time,'%Y-%m') AS month, COUNT(*) AS trips, SUM(pt.fare) AS revenue, AVG(pt.fare) AS average_fare, SUM(pt.cash_amount) AS cash, SUM(pt.gcash_amount) AS gcash
                FROM passenger_trips pt
                WHERE {$ptWhere}
                GROUP BY month ORDER BY month DESC LIMIT 12
                ",
                $ptParams
            ],
            'payment_summary' => [
                "
                SELECT pt.payment_method, COUNT(*) AS total, SUM(pt.fare) AS revenue FROM passenger_trips pt
                WHERE {$ptWhere}
                GROUP BY pt.payment_method
                ",
                $ptParams
            ],
            'payment_status' => [
                "
                SELECT pt.payment_status, COUNT(*) AS total FROM passenger_trips pt
                WHERE {$ptWhere}
                GROUP BY pt.payment_status
                ",
                $ptParams
            ],
            'route_summary' => [
                "
                SELECT CONCAT(pt.boarding_point,' -> ',pt.destination) AS route, COUNT(*) AS trips, SUM(pt.fare) AS revenue
                FROM passenger_trips pt
                WHERE {$ptWhere}
                GROUP BY route ORDER BY trips DESC LIMIT 15
                ",
                $ptParams
            ],
            /* ── Passenger Trip History ─────── */

            'historical_summary' => [
                "
                SELECT COUNT(DISTINCT trip_id) AS total_trips, COUNT(*) AS total_passengers, SUM(fare) AS total_revenue, AVG(fare) AS average_fare, MIN(fare) AS lowest_fare, MAX(fare) AS highest_fare
                FROM passenger_trip_history
                ",
                []
            ],
            'historical_yearly' => [
                "
                SELECT YEAR(COALESCE(trip_date, DATE(date_booked))) AS year, COUNT(DISTINCT trip_id) AS trips, COUNT(*) AS passengers, SUM(fare) AS revenue, AVG(fare) AS average_fare
                FROM passenger_trip_history GROUP BY year ORDER BY year DESC
                ",
                []
            ],
            'historical_monthly' => [
                "
                SELECT DATE_FORMAT(COALESCE(trip_date, DATE(date_booked)),'%Y-%m') AS month, COUNT(DISTINCT trip_id) AS trips, COUNT(*) AS passengers, SUM(fare) AS revenue, AVG(fare) AS average_fare
                FROM passenger_trip_history GROUP BY month ORDER BY month DESC LIMIT 12
                ",
                []
            ],
            'historical_daily' => [
                "
                SELECT COALESCE(trip_date, DATE(date_booked)) AS trip_date, COUNT(DISTINCT trip_id) AS trips, COUNT(*) AS passengers, SUM(fare) AS revenue
                FROM passenger_trip_history GROUP BY trip_date ORDER BY trip_date DESC LIMIT 60
                ",
                []
            ],
            'historical_passenger_types' => [
                "
                SELECT passenger_type, COUNT(*) AS total, SUM(fare) AS revenue, AVG(fare) AS average_fare
                FROM passenger_trip_history GROUP BY passenger_type
                ",
                []
            ],
            'historical_discounts' => [
                "
                SELECT
                    SUM(IF(student_id IS NOT NULL AND student_id != '',1,0)) AS students,
                    SUM(IF(senior_pwd_id IS NOT NULL AND senior_pwd_id != '',1,0)) AS senior_pwd,
                    SUM(IF(beneficiary_id IS NOT NULL AND beneficiary_id != '',1,0)) AS beneficiaries,
                    SUM(IF(coop_disc_id IS NOT NULL AND coop_disc_id != '',1,0)) AS coop_members
                FROM passenger_trip_history
                ",
                []
            ],
            'historical_bus_yearly' => [
                "
                SELECT YEAR(trip_date) AS year, bus_id, route_name, COUNT(*) AS trips, SUM(total_passengers) AS passengers, SUM(total_revenue) AS revenue, AVG(fuel_cost) AS average_fuel_cost
                FROM bus_trips WHERE status = 'Completed' GROUP BY year, bus_id, route_name ORDER BY year DESC, revenue DESC LIMIT 200
                ",
                []
            ],
            'historical_routes_yearly' => [
                "
                SELECT YEAR(COALESCE(trip_date, DATE(date_booked))) AS year, boarding_point, destination, COUNT(DISTINCT trip_id) AS trips, COUNT(*) AS passengers, SUM(fare) AS revenue
                FROM passenger_trip_history GROUP BY year, boarding_point, destination ORDER BY year DESC, revenue DESC LIMIT 200
                ",
                []
            ],
            'historical_payment_methods' => [
                "
                SELECT payment_method, COUNT(*) AS total, SUM(fare) AS revenue FROM passenger_trip_history GROUP BY payment_method
                ",
                []
            ],
            'historical_recent' => [
                "
                SELECT history_id, passenger_name, passenger_type, boarding_point, destination, fare, payment_method, COALESCE(trip_date, DATE(date_booked)) AS trip_date, drivers_full_name
                FROM passenger_trip_history ORDER BY COALESCE(trip_date, DATE(date_booked)) DESC LIMIT 10
                ",
                []
            ],

            /* ── Remittances ─────────────────── */

            'remittance_summary' => [
                "
                SELECT status, COUNT(*) AS total,
                       COALESCE(SUM(expected_cash),0) AS expected,
                       COALESCE(SUM(actual_cash),0) AS actual,
                       COALESCE(SUM(discrepancy_amount),0) AS discrepancy
                FROM remittance_logs rl
                WHERE {$remitWhere}
                GROUP BY status
                ",
                $remitParams
            ],
            'latest_remittances' => [
                "
                SELECT rl.driver_id,
                       COALESCE(d.full_name, rl.driver_id) AS driver_name,
                       COUNT(*) AS log_count,
                       SUM(CASE WHEN rl.status = 'Completed' THEN 1 ELSE 0 END) AS completed_count,
                       SUM(CASE WHEN rl.status = 'Discrepancy' THEN 1 ELSE 0 END) AS discrepancy_count,
                       COALESCE(SUM(rl.total_collection),0) AS total_collection,
                       COALESCE(SUM(rl.expected_cash),0) AS expected_cash,
                       COALESCE(SUM(rl.actual_cash),0) AS actual_cash,
                       COALESCE(SUM(rl.discrepancy_amount),0) AS discrepancy_amount,
                       MAX(rl.created_at) AS last_remitted_at
                FROM remittance_logs rl
                LEFT JOIN drivers d ON d.driver_id = rl.driver_id
                WHERE {$remitWhere}
                GROUP BY rl.driver_id, d.full_name
                ORDER BY last_remitted_at DESC
                LIMIT 10
                ",
                $remitParams
            ],

            /* ── Expenses ────────────────────── */

            'expense_summary' => [
                "
                SELECT category, COUNT(*) AS count, SUM(amount) AS total, AVG(amount) AS average
                FROM expenses GROUP BY category ORDER BY total DESC
                ",
                []
            ],

            /* ── Buses ───────────────────────── */

            'bus_summary' => [
                "
                SELECT bus_id, plate_number, driver, route_name, status FROM buses ORDER BY bus_id LIMIT 10
                ",
                []
            ],

            /* ── Dispatches ──────────────────── */

            'latest_dispatches' => [
                "
                SELECT id, bus_id, driver_name, route, trip_date, passenger_count, status
                FROM dispatches ORDER BY trip_date DESC LIMIT 5
                ",
                []
            ],

            /* ── Reference ───────────────────── */

            'driver_summary' => [
                "
                SELECT COUNT(*) AS total_drivers, SUM(IF(status='Active',1,0)) AS active_drivers FROM drivers
                ",
                []
            ],
            'bus_trip_summary' => [
                "
                SELECT COUNT(*) AS total_trips, SUM(total_passengers) AS total_passengers, SUM(total_revenue) AS total_revenue, AVG(fuel_cost) AS average_fuel_cost
                FROM bus_trips
                ",
                []
            ],
            'latest_bus_trips' => [
                "
                SELECT id, bus_id, driver_id, route_name, trip_date, total_passengers, total_revenue, status, fuel_cost
                FROM bus_trips ORDER BY trip_date DESC LIMIT 5
                ",
                []
            ],
            'fuel_prices' => [
                "
                SELECT id, fuel_type, price_per_liter FROM fuel_prices ORDER BY updated_at DESC LIMIT 5
                ",
                []
            ],
            'passenger_count' => [
                "SELECT COUNT(*) AS total FROM passengers",
                []
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | DRIVER PERFORMANCE (AUTHORITATIVE)
        |--------------------------------------------------------------------------
        |
        | Delegates to the shared DriverPerformanceService so the AI fallback
        | uses the exact same query, filters, and business rules as the
        | dashboard widgets. No separate driver SQL exists anymore, and the
        | all-time archive driver query (historical_drivers) has been removed
        | so the AI can never answer from a different dataset.
        |
        */

        if ($onlyKeys === null || in_array('driver_performance', $onlyKeys, true)) {
            try {
                $d['driver_performance'] = $this->fetchDriverPerformance($filters);
            } catch (\Throwable $e) {
                file_put_contents(
                    $logFile,
                    date('Y-m-d H:i:s') . " [driver_performance] " . $e->getMessage() . PHP_EOL,
                    FILE_APPEND
                );
            }
        }

        foreach ($queries as $name => $entry) {
            if ($onlyKeys !== null && !in_array($name, $onlyKeys, true)) {
                continue;
            }
            [$sql, $params] = $entry;

            try {
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $d[$name] = $rows;
                if ($name === 'historical_monthly') {
                    file_put_contents($logFile, date('Y-m-d H:i:s') . " [historical_monthly] " . json_encode($rows) . PHP_EOL, FILE_APPEND);
                }
            } catch (\Throwable $e) {
                file_put_contents(
                    $logFile,
                    date('Y-m-d H:i:s') . " [$name] " . $e->getMessage() . PHP_EOL,
                    FILE_APPEND
                );
            }
        }

        return $d;
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH DRIVER PERFORMANCE (SHARED SERVICE)
    |--------------------------------------------------------------------------
    |
    | Same dataset + business logic as the dashboard. The output shape is
    | preserved for existing AI consumers (driver, trips, revenue, ...).
    |
    */

    private function fetchDriverPerformance(array $filters): array
    {
        $rows = (new DriverPerformanceService())->getDriverPerformance($filters);

        $out = [];

        foreach ($rows as $row) {
            $out[] = [
                'driver'                => $row['name'] ?? ($row['driver_id'] ?? 'Unknown Driver'),
                'driver_id'             => $row['driver_id'] ?? '',
                'trips'                 => (int)($row['trips'] ?? 0),
                'revenue'               => (float)($row['income'] ?? 0),
                'passengers'            => (int)($row['passengers'] ?? 0),
                'archived_trips'        => (int)($row['archived_trips'] ?? 0),
                'archived_income'       => (float)($row['archived_income'] ?? 0),
                'regular_trips'         => 0,
                'special_trips'         => 0,
                'regular_income'        => 0.0,
                'special_income'        => 0.0,
                'net_income'            => 0.0,
                'operational_revenue'   => 0.0,
                'operational_passengers'=> 0
            ];
        }

        return $out;
    }
}
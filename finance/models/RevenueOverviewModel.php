<?php

namespace Finance\Models;

require_once __DIR__ . '/../../app/Core/Database.php';

use App\Core\Database;

class RevenueOverviewModel
{
    /**
     * =========================
     * BUILD FILTER CONDITIONS
     * =========================
     */
    private static function buildFilters(array $filters): array
    {
        $ptWhere  = '';
        $pthWhere = '';

        $db = Database::get();

        $fromDate = null;
        $toDate   = null;

        if (!empty($filters['type'])) {
            switch ($filters['type']) {
                case 'today':
                    $fromDate = date('Y-m-d');
                    $toDate   = date('Y-m-d');
                    break;
                case 'month':
                    $fromDate = date('Y-m-01');
                    $toDate   = date('Y-m-t');
                    break;
                case 'year':
                    $fromDate = date('Y-01-01');
                    $toDate   = date('Y-12-31');
                    break;
            }
        }

        if (!empty($filters['fromDate'])) {
            $fromDate = $filters['fromDate'];
        }
        if (!empty($filters['toDate'])) {
            $toDate = $filters['toDate'];
        }

        if ($fromDate) {
            $d = $db->quote($fromDate);
            $ptWhere  .= " AND DATE(boarding_time) >= $d";
            $pthWhere .= " AND DATE(trip_date) >= $d";
        }
        if ($toDate) {
            $d = $db->quote($toDate);
            $ptWhere  .= " AND DATE(boarding_time) <= $d";
            $pthWhere .= " AND DATE(trip_date) <= $d";
        }

        if (!empty($filters['day'])) {
            $d = (int)$filters['day'];
            $ptWhere  .= " AND DAY(boarding_time) = $d";
            $pthWhere .= " AND DAY(trip_date) = $d";
        }
        if (!empty($filters['month'])) {
            $d = (int)$filters['month'];
            $ptWhere  .= " AND MONTH(boarding_time) = $d";
            $pthWhere .= " AND MONTH(trip_date) = $d";
        }
        if (!empty($filters['year'])) {
            $d = (int)$filters['year'];
            $ptWhere  .= " AND YEAR(boarding_time) = $d";
            $pthWhere .= " AND YEAR(trip_date) = $d";
        }

        if (!empty($filters['bus'])) {
            $b = $db->quote('%' . $filters['bus'] . '%');
            $ptWhere  .= " AND driver_id LIKE $b";
            $pthWhere .= " AND driver_id LIKE $b";
        }

        if (!empty($filters['filter_route'])) {
            $r = $db->quote('%' . $filters['filter_route'] . '%');
            $ptWhere  .= " AND CONCAT(boarding_point, ' ', destination) LIKE $r";
            $pthWhere .= " AND CONCAT(boarding_point, ' ', destination) LIKE $r";
        }

        $pthFilter = $pthWhere ? "WHERE 1=1 $pthWhere" : '';

        return [$ptWhere, $pthFilter];
    }

    /**
     * =========================
     * OVERVIEW STATS
     * =========================
     */
    public static function getOverviewStats(array $filters = [])
    {
        $db = Database::get();

        [$ptDateWhere, $pthDateWhere] = self::buildFilters($filters);

        /**
         * =========================
         * TOTAL INCOME
         * =========================
         */
        $totalIncomeQuery = "
            SELECT
                COALESCE(SUM(fare), 0) AS total_income
            FROM (
                SELECT fare FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT fare FROM passenger_trip_history $pthDateWhere
            ) combined
        ";

        $totalIncome =
            $db->query($totalIncomeQuery)
               ->fetch();

        /**
         * =========================
         * AVG INCOME PER TRIP
         * =========================
         */
        $avgIncomeQuery = "
            SELECT
                COALESCE(AVG(trip_total), 0) AS avg_income
            FROM (
                SELECT SUM(fare) AS trip_total
                FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                GROUP BY trip_no
                UNION ALL
                SELECT SUM(fare) AS trip_total
                FROM passenger_trip_history $pthDateWhere
                GROUP BY trip_id
            ) trip_totals
        ";

        $avgIncome =
            $db->query($avgIncomeQuery)
               ->fetch();

        /**
         * =========================
         * ACTIVE BUSES
         * =========================
         */
        $activeBusQuery = "
            SELECT COUNT(DISTINCT bus_id) AS active_buses
            FROM (
                SELECT driver_id AS bus_id FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT driver_id AS bus_id FROM passenger_trip_history $pthDateWhere
            ) combined
        ";

        $activeBuses =
            $db->query($activeBusQuery)
               ->fetch();

        /**
         * =========================
         * PEAK INCOME HOUR
         * =========================
         */
        $peakHourQuery = "
            SELECT
                CONCAT(
                    LPAD(HOUR(boarding_time), 2, '0'),
                    ':00 to ',
                    LPAD(HOUR(boarding_time) + 1, 2, '0'),
                    ':00'
                ) AS peak_hour,
                COALESCE(SUM(fare), 0) AS total_sales
            FROM passenger_trips
            WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
            GROUP BY HOUR(boarding_time)
            ORDER BY total_sales DESC
            LIMIT 1
        ";

        $peakHour =
            $db->query($peakHourQuery)
               ->fetch();

        /**
         * =========================
         * TOTAL TRIPS
         * =========================
         */
        $tripCountQuery = "
            SELECT COUNT(DISTINCT trip_id) AS total_trips
            FROM (
                SELECT trip_no AS trip_id FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT trip_id FROM passenger_trip_history $pthDateWhere
            ) combined
        ";

        $tripCount =
            $db->query($tripCountQuery)
               ->fetch();

        /**
         * =========================
         * INCOME PER BUS
         * =========================
         */
        $incomePerBusQuery = "
            SELECT
                bus_id,
                COUNT(DISTINCT trip_id) AS total_trips,
                COUNT(*) AS total_passengers,
                COALESCE(SUM(fare), 0) AS revenue
            FROM (
                SELECT
                    driver_id AS bus_id,
                    trip_no AS trip_id,
                    id AS rec_id,
                    fare
                FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT
                    driver_id AS bus_id,
                    trip_id,
                    history_id AS rec_id,
                    fare
                FROM passenger_trip_history $pthDateWhere
            ) combined
            GROUP BY bus_id
            ORDER BY revenue DESC
        ";

        $incomePerBus =
            $db->query($incomePerBusQuery)
               ->fetchAll();

        /**
         * =========================
         * TRIPS PER BUS
         * =========================
         */
        $tripsPerBusQuery = "
            SELECT
                bus_id,
                COUNT(DISTINCT trip_id) AS total_trips
            FROM (
                SELECT driver_id AS bus_id, trip_no AS trip_id
                FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT driver_id AS bus_id, trip_id
                FROM passenger_trip_history $pthDateWhere
            ) combined
            GROUP BY bus_id
            ORDER BY total_trips DESC
        ";

        $tripsPerBus =
            $db->query($tripsPerBusQuery)
               ->fetchAll();

        /**
         * =========================
         * HIGHEST EARNING BUS
         * =========================
         */
        $highestBusQuery = "
            SELECT
                bus_id,
                COUNT(DISTINCT trip_id) AS total_trips,
                COALESCE(SUM(fare), 0) AS revenue
            FROM (
                SELECT driver_id AS bus_id, trip_no AS trip_id, fare
                FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT driver_id AS bus_id, trip_id, fare
                FROM passenger_trip_history $pthDateWhere
            ) combined
            GROUP BY bus_id
            ORDER BY revenue DESC
            LIMIT 1
        ";

        $highestBus =
            $db->query($highestBusQuery)
               ->fetch();

        /**
         * =========================
         * CONTRIBUTION PER BUS
         * =========================
         */
        $contributionBusQuery = "
            SELECT
                bus_id,
                COALESCE(SUM(fare), 0) AS revenue
            FROM (
                SELECT driver_id AS bus_id, fare
                FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT driver_id AS bus_id, fare
                FROM passenger_trip_history $pthDateWhere
            ) combined
            GROUP BY bus_id
            ORDER BY revenue DESC
        ";

        $contributionBus =
            $db->query($contributionBusQuery)
               ->fetchAll();

        /**
         * =========================
         * ROUTE INCOME
         * =========================
         */
        $routeIncomeQuery = "
            SELECT
                boarding_point,
                destination,
                COUNT(*) AS passengers,
                COALESCE(SUM(fare), 0) AS revenue,
                COALESCE(AVG(fare), 0) AS avg_trip_income
            FROM (
                SELECT boarding_point, destination, fare
                FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT boarding_point, destination, fare
                FROM passenger_trip_history $pthDateWhere
            ) combined
            GROUP BY boarding_point, destination
            ORDER BY revenue DESC
            LIMIT 7
        ";

        $routeIncome =
            $db->query($routeIncomeQuery)
               ->fetchAll();

        /**
         * =========================
         * PEAK HOURS (live trips only — history has no time data)
         * =========================
         */
        $peakHoursQuery = "
            SELECT
                CONCAT(LPAD(HOUR(boarding_time), 2, '0'), ':00') AS hour_label,
                COALESCE(SUM(fare), 0) AS revenue
            FROM passenger_trips
            WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
            GROUP BY HOUR(boarding_time)
            ORDER BY revenue DESC
        ";

        $peakHours =
            $db->query($peakHoursQuery)
               ->fetchAll();

        /**
         * =========================
         * HIGHEST DAY
         * =========================
         */
        $highestDaysQuery = "
            SELECT
                day_name,
                COALESCE(SUM(fare), 0) AS revenue
            FROM (
                SELECT DAYNAME(boarding_time) AS day_name, fare
                FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT DAYNAME(trip_date) AS day_name, fare
                FROM passenger_trip_history $pthDateWhere
            ) combined
            GROUP BY day_name
            ORDER BY revenue DESC
        ";

        $highestDays =
            $db->query($highestDaysQuery)
               ->fetchAll();

        /**
         * =========================
         * WEEKLY REVENUE
         * =========================
         */
        $weeklyRevenueQuery = "
            SELECT
                CONCAT('Week ', week_num) AS week_label,
                COALESCE(SUM(fare), 0) AS revenue
            FROM (
                SELECT WEEK(boarding_time) AS week_num, fare
                FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT WEEK(trip_date) AS week_num, fare
                FROM passenger_trip_history $pthDateWhere
            ) combined
            GROUP BY week_num
            ORDER BY week_num
        ";

        $weeklyRevenue =
            $db->query($weeklyRevenueQuery)
               ->fetchAll();

        /**
         * =========================
         * MONTHLY REVENUE
         * =========================
         */
        $monthlyRevenueQuery = "
            SELECT
                month_label,
                COALESCE(SUM(fare), 0) AS revenue
            FROM (
                SELECT DATE_FORMAT(boarding_time, '%b') AS month_label, MONTH(boarding_time) AS month_num, fare
                FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT DATE_FORMAT(trip_date, '%b') AS month_label, MONTH(trip_date) AS month_num, fare
                FROM passenger_trip_history $pthDateWhere
            ) combined
            GROUP BY month_label, month_num
            ORDER BY month_num
        ";

        $monthlyRevenue =
            $db->query($monthlyRevenueQuery)
               ->fetchAll();

        /**
         * =========================
         * DAILY REVENUE
         * =========================
         */
        $dailyRevenueQuery = "
            SELECT
                day_label,
                COALESCE(SUM(fare), 0) AS revenue
            FROM (
                SELECT DAYNAME(boarding_time) AS day_label, fare
                FROM passenger_trips
                WHERE LOWER(TRIM(payment_status)) IN ('paid','pending','ongoing','rejected') $ptDateWhere
                UNION ALL
                SELECT DAYNAME(trip_date) AS day_label, fare
                FROM passenger_trip_history $pthDateWhere
            ) combined
            GROUP BY day_label
        ";

        $dailyRevenue =
            $db->query($dailyRevenueQuery)
               ->fetchAll();

        /**
         * =========================
         * RETURN DATA
         * =========================
         */
        return [

            'total_income' =>

                (float)(
                    $totalIncome['total_income']
                    ?? 0
                ),

            'avg_income_per_trip' =>

                (float)(
                    $avgIncome['avg_income']
                    ?? 0
                ),

            'active_buses' =>

                (int)(
                    $activeBuses['active_buses']
                    ?? 0
                ),

            'peak_income_hour' =>

                $peakHour['peak_hour']
                ?? 'N/A',

            'total_trips' =>

                (int)(
                    $tripCount['total_trips']
                    ?? 0
                ),

            /**
             * BUS PERFORMANCE
             */
            'incomePerBus' =>

                $incomePerBus,

            'tripsPerBus' =>

                $tripsPerBus,

            'highestBus' =>

                $highestBus,

            'contributionBus' =>

                $contributionBus,

            'averageRevenue' => [

                'average_revenue' =>

                    (float)(
                        $avgIncome['avg_income']
                        ?? 0
                    )
            ],

            /**
             * ROUTE
             */
            'routeIncome' =>

                $routeIncome,

            /**
             * HOURLY
             */
            'peakHours' =>

                $peakHours,

            /**
             * DAILY
             */
            'highestDays' =>

                $highestDays,

            'dailyRevenue' =>

                $dailyRevenue,

            /**
             * WEEKLY
             */
            'weeklyRevenue' =>

                $weeklyRevenue,

            /**
             * MONTHLY
             */
            'monthlyRevenue' =>

                $monthlyRevenue
        ];
    }
}

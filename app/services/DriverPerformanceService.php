<?php

declare(strict_types=1);

namespace App\Services;

require_once __DIR__
    . '/../Core/Database.php';

use App\Core\Database;

/*
|--------------------------------------------------------------------------
| DRIVER PERFORMANCE SERVICE (AUTHORITATIVE)
|--------------------------------------------------------------------------
|
| Single source of truth for all driver-performance calculations used by:
|
|   1. The admin dashboard APIs:  admin/api/dashboard/top-drivers.php
|                                 admin/api/dashboard/driver-performance.php
|   2. The AI assistant fallback: finance/models/AI/AIAnalyticsModel.php
|                                 (key: driver_performance)
|
| Every consumer runs the SAME query and the SAME business rules so the
| dashboard numbers, the AI numbers, and the backend always agree:
|
|   - live rows come from passenger_trips restricted to payment_status='paid'
|   - archived rows come from passenger_trip_history (when the table exists),
|     same period conditions as the live rows
|   - a trip is counted as COUNT(DISTINCT trip_key), where
|       trip_key = CONCAT('l-', trip_no)  for live rows
|       trip_key = CONCAT('h-', trip_id)  for archived rows
|   - passengers are counted as COUNT(DISTINCT passenger_identifier)
|   - income = SUM(fare), performance = income / trips
|   - the selected page period is applied to Date(boarding_time) / Date(trip_date)
|     exactly like the dashboard endpoints (fromDate/toDate, or month/year)
|   - timezone is pinned to Asia/Manila (the cooperative's application TZ)
|
*/
class DriverPerformanceService
{
    private \PDO $conn;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCT
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        date_default_timezone_set('Asia/Manila');
        $this->conn = Database::get();
    }

    /*
    |--------------------------------------------------------------------------
    | GET DRIVER PERFORMANCE
    |--------------------------------------------------------------------------
    |
    | $filters: month, year, fromDate, toDate  (same GET params the admin
    |           dashboard endpoints accept)
    |
    | $options: orderBy   (whitelisted column, default 'income')
    |           orderDir  ('ASC' | 'DESC', default 'DESC')
    |           limit     (int > 0 to cap rows, 0 = no limit)
    |
    | Returns the full canonical rows. Consumers shape/narrow the output.
    |
    */

    public function getDriverPerformance(array $filters = [], array $options = []): array
    {
        $month    = (string)($filters['month'] ?? '');
        $year     = (string)($filters['year'] ?? '');
        $fromDate = (string)($filters['fromDate'] ?? '');
        $toDate   = (string)($filters['toDate'] ?? '');

        $ptConds   = ["payment_status = 'paid'"];
        $ptParams  = [];
        $pthConds  = [];
        $pthParams = [];

        if ($fromDate !== '') {
            $ptConds[]  = "DATE(pt.boarding_time) >= :pt_fd";
            $ptParams[':pt_fd'] = $fromDate;
            $pthConds[] = "DATE(pth.trip_date) >= :pth_fd";
            $pthParams[':pth_fd'] = $fromDate;
        }
        if ($toDate !== '') {
            $ptConds[]  = "DATE(pt.boarding_time) <= :pt_td";
            $ptParams[':pt_td'] = $toDate;
            $pthConds[] = "DATE(pth.trip_date) <= :pth_td";
            $pthParams[':pth_td'] = $toDate;
        }
        if ($month !== '' && $fromDate === '') {
            $ptConds[]  = "MONTH(pt.boarding_time) = :pt_m";
            $ptParams[':pt_m'] = $month;
            $pthConds[] = "MONTH(pth.trip_date) = :pth_m";
            $pthParams[':pth_m'] = $month;
        }
        if ($year !== '' && $fromDate === '') {
            $ptConds[]  = "YEAR(pt.boarding_time) = :pt_y";
            $ptParams[':pt_y'] = $year;
            $pthConds[] = "YEAR(pth.trip_date) = :pth_y";
            $pthParams[':pth_y'] = $year;
        }

        $ptWhere = implode(' AND ', $ptConds);

        /*
        |--------------------------------------------------------------------------
        | ARRIVED TRIPS (HISTORY UNION) - same period conditions as live rows
        |--------------------------------------------------------------------------
        */

        $hasHistory = false;

        try {
            $check = $this->conn->query("SHOW TABLES LIKE 'passenger_trip_history'");
            $hasHistory = $check->fetchColumn() ? true : false;
        } catch (\Throwable $e) {
            $hasHistory = false;
        }

        $historyUnion   = '';
        $historyParams  = [];

        if ($hasHistory) {
            $pthExtra = $pthConds ? ' AND ' . implode(' AND ', $pthConds) : '';

            $historyUnion = "
            UNION ALL
            SELECT
                CONVERT(driver_id USING utf8mb4) COLLATE utf8mb4_general_ci AS driver_id,
                history_id AS record_id,
                CONVERT(passenger_name USING utf8mb4) COLLATE utf8mb4_general_ci AS passenger_identifier,
                fare,
                CONVERT('cash' USING utf8mb4) COLLATE utf8mb4_general_ci AS payment_method,
                CONVERT(CONCAT('h-', trip_id) USING utf8mb4) COLLATE utf8mb4_general_ci AS trip_key
            FROM passenger_trip_history pth
            WHERE 1=1 {$pthExtra}
            ";

            $historyParams = $pthParams;
        }

        /*
        |--------------------------------------------------------------------------
        | CANONICAL QUERY
        |--------------------------------------------------------------------------
        */

        $orderBy = (string)($options['orderBy'] ?? 'income');

        $allowedOrderBy = [
            'id', 'name', 'driver_id', 'photo',
            'income', 'trips', 'passengers', 'performance',
            'cash_payments', 'gcash_payments', 'hybrid_payments',
            'archived_trips', 'archived_income'
        ];

        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'income';
        }

        $orderDir = strtoupper((string)($options['orderDir'] ?? 'DESC'));
        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $limit = isset($options['limit']) ? (int)$options['limit'] : 0;
        if ($limit < 0) {
            $limit = 0;
        }

        /* The live drivers table does not carry a photo (or necessarily the
           same name) column on every schema revision, so both are discovered
           once and fall back to empty values instead of failing the query. */
        $driverColumns = [];
        try {
            foreach ($this->conn->query('SHOW COLUMNS FROM drivers') as $columnRow) {
                $driverColumns[strtolower((string) $columnRow['Field'])] = true;
            }
        } catch (\Throwable $e) {
            $driverColumns = [];
        }
        $photoExpr = isset($driverColumns['photo']) ? 'd.photo' : "''";
        $nameExpr = isset($driverColumns['full_name'])
            ? 'd.full_name'
            : (isset($driverColumns['name']) ? 'd.name' : "''");

        $sql = "
        SELECT
            d.id,
            d.driver_id,
            {$nameExpr} AS name,
            {$photoExpr} AS photo,
            COALESCE(SUM(all_data.fare), 0) AS income,
            COUNT(DISTINCT all_data.trip_key) AS trips,
            COUNT(DISTINCT all_data.passenger_identifier) AS passengers,
            ROUND(
                CASE
                    WHEN COUNT(DISTINCT all_data.trip_key) = 0 THEN 0
                    ELSE COALESCE(SUM(all_data.fare), 0) / COUNT(DISTINCT all_data.trip_key)
                END,
                2
            ) AS performance,
            SUM(CASE WHEN LOWER(all_data.payment_method) = 'cash' THEN 1 ELSE 0 END) AS cash_payments,
            SUM(CASE WHEN LOWER(all_data.payment_method) = 'gcash' THEN 1 ELSE 0 END) AS gcash_payments,
            SUM(CASE WHEN LOWER(all_data.payment_method) = 'hybrid' THEN 1 ELSE 0 END) AS hybrid_payments,
            0 AS regular_trips,
            0 AS special_trips,
            COUNT(DISTINCT all_data.trip_key) AS archived_trips,
            0 AS regular_income,
            0 AS special_income,
            COALESCE(SUM(all_data.fare), 0) AS archived_income,
            0 AS net_income,
            0 AS operational_revenue,
            0 AS operational_passengers
        FROM drivers d
        LEFT JOIN (
            SELECT
                /* passenger_trips is stored as latin1 on the live database, so
                   every text column is converted before collating; a bare
                   COLLATE utf8mb4_general_ci fails with error 1253 when the
                   source column is latin1 and the whole query returns nothing. */
                CONVERT(driver_id USING utf8mb4) COLLATE utf8mb4_general_ci AS driver_id,
                id AS record_id,
                CONVERT(CAST(passenger_id AS CHAR) USING utf8mb4) COLLATE utf8mb4_general_ci AS passenger_identifier,
                fare,
                CONVERT(payment_method USING utf8mb4) COLLATE utf8mb4_general_ci AS payment_method,
                CONVERT(CONCAT('l-', trip_no) USING utf8mb4) COLLATE utf8mb4_general_ci AS trip_key
            FROM passenger_trips pt
            WHERE {$ptWhere}
            {$historyUnion}
        ) all_data ON LOWER(TRIM(d.driver_id)) = LOWER(TRIM(all_data.driver_id))
        GROUP BY d.id, d.driver_id, {$nameExpr}, {$photoExpr}
        ORDER BY {$orderBy} {$orderDir}
        ";

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }

        $allParams = array_merge($ptParams, $historyParams);

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($allParams);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['id']                     = (int)$row['id'];
            $row['trips']                  = (int)$row['trips'];
            $row['passengers']             = (int)$row['passengers'];
            $row['income']                 = (float)$row['income'];
            $row['performance']            = (float)$row['performance'];
            $row['cash_payments']          = (int)$row['cash_payments'];
            $row['gcash_payments']         = (int)$row['gcash_payments'];
            $row['hybrid_payments']        = (int)$row['hybrid_payments'];
            $row['regular_trips']          = (int)$row['regular_trips'];
            $row['special_trips']          = (int)$row['special_trips'];
            $row['archived_trips']         = (int)$row['archived_trips'];
            $row['regular_income']         = (float)$row['regular_income'];
            $row['special_income']         = (float)$row['special_income'];
            $row['archived_income']        = (float)$row['archived_income'];
            $row['net_income']             = (float)$row['net_income'];
            $row['operational_revenue']    = (float)$row['operational_revenue'];
            $row['operational_passengers'] = (int)$row['operational_passengers'];

            if (!empty($row['photo']) && $row['photo'][0] !== '/') {
                $row['photo'] = '/' . $row['photo'];
            }
        }

        return $rows;
    }
}
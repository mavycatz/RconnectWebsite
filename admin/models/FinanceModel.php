<?php declare(strict_types=1);

namespace Admin\Models;

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;
use PDO;
use PDOException;

class FinanceModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    public function getFinanceOverview(array $filters = []): array
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | FILTERS
            |--------------------------------------------------------------------------
            */

            $search = trim($filters['search'] ?? '');

            $category = trim($filters['category'] ?? '');

            $from = trim($filters['from'] ?? '');

            $to = trim($filters['to'] ?? '');

            /*
            |--------------------------------------------------------------------------
            | WHERE CONDITIONS
            |--------------------------------------------------------------------------
            */

            $where = [];

            $params = [];

            /*
            |--------------------------------------------------------------------------
            | SEARCH
            |--------------------------------------------------------------------------
            */

            if (!empty($search)) {

                $where[] = "
                    (
                        passenger_name LIKE :search
                        OR payment_method LIKE :search
                        OR origin LIKE :search
                        OR destination LIKE :search
                    )
                ";

                $params[':search'] = '%' . $search . '%';
            }

            /*
            |--------------------------------------------------------------------------
            | CATEGORY
            |--------------------------------------------------------------------------
            */

            if ($category === 'Cash') {

                $where[] = "payment_method = 'Cash'";
            }

            if ($category === 'GCash') {

                $where[] = "payment_method = 'GCash'";
            }

            /*
            |--------------------------------------------------------------------------
            | DATE FILTERS
            |--------------------------------------------------------------------------
            */

            if (!empty($from)) {

                $where[] = "DATE(created_at) >= :from";

                $params[':from'] = $from;
            }

            if (!empty($to)) {

                $where[] = "DATE(created_at) <= :to";

                $params[':to'] = $to;
            }

            /*
            |--------------------------------------------------------------------------
            | WHERE SQL
            |--------------------------------------------------------------------------
            */

            $whereSQL = '';

            if (!empty($where)) {

                $whereSQL = 'WHERE ' . implode(' AND ', $where);
            }

            /*
            |--------------------------------------------------------------------------
            | FUEL LOGS DATA
            |--------------------------------------------------------------------------
            */

            $fuelLogsData = $this->getFuelLogsData($from, $to);

            /*
            |--------------------------------------------------------------------------
            | TOTAL REVENUE
            |--------------------------------------------------------------------------
            */

            $revenueStmt = $this->db->prepare("
                SELECT COALESCE(SUM(fare), 0) AS total_revenue
                FROM passenger_trips
                $whereSQL
            ");

            $revenueStmt->execute($params);

            $revenueData = $revenueStmt->fetch(PDO::FETCH_ASSOC);

            $totalRevenue = (float) (
                $revenueData['total_revenue'] ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | OPERATIONS EXPENSES
            |--------------------------------------------------------------------------
            */

            $operationsStmt = $this->db->prepare("
                SELECT COALESCE(
                    SUM(fuel_cost + other_expenses),
                0) AS operations_expenses
                FROM trip_operations_reports
            ");

            $operationsStmt->execute();

            $operationsData = $operationsStmt->fetch(PDO::FETCH_ASSOC);

            $operationsExpenses = (float) (
                $operationsData['operations_expenses'] ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | EXTRA EXPENSES
            |--------------------------------------------------------------------------
            */

            $extraStmt = $this->db->prepare("
                SELECT COALESCE(
                    SUM(
                        toll_fees +
                        maintenance_cost +
                        other_cost
                    ),
                0) AS extra_expenses
                FROM trip_expenses
            ");

            $extraStmt->execute();

            $extraData = $extraStmt->fetch(PDO::FETCH_ASSOC);

            $extraExpenses = (float) (
                $extraData['extra_expenses'] ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | TOTAL EXPENSES
            |--------------------------------------------------------------------------
            */

            $totalExpenses = $operationsExpenses + $extraExpenses;

            /*
            |--------------------------------------------------------------------------
            | NET PROFIT
            |--------------------------------------------------------------------------
            */

            $netProfit = $totalRevenue - $totalExpenses;

            /*
            |--------------------------------------------------------------------------
            | PASSENGERS
            |--------------------------------------------------------------------------
            */

            $passengerStmt = $this->db->prepare("
                SELECT COUNT(*) AS total_passengers
                FROM passenger_trips
                $whereSQL
            ");

            $passengerStmt->execute($params);

            $passengerData = $passengerStmt->fetch(PDO::FETCH_ASSOC);

            $totalPassengers = (int) (
                $passengerData['total_passengers'] ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | TRIPS
            |--------------------------------------------------------------------------
            */

            $tripStmt = $this->db->prepare("
                SELECT COUNT(*) AS total_trips
                FROM trip_operations_reports
            ");

            $tripStmt->execute();

            $tripData = $tripStmt->fetch(PDO::FETCH_ASSOC);

            $totalTrips = (int) (
                $tripData['total_trips'] ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | REMITTANCE
            |--------------------------------------------------------------------------
            */

            $remittanceStmt = $this->db->prepare("
                SELECT COALESCE(SUM(actual_cash), 0) AS total_remittance
                FROM remittance_logs
            ");

            $remittanceStmt->execute();

            $remittanceData = $remittanceStmt->fetch(PDO::FETCH_ASSOC);

            $totalRemittance = (float) (
                $remittanceData['total_remittance'] ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | GCASH REVENUE
            |--------------------------------------------------------------------------
            */

            $gcashStmt = $this->db->prepare("
                SELECT COALESCE(SUM(fare), 0) AS gcash_total
                FROM passenger_trips
                WHERE payment_method = 'GCash'
            ");

            $gcashStmt->execute();

            $gcashData = $gcashStmt->fetch(PDO::FETCH_ASSOC);

            $gcashRevenue = (float) (
                $gcashData['gcash_total'] ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | CASH REVENUE
            |--------------------------------------------------------------------------
            */

            $cashStmt = $this->db->prepare("
                SELECT COALESCE(SUM(fare), 0) AS cash_total
                FROM passenger_trips
                WHERE payment_method = 'Cash'
            ");

            $cashStmt->execute();

            $cashData = $cashStmt->fetch(PDO::FETCH_ASSOC);

            $cashRevenue = (float) (
                $cashData['cash_total'] ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | HISTORY RECORDS
            |--------------------------------------------------------------------------
            */

            $historyStmt = $this->db->prepare("
                SELECT COUNT(*) AS total_history_records
                FROM passenger_trip_history
            ");

            $historyStmt->execute();

            $historyData = $historyStmt->fetch(PDO::FETCH_ASSOC);

            $totalHistoryRecords = (int) (
                $historyData['total_history_records'] ?? 0
            );

            /*
            |--------------------------------------------------------------------------
            | AVERAGE FARE
            |--------------------------------------------------------------------------
            */

            $averageFare = 0;

            if ($totalPassengers > 0) {

                $averageFare = $totalRevenue / $totalPassengers;
            }

            /*
            |--------------------------------------------------------------------------
            | TRANSACTIONS
            |--------------------------------------------------------------------------
            */

            $transactions = [

                [
                    'category' => 'Cash Revenue',
                    'name' => 'Passenger Cash Payments',
                    'amount' => round($cashRevenue, 2),
                    'type' => 'Income'
                ],

                [
                    'category' => 'GCash Revenue',
                    'name' => 'Passenger GCash Payments',
                    'amount' => round($gcashRevenue, 2),
                    'type' => 'Income'
                ],

                [
                    'category' => 'Operations',
                    'name' => 'Fuel & Operations Expenses',
                    'amount' => round($operationsExpenses, 2),
                    'type' => 'Expense'
                ],

                [
                    'category' => 'Maintenance',
                    'name' => 'Maintenance & Toll Expenses',
                    'amount' => round($extraExpenses, 2),
                    'type' => 'Expense'
                ],

                [
                    'category' => 'Remittance',
                    'name' => 'Driver Remittance Logs',
                    'amount' => round($totalRemittance, 2),
                    'type' => 'Income'
                ]

            ];

            /*
            |--------------------------------------------------------------------------
            | FUEL LOGS
            |--------------------------------------------------------------------------
            */

            $fuelTotalCost = (float) ($fuelLogsData['totalFuelCost'] ?? 0);
            $fuelTotalLiters = (float) ($fuelLogsData['totalLiters'] ?? 0);
            $fuelLogCount = (int) ($fuelLogsData['logCount'] ?? 0);

            /*
            |--------------------------------------------------------------------------
            | RETURN
            |--------------------------------------------------------------------------
            */

            return [

                // MAIN
                'totalRevenue' => round($totalRevenue, 2),
                'totalExpenses' => round($totalExpenses, 2),
                'netProfit' => round($netProfit, 2),

                // OPERATIONS
                'operationsExpenses' => round($operationsExpenses, 2),
                'extraExpenses' => round($extraExpenses, 2),

                // PASSENGERS
                'totalPassengers' => $totalPassengers,
                'totalTrips' => $totalTrips,
                'averageFare' => round($averageFare, 2),

                // PAYMENTS
                'cashRevenue' => round($cashRevenue, 2),
                'gcashRevenue' => round($gcashRevenue, 2),
                'totalRemittance' => round($totalRemittance, 2),

                // HISTORY
                'totalHistoryRecords' => $totalHistoryRecords,

                // TRANSACTIONS
                'transactions' => $transactions,

                // FUEL LOGS
                'fuelLogs' => [
                    'totalFuelCost' => $fuelTotalCost,
                    'totalLiters' => $fuelTotalLiters,
                    'logCount' => $fuelLogCount,
                    'perBus' => $fuelLogsData['perBus'] ?? []
                ]

            ];

        } catch (PDOException $e) {

            return [

                'success' => false,

                'message' => $e->getMessage(),

                'totalRevenue' => 0,
                'totalExpenses' => 0,
                'netProfit' => 0,

                'transactions' => []

            ];
        }
    }

    public function getFuelLogsData(string $from = '', string $to = ''): array
    {
        try {
            $where = [];
            $params = [];

            if (!empty($from)) {
                $where[] = "DATE(created_at) >= :from";
                $params[':from'] = $from;
            }

            if (!empty($to)) {
                $where[] = "DATE(created_at) <= :to";
                $params[':to'] = $to;
            }

            $whereSQL = '';
            if (!empty($where)) {
                $whereSQL = 'WHERE ' . implode(' AND ', $where);
            }

            $totalStmt = $this->db->prepare("
                SELECT
                    COALESCE(SUM(cost), 0) AS total_cost,
                    COALESCE(SUM(liters), 0) AS total_liters,
                    COUNT(*) AS log_count
                FROM fuel_logs
                $whereSQL
            ");
            $totalStmt->execute($params);
            $totalData = $totalStmt->fetch(PDO::FETCH_ASSOC);

            $perBusStmt = $this->db->prepare("
                SELECT
                    bus_id,
                    COALESCE(SUM(liters), 0) AS total_liters,
                    COALESCE(SUM(cost), 0) AS total_cost,
                    COALESCE(SUM(cost) / NULLIF(SUM(liters), 0), 0) AS avg_price_per_liter,
                    COUNT(*) AS log_count
                FROM fuel_logs
                $whereSQL
                GROUP BY bus_id
                ORDER BY total_cost DESC
            ");
            $perBusStmt->execute($params);
            $perBus = $perBusStmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'totalFuelCost' => (float) ($totalData['total_cost'] ?? 0),
                'totalLiters' => (float) ($totalData['total_liters'] ?? 0),
                'logCount' => (int) ($totalData['log_count'] ?? 0),
                'perBus' => $perBus
            ];

        } catch (PDOException $e) {
            return [
                'totalFuelCost' => 0,
                'totalLiters' => 0,
                'logCount' => 0,
                'perBus' => []
            ];
        }
    }
}
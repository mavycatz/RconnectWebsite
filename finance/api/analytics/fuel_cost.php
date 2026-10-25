<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/../../services/FuelCostAnalyticsService.php';

use App\Core\Database;
use Finance\Services\FuelCostAnalyticsService;

validateFinanceRequest('standard', false);
setRequestTimeout(30);

try {
    $db = Database::tryGet();
    if ($db === null) {
        throw new \RuntimeException('Database connection unavailable.');
    }
    $service = new FuelCostAnalyticsService();

    $filters = [];
    if (!empty($_GET['from'])) $filters['from'] = $_GET['from'];
    if (!empty($_GET['to'])) $filters['to'] = $_GET['to'];
    if (!empty($_GET['bus'])) $filters['bus'] = $_GET['bus'];
    if (!empty($_GET['driver'])) $filters['driver'] = $_GET['driver'];
    if (!empty($_GET['period'])) $filters['period'] = $_GET['period'];

    [$filters['from'], $filters['to']] = $service->resolvePeriod($filters);

    $summary = $service->getSummary($filters);
    $fuelPerBus = $service->getFuelCostPerBus($filters, 10);
    $dailyTrend = $service->getDailyFuelTrend($filters);
    $costComparison = $service->getCostComparison($filters);

    $expenseBreakdown = [];
    $anomalies = [];
    try {
        $expCols = [];
        $colStmt = $db->query("SHOW COLUMNS FROM expenses");
        if ($colStmt) {
            while ($colRow = $colStmt->fetch(\PDO::FETCH_ASSOC)) {
                $expCols[strtolower((string) $colRow['Field'])] = true;
            }
        }

        if (isset($expCols['category']) && isset($expCols['amount'])) {
            $stmt = $db->query("
                SELECT category, SUM(amount) as total
                FROM expenses
                GROUP BY category
                ORDER BY total DESC
            ");
            $expenseBreakdown = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        if (isset($expCols['cost_level']) && isset($expCols['anomaly_flag'])) {
            $stmt = $db->query("
                SELECT id, bus_id, category, amount, description, created_at, cost_level, anomaly_flag
                FROM expenses
                WHERE cost_level = 'high' OR anomaly_flag = 1
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $anomalies = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
    } catch (\Throwable $e) {
        // Safe fallback if expenses table is empty or unavailable
    }

    echo json_encode([
        'success' => true,
        // Primary KPI: Average Fuel Consumed in Liters
        'avg_fuel_consumed' => (float) ($summary['avg_fuel_consumed'] ?? 0),
        'total_fuel_consumed' => (float) ($summary['total_fuel_consumed'] ?? 0),
        'valid_trips_count' => (int) ($summary['valid_trips_count'] ?? 0),
        // Estimated Fuel Cost
        'total_estimated_fuel_cost' => (float) ($summary['total_estimated_cost'] ?? 0),
        'total_actual_fuel_cost' => (float) ($summary['total_actual_cost'] ?? 0),
        // Actual refuel transactions (fuel_refuels)
        'total_refuel_cost' => (float) ($summary['total_refuel_cost'] ?? 0),
        'total_refuel_liters' => (float) ($summary['total_refuel_liters'] ?? 0),
        'refuel_events' => (int) ($summary['refuel_events'] ?? 0),
        // Top Bus
        'top_fuel_bus' => !empty($summary['top_bus']) ? $summary['top_bus']['bus'] . ' (₱' . number_format((float)$summary['top_bus']['total'], 2) . ')' : 'N/A',
        'diesel_price' => (float) ($summary['avg_price'] ?? 65.55),
        // Charts data
        'fuel_per_bus' => $fuelPerBus,
        'daily_trend' => $dailyTrend,
        'cost_comparison' => $costComparison,
        // Backward compatibility for dashboard and legacy callers
        'total_fuel_all_time' => (float) ($summary['total_estimated_cost'] ?? 0),
        'total_fuel_month' => (float) ($summary['total_estimated_cost'] ?? 0),
        'avg_cost_per_trip' => (float) ($summary['avg_cost'] ?? 0),
        // Expenses breakdown & anomalies
        'expense_breakdown' => $expenseBreakdown,
        'anomalies' => $anomalies,
    ]);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

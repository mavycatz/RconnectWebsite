<?php
namespace Finance\Services;

use App\Core\Database;

class DashboardService
{
    private function hasFuelRefuels(\PDO $db): bool
    {
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'fuel_refuels'");
            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getDashboardData($start = null, $end = null): array
    {
        $db = Database::get();
        $hasRefuels = $this->hasFuelRefuels($db);

        /* ---------------- real fuel figures from fuel_logs + fuel_refuels + expenses ----------------
           Actual purchases live in fuel_refuels for generator-produced history; fuel_logs carries
           the per-trip odometer/KPL rows (zero cost) plus legacy refuel rows. Each source is summed
           separately and merged in PHP so every query binds its own date parameters. */
        $fuelRange = '';
        $params = [];
        if ($start) { $fuelRange .= ' AND DATE(created_at) >= :start'; $params[':start'] = $start; }
        if ($end)   { $fuelRange .= ' AND DATE(created_at) <= :end';   $params[':end']   = $end; }

        $sumFuel = function (string $table, string $column, string $where = '') use ($db, $fuelRange, $params): float {
            $stmt = $db->prepare("SELECT COALESCE(SUM(`$column`),0) FROM `$table` WHERE 1=1 $where $fuelRange");
            $stmt->execute($params);
            return (float) $stmt->fetchColumn();
        };

        $totalFuelAllTime = $sumFuel('expenses', 'amount', "AND category = 'fuel'")
            + $sumFuel('fuel_logs', 'cost')
            + ($hasRefuels ? $sumFuel('fuel_refuels', 'total_cost') : 0.0);

        /* Refills and liters: legacy refuel rows on fuel_logs (liters > 0) plus fuel_refuels rows. */
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(liters),0) AS liters, COUNT(*) AS refills
            FROM fuel_logs WHERE liters > 0 $fuelRange
        ");
        $stmt->execute($params);
        $logStats = $stmt->fetch() ?: ['liters' => 0, 'refills' => 0];
        $totalFuelLiters = (float) ($logStats['liters'] ?? 0);
        $fuelRefills = (int) ($logStats['refills'] ?? 0);

        if ($hasRefuels) {
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(liters),0) AS liters, COUNT(*) AS refills
                FROM fuel_refuels WHERE 1=1 $fuelRange
            ");
            $stmt->execute($params);
            $refuelStats = $stmt->fetch() ?: ['liters' => 0, 'refills' => 0];
            $totalFuelLiters += (float) ($refuelStats['liters'] ?? 0);
            $fuelRefills += (int) ($refuelStats['refills'] ?? 0);
        }

        /* Per-bus fuel cost and refill stats, merged across the sources. */
        $fuelPerBusCost = [];
        $fuelPerBusStats = [];

        $collectFuelCost = function (string $table, string $column, string $where = '') use ($db, $fuelRange, $params, &$fuelPerBusCost): void {
            $stmt = $db->prepare("
                SELECT bus_id, COALESCE(SUM(`$column`),0) AS total
                FROM `$table` WHERE 1=1 $where $fuelRange
                GROUP BY bus_id
            ");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $busId = (string) $row['bus_id'];
                $fuelPerBusCost[$busId] = ($fuelPerBusCost[$busId] ?? 0.0) + (float) $row['total'];
            }
        };

        $collectFuelStats = function (string $table, string $costColumn, string $where = '') use ($db, $fuelRange, $params, &$fuelPerBusStats): void {
            $stmt = $db->prepare("
                SELECT bus_id, COALESCE(SUM(liters),0) AS liters, COUNT(*) AS refills, COALESCE(SUM(`$costColumn`),0) AS log_cost
                FROM `$table` WHERE 1=1 $where $fuelRange
                GROUP BY bus_id
            ");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $busId = (string) $row['bus_id'];
                if (!isset($fuelPerBusStats[$busId])) {
                    $fuelPerBusStats[$busId] = ['liters' => 0.0, 'refills' => 0, 'log_cost' => 0.0];
                }
                $fuelPerBusStats[$busId]['liters'] += (float) $row['liters'];
                $fuelPerBusStats[$busId]['refills'] += (int) $row['refills'];
                $fuelPerBusStats[$busId]['log_cost'] += (float) $row['log_cost'];
            }
        };

        $collectFuelCost('expenses', 'amount', "AND category = 'fuel'");
        $collectFuelCost('fuel_logs', 'cost');
        $collectFuelStats('fuel_logs', 'cost', 'AND liters > 0');
        if ($hasRefuels) {
            $collectFuelCost('fuel_refuels', 'total_cost');
            $collectFuelStats('fuel_refuels', 'total_cost');
        }

        arsort($fuelPerBusCost);
        $fuelPerBus = [];
        foreach ($fuelPerBusCost as $busId => $cost) {
            $st = $fuelPerBusStats[$busId] ?? null;
            $fuelPerBus[] = [
                'bus_id'     => (string) $busId,
                'cost'       => (float) $cost,
                'liters'     => $st ? (float) $st['liters'] : 0.0,
                'refills'    => $st ? (int) $st['refills'] : 0,
                'avg_price'  => ($st && (float) $st['liters'] > 0)
                    ? (float) $st['log_cost'] / (float) $st['liters']
                    : null,
            ];
        }
        $fuelBusCount = count(array_filter($fuelPerBus, fn ($b) => $b['cost'] > 0));
        $avgFuelCostPerBus = $fuelBusCount > 0 ? $totalFuelAllTime / $fuelBusCount : 0.0;

        /* daily 30-day fuel trend (expenses + fuel_logs + fuel_refuels merged in PHP) */
        $fuelTrendMap = [];
        $fuelTrendSources = [
            "SELECT DATE(created_at) AS day, COALESCE(SUM(amount),0) AS total FROM expenses
             WHERE category = 'fuel' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY day",
            "SELECT DATE(created_at) AS day, COALESCE(SUM(cost),0) AS total FROM fuel_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY day",
        ];
        if ($hasRefuels) {
            $fuelTrendSources[] = "SELECT DATE(created_at) AS day, COALESCE(SUM(total_cost),0) AS total FROM fuel_refuels
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY day";
        }
        foreach ($fuelTrendSources as $trendSql) {
            foreach ($db->query($trendSql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $day = (string) $row['day'];
                $fuelTrendMap[$day] = ($fuelTrendMap[$day] ?? 0.0) + (float) $row['total'];
            }
        }
        ksort($fuelTrendMap);
        $fuelTrend = [];
        foreach ($fuelTrendMap as $day => $total) {
            $fuelTrend[] = ['day' => $day, 'total' => (float) $total];
        }

        /* ---------------- passenger-based income (paid passenger trips) ---------------- */
        $incomeParams = [];
        $liveRange = '';
        $histRange = '';
        if ($start) { $liveRange .= ' AND DATE(boarding_time) >= :start'; $incomeParams[':start'] = $start; }
        if ($end)   { $liveRange .= ' AND DATE(boarding_time) <= :end';   $incomeParams[':end'] = $end; }

        $histParams = $incomeParams;
        if ($start) { $histRange .= ' AND DATE(trip_date) >= :start'; }
        if ($end)   { $histRange .= ' AND DATE(trip_date) <= :end'; }

        $stmt = $db->prepare("
            SELECT COALESCE(SUM(fare),0) FROM passenger_trips
            WHERE LOWER(TRIM(payment_status)) = 'paid' $liveRange
        ");
        $stmt->execute($incomeParams);
        $incomeLive = (float) $stmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT COALESCE(SUM(fare),0) FROM passenger_trip_history
            WHERE 1=1 $histRange
        ");
        $stmt->execute($histParams);
        $incomeHistory = (float) $stmt->fetchColumn();

        $grossIncome = $incomeLive + $incomeHistory;

        /* ---------------- expenses (expenses table + fuel_logs) ---------------- */
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amount),0) FROM expenses WHERE 1=1 $fuelRange
        ");
        $stmt->execute($params);
        $expensesFromTable = (float) $stmt->fetchColumn();

        $totalExpenses = $expensesFromTable + $totalFuelAllTime;
        $netProfit = $grossIncome - $totalExpenses;

        /* ---------------- operations (bus trips) ---------------- */
        $btRange = '';
        if ($start) { $btRange .= ' AND DATE(trip_date) >= :start'; }
        if ($end)   { $btRange .= ' AND DATE(trip_date) <= :end'; }

        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT bus_id) AS buses, COUNT(*) AS trips
            FROM bus_trips WHERE 1=1 $btRange
        ");
        $busTripParams = [];
        if ($start) { $busTripParams[':start'] = $start; }
        if ($end)   { $busTripParams[':end'] = $end; }
        $stmt->execute($busTripParams);
        $ops = $stmt->fetch();
        $activeBuses = (int) ($ops['buses'] ?? 0);
        $totalRoundTrips = (int) ($ops['trips'] ?? 0);
        if ($activeBuses === 0) {
            $activeBuses = (int) ($db->query("SELECT COUNT(*) FROM buses")->fetchColumn() ?? 0);
        }

        /* ---------------- 7-day income vs expense chart ---------------- */
        $cut = date('Y-m-d', strtotime('-6 days'));
        $chart = $this->buildChartData($cut);

        $avgCostPerTrip = $totalRoundTrips > 0 ? $totalFuelAllTime / $totalRoundTrips : 0.0;

        return [
            'net_profit'          => round($netProfit, 2),
            'gross_income'        => round($grossIncome, 2),
            'total_expenses'      => round($totalExpenses, 2),
            'active_buses'        => $activeBuses,
            'total_round_trips'   => $totalRoundTrips,
            'chart'               => $chart,
            'total_fuel_cost'     => round($totalFuelAllTime, 2),
            'total_fuel_liters'   => round($totalFuelLiters, 2),
            'avg_fuel_cost_per_bus' => round($avgFuelCostPerBus, 2),
            'avg_cost_per_trip'   => round($avgCostPerTrip, 2),
            'fuel_refills'        => $fuelRefills,
            'fuel_per_bus'        => $fuelPerBus,
            'fuel_trend'          => $fuelTrend,
        ];
    }

    private function buildChartData(string $cut): array
    {
        $db = Database::get();

        $income = [];
        $stmt = $db->prepare("
            SELECT DATE(boarding_time) d, SUM(fare) t FROM passenger_trips
            WHERE LOWER(TRIM(payment_status)) = 'paid' AND DATE(boarding_time) >= :cut
            GROUP BY d
        ");
        $stmt->execute([':cut' => $cut]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $income[$row['d']] = ($income[$row['d']] ?? 0) + (float) $row['t'];
        }
        $stmt = $db->prepare("
            SELECT DATE(trip_date) d, SUM(fare) t FROM passenger_trip_history
            WHERE DATE(trip_date) >= :cut
            GROUP BY d
        ");
        $stmt->execute([':cut' => $cut]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $income[$row['d']] = ($income[$row['d']] ?? 0) + (float) $row['t'];
        }

        $expenses = [];
        $stmt = $db->prepare("
            SELECT DATE(created_at) d, amount t FROM expenses
            WHERE DATE(created_at) >= :cut
        ");
        $stmt->execute([':cut' => $cut]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $expenses[$row['d']] = ($expenses[$row['d']] ?? 0) + (float) $row['t'];
        }
        $stmt = $db->prepare("
            SELECT DATE(created_at) d, cost t FROM fuel_logs
            WHERE DATE(created_at) >= :cut
        ");
        $stmt->execute([':cut' => $cut]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $expenses[$row['d']] = ($expenses[$row['d']] ?? 0) + (float) $row['t'];
        }

        if ($this->hasFuelRefuels($db)) {
            $stmt = $db->prepare("
                SELECT DATE(created_at) d, total_cost t FROM fuel_refuels
                WHERE DATE(created_at) >= :cut
            ");
            $stmt->execute([':cut' => $cut]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $expenses[$row['d']] = ($expenses[$row['d']] ?? 0) + (float) $row['t'];
            }
        }

        $labels = [];
        $incomeArr = [];
        $expenseArr = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('D, M j', strtotime($d));
            $incomeArr[] = round($income[$d] ?? 0, 2);
            $expenseArr[] = round($expenses[$d] ?? 0, 2);
        }

        return [
            'labels'   => $labels,
            'income'   => $incomeArr,
            'expenses' => $expenseArr,
        ];
    }
}
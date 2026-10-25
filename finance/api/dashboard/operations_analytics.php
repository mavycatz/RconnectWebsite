<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/finance/middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';

use App\Core\Database;

validateFinanceRequest('standard', false);
setRequestTimeout(30);

$db = Database::get();

/* -----------------------------------------
   PERIOD RESOLVERS
----------------------------------------- */

function resolveCurrentRange(string $period): array
{
    $today = date('Y-m-d');

    switch ($period) {
        case 'last12':
            return [
                'from'  => date('Y-m-d', strtotime('-12 months')),
                'to'    => $today,
                'label' => 'Last 12 Months'
            ];
        case 'month':
            return [
                'from'  => date('Y-m-01'),
                'to'    => date('Y-m-t'),
                'label' => 'Month ('.date('M Y').')'
            ];
        case 'ytd':
            return [
                'from'  => date('Y').'-01-01',
                'to'    => date('Y-m-d'),
                'label' => 'Year to Date'
            ];
        case 'year':
            return [
                'from'  => date('Y').'-01-01',
                'to'    => date('Y').'-12-31',
                'label' => 'Year ('.date('Y').')'
            ];
        case 'custom':
            $from = $_GET['from'] ?? '';
            $to   = $_GET['to'] ?? '';
            if (!$from || !$to || !strtotime($from) || !strtotime($to) || $from > $to) {
                $from = date('Y-m-01');
                $to   = date('Y-m-t');
            }
            return [
                'from'  => $from,
                'to'    => $to,
                'label' => 'Custom Range'
            ];
        case 'last6':
        default:
            return [
                'from'  => date('Y-m-d', strtotime('-6 months')),
                'to'    => $today,
                'label' => 'Last 6 Months'
            ];
    }
}

function resolvePreviousRange(string $against, array $current): array
{
    $from = $current['from'];
    $to   = $current['to'];

    switch ($against) {
        case 'prev6':
            return [
                'from'  => date('Y-m-d', strtotime($from.' -6 months')),
                'to'    => date('Y-m-d', strtotime($from.' -1 day')),
                'label' => 'Previous 6 Months'
            ];
        case 'prev12':
            return [
                'from'  => date('Y-m-d', strtotime($from.' -12 months')),
                'to'    => date('Y-m-d', strtotime($from.' -1 day')),
                'label' => 'Previous 12 Months'
            ];
        case 'prev_month':
            return [
                'from'  => date('Y-m-01', strtotime($from.' -1 month')),
                'to'    => date('Y-m-t',  strtotime($from.' -1 month')),
                'label' => 'Previous Month'
            ];
        case 'yoy_month':
            return [
                'from'  => date('Y-m-d', strtotime($from.' -1 year')),
                'to'    => date('Y-m-d', strtotime($to.' -1 year')),
                'label' => 'Same Month Last Year'
            ];
        case 'yoy_12':
            return [
                'from'  => date('Y-m-d', strtotime($from.' -1 year')),
                'to'    => date('Y-m-d', strtotime($to.' -1 year')),
                'label' => 'Same 12 Months Last Year'
            ];
        case 'yoy_6':
            return [
                'from'  => date('Y-m-d', strtotime($from.' -1 year')),
                'to'    => date('Y-m-d', strtotime($to.' -1 year')),
                'label' => 'Same 6 Months Last Year'
            ];
        case 'prev_year':
            return [
                'from'  => date('Y-01-01', strtotime($from.' -1 year')),
                'to'    => date('Y-12-31', strtotime($from.' -1 year')),
                'label' => 'Previous Year'
            ];
        case 'custom':
            $from2 = $_GET['from2'] ?? '';
            $to2   = $_GET['to2'] ?? '';
            if (!$from2 || !$to2 || !strtotime($from2) || !strtotime($to2) || $from2 > $to2) {
                $from2 = date('Y-m-d', strtotime($from.' -1 year'));
                $to2   = date('Y-m-d', strtotime($to.' -1 year'));
            }
            return [
                'from'  => $from2,
                'to'    => $to2,
                'label' => 'Custom Range'
            ];
        default:
            return [
                'from'  => date('Y-m-d', strtotime($from.' -6 months')),
                'to'    => date('Y-m-d', strtotime($from.' -1 day')),
                'label' => 'Previous 6 Months'
            ];
    }
}

/* -----------------------------------------
   RANGE SQL
----------------------------------------- */

function buildRangeSQL(string $alias, array $range, array &$params, string $dateCol = 'boarding_time'): string
{
    $fromKey = ':r_from'.($alias === 'bt' ? 'b' : ($alias === 'pt' ? 'p' : 'd'));
    $toKey   = ':r_to'.($alias === 'bt' ? 'b' : ($alias === 'pt' ? 'p' : 'd'));

    $params[$fromKey] = $range['from'];
    $params[$toKey]   = $range['to'];

    return "($alias.$dateCol >= $fromKey AND $alias.$dateCol < DATE_ADD($toKey, INTERVAL 1 DAY))";
}

function buildHistoryRangeSQL(array $range, array &$params): string
{
    $params[':r_fromh'] = $range['from'];
    $params[':r_toh']   = $range['to'];

    return "(COALESCE(pth.trip_date, DATE(pth.date_booked)) >= :r_fromh
            AND COALESCE(pth.trip_date, DATE(pth.date_booked)) < DATE_ADD(:r_toh, INTERVAL 1 DAY))";
}

/* -----------------------------------------
   COMPARISON METRICS
----------------------------------------- */

function fetchComparison(string $alias, array $range): array
{
    $db = Database::get();
    $params = [];
    $rangeSQL = buildRangeSQL($alias, $range, $params);
    $histParams = [];
    $histRangeSQL = buildHistoryRangeSQL($range, $histParams);

    $sql = "
    SELECT
        COUNT(*) AS passengers,
        COUNT(DISTINCT trip_key) AS trips,
        COALESCE(SUM(revenue_amt), 0) AS revenue,
        COALESCE(SUM(discrepancy_flag), 0) AS discrepancies
    FROM (
        SELECT CONCAT('L', pt.id) AS trip_key,
               CASE WHEN LOWER(TRIM(pt.payment_status)) = 'paid' THEN pt.fare ELSE 0 END AS revenue_amt,
               CASE WHEN pt.discrepancy_amount > 0 THEN 1 ELSE 0 END AS discrepancy_flag
        FROM passenger_trips pt
        WHERE $rangeSQL
        UNION ALL
        SELECT CONCAT('H', COALESCE(NULLIF(TRIM(pth.trip_id), ''), pth.history_id)) AS trip_key,
               pth.fare AS revenue_amt,
               0 AS discrepancy_flag
        FROM passenger_trip_history pth
        WHERE $histRangeSQL
    ) combined
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($params, $histParams));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'revenue'      => (float)($row['revenue'] ?? 0),
        'passengers'   => (int)($row['passengers'] ?? 0),
        'trips'        => (int)($row['trips'] ?? 0),
        'discrepancies'=> (int)($row['discrepancies'] ?? 0)
    ];
}

function percentChange($current, $previous)
{
    $prev = (float)$previous;
    if ($prev == 0) {
        return null;
    }
    return round((((float)$current - $prev) / $prev) * 100, 1);
}

/* -----------------------------------------
   MONTHLY TREND
----------------------------------------- */

function buildMonthList(string $from, string $to): array
{
    $months = [];
    $start = new DateTime(date('Y-m-01', strtotime($from)));
    $end   = new DateTime(date('Y-m-01', strtotime($to)));
    $cursor = clone $start;
    while ($cursor <= $end) {
        $months[] = [
            'ym'    => $cursor->format('Y-m'),
            'label' => $cursor->format('M Y')
        ];
        $cursor->modify('+1 month');
    }
    return $months;
}

function fetchTrend(string $alias, array $range, array $monthKeys): array
{
    $db = Database::get();
    $params = [];
    $rangeSQL = buildRangeSQL($alias, $range, $params);
    $histParams = [];
    $histRangeSQL = buildHistoryRangeSQL($range, $histParams);

    $sql = "
    SELECT ym, SUM(revenue) AS revenue, SUM(passengers) AS passengers, SUM(trips) AS trips, SUM(discrepancies) AS discrepancies
    FROM (
        SELECT
            DATE_FORMAT(pt.boarding_time, '%Y-%m') AS ym,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pt.payment_status)) = 'paid' THEN pt.fare ELSE 0 END), 0) AS revenue,
            COUNT(*) AS passengers,
            COUNT(DISTINCT CONCAT('L', pt.id)) AS trips,
            COALESCE(SUM(CASE WHEN pt.discrepancy_amount > 0 THEN 1 ELSE 0 END), 0) AS discrepancies
        FROM passenger_trips pt
        WHERE $rangeSQL
        GROUP BY ym
        UNION ALL
        SELECT
            DATE_FORMAT(COALESCE(pth.trip_date, DATE(pth.date_booked)), '%Y-%m') AS ym,
            COALESCE(SUM(pth.fare), 0) AS revenue,
            COUNT(*) AS passengers,
            COUNT(DISTINCT CONCAT('H', COALESCE(NULLIF(TRIM(pth.trip_id), ''), pth.history_id))) AS trips,
            0 AS discrepancies
        FROM passenger_trip_history pth
        WHERE $histRangeSQL
        GROUP BY ym
    ) combined
    GROUP BY ym
    ORDER BY ym
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($params, $histParams));

    $byMonth = [];
    foreach ($stmt as $row) {
        $byMonth[$row['ym']] = [
            'revenue'      => (float)$row['revenue'],
            'passengers'   => (int)$row['passengers'],
            'trips'        => (int)$row['trips'],
            'discrepancies'=> (int)$row['discrepancies']
        ];
    }

    $labels = [];
    $revenue = [];
    $passengers = [];
    $trips = [];
    $discrepancies = [];
    foreach ($monthKeys as $m) {
        $labels[] = $m['label'];
        $mm = $byMonth[$m['ym']] ?? null;
        $revenue[]       = $mm ? $mm['revenue'] : 0;
        $passengers[]    = $mm ? $mm['passengers'] : 0;
        $trips[]         = $mm ? $mm['trips'] : 0;
        $discrepancies[] = $mm ? $mm['discrepancies'] : 0;
    }

    return compact('labels', 'revenue', 'passengers', 'trips', 'discrepancies');
}

/* -----------------------------------------
   OPERATIONAL METRICS
----------------------------------------- */

function fetchOperational(array $range): array
{
    $db = Database::get();

    $comparison = fetchComparison('pt', $range);

    $params = [];
    $rangeSQL = buildRangeSQL('bt', $range, $params, 'trip_date');

    $sqlTrips = "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN bt.status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed
    FROM bus_trips bt
    WHERE $rangeSQL
    ";
    $stmt = $db->prepare($sqlTrips);
    $stmt->execute($params);
    $busTrips = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalBusTrips = (int)($busTrips['total'] ?? 0);
    $completedBusTrips = (int)($busTrips['completed'] ?? 0);

    $stmt = $db->prepare("SELECT COUNT(*) FROM buses WHERE LOWER(TRIM(status)) = 'active'");
    $stmt->execute();
    $activeBuses = (int)$stmt->fetchColumn();

    $metrics = [];

    if ($comparison['trips'] > 0) {
        $metrics['avg_passengers_per_trip'] = round($comparison['passengers'] / $comparison['trips'], 1);
        $metrics['avg_revenue_per_trip']    = round($comparison['revenue'] / $comparison['trips'], 2);
        $metrics['discrepancy_rate']        = round(($comparison['discrepancies'] / $comparison['trips']) * 100, 1);
    }

    if ($totalBusTrips > 0) {
        $metrics['completed_trip_rate'] = round(($completedBusTrips / $totalBusTrips) * 100, 1);
    }

    if ($activeBuses > 0) {
        $metrics['bus_utilization'] = round($comparison['passengers'] / $activeBuses, 1);
    }

    return $metrics;
}

/* -----------------------------------------
   DEMAND ANALYSIS
----------------------------------------- */

function fetchDemand(array $range, array $monthKeys): array
{
    $db = Database::get();
    $params = [];
    $rangeSQL = buildRangeSQL('pt', $range, $params);
    $histParams = [];
    $histRangeSQL = buildHistoryRangeSQL($range, $histParams);

    $byMonth = [];
    $total = 0;

    $stmt = $db->prepare("
        SELECT DATE_FORMAT(pt.boarding_time, '%Y-%m') AS ym, COUNT(*) AS c
        FROM passenger_trips pt
        WHERE $rangeSQL
        GROUP BY ym
    ");
    $stmt->execute($params);
    foreach ($stmt as $row) {
        $byMonth[$row['ym']] = (int)$row['c'];
        $total += (int)$row['c'];
    }

    $stmt = $db->prepare("
        SELECT DATE_FORMAT(COALESCE(pth.trip_date, DATE(pth.date_booked)), '%Y-%m') AS ym, COUNT(*) AS c
        FROM passenger_trip_history pth
        WHERE $histRangeSQL
        GROUP BY ym
    ");
    $stmt->execute($histParams);
    foreach ($stmt as $row) {
        $byMonth[$row['ym']] = (int)$row['c'] + ($byMonth[$row['ym']] ?? 0);
        $total += (int)$row['c'];
    }

    $labels = [];
    $counts = [];
    $highIndex = null;
    $lowIndex = null;
    foreach ($monthKeys as $i => $m) {
        $labels[] = $m['label'];
        $counts[] = $byMonth[$m['ym']] ?? 0;
        if ($counts[$i] > 0) {
            if ($highIndex === null || $counts[$i] > $counts[$highIndex]) {
                $highIndex = $i;
            }
        }
        if ($lowIndex === null || $counts[$i] < $counts[$lowIndex]) {
            $lowIndex = $i;
        }
    }

    $days = 0;
    if ($range['from'] && $range['to']) {
        $days = max(1, (int)((new DateTime($range['to']))->diff(new DateTime($range['from'])))->days + 1);
    }

    $sqlPeaks = "
    SELECT
        COALESCE(SUM(CASE WHEN HOUR(x.t) >= 6  AND HOUR(x.t) < 10 THEN 1 ELSE 0 END), 0) AS morning,
        COALESCE(SUM(CASE WHEN HOUR(x.t) >= 10 AND HOUR(x.t) < 16 THEN 1 ELSE 0 END), 0) AS afternoon,
        COALESCE(SUM(CASE WHEN HOUR(x.t) >= 16 THEN 1 ELSE 0 END), 0) AS evening,
        COALESCE(SUM(CASE WHEN HOUR(x.t) >= 0  AND HOUR(x.t) < 6  THEN 1 ELSE 0 END), 0) AS predawn,
        COUNT(CASE WHEN x.t IS NOT NULL THEN 1 END) AS time_total
    FROM (
        SELECT pt.boarding_time AS t
        FROM passenger_trips pt
        WHERE $rangeSQL
        UNION ALL
        SELECT pth.time_of_departure AS t
        FROM passenger_trip_history pth
        WHERE $histRangeSQL
    ) x
    ";
    $stmt = $db->prepare($sqlPeaks);
    $stmt->execute(array_merge($params, $histParams));
    $peaks = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'labels'      => $labels,
        'counts'      => $counts,
        'highest'     => $highIndex,
        'lowest'      => $lowIndex,
        'total'       => $total,
        'time_total'  => (int)($peaks['time_total'] ?? 0),
        'avg_per_day' => (int)round($total / $days),
        'predawn'     => (int)($peaks['predawn'] ?? 0),
        'morning'     => (int)($peaks['morning'] ?? 0),
        'afternoon'   => (int)($peaks['afternoon'] ?? 0),
        'evening'     => (int)($peaks['evening'] ?? 0)
    ];
}

/* -----------------------------------------
   OUTPUT
----------------------------------------- */

try {

    $period  = $_GET['period']  ?? 'last6';
    $against = $_GET['against'] ?? 'prev6';

    $current = resolveCurrentRange($period);
    $previous = resolvePreviousRange($against, $current);

    $monthKeys = buildMonthList($current['from'], $current['to']);
    $prevMonthKeys = buildMonthList($previous['from'], $previous['to']);

    $cur = fetchComparison('pt', $current);
    $prev = fetchComparison('pt', $previous);

    $comparison = [];
    $metricNames = ['revenue' => 'Revenue', 'passengers' => 'Passengers', 'trips' => 'Trips', 'discrepancies' => 'Discrepancies'];
    foreach ($metricNames as $key => $label) {
        $delta = $cur[$key] - $prev[$key];
        $comparison[$key] = [
            'label'      => $label,
            'current'    => $cur[$key],
            'previous'   => $prev[$key],
            'difference' => $delta,
            'percent'    => percentChange($cur[$key], $prev[$key])
        ];
    }

    $trend = fetchTrend('pt', $current, $monthKeys);
    $trendPrev = fetchTrend('pt', $previous, $prevMonthKeys);

    $operationalCur = fetchOperational($current);
    $operationalPrev = fetchOperational($previous);

    $operational = [];
    $opLabels = [
        'avg_passengers_per_trip' => 'Average Passengers / Trip',
        'avg_revenue_per_trip'    => 'Average Revenue / Trip',
        'completed_trip_rate'     => 'Trip Completion Rate',
        'bus_utilization'         => 'Bus Utilization',
        'discrepancy_rate'        => 'Discrepancy Rate'
    ];
    foreach ($opLabels as $key => $label) {
        if (!array_key_exists($key, $operationalCur)) {
            continue;
        }
        $operational[$key] = [
            'label'   => $label,
            'current' => $operationalCur[$key],
            'previous'=> $operationalPrev[$key] ?? null,
            'percent' => (isset($operationalPrev[$key]) && (float)$operationalPrev[$key] != 0)
                ? round((($operationalCur[$key] - $operationalPrev[$key]) / $operationalPrev[$key]) * 100, 1)
                : null
        ];
    }

    $demand = fetchDemand($current, $monthKeys);

    $isEmpty = ($cur['trips'] == 0 && $prev['trips'] == 0);

    echo json_encode([
        'success'         => true,
        'current_range'   => $current,
        'previous_range'  => $previous,
        'trend_label'     => $current['label'],
        'comparison'      => $comparison,
        'trend'           => $trend,
        'trend_previous'  => $trendPrev,
        'operational'     => $operational,
        'demand'          => $demand,
        'empty'           => $isEmpty
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}
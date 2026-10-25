<?php declare(strict_types=1);

/**
 * Builds a complete report payload (metadata, filtered rows, computed KPIs,
 * totals, distributions, trends, comparison, findings, optional AI narrative)
 * from the report-type registry and the filtered model query.
 *
 * All displayed figures are computed from the filtered database records; the
 * AI narrative is optional and grounded in those figures.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/reports/report_types.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/GenerateReportModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/Driver/driver-perf-metrics-core.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/Driver/driver-ai-core.php';

use Admin\Models\GenerateReportModel;
use App\Core\Database;

function rgen_money($value): string
{
    return '₱' . number_format((float)$value, 2);
}

function rgen_datetime(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '' || str_starts_with($value, '0000-00-00')) {
        return '—';
    }
    date_default_timezone_set('Asia/Manila');
    try {
        $dt = new DateTime($value, new DateTimeZone('Asia/Manila'));
        return $dt->format('M j, Y g:i A');
    } catch (Throwable $e) {
        return $value;
    }
}

function rgen_kpi(string $label, $value, ?string $hint = null, ?string $format = null): array
{
    return ['label' => $label, 'value' => (string)$value, 'hint' => $hint, 'format' => $format];
}

function rgen_sum(array $rows, string $key): float
{
    $total = 0.0;
    foreach ($rows as $row) {
        $total += (float)($row[$key] ?? 0);
    }
    return $total;
}

function rgen_count_where(array $rows, callable $test): int
{
    $count = 0;
    foreach ($rows as $row) {
        if ($test($row)) {
            $count++;
        }
    }
    return $count;
}

/**
 * KPIs computed from the already-filtered rows for a report type.
 */
function rgen_kpis(string $type, array $rows, array $def): array
{
    $total = count($rows);
    $kpis = [];

    switch ($type) {
        case 'drivers':
            $active = rgen_count_where($rows, static fn ($r) => strcasecmp((string)($r['status'] ?? ''), 'Active') === 0);
            $limit = (new DateTime('+90 days', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
            $today = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
            $expiring = rgen_count_where($rows, static function ($r) use ($today, $limit) {
                $exp = trim((string)($r['license_expiry'] ?? ''));
                return $exp !== '' && !str_starts_with($exp, '0000') && $exp >= $today && $exp <= $limit;
            });
            $kpis[] = rgen_kpi('Total Drivers', $total);
            $kpis[] = rgen_kpi('Active Drivers', $active);
            $kpis[] = rgen_kpi('Inactive / Archived', $total - $active);
            $kpis[] = rgen_kpi('Licenses Expiring (90 days)', $expiring);
            break;

        case 'driver_accounts':
            $active = rgen_count_where($rows, static fn ($r) => strcasecmp((string)($r['status'] ?? ''), 'Active') === 0);
            $kpis[] = rgen_kpi('Total Accounts', $total);
            $kpis[] = rgen_kpi('Active Drivers', $active);
            $kpis[] = rgen_kpi('Inactive / Archived', $total - $active);
            break;

        case 'driver_bus_assignments':
            $drivers = [];
            $buses = [];
            foreach ($rows as $row) {
                $drivers[(string)($row['driver_id'] ?? '')] = true;
                $buses[(string)($row['bus_id'] ?? '')] = true;
            }
            $kpis[] = rgen_kpi('Total Assignments', $total);
            $kpis[] = rgen_kpi('Drivers Assigned', count(array_filter(array_keys($drivers), static fn ($k) => $k !== '')));
            $kpis[] = rgen_kpi('Buses Assigned', count(array_filter(array_keys($buses), static fn ($k) => $k !== '')));
            break;

        case 'passengers':
            $withTrips = rgen_count_where($rows, static fn ($r) => (int)($r['total_trip_history'] ?? 0) > 0);
            $kpis[] = rgen_kpi('Total Passengers', $total);
            $kpis[] = rgen_kpi('With Trip History', $withTrips);
            $kpis[] = rgen_kpi('Total Fare Paid', rgen_money(rgen_sum($rows, 'total_fare_paid')), null, 'money');
            break;

        case 'passenger_trips':
            $trips = [];
            $paid = 0.0;
            $pending = 0.0;
            $discrepancy = 0.0;
            foreach ($rows as $row) {
                $trips[(string)($row['trip_no'] ?? '')] = true;
                $fare = (float)($row['fare'] ?? 0);
                $status = strtolower((string)($row['payment_status'] ?? ''));
                if ($status === 'paid') {
                    $paid += $fare;
                } elseif (in_array($status, ['pending', 'ongoing'], true)) {
                    $pending += $fare;
                }
                $discrepancy += (float)($row['discrepancy_amount'] ?? 0);
            }
            $withDiscrepancy = rgen_count_where($rows, static fn ($r) => (float)($r['discrepancy_amount'] ?? 0) > 0);
            $kpis[] = rgen_kpi('Trip Records', $total);
            $kpis[] = rgen_kpi('Distinct Trips', count(array_filter(array_keys($trips), static fn ($k) => $k !== '')));
            $kpis[] = rgen_kpi('Gross Fare', rgen_money(rgen_sum($rows, 'fare')), null, 'money');
            $kpis[] = rgen_kpi('Paid Fare', rgen_money($paid), null, 'money');
            $kpis[] = rgen_kpi('Pending Fare', rgen_money($pending), null, 'money');
            $kpis[] = rgen_kpi('Total Discrepancy', rgen_money($discrepancy), $withDiscrepancy . ' record(s) with discrepancy', 'money');
            break;

        case 'passenger_trip_history':
            $trips = [];
            foreach ($rows as $row) {
                $trips[(string)($row['trip_id'] ?? '')] = true;
            }
            $kpis[] = rgen_kpi('Trip Records', $total);
            $kpis[] = rgen_kpi('Distinct Trips', count(array_filter(array_keys($trips), static fn ($k) => $k !== '')));
            $kpis[] = rgen_kpi('Total Fare', rgen_money(rgen_sum($rows, 'fare')), null, 'money');
            break;

        case 'trip_operations_reports':
            $kpis[] = rgen_kpi('Operations', $total);
            $kpis[] = rgen_kpi('Passengers', (string)(int)rgen_sum($rows, 'passenger_count'));
            $kpis[] = rgen_kpi('Total Revenue', rgen_money(rgen_sum($rows, 'total_revenue')), null, 'money');
            $kpis[] = rgen_kpi('Fuel Cost', rgen_money(rgen_sum($rows, 'fuel_cost')), null, 'money');
            $kpis[] = rgen_kpi('Other Expenses', rgen_money(rgen_sum($rows, 'other_expenses')), null, 'money');
            $net = rgen_sum($rows, 'net_income');
            $kpis[] = rgen_kpi('Net Income', rgen_money($net), $total > 0 ? rgen_money($net / $total) . ' average per operation' : null, 'money');
            break;

        case 'trip_operation_evidences':
            $available = rgen_count_where($rows, static fn ($r) => trim((string)($r['evidence_file'] ?? '')) !== '');
            $kpis[] = rgen_kpi('Evidence Records', $total);
            $kpis[] = rgen_kpi('With Evidence', $available);
            $kpis[] = rgen_kpi('Missing Evidence', $total - $available);
            break;

        case 'ai_trip_operations_reports':
            $categories = [];
            foreach ($rows as $row) {
                $categories[(string)($row['report_category'] ?? '')] = true;
            }
            $kpis[] = rgen_kpi('Reports Stored', $total);
            $kpis[] = rgen_kpi('Categories', count(array_filter(array_keys($categories), static fn ($k) => $k !== '')));
            $latest = $rows[0]['generated_at'] ?? '';
            $kpis[] = rgen_kpi('Latest Generated', rgen_datetime($latest));
            break;

        case 'route_stops':
            $routes = [];
            foreach ($rows as $row) {
                $routes[(string)($row['route_name'] ?? '')] = true;
            }
            $primary = rgen_count_where($rows, static fn ($r) => (int)($r['sequence_order'] ?? 0) <= 3);
            $kpis[] = rgen_kpi('Total Stops', $total);
            $kpis[] = rgen_kpi('Routes Covered', count(array_filter(array_keys($routes), static fn ($k) => $k !== '')));
            $kpis[] = rgen_kpi('Primary Stops', $primary);
            $kpis[] = rgen_kpi('Secondary Stops', $total - $primary);
            break;

        case 'fares':
            $regular = array_map(static fn ($r) => (float)($r['fare_regular'] ?? 0), $rows);
            $pairs = [];
            foreach ($rows as $row) {
                $from = trim((string)($row['route_from'] ?? ''));
                $to = trim((string)($row['route_to'] ?? ''));
                if ($from !== '' || $to !== '') {
                    $pairs[$from . ' → ' . $to] = true;
                }
            }
            $kpis[] = rgen_kpi('Fare Entries', $total);
            $kpis[] = rgen_kpi('Routes Covered', count($pairs));
            if ($regular) {
                $kpis[] = rgen_kpi('Average Regular Fare', rgen_money(array_sum($regular) / count($regular)), null, 'money');
                $kpis[] = rgen_kpi('Lowest Regular Fare', rgen_money(min($regular)), null, 'money');
                $kpis[] = rgen_kpi('Highest Regular Fare', rgen_money(max($regular)), null, 'money');
            }
            break;

        case 'remittance_logs':
            $discrepancy = rgen_sum($rows, 'discrepancy_amount');
            $withDiscrepancy = rgen_count_where($rows, static fn ($r) => (float)($r['discrepancy_amount'] ?? 0) > 0);
            $kpis[] = rgen_kpi('Remittance Logs', $total);
            $kpis[] = rgen_kpi('Total Collection', rgen_money(rgen_sum($rows, 'total_collection')), null, 'money');
            $kpis[] = rgen_kpi('Expected Cash', rgen_money(rgen_sum($rows, 'expected_cash')), null, 'money');
            $kpis[] = rgen_kpi('Actual Cash', rgen_money(rgen_sum($rows, 'actual_cash')), null, 'money');
            $kpis[] = rgen_kpi('Total Discrepancy', rgen_money($discrepancy), $withDiscrepancy . ' log(s) with discrepancy', 'money');
            break;

        case 'inspectors':
            $active = rgen_count_where($rows, static fn ($r) => strcasecmp((string)($r['status'] ?? ''), 'Active') === 0);
            $kpis[] = rgen_kpi('Total Inspectors', $total);
            $kpis[] = rgen_kpi('Active', $active);
            $kpis[] = rgen_kpi('Inactive / Archived', $total - $active);
            break;

        default:
            $kpis[] = rgen_kpi('Total Records', $total);
    }

    return $kpis;
}

/**
 * Status-like distributions computed from the filtered rows.
 */
function rgen_distributions(string $type, array $rows): array
{
    $groups = [
        'drivers' => [['Status', 'status']],
        'driver_accounts' => [['Status', 'status']],
        'driver_bus_assignments' => [['Driver Status', 'status']],
        'passengers' => [['Discount Status', 'discount_status']],
        'passenger_trips' => [['Payment Status', 'payment_status'], ['Remittance Status', 'remittance_status']],
        'passenger_trip_history' => [['Passenger Type', 'passenger_type']],
        'trip_operations_reports' => [['Operation Status', 'operation_status']],
        'trip_operation_evidences' => [['Evidence Status', 'evidence_status']],
        'remittance_logs' => [['Status', 'status']],
        'inspectors' => [['Status', 'status']],
        'route_stops' => [['Stop Category', 'stop_category']],
        'ai_trip_operations_reports' => [['Report Category', 'report_category']],
    ][$type] ?? [];

    $out = [];
    foreach ($groups as [$label, $key]) {
        $counts = [];
        foreach ($rows as $row) {
            $value = trim((string)($row[$key] ?? ''));
            if ($value === '') {
                $value = 'Not recorded';
            }
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);
        if ($counts) {
            $out[] = ['label' => $label, 'counts' => $counts];
        }
    }
    return $out;
}

function rgen_primary_measure(string $type): ?array
{
    return [
        'passenger_trips' => ['key' => 'fare', 'label' => 'Gross Fare'],
        'passenger_trip_history' => ['key' => 'fare', 'label' => 'Total Fare'],
        'trip_operations_reports' => ['key' => 'net_income', 'label' => 'Net Income'],
        'remittance_logs' => ['key' => 'total_collection', 'label' => 'Total Collection'],
    ][$type] ?? null;
}

/**
 * Day buckets from the filtered rows for the type's date key.
 */
function rgen_trends(array $def, string $type, array $rows): array
{
    $dateKey = $def['date_key'] ?? null;
    if (!$dateKey) {
        return [];
    }
    $measure = rgen_primary_measure($type);
    $points = [];
    foreach ($rows as $row) {
        $day = substr((string)($row[$dateKey] ?? ''), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            continue;
        }
        $points[$day] = $points[$day] ?? ['count' => 0, 'sum' => 0.0];
        $points[$day]['count']++;
        if ($measure) {
            $points[$day]['sum'] += (float)($row[$measure['key']] ?? 0);
        }
    }
    ksort($points);
    if (!$points) {
        return [];
    }
    return [[
        'label' => $measure ? ('Records and ' . $measure['label'] . ' per day') : 'Records per day',
        'points' => $points,
        'measure_label' => $measure['label'] ?? null,
    ]];
}

/**
 * Previous equal-length period immediately before the selected range.
 */
function rgen_previous_period(array $period): ?array
{
    $startDate = (string)($period['start_date'] ?? '');
    $endDate = (string)($period['end_date'] ?? '');
    if ($startDate === '' || $endDate === '') {
        return null;
    }
    date_default_timezone_set('Asia/Manila');
    try {
        $start = new DateTime($startDate, new DateTimeZone('Asia/Manila'));
        $end = new DateTime($endDate, new DateTimeZone('Asia/Manila'));
    } catch (Throwable $e) {
        return null;
    }
    $days = (int)$start->diff($end)->days + 1;
    $prevEnd = (clone $start)->modify('-1 day');
    $prevStart = (clone $prevEnd)->modify('-' . ($days - 1) . ' days');
    return [
        'mode' => 'custom',
        'start' => $prevStart->format('Y-m-d') . ' 00:00:00',
        'end' => $prevEnd->format('Y-m-d') . ' 23:59:59',
        'start_date' => $prevStart->format('Y-m-d'),
        'end_date' => $prevEnd->format('Y-m-d'),
        'label' => $prevStart->format('M j, Y') . ' to ' . $prevEnd->format('M j, Y'),
    ];
}

function rgen_comparison(string $type, array $filters, array $period, array $currentRows, GenerateReportModel $model): ?array
{
    $prev = rgen_previous_period($period);
    if ($prev === null) {
        return null;
    }
    $prevFilters = $filters;
    $prevFilters['period'] = $prev;

    try {
        $prevRows = $model->generate($type, $prevFilters)['records'];
    } catch (Throwable $e) {
        return null;
    }

    $currentCount = count($currentRows);
    $previousCount = count($prevRows);
    $delta = static function (float $current, float $previous): ?float {
        if ($previous == 0.0) {
            return null;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    };

    $entries = [[
        'label' => 'Records',
        'current' => (string)$currentCount,
        'previous' => (string)$previousCount,
        'delta_pct' => $delta((float)$currentCount, (float)$previousCount),
        'format' => 'int',
    ]];

    $measure = rgen_primary_measure($type);
    if ($measure) {
        $currentSum = rgen_sum($currentRows, $measure['key']);
        $previousSum = rgen_sum($prevRows, $measure['key']);
        $entries[] = [
            'label' => $measure['label'],
            'current' => rgen_money($currentSum),
            'previous' => rgen_money($previousSum),
            'delta_pct' => $delta($currentSum, $previousSum),
            'format' => 'money',
        ];
    }

    return ['label' => 'Compared with ' . $prev['label'], 'entries' => $entries];
}

/**
 * Rule-based findings that name the records behind them.
 */
function rgen_findings(string $type, array $rows, array $kpis): array
{
    if (!$rows) {
        return ['No records matched the applied filters.'];
    }

    $findings = [];
    $peak = static function (array $counts): ?array {
        if (!$counts) {
            return null;
        }
        arsort($counts);
        $key = array_key_first($counts);
        return ['key' => (string)$key, 'count' => $counts[$key]];
    };

    switch ($type) {
        case 'drivers': {
            $active = rgen_count_where($rows, static fn ($r) => strcasecmp((string)($r['status'] ?? ''), 'Active') === 0);
            if (count($rows) - $active > 0) {
                $findings[] = (count($rows) - $active) . ' driver account(s) are not marked Active.';
            }
            $limit = (new DateTime('+90 days', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
            $today = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
            $expiring = [];
            foreach ($rows as $row) {
                $exp = trim((string)($row['license_expiry'] ?? ''));
                if ($exp !== '' && !str_starts_with($exp, '0000') && $exp >= $today && $exp <= $limit) {
                    $expiring[] = (string)($row['full_name'] ?? 'Driver') . ' (' . $exp . ')';
                }
            }
            if ($expiring) {
                $findings[] = 'License(s) expiring within 90 days: ' . implode(', ', array_slice($expiring, 0, 6)) . (count($expiring) > 6 ? ' and ' . (count($expiring) - 6) . ' more' : '') . '.';
            }
            break;
        }

        case 'passenger_trips': {
            $discrepancyRows = rgen_count_where($rows, static fn ($r) => (float)($r['discrepancy_amount'] ?? 0) > 0);
            if ($discrepancyRows > 0) {
                $findings[] = $discrepancyRows . ' trip record(s) carry a remittance discrepancy totaling ' . rgen_money(rgen_sum($rows, 'discrepancy_amount')) . '.';
            }
            $routes = [];
            foreach ($rows as $row) {
                $route = trim((string)($row['boarding_point'] ?? '')) . ' → ' . trim((string)($row['destination'] ?? ''));
                if (trim($route, " →") !== '') {
                    $routes[$route] = ($routes[$route] ?? 0) + 1;
                }
            }
            $top = $peak($routes);
            if ($top) {
                $findings[] = 'Most used route: ' . $top['key'] . ' with ' . $top['count'] . ' record(s).';
            }
            $highest = null;
            foreach ($rows as $row) {
                if ($highest === null || (float)$row['fare'] > (float)$highest['fare']) {
                    $highest = $row;
                }
            }
            if ($highest) {
                $findings[] = 'Highest fare in range: ' . rgen_money($highest['fare']) . ' — ' . (trim((string)($highest['full_name'] ?? '')) ?: 'Passenger') . ' on ' . rgen_datetime((string)($highest['boarding_time'] ?? '')) . '.';
            }
            break;
        }

        case 'passenger_trip_history': {
            $days = [];
            foreach ($rows as $row) {
                $day = substr((string)($row['trip_date'] ?? ''), 0, 10);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                    $days[$day] = ($days[$day] ?? 0) + 1;
                }
            }
            $top = $peak($days);
            if ($top) {
                $findings[] = 'Peak travel day: ' . $top['key'] . ' with ' . $top['count'] . ' record(s).';
            }
            $routes = [];
            foreach ($rows as $row) {
                $route = trim((string)($row['boarding_point'] ?? '')) . ' → ' . trim((string)($row['destination'] ?? ''));
                if (trim($route, " →") !== '') {
                    $routes[$route] = ($routes[$route] ?? 0) + 1;
                }
            }
            $top = $peak($routes);
            if ($top) {
                $findings[] = 'Most used route: ' . $top['key'] . ' with ' . $top['count'] . ' record(s).';
            }
            break;
        }

        case 'trip_operations_reports': {
            $low = rgen_count_where($rows, static fn ($r) => (float)($r['net_income'] ?? 0) <= 0);
            if ($low > 0) {
                $findings[] = $low . ' operation(s) recorded no positive net income.';
            }
            $best = null;
            foreach ($rows as $row) {
                if ($best === null || (float)$row['net_income'] > (float)$best['net_income']) {
                    $best = $row;
                }
            }
            if ($best) {
                $findings[] = 'Most profitable operation: ' . (trim((string)($best['trip_group_code'] ?? '')) ?: 'operation') . ' at ' . rgen_money($best['net_income']) . ' net income (' . rgen_datetime((string)($best['trip_date'] ?? '')) . ').';
            }
            break;
        }

        case 'remittance_logs': {
            $discrepancyRows = rgen_count_where($rows, static fn ($r) => (float)($r['discrepancy_amount'] ?? 0) > 0);
            if ($discrepancyRows > 0) {
                $findings[] = $discrepancyRows . ' remittance log(s) show a discrepancy totaling ' . rgen_money(rgen_sum($rows, 'discrepancy_amount')) . '.';
            }
            $best = null;
            foreach ($rows as $row) {
                if ($best === null || (float)$row['total_collection'] > (float)$best['total_collection']) {
                    $best = $row;
                }
            }
            if ($best) {
                $findings[] = 'Highest collection: ' . (trim((string)($best['full_name'] ?? '')) ?: 'Driver') . ' at ' . rgen_money($best['total_collection']) . '.';
            }
            break;
        }

        case 'route_stops': {
            $routes = [];
            foreach ($rows as $row) {
                $route = trim((string)($row['route_name'] ?? ''));
                if ($route !== '') {
                    $routes[$route] = ($routes[$route] ?? 0) + 1;
                }
            }
            $top = $peak($routes);
            if ($top) {
                $findings[] = 'Route with the most stops: ' . $top['key'] . ' (' . $top['count'] . ' stops).';
            }
            break;
        }

        case 'fares': {
            if ($rows) {
                $regular = array_map(static fn ($r) => (float)($r['fare_regular'] ?? 0), $rows);
                $lowest = min($regular);
                $highest = max($regular);
                $findings[] = 'Regular fare range: ' . rgen_money($lowest) . ' to ' . rgen_money($highest) . ' across ' . count(array_unique($regular)) . ' distinct fare value(s).';
            }
            break;
        }

        case 'passengers': {
            $topPayer = null;
            foreach ($rows as $row) {
                if ($topPayer === null || (float)$row['total_fare_paid'] > (float)$topPayer['total_fare_paid']) {
                    $topPayer = $row;
                }
            }
            if ($topPayer && (float)$topPayer['total_fare_paid'] > 0) {
                $findings[] = 'Highest recorded fare paid: ' . (trim((string)($topPayer['full_name'] ?? '')) ?: 'Passenger') . ' at ' . rgen_money($topPayer['total_fare_paid']) . '.';
            }
            break;
        }

        case 'trip_operation_evidences': {
            $missing = rgen_count_where($rows, static fn ($r) => trim((string)($r['evidence_file'] ?? '')) === '');
            if ($missing > 0) {
                $findings[] = $missing . ' evidence record(s) have no uploaded file.';
            }
            break;
        }
    }

    if (!$findings) {
        $findings[] = 'No notable issues were found in the filtered records.';
    }

    return $findings;
}

function rgen_applied_filters(array $filters, string $driverName, string $busName): array
{
    $applied = [];
    if (!empty($filters['period'])) {
        $applied[] = ['label' => 'Period', 'value' => (string)$filters['period']['label']];
    }
    if ($driverName !== '') {
        $applied[] = ['label' => 'Driver', 'value' => $driverName];
    }
    if ($busName !== '') {
        $applied[] = ['label' => 'Bus', 'value' => $busName];
    }
    if (!empty($filters['status'])) {
        $applied[] = ['label' => 'Status', 'value' => (string)$filters['status']];
    }
    if (!empty($filters['search'])) {
        $applied[] = ['label' => 'Search', 'value' => (string)$filters['search']];
    }
    if (!empty($filters['route'])) {
        $applied[] = ['label' => 'Route', 'value' => (string)$filters['route']];
    }
    return $applied;
}

function rgen_title(array $def, string $driverName, string $busName, ?array $period): string
{
    $title = (string)$def['label'];
    if ($driverName !== '') {
        $title .= ' — ' . $driverName;
    } elseif ($busName !== '') {
        $title .= ' — ' . $busName;
    }
    $title .= ' — ' . ($period ? (string)$period['label'] : 'All records');
    return $title;
}

/**
 * Driver Performance delegates to the existing per-driver metrics service.
 */
function rgen_driver_performance(array $def, array $filters, string $driverName, ?array $period, ?array $actor, bool $preview): array
{
    $driverId = (string)($filters['driver_id'] ?? '');
    if ($driverId === '') {
        throw new InvalidArgumentException('Select a driver for the Driver Performance report.');
    }
    $period = $period ?? rgen_resolve_period('monthly', '', '');

    $db = Database::get();
    $metrics = dpm_compute($db, $driverId, $period['start'], $period['end'], $period['mode']);
    if (isset($metrics['error'])) {
        throw new RuntimeException((string)$metrics['error']);
    }

    $driverName = trim((string)($metrics['name'] ?? '')) !== '' ? (string)$metrics['name'] : ($driverName !== '' ? $driverName : $driverId);

    $att = $metrics['attendance'] ?? [];
    $tr = $metrics['trips'] ?? [];
    $pa = $metrics['passengers'] ?? [];
    $ot = $metrics['other'] ?? [];
    $fa = $metrics['fare'] ?? [];
    $ca = $metrics['capacity'] ?? [];

    $rate = $att['rate'] ?? null;
    $kpis = [
        rgen_kpi('Attendance Rate', $rate === null || $rate === '' ? 'Not recorded' : $rate . '%', 'Present ' . (int)($att['present'] ?? 0) . ' of ' . (int)($att['scheduled'] ?? 0) . ' expected day(s)'),
        rgen_kpi('Trips', (string)(int)($tr['total'] ?? 0), 'Completed ' . (int)($tr['completed'] ?? 0) . ' · Cancelled ' . (int)($tr['cancelled'] ?? 0) . ' · Pending ' . (int)($tr['pending'] ?? 0)),
        rgen_kpi('Passengers', (string)(int)($pa['total'] ?? 0), 'Regular ' . (int)($pa['regular'] ?? 0) . ' · Special ' . (int)($pa['special'] ?? 0)),
        rgen_kpi('Cargo Records', (string)(int)($ot['records'] ?? 0), (int)($ot['occupied_spaces'] ?? 0) . ' occupied space(s)'),
        rgen_kpi('Gross Income', rgen_money($metrics['gross_income'] ?? 0), null, 'money'),
        rgen_kpi('Fuel Cost', rgen_money($metrics['gas']['amount'] ?? 0), number_format((float)($metrics['gas']['liters'] ?? 0), 2) . ' L', 'money'),
        rgen_kpi('Fixed Costs', rgen_money($metrics['fixed_costs']['total'] ?? 0), null, 'money'),
        rgen_kpi('Net Income', rgen_money($metrics['net_income'] ?? 0), null, 'money'),
        rgen_kpi('Occupancy', ($ca['occupancy'] ?? null) === null ? 'Not available' : $ca['occupancy'] . '%', (int)($ca['occupied_passenger'] ?? 0) . ' passenger + ' . (int)($ca['occupied_other'] ?? 0) . ' cargo space(s) of ' . (int)($ca['vehicle_capacity'] ?? 0)),
        rgen_kpi('Remittance', (string)($metrics['remittance_status'] ?? 'Not recorded'), 'Remitted ' . rgen_money($metrics['remitted_amount'] ?? 0) . ' of ' . rgen_money($metrics['paid_expected'] ?? 0) . ' paid income'),
    ];

    $findings = [];
    if ((int)($tr['total'] ?? 0) === 0) {
        $findings[] = 'No recorded trips were found for this driver in the selected period.';
    }
    $absent = (int)($att['absent'] ?? 0);
    if ($absent > 0) {
        $findings[] = $absent . ' absent day(s) recorded against ' . (int)($att['scheduled'] ?? 0) . ' expected workday(s).';
    }
    $findings[] = 'Net income of ' . rgen_money($metrics['net_income'] ?? 0) . ' after ' . rgen_money($metrics['gas']['amount'] ?? 0) . ' fuel and ' . rgen_money($metrics['fixed_costs']['total'] ?? 0) . ' fixed costs.';
    $topRoutes = $metrics['top_routes'] ?? [];
    if ($topRoutes) {
        $route = (string)array_key_first($topRoutes);
        $findings[] = 'Top route: ' . $route . ' with ' . (int)$topRoutes[$route] . ' record(s).';
    }

    $extraTables = [];
    if (!empty($metrics['trip_records'])) {
        $extraTables[] = [
            'title' => 'Trip Records',
            'columns' => [
                ['key' => 'trip', 'label' => 'Trip', 'type' => 'text'],
                ['key' => 'day', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'records', 'label' => 'Records', 'type' => 'int'],
                ['key' => 'fare', 'label' => 'Fare', 'type' => 'money'],
                ['key' => 'payment_status', 'label' => 'Payment Status', 'type' => 'status'],
            ],
            'rows' => $metrics['trip_records'],
        ];
    }
    if (!empty($metrics['payments'])) {
        $extraTables[] = [
            'title' => 'Payments',
            'columns' => [
                ['key' => 'payment_method', 'label' => 'Method', 'type' => 'text'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
                ['key' => 'count', 'label' => 'Count', 'type' => 'int'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
            ],
            'rows' => $metrics['payments'],
        ];
    }
    if ($topRoutes) {
        $routeRows = [];
        foreach ($topRoutes as $route => $count) {
            $routeRows[] = ['route' => $route, 'count' => $count];
        }
        $extraTables[] = [
            'title' => 'Top Routes',
            'columns' => [
                ['key' => 'route', 'label' => 'Route', 'type' => 'text'],
                ['key' => 'count', 'label' => 'Records', 'type' => 'int'],
            ],
            'rows' => $routeRows,
        ];
    }

    $narrative = null;
    if (!$preview) {
        try {
            $analysis = dpm_build_analysis($db, $driverId, $period['start'], $period['end'], $period['mode']);
            if (!isset($analysis['error'])) {
                $a = $analysis['analysis'] ?? [];
                $narrative = [
                    'summary' => (string)($a['summary'] ?? ''),
                    'insights' => array_values(array_filter([
                        (string)($a['strengths'] ?? ''),
                        (string)($a['watch_outs'] ?? ''),
                        (string)($a['key_findings'] ?? ''),
                    ], static fn ($x) => trim($x) !== '')),
                    'recommendations' => array_values(array_filter([(string)($a['recommendation'] ?? '')], static fn ($x) => trim($x) !== '')),
                ];
            }
        } catch (Throwable $e) {
            $narrative = null;
        }
    }

    $rows = $metrics['daily_performance'] ?? [];

    return [
        'type' => 'driver_performance',
        'type_label' => (string)$def['label'],
        'description' => (string)$def['description'],
        'category' => (string)$def['category'],
        'title' => rgen_title($def, $driverName, '', $period),
        'period' => $period,
        'applied_filters' => rgen_applied_filters($filters, $driverName, ''),
        'columns' => $def['columns'],
        'records' => $rows,
        'record_count' => count($rows),
        'kpis' => $kpis,
        'totals' => array_values(array_filter($kpis, static fn ($k) => ($k['format'] ?? '') === 'money')),
        'distributions' => [],
        'trends' => [],
        'comparison' => null,
        'findings' => $findings,
        'extra_tables' => $extraTables,
        'metrics' => $metrics,
        'narrative' => $narrative,
        'generated_at' => (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s'),
        'generated_by' => (string)($actor['username'] ?? $actor['name'] ?? 'System process'),
    ];
}

/**
 * Build the full report payload for any registry type.
 */
function rgen_build_report(string $type, array $input = [], ?array $actor = null, bool $preview = false): array
{
    date_default_timezone_set('Asia/Manila');

    $def = rgen_get_type($type);
    if ($def === null) {
        throw new InvalidArgumentException('Unknown report type.');
    }

    $filters = rgen_normalize_filters($type, $input);

    $driverName = '';
    $busName = '';
    $model = null;

    if ($type === 'driver_performance') {
        $driverName = (string)($filters['driver_id'] ?? '');
        return rgen_driver_performance($def, $filters, $driverName, $filters['period'] ?? null, $actor, $preview);
    }

    $model = new GenerateReportModel();

    if (!empty($filters['driver_id'])) {
        $driverName = (string)$filters['driver_id'];
        foreach ($model->driverOptions() as $option) {
            if ($option['id'] === $filters['driver_id']) {
                $driverName = $option['name'];
                break;
            }
        }
    }
    if (!empty($filters['bus_no'])) {
        $busName = (string)$filters['bus_no'];
        foreach ($model->busOptions() as $option) {
            if ($option['id'] === $filters['bus_no']) {
                $busName = $option['name'];
                break;
            }
        }
    }

    $period = $filters['period'] ?? null;
    $result = $model->generate($type, $filters);
    $rows = $result['records'];

    $kpis = rgen_kpis($type, $rows, $def);

    return [
        'type' => $type,
        'type_label' => (string)$def['label'],
        'description' => (string)$def['description'],
        'category' => (string)$def['category'],
        'title' => rgen_title($def, $driverName, $busName, $period),
        'period' => $period,
        'applied_filters' => rgen_applied_filters($filters, $driverName, $busName),
        'columns' => $def['columns'],
        'records' => $rows,
        'record_count' => count($rows),
        'kpis' => $kpis,
        'totals' => array_values(array_filter($kpis, static fn ($k) => ($k['format'] ?? '') === 'money')),
        'distributions' => rgen_distributions($type, $rows),
        'trends' => rgen_trends($def, $type, $rows),
        'comparison' => $period ? rgen_comparison($type, $filters, $period, $rows, $model) : null,
        'findings' => rgen_findings($type, $rows, $kpis),
        'extra_tables' => [],
        'narrative' => null,
        'generated_at' => (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s'),
        'generated_by' => (string)($actor['username'] ?? $actor['name'] ?? 'System process'),
    ];
}

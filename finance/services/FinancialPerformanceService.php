<?php
declare(strict_types=1);

namespace Finance\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

final class FinancialPerformanceService
{
    private PDO $db;
    private array $columns = [];
    private array $notes = [];
    private array $driverAliases = [];
    private array $busAliases = [];
    private array $drivers = [];
    private array $buses = [];
    private array $tripBuses = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function filters(array $input, ?DateTimeImmutable $now = null): array
    {
        $now = $now ?? new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
        $period = (string) ($input['report_period'] ?? $input['period'] ?? ((!empty($input['from']) || !empty($input['to'])) ? 'custom' : 'this_month'));
        $aliases = ['monthly' => 'this_month', 'month' => 'this_month', 'annually' => 'this_year', 'year' => 'this_year', 'weekly' => 'this_week'];
        $period = $aliases[$period] ?? $period;
        switch ($period) {
            case 'today': $start = $end = $now; $label = 'Today'; break;
            case 'this_week':
                $start = $now->modify('-' . ((int) $now->format('N') - 1) . ' days');
                $end = $start->modify('+6 days'); $label = 'This Week'; break;
            case 'this_month':
                $start = $now->modify('first day of this month'); $end = $now->modify('last day of this month'); $label = 'This Month'; break;
            case 'last6':
                $start = $now->modify('first day of this month')->modify('-5 months');
                $end = $now->modify('last day of this month'); $label = 'Last 6 Months'; break;
            case 'this_year':
                $start = $now->setDate((int) $now->format('Y'), 1, 1);
                $end = $now->setDate((int) $now->format('Y'), 12, 31); $label = 'This Year'; break;
            case 'custom':
                $start = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($input['from'] ?? ''), new DateTimeZone('Asia/Manila'));
                $end = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($input['to'] ?? ''), new DateTimeZone('Asia/Manila'));
                if (!$start || !$end || $start->format('Y-m-d') !== ($input['from'] ?? '') || $end->format('Y-m-d') !== ($input['to'] ?? '') || $start > $end) {
                    throw new InvalidArgumentException('Choose valid start and end dates, with the start on or before the end.');
                }
                $label = 'Custom Date Range'; break;
            default: throw new InvalidArgumentException('Unsupported financial reporting period.');
        }
        return [
            'period' => $period, 'from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d'),
            'label' => $label . ' · ' . $start->format('F j, Y') . ' – ' . $end->format('F j, Y'),
            'driver' => trim((string) ($input['driver'] ?? '')), 'bus' => trim((string) ($input['bus'] ?? '')),
        ];
    }

    private function cols(string $table): array
    {
        if (isset($this->columns[$table])) return $this->columns[$table];
        $check = $this->db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $check->execute([$table]);
        return $this->columns[$table] = array_fill_keys($check->fetchAll(PDO::FETCH_COLUMN), true);
    }

    private function first(array $cols, array $names): ?string
    {
        foreach ($names as $name) if (isset($cols[$name])) return $name;
        return null;
    }

    private function directory(string $table, string $code, array $labelNames): array
    {
        $cols = $this->cols($table);
        if (!isset($cols[$code])) return [];
        $label = $this->first($cols, $labelNames) ?? $code;
        $stmt = $this->db->query("SELECT `$code` AS code, `$label` AS label" . (isset($cols['id']) ? ', id' : '') . " FROM `$table` ORDER BY `$label`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) { $row['code'] = (string) $row['code']; $row['label'] = (string) $row['label']; }
        unset($row);
        return $rows;
    }

    private function prepareDirectories(): void
    {
        $this->drivers = $this->directory('drivers', 'driver_id', ['full_name', 'name']);
        $this->buses = $this->directory('buses', 'bus_id', ['plate_number', 'bus_no', 'bus_label']);
        foreach ($this->drivers as $row) {
            $this->driverAliases[(string) $row['code']] = (string) $row['code'];
            if (isset($row['id'])) $this->driverAliases[(string) $row['id']] = (string) $row['code'];
        }
        foreach ($this->buses as $row) {
            $this->busAliases[(string) $row['code']] = (string) $row['code'];
            if (isset($row['id'])) $this->busAliases[(string) $row['id']] = (string) $row['code'];
        }
    }

    public function options(): array
    {
        $this->prepareDirectories();
        return ['drivers' => $this->drivers, 'buses' => $this->buses];
    }

    private function source(string $table, array $filters, string $kind, array $dateNames, array $amountNames): array
    {
        $cols = $this->cols($table);
        if (!$cols) { $this->notes[] = "Source unavailable: $table."; return []; }
        $date = $this->first($cols, $dateNames);
        $amount = $this->first($cols, $amountNames);
        if (!$date || !$amount) { $this->notes[] = "Source $table has no supported date/amount columns; it is not included."; return []; }
        $end = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        $dateExpr = $table === 'passenger_trip_history' && isset($cols['trip_date'], $cols['date_booked']) ? "COALESCE(NULLIF(r.trip_date, '0000-00-00'), DATE(r.date_booked))" : "r.`$date`";
        $where = "$dateExpr >= :from AND $dateExpr < :until";
        if ($table === 'passenger_trips') {
            if (!isset($cols['payment_status'])) throw new \RuntimeException('Paid fare status is unavailable; revenue cannot be verified.');
            $where .= " AND LOWER(TRIM(r.payment_status)) = 'paid'";
            if (isset($cols['archived_status'])) $where .= " AND COALESCE(r.archived_status, 'no') <> 'yes'";
            if (isset($cols['archived'])) $where .= ' AND COALESCE(r.archived, 0) = 0';
        }
        if ($table === 'fuel_logs' && isset($cols['liters'])) $where .= ' AND COALESCE(r.liters, 0) > 0';
        $selected = [$dateExpr . ' AS report_date', "r.`$amount` AS report_amount"];
        $join = '';
        if ($table === 'passenger_trips' && !isset($cols['type']) && isset($cols['passenger_id'])) {
            $passengerCols = $this->cols('passengers');
            if (isset($passengerCols['id'], $passengerCols['type'])) {
                $join = ' LEFT JOIN passengers p ON p.id = r.passenger_id';
                $selected[] = 'p.type AS type';
            }
        }
        foreach (['driver_id', 'bus_id', 'trip_no', 'trip_id', 'payment_method', 'type', 'category', 'description', 'liters', 'receipt_number'] as $field) {
            if (isset($cols[$field])) $selected[] = "r.`$field`";
        }
        $id = $this->first($cols, ['history_id', 'refuel_id', 'fuel_log_id', 'id', 'receipt_number']);
        if ($id) $selected[] = "r.`$id` AS source_id";
        $stmt = $this->db->prepare('SELECT ' . implode(', ', $selected) . " FROM `$table` r $join WHERE $where ORDER BY $dateExpr");
        $stmt->execute([':from' => $filters['from'], ':until' => $end]);
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $driverRaw = trim((string) ($row['driver_id'] ?? ''));
            $busRaw = trim((string) ($row['bus_id'] ?? ''));
            $tripRef = (string) ($row['trip_no'] ?? $row['trip_id'] ?? '');
            if ($busRaw === '' && $tripRef !== '') {
                $key = substr((string) $row['report_date'], 0, 10) . '|' . $driverRaw . '|' . $tripRef;
                $matches = array_keys($this->tripBuses[$key] ?? []);
                if (count($matches) === 1) $busRaw = (string) $matches[0];
            }
            $driver = $this->driverAliases[$driverRaw] ?? $driverRaw;
            $bus = $this->busAliases[$busRaw] ?? $busRaw;
            if ($filters['driver'] !== '' && $driver !== $filters['driver']) continue;
            if ($filters['bus'] !== '' && $bus !== $filters['bus']) continue;
            $cents = (int) round((float) ($row['report_amount'] ?? 0) * 100);
            if ($kind === 'expense' && $cents === 0) continue;
            $rows[] = [
                'date' => substr((string) $row['report_date'], 0, 10), 'kind' => $kind,
                'category' => $kind === 'settlement' ? 'Remittance settlement' : ($kind === 'revenue' ? ((strtolower((string) ($row['type'] ?? '')) === 'other') ? 'Other fare' : 'Passenger fare') : (string) ($row['category'] ?? (in_array($table, ['fuel_refuels', 'fuel_logs'], true) ? 'Fuel' : 'Operating expenses'))),
                'cents' => $cents, 'driver' => $driver, 'bus' => $bus,
                'trip' => (string) ($row['trip_no'] ?? $row['trip_id'] ?? ''),
                'payment' => (string) ($row['payment_method'] ?? 'Unspecified'),
                'source' => $table, 'reference' => $table . ':' . (string) ($row['source_id'] ?? ''),
                'receipt' => trim((string) ($row['receipt_number'] ?? '')),
                'description' => (string) ($row['description'] ?? ($kind === 'revenue' ? 'Fare collection' : 'Recorded expense')),
            ];
        }
        return $rows;
    }

    private function records(array $filters): array
    {
        $this->tripBuses = [];
        $tripCols = $this->cols('bus_trips');
        $tripDate = $this->first($tripCols, ['trip_date', 'created_at']);
        if ($tripDate && isset($tripCols['driver_id'], $tripCols['bus_id'])) {
            $refs = array_values(array_filter(['trip_no', 'trip_id', 'id'], static fn($name) => isset($tripCols[$name])));
            if ($refs) {
                $fields = implode(', ', array_map(static fn($name) => "`$name`", $refs));
                $stmt = $this->db->prepare("SELECT `$tripDate` AS day, driver_id, bus_id, $fields FROM bus_trips WHERE `$tripDate` >= ? AND `$tripDate` < ?");
                $stmt->execute([$filters['from'], (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d')]);
                while ($trip = $stmt->fetch(PDO::FETCH_ASSOC)) foreach ($refs as $ref) {
                    $key = substr((string) $trip['day'], 0, 10) . '|' . (string) $trip['driver_id'] . '|' . (string) $trip[$ref];
                    if (!empty($trip['bus_id'])) $this->tripBuses[$key][(string) $trip['bus_id']] = true;
                }
            }
        }
        $revenues = array_merge(
            $this->source('passenger_trips', $filters, 'revenue', ['boarding_time', 'created_at'], ['fare']),
            $this->source('passenger_trip_history', $filters, 'revenue', ['trip_date', 'date_booked'], ['fare'])
        );
        $expenses = array_merge(
            $this->source('expenses', $filters, 'expense', ['expense_date', 'created_at'], ['amount']),
            $this->source('financial_expenses', $filters, 'expense', ['expense_date', 'created_at'], ['amount'])
        );
        $hasFuelLedger = count(array_filter($expenses, static fn($r) => self::isFuel($r['category']))) > 0;
        if (!$hasFuelLedger) {
            $receipts = $this->source('fuel_refuels', $filters, 'expense', ['created_at'], ['total_cost']);
            $legacyFuel = $this->source('fuel_logs', $filters, 'expense', ['created_at'], ['cost']);
            $seen = [];
            foreach ($receipts as $row) if ($row['receipt'] !== '') $seen[$row['bus'] . '|' . $row['receipt']] = true;
            foreach ($legacyFuel as $row) {
                $key = $row['bus'] . '|' . $row['receipt'];
                if ($row['receipt'] === '' || !isset($seen[$key])) $receipts[] = $row;
            }
            $expenses = array_merge($expenses, $receipts);
            $this->notes[] = $filters['from'] . ' – ' . $filters['to'] . ': Fuel uses actual refuel receipts and legacy fuel-log purchase rows (positive liters and recorded cost) when no fuel expense is posted in this scope. Copies with the same stored receipt number and bus across both fuel sources are excluded; equal amounts alone are not treated as duplicates.';
        } else {
            $this->notes[] = $filters['from'] . ' – ' . $filters['to'] . ': Fuel expenses use posted expense-ledger entries for this scope; refuel receipts are not added again. Reconcile any unposted receipts before interpreting this as a complete accounting close.';
        }
        $settlementTable = $this->cols('remittance_logs') ? 'remittance_logs' : 'remittances';
        $settlements = $this->source($settlementTable, $filters, 'settlement', ['created_at'], ['actual_cash']);
        return array_merge($revenues, $expenses, $settlements);
    }

    public function build(array $input): array
    {
        $filters = self::filters($input);
        $this->prepareDirectories();
        if ($filters['driver'] !== '' && !in_array($filters['driver'], array_column($this->drivers, 'code'), true)) throw new InvalidArgumentException('Choose a valid driver.');
        if ($filters['bus'] !== '' && !in_array($filters['bus'], array_column($this->buses, 'code'), true)) throw new InvalidArgumentException('Choose a valid bus.');
        $previous = $filters;
        $start = new DateTimeImmutable($filters['from']);
        $finish = new DateTimeImmutable($filters['to']);
        if ($filters['period'] === 'this_month') {
            $p = $start->modify('-1 month'); $previous['from'] = $p->format('Y-m-01'); $previous['to'] = $p->format('Y-m-t');
        } elseif ($filters['period'] === 'this_year') {
            $previous['from'] = $start->modify('-1 year')->format('Y-m-d'); $previous['to'] = $finish->modify('-1 year')->format('Y-m-d');
        } elseif ($filters['period'] === 'last6') {
            $previous['from'] = $start->modify('-6 months')->format('Y-m-d'); $previous['to'] = $start->modify('-1 day')->format('Y-m-d');
        } else {
            $days = (int) $start->diff($finish)->days + 1;
            $previous['to'] = $start->modify('-1 day')->format('Y-m-d'); $previous['from'] = $start->modify("-$days days")->format('Y-m-d');
        }
        $currentRows = $this->records($filters);
        $previousRows = $this->records($previous);
        $snapshot = self::aggregate($currentRows, $filters, $this->drivers, $this->buses);
        $prev = self::aggregate($previousRows, $previous, $this->drivers, $this->buses);
        $snapshot['previous'] = ['from' => $previous['from'], 'to' => $previous['to'], 'totals' => $prev['totals']];
        $snapshot['growth'] = [];
        foreach (['revenue', 'expenses', 'profit'] as $measure) {
            $before = $prev['totals'][$measure];
            $snapshot['growth'][$measure] = abs($before) > 0.00001 ? round(($snapshot['totals'][$measure] - $before) / abs($before) * 100, 2) : null;
        }
        $snapshot['notes'] = array_values(array_unique(array_merge($this->notes, [
            'Revenue is paid live fares (archived live copies excluded) plus archived fares. Remittances and bus-trip revenue summaries are settlement/summary records, not additional income.',
            'Expenses are posted expenses and financial_expenses records, with the fuel-source precedence described above. Configured fixed-cost estimates are not added to posted ledgers a second time.',
            'Driver and bus allocations use identifiers stored on each record or a unique matching date/driver/trip in bus_trips. Missing or ambiguous attribution is Unassigned, never guessed from current assignments.',
            'Daily, weekly (Monday–Sunday), monthly and yearly buckets all cover only the selected reporting range; incomplete buckets are partial periods.',
        ])));
        $snapshot['options'] = ['drivers' => $this->drivers, 'buses' => $this->buses];
        $snapshot['generated_at'] = date('Y-m-d H:i:s');
        $snapshot['insights'] = self::insights($snapshot);
        return $snapshot;
    }

    public static function aggregate(array $rows, array $filters, array $driverLabels = [], array $busLabels = []): array
    {
        $totals = ['revenue' => 0, 'expenses' => 0, 'fuel' => 0, 'collections' => 0, 'passenger_revenue' => 0, 'passengers' => 0, 'trips' => 0];
        $groups = ['drivers' => [], 'buses' => [], 'categories' => [], 'payments' => []];
        $series = ['daily' => [], 'weekly' => [], 'monthly' => [], 'yearly' => []];
        $trips = [];
        $driverMap = array_column($driverLabels, 'label', 'code');
        $busMap = array_column($busLabels, 'label', 'code');
        $add = static function (array &$group, string $key, string $name, string $kind, int $cents): void {
            if (!isset($group[$key])) $group[$key] = ['key' => $key, 'name' => $name, 'revenue' => 0, 'expenses' => 0, 'count' => 0];
            $group[$key][$kind === 'revenue' ? 'revenue' : 'expenses'] += $cents;
            $group[$key]['count']++;
        };
        foreach ($rows as $row) {
            $kind = $row['kind']; $cents = (int) $row['cents'];
            if ($kind === 'settlement') { $totals['collections'] += $cents; continue; }
            $totals[$kind === 'revenue' ? 'revenue' : 'expenses'] += $cents;
            if ($kind === 'expense' && self::isFuel($row['category'])) $totals['fuel'] += $cents;
            if ($kind === 'revenue') {
                if ($row['category'] === 'Passenger fare') { $totals['passenger_revenue'] += $cents; $totals['passengers']++; }
                if ($row['trip'] !== '') $trips[$row['date'] . '|' . $row['driver'] . '|' . $row['bus'] . '|' . $row['trip']] = true;
                $add($groups['payments'], $row['payment'], $row['payment'], $kind, $cents);
            } else {
                $add($groups['categories'], $row['category'], $row['category'], $kind, $cents);
            }
            $driver = $row['driver'] ?: 'unassigned'; $bus = $row['bus'] ?: 'unassigned';
            $add($groups['drivers'], $driver, $driverMap[$driver] ?? ($driver === 'unassigned' ? 'Unassigned' : $driver), $kind, $cents);
            $add($groups['buses'], $bus, $busMap[$bus] ?? ($bus === 'unassigned' ? 'Unassigned' : $bus), $kind, $cents);
            $d = new DateTimeImmutable($row['date']);
            $keys = ['daily' => $row['date'], 'weekly' => $d->modify('-' . ((int) $d->format('N') - 1) . ' days')->format('Y-m-d'), 'monthly' => $d->format('Y-m'), 'yearly' => $d->format('Y')];
            foreach ($keys as $bucket => $key) $add($series[$bucket], $key, $key, $kind, $cents);
        }
        $totals['trips'] = count($trips);
        foreach (['revenue', 'expenses', 'fuel', 'collections', 'passenger_revenue'] as $key) $totals[$key] /= 100;
        $totals['profit'] = round($totals['revenue'] - $totals['expenses'], 2);
        $totals['operational_costs'] = round($totals['expenses'] - $totals['fuel'], 2);
        $totals['margin'] = $totals['revenue'] > 0 ? round($totals['profit'] / $totals['revenue'] * 100, 2) : null;
        $finalize = static function (array $group): array {
            foreach ($group as &$item) {
                $item['revenue'] /= 100; $item['expenses'] /= 100;
                $item['profit'] = round($item['revenue'] - $item['expenses'], 2);
            }
            unset($item);
            return array_values($group);
        };
        foreach ($groups as $key => $group) $groups[$key] = $finalize($group);
        foreach (['drivers', 'buses'] as $key) usort($groups[$key], static fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        $cursor = new DateTimeImmutable($filters['from']);
        $end = new DateTimeImmutable($filters['to']);
        while ($cursor <= $end) {
            $keys = ['daily' => $cursor->format('Y-m-d'), 'weekly' => $cursor->modify('-' . ((int) $cursor->format('N') - 1) . ' days')->format('Y-m-d'), 'monthly' => $cursor->format('Y-m'), 'yearly' => $cursor->format('Y')];
            foreach ($keys as $bucket => $key) if (!isset($series[$bucket][$key])) $series[$bucket][$key] = ['key' => $key, 'name' => $key, 'revenue' => 0, 'expenses' => 0, 'count' => 0];
            $cursor = $cursor->modify('+1 day');
        }
        foreach ($series as $key => $group) { ksort($group); $series[$key] = $finalize($group); }
        usort($rows, static fn($a, $b) => strcmp($b['date'], $a['date']));
        foreach ($rows as &$row) { $row['amount'] = $row['cents'] / 100; unset($row['cents']); }
        unset($row);
        return ['filters' => $filters, 'totals' => $totals, 'breakdowns' => $groups, 'series' => $series, 'records' => $rows, 'empty' => count($rows) === 0];
    }

    public static function insights(array $snapshot): array
    {
        $t = $snapshot['totals'];
        $money = static fn($v) => '₱' . number_format((float) $v, 2);
        $findings = [($t['profit'] >= 0 ? 'Net profit' : 'Net loss') . ' for this period is ' . $money($t['profit']) . '.',
            'Paid fare revenue of ' . $money($t['revenue']) . ' is compared with recorded expenses of ' . $money($t['expenses']) . '.',
            'Fuel accounts for ' . ($t['expenses'] > 0 ? number_format($t['fuel'] / $t['expenses'] * 100, 1) . '%' : '0%') . ' of recorded expenses.'];
        if ($t['margin'] !== null) $findings[] = 'Net profit margin is ' . number_format($t['margin'], 1) . '%.';
        if ($snapshot['growth']['revenue'] !== null) $findings[] = 'Revenue changed by ' . number_format($snapshot['growth']['revenue'], 1) . '% against the preceding comparison period.';
        $ranked = array_values(array_filter($snapshot['breakdowns']['buses'], static fn($r) => $r['key'] !== 'unassigned'));
        if ($ranked) {
            $findings[] = 'Highest revenue bus: ' . $ranked[0]['name'] . ' (' . $money($ranked[0]['revenue']) . ').';
            $last = $ranked[count($ranked) - 1];
            $findings[] = 'Lowest revenue bus with recorded activity: ' . $last['name'] . ' (' . $money($last['revenue']) . ').';
        }
        return $findings;
    }

    private static function isFuel(string $category): bool
    {
        return preg_match('/fuel|diesel|gasoline|petrol|^gas$/i', trim($category)) === 1;
    }
}

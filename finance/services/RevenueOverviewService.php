<?php

namespace Finance\Services;

require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/DateFilterService.php';

use App\Core\Database;
use PDO;

class RevenueOverviewService
{
    private ?PDO $db;

    private array $tableCache = [];

    private array $columnsCache = [];

    private const LIST_CAP = 50;

    private const TOP_N = 10;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function db(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::get();
        }

        return $this->db;
    }

    public function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        $exists = false;

        try {
            $stmt = $this->db()->prepare(
                'SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1'
            );
            $stmt->execute([':t' => $table]);
            $exists = (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $exists = false;
        }

        return $this->tableCache[$table] = $exists;
    }

    public function columns(string $table): array
    {
        if (array_key_exists($table, $this->columnsCache)) {
            return $this->columnsCache[$table];
        }

        $cols = [];

        if ($this->tableExists($table)) {
            try {
                $safe = str_replace('`', '', $table);
                $stmt = $this->db()->query("SHOW COLUMNS FROM `$safe`");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols[strtolower((string) $row['Field'])] = true;
                }
            } catch (\Throwable $e) {
                $cols = [];
            }
        }

        return $this->columnsCache[$table] = $cols;
    }

    public function firstColumn(string $table, array $candidates): ?string
    {
        $cols = $this->columns($table);

        foreach ($candidates as $candidate) {
            $candidate = strtolower((string) $candidate);
            if (isset($cols[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    public function resolvePeriod(array $filters): array
    {
        $resolved = DateFilterService::normalizeRequest($filters);
        $previous = $this->previousRange($resolved['period'], $resolved['start'], $resolved['end']);

        return [
            'period' => $resolved['period'],
            'from' => $resolved['start'],
            'to' => $resolved['end'],
            'previous_from' => $previous[0],
            'previous_to' => $previous[1],
        ];
    }

    private function previousRange(string $period, string $from, string $to): array
    {
        return DateFilterService::previousRange($period, $from, $to);
    }

    private function colExpr(?array $cols, string $name): ?string
    {
        if (empty($cols) || !isset($cols[strtolower($name)])) {
            return null;
        }

        return '`' . strtolower($name) . '`';
    }

    private function liveWhere(array $live, array $range, array $filters, string $prefix, array &$params, bool $paidOnly = true): string
    {
        $conds = [];

        if ($paidOnly && isset($live['payment_status'])) {
            $conds[] = "`payment_status` = 'paid'";
        }

        if (isset($live['boarding_time'])) {
            $from = (string) ($range['from'] ?? '');
            $to = (string) ($range['to'] ?? '');
            if ($from !== '') {
                $conds[] = "`boarding_time` >= :{$prefix}from";
                $params[":{$prefix}from"] = $from . ' 00:00:00';
            }
            if ($to !== '') {
                $conds[] = "`boarding_time` < :{$prefix}to";
                $params[":{$prefix}to"] = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
            }
        }

        $bus = trim((string) ($filters['bus'] ?? ''));
        if ($bus !== '' && isset($live['driver_id'])) {
            $conds[] = "`driver_id` LIKE :{$prefix}driver";
            $params[":{$prefix}driver"] = '%' . $bus . '%';
        }

        $route = trim((string) ($filters['route'] ?? ''));
        if ($route !== '' && isset($live['boarding_point'], $live['destination'])) {
            $conds[] = "CONCAT(COALESCE(`boarding_point`, ''), ' ', COALESCE(`destination`, '')) LIKE :{$prefix}route";
            $params[":{$prefix}route"] = '%' . $route . '%';
        }

        return count($conds) ? implode(' AND ', $conds) : '1=1';
    }

    private function historyWhere(array $history, array $range, array $filters, string $prefix, array &$params): string
    {
        $conds = [];

        if (isset($history['trip_date'])) {
            $from = (string) ($range['from'] ?? '');
            $to = (string) ($range['to'] ?? '');
            if ($from !== '') {
                $conds[] = "`trip_date` >= :{$prefix}from";
                $params[":{$prefix}from"] = $from;
            }
            if ($to !== '') {
                $conds[] = "`trip_date` <= :{$prefix}to";
                $params[":{$prefix}to"] = $to;
            }
        }

        $bus = trim((string) ($filters['bus'] ?? ''));
        if ($bus !== '' && isset($history['driver_id'])) {
            $conds[] = "`driver_id` LIKE :{$prefix}driver";
            $params[":{$prefix}driver"] = '%' . $bus . '%';
        }

        $route = trim((string) ($filters['route'] ?? ''));
        if ($route !== '' && isset($history['boarding_point'], $history['destination'])) {
            $conds[] = "CONCAT(COALESCE(`boarding_point`, ''), ' ', COALESCE(`destination`, '')) LIKE :{$prefix}route";
            $params[":{$prefix}route"] = '%' . $route . '%';
        }

        return count($conds) ? implode(' AND ', $conds) : '1=1';
    }

    public function unionSql(array $live, array $history, array $range, array $filters, array $selects): array
    {
        $parts = [];
        $params = [];

        if (!empty($live) && isset($live['fare'])) {
            $select = [];
            foreach ($selects as $alias => $spec) {
                $expr = $spec['live'] ?? null;
                $select[] = ($expr !== null ? $expr : 'NULL') . " AS `$alias`";
            }
            $select[] = '`fare`';
            $where = $this->liveWhere($live, $range, $filters, 'l', $params);
            $parts[] = 'SELECT ' . implode(', ', $select) . ' FROM `passenger_trips` WHERE ' . $where;
        }

        if (!empty($history) && isset($history['fare'])) {
            $select = [];
            foreach ($selects as $alias => $spec) {
                $expr = $spec['history'] ?? null;
                $select[] = ($expr !== null ? $expr : 'NULL') . " AS `$alias`";
            }
            $select[] = '`fare`';
            $where = $this->historyWhere($history, $range, $filters, 'h', $params);
            $parts[] = 'SELECT ' . implode(', ', $select) . ' FROM `passenger_trip_history` WHERE ' . $where;
        }

        if (count($parts) === 0) {
            return ['', []];
        }

        return ['(' . implode(' UNION ALL ', $parts) . ')', $params];
    }

    private function bucketSeries(array $live, array $history, array $range, array $filters, ?string $liveBucket, ?string $histBucket): array
    {
        $parts = [];
        $params = [];

        if ($liveBucket !== null && !empty($live) && isset($live['fare'], $live['boarding_time'])) {
            $where = $this->liveWhere($live, $range, $filters, 'l', $params);
            $parts[] = 'SELECT ' . $liveBucket . ' AS k, `fare` FROM `passenger_trips` WHERE ' . $where;
        }

        if ($histBucket !== null && !empty($history) && isset($history['fare'], $history['trip_date'])) {
            $where = $this->historyWhere($history, $range, $filters, 'h', $params);
            $parts[] = 'SELECT ' . $histBucket . ' AS k, `fare` FROM `passenger_trip_history` WHERE ' . $where;
        }

        if (count($parts) === 0) {
            return [];
        }

        $sql = 'SELECT k, COALESCE(SUM(fare), 0) AS revenue, COUNT(*) AS transactions
                FROM (' . implode(' UNION ALL ', $parts) . ') t
                GROUP BY k ORDER BY k';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function definedSelects(array $live, array $history): array
    {
        return [
            'trip_key' => [
                'live' => $this->colExpr($live, 'trip_no') ?? $this->colExpr($live, 'trip_id') ?? $this->colExpr($live, 'id'),
                'history' => $this->colExpr($history, 'trip_id') ?? $this->colExpr($history, 'trip_no') ?? $this->colExpr($history, 'history_id'),
            ],
            'driver_key' => [
                'live' => $this->colExpr($live, 'driver_id'),
                'history' => $this->colExpr($history, 'driver_id'),
            ],
            'boarding_point' => [
                'live' => $this->colExpr($live, 'boarding_point'),
                'history' => $this->colExpr($history, 'boarding_point'),
            ],
            'destination' => [
                'live' => $this->colExpr($live, 'destination'),
                'history' => $this->colExpr($history, 'destination'),
            ],
        ];
    }

    private function aggregateKpis(array $live, array $history, array $range, array $filters): array
    {
        $selects = $this->definedSelects($live, $history);
        [$union, $params] = $this->unionSql($live, $history, $range, $filters, [
            'trip_key' => $selects['trip_key'],
            'driver_key' => $selects['driver_key'],
        ]);

        if ($union === '') {
            return [
                'revenue' => 0.0,
                'trips' => 0,
                'transactions' => 0,
                'avg_fare' => 0.0,
                'active_drivers' => 0,
            ];
        }

        $sql = "SELECT COALESCE(SUM(fare), 0) AS revenue,
                       COUNT(DISTINCT trip_key) AS trips,
                       COUNT(*) AS transactions,
                       COUNT(DISTINCT driver_key) AS active_drivers
                FROM $union t";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $revenue = (float) ($row['revenue'] ?? 0);
        $transactions = (int) ($row['transactions'] ?? 0);

        return [
            'revenue' => round($revenue, 2),
            'trips' => (int) ($row['trips'] ?? 0),
            'transactions' => $transactions,
            'avg_fare' => $transactions > 0 ? round($revenue / $transactions, 2) : 0.0,
            'active_drivers' => (int) ($row['active_drivers'] ?? 0),
        ];
    }

    private function peakHour(array $live, array $range, array $filters): ?array
    {
        if (empty($live) || !isset($live['fare'], $live['boarding_time'])) {
            return null;
        }

        $params = [];
        $where = $this->liveWhere($live, $range, $filters, 'p', $params);

        $sql = "SELECT HOUR(`boarding_time`) AS h, COALESCE(SUM(`fare`), 0) AS revenue
                FROM `passenger_trips`
                WHERE $where
                GROUP BY HOUR(`boarding_time`)
                ORDER BY revenue DESC
                LIMIT 1";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$row) {
            return null;
        }

        $hour = (int) $row['h'];
        $next = ($hour + 1) % 24;

        return [
            'hour' => $hour,
            'label' => sprintf('%02d:00 - %02d:00', $hour, $next),
            'revenue' => round((float) $row['revenue'], 2),
        ];
    }

    public function getKpis(array $filters, ?array $period = null): array
    {
        $period = $period ?? $this->resolvePeriod($filters);
        $live = $this->columns('passenger_trips');
        $history = $this->columns('passenger_trip_history');
        $range = ['from' => $period['from'], 'to' => $period['to']];

        $current = $this->aggregateKpis($live, $history, $range, $filters);
        $current['avg_revenue_per_trip'] = $current['trips'] > 0
            ? round($current['revenue'] / $current['trips'], 2)
            : 0.0;

        $previous = null;
        if (($period['previous_from'] ?? '') !== '' || ($period['previous_to'] ?? '') !== '') {
            $previousRange = ['from' => $period['previous_from'], 'to' => $period['previous_to']];
            $previousRaw = $this->aggregateKpis($live, $history, $previousRange, $filters);
            $previous = [
                'revenue' => $previousRaw['revenue'],
                'trips' => $previousRaw['trips'],
                'transactions' => $previousRaw['transactions'],
                'avg_fare' => $previousRaw['avg_fare'],
                'active_drivers' => $previousRaw['active_drivers'],
                'avg_revenue_per_trip' => $previousRaw['trips'] > 0
                    ? round($previousRaw['revenue'] / $previousRaw['trips'], 2)
                    : 0.0,
            ];
        }

        $current['peak_hour'] = $this->peakHour($live, $range, $filters);
        $current['previous'] = $previous;
        $current['available'] = !empty($live) || !empty($history);

        return $current;
    }

    public function getDrivers(array $filters, ?array $period = null): array
    {
        $period = $period ?? $this->resolvePeriod($filters);
        $live = $this->columns('passenger_trips');
        $history = $this->columns('passenger_trip_history');
        $range = ['from' => $period['from'], 'to' => $period['to']];

        $selects = $this->definedSelects($live, $history);
        [$union, $params] = $this->unionSql($live, $history, $range, $filters, [
            'driver_key' => $selects['driver_key'],
            'trip_key' => $selects['trip_key'],
        ]);

        $payload = [
            'drivers' => [],
            'totals' => ['revenue' => 0.0, 'trips' => 0, 'drivers' => 0, 'transactions' => 0, 'avg_fare' => 0.0],
            'top_n' => self::TOP_N,
            'cap' => self::LIST_CAP,
            'available' => !empty($live) || !empty($history),
        ];

        if ($union === '' || $selects['driver_key']['live'] === null && $selects['driver_key']['history'] === null) {
            return $payload;
        }

        $hasDrivers = $this->tableExists('drivers') && isset($this->columns('drivers')['driver_id']);
        $nameExpr = $hasDrivers
            ? 'COALESCE(MAX(d.`full_name`), MAX(t.`driver_key`))'
            : 'MAX(t.`driver_key`)';
        $join = $hasDrivers ? ' LEFT JOIN `drivers` d ON d.`driver_id` = t.`driver_key`' : '';

        $sql = "SELECT t.`driver_key` AS driver_id,
                       $nameExpr AS name,
                       COUNT(DISTINCT t.`trip_key`) AS trips,
                       COUNT(*) AS transactions,
                       COALESCE(SUM(t.`fare`), 0) AS revenue
                FROM $union t$join
                WHERE t.`driver_key` IS NOT NULL AND t.`driver_key` <> ''
                GROUP BY t.`driver_key`
                ORDER BY revenue DESC
                LIMIT " . self::LIST_CAP;

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $rows = [];
        }

        $drivers = [];
        $totalRevenue = 0.0;
        $totalTrips = 0;
        $totalTransactions = 0;

        foreach ($rows as $row) {
            $revenue = round((float) ($row['revenue'] ?? 0), 2);
            $trips = (int) ($row['trips'] ?? 0);
            $transactions = (int) ($row['transactions'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = (string) ($row['driver_id'] ?? '');
            }

            $drivers[] = [
                'driver_id' => (string) ($row['driver_id'] ?? ''),
                'name' => $name,
                'revenue' => $revenue,
                'trips' => $trips,
                'transactions' => $transactions,
                'avg_fare' => $transactions > 0 ? round($revenue / $transactions, 2) : 0.0,
                'revenue_per_trip' => $trips > 0 ? round($revenue / $trips, 2) : 0.0,
            ];

            $totalRevenue += $revenue;
            $totalTrips += $trips;
            $totalTransactions += $transactions;
        }

        $count = count($drivers);
        foreach ($drivers as $index => $driver) {
            $drivers[$index]['contribution'] = $totalRevenue > 0
                ? round(($driver['revenue'] / $totalRevenue) * 100, 2)
                : 0.0;
        }

        $payload['drivers'] = $drivers;
        $payload['totals'] = [
            'revenue' => round($totalRevenue, 2),
            'trips' => $totalTrips,
            'drivers' => $count,
            'transactions' => $totalTransactions,
            'avg_fare' => $totalTransactions > 0 ? round($totalRevenue / $totalTransactions, 2) : 0.0,
            'revenue_per_trip' => $totalTrips > 0 ? round($totalRevenue / $totalTrips, 2) : 0.0,
        ];

        return $payload;
    }

    public function getRoute(array $filters, ?array $period = null): array
    {
        $period = $period ?? $this->resolvePeriod($filters);
        $live = $this->columns('passenger_trips');
        $history = $this->columns('passenger_trip_history');
        $range = ['from' => $period['from'], 'to' => $period['to']];

        $selects = $this->definedSelects($live, $history);
        [$union, $params] = $this->unionSql($live, $history, $range, $filters, [
            'trip_key' => $selects['trip_key'],
            'boarding_point' => $selects['boarding_point'],
            'destination' => $selects['destination'],
        ]);

        $payload = [
            'routes' => [],
            'hourly' => [],
            'dow' => [],
            'available' => !empty($live) || !empty($history),
        ];

        $hasRouteDims = ($selects['boarding_point']['live'] !== null || $selects['boarding_point']['history'] !== null)
            && ($selects['destination']['live'] !== null || $selects['destination']['history'] !== null);

        if ($union !== '' && $hasRouteDims) {
            $sql = "SELECT t.`boarding_point` AS boarding_point,
                           t.`destination` AS destination,
                           COUNT(DISTINCT t.`trip_key`) AS trips,
                           COUNT(*) AS transactions,
                           COALESCE(SUM(t.`fare`), 0) AS revenue
                    FROM $union t
                    WHERE t.`boarding_point` IS NOT NULL
                      AND t.`destination` IS NOT NULL
                      AND (t.`boarding_point` <> '' OR t.`destination` <> '')
                    GROUP BY t.`boarding_point`, t.`destination`
                    ORDER BY revenue DESC
                    LIMIT " . self::LIST_CAP;

            try {
                $stmt = $this->db()->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $rows = [];
            }

            foreach ($rows as $row) {
                $revenue = round((float) ($row['revenue'] ?? 0), 2);
                $trips = (int) ($row['trips'] ?? 0);
                $transactions = (int) ($row['transactions'] ?? 0);
                $from = trim((string) ($row['boarding_point'] ?? ''));
                $to = trim((string) ($row['destination'] ?? ''));
                $label = ($from !== '' ? $from : 'Unknown') . ' → ' . ($to !== '' ? $to : 'Unknown');

                $payload['routes'][] = [
                    'from' => $from,
                    'to' => $to,
                    'label' => $label,
                    'revenue' => $revenue,
                    'trips' => $trips,
                    'transactions' => $transactions,
                    'passengers' => $transactions,
                    'avg_fare' => $transactions > 0 ? round($revenue / $transactions, 2) : 0.0,
                    'revenue_per_trip' => $trips > 0 ? round($revenue / $trips, 2) : 0.0,
                ];
            }
        }

        $hourlyRows = $this->bucketSeries($live, [], $range, $filters, 'HOUR(`boarding_time`)', null);
        foreach ($hourlyRows as $row) {
            $hour = (int) $row['k'];
            $payload['hourly'][] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'revenue' => round((float) $row['revenue'], 2),
            ];
        }

        $dowRows = $this->bucketSeries(
            $live,
            $history,
            $range,
            $filters,
            'DAYOFWEEK(`boarding_time`)',
            'DAYOFWEEK(`trip_date`)'
        );
        $payload['dow'] = $this->formatDow($dowRows);

        return $payload;
    }

    private function formatDow(array $rows): array
    {
        $names = [1 => 'Sun', 2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat'];
        $series = [];

        foreach ($rows as $row) {
            $day = (int) $row['k'];
            if (!isset($names[$day])) {
                continue;
            }
            $series[] = [
                'day' => $day,
                'label' => $names[$day],
                'revenue' => round((float) $row['revenue'], 2),
            ];
        }

        return $series;
    }

    public function getLocation(array $filters, ?array $period = null): array
    {
        $period = $period ?? $this->resolvePeriod($filters);
        $live = $this->columns('passenger_trips');
        $history = $this->columns('passenger_trip_history');
        $range = ['from' => $period['from'], 'to' => $period['to']];

        $selects = $this->definedSelects($live, $history);
        [$union, $params] = $this->unionSql($live, $history, $range, $filters, [
            'boarding_point' => $selects['boarding_point'],
        ]);

        $payload = [
            'stations' => [],
            'available' => !empty($live) || !empty($history),
        ];

        if ($union === '' || ($selects['boarding_point']['live'] === null && $selects['boarding_point']['history'] === null)) {
            return $payload;
        }

        $sql = "SELECT t.`boarding_point` AS boarding_point,
                       COUNT(*) AS passengers,
                       COALESCE(SUM(t.`fare`), 0) AS revenue
                FROM $union t
                WHERE t.`boarding_point` IS NOT NULL AND t.`boarding_point` <> ''
                GROUP BY t.`boarding_point`
                ORDER BY revenue DESC
                LIMIT " . self::LIST_CAP;

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $rows = [];
        }

        foreach ($rows as $row) {
            $revenue = round((float) ($row['revenue'] ?? 0), 2);
            $passengers = (int) ($row['passengers'] ?? 0);
            $payload['stations'][] = [
                'name' => trim((string) $row['boarding_point']),
                'revenue' => $revenue,
                'passengers' => $passengers,
                'avg_fare' => $passengers > 0 ? round($revenue / $passengers, 2) : 0.0,
            ];
        }

        return $payload;
    }

    public function getTime(array $filters, ?array $period = null): array
    {
        $period = $period ?? $this->resolvePeriod($filters);
        $live = $this->columns('passenger_trips');
        $history = $this->columns('passenger_trip_history');
        $range = ['from' => $period['from'], 'to' => $period['to']];

        $payload = [
            'hourly' => [],
            'dow' => [],
            'weekly' => [],
            'monthly' => [],
            'peak' => ['morning' => 0.0, 'afternoon' => 0.0, 'evening' => 0.0],
            'highest_day' => null,
            'growth' => null,
            'available' => !empty($live) || !empty($history),
        ];

        $hourlyRows = $this->bucketSeries($live, [], $range, $filters, 'HOUR(`boarding_time`)', null);
        foreach ($hourlyRows as $row) {
            $hour = (int) $row['k'];
            $revenue = round((float) $row['revenue'], 2);
            $payload['hourly'][] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'revenue' => $revenue,
            ];
            if ($hour < 12) {
                $payload['peak']['morning'] += $revenue;
            } elseif ($hour < 18) {
                $payload['peak']['afternoon'] += $revenue;
            } else {
                $payload['peak']['evening'] += $revenue;
            }
        }
        $payload['peak'] = array_map(static fn ($value) => round($value, 2), $payload['peak']);

        $dowRows = $this->bucketSeries(
            $live,
            $history,
            $range,
            $filters,
            'DAYOFWEEK(`boarding_time`)',
            'DAYOFWEEK(`trip_date`)'
        );
        $payload['dow'] = $this->formatDow($dowRows);

        foreach ($payload['dow'] as $row) {
            if ($payload['highest_day'] === null || $row['revenue'] > $payload['highest_day']['revenue']) {
                $payload['highest_day'] = ['label' => $row['label'], 'revenue' => $row['revenue']];
            }
        }

        $weeklyRows = $this->bucketSeries(
            $live,
            $history,
            $range,
            $filters,
            "DATE_FORMAT(DATE_SUB(DATE(`boarding_time`), INTERVAL WEEKDAY(`boarding_time`) DAY), '%Y-%m-%d')",
            "DATE_FORMAT(DATE_SUB(`trip_date`, INTERVAL WEEKDAY(`trip_date`) DAY), '%Y-%m-%d')"
        );
        foreach ($weeklyRows as $row) {
            $key = (string) $row['k'];
            $payload['weekly'][] = [
                'key' => $key,
                'label' => date('M d', strtotime($key)),
                'revenue' => round((float) $row['revenue'], 2),
            ];
        }

        $monthlyRows = $this->bucketSeries(
            $live,
            $history,
            $range,
            $filters,
            "DATE_FORMAT(`boarding_time`, '%Y-%m')",
            "DATE_FORMAT(`trip_date`, '%Y-%m')"
        );
        foreach ($monthlyRows as $row) {
            $key = (string) $row['k'];
            $payload['monthly'][] = [
                'key' => $key,
                'label' => date('M Y', strtotime($key . '-01')),
                'revenue' => round((float) $row['revenue'], 2),
            ];
        }

        if (($period['previous_from'] ?? '') !== '' || ($period['previous_to'] ?? '') !== '') {
            $currentTotal = 0.0;
            foreach ($payload['monthly'] as $row) {
                $currentTotal += $row['revenue'];
            }
            if ($currentTotal <= 0) {
                foreach ($payload['weekly'] as $row) {
                    $currentTotal += $row['revenue'];
                }
            }

            $previousRaw = $this->aggregateKpis($live, $history, [
                'from' => $period['previous_from'],
                'to' => $period['previous_to'],
            ], $filters);

            $previousTotal = $previousRaw['revenue'];
            $payload['growth'] = [
                'current' => round($currentTotal, 2),
                'previous' => $previousTotal,
                'percent' => $previousTotal > 0
                    ? round((($currentTotal - $previousTotal) / $previousTotal) * 100, 2)
                    : null,
            ];
        }

        return $payload;
    }

    public function getSection(string $section, array $filters, ?array $period = null): array
    {
        switch (strtolower(trim($section))) {
            case 'kpis':
                return $this->getKpis($filters, $period);
            case 'drivers':
                return $this->getDrivers($filters, $period);
            case 'route':
                return $this->getRoute($filters, $period);
            case 'location':
                return $this->getLocation($filters, $period);
            case 'time':
                return $this->getTime($filters, $period);
            default:
                return [];
        }
    }

    public function ensureIndexes(): void
    {
        $candidates = [
            ['passenger_trips', ['boarding_time'], 'idx_ro_pt_boarding_time'],
            ['passenger_trips', ['payment_status'], 'idx_ro_pt_payment_status'],
            ['passenger_trips', ['driver_id', 'boarding_time'], 'idx_ro_pt_driver_time'],
            ['passenger_trips', ['trip_no'], 'idx_ro_pt_trip'],
            ['passenger_trip_history', ['driver_id', 'trip_date'], 'idx_ro_pth_driver_date'],
            ['passenger_trip_history', ['trip_id'], 'idx_ro_pth_trip'],
        ];

        $existing = [];

        try {
            $tables = array_values(array_unique(array_column($candidates, 0)));
            $in = implode(', ', array_fill(0, count($tables), '?'));
            $stmt = $this->db()->prepare(
                "SELECT TABLE_NAME, COLUMN_NAME
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME IN ($in)
                   AND SEQ_IN_INDEX = 1"
            );
            $stmt->execute($tables);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existing[strtolower((string) $row['TABLE_NAME'])][strtolower((string) $row['COLUMN_NAME'])] = true;
            }
        } catch (\Throwable $e) {
            return;
        }

        foreach ($candidates as [$table, $cols, $name]) {
            $columnMap = $this->columns($table);
            if (empty($columnMap)) {
                continue;
            }

            $missingColumn = false;
            foreach ($cols as $column) {
                if (!isset($columnMap[strtolower($column)])) {
                    $missingColumn = true;
                    break;
                }
            }
            if ($missingColumn) {
                continue;
            }

            $first = strtolower($cols[0]);
            if (!empty($existing[strtolower($table)][$first])) {
                continue;
            }

            $colSql = implode('`, `', $cols);

            try {
                $this->db()->exec("ALTER TABLE `$table` ADD INDEX `$name` (`$colSql`)");
                $existing[strtolower($table)][$first] = true;
            } catch (\Throwable $e) {
            }
        }
    }
}

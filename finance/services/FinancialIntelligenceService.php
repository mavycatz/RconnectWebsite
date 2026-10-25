<?php
declare(strict_types=1);

namespace Finance\Services;

use App\Core\Database;
use App\Core\FinancialCosts;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../../app/Core/FinancialCosts.php';

/**
 * Read-only analytics for the Financial Intelligence & Analysis page.
 *
 * All amounts are collected in cents and rounded once at the edges. Revenue
 * inclusion, archived-row exclusion, fuel-source precedence and receipt
 * de-duplication follow FinancialPerformanceService so this page reconciles
 * with the Financial Reports totals. Remittances and bus-trip revenue
 * summaries are settlement records, never additional income.
 *
 * The authoritative data-source map for every section is maintained in
 * {@see self::dataSourceMap()} and cited by each section payload's
 * `data_sources` key.
 */
final class FinancialIntelligenceService
{
    private const MONTHLY = 'monthly';
    private const WEEKLY = 'weekly';
    private const TODAY = 'today';
    private const YESTERDAY = 'yesterday';
    private const CUSTOM = 'custom';

    /**
     * Route labels excluded from route charts, tables and filters. Trips that
     * resolve only to these labels stay in the period totals and are reported
     * as unattributed instead of forming a route row.
     */
    private const EXCLUDED_ROUTES = [
        'Cabanatuan Central Terminal - Rizal / Poblacion Terminal',
    ];

    /** Percentage deviation from the trailing average that flags an unusual fuel price. */
    private const FUEL_PRICE_DEVIATION_PCT = 30.0;
    /** Percentage change versus the previous window that flags a material expense move. */
    private const EXPENSE_CHANGE_PCT = 25.0;
    /** Absolute peso change below which an expense move is not reported. */
    private const EXPENSE_CHANGE_FLOOR = 100.0;
    /** Days of purchases used for the trailing fuel-price baseline. */
    private const FUEL_TRAILING_DAYS = 90;

    private ?PDO $db;
    /** @var array<string, array<string, bool>> */
    private array $columnCache = [];
    /** @var array<string, bool> */
    private array $tableCache = [];
    /** @var list<string> */
    private array $notes = [];
    /** @var array<string, array<string, mixed>> */
    private array $collectCache = [];
    /** @var array<string, array<string, mixed>> */
    private array $tripIndexCache = [];
    /** @var array<string, array{bus: string, route: string, excluded: bool}>|null */
    private ?array $tripByIdCache = null;
    /** @var list<array<string, string>> */
    private array $sourceErrors = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::tryGet();
    }

    private function db(): PDO
    {
        if ($this->db === null) {
            throw new \RuntimeException('Database connection unavailable.');
        }

        return $this->db;
    }

    /* =========================================================
       SCHEMA INTROSPECTION (MySQL + SQLite safe)
    ========================================================= */

    /** @return array<string, bool> */
    private function columns(string $table): array
    {
        $key = strtolower($table);
        if (isset($this->columnCache[$key])) {
            return $this->columnCache[$key];
        }
        $cols = [];
        try {
            $stmt = $this->db()->query("SHOW COLUMNS FROM `$table`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols[strtolower((string) ($row['Field'] ?? ''))] = true;
            }
        } catch (Throwable $e) {
            try {
                $stmt = $this->db()->query("PRAGMA table_info(`$table`)");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols[strtolower((string) ($row['name'] ?? ''))] = true;
                }
            } catch (Throwable $e2) {
                $cols = [];
            }
        }
        unset($cols['']);
        $this->tableCache[$key] = $cols !== [];
        return $this->columnCache[$key] = $cols;
    }

    private function tableExists(string $table): bool
    {
        $key = strtolower($table);
        if (!isset($this->tableCache[$key])) {
            $this->columns($table);
        }
        return $this->tableCache[$key] ?? false;
    }

    private function has(string $table, string $column): bool
    {
        return isset($this->columns($table)[strtolower($column)]);
    }

    private function firstColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $name) {
            if ($this->has($table, $name)) {
                return $name;
            }
        }
        return null;
    }

    /** @return list<array<string, mixed>> */
    private function fetch(string $sql, array $params = [], string $note = '', bool $critical = false): array
    {
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $this->recordSourceError($sql, $note, $e);
            if ($critical) {
                throw new RuntimeException('A required data source could not be read.');
            }
            return [];
        }
    }

    /**
     * Iterate a query row by row without buffering the whole result set, so
     * wide windows (semi-annual, annual) stream large fare tables instead of
     * materializing every raw row before normalization. Falls back to a
     * buffered statement when the driver cannot disable buffering.
     */
    private function stream(string $sql, array $params, callable $onRow, string $note = '', bool $critical = false): void
    {
        $bufferAttr = null;
        if (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY')) {
            $bufferAttr = \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY;
        } elseif (PHP_VERSION_ID < 80500 && defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $bufferAttr = PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
        }

        /* Unbuffered streaming is a connection-level MySQL attribute; setting
           it on the statement silently fails and buffers the whole result set,
           which exhausted memory on annual windows. Toggle it on the
           connection and restore the previous value afterwards. */
        $pdo = $this->db();
        $previousBuffer = null;
        if ($bufferAttr !== null) {
            try {
                $previousBuffer = $pdo->getAttribute($bufferAttr);
                if ($previousBuffer) {
                    $pdo->setAttribute($bufferAttr, false);
                }
            } catch (Throwable $e) {
                $bufferAttr = null;
                $previousBuffer = null;
            }
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $onRow($row);
            }
            $stmt->closeCursor();
        } catch (Throwable $e) {
            $this->recordSourceError($sql, $note, $e);
            if ($critical) {
                throw new RuntimeException('A required data source could not be read.');
            }
        } finally {
            if ($bufferAttr !== null && $previousBuffer !== null) {
                try {
                    $pdo->setAttribute($bufferAttr, $previousBuffer);
                } catch (Throwable $e) {
                    // Restoring the attribute is best-effort.
                }
            }
        }
    }

    /**
     * Query failures stay visible: the affected source is recorded so section
     * payloads can flag partial data instead of presenting an empty dataset.
     * The recorded message is generic; the underlying driver message is only
     * logged server-side.
     */
    private function recordSourceError(string $sql, string $note, Throwable $error): void
    {
        $source = 'dataset';
        if (preg_match('/\bFROM\s+`?([a-z0-9_]+)`?/i', $sql, $matches) === 1) {
            $source = strtolower($matches[1]);
        }
        $operation = $note !== '' ? $note : 'read ' . $source;
        foreach ($this->sourceErrors as $existing) {
            if (($existing['source'] ?? '') === $source && ($existing['operation'] ?? '') === $operation) {
                return;
            }
        }
        $this->sourceErrors[] = [
            'source' => $source,
            'operation' => $operation,
            'message' => 'The ' . $source . ' source could not be read for this request.',
        ];
        if ($note !== '') {
            $this->notes[] = $note;
        }
        error_log('financial intelligence source failure (' . $source . '): ' . $error->getMessage());
    }

    /** @return array{partial: bool, source_errors: list<array<string, string>>} */
    private function errorSummary(): array
    {
        return [
            'partial' => $this->sourceErrors !== [],
            'source_errors' => array_values($this->sourceErrors),
        ];
    }

    private function note(string $message): void
    {
        if (!in_array($message, $this->notes, true)) {
            $this->notes[] = $message;
        }
    }

    /**
     * Filter scope disclosure: which active filters cannot apply to a data
     * group because its records do not carry that dimension. The UI shows
     * these alongside the affected metrics instead of silently ignoring the
     * filter.
     *
     * @param list<string> $groups Data groups in scope: revenue, expenses,
     *   fuel, configured, remittance, forecast, pricing.
     */
    private function scopeMetadata(array $filters, array $groups): array
    {
        $notApplicable = [];
        $passengerType = (string) ($filters['passenger_type'] ?? '');
        $nonPassengerGroups = array_intersect($groups, ['expenses', 'fuel', 'configured', 'remittance', 'forecast']);
        if ($passengerType !== '' && $nonPassengerGroups !== []) {
            $labels = [
                'expenses' => 'recorded expenses',
                'fuel' => 'fuel costs',
                'configured' => 'configured costs',
                'remittance' => 'remittance settlements',
                'forecast' => 'the forecast baseline',
            ];
            $affected = [];
            foreach ($nonPassengerGroups as $group) {
                $affected[] = $labels[$group] ?? $group;
            }
            $notApplicable[] = [
                'filter' => 'passenger_type',
                'value' => $passengerType,
                'message' => 'The passenger-type filter applies to fare revenue and passenger metrics; '
                    . implode(', ', $affected) . ' are not passenger-specific and remain in full.',
            ];
        }

        return [
            'filters' => [
                'period' => (string) ($filters['period'] ?? ''),
                'year' => (int) ($filters['year'] ?? 0),
                'route' => (string) ($filters['route'] ?? ''),
                'bus' => (string) ($filters['bus'] ?? ''),
                'passenger_type' => $passengerType,
            ],
            'not_applicable' => $notApplicable,
        ];
    }

    /* =========================================================
       FILTERS
    ========================================================= */

    /**
     * Resolve page filters to inclusive Y-m-d bounds plus a comparable
     * previous window. Periods: today, yesterday, weekly, monthly, q1-q4,
     * h1, h2, yearly (selected year against the previous year), custom.
     * Legacy links using `yoy` are treated as yearly so a selected year is
     * always compared against the year immediately before it.
     *
     * @return array{period: string, label: string, from: string, to: string,
     *   days: int, previous_from: string, previous_to: string,
     *   comparison_label: string, year: int, quarter: int,
     *   route: string, bus: string, passenger_type: string}
     */
    public static function resolveFilters(array $input, ?DateTimeImmutable $today = null): array
    {
        $today = $today ?? new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
        $yearInput = trim((string) ($input['year'] ?? ''));
        $year = ctype_digit($yearInput) ? (int) $yearInput : (int) $today->format('Y');

        $raw = strtolower(trim((string) ($input['period'] ?? '')));
        $aliases = [
            'daily' => self::TODAY,
            'week' => self::WEEKLY,
            'this_week' => self::WEEKLY,
            'month' => self::MONTHLY,
            'this_month' => self::MONTHLY,
            'quarterly' => 'quarter',
            'quarter' => 'quarter',
            'half' => 'half',
            'semi_annually' => 'half',
            'semiannually' => 'half',
            'first_half' => 'h1',
            'second_half' => 'h2',
            'annually' => 'yearly',
            'annual' => 'yearly',
            'year' => 'yearly',
            'this_year' => 'yearly',
            'full_year' => 'yearly',
            'yoy' => 'yearly',
            'year_over_year' => 'yearly',
            'previous_year' => 'yearly',
            'last_year' => 'yearly',
        ];
        $period = $aliases[$raw] ?? $raw;
        if ($period === '') {
            $period = ($input['from'] ?? '') !== '' || ($input['to'] ?? '') !== '' ? self::CUSTOM : self::TODAY;
        }

        $quarter = (int) ($input['quarter'] ?? 0);
        if ($period === 'q1' || $period === 'q2' || $period === 'q3' || $period === 'q4') {
            $quarter = (int) substr($period, 1);
            $period = 'quarter';
        }
        if ($period === 'half') {
            $halfInput = strtolower(trim((string) ($input['half'] ?? '')));
            $period = $halfInput === '2' || $halfInput === 'h2' ? 'h2' : 'h1';
        }
        if ($period === 'quarter' && $quarter < 1) {
            $quarter = (int) ceil((int) $today->format('n') / 3);
        }
        if ($quarter < 1 || $quarter > 4) {
            $quarter = (int) ceil((int) $today->format('n') / 3);
        }

        switch ($period) {
            case self::TODAY:
                $start = $today;
                $end = $today;
                $label = 'Today · ' . $today->format('F j, Y');
                break;
            case self::YESTERDAY:
                $day = $today->modify('-1 day');
                $start = $day;
                $end = $day;
                $label = 'Yesterday · ' . $day->format('F j, Y');
                break;
            case self::WEEKLY:
                $start = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
                $end = $start->modify('+6 days');
                $label = 'Weekly · ' . $start->format('M j') . ' – ' . $end->format('M j, Y');
                break;
            case self::MONTHLY:
                $start = $today->setDate((int) $today->format('Y'), (int) $today->format('n'), 1);
                $end = $start->modify('last day of this month');
                $label = 'Monthly · ' . $start->format('F Y');
                break;
            case 'quarter':
                $firstMonth = ($quarter - 1) * 3 + 1;
                $start = (new DateTimeImmutable($today->format('Y') . '-01-01'))->setDate($year, $firstMonth, 1);
                $end = $start->modify('+2 months')->modify('last day of this month');
                $label = 'Q' . $quarter . ' ' . $year . ' · ' . $start->format('M j') . ' – ' . $end->format('M j, Y');
                break;
            case 'h1':
                $start = (new DateTimeImmutable("$year-01-01"));
                $end = (new DateTimeImmutable("$year-06-30"));
                $label = 'H1 ' . $year . ' · Jan 1 – Jun 30, ' . $year;
                break;
            case 'h2':
                $start = (new DateTimeImmutable("$year-07-01"));
                $end = (new DateTimeImmutable("$year-12-31"));
                $label = 'H2 ' . $year . ' · Jul 1 – Dec 31, ' . $year;
                break;
            case 'yearly':
                $start = new DateTimeImmutable("$year-01-01");
                $end = new DateTimeImmutable("$year-12-31");
                $label = $year . ' vs ' . ($year - 1);
                break;
            case self::CUSTOM:
            default:
                $period = self::CUSTOM;
                $start = self::parseDate((string) ($input['from'] ?? ''));
                $end = self::parseDate((string) ($input['to'] ?? ''));
                if ($start === null || $end === null) {
                    throw new \InvalidArgumentException('Choose valid start and end dates for the custom range.');
                }
                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }
                $label = 'Custom Range · ' . $start->format('M j, Y') . ' – ' . $end->format('M j, Y');
                break;
        }

        $days = (int) $start->diff($end)->days + 1;

        /* Year governs the window only for the calendar periods selected by the
           year control (quarter, halves, full year). For date-window periods
           (today, yesterday, weekly, monthly, custom) the window comes from
           the current date and the year is reported as the window's own year
           so the payload never disagrees with the dates. */
        if (in_array($period, [self::TODAY, self::YESTERDAY, self::WEEKLY, self::MONTHLY, self::CUSTOM], true)) {
            $year = (int) $start->format('Y');
        }

        $previousFrom = '';
        $previousTo = '';
        switch ($period) {
            case self::TODAY:
                $previousFrom = $previousTo = $start->modify('-1 day')->format('Y-m-d');
                break;
            case self::YESTERDAY:
                $previousFrom = $previousTo = $start->modify('-1 day')->format('Y-m-d');
                break;
            case self::WEEKLY:
                $previousFrom = $start->modify('-7 days')->format('Y-m-d');
                $previousTo = $start->modify('-1 day')->format('Y-m-d');
                break;
            case self::MONTHLY:
                $previousFrom = $start->modify('-1 month')->format('Y-m-01');
                $previousTo = $start->modify('-1 month')->format('Y-m-t');
                break;
            case 'quarter':
                $previousFrom = $start->modify('-3 months')->format('Y-m-d');
                $previousTo = $start->modify('-1 day')->format('Y-m-d');
                break;
            case 'h1':
                $previousFrom = ($year - 1) . '-07-01';
                $previousTo = ($year - 1) . '-12-31';
                break;
            case 'h2':
                $previousFrom = "$year-01-01";
                $previousTo = "$year-06-30";
                break;
            case 'yearly':
                $previousFrom = ($start->format('Y') - 1) . '-01-01';
                $previousTo = ($start->format('Y') - 1) . '-12-31';
                break;
            case self::CUSTOM:
                $previousTo = $start->modify('-1 day')->format('Y-m-d');
                $previousFrom = $start->modify('-' . $days . ' days')->format('Y-m-d');
                break;
        }

        $passengerType = trim((string) ($input['passenger_type'] ?? ''));
        if ($passengerType !== '') {
            $passengerType = strtolower($passengerType) === 'baggage'
                ? 'Baggage'
                : self::normalizePassengerType($passengerType);
        }

        return [
            'period' => $period,
            'label' => $label,
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
            'days' => $days,
            'previous_from' => $previousFrom,
            'previous_to' => $previousTo,
            'comparison_label' => 'vs ' . $previousFrom . ' – ' . $previousTo,
            'year' => $year,
            'quarter' => $period === 'quarter' ? $quarter : 0,
            'route' => trim((string) ($input['route'] ?? '')),
            'bus' => trim((string) ($input['bus'] ?? '')),
            'passenger_type' => $passengerType,
        ];
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    private static function normalizePassengerType(?string $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if ($value === '' || in_array($value, ['regular', 'normal', 'adult', 'passenger', 'none'], true)) {
            return 'Regular';
        }
        if (str_contains($value, 'student')) {
            return 'Student';
        }
        if (str_contains($value, 'senior')) {
            return 'Senior Citizen';
        }
        if (str_contains($value, 'pwd') || str_contains($value, 'disab')) {
            return 'PWD';
        }
        if (str_contains($value, 'teacher') || str_contains($value, 'nurse') || str_contains($value, 'special') || str_contains($value, 'voucher')) {
            return 'Special';
        }
        if (str_contains($value, 'cooperative') || str_contains($value, 'co-op') || str_contains($value, 'coop')) {
            return 'Cooperative';
        }
        if (str_contains($value, 'beneficiary') || str_contains($value, '4ps') || str_contains($value, '4 ps')) {
            return 'Beneficiary';
        }
        return 'Other';
    }

    /**
     * The first non-empty value, used to prefer a trip's recorded passenger
     * type and fall back to the linked account's discount type.
     */
    private static function firstFilledString(...$values): ?string
    {
        foreach ($values as $value) {
            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    /** Fare column used by fare-engine for a passenger type. */
    private static function fareColumnFor(string $type): string
    {
        if ($type === 'Student' || $type === 'Senior Citizen' || $type === 'PWD') {
            return 'student_fare';
        }
        if ($type === 'Special') {
            return 'special_fare';
        }
        return 'regular_fare';
    }

    private static function isFuelCategory(string $category): bool
    {
        return preg_match('/fuel|diesel|gasoline|petrol|^gas$/i', trim($category)) === 1;
    }

    /** Split a route label into endpoint parts across common separators. */
    private static function splitRouteParts(string $name): array
    {
        $normalized = (string) preg_replace('/\s*(?:–|—|→|->|\s+to\s+|\s*-\s*)\s*/iu', ' - ', $name);
        $parts = preg_split('/\s+-\s+/', $normalized) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim((string) preg_replace('/\s+/', ' ', $part));
            if ($part !== '') {
                $out[] = $part;
            }
        }
        return $out;
    }

    /** Display an endpoint, title-casing uniformly cased names. */
    private static function displayEndpoint(string $endpoint): string
    {
        $endpoint = trim((string) preg_replace('/\s+/', ' ', $endpoint));
        if ($endpoint === '') {
            return '';
        }
        if (mb_strtoupper($endpoint) === $endpoint || mb_strtolower($endpoint) === $endpoint) {
            return mb_convert_case(mb_strtolower($endpoint), MB_CASE_TITLE);
        }
        return $endpoint;
    }

    /**
     * Direction-preserving key for a route: endpoints in recorded order,
     * lowercased, separators normalized, so "Cabanatuan-Rizal",
     * "Cabanatuan - Rizal" and "CABANATUAN TO RIZAL" share a key while
     * "Rizal-Cabanatuan" keeps its own.
     */
    private static function routeKey(?string $route): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $route));
        if ($name === '') {
            return '';
        }
        $parts = self::splitRouteParts($name);
        if ($parts === []) {
            return '';
        }
        return implode('|', array_map(static fn(string $part): string => strtolower($part), $parts));
    }

    /** Direction-insensitive key (endpoints sorted) used for exclusions. */
    private static function routeKeyUnordered(?string $route): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $route));
        if ($name === '') {
            return '';
        }
        $parts = self::splitRouteParts($name);
        if ($parts === []) {
            return '';
        }
        $parts = array_map(static fn(string $part): string => strtolower($part), $parts);
        sort($parts);
        return implode('|', $parts);
    }

    private static function isExcludedRoute(string $name): bool
    {
        $key = self::routeKeyUnordered($name);
        foreach (self::EXCLUDED_ROUTES as $excluded) {
            if (strcasecmp(trim($name), trim($excluded)) === 0) {
                return true;
            }
            if ($key !== '' && $key === self::routeKeyUnordered($excluded)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Canonical route label for analysis. Each recorded direction keeps its
     * own label, normalized consistently, for example "Cabanatuan-Rizal" and
     * "Cabanatuan to Rizal" become "Cabanatuan - Rizal", while
     * "Rizal-Cabanatuan" stays "Rizal - Cabanatuan".
     */
    private static function canonicalRouteName(?string $route): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $route));
        if ($name === '' || self::isExcludedRoute($name)) {
            return '';
        }
        $parts = self::splitRouteParts($name);
        if (count($parts) >= 2) {
            return implode(' - ', array_map(static fn(string $part): string => self::displayEndpoint($part), $parts));
        }
        return self::displayEndpoint($name);
    }

    private static function routesMatch(string $a, string $b): bool
    {
        if (strcasecmp(trim($a), trim($b)) === 0) {
            return true;
        }
        $keyA = self::routeKey($a);
        return $keyA !== '' && $keyA === self::routeKey($b);
    }

    /* =========================================================
       CONFIGURED MONTHLY COSTS (Financial Cost Settings)
    ========================================================= */

    /**
     * Active configured costs keyed by the fixed cost keys. Settings rows
     * override the shipped defaults; the Gas row is excluded because fuel is
     * already counted from actual purchases.
     *
     * @return array<string, array<string, mixed>>
     */
    private function configuredCostItems(): array
    {
        $defaults = FinancialCosts::DEFAULTS;
        $rows = [];
        if ($this->tableExists('financial_cost_settings')) {
            foreach ($this->fetch("SELECT cost_key, label, amount, rate, calc_method, status FROM financial_cost_settings WHERE status = 'Active'") as $row) {
                $key = strtolower(trim((string) ($row['cost_key'] ?? '')));
                if (isset($defaults[$key])) {
                    $rows[$key] = $row;
                }
            }
        }

        $items = [];
        foreach (FinancialCosts::FIXED_KEYS as $key) {
            $def = $defaults[$key];
            $row = $rows[$key] ?? null;
            $method = $row !== null ? strtolower(trim((string) ($row['calc_method'] ?? ''))) : '';
            $items[$key] = [
                'key' => $key,
                'label' => $row !== null && trim((string) ($row['label'] ?? '')) !== ''
                    ? trim((string) $row['label'])
                    : (string) $def['label'],
                'amount' => (float) ($row['amount'] ?? $def['amount']),
                'rate' => (float) ($row['rate'] ?? $def['rate']),
                'method' => $method !== '' ? $method : (string) $def['method'],
                'unit' => (string) $def['unit'],
            ];
        }
        return $items;
    }

    /**
     * Configured costs for the selected period: per-driver costs scaled to the
     * period length and the drivers recorded in it. Items already present in
     * the recorded expense categories are skipped so nothing is counted twice.
     *
     * @param array{drivers: array<string, array{gross_cents: int, trips: array<string, bool>}>, total_gross_cents: int} $basis
     * @param list<string> $recordedCategories
     */
    private function configuredCosts(array $filters, array $basis, array $recordedCategories = []): array
    {
        $settings = $this->configuredCostItems();
        $days = max(0, (int) $filters['days']);
        $months = $days > 0 ? $days / FinancialCosts::DAYS_PER_MONTH : 0.0;

        $drivers = $basis['drivers'] ?? [];
        $totalGross = ((int) ($basis['total_gross_cents'] ?? 0)) / 100;
        $driverCount = count($drivers);
        $totalTrips = 0;
        foreach ($drivers as $context) {
            $totalTrips += count($context['trips'] ?? []);
        }

        $recorded = [];
        foreach ($recordedCategories as $category) {
            $recorded[] = strtolower(trim((string) $category));
        }

        $itemRows = [];
        $skipped = [];
        $total = 0.0;
        $monthlyPerDriver = 0.0;
        foreach ($settings as $setting) {
            $label = (string) $setting['label'];
            $labelLower = strtolower($label);
            $alreadyPosted = false;
            foreach ($recorded as $category) {
                if ($category === '' || $labelLower === '') {
                    continue;
                }
                if (str_contains($category, $labelLower) || str_contains($labelLower, $category)) {
                    $alreadyPosted = true;
                    break;
                }
            }
            if ($alreadyPosted) {
                $skipped[] = $label;
                continue;
            }

            $method = (string) $setting['method'];
            $amount = (float) $setting['amount'];
            $monthlyBase = $method === 'fixed_daily'
                ? round($amount * FinancialCosts::DAYS_PER_MONTH, 2)
                : round($amount, 2);

            switch ($method) {
                case 'percent_gross':
                    $periodAmount = round($totalGross * ((float) $setting['rate'] / 100), 2);
                    break;
                case 'per_trip':
                    $periodAmount = round($amount * $totalTrips, 2);
                    break;
                case 'fixed_daily':
                case 'fixed_monthly':
                default:
                    $periodAmount = round($monthlyBase * $months * $driverCount, 2);
                    $monthlyPerDriver += $monthlyBase;
                    break;
            }

            $total += $periodAmount;
            $itemRows[] = [
                'key' => (string) $setting['key'],
                'label' => $label,
                'method' => $method,
                'unit' => (string) $setting['unit'],
                'per_driver_month' => $monthlyBase,
                'amount' => $periodAmount,
            ];
        }

        $note = 'Amortization, Insurance, GPS and OPTR are configured per driver per month in Financial Cost Settings and scaled to the period length and recorded drivers. The Gas setting is not added because fuel is already counted from actual purchases.';
        if ($skipped !== []) {
            $note .= ' Already posted in the expense ledger and therefore not added again: ' . implode(', ', $skipped) . '.';
        }
        if ($driverCount === 0) {
            $note .= ' No driver activity was recorded for this period, so configured costs were not applied.';
        }

        return [
            'available' => $driverCount > 0,
            'days' => $days,
            'months' => round($months, 2),
            'drivers' => $driverCount,
            'monthly_per_driver' => round($monthlyPerDriver, 2),
            'items' => $itemRows,
            'total' => round($total, 2),
            'skipped' => $skipped,
            'basis' => 'Per-driver configured costs scaled to ' . $days . ' day(s) and ' . $driverCount . ' driver(s) recorded in the period; per-trip items use distinct recorded trips.',
            'note' => $note,
        ];
    }

    private static function isMaintenanceCategory(string $category): bool
    {
        return preg_match('/maintenance|repair|parts|oil|tire|tyre/i', trim($category)) === 1;
    }

    /**
     * Compact configured-cost basis derived from the aggregation state: the
     * drivers recorded in the period with their gross fare and distinct trips.
     *
     * @param array<string, mixed> $state
     * @return array{drivers: array<string, array{gross_cents: int, trips: array<string, bool>}>, total_gross_cents: int}
     */
    private static function configuredBasis(array $state): array
    {
        return [
            'drivers' => $state['config_drivers'] ?? [],
            'total_gross_cents' => (int) ($state['config_total_gross_cents'] ?? 0),
        ];
    }

    /* =========================================================
       DIMENSION MAPS
    ========================================================= */

    /** @return array<string, string> raw bus identifier => canonical bus code */
    private function busAliases(): array
    {
        if (!$this->tableExists('buses')) {
            return [];
        }
        $code = $this->firstColumn('buses', ['bus_id', 'bus_no', 'bus_label', 'code']);
        if ($code === null) {
            return [];
        }
        $aliases = [];
        $select = "`$code` AS code" . ($this->has('buses', 'id') ? ', id' : '');
        foreach ($this->fetch("SELECT $select FROM buses") as $row) {
            $aliases[strtolower(trim((string) ($row['code'] ?? '')))] = (string) $row['code'];
            if (isset($row['id'])) {
                $aliases[strtolower(trim((string) $row['id']))] = (string) $row['code'];
            }
        }
        return $aliases;
    }

    /**
     * Exact recorded-trip lookup keyed by bus_trips.id, independent of the
     * reporting window. Archived CSV rows (ARCV-####) and some live rows
     * reference a trip whose recorded trip_date differs from the journey
     * date, so a window-scoped index misses them; the id link itself is exact
     * and was verified for 100% of ARCV rows.
     *
     * @return array<string, array{bus: string, route: string, excluded: bool}>
     */
    private function tripById(): array
    {
        if ($this->tripByIdCache !== null) {
            return $this->tripByIdCache;
        }
        $this->tripByIdCache = [];
        if (!$this->tableExists('bus_trips') || !$this->has('bus_trips', 'id')) {
            return $this->tripByIdCache;
        }
        $select = ['id'];
        foreach (['bus_id', 'route_name'] as $column) {
            if ($this->has('bus_trips', $column)) {
                $select[] = $column;
            }
        }
        foreach ($this->fetch('SELECT ' . implode(', ', $select) . ' FROM bus_trips') as $row) {
            $id = (string) (int) ($row['id'] ?? 0);
            if ($id === '0') {
                continue;
            }
            $rawRoute = trim((string) ($row['route_name'] ?? ''));
            $route = self::canonicalRouteName($rawRoute);
            $this->tripByIdCache[$id] = [
                'bus' => trim((string) ($row['bus_id'] ?? '')),
                'route' => $route,
                'excluded' => $rawRoute !== '' && $route === '',
            ];
        }
        return $this->tripByIdCache;
    }

    /**
     * Trip references in the window resolve to a unique bus/route when the
     * stored identifiers match; ambiguous matches are never guessed. Also
     * records, per bus and day, the set of routes so fuel receipts without a
     * trip reference can still be attributed when the bus ran one route.
     *
     * @return array{exact: array<string, array{bus: string, route: string}>,
     *   by_ref: array<string, array{bus: string, route: string}>,
     *   bus_routes: array<string, array<string, bool>>,
     *   driver_buses: array<string, array<string, bool>>,
     *   driver_routes: array<string, array<string, bool>>}
     */
    private function tripIndex(string $from, string $to): array
    {
        $cacheKey = $from . '|' . $to;
        if (isset($this->tripIndexCache[$cacheKey])) {
            return $this->tripIndexCache[$cacheKey];
        }
        $empty = ['exact' => [], 'by_ref' => [], 'bus_routes' => [], 'driver_buses' => [], 'driver_routes' => [], 'excluded_refs' => []];
        if (!$this->tableExists('bus_trips')) {
            return $this->tripIndexCache[$cacheKey] = $empty;
        }
        $date = $this->firstColumn('bus_trips', ['trip_date', 'created_at']);
        if ($date === null) {
            return $this->tripIndexCache[$cacheKey] = $empty;
        }
        $refs = array_values(array_filter(
            ['trip_no', 'trip_id', 'id'],
            fn(string $name): bool => $this->has('bus_trips', $name)
        ));
        if ($refs === []) {
            return $this->tripIndexCache[$cacheKey] = $empty;
        }
        $fields = array_values(array_filter(
            ['bus_id', 'driver_id', 'route_name'],
            fn(string $name): bool => $this->has('bus_trips', $name)
        ));
        $select = ["`$date` AS day"];
        foreach ($refs as $ref) {
            $select[] = "`$ref`";
        }
        foreach ($fields as $field) {
            $select[] = "`$field`";
        }
        /* One day of margin on each side so journeys boarding just after
           midnight still resolve to their operating trip and journeys that
           departed before midnight resolve on the next calendar day. */
        $indexFrom = (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
        $until = (new DateTimeImmutable($to))->modify('+2 day')->format('Y-m-d');
        $rows = $this->fetch(
            'SELECT ' . implode(', ', $select) . " FROM bus_trips WHERE `$date` >= :from AND `$date` < :until",
            [':from' => $indexFrom, ':until' => $until]
        );

        $exact = [];
        $byRef = [];
        $busRoutes = [];
        $driverBuses = [];
        $driverRoutes = [];
        $excludedRefs = [];
        foreach ($rows as $row) {
            $day = substr((string) ($row['day'] ?? ''), 0, 10);
            $driver = strtolower(trim((string) ($row['driver_id'] ?? '')));
            $bus = trim((string) ($row['bus_id'] ?? ''));
            $rawRoute = trim((string) ($row['route_name'] ?? ''));
            $route = self::canonicalRouteName($rawRoute);
            $isExcluded = $rawRoute !== '' && $route === '';
            if ($bus !== '' && $route !== '' && $day !== '') {
                $busRoutes[strtolower($bus) . '|' . $day][$route] = true;
            }
            if ($driver !== '' && $day !== '') {
                if ($bus !== '') {
                    $driverBuses[$driver . '|' . $day][$bus] = true;
                }
                if ($route !== '') {
                    $driverRoutes[$driver . '|' . $day][$route] = true;
                }
            }
            foreach ($refs as $ref) {
                $value = trim((string) ($row[$ref] ?? ''));
                if ($value === '') {
                    continue;
                }
                $key = $day . '|' . $driver . '|' . $value;
                $exact[$key] = self::accumulateUnique($exact[$key] ?? null, $bus, $route);
                $byRef[$value] = self::accumulateUnique($byRef[$value] ?? null, $bus, $route);
                if ($isExcluded) {
                    $excludedRefs[$value] = true;
                }
            }
        }

        return $this->tripIndexCache[$cacheKey] = [
            'exact' => self::resolveUniqueMap($exact),
            'by_ref' => self::resolveUniqueMap($byRef),
            'bus_routes' => $busRoutes,
            'driver_buses' => $this->uniqueValues($driverBuses),
            'driver_routes' => $this->uniqueValues($driverRoutes),
            'excluded_refs' => $excludedRefs,
        ];
    }

    /**
     * Accumulate one observation for a trip reference without storing nested
     * value sets: the first non-empty value wins, a later different value
     * marks the slot ambiguous (null), and an empty value is only kept while
     * no value has been seen. Keeps wide-window trip indexes small while
     * preserving "unique match or nothing" semantics.
     *
     * @param array{bus: string|null, route: string|null}|null $state
     * @return array{bus: string|null, route: string|null}
     */
    private static function accumulateUnique(?array $state, string $bus, string $route): array
    {
        if ($state === null) {
            $state = ['bus' => '', 'route' => ''];
        }
        foreach (['bus' => $bus, 'route' => $route] as $slot => $value) {
            if ($value === '' || $state[$slot] === null) {
                continue;
            }
            if ($state[$slot] === '') {
                $state[$slot] = $value;
            } elseif ($state[$slot] !== $value) {
                $state[$slot] = null;
            }
        }
        return $state;
    }

    /**
     * @param array<string, array{bus: string|null, route: string|null}> $map
     * @return array<string, array{bus: string, route: string}>
     */
    private static function resolveUniqueMap(array $map): array
    {
        $resolved = [];
        foreach ($map as $key => $state) {
            $resolved[$key] = [
                'bus' => $state['bus'] === null ? '' : (string) $state['bus'],
                'route' => $state['route'] === null ? '' : (string) $state['route'],
            ];
        }
        return $resolved;
    }

    /**
     * Collapse keyed value sets to a single value only when unambiguous.
     *
     * @param array<string, array<string, bool>> $sets
     * @return array<string, string>
     */
    private static function uniqueValues(array $sets): array
    {
        $out = [];
        foreach ($sets as $key => $values) {
            $names = array_keys($values);
            if (count($names) === 1) {
                $out[$key] = $names[0];
            }
        }
        return $out;
    }

    /**
     * Route for a bus on a recorded day, but only when the bus ran exactly
     * one canonical route that day. Ambiguous days stay unattributed.
     */
    private static function busRouteFor(array $tripIndex, string $bus, string $date): string
    {
        $bus = strtolower(trim($bus));
        $date = substr(trim($date), 0, 10);
        if ($bus === '' || $date === '') {
            return '';
        }
        $routes = $tripIndex['bus_routes'][$bus . '|' . $date] ?? [];
        $names = array_keys($routes);
        return count($names) === 1 ? $names[0] : '';
    }

    private static function driverBusFor(array $tripIndex, string $driver, string $date): string
    {
        $driver = strtolower(trim($driver));
        $date = substr(trim($date), 0, 10);
        if ($driver === '' || $date === '') {
            return '';
        }
        return (string) ($tripIndex['driver_buses'][$driver . '|' . $date] ?? '');
    }

    private static function driverRouteFor(array $tripIndex, string $driver, string $date): string
    {
        $driver = strtolower(trim($driver));
        $date = substr(trim($date), 0, 10);
        if ($driver === '' || $date === '') {
            return '';
        }
        return (string) ($tripIndex['driver_routes'][$driver . '|' . $date] ?? '');
    }

    private function resolveTripRoute(array $tripIndex, string $date, string $driver, string $trip): array
    {
        if ($trip === '') {
            return ['bus' => '', 'route' => '', 'excluded' => false];
        }
        $key = $date . '|' . strtolower(trim($driver)) . '|' . $trip;
        if (isset($tripIndex['exact'][$key])) {
            return self::withExcludedFlag($tripIndex, $trip, $tripIndex['exact'][$key]);
        }
        if (isset($tripIndex['by_ref'][$trip])) {
            return self::withExcludedFlag($tripIndex, $trip, $tripIndex['by_ref'][$trip]);
        }
        return ['bus' => '', 'route' => '', 'excluded' => isset($tripIndex['excluded_refs'][$trip])];
    }

    /* =========================================================
       RECORD COLLECTION
    ========================================================= */

    /**
     * @return list<array<string, mixed>>
     */
    private function revenueRows(array $filters): array
    {
        $rows = [];
        $this->streamRevenueRows($filters, static function (array $row) use (&$rows): void {
            /* identity only guards streaming de-duplication; it is not used by
               the aggregations and would otherwise be retained per row. */
            unset($row['identity'], $row['twin_key'], $row['source']);
            $rows[] = $row;
        });
        return $rows;
    }

    /**
     * Stream normalized revenue rows so large quarterly windows can aggregate
     * without holding every normalized row in memory. Each source is fetched,
     * normalized and released one at a time; live and archived records are
     * mutually exclusive (live excludes archived rows) and any duplicated
     * record identity within a request is emitted once.
     */
    private function streamRevenueRows(array $filters, callable $emit): void
    {
        $from = $filters['from'];
        $until = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        $tripIndex = $this->tripIndex($from, (string) $filters['to']);
        $busAliases = $this->busAliases();
        /* Built eagerly: while an unbuffered stream is open no further query
           may run on the connection, and the row normalizer needs this map. */
        $this->tripById();

        $seen = [];
        $liveTwinKeys = [];
        $emitOnce = static function (array $row) use (&$seen, &$liveTwinKeys, $emit): void {
            $source = (string) ($row['source'] ?? '');
            $twinKey = (string) ($row['twin_key'] ?? '');
            if ($source === 'passenger_trips' && $twinKey !== '') {
                $liveTwinKeys[$twinKey] = true;
            } elseif ($source === 'passenger_trip_history' && $twinKey !== '' && isset($liveTwinKeys[$twinKey])) {
                /* Archived twin of a live fare: counted once, through the live row. */
                return;
            }
            $identity = (string) ($row['identity'] ?? '');
            if ($identity !== '') {
                if (isset($seen[$identity])) {
                    return;
                }
                $seen[$identity] = true;
            }
            $emit($row);
        };

        // Live inspector-verified fares.
        if ($this->tableExists('passenger_trips')) {
            $date = $this->firstColumn('passenger_trips', ['boarding_time', 'created_at', 'verified_at']);
            if ($date !== null && $this->has('passenger_trips', 'fare') && $this->has('passenger_trips', 'payment_status')) {
                $select = ["r.`$date` AS report_date", 'r.fare AS report_amount'];
                foreach (['id', 'bus_id', 'bus_no', 'driver_id', 'trip_no', 'trip_id', 'payment_method', 'type', 'passenger_type', 'passenger_id'] as $column) {
                    if ($this->has('passenger_trips', $column)) {
                        $select[] = "r.`$column`";
                    }
                }
                $where = "r.`$date` >= :from AND r.`$date` < :until AND LOWER(TRIM(r.payment_status)) = 'paid'";
                if ($this->has('passenger_trips', 'archived_status')) {
                    $where .= " AND COALESCE(r.archived_status, 'no') <> 'yes'";
                }
                if ($this->has('passenger_trips', 'archived')) {
                    $where .= ' AND COALESCE(r.archived, 0) = 0';
                }
                $join = '';
                $tripTypeExpr = 'NULL';
                foreach (['passenger_type', 'type'] as $candidate) {
                    if ($this->has('passenger_trips', $candidate)) {
                        $tripTypeExpr = "r.`$candidate`";
                        break;
                    }
                }
                /* The account's own discount type is the fallback when the
                   trip row does not record a passenger type (live app
                   boardings leave the column empty), so those fares are not
                   all reported as Regular. */
                $accountTypeExpr = 'NULL';
                if ($this->has('passenger_trips', 'passenger_id') && $this->tableExists('passengers')) {
                    $passengerType = $this->firstColumn('passengers', ['discount_type', 'type', 'passenger_type']);
                    if ($passengerType !== null) {
                        $join = ' LEFT JOIN passengers p ON p.id = r.passenger_id';
                        $accountTypeExpr = "p.`$passengerType`";
                    }
                }
                $select[] = $tripTypeExpr . ' AS passenger_type_raw';
                $select[] = $accountTypeExpr . ' AS account_type_raw';
                $this->stream(
                    'SELECT ' . implode(', ', $select) . " FROM passenger_trips r $join WHERE $where",
                    [':from' => $from, ':until' => $until],
                    function (array $row) use ($emitOnce, $filters, $tripIndex, $busAliases): void {
                        $normalized = $this->normalizeRevenueRow($row, 'passenger_trips', $filters, $tripIndex, $busAliases);
                        if ($normalized !== null) {
                            $emitOnce($normalized);
                        }
                    },
                    '',
                    /* The live paid-fare stream is the section's primary source:
                       when it cannot be read the request fails loudly instead
                       of quietly reporting archived-only totals. */
                    true
                );
            } else {
                $this->note('Current fare records were not readable, so only archived fare history is included.');
            }
        }

        // Archived fare history.
        if ($this->tableExists('passenger_trip_history') && $this->has('passenger_trip_history', 'fare')) {
            $dateCol = $this->firstColumn('passenger_trip_history', ['trip_date', 'date_booked']);
            if ($dateCol !== null) {
                $dateExpr = $dateCol === 'trip_date' && $this->has('passenger_trip_history', 'date_booked')
                    ? "COALESCE(NULLIF(r.trip_date, '0000-00-00'), DATE(r.date_booked))"
                    : "r.`$dateCol`";
                $select = [$dateExpr . ' AS report_date', 'r.fare AS report_amount'];
                foreach (['history_id', 'bus_id', 'bus_number', 'driver_id', 'trip_id', 'payment_method', 'type', 'passenger_type'] as $column) {
                    if ($this->has('passenger_trip_history', $column)) {
                        $select[] = "r.`$column`";
                    }
                }
                $this->stream(
                    'SELECT ' . implode(', ', $select) . " FROM passenger_trip_history r WHERE $dateExpr >= :from AND $dateExpr < :until",
                    [':from' => $from, ':until' => $until],
                    function (array $row) use ($emitOnce, $filters, $tripIndex, $busAliases): void {
                        $normalized = $this->normalizeRevenueRow($row, 'passenger_trip_history', $filters, $tripIndex, $busAliases);
                        if ($normalized !== null) {
                            $emitOnce($normalized);
                        }
                    }
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function normalizeRevenueRow(array $row, string $source, array $filters, array $tripIndex, array $busAliases): ?array
    {
        $date = substr((string) ($row['report_date'] ?? ''), 0, 10);
        if ($date === '') {
            return null;
        }
        $cents = (int) round((float) ($row['report_amount'] ?? 0) * 100);
        $typeRaw = strtolower(trim((string) ($row['type'] ?? '')));
        $isBaggage = $typeRaw !== '' && $typeRaw !== 'passenger';
        $passengerType = $isBaggage
            ? 'Baggage'
            : self::normalizePassengerType(self::firstFilledString(
                $row['passenger_type_raw'] ?? null,
                $row['account_type_raw'] ?? null,
                $row['passenger_type'] ?? null
            ));
        $driver = trim((string) ($row['driver_id'] ?? ''));
        $trip = trim((string) ($row['trip_no'] ?? $row['trip_id'] ?? ''));
        /* The archived history keeps the most complete bus reference in
           bus_number; the live source stores it in bus_id. */
        $bus = trim((string) self::firstFilledString(
            $row['bus_number'] ?? null,
            $row['bus_id'] ?? null,
            $row['bus_no'] ?? null
        ));
        $route = '';
        $excluded = false;
        $lookupRef = $trip;
        /* Historical CSV archives prefix the recorded trip reference:
           ARCV-#### stores bus_trips.id after the prefix (verified 100% of
           live ARCV rows). TRIP-#### has no verified mapping and stays
           unattributed rather than guessed. */
        if ($source === 'passenger_trip_history' && preg_match('/^ARCV-(\d+)$/i', $trip, $matches) === 1) {
            $lookupRef = $matches[1];
        }
        if ($lookupRef !== '' && preg_match('/^TRIP-/i', $lookupRef) !== 1) {
            $resolved = $this->resolveTripRoute($tripIndex, $date, $driver, $lookupRef);
            if ($bus === '') {
                $bus = $resolved['bus'];
            }
            $route = $resolved['route'];
            $excluded = (bool) ($resolved['excluded'] ?? false);
            /* Exact id fallback for references the window-scoped index cannot
               place: an ARCV suffix or numeric trip_no that names a recorded
               trip is a verified link even when the recorded trip_date and
               the journey date differ. */
            if ($route === '' && preg_match('/^\d+$/', $lookupRef) === 1) {
                $tripById = $this->tripById();
                if (isset($tripById[$lookupRef])) {
                    if ($bus === '') {
                        $bus = $tripById[$lookupRef]['bus'];
                    }
                    $route = $tripById[$lookupRef]['route'];
                    $excluded = (bool) $tripById[$lookupRef]['excluded'];
                }
            }
        }
        /* Verified fallbacks when the trip reference does not resolve:
           the bus ran exactly one canonical route that day, or (for the
           archived source) the driver ran exactly one route that day. */
        if ($route === '' && $bus !== '') {
            $busRoute = self::busRouteFor($tripIndex, $bus, $date);
            if ($busRoute !== '') {
                $route = $busRoute;
                $excluded = false;
            } elseif ($source === 'passenger_trip_history' && $driver !== '') {
                $driverRoute = self::driverRouteFor($tripIndex, $driver, $date);
                if ($driverRoute !== '') {
                    $route = $driverRoute;
                    $excluded = false;
                }
            }
        }
        if ($bus !== '' && isset($busAliases[strtolower($bus)])) {
            $bus = $busAliases[strtolower($bus)];
        }
        if ($filters['route'] !== '' && !self::routesMatch($route, (string) $filters['route'])) {
            return null;
        }
        if ($filters['bus'] !== '' && strcasecmp($bus, (string) $filters['bus']) !== 0) {
            return null;
        }
        if ($filters['passenger_type'] !== '' && strcasecmp($passengerType, (string) $filters['passenger_type']) !== 0) {
            return null;
        }
        $recordId = trim((string) ($row['id'] ?? $row['history_id'] ?? ''));
        $identity = $recordId !== ''
            ? $source . ':' . $recordId
            : $source . ':' . $date . ':' . $driver . ':' . $bus . ':' . $trip . ':' . $cents . ':' . $passengerType;
        /* Cross-source de-dup (verified): the archive convention stores the
           live passenger_trips.id in history.trip_id, so a numeric history
           reference that matches a live id with the same date, driver and
           fare is the archived twin of that live row. The twin key is only
           used to suppress the archived copy; identity stays namespaced so
           distinct same-fare passengers are never collapsed. */
        $twinKey = '';
        if ($source === 'passenger_trips' && $recordId !== '') {
            $twinKey = $recordId . '|' . $date . '|' . $driver . '|' . $cents;
        } elseif ($source === 'passenger_trip_history' && preg_match('/^\d+$/', $trip) === 1) {
            $twinKey = $trip . '|' . $date . '|' . $driver . '|' . $cents;
        }
        $routeCause = '';
        if ($route === '') {
            if ($excluded) {
                $routeCause = 'configuration_excluded';
            } elseif ($trip === '') {
                $routeCause = 'no_trip_reference';
            } elseif (preg_match('/^TRIP-/i', $trip) === 1) {
                $routeCause = 'history_reference_unmapped';
            } elseif (preg_match('/^ARCV-/i', $trip) === 1 || preg_match('/^\d+$/', $trip) === 1) {
                $routeCause = 'trip_reference_not_found';
            } else {
                $routeCause = 'reference_not_verified';
            }
        }
        return [
            'date' => $date,
            'kind' => 'revenue',
            'source' => $source,
            'category' => $isBaggage ? 'Baggage & extra fares' : 'Passenger fare',
            'cents' => $cents,
            'bus' => $bus,
            'driver' => $driver,
            'trip' => $trip,
            'route' => $route,
            'route_state' => $route !== '' ? 'attributed' : 'unattributed',
            'route_cause' => $routeCause,
            'payment' => trim((string) ($row['payment_method'] ?? 'Unspecified')),
            'passenger_type' => $passengerType,
            'passenger_id' => trim((string) ($row['passenger_id'] ?? '')),
            'identity' => $identity,
            'twin_key' => $twinKey,
        ];
    }

    /**
     * Expense rows with the FinancialPerformanceService fuel precedence.
     * Ledger rows from both expense tables are merged with a conservative
     * cross-table de-duplication, narrowed by route/bus, and only then is
     * ledger-versus-receipt precedence decided for the narrowed scope.
     *
     * @return list<array<string, mixed>>
     */
    private function expenseRows(array $filters): array
    {
        $rows = [];
        $this->streamExpenseRows($filters, static function (array $row) use (&$rows): void {
            $rows[] = $row;
        });
        return $rows;
    }

    private function streamExpenseRows(array $filters, callable $emit): void
    {
        $from = $filters['from'];
        $until = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        $busAliases = $this->busAliases();
        $ledgerRows = [];

        $seen = [];
        $duplicates = 0;
        foreach (['expenses', 'financial_expenses'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $date = $this->firstColumn($table, ['expense_date', 'created_at']);
            $amount = $this->firstColumn($table, ['amount']);
            if ($date === null || $amount === null) {
                $this->note('One expense source does not expose usable date or amount fields and was skipped.');
                continue;
            }
            $select = ["r.`$date` AS report_date", "r.`$amount` AS report_amount"];
            foreach (['category', 'bus_id', 'driver_id', 'trip_id', 'description', 'reference_file'] as $column) {
                if ($this->has($table, $column)) {
                    $select[] = "r.`$column`";
                }
            }
            foreach ($this->fetch('SELECT ' . implode(', ', $select) . " FROM `$table` r WHERE r.`$date` >= :from AND r.`$date` < :until", [':from' => $from, ':until' => $until]) as $row) {
                $cents = (int) round((float) ($row['report_amount'] ?? 0) * 100);
                if ($cents === 0) {
                    continue;
                }
                $bus = trim((string) ($row['bus_id'] ?? ''));
                if ($bus !== '' && isset($busAliases[strtolower($bus)])) {
                    $bus = $busAliases[strtolower($bus)];
                }
                $category = trim((string) ($row['category'] ?? 'Operating expenses')) ?: 'Operating expenses';
                $description = trim((string) ($row['description'] ?? ''));
                $dedupKey = substr((string) ($row['report_date'] ?? ''), 0, 10) . '|' . strtolower($category) . '|' . $cents
                    . '|' . strtolower($bus) . '|' . strtolower($description);
                if (isset($seen[$dedupKey]) && $seen[$dedupKey] !== $table) {
                    $duplicates++;
                    continue;
                }
                $seen[$dedupKey] = $table;
                $ledgerRows[] = [
                    'date' => substr((string) ($row['report_date'] ?? ''), 0, 10),
                    'kind' => 'expense',
                    'category' => $category,
                    'cents' => $cents,
                    'bus' => $bus,
                    'driver' => trim((string) ($row['driver_id'] ?? '')),
                    'trip' => trim((string) ($row['trip_id'] ?? '')),
                    'route' => '',
                    'payment' => '',
                    'passenger_type' => '',
                    'source' => $table,
                    'reference' => $table,
                    'description' => $description,
                    'receipt' => '',
                    'is_fuel_purchase' => false,
                ];
            }
        }
        if ($duplicates > 0) {
            $this->note($duplicates . ' expense record(s) mirrored in both expense tables were counted once.');
        }

        /* Route and bus filters apply to attributable costs too, so a filtered
           overview never pairs narrowed revenue with company-wide expenses.
           Costs that cannot be attributed stay out and are reported. */
        if ($filters['route'] !== '' || $filters['bus'] !== '') {
            $tripIndex = $this->tripIndex($filters['from'], $filters['to']);
            $kept = [];
            $droppedRoute = 0;
            $droppedBus = 0;
            foreach ($ledgerRows as $row) {
                $bus = trim((string) $row['bus']);
                $route = trim((string) $row['route']);
                if (($route === '' || $bus === '') && (string) $row['trip'] !== '') {
                    $resolved = self::resolveTripRouteStatic($tripIndex, (string) $row['date'], (string) $row['driver'], (string) $row['trip']);
                    if ($route === '') {
                        $route = $resolved['route'];
                    }
                    if ($bus === '') {
                        $bus = $resolved['bus'];
                    }
                }
                if ($bus !== '' && isset($busAliases[strtolower($bus)])) {
                    $bus = $busAliases[strtolower($bus)];
                }
                if ($filters['bus'] !== '' && ($bus === '' || strcasecmp($bus, (string) $filters['bus']) !== 0)) {
                    $droppedBus++;
                    continue;
                }
                if ($filters['route'] !== '' && ($route === '' || !self::routesMatch($route, (string) $filters['route']))) {
                    $droppedRoute++;
                    continue;
                }
                $row['bus'] = $bus;
                $row['route'] = $route;
                $kept[] = $row;
            }
            if ($filters['route'] !== '' && $droppedRoute > 0) {
                $this->note($droppedRoute . ' expense record(s) without a matching route were excluded under the route filter; route costs remain attributable only.');
            }
            if ($filters['bus'] !== '' && $droppedBus > 0) {
                $this->note($droppedBus . ' expense record(s) without a matching bus were excluded under the bus filter.');
            }
            $ledgerRows = $kept;
        }

        $hasFuelLedger = false;
        foreach ($ledgerRows as $row) {
            if (self::isFuelCategory((string) $row['category'])) {
                $hasFuelLedger = true;
                break;
            }
        }

        if ($hasFuelLedger) {
            $this->note('Recorded fuel expenses are used for this period; refuel receipts are not added again.');
        } else {
            $purchases = $this->fuelPurchaseRows($filters);
            $this->note('Fuel uses actual refuel receipts when no fuel expense is posted for this period; duplicate receipts for the same bus are excluded.');
            foreach ($purchases as $purchase) {
                $purchase['kind'] = 'expense';
                $purchase['category'] = 'Fuel';
                $purchase['is_fuel_purchase'] = true;
                $ledgerRows[] = $purchase;
            }
        }

        foreach ($ledgerRows as $row) {
            $emit($row);
        }
    }

    /**
     * Actual refuel receipts (fuel_refuels) plus legacy fuel_logs purchase
     * rows, de-duplicated by bus + stored receipt number.
     *
     * @return list<array<string, mixed>>
     */
    private function fuelPurchaseRows(array $filters): array
    {
        $from = $filters['from'];
        $until = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        $busAliases = $this->busAliases();
        $rows = [];
        $seen = [];
        $seenRefuelNatural = [];

        $add = static function (array &$target, array $row): void {
            $target[] = $row;
        };

        if ($this->tableExists('fuel_refuels')) {
            $select = ['fr.refuel_id AS record_id', 'fr.created_at AS report_date', 'fr.total_cost AS report_amount'];
            foreach (['bus_id', 'driver_id', 'trip_id', 'liters', 'price_per_liter', 'refuel_odo'] as $column) {
                if ($this->has('fuel_refuels', $column)) {
                    $select[] = "fr.`$column`";
                }
            }
            foreach ($this->fetch('SELECT ' . implode(', ', $select) . ' FROM fuel_refuels fr WHERE fr.created_at >= :from AND fr.created_at < :until', [':from' => $from, ':until' => $until]) as $row) {
                $receipt = 'RF-' . (string) ($row['record_id'] ?? '');
                $bus = trim((string) ($row['bus_id'] ?? ''));
                $key = strtolower($bus) . '|' . $receipt;
                $seen[$key] = true;
                /* Natural identity used to suppress a legacy row describing
                   the same physical purchase when neither side carries a
                   matching receipt identifier. */
                $seenRefuelNatural[strtolower($bus) . '|' . substr((string) ($row['report_date'] ?? ''), 0, 10)
                    . '|' . number_format((float) ($row['liters'] ?? 0), 2, '.', '')
                    . '|' . (int) round((float) ($row['report_amount'] ?? 0) * 100)] = true;
                $add($rows, [
                    'date' => substr((string) ($row['report_date'] ?? ''), 0, 10),
                    'bus' => $bus,
                    'driver' => trim((string) ($row['driver_id'] ?? '')),
                    'trip' => trim((string) ($row['trip_id'] ?? '')),
                    'route' => '',
                    'cents' => (int) round((float) ($row['report_amount'] ?? 0) * 100),
                    'liters' => (float) ($row['liters'] ?? 0),
                    'price' => (float) ($row['price_per_liter'] ?? 0),
                    'odo' => (float) ($row['refuel_odo'] ?? 0),
                    'source' => 'fuel_refuels',
                    'reference' => 'fuel_refuels:' . (string) ($row['record_id'] ?? ''),
                    'receipt' => $receipt,
                    'description' => 'Refuel receipt',
                    'payment' => '',
                    'passenger_type' => '',
                ]);
            }
        }

        if ($this->tableExists('fuel_logs') && $this->has('fuel_logs', 'liters')) {
            $date = $this->firstColumn('fuel_logs', ['created_at']);
            $amount = $this->firstColumn('fuel_logs', ['cost']);
            if ($date !== null && $amount !== null) {
                $select = ["fl.`$date` AS report_date", "fl.`$amount` AS report_amount", 'fl.liters'];
                foreach (['fuel_log_id', 'id', 'bus_id', 'driver_id', 'trip_id', 'price_per_liter', 'receipt_number'] as $column) {
                    if ($this->has('fuel_logs', $column)) {
                        $select[] = "fl.`$column`";
                    }
                }
                foreach ($this->fetch('SELECT ' . implode(', ', $select) . " FROM fuel_logs fl WHERE fl.`$date` >= :from AND fl.`$date` < :until AND COALESCE(fl.liters, 0) > 0", [':from' => $from, ':until' => $until]) as $row) {
                    $receipt = trim((string) ($row['receipt_number'] ?? ''));
                    if ($receipt === '') {
                        $receipt = 'FL-' . (string) ($row['fuel_log_id'] ?? $row['id'] ?? '');
                    }
                    $bus = trim((string) ($row['bus_id'] ?? ''));
                    $key = strtolower($bus) . '|' . $receipt;
                    $natural = strtolower($bus) . '|' . substr((string) ($row['report_date'] ?? ''), 0, 10)
                        . '|' . number_format((float) ($row['liters'] ?? 0), 2, '.', '')
                        . '|' . (int) round((float) ($row['report_amount'] ?? 0) * 100);
                    if (isset($seen[$key]) || isset($seenRefuelNatural[$natural])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $add($rows, [
                        'date' => substr((string) ($row['report_date'] ?? ''), 0, 10),
                        'bus' => $bus,
                        'driver' => trim((string) ($row['driver_id'] ?? '')),
                        'trip' => trim((string) ($row['trip_id'] ?? '')),
                        'route' => '',
                        'cents' => (int) round((float) ($row['report_amount'] ?? 0) * 100),
                        'liters' => (float) ($row['liters'] ?? 0),
                        'price' => (float) ($row['price_per_liter'] ?? 0),
                        'odo' => 0.0,
                        'source' => 'fuel_logs',
                        'reference' => 'fuel_logs:' . (string) ($row['fuel_log_id'] ?? $row['id'] ?? ''),
                        'receipt' => $receipt,
                        'description' => 'Legacy fuel purchase',
                        'payment' => '',
                        'passenger_type' => '',
                    ]);
                }
            }
        }

        $tripIndex = $this->tripIndex($from, (string) $filters['to']);
        foreach ($rows as &$row) {
            if ($row['bus'] !== '' && isset($busAliases[strtolower((string) $row['bus'])])) {
                $row['bus'] = $busAliases[strtolower((string) $row['bus'])];
            }
            $route = '';
            if ((string) $row['trip'] !== '') {
                $resolved = self::resolveTripRouteStatic($tripIndex, (string) $row['date'], (string) $row['driver'], (string) $row['trip']);
                $route = $resolved['route'];
            }
            if ($route === '' && (string) $row['bus'] !== '') {
                $route = self::busRouteFor($tripIndex, (string) $row['bus'], (string) $row['date']);
            }
            $row['route'] = $route;
        }
        unset($row);

        $filtered = [];
        $droppedRoute = 0;
        $unattributedRoute = 0;
        foreach ($rows as $row) {
            if ($filters['bus'] !== '' && strcasecmp((string) $row['bus'], (string) $filters['bus']) !== 0) {
                continue;
            }
            if ($filters['route'] !== '') {
                if ((string) $row['route'] === '') {
                    $unattributedRoute++;
                    continue;
                }
                if (!self::routesMatch((string) $row['route'], (string) $filters['route'])) {
                    $droppedRoute++;
                    continue;
                }
            }
            $filtered[] = $row;
        }
        if ($filters['route'] !== '' && ($droppedRoute > 0 || $unattributedRoute > 0)) {
            $this->note($droppedRoute . ' fuel purchase(s) for other routes and ' . $unattributedRoute
                . ' without an attributable route were excluded under the route filter.');
        }
        return $filtered;
    }

    /**
     * Per-trip odometer/KPL rows used for efficiency metrics.
     *
     * @return list<array<string, mixed>>
     */
    private function fuelOperationalRows(array $filters): array
    {
        if (!$this->tableExists('fuel_logs')) {
            return [];
        }
        $date = $this->firstColumn('fuel_logs', ['created_at']);
        if ($date === null) {
            return [];
        }
        $select = ["fl.`$date` AS report_date"];
        foreach (['bus_id', 'driver_id', 'trip_id', 'liters', 'cost', 'price_per_liter', 'distance_traveled', 'distance_km', 'starting_odo', 'ending_odo', 'starting_odometer', 'ending_odometer', 'start_km', 'end_km', 'kpl'] as $column) {
            if ($this->has('fuel_logs', $column)) {
                $select[] = "fl.`$column`";
            }
        }
        $until = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        $busAliases = $this->busAliases();
        $tripIndex = $this->tripIndex($filters['from'], (string) $filters['to']);
        $rows = [];
        foreach ($this->fetch('SELECT ' . implode(', ', $select) . " FROM fuel_logs fl WHERE fl.`$date` >= :from AND fl.`$date` < :until", [':from' => $filters['from'], ':until' => $until]) as $row) {
            $distance = (float) ($row['distance_traveled'] ?? $row['distance_km'] ?? 0);
            if ($distance <= 0) {
                /* Odometer pairs are tried in recorded order; the first valid
                   pair wins. start_km/end_km carries live data the older
                   service never read. */
                foreach ([['starting_odo', 'ending_odo'], ['starting_odometer', 'ending_odometer'], ['start_km', 'end_km']] as [$startColumn, $endColumn]) {
                    $start = (float) ($row[$startColumn] ?? 0);
                    $end = (float) ($row[$endColumn] ?? 0);
                    if ($end > $start) {
                        $distance = $end - $start;
                        break;
                    }
                }
            }
            $liters = (float) ($row['liters'] ?? 0);
            $recordedKpl = (float) ($row['kpl'] ?? 0);
            /* Three states are kept distinct: a recorded measurement, a value
               derived from distance and liters, a missing measurement, and a
               genuine zero record. The column default for kpl is not trusted
               as a measurement by itself. */
            if ($recordedKpl > 0) {
                $kpl = $recordedKpl;
                $measurement = 'recorded';
            } elseif ($distance > 0 && $liters > 0) {
                $kpl = $distance / $liters;
                $measurement = 'derived';
            } elseif ($distance <= 0 && $liters <= 0) {
                $kpl = 0.0;
                $measurement = 'zero';
            } else {
                $kpl = 0.0;
                $measurement = 'missing';
            }
            $bus = trim((string) ($row['bus_id'] ?? ''));
            if ($bus !== '' && isset($busAliases[strtolower($bus)])) {
                $bus = $busAliases[strtolower($bus)];
            }
            if ($filters['bus'] !== '' && strcasecmp($bus, (string) $filters['bus']) !== 0) {
                continue;
            }
            $trip = trim((string) ($row['trip_id'] ?? ''));
            $route = '';
            if ($trip !== '') {
                $route = self::resolveTripRouteStatic($tripIndex, substr((string) ($row['report_date'] ?? ''), 0, 10), trim((string) ($row['driver_id'] ?? '')), $trip)['route'];
            }
            if ($route === '' && $bus !== '') {
                $route = self::busRouteFor($tripIndex, $bus, substr((string) ($row['report_date'] ?? ''), 0, 10));
            }
            if ($filters['route'] !== '' && ($route === '' || !self::routesMatch($route, (string) $filters['route']))) {
                continue;
            }
            $rows[] = [
                'date' => substr((string) ($row['report_date'] ?? ''), 0, 10),
                'bus' => $bus,
                'trip' => $trip,
                'route' => $route,
                'liters' => $liters,
                'cost' => (float) ($row['cost'] ?? 0),
                'price' => (float) ($row['price_per_liter'] ?? 0),
                'distance' => $distance,
                'kpl' => $kpl,
                'measurement' => $measurement,
            ];
        }
        return $rows;
    }

    /* =========================================================
       AGGREGATION
    ========================================================= */

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function aggregateRows(array $rows, array $filters, array $context = []): array
    {
        $state = self::newAggregateState();
        $fold = self::aggregateFolder($context);
        foreach ($rows as $row) {
            $fold($row, $state);
        }
        return self::finalizeAggregate($state, $filters);
    }

    /** @return array<string, mixed> */
    private static function newAggregateState(): array
    {
        return [
            'totals' => [
                'collections' => 0, 'fuel' => 0, 'other_expenses' => 0, 'expenses' => 0,
                'passengers' => 0, 'baggage_count' => 0, 'baggage_collections' => 0,
            ],
            'trips' => [],
            'daily' => [],
            'monthly' => [],
            'by_route' => [],
            'by_bus' => [],
            'by_type' => [],
            'by_category' => [],
            'by_payment' => [],
            'attribution' => [],
            'fare_groups' => [],
            'fare_unresolved_route' => 0,
            'identified' => [],
            'covered_exact' => [],
            'covered_refs' => [],
            'unattributed_cents' => 0,
            'unattributed_records' => 0,
            'unattributed_ref_records' => 0,
            'revenue_records' => 0,
            'config_drivers' => [],
            'config_total_gross_cents' => 0,
        ];
    }

    /**
     * Per-row aggregation fold shared by the buffered and the streaming path.
     * Wide windows stream directly through this fold, so no normalized row
     * set is ever retained.
     *
     * @return callable(array<string, mixed>, array<string, mixed>&): void
     */
    private static function aggregateFolder(array $context): callable
    {
        $tripIndex = $context['trip_index'] ?? ['exact' => [], 'by_ref' => []];
        $busAliases = $context['bus_aliases'] ?? [];
        return static function (array $row, array &$state) use ($tripIndex, $busAliases): void {
            $totals = &$state['totals'];
            $trips = &$state['trips'];
            $daily = &$state['daily'];
            $monthly = &$state['monthly'];
            $byRoute = &$state['by_route'];
            $byBus = &$state['by_bus'];
            $byType = &$state['by_type'];
            $byCategory = &$state['by_category'];
            $byPayment = &$state['by_payment'];
            $attribution = &$state['attribution'];
            $fareGroups = &$state['fare_groups'];
            $fareUnresolvedRoute = &$state['fare_unresolved_route'];
            $identified = &$state['identified'];
            $coveredExact = &$state['covered_exact'];
            $coveredRefs = &$state['covered_refs'];
            $unattributedCents = &$state['unattributed_cents'];
            $unattributedRecords = &$state['unattributed_records'];
            $unattributedRefRecords = &$state['unattributed_ref_records'];
            $configDrivers = &$state['config_drivers'];
            $configTotalGrossCents = &$state['config_total_gross_cents'];

            $bucket = static function (array &$target, string $key): void {
                if (!isset($target[$key])) {
                    $target[$key] = ['collections' => 0, 'expenses' => 0, 'fuel' => 0, 'passengers' => 0, 'trips' => []];
                }
            };

            $cents = (int) $row['cents'];
            $date = (string) $row['date'];
            $month = substr($date, 0, 7);
            $bucket($daily, $date);
            $bucket($monthly, $month);
            $route = (string) $row['route'];
            $bus = (string) $row['bus'];
            if ($row['kind'] === 'revenue') {
                $state['revenue_records']++;
                $isBaggage = ($row['passenger_type'] ?? '') === 'Baggage';
                $totals['collections'] += $cents;
                $paymentKey = trim((string) $row['payment']);
                $paymentKey = $paymentKey === '' ? 'Unspecified' : $paymentKey;
                if (!isset($byPayment[$paymentKey])) {
                    $byPayment[$paymentKey] = ['method' => $paymentKey, 'cents' => 0, 'count' => 0];
                }
                $byPayment[$paymentKey]['cents'] += $cents;
                $byPayment[$paymentKey]['count']++;
                if ($isBaggage) {
                    $totals['baggage_count']++;
                    $totals['baggage_collections'] += $cents;
                } else {
                    $totals['passengers']++;
                    $passengerId = trim((string) ($row['passenger_id'] ?? ''));
                    if ($passengerId !== '') {
                        $identified[$passengerId] = true;
                    }
                    $routeKey = self::routeKeyUnordered($route);
                    if ($route === '' || $routeKey === '') {
                        $fareUnresolvedRoute++;
                    } else {
                        if (!isset($fareGroups[$routeKey])) {
                            $fareGroups[$routeKey] = ['route' => $route, 'rows' => 0, 'columns' => []];
                        }
                        $column = self::fareColumnFor((string) $row['passenger_type']);
                        if (!isset($fareGroups[$routeKey]['columns'][$column])) {
                            $fareGroups[$routeKey]['columns'][$column] = ['rows' => 0, 'cents' => 0];
                        }
                        $fareGroups[$routeKey]['rows']++;
                        $fareGroups[$routeKey]['columns'][$column]['rows']++;
                        $fareGroups[$routeKey]['columns'][$column]['cents'] += $cents;
                    }
                    $driverName = trim((string) $row['driver']);
                    $driverName = $driverName === '' ? 'Unassigned' : $driverName;
                    if (!isset($configDrivers[$driverName])) {
                        $configDrivers[$driverName] = ['gross_cents' => 0, 'trips' => []];
                    }
                    $configDrivers[$driverName]['gross_cents'] += $cents;
                    if ((string) $row['trip'] !== '') {
                        $configDrivers[$driverName]['trips'][$date . '|' . strtolower($driverName) . '|' . $bus . '|' . (string) $row['trip']] = true;
                    }
                    $configTotalGrossCents += $cents;
                }
                if ((string) $row['trip'] !== '') {
                    $coveredExact[$date . '|' . strtolower(trim((string) $row['driver'])) . '|' . (string) $row['trip']] = true;
                    $coveredRefs[(string) $row['trip']] = true;
                }
                $daily[$date]['collections'] += $cents;
                $monthly[$month]['collections'] += $cents;
                if (!$isBaggage) {
                    $daily[$date]['passengers']++;
                    $monthly[$month]['passengers']++;
                    $type = (string) $row['passenger_type'];
                    if (!isset($byType[$type])) {
                        $byType[$type] = ['type' => $type, 'trips' => 0, 'cents' => 0, 'by_month' => [], 'by_route' => []];
                    }
                    $byType[$type]['trips']++;
                    $byType[$type]['cents'] += $cents;
                    $byType[$type]['by_month'][$month] = ($byType[$type]['by_month'][$month] ?? 0) + $cents;
                    if ($route !== '') {
                        $byType[$type]['by_route'][$route] = ($byType[$type]['by_route'][$route] ?? 0) + 1;
                    }
                }
                if ($route !== '') {
                    $bucket($byRoute, $route);
                    $byRoute[$route]['collections'] += $cents;
                    if (!$isBaggage) {
                        $byRoute[$route]['passengers']++;
                    }
                } else {
                    $cause = (string) ($row['route_cause'] ?? 'unattributed_other');
                    if (!isset($attribution[$cause])) {
                        $attribution[$cause] = ['cause' => $cause, 'cents' => 0, 'rows' => 0, 'trips' => []];
                    }
                    $attribution[$cause]['cents'] += $cents;
                    $attribution[$cause]['rows']++;
                    $unattributedCents += $cents;
                    $unattributedRecords++;
                    if ((string) $row['trip'] !== '') {
                        $attribution[$cause]['trips'][$date . '|' . (string) $row['driver'] . '|' . $bus . '|' . (string) $row['trip']] = true;
                        $unattributedRefRecords++;
                    }
                }
                if ($bus !== '') {
                    $bucket($byBus, $bus);
                    $byBus[$bus]['collections'] += $cents;
                    if (!$isBaggage) {
                        $byBus[$bus]['passengers']++;
                    }
                }
                if ((string) $row['trip'] !== '') {
                    $tripKey = $date . '|' . (string) $row['driver'] . '|' . (string) $row['bus'] . '|' . (string) $row['trip'];
                    $trips[$tripKey] = true;
                    $daily[$date]['trips'][$tripKey] = true;
                    $monthly[$month]['trips'][$tripKey] = true;
                    if ($route !== '') {
                        $byRoute[$route]['trips'][$tripKey] = true;
                    }
                    if ($bus !== '') {
                        $byBus[$bus]['trips'][$tripKey] = true;
                    }
                }
            } else {
                $isFuel = self::isFuelCategory((string) $row['category']);
                $categoryKey = trim((string) $row['category']);
                $categoryKey = $categoryKey === '' ? 'Operating expenses' : $categoryKey;
                if (!isset($byCategory[$categoryKey])) {
                    $byCategory[$categoryKey] = ['category' => $categoryKey, 'cents' => 0, 'count' => 0];
                }
                $byCategory[$categoryKey]['cents'] += $cents;
                $byCategory[$categoryKey]['count']++;
                if ($isFuel) {
                    $totals['fuel'] += $cents;
                } else {
                    $totals['other_expenses'] += $cents;
                }
                $totals['expenses'] += $cents;
                $daily[$date]['expenses'] += $cents;
                if ($isFuel) {
                    $daily[$date]['fuel'] += $cents;
                }
                $monthly[$month]['expenses'] += $cents;
                if ($isFuel) {
                    $monthly[$month]['fuel'] += $cents;
                }
                if ($route === '' && (string) $row['trip'] !== '') {
                    $resolved = self::resolveTripRouteStatic($tripIndex, $date, (string) $row['driver'], (string) $row['trip']);
                    $route = $resolved['route'];
                    if ($bus === '') {
                        $bus = $resolved['bus'];
                    }
                    if ($bus !== '' && isset($busAliases[strtolower($bus)])) {
                        $bus = $busAliases[strtolower($bus)];
                    }
                }
                if ($route !== '') {
                    $bucket($byRoute, $route);
                    $byRoute[$route]['expenses'] += $cents;
                    if ($isFuel) {
                        $byRoute[$route]['fuel'] += $cents;
                    }
                }
                if ($bus !== '') {
                    $bucket($byBus, $bus);
                    $byBus[$bus]['expenses'] += $cents;
                    if ($isFuel) {
                        $byBus[$bus]['fuel'] += $cents;
                    }
                }
            }
        };
    }

    private static function finalizeAggregate(array $state, array $filters): array
    {
        $totals = $state['totals'];
        $trips = $state['trips'];
        $daily = $state['daily'];
        $monthly = $state['monthly'];
        $byRoute = $state['by_route'];
        $byBus = $state['by_bus'];
        $byType = $state['by_type'];
        $byCategory = $state['by_category'];
        $byPayment = $state['by_payment'];
        $attribution = $state['attribution'];
        $fareGroups = $state['fare_groups'];
        $fareUnresolvedRoute = $state['fare_unresolved_route'];
        $identified = $state['identified'];
        $coveredExact = $state['covered_exact'];
        $coveredRefs = $state['covered_refs'];
        $unattributedCents = $state['unattributed_cents'];
        $unattributedRecords = $state['unattributed_records'];
        $unattributedRefRecords = $state['unattributed_ref_records'];

        $bucket = static function (array &$target, string $key): void {
            if (!isset($target[$key])) {
                $target[$key] = ['collections' => 0, 'expenses' => 0, 'fuel' => 0, 'passengers' => 0, 'trips' => []];
            }
        };

        $money = static fn(int $cents): float => round($cents / 100, 2);
        foreach (['collections', 'fuel', 'other_expenses', 'expenses'] as $key) {
            $totals[$key] = $money($totals[$key]);
        }
        $totals['baggage_collections'] = $money($totals['baggage_collections']);
        $totals['profit'] = round($totals['collections'] - $totals['expenses'], 2);
        $totals['margin'] = $totals['collections'] > 0 ? round($totals['profit'] / $totals['collections'] * 100, 2) : null;
        $totals['trips'] = count($trips);
        $totals['avg_fare'] = $totals['passengers'] > 0
            ? round(($totals['collections'] - $totals['baggage_collections']) / $totals['passengers'], 2)
            : null;

        $cursor = new DateTimeImmutable($filters['from']);
        $end = new DateTimeImmutable($filters['to']);
        while ($cursor <= $end) {
            $bucket($daily, $cursor->format('Y-m-d'));
            $bucket($monthly, $cursor->format('Y-m'));
            $cursor = $cursor->modify('+1 day');
        }

        $series = static function (array $group) use ($money): array {
            ksort($group);
            $out = [];
            foreach ($group as $key => $item) {
                $out[] = [
                    'key' => (string) $key,
                    'collections' => $money((int) $item['collections']),
                    'expenses' => $money((int) $item['expenses']),
                    'fuel' => $money((int) $item['fuel']),
                    'profit' => $money((int) $item['collections'] - (int) $item['expenses']),
                    'passengers' => (int) $item['passengers'],
                    'trips' => is_array($item['trips']) ? count($item['trips']) : (int) $item['trips'],
                ];
            }
            return $out;
        };

        $byRouteOut = [];
        foreach ($byRoute as $name => $item) {
            $costComplete = $item['expenses'] > 0;
            $byRouteOut[] = [
                'route' => (string) $name,
                'trips' => count($item['trips']),
                'passengers' => (int) $item['passengers'],
                'collections' => $money((int) $item['collections']),
                'expenses' => $money((int) $item['expenses']),
                'fuel' => $money((int) $item['fuel']),
                'profit' => $money((int) $item['collections'] - (int) $item['expenses']),
                'margin' => $item['collections'] > 0 ? round(($item['collections'] - $item['expenses']) / $item['collections'] * 100, 2) : null,
                'cost_complete' => $costComplete,
            ];
        }
        usort($byRouteOut, static fn(array $a, array $b): int => $b['collections'] <=> $a['collections']);

        $byBusOut = [];
        foreach ($byBus as $name => $item) {
            $byBusOut[] = [
                'bus' => (string) $name,
                'trips' => count($item['trips']),
                'passengers' => (int) $item['passengers'],
                'collections' => $money((int) $item['collections']),
                'expenses' => $money((int) $item['expenses']),
                'fuel' => $money((int) $item['fuel']),
                'profit' => $money((int) $item['collections'] - (int) $item['expenses']),
                'margin' => $item['collections'] > 0 ? round(($item['collections'] - $item['expenses']) / $item['collections'] * 100, 2) : null,
                'cost_complete' => $item['expenses'] > 0,
            ];
        }
        usort($byBusOut, static fn(array $a, array $b): int => $b['collections'] <=> $a['collections']);

        $byTypeOut = [];
        $totalPassengerCents = 0;
        foreach ($byType as $type => $item) {
            $totalPassengerCents += (int) $item['cents'];
        }
        foreach ($byType as $type => $item) {
            $byTypeOut[] = [
                'type' => (string) $type,
                'trips' => (int) $item['trips'],
                'collections' => $money((int) $item['cents']),
                'avg_fare' => $item['trips'] > 0 ? round($item['cents'] / 100 / $item['trips'], 2) : null,
                'share_pct' => $totalPassengerCents > 0 ? round($item['cents'] / $totalPassengerCents * 100, 2) : 0.0,
                'by_month' => array_map($money, $item['by_month']),
                'by_route' => $item['by_route'],
            ];
        }
        usort($byTypeOut, static fn(array $a, array $b): int => $b['collections'] <=> $a['collections']);

        $expenseTotal = (float) $totals['expenses'];
        $byCategoryOut = [];
        foreach ($byCategory as $item) {
            $amount = $money((int) $item['cents']);
            $byCategoryOut[] = [
                'category' => $item['category'],
                'amount' => $amount,
                'records' => $item['count'],
                'share_pct' => $expenseTotal > 0 ? round($amount / $expenseTotal * 100, 2) : 0.0,
            ];
        }
        usort($byCategoryOut, static fn(array $a, array $b): int => $b['amount'] <=> $a['amount']);

        $byPaymentOut = [];
        foreach ($byPayment as $item) {
            if ((int) $item['cents'] <= 0) {
                continue;
            }
            $byPaymentOut[] = [
                'method' => $item['method'],
                'collections' => $money((int) $item['cents']),
                'transactions' => $item['count'],
            ];
        }
        usort($byPaymentOut, static fn(array $a, array $b): int => $b['collections'] <=> $a['collections']);

        $causeLabels = [
            'no_trip_reference' => 'Journeys with no recorded trip reference',
            'trip_reference_not_found' => 'Trip reference not found in the recorded window',
            'history_reference_unmapped' => 'Archived trip reference with no verified mapping',
            'configuration_excluded' => 'Route excluded by configuration',
            'reference_not_verified' => 'Trip reference could not be verified',
            'unattributed_other' => 'Other unattributed records',
        ];
        $attributionOut = [];
        foreach ($attribution as $cause => $item) {
            $attributionOut[] = [
                'cause' => (string) $cause,
                'label' => $causeLabels[$cause] ?? 'Other unattributed records',
                'collections' => $money((int) $item['cents']),
                'records' => (int) $item['rows'],
                'trips' => count($item['trips']),
            ];
        }
        usort($attributionOut, static fn(array $a, array $b): int => $b['collections'] <=> $a['collections']);

        return [
            'totals' => $totals,
            'daily' => $series($daily),
            'monthly' => $series($monthly),
            'by_route' => $byRouteOut,
            'by_bus' => $byBusOut,
            'by_type' => $byTypeOut,
            'by_category' => $byCategoryOut,
            'by_payment' => $byPaymentOut,
            'attribution' => $attributionOut,
            'fare_groups' => $fareGroups,
            'fare_unresolved_route' => $fareUnresolvedRoute,
            'identified_passengers' => count($identified),
            'covered_exact' => $coveredExact,
            'covered_refs' => $coveredRefs,
            'unattributed_totals' => [
                'collections_cents' => $unattributedCents,
                'records' => $unattributedRecords,
                'records_with_reference' => $unattributedRefRecords,
            ],
        ];
    }

    private static function resolveTripRouteStatic(array $tripIndex, string $date, string $driver, string $trip): array
    {
        if ($trip === '') {
            return ['bus' => '', 'route' => '', 'excluded' => false];
        }
        $key = $date . '|' . strtolower(trim($driver)) . '|' . $trip;
        if (isset($tripIndex['exact'][$key])) {
            return self::withExcludedFlag($tripIndex, $trip, $tripIndex['exact'][$key]);
        }
        if (isset($tripIndex['by_ref'][$trip])) {
            return self::withExcludedFlag($tripIndex, $trip, $tripIndex['by_ref'][$trip]);
        }
        return ['bus' => '', 'route' => '', 'excluded' => isset($tripIndex['excluded_refs'][$trip])];
    }

    /**
     * @param array{bus: string, route: string} $resolved
     * @return array{bus: string, route: string, excluded: bool}
     */
    private static function withExcludedFlag(array $tripIndex, string $trip, array $resolved): array
    {
        $resolved['excluded'] = $resolved['route'] === '' && isset($tripIndex['excluded_refs'][$trip]);
        return $resolved;
    }

    private function collect(array $filters, bool $keepRevenueRows = true): array
    {
        $cacheKey = implode('|', [
            (string) $filters['from'],
            (string) $filters['to'],
            (string) $filters['route'],
            (string) $filters['bus'],
            (string) $filters['passenger_type'],
        ]) . ($keepRevenueRows ? '' : '|slim');
        if (isset($this->collectCache[$cacheKey])) {
            return $this->collectCache[$cacheKey];
        }

        $expenses = $this->expenseRows($filters);
        $context = [
            'trip_index' => $this->tripIndex($filters['from'], $filters['to']),
            'bus_aliases' => $this->busAliases(),
        ];
        /* Revenue streams directly through the aggregation fold: wide windows
           (semi-annual, annual) never retain the normalized row set. */
        $state = self::newAggregateState();
        $fold = self::aggregateFolder($context);
        $this->streamRevenueRows($filters, static function (array $row) use ($fold, &$state): void {
            $fold($row, $state);
        });
        foreach ($expenses as $row) {
            $fold($row, $state);
        }
        $aggregate = self::finalizeAggregate($state, $filters);
        $aggregate['has_revenue_records'] = (int) $state['revenue_records'] > 0;
        $aggregate['expense_rows'] = $expenses;
        $configuredBasis = self::configuredBasis($state);
        if ((string) ($filters['passenger_type'] ?? '') !== '') {
            /* Configured per-driver costs are not passenger-specific; their
               driver/trip base honors period, route and bus but not the
               passenger-type filter, which is disclosed in the scope notes. */
            $basisFilters = $filters;
            $basisFilters['passenger_type'] = '';
            $basisState = self::newAggregateState();
            $basisFold = self::aggregateFolder($context);
            $this->streamRevenueRows($basisFilters, static function (array $row) use ($basisFold, &$basisState): void {
                $basisFold($row, $basisState);
            });
            $configuredBasis = self::configuredBasis($basisState);
        }
        $recordedCategories = array_map(static fn(array $row): string => (string) ($row['category'] ?? ''), $expenses);
        $aggregate['configured'] = $this->configuredCosts($filters, $configuredBasis, $recordedCategories);
        $aggregate['has_expense_records'] = $expenses !== [];
        $hasFuel = false;
        foreach ($expenses as $row) {
            if (self::isFuelCategory((string) $row['category'])) {
                $hasFuel = true;
                break;
            }
        }
        $aggregate['has_fuel_records'] = $hasFuel;
        return $this->collectCache[$cacheKey] = $aggregate;
    }

    /* =========================================================
       SECTION: OVERVIEW
    ========================================================= */

    public function overview(array $filters): array
    {
        $current = $this->collect($filters, false);
        $previous = null;
        $previousFilters = null;
        if ($filters['previous_from'] !== '' && $filters['previous_to'] !== '') {
            $previousFilters = $filters;
            $previousFilters['from'] = $filters['previous_from'];
            $previousFilters['to'] = $filters['previous_to'];
            /* The comparison window is equivalent in its own right: day counts
               and anything scaled by them must be recomputed, never copied
               from the current window. */
            $previousFilters['days'] = self::windowDays((string) $filters['previous_from'], (string) $filters['previous_to']);
            $previous = $this->collect($previousFilters, false);
        }
        $previousAvailable = $previous !== null
            && (($previous['has_revenue_records'] ?? false) || $previous['expense_rows'] !== []);

        $change = static function (float $now, float $before): ?float {
            return abs($before) > 0.00001 ? round(($now - $before) / abs($before) * 100, 2) : null;
        };

        $configured = $current['configured'];

        $actual = $current['totals'];
        $actual['configured_costs'] = $configured['total'];
        $actual['total_expenses'] = round((float) $actual['expenses'] + (float) $configured['total'], 2);
        $actual['profit_after_configured'] = round((float) $actual['collections'] - (float) $actual['total_expenses'], 2);
        $actual['margin_after_configured'] = $actual['collections'] > 0
            ? round((float) $actual['profit_after_configured'] / (float) $actual['collections'] * 100, 2)
            : null;

        $prevTotals = null;
        $previousConfigured = null;
        if ($previous !== null && $previousFilters !== null && $previousAvailable) {
            $previousConfigured = $previous['configured'];
            $prevTotals = $previous['totals'];
            $prevTotals['configured_costs'] = $previousConfigured['total'];
            $prevTotals['total_expenses'] = round((float) $prevTotals['expenses'] + (float) $previousConfigured['total'], 2);
            $prevTotals['profit_after_configured'] = round((float) $prevTotals['collections'] - (float) $prevTotals['total_expenses'], 2);
            $prevTotals['margin_after_configured'] = $prevTotals['collections'] > 0
                ? round((float) $prevTotals['profit_after_configured'] / (float) $prevTotals['collections'] * 100, 2)
                : null;
        }

        $changes = null;
        if ($prevTotals !== null) {
            $changes = [
                'collections_pct' => $change((float) $actual['collections'], (float) $prevTotals['collections']),
                'expenses_pct' => $change((float) $actual['expenses'], (float) $prevTotals['expenses']),
                'profit_pct' => $change((float) $actual['profit'], (float) $prevTotals['profit']),
                'passengers_pct' => $change((float) $actual['passengers'], (float) $prevTotals['passengers']),
                'margin_delta_pts' => ($actual['margin'] !== null && $prevTotals['margin'] !== null)
                    ? round((float) $actual['margin'] - (float) $prevTotals['margin'], 2)
                    : null,
                'collections_delta' => round((float) $actual['collections'] - (float) $prevTotals['collections'], 2),
                'expenses_delta' => round((float) $actual['expenses'] - (float) $prevTotals['expenses'], 2),
                'profit_delta' => round((float) $actual['profit'] - (float) $prevTotals['profit'], 2),
                'total_expenses_delta' => round((float) $actual['total_expenses'] - (float) $prevTotals['total_expenses'], 2),
                'configured_delta' => round((float) $actual['configured_costs'] - (float) $prevTotals['configured_costs'], 2),
                'profit_after_configured_delta' => round((float) $actual['profit_after_configured'] - (float) $prevTotals['profit_after_configured'], 2),
                'profit_after_configured_pct' => $change((float) $actual['profit_after_configured'], (float) $prevTotals['profit_after_configured']),
            ];
        }

        $estimated = !$current['has_expense_records'] || !$current['has_fuel_records'];
        $completenessNotes = [];
        if (!$current['has_expense_records']) {
            $completenessNotes[] = 'No operating expense records were found for this period; the operating profit is an estimate based on fare collections only.';
        }
        if (!$current['has_fuel_records']) {
            $completenessNotes[] = 'No fuel cost records were found for this period; fuel-sensitive profit and margin figures are estimates.';
        }
        $completenessNotes[] = 'Remittance settlements and trip revenue summaries are not counted as additional income.';
        if ($configured['available']) {
            $completenessNotes[] = 'Configured monthly costs of ' . '₱' . number_format((float) $configured['total'], 2)
                . ' are estimates from Financial Cost Settings (' . $configured['drivers'] . ' driver(s), ' . $configured['months'] . ' month(s)); they are not posted transactions.';
        }
        if ($filters['passenger_type'] !== '') {
            $this->note('The passenger-type filter applies to fare revenue and passenger metrics; operating expenses are not passenger-specific and remain in full.');
        }

        $expenseBreakdown = $current['by_category'];
        $expensePool = (float) $actual['total_expenses'];
        foreach ($expenseBreakdown as &$item) {
            $item['share_pct'] = $expensePool > 0 ? round((float) $item['amount'] / $expensePool * 100, 2) : 0.0;
            $item['configured'] = false;
        }
        unset($item);
        foreach ($configured['items'] as $item) {
            if ((float) $item['amount'] <= 0) {
                continue;
            }
            $expenseBreakdown[] = [
                'category' => $item['label'] . ' (configured)',
                'amount' => (float) $item['amount'],
                'records' => 0,
                'share_pct' => $expensePool > 0 ? round((float) $item['amount'] / $expensePool * 100, 2) : 0.0,
                'configured' => true,
                'is_estimate' => true,
            ];
        }
        usort($expenseBreakdown, static fn(array $a, array $b): int => $b['amount'] <=> $a['amount']);

        $previousPeriod = ($previous !== null && $previousFilters !== null)
            ? ['from' => (string) $previousFilters['from'], 'to' => (string) $previousFilters['to']]
            : null;
        $totalOperating = (float) $actual['total_expenses'];

        return [
            'period' => $this->periodPayload($filters),
            'comparison_label' => (string) $filters['comparison_label'],
            'actual' => $actual,
            'previous' => $prevTotals,
            'previous_available' => $previousAvailable,
            'previous_period' => $previousPeriod,
            'change' => $changes,
            'configured_costs' => $configured,
            'configured_costs_previous' => $previousConfigured,
            'expense_summary' => [
                'recorded' => (float) $actual['expenses'],
                'configured_estimate' => (float) $configured['total'],
                'total' => $totalOperating,
                'recorded_share_pct' => $totalOperating > 0 ? round((float) $actual['expenses'] / $totalOperating * 100, 2) : null,
                'note' => 'Recorded expenses are actual ledger and purchase records; configured monthly costs are estimates from Financial Cost Settings and are labelled separately.',
            ],
            'expense_completeness' => [
                'estimated' => $estimated,
                'has_expense_records' => $current['has_expense_records'],
                'has_fuel_records' => $current['has_fuel_records'],
                'notes' => $completenessNotes,
            ],
            'series' => [
                'daily' => $current['daily'],
                'monthly' => $current['monthly'],
            ],
            'quarterly' => $this->quarterlySeries($filters),
            'waterfall' => $previousAvailable ? $this->waterfall($filters, $actual, $prevTotals) : [],
            'findings' => $this->findings($filters, $actual, $prevTotals, $changes, $current, $previous, $previousAvailable),
            'by_route' => $current['by_route'],
            'expense_breakdown' => $expenseBreakdown,
            'payment_methods' => $current['by_payment'],
            'scope' => $this->scopeMetadata($filters, ['revenue', 'expenses', 'fuel', 'configured']),
            'notes' => array_values($this->notes),
        ];
    }

    private function periodPayload(array $filters): array
    {
        return [
            'period' => $filters['period'],
            'label' => $filters['label'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'days' => $filters['days'],
            'partial' => self::isPartialPeriod($filters),
            'previous_days' => ($filters['previous_from'] !== '' && $filters['previous_to'] !== '')
                ? self::windowDays((string) $filters['previous_from'], (string) $filters['previous_to'])
                : null,
            'year' => (int) $filters['year'],
            'previous_from' => $filters['previous_from'],
            'previous_to' => $filters['previous_to'],
            'comparison_label' => (string) $filters['comparison_label'],
            'route' => $filters['route'],
            'bus' => $filters['bus'],
            'passenger_type' => $filters['passenger_type'],
        ];
    }

    /** Inclusive day count between two dates. */
    private static function windowDays(string $from, string $to): int
    {
        $start = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        return (int) $start->diff($end)->days + 1;
    }

    /**
     * True when the selected window still includes the current business day,
     * so comparisons against a complete previous window must be disclosed as
     * partial rather than presented as like-for-like.
     */
    private static function isPartialPeriod(array $filters): bool
    {
        $to = (string) ($filters['to'] ?? '');
        if ($to === '') {
            return false;
        }
        $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
        return $to >= $today;
    }

    /**
     * Quarter buckets for the eight quarters ending with the selected range's
     * final quarter, so management can compare quarterly performance. Records
     * are streamed into buckets instead of collecting full rows, which keeps
     * the long window inside the request memory budget.
     */
    private function quarterlySeries(array $filters): array
    {
        $end = new DateTimeImmutable($filters['to']);
        $currentQuarterStart = $end->setDate((int) $end->format('Y'), (int) ceil((int) $end->format('n') / 3) * 3 - 2, 1);
        $windowStart = $currentQuarterStart->modify('-21 months');
        $windowFilters = $filters;
        $windowFilters['from'] = $windowStart->format('Y-m-d');
        $windowFilters['to'] = $end->format('Y-m-d');

        $buckets = [];
        $add = static function (string $date, int $cents, bool $isExpense, bool $isFuel) use (&$buckets): void {
            if ($date === '') {
                return;
            }
            $day = new DateTimeImmutable($date);
            $key = $day->format('Y') . '-Q' . (string) ceil((int) $day->format('n') / 3);
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['quarter' => $key, 'collections' => 0, 'expenses' => 0, 'fuel' => 0];
            }
            if ($isExpense) {
                $buckets[$key]['expenses'] += $cents;
                if ($isFuel) {
                    $buckets[$key]['fuel'] += $cents;
                }
            } else {
                $buckets[$key]['collections'] += $cents;
            }
        };

        $this->streamRevenueRows($windowFilters, static function (array $row) use ($add): void {
            $add((string) $row['date'], (int) $row['cents'], false, false);
        });
        $this->streamExpenseRows($windowFilters, static function (array $row) use ($add): void {
            $add((string) $row['date'], (int) $row['cents'], true, self::isFuelCategory((string) $row['category']));
        });

        ksort($buckets);
        $out = [];
        foreach (array_slice($buckets, -8, null, true) as $item) {
            $collections = round($item['collections'] / 100, 2);
            $expenses = round($item['expenses'] / 100, 2);
            $out[] = [
                'quarter' => $item['quarter'],
                'collections' => $collections,
                'expenses' => $expenses,
                'fuel' => round($item['fuel'] / 100, 2),
                'profit' => round($collections - $expenses, 2),
                'margin' => $collections > 0 ? round(($collections - $expenses) / $collections * 100, 2) : null,
            ];
        }
        return $out;
    }

    /**
     * Previous profit bridged to current profit through collection, fuel,
     * other-expense and configured-cost movements.
     */
    private function waterfall(array $filters, array $actual, ?array $previous): array
    {
        if ($previous === null) {
            return [];
        }
        $deltaCollections = round((float) $actual['collections'] - (float) $previous['collections'], 2);
        $deltaFuel = round((float) $actual['fuel'] - (float) $previous['fuel'], 2);
        $deltaOther = round((float) $actual['other_expenses'] - (float) $previous['other_expenses'], 2);
        $deltaConfigured = round((float) $actual['configured_costs'] - (float) ($previous['configured_costs'] ?? 0), 2);

        $steps = [
            ['label' => 'Estimated profit — previous period', 'type' => 'total', 'basis' => 'estimate', 'value' => (float) ($previous['profit_after_configured'] ?? $previous['profit'])],
            ['label' => 'Collections change', 'type' => $deltaCollections >= 0 ? 'positive' : 'negative', 'basis' => 'recorded', 'value' => $deltaCollections],
            ['label' => 'Fuel cost change', 'type' => $deltaFuel <= 0 ? 'positive' : 'negative', 'basis' => 'recorded', 'value' => round(-$deltaFuel, 2)],
            ['label' => 'Other expense change', 'type' => $deltaOther <= 0 ? 'positive' : 'negative', 'basis' => 'recorded', 'value' => round(-$deltaOther, 2)],
            ['label' => 'Configured costs change', 'type' => $deltaConfigured <= 0 ? 'positive' : 'negative', 'basis' => 'configured', 'value' => round(-$deltaConfigured, 2)],
            ['label' => 'Estimated profit — current period', 'type' => 'total', 'basis' => 'estimate', 'value' => (float) ($actual['profit_after_configured'] ?? $actual['profit'])],
        ];
        $cumulative = 0.0;
        foreach ($steps as &$step) {
            if ($step['type'] === 'total') {
                $cumulative = (float) $step['value'];
            } else {
                $cumulative = round($cumulative + (float) $step['value'], 2);
            }
            $step['cumulative'] = $cumulative;
        }
        unset($step);
        return $steps;
    }

    /**
     * Actionable findings with amounts and the recorded factors behind them.
     * Every finding states the figures it is based on and avoids conclusions
     * the figures do not support.
     */
    private function findings(
        array $filters,
        array $actual,
        ?array $previous,
        ?array $changes,
        array $current,
        ?array $previousAggregate = null,
        bool $previousAvailable = true
    ): array {
        $money = static fn($value): string => '₱' . number_format((float) $value, 2);
        $pct = static fn($value): string => $value === null ? 'n/a' : number_format((float) $value, 1) . '%';
        $findings = [];

        if (!$previousAvailable || $previous === null || $changes === null) {
            $findings[] = 'No comparable previous window with records was available, so period-over-period changes cannot be computed for this selection.';
        } else {
            $findings[] = 'Fare collections ' . ($changes['collections_delta'] >= 0 ? 'increased' : 'decreased') . ' by '
                . $money(abs((float) $changes['collections_delta'])) . ' (' . $pct($changes['collections_pct']) . ') versus the previous window.';
            $findings[] = 'Recorded expenses ' . ($changes['expenses_delta'] >= 0 ? 'increased' : 'decreased') . ' by '
                . $money(abs((float) $changes['expenses_delta'])) . ' (' . $pct($changes['expenses_pct']) . '), with fuel at '
                . ($actual['expenses'] > 0 ? number_format((float) $actual['fuel'] / (float) $actual['expenses'] * 100, 1) : '0.0') . '% of expenses.';
            $findings[] = 'Operating profit after configured costs ' . ($changes['profit_after_configured_delta'] >= 0 ? 'improved' : 'fell') . ' by '
                . $money(abs((float) $changes['profit_after_configured_delta'])) . ' (' . $pct($changes['profit_after_configured_pct']) . ').';
        }

        if ((float) ($actual['configured_costs'] ?? 0) > 0) {
            $findings[] = 'Configured monthly costs add ' . $money($actual['configured_costs'])
                . ' for this period; these are estimates from Financial Cost Settings, not posted transactions.';
        }

        if ($actual['margin_after_configured'] !== null) {
            $findings[] = 'Operating margin after configured costs is ' . number_format((float) $actual['margin_after_configured'], 1) . '% on '
                . $money($actual['collections']) . ' of collections.';
        } elseif ($actual['margin'] !== null) {
            $findings[] = 'Operating profit margin is ' . number_format((float) $actual['margin'], 1) . '% on '
                . $money($actual['collections']) . ' of collections.';
        } else {
            $findings[] = 'No fare collections were recorded for this selection, so a margin cannot be computed.';
        }

        $routes = array_values(array_filter($current['by_route'], static fn(array $row): bool => $row['trips'] > 0));
        $complete = array_values(array_filter($routes, static fn(array $row): bool => $row['cost_complete'] === true && $row['margin'] !== null));
        if ($complete !== []) {
            usort($complete, static fn(array $a, array $b): int => $a['margin'] <=> $b['margin']);
            $lowest = $complete[0];
            $findings[] = 'Lowest estimated margin among routes with cost records: ' . $lowest['route'] . ' at '
                . number_format((float) $lowest['margin'], 1) . '% (' . $money($lowest['profit']) . ' on ' . $money($lowest['collections']) . ').';
            $incomplete = count($routes) - count($complete);
            if ($incomplete > 0) {
                $findings[] = $incomplete . ' route(s) have trips but incomplete cost records; their profitability is not asserted.';
            }
        } else {
            $findings[] = 'Route-level profitability was not asserted because no route had both collections and attributable cost records.';
        }

        if (!$current['has_fuel_records'] || !$current['has_expense_records']) {
            $findings[] = 'Expense records are incomplete for this selection; treat profit and margin as estimates, not a closed accounting result.';
        }

        /* Negative estimated operating profit with its recorded components. */
        if ((float) $actual['collections'] > 0 && (float) ($actual['profit_after_configured'] ?? 0) < 0) {
            $findings[] = 'Negative estimated operating profit of ' . $money(abs((float) $actual['profit_after_configured']))
                . ' for the selection: ' . $money($actual['collections']) . ' collections, ' . $money($actual['expenses'])
                . ' recorded expenses and ' . $money($actual['configured_costs'] ?? 0)
                . ' configured cost estimates. These are the recorded components; the figures do not establish what caused the loss.';
        }

        /* Completed trips without any matching paid collection record. */
        $missing = $this->missingCollectionTrips($filters, $current['covered_exact'] ?? [], $current['covered_refs'] ?? []);
        if ($missing['count'] > 0) {
            $message = $missing['count'] . ' completed trip(s) in this selection have no matching paid collection records';
            if ($missing['routes'] !== []) {
                $message .= ' (routes: ' . implode(', ', $missing['routes']) . ')';
            }
            $findings[] = $message . '. Recorded trip counts may overstate collected fares for those trips.';
        }

        /* Unusual fuel price versus the trailing average. */
        $fuelDeviation = $this->fuelPriceDeviation($filters, $current['expense_rows']);
        if ($fuelDeviation !== null) {
            $direction = $fuelDeviation['deviation_pct'] >= 0 ? 'above' : 'below';
            $findings[] = 'Fuel price averaging ' . $money($fuelDeviation['observed']) . ' per liter for '
                . number_format($fuelDeviation['liters'], 2) . ' L recorded in this selection is '
                . number_format(abs($fuelDeviation['deviation_pct']), 1) . '% ' . $direction
                . ' the trailing average of ' . $money($fuelDeviation['baseline']) . ' per liter ('
                . $fuelDeviation['from'] . ' to ' . $fuelDeviation['to'] . ').';
        }

        /* Material recorded expense changes by category versus the previous window. */
        if ($previousAvailable && $previousAggregate !== null) {
            $previousCats = [];
            foreach ($previousAggregate['by_category'] as $row) {
                $previousCats[strtolower((string) $row['category'])] = (float) $row['amount'];
            }
            $expenseFindings = 0;
            foreach ($current['by_category'] as $row) {
                if ($expenseFindings >= 3) {
                    break;
                }
                $key = strtolower((string) $row['category']);
                $amount = (float) $row['amount'];
                $before = $previousCats[$key] ?? 0.0;
                if ($before <= 0.0 || $amount <= 0.0) {
                    continue;
                }
                $delta = $amount - $before;
                if (abs($delta) < self::EXPENSE_CHANGE_FLOOR) {
                    continue;
                }
                $pctChange = $delta / $before * 100;
                if (abs($pctChange) < self::EXPENSE_CHANGE_PCT) {
                    continue;
                }
                $findings[] = 'Recorded expense category ' . $row['category'] . ' '
                    . ($delta >= 0 ? 'increased' : 'decreased') . ' by ' . $money(abs($delta))
                    . ' (' . number_format($pctChange, 1) . '%) versus the previous window, from '
                    . $money($before) . ' to ' . $money($amount) . '.';
                $expenseFindings++;
            }
        }

        return $findings;
    }

    /**
     * Completed bus trips in the selected window whose trip reference never
     * appears on a paid collection row. The coverage sets are aggregated
     * while revenue rows stream, so no raw rows are retained here.
     *
     * @param array<string, bool> $coveredExact date|driver|trip keys
     * @param array<string, bool> $coveredRefs trip reference values
     * @return array{count: int, routes: list<string>, sample: list<string>}
     */
    private function missingCollectionTrips(array $filters, array $coveredExact, array $coveredRefs): array
    {
        $out = ['count' => 0, 'routes' => [], 'sample' => []];
        if (!$this->tableExists('bus_trips')) {
            return $out;
        }
        $date = $this->firstColumn('bus_trips', ['trip_date', 'created_at']);
        if ($date === null) {
            return $out;
        }
        $refs = array_values(array_filter(
            ['trip_no', 'trip_id', 'id'],
            fn(string $name): bool => $this->has('bus_trips', $name)
        ));
        if ($refs === []) {
            return $out;
        }

        $exactCovered = $coveredExact;
        $refCovered = $coveredRefs;

        $select = ["`$date` AS day"];
        foreach ($refs as $ref) {
            $select[] = "`$ref`";
        }
        foreach (['driver_id', 'route_name', 'status'] as $field) {
            if ($this->has('bus_trips', $field)) {
                $select[] = "`$field`";
            }
        }
        $until = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        $rows = $this->fetch(
            'SELECT ' . implode(', ', $select) . " FROM bus_trips WHERE `$date` >= :from AND `$date` < :until",
            [':from' => $filters['from'], ':until' => $until]
        );

        foreach ($rows as $row) {
            $status = trim((string) ($row['status'] ?? ''));
            if ($status !== '' && stripos($status, 'complet') === false) {
                continue;
            }
            $day = substr((string) ($row['day'] ?? ''), 0, 10);
            $driver = strtolower(trim((string) ($row['driver_id'] ?? '')));
            $covered = false;
            $sampleRef = '';
            foreach ($refs as $ref) {
                $value = trim((string) ($row[$ref] ?? ''));
                if ($value === '') {
                    continue;
                }
                if ($sampleRef === '') {
                    $sampleRef = $value;
                }
                if (isset($exactCovered[$day . '|' . $driver . '|' . $value]) || isset($refCovered[$value])) {
                    $covered = true;
                    break;
                }
            }
            if ($covered) {
                continue;
            }
            $out['count']++;
            $route = self::canonicalRouteName((string) ($row['route_name'] ?? ''));
            if ($route !== '' && !in_array($route, $out['routes'], true) && count($out['routes']) < 3) {
                $out['routes'][] = $route;
            }
            if ($sampleRef !== '' && count($out['sample']) < 3) {
                $out['sample'][] = $day . ' #' . $sampleRef;
            }
        }
        return $out;
    }

    /**
     * Weighted average fuel price for the selection versus the trailing
     * window average. Returns null when either side lacks priced purchases.
     *
     * @param list<array<string, mixed>> $expenseRows
     */
    private function fuelPriceDeviation(array $filters, array $expenseRows): ?array
    {
        $currentLiters = 0.0;
        $currentCost = 0.0;
        foreach ($expenseRows as $row) {
            if (!self::isFuelCategory((string) $row['category'])) {
                continue;
            }
            $liters = (float) ($row['liters'] ?? 0);
            if ($liters <= 0) {
                continue;
            }
            $currentLiters += $liters;
            $currentCost += (int) $row['cents'] / 100;
        }
        if ($currentLiters <= 0 || $currentCost <= 0) {
            return null;
        }

        $from = (new DateTimeImmutable($filters['from']));
        $trailingFilters = $filters;
        $trailingFilters['from'] = $from->modify('-' . self::FUEL_TRAILING_DAYS . ' days')->format('Y-m-d');
        $trailingFilters['to'] = $from->modify('-1 day')->format('Y-m-d');
        $trailingLiters = 0.0;
        $trailingCost = 0.0;
        foreach ($this->fuelPurchaseRows($trailingFilters) as $row) {
            $liters = (float) ($row['liters'] ?? 0);
            if ($liters <= 0) {
                continue;
            }
            $trailingLiters += $liters;
            $trailingCost += (int) $row['cents'] / 100;
        }
        if ($trailingLiters <= 0 || $trailingCost <= 0) {
            return null;
        }

        $observed = $currentCost / $currentLiters;
        $baseline = $trailingCost / $trailingLiters;
        $deviation = ($observed - $baseline) / $baseline * 100;
        if (abs($deviation) < self::FUEL_PRICE_DEVIATION_PCT) {
            return null;
        }
        return [
            'observed' => round($observed, 2),
            'baseline' => round($baseline, 2),
            'deviation_pct' => round($deviation, 1),
            'liters' => round($currentLiters, 2),
            'from' => (string) $trailingFilters['from'],
            'to' => (string) $trailingFilters['to'],
        ];
    }

    /* =========================================================
       SECTION: PASSENGER TYPES
    ========================================================= */

    public function passengers(array $filters): array
    {
        $current = $this->collect($filters, false);
        $types = $current['by_type'];

        $regular = null;
        foreach ($types as $type) {
            if ($type['type'] === 'Regular') {
                $regular = $type;
                break;
            }
        }
        $regularAvg = $regular !== null ? (float) $regular['avg_fare'] : 0.0;
        $totalDiscount = 0.0;
        foreach ($types as &$type) {
            if ($type['type'] === 'Regular' || $type['avg_fare'] === null) {
                $type['discount_impact_estimate'] = 0.0;
                $type['is_estimate'] = $type['type'] !== 'Regular';
                continue;
            }
            $impact = round(max(0.0, $regularAvg - (float) $type['avg_fare']) * (int) $type['trips'], 2);
            $type['discount_impact_estimate'] = $impact;
            $type['is_estimate'] = true;
            $totalDiscount += $impact;
        }
        unset($type);

        $months = [];
        foreach ($types as $type) {
            foreach ($type['by_month'] as $month => $amount) {
                if (!isset($months[$month])) {
                    $months[$month] = ['month' => $month, 'types' => []];
                }
                $months[$month]['types'][$type['type']] = $amount;
            }
        }
        ksort($months);

        $routeDistribution = [];
        foreach ($types as $type) {
            foreach ($type['by_route'] as $route => $count) {
                if (!isset($routeDistribution[$route])) {
                    $routeDistribution[$route] = ['route' => $route, 'total_trips' => 0, 'types' => []];
                }
                $routeDistribution[$route]['types'][$type['type']] = (int) $count;
                $routeDistribution[$route]['total_trips'] += (int) $count;
            }
        }
        usort($routeDistribution, static fn(array $a, array $b): int => $b['total_trips'] <=> $a['total_trips']);

        /* Journeys and accounts are different measures: a journey is a
           recorded trip record, an account is a unique passenger identity.
           They are reported side by side and never substituted. */
        $journeys = (int) ($current['totals']['passengers'] ?? 0);
        $registeredAccounts = null;
        if ($this->tableExists('passengers') && $this->has('passengers', 'id')) {
            $accountRows = $this->fetch('SELECT COUNT(*) AS accounts FROM passengers');
            if ($accountRows !== []) {
                $registeredAccounts = (int) ($accountRows[0]['accounts'] ?? 0);
            }
        }

        return [
            'period' => $this->periodPayload($filters),
            'types' => $types,
            'accounts' => [
                'journeys' => $journeys,
                'registered' => $registeredAccounts,
                'identified_in_scope' => (int) ($current['identified_passengers'] ?? 0),
                'labels' => [
                    'journeys' => 'Recorded passenger journeys (paid trip records)',
                    'registered' => 'Registered passenger accounts (unique identities)',
                ],
                'note' => 'Journeys count recorded trip records; registered accounts count unique passenger identities. They must not be substituted for each other.',
            ],
            'baggage' => [
                'trips' => (int) $current['totals']['baggage_count'],
                'collections' => (float) $current['totals']['baggage_collections'],
                'avg_fare' => (int) $current['totals']['baggage_count'] > 0
                    ? round((float) $current['totals']['baggage_collections'] / (int) $current['totals']['baggage_count'], 2)
                    : null,
                'category' => 'Baggage & extra fares',
            ],
            'monthly' => array_values($months),
            'by_route' => array_slice($routeDistribution, 0, 20),
            'discount_summary' => [
                'total_estimated_discount' => round($totalDiscount, 2),
                'baseline' => 'Regular passenger average realized fare',
                'note' => 'Discount impact is estimated as the Regular average realized fare minus each category average, times recorded trips. It is an estimate of foregone fare, not a posted expense.',
            ],
            'scope' => $this->scopeMetadata($filters, ['revenue']),
            'notes' => array_values($this->notes),
        ];
    }

    /* =========================================================
       SECTION: FARE MATRIX
    ========================================================= */

    /** @return list<array<string, mixed>> */
    public function fareSegments(): array
    {
        if (!$this->tableExists('fare_pricing')) {
            return [];
        }
        $select = ['id', 'route_name', 'destination', 'regular_fare', 'student_fare', 'special_fare'];
        if ($this->has('fare_pricing', 'display_order')) {
            $select[] = 'display_order';
        }
        if ($this->has('fare_pricing', 'updated_at')) {
            $select[] = 'updated_at';
        }
        if ($this->has('fare_pricing', 'created_at')) {
            $select[] = 'created_at';
        }
        $rows = $this->fetch('SELECT ' . implode(', ', $select) . ' FROM fare_pricing ORDER BY route_name, display_order');
        $segments = [];
        foreach ($rows as $row) {
            $route = self::canonicalRouteName((string) ($row['route_name'] ?? ''));
            if ($route === '') {
                continue;
            }
            $segments[] = [
                'id' => (int) ($row['id'] ?? 0),
                'route_name' => $route,
                'route_match_key' => self::routeKeyUnordered($route),
                'stored_route_name' => trim((string) ($row['route_name'] ?? '')),
                'destination' => trim((string) ($row['destination'] ?? '')),
                'regular_fare' => round((float) ($row['regular_fare'] ?? 0), 2),
                'student_fare' => round((float) ($row['student_fare'] ?? 0), 2),
                'special_fare' => round((float) ($row['special_fare'] ?? 0), 2),
                'display_order' => (int) ($row['display_order'] ?? 0),
                'updated_at' => (string) ($row['updated_at'] ?? $row['created_at'] ?? ''),
            ];
        }
        return $segments;
    }

    public function fare(array $filters): array
    {
        $current = $this->collect($filters, false);
        $segments = $this->fareSegments();

        $byRoute = [];
        foreach ($segments as $segment) {
            $route = (string) ($segment['route_match_key'] ?? '');
            if ($route === '') {
                continue;
            }
            if (!isset($byRoute[$route]) || $segment['display_order'] >= $byRoute[$route]['display_order']) {
                $byRoute[$route] = $segment;
            }
        }

        $matched = 0;
        $expectedCents = 0;
        $actualCents = 0;
        $routeStats = [];
        $unmatchedByCause = [
            'unresolved_route' => (int) ($current['fare_unresolved_route'] ?? 0),
            'matrix_row_missing' => 0,
            'fare_missing_for_category' => 0,
        ];
        $recordedRoutes = [];
        $matrixKeys = [];
        foreach ($byRoute as $segment) {
            $matrixKeys[(string) $segment['route_match_key']] = true;
        }
        /* Fare matching runs over the aggregated (route key, category, count,
           cents) groups built while streaming, so annual windows never retain
           the raw fare rows. */
        $eligible = 0;
        foreach ($current['fare_groups'] ?? [] as $routeKey => $group) {
            $rowsInGroup = (int) $group['rows'];
            $eligible += $rowsInGroup;
            $recordedRoutes[$routeKey] = ['route' => (string) $group['route'], 'rows' => $rowsInGroup, 'matched' => 0];
            $matrix = $byRoute[$routeKey] ?? null;
            if ($matrix === null) {
                $unmatchedByCause['matrix_row_missing'] += $rowsInGroup;
                continue;
            }
            foreach ($group['columns'] as $column => $columnData) {
                $columnRows = (int) $columnData['rows'];
                $expected = (float) $matrix[$column];
                if ($expected <= 0) {
                    $unmatchedByCause['fare_missing_for_category'] += $columnRows;
                    continue;
                }
                $matched += $columnRows;
                $expectedCents += (int) round($expected * 100) * $columnRows;
                $actualCents += (int) $columnData['cents'];
                $recordedRoutes[$routeKey]['matched'] += $columnRows;
                if (!isset($routeStats[$routeKey])) {
                    $routeStats[$routeKey] = ['route' => $matrix['route_name'], 'matched' => 0, 'expected' => 0, 'actual' => 0];
                }
                $routeStats[$routeKey]['matched'] += $columnRows;
                $routeStats[$routeKey]['expected'] += (int) round($expected * 100) * $columnRows;
                $routeStats[$routeKey]['actual'] += (int) $columnData['cents'];
            }
        }

        $totalRows = $eligible + (int) $unmatchedByCause['unresolved_route'];
        /* Eligible = non-baggage journeys whose route identity resolves; the
           coverage measure answers "of the journeys that CAN be matched, how
           many did", so route-less rows are reported as an unmatched cause
           instead of silently deflating the percentage. */
        $matchedTotal = $matched;
        $unmatchedTotal = 0;
        foreach ($unmatchedByCause as $count) {
            $unmatchedTotal += $count;
        }
        $coverage = $eligible > 0 ? round($matched / $eligible * 100, 1) : null;
        if ($eligible === 0 && $unmatchedTotal > 0) {
            $this->note('No recorded journey in this scope has a resolvable route identity, so no fare row can be matched; the unmatched causes below state why.');
        }

        $unmatchedOut = [];
        $causeLabels = [
            'unresolved_route' => 'Journey route identity could not be resolved',
            'matrix_row_missing' => 'No fare matrix row for the recorded route',
            'fare_missing_for_category' => 'Fare matrix row has no fare for the passenger category',
        ];
        foreach ($unmatchedByCause as $cause => $count) {
            if ($count <= 0) {
                continue;
            }
            $unmatchedOut[] = ['cause' => $cause, 'label' => $causeLabels[$cause], 'rows' => $count];
        }

        $recordedRoutesOut = array_values($recordedRoutes);
        usort($recordedRoutesOut, static fn(array $a, array $b): int => $b['rows'] <=> $a['rows']);
        $intersection = [];
        foreach ($recordedRoutes as $key => $entry) {
            if (isset($matrixKeys[$key])) {
                $intersection[] = ['route' => $entry['route'], 'matched_rows' => $entry['matched'], 'rows' => $entry['rows']];
            }
        }

        $applied = [];
        foreach ($routeStats as $stat) {
            $variance = round(($stat['expected'] - $stat['actual']) / 100, 2);
            $applied[] = [
                'route' => $stat['route'],
                'matched_rows' => $stat['matched'],
                'expected_collections' => round($stat['expected'] / 100, 2),
                'actual_collections' => round($stat['actual'] / 100, 2),
                'variance' => $variance,
                'variance_pct' => $stat['actual'] > 0 ? round($variance / ($stat['actual'] / 100) * 100, 2) : null,
            ];
        }
        usort($applied, static fn(array $a, array $b): int => abs((float) $b['variance']) <=> abs((float) $a['variance']));

        return [
            'period' => $this->periodPayload($filters),
            'segments' => $segments,
            'segment_count' => count($segments),
            'routes' => array_values(array_unique(array_map(static fn(array $s): string => $s['route_name'], $segments))),
            'last_updated' => $segments !== [] ? max(array_map(static fn(array $s): string => (string) $s['updated_at'], $segments)) : '',
            'applied' => [
                'coverage_pct' => $coverage,
                'matched_rows' => $matchedTotal,
                'eligible_rows' => $eligible,
                'unmatched_rows' => $unmatchedTotal,
                'unmatched_causes' => $unmatchedOut,
                'total_rows' => $totalRows,
                'expected_collections' => round($expectedCents / 100, 2),
                'actual_collections' => round($actualCents / 100, 2),
                'variance' => round(($expectedCents - $actualCents) / 100, 2),
                'by_route' => $applied,
                'recorded_routes' => $recordedRoutesOut,
                'matrix_routes' => array_values(array_map(static fn(array $s): string => (string) $s['route_name'], $byRoute)),
                'intersection' => $intersection,
                'coverage_basis' => 'coverage = matched eligible journeys ÷ all journeys whose route identity resolves; route-unresolved and matrix-missing rows are reported separately',
                'note' => 'Expected collections apply the current fare matrix per passenger type to recorded volumes where the trip route matches a matrix route; unmatched trips are excluded, so this is an estimate of matrix alignment, not a re-statement of collections.',
            ],
            'change_tracking' => $this->fareChangeTracking($filters, $segments),
            'scope' => $this->scopeMetadata($filters, ['revenue']),
            'notes' => array_values($this->notes),
        ];
    }

    /**
     * Compare realized fares before and after the latest matrix update when
     * the update falls inside the selected range and history exists on both
     * sides. Otherwise report that the change is not observable here.
     */
    private function fareChangeTracking(array $filters, array $segments): array
    {
        $updatedAt = '';
        foreach ($segments as $segment) {
            if ((string) $segment['updated_at'] > $updatedAt) {
                $updatedAt = (string) $segment['updated_at'];
            }
        }
        if ($updatedAt === '') {
            return ['observable' => false, 'basis' => 'No fare matrix update timestamp is available.', 'before' => null, 'after' => null];
        }
        $updateDate = substr($updatedAt, 0, 10);
        if ($updateDate < $filters['from'] || $updateDate > $filters['to']) {
            return [
                'observable' => false,
                'basis' => 'The latest fare matrix update (' . $updateDate . ') is outside the selected range, so before/after collections cannot be compared here.',
                'before' => null,
                'after' => null,
                'last_updated' => $updatedAt,
            ];
        }

        $beforeFilters = $filters;
        $beforeFilters['from'] = $filters['from'];
        $beforeFilters['to'] = (new DateTimeImmutable($updateDate))->modify('-1 day')->format('Y-m-d');
        $afterFilters = $filters;
        $afterFilters['from'] = $updateDate;
        $afterFilters['to'] = $filters['to'];

        $before = null;
        if ($beforeFilters['from'] <= $beforeFilters['to']) {
            $beforeSnap = $this->collect($beforeFilters, false);
            $before = [
                'from' => $beforeFilters['from'],
                'to' => $beforeFilters['to'],
                'collections' => $beforeSnap['totals']['collections'],
                'avg_fare' => $beforeSnap['totals']['avg_fare'],
                'passengers' => $beforeSnap['totals']['passengers'],
            ];
        }
        $afterSnap = $this->collect($afterFilters, false);
        $after = [
            'from' => $afterFilters['from'],
            'to' => $afterFilters['to'],
            'collections' => $afterSnap['totals']['collections'],
            'avg_fare' => $afterSnap['totals']['avg_fare'],
            'passengers' => $afterSnap['totals']['passengers'],
        ];

        $avgDelta = ($before !== null && $before['avg_fare'] !== null && $after['avg_fare'] !== null)
            ? round((float) $after['avg_fare'] - (float) $before['avg_fare'], 2)
            : null;

        return [
            'observable' => $before !== null,
            'basis' => 'Realized fares recorded before and after the latest fare matrix update at ' . $updatedAt . '.',
            'last_updated' => $updatedAt,
            'before' => $before,
            'after' => $after,
            'avg_fare_change' => $avgDelta,
            'note' => 'This compares actual recorded fares around the matrix update; other factors (volume, route mix) can also move the averages.',
        ];
    }

    /* =========================================================
       SECTION: FUEL
    ========================================================= */

    private function fuelPriceRows(): array
    {
        if (!$this->tableExists('fuel_prices')) {
            return [];
        }
        $rows = $this->fetch("SELECT * FROM fuel_prices WHERE fuel_type LIKE '%diesel%' ORDER BY id");
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'fuel_type' => (string) ($row['fuel_type'] ?? 'Diesel'),
                'price_per_liter' => round((float) ($row['price_per_liter'] ?? 0), 2),
                'updated_at' => (string) ($row['updated_at'] ?? $row['created_at'] ?? ''),
            ];
        }
        return $out;
    }

    private function fuelDataWindow(): array
    {
        $from = null;
        $to = null;
        $records = 0;
        foreach ([['fuel_refuels', 'created_at'], ['fuel_logs', 'created_at']] as [$table, $column]) {
            if (!$this->tableExists($table) || !$this->has($table, $column)) {
                continue;
            }
            $row = $this->fetch("SELECT MIN(`$column`) AS min_date, MAX(`$column`) AS max_date, COUNT(*) AS record_count FROM `$table`")[0] ?? null;
            if ($row === null) {
                continue;
            }
            $min = $row['min_date'] !== null ? substr((string) $row['min_date'], 0, 10) : null;
            $max = $row['max_date'] !== null ? substr((string) $row['max_date'], 0, 10) : null;
            if ($min !== null && ($from === null || strcmp($min, $from) < 0)) {
                $from = $min;
            }
            if ($max !== null && ($to === null || strcmp($max, $to) > 0)) {
                $to = $max;
            }
            $records += (int) ($row['record_count'] ?? 0);
        }
        return ['from' => $from, 'to' => $to, 'records' => $records];
    }

    public function fuel(array $filters): array
    {
        $purchases = $this->fuelPurchaseRows($filters);
        $operational = $this->fuelOperationalRows($filters);
        $prices = $this->fuelPriceRows();

        $liters = 0.0;
        $cost = 0.0;
        $monthly = [];
        $byBus = [];
        foreach ($purchases as $row) {
            $rowLiters = (float) $row['liters'];
            $rowCost = (int) $row['cents'] / 100;
            $liters += $rowLiters;
            $cost += $rowCost;
            $month = substr((string) $row['date'], 0, 7);
            if (!isset($monthly[$month])) {
                $monthly[$month] = ['month' => $month, 'liters' => 0.0, 'cost' => 0.0, 'purchase_count' => 0];
            }
            $monthly[$month]['liters'] += $rowLiters;
            $monthly[$month]['cost'] += $rowCost;
            $monthly[$month]['purchase_count']++;
            $bus = (string) $row['bus'];
            if ($bus !== '') {
                if (!isset($byBus[$bus])) {
                    $byBus[$bus] = ['bus' => $bus, 'liters' => 0.0, 'cost' => 0.0, 'purchases' => 0];
                }
                $byBus[$bus]['liters'] += $rowLiters;
                $byBus[$bus]['cost'] += $rowCost;
                $byBus[$bus]['purchases']++;
            }
        }

        $distance = 0.0;
        $serviceLiters = 0.0;
        $trips = [];
        $kplValues = [];
        $measurements = ['recorded' => 0, 'derived' => 0, 'missing' => 0, 'zero' => 0];
        $monthlyOps = [];
        foreach ($operational as $row) {
            $distance += (float) $row['distance'];
            $serviceLiters += (float) $row['liters'];
            $measurement = (string) ($row['measurement'] ?? 'missing');
            $measurements[$measurement] = ($measurements[$measurement] ?? 0) + 1;
            if ((float) $row['kpl'] > 0) {
                $kplValues[] = (float) $row['kpl'];
            }
            if ((string) $row['trip'] !== '') {
                $trips[(string) $row['date'] . '|' . (string) $row['trip']] = true;
            }
            $month = substr((string) $row['date'], 0, 7);
            if (!isset($monthlyOps[$month])) {
                $monthlyOps[$month] = ['distance' => 0.0, 'liters' => 0.0, 'trips' => [], 'kpl_sum' => 0.0, 'kpl_count' => 0, 'measurements' => ['recorded' => 0, 'derived' => 0, 'missing' => 0, 'zero' => 0]];
            }
            $monthlyOps[$month]['distance'] += (float) $row['distance'];
            $monthlyOps[$month]['liters'] += (float) $row['liters'];
            $monthlyOps[$month]['measurements'][$measurement] = ($monthlyOps[$month]['measurements'][$measurement] ?? 0) + 1;
            if ((float) $row['kpl'] > 0) {
                $monthlyOps[$month]['kpl_sum'] += (float) $row['kpl'];
                $monthlyOps[$month]['kpl_count']++;
            }
            if ((string) $row['trip'] !== '') {
                $monthlyOps[$month]['trips'][(string) $row['date'] . '|' . (string) $row['trip']] = true;
            }
            $bus = (string) $row['bus'];
            if ($bus !== '') {
                if (!isset($byBus[$bus])) {
                    $byBus[$bus] = ['bus' => $bus, 'liters' => 0.0, 'cost' => 0.0, 'purchases' => 0];
                }
                $byBus[$bus]['distance'] = ($byBus[$bus]['distance'] ?? 0.0) + (float) $row['distance'];
                $byBus[$bus]['service_liters'] = ($byBus[$bus]['service_liters'] ?? 0.0) + (float) $row['liters'];
                if ((float) $row['kpl'] > 0) {
                    $byBus[$bus]['kpl_sum'] = ($byBus[$bus]['kpl_sum'] ?? 0.0) + (float) $row['kpl'];
                    $byBus[$bus]['kpl_count'] = ($byBus[$bus]['kpl_count'] ?? 0) + 1;
                    if ($measurement === 'derived') {
                        $byBus[$bus]['kpl_derived'] = ($byBus[$bus]['kpl_derived'] ?? 0) + 1;
                    }
                }
            }
        }

        $tripCount = count($trips);
        $summary = [
            'liters' => round($liters, 2),
            'purchase_cost' => round($cost, 2),
            'avg_price' => $liters > 0 ? round($cost / $liters, 2) : ($prices !== [] ? (float) end($prices)['price_per_liter'] : null),
            'purchases' => count($purchases),
            'service_distance_km' => round($distance, 2),
            'service_liters' => round($serviceLiters, 2),
            'trips_measured' => $tripCount,
            'cost_per_km' => $distance > 0 && $cost > 0 ? round($cost / $distance, 2) : null,
            'cost_per_trip' => $tripCount > 0 && $cost > 0 ? round($cost / $tripCount, 2) : null,
            'avg_kpl' => $kplValues !== [] ? round(array_sum($kplValues) / count($kplValues), 2) : null,
            'avg_kpl_source' => $kplValues !== [] ? 'recorded and derived measurements' : null,
            'measurements' => $measurements,
            'liters_per_trip' => $tripCount > 0 && $serviceLiters > 0 ? round($serviceLiters / $tripCount, 2) : null,
        ];

        $busOut = [];
        foreach ($byBus as $bus) {
            $kpl = ($bus['kpl_count'] ?? 0) > 0 ? round($bus['kpl_sum'] / $bus['kpl_count'], 2) : null;
            $busDistance = (float) ($bus['distance'] ?? 0);
            $busOut[] = [
                'bus' => $bus['bus'],
                'liters' => round($bus['liters'], 2),
                'cost' => round($bus['cost'], 2),
                'purchases' => $bus['purchases'],
                'distance_km' => round($busDistance, 2),
                'avg_kpl' => $kpl,
                'derived_kpl_rows' => (int) ($bus['kpl_derived'] ?? 0),
                'cost_per_km' => $busDistance > 0 && (float) $bus['cost'] > 0 ? round($bus['cost'] / $busDistance, 2) : null,
                'cost_per_liter' => $bus['liters'] > 0 ? round($bus['cost'] / $bus['liters'], 2) : null,
            ];
        }
        usort($busOut, static fn(array $a, array $b): int => $b['cost'] <=> $a['cost']);

        $months = array_values(array_unique(array_merge(array_keys($monthly), array_keys($monthlyOps))));
        sort($months);
        $monthlyOut = [];
        foreach ($months as $monthKey) {
            $month = $monthly[$monthKey] ?? ['month' => $monthKey, 'liters' => 0.0, 'cost' => 0.0, 'purchase_count' => 0];
            $ops = $monthlyOps[$monthKey] ?? ['distance' => 0.0, 'liters' => 0.0, 'trips' => [], 'kpl_sum' => 0.0, 'kpl_count' => 0, 'measurements' => ['recorded' => 0, 'derived' => 0, 'missing' => 0, 'zero' => 0]];
            $tripCount = count($ops['trips']);
            $monthlyOut[] = [
                'month' => $month['month'],
                'liters' => round($month['liters'], 2),
                'cost' => round($month['cost'], 2),
                'purchases' => $month['purchase_count'],
                'avg_price' => $month['liters'] > 0 ? round($month['cost'] / $month['liters'], 2) : null,
                'distance_km' => round($ops['distance'], 2),
                'trips' => $tripCount,
                'avg_kpl' => $ops['kpl_count'] > 0 ? round($ops['kpl_sum'] / $ops['kpl_count'], 2) : null,
                'measurements' => $ops['measurements'],
                'cost_per_km' => $ops['distance'] > 0 && $month['cost'] > 0 ? round($month['cost'] / $ops['distance'], 2) : null,
                'cost_per_trip' => $tripCount > 0 && $month['cost'] > 0 ? round($month['cost'] / $tripCount, 2) : null,
                'liters_per_trip' => $tripCount > 0 && $ops['liters'] > 0 ? round($ops['liters'] / $tripCount, 2) : null,
            ];
        }
        usort($monthlyOut, static fn(array $a, array $b): int => strcmp($a['month'], $b['month']));

        $priceVsConsumption = [];
        foreach ($monthlyOut as $month) {
            $priceVsConsumption[] = [
                'month' => $month['month'],
                'avg_price' => $month['avg_price'],
                'liters' => $month['liters'],
                'cost' => $month['cost'],
            ];
        }

        $byRoute = $this->fuelByRoute($filters);

        $operatingFuel = 0.0;
        $hasLedgerFuel = false;
        foreach ($this->expenseRows($filters) as $row) {
            if (!self::isFuelCategory((string) $row['category'])) {
                continue;
            }
            $operatingFuel += (int) $row['cents'] / 100;
            if (($row['is_fuel_purchase'] ?? false) !== true) {
                $hasLedgerFuel = true;
            }
        }

        return [
            'period' => $this->periodPayload($filters),
            'prices' => array_slice($prices, -24, null, true),
            'latest_price' => $prices !== [] ? end($prices) : null,
            'data_window' => $this->fuelDataWindow(),
            'summary' => $summary,
            'monthly' => $monthlyOut,
            'by_bus' => array_slice($busOut, 0, 50),
            'by_route' => $byRoute,
            'price_vs_consumption' => $priceVsConsumption,
            'operating' => [
                'fuel_cost' => round($operatingFuel, 2),
                'source' => $hasLedgerFuel ? 'recorded_expenses' : 'purchase_receipts',
                'note' => $hasLedgerFuel
                    ? 'Operating totals use the recorded fuel expenses for this scope; the purchases below are receipts shown for volume and price detail, so their total can differ.'
                    : 'Operating totals use these refuel receipts because no fuel expense is posted for this period.',
            ],
            'double_counting' => [
                'policy' => 'Operating totals use recorded fuel expenses when they exist; otherwise actual refuel receipts are used once, with duplicate receipts for the same bus excluded. This section always lists actual purchases and never adds them to revenue.',
                'sources' => ['actual refuel receipts', 'legacy fuel purchases with recorded liters', 'recorded fuel expense entries', 'recorded diesel prices'],
            ],
            'scope' => $this->scopeMetadata($filters, ['fuel']),
            'notes' => array_values($this->notes),
        ];
    }

    /**
     * Fuel cost by route where the fuel record carries a trip reference that
     * resolves to a route; otherwise the row is reported as unattributed.
     */
    private function fuelByRoute(array $filters): array
    {
        $tripIndex = $this->tripIndex($filters['from'], $filters['to']);
        $routes = [];
        $unattributed = ['cost' => 0.0, 'liters' => 0.0, 'rows' => 0];
        foreach ($this->fuelPurchaseRows($filters) as $row) {
            $route = trim((string) ($row['route'] ?? ''));
            if ($route === '' && (string) $row['trip'] !== '') {
                $resolved = self::resolveTripRouteStatic($tripIndex, (string) $row['date'], (string) $row['driver'], (string) $row['trip']);
                $route = $resolved['route'];
            }
            if ($route === '') {
                $route = self::busRouteFor($tripIndex, (string) $row['bus'], (string) $row['date']);
            }
            $cost = (int) $row['cents'] / 100;
            $liters = (float) $row['liters'];
            if ($route === '') {
                $unattributed['cost'] += $cost;
                $unattributed['liters'] += $liters;
                $unattributed['rows']++;
                continue;
            }
            if (!isset($routes[$route])) {
                $routes[$route] = ['route' => $route, 'cost' => 0.0, 'liters' => 0.0, 'rows' => 0];
            }
            $routes[$route]['cost'] += $cost;
            $routes[$route]['liters'] += $liters;
            $routes[$route]['rows']++;
        }
        $out = [];
        foreach ($routes as $route) {
            $out[] = [
                'route' => $route['route'],
                'cost' => round($route['cost'], 2),
                'liters' => round($route['liters'], 2),
                'records' => $route['rows'],
                'reliable' => $route['rows'] >= 3,
            ];
        }
        usort($out, static fn(array $a, array $b): int => $b['cost'] <=> $a['cost']);
        return [
            'routes' => $out,
            'unattributed' => [
                'cost' => round($unattributed['cost'], 2),
                'liters' => round($unattributed['liters'], 2),
                'records' => $unattributed['rows'],
            ],
            'note' => 'Fuel is charged to a route only when its trip reference resolves to exactly one route; unmatched purchases stay unattributed.',
        ];
    }

    /* =========================================================
       SECTION: ROUTES & FLEET
    ========================================================= */

    public function fleet(array $filters): array
    {
        $current = $this->collect($filters, false);
        $maintenance = $this->maintenanceByRoute($filters, $current['expense_rows']);
        $activity = $this->completedTripRoutes($filters);

        $routes = [];
        $seenRoutes = [];
        foreach ($current['by_route'] as $row) {
            $routeTrips = (int) $row['trips'];
            if (isset($activity[(string) $row['route']])) {
                $routeTrips = max($routeTrips, (int) $activity[(string) $row['route']]);
            }
            $seenRoutes[(string) $row['route']] = true;
            $routes[] = [
                'route' => $row['route'],
                'trips' => $routeTrips,
                'passengers' => $row['passengers'],
                'collections' => $row['collections'],
                'attributable_expenses' => $row['expenses'],
                'fuel_cost' => $row['fuel'],
                'maintenance_cost' => $maintenance[(string) $row['route']] ?? 0.0,
                'cost_per_trip' => $routeTrips > 0 ? round((float) $row['expenses'] / $routeTrips, 2) : null,
                'estimated_profit' => $row['cost_complete'] ? $row['profit'] : null,
                'estimated_margin' => $row['cost_complete'] && $row['margin'] !== null ? $row['margin'] : null,
                'cost_complete' => $row['cost_complete'],
                'ranking_eligible' => $row['cost_complete'] && $routeTrips > 0,
            ];
        }
        /* Operating routes are derived from completed recorded trips, so a
           route with trips but no revenue or cost records still appears (with
           its profitability withheld) and a history-only scope cannot erase
           the routes that actually ran. */
        foreach ($activity as $routeName => $trips) {
            if (isset($seenRoutes[$routeName])) {
                continue;
            }
            $routes[] = [
                'route' => $routeName,
                'trips' => (int) $trips,
                'passengers' => 0,
                'collections' => 0.0,
                'attributable_expenses' => 0.0,
                'fuel_cost' => 0.0,
                'maintenance_cost' => 0.0,
                'cost_per_trip' => null,
                'estimated_profit' => null,
                'estimated_margin' => null,
                'cost_complete' => false,
                'ranking_eligible' => false,
            ];
        }
        usort($routes, static function (array $a, array $b): int {
            if ($a['estimated_margin'] === null && $b['estimated_margin'] === null) {
                return $b['collections'] <=> $a['collections'];
            }
            if ($a['estimated_margin'] === null) {
                return 1;
            }
            if ($b['estimated_margin'] === null) {
                return -1;
            }
            return $a['estimated_margin'] <=> $b['estimated_margin'];
        });

        $buses = [];
        foreach ($current['by_bus'] as $row) {
            $buses[] = [
                'bus' => $row['bus'],
                'trips' => $row['trips'],
                'passengers' => $row['passengers'],
                'collections' => $row['collections'],
                'attributable_expenses' => $row['expenses'],
                'fuel_cost' => $row['fuel'],
                'cost_per_trip' => $row['trips'] > 0 ? round((float) $row['expenses'] / $row['trips'], 2) : null,
                'estimated_profit' => $row['cost_complete'] ? $row['profit'] : null,
                'estimated_margin' => $row['cost_complete'] ? $row['margin'] : null,
                'cost_complete' => $row['cost_complete'],
            ];
        }

        $unattributedCollections = ((int) ($current['unattributed_totals']['collections_cents'] ?? 0)) / 100;
        $unattributedTrips = (int) ($current['unattributed_totals']['records_with_reference'] ?? 0);
        $routeCollections = 0.0;
        foreach ($current['by_route'] as $row) {
            $routeCollections += (float) $row['collections'];
        }
        $totalCollections = (float) ($current['totals']['collections'] ?? 0);
        $unattributedCents = (int) round($unattributedCollections * 100);
        $routeCents = (int) round($routeCollections * 100);
        $totalCents = (int) round($totalCollections * 100);

        return [
            'period' => $this->periodPayload($filters),
            'routes' => $routes,
            'buses' => $buses,
            'unattributed' => [
                'collections' => round($unattributedCollections, 2),
                'trips' => $unattributedTrips,
                'causes' => $current['attribution'] ?? [],
            ],
            'reconciliation' => [
                'route_collections' => round($routeCollections, 2),
                'unattributed_collections' => round($unattributedCollections, 2),
                'total_collections' => round($totalCollections, 2),
                'balanced' => ($routeCents + $unattributedCents) === $totalCents,
            ],
            'route_activity' => [
                'completed_trips' => array_sum($activity),
                'routes_with_trips' => count($activity),
            ],
            'scope' => $this->scopeMetadata($filters, ['revenue', 'expenses', 'fuel']),
            'notes' => array_merge(array_values($this->notes), [
                'Costs are attributed through the identifiers stored on each record and unique trip matches; ambiguous attribution is left unattributed rather than guessed.',
                'A route or bus with trips but no attributable cost records is marked cost-incomplete; its profitability is not asserted.',
                'Route rows are seeded from completed recorded trips as well as attributed collections, so a zero-route view means no qualifying trip and no attributable collection exist for the scope.',
            ]),
        ];
    }

    /**
     * Completed recorded trips per canonical route in the selected window.
     * Configuration-excluded labels are not operating routes and are surfaced
     * through the unattributed explanation instead.
     *
     * @return array<string, int> route => completed trip count
     */
    private function completedTripRoutes(array $filters): array
    {
        if (!$this->tableExists('bus_trips') || !$this->has('bus_trips', 'route_name')) {
            return [];
        }
        $date = $this->firstColumn('bus_trips', ['trip_date', 'created_at']);
        if ($date === null) {
            return [];
        }
        $until = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        $where = "`$date` >= :from AND `$date` < :until";
        if ($this->has('bus_trips', 'status')) {
            $where .= " AND LOWER(TRIM(status)) = 'completed'";
        }
        $out = [];
        foreach ($this->fetch("SELECT route_name, COUNT(*) AS trips FROM bus_trips WHERE $where GROUP BY route_name", [':from' => $filters['from'], ':until' => $until]) as $row) {
            $route = self::canonicalRouteName((string) ($row['route_name'] ?? ''));
            if ($route === '') {
                continue;
            }
            if ($filters['route'] !== '' && !self::routesMatch($route, (string) $filters['route'])) {
                continue;
            }
            $out[$route] = ($out[$route] ?? 0) + (int) ($row['trips'] ?? 0);
        }
        return $out;
    }

    /**
     * Maintenance totals per canonical route, resolved once from the already
     * collected expense rows instead of refetching expenses per route.
     *
     * @param list<array<string, mixed>> $expenseRows
     * @return array<string, float>
     */
    private function maintenanceByRoute(array $filters, array $expenseRows): array
    {
        $tripIndex = $this->tripIndex($filters['from'], $filters['to']);
        $totals = [];
        foreach ($expenseRows as $row) {
            if (!self::isMaintenanceCategory((string) $row['category'])) {
                continue;
            }
            $route = trim((string) $row['route']);
            if ($route === '' && (string) $row['trip'] !== '') {
                $route = self::resolveTripRouteStatic($tripIndex, (string) $row['date'], (string) $row['driver'], (string) $row['trip'])['route'];
            }
            if ($route === '') {
                continue;
            }
            $totals[$route] = ($totals[$route] ?? 0.0) + (int) $row['cents'] / 100;
        }
        foreach ($totals as $route => $total) {
            $totals[$route] = round($total, 2);
        }
        return $totals;
    }

    /* =========================================================
       SECTION: REMITTANCE & DISCREPANCIES
    ========================================================= */

    public function remittance(array $filters): array
    {
        $from = $filters['from'];
        $until = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        $rows = [];
        $dateExpr = '';
        if ($this->tableExists('remittance_logs')) {
            /* Documented date fallback: the recorded business date first, then
               the creation timestamp, then verification. Rows with none of
               them are reported as undated instead of disappearing. */
            $dateParts = [];
            if ($this->has('remittance_logs', 'business_date')) {
                $dateParts[] = 'r.business_date';
            }
            if ($this->has('remittance_logs', 'created_at')) {
                $dateParts[] = 'DATE(r.created_at)';
            }
            if ($this->has('remittance_logs', 'verified_at')) {
                $dateParts[] = 'DATE(r.verified_at)';
            }
            if ($dateParts !== []) {
                $dateExpr = count($dateParts) === 1 ? $dateParts[0] : 'COALESCE(' . implode(', ', $dateParts) . ')';
                $select = ["$dateExpr AS report_date"];
                /* trip_no, status and verified_at are read by the attribution
                   and settlement logic and must therefore be selected. */
                foreach (['driver_id', 'bus_no', 'total_collection', 'expected_cash', 'actual_cash', 'status', 'discrepancy_amount', 'trip_no', 'verified_at'] as $column) {
                    if ($this->has('remittance_logs', $column)) {
                        $select[] = "r.`$column`";
                    }
                }
                $rows = $this->fetch('SELECT ' . implode(', ', $select) . " FROM remittance_logs r WHERE $dateExpr >= :from AND $dateExpr < :until", [':from' => $from, ':until' => $until]);
            } else {
                $this->note('Remittance records expose no usable date field; expected versus actual analysis is unavailable.');
            }
        } elseif ($this->tableExists('remittances')) {
            $rows = $this->fetch('SELECT created_at AS report_date, driver_id, bus_no, total_collection, expected_cash, actual_cash, status, discrepancy_amount FROM remittances WHERE created_at >= :from AND created_at < :until', [':from' => $from, ':until' => $until]);
        } else {
            $this->note('Remittance records are unavailable; expected versus actual remittance analysis cannot be shown for this period.');
        }

        $undated = ['rows' => 0, 'expected_cash' => 0.0];
        if ($dateExpr !== '' && $this->tableExists('remittance_logs')) {
            $expectedColumn = $this->has('remittance_logs', 'expected_cash') ? 'COALESCE(SUM(r.expected_cash), 0)' : '0';
            $undatedRows = $this->fetch("SELECT COUNT(*) AS rows_count, $expectedColumn AS expected_total FROM remittance_logs r WHERE $dateExpr IS NULL");
            if ($undatedRows !== []) {
                $undated['rows'] = (int) ($undatedRows[0]['rows_count'] ?? 0);
                $undated['expected_cash'] = round((float) ($undatedRows[0]['expected_total'] ?? 0), 2);
            }
            if ($undated['rows'] > 0) {
                $this->note($undated['rows'] . ' remittance record(s) carry no recorded date (business date, created, or verified) and are reported as undated rather than assigned to a period.');
            }
        }

        /* Bus and route filters apply where the settlement can be attributed:
           a stored bus reference, a unique trip match, or a unique driver/day
           match. Unattributable records leave the filtered view but their
           counts and amounts are reported in an explicit bucket. */
        $driverFilter = null;
        $excludedByScope = ['rows' => 0, 'expected_cash' => 0.0, 'actual_cash' => 0.0, 'reason' => ''];
        if (($filters['bus'] !== '' || $filters['route'] !== '') && $rows !== []) {
            $tripIndex = $this->tripIndex($filters['from'], $filters['to']);
            $busAliases = $this->busAliases();
            $filtered = [];
            $keptDrivers = [];
            $droppedBus = 0;
            $droppedRoute = 0;
            foreach ($rows as $row) {
                $day = substr((string) ($row['report_date'] ?? ''), 0, 10);
                $driver = trim((string) ($row['driver_id'] ?? ''));
                $trip = trim((string) ($row['trip_no'] ?? ''));
                $bus = trim((string) ($row['bus_no'] ?? ''));
                $route = '';
                if ($trip !== '') {
                    $resolved = self::resolveTripRouteStatic($tripIndex, $day, $driver, $trip);
                    if ($bus === '') {
                        $bus = $resolved['bus'];
                    }
                    $route = $resolved['route'];
                }
                if ($bus === '') {
                    $bus = self::driverBusFor($tripIndex, $driver, $day);
                }
                if ($route === '') {
                    $route = self::driverRouteFor($tripIndex, $driver, $day);
                }
                if ($bus !== '' && isset($busAliases[strtolower($bus)])) {
                    $bus = $busAliases[strtolower($bus)];
                }
                if ($filters['bus'] !== '' && ($bus === '' || strcasecmp($bus, (string) $filters['bus']) !== 0)) {
                    $droppedBus++;
                    $excludedByScope['rows']++;
                    $excludedByScope['expected_cash'] += (float) ($row['expected_cash'] ?? 0);
                    $excludedByScope['actual_cash'] += (float) ($row['actual_cash'] ?? 0);
                    continue;
                }
                if ($filters['route'] !== '' && ($route === '' || !self::routesMatch($route, (string) $filters['route']))) {
                    $droppedRoute++;
                    $excludedByScope['rows']++;
                    $excludedByScope['expected_cash'] += (float) ($row['expected_cash'] ?? 0);
                    $excludedByScope['actual_cash'] += (float) ($row['actual_cash'] ?? 0);
                    continue;
                }
                $filtered[] = $row;
                if ($driver !== '') {
                    $keptDrivers[strtolower($driver)] = true;
                }
            }
            if ($droppedBus > 0) {
                $this->note($droppedBus . ' remittance record(s) could not be attributed to the selected bus and were excluded under the bus filter; their amounts are reported in the scope-excluded bucket.');
            }
            if ($droppedRoute > 0) {
                $this->note($droppedRoute . ' remittance record(s) could not be attributed to the selected route and were excluded under the route filter; attribution uses unique trip or driver/day matches and their amounts are reported in the scope-excluded bucket.');
            }
            $rows = $filtered;
            $driverFilter = array_keys($keptDrivers);
            if ($excludedByScope['rows'] > 0) {
                $reasons = [];
                if ($droppedBus > 0) {
                    $reasons[] = $droppedBus . ' without an attributable bus';
                }
                if ($droppedRoute > 0) {
                    $reasons[] = $droppedRoute . ' without an attributable route';
                }
                $excludedByScope['reason'] = implode('; ', $reasons);
            }
        }

        $expected = 0.0;
        $actual = 0.0;
        $totalCollection = 0.0;
        $shortage = 0.0;
        $overage = 0.0;
        $monthly = [];
        $statuses = [];
        foreach ($rows as $row) {
            $expectedCash = (float) ($row['expected_cash'] ?? 0);
            $actualCash = (float) ($row['actual_cash'] ?? 0);
            $expected += $expectedCash;
            $actual += $actualCash;
            $totalCollection += (float) ($row['total_collection'] ?? 0);
            if ($actualCash < $expectedCash) {
                $shortage += $expectedCash - $actualCash;
            } elseif ($actualCash > $expectedCash) {
                $overage += $actualCash - $expectedCash;
            }
            $month = substr((string) ($row['report_date'] ?? ''), 0, 7);
            if (!isset($monthly[$month])) {
                $monthly[$month] = ['month' => $month, 'expected' => 0.0, 'actual' => 0.0, 'shortage' => 0.0, 'overage' => 0.0, 'count' => 0];
            }
            $monthly[$month]['expected'] += $expectedCash;
            $monthly[$month]['actual'] += $actualCash;
            $monthly[$month]['count']++;
            if ($actualCash < $expectedCash) {
                $monthly[$month]['shortage'] += $expectedCash - $actualCash;
            } elseif ($actualCash > $expectedCash) {
                $monthly[$month]['overage'] += $actualCash - $expectedCash;
            }
            $statusRaw = trim((string) ($row['status'] ?? ''));
            $status = $statusRaw === '' ? 'Unknown' : ucfirst(strtolower($statusRaw));
            if (!isset($statuses[$status])) {
                $statuses[$status] = ['status' => $status, 'count' => 0, 'amount' => 0.0];
            }
            $statuses[$status]['count']++;
            $statuses[$status]['amount'] += $actualCash;
        }
        ksort($monthly);

        $discrepancies = $this->discrepancyData($filters, $driverFilter);

        return [
            'period' => $this->periodPayload($filters),
            'summary' => [
                'expected_cash' => round($expected, 2),
                'actual_cash' => round($actual, 2),
                'total_collection' => round($totalCollection, 2),
                'shortage' => round($shortage, 2),
                'overage' => round($overage, 2),
                'remittance_count' => count($rows),
                'remittance_rate' => $expected > 0 ? round($actual / $expected * 100, 2) : null,
                'undated_rows' => $undated['rows'],
                'undated_expected_cash' => $undated['expected_cash'],
                'excluded_by_scope' => [
                    'rows' => $excludedByScope['rows'],
                    'expected_cash' => round($excludedByScope['expected_cash'], 2),
                    'actual_cash' => round($excludedByScope['actual_cash'], 2),
                    'reason' => $excludedByScope['reason'],
                ],
                'unresolved_cases' => $discrepancies['unresolved_cases'],
                'unresolved_amount' => $discrepancies['unresolved_amount'],
                'recovered_amount' => $discrepancies['recovered_amount'],
                'outstanding_amount' => $discrepancies['outstanding_amount'],
            ],
            'monthly' => array_map(static fn(array $m): array => [
                'month' => $m['month'],
                'expected' => round($m['expected'], 2),
                'actual' => round($m['actual'], 2),
                'shortage' => round($m['shortage'], 2),
                'overage' => round($m['overage'], 2),
                'count' => $m['count'],
            ], array_values($monthly)),
            'statuses' => array_values(array_map(static fn(array $s): array => [
                'status' => $s['status'],
                'count' => $s['count'],
                'amount' => round($s['amount'], 2),
            ], $statuses)),
            'discrepancies' => $discrepancies,
            'scope' => $this->scopeMetadata($filters, ['remittance']),
            'notes' => array_merge(array_values($this->notes), [
                'Remittances are settlements of collected cash; they are never added to fare revenue.',
                'Unresolved discrepancy balances are open exposures, not confirmed losses, and are excluded from operating expenses until resolved and posted.',
            ]),
        ];
    }

    /**
     * @param list<string>|null $driverFilter Lowercased driver ids the bus/route
     *   filter kept, so discrepancy cases stay on the same scope.
     */
    private function discrepancyData(array $filters, ?array $driverFilter = null): array
    {
        $out = [
            'cases' => [],
            'unresolved_cases' => 0,
            'unresolved_amount' => 0.0,
            'recovered_amount' => 0.0,
            'outstanding_amount' => 0.0,
        ];
        if (!$this->tableExists('discrepancy_cases')) {
            $this->note('Discrepancy case records are unavailable; shortages are shown from remittance rows only.');
            return $out;
        }
        $date = $this->firstColumn('discrepancy_cases', ['business_date', 'opened_at', 'created_at']);
        if ($date === null) {
            return $out;
        }
        $until = (new DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
        $select = ['c.id', 'c.case_ref', 'c.driver_id', 'c.business_date', 'c.case_type', 'c.status', 'c.expected_amount', 'c.actual_amount', 'c.discrepancy_amount', 'c.excess_amount'];
        $rows = $this->fetch('SELECT ' . implode(', ', $select) . " FROM discrepancy_cases c WHERE c.`$date` >= :from AND c.`$date` < :until", [':from' => $filters['from'], ':until' => $until]);
        if ($driverFilter !== null) {
            $allowed = array_fill_keys(array_map('strtolower', $driverFilter), true);
            $rows = array_values(array_filter(
                $rows,
                static fn(array $row): bool => isset($allowed[strtolower(trim((string) ($row['driver_id'] ?? '')))])
            ));
        }

        $recoveryTotals = [];
        if ($this->tableExists('discrepancy_ledger_entries')) {
            foreach ($this->fetch('SELECT case_id, entry_type, recovery_class, amount, direction, state FROM discrepancy_ledger_entries WHERE state = :state', [':state' => 'posted']) as $entry) {
                $class = strtolower((string) ($entry['recovery_class'] ?? ''));
                if (!in_array($class, ['cash', 'payroll'], true)) {
                    continue;
                }
                $id = (int) ($entry['case_id'] ?? 0);
                $direction = strtolower((string) ($entry['direction'] ?? 'reduce'));
                $amount = (float) ($entry['amount'] ?? 0);
                if (!isset($recoveryTotals[$id])) {
                    $recoveryTotals[$id] = ['recovered' => 0.0, 'increase' => 0.0];
                }
                if ($direction === 'increase') {
                    $recoveryTotals[$id]['increase'] += $amount;
                } else {
                    $recoveryTotals[$id]['recovered'] += $amount;
                }
            }
        }

        foreach ($rows as $row) {
            $status = trim((string) ($row['status'] ?? ''));
            $caseType = trim((string) ($row['case_type'] ?? ''));
            $discrepancy = (float) ($row['discrepancy_amount'] ?? 0);
            $id = (int) ($row['id'] ?? 0);
            $totals = $recoveryTotals[$id] ?? ['recovered' => 0.0, 'increase' => 0.0];
            $outstanding = max(0.0, $discrepancy - $totals['recovered'] + $totals['increase']);
            $isOpen = !in_array($status, ['Resolved', 'Closed'], true);
            $isShortage = strcasecmp($caseType, 'Excess Remittance') !== 0;

            if ($isOpen && $isShortage) {
                $out['unresolved_cases']++;
                $out['unresolved_amount'] += $outstanding;
            }
            $out['recovered_amount'] += $totals['recovered'];
            $out['outstanding_amount'] += $outstanding;
            $out['cases'][] = [
                'case_ref' => (string) ($row['case_ref'] ?? ''),
                'driver_id' => (string) ($row['driver_id'] ?? ''),
                'business_date' => (string) ($row['business_date'] ?? ''),
                'case_type' => $caseType,
                'status' => $status,
                'expected' => round((float) ($row['expected_amount'] ?? 0), 2),
                'actual' => round((float) ($row['actual_amount'] ?? 0), 2),
                'discrepancy' => round($discrepancy, 2),
                'recovered' => round($totals['recovered'], 2),
                'outstanding' => round($outstanding, 2),
                'open' => $isOpen,
            ];
        }
        usort($out['cases'], static fn(array $a, array $b): int => $b['outstanding'] <=> $a['outstanding']);
        $out['unresolved_amount'] = round($out['unresolved_amount'], 2);
        $out['recovered_amount'] = round($out['recovered_amount'], 2);
        $out['outstanding_amount'] = round($out['outstanding_amount'], 2);
        return $out;
    }

    /* =========================================================
       SECTION: FORECAST
    ========================================================= */

    /**
     * @param array<string, mixed> $params horizon (7d|30d|90d)
     */
    public function forecast(array $filters, array $params = []): array
    {
        $horizon = strtolower(trim((string) ($params['horizon'] ?? '30d')));
        if (!in_array($horizon, ['7d', '30d', '90d'], true)) {
            $horizon = '30d';
        }
        if ((string) $filters['passenger_type'] !== '') {
            $this->note('The forecast history covers all passenger types; the passenger-type filter applies to recorded fare metrics but not to the predictive baseline.');
        }

        $predictive = null;
        $message = '';
        try {
            $serviceFile = __DIR__ . '/PredictiveAnalysisService.php';
            if (is_file($serviceFile) && !class_exists(PredictiveAnalysisService::class, false)) {
                require_once $serviceFile;
            }
            $service = new PredictiveAnalysisService();
            $predictive = $service->build([
                'horizon' => $horizon,
                'date_period' => 'custom',
                'from' => $filters['from'],
                'to' => $filters['to'],
                'route' => (string) $filters['route'],
                'bus' => (string) $filters['bus'],
                'time_period' => 'all',
            ]);
            if (($predictive['success'] ?? false) !== true) {
                $message = (string) ($predictive['message'] ?? 'Prediction unavailable.');
                $predictive = null;
            }
        } catch (Throwable $e) {
            $message = 'Prediction service unavailable.';
        }

        if ($predictive === null) {
            return [
                'period' => $this->periodPayload($filters),
                'horizon' => $horizon,
                'available' => false,
                'message' => $message !== '' ? $message : 'Prediction unavailable due to insufficient historical data.',
                'scope' => $this->scopeMetadata($filters, ['revenue', 'forecast']),
                'notes' => array_values($this->notes),
            ];
        }
        if (($predictive['data_quality']['unavailable'] ?? false) === true) {
            return [
                'period' => $this->periodPayload($filters),
                'horizon' => $horizon,
                'available' => false,
                'message' => (string) ($predictive['data_quality']['message'] ?? 'Insufficient historical data for a forecast.'),
                'data_quality' => $predictive['data_quality'],
                'scope' => $this->scopeMetadata($filters, ['revenue', 'forecast']),
                'notes' => array_values($this->notes),
            ];
        }

        return $this->composeForecast($filters, $horizon, $predictive);
    }

    /**
     * Derive collections, expense and profit forecasts from the predictive
     * payload plus the actual run-rate of the selected window.
     *
     * @param array<string, mixed> $predictive
     */
    public function composeForecast(array $filters, string $horizon, array $predictive): array
    {
        $current = $this->collect($filters, false);
        $totals = $current['totals'];
        $days = max(1, (int) $filters['days']);
        $horizonDays = ['7d' => 7, '30d' => 30, '90d' => 90][$horizon] ?? 30;

        $summary = $predictive['summary'] ?? [];
        $passengerSummary = $summary['passengers'] ?? [];
        $forecastPassengers = (float) ($passengerSummary['forecast_total'] ?? 0);
        $lowPassengers = (float) ($passengerSummary['range_low'] ?? $forecastPassengers);
        $highPassengers = (float) ($passengerSummary['range_high'] ?? $forecastPassengers);

        $avgFare = (float) ($totals['avg_fare'] ?? 0);
        $dailyFareRevenue = (float) $totals['collections'] / $days;
        $dailyOtherExpenses = (float) $totals['other_expenses'] / $days;
        $dailyFuel = (float) $totals['fuel'] / $days;

        $fuelPayload = $predictive['fuel'] ?? [];
        $estimatedLiters = (float) ($fuelPayload['estimated_liters'] ?? 0);
        $avgPrice = (float) ($fuelPayload['avg_price'] ?? 0);
        if ($avgPrice <= 0) {
            $prices = $this->fuelPriceRows();
            $avgPrice = $prices !== [] ? (float) end($prices)['price_per_liter'] : 0.0;
        }

        $collections = $forecastPassengers > 0 && $avgFare > 0
            ? round($forecastPassengers * $avgFare, 2)
            : round($dailyFareRevenue * $horizonDays, 2);
        $collectionsLow = $lowPassengers > 0 && $avgFare > 0 ? round($lowPassengers * $avgFare, 2) : $collections;
        $collectionsHigh = $highPassengers > 0 && $avgFare > 0 ? round($highPassengers * $avgFare, 2) : $collections;

        $fuelForecast = $estimatedLiters > 0
            ? round($estimatedLiters * max($avgPrice, 0.0), 2)
            : round($dailyFuel * $horizonDays, 2);
        $otherForecast = round($dailyOtherExpenses * $horizonDays, 2);
        $expensesForecast = round($fuelForecast + $otherForecast, 2);
        $profitForecast = round($collections - $expensesForecast, 2);
        $profitLow = round($collectionsLow - $expensesForecast, 2);
        $profitHigh = round($collectionsHigh - $expensesForecast, 2);

        $uncertaintyLevel = 'moderate';
        if ($collectionsHigh > 0 && $collections > 0) {
            $spread = ($collectionsHigh - $collectionsLow) / $collections * 100;
            if ($spread > 40) {
                $uncertaintyLevel = 'high';
            } elseif ($spread < 15) {
                $uncertaintyLevel = 'low';
            }
        }

        $model = $predictive['model'] ?? [];
        $reliability = $model['reliability'] ?? [];
        $assumptions = [
            'Collections forecast = predicted passenger volume × actual average realized fare of ' . number_format($avgFare, 2) . ' from the selected window.',
            'Fuel forecast = predicted fuel liters × latest recorded diesel price of ' . number_format($avgPrice, 2) . ' per liter.',
            'Other expenses forecast = the selected window\'s daily non-fuel expense run-rate × ' . $horizonDays . ' days.',
            'Discounts and passenger mix are assumed to follow the selected window averages.',
            'Configured monthly fees (Amortization, Insurance, GPS, OPTR) are not part of this run-rate projection; they are shown in the Overview expense breakdown.',
        ];

        return [
            'period' => $this->periodPayload($filters),
            'horizon' => $horizon,
            'horizon_days' => $horizonDays,
            'available' => true,
            'basis' => [
                'history_from' => (string) ($model['history_from'] ?? $filters['from']),
                'history_to' => (string) ($model['history_to'] ?? $filters['to']),
                'avg_realized_fare' => $avgFare,
                'daily_other_expenses' => round($dailyOtherExpenses, 2),
                'fuel_price_used' => round($avgPrice, 2),
            ],
            'passengers' => [
                'forecast_total' => round($forecastPassengers, 0),
                'range_low' => round($lowPassengers, 0),
                'range_high' => round($highPassengers, 0),
                'change_pct' => $passengerSummary['change_pct'] ?? null,
                'period_label' => (string) ($passengerSummary['period_label'] ?? ''),
            ],
            'collections' => [
                'forecast_total' => $collections,
                'range_low' => $collectionsLow,
                'range_high' => $collectionsHigh,
            ],
            'fuel' => [
                'liters' => round($estimatedLiters, 2),
                'cost_estimate' => $fuelForecast,
                'price_used' => round($avgPrice, 2),
                'available' => (bool) ($fuelPayload['available'] ?? false),
                'message' => (string) ($fuelPayload['message'] ?? ''),
            ],
            'other_expenses' => ['forecast_total' => $otherForecast],
            'expenses' => ['forecast_total' => $expensesForecast],
            'profit' => [
                'estimate' => $profitForecast,
                'range_low' => $profitLow,
                'range_high' => $profitHigh,
            ],
            'uncertainty' => [
                'level' => $uncertaintyLevel,
                'note' => 'Range reflects the passenger forecast interval' . ($reliability['label'] ?? '' !== '' ? ' (model reliability: ' . (string) ($reliability['label'] ?? '') . ')' : '') . '.',
            ],
            'assumptions' => $assumptions,
            'history' => $predictive['forecast']['history'] ?? [],
            'forecast_points' => $predictive['forecast']['points'] ?? [],
            'model' => [
                'name' => (string) ($model['name'] ?? ''),
                'computed_at' => (string) ($model['computed_at'] ?? ''),
                'reliability' => $reliability,
            ],
            'scope' => $this->scopeMetadata($filters, ['revenue', 'forecast']),
            'notes' => array_values($this->notes),
            'disclaimer' => 'Forecast values are projections from historical records, not actual results.',
        ];
    }

    /* =========================================================
       SECTION: WHAT-IF
    ========================================================= */

    /**
     * Safe projection: nothing is written to the database. Fares, volume,
     * fuel price and other expenses move from the actual baseline.
     *
     * @param array<string, mixed> $scenario
     */
    public function whatIf(array $filters, array $scenario): array
    {
        $current = $this->collect($filters, false);
        $totals = $current['totals'];

        $farePct = self::clampPct($scenario['fare_change_pct'] ?? 0);
        $volumePct = self::clampPct($scenario['volume_change_pct'] ?? 0);
        $fuelPct = self::clampPct($scenario['fuel_price_change_pct'] ?? 0);
        $expensePct = self::clampPct($scenario['other_expense_change_pct'] ?? 0);
        $routeFares = [];
        $routeFareLookup = [];
        foreach ((array) ($scenario['route_fare_changes'] ?? []) as $route => $pct) {
            if (is_string($route) && $route !== '') {
                $routeFares[$route] = self::clampPct($pct);
                $key = self::routeKey($route);
                if ($key !== '') {
                    $routeFareLookup[$key] = $routeFares[$route];
                }
            }
        }

        $projectedCollections = (float) $totals['collections'];
        $routeRows = [];
        if ($routeFares !== []) {
            $projectedCollections = 0.0;
            foreach ($current['by_route'] as $row) {
                $pct = $routeFareLookup[self::routeKey((string) $row['route'])] ?? $farePct;
                $projected = round((float) $row['collections'] * (1 + $pct / 100), 2);
                $projectedCollections += $projected;
                $routeRows[] = [
                    'route' => $row['route'],
                    'actual_collections' => $row['collections'],
                    'fare_change_pct' => $pct,
                    'projected_collections' => $projected,
                    'difference' => round($projected - (float) $row['collections'], 2),
                ];
            }
            $unattributed = round((float) $totals['collections'] - array_sum(array_map(static fn(array $r): float => (float) $r['actual_collections'], $routeRows)), 2);
            if ($unattributed > 0.005) {
                $projectedCollections += round($unattributed * (1 + $farePct / 100), 2);
            }
        } else {
            $projectedCollections = round($projectedCollections * (1 + $farePct / 100), 2);
        }
        $projectedCollections = round($projectedCollections * (1 + $volumePct / 100), 2);

        $projectedFuel = round((float) $totals['fuel'] * (1 + $fuelPct / 100), 2);
        $projectedOther = round((float) $totals['other_expenses'] * (1 + $expensePct / 100), 2);

        $configured = $current['configured'];
        $configuredTotal = (float) $configured['total'];

        $baselineExpenses = round((float) $totals['expenses'] + $configuredTotal, 2);
        $baselineProfit = round((float) $totals['collections'] - $baselineExpenses, 2);
        $projectedExpenses = round($projectedFuel + $projectedOther + $configuredTotal, 2);
        $projectedProfit = round($projectedCollections - $projectedExpenses, 2);

        return [
            'period' => $this->periodPayload($filters),
            'scenario' => [
                'fare_change_pct' => $farePct,
                'volume_change_pct' => $volumePct,
                'fuel_price_change_pct' => $fuelPct,
                'other_expense_change_pct' => $expensePct,
                'route_fare_changes' => $routeFares,
            ],
            'baseline' => [
                'collections' => $totals['collections'],
                'fuel' => $totals['fuel'],
                'other_expenses' => $totals['other_expenses'],
                'configured_costs' => $configuredTotal,
                'expenses' => $baselineExpenses,
                'profit' => $baselineProfit,
                'margin' => $totals['collections'] > 0 ? round($baselineProfit / (float) $totals['collections'] * 100, 2) : null,
            ],
            'projected' => [
                'collections' => $projectedCollections,
                'fuel' => $projectedFuel,
                'other_expenses' => $projectedOther,
                'configured_costs' => $configuredTotal,
                'expenses' => $projectedExpenses,
                'profit' => $projectedProfit,
                'margin' => $projectedCollections > 0 ? round($projectedProfit / $projectedCollections * 100, 2) : null,
            ],
            'difference' => [
                'collections' => round($projectedCollections - (float) $totals['collections'], 2),
                'expenses' => round($projectedExpenses - $baselineExpenses, 2),
                'profit' => round($projectedProfit - $baselineProfit, 2),
            ],
            'by_route' => $routeRows,
            'assumptions' => [
                'Projections apply the scenario percentages directly to the actual baseline recorded for the selected window.',
                'Fare changes scale collections after discounts; volume changes scale passenger-driven collections.',
                'Configured monthly costs from Financial Cost Settings are included in the baseline and held constant in the scenario.',
                'No database records are modified; proposed fares are not written to the live fare matrix.',
            ],
            'disclaimer' => 'What-if results are projections, not actual or forecast figures, and depend on the recorded baseline.',
            'scope' => $this->scopeMetadata($filters, ['revenue', 'expenses', 'fuel', 'configured']),
            'notes' => array_values($this->notes),
        ];
    }

    private static function clampPct($value): float
    {
        $pct = (float) $value;
        return max(-90.0, min(200.0, round($pct, 2)));
    }

    /* =========================================================
       OPTIONS + DISPATCH
    ========================================================= */

    public function options(): array
    {
        $years = [];
        foreach (['passenger_trip_history', 'passenger_trips', 'fuel_refuels'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $date = $this->firstColumn($table, ['trip_date', 'date_booked', 'boarding_time', 'created_at']);
            if ($date === null) {
                continue;
            }
            $row = $this->fetch("SELECT MIN(r.`$date`) AS min_date, MAX(r.`$date`) AS max_date FROM `$table` r");
            if ($row !== []) {
                $minYear = (int) substr((string) ($row[0]['min_date'] ?? ''), 0, 4);
                $maxYear = (int) substr((string) ($row[0]['max_date'] ?? ''), 0, 4);
                for ($year = max(2000, $minYear); $year <= max($maxYear, $minYear); $year++) {
                    $years[$year] = true;
                }
            }
        }
        $currentYear = (int) (new DateTimeImmutable('today'))->format('Y');
        $years[$currentYear] = true;
        krsort($years);

        $routes = [];
        if ($this->tableExists('bus_trips') && $this->has('bus_trips', 'route_name')) {
            foreach ($this->fetch('SELECT DISTINCT route_name FROM bus_trips ORDER BY route_name') as $row) {
                $name = self::canonicalRouteName((string) ($row['route_name'] ?? ''));
                if ($name !== '') {
                    $routes[$name] = true;
                }
            }
        }
        foreach ($this->fareSegments() as $segment) {
            if ($segment['route_name'] !== '') {
                $routes[$segment['route_name']] = true;
            }
        }
        $routeList = array_keys($routes);
        sort($routeList);

        $buses = [];
        if ($this->tableExists('buses')) {
            foreach ($this->busAliases() as $canonical) {
                $buses[$canonical] = true;
            }
        }
        $busList = array_keys($buses);
        sort($busList);

        $types = ['Regular', 'Student', 'Senior Citizen', 'PWD', 'Special', 'Cooperative', 'Beneficiary', 'Baggage'];
        if ($this->tableExists('passenger_trip_history') && $this->has('passenger_trip_history', 'passenger_type')) {
            foreach ($this->fetch('SELECT DISTINCT passenger_type FROM passenger_trip_history') as $row) {
                $type = trim((string) ($row['passenger_type'] ?? ''));
                if ($type !== '') {
                    $normalized = self::normalizePassengerType($type);
                    if (!in_array($normalized, $types, true)) {
                        $types[] = $normalized;
                    }
                }
            }
        }

        return [
            'periods' => [
                ['value' => 'today', 'label' => 'Today'],
                ['value' => 'yesterday', 'label' => 'Yesterday'],
                ['value' => 'weekly', 'label' => 'Weekly'],
                ['value' => 'monthly', 'label' => 'Monthly'],
                ['value' => 'quarter', 'label' => 'Quarterly'],
                ['value' => 'h1', 'label' => 'Semi-Annual - First Half (Jan-Jun)'],
                ['value' => 'h2', 'label' => 'Semi-Annual - Second Half (Jul-Dec)'],
                ['value' => 'yearly', 'label' => 'Annually (Full Year)'],
                ['value' => 'custom', 'label' => 'Custom Range'],
            ],
            'years' => array_keys($years),
            'routes' => $routeList,
            'buses' => $busList,
            'passenger_types' => $types,
            'fare_routes' => array_values(array_unique(array_map(static fn(array $s): string => $s['route_name'], $this->fareSegments()))),
        ];
    }

    /**
     * Authoritative data-source map (see the change design document for the
     * verified evidence). Each section lists the tables it reads, the columns
     * that carry the metric, and the date/status/role of each source. Tests
     * verify every mapped table and column against the fixture and the live
     * schema, and section payloads cite their entries.
     *
     * @return array<string, list<array{table: string, columns: list<string>, date: string, status: string, role: string}>>
     */
    public static function dataSourceMap(): array
    {
        return [
            'overview' => [
                ['table' => 'passenger_trips', 'columns' => ['id', 'boarding_time', 'fare', 'payment_status', 'trip_no', 'bus_id', 'driver_id', 'passenger_id', 'passenger_type', 'payment_method'], 'date' => 'boarding_time', 'status' => "payment_status = 'paid'", 'role' => 'current fare collections and journeys'],
                ['table' => 'passenger_trip_history', 'columns' => ['history_id', 'trip_date', 'date_booked', 'trip_id', 'bus_id', 'bus_number', 'driver_id', 'fare', 'payment_status'], 'date' => 'COALESCE(trip_date, DATE(date_booked))', 'status' => "payment_status = 'paid'", 'role' => 'archived fare collections and journeys'],
                ['table' => 'financial_expenses', 'columns' => ['expense_date', 'amount', 'bus_id', 'category'], 'date' => 'expense_date', 'status' => 'non-zero amounts', 'role' => 'recorded expenses'],
                ['table' => 'fuel_refuels', 'columns' => ['refuel_id', 'bus_id', 'trip_id', 'created_at', 'liters', 'total_cost'], 'date' => 'created_at', 'status' => 'liters and cost recorded', 'role' => 'fuel purchase cost fallback'],
                ['table' => 'bus_trips', 'columns' => ['id', 'trip_date', 'status', 'bus_id', 'route_name', 'driver_id'], 'date' => 'trip_date', 'status' => "status = 'Completed'", 'role' => 'route and bus attribution'],
                ['table' => 'financial_cost_settings', 'columns' => ['cost_key', 'label', 'amount', 'rate', 'calc_method', 'status'], 'date' => '', 'status' => "status = 'Active'", 'role' => 'configured cost estimates'],
                ['table' => 'fuel_prices', 'columns' => ['fuel_type', 'price_per_liter', 'updated_at'], 'date' => 'updated_at', 'status' => 'latest row', 'role' => 'reference fuel price'],
            ],
            'passengers' => [
                ['table' => 'passenger_trips', 'columns' => ['id', 'boarding_time', 'fare', 'payment_status', 'passenger_id', 'passenger_type', 'bus_id', 'trip_no'], 'date' => 'boarding_time', 'status' => "payment_status = 'paid'", 'role' => 'journeys and categories'],
                ['table' => 'passenger_trip_history', 'columns' => ['history_id', 'trip_date', 'fare', 'type', 'passenger_type', 'bus_id', 'bus_number', 'trip_id'], 'date' => 'trip_date', 'status' => 'non-baggage rows', 'role' => 'archived journeys'],
                ['table' => 'passengers', 'columns' => ['id'], 'date' => '', 'status' => 'registered accounts', 'role' => 'account identity count'],
            ],
            'fare' => [
                ['table' => 'fare_pricing', 'columns' => ['id', 'route_name', 'destination', 'regular_fare', 'student_fare', 'special_fare', 'display_order', 'updated_at'], 'date' => 'updated_at', 'status' => 'fare > 0', 'role' => 'configured matrix rows'],
                ['table' => 'passenger_trips', 'columns' => ['id', 'boarding_time', 'fare', 'payment_status', 'trip_no', 'bus_id', 'passenger_type'], 'date' => 'boarding_time', 'status' => "payment_status = 'paid'", 'role' => 'eligible recorded journeys'],
                ['table' => 'passenger_trip_history', 'columns' => ['history_id', 'trip_date', 'fare', 'trip_id', 'bus_id', 'bus_number'], 'date' => 'trip_date', 'status' => 'archived rows', 'role' => 'eligible archived journeys'],
                ['table' => 'bus_trips', 'columns' => ['id', 'trip_date', 'route_name', 'bus_id'], 'date' => 'trip_date', 'status' => 'recorded trips', 'role' => 'route identity for matching'],
            ],
            'fuel' => [
                ['table' => 'fuel_refuels', 'columns' => ['refuel_id', 'bus_id', 'trip_id', 'created_at', 'liters', 'total_cost', 'price_per_liter', 'refuel_odo'], 'date' => 'created_at', 'status' => 'liter and cost recorded', 'role' => 'purchases'],
                ['table' => 'fuel_logs', 'columns' => ['id', 'bus_id', 'trip_id', 'created_at', 'liters', 'cost', 'price_per_liter', 'kpl', 'distance_traveled', 'distance_km'], 'date' => 'created_at', 'status' => 'measurements recorded', 'role' => 'consumption and efficiency'],
                ['table' => 'fuel_prices', 'columns' => ['fuel_type', 'price_per_liter', 'updated_at'], 'date' => 'updated_at', 'status' => 'latest row', 'role' => 'reference price'],
                ['table' => 'bus_trips', 'columns' => ['id', 'trip_date', 'route_name', 'bus_id'], 'date' => 'trip_date', 'status' => 'recorded trips', 'role' => 'route attribution'],
            ],
            'fleet' => [
                ['table' => 'bus_trips', 'columns' => ['id', 'trip_date', 'status', 'bus_id', 'route_name', 'driver_id'], 'date' => 'trip_date', 'status' => "status = 'Completed'", 'role' => 'operating routes and trips'],
                ['table' => 'passenger_trips', 'columns' => ['id', 'boarding_time', 'fare', 'payment_status', 'trip_no', 'bus_id'], 'date' => 'boarding_time', 'status' => "payment_status = 'paid'", 'role' => 'route collections'],
                ['table' => 'passenger_trip_history', 'columns' => ['history_id', 'trip_date', 'fare', 'trip_id', 'bus_id', 'bus_number'], 'date' => 'trip_date', 'status' => 'archived rows', 'role' => 'archived route collections'],
                ['table' => 'financial_expenses', 'columns' => ['expense_date', 'amount', 'bus_id', 'category'], 'date' => 'expense_date', 'status' => 'non-zero amounts', 'role' => 'attributable costs'],
                ['table' => 'fuel_refuels', 'columns' => ['refuel_id', 'bus_id', 'trip_id', 'created_at', 'total_cost'], 'date' => 'created_at', 'status' => 'recorded purchases', 'role' => 'attributable fuel cost'],
                ['table' => 'buses', 'columns' => ['id', 'bus_id'], 'date' => '', 'status' => 'fleet registry', 'role' => 'bus identifier normalization'],
            ],
            'remittance' => [
                ['table' => 'remittance_logs', 'columns' => ['id', 'driver_id', 'total_collection', 'expected_cash', 'actual_cash', 'status', 'created_at', 'discrepancy_amount'], 'date' => 'business_date else created_at else verified_at (feature-detected)', 'status' => "status = 'Completed' etc.", 'role' => 'settlements'],
                ['table' => 'discrepancy_cases', 'columns' => ['id', 'remittance_log_id', 'driver_id', 'business_date', 'status', 'expected_amount', 'actual_amount', 'discrepancy_amount'], 'date' => 'business_date', 'status' => 'open/resolved states', 'role' => 'shortage and excess cases'],
                ['table' => 'discrepancy_ledger_entries', 'columns' => ['case_id', 'amount', 'state'], 'date' => '', 'status' => "state = 'posted'", 'role' => 'recoveries'],
                ['table' => 'bus_trips', 'columns' => ['id', 'trip_date', 'route_name', 'bus_id', 'driver_id'], 'date' => 'trip_date', 'status' => 'recorded trips', 'role' => 'driver/bus attribution'],
            ],
            'forecast' => [
                ['table' => 'passenger_trips', 'columns' => ['id', 'boarding_time', 'fare', 'payment_status', 'passenger_id'], 'date' => 'boarding_time', 'status' => "payment_status = 'paid'", 'role' => 'history basis'],
                ['table' => 'passenger_trip_history', 'columns' => ['history_id', 'trip_date', 'fare'], 'date' => 'trip_date', 'status' => 'archived rows', 'role' => 'history basis'],
                ['table' => 'fuel_refuels', 'columns' => ['refuel_id', 'created_at', 'liters', 'total_cost'], 'date' => 'created_at', 'status' => 'recorded purchases', 'role' => 'fuel run-rate'],
                ['table' => 'financial_expenses', 'columns' => ['expense_date', 'amount', 'category'], 'date' => 'expense_date', 'status' => 'non-zero amounts', 'role' => 'other expense run-rate'],
            ],
            'whatif' => [
                ['table' => 'passenger_trips', 'columns' => ['id', 'boarding_time', 'fare', 'payment_status', 'bus_id', 'trip_no'], 'date' => 'boarding_time', 'status' => "payment_status = 'paid'", 'role' => 'baseline collections'],
                ['table' => 'passenger_trip_history', 'columns' => ['history_id', 'trip_date', 'fare', 'bus_id', 'bus_number', 'trip_id'], 'date' => 'trip_date', 'status' => 'archived rows', 'role' => 'baseline collections'],
                ['table' => 'fuel_refuels', 'columns' => ['refuel_id', 'created_at', 'liters', 'total_cost'], 'date' => 'created_at', 'status' => 'recorded purchases', 'role' => 'baseline fuel'],
                ['table' => 'financial_expenses', 'columns' => ['expense_date', 'amount'], 'date' => 'expense_date', 'status' => 'non-zero amounts', 'role' => 'baseline other expenses'],
            ],
            'options' => [
                ['table' => 'bus_trips', 'columns' => ['id', 'trip_date', 'route_name'], 'date' => 'trip_date', 'status' => 'recorded trips', 'role' => 'route and year options'],
                ['table' => 'fare_pricing', 'columns' => ['route_name'], 'date' => 'updated_at', 'status' => 'matrix rows', 'role' => 'fare route options'],
                ['table' => 'buses', 'columns' => ['id', 'bus_id'], 'date' => '', 'status' => 'fleet registry', 'role' => 'bus options'],
            ],
        ];
    }

    /** @return list<string> */
    private static function sectionDataSources(string $section): array
    {
        $map = self::dataSourceMap();
        $tables = [];
        if ($section === 'all') {
            foreach ($map as $entries) {
                foreach ($entries as $entry) {
                    $tables[(string) $entry['table']] = true;
                }
            }
        } else {
            foreach ($map[$section] ?? [] as $entry) {
                $tables[(string) $entry['table']] = true;
            }
        }
        return array_keys($tables);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function section(string $section, array $filters, array $params = []): array
    {
        $payload = match ($section) {
            'overview' => $this->overview($filters),
            'passengers' => $this->passengers($filters),
            'fare' => $this->fare($filters),
            'fuel' => $this->fuel($filters),
            'fleet' => $this->fleet($filters),
            'remittance' => $this->remittance($filters),
            'forecast' => $this->forecast($filters, $params),
            'whatif' => $this->whatIf($filters, $params),
            'options' => $this->options(),
            'all' => [
                'overview' => $this->overview($filters),
                'passengers' => $this->passengers($filters),
                'fare' => $this->fare($filters),
                'fuel' => $this->fuel($filters),
                'fleet' => $this->fleet($filters),
                'remittance' => $this->remittance($filters),
                'forecast' => $this->forecast($filters, $params),
            ],
            default => throw new \InvalidArgumentException('Unknown financial intelligence section.'),
        };
        /* Additive, request-level honesty flags: a failed secondary source
           yields partial data with its sources named, never an empty dataset
           that looks like a genuine zero. Each payload also cites its
           authoritative sources from the maintained map. */
        $payload['data_sources'] = self::sectionDataSources($section);
        return array_merge($payload, $this->errorSummary());
    }

    /** @return list<string> */
    public static function sections(): array
    {
        return ['overview', 'passengers', 'fare', 'fuel', 'fleet', 'remittance', 'forecast', 'whatif', 'options', 'all'];
    }
}

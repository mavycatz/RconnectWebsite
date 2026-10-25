<?php declare(strict_types=1);

namespace Admin\Models\Bus;

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;
use DateTime;
use PDO;

/**
 * Schema-tolerant read model for the Bus Management directory.
 *
 * Mirrors the Role Management directory model: columns and tables that this
 * installation does not have are skipped rather than failing, and every
 * string comparison carries an explicit collation so the legacy `buses`
 * table can never raise MySQL error 1267.
 *
 * One shared status classification feeds the summary cards, the status
 * filter, and the per-class counts, so their numbers can never disagree.
 */
class BusDirectoryModel
{
    /**
     * Lifecycle status classes for the status filter and summary cards.
     *
     * These come only from the stored `buses.status` value; operational
     * status is a separate value and can never override or replace these.
     */
    private const CLASSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'maintenance' => 'Maintenance',
        'archived' => 'Archived',
    ];

    /** Normalized operational status values used for display styling only. */
    private const OPERATIONAL = [
        'available' => 'Available',
        'on trip' => 'On Trip',
        'under maintenance' => 'Under Maintenance',
        'out of service' => 'Out of Service',
    ];

    private PDO $db;
    private array $tableCache = [];
    private array $columnCache = [];
    private array $sourceErrors = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::get();
    }

    /**
     * @return array<string,string> class => label (filter options)
     */
    public static function statusOptions(): array
    {
        return self::CLASSES;
    }

    /**
     * The single lifecycle rule used by rows, filters, and counts.
     *
     * The displayed status is derived only from the stored `status` value.
     * `operational_status` is never consulted here, so an operational value
     * can never turn an Active bus into Inactive.
     *
     * @param array<string,mixed> $row raw status (+ optional operational_status)
     */
    public static function classify(array $row): string
    {
        $status = strtolower(trim((string)($row['raw_status'] ?? $row['status'] ?? '')));

        if ($status === '') {
            return 'inactive';
        }

        foreach (self::CLASSES as $class => $label) {
            if ($status === strtolower($label)) {
                return $class;
            }
        }

        return self::slug($status);
    }

    /**
     * Display label for a lifecycle class: the configured label, or the
     * legacy stored value shown as stored.
     */
    public static function labelFor(string $class, string $rawStatus = ''): string
    {
        if (isset(self::CLASSES[$class])) {
            return self::CLASSES[$class];
        }

        $raw = trim($rawStatus);

        return $raw !== '' ? $raw : 'Inactive';
    }

    /**
     * Normalized operational status value for display styling; 'other' for
     * legacy values, '' when unset. The operational value itself is always
     * shown as stored and never changes the lifecycle status.
     *
     * @param array<string,mixed> $row
     */
    public static function operationalClass(array $row): string
    {
        $op = strtolower(trim((string)($row['operational_status'] ?? '')));

        if ($op === '') {
            return '';
        }

        return isset(self::OPERATIONAL[$op]) ? str_replace(' ', '_', $op) : 'other';
    }

    /**
     * Stable key for a stored value outside the configured lifecycle set.
     */
    private static function slug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($value)));

        return trim((string)$slug, '_') ?: 'inactive';
    }

    /**
     * Expiry state for a stored date: '' when unset, otherwise ok/expiring/expired.
     */
    public static function expiryState(?string $date, int $warnDays = 30): string
    {
        $date = trim((string)$date);
        if ($date === '') {
            return '';
        }
        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            return '';
        }

        $today = new DateTime('today');
        if ($parsed < $today) {
            return 'expired';
        }
        $diff = (int)$today->diff($parsed)->days;

        return $diff <= max(0, $warnDays) ? 'expiring' : 'ok';
    }

    /**
     * @return array{rows:array,counts:array,summary:array,total:int,errors:array}
     */
    public function query(array $filters): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $status = strtolower(trim((string)($filters['status'] ?? '')));
        $busType = trim((string)($filters['bus_type'] ?? ''));
        $route = trim((string)($filters['route'] ?? ''));
        $driver = trim((string)($filters['driver'] ?? ''));
        $registeredFrom = trim((string)($filters['registered_from'] ?? ''));
        $registeredTo = trim((string)($filters['registered_to'] ?? ''));
        $sort = strtolower(trim((string)($filters['sort'] ?? 'bus_asc')));

        if ($status !== '' && !isset(self::CLASSES[$status])) {
            $status = '';
        }

        [$baseWhere, $baseParams] = $this->baseWhere($search, $busType, $route, $driver, $registeredFrom, $registeredTo);

        $conditions = $this->classificationConditions();

        $rows = [];
        try {
            $where = $baseWhere;
            $params = $baseParams;
            if ($status !== '') {
                $where[] = $conditions[$status];
            } else {
                $where[] = 'NOT (' . $conditions['archived'] . ')';
            }
            $rows = $this->fetchRows($where, $params, $sort);
        } catch (\Throwable $e) {
            $this->sourceErrors['buses'] = 'Bus records could not be loaded from this database (its tables or columns are missing). Details: ' . $e->getMessage();
        }

        $counts = [];
        $summary = [];
        try {
            $counts = $this->countClasses($baseWhere, $baseParams);
        } catch (\Throwable $e) {
            $counts = array_fill_keys(array_keys(self::CLASSES), 0);
        }
        try {
            $summary = $this->countClasses([], []);
        } catch (\Throwable $e) {
            $summary = array_fill_keys(array_keys(self::CLASSES), 0);
        }

        foreach ($rows as $index => $row) {
            $rows[$index] = $this->normalizeRow($row);
            if ($rows[$index]['classification'] === 'archived' && $this->tableExists('audit_trail')) {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/ArchiveMetadata.php';
                $details = \Admin\Services\RoleManagement\ArchiveMetadata::details($this->db, 'bus', (string) $rows[$index]['ref'], (string) $rows[$index]['bus_id']);
                $rows[$index]['archive_reason'] = $details['reason'] ?? null;
                $rows[$index]['archived_at'] = $details['archived_at'] ?? null;
                $rows[$index]['archived_by'] = $details['archived_by'] ?? null;
            }
        }

        return [
            'rows' => $rows,
            'counts' => $counts,
            'summary' => $summary + ['total' => array_sum($summary)],
            'total' => count($rows),
            'errors' => $this->sourceErrors,
        ];
    }

    /**
     * Build the SELECT statement for a filter set. Public so the SQL can be
     * probed without a database connection.
     *
     * @return array{0:string,1:array}
     */
    public function buildQuery(array $filters): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $status = strtolower(trim((string)($filters['status'] ?? '')));
        $busType = trim((string)($filters['bus_type'] ?? ''));
        $route = trim((string)($filters['route'] ?? ''));
        $driver = trim((string)($filters['driver'] ?? ''));
        $registeredFrom = trim((string)($filters['registered_from'] ?? ''));
        $registeredTo = trim((string)($filters['registered_to'] ?? ''));
        $sort = strtolower(trim((string)($filters['sort'] ?? 'bus_asc')));

        [$where, $params] = $this->baseWhere($search, $busType, $route, $driver, $registeredFrom, $registeredTo);

        if ($status !== '' && isset(self::CLASSES[$status])) {
            $where[] = $this->classificationConditions()[$status];
        } else {
            $where[] = 'NOT (' . $this->classificationConditions()['archived'] . ')';
        }

        return $this->rowsQuery($where, $params, $sort);
    }

    /**
     * @return array{0:array,1:array} [where fragments, params]
     */
    private function baseWhere(
        string $search,
        string $busType,
        string $route,
        string $driver,
        string $registeredFrom,
        string $registeredTo
    ): array {
        $where = [];
        $params = [];

        if ($search !== '') {
            $expressions = [];
            foreach ([
                ['buses', 'bus_id', 'b.bus_id'],
                ['buses', 'plate_number', 'b.plate_number'],
                ['buses', 'unit_number', 'b.unit_number'],
                ['buses', 'manufacturer', 'b.manufacturer'],
                ['buses', 'model', 'b.model'],
                ['buses', 'route_name', 'b.route_name'],
                ['buses', 'terminal', 'b.terminal'],
                ['buses', 'schedule', 'b.schedule'],
            ] as $target) {
                if ($this->has($target[0], $target[1])) {
                    $expressions[] = self::safeCol($target[2]);
                }
            }
            if ($this->has('buses', 'driver')) {
                $expressions[] = self::safeCol('b.driver');
            }
            if ($this->driverJoin()) {
                $expressions[] = self::safeCol('drv.full_name');
            }

            if ($expressions) {
                $where[] = '(' . implode(' OR ', array_map(static fn ($e) => $e . ' LIKE ?', $expressions)) . ')';
                foreach ($expressions as $ignored) {
                    $params[] = '%' . $search . '%';
                }
            }
        }

        if ($busType !== '' && $this->has('buses', 'bus_type')) {
            $where[] = self::safeCol('b.bus_type') . ' = ?';
            $params[] = $busType;
        }

        if ($route !== '' && $this->has('buses', 'route_name')) {
            $where[] = self::safeCol('b.route_name') . ' = ?';
            $params[] = $route;
        }

        if ($driver !== '') {
            $driverMatches = [];
            if ($this->has('buses', 'driver')) {
                $driverMatches[] = self::safeCol('b.driver') . ' = ?';
                $params[] = $driver;
            }
            if ($this->driverJoin()) {
                $driverMatches[] = self::safeCol('drv.full_name') . ' = ?';
                $params[] = $driver;
            }
            if ($driverMatches) {
                $where[] = '(' . implode(' OR ', $driverMatches) . ')';
            } else {
                $where[] = '1 = 0';
            }
        }

        if ($this->has('buses', 'created_at')) {
            if ($registeredFrom !== '') {
                $where[] = 'b.created_at >= ?';
                $params[] = $registeredFrom . ' 00:00:00';
            }
            if ($registeredTo !== '') {
                $where[] = 'b.created_at <= ?';
                $params[] = $registeredTo . ' 23:59:59';
            }
        }

        return [$where, $params];
    }

    /**
     * SQL conditions per lifecycle class, derived from the same CLASSES map
     * that `classify()` uses, so a row's displayed status, its filter
     * membership, and its bucket count can never disagree.
     *
     * @return array<string,string>
     */
    private function classificationConditions(): array
    {
        $hasStatus = $this->has('buses', 'status');
        $statusCol = static fn (string $value): string => self::safeCol('b.status') . " = '" . $value . "'";

        $conditions = [];
        foreach (self::CLASSES as $class => $label) {
            if (!$hasStatus) {
                // Without a status column every row falls back to Active.
                $conditions[$class] = $class === 'active' ? '1 = 1' : '1 = 0';
                continue;
            }
            if ($class === 'inactive') {
                // Unset statuses display as Inactive too, so they filter and
                // count with the Inactive class.
                $conditions[$class] = '(' . $statusCol($label) . ' OR b.status IS NULL OR b.status = \'\')';
                continue;
            }
            $conditions[$class] = $statusCol($label);
        }

        $conditions['other'] = $hasStatus
            ? 'NOT (' . implode(' OR ', [
                $statusCol('Active'),
                $statusCol('Inactive'),
                $statusCol('Maintenance'),
                $statusCol('Archived'),
                'b.status IS NULL',
                "b.status = ''",
            ]) . ')'
            : '1 = 0';

        return $conditions;
    }

    /**
     * One query returns every classification count (summary cards and
     * per-filter counts share this exact expression set).
     *
     * @param string[] $where
     * @param array $params
     * @return array<string,int>
     */
    private function countClasses(array $where, array $params): array
    {
        $conditions = $this->classificationConditions();

        $sums = [];
        foreach ($conditions as $class => $condition) {
            $sums[] = 'SUM(CASE WHEN ' . $condition . ' THEN 1 ELSE 0 END) AS `' . $class . '`';
        }

        $sql = 'SELECT ' . implode(', ', $sums) . ' FROM buses b' . $this->joins();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $counts = [];
        foreach (array_keys($conditions) as $class) {
            $counts[$class] = (int)($row[$class] ?? 0);
        }

        return $counts;
    }

    /**
     * @param string[] $where
     * @param array $params
     * @return array<int,array<string,mixed>>
     */
    private function fetchRows(array $where, array $params, string $sort): array
    {
        [$sql, $params] = $this->rowsQuery($where, $params, $sort);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param string[] $where
     * @param array $params
     * @return array{0:string,1:array}
     */
    private function rowsQuery(array $where, array $params, string $sort): array
    {
        $orNull = static fn (string $expr): string => 'CASE WHEN ' . $expr . ' IS NULL THEN 1 ELSE 0 END';

        $select = [
            'b.id AS ref',
            $this->textExpr('buses', 'bus_id', 'b.bus_id') . ' AS bus_id',
            $this->textExpr('buses', 'plate_number', 'b.plate_number') . ' AS plate',
            $this->textExpr('buses', 'unit_number', 'b.unit_number') . ' AS unit_number',
            $this->textExpr('buses', 'bus_type', 'b.bus_type') . ' AS bus_type',
            $this->has('buses', 'status') ? 'b.status AS raw_status' : "'active' AS raw_status",
            $this->textExpr('buses', 'operational_status', 'b.operational_status') . ' AS operational_status',
            $this->expr('buses', 'seats', 'b.seats') . ' AS seats',
            $this->expr('buses', 'standing_capacity', 'b.standing_capacity') . ' AS standing_capacity',
            $this->textExpr('buses', 'manufacturer', 'b.manufacturer') . ' AS manufacturer',
            $this->textExpr('buses', 'model', 'b.model') . ' AS model',
            $this->textExpr('buses', 'year_model', 'b.year_model') . ' AS year_model',
            $this->textExpr('buses', 'engine_number', 'b.engine_number') . ' AS engine_number',
            $this->textExpr('buses', 'chassis_number', 'b.chassis_number') . ' AS chassis_number',
            $this->textExpr('buses', 'fuel_type', 'b.fuel_type') . ' AS fuel_type',
            $this->textExpr('buses', 'transmission_type', 'b.transmission_type') . ' AS transmission_type',
            $this->textExpr('buses', 'air_conditioning', 'b.air_conditioning') . ' AS air_conditioning',
            $this->textExpr('buses', 'vehicle_ownership', 'b.vehicle_ownership') . ' AS vehicle_ownership',
            $this->textExpr('buses', 'route_name', 'b.route_name') . ' AS route_name',
            $this->textExpr('buses', 'terminal', 'b.terminal') . ' AS terminal',
            $this->expr('buses', 'from_terminal_id', 'b.from_terminal_id') . ' AS from_terminal_id',
            $this->expr('buses', 'to_terminal_id', 'b.to_terminal_id') . ' AS to_terminal_id',
            $this->textExpr('buses', 'accessible_features', 'b.accessible_features') . ' AS accessible_features',
            $this->textExpr('buses', 'schedule', 'b.schedule') . ' AS schedule',
            $this->textExpr('buses', 'driver', 'b.driver') . ' AS driver_ref',
            $this->expr('buses', 'assigned_inspector_id', 'b.assigned_inspector_id') . ' AS assigned_inspector_id',
            $this->textExpr('buses', 'registration_month', 'b.registration_month') . ' AS registration_month',
            $this->expr('buses', 'registration_expiry', 'b.registration_expiry') . ' AS registration_expiry',
            $this->expr('buses', 'insurance_expiry', 'b.insurance_expiry') . ' AS insurance_expiry',
            $this->expr('buses', 'inspection_date', 'b.inspection_date') . ' AS inspection_date',
            $this->expr('buses', 'next_inspection_date', 'b.next_inspection_date') . ' AS next_inspection_date',
            $this->textExpr('buses', 'qr_status', 'b.qr_status') . ' AS qr_status',
            $this->textExpr('buses', 'qr_code', 'b.qr_code') . ' AS qr_code',
            $this->textExpr('buses', 'uploaded_image', 'b.uploaded_image') . ' AS uploaded_image',
            $this->expr('buses', 'created_at', 'b.created_at') . ' AS created_at',
            $this->expr('buses', 'updated_at', 'b.updated_at') . ' AS updated_at',
            $this->textExpr('buses', 'created_by', 'b.created_by') . ' AS created_by',
        ];

        if ($this->driverJoin()) {
            $select[] = self::safeCol('drv.full_name') . ' AS driver_name';
        } else {
            $select[] = 'NULL AS driver_name';
        }
        if ($this->inspectorJoin()) {
            $select[] = $this->inspectorNameExpr() . ' AS inspector_name';
        } else {
            $select[] = 'NULL AS inspector_name';
        }

        $orderBy = match ($sort) {
            'newest' => $this->has('buses', 'created_at')
                ? 'ORDER BY ' . $orNull('b.created_at') . ' ASC, b.created_at DESC, b.id DESC'
                : 'ORDER BY b.id DESC',
            'oldest' => $this->has('buses', 'created_at')
                ? 'ORDER BY ' . $orNull('b.created_at') . ' ASC, b.created_at ASC, b.id ASC'
                : 'ORDER BY b.id ASC',
            'plate_asc' => $this->has('buses', 'plate_number')
                ? 'ORDER BY ' . self::safeCol('b.plate_number') . ' ASC, b.id ASC'
                : 'ORDER BY b.id ASC',
            'plate_desc' => $this->has('buses', 'plate_number')
                ? 'ORDER BY ' . self::safeCol('b.plate_number') . ' DESC, b.id DESC'
                : 'ORDER BY b.id DESC',
            'bus_desc' => $this->has('buses', 'bus_id')
                ? 'ORDER BY ' . self::safeCol('b.bus_id') . ' DESC'
                : 'ORDER BY b.id DESC',
            default => $this->has('buses', 'bus_id')
                ? 'ORDER BY ' . self::safeCol('b.bus_id') . ' ASC, b.id ASC'
                : 'ORDER BY b.id ASC',
        };

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM buses b' . $this->joins();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ' . $orderBy . ' LIMIT 5000';

        return [$sql, $params];
    }

    private function joins(): string
    {
        $joins = '';
        if ($this->driverJoin()) {
            $joins .= ' LEFT JOIN drivers drv ON ' . self::safeCol('drv.driver_id') . ' = ' . self::safeCol('b.driver');
        }
        if ($this->inspectorJoin()) {
            $joins .= ' LEFT JOIN inspectors insp ON insp.inspector_pk = b.assigned_inspector_id';
        }

        return $joins;
    }

    private function driverJoin(): bool
    {
        return $this->has('buses', 'driver')
            && $this->tableExists('drivers')
            && $this->has('drivers', 'driver_id')
            && $this->has('drivers', 'full_name');
    }

    private function inspectorJoin(): bool
    {
        return $this->has('buses', 'assigned_inspector_id')
            && $this->tableExists('inspectors')
            && $this->has('inspectors', 'inspector_pk')
            && $this->inspectorNameExpr() !== null;
    }

    /**
     * The inspectors table name column varies by installation: the canonical
     * schema stores `inspector_name`, older copies may use `full_name`, and a
     * split name is tolerated too. Returns null when no name column exists.
     */
    private function inspectorNameExpr(): ?string
    {
        if ($this->has('inspectors', 'inspector_name')) {
            return self::safeCol('insp.inspector_name');
        }
        if ($this->has('inspectors', 'full_name')) {
            return self::safeCol('insp.full_name');
        }
        if ($this->has('inspectors', 'first_name') && $this->has('inspectors', 'last_name')) {
            return 'CONCAT_WS(" ", insp.first_name, insp.last_name)';
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['classification'] = self::classify($row);
        $row['classification_label'] = self::labelFor($row['classification'], (string)($row['raw_status'] ?? ''));
        $row['operational_class'] = self::operationalClass($row);
        $row['registration_state'] = self::expiryState((string)($row['registration_expiry'] ?? ''));
        $row['insurance_state'] = self::expiryState((string)($row['insurance_expiry'] ?? ''));
        $row['inspection_state'] = self::expiryState((string)($row['next_inspection_date'] ?? ''));

        return $row;
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableCache)) {
            try {
                $stmt = $this->db->query('SHOW TABLES LIKE ' . $this->db->quote($table));
                $this->tableCache[$table] = (bool)$stmt->fetchColumn();
            } catch (\Throwable $e) {
                $this->tableCache[$table] = false;
            }
        }

        return $this->tableCache[$table];
    }

    private function columns(string $table): array
    {
        if (!array_key_exists($table, $this->columnCache)) {
            $list = [];
            try {
                foreach ($this->db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->fetchAll(PDO::FETCH_ASSOC) as $col) {
                    $list[strtolower((string)$col['Field'])] = true;
                }
            } catch (\Throwable $e) {
                $list = [];
            }
            $this->columnCache[$table] = $list;
        }

        return $this->columnCache[$table];
    }

    private function has(string $table, string $column): bool
    {
        $columns = $this->columns($table);

        return isset($columns[strtolower($column)]);
    }

    private function expr(string $table, string $column, string $expression): string
    {
        return $this->has($table, $column) ? $expression : 'NULL';
    }

    private function textExpr(string $table, string $column, string $expression): string
    {
        return $this->has($table, $column) ? $expression : "''";
    }

    /**
     * Explicit collation so comparisons can never raise MySQL error 1267 on
     * the legacy tables.
     */
    private static function safeCol(string $expr): string
    {
        return 'CONVERT(' . $expr . ' USING utf8mb4) COLLATE utf8mb4_general_ci';
    }
}

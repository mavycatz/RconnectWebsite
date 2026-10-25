<?php declare(strict_types=1);

namespace Admin\Models\RoleManagement;

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/PersonnelFields.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/ArchiveMetadata.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/SecurityCodeService.php';

use App\Core\Database;
use Admin\Services\RoleManagement\PersonnelFields;
use PDO;

/**
 * Normalized read model for the unified Role Management directory.
 *
 * Seven roles come from four existing sources:
 *   driver                        -> drivers + driver_accounts
 *   inspector / dispatcher        -> inspectors + inspector_accounts
 *   finance_officer / manager     -> users
 *   admin / super_admin           -> users
 *
 * The queries are schema-tolerant: columns and joins that this installation's
 * database does not have are skipped, and a source whose core tables are
 * missing is reported instead of silently looking like "no accounts".
 */
class RoleDirectoryModel
{
    private PDO $db;
    private array $tableCache = [];
    private array $columnCache = [];
    private array $sourceErrors = [];

    public function __construct()
    {
        $this->db = Database::get();
    }

    public static function roles(): array
    {
        return [
            'driver' => 'Driver',
            'inspector' => 'Inspector',
            'dispatcher' => 'Dispatcher',
            'finance_officer' => 'Finance Officer',
            'finance_manager' => 'Finance Manager',
            'admin' => 'Admin',
            'super_admin' => 'Super Admin',
        ];
    }

    public static function cvSubjectType(string $role): string
    {
        return match ($role) {
            'driver' => 'driver',
            'inspector' => 'inspector',
            'dispatcher' => 'dispatcher',
            'finance_officer', 'finance_manager' => 'finance',
            'admin', 'super_admin' => 'admin',
            default => '',
        };
    }

    public static function statusOptions(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'pending' => 'Pending',
            'rejected' => 'Rejected',
            'archived' => 'Archived',
        ];
    }

    public static function normalizeStatus(string $raw): string
    {
        $status = strtolower(trim($raw));
        return match ($status) {
            'active', 'approved' => 'active',
            'archived' => 'archived',
            'inactive', 'disabled', 'deactivated' => 'inactive',
            'pending' => 'pending',
            'rejected', 'denied' => 'rejected',
            default => $status === '' ? 'pending' : $status,
        };
    }

    /**
     * @return array{rows:array,counts:array,total:int,errors:array,summary:array}
     */
    public function query(array $filters): array
    {
        $role = strtolower(trim((string)($filters['role'] ?? 'all')));
        $search = trim((string)($filters['search'] ?? ''));
        $status = strtolower(trim((string)($filters['status'] ?? '')));
        $sort = strtolower(trim((string)($filters['sort'] ?? 'newest')));

        $extra = [
            'department' => trim((string)($filters['department'] ?? '')),
            'employment' => trim((string)($filters['employment'] ?? '')),
            'created_from' => trim((string)($filters['created_from'] ?? '')),
            'created_to' => trim((string)($filters['created_to'] ?? '')),
        ];

        $allRoles = array_keys(self::roles());
        $activeRoles = ($role !== '' && $role !== 'all' && isset(self::roles()[$role])) ? [$role] : $allRoles;

        $counts = [];
        foreach ($allRoles as $roleKey) {
            $counts[$roleKey] = $this->countForRole($roleKey, $search, $status, $extra);
        }

        $rows = [];
        foreach ($activeRoles as $roleKey) {
            foreach ($this->rowsForRole($roleKey, $search, $status, '', $extra) as $row) {
                $rows[] = $this->normalizeRow($roleKey, $row);
            }
        }

        usort($rows, static function (array $a, array $b) use ($sort): int {
            if ($sort === 'name_asc') {
                return strcasecmp((string)$a['name'], (string)$b['name']);
            }
            if ($sort === 'name_desc') {
                return strcasecmp((string)$b['name'], (string)$a['name']);
            }

            $cmp = strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? ''));
            if ($sort === 'oldest') {
                $cmp = -$cmp;
            }
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcasecmp((string)$a['name'], (string)$b['name']);
        });

        return [
            'rows' => $rows,
            'counts' => $counts,
            'total' => count($rows),
            'errors' => $this->sourceErrors,
            'summary' => $this->summary(),
        ];
    }

    /**
     * Account statistics from the real records.
     *
     * Rule (applied consistently everywhere): every account counts once.
     *   - archived: raw status "archived" (drivers keep "Archived" while archived)
     *   - active:   raw status active/approved
     *   - pending:  raw status pending (or empty, matching the directory default)
     *   - inactive: inactive, deactivated, disabled, rejected, and anything else
     * Total always includes archived accounts, the same rule the role filter
     * counts use, so the header total and the tab counts can never disagree.
     *
     * @return array{total:int,active:int,inactive:int,archived:int,pending:int}
     */
    public function summary(): array
    {
        $buckets = ['total' => 0, 'active' => 0, 'inactive' => 0, 'archived' => 0, 'pending' => 0];

        $sources = [];

        if ($this->tableExists('drivers') && $this->has('drivers', 'status')) {
            $sources[] = 'SELECT status FROM drivers';
        }
        if ($this->tableExists('inspector_accounts') && $this->has('inspector_accounts', 'status')) {
            $sources[] = 'SELECT ia.status AS status FROM inspector_accounts ia';
        }
        if ($this->tableExists('users') && $this->has('users', 'role') && $this->has('users', 'status')) {
            $sources[] = "SELECT u.status AS status FROM users u"
                . ' WHERE ' . self::safeCol("REPLACE(LOWER(u.role),' ','_')")
                . " IN ('finance_officer','finance_manager','admin','super_admin')";
        }

        foreach ($sources as $sql) {
            try {
                foreach ($this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $bucket = $this->classifyStatus((string)($row['status'] ?? ''));
                    $buckets[$bucket]++;
                    $buckets['total']++;
                }
            } catch (\Throwable $e) {
                // A missing/broken source simply contributes nothing.
            }
        }

        return $buckets;
    }

    private function classifyStatus(string $raw): string
    {
        $raw = strtolower(trim($raw));

        if ($raw === 'archived') {
            return 'archived';
        }
        if (in_array($raw, ['active', 'approved'], true)) {
            return 'active';
        }
        if ($raw === '' || $raw === 'pending') {
            return 'pending';
        }

        return 'inactive';
    }

    public function find(string $role, string $ref): ?array
    {
        $role = strtolower(trim($role));
        if (!isset(self::roles()[$role]) || $ref === '') {
            return null;
        }
        foreach ($this->rowsForRole($role, '', '', $ref) as $row) {
            return $this->normalizeRow($role, $row);
        }
        return null;
    }

    private function countForRole(string $role, string $search, string $status, array $extra = []): int
    {
        try {
            [$sql, $params] = $this->sourceQuery($role, $search, $status, '', true, $extra);
        } catch (\Throwable $e) {
            $this->sourceErrors[$role] = $this->friendlyError($role, $e);
            return 0;
        }
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->sourceErrors[$role] = $this->friendlyError($role, $e);
            error_log('RoleDirectoryModel count failed (' . $role . '): ' . $e->getMessage());
            return 0;
        }
    }

    private function rowsForRole(string $role, string $search, string $status, string $ref = '', array $extra = []): array
    {
        try {
            [$sql, $params] = $this->sourceQuery($role, $search, $status, $ref, false, $extra);
        } catch (\Throwable $e) {
            $this->sourceErrors[$role] = $this->friendlyError($role, $e);
            return [];
        }
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->sourceErrors[$role] = $this->friendlyError($role, $e);
            error_log('RoleDirectoryModel list failed (' . $role . '): ' . $e->getMessage());
            return [];
        }
    }

    private function friendlyError(string $role, \Throwable $e): string
    {
        $label = self::roles()[$role] ?? $role;
        $reason = trim($e->getMessage());
        if ($reason !== '' && mb_strlen($reason) > 200) {
            $reason = mb_substr($reason, 0, 200) . '…';
        }

        return $label . ' accounts could not be loaded from this database (its tables or columns are missing).'
            . ($reason !== '' ? ' Details: ' . $reason : '');
    }

    /**
     * Force an expression into utf8mb4 with an explicit collation.
     *
     * An explicit COLLATE always wins in MySQL's collation resolution, so a
     * comparison against a literal or bound parameter can never raise error
     * 1267 "Illegal mix of collations", whatever the server's connection
     * default or the legacy tables' collation happens to be. CONVERT is used
     * because a legacy column may be utf8/utf8mb3, which would reject a
     * utf8mb4-only COLLATE directly.
     */
    private static function safeCol(string $expr): string
    {
        return 'CONVERT(' . $expr . ' USING utf8mb4) COLLATE utf8mb4_general_ci';
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

    /**
     * SQL expression for an optional column: NULL when the column is absent.
     */
    private function expr(string $table, string $column, string $expression): string
    {
        return $this->has($table, $column) ? $expression : 'NULL';
    }

    private function textExpr(string $table, string $column, string $expression): string
    {
        return $this->has($table, $column) ? $expression : "''";
    }

    /**
     * @return array{0:string,1:array}
     */
    private function sourceQuery(string $role, string $search, string $status, string $ref, bool $count, array $extra = []): array
    {
        $where = [];
        $params = [];
        $select = 'COUNT(*)';
        $from = '';
        $groupBy = '';
        $orderBy = '';

        $like = $search !== '' ? '%' . $search . '%' : '';
        $pushLike = static function (array &$params, array &$where, array $expressions, string $like): void {
            if ($like === '' || !$expressions) {
                return;
            }
            $where[] = '(' . implode(' OR ', array_map(
                static fn ($e) => self::safeCol((string)$e) . ' LIKE ?',
                $expressions
            )) . ')';
            foreach ($expressions as $ignored) {
                $params[] = $like;
            }
        };
        $pushStatus = static function (array &$params, array &$where, string $statusExpr, string $status): void {
            if ($status === '') {
                return;
            }
            $where[] = self::safeCol($statusExpr) . ' = ?';
            $params[] = $status;
        };
        // Advanced filters. A role whose source has no such column simply
        // cannot match, so the filter excludes it rather than being ignored.
        $pushExtra = static function (array &$params, array &$where, array $spec, array $extra): void {
            if (($extra['department'] ?? '') !== '') {
                if (($spec['department'] ?? null) !== null) {
                    // general_ci is case-insensitive, so no LOWER() is needed.
                    $where[] = self::safeCol((string)$spec['department']) . ' = ?';
                    $params[] = $extra['department'];
                } else {
                    $where[] = '1 = 0';
                }
            }
            if (($extra['employment'] ?? '') !== '') {
                if (($spec['employment'] ?? null) !== null) {
                    $where[] = self::safeCol((string)$spec['employment']) . ' = ?';
                    $params[] = $extra['employment'];
                } else {
                    $where[] = '1 = 0';
                }
            }
            if (($extra['created_from'] ?? '') !== '' && ($spec['created'] ?? null) !== null) {
                $where[] = $spec['created'] . ' >= ?';
                $params[] = $extra['created_from'] . ' 00:00:00';
            }
            if (($extra['created_to'] ?? '') !== '' && ($spec['created'] ?? null) !== null) {
                $where[] = $spec['created'] . ' <= ?';
                $params[] = $extra['created_to'] . ' 23:59:59';
            }
        };

        switch ($role) {
            case 'driver':
                if (!$this->tableExists('drivers')) {
                    throw new \RuntimeException('drivers table missing');
                }
                $hasAccounts = $this->tableExists('driver_accounts');
                $hasAssignments = $this->tableExists('driver_bus_assignment');
                $hasSupervisor = $this->has('drivers', 'supervisor_id') && $this->tableExists('users');
                if ($ref === '') { $where[] = 'LOWER(COALESCE(d.status,\'\')) ' . ($status === 'archived' ? '=' : '<>') . " 'archived'"; }

                $statusCase = '(CASE WHEN LOWER(' . self::safeCol('d.status') . ') = \'archived\' THEN \'archived\' WHEN LOWER(' . self::safeCol('d.status') . ') IN (\'inactive\',\'disabled\',\'deactivated\') THEN \'inactive\' '
                    . 'WHEN LOWER(' . self::safeCol('d.status') . ') IN (\'active\',\'approved\') THEN \'active\' '
                    . 'WHEN LOWER(' . self::safeCol('d.status') . ') = \'rejected\' THEN \'rejected\' ELSE \'pending\' END)';

                if ($ref !== '') {
                    $where[] = 'd.id = ?';
                    $params[] = $ref;
                }
                $pushLike($params, $where, array_values(array_filter([
                    $this->has('drivers', 'full_name') ? 'd.full_name' : null,
                    $this->has('drivers', 'driver_id') ? 'd.driver_id' : null,
                    $this->has('drivers', 'email') ? 'd.email' : null,
                    $this->has('drivers', 'contact_number') ? 'd.contact_number' : null,
                    $this->has('drivers', 'assigned_buses') ? 'd.assigned_buses' : null,
                    $this->has('drivers', 'routes') ? 'd.routes' : null,
                    $this->has('drivers', 'license_number') ? 'd.license_number' : null,
                ])), $like);
                $pushStatus($params, $where, $statusCase, $status);
                $pushExtra($params, $where, [
                    'department' => null,
                    'employment' => $this->has('drivers', 'employment_status') ? 'd.employment_status' : null,
                    'created' => $this->has('drivers', 'created_at') ? 'd.created_at' : null,
                ], $extra);

                if (!$count) {
                    $select = implode(', ', [
                        'd.id AS ref',
                        $this->textExpr('drivers', 'driver_id', 'd.driver_id') . ' AS user_id',
                        $this->textExpr('drivers', 'full_name', 'd.full_name') . ' AS name',
                        $this->textExpr('drivers', 'email', 'd.email') . ' AS email',
                        $this->textExpr('drivers', 'contact_number', 'd.contact_number') . ' AS contact',
                        $this->textExpr('drivers', 'status', 'd.status') . ' AS raw_status',
                        $this->expr('drivers', 'created_at', 'd.created_at') . ' AS created_at',
                        $this->textExpr('drivers', 'photo', 'd.photo') . ' AS photo',
                        $this->textExpr('drivers', 'resume_file', 'd.resume_file') . ' AS resume_file',
                        $this->textExpr('drivers', 'supporting_docs', 'd.supporting_docs') . ' AS supporting_docs',
                        $this->textExpr('drivers', 'license_number', 'd.license_number') . ' AS license_number',
                        $this->textExpr('drivers', 'license_type', 'd.license_type') . ' AS license_type',
                        $this->textExpr('drivers', 'license_expiry', 'd.license_expiry') . ' AS license_expiry',
                        $this->textExpr('drivers', 'experience_years', 'd.experience_years') . ' AS experience_years',
                        $this->textExpr('drivers', 'assigned_buses', 'd.assigned_buses') . ' AS assigned_buses',
                        $this->textExpr('drivers', 'routes', 'd.routes') . ' AS routes',
                        $this->textExpr('drivers', 'approval_status', 'd.approval_status') . ' AS approval_status',
                        $this->expr('drivers', 'birth_date', 'd.birth_date') . ' AS birth_date',
                        $this->expr('drivers', 'hired_date', 'd.hired_date') . ' AS hired_date',
                        ($hasAccounts ? 'da.username' : 'NULL') . ' AS username',
                        ($hasAccounts ? 'da.id' : 'NULL') . ' AS account_ref',
                        // Correlated subquery instead of a grouped JOIN: works on
                        // every sql_mode (including ONLY_FULL_GROUP_BY) and cannot
                        // multiply rows when a driver has several bus assignments.
                        ($hasAssignments
                            ? '(SELECT COUNT(*) FROM driver_bus_assignment dba2 WHERE dba2.driver_id = d.driver_id)'
                            : '0') . ' AS total_bus_assignments',
                        $this->textExpr('drivers', 'sex', 'd.sex') . ' AS sex',
                        $this->textExpr('drivers', 'address', 'd.address') . ' AS address',
                        $this->textExpr('drivers', 'employment_type', 'd.employment_type') . ' AS employment_type',
                        $this->textExpr('drivers', 'employment_status', 'd.employment_status') . ' AS employment_status',
                        ($hasSupervisor ? 'd.supervisor_id' : 'NULL') . ' AS supervisor_id',
                        ($hasSupervisor ? 'COALESCE(sup.full_name, sup.username)' : 'NULL') . ' AS supervisor_name',
                        $this->textExpr('drivers', 'work_shift', 'd.work_shift') . ' AS work_shift',
                        $this->textExpr('drivers', 'availability', 'd.availability') . ' AS availability',
                        $this->textExpr('drivers', 'created_by', 'd.created_by') . ' AS created_by',
                        $this->expr('drivers', 'updated_at', 'd.updated_at') . ' AS updated_at',
                    ]);
                }
                $from = 'drivers d'
                    . ($hasAccounts ? ' LEFT JOIN driver_accounts da ON da.driver_id = ' . self::safeCol('d.driver_id') : '')
                    . ($hasSupervisor ? ' LEFT JOIN users sup ON sup.id = d.supervisor_id' : '');
                $orderBy = $this->has('drivers', 'created_at') ? 'ORDER BY d.created_at DESC' : 'ORDER BY d.id DESC';
                break;

            case 'inspector':
            case 'dispatcher':
                if (!$this->tableExists('inspectors') || !$this->tableExists('inspector_accounts')) {
                    throw new \RuntimeException('inspectors tables missing');
                }
                $hasTerminals = $this->tableExists('terminals');
                $hasSupervisor = $this->has('inspectors', 'supervisor_id') && $this->tableExists('users');
                if ($ref === '') { $where[] = 'LOWER(COALESCE(ia.status,\'\')) ' . ($status === 'archived' ? '=' : '<>') . " 'archived'"; }

                $statusCase = '(CASE WHEN LOWER(' . self::safeCol('ia.status') . ') = \'archived\' THEN \'archived\' WHEN LOWER(' . self::safeCol('ia.status') . ') IN (\'inactive\',\'disabled\',\'deactivated\') THEN \'inactive\' '
                    . 'WHEN LOWER(' . self::safeCol('ia.status') . ') IN (\'active\',\'approved\') THEN \'active\' '
                    . 'WHEN LOWER(' . self::safeCol('ia.status') . ') = \'rejected\' THEN \'rejected\' ELSE \'pending\' END)';

                if ($ref !== '') {
                    $where[] = 'i.inspector_pk = ?';
                    $params[] = $ref;
                }

                $roleExpr = self::safeCol("REPLACE(LOWER(COALESCE(NULLIF(" . self::safeCol('ia.role') . ",''),'inspector')),' ','_')");
                if ($role === 'dispatcher') {
                    $where[] = $roleExpr . ' = ?';
                    $params[] = 'dispatcher';
                } else {
                    // The Inspector tab lists every inspector profile that is
                    // not a dispatcher (matches the original Inspectors page).
                    $where[] = $roleExpr . ' <> ?';
                    $params[] = 'dispatcher';
                }

                $pushLike($params, $where, array_values(array_filter([
                    $this->has('inspectors', 'inspector_name') ? 'i.inspector_name' : null,
                    $this->has('inspector_accounts', 'inspector_id') ? 'ia.inspector_id' : null,
                    $this->has('inspectors', 'email') ? 'i.email' : null,
                    $this->has('inspectors', 'contact_number') ? 'i.contact_number' : null,
                    $this->has('inspectors', 'department') ? 'i.department' : null,
                    $this->has('inspectors', 'operational_assignment') ? 'i.operational_assignment' : null,
                ])), $like);
                $pushStatus($params, $where, $statusCase, $status);
                $pushExtra($params, $where, [
                    'department' => $this->has('inspectors', 'department') ? 'i.department' : null,
                    'employment' => $this->has('inspectors', 'employment_status') ? 'i.employment_status' : null,
                    'created' => $this->has('inspectors', 'created_at') ? 'i.created_at' : null,
                ], $extra);

                if (!$count) {
                    $select = implode(', ', [
                        'i.inspector_pk AS ref',
                        $this->textExpr('inspector_accounts', 'inspector_id', 'ia.inspector_id') . ' AS user_id',
                        $this->textExpr('inspectors', 'inspector_name', 'i.inspector_name') . ' AS name',
                        $this->textExpr('inspectors', 'email', 'i.email') . ' AS email',
                        $this->textExpr('inspectors', 'contact_number', 'i.contact_number') . ' AS contact',
                        $this->textExpr('inspector_accounts', 'status', 'ia.status') . ' AS raw_status',
                        $this->expr('inspectors', 'created_at', 'i.created_at') . ' AS created_at',
                        $this->textExpr('inspectors', 'profile_picture', 'i.profile_picture') . ' AS photo',
                        $this->textExpr('inspectors', 'resume_file', 'i.resume_file') . ' AS resume_file',
                        $this->textExpr('inspectors', 'supporting_docs', 'i.supporting_docs') . ' AS supporting_docs',
                        $this->textExpr('inspectors', 'inspector_identity', 'i.inspector_identity') . ' AS inspector_identity',
                        $this->textExpr('inspectors', 'department', 'i.department') . ' AS department',
                        $this->textExpr('inspectors', 'experience_years', 'i.experience_years') . ' AS experience_years',
                        $this->textExpr('inspectors', 'operational_assignment', 'i.operational_assignment') . ' AS operational_assignment',
                        $this->textExpr('inspectors', 'additional_notes', 'i.additional_notes') . ' AS additional_notes',
                        ($hasTerminals ? 't.terminal_name' : 'NULL') . ' AS terminal_name',
                        ($hasTerminals ? 'i.assigned_routes' : 'NULL') . ' AS terminal_id',
                        $this->expr('inspectors', 'birth_date', 'i.birth_date') . ' AS birth_date',
                        $this->expr('inspectors', 'hired_date', 'i.hired_date') . ' AS hired_date',
                        'ia.account_id AS account_ref',
                        'ia.role AS raw_role',
                        $this->textExpr('inspectors', 'sex', 'i.sex') . ' AS sex',
                        $this->textExpr('inspectors', 'address', 'i.address') . ' AS address',
                        $this->textExpr('inspectors', 'assigned_area', 'i.assigned_area') . ' AS assigned_area',
                        $this->textExpr('inspectors', 'employment_type', 'i.employment_type') . ' AS employment_type',
                        $this->textExpr('inspectors', 'employment_status', 'i.employment_status') . ' AS employment_status',
                        ($hasSupervisor ? 'i.supervisor_id' : 'NULL') . ' AS supervisor_id',
                        ($hasSupervisor ? 'COALESCE(sup.full_name, sup.username)' : 'NULL') . ' AS supervisor_name',
                        $this->textExpr('inspectors', 'work_shift', 'i.work_shift') . ' AS work_shift',
                        $this->textExpr('inspectors', 'availability', 'i.availability') . ' AS availability',
                        $this->textExpr('inspectors', 'created_by', 'i.created_by') . ' AS created_by',
                        $this->expr('inspectors', 'updated_at', 'i.updated_at') . ' AS updated_at',
                    ]);
                }
                $from = 'inspectors i INNER JOIN inspector_accounts ia ON ia.account_id = i.account_id'
                    . ($hasTerminals ? ' LEFT JOIN terminals t ON t.id = i.assigned_routes' : '')
                    . ($hasSupervisor ? ' LEFT JOIN users sup ON sup.id = i.supervisor_id' : '');
                $orderBy = $this->has('inspectors', 'created_at') ? 'ORDER BY i.created_at DESC' : 'ORDER BY i.inspector_pk DESC';
                break;

            case 'finance_officer':
            case 'finance_manager':
            case 'admin':
            case 'super_admin':
                if (!$this->tableExists('users') || !$this->has('users', 'role')) {
                    throw new \RuntimeException('users table missing');
                }

                $hasSupervisor = $this->has('users', 'supervisor_id');

                $nameParts = [];
                if ($this->has('users', 'full_name')) {
                    $nameParts[] = 'NULLIF(' . self::safeCol('u.full_name') . ",'')";
                }
                if ($this->has('users', 'first_name') || $this->has('users', 'last_name')) {
                    $first = $this->has('users', 'first_name') ? "COALESCE(u.first_name,'')" : "''";
                    $last = $this->has('users', 'last_name') ? "COALESCE(u.last_name,'')" : "''";
                    $nameParts[] = 'NULLIF(' . self::safeCol("TRIM(CONCAT($first,' ',$last))") . ",'')";
                }
                if ($this->has('users', 'username')) {
                    $nameParts[] = 'NULLIF(' . self::safeCol('u.username') . ",'')";
                }
                $nameParts[] = "CONCAT('User #', u.id)";
                $nameExpr = 'COALESCE(' . implode(', ', $nameParts) . ')';

                $contactParts = [];
                if ($this->has('users', 'contact_no')) {
                    $contactParts[] = 'NULLIF(' . self::safeCol('u.contact_no') . ",'')";
                }
                if ($this->has('users', 'phone')) {
                    $contactParts[] = 'NULLIF(' . self::safeCol('u.phone') . ",'')";
                }
                $contactExpr = $contactParts ? 'COALESCE(' . implode(', ', $contactParts) . ')' : "''";

                $roleExpr = self::safeCol("REPLACE(LOWER(" . self::safeCol('u.role') . "),' ','_')");
                if ($ref !== '') {
                    $where[] = 'u.id = ?';
                    $params[] = $ref;
                }
                $where[] = $roleExpr . ' = ?';
                $params[] = $role;

                $searchExprs = [];
                foreach ([
                    $this->has('users', 'full_name') ? "COALESCE(u.full_name,'')" : null,
                    $this->has('users', 'first_name') ? "COALESCE(u.first_name,'')" : null,
                    $this->has('users', 'last_name') ? "COALESCE(u.last_name,'')" : null,
                    $this->has('users', 'employee_id') ? "COALESCE(u.employee_id,'')" : null,
                    $this->has('users', 'email') ? "COALESCE(u.email,'')" : null,
                    $this->has('users', 'contact_no') ? "COALESCE(u.contact_no,'')" : null,
                    $this->has('users', 'phone') ? "COALESCE(u.phone,'')" : null,
                    $this->has('users', 'department') ? "COALESCE(u.department,'')" : null,
                    $this->has('users', 'username') ? "COALESCE(u.username,'')" : null,
                ] as $searchExpr) {
                    if ($searchExpr !== null) {
                        $searchExprs[] = $searchExpr;
                    }
                }
                $pushLike($params, $where, $searchExprs, $like);

                if ($this->has('users', 'status')) {
                    if ($ref === '') { $where[] = 'LOWER(COALESCE(u.status,\'\')) ' . ($status === 'archived' ? '=' : '<>') . " 'archived'"; }
                    $statusCase = '(CASE WHEN LOWER(' . self::safeCol('u.status') . ') = \'archived\' THEN \'archived\' WHEN LOWER(COALESCE(NULLIF(' . self::safeCol('u.status') . ',\'\'),\'pending\')) IN (\'inactive\',\'disabled\',\'deactivated\') THEN \'inactive\' '
                        . 'WHEN LOWER(COALESCE(NULLIF(' . self::safeCol('u.status') . ',\'\'),\'pending\')) IN (\'active\',\'approved\') THEN \'active\' '
                        . 'WHEN LOWER(COALESCE(NULLIF(' . self::safeCol('u.status') . ',\'\'),\'pending\')) = \'rejected\' THEN \'rejected\' ELSE \'pending\' END)';
                    $pushStatus($params, $where, $statusCase, $status);
                }

                $pushExtra($params, $where, [
                    'department' => $this->has('users', 'department') ? 'u.department' : null,
                    'employment' => $this->has('users', 'employment_status') ? 'u.employment_status' : null,
                    'created' => $this->has('users', 'created_at') ? 'u.created_at' : null,
                ], $extra);

                if (!$count) {
                    $select = implode(', ', [
                        'u.id AS ref',
                        $this->has('users', 'employee_id')
                            ? 'COALESCE(NULLIF(' . self::safeCol('u.employee_id') . ",''), CONCAT('USR-', u.id)) AS user_id"
                            : "CONCAT('USR-', u.id) AS user_id",
                        $nameExpr . ' AS name',
                        $this->textExpr('users', 'email', 'u.email') . ' AS email',
                        $contactExpr . ' AS contact',
                        ($this->has('users', 'status') ? 'COALESCE(NULLIF(' . self::safeCol('u.status') . ",''),'pending')" : "'active'") . ' AS raw_status',
                        $this->expr('users', 'created_at', 'u.created_at') . ' AS created_at',
                        $this->expr('users', 'profile_picture', 'u.profile_picture') . ' AS photo_picture',
                        $this->expr('users', 'profile_photo', 'u.profile_photo') . ' AS photo_photo',
                        $this->textExpr('users', 'resume_file', 'u.resume_file') . ' AS resume_file',
                        $this->textExpr('users', 'supporting_docs', 'u.supporting_docs') . ' AS supporting_docs',
                        $this->textExpr('users', 'department', 'u.department') . ' AS department',
                        $this->textExpr('users', 'assigned_area', 'u.assigned_area') . ' AS assigned_area',
                        $this->textExpr('users', 'shift_status', 'u.shift_status') . ' AS shift_status',
                        $this->textExpr('users', 'access_level', 'u.access_level') . ' AS access_level',
                        $this->textExpr('users', 'shift_schedule', 'u.shift_schedule') . ' AS shift_schedule',
                        $this->textExpr('users', 'address', 'u.address') . ' AS address',
                        $this->textExpr('users', 'bio', 'u.bio') . ' AS bio',
                        $this->textExpr('users', 'username', 'u.username') . ' AS username',
                        'u.role AS raw_role',
                        $this->expr('users', 'is_active', 'u.is_active') . ' AS is_active',
                        $this->expr('users', 'birth_date', 'u.birth_date') . ' AS birth_date',
                        $this->expr('users', 'hired_date', 'u.hired_date') . ' AS hired_date',
                        $this->textExpr('users', 'sex', 'u.sex') . ' AS sex',
                        $this->textExpr('users', 'position_title', 'u.position_title') . ' AS position_title',
                        $this->textExpr('users', 'assigned_office', 'u.assigned_office') . ' AS assigned_office',
                        $this->textExpr('users', 'employment_type', 'u.employment_type') . ' AS employment_type',
                        $this->textExpr('users', 'employment_status', 'u.employment_status') . ' AS employment_status',
                        ($hasSupervisor ? 'u.supervisor_id' : 'NULL') . ' AS supervisor_id',
                        ($hasSupervisor ? 'COALESCE(sup.full_name, sup.username)' : 'NULL') . ' AS supervisor_name',
                        $this->textExpr('users', 'work_shift', 'u.work_shift') . ' AS work_shift',
                        $this->textExpr('users', 'created_by', 'u.created_by') . ' AS created_by',
                        $this->expr('users', 'updated_at', 'u.updated_at') . ' AS updated_at',
                    ]);
                }
                $from = 'users u'
                    . ($hasSupervisor ? ' LEFT JOIN users sup ON sup.id = u.supervisor_id' : '');
                $orderBy = $this->has('users', 'created_at') ? 'ORDER BY u.created_at DESC' : 'ORDER BY u.id DESC';
                break;

            default:
                throw new \InvalidArgumentException('Unknown role.');
        }

        $sql = 'SELECT ' . $select . ' FROM ' . $from;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        if (!$count) {
            $sql .= $groupBy . ' ' . $orderBy . ' LIMIT 5000';
        }

        return [$sql, $params];
    }

    private function normalizeRow(string $role, array $row): array
    {
        $rawStatus = (string)($row['raw_status'] ?? '');
        $status = self::normalizeStatus($rawStatus);
        $archive = null;
        if ($status === 'archived' && $this->tableExists('audit_trail')) {
            $source = \Admin\Services\RoleManagement\SecurityCodeService::targetLabel($role);
            if ($source === 'super_admin') { $source = 'admin'; }
            $archive = \Admin\Services\RoleManagement\ArchiveMetadata::details($this->db, $source, (string) $row['ref']);
        }
        $labels = self::statusOptions();

        $photo = (string)($row['photo'] ?? '');
        if ($photo === '') {
            $photo = (string)($row['photo_picture'] ?? '');
        }
        if ($photo === '') {
            $photo = (string)($row['photo_photo'] ?? '');
        }

        $extra = [];
        $assignment = '';

        switch ($role) {
            case 'driver':
                $assignment = trim((string)($row['routes'] ?? ''));
                $extra = [
                    ['label' => 'License No.', 'value' => (string)($row['license_number'] ?? '')],
                    ['label' => 'License Type', 'value' => (string)($row['license_type'] ?? '')],
                    ['label' => 'License Expiry', 'value' => (string)($row['license_expiry'] ?? '')],
                    ['label' => 'Experience', 'value' => $this->years((string)($row['experience_years'] ?? ''))],
                    ['label' => 'Assigned Buses', 'value' => (string)($row['assigned_buses'] ?? '')],
                    ['label' => 'Routes', 'value' => (string)($row['routes'] ?? '')],
                    ['label' => 'Approval', 'value' => (string)($row['approval_status'] ?? '')],
                    ['label' => 'Account', 'value' => (string)($row['username'] ?? '')],
                ];
                break;

            case 'inspector':
            case 'dispatcher':
                $assignment = trim((string)($row['operational_assignment'] ?? ''));
                $extra = [
                    ['label' => 'Identity', 'value' => (string)($row['inspector_identity'] ?? '')],
                    ['label' => 'Department', 'value' => (string)($row['department'] ?? '')],
                    ['label' => 'Experience', 'value' => $this->years((string)($row['experience_years'] ?? ''))],
                    ['label' => 'Operational Assignment', 'value' => (string)($row['operational_assignment'] ?? '')],
                    ['label' => 'Assigned Terminal', 'value' => (string)($row['terminal_name'] ?? '')],
                    ['label' => 'Notes', 'value' => (string)($row['additional_notes'] ?? '')],
                ];
                break;

            default:
                $assignment = trim((string)($row['assigned_area'] ?? ''));
                $extra = [
                    ['label' => 'Department', 'value' => (string)($row['department'] ?? '')],
                    ['label' => 'Assigned Area', 'value' => (string)($row['assigned_area'] ?? '')],
                    ['label' => 'Shift Status', 'value' => (string)($row['shift_status'] ?? '')],
                    ['label' => 'Access Level', 'value' => (string)($row['access_level'] ?? '')],
                    ['label' => 'Shift Schedule', 'value' => (string)($row['shift_schedule'] ?? '')],
                    ['label' => 'Address', 'value' => (string)($row['address'] ?? '')],
                    ['label' => 'Bio', 'value' => (string)($row['bio'] ?? '')],
                    ['label' => 'Active Flag', 'value' => isset($row['is_active']) && $row['is_active'] !== null ? ((int)$row['is_active'] === 1 ? 'Yes' : 'No') : ''],
                ];
                break;
        }

        $birthDate = (string)($row['birth_date'] ?? '');
        $hiredDate = (string)($row['hired_date'] ?? '');
        if ($birthDate !== '') {
            $extra[] = ['label' => 'Birth Date', 'value' => $birthDate];
            $age = PersonnelFields::ageFrom($birthDate);
            if ($age !== null) {
                $extra[] = ['label' => 'Age', 'value' => $age . ' years old'];
            }
        }
        if ($hiredDate !== '') {
            $extra[] = ['label' => 'Hired Date', 'value' => $hiredDate];
        }

        $extra = array_values(array_filter($extra, static fn ($field) => trim((string)$field['value']) !== ''));

        $edit = [];
        if ($role === 'driver') {
            $edit = [
                'full_name' => (string)($row['name'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'contact_number' => (string)($row['contact'] ?? ''),
                'status' => $rawStatus !== '' ? $rawStatus : 'Active',
                'license_number' => (string)($row['license_number'] ?? ''),
                'license_type' => (string)($row['license_type'] ?? ''),
                'license_expiry' => (string)($row['license_expiry'] ?? ''),
                'experience_years' => (string)($row['experience_years'] ?? ''),
                'assigned_buses' => (string)($row['assigned_buses'] ?? ''),
                'routes' => (string)($row['routes'] ?? ''),
                'approval_status' => (string)($row['approval_status'] ?? ''),
                'birth_date' => $birthDate,
                'hired_date' => $hiredDate,
                'sex' => (string)($row['sex'] ?? ''),
                'address' => (string)($row['address'] ?? ''),
                'employment_type' => (string)($row['employment_type'] ?? ''),
                'employment_status' => (string)($row['employment_status'] ?? ''),
                'supervisor_id' => (string)($row['supervisor_id'] ?? ''),
                'work_shift' => (string)($row['work_shift'] ?? ''),
                'availability' => (string)($row['availability'] ?? ''),
            ];
        } elseif (in_array($role, ['inspector', 'dispatcher'], true)) {
            $edit = [
                'account_id' => (string)($row['account_ref'] ?? ''),
                'role' => (string)($row['raw_role'] ?? $role),
                'inspector_name' => (string)($row['name'] ?? ''),
                'inspector_identity' => (string)($row['inspector_identity'] ?? ''),
                'department' => (string)($row['department'] ?? ''),
                'experience_years' => (string)($row['experience_years'] ?? ''),
                'operational_assignment' => (string)($row['operational_assignment'] ?? ''),
                'contact_number' => (string)($row['contact'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'terminal_id' => (string)($row['terminal_id'] ?? ''),
                'assigned_routes' => (string)($row['terminal_id'] ?? ''),
                'additional_notes' => (string)($row['additional_notes'] ?? ''),
                'birth_date' => $birthDate,
                'hired_date' => $hiredDate,
                'sex' => (string)($row['sex'] ?? ''),
                'address' => (string)($row['address'] ?? ''),
                'assigned_area' => (string)($row['assigned_area'] ?? ''),
                'employment_type' => (string)($row['employment_type'] ?? ''),
                'employment_status' => (string)($row['employment_status'] ?? ''),
                'supervisor_id' => (string)($row['supervisor_id'] ?? ''),
                'work_shift' => (string)($row['work_shift'] ?? ''),
                'availability' => (string)($row['availability'] ?? ''),
            ];
        } else {
            $edit = [
                'full_name' => (string)($row['name'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'contact_no' => (string)($row['contact'] ?? ''),
                'status' => strtolower((string)($row['raw_status'] ?? 'active')),
                'department' => (string)($row['department'] ?? ''),
                'assigned_area' => (string)($row['assigned_area'] ?? ''),
                'shift_status' => (string)($row['shift_status'] ?? ''),
                'access_level' => (string)($row['access_level'] ?? ''),
                'shift_schedule' => (string)($row['shift_schedule'] ?? ''),
                'address' => (string)($row['address'] ?? ''),
                'bio' => (string)($row['bio'] ?? ''),
                'username' => (string)($row['username'] ?? ''),
                'role' => (string)($row['raw_role'] ?? $role),
                'birth_date' => $birthDate,
                'hired_date' => $hiredDate,
                'sex' => (string)($row['sex'] ?? ''),
                'position_title' => (string)($row['position_title'] ?? ''),
                'assigned_office' => (string)($row['assigned_office'] ?? ''),
                'employment_type' => (string)($row['employment_type'] ?? ''),
                'employment_status' => (string)($row['employment_status'] ?? ''),
                'supervisor_id' => (string)($row['supervisor_id'] ?? ''),
                'work_shift' => (string)($row['work_shift'] ?? ''),
            ];
        }

        $cleanRows = static function (array $rows): array {
            return array_values(array_filter(
                $rows,
                static fn (array $field): bool => trim((string)$field['value']) !== ''
            ));
        };

        $employment = $cleanRows([
            ['label' => 'Employee No.', 'value' => (string)($row['user_id'] ?? '')],
            ['label' => 'Employment Type', 'value' => (string)($row['employment_type'] ?? '')],
            ['label' => 'Employment Status', 'value' => (string)($row['employment_status'] ?? '')],
            ['label' => 'Date Hired', 'value' => $hiredDate],
            ['label' => 'Department', 'value' => (string)($row['department'] ?? '')],
            ['label' => 'Supervisor', 'value' => (string)($row['supervisor_name'] ?? '')],
            ['label' => 'Work Shift', 'value' => (string)($row['work_shift'] ?? '')],
        ]);

        if ($role === 'driver') {
            $roleSpecific = $cleanRows([
                ['label' => 'License No.', 'value' => (string)($row['license_number'] ?? '')],
                ['label' => 'License Classification', 'value' => (string)($row['license_type'] ?? '')],
                ['label' => 'License Expiry', 'value' => (string)($row['license_expiry'] ?? '')],
                ['label' => 'Assigned Bus', 'value' => (string)($row['assigned_buses'] ?? '')],
                ['label' => 'Assigned Route', 'value' => (string)($row['routes'] ?? '')],
                ['label' => 'Availability', 'value' => (string)($row['availability'] ?? '')],
            ]);
        } elseif (in_array($role, ['inspector', 'dispatcher'], true)) {
            $roleSpecific = $cleanRows([
                ['label' => 'Identity', 'value' => (string)($row['inspector_identity'] ?? '')],
                ['label' => 'Assigned Terminal', 'value' => (string)($row['terminal_name'] ?? '')],
                ['label' => 'Assigned Bus / Area', 'value' => (string)($row['assigned_area'] ?? '')],
                ['label' => 'Work Shift', 'value' => (string)($row['work_shift'] ?? '')],
                ['label' => 'Availability', 'value' => (string)($row['availability'] ?? '')],
            ]);
        } else {
            $roleSpecific = $cleanRows([
                ['label' => 'Position', 'value' => (string)($row['position_title'] ?? '')],
                ['label' => 'Assigned Office', 'value' => (string)($row['assigned_office'] ?? '')],
                ['label' => 'Assigned Area', 'value' => (string)($row['assigned_area'] ?? '')],
                ['label' => 'Shift Status', 'value' => (string)($row['shift_status'] ?? '')],
                ['label' => 'Access Level', 'value' => (string)($row['access_level'] ?? '')],
            ]);
        }

        return [
            'role' => $role,
            'role_label' => self::roles()[$role],
            'kind' => $role === 'driver' ? 'driver' : (in_array($role, ['inspector', 'dispatcher'], true) ? 'inspector' : 'user'),
            'cv_subject' => self::cvSubjectType($role),
            'ref' => (string)($row['ref'] ?? ''),
            'account_ref' => (string)($row['account_ref'] ?? ''),
            'user_id' => (string)($row['user_id'] ?? ''),
            'name' => trim((string)($row['name'] ?? '')) !== '' ? (string)$row['name'] : 'Unnamed account',
            'email' => (string)($row['email'] ?? ''),
            'contact' => (string)($row['contact'] ?? ''),
            'status' => $status,
            'status_label' => $labels[$status] ?? ucfirst($status),
            'raw_status' => $rawStatus,
            'archive_reason' => $archive['reason'] ?? null,
            'archived_at' => $archive['archived_at'] ?? null,
            'archived_by' => $archive['archived_by'] ?? null,
            'photo' => $this->docPath($photo),
            'created_at' => (string)($row['created_at'] ?? ''),
            'department' => (string)($row['department'] ?? ''),
            'assignment' => $assignment,
            'address' => (string)($row['address'] ?? ''),
            'sex' => (string)($row['sex'] ?? ''),
            'created_by' => (string)($row['created_by'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'employment' => $employment,
            'role_specific' => $roleSpecific,
            'extra' => $extra,
            'edit' => $edit,
            'documents' => [
                'resume' => $this->docPath((string)($row['resume_file'] ?? '')),
                'supporting' => $this->docPath((string)($row['supporting_docs'] ?? '')),
            ],
        ];
    }

    private function years(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return $value;
        }
        return $value . ' year(s)';
    }

    private function docPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        return '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}

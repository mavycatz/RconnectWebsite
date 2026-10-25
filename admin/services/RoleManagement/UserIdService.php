<?php declare(strict_types=1);

namespace Admin\Services\RoleManagement;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Centralized User ID generation for every account type.
 *
 * One service owns the numbering for all roles so previews, final allocation
 * and legacy next-ID endpoints can never drift apart:
 *
 *   driver        DRV-001     drivers.driver_id
 *   inspector     INS-001     inspector_accounts.inspector_id
 *   dispatcher    DIS-001     inspector_accounts.inspector_id
 *   finance_*     FIN-001     users.employee_id
 *   admin         ADM-001     users.employee_id
 *   super_admin   SUP-001     users.employee_id
 *
 * Numbers come from a dedicated `user_id_sequences` row per scope and are
 * allocated inside a transaction with a row lock (SELECT ... FOR UPDATE on
 * MySQL), so two simultaneous registrations can never receive the same ID.
 * The number always starts at least three digits and grows beyond 999
 * naturally. Existing IDs are never rewritten.
 */
final class UserIdService
{
    public const PREFIX_BY_SCOPE = [
        'driver' => 'DRV',
        'inspector' => 'INS',
        'dispatcher' => 'DIS',
        'finance' => 'FIN',
        'admin' => 'ADM',
        'super_admin' => 'SUP',
        'bus' => 'BUS',
    ];

    private const TARGET_BY_SCOPE = [
        'driver' => ['table' => 'drivers', 'column' => 'driver_id'],
        'inspector' => ['table' => 'inspector_accounts', 'column' => 'inspector_id'],
        'dispatcher' => ['table' => 'inspector_accounts', 'column' => 'inspector_id'],
        'finance' => ['table' => 'users', 'column' => 'employee_id'],
        'admin' => ['table' => 'users', 'column' => 'employee_id'],
        'super_admin' => ['table' => 'users', 'column' => 'employee_id'],
        'bus' => ['table' => 'buses', 'column' => 'bus_id'],
    ];

    private const UNIQUE_INDEXES = [
        ['drivers', 'driver_id', 'uq_drivers_driver_id'],
        ['inspector_accounts', 'inspector_id', 'uq_inspector_accounts_inspector_id'],
        ['users', 'employee_id', 'uq_users_employee_id'],
        ['buses', 'bus_id', 'uq_buses_bus_id'],
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Map a displayed role to its numbering scope. Returns '' for unknown.
     */
    public static function scopeForRole(string $role): string
    {
        $role = strtolower(trim($role));

        return match ($role) {
            'finance_officer', 'finance_manager' => 'finance',
            'driver', 'inspector', 'dispatcher', 'admin', 'super_admin' => $role,
            default => '',
        };
    }

    /**
     * Format one allocated number: PREFIX + '-' + at least three digits.
     */
    public static function format(string $scope, int $number): string
    {
        $prefix = self::PREFIX_BY_SCOPE[$scope] ?? null;

        if ($prefix === null || $number < 1) {
            throw new RuntimeException('Unknown user ID scope.');
        }

        return sprintf('%s-%03d', $prefix, $number);
    }

    /**
     * Create the sequence table. On first creation, also try to enforce the
     * database-level UNIQUE indexes (skipped safely when legacy duplicates
     * exist — the atomic allocator still prevents new duplicates).
     */
    public static function ensureSchema(PDO $db): void
    {
        $existed = self::tableExists($db, 'user_id_sequences');

        $db->exec(
            'CREATE TABLE IF NOT EXISTS user_id_sequences ('
            . ' scope VARCHAR(32) PRIMARY KEY,'
            . ' last_number INTEGER NOT NULL DEFAULT 0,'
            . ' updated_at VARCHAR(32) NULL'
            . ')'
        );

        if (!$existed) {
            self::ensureUniqueIndexes($db);
        }
    }

    /**
     * Attempt to add the UNIQUE indexes that back the application-level
     * allocation. Never renumbers or changes existing rows.
     *
     * @return array<string,string> target => ok|exists|blocked-duplicates|missing|failed
     */
    public static function ensureUniqueIndexes(PDO $db): array
    {
        $report = [];

        foreach (self::UNIQUE_INDEXES as [$table, $column, $indexName]) {
            $key = $table . '.' . $column;

            if (!self::tableExists($db, $table) || !self::columnExists($db, $table, $column)) {
                $report[$key] = 'missing';
                continue;
            }

            if (self::duplicateCount($db, $table, $column) > 0) {
                $report[$key] = 'blocked-duplicates';
                continue;
            }

            try {
                if (self::driverName($db) === 'sqlite') {
                    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ' . $indexName . ' ON ' . $table . ' (' . $column . ')');
                } else {
                    $db->exec('ALTER TABLE `' . $table . '` ADD UNIQUE INDEX `' . $indexName . '` (`' . $column . '`)');
                }
                $report[$key] = 'ok';
            } catch (PDOException $e) {
                $code = (int)($e->errorInfo[1] ?? 0);
                // 1061 = duplicate index name -> already enforced.
                $report[$key] = $code === 1061 ? 'exists' : 'failed';
            } catch (Throwable $e) {
                $report[$key] = 'failed';
            }
        }

        return $report;
    }

    /**
     * Read-only next-ID preview. Never reserves the number.
     */
    public function preview(string $scope): string
    {
        $scope = $this->requireScope($scope);
        self::ensureSchema($this->db);

        $last = max($this->sequenceValue($scope), $this->existingMax($scope));

        return self::format($scope, $last + 1);
    }

    /**
     * Atomically allocate the next number for a scope.
     *
     * When the caller already opened a transaction (the add models do), the
     * allocation joins it, so a rolled-back registration also rolls back the
     * sequence and the number is reused.
     */
    public function allocate(string $scope): string
    {
        $scope = $this->requireScope($scope);

        // DDL must never run inside the caller's transaction (MySQL DDL causes
        // an implicit commit). Callers that open a transaction call
        // ensureSchema() first; here it is only safe when no transaction is
        // active.
        if (!$this->db->inTransaction()) {
            self::ensureSchema($this->db);
        }

        $owns = !$this->db->inTransaction();

        if ($owns) {
            $this->db->beginTransaction();
        }

        try {
            $number = $this->nextValue($scope);
            if ($owns) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($owns && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return self::format($scope, $number);
    }

    /**
     * Allocate the next ID that is free in its table as well. This closes the
     * preview/allocate gap: if another request received the previewed ID
     * first, this simply moves to the following number.
     */
    public function allocateAvailable(string $scope, int $maxAttempts = 100): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $id = $this->allocate($scope);
            if (!$this->idExists($scope, $id)) {
                return $id;
            }
        }

        throw new RuntimeException('Could not allocate a unique user ID.');
    }

    public function idExists(string $scope, string $id): bool
    {
        $scope = $this->requireScope($scope);
        $target = self::TARGET_BY_SCOPE[$scope];

        if ($id === '' || !self::tableExists($this->db, $target['table'])) {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM `' . $target['table'] . '` WHERE `' . $target['column'] . '` = ? LIMIT 1'
            );
            $stmt->execute([$id]);

            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Highest numeric suffix already present in the scope's table. Existing
     * IDs are only read, never changed, so the next allocation continues
     * after the highest historical number (archived rows included).
     */
    public function existingMax(string $scope): int
    {
        $scope = $this->requireScope($scope);
        $target = self::TARGET_BY_SCOPE[$scope];
        $prefix = self::PREFIX_BY_SCOPE[$scope] . '-';

        if (!self::tableExists($this->db, $target['table']) || !self::columnExists($this->db, $target['table'], $target['column'])) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT MAX(CAST(SUBSTR(`' . $target['column'] . '`, LENGTH(?) + 1) AS UNSIGNED))'
                . ' FROM `' . $target['table'] . '`'
                . ' WHERE `' . $target['column'] . '` LIKE ?'
                . ' AND `' . $target['column'] . '` <> ?'
            );
            $stmt->execute([$prefix, $prefix . '%', '']);

            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Migration report: duplicates and formatting issues per source, plus the
     * next ID each scope would allocate. Read-only — safe to run in
     * production before deciding on any data cleanup.
     *
     * @return array{duplicates:array,format_issues:array,next:array,unique_indexes:array}
     */
    public function audit(int $sampleLimit = 10): array
    {
        $report = [
            'duplicates' => [],
            'format_issues' => [],
            'next' => [],
            'unique_indexes' => [],
        ];

        foreach (array_keys(self::TARGET_BY_SCOPE) as $scope) {
            $target = self::TARGET_BY_SCOPE[$scope];
            $key = $target['table'] . '.' . $target['column'];

            if (!self::tableExists($this->db, $target['table']) || !self::columnExists($this->db, $target['table'], $target['column'])) {
                continue;
            }

            $report['duplicates'][$key] = self::duplicateValues($this->db, $target['table'], $target['column'], $sampleLimit);

            try {
                $like = self::PREFIX_BY_SCOPE[$scope] . '-%';
                $stmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM `' . $target['table'] . '`'
                    . ' WHERE `' . $target['column'] . '` IS NOT NULL AND `' . $target['column'] . '` <> ?'
                    . ' AND (`' . $target['column'] . '` NOT LIKE ? OR `' . $target['column'] . '` NOT REGEXP ?)'
                );
                $stmt->execute(['', $like, '^[A-Z]+-[0-9]+$']);
                $report['format_issues'][$key] = (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                // REGEXP is not available on every engine; fall back below.
                try {
                    $like = self::PREFIX_BY_SCOPE[$scope] . '-%';
                    $stmt = $this->db->prepare(
                        'SELECT COUNT(*) FROM `' . $target['table'] . '`'
                        . ' WHERE `' . $target['column'] . '` IS NOT NULL AND `' . $target['column'] . '` <> ?'
                        . ' AND `' . $target['column'] . '` NOT LIKE ?'
                    );
                    $stmt->execute(['', $like]);
                    $report['format_issues'][$key] = (int)$stmt->fetchColumn();
                } catch (Throwable $ignored) {
                    $report['format_issues'][$key] = 0;
                }
            }
        }

        foreach (array_keys(self::PREFIX_BY_SCOPE) as $scope) {
            try {
                $report['next'][$scope] = $this->preview($scope);
            } catch (Throwable $e) {
                $report['next'][$scope] = '';
            }
        }

        $report['unique_indexes'] = self::ensureUniqueIndexes($this->db);

        return $report;
    }

    private function nextValue(string $scope): int
    {
        $this->ensureRow($scope);

        if (self::driverName($this->db) === 'mysql' && $this->db->inTransaction()) {
            $stmt = $this->db->prepare('SELECT last_number FROM user_id_sequences WHERE scope = ? FOR UPDATE');
            $stmt->execute([$scope]);
            $last = (int)$stmt->fetchColumn();
        } else {
            // SQLite serializes writers; an explicit row lock is unnecessary.
            $last = $this->sequenceValue($scope);
        }

        // Continue after the highest ID that already exists, even if the
        // sequence row is behind (for example after a data import).
        $last = max($last, $this->existingMax($scope));
        $next = $last + 1;

        $stmt = $this->db->prepare('UPDATE user_id_sequences SET last_number = ?, updated_at = ? WHERE scope = ?');
        $stmt->execute([$next, gmdate('Y-m-d H:i:s'), $scope]);

        return $next;
    }

    private function ensureRow(string $scope): void
    {
        try {
            $stmt = $this->db->prepare('INSERT INTO user_id_sequences (scope, last_number, updated_at) VALUES (?, 0, ?)');
            $stmt->execute([$scope, gmdate('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            // Already there (normal case after the first allocation).
        }
    }

    private function sequenceValue(string $scope): int
    {
        try {
            $stmt = $this->db->prepare('SELECT last_number FROM user_id_sequences WHERE scope = ? LIMIT 1');
            $stmt->execute([$scope]);

            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function requireScope(string $scope): string
    {
        $scope = strtolower(trim($scope));

        if (!isset(self::PREFIX_BY_SCOPE[$scope])) {
            throw new RuntimeException('Unknown user ID scope.');
        }

        return $scope;
    }

    private static function duplicateCount(PDO $db, string $table, string $column): int
    {
        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM ('
                . 'SELECT `' . $column . '` FROM `' . $table . '`'
                . ' WHERE `' . $column . '` IS NOT NULL AND `' . $column . '` <> ?'
                . ' GROUP BY `' . $column . '` HAVING COUNT(*) > 1'
                . ') AS duplicates'
            );
            $stmt->execute(['']);

            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * @return string[]
     */
    private static function duplicateValues(PDO $db, string $table, string $column, int $limit): array
    {
        try {
            $limit = max(1, min(100, $limit));
            $stmt = $db->prepare(
                'SELECT `' . $column . '` AS value FROM `' . $table . '`'
                . ' WHERE `' . $column . '` IS NOT NULL AND `' . $column . '` <> ?'
                . ' GROUP BY `' . $column . '` HAVING COUNT(*) > 1'
                . ' LIMIT ' . $limit
            );
            $stmt->execute(['']);

            return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            return [];
        }
    }

    private static function driverName(PDO $db): string
    {
        try {
            return (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $e) {
            return 'mysql';
        }
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        try {
            if (self::driverName($db) === 'sqlite') {
                $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$table]);

                return (bool)$stmt->fetchColumn();
            }

            $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));

            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function columnExists(PDO $db, string $table, string $column): bool
    {
        try {
            if (self::driverName($db) === 'sqlite') {
                $stmt = $db->query('PRAGMA table_info(`' . $table . '`)');
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (strtolower((string)($row['name'] ?? '')) === strtolower($column)) {
                        return true;
                    }
                }

                return false;
            }

            $stmt = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (strtolower((string)($row['Field'] ?? '')) === strtolower($column)) {
                    return true;
                }
            }

            return false;
        } catch (Throwable $e) {
            return false;
        }
    }
}

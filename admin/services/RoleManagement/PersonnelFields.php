<?php declare(strict_types=1);

namespace Admin\Services\RoleManagement;

use DateTime;
use DateTimeZone;
use PDO;

/**
 * Shared birth-date / hired-date support for role accounts.
 *
 * The columns are added additively to the existing account tables at runtime
 * (information_schema check + ALTER), so older databases pick them up without
 * a manual migration. Age is never stored: it is derived from the birth date.
 */
class PersonnelFields
{
    public const TABLES = ['drivers', 'inspectors', 'users'];
    public const COLUMNS = ['birth_date', 'hired_date'];

    /**
     * Typed additive columns used by the structured Add/Edit forms. Every
     * column is nullable so existing rows and legacy flows keep working.
     */
    public const TYPED_COLUMNS = [
        'drivers' => [
            'sex' => 'VARCHAR(24) NULL',
            'address' => 'TEXT NULL',
            'employment_type' => 'VARCHAR(32) NULL',
            'employment_status' => 'VARCHAR(32) NULL',
            'supervisor_id' => 'INT NULL',
            'work_shift' => 'VARCHAR(32) NULL',
            'availability' => 'VARCHAR(32) NULL',
            'created_by' => 'VARCHAR(120) NULL',
            'updated_at' => 'DATETIME NULL',
        ],
        'inspectors' => [
            'sex' => 'VARCHAR(24) NULL',
            'address' => 'TEXT NULL',
            'employment_type' => 'VARCHAR(32) NULL',
            'employment_status' => 'VARCHAR(32) NULL',
            'supervisor_id' => 'INT NULL',
            'work_shift' => 'VARCHAR(32) NULL',
            'availability' => 'VARCHAR(32) NULL',
            'assigned_area' => 'VARCHAR(120) NULL',
            'created_by' => 'VARCHAR(120) NULL',
            'updated_at' => 'DATETIME NULL',
        ],
        'users' => [
            'sex' => 'VARCHAR(24) NULL',
            'position_title' => 'VARCHAR(80) NULL',
            'assigned_office' => 'VARCHAR(120) NULL',
            'employment_type' => 'VARCHAR(32) NULL',
            'employment_status' => 'VARCHAR(32) NULL',
            'supervisor_id' => 'INT NULL',
            'work_shift' => 'VARCHAR(32) NULL',
            'created_by' => 'VARCHAR(120) NULL',
            'updated_at' => 'DATETIME NULL',
        ],
    ];

    private static bool $ensured = false;

    public static function ensureColumns(PDO $db): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        foreach (self::TABLES as $table) {
            if (!self::tableExists($db, $table)) {
                continue;
            }
            foreach (self::COLUMNS as $column) {
                if (self::hasColumn($db, $table, $column)) {
                    continue;
                }
                try {
                    $db->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` DATE NULL');
                } catch (\Throwable $e) {
                    error_log('PersonnelFields: could not add ' . $table . '.' . $column . ': ' . $e->getMessage());
                }
            }

            foreach (self::TYPED_COLUMNS[$table] ?? [] as $column => $definition) {
                if (self::hasColumn($db, $table, $column)) {
                    continue;
                }
                try {
                    $db->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
                } catch (\Throwable $e) {
                    error_log('PersonnelFields: could not add ' . $table . '.' . $column . ': ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Option lists shared by the Roles Management forms (enums, positions).
     *
     * @return array<string,array>
     */
    public static function options(): array
    {
        static $options = null;

        if ($options === null) {
            $loaded = require $_SERVER['DOCUMENT_ROOT'] . '/admin/config/role_options.php';
            $options = is_array($loaded) ? $loaded : [];
        }

        return $options;
    }

    /**
     * Value type per structured column, used when binding form input.
     */
    public const FORM_FIELD_TYPES = [
        'sex' => 'text',
        'address' => 'text',
        'employment_type' => 'text',
        'employment_status' => 'text',
        'supervisor_id' => 'int',
        'work_shift' => 'text',
        'availability' => 'text',
        'assigned_area' => 'text',
        'position_title' => 'text',
        'assigned_office' => 'text',
        'created_by' => 'text',
    ];

    /**
     * Build the extra INSERT fragments for a table's structured columns.
     *
     * Columns missing from this installation are skipped, so an old database
     * keeps working even if an ALTER was not permitted.
     *
     * @return array{0:string,1:string,2:array<string,mixed>} [columns, placeholders, params]
     */
    public static function extraInsert(PDO $db, string $table, array $data): array
    {
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach (self::TYPED_COLUMNS[$table] ?? [] as $column => $definition) {
            if ($column === 'updated_at' || !self::hasColumn($db, $table, $column)) {
                continue;
            }
            $columns[] = '`' . $column . '`';
            $placeholders[] = ':' . $column;
            $params[':' . $column] = self::normalizeValue(
                self::FORM_FIELD_TYPES[$column] ?? 'text',
                $data[$column] ?? null
            );
        }

        if (self::hasColumn($db, $table, 'updated_at')) {
            $columns[] = '`updated_at`';
            $placeholders[] = 'NOW()';
        }

        return [
            $columns ? ', ' . implode(', ', $columns) : '',
            $placeholders ? ', ' . implode(', ', $placeholders) : '',
            $params,
        ];
    }

    /**
     * Build the extra UPDATE SET fragments for supplied structured fields.
     *
     * Only fields present in $data are written, so a partial legacy form can
     * never blank a structured value. Always refreshes updated_at.
     *
     * @return array{0:string,1:array<string,mixed>} [set fragment, params]
     */
    public static function extraUpdate(PDO $db, string $table, array $data): array
    {
        $set = [];
        $params = [];

        foreach (self::TYPED_COLUMNS[$table] ?? [] as $column => $definition) {
            if ($column === 'updated_at' || $column === 'created_by') {
                continue;
            }
            if (!array_key_exists($column, $data) || !self::hasColumn($db, $table, $column)) {
                continue;
            }
            $set[] = '`' . $column . '` = :' . $column;
            $params[':' . $column] = self::normalizeValue(
                self::FORM_FIELD_TYPES[$column] ?? 'text',
                $data[$column]
            );
        }

        if (self::hasColumn($db, $table, 'updated_at')) {
            $set[] = '`updated_at` = NOW()';
        }

        return [$set ? ', ' . implode(', ', $set) : '', $params];
    }

    private static function normalizeValue(string $type, $value)
    {
        if ($type === 'int') {
            $value = trim((string)($value ?? ''));
            return ($value === '' || !ctype_digit($value)) ? null : (int)$value;
        }
        if ($type === 'date') {
            return self::normalizeDate($value);
        }

        $value = trim((string)($value ?? ''));
        return $value === '' ? null : $value;
    }

    public static function tableExists(PDO $db, string $table): bool
    {
        try {
            $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function hasColumn(PDO $db, string $table, string $column): bool
    {
        try {
            $stmt = $db->prepare('
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ');
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Accept only a real calendar date in Y-m-d form; anything else becomes null.
     */
    public static function normalizeDate($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }
        $parsed = DateTime::createFromFormat('Y-m-d', $value);
        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            return null;
        }
        return $value;
    }

    /**
     * Whole years between a birth date and today (Asia/Manila), or null.
     */
    public static function ageFrom(?string $birthDate): ?int
    {
        $birthDate = self::normalizeDate($birthDate);
        if ($birthDate === null) {
            return null;
        }
        try {
            $zone = new DateTimeZone('Asia/Manila');
            $birth = new DateTime($birthDate, $zone);
            $today = new DateTime('now', $zone);
            if ($birth > $today) {
                return null;
            }
            return $birth->diff($today)->y;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

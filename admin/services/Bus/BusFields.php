<?php declare(strict_types=1);

namespace Admin\Services\Bus;

use DateTime;
use PDO;

/**
 * Additive bus columns for the redesigned Bus Management module.
 *
 * The columns are added to the legacy `buses` table at runtime
 * (information_schema check + ALTER), so older databases pick them up
 * without a manual migration. Every column is nullable so existing rows and
 * legacy writers keep working unchanged.
 */
class BusFields
{
    public const TABLE = 'buses';

    /**
     * Typed additive columns. Registration/insurance/inspection dates store
     * real calendar dates; everything else is free text or an integer
     * reference.
     */
    public const TYPED_COLUMNS = [
        'unit_number' => 'VARCHAR(32) NULL',
        'manufacturer' => 'VARCHAR(80) NULL',
        'model' => 'VARCHAR(80) NULL',
        'year_model' => 'VARCHAR(8) NULL',
        'engine_number' => 'VARCHAR(64) NULL',
        'chassis_number' => 'VARCHAR(64) NULL',
        'fuel_type' => 'VARCHAR(32) NULL',
        'transmission_type' => 'VARCHAR(32) NULL',
        'air_conditioning' => 'VARCHAR(32) NULL',
        'vehicle_ownership' => 'VARCHAR(32) NULL',
        'assigned_inspector_id' => 'INT NULL',
        'operational_status' => 'VARCHAR(32) NULL',
        'registration_month' => 'VARCHAR(16) NULL',
        'registration_expiry' => 'DATE NULL',
        'insurance_expiry' => 'DATE NULL',
        'inspection_date' => 'DATE NULL',
        'next_inspection_date' => 'DATE NULL',
        'created_by' => 'VARCHAR(120) NULL',
        'updated_at' => 'DATETIME NULL',
    ];

    /**
     * Base bus columns and their binding types, used by the partial writer.
     * These columns exist on the canonical schema; tolerate installations
     * where a legacy column is missing.
     */
    public const BASE_COLUMNS = [
        'plate_number' => 'text',
        'bus_type' => 'text',
        'seats' => 'int',
        'standing_capacity' => 'int',
        'accessible_features' => 'text',
        'driver' => 'text',
        'from_terminal_id' => 'int',
        'to_terminal_id' => 'int',
        'route_name' => 'text',
        'terminal' => 'text',
        'schedule' => 'text',
        'status' => 'text',
        'qr_code' => 'text',
    ];

    /**
     * Binding type per column, used by the insert/update fragment builders.
     */
    public const FORM_FIELD_TYPES = [
        'unit_number' => 'text',
        'manufacturer' => 'text',
        'model' => 'text',
        'year_model' => 'text',
        'engine_number' => 'text',
        'chassis_number' => 'text',
        'fuel_type' => 'text',
        'transmission_type' => 'text',
        'air_conditioning' => 'text',
        'vehicle_ownership' => 'text',
        'assigned_inspector_id' => 'int',
        'operational_status' => 'text',
        'registration_month' => 'text',
        'registration_expiry' => 'date',
        'insurance_expiry' => 'date',
        'inspection_date' => 'date',
        'next_inspection_date' => 'date',
        'created_by' => 'text',
    ];

    private static bool $ensured = false;

    public static function ensureColumns(PDO $db): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        if (!self::tableExists($db, self::TABLE)) {
            return;
        }

        foreach (self::TYPED_COLUMNS as $column => $definition) {
            if (self::hasColumn($db, self::TABLE, $column)) {
                continue;
            }
            try {
                $db->exec('ALTER TABLE `' . self::TABLE . '` ADD COLUMN `' . $column . '` ' . $definition);
            } catch (\Throwable $e) {
                error_log('BusFields: could not add ' . self::TABLE . '.' . $column . ': ' . $e->getMessage());
            }
        }
    }

    public static function tableExists(PDO $db, string $table): bool
    {
        try {
            if (self::driverName($db) === 'sqlite') {
                $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$table]);

                return (bool)$stmt->fetchColumn();
            }

            $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));

            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function hasColumn(PDO $db, string $table, string $column): bool
    {
        try {
            if (self::driverName($db) === 'sqlite') {
                foreach ($db->query('PRAGMA table_info(`' . str_replace('`', '', $table) . '`)')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (strtolower((string)($row['name'] ?? '')) === strtolower($column)) {
                        return true;
                    }
                }

                return false;
            }

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

    private static function driverName(PDO $db): string
    {
        try {
            return (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            return 'mysql';
        }
    }

    /**
     * Build the extra INSERT fragments for the additive bus columns.
     *
     * Columns missing from this installation are skipped, so an old database
     * keeps working even if an ALTER was not permitted.
     *
     * @return array{0:string,1:string,2:array<string,mixed>} [columns, placeholders, params]
     */
    public static function extraInsert(PDO $db, array $data): array
    {
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach (self::TYPED_COLUMNS as $column => $definition) {
            if ($column === 'updated_at' || !self::hasColumn($db, self::TABLE, $column)) {
                continue;
            }
            $columns[] = '`' . $column . '`';
            $placeholders[] = ':' . $column;
            $params[':' . $column] = self::normalizeValue(
                self::FORM_FIELD_TYPES[$column] ?? 'text',
                $data[$column] ?? null
            );
        }

        if (self::hasColumn($db, self::TABLE, 'updated_at')) {
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
     * Build the extra UPDATE SET fragments for supplied additive fields.
     *
     * Only fields present in $data are written, so a partial legacy form can
     * never blank a value. Always refreshes updated_at.
     *
     * @return array{0:string,1:array<string,mixed>} [set fragment, params]
     */
    public static function extraUpdate(PDO $db, array $data): array
    {
        $set = [];
        $params = [];

        foreach (self::TYPED_COLUMNS as $column => $definition) {
            if ($column === 'updated_at' || $column === 'created_by') {
                continue;
            }
            if (!array_key_exists($column, $data) || !self::hasColumn($db, self::TABLE, $column)) {
                continue;
            }
            $set[] = '`' . $column . '` = :' . $column;
            $params[':' . $column] = self::normalizeValue(
                self::FORM_FIELD_TYPES[$column] ?? 'text',
                $data[$column]
            );
        }

        if (self::hasColumn($db, self::TABLE, 'updated_at')) {
            $set[] = '`updated_at` = NOW()';
        }

        return [$set ? ', ' . implode(', ', $set) : '', $params];
    }

    /**
     * Schema-tolerant partial UPDATE that writes only the columns supplied and
     * only when they exist on this installation, so a caller applying a
     * subset of fields can never blank the fields it did not supply.
     *
     * @param array<string,mixed> $data column => value
     * @return bool whether an UPDATE was executed
     */
    public static function updateProvided(PDO $db, string $keyColumn, $keyValue, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $set = [];
        $params = [];

        foreach (array_merge(self::BASE_COLUMNS, self::FORM_FIELD_TYPES) as $column => $type) {
            if ($column === 'created_by' || !array_key_exists($column, $data)) {
                continue;
            }
            if (!self::hasColumn($db, self::TABLE, $column)) {
                continue;
            }
            $set[] = '`' . $column . '` = ?';
            $params[] = self::normalizeValue($type, $data[$column]);
        }

        if ($set === []) {
            return false;
        }

        if (self::hasColumn($db, self::TABLE, 'updated_at')) {
            $set[] = '`updated_at` = NOW()';
        }
        $params[] = $keyValue;

        $stmt = $db->prepare(
            'UPDATE `' . self::TABLE . '` SET ' . implode(', ', $set)
            . ' WHERE `' . str_replace('`', '', $keyColumn) . '` = ?'
        );

        return $stmt->execute($params);
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
}

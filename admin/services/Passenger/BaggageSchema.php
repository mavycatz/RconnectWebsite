<?php declare(strict_types=1);

namespace Admin\Services\Passenger;

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;
use PDO;

/**
 * Additive baggage category support over the existing storage.
 *
 * Baggage lives in passengers (live) and passenger_trip_history (archived),
 * discriminated by type='other'. The category column is added lazily so older
 * databases keep working; existing rows simply show "not categorized".
 */
class BaggageSchema
{
    public const CATEGORIES = ['Box', 'Carton', 'Luggage', 'Sack', 'Bundle', 'Plastic', 'Other'];

    private static bool $ensured = false;

    public static function ensureColumns(?PDO $db = null): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        if ($db === null) {
            try {
                $db = Database::get();
            } catch (\Throwable $e) {
                return;
            }
        }

        foreach (['passengers', 'passenger_trip_history'] as $table) {
            if (!self::tableExists($db, $table) || self::hasColumn($db, $table, 'baggage_category')) {
                continue;
            }
            try {
                $db->exec("ALTER TABLE `{$table}` ADD COLUMN `baggage_category` VARCHAR(50) NULL");
            } catch (\Throwable $e) {
                error_log('BaggageSchema: could not add ' . $table . '.baggage_category: ' . $e->getMessage());
            }
        }
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
                SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ');
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function normalizeCategory(?string $category): ?string
    {
        $category = trim((string)$category);
        if ($category === '') {
            return null;
        }
        foreach (self::CATEGORIES as $allowed) {
            if (strcasecmp($category, $allowed) === 0) {
                return $allowed;
            }
        }
        return null;
    }
}

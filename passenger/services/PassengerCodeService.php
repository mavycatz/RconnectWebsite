<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/PassengerAccountSchema.php';

use App\Core\Database;

/**
 * Centralized prefix passenger-ID generation.
 *
 * Codes are PREFIX-NNN, sequential per prefix and zero-padded to at least three
 * digits. Generation happens on the server for every creation path; existing
 * codes are never renumbered and only empty/NULL codes are backfilled.
 *
 * The QR JSON contract is unchanged: it carries whatever value is stored in
 * passengers.passenger_code.
 */
class PassengerCodeService
{
    /** Type label (normalized) => prefix. */
    private const PREFIXES = [
        'regular' => 'STD',
        'standard' => 'STD',
        'student' => 'STU',
        'senior' => 'SEN',
        'seniorcitizen' => 'SEN',
        'pwd' => 'PWD',
        'personwithdisability' => 'PWD',
        'teacher' => 'TCH',
        'nurse' => 'NUR',
        'voucher' => 'VCH',
        'promo' => 'PRO',
        'cooperative' => 'COO',
        'coop' => 'COO',
        'rpmvfmpcmember' => 'MEM',
        'member' => 'MEM',
        'special' => 'SPC',
        'other' => 'OTH',
    ];

    private const PAD = 3;
    private const MAX_ATTEMPTS = 25;

    /**
     * Resolve the prefix for a stored passenger type value.
     */
    public static function prefixForType(?string $type, ?string $registrationType = null): string
    {
        $candidate = self::normalize($type);
        if ($candidate === '' && $registrationType !== null) {
            $candidate = self::normalize($registrationType);
        }
        return self::PREFIXES[$candidate] ?? 'STD';
    }

    private static function normalize(?string $value): string
    {
        $value = strtolower(trim((string)$value));
        $value = preg_replace('/[^a-z]/', '', $value) ?? '';
        return $value;
    }

    /**
     * Assign a code to a passenger that does not have one yet.
     * Returns the stored code (existing or newly assigned), or null when the
     * passenger row cannot be found.
     */
    public static function assignIfMissing(PDO $db, int $passengerId, ?string $type, ?string $registrationType = null): ?string
    {
        $existing = self::currentCode($db, $passengerId);
        if ($existing === null) {
            return null; // row missing
        }
        if ($existing !== '') {
            return $existing;
        }
        if ($type === null) {
            $type = self::storedType($db, $passengerId);
        }
        return self::generate($db, $passengerId, $type, $registrationType);
    }

    /**
     * Best-effort read of the stored type value for prefix selection.
     */
    private static function storedType(PDO $db, int $passengerId): string
    {
        $column = self::hasColumn($db, 'passengers', 'discount_type')
            ? 'discount_type'
            : (self::hasColumn($db, 'passengers', 'type') ? 'type' : null);
        if ($column === null) {
            return '';
        }
        try {
            $stmt = $db->prepare("SELECT {$column} FROM passengers WHERE id = ? LIMIT 1");
            $stmt->execute([$passengerId]);
            return (string)($stmt->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Generate and store a code for a passenger whose code is empty.
     * Safe to call repeatedly; returns the effective stored code.
     */
    public static function generate(PDO $db, int $passengerId, ?string $type, ?string $registrationType = null): string
    {
        return self::allocate($db, $passengerId, false);
    }

    public static function effectiveType(array $row): string
    {
        $type = trim((string)($row['discount_type'] ?? $row['type'] ?? 'Regular'));
        if ($type === '') $type = (string)($row['type'] ?? 'Regular');
        if (in_array(self::normalize($type), ['student', 'senior', 'seniorcitizen', 'pwd', 'personwithdisability'], true)
            && !in_array(strtolower((string)($row['discount_status'] ?? '')), ['verified', 'approved'], true)) return 'Regular';
        return $type ?: 'Regular';
    }

    public static function reclassify(PDO $db, int $id): string { return self::allocate($db, $id, true); }

    private static function allocate(PDO $db, int $id, bool $change): string
    {
        PassengerAccountSchema::ensure($db);
        $own = !$db->inTransaction();
        if ($own) $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT * FROM passengers WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Passenger account not found.');
            $old = trim((string)($row['passenger_code'] ?? ''));
            $type = self::effectiveType($row);
            $prefix = self::prefixForType($type);
            if ($old !== '') {
                $currentPrefix = strtoupper((string) strstr($old, '-', true));
                // Keep an existing code when nothing changed. When the caller
                // explicitly reclassifies, also reissue if the stored prefix no
                // longer matches the target classification's prefix (for
                // example a legacy student STD- code under the new STU scheme).
                if (!$change || ($currentPrefix === $prefix && ($row['type'] ?? '') === $type)) {
                    if ($own) $db->commit();
                    return $old;
                }
            }
            // Reserve legacy codes permanently before changing the display ID.
            if ($old !== '') {
                $db->prepare('INSERT IGNORE INTO passenger_code_history (passenger_code, passenger_id, classification) VALUES (?, ?, ?)')->execute([$old, $id, $row['type'] ?? 'Regular']);
                $db->prepare('UPDATE passenger_code_history SET retired_at = COALESCE(retired_at, NOW()) WHERE passenger_code = ? AND passenger_id = ?')->execute([$old, $id]);
            }
            $db->prepare('INSERT IGNORE INTO passenger_id_sequences (prefix, last_number) VALUES (?, 0)')->execute([$prefix]);
            $lock = $db->prepare('SELECT last_number FROM passenger_id_sequences WHERE prefix = ? FOR UPDATE');
            $lock->execute([$prefix]); $last = (int)$lock->fetchColumn();
            // Both live and retired codes count, including legacy STD student IDs.
            $max = $db->prepare("SELECT MAX(n) FROM (SELECT CAST(SUBSTRING_INDEX(passenger_code, '-', -1) AS UNSIGNED) n FROM passengers WHERE passenger_code LIKE ? UNION ALL SELECT CAST(SUBSTRING_INDEX(passenger_code, '-', -1) AS UNSIGNED) n FROM passenger_code_history WHERE passenger_code LIKE ?) used_codes");
            $max->execute([$prefix . '-%', $prefix . '-%']);
            $number = max($last, (int)$max->fetchColumn()) + 1;
            $code = $prefix . '-' . str_pad((string)$number, self::PAD, '0', STR_PAD_LEFT);
            $db->prepare('INSERT INTO passenger_code_history (passenger_code, passenger_id, classification) VALUES (?, ?, ?)')->execute([$code, $id, $type]);
            $db->prepare('UPDATE passenger_id_sequences SET last_number = ? WHERE prefix = ?')->execute([$number, $prefix]);
            $fields = 'passenger_code = ?'; $values = [$code];
            if (self::hasColumn($db, 'passengers', 'type')) { $fields .= ', type = ?'; $values[] = $type; }
            $values[] = $id;
            $db->prepare("UPDATE passengers SET $fields WHERE id = ?")->execute($values);
            if ($own) $db->commit();
            return $code;
        } catch (Throwable $e) {
            if ($own && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private static function currentCode(PDO $db, int $passengerId): ?string
    {
        $stmt = $db->prepare('SELECT passenger_code FROM passengers WHERE id = ? LIMIT 1');
        $stmt->execute([$passengerId]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return null;
        }
        return trim((string)$value);
    }

    /**
     * Highest used number for a prefix plus the attempt offset.
     */
    private static function nextNumber(PDO $db, string $prefix, int $offset): int
    {
        $stmt = $db->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(passenger_code, '-', -1) AS UNSIGNED))
             FROM passengers
             WHERE passenger_code LIKE ?"
        );
        $stmt->execute([$prefix . '-%']);
        $max = (int)$stmt->fetchColumn();
        return $max + 1 + $offset;
    }

    /**
     * Backfill empty/NULL codes in small batches. Existing codes are untouched.
     * Returns the number of passengers that received a code.
     */
    public static function backfillMissing(PDO $db, int $limit = 200): int
    {
        $typeExpr = self::typeExpression($db);

        try {
            $stmt = $db->prepare(
                "SELECT id, {$typeExpr} AS ptype
                 FROM passengers
                 WHERE passenger_code IS NULL OR passenger_code = ''
                 ORDER BY id ASC
                 LIMIT " . max(1, $limit)
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return 0;
        }

        $assigned = 0;
        foreach ($rows as $row) {
            try {
                self::generate($db, (int)$row['id'], (string)($row['ptype'] ?? ''));
                $assigned++;
            } catch (\Throwable $e) {
                // Keep backfilling the rest.
            }
        }
        return $assigned;
    }

    private static function typeExpression(PDO $db): string
    {
        if (self::hasColumn($db, 'passengers', 'discount_type')) {
            return "COALESCE(NULLIF(discount_type, ''), " . (self::hasColumn($db, 'passengers', 'type') ? "COALESCE(type, '')" : "''") . ")";
        }
        if (self::hasColumn($db, 'passengers', 'type')) {
            return "COALESCE(type, '')";
        }
        return "''";
    }

    /**
     * Add a unique key on passenger_code when the data allows it.
     * Returns true when a unique index exists afterwards.
     */
    public static function ensureUniqueIndex(PDO $db): bool
    {
        try {
            $stmt = $db->query("SHOW INDEX FROM passengers WHERE Column_name = 'passenger_code'");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $index) {
                if ((int)($index['Non_unique'] ?? 1) === 0) {
                    return true;
                }
            }

            $dup = $db->query(
                "SELECT passenger_code FROM passengers
                 WHERE passenger_code IS NOT NULL AND passenger_code <> ''
                 GROUP BY passenger_code HAVING COUNT(*) > 1 LIMIT 1"
            )->fetchColumn();
            if ($dup !== false) {
                return false;
            }

            $db->exec("ALTER TABLE passengers ADD UNIQUE KEY uniq_passenger_code (passenger_code)");
            return true;
        } catch (\Throwable $e) {
            error_log('PassengerCodeService: unique index not applied: ' . $e->getMessage());
            return false;
        }
    }

    public static function hasColumn(PDO $db, string $table, string $column): bool
    {
        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

<?php declare(strict_types=1);

namespace Admin\Services\Bus;

use PDO;
use PDOException;
use Throwable;

/**
 * Plate-number duplicate detection for the Bus Management forms.
 *
 * Plates are compared after normalizing away case, spaces, and punctuation,
 * so "ABC 1234", "abc-1234", and "ABC1234" are treated as the same plate.
 * The bus being edited is always excluded from its own check.
 */
final class BusDuplicates
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Uppercase alphanumeric form of a plate number.
     */
    public static function normalizePlate(string $plate): string
    {
        return strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $plate));
    }

    /**
     * Collation-safe column expression: explicit on MySQL (prevents error
     * 1267 against legacy collations), plain on SQLite.
     */
    private function wrap(string $expr): string
    {
        try {
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $e) {
            $driver = 'mysql';
        }

        return $driver === 'sqlite'
            ? $expr
            : 'CONVERT(' . $expr . ' USING utf8mb4) COLLATE utf8mb4_general_ci';
    }

    /**
     * Existing buses whose plate matches the given one.
     *
     * @return array<int,array{ref:string,bus_id:string,plate:string,status:string}>
     */
    public function find(string $plate, ?int $excludeRef = null, int $limit = 5): array
    {
        $normalized = self::normalizePlate($plate);
        if (strlen($normalized) < 3) {
            return [];
        }

        // Two fragments (head and tail) so stored plates with separators
        // ("ABC 1234") still match, with exact normalized comparison below.
        $head = substr($normalized, 0, 2);
        $tail = strlen($normalized) >= 4 ? substr($normalized, -2) : '';
        $limit = max(1, min(20, $limit));

        try {
            $sql = 'SELECT id, bus_id, plate_number, status FROM buses'
                . ' WHERE plate_number IS NOT NULL AND LENGTH(plate_number) > 0'
                . ' AND ' . $this->wrap('plate_number') . ' LIKE ?';
            $params = ['%' . $head . '%'];
            if ($tail !== '' && $tail !== $head) {
                $sql .= ' AND plate_number LIKE ?';
                $params[] = '%' . $tail . '%';
            }
            if ($excludeRef !== null && $excludeRef > 0) {
                $sql .= ' AND id <> ?';
                $params[] = $excludeRef;
            }
            $sql .= ' LIMIT 50';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        } catch (Throwable $e) {
            return [];
        }

        $matches = [];
        foreach ($rows as $row) {
            if (self::normalizePlate((string)($row['plate_number'] ?? '')) !== $normalized) {
                continue;
            }
            $matches[] = [
                'ref' => (string)($row['id'] ?? ''),
                'bus_id' => trim((string)($row['bus_id'] ?? '')),
                'plate' => trim((string)($row['plate_number'] ?? '')),
                'status' => trim((string)($row['status'] ?? '')),
            ];
            if (count($matches) >= $limit) {
                break;
            }
        }

        return $matches;
    }
}

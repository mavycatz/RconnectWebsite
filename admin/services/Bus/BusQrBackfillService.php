<?php declare(strict_types=1);

namespace Admin\Services\Bus;

use PDO;
use Throwable;

/**
 * Bulk QR preparation for existing buses.
 *
 * Detects registered buses without an active QR credential and creates the
 * missing ones. Valid credentials are preserved, repeats are safe, and no
 * duplicate credential is ever created.
 */
final class BusQrBackfillService
{
    private PDO $db;
    private BusQrService $qr;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->qr = new BusQrService($db);
    }

    private function driverName(): string
    {
        try {
            return (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $e) {
            return 'mysql';
        }
    }

    private function wrap(string $expr): string
    {
        return $this->driverName() === 'sqlite'
            ? $expr
            : 'CONVERT(' . $expr . ' USING utf8mb4) COLLATE utf8mb4_general_ci';
    }

    /**
     * Buses with no active QR credential.
     *
     * @return array<int,array{ref:int,bus_id:string,plate:string,unit_number:string}>
     */
    public function missing(): array
    {
        BusQrService::ensureSchema($this->db);

        $sql = 'SELECT b.id AS ref, b.bus_id AS bus_id,'
            . ' COALESCE(b.plate_number, \'\') AS plate,'
            . ' ' . ($this->hasColumn('buses', 'unit_number') ? 'COALESCE(b.unit_number, \'\')' : '\'\'') . ' AS unit_number'
            . ' FROM buses b'
            . ' LEFT JOIN ' . BusQrService::TABLE . ' c'
            . ' ON c.bus_ref = b.id AND c.status = ' . $this->db->quote(BusQrService::STATUS_ACTIVE)
            . ' WHERE b.bus_id IS NOT NULL AND LENGTH(b.bus_id) > 0'
            . ' AND c.credential_id IS NULL'
            . ' ORDER BY b.bus_id ASC';

        try {
            $stmt = $this->db->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            return [];
        }

        $missing = [];
        foreach ($rows as $row) {
            $missing[] = [
                'ref' => (int)($row['ref'] ?? 0),
                'bus_id' => (string)($row['bus_id'] ?? ''),
                'plate' => (string)($row['plate'] ?? ''),
                'unit_number' => (string)($row['unit_number'] ?? ''),
            ];
        }

        return $missing;
    }

    /**
     * Create credentials for every bus missing one.
     *
     * @param bool $dryRun when true only reports what would be created
     * @return array{created:int,missing_before:int,missing_after:int,buses:array<int,array<string,mixed>>}
     */
    public function run(string $origin, ?string $actor, bool $dryRun = false): array
    {
        $missing = $this->missing();
        $created = [];
        $refs = [];
        foreach ($missing as $bus) {
            $refs[] = $bus['ref'];
        }

        if ($dryRun) {
            return [
                'created' => 0,
                'missing_before' => count($missing),
                'missing_after' => count($missing),
                'buses' => $missing,
            ];
        }

        foreach ($missing as $bus) {
            if ($bus['ref'] <= 0 || $bus['bus_id'] === '') {
                continue;
            }
            try {
                $credential = $this->qr->ensureForBus($bus['ref'], $bus['bus_id'], $origin, $actor);
                $created[] = [
                    'bus_id' => $bus['bus_id'],
                    'plate' => $bus['plate'],
                    'unit_number' => $bus['unit_number'],
                    'token' => $credential['token'] ?? '',
                    'payload_url' => $credential['payload_url'] ?? '',
                    'created_at' => $credential['created_at'] ?? '',
                ];
            } catch (Throwable $e) {
                error_log('BusQrBackfill: failed for ' . $bus['bus_id'] . ': ' . $e->getMessage());
            }
        }

        return [
            'created' => count($created),
            'missing_before' => count($missing),
            'missing_after' => count($this->missing()),
            'buses' => $created,
        ];
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            if ($this->driverName() === 'sqlite') {
                foreach ($this->db->query('PRAGMA table_info(`' . $table . '`)')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (strtolower((string)($row['name'] ?? '')) === strtolower($column)) {
                        return true;
                    }
                }

                return false;
            }

            foreach ($this->db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (strtolower((string)($row['Field'] ?? '')) === strtolower($column)) {
                    return true;
                }
            }
        } catch (Throwable $e) {
        }

        return false;
    }
}

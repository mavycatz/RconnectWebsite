<?php declare(strict_types=1);

namespace Admin\Models\Passenger;

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Passenger/BaggageSchema.php';

use Admin\Services\Passenger\BaggageSchema;
use App\Core\Database;
use PDO;

/**
 * Baggage read model over the existing cargo storage.
 *
 * Live cargo:    passengers.type='other' joined to its passenger_trips row.
 * Archived cargo: passenger_trip_history rows with type='Other'.
 * The baggage ID is derived and stable for each source row.
 */
class BaggageModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    /**
     * @return array{rows:array,total:int,errors:array}
     */
    public function query(array $filters, int $page = 1, int $perPage = 15): array
    {
        $rows = [];
        $errors = [];

        try {
            foreach ($this->liveRows($filters) as $row) {
                $rows[] = $this->normalize($row, 'live');
            }
        } catch (\Throwable $e) {
            $errors['live'] = 'Live baggage records could not be loaded from this database.';
            error_log('BaggageModel live: ' . $e->getMessage());
        }

        try {
            foreach ($this->historyRows($filters) as $row) {
                $rows[] = $this->normalize($row, 'history');
            }
        } catch (\Throwable $e) {
            $errors['history'] = 'Archived baggage records could not be loaded from this database.';
            error_log('BaggageModel history: ' . $e->getMessage());
        }

        usort($rows, static fn ($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));

        $total = count($rows);
        $perPage = max(1, $perPage);
        $page = max(1, min($page, max(1, (int)ceil($total / $perPage))));

        return [
            'rows' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'errors' => $errors,
        ];
    }

    public function find(string $source, int $ref): ?array
    {
        $filters = ['ref' => $ref];
        $rows = $source === 'history' ? $this->historyRows($filters) : $this->liveRows($filters);
        foreach ($rows as $row) {
            return $this->normalize($row, $source === 'history' ? 'history' : 'live');
        }
        return null;
    }

    private function buildFilters(array $filters, array $columnMap): array
    {
        $where = [];
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $parts = [];
            foreach ($columnMap['search'] as $expr) {
                $parts[] = $expr . ' LIKE ?';
                $params[] = $like;
            }
            if ($parts) {
                $where[] = '(' . implode(' OR ', $parts) . ')';
            }
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $where[] = $columnMap['date'] . ' >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $where[] = $columnMap['date'] . ' <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $trip = trim((string)($filters['trip'] ?? ''));
        if ($trip !== '') {
            $where[] = $columnMap['trip'] . ' = ?';
            $params[] = $trip;
        }

        $payment = trim((string)($filters['payment_status'] ?? ''));
        if ($payment !== '') {
            $where[] = 'LOWER(COALESCE(' . $columnMap['payment'] . ", '')) = LOWER(?)";
            $params[] = $payment;
        }

        return [$where, $params];
    }

    private function applyCategoryFilter(array &$where, array &$params, string $column, string $category): void
    {
        $category = trim($category);
        if ($category === '') {
            return;
        }
        if (strcasecmp($category, 'Uncategorized') === 0) {
            $where[] = '(' . $column . ' IS NULL OR ' . $column . " = '')";
            return;
        }
        $where[] = $column . ' = ?';
        $params[] = $category;
    }

    private function liveRows(array $filters): array
    {
        if (!BaggageSchema::tableExists($this->db, 'passengers') || !BaggageSchema::tableExists($this->db, 'passenger_trips')) {
            throw new \RuntimeException('baggage tables missing');
        }

        $hasCategory = BaggageSchema::hasColumn($this->db, 'passengers', 'baggage_category');
        $hasCode = BaggageSchema::hasColumn($this->db, 'passengers', 'passenger_code');

        [$where, $params] = $this->buildFilters($filters, [
            'search' => array_values(array_filter([
                'p.full_name',
                'pt.trip_no',
                'pt.bus_no',
                'pt.boarding_point',
                'pt.destination',
                $hasCode ? 'p.passenger_code' : null,
            ])),
            'date' => 'pt.boarding_time',
            'trip' => 'pt.trip_no',
            'payment' => 'pt.payment_status',
        ]);
        $where[] = "LOWER(COALESCE(p.type,'')) = 'other'";

        if ($hasCategory) {
            $this->applyCategoryFilter($where, $params, 'p.baggage_category', (string)($filters['category'] ?? ''));
        }

        if (!empty($filters['ref'])) {
            $where[] = 'pt.id = ?';
            $params[] = (int)$filters['ref'];
        }

        $sql = 'SELECT pt.id AS ref, pt.passenger_id, pt.trip_no, pt.bus_no, pt.driver_id,
                       pt.boarding_point, pt.destination, pt.fare, pt.payment_method, pt.payment_status,
                       pt.remittance_status, pt.boarding_time, pt.dropoff_time, pt.archived_status,
                       p.full_name AS description, p.space_occupied,
                       ' . ($hasCategory ? 'p.baggage_category' : 'NULL') . ' AS baggage_category,
                       ' . ($hasCode ? 'p.passenger_code' : 'NULL') . ' AS passenger_code,
                       p.created_at,
                       d.full_name AS driver_name
                FROM passengers p
                INNER JOIN passenger_trips pt ON pt.passenger_id = p.id
                LEFT JOIN drivers d ON d.driver_id = pt.driver_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY pt.boarding_time DESC
                LIMIT 5000';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function historyRows(array $filters): array
    {
        if (!BaggageSchema::tableExists($this->db, 'passenger_trip_history')) {
            throw new \RuntimeException('history table missing');
        }

        $hasCategory = BaggageSchema::hasColumn($this->db, 'passenger_trip_history', 'baggage_category');
        $hasDriverName = BaggageSchema::hasColumn($this->db, 'passenger_trip_history', 'drivers_full_name');

        [$where, $params] = $this->buildFilters($filters, [
            'search' => array_values(array_filter([
                'pth.passenger_name',
                'pth.trip_id',
                'pth.bus_number',
                'pth.boarding_point',
                'pth.destination',
                $hasDriverName ? 'pth.drivers_full_name' : null,
            ])),
            'date' => 'pth.trip_date',
            'trip' => 'pth.trip_id',
            'payment' => 'pth.payment_status',
        ]);
        $where[] = "LOWER(COALESCE(pth.type,'')) = 'other'";

        if ($hasCategory) {
            $this->applyCategoryFilter($where, $params, 'pth.baggage_category', (string)($filters['category'] ?? ''));
        }

        if (!empty($filters['ref'])) {
            $where[] = 'pth.history_id = ?';
            $params[] = (int)$filters['ref'];
        }

        $sql = 'SELECT pth.history_id AS ref, pth.passenger_name AS description, pth.space_occupied,
                       pth.fare, pth.payment_method, pth.payment_status, pth.remittance_status,
                       pth.boarding_point, pth.destination, pth.trip_id AS trip_no, pth.bus_number AS bus_no,
                       pth.driver_id, pth.driver_id AS passenger_id,
                       pth.trip_date AS boarding_time, pth.date_booked AS created_at,
                       ' . ($hasCategory ? 'pth.baggage_category' : 'NULL') . ' AS baggage_category,
                       NULL AS passenger_code,
                       ' . ($hasDriverName ? 'pth.drivers_full_name' : 'NULL') . ' AS driver_name
                FROM passenger_trip_history pth
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY pth.trip_date DESC
                LIMIT 5000';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fallback driver name when the history table has no cached name.
        if (!$hasDriverName && $rows) {
            try {
                $names = $this->db->query('SELECT driver_id, MAX(full_name) AS full_name FROM drivers GROUP BY driver_id')
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
                foreach ($rows as &$row) {
                    $row['driver_name'] = $names[(string)$row['driver_id']] ?? null;
                }
                unset($row);
            } catch (\Throwable $e) {
            }
        }

        return $rows;
    }

    private function normalize(array $row, string $source): array
    {
        $spaces = max(1, (int)($row['space_occupied'] ?? 1));
        $fare = (float)($row['fare'] ?? 0);
        $base = null;
        if ($spaces > 0 && abs(($fare / $spaces) * $spaces - $fare) < 0.011) {
            $base = round($fare / $spaces, 2);
        }

        return [
            'source' => $source,
            'baggage_id' => $source === 'history' ? 'BAG-H' . $row['ref'] : 'BAG-' . $row['ref'],
            'ref' => (string)$row['ref'],
            'description' => trim((string)($row['description'] ?? '')) !== '' ? (string)$row['description'] : 'Unnamed item',
            'category' => trim((string)($row['baggage_category'] ?? '')),
            'spaces' => $spaces,
            'multiplier' => $spaces,
            'base_fare' => $base,
            'fare' => round($fare, 2),
            'passenger_id' => (string)($row['passenger_id'] ?? ''),
            'passenger_code' => (string)($row['passenger_code'] ?? ''),
            'trip_no' => (string)($row['trip_no'] ?? ''),
            'bus' => (string)($row['bus_no'] ?? ''),
            'driver' => trim((string)($row['driver_name'] ?? '')),
            'boarding_point' => (string)($row['boarding_point'] ?? ''),
            'destination' => (string)($row['destination'] ?? ''),
            'payment_method' => (string)($row['payment_method'] ?? ''),
            'payment_status' => (string)($row['payment_status'] ?? ''),
            'remittance_status' => (string)($row['remittance_status'] ?? ''),
            'created_at' => (string)($row['boarding_time'] ?? $row['created_at'] ?? ''),
            'archived' => $source === 'history' || strtolower((string)($row['archived_status'] ?? '')) === 'yes',
        ];
    }
}

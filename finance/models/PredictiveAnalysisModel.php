<?php

declare(strict_types=1);

namespace Finance\Models;

require_once __DIR__ . '/../../app/Core/Database.php';

use PDO;
use App\Core\Database;

/**
 * Read-only data access for the Finance Predictive Analysis page.
 *
 * Every query is scoped to the columns/tables this deployment actually has:
 * optional tables (passenger_trip_history, fuel_refuels) and optional columns
 * (payment_status, departure_time, date_booked) are feature-detected with
 * SHOW TABLES / SHOW COLUMNS so a schema variation degrades a section instead
 * of breaking the page.
 *
 * Passenger demand is aggregated from live passenger_trips and archived
 * passenger_trip_history rows using the same union/identifier strategy as
 * admin/api/dashboard/peak-travel-time.php. Completed-trip counts, route
 * attribution and total_passengers come from bus_trips, the same source the
 * existing finance analytics pages use (FleetPerformanceService).
 */
class PredictiveAnalysisModel
{
    private ?PDO $db;

    /** @var array<string, bool> */
    private array $tableCache = [];

    /** @var array<string, array<string, bool>> */
    private array $columnCache = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::tryGet();
    }

    public function isAvailable(): bool
    {
        return $this->db !== null;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        $exists = false;
        if ($this->db !== null) {
            try {
                $stmt = $this->db->query('SHOW TABLES LIKE ' . $this->db->quote($table));
                $exists = $stmt !== false && $stmt->fetchColumn() !== false;
            } catch (\Throwable $e) {
                $exists = false;
            }
        }

        $this->tableCache[$table] = $exists;
        return $exists;
    }

    /** @return array<string, bool> */
    private function columns(string $table): array
    {
        if (array_key_exists($table, $this->columnCache)) {
            return $this->columnCache[$table];
        }

        $columns = [];
        if ($this->db !== null && $this->tableExists($table)) {
            try {
                $stmt = $this->db->query('SHOW COLUMNS FROM ' . $table);
                if ($stmt !== false) {
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $columns[strtolower((string) ($row['Field'] ?? ''))] = true;
                    }
                }
            } catch (\Throwable $e) {
                $columns = [];
            }
        }

        $this->columnCache[$table] = $columns;
        return $columns;
    }

    private function has(string $table, string $column): bool
    {
        return isset($this->columns($table)[strtolower($column)]);
    }

    /**
     * Distinct routes and buses that actually appear in stored data.
     *
     * @return array{routes: list<string>, buses: list<array{value: string, label: string}>}
     */
    public function getFilterOptions(): array
    {
        $routes = [];
        $buses = [];

        if ($this->db === null) {
            return ['routes' => [], 'buses' => []];
        }

        if ($this->tableExists('bus_trips') && $this->has('bus_trips', 'route_name')) {
            try {
                $stmt = $this->db->query("
                    SELECT DISTINCT TRIM(route_name) AS route_name
                    FROM bus_trips
                    WHERE route_name IS NOT NULL AND TRIM(route_name) <> ''
                    ORDER BY route_name ASC
                ");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $routes[(string) $row['route_name']] = true;
                }
            } catch (\Throwable $e) {
            }
        }

        if (
            $this->tableExists('passenger_trip_history')
            && $this->has('passenger_trip_history', 'boarding_point')
            && $this->has('passenger_trip_history', 'destination')
        ) {
            try {
                $stmt = $this->db->query("
                    SELECT DISTINCT
                        CONCAT(TRIM(boarding_point), ' \u{2192} ', TRIM(destination)) AS route_name
                    FROM passenger_trip_history
                    WHERE boarding_point IS NOT NULL AND TRIM(boarding_point) <> ''
                      AND destination IS NOT NULL AND TRIM(destination) <> ''
                    ORDER BY route_name ASC
                ");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $name = (string) $row['route_name'];
                    if ($name !== '') {
                        $routes[$name] = true;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $busesById = [];
        if ($this->tableExists('buses') && $this->has('buses', 'bus_id')) {
            $labelExpr = 'bus_id';
            if ($this->has('buses', 'bus_label')) {
                $labelExpr = "COALESCE(NULLIF(bus_label, ''), bus_id)";
            } elseif ($this->has('buses', 'plate_number')) {
                $labelExpr = "COALESCE(NULLIF(plate_number, ''), bus_id)";
            }
            try {
                $stmt = $this->db->query("
                    SELECT DISTINCT bus_id, $labelExpr AS label
                    FROM buses
                    WHERE bus_id IS NOT NULL AND TRIM(bus_id) <> ''
                    ORDER BY bus_id ASC
                ");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $id = trim((string) $row['bus_id']);
                    if ($id !== '') {
                        $busesById[$id] = trim((string) ($row['label'] ?? $id)) ?: $id;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if ($this->tableExists('bus_trips') && $this->has('bus_trips', 'bus_id')) {
            try {
                $stmt = $this->db->query("
                    SELECT DISTINCT TRIM(bus_id) AS bus_id
                    FROM bus_trips
                    WHERE bus_id IS NOT NULL AND TRIM(bus_id) <> ''
                    ORDER BY bus_id ASC
                ");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $id = (string) $row['bus_id'];
                    if ($id !== '' && !isset($busesById[$id])) {
                        $busesById[$id] = $id;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        ksort($busesById);
        foreach ($busesById as $id => $label) {
            $buses[] = ['value' => (string) $id, 'label' => $label];
        }

        $routeList = array_keys($routes);
        sort($routeList, SORT_NATURAL | SORT_FLAG_CASE);

        return ['routes' => $routeList, 'buses' => $buses];
    }

    /**
     * Daily passenger demand across live + archived records.
     *
     * @param array{bus?: string, route?: string, time_start?: ?int, time_end?: ?int} $scope
     * @return array<string, array{passengers: int, trips: int}>
     */
    public function getDailyPassengers(string $from, string $toExclusive, array $scope = []): array
    {
        if ($this->db === null) {
            return [];
        }

        $sources = [];
        $params = [];
        $bus = trim((string) ($scope['bus'] ?? ''));
        $route = trim((string) ($scope['route'] ?? ''));
        $timeStart = isset($scope['time_start']) && $scope['time_start'] !== null ? (int) $scope['time_start'] : null;
        $timeEnd = isset($scope['time_end']) && $scope['time_end'] !== null ? (int) $scope['time_end'] : null;
        $timeWindow = $timeStart !== null && $timeEnd !== null;

        /* ---- live passenger_trips ---- */
        $liveCanJoinRoute = $route === ''
            || ($this->tableExists('bus_trips')
                && $this->has('passenger_trips', 'trip_no')
                && $this->has('bus_trips', 'id')
                && $this->has('bus_trips', 'route_name'));
        $liveCanFilterBus = $bus === '' || $this->has('passenger_trips', 'bus_no');

        if (
            $this->tableExists('passenger_trips')
            && $this->has('passenger_trips', 'boarding_time')
            && $liveCanJoinRoute
            && $liveCanFilterBus
        ) {
            $join = '';
            $conds = [
                'pt.boarding_time >= :dp_from',
                'pt.boarding_time < :dp_to',
                'pt.boarding_time IS NOT NULL',
            ];

            $tripKey = $this->has('passenger_trips', 'trip_no')
                ? "CONCAT('pt-', COALESCE(NULLIF(pt.trip_no, ''), " . ($this->has('passenger_trips', 'id') ? 'pt.id' : "''") . '))'
                : ($this->has('passenger_trips', 'id') ? "CONCAT('pt-row-', pt.id)" : "CONCAT('pt-', pt.boarding_time)");

            if ($bus !== '' && $this->has('passenger_trips', 'bus_no')) {
                $conds[] = 'LOWER(TRIM(pt.bus_no)) = LOWER(TRIM(:dp_bus))';
                $params[':dp_bus'] = $bus;
            }

            if ($timeWindow) {
                $conds[] = 'HOUR(pt.boarding_time) >= :dp_hs AND HOUR(pt.boarding_time) < :dp_he';
                $params[':dp_hs'] = $timeStart;
                $params[':dp_he'] = $timeEnd;
            }

            if ($this->has('passenger_trips', 'payment_status')) {
                $conds[] = "LOWER(COALESCE(pt.payment_status, '')) NOT IN ('rejected', 'cancelled', 'canceled')";
            }

            if ($route !== '' && $this->tableExists('bus_trips') && $this->has('passenger_trips', 'trip_no') && $this->has('bus_trips', 'id')) {
                $join = 'INNER JOIN bus_trips bt ON bt.id = pt.trip_no';
                $conds[] = 'bt.route_name = :dp_route';
                $params[':dp_route'] = $route;
            }

            $params[':dp_from'] = $from . ' 00:00:00';
            $params[':dp_to'] = $toExclusive . ' 00:00:00';

            $sources[] = "SELECT DATE(pt.boarding_time) AS day, $tripKey AS trip_key
                          FROM passenger_trips pt
                          $join
                          WHERE " . implode(' AND ', $conds);
        }

        /* ---- archived passenger_trip_history ---- */
        $historyCanFilterBus = $bus === '' || $this->has('passenger_trip_history', 'bus_number');
        $historyCanFilterRoute = $route === ''
            || ($this->has('passenger_trip_history', 'boarding_point') && $this->has('passenger_trip_history', 'destination'));
        $historyCanFilterTime = !$timeWindow || $this->has('passenger_trip_history', 'time_of_departure');

        if (
            $this->tableExists('passenger_trip_history')
            && $this->has('passenger_trip_history', 'trip_date')
            && $historyCanFilterBus
            && $historyCanFilterRoute
            && $historyCanFilterTime
        ) {
            $conds = [
                'pth.trip_date >= :dph_from',
                'pth.trip_date < :dph_to',
                'pth.trip_date IS NOT NULL',
            ];

            $tripKey = $this->has('passenger_trip_history', 'history_id')
                ? "CONCAT('pth-', COALESCE(NULLIF(" . ($this->has('passenger_trip_history', 'trip_id') ? 'pth.trip_id' : "''") . ", ''), pth.history_id))"
                : "CONCAT('pth-', " . ($this->has('passenger_trip_history', 'trip_id') ? 'pth.trip_id' : 'pth.trip_date') . ')';

            if ($bus !== '' && $this->has('passenger_trip_history', 'bus_number')) {
                $conds[] = 'LOWER(TRIM(pth.bus_number)) = LOWER(TRIM(:dph_bus))';
                $params[':dph_bus'] = $bus;
            }

            if ($timeWindow && $this->has('passenger_trip_history', 'time_of_departure')) {
                $conds[] = 'HOUR(pth.time_of_departure) >= :dph_hs AND HOUR(pth.time_of_departure) < :dph_he';
                $params[':dph_hs'] = $timeStart;
                $params[':dph_he'] = $timeEnd;
            }

            if ($route !== '' && $this->has('passenger_trip_history', 'boarding_point') && $this->has('passenger_trip_history', 'destination')) {
                $conds[] = "CONCAT(TRIM(pth.boarding_point), ' \u{2192} ', TRIM(pth.destination)) = :dph_route";
                $params[':dph_route'] = $route;
            }

            $params[':dph_from'] = $from;
            $params[':dph_to'] = $toExclusive;

            $sources[] = "SELECT DATE(pth.trip_date) AS day, $tripKey AS trip_key
                          FROM passenger_trip_history pth
                          WHERE " . implode(' AND ', $conds);
        }

        if (!$sources) {
            return [];
        }

        $sql = '
            SELECT day,
                   COUNT(*) AS passengers,
                   COUNT(DISTINCT trip_key) AS trips
            FROM (' . implode(' UNION ALL ', $sources) . ') combined
            WHERE day IS NOT NULL
            GROUP BY day
            ORDER BY day ASC
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('PredictiveAnalysisModel::getDailyPassengers failed: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['day']] = [
                'passengers' => (int) $row['passengers'],
                'trips' => (int) $row['trips'],
            ];
        }

        return $out;
    }

    /**
     * Daily/hour passenger demand across live + archived records, so callers
     * can compute honest per-day ranges instead of invented intervals.
     *
     * @param array{bus?: string, route?: string, time_start?: ?int, time_end?: ?int} $scope
     * @return array<string, array<int, array{passengers: int, trips: int}>>
     */
    public function getHourlyPassengers(string $from, string $toExclusive, array $scope = []): array
    {
        if ($this->db === null) {
            return [];
        }

        $sources = [];
        $params = [];
        $bus = trim((string) ($scope['bus'] ?? ''));
        $route = trim((string) ($scope['route'] ?? ''));
        $timeStart = isset($scope['time_start']) && $scope['time_start'] !== null ? (int) $scope['time_start'] : null;
        $timeEnd = isset($scope['time_end']) && $scope['time_end'] !== null ? (int) $scope['time_end'] : null;
        $timeWindow = $timeStart !== null && $timeEnd !== null;

        /* ---- live passenger_trips ---- */
        $liveCanJoinRoute = $route === ''
            || ($this->tableExists('bus_trips')
                && $this->has('passenger_trips', 'trip_no')
                && $this->has('bus_trips', 'id')
                && $this->has('bus_trips', 'route_name'));
        $liveCanFilterBus = $bus === '' || $this->has('passenger_trips', 'bus_no');

        if (
            $this->tableExists('passenger_trips')
            && $this->has('passenger_trips', 'boarding_time')
            && $liveCanJoinRoute
            && $liveCanFilterBus
        ) {
            $join = '';
            $conds = [
                'pt.boarding_time >= :ph_from',
                'pt.boarding_time < :ph_to',
                'pt.boarding_time IS NOT NULL',
            ];

            $tripKey = $this->has('passenger_trips', 'trip_no')
                ? "CONCAT('pt-', COALESCE(NULLIF(pt.trip_no, ''), " . ($this->has('passenger_trips', 'id') ? 'pt.id' : "''") . '))'
                : ($this->has('passenger_trips', 'id') ? "CONCAT('pt-row-', pt.id)" : "CONCAT('pt-', pt.boarding_time)");

            if ($bus !== '' && $this->has('passenger_trips', 'bus_no')) {
                $conds[] = 'LOWER(TRIM(pt.bus_no)) = LOWER(TRIM(:ph_bus))';
                $params[':ph_bus'] = $bus;
            }

            if ($timeWindow) {
                $conds[] = 'HOUR(pt.boarding_time) >= :ph_hs AND HOUR(pt.boarding_time) < :ph_he';
                $params[':ph_hs'] = $timeStart;
                $params[':ph_he'] = $timeEnd;
            }

            if ($this->has('passenger_trips', 'payment_status')) {
                $conds[] = "LOWER(COALESCE(pt.payment_status, '')) NOT IN ('rejected', 'cancelled', 'canceled')";
            }

            if ($route !== '' && $this->tableExists('bus_trips') && $this->has('passenger_trips', 'trip_no') && $this->has('bus_trips', 'id')) {
                $join = 'INNER JOIN bus_trips bt ON bt.id = pt.trip_no';
                $conds[] = 'bt.route_name = :ph_route';
                $params[':ph_route'] = $route;
            }

            $params[':ph_from'] = $from . ' 00:00:00';
            $params[':ph_to'] = $toExclusive . ' 00:00:00';

            $sources[] = "SELECT DATE(pt.boarding_time) AS day,
                                 HOUR(pt.boarding_time) AS hour,
                                 $tripKey AS trip_key
                          FROM passenger_trips pt
                          $join
                          WHERE " . implode(' AND ', $conds);
        }

        /* ---- archived passenger_trip_history (requires departure time) ---- */
        $historyCanFilterBus = $bus === '' || $this->has('passenger_trip_history', 'bus_number');
        $historyCanFilterRoute = $route === ''
            || ($this->has('passenger_trip_history', 'boarding_point') && $this->has('passenger_trip_history', 'destination'));

        if (
            $this->tableExists('passenger_trip_history')
            && $this->has('passenger_trip_history', 'trip_date')
            && $this->has('passenger_trip_history', 'time_of_departure')
            && $historyCanFilterBus
            && $historyCanFilterRoute
        ) {
            $conds = [
                'pth.trip_date >= :phh_from',
                'pth.trip_date < :phh_to',
                'pth.time_of_departure IS NOT NULL',
            ];

            $tripKey = $this->has('passenger_trip_history', 'history_id')
                ? "CONCAT('pth-', COALESCE(NULLIF(" . ($this->has('passenger_trip_history', 'trip_id') ? 'pth.trip_id' : "''") . ", ''), pth.history_id))"
                : "CONCAT('pth-', " . ($this->has('passenger_trip_history', 'trip_id') ? 'pth.trip_id' : 'pth.trip_date') . ')';

            if ($bus !== '' && $this->has('passenger_trip_history', 'bus_number')) {
                $conds[] = 'LOWER(TRIM(pth.bus_number)) = LOWER(TRIM(:phh_bus))';
                $params[':phh_bus'] = $bus;
            }

            if ($timeWindow) {
                $conds[] = 'HOUR(pth.time_of_departure) >= :phh_hs AND HOUR(pth.time_of_departure) < :phh_he';
                $params[':phh_hs'] = $timeStart;
                $params[':phh_he'] = $timeEnd;
            }

            if ($route !== '' && $this->has('passenger_trip_history', 'boarding_point') && $this->has('passenger_trip_history', 'destination')) {
                $conds[] = "CONCAT(TRIM(pth.boarding_point), ' \u{2192} ', TRIM(pth.destination)) = :phh_route";
                $params[':phh_route'] = $route;
            }

            $params[':phh_from'] = $from;
            $params[':phh_to'] = $toExclusive;

            $sources[] = "SELECT DATE(pth.trip_date) AS day,
                                 HOUR(pth.time_of_departure) AS hour,
                                 $tripKey AS trip_key
                          FROM passenger_trip_history pth
                          WHERE " . implode(' AND ', $conds);
        }

        if (!$sources) {
            return [];
        }

        $sql = '
            SELECT day, hour,
                   COUNT(*) AS passengers,
                   COUNT(DISTINCT trip_key) AS trips
            FROM (' . implode(' UNION ALL ', $sources) . ') combined
            WHERE day IS NOT NULL AND hour IS NOT NULL
            GROUP BY day, hour
            ORDER BY day ASC, hour ASC
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('PredictiveAnalysisModel::getHourlyPassengers failed: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $day = (string) $row['day'];
            $hour = (int) $row['hour'];
            if ($day === '') {
                continue;
            }
            $out[$day][$hour] = [
                'passengers' => (int) $row['passengers'],
                'trips' => (int) $row['trips'],
            ];
        }

        return $out;
    }

    /**
     * Completed-trip counts and summed passengers per day from bus_trips.
     *
     * @param array{bus?: string, route?: string, time_start?: ?int, time_end?: ?int} $scope
     * @return array<string, array{trips: int, passengers: int}>
     */
    public function getDailyCompletedTrips(string $from, string $toExclusive, array $scope = []): array
    {
        if (
            $this->db === null
            || !$this->tableExists('bus_trips')
            || !$this->has('bus_trips', 'trip_date')
            || !$this->has('bus_trips', 'status')
        ) {
            return [];
        }

        $conds = [
            "bt.status = 'Completed'",
            'bt.trip_date >= :ct_from',
            'bt.trip_date < :ct_to',
        ];
        $params = [
            ':ct_from' => $from,
            ':ct_to' => $toExclusive,
        ];

        $passengerExpr = $this->has('bus_trips', 'total_passengers')
            ? 'COALESCE(SUM(bt.total_passengers), 0)'
            : '0';

        $bus = trim((string) ($scope['bus'] ?? ''));
        if ($bus !== '' && $this->has('bus_trips', 'bus_id')) {
            $conds[] = 'CAST(bt.bus_id AS CHAR) = :ct_bus';
            $params[':ct_bus'] = $bus;
        }

        $route = trim((string) ($scope['route'] ?? ''));
        if ($route !== '' && $this->has('bus_trips', 'route_name')) {
            $conds[] = 'TRIM(bt.route_name) = :ct_route';
            $params[':ct_route'] = $route;
        }

        $timeStart = isset($scope['time_start']) && $scope['time_start'] !== null ? (int) $scope['time_start'] : null;
        $timeEnd = isset($scope['time_end']) && $scope['time_end'] !== null ? (int) $scope['time_end'] : null;
        if ($timeStart !== null && $timeEnd !== null && $this->has('bus_trips', 'departure_time')) {
            $conds[] = 'HOUR(bt.departure_time) >= :ct_hs AND HOUR(bt.departure_time) < :ct_he';
            $params[':ct_hs'] = $timeStart;
            $params[':ct_he'] = $timeEnd;
        }

        $sql = '
            SELECT DATE(bt.trip_date) AS day,
                   COUNT(*) AS trips,
                   ' . $passengerExpr . ' AS passengers
            FROM bus_trips bt
            WHERE ' . implode(' AND ', $conds) . '
            GROUP BY DATE(bt.trip_date)
            ORDER BY day ASC
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('PredictiveAnalysisModel::getDailyCompletedTrips failed: ' . $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['day']] = [
                'trips' => (int) $row['trips'],
                'passengers' => (int) $row['passengers'],
            ];
        }

        return $out;
    }

}

<?php

namespace Admin\Models;

use PDO;

class DashboardModel
{
    private PDO $db;

    private array $columnCache = [];

    public function __construct(PDO $db)
    {
        if (!$db) {
            throw new \Exception(
                "DashboardModel initialization failed: PDO database connection is null."
            );
        }

        $this->db = $db;
    }

    /* =====================================================
       UNIVERSAL DATE FILTER BUILDER
    ===================================================== */

    private function buildDateFilters(
        string $column,
        array $filters,
        array &$params
    ): string {

        $conditions = [];

        // SAFE PARAMETER NAME
        $safeColumn = str_replace('.', '_', $column);

        if (!empty($filters['day'])) {

            $key = $safeColumn . '_day';

            $conditions[] = "DAY($column) = :$key";

            $params[":$key"] = $filters['day'];
        }

        if (!empty($filters['month'])) {

            $key = $safeColumn . '_month';

            $conditions[] = "MONTH($column) = :$key";

            $params[":$key"] = $filters['month'];
        }

        if (!empty($filters['year'])) {

            $key = $safeColumn . '_year';

            $conditions[] = "YEAR($column) = :$key";

            $params[":$key"] = $filters['year'];
        }

        if (!empty($filters['fromDate'])) {

            $key = $safeColumn . '_from';

            $conditions[] = "DATE($column) >= :$key";

            $params[":$key"] = $filters['fromDate'];
        }

        if (!empty($filters['toDate'])) {

            $key = $safeColumn . '_to';

            $conditions[] = "DATE($column) <= :$key";

            $params[":$key"] = $filters['toDate'];
        }

        return $conditions
            ? implode(' AND ', $conditions)
            : '';
    }

    /* =====================================================
       SARGABLE DATE RANGE (for stats performance)
       Builds [start, end) calendar boundaries from the same
       range/filter combinations the dashboard produces, so a
       single index on the date column can be used instead of
       applying DAY()/MONTH()/YEAR() functions to every row.
       Falls back to the legacy function-based filter when the
       combination is incomplete (e.g. month only, no year).
       ===================================================== */

    private function buildRange(array $filters): ?array
    {
        if (!empty($filters['fromDate']) && !empty($filters['toDate'])) {
            $start = $filters['fromDate'] . ' 00:00:00';
            $end   = date('Y-m-d 00:00:00', strtotime($filters['toDate'] . ' +1 day'));
            return [$start, $end];
        }

        if (!empty($filters['day']) && !empty($filters['month']) && !empty($filters['year'])) {
            $d = sprintf('%04d-%02d-%02d', $filters['year'], $filters['month'], $filters['day']);
            return [$d . ' 00:00:00', date('Y-m-d 00:00:00', strtotime($d . ' +1 day'))];
        }

        if (!empty($filters['month']) && !empty($filters['year'])) {
            $start = sprintf('%04d-%02d-01', $filters['year'], $filters['month']);
            return [$start . ' 00:00:00', date('Y-m-d 00:00:00', strtotime($start . ' first day of next month'))];
        }

        if (!empty($filters['year'])) {
            $start = sprintf('%04d-01-01', $filters['year']);
            $end   = sprintf('%04d-01-01', $filters['year'] + 1);
            return [$start . ' 00:00:00', $end . ' 00:00:00'];
        }

        return null;
    }

    private function dateCondition(string $column, array $filters, array &$params, string $tag): string
    {
        $range = $this->buildRange($filters);
        if ($range !== null) {
            $params[":{$tag}_s"] = $range[0];
            $params[":{$tag}_e"] = $range[1];
            return "($column >= :{$tag}_s AND $column < :{$tag}_e)";
        }
        return $this->buildDateFilters($column, $filters, $params);
    }

    /* =====================================================
       VEHICLE / ROUTE FILTERS
       Shared by every aggregation that supports the dashboard's
       advanced bus and route filters. Route names are resolved
       through the buses table's assigned route_name column.
    ===================================================== */

    private function tableHasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :t
                   AND COLUMN_NAME = :c
                 LIMIT 1"
            );
            $stmt->execute([':t' => $table, ':c' => $column]);
            $this->columnCache[$key] = (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->columnCache[$key] = false;
        }

        return $this->columnCache[$key];
    }

    private function odoColumnExpr(string $alias, string $kind): ?string
    {
        $primary   = $this->tableHasColumn('fuel_logs', $kind . '_odo') ? "$alias.{$kind}_odo" : null;
        $secondary = $this->tableHasColumn('fuel_logs', $kind . '_odometer') ? "$alias.{$kind}_odometer" : null;

        if ($primary !== null && $secondary !== null) {
            return "COALESCE($primary, $secondary)";
        }

        return $primary ?? $secondary;
    }

    private function distanceExpr(string $alias): string
    {
        $start = $this->odoColumnExpr($alias, 'starting');
        $end   = $this->odoColumnExpr($alias, 'ending');

        if ($start === null || $end === null) {
            return '0';
        }

        return "GREATEST(COALESCE($end, 0) - COALESCE($start, 0), 0)";
    }

    private function vehicleConstraint(string $column, array $filters, array &$params, string $tag): string
    {
        $buses = [];

        if (!empty($filters['bus'])) {
            $buses[] = (string)$filters['bus'];
        }

        if (!empty($filters['route'])) {
            try {
                $stmt = $this->db->prepare(
                    "SELECT bus_id FROM buses
                     WHERE LOWER(TRIM(route_name)) = LOWER(TRIM(:route))"
                );
                $stmt->execute([':route' => (string)$filters['route']]);
                $routeBuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (\Throwable $e) {
                $routeBuses = [];
            }

            if (empty($routeBuses)) {
                return '1 = 0';
            }

            if (empty($buses)) {
                $buses = $routeBuses;
            } else {
                $normalized = array_map(
                    static fn($v) => strtolower(trim((string)$v)),
                    $routeBuses
                );
                $buses = array_values(array_filter(
                    $buses,
                    static fn($b) => in_array(strtolower(trim((string)$b)), $normalized, true)
                ));

                if (empty($buses)) {
                    return '1 = 0';
                }
            }
        }

        if (empty($buses)) {
            return '';
        }

        $placeholders = [];

        foreach (array_values(array_unique($buses)) as $i => $bus) {
            $key = ":{$tag}_veh_{$i}";
            $placeholders[] = $key;
            $params[$key] = strtolower(trim((string)$bus));
        }

        return "LOWER(TRIM($column)) IN (" . implode(', ', $placeholders) . ")";
    }

    /* =====================================================
       FUEL EFFICIENCY (existing ODO + KPL data)
       Distance = ending ODO - starting ODO
       Consumed = liters when present, else distance / kpl
       Efficiency = distance / consumed (km/L)
    ===================================================== */

    public function fleetFuelEfficiency(array $filters = []): float
    {
        $summary = $this->fuelEfficiencySummary($filters);

        return (float)($summary['fleet_kmpl'] ?? 0.0);
    }

    public function fuelEfficiencySummary(array $filters = []): array
    {
        $params = [];

        $dateSQL = $this->dateCondition('fl.created_at', $filters, $params, 'fes');
        $vehicle = $this->vehicleConstraint('fl.bus_id', $filters, $params, 'fes');
        $distanceExpr = $this->distanceExpr('fl');

        $conditions = array_values(array_filter([$dateSQL, $vehicle]));
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT
                fl.bus_id,
                COALESCE(SUM($distanceExpr), 0) AS distance,
                COALESCE(SUM(
                    CASE
                        WHEN fl.liters > 0 THEN fl.liters
                        WHEN fl.kpl > 0 AND $distanceExpr > 0 THEN $distanceExpr / fl.kpl
                        ELSE 0
                    END
                ), 0) AS consumed,
                COALESCE(SUM(CASE WHEN fl.liters > 0 THEN $distanceExpr ELSE 0 END), 0) AS actual_distance,
                COALESCE(SUM(CASE WHEN fl.liters > 0 THEN fl.liters ELSE 0 END), 0) AS actual_liters
            FROM fuel_logs fl
            $where
            GROUP BY fl.bus_id
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $rows = [];
        }

        /* Actual purchases live in fuel_refuels for generator-produced history;
           fuel_logs carries the per-trip odometer rows (zero liters/cost). */
        $refuelByBus = [];
        try {
            $chk = $this->db->query("SHOW TABLES LIKE 'fuel_refuels'");
            if ($chk && $chk->fetchColumn() !== false) {
                $refParams = [];
                $refDate = $this->dateCondition('fr.created_at', $filters, $refParams, 'fer');
                $refVehicle = $this->vehicleConstraint('fr.bus_id', $filters, $refParams, 'fer');
                $refConditions = array_values(array_filter([$refDate, $refVehicle]));
                $refWhere = $refConditions ? 'WHERE ' . implode(' AND ', $refConditions) : '';
                $stmt = $this->db->prepare("
                    SELECT fr.bus_id, COALESCE(SUM(fr.liters), 0) AS liters
                    FROM fuel_refuels fr
                    $refWhere
                    GROUP BY fr.bus_id
                ");
                $stmt->execute($refParams);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $refRow) {
                    $refuelByBus[(string)($refRow['bus_id'] ?? '')] = (float)($refRow['liters'] ?? 0);
                }
            }
        } catch (\Throwable $e) {
            // fuel_refuels unavailable: fall back to fuel_logs figures only.
        }

        $buses = [];
        $fleetDistance = 0.0;
        $fleetConsumed = 0.0;

        foreach ($rows as $row) {
            $busId = (string)($row['bus_id'] ?? '');
            $distance = (float)($row['distance'] ?? 0);
            $consumed = (float)($row['consumed'] ?? 0);
            $actualDistance = (float)($row['actual_distance'] ?? 0);
            $actualLiters = (float)($row['actual_liters'] ?? 0);
            $refuelLiters = (float)($refuelByBus[$busId] ?? 0);

            if ($distance <= 0) {
                continue;
            }

            /* km/L: paired actual odometer distance over actual liters when
               recorded, else period odometer distance over the bus's refuel
               purchases; the distance/KPL estimate is a last resort only. */
            if ($actualDistance > 0 && $actualLiters > 0) {
                $busDistance = $actualDistance;
                $busConsumed = $actualLiters;
            } elseif ($refuelLiters > 0) {
                $busDistance = $distance;
                $busConsumed = $refuelLiters;
            } elseif ($consumed > 0) {
                $busDistance = $distance;
                $busConsumed = $consumed;
            } else {
                continue;
            }

            $fleetDistance += $busDistance;
            $fleetConsumed += $busConsumed;

            $buses[] = [
                'bus_id'   => $busId,
                'label'    => $busId,
                'kmpl'     => round($busDistance / $busConsumed, 2),
                'distance' => round($busDistance, 2),
                'consumed' => round($busConsumed, 2),
            ];
        }

        usort($buses, static fn($a, $b) => $b['kmpl'] <=> $a['kmpl']);

        return [
            'fleet_kmpl' => ($fleetConsumed > 0 && $fleetDistance > 0)
                ? round($fleetDistance / $fleetConsumed, 2)
                : 0.0,
            'buses'  => $buses,
            'best'   => $buses[0] ?? null,
            'lowest' => count($buses) > 1 ? $buses[count($buses) - 1] : null,
        ];
    }

    /* =====================================================
       TRIP ACTIVITY SERIES
       Counts distinct trips per time bucket using the same
       trip union as totalTrips(): trip_operations_reports,
       passenger_trips and passenger_trip_history.
    ===================================================== */

    public function tripCountsByBucket(
        string $start,
        string $end,
        string $granularity,
        array $filters = []
    ): array {
        $format = match ($granularity) {
            'hour'  => '%Y-%m-%d %H',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $params = [];
        $sources = [];

        $torBucket = ($granularity === 'hour' && $this->tableHasColumn('trip_operations_reports', 'created_at'))
            ? 'tor.created_at'
            : 'tor.trip_date';

        $torConditions = ["(tor.trip_date >= :tor_s AND tor.trip_date < :tor_e)"];
        $params[':tor_s'] = $start;
        $params[':tor_e'] = $end;
        $torVehicle = $this->vehicleConstraint('tor.bus_id', $filters, $params, 'tor');
        if ($torVehicle !== '') {
            $torConditions[] = $torVehicle;
        }
        $sources[] = "SELECT DATE_FORMAT($torBucket, '$format') AS bucket,
                             CONCAT('tor-', tor.id) AS trip_key
                      FROM trip_operations_reports tor
                      WHERE " . implode(' AND ', $torConditions);

        $ptConditions = ["(pt.boarding_time >= :pt_s AND pt.boarding_time < :pt_e)"];
        $params[':pt_s'] = $start;
        $params[':pt_e'] = $end;
        $ptVehicle = $this->vehicleConstraint('pt.bus_no', $filters, $params, 'pt');
        if ($ptVehicle !== '') {
            $ptConditions[] = $ptVehicle;
        }
        $sources[] = "SELECT DATE_FORMAT(pt.boarding_time, '$format') AS bucket,
                             CONCAT('pt-', pt.trip_no) AS trip_key
                      FROM passenger_trips pt
                      WHERE " . implode(' AND ', $ptConditions);

        $pthConditions = ["(pth.trip_date >= :pth_s AND pth.trip_date < :pth_e)"];
        $params[':pth_s'] = $start;
        $params[':pth_e'] = $end;
        $pthVehicle = $this->vehicleConstraint('pth.bus_number', $filters, $params, 'pth');
        if ($pthVehicle !== '') {
            $pthConditions[] = $pthVehicle;
        }
        $sources[] = "SELECT DATE_FORMAT(pth.trip_date, '$format') AS bucket,
                             CONCAT('pth-', COALESCE(NULLIF(pth.trip_id, ''), pth.history_id)) AS trip_key
                      FROM passenger_trip_history pth
                      WHERE " . implode(' AND ', $pthConditions);

        $sql = "
            SELECT bucket, COUNT(DISTINCT trip_key) AS total
            FROM (" . implode(' UNION ALL ', $sources) . ") combined
            GROUP BY bucket
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $counts = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $counts[(string)$row['bucket']] = (int)$row['total'];
            }

            return $counts;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /* =====================================================
       OPERATIONAL SUMMARY
       Compact per-period indicators: most active route, most
       utilized bus, highest passenger activity, latest trip
       status and the highest-priority attention item.
    ===================================================== */

    public function operationalSummary(array $filters = []): array
    {
        return [
            'route'              => $this->mostActiveRoute($filters),
            'bus'                => $this->mostUtilizedBus($filters),
            'passenger_activity' => $this->highestPassengerActivity($filters),
            'trip_status'        => $this->latestTripStatus($filters),
            'issue'              => $this->topAttentionItem($filters),
        ];
    }

    private function mostActiveRoute(array $filters): ?array
    {
        $params = [];

        $ptDate = $this->dateCondition('pt.boarding_time', $filters, $params, 'mar_pt');
        $ptVehicle = $this->vehicleConstraint('pt.bus_no', $filters, $params, 'mar_pt');
        $ptConditions = array_values(array_filter([$ptDate, $ptVehicle]));
        $ptWhere = $ptConditions ? 'WHERE ' . implode(' AND ', $ptConditions) : '';

        $pthDate = $this->dateCondition('pth.trip_date', $filters, $params, 'mar_pth');
        $pthVehicle = $this->vehicleConstraint('pth.bus_number', $filters, $params, 'mar_pth');
        $pthConditions = array_values(array_filter([$pthDate, $pthVehicle]));
        $pthWhere = $pthConditions ? 'WHERE ' . implode(' AND ', $pthConditions) : '';

        /* Each physical trip is counted once per route: rides are matched to
           the route whose fare_pricing stops contain both the boarding point
           and the destination in order, and the trip key is counted DISTINCT
           per route. Counting per boarding/destination segment and summing
           those counts into a route label multiplied every trip by its number
           of stop pairs (a 127-trip period reported a 790-trip route). */
        $sql = "
            SELECT route_name, COUNT(DISTINCT trip_key) AS trips
            FROM (
                SELECT fp_d.route_name AS route_name,
                       CONCAT('pt-', pt.trip_no) AS trip_key
                FROM passenger_trips pt
                INNER JOIN fare_pricing fp_d
                        ON LOWER(TRIM(fp_d.destination)) = LOWER(TRIM(pt.destination))
                INNER JOIN fare_pricing fp_b
                        ON fp_b.route_name = fp_d.route_name
                       AND LOWER(TRIM(fp_b.destination)) = LOWER(TRIM(pt.boarding_point))
                       AND fp_b.display_order < fp_d.display_order
                $ptWhere

                UNION ALL

                SELECT fp_d.route_name AS route_name,
                       CONCAT('pth-', COALESCE(NULLIF(pth.trip_id, ''), pth.history_id)) AS trip_key
                FROM passenger_trip_history pth
                INNER JOIN fare_pricing fp_d
                        ON LOWER(TRIM(fp_d.destination)) = LOWER(TRIM(pth.destination))
                INNER JOIN fare_pricing fp_b
                        ON fp_b.route_name = fp_d.route_name
                       AND LOWER(TRIM(fp_b.destination)) = LOWER(TRIM(pth.boarding_point))
                       AND fp_b.display_order < fp_d.display_order
                $pthWhere
            ) combined
            GROUP BY route_name
            ORDER BY trips DESC
            LIMIT 1
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $row = false;
        }

        if ($row && (int)($row['trips'] ?? 0) > 0) {
            return [
                'label' => (string)$row['route_name'],
                'trips' => (int)$row['trips'],
            ];
        }

        return $this->mostActiveRouteByStopPairs($filters);
    }

    /* Fuzzy fallback for rides whose stop names do not exactly match
       fare_pricing stop names: resolves each boarding/destination pair to a
       route label and sums the pair counts (legacy behavior). */
    private function mostActiveRouteByStopPairs(array $filters): ?array
    {
        $params = [];

        $ptDate = $this->dateCondition('pt.boarding_time', $filters, $params, 'mar_pt');
        $ptVehicle = $this->vehicleConstraint('pt.bus_no', $filters, $params, 'mar_pt');
        $ptConditions = array_values(array_filter([$ptDate, $ptVehicle]));
        $ptWhere = $ptConditions ? 'WHERE ' . implode(' AND ', $ptConditions) : '';

        $pthDate = $this->dateCondition('pth.trip_date', $filters, $params, 'mar_pth');
        $pthVehicle = $this->vehicleConstraint('pth.bus_number', $filters, $params, 'mar_pth');
        $pthConditions = array_values(array_filter([$pthDate, $pthVehicle]));
        $pthWhere = $pthConditions ? 'WHERE ' . implode(' AND ', $pthConditions) : '';

        $sql = "
            SELECT boarding, destination, COUNT(DISTINCT trip_key) AS trips
            FROM (
                SELECT pt.boarding_point AS boarding,
                       pt.destination AS destination,
                       CONCAT('pt-', pt.trip_no) AS trip_key
                FROM passenger_trips pt
                $ptWhere

                UNION ALL

                SELECT pth.boarding_point AS boarding,
                       pth.destination AS destination,
                       CONCAT('pth-', COALESCE(NULLIF(pth.trip_id, ''), pth.history_id)) AS trip_key
                FROM passenger_trip_history pth
                $pthWhere
            ) combined
            GROUP BY boarding, destination
            HAVING trips > 0
            ORDER BY trips DESC
            LIMIT 500
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null;
        }

        $totals = [];

        foreach ($rows as $row) {
            $trips = (int)($row['trips'] ?? 0);
            if ($trips <= 0) {
                continue;
            }

            $label = $this->resolveRouteName(
                (string)($row['boarding'] ?? ''),
                (string)($row['destination'] ?? '')
            );

            if ($label === null) {
                $from = trim((string)($row['boarding'] ?? ''));
                $to   = trim((string)($row['destination'] ?? ''));
                $label = ($from !== '' || $to !== '')
                    ? trim($from . ' → ' . $to)
                    : 'Unassigned route';
            }

            $totals[$label] = ($totals[$label] ?? 0) + $trips;
        }

        if (empty($totals)) {
            return null;
        }

        arsort($totals);

        $label = (string)array_key_first($totals);

        return ['label' => $label, 'trips' => (int)$totals[$label]];
    }

    private function mostUtilizedBus(array $filters): ?array
    {
        $params = [];

        $ptDate = $this->dateCondition('pt.boarding_time', $filters, $params, 'mub_pt');
        $ptVehicle = $this->vehicleConstraint('pt.bus_no', $filters, $params, 'mub_pt');
        $ptConditions = array_values(array_filter([$ptDate, $ptVehicle]));
        $ptConditions[] = "pt.bus_no IS NOT NULL AND TRIM(pt.bus_no) != ''";
        $ptWhere = 'WHERE ' . implode(' AND ', $ptConditions);

        $pthDate = $this->dateCondition('pth.trip_date', $filters, $params, 'mub_pth');
        $pthVehicle = $this->vehicleConstraint('pth.bus_number', $filters, $params, 'mub_pth');
        $pthConditions = array_values(array_filter([$pthDate, $pthVehicle]));
        $pthConditions[] = "pth.bus_number IS NOT NULL AND TRIM(pth.bus_number) != ''";
        $pthWhere = 'WHERE ' . implode(' AND ', $pthConditions);

        $sql = "
            SELECT bus_id, COUNT(DISTINCT trip_key) AS trips
            FROM (
                SELECT pt.bus_no AS bus_id,
                       CONCAT('pt-', pt.trip_no) AS trip_key
                FROM passenger_trips pt
                $ptWhere

                UNION ALL

                SELECT pth.bus_number AS bus_id,
                       CONCAT('pth-', COALESCE(NULLIF(pth.trip_id, ''), pth.history_id)) AS trip_key
                FROM passenger_trip_history pth
                $pthWhere
            ) combined
            GROUP BY bus_id
            ORDER BY trips DESC
            LIMIT 1
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$row || (int)($row['trips'] ?? 0) <= 0) {
            return null;
        }

        $busId = (string)$row['bus_id'];

        return [
            'label' => $this->busLabel($busId),
            'bus_id' => $busId,
            'trips' => (int)$row['trips'],
        ];
    }

    private function highestPassengerActivity(array $filters): ?array
    {
        $params = [];

        $ptDate = $this->dateCondition('pt.boarding_time', $filters, $params, 'hpa_pt');
        $ptVehicle = $this->vehicleConstraint('pt.bus_no', $filters, $params, 'hpa_pt');
        $ptConditions = array_values(array_filter([$ptDate, $ptVehicle]));
        $ptConditions[] = "pt.boarding_point IS NOT NULL AND TRIM(pt.boarding_point) != ''";
        $ptWhere = 'WHERE ' . implode(' AND ', $ptConditions);

        $pthDate = $this->dateCondition('pth.trip_date', $filters, $params, 'hpa_pth');
        $pthVehicle = $this->vehicleConstraint('pth.bus_number', $filters, $params, 'hpa_pth');
        $pthConditions = array_values(array_filter([$pthDate, $pthVehicle]));
        $pthConditions[] = "pth.boarding_point IS NOT NULL AND TRIM(pth.boarding_point) != ''";
        $pthWhere = 'WHERE ' . implode(' AND ', $pthConditions);

        $sql = "
            SELECT point, COUNT(*) AS passengers
            FROM (
                SELECT pt.boarding_point AS point FROM passenger_trips pt $ptWhere

                UNION ALL

                SELECT pth.boarding_point AS point FROM passenger_trip_history pth $pthWhere
            ) combined
            GROUP BY point
            ORDER BY passengers DESC
            LIMIT 1
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$row || (int)($row['passengers'] ?? 0) <= 0) {
            return null;
        }

        return [
            'label'      => (string)$row['point'],
            'passengers' => (int)$row['passengers'],
        ];
    }

    private function latestTripStatus(array $filters): ?array
    {
        $bounds = $this->buildRange($filters);

        $params = [];
        $conditions = [];

        if ($bounds !== null) {
            $conditions[] = "(trip_date >= :ts_s AND trip_date < :ts_e)";
            $params[':ts_s'] = $bounds[0];
            $params[':ts_e'] = $bounds[1];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        try {
            $stmt = $this->db->prepare(
                "SELECT status, route_name, bus_id, trip_date
                 FROM bus_trips
                 $where
                 ORDER BY trip_date DESC, id DESC
                 LIMIT 1"
            );
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $row = false;
        }

        if ($row) {
            $route = trim((string)($row['route_name'] ?? ''));
            $bus   = trim((string)($row['bus_id'] ?? ''));

            return [
                'status' => (string)($row['status'] ?? 'Scheduled'),
                'detail' => trim(($route !== '' ? $route : '') . ($bus !== '' ? ' • ' . $this->busLabel($bus) : ''), ' •'),
                'at'     => (string)($row['trip_date'] ?? ''),
            ];
        }

        try {
            $params = [];
            $ptDate = $this->dateCondition('pt.boarding_time', $filters, $params, 'lts');
            $ptVehicle = $this->vehicleConstraint('pt.bus_no', $filters, $params, 'lts');
            $conditions = array_values(array_filter([$ptDate, $ptVehicle]));
            $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

            $stmt = $this->db->prepare(
                "SELECT pt.boarding_time, pt.bus_no
                 FROM passenger_trips pt
                 $where
                 ORDER BY pt.boarding_time DESC
                 LIMIT 1"
            );
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $row = false;
        }

        if ($row) {
            $bus = trim((string)($row['bus_no'] ?? ''));

            return [
                'status' => 'Trip recorded',
                'detail' => $bus !== '' ? $this->busLabel($bus) : '',
                'at'     => (string)($row['boarding_time'] ?? ''),
            ];
        }

        return null;
    }

    private function topAttentionItem(array $filters): ?array
    {
        $attention = $this->needsAttention($filters);

        return ($attention['total'] ?? 0) > 0 ? ($attention['items'][0] ?? null) : null;
    }

    private function busLabel(string $busId): string
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT plate_number FROM buses WHERE bus_id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $busId]);
            $plate = trim((string)$stmt->fetchColumn());
        } catch (\Throwable $e) {
            $plate = '';
        }

        return $plate !== '' ? $plate : $busId;
    }

    private function farePricingStopIndex(): array
    {
        $rows = $this->db->query(
            "SELECT LOWER(TRIM(destination)) AS stop, route_name, display_order
             FROM fare_pricing
             ORDER BY route_name, display_order"
        )->fetchAll(PDO::FETCH_ASSOC);

        $index = [];

        foreach ($rows as $row) {
            $index[$row['stop']][$row['route_name']] = (int)$row['display_order'];
        }

        return $index;
    }

    private function resolveRouteName(string $from, string $to): ?string
    {
        $from = strtolower(trim($from));
        $to   = strtolower(trim($to));

        if ($from === '' || $to === '' || $from === $to) {
            return null;
        }

        try {
            $index = $this->farePricingStopIndex();
        } catch (\Throwable $e) {
            return null;
        }

        if (isset($index[$from]) && isset($index[$to])) {
            foreach ($index[$from] as $routeName => $fromOrder) {
                $toOrder = $index[$to][$routeName] ?? null;
                if ($toOrder !== null && $fromOrder < $toOrder) {
                    return (string)$routeName;
                }
            }
        }

        $candidates = [];

        foreach ($index as $stop => $routes) {
            if (str_contains($stop, $from) || str_contains($from, $stop)) {
                foreach ($routes as $routeName => $order) {
                    $candidates[$routeName]['from'] = $order;
                }
            }
            if (str_contains($stop, $to) || str_contains($to, $stop)) {
                foreach ($routes as $routeName => $order) {
                    $candidates[$routeName]['to'] = $order;
                }
            }
        }

        foreach ($candidates as $routeName => $pair) {
            if (isset($pair['from'], $pair['to']) && $pair['from'] < $pair['to']) {
                return (string)$routeName;
            }
        }

        return null;
    }

    /* =====================================================
       NEEDS ATTENTION
       Only important, actionable items: trip discrepancies,
       fuel records awaiting verification and failed/warning
       system alerts. Ordinary notifications never qualify.
    ===================================================== */

    public function needsAttention(array $filters = []): array
    {
        $items = [];

        if ($this->tableHasColumn('passenger_trips', 'discrepancy_amount')) {
            try {
                $params = [];
                $date = $this->dateCondition('pt.boarding_time', $filters, $params, 'nad');
                $vehicle = $this->vehicleConstraint('pt.bus_no', $filters, $params, 'nad');
                $conditions = array_values(array_filter([$date, $vehicle]));
                $where = 'WHERE pt.discrepancy_amount > 0'
                    . ($conditions ? ' AND ' . implode(' AND ', $conditions) : '');

                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) AS c, MAX(pt.boarding_time) AS latest
                     FROM passenger_trips pt
                     $where"
                );
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $count = (int)($row['c'] ?? 0);
                if ($count > 0) {
                    $items[] = [
                        'type'      => 'trip_discrepancy',
                        'label'     => 'Trip discrepancies',
                        'count'     => $count,
                        'latest_at' => (string)($row['latest'] ?? ''),
                    ];
                }
            } catch (\Throwable $e) {
                // Rule unavailable on this schema.
            }
        }

        try {
            $missingOdo = null;

            if ($this->tableHasColumn('fuel_logs', 'ending_odo')
                && $this->tableHasColumn('fuel_logs', 'ending_odometer')) {
                $missingOdo = '(fl.ending_odo IS NULL AND fl.ending_odometer IS NULL)';
            } elseif ($this->tableHasColumn('fuel_logs', 'ending_odo')) {
                $missingOdo = '(fl.ending_odo IS NULL)';
            } elseif ($this->tableHasColumn('fuel_logs', 'ending_odometer')) {
                $missingOdo = '(fl.ending_odometer IS NULL)';
            }

            $missingKpl = $this->tableHasColumn('fuel_logs', 'kpl')
                ? '(COALESCE(fl.kpl, 0) <= 0)'
                : null;

            $rules = array_values(array_filter([$missingOdo, $missingKpl]));

            if (!empty($rules)) {
                $params = [];
                $date = $this->dateCondition('fl.created_at', $filters, $params, 'naf');
                $vehicle = $this->vehicleConstraint('fl.bus_id', $filters, $params, 'naf');
                $conditions = array_values(array_filter([$date, $vehicle]));
                $where = 'WHERE (' . implode(' OR ', $rules) . ')'
                    . ($conditions ? ' AND ' . implode(' AND ', $conditions) : '');

                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) AS c, MAX(fl.created_at) AS latest
                     FROM fuel_logs fl
                     $where"
                );
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $count = (int)($row['c'] ?? 0);
                if ($count > 0) {
                    $items[] = [
                        'type'      => 'fuel_verification',
                        'label'     => 'Fuel records requiring verification',
                        'count'     => $count,
                        'latest_at' => (string)($row['latest'] ?? ''),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Rule unavailable on this schema.
        }

        try {
            $params = [];
            $date = $this->dateCondition('at.created_at', $filters, $params, 'naa');
            $where = "WHERE at.status IN ('Failed', 'Warning')"
                . ($date ? " AND $date" : '');

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS c, MAX(at.created_at) AS latest
                 FROM audit_trail at
                 $where"
            );
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $count = (int)($row['c'] ?? 0);
            if ($count > 0) {
                $items[] = [
                    'type'      => 'system_alert',
                    'label'     => 'System alerts',
                    'count'     => $count,
                    'latest_at' => (string)($row['latest'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            // Rule unavailable on this schema.
        }

        /* Dispatcher passenger-count discrepancies awaiting review (module Dispatcher). */
        if ($this->tableHasColumn('audit_trail', 'record_id')) {
            try {
                $params = [];
                $date = $this->dateCondition('at.created_at', $filters, $params, 'nadd');
                $where = "WHERE at.module = 'Dispatcher' AND at.action = 'Passenger Count Discrepancy'"
                    . " AND NOT EXISTS (SELECT 1 FROM audit_trail r WHERE r.module = 'Dispatcher'"
                    . "   AND r.action = 'Passenger Count Resolved' AND r.record_id = at.record_id AND r.id > at.id)"
                    . ($date ? " AND $date" : '');

                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) AS c, MAX(at.created_at) AS latest
                     FROM audit_trail at
                     $where"
                );
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $count = (int)($row['c'] ?? 0);

                if ($count > 0) {
                    $latestStmt = $this->db->prepare(
                        "SELECT at.id, at.record_id, at.new_values
                         FROM audit_trail at
                         $where
                         ORDER BY at.id DESC
                         LIMIT 1"
                    );
                    $latestStmt->execute($params);
                    $latestRow = $latestStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    $latestValues = json_decode((string)($latestRow['new_values'] ?? ''), true) ?: [];

                    $items[] = [
                        'type'      => 'dispatcher_discrepancy',
                        'label'     => 'Passenger-count discrepancies',
                        'count'     => $count,
                        'latest_at' => (string)($row['latest'] ?? ''),
                        'trip_id'   => (int)($latestRow['record_id'] ?? 0),
                        'audit_id'  => (int)($latestRow['id'] ?? 0),
                        'link'      => '/admin/inspectors/?dispatcher_discrepancy=' . (int)($latestRow['id'] ?? 0),
                        'details'   => [
                            'trip_id'          => (int)($latestRow['record_id'] ?? 0),
                            'bus_id'           => (string)($latestValues['bus_id'] ?? ''),
                            'driver_id'        => (string)($latestValues['driver_id'] ?? ''),
                            'dispatcher_id'    => (string)($latestValues['dispatcher_id'] ?? ''),
                            'trip_date'        => (string)($latestValues['trip_date'] ?? ''),
                            'dispatcher_count' => (int)($latestValues['dispatcher_count'] ?? 0),
                            'driver_count'     => (int)($latestValues['driver_count'] ?? 0),
                            'inspector_count'  => isset($latestValues['inspector_count']) && $latestValues['inspector_count'] !== null
                                ? (int)$latestValues['inspector_count']
                                : null,
                            'difference'       => (int)($latestValues['difference'] ?? 0),
                            'review_status'    => 'Requires Review',
                        ],
                    ];
                }
            } catch (\Throwable $e) {
                // Rule unavailable on this schema.
            }
        }

        /* Inspector needs-review items (from inspector_inspection_items, when present). */
        if ($this->tableHasColumn('inspector_inspection_items', 'validation_status')) {
            try {
                $params = [];
                $dateCol = $this->tableHasColumn('inspector_inspection_items', 'verified_at')
                    ? 'verified_at'
                    : ($this->tableHasColumn('inspector_inspection_items', 'created_at') ? 'created_at' : null);
                $date = $dateCol !== null ? $this->dateCondition('ii.' . $dateCol, $filters, $params, 'naii') : '';
                $where = "WHERE UPPER(TRIM(ii.validation_status)) = 'NEEDS REVIEW'" . ($date ? " AND $date" : '');

                $latestExpr = $dateCol !== null ? 'MAX(ii.`' . $dateCol . '`)' : "''";
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) AS c, $latestExpr AS latest
                     FROM inspector_inspection_items ii
                     $where"
                );
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $count = (int)($row['c'] ?? 0);

                if ($count > 0) {
                    $select = [];
                    foreach (['inspection_id', 'passenger_name', 'passenger_code', 'trip_id', 'bus_id', 'bus_no', 'discrepancy_reasons', 'verified_at'] as $col) {
                        if ($this->tableHasColumn('inspector_inspection_items', $col)) {
                            $select[] = 'ii.`' . $col . '`';
                        }
                    }
                    if (empty($select)) {
                        $select[] = '1';
                    }
                    $orderCol = $dateCol !== null
                        ? 'ii.`' . $dateCol . '`'
                        : ($this->tableHasColumn('inspector_inspection_items', 'inspection_id') ? 'ii.`inspection_id`' : '1');

                    $latestStmt = $this->db->prepare(
                        'SELECT ' . implode(', ', $select) . " FROM inspector_inspection_items ii $where ORDER BY $orderCol DESC LIMIT 1"
                    );
                    $latestStmt->execute($params);
                    $latest = $latestStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                    $items[] = [
                        'type'      => 'inspector_review',
                        'label'     => 'Inspector needs-review items',
                        'count'     => $count,
                        'latest_at' => (string)($row['latest'] ?? ''),
                        'link'      => '/admin/inspectors/?monitoring=inspector&inspector_item=' . (string)($latest['inspection_id'] ?? ''),
                        'details'   => [
                            'source'            => 'inspector',
                            'trip_id'           => (string)($latest['trip_id'] ?? ''),
                            'bus_id'            => (string)($latest['bus_id'] ?? ($latest['bus_no'] ?? '')),
                            'passenger_name'    => (string)($latest['passenger_name'] ?? ''),
                            'passenger_code'    => (string)($latest['passenger_code'] ?? ''),
                            'validation_status' => 'NEEDS REVIEW',
                            'reason'            => (string)($latest['discrepancy_reasons'] ?? ''),
                        ],
                    ];
                }
            } catch (\Throwable $e) {
                // Rule unavailable on this schema.
            }
        }

        /* =====================================================
           BUS PROBLEM REPORTS
           Unresolved reports (waiting/acknowledged) from the
           dispatcher's bus_problem_reports table — the single source
           of truth. Each report shows its bus, problem type, trip
           number, driver, reported time and current status. Resolving
           a report removes it from this list automatically.
        ===================================================== */
        if ($this->tableHasColumn('bus_problem_reports', 'status')) {
            try {
                $params = [];
                $date = $this->dateCondition('bpr.created_at', $filters, $params, 'nab');
                $vehicle = $this->vehicleConstraint('bpr.bus_id', $filters, $params, 'nab');
                $conditions = array_values(array_filter([$date, $vehicle]));

                if (!empty($filters['driver'])) {
                    $conditions[] = 'bpr.driver_id = :nab_drv';
                    $params[':nab_drv'] = (string)$filters['driver'];
                }

                $where = "WHERE bpr.status IN ('waiting', 'acknowledged')"
                    . ($conditions ? ' AND ' . implode(' AND ', $conditions) : '');

                $stmt = $this->db->prepare(
                    "SELECT
                        bpr.id,
                        bpr.bus_id,
                        bpr.problem_type,
                        bpr.trip_no,
                        bpr.driver_id,
                        bpr.status,
                        bpr.created_at,
                        bpr.replacement_bus_id,
                        d.full_name AS driver_name
                     FROM bus_problem_reports bpr
                     LEFT JOIN drivers d ON d.driver_id = bpr.driver_id
                     $where
                     ORDER BY bpr.created_at DESC
                     LIMIT 10"
                );
                $stmt->execute($params);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $items[] = [
                        'type'      => 'bus_problem',
                        'label'     => 'Bus problem: ' . (string)$row['problem_type'] . ' (' . (string)$row['bus_id'] . ')',
                        'count'     => 1,
                        'latest_at' => (string)$row['created_at'],
                        'link'      => '/admin/inspectors/?monitoring=dispatcher',
                        'details'   => [
                            'problem_type'       => (string)$row['problem_type'],
                            'bus_id'             => (string)$row['bus_id'],
                            'trip_no'            => (string)$row['trip_no'],
                            'driver_name'        => (string)($row['driver_name'] ?: $row['driver_id']),
                            'status'             => (string)$row['status'],
                            'reported_at'        => (string)$row['created_at'],
                            'replacement_bus_id' => (string)($row['replacement_bus_id'] ?? ''),
                        ],
                    ];
                }
            } catch (\Throwable $e) {
                // Rule unavailable on this schema.
            }
        }

        $total = 0;
        foreach ($items as $item) {
            $total += (int)$item['count'];
        }

        return ['total' => $total, 'items' => $items];
    }

    /* =====================================================
       RECENT SYSTEM ACTIVITY
       Latest audit_trail entries (login/logout included) with
       an active-sessions fallback when the trail is empty.
    ===================================================== */

    private function mapAuditRows(array $rows): array
    {
        $entries = [];

        foreach ($rows as $row) {
            $entries[] = [
                'name'     => (string)($row['username'] ?? ''),
                'role'     => (string)($row['user_role'] ?? ''),
                'activity' => trim((string)($row['action'] ?? '')) !== ''
                    ? (string)$row['action']
                    : (string)($row['module'] ?? ''),
                'detail'   => (string)($row['description'] ?? ''),
                'status'   => (string)($row['status'] ?? ''),
                'at'       => (string)($row['created_at'] ?? ''),
            ];
        }

        return $entries;
    }

    public function recentSystemActivity(int $limit = 8, array $filters = []): array
    {
        $limit = max(1, min(50, $limit));
        $entries = [];

        try {
            $params = [];
            $date = $this->dateCondition('at.created_at', $filters, $params, 'rsa');
            $where = $date ? "WHERE $date" : '';

            $stmt = $this->db->prepare(
                "SELECT username, user_role, module, action, description, status, created_at
                 FROM audit_trail at
                 $where
                 ORDER BY at.created_at DESC, at.id DESC
                 LIMIT $limit"
            );
            $stmt->execute($params);
            $entries = $this->mapAuditRows($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Throwable $e) {
            $entries = [];
        }

        if (empty($entries) && !empty($filters)) {
            try {
                $stmt = $this->db->prepare(
                    "SELECT username, user_role, module, action, description, status, created_at
                     FROM audit_trail
                     ORDER BY created_at DESC, id DESC
                     LIMIT $limit"
                );
                $stmt->execute();
                $entries = $this->mapAuditRows($stmt->fetchAll(PDO::FETCH_ASSOC));
            } catch (\Throwable $e) {
                $entries = [];
            }
        }

        if (!empty($entries)) {
            return $entries;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT u.username, u.role, u.full_name, u.first_name, u.last_name, s.last_activity
                 FROM sessions s
                 LEFT JOIN users u ON s.user_id = u.id
                 WHERE s.last_activity > UNIX_TIMESTAMP() - 900
                 ORDER BY s.last_activity DESC
                 LIMIT $limit"
            );
            $stmt->execute();

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = trim((string)($row['full_name'] ?? ''));

                if ($name === '') {
                    $name = trim(
                        (string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? '')
                    );
                }
                if ($name === '') {
                    $name = (string)($row['username'] ?? '');
                }

                $entries[] = [
                    'name'     => $name,
                    'role'     => (string)($row['role'] ?? ''),
                    'activity' => 'Active session',
                    'detail'   => '',
                    'status'   => 'Success',
                    'at'       => date('Y-m-d H:i:s', (int)($row['last_activity'] ?? time())),
                ];
            }
        } catch (\Throwable $e) {
            $entries = [];
        }

        return $entries;
    }

    /* =====================================================
       ENSURE STATS DATE INDEX
       Idempotently creates an index on the large history table's
       date column so the range queries above can use it.
       ===================================================== */

    public function ensureTripDateIndex(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `passenger_trip_history` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `trip_date` DATETIME NULL,
                    `fare` DECIMAL(10,2) DEFAULT 0.00,
                    `passenger_type` VARCHAR(50) DEFAULT NULL,
                    `payment_method` VARCHAR(50) DEFAULT NULL,
                    `bus_id` VARCHAR(50) DEFAULT NULL,
                    `driver_id` VARCHAR(50) DEFAULT NULL,
                    `drivers_full_name` VARCHAR(100) DEFAULT NULL,
                    `history_id` INT NULL,
                    INDEX `idx_pth_trip_date` (`trip_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `trip_operations_reports` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `trip_date` DATE NULL,
                    `bus_id` VARCHAR(50) DEFAULT NULL,
                    `driver_id` VARCHAR(50) DEFAULT NULL,
                    `fuel_cost` DECIMAL(10,2) DEFAULT 0.00,
                    `other_expenses` DECIMAL(10,2) DEFAULT 0.00,
                    INDEX `idx_tor_trip_date` (`trip_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `drivers` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `full_name` VARCHAR(100) DEFAULT NULL,
                    `status` VARCHAR(50) DEFAULT 'Active'
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `passengers` (
                    `id` VARCHAR(50) PRIMARY KEY,
                    `type` VARCHAR(50) DEFAULT 'Regular',
                    `name` VARCHAR(100) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $ptCols = [
                'passenger_id' => 'VARCHAR(50) DEFAULT NULL',
                'trip_no' => 'INT DEFAULT NULL',
                'passenger_type' => 'VARCHAR(50) DEFAULT NULL',
                'payment_method' => 'VARCHAR(50) DEFAULT NULL',
                'bus_id' => 'VARCHAR(50) DEFAULT NULL',
                'driver_id' => 'VARCHAR(50) DEFAULT NULL',
            ];
            foreach ($ptCols as $col => $type) {
                try {
                    @$this->db->exec("ALTER TABLE `passenger_trips` ADD COLUMN `$col` $type");
                } catch (\Throwable $e) {}
            }

            $pthCols = [
                'history_id' => 'INT DEFAULT NULL',
                'passenger_name' => 'VARCHAR(100) DEFAULT NULL',
                'drivers_full_name' => 'VARCHAR(100) DEFAULT NULL',
                'passenger_type' => 'VARCHAR(50) DEFAULT NULL',
                'payment_method' => 'VARCHAR(50) DEFAULT NULL',
                'bus_id' => 'VARCHAR(50) DEFAULT NULL',
                'driver_id' => 'VARCHAR(50) DEFAULT NULL',
                'fare' => 'DECIMAL(10,2) DEFAULT 0.00',
                'trip_date' => 'DATETIME NULL',
                'type' => 'VARCHAR(50) DEFAULT "passenger"'
            ];
            foreach ($pthCols as $col => $type) {
                try {
                    @$this->db->exec("ALTER TABLE `passenger_trip_history` ADD COLUMN `$col` $type");
                } catch (\Throwable $e) {}
            }

            $exists = $this->db->query(
                "SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'passenger_trip_history'
                   AND INDEX_NAME = 'idx_pth_trip_date'
                 LIMIT 1"
            )->fetchColumn();

            if (!$exists) {
                @$this->db->exec("CREATE INDEX idx_pth_trip_date ON passenger_trip_history (trip_date)");
            }
        } catch (\Throwable $e) {
            // Non-fatal: dashboard still works without the index.
        }
    }

    /* =====================================================
       PASSENGER FILTERS
    ===================================================== */

    private function buildPassengerFilters(
        array $filters,
        array &$params
    ): string {

        $conditions = [];

        if (!empty($filters['payment_method'])) {

            $conditions[] =
                "pt.payment_method = :payment_method";

            $params[':payment_method'] =
                $filters['payment_method'];
        }

        if (!empty($filters['payment_status'])) {

            $conditions[] =
                "pt.payment_status = :payment_status";

            $params[':payment_status'] =
                $filters['payment_status'];
        }

        if (!empty($filters['discount_status'])) {

            $conditions[] =
                "p.discount_status = :discount_status";

            $params[':discount_status'] =
                $filters['discount_status'];
        }

        if (!empty($filters['bus_id'])) {

            $conditions[] =
                "tor.bus_id = :bus_id";

            $params[':bus_id'] =
                $filters['bus_id'];
        }

        $dateSQL = $this->buildDateFilters(
            'pt.boarding_time',
            $filters,
            $params
        );

        if ($dateSQL) {
            $conditions[] = $dateSQL;
        }

        return $conditions
            ? ' WHERE ' . implode(' AND ', $conditions)
            : '';
    }

public function totalEarnings(
    array $filters = []
): float {

    $paramsTrips = [];
    $paramsHistory = [];

    /*
    |--------------------------------------------------------------------------
    | PASSENGER TRIPS FILTERS
    |--------------------------------------------------------------------------
    */

    $conditionsTrips = [];

    $dateTrips = $this->dateCondition(
        'pt.boarding_time',
        $filters,
        $paramsTrips,
        'pt'
    );

    if ($dateTrips) {
        $conditionsTrips[] = $dateTrips;
    }

    $vehicleTrips = $this->vehicleConstraint('pt.bus_no', $filters, $paramsTrips, 'te_pt');

    if ($vehicleTrips !== '') {
        $conditionsTrips[] = $vehicleTrips;
    }

    /*
    |--------------------------------------------------------------------------
    | OPTIONAL REMITTANCE FILTER
    |--------------------------------------------------------------------------
    */

    if (!empty($filters['remitted_only'])) {

        $conditionsTrips[] =

            "pt.remittance_status = 'Remitted'";
    }

    $whereTrips = '';

    if (!empty($conditionsTrips)) {

        $whereTrips =

            'WHERE '

            . implode(
                ' AND ',
                $conditionsTrips
            );
    }

    /*
    |--------------------------------------------------------------------------
    | PASSENGER TRIPS TOTAL
    |--------------------------------------------------------------------------
    */

    $sqlTrips = "

        SELECT

            IFNULL(SUM(pt.fare), 0)

        FROM passenger_trips pt

        $whereTrips

    ";

    $stmtTrips =
        $this->db->prepare($sqlTrips);

    $stmtTrips->execute($paramsTrips);

    $passengerTripsTotal =

        (float)$stmtTrips->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | PASSENGER HISTORY FILTERS
    |--------------------------------------------------------------------------
    */

    $conditionsHistory = [];

    $dateHistory = $this->dateCondition(
        'pth.trip_date',
        $filters,
        $paramsHistory,
        'pth'
    );

    if ($dateHistory) {
        $conditionsHistory[] = $dateHistory;
    }

    $vehicleHistory = $this->vehicleConstraint('pth.bus_number', $filters, $paramsHistory, 'te_pth');

    if ($vehicleHistory !== '') {
        $conditionsHistory[] = $vehicleHistory;
    }

    $whereHistory = '';

    if (!empty($conditionsHistory)) {

        $whereHistory =

            'WHERE '

            . implode(
                ' AND ',
                $conditionsHistory
            );
    }

    /*
    |--------------------------------------------------------------------------
    | PASSENGER HISTORY TOTAL
    |--------------------------------------------------------------------------
    */

    $sqlHistory = "

        SELECT

            IFNULL(SUM(pth.fare), 0)

        FROM passenger_trip_history pth

        $whereHistory

    ";

    $stmtHistory =
        $this->db->prepare($sqlHistory);

    $stmtHistory->execute($paramsHistory);

    $historyTotal =

        (float)$stmtHistory->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | FINAL TOTAL
    |--------------------------------------------------------------------------
    */

    return round(

        $passengerTripsTotal
        +
        $historyTotal,

        2
    );
}
    /* =====================================================
       TOTAL PASSENGERS
    ===================================================== */

public function totalPassengers(
    array $filters = []
): int {

    $paramsTrips = [];

    $paramsHistory = [];

    /*
    |--------------------------------------------------------------------------
    | PASSENGER TRIPS FILTERS
    |--------------------------------------------------------------------------
    */

    $conditionsTrips = [];

    $dateTrips = $this->dateCondition(
        'pt.boarding_time',
        $filters,
        $paramsTrips,
        'pt'
    );

    if ($dateTrips) {
        $conditionsTrips[] = $dateTrips;
    }

    $vehicleTrips = $this->vehicleConstraint('pt.bus_no', $filters, $paramsTrips, 'tp_pt');

    if ($vehicleTrips !== '') {
        $conditionsTrips[] = $vehicleTrips;
    }

    $whereTrips = '';

    if (!empty($conditionsTrips)) {

        $whereTrips =

            'WHERE '

            . implode(
                ' AND ',
                $conditionsTrips
            );
    }

    /*
    |--------------------------------------------------------------------------
    | LIVE PASSENGERS
    |--------------------------------------------------------------------------
    */

    $sqlTrips = "

        SELECT

            COUNT(DISTINCT pt.passenger_id)

        FROM passenger_trips pt

        $whereTrips

    ";

    $stmtTrips =
        $this->db->prepare($sqlTrips);

    $stmtTrips->execute($paramsTrips);

    $livePassengers =

        (int)$stmtTrips->fetchColumn();
    /*
    |--------------------------------------------------------------------------
    | HISTORY PASSENGERS (date-filtered via sargable range)
    |--------------------------------------------------------------------------
    */

    $dateHistory = $this->dateCondition('pth.trip_date', $filters, $paramsHistory, 'pth');
    $vehicleHistory = $this->vehicleConstraint('pth.bus_number', $filters, $paramsHistory, 'tp_pth');
    $historyConditions = array_values(array_filter([$dateHistory, $vehicleHistory]));
    $whereHistory = $historyConditions ? " WHERE " . implode(' AND ', $historyConditions) : "";

    $sqlHistory = "

        SELECT

            COUNT(DISTINCT pth.passenger_name)

        FROM passenger_trip_history pth

        $whereHistory

    ";

    $stmtHistory =
        $this->db->prepare($sqlHistory);

    $stmtHistory->execute($paramsHistory);

    $historyPassengers =

        (int)$stmtHistory->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | FINAL TOTAL
    |--------------------------------------------------------------------------
    */

    return

        $livePassengers

        +

        $historyPassengers;
}

    /* =====================================================
       TOTAL PASSENGER RECORDS (type = passenger only)
       Excludes Other/Baggage/Luggage (type = 'other')
    ===================================================== */

    public function totalPassengerRecords(array $filters = []): int
    {
        $paramsTrips = [];
        $paramsHistory = [];

        $conditionsTrips = [];
        $dateTrips = $this->dateCondition('pt.boarding_time', $filters, $paramsTrips, 'tpr_pt');
        if ($dateTrips) {
            $conditionsTrips[] = $dateTrips;
        }
        $vehicleTrips = $this->vehicleConstraint('pt.bus_no', $filters, $paramsTrips, 'tpr_pt');
        if ($vehicleTrips !== '') {
            $conditionsTrips[] = $vehicleTrips;
        }
        $conditionsTrips[] = "COALESCE(p.type, '') != 'other'";
        $whereTrips = 'WHERE ' . implode(' AND ', $conditionsTrips);

        $sqlTrips = "
            SELECT COUNT(*)
            FROM passenger_trips pt
            LEFT JOIN passengers p ON p.id = pt.passenger_id
            $whereTrips
        ";
        $stmtTrips = $this->db->prepare($sqlTrips);
        $stmtTrips->execute($paramsTrips);
        $livePassengers = (int)$stmtTrips->fetchColumn();

        $conditionsHistory = [];
        $dateHistory = $this->dateCondition('pth.trip_date', $filters, $paramsHistory, 'tpr_pth');
        if ($dateHistory) {
            $conditionsHistory[] = $dateHistory;
        }
        $vehicleHistory = $this->vehicleConstraint('pth.bus_number', $filters, $paramsHistory, 'tpr_pth');
        if ($vehicleHistory !== '') {
            $conditionsHistory[] = $vehicleHistory;
        }
        $conditionsHistory[] = "COALESCE(pth.type, '') = 'passenger'";
        $whereHistory = 'WHERE ' . implode(' AND ', $conditionsHistory);

        $sqlHistory = "
            SELECT COUNT(*)
            FROM passenger_trip_history pth
            $whereHistory
        ";
        $stmtHistory = $this->db->prepare($sqlHistory);
        $stmtHistory->execute($paramsHistory);
        $historyPassengers = (int)$stmtHistory->fetchColumn();

        return $livePassengers + $historyPassengers;
    }

    /* =====================================================
       PASSENGER TYPE BREAKDOWN
       Aggregates passenger counts by category across BOTH the
       live (passenger_trips) and archived (passenger_trip_history)
       tables, applying the EXACT same filters used by
       totalPassengerRecords() so the per-type totals reconcile
       with the "Total Passengers" card.

       Live category  : passengers.discount_type (NULL/'' -> 'Regular')
       History category: passenger_trip_history.passenger_type
                        (NULL/'' -> 'Uncategorized')
       Baggage (type = 'other') is excluded, matching the card.
       ===================================================== */

    public function passengerTypeBreakdown(array $filters = []): array
    {
        $params = [];

        $dateTrips = $this->dateCondition('pt.boarding_time', $filters, $params, 'pt');
        $whereTrips = $dateTrips ? "AND $dateTrips" : "";

        $dateHist = $this->dateCondition('pth.trip_date', $filters, $params, 'pth');
        $whereHist = $dateHist ? "AND $dateHist" : "";

        $sql = "
            SELECT label, SUM(c) AS total FROM (
                SELECT
                    CASE
                        WHEN p.discount_type IS NULL OR TRIM(p.discount_type) = ''
                        THEN 'Regular'
                        ELSE p.discount_type
                    END AS label,
                    COUNT(*) AS c
                FROM passenger_trips pt
                LEFT JOIN passengers p ON p.id = pt.passenger_id
                WHERE COALESCE(p.type, '') != 'other' $whereTrips
                GROUP BY label

                UNION ALL

                SELECT
                    CASE
                        WHEN pth.passenger_type IS NULL OR TRIM(pth.passenger_type) = ''
                        THEN 'Uncategorized'
                        ELSE pth.passenger_type
                    END AS label,
                    COUNT(*) AS c
                FROM passenger_trip_history pth
                WHERE COALESCE(pth.type, '') = 'passenger' $whereHist
                GROUP BY label
            ) combined
            GROUP BY label
            ORDER BY total DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       TOTAL BUSES (excludes Archived)
       Used for fleet-utilization math.
       ===================================================== */

    public function totalBusesCount(): int
    {
        $sql = "SELECT COUNT(*) FROM buses WHERE COALESCE(status, '') != 'Archived'";
        return (int)$this->db->query($sql)->fetchColumn();
    }

    /* =====================================================
       TOTAL TRIPS
       ===================================================== */

public function totalTrips(
    array $filters = []
): int {

    $params = [];

    $sql = "
        SELECT COUNT(DISTINCT trip_key) FROM (
            SELECT CONCAT('tor-', tor.id) AS trip_key
            FROM trip_operations_reports tor
    ";

    $dateSQL = $this->dateCondition(
        'tor.trip_date',
        $filters,
        $params,
        'tor'
    );
    $vehicleTOR = $this->vehicleConstraint('tor.bus_id', $filters, $params, 'tt_tor');
    $conditionsTOR = array_values(array_filter([$dateSQL, $vehicleTOR]));

    if (!empty($conditionsTOR)) {
        $sql .= " WHERE " . implode(' AND ', $conditionsTOR);
    }

    $sql .= "
            UNION ALL
            SELECT CONCAT('pt-', pt.trip_no)
            FROM passenger_trips pt
    ";

    $paramsPT = [];
    $datePT = $this->dateCondition(
        'pt.boarding_time',
        $filters,
        $paramsPT,
        'pt'
    );
    $vehiclePT = $this->vehicleConstraint('pt.bus_no', $filters, $paramsPT, 'tt_pt');
    $conditionsPT = array_values(array_filter([$datePT, $vehiclePT]));

    if (!empty($conditionsPT)) {
        $sql .= " WHERE " . implode(' AND ', $conditionsPT);
    }

    $sql .= "
            UNION ALL
            SELECT CONCAT('pth-', COALESCE(NULLIF(pth.trip_id, ''), pth.history_id))
            FROM passenger_trip_history pth
    ";

    $paramsPTH = [];
    $datePTH = $this->dateCondition(
        'pth.trip_date',
        $filters,
        $paramsPTH,
        'pth'
    );
    $vehiclePTH = $this->vehicleConstraint('pth.bus_number', $filters, $paramsPTH, 'tt_pth');
    $conditionsPTH = array_values(array_filter([$datePTH, $vehiclePTH]));

    if (!empty($conditionsPTH)) {
        $sql .= " WHERE " . implode(' AND ', $conditionsPTH);
    }

    $sql .= "
        ) combined
    ";

    $allParams = array_merge($params, $paramsPT, $paramsPTH);

    $stmt = $this->db->prepare($sql);
    $stmt->execute($allParams);

    return (int)$stmt->fetchColumn();
}

    /* =====================================================
       ACTIVE BUSES
    ===================================================== */

    public function activeBuses(): int
    {
        $sql = "
            SELECT COUNT(*)

            FROM buses

            WHERE status = 'Active'
        ";

        return (int)$this->db
            ->query($sql)
            ->fetchColumn();
    }

    /* =====================================================
       ACTIVE DRIVERS
    ===================================================== */

    public function activeDrivers(array $filters = []): int
    {
        $params = [];
        $datePT = $this->dateCondition('pt.boarding_time', $filters, $params, 'adpt');
        $datePTH = $this->dateCondition('pth.trip_date', $filters, $params, 'adpth');

        $ptWhere = $datePT ? "AND $datePT" : '';
        $pthWhere = $datePTH ? "AND $datePTH" : '';

        $sql = "
            SELECT COUNT(DISTINCT d.id)
            FROM drivers d
            WHERE (
                EXISTS (
                    SELECT 1
                    FROM passenger_trips pt
                    WHERE pt.driver_id = d.driver_id
                    $ptWhere
                )
                OR EXISTS (
                    SELECT 1
                    FROM passenger_trip_history pth
                    WHERE REPLACE(LOWER(TRIM(pth.drivers_full_name)), ' ', '')
                        = REPLACE(LOWER(TRIM(d.full_name)), ' ', '')
                    $pthWhere
                )
            )
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /* =====================================================
       TOTAL NET INCOME
    ===================================================== */

public function totalNetIncome(
    array $filters = []
): float {

    $earnings =
        $this->totalEarnings($filters);

    $fuel =
        $this->totalFuelCost($filters);

    $other =
        $this->totalOtherExpenses($filters);

    return round(

        $earnings
        -
        ($fuel + $other),

        2
    );
}

    /* =====================================================
       TOTAL FUEL COST
    ===================================================== */

    public function totalFuelCost(array $filters = []): float
    {
        $params = [];

        $dateSQL = $this->dateCondition(
            'tor.trip_date',
            $filters,
            $params,
            'tor'
        );

        $where = $dateSQL
            ? "WHERE $dateSQL"
            : "";

        $sql = "
            SELECT IFNULL(SUM(tor.fuel_cost), 0)

            FROM trip_operations_reports tor

            $where
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        return (float)$stmt->fetchColumn();
    }

    /* =====================================================
       TOTAL OTHER EXPENSES
    ===================================================== */

    public function totalOtherExpenses(array $filters = []): float
    {
        $params = [];

        $dateSQL = $this->dateCondition(
            'tor.trip_date',
            $filters,
            $params,
            'tor'
        );

        $where = $dateSQL
            ? "WHERE $dateSQL"
            : "";

        $sql = "
            SELECT IFNULL(SUM(tor.other_expenses), 0)

            FROM trip_operations_reports tor

            $where
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        return (float)$stmt->fetchColumn();
    }

    /* =====================================================
       TOTAL FUEL COST FROM fuel_logs
       Replaces trip_operations_reports.fuel_cost. Sums the
       fuel_logs.cost column, filtered by created_at over the
       same sargable date range used everywhere else.
    ===================================================== */

    public function totalFuelFromLogs(array $filters = []): float
    {
        $hasRefuels = false;
        try {
            $chk = $this->db->query("SHOW TABLES LIKE 'fuel_refuels'");
            $hasRefuels = ($chk && $chk->rowCount() > 0);
        } catch (\Throwable $e) {}

        if ($hasRefuels) {
            $params = [];
            $dateSQL = $this->dateCondition('fr.created_at', $filters, $params, 'fr');
            $vehicle = $this->vehicleConstraint('fr.bus_id', $filters, $params, 'fr');
            $conditions = array_values(array_filter([$dateSQL, $vehicle]));
            $where = $conditions ? "WHERE " . implode(' AND ', $conditions) : "";
            $sql = "SELECT IFNULL(SUM(fr.total_cost), 0) FROM fuel_refuels fr $where";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $cost = (float)$stmt->fetchColumn();
            if ($cost > 0) {
                return $cost;
            }
        }

        $params = [];

        $dateSQL = $this->dateCondition(
            'fl.created_at',
            $filters,
            $params,
            'fl'
        );
        $vehicle = $this->vehicleConstraint('fl.bus_id', $filters, $params, 'fl');
        $conditions = array_values(array_filter([$dateSQL, $vehicle]));

        $where = $conditions
            ? "WHERE " . implode(' AND ', $conditions)
            : "";

        $sql = "
            SELECT IFNULL(SUM(fl.cost), 0)

            FROM fuel_logs fl

            $where
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        return (float)$stmt->fetchColumn();
    }

    /* =====================================================
       AVG TRIP
    ===================================================== */

public function avgTrip(
    array $filters = []
): float {

    $earnings =
        $this->totalEarnings($filters);

    $trips =
        $this->totalTrips($filters);

    if ($trips <= 0) {
        return 0;
    }

    return round(
        $earnings / $trips,
        2
    );
}

/* =====================================================
   TOP PASSENGERS
===================================================== */

public function topPassengers(
    array $filters = []
): array {

    $params = [];

    $filterSQL =
        $this->buildPassengerFilters($filters, $params);

    $sql = "
        SELECT
            p.full_name,
            p.email,

            COUNT(pt.id) AS total_trips,

            SUM(pt.fare) AS total_spent

        FROM passenger_trips pt

        LEFT JOIN passengers p
            ON p.id = pt.passenger_id

        $filterSQL

        GROUP BY p.id

        ORDER BY total_spent DESC

        LIMIT 10
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    /* =====================================================
       RECENT PASSENGER HISTORY
    ===================================================== */

    public function recentPassengerHistory(
        int $limit = 10
    ): array {

        $sql = "
            SELECT
                ph.history_id,
                ph.passenger_name,
                ph.passenger_type,
                ph.boarding_point,
                ph.destination,
                ph.bus_number,
                ph.trip_date,
                ph.payment_method,
                ph.fare

            FROM passenger_trip_history ph

            ORDER BY ph.trip_date DESC

            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       RECENT TRIPS
    ===================================================== */

    public function recentTrips(
        int $limit = 10
    ): array {

        $sql = "
            SELECT
                trip_group_code,
                route_id,
                bus_id,
                driver_id,
                inspector_id,
                trip_date,
                passenger_count,
                total_revenue,
                fuel_cost,
                other_expenses,
                net_income

            FROM trip_operations_reports

            ORDER BY created_at DESC

            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(
            ':limit',
            $limit,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
/* =====================================================
   TOP DRIVERS
===================================================== */

public function topDrivers(
    array $filters = []
): array {

    $params = [];

    $wherePT  = '';
    $wherePTH = '';

    if (!empty($filters['month'])) {
        $wherePT  = "WHERE MONTH(pt.boarding_time) = :month";
        $wherePTH = "WHERE MONTH(pth.trip_date) = :month2";
        $params[':month']  = $filters['month'];
        $params[':month2'] = $filters['month'];
    }

    if (!empty($filters['year'])) {
        $yearPT  = "YEAR(pt.boarding_time) = :year";
        $yearPTH = "YEAR(pth.trip_date) = :year2";
        if ($wherePT) {
            $wherePT .= " AND $yearPT";
        } else {
            $wherePT = "WHERE $yearPT";
        }
        if ($wherePTH) {
            $wherePTH .= " AND $yearPTH";
        } else {
            $wherePTH = "WHERE $yearPTH";
        }
        $params[':year']  = $filters['year'];
        $params[':year2'] = $filters['year'];
    }

    $sql = "

        SELECT

            d.full_name AS name,

            d.driver_id,

            COALESCE(SUM(total.fare), 0) AS earnings,

            COUNT(DISTINCT total.passenger) AS passengers,

            SUM(total.trip_count) AS trips

        FROM drivers d

        LEFT JOIN (

            SELECT
                d2.driver_id AS match_id,
                pt.fare AS fare,
                pt.passenger_id AS passenger,
                1 AS trip_count
            FROM passenger_trips pt
            INNER JOIN drivers d2 ON pt.driver_id = d2.driver_id
            $wherePT

            UNION ALL

            SELECT
                d2.driver_id AS match_id,
                pth.fare AS fare,
                pth.passenger_name AS passenger,
                1 AS trip_count
            FROM passenger_trip_history pth
            INNER JOIN drivers d2
                ON REPLACE(
                    LOWER(TRIM(pth.drivers_full_name)),
                    ' ',
                    ''
                )
                =
                REPLACE(
                    LOWER(TRIM(d2.full_name)),
                    ' ',
                    ''
                )
            $wherePTH

        ) total ON total.match_id = d.driver_id

        WHERE d.full_name IS NOT NULL
        AND TRIM(d.full_name) != ''

        GROUP BY d.id, d.driver_id, d.full_name

        ORDER BY earnings DESC

        LIMIT 5

    ";

    $stmt =
        $this->db->prepare($sql);

    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    /* =====================================================
       PAYMENT METHOD STATS
    ===================================================== */

    public function paymentMethodStats(): array
    {
        $sql = "
            SELECT payment_method, SUM(total) AS total
            FROM (
                SELECT UPPER(COALESCE(NULLIF(payment_method,''), 'CASH')) AS payment_method, COUNT(*) AS total
                FROM passenger_trips
                GROUP BY payment_method
                UNION ALL
                SELECT UPPER(COALESCE(NULLIF(payment_method,''), 'CASH')) AS payment_method, COUNT(*) AS total
                FROM passenger_trip_history
                GROUP BY payment_method
            ) combined
            GROUP BY payment_method
            ORDER BY total DESC
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       DISCOUNT STATS
    ===================================================== */

    public function discountStats(): array
    {
        $sql = "
            SELECT
                discount_status,
                COUNT(*) AS total

            FROM passengers

            GROUP BY discount_status
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       TODAY EARNINGS
    ===================================================== */

    public function todayEarnings(): float
    {
        $sql = "
            SELECT IFNULL(SUM(fare),0)
            FROM (
                SELECT fare FROM passenger_trips WHERE DATE(boarding_time) = CURDATE()
                UNION ALL
                SELECT fare FROM passenger_trip_history WHERE DATE(trip_date) = CURDATE()
            ) combined
        ";

        return (float)$this->db
            ->query($sql)
            ->fetchColumn();
    }

    /* =====================================================
       TODAY PASSENGERS
    ===================================================== */

    public function todayPassengers(): int
    {
        $sql = "
            SELECT COUNT(*) FROM (
                SELECT id FROM passenger_trips WHERE DATE(boarding_time) = CURDATE()
                UNION ALL
                SELECT id FROM passenger_trip_history WHERE DATE(trip_date) = CURDATE()
            ) combined
        ";

        return (int)$this->db
            ->query($sql)
            ->fetchColumn();
    }

    /* =====================================================
       GCASH TOTAL
    ===================================================== */

    public function gcashTotal(): float
    {
        $sql = "
            SELECT IFNULL(SUM(amount),0)
            FROM (
                SELECT gcash_amount AS amount FROM passenger_trips
                UNION ALL
                SELECT fare AS amount FROM passenger_trip_history WHERE LOWER(COALESCE(payment_method,'')) = 'gcash'
            ) combined
        ";

        return (float)$this->db
            ->query($sql)
            ->fetchColumn();
    }

    /* =====================================================
       CASH TOTAL
    ===================================================== */

    public function cashTotal(): float
    {
        $sql = "
            SELECT IFNULL(SUM(amount),0)
            FROM (
                SELECT cash_amount AS amount FROM passenger_trips
                UNION ALL
                SELECT fare AS amount FROM passenger_trip_history WHERE LOWER(COALESCE(payment_method,'')) IN ('cash','')
            ) combined
        ";

        return (float)$this->db
            ->query($sql)
            ->fetchColumn();
    }

    /* =====================================================
       REVENUE CHART
    ===================================================== */

    public function revenueChart(): array
    {
        $sql = "
            SELECT trip_day, SUM(revenue) AS revenue
            FROM (
                SELECT DATE(boarding_time) AS trip_day, SUM(fare) AS revenue
                FROM passenger_trips
                GROUP BY DATE(boarding_time)
                UNION ALL
                SELECT DATE(trip_date) AS trip_day, SUM(fare) AS revenue
                FROM passenger_trip_history
                GROUP BY DATE(trip_date)
            ) combined
            GROUP BY trip_day
            ORDER BY trip_day ASC
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       PASSENGER TRIP HISTORY COUNT
    ===================================================== */

    public function totalPassengerTrips(array $filters = []): int
    {
        $params = [];
        $dateSQL = $this->dateCondition('pth.trip_date', $filters, $params, 'pth');
        $where = $dateSQL ? " WHERE " . $dateSQL : "";

        $sql = "
            SELECT COUNT(*)
            FROM passenger_trip_history pth
            $where
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /* =====================================================
       PASSENGER TRIP HISTORY TREND
    ===================================================== */

    public function passengerTripTrend(array $filters = []): array
    {
        $params = [];
        $conditions = [];

        $dateSQL = $this->dateCondition('trip_date', $filters, $params, 'pth');
        if ($dateSQL) {
            $conditions[] = $dateSQL;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT
                DATE(trip_date) AS trip_day,
                COUNT(*) AS trip_count,
                SUM(fare) AS total_fare
            FROM passenger_trip_history pth
            $where
            GROUP BY DATE(trip_date)
            ORDER BY trip_day ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       FUEL ANALYTICS - SUMMARY
       Returns aggregate fuel statistics from fuel_logs using
       the same sargable date filter builder as the dashboard.
       avg_cost_per_trip is total_cost / distinct trip count.
    ===================================================== */

    public function fuelAnalyticsSummary(array $filters = []): array
    {
        try {
            $params = [];

            $dateSQL = $this->dateCondition(
                'fl.created_at',
                $filters,
                $params,
                'fl'
            );
            $vehicle = $this->vehicleConstraint('fl.bus_id', $filters, $params, 'fl');
            $conditions = array_values(array_filter([$dateSQL, $vehicle]));

            $where = $conditions
                ? "WHERE " . implode(' AND ', $conditions)
                : "";

            $sql = "
                SELECT
                    IFNULL(AVG(CASE WHEN fl.kpl IS NOT NULL AND fl.kpl > 0 THEN fl.kpl END), 0) AS avg_kpl,
                    COALESCE(SUM(
                        CASE
                            WHEN fl.liters > 0 THEN fl.liters
                            WHEN fl.ending_odo IS NOT NULL AND fl.starting_odo IS NOT NULL AND fl.ending_odo > fl.starting_odo AND fl.kpl > 0
                                THEN ((fl.ending_odo - fl.starting_odo) / fl.kpl)
                            ELSE 0
                        END
                    ), 0) AS total_liters,
                    COALESCE(SUM(CASE WHEN fl.cost > 0 THEN fl.cost ELSE 0 END), 0) AS legacy_cost,
                    COALESCE(SUM(
                        CASE
                            WHEN fl.liters > 0 THEN 0
                            WHEN fl.ending_odo IS NOT NULL AND fl.starting_odo IS NOT NULL AND fl.ending_odo > fl.starting_odo AND fl.kpl > 0
                                THEN ((fl.ending_odo - fl.starting_odo) / fl.kpl) * COALESCE(NULLIF(fl.price_per_liter, 0), 65.55)
                            ELSE 0
                        END
                    ), 0) AS estimated_cost,
                    COUNT(CASE WHEN fl.liters > 0 THEN 1 END) AS refills,
                    COUNT(DISTINCT CASE
                        WHEN fl.trip_id IS NOT NULL AND fl.trip_id <> '' AND (
                            fl.cost > 0 OR
                            (fl.ending_odo IS NOT NULL AND fl.starting_odo IS NOT NULL AND fl.ending_odo > fl.starting_odo AND fl.kpl > 0)
                        ) THEN fl.trip_id
                    END) AS trip_count,
                    COALESCE(SUM(CASE
                        WHEN fl.liters > 0 AND fl.ending_odo IS NOT NULL AND fl.starting_odo IS NOT NULL AND fl.ending_odo > fl.starting_odo
                            THEN (fl.ending_odo - fl.starting_odo)
                        ELSE 0
                    END), 0) AS actual_odo_distance,
                    COALESCE(SUM(CASE WHEN fl.liters > 0 THEN fl.liters ELSE 0 END), 0) AS actual_liters,
                    COALESCE(SUM(CASE
                        WHEN fl.ending_odo IS NOT NULL AND fl.starting_odo IS NOT NULL AND fl.ending_odo > fl.starting_odo
                            THEN (fl.ending_odo - fl.starting_odo)
                        ELSE 0
                    END), 0) AS odo_distance
                FROM fuel_logs fl
                $where
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $totalLiters   = (float)($row['total_liters'] ?? 0);
            $legacyCost    = (float)($row['legacy_cost'] ?? 0);
            $estimatedCost = (float)($row['estimated_cost'] ?? 0);
            $tripCount     = (int)($row['trip_count'] ?? 0);
            $legacyRefills = (int)($row['refills'] ?? 0);

            /* Actual refuel transactions live in fuel_refuels for generator-produced
               history; fuel_logs carries the per-trip odometer/KPL rows (zero cost). */
            $refuelLiters = 0.0;
            $refuelCost = 0.0;
            $refuelEvents = 0;
            try {
                $chk = $this->db->query("SHOW TABLES LIKE 'fuel_refuels'");
                if ($chk && $chk->fetchColumn() !== false) {
                    $refParams = [];
                    $refDate = $this->dateCondition('fr.created_at', $filters, $refParams, 'fr');
                    $refVehicle = $this->vehicleConstraint('fr.bus_id', $filters, $refParams, 'fr');
                    $refConditions = array_values(array_filter([$refDate, $refVehicle]));
                    $refWhere = $refConditions ? "WHERE " . implode(' AND ', $refConditions) : "";
                    $stmt = $this->db->prepare("
                        SELECT
                            COALESCE(SUM(fr.liters), 0) AS liters,
                            COALESCE(SUM(fr.total_cost), 0) AS cost,
                            COUNT(*) AS events
                        FROM fuel_refuels fr
                        $refWhere
                    ");
                    $stmt->execute($refParams);
                    $refRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    $refuelLiters = (float)($refRow['liters'] ?? 0);
                    $refuelCost   = (float)($refRow['cost'] ?? 0);
                    $refuelEvents = (int)($refRow['events'] ?? 0);
                }
            } catch (\Throwable $e) {
                // fuel_refuels unavailable: keep the fuel_logs figures.
            }

            $actualCost    = round($legacyCost + $refuelCost, 2);
            $totalCost     = $actualCost > 0 ? $actualCost : round($estimatedCost, 2);

            $avgLitersTrip = ($tripCount > 0 && $totalLiters > 0) ? round($totalLiters / $tripCount, 2) : null;
            $avgCostTrip   = ($tripCount > 0 && $totalCost > 0) ? round($totalCost / $tripCount, 2) : null;

            /* Fleet consumption in km/L: actual odometer distance divided by
               the actual liters consumed for the same records (fuel_logs
               liters when recorded, else fuel_refuels purchases); fall back
               to the recorded per-trip KPL average only when no actual
               odometer/liters pair exists. */
            $odoDistance        = (float)($row['odo_distance'] ?? 0);
            $actualOdoDistance  = (float)($row['actual_odo_distance'] ?? 0);
            $actualOdoLiters    = (float)($row['actual_liters'] ?? 0);
            $avgKpl             = round((float)($row['avg_kpl'] ?? 0), 2);
            if ($actualOdoDistance > 0 && $actualOdoLiters > 0) {
                $avgKpl = round($actualOdoDistance / $actualOdoLiters, 2);
            } elseif ($odoDistance > 0 && $refuelLiters > 0) {
                $avgKpl = round($odoDistance / $refuelLiters, 2);
            }

            return [
                'avg_kpl'                    => $avgKpl,
                'avg_l_per_100km'            => $avgKpl > 0 ? round(100 / $avgKpl, 2) : null,
                'total_liters'               => round($totalLiters, 2),
                'total_fuel_consumed'        => round($totalLiters, 2),
                'avg_fuel_consumed_per_trip' => $avgLitersTrip,
                'avg_liters_per_trip'        => $avgLitersTrip,
                'total_cost'                 => $totalCost,
                'total_fuel_cost'            => $totalCost,
                'total_estimated_fuel_cost'  => round($estimatedCost, 2),
                'total_actual_fuel_cost'     => $actualCost,
                'actual_refuel_liters'       => round($refuelLiters, 2),
                'avg_cost_per_trip'          => $avgCostTrip,
                'refills'                    => $legacyRefills + $refuelEvents,
                'refuel_events'              => $refuelEvents,
                'trip_count'                 => $tripCount
            ];
        } catch (\Throwable $e) {
            error_log("DashboardModel fuelAnalyticsSummary error: " . $e->getMessage());
            return [
                'avg_kpl'                    => 0.0,
                'avg_l_per_100km'            => null,
                'total_liters'               => 0.0,
                'total_fuel_consumed'        => 0.0,
                'avg_fuel_consumed_per_trip' => null,
                'avg_liters_per_trip'        => null,
                'total_cost'                 => 0.0,
                'total_fuel_cost'            => 0.0,
                'total_estimated_fuel_cost'  => 0.0,
                'total_actual_fuel_cost'     => 0.0,
                'actual_refuel_liters'       => 0.0,
                'avg_cost_per_trip'          => null,
                'refills'                    => 0,
                'refuel_events'              => 0,
                'trip_count'                 => 0
            ];
        }
    }

    /* =====================================================
       FUEL ANALYTICS - PER BUS
       Per-bus fuel performance from fuel_logs, sorted by total
       fuel cost descending. avg_kpl is distance / liters.
    ===================================================== */

    public function fuelAnalyticsPerBus(array $filters = []): array
    {
        $params = [];

        $dateSQL = $this->dateCondition(
            'fl.created_at',
            $filters,
            $params,
            'fl'
        );
        $vehicle = $this->vehicleConstraint('fl.bus_id', $filters, $params, 'fl');
        $conditions = array_values(array_filter([$dateSQL, $vehicle]));

        $where = $conditions
            ? "WHERE " . implode(' AND ', $conditions)
            : "";

        $sql = "
            SELECT
                fl.bus_id,
                COALESCE(SUM(fl.distance_traveled), 0) AS distance_traveled,
                COALESCE(SUM(fl.liters), 0) AS liters,
                COALESCE(SUM(fl.cost), 0) AS cost
            FROM fuel_logs fl
            $where
            GROUP BY fl.bus_id
            ORDER BY cost DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        /* Actual refuels (fuel_refuels) per bus for generator-produced history. */
        $refuelByBus = [];
        try {
            $chk = $this->db->query("SHOW TABLES LIKE 'fuel_refuels'");
            if ($chk && $chk->fetchColumn() !== false) {
                $refParams = [];
                $refDate = $this->dateCondition('fr.created_at', $filters, $refParams, 'fr');
                $refVehicle = $this->vehicleConstraint('fr.bus_id', $filters, $refParams, 'fr');
                $refConditions = array_values(array_filter([$refDate, $refVehicle]));
                $refWhere = $refConditions ? "WHERE " . implode(' AND ', $refConditions) : "";
                $stmt = $this->db->prepare("
                    SELECT fr.bus_id,
                           COALESCE(SUM(fr.liters), 0) AS liters,
                           COALESCE(SUM(fr.total_cost), 0) AS cost
                    FROM fuel_refuels fr
                    $refWhere
                    GROUP BY fr.bus_id
                ");
                $stmt->execute($refParams);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $refRow) {
                    $refuelByBus[(string)$refRow['bus_id']] = [
                        'liters' => (float)$refRow['liters'],
                        'cost'   => (float)$refRow['cost'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            // fuel_refuels unavailable: fuel_logs figures only.
        }

        $out  = [];

        foreach ($rows as $r) {
            $busId    = (string)$r['bus_id'];
            $liters   = (float)($r['liters'] ?? 0);
            $distance = (float)($r['distance_traveled'] ?? 0);
            $cost     = (float)($r['cost'] ?? 0);
            $refuel   = $refuelByBus[$busId] ?? null;

            if ($refuel !== null) {
                if ($liters <= 0) {
                    $liters = $refuel['liters'];
                }
                $cost += $refuel['cost'];
            }

            $out[] = [
                'bus_id'           => $busId,
                'distance_traveled'=> round($distance, 2),
                'liters'           => round($liters, 2),
                'avg_kpl'          => round($liters > 0 ? $distance / $liters : 0, 2),
                'cost'             => round($cost, 2)
            ];
        }

        usort($out, static fn($a, $b) => $b['cost'] <=> $a['cost']);

        return $out;
    }
}
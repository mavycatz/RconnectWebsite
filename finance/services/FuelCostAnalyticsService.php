<?php

namespace Finance\Services;

use PDO;
use App\Core\Database;

require_once __DIR__ . '/DateFilterService.php';

class FuelCostAnalyticsService
{
    private ?array $busesCols = null;
    private ?array $fuelCols = null;
    private ?float $dieselPrice = null;

    /**
     * Inspect buses table columns to handle schema variations (id vs bus_id, bus_label vs plate_number).
     */
    private function getBusesCols(): array
    {
        if ($this->busesCols !== null) {
            return $this->busesCols;
        }
        $cols = [];
        $db = Database::tryGet();
        if ($db === null) {
            $this->busesCols = $cols;
            return $cols;
        }
        try {
            $stmt = $db->query("SHOW COLUMNS FROM buses");
            if ($stmt) {
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols[strtolower((string) $r['Field'])] = true;
                }
            }
        } catch (\Throwable $e) {}
        $this->busesCols = $cols;
        return $cols;
    }

    /**
     * Inspect fuel_logs table columns to handle column name variations.
     */
    private function getFuelCols(): array
    {
        if ($this->fuelCols !== null) {
            return $this->fuelCols;
        }
        $cols = [];
        $db = Database::tryGet();
        if ($db === null) {
            $this->fuelCols = $cols;
            return $cols;
        }
        try {
            $stmt = $db->query("SHOW COLUMNS FROM fuel_logs");
            if ($stmt) {
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols[strtolower((string) $r['Field'])] = true;
                }
            }
        } catch (\Throwable $e) {}
        $this->fuelCols = $cols;
        return $cols;
    }

    /**
     * Get current or latest recorded diesel price.
     */
    public function getDieselPrice(): float
    {
        if ($this->dieselPrice !== null) {
            return $this->dieselPrice;
        }
        $price = 65.55;
        $db = Database::tryGet();
        if ($db === null) {
            $this->dieselPrice = $price;
            return $price;
        }
        try {
            $stmt = $db->query(
                "SELECT price_per_liter FROM fuel_prices WHERE fuel_type LIKE '%diesel%' ORDER BY id DESC LIMIT 1"
            );
            if ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                if ((float) $row['price_per_liter'] > 0) {
                    $price = (float) $row['price_per_liter'];
                }
            }
        } catch (\Throwable $e) {}
        $this->dieselPrice = $price;
        return $price;
    }

    /**
     * Build the WHERE clause for fuel log filters.
     *
     * Every referenced column is gated on SHOW COLUMNS inspection so filters
     * degrade gracefully (never throw) on schemas that lack optional columns.
     */
    public function applyFilters(array $filters): array
    {
        $where = [];
        $params = [];
        $fuelCols = $this->getFuelCols();

        if (!empty($filters['bus']) && isset($fuelCols['bus_id'])) {
            $where[] = 'fl.bus_id = :bus';
            $params[':bus'] = (string) $filters['bus'];
        }

        if (!empty($filters['driver']) && isset($fuelCols['driver_id'])) {
            $where[] = 'fl.driver_id = :driver';
            $params[':driver'] = (string) $filters['driver'];
        }

        if (!empty($filters['from']) && isset($fuelCols['created_at'])) {
            $where[] = 'fl.created_at >= :from';
            $params[':from'] = date('Y-m-d 00:00:00', strtotime((string) $filters['from']));
        }

        if (!empty($filters['to']) && isset($fuelCols['created_at'])) {
            $where[] = 'fl.created_at <= :to';
            $params[':to'] = date('Y-m-d 23:59:59', strtotime((string) $filters['to']));
        }

        if (!empty($filters['search'])) {
            $searchParts = [];
            if (isset($fuelCols['fuel_log_id'])) {
                $searchParts[] = 'fl.fuel_log_id LIKE :search';
            }
            if (isset($fuelCols['bus_id'])) {
                $searchParts[] = 'fl.bus_id LIKE :search';
            }
            if (isset($fuelCols['driver_id'])) {
                $searchParts[] = 'fl.driver_id LIKE :search';
            }
            if (!empty($searchParts)) {
                $where[] = '(' . implode(' OR ', $searchParts) . ')';
                $params[':search'] = '%' . (string) $filters['search'] . '%';
            }
        }

        return [
            count($where) ? 'WHERE ' . implode(' AND ', $where) : '',
            $params,
        ];
    }

    /**
     * Resolve created_at bounds from the shared Finance period model.
     */
    public function resolvePeriod(array $filters): array
    {
        $resolved = DateFilterService::normalizeRequest($filters);

        return [$resolved['start'], $resolved['end']];
    }

    /**
     * Format a bus into a clean, human-friendly display label.
     *
     * Uses the actual name/number stored in the database when available and
     * strips redundant "Bus #"/"BUS-" prefixes so codes like "BUS-1009" render
     * as "Bus-1009". Only affects the display label; the underlying value
     * (bus_id) is never modified.
     */
    public function busDisplay(?string $busId, ?string $label = null): string
    {
        $s = trim((string) (($label !== null && $label !== '') ? $label : $busId));
        $s = trim((string) preg_replace('/\s+/', ' ', $s));
        if ($s === '') {
            return 'Unassigned Bus';
        }
        $s = (string) preg_replace('/^Bus\s*#?\s*[-: ]*/i', '', $s);
        if ($s === '') {
            return 'Unassigned Bus';
        }
        $s = (string) preg_replace('/^BUS/', 'Bus', $s);
        if (preg_match('/\bBus\b/i', $s) === 1) {
            return $s;
        }
        return 'Bus-' . $s;
    }

    /**
     * Process an individual fuel log record to compute:
     * - Distance (ending_odo - starting_odo)
     * - Consumed fuel (liters) for the Average Fuel Consumed KPI
     * - Estimated fuel (liters) = Distance / KPL
     * - Estimated fuel cost (₱) = Estimated fuel * Diesel Price
     * - Actual fuel cost (₱) = Recorded refuel cost
     */
    public function processRow(array $row, float $dieselPrice): array
    {
        $rawLiters = (float) ($row['liters'] ?? 0);
        $rawCost   = (float) ($row['cost'] ?? 0);
        $rawPrice  = (float) ($row['price_per_liter'] ?? 0);
        $price     = $rawPrice > 0 ? $rawPrice : $dieselPrice;

        $startOdo  = (float) ($row['starting_odo'] ?? ($row['starting_odometer'] ?? ($row['start_km'] ?? 0)));
        $endOdo    = (float) ($row['ending_odo'] ?? ($row['ending_odometer'] ?? ($row['end_km'] ?? 0)));
        $rawDist   = (float) ($row['distance_traveled'] ?? ($row['distance_km'] ?? 0));
        $kpl       = (float) ($row['kpl'] ?? 0) > 0 ? (float) $row['kpl'] : 9.0;

        // Distance = Ending ODO - Starting ODO
        $dist = 0.0;
        if ($endOdo > $startOdo && $startOdo > 0) {
            $dist = round($endOdo - $startOdo, 2);
        } elseif ($rawDist > 0) {
            $dist = round($rawDist, 2);
        }

        $hasActualLiters = ($rawLiters > 0);
        $hasOdo          = ($dist > 0 && $kpl > 0);
        $isValid         = ($hasActualLiters || $hasOdo);

        // KPI Fuel Consumption (Liters):
        // Priority 1: actual fuel_logs.liters when available
        // Priority 2: Distance / Bus KPL
        $consumedLiters = 0.0;
        if ($hasActualLiters) {
            $consumedLiters = round($rawLiters, 2);
        } elseif ($hasOdo) {
            $consumedLiters = round($dist / $kpl, 2);
        }

        // Estimated Fuel (L):
        $estimatedLiters = $hasOdo ? round($dist / $kpl, 2) : $consumedLiters;

        // Estimated Fuel Cost (₱):
        // Estimated fuel cost = (Distance ÷ Bus KPL) × Diesel Price
        $estimatedCost = round($estimatedLiters * $price, 2);

        // Actual Fuel Cost (₱):
        $actualCost    = 0.0;
        $hasActualCost = false;
        if ($rawCost > 0) {
            $actualCost    = round($rawCost, 2);
            $hasActualCost = true;
        } elseif ($hasActualLiters) {
            $actualCost    = round($rawLiters * $price, 2);
            $hasActualCost = true;
        }

        // Format human-friendly bus label (display only; raw bus_id untouched)
        $rawBusLabel = trim((string) ($row['bus_label_raw'] ?? ''));
        $busIdVal    = (string) ($row['bus_id'] ?? '');
        $row['bus']  = $this->busDisplay($busIdVal, $rawBusLabel !== '' ? $rawBusLabel : null);
        $row['distance']          = $dist;
        $row['calculated_kpl']    = $kpl;
        $row['consumed_liters']   = $consumedLiters;
        $row['estimated_liters']  = $estimatedLiters;
        $row['actual_liters']     = $hasActualLiters ? $rawLiters : 0.0;
        $row['estimated_cost']    = $estimatedCost;
        $row['actual_cost']       = $actualCost;
        $row['has_actual_cost']   = $hasActualCost;
        $row['is_valid_trip']     = $isValid;
        $row['effective_price']   = $price;
        $row['receipt_number']    = !empty($row['receipt_number']) ? $row['receipt_number'] : ('REC-' . str_pad((string)($row['id'] ?? '1'), 5, '0', STR_PAD_LEFT));

        return $row;
    }

    /**
     * Build a schema-adaptive SELECT list for fuel_logs rows.
     *
     * Missing optional columns (odo variants, kpl, price, etc.) degrade to
     * safe defaults instead of producing SQL errors, mirroring the resilience
     * used by api/dashboard/fuel_analytics.php.
     *
     * @return list<string>
     */
    private function fuelLogSelectExpressions(): array
    {
        $f = $this->getFuelCols();

        if (isset($f['fuel_log_id']) && isset($f['id'])) {
            $idExpr = 'COALESCE(fl.fuel_log_id, fl.id) AS id';
        } elseif (isset($f['fuel_log_id'])) {
            $idExpr = 'fl.fuel_log_id AS id';
        } elseif (isset($f['id'])) {
            $idExpr = 'fl.id AS id';
        } else {
            $idExpr = '1 AS id';
        }

        $coalesceNum = static function (array $candidates) use ($f): string {
            $names = array_values(array_intersect($candidates, array_keys($f)));
            if (empty($names)) {
                return '0';
            }
            return 'COALESCE('
                . implode(', ', array_map(static fn (string $c): string => "fl.$c", $names))
                . ', 0)';
        };

        return [
            $idExpr,
            isset($f['fuel_log_id']) ? 'fl.fuel_log_id' : 'NULL AS fuel_log_id',
            isset($f['bus_id']) ? 'fl.bus_id' : 'NULL AS bus_id',
            isset($f['driver_id']) ? 'fl.driver_id' : 'NULL AS driver_id',
            isset($f['trip_id']) ? 'fl.trip_id' : 'NULL AS trip_id',
            isset($f['liters']) ? 'COALESCE(fl.liters, 0) AS liters' : '0 AS liters',
            isset($f['cost']) ? 'COALESCE(fl.cost, 0) AS cost' : '0 AS cost',
            isset($f['price_per_liter']) ? 'COALESCE(fl.price_per_liter, 0) AS price_per_liter' : '0 AS price_per_liter',
            $coalesceNum(['starting_odo', 'starting_odometer', 'start_km']) . ' AS starting_odo',
            $coalesceNum(['ending_odo', 'ending_odometer', 'end_km']) . ' AS ending_odo',
            $coalesceNum(['distance_traveled', 'distance_km']) . ' AS distance_traveled',
            isset($f['kpl']) ? 'COALESCE(fl.kpl, 9.0) AS kpl' : '9.0 AS kpl',
            isset($f['is_full_tank']) ? 'COALESCE(fl.is_full_tank, 0) AS is_full_tank' : '0 AS is_full_tank',
            isset($f['created_at']) ? 'fl.created_at' : 'NULL AS created_at',
        ];
    }

    /**
     * Fetch all raw records matching the filters with safe, schema-adaptive joins.
     *
     * A single query is assembled so that no column is ever referenced unless
     * SHOW COLUMNS confirmed it exists: the sort expression, the buses join,
     * and the drivers join are all gated. On failure the error is written to
     * the PHP error log instead of being silently swallowed.
     */
    private function fetchRawRows(array $filters): array
    {
        $db = Database::tryGet();
        if ($db === null) {
            return [];
        }

        $fuelCols = $this->getFuelCols();
        $selectList = implode(",\n                ", $this->fuelLogSelectExpressions());

        // Sort expression built ONLY from columns confirmed present, so ORDER BY
        // can never reference a missing column.
        $orderCols = [];
        if (isset($fuelCols['created_at'])) {
            $orderCols[] = 'fl.created_at DESC';
        }
        if (isset($fuelCols['fuel_log_id'])) {
            $orderCols[] = 'fl.fuel_log_id DESC';
        } elseif (isset($fuelCols['id'])) {
            $orderCols[] = 'fl.id DESC';
        }
        $orderBy = empty($orderCols) ? '' : 'ORDER BY ' . implode(', ', $orderCols);

        // Buses join: only when both fuel_logs.bus_id and a usable buses key exist.
        $busJoin = '';
        $busAliasExpr = 'fl.bus_id';
        $bPlateCol = "''";
        if (isset($fuelCols['bus_id'])) {
            $busCols = $this->getBusesCols();
            if (isset($busCols['bus_id'])) {
                $busJoin = 'LEFT JOIN buses b ON b.bus_id = fl.bus_id';
            } elseif (isset($busCols['id'])) {
                $busJoin = 'LEFT JOIN buses b ON CAST(b.id AS CHAR) = CAST(fl.bus_id AS CHAR)';
            }
            if ($busJoin !== '') {
                if (isset($busCols['bus_label'])) {
                    $busAliasExpr = 'COALESCE(NULLIF(b.bus_label, \'\'), fl.bus_id)';
                }
                $bPlateCol = isset($busCols['plate_number'])
                    ? 'b.plate_number'
                    : "''";
            }
        }

        // Drivers join: gated on fuel_logs.driver_id AND valid drivers columns.
        $driverJoin = '';
        $driverExpr = "'Driver' AS driver_name,
                     NULL AS driver_code";
        if (isset($fuelCols['driver_id'])) {
            $driverCols = [];
            try {
                $stmt = $db->query('SHOW COLUMNS FROM drivers');
                if ($stmt) {
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $driverCols[strtolower((string) $r['Field'])] = true;
                    }
                }
            } catch (\Throwable $e) {}
            if (isset($driverCols['driver_id'])) {
                $driverNameCol = isset($driverCols['full_name'])
                    ? 'd.full_name'
                    : 'd.driver_id';
                $driverJoin = 'LEFT JOIN drivers d ON d.driver_id = fl.driver_id';
                $driverExpr = "COALESCE($driverNameCol, fl.driver_id, 'Driver') AS driver_name,
                             fl.driver_id AS driver_code";
            } else {
                $driverExpr = "COALESCE(fl.driver_id, 'Driver') AS driver_name,
                             fl.driver_id AS driver_code";
            }
        }

        [$where, $params] = $this->applyFilters($filters);

        $sql = "
            SELECT
                $selectList,
                $busAliasExpr AS bus_label_raw,
                $bPlateCol AS plate_number,
                $driverExpr
            FROM fuel_logs fl
            $busJoin
            $driverJoin
            $where
            $orderBy
        ";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log(
                'FuelCostAnalyticsService: fuel_logs query failed: '
                . $e->getMessage()
                . ' | SQL: ' . preg_replace('/\s+/', ' ', $sql)
            );
            return [];
        }

        $dieselPrice = $this->getDieselPrice();
        return array_map(fn($r) => $this->processRow($r, $dieselPrice), $rows);
    }

    /**
     * Shared WHERE clause for fuel_refuels queries (bus/driver/date filters).
     */
    private function refuelWhere(array $filters, array &$params, string $alias = 'fr'): string
    {
        $where = [];

        if (!empty($filters['bus'])) {
            $where[] = "$alias.bus_id = :ref_bus";
            $params[':ref_bus'] = (string) $filters['bus'];
        }
        if (!empty($filters['driver'])) {
            $where[] = "$alias.driver_id = :ref_driver";
            $params[':ref_driver'] = (string) $filters['driver'];
        }
        if (!empty($filters['from'])) {
            $where[] = "$alias.created_at >= :ref_from";
            $params[':ref_from'] = date('Y-m-d 00:00:00', strtotime((string) $filters['from']));
        }
        if (!empty($filters['to'])) {
            $where[] = "$alias.created_at <= :ref_to";
            $params[':ref_to'] = date('Y-m-d 23:59:59', strtotime((string) $filters['to']));
        }

        return $where ? 'WHERE ' . implode(' AND ', $where) : '';
    }

    /**
     * Fetch actual refuel transactions (fuel_refuels) for the same filters.
     * Generator-produced history records purchases here while fuel_logs keeps
     * one odometer/KPL row per completed trip (zero liters/cost).
     *
     * @return list<array<string,mixed>>
     */
    private function fetchRefuelTransactions(array $filters): array
    {
        $db = Database::tryGet();
        if ($db === null) {
            return [];
        }

        try {
            $chk = $db->query("SHOW TABLES LIKE 'fuel_refuels'");
            if (!$chk || $chk->fetchColumn() === false) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        $params = [];
        $where = $this->refuelWhere($filters, $params);

        $sql = "
            SELECT
                fr.bus_id,
                COALESCE(fr.liters, 0) AS liters,
                COALESCE(fr.total_cost, 0) AS cost,
                fr.created_at
            FROM fuel_refuels fr
            $where";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('FuelCostAnalyticsService: fuel_refuels query failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Liters-weighted average diesel price actually paid in the filtered period
     * (from fuel_refuels). Used for estimates so historical periods do not use
     * today's pump price. Null when no refuel transactions match.
     */
    public function getPeriodRefuelPrice(array $filters = []): ?float
    {
        $db = Database::tryGet();
        if ($db === null) {
            return null;
        }

        try {
            $chk = $db->query("SHOW TABLES LIKE 'fuel_refuels'");
            if (!$chk || $chk->fetchColumn() === false) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        $params = [];
        $where = $this->refuelWhere($filters, $params);

        try {
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(fr.liters), 0) AS liters, COALESCE(SUM(fr.total_cost), 0) AS cost
                FROM fuel_refuels fr
                $where
            ");
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $liters = (float) ($row['liters'] ?? 0);
            $cost = (float) ($row['cost'] ?? 0);
            if ($liters > 0 && $cost > 0) {
                return round($cost / $liters, 2);
            }
        } catch (\Throwable $e) {
            error_log('FuelCostAnalyticsService: period refuel price failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Bus display labels + plate numbers keyed by bus_id.
     */
    private function busMeta(): array
    {
        $db = Database::tryGet();
        if ($db === null) {
            return [];
        }

        $cols = $this->getBusesCols();
        $idCol = isset($cols['bus_id']) ? 'bus_id' : (isset($cols['id']) ? 'id' : null);
        if ($idCol === null) {
            return [];
        }
        $labelCol = isset($cols['bus_label']) ? 'bus_label' : $idCol;
        $plateExpr = isset($cols['plate_number']) ? 'plate_number' : "''";

        $out = [];
        try {
            $stmt = $db->query("SELECT $idCol AS bus_id, $labelCol AS label, $plateExpr AS plate_number FROM buses");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(string) $r['bus_id']] = [
                    'label' => trim((string) $r['label']),
                    'plate' => trim((string) $r['plate_number']),
                ];
            }
        } catch (\Throwable $e) {}

        return $out;
    }

    /**
     * Driver full names keyed by driver_id.
     */
    private function driverMeta(): array
    {
        $db = Database::tryGet();
        if ($db === null) {
            return [];
        }

        $out = [];
        try {
            $stmt = $db->query("SELECT driver_id, full_name FROM drivers");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[(string) $r['driver_id']] = trim((string) $r['full_name']);
            }
        } catch (\Throwable $e) {}

        return $out;
    }

    /**
     * Normalise one refuel row into the receipt/transaction shape the UI and
     * exports expect.
     */
    private function formatTransaction(array $r, array $busMeta, array $driverMeta, string $prefix): array
    {
        $busId = (string) ($r['bus_id'] ?? '');
        $driverId = (string) ($r['driver_id'] ?? '');
        $id = (int) ($r['id'] ?? 0);

        $liters = round((float) ($r['liters'] ?? 0), 2);
        $cost = round((float) ($r['cost'] ?? 0), 2);
        $price = round((float) ($r['price_per_liter'] ?? 0), 2);
        if ($price <= 0 && $liters > 0 && $cost > 0) {
            $price = round($cost / $liters, 2);
        }

        $meta = $busMeta[$busId] ?? null;
        $label = $meta['label'] ?? $busId;

        return [
            'id' => $id,
            'receipt_number' => $prefix . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
            'bus_id' => $busId,
            'bus' => $this->busDisplay($busId, $label !== '' ? $label : null),
            'plate_number' => (string) ($meta['plate'] ?? ''),
            'driver_id' => $driverId,
            'driver_name' => $driverMeta[$driverId] ?? $driverId,
            'driver_code' => $driverId,
            'liters' => $liters,
            'price_per_liter' => $price,
            'cost' => $cost,
            'is_full_tank' => (int) ($r['is_full_tank'] ?? 1),
            'refuel_type' => (string) ($r['refuel_type'] ?? ''),
            'notes' => $r['notes'] ?? null,
            'created_at' => (string) ($r['created_at'] ?? ''),
        ];
    }

    /**
     * Actual refuel transactions for the receipts panel and Fuel Transactions
     * table: fuel_refuels rows plus legacy fuel_logs refuel rows (liters > 0),
     * newest first.
     *
     * @return list<array<string,mixed>>
     */
    public function getRefuelTransactions(array $filters = [], ?int $limit = null): array
    {
        $db = Database::tryGet();
        if ($db === null) {
            return [];
        }

        $busMeta = $this->busMeta();
        $driverMeta = $this->driverMeta();
        $rows = [];

        // 1. Generator/current refuel transactions.
        try {
            $chk = $db->query("SHOW TABLES LIKE 'fuel_refuels'");
            if ($chk && $chk->fetchColumn() !== false) {
                $params = [];
                $where = $this->refuelWhere($filters, $params);
                $stmt = $db->prepare("
                    SELECT
                        fr.refuel_id AS id,
                        fr.bus_id,
                        fr.driver_id,
                        COALESCE(fr.liters, 0) AS liters,
                        COALESCE(fr.price_per_liter, 0) AS price_per_liter,
                        COALESCE(fr.total_cost, 0) AS cost,
                        COALESCE(fr.is_full_tank, 1) AS is_full_tank,
                        fr.refuel_type,
                        fr.notes,
                        fr.created_at
                    FROM fuel_refuels fr
                    $where
                ");
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $rows[] = $this->formatTransaction($r, $busMeta, $driverMeta, 'RF-');
                }
            }
        } catch (\Throwable $e) {
            error_log('FuelCostAnalyticsService: refuel transactions failed: ' . $e->getMessage());
        }

        // 2. Legacy fuel_logs refuel rows (the old model wrote purchases here).
        $fuelCols = $this->getFuelCols();
        if (isset($fuelCols['liters'])) {
            try {
                $params = [];
                $where = [];
                $where[] = 'fl.liters > 0';
                if (!empty($filters['bus']) && isset($fuelCols['bus_id'])) {
                    $where[] = 'fl.bus_id = :lg_bus';
                    $params[':lg_bus'] = (string) $filters['bus'];
                }
                if (!empty($filters['driver']) && isset($fuelCols['driver_id'])) {
                    $where[] = 'fl.driver_id = :lg_driver';
                    $params[':lg_driver'] = (string) $filters['driver'];
                }
                if (!empty($filters['from']) && isset($fuelCols['created_at'])) {
                    $where[] = 'fl.created_at >= :lg_from';
                    $params[':lg_from'] = date('Y-m-d 00:00:00', strtotime((string) $filters['from']));
                }
                if (!empty($filters['to']) && isset($fuelCols['created_at'])) {
                    $where[] = 'fl.created_at <= :lg_to';
                    $params[':lg_to'] = date('Y-m-d 23:59:59', strtotime((string) $filters['to']));
                }

                $idExpr = isset($fuelCols['fuel_log_id']) ? 'COALESCE(fl.fuel_log_id, fl.id)' : (isset($fuelCols['id']) ? 'fl.id' : '0');
                $busExpr = isset($fuelCols['bus_id']) ? 'fl.bus_id' : 'NULL';
                $driverExpr = isset($fuelCols['driver_id']) ? 'fl.driver_id' : 'NULL';
                $priceExpr = isset($fuelCols['price_per_liter']) ? 'COALESCE(fl.price_per_liter, 0)' : '0';
                $notesExpr = isset($fuelCols['notes']) ? 'fl.notes' : 'NULL';

                $stmt = $db->prepare("
                    SELECT
                        $idExpr AS id,
                        $busExpr AS bus_id,
                        $driverExpr AS driver_id,
                        COALESCE(fl.liters, 0) AS liters,
                        $priceExpr AS price_per_liter,
                        COALESCE(fl.cost, 0) AS cost,
                        1 AS is_full_tank,
                        'Full Tank' AS refuel_type,
                        $notesExpr AS notes,
                        fl.created_at
                    FROM fuel_logs fl
                    WHERE " . implode(' AND ', $where) . "
                ");
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $rows[] = $this->formatTransaction($r, $busMeta, $driverMeta, 'FL-');
                }
            } catch (\Throwable $e) {
                error_log('FuelCostAnalyticsService: legacy refuel rows failed: ' . $e->getMessage());
            }
        }

        usort($rows, static fn($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        if ($limit !== null) {
            $rows = array_slice($rows, 0, max(1, $limit));
        }

        return $rows;
    }

    /**
     * Compute KPI summary metrics.
     * The primary KPI is "Average Fuel Consumption" in km/L (odometer
     * distance over consumed liters); per-trip liters stay available.
     */
    public function getSummary(array $filters): array
    {
        [$from, $to] = $this->resolvePeriod($filters);
        $filters['from'] = $filters['from'] ?? $from;
        $filters['to'] = $filters['to'] ?? $to;

        $rows = $this->fetchRawRows($filters);

        $totalConsumedLiters = 0.0;
        $totalEstimatedCost  = 0.0;
        $totalActualCost     = 0.0;
        $totalDistance       = 0.0;
        $validTripsCount     = 0;
        $busesCostMap        = [];

        foreach ($rows as $r) {
            if (!$r['is_valid_trip']) {
                continue;
            }
            $validTripsCount++;
            $totalConsumedLiters += $r['consumed_liters'];
            $totalEstimatedCost  += $r['estimated_cost'];
            $totalActualCost     += $r['actual_cost'];
            $totalDistance       += (float) ($r['distance'] ?? 0);

            $busKey = $r['bus'] ?: $this->busDisplay($r['bus_id'] ?? null);
            $busesCostMap[$busKey] = ($busesCostMap[$busKey] ?? 0.0) + $r['estimated_cost'];
        }

        /* Actual refuel transactions from fuel_refuels. */
        $refuelLiters = 0.0;
        $refuelCost = 0.0;
        $refuelEvents = 0;
        foreach ($this->fetchRefuelTransactions($filters) as $ref) {
            $refuelLiters += (float) ($ref['liters'] ?? 0);
            $refuelCost   += (float) ($ref['cost'] ?? 0);
            $refuelEvents++;
        }
        $totalActualCost += $refuelCost;

        // KPI: Average Fuel Consumed per valid trip (Liters)
        $avgFuelConsumed = ($validTripsCount > 0 && $totalConsumedLiters > 0)
            ? round($totalConsumedLiters / $validTripsCount, 2)
            : 0.0;

        // Highest fuel cost bus
        $topBus = null;
        if (!empty($busesCostMap)) {
            arsort($busesCostMap);
            $topBusName = (string) array_key_first($busesCostMap);
            $topBus = [
                'bus'   => $topBusName,
                'total' => round($busesCostMap[$topBusName], 2)
            ];
        }

        $dieselPrice = $this->getDieselPrice();

        return [
            // Primary KPI requested: Average Fuel Consumption (km/L)
            'avg_fuel_consumed'      => $avgFuelConsumed,
            'avg_kpl'                => ($totalDistance > 0 && $totalConsumedLiters > 0)
                ? round($totalDistance / $totalConsumedLiters, 2)
                : 0.0,
            'total_distance'         => round($totalDistance, 2),
            'total_fuel_consumed'    => round($totalConsumedLiters, 2),
            'valid_trips_count'      => $validTripsCount,

            // Charts & Financial metrics: Estimated vs Actual Fuel Cost
            'total_estimated_cost'   => round($totalEstimatedCost, 2),
            'total_actual_cost'      => round($totalActualCost, 2),
            'total_cost'             => round($totalEstimatedCost, 2), // Chart primary cost
            'avg_cost'               => ($validTripsCount > 0) ? round($totalEstimatedCost / $validTripsCount, 2) : 0.0,
            'total_liters'           => round($totalConsumedLiters, 2),
            'total_refuel_liters'    => round($refuelLiters, 2),
            'total_refuel_cost'      => round($refuelCost, 2),
            'refuel_events'          => $refuelEvents,
            'avg_price'              => $dieselPrice,
            'total_records'          => count($rows),
            'avg_liters_per_record'  => $avgFuelConsumed,
            'top_bus'                => $topBus,
        ];
    }

    /**
     * Estimated Fuel Cost per Bus (sorted descending by estimated_cost).
     */
    public function getFuelCostPerBus(array $filters): array
    {
        [$from, $to] = $this->resolvePeriod($filters);
        $filters['from'] = $filters['from'] ?? $from;
        $filters['to'] = $filters['to'] ?? $to;

        $rows = $this->fetchRawRows($filters);
        $byBus = [];

        foreach ($rows as $r) {
            if (!$r['is_valid_trip']) continue;
            $busId = (string)$r['bus_id'];
            $label = $r['bus'];

            if (!isset($byBus[$busId])) {
                $byBus[$busId] = [
                    'bus_id'           => $busId,
                    'bus'              => $label,
                    'plate_number'     => $r['plate_number'] ?? '',
                    'estimated_cost'   => 0.0,
                    'actual_cost'      => 0.0,
                    'total'            => 0.0, // Primary chart value is estimated fuel cost
                    'liters'           => 0.0, // Consumer alias for consumed_liters
                    'consumed_liters'  => 0.0,
                    'estimated_liters' => 0.0,
                    'actual_liters'    => 0.0,
                    'distance'         => 0.0,
                    'trips_count'      => 0,
                    'has_actual'       => false,
                ];
            }

            $byBus[$busId]['estimated_cost']   += $r['estimated_cost'];
            $byBus[$busId]['actual_cost']      += $r['actual_cost'];
            $byBus[$busId]['total']            += $r['estimated_cost'];
            $byBus[$busId]['liters']           += $r['consumed_liters'];
            $byBus[$busId]['consumed_liters']  += $r['consumed_liters'];
            $byBus[$busId]['estimated_liters'] += $r['estimated_liters'];
            $byBus[$busId]['actual_liters']    += $r['actual_liters'];
            $byBus[$busId]['distance']         += $r['distance'];
            $byBus[$busId]['trips_count']++;
            if ($r['has_actual_cost']) {
                $byBus[$busId]['has_actual'] = true;
            }
        }

        /* Actual refuel transactions (fuel_refuels) merged per bus. */
        foreach ($this->fetchRefuelTransactions($filters) as $ref) {
            $busId = (string) ($ref['bus_id'] ?? '');
            if ($busId === '') {
                continue;
            }
            if (!isset($byBus[$busId])) {
                $byBus[$busId] = [
                    'bus_id'           => $busId,
                    'bus'              => $this->busDisplay($busId),
                    'plate_number'     => '',
                    'estimated_cost'   => 0.0,
                    'actual_cost'      => 0.0,
                    'total'            => 0.0, // Primary chart value is estimated fuel cost
                    'liters'           => 0.0, // Consumer alias for consumed_liters
                    'consumed_liters'  => 0.0,
                    'estimated_liters' => 0.0,
                    'actual_liters'    => 0.0,
                    'distance'         => 0.0,
                    'trips_count'      => 0,
                    'has_actual'       => false,
                ];
            }
            $byBus[$busId]['actual_cost']   += (float) ($ref['cost'] ?? 0);
            $byBus[$busId]['actual_liters'] += (float) ($ref['liters'] ?? 0);
            $byBus[$busId]['has_actual'] = true;
        }

        $list = array_values($byBus);
        usort($list, fn($a, $b) => $b['estimated_cost'] <=> $a['estimated_cost']);

        return $list;
    }

    /**
     * Daily Estimated Fuel Cost Trend over time.
     */
    public function getDailyFuelTrend(array $filters): array
    {
        [$from, $to] = $this->resolvePeriod($filters);
        $filters['from'] = $filters['from'] ?? $from;
        $filters['to'] = $filters['to'] ?? $to;

        $rows = $this->fetchRawRows($filters);
        $byDay = [];

        foreach ($rows as $r) {
            if (!$r['is_valid_trip']) continue;
            $day = substr((string)$r['created_at'], 0, 10);
            if (!isset($byDay[$day])) {
                $byDay[$day] = [
                    'day'              => $day,
                    'estimated_cost'   => 0.0,
                    'actual_cost'      => 0.0,
                    'total'            => 0.0, // Backward compat for total
                    'consumed_liters'  => 0.0,
                    'estimated_liters' => 0.0,
                    'liters'           => 0.0,
                    'trips_count'      => 0,
                ];
            }

            $byDay[$day]['estimated_cost']   += $r['estimated_cost'];
            $byDay[$day]['actual_cost']      += $r['actual_cost'];
            $byDay[$day]['total']            += $r['estimated_cost'];
            $byDay[$day]['consumed_liters']  += $r['consumed_liters'];
            $byDay[$day]['estimated_liters'] += $r['estimated_liters'];
            $byDay[$day]['liters']           += $r['consumed_liters'];
            $byDay[$day]['trips_count']++;
        }

        /* Actual refuel transactions merged into the daily actual cost. */
        foreach ($this->fetchRefuelTransactions($filters) as $ref) {
            $day = substr((string) ($ref['created_at'] ?? ''), 0, 10);
            if ($day === '') {
                continue;
            }
            if (!isset($byDay[$day])) {
                $byDay[$day] = [
                    'day'              => $day,
                    'estimated_cost'   => 0.0,
                    'actual_cost'      => 0.0,
                    'total'            => 0.0, // Backward compat for total
                    'consumed_liters'  => 0.0,
                    'estimated_liters' => 0.0,
                    'liters'           => 0.0,
                    'trips_count'      => 0,
                ];
            }
            $byDay[$day]['actual_cost'] += (float) ($ref['cost'] ?? 0);
        }

        ksort($byDay);
        return array_values($byDay);
    }

    /**
     * Estimated vs Actual Fuel Cost comparison per bus.
     */
    public function getCostComparison(array $filters): array
    {
        $busList = $this->getFuelCostPerBus($filters);
        $comparison = [];

        foreach ($busList as $b) {
            $estCost = round($b['estimated_cost'], 2);
            $actCost = round($b['actual_cost'], 2);
            $varCost = round($actCost - $estCost, 2);
            $varPct  = $estCost > 0 ? round(($varCost / $estCost) * 100, 1) : null;

            $comparison[] = [
                'bus_id'           => $b['bus_id'],
                'bus'              => $b['bus'],
                'plate_number'     => $b['plate_number'],
                'estimated_cost'   => $estCost,
                'actual_cost'      => $actCost,
                'variance_cost'    => $varCost,
                'variance_pct'     => $varPct,
                'has_actual'       => $b['has_actual'],
                'consumed_liters'  => round($b['consumed_liters'], 2),
                'trips_count'      => $b['trips_count'],
            ];
        }

        return $comparison;
    }

    /**
     * Fetch recent receipts / fuel log entries.
     */
    public function getRecentReceipts(array $filters, int $limit = 5): array
    {
        $rows = $this->fetchRawRows($filters);
        return array_slice($rows, 0, max(1, $limit));
    }

    /**
     * Fetch all fuel log entries matching filters.
     */
    public function getReceipts(array $filters, ?int $limit = null): array
    {
        $rows = $this->fetchRawRows($filters);
        if ($limit !== null) {
            return array_slice($rows, 0, max(1, $limit));
        }
        return $rows;
    }

    /**
     * List all buses safely without failing on schema variations.
     */
    public function getBuses(): array
    {
        $db = Database::tryGet();
        if ($db === null) {
            return [];
        }
        $cols = $this->getBusesCols();
        $idCol = isset($cols['bus_id']) ? 'bus_id' : (isset($cols['id']) ? 'id' : 'id');
        $labelCol = isset($cols['bus_label']) ? 'bus_label' : $idCol;

        try {
            $stmt = $db->query("SELECT $idCol AS bus_id, $labelCol AS bus_label FROM buses ORDER BY $idCol ASC");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($rows)) {
                return $rows;
            }
        } catch (\Throwable $e) {}

        // Fallback directly from fuel_logs
        try {
            $stmt = $db->query("SELECT DISTINCT bus_id, bus_id AS bus_label FROM fuel_logs ORDER BY bus_id ASC");
            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * List active drivers safely without failing on missing tables.
     */
    public function getDrivers(): array
    {
        $db = Database::tryGet();
        if ($db === null) {
            return [];
        }
        try {
            $stmt = $db->query("SELECT driver_id, full_name FROM drivers WHERE status = 'Active' ORDER BY full_name ASC");
            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $e) {
            try {
                $stmt = $db->query("SELECT DISTINCT driver_id, driver_id AS full_name FROM fuel_logs WHERE driver_id IS NOT NULL AND driver_id != ''");
                return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            } catch (\Throwable $e2) {
                return [];
            }
        }
    }
}

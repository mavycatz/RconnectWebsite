<?php

declare(strict_types=1);

ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/services/DateFilterService.php';

use App\Core\Database;

validateFinanceRequest('standard', false);
setRequestTimeout(30);

$db = Database::tryGet();

$buildFuelFailure = static function (string $message): string {
    $defaultDieselPrice = 65.55;
    return json_encode([
        'success' => false,
        'message' => $message,
        'summary' => [
            'avg_fuel_consumed_per_trip' => null,
            'avg_liters_per_trip'        => null,
            'total_fuel_consumed'        => 0.0,
            'total_liters'               => 0.0,
            'total_estimated_fuel_cost'  => 0.0,
            'total_actual_fuel_cost'     => 0.0,
            'total_fuel_cost'            => 0.0,
            'avg_fuel_cost_per_trip'     => null,
            'avg_estimated_cost_per_trip'=> null,
            'fuel_trips_count'           => 0,
            'actual_trips_count'         => 0,
            'actual_refuel_events'       => 0,
            'estimated_trips_count'      => 0,
            'total_distance'             => 0.0,
            'avg_kpl'                    => null,
            'avg_l_per_100km'            => null,
            'default_price'              => $defaultDieselPrice
        ],
        'fuel_by_bus'            => [],
        'cost_comparison_by_bus' => [],
        'fuel_trend'             => [
            'granularity'       => 'month',
            'labels'            => [],
            'estimated_cost'    => [],
            'actual_cost'       => [],
            'total_cost'        => [],
            'estimated_liters'  => [],
            'consumed_liters'   => [],
            'avg_consumed_trip' => [],
            'avg_cost_per_trip' => [],
            'trip_count'        => []
        ],
        'efficiency_by_bus'      => []
    ]);
};

if ($db === null) {
    ob_end_clean();
    $payload = $buildFuelFailure('Failed to load fuel analytics: Database connection unavailable.');
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo $payload;
    exit;
}

/* -------------------------------------------------------------
   SCHEMA RESILIENCE & AUTO-ENSURE
   Ensures fuel_prices and fuel_logs exist and have needed columns
   without failing on diverse database environments.
------------------------------------------------------------- */

if (!function_exists('ensureFuelDatabaseSchema')) {
    function ensureFuelDatabaseSchema(PDO $db): void
    {
    static $ensured = false;
    if ($ensured) return;

    try {
        // 1. Ensure fuel_prices table & Diesel row
        $db->exec("
            CREATE TABLE IF NOT EXISTS `fuel_prices` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `fuel_type` VARCHAR(50) NOT NULL,
                `price_per_liter` DECIMAL(10,2) NOT NULL DEFAULT 65.55,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_fuel_type` (`fuel_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $seedCheck = $db->query("SELECT 1 FROM `fuel_prices` WHERE `fuel_type` = 'Diesel' LIMIT 1")->fetch();
        if (!$seedCheck) {
            @$db->exec("INSERT INTO `fuel_prices` (`fuel_type`, `price_per_liter`) VALUES ('Diesel', 65.55)");
        }
    } catch (\Throwable $e) {
        // Non-fatal
    }

    try {
        // 2. Ensure fuel_logs table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `fuel_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `fuel_log_id` INT NULL,
                `bus_id` VARCHAR(50) NOT NULL,
                `driver_id` VARCHAR(50) DEFAULT NULL,
                `trip_id` INT DEFAULT NULL,
                `liters` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `price_per_liter` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `starting_odo` DECIMAL(10,2) DEFAULT NULL,
                `ending_odo` DECIMAL(10,2) DEFAULT NULL,
                `starting_odometer` DECIMAL(10,2) DEFAULT NULL,
                `ending_odometer` DECIMAL(10,2) DEFAULT NULL,
                `start_km` DECIMAL(10,2) DEFAULT NULL,
                `end_km` DECIMAL(10,2) DEFAULT NULL,
                `distance_traveled` DECIMAL(10,2) DEFAULT NULL,
                `distance_km` DECIMAL(10,2) DEFAULT NULL,
                `kpl` DECIMAL(10,2) DEFAULT 9.00,
                `is_full_tank` TINYINT(1) DEFAULT 0,
                `expected_fuel` DECIMAL(10,2) DEFAULT NULL,
                `notes` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_fuel_bus_id` (`bus_id`),
                INDEX `idx_fuel_trip_id` (`trip_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (\Throwable $e) {
        // Non-fatal
    }

        $ensured = true;
    }
}

ensureFuelDatabaseSchema($db);

/* -------------------------------------------------------------
   INSPECT LIVE TABLE COLUMNS DYNAMICALLY
------------------------------------------------------------- */

$colMapFuel = [];
try {
    $colStmt = $db->query("SHOW COLUMNS FROM fuel_logs");
    if ($colStmt) {
        while ($colRow = $colStmt->fetch(PDO::FETCH_ASSOC)) {
            $colMapFuel[strtolower((string)$colRow['Field'])] = true;
        }
    }
} catch (\Throwable $e) {}

$colMapBuses = [];
try {
    $bStmt = $db->query("SHOW COLUMNS FROM buses");
    if ($bStmt) {
        while ($bRow = $bStmt->fetch(PDO::FETCH_ASSOC)) {
            $colMapBuses[strtolower((string)$bRow['Field'])] = true;
        }
    }
} catch (\Throwable $e) {}

// Ensure critical columns exist on fuel_logs if missing
if (!empty($colMapFuel)) {
    $alterNeeded = [
        'starting_odo'      => 'DECIMAL(10,2) DEFAULT NULL',
        'ending_odo'        => 'DECIMAL(10,2) DEFAULT NULL',
        'kpl'               => 'DECIMAL(10,2) DEFAULT 9.00',
        'distance_traveled' => 'DECIMAL(10,2) DEFAULT NULL',
        'is_full_tank'      => 'TINYINT(1) DEFAULT 0',
        'price_per_liter'   => 'DECIMAL(10,2) DEFAULT 0.00',
        'trip_id'           => 'INT DEFAULT NULL',
    ];
    foreach ($alterNeeded as $col => $typeDef) {
        if (!isset($colMapFuel[$col])) {
            try {
                @$db->exec("ALTER TABLE `fuel_logs` ADD COLUMN `$col` $typeDef");
                $colMapFuel[$col] = true;
            } catch (\Throwable $e) {}
        }
    }
}

/* -------------------------------------------------------------
   BUILD DYNAMIC SELECT & JOIN EXPRESSIONS
------------------------------------------------------------- */

// ID expression
if (isset($colMapFuel['fuel_log_id']) && isset($colMapFuel['id'])) {
    $selectId = "COALESCE(fl.id, fl.fuel_log_id, 0) AS log_id";
    $orderId  = "fl.id ASC";
} elseif (isset($colMapFuel['fuel_log_id'])) {
    $selectId = "fl.fuel_log_id AS log_id";
    $orderId  = "fl.fuel_log_id ASC";
} elseif (isset($colMapFuel['id'])) {
    $selectId = "fl.id AS log_id";
    $orderId  = "fl.id ASC";
} else {
    $selectId = "1 AS log_id";
    $orderId  = "1 ASC";
}

// Starting ODO expression
$startCols = array_values(array_intersect(['starting_odo', 'starting_odometer', 'start_km'], array_keys($colMapFuel)));
$selectStartOdo = !empty($startCols)
    ? "COALESCE(" . implode(", ", array_map(fn($c) => "fl.$c", $startCols)) . ", 0) AS starting_odo"
    : "0 AS starting_odo";

// Ending ODO expression
$endCols = array_values(array_intersect(['ending_odo', 'ending_odometer', 'end_km'], array_keys($colMapFuel)));
$selectEndOdo = !empty($endCols)
    ? "COALESCE(" . implode(", ", array_map(fn($c) => "fl.$c", $endCols)) . ", 0) AS ending_odo"
    : "0 AS ending_odo";

// Distance expression
$distCols = array_values(array_intersect(['distance_traveled', 'distance_km'], array_keys($colMapFuel)));
$selectDist = !empty($distCols)
    ? "COALESCE(" . implode(", ", array_map(fn($c) => "fl.$c", $distCols)) . ", 0) AS raw_distance"
    : "0 AS raw_distance";

// Buses join & label expression
$joinBuses = "";
$selectBusLabel = "'' AS bus_display_label, '' AS bus_plate_number";

if (!empty($colMapBuses)) {
    $hasBusIdCol = isset($colMapBuses['bus_id']);
    $joinCondition = $hasBusIdCol ? "b.bus_id = fl.bus_id" : "CAST(b.id AS CHAR) = CAST(fl.bus_id AS CHAR)";
    $joinBuses = "LEFT JOIN buses b ON $joinCondition";

    $busNameExpr  = isset($colMapBuses['bus_label']) ? "COALESCE(b.bus_label, '')" : "''";
    $busPlateExpr = isset($colMapBuses['plate_number']) ? "COALESCE(b.plate_number, '')" : "''";
    $selectBusLabel = "$busNameExpr AS bus_display_label, $busPlateExpr AS bus_plate_number";
}


/* -------------------------------------------------------------
   PARSE DATE FILTERS
   Supports:
   - range: today, month, year, custom
   - day, month, year
   - fromDate, toDate (or from, to)
   - period: ytd, last6, last12, month, custom (from ops analytics)
------------------------------------------------------------- */

$params = [];
$conditions = [];

/* Shared Finance period model: canonical period/from/to, legacy
   range/fromDate/toDate and day/month/year all resolve through
   DateFilterService; operations-analytics horizons (ytd/last6/last12)
   map to explicit custom ranges. */
$fsRequest = $_GET;
$fsPeriodParam = strtolower(trim((string) ($_GET['period'] ?? '')));
if ($fsPeriodParam === 'ytd') {
    $fsRequest['period'] = 'custom';
    $fsRequest['from'] = date('Y-01-01');
    $fsRequest['to'] = date('Y-m-d');
} elseif ($fsPeriodParam === 'last6') {
    $fsRequest['period'] = 'custom';
    $fsRequest['from'] = date('Y-m-d', strtotime('-6 months'));
    $fsRequest['to'] = date('Y-m-d');
} elseif ($fsPeriodParam === 'last12') {
    $fsRequest['period'] = 'custom';
    $fsRequest['from'] = date('Y-m-d', strtotime('-12 months'));
    $fsRequest['to'] = date('Y-m-d');
}

$fsResolved = \Finance\Services\DateFilterService::normalizeRequest($fsRequest);
$from = $fsResolved['start'];
$to = $fsResolved['end'];

if ($from !== '' && $to !== '') {
    $conditions[] = "DATE(fl.created_at) >= :from_date";
    $params[':from_date'] = $from;
    $conditions[] = "DATE(fl.created_at) <= :to_date";
    $params[':to_date'] = $to;
}

$whereSQL = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Fetch current diesel price for fallback estimation
$defaultDieselPrice = 65.55;
try {
    $priceStmt = $db->query("SELECT price_per_liter FROM fuel_prices WHERE fuel_type LIKE '%diesel%' ORDER BY id DESC LIMIT 1");
    if ($priceStmt && ($priceRow = $priceStmt->fetch(PDO::FETCH_ASSOC))) {
        if ((float)$priceRow['price_per_liter'] > 0) {
            $defaultDieselPrice = (float)$priceRow['price_per_liter'];
        }
    }
} catch (\Throwable $e) {
    // Keep default 65.55
}

try {
    /* -------------------------------------------------------------
       FETCH ALL MATCHING FUEL LOGS FOR THE PERIOD
    ------------------------------------------------------------- */
    $sqlLogs = "
        SELECT
            $selectId,
            fl.bus_id,
            $selectBusLabel,
            fl.trip_id,
            COALESCE(fl.liters, 0) AS raw_liters,
            COALESCE(fl.cost, 0) AS raw_cost,
            COALESCE(fl.price_per_liter, 0) AS raw_price,
            $selectStartOdo,
            $selectEndOdo,
            $selectDist,
            COALESCE(fl.kpl, 9.0) AS bus_kpl,
            COALESCE(fl.is_full_tank, 0) AS is_full_tank,
            DATE(fl.created_at) AS log_date,
            fl.created_at
        FROM fuel_logs fl
        $joinBuses
        $whereSQL
        ORDER BY fl.created_at ASC, $orderId
    ";

    $stmt = $db->prepare($sqlLogs);
    $stmt->execute($params);
    $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    /* -------------------------------------------------------------
       PROCESS RECORDS WITH FULL-TANK TO FULL-TANK PRIORITY
       AND ODO-ESTIMATION FALLBACK
    ------------------------------------------------------------- */
    $totalFuelConsumed = 0.0;
    $totalEstimatedCost = 0.0;
    $totalActualCost = 0.0;
    $totalDistance = 0.0;
    $validTripsMap = [];
    $actualTripsCount = 0;
    $estimatedTripsCount = 0;

    $busSummary = [];
    $busEfficiency = [];
    $timelineBuckets = [];

    // Determine grouping granularity for the trend chart
    $granularity = 'month'; // 'month' or 'day'
    if ($from !== '' && $to !== '') {
        $diffDays = (int) round((strtotime($to) - strtotime($from)) / 86400);
        if ($diffDays <= 31) {
            $granularity = 'day';
        }
    }

    foreach ($allRows as $row) {
        $busId = (string)$row['bus_id'];
        $rawName  = trim((string)($row['bus_display_label'] ?? ''));
        $rawPlate = trim((string)($row['bus_plate_number'] ?? ''));
        $busIdStr = trim((string)$busId);
        $plate = $rawPlate !== '' ? $rawPlate : ($rawName !== '' ? $rawName : $busIdStr);

        $label = '';
        foreach ([$rawName, $rawPlate, $busIdStr] as $candidate) {
            if ($candidate !== '' && stripos($candidate, 'Bus') !== false) {
                $label = $candidate;
                break;
            }
        }
        if ($label === '') {
            if ($rawName !== '') {
                $label = $rawName;
            } elseif ($rawPlate !== '') {
                $label = $rawPlate;
            } elseif ($busIdStr !== '') {
                $label = $busIdStr;
            } else {
                $label = 'Unknown bus';
            }
        }

        $rawLiters = (float)$row['raw_liters'];
        $rawCost   = (float)$row['raw_cost'];
        $startOdo  = (float)$row['starting_odo'];
        $endOdo    = (float)$row['ending_odo'];
        $rawDist   = (float)$row['raw_distance'];
        $specKpl   = (float)$row['bus_kpl'] > 0 ? (float)$row['bus_kpl'] : 9.0;
        $price     = (float)$row['raw_price'] > 0 ? (float)$row['raw_price'] : $defaultDieselPrice;

        // Calculate distance traveled: Ending ODO - Starting ODO
        $dist = 0.0;
        if ($endOdo > $startOdo && $startOdo > 0) {
            $dist = round($endOdo - $startOdo, 2);
        } elseif ($rawDist > 0) {
            $dist = round($rawDist, 2);
        }

        // Validity check
        $hasActualLiters = ($rawLiters > 0);
        $hasOdoEst = ($dist > 0 && $specKpl > 0);

        if (!$hasActualLiters && !$hasOdoEst) {
            // No valid fuel or odometer consumption record - safely exclude
            continue;
        }

        // KPI Fuel Consumption (L):
        // Priority 1: actual fuel_logs.liters
        // Priority 2: Distance / KPL
        $consumedLiters = 0.0;
        if ($hasActualLiters) {
            $consumedLiters = round($rawLiters, 2);
        } elseif ($hasOdoEst) {
            $consumedLiters = round($dist / $specKpl, 2);
        }

        // Estimated Fuel (L) and Estimated Cost (₱):
        // Formula: Estimated Fuel = Distance / KPL
        // Estimated Fuel Cost = Estimated Fuel × Fuel Price per Liter
        $estimatedLiters = $hasOdoEst ? round($dist / $specKpl, 2) : $consumedLiters;
        $estimatedCost   = round($estimatedLiters * $price, 2);

        // Actual Fuel (L) and Actual Cost (₱):
        // fuel_logs consumption rows record liters (and the historical price) but
        // no purchase cost; purchases live in fuel_refuels. Consumed liters feed
        // the km/L calculation, while "actual cost" counts only recorded costs
        // (legacy fuel_logs costs plus refuel purchases) so fuel is never costed
        // twice.
        $actualLiters = $hasActualLiters ? round($rawLiters, 2) : 0.0;
        $actualCost   = 0.0;
        $hasActual    = false;
        if ($rawCost > 0) {
            $actualCost = round($rawCost, 2);
            $hasActual = true;
        }

        $totalFuelConsumed += $consumedLiters;
        $totalEstimatedCost += $estimatedCost;
        $totalActualCost += $actualCost;
        $totalDistance += $dist;

        $tripKey = !empty($row['trip_id']) ? (string)$row['trip_id'] : ('log_' . ($row['log_id'] ?? uniqid()));
        if (!isset($validTripsMap[$tripKey])) {
            $validTripsMap[$tripKey] = true;
            if ($hasActual) {
                $actualTripsCount++;
            } else {
                $estimatedTripsCount++;
            }
        }

        // --- 1. Bus Summary Aggregation ---
        if (!isset($busSummary[$busId])) {
            $busSummary[$busId] = [
                'bus_id'           => $busId,
                'bus_label'        => $label,
                'plate_number'     => $plate,
                'estimated_cost'   => 0.0,
                'actual_cost'      => 0.0,
                'total_cost'       => 0.0,
                'consumed_liters'  => 0.0,
                'estimated_liters' => 0.0,
                'actual_liters'    => 0.0,
                'total_liters'     => 0.0,
                'total_distance'   => 0.0,
                'trips_set'        => [],
                'has_actual'       => false,
                'logs_count'       => 0
            ];
        }
        $busSummary[$busId]['estimated_cost']   += $estimatedCost;
        $busSummary[$busId]['actual_cost']      += $actualCost;
        $busSummary[$busId]['total_cost']       += $estimatedCost; // Primary chart cost metric is estimated fuel cost
        $busSummary[$busId]['consumed_liters']  += $consumedLiters;
        $busSummary[$busId]['estimated_liters'] += $estimatedLiters;
        $busSummary[$busId]['actual_liters']    += $actualLiters;
        $busSummary[$busId]['total_liters']     += $consumedLiters;
        $busSummary[$busId]['total_distance']   += $dist;
        $busSummary[$busId]['logs_count']++;
        $busSummary[$busId]['trips_set'][$tripKey] = true;
        if ($hasActual) {
            $busSummary[$busId]['has_actual'] = true;
        }

        // --- 2. Bus Efficiency Aggregation ---
        if (!isset($busEfficiency[$busId])) {
            $busEfficiency[$busId] = [
                'bus_id'          => $busId,
                'bus_label'       => $label,
                'plate_number'    => $plate,
                'spec_kpl'        => $specKpl,
                'actual_dist'     => 0.0,
                'actual_liters'   => 0.0,
                'total_dist'      => 0.0,
                'total_liters'    => 0.0
            ];
        }
        $busEfficiency[$busId]['total_dist'] += $dist;
        $busEfficiency[$busId]['total_liters'] += $consumedLiters;
        /* Recorded consumption liters pair with this record's odometer
           distance for the per-bus km/L, regardless of cost recording. */
        if ($actualLiters > 0 && $dist > 0) {
            $busEfficiency[$busId]['actual_dist'] += $dist;
            $busEfficiency[$busId]['actual_liters'] += $actualLiters;
        }

        // --- 3. Timeline Trend Aggregation ---
        $dateStr = (string)$row['log_date'];
        $bucketKey = ($granularity === 'day')
            ? $dateStr
            : substr($dateStr, 0, 7); // YYYY-MM

        if (!isset($timelineBuckets[$bucketKey])) {
            $timelineBuckets[$bucketKey] = [
                'estimated_cost'   => 0.0,
                'actual_cost'      => 0.0,
                'consumed_liters'  => 0.0,
                'estimated_liters' => 0.0,
                'trips_set'        => []
            ];
        }
        $timelineBuckets[$bucketKey]['estimated_cost']   += $estimatedCost;
        $timelineBuckets[$bucketKey]['actual_cost']      += $actualCost;
        $timelineBuckets[$bucketKey]['consumed_liters']  += $consumedLiters;
        $timelineBuckets[$bucketKey]['estimated_liters'] += $estimatedLiters;
        $timelineBuckets[$bucketKey]['trips_set'][$tripKey] = true;
    }

    /* -------------------------------------------------------------
       ACTUAL REFUEL TRANSACTIONS (fuel_refuels)
       The generator records one odometer/KPL row per completed trip in
       fuel_logs (liters/cost stay 0) and the actual purchases in
       fuel_refuels. Merge those transactions into the actual cost/liters
       per bus and per trend bucket so "Actual Refuels" is real.
    ------------------------------------------------------------- */
    $refuelEvents = 0;
    $hasRefuelTable = false;
    try {
        $tblStmt = $db->query("SHOW TABLES LIKE 'fuel_refuels'");
        $hasRefuelTable = $tblStmt && $tblStmt->fetchColumn() !== false;
    } catch (\Throwable $e) {
        $hasRefuelTable = false;
    }

    if ($hasRefuelTable) {
        $whereRefuels = str_replace('fl.', 'fr.', $whereSQL);
        $sqlRefuels = "
            SELECT
                fr.bus_id,
                COALESCE(fr.liters, 0) AS liters,
                COALESCE(fr.total_cost, 0) AS cost,
                DATE(fr.created_at) AS refuel_date
            FROM fuel_refuels fr
            $whereRefuels
            ORDER BY fr.created_at ASC
        ";
        try {
            $stmt = $db->prepare($sqlRefuels);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $refuel) {
                $refuelBusId = (string)$refuel['bus_id'];
                $refuelLiters = (float)$refuel['liters'];
                $refuelCost = (float)$refuel['cost'];
                if ($refuelLiters <= 0 && $refuelCost <= 0) {
                    continue;
                }
                $refuelEvents++;
                $totalActualCost += $refuelCost;

                if (!isset($busSummary[$refuelBusId])) {
                    $busSummary[$refuelBusId] = [
                        'bus_id'           => $refuelBusId,
                        'bus_label'        => $refuelBusId !== '' ? $refuelBusId : 'Unknown bus',
                        'plate_number'     => '',
                        'estimated_cost'   => 0.0,
                        'actual_cost'      => 0.0,
                        'total_cost'       => 0.0,
                        'consumed_liters'  => 0.0,
                        'estimated_liters' => 0.0,
                        'actual_liters'    => 0.0,
                        'total_liters'     => 0.0,
                        'total_distance'   => 0.0,
                        'trips_set'        => [],
                        'has_actual'       => false,
                        'logs_count'       => 0
                    ];
                }
                $busSummary[$refuelBusId]['actual_cost'] += $refuelCost;
                $busSummary[$refuelBusId]['actual_liters'] += $refuelLiters;
                $busSummary[$refuelBusId]['has_actual'] = true;

                $refuelDate = (string)$refuel['refuel_date'];
                $refuelBucket = ($granularity === 'day') ? $refuelDate : substr($refuelDate, 0, 7);
                if (!isset($timelineBuckets[$refuelBucket])) {
                    $timelineBuckets[$refuelBucket] = [
                        'estimated_cost'   => 0.0,
                        'actual_cost'      => 0.0,
                        'consumed_liters'  => 0.0,
                        'estimated_liters' => 0.0,
                        'trips_set'        => []
                    ];
                }
                $timelineBuckets[$refuelBucket]['actual_cost'] += $refuelCost;
            }
        } catch (\Throwable $e) {
            // Refuel table unreadable: keep the fuel_logs figures only.
        }
    }

    /* Actual KPL for buses whose refuels live in fuel_refuels: pair the period's
       total distance with the refuel liters purchased (full-tank to full-tank). */
    foreach ($busEfficiency as $busId => &$be) {
        if ($be['actual_liters'] > 0) {
            continue;
        }
        $refuelLiters = (float)($busSummary[$busId]['actual_liters'] ?? 0.0);
        if ($refuelLiters > 0 && $be['total_dist'] > 0) {
            $be['actual_dist'] = $be['total_dist'];
            $be['actual_liters'] = $refuelLiters;
        }
    }
    unset($be);

    $validTripsCount = count($validTripsMap);

    // Primary KPI: Average Fuel Consumed per Trip (Liters)
    $avgFuelConsumedPerTrip = ($validTripsCount > 0 && $totalFuelConsumed > 0)
        ? round($totalFuelConsumed / $validTripsCount, 2)
        : null;

    // Estimated Cost per Trip (₱)
    $avgEstimatedCostPerTrip = ($validTripsCount > 0 && $totalEstimatedCost > 0)
        ? round($totalEstimatedCost / $validTripsCount, 2)
        : null;

    $avgKpl = null;
    $avgLPer100Km = null;
    /* Fleet consumption KPI in km/L: sum the paired actual odometer distance
       (ending - starting reading) and the actual liters consumed for those
       same records - fuel_logs liters when recorded, otherwise the bus's
       fuel_refuels purchases. Buses without actual consumption are excluded
       so no estimated liters are mixed into the actual ratio, and each bus
       contributes exactly once (no double-counting). Only when no actual
       consumption exists at all does it fall back to the estimated
       odometer/KPL totals. */
    $actualKplDistance = 0.0;
    $actualKplLiters = 0.0;
    foreach ($busEfficiency as $be) {
        if ($be['actual_dist'] > 0 && $be['actual_liters'] > 0) {
            $actualKplDistance += $be['actual_dist'];
            $actualKplLiters += $be['actual_liters'];
        }
    }
    $kplRatio = 0.0;
    if ($actualKplDistance > 0 && $actualKplLiters > 0) {
        $kplRatio = $actualKplDistance / $actualKplLiters;
    } elseif ($totalDistance > 0 && $totalFuelConsumed > 0) {
        $kplRatio = $totalDistance / $totalFuelConsumed;
    }
    if ($kplRatio > 0) {
        $avgKpl = round($kplRatio, 2);
        $avgLPer100Km = round(100 / $kplRatio, 2);
    }

    /* -------------------------------------------------------------
       BUILD ESTIMATED FUEL COST BY BUS LIST
       (Sorted descending by estimated_cost)
    ------------------------------------------------------------- */
    $fuelByBus = [];
    $costComparisonByBus = [];

    foreach ($busSummary as $b) {
        $tCount = count($b['trips_set']);
        $estCost = round($b['estimated_cost'], 2);
        $actCost = round($b['actual_cost'], 2);
        $consLit = round($b['consumed_liters'], 2);
        $estLit  = round($b['estimated_liters'], 2);
        $actLit  = round($b['actual_liters'], 2);
        $distVal = round($b['total_distance'], 2);

        $varianceCost = round($actCost - $estCost, 2);
        $variancePct  = ($estCost > 0) ? round(($varianceCost / $estCost) * 100, 1) : null;

        $busEntry = [
            'bus_id'                => $b['bus_id'],
            'bus_label'             => $b['bus_label'],
            'plate_number'          => $b['plate_number'],
            'estimated_cost'        => $estCost,
            'actual_cost'           => $actCost,
            'total_cost'            => $estCost, // Primary chart value
            'total_fuel_cost'       => $estCost,
            'consumed_liters'       => $consLit,
            'estimated_liters'      => $estLit,
            'actual_liters'         => $actLit,
            'total_liters'          => $consLit,
            'total_distance'        => $distVal,
            'trips_count'           => $tCount,
            'has_actual'            => $b['has_actual'],
            'avg_consumed_per_trip' => ($tCount > 0 && $consLit > 0) ? round($consLit / $tCount, 2) : null,
            'avg_cost_per_trip'     => ($tCount > 0 && $estCost > 0) ? round($estCost / $tCount, 2) : null
        ];

        $fuelByBus[] = $busEntry;

        $costComparisonByBus[] = [
            'bus_id'           => $b['bus_id'],
            'bus_label'        => $b['bus_label'],
            'plate_number'     => $b['plate_number'],
            'estimated_cost'   => $estCost,
            'actual_cost'      => $actCost,
            'variance_cost'    => $varianceCost,
            'variance_pct'     => $variancePct,
            'has_actual'       => $b['has_actual'],
            'estimated_liters' => $estLit,
            'actual_liters'    => $actLit,
            'trips_count'      => $tCount
        ];
    }

    // Sort Fuel By Bus descending by estimated_cost
    usort($fuelByBus, fn($a, $b) => $b['estimated_cost'] <=> $a['estimated_cost']);

    // Sort Cost Comparison descending by estimated_cost
    usort($costComparisonByBus, fn($a, $b) => $b['estimated_cost'] <=> $a['estimated_cost']);

    /* -------------------------------------------------------------
       BUILD FUEL EFFICIENCY BY BUS LIST (ACTUAL VS SPECIFICATION)
    ------------------------------------------------------------- */
    $efficiencyByBus = [];
    foreach ($busEfficiency as $be) {
        $hasActual = ($be['actual_liters'] > 0 && $be['actual_dist'] > 0);
        $kpl = 0.0;
        $status = 'estimated';
        $statusLabel = 'Specification KPL';

        if ($hasActual) {
            $kpl = round($be['actual_dist'] / $be['actual_liters'], 2);
            $status = 'actual';
            $statusLabel = 'Actual Full-Tank';
        } elseif ($be['total_dist'] > 0 && $be['total_liters'] > 0) {
            $kpl = round($be['total_dist'] / $be['total_liters'], 2);
            $status = 'estimated';
            $statusLabel = 'Estimated (ODO & KPL)';
        } else {
            $kpl = round($be['spec_kpl'], 2);
            $status = 'specification';
            $statusLabel = 'Specification KPL';
        }

        $efficiencyByBus[] = [
            'bus_id'            => $be['bus_id'],
            'bus_label'         => $be['bus_label'],
            'plate_number'      => $be['plate_number'],
            'avg_kpl'           => $kpl,
            'l_per_100km'       => $kpl > 0 ? round(100 / $kpl, 2) : null,
            'is_actual'         => ($status === 'actual'),
            'status'            => $status,
            'status_label'      => $statusLabel,
            'distance_traveled' => round($be['total_dist'], 2),
            'liters'            => round($be['total_liters'], 2)
        ];
    }
    usort($efficiencyByBus, fn($a, $b) => $b['avg_kpl'] <=> $a['avg_kpl']);

    /* -------------------------------------------------------------
       BUILD TREND OVER TIME (ESTIMATED FUEL COST TREND)
    ------------------------------------------------------------- */
    ksort($timelineBuckets);
    $trendLabels = [];
    $trendEstimatedCost = [];
    $trendActualCost = [];
    $trendEstimatedLiters = [];
    $trendConsumedLiters = [];
    $trendTrips = [];
    $trendAvgConsumed = [];
    $trendAvgEstimatedCost = [];

    foreach ($timelineBuckets as $k => $data) {
        $tCount = count($data['trips_set']);
        $estC   = round($data['estimated_cost'], 2);
        $actC   = round($data['actual_cost'], 2);
        $consL  = round($data['consumed_liters'], 2);
        $estL   = round($data['estimated_liters'], 2);

        $avgCons = ($tCount > 0 && $consL > 0) ? round($consL / $tCount, 2) : null;
        $avgEstC = ($tCount > 0 && $estC > 0) ? round($estC / $tCount, 2) : null;

        // Human-readable label
        $lbl = $k;
        if ($granularity === 'month' && strlen($k) === 7) {
            $lbl = date("M Y", strtotime($k . "-01"));
        } elseif ($granularity === 'day' && strlen($k) === 10) {
            $lbl = date("M d", strtotime($k));
        }

        $trendLabels[]          = $lbl;
        $trendEstimatedCost[]   = $estC;
        $trendActualCost[]      = $actC;
        $trendEstimatedLiters[] = $estL;
        $trendConsumedLiters[]  = $consL;
        $trendTrips[]           = $tCount;
        $trendAvgConsumed[]     = $avgCons;
        $trendAvgEstimatedCost[]= $avgEstC;
    }

    $payload = json_encode([
        'success' => true,
        'summary' => [
            // KPI: Average Fuel Consumed per Trip (Liters)
            'avg_fuel_consumed_per_trip' => $avgFuelConsumedPerTrip,
            'avg_liters_per_trip'        => $avgFuelConsumedPerTrip,
            'total_fuel_consumed'        => round($totalFuelConsumed, 2),
            'total_liters'               => round($totalFuelConsumed, 2),

            // Cost summaries in ₱ (For charts & breakdown)
            'total_estimated_fuel_cost'  => round($totalEstimatedCost, 2),
            'total_actual_fuel_cost'     => round($totalActualCost, 2),
            'total_fuel_cost'            => round($totalEstimatedCost, 2),
            'avg_fuel_cost_per_trip'     => $avgEstimatedCostPerTrip,
            'avg_estimated_cost_per_trip'=> $avgEstimatedCostPerTrip,

            // Operational metrics
            'fuel_trips_count'           => $validTripsCount,
            'actual_trips_count'         => $actualTripsCount,
            'actual_refuel_events'       => $refuelEvents,
            'estimated_trips_count'      => $estimatedTripsCount,
            'total_distance'             => round($totalDistance, 2),
            'avg_kpl'                    => $avgKpl,
            'avg_l_per_100km'            => $avgLPer100Km,
            'default_price'              => $defaultDieselPrice
        ],
        'fuel_by_bus'            => $fuelByBus,
        'cost_comparison_by_bus' => $costComparisonByBus,
        'fuel_trend'             => [
            'granularity'       => $granularity,
            'labels'            => $trendLabels,
            'estimated_cost'    => $trendEstimatedCost,
            'actual_cost'       => $trendActualCost,
            'total_cost'        => $trendEstimatedCost, // Backward compatibility alias
            'estimated_liters'  => $trendEstimatedLiters,
            'consumed_liters'   => $trendConsumedLiters,
            'avg_consumed_trip' => $trendAvgConsumed,
            'avg_cost_per_trip' => $trendAvgEstimatedCost,
            'trip_count'        => $trendTrips
        ],
        'efficiency_by_bus'      => $efficiencyByBus
    ]);

} catch (\Throwable $e) {
    error_log("Finance fuel_analytics error: " . $e->getMessage());
    $payload = $buildFuelFailure('Failed to load fuel analytics: ' . $e->getMessage());
}

ob_end_clean();

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
echo $payload;


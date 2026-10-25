<?php

declare(strict_types=1);

/**
 * Finance Analytics Dashboard — JSON data endpoint.
 *
 * This endpoint previously contained a stale HTML page, so the Finance
 * Analytics dashboard could never parse a response. It now returns the JSON
 * payload finance/views/analytics_dashboard/index.php expects:
 *   financial / remittance / discrepancies / drivers[] / charts{} / range{}.
 *
 * Data semantics mirror the rest of the Finance portal:
 *  - Income : paid live `passenger_trips` (archived rows excluded) plus
 *             `passenger_trip_history` for the period.
 *  - Fuel   : effective gas cost — recorded `fuel_logs.cost` /
 *             `fuel_refuels.total_cost` when available, otherwise actual
 *             liters × current fuel price (Finance → Financial Cost Settings).
 *  - Fixed  : amortization / insurance / GPS / OPTR from Finance → Financial
 *             Cost Settings (App\Core\FinancialCosts), scaled to the period
 *             per active operator; legacy defaults used when unset.
 *  - Net    : gross income − fuel − fixed costs.
 *  - Remit  : live `passenger_trips.remittance_status` + `remittance_logs`
 *             (history rows are settled by definition). Rate clamped at 100%.
 *
 * Every table/column is schema-guarded so missing optional columns degrade to
 * zero/empty values instead of failing the request.
 */

ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/services/DateFilterService.php';

use App\Core\Database;
use App\Core\FinancialCosts;
use App\Auth\Auth;
use Finance\Services\DateFilterService;

validateFinanceRequest('standard', false);
setRequestTimeout(60);

function fad_json(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::check()) {
    fad_json(['success' => false, 'message' => 'Unauthenticated'], 401);
}

$fadRole = strtolower(trim((string) Auth::role()));
if (!in_array($fadRole, ['finance_manager', 'finance_officer'], true)) {
    fad_json(['success' => false, 'message' => 'Forbidden'], 403);
}

$db = Database::tryGet();
if ($db === null) {
    fad_json(['success' => false, 'message' => 'Database connection unavailable.'], 500);
}

/* ------------------------------------------------------------------ */
/* Schema helpers (cached per request)                                 */
/* ------------------------------------------------------------------ */

$fadTableCache = [];
function fad_table(PDO $db, string $table): bool
{
    global $fadTableCache;
    if (array_key_exists($table, $fadTableCache)) {
        return $fadTableCache[$table];
    }
    try {
        $stmt = $db->prepare(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1"
        );
        $stmt->execute([':t' => $table]);
        return $fadTableCache[$table] = (bool) $stmt->fetchColumn();
    } catch (\Throwable $e) {
        return $fadTableCache[$table] = false;
    }
}

$fadColumnCache = [];
function fad_cols(PDO $db, string $table): array
{
    global $fadColumnCache;
    if (array_key_exists($table, $fadColumnCache)) {
        return $fadColumnCache[$table];
    }
    $cols = [];
    if (fad_table($db, $table)) {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM `$table`");
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols[strtolower((string) $row['Field'])] = true;
                }
            }
        } catch (\Throwable $e) {
            $cols = [];
        }
    }
    return $fadColumnCache[$table] = $cols;
}

/* ------------------------------------------------------------------ */
/* Period resolution                                                   */
/* ------------------------------------------------------------------ */

$fadResolved = DateFilterService::normalizeRequest($_GET);
$fadPeriod = $fadResolved['period'];
$fadFrom = $fadResolved['start'];
$fadTo = $fadResolved['end'];
$fadSummary = DateFilterService::summary($fadPeriod, $fadFrom, $fadTo);

try {
    $rangeDays = (int) ((strtotime($fadTo) - strtotime($fadFrom)) / 86400) + 1;
    $rangeDays = max(1, $rangeDays);
    $monthFactor = $rangeDays / 30.4375;

    /* ------------------------------------------------------------------ */
    /* Fetch live + history activity                                       */
    /* ------------------------------------------------------------------ */

    $ptCols = fad_cols($db, 'passenger_trips');
    $pthCols = fad_cols($db, 'passenger_trip_history');

    $hasPassengersTable = fad_table($db, 'passengers');
    $passengerCols = $hasPassengersTable ? fad_cols($db, 'passengers') : [];
    $canJoinPassengerType = $hasPassengersTable
        && isset($passengerCols['id'], $passengerCols['type'], $ptCols['passenger_id']);
    $passengerJoin = $canJoinPassengerType ? ' LEFT JOIN passengers p ON p.id = pt.passenger_id' : '';

    $liveTypeExpr = $canJoinPassengerType ? "COALESCE(p.type,'')" : "''";
    $histTypeExpr = isset($pthCols['type']) ? "COALESCE(pth.type,'')" : "''";
    $hasLiveRemittance = isset($ptCols['remittance_status']);

    $liveWhere = "pt.driver_id IS NOT NULL AND TRIM(pt.driver_id) <> ''"
        . " AND DATE(pt.boarding_time) BETWEEN :lfrom AND :lto"
        . " AND LOWER(COALESCE(pt.payment_status,'')) <> 'rejected'";
    if (isset($ptCols['archived_status'])) {
        $liveWhere .= " AND COALESCE(pt.archived_status,'no') <> 'yes'";
    }

    $liveRemittedExpr = $hasLiveRemittance
        ? "COALESCE(SUM(CASE WHEN LOWER(TRIM(pt.payment_status)) = 'paid'
                AND LOWER(TRIM(COALESCE(pt.remittance_status,'Pending'))) <> 'pending'
                THEN COALESCE(pt.fare,0) ELSE 0 END),0) AS remitted_paid"
        : "COALESCE(SUM(CASE WHEN LOWER(TRIM(pt.payment_status)) = 'paid'
                THEN COALESCE(pt.fare,0) ELSE 0 END),0) AS remitted_paid";

    $liveSql = "
        SELECT pt.driver_id,
            COUNT(DISTINCT CASE WHEN LOWER(TRIM(pt.payment_status)) = 'paid' THEN pt.trip_no END) AS trips,
            COUNT(CASE WHEN LOWER(TRIM(pt.payment_status)) = 'paid'
                AND {$liveTypeExpr} <> 'other' THEN 1 END) AS passengers,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pt.payment_status)) = 'paid'
                AND {$liveTypeExpr} = 'other' THEN COALESCE(pt.fare,0) ELSE 0 END),0) AS other_fare,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pt.payment_status)) = 'paid'
                AND {$liveTypeExpr} <> 'other' THEN COALESCE(pt.fare,0) ELSE 0 END),0) AS passenger_fare,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(pt.payment_status)) = 'paid'
                THEN COALESCE(pt.fare,0) ELSE 0 END),0) AS gross,
            {$liveRemittedExpr}
        FROM passenger_trips pt{$passengerJoin}
        WHERE {$liveWhere}
        GROUP BY pt.driver_id
    ";

    $liveRows = [];
    try {
        $stmt = $db->prepare($liveSql);
        $stmt->execute([':lfrom' => $fadFrom, ':lto' => $fadTo]);
        $liveRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        error_log('Finance analytics: live driver query failed: ' . $e->getMessage());
    }

    $histRows = [];
    try {
        $stmt = $db->prepare("
            SELECT pth.driver_id,
                COUNT(DISTINCT pth.trip_id) AS trips,
                COUNT(CASE WHEN {$histTypeExpr} <> 'other' THEN 1 END) AS passengers,
                COALESCE(SUM(CASE WHEN {$histTypeExpr} = 'other'
                    THEN COALESCE(pth.fare,0) ELSE 0 END),0) AS other_fare,
                COALESCE(SUM(CASE WHEN {$histTypeExpr} <> 'other'
                    THEN COALESCE(pth.fare,0) ELSE 0 END),0) AS passenger_fare,
                COALESCE(SUM(COALESCE(pth.fare,0)),0) AS gross
            FROM passenger_trip_history pth
            WHERE pth.driver_id IS NOT NULL AND TRIM(pth.driver_id) <> ''
              AND DATE(pth.trip_date) BETWEEN :hfrom AND :hto
            GROUP BY pth.driver_id
        ");
        $stmt->execute([':hfrom' => $fadFrom, ':hto' => $fadTo]);
        $histRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        error_log('Finance analytics: history driver query failed: ' . $e->getMessage());
    }

    $driverMap = [];
    $ensureDriver = static function (array &$map, string $driverId): void {
        if ($driverId === '') {
            return;
        }
        if (!isset($map[$driverId])) {
            $map[$driverId] = [
                'live_trips' => 0,
                'live_passengers' => 0,
                'live_gross' => 0.0,
                'live_passenger_fare' => 0.0,
                'live_other_fare' => 0.0,
                'live_remitted_paid' => 0.0,
                'hist_trips' => 0,
                'hist_passengers' => 0,
                'hist_gross' => 0.0,
                'hist_passenger_fare' => 0.0,
                'hist_other_fare' => 0.0,
            ];
        }
    };

    foreach ($liveRows as $row) {
        $did = trim((string) ($row['driver_id'] ?? ''));
        if ($did === '') {
            continue;
        }
        $ensureDriver($driverMap, $did);
        $driverMap[$did]['live_trips'] += (int) ($row['trips'] ?? 0);
        $driverMap[$did]['live_passengers'] += (int) ($row['passengers'] ?? 0);
        $driverMap[$did]['live_gross'] += (float) ($row['gross'] ?? 0);
        $driverMap[$did]['live_passenger_fare'] += (float) ($row['passenger_fare'] ?? 0);
        $driverMap[$did]['live_other_fare'] += (float) ($row['other_fare'] ?? 0);
        $driverMap[$did]['live_remitted_paid'] += (float) ($row['remitted_paid'] ?? 0);
    }

    foreach ($histRows as $row) {
        $did = trim((string) ($row['driver_id'] ?? ''));
        if ($did === '') {
            continue;
        }
        $ensureDriver($driverMap, $did);
        $driverMap[$did]['hist_trips'] += (int) ($row['trips'] ?? 0);
        $driverMap[$did]['hist_passengers'] += (int) ($row['passengers'] ?? 0);
        $driverMap[$did]['hist_gross'] += (float) ($row['gross'] ?? 0);
        $driverMap[$did]['hist_passenger_fare'] += (float) ($row['passenger_fare'] ?? 0);
        $driverMap[$did]['hist_other_fare'] += (float) ($row['other_fare'] ?? 0);
    }

    /* ------------------------------------------------------------------ */
    /* Fuel (total + per driver + daily series)                            */
    /* ------------------------------------------------------------------ */

    $fuelTotal = 0.0;
    $fuelDriver = [];
    $fuelDays = [];
    $fuelCols = fad_cols($db, 'fuel_logs');

    $addFuel = static function (array &$byDriver, array &$byDay, string $driverId, string $day, float $liters, float $cost): void {
        if ($day !== '') {
            if (!isset($byDay[$day])) {
                $byDay[$day] = ['cost' => 0.0, 'liters' => 0.0];
            }
            $byDay[$day]['cost'] += $cost;
            $byDay[$day]['liters'] += $liters;
        }
        if ($driverId !== '') {
            if (!isset($byDriver[$driverId])) {
                $byDriver[$driverId] = ['amount' => 0.0, 'liters' => 0.0];
            }
            $byDriver[$driverId]['amount'] += $cost;
            $byDriver[$driverId]['liters'] += $liters;
        }
    };

    if (isset($fuelCols['created_at'], $fuelCols['liters'], $fuelCols['cost'])) {
        $hasFuelDriver = isset($fuelCols['driver_id']);
        try {
            $stmt = $db->prepare("
                SELECT " . ($hasFuelDriver ? "COALESCE(NULLIF(TRIM(driver_id),''),'')" : "''") . " AS driver_id,
                    DATE(created_at) AS day,
                    COALESCE(SUM(liters),0) AS liters,
                    COALESCE(SUM(cost),0) AS cost
                FROM fuel_logs
                WHERE DATE(created_at) BETWEEN :ffrom AND :fto
                GROUP BY driver_id, day
            ");
            $stmt->execute([':ffrom' => $fadFrom, ':fto' => $fadTo]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $liters = (float) ($row['liters'] ?? 0);
                $cost = (float) ($row['cost'] ?? 0);
                $effectiveCost = FinancialCosts::gasAmount($liters, $cost);
                $fuelTotal += $effectiveCost;
                $addFuel($fuelDriver, $fuelDays, trim((string) ($row['driver_id'] ?? '')), (string) ($row['day'] ?? ''), $liters, $effectiveCost);
            }
        } catch (\Throwable $e) {
            error_log('Finance analytics: fuel_logs query failed: ' . $e->getMessage());
        }
    }

    if (fad_table($db, 'fuel_refuels')) {
        $frCols = fad_cols($db, 'fuel_refuels');
        if (isset($frCols['created_at'], $frCols['liters'], $frCols['total_cost'])) {
            $hasRefuelDriver = isset($frCols['driver_id']);
            try {
                $stmt = $db->prepare("
                    SELECT " . ($hasRefuelDriver ? "COALESCE(NULLIF(TRIM(driver_id),''),'')" : "''") . " AS driver_id,
                        DATE(created_at) AS day,
                        COALESCE(SUM(liters),0) AS liters,
                        COALESCE(SUM(total_cost),0) AS cost
                    FROM fuel_refuels
                    WHERE DATE(created_at) BETWEEN :ffrom AND :fto
                    GROUP BY driver_id, day
                ");
                $stmt->execute([':ffrom' => $fadFrom, ':fto' => $fadTo]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $liters = (float) ($row['liters'] ?? 0);
                    $cost = (float) ($row['cost'] ?? 0);
                    $effectiveCost = FinancialCosts::gasAmount($liters, $cost);
                    $fuelTotal += $effectiveCost;
                    $addFuel($fuelDriver, $fuelDays, trim((string) ($row['driver_id'] ?? '')), (string) ($row['day'] ?? ''), $liters, $effectiveCost);
                }
            } catch (\Throwable $e) {
                error_log('Finance analytics: fuel_refuels query failed: ' . $e->getMessage());
            }
        }
    }

    $fuelTotal = round($fuelTotal, 2);

    /* ------------------------------------------------------------------ */
    /* Remittance (per driver, latest log in period)                       */
    /* ------------------------------------------------------------------ */

    $remitMap = [];
    if (fad_table($db, 'remittance_logs')) {
        $rlCols = fad_cols($db, 'remittance_logs');
        if (isset($rlCols['created_at'], $rlCols['driver_id'])) {
            $rlExpected = isset($rlCols['expected_cash']) ? 'COALESCE(expected_cash,0)' : '0';
            $rlActual = isset($rlCols['actual_cash']) ? 'COALESCE(actual_cash,0)' : '0';
            $rlDisc = isset($rlCols['discrepancy_amount']) ? 'COALESCE(discrepancy_amount,0)' : '0';
            $rlStatus = isset($rlCols['status']) ? 'status' : "''";

            try {
                $stmt = $db->prepare("
                    SELECT driver_id,
                        {$rlExpected} AS expected_cash,
                        {$rlActual} AS actual_cash,
                        {$rlDisc} AS discrepancy_amount,
                        {$rlStatus} AS status,
                        created_at
                    FROM remittance_logs
                    WHERE driver_id IS NOT NULL AND TRIM(driver_id) <> ''
                      AND DATE(created_at) BETWEEN :rfrom AND :rto
                ");
                $stmt->execute([':rfrom' => $fadFrom, ':rto' => $fadTo]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $did = trim((string) ($row['driver_id'] ?? ''));
                    if ($did === '') {
                        continue;
                    }
                    $created = (string) ($row['created_at'] ?? '');
                    if (isset($remitMap[$did]) && $remitMap[$did]['created_at'] > $created) {
                        continue;
                    }
                    $rawStatus = trim((string) ($row['status'] ?? ''));
                    $lower = strtolower($rawStatus);
                    if (in_array($lower, ['completed', 'matched', 'paid', 'remitted'], true)) {
                        $status = 'Remitted';
                    } elseif ($lower === 'discrepancy') {
                        $status = 'Discrepancy';
                    } elseif ($lower === 'no remit') {
                        $status = 'No Remit';
                    } else {
                        $status = $rawStatus !== '' ? $rawStatus : 'Pending';
                    }
                    $remitMap[$did] = [
                        'status' => $status,
                        'expected' => round((float) ($row['expected_cash'] ?? 0), 2),
                        'actual' => round((float) ($row['actual_cash'] ?? 0), 2),
                        'variance' => round((float) ($row['discrepancy_amount'] ?? 0), 2),
                        'created_at' => $created,
                    ];
                }
            } catch (\Throwable $e) {
                error_log('Finance analytics: remittance_logs query failed: ' . $e->getMessage());
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Driver names / photos                                               */
    /* ------------------------------------------------------------------ */

    $driverIds = array_keys($driverMap);
    $driverMeta = [];
    if (!empty($driverIds)) {
        $driverCols = fad_cols($db, 'drivers');
        $photoSelect = isset($driverCols['photo']) ? ', MAX(d.photo) AS photo' : ", '' AS photo";
        $placeholders = [];
        $params = [];
        foreach ($driverIds as $i => $did) {
            $key = ':d' . $i;
            $placeholders[] = $key;
            $params[$key] = $did;
        }
        try {
            $stmt = $db->prepare("
                SELECT d.driver_id,
                    MAX(d.full_name) AS full_name
                    $photoSelect
                FROM drivers d
                WHERE d.driver_id IN (" . implode(', ', $placeholders) . ")
                GROUP BY d.driver_id
            ");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $driverMeta[(string) $row['driver_id']] = [
                    'name' => trim((string) ($row['full_name'] ?? '')),
                    'photo' => trim((string) ($row['photo'] ?? '')),
                ];
            }
        } catch (\Throwable $e) {
            error_log('Finance analytics: driver lookup failed: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* Fixed cost model (Finance → Financial Cost Settings)                */
    /* ------------------------------------------------------------------ */

    /* ------------------------------------------------------------------ */
    /* Build per-driver rows                                               */
    /* ------------------------------------------------------------------ */

    $drivers = [];
    $remittedAmount = 0.0;
    $paidExpected = 0.0;
    $fixedAmortization = 0.0;
    $fixedInsurance = 0.0;
    $fixedGps = 0.0;
    $fixedOptr = 0.0;
    $discrepancyCases = 0;
    $discrepancyAmount = 0.0;

    foreach ($driverMap as $did => $d) {
        $gross = round($d['live_gross'] + $d['hist_gross'], 2);
        $passengerFare = round($d['live_passenger_fare'] + $d['hist_passenger_fare'], 2);
        $otherFare = round($d['live_other_fare'] + $d['hist_other_fare'], 2);
        $trips = (int) $d['live_trips'] + (int) $d['hist_trips'];
        $passengers = (int) $d['live_passengers'] + (int) $d['hist_passengers'];

        $gasAmount = round((float) ($fuelDriver[$did]['amount'] ?? 0), 2);
        $gasLiters = round((float) ($fuelDriver[$did]['liters'] ?? 0), 2);

        $hasActivity = $trips > 0 || $gross != 0.0;
        $fixed = $hasActivity
            ? FinancialCosts::fixedCosts($monthFactor, ['gross' => $gross, 'trips' => $trips])
            : ['amortization' => 0.0, 'insurance' => 0.0, 'gps' => 0.0, 'optr' => 0.0, 'total' => 0.0, 'monthly_total' => 0.0, 'months' => $monthFactor];
        $fixedTotal = $fixed['total'];
        $netIncome = $hasActivity ? round($gross - $gasAmount - $fixedTotal, 2) : 0.0;

        if ($hasActivity) {
            $fixedAmortization += $fixed['amortization'];
            $fixedInsurance += $fixed['insurance'];
            $fixedGps += $fixed['gps'];
            $fixedOptr += $fixed['optr'];
        }

        $hasLive = (int) $d['live_trips'] > 0 || (float) $d['live_gross'] > 0;
        $hasArchive = (int) $d['hist_trips'] > 0 || (float) $d['hist_gross'] > 0;
        $allLiveRemitted = $hasLive
            && (float) $d['live_gross'] > 0
            && (float) $d['live_remitted_paid'] >= (float) $d['live_gross'];

        if (!$hasLive && $hasArchive) {
            $remitStatus = 'History';
            $remitExpected = 0.0;
            $remitActual = 0.0;
            $remitVariance = 0.0;
        } elseif (isset($remitMap[$did])) {
            $remitStatus = $remitMap[$did]['status'];
            $remitExpected = $remitMap[$did]['expected'];
            $remitActual = $remitMap[$did]['actual'];
            $remitVariance = $remitMap[$did]['variance'];
            if ($remitVariance == 0.0 && $remitExpected > 0 && $remitActual != $remitExpected) {
                $remitVariance = round($remitActual - $remitExpected, 2);
            }
        } elseif ($hasLive) {
            $remitStatus = $allLiveRemitted ? 'Remitted' : 'Pending';
            $remitExpected = 0.0;
            $remitActual = 0.0;
            $remitVariance = 0.0;
        } else {
            $remitStatus = 'No Activity';
            $remitExpected = 0.0;
            $remitActual = 0.0;
            $remitVariance = 0.0;
        }

        if ($remitStatus === 'Discrepancy') {
            $discrepancyCases++;
            $discrepancyAmount += abs($remitVariance);
        }

        $meta = $driverMeta[$did] ?? ['name' => '', 'photo' => ''];
        $name = $meta['name'] !== '' ? $meta['name'] : ('Driver ' . $did);

        $remittedAmount += (float) $d['live_remitted_paid'] + (float) $d['hist_gross'];
        $paidExpected += (float) $d['live_gross'] + (float) $d['hist_gross'];

        $drivers[] = [
            'driver_id' => (string) $did,
            'name' => $name,
            'photo' => $meta['photo'],
            'trips' => ['total' => $trips],
            'passengers' => ['total' => $passengers],
            'fare' => [
                'passenger' => $passengerFare,
                'other' => $otherFare,
                'total' => round($passengerFare + $otherFare, 2),
            ],
            'gas' => [
                'liters' => $gasLiters,
                'amount' => $gasAmount,
            ],
            'fixed_costs' => [
                'amortization' => $fixed['amortization'],
                'insurance' => $fixed['insurance'],
                'gps' => $fixed['gps'],
                'optr' => $fixed['optr'],
                'total' => $fixedTotal,
            ],
            'gross_income' => $gross,
            'net_income' => $netIncome,
            'remittance_status' => $remitStatus,
            'remitted_expected' => $remitExpected,
            'remitted_actual' => $remitActual,
            'remitted_variance' => $remitVariance,
        ];
    }

    usort($drivers, static function (array $a, array $b): int {
        return ($b['gross_income'] <=> $a['gross_income']);
    });

    $grossTotal = 0.0;
    foreach ($drivers as $driver) {
        $grossTotal += (float) $driver['gross_income'];
    }
    $grossTotal = round($grossTotal, 2);

    $fixedAmortization = round($fixedAmortization, 2);
    $fixedInsurance = round($fixedInsurance, 2);
    $fixedGps = round($fixedGps, 2);
    $fixedOptr = round($fixedOptr, 2);
    $fixedTotal = round($fixedAmortization + $fixedInsurance + $fixedGps + $fixedOptr, 2);

    $netTotal = round($grossTotal - $fuelTotal - $fixedTotal, 2);
    $remittanceRate = $paidExpected > 0
        ? min(100.0, round(($remittedAmount / $paidExpected) * 100, 1))
        : null;

    /* ------------------------------------------------------------------ */
    /* Chart series                                                        */
    /* ------------------------------------------------------------------ */

    $fareSeries = [];
    try {
        $stmt = $db->prepare("
            SELECT DATE(pt.boarding_time) AS k, COALESCE(SUM(COALESCE(pt.fare,0)),0) AS v
            FROM passenger_trips pt
            WHERE LOWER(TRIM(pt.payment_status)) = 'paid'
              AND DATE(pt.boarding_time) BETWEEN :lfrom AND :lto
              " . (isset($ptCols['archived_status']) ? "AND COALESCE(pt.archived_status,'no') <> 'yes'" : "") . "
            GROUP BY k
        ");
        $stmt->execute([':lfrom' => $fadFrom, ':lto' => $fadTo]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (string) ($row['k'] ?? '');
            if ($key !== '') {
                $fareSeries[$key] = round(($fareSeries[$key] ?? 0) + (float) ($row['v'] ?? 0), 2);
            }
        }
    } catch (\Throwable $e) {
        error_log('Finance analytics: fare series (live) failed: ' . $e->getMessage());
    }

    try {
        $stmt = $db->prepare("
            SELECT pth.trip_date AS k, COALESCE(SUM(COALESCE(pth.fare,0)),0) AS v
            FROM passenger_trip_history pth
            WHERE DATE(pth.trip_date) BETWEEN :hfrom AND :hto
            GROUP BY k
        ");
        $stmt->execute([':hfrom' => $fadFrom, ':hto' => $fadTo]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (string) ($row['k'] ?? '');
            if ($key !== '') {
                $fareSeries[$key] = round(($fareSeries[$key] ?? 0) + (float) ($row['v'] ?? 0), 2);
            }
        }
    } catch (\Throwable $e) {
        error_log('Finance analytics: fare series (history) failed: ' . $e->getMessage());
    }
    ksort($fareSeries);

    $fuelSeries = [];
    foreach ($fuelDays as $day => $entry) {
        $fuelSeries[(string) $day] = ['cost' => round((float) ($entry['cost'] ?? 0), 2)];
    }
    ksort($fuelSeries);

    $busMap = [];
    try {
        $stmt = $db->prepare("
            SELECT pt.bus_no AS bus,
                COUNT(DISTINCT pt.trip_no) AS trips,
                COALESCE(SUM(COALESCE(pt.fare,0)),0) AS gross
            FROM passenger_trips pt
            WHERE LOWER(TRIM(pt.payment_status)) = 'paid'
              AND DATE(pt.boarding_time) BETWEEN :lfrom AND :lto
              AND pt.bus_no IS NOT NULL AND TRIM(pt.bus_no) <> ''
              " . (isset($ptCols['archived_status']) ? "AND COALESCE(pt.archived_status,'no') <> 'yes'" : "") . "
            GROUP BY pt.bus_no
        ");
        $stmt->execute([':lfrom' => $fadFrom, ':lto' => $fadTo]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $bus = trim((string) ($row['bus'] ?? ''));
            if ($bus === '') {
                continue;
            }
            if (!isset($busMap[$bus])) {
                $busMap[$bus] = ['bus' => $bus, 'trips' => 0, 'gross_income' => 0.0];
            }
            $busMap[$bus]['trips'] += (int) ($row['trips'] ?? 0);
            $busMap[$bus]['gross_income'] += (float) ($row['gross'] ?? 0);
        }
    } catch (\Throwable $e) {
        error_log('Finance analytics: per-bus series (live) failed: ' . $e->getMessage());
    }

    try {
        $stmt = $db->prepare("
            SELECT pth.bus_number AS bus,
                COUNT(DISTINCT pth.trip_id) AS trips,
                COALESCE(SUM(COALESCE(pth.fare,0)),0) AS gross
            FROM passenger_trip_history pth
            WHERE DATE(pth.trip_date) BETWEEN :hfrom AND :hto
              AND pth.bus_number IS NOT NULL AND TRIM(pth.bus_number) <> ''
            GROUP BY pth.bus_number
        ");
        $stmt->execute([':hfrom' => $fadFrom, ':hto' => $fadTo]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $bus = trim((string) ($row['bus'] ?? ''));
            if ($bus === '') {
                continue;
            }
            if (!isset($busMap[$bus])) {
                $busMap[$bus] = ['bus' => $bus, 'trips' => 0, 'gross_income' => 0.0];
            }
            $busMap[$bus]['trips'] += (int) ($row['trips'] ?? 0);
            $busMap[$bus]['gross_income'] += (float) ($row['gross'] ?? 0);
        }
    } catch (\Throwable $e) {
        error_log('Finance analytics: per-bus series (history) failed: ' . $e->getMessage());
    }

    $perBus = array_values($busMap);
    usort($perBus, static function (array $a, array $b): int {
        return ($b['gross_income'] <=> $a['gross_income']);
    });
    $perBus = array_slice($perBus, 0, 12);
    foreach ($perBus as $i => $bus) {
        $perBus[$i]['gross_income'] = round((float) $bus['gross_income'], 2);
    }

    $passengerTypes = [];
    try {
        $stmt = $db->prepare("
            SELECT COALESCE(NULLIF(TRIM(pth.passenger_type),''),'Regular') AS t, COUNT(*) AS c
            FROM passenger_trip_history pth
            WHERE DATE(pth.trip_date) BETWEEN :hfrom AND :hto
            GROUP BY t
            ORDER BY c DESC
        ");
        $stmt->execute([':hfrom' => $fadFrom, ':hto' => $fadTo]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $type = trim((string) ($row['t'] ?? ''));
            if ($type !== '') {
                $passengerTypes[$type] = (int) ($row['c'] ?? 0);
            }
        }
    } catch (\Throwable $e) {
        error_log('Finance analytics: passenger types (history) failed: ' . $e->getMessage());
    }

    if (empty($passengerTypes) && $canJoinPassengerType && isset($passengerCols['discount_type'])) {
        try {
            $stmt = $db->prepare("
                SELECT COALESCE(NULLIF(TRIM(p.discount_type),''),'Regular') AS t, COUNT(*) AS c
                FROM passenger_trips pt
                JOIN passengers p ON p.id = pt.passenger_id
                WHERE LOWER(TRIM(pt.payment_status)) = 'paid'
                  AND DATE(pt.boarding_time) BETWEEN :lfrom AND :lto
                  " . (isset($ptCols['archived_status']) ? "AND COALESCE(pt.archived_status,'no') <> 'yes'" : "") . "
                GROUP BY t
                ORDER BY c DESC
            ");
            $stmt->execute([':lfrom' => $fadFrom, ':lto' => $fadTo]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $type = trim((string) ($row['t'] ?? ''));
                if ($type !== '') {
                    $passengerTypes[$type] = (int) ($row['c'] ?? 0);
                }
            }
        } catch (\Throwable $e) {
            error_log('Finance analytics: passenger types (live) failed: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ */
    /* Response                                                            */
    /* ------------------------------------------------------------------ */

    fad_json([
        'success' => true,
        'period' => $fadPeriod,
        'summary' => $fadSummary,
        'range' => [
            'start' => $fadFrom,
            'end' => $fadTo,
        ],
        'financial' => [
            'gross_income' => $grossTotal,
            'net_income' => $netTotal,
            'fuel' => $fuelTotal,
            'other_expenses' => $fixedTotal,
            'expenses_breakdown' => [
                'fuel' => $fuelTotal,
                'amortization' => $fixedAmortization,
                'insurance' => $fixedInsurance,
                'gps' => $fixedGps,
                'optr' => $fixedOptr,
            ],
        ],
        'remittance' => [
            'remitted_amount' => round($remittedAmount, 2),
            'paid_expected' => round($paidExpected, 2),
            'remittance_rate' => $remittanceRate,
            'active_drivers' => count($drivers),
        ],
        'discrepancies' => [
            'cases' => $discrepancyCases,
            'amount' => round($discrepancyAmount, 2),
        ],
        'drivers' => $drivers,
        'charts' => [
            'fare_over_time' => (object) $fareSeries,
            'fuel_over_time' => (object) $fuelSeries,
            'per_bus' => $perBus,
            'passenger_types' => (object) $passengerTypes,
        ],
        'generated_at' => date('Y-m-d H:i:s'),
    ]);
} catch (\Throwable $e) {
    error_log('Finance analytics dashboard error: ' . $e->getMessage());
    fad_json([
        'success' => false,
        'message' => 'Unable to load analytics data.',
    ], 500);
}

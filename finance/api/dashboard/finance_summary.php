<?php

/**
 * Finance Dashboard — Remittance & Profit Summary (read-only)
 *
 * Reuses the existing table/status semantics of the Finance portal:
 *  - Finalized remittances  : `remittance_logs` (status Completed/Matched/Paid/Remitted,
 *                             Discrepancy, No Remit) — same mapping used by
 *                             finance/api/analytics/analytics_dashboard.php.
 *  - Pending remittances    : paid trip collections not yet finalized — live
 *                             `bus_trips.submitted = 0` (+ paid `passenger_trips`),
 *                             and `passenger_trip_history.remittance_status = 'Pending'`
 *                             when those columns exist (same guard as RemittanceModel).
 *  - Profit                 : paid passenger income (live + history) minus
 *                             actual refuel cost — `fuel_refuels.total_cost`
 *                             (falling back to `fuel_logs.cost` for legacy
 *                             refuel rows) — the same formula used by
 *                             admin/api/dashboard/stats.php (net_profit) and
 *                             finance/services/DashboardService.php.
 *
 * No schema changes, no writes. Missing tables/columns degrade to empty payloads.
 */

declare(strict_types=1);

ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/services/DateFilterService.php';

use App\Core\Database;
use App\Auth\Auth;

validateFinanceRequest('standard', false);
setRequestTimeout(30);

function fs_json(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

/* Finance Manager / Finance Officer only (matches the dashboard page gate). */
if (!Auth::check()) {
    http_response_code(401);
    fs_json(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}
$fsRole = strtolower(trim((string) Auth::role()));
if (!in_array($fsRole, ['finance_manager', 'finance_officer'], true)) {
    http_response_code(403);
    fs_json(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$db = Database::tryGet();

if ($db === null) {
    fs_json(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

/* ------------------------------------------------------------------ */
/* Schema helpers                                                      */
/* ------------------------------------------------------------------ */

function fs_table_exists(PDO $db, string $table): bool
{
    try {
        $stmt = $db->prepare(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1"
        );
        $stmt->execute([':t' => $table]);
        return (bool) $stmt->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

function fs_columns(PDO $db, string $table): array
{
    $cols = [];
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `$table`");
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cols[strtolower((string) $row['Field'])] = true;
            }
        }
    } catch (\Throwable $e) {
        // Table unavailable — caller treats as empty.
    }
    return $cols;
}

function fs_pct(float $current, float $previous): ?float
{
    if (abs($previous) < 0.00001) {
        return null;
    }
    return round((($current - $previous) / abs($previous)) * 100, 1);
}

/* ------------------------------------------------------------------ */
/* Range resolution (shared Finance period model: accepts canonical      */
/* period/from/to plus legacy range/fromDate/toDate, month+year, year)  */
/* ------------------------------------------------------------------ */

function fs_resolve_range(): array
{
    $resolved = \Finance\Services\DateFilterService::normalizeRequest($_GET);
    $previous = \Finance\Services\DateFilterService::previousRange(
        $resolved['period'],
        $resolved['start'],
        $resolved['end']
    );

    return [
        'from'          => $resolved['start'],
        'to'            => $resolved['end'],
        'previous_from' => $previous[0],
        'previous_to'   => $previous[1],
        'label'         => \Finance\Services\DateFilterService::summary(
            $resolved['period'],
            $resolved['start'],
            $resolved['end']
        ),
    ];
}

/* ------------------------------------------------------------------ */
/* Bucket helpers                                                      */
/* ------------------------------------------------------------------ */

function fs_build_buckets(string $from, string $to, string $granularity): array
{
    $keys = [];
    $labels = [];
    $cursor = new DateTime($from);
    $end = new DateTime($to);

    if ($granularity === 'month') {
        $cursor->modify('first day of this month');
        $end->modify('first day of this month');
        while ($cursor <= $end) {
            $keys[] = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $cursor->modify('+1 month');
        }
        return [$keys, $labels];
    }

    while ($cursor <= $end) {
        $keys[] = $cursor->format('Y-m-d');
        $labels[] = $cursor->format('M j');
        $cursor->modify('+1 day');
    }

    return [$keys, $labels];
}

function fs_series_from_map(array $map, array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[] = round((float) ($map[$key] ?? 0), 2);
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Main                                                                */
/* ------------------------------------------------------------------ */

try {
    $range = fs_resolve_range();

    $days = (int) ((strtotime($range['to']) - strtotime($range['from'])) / 86400) + 1;
    $granularity = $days <= 62 ? 'day' : 'month';
    $bucketFormat = $granularity === 'day' ? '%Y-%m-%d' : '%Y-%m';

    [$bucketKeys, $bucketLabels] = fs_build_buckets($range['from'], $range['to'], $granularity);
    [$prevBucketKeys] = fs_build_buckets($range['previous_from'], $range['previous_to'], $granularity);

    $from = $range['from'];
    $to   = $range['to'];
    $prevFrom = $range['previous_from'];
    $prevTo   = $range['previous_to'];

    /* ---------------- Profit: income (live + history) ---------------- */

    $incomeBucket = [];
    $incomePrevious = 0.0;
    $fuelBucket = [];
    $fuelPrevious = 0.0;
    $incomeCurrent = 0.0;
    $fuelCurrent = 0.0;

    $collectPair = static function (array &$map, string $key, float $value): void {
        $map[$key] = ($map[$key] ?? 0) + $value;
    };

    try {
        $stmt = $db->prepare(
            "SELECT DATE_FORMAT(boarding_time, '$bucketFormat') AS k, COALESCE(SUM(fare),0) AS v
             FROM passenger_trips
             WHERE LOWER(TRIM(payment_status)) = 'paid'
               AND DATE(boarding_time) BETWEEN :f AND :t
             GROUP BY k"
        );
        $stmt->execute([':f' => $from, ':t' => $to]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $collectPair($incomeBucket, (string) $row['k'], (float) $row['v']);
            $incomeCurrent += (float) $row['v'];
        }
    } catch (\Throwable $e) {
    }

    try {
        $stmt = $db->prepare(
            "SELECT DATE_FORMAT(trip_date, '$bucketFormat') AS k, COALESCE(SUM(fare),0) AS v
             FROM passenger_trip_history
             WHERE DATE(trip_date) BETWEEN :f AND :t
             GROUP BY k"
        );
        $stmt->execute([':f' => $from, ':t' => $to]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $collectPair($incomeBucket, (string) $row['k'], (float) $row['v']);
            $incomeCurrent += (float) $row['v'];
        }
    } catch (\Throwable $e) {
    }

    if (fs_table_exists($db, 'passenger_trips')) {
        try {
            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(fare),0) FROM passenger_trips
                 WHERE LOWER(TRIM(payment_status)) = 'paid'
                   AND DATE(boarding_time) BETWEEN :f AND :t"
            );
            $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
            $prevLive = (float) $stmt->fetchColumn();
            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(fare),0) FROM passenger_trip_history
                 WHERE DATE(trip_date) BETWEEN :f AND :t"
            );
            $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
            $incomePrevious = $prevLive + (float) $stmt->fetchColumn();
        } catch (\Throwable $e) {
        }
    }

    try {
        $stmt = $db->prepare(
            "SELECT DATE_FORMAT(created_at, '$bucketFormat') AS k, COALESCE(SUM(cost),0) AS v
             FROM fuel_logs
             WHERE DATE(created_at) BETWEEN :f AND :t
             GROUP BY k"
        );
        $stmt->execute([':f' => $from, ':t' => $to]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $collectPair($fuelBucket, (string) $row['k'], (float) $row['v']);
            $fuelCurrent += (float) $row['v'];
        }

        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(cost),0) FROM fuel_logs
             WHERE DATE(created_at) BETWEEN :f AND :t"
        );
        $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
        $fuelPrevious = (float) $stmt->fetchColumn();
    } catch (\Throwable $e) {
    }

    /* Actual refuel transactions live in fuel_refuels for generator-produced
       history (fuel_logs carries the per-trip odometer/KPL rows with zero cost). */
    if (fs_table_exists($db, 'fuel_refuels')) {
        try {
            $stmt = $db->prepare(
                "SELECT DATE_FORMAT(created_at, '$bucketFormat') AS k, COALESCE(SUM(total_cost),0) AS v
                 FROM fuel_refuels
                 WHERE DATE(created_at) BETWEEN :f AND :t
                 GROUP BY k"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $collectPair($fuelBucket, (string) $row['k'], (float) $row['v']);
                $fuelCurrent += (float) $row['v'];
            }

            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(total_cost),0) FROM fuel_refuels
                 WHERE DATE(created_at) BETWEEN :f AND :t"
            );
            $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
            $fuelPrevious += (float) $stmt->fetchColumn();
        } catch (\Throwable $e) {
        }
    }

    $profitCurrent = round($incomeCurrent - $fuelCurrent, 2);
    $profitPrevious = round($incomePrevious - $fuelPrevious, 2);

    $incomeSeries = fs_series_from_map($incomeBucket, $bucketKeys);
    $fuelSeries = fs_series_from_map($fuelBucket, $bucketKeys);
    $profitSeries = [];
    foreach ($bucketKeys as $i => $key) {
        $profitSeries[] = round(($incomeSeries[$i] ?? 0) - ($fuelSeries[$i] ?? 0), 2);
    }

    /* ---------------- Remittances ---------------- */

    $rlCols = fs_table_exists($db, 'remittance_logs') ? fs_columns($db, 'remittance_logs') : [];
    $hasLogs = !empty($rlCols) && isset($rlCols['created_at']) && isset($rlCols['status']);

    $amountExpr = null;
    if ($hasLogs) {
        $parts = [];
        foreach (['actual_cash', 'expected_cash', 'total_collection'] as $c) {
            if (isset($rlCols[$c])) {
                $parts[] = "NULLIF(`$c`, 0)";
            }
        }
        if (!empty($parts)) {
            $amountExpr = 'COALESCE(' . implode(', ', $parts) . ', 0)';
        } elseif (isset($rlCols['total_collection'])) {
            $amountExpr = 'COALESCE(total_collection, 0)';
        } else {
            $amountExpr = '0';
        }
    }

    $completedBuckets = [];
    $pendingBuckets = [];
    $completedCurrent = 0.0;
    $completedAmountCurrent = 0.0;
    $pendingCurrent = 0.0;
    $pendingAmountCurrent = 0.0;
    $completedPrevious = 0.0;
    $completedAmountPrevious = 0.0;
    $pendingPrevious = 0.0;
    $pendingAmountPrevious = 0.0;
    $discrepancyCurrent = 0.0;
    $discrepancyAmountCurrent = 0.0;
    $noRemitCurrent = 0.0;
    $noRemitAmountCurrent = 0.0;
    $expectedCurrent = 0.0;
    $expectedPrevious = 0.0;
    $actualCurrent = 0.0;
    $actualPrevious = 0.0;
    $hasExpectedCash = $hasLogs && isset($rlCols['expected_cash']);
    $hasActualCash = $hasLogs && isset($rlCols['actual_cash']);

    $driverStats = [];
    $ensureDriver = static function (array &$map, string $driverId): void {
        if ($driverId === '') {
            return;
        }
        if (!isset($map[$driverId])) {
            $map[$driverId] = [
                'driver_id'          => $driverId,
                'trips'              => 0,
                'completed'          => 0,
                'completed_amount'   => 0.0,
                'pending'            => 0,
                'pending_amount'     => 0.0,
                'discrepancy'        => 0,
                'last_remit_at'      => '',
            ];
        }
    };

    $statusCounts = [
        'completed'   => ['count' => 0.0, 'amount' => 0.0],
        'pending'     => ['count' => 0.0, 'amount' => 0.0],
        'discrepancy' => ['count' => 0.0, 'amount' => 0.0],
        'no_remit'    => ['count' => 0.0, 'amount' => 0.0],
    ];

    if ($hasLogs) {
        try {
            $stmt = $db->prepare(
                "SELECT DATE_FORMAT(created_at, '$bucketFormat') AS k,
                        COUNT(*) AS c,
                        COALESCE(SUM($amountExpr), 0) AS amt
                 FROM remittance_logs
                 WHERE DATE(created_at) BETWEEN :f AND :t
                   AND LOWER(TRIM(status)) IN ('completed','matched','paid','remitted')
                 GROUP BY k"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $completedBuckets[(string) $row['k']] = (int) $row['c'];
                $completedCurrent += (int) $row['c'];
                $completedAmountCurrent += (float) $row['amt'];
                $statusCounts['completed']['count'] += (int) $row['c'];
                $statusCounts['completed']['amount'] += (float) $row['amt'];
            }
        } catch (\Throwable $e) {
        }

        try {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS c, COALESCE(SUM($amountExpr), 0) AS amt
                 FROM remittance_logs
                 WHERE DATE(created_at) BETWEEN :f AND :t
                   AND LOWER(TRIM(status)) IN ('completed','matched','paid','remitted')"
            );
            $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $completedPrevious = (float) ($row['c'] ?? 0);
            $completedAmountPrevious = (float) ($row['amt'] ?? 0);
        } catch (\Throwable $e) {
        }

        try {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS c, COALESCE(SUM(COALESCE(discrepancy_amount, 0)), 0) AS amt
                 FROM remittance_logs
                 WHERE DATE(created_at) BETWEEN :f AND :t
                   AND (COALESCE(discrepancy_amount, 0) > 0 OR LOWER(TRIM(status)) = 'discrepancy')"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $discrepancyCurrent = (int) ($row['c'] ?? 0);
            $discrepancyAmountCurrent = (float) ($row['amt'] ?? 0);
            $statusCounts['discrepancy']['count'] = $discrepancyCurrent;
            $statusCounts['discrepancy']['amount'] = $discrepancyAmountCurrent;
        } catch (\Throwable $e) {
        }

        try {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS c, COALESCE(SUM($amountExpr), 0) AS amt
                 FROM remittance_logs
                 WHERE DATE(created_at) BETWEEN :f AND :t
                   AND LOWER(TRIM(status)) = 'no remit'"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $noRemitCurrent = (int) ($row['c'] ?? 0);
            $noRemitAmountCurrent = (float) ($row['amt'] ?? 0);
            $statusCounts['no_remit']['count'] = $noRemitCurrent;
            $statusCounts['no_remit']['amount'] = $noRemitAmountCurrent;
        } catch (\Throwable $e) {
        }

        if ($hasExpectedCash || $hasActualCash) {
            $cashSelect = [];
            if ($hasExpectedCash) {
                $cashSelect[] = 'COALESCE(SUM(COALESCE(expected_cash, 0)), 0) AS expected';
            }
            if ($hasActualCash) {
                $cashSelect[] = 'COALESCE(SUM(COALESCE(actual_cash, 0)), 0) AS actual';
            }
            $cashSql = 'SELECT ' . implode(', ', $cashSelect) . '
                        FROM remittance_logs
                        WHERE DATE(created_at) BETWEEN :f AND :t';

            try {
                $stmt = $db->prepare($cashSql);
                $stmt->execute([':f' => $from, ':t' => $to]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                if ($hasExpectedCash) {
                    $expectedCurrent = (float) ($row['expected'] ?? 0);
                }
                if ($hasActualCash) {
                    $actualCurrent = (float) ($row['actual'] ?? 0);
                }
            } catch (\Throwable $e) {
            }

            try {
                $stmt = $db->prepare($cashSql);
                $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                if ($hasExpectedCash) {
                    $expectedPrevious = (float) ($row['expected'] ?? 0);
                }
                if ($hasActualCash) {
                    $actualPrevious = (float) ($row['actual'] ?? 0);
                }
            } catch (\Throwable $e) {
            }
        }

        try {
            $stmt = $db->prepare(
                "SELECT driver_id,
                        DATE_FORMAT(created_at, '$bucketFormat') AS k,
                        created_at,
                        COUNT(*) AS c,
                        COALESCE(SUM($amountExpr), 0) AS amt,
                        COALESCE(SUM(COALESCE(discrepancy_amount, 0)), 0) AS disc
                 FROM remittance_logs
                 WHERE DATE(created_at) BETWEEN :f AND :t
                 GROUP BY driver_id, k, created_at"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $did = trim((string) ($row['driver_id'] ?? ''));
                if ($did === '') {
                    continue;
                }
                $ensureDriver($driverStats, $did);
                $driverStats[$did]['completed'] += (int) $row['c'];
                $driverStats[$did]['completed_amount'] += (float) $row['amt'];
                $driverStats[$did]['discrepancy'] += ((float) $row['disc'] > 0) ? 1 : 0;
                $at = (string) ($row['created_at'] ?? '');
                if ($at !== '' && $at > $driverStats[$did]['last_remit_at']) {
                    $driverStats[$did]['last_remit_at'] = $at;
                }
            }
        } catch (\Throwable $e) {
        }
    }

    /* Pending live trips (bus_trips.submitted = 0 with paid collections) */
    $btCols = fs_columns($db, 'bus_trips');
    $ptCols = fs_columns($db, 'passenger_trips');
    $hasBusTrips = !empty($btCols);
    $hasSubmitted = isset($btCols['submitted']);
    $ptHasRemit = isset($ptCols['remittance_status']);

    $pendingQuery = static function (PDO $db, string $from, string $to, string $bucketFormat, bool $hasSubmitted, array &$bucketMap, array &$driverMap, float &$count, float &$amount, callable $ensureDriver, bool $collectDrivers): void {
        if (!$hasSubmitted) {
            return;
        }
        try {
            $stmt = $db->prepare(
                "SELECT bt.driver_id,
                        DATE_FORMAT(bt.trip_date, '$bucketFormat') AS k,
                        COUNT(DISTINCT bt.id) AS c,
                        COALESCE(SUM(pt.fare), 0) AS amt
                 FROM bus_trips bt
                 JOIN passenger_trips pt
                      ON pt.trip_no = bt.id
                     AND LOWER(TRIM(pt.payment_status)) = 'paid'
                 WHERE bt.trip_date BETWEEN :f AND :t
                   AND COALESCE(bt.submitted, 0) = 0
                 GROUP BY bt.driver_id, k"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $c = (int) $row['c'];
                $amt = (float) $row['amt'];
                $bucketMap[(string) $row['k']] = ($bucketMap[(string) $row['k']] ?? 0) + $c;
                $count += $c;
                $amount += $amt;

                if ($collectDrivers) {
                    $did = trim((string) ($row['driver_id'] ?? ''));
                    if ($did !== '') {
                        $ensureDriver($driverMap, $did);
                        $driverMap[$did]['pending'] += $c;
                        $driverMap[$did]['pending_amount'] += $amt;
                    }
                }
            }
        } catch (\Throwable $e) {
        }
    };

    $pendingQuery($db, $from, $to, $bucketFormat, $hasSubmitted, $pendingBuckets, $driverStats, $pendingCurrent, $pendingAmountCurrent, $ensureDriver, true);

    $pendingPreviousBuckets = [];
    $pendingPreviousDrivers = [];
    $pendingQuery($db, $prevFrom, $prevTo, $bucketFormat, $hasSubmitted, $pendingPreviousBuckets, $pendingPreviousDrivers, $pendingPrevious, $pendingAmountPrevious, $ensureDriver, false);

    /* Pending history rows (only when remittance columns exist) */
    $pthCols = fs_columns($db, 'passenger_trip_history');
    $pthHasRemit = isset($pthCols['remittance_status']);

    if ($pthHasRemit) {
        try {
            $stmt = $db->prepare(
                "SELECT pth.driver_id,
                        DATE_FORMAT(pth.trip_date, '$bucketFormat') AS k,
                        COUNT(DISTINCT COALESCE(NULLIF(TRIM(pth.trip_id), ''), pth.history_id)) AS c,
                        COALESCE(SUM(pth.fare), 0) AS amt
                 FROM passenger_trip_history pth
                 WHERE pth.trip_date BETWEEN :f AND :t
                   AND LOWER(COALESCE(pth.remittance_status, 'pending')) = 'pending'
                   AND pth.driver_id IS NOT NULL AND TRIM(pth.driver_id) <> ''
                 GROUP BY pth.driver_id, k"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $c = (int) $row['c'];
                $amt = (float) $row['amt'];
                $pendingBuckets[(string) $row['k']] = ($pendingBuckets[(string) $row['k']] ?? 0) + $c;
                $pendingCurrent += $c;
                $pendingAmountCurrent += $amt;

                $did = trim((string) ($row['driver_id'] ?? ''));
                if ($did !== '') {
                    $ensureDriver($driverStats, $did);
                    $driverStats[$did]['pending'] += $c;
                    $driverStats[$did]['pending_amount'] += $amt;
                }
            }
        } catch (\Throwable $e) {
        }
    }

    $statusCounts['pending']['count'] = $pendingCurrent;
    $statusCounts['pending']['amount'] = $pendingAmountCurrent;

    /* Completed history rows (generator + archive): a historical trip_date with
       remittance_status = Remitted is settled even though no remittance_logs row
       exists for it. Mirrors the pending history query above. */
    if ($pthHasRemit) {
        try {
            $stmt = $db->prepare(
                "SELECT pth.driver_id,
                        DATE_FORMAT(pth.trip_date, '$bucketFormat') AS k,
                        COUNT(DISTINCT COALESCE(NULLIF(TRIM(pth.trip_id), ''), pth.history_id)) AS c,
                        COALESCE(SUM(COALESCE(pth.actual_cash, pth.fare, 0)), 0) AS amt
                 FROM passenger_trip_history pth
                 WHERE pth.trip_date BETWEEN :f AND :t
                   AND LOWER(COALESCE(pth.remittance_status, 'pending')) = 'remitted'
                 GROUP BY pth.driver_id, k"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $c = (int) $row['c'];
                $amt = (float) $row['amt'];
                $completedBuckets[(string) $row['k']] = ($completedBuckets[(string) $row['k']] ?? 0) + $c;
                $completedCurrent += $c;
                $completedAmountCurrent += $amt;
                $statusCounts['completed']['count'] += $c;
                $statusCounts['completed']['amount'] += $amt;

                $did = trim((string) ($row['driver_id'] ?? ''));
                if ($did !== '') {
                    $ensureDriver($driverStats, $did);
                    $driverStats[$did]['completed'] += $c;
                    $driverStats[$did]['completed_amount'] += $amt;
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            $stmt = $db->prepare(
                "SELECT COUNT(DISTINCT COALESCE(NULLIF(TRIM(pth.trip_id), ''), pth.history_id)) AS c,
                        COALESCE(SUM(COALESCE(pth.actual_cash, pth.fare, 0)), 0) AS amt
                 FROM passenger_trip_history pth
                 WHERE pth.trip_date BETWEEN :f AND :t
                   AND LOWER(COALESCE(pth.remittance_status, 'pending')) = 'remitted'"
            );
            $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $completedPrevious += (float) ($row['c'] ?? 0);
            $completedAmountPrevious += (float) ($row['amt'] ?? 0);
        } catch (\Throwable $e) {
        }

        try {
            $stmt = $db->prepare(
                "SELECT COUNT(DISTINCT COALESCE(NULLIF(TRIM(pth.trip_id), ''), pth.history_id)) AS c,
                        COALESCE(SUM(COALESCE(pth.discrepancy_amount, 0)), 0) AS amt
                 FROM passenger_trip_history pth
                 WHERE pth.trip_date BETWEEN :f AND :t
                   AND LOWER(COALESCE(pth.remittance_status, '')) = 'discrepancy'"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $discrepancyCurrent += (int) ($row['c'] ?? 0);
            $discrepancyAmountCurrent += (float) ($row['amt'] ?? 0);
            $statusCounts['discrepancy']['count'] = $discrepancyCurrent;
            $statusCounts['discrepancy']['amount'] = $discrepancyAmountCurrent;
        } catch (\Throwable $e) {
        }

        /* History discrepancy rows per driver (drives the driver status pill). */
        try {
            $stmt = $db->prepare(
                "SELECT pth.driver_id,
                        COUNT(DISTINCT COALESCE(NULLIF(TRIM(pth.trip_id), ''), pth.history_id)) AS c
                 FROM passenger_trip_history pth
                 WHERE pth.trip_date BETWEEN :f AND :t
                   AND LOWER(COALESCE(pth.remittance_status, '')) = 'discrepancy'
                   AND pth.driver_id IS NOT NULL AND TRIM(pth.driver_id) <> ''
                 GROUP BY pth.driver_id"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $did = trim((string) ($row['driver_id'] ?? ''));
                if ($did === '') {
                    continue;
                }
                $ensureDriver($driverStats, $did);
                $driverStats[$did]['discrepancy'] += (int) $row['c'];
            }
        } catch (\Throwable $e) {
        }

        /* Cash reconciliation for history remitted rows (expected vs actual). */
        $pthHasExpected = isset($pthCols['expected_cash']);
        $pthHasActual = isset($pthCols['actual_cash']);
        if ($pthHasExpected || $pthHasActual) {
            $cashSelect = [];
            if ($pthHasExpected) {
                $cashSelect[] = 'COALESCE(SUM(COALESCE(pth.expected_cash, 0)), 0) AS expected';
            }
            if ($pthHasActual) {
                $cashSelect[] = 'COALESCE(SUM(COALESCE(pth.actual_cash, 0)), 0) AS actual';
            }
            $cashSql = 'SELECT ' . implode(', ', $cashSelect) . "
                        FROM passenger_trip_history pth
                        WHERE pth.trip_date BETWEEN :f AND :t
                          AND LOWER(COALESCE(pth.remittance_status, 'pending')) = 'remitted'";
            try {
                $stmt = $db->prepare($cashSql);
                $stmt->execute([':f' => $from, ':t' => $to]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                if ($pthHasExpected) {
                    $expectedCurrent += (float) ($row['expected'] ?? 0);
                }
                if ($pthHasActual) {
                    $actualCurrent += (float) ($row['actual'] ?? 0);
                }
            } catch (\Throwable $e) {
            }
            try {
                $stmt = $db->prepare($cashSql);
                $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                if ($pthHasExpected) {
                    $expectedPrevious += (float) ($row['expected'] ?? 0);
                }
                if ($pthHasActual) {
                    $actualPrevious += (float) ($row['actual'] ?? 0);
                }
            } catch (\Throwable $e) {
            }
            $hasExpectedCash = $hasExpectedCash || $pthHasExpected;
            $hasActualCash = $hasActualCash || $pthHasActual;
        }
    }

    /* ---------------- Driver trips (paid, live + history) ---------------- */

    try {
        $stmt = $db->prepare(
            "SELECT bt.driver_id, COUNT(DISTINCT bt.id) AS c
             FROM bus_trips bt
             JOIN passenger_trips pt
                  ON pt.trip_no = bt.id
                 AND LOWER(TRIM(pt.payment_status)) = 'paid'
             WHERE bt.trip_date BETWEEN :f AND :t
               AND bt.driver_id IS NOT NULL AND TRIM(bt.driver_id) <> ''
             GROUP BY bt.driver_id"
        );
        $stmt->execute([':f' => $from, ':t' => $to]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $did = trim((string) $row['driver_id']);
            $ensureDriver($driverStats, $did);
            $driverStats[$did]['trips'] += (int) $row['c'];
        }
    } catch (\Throwable $e) {
    }

    try {
        $stmt = $db->prepare(
            "SELECT pth.driver_id,
                    COUNT(DISTINCT COALESCE(NULLIF(TRIM(pth.trip_id), ''), pth.history_id)) AS c
             FROM passenger_trip_history pth
             WHERE pth.trip_date BETWEEN :f AND :t
               AND pth.driver_id IS NOT NULL AND TRIM(pth.driver_id) <> ''
             GROUP BY pth.driver_id"
        );
        $stmt->execute([':f' => $from, ':t' => $to]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $did = trim((string) $row['driver_id']);
            $ensureDriver($driverStats, $did);
            $driverStats[$did]['trips'] += (int) $row['c'];
        }
    } catch (\Throwable $e) {
    }

    /* Resolve driver names in one query */
    $driverNames = [];
    if (!empty($driverStats)) {
        try {
            $ids = array_keys($driverStats);
            $placeholders = [];
            $params = [];
            foreach ($ids as $i => $id) {
                $key = ':d' . $i;
                $placeholders[] = $key;
                $params[$key] = $id;
            }
            $stmt = $db->prepare(
                "SELECT driver_id, full_name FROM drivers
                 WHERE driver_id IN (" . implode(', ', $placeholders) . ")"
            );
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $driverNames[(string) $row['driver_id']] = trim((string) ($row['full_name'] ?? ''));
            }
        } catch (\Throwable $e) {
        }
    }

    $drivers = [];
    foreach ($driverStats as $did => $d) {
        $completed = (int) $d['completed'];
        $pending = (int) $d['pending'];
        $denominator = $completed + $pending;
        $status = 'No Activity';
        if ((int) $d['discrepancy'] > 0) {
            $status = 'Discrepancy';
        } elseif ($pending > 0) {
            $status = 'Pending';
        } elseif ($completed > 0) {
            $status = 'Completed';
        }
        $drivers[] = [
            'driver_id'        => $did,
            'name'             => $driverNames[$did] ?? ('Driver ' . $did),
            'trips'            => (int) $d['trips'],
            'completed'        => $completed,
            'completed_amount' => round((float) $d['completed_amount'], 2),
            'pending'          => $pending,
            'pending_amount'   => round((float) $d['pending_amount'], 2),
            'discrepancy'      => (int) $d['discrepancy'],
            'status'           => $status,
            'compliance'       => $denominator > 0 ? round(($completed / $denominator) * 100, 1) : ($completed > 0 ? 100.0 : 0.0),
            'last_remit_at'    => (string) $d['last_remit_at'],
        ];
    }

    usort($drivers, static function (array $a, array $b): int {
        if ($a['compliance'] !== $b['compliance']) {
            return $b['compliance'] <=> $a['compliance'];
        }
        if ($a['completed'] !== $b['completed']) {
            return $b['completed'] <=> $a['completed'];
        }
        return $b['pending'] <=> $a['pending'];
    });

    /* ---------------- Recent remittances ---------------- */

    /* Fallback bus lookup: driver's bus on that date, else the assigned bus. */
    $busForDriverDate = static function (string $did, string $at) use ($db): string {
        if ($did === '') {
            return '';
        }
        $date = substr($at, 0, 10);
        try {
            if ($date !== '' && fs_table_exists($db, 'passenger_trip_history')) {
                $stmt = $db->prepare(
                    "SELECT bus_number FROM passenger_trip_history
                     WHERE driver_id = :d AND trip_date = :dt
                       AND bus_number IS NOT NULL AND TRIM(bus_number) <> ''
                     ORDER BY history_id DESC LIMIT 1"
                );
                $stmt->execute([':d' => $did, ':dt' => $date]);
                $bus = trim((string) $stmt->fetchColumn());
                if ($bus !== '') {
                    return $bus;
                }
            }
            if (fs_table_exists($db, 'driver_bus_assignment')) {
                $stmt = $db->prepare(
                    "SELECT bus_id FROM driver_bus_assignment
                     WHERE driver_id = :d ORDER BY id DESC LIMIT 1"
                );
                $stmt->execute([':d' => $did]);
                return trim((string) $stmt->fetchColumn());
            }
        } catch (\Throwable $e) {
        }
        return '';
    };

    $recent = [];
    if ($hasLogs) {
        try {
            /* Bus column names vary by environment; use the first one that exists
               and fall back to the driver's bus when the value is empty. */
            $busCandidates = [];
            foreach (['bus_no', 'bus_id', 'bus', 'bus_number'] as $candidate) {
                if (isset($rlCols[$candidate])) {
                    $busCandidates[] = "NULLIF(TRIM(`$candidate`), '')";
                }
            }
            $selectBus = !empty($busCandidates)
                ? 'COALESCE(' . implode(', ', $busCandidates) . ", '') AS bus_no"
                : "'' AS bus_no";

            $stmt = $db->prepare(
                "SELECT id, driver_id, status, created_at, $selectBus,
                        $amountExpr AS amount
                 FROM remittance_logs
                 WHERE DATE(created_at) BETWEEN :f AND :t
                 ORDER BY created_at DESC, id DESC
                 LIMIT 8"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $did = trim((string) ($row['driver_id'] ?? ''));
                $bus = trim((string) ($row['bus_no'] ?? ''));
                if ($bus === '') {
                    $bus = $busForDriverDate($did, (string) ($row['created_at'] ?? ''));
                }
                $recent[] = [
                    'at'        => (string) ($row['created_at'] ?? ''),
                    'driver_id' => $did,
                    'driver'    => $driverNames[$did] ?? ($did !== '' ? 'Driver ' . $did : '—'),
                    'bus'       => $bus,
                    'amount'    => round((float) ($row['amount'] ?? 0), 2),
                    'status'    => (string) ($row['status'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
        }
    }

    /* Generated/archive history has no remittance_logs events: derive one entry
       per driver per remitted day from passenger_trip_history so the panel is
       not empty for generated data. */
    if ($pthHasRemit && count($recent) < 8) {
        try {
            $need = 8 - count($recent);
            $stmt = $db->prepare(
                "SELECT pth.driver_id,
                        MAX(COALESCE(pth.remitted_at, CONCAT(pth.trip_date, ' 18:15:00'))) AS at,
                        MAX(pth.bus_number) AS bus,
                        COALESCE(SUM(COALESCE(pth.actual_cash, pth.fare, 0)), 0) AS amount
                 FROM passenger_trip_history pth
                 WHERE pth.trip_date BETWEEN :f AND :t
                   AND LOWER(COALESCE(pth.remittance_status, 'pending')) = 'remitted'
                 GROUP BY pth.driver_id, DATE(COALESCE(pth.remitted_at, CONCAT(pth.trip_date, ' 18:15:00')))
                 ORDER BY at DESC
                 LIMIT $need"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $did = trim((string) ($row['driver_id'] ?? ''));
                $at = (string) ($row['at'] ?? '');
                $bus = trim((string) ($row['bus'] ?? ''));
                if ($bus === '') {
                    $bus = $busForDriverDate($did, $at);
                }
                $recent[] = [
                    'at'        => $at,
                    'driver_id' => $did,
                    'driver'    => $driverNames[$did] ?? ($did !== '' ? 'Driver ' . $did : '—'),
                    'bus'       => $bus,
                    'amount'    => round((float) ($row['amount'] ?? 0), 2),
                    'status'    => 'Remitted',
                ];
            }
        } catch (\Throwable $e) {
        }
    }

    /* Names for recent rows that were not part of driverStats */
    $missingIds = [];
    foreach ($recent as $r) {
        if ($r['driver_id'] !== '' && !isset($driverNames[$r['driver_id']])) {
            $missingIds[$r['driver_id']] = true;
        }
    }
    if (!empty($missingIds)) {
        try {
            $ids = array_keys($missingIds);
            $placeholders = [];
            $params = [];
            foreach ($ids as $i => $id) {
                $key = ':n' . $i;
                $placeholders[] = $key;
                $params[$key] = $id;
            }
            $stmt = $db->prepare(
                "SELECT driver_id, full_name FROM drivers
                 WHERE driver_id IN (" . implode(', ', $placeholders) . ")"
            );
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $driverNames[(string) $row['driver_id']] = trim((string) ($row['full_name'] ?? ''));
            }
            foreach ($recent as &$r) {
                if ($r['driver_id'] !== '' && isset($driverNames[$r['driver_id']])) {
                    $r['driver'] = $driverNames[$r['driver_id']];
                }
            }
            unset($r);
        } catch (\Throwable $e) {
        }
    }

    /* ---------------- Previous remittance buckets (profit comparison) ---------------- */

    $profitPreviousSeries = [];
    $prevProfitMap = [];
    {
        try {
            $stmt = $db->prepare(
                "SELECT DATE_FORMAT(boarding_time, '$bucketFormat') AS k, COALESCE(SUM(fare),0) AS v
                 FROM passenger_trips
                 WHERE LOWER(TRIM(payment_status)) = 'paid'
                   AND DATE(boarding_time) BETWEEN :f AND :t
                 GROUP BY k"
            );
            $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $prevProfitMap[(string) $row['k']] = (float) $row['v'];
            }
        } catch (\Throwable $e) {
        }
        try {
            $stmt = $db->prepare(
                "SELECT DATE_FORMAT(trip_date, '$bucketFormat') AS k, COALESCE(SUM(fare),0) AS v
                 FROM passenger_trip_history
                 WHERE DATE(trip_date) BETWEEN :f AND :t
                 GROUP BY k"
            );
            $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $prevProfitMap[(string) $row['k']] = ($prevProfitMap[(string) $row['k']] ?? 0) + (float) $row['v'];
            }
        } catch (\Throwable $e) {
        }
        try {
            $stmt = $db->prepare(
                "SELECT DATE_FORMAT(created_at, '$bucketFormat') AS k, COALESCE(SUM(cost),0) AS v
                 FROM fuel_logs
                 WHERE DATE(created_at) BETWEEN :f AND :t
                 GROUP BY k"
            );
            $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $prevProfitMap[(string) $row['k']] = ($prevProfitMap[(string) $row['k']] ?? 0) - (float) $row['v'];
            }
        } catch (\Throwable $e) {
        }
        if (fs_table_exists($db, 'fuel_refuels')) {
            try {
                $stmt = $db->prepare(
                    "SELECT DATE_FORMAT(created_at, '$bucketFormat') AS k, COALESCE(SUM(total_cost),0) AS v
                     FROM fuel_refuels
                     WHERE DATE(created_at) BETWEEN :f AND :t
                     GROUP BY k"
                );
                $stmt->execute([':f' => $prevFrom, ':t' => $prevTo]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $prevProfitMap[(string) $row['k']] = ($prevProfitMap[(string) $row['k']] ?? 0) - (float) $row['v'];
                }
            } catch (\Throwable $e) {
            }
        }

        foreach ($prevBucketKeys as $key) {
            $profitPreviousSeries[] = round((float) ($prevProfitMap[$key] ?? 0), 2);
        }
    }

    /* ---------------- Payload ---------------- */

    fs_json([
        'success' => true,
        'range'   => [
            'label'         => $range['label'],
            'from'          => $from,
            'to'            => $to,
            'previous_from' => $prevFrom,
            'previous_to'   => $prevTo,
            'granularity'   => $granularity,
        ],
        'labels'  => $bucketLabels,
        'profit'  => [
            'current'  => $profitCurrent,
            'previous' => $profitPrevious,
            'percent'  => fs_pct($profitCurrent, $profitPrevious),
            'income'   => round($incomeCurrent, 2),
            'fuel'     => round($fuelCurrent, 2),
            'previous_income' => round($incomePrevious, 2),
            'previous_fuel'   => round($fuelPrevious, 2),
        ],
        'pending' => [
            'count'          => (int) $pendingCurrent,
            'amount'         => round($pendingAmountCurrent, 2),
            'previous_count' => (int) $pendingPrevious,
            'previous_amount'=> round($pendingAmountPrevious, 2),
            'percent'        => fs_pct($pendingAmountCurrent, $pendingAmountPrevious),
            'drivers'        => count(array_filter($drivers, static fn($d) => $d['pending'] > 0)),
        ],
        'completed' => [
            'count'          => (int) $completedCurrent,
            'amount'         => round($completedAmountCurrent, 2),
            'previous_count' => (int) $completedPrevious,
            'previous_amount'=> round($completedAmountPrevious, 2),
            'percent'        => fs_pct($completedAmountCurrent, $completedAmountPrevious),
        ],
        'expected' => [
            'current'  => $hasExpectedCash ? round($expectedCurrent, 2) : null,
            'previous' => $hasExpectedCash ? round($expectedPrevious, 2) : null,
        ],
        'actual' => [
            'current'  => $hasActualCash ? round($actualCurrent, 2) : null,
            'previous' => $hasActualCash ? round($actualPrevious, 2) : null,
        ],
        'variance' => [
            'current'  => ($hasExpectedCash && $hasActualCash) ? round($actualCurrent - $expectedCurrent, 2) : null,
            'previous' => ($hasExpectedCash && $hasActualCash) ? round($actualPrevious - $expectedPrevious, 2) : null,
            'percent'  => ($hasExpectedCash && $hasActualCash) ? fs_pct($actualCurrent, $expectedCurrent) : null,
        ],
        'discrepancy' => [
            'count'  => (int) $discrepancyCurrent,
            'amount' => round($discrepancyAmountCurrent, 2),
        ],
        'no_remit' => [
            'count'  => (int) $noRemitCurrent,
            'amount' => round($noRemitAmountCurrent, 2),
        ],
        'status' => [
            ['key' => 'completed',   'label' => 'Completed',   'count' => $statusCounts['completed']['count'],   'amount' => round($statusCounts['completed']['amount'], 2)],
            ['key' => 'pending',     'label' => 'Pending',     'count' => $statusCounts['pending']['count'],     'amount' => round($statusCounts['pending']['amount'], 2)],
            ['key' => 'discrepancy', 'label' => 'Discrepancy', 'count' => $statusCounts['discrepancy']['count'], 'amount' => round($statusCounts['discrepancy']['amount'], 2)],
            ['key' => 'no_remit',    'label' => 'No Remit',    'count' => $statusCounts['no_remit']['count'],    'amount' => round($statusCounts['no_remit']['amount'], 2)],
        ],
        'series' => [
            'income'    => $incomeSeries,
            'fuel'      => $fuelSeries,
            'profit'    => $profitSeries,
            'profit_previous'    => $profitPreviousSeries,
            'completed' => fs_series_from_map($completedBuckets, $bucketKeys),
            'pending'   => fs_series_from_map($pendingBuckets, $bucketKeys),
        ],
        'drivers'        => array_slice($drivers, 0, 10),
        'driver_count'   => count($drivers),
        'recent'         => $recent,
    ]);
} catch (\Throwable $e) {
    error_log('Finance summary error: ' . $e->getMessage());
    fs_json(['success' => false, 'message' => 'Unable to load finance summary.']);
}

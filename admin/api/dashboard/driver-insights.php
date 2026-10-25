<?php

/**
 * Admin Dashboard — Driver Insights (read-only)
 *
 * Per-driver and fleet-level Driver Insights for the Admin Dashboard's
 * Driver Performance Analytics panel:
 *   - Trip completion     (completed / cancelled / pending + completion rate)
 *   - Fuel efficiency     (km/L, liters, fuel cost per driver — fuel_logs odometer data)
 *   - Operating costs     (fixed per-driver deductions + fuel, net income)
 *   - Attendance          (dispatcher check-in records, same logic as admin analytics)
 *   - Performance trends  (daily trips/fare per driver + fleet series)
 *
 * Reuses the authoritative shared admin helpers (driver-perf-occupancy.php and
 * driver-perf-metrics-core.php) so the numbers, formulas and business rules stay
 * identical to the admin Driver Performance Analytics page — no duplicated SQL.
 * Fuel efficiency uses the exact odometer + liters/kpl formula from
 * admin/models/DashboardModel.php::fuelEfficiencySummary(), but scoped per driver.
 *
 * Period fields mirror the admin dashboard quick filters: range=today|week|month|year|custom
 * plus fromDate/toDate or month/year, resolved through dpm_period_range().
 *
 * No schema changes, no writes. Missing tables/columns degrade to empty payloads.
 */

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/Driver/driver-perf-occupancy.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/Driver/driver-perf-metrics-core.php';

use App\Core\Database;

function adi_json(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$db = Database::tryGet();

if ($db === null) {
    adi_json(['success' => false, 'message' => 'Database connection unavailable.']);
}

/* ------------------------------------------------------------------ */
/* Fuel_logs schema guard + odometer based fuel efficiency (per driver) */
/* ------------------------------------------------------------------ */

function adi_fuel_columns(PDO $db): array
{
    $cols = [];
    try {
        $stmt = $db->query("SHOW COLUMNS FROM fuel_logs");
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cols[strtolower((string) $row['Field'])] = true;
            }
        }
    } catch (\Throwable $e) {
        $cols = [];
    }
    return $cols;
}

/**
 * Per-driver fuel efficiency for the period.
 * Distance  = ending odometer - starting odometer (GREATEST 0, same as DashboardModel).
 * Consumed  = liters when present, else distance / kpl (same fallback).
 * kmpl      = distance / consumed.
 */
function adi_driver_fuel(PDO $db, string $driverId, string $startDt, string $endDt, array $cols): array
{
    if (empty($cols)) {
        return ['liters' => 0.0, 'amount' => 0.0, 'consumed' => 0.0, 'distance' => 0.0, 'kmpl' => null, 'cost_per_liter' => null];
    }

    $odoStart = null;
    if (isset($cols['starting_odo'])) {
        $odoStart = (isset($cols['starting_odometer']) ? 'COALESCE(fl.starting_odo, fl.starting_odometer)' : 'fl.starting_odo');
    } elseif (isset($cols['starting_odometer'])) {
        $odoStart = 'fl.starting_odometer';
    }
    $odoEnd = null;
    if (isset($cols['ending_odo'])) {
        $odoEnd = (isset($cols['ending_odometer']) ? 'COALESCE(fl.ending_odo, fl.ending_odometer)' : 'fl.ending_odo');
    } elseif (isset($cols['ending_odometer'])) {
        $odoEnd = 'fl.ending_odometer';
    }

    $distanceExpr = ($odoStart !== null && $odoEnd !== null)
        ? "GREATEST(COALESCE($odoEnd, 0) - COALESCE($odoStart, 0), 0)"
        : '0';

    $litersCol = isset($cols['liters']) ? 'fl.liters' : '0';
    $kplCol    = isset($cols['kpl']) ? 'fl.kpl' : '0';
    $costCol   = isset($cols['cost']) ? 'fl.cost' : '0';

    $consumedExpr = "CASE
        WHEN $litersCol > 0 THEN $litersCol
        WHEN $kplCol > 0 AND $distanceExpr > 0 THEN $distanceExpr / $kplCol
        ELSE 0 END";

    try {
        $stmt = $db->prepare(
            "SELECT
                 COALESCE(SUM($distanceExpr), 0) AS distance,
                 COALESCE(SUM($consumedExpr), 0) AS consumed,
                 COALESCE(SUM($litersCol), 0)   AS liters,
                 COALESCE(SUM($costCol), 0)     AS amount
             FROM fuel_logs fl
             WHERE fl.driver_id = :did AND fl.created_at BETWEEN :s AND :e"
        );
        $stmt->execute([':did' => $driverId, ':s' => $startDt, ':e' => $endDt]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        return ['liters' => 0.0, 'amount' => 0.0, 'consumed' => 0.0, 'distance' => 0.0, 'kmpl' => null, 'cost_per_liter' => null];
    }

    $liters    = (float) ($row['liters'] ?? 0);
    $amount    = (float) ($row['amount'] ?? 0);
    $consumed  = (float) ($row['consumed'] ?? 0);
    $distance  = (float) ($row['distance'] ?? 0);

    $kmpl     = ($consumed > 0 && $distance > 0) ? round($distance / $consumed, 2) : null;
    $cpl      = $consumed > 0 ? round($amount / $consumed, 2) : ($liters > 0 ? round($amount / $liters, 2) : null);

    return [
        'liters'         => round($liters, 2),
        'amount'         => round($amount, 2),
        'consumed'       => round($consumed, 2),
        'distance'       => round($distance, 2),
        'kmpl'           => $kmpl,
        'cost_per_liter' => $cpl,
    ];
}

/* ------------------------------------------------------------------ */
/* Main                                                                */
/* ------------------------------------------------------------------ */

try {
    /* Resolve the admin dashboard quick filters (range + fromDate/toDate or
       month/year) into one reporting window through the shared helper. */
    $mapped = [];
    $fromDate = trim((string) ($_GET['fromDate'] ?? ''));
    $toDate   = trim((string) ($_GET['toDate'] ?? ''));

    if ($fromDate !== '' || $toDate !== '') {
        $mapped['period'] = 'custom';
        $mapped['start'] = $fromDate !== '' ? $fromDate : $toDate;
        $mapped['end']   = $toDate !== '' ? $toDate : $fromDate;
    } else {
        $range = strtolower(trim((string) ($_GET['range'] ?? '')));
        $mapped['period'] = match ($range) {
            'today' => 'today',
            'year'  => 'yearly',
            default => 'monthly',
        };
    }

    [$start, $end, $period] = dpm_period_range($mapped);
    $startDt = $start;
    $endDt   = $end;

    /* Keep admin monthly / today behavior for the common presets and prorate
       fixed monthly costs by actual days for everything else. */
    $dpmPeriod = match ($period) {
        'today'   => 'today',
        'monthly' => 'monthly',
        'yearly'  => 'annually',
        default   => 'custom',
    };

    $fuelCols = adi_fuel_columns($db);

    $roster = [];
    try {
        $stmt = $db->query(
            "SELECT d.driver_id, MAX(COALESCE(d.full_name, '')) AS full_name,
                    MAX(COALESCE(d.photo, '')) AS photo
             FROM drivers d
             GROUP BY d.driver_id
             ORDER BY full_name"
        );
        $roster = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        $roster = [];
    }

    $rows = [];
    $trend = [];

    foreach ($roster as $d) {
        $did = (string) $d['driver_id'];
        if ($did === '') {
            continue;
        }

        try {
            $m = dpm_compute($db, $did, $startDt, $endDt, $dpmPeriod);
        } catch (\Throwable $e) {
            continue;
        }
        if (!is_array($m) || isset($m['error'])) {
            continue;
        }

        $fuel = adi_driver_fuel($db, $did, $startDt, $endDt, $fuelCols);

        $trips = $m['trips'] ?? [];
        $total = (int) ($trips['total'] ?? 0);
        $completed = (int) ($trips['completed'] ?? 0);
        $cancelled = (int) ($trips['cancelled'] ?? 0);
        $pending   = (int) ($trips['pending'] ?? 0);

        $att = $m['attendance'] ?? [];
        $attScheduled = (int) ($att['scheduled'] ?? 0);

        $gross = (float) ($m['gross_income'] ?? 0);
        $fixed = $m['fixed_costs'] ?? [];
        $fixedTotal = (float) ($fixed['total'] ?? 0);
        $gasAmount = (float) ($fuel['amount'] ?? 0);
        $net = (float) ($m['net_income'] ?? 0);

        foreach (($m['daily_performance'] ?? []) as $dr) {
            $day = substr((string) ($dr['day'] ?? ''), 0, 10);
            if ($day === '') {
                continue;
            }
            if (!isset($trend[$day])) {
                $trend[$day] = ['trips' => 0, 'fare' => 0.0];
            }
            $trend[$day]['trips'] += (int) ($dr['trips'] ?? 0);
            $trend[$day]['fare']  += (float) ($dr['fare'] ?? 0);
        }

        $rows[] = [
            'driver_id' => $did,
            'name'      => (string) ($d['full_name'] ?? $m['name'] ?? 'Unknown'),
            'photo'     => (string) ($d['photo'] ?? ''),
            'no_activity' => $total <= 0,
            'trips' => [
                'total' => $total,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'pending'   => $pending,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
            ],
            'attendance' => [
                'present'  => (int) ($att['present'] ?? 0),
                'absent'   => (int) ($att['absent'] ?? 0),
                'scheduled'=> $attScheduled,
                'rate'     => isset($att['rate']) ? (float) $att['rate'] : null,
                'extra_days' => (int) ($att['extra_days'] ?? 0),
            ],
            'fuel' => [
                'liters'         => $fuel['liters'],
                'amount'         => $fuel['amount'],
                'consumed'       => $fuel['consumed'],
                'distance'       => $fuel['distance'],
                'kmpl'           => $fuel['kmpl'],
                'cost_per_liter' => $fuel['cost_per_liter'],
            ],
            'costs' => [
                'gross_income' => round($gross, 2),
                'fixed_total'  => round($fixedTotal, 2),
                'fuel_cost'    => round($gasAmount, 2),
                'net_income'   => round($net, 2),
                'net_margin'   => $gross > 0 ? round(($net / $gross) * 100, 1) : null,
            ],
            'occupancy' => [
                'value'               => ($m['capacity']['occupancy'] ?? null),
                'occupied'            => (int) (($m['capacity']['occupied_passenger'] ?? 0) + ($m['capacity']['occupied_other'] ?? 0)),
                'capacity'            => (int) ($m['capacity']['vehicle_capacity'] ?? 0),
                'capacity_unavailable'=> (bool) ($m['capacity']['capacity_unavailable'] ?? true),
            ],
            'passengers' => [
                'total'   => (int) (($m['passengers']['total'] ?? 0)),
                'regular' => (int) (($m['passengers']['regular'] ?? 0)),
                'special' => (int) (($m['passengers']['special'] ?? 0)),
            ],
            'daily' => array_map(static function (array $r): array {
                return [
                    'day' => substr((string) ($r['day'] ?? ''), 0, 10),
                    'trips' => (int) ($r['trips'] ?? 0),
                    'passengers' => (int) ($r['passengers'] ?? 0),
                    'fare' => round((float) ($r['fare'] ?? 0), 2),
                ];
            }, $m['daily_performance'] ?? []),
        ];
    }

    usort($rows, static fn(array $a, array $b): int => ($b['costs']['gross_income'] <=> $a['costs']['gross_income'])
        ?: ($b['trips']['total'] <=> $a['trips']['total']));

    /* ---------------- Fleet aggregates (active drivers only) ---------------- */

    $active = array_values(array_filter($rows, static fn(array $r): bool => !$r['no_activity']));

    $agg = [
        'trips_total' => 0, 'trips_completed' => 0, 'trips_cancelled' => 0, 'trips_pending' => 0,
        'att_present' => 0, 'att_absent' => 0, 'att_scheduled' => 0,
        'fuel_liters' => 0.0, 'fuel_consumed' => 0.0, 'fuel_amount' => 0.0, 'fuel_distance' => 0.0,
        'fin_gross' => 0.0, 'fin_fixed' => 0.0, 'fin_fuel' => 0.0, 'fin_net' => 0.0,
        'occ_occupied' => 0, 'occ_capacity' => 0,
    ];

    foreach ($active as $r) {
        $agg['trips_total']      += $r['trips']['total'];
        $agg['trips_completed']  += $r['trips']['completed'];
        $agg['trips_cancelled']  += $r['trips']['cancelled'];
        $agg['trips_pending']    += $r['trips']['pending'];
        $agg['att_present']      += $r['attendance']['present'];
        $agg['att_absent']       += $r['attendance']['absent'];
        $agg['att_scheduled']    += $r['attendance']['scheduled'];
        $agg['fuel_liters']      += $r['fuel']['liters'];
        $agg['fuel_consumed']    += $r['fuel']['consumed'];
        $agg['fuel_amount']      += $r['fuel']['amount'];
        $agg['fuel_distance']    += $r['fuel']['distance'];
        $agg['fin_gross']        += $r['costs']['gross_income'];
        $agg['fin_fixed']        += $r['costs']['fixed_total'];
        $agg['fin_fuel']         += $r['costs']['fuel_cost'];
        $agg['fin_net']          += $r['costs']['net_income'];
        if (!$r['occupancy']['capacity_unavailable']) {
            $agg['occ_occupied'] += $r['occupancy']['occupied'];
            $agg['occ_capacity'] += $r['occupancy']['capacity'];
        }
    }

    $totalTrips = $agg['trips_total'];

    /* ---------------- Best / lowest drivers per metric (comparisons) ---------------- */

    $kmplRows = array_values(array_filter($active, static fn(array $r): bool => ($r['fuel']['kmpl'] ?? 0) > 0));
    $bestKmpl = null;
    if (!empty($kmplRows)) {
        usort($kmplRows, static fn(array $a, array $b): int => $b['fuel']['kmpl'] <=> $a['fuel']['kmpl']);
        $bestKmpl = [
            'driver_id' => $kmplRows[0]['driver_id'],
            'name'      => $kmplRows[0]['name'],
            'value'     => $kmplRows[0]['fuel']['kmpl'],
        ];
    }

    $completionRows = array_values(array_filter($active, static fn(array $r): bool => $r['trips']['total'] > 0));
    $bestCompletion = null;
    if (!empty($completionRows)) {
        usort($completionRows, static fn(array $a, array $b): int => $b['trips']['completion_rate'] <=> $a['trips']['completion_rate']);
        $bestCompletion = [
            'driver_id' => $completionRows[0]['driver_id'],
            'name'      => $completionRows[0]['name'],
            'value'     => $completionRows[0]['trips']['completion_rate'],
        ];
    }

    $attRows = array_values(array_filter($active, static fn(array $r): bool => ($r['attendance']['rate'] ?? null) !== null));
    $bestAttendance = null;
    if (!empty($attRows)) {
        usort($attRows, static fn(array $a, array $b): int => $b['attendance']['rate'] <=> $a['attendance']['rate']);
        $bestAttendance = [
            'driver_id' => $attRows[0]['driver_id'],
            'name'      => $attRows[0]['name'],
            'value'     => $attRows[0]['attendance']['rate'],
        ];
    }

    $netRows = array_values(array_filter($active, static fn(array $r): bool => true));
    usort($netRows, static fn(array $a, array $b): int => $b['costs']['net_income'] <=> $a['costs']['net_income']);
    $bestNet   = isset($netRows[0]['driver_id']) ? ['driver_id' => $netRows[0]['driver_id'], 'name' => $netRows[0]['name'], 'value' => $netRows[0]['costs']['net_income']] : null;
    $lowestNet = count($netRows) > 1 ? ['driver_id' => $netRows[count($netRows) - 1]['driver_id'], 'name' => $netRows[count($netRows) - 1]['name'], 'value' => $netRows[count($netRows) - 1]['costs']['net_income']] : null;

    ksort($trend);
    $trendKeys = array_keys($trend);
    $trendTrips = [];
    $trendFare = [];
    foreach ($trendKeys as $k) {
        $trendTrips[] = $trend[$k]['trips'];
        $trendFare[] = round($trend[$k]['fare'], 2);
    }

    $fleetKmpl = ($agg['fuel_consumed'] > 0 && $agg['fuel_distance'] > 0)
        ? round($agg['fuel_distance'] / $agg['fuel_consumed'], 2)
        : null;

    adi_json([
        'success' => true,
        'range'   => [
            'label'  => dpm_period_label($period, $start, $end),
            'from'   => substr($start, 0, 10),
            'to'     => substr($end, 0, 10),
            'period' => $period,
        ],
        'fleet' => [
            'drivers'  => count($rows),
            'active'   => count($active),
            'trips'    => [
                'total' => $totalTrips,
                'completed' => $agg['trips_completed'],
                'cancelled' => $agg['trips_cancelled'],
                'pending'   => $agg['trips_pending'],
                'completion_rate' => $totalTrips > 0 ? round(($agg['trips_completed'] / $totalTrips) * 100, 1) : 0.0,
            ],
            'attendance' => [
                'present'  => $agg['att_present'],
                'absent'   => $agg['att_absent'],
                'scheduled'=> $agg['att_scheduled'],
                'rate'     => $agg['att_scheduled'] > 0 ? round(($agg['att_present'] / $agg['att_scheduled']) * 100, 1) : null,
            ],
            'fuel' => [
                'liters'   => round($agg['fuel_liters'], 2),
                'consumed' => round($agg['fuel_consumed'], 2),
                'amount'   => round($agg['fuel_amount'], 2),
                'distance' => round($agg['fuel_distance'], 2),
                'kmpl'     => $fleetKmpl,
            ],
            'financial' => [
                'gross_income' => round($agg['fin_gross'], 2),
                'fixed_total'  => round($agg['fin_fixed'], 2),
                'fuel_cost'    => round($agg['fin_fuel'], 2),
                'net_income'   => round($agg['fin_net'], 2),
                'net_margin'   => $agg['fin_gross'] > 0 ? round(($agg['fin_net'] / $agg['fin_gross']) * 100, 1) : null,
            ],
            'occupancy' => [
                'value'               => $agg['occ_capacity'] > 0 ? round(($agg['occ_occupied'] / $agg['occ_capacity']) * 100, 1) : null,
                'occupied'            => $agg['occ_occupied'],
                'capacity'            => $agg['occ_capacity'],
                'capacity_unavailable'=> $agg['occ_capacity'] <= 0,
            ],
            'best' => [
                'kmpl' => $bestKmpl,
                'completion' => $bestCompletion,
                'attendance' => $bestAttendance,
                'net' => $bestNet,
            ],
            'lowest_net' => $lowestNet,
        ],
        'trend'   => [
            'keys'  => $trendKeys,
            'trips' => $trendTrips,
            'fare'  => $trendFare,
        ],
        'drivers' => $rows,
    ]);
} catch (\Throwable $e) {
    error_log('Admin driver insights error: ' . $e->getMessage());
    adi_json(['success' => false, 'message' => 'Unable to load driver insights.']);
}

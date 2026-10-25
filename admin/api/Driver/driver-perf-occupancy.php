<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/Core/Database.php';
require_once __DIR__ . '/../../../app/Core/FinancialCosts.php';

use App\Core\Database;
use App\Core\FinancialCosts;

/**
 * Number of months a MONTHLY fixed cost must be multiplied by for the given period.
 *   today / weekly / custom -> prorated by actual days (÷ 30.4375)
 *   monthly -> exactly 1
 *   yearly  -> exactly 12
 */
function dpm_period_months(?string $period, string $start, string $end): float
{
    if ($period === 'yearly' || $period === 'annually' || $period === 'annual') {
        return 12.0;
    }
    if ($period === 'semi_annually' || $period === 'semi-annually' || $period === 'semiannual') {
        return 6.0;
    }
    if ($period === 'quarterly' || $period === 'quarter') {
        return 3.0;
    }
    if ($period === 'monthly') {
        return 1.0;
    }
    $days = (strtotime($end) - strtotime($start)) / 86400 + 1;
    return max(0.0, (float)$days / 30.4375);
}

/**
 * Fixed per-driver operating costs (₱). These are MONTHLY costs per driver and must be
 * scaled to the selected reporting period (NOT per-trip or per-day).
 *   Legacy defaults: ₱1,797.14 per driver per month
 *   Yearly:          ₱1,797.14 × 12 = ₱21,565.68 per driver
 * Net Income = Gross Income - (monthly fixed costs × number of months in the period).
 *
 * Values are now sourced from Finance → Financial Cost Settings through
 * App\Core\FinancialCosts::fixedCosts(), which falls back to the legacy constants
 * above until an authorized user edits the settings.
 *
 * @param float $months Number of months the costs apply to (default 1 = one month).
 * @param array{gross?: float, trips?: int|float} $context Period figures used by the
 *        percent_gross / per_trip calculation methods (per driver when called per driver).
 */
function dpm_fixed_costs(float $months = 1, array $context = []): array
{
    return FinancialCosts::fixedCosts($months, $context);
}

/**
 * Per-trip occupancy helper.
 *
 * Occupancy must be computed as:
 *   SUM(occupied spaces across every trip) / SUM(vehicle capacity across every trip) * 100
 *
 * A "trip" is identified by its trip identifier + the bus that operated it.
 * Occupied spaces for a trip = (passenger records) + (sum of other space_occupied).
 * Available capacity for a trip = the operating bus capacity (seats + standing_capacity).
 *
 * Trips whose bus capacity cannot be resolved are excluded from the capacity math so
 * they do not distort the percentage. "Overcapacity" tracks spaces beyond capacity.
 */
function dpm_occupancy(PDO $db, string $driverId, string $start, string $end): array
{
    $spaceExprLive = "CASE WHEN COALESCE(p.type, '') = 'other' THEN CASE WHEN COALESCE(p.space_occupied, 1) > 1 THEN COALESCE(p.space_occupied, 1) ELSE 1 END ELSE 1 END";
    $live = $db->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN cap > 0 THEN pass_occ END), 0) AS occupied_passenger_total,
            COALESCE(SUM(CASE WHEN cap > 0 THEN other_occ END), 0) AS occupied_other_total,
            COALESCE(SUM(CASE WHEN cap > 0 THEN cap END), 0) AS capacity_total,
            COALESCE(SUM(CASE WHEN cap > 0 THEN overcap END), 0) AS over_total
        FROM (
            SELECT
                SUM(CASE WHEN COALESCE(p.type, '') = 'other' THEN 0 ELSE 1 END) AS pass_occ,
                SUM(CASE WHEN COALESCE(p.type, '') = 'other' THEN CASE WHEN COALESCE(p.space_occupied, 1) > 1 THEN COALESCE(p.space_occupied, 1) ELSE 1 END ELSE 0 END) AS other_occ,
                (COALESCE(b.seats, 0)) AS cap,
                CASE WHEN (SUM($spaceExprLive) - (COALESCE(b.seats, 0))) > 0
                     THEN (SUM($spaceExprLive) - (COALESCE(b.seats, 0)))
                     ELSE 0 END AS overcap
            FROM passenger_trips pt
            LEFT JOIN passengers p ON p.id = pt.passenger_id
            JOIN (SELECT DISTINCT driver_id, bus_id FROM driver_bus_assignment) dba ON dba.driver_id = pt.driver_id AND dba.bus_id = pt.bus_no
            LEFT JOIN buses b ON b.bus_id = dba.bus_id
            WHERE pt.driver_id = :did AND pt.boarding_time BETWEEN :s AND :e
            GROUP BY pt.trip_no, dba.bus_id
        ) t
    ");
    $live->execute([':did' => $driverId, ':s' => $start, ':e' => $end]);
    $liveRow = $live->fetch(PDO::FETCH_ASSOC) ?: ['occupied_passenger_total' => 0, 'occupied_other_total' => 0, 'capacity_total' => 0, 'over_total' => 0];

    $spaceExprArch = "CASE WHEN COALESCE(pth.type, '') = 'other' THEN CASE WHEN COALESCE(pth.space_occupied, 1) > 1 THEN COALESCE(pth.space_occupied, 1) ELSE 1 END ELSE 1 END";
    $arch = $db->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN cap > 0 THEN pass_occ END), 0) AS occupied_passenger_total,
            COALESCE(SUM(CASE WHEN cap > 0 THEN other_occ END), 0) AS occupied_other_total,
            COALESCE(SUM(CASE WHEN cap > 0 THEN cap END), 0) AS capacity_total,
            COALESCE(SUM(CASE WHEN cap > 0 THEN overcap END), 0) AS over_total
        FROM (
            SELECT
                SUM(CASE WHEN COALESCE(pth.type, '') = 'other' THEN 0 ELSE 1 END) AS pass_occ,
                SUM(CASE WHEN COALESCE(pth.type, '') = 'other' THEN CASE WHEN COALESCE(pth.space_occupied, 1) > 1 THEN COALESCE(pth.space_occupied, 1) ELSE 1 END ELSE 0 END) AS other_occ,
                (COALESCE(b.seats, 0)) AS cap,
                CASE WHEN (SUM($spaceExprArch) - (COALESCE(b.seats, 0))) > 0
                     THEN (SUM($spaceExprArch) - (COALESCE(b.seats, 0)))
                     ELSE 0 END AS overcap
            FROM passenger_trip_history pth
            JOIN (SELECT DISTINCT driver_id, bus_id FROM driver_bus_assignment) dba ON dba.driver_id = pth.driver_id AND dba.bus_id = pth.bus_number
            LEFT JOIN buses b ON b.bus_id = dba.bus_id
            WHERE pth.driver_id = :did AND pth.trip_date BETWEEN :s AND :e
            GROUP BY pth.trip_id, dba.bus_id
        ) t
    ");
    $arch->execute([':did' => $driverId, ':s' => $start, ':e' => $end]);
    $archRow = $arch->fetch(PDO::FETCH_ASSOC) ?: ['occupied_passenger_total' => 0, 'occupied_other_total' => 0, 'capacity_total' => 0, 'over_total' => 0];

    $occupiedPassenger = (float)$liveRow['occupied_passenger_total'] + (float)$archRow['occupied_passenger_total'];
    $occupiedOther = (float)$liveRow['occupied_other_total'] + (float)$archRow['occupied_other_total'];
    $capacity = (float)$liveRow['capacity_total'] + (float)$archRow['capacity_total'];
    $over = (float)$liveRow['over_total'] + (float)$archRow['over_total'];

    $occupiedTotal = $occupiedPassenger + $occupiedOther;

    // N/A when the driver has NO bus assignment, or when no bus capacity can be resolved
    // for their trips in the period (don't guess a 0% figure).
    $asgStmt = $db->prepare("SELECT COUNT(*) FROM driver_bus_assignment WHERE driver_id = :did");
    $asgStmt->execute([':did' => $driverId]);
    $hasAssignment = (int)$asgStmt->fetchColumn();

    $capacityUnavailable = $hasAssignment <= 0;
    if ($capacityUnavailable) {
        $occupancy = null;
    } elseif ($capacity > 0) {
        $occupancy = min(100.0, round(($occupiedTotal / $capacity) * 100, 1));
    } else {
        $occupancy = null;
    }

    return [
        'occupied_passenger' => (int)$occupiedPassenger,
        'occupied_other' => (int)$occupiedOther,
        'vehicle_capacity' => (int)$capacity,
        'overcapacity_spaces' => (int)$over,
        'occupancy' => $occupancy,
        'capacity_unavailable' => $capacityUnavailable,
    ];
}

/**
 * Attendance helper (actual check-in records).
 *
 * Present is counted from the dispatcher 'Driver Checked In' records in
 * audit_trail (module Dispatcher), NOT from completed trips. Expected workdays
 * are the driver's scheduled weekdays inside the period, excluding rest days
 * and future dates. Working weekdays are derived from the driver's own
 * check-in history first, then assigned bus trips, falling back to
 * Monday-Friday when no schedule evidence exists.
 *
 * Present         = expected workdays with a recorded check-in.
 * Absent          = completed expected workdays (before today) with no
 *                   check-in, counted only from the driver's first check-in
 *                   onwards so days without reliable attendance data are
 *                   never treated as absences.
 * Attendance rate = present / expected workdays x 100 (null when the period
 *                   has no attendance signal to judge).
 */
function dpm_attendance(PDO $db, string $driverId, array $buses, string $start, string $end): array
{
    $startDay = new DateTime(substr($start, 0, 10));
    $endDay   = new DateTime(substr($end, 0, 10));
    if ($endDay < $startDay) {
        [$startDay, $endDay] = [$endDay, $startDay];
    }

    /* ---- 1. Actual attendance: dispatcher driver check-ins ---- */
    $checkins = [];
    try {
        $ciStmt = $db->prepare("
            SELECT new_values, created_at
            FROM audit_trail
            WHERE module = 'Dispatcher' AND action = 'Driver Checked In'
              AND created_at >= :from
            ORDER BY created_at ASC
        ");
        $ciStmt->execute([':from' => date('Y-m-d 00:00:00', strtotime('-400 days'))]);
        foreach ($ciStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $payload = json_decode((string)($row['new_values'] ?? ''), true);
            if (!is_array($payload) || (string)($payload['driver_id'] ?? '') !== $driverId) {
                continue;
            }
            $day = trim((string)($payload['trip_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                $day = substr((string)($row['created_at'] ?? ''), 0, 10);
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                $checkins[$day] = true;
            }
        }
    } catch (Throwable $e) {
        $checkins = [];
    }

    /* ---- 2. Working-weekday pattern: check-ins, then assigned bus trips ---- */
    $patternEndKey   = date('Y-m-d', min($endDay->getTimestamp(), time()));
    $patternStartKey = date('Y-m-d', strtotime($patternEndKey . ' -83 days'));

    $evidence = [];
    foreach (array_keys($checkins) as $d) {
        if ($d >= $patternStartKey && $d <= $patternEndKey) {
            $evidence[$d] = true;
        }
    }
    try {
        $tripStmt = $db->prepare("
            SELECT DISTINCT trip_date AS d FROM bus_trips
            WHERE driver_id = :did AND trip_date BETWEEN :s AND :e
              AND LOWER(COALESCE(status,'')) <> 'cancelled'
        ");
        $tripStmt->execute([':did' => $driverId, ':s' => $patternStartKey, ':e' => $patternEndKey]);
        foreach ($tripStmt->fetchAll(PDO::FETCH_COLUMN) as $d) {
            $d = substr((string)$d, 0, 10);
            if ($d !== '') {
                $evidence[$d] = true;
            }
        }
    } catch (Throwable $e) {
        // bus_trips may be unavailable; keep check-in evidence only
    }

    $weekdayCounts = [];
    foreach (array_keys($evidence) as $d) {
        $ts = strtotime($d . ' 00:00:00');
        if ($ts === false) {
            continue;
        }
        $wd = (int)date('N', $ts);
        $weekdayCounts[$wd] = ($weekdayCounts[$wd] ?? 0) + 1;
    }

    $workingWeekdays = [];
    foreach ($weekdayCounts as $wd => $count) {
        if ($count >= 2) {
            $workingWeekdays[$wd] = true;
        }
    }
    if (empty($workingWeekdays)) {
        if (!empty($weekdayCounts)) {
            /* Sparse history: every weekday the driver actually worked. */
            foreach ($weekdayCounts as $wd => $count) {
                $workingWeekdays[$wd] = true;
            }
        } else {
            /* No history at all: default Monday-Friday. */
            $workingWeekdays = [1 => true, 2 => true, 3 => true, 4 => true, 5 => true];
        }
    }

    /* ---- 3. Expected workdays: scheduled weekdays in the period, excluding
       rest days and future dates. ---- */
    $today = new DateTime('today');
    $expectedEnd = min($endDay->getTimestamp(), $today->getTimestamp());
    $scheduledDates = [];
    $cursor = clone $startDay;
    $guard = 0;
    while ($cursor->getTimestamp() <= $expectedEnd && $guard < 400) {
        if (!empty($workingWeekdays[(int)$cursor->format('N')])) {
            $scheduledDates[$cursor->format('Y-m-d')] = true;
        }
        $cursor->modify('+1 day');
        $guard++;
    }

    /* ---- 4. Present = expected workdays with a recorded check-in. ---- */
    $present   = 0;
    $extraDays = 0;
    $periodStartKey = $startDay->format('Y-m-d');
    $periodEndKey   = $endDay->format('Y-m-d');
    foreach (array_keys($checkins) as $day) {
        if (isset($scheduledDates[$day])) {
            $present++;
        } elseif ($day >= $periodStartKey && $day <= $periodEndKey) {
            $extraDays++;
        }
    }

    /* ---- 5. Absent = completed expected workdays with no check-in. Only
       days from the driver's first recorded check-in onwards are eligible,
       and today's incomplete day is never counted. ---- */
    $firstCheckin = null;
    foreach (array_keys($checkins) as $d) {
        if ($firstCheckin === null || $d < $firstCheckin) {
            $firstCheckin = $d;
        }
    }
    $todayKey = $today->format('Y-m-d');
    $absent = 0;
    if ($firstCheckin !== null) {
        foreach (array_keys($scheduledDates) as $day) {
            if ($day >= $firstCheckin && $day < $todayKey && !isset($checkins[$day])) {
                $absent++;
            }
        }
    }

    $scheduled = count($scheduledDates);
    $rate      = ($scheduled > 0 && ($present > 0 || $absent > 0))
        ? round(($present / $scheduled) * 100, 1)
        : null;

    return [
        'present' => $present,
        'absent' => $absent,
        'scheduled' => $scheduled,
        'rate' => $rate,
        'max' => $scheduled,
        'extra_days' => $extraDays,
        'working_weekdays' => array_keys($workingWeekdays),
    ];
}

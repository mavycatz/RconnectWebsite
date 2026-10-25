<?php

declare(strict_types=1);

/**
 * Weekly travel-pattern rules for the passenger trip-history generator.
 *
 * Pure functions only: each passenger profile gets a deterministic weekly
 * plan (travel weekdays, legs per travel day, one-way odds, absence odds),
 * daily legs are materialized per calendar day, planned legs are matched to
 * the day's scheduled trips, and the passenger pool is sized from expected
 * weekly demand. Keeping the rules here makes them unit-testable without
 * booting the application or the database.
 */

require_once __DIR__ . '/passenger_routines.php';

/* Travel-day bands per occupation (inclusive). */
const PLAN_MIN_TRAVEL_DAYS = [
    'student' => 5,
    'worker' => 5,
    'vendor' => 4,
    'senior' => 1,
    'commuter' => 1,
];
const PLAN_MAX_TRAVEL_DAYS = [
    'student' => 6,
    'worker' => 6,
    'vendor' => 6,
    'senior' => 3,
    'commuter' => 5,
];

/* Base odds that a planned round-trip day becomes a one-way day. */
const PLAN_ONE_WAY_ODDS = [
    'student' => 0.05,
    'worker' => 0.06,
    'vendor' => 0.10,
    'senior' => 0.15,
    'commuter' => 0.10,
];

/* Share of students and workers whose week includes a sixth (Saturday) day. */
const PLAN_STUDENT_SATURDAY_SHARE = 0.35;
const PLAN_WORKER_SIX_DAY_SHARE = 0.16;
const PLAN_WORKER_SHIFTED_SHARE = 0.07;

/* Minimum layover between an outbound arrival and its return departure. */
const PLAN_LAYOVER_SECONDS = 15 * 60;

/* Leg windows are extended by this much on each side when matching trips. */
const PLAN_WINDOW_FLEX_SECONDS = 3600;

/* Top-up allowance above the planned weekly legs, per profile. */
const PLAN_WEEKLY_SLACK = 4;

/* Pool sizing bounds and demand estimates (calibrated to observed generation:
   trips average roughly 16 riders, regular-band attendance lands near 0.8,
   and round-trip days average close to two legs per traveling rider). */
const PLAN_POOL_MIN = 400;
const PLAN_POOL_MAX = 5000;
const PLAN_POOL_RIDERS_PER_BUS_MIN = 20;
const PLAN_RIDERS_PER_TRIP = 16.0;
const PLAN_EXPECTED_ATTENDANCE = 0.80;
const PLAN_EXPECTED_LEGS_PER_TRAVEL_DAY = 1.90;
const PLAN_OCCUPATION_SHARES = [
    'student' => 18,
    'worker' => 30,
    'vendor' => 26,
    'senior' => 10,
    'commuter' => 16,
];

function planNormalizeOccupation(string $occupation): string
{
    $occupation = strtolower(trim($occupation));
    return isset(PLAN_MIN_TRAVEL_DAYS[$occupation]) ? $occupation : 'commuter';
}

function planTravelDaysBounds(string $occupation): array
{
    $occupation = planNormalizeOccupation($occupation);
    return [PLAN_MIN_TRAVEL_DAYS[$occupation], PLAN_MAX_TRAVEL_DAYS[$occupation]];
}

/* Stable ordering of a weekday list by a per-profile key (no mt_rand). */
function planStableWeekdayOrder(string $name, array $weekdays): array
{
    usort($weekdays, static function (int $a, int $b) use ($name): int {
        $ra = routineRoll('plan-day-order-' . $name . '-' . $a);
        $rb = routineRoll('plan-day-order-' . $name . '-' . $b);
        return $ra <=> $rb ?: $a <=> $b;
    });
    return $weekdays;
}

/**
 * Deterministic weekly travel plan for one profile.
 *
 * Returns ['days' => [ISO weekday numbers sorted], 'legs' => 'round',
 * 'absence' => float (0..1), 'oneway' => float (0..1), 'anchor' => float].
 */
function weeklyPlanFor(string $name, string $occupation, float $regularity, bool $commuter): array
{
    $occupation = planNormalizeOccupation($occupation);
    $regularity = max(0.0, min(1.0, $regularity));

    if ($occupation === 'student') {
        $days = [1, 2, 3, 4, 5];
        if (routineRoll('plan-sixth-day-' . $name) < PLAN_STUDENT_SATURDAY_SHARE) {
            $days[] = 6;
        }
    } elseif ($occupation === 'worker') {
        $variant = routineRoll('plan-worker-variant-' . $name);
        if ($variant < PLAN_WORKER_SIX_DAY_SHARE) {
            $days = [1, 2, 3, 4, 5, 6];
        } elseif ($variant < PLAN_WORKER_SIX_DAY_SHARE + PLAN_WORKER_SHIFTED_SHARE) {
            $days = [2, 3, 4, 5, 6];
        } else {
            $days = [1, 2, 3, 4, 5];
        }
    } elseif ($occupation === 'vendor') {
        [$minDays, $maxDays] = planTravelDaysBounds($occupation);
        $count = $minDays + (int)floor(routineRoll('plan-vendor-days-' . $name) * ($maxDays - $minDays + 1));
        $count = max($minDays, min($maxDays, $count));
        /* Market rhythm: Saturday first, then stable mid-week days. */
        $order = planStableWeekdayOrder($name, [2, 4, 1, 3, 5]);
        $days = array_merge([6], array_slice($order, 0, $count - 1));
        sort($days);
    } elseif ($occupation === 'senior') {
        [$minDays, $maxDays] = planTravelDaysBounds($occupation);
        $count = $minDays + (int)floor(routineRoll('plan-senior-days-' . $name) * ($maxDays - $minDays + 1));
        $count = max($minDays, min($maxDays, $count));
        $order = planStableWeekdayOrder($name, [2, 4, 6]);
        $days = array_slice($order, 0, $count);
        sort($days);
    } else {
        /* General commuters: frequency follows the regularity band. */
        [$minDays, $maxDays] = planTravelDaysBounds('commuter');
        $count = 1 + (int)floor($regularity * 5.0);
        $count = max($minDays, min($maxDays, $count));
        $order = planStableWeekdayOrder($name, [1, 2, 3, 4, 5]);
        $days = array_slice($order, 0, $count);
        sort($days);
    }

    $absence = 1.0 - routineAttendanceProbability($regularity, $occupation, 'weekday');
    $absence = max(0.03, min(0.95, $absence));

    $oneway = PLAN_ONE_WAY_ODDS[$occupation] ?? 0.10;
    $oneway *= 0.6 + (1.0 - $regularity) * 0.8;
    $oneway = max(0.01, min(0.30, $oneway));

    return [
        'days' => array_values($days),
        'legs' => 'round',
        'absence' => $absence,
        'oneway' => $oneway,
        'anchor' => routineRoll('plan-anchor-' . $name),
        'occupation' => $occupation,
    ];
}

/**
 * Legs a profile travels on one calendar day: [], ['out'], ['ret'], or
 * ['out', 'ret']. Suppressed rides are never moved to another day: a day is
 * simply empty when the plan does not include it, attendance fails, or a
 * commuter holiday suppresses school/work travel.
 */
function plannedLegsFor(array $plan, string $name, string $day, string $occupation, float $regularity, string $dayType): array
{
    $occupation = planNormalizeOccupation($occupation);
    $dow = (int)date('N', strtotime($day));
    $days = array_map('intval', (array)($plan['days'] ?? []));
    if (!in_array($dow, $days, true)) {
        return [];
    }
    if ($dayType === 'holiday' && routineIsCommuteOccupation($occupation)) {
        return [];
    }
    if (!routineAttends($name, $day, $regularity, $occupation, $dayType)) {
        return [];
    }
    $oneway = (float)($plan['oneway'] ?? 0.05);
    if (routineRoll('plan-oneway-' . $name . '-' . $day) < $oneway) {
        return [routineRoll('plan-oneway-dir-' . $name . '-' . $day) < 0.5 ? 'out' : 'ret'];
    }
    return ['out', 'ret'];
}

/**
 * Weekly ride cap for a profile: the maximum planned legs for its occupation
 * plus the deterministic top-up allowance.
 */
function planWeeklyRideCap(string $occupation): int
{
    $occupation = planNormalizeOccupation($occupation);
    return PLAN_MAX_TRAVEL_DAYS[$occupation] * 2 + PLAN_WEEKLY_SLACK;
}

/* Trips compatible with a leg window (and optional earliest departure). */
function planLegCandidates(array $trips, ?array $window, int $minSec = 0): array
{
    if (!$trips) {
        return [];
    }
    $from = $window !== null ? (int)$window[0] - PLAN_WINDOW_FLEX_SECONDS : 0;
    $to = $window !== null ? (int)$window[1] + PLAN_WINDOW_FLEX_SECONDS : PHP_INT_MAX;
    $from = max($from, $minSec);
    $out = [];
    foreach ($trips as $trip) {
        $sec = (int)$trip['sec'];
        if ($sec >= $from && $sec <= $to) {
            $out[] = $trip;
        }
    }
    return $out;
}

/* Space still available on a trip (planned riders stop at its target). */
function planTripHasSpace(array $trip, array $load): bool
{
    $index = (int)$trip['index'];
    $limit = PHP_INT_MAX;
    if ((int)($trip['target'] ?? 0) > 0) {
        $limit = min($limit, (int)$trip['target']);
    }
    if ((int)($trip['capacity'] ?? 0) > 0) {
        $limit = min($limit, (int)$trip['capacity']);
    }
    if ($limit === PHP_INT_MAX) {
        $limit = 0;
    }
    return (int)($load[$index] ?? 0) < $limit;
}

/**
 * Stable choice among compatible trips: the per-profile/day roll selects the
 * starting departure, then the next trip with space is taken. Different days
 * roll different starts, so a rider rotates across departures and buses.
 */
function planPickTrip(array $candidates, string $key, array $load): ?array
{
    $count = count($candidates);
    if ($count === 0) {
        return null;
    }
    $start = (int)floor(routineRoll($key) * $count) % $count;
    for ($i = 0; $i < $count; $i++) {
        $trip = $candidates[($start + $i) % $count];
        if (planTripHasSpace($trip, $load)) {
            return $trip;
        }
    }
    return null;
}

/**
 * Match planned legs onto the day's scheduled trips.
 *
 * $dayPlans entries: ['name', 'day', 'route' (home), 'return' (opposite route
 * or null), 'legs' (['out'], ['ret'], or ['out','ret']), 'windows' (list of
 * [from, to] seconds; first used for outbound, last for return)].
 * $trips entries: ['index', 'id', 'routeName', 'sec' (departure), 'arrSec',
 * 'min', 'target', 'capacity'].
 *
 * Returns ['assignments' => [['trip', 'name', 'leg', 'route', 'sec']...],
 * 'load' => [tripIndex => count], 'unplaced' => [names...]].
 *
 * Round plans commit only when the return direction has a trip inside the
 * return window; otherwise the day degrades to the outbound leg only.
 * Return-only plans place a single return leg. Planned riders stop at each
 * trip's target load; minimums are filled by assignTopUpLegs.
 */
function assignPlannedLegs(array $dayPlans, array $trips, array $routeOpposites): array
{
    $byRoute = [];
    foreach ($trips as $trip) {
        $byRoute[(string)$trip['routeName']][] = $trip;
    }
    foreach ($byRoute as &$list) {
        usort($list, static fn(array $a, array $b): int => (int)$a['sec'] <=> (int)$b['sec']);
    }
    unset($list);

    $load = [];
    $assignments = [];
    $unplaced = [];

    foreach ($dayPlans as $plan) {
        $name = (string)($plan['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $day = (string)($plan['day'] ?? '');
        $home = (string)($plan['route'] ?? '');
        $return = (string)($plan['return'] ?? '');
        if ($return === '' && $home !== '') {
            $return = (string)($routeOpposites[$home] ?? '');
        }
        $legs = array_values(array_unique(array_map('strval', (array)($plan['legs'] ?? []))));
        $windows = (array)($plan['windows'] ?? []);
        $outWindow = $windows[0] ?? null;
        $retWindow = $windows[count($windows) - 1] ?? $outWindow;
        $placed = false;

        $outTrip = null;
        if (in_array('out', $legs, true) && $home !== '') {
            $candidates = planLegCandidates($byRoute[$home] ?? [], is_array($outWindow) ? $outWindow : null);
            $outTrip = planPickTrip($candidates, 'plan-out-' . $name . '-' . $day, $load);
            if ($outTrip !== null) {
                $load[(int)$outTrip['index']] = (int)($load[(int)$outTrip['index']] ?? 0) + 1;
                $assignments[] = [
                    'trip' => (int)$outTrip['index'],
                    'name' => $name,
                    'leg' => 'out',
                    'route' => $home,
                    'sec' => (int)$outTrip['sec'],
                ];
                $placed = true;
            }
        }

        if (in_array('ret', $legs, true) && $return !== '') {
            $isReturnOnly = !in_array('out', $legs, true);
            if ($isReturnOnly || $outTrip !== null) {
                $minSec = $outTrip !== null ? (int)$outTrip['arrSec'] + PLAN_LAYOVER_SECONDS : 0;
                $candidates = planLegCandidates(
                    $byRoute[$return] ?? [],
                    is_array($retWindow) ? $retWindow : null,
                    $minSec
                );
                $retTrip = planPickTrip($candidates, 'plan-ret-' . $name . '-' . $day, $load);
                if ($retTrip !== null) {
                    $load[(int)$retTrip['index']] = (int)($load[(int)$retTrip['index']] ?? 0) + 1;
                    $assignments[] = [
                        'trip' => (int)$retTrip['index'],
                        'name' => $name,
                        'leg' => 'ret',
                        'route' => $return,
                        'sec' => (int)$retTrip['sec'],
                    ];
                    $placed = true;
                }
            }
        }

        if (!$placed) {
            $unplaced[] = $name;
        }
    }

    return [
        'assignments' => $assignments,
        'load' => $load,
        'unplaced' => $unplaced,
    ];
}

/**
 * Fill under-minimum trips from a pool of plan-compatible profiles that still
 * have weekly slack. Each rider receives at most one top-up leg per day, and
 * no rider is placed twice on the same trip. Deterministic order: riders are
 * considered by a stable per-day roll, trips in schedule order.
 *
 * $topUpPool entries: ['name', 'route' (home), 'return' (opposite or null),
 * 'windows'].
 * Returns ['assignments' => [...], 'load' => [...]] merged onto $load.
 */
function assignTopUpLegs(array $trips, array $topUpPool, array $load, string $day): array
{
    $assignments = [];
    if (!$trips || !$topUpPool) {
        return ['assignments' => $assignments, 'load' => $load];
    }
    usort($topUpPool, static function (array $a, array $b) use ($day): int {
        return routineRoll('plan-topup-' . $a['name'] . '-' . $day)
            <=> routineRoll('plan-topup-' . $b['name'] . '-' . $day)
            ?: strcmp((string)$a['name'], (string)$b['name']);
    });
    $taken = [];
    foreach ($trips as $trip) {
        $index = (int)$trip['index'];
        $min = (int)($trip['min'] ?? 0);
        while ((int)($load[$index] ?? 0) < $min) {
            $granted = false;
            foreach ($topUpPool as $rider) {
                $name = (string)$rider['name'];
                if (isset($taken[$name]) || !planTripHasSpace($trip, $load)) {
                    continue;
                }
                $home = (string)($rider['route'] ?? '');
                $return = (string)($rider['return'] ?? '');
                if ($home !== '' && $home === (string)$trip['routeName']) {
                    $leg = 'out';
                } elseif ($return !== '' && $return === (string)$trip['routeName']) {
                    $leg = 'ret';
                } else {
                    continue;
                }
                $load[$index] = (int)($load[$index] ?? 0) + 1;
                $taken[$name] = true;
                $assignments[] = [
                    'trip' => $index,
                    'name' => $name,
                    'leg' => $leg,
                    'route' => (string)$trip['routeName'],
                    'sec' => (int)$trip['sec'],
                    'topup' => true,
                ];
                $granted = true;
                break;
            }
            if (!$granted) {
                break;
            }
        }
    }
    return ['assignments' => $assignments, 'load' => $load];
}

/**
 * Passenger pool target from expected weekly demand: daily seats divided by
 * the expected legs each rider takes per day. Clamped to a floor that keeps
 * every bus served and to the configured ceiling.
 */
function planPoolTarget(int $dailyTrips, int $fleetSize, array $options = []): int
{
    $dailyTrips = max(0, $dailyTrips);
    $fleetSize = max(0, $fleetSize);
    $shares = (array)($options['shares'] ?? PLAN_OCCUPATION_SHARES);
    $ridersPerTrip = (float)($options['riders_per_trip'] ?? PLAN_RIDERS_PER_TRIP);
    $attendance = (float)($options['attendance'] ?? PLAN_EXPECTED_ATTENDANCE);
    $legsPerTravelDay = (float)($options['legs_per_travel_day'] ?? PLAN_EXPECTED_LEGS_PER_TRAVEL_DAY);

    $expectedDays = 0.0;
    $totalShare = 0.0;
    foreach ($shares as $occupation => $share) {
        [$minDays, $maxDays] = planTravelDaysBounds((string)$occupation);
        $expectedDays += (float)$share * (($minDays + $maxDays) / 2);
        $totalShare += (float)$share;
    }
    $expectedDays = $totalShare > 0 ? $expectedDays / $totalShare : 4.5;

    $legsPerRiderDay = ($expectedDays / 7.0) * $attendance * $legsPerTravelDay;
    $target = (int)round(($dailyTrips * $ridersPerTrip) / max(0.05, $legsPerRiderDay));

    $floor = max((int)($options['min'] ?? PLAN_POOL_MIN), $fleetSize * PLAN_POOL_RIDERS_PER_BUS_MIN);
    $ceiling = max($floor, (int)($options['max'] ?? PLAN_POOL_MAX));
    return max($floor, min($ceiling, $target));
}

/**
 * Weekly plan conformance violations from the generation ledger.
 *
 * Ledger shape:
 *   ['plan' => [day][name] => ['planned' => [legs], 'placed' => ['out' => route|null,
 *      'ret' => route|null], 'topup' => bool, 'out_at' => sec|null, 'ret_at' => sec|null],
 *    'plan_caps' => [name => int],
 *    'rides' => [day][name => count],
 *    'ride_dupes' => [string...]]
 *
 * Returns a list of human-readable violations (empty when the batch conforms).
 */
function weeklyPlanViolations(array $ledger): array
{
    $violations = [];
    $plan = (array)($ledger['plan'] ?? []);
    $caps = (array)($ledger['plan_caps'] ?? []);
    $weekly = [];

    foreach ($plan as $day => $names) {
        $week = date('o-W', strtotime((string)$day));
        foreach ($names as $name => $entry) {
            $placed = (array)($entry['placed'] ?? []);
            $planned = array_values(array_unique(array_map('strval', (array)($entry['planned'] ?? []))));
            $topup = !empty($entry['topup']);

            $outRoute = $placed['out'] ?? null;
            $retRoute = $placed['ret'] ?? null;
            $outAt = $entry['out_at'] ?? null;
            $retAt = $entry['ret_at'] ?? null;

            if ($outRoute !== null && $retRoute !== null && $outAt !== null && $retAt !== null && (int)$retAt <= (int)$outAt) {
                $violations[] = "return ride precedes outbound for $name on $day";
            }
            if ($retRoute !== null && $outRoute === null && !$topup && $planned !== ['ret']) {
                $violations[] = "return ride without outbound for $name on $day";
            }
            $placedCount = (int)($outRoute !== null) + (int)($retRoute !== null);
            if ($placedCount > 2) {
                $violations[] = "more than two legs placed for $name on $day";
            }
            if (!$topup && $placedCount > count($planned)) {
                $violations[] = "extra leg without top-up for $name on $day";
            }

            $rides = (int)($ledger['rides'][$day][$name] ?? 0);
            if ($rides > 0) {
                $weekly[$week][$name] = ($weekly[$week][$name] ?? 0) + $rides;
            }
        }
    }

    foreach ($weekly as $week => $names) {
        foreach ($names as $name => $rides) {
            $cap = (int)($caps[$name] ?? 0);
            if ($cap > 0 && $rides > $cap) {
                $violations[] = "$name has $rides rides in week $week (cap $cap)";
            }
        }
    }

    foreach ((array)($ledger['ride_dupes'] ?? []) as $dupe) {
        $violations[] = 'duplicate passenger-on-trip ride ' . (string)$dupe;
    }

    return $violations;
}

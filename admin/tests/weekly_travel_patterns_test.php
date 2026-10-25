<?php declare(strict_types=1);

/**
 * Weekly travel-pattern rule checks: plan bands and stability, daily leg
 * materialization (attendance, holidays, one-way days), the leg-to-trip
 * matcher (pairing, capacity, layover, top-up), pool sizing, and weekly
 * violation reporting. Pure functions only - no database is required.
 */

require_once __DIR__ . '/../api/TripDashboard/weekly_travel_patterns.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function plannedWeekday(array $plan, string $weekStart = '2026-09-07'): ?string
{
    for ($i = 0; $i < 7; $i++) {
        $day = date('Y-m-d', strtotime($weekStart . " +$i day"));
        if (in_array((int)date('N', strtotime($day)), array_map('intval', $plan['days']), true)) {
            return $day;
        }
    }
    return null;
}

function firstUnplannedWeekday(array $plan, string $weekStart = '2026-09-07'): ?string
{
    for ($i = 0; $i < 7; $i++) {
        $day = date('Y-m-d', strtotime($weekStart . " +$i day"));
        if (!in_array((int)date('N', strtotime($day)), array_map('intval', $plan['days']), true)) {
            return $day;
        }
    }
    return null;
}

/* ----------------------- 1.1 plan shape and bands ----------------------- */

$occupations = ['student', 'worker', 'vendor', 'senior', 'commuter'];
$bandSeen = [];
foreach ($occupations as $occupation) {
    [$minDays, $maxDays] = planTravelDaysBounds($occupation);
    $bandSeen[$occupation] = [];
    for ($i = 0; $i < 250; $i++) {
        $name = 'Plan ' . ucfirst($occupation) . ' ' . $i;
        $regularity = 0.15 + ($i % 8) * 0.1;
        $plan = weeklyPlanFor($name, $occupation, $regularity, $occupation !== 'vendor');
        $again = weeklyPlanFor($name, $occupation, $regularity, $occupation !== 'vendor');
        check($plan === $again, "$occupation plan is deterministic");
        check($plan['days'] !== [], "$occupation plan travels at least one day");
        foreach ($plan['days'] as $dow) {
            check($dow >= 1 && $dow <= 7, "$occupation plan weekday in ISO range");
        }
        $count = count(array_unique($plan['days']));
        check($count >= $minDays && $count <= $maxDays, "$occupation plan stays inside its band ($count)");
        check($plan['absence'] > 0.0 && $plan['absence'] < 1.0, "$occupation absence probability in (0,1)");
        check($plan['oneway'] >= 0.01 && $plan['oneway'] <= 0.30, "$occupation one-way odds in range");
        $bandSeen[$occupation][$count] = true;
    }
}

check(array_keys($bandSeen['student']) === [5, 6] || (isset($bandSeen['student'][5]) && isset($bandSeen['student'][6])), 'Students produce both 5-day and 6-day weeks');
check(isset($bandSeen['worker'][6]), 'Some workers run six-day weeks');
check(count($bandSeen['vendor']) >= 2, 'Vendors vary between market-day counts');
check(isset($bandSeen['senior'][1]) && isset($bandSeen['senior'][3]), 'Seniors vary between occasional and regular visits');
check(count($bandSeen['commuter']) >= 3, 'General commuters vary in weekly frequency');

$student = weeklyPlanFor('Band Student', 'student', 0.9, true);
check(array_diff([1, 2, 3, 4, 5], $student['days']) === [], 'Students always include Monday-Friday');
$worker = weeklyPlanFor('Band Worker', 'worker', 0.9, true);
check(in_array(1, $worker['days'], true) || in_array(2, $worker['days'], true), 'Worker weeks start early in the week');

/* --------------- 1.2 daily legs: attendance, holiday, one-way ------------ */

$planDay = plannedWeekday($student);
check($planDay !== null, 'Student has a planned weekday');
check(plannedLegsFor($student, 'Band Student', $planDay, 'student', 0.9, 'weekday') === ['out', 'ret'], 'Regular student round-trips on a planned weekday');
$unplanned = firstUnplannedWeekday($student);
if ($unplanned !== null) {
    check(plannedLegsFor($student, 'Band Student', $unplanned, 'student', 0.9, 'weekday') === [], 'Unplanned weekday carries no legs');
}
check(plannedLegsFor($student, 'Band Student', $planDay, 'student', 0.9, 'holiday') === [], 'Holidays suppress student work travel');
$workerPlan = weeklyPlanFor('Holiday Worker', 'worker', 0.9, true);
$workerDay = plannedWeekday($workerPlan);
if ($workerDay !== null) {
    check(plannedLegsFor($workerPlan, 'Holiday Worker', $workerDay, 'worker', 0.9, 'holiday') === [], 'Holidays suppress worker travel');
}
$vendorPlan = weeklyPlanFor('Holiday Vendor', 'vendor', 0.9, false);
$vendorDay = plannedWeekday($vendorPlan);
if ($vendorDay !== null) {
    $vendorHoliday = plannedLegsFor($vendorPlan, 'Holiday Vendor', $vendorDay, 'vendor', 0.9, 'holiday');
    check($vendorHoliday !== [], 'Vendors are not force-suppressed on holidays');
}

$absentName = 'Attendance Case';
$absentPlan = weeklyPlanFor($absentName, 'student', 0.05, true);
$absentDay = plannedWeekday($absentPlan);
check($absentDay !== null, 'Absence case has a planned day');
check(plannedLegsFor($absentPlan, $absentName, $absentDay, 'student', 0.05, 'weekday') === [], 'Low-regularity attendance removes the planned ride');
check(
    plannedLegsFor($absentPlan, $absentName, $absentDay, 'student', 0.05, 'weekday') === plannedLegsFor($absentPlan, $absentName, $absentDay, 'student', 0.05, 'weekday'),
    'Attendance decision is deterministic per name and day'
);

$oneWayFound = false;
$twoLegFound = false;
for ($i = 0; $i < 200 && (!$oneWayFound || !$twoLegFound); $i++) {
    $name = 'Oneway Case ' . $i;
    $plan = weeklyPlanFor($name, 'commuter', 0.5, true);
    $day = plannedWeekday($plan);
    if ($day === null) {
        continue;
    }
    $legs = plannedLegsFor($plan, $name, $day, 'commuter', 0.5, 'weekday');
    if (count($legs) === 1) {
        $oneWayFound = true;
        check($legs[0] === 'out' || $legs[0] === 'ret', 'One-way day carries a single directional leg');
    }
    if (count($legs) === 2) {
        $twoLegFound = true;
    }
}
check($oneWayFound, 'One-way days occur across sampled profiles');
check($twoLegFound, 'Round days occur across sampled profiles');

/* ------------------------ 1.3/3.4 trip matching ------------------------- */

$trips = [
    ['index' => 0, 'id' => 'T1', 'routeName' => 'A-B', 'sec' => 7 * 3600, 'arrSec' => 8 * 3600, 'min' => 1, 'target' => 5, 'capacity' => 5],
    ['index' => 1, 'id' => 'T2', 'routeName' => 'A-B', 'sec' => 8 * 3600, 'arrSec' => 9 * 3600, 'min' => 1, 'target' => 5, 'capacity' => 5],
    ['index' => 2, 'id' => 'T3', 'routeName' => 'B-A', 'sec' => 16 * 3600, 'arrSec' => 17 * 3600, 'min' => 1, 'target' => 5, 'capacity' => 5],
    ['index' => 3, 'id' => 'T4', 'routeName' => 'B-A', 'sec' => 17 * 3600, 'arrSec' => 18 * 3600, 'min' => 1, 'target' => 5, 'capacity' => 5],
];
$opposites = ['A-B' => 'B-A', 'B-A' => 'A-B'];
$windows = [[6 * 3600, 9 * 3600], [15 * 3600, 18 * 3600 + 1800]];
$dayPlans = [];
for ($i = 0; $i < 3; $i++) {
    $dayPlans[] = [
        'name' => 'Rider ' . $i,
        'day' => '2026-09-07',
        'route' => 'A-B',
        'return' => null,
        'legs' => ['out', 'ret'],
        'windows' => $windows,
    ];
}
$result = assignPlannedLegs($dayPlans, $trips, $opposites);
check(count($result['assignments']) === 6, 'Round journeys produce outbound and return legs');
$byName = [];
foreach ($result['assignments'] as $a) {
    $byName[$a['name']][$a['leg']] = $a;
}
foreach (['Rider 0', 'Rider 1', 'Rider 2'] as $name) {
    check(isset($byName[$name]['out'], $byName[$name]['ret']), "$name has both legs");
    check($byName[$name]['out']['sec'] < $byName[$name]['ret']['sec'], "$name returns after boarding outbound");
    check($byName[$name]['out']['route'] !== $byName[$name]['ret']['route'], "$name legs use opposite directions");
}
check(assignPlannedLegs($dayPlans, $trips, $opposites) === $result, 'Matcher is deterministic');

/* Capacity stops planned riders at the trip target. */
$smallTrips = $trips;
foreach ($smallTrips as &$t) {
    if ($t['routeName'] === 'A-B') {
        $t['target'] = 1;
    }
}
unset($t);
$capacityResult = assignPlannedLegs($dayPlans, $smallTrips, $opposites);
$outCount = 0;
foreach ($capacityResult['assignments'] as $a) {
    if ($a['leg'] === 'out') {
        $outCount++;
    }
}
check($outCount === 2, 'Planned riders stop at the trip target');
check(count($capacityResult['unplaced']) === 1, 'Overflow rider is left unplaced instead of stranded');

/* Layover: a return before arrival + layover is not eligible. */
$layoverTrips = [
    ['index' => 0, 'id' => 'L1', 'routeName' => 'A-B', 'sec' => 8 * 3600, 'arrSec' => 9 * 3600, 'min' => 1, 'target' => 5, 'capacity' => 5],
    ['index' => 1, 'id' => 'L2', 'routeName' => 'B-A', 'sec' => 9 * 3600 + 300, 'arrSec' => 10 * 3600 + 300, 'min' => 1, 'target' => 5, 'capacity' => 5],
    ['index' => 2, 'id' => 'L3', 'routeName' => 'B-A', 'sec' => 9 * 3600 + 1800, 'arrSec' => 10 * 3600 + 1800, 'min' => 1, 'target' => 5, 'capacity' => 5],
];
$layoverPlans = [[
    'name' => 'Layover Rider',
    'day' => '2026-09-07',
    'route' => 'A-B',
    'return' => null,
    'legs' => ['out', 'ret'],
    'windows' => [[6 * 3600, 9 * 3600 + 1800], [9 * 3600, 18 * 3600]],
]];
$layoverResult = assignPlannedLegs($layoverPlans, $layoverTrips, $opposites);
$layoverLegs = [];
foreach ($layoverResult['assignments'] as $a) {
    $layoverLegs[$a['leg']] = $a;
}
check(isset($layoverLegs['ret']), 'Layover rider has a return');
check($layoverLegs['ret']['sec'] >= (int)$layoverTrips[0]['arrSec'] + PLAN_LAYOVER_SECONDS, 'Return departs after arrival plus layover');

/* One-way degradation: no return-direction trip exists. */
$noReturnTrips = [$trips[0], $trips[1]];
$degraded = assignPlannedLegs($dayPlans, $noReturnTrips, $opposites);
check(count($degraded['assignments']) === 3, 'Without return trips, only outbound legs are placed');
foreach ($degraded['assignments'] as $a) {
    check($a['leg'] === 'out', 'Degraded day never strands a return leg');
}

/* Return-only plan places a single return leg. */
$retOnlyPlans = [[
    'name' => 'Return Only',
    'day' => '2026-09-08',
    'route' => 'A-B',
    'return' => null,
    'legs' => ['ret'],
    'windows' => [[6 * 3600, 9 * 3600], [15 * 3600, 18 * 3600 + 1800]],
]];
$retOnlyResult = assignPlannedLegs($retOnlyPlans, $trips, $opposites);
check(count($retOnlyResult['assignments']) === 1 && $retOnlyResult['assignments'][0]['leg'] === 'ret', 'Return-only day places a single return leg');

/* Bus variation: the same rider picks different departures on different days. */
$rotationPlans = [];
foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10'] as $d) {
    $rotationPlans[] = [
        'name' => 'Rotation Rider',
        'day' => $d,
        'route' => 'A-B',
        'return' => null,
        'legs' => ['out'],
        'windows' => $windows,
    ];
}
$rotationResult = assignPlannedLegs($rotationPlans, $trips, $opposites);
$rotationTrips = array_unique(array_map(static fn(array $a): int => $a['trip'], $rotationResult['assignments']));
check(count($rotationTrips) > 1, 'Riders rotate across departures on different days');

/* Top-up: under-minimum trips draw plan-compatible riders with slack. */
$topUpTrips = [
    ['index' => 0, 'id' => 'U1', 'routeName' => 'A-B', 'sec' => 7 * 3600, 'arrSec' => 8 * 3600, 'min' => 2, 'target' => 3, 'capacity' => 3],
];
$topUpPool = [
    ['name' => 'Topup One', 'route' => 'A-B', 'return' => 'B-A', 'windows' => $windows],
    ['name' => 'Topup Two', 'route' => 'A-B', 'return' => 'B-A', 'windows' => $windows],
];
$topUp = assignTopUpLegs($topUpTrips, $topUpPool, [], '2026-09-07');
check(count($topUp['assignments']) === 2, 'Top-up fills the under-minimum trip');
check((int)$topUp['load'][0] === 2, 'Top-up load reaches the trip minimum');
$firstTopUp = assignTopUpLegs($topUpTrips, $topUpPool, [], '2026-09-07');
check($firstTopUp === $topUp, 'Top-up is deterministic');
$topUpNames = array_map(static fn(array $a): string => $a['name'], $topUp['assignments']);
check(count($topUpNames) === count(array_unique($topUpNames)), 'No duplicate rider on a top-up trip');

/* --------------------------- 1.4 pool sizing --------------------------- */

mt_srand(1);
$seedPlanA = weeklyPlanFor('Seed Case', 'student', 0.8, true);
$seedMatchA = assignPlannedLegs($dayPlans, $trips, $opposites);
mt_srand(999);
$seedPlanB = weeklyPlanFor('Seed Case', 'student', 0.8, true);
$seedMatchB = assignPlannedLegs($dayPlans, $trips, $opposites);
check($seedPlanA === $seedPlanB, 'Plans ignore mt_rand state');
check($seedMatchA === $seedMatchB, 'Matcher ignores mt_rand state');

$smallPool = planPoolTarget(25, 10);
$largePool = planPoolTarget(75, 10);
check($largePool > $smallPool, 'More daily trips means a larger pool');
check($smallPool >= PLAN_POOL_MIN, 'Pool respects the floor');
$clamped = planPoolTarget(100000, 10, ['max' => 2000]);
check($clamped === 2000, 'Pool respects the ceiling option');
$fleetFloor = planPoolTarget(1, 40);
check($fleetFloor >= 40 * PLAN_POOL_RIDERS_PER_BUS_MIN, 'Fleet size keeps a served floor');

/* ----------------------- 1.5 weekly violations ------------------------- */

$cleanLedger = [
    'plan' => [
        '2026-09-07' => [
            'Clean Rider' => [
                'planned' => ['out', 'ret'],
                'placed' => ['out' => 'A-B', 'ret' => 'B-A'],
                'topup' => false,
                'out_at' => 7 * 3600,
                'ret_at' => 16 * 3600,
            ],
        ],
    ],
    'plan_caps' => ['Clean Rider' => planWeeklyRideCap('student')],
    'rides' => ['2026-09-07' => ['Clean Rider' => 2]],
    'ride_dupes' => [],
];
check(weeklyPlanViolations($cleanLedger) === [], 'Clean ledger has no weekly violations');

$overCapLedger = $cleanLedger;
$overCapLedger['rides']['2026-09-07']['Clean Rider'] = planWeeklyRideCap('student') + 1;
$overCap = weeklyPlanViolations($overCapLedger);
check(count($overCap) === 1 && str_contains($overCap[0], 'cap'), 'Weekly cap violation is reported');

$orderingLedger = $cleanLedger;
$orderingLedger['plan']['2026-09-07']['Clean Rider']['ret_at'] = 6 * 3600;
$ordering = weeklyPlanViolations($orderingLedger);
check(count($ordering) === 1 && str_contains($ordering[0], 'precedes'), 'Return-before-outbound violation is reported');

$strandedLedger = $cleanLedger;
$strandedLedger['plan']['2026-09-07']['Clean Rider']['planned'] = ['out'];
$strandedLedger['plan']['2026-09-07']['Clean Rider']['placed'] = ['out' => null, 'ret' => 'B-A'];
$stranded = weeklyPlanViolations($strandedLedger);
check(count($stranded) === 1 && str_contains($stranded[0], 'without outbound'), 'Stranded return violation is reported');

$dupeLedger = $cleanLedger;
$dupeLedger['ride_dupes'] = ['2026-09-07|T1|Clean Rider'];
$dupes = weeklyPlanViolations($dupeLedger);
check(count($dupes) === 1 && str_contains($dupes[0], 'duplicate'), 'Duplicate passenger-on-trip violation is reported');

$extraLedger = $cleanLedger;
$extraLedger['plan']['2026-09-07']['Clean Rider']['planned'] = ['out'];
$extras = weeklyPlanViolations($extraLedger);
check(count($extras) === 1 && str_contains($extras[0], 'extra leg'), 'Extra leg without top-up is reported');

echo "$checks weekly travel-pattern checks passed.\n";

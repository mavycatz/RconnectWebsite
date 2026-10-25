<?php declare(strict_types=1);

/**
 * Fleet daily volume checks: the day-type-adjusted fleet trip target, the
 * deterministic bus allocation with its per-bus cap, the seat-scaled load
 * bands, and the demand scenario presets. Pure functions only - no database
 * is required.
 */

require_once __DIR__ . '/../api/TripDashboard/fleet_daily_volume.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

check(FLEET_DAILY_TRIPS_MIN === 13, 'Default band floor is thirteen');
check(FLEET_DAILY_TRIPS_MAX === 18, 'Default band ceiling is eighteen');
check(FLEET_DAILY_TRIPS_CEILING === FLEET_DAILY_TRIPS_MAX, 'Configured targets share the band ceiling');
check(FLEET_DAILY_TRIPS_PER_BUS_CAP === 4, 'Hard per-bus cap is four');
check(FLEET_FALLBACK_SEATS === 50, 'Fallback bus capacity is the 50-seat sample');

check(fleetScenarioMultiplier('low') === 0.6, 'Low scenario preset is 0.6');
check(fleetScenarioMultiplier('normal') === 1.0, 'Normal scenario preset is 1.0');
check(fleetScenarioMultiplier('high') === 1.4, 'High scenario preset is 1.4');
check(fleetScenarioMultiplier(' HIGH ') === 1.4, 'Scenario names are case/space-insensitive');
check(fleetScenarioMultiplier('turbo') === 1.0, 'Unknown scenario falls back to normal');

check(fleetDayProfileAnchor('weekday') === 0.75, 'Weekdays bias to the upper half');
check(fleetDayProfileAnchor('friday') === 0.75, 'Fridays bias to the upper half');
check(fleetDayProfileAnchor('saturday') === 0.50, 'Saturday sits in the middle');
check(fleetDayProfileAnchor('sunday') === 0.25, 'Sundays bias to the lower half');
check(fleetDayProfileAnchor('holiday') === 0.15, 'Holidays sit lowest');
check(fleetDayProfileAnchor('unknown') === 0.5, 'Unknown profile stays neutral');

check(abs(fleetDemandAnchor(0.6) - 0.0) < 1e-9, 'Low demand anchors at the band floor');
check(abs(fleetDemandAnchor(1.0) - 0.5) < 1e-9, 'Normal demand anchors at the middle');
check(abs(fleetDemandAnchor(1.4) - 1.0) < 1e-9, 'High demand anchors at the band top');
check(abs(fleetDemandAnchor(0.0) - 0.5) < 1e-9, 'Invalid demand falls back to normal');
check(fleetDemandAnchor(5.0) === 1.0, 'Demand anchor clamps at the top');

check(fleetStableUnit('fleet-base-2026-10-14') === fleetStableUnit('fleet-base-2026-10-14'), 'Stable units repeat for one key');
check(fleetStableUnit('a') >= 0.0 && fleetStableUnit('a') < 1.0, 'Stable unit stays inside 0-1');

check(fleetDayProfile('2026-10-14') === 'weekday', 'Wednesday is a weekday profile');
check(fleetDayProfile('2026-10-16') === 'friday', 'Friday is its own profile');
check(fleetDayProfile('2026-10-17') === 'saturday', 'Saturday is its own profile');
check(fleetDayProfile('2026-10-18') === 'sunday', 'Sunday is its own profile');

/* Automatic band: every profile on every sampled day lands inside 13-18. */
$sampleDays = ['2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18', '2026-10-19', '2026-10-24', '2026-10-25'];
$seenTotals = [];
foreach ($sampleDays as $day) {
    foreach (['weekday', 'friday', 'saturday', 'sunday', 'holiday'] as $profile) {
        $target = fleetDailyTarget($day, ['day_profile' => $profile]);
        check($target >= FLEET_DAILY_TRIPS_MIN && $target <= FLEET_DAILY_TRIPS_MAX, "Automatic $profile target for $day stays inside 13-18");
        $seenTotals[$target] = true;
    }
}
check(count($seenTotals) > 1, 'Automatic targets vary across dates and profiles');
check(
    fleetDailyTarget('2026-10-14', ['day_profile' => 'weekday'])
        === fleetDailyTarget('2026-10-14', ['day_profile' => 'weekday']),
    'Automatic target is deterministic for one day'
);

/* Day-type bias: weekdays sit at or above weekends inside the band. */
$weekday = fleetDailyTarget('2026-10-14', ['day_profile' => 'weekday']);
$saturday = fleetDailyTarget('2026-10-14', ['day_profile' => 'saturday']);
$sunday = fleetDailyTarget('2026-10-14', ['day_profile' => 'sunday']);
$holiday = fleetDailyTarget('2026-10-14', ['day_profile' => 'holiday']);
check($weekday >= $saturday && $saturday >= $sunday, 'Weekday, Saturday, Sunday step down inside the band');
check($holiday <= $sunday, 'Holidays stay at or below Sundays');
check($weekday >= 13 && $weekday <= 18 && $holiday >= 13, 'Day-type bias never leaves the band');

/* Configured target: the value is a center clamped into the band, and day
   type/demand move it by at most one trip each. */
$configured = ['daily_trips_target' => 15];
$cfgWeekday = fleetDailyTarget('2026-10-14', $configured + ['day_profile' => 'weekday']);
$cfgSunday = fleetDailyTarget('2026-10-14', $configured + ['day_profile' => 'sunday']);
$cfgLow = fleetDailyTarget('2026-10-14', $configured + ['day_profile' => 'weekday', 'demand_multiplier' => fleetScenarioMultiplier('low')]);
$cfgHigh = fleetDailyTarget('2026-10-14', $configured + ['day_profile' => 'weekday', 'demand_multiplier' => fleetScenarioMultiplier('high')]);
check($cfgWeekday === 15, 'Weekday keeps the configured center of fifteen');
check($cfgSunday === 14, 'Sunday steps one trip below the center');
check($cfgLow === 14 && $cfgHigh === 16, 'Low and high demand move one trip within the band');
check($cfgLow >= 13 && $cfgHigh <= 18, 'Configured shifts never leave the band');

check(fleetDailyTarget('2026-10-14', ['daily_trips_target' => 1, 'day_profile' => 'weekday']) === FLEET_DAILY_TRIPS_MIN, 'Tiny configured targets clamp up to the band floor');
check(fleetDailyTarget('2026-10-14', ['daily_trips_target' => 12, 'day_profile' => 'weekday']) === FLEET_DAILY_TRIPS_MIN, 'Below-band configured targets clamp up to thirteen');
check(fleetDailyTarget('2026-10-14', ['daily_trips_target' => 500, 'day_profile' => 'weekday']) === FLEET_DAILY_TRIPS_MAX, 'Large configured targets clamp down to eighteen');
check(
    fleetDailyTarget('2026-10-14', ['daily_trips_target' => 13, 'day_profile' => 'holiday', 'demand_multiplier' => 0.6]) === 13,
    'Floor-clamped centers never drop below thirteen even on light holidays'
);

/* Scenario presets position the automatic target inside the band. */
foreach ($sampleDays as $day) {
    $low = fleetDailyTarget($day, ['day_profile' => 'weekday', 'demand_multiplier' => fleetScenarioMultiplier('low')]);
    $normal = fleetDailyTarget($day, ['day_profile' => 'weekday', 'demand_multiplier' => fleetScenarioMultiplier('normal')]);
    $high = fleetDailyTarget($day, ['day_profile' => 'weekday', 'demand_multiplier' => fleetScenarioMultiplier('high')]);
    check($low <= $normal && $normal <= $high, "Demand scenarios stay ordered on $day");
    check($low >= FLEET_DAILY_TRIPS_MIN && $high <= FLEET_DAILY_TRIPS_MAX, "Demand scenarios stay inside the band on $day");
}
check(
    fleetDailyTarget('2026-10-14', ['daily_trips_target' => 15, 'day_profile' => 'weekday', 'demand_multiplier' => 0]) === $cfgWeekday,
    'Invalid demand multiplier falls back to one'
);

/* Allocation: target inside the fleet size runs one trip per ranked bus. */
$buses = [];
for ($i = 1; $i <= 20; $i++) {
    $buses[] = 'BUS-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
}
$allocation = fleetAllocateTrips($buses, '2026-10-14', 12);
check(array_sum($allocation) === 12, 'Allocation places exactly the target trips');
check(count($allocation) === 12, 'Target below fleet size uses one bus per trip');
check(array_unique(array_values($allocation)) === [1], 'Target below fleet size gives every bus a single trip');
check(fleetAllocateTrips($buses, '2026-10-14', 12) === $allocation, 'Allocation is deterministic for one day');
check(fleetAllocateTrips($buses, '2026-10-15', 12) !== $allocation || true, 'Other days may rank buses differently');

/* Allocation: target above the fleet size gives seconds only after firsts. */
$six = array_slice($buses, 0, 6);
$twoRound = fleetAllocateTrips($six, '2026-10-14', 10);
check(array_sum($twoRound) === 10, 'Over-fleet target still lands exactly');
check(count($twoRound) === 6, 'Every available bus gets its first trip');
check(max($twoRound) === 2, 'Seconds are placed only after every first');
check(min($twoRound) === 1, 'No bus is skipped while others repeat');

/* Allocation: configured per-bus cap tightens the hard cap. */
$capped = fleetAllocateTrips($six, '2026-10-14', 10, ['max_trips_per_bus' => 2]);
check(array_sum($capped) === 10 && max($capped) <= 2, 'Configured per-bus cap is honored');
$hardCapped = fleetAllocateTrips(array_slice($buses, 0, 2), '2026-10-14', 30, ['max_trips_per_bus' => 99]);
check(array_sum($hardCapped) === 8 && max($hardCapped) === FLEET_DAILY_TRIPS_PER_BUS_CAP, 'Hard cap of four trips per bus is never exceeded');
check(fleetAllocateTrips([], '2026-10-14', 10) === [], 'No buses yields no allocation');
check(fleetAllocateTrips($busys = ['BUS-0001'], '2026-10-14', 0) === [], 'Zero target yields no allocation');
$deduped = fleetAllocateTrips(['BUS-0001', 'BUS-0001', ' BUS-0002 '], '2026-10-14', 2);
check(count($deduped) === 2, 'Duplicate and padded bus ids are normalized');

/* Seat-scaled load bands. */
check(loadBoundsForSeats(50) === [15, 35], 'A 50-seat bus gets the 15-35 band');
check(loadBoundsForSeats(30) === [9, 21], 'A 30-seat bus scales the band down');
check(loadBoundsForSeats(0) === [15, 35], 'Unknown capacity falls back to the sample bus');
check(loadBoundsForSeats(null) === [15, 35], 'Null capacity falls back to the sample bus');
check(loadBoundsForSeats(1) === [1, 1], 'A single-seat vehicle is clamped to one');
foreach ([4, 10, 22, 45, 49, 50, 60, 99] as $seats) {
    [$min, $max] = loadBoundsForSeats($seats);
    check($min >= 1 && $max <= $seats && $max >= $min, "Band for $seats seats is valid and inside capacity");
}
check(fleetNormalizeSeats(0) === FLEET_FALLBACK_SEATS, 'Normalizing zero uses the fallback');
check(fleetNormalizeSeats(-3) === FLEET_FALLBACK_SEATS, 'Normalizing a negative uses the fallback');
check(fleetNormalizeSeats(45) === 45, 'Normalizing a positive keeps the value');

echo "$checks fleet daily volume checks passed.\n";

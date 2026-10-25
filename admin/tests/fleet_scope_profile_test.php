<?php declare(strict_types=1);

/**
 * Fleet-scope profile checks: the September-October 2026 window boundaries,
 * case-insensitive bus allow-list matching, and the reduced volume bounds.
 * Pure functions only - no database is required.
 */

require_once __DIR__ . '/../api/TripDashboard/fleet_scope_profile.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

check(FLEET_SCOPE_WINDOW_START === '2026-09-01', 'Window starts 2026-09-01');
check(FLEET_SCOPE_WINDOW_END === '2026-10-31', 'Window ends 2026-10-31');
check(FLEET_SCOPE_TRIPS_PER_BUS === 2, 'Window trip count is two per bus');
check(FLEET_SCOPE_MAX_BUSES_PER_DAY === 9, 'Window days run at most nine buses');
check(FLEET_SCOPE_MIN_BUSES_PER_DAY === 7, 'Window days run at least seven buses');
check(FLEET_SCOPE_PAX_MIN === 5, 'Window passenger floor is five');
check(FLEET_SCOPE_PAX_MAX === 15, 'Window passenger ceiling is fifteen');
check(FLEET_SCOPE_SPACE_MAX === 15, 'Window occupied-space cap is fifteen');

check(fleetScopeDayActive('2026-08-31') === false, 'Day before the window is inactive');
check(fleetScopeDayActive('2026-09-01') === true, 'First window day is active');
check(fleetScopeDayActive('2026-10-31') === true, 'Last window day is active');
check(fleetScopeDayActive('2026-11-01') === false, 'Day after the window is inactive');
check(fleetScopeDayActive('') === false, 'Empty day is inactive');

check(fleetScopeAllowsBus('Bus-2001') === true, 'Title-case bus id is allowed');
check(fleetScopeAllowsBus('bus-2001') === true, 'Lower-case bus id is allowed');
check(fleetScopeAllowsBus('BUS-2001') === true, 'Upper-case bus id is allowed');
check(fleetScopeAllowsBus(' BUS-2004 ') === true, 'Padded bus id is allowed');
check(fleetScopeAllowsBus('BUS-2105') === true, 'BUS-2105 is allowed');
check(fleetScopeAllowsBus('BUS-2106') === true, 'BUS-2106 is allowed');
check(fleetScopeAllowsBus('BUS-2002') === false, 'Unlisted bus is rejected');
check(fleetScopeAllowsBus('') === false, 'Empty bus id is rejected');

check(fleetScopeBoundsForDay('2026-09-01') === [5, 15, 15], 'Window bounds are [5, 15, 15]');
check(fleetScopeBoundsForDay('2026-10-31') === [5, 15, 15], 'Window bounds on the last day are [5, 15, 15]');
check(fleetScopeBoundsForDay('2026-08-31') === null, 'Day before the window has no bounds');
check(fleetScopeBoundsForDay('2026-11-01') === null, 'Day after the window has no bounds');

/* --- Per-day window fleet: bounded for the 13-18 band, varying by day ----- */

$windowEight = ['BUS-2001', 'BUS-2004', 'BUS-2017', 'BUS-2105', 'BUS-2106', 'BUS-2208', 'BUS-2209', 'BUS-2210'];
$windowEightSelection = fleetScopeDayBuses($windowEight, '2026-09-01');
check(count($windowEightSelection) >= FLEET_SCOPE_MIN_BUSES_PER_DAY, 'A full allow-list runs at least the daily floor');
check(count($windowEightSelection) <= count($windowEight), 'A full allow-list never exceeds its own size');
check(fleetScopeDayBuses([], '2026-09-01') === [], 'No allowed buses selects nothing');

$windowPool = [];
for ($i = 1; $i <= 20; $i++) {
    $windowPool[] = 'BUS-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
}
$windowDaySelection = fleetScopeDayBuses($windowPool, '2026-09-01');
check(count($windowDaySelection) >= FLEET_SCOPE_MIN_BUSES_PER_DAY, 'A larger allow-list runs at least the daily floor');
check(count($windowDaySelection) <= FLEET_SCOPE_MAX_BUSES_PER_DAY, 'A larger allow-list is bounded by the daily cap');
check(count(array_unique($windowDaySelection)) === count($windowDaySelection), 'Selected window buses are unique');
check(fleetScopeDayBuses($windowPool, '2026-09-01') === $windowDaySelection, 'The day selection is deterministic');
$windowUnion = [];
$windowDayCounts = [];
foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05'] as $windowDay) {
    $daySelection = fleetScopeDayBuses($windowPool, $windowDay);
    $windowDayCounts[] = count($daySelection);
    $windowUnion = array_merge($windowUnion, $daySelection);
}
check(count(array_unique($windowUnion)) > FLEET_SCOPE_MAX_BUSES_PER_DAY, 'The selected window subsets rotate across days');
check(count(array_unique($windowDayCounts)) > 1, 'The operating bus count varies across window days');
check(count(fleetScopeDayBuses($windowPool, '2026-09-01', 3)) === 3, 'An explicit per-day cap is honored');
foreach ($windowDaySelection as $selectedBus) {
    check(in_array($selectedBus, $windowPool, true), 'Selected window buses come from the allow-list');
}

/* --- Per-day bus budget: profile-biased and deterministic ----------------- */

check(fleetScopeDayBusBudget([], '2026-09-01') === 0, 'An empty allow-list has a zero budget');
check(fleetScopeDayBusBudget($windowPool, '2026-09-01') === fleetScopeDayBusBudget($windowPool, '2026-09-01'), 'The daily budget is deterministic');
check(
    fleetScopeDayBusBudget($windowPool, '2026-09-01', 'holiday') <= fleetScopeDayBusBudget($windowPool, '2026-09-01', 'weekday'),
    'A holiday never runs more buses than a weekday'
);
check(
    fleetScopeDayBusBudget($windowPool, '2026-09-01', 'sunday') <= fleetScopeDayBusBudget($windowPool, '2026-09-01', 'weekday'),
    'A Sunday never runs more buses than a weekday'
);
$windowBudgets = [];
foreach (range(1, 14) as $offset) {
    $windowBudgets[] = fleetScopeDayBusBudget($windowPool, sprintf('2026-09-%02d', $offset));
}
check(count(array_unique($windowBudgets)) > 1, 'The daily bus budget varies inside the window');

/* --- Whole-fleet default used by the generator when nothing is pinned ------ */

fleetScopeSetBusList(['BUS-9001', 'BUS-9002']);
check(fleetScopeAllowsBus('BUS-9001') === true, 'A pinned fleet bus is allowed');
check(fleetScopeAllowsBus('BUS-9002') === true, 'Every pinned fleet bus is allowed');
check(fleetScopeAllowsBus('BUS-2001') === false, 'The built-in list no longer applies while pinned');

fleetScopeSetBusList('');
check(fleetScopeAllowsBus('BUS-2001') === true, 'Clearing the pin restores the built-in list');

$generator = (string) file_get_contents(__DIR__ . '/../../trip-history-generator.php');
check(str_contains($generator, 'function applyDefaultFleetScope('), 'The generator defines the default fleet scope');
check(str_contains($generator, 'SELECT bus_id FROM buses'), 'The default scope reads the whole fleet');
check(str_contains($generator, 'applyDefaultFleetScope($db, $input)'), 'The live generation path applies the default scope');
check(str_contains($generator, 'blank = all buses in the fleet'), 'The generator page documents the whole-fleet default');
check(str_contains($generator, "\$volumeMeta['never_operated']"), 'The run collects the never-operated fleet buses with their status');
check(str_contains($generator, "'never_operated' => array_values"), 'The run summary exposes the never-operated buses');

echo "$checks fleet-scope profile checks passed.\n";

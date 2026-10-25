<?php declare(strict_types=1);

/**
 * Dispatch coverage checks: eligibility filters for the real fleet and driver
 * roster, the capped daily target, the rotation-based lineup/assignment
 * selection that always keeps the day's trip participants, window scoping, gap
 * labels, and the coverage row builders. Pure functions only - no database is
 * required.
 */

require_once __DIR__ . '/../api/TripDashboard/dispatch_coverage.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

check(DISPATCH_COVERAGE_BUS_TARGET === 19, 'Bus coverage target is nineteen');
check(DISPATCH_COVERAGE_DRIVER_TARGET === 19, 'Driver coverage target is nineteen');

check(dispatchCoverageBusIdValid('BUS-2001'), 'Official BUS- ids are valid');
check(dispatchCoverageBusIdValid('Bus-1001'), 'Legacy Bus- ids are valid case-insensitively');
check(dispatchCoverageBusIdValid(' BUS-2004 '), 'Padded ids are trimmed');
check(!dispatchCoverageBusIdValid('XYZ-1'), 'Non-BUS identifiers are invalid');
check(!dispatchCoverageBusIdValid(''), 'Empty identifiers are invalid');

/* Bus eligibility: lifecycle and operational status gates. */
$busRows = [
    ['bus_id' => 'BUS-0001', 'status' => 'Active'],
    ['bus_id' => 'BUS-0002', 'status' => 'Inactive'],
    ['bus_id' => 'BUS-0003', 'status' => 'Maintenance'],
    ['bus_id' => 'BUS-0004', 'status' => 'Archived'],
    ['bus_id' => 'BUS-0005', 'status' => 'Retired'],
    ['bus_id' => 'BUS-0006', 'status' => 'Out of Service'],
    ['bus_id' => 'BUS-0007', 'status' => 'Active', 'operational_status' => 'Under Maintenance'],
    ['bus_id' => 'BUS-0008', 'status' => 'Active', 'operational_status' => 'Out of Service'],
    ['bus_id' => 'BUS-0009', 'status' => 'Active', 'operational_status' => 'Available'],
    ['bus_id' => 'BUS-0010'],
    ['bus_id' => 'XYZ-1', 'status' => 'Active'],
    ['bus_id' => 'BUS-0001', 'status' => 'Active'],
];
$eligibleBuses = dispatchCoverageEligibleBuses($busRows);
check($eligibleBuses === ['BUS-0001', 'BUS-0009', 'BUS-0010'], 'Only operable, valid, unique BUS- ids stay eligible');
check(dispatchCoverageEligibleBuses([]) === [], 'An empty fleet yields no eligible buses');
check(dispatchCoverageBusEligible(['bus_id' => 'XYZ-1', 'status' => 'Active']), 'Trip eligibility does not require the BUS- prefix');
check(!dispatchCoverageBusEligible(['bus_id' => '', 'status' => 'Active']), 'Empty bus ids are never eligible');
check(!dispatchCoverageBusEligible(['bus_id' => 'BUS-0002', 'status' => 'Inactive']), 'Inactive buses are never eligible');
check(!dispatchCoverageBusEligible(['bus_id' => 'BUS-0007', 'status' => 'Active', 'operational_status' => 'Under Maintenance']), 'Operationally blocked buses are never eligible');
check(dispatchCoverageBusEligible(['bus_id' => 'BUS-0010']), 'Missing status columns stay eligible');

/* Dispatch schema usability: required columns for historical coverage. */
$fullQueue = DISPATCH_COVERAGE_QUEUE_COLUMNS;
$fullAssign = DISPATCH_COVERAGE_ASSIGNMENT_COLUMNS;
$usableSchema = dispatchCoverageSchemaUsable($fullQueue, $fullAssign);
check($usableSchema['queue'] === true && $usableSchema['terminals'] === true, 'Complete dispatch tables are usable');
check($usableSchema['missing']['queue'] === [] && $usableSchema['missing']['terminals'] === [], 'Complete dispatch tables report no missing columns');
$missingQueue = dispatchCoverageSchemaUsable(array_slice($fullQueue, 0, 5), $fullAssign);
check($missingQueue['queue'] === false, 'A queue table without terminal_id is unusable');
check($missingQueue['missing']['queue'] === ['terminal_id'], 'Missing queue columns are named');
check($missingQueue['terminals'] === true, 'A usable assignment table stays usable');
$absent = dispatchCoverageSchemaUsable(null, null);
check($absent['queue'] === false && $absent['terminals'] === false, 'Absent dispatch tables are unusable');
check(count($absent['missing']['queue']) === count($fullQueue) && count($absent['missing']['terminals']) === count($fullAssign), 'Absent tables report every required column');
$mixedCase = dispatchCoverageSchemaUsable(['Operating_Date', 'BUS_ID', 'Queue_Position', 'Published_At', 'Published_By', 'Terminal_Id'], $fullAssign);
check($mixedCase['queue'] === true, 'Required columns are matched case-insensitively');

/* Driver eligibility: status plus optional availability. */
$driverRows = [
    ['driver_id' => 'DRV-001', 'status' => 'Active', 'availability' => 'Available'],
    ['driver_id' => 'DRV-002', 'status' => 'Inactive'],
    ['driver_id' => 'DRV-003', 'status' => 'Active', 'availability' => 'On Leave'],
    ['driver_id' => 'DRV-004', 'status' => 'Archived', 'availability' => 'Available'],
    ['driver_id' => 'DRV-005'],
    ['driver_id' => 'DRV-006', 'status' => 'active', 'availability' => 'unavailable'],
    ['driver_id' => 'DRV-001', 'status' => 'Active', 'availability' => 'On Duty'],
];
$eligibleDrivers = dispatchCoverageEligibleDrivers($driverRows);
check($eligibleDrivers === ['DRV-001', 'DRV-005'], 'Only active, available, unique drivers stay eligible');
check(dispatchCoverageEligibleDrivers([]) === [], 'An empty roster yields no eligible drivers');

/* Target: capped at the configured target and never above the eligible pool. */
check(dispatchCoverageTarget(25) === 19, 'Large pools cap at the nineteen target');
check(dispatchCoverageTarget(12) === 12, 'Small pools use every eligible record');
check(dispatchCoverageTarget(0) === 0, 'Empty pools yield a zero target');
check(dispatchCoverageTarget(30, 19) === 19, 'The explicit target caps the pool');
check(dispatchCoverageTarget(-4) === 0, 'Negative pools yield a zero target');

/* Selection: full pool cap, small shortfalls, and required participants. */
$pool = [];
for ($i = 1; $i <= 30; $i++) {
    $pool[] = 'BUS-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
}
$dayA = '2026-10-14';
$selectionA = dispatchCoverageSelection($pool, [], $dayA, dispatchCoverageTarget(count($pool)));
check(count($selectionA) === 19, 'A large pool selects exactly nineteen buses');
check(count(array_unique($selectionA)) === 19, 'Selected buses are unique');
check(dispatchCoverageSelection($pool, [], $dayA, dispatchCoverageTarget(count($pool))) === $selectionA, 'Selection is deterministic for one date');

$smallPool = array_slice($pool, 0, 5);
$smallSelection = dispatchCoverageSelection($smallPool, [], $dayA, dispatchCoverageTarget(count($smallPool)));
check(count($smallSelection) === 5, 'A small fleet selects every eligible bus');
check(count(dispatchCoverageSelection($smallPool, ['BUS-0003'], $dayA, 3)) === 3, 'Small-pool selection caps at the eligible count, not the target');

$required = ['BUS-0029', 'BUS-0030', 'BUS-0001'];
$withRequired = dispatchCoverageSelection($pool, $required, $dayA, 19);
foreach ($required as $requiredId) {
    check(in_array($requiredId, $withRequired, true), "Trip bus $requiredId is always included");
}
check(count($withRequired) === 19, 'Required participants do not grow the selection past the target');
$ignored = dispatchCoverageSelection($pool, ['BUS-9999'], $dayA, 19);
check(!in_array('BUS-9999', $ignored, true), 'Required ids outside the eligible pool are ignored');
check(dispatchCoverageSelection($pool, [], $dayA, 0) === [], 'A zero target selects nothing');
check(dispatchCoverageSelection([], [], $dayA, 19) === [], 'No eligible records selects nothing');

/* Rotation: different dates pick different subsets, deterministically. */
$union = [];
$days = ['2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17'];
foreach ($days as $day) {
    $selection = dispatchCoverageSelection($pool, [], $day, 19);
    check(count($selection) === 19, "Selection for $day stays capped at nineteen");
    $union = array_merge($union, $selection);
}
check(count(array_unique($union)) > 19, 'The selected subsets rotate across dates');
$changed = false;
for ($i = 1; $i < count($days); $i++) {
    $a = dispatchCoverageSelection($pool, [], $days[$i - 1], 19);
    $b = dispatchCoverageSelection($pool, [], $days[$i], 19);
    if ($a !== $b) {
        $changed = true;
        break;
    }
}
check($changed, 'Consecutive dates are not forced onto the same subset');

/* Window scoping: only the Sep-Oct 2026 allow-list survives on window days. */
$windowRows = [
    ['bus_id' => 'BUS-2001', 'status' => 'Active'],
    ['bus_id' => 'BUS-2004', 'status' => 'Active'],
    ['bus_id' => 'BUS-2001', 'status' => 'Maintenance'],
    ['bus_id' => 'BUS-1001', 'status' => 'Active'],
    ['bus_id' => 'BUS-9999', 'status' => 'Active'],
];
check(
    dispatchCoverageEligibleBuses($windowRows, true) === ['BUS-2001', 'BUS-2004'],
    'Window days keep only the allow-listed eligible buses'
);
check(
    dispatchCoverageEligibleBuses($windowRows, false) === ['BUS-1001', 'BUS-2001', 'BUS-2004', 'BUS-9999'],
    'Non-window days use the whole eligible fleet'
);

/* Gap labels name the limiting reason. */
check(dispatchCoverageGapLabel(19, 19, 'fleet') === 'achieved', 'Meeting the target reads achieved');
check(dispatchCoverageGapLabel(25, 19, 'fleet') === 'achieved', 'Exceeding the target reads achieved');
check(dispatchCoverageGapLabel(0, 0, 'fleet') === 'achieved', 'A zero target reads achieved');
check(dispatchCoverageGapLabel(5, 19, 'fleet') === 'limited:fleet (5 of 19)', 'Fleet shortfalls name the fleet');
check(dispatchCoverageGapLabel(8, 19, 'window') === 'limited:window (8 of 19)', 'Window days name the window profile');
check(dispatchCoverageGapLabel(10, 19, 'roster') === 'limited:roster (10 of 19)', 'Roster shortfalls name the roster');
check(dispatchCoverageGapLabel(10, 19, 'terminals') === 'limited:terminals (10 of 19)', 'Missing terminals name the terminals');
check(dispatchCoverageBusReasonKey(true) === 'window', 'Window days use the window reason key');
check(dispatchCoverageBusReasonKey(false) === 'fleet', 'Ordinary days use the fleet reason key');
check(dispatchCoverageDriverReasonKey(true) === 'roster', 'Configured terminals use the roster reason key');
check(dispatchCoverageDriverReasonKey(false) === 'terminals', 'Missing terminals use the terminals reason key');

/* Written-pair parsing: only the lineage lists recorded in the audit payload
   are returned, so cleanup never guesses. */
$written = dispatchCoverageWrittenPairs([
    'operating_date' => '2026-10-14',
    'written_buses' => ['BUS-2001', ' BUS-2004 ', '', 'BUS-2001'],
    'written_drivers' => ['DRV-001', 'DRV-002'],
]);
check(array_keys($written['buses']) === ['2026-10-14'], 'Written bus pairs carry the operating date');
check(array_keys($written['buses']['2026-10-14']) === ['BUS-2001', 'BUS-2004'], 'Written bus ids are trimmed and unique');
check(array_keys($written['drivers']['2026-10-14']) === ['DRV-001', 'DRV-002'], 'Written driver ids are listed');
$legacyPayload = dispatchCoverageWrittenPairs(['operating_date' => '2026-10-14', 'count' => 16]);
check($legacyPayload['buses'] === [] && $legacyPayload['drivers'] === [], 'Legacy payloads without lists yield no pairs');
$noDayPayload = dispatchCoverageWrittenPairs(['written_buses' => ['BUS-2001']]);
check($noDayPayload['buses'] === [], 'Payloads without an operating date yield no pairs');

/* Publication timestamp: 04:45 plus a stable jitter inside the hour. */
$publishedAt = dispatchCoveragePublishedAt('2026-10-14');
check(preg_match('/^\d{4}-\d{2}-\d{2} 04:\d{2}:\d{2}$/', $publishedAt) === 1, 'Publication stays in the 04:xx morning window');
check(strtotime('2026-10-14 04:45:00') <= strtotime($publishedAt), 'Publication is at or after 04:45');
check(strtotime($publishedAt) <= strtotime('2026-10-14 05:00:00'), 'Publication is before 05:00');
check(dispatchCoveragePublishedAt('2026-10-14') === $publishedAt, 'Publication timestamp is deterministic for one day');

/* Queue row builder: shape, position, and publication metadata. */
$queueRow = dispatchCoverageQueueRow('2026-10-14', 'BUS-2001', 3, 'dispatcher_a', 7);
check($queueRow['operating_date'] === '2026-10-14', 'Queue rows carry the operating date');
check($queueRow['bus_id'] === 'BUS-2001', 'Queue rows carry the bus id');
check($queueRow['queue_position'] === 3, 'Queue rows carry the queue position');
check($queueRow['published_at'] === $publishedAt, 'Queue rows share the day publication timestamp');
check($queueRow['published_by'] === 'dispatcher_a', 'Queue rows carry the publishing account');
check($queueRow['terminal_id'] === 7, 'Queue rows carry a resolvable terminal');
$queueRowNoTerminal = dispatchCoverageQueueRow('2026-10-14', 'BUS-2001', 1, null, null);
check($queueRowNoTerminal['terminal_id'] === null, 'Queue rows allow a null terminal');

/* Bus terminal resolution from fleet columns. */
$terminalIds = [5, 6];
check(dispatchCoverageBusTerminalId(['from_terminal_id' => 5], $terminalIds) === 5, 'From-terminal resolves when known');
check(dispatchCoverageBusTerminalId(['from_terminal_id' => 9, 'to_terminal_id' => 6], $terminalIds) === 6, 'To-terminal resolves when from-terminal is unknown');
check(dispatchCoverageBusTerminalId(['from_terminal_id' => 9], $terminalIds) === null, 'Unknown terminals resolve to null');
check(dispatchCoverageBusTerminalId([], $terminalIds) === null, 'Missing terminal columns resolve to null');

/* Driver terminal assignment: deterministic pick from the configured list. */
$driverTerminal = dispatchCoverageDriverTerminalId($terminalIds, '2026-10-14', 'DRV-001');
check(in_array($driverTerminal, $terminalIds, true), 'Driver terminals come from the configured list');
check(dispatchCoverageDriverTerminalId($terminalIds, '2026-10-14', 'DRV-001') === $driverTerminal, 'Driver terminal picks are deterministic');
check(dispatchCoverageDriverTerminalId([], '2026-10-14', 'DRV-001') === null, 'Drivers resolve to null without terminals');
check(dispatchCoverageDriverTerminalId([7], '2026-10-14', 'DRV-001') === 7, 'A single terminal is always chosen');
$assignRow = dispatchCoverageTerminalAssignmentRow('2026-10-14', 'DRV-001', 7);
check($assignRow['operating_date'] === '2026-10-14' && $assignRow['driver_id'] === 'DRV-001' && $assignRow['terminal_id'] === 7, 'Assignment rows carry date, driver, and terminal');
check($assignRow['created_at'] === $publishedAt, 'Assignment rows share the day publication timestamp');

/* Per-date validation helpers: trip band, participants, positions,
   chronology, and coverage references. */
check(dispatchCoverageTripCountErrors(true, 13) === [], 'Past days at the band floor pass');
check(dispatchCoverageTripCountErrors(true, 18) === [], 'Past days at the band ceiling pass');
check(count(dispatchCoverageTripCountErrors(true, 12)) === 1, 'Past days below the band fail');
check(count(dispatchCoverageTripCountErrors(true, 19)) === 1, 'Past days above the band fail');
check(strpos(dispatchCoverageTripCountErrors(true, 12)[0], '13-18') !== false, 'Band failures name the 13-18 range');
check(dispatchCoverageTripCountErrors(false, 5) === [], 'The partial current day is exempt from the floor');
check(count(dispatchCoverageTripCountErrors(false, 19)) === 1, 'The current day still respects the ceiling');
check(count(dispatchCoverageTripCountErrors(true, -1)) === 1, 'Negative trip counts fail');

check(dispatchCoverageParticipantErrors(['BUS-1', 'BUS-2'], ['DRV-1'], ['BUS-1', 'BUS-2'], ['DRV-1']) === [], 'Covered participants pass');
$participantErrors = dispatchCoverageParticipantErrors(['BUS-1', 'BUS-3'], ['DRV-1', 'DRV-9'], ['BUS-1'], ['DRV-1']);
check(count($participantErrors) === 2, 'Missing bus and driver participants are both reported');
check(strpos($participantErrors[0], 'BUS-3') !== false && strpos($participantErrors[1], 'DRV-9') !== false, 'Participant errors name the missing records');

check(dispatchCoverageQueuePositionErrors([1, 2, 3]) === [], 'Unique sequential positions pass');
check(dispatchCoverageQueuePositionErrors([]) === [], 'An empty queue has no position errors');
check(count(dispatchCoverageQueuePositionErrors([1, 2, 2])) === 1, 'Duplicate positions fail');
check(count(dispatchCoverageQueuePositionErrors([1, 3])) === 1, 'Gapped positions fail');

check(dispatchCoverageChronologyErrors('2026-10-14 04:52:00', '2026-10-14 05:10:00') === [], 'Publication before the first departure passes');
check(count(dispatchCoverageChronologyErrors('2026-10-14 05:30:00', '2026-10-14 05:10:00')) === 1, 'Publication after the first departure fails');
check(count(dispatchCoverageChronologyErrors(null, '2026-10-14 05:10:00')) === 1, 'Missing publication timestamps fail');
check(count(dispatchCoverageChronologyErrors('2026-10-14 04:50:00', '')) === 1, 'Missing first departures fail');

check(dispatchCoverageReferenceErrors(['BUS-1'], ['BUS-1'], ['DRV-1' => 7], ['DRV-1'], [7]) === [], 'Known references pass');
$referenceErrors = dispatchCoverageReferenceErrors(['BUS-9'], ['BUS-1'], ['DRV-9' => 5, 'DRV-1' => 8], ['DRV-1'], [7]);
check(count($referenceErrors) === 3, 'Unknown bus, driver, and terminal references are all reported');
check(strpos($referenceErrors[0], 'BUS-9') !== false, 'Unknown lineup buses are named');
check(strpos($referenceErrors[1], 'DRV-9') !== false, 'Unknown assigned drivers are named');
check(strpos($referenceErrors[2], '8') !== false, 'Unknown terminals are named');
check(dispatchCoverageReferenceErrors([], [], ['DRV-1' => null], ['DRV-1'], []) === [], 'Null terminal assignments skip the terminal check');

/* Coverage summary: per-date entries and aggregates keep trips, drivers, and
   buses separate, and limitations are named. */
$coverageDays = [
    '2026-10-13' => [
        'window_day' => false,
        'trips' => 15,
        'trip_buses' => ['BUS-1', 'BUS-2'],
        'trip_drivers' => ['DRV-1', 'DRV-2'],
        'lineup_buses' => ['BUS-1', 'BUS-2', 'BUS-3', 'BUS-4'],
        'assigned_drivers' => ['DRV-1', 'DRV-2', 'DRV-3', 'DRV-4'],
        'queue_source' => 'generated',
        'assign_source' => 'generated',
    ],
    '2026-10-14' => [
        'window_day' => true,
        'trips' => 16,
        'trip_buses' => ['BUS-1'],
        'trip_drivers' => ['DRV-5'],
        'lineup_buses' => ['BUS-1'],
        'assigned_drivers' => ['DRV-5'],
        'queue_source' => 'generated',
        'assign_source' => 'generated',
    ],
];
$rideTotals = [
    '2026-10-13' => ['passengers' => 30, 'fares' => 512.5],
    '2026-10-14' => ['passengers' => 12, 'fares' => 220.0],
];
$coverageSummary = dispatchCoverageSummary($coverageDays, $rideTotals, 19, 19, true);
check(count($coverageSummary['daily']) === 2, 'Every generated date gets a coverage entry');
check($coverageSummary['daily'][0]['date'] === '2026-10-13' && $coverageSummary['daily'][1]['date'] === '2026-10-14', 'Daily coverage is date-ordered');
$firstDay = $coverageSummary['daily'][0];
check($firstDay['trips'] === 15, 'Trip totals stay their own figure');
check($firstDay['drivers_on_trips'] === 2 && $firstDay['buses_on_trips'] === 2, 'Trip participants are counted from the trips');
check($firstDay['lineup_buses'] === 4 && $firstDay['terminal_drivers'] === 4, 'Lineup and assignment coverage are reported separately');
check($firstDay['passengers'] === 30 && abs($firstDay['fares'] - 512.5) < 0.001, 'Ride totals merge into the date');
check($firstDay['bus_coverage'] === 'limited:fleet (4 of 19)', 'Ordinary-day bus shortfalls name the fleet');
check($firstDay['driver_coverage'] === 'limited:roster (4 of 19)', 'Driver shortfalls name the roster');
$secondDay = $coverageSummary['daily'][1];
check($secondDay['bus_coverage'] === 'limited:window (1 of 19)', 'Window days name the window profile');

$coverageTotals = $coverageSummary['totals'];
check($coverageTotals['dates'] === 2, 'The aggregate counts the dates');
check($coverageTotals['trips'] === 31, 'The aggregate sums the trips');
check($coverageTotals['drivers_on_trips'] === 3, 'Aggregate trip drivers are distinct across dates');
check($coverageTotals['buses_on_trips'] === 2, 'Aggregate trip buses are distinct across dates');
check($coverageTotals['lineup_buses'] === 4, 'Aggregate lineup buses are distinct across dates');
check($coverageTotals['terminal_drivers'] === 5, 'Aggregate terminal drivers are distinct across dates');
check($coverageTotals['passengers'] === 42 && abs($coverageTotals['fares'] - 732.5) < 0.001, 'The aggregate sums passengers and fares');
check($coverageTotals['bus_targets_achieved'] === 0 && $coverageTotals['driver_targets_achieved'] === 0, 'Unmet targets are not reported as achieved');
check(count($coverageTotals['limitations']) === 4, 'Every limitation label is aggregated by count');

$achievedDays = [
    '2026-10-15' => [
        'window_day' => false,
        'trips' => 18,
        'trip_buses' => ['BUS-1'],
        'trip_drivers' => ['DRV-1'],
        'lineup_buses' => array_map(static fn(int $i): string => 'BUS-' . $i, range(1, 19)),
        'assigned_drivers' => array_map(static fn(int $i): string => 'DRV-' . $i, range(1, 19)),
        'queue_source' => 'generated',
        'assign_source' => 'generated',
    ],
];
$achievedSummary = dispatchCoverageSummary($achievedDays, [], 19, 19, true);
check($achievedSummary['daily'][0]['bus_coverage'] === 'achieved' && $achievedSummary['daily'][0]['driver_coverage'] === 'achieved', 'Met targets report achieved');
check($achievedSummary['totals']['bus_targets_achieved'] === 1 && $achievedSummary['totals']['driver_targets_achieved'] === 1, 'Met targets are counted in the aggregate');
check($achievedSummary['totals']['limitations'] === [], 'No limitations are listed when targets are met');

$noTerminals = dispatchCoverageSummary($coverageDays, [], 19, 19, false);
check(strpos($noTerminals['daily'][0]['driver_coverage'], 'limited:terminals') === 0, 'Missing terminals are named');
check(dispatchCoverageSummary([], [], 19, 19, false)['daily'] === [], 'No coverage days yields an empty report');

/* Driver handover readiness: latest departure + duration + turnaround per
   driver, so a replacement never takes over while a candidate is still
   turning around. */
$readyTimes = dispatchCoverageDriverReadyTimes([
    ['sec' => 18000, 'durationMin' => 50, 'turnSec' => 600, 'drv' => ['driver_id' => 'DRV-1']],
    ['sec' => 18000, 'durationMin' => 60, 'turnSec' => 900, 'drv' => ['driver_id' => 'DRV-1']],
    ['sec' => 19000, 'durationMin' => 40, 'turnSec' => 0, 'drv' => ['driver_id' => 'DRV-2']],
    ['sec' => 20000, 'durationMin' => 30, 'turnSec' => 300, 'drv' => []],
    ['durationMin' => 30, 'drv' => ['driver_id' => 'DRV-3']],
]);
check(($readyTimes['DRV-1'] ?? 0) === 22500, 'Ready time is the latest arrival plus turnaround');
check(($readyTimes['DRV-2'] ?? 0) === 21400, 'Ready time covers a single trip');
check(!isset($readyTimes['DRV-3']), 'Trips without a departure time are skipped');
check(count($readyTimes) === 2, 'Only drivers with valid trips appear');
check(dispatchCoverageDriverReadyTimes([]) === [], 'No trips yields no ready times');
check($readyTimes['DRV-1'] >= 22200, 'A handover right after arrival is rejected by the ready time');

echo "$checks dispatch coverage checks passed.\n";

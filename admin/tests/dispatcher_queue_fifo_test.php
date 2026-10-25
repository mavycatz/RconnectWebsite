<?php declare(strict_types=1);

/**
 * Dispatcher per-terminal queue checks: with an Admin publication the whole
 * terminal queue - waiting and returning buses alike - follows the published
 * positions; without one, waiting buses order by queue-entry time and
 * returning buses join the back by arrival time. Terminal membership follows
 * the bus's current trip.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../../dispatcher/api/helpers.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function fifo_ids(array $ordered): array
{
    return array_map(static fn (array $candidate): string => (string)$candidate['bus_id'], $ordered);
}

function fifo_candidate(string $busId, bool $returning, ?int $entryTime): array
{
    return [
        'bus_id' => $busId,
        'returning' => $returning,
        'entry_time' => $entryTime,
        'bus' => ['bus_id' => $busId],
    ];
}

/* ----------------------------- terminal membership ----------------------------- */

$bus = ['bus_id' => 'BUS-1', 'from_terminal_id' => 1, 'to_terminal_id' => 2];
$tripData = ['by_bus' => [], 'last_completed_by_bus' => []];
check(dispatcher_queue_bus_terminal_id($bus, 'BUS-1', $tripData) === 1, 'Without trips a bus belongs to its home terminal');

$tripData['by_bus']['BUS-1'] = ['from_terminal_id' => 2, 'to_terminal_id' => 1];
check(dispatcher_queue_bus_terminal_id($bus, 'BUS-1', $tripData) === 2, 'An active trip keeps the bus at its departure terminal');

$tripData['by_bus'] = [];
$tripData['last_completed_by_bus']['BUS-1'] = ['from_terminal_id' => 1, 'to_terminal_id' => 2];
check(dispatcher_queue_bus_terminal_id($bus, 'BUS-1', $tripData) === 2, 'A completed trip moves the bus to its arrival terminal');

$tripData['last_completed_by_bus']['BUS-1'] = ['from_terminal_id' => 1, 'to_terminal_id' => null];
check(dispatcher_queue_bus_terminal_id($bus, 'BUS-1', $tripData) === 1, 'A missing arrival terminal falls back to the bus origin, not its configured destination');

/* ---------------------------- queue-entry timestamps ---------------------------- */

$tripData = ['by_bus' => [], 'last_completed_by_bus' => ['BUS-1' => ['arrival_time' => '2026-10-14 09:30:00', 'departure_time' => '2026-10-14 07:00:00']]];
check(dispatcher_queue_entry_timestamp('BUS-1', $tripData, [], []) === strtotime('2026-10-14 09:30:00'), 'A returning bus is keyed by its arrival time');

$tripData = ['by_bus' => [], 'last_completed_by_bus' => []];
check(dispatcher_queue_entry_timestamp('BUS-1', $tripData, ['BUS-1' => ['at' => '2026-10-14 05:15:00']], []) === strtotime('2026-10-14 05:15:00'), 'A waiting bus is keyed by its driver check-in time');
check(dispatcher_queue_entry_timestamp('BUS-1', $tripData, [], ['BUS-1' => ['at' => '2026-10-14 05:40:00']]) === strtotime('2026-10-14 05:40:00'), 'Prepared time is the fallback queue entry');
check(dispatcher_queue_entry_timestamp('BUS-1', $tripData, [], []) === null, 'No timestamp stays null');

/* -------------------------------- FIFO ordering -------------------------------- */

$waitEarly = fifo_candidate('BUS-1', false, strtotime('2026-10-14 05:00:00'));
$waitLate = fifo_candidate('BUS-2', false, strtotime('2026-10-14 06:00:00'));
$returnLate = fifo_candidate('BUS-3', true, strtotime('2026-10-14 10:00:00'));
$returnEarly = fifo_candidate('BUS-4', true, strtotime('2026-10-14 08:00:00'));

$ordered = dispatcher_queue_order_buses([$returnEarly, $waitLate, $returnLate, $waitEarly], [], false);
check(fifo_ids($ordered) === ['BUS-1', 'BUS-2', 'BUS-4', 'BUS-3'], 'Waiting buses keep FIFO by entry time and returning buses join the back by arrival');

$ordered = dispatcher_queue_order_buses([$returnEarly, $waitLate], [], false);
check(fifo_ids($ordered) === ['BUS-2', 'BUS-4'], 'An earlier arrival never overtakes a bus already waiting');

$published = ['BUS-2' => 1, 'BUS-1' => 2];
$ordered = dispatcher_queue_order_buses([$waitEarly, $waitLate, $returnEarly], $published, true);
check(fifo_ids($ordered) === ['BUS-2', 'BUS-1', 'BUS-4'], 'Published waiting buses lead and an unpublished returning bus joins the back');

$publishedReturn = ['BUS-4' => 1, 'BUS-2' => 2, 'BUS-1' => 3];
$ordered = dispatcher_queue_order_buses([$waitEarly, $waitLate, $returnEarly], $publishedReturn, true);
check(fifo_ids($ordered) === ['BUS-4', 'BUS-2', 'BUS-1'], 'With the order official a returning bus takes its published position ahead of later-positioned waiting buses');

$ordered = dispatcher_queue_order_buses([$waitEarly, $waitLate], $published, false);
check(fifo_ids($ordered) === ['BUS-1', 'BUS-2'], 'Away from the starting terminal the published order is ignored');

$ordered = dispatcher_queue_order_buses([$waitEarly, $waitLate], ['BUS-2' => 1], true);
check(fifo_ids($ordered) === ['BUS-2', 'BUS-1'], 'Published buses lead unlisted waiting buses');

$ordered = dispatcher_queue_order_buses([$waitEarly, $waitLate], ['bus-2' => 1], true);
check(fifo_ids($ordered) === ['BUS-2', 'BUS-1'], 'Published positions bind to bus ids case-insensitively');

$ordered = dispatcher_queue_order_buses([fifo_candidate('BUS-9', false, null), fifo_candidate('BUS-2', false, null)], [], false);
check(fifo_ids($ordered) === ['BUS-2', 'BUS-9'], 'Buses without a timestamp tie-break by bus id');

check(dispatcher_queue_order_buses([], [], false) === [], 'An empty queue stays empty');

echo "$checks dispatcher queue FIFO checks passed.\n";

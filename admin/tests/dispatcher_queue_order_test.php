<?php declare(strict_types=1);

/**
 * Dispatcher queue ordering checks: the published Admin dispatch order takes
 * precedence over the legacy schedule/bus-id fallback, and legacy behavior is
 * unchanged when no order is published.
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

function dispatch_order_ids(array $buses): array
{
    return array_map(static fn (array $bus): string => (string)$bus['bus_id'], $buses);
}

$buses = [
    ['bus_id' => 'BUS-3003', 'schedule' => '08:00 AM'],
    ['bus_id' => 'BUS-1001', 'schedule' => '05:00 AM'],
    ['bus_id' => 'BUS-2002', 'schedule' => '06:30 AM'],
];

/* ------------------------- legacy order without a publication ------------------------- */

$sorted = dispatcher_queue_sort_buses($buses);
check(dispatch_order_ids($sorted) === ['BUS-1001', 'BUS-2002', 'BUS-3003'], 'Without a publication the schedule time still orders the queue');

$sorted = dispatcher_queue_sort_buses($buses, []);
check(dispatch_order_ids($sorted) === ['BUS-1001', 'BUS-2002', 'BUS-3003'], 'An empty publication map behaves exactly like the legacy sort');

/* --------------------------- published order is authoritative --------------------------- */

$published = ['BUS-3003' => 1, 'BUS-1001' => 2, 'BUS-2002' => 3];
$sorted = dispatcher_queue_sort_buses($buses, $published);
check(dispatch_order_ids($sorted) === ['BUS-3003', 'BUS-1001', 'BUS-2002'], 'The published order overrides the schedule order');

$published = ['BUS-3003' => 1, 'BUS-2002' => 2, 'BUS-1001' => 3];
$sorted = dispatcher_queue_sort_buses($buses, $published);
check(dispatch_order_ids($sorted) === ['BUS-3003', 'BUS-2002', 'BUS-1001'], 'Every published position is honored');

/* --------------------- partially published queues keep fallback order --------------------- */

$published = ['BUS-3003' => 1];
$sorted = dispatcher_queue_sort_buses($buses, $published);
check(dispatch_order_ids($sorted) === ['BUS-3003', 'BUS-1001', 'BUS-2002'], 'Published buses lead and unpublished buses keep the legacy order');

$published = ['BUS-1001' => 3, 'BUS-2002' => 1];
$sorted = dispatcher_queue_sort_buses($buses, $published);
check(dispatch_order_ids($sorted) === ['BUS-2002', 'BUS-1001', 'BUS-3003'], 'Unlisted buses sort after published ones by schedule');

/* ------------------------------ schedule parsing is intact ------------------------------ */

check(dispatcher_queue_schedule_minutes('morning') === null, 'Unparseable schedules keep returning null');
check(dispatcher_queue_schedule_minutes('05:30') === 330, 'Leading HH:MM schedules still parse');

/* ------------------------ operational status dispatch gate ------------------------ */

check(dispatcher_operational_blocked('Under Maintenance') === true, 'Under Maintenance blocks dispatch');
check(dispatcher_operational_blocked('out of service') === true, 'Out of Service blocks dispatch case-insensitively');
check(dispatcher_operational_blocked(' On Trip ') === false, 'On Trip does not block dispatch');
check(dispatcher_operational_blocked('Available') === false, 'Available does not block dispatch');
check(dispatcher_operational_blocked('') === false, 'An empty status does not block dispatch');

/* --------------------------- Admin-published order gate --------------------------- */

check(dispatcher_admin_dispatch_gate(false, 'BUS-2', 'BUS-1', 1) === null, 'Without a publication any ready bus may dispatch (legacy mode)');
check(dispatcher_admin_dispatch_gate(true, 'BUS-1', 'BUS-1', 1) === null, 'With a publication the next ready bus dispatches');
check(dispatcher_admin_dispatch_gate(true, 'bus-1', 'BUS-1', 1) === null, 'The next-bus match is case-insensitive');
check(dispatcher_admin_dispatch_gate(true, 'BUS-1', '', 0) === null, 'With nothing ready the normal readiness checks speak');

$gate = dispatcher_admin_dispatch_gate(true, 'BUS-3', 'BUS-1', 2);
check(is_array($gate) && $gate['code'] === 'admin_queue_locked', 'Out-of-order dispatch is rejected when published');
check(strpos($gate['message'], 'BUS-1') !== false && strpos($gate['message'], '#2') !== false, 'The rejection names the next bus and position');
check($gate['next_bus_id'] === 'BUS-1' && $gate['next_position'] === 2, 'The rejection carries the next queue position');

echo "$checks dispatcher queue order checks passed.\n";

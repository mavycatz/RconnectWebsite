<?php declare(strict_types=1);

/**
 * Dispatcher driver conflict checks: a fallback (trip-record) driver must not
 * be handed to a bus when that driver is already on an active trip with a
 * different bus, which previously surfaced as "driver is already on another
 * active trip today" when dispatching another queue bus.
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

$tripData = [
    'by_driver' => [
        'DRV-001' => ['bus_id' => 'BUS-2001', 'status' => 'Departed'],
        'DRV-002' => ['bus_id' => 'BUS-3001', 'status' => 'Scheduled'],
    ],
];

check(dispatcher_driver_active_on_other_bus($tripData, 'DRV-001', 'BUS-1001') === true, 'A driver on an active trip with another bus is busy elsewhere');
check(dispatcher_driver_active_on_other_bus($tripData, 'DRV-001', 'BUS-2001') === false, 'The driver\'s own active trip does not make them busy elsewhere');
check(dispatcher_driver_active_on_other_bus($tripData, 'DRV-999', 'BUS-1001') === false, 'A driver with no active trip is not busy');
check(dispatcher_driver_active_on_other_bus(['by_driver' => []], 'DRV-001', 'BUS-1001') === false, 'An empty trip map reports no conflict');
check(dispatcher_driver_active_on_other_bus($tripData, '', 'BUS-1001') === false, 'An empty driver id never conflicts');
check(dispatcher_driver_active_on_other_bus($tripData, 'DRV-002', 'BUS-3001') === false, 'A scheduled trip on the same bus stays the bus\'s own trip');

echo "$checks dispatcher driver conflict checks passed.\n";

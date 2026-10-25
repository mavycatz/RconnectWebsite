<?php
declare(strict_types=1);

/**
 * Arrival-radius checks: the driver GPS report radius and the dispatcher's
 * nearest-terminal-within-3-km resolution. Pure logic over constants and
 * arrays; no database connection is opened.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once $_SERVER['DOCUMENT_ROOT'] . '/dispatcher/api/helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/mobile/driver/lib/arrival_report.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

/* The driver app and the dispatcher agree on one 3 km arrival radius. */
check(driver_arrival_report_distance_m() === 3000.0, 'The driver GPS arrival radius is 3 km');
check(dispatcher_queue_proximity_km() === 3.0, 'The dispatcher arrival radius is 3 km');

$source = (string) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/mobile/driver/getTripStatus.php');
check(str_contains($source, 'driver_arrival_report_distance_m()'), 'The driver status endpoint uses the shared radius');
check(!str_contains($source, '$distanceM < 300'), 'The old 300 m threshold is gone');

$terminals = [
    ['id' => 1, 'terminal_name' => 'Rizal', 'latitude' => 15.705381, 'longitude' => 121.099504],
    ['id' => 2, 'terminal_name' => 'Cabanatuan', 'latitude' => 15.472336, 'longitude' => 120.969126],
];

/* Nearest terminal wins within the radius (about 1 km east of Rizal). */
$near = dispatcher_nearest_terminal_within_km($terminals, 15.705381, 121.109504, 3.0);
check($near !== null && $near['terminal_id'] === 1, 'A point within 3 km resolves to the nearest terminal');
check($near['terminal_name'] === 'Rizal', 'The resolved terminal carries its name');

/* Just inside the radius boundary still resolves. */
$insideDistance = dispatcher_queue_distance_km(15.705381, 121.099504, 15.705381, 121.126905);
check($insideDistance !== null && $insideDistance <= 3.0, 'The boundary probe for this test is within 3 km');
$inside = dispatcher_nearest_terminal_within_km($terminals, 15.705381, 121.126905, 3.0);
check($inside !== null && $inside['terminal_id'] === 1, 'A point just inside 3 km resolves');

/* Beyond the radius resolves to nothing. */
check(dispatcher_nearest_terminal_within_km($terminals, 15.705381, 121.15, 3.0) === null, 'A point beyond 3 km resolves to nothing');

/* A closer terminal wins over a farther one. */
$closerToCabanatuan = dispatcher_nearest_terminal_within_km($terminals, 15.480000, 120.975000, 3.0);
check($closerToCabanatuan !== null && $closerToCabanatuan['terminal_id'] === 2, 'The nearest terminal wins when both are in range');

/* Invalid and incomplete input never throws. */
check(dispatcher_nearest_terminal_within_km($terminals, null, 'x', 3.0) === null, 'Missing coordinates resolve to nothing');
check(dispatcher_nearest_terminal_within_km([], 15.7, 121.1, 3.0) === null, 'No terminals resolve to nothing');
check(dispatcher_nearest_terminal_within_km([['id' => 5, 'latitude' => null, 'longitude' => null]], 15.7, 121.1, 3.0) === null, 'Terminals without coordinates are skipped');

echo "$checks dispatcher arrival radius checks passed.\n";

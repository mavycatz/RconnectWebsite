<?php
declare(strict_types=1);

/**
 * Receiving-terminal receipt checks: destination-less on-route trips are
 * inbound to any terminal, the arrival terminal resolves from the driver's
 * latest location within 3 km, and assigned trips record their terminals.
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

function receipt_test_db(): PDO
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE terminals (id INTEGER PRIMARY KEY, terminal_name TEXT, latitude REAL, longitude REAL, status TEXT)');
    $db->exec("INSERT INTO terminals VALUES
        (1, 'Rizal/Poblacion Terminal', 15.705381, 121.099504, 'Active'),
        (2, 'Cabanatuan Central Terminal', 15.472336, 120.969126, 'Active')");
    $db->exec('CREATE TABLE driver_current_location (id INTEGER PRIMARY KEY AUTOINCREMENT, driver_id TEXT, bus_id TEXT, latitude REAL, longitude REAL, speed REAL, updated_at TEXT)');
    return $db;
}

/* ------------------------- Inbound classification ------------------------- */

check(dispatcher_trip_is_inbound(['to_terminal_id' => 1, 'status' => 'Departed'], 1) === true, 'A trip to this terminal is inbound');
check(dispatcher_trip_is_inbound(['to_terminal_id' => 2, 'status' => 'Departed'], 1) === false, 'A trip to another terminal is not inbound');
check(dispatcher_trip_is_inbound(['to_terminal_id' => null, 'status' => 'Departed'], 1) === true, 'A destination-less on-route trip is inbound to any terminal');
check(dispatcher_trip_is_inbound(['to_terminal_id' => 0, 'status' => 'Scheduled'], 1) === false, 'A destination-less pending trip is not yet inbound');
check(dispatcher_trip_is_inbound(['to_terminal_id' => null, 'status' => 'Completed'], 1) === false, 'A completed destination-less trip is not inbound');
check(dispatcher_trip_is_inbound(['to_terminal_id' => 1, 'status' => 'Departed'], 0) === false, 'No terminal means nothing is inbound');

/* ---------------- Arrival terminal from the latest location --------------- */

$db = receipt_test_db();
$db->exec("INSERT INTO driver_current_location (driver_id, bus_id, latitude, longitude, speed, updated_at)
           VALUES ('DRV-1', 'Bus-1', 15.705381, 121.109504, 0, '2026-10-16 10:00:00')");
check(dispatcher_arrival_terminal_from_location($db, 'DRV-1') === 1, 'A driver within 3 km of Rizal resolves Rizal');

$db->exec("UPDATE driver_current_location SET latitude = 15.480000, longitude = 120.975000 WHERE driver_id = 'DRV-1'");
check(dispatcher_arrival_terminal_from_location($db, 'DRV-1') === 2, 'A driver near Cabanatuan resolves Cabanatuan');

$db->exec("UPDATE driver_current_location SET latitude = 15.600000, longitude = 121.000000 WHERE driver_id = 'DRV-1'");
check(dispatcher_arrival_terminal_from_location($db, 'DRV-1') === 0, 'A driver away from every terminal resolves nothing');

check(dispatcher_arrival_terminal_from_location($db, 'DRV-NONE') === 0, 'A driver without a location resolves nothing');
check(dispatcher_arrival_terminal_from_location($db, '') === 0, 'An empty driver resolves nothing');

/* --------------------- Assignment records the terminals ------------------- */

$source = (string) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/dispatcher/api/assign_dispatch.php');
check(substr_count($source, 'from_terminal_id, to_terminal_id') >= 2, 'Both assignment inserts record the terminals');
check(str_contains($source, 'dispatcher_resolve_destination('), 'The assignment destination is derived from the bus terminal');

/* ------------- Destination-less trips reach every terminal's list --------- */

$listSource = (string) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/dispatcher/api/get_dispatches.php');
check(str_contains($listSource, "bt.status = 'Departed' AND (bt.to_terminal_id IS NULL OR bt.to_terminal_id = 0)"), 'The list includes destination-less on-route trips for every terminal');
check(str_contains($listSource, "dispatcher_trip_is_inbound(\$dispatchRow, \$terminal_id)"), 'The list exposes the server-side inbound flag');
check(str_contains($listSource, "\$dispatchRow['historical_kpl'] = \$historicalKplByBus[\$busId];"), 'The bus historical KPL is attached to every trip row');
check(!preg_match('/if\s*\(\$isInbound\)/', $listSource), 'The historical KPL is no longer restricted to inbound rows');

/* --------------- Arrival actions on the Home (queue) screen -------------- */

$homePhp = (string) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/dispatcher/views/pages/home.php');
check(str_contains($homePhp, 'homeArrivingCard'), 'The Home screen carries the arriving-buses section');

$homeJs = (string) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/dispatcher/assets/js/home.js');
check(str_contains($homeJs, 'loadHomeArrivals'), 'The Home screen loads the inbound trips');
check(str_contains($homeJs, 'homeReportArrival'), 'The Home screen can report an arrival');
check(str_contains($homeJs, 'arrive_trip:'), 'The Home screen hands the arrival confirmation to the Trips screen');

$coreJs = (string) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/dispatcher/assets/js/core.js');
check(str_contains($coreJs, 'function tripIsInboundTo'), 'The inbound check is shared across screens');

$tripsJs = (string) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/dispatcher/assets/js/trips.js');
check(str_contains($tripsJs, 'focusArriveIfRequested'), 'The Trips screen opens the arrival dialog when handed a trip');

$routesSource = (string) file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/dispatcher/api/get_routes.php');
check(str_contains($routesSource, "UPPER(TRIM(route_name)) NOT IN ('TBD', 'UNASSIGNED')"), 'The dispatch route list never offers the TBD placeholder');

echo "$checks dispatcher arrival receipt checks passed.\n";

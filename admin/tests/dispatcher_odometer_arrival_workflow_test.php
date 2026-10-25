<?php declare(strict_types=1);

/**
 * Dispatcher odometer-to-arrival workflow integration checks.
 *
 * Exercises the complete chain on one fixture database:
 * dispatch records the authoritative starting odometer with no fuel/KPL entry
 * → the receiving terminal sees the inbound trip "on route" → staff report it
 * arrived → staff confirm with the actual ending odometer → the trip completes
 * with distance, confirming staff, and timestamps. Rejection cases (ending
 * below the recorded starting odometer, missing/zero ending, duplicate
 * confirmation, corrections without a reason, buses with no historical KPL)
 * are asserted to leave records unchanged.
 *
 * Terminal/role scoping (destination-terminal-only confirmation, dispatcher
 * authentication) is enforced in dispatcher/api/update_dispatch.php via the
 * session terminal and dispatcher_require_auth; it is covered by the API code
 * path rather than this database-level harness.
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

function workflow_db(): PDO
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE bus_trips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bus_id TEXT,
        driver_id TEXT,
        route_name TEXT,
        from_terminal_id INTEGER DEFAULT NULL,
        to_terminal_id INTEGER DEFAULT NULL,
        trip_date TEXT,
        trip_number INTEGER,
        departure_time TEXT,
        arrival_time TEXT,
        total_passengers INTEGER,
        duration_minutes INTEGER,
        dispatcher_id TEXT,
        fuel_cost REAL DEFAULT 0,
        status TEXT,
        arrival_reported_at TEXT DEFAULT NULL,
        arrival_report_source TEXT DEFAULT NULL,
        arrival_reported_by TEXT DEFAULT NULL,
        arrival_confirmed_at TEXT DEFAULT NULL,
        arrival_confirmed_by TEXT DEFAULT NULL
    )');
    $db->exec('CREATE TABLE fuel_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bus_id TEXT NOT NULL,
        driver_id TEXT,
        trip_id INTEGER,
        liters REAL DEFAULT 0,
        cost REAL DEFAULT 0,
        price_per_liter REAL DEFAULT 0,
        starting_odo REAL DEFAULT NULL,
        ending_odo REAL DEFAULT NULL,
        kpl REAL DEFAULT NULL,
        distance_traveled REAL DEFAULT NULL,
        expected_fuel REAL DEFAULT NULL,
        created_at TEXT DEFAULT NULL
    )');
    return $db;
}

function workflow_trip(PDO $db, int $id): array
{
    $row = $db->query("SELECT * FROM bus_trips WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    return $row === false ? [] : $row;
}

$db = workflow_db();

/* The bus carries a recorded historical measurement (odometer + distance). */
$db->exec("INSERT INTO fuel_logs (bus_id, driver_id, trip_id, liters, cost, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
           VALUES ('BUS-WF', 'DRV-OLD', 900, 0, 0, 500, 600, 8.30, 100, 12.05, '2026-10-10 08:00:00')");

/* 1. Dispatch from the origin terminal: the actual starting odometer is
   recorded, and nothing fuel- or KPL-related is fabricated. */
$db->exec("INSERT INTO bus_trips (id, bus_id, driver_id, route_name, from_terminal_id, to_terminal_id, trip_date, trip_number, departure_time, total_passengers, dispatcher_id, fuel_cost, status)
           VALUES (101, 'BUS-WF', 'DRV-WF', 'Rizal-Cabanatuan', 1, 2, '2026-10-16', 1, '08:00:00', 25, 'DISP-ORG', 0, 'Departed')");
$fuelLogId = dispatcher_insert_dispatch_fuel_log($db, 'BUS-WF', 'DRV-WF', 101, 600.0, 65.55);
check(is_int($fuelLogId) && $fuelLogId > 0, 'Dispatch creates the linked odometer record');
$dispatchFuel = $db->query("SELECT * FROM fuel_logs WHERE id = $fuelLogId")->fetch(PDO::FETCH_ASSOC);
check((float)$dispatchFuel['starting_odo'] === 600.0, 'The dispatch starting odometer is recorded');
check((float)$dispatchFuel['liters'] === 0.0 && (float)$dispatchFuel['cost'] === 0.0, 'Dispatch records no fuel quantity or cost');
check($dispatchFuel['kpl'] === null, 'Dispatch records no KPL measurement');
check($dispatchFuel['ending_odo'] === null, 'The ending odometer stays open until confirmation');

/* 2. The historical KPL reference is available for the dispatch form. */
$reference = dispatcher_bus_historical_kpl($db, 'BUS-WF');
check($reference !== null && abs($reference['kpl'] - 8.30) < 0.0001, 'The historical KPL reference is available');

/* 3. The receiving terminal sees the inbound trip as on route. */
$trip = workflow_trip($db, 101);
check((int)$trip['to_terminal_id'] === 2, 'The trip is inbound to the receiving terminal');
check(dispatcher_trip_arrival_status($trip) === 'on_route', 'The expected arrival starts as on route');

/* 4. Receiving-terminal staff report the arrival; the trip stays open. */
check(dispatcher_report_trip_arrival($db, 101, 'staff', 'DISP-RCV', '2026-10-16 09:50:00') === true, 'The receiving terminal reports the arrival');
$trip = workflow_trip($db, 101);
check($trip['status'] === 'Departed', 'The reported trip awaits confirmation');
check(dispatcher_trip_arrival_status($trip) === 'arrived_awaiting_confirmation', 'The expected arrival shows awaiting confirmation');
check($trip['arrival_reported_by'] === 'DISP-RCV', 'The reporting staff member is recorded');

/* 5. Staff confirm with the actual ending odometer. */
$result = dispatcher_confirm_trip_arrival($db, $trip, 720, '10:00:00', 25, 120, 'DISP-RCV', '2026-10-16 10:00:00');
check(!empty($result['success']), 'Staff confirmation succeeds');
$completed = workflow_trip($db, 101);
check($completed['status'] === 'Completed', 'The confirmed trip completes');
check($completed['arrival_confirmed_at'] === '2026-10-16 10:00:00', 'The confirmation timestamp is recorded');
check($completed['arrival_confirmed_by'] === 'DISP-RCV', 'The confirming staff member is recorded');
check(dispatcher_trip_arrival_status($completed) === 'confirmed', 'The expected arrival reads confirmed');
$confirmedFuel = $db->query("SELECT * FROM fuel_logs WHERE trip_id = 101")->fetch(PDO::FETCH_ASSOC);
check((float)$confirmedFuel['ending_odo'] === 720.0, 'The ending odometer is recorded');
check((float)$confirmedFuel['distance_traveled'] === 120.0, 'The distance is ending minus the recorded starting odometer');
check(abs((float)$confirmedFuel['expected_fuel'] - 14.46) < 0.01, 'The expected consumption is an estimate from the historical KPL');
check($confirmedFuel['kpl'] === null, 'No actual KPL measurement is written by confirmation');
check((float)$confirmedFuel['starting_odo'] === 600.0, 'The dispatch starting odometer is never rewritten');
check((int)$db->query("SELECT COUNT(*) FROM fuel_logs WHERE trip_id = 101")->fetchColumn() === 1, 'The confirmation creates no duplicate fuel record');
check(array_key_exists('actual_kpl', $result['fuel_data']) && $result['fuel_data']['actual_kpl'] === null, 'No actual KPL exists before liters are recorded');

/* 5b. Recording the consumed liters after the arrival computes the actual KPL
   (distance / liters) and makes it the bus's reference for the next dispatch. */
$correction = dispatcher_trip_fuel_correction($db, $confirmedFuel, ['starting_odo' => 600, 'ending_odo' => 720, 'actual_liters' => 15, 'reason' => 'Recorded consumed fuel']);
check(!empty($correction['success']), 'Recording the consumed liters on the completed trip succeeds');
check($correction['values']['actual_kpl'] === 8.0, 'Distance 120 over 15 liters computes 8.00 KM/L');
check($correction['values']['kpl'] === 8.0, 'The computed actual KPL is the value written to the record');
check($correction['values']['expected_fuel'] === null, 'No estimate is stored when actual liters exist');
$vals = $correction['values'];
$db->prepare("UPDATE fuel_logs SET starting_odo = ?, ending_odo = ?, kpl = ?, distance_traveled = ?, expected_fuel = ?, liters = ?, cost = ?, price_per_liter = ? WHERE trip_id = 101")
   ->execute([$vals['starting_odo'], $vals['ending_odo'], $vals['kpl'], $vals['distance_traveled'], $vals['expected_fuel'], $vals['liters'], $vals['cost'], $vals['price_per_liter']]);
$referenceAfter = dispatcher_bus_historical_kpl($db, 'BUS-WF');
check($referenceAfter !== null && abs($referenceAfter['kpl'] - 8.0) < 0.0001, 'The computed KPL becomes the reference for the next dispatch');

/* 6. Duplicate confirmation is refused and changes nothing. */
$again = dispatcher_confirm_trip_arrival($db, $completed, 800, '10:30:00', 25, 150, 'DISP-2', '2026-10-16 10:30:00');
check(empty($again['success']) && $again['code'] === 'already_confirmed', 'A duplicate confirmation is refused');
check((float)$db->query("SELECT ending_odo FROM fuel_logs WHERE trip_id = 101")->fetchColumn() === 720.0, 'The confirmed odometer is unchanged');

/* 7. Rejection cases leave records unchanged. */
$db->exec("INSERT INTO bus_trips (id, bus_id, driver_id, trip_date, trip_number, departure_time, status)
           VALUES (102, 'BUS-WF2', 'DRV-WF2', '2026-10-16', 1, '07:00:00', 'Departed')");
$db->exec("INSERT INTO fuel_logs (bus_id, driver_id, trip_id, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
           VALUES ('BUS-WF2', 'DRV-WF2', 102, 1000, NULL, NULL, NULL, NULL, '2026-10-16 07:00:00')");
$trip2 = workflow_trip($db, 102);
$below = dispatcher_confirm_trip_arrival($db, $trip2, 990, '09:00:00', 0, 0, 'DISP-1', '2026-10-16 09:00:00');
check(empty($below['success']) && $below['code'] === 'ending_odo_below_starting', 'An ending odometer below the starting odometer is rejected');
$missing = dispatcher_confirm_trip_arrival($db, $trip2, null, '09:00:00', 0, 0, 'DISP-1', '2026-10-16 09:00:00');
check(empty($missing['success']) && $missing['code'] === 'ending_odo_required', 'A missing ending odometer is rejected');
$zero = dispatcher_confirm_trip_arrival($db, $trip2, 0, '09:00:00', 0, 0, 'DISP-1', '2026-10-16 09:00:00');
check(empty($zero['success']) && $zero['code'] === 'ending_odo_required', 'A zero ending odometer is rejected');
check((workflow_trip($db, 102)['status'] ?? '') === 'Departed', 'The rejected trip stays on route');
check($db->query("SELECT ending_odo FROM fuel_logs WHERE trip_id = 102")->fetchColumn() === null, 'The rejected ending odometer is not recorded');

/* A bus with no historical KPL still completes with distance, but no estimate. */
$noHist = dispatcher_confirm_trip_arrival($db, $trip2, 1100, '09:30:00', 0, 0, 'DISP-1', '2026-10-16 09:30:00');
check(!empty($noHist['success']), 'A bus without a historical KPL can still confirm its arrival');
check($noHist['fuel_data']['distance_traveled'] === 100.0, 'The distance is recorded without a historical KPL');
check($noHist['fuel_data']['expected_fuel'] === null, 'No expected consumption is fabricated without a historical KPL');

/* Corrections stay on the audited, reason-required edit path. */
$existing = $db->query("SELECT * FROM fuel_logs WHERE trip_id = 102")->fetch(PDO::FETCH_ASSOC);
$noReason = dispatcher_trip_fuel_correction($db, $existing, ['starting_odo' => 1000, 'ending_odo' => 1110]);
check(empty($noReason['success']) && $noReason['code'] === 'reason_required', 'A correction without a reason is refused');
$withReason = dispatcher_trip_fuel_correction($db, $existing, ['starting_odo' => 1000, 'ending_odo' => 1110, 'reason' => 'Odometer correction']);
check(!empty($withReason['success']) && $withReason['values']['distance_traveled'] === 110.0, 'An audited correction with a reason is accepted');
check($withReason['values']['kpl'] === null, 'The correction invents no KPL when none was recorded');

echo "$checks dispatcher odometer-arrival workflow checks passed.\n";

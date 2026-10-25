<?php declare(strict_types=1);

/**
 * Dispatcher arrival report and confirmation checks.
 *
 * The receiving terminal uses a two-step arrival lifecycle:
 * 1. a report (staff screen or driver app) marks the trip arrived while it
 *    stays Departed and awaiting confirmation;
 * 2. staff confirmation records the actual ending odometer, completes the
 *    trip, and stamps the responsible staff member and timestamps.
 *
 * These checks pin the server-side data rules:
 * 1. First report wins (repeated reports never overwrite time/source/user).
 * 2. A report never completes the trip and records no odometer.
 * 3. Confirmation requires a positive ending odometer and rejects one lower
 *    than the recorded starting odometer.
 * 4. Distance is ending minus the recorded starting odometer; with no
 *    recorded starting odometer the distance stays unavailable.
 * 5. Already-confirmed trips refuse further arrival actions.
 * 6. Corrections stay on the audited, reason-required edit path.
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

function arrival_test_db(): PDO
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

function arrival_trip(PDO $db, int $id): array
{
    $row = $db->query("SELECT * FROM bus_trips WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
    return $row === false ? [] : $row;
}

/* ------------------------------- Arrival report ------------------------------- */

$reportDb = arrival_test_db();
$reportDb->exec("INSERT INTO bus_trips (bus_id, driver_id, route_name, from_terminal_id, to_terminal_id, trip_date, trip_number, departure_time, status)
                 VALUES ('BUS-R', 'DRV-R', 'Rizal-Cabanatuan', 1, 2, '2026-10-16', 1, '08:00:00', 'Departed')");

$recorded = dispatcher_report_trip_arrival($reportDb, 1, 'staff', 'DISP-1', '2026-10-16 09:30:00');
check($recorded === true, 'The first arrival report is recorded');
$trip = arrival_trip($reportDb, 1);
check(($trip['arrival_reported_at'] ?? '') === '2026-10-16 09:30:00', 'The report time is recorded');
check(($trip['arrival_report_source'] ?? '') === 'staff', 'The report source is recorded');
check(($trip['arrival_reported_by'] ?? '') === 'DISP-1', 'The responsible user is recorded');
check(($trip['status'] ?? '') === 'Departed', 'Reporting an arrival does not complete the trip');
check(($trip['arrival_confirmed_at'] ?? null) === null, 'Reporting an arrival does not confirm it');

$again = dispatcher_report_trip_arrival($reportDb, 1, 'driver_gps', 'DRV-R', '2026-10-16 09:45:00');
check($again === false, 'A repeated arrival report is ignored (first report wins)');
$trip = arrival_trip($reportDb, 1);
check(($trip['arrival_reported_at'] ?? '') === '2026-10-16 09:30:00', 'The first report time is preserved');
check(($trip['arrival_report_source'] ?? '') === 'staff', 'The first report source is preserved');
check(($trip['arrival_reported_by'] ?? '') === 'DISP-1', 'The first reporting user is preserved');
check(dispatcher_report_trip_arrival($reportDb, 999, 'staff', 'DISP-1') === false, 'Reporting a missing trip changes nothing');
$reportDb->exec("INSERT INTO bus_trips (bus_id, driver_id, trip_date, trip_number, departure_time, arrival_time, status)
                 VALUES ('BUS-C', 'DRV-C', '2026-10-16', 1, '07:00:00', '08:10:00', 'Completed')");
check(dispatcher_report_trip_arrival($reportDb, 2, 'staff', 'DISP-1', '2026-10-16 09:00:00') === false, 'A completed trip refuses an arrival report');
check((arrival_trip($reportDb, 2)['arrival_reported_at'] ?? null) === null, 'The completed trip gains no report');
check(count($reportDb->query("SELECT id FROM fuel_logs WHERE trip_id = 1")->fetchAll(PDO::FETCH_ASSOC)) === 0, 'Reporting an arrival records no odometer');

/* ---------------------------- Arrival confirmation ---------------------------- */

$confirmDb = arrival_test_db();
/* A bus with one recorded historical measurement and a linked dispatch row. */
$confirmDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, route_name, from_terminal_id, to_terminal_id, trip_date, trip_number, departure_time, total_passengers, status)
                  VALUES (3, 'BUS-CONF', 'DRV-CONF', 'Rizal-Cabanatuan', 1, 2, '2026-10-16', 1, '08:00:00', 30, 'Departed')");
$confirmDb->exec("INSERT INTO fuel_logs (bus_id, driver_id, trip_id, liters, cost, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
                  VALUES ('BUS-CONF', 'DRV-OLD', 90, 0, 0, 920, 1000, 8.00, 80, 10.00, '2026-10-10 08:00:00')");
$confirmDb->exec("INSERT INTO fuel_logs (bus_id, driver_id, trip_id, liters, cost, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
                  VALUES ('BUS-CONF', 'DRV-CONF', 3, 0, 0, 1000, NULL, NULL, NULL, NULL, '2026-10-16 08:00:00')");
$trip3 = arrival_trip($confirmDb, 3);

/* Ending lower than the recorded starting odometer: rejected, no changes. */
$result = dispatcher_confirm_trip_arrival($confirmDb, $trip3, 990, '10:00:00', 30, 120, 'DISP-1', '2026-10-16 10:00:00');
check(empty($result['success']) && $result['code'] === 'ending_odo_below_starting', 'An ending odometer below the recorded starting odometer is rejected');
check((arrival_trip($confirmDb, 3)['status'] ?? '') === 'Departed', 'A rejected confirmation leaves the trip on route');
check($confirmDb->query("SELECT ending_odo FROM fuel_logs WHERE trip_id = 3")->fetchColumn() === null, 'A rejected confirmation records no ending odometer');
check($confirmDb->query("SELECT distance_traveled FROM fuel_logs WHERE trip_id = 3")->fetchColumn() === null, 'A rejected confirmation records no distance');

/* Missing / zero / negative ending odometer: rejected, no changes. */
$result = dispatcher_confirm_trip_arrival($confirmDb, $trip3, null, '10:00:00', 30, 120, 'DISP-1', '2026-10-16 10:00:00');
check(empty($result['success']) && $result['code'] === 'ending_odo_required', 'A missing ending odometer is rejected');
$result = dispatcher_confirm_trip_arrival($confirmDb, $trip3, 0, '10:00:00', 30, 120, 'DISP-1', '2026-10-16 10:00:00');
check(empty($result['success']) && $result['code'] === 'ending_odo_required', 'A zero ending odometer is rejected');
$result = dispatcher_confirm_trip_arrival($confirmDb, $trip3, -5, '10:00:00', 30, 120, 'DISP-1', '2026-10-16 10:00:00');
check(empty($result['success']) && $result['code'] === 'ending_odo_required', 'A negative ending odometer is rejected');

/* Valid confirmation: completes the trip, records odometer, distance,
   confirmation staff/timestamps, and an estimate only. */
$result = dispatcher_confirm_trip_arrival($confirmDb, $trip3, 1120, '10:00:00', 32, 120, 'DISP-1', '2026-10-16 10:00:00');
check(!empty($result['success']), 'A valid ending odometer confirms the arrival');
$completed = arrival_trip($confirmDb, 3);
check(($completed['status'] ?? '') === 'Completed', 'Confirmation completes the trip');
check(($completed['arrival_time'] ?? '') === '10:00:00', 'The arrival time is recorded');
check((int)($completed['duration_minutes'] ?? 0) === 120, 'The duration is recorded');
check((int)($completed['total_passengers'] ?? 0) === 32, 'The reconciled passenger total is recorded');
check(($completed['arrival_confirmed_at'] ?? '') === '2026-10-16 10:00:00', 'The confirmation time is recorded');
check(($completed['arrival_confirmed_by'] ?? '') === 'DISP-1', 'The confirming staff member is recorded');
$fuelRow = $confirmDb->query("SELECT * FROM fuel_logs WHERE trip_id = 3")->fetch(PDO::FETCH_ASSOC);
check((float)$fuelRow['ending_odo'] === 1120.0, 'The ending odometer is recorded on the linked fuel record');
check((float)$fuelRow['distance_traveled'] === 120.0, 'The distance is ending minus the recorded starting odometer');
check((float)$fuelRow['starting_odo'] === 1000.0, 'The recorded starting odometer is never rewritten');
check(abs((float)$fuelRow['expected_fuel'] - 15.0) < 0.0001, 'The expected consumption is an estimate from the historical KPL');
check($fuelRow['kpl'] === null, 'Confirmation writes no actual KPL measurement');
check(($result['fuel_data']['kpl'] ?? null) === 8.0, 'The fuel data reports the historical KPL used as the reference');
check(($result['fuel_data']['starting_odo'] ?? null) === 1000.0, 'The fuel data reports the recorded starting odometer');

/* Duplicate confirmation: refused, confirmed record unchanged. */
$result = dispatcher_confirm_trip_arrival($confirmDb, $completed, 1130, '10:05:00', 32, 120, 'DISP-2', '2026-10-16 10:05:00');
check(empty($result['success']) && $result['code'] === 'already_confirmed', 'A second confirmation is refused');
check((arrival_trip($confirmDb, 3)['arrival_confirmed_by'] ?? '') === 'DISP-1', 'The confirmed staff member is unchanged');
check((float)$confirmDb->query("SELECT ending_odo FROM fuel_logs WHERE trip_id = 3")->fetchColumn() === 1120.0, 'The confirmed ending odometer is unchanged');

/* No recorded starting odometer: the ending odometer is still recorded and
   the distance stays unavailable; nothing is invented. */
$confirmDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, trip_date, trip_number, departure_time, status)
                  VALUES (4, 'BUS-NOSTART', 'DRV-NS', '2026-10-16', 1, '07:00:00', 'Departed')");
$confirmDb->exec("INSERT INTO fuel_logs (bus_id, driver_id, trip_id, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
                  VALUES ('BUS-NOSTART', 'DRV-NS', 4, NULL, NULL, NULL, NULL, NULL, '2026-10-16 07:00:00')");
$confirmDb->exec("INSERT INTO fuel_logs (bus_id, driver_id, trip_id, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
                  VALUES ('BUS-NOSTART', 'DRV-X', 91, 100, 200, 8.50, 100, 11.76, '2026-10-09 07:00:00')");
$result = dispatcher_confirm_trip_arrival($confirmDb, arrival_trip($confirmDb, 4), 500, '09:00:00', 0, 0, 'DISP-1', '2026-10-16 09:00:00');
check(!empty($result['success']), 'A trip without a recorded starting odometer can still be confirmed');
$noStartRow = $confirmDb->query("SELECT * FROM fuel_logs WHERE trip_id = 4")->fetch(PDO::FETCH_ASSOC);
check((float)$noStartRow['ending_odo'] === 500.0, 'The ending odometer is recorded');
check($noStartRow['starting_odo'] === null, 'No starting odometer is invented');
check($noStartRow['distance_traveled'] === null, 'The distance is unavailable without a starting odometer');
check($noStartRow['expected_fuel'] === null, 'No expected consumption is fabricated without a distance');

/* No linked fuel record at all: confirmation creates one with the ending
   odometer only. */
$confirmDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, trip_date, trip_number, departure_time, status)
                  VALUES (5, 'BUS-NOFUEL', 'DRV-NF', '2026-10-16', 1, '06:00:00', 'Departed')");
$result = dispatcher_confirm_trip_arrival($confirmDb, arrival_trip($confirmDb, 5), 600, '08:00:00', 0, 0, 'DISP-1', '2026-10-16 08:00:00');
check(!empty($result['success']), 'A trip without any fuel record can still be confirmed');
$newRow = $confirmDb->query("SELECT * FROM fuel_logs WHERE trip_id = 5")->fetch(PDO::FETCH_ASSOC);
check($newRow !== false && (float)$newRow['ending_odo'] === 600.0, 'Confirmation creates the missing fuel record with the ending odometer');
check($newRow['starting_odo'] === null && $newRow['distance_traveled'] === null, 'The created record invents no starting odometer or distance');
check($newRow['kpl'] === null, 'The created record carries no KPL measurement');

/* Distance without a historical KPL: recorded, but no estimate. */
$confirmDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, trip_date, trip_number, departure_time, status)
                  VALUES (6, 'BUS-NOHIST', 'DRV-NH', '2026-10-16', 1, '05:00:00', 'Departed')");
$confirmDb->exec("INSERT INTO fuel_logs (bus_id, driver_id, trip_id, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
                  VALUES ('BUS-NOHIST', 'DRV-NH', 6, 300, NULL, NULL, NULL, NULL, '2026-10-16 05:00:00')");
$result = dispatcher_confirm_trip_arrival($confirmDb, arrival_trip($confirmDb, 6), 450, '07:30:00', 0, 0, 'DISP-1', '2026-10-16 07:30:00');
check(!empty($result['success']) && ($result['fuel_data']['distance_traveled'] ?? null) === 150.0, 'Distance is still recorded without a historical KPL');
check(array_key_exists('expected_fuel', $result['fuel_data']) && $result['fuel_data']['expected_fuel'] === null, 'No expected consumption estimate is produced without a historical KPL');
check($confirmDb->query("SELECT expected_fuel FROM fuel_logs WHERE trip_id = 6")->fetchColumn() === null, 'No estimate is stored without a historical KPL');

/* An unchanged odometer is accepted with zero distance. */
$confirmDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, trip_date, trip_number, departure_time, status)
                  VALUES (7, 'BUS-EQ', 'DRV-EQ', '2026-10-16', 1, '04:00:00', 'Departed')");
$confirmDb->exec("INSERT INTO fuel_logs (bus_id, driver_id, trip_id, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
                  VALUES ('BUS-EQ', 'DRV-EQ', 7, 700, NULL, NULL, NULL, NULL, '2026-10-16 04:00:00')");
$result = dispatcher_confirm_trip_arrival($confirmDb, arrival_trip($confirmDb, 7), 700, '05:30:00', 0, 0, 'DISP-1', '2026-10-16 05:30:00');
check(!empty($result['success']) && ($result['fuel_data']['distance_traveled'] ?? null) === 0.0, 'An unchanged odometer is accepted with zero distance');

/* ------------------- Computed actual KPL from recorded liters ------------------- */

check(dispatcher_derive_actual_kpl(160, 20) === 8.0, 'Distance divided by recorded liters derives the actual KPL');
check(dispatcher_derive_actual_kpl(0, 20) === null, 'No KPL is derived without distance');
check(dispatcher_derive_actual_kpl(100, 0) === null, 'No KPL is derived without recorded liters');
check(dispatcher_derive_actual_kpl(null, 5) === null, 'No KPL is derived without distance or liters');

/* Confirmation with liters already recorded: actual KPL computed, stored, and
   used as the bus's latest reference. */
$confirmDb->exec("INSERT INTO bus_trips (id, bus_id, driver_id, trip_date, trip_number, departure_time, status)
                  VALUES (8, 'BUS-KPL', 'DRV-KPL', '2026-10-16', 1, '05:00:00', 'Departed')");
$confirmDb->exec("INSERT INTO fuel_logs (bus_id, driver_id, trip_id, liters, cost, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
                  VALUES ('BUS-KPL', 'DRV-OLD', 92, 0, 0, 300, 400, 8.50, 100, 11.76, '2026-10-09 05:00:00')");
$confirmDb->exec("INSERT INTO fuel_logs (bus_id, driver_id, trip_id, liters, cost, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
                  VALUES ('BUS-KPL', 'DRV-KPL', 8, 20, 1311, 400, NULL, NULL, NULL, NULL, '2026-10-16 05:00:00')");
$result = dispatcher_confirm_trip_arrival($confirmDb, arrival_trip($confirmDb, 8), 560, '08:00:00', 0, 0, 'DISP-1', '2026-10-16 08:00:00');
check(!empty($result['success']), 'A confirmation with recorded liters succeeds');
check(($result['fuel_data']['actual_kpl'] ?? null) === 8.0, 'The actual KPL is distance divided by recorded liters');
check(array_key_exists('expected_fuel', $result['fuel_data']) && $result['fuel_data']['expected_fuel'] === null, 'No estimate is produced when actual consumption is recorded');
$kplRow = $confirmDb->query("SELECT kpl, distance_traveled FROM fuel_logs WHERE trip_id = 8")->fetch(PDO::FETCH_ASSOC);
check(abs((float)$kplRow['kpl'] - 8.0) < 0.0001, 'The computed actual KPL is stored on the fuel record');
check((float)$kplRow['distance_traveled'] === 160.0, 'The distance is recorded with the computed KPL');
$kplRef = dispatcher_bus_historical_kpl($confirmDb, 'BUS-KPL');
check($kplRef !== null && abs($kplRef['kpl'] - 8.0) < 0.0001, 'The computed KPL becomes the bus latest historical reference');

/* ---------------- Audited odometer-only corrections (edit flow) ---------------- */

$existingRow = [
    'id' => 21,
    'bus_id' => 'BUS-EDIT',
    'driver_id' => 'DRV-EDIT',
    'trip_id' => 8,
    'liters' => 0,
    'cost' => 0,
    'price_per_liter' => 65.55,
    'starting_odo' => 1000,
    'ending_odo' => null,
    'kpl' => null,
    'distance_traveled' => null,
    'expected_fuel' => null,
    'created_at' => '2026-10-16 08:00:00',
];

/* Missing reason on an existing record is refused (audited edit path). */
$correction = dispatcher_trip_fuel_correction($confirmDb, $existingRow, ['starting_odo' => 1000, 'ending_odo' => 1100]);
check(empty($correction['success']) && $correction['code'] === 'reason_required', 'An edit without a reason is refused');
check($correction['values'] === null, 'A refused correction returns no values');

/* Odometer-only correction: no liters, no KPL - nothing inferred. */
$correction = dispatcher_trip_fuel_correction($confirmDb, $existingRow, ['starting_odo' => 1000, 'ending_odo' => 1100, 'reason' => 'Odometer typo correction']);
check(!empty($correction['success']), 'An odometer-only correction is accepted with a reason');
check($correction['values']['kpl'] === null, 'No KPL is inferred when the record had none');
check($correction['values']['expected_fuel'] === null, 'No expected consumption is inferred without a KPL basis');
check($correction['values']['liters'] === 0.0 && $correction['values']['cost'] === 0.0, 'No fuel quantity or cost is inferred');
check($correction['values']['distance_traveled'] === 100.0, 'The corrected distance is ending minus starting');

/* Ending lower than starting is refused. */
$correction = dispatcher_trip_fuel_correction($confirmDb, $existingRow, ['starting_odo' => 1000, 'ending_odo' => 900, 'reason' => 'Test']);
check(empty($correction['success']) && $correction['code'] === 'ending_odo_below_starting', 'An ending odometer below the starting odometer is refused by the edit flow');

/* A missing starting odometer is refused. */
$correction = dispatcher_trip_fuel_correction($confirmDb, null, ['ending_odo' => 900, 'reason' => 'Test']);
check(empty($correction['success']) && $correction['code'] === 'starting_odo_required', 'A correction without a starting odometer is refused');

/* An existing recorded KPL is retained and supplies the estimate. */
$existingWithKpl = array_merge($existingRow, ['kpl' => 8.50]);
$correction = dispatcher_trip_fuel_correction($confirmDb, $existingWithKpl, ['starting_odo' => 1000, 'ending_odo' => 1170, 'reason' => 'Odometer correction']);
check(!empty($correction['success']), 'A correction with an existing recorded KPL succeeds');
check($correction['values']['kpl'] === 8.50, 'The existing recorded KPL is retained when not provided');
check($correction['values']['expected_fuel'] === 20.0, 'Expected consumption uses the retained recorded KPL');

/* An explicitly provided KPL is honored. */
$correction = dispatcher_trip_fuel_correction($confirmDb, $existingWithKpl, ['starting_odo' => 1000, 'ending_odo' => 1200, 'kpl' => 10, 'reason' => 'Measured on correction']);
check(!empty($correction['success']) && $correction['values']['kpl'] === 10.0, 'An explicitly provided KPL is honored');
check($correction['values']['expected_fuel'] === 20.0, 'Expected consumption uses the provided KPL');

/* Recorded liters on a correction compute and store the actual KPL. */
$correction = dispatcher_trip_fuel_correction($confirmDb, $existingRow, ['starting_odo' => 1000, 'ending_odo' => 1170, 'actual_liters' => 20, 'reason' => 'Recorded actual fuel']);
check(!empty($correction['success']), 'A correction with recorded liters succeeds');
check($correction['values']['actual_kpl'] === 8.5, 'The correction computes the actual KPL from recorded liters');
check($correction['values']['kpl'] === 8.5, 'The computed actual KPL is written to the record');
check($correction['values']['expected_fuel'] === null, 'No estimate is produced when actual liters are recorded');

/* ------------------------- Derived arrival status ------------------------- */

check(dispatcher_trip_arrival_status(['status' => 'Departed']) === 'on_route', 'A departed trip without a report is on route');
check(dispatcher_trip_arrival_status(['status' => 'Departed', 'arrival_reported_at' => '2026-10-16 09:00:00']) === 'arrived_awaiting_confirmation', 'A report puts the trip in arrived awaiting confirmation');
check(dispatcher_trip_arrival_status(['status' => 'Completed', 'arrival_confirmed_at' => '2026-10-16 10:00:00']) === 'confirmed', 'A completed trip is confirmed');
check(dispatcher_trip_arrival_status(['status' => 'Completed']) === 'confirmed', 'A completed trip without the stamp still reads confirmed');
check(dispatcher_trip_arrival_status(['status' => 'Scheduled']) === 'not_on_route', 'A scheduled trip is not on route');
check(dispatcher_trip_arrival_status(['status' => 'Cancelled']) === 'not_on_route', 'A cancelled trip is not on route');
check(dispatcher_trip_arrival_status(null) === 'not_on_route', 'A missing trip record has no arrival status');

echo "$checks dispatcher arrival confirmation checks passed.\n";

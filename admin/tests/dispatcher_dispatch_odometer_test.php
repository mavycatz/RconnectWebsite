<?php declare(strict_types=1);

/**
 * Dispatcher dispatch-odometer and historical-KPL reference checks.
 *
 * The Dispatch Trip form records only the bus's actual starting odometer;
 * fuel liters and an editable KPL are gone, and the form shows the bus's
 * latest valid historical KPL as a read-only reference (or "No historical
 * KPL available"). These checks pin the data rules those behaviors rely on:
 *
 * 1. Historical KPL comes from the latest fuel_logs row with a recorded
 *    KPL (> 0) AND measurement evidence (ending odometer, distance, liters,
 *    or expected consumption).
 * 2. Legacy rows whose only value is the 9.0 dispatch fallback are not
 *    presented as measurements.
 * 3. Dispatch intake (to be extended below) records the starting odometer
 *    and never fabricates fuel quantities or KPL measurements.
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

function odometer_test_db(): PDO
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

/* ---------------------- Historical KPL reference rules ---------------------- */

$kplDb = odometer_test_db();

/* Generated measurement rows: KPL recorded with odometer/distance evidence. */
$kplDb->exec("INSERT INTO fuel_logs (bus_id, liters, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
              VALUES ('BUS-1', 0, 1000, 1100, 8.50, 100, 11.76, '2026-10-10 08:00:00')");
$kplDb->exec("INSERT INTO fuel_logs (bus_id, liters, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
              VALUES ('BUS-1', 0, 1100, 1220, 8.75, 120, 13.71, '2026-10-12 09:00:00')");
$historical = dispatcher_bus_historical_kpl($kplDb, 'BUS-1');
check($historical !== null && abs($historical['kpl'] - 8.75) < 0.0001, 'The latest recorded KPL is the historical reference');
check(($historical['recorded_at'] ?? '') === '2026-10-12 09:00:00', 'The reference carries the source row date');

/* A fallback-only row (kpl = 9.0 with no evidence) is not a measurement. */
$kplDb->exec("INSERT INTO fuel_logs (bus_id, liters, cost, price_per_liter, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
              VALUES ('BUS-2', 0, 0, 0, 500, NULL, 9.0, NULL, NULL, '2026-10-13 07:00:00')");
check(dispatcher_bus_historical_kpl($kplDb, 'BUS-2') === null, 'A legacy 9.0 fallback row with no evidence is not a historical KPL');

/* The fallback is skipped even when it is newer than the real measurement. */
$kplDb->exec("INSERT INTO fuel_logs (bus_id, liters, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
              VALUES ('BUS-3', 0, 2000, 2090, 8.20, 90, 10.98, '2026-10-09 08:00:00')");
$kplDb->exec("INSERT INTO fuel_logs (bus_id, liters, cost, price_per_liter, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
              VALUES ('BUS-3', 0, 0, 0, 2090, NULL, 9.0, NULL, NULL, '2026-10-14 07:00:00')");
$historical = dispatcher_bus_historical_kpl($kplDb, 'BUS-3');
check($historical !== null && abs($historical['kpl'] - 8.20) < 0.0001, 'A newer fallback row does not hide the last real measurement');

/* A recorded fuel quantity alone is measurement evidence. */
$kplDb->exec("INSERT INTO fuel_logs (bus_id, liters, cost, price_per_liter, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
              VALUES ('BUS-4', 40, 2622, 65.55, 3000, NULL, 8.90, NULL, NULL, '2026-10-11 06:00:00')");
$historical = dispatcher_bus_historical_kpl($kplDb, 'BUS-4');
check($historical !== null && abs($historical['kpl'] - 8.90) < 0.0001, 'A recorded fuel quantity counts as measurement evidence');

/* Zero or missing KPL values never count. */
$kplDb->exec("INSERT INTO fuel_logs (bus_id, liters, starting_odo, ending_odo, kpl, distance_traveled, expected_fuel, created_at)
              VALUES ('BUS-5', 0, 4000, 4100, 0, 100, 12.05, '2026-10-12 08:00:00')");
check(dispatcher_bus_historical_kpl($kplDb, 'BUS-5') === null, 'A zero KPL is not a historical measurement');

/* No fuel history at all. */
check(dispatcher_bus_historical_kpl($kplDb, 'BUS-6') === null, 'A bus without fuel history has no historical KPL');
check(dispatcher_bus_historical_kpl($kplDb, '') === null, 'An empty bus id has no historical KPL');

/* Variant installs: ending odometer spelled differently still counts. */
$variantDb = new PDO('sqlite::memory:');
$variantDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$variantDb->exec('CREATE TABLE fuel_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bus_id TEXT NOT NULL,
    liters REAL DEFAULT 0,
    starting_odometer REAL DEFAULT NULL,
    ending_odometer REAL DEFAULT NULL,
    kpl REAL DEFAULT NULL,
    created_at TEXT DEFAULT NULL
)');
$variantDb->exec("INSERT INTO fuel_logs (bus_id, liters, starting_odometer, ending_odometer, kpl, created_at)
                  VALUES ('BUS-V', 0, 100, 210, 8.40, '2026-10-12 08:00:00')");
$historical = dispatcher_bus_historical_kpl($variantDb, 'BUS-V');
check($historical !== null && abs($historical['kpl'] - 8.40) < 0.0001, 'Variant odometer column spellings are supported');

/* ------------------------- Starting odometer intake ------------------------- */

check(dispatcher_normalize_starting_odo(null) === null, 'A missing starting odometer is rejected');
check(dispatcher_normalize_starting_odo('') === null, 'An empty starting odometer is rejected');
check(dispatcher_normalize_starting_odo('abc') === null, 'A non-numeric starting odometer is rejected');
check(dispatcher_normalize_starting_odo(0) === null, 'A zero starting odometer is rejected');
check(dispatcher_normalize_starting_odo('0') === null, 'A string zero starting odometer is rejected');
check(dispatcher_normalize_starting_odo(-5) === null, 'A negative starting odometer is rejected');
check(dispatcher_normalize_starting_odo(' 1001 ') === 1001.0, 'A numeric string starting odometer is accepted and trimmed');
check(dispatcher_normalize_starting_odo(1001.5) === 1001.5, 'A float starting odometer is accepted');

/* ----------------------- Dispatch fuel_logs intake rules ----------------------- */

$dispatchDb = odometer_test_db();
$fuelLogId = dispatcher_insert_dispatch_fuel_log($dispatchDb, 'BUS-D', 'DRV-D', 77, 1543.5, 65.55);
check(is_int($fuelLogId) && $fuelLogId > 0, 'The dispatch creates a linked fuel record');
$row = $dispatchDb->query("SELECT * FROM fuel_logs WHERE id = $fuelLogId")->fetch(PDO::FETCH_ASSOC);
check($row !== false, 'The linked fuel record is readable');
check((float)$row['starting_odo'] === 1543.5, 'The starting odometer is recorded on the linked fuel record');
check($row['ending_odo'] === null, 'The ending odometer stays unrecorded until arrival is confirmed');
check((float)$row['liters'] === 0.0, 'No fuel liters are recorded at dispatch');
check((float)$row['cost'] === 0.0, 'No fuel cost is recorded at dispatch');
check($row['kpl'] === null, 'No KPL measurement is fabricated at dispatch (nullable column stores NULL)');
check((int)$row['trip_id'] === 77, 'The linked fuel record is tied to the trip');

/* On installs whose kpl column is NOT NULL, dispatch writes 0 - still no measurement. */
$strictDb = new PDO('sqlite::memory:');
$strictDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$strictDb->exec('CREATE TABLE fuel_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bus_id TEXT NOT NULL,
    trip_id INTEGER,
    liters REAL NOT NULL DEFAULT 0,
    cost REAL NOT NULL DEFAULT 0,
    kpl REAL NOT NULL DEFAULT 9.0,
    starting_odo REAL DEFAULT NULL,
    ending_odo REAL DEFAULT NULL,
    created_at TEXT DEFAULT NULL
)');
$strictId = dispatcher_insert_dispatch_fuel_log($strictDb, 'BUS-N', 'DRV-N', 78, 2000.0);
check(is_int($strictId) && $strictId > 0, 'A NOT NULL kpl column still accepts the dispatch record');
$strictKpl = $strictDb->query("SELECT kpl FROM fuel_logs WHERE id = $strictId")->fetchColumn();
check((float)$strictKpl === 0.0, 'A NOT NULL kpl column receives 0 (never the 9.0 fallback)');
check(dispatcher_bus_historical_kpl($strictDb, 'BUS-N') === null, 'The dispatch row does not become a historical KPL');

/* The linked record satisfies the queue fuel/odometer readiness reference. */
$readinessMap = dispatcher_queue_fuel_map($dispatchDb, ['BUS-D']);
check(isset($readinessMap['BUS-D']), 'The queue fuel map finds the dispatch odometer reference');
check((float)$readinessMap['BUS-D']['starting_odo'] > 0, 'The queue fuel readiness sees a usable starting odometer');

/* Without a linked record the map has no reference (dispatch would only be
   allowed after the operator enters the odometer). */
$emptyMap = dispatcher_queue_fuel_map($dispatchDb, ['BUS-NO-HISTORY']);
check(!isset($emptyMap['BUS-NO-HISTORY']), 'A bus without a fuel record has no readiness reference until dispatch records one');

/* --------------------- Fuel reference payload (dispatch form) --------------------- */

$ref = dispatcher_bus_fuel_reference($kplDb, 'BUS-1');
check($ref['historical_kpl'] !== null && abs($ref['historical_kpl'] - 8.75) < 0.0001, 'The fuel reference exposes the latest valid historical KPL');
check(($ref['historical_kpl_recorded_at'] ?? '') === '2026-10-12 09:00:00', 'The fuel reference exposes the historical KPL source date');
check($ref['suggested_kpl'] !== null && abs($ref['suggested_kpl'] - 8.75) < 0.0001, 'The deprecated suggested_kpl mirrors the historical KPL');

$refMissing = dispatcher_bus_fuel_reference($kplDb, 'BUS-2');
check($refMissing['historical_kpl'] === null, 'A bus with no valid measurement returns no historical KPL');
check($refMissing['historical_kpl_recorded_at'] === null, 'No source date is fabricated without a historical KPL');
check($refMissing['suggested_kpl'] === null, 'The reference never falls back to the 9.0 default');

echo "$checks dispatcher dispatch odometer checks passed.\n";

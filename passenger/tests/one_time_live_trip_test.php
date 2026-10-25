<?php

/**
 * OneTimeService::liveTripForPassengerAndTrip over an isolated SQLite database:
 * completed/cancelled/archived/ended trips are excluded, live rows are
 * returned, bus-trip-id matching is primary (a different trip whose
 * trip_number equals this trip's id can never match), and the alphanumeric
 * fallback only applies when no bus trip resolves.
 *
 * Run: php passenger/tests/one_time_live_trip_test.php
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/../services/OneTime/OneTimeService.php';

$failures = 0;
$checks = 0;

function check($label, $condition)
{
    global $failures, $checks;
    $checks++;

    if ($condition) {
        echo "PASS  $label\n";
    } else {
        echo "FAIL  $label\n";
        $failures++;
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$db->exec("
    CREATE TABLE passengers (id INTEGER PRIMARY KEY, full_name TEXT, discount_type TEXT);
    CREATE TABLE bus_trips (id INTEGER PRIMARY KEY, trip_number TEXT, status TEXT);
    CREATE TABLE passenger_trips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        passenger_id INT,
        bus_no TEXT,
        trip_no TEXT,
        driver_id TEXT,
        passenger_type TEXT,
        boarding_point TEXT,
        destination TEXT,
        fare REAL,
        base_fare REAL,
        discount_amount REAL,
        payment_method TEXT,
        payment_status TEXT,
        trip_status TEXT,
        identification_source TEXT,
        dropoff_time TEXT,
        archived_status TEXT,
        declared_id_number TEXT,
        declared_id_status TEXT,
        submission_token TEXT
    );
");

$db->exec("
    INSERT INTO passengers (id, full_name, discount_type) VALUES (1, 'Rider One', 'Regular');
    INSERT INTO bus_trips (id, trip_number, status) VALUES
        (77, 'T-77', 'Departed'),
        (78, 'T-78', 'Departed'),
        (79, 'T-79', 'Completed'),
        (200, '77', 'Departed');
");

function insert_trip(PDO $db, array $overrides = []): int
{
    $row = array_merge([
        'passenger_id' => 1,
        'bus_no' => 'Bus-12',
        'trip_no' => '77',
        'driver_id' => 'DRV-1',
        'passenger_type' => 'Regular',
        'boarding_point' => 'Stop A',
        'destination' => 'Stop C',
        'fare' => 30.0,
        'base_fare' => 30.0,
        'discount_amount' => 0.0,
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'trip_status' => 'In Progress',
        'identification_source' => 'one_time',
        'dropoff_time' => null,
        'archived_status' => 'no',
        'declared_id_number' => null,
        'declared_id_status' => null,
        'submission_token' => uniqid('tok-', true),
    ], $overrides);

    $stmt = $db->prepare("
        INSERT INTO passenger_trips
            (passenger_id, bus_no, trip_no, driver_id, passenger_type, boarding_point, destination,
             fare, base_fare, discount_amount, payment_method, payment_status, trip_status,
             identification_source, dropoff_time, archived_status, declared_id_number,
             declared_id_status, submission_token)
        VALUES
            (:passenger_id, :bus_no, :trip_no, :driver_id, :passenger_type, :boarding_point, :destination,
             :fare, :base_fare, :discount_amount, :payment_method, :payment_status, :trip_status,
             :identification_source, :dropoff_time, :archived_status, :declared_id_number,
             :declared_id_status, :submission_token)
    ");
    $stmt->execute($row);

    return (int) $db->lastInsertId();
}

/* --- Live in-progress row is returned ----------------------------------- */
$liveId = insert_trip($db);
$live = OneTimeService::liveTripForPassengerAndTrip($db, 1, 77, 'T-77');
check('an in-progress trip is live', $live !== null && (int) $live['id'] === $liveId);

/* --- Completed / cancelled rows are excluded ----------------------------- */
$completedId = insert_trip($db, ['trip_no' => '78', 'trip_status' => 'Completed']);
$cancelledId = insert_trip($db, ['trip_no' => '78', 'trip_status' => 'Cancelled']);

check('a completed row for the scanned trip is not live', OneTimeService::liveTripForPassengerAndTrip($db, 1, 78, 'T-78') === null);
check('a cancelled row for the scanned trip is not live', OneTimeService::liveTripForPassengerAndTrip($db, 1, 78, 'T-78') === null);

$db->exec("UPDATE passenger_trips SET trip_status = 'Completed' WHERE id = $liveId");
check('completed trip_status releases the trip', OneTimeService::liveTripForPassengerAndTrip($db, 1, 77, 'T-77') === null);
$db->exec("UPDATE passenger_trips SET trip_status = 'Cancelled' WHERE id = $liveId");
check('cancelled trip_status releases the trip', OneTimeService::liveTripForPassengerAndTrip($db, 1, 77, 'T-77') === null);

/* --- Terminal dropoff releases the trip ---------------------------------- */
$db->exec("UPDATE passenger_trips SET trip_status = 'In Progress', dropoff_time = '' WHERE id = $liveId");
check('an empty-string dropoff still counts as live (legacy "no dropoff")', OneTimeService::liveTripForPassengerAndTrip($db, 1, 77, 'T-77') !== null);
$db->exec("UPDATE passenger_trips SET dropoff_time = '2026-10-16 10:00:00' WHERE id = $liveId");
check('a recorded dropoff releases the trip', OneTimeService::liveTripForPassengerAndTrip($db, 1, 77, 'T-77') === null);

/* --- Archived rows are excluded ------------------------------------------ */
$db->exec("UPDATE passenger_trips SET dropoff_time = NULL, archived_status = 'yes' WHERE id = $liveId");
check('an archived row releases the trip', OneTimeService::liveTripForPassengerAndTrip($db, 1, 77, 'T-77') === null);
$db->exec("UPDATE passenger_trips SET archived_status = 'no' WHERE id = $liveId");

/* --- A terminal bus trip releases the passenger row ---------------------- */
$db->exec("UPDATE passenger_trips SET trip_no = '79' WHERE id = $liveId");
check('a completed bus trip releases the row left behind', OneTimeService::liveTripForPassengerAndTrip($db, 1, 79, 'T-79') === null);
$db->exec("UPDATE passenger_trips SET trip_no = '77' WHERE id = $liveId");

/* --- Bus-trip-id matching is primary ------------------------------------- */
/* Trip 200's trip_number is literally '77'. A live row for trip 200 must NOT
   be returned when scanning bus trip 77. */
$trapId = insert_trip($db, ['trip_no' => '200', 'bus_no' => 'Bus-13']);
$byId = OneTimeService::liveTripForPassengerAndTrip($db, 1, 77, 'T-77');
check('scanning trip 77 returns the trip-77 row, never the id-colliding trip 200 row',
    $byId !== null && (int) $byId['id'] === $liveId);
$trapped = OneTimeService::liveTripForPassengerAndTrip($db, 1, 200, '77');
check('scanning trip 200 returns its own row', $trapped !== null && (int) $trapped['id'] === $trapId);

/* --- trip_number identifier still resolves through the id ---------------- */
$byNumber = OneTimeService::liveTripForPassengerAndTrip($db, 1, 'T-77');
check('the trip_number identifier resolves to the bus trip id', $byNumber !== null && (int) $byNumber['id'] === $liveId);

/* --- Fallback match when no bus trip resolves ---------------------------- */
$legacyId = insert_trip($db, ['trip_no' => 'LEGACY-9']);
$legacy = OneTimeService::liveTripForPassengerAndTrip($db, 1, 'LEGACY-9');
check('an unresolved identifier falls back to the stored trip_no', $legacy !== null && (int) $legacy['id'] === $legacyId);

/* --- Other passengers never match ---------------------------------------- */
$db->exec("INSERT INTO passengers (id, full_name, discount_type) VALUES (2, 'Rider Two', 'Regular')");
check('another passenger has no live trip for this bus trip', OneTimeService::liveTripForPassengerAndTrip($db, 2, 77, 'T-77') === null);

echo "\n$checks checks, $failures failure(s)\n";
exit($failures === 0 ? 0 : 1);

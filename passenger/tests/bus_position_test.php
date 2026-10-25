<?php declare(strict_types=1);

/**
 * Driver/bus location primitive checks.
 *
 * Covers the helpers extracted from DriverLocationService.php into
 * DriverLocationSupport.php (bus-id matching, coordinate validity, record
 * timestamp rule, time basis, age/skew, freshness) and the bus-trip-scoped
 * position resolver added on top of them.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once __DIR__ . '/../services/DriverLocationSupport.php';
require_once __DIR__ . '/../services/BusPositionService.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- Bus-id matching ---------------------------------------------------------

check(passengerBusIdMatches('Bus-12', 'bus 12'), 'Bus-12 matches bus 12');
check(passengerBusIdMatches('Bus-12', '12'), 'Bus-12 matches the bare number 12');
check(passengerBusIdMatches('12', 'Bus-12'), 'The bare number matches Bus-12');
check(passengerBusIdMatches('BUS #12', 'bus-12'), 'Punctuation between bus and number is ignored');
check(!passengerBusIdMatches('Bus-12', 'Bus-13'), 'Different numbers do not match');
check(!passengerBusIdMatches('', 'Bus-12'), 'An empty id never matches');
check(!passengerBusIdMatches('Bus-12', ''), 'An empty candidate never matches');

// --- Coordinate validity -----------------------------------------------------

check(passengerDriverLocationCoordinatesValid(['latitude' => 15.5, 'longitude' => 121.0]), 'A valid coordinate pair passes');
check(!passengerDriverLocationCoordinatesValid(['latitude' => 15.5]), 'A missing longitude fails');
check(!passengerDriverLocationCoordinatesValid(['latitude' => 'abc', 'longitude' => 121.0]), 'A non-numeric latitude fails');
check(!passengerDriverLocationCoordinatesValid(['latitude' => 91.0, 'longitude' => 121.0]), 'An out-of-range latitude fails');
check(!passengerDriverLocationCoordinatesValid(['latitude' => 15.5, 'longitude' => 181.0]), 'An out-of-range longitude fails');

// --- Record timestamp rule ---------------------------------------------------

$history = passengerDriverLocationRecordTimestamp(['created_at' => '2026-10-15 10:00:00', 'updated_at' => '2026-10-15 09:00:00'], PASSENGER_DRIVER_POSITION_SOURCE_HISTORY);
check($history['value'] === '2026-10-15 10:00:00' && $history['column'] === 'created_at', 'History uses created_at');

$historyFallback = passengerDriverLocationRecordTimestamp(['updated_at' => '2026-10-15 09:00:00'], PASSENGER_DRIVER_POSITION_SOURCE_HISTORY);
check($historyFallback['value'] === '2026-10-15 09:00:00' && $historyFallback['column'] === 'updated_at', 'History falls back to updated_at');

$snapshot = passengerDriverLocationRecordTimestamp(['created_at' => '2026-10-15 08:00:00', 'updated_at' => '2026-10-15 10:00:00'], PASSENGER_DRIVER_POSITION_SOURCE_SNAPSHOT);
check($snapshot['value'] === '2026-10-15 10:00:00' && $snapshot['column'] === 'updated_at', 'Snapshot prefers updated_at');

$snapshotFallback = passengerDriverLocationRecordTimestamp(['created_at' => '2026-10-15 08:00:00'], PASSENGER_DRIVER_POSITION_SOURCE_SNAPSHOT);
check($snapshotFallback['value'] === '2026-10-15 08:00:00' && $snapshotFallback['column'] === 'created_at', 'Snapshot falls back to created_at');

$none = passengerDriverLocationRecordTimestamp([]);
check($none['value'] === '' && $none['column'] === null, 'An undated record reports no timestamp');

// --- Time basis, age, and clock skew ----------------------------------------

$basis = passengerDriverTimeBasis();
check($basis['timezone'] === 'Asia/Manila', 'The declared basis is Asia/Manila');
check($basis['offset'] === '+08:00', 'The declared offset is +08:00');

$reference = passengerDriverReferenceTime($db);
check($reference['source'] === 'php_now', 'A non-MySQL connection reads the reference time from PHP');
check(
    preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $reference['value']) === 1,
    'The reference time is a formatted timestamp'
);

$age = passengerDriverRecordAge($db, '2026-10-15 10:00:00', '2026-10-15 10:00:20');
check($age['age_seconds'] === 20, 'An older record reports its age');
check($age['basis'] === 'Asia/Manila', 'The age is computed on the declared basis');

$skewed = passengerDriverRecordAge($db, '2026-10-15 10:00:10', '2026-10-15 10:00:00');
check($skewed['age_seconds'] === null, 'A record stamped ahead of the clock is not trusted');
check($skewed['basis'] === 'clock_skew', 'The skew is reported as clock_skew');
check($skewed['skew_seconds'] === -10, 'The skew seconds are reported');

$jitter = passengerDriverRecordAge($db, '2026-10-15 10:00:03', '2026-10-15 10:00:00');
check($jitter['age_seconds'] === 0, 'A few seconds of jitter reads as age zero, not skew');
check($jitter['skew_seconds'] === -3, 'Small jitter is recorded but forgiven');

$unparseable = passengerDriverRecordAge($db, 'not-a-timestamp');
check($unparseable['age_seconds'] === null, 'An unparseable timestamp has no age');

$scalar = passengerDriverAgeSeconds($db, '2026-10-15 10:00:00');
check(is_int($scalar), 'The scalar age form returns an integer');

// --- Freshness classification ------------------------------------------------

check(passengerDriverFreshnessState(0) === 'FRESH', 'Zero seconds is FRESH');
check(passengerDriverFreshnessState(FRESHNESS_FRESH_SECONDS) === 'FRESH', 'The fresh boundary is FRESH');
check(passengerDriverFreshnessState(FRESHNESS_FRESH_SECONDS + 1) === 'AGING', 'Just past fresh is AGING');
check(passengerDriverFreshnessState(FRESHNESS_AGING_SECONDS) === 'AGING', 'The aging boundary is AGING');
check(passengerDriverFreshnessState(FRESHNESS_AGING_SECONDS + 1) === 'STALE', 'Past aging is STALE');
check(passengerDriverFreshnessState(null) === 'STALE', 'An unknown age is STALE');

// --- Bus-trip position resolver ---------------------------------------------

$db->exec("
    CREATE TABLE bus_trips (
        id INTEGER PRIMARY KEY,
        bus_id TEXT,
        driver_id TEXT,
        route_name TEXT,
        status TEXT,
        trip_date TEXT
    );
    CREATE TABLE driver_location_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        driver_id TEXT,
        bus_id TEXT,
        latitude REAL,
        longitude REAL,
        created_at TEXT
    );
    CREATE TABLE driver_current_location (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        driver_id TEXT,
        bus_id TEXT,
        latitude REAL,
        longitude REAL,
        created_at TEXT,
        updated_at TEXT
    );
    CREATE TABLE simulation_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bus_id TEXT,
        driver_id TEXT,
        trip_id INTEGER,
        status TEXT,
        current_lat REAL,
        current_lng REAL,
        speed_kmh REAL,
        progress_pct REAL,
        updated_at TEXT,
        last_tick_at TEXT
    )
");

$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$stamp = static function (int $secondsAgo) use ($now): string {
    return $now->modify(($secondsAgo >= 0 ? '-' : '+') . abs($secondsAgo) . ' seconds')->format('Y-m-d H:i:s');
};

$trip = ['id' => 77, 'bus_id' => 'Bus-12', 'driver_id' => 'DRV-1', 'route_name' => 'Cabanatuan-Rizal', 'status' => 'Departed'];

// No records at all.
$position = passengerBusPositionForTrip($db, $trip);
check($position['available'] === false, 'No records means no position');
check($position['usable'] === false && $position['reason'] === 'no_driver_location', 'The reason is no_driver_location');

// Fresh history wins.
$db->exec("INSERT INTO driver_location_history (driver_id, bus_id, latitude, longitude, created_at)
    VALUES ('DRV-1', 'Bus-12', 15.52, 120.94, '{$stamp(10)}')");
$position = passengerBusPositionForTrip($db, $trip);
check($position['available'] === true && $position['usable'] === true, 'A fresh history row is usable');
check($position['source'] === 'driver_location_history', 'The source is the history table');
check($position['latitude'] === 15.52 && $position['longitude'] === 120.94, 'The history coordinates are returned');
check($position['freshness'] === 'FRESH', 'A 10-second-old row is FRESH');
check($position['age_seconds'] === 10, 'The age is reported');

// A foreign bus row is rejected; the newest valid bus row is used instead.
$db->exec("INSERT INTO driver_location_history (driver_id, bus_id, latitude, longitude, created_at)
    VALUES ('DRV-1', 'Bus-99', 14.0, 119.0, '{$stamp(5)}')");
$position = passengerBusPositionForTrip($db, $trip);
check($position['latitude'] === 15.52 && $position['longitude'] === 120.94, 'A newer row for another bus is ignored');

// A stale history row falls back to nothing rather than claiming movement.
$db->exec("DELETE FROM driver_location_history");
$db->exec("INSERT INTO driver_location_history (driver_id, bus_id, latitude, longitude, created_at)
    VALUES ('DRV-1', 'Bus-12', 15.60, 120.98, '{$stamp(600)}')");
$position = passengerBusPositionForTrip($db, $trip);
check($position['available'] === true && $position['usable'] === false, 'A stale history row is not usable');
check($position['reason'] === 'stale', 'The stale reason is reported');
check($position['freshness'] === 'STALE', 'The stale freshness is reported');

// Snapshot fallback when history has no usable row.
$db->exec("DELETE FROM driver_location_history");
$db->exec("INSERT INTO driver_current_location (driver_id, bus_id, latitude, longitude, created_at, updated_at)
    VALUES ('DRV-1', 'Bus-12', 15.55, 120.95, '{$stamp(600)}', '{$stamp(15)}')");
$position = passengerBusPositionForTrip($db, $trip);
check($position['source'] === 'driver_current_location' && $position['freshness'] === 'FRESH', 'The snapshot supplies the fallback position');
check($position['age_seconds'] === 15, 'The snapshot age uses updated_at');

// A foreign driver snapshot never satisfies this trip's bus.
$db->exec("DELETE FROM driver_current_location");
$db->exec("INSERT INTO driver_current_location (driver_id, bus_id, latitude, longitude, created_at, updated_at)
    VALUES ('DRV-OTHER', 'Bus-99', 14.0, 119.0, '{$stamp(1)}', '{$stamp(1)}')");
$position = passengerBusPositionForTrip($db, $trip);
check($position['available'] === false, 'A foreign driver/bus snapshot is ignored');

// An active simulation session overrides stale live rows.
$db->exec("INSERT INTO driver_current_location (driver_id, bus_id, latitude, longitude, created_at, updated_at)
    VALUES ('DRV-1', 'Bus-12', 15.55, 120.95, '{$stamp(600)}', '{$stamp(600)}')");
$db->exec("INSERT INTO simulation_sessions (bus_id, driver_id, trip_id, status, current_lat, current_lng, speed_kmh, updated_at, last_tick_at)
    VALUES ('Bus-12', 'DRV-1', 77, 'running', 15.5482, 120.9335, 42.0, '{$stamp(2)}', '{$stamp(2)}')");
$position = passengerBusPositionForTrip($db, $trip);
check($position['usable'] === true, 'An active simulation session supplies a usable position');
check($position['source'] === 'simulation', 'The source is marked simulation');
check($position['latitude'] === 15.5482 && $position['longitude'] === 120.9335, 'The simulated coordinates are returned');

// A disabled session does not override.
$db->exec("UPDATE simulation_sessions SET status = 'disabled'");
$position = passengerBusPositionForTrip($db, $trip);
check($position['source'] !== 'simulation', 'A disabled session is not an override');
check($position['usable'] === false, 'The disabled simulation falls back to the stale live row');

// A trip with no driver still resolves by bus.
$db->exec("DELETE FROM driver_current_location");
$db->exec("DELETE FROM simulation_sessions");
$db->exec("INSERT INTO driver_location_history (driver_id, bus_id, latitude, longitude, created_at)
    VALUES ('DRV-1', 'Bus-12', 15.51, 120.96, '{$stamp(12)}')");
$busOnlyTrip = ['id' => 78, 'bus_id' => 'Bus-12', 'driver_id' => null, 'route_name' => 'Cabanatuan-Rizal', 'status' => 'Departed'];
$position = passengerBusPositionForTrip($db, $busOnlyTrip);
check($position['usable'] === true, 'A trip without a driver id still resolves by bus');
check($position['latitude'] === 15.51, 'The bus-scoped history row is used');

echo "{$checks} bus position checks passed.\n";

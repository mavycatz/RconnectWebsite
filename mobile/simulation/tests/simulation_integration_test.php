<?php

/**
 * Integration test for the simulation engine against a dedicated MySQL test
 * database. It creates (and drops) its own schema so the real database is
 * never touched:
 *
 *   php mobile/simulation/tests/simulation_integration_test.php
 *
 * Skips (exit 3) when the local MySQL user cannot create a test database.
 * Exits 0 when every assertion passes, 1 otherwise.
 */

define('APP_INIT', true);
$credentialsPath = __DIR__ . '/../../config/db_credentials.php';

/* Mirrored copies ship without the credentials file; skip instead of fatal. */
if (!is_file($credentialsPath)) {
    echo "SKIP: no database credentials at $credentialsPath\n";
    exit(3);
}

$credentials = require $credentialsPath;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$testDb = 'rconnect_sim_test';

try {
    $server = new mysqli($credentials['host'], $credentials['username'], $credentials['password'], null, null, 5);
    $server->query("DROP DATABASE IF EXISTS `$testDb`");
    $server->query("CREATE DATABASE `$testDb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $server->select_db($testDb);
} catch (\Throwable $error) {
    echo "SKIP: cannot create the test database: " . $error->getMessage() . "\n";
    exit(3);
}

$conn = $server;

require_once __DIR__ . '/../lib/simulation_engine.php';

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

function run_sql($conn, $sql)
{
    if (!$conn->multi_query($sql)) {
        throw new RuntimeException('SQL failed: ' . $conn->error);
    }

    while ($conn->more_results()) {
        $conn->next_result();
    }
}

/**
 * Where a landmark's closest approach falls inside a segment, as 0..1.
 * Equirectangular approximation, good to a few metres at these latitudes.
 */
function segment_fraction($from, $to, $point)
{
    $latScale = 110948.0;
    $lngScale = 107280.0;
    $vx = ($to['lat'] - $from['lat']) * $latScale;
    $vy = ($to['lng'] - $from['lng']) * $lngScale;
    $wx = ($point['lat'] - $from['lat']) * $latScale;
    $wy = ($point['lng'] - $from['lng']) * $lngScale;
    $den = $vx * $vx + $vy * $vy;
    $t = $den > 0 ? (($wx * $vx) + ($wy * $vy)) / $den : 0.0;

    return max(0.0, min(1.0, $t));
}

function closest_approach($waypoints, $plan, $point)
{
    $best = null;
    $latScale = 110948.0;
    $lngScale = 107280.0;

    foreach ($plan['segments'] as $index => $segment) {
        $from = $waypoints[$segment['from_index']];
        $to = $waypoints[$segment['to_index']];
        $t = segment_fraction($from, $to, $point);
        $lat = $from['lat'] + ($to['lat'] - $from['lat']) * $t;
        $lng = $from['lng'] + ($to['lng'] - $from['lng']) * $t;
        $distance = sqrt(
            pow(($lat - $point['lat']) * $latScale, 2) + pow(($lng - $point['lng']) * $lngScale, 2)
        );

        if ($best === null || $distance < $best['distance']) {
            $best = array('index' => $index, 't' => $t, 'distance' => $distance);
        }
    }

    return $best;
}

/* ------------------------------------------------------------------ */
/* Schema + fixtures                                                   */
/* ------------------------------------------------------------------ */

run_sql($conn, "
    CREATE TABLE route_stops (
        id int(11) NOT NULL AUTO_INCREMENT,
        route_name varchar(150) NOT NULL,
        stop_name varchar(255) NOT NULL,
        latitude decimal(10,8) NOT NULL,
        longitude decimal(11,8) NOT NULL,
        sequence_order int(11) NOT NULL,
        km_marker int(11) NOT NULL DEFAULT 1,
        fare_pricing_id int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB;

    CREATE TABLE bus_trips (
        id int(11) NOT NULL AUTO_INCREMENT,
        bus_id varchar(20) NOT NULL,
        driver_id varchar(20) NOT NULL,
        route_name varchar(100) DEFAULT NULL,
        trip_date date DEFAULT NULL,
        departure_time time DEFAULT NULL,
        status enum('Scheduled','Departed','Completed','Cancelled') DEFAULT 'Scheduled',
        PRIMARY KEY (id)
    ) ENGINE=InnoDB;

    CREATE TABLE fare_pricing (
        id int(11) NOT NULL AUTO_INCREMENT,
        route_name varchar(150) NOT NULL,
        destination varchar(150) NOT NULL,
        regular_fare decimal(10,2) NOT NULL,
        student_fare decimal(10,2) NOT NULL,
        special_fare decimal(10,2) NOT NULL,
        display_order int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB;

    CREATE TABLE drivers (
        driver_id varchar(20) NOT NULL,
        full_name varchar(100) DEFAULT NULL,
        PRIMARY KEY (driver_id)
    ) ENGINE=InnoDB;

    CREATE TABLE passengers (
        id varchar(50) NOT NULL,
        type varchar(50) DEFAULT 'Regular',
        name varchar(100) DEFAULT NULL,
        personal_qr_seen tinyint(1) NOT NULL DEFAULT 0,
        passenger_code varchar(50) DEFAULT NULL,
        registration_type enum('regular','one_time') NOT NULL DEFAULT 'regular',
        PRIMARY KEY (id),
        UNIQUE KEY uniq_passenger_code (passenger_code)
    ) ENGINE=InnoDB;

    CREATE TABLE passenger_trips (
        id int(11) NOT NULL AUTO_INCREMENT,
        boarding_time datetime NOT NULL,
        fare decimal(10,2) NOT NULL DEFAULT 0.00,
        payment_status varchar(20) NOT NULL DEFAULT 'paid',
        trip_no varchar(100) DEFAULT NULL,
        passenger_id varchar(50) DEFAULT NULL,
        passenger_code varchar(50) DEFAULT NULL,
        passenger_type varchar(50) DEFAULT NULL,
        payment_method varchar(50) DEFAULT NULL,
        bus_id varchar(50) DEFAULT NULL,
        bus_no varchar(50) DEFAULT NULL,
        driver_id varchar(50) DEFAULT NULL,
        boarding_point varchar(255) DEFAULT NULL,
        destination varchar(255) DEFAULT NULL,
        selected_destination varchar(255) DEFAULT NULL,
        actual_destination varchar(255) DEFAULT NULL,
        dropoff_time datetime DEFAULT NULL,
        archived_status varchar(10) DEFAULT 'no',
        scan_location_source varchar(20) DEFAULT NULL,
        identification_source varchar(30) DEFAULT NULL,
        latitude decimal(10,7) DEFAULT NULL,
        longitude decimal(10,7) DEFAULT NULL,
        scan_latitude decimal(10,7) DEFAULT NULL,
        scan_longitude decimal(10,7) DEFAULT NULL,
        scan_recorded_at datetime DEFAULT NULL,
        scan_stop_id int(11) DEFAULT NULL,
        verified_cash_amount decimal(10,2) DEFAULT NULL,
        expected_cash decimal(10,2) DEFAULT NULL,
        actual_cash decimal(10,2) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_pt_trip_no (trip_no),
        KEY idx_pt_passenger (passenger_id)
    ) ENGINE=InnoDB;

    CREATE TABLE driver_current_location (
        id int(11) NOT NULL AUTO_INCREMENT,
        driver_id varchar(20) NOT NULL,
        bus_id varchar(20) DEFAULT NULL,
        latitude decimal(10,8) DEFAULT NULL,
        longitude decimal(11,8) DEFAULT NULL,
        speed decimal(6,2) DEFAULT NULL,
        heading decimal(6,2) DEFAULT NULL,
        updated_at datetime DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB;

    CREATE TABLE driver_location_history (
        id int(11) NOT NULL AUTO_INCREMENT,
        driver_id varchar(20) NOT NULL,
        bus_id varchar(20) DEFAULT NULL,
        latitude decimal(10,8) DEFAULT NULL,
        longitude decimal(11,8) DEFAULT NULL,
        created_at datetime DEFAULT NULL,
        sample_id varchar(90) DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB;
");

$ddl = file_get_contents(__DIR__ . '/../scripts/simulation_tables.sql');
run_sql($conn, $ddl);

/* Designated stops: one at the first waypoint, one mid-route, one at the end.
   These stored rows ARE the movement source the engine must follow. */
$stops = array(
    array('Cabanatuan to Rizal Route', 'Cabanatuan City Terminal', 15.47756100, 120.96733400, 1),
    array('Cabanatuan to Rizal Route', 'Mayapyap', 15.51648000, 120.95498400, 25),
    array('Cabanatuan to Rizal Route', 'Baluga', 15.56004300, 120.94605600, 35),
    array('Rizal to Cabanatuan Route', 'Rizal Terminal', 15.70526900, 121.09934400, 1),
);
$insertStop = $conn->prepare("INSERT INTO route_stops (route_name, stop_name, latitude, longitude, sequence_order, km_marker, fare_pricing_id) VALUES (?,?,?,?,?,1,0)");

foreach ($stops as $stop) {
    $insertStop->bind_param("ssddi", $stop[0], $stop[1], $stop[2], $stop[3], $stop[4]);
    $insertStop->execute();
}

$insertStop->close();

/* The fare matrix the passenger simulation must read instead of inventing
   fares: Mayapyap boards at the terminal only, Baluga boards at the terminal
   and Mayapyap. */
$conn->query("INSERT INTO fare_pricing (id, route_name, destination, regular_fare, student_fare, special_fare, display_order) VALUES
    (1, 'Cabanatuan to Rizal Route', 'Mayapyap', 20.00, 16.00, 14.00, 1),
    (2, 'Cabanatuan to Rizal Route', 'Baluga', 35.00, 28.00, 24.50, 2)");

$today = date('Y-m-d');
$conn->query("INSERT INTO drivers (driver_id, full_name) VALUES ('DRV-001', 'Test Driver')");
$conn->query("INSERT INTO bus_trips (id, bus_id, driver_id, route_name, trip_date, departure_time, status) VALUES
    (9001, 'Bus-1001', 'DRV-001', 'Rizal-Cabanatuan', '$today', '06:00:00', 'Departed'),
    (9002, 'Bus-1002', 'DRV-002', 'Cabanatuan-Rizal', '$today', '06:15:00', 'Scheduled'),
    (9003, 'Bus-1003', 'DRV-003', 'Cabanatuan-Rizal', '$today', '07:00:00', 'Completed'),
    (9004, 'Bus-1004', 'DRV-004', 'Unknown Corridor', '$today', '07:30:00', 'Departed')");

/* A real GPS snapshot to prove simulated reads never take it over. */
$conn->query("INSERT INTO driver_current_location (driver_id, bus_id, latitude, longitude, speed, updated_at)
    VALUES ('DRV-001', 'Bus-1001', 15.10000, 120.50000, 12.3, NOW())");

/* ------------------------------------------------------------------ */
/* Options + validation refusals                                       */
/* ------------------------------------------------------------------ */

$options = simulation_options($conn);
check('options lists the active trips', count($options) === 3);
$bus1002 = null;
foreach ($options as $option) {
    if ($option['bus_id'] === 'Bus-1002') {
        $bus1002 = $option;
    }
}
check('options resolves the route direction', $bus1002 !== null && $bus1002['route_direction'] === 'cabanatuan-rizal');

$result = simulation_control($conn, 'enable', array('bus_id' => 'Bus-9999', 'trip_id' => 9001));
check('enable refuses an unknown trip', $result['success'] === false);

$result = simulation_control($conn, 'enable', array('bus_id' => 'Bus-1003', 'trip_id' => 9003));
check('enable refuses a completed trip', $result['success'] === false && strpos($result['message'], 'not active') !== false);

$result = simulation_control($conn, 'enable', array('bus_id' => 'Bus-1002', 'trip_id' => 9001));
check('enable refuses a bus/trip mismatch', $result['success'] === false && strpos($result['message'], 'belong') !== false);

$result = simulation_control($conn, 'enable', array('bus_id' => 'Bus-1004', 'trip_id' => 9004));
check('enable refuses an unknown route', $result['success'] === false && strpos($result['message'], 'not recognized') !== false);

/* ------------------------------------------------------------------ */
/* Enable + duplicate enable                                           */
/* ------------------------------------------------------------------ */

$result = simulation_control($conn, 'enable', array('bus_id' => 'Bus-1002', 'trip_id' => 9002));
check('enable succeeds for a valid bus/trip', $result['success'] === true && $result['data']['status'] === 'idle');
check('enabled session starts at the first waypoint', abs($result['data']['latitude'] - 15.477561) < 0.000001);
$sessionId = $result['data']['session_id'];

$again = simulation_control($conn, 'enable', array('bus_id' => 'Bus-1002', 'trip_id' => 9002));
check('enable is idempotent for the same bus', $again['success'] === true && $again['data']['session_id'] === $sessionId);

/* ------------------------------------------------------------------ */
/* Passenger generation from route_stops order + fare_pricing matrix   */
/* ------------------------------------------------------------------ */

$enabled = $again['data'];
check('passenger demand defaults to normal', (string) ($enabled['passenger_demand'] ?? '') === 'normal');
check('waiting passengers are generated for the route', (int) $enabled['passengers']['waiting'] > 0);
check('passenger capacity uses the configured default without a bus record', (int) $enabled['passengers']['capacity'] === 50);
check('passengers start waiting, never onboard or alighted', (int) $enabled['passengers']['onboard'] === 0 && (int) $enabled['passengers']['alighted'] === 0);

$stopOrder = array();
foreach ($enabled['passengers']['by_stop'] as $stopRow) {
    $stopOrder[] = $stopRow['stop_name'];
}
check('waiting lists follow the route_stops order', $stopOrder === array('Cabanatuan City Terminal', 'Mayapyap', 'Baluga'));

$manifestRow = $conn->query("SELECT passenger_manifest FROM simulation_sessions WHERE id = $sessionId")->fetch_assoc();
$manifest = json_decode((string) ($manifestRow['passenger_manifest'] ?? ''), true);
$journeys = is_array($manifest) ? ($manifest['passengers'] ?? array()) : array();
check('simulated journeys are generated into the session manifest', count($journeys) > 0);

$fareValues = array(20.0, 16.0, 14.0, 35.0, 28.0, 24.5);
$faresValid = true;
$boardsBeforeDestination = true;
$waitsAtFinalStop = false;
$codes = array();
$finalSequence = 0;
foreach (simulation_load_stops($conn, 'cabanatuan-rizal') as $stopFixture) {
    $finalSequence = max($finalSequence, (int) $stopFixture['sequence_order']);
}

foreach ($journeys as $journey) {
    if (!in_array((float) $journey['fare'], $fareValues, true)) {
        $faresValid = false;
    }
    if (strpos((string) $journey['code'], 'SIM-') !== 0) {
        $faresValid = false;
    }
    if ((int) $journey['destination_sequence'] <= (int) $journey['boarding_sequence']) {
        $boardsBeforeDestination = false;
    }
    if ($finalSequence > 0 && (int) $journey['boarding_sequence'] >= $finalSequence) {
        $waitsAtFinalStop = true;
    }
    $codes[] = (string) $journey['code'];
}

check('every fare comes from the fare_pricing matrix', $faresValid);
check('every journey boards before its destination', $boardsBeforeDestination);
check('no journey waits at the final stop', $waitsAtFinalStop === false);
check('passenger codes are unique and SIM-prefixed', count($codes) === count(array_unique($codes)));
check('no real boarding rows exist before the bus moves', (int) $conn->query("SELECT COUNT(*) c FROM passenger_trips WHERE trip_no = '9002'")->fetch_assoc()['c'] === 0);
check('no real passenger accounts exist before boarding', (int) $conn->query("SELECT COUNT(*) c FROM passengers WHERE id LIKE 'SIM-%'")->fetch_assoc()['c'] === 0);

$realRows = $conn->query("SELECT COUNT(*) c FROM driver_location_history")->fetch_assoc()['c'];
check('no simulation writes to real GPS history', (int) $realRows === 0);

/* ------------------------------------------------------------------ */
/* Override + upload isolation                                         */
/* ------------------------------------------------------------------ */

$override = simulation_override_for($conn, 'Bus-1002');
check('override reports the active session', $override['active'] === true && abs($override['latitude'] - 15.477561) < 0.000001);
check('upload blocked while enabled', simulation_upload_blocked($conn, 'DRV-002', 'Bus-1002') === true);
check('upload not blocked for other drivers', simulation_upload_blocked($conn, 'DRV-001', 'Bus-1001') === false);

/* The admin Trip Dashboard reads through PDO; the shared engine must answer
   the same override there without a mysqli handle. */
$pdo = new PDO(
    'mysql:host=' . $credentials['host'] . ';dbname=' . $testDb . ';charset=utf8mb4',
    $credentials['username'],
    $credentials['password'],
    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
);
$pdoOverride = simulation_override_for_pdo($pdo, 'Bus-1002');
check('the PDO override reports the same active session',
    $pdoOverride['active'] === true && abs($pdoOverride['latitude'] - 15.477561) < 0.000001);
check('the PDO override leaves other buses alone',
    simulation_override_for_pdo($pdo, 'Bus-1001')['active'] === false);

/* ------------------------------------------------------------------ */
/* Start, tick, progress and stop arrivals                             */
/* ------------------------------------------------------------------ */

$result = simulation_control($conn, 'start', array('session_id' => $sessionId));
check('start transitions to running', $result['success'] === true && $result['data']['status'] === 'running');

/* The expected plan is the engine's actual movement source: the stored
   route_stops sequence for the direction. */
$waypoints = simulation_direction_waypoints($conn, 'cabanatuan-rizal');
check('movement path comes from the stored route_stops', count($waypoints) === 3 && abs($waypoints[1]['lat'] - 15.51648) < 0.000001);
$plan = simulation_build_segments($waypoints, 40);

/* A tick right after start sits on the terminal, so its arrival must record. */
$startTick = simulation_tick($conn, simulation_find_session($conn, $sessionId));
check('tick at the start succeeds while running', $startTick['success'] === true);
check('tick at the start records the terminal arrival', $startTick['data']['arrived_stops'] >= 1);

$terminalPassengers = $startTick['data']['passengers'];
check('boarding happens at the terminal stop', (int) $terminalPassengers['boarded'] > 0 && (int) $terminalPassengers['onboard'] > 0);
check('collected fares are positive after boarding', (float) $terminalPassengers['fare_total'] > 0);
check('boarding empties the terminal waiting queue when capacity allows', (int) ($terminalPassengers['waiting_by_sequence']['1'] ?? 0) === 0);

/* The boardings are real passenger activity: `passenger_trips` rows and
   `passengers` accounts the driver and inspector apps read. */
$terminalBoarded = (int) $terminalPassengers['boarded'];
check('boarding writes real passenger_trips rows for the session trip', (int) $conn->query("SELECT COUNT(*) c FROM passenger_trips WHERE trip_no = '9002'")->fetch_assoc()['c'] === $terminalBoarded);
check('boarded rows carry the session bus, driver, and simulation marker', (int) $conn->query("SELECT COUNT(*) c FROM passenger_trips WHERE trip_no = '9002' AND bus_no = 'Bus-1002' AND bus_id = 'Bus-1002' AND driver_id = 'DRV-002' AND scan_location_source = 'simulation' AND archived_status = 'no'")->fetch_assoc()['c'] === $terminalBoarded);
check('boarded rows carry paid cash fares from the fare_pricing matrix', (int) $conn->query("SELECT COUNT(*) c FROM passenger_trips WHERE trip_no = '9002' AND payment_status = 'paid' AND payment_method = 'Cash' AND fare IN (20.00, 16.00, 14.00, 35.00, 28.00, 24.50)")->fetch_assoc()['c'] === $terminalBoarded);
check('real SIM passenger accounts are created for the boarded riders', (int) $conn->query("SELECT COUNT(*) c FROM passengers WHERE id LIKE 'SIM-%'")->fetch_assoc()['c'] === $terminalBoarded);
check('the driver-side join resolves every simulated rider account', (int) $conn->query("SELECT COUNT(*) c FROM passenger_trips pt LEFT JOIN passengers p ON p.id = pt.passenger_id WHERE pt.trip_no = '9002' AND p.id IS NULL")->fetch_assoc()['c'] === 0);

$mayapyapBound = (int) $conn->query("SELECT COUNT(*) c FROM passenger_trips WHERE trip_no = '9002' AND destination = 'Mayapyap'")->fetch_assoc()['c'];

$events = $conn->query("SELECT stop_name FROM simulation_stop_events WHERE session_id = $sessionId ORDER BY id")->fetch_all(MYSQLI_ASSOC);
check('first stop event is the terminal', count($events) >= 1 && $events[0]['stop_name'] === 'Cabanatuan City Terminal');

/* Advance the clock to the Mayapyap landmark's closest approach on the path. */
$mayapyap = array('lat' => 15.51648000, 'lng' => 120.95498400);
$approach = closest_approach($waypoints, $plan, $mayapyap);
check('Mayapyap projects onto the stored path inside a stop radius', $approach !== null && $approach['distance'] < 150);

$targetElapsed = 0.0;
for ($i = 0; $i < $approach['index']; $i++) {
    $targetElapsed += $plan['segments'][$i]['duration_ms'];
}
$targetElapsed += $approach['t'] * $plan['segments'][$approach['index']]['duration_ms'];
$startedAt = date('Y-m-d H:i:s', time() - (int) ceil(($targetElapsed + 500) / 1000));
$conn->query("UPDATE simulation_sessions SET started_at = '$startedAt' WHERE id = $sessionId");

$result = simulation_tick($conn, simulation_find_session($conn, $sessionId));
check('tick advances the position', $result['success'] === true && $result['data']['latitude'] > 15.48);
check('tick records the Mayapyap arrival', $result['data']['arrived_stops'] >= 2);

$midPassengers = $result['data']['passengers'];
check('onboard plus alighted always equals boarded', (int) $midPassengers['onboard'] + (int) $midPassengers['alighted'] === (int) $midPassengers['boarded']);
check('boarding never exceeds the bus capacity', (int) $midPassengers['onboard'] <= (int) $midPassengers['capacity']);
check('the Mayapyap stop boarded its own waiting queue', (int) ($midPassengers['waiting_by_sequence']['25'] ?? 0) === 0);

check('every boarded rider keeps a real boarding row', (int) $conn->query("SELECT COUNT(*) c FROM passenger_trips WHERE trip_no = '9002'")->fetch_assoc()['c'] === (int) $midPassengers['boarded']);
check('passengers destined for the reached stop alight with a recorded drop-off', (int) $conn->query("SELECT COUNT(*) c FROM passenger_trips WHERE trip_no = '9002' AND destination = 'Mayapyap' AND dropoff_time IS NOT NULL")->fetch_assoc()['c'] === $mayapyapBound);
check('a session never duplicates a simulated boarding', (int) $conn->query("SELECT COUNT(*) c FROM (SELECT passenger_id FROM passenger_trips WHERE trip_no = '9002' GROUP BY passenger_id HAVING COUNT(*) > 1) t")->fetch_assoc()['c'] === 0);
check('every boarded rider reuses exactly one simulated account', (int) $conn->query("SELECT COUNT(*) c FROM passengers WHERE id LIKE 'SIM-%'")->fetch_assoc()['c'] === (int) $conn->query("SELECT COUNT(DISTINCT passenger_id) c FROM passenger_trips WHERE trip_no = '9002'")->fetch_assoc()['c']);

$events = $conn->query("SELECT stop_name FROM simulation_stop_events WHERE session_id = $sessionId ORDER BY id")->fetch_all(MYSQLI_ASSOC);
check('Mayapyap arrival is recorded', in_array('Mayapyap', array_column($events, 'stop_name'), true));

$firstTick = $result['data'];
$conn->query("UPDATE simulation_sessions SET started_at = DATE_SUB(started_at, INTERVAL 60 SECOND) WHERE id = $sessionId");
$secondTick = simulation_tick($conn, simulation_find_session($conn, $sessionId));
check('position advances monotonically with elapsed time', $secondTick['data']['progress_pct'] > $firstTick['progress_pct']);

check('duplicate arrivals are not double-recorded', (int) $conn->query("SELECT COUNT(*) c FROM simulation_stop_events WHERE session_id = $sessionId AND stop_name = 'Cabanatuan City Terminal'")->fetch_assoc()['c'] === 1);

/* ------------------------------------------------------------------ */
/* Pause / resume / reset                                              */
/* ------------------------------------------------------------------ */

$result = simulation_control($conn, 'pause', array('session_id' => $sessionId));
check('pause freezes the session', $result['success'] === true && $result['data']['status'] === 'paused');
$pausedLat = $result['data']['latitude'];

$conn->query("UPDATE simulation_sessions SET started_at = DATE_SUB(started_at, INTERVAL 120 SECOND) WHERE id = $sessionId");
$pausedTick = simulation_tick($conn, simulation_find_session($conn, $sessionId));
check('paused position does not move', abs($pausedTick['data']['latitude'] - $pausedLat) < 0.0000001);

$result = simulation_control($conn, 'resume', array('session_id' => $sessionId));
check('resume returns to running', $result['success'] === true && $result['data']['status'] === 'running');
$conn->query("UPDATE simulation_sessions SET started_at = DATE_SUB(started_at, INTERVAL 60 SECOND) WHERE id = $sessionId");
$resumedTick = simulation_tick($conn, simulation_find_session($conn, $sessionId));
check('resume continues from the paused position', $resumedTick['data']['progress_pct'] > $pausedTick['data']['progress_pct']);

$result = simulation_control($conn, 'reset', array('session_id' => $sessionId));
check('reset returns to idle at the first waypoint', $result['success'] === true && $result['data']['status'] === 'idle' && abs($result['data']['latitude'] - 15.477561) < 0.000001);
check('reset clears stop events', (int) $conn->query("SELECT COUNT(*) c FROM simulation_stop_events WHERE session_id = $sessionId")->fetch_assoc()['c'] === 0);

$resetPassengers = $result['data']['passengers'];
check('reset clears the simulated boardings for the trip', (int) $conn->query("SELECT COUNT(*) c FROM passenger_trips WHERE trip_no = '9002'")->fetch_assoc()['c'] === 0);
check('reset keeps real passenger activity outside the trip untouched', (int) $conn->query("SELECT COUNT(*) c FROM passenger_trips WHERE trip_no <> '9002'")->fetch_assoc()['c'] === 0);
check('reset rebuilds the same journeys without duplicates', (int) $conn->query("SELECT COUNT(*) c FROM passengers WHERE id LIKE 'SIM-%'")->fetch_assoc()['c'] === count(array_unique($codes)));
check('reset clears boarded progress back to waiting', (int) $resetPassengers['boarded'] === 0 && (int) $resetPassengers['waiting'] > 0);

/* ------------------------------------------------------------------ */
/* Completion                                                          */
/* ------------------------------------------------------------------ */

simulation_control($conn, 'start', array('session_id' => $sessionId));
$conn->query("UPDATE simulation_sessions SET started_at = DATE_SUB(started_at, INTERVAL 7200 SECOND) WHERE id = $sessionId");
$completedTick = simulation_tick($conn, simulation_find_session($conn, $sessionId));
check('long elapsed completes the route', $completedTick['data']['status'] === 'completed' && $completedTick['data']['progress_pct'] >= 99.9);

/* ------------------------------------------------------------------ */
/* Disable + isolation                                                 */
/* ------------------------------------------------------------------ */

$result = simulation_control($conn, 'disable', array('session_id' => $sessionId));
check('disable ends the simulation', $result['success'] === true && $result['data']['active'] === false);

$override = simulation_override_for($conn, 'Bus-1002');
check('override is inactive after disable', $override['active'] === false);
check('upload no longer blocked after disable', simulation_upload_blocked($conn, 'DRV-002', 'Bus-1002') === false);
check('the PDO override is inactive after disable',
    simulation_override_for_pdo($pdo, 'Bus-1002')['active'] === false);
$pdo = null;

$realCurrent = $conn->query("SELECT latitude, longitude FROM driver_current_location WHERE driver_id = 'DRV-001'")->fetch_assoc();
check('real GPS snapshot is untouched by the whole run', abs((float) $realCurrent['latitude'] - 15.1) < 0.000001);

$realRows = $conn->query("SELECT COUNT(*) c FROM driver_location_history")->fetch_assoc()['c'];
check('real GPS history stayed empty', (int) $realRows === 0);

/* ------------------------------------------------------------------ */
/* Unknown action + no-session tick                                    */
/* ------------------------------------------------------------------ */

$result = simulation_control($conn, 'explode', array('session_id' => $sessionId));
check('unknown control action is refused', $result['success'] === false);

$result = simulation_tick($conn, null);
check('tick without a session is refused', $result['success'] === false);

/* Clean up the test database; never leave fixtures behind. */
$server->query("DROP DATABASE IF EXISTS `$testDb`");

echo "\n$checks checks, $failures failure(s)\n";
exit($failures === 0 ? 0 : 1);

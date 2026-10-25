<?php declare(strict_types=1);

// Isolated local test router. Never opens the application database or gateway.
if (PHP_SAPI !== 'cli-server' || !getenv('REGULAR_PARTY_TEST_DB')) {
    http_response_code(403); exit;
}
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
$_SESSION = ['passenger_id' => (int) ($_GET['passenger_id'] ?? 1)];
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

/** Only translates schema introspection; all pricing and data writes are real. */
class PartyFixturePDO extends PDO
{
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (preg_match('/^SHOW COLUMNS FROM (\w+) LIKE (.+)$/i', trim($query), $m)) {
            $query = "SELECT name AS Field FROM pragma_table_info('" . $m[1] . "') WHERE name = " . $m[2];
        } elseif (preg_match('/^SHOW INDEX FROM (\w+) WHERE Key_name = (.+)$/i', trim($query), $m)) {
            $query = "SELECT name FROM sqlite_master WHERE type='index' AND name = " . $m[2];
        }
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
$db = new PartyFixturePDO('sqlite:' . getenv('REGULAR_PARTY_TEST_DB'));
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
@$db->sqliteCreateFunction('NOW', static fn() => date('Y-m-d H:i:s'));
@$db->sqliteCreateFunction('DATABASE', static fn() => 'test');
(new ReflectionProperty(App\Core\Database::class, 'instance'))->setValue(null, $db);
if (!$db->query("SELECT name FROM sqlite_master WHERE name='passengers'")->fetchColumn()) {
    $db->exec("CREATE TABLE passengers (id INTEGER PRIMARY KEY AUTOINCREMENT, full_name TEXT,
        registration_type TEXT, discount_type TEXT, type TEXT DEFAULT 'passenger', passenger_code TEXT,
        space_occupied INT, declared_id_number TEXT, declared_id_status TEXT, latitude REAL, longitude REAL);
        INSERT INTO passengers(id,full_name,registration_type,discount_type,passenger_code) VALUES
        (1,'Main Rider','regular','Regular','STD-001'), (4,'Child Rider','regular','Student','STU-004'),
        (5,'Senior Rider','regular','Senior','SEN-005'), (6,'Regular Rider','regular','Regular','STD-006'),
        (7,'Second Child','regular','Student','STU-007'), (8,'Second Senior','regular','Senior','SEN-008');
        CREATE TABLE passenger_companions (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_passenger_id INT,
        passenger_id INT, full_name TEXT, companion_type TEXT, declared_id_number TEXT, declared_id_status TEXT,
        created_at TEXT, UNIQUE(owner_passenger_id,full_name));
        INSERT INTO passenger_companions(id,owner_passenger_id,passenger_id,full_name,companion_type) VALUES
        (1,1,4,'Child Rider','child'),(2,1,5,'Senior Rider','senior'),(3,1,6,'Regular Rider','other'),
        (4,1,7,'Second Child','child'),(5,1,8,'Second Senior','senior');
        CREATE TABLE bus_trips (id INT PRIMARY KEY,route_name TEXT,status TEXT,bus_id TEXT,driver_id TEXT,trip_date TEXT);
        INSERT INTO bus_trips VALUES (10,'Route X','ongoing','Bus-1','DRV-1','2026-01-01'),
        (11,'Cabanatuan-Rizal','Ongoing','Bus-2','DRV-2', date('now'));
        CREATE TABLE buses (id INTEGER PRIMARY KEY AUTOINCREMENT,bus_id TEXT,status TEXT,operational_status TEXT,seats INT,standing_capacity INT);
        INSERT INTO buses (bus_id,status,seats,standing_capacity) VALUES ('Bus-1','Active',49,10),('Bus-2','Active',49,10);
        CREATE TABLE drivers (driver_id TEXT PRIMARY KEY,full_name TEXT);
        INSERT INTO drivers VALUES ('DRV-1','Driver One'),('DRV-2','Driver Two');
        CREATE TABLE driver_bus_assignment (id INTEGER PRIMARY KEY AUTOINCREMENT,driver_id TEXT,bus_id TEXT);
        INSERT INTO driver_bus_assignment (driver_id,bus_id) VALUES ('DRV-1','Bus-1'),('DRV-2','Bus-2');
        CREATE TABLE passenger_location_history (id INTEGER PRIMARY KEY AUTOINCREMENT,passenger_trip_id INT,
        first_latitude REAL,first_longitude REAL,first_recorded_at TEXT,latitude REAL,longitude REAL,accuracy REAL,recorded_at TEXT);
        CREATE TABLE fare_pricing (id INT PRIMARY KEY,destination TEXT,display_order INT,regular_fare REAL,
        student_fare REAL,special_fare REAL,route_name TEXT);
        INSERT INTO fare_pricing VALUES (100,'Stop A',1,0,0,0,'Route X'),(200,'Stop C',3,30,24,36,'Route X'),
        (300,'Stop D',4,60,48,72,'Route X'),
        (400,'Cabanatuan Central Terminal → Daang Sarile',1,20,16,14,'Cabanatuan-Rizal'),
        (401,'Valdefuente',2,22,17.6,15.4,'Cabanatuan-Rizal'),
        (402,'Mayapyap',3,24,19.2,16.8,'Cabanatuan-Rizal'),
        (403,'Caalibangbangan',4,26,20.8,18.2,'Cabanatuan-Rizal');
        CREATE TABLE route_stops (id INT, fare_pricing_id INT,route_name TEXT,stop_name TEXT,sequence_order INT,km_marker INT,latitude REAL,longitude REAL);
        INSERT INTO route_stops VALUES (1000,100,'Route X','Stop A',1,0,0,0),(2000,200,'Route X','Stop C',3,10,0,0),(3000,300,'Route X','Stop D',4,20,0,0),
        (4000,400,'Cabanatuan-Rizal','Cabanatuan Central Terminal → Daang Sarile',1,0,15.472229,120.969183),
        (4001,401,'Cabanatuan-Rizal','Valdefuente',2,5,15.506807,120.966642),
        (4002,402,'Cabanatuan-Rizal','Mayapyap',3,9,15.514560,120.956086),
        (4003,403,'Cabanatuan-Rizal','Caalibangbangan',4,13,15.519634,120.941532);
        CREATE TABLE simulation_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, bus_id TEXT, driver_id TEXT,
        trip_id INTEGER, route_id INTEGER, status TEXT, current_lat REAL, current_lng REAL, speed_kmh REAL,
        progress_pct REAL, updated_at TEXT, last_tick_at TEXT, created_at TEXT);
        CREATE TABLE driver_location_history (id INTEGER PRIMARY KEY AUTOINCREMENT, driver_id TEXT, bus_id TEXT,
        latitude REAL, longitude REAL, speed REAL, created_at TEXT);
        CREATE TABLE driver_current_location (id INTEGER PRIMARY KEY AUTOINCREMENT, driver_id TEXT, bus_id TEXT,
        latitude REAL, longitude REAL, speed REAL, created_at TEXT, updated_at TEXT);
        CREATE TABLE passenger_trips (id INTEGER PRIMARY KEY AUTOINCREMENT,passenger_id INT,bus_no TEXT,trip_no INT,
        driver_id TEXT,passenger_type TEXT,boarding_point TEXT,boarding_stop_id INT,boarding_km_marker INT,
        destination TEXT,destination_id INT,selected_destination TEXT,selected_destination_id INT,selected_km_marker INT,
        estimated_fare REAL,fare REAL,base_fare REAL,discount_amount REAL,payment_method TEXT,payment_status TEXT,
        trip_status TEXT,latitude REAL,longitude REAL,boarding_time TEXT,dropoff_time TEXT,identification_source TEXT,
        declared_id_number TEXT,declared_id_status TEXT,submission_token TEXT UNIQUE,companion_of INT,companion_profile_id INT,
        cash_amount REAL,verified_cash_amount REAL,gcash_amount REAL,paymongo_intent_id TEXT,paymongo_payment_id TEXT,
        paymongo_qr_expires_at TEXT,paymongo_qr_image TEXT,paymongo_history TEXT,paymongo_client_key TEXT,
        actual_stop_id INT,actual_km_marker INT,planned_dropoff_lat REAL,planned_dropoff_lng REAL,planned_dropoff_name TEXT,
        planned_dropoff_set_at TEXT,destination_near_alerted INT,destination_arrival_alerted INT,destination_passed_alerted INT,
        destination_passed_at TEXT,destination_last_distance_m REAL,destination_distance_trend INT,
        continuation_of_trip_id INT,continuation_token TEXT,carried_balance REAL,paid_amount REAL);
        CREATE UNIQUE INDEX uq_passenger_trips_continuation_token ON passenger_trips(continuation_token);
        CREATE UNIQUE INDEX uq_passenger_trips_continuation_of ON passenger_trips(continuation_of_trip_id);
        INSERT INTO passenger_trips(id,passenger_id,bus_no,trip_no,passenger_type,boarding_point,boarding_stop_id,
        destination,destination_id,selected_destination,selected_destination_id,fare,estimated_fare,base_fare,discount_amount,
        payment_method,payment_status,trip_status) VALUES
        (20,1,'Bus-1',10,'Regular','Stop A',1000,'Stop C',200,'Stop C',200,30,30,30,0,'gcash','pending','In Progress');");
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/* The fixture owns the companion tables already; the real schema ensure uses
   MySQL-only syntax (SELECT DATABASE(), ENUM) that SQLite cannot parse. This
   stub is declared before CompanionController loads CompanionSchema.php. */
if (!function_exists('ensureCompanionSchema')) {
    function ensureCompanionSchema(PDO $conn) {}
}

if ($path === '/passenger/trip/select-destination') {
    require $_SERVER['DOCUMENT_ROOT'] . '/passenger/controllers/PassengerHome/SelectDestinationController.php';
    exit;
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/controllers/PassengerHome/CompanionController.php';
if ($path === '/passenger/companions') {
    echo json_encode(['success' => true, 'profiles' => CompanionService::profiles($db, 1)]); exit;
}
if ($path === '/passenger/companions/party') {
    $input = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : json_decode(file_get_contents('php://input'), true);
    $result = CompanionController::partyRequest($db, 1, $_SERVER['REQUEST_METHOD'], $input);
    http_response_code($result['http_status']); unset($result['http_status']); echo json_encode($result); exit;
}
if ($path === '/_test/charge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->exec('UPDATE passenger_trips SET carried_balance=5 WHERE id=20');
    echo json_encode(['success' => true]); exit;
}
if ($path === '/_test/legacy' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->exec("DELETE FROM passenger_trips WHERE companion_of=20;
        UPDATE passenger_trips SET fare=NULL,base_fare=NULL,discount_amount=NULL,destination='',destination_id=NULL,
        selected_destination='Stop C',selected_destination_id=200,estimated_fare=30,carried_balance=0 WHERE id=20");
    echo json_encode(['success' => true]); exit;
}
if ($path === '/_test/before-destination' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->exec("DELETE FROM passenger_trips WHERE companion_of=20;
        UPDATE passenger_trips SET fare=NULL,estimated_fare=NULL,base_fare=NULL,discount_amount=NULL,
        destination=NULL,destination_id=NULL,selected_destination=NULL,selected_destination_id=NULL,carried_balance=0 WHERE id=20;
        DELETE FROM passenger_trip_state");
    echo json_encode(['success' => true]); exit;
}
if ($path === '/_test/payment') {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/passenger/controllers/PaymentMongo/CheckoutController.php';
    $controller = (new ReflectionClass(CheckoutController::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty(CheckoutController::class, 'conn'))->setValue($controller, $db);
    (new ReflectionProperty(CheckoutController::class, 'passengerId'))->setValue($controller, 1);
    $trip = (new ReflectionMethod(CheckoutController::class, 'loadOwnedTrip'))->invoke($controller, 20);
    $amount = (new ReflectionMethod(CheckoutController::class, 'amountCentavos'))->invoke($controller, $trip);
    echo json_encode(['amount_centavos' => $amount, 'primary_fare' => (float) $trip['fare'],
        'companion_count' => count(RegularPartyService::travellerRows($db, 20))]); exit;
}
if ($path === '/passenger/scan/store') {
    if (!function_exists('passenger_rate_limit')) {
        function passenger_rate_limit(...$args) { return true; }
        function passenger_client_ip() { return '127.0.0.1'; }
        function passenger_qr_scan_debug_enabled() { return false; }
        function passenger_qr_scan_log(array $entry) {}
    }
    require $_SERVER['DOCUMENT_ROOT'] . '/passenger/controllers/PassengerHome/ScanController.php';
    exit;
}
if ($path === '/_test/trip-row') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM passenger_trips WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['row' => $stmt->fetch() ?: null]); exit;
}
if ($path === '/passenger/api/destinations') {
    $_GET['action'] = 'destinations';
    require $_SERVER['DOCUMENT_ROOT'] . '/passenger/controllers/Api/DestinationController.php';
    exit;
}
if ($path === '/_test/seed-boarding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->exec('DELETE FROM passenger_trips WHERE id = 30');
    $db->exec("INSERT INTO passenger_trips (id, passenger_id, bus_no, trip_no, boarding_point, boarding_stop_id, boarding_km_marker, payment_method, payment_status, trip_status)
        VALUES (30, 1, 'Bus-2', 11, 'Valdefuente', 4001, 5, 'cash', 'pending', 'In Progress')");
    echo json_encode(['success' => true, 'trip_id' => 30]); exit;
}
if ($path === '/_test/bus-position' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $db->exec('DELETE FROM simulation_sessions; DELETE FROM driver_location_history; DELETE FROM driver_current_location');
    if (isset($input['lat'], $input['lng']) && is_numeric($input['lat']) && is_numeric($input['lng'])) {
        $when = date('Y-m-d H:i:s');
        $stmt = $db->prepare("INSERT INTO simulation_sessions (bus_id, driver_id, trip_id, route_id, status, current_lat, current_lng, speed_kmh, progress_pct, updated_at, last_tick_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            (string) ($input['bus_id'] ?? 'Bus-2'),
            (string) ($input['driver_id'] ?? 'DRV-2'),
            (int) ($input['trip_id'] ?? 11),
            1,
            'running',
            (float) $input['lat'],
            (float) $input['lng'],
            40.0,
            25.0,
            $when,
            $when,
            $when,
        ]);
    }
    echo json_encode(['success' => true]); exit;
}
http_response_code(404); echo json_encode(['success' => false]);

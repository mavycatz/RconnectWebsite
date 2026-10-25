<?php declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require_once __DIR__ . '/../controllers/PassengerHome/CompanionController.php';

$db = class_exists('Pdo\\Sqlite') ? new Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (method_exists($db, 'createFunction')) {
    $db->createFunction('NOW', static fn() => date('Y-m-d H:i:s'));
} else {
    $db->sqliteCreateFunction('NOW', static fn() => date('Y-m-d H:i:s'));
}
$property = new ReflectionProperty(App\Core\Database::class, 'instance');
$property->setValue(null, $db);
$db->exec("CREATE TABLE passengers (id INTEGER PRIMARY KEY AUTOINCREMENT, full_name TEXT,
    registration_type TEXT, discount_type TEXT, type TEXT DEFAULT 'passenger', passenger_code TEXT,
    space_occupied INT, declared_id_number TEXT, declared_id_status TEXT, latitude REAL, longitude REAL);
    INSERT INTO passengers(id, full_name, registration_type, discount_type) VALUES
    (1, 'Main Rider', 'regular', 'Regular'), (2, 'Other Owner', 'regular', 'Regular'), (3, 'One Time', 'one_time', 'Regular');
    CREATE TABLE passenger_companions (id INTEGER PRIMARY KEY AUTOINCREMENT,
    owner_passenger_id INT, passenger_id INT, full_name TEXT, companion_type TEXT,
    declared_id_number TEXT, declared_id_status TEXT, created_at TEXT, UNIQUE(owner_passenger_id, full_name));
    CREATE TABLE bus_trips (id INT PRIMARY KEY, route_name TEXT, status TEXT);
    CREATE TABLE drivers (driver_id TEXT, full_name TEXT);
    INSERT INTO bus_trips VALUES (10, 'Route X', 'ongoing');
    CREATE TABLE fare_pricing (id INT PRIMARY KEY, destination TEXT, display_order INT,
        regular_fare REAL, student_fare REAL, special_fare REAL, route_name TEXT);
    INSERT INTO fare_pricing VALUES (100, 'Stop A', 1, 0, 0, 0, 'Route X'), (200, 'Stop C', 3, 30, 24, 36, 'Route X');
    CREATE TABLE route_stops (id INT, fare_pricing_id INT);
    CREATE TABLE passenger_trips (id INTEGER PRIMARY KEY AUTOINCREMENT, passenger_id INT,
        bus_no TEXT, trip_no INT, driver_id TEXT, passenger_type TEXT, boarding_point TEXT,
        boarding_stop_id INT, boarding_km_marker INT, destination TEXT, destination_id INT,
        selected_destination TEXT, selected_destination_id INT, selected_km_marker INT,
        estimated_fare REAL, fare REAL, base_fare REAL, discount_amount REAL,
        payment_method TEXT, payment_status TEXT, trip_status TEXT, latitude REAL, longitude REAL,
        boarding_time TEXT, dropoff_time TEXT, identification_source TEXT, declared_id_number TEXT,
        declared_id_status TEXT, submission_token TEXT UNIQUE, companion_of INT, companion_profile_id INT,
        cash_amount REAL, verified_cash_amount REAL, paymongo_intent_id TEXT, paymongo_qr_expires_at TEXT,
        paymongo_history TEXT, verified_at TEXT, verified_by TEXT);
    ");
$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
}
function request(PDO $db, int $owner, string $method, array $input): array {
    return CompanionController::partyRequest($db, $owner, $method, $input);
}
$empty = request($db, 1, 'GET', []);
check($empty['success'] && $empty['revision'] === 0 && $empty['companions'] === [], 'Draft read');
$draft = request($db, 1, 'POST', ['revision' => 0, 'companions' => [['full_name' => 'Child Rider', 'companion_type' => 'child']]]);
check($draft['success'] && $draft['revision'] === 1, 'Draft request persists');
check(request($db, 1, 'GET', [])['companions'] === $draft['companions'], 'Draft request restores identities');
check(request($db, 2, 'GET', [])['companions'] === [], 'Owner scoping');
check(request($db, 2, 'POST', ['revision' => 0, 'companions' => $draft['companions']])['http_status'] === 403, 'Foreign profile denied');
check(request($db, 0, 'GET', [])['http_status'] === 401, 'Missing authentication');
check(request($db, 3, 'GET', [])['http_status'] === 403, 'One-Time route isolation');
check(request($db, 1, 'POST', ['revision' => 0, 'companions' => []])['code'] === 'STALE_PARTY', 'Stale request rejected');
check(request($db, 1, 'POST', ['revision' => 1, 'companions' => array_fill(0, 6, ['full_name' => 'Rider', 'companion_type' => 'other'])])['http_status'] === 422, 'Maximum enforced');
check(request($db, 1, 'POST', ['revision' => 1, 'companions' => array_merge($draft['companions'], $draft['companions'])])['code'] === 'DUPLICATE_COMPANION', 'Duplicates rejected');
$quote = request($db, 1, 'GET', ['trip_no' => 10, 'boarding_point' => 'Stop A', 'destination' => 'Stop C', 'destination_id' => 200]);
check($quote['quote_available'] && $quote['party_total'] === 54.0, 'Primary 30 plus child 24 is 54');
check($quote['breakdown']['lines'][1]['type_label'] === 'Child', 'Declared type preserved in display');
$seniorQuote = RegularPartyService::quote($db, 1, [['full_name' => 'Senior Rider', 'companion_type' => 'senior']], ['trip_no' => 10, 'boarding_point' => 'Stop A', 'destination' => 'Stop C', 'destination_id' => 200]);
check($seniorQuote['party_total'] === 54.0 && $seniorQuote['breakdown']['lines'][1]['discount_amount'] === 6.0, 'Senior without ID receives one engine discount');
$regularQuote = RegularPartyService::quote($db, 1, [['full_name' => 'Regular Rider', 'companion_type' => 'other']], ['trip_no' => 10, 'boarding_point' => 'Stop A', 'destination' => 'Stop C', 'destination_id' => 200]);
check($regularQuote['party_total'] === 60.0 && $regularQuote['breakdown']['lines'][1]['discount_amount'] === 0.0, 'Regular not discounted');
check(!RegularPartyService::quote($db, 1, [], [])['quote_available'], 'Missing route is unavailable');
check(!RegularPartyService::quote($db, 1, [], ['trip_no' => 999, 'boarding_point' => 'Missing', 'destination' => 'Missing'])['quote_available'], 'Unknown route is unavailable');
$db->exec("INSERT INTO passengers(id,full_name,discount_type,registration_type,passenger_code) VALUES (4,'Child Rider','Student','regular','STU-004');
    UPDATE passenger_companions SET passenger_id = 4 WHERE full_name = 'Child Rider';
    INSERT INTO passenger_trips(id, passenger_id, trip_no, boarding_point, destination, destination_id,
        fare, base_fare, discount_amount, payment_method, payment_status, trip_status)
        VALUES (20, 1, 10, 'Stop A', 'Stop C', 200, 30, 30, 0, 'gcash', 'pending', 'In Progress')");
$attached = request($db, 1, 'POST', ['trip_id' => 20, 'revision' => 0, 'companions' => $draft['companions']]);
check($attached['success'] && $attached['mode'] === 'trip' && $attached['party_total'] === 54.0, 'Trip-owned party total');
check($attached['online_amount_centavos'] === 5400, 'Checkout amount agrees');
check(request($db, 1, 'GET', [])['mode'] === 'trip', 'Reopen discovers active party instead of draft');
check(request($db, 1, 'POST', ['revision' => 1, 'companions' => []])['code'] === 'ACTIVE_TRIP', 'Late draft write cannot overwrite newly boarded party');
$repeat = request($db, 1, 'POST', ['trip_id' => 20, 'revision' => 1, 'companions' => $attached['companions']]);
check($repeat['success'] && count($repeat['companions']) === 1, 'Identical party retry');
check((int) $db->query('SELECT COUNT(*) FROM passenger_trips WHERE companion_of = 20')->fetchColumn() === 1, 'No duplicate trip');
$edit = $attached['companions']; $edit[0]['companion_type'] = 'other';
$changed = request($db, 1, 'POST', ['trip_id' => 20, 'revision' => 1, 'companions' => $edit]);
check($changed['success'] && $changed['party_total'] === 60.0, 'Type edit reprices own trip');
check((float) $db->query('SELECT fare FROM passenger_trips WHERE id = 20')->fetchColumn() === 30.0, 'Primary remains individual fare');
$bad = $changed['companions']; $bad[0]['companion_type'] = 'senior';
$db->exec("CREATE TRIGGER reject_party_edit BEFORE UPDATE ON passenger_trips WHEN NEW.companion_of = 20 BEGIN SELECT RAISE(ABORT, 'fixture rollback'); END");
try {
    RegularPartyService::save($db, 1, $bad, 2, 20);
    throw new RuntimeException('Expected simulated persistence failure');
} catch (PDOException $e) {
    check(str_contains($e->getMessage(), 'fixture rollback'), 'Injected update failure surfaces');
}
check(request($db, 1, 'GET', ['trip_id' => 20])['revision'] === 2, 'Failed edit rolls back revision');
check(request($db, 1, 'GET', ['trip_id' => 20])['party_total'] === 60.0, 'Failed edit rolls back fares');
$db->exec('DROP TRIGGER reject_party_edit');
$db->exec("UPDATE passenger_trips SET paymongo_intent_id='pi_active',paymongo_qr_expires_at=datetime('now','+1 hour') WHERE id=20");
check(request($db, 1, 'POST', ['trip_id' => 20, 'revision' => 2, 'companions' => []])['code'] === 'CHECKOUT_ACTIVE', 'Issued QR cannot silently become a different amount');
$db->exec("UPDATE passenger_trips SET paymongo_intent_id=NULL,paymongo_qr_expires_at=NULL WHERE id=20");
check(request($db, 2, 'GET', ['trip_id' => 20])['http_status'] === 404, 'Foreign trip denied');
$db->exec("UPDATE passengers SET discount_type = 'Senior' WHERE full_name = 'Child Rider'");
check(request($db, 1, 'GET', ['trip_id' => 20])['breakdown']['lines'][1]['type_label'] === 'Regular', 'Trip declaration wins over changed profile');
$db->exec("INSERT INTO passengers(id,full_name,type) VALUES (99,'Cargo','other');
    INSERT INTO passenger_trips(passenger_id,companion_of,fare) VALUES (99,20,900)");
check(request($db, 1, 'GET', ['trip_id' => 20])['party_total'] === 60.0, 'Cargo never becomes traveller fare');
require_once __DIR__ . '/../services/PaymongoReconciler.php';
check(PaymongoReconciler::tripAmountCentavosParty($db, RegularPartyService::ownedTrip($db, 1, 20)) === 6000, 'Payment reconciliation excludes cargo consistently');
$db->exec("UPDATE passenger_trips SET payment_status = 'paid' WHERE companion_of = 20 AND passenger_id <> 99");
check(request($db, 1, 'POST', ['trip_id' => 20, 'revision' => 2, 'companions' => []])['code'] === 'PARTY_SETTLED', 'Settled companion cannot be deleted');
$db->exec("UPDATE passenger_trips SET payment_status = 'pending' WHERE companion_of = 20");
$removed = request($db, 1, 'POST', ['trip_id' => 20, 'revision' => 2, 'companions' => []]);
check($removed['success'] && $removed['party_total'] === 30.0 && $removed['companions'] === [], 'Remove party returns solo total');
check((int) $db->query('SELECT COUNT(*) FROM passenger_trips WHERE passenger_id = 99')->fetchColumn() === 1, 'Cargo records untouched');
check(request($db, 1, 'GET', ['trip_id' => 20])['companions'] === [], 'Trip-owned read does not merge saved draft');
$db->exec("UPDATE passenger_trips SET trip_status = 'Completed' WHERE id = 20");
check(request($db, 1, 'POST', ['trip_id' => 20, 'revision' => 3, 'companions' => []])['code'] === 'PARTY_SETTLED', 'Completed trip immutable');
$db->exec("CREATE TRIGGER reject_board BEFORE UPDATE ON passenger_trips WHEN NEW.companion_of = 21 BEGIN SELECT RAISE(ABORT, 'board rollback'); END");
$db->beginTransaction();
try {
    $db->exec("INSERT INTO passenger_trips(id,passenger_id,trip_no,boarding_point,trip_status,payment_method) VALUES (21,1,10,'Stop A','In Progress','gcash')");
    RegularPartyService::boardDraft($db, 1, 21);
    throw new RuntimeException('Expected failed boarding fixture');
} catch (PDOException $e) {
    $db->rollBack();
    check(str_contains($e->getMessage(), 'board rollback'), 'Boarding failure surfaces');
}
check((int) $db->query('SELECT COUNT(*) FROM passenger_trips WHERE id = 21')->fetchColumn() === 0, 'Primary creation rolled back with companions');
check(count(RegularPartyService::readDraft($db, 1)['companions']) === 1, 'Failed boarding retains selection');
$db->exec('DROP TRIGGER reject_board');
$db->beginTransaction();
$db->exec("INSERT INTO passenger_trips(id,passenger_id,trip_no,boarding_point,trip_status,payment_method) VALUES (21,1,10,'Stop A','In Progress','gcash')");
$boarded = RegularPartyService::boardDraft($db, 1, 21);
check(count($boarded['companions']) === 1 && !$boarded['quote_available'], 'Boarded party retained with unavailable quote until destination');
check(count(RegularPartyService::boardDraft($db, 1, 21)['companions']) === 1, 'Repeated board does not duplicate');
$db->commit();
check(RegularPartyService::readDraft($db, 1)['companions'] === [], 'Draft consumed after commit');
$db->exec("CREATE TRIGGER reject_destination BEFORE UPDATE ON passenger_trips WHEN NEW.companion_of = 21 BEGIN SELECT RAISE(ABORT, 'destination rollback'); END");
$db->beginTransaction();
try {
    $db->exec("UPDATE passenger_trips SET destination='Stop C',destination_id=200,fare=30,base_fare=30,discount_amount=0 WHERE id=21");
    RegularPartyService::repriceTrip($db, 1, 21);
    throw new RuntimeException('Expected failed destination fixture');
} catch (PDOException $e) {
    $db->rollBack();
    check(str_contains($e->getMessage(), 'destination rollback'), 'Companion destination failure surfaces');
}
check($db->query('SELECT destination FROM passenger_trips WHERE id=21')->fetchColumn() === null, 'Primary destination rolls back with failed party update');
$db->exec('DROP TRIGGER reject_destination');
$db->beginTransaction();
$db->exec("UPDATE passenger_trips SET destination='Stop C',destination_id=200,fare=30,base_fare=30,discount_amount=0 WHERE id=21");
$repriced = RegularPartyService::repriceTrip($db, 1, 21);
$db->commit();
check($repriced['party_total'] === 54.0, 'Destination locking prices committed child party to 54');
check((float) $db->query('SELECT SUM(fare) FROM passenger_trips WHERE id=21 OR companion_of=21')->fetchColumn() === 54.0, 'Individual persisted amounts sum to quote');
check((float) $db->query('SELECT discount_amount FROM passenger_trips WHERE companion_of=21')->fetchColumn() === 6.0, 'Persisted child has exactly one discount');
check((float) $db->query('SELECT estimated_fare FROM passenger_trips WHERE companion_of=21')->fetchColumn() === 24.0, 'Companion estimate remains individual');
$balanceTrip = RegularPartyService::ownedTrip($db, 1, 21);
$balanceRows = RegularPartyService::travellerRows($db, 21);
$balanceTrip['payment_method'] = 'hybrid'; $balanceTrip['verified_cash_amount'] = 10;
$balanceRows[0]['verified_cash_amount'] = 4;
$balance = RegularPartyService::storedQuote($db, $balanceTrip, $balanceRows);
check($balance['party_total'] === 54.0 && $balance['online_amount_centavos'] === 4000, 'Full party total distinct from verified hybrid balance');
$balanceRows[0]['payment_status'] = 'paid';
check(RegularPartyService::storedQuote($db, $balanceTrip, $balanceRows)['online_amount_centavos'] === 2000, 'Previously paid companion not charged again');
check(RegularPartyService::read($db, 1)['party_total'] === 54.0, 'Reopen recovers destination and complete party amount');
require_once __DIR__ . '/../models/TripDashboard/Trip.php';
$history = (new Trip($db))->getByPassenger(1);
check(count($history) === 2, 'History groups two primary journeys, not traveller rows');
$partyTrip = array_values(array_filter($history, static fn(array $trip): bool => (int) $trip['id'] === 21))[0];
check($partyTrip['party_total'] === 54.0 && $partyTrip['companion_count'] === 1, 'History retains complete party fare');
check($partyTrip['breakdown']['lines'][1]['type_label'] === 'Child', 'History retains declared Child label');
$db->exec("UPDATE fare_pricing SET regular_fare=999,student_fare=998 WHERE id=200;
    UPDATE passengers SET discount_type='Senior' WHERE id=1;
    UPDATE passenger_trips SET trip_status='Completed',dropoff_time=NOW() WHERE id=21 OR companion_of=21;");
$completed = RegularPartyService::read($db, 1, 21);
check($completed['party_total'] === 54.0, 'Completed history does not reprice from current fare table');
check($completed['breakdown']['lines'][0]['type_label'] === 'Regular', 'Boarded primary type does not follow later account edits');
require_once __DIR__ . '/../utils/i18n.php';
$_SESSION = ['passenger_id' => 1]; $_GET = ['id' => 21];
ob_start(); include __DIR__ . '/../views/TripDashboad/trip_modal.php'; $html = ob_get_clean();
check(str_contains($html, 'Child Rider') && str_contains($html, '₱54.00'), 'Actual receipt renders passenger and combined total');
check(str_contains($html, 'Base fare') && str_contains($html, 'Discount'), 'Actual receipt shows individual base and discount');
RegularPartyService::markTravellersPaid($db, 20, 'fixture');
check($db->query('SELECT payment_status FROM passenger_trips WHERE passenger_id=99')->fetchColumn() !== 'paid', 'Passenger payment does not settle cargo');
RegularPartyService::markTravellersPaid($db, 21, 'fixture');
check($db->query('SELECT payment_status FROM passenger_trips WHERE companion_of=21')->fetchColumn() === 'paid', 'Passenger payment settles linked travellers');
echo "$checks regular party request checks passed.\n";

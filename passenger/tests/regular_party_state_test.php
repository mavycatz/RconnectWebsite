<?php declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require_once __DIR__ . '/../services/Companion/RegularPartyService.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE passengers (id INT PRIMARY KEY, registration_type TEXT);
    INSERT INTO passengers VALUES (1, 'regular'), (2, 'regular'), (3, 'one_time');
    CREATE TABLE bus_trips (id INT, status TEXT);
    CREATE TABLE passenger_trips (id INT, passenger_id INT, trip_no INT, companion_of INT, dropoff_time TEXT, trip_status TEXT);
    CREATE TABLE passenger_companions (id INTEGER PRIMARY KEY AUTOINCREMENT,
        owner_passenger_id INT, full_name TEXT, companion_type TEXT,
        declared_id_number TEXT, UNIQUE(owner_passenger_id, full_name));");
$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
}
function rejected(callable $call, string $code): void {
    try { $call(); } catch (RegularPartyException $e) {
        check($e->errorCode === $code, 'Expected rejection ' . $code);
        return;
    }
    throw new RuntimeException('Expected rejection ' . $code);
}

check(RegularPartyService::readDraft($db, 1) === ['revision' => 0, 'companions' => []], 'New draft is empty');
$party = RegularPartyService::saveDraft($db, 1, [['full_name' => 'Kid Rider', 'companion_type' => 'child']], 0);
check($party['revision'] === 1, 'Revision advances');
check($party['companions'][0]['profile_id'] > 0, 'Saved stable profile identity');
check(RegularPartyService::readDraft($db, 1) === $party, 'Selection survives restore');
check(RegularPartyService::readDraft($db, 2)['companions'] === [], 'Other owner sees no selection');
rejected(fn() => RegularPartyService::saveDraft($db, 2, $party['companions'], 0), 'INVALID_PROFILE');
rejected(fn() => RegularPartyService::readDraft($db, 0), 'AUTH_REQUIRED');
rejected(fn() => RegularPartyService::readDraft($db, 3), 'REGULAR_ONLY');
rejected(fn() => RegularPartyService::saveDraft($db, 1, [], 0), 'STALE_PARTY');
check(RegularPartyService::saveDraft($db, 1, $party['companions'], 1) === $party, 'Accepted identical write is idempotent');
rejected(fn() => RegularPartyService::saveDraft($db, 1, array_fill(0, 6, ['full_name' => 'Too Many', 'companion_type' => 'other']), 1), 'INVALID_COMPANIONS');
rejected(fn() => RegularPartyService::saveDraft($db, 1, [
    ['full_name' => 'New Person', 'companion_type' => 'other'],
    ['full_name' => ' new   person ', 'companion_type' => 'child'],
], 1), 'DUPLICATE_COMPANION');
check((int) $db->query('SELECT COUNT(*) FROM passenger_companions')->fetchColumn() === 1, 'Rejected selection rolls back new profiles');
rejected(fn() => RegularPartyService::saveDraft($db, 1, [['full_name' => 'Person', 'companion_type' => 'invalid']], 1), 'INVALID_COMPANIONS');
$empty = RegularPartyService::saveDraft($db, 1, [], 1);
check($empty === ['revision' => 2, 'companions' => []], 'Empty selection persists with revision');
check(RegularPartyService::readDraft($db, 1) === $empty, 'Empty selection restores');
$senior = RegularPartyService::saveDraft($db, 1, [['full_name' => 'Senior Rider', 'companion_type' => 'senior']], 2);
check($senior['companions'][0]['declared_id_number'] === '', 'Senior selection does not require ID');
echo "$checks regular party state checks passed.\n";

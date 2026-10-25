<?php declare(strict_types=1);

/**
 * BusQrBackfillService checks on SQLite: missing detection, idempotent
 * creation, valid-credential preservation, and repeat safety.
 */

require_once __DIR__ . '/../services/Bus/BusQrService.php';
require_once __DIR__ . '/../services/Bus/BusQrBackfillService.php';

use Admin\Services\Bus\BusQrBackfillService;
use Admin\Services\Bus\BusQrService;

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
$db->exec('CREATE TABLE buses (id INTEGER PRIMARY KEY AUTOINCREMENT, bus_id TEXT, plate_number TEXT, unit_number TEXT)');
$db->exec("INSERT INTO buses (bus_id, plate_number, unit_number) VALUES ('BUS-001', 'ABC 1234', '1201')");
$db->exec("INSERT INTO buses (bus_id, plate_number, unit_number) VALUES ('BUS-002', 'XYZ 789', '')");
$db->exec("INSERT INTO buses (bus_id, plate_number, unit_number) VALUES ('BUS-003', 'DEF 456', '1403')");

BusQrService::ensureSchema($db);
$qr = new BusQrService($db);
$service = new BusQrBackfillService($db);

check(count($service->missing()) === 3, 'All buses are missing credentials initially');

$dry = $service->run('https://rconnect.example', 'superadmin', true);
check($dry['created'] === 0 && $dry['missing_before'] === 3, 'A dry run reports without creating');
check(count($service->missing()) === 3, 'A dry run changes nothing');

/* One valid credential already exists and must be preserved. */
$existing = $qr->ensureForBus(2, 'BUS-002', 'https://rconnect.example', 'superadmin');

$first = $service->run('https://rconnect.example', 'superadmin');
check($first['created'] === 2, 'Only the buses missing a credential are prepared');
check($first['missing_after'] === 0, 'Nothing is missing after the run');

$preserved = $qr->activeFor(2);
check($preserved !== null && $preserved['token'] === $existing['token'], 'Existing valid credentials are preserved');

$busOne = $qr->activeFor(1);
check($busOne !== null && $busOne['status'] === BusQrService::STATUS_ACTIVE, 'New credentials are active');
check(strpos((string)$busOne['payload_url'], 'BUS-001') !== false, 'New payloads point at the right bus');

$second = $service->run('https://rconnect.example', 'superadmin');
check($second['created'] === 0, 'A second run creates nothing');
check($service->missing() === [], 'Repeat runs are safe');

$allTokens = $db->query('SELECT token FROM bus_qr_credentials')->fetchAll(PDO::FETCH_COLUMN);
check(count($allTokens) === count(array_unique($allTokens)), 'No duplicate credentials exist');
check(count($allTokens) === 3, 'Exactly one credential per bus exists');

echo "$checks bus QR backfill checks passed.\n";

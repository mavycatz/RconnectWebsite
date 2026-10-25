<?php declare(strict_types=1);

/**
 * BusDuplicates checks on SQLite: plate normalization, self-exclusion,
 * archived buses included, and non-matches.
 */

require_once __DIR__ . '/../services/Bus/BusDuplicates.php';

use Admin\Services\Bus\BusDuplicates;

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
$db->exec('CREATE TABLE buses (id INTEGER PRIMARY KEY AUTOINCREMENT, bus_id TEXT, plate_number TEXT, status TEXT)');
$db->exec("INSERT INTO buses (bus_id, plate_number, status) VALUES ('BUS-001', 'ABC 1234', 'Active')");
$db->exec("INSERT INTO buses (bus_id, plate_number, status) VALUES ('BUS-002', 'XYZ 789', 'Archived')");

$service = new BusDuplicates($db);

check(BusDuplicates::normalizePlate('abc 1234') === 'ABC1234', 'Case and spaces are normalized away');
check(BusDuplicates::normalizePlate(' ABC-1234 ') === 'ABC1234', 'Punctuation and padding are normalized away');
check(BusDuplicates::normalizePlate('AbC_12.34') === 'ABC1234', 'Dots and underscores are normalized away');

$match = $service->find('abc-1234');
check(count($match) === 1, 'A normalized match is found');
check($match[0]['bus_id'] === 'BUS-001', 'The match names the conflicting Bus ID');
check($match[0]['plate'] === 'ABC 1234', 'The stored plate is returned for review');

check($service->find('ABC 1234', 1) === [], 'The bus being edited is excluded from its own check');
check(count($service->find('ABC 1234', 2)) === 1, 'A different exclusion does not hide the match');

$archived = $service->find('xyz 789');
check(count($archived) === 1 && $archived[0]['status'] === 'Archived', 'Archived buses are still reported as possible duplicates');

check($service->find('ZZZ 000') === [], 'Non-matching plates report nothing');
check($service->find('AB') === [], 'Too-short plates are ignored');

echo "$checks bus duplicate checks passed.\n";

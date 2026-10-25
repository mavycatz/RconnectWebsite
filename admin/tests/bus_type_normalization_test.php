<?php declare(strict_types=1);

/**
 * Bus type normalization checks on in-memory SQLite: configured types are kept
 * (canonicalized case-insensitively), retired types are blanked, dry runs and
 * re-runs change nothing.
 */

if (!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === '') {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once __DIR__ . '/../migrations/normalize_bus_types.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function bus_types_test_db(): PDO
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE buses (id INTEGER PRIMARY KEY AUTOINCREMENT, bus_id TEXT, bus_type TEXT)');

    return $db;
}

function bus_types_seed(PDO $db, string $busId, ?string $type): void
{
    $stmt = $db->prepare('INSERT INTO buses (bus_id, bus_type) VALUES (?, ?)');
    $stmt->execute([$busId, $type]);
}

function bus_types_map(PDO $db): array
{
    $map = [];
    foreach ($db->query('SELECT bus_id, bus_type FROM buses ORDER BY bus_id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(string)$row['bus_id']] = $row['bus_type'] === null ? null : (string)$row['bus_type'];
    }

    return $map;
}

check(admin_allowed_bus_types() === ['HINO Bus', 'Higer Bus'], 'The allowed list is exactly the two configured types');

$db = bus_types_test_db();
bus_types_seed($db, 'BUS-2001', 'HINO Bus');
bus_types_seed($db, 'BUS-2210', 'Higer Bus');
bus_types_seed($db, 'BUS-2417', 'hino bus');
bus_types_seed($db, 'Bus-1001', 'Aircon');
bus_types_seed($db, 'Bus-1005', 'Standard');
bus_types_seed($db, 'BUS-2002', null);

$before = bus_types_map($db);
$report = admin_normalize_bus_types($db, true);
check($report['dry_run'] === true, 'A dry run is marked as such');
check(count($report['blanked']) === 2, 'The dry run reports both retired types');
check(count($report['canonicalized']) === 1, 'The dry run reports the case-mismatched value');
check(bus_types_map($db) === $before, 'The dry run changes nothing');

$report = admin_normalize_bus_types($db);
check(count($report['canonicalized']) === 1 && $report['canonicalized'][0]['bus_id'] === 'BUS-2417', 'Near matches are canonicalized to the configured spelling');
check(count($report['blanked']) === 2, 'Retired types are blanked');
check((int)$report['unchanged'] === 3, 'Configured/blank values are left alone');

$after = bus_types_map($db);
check($after['BUS-2001'] === 'HINO Bus' && $after['BUS-2210'] === 'Higer Bus', 'Configured types are untouched');
check($after['BUS-2417'] === 'HINO Bus', 'Canonicalization stores the exact configured spelling');
check($after['Bus-1001'] === null && $after['Bus-1005'] === null, 'Retired types are blanked');
check($after['BUS-2002'] === null, 'Already blank values stay blank');

$report = admin_normalize_bus_types($db);
check($report['canonicalized'] === [] && $report['blanked'] === [], 'A re-run finds nothing to change');
check(bus_types_map($db) === $after, 'A re-run changes nothing');

$text = admin_normalize_bus_types_report(admin_normalize_bus_types(bus_types_test_db(), true));
check(strpos($text, 'Allowed types: HINO Bus, Higer Bus') !== false, 'The report names the allowed types');

echo "$checks bus type normalization checks passed.\n";

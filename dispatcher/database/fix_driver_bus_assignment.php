<?php

/**
 * One-off maintenance: collapse driver_bus_assignment to ONE row per bus and
 * enforce that with a unique key.
 *
 * The table accumulated duplicate rows (the admin driver-create flow inserts
 * blindly), and every reader picks a different row, so the same bus resolved to
 * different drivers across the dispatcher, passenger app, and mobile APIs.
 * This script keeps the OLDEST (lowest id) row per bus and deletes the rest,
 * then adds UNIQUE KEY uq_driver_bus_assignment_bus (bus_id) so new duplicates
 * cannot be created.
 *
 * Usage:
 *   php dispatcher/database/fix_driver_bus_assignment.php          (dry run)
 *   php dispatcher/database/fix_driver_bus_assignment.php --run    (apply)
 *
 * The dry run prints the exact SQL, so it can also be run manually in
 * phpMyAdmin when CLI access is not available.
 */

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
}

$run = false;
if (PHP_SAPI === 'cli') {
    $run = in_array('--run', $argv ?? [], true);
} else {
    $run = isset($_GET['run']) && (string) $_GET['run'] === '1';
}

require_once dirname(__DIR__, 2) . '/mobile/config/database.php';

$db = new Database();
$conn = $db->connect();

function fix_dba_line(string $line): void
{
    echo $line . PHP_EOL;
}

fix_dba_line('driver_bus_assignment cleanup');
fix_dba_line('mode: ' . ($run ? 'APPLY (--run)' : 'DRY RUN'));
fix_dba_line('');

$tableCheck = $conn->query("SHOW TABLES LIKE 'driver_bus_assignment'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    fix_dba_line('Table driver_bus_assignment does not exist. Nothing to do.');
    $conn->close();
    exit(0);
}

$idCol = $conn->query("SHOW COLUMNS FROM driver_bus_assignment LIKE 'id'");
if (!$idCol || $idCol->num_rows === 0) {
    fix_dba_line('The table has no `id` column; cannot identify the oldest row safely.');
    fix_dba_line('Add an id column or dedupe manually, then re-run.');
    $conn->close();
    exit(1);
}

$total = (int) $conn->query("SELECT COUNT(*) AS c FROM driver_bus_assignment")->fetch_assoc()['c'];
$busCount = (int) $conn->query("SELECT COUNT(DISTINCT bus_id) AS c FROM driver_bus_assignment")->fetch_assoc()['c'];

fix_dba_line("rows=$total buses=$busCount duplicate_rows=" . max(0, $total - $busCount));
fix_dba_line('');

$dupSql = "
    SELECT dba.bus_id,
           dba.id,
           dba.driver_id,
           CASE WHEN dba.id = keepers.keep_id THEN 'KEEP' ELSE 'DELETE' END AS action
    FROM driver_bus_assignment dba
    INNER JOIN (
        SELECT bus_id, MIN(id) AS keep_id
        FROM driver_bus_assignment
        GROUP BY bus_id
    ) keepers ON keepers.bus_id = dba.bus_id
    WHERE dba.id <> keepers.keep_id
    ORDER BY dba.bus_id ASC, dba.id ASC
";

$dups = $conn->query($dupSql);
$duplicateRows = $dups ? $dups->num_rows : 0;

if ($duplicateRows === 0) {
    fix_dba_line('No duplicate rows found. Checking the unique key...');
} else {
    fix_dba_line("Duplicate rows to delete (oldest row per bus is kept): $duplicateRows");
    while ($row = $dups->fetch_assoc()) {
        fix_dba_line(sprintf(
            '  %s id=%s driver=%s -> %s',
            (string) $row['bus_id'],
            (string) $row['id'],
            (string) $row['driver_id'],
            (string) $row['action']
        ));
    }
    fix_dba_line('');
}

$deleteSql = "
    DELETE dba FROM driver_bus_assignment dba
    INNER JOIN (
        SELECT bus_id, MIN(id) AS keep_id
        FROM driver_bus_assignment
        GROUP BY bus_id
    ) keepers ON keepers.bus_id = dba.bus_id
    WHERE dba.id <> keepers.keep_id
";

$indexSql = "ALTER TABLE driver_bus_assignment ADD UNIQUE KEY uq_driver_bus_assignment_bus (bus_id)";

$hasIndex = false;
$idxRes = $conn->query("SHOW INDEX FROM driver_bus_assignment WHERE Key_name = 'uq_driver_bus_assignment_bus'");
if ($idxRes && $idxRes->num_rows > 0) {
    $hasIndex = true;
}

if (!$run) {
    fix_dba_line('SQL to run (phpMyAdmin or CLI):');
    fix_dba_line('');
    fix_dba_line('-- 1. keep the oldest row per bus');
    fix_dba_line($deleteSql . ';');
    fix_dba_line('');
    fix_dba_line('-- 2. prevent duplicates from coming back');
    if ($hasIndex) {
        fix_dba_line('-- (unique key already present)');
    } else {
        fix_dba_line($indexSql . ';');
    }
    fix_dba_line('');
    fix_dba_line('Re-run with --run to apply, or execute the SQL above manually.');
    $conn->close();
    exit(0);
}

if ($duplicateRows > 0) {
    if (!$conn->query($deleteSql)) {
        fix_dba_line('Cleanup failed: ' . $conn->error);
        $conn->close();
        exit(1);
    }
    fix_dba_line('Deleted ' . $conn->affected_rows . ' duplicate row(s).');
} else {
    fix_dba_line('No duplicate rows to delete.');
}

if (!$hasIndex) {
    if (!$conn->query($indexSql)) {
        fix_dba_line('Adding the unique key failed: ' . $conn->error);
        fix_dba_line('Resolve remaining duplicates manually, then re-run.');
        $conn->close();
        exit(1);
    }
    fix_dba_line('Added UNIQUE KEY uq_driver_bus_assignment_bus (bus_id).');
} else {
    fix_dba_line('Unique key already present.');
}

$afterTotal = (int) $conn->query("SELECT COUNT(*) AS c FROM driver_bus_assignment")->fetch_assoc()['c'];
$afterBusCount = (int) $conn->query("SELECT COUNT(DISTINCT bus_id) AS c FROM driver_bus_assignment")->fetch_assoc()['c'];
fix_dba_line('');
fix_dba_line("Done. rows=$afterTotal buses=$afterBusCount");

$conn->close();
exit(0);

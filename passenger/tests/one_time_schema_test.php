<?php declare(strict_types=1);

/**
 * Additive scan-snapshot schema checks for the One-Time flow.
 *
 * The columns are added lazily by ensureOneTimeSchema() on the app's MySQL
 * connection. This test needs that connection; when none is available (for
 * example a CI box without the app database) it reports a skip instead of
 * failing, matching the schema helpers' deploy-time behavior.
 */

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

// Real callers (scan.php, OneTimeController) load the Database class before
// the schema helper; the helper's include guard is early-bound, so do the same.
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/../services/OneTime/OneTimeSchema.php';

$checks = 0;
function check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$conn = \App\Core\Database::tryGet();

if (!$conn) {
    echo "one-time schema checks skipped: no database connection available.\n";
    exit(0);
}

$columns = ['scan_latitude', 'scan_longitude', 'scan_recorded_at', 'scan_stop_id', 'scan_location_source'];

ensureOneTimeSchema($conn);

foreach ($columns as $column) {
    $stmt = $conn->query("SHOW COLUMNS FROM passenger_trips LIKE " . $conn->quote($column));
    check($stmt && $stmt->fetch() !== false, "Column {$column} exists after the ensure call");
}

// A second call must not fail and must not change the result (idempotent).
ensureOneTimeSchema($conn);

foreach ($columns as $column) {
    $stmt = $conn->query("SHOW COLUMNS FROM passenger_trips LIKE " . $conn->quote($column));
    check($stmt && $stmt->fetch() !== false, "Column {$column} survives the second ensure call");
}

echo "{$checks} one-time schema checks passed.\n";

<?php declare(strict_types=1);

/**
 * Read-only User ID audit for existing installations.
 *
 * Usage:
 *   php admin/tools/user_id_audit.php          Human-readable report
 *   php admin/tools/user_id_audit.php --json   Machine-readable report
 *
 * Reports duplicate IDs, malformed IDs, the next ID each scope would
 * allocate, and whether the UNIQUE indexes are in place. It never renumbers
 * or changes existing records — run it before deploying the automatic ID
 * generation to confirm legacy data is safe.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/UserIdService.php';

use Admin\Services\RoleManagement\UserIdService;
use App\Core\Database;

try {
    $db = Database::get();
} catch (Throwable $e) {
    fwrite(STDERR, 'Could not connect to the database: ' . $e->getMessage() . "\n");
    exit(1);
}

UserIdService::ensureSchema($db);
$report = (new UserIdService($db))->audit();

if (in_array('--json', $argv, true)) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

echo "R-Connect User ID audit (read-only)\n";
echo str_repeat('=', 40) . "\n\n";

echo "Next ID per scope:\n";
foreach ($report['next'] as $scope => $next) {
    printf("  %-14s %s\n", $scope, $next !== '' ? $next : '(unavailable)');
}

echo "\nUnique indexes:\n";
foreach ($report['unique_indexes'] as $target => $state) {
    $note = $state === 'blocked-duplicates'
        ? ' — duplicates exist; clean them before enforcing'
        : '';
    printf("  %-40s %s%s\n", $target, $state, $note);
}

echo "\nDuplicate IDs found:\n";
$foundDuplicate = false;
foreach ($report['duplicates'] as $target => $values) {
    if ($values) {
        $foundDuplicate = true;
        printf("  %s: %s\n", $target, implode(', ', $values));
    }
}
if (!$foundDuplicate) {
    echo "  None.\n";
}

echo "\nMalformed IDs (not PREFIX-number):\n";
$foundMalformed = false;
foreach ($report['format_issues'] as $target => $count) {
    if ($count > 0) {
        $foundMalformed = true;
        printf("  %s: %d\n", $target, $count);
    }
}
if (!$foundMalformed) {
    echo "  None.\n";
}

echo "\nExisting IDs are never changed by this tool. New accounts continue after the highest number per scope.\n";
exit(0);

<?php declare(strict_types=1);

/**
 * Backfill discrepancy cases for existing remittance records.
 *
 * Usage:
 *   php finance/tools/backfill_discrepancy_cases.php            Preview only (dry run)
 *   php finance/tools/backfill_discrepancy_cases.php --apply    Create the missing cases
 *
 * Mapping (design D7):
 *   discrepancy_amount > 0, actual_cash > expected_cash   -> Excess Remittance
 *   discrepancy_amount > 0, actual_cash < expected_cash   -> Cash Shortage
 *   status = Overdue (actual_cash NULL)                   -> Overdue Remittance
 *   legacy Resolved/Completed with a discrepancy          -> case status Resolved,
 *                                                            findings/reason from the
 *                                                            legacy resolution columns
 *
 * The insert is guarded by the UNIQUE discrepancy_cases.remittance_log_id, so
 * running the tool repeatedly never creates a second case for one remittance
 * and never modifies or deletes remittance data.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/services/DiscrepancyCaseService.php';

use App\Core\Database;
use Finance\Services\DiscrepancyCaseService;

$apply = in_array('--apply', $argv, true);

try {
    $db = Database::get();
} catch (Throwable $e) {
    fwrite(STDERR, 'Could not connect to the database: ' . $e->getMessage() . "\n");
    exit(1);
}

try {
    $result = (new DiscrepancyCaseService($db))->backfillLegacyCases($apply);
} catch (Throwable $e) {
    fwrite(STDERR, 'Backfill failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo 'Discrepancy case backfill ' . ($apply ? '(applied)' : '(dry run)') . "\n";
echo 'Remittance rows scanned: ' . $result['scanned'] . "\n";
echo 'Cases already present:   ' . $result['existing'] . "\n";
echo 'Cases created:           ' . $result['created'] . "\n";

if ($result['by_status']) {
    echo "Legacy status breakdown:\n";
    foreach ($result['by_status'] as $status => $count) {
        echo '  - ' . ($status === '' ? '(empty)' : $status) . ': ' . $count . "\n";
    }
}

if (!$apply && $result['scanned'] > 0) {
    echo "\nDry run only. Re-run with --apply to create the missing cases.\n";
}

exit(0);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';

use App\Core\Database;

$output = [];
$output[] = '=== Archived Records Cleanup ===';
$output[] = 'Started: ' . date('Y-m-d H:i:s');
$output[] = '';

try {
    $db = Database::get();
    $cutoff = date('Y-m-d H:i:s', strtotime('-2 years'));

    // ── Drivers ──
    $stmt = $db->prepare("DELETE FROM drivers WHERE status = 'Archived' AND archived_at IS NOT NULL AND archived_at <= :cutoff");
    $stmt->execute([':cutoff' => $cutoff]);
    $count = $stmt->rowCount();
    $output[] = "Drivers: {$count} record(s) deleted";

    // ── Inspectors ──
    $stmt = $db->prepare("DELETE FROM inspector_accounts WHERE status = 'archived' AND archived_at IS NOT NULL AND archived_at <= :cutoff");
    $stmt->execute([':cutoff' => $cutoff]);
    $count = $stmt->rowCount();
    $output[] = "Inspectors: {$count} record(s) deleted";

    // ── Financial Reports ──
    $stmt = $db->prepare("DELETE FROM financial_reports WHERE archived = 1 AND archived_at IS NOT NULL AND archived_at <= :cutoff");
    $stmt->execute([':cutoff' => $cutoff]);
    $count = $stmt->rowCount();
    $output[] = "Financial Reports: {$count} record(s) deleted";

    $output[] = '';
    $output[] = 'Cleanup completed successfully.';

} catch (Throwable $e) {
    $output[] = '';
    $output[] = 'ERROR: ' . $e->getMessage();
}

$output[] = 'Finished: ' . date('Y-m-d H:i:s');
echo implode(PHP_EOL, $output) . PHP_EOL;

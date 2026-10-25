<?php
declare(strict_types=1);

/**
 * Sample script proving the reusable DiscrepancyFixture helper works.
 *
 * Usage: php finance/tests/discrepancy_fixture_sample_test.php
 * Exits 0 and reports the seeded row counts.
 */

require_once __DIR__ . '/DiscrepancyFixture.php';

$db = DiscrepancyFixture::pdo();

DiscrepancyFixture::seedDriver($db, 'D1', 'Driver One');
DiscrepancyFixture::seedRemittanceLog($db, [
    'driver_id' => 'D1',
    'business_date' => '2026-10-01',
    'expected_cash' => 500,
    'actual_cash' => 450,
    'discrepancy_amount' => 50,
    'status' => 'Discrepancy',
]);
DiscrepancyFixture::seedCase($db, [
    'remittance_log_id' => 1,
    'driver_id' => 'D1',
    'business_date' => '2026-10-01',
    'case_type' => 'Cash Shortage',
    'expected_amount' => 500,
    'actual_amount' => 450,
    'discrepancy_amount' => 50,
    'due_date' => '2026-10-04',
    'opened_by' => 'sample',
    'opened_at' => '2026-10-01 21:00:00',
]);
DiscrepancyFixture::seedLedgerEntry($db, ['case_id' => 1, 'amount' => 20]);

$counts = DiscrepancyFixture::counts($db);

echo "Discrepancy fixture sample\n";
foreach (['drivers', 'remittance_logs', 'discrepancy_cases', 'discrepancy_ledger_entries'] as $table) {
    echo '  ' . $table . ': ' . $counts[$table] . "\n";
}

if ($counts['drivers'] !== 1
    || $counts['remittance_logs'] !== 1
    || $counts['discrepancy_cases'] !== 1
    || $counts['discrepancy_ledger_entries'] !== 1
) {
    fwrite(STDERR, "Fixture seed counts are wrong.\n");
    exit(1);
}

echo "OK\n";
exit(0);

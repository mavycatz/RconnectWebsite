<?php declare(strict_types=1);

/**
 * Consolidate duplicate remittance records (one driver = one record per
 * business date).
 *
 * Usage:
 *   php finance/tools/consolidate_remittance_duplicates.php            Preview only (dry run)
 *   php finance/tools/consolidate_remittance_duplicates.php --apply    Merge duplicates
 *
 * Merge rules live in Finance\Services\RemittanceConsolidationService so the
 * Remittance History page applies the same logic when displaying data. Every
 * merge is audited (Finance / Remittance Consolidated), and records are never
 * merged across different drivers or business dates.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/auth/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/RemittanceModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/services/RemittanceConsolidationService.php';

use App\Core\Database;
use Finance\Models\AuditTrailModel;
use Finance\Models\RemittanceModel;
use Finance\Services\RemittanceConsolidationService;

$apply = in_array('--apply', $argv, true);

try {
    $db = Database::get();
} catch (Throwable $e) {
    fwrite(STDERR, 'Could not connect to the database: ' . $e->getMessage() . "\n");
    exit(1);
}

RemittanceModel::ensureBusinessDateColumn($db);

$hasResolutionColumns = false;
try {
    $hasResolutionColumns = (int) $db->query("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'remittance_logs'
          AND COLUMN_NAME = 'resolution_type'
    ")->fetchColumn() > 0;
} catch (Throwable $e) {
    $hasResolutionColumns = false;
}

$resolutionSelect = $hasResolutionColumns
    ? ', resolution_type, resolution_notes, resolved_by, resolved_at'
    : '';

$rows = $db->query("
    SELECT
        id, driver_id, business_date, trip_no, total_passengers, total_collection,
        expected_cash, actual_cash, discrepancy_amount, status, finance_code,
        confirmed_by, submitted_by, remitted_by, reviewed_by, reviewed_at, created_at
        {$resolutionSelect}
    FROM remittance_logs
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$groups = RemittanceConsolidationService::groupRows($rows);
$duplicateGroups = array_values(array_filter($groups, static fn(array $group): bool => count($group) > 1));

echo 'Remittance records scanned: ' . count($rows) . "\n";
echo 'Duplicate driver/date groups: ' . count($duplicateGroups) . "\n\n";

if (empty($duplicateGroups)) {
    echo "No duplicates found. The unique index can be added safely (see finance/database/migrations/003_consolidate_remittance_duplicates.sql).\n";
    exit(0);
}

$mergedCount = 0;

foreach ($duplicateGroups as $key => $group) {
    [$driverId, $businessDate] = explode('|', $key, 2);

    $merged = RemittanceConsolidationService::summarize($group);
    $canonicalId = (int) ($merged['id'] ?? 0);
    $sourceIds = $merged['source_ids'] ?? [];
    $statuses = array_map(static fn(array $row): string => (string) ($row['status'] ?? ''), $group);

    echo "- Driver {$driverId} / {$businessDate}: ids [" . implode(', ', $sourceIds) . ']'
        . ' statuses [' . implode(', ', $statuses) . ']'
        . ' -> keep #' . $canonicalId
        . ' expected ₱' . number_format((float) ($merged['expected_cash'] ?? 0), 2)
        . ' actual ₱' . number_format((float) ($merged['actual_cash'] ?? 0), 2)
        . ' discrepancy ₱' . number_format((float) ($merged['discrepancy_amount'] ?? 0), 2)
        . "\n";

    if (!$apply) {
        continue;
    }

    $resolution = [
        'resolution_type' => null,
        'resolution_notes' => null,
        'resolved_by' => null,
        'resolved_at' => null,
    ];
    if ($hasResolutionColumns) {
        foreach ($group as $row) {
            foreach (array_keys($resolution) as $column) {
                if ($resolution[$column] === null && !empty($row[$column])) {
                    $resolution[$column] = $row[$column];
                }
            }
        }
    }

    $db->beginTransaction();

    try {
        $resolutionSet = '';
        $resolutionParams = [];

        if ($hasResolutionColumns) {
            $resolutionSet = ",
                resolution_type = COALESCE(resolution_type, :resolution_type),
                resolution_notes = COALESCE(resolution_notes, :resolution_notes),
                resolved_by = COALESCE(resolved_by, :resolved_by),
                resolved_at = COALESCE(resolved_at, :resolved_at)";
            $resolutionParams = [
                ':resolution_type' => $resolution['resolution_type'],
                ':resolution_notes' => $resolution['resolution_notes'],
                ':resolved_by' => $resolution['resolved_by'],
                ':resolved_at' => $resolution['resolved_at'],
            ];
        }

        $update = $db->prepare("
            UPDATE remittance_logs
            SET business_date = :business_date,
                trip_no = :trip_no,
                total_passengers = :total_passengers,
                total_collection = :total_collection,
                expected_cash = :expected_cash,
                actual_cash = :actual_cash,
                discrepancy_amount = :discrepancy_amount,
                status = :status,
                finance_code = :finance_code
                {$resolutionSet}
            WHERE id = :id
        ");

        $update->execute([
            ':business_date' => $merged['business_date'],
            ':trip_no' => (string) ($merged['trip_no'] ?? ''),
            ':total_passengers' => (int) ($merged['total_passengers'] ?? 0),
            ':total_collection' => (float) ($merged['total_collection'] ?? 0),
            ':expected_cash' => (float) ($merged['expected_cash'] ?? 0),
            ':actual_cash' => (float) ($merged['actual_cash'] ?? 0),
            ':discrepancy_amount' => (float) ($merged['discrepancy_amount'] ?? 0),
            ':status' => (string) ($merged['status'] ?? 'Completed'),
            ':finance_code' => (string) ($merged['finance_code'] ?? 'verified'),
            ':id' => $canonicalId,
        ] + $resolutionParams);

        $removeIds = array_values(array_diff($sourceIds, [$canonicalId]));
        if (!empty($removeIds)) {
            $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
            $db->prepare("DELETE FROM remittance_logs WHERE id IN ($placeholders)")->execute($removeIds);
        }

        $reference = 'RM-' . str_pad((string) $canonicalId, 5, '0', STR_PAD_LEFT);

        AuditTrailModel::record(
            'Finance',
            'Remittance Consolidated',
            'Consolidated ' . count($group) . ' duplicate remittance records for driver '
                . $driverId . ' on ' . $merged['business_date'] . ' into ' . $reference . '.',
            $reference,
            ['ids' => $sourceIds, 'statuses' => $statuses],
            [
                'kept_id' => $canonicalId,
                'business_date' => $merged['business_date'],
                'expected_cash' => $merged['expected_cash'],
                'actual_cash' => $merged['actual_cash'],
                'discrepancy_amount' => $merged['discrepancy_amount'],
            ],
            'Success'
        );

        $db->commit();
        $mergedCount++;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        fwrite(STDERR, "  merge failed: " . $e->getMessage() . "\n");
    }
}

if (!$apply) {
    echo "\nDry run only. Re-run with --apply to merge these records.\n";
    exit(0);
}

echo "\nMerged groups: {$mergedCount}\n";

$remaining = (int) $db->query("
    SELECT COUNT(*) FROM (
        SELECT driver_id, COALESCE(business_date, DATE(created_at)) AS business_day
        FROM remittance_logs
        GROUP BY driver_id, COALESCE(business_date, DATE(created_at))
        HAVING COUNT(*) > 1
    ) duplicates
")->fetchColumn();

if ($remaining > 0) {
    echo "Remaining duplicate groups: {$remaining}. Review these before adding a unique index.\n";
    exit(1);
}

echo "No duplicates remain. Attempting the unique index (driver_id, business_date)...\n";

try {
    $db->exec("ALTER TABLE remittance_logs ADD UNIQUE INDEX uq_remittance_driver_business (driver_id, business_date)");
    echo "Unique index added.\n";
} catch (Throwable $e) {
    if ((int) ($e->errorInfo[1] ?? 0) === 1061) {
        echo "Unique index already exists.\n";
    } else {
        echo 'Could not add the unique index: ' . $e->getMessage() . "\n";
    }
}

exit(0);

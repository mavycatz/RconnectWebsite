<?php declare(strict_types=1);

/**
 * Normalize buses.bus_type to the configured two-type fleet list.
 *
 * Values that match a configured type (case-insensitively) are rewritten to
 * the canonical spelling; any other value is blanked (NULL) so no retired type
 * ("Standard", "Aircon", ...) survives in the data. The Add/Edit bus form then
 * sets the real type from the HINO Bus / Higer Bus dropdown.
 *
 *   php admin/migrations/normalize_bus_types.php --dry-run
 *   php admin/migrations/normalize_bus_types.php
 *
 * Idempotent: a second run reports no changes. Writes run in one transaction.
 */

require_once __DIR__ . '/../../app/Core/Database.php';

/**
 * The canonical type list, straight from the bus options configuration.
 *
 * @return string[]
 */
function admin_allowed_bus_types(): array
{
    $config = require __DIR__ . '/../config/bus_options.php';
    $types = is_array($config['bus_types'] ?? null) ? $config['bus_types'] : [];

    return array_values(array_filter(array_map(
        static fn ($value) => trim((string)$value),
        $types
    ), static fn ($value) => $value !== ''));
}

/**
 * @return array{allowed:string[],dry_run:bool,unchanged:int,canonicalized:array<int,array<string,mixed>>,blanked:array<int,array<string,mixed>>}
 */
function admin_normalize_bus_types(PDO $db, bool $dryRun = false): array
{
    $allowed = admin_allowed_bus_types();
    $report = [
        'allowed' => $allowed,
        'dry_run' => $dryRun,
        'unchanged' => 0,
        'canonicalized' => [],
        'blanked' => [],
    ];

    try {
        $rows = $db->query('SELECT id, bus_id, bus_type FROM buses')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        throw new \RuntimeException('The buses table could not be read: ' . $e->getMessage());
    }

    $updates = [];
    foreach ($rows as $row) {
        $current = trim((string)($row['bus_type'] ?? ''));
        if ($current === '') {
            $report['unchanged']++;
            continue;
        }

        $canonical = null;
        foreach ($allowed as $type) {
            if (strcasecmp($type, $current) === 0) {
                $canonical = $type;
                break;
            }
        }
        if ($canonical === $current) {
            $report['unchanged']++;
            continue;
        }

        $updates[(int)$row['id']] = $canonical;
        if ($canonical !== null) {
            $report['canonicalized'][] = ['bus_id' => (string)$row['bus_id'], 'from' => $current, 'to' => $canonical];
        } else {
            $report['blanked'][] = ['bus_id' => (string)$row['bus_id'], 'from' => $current, 'to' => null];
        }
    }

    if ($dryRun || $updates === []) {
        return $report;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE buses SET bus_type = ? WHERE id = ?');
        foreach ($updates as $id => $value) {
            $stmt->execute([$value, $id]);
        }
        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }

    return $report;
}

/**
 * Human-readable report (also asserted by the test).
 */
function admin_normalize_bus_types_report(array $report): string
{
    $lines = [];
    $lines[] = 'Bus type normalization' . ($report['dry_run'] ? ' (dry run - no changes written)' : '');
    $lines[] = 'Allowed types: ' . implode(', ', $report['allowed']);
    $lines[] = 'Canonicalized (' . count($report['canonicalized']) . '):';
    foreach ($report['canonicalized'] as $row) {
        $lines[] = sprintf('  ~ %s  "%s" -> "%s"', (string)$row['bus_id'], (string)$row['from'], (string)$row['to']);
    }
    $lines[] = 'Blanked (' . count($report['blanked']) . '):';
    foreach ($report['blanked'] as $row) {
        $lines[] = sprintf('  - %s  "%s" -> (unset)', (string)$row['bus_id'], (string)$row['from']);
    }
    $lines[] = sprintf(
        'Summary: %d canonicalized, %d blanked, %d unchanged.',
        count($report['canonicalized']),
        count($report['blanked']),
        (int)$report['unchanged']
    );

    return implode("\n", $lines) . "\n";
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $dryRun = in_array('--dry-run', (array)($argv ?? []), true);
    $db = App\Core\Database::tryGet();
    if ($db === null) {
        fwrite(STDERR, "Bus type normalization failed: the database is unreachable.\n");
        exit(1);
    }
    try {
        $report = admin_normalize_bus_types($db, $dryRun);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Bus type normalization failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
    echo admin_normalize_bus_types_report($report);
    exit(0);
}

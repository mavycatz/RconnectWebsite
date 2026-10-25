<?php declare(strict_types=1);

/**
 * One-time reconciliation of the official R-CONNECT bus fleet.
 *
 * The company's official bus registration table lists 18 buses with their
 * plate numbers and registration months. This script makes the `buses` table
 * match that table without fabricating any other vehicle data:
 *
 *   php admin/migrations/reconcile_official_buses.php --dry-run   # report only
 *   php admin/migrations/reconcile_official_buses.php             # write
 *
 * Matching rules (see openspec/changes/correct-bus-management-data/design.md):
 *   - plates are compared with BusDuplicates::normalizePlate();
 *   - bus numbers are compared case- and prefix-insensitively, so BUS-2001,
 *     Bus-2001 and 2001 all identify bus 2001;
 *   - a plate match wins over a bus-number match; a number-holder with a
 *     different plate is reported as a conflict and left untouched;
 *   - matched rows keep their primary key and identifier shape; missing
 *     buses are inserted as BUS-#### with only verified values (everything
 *     else stays NULL).
 *
 * All writes run in one transaction and the 18 official number/plate pairs
 * are verified before the commit. Re-running is a no-op. QR columns and the
 * bus_qr_credentials table are never written.
 */

require_once __DIR__ . '/../services/Bus/BusDuplicates.php';
require_once __DIR__ . '/../services/Bus/BusFields.php';
require_once __DIR__ . '/../../app/Core/Database.php';

use Admin\Services\Bus\BusDuplicates;
use Admin\Services\Bus\BusFields;

/**
 * The official registration table: bus number, plate number, month.
 *
 * @return array<int,array{number:string,plate:string,month:string}>
 */
function admin_official_buses(): array
{
    return [
        ['number' => '2001', 'plate' => 'NGP-2212', 'month' => 'February'],
        ['number' => '2417', 'plate' => 'CCF-6063', 'month' => 'March'],
        ['number' => '2105', 'plate' => 'NEW-1314', 'month' => 'April'],
        ['number' => '2418', 'plate' => 'CCF-6064', 'month' => 'April'],
        ['number' => '2208', 'plate' => 'NFG-9974', 'month' => 'April'],
        ['number' => '2106', 'plate' => 'NEW-1315', 'month' => 'May'],
        ['number' => '2209', 'plate' => 'NFG-9975', 'month' => 'May'],
        ['number' => '2002', 'plate' => 'NGP-3385', 'month' => 'May'],
        ['number' => '2107', 'plate' => 'NEW-1316', 'month' => 'June'],
        ['number' => '2212', 'plate' => 'NIE-4336', 'month' => 'June'],
        ['number' => '2211', 'plate' => 'NIB-4266', 'month' => 'June'],
        ['number' => '2315', 'plate' => 'CBT-4976', 'month' => 'June'],
        ['number' => '2213', 'plate' => 'NIE-4337', 'month' => 'July'],
        ['number' => '2316', 'plate' => 'CAZ-5548', 'month' => 'August'],
        ['number' => '2003', 'plate' => 'NGP-5468', 'month' => 'August'],
        ['number' => '2318', 'plate' => 'CBT-4978', 'month' => 'August'],
        ['number' => '2004', 'plate' => 'NGP-5469', 'month' => 'September'],
        ['number' => '2210', 'plate' => 'NID-6560', 'month' => 'October'],
    ];
}

/**
 * Columns the official table says nothing about. Matched rows keep their
 * existing values; inserted rows leave them NULL.
 *
 * @return string[]
 */
function admin_official_bus_unknown_fields(): array
{
    return [
        'bus_type', 'seats', 'standing_capacity', 'accessible_features', 'driver',
        'from_terminal_id', 'to_terminal_id', 'route_name', 'terminal', 'schedule',
        'manufacturer', 'model', 'year_model', 'engine_number', 'chassis_number',
        'fuel_type', 'transmission_type', 'air_conditioning', 'vehicle_ownership',
        'assigned_inspector_id', 'operational_status', 'registration_expiry',
        'insurance_expiry', 'inspection_date', 'next_inspection_date',
    ];
}

/**
 * Case- and prefix-insensitive form of a bus identifier: BUS-2001, Bus-2001
 * and 2001 all normalize to 2001.
 */
function admin_normalize_official_bus_number(string $busId): string
{
    $value = strtoupper(trim($busId));
    $value = (string)preg_replace('/^BUS[\s\-_]*/', '', $value);

    return $value;
}

/**
 * Identifier to store for a matched row: the existing prefix shape is kept
 * and only the numeric part is corrected.
 */
function admin_reconcile_desired_bus_id(string $stored, string $number): string
{
    $stored = trim($stored);
    if ($stored === '') {
        return 'BUS-' . $number;
    }
    if (preg_match('/^(BUS[\s\-_]*)/i', $stored, $match) === 1) {
        return $match[1] . $number;
    }

    return $number;
}

/**
 * Portable column check: PRAGMA on SQLite (tests), information_schema-backed
 * BusFields::hasColumn on the application database.
 */
function admin_reconcile_has_column(PDO $db, string $table, string $column): bool
{
    $driver = 'mysql';
    try {
        $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (\Throwable $e) {
        $driver = 'mysql';
    }
    if ($driver === 'sqlite') {
        try {
            foreach ($db->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if (strcasecmp((string)($col['name'] ?? ''), $column) === 0) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    return BusFields::hasColumn($db, $table, $column);
}

/**
 * Pick the deterministic row from a candidate set: one that also matches the
 * secondary signal first, otherwise the lowest id.
 *
 * @param array<int,array<string,mixed>> $primary
 * @param array<int,array<string,mixed>> $secondary
 * @return array<string,mixed>
 */
function admin_reconcile_pick_row(array $primary, array $secondary): array
{
    $secondaryIds = [];
    foreach ($secondary as $row) {
        $secondaryIds[(int)$row['id']] = true;
    }
    $both = [];
    foreach ($primary as $row) {
        if (isset($secondaryIds[(int)$row['id']])) {
            $both[] = $row;
        }
    }
    $pool = $both !== [] ? $both : $primary;
    usort($pool, static fn (array $a, array $b): int => (int)$a['id'] <=> (int)$b['id']);

    return $pool[0];
}

/**
 * Reconcile the fleet against the official table.
 *
 * @return array{
 *     dry_run:bool,
 *     inserted:array<int,array<string,mixed>>,
 *     updated:array<int,array<string,mixed>>,
 *     unchanged:array<int,array<string,mixed>>,
 *     conflicts:string[],
 *     unknown_fields:string[],
 *     verified:bool
 * }
 */
function admin_reconcile_official_buses(PDO $db, bool $dryRun = false): array
{
    $official = admin_official_buses();

    try {
        BusFields::ensureColumns($db);
    } catch (\Throwable $e) {
        // Legacy or SQLite installs without the additive schema still work.
    }

    $available = [];
    foreach (['registration_month', 'status', 'qr_code', 'updated_at'] as $column) {
        $available[$column] = admin_reconcile_has_column($db, 'buses', $column);
    }
    $hasUpdatedAt = $available['updated_at'];

    $report = [
        'dry_run' => $dryRun,
        'inserted' => [],
        'updated' => [],
        'unchanged' => [],
        'conflicts' => [],
        'unknown_fields' => admin_official_bus_unknown_fields(),
        'verified' => true,
    ];

    $rows = $db->query('SELECT * FROM buses')->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $byPlate = [];
    $byNumber = [];
    foreach ($rows as $row) {
        $plateKey = BusDuplicates::normalizePlate((string)($row['plate_number'] ?? ''));
        if ($plateKey !== '') {
            $byPlate[$plateKey][] = $row;
        }
        $numberKey = admin_normalize_official_bus_number((string)($row['bus_id'] ?? ''));
        if ($numberKey !== '') {
            $byNumber[$numberKey][] = $row;
        }
    }

    $conflicted = [];
    $addConflict = static function (array $row, string $message) use (&$report, &$conflicted): void {
        $id = (int)$row['id'];
        if (isset($conflicted[$id])) {
            return;
        }
        $conflicted[$id] = true;
        $report['conflicts'][] = $message;
    };

    $plan = [];
    foreach ($official as $entry) {
        $plateKey = BusDuplicates::normalizePlate($entry['plate']);
        $plateRows = $byPlate[$plateKey] ?? [];
        $numberRows = $byNumber[$entry['number']] ?? [];

        $target = null;
        if (count($plateRows) > 0) {
            $target = count($plateRows) === 1 ? $plateRows[0] : admin_reconcile_pick_row($plateRows, $numberRows);
            if (count($plateRows) > 1) {
                foreach ($plateRows as $row) {
                    if ((int)$row['id'] !== (int)$target['id']) {
                        $addConflict($row, sprintf(
                            'Duplicate plate %s: records #%d (%s) and #%d (%s) share the official plate; kept #%d unchanged and left #%d untouched.',
                            $entry['plate'],
                            (int)$target['id'],
                            (string)($target['bus_id'] ?? ''),
                            (int)$row['id'],
                            (string)($row['bus_id'] ?? ''),
                            (int)$target['id'],
                            (int)$row['id']
                        ));
                    }
                }
            }
        } elseif (count($numberRows) === 1) {
            $target = $numberRows[0];
        } elseif (count($numberRows) > 1) {
            $target = admin_reconcile_pick_row($numberRows, []);
            foreach ($numberRows as $row) {
                if ((int)$row['id'] !== (int)$target['id']) {
                    $addConflict($row, sprintf(
                        'Duplicate bus number %s: records #%d (%s) and #%d (%s); kept #%d unchanged and left #%d untouched.',
                        $entry['number'],
                        (int)$target['id'],
                        (string)($target['bus_id'] ?? ''),
                        (int)$row['id'],
                        (string)($row['bus_id'] ?? ''),
                        (int)$target['id'],
                        (int)$row['id']
                    ));
                }
            }
        }

        foreach ($numberRows as $row) {
            if ($target !== null && (int)$row['id'] === (int)$target['id']) {
                continue;
            }
            $addConflict($row, sprintf(
                'Conflicting record #%d (%s / %s) holds official bus number %s but not plate %s; left unchanged.',
                (int)$row['id'],
                (string)($row['bus_id'] ?? ''),
                (string)($row['plate_number'] ?? ''),
                $entry['number'],
                $entry['plate']
            ));
        }

        if ($target === null) {
            $plan[] = ['action' => 'insert', 'entry' => $entry];
            continue;
        }

        $desiredBusId = admin_reconcile_desired_bus_id((string)($target['bus_id'] ?? ''), $entry['number']);
        $currentBusId = trim((string)($target['bus_id'] ?? ''));
        $currentPlate = trim((string)($target['plate_number'] ?? ''));
        $currentMonth = trim((string)($target['registration_month'] ?? ''));

        $changes = [];
        if ($currentBusId !== $desiredBusId) {
            $changes['bus_id'] = ['from' => $currentBusId, 'to' => $desiredBusId];
        }
        if ($currentPlate !== $entry['plate']) {
            $changes['plate_number'] = ['from' => $currentPlate, 'to' => $entry['plate']];
        }
        if ($available['registration_month'] && $currentMonth !== $entry['month']) {
            $changes['registration_month'] = ['from' => $currentMonth, 'to' => $entry['month']];
        }

        if ($changes === []) {
            $plan[] = ['action' => 'unchanged', 'entry' => $entry, 'target' => $target];
        } else {
            $plan[] = ['action' => 'update', 'entry' => $entry, 'target' => $target, 'changes' => $changes];
        }
    }

    if ($dryRun) {
        foreach ($plan as $step) {
            if ($step['action'] === 'insert') {
                $report['inserted'][] = [
                    'bus_id' => 'BUS-' . $step['entry']['number'],
                    'plate_number' => $step['entry']['plate'],
                    'registration_month' => $step['entry']['month'],
                ];
            } elseif ($step['action'] === 'update') {
                $report['updated'][] = [
                    'id' => (int)$step['target']['id'],
                    'bus_id' => (string)($step['target']['bus_id'] ?? ''),
                    'changes' => $step['changes'],
                ];
            } else {
                $report['unchanged'][] = [
                    'id' => (int)$step['target']['id'],
                    'bus_id' => (string)($step['target']['bus_id'] ?? ''),
                ];
            }
        }

        return $report;
    }

    $db->beginTransaction();
    try {
        $now = date('Y-m-d H:i:s');
        foreach ($plan as $step) {
            if ($step['action'] === 'insert') {
                $columns = ['bus_id', 'plate_number'];
                $values = ['BUS-' . $step['entry']['number'], $step['entry']['plate']];
                if ($available['registration_month']) {
                    $columns[] = 'registration_month';
                    $values[] = $step['entry']['month'];
                }
                if ($available['status']) {
                    $columns[] = 'status';
                    $values[] = 'Active';
                }
                if ($available['qr_code']) {
                    $columns[] = 'qr_code';
                    $values[] = 'Pending';
                }
                $stmt = $db->prepare(
                    'INSERT INTO buses (' . implode(', ', $columns) . ') VALUES ('
                    . implode(', ', array_fill(0, count($columns), '?')) . ')'
                );
                $stmt->execute($values);
                $report['inserted'][] = [
                    'id' => (int)$db->lastInsertId(),
                    'bus_id' => 'BUS-' . $step['entry']['number'],
                    'plate_number' => $step['entry']['plate'],
                    'registration_month' => $step['entry']['month'],
                ];
                continue;
            }

            if ($step['action'] === 'unchanged') {
                $report['unchanged'][] = [
                    'id' => (int)$step['target']['id'],
                    'bus_id' => (string)($step['target']['bus_id'] ?? ''),
                ];
                continue;
            }

            $sets = [];
            $params = [];
            foreach ($step['changes'] as $column => $pair) {
                $sets[] = '`' . $column . '` = ?';
                $params[] = $pair['to'];
            }
            if ($hasUpdatedAt) {
                $sets[] = '`updated_at` = ?';
                $params[] = $now;
            }
            $params[] = (int)$step['target']['id'];
            $stmt = $db->prepare('UPDATE buses SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $stmt->execute($params);
            $report['updated'][] = [
                'id' => (int)$step['target']['id'],
                'bus_id' => (string)($step['target']['bus_id'] ?? ''),
                'changes' => $step['changes'],
            ];
        }

        /* Verify all 18 official pairs inside the transaction before committing. */
        $verifyRows = $db->query('SELECT * FROM buses')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $pairs = [];
        foreach ($verifyRows as $row) {
            $key = admin_normalize_official_bus_number((string)($row['bus_id'] ?? ''))
                . '|' . BusDuplicates::normalizePlate((string)($row['plate_number'] ?? ''));
            $pairs[$key] = true;
        }
        $allPresent = true;
        foreach ($official as $entry) {
            $key = $entry['number'] . '|' . BusDuplicates::normalizePlate($entry['plate']);
            if (!isset($pairs[$key])) {
                $allPresent = false;
                break;
            }
        }

        if (!$allPresent) {
            $db->rollBack();
            $report['verified'] = false;
            $report['inserted'] = [];
            $report['updated'] = [];
            $report['unchanged'] = [];

            return $report;
        }

        $db->commit();
        $report['verified'] = true;
    } catch (\Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }

    return $report;
}

/**
 * Human-readable reconciliation report (also asserted by the test).
 */
function admin_reconcile_report_text(array $report): string
{
    $lines = [];
    $lines[] = 'Official bus reconciliation' . ($report['dry_run'] ? ' (dry run - no changes written)' : '');

    $lines[] = 'Inserted (' . count($report['inserted']) . '):';
    foreach ($report['inserted'] as $row) {
        $lines[] = sprintf(
            '  + %s  %s  %s',
            (string)($row['bus_id'] ?? ''),
            (string)($row['plate_number'] ?? ''),
            (string)($row['registration_month'] ?? '')
        );
    }

    $lines[] = 'Updated (' . count($report['updated']) . '):';
    foreach ($report['updated'] as $row) {
        $changes = [];
        foreach (($row['changes'] ?? []) as $column => $pair) {
            $changes[] = $column . ': ' . (string)($pair['from'] ?? '') . ' -> ' . (string)($pair['to'] ?? '');
        }
        $lines[] = sprintf('  ~ id=%d %s  %s', (int)($row['id'] ?? 0), (string)($row['bus_id'] ?? ''), implode(', ', $changes));
    }

    $lines[] = 'Unchanged (' . count($report['unchanged']) . '):';
    foreach ($report['unchanged'] as $row) {
        $lines[] = sprintf('  = id=%d %s', (int)($row['id'] ?? 0), (string)($row['bus_id'] ?? ''));
    }

    $lines[] = 'Conflicts (' . count($report['conflicts']) . '):';
    foreach ($report['conflicts'] as $conflict) {
        $lines[] = '  ! ' . $conflict;
    }

    $lines[] = 'Unknown fields (not verified by the official registration table): '
        . implode(', ', $report['unknown_fields']);

    if (!$report['dry_run']) {
        $lines[] = 'Verification: ' . ($report['verified']
            ? 'all 18 official bus number/plate pairs present (committed)'
            : 'FAILED - nothing was committed');
    }

    $lines[] = sprintf(
        'Summary: %d inserted, %d updated, %d unchanged, %d conflict(s).',
        count($report['inserted']),
        count($report['updated']),
        count($report['unchanged']),
        count($report['conflicts'])
    );

    return implode("\n", $lines) . "\n";
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $dryRun = in_array('--dry-run', (array)($argv ?? []), true);
    $db = App\Core\Database::tryGet();
    if ($db === null) {
        fwrite(STDERR, "Official bus reconciliation failed: the database is unreachable.\n");
        exit(1);
    }
    try {
        $report = admin_reconcile_official_buses($db, $dryRun);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Official bus reconciliation failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
    echo admin_reconcile_report_text($report);
    exit($report['verified'] ? 0 : 1);
}

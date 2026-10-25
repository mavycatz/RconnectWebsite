<?php
declare(strict_types=1);

/**
 * Reports Center library: normalized report metadata, titles, and filters.
 *
 * Merges the three existing report sources into one item shape WITHOUT
 * changing storage:
 *   - /generated_reports/<category>/<file>       active files
 *   - /archived_reports/<category?>/<file>       archived files (moved by the
 *                                                existing archive model)
 *   - ai_trip_operations_reports / trip_operations_reports tables
 *
 * Timestamps are formatted in Asia/Manila. The physical filename and path stay
 * internal (item['file']) and are never used as the display title.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';

use App\Core\Database;

/**
 * Enforce the existing admin/super_admin session for every library request.
 */
function rlib_guard(): array
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/auth/Session.php';
    \App\Auth\Session::start();

    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
        exit;
    }
    $role = strtolower((string)($user['role'] ?? ''));
    if (!in_array($role, ['admin', 'super_admin'], true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden.']);
        exit;
    }
    return $user;
}

function rlib_respond(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/**
 * Report-type naming and descriptions keyed by the storage category slug.
 * Unknown categories still get a sensible humanized fallback.
 */
function rlib_type_info(string $category): array
{
    $key = strtolower(trim($category));
    $map = [
        'driver_report' => [
            'type' => 'Driver Performance',
            'title' => 'Driver Performance Report',
            'description' => 'AI performance analysis for one driver: attendance, trips, passengers, income, expenses and net results.',
            'needs_entity' => 'driver',
        ],
        'driver_performance' => [
            'type' => 'Driver Performance',
            'title' => 'Driver Performance Report',
            'description' => 'AI performance analysis for one driver: attendance, trips, passengers, income, expenses and net results.',
            'needs_entity' => 'driver',
        ],
        'daily_report' => [
            'type' => 'Trip Operations',
            'title' => 'Daily Closing Report',
            'description' => 'Finalized daily closing summary: trips, passengers, remittance, fuel, expenses and per-driver breakdown.',
            'needs_entity' => 'date',
        ],
        'trip_operations' => [
            'type' => 'Trip Operations',
            'title' => 'Trip Operations Report',
            'description' => 'Trip operations summary with per-trip records, routes, passenger counts and statuses.',
            'needs_entity' => 'period',
        ],
        'ai_trip_operations_reports' => [
            'type' => 'Trip Operations',
            'title' => 'Trip Operations Report',
            'description' => 'AI-assisted trip operations report with routes, passenger activity and operational findings.',
            'needs_entity' => 'period',
        ],
        'financial' => [
            'type' => 'Financial',
            'title' => 'Financial Performance Report',
            'description' => 'Income, expense categories, remittance and net results for the covered period.',
            'needs_entity' => 'period',
        ],
        'financial_reports' => [
            'type' => 'Financial',
            'title' => 'Financial Performance Report',
            'description' => 'Income, expense categories, remittance and net results for the covered period.',
            'needs_entity' => 'period',
        ],
        'fuel' => [
            'type' => 'Fuel',
            'title' => 'Fuel and Operating Expenses',
            'description' => 'Fuel consumption, fuel cost and operating expenses for the covered period.',
            'needs_entity' => 'period',
        ],
        'attendance' => [
            'type' => 'Attendance',
            'title' => 'Driver Attendance Summary',
            'description' => 'Driver attendance from check-in records: present days, absences and expected workdays.',
            'needs_entity' => 'driver',
        ],
        'bus_utilization' => [
            'type' => 'Bus Utilization',
            'title' => 'Bus Utilization Report',
            'description' => 'Bus utilization, trips operated and capacity usage for the covered period.',
            'needs_entity' => 'bus',
        ],
    ];

    if (isset($map[$key])) {
        return $map[$key] + ['category' => $key];
    }

    $human = ucwords(str_replace(['_', '-'], ' ', $key !== '' ? $key : 'general'));
    return [
        'type' => $human !== '' ? $human : 'General',
        'title' => ($human !== '' ? $human : 'General') . ' Report',
        'description' => 'Generated ' . strtolower($human !== '' ? $human : 'general') . ' report.',
        'needs_entity' => 'none',
        'category' => $key,
    ];
}

/**
 * Detect a covered period from a file name. Returns [label, mode, frequency]
 * or [null, null, null] when the name carries no reliable period.
 * Never guesses: unrecognized names stay unknown.
 */
function rlib_period_from_name(string $name): array
{
    $stem = pathinfo($name, PATHINFO_FILENAME);

    // Quarter: 2026_Q3 / 2026-Q3 / Q3_2026
    if (preg_match('/(20\d{2})[_\-]?Q([1-4])/i', $stem, $m) || preg_match('/Q([1-4])[_\-]?(20\d{2})/i', $stem, $m2)) {
        $year = $m ? $m[1] : $m2[2];
        $q = $m ? $m[2] : $m2[1];
        return ['Q' . $q . ' ' . $year, 'quarterly', 'Quarterly'];
    }

    // Day: 20261010 (must be a valid calendar date)
    if (preg_match('/(20\d{2})(\d{2})(\d{2})/', $stem, $m)) {
        $ts = strtotime($m[1] . '-' . $m[2] . '-' . $m[3]);
        if ($ts !== false) {
            return [date('F j, Y', $ts), 'custom', 'Daily'];
        }
    }

    // Month: 2026_10 / 2026-10
    if (preg_match('/(20\d{2})[_\-](0[1-9]|1[0-2])(?!\d)/', $stem, $m)) {
        $ts = strtotime($m[1] . '-' . $m[2] . '-01');
        if ($ts !== false) {
            return [date('F Y', $ts), 'monthly', 'Monthly'];
        }
    }

    // Frequency words only (no concrete covered dates).
    if (preg_match('/\b(semi[\-\s]?annual|half[\-\s]?year)\b/i', $stem)) {
        return [null, 'semi_annually', 'Semi-Annual'];
    }
    if (preg_match('/\bquarter(ly)?\b/i', $stem)) {
        return [null, 'quarterly', 'Quarterly'];
    }
    if (preg_match('/\bannual(ly)?|yearly\b/i', $stem)) {
        return [null, 'annually', 'Annual'];
    }
    if (preg_match('/\bmonth(ly)?\b/i', $stem)) {
        return [null, 'monthly', 'Monthly'];
    }
    if (preg_match('/\bweek(ly)?\b/i', $stem)) {
        return [null, 'weekly', 'Weekly'];
    }
    if (preg_match('/\bdaily\b/i', $stem)) {
        return [null, 'daily', 'Daily'];
    }

    return [null, null, null];
}

/**
 * Build the display title. Stored titles win when they are already
 * meaningful; otherwise the category type + detected period is used. The
 * file name is only ever used to extract a period or entity, never shown raw.
 */
function rlib_title(string $category, string $storedTitle, string $fileName): array
{
    $info = rlib_type_info($category);
    [$periodLabel, $periodMode, $frequency] = rlib_period_from_name($fileName);

    $entity = '';
    $stem = pathinfo($fileName, PATHINFO_FILENAME);
    if (preg_match('/^(.+?)\s*[\-–]\s*(performance report|driver (performance|report)|attendance)/i', $stem, $m)) {
        $entity = trim($m[1]);
        if (mb_strlen($entity) > 60) {
            $entity = '';
        }
    }

    $stored = trim($storedTitle);
    $generic = $stored === '' || preg_match('/^(ai\s+)?driver\s+report|driver_ai_report|\d{4}[_\-]\d{2}$/i', $stored) === 1;

    $base = $stored !== '' && !$generic ? $stored : $info['title'];
    if ($entity !== '' && stripos($base, $entity) === false) {
        $base = $entity . ' – ' . $info['title'];
    }
    if ($periodLabel !== null && stripos($base, $periodLabel) === false) {
        $base .= ' – ' . $periodLabel;
    }

    return [
        'title' => $base,
        'type' => $info['type'],
        'description' => $info['description'],
        'period_label' => $periodLabel,
        'period_mode' => $periodMode,
        'frequency' => $frequency,
        'entity' => $entity,
    ];
}

function rlib_format_from_ext(string $ext): string
{
    $ext = strtolower($ext);
    if ($ext === 'xlsx' || $ext === 'xls') return 'XLSX';
    if ($ext === 'csv') return 'CSV';
    if ($ext === 'pdf') return 'PDF';
    return strtoupper($ext !== '' ? $ext : 'FILE');
}

/**
 * Scan a report directory (active or archived) for physical files.
 */
function rlib_scan_files(string $root, string $base, bool $archived): array
{
    $items = [];
    $dir = rtrim($root, '/\\') . $base;
    if (!is_dir($dir)) {
        return $items;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['pdf', 'xlsx', 'xls', 'csv'], true)) {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());
        $rootPos = strpos($path, $base);
        $relative = $rootPos !== false ? substr($path, $rootPos + strlen($base)) : basename($path);
        $relative = ltrim($relative, '/');

        $segments = explode('/', $relative);
        $name = array_pop($segments);
        $category = $segments ? strtolower($segments[0]) : 'general';

        $meta = rlib_title($category, '', $name);
        $mtime = (int)$file->getMTime();

        $items[] = [
            'id' => ($archived ? 'a:' : 'f:') . $relative,
            'source' => 'file',
            'file' => $relative,
            'title' => $meta['title'],
            'type' => $meta['type'],
            'description' => $meta['description'],
            'entity' => $meta['entity'],
            'period' => [
                'label' => $meta['period_label'],
                'mode' => $meta['period_mode'],
                'known' => $meta['period_label'] !== null,
            ],
            'frequency' => $meta['frequency'],
            'generated_at' => date('Y-m-d H:i:s', $mtime),
            'generated_by' => null,
            'format' => rlib_format_from_ext($ext),
            'size_bytes' => (int)$file->getSize(),
            'status' => $archived ? 'Archived' : 'Available',
            'archived' => $archived,
            'sections' => [],
        ];
    }

    return $items;
}

/**
 * Read archived/generated reports from one of the report tables. The exact
 * column set differs between tables, so columns are checked first.
 */
function rlib_table_items(string $table, bool $archived): array
{
    $items = [];
    try {
        $db = Database::get();
        $cols = [];
        foreach ($db->query("SHOW COLUMNS FROM {$table}") as $c) {
            $cols[strtolower((string)$c['Field'])] = true;
        }
        if (!isset($cols['id'])) {
            return $items;
        }

        $select = ['id'];
        foreach (['report_title', 'report_category', 'generated_at', 'created_at', 'archived'] as $candidate) {
            if (isset($cols[$candidate])) {
                $select[] = $candidate;
            }
        }

        $where = isset($cols['archived']) ? (' WHERE archived = ' . ($archived ? '1' : '0')) : '';
        $order = isset($cols['generated_at']) ? ' ORDER BY generated_at DESC' : ' ORDER BY id DESC';

        $stmt = $db->query("SELECT " . implode(', ', $select) . " FROM {$table}{$where}{$order} LIMIT 500");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $category = strtolower(trim((string)($row['report_category'] ?? $table)));
            $storedTitle = (string)($row['report_title'] ?? '');
            $meta = rlib_title($category, $storedTitle, '');
            $generatedAt = (string)($row['generated_at'] ?? $row['created_at'] ?? '');

            $items[] = [
                'id' => 't:' . $table . ':' . (int)$row['id'],
                'source' => 'table',
                'table' => $table,
                'record_id' => (int)$row['id'],
                'file' => null,
                'title' => $meta['title'],
                'type' => $meta['type'],
                'description' => $meta['description'],
                'entity' => $meta['entity'],
                'period' => [
                    'label' => $meta['period_label'],
                    'mode' => $meta['period_mode'],
                    'known' => $meta['period_label'] !== null,
                ],
                'frequency' => $meta['frequency'],
                'generated_at' => $generatedAt,
                'generated_by' => null,
                'format' => 'PDF',
                'size_bytes' => null,
                'status' => $archived ? 'Archived' : 'Available',
                'archived' => $archived,
                'sections' => [],
            ];
        }
    } catch (Throwable $e) {
        // Table may not exist in this deployment; other sources still report.
    }
    return $items;
}

/**
 * Normalize a period filter (mode + optional custom range) into a
 * [start, end] day window for the covered period.
 */
function rlib_period_window(string $mode, string $start, string $end): ?array
{
    $now = new DateTime();
    switch (strtolower($mode)) {
        case 'monthly':
            return [$now->format('Y-m-01'), $now->format('Y-m-t')];
        case 'quarterly':
            $q = (int)ceil(((int)$now->format('n')) / 3);
            $s = new DateTime($now->format('Y') . '-' . str_pad((string)(($q - 1) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01');
            return [$s->format('Y-m-d'), $s->modify('+3 months')->modify('-1 day')->format('Y-m-d')];
        case 'semi_annually':
            $half = ((int)$now->format('n') <= 6) ? 1 : 2;
            $s = new DateTime($now->format('Y') . ($half === 1 ? '-01-01' : '-07-01'));
            return [$s->format('Y-m-d'), $s->modify('+6 months')->modify('-1 day')->format('Y-m-d')];
        case 'annually':
            return [$now->format('Y-01-01'), $now->format('Y-12-31')];
        case 'custom':
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                return $start <= $end ? [$start, $end] : [$end, $start];
            }
            return null;
        default:
            return null;
    }
}

/**
 * Apply the filter bar to a merged item list. Every filter is optional and
 * items missing a filter's metadata are excluded while that filter is active.
 */
function rlib_filter_items(array $items, array $f): array
{
    $q = strtolower(trim((string)($f['q'] ?? '')));
    $type = strtolower(trim((string)($f['type'] ?? '')));
    $entity = strtolower(trim((string)($f['entity'] ?? '')));
    $format = strtoupper(trim((string)($f['format'] ?? '')));
    $status = strtolower(trim((string)($f['status'] ?? '')));
    $periodMode = strtolower(trim((string)($f['period_mode'] ?? '')));
    $periodStart = (string)($f['period_start'] ?? '');
    $periodEnd = (string)($f['period_end'] ?? '');
    $from = (string)($f['gen_from'] ?? '');
    $to = (string)($f['gen_to'] ?? '');

    $window = rlib_period_window($periodMode, $periodStart, $periodEnd);

    return array_values(array_filter($items, static function (array $item) use ($q, $type, $entity, $format, $status, $window, $from, $to): bool {
        if ($q !== '') {
            $hay = strtolower(implode(' ', [
                (string)$item['title'],
                (string)$item['type'],
                (string)$item['description'],
                (string)$item['entity'],
                (string)($item['file'] ?? ''),
                (string)($item['period']['label'] ?? ''),
                (string)($item['generated_by'] ?? ''),
            ]));
            if (strpos($hay, $q) === false) {
                return false;
            }
        }
        if ($type !== '' && strtolower((string)$item['type']) !== $type) {
            return false;
        }
        if ($entity !== '') {
            if (stripos((string)$item['entity'], $entity) === false && stripos((string)$item['title'], $entity) === false) {
                return false;
            }
        }
        if ($format !== '' && strtoupper((string)$item['format']) !== $format) {
            return false;
        }
        if ($status !== '' && strtolower((string)$item['status']) !== $status) {
            return false;
        }
        if ($window !== null) {
            if (empty($item['period']['known']) || empty($item['period']['label'])) {
                return false; // period filter requires a known covered period
            }
            $ts = strtotime((string)$item['period']['label']);
            if ($ts === false) {
                return false;
            }
            $day = date('Y-m-d', $ts);
            if ($day < $window[0] || $day > $window[1]) {
                return false;
            }
        }
        $gen = substr((string)$item['generated_at'], 0, 10);
        if ($from !== '' && ($gen === '' || $gen < $from)) {
            return false;
        }
        if ($to !== '' && ($gen === '' || $gen > $to)) {
            return false;
        }
        return true;
    }));
}

/**
 * Build the complete merged library. $scope: recent | all | archived.
 */
function rlib_library(string $scope, array $filters): array
{
    $root = $_SERVER['DOCUMENT_ROOT'];

    $items = [];
    if ($scope === 'archived') {
        $items = array_merge(
            rlib_scan_files($root, '/archived_reports/', true),
            rlib_table_items('ai_trip_operations_reports', true),
            rlib_table_items('trip_operations_reports', true)
        );
    } else {
        $items = array_merge(
            rlib_scan_files($root, '/generated_reports/', false),
            rlib_table_items('ai_trip_operations_reports', false),
            rlib_table_items('trip_operations_reports', false)
        );
        if ($scope === 'recent') {
            $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
            $items = array_values(array_filter($items, static fn(array $i): bool => (string)$i['generated_at'] >= $cutoff));
        }
    }

    $items = rlib_filter_items($items, $filters);

    usort($items, static fn(array $a, array $b): int => strcmp((string)$b['generated_at'], (string)$a['generated_at']));

    return $items;
}

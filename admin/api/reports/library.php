<?php
declare(strict_types=1);

/**
 * Reports Center library endpoint.
 *
 * Returns the merged, server-filtered report library for the Reports Center
 * page. Read-only; every request is authorized as admin/super_admin.
 */

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/_library.php';

rlib_guard();

$scope = strtolower(trim((string)($_GET['scope'] ?? 'all')));
if (!in_array($scope, ['recent', 'all', 'archived'], true)) {
    $scope = 'all';
}

$filters = [
    'q' => (string)($_GET['q'] ?? ''),
    'type' => (string)($_GET['type'] ?? ''),
    'entity' => (string)($_GET['entity'] ?? ''),
    'period_mode' => (string)($_GET['period_mode'] ?? ''),
    'period_start' => (string)($_GET['period_start'] ?? ''),
    'period_end' => (string)($_GET['period_end'] ?? ''),
    'gen_from' => (string)($_GET['gen_from'] ?? ''),
    'gen_to' => (string)($_GET['gen_to'] ?? ''),
    'format' => (string)($_GET['format'] ?? ''),
    'status' => (string)($_GET['status'] ?? ''),
];

try {
    $items = rlib_library($scope, $filters);

    $counts = [
        'recent' => count(rlib_library('recent', [])),
        'all' => count(rlib_library('all', [])),
        'archived' => count(rlib_library('archived', [])),
    ];

    $typeSet = [];
    foreach (rlib_library($scope, []) as $item) {
        $typeSet[(string)$item['type']] = true;
    }
    $types = array_keys($typeSet);
    sort($types);

    rlib_respond([
        'success' => true,
        'scope' => $scope,
        'counts' => $counts,
        'types' => $types,
        'total' => count($items),
        'data' => $items,
    ]);
} catch (Throwable $e) {
    rlib_respond(['success' => false, 'message' => $e->getMessage()], 500);
}

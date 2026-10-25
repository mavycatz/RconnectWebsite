<?php declare(strict_types=1);

/**
 * Bus Management directory list endpoint.
 *
 * Server-side search/filter/sort/pagination across the buses table, with the
 * shared lifecycle-status counts the dashboard cards and filters display.
 * The status filter uses only stored lifecycle values; operational status is
 * returned separately and never drives the status buckets.
 * Admin/super_admin only.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/Bus/BusDirectoryModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Bus/BusFields.php';

use Admin\Models\Bus\BusDirectoryModel;
use Admin\Services\Bus\BusFields;

$actor = rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$sorts = ['bus_asc', 'bus_desc', 'newest', 'oldest', 'plate_asc', 'plate_desc'];

try {
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
        BusFields::ensureColumns(\App\Core\Database::get());
    } catch (Throwable $ignore) {
    }

    $search = trim((string)($_GET['search'] ?? ''));
    if (mb_strlen($search) > 120) {
        $search = mb_substr($search, 0, 120);
    }

    $status = strtolower(trim((string)($_GET['status'] ?? '')));
    if ($status !== '' && !isset(BusDirectoryModel::statusOptions()[$status])) {
        rmg_fail(400, 'Unknown status filter.');
    }

    $busType = trim((string)($_GET['bus_type'] ?? ''));
    if (mb_strlen($busType) > 50) {
        $busType = mb_substr($busType, 0, 50);
    }

    $route = trim((string)($_GET['route'] ?? ''));
    if (mb_strlen($route) > 120) {
        $route = mb_substr($route, 0, 120);
    }

    $driver = trim((string)($_GET['driver'] ?? ''));
    if (mb_strlen($driver) > 120) {
        $driver = mb_substr($driver, 0, 120);
    }

    $registeredFrom = trim((string)($_GET['registered_from'] ?? ''));
    $registeredTo = trim((string)($_GET['registered_to'] ?? ''));
    foreach ([$registeredFrom, $registeredTo] as $value) {
        if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            rmg_fail(400, 'Invalid date filter.');
        }
    }
    if ($registeredFrom !== '' && $registeredTo !== '' && $registeredFrom > $registeredTo) {
        [$registeredFrom, $registeredTo] = [$registeredTo, $registeredFrom];
    }

    $sort = strtolower(trim((string)($_GET['sort'] ?? 'bus_asc')));
    if (!in_array($sort, $sorts, true)) {
        $sort = 'bus_asc';
    }

    $perPage = (int)($_GET['per_page'] ?? 10);
    if (!in_array($perPage, [10, 25, 50, 100], true)) {
        $perPage = 10;
    }
    $page = max(1, (int)($_GET['page'] ?? 1));

    $model = new BusDirectoryModel();
    $result = $model->query([
        'search' => $search,
        'status' => $status,
        'bus_type' => $busType,
        'route' => $route,
        'driver' => $driver,
        'registered_from' => $registeredFrom,
        'registered_to' => $registeredTo,
        'sort' => $sort,
    ]);

    $rows = [];
    foreach ($result['rows'] as $row) {
        $row['can_manage'] = true;
        $rows[] = $row;
    }

    $total = count($rows);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);

    echo json_encode([
        'success' => true,
        'data' => $slice,
        'counts' => $result['counts'],
        'summary' => $result['summary'],
        'errors' => $result['errors'] ?? [],
        'statuses' => BusDirectoryModel::statusOptions(),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
        'filters' => [
            'search' => $search,
            'status' => $status,
            'bus_type' => $busType,
            'route' => $route,
            'driver' => $driver,
            'registered_from' => $registeredFrom,
            'registered_to' => $registeredTo,
            'sort' => $sort,
        ],
    ]);
} catch (Throwable $e) {
    error_log('BusManagement list failed: ' . $e->getMessage());
    rmg_fail(500, 'Could not load the bus directory.');
}

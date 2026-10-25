<?php declare(strict_types=1);

/**
 * Role Management directory list endpoint.
 *
 * Server-side role/search/status filtering and pagination across the seven
 * role sources. Admin/super_admin only.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/RoleManagement/RoleDirectoryModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/PersonnelFields.php';

use Admin\Models\RoleManagement\RoleDirectoryModel;

$actor = rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
        \Admin\Services\RoleManagement\PersonnelFields::ensureColumns(\App\Core\Database::get());
    } catch (Throwable $ignore) {
    }

    $roles = RoleDirectoryModel::roles();

    $role = strtolower(trim((string)($_GET['role'] ?? 'all')));
    if ($role === '') {
        $role = 'all';
    }
    if ($role !== 'all' && !isset($roles[$role])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown role filter.']);
        exit;
    }

    $search = trim((string)($_GET['search'] ?? ''));
    if (mb_strlen($search) > 120) {
        $search = mb_substr($search, 0, 120);
    }

    $status = strtolower(trim((string)($_GET['status'] ?? '')));
    if ($status !== '' && !isset(RoleDirectoryModel::statusOptions()[$status])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown status filter.']);
        exit;
    }

    $department = trim((string)($_GET['department'] ?? ''));
    if (mb_strlen($department) > 100) {
        $department = mb_substr($department, 0, 100);
    }

    $employment = trim((string)($_GET['employment'] ?? ''));
    if (mb_strlen($employment) > 32) {
        $employment = mb_substr($employment, 0, 32);
    }

    $createdFrom = trim((string)($_GET['created_from'] ?? ''));
    $createdTo = trim((string)($_GET['created_to'] ?? ''));
    foreach (['created_from' => $createdFrom, 'created_to' => $createdTo] as $key => $value) {
        if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid date filter.']);
            exit;
        }
    }
    if ($createdFrom !== '' && $createdTo !== '' && $createdFrom > $createdTo) {
        [$createdFrom, $createdTo] = [$createdTo, $createdFrom];
    }

    $sort = strtolower(trim((string)($_GET['sort'] ?? 'newest')));
    if (!in_array($sort, ['newest', 'oldest', 'name_asc', 'name_desc'], true)) {
        $sort = 'newest';
    }

    $perPage = (int)($_GET['per_page'] ?? 10);
    if (!in_array($perPage, [10, 25, 50, 100], true)) {
        $perPage = 10;
    }
    $page = max(1, (int)($_GET['page'] ?? 1));

    $model = new RoleDirectoryModel();
    $result = $model->query([
        'role' => $role,
        'search' => $search,
        'status' => $status,
        'department' => $department,
        'employment' => $employment,
        'created_from' => $createdFrom,
        'created_to' => $createdTo,
        'sort' => $sort,
    ]);

    $rows = [];
    foreach ($result['rows'] as $row) {
        $row['can_manage'] = rmg_can_manage_role($actor, (string)$row['role']);
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
        'summary' => $result['summary'] ?? [],
        'errors' => $result['errors'] ?? [],
        'roles' => $roles,
        'statuses' => RoleDirectoryModel::statusOptions(),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
        'is_super' => rmg_is_super($actor),
    ]);
} catch (Throwable $e) {
    error_log('RoleManagement list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load the directory.']);
}

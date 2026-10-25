<?php declare(strict_types=1);

/**
 * Role Management CSV export (read-only).
 *
 * Applies the same role/search/status filters as list.php, reusing
 * RoleDirectoryModel so the export always matches the directory. Pagination
 * is intentionally ignored: the export covers the whole filtered result.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/RoleManagement/RoleDirectoryModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/RoleListingCsv.php';

use Admin\Models\RoleManagement\RoleDirectoryModel;
use Admin\Services\RoleManagement\RoleListingCsv;

$actor = rmg_actor();

header('Cache-Control: no-store');

try {
    $role = strtolower(trim((string)($_GET['role'] ?? 'all')));
    if ($role === '') {
        $role = 'all';
    }
    if ($role !== 'all' && !isset(RoleDirectoryModel::roles()[$role])) {
        rmg_fail(400, 'Unknown role filter.');
    }

    $search = trim((string)($_GET['search'] ?? ''));
    if (mb_strlen($search) > 120) {
        $search = mb_substr($search, 0, 120);
    }

    $status = strtolower(trim((string)($_GET['status'] ?? '')));
    if ($status !== '' && !isset(RoleDirectoryModel::statusOptions()[$status])) {
        rmg_fail(400, 'Unknown status filter.');
    }

    $model = new RoleDirectoryModel();
    $result = $model->query(['role' => $role, 'search' => $search, 'status' => $status]);
    $rows = $result['rows'] ?? [];

    if (!$rows) {
        $message = 'No data available to export.';
        if ($role !== 'all' && !empty($result['errors'][$role])) {
            $message .= ' ' . trim((string)$result['errors'][$role]);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    $filename = 'role-management-' . ($role === 'all' ? 'all' : $role) . '-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel reads the accented names correctly.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, RoleListingCsv::headers(), ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, RoleListingCsv::row($row), ',', '"', '');
    }
    fclose($out);
    exit;
} catch (Throwable $e) {
    error_log('RoleManagement export failed: ' . $e->getMessage());
    rmg_fail(500, 'Could not export the directory. Please try again.');
}

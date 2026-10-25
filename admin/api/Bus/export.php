<?php declare(strict_types=1);

/**
 * Bus Management CSV export (read-only).
 *
 * Applies the same search/filter set as list.php, reusing BusDirectoryModel
 * so the export always matches the directory. Pagination is intentionally
 * ignored: the export covers the whole filtered result.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/Bus/BusDirectoryModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Bus/BusListingCsv.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Bus/BusFields.php';

use Admin\Models\Bus\BusDirectoryModel;
use Admin\Services\Bus\BusFields;
use Admin\Services\Bus\BusListingCsv;

rmg_actor();

header('Cache-Control: no-store');

$sorts = ['bus_asc', 'bus_desc', 'newest', 'oldest', 'plate_asc', 'plate_desc'];

try {
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
        BusFields::ensureColumns(\App\Core\Database::get());
    } catch (Throwable $ignore) {
    }

    $status = strtolower(trim((string)($_GET['status'] ?? '')));
    if ($status !== '' && !isset(BusDirectoryModel::statusOptions()[$status])) {
        rmg_fail(400, 'Unknown status filter.');
    }

    $sort = strtolower(trim((string)($_GET['sort'] ?? 'bus_asc')));
    if (!in_array($sort, $sorts, true)) {
        $sort = 'bus_asc';
    }

    $model = new BusDirectoryModel();
    $result = $model->query([
        'search' => trim((string)($_GET['search'] ?? '')),
        'status' => $status,
        'bus_type' => trim((string)($_GET['bus_type'] ?? '')),
        'route' => trim((string)($_GET['route'] ?? '')),
        'driver' => trim((string)($_GET['driver'] ?? '')),
        'registered_from' => trim((string)($_GET['registered_from'] ?? '')),
        'registered_to' => trim((string)($_GET['registered_to'] ?? '')),
        'sort' => $sort,
    ]);

    $rows = $result['rows'] ?? [];
    if (!$rows) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'No data available to export.']);
        exit;
    }

    $filename = 'bus-management-' . ($status !== '' ? $status : 'all') . '-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel reads the values correctly.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, BusListingCsv::headers(), ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, BusListingCsv::row($row), ',', '"', '');
    }
    fclose($out);
    exit;
} catch (Throwable $e) {
    error_log('BusManagement export failed: ' . $e->getMessage());
    rmg_fail(500, 'Could not export the bus directory. Please try again.');
}

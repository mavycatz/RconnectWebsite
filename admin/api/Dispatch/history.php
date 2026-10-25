<?php declare(strict_types=1);

/**
 * Dispatch queue history for an operating date.
 *
 *   GET ?date=YYYY-MM-DD[&limit=25]
 *
 * Returns the page's audit activity (queue updates, publications, overrides)
 * for the date plus the most recently published orders. Admin/super_admin
 * only. Read-only.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Dispatch/DispatchQueueService.php';

use Admin\Services\Dispatch\DispatchQueueService;

rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$date = trim((string)($_GET['date'] ?? ''));
if ($date !== '' && DispatchQueueService::normalizeDate($date) === null) {
    rmg_fail(422, 'Select a valid operating date.');
}
if ($date === '') {
    $date = date('Y-m-d');
}
$limit = (int)($_GET['limit'] ?? 25);

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
    $db = \App\Core\Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'Queue history is unavailable. Please try again.');
}

try {
    $service = new DispatchQueueService($db);
    $data = $service->history($date, $limit);
} catch (Throwable $e) {
    error_log('Dispatch queue history read failed: ' . $e->getMessage());
    rmg_fail(500, 'Queue history could not be loaded. Please try again.');
}

echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

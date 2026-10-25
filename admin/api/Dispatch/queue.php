<?php declare(strict_types=1);

/**
 * Admin Bus Assignment & Dispatch Queue: page payload for an operating date.
 *
 *   GET ?date=YYYY-MM-DD   (defaults to today, Asia/Manila)
 *
 * Admin/super_admin only. Read-only.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Dispatch/DispatchQueueService.php';

use Admin\Services\Dispatch\DispatchQueueService;

rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
    $db = \App\Core\Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'The dispatch queue is unavailable. Please try again.');
}

$date = trim((string)($_GET['date'] ?? ''));
if ($date !== '' && DispatchQueueService::normalizeDate($date) === null) {
    rmg_fail(422, 'Select a valid operating date.');
}
if ($date === '') {
    $date = date('Y-m-d');
}

try {
    $service = new DispatchQueueService($db);
    $data = $service->queueData($date);
} catch (Throwable $e) {
    error_log('Dispatch queue read failed: ' . $e->getMessage());
    rmg_fail(500, 'The dispatch queue could not be loaded. Please try again.');
}

echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

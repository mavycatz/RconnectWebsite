<?php declare(strict_types=1);

/**
 * Save the draft dispatch order for an operating date.
 *
 *   POST {date: "YYYY-MM-DD", order: ["BUS-2001", "Bus-1001", ...]}
 *
 * Positions are assigned 1..n in the submitted order. Saving a draft
 * invalidates any previous publication for the date until it is published
 * again. Never creates trips - only bus_dispatch_queue rows change.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Dispatch/DispatchQueueService.php';

use Admin\Services\Dispatch\DispatchQueueService;

$actor = rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rmg_fail(405, 'Method not allowed.');
}

$input = rmg_input();
$date = trim((string)($input['date'] ?? ''));
$order = $input['order'] ?? [];
if (!is_array($order)) {
    rmg_fail(422, 'The queue order must be a list of bus identifiers.');
}
if (DispatchQueueService::normalizeDate($date) === null) {
    rmg_fail(422, 'Select a valid operating date.');
}

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
    $db = \App\Core\Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'The dispatch queue is unavailable. Please try again.');
}

try {
    $service = new DispatchQueueService($db);
    $result = $service->saveDraft($date, $order, $actor['user']);
} catch (Throwable $e) {
    error_log('Dispatch queue save failed: ' . $e->getMessage());
    rmg_fail(500, 'The dispatch queue could not be saved. Please try again.');
}

if (!$result['success']) {
    rmg_fail(409, (string)$result['error']);
}

echo json_encode([
    'success' => true,
    'message' => 'Queue saved as a draft.',
    'data' => $result['data'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

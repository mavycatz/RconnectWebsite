<?php declare(strict_types=1);

/**
 * Manual queue-position override for a queued bus, with a recorded reason.
 *
 *   POST {date, bus_id, position, reason}
 *
 * The override invalidates the date's publication until it is published
 * again. Never creates trips.
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
$busId = trim((string)($input['bus_id'] ?? ''));
$position = (int)($input['position'] ?? 0);
$reason = trim((string)($input['reason'] ?? ''));

if (DispatchQueueService::normalizeDate($date) === null) {
    rmg_fail(422, 'Select a valid operating date.');
}
if ($busId === '' || !DispatchQueueService::busIdIsValid($busId)) {
    rmg_fail(422, 'Select a valid bus to override.');
}
if ($position < 1) {
    rmg_fail(422, 'Enter a queue position of 1 or higher.');
}
if (mb_strlen($reason) < 5) {
    rmg_fail(422, 'A reason for this override is required (at least 5 characters).');
}

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
    $db = \App\Core\Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'The dispatch queue is unavailable. Please try again.');
}

try {
    $service = new DispatchQueueService($db);
    $result = $service->overridePosition($date, $busId, $position, $reason, $actor['user']);
} catch (Throwable $e) {
    error_log('Dispatch queue override failed: ' . $e->getMessage());
    rmg_fail(500, 'The queue override could not be saved. Please try again.');
}

if (!$result['success']) {
    rmg_fail(409, (string)$result['error']);
}

echo json_encode([
    'success' => true,
    'message' => 'Queue position overridden.',
    'data' => $result['data'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

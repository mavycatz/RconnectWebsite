<?php declare(strict_types=1);

/**
 * Publish the saved dispatch queue for an operating date as the official
 * order the dispatcher follows.
 *
 *   POST {date: "YYYY-MM-DD"}
 *
 * Blocked while any queued bus is unavailable (archived, inactive, under
 * maintenance, out of service). Never creates trips.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_security_guard.php';
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
if (DispatchQueueService::normalizeDate($date) === null) {
    rmg_fail(422, 'Select a valid operating date.');
}

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
    $db = \App\Core\Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'The dispatch queue is unavailable. Please try again.');
}

/* Publishing the official order requires the Security Authorization Code:
   a single-use token bound to this admin, the publish action, the dispatch
   target, and this exact payload. */
rmg_guard_require('publish', 'dispatch');

try {
    $service = new DispatchQueueService($db);
    $result = $service->publish($date, $actor['user']);
} catch (Throwable $e) {
    error_log('Dispatch queue publish failed: ' . $e->getMessage());
    rmg_fail(500, 'The dispatch queue could not be published. Please try again.');
}

if (!$result['success']) {
    if (!empty($result['blockers'])) {
        header('X-Dispatch-Blockers: ' . count($result['blockers']));
    }
    $payload = [
        'success' => false,
        'message' => (string)$result['error'],
        'blockers' => $result['blockers'],
    ];
    http_response_code(409);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Official dispatch order published.',
    'data' => $result['data'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

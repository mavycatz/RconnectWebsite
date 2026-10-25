<?php declare(strict_types=1);

/**
 * Mark a bus available / under maintenance / out of service from the dispatch
 * queue page.
 *
 *   POST {bus_id, operational_status, reason, date?}
 *
 * Admin/super_admin only; the change is audited on the bus history. Never
 * creates trips.
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
$busId = trim((string)($input['bus_id'] ?? ''));
$status = trim((string)($input['operational_status'] ?? ''));
$reason = trim((string)($input['reason'] ?? ''));
$date = trim((string)($input['date'] ?? ''));

if ($busId === '' || !DispatchQueueService::busIdIsValid($busId)) {
    rmg_fail(422, 'Select a valid bus.');
}
if (!in_array($status, DispatchQueueService::ADMIN_SETTABLE_OPERATIONAL, true)) {
    rmg_fail(422, 'Select a valid availability status.');
}
if (mb_strlen($reason) < 5) {
    rmg_fail(422, 'A reason for this change is required (at least 5 characters).');
}
if ($date !== '' && DispatchQueueService::normalizeDate($date) === null) {
    rmg_fail(422, 'Select a valid operating date.');
}
if ($date === '') {
    $date = date('Y-m-d');
}

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
    $db = \App\Core\Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'Bus availability is unavailable. Please try again.');
}

try {
    $service = new DispatchQueueService($db);
    $result = $service->setOperationalStatus($busId, $status, $reason, $date, $actor['user']);
} catch (Throwable $e) {
    error_log('Dispatch queue availability change failed: ' . $e->getMessage());
    rmg_fail(500, 'The bus availability could not be changed. Please try again.');
}

if (!$result['success']) {
    rmg_fail(409, (string)$result['error']);
}

echo json_encode([
    'success' => true,
    'message' => $result['unchanged']
        ? 'Bus ' . $busId . ' already has that availability.'
        : 'Bus availability updated.',
    'bus_id' => $busId,
    'operational_status' => $status,
    'data' => $result['data'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

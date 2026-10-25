<?php declare(strict_types=1);

/**
 * Assign (or move) the standing driver of a bus from the dispatch queue page.
 *
 *   POST {bus_id, driver_id, allow_move?, date?}
 *
 * Blocked for unavailable drivers (inactive / on leave / unavailable),
 * maintenance / out-of-service buses, and buses with an active trip on the
 * date. Conflicts when the driver is bound to another bus report the other
 * bus; the client confirms and retries with allow_move=1.
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
$driverId = trim((string)($input['driver_id'] ?? ''));
$date = trim((string)($input['date'] ?? ''));
$allowMove = filter_var($input['allow_move'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($busId === '' || !DispatchQueueService::busIdIsValid($busId)) {
    rmg_fail(422, 'Select a valid bus.');
}
if ($driverId === '') {
    rmg_fail(422, 'Select a driver to assign.');
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
    rmg_fail(500, 'Driver assignment is unavailable. Please try again.');
}

try {
    $service = new DispatchQueueService($db);
    $result = $service->assignDriver($busId, $date, $driverId, $allowMove, $actor['user']);
} catch (Throwable $e) {
    error_log('Dispatch queue driver assignment failed: ' . $e->getMessage());
    rmg_fail(500, 'The driver assignment could not be saved. Please try again.');
}

if (!$result['success']) {
    $code = $result['conflict_bus'] !== null ? 'driver_conflict' : 'assignment_blocked';
    http_response_code(409);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'code' => $code,
        'conflict_bus' => $result['conflict_bus'],
        'message' => (string)$result['error'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Driver assignment saved.',
    'bus_id' => $busId,
    'driver_id' => $driverId,
    'moved_from' => $result['moved_from'],
    'data' => $result['data'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

<?php declare(strict_types=1);

/**
 * Assign a bus - and optionally its driver - to a terminal for an operating
 * date from the dispatch queue page.
 *
 *   POST {date, bus_id, terminal_id, driver_id?}
 *
 * The assignment is the bus's queue entry for that terminal. Blocked for
 * ineligible buses/drivers, unknown terminals, published dates, and entities
 * already on an active trip (they keep their origin terminal until the arrival
 * is confirmed). Never creates trips - only the per-date queue and driver
 * assignment records change.
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
$terminalId = (int)($input['terminal_id'] ?? 0);
$driverTerminalId = (int)($input['driver_terminal_id'] ?? 0);
$date = trim((string)($input['date'] ?? ''));

if ($busId === '' || !DispatchQueueService::busIdIsValid($busId)) {
    rmg_fail(422, 'Select a valid bus.');
}
if ($terminalId <= 0) {
    rmg_fail(422, 'Select a terminal to assign.');
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
    rmg_fail(500, 'Terminal assignment is unavailable. Please try again.');
}

try {
    $service = new DispatchQueueService($db);
    $result = $service->assignTerminal(
        $date,
        $busId,
        $terminalId,
        $driverId !== '' ? $driverId : null,
        $actor['user'],
        $driverTerminalId > 0 ? $driverTerminalId : null
    );
} catch (Throwable $e) {
    error_log('Dispatch queue terminal assignment failed: ' . $e->getMessage());
    rmg_fail(500, 'The terminal assignment could not be saved. Please try again.');
}

if (!$result['success']) {
    rmg_fail(409, (string)$result['error']);
}

echo json_encode([
    'success' => true,
    'message' => 'Terminal assignment saved.',
    'bus_id' => $busId,
    'terminal_id' => $terminalId,
    'driver_id' => $driverId !== '' ? $driverId : null,
    'driver_terminal_id' => $driverTerminalId > 0 ? $driverTerminalId : null,
    'data' => $result['data'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

<?php declare(strict_types=1);

/**
 * Next Bus ID preview for the Add Bus form.
 *
 * Returns the ID the next bus would receive. The value is a preview only —
 * the real ID is allocated atomically when the bus is saved, so a number
 * claimed by another administrator in the meantime is skipped automatically.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/SecurityCodeService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/UserIdService.php';

use Admin\Services\RoleManagement\SecurityCodeService;
use Admin\Services\RoleManagement\UserIdService;
use App\Core\Database;

$actor = rmg_actor();

header('Cache-Control: no-store');

if (!SecurityCodeService::actionAllowed($actor['role'], 'add', 'bus')) {
    rmg_fail(403, 'You are not allowed to register buses.');
}

try {
    $db = Database::get();
} catch (Throwable $e) {
    rmg_fail(500, 'Bus ID service is unavailable. Please try again.');
}

try {
    UserIdService::ensureSchema($db);
    $service = new UserIdService($db);

    echo json_encode([
        'success' => true,
        'user_id' => $service->preview('bus'),
        'bus_id' => $service->preview('bus'),
        'role' => 'bus',
        'preview' => true,
    ]);
} catch (Throwable $e) {
    error_log('NextBusId preview failed: ' . $e->getMessage());
    rmg_fail(500, 'Could not generate the next Bus ID. Please try again.');
}

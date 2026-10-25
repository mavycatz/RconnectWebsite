<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/RoleManagement/UserIdService.php';

use App\Core\Database;
use Admin\Services\RoleManagement\UserIdService;

header('Content-Type: application/json');

try {
    $conn = Database::get();

    // Determine the numbering scope from the requested role.
    $role = strtolower(trim((string)($_GET['role'] ?? 'inspector')));
    $scope = $role === 'dispatcher' ? 'dispatcher' : 'inspector';

    $service = new UserIdService($conn);

    echo json_encode([
        'success' => true,
        'inspector_id' => $service->preview($scope)
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

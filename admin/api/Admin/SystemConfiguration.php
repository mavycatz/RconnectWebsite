<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Admin/SystemConfiguration.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Admin/SystemConfiguration.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Admin/SystemConfiguration.php';

use App\Auth\Auth;
use App\Auth\Session;
use App\Core\Database;

Session::start();

header(
    'Content-Type: application/json'
);

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$configActor = Auth::user() ?: [];
if (!in_array(strtolower((string)($configActor['role'] ?? '')), ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$conn =
    Database::get();

$model =
    new SystemConfigurationModel(
        $conn
    );

$service =
    new SystemConfigurationService(
        $model
    );

$controller =
    new SystemConfigurationController(
        $service
    );

$action = strtolower(trim((string)($_GET['action'] ?? '')));

$writeActions = [
    'addvehicletype', 'updatevehicletype', 'deletevehicletype',
    'savefuelprices', 'addfuelprice', 'deletefuelprice',
    'addviolationcategory', 'updateviolationcategory', 'deleteviolationcategory',
    'addviolation', 'updateviolation', 'deleteviolation',
];

ob_start();
$controller->handle();
$response = (string)ob_get_clean();
echo $response;

if (in_array($action, $writeActions, true)) {
    $decoded = json_decode($response, true);
    if (is_array($decoded) && ($decoded['success'] ?? false) === true) {
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';
            $body = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($body)) {
                $body = $_POST ?: $_GET;
            }
            unset($body['prices']);
            $audit = new \Finance\Models\AuditTrailModel();
            $audit->log(
                'System Configuration',
                $action !== '' ? $action : 'update',
                'System configuration change: ' . ($action !== '' ? $action : 'update'),
                isset($configActor['id']) ? (int)$configActor['id'] : null,
                $configActor['username'] ?? null,
                $configActor['role'] ?? null,
                null,
                null,
                $body,
                'Success'
            );
        } catch (Throwable $ignore) {
        }
    }
}

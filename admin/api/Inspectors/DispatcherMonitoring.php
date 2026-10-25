<?php

/**
 * Dispatcher Monitoring API (admin-only).
 *
 * Actions: live | activity | history | detail | resolve
 * Lives inside the existing Inspector admin area; no separate dispatcher module.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

header('Content-Type: application/json');

$role = strtolower(trim((string)Auth::role()));
if (!Auth::check() || !in_array($role, ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/Inspectors/DispatcherMonitoringModel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/Inspectors/DispatcherMonitoring.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/Controllers/Inspectors/DispatcherMonitoring.php';

$conn = \App\Core\Database::tryGet();
if ($conn === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable']);
    exit;
}

$model = new DispatcherMonitoringModel($conn);
$service = new DispatcherMonitoringService($model);
$controller = new DispatcherMonitoringController($service);

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'live':
        $controller->live();
        break;
    case 'activity':
        $controller->activity();
        break;
    case 'bus_problems':
        $controller->busProblems();
        break;
    case 'history':
        $controller->history();
        break;
    case 'detail':
        $controller->detail();
        break;
    case 'resolve':
        $controller->resolve();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Admin/FarePricing.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Admin/FarePricing.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/Admin/FarePricing.php';

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

$fareActor = Auth::user() ?: [];
if (!in_array(strtolower((string)($fareActor['role'] ?? '')), ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$conn =
    Database::get();

$model =
    new FarePricingModel(
        $conn
    );

$service =
    new FarePricingService(
        $model
    );

$controller =
    new FarePricingController(
        $service
    );

$action =
    $_GET['action']
    ?? 'get';

ob_start();

switch(
    $action
){

    case 'save':

        $controller
            ->saveFarePricing();

        break;

    case 'add':

        $controller
            ->addFare();

        break;

    case 'update':

        $controller
            ->updateFare();

        break;

    case 'delete':

        $controller
            ->deleteFare();

        break;

    default:

        $controller
            ->getFarePricing();

        break;

}

$response = (string)ob_get_clean();
echo $response;

if (in_array(strtolower((string)$action), ['save', 'add', 'update', 'delete'], true)) {
    $decoded = json_decode($response, true);
    if (is_array($decoded) && ($decoded['success'] ?? false) === true) {
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';
            $body = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($body)) {
                $body = $_POST ?: $_GET;
            }
            unset($body['fares']);
            $audit = new \Finance\Models\AuditTrailModel();
            $audit->log(
                'System Configuration',
                'Fare Pricing ' . ucfirst(strtolower((string)$action)),
                'Fare pricing change: ' . strtolower((string)$action),
                isset($fareActor['id']) ? (int)$fareActor['id'] : null,
                $fareActor['username'] ?? null,
                $fareActor['role'] ?? null,
                null,
                null,
                $body,
                'Success'
            );
        } catch (Throwable $ignore) {
        }
    }
}

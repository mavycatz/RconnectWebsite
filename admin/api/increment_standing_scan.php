<?php
require_once __DIR__ . '/../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();
header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../appModels/QR_Bus_Passenger.php';

$bus_id = $_GET['bus_id'] ?? '';
if (!$bus_id) {
    echo json_encode(['error' => 'Bus ID missing']);
    exit;
}

$model = new QR_Bus_Passenger();
$result = $model->incrementStandingScan($bus_id);

echo json_encode($result);

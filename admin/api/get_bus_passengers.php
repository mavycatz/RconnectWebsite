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

// Include the model
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Models/QR_Bus_Passenger.php';

try {
    $bus_id = $_GET['bus_id'] ?? '';
    if (!$bus_id) {
        echo json_encode(['error' => 'Bus ID missing']);
        exit;
    }

    $model = new QR_Bus_Passenger();
    $passengers = $model->getPassengersByBus($bus_id);

    echo json_encode([
        'bus_id' => $bus_id,
        'passenger_count' => count($passengers),
        'passengers' => $passengers
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to fetch passengers.']);
}

<?php
require_once __DIR__ . '/../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();
header('Content-Type: application/json');

if (!Auth::check() || Auth::user()['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../app/Models/BusSeat.php';

$bus_id = $_GET['bus_id'] ?? '';

if (!$bus_id) {
    echo json_encode(['status'=>'error','message'=>'No bus_id provided']);
    exit;
}

$seatModel = new BusSeat();
try {
    $seats = $seatModel->getByBus($bus_id);
    echo json_encode(['status'=>'success','seats'=>$seats]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
exit;

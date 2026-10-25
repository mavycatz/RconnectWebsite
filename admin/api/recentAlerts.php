<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Bootstrap.php';
require_once __DIR__ . '/../../app/auth/Session.php';
require_once __DIR__ . '/../../app/auth/Auth.php';
require_once __DIR__ . '/../Controllers/DashboardController.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Controllers\DashboardController;

Session::start();

header('Content-Type: application/json');

if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Fetch latest 5 alerts
$alerts = DashboardController::getAlerts();

echo json_encode($alerts);
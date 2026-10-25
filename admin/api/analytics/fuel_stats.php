<?php

declare(strict_types=1);

/* Buffer any stray PHP output so it can never corrupt the JSON body. */
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/Controllers/DashboardController.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;
use Admin\Controllers\DashboardController;

Session::start();

header('Content-Type: application/json');

// Check if admin
if (!Auth::check() || !in_array(Auth::user()['role'] ?? null, ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get filters from query
$filters = [
    'busNumber' => $_GET['busNumber'] ?? '',
    'day'       => $_GET['day'] ?? '',
    'month'     => $_GET['month'] ?? '',
    'year'      => $_GET['year'] ?? '',
    'category'  => $_GET['category'] ?? '',
    'fromDate'  => $_GET['fromDate'] ?? '',
    'toDate'    => $_GET['toDate'] ?? '',
    'fromTime'  => $_GET['fromTime'] ?? '',
    'toTime'    => $_GET['toTime'] ?? '',
];

try {
    $db = Database::get();
    $controller = new DashboardController($db);
    $payload = json_encode($controller->getFuelStats($filters));
} catch (\Throwable $e) {
    error_log('Fuel stats error: ' . $e->getMessage());
    http_response_code(500);
    $payload = json_encode([
        'success' => false,
        'message' => 'Unable to load fuel statistics.'
    ]);
}

ob_get_clean();

echo $payload;
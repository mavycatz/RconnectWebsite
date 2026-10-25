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

// Check login
if (!Auth::check()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Auth::user();
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Collect filters safely
$filters = [
    'busNumber' => htmlspecialchars($_GET['busNumber'] ?? ''),
    'day'       => (int)($_GET['day'] ?? 0),
    'month'     => (int)($_GET['month'] ?? 0),
    'year'      => (int)($_GET['year'] ?? 0),
    'category'  => htmlspecialchars($_GET['category'] ?? ''),
    'fromDate'  => $_GET['fromDate'] ?? '',
    'toDate'    => $_GET['toDate'] ?? '',
    'fromTime'  => $_GET['fromTime'] ?? '',
    'toTime'    => $_GET['toTime'] ?? '',
];

// Fetch filtered summary data
try {
    $data = DashboardController::getSummaryData($filters);
    $data = array_merge([
        'totalRevenue'    => 0,
        'avgRevenue'      => 0,
        'activeBuses'     => 0,
        'totalPassengers' => 0
    ], $data);

    echo json_encode($data);
} catch (\Exception $e) {
    echo json_encode(['error' => 'Failed to fetch summary: ' . $e->getMessage()]);
}
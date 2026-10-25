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

// Check if admin
if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'super_admin'])) {
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
    // 1️⃣ Revenue vs Profit vs Expenses (Area chart)
    $revenueStats = DashboardController::getRevenueStats($filters);

    // 2️⃣ Daily Trips (Bar chart)
    $dailyTrips = DashboardController::getDailyTrips($filters);

    // 3️⃣ Passenger Distribution (Horizontal bar)
    $passengerDistribution = DashboardController::getPassengerDistribution($filters);

    // 4️⃣ Summary cards (optional, so charts + cards can sync)
    $summary = DashboardController::getSummaryData($filters);

    echo json_encode([
        'revenueStats' => $revenueStats ?: [],
        'dailyTrips' => $dailyTrips ?: [],
        'passengerDistribution' => $passengerDistribution ?: [],
        'summary' => $summary ?: []
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error: '.$e->getMessage()]);
}
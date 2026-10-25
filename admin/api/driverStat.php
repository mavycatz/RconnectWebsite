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
$user = Auth::user();
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Database connection
$db = App\Core\Database::get();

// Get full_name from query string
$fullName = $_GET['driver'] ?? '';

$data = [];

if ($fullName !== '') {
    // Lookup driver_id by full_name
    $stmt = $db->prepare("SELECT driver_id FROM drivers WHERE full_name = :full_name LIMIT 1");
    $stmt->execute(['full_name' => $fullName]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($driver) {
        $driverId = $driver['driver_id'];
        $stats = DashboardController::getDriverStats($driverId);
        $data = [
            'revenue' => array_map(fn($s) => ['month' => $s['month'], 'amount' => (int)$s['revenue']], $stats),
            'performance' => array_map(fn($s) => ['trips' => (int)$s['trips'], 'absence' => 0], $stats)
        ];
    }
} else {
    // Fetch all drivers
    $stmt = $db->query("SELECT driver_id, full_name FROM drivers ORDER BY full_name ASC");
    $allDrivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $revenue = [];
    $performance = [];

    foreach ($allDrivers as $d) {
        $stats = DashboardController::getDriverStats($d['driver_id']);
        $totalRevenue = array_sum(array_map(fn($s) => (int)$s['revenue'], $stats));
        $totalTrips = array_sum(array_map(fn($s) => (int)$s['trips'], $stats));

        $revenue[] = [
            'month' => $d['full_name'],
            'amount' => $totalRevenue
        ];

        $performance[] = [
            'trips' => $totalTrips,
            'absence' => 0
        ];
    }

    $data = [
        'revenue' => $revenue,
        'performance' => $performance
    ];
}

echo json_encode($data);
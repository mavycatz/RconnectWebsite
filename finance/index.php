<?php

declare(strict_types=1);

$validToken = require __DIR__ . '/../admin/config/access_token.php';

define('FINANCE_MODULE', true);
require_once __DIR__ . '/../app/init.php';

use App\Auth\Auth;

$user = Auth::user();
if (!in_array($user['role'] ?? '', ['finance_manager', 'finance_officer'])) {
    header('Location: /public/login/index.php');
    exit;
}

$route = $_GET['route'] ?? '';
$token = $_GET['token'] ?? '';

if ($token !== $validToken) {
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

$_SESSION['admin_access_token'] = $validToken;
setcookie('admin_token', $validToken, [
    'expires' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
]);

$pageMap = [
    'dashboard' => __DIR__ . '/dashboard.php',
    'analytics-dashboard' => __DIR__ . '/analytics_dashboard.php',
    'predictive-analysis' => __DIR__ . '/predictive_analysis.php',
    'remittance' => __DIR__ . '/remittance.php',
    'history' => __DIR__ . '/history.php',
    'discrepancies' => __DIR__ . '/discrepancies.php',
    'online-payment-verification' => __DIR__ . '/online_payment_verification.php',
    'revenue-overview' => __DIR__ . '/revenueOverview.php',
    'financial-intelligence' => __DIR__ . '/financial_intelligence.php',
    'fuel-cost-analytics' => __DIR__ . '/fuel_cost_analytics.php',
    'fleet-performance' => __DIR__ . '/fleet_performance.php',
    'financial-reports' => __DIR__ . '/financialreports.php',
    'exports-audits' => __DIR__ . '/exportandaudits.php',
    'documentation' => __DIR__ . '/documentation.php',
    'download-reports' => __DIR__ . '/downloadreports.php',
    'profile' => __DIR__ . '/profile.php',
    'settings' => __DIR__ . '/settings.php',
    'financial-cost-settings' => __DIR__ . '/financial-cost-settings.php',
];

if (isset($pageMap[$route])) {
    $file = $pageMap[$route];
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

http_response_code(404);
echo '404 Not Found';

<?php

declare(strict_types=1);

/* Buffer any stray PHP output (notices/deprecations with display_errors on)
   so it can never corrupt the JSON body returned to the dashboard cards. */
ob_start();

require_once __DIR__ . '/init.php';

use App\Core\Database;
use Admin\Controllers\DashboardController;

ini_set('display_errors',1);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/Controllers/DashboardController.php';

header('Content-Type: application/json');

try {
    $db = Database::get();
    $controller = new DashboardController($db);
    $payload = json_encode($controller->getStats());
} catch (\Throwable $e) {
    error_log('Dashboard stats error: ' . $e->getMessage());
    http_response_code(500);
    $payload = json_encode([
        'success' => false,
        'message' => 'Unable to load dashboard data.'
    ]);
}

ob_get_clean();

echo $payload;
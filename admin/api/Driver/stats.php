<?php

declare(strict_types=1);

/* Buffer any stray PHP output (notices/deprecations with display_errors on)
   so it can never corrupt the JSON body returned to the dashboard cards. */
ob_start();

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/cache.php';

use App\Core\Database;
use Admin\Controllers\DashboardController;

ini_set('display_errors', '0');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/Controllers/DashboardController.php';

header('Content-Type: application/json');

try {
    $cacheKey = dashboard_cache_key('stats', $_GET);
    $cached = dashboard_cache_get($cacheKey, 45);

    if ($cached !== null) {
        $payload = json_encode($cached);
    } else {
        $db = Database::get();
        $controller = new DashboardController($db);
        $result = $controller->getStats();
        if (($result['success'] ?? false) === true) {
            dashboard_cache_save($cacheKey, $result, 45);
        }
        $payload = json_encode($result);
    }
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
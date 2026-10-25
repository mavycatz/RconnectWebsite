<?php
declare(strict_types = 1);

define('APP_DEBUG', true);
define('BASE_PATH', dirname(__DIR__, 3));

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php_errors.log');

set_exception_handler(function ($e) {
    if (APP_DEBUG) {
        echo "<pre style='background:#fff3f3;padding:20px;border:1px solid #ffb3b3;border-radius:8px;'>";
        echo "UNCAUGHT EXCEPTION\n\n";
        echo "Message: " . $e->getMessage() . "\n\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n\n";
        echo "Stack Trace:\n" . $e->getTraceAsString();
        echo "</pre>";
    } else {
        echo "Something went wrong. Please try again later.";
    }
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null) {
        if (APP_DEBUG) {
            echo "<pre style='background:#fff3f3;padding:20px;border:1px solid #ffb3b3;border-radius:8px;'>";
            echo "FATAL ERROR\n\n";
            echo "Message: " . $error['message'] . "\n";
            echo "File: " . $error['file'] . "\n";
            echo "Line: " . $error['line'];
            echo "</pre>";
        } else {
            echo "A system error occurred.";
        }
    }
});

require BASE_PATH . '/app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;
use App\Models\Terminal;
use App\Models\Driver;

Session::start();

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

$user = Auth::user();

if (!isset($user['role']) || !is_string($user['role']) || !in_array(strtolower($user['role']), ['admin', 'super_admin'])) {
    header('Location: /admin/');
    exit;
}

try {
    $pdo = Database::get();
    require_once BASE_PATH . '/admin/models/Bus/BusModel.php';
    require_once BASE_PATH . '/admin/services/Bus/BusService.php';
    $busModel = new BusModel($pdo);
    $busService = new BusService($busModel);
    $buses = $busService->getAllBuses();
    $terminalModel = new Terminal();
    $terminals = $terminalModel->getActive();
    $driverModel = new Driver();
    $availableDrivers = $driverModel->getAvailableDrivers();
} catch (Throwable $e) {
    throw $e;
}

require __DIR__ . '/layout/head.php';
?>
<body>
<?php require BASE_PATH . '/admin/assets/layout/navbaradmin.php'; ?>
<div class="main-content" id="mainContent">
    <div class="content-wrapper">
<?php require __DIR__ . '/sections/header.php'; ?>
<?php require __DIR__ . '/sections/stats.php'; ?>
<?php require __DIR__ . '/sections/cards.php'; ?>
<?php require __DIR__ . '/sections/route-table.php'; ?>
</div>
</div>
<?php
require __DIR__ . '/modals/add_bus_modal.php';
require __DIR__ . '/modals/edit_bus_modal.php';
require __DIR__ . '/modals/archived_buses_modal.php';
require BASE_PATH . '/admin/modals/qr_generation_modal.php';
?>
<?php require __DIR__ . '/layout/scripts.php'; ?>

<script src="/public/login/custom-alert.js?v=<?= time() ?>"></script>

<div id="customAlertOverlay" class="custom-alert-overlay">
    <div class="custom-alert-card">
        <div class="custom-alert-header">
            <div class="custom-alert-icon"></div>
            <div class="custom-alert-title"></div>
        </div>
        <div class="custom-alert-body">
            <div class="custom-alert-message"></div>
            <div class="custom-alert-actions">
                <button class="custom-alert-btn"></button>
                <button class="custom-alert-btn-cancel"></button>
            </div>
        </div>
    </div>
</div>
</body>
</html>

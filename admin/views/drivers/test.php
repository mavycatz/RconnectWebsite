<?php
declare(strict_types = 1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null) {
        echo "<pre style='background:#111;color:#00ff88;padding:20px;font-size:14px;'>";
        echo "🔥 FATAL ERROR\n\n";
        print_r($error);
        echo "</pre>";
    }
});

set_exception_handler(function ($e) {
    echo "<pre style='background:#111;color:#ff4444;padding:20px;font-size:14px;'>";
    echo "💥 UNCAUGHT EXCEPTION\n\n";
    echo "Message: " . $e->getMessage() . "\n\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo "Stack Trace:\n";
    print_r($e->getTrace());
    echo "</pre>";
    exit;
});

function safe_require($file)
{
    if (!file_exists($file)) {
        throw new Exception("Required file not found: " . $file);
    }
    require $file;
}

try {
    safe_require(__DIR__ . '/../app/Core/Bootstrap.php');
} catch (Throwable $e) {
    throw new Exception("BOOTSTRAP FAILED: " . $e->getMessage());
}

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

if (!Auth::check()) {
    header('Location: /login/');
    exit;
}

$user = Auth::user();
if ($user['role'] !== 'admin') {
    header('Location: /admin/');
    exit;
}

define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);

try {

    safe_require(ROOT_PATH . '/admin/views/drivers/layout/header.php');
    safe_require(ROOT_PATH . '/admin/views/drivers/layout/styles.php');

    echo "<body>";

    safe_require(ROOT_PATH . '/admin/assets/layout/navbaradmin.php');

    echo '
    <div class="main-content" id="mainContent">
        <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
            <div class="container-fluid p-0">
    ';

    safe_require(ROOT_PATH . '/admin/views/drivers/sections/stats.php');
    safe_require(ROOT_PATH . '/admin/views/drivers/sections/driver-cards.php');
    safe_require(ROOT_PATH . '/admin/views/drivers/sections/modals.php');
    safe_require(ROOT_PATH . '/admin/views/drivers/sections/schedule.php');
    safe_require(ROOT_PATH . '/admin/views/drivers/sections/access-control.php');

    echo '
            </div>
        </div>
    </div>
    ';

    safe_require(ROOT_PATH . '/admin/views/drivers/layout/scripts.php');

} catch (Throwable $e) {

    echo "<pre style='background:#111;color:#ff4444;padding:20px;font-size:14px;'>";
    echo "🚨 VIEW LOAD ERROR\n\n";
    echo "Message: " . $e->getMessage() . "\n\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo "Trace:\n";
    print_r($e->getTrace());
    echo "</pre>";
}
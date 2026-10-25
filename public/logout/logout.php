<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Clear-Site-Data: "cache", "storage"');

require_once __DIR__ . '/../../app/Core/Bootstrap.php';
require_once __DIR__ . '/../../app/auth/Session.php';
require_once __DIR__ . '/../../app/auth/Auth.php';
require_once __DIR__ . '/../../app/auth/RememberAuth.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;

if (session_status() === PHP_SESSION_NONE) {
    Session::start();
}

$user = Auth::user();
$userId = $user['id'] ?? null;

$_SESSION = [];

try {
    $pdo = Database::get();
} catch (\Throwable $e) {}

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

session_destroy();

setcookie('rconnect_logout', '1', time() + 60, '/', '', false, true);

$incomingPortal = $_GET['portal'] ?? '';
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/public/config/portal_key.php')) {
    $key = require $_SERVER['DOCUMENT_ROOT'] . '/public/config/portal_key.php';
    if (!empty($incomingPortal) && hash_equals($key, $incomingPortal)) {
        $secret = bin2hex(random_bytes(8));
        header("Location: /$key?_=$secret&portal=$key");
    } else {
        header("Location: /$key");
    }
} else {
    header("Location: /public/login/");
}
exit;
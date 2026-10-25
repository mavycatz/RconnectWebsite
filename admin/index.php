<?php

declare(strict_types=1);

use App\Auth\Session;
use App\Auth\Auth;

try {
    $validToken = require __DIR__ . '/config/access_token.php';

    require_once __DIR__ . '/../app/Core/Bootstrap.php';

    Session::start();

    if (!Auth::check()) {
        header('Location: /public/login/index.php');
        exit;
    }

    $user = Auth::user();
    $role = strtolower(trim((string)($user['role'] ?? $_SESSION['role'] ?? '')));
    if (!in_array($role, ['admin', 'super_admin'], true)) {
        header('Location: /public/login/index.php');
        exit;
    }

    $rawRoute = trim((string)($_GET['route'] ?? ''));
    $route = preg_replace('/\.php$/i', '', basename($rawRoute));
    if ($route === '') {
        $route = 'dashboard';
    }

    // Identity is already proven by Auth::check()/Session + the role gate above,
    // so the URL token is only kept for link consistency, NOT as an access gate.
    // We always re-derive it from the CURRENT valid token: a stale token left in
    // an old bookmark, cookie or navbar link (e.g. after config/.token.php was
    // regenerated during a deploy) must never 404 dashboard navigation again.
    $_SESSION['admin_access_token'] = $validToken;
    setcookie('admin_token', $validToken, [
        'expires' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    // Expose the token so included views/navbars can build /admin/<page>/<token>
    // links even when the page was reached without a ?token= URL segment.
    $token = $validToken;

    // Sub-page routes that don't follow views/<route>/index.php convention.
    $subPageRoutes = [
        'passenger-trip-sheet' => '/views/passenger/trip-sheet.php',
        'passenger-baggage' => '/views/passenger/baggage.php',
    ];

    if (isset($subPageRoutes[$route]) && file_exists(__DIR__ . $subPageRoutes[$route])) {
        require __DIR__ . $subPageRoutes[$route];
        exit;
    }

    $viewFile = __DIR__ . '/views/' . $route . '/index.php';
    if (file_exists($viewFile)) {
        require $viewFile;
        exit;
    }

    $directFile = __DIR__ . '/' . $route . '.php';
    if (file_exists($directFile)) {
        require $directFile;
        exit;
    }

    http_response_code(404);
    echo '404 Not Found';

} catch (\Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'File: ' . $e->getFile() . ':' . $e->getLine() . "\n";
    echo 'Trace: ' . $e->getTraceAsString() . "\n";
}

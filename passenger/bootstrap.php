<?php

if (defined('PASSENGER_BOOTSTRAP_LOADED')) {
    return;
}
define('PASSENGER_BOOTSTRAP_LOADED', true);

if (!defined('PASSENGER_ROOT')) {
    define('PASSENGER_ROOT', __DIR__);
}
if (!defined('PASSENGER_SESSION_LIFETIME')) {
    define('PASSENGER_SESSION_LIFETIME', 60 * 60 * 24 * 365);
}

require_once __DIR__ . '/services/PassengerSessionService.php';
require_once __DIR__ . '/services/RateLimiter.php';
require_once __DIR__ . '/services/PassengerValidator.php';
require_once __DIR__ . '/services/PassengerAccountService.php';
require_once __DIR__ . '/database/hardening_schema.php';
require_once __DIR__ . '/utils/i18n.php';

function passenger_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return false;
}

function passenger_enforce_https(): void
{
    if (PHP_SAPI === 'cli' || passenger_is_https()) {
        return;
    }

    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));

    if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
        return;
    }

    if (!filter_var($host, FILTER_VALIDATE_IP) && strpos($host, '.') === false) {
        return;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return;
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/passenger/');
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function passenger_session_start(): void
{
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    $sessionPath = $docRoot !== '' ? $docRoot . '/storage/sessions' : dirname(PASSENGER_ROOT) . '/storage/sessions';

    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0777, true);
    }
    if (is_dir($sessionPath)) {
        ini_set('session.save_path', $sessionPath);
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) PASSENGER_SESSION_LIFETIME);

    $rawHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $hostWithoutPort = (string) preg_replace('/:\d+$/', '', $rawHost);
    $cookieDomain = '';
    if (
        $hostWithoutPort !== ''
        && strtolower($hostWithoutPort) !== 'localhost'
        && !filter_var($hostWithoutPort, FILTER_VALIDATE_IP)
        && strpos($hostWithoutPort, '.') !== false
    ) {
        $cookieDomain = $hostWithoutPort;
    }

    session_set_cookie_params([
        'lifetime' => PASSENGER_SESSION_LIFETIME,
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => passenger_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    if (empty($_SESSION['passenger_csrf'])) {
        $_SESSION['passenger_csrf'] = bin2hex(random_bytes(32));
    }
}

function passenger_send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
        . "img-src 'self' data: blob: https://tile.openstreetmap.org; "
        . "connect-src 'self'; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'"
    );

    if (passenger_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function passenger_is_api_request(): bool
{
    $route = (string) ($_GET['route'] ?? '');
    foreach (['api/', 'trip/', 'scan/', 'qr/', 'paymongo/', 'token-login'] as $prefix) {
        if (strpos($route, $prefix) === 0) {
            return true;
        }
    }

    if (
        strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
        && in_array($route, [
            'register',
            'register/scan-id',
            'passengers/store',
            'passengers/verify-otp',
            'passengers/resend-otp',
            'one-time/register',
            'one-time/fare',
            'one-time/payment-method',
            'forgot-password/send',
            'forgot-password/verify',
            'forgot-password/update',
            'auto-board',
            'calculate-fare',
            'profile/update',
            'profile/default-payment-method',
            'payment/proof',
        ], true)
    ) {
        return true;
    }

    if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
        return true;
    }

    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($contentType !== '' && strpos($contentType, 'application/json') !== false) {
        return true;
    }

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if ($accept !== '' && strpos($accept, 'application/json') !== false && strpos($accept, 'text/html') === false) {
        return true;
    }

    return false;
}

function passenger_reject_foreign_origin(): void
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '') {
        return;
    }

    $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));

    if ($originHost === '' || $host === '' || $originHost === $host) {
        return;
    }

    if (passenger_is_api_request()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'code' => 'origin', 'message' => 'Cross-origin request rejected.']);
        exit;
    }

    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo 'Cross-origin request rejected.';
    exit;
}

function passenger_render_db_error_page(string $ref = ''): string
{
    $refText = $ref !== '' ? htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') : strtoupper(substr(hash('sha256', (string) microtime(true)), 0, 8));
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Service Unavailable | R-CONNECT</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:\'Poppins\',sans-serif;
            background:#f4f7f2;
            color:#24513c;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:20px;
        }
        .error-card{
            background:#ffffff;
            border:1.5px solid #c6d48c;
            border-radius:24px;
            padding:36px 28px;
            max-width:420px;
            width:100%;
            text-align:center;
            box-shadow:0 12px 32px rgba(36,81,60,.12);
        }
        .icon-circle{
            width:68px;
            height:68px;
            border-radius:50%;
            background:#eef4e6;
            color:#47613b;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            margin-bottom:20px;
        }
        .icon-circle svg{
            width:34px;
            height:34px;
            fill:currentColor;
        }
        h1{
            font-size:20px;
            font-weight:700;
            color:#24513c;
            margin-bottom:10px;
            line-height:1.3;
        }
        p{
            font-size:13.5px;
            color:#5b6e58;
            line-height:1.55;
            margin-bottom:24px;
        }
        .btn-retry{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            width:100%;
            padding:13px 20px;
            border:none;
            border-radius:30px;
            font-family:\'Poppins\',sans-serif;
            font-size:14px;
            font-weight:700;
            color:#ffffff;
            background:linear-gradient(135deg,#47613b,#5d8550);
            cursor:pointer;
            box-shadow:0 4px 12px rgba(71,97,59,.25);
            transition:transform .15s ease,box-shadow .15s ease;
            touch-action:manipulation;
        }
        .btn-retry:active{
            transform:scale(0.98);
        }
        .ref-code{
            font-size:11px;
            color:#9aa995;
            margin-top:20px;
            letter-spacing:0.5px;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon-circle">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        </div>
        <h1>Service Temporarily Unavailable</h1>
        <p>We are having trouble connecting to the service right now. Please wait a moment and try again.</p>
        <button type="button" class="btn-retry" onclick="window.location.reload()">
            Try Again
        </button>
        <div class="ref-code">Reference: ' . $refText . '</div>
    </div>
</body>
</html>';
}

function passenger_render_themed_error(string $title, string $message, string $buttonText = 'Return to Home', string $buttonUrl = '/passenger/home', int $httpCode = 404): void
{
    if (passenger_is_api_request()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'code' => ($httpCode === 404) ? 'NOT_FOUND' : 'ERROR',
            'message' => $message,
        ]);
        exit;
    }

    http_response_code($httpCode);
    $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $escapedMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $escapedBtn = htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8');
    $escapedUrl = htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>' . $escapedTitle . ' | R-CONNECT</title>
    <link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:\'Poppins\',sans-serif;
            background:#f4f7f2;
            color:#24513c;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:20px;
        }
        .error-card{
            background:#ffffff;
            border:1.5px solid #c6d48c;
            border-radius:24px;
            padding:36px 28px;
            max-width:440px;
            width:100%;
            text-align:center;
            box-shadow:0 12px 32px rgba(36,81,60,.12);
        }
        .icon-circle{
            width:68px;
            height:68px;
            border-radius:50%;
            background:#fcf5dd;
            color:#47613b;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            margin-bottom:20px;
            font-size:30px;
            border:1.5px solid #c6d48c;
        }
        h1{
            font-size:20px;
            font-weight:700;
            color:#24513c;
            margin-bottom:10px;
            line-height:1.3;
        }
        p{
            font-size:13.5px;
            color:#5b6e58;
            line-height:1.55;
            margin-bottom:24px;
        }
        .btn-action{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            width:100%;
            padding:13px 20px;
            border:none;
            border-radius:30px;
            font-family:\'Poppins\',sans-serif;
            font-size:14px;
            font-weight:700;
            color:#ffffff;
            background:linear-gradient(135deg,#47613b,#5d8550);
            text-decoration:none;
            cursor:pointer;
            box-shadow:0 4px 12px rgba(71,97,59,.25);
            transition:transform .15s ease,box-shadow .15s ease;
        }
        .btn-action:hover{
            color:#ffffff;
            transform:translateY(-1px);
        }
        .btn-action:active{
            transform:scale(0.98);
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon-circle">
            <i class="bi bi-compass"></i>
        </div>
        <h1>' . $escapedTitle . '</h1>
        <p>' . $escapedMsg . '</p>
        <a href="' . $escapedUrl . '" class="btn-action">' . $escapedBtn . '</a>
    </div>
</body>
</html>';
    exit;
}

function passenger_shutdown_handler(): void
{
    $output = '';
    while (ob_get_level() > 0) {
        $output = ob_get_clean() . $output;
    }

    $lastError = error_get_last();
    $isFatal = false;
    if ($lastError !== null && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $isFatal = true;
    }

    $isDbDie = (stripos($output, 'Database connection failed') !== false);

    if ($isFatal || $isDbDie) {
        $ref = strtoupper(substr(hash('sha256', microtime(true) . 'raw_error'), 0, 8));
        $errorDetail = $isFatal
            ? ($lastError['message'] . ' in ' . $lastError['file'] . ':' . $lastError['line'])
            : trim(strip_tags($output));

        error_log('Passenger intercepted raw error [' . $ref . ']: ' . $errorDetail);

        try {
            $logDir = dirname(PASSENGER_ROOT) . '/storage/logs';
            if (is_dir($logDir) || @mkdir($logDir, 0777, true)) {
                @file_put_contents(
                    $logDir . '/passenger_errors.log',
                    date('c') . ' | [' . $ref . '] ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '')
                    . ' | Intercepted raw failure: ' . $errorDetail . PHP_EOL,
                    FILE_APPEND
                );
            }
        } catch (\Throwable $t) {
            // Best effort logging
        }

        $statusCode = $isDbDie ? 503 : 500;
        if (!headers_sent()) {
            http_response_code($statusCode);
            if (passenger_is_api_request()) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'code' => $isDbDie ? 'DB_UNAVAILABLE' : 'SERVICE_ERROR',
                    'reference' => $ref,
                    'message' => 'We couldn\'t process your request right now. Please try again.',
                ]);
                return;
            }

            header('Content-Type: text/html; charset=UTF-8');
            echo passenger_render_db_error_page($ref);
            return;
        }

        echo passenger_render_db_error_page($ref);
        return;
    }

    echo $output;
}

function passenger_db(): ?\PDO
{
    if (!class_exists('\App\Core\Database', false)) {
        $path = dirname(__DIR__) . '/app/Core/Database.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
    if (class_exists('\App\Core\Database') && method_exists('\App\Core\Database', 'tryGet')) {
        return \App\Core\Database::tryGet();
    }
    return null;
}

function passenger_install_error_handler(): void
{
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    if (!defined('PASSENGER_OB_STARTED')) {
        define('PASSENGER_OB_STARTED', true);
        ob_start();
        register_shutdown_function('passenger_shutdown_handler');
    }

    set_exception_handler(function ($e) {
        $ref = strtoupper(substr(hash('sha256', microtime(true) . $e->getMessage()), 0, 8));
        error_log('Passenger unhandled exception [' . $ref . ']: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

        try {
            $logDir = dirname(PASSENGER_ROOT) . '/storage/logs';
            if (is_dir($logDir)) {
                @file_put_contents(
                    $logDir . '/passenger_errors.log',
                    date('c') . ' | [' . $ref . '] ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '')
                    . ' | ' . $e->getMessage()
                    . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL,
                    FILE_APPEND
                );
            }
        } catch (\Throwable $logError) {
            // Never let logging break the error response.
        }

        if (headers_sent()) {
            echo '';
            return;
        }

        if (passenger_is_api_request()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'code' => 'SERVER_ERROR',
                'reference' => $ref,
                'message' => 'Something went wrong. Please try again.',
            ]);
        } else {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Something went wrong</title></head><body style="font-family:sans-serif;padding:24px;text-align:center;"><h3>Something went wrong</h3><p>Please refresh and try again.</p><p style="font-size:12px;color:#888;">Reference: ' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
        }
        exit;
    });
}

function passenger_is_mobile_request(): bool
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua !== '' && preg_match('/(android|iphone|ipad|ipod|mobile|tablet|kindle|silk|blackberry|opera mini|iemobile|wpdesktop)/i', $ua)) {
        return true;
    }

    if (trim((string) ($_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '')) === '?1') {
        return true;
    }

    return false;
}

function passenger_desktop_allowed(): bool
{
    $value = getenv('PASSENGER_ALLOW_DESKTOP');

    if ($value === false || $value === '') {
        $value = $_SERVER['PASSENGER_ALLOW_DESKTOP'] ?? '';
    }

    if ($value === false || $value === '') {
        $envFile = dirname(PASSENGER_ROOT) . '/app/config/Env.php';
        if (is_file($envFile)) {
            require_once $envFile;
            if (class_exists('App\\Config\\Env')) {
                $value = \App\Config\Env::get('PASSENGER_ALLOW_DESKTOP', '');
            }
        }
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function passenger_mobile_gate(): void
{
    if (PHP_SAPI === 'cli' || passenger_is_mobile_request() || passenger_desktop_allowed()) {
        return;
    }

    if (passenger_is_api_request()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'code' => 'mobile_only', 'message' => 'This service is available on mobile devices only.']);
        exit;
    }

    http_response_code(403);
    $blocked = PASSENGER_ROOT . '/views/blocked.php';
    if (is_file($blocked)) {
        require $blocked;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo 'This portal is available on mobile devices only.';
    exit;
}

function passenger_url(string $route = ''): string
{
    $route = trim($route, '/');
    return $route === '' ? '/passenger/' : '/passenger/' . $route;
}

function passenger_csrf_token(): string
{
    if (empty($_SESSION['passenger_csrf'])) {
        $_SESSION['passenger_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['passenger_csrf'];
}

function passenger_verify_csrf(): bool
{
    $sent = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $known = (string) ($_SESSION['passenger_csrf'] ?? '');

    if ($known === '' || $sent === '') {
        return false;
    }

    return hash_equals($known, $sent);
}

function passenger_require_csrf(): void
{
    if (passenger_verify_csrf()) {
        return;
    }

    if (passenger_is_api_request()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'code' => 'csrf', 'message' => 'Security check failed. Please reopen the app and try again.']);
        exit;
    }

    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo 'Security check failed. Please refresh the page and try again.';
    exit;
}

function passenger_is_disabled_status($status): bool
{
    return in_array((string) $status, ['Blocked', 'Suspended', 'Archived'], true);
}

function passenger_deny_auth(bool $json, string $message = 'Your session has ended. Please log in again.', string $reason = 'expired'): void
{
    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'code' => 'auth', 'message' => $message]);
        exit;
    }

    header('Location: ' . passenger_url('login?reason=' . urlencode($reason)));
    exit;
}

/**
 * Read the current session identity's account and device facts without
 * redirecting or changing session state. passenger_require_auth() and the
 * QR entry surfaces share this single verification path so their decisions
 * cannot drift apart.
 *
 * @return array{status:string,conn:?object,account:?array,account_error:?string,device:?string,device_error:?string}
 *   status: no_identity | one_time | db_unavailable | dead | account_error | verified
 *   account_error / device_error: the failure message when that read failed
 */
function passenger_session_verification(): array
{
    if (empty($_SESSION['passenger_id'])) {
        return ['status' => 'no_identity', 'conn' => null, 'account' => null, 'account_error' => null, 'device' => null, 'device_error' => null];
    }

    if (!empty($_SESSION['is_one_time'])) {
        return ['status' => 'one_time', 'conn' => null, 'account' => null, 'account_error' => null, 'device' => null, 'device_error' => null];
    }

    $passengerId = (int) $_SESSION['passenger_id'];

    if (!class_exists('App\\Core\\Database')) {
        require_once dirname(PASSENGER_ROOT) . '/app/Core/Database.php';
    }

    try {
        $conn = \App\Core\Database::tryGet();
    } catch (\Throwable $e) {
        $conn = null;
    }

    if (!$conn) {
        return ['status' => 'db_unavailable', 'conn' => null, 'account' => null, 'account_error' => 'Database connection is unavailable', 'device' => null, 'device_error' => null];
    }

    try {
        // A session pointing at a passenger record that no longer exists is a
        // dead session, not a service outage. It must read as "session ended"
        // instead of the misleading "unavailable".
        if (!PassengerAccountService::exists($conn, $passengerId)) {
            return ['status' => 'dead', 'conn' => $conn, 'account' => null, 'account_error' => null, 'device' => null, 'device_error' => null];
        }

        $account = PassengerAccountService::state($conn, $passengerId);
        $_SESSION['passenger_code'] = (string) ($account['passenger_code'] ?? '');
    } catch (\Throwable $e) {
        error_log('Passenger account check failed: ' . $e->getMessage());
        return ['status' => 'account_error', 'conn' => $conn, 'account' => null, 'account_error' => $e->getMessage(), 'device' => null, 'device_error' => null];
    }

    try {
        $device = \App\Passenger\PassengerSessionService::state($conn, $passengerId);
    } catch (\Throwable $e) {
        error_log('Passenger device check failed: ' . $e->getMessage());
        return ['status' => 'verified', 'conn' => $conn, 'account' => $account, 'account_error' => null, 'device' => null, 'device_error' => $e->getMessage()];
    }

    return ['status' => 'verified', 'conn' => $conn, 'account' => $account, 'account_error' => null, 'device' => $device, 'device_error' => null];
}

/**
 * Classify the current session for the QR entry surfaces (scan, One-Time
 * landing, One-Time registration).
 *
 * @return array{state:string,account:?array,detail:?string}
 *   state: visitor | one_time | regular | restricted | invalid | unverifiable
 */
function passenger_entry_session_state(): array
{
    $verification = passenger_session_verification();
    $status = (string) ($verification['status'] ?? '');

    if ($status === 'no_identity') {
        return ['state' => 'visitor', 'account' => null, 'detail' => null];
    }

    if ($status === 'one_time') {
        return ['state' => 'one_time', 'account' => null, 'detail' => null];
    }

    if ($status === 'db_unavailable' || $status === 'account_error') {
        return ['state' => 'unverifiable', 'account' => null, 'detail' => (string) ($verification['account_error'] ?? '')];
    }

    if ($status === 'dead') {
        return ['state' => 'invalid', 'account' => null, 'detail' => 'dead_identity'];
    }

    if ($status !== 'verified') {
        return ['state' => 'unverifiable', 'account' => null, 'detail' => 'unknown_state'];
    }

    $account = $verification['account'] ?? [];

    // Restricted accounts keep the existing fence; the restriction check runs
    // before any device-state handling, matching passenger_require_auth().
    if (($account['effective_account_status'] ?? null) !== 'ACTIVE') {
        return ['state' => 'restricted', 'account' => $account, 'detail' => null];
    }

    if (!empty($verification['device_error'])) {
        return ['state' => 'unverifiable', 'account' => $account, 'detail' => (string) $verification['device_error']];
    }

    $device = (string) ($verification['device'] ?? '');
    if ($device === 'valid' || $device === 'legacy') {
        return ['state' => 'regular', 'account' => $account, 'detail' => null];
    }

    // revoked / disabled / unrecognized device state: demote to a visitor on
    // the entry surfaces instead of bouncing to login.
    return ['state' => 'invalid', 'account' => $account, 'detail' => $device !== '' ? $device : 'unknown_device_state'];
}

/**
 * Drop the account identity for the QR entry surfaces while preserving the
 * scanned bus/trip context (qr_bus, qr_trip, qr_location_snapshot) and the
 * CSRF token, so the landing still renders the scanned trip.
 */
function passenger_clear_entry_identity(): void
{
    unset(
        $_SESSION['passenger_id'],
        $_SESSION['passenger_name'],
        $_SESSION['is_one_time']
    );
}

function passenger_require_auth(bool $json = false): void
{
    $verification = passenger_session_verification();
    $status = (string) ($verification['status'] ?? '');
    $conn = $verification['conn'] ?? null;

    // A one-time passenger has no account to verify. The One-Time flow is
    // intentionally login-free, so it must never be gated behind the account
    // and device checks (a stale one-time identity used to redirect the
    // landing page to login with reason=unavailable).
    if ($status === 'one_time') {
        return;
    }

    if ($status === 'no_identity') {
        passenger_deny_auth($json, 'Your session has ended. Please log in again.', 'expired');
    }

    if ($status === 'db_unavailable' || $status === 'account_error') {
        passenger_deny_auth($json, 'Account status cannot be verified right now. Please retry.', 'unavailable');
    }

    if ($status === 'dead') {
        \App\Passenger\PassengerSessionService::killCurrentSession();
        passenger_deny_auth($json, 'Your session has ended. Please log in again.', 'expired');
    }

    if ($status !== 'verified') {
        passenger_deny_auth($json, 'Account status cannot be verified right now. Please retry.', 'unavailable');
    }

    $account = $verification['account'] ?? [];
    $passengerId = (int) $_SESSION['passenger_id'];

    // Restricted accounts are fenced to the notice/appeal pages. This check
    // runs before device-state handling so a restricted account is denied with
    // the restriction notice even when the device state cannot be read.
    if (($account['effective_account_status'] ?? null) !== 'ACTIVE') {
        $route = (string) ($_GET['route'] ?? '');
        if (!in_array($route, ['account/restriction', 'account/appeal', 'account/notifications/read', 'logout'], true)) {
            if ($json) {
                http_response_code(403); header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['success' => false, 'code' => 'account_restricted', 'status' => $account['effective_account_status'] ?? null, 'message' => PassengerAccountService::notice($account), 'redirect' => passenger_url('account/restriction')]); exit;
            }
            header('Location: ' . passenger_url('account/restriction')); exit;
        }
    }

    if (!empty($verification['device_error'])) {
        passenger_deny_auth($json, 'Account status cannot be verified right now. Please retry.', 'unavailable');
    }

    $state = (string) ($verification['device'] ?? '');

    if ($state === 'valid') {
        if ($conn) {
            try {
                $conn->prepare("UPDATE passenger_sessions SET last_seen_at = NOW(), ip_address = ? WHERE session_hash = ? AND revoked_at IS NULL")
                    ->execute([(string) ($_SERVER['REMOTE_ADDR'] ?? ''), hash('sha256', (string) session_id())]);
            } catch (\Throwable $e) {
            }
        }
        return;
    }

    if ($state === 'legacy') {
        if ($conn) {
            \App\Passenger\PassengerSessionService::adoptLegacy($conn, $passengerId);
        }
        return;
    }

    if ($state === 'disabled') {
        if ($conn) {
            \App\Passenger\PassengerSessionService::revokeAll($conn, $passengerId, 'disabled');
        }
        \App\Passenger\PassengerSessionService::killCurrentSession();
        if ($json) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'code' => 'account_inactive',
                'message' => 'Your account is currently inactive. Please contact the administrator.'
            ]);
            exit;
        }
        passenger_deny_auth(false, 'Your account is currently inactive. Please contact the administrator.', 'inactive');
    }

    if ($state === 'revoked') {
        \App\Passenger\PassengerSessionService::killCurrentSession();
        if ($json) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'code' => 'session_invalidated',
                'message' => 'Your account was signed in on another device or browser. Please log in again.'
            ]);
            exit;
        }
        passenger_deny_auth(false, 'Your account was signed in on another device or browser. Please log in again.', 'session_invalidated');
    }

    \App\Passenger\PassengerSessionService::killCurrentSession();
    passenger_deny_auth($json, 'Your session has ended. Please log in again.', 'expired');
}

function passenger_rate_limit(string $action, string $key, int $max, int $windowSeconds, int $lockSeconds = 0): bool
{
    if (!class_exists('App\\Core\\Database')) {
        require_once dirname(PASSENGER_ROOT) . '/app/Core/Database.php';
    }

    try {
        $conn = \App\Core\Database::tryGet();
    } catch (\Throwable $e) {
        return true;
    }

    if (!$conn) {
        return true;
    }

    return \App\Passenger\RateLimiter::hit($conn, $action, $key, $max, $windowSeconds, $lockSeconds);
}

function passenger_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * Whether the QR scan diagnostic details may be exposed in API responses.
 * Off by default; enabled only by explicit configuration
 * (PASSENGER_QR_SCAN_DEBUG=1 in app/config/.env or the environment).
 */
function passenger_qr_scan_debug_enabled(): bool
{
    $value = getenv('PASSENGER_QR_SCAN_DEBUG');

    if ($value === false || $value === '') {
        $value = $_SERVER['PASSENGER_QR_SCAN_DEBUG'] ?? '';
    }

    if ($value === false || $value === '') {
        $envFile = dirname(PASSENGER_ROOT) . '/app/config/Env.php';
        if (is_file($envFile)) {
            require_once $envFile;
            if (class_exists('App\\Config\\Env')) {
                $value = \App\Config\Env::get('PASSENGER_QR_SCAN_DEBUG', '');
            }
        }
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

/**
 * Truncate and normalize a value so one diagnostic entry cannot produce an
 * oversized log line.
 */
function passenger_qr_scan_log_sanitize($value, int $depth = 0)
{
    if (is_string($value)) {
        return function_exists('mb_substr') ? mb_substr($value, 0, 500) : substr($value, 0, 500);
    }

    if (is_array($value) && $depth < 5) {
        $clean = [];
        foreach ($value as $key => $item) {
            $clean[$key] = passenger_qr_scan_log_sanitize($item, $depth + 1);
        }
        return $clean;
    }

    return $value;
}

/**
 * Append one structured JSON line to the QR scan diagnostic log. Never throws
 * and never alters a response: when the log file cannot be written the entry
 * falls back to the PHP error log.
 */
function passenger_qr_scan_log(array $entry): void
{
    try {
        if (!isset($entry['ts'])) {
            $entry['ts'] = date('c');
        }

        $line = json_encode(
            passenger_qr_scan_log_sanitize($entry),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($line === false) {
            $line = json_encode(['ts' => $entry['ts'], 'type' => 'encode_failed']);
        }

        $logDir = dirname(PASSENGER_ROOT) . '/storage/logs';

        if (is_dir($logDir) || @mkdir($logDir, 0777, true)) {
            $written = @file_put_contents(
                $logDir . '/passenger_qr_scan.log',
                $line . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );

            if ($written !== false) {
                return;
            }
        }

        error_log('[passenger_qr_scan] ' . $line);
    } catch (\Throwable $e) {
        // Diagnostics must never break the passenger request.
    }
}

function passenger_ensure_schema_once(): void
{
    $db = passenger_db();
    if ($db) {
        // Account-status storage is additive. If the database rejects the DDL
        // (permissions, lock) the request must still proceed: scanning,
        // registration and browsing keep working, and the failure is logged
        // instead of surfacing as a broken JSON response.
        try {
            PassengerAccountSchema::ensure($db);
        } catch (\Throwable $e) {
            error_log('Passenger account schema ensure skipped: ' . $e->getMessage());
        }
    }
    if (!empty($_SESSION['_passenger_schema_checked'])) {
        return;
    }

    \App\Passenger\PassengerSessionService::ensureSchema();
    $_SESSION['_passenger_schema_checked'] = 1;
}

function passenger_validate_image_upload(array $file, int $maxBytes = 5242880): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return [false, 'No file uploaded.'];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return [false, 'Upload failed. Please try again.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        return [false, 'File is too large.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [false, 'Upload failed. Please try again.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        return [false, 'Unsupported file type. Please upload a JPG, PNG, GIF, or WEBP image.'];
    }

    return [true, $allowed[$mime]];
}

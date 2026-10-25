<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Models\User;
use App\Core\MailerService;
use App\Auth\Auth;
use App\Core\Database;

if (session_status() === PHP_SESSION_NONE) {
    ini_set('cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    if (!empty($_SERVER['HTTPS'])) {
        ini_set('cookie_secure', '1');
    }
    ini_set('cookie_samesite', 'Lax');
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self';");

$portalKey = require $_SERVER['DOCUMENT_ROOT'] . '/public/config/portal_key.php';
$incomingPortal = $_GET['portal'] ?? '';
if (!hash_equals($portalKey, $incomingPortal)) {
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $incoming = $_GET['_'] ?? '';
    $stored = $_SESSION['login_secret'] ?? '';
    if (empty($incoming) || empty($stored) || !hash_equals($stored, $incoming)) {
        $_SESSION['login_secret'] = bin2hex(random_bytes(8));
        $qs = $_GET;
        unset($qs['_']);
        $extra = $qs ? '&' . http_build_query($qs) : '';
        header('Location: ?_=' . $_SESSION['login_secret'] . $extra);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_COOKIE['remember'])) {
    try {
        $parts = explode(':', $_COOKIE['remember']);
        if (count($parts) === 2) {
            $pdoCheck = Database::get();
            $stmt = $pdoCheck->prepare("SELECT id FROM remember_tokens WHERE selector = ? AND expires > NOW() LIMIT 1");
            $stmt->execute([$parts[0]]);
            if (!$stmt->fetch()) {
                setcookie('remember', '', time() - 42000, '/', '', true, true);
            }
        }
    } catch (\Throwable $e) {}
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$error = '';
$success = '';

function checkLoginRateLimit(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $storageDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/ratelimit';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0755, true);
    }
    $key = hash('sha256', 'login:' . $ip);
    $path = $storageDir . '/' . $key . '.json';
    $maxAttempts = 5;
    $windowSeconds = 900;
    $attempts = [];
    if (file_exists($path)) {
        $data = @file_get_contents($path);
        $attempts = json_decode($data, true) ?: [];
    }
    $now = time();
    $windowStart = $now - $windowSeconds;
    $attempts = array_filter($attempts, fn($ts) => $ts > $windowStart);
    if (count($attempts) >= $maxAttempts) {
        $retryAfter = $attempts[0] + $windowSeconds - $now;
        return "Too many login attempts from your IP. Please try again in " . ceil($retryAfter / 60) . " minute(s).";
    }
    return null;
}

function recordLoginFailure(): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $storageDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/ratelimit';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0755, true);
    }
    $key = hash('sha256', 'login:' . $ip);
    $path = $storageDir . '/' . $key . '.json';
    $windowSeconds = 900;
    $attempts = [];
    if (file_exists($path)) {
        $data = @file_get_contents($path);
        $attempts = json_decode($data, true) ?: [];
    }
    $now = time();
    $windowStart = $now - $windowSeconds;
    $attempts = array_values(array_filter($attempts, fn($ts) => $ts > $windowStart));
    $attempts[] = $now;
    @file_put_contents($path, json_encode($attempts), LOCK_EX);
}

function resetLoginRateLimit(): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $storageDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/ratelimit';
    $key = hash('sha256', 'login:' . $ip);
    $path = $storageDir . '/' . $key . '.json';
    if (file_exists($path)) {
        @unlink($path);
    }
}

$baseUrl = '';

function redirectByRole(string $role, string $baseUrl = ''): void
{
    $tokenPath = file_exists(__DIR__ . '/../../admin/config/access_token.php')
        ? __DIR__ . '/../../admin/config/access_token.php'
        : (($_SERVER['DOCUMENT_ROOT'] ?? '') . '/admin/config/access_token.php');
    $adminToken = file_exists($tokenPath) ? require $tokenPath : '';

    $normRole = strtolower(trim($role));
    if ($normRole === 'admin' || $normRole === 'super_admin') {
        header('Location: ' . (!empty($adminToken) ? $baseUrl . '/admin/dashboard/' . $adminToken : $baseUrl . '/admin/dashboard'));
    } elseif ($normRole === 'finance_manager' || $normRole === 'finance_officer') {
        header('Location: ' . (!empty($adminToken) ? $baseUrl . '/finance/dashboard/' . $adminToken : $baseUrl . '/finance/dashboard'));
    } else {
        header('Location: ' . $baseUrl . '/');
    }
    exit;
}

function loginAlertParts(string $message, $user = null): array
{
    $title = trim($message);
    $detail = '';
    $variant = 'error';
    $lockUntilSecs = null;

    if (str_starts_with($title, 'Please enter both username and password.')) {
        $title = 'Please enter your username and password.';
        $detail = 'Both fields are required to continue.';
        $variant = 'warning';
    } elseif (preg_match('/^Incorrect email\/username or password\.\s*$/', $title)) {
        $title = 'Incorrect username or password.';
        $detail = 'Please check your credentials and try again.';
        $variant = 'error';
    } elseif (preg_match('/^Incorrect email\/username or password\.\s*You have (\d+) attempt\(s\)? remaining\.\s*$/', $title, $m)) {
        $remaining = (int) $m[1];
        $title = 'Incorrect username or password.';
        $detail = $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' remaining.';
        $variant = 'warning';
    } elseif (preg_match('/^Too many unsuccessful login attempts\.\s*Your account has been temporarily locked for (\d+) minutes\.$/', $title, $m)) {
        $variant = 'locked';
        $lockUntilSecs = null;
        if (is_array($user) && !empty($user['lock_until'])) {
            $lockTs = strtotime($user['lock_until']);
            if ($lockTs !== false && $lockTs > time()) {
                $lockUntilSecs = (int) $lockTs;
            }
        }
        if ($lockUntilSecs) {
            $title = 'Your account is temporarily locked.';
            $detail = 'Try again in %s.';
        } else {
            $title = 'Too many failed attempts.';
            $detail = 'Your account has been temporarily locked.';
        }
    } elseif (str_starts_with($title, 'Your account has been permanently locked.')) {
        $title = 'Your account is locked.';
        $detail = 'Please contact the administrator.';
        $variant = 'locked';
    } elseif (str_starts_with($title, 'Your account is currently inactive.')) {
        $title = 'Your account is currently inactive.';
        $detail = 'Please contact the administrator.';
        $variant = 'info';
    } elseif (str_starts_with($title, 'Failed to send OTP email.')) {
        $title = 'Something went wrong.';
        $detail = 'We could not send the verification code. Please try again.';
        $variant = 'error';
    } elseif (str_starts_with($title, 'Security validation failed')) {
        $title = 'Security check failed.';
        $detail = 'Please refresh the page and try again.';
        $variant = 'error';
    } else {
        $title = 'Something went wrong.';
        $detail = 'Please refresh the page and try again.';
        $variant = 'error';
    }

    return [$variant, $title, $detail, $lockUntilSecs];
}

if (Auth::check()) {
    $user = Auth::user();
    $role = $user['role'] ?? '';
    redirectByRole($role, $baseUrl);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $incoming = $_GET['_'] ?? '';
    $stored = $_SESSION['login_secret'] ?? '';
    if (empty($incoming) || empty($stored) || !hash_equals($stored, $incoming)) {
        $_SESSION['login_secret'] = bin2hex(random_bytes(8));
        $qs = $_GET;
        unset($qs['_']);
        $extra = $qs ? '&' . http_build_query($qs) : '';
        header('Location: ?_=' . $_SESSION['login_secret'] . $extra);
        exit;
    }

    $rateLimitError = checkLoginRateLimit();
    if ($rateLimitError) {
        throw new Exception($rateLimitError);
    }

    try {
        $postedToken = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($postedToken) || empty($sessionToken) || !hash_equals($sessionToken, $postedToken)) {
            throw new Exception('Security validation failed. Please refresh the page.');
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        if (empty($username) || empty($password)) {
            throw new Exception('Please enter both username and password.');
        }

        $userModel = new User();
        $user = $userModel->findByUsernameOrEmail($username);

        if (!$user) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
            \App\Audit\Audit::log('Login', 'Login', 'Failed login: account not found', null, $username, null, null, 'Failed');
            recordLoginFailure();
            throw new Exception('Incorrect email/username or password.');
        }

        if (strtolower($user['status'] ?? '') !== 'active') {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
            \App\Audit\Audit::log('Login', 'Login', 'Blocked login: inactive account', $user['id'], $user['username'], $user['role'], null, 'Failed');
            recordLoginFailure();
            throw new Exception('Your account is currently inactive. Please contact the administrator to reactivate your account.');
        }

        if (!empty($user['permanently_locked'])) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
            \App\Audit\Audit::log('Login', 'Login', 'Blocked login: permanently locked account', $user['id'], $user['username'], $user['role'], null, 'Failed');
            throw new Exception('Your account has been permanently locked. Please contact the administrator.');
        }

        if (!empty($user['lock_until'])) {
            $lockUntil = strtotime($user['lock_until']);
            if ($lockUntil !== false && $lockUntil > time()) {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
                \App\Audit\Audit::log('Login', 'Login', 'Blocked login: temporarily locked', $user['id'], $user['username'], $user['role'], null, 'Failed');
                throw new Exception('Too many unsuccessful login attempts. Your account has been temporarily locked for 15 minutes.');
            }
        }

        if (!password_verify($password, $user['password'])) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
            \App\Audit\Audit::log('Login', 'Login', 'Failed login: incorrect password', $user['id'], $user['username'], $user['role'], null, 'Failed');
            recordLoginFailure();
            $failedAttempts = (int)($user['failed_attempts'] ?? 0) + 1;
            $userModel->incrementAttempts((int)$user['id']);
            if ($failedAttempts >= 3) {
                $until = date('Y-m-d H:i:s', time() + (15 * 60));
                $userModel->lockTemporarily((int)$user['id'], $until);
                throw new Exception('Too many unsuccessful login attempts. Your account has been temporarily locked for 15 minutes.');
            }
            $remaining = 3 - $failedAttempts;
            throw new Exception("Incorrect email/username or password. You have {$remaining} attempt(s) remaining.");
        }

        $userModel->resetAttempts((int)$user['id']);
        resetLoginRateLimit();

        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
        \App\Audit\Audit::log('Login', 'Login', 'Successful password verification (sent OTP)', $user['id'], $user['username'], $user['role'], null, 'Success');

        unset($_SESSION['user']);
        session_regenerate_id(true);

        if ($remember && !empty($_COOKIE['remember'])) {
            $parts = explode(':', $_COOKIE['remember']);
            if (count($parts) === 2) {
                $selector = trim($parts[0]);
                $token = trim($parts[1]);
                if (!empty($selector) && !empty($token)) {
                    $pdoCheck = Database::get();
                    $stmtCheck = $pdoCheck->prepare("
                        SELECT rt.*, u.id AS user_id, u.username, u.role
                        FROM remember_tokens rt
                        INNER JOIN users u ON rt.user_id = u.id
                        WHERE rt.selector = ?
                        AND rt.expires > NOW()
                        AND rt.user_id = ?
                        LIMIT 1
                    ");
                    $stmtCheck->execute([$selector, $user['id']]);
                    $record = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                    if ($record && hash_equals($record['token_hash'], hash('sha256', $token))) {
                        Auth::login((int)$user['id'], $user['role'], $user['username']);
                        $_SESSION['session'] = [
                            'started_at' => time(),
                            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ];
                        $_SESSION['last_activity'] = time();
                        redirectByRole($user['role'], $baseUrl);
                    }
                }
            }
        }

        if (!empty($_COOKIE['remember'])) {
            setcookie('remember', '', time() - 42000, '/', '', true, true);
        }

        $otp = random_int(100000, 999999);
        $_SESSION['otp'] = [
            'hash' => password_hash((string)$otp, PASSWORD_DEFAULT),
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'username' => $user['username'],
            'remember' => $remember,
            'expires' => time() + 300,
            'attempts' => 0
        ];

        $mailer = new MailerService();
        $sent = $mailer->sendOtp($user['email'], (string)$otp);
        if (!$sent) {
            throw new Exception('Failed to send OTP email.');
        }

        $success = 'OTP sent successfully.';
        $otpSecret = $_GET['_'] ?? '';
        header('Location: ' . $baseUrl . '/' . urlencode($incomingPortal) . '/verify-otp?_=' . urlencode($otpSecret));
        exit;

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title>Login | RConnect TMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="/public/login/style.css">
<link rel="stylesheet" href="/public/login/auth-layout.css?v=4">
<link rel="stylesheet" href="/assets/css/site-transitions.css">
<link rel="stylesheet" href="/public/login/auth-alert.css">
<script src="/public/login/auth-alert.js"></script>
<style>
.login-card {
    box-sizing: border-box;
    width: 100%;
    max-width: 520px;
    position: absolute;
    top: 20px;
    right: 80px;
    background: rgba(250, 243, 218, 0.95);
    border: 1.5px solid #545454;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
}

.login-card > * {
    box-sizing: border-box;
    max-width: 100%;
}

.welcome-heading {
    font-family: 'League Spartan', sans-serif;
    font-size: 1.6rem;
    font-weight: 700;
    color: #03580a;
    text-align: center;
    margin: 10px 0 34px;
}

.login-form {
    box-sizing: border-box;
    position: relative;
    width: 100%;
    max-width: 100%;
    min-height: 0;
    height: auto;
    background: #faf6ed;
    border: 1px solid #8f8e8e;
    border-radius: 12px;
    padding: 44px 20px 22px;
    overflow: visible;
}

.login-form > * {
    box-sizing: border-box;
    max-width: 100%;
}

.login-form form {
    width: 100%;
    max-width: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.login-form form > * {
    margin-bottom: 0;
}

.form-title-badge {
    box-sizing: border-box;
    position: absolute;
    top: 0;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 2;
    width: auto;
    max-width: 100%;
    white-space: nowrap;
    background: #fffaf3;
    border: 1px solid #e5e0a4;
    border-radius: 10px;
    padding: 8px 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    font-weight: 500;
    font-size: 0.9rem;
    color: #2c2c2c;
}

.form-title-badge > * {
    box-sizing: border-box;
    max-width: 100%;
}

@media (max-width: 900px) {
    .login-card {
        position: static;
        transform: none;
        margin: 24px auto;
    }
}

@media (max-width: 575.98px) {
    .login-card { padding: 20px 16px; }
    .login-form { padding: 40px 14px 18px; }
}

@media (max-width: 480px) {
    .form-title-badge {
        padding: 7px 10px;
        gap: 8px;
        white-space: normal;
        text-align: center;
    }
    .welcome-heading { font-size: 1.35rem; }
}
</style>
</head>

<body style="margin:0; font-family:'Poppins',sans-serif;">

<div class="bg-blur" style="
position: fixed;
top:0;
left:0;
width:100%;
height:100%;
background:url('/assets/images/login/backgroundforlogin2.png') no-repeat center center;
background-size:cover;
filter: saturate(1.28) contrast(1.10) brightness(1.02);
z-index:-1;
"></div>

<div class="login-card">

    <div style="text-align: center; margin-bottom: 10px;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
            <img class="logo-img" src="/assets/images/passenger/logoR.png" style="width: 65px; height: 65px;">
            <div class="brand-name" style="
            font-family: 'League Spartan', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c2c2c;
            ">
                RConnect TMS
            </div>
        </div>

        <div class="brand-sub" style="font-size: 0.85rem; color: #6c757d; margin-top: 5px;">
            Transportation Management System for <strong>RPMVFMPC</strong>
        </div>
    </div>

    <div class="welcome-heading">
        Welcome to R-Connect!
    </div>

    <div class="login-form">

        <div class="form-title-badge">

            <div class="auth-badge-icon">
                <img src="/assets/images/login/login-icon.png">
            </div>

            <div class="auth-badge-text">
                Fill your details here:
            </div>

        </div>

        <div id="loginAlert"></div>
        <?php if (!empty($error)): ?>
        <script>
        (function(){
            <?php
            [$alertVariant, $alertTitle, $alertDetail, $lockUntilSecs] = loginAlertParts($error, $user ?? null);
            ?>
            var variant = <?= json_encode($alertVariant) ?>;
            var title   = <?= json_encode($alertTitle) ?>;
            var detail  = <?= json_encode($alertDetail) ?>;
            var lockUntilSecs = <?= json_encode($lockUntilSecs) ?>;
            showAuthAlert('loginAlert', variant, title, detail);
            if (variant === 'locked' && lockUntilSecs) {
                startLockCountdown('login', 'loginAlert', lockUntilSecs, detail);
            }
        })();
        </script>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="?_=<?= htmlspecialchars($_GET['_'] ?? '') ?>">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="mb-3 auth-field">
                <div class="input-icon">
                    <i class="bi bi-person-fill"></i>
                </div>
                <input type="text" name="username" class="form-control" placeholder="Enter your Username ID:" required style="border-radius: 0 8px 8px 0; border-left: none; height: 46px;">
            </div>

            <div class="mb-3 auth-field" style="position: relative;">
                <div class="input-icon">
                    <i class="bi bi-lock-fill"></i>
                </div>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter your Password:" required style="border-radius: 0 8px 8px 0; border-left: none; height: 46px; padding-right: 45px;">
                <button type="button" onclick="togglePassword()" style="position: absolute; right: 12px; background: none; border: none;">
                    <i id="eyeIcon" class="bi bi-eye" style="color: #0e7f00;"></i>
                </button>
            </div>

            <div class="d-flex justify-content-between mb-3 form-check-row" style="font-size: 0.9rem;">
                <label>
                    <input type="checkbox" name="remember">
                    Remember Me
                </label>
                <a href="/<?= htmlspecialchars($incomingPortal) ?>/forgot-password" style="color: #4b794d;">
                    Forgot Password?
                </a>
            </div>

            <button type="submit" id="loginBtn" class="btn action-btn">
                <span id="loginBtnText">Login</span>
            </button>

        </form>

    </div>

</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.remove('bi-eye');
        eyeIcon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        eyeIcon.classList.remove('bi-eye-slash');
        eyeIcon.classList.add('bi-eye');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var loginForm = document.getElementById('loginForm');
    var loginBtn = document.getElementById('loginBtn');
    if (!loginForm || !loginBtn) return;
    loginForm.addEventListener('submit', function (e) {
        if (!authLoading(loginBtn, 'Signing in...')) {
            e.preventDefault();
        }
    });
});
</script>
<script src="/assets/js/site-transitions.js"></script>
</body>
</html>

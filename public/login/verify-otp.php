<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/init.php';

use App\Auth\Auth;
use App\Core\Database;
use App\Core\MailerService;

$error = '';
$success = '';
$redirectUrl = '';

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
if (!hash_equals($portalKey, $_GET['portal'] ?? '')) {
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

$baseUrl = '';

function roleRedirectUrl(string $role, string $baseUrl = ''): string
{
    $tokenPath = file_exists(__DIR__ . '/../../admin/config/access_token.php')
        ? __DIR__ . '/../../admin/config/access_token.php'
        : (($_SERVER['DOCUMENT_ROOT'] ?? '') . '/admin/config/access_token.php');
    $adminToken = file_exists($tokenPath) ? require $tokenPath : '';

    $normRole = strtolower(trim($role));
    if ($normRole === 'admin' || $normRole === 'super_admin') {
        return !empty($adminToken)
            ? $baseUrl . '/admin/dashboard/' . $adminToken
            : $baseUrl . '/admin/dashboard';
    } elseif ($normRole === 'finance_manager' || $normRole === 'finance_officer') {
        return !empty($adminToken)
            ? $baseUrl . '/finance/dashboard/' . $adminToken
            : $baseUrl . '/finance/dashboard';
    }
    return $baseUrl . '/';
}

function redirectByRole(string $role, string $baseUrl = ''): void
{
    header('Location: ' . roleRedirectUrl($role, $baseUrl));
    exit;
}

if (Auth::check()) {
    $user = Auth::user();
    $role = $user['role'] ?? '';
    redirectByRole($role, $baseUrl);
}

if (isset($_GET['back'])) {
    unset($_SESSION['otp']);
    unset($_SESSION['user']);
    setcookie('remember', '', time() - 3600, '/');
    session_unset();
    session_destroy();
    header('Location: ' . $baseUrl . '/' . urlencode($_GET['portal'] ?? ''));
    exit;
}

if (!isset($_SESSION['otp'])) {
    session_unset();
    session_destroy();
    header('Location: ' . $baseUrl . '/' . urlencode($_GET['portal'] ?? ''));
    exit;
}

$otpSession = $_SESSION['otp'];

if (!isset($_SESSION['otp']['attempts'])) {
    $_SESSION['otp']['attempts'] = 0;
}

$isInactive = false;
if (isset($otpSession['user_id'])) {
    $pdo = Database::get();
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([(int)$otpSession['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && strtolower($user['status'] ?? '') !== 'active') {
        $isInactive = true;
        unset($_SESSION['otp']);
        unset($_SESSION['user']);
        session_unset();
        session_destroy();
        $error = 'Your account is currently inactive. Please contact the administrator to reactivate your account.';
    }
}

if (!$isInactive && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_otp') {
    header('Content-Type: application/json');
    try {
        if (!isset($_SESSION['otp'])) {
            throw new Exception('Session expired. Please log in again.');
        }

        $otpSession = $_SESSION['otp'];

        $expires = (int)($_SESSION['otp']['expires'] ?? 0);
        $remaining = $expires - time();
        if ($remaining > 0) {
            $min = floor($remaining / 60);
            $sec = $remaining % 60;
            $waitText = $min > 0
                ? ($min . ' minute' . ($min === 1 ? '' : 's') . ' and ' . $sec . ' second' . ($sec === 1 ? '' : 's'))
                : ($sec . ' second' . ($sec === 1 ? '' : 's'));
            throw new Exception('The current code is still valid. Please wait ' . $waitText . ' before requesting a new code.');
        }

        $otp = random_int(100000, 999999);
        $_SESSION['otp']['hash'] = password_hash((string)$otp, PASSWORD_DEFAULT);
        $_SESSION['otp']['expires'] = time() + 300;
        $_SESSION['otp']['attempts'] = 0;

        $mailer = new MailerService();
        $sent = $mailer->sendOtp($otpSession['email'], (string)$otp);
        if (!$sent) {
            throw new Exception('Failed to send OTP email. Please try again.');
        }

        echo json_encode([
            'success' => true,
            'expires' => $_SESSION['otp']['expires'],
            'message' => 'A new verification code was sent to your email.'
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

if (!$isInactive && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $inputOtp = trim($_POST['otp'] ?? '');

        if (empty($inputOtp)) {
            throw new Exception('Please enter the OTP code.');
        }

        if (time() > ($otpSession['expires'] ?? 0)) {
            unset($_SESSION['otp']);
            unset($_SESSION['user']);
            session_unset();
            session_destroy();
            header('Location: ' . $baseUrl . '/' . urlencode($_GET['portal'] ?? ''));
            exit;
        }

        if (($_SESSION['otp']['attempts'] ?? 0) >= 3) {
            unset($_SESSION['otp']);
            unset($_SESSION['user']);
            session_unset();
            session_destroy();
            header('Location: ' . $baseUrl . '/' . urlencode($_GET['portal'] ?? ''));
            exit;
        }

        if (password_verify($inputOtp, $otpSession['hash'])) {
            unset($_SESSION['user']);
            session_regenerate_id(true);

            $pdo = Database::get();
            $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
            $stmt->execute([(int)$otpSession['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && strtolower($user['status'] ?? '') !== 'active') {
                unset($_SESSION['otp']);
                unset($_SESSION['user']);
                session_unset();
                session_destroy();
                $error = 'Your account is currently inactive. Please contact the administrator to reactivate your account.';
                throw new Exception($error);
            }

            Auth::login((int)$otpSession['user_id'], $otpSession['role'], $otpSession['username']);
            $_SESSION['role'] = $otpSession['role'];

            $normRole = strtolower(trim((string)$otpSession['role']));
            if (in_array($normRole, ['admin', 'super_admin'], true)) {
                $tokenPath = file_exists(__DIR__ . '/../../admin/config/access_token.php')
                    ? __DIR__ . '/../../admin/config/access_token.php'
                    : (($_SERVER['DOCUMENT_ROOT'] ?? '') . '/admin/config/access_token.php');
                if (file_exists($tokenPath)) {
                    $adminToken = require $tokenPath;
                    $_SESSION['admin_access_token'] = $adminToken;
                    setcookie('admin_token', $adminToken, [
                        'expires' => 0,
                        'path' => '/',
                        'httponly' => true,
                        'samesite' => 'Strict',
                    ]);
                }
            }

            require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
            \App\Audit\Audit::log('Login', 'Login', 'Successful login (OTP verified)', (int)$otpSession['user_id'], $otpSession['username'], $otpSession['role'], null, 'Success');

            $_SESSION['session'] = [
                'started_at' => time(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ];
            $_SESSION['last_activity'] = time();

            if (!empty($otpSession['remember'])) {
                $selector = bin2hex(random_bytes(8));
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expires = date('Y-m-d H:i:s', time() + (60 * 60 * 24 * 30));
                $pdo = Database::get();

                $deleteStmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                $deleteStmt->execute([$otpSession['user_id']]);

                $stmt = $pdo->prepare("
                    INSERT INTO remember_tokens (user_id, selector, token_hash, expires)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$otpSession['user_id'], $selector, $tokenHash, $expires]);

                setcookie('remember', $selector . ':' . $token, [
                    'expires' => time() + (60 * 60 * 24 * 30),
                    'path' => '/',
                    'secure' => !empty($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            $role = $otpSession['role'];
            unset($_SESSION['otp']);

            $success = 'OTP successfully verified.';
            $redirectUrl = roleRedirectUrl($role, $baseUrl);
        }

        $_SESSION['otp']['attempts'] = ($_SESSION['otp']['attempts'] ?? 0) + 1;

        if ($_SESSION['otp']['attempts'] >= 3) {
            unset($_SESSION['otp']);
            unset($_SESSION['user']);
            session_unset();
            session_destroy();
            header('Location: ' . $baseUrl . '/' . urlencode($_GET['portal'] ?? ''));
            exit;
        }

        $remaining = 3 - $_SESSION['otp']['attempts'];
        throw new Exception("Invalid OTP. {$remaining} attempt(s) remaining.");

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
<title>Verify OTP | RConnect TMS</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="/public/login/style.css">
<link rel="stylesheet" href="/public/login/auth-layout.css?v=4">
<link rel="stylesheet" href="/public/login/auth-alert.css">
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
    gap: 15px;
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

<div style="text-align:center; margin-bottom:10px;">
<div style="display:flex; justify-content:center; align-items:center; gap:10px;">
<img class="logo-img" src="/assets/images/passenger/logoR.png" style="width:65px;">
<div class="brand-name" style="font-family:'League Spartan'; font-size:1.5rem; font-weight:700;">
RConnect TMS
</div>
</div>

<div class="brand-sub" style="font-size:0.85rem; color:#6c757d; margin-top:5px;">
Transportation Management System for <strong>RPMVFMPC</strong>
</div>
</div>

<div class="welcome-heading">
Verify OTP
</div>

<div class="login-form">

<div class="form-title-badge">

<div class="auth-badge-icon">
<i class="bi bi-key-fill"></i>
</div>

<div class="auth-badge-text">
Enter your OTP code
</div>

</div>

<form method="POST">

<input type="hidden" name="action" id="otpFormAction" value="verify">

<div class="auth-field">
<div class="input-icon">
<i class="bi bi-key-fill"></i>
</div>

<input type="text" name="otp" id="otpInput" class="form-control"
placeholder="Enter 6-digit OTP"
required
style="
border-radius:0 8px 8px 0;
border:.5px solid #e5e0a4;
border-left:none;
height:46px;
font-size:1rem;
padding:0 12px;
text-align:center;
letter-spacing:3px;
">
</div>

<?php
$otpExpires = $_SESSION['otp']['expires'] ?? 0;
$otpAttempts = (int)($_SESSION['otp']['attempts'] ?? 0);
?>
<script src="/public/login/auth-alert.js"></script>

<div id="otpAlert"></div>

<?php if (!empty($success)): ?>
<script>
(function(){
    showAuthAlert('otpAlert', 'success', 'OTP verified successfully.', 'Redirecting you to your dashboard...');
    window.setTimeout(function () {
        window.location.href = <?= json_encode($redirectUrl) ?>;
    }, 1200);
})();
</script>
<?php elseif (!empty($error)): ?>
<script>
(function(){
    var msg = <?= json_encode($error) ?>;
    var remainingAttempts = <?= json_encode(3 - $otpAttempts) ?>;
    var variant = 'error';
    var title = ''; var detail = '';
    if (/please enter the otp/i.test(msg)) {
        variant = 'warning';
        title = 'Enter your OTP';
        detail = 'Please enter the verification code to continue.';
    } else if (/invalid otp/i.test(msg)) {
        variant = 'error';
        title = 'Invalid OTP.';
        detail = (remainingAttempts > 0
            ? remainingAttempts + ' attempt' + (remainingAttempts === 1 ? '' : 's') + ' remaining.'
            : 'Please request a new code.');
    } else {
        variant = 'error';
        title = 'Verification failed.';
        detail = 'Please check the code and try again.';
    }
    showAuthAlert('otpAlert', variant, title, detail);
})();
</script>
<?php elseif ($isInactive): ?>
<script>
    (function(){
        showAuthAlert('otpAlert', 'error', 'Your account is currently inactive.', 'Please contact the administrator to reactivate your account.');
    })();
</script>
<?php elseif (($otpExpires > 0) && ($otpExpires <= time())): ?>
<script>
    (function(){
        showAuthAlert('otpAlert', 'expired', 'Your OTP has expired.', 'Please request a new verification code to continue.', {
            label: 'Resend OTP',
            onClick: function () { resendOtp(); }
        });
    })();
</script>
<?php else: ?>
<script>
    showAuthAlert('otpAlert', 'info', 'OTP sent successfully.', 'A verification code was sent to your email. Enter it below to continue.');
</script>
<?php endif; ?>

<script>
var otpCountdownExpires = <?= json_encode((int)$otpExpires) ?>;
var otpHasSession = <?= json_encode(isset($_SESSION['otp'])) ?>;
var otpDone = <?= json_encode(!empty($success)) ?>;

function resendOtp() {
    var btn = document.getElementById('resendOtpBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }

    var form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    var actionField = document.createElement('input');
    actionField.type = 'hidden';
    actionField.name = 'action';
    actionField.value = 'resend_otp';
    form.appendChild(actionField);
    document.body.appendChild(form);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function () {
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.success) {
                otpCountdownExpires = resp.expires;
                showAuthAlert('otpAlert', 'success', 'New code sent.', resp.message);
                startOtpCountdown('otp', 'otpAlert', otpCountdownExpires, null, function () {
                    showAuthAlert('otpAlert', 'expired', 'Your OTP has expired.', 'Please request a new verification code to continue.', {
                        label: 'Resend OTP',
                        onClick: function () { resendOtp(); }
                    });
                });
                if (btn) { btn.disabled = false; btn.textContent = 'Resend OTP'; }
            } else {
                showAuthAlert('otpAlert', 'error', 'Resend failed.', resp.message || 'Please try again.');
                if (btn) { btn.disabled = false; btn.textContent = 'Resend OTP'; }
            }
        } catch (e) {
            showAuthAlert('otpAlert', 'error', 'Resend failed.', 'Please try again.');
            if (btn) { btn.disabled = false; btn.textContent = 'Resend OTP'; }
        }
    };
    xhr.onerror = function () {
        showAuthAlert('otpAlert', 'error', 'Resend failed.', 'Network error. Please try again.');
        if (btn) { btn.disabled = false; btn.textContent = 'Resend OTP'; }
    };
    xhr.send('action=resend_otp');
    form.remove();
}

(function(){
    if (!otpDone && otpHasSession && otpCountdownExpires > Math.floor(Date.now() / 1000)) {
        startOtpCountdown('otp', 'otpAlert', otpCountdownExpires, null, function () {
            showAuthAlert('otpAlert', 'expired', 'Your OTP has expired.', 'Please request a new verification code to continue.', {
                label: 'Resend OTP',
                onClick: function () { resendOtp(); }
            });
        });
    }
})();
</script>

<button type="submit" class="btn w-100 action-btn" id="verifyOtpBtn">
Verify OTP
</button>

<div style="display:flex; align-items:center; justify-content:center; gap:16px; margin-top:14px; font-size:0.85rem;">
    <button type="button" id="resendOtpBtn" onclick="resendOtp()" style="background:none; border:none; color:#4b794d; font-weight:500; cursor:pointer; padding:0; text-decoration:underline;">
        Resend OTP
    </button>
    <span style="color:#cdd9b5;">|</span>
    <a href="?back=1" style="color:#6c757d; text-decoration:none; font-weight:500;">
        Back to Login
    </a>
</div>

</form>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

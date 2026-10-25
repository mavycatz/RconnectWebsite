<?php
require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/utils/otp.php';

// ✅ MOBILE-ONLY GATE  [RE-ENABLED — dispatcher is mobile only]
$mobilePassed = _isMobile();
dispatcher_log("LOGIN_MOBILE_CHECK: ua=" . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . " passed=" . ($mobilePassed ? 'true' : 'false'));
if (!$mobilePassed) {
    $blockedFile = __DIR__ . '/views/blocked.php';
    if (file_exists($blockedFile)) {
        require $blockedFile;
    } else {
        echo '<!DOCTYPE html><html><body><h2>Mobile Only</h2><p>Please access from a mobile device.</p></body></html>';
    }
    exit;
}

// ✅ LOAD OBFUSCATED URLS
$urls = require __DIR__ . '/utils/urls.php';

// Initialize session via bootstrap
dispatcher_init_session();

require_once __DIR__ . '/utils/token.php';

// Flash messages (persist across redirect)
$error = $_SESSION['_flash_error'] ?? $_SESSION['error'] ?? '';
$success = $_SESSION['_flash_success'] ?? '';
unset($_SESSION['_flash_error'], $_SESSION['_flash_success'], $_SESSION['error']);

$authAlert = null;

function flashError($msg) { $_SESSION['_flash_error'] = $msg; }
function flashSuccess($msg) { $_SESSION['_flash_success'] = $msg; }

// Handle forgot password link click
if (isset($_GET['forgot'])) {
    $_SESSION['forgot_step'] = 'email';
    header("Location: /dispatcher/" . $urls['login']);
    exit;
}

// Clear forgot flow
if (isset($_GET['reset'])) {
    unset($_SESSION['forgot_step'], $_SESSION['reset_otp'], $_SESSION['reset_user'], $_SESSION['reset_otp_created']);
    header("Location: /dispatcher/" . $urls['login']);
    exit;
}

// Resend OTP via GET
if (isset($_GET['resend']) && isset($_SESSION['otp_user']) && !empty($_SESSION['otp_user']['email'])) {
    $throttle = dispatcher_otp_throttle_check('login');
    if ($throttle !== null) {
        flashError($throttle);
    } else {
        dispatcher_otp_throttle_note('login');
        $otp = dispatcher_otp_generate();
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_created'] = time();
        $_SESSION['otp_attempts'] = 0;
        $_SESSION['debug_otp'] = $otp;
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/MailerService.php';
        $mailer = new App\Core\MailerService();
        $sent = $mailer->sendOtp($_SESSION['otp_user']['email'], $otp);
        if ($sent) {
            flashSuccess('New OTP sent to your email.');
        } else {
            flashError($_SESSION['error'] ?? 'Failed to send OTP.');
            unset($_SESSION['error']);
        }
    }
    header("Location: /dispatcher/" . $urls['login']);
    exit;
}

// Clear OTP / forgot session — used by "Back" links
if (isset($_GET['clear'])) {
    unset($_SESSION['otp'], $_SESSION['otp_user'], $_SESSION['otp_created'], $_SESSION['debug_otp']);
    unset($_SESSION['forgot_step'], $_SESSION['reset_otp'], $_SESSION['reset_user'], $_SESSION['reset_otp_created'], $_SESSION['debug_reset_otp']);
    header("Location: /dispatcher/" . $urls['login']);
    exit;
}

// Forgot password — send OTP to email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_email'])) {
    $email = trim($_POST['forgot_email'] ?? '');
    if ($email) {
        require_once dirname(__DIR__) . '/mobile/config/database.php';
        $db = new Database();
        $conn = $db->connect();
        $e = $conn->real_escape_string($email);
        $result = $conn->query("
            SELECT ia.*, i.email, i.inspector_name
            FROM inspector_accounts ia
            LEFT JOIN inspectors i ON ia.account_id = i.account_id
            WHERE i.email = '$e' AND ia.role = 'dispatcher' AND ia.status = 'active'
            LIMIT 1
        ");
        $user = $result->fetch_assoc();
        if ($user) {
            $throttle = dispatcher_otp_throttle_check('reset');
            if ($throttle !== null) {
                $error = $throttle;
            } else {
                dispatcher_otp_throttle_note('reset');
                $otp = dispatcher_otp_generate();
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['reset_user'] = $user;
                $_SESSION['reset_otp_created'] = time();
                $_SESSION['reset_otp_attempts'] = 0;
                $_SESSION['debug_reset_otp'] = $otp;
                require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/MailerService.php';
                $mailer = new App\Core\MailerService();
                if (!empty($user['email'])) {
                    $sent = $mailer->sendOtp($user['email'], $otp);
                    if (!$sent) {
                        $error = $_SESSION['error'] ?? 'Failed to send OTP. Try again.';
                        unset($_SESSION['error']);
                    }
                }
                $_SESSION['forgot_step'] = 'otp';
            }
        } else {
            $error = 'Email not found or not a dispatcher account.';
        }
        $conn->close();
    } else {
        $error = 'Please enter your email.';
    }
}

// Forgot password — verify reset OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_otp'])) {
    $result = dispatcher_otp_verify(
        $_POST['reset_otp'] ?? '',
        $_SESSION['reset_otp'] ?? null,
        (int) ($_SESSION['reset_otp_created'] ?? 0),
        (int) ($_SESSION['reset_otp_attempts'] ?? 0)
    );

    switch ($result['status']) {
        case 'missing':
            $error = 'Session expired. Please start again.';
            break;

        case 'expired':
        case 'too_many':
            unset(
                $_SESSION['reset_otp'],
                $_SESSION['reset_user'],
                $_SESSION['reset_otp_created'],
                $_SESSION['reset_otp_attempts'],
                $_SESSION['debug_reset_otp'],
                $_SESSION['forgot_step']
            );
            $error = $result['status'] === 'expired'
                ? 'OTP expired. Please start again.'
                : 'Too many incorrect attempts. Please start again.';
            break;

        case 'format':
            $_SESSION['reset_otp_attempts'] = $result['attempts'];
            $error = 'Please enter a valid 6-digit code.';
            break;

        case 'ok':
            unset($_SESSION['reset_otp_attempts']);
            $_SESSION['forgot_step'] = 'new_password';
            break;

        default:
            $_SESSION['reset_otp_attempts'] = $result['attempts'];
            $remaining = dispatcher_otp_verify_remaining($result['attempts']);
            $error = 'Invalid OTP. ' . $remaining . ($remaining === 1 ? ' attempt' : ' attempts') . ' remaining.';
            break;
    }
}

// Forgot password — set new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $user = $_SESSION['reset_user'] ?? null;
    if (!$user) {
        $error = 'Session expired. Please start again.';
    } elseif (strlen($newPass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($newPass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        require_once dirname(__DIR__) . '/mobile/config/database.php';
        $db = new Database();
        $conn = $db->connect();
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $id = $conn->real_escape_string($user['inspector_id']);
        $conn->query("UPDATE inspector_accounts SET password = '$hash' WHERE inspector_id = '$id'");
        $conn->close();
        $success = 'Password updated successfully. You can now login.';
        unset($_SESSION['reset_otp'], $_SESSION['reset_user'], $_SESSION['reset_otp_created'], $_SESSION['forgot_step'], $_SESSION['debug_reset_otp']);
    }
}

// Resend OTP (login OTP)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    if (isset($_SESSION['otp_user']) && !empty($_SESSION['otp_user']['email'])) {
        $throttle = dispatcher_otp_throttle_check('login');
        if ($throttle !== null) {
            $error = $throttle;
        } else {
            dispatcher_otp_throttle_note('login');
            $otp = dispatcher_otp_generate();
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_created'] = time();
            $_SESSION['otp_attempts'] = 0;
            $_SESSION['debug_otp'] = $otp;
            require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/MailerService.php';
            $mailer = new App\Core\MailerService();
            $sent = $mailer->sendOtp($_SESSION['otp_user']['email'], $otp);
            if ($sent) {
                $success = 'New OTP sent to your email.';
            } else {
                $error = $_SESSION['error'] ?? 'Failed to send OTP. Try again.';
                unset($_SESSION['error']);
            }
        }
    } else {
        $error = 'Unable to resend OTP. Please login again.';
    }
}

// STEP 1: Username + Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['otp']) && !isset($_POST['forgot_email']) && !isset($_POST['reset_otp']) && !isset($_POST['new_password']) && !isset($_POST['resend_otp'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        require_once dirname(__DIR__) . '/mobile/config/database.php';
        $db = new Database();
        $conn = $db->connect();
        $u = $conn->real_escape_string($username);

        $check = $conn->query("SELECT COUNT(*) as c FROM inspector_accounts WHERE role = 'dispatcher'")->fetch_assoc();
        if ((int)$check['c'] === 0 && $username === 'admin' && $password === 'admin123') {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $conn->query("INSERT IGNORE INTO inspector_accounts (inspector_id, password, role, status) VALUES ('admin', '$hash', 'dispatcher', 'active')");
        }

        $result = $conn->query("
            SELECT ia.*, i.email, i.inspector_name
            FROM inspector_accounts ia
            LEFT JOIN inspectors i ON ia.account_id = i.account_id
            WHERE ia.inspector_id = '$u' AND ia.role = 'dispatcher' AND ia.status = 'active'
            LIMIT 1
        ");
        $user = $result->fetch_assoc();

        // Active temporary lock: reject even with a correct password
        $isLocked = false;
        $lockEpoch = 0;
        if ($user && !empty($user['blocked_until'])) {
            $lockTs = strtotime($user['blocked_until']);
            if ($lockTs !== false && $lockTs > time()) {
                $isLocked = true;
                $lockEpoch = (int) $lockTs;
            }
        }

        if ($isLocked) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
            \App\Audit\Audit::log('Login', 'Login', 'Blocked dispatcher login: temporarily locked', null, $username, 'dispatcher', null, 'Failed');
            $authAlert = ['locked', 'Your account is temporarily locked.', 'Try again in %s.', $lockEpoch];
            $error = 'Too many unsuccessful login attempts. Your account has been temporarily locked for 15 minutes.';
        } elseif ($user && password_verify($password, $user['password'])) {
            $conn->query("UPDATE inspector_accounts SET failed_login_attempts = 0, blocked_until = NULL WHERE inspector_id = '$u' AND role = 'dispatcher'");
            $throttle = dispatcher_otp_throttle_check('login');
            if ($throttle !== null) {
                $error = $throttle;
            } else {
                dispatcher_otp_throttle_note('login');
                $otp = dispatcher_otp_generate();
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_user'] = $user;
                $_SESSION['otp_created'] = time();
                $_SESSION['otp_attempts'] = 0;
                $_SESSION['debug_otp'] = $otp;
                require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/MailerService.php';
                $mailer = new App\Core\MailerService();
                if (!empty($user['email'])) {
                    $sent = $mailer->sendOtp($user['email'], $otp);
                    if ($sent) {
                        $success = 'OTP sent to your email.';
                    } else {
                        $error = $_SESSION['error'] ?? 'Failed to send OTP. Please try again.';
                        unset($_SESSION['error']);
                    }
                } else {
                    $error = 'No email on record. Contact support.';
                }
            }
        } else {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/app/audit/Audit.php';
            \App\Audit\Audit::log('Login', 'Login', 'Failed dispatcher login: invalid credentials', null, $username, 'dispatcher', null, 'Failed');
            if ($user) {
                $conn->query("UPDATE inspector_accounts SET failed_login_attempts = COALESCE(failed_login_attempts, 0) + 1 WHERE inspector_id = '$u' AND role = 'dispatcher'");
                $attemptRow = $conn->query("SELECT failed_login_attempts FROM inspector_accounts WHERE inspector_id = '$u' AND role = 'dispatcher' LIMIT 1")->fetch_assoc();
                $failedAttempts = (int)($attemptRow['failed_login_attempts'] ?? 0);
                if ($failedAttempts >= 3) {
                    $conn->query("UPDATE inspector_accounts SET blocked_until = '" . date('Y-m-d H:i:s', time() + 900) . "', failed_login_attempts = 3 WHERE inspector_id = '$u' AND role = 'dispatcher'");
                    $authAlert = ['locked', 'Your account is temporarily locked.', 'Try again in %s.', (int)(time() + 900)];
                    $error = 'Too many unsuccessful login attempts. Your account has been temporarily locked for 15 minutes.';
                } else {
                    $remaining = 3 - $failedAttempts;
                    $authAlert = ['warning', 'Incorrect username or password.', $remaining . ($remaining === 1 ? ' attempt' : ' attempts') . ' remaining.', 0];
                    $error = "Incorrect username or password. You have {$remaining} attempt(s) remaining.";
                }
            } else {
                $error = 'Invalid credentials';
            }
        }
        $conn->close();
    } else {
        $authAlert = ['warning', 'Please enter your username and password.', 'Both fields are required to continue.', 0];
        $error = 'Please enter username and password';
    }
}

// STEP 2: OTP Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp']) && !isset($_POST['resend_otp'])) {
    $result = dispatcher_otp_verify(
        $_POST['otp'] ?? '',
        $_SESSION['otp'] ?? null,
        (int) ($_SESSION['otp_created'] ?? 0),
        (int) ($_SESSION['otp_attempts'] ?? 0)
    );

    switch ($result['status']) {
        case 'missing':
            $error = 'Session expired. Please login again.';
            break;

        case 'expired':
        case 'too_many':
            unset(
                $_SESSION['otp'],
                $_SESSION['otp_user'],
                $_SESSION['otp_created'],
                $_SESSION['otp_attempts'],
                $_SESSION['debug_otp']
            );
            $error = $result['status'] === 'expired'
                ? 'OTP expired. Please login again.'
                : 'Too many incorrect attempts. Please login again.';
            break;

        case 'format':
            $_SESSION['otp_attempts'] = $result['attempts'];
            $error = 'Please enter a valid 6-digit code.';
            break;

        case 'ok':
            $user = $_SESSION['otp_user'];
            unset(
                $_SESSION['otp'],
                $_SESSION['otp_user'],
                $_SESSION['otp_created'],
                $_SESSION['otp_attempts'],
                $_SESSION['debug_otp']
            );
            // New session id after authentication (session fixation defence).
            session_regenerate_id(true);
            $_SESSION['inspector_id'] = $user['inspector_id'];
            $_SESSION['inspector_name'] = $user['inspector_name'] ?? $user['inspector_id'];
            $_SESSION['dispatcher_token'] = generateToken($user['inspector_id']);
            dispatcher_set_remember_cookie($_SESSION['dispatcher_token']);
            header("Location: /dispatcher/" . $urls['dashboard']);
            exit;

        default:
            $_SESSION['otp_attempts'] = $result['attempts'];
            $remaining = dispatcher_otp_verify_remaining($result['attempts']);
            $error = 'Invalid OTP. ' . $remaining . ($remaining === 1 ? ' attempt' : ' attempts') . ' remaining.';
            break;
    }
}

$showOtp = isset($_SESSION['otp']);
$showForgot = isset($_SESSION['forgot_step']);
$forgotStep = $_SESSION['forgot_step'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title>Dispatcher Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/public/login/auth-alert.css?v=20260912" rel="stylesheet">
<script src="/public/login/auth-alert.js?v=20260912"></script>
<link rel="manifest" href="/dispatcher/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Dispatcher">
<meta name="theme-color" content="#47613b">
<style>
@media (min-width: 768px) {
  .banner-img { max-height: 260px; object-fit: cover; }
  .banner-title { top: 50% !important; transform: translate(-50%, -50%) !important; width: auto !important; padding: 28px 60px !important; }
  .banner-label { top: auto !important; bottom: -16px; left: 50%; transform: translateX(-50%); width: auto !important; padding: 6px 24px !important; position: absolute; }
  .banner-strip { display: none !important; }
  .login-card-wrap { margin-top: 50px !important; }
}
</style>
</head>
<body style="font-family:Poppins;background:#fef8f3;margin:0;padding-bottom:40px;">

<?php
// The shared branding header calls passenger_t(); load its helper before the
// include so this page cannot fatal with an undefined function.
require_once dirname(__DIR__) . '/passenger/utils/i18n.php';
include dirname(__DIR__) . '/passenger/views/layout/header.php';
?>

<div style="position:relative;width:100%;margin:0;padding:0;">
    <img class="banner-img" src="/assets/images/passenger/header-banner.png" style="width:100%;display:block;object-fit:cover;">

    <div class="banner-title" style="position:absolute;top:130%;left:50%;transform:translate(-50%,-50%);width:95%;max-width:800px;background:#fef8f3;border-radius:15px;text-align:center;box-shadow:0 4px 15px rgba(0,0,0,0.2);padding:25px 20px;">
        <h2 style="margin:0;font-weight:700;font-size:20px;color:#24513c;">Dispatcher Portal</h2>
        <p style="margin-top:5px;font-size:12px;color:#6c757d;">Manage bus dispatch and queue readiness</p>
    </div>

    <div class="banner-label" style="position:absolute;top:160%;left:50%;transform:translate(-50%,-50%);width:55%;max-width:450px;background:#fcf5dd;border:1px solid #c6d48c;border-radius:15px;padding:5px 10px;display:flex;align-items:center;justify-content:center;z-index:2;">
        <h3 style="margin:0;font-size:13px;font-weight:600;">
            <?php if ($showForgot): ?>
                <?php echo $forgotStep === 'new_password' ? 'New Password' : 'Forgot Password'; ?>
            <?php elseif ($showOtp): ?>
                Verify OTP
            <?php else: ?>
                Dispatcher Login
            <?php endif; ?>
        </h3>
    </div>

    <div class="banner-strip" style="position:absolute;top:160%;left:50%;transform:translateX(-50%);width:95%;background:#e3eabb;padding:30px 0;z-index:1;"></div>
</div>

<div class="login-card-wrap" style="position:relative;width:95%;max-width:900px;margin:120px auto 0 auto;display:flex;justify-content:center;background:#fef8f3;border:2px solid #c6d48c;border-radius:20px;padding:10px 0;z-index:10;">

<div style="width:95%;max-width:650px;background:#fef8f3;border-radius:20px;box-shadow:0 6px 20px rgba(0,0,0,0.15);padding:15px 20px;">

<div id="loginAlert" style="width:100%;"></div>

<?php if ($authAlert): ?>
<script>
(function(){
    var lockUntil = <?= json_encode((int)($authAlert[3] ?? 0)) ?>;
    showAuthAlert('loginAlert', <?= json_encode($authAlert[0] ?? 'error') ?>, <?= json_encode($authAlert[1] ?? 'Something went wrong.') ?>, <?= json_encode($authAlert[2] ?? 'Please try again.') ?>);
    if (lockUntil > Math.floor(Date.now() / 1000)) {
        try { startLockCountdown('dispatcher', 'loginAlert', lockUntil, 'Try again in %s.'); } catch (e) {}
    }
})();
</script>
<?php elseif ($error): ?>
<script>
(function(){
    showAuthAlert('loginAlert', 'error', 'Something went wrong.', <?= json_encode(htmlspecialchars($error, ENT_QUOTES, 'UTF-8'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
})();
</script>
<?php elseif ($success): ?>
<script>
(function(){
    showAuthAlert('loginAlert', 'success', 'Success', <?= json_encode(htmlspecialchars($success, ENT_QUOTES, 'UTF-8'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
})();
</script>
<?php endif; ?>

<!-- ========== FORGOT PASSWORD: ENTER EMAIL ========== -->
<?php if ($showForgot && $forgotStep === 'email'): ?>
<form method="POST">
<div style="display:flex;justify-content:center;margin-bottom:15px;">
    <img src="/assets/images/passenger/profile.png" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid #c6d48c;">
</div>
<div class="mb-2">
    <div style="display:flex;align-items:stretch;gap:10px;border:1px solid #c4b417;border-radius:14px;background:#fff;height:40px;margin-top:20px;">
        <div style="width:50px;flex-shrink:0;border-radius:10px;background:#faf9e5;border:1.5px solid #c2b636;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-envelope-fill" style="color:#47613b;font-size:16px;"></i>
        </div>
        <input type="email" name="forgot_email" placeholder="Enter your email" required
               style="flex:1;min-width:0;border:none;outline:none;font-size:13px;background:transparent;height:100%;">
        <div style="width:30px;flex-shrink:0;"></div>
    </div>
</div>
<div style="display:flex;justify-content:center;">
    <button type="submit" style="width:70%;margin-top:10px;padding:10px;border:none;border-radius:12px;background:linear-gradient(90deg,#306449,#b3be5b);color:#fff;font-size:16px;font-weight:700;cursor:pointer;">Send OTP</button>
</div>
<div style="text-align:center;margin-top:12px;">
    <a href="/dispatcher/<?php echo $urls['login']; ?>?clear=1" style="font-size:12px;color:#47613b;text-decoration:none;">
        <i class="bi bi-arrow-left"></i> Back to Login
    </a>
</div>
<div style="text-align:center;margin-top:14px;font-size:10px;color:#888;">Authorized personnel only</div>
</form>

<!-- ========== FORGOT PASSWORD: VERIFY OTP ========== -->
<?php elseif ($showForgot && $forgotStep === 'otp'): ?>
<form method="POST" id="resetOtpForm">
<div style="display:flex;justify-content:center;margin-bottom:10px;">
    <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#47613b,#6a8f5a);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(71,97,59,0.3);">
        <i class="bi bi-envelope-open" style="font-size:32px;color:#fff;"></i>
    </div>
</div>
<div style="text-align:center;margin-bottom:16px;">
    <div style="font-size:16px;font-weight:700;color:#24513c;">Reset Password</div>
    <div style="font-size:12px;color:#6b7280;margin-top:4px;">Enter the 6-digit code sent to your email</div>
</div>

<div style="display:flex;justify-content:center;gap:8px;margin-bottom:16px;">
    <?php for ($i = 1; $i <= 6; $i++): ?>
    <input type="text" name="reset_digit_<?= $i ?>" id="resetOtp<?= $i ?>" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required
           style="width:44px;height:52px;text-align:center;font-size:20px;font-weight:700;color:#24513c;
                  border:2px solid #cdd9b5;border-radius:12px;background:#fff;outline:none;
                  transition:border-color .2s,box-shadow .2s;"
           onfocus="this.style.borderColor='#47613b';this.style.boxShadow='0 0 0 3px rgba(71,97,59,0.15)'"
           onblur="this.style.borderColor='#cdd9b5';this.style.boxShadow='none'">
    <?php endfor; ?>
</div>
<input type="hidden" name="reset_otp" id="resetOtpCombined">

<div style="display:flex;justify-content:center;">
    <button type="submit" style="width:80%;padding:12px;border:none;border-radius:12px;background:linear-gradient(90deg,#306449,#b3be5b);color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:opacity .2s;" onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
        <i class="bi bi-check-lg"></i> Verify Code
    </button>
</div>

<div style="text-align:center;margin-top:12px;">
    <a href="/dispatcher/<?php echo $urls['login']; ?>?clear=1" style="font-size:12px;color:#47613b;text-decoration:none;font-weight:500;">
        <i class="bi bi-arrow-left"></i> Back to Login
    </a>
</div>
<div style="text-align:center;margin-top:14px;font-size:10px;color:#888;">Authorized personnel only</div>
</form>

<!-- ========== FORGOT PASSWORD: NEW PASSWORD ========== -->
<?php elseif ($showForgot && $forgotStep === 'new_password'): ?>
<form method="POST">
<div style="display:flex;justify-content:center;margin-bottom:15px;">
    <img src="/assets/images/passenger/profile.png" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid #c6d48c;">
</div>
<div class="mb-2">
    <div style="display:flex;align-items:stretch;gap:10px;border:1px solid #c4b417;border-radius:14px;background:#fff;height:40px;">
        <div style="width:50px;flex-shrink:0;border-radius:10px;background:#faf9e5;border:1.5px solid #c2b636;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-lock-fill" style="color:#47613b;"></i>
        </div>
        <input type="password" name="new_password" placeholder="New password (min 6 chars)" required minlength="6"
               style="flex:1;min-width:0;border:none;outline:none;font-size:13px;background:transparent;height:100%;">
        <div style="width:30px;flex-shrink:0;"></div>
    </div>
</div>
<div class="mb-2">
    <div style="display:flex;align-items:stretch;gap:10px;border:1px solid #c4b417;border-radius:14px;background:#fff;height:40px;margin-top:20px;">
        <div style="width:50px;flex-shrink:0;border-radius:10px;background:#faf9e5;border:1.5px solid #c2b636;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-lock-fill" style="color:#47613b;"></i>
        </div>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required minlength="6"
               style="flex:1;min-width:0;border:none;outline:none;font-size:13px;background:transparent;height:100%;">
        <div style="width:30px;flex-shrink:0;"></div>
    </div>
</div>
<div style="display:flex;justify-content:center;">
    <button type="submit" style="width:70%;margin-top:10px;padding:10px;border:none;border-radius:12px;background:linear-gradient(90deg,#306449,#b3be5b);color:#fff;font-size:16px;font-weight:700;cursor:pointer;">Reset Password</button>
</div>
<div style="text-align:center;margin-top:14px;font-size:10px;color:#888;">Authorized personnel only</div>
</form>

<!-- ========== LOGIN FORM ========== -->
<?php elseif ($showOtp): ?>

<form method="POST" id="otpForm">
<div style="display:flex;justify-content:center;margin-bottom:10px;">
    <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#47613b,#6a8f5a);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(71,97,59,0.3);">
        <i class="bi bi-shield-check" style="font-size:32px;color:#fff;"></i>
    </div>
</div>
<div style="text-align:center;margin-bottom:16px;">
    <div style="font-size:16px;font-weight:700;color:#24513c;">Two-Factor Authentication</div>
    <div style="font-size:12px;color:#6b7280;margin-top:4px;">Enter the 6-digit code sent to your email</div>
</div>

<div style="display:flex;justify-content:center;gap:8px;margin-bottom:16px;">
    <?php for ($i = 1; $i <= 6; $i++): ?>
    <input type="text" name="otp_digit_<?= $i ?>" id="otp<?= $i ?>" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required
           style="width:44px;height:52px;text-align:center;font-size:20px;font-weight:700;color:#24513c;
                  border:2px solid #cdd9b5;border-radius:12px;background:#fff;outline:none;
                  transition:border-color .2s,box-shadow .2s;"
           onfocus="this.style.borderColor='#47613b';this.style.boxShadow='0 0 0 3px rgba(71,97,59,0.15)'"
           onblur="this.style.borderColor='#cdd9b5';this.style.boxShadow='none'">
    <?php endfor; ?>
</div>
<input type="hidden" name="otp" id="otpCombined">

<div style="display:flex;justify-content:center;">
    <button type="submit" id="verifyBtn" style="width:80%;padding:12px;border:none;border-radius:12px;background:linear-gradient(90deg,#306449,#b3be5b);color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:opacity .2s;" onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
        <i class="bi bi-check-lg"></i> Verify OTP
    </button>
</div>

<div style="display:flex;justify-content:center;align-items:center;gap:16px;margin-top:16px;">
    <button type="button" id="resendOtpBtn" style="background:none;border:none;color:#47613b;font-weight:600;cursor:pointer;font-size:12px;text-decoration:underline;padding:0;">
        <i class="bi bi-arrow-clockwise"></i> Resend Code
    </button>
    <span style="color:#cdd9b5;">|</span>
    <a href="/dispatcher/<?php echo $urls['login']; ?>?clear=1" style="color:#47613b;font-weight:600;font-size:12px;text-decoration:none;">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>
<div style="text-align:center;margin-top:14px;font-size:10px;color:#888;">Authorized personnel only</div>
</form>

<!-- ========== LOGIN: USERNAME + PASSWORD ========== -->
<?php else: ?>

<form method="POST" onsubmit="var b=this.querySelector('button[type=submit]');if(b){authLoading(b,'Please wait...');}">
<div style="display:flex;justify-content:center;margin-bottom:15px;">
    <img src="/assets/images/passenger/profile.png" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid #c6d48c;">
</div>

<div class="mb-2">
    <div style="display:flex;align-items:stretch;gap:10px;border:1px solid #c4b417;border-radius:14px;background:#fff;height:40px;">
        <div style="width:50px;flex-shrink:0;border-radius:10px;background:#faf9e5;border:1.5px solid #c2b636;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-person-fill" style="color:#47613b;"></i>
        </div>
        <input type="text" name="username" placeholder="Enter username" required
               style="flex:1;min-width:0;border:none;outline:none;font-size:13px;background:transparent;height:100%;">
        <div style="width:30px;flex-shrink:0;"></div>
    </div>
</div>

<div class="mb-2">
    <div style="display:flex;align-items:stretch;gap:10px;border:1px solid #c4b417;border-radius:14px;background:#fff;height:40px;margin-top:20px;">
        <div style="width:50px;flex-shrink:0;border-radius:10px;background:#faf9e5;border:1.5px solid #c2b636;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-lock-fill" style="color:#47613b;"></i>
        </div>
        <input type="password" id="passwordInput" name="password" placeholder="Enter your password" required
               style="flex:1;min-width:0;border:none;outline:none;font-size:13px;background:transparent;height:100%;">
        <div onclick="togglePassword()" style="width:30px;flex-shrink:0;display:flex;align-items:center;justify-content:center;cursor:pointer;">
            <i id="eyeIcon" class="bi bi-eye-slash" style="color:#47613b;font-size:16px;"></i>
        </div>
    </div>
</div>

<div style="display:flex;justify-content:center;">
    <button type="submit" style="width:70%;margin-top:10px;padding:10px;border:none;border-radius:12px;background:linear-gradient(90deg,#306449,#b3be5b);color:#fff;font-size:16px;font-weight:700;cursor:pointer;">Login</button>
</div>

<div style="text-align:center;margin-top:12px;">
    <a href="?forgot=1" style="font-size:12px;color:#47613b;text-decoration:none;font-weight:500;">
        Forgot Password?
    </a>
</div>
<div style="text-align:center;margin-top:14px;font-size:10px;color:#888;">Authorized personnel only</div>
</form>

<?php endif; ?>

</div>
</div>

<script>
if('serviceWorker' in navigator){navigator.serviceWorker.register('/dispatcher/sw.js');}

/**
 * Shared OTP field handler for the dispatcher 2FA and reset-password forms.
 * Supports typing (hardware + mobile keyboards), pasting a full code into any
 * box, auto-filling every digit, backspace/replacement, hidden combined-field
 * sync, and automatic verification once all digits are present.
 */
function dispatcherOtpInit(opts){
    var form = document.getElementById(opts.form);
    if(!form) return;

    var inputs = [];
    for(var i = 0; i < opts.ids.length; i++){
        var el = document.getElementById(opts.ids[i]);
        if(el) inputs.push(el);
    }
    if(!inputs.length) return;

    var combined = opts.combined ? document.getElementById(opts.combined) : null;
    var submitted = false;

    function getCode(){
        var code = '';
        for(var i = 0; i < inputs.length; i++){
            code += (inputs[i].value || '').replace(/\D/g, '');
        }
        return code;
    }

    function sync(){
        if(combined) combined.value = getCode();
    }

    function focusIndex(idx){
        idx = Math.max(0, Math.min(idx, inputs.length - 1));
        inputs[idx].focus();
        inputs[idx].select();
    }

    function deferFocusIndex(idx){
        if(typeof requestAnimationFrame === 'function'){
            requestAnimationFrame(function(){ focusIndex(idx); });
        } else {
            setTimeout(function(){ focusIndex(idx); }, 0);
        }
    }

    function setDigit(idx, char){
        if(idx < 0 || idx >= inputs.length) return;
        inputs[idx].value = char || '';
        sync();
    }

    function distributeFrom(startIdx, digits){
        for(var i = 0; i < digits.length; i++){
            var idx = startIdx + i;
            if(idx >= inputs.length) break;
            inputs[idx].value = digits[i];
        }
        sync();
        deferFocusIndex(Math.min(startIdx + digits.length, inputs.length - 1));
    }

    function fill(digitsStr){
        var cleaned = (digitsStr || '').replace(/\D/g, '').slice(0, inputs.length);
        if(!cleaned) return;
        for(var i = 0; i < inputs.length; i++){
            inputs[i].value = '';
        }
        submitted = false;
        distributeFrom(0, cleaned);
    }

    function maybeAutoSubmit(){
        if(submitted || getCode().length !== inputs.length) return;
        submitted = true;
        sync();
        if(typeof form.requestSubmit === 'function'){
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function handlePaste(e){
        var clipboard = e.clipboardData || window.clipboardData;
        if(e.preventDefault) e.preventDefault();
        if(clipboard){
            fill(clipboard.getData('text') || '');
        }
        maybeAutoSubmit();
    }

    inputs.forEach(function(input, idx){
        input.addEventListener('paste', handlePaste);

        input.addEventListener('focus', function(){
            this.select();
        });

        input.addEventListener('input', function(){
            var digits = (this.value || '').replace(/\D/g, '');

            if(digits === ''){
                this.value = '';
                submitted = false;
                sync();
                return;
            }

            if(digits.length > 1){
                distributeFrom(idx, digits.slice(0, inputs.length - idx));
                maybeAutoSubmit();
                return;
            }

            this.value = digits;
            sync();
            if(idx < inputs.length - 1){
                deferFocusIndex(idx + 1);
            }
            if(getCode().length === inputs.length){
                maybeAutoSubmit();
            }
        });

        input.addEventListener('keydown', function(e){
            if(e.key === 'Backspace'){
                e.preventDefault();
                submitted = false;
                if(this.value){
                    setDigit(idx, '');
                    if(idx > 0) focusIndex(idx - 1);
                } else if(idx > 0){
                    setDigit(idx - 1, '');
                    focusIndex(idx - 1);
                }
                return;
            }

            if(e.key === 'ArrowLeft' && idx > 0){
                e.preventDefault();
                focusIndex(idx - 1);
                return;
            }

            if(e.key === 'ArrowRight' && idx < inputs.length - 1){
                e.preventDefault();
                focusIndex(idx + 1);
                return;
            }

            if(e.key.length === 1 && /^[0-9]$/.test(e.key) && !e.ctrlKey && !e.metaKey && !e.altKey){
                e.preventDefault();
                submitted = false;
                setDigit(idx, e.key);
                if(idx < inputs.length - 1){
                    focusIndex(idx + 1);
                }
                maybeAutoSubmit();
            }
        });
    });

    form.addEventListener('submit', function(e){
        sync();
        if(getCode().length !== inputs.length){
            e.preventDefault();
            for(var i = 0; i < inputs.length; i++){
                inputs[i].style.borderColor = '#dc2626';
                inputs[i].style.boxShadow = '0 0 0 3px rgba(220,38,38,0.15)';
            }
            return false;
        }
    });

    setTimeout(function(){
        var target = inputs[0];
        for(var i = 0; i < inputs.length; i++){
            if(!inputs[i].value){ target = inputs[i]; break; }
        }
        target.focus();
    }, 100);
}

if(document.getElementById('otpForm')){
    dispatcherOtpInit({
        form: 'otpForm',
        ids: ['otp1','otp2','otp3','otp4','otp5','otp6'],
        combined: 'otpCombined'
    });
}

if(document.getElementById('resetOtpForm')){
    dispatcherOtpInit({
        form: 'resetOtpForm',
        ids: ['resetOtp1','resetOtp2','resetOtp3','resetOtp4','resetOtp5','resetOtp6'],
        combined: 'resetOtpCombined'
    });
}

// Resend OTP
document.getElementById('resendOtpBtn')?.addEventListener('click', function(){
    const btn = this;
    btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Sending...';
    btn.disabled = true;
    window.location.href = '/dispatcher/<?php echo $urls['login']; ?>?resend=1';
});

function togglePassword() {
    const passwordInput = document.getElementById("passwordInput");
    const eyeIcon = document.getElementById("eyeIcon");
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        eyeIcon.classList.remove("bi-eye-slash");
        eyeIcon.classList.add("bi-eye");
    } else {
        passwordInput.type = "password";
        eyeIcon.classList.remove("bi-eye");
        eyeIcon.classList.add("bi-eye-slash");
    }
}


</script>
</body>
</html>

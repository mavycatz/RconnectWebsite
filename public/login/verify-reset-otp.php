<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Bootstrap.php';

use App\Models\User;
use App\Core\Database;
use App\Core\Csrf;

$portalKey = require $_SERVER['DOCUMENT_ROOT'] . '/public/config/portal_key.php';
if (!hash_equals($portalKey, $_GET['portal'] ?? '')) {
    header('Location: /');
    exit;
}

$error = '';
$success = '';
$redirect = '';

$pdo = Database::get();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (empty($_SESSION['password_reset_user'])) {
    header('Location: forgot-password');
    exit;
}

$userId = (int) $_SESSION['password_reset_user'];
$userModel = new User();
$reset = $userModel->getActivePasswordReset($userId);

if (!$reset) {
    $sessionOtp = $_SESSION['password_reset_otp'] ?? null;
    if ($sessionOtp && strtotime($sessionOtp['expires']) > time()) {
        $reset = [
            'id' => 0,
            'otp_hash' => $sessionOtp['hash'],
            'expires_at' => $sessionOtp['expires'],
            'attempts' => $sessionOtp['attempts'] ?? 0,
        ];
    } else {
        unset($_SESSION['password_reset_otp']);
        unset($_SESSION['password_reset_user']);
        $error = 'Invalid or expired reset request. Please request a new OTP.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        $inputOtp = trim($_POST['otp'] ?? '');

        if (empty($inputOtp)) {
            $error = 'Please enter the OTP.';
        } elseif ($reset['attempts'] >= 5) {
            $userModel->deletePasswordReset($userId);
            unset($_SESSION['password_reset_otp']);
            unset($_SESSION['password_reset_user']);
            $pdo->prepare("
                INSERT INTO security_logs (user_id, event, ip_address)
                VALUES (?, 'Password Reset OTP Max Attempts Reached', ?)
            ")->execute([$userId, $ip]);
            $error = 'Too many attempts. Please request a new OTP.';
        } elseif (strtotime($reset['expires_at']) < time()) {
            $userModel->deletePasswordReset($userId);
            unset($_SESSION['password_reset_otp']);
            unset($_SESSION['password_reset_user']);
            $pdo->prepare("
                INSERT INTO security_logs (user_id, event, ip_address)
                VALUES (?, 'Password Reset OTP Expired', ?)
            ")->execute([$userId, $ip]);
            $error = 'OTP expired. Please request a new one.';
        } elseif (password_verify($inputOtp, $reset['otp_hash'])) {
            $pdo->prepare("
                INSERT INTO security_logs (user_id, event, ip_address)
                VALUES (?, 'Password Reset OTP Verified', ?)
            ")->execute([$userId, $ip]);
            $_SESSION['password_reset_verified'] = true;
            $success = 'OTP Verified!';
            $redirect = 'reset-password';
        } else {
            if ((int)$reset['id'] === 0) {
                $_SESSION['password_reset_otp']['attempts'] = ($_SESSION['password_reset_otp']['attempts'] ?? 0) + 1;
            } else {
                $userModel->incrementResetAttempts((int)$reset['id']);
            }
            $pdo->prepare("
                INSERT INTO security_logs (user_id, event, ip_address)
                VALUES (?, 'Password Reset OTP Failed', ?)
            ")->execute([$userId, $ip]);
            $error = 'Invalid OTP.';
        }
    }
}

$csrfToken = Csrf::generate();
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

    <div style="text-align: center; margin-bottom: 10px;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
            <img class="logo-img" src="/assets/images/passenger/logoR.png" style="width: 65px; height: 65px;">
            <div class="brand-name" style="font-family: 'League Spartan'; font-size: 1.5rem; font-weight: 700;">
                RConnect TMS
            </div>
        </div>

        <div class="brand-sub" style="font-size: 0.85rem; color: #6c757d; margin-top: 5px;">
            Transportation Management System for <strong>RPMVFMPC</strong>
        </div>
    </div>

    <div class="welcome-heading">
        Verify OTP
    </div>

    <div class="login-form">

        <div class="form-title-badge">

            <div class="auth-badge-icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>

            <div class="auth-badge-text">
                Enter your verification code
            </div>

        </div>

            <form method="POST">

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <script src="/public/login/auth-alert.js"></script>
                <div id="resetOtpAlert"></div>

                <div class="auth-field">
                    <div class="input-icon">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>

                    <input type="text" name="otp" class="form-control"
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

                <button type="submit" class="btn w-100 action-btn">
                    Verify OTP
                </button>

                <div class="text-center" style="font-size:0.9rem;">
                    <a href="forgot-password" style="color:#4b794d; text-decoration:none;">
                        Request New OTP
                    </a>
                </div>

            </form>

    </div>

</div>

<script>
<?php if ($success): ?>
showAuthAlert('resetOtpAlert', 'success', 'OTP Verified!', 'Proceeding to reset your password...');
window.setTimeout(function(){ window.location.href = <?= json_encode($redirect) ?>; }, 1200);
<?php endif; ?>
</script>

<script>
(function(){
    var error = <?= json_encode($error) ?>;
    var variant = 'error'; var title = ''; var detail = '';

    if (error) {
        <?php
        $attempts = 0;
        if (isset($_SESSION['password_reset_otp'])) { $attempts = (int)($_SESSION['password_reset_otp']['attempts'] ?? 0); }
        elseif ($reset) { $attempts = (int)($reset['attempts'] ?? 0); }
        $remainingReset = max(0, 5 - $attempts);
        ?>
        if (/csrf/i.test(error)) { variant='error'; title='Security check failed.'; detail='Please refresh the page and try again.'; }
        else if (/please enter the otp/i.test(error)) { variant='warning'; title='Enter your OTP'; detail='Please enter the verification code to continue.'; }
        else if (/too many attempts/i.test(error)) { variant='locked'; title='Too many attempts.'; detail='Please request a new OTP.'; }
        else if (/expired/i.test(error)) { variant='expired'; title='OTP expired.'; detail='Please request a new OTP.'; }
        else if (/invalid/i.test(error)) {
            variant='error'; title='Invalid OTP.'; detail=(<?= $remainingReset ?> > 0 ? '<?= $remainingReset ?> attempt' + (<?= $remainingReset ?>===1?'':'s') + ' remaining.' : 'Please request a new OTP.');
        }
        else { variant='error'; title='Verification failed.'; detail='Please check the code and try again.'; }
        showAuthAlert('resetOtpAlert', variant, title, detail);
    }
})();
</script>

<script>
(function(){
    var hasOtp = <?= json_encode(!empty($_SESSION['password_reset_otp'])) ?>;
    var expiresSecs = <?= json_encode(
        isset($_SESSION['password_reset_otp']['expires']) ? (int)strtotime($_SESSION['password_reset_otp']['expires']) : 0
    ) ?>;
    var hasReset = <?= json_encode(!empty($reset) && !empty($reset['expires_at'])) ?>;
    var resetExpires = <?= !empty($reset['expires_at']) ? json_encode((int)strtotime($reset['expires_at'])) : '0' ?>;

    var expires = Math.max(expiresSecs, resetExpires);
    var done = <?= json_encode(!empty($success)) ?>;
    var justArrived = <?= json_encode(empty($error) && empty($success)) ?>;

    if (!done && (hasOtp || hasReset) && expires > Math.floor(Date.now() / 1000)) {
        if (justArrived) {
            showAuthAlert('resetOtpAlert', 'info', 'Verification code sent.', 'Enter the code sent to your email.');
        }
        startOtpCountdown('resetsotp', 'resetOtpAlert', expires);
    }
})();
</script>

</body>
</html>

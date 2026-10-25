<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Bootstrap.php';

use App\Models\User;
use App\Core\Database;
use App\Core\MailerService;
use App\Core\Csrf;

$portalKey = require $_SERVER['DOCUMENT_ROOT'] . '/public/config/portal_key.php';
if (!hash_equals($portalKey, $_GET['portal'] ?? '')) {
    header('Location: /');
    exit;
}

$error = '';
$success = '';
$pdo = Database::get();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
        die('Invalid CSRF token.');
    }

    $input = trim((string) filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

    if (empty($input)) {
        $error = 'Please enter your username or email.';
    } else {
        $userModel = new User();
        $user = $userModel->findByUsernameOrEmail($input);

        if ($user) {
            $otp = random_int(100000, 999999);
            $otpHash = password_hash((string)$otp, PASSWORD_DEFAULT);
            $expires = date('Y-m-d H:i:s', time() + 300);

            if (($user['role'] ?? '') === 'driver') {
                $userModel->deletePasswordReset($user['id']);
                $userModel->storePasswordReset($user['id'], $otpHash, $expires);
            } else {
                $_SESSION['password_reset_otp'] = [
                    'hash' => $otpHash,
                    'expires' => $expires,
                    'attempts' => 0,
                ];
            }

            $pdo->prepare("
                INSERT INTO security_logs (user_id, event, ip_address)
                VALUES (?, 'Password Reset OTP Sent', ?)
            ")->execute([$user['id'], $ip]);

            $mailer = new MailerService();
            $sent = $mailer->sendOtp($user['email'], (string)$otp);

            if (!$sent) {
                $error = 'Failed to send OTP email. Please try again.';
                $pdo->prepare("
                    INSERT INTO security_logs (user_id, event, ip_address)
                    VALUES (?, 'Password Reset Email Failed', ?)
                ")->execute([$user['id'], $ip]);
            } else {
                $_SESSION['password_reset_user'] = $user['id'];
                header('Location: verify-reset-otp');
                exit;
            }
        } else {
            $error = 'If the account exists, an OTP has been sent.';
            $pdo->prepare("
                INSERT INTO security_logs (event, ip_address)
                VALUES ('Password Reset Unknown User Attempt', ?)
            ")->execute([$ip]);
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
<title>Forgot Password | RConnect TMS</title>

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
        Forgot Password
    </div>

    <div class="login-form">

        <div class="form-title-badge">

            <div class="auth-badge-icon">
                <i class="bi bi-envelope-fill"></i>
            </div>

            <div class="auth-badge-text">
                Enter your account details
            </div>

        </div>

<script src="/public/login/auth-alert.js"></script>
<div id="forgotAlert" style="width:100%;"></div>

<?php if (!empty($error)): ?>
<script>
(function(){
    var msg = <?= json_encode($error) ?>;
    var variant = 'error'; var title = ''; var detail = '';
    if (/please enter your username or email/i.test(msg)) { variant='warning'; title='Enter your account details.'; detail='Please provide your username or email to continue.'; }
    else if (/failed to send otp/i.test(msg)) { variant='error'; title='Something went wrong.'; detail='We could not send the verification code. Please try again.'; }
    else if (/if the account exists/i.test(msg)) { variant='info'; title='Check your email.'; detail='If the account exists, a verification code has been sent to the registered email address.'; }
    else { variant='error'; title='Something went wrong.'; detail='Please try again later.'; }
    showAuthAlert('forgotAlert', variant, title, detail);
})();
</script>
<?php endif; ?>

<form method="POST" style="margin-top: 10px;">

    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

    <div class="auth-field">
        <div class="input-icon">
            <i class="bi bi-envelope-fill"></i>
        </div>

        <input type="text" name="username" class="form-control"
        placeholder="Enter your Username or Email"
        required
        style="
        border-radius: 0 8px 8px 0;
        border: .5px solid #e5e0a4;
        border-left: none;
        height: 46px;
        font-size: 1rem;
        padding: 0 12px;
        ">
    </div>

    <button type="submit" class="btn action-btn">
        Send OTP
    </button>

    <div class="text-center" style="font-size: 0.9rem;">
        <a href="/<?= htmlspecialchars($_GET['portal']) ?>" style="color: #4b794d; text-decoration: none;">
            Back to Login
        </a>
    </div>

</form>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

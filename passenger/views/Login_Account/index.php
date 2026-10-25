<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/app/Core/Database.php";
$lang = passenger_current_language();
$translations = passenger_translations($lang);
$languages = passenger_available_languages();
$languageKeys = ['en' => 'login.language_english', 'tl' => 'login.language_tagalog', 'ilo' => 'login.language_ilocano'];
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title><?= htmlspecialchars(passenger_t('login.title', 'Passenger Login'), ENT_QUOTES, 'UTF-8') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php include __DIR__ . "/../layout/csrf.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/public/login/auth-alert.css?v=20260912" rel="stylesheet">
<script src="/public/login/auth-alert.js?v=20260912"></script>
<style>

@media (max-width:768px){
    body{ padding-bottom: 160px; }
}
</style>
<script>

document.addEventListener('focusin', function(e){
    var t = e.target;
    if(t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA')){
        try { t.scrollIntoView({block:'nearest', behavior:'smooth'}); } catch(err) {}
    }
});
</script>
<link rel="manifest" href="/passenger/manifest.json">
<link rel="apple-touch-icon" href="/assets/images/logo/logoR.png">
<meta name="theme-color" content="#47613b">
</head>

<body style="font-family:'Poppins'; background:#fef8f3;">
<?php include __DIR__ . "/../layout/header.php"; ?>

<div style="position: relative; width: 100%; margin: 0; padding: 0;">

    <img src="/assets/images/passenger/header-banner.png" style="width:100%; display:block; object-fit:cover;">

    <div style="
        position: absolute;
        top: 130%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 95%;
        max-width: 800px;
        background: #fef8f3;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        padding: 25px 20px;
    ">
        <h2 style="margin:0; font-weight:700; font-size:20px; color:#24513c;">
            <?= htmlspecialchars(passenger_t('login.welcome', 'Welcome to R-Connect!'), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p style="margin-top:5px; font-size:12px; color:#6c757d;">
            <?= htmlspecialchars(passenger_t('login.subtitle', 'Your journey starts here!'), ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>

    <div style="
        position: absolute;
        top: 160%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 55%;
        max-width: 450px;
        background: #fcf5dd;
        border: 1px solid #c6d48c;
        border-radius: 15px;
        padding: 5px 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2;
    ">
        <h3 style="margin:0; font-size:13px; font-weight:600;">
            <?= htmlspecialchars(passenger_t('login.title', 'Passenger Login'), ENT_QUOTES, 'UTF-8') ?>
        </h3>
    </div>

    <div style="
        position: absolute;
        top: 160%;
        left: 50%;
        transform: translateX(-50%);
        width: 95%;
        background: #e3eabb;
        padding: 30px 0;
        z-index: 1;
    "></div>

</div>

<div style="
    position: relative;
    width: 95%;
    max-width: 900px;
    margin: 120px auto 0 auto;
    display: flex;
    justify-content: center;
    background: #fef8f3;
    border: 2px solid #c6d48c;
    border-radius: 20px;
    padding: 10px 0;
    z-index: 10;
">

<div style="
    width: 95%;
    max-width: 650px;
    background: #fef8f3;
    border-radius: 20px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    padding: 15px 20px;
">

<form method="POST" action="/passenger/login" onsubmit="var lb=document.getElementById('loginSubmitBtn');var ob=document.getElementById('otpSubmitBtn');if(ob){ob.textContent='';authLoading(ob,'Verifying OTP Please wait...');}else if(lb){lb.textContent='';authLoading(lb,'Please wait...');}">
<?php if (function_exists("passenger_csrf_token")): ?>
<input type="hidden" name="_csrf" value="<?= htmlspecialchars(passenger_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
<?php endif; ?>
<input type="hidden" name="trusted_token" id="trustedTokenField" value="">

<?php if (isset($_SESSION["auth_alert"])): ?>
<?php
$_aa = $_SESSION["auth_alert"];
unset($_SESSION["auth_alert"], $_SESSION["error"], $_SESSION["success"]);
?>
<div id="loginAlert" style="width:100%;"></div>
<script>
(function(){
    var lockUntil = <?= json_encode(isset($_aa["lock_until"]) ? (int) $_aa["lock_until"] : 0) ?>;
    showAuthAlert('loginAlert', <?= json_encode($_aa["variant"] ?? "error") ?>, <?= json_encode(
    $_aa["title"] ?? "Something went wrong.",
) ?>, <?= json_encode($_aa["detail"] ?? "Please try again.") ?>);
    if (lockUntil > Math.floor(Date.now() / 1000)) {
        try { startLockCountdown('passenger', 'loginAlert', lockUntil, 'Try again in %s.'); } catch (e) {}
    }
})();
</script>
<?php elseif (isset($_SESSION["error"])): ?>
<?php
$_authErr = $_SESSION["error"];
unset($_SESSION["error"]);
?>
<div id="loginAlert" style="width:100%;"></div>
<script>
(function(){
    showAuthAlert('loginAlert', 'error', <?= json_encode(
        passenger_t('login.failed_title', 'Login failed'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>, <?= json_encode(
        $_authErr,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>);
})();
</script>
<?php elseif (isset($_SESSION["success"])): ?>
<?php
$_authOk = $_SESSION["success"];
unset($_SESSION["success"]);
?>
<div id="loginAlert" style="width:100%;"></div>
<script>
(function(){
    showAuthAlert('loginAlert', 'success', <?= json_encode(
        passenger_t('login.success_title', 'Success'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>, <?= json_encode(
        $_authOk,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>);
})();
</script>
<?php elseif (isset($_GET["reason"]) && $_GET["reason"] === "session_invalidated"): ?>
<div id="loginAlert" style="width:100%;"></div>
<script>
(function(){
    showAuthAlert('loginAlert', 'warning', <?= json_encode(
        passenger_t('session.invalidated_title', 'Session Ended'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>, <?= json_encode(
        passenger_t('session.invalidated_body', 'Your account is currently active on another device or browser. Please log in again.'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>);
})();
</script>
<?php elseif (isset($_GET["reason"]) && $_GET["reason"] === "expired"): ?>
<div id="loginAlert" style="width:100%;"></div>
<script>
(function(){
    showAuthAlert('loginAlert', 'warning', <?= json_encode(
        passenger_t('session.expired_title', 'Session Expired'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>, <?= json_encode(
        passenger_t('session.expired_body', 'Your session is no longer valid. Please log in again.'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>);
})();
</script>
<?php elseif (isset($_GET["reason"]) && $_GET["reason"] === "inactive"): ?>
<div id="loginAlert" style="width:100%;"></div>
<script>
(function(){
    showAuthAlert('loginAlert', 'error', <?= json_encode(
        passenger_t('login.inactive_title', 'Account Inactive'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>, <?= json_encode(
        passenger_t('login.inactive_detail', 'Your account is currently inactive. Please contact the administrator.'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>);
})();
</script>
<?php elseif (isset($_GET["reason"]) && $_GET["reason"] === "unavailable"): ?>
<div id="loginAlert" style="width:100%;"></div>
<script>
(function(){
    showAuthAlert('loginAlert', 'warning', <?= json_encode(
        passenger_t('session.unavailable_title', 'Session Check Failed'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>, <?= json_encode(
        passenger_t('session.unavailable_body', 'We could not verify your account right now. Please try again in a moment.'),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
    ) ?>);
})();
</script>
<?php else: ?>
<div id="loginAlert" style="width:100%;"></div>
<?php endif; ?>

<div style="margin-bottom:8px;">
    <?php include __DIR__ . "/../layout/language.php"; ?>
</div>

<?php if (!isset($_SESSION["otp"])): ?>
<div style="display:flex; justify-content:center; margin-bottom:15px;">
    <img src="/assets/images/passenger/profile.png" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid #c6d48c;">
</div>
<?php endif; ?>
<?php if (isset($_SESSION["otp"])): ?>
<a href="/passenger/login/back" style="text-decoration:none;font-size:13px;color:#47613b;display:inline-flex;align-items:center;gap:5px;margin-bottom:5px;">
    <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(passenger_t('login.back', 'Back'), ENT_QUOTES, 'UTF-8') ?>
</a>
<?php endif; ?>

<?php if (!isset($_SESSION["otp"])): ?>

<div class="mb-2">
    <div style="
        display:flex;
        align-items:center;
        gap:10px;
        width:100%;
        height:40px;
        box-sizing:border-box;
        border:1px solid #c4b417;
        border-radius:14px;
        background:#fff;
        overflow:hidden;
    ">
        <div style="
            flex:0 0 50px;
            width:50px;
            height:100%;
            box-sizing:border-box;
            border-radius:10px;
            background:#faf9e5;
            border:1.5px solid #c2b636;
            display:flex;
            align-items:center;
            justify-content:center;
        ">
            <i class="bi bi-person-fill" style="color:#47613b;"></i>
        </div>

        <input
            type="text"
            name="login"
            value="<?= $_COOKIE["remember_login"] ?? "" ?>"
            placeholder="<?= htmlspecialchars(passenger_t('login.email_or_mobile', 'Enter your email or mobile'), ENT_QUOTES, 'UTF-8') ?>"
            maxlength="100"
            autocomplete="username"
            style="
                flex:1 1 auto;
                min-width:0;
                width:100%;
                height:100%;
                box-sizing:border-box;
                border:none;
                outline:none;
                padding:0 10px;
                font-size:16px;
                background:transparent;
            "
            required>
    </div>
</div>
<?php endif; ?>

<?php if (!isset($_SESSION["otp"])): ?>

<div class="mb-2">
    <div style="
        display:flex;
        align-items:center;
        gap:10px;
        width:100%;
        height:40px;
        box-sizing:border-box;
        border:1px solid #c4b417;
        border-radius:14px;
        background:#fff;
        overflow:hidden;
        margin-top:20px;
    ">
        <div style="
            flex:0 0 50px;
            width:50px;
            height:100%;
            box-sizing:border-box;
            border-radius:10px;
            background:#faf9e5;
            border:1.5px solid #c2b636;
            display:flex;
            align-items:center;
            justify-content:center;
        ">
            <i class="bi bi-lock-fill" style="color:#47613b;"></i>
        </div>

        <input
            type="password"
            id="passwordInput"
            name="password"
            maxlength="72"
            autocomplete="current-password"
            placeholder="<?= htmlspecialchars(passenger_t('login.password', 'Enter your password'), ENT_QUOTES, 'UTF-8') ?>"
            style="
                flex:1 1 auto;
                min-width:0;
                width:100%;
                height:100%;
                box-sizing:border-box;
                border:none;
                outline:none;
                padding:0 10px;
                font-size:16px;
                background:transparent;
            "
            required>

        <div
            onclick="togglePassword()"
            style="
                flex:0 0 30px;
                width:30px;
                height:100%;
                display:flex;
                align-items:center;
                justify-content:center;
                cursor:pointer;
            ">
            <i
                id="eyeIcon"
                class="bi bi-eye-slash"
                style="color:#47613b;font-size:16px;">
            </i>
        </div>
    </div>
</div>

<?php endif; ?>

<?php if (isset($_SESSION["otp"])): ?>
<div style="text-align:center;margin-top:16px;">
    <div style="display:flex;justify-content:center;margin-bottom:10px;">
        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#47613b,#6a8f5a);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(71,97,59,0.3);">
            <i class="bi bi-shield-check" style="font-size:32px;color:#fff;"></i>
        </div>
    </div>
    <div style="font-size:16px;font-weight:700;color:#24513c;"><?= htmlspecialchars(passenger_t('otp.title', 'Two-Factor Authentication'), ENT_QUOTES, 'UTF-8') ?></div>
    <div style="font-size:12px;color:#6b7280;margin-top:4px;margin-bottom:16px;"><?= htmlspecialchars(passenger_t('otp.subtitle', 'Enter the 6-digit code sent to your email'), ENT_QUOTES, 'UTF-8') ?></div>
    <div style="display:flex;justify-content:center;gap:8px;margin-bottom:16px;">
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <input type="text" name="otp_digit_<?= $i ?>" id="loginOtp<?= $i ?>" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required
               style="width:44px;height:52px;text-align:center;font-size:20px;font-weight:700;color:#24513c;
                      border:2px solid #cdd9b5;border-radius:12px;background:#fff;outline:none;
                      transition:border-color .2s,box-shadow .2s;"
               onfocus="this.style.borderColor='#47613b';this.style.boxShadow='0 0 0 3px rgba(71,97,59,0.15)'"
               onblur="this.style.borderColor='#cdd9b5';this.style.boxShadow='none'">
        <?php endfor; ?>
    </div>
    <div id="otpExpireRow" style="display:flex;justify-content:center;align-items:center;gap:6px;font-size:12px;color:#8a6d3b;margin-bottom:12px;">
        <i class="bi bi-clock" style="font-size:13px;"></i>
        <span><?= htmlspecialchars(passenger_t('otp.expires_in', 'Code expires in'), ENT_QUOTES, 'UTF-8') ?> <strong id="otpCountdown" style="font-variant-numeric:tabular-nums;">05:00</strong></span>
    </div>
    <input type="hidden" name="otp" id="loginOtpCombined">
    <button type="submit" id="otpSubmitBtn" style="width:70%;padding:10px;border:none;border-radius:12px;background:linear-gradient(90deg,#306449,#b3be5b);color:#fff;font-size:16px;font-weight:700;cursor:pointer;"><?= htmlspecialchars(passenger_t('otp.verify', 'Verify OTP'), ENT_QUOTES, 'UTF-8') ?></button>
    <div style="display:flex;justify-content:center;align-items:center;gap:16px;margin-top:14px;">
        <button type="button" id="loginResendOtpBtn" style="background:none;border:none;color:#47613b;font-weight:600;cursor:pointer;font-size:12px;text-decoration:underline;padding:0;">
            <i class="bi bi-arrow-clockwise"></i> <?= htmlspecialchars(passenger_t('otp.resend', 'Resend Code'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <span style="color:#cdd9b5;">|</span>
        <a href="/passenger/login/back" style="color:#47613b;font-weight:600;font-size:12px;text-decoration:none;">
            <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(passenger_t('login.back', 'Back'), ENT_QUOTES, 'UTF-8') ?>
        </a>
    </div>
</div>
<?php endif; ?>

<?php if (!isset($_SESSION["otp"])): ?>

<div style="display:flex; justify-content:center;">
    <button type="submit" id="loginSubmitBtn" style="
        width:70%;
        margin-top:10px;
        padding:10px;
        border:none;
        border-radius:12px;
        background: linear-gradient(90deg, #306449, #b3be5b);
        color:#fff;
        font-size:16px;
        font-weight:700;
    ">
        <?= htmlspecialchars(passenger_t('login.button', 'Login'), ENT_QUOTES, 'UTF-8') ?>
    </button>
</div>

<div style="text-align:center; margin-top:10px; font-size:12px; margin-bottom:20px;">
    <a href="/passenger/forgot-password"
       style="color:#545454; text-decoration:none;">
       <?= htmlspecialchars(passenger_t('login.forgot', 'Forgot Password'), ENT_QUOTES, 'UTF-8') ?>
    </a>
</div>

<div style="text-align:center; margin-top:5px; font-size:12px;">
    <?= htmlspecialchars(passenger_t('login.no_account', "Don't have an account?"), ENT_QUOTES, 'UTF-8') ?> 
    <a href="/passenger/register"
       style="background: linear-gradient(90deg, #306449, #b3be5b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-weight:600;">
        <?= htmlspecialchars(passenger_t('login.create_account', 'Create Account'), ENT_QUOTES, 'UTF-8') ?>
    </a>
</div>
<?php endif; ?>

</form>
</div>
</div>

<?php include __DIR__ . "/../layout/otp-input.php"; ?>
<script>
const PASSENGER_I18N = <?= json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const PASSENGER_LANG = <?= json_encode($lang) ?>;
function pt(key, fallback, replace){
    let text = (PASSENGER_I18N && Object.prototype.hasOwnProperty.call(PASSENGER_I18N, key)) ? PASSENGER_I18N[key] : (fallback !== undefined ? fallback : key);
    if(replace){
        Object.keys(replace).forEach(function(k){ text = text.split(':' + k).join(String(replace[k])); });
    }
    return text;
}
(function(){
    try {
        const trusted = localStorage.getItem('remember_passenger');
        const field = document.getElementById('trustedTokenField');
        if(trusted && field){ field.value = trusted; }
    } catch(e) {}
})();

function togglePassword(){
    const input = document.getElementById("passwordInput");
    const icon = document.getElementById("eyeIcon");

    if(input.type === "password"){
        input.type = "text";
        icon.classList.replace("bi-eye-slash","bi-eye");
    } else {
        input.type = "password";
        icon.classList.replace("bi-eye","bi-eye-slash");
    }
}

function initializeLoginOtpForm(){
    if(!window.PassengerOtp) return;

    PassengerOtp.init({
        ids: ['loginOtp1', 'loginOtp2', 'loginOtp3', 'loginOtp4', 'loginOtp5', 'loginOtp6'],
        combined: 'loginOtpCombined'
    });
}

initializeLoginOtpForm();

document.getElementById('loginResendOtpBtn')?.addEventListener('click', function(){
    const btn = this;
    btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> ' + pt('otp.sending', 'Sending...');
    btn.disabled = true;
    window.location.href = '/passenger/login/resend';
});

(function(){
    var otpCreated = <?php echo (int) ($_SESSION["otp_created"] ?? 0); ?>;
    var el = document.getElementById('otpCountdown');
    var row = document.getElementById('otpExpireRow');
    var btn = document.getElementById('otpSubmitBtn');
    if(!otpCreated || !el || !row) return;

    var expiresAt = (otpCreated + 300) * 1000;

    function tick(){
        var left = expiresAt - Date.now();

        if(left <= 0){
            el.textContent = '00:00';
            el.style.color = '#a93226';
            var msg = row.querySelector('span');
            if(msg){
                msg.innerHTML = pt('otp.expired_inline', 'Code expired.') + ' <a href="/passenger/login/resend" style="color:#47613b;font-weight:600;">' + pt('otp.resend_inline', 'Resend code') + '</a>';
            }
            if(btn){
                btn.disabled = true;
                btn.style.opacity = '.55';
                btn.style.cursor = 'not-allowed';
            }
            clearInterval(authOtpTimer);
            return;
        }

        var total = Math.floor(left / 1000);
        var m = Math.floor(total / 60);
        var s = total % 60;
        el.textContent = ('0' + m).slice(-2) + ':' + ('0' + s).slice(-2);
        el.style.color = left <= 60000 ? '#c0392b' : '#8a6d3b';
    }

    tick();
    var authOtpTimer = setInterval(tick, 1000);
})();
</script>

</body>
</html>

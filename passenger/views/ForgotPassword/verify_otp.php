<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title>Verify OTP</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php include __DIR__ . "/../layout/csrf.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

</head>

<body style="font-family:'Poppins'; background:#fef8f3;">

<?php include __DIR__ . "/../layout/header.php"; ?>

<div style="position: relative; width: 100%; margin: 0; padding: 0;">

    <img src="/assets/images/passenger/header-banner.png"
         style="width:100%; display:block; object-fit:cover;">

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

        <h2 style="
            margin:0;
            font-weight:700;
            font-size:20px;
            color:#24513c;
        ">
            Verify OTP
        </h2>

        <p style="
            margin-top:5px;
            font-size:12px;
            color:#6c757d;
        ">
            Enter the OTP sent to your email
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

        <h3 style="
            margin:0;
            font-size:13px;
            font-weight:600;
        ">
            OTP Verification
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

<form method="POST"
      action="/passenger/verify-reset-otp-process"
      id="otpForm">

<?php if (function_exists("passenger_csrf_token")): ?>
<input type="hidden" name="_csrf" value="<?= htmlspecialchars(passenger_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
<?php endif; ?>

<?php if (isset($_SESSION["error"])): ?>
<div style="
    background:#ffe5e5;
    color:#b30000;
    padding:10px;
    border-radius:10px;
    font-size:12px;
    margin-bottom:10px;
    text-align:center;
">
    <?php echo $_SESSION["error"]; unset($_SESSION["error"]); ?>
</div>
<?php endif; ?>
<?php if (isset($_SESSION["success"])): ?>
<div style="
    background:#e5ffe5;
    color:#006600;
    padding:10px;
    border-radius:10px;
    font-size:12px;
    margin-bottom:10px;
    text-align:center;
">
    <?php echo $_SESSION["success"]; unset($_SESSION["success"]); ?>
</div>
<?php endif; ?>

<div style="
    display:flex;
    justify-content:center;
    margin-bottom:10px;
">
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
    <input type="text" name="otp_digit_<?= $i ?>" id="otp<?= $i ?>" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required
           style="width:44px;height:52px;text-align:center;font-size:20px;font-weight:700;color:#24513c;
                  border:2px solid #cdd9b5;border-radius:12px;background:#fff;outline:none;
                  transition:border-color .2s,box-shadow .2s;"
           onfocus="this.style.borderColor='#47613b';this.style.boxShadow='0 0 0 3px rgba(71,97,59,0.15)'"
           onblur="this.style.borderColor='#cdd9b5';this.style.boxShadow='none'">
    <?php endfor; ?>
</div>
<input type="hidden" name="otp" id="otpCombined">

<div style="display:flex; justify-content:center;">

<button type="submit"
        style="
            width:70%;
            margin-top:10px;
            padding:10px;
            border:none;
            border-radius:12px;
            background: linear-gradient(90deg, #306449, #b3be5b);
            color:#fff;
            font-size:16px;
            font-weight:700;
            cursor:pointer;
        ">
    Verify Code
</button>

</div>

<div style="display:flex;justify-content:center;align-items:center;gap:16px;margin-top:16px;">
    <button type="button" id="resendOtpBtn" style="background:none;border:none;color:#47613b;font-weight:600;cursor:pointer;font-size:12px;text-decoration:underline;padding:0;">
        <i class="bi bi-arrow-clockwise"></i> Resend Code
    </button>
    <span style="color:#cdd9b5;">|</span>
    <a href="/passenger/forgot-password" style="color:#47613b;font-weight:600;font-size:12px;text-decoration:none;">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

</form>

<?php include __DIR__ . "/../layout/otp-input.php"; ?>

<script>
var otpController = window.PassengerOtp
    ? PassengerOtp.init({
        ids: ['otp1', 'otp2', 'otp3', 'otp4', 'otp5', 'otp6'],
        combined: 'otpCombined'
    })
    : null;

document.getElementById('otpForm')?.addEventListener('submit', function(e){
    if(otpController) otpController.sync();
    const combined = document.getElementById('otpCombined');
    if(!combined || combined.value.length !== 6){
        e.preventDefault();
        for(let i = 1; i <= 6; i++){
            const el = document.getElementById('otp' + i);
            if(el){
                el.style.borderColor = '#dc2626';
                el.style.boxShadow = '0 0 0 3px rgba(220,38,38,0.15)';
            }
        }
        return false;
    }
});

if(otpController) otpController.focusIndex(0);

document.getElementById('resendOtpBtn')?.addEventListener('click', function(){
    const btn = this;
    btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Sending...';
    btn.disabled = true;
    window.location.href = '/passenger/resend-reset-otp';
});
</script>
</div>
</div>

</body>
</html>

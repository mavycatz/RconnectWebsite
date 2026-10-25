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
        <h2 style="margin:0; font-family:'Poppins'; font-weight:700; font-size:20px; color:#24513c;">
            Passenger Login
        </h2>
        <p style="margin-top:5px; font-size:12px; color:#6c757d;">
            Welcome to R-Connect!<br>
            Your journey starts here!
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
            Enter your login credentials
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

<form method="POST" action="/passenger/login">
<?php if (function_exists("passenger_csrf_token")): ?>
<input type="hidden" name="_csrf" value="<?= htmlspecialchars(passenger_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
    <button type="button" onclick="history.back()" style="border:none;background:none;display:flex;align-items:center;gap:5px;font-weight:600;">
        <i class="bi bi-arrow-left"></i> Back
    </button>

    <select class="form-select" style="width:75px;font-size:12px;padding:2px 6px;">
        <option>EN</option>
        <option>TL</option>
        <option>ILO</option>
    </select>
</div>

<div class="mb-2">
<label style="display:flex;gap:6px;">
<i class="bi bi-person-fill" style="color:#47613b;"></i>
<span>Enter your email or mobile</span>
</label>
<input type="text" name="login" class="form-control form-control-sm" maxlength="100" autocomplete="username" placeholder="Enter your email or mobile" required>
</div>

<div class="mb-2">
<label style="display:flex;gap:6px;">
<i class="bi bi-lock-fill" style="color:#47613b;"></i>
<span>Enter your password</span>
</label>
<input type="password" name="password" class="form-control form-control-sm" maxlength="72" autocomplete="current-password" placeholder="Enter your password" required>
</div>

<button class="btn btn-success w-100 btn-sm mt-2">Login</button>

<div style="text-align:center; margin-top:10px; font-size:12px;">
    <a href="/passenger/forgot-password">Forgot Password</a>
</div>

<div style="text-align:center; margin-top:5px; font-size:12px;">
    Don't have an account? 
    <a href="/passenger/register">Create Account</a>
</div>

</form>
</div>
</div>

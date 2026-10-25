<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title>Forgot Password</title>
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
            Forgot Password
        </h2>

        <p style="
            margin-top:5px;
            font-size:12px;
            color:#6c757d;
        ">
            Recover your R-Connect account
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
            Password Recovery
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
      action="/passenger/send-reset-otp">

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

<div style="
    display:flex;
    justify-content:center;
    margin-bottom:15px;
">

    <img src="/assets/images/passenger/profile.png"
         style="
            width:80px;
            height:80px;
            border-radius:50%;
            object-fit:cover;
            border:2px solid #c6d48c;
         ">

</div>

<div class="mb-2">

<div style="
    display:flex;
    align-items:stretch;
    gap:10px;
    border:1px solid #c4b417;
    border-radius:14px;
    background:#fff;
    height:40px;
">

    <div style="
        width:50px;
        border-radius:10px;
        background:#faf9e5;
        border:1.5px solid #c2b636;
        display:flex;
        align-items:center;
        justify-content:center;
    ">
        <i class="bi bi-envelope-fill"
           style="color:#47613b;"></i>
    </div>

    <input type="email"
           name="email"
           maxlength="100"
           autocomplete="email"
           placeholder="Enter your email"
           style="
                flex:1;
                border:none;
                outline:none;
                font-size:13px;
                background:transparent;
                height:100%;
           "
           required>

</div>
</div>

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
        ">
    Send OTP
</button>

</div>

</form>
</div>
</div>

</body>
</html>

<?php

$redirect = "/passenger/home"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Confirming Payment</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="2;url=<?php echo htmlspecialchars($redirect); ?>">
<style>
body{font-family:'Poppins',sans-serif;background:#f4f1ea;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh;}
.card{background:#fff;border-radius:20px;box-shadow:0 4px 20px rgba(0,0,0,0.08);padding:28px 34px;text-align:center;max-width:360px;width:90%;}
.spinner{width:42px;height:42px;border:4px solid #e3eabb;border-top-color:#24513c;border-radius:50%;margin:0 auto 16px;animation:spin 0.9s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
.title{font-weight:700;color:#24513c;font-size:16px;margin-bottom:6px;}
.sub{font-size:12px;color:#6c757d;line-height:1.6;}
a{color:#24513c;font-size:12px;}
</style>
</head>
<body>
<div class="card">
    <div class="spinner"></div>
    <div class="title">Confirming your payment...</div>
    <div class="sub">Taking you back to your trip. If you are not redirected automatically, <a href="<?php echo htmlspecialchars(
        $redirect,
    ); ?>">tap here</a>.</div>
</div>
<script>setTimeout(function(){ window.location.href = <?php echo json_encode($redirect); ?>; }, 2000);</script>
</body>
</html>

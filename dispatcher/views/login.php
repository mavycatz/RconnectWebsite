<?php
$sessionPath = dirname(__DIR__) . '/storage/sessions';
@mkdir($sessionPath, 0777, true);
ini_set('session.save_path', $sessionPath);
@session_start();
$error = $_GET['error'] ?? '';
if (isset($_SESSION['dispatcher_id'])) {
    header("Location: /dispatcher/index.php?page=dispatch");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title>R-Connect Dispatcher - Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<meta name="theme-color" content="#47613b">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{font-family:'Poppins',sans-serif;background:#fef8f3;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.login-card{width:100%;max-width:380px;background:#faf9e5;border:2px solid #e5e0a4;border-radius:24px;padding:32px 24px;box-shadow:0 8px 25px rgba(0,0,0,0.08);}
.login-logo{text-align:center;margin-bottom:24px;}
.login-logo h4{font-weight:700;color:#24513c;margin:8px 0 4px;font-size:20px;}
.login-logo p{font-size:11px;color:#6c757d;margin:0;}
.form-control{border-radius:12px;border:1px solid #c6d48c;padding:11px 14px;font-size:13px;font-family:'Poppins',sans-serif;}
.form-control:focus{border-color:#47613b;box-shadow:0 0 0 2px rgba(71,97,59,0.15);}
.btn-login{width:100%;padding:11px;border:none;border-radius:12px;background:#47613b;color:#fff;font-weight:600;font-size:14px;cursor:pointer;transition:.2s;}
.btn-login:hover{background:#24513c;}
.error-msg{background:#f8d7da;color:#721c24;padding:10px;border-radius:10px;font-size:12px;margin-bottom:12px;display:none;}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">
    <img src="/assets/images/passenger/buslogo.png" alt="R-Connect" style="height:50px;">
    <h4>R-Connect</h4>
    <p>Dispatcher Operations Portal</p>
  </div>
  <div class="error-msg" id="errorMsg"></div>
  <form onsubmit="handleLogin(event)">
    <div style="margin-bottom:14px;">
      <label style="font-size:12px;color:#24513c;font-weight:600;margin-bottom:4px;display:block;">Username</label>
      <input type="text" id="username" class="form-control" placeholder="Enter username" required>
    </div>
    <div style="margin-bottom:20px;">
      <label style="font-size:12px;color:#24513c;font-weight:600;margin-bottom:4px;display:block;">Password</label>
      <input type="password" id="password" class="form-control" placeholder="Enter password" required>
    </div>
    <button type="submit" class="btn-login">Sign In</button>
  </form>
</div>
<script>
async function handleLogin(e) {
  e.preventDefault();
  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value.trim();
  const errorEl = document.getElementById('errorMsg');
  if (!username || !password) { errorEl.textContent = 'Please enter username and password'; errorEl.style.display = 'block'; return; }
  try {
    const res = await fetch('/dispatcher/api/login.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ username, password }) });
    const json = await res.json();
    if (json.success) { window.location.href = '/dispatcher/index.php?page=dispatch'; }
    else { errorEl.textContent = json.message || 'Invalid credentials'; errorEl.style.display = 'block'; }
  } catch(e) { errorEl.textContent = 'Connection error'; errorEl.style.display = 'block'; }
}
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mobile Only - Dispatcher</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Poppins', sans-serif;
    background: #fef8f3;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: -35%;
    right: -15%;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(198,212,140,0.2) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

body::after {
    content: '';
    position: fixed;
    bottom: -30%;
    left: -15%;
    width: 450px;
    height: 450px;
    background: radial-gradient(circle, rgba(36,81,60,0.08) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.card {
    background: #fff;
    border-radius: 28px;
    padding: 44px 36px 36px;
    max-width: 440px;
    width: 100%;
    text-align: center;
    box-shadow: 0 8px 32px rgba(36,81,60,0.10);
    border: 1px solid rgba(198,212,140,0.25);
    position: relative;
    z-index: 1;
}

.phone-mockup {
    width: 68px;
    height: 112px;
    margin: 0 auto 20px;
    background: linear-gradient(180deg, #47613b 0%, #24513c 100%);
    border-radius: 16px;
    position: relative;
    box-shadow: 0 6px 20px rgba(36,81,60,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    animation: float 3s ease-in-out infinite;
}

.phone-mockup::before {
    content: '';
    position: absolute;
    top: 7px;
    left: 50%;
    transform: translateX(-50%);
    width: 34px;
    height: 4px;
    background: rgba(255,255,255,0.12);
    border-radius: 2px;
}

.phone-mockup i {
    font-size: 30px;
    color: #c6d48c;
}

.phone-mockup::after {
    content: '';
    position: absolute;
    bottom: 6px;
    left: 50%;
    transform: translateX(-50%);
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.1);
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-7px); }
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #24513c;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    padding: 6px 16px;
    border-radius: 100px;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    margin-bottom: 16px;
}

.logo {
    width: 70px;
    height: 70px;
    border-radius: 18px;
    margin-bottom: 18px;
    box-shadow: 0 4px 12px rgba(36,81,60,0.12);
}

h1 {
    font-size: 24px;
    font-weight: 800;
    color: #24513c;
    line-height: 1.2;
    margin-bottom: 10px;
}

.subtitle {
    font-size: 14px;
    color: #6c757d;
    font-weight: 400;
    line-height: 1.7;
    margin-bottom: 28px;
    max-width: 320px;
    margin-left: auto;
    margin-right: auto;
}

.steps {
    display: flex;
    flex-direction: column;
    gap: 10px;
    text-align: left;
    margin-bottom: 28px;
}

.step {
    display: flex;
    align-items: center;
    gap: 14px;
    background: #fcf8ed;
    padding: 12px 16px;
    border-radius: 14px;
    border: 1px solid rgba(198,212,140,0.3);
    transition: all 0.2s;
}

.step:hover {
    background: #f9f4e4;
    border-color: rgba(198,212,140,0.5);
    transform: translateX(4px);
}

.step-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
    color: #fff;
}

.step-icon.primary { background: #24513c; }
.step-icon.accent { background: #c6d48c; color: #24513c; }
.step-icon.dark { background: #47613b; }

.step-text {
    font-size: 13px;
    font-weight: 500;
    color: #2d3e35;
    line-height: 1.4;
}

.step-text small {
    display: block;
    font-weight: 400;
    color: #8a9b91;
    font-size: 12px;
    margin-top: 1px;
}

.divider {
    height: 1px;
    background: linear-gradient(to right, transparent, rgba(198,212,140,0.4), transparent);
    margin-bottom: 20px;
}

.footer-text {
    font-size: 12px;
    color: #8a9b91;
    line-height: 1.6;
}

.footer-text i {
    margin-right: 4px;
    color: #c6d48c;
}

@media (max-width: 480px) {
    .card { padding: 28px 20px 24px; }
    h1 { font-size: 21px; }
}
</style>
</head>
<body>
<div class="card">
    <div class="badge">
        <i class="bi bi-shield-check"></i>
        Access Restricted
    </div>

    <div class="phone-mockup">
        <i class="bi bi-phone"></i>
    </div>

    <img class="logo" src="/assets/images/logo/logoR.png" alt="R-Connect">

    <h1>Mobile Only</h1>

    <p class="subtitle">
        The R-Connect Dispatcher Portal is designed for on-the-go use.
        Please access it from your smartphone or tablet.
    </p>

    <div class="steps">
        <div class="step">
            <div class="step-icon accent">
                <i class="bi bi-qr-code-scan"></i>
            </div>
            <div class="step-text">
                Open this link on your mobile browser
                <small>Copy the URL and send it to your phone</small>
            </div>
        </div>
        <div class="step">
            <div class="step-icon primary">
                <i class="bi bi-phone-flip"></i>
            </div>
            <div class="step-text">
                Use the dispatcher mobile app
                <small>Install via the PWA prompt on your phone</small>
            </div>
        </div>
        <div class="step">
            <div class="step-icon dark">
                <i class="bi bi-headset"></i>
            </div>
            <div class="step-text">
                Already on mobile? Go back and try again
                <small>Your device may have been detected incorrectly</small>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    <p class="footer-text">
        <i class="bi bi-info-circle"></i>
        Need help? Contact your terminal supervisor or system admin.
    </p>
</div>
</body>
</html><?php exit; ?>

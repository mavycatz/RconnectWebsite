<?php

// The controller passes the bus validated by scan.php; the view never reads
// the URL directly, and the register link carries no bus id.
$oneTimeBus = trim((string) ($bus ?? ""));

$oneTimeLoginUrl = "/passenger/login";
$oneTimeRegisterUrl = "/passenger/one-time/register";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title>R-Connect — Current Trip</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php include __DIR__ . "/../layout/csrf.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="manifest" href="/passenger/manifest.json">
<link rel="apple-touch-icon" href="/assets/images/logo/logoR.png">
<meta name="theme-color" content="#47613b">
<style>
body{
    font-family:'Poppins', sans-serif;
    background:#fef8f3;
}
.ot-card{
    background:#fef8f3;
    border:1.5px solid #c6d48c;
    border-radius:20px;
    padding:16px;
    box-shadow:0 6px 20px rgba(0,0,0,.12);
}
.ot-choice{
    display:flex;
    align-items:center;
    gap:14px;
    width:100%;
    box-sizing:border-box;
    background:#fff;
    border:1.5px solid #c6d48c;
    border-radius:16px;
    padding:14px;
    min-height:72px;
    margin-top:12px;
    text-decoration:none;
    transition:.2s;
    -webkit-tap-highlight-color:transparent;
}
.ot-choice:active{
    transform:scale(.985);
}
.ot-choice-icon{
    width:46px;
    height:46px;
    flex-shrink:0;
    border-radius:14px;
    background:#eef4e6;
    border:1px solid #d5e4c6;
    display:flex;
    align-items:center;
    justify-content:center;
}
.ot-choice-icon i{
    font-size:20px;
    color:#47613b;
}
.ot-choice-title{
    font-size:15px;
    font-weight:700;
    color:#24513c;
    line-height:1.25;
}
.ot-choice-desc{
    font-size:11.5px;
    color:#7b8a74;
    margin-top:2px;
    line-height:1.4;
}
.ot-choice-arrow{
    margin-left:auto;
    color:#5d8550;
    font-size:16px;
    flex-shrink:0;
}
.ot-primary-btn{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    width:100%;
    box-sizing:border-box;
    min-height:48px;
    border:none;
    border-radius:12px;
    background:linear-gradient(135deg,#47613b,#5d8550);
    color:#fff;
    font-family:'Poppins', sans-serif;
    font-size:14px;
    font-weight:700;
    padding:13px 16px;
    text-decoration:none;
    box-shadow:0 4px 12px rgba(71,97,59,.25);
    touch-action:manipulation;
    cursor:pointer;
}
.ot-secondary-link{
    display:block;
    text-align:center;
    margin-top:14px;
    font-size:12px;
    color:#47613b;
    font-weight:600;
    text-decoration:none;
}
.ot-alert-icon{
    width:54px;
    height:54px;
    margin:0 auto;
    border-radius:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#fff4d6;
    border:1.5px solid #eecb6f;
}
.ot-alert-icon i{
    font-size:24px;
    color:#b7791f;
}
.ot-alert-title{
    font-size:19px;
    font-weight:700;
    color:#24513c;
    text-align:center;
    margin-top:12px;
}
.ot-alert-text{
    font-size:13px;
    color:#6c757d;
    text-align:center;
    margin-top:6px;
    line-height:1.5;
}
.ot-spinner{
    width:28px;
    height:28px;
    margin:0 auto;
    border:3px solid #e3eabb;
    border-top-color:#47613b;
    border-radius:50%;
    animation:otSpin .8s linear infinite;
}
@keyframes otSpin{
    to{ transform:rotate(360deg); }
}
@keyframes fadeUp{
    from{ transform:translateY(20px); opacity:0; }
    to{ transform:translateY(0); opacity:1; }
}
.ot-fadeup{
    animation:fadeUp .25s ease;
}

body{
    background:
        radial-gradient(circle at 12% -8%, #eef4e6 0%, rgba(238,244,230,0) 45%),
        radial-gradient(circle at 100% 0%, #fcf5dd 0%, rgba(252,245,221,0) 40%),
        #fef8f3;
    min-height:100vh;
}
.ot-card{
    border-color:#d7e0b4;
    border-radius:22px;
    box-shadow:0 10px 26px rgba(36,81,60,.10);
}
.ot-trip-card{
    padding:0;
    overflow:hidden;
    background:#fff;
}
.ot-trip-hero{
    background:#f8faf7;
    padding:13px 14px 12px;
}
.ot-hero-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}
.ot-hero-eyebrow{
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-size:10px;
    font-weight:700;
    letter-spacing:.6px;
    text-transform:uppercase;
    color:#7b8a74;
}
.ot-hero-eyebrow i{
    font-size:12px;
    color:#83966a;
}
.ot-hero-badge{
    display:inline-flex;
    align-items:center;
    gap:5px;
    background:#eef4e6;
    border:1px solid #d5e4c6;
    color:#2f6b43;
    padding:4px 10px;
    border-radius:999px;
    font-size:10px;
    font-weight:700;
    white-space:nowrap;
    flex-shrink:0;
}
.ot-hero-badge::before{
    content:'';
    width:6px;
    height:6px;
    border-radius:50%;
    background:#5aa943;
    flex-shrink:0;
}
.ot-hero-route{
    font-size:18px;
    font-weight:800;
    color:#24513c;
    line-height:1.3;
    margin-top:6px;
    letter-spacing:.2px;
    overflow-wrap:anywhere;
}
.ot-hero-meta{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-top:10px;
}
.ot-hero-chip{
    display:inline-flex;
    align-items:center;
    gap:5px;
    min-width:0;
    max-width:100%;
    background:#fff;
    border:1px solid #d5e4c6;
    border-radius:999px;
    padding:5px 10px;
    font-size:10.5px;
    font-weight:700;
    color:#2f6b43;
}
.ot-hero-chip i{
    font-size:11px;
    color:#5d8550;
    flex-shrink:0;
}
.ot-hero-chip-value{
    min-width:0;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.ot-choice-section{
    padding:16px 16px 18px;
    border-top:1px dashed #e3eabb;
    background:#fdfbf3;
}
.ot-choice-section-title{
    font-size:15px;
    font-weight:700;
    color:#24513c;
}
.ot-choice-section-sub{
    font-size:11.5px;
    color:#7b8a74;
    margin-top:3px;
}
.ot-choice{
    background:#fff;
    border-color:#d7e0b4;
    border-radius:18px;
    box-shadow:0 4px 12px rgba(36,81,60,.06);
}
.ot-choice.ot-choice-primary{
    border-color:#a9bd7f;
    background:linear-gradient(135deg,#ffffff,#f4f8ec);
    box-shadow:0 8px 18px rgba(71,97,59,.14);
}
.ot-choice.ot-choice-primary .ot-choice-icon{
    background:linear-gradient(135deg,#47613b,#6a8f5a);
    border-color:transparent;
    box-shadow:0 4px 10px rgba(71,97,59,.25);
}
.ot-choice.ot-choice-primary .ot-choice-icon i{
    color:#fff;
}
.ot-choice-tag{
    font-size:9px;
    font-weight:700;
    letter-spacing:.6px;
    text-transform:uppercase;
    background:#eef4e6;
    border:1px solid #d5e4c6;
    color:#2f6b43;
    border-radius:999px;
    padding:3px 8px;
    flex-shrink:0;
}
.ot-primary-btn{
    border-radius:14px;
    box-shadow:0 6px 16px rgba(71,97,59,.28);
    transition:transform .15s ease, filter .15s ease;
}
.ot-primary-btn:active{
    transform:translateY(1px) scale(.99);
    filter:brightness(.96);
}
.ot-alert-icon{
    box-shadow:0 6px 16px rgba(183,121,31,.18);
}
.ot-skeleton{
    display:flex;
    flex-direction:column;
    gap:9px;
    margin-top:18px;
}
.ot-skeleton-line{
    height:11px;
    border-radius:999px;
    background:linear-gradient(90deg,#eef2e4 25%,#f7faf1 37%,#eef2e4 63%);
    background-size:400% 100%;
    animation:otShimmer 1.4s ease infinite;
}
@keyframes otShimmer{
    0%{ background-position:100% 50%; }
    100%{ background-position:0 50%; }
}

body.ot-landing{
    background:#fef8f3;
    min-height:100vh;
    overflow:hidden;
}
.ot-overlay{
    position:fixed;
    inset:0;
    z-index:9000;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:10px;
    background:
        radial-gradient(circle at 12% -8%, #eef4e6 0%, rgba(238,244,230,0) 45%),
        radial-gradient(circle at 100% 0%, #fcf5dd 0%, rgba(252,245,221,0) 40%),
        #fef8f3;
}
.ot-sheet{
    width:100%;
    max-width:640px;
    max-height:calc(100vh - 20px);
    max-height:calc(100dvh - 20px);
    display:flex;
    flex-direction:column;
    background:#fef8f3;
    border:2px solid #c6d48c;
    border-radius:22px;
    overflow:hidden;
    box-shadow:0 18px 44px rgba(36,81,60,.22);
    animation:fadeUp .3s ease;
}
.ot-sheet-body{
    flex:1;
    min-height:0;
    overflow-y:auto;
    -webkit-overflow-scrolling:touch;
    padding:16px;
}

.ot-brand{
    position:relative;
    flex-shrink:0;
    background:
        linear-gradient(rgba(254,248,243,.88), rgba(238,244,230,.93)),
        url('/assets/images/passenger/header-banner.png') center/cover no-repeat,
        #fcf8ed;
    border-bottom:1px solid #e3eabb;
    padding:14px 16px 12px;
}
.ot-brand-row{
    display:flex;
    align-items:center;
    gap:12px;
}
.ot-brand-mark{
    height:38px;
    width:auto;
    flex-shrink:0;
}
.ot-brand-text{
    min-width:0;
    flex:1;
}
.ot-brand-name{
    font-size:21px;
    font-weight:700;
    color:#24513c;
    line-height:1.15;
}
.ot-brand-org{
    font-size:10px;
    color:#6c757d;
    margin-top:3px;
    line-height:1.4;
}
.ot-brand-org strong{
    color:#47613b;
    font-weight:700;
}
.ot-brand-logo{
    height:42px;
    width:auto;
    flex-shrink:0;
}
.ot-brand-welcome{
    display:flex;
    align-items:center;
    gap:7px;
    margin-top:10px;
    padding:8px 12px;
    background:#fff;
    border:1px solid #e3eabb;
    border-radius:12px;
    font-size:11px;
    font-weight:600;
    color:#4b6b3f;
    line-height:1.4;
}
.ot-brand-welcome i{
    color:#5aa943;
    font-size:12px;
    flex-shrink:0;
}

.ot-choice-section{
    padding:15px 16px 17px;
}
.ot-choice-section-title{
    font-size:15px;
}
.ot-choice-section-sub{
    font-size:11px;
    margin-top:3px;
}
.ot-choice{
    gap:12px;
    padding:13px 14px;
    min-height:70px;
    margin-top:10px;
    border-radius:16px;
}
.ot-choice-icon{
    width:40px;
    height:40px;
    border-radius:13px;
}
.ot-choice-icon i{
    font-size:18px;
}
.ot-choice-body{
    display:flex;
    flex-direction:column;
    min-width:0;
    flex:1;
}
.ot-choice-head{
    display:flex;
    align-items:center;
    gap:7px;
    min-width:0;
}
.ot-choice-title{
    font-size:14px;
    line-height:1.2;
}
.ot-choice-desc{
    font-size:11px;
    line-height:1.4;
    margin-top:3px;
}
.ot-choice-tag{
    font-size:9px;
    padding:3px 8px;
    letter-spacing:.5px;
}
.ot-choice-arrow{
    font-size:15px;
}

#otLoading{
    padding:30px 20px !important;
}
#otLoading .ot-spinner{
    width:26px;
    height:26px;
}
#otLoading .ot-skeleton{
    margin-top:16px;
    gap:9px;
}
#otError{
    padding:26px 20px !important;
}
.ot-alert-icon{
    width:52px;
    height:52px;
    border-radius:15px;
}
.ot-alert-icon i{
    font-size:23px;
}
.ot-alert-title{
    font-size:18px;
    margin-top:12px;
}
.ot-alert-text{
    font-size:12.5px;
    margin-top:6px;
}
.ot-secondary-link{
    margin-top:12px;
    font-size:11.5px;
}
</style>
</head>

<body class="ot-landing">

<div class="ot-overlay">
<div class="ot-sheet">

    <div class="ot-brand">
        <div class="ot-brand-row">
            <img class="ot-brand-mark" src="/assets/images/passenger/buslogo.png" alt="R-Connect logo">
            <div class="ot-brand-text">
                <div class="ot-brand-name">R-Connect</div>
                <div class="ot-brand-org">
                    <?= htmlspecialchars(passenger_t('app.tagline', 'Transportation Management System for'), ENT_QUOTES, 'UTF-8') ?> <strong>RPMVFMPC</strong>
                </div>
            </div>
            <img class="ot-brand-logo" src="/assets/images/passenger/logoR.png" alt="RPMVFMPC logo">
        </div>
        <div class="ot-brand-welcome">
            <i class="bi bi-hand-thumbs-up-fill"></i>
            <span><?= htmlspecialchars(passenger_t('ot.welcome', 'Welcome! Check your trip below, then choose how to continue.'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <div class="ot-sheet-body">

    <div id="otLoading" class="ot-card" style="text-align:center;padding:34px 22px;">
        <div class="ot-spinner"></div>
        <div style="font-size:13px;color:#7b8a74;margin-top:14px;font-weight:600;">
            <?= htmlspecialchars(passenger_t('ot.checking_trip', 'Checking the bus trip...'), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="ot-skeleton">
            <span class="ot-skeleton-line" style="width:70%;"></span>
            <span class="ot-skeleton-line" style="width:92%;"></span>
            <span class="ot-skeleton-line" style="width:55%;"></span>
        </div>
    </div>

    <div id="otError" class="ot-card ot-fadeup" style="display:none;padding:26px 20px;">
        <div id="otErrorIcon" class="ot-alert-icon">
            <i id="otErrorIconGlyph" class="bi bi-exclamation-triangle"></i>
        </div>
        <div id="otErrorTitle" class="ot-alert-title"><?= htmlspecialchars(passenger_t('ot.no_active_trip', 'No Active Trip'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="otErrorText" class="ot-alert-text">
            <?= htmlspecialchars(passenger_t('ot.no_active_trip_body', 'This bus currently has no active trip. Please try again later or contact the driver.'), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <button type="button" id="otRetryBtn" class="ot-primary-btn" style="margin-top:20px;">
            <i class="bi bi-arrow-clockwise"></i> <?= htmlspecialchars(passenger_t('ot.try_again', 'Try Again'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <a class="ot-secondary-link" href="<?= htmlspecialchars($oneTimeLoginUrl, ENT_QUOTES) ?>">
            <?= htmlspecialchars(passenger_t('ot.login_instead', 'Log in instead'), ENT_QUOTES, 'UTF-8') ?>
        </a>
    </div>

    <div id="otTrip" class="ot-card ot-trip-card ot-fadeup" style="display:none;">

        <div class="ot-trip-hero">
            <div class="ot-hero-top">
                <span class="ot-hero-eyebrow"><i class="bi bi-qr-code-scan"></i> <?= htmlspecialchars(passenger_t('ot.current_trip', 'Current Trip'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div id="otTripRoute" class="ot-hero-route">—</div>
            <div class="ot-hero-meta">
                <span class="ot-hero-chip">
                    <i class="bi bi-bus-front-fill"></i> <?= htmlspecialchars(passenger_t('ot.bus', 'Bus'), ENT_QUOTES, 'UTF-8') ?>&nbsp;<span id="otTripBus" class="ot-hero-chip-value">—</span>
                </span>
                <span class="ot-hero-chip">
                    <i class="bi bi-ticket-detailed"></i> <?= htmlspecialchars(passenger_t('ot.trip', 'Trip'), ENT_QUOTES, 'UTF-8') ?>&nbsp;<span id="otTripNo" class="ot-hero-chip-value">—</span>
                </span>
                <span class="ot-hero-chip">
                    <i class="bi bi-person-badge"></i> <span id="otTripDriver" class="ot-hero-chip-value">—</span>
                </span>
            </div>
            <div style="display:flex;flex-direction:row;align-items:center;justify-content:space-between;gap:8px;width:100%;margin-top:10px;">
                <span id="otTripBadge" class="ot-hero-badge">—</span>
                <div style="display:block;">
                    <?php $passengerLangAlign = 'flex-start'; include __DIR__ . "/../layout/language.php"; ?>
                </div>
            </div>
        </div>

        <div class="ot-choice-section">
            <div class="ot-choice-section-title"><?= htmlspecialchars(passenger_t('ot.how_use', 'How will you use R-CONNECT?'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="ot-choice-section-sub"><?= htmlspecialchars(passenger_t('ot.choose_one', 'Choose one to continue.'), ENT_QUOTES, 'UTF-8') ?></div>

            <a class="ot-choice ot-choice-primary" href="<?= htmlspecialchars($oneTimeRegisterUrl, ENT_QUOTES) ?>">
                <span class="ot-choice-icon">
                    <i class="bi bi-lightning-charge-fill"></i>
                </span>
                <span class="ot-choice-body">
                    <span class="ot-choice-head">
                        <span class="ot-choice-title"><?= htmlspecialchars(passenger_t('ot.one_time_user', 'One-Time User'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="ot-choice-tag"><?= htmlspecialchars(passenger_t('ot.fastest', 'Fastest'), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                    <span class="ot-choice-desc">
                        <?= htmlspecialchars(passenger_t('ot.one_time_desc', 'First time or one-time access — continue without creating an account.'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </span>
                <i class="bi bi-chevron-right ot-choice-arrow"></i>
            </a>

            <a class="ot-choice" href="<?= htmlspecialchars($oneTimeLoginUrl, ENT_QUOTES) ?>">
                <span class="ot-choice-icon">
                    <i class="bi bi-person-fill"></i>
                </span>
                <span class="ot-choice-body">
                    <span class="ot-choice-head">
                        <span class="ot-choice-title"><?= htmlspecialchars(passenger_t('ot.regular_user', 'Regular User'), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                    <span class="ot-choice-desc">
                        <?= htmlspecialchars(passenger_t('ot.regular_desc', 'Easy access for regular trips — log in or create an account for faster access next time.'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </span>
                <i class="bi bi-chevron-right ot-choice-arrow"></i>
            </a>

            <?php if (!empty($hasCurrentTripReceipt)): ?>
            <a class="ot-secondary-link" href="/passenger/one-time/register">
                <i class="bi bi-receipt"></i> <?= htmlspecialchars(passenger_t('ot.view_receipt', 'View my payment receipt'), ENT_QUOTES, 'UTF-8') ?>
            </a>
            <?php endif; ?>
        </div>

    </div>

    </div>
</div>
</div>

<script>
const OT_BUS = <?= json_encode($oneTimeBus, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const OT_I18N = <?= json_encode([
    'no_active_trip' => passenger_t('ot.no_active_trip', 'No Active Trip'),
    'no_active_trip_body' => passenger_t('ot.no_active_trip_body', 'This bus currently has no active trip. Please try again later or contact the driver.'),
    'invalid_bus' => passenger_t('ot.invalid_bus', 'Invalid Bus QR'),
    'invalid_bus_body' => passenger_t('ot.invalid_bus_body', 'We could not identify this bus. Please scan the Bus QR again.'),
    'load_failed' => passenger_t('ot.load_failed', 'We could not load the trip. Please check your connection and try again.'),
    'continue_trip' => passenger_t('ot.continue_trip', 'Continue to Existing Trip'),
    'continue_trip_desc' => passenger_t('ot.continue_trip_desc', 'You are already registered on this trip. View your receipt or complete payment.'),
    'registered' => passenger_t('ot.registered', 'Registered'),
    'scan_to_see' => passenger_t('ot.scan_to_see', 'Scan the bus QR to see the current trip'),
    'no_scanned_trip' => passenger_t('ot.no_scanned_trip', 'No scanned trip'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const otLoading = document.getElementById('otLoading');
const otError = document.getElementById('otError');
const otTrip = document.getElementById('otTrip');
const otErrorTitle = document.getElementById('otErrorTitle');
const otErrorText = document.getElementById('otErrorText');
const otErrorIconGlyph = document.getElementById('otErrorIconGlyph');

function showLoading(){
    otLoading.style.display = 'block';
    otError.style.display = 'none';
    otTrip.style.display = 'none';
}

function showTrip(data){
    otLoading.style.display = 'none';
    otError.style.display = 'none';

    document.getElementById('otTripBus').textContent = data.bus?.bus_id || '—';
    document.getElementById('otTripRoute').textContent = data.trip?.direction || data.trip?.route_name || '—';
    document.getElementById('otTripDriver').textContent = data.driver?.driver_name || '—';
    document.getElementById('otTripNo').textContent = data.trip?.trip_number || ('#' + (data.trip?.id ?? '—'));
    document.getElementById('otTripBadge').textContent = data.trip?.status || 'Active';

    if(data.has_existing_trip){
        const oneTimeTitle = document.querySelector('.ot-choice-primary .ot-choice-title');
        const oneTimeDesc = document.querySelector('.ot-choice-primary .ot-choice-desc');
        const oneTimeTag = document.querySelector('.ot-choice-primary .ot-choice-tag');
        if(oneTimeTitle) oneTimeTitle.textContent = OT_I18N.continue_trip;
        if(oneTimeDesc) oneTimeDesc.textContent = OT_I18N.continue_trip_desc;
        if(oneTimeTag) oneTimeTag.textContent = OT_I18N.registered;
    }

    otTrip.style.display = 'block';
}

function showError(code, message){
    otLoading.style.display = 'none';
    otTrip.style.display = 'none';

    var safeMsg = message;
    if (window.PassengerSession && typeof window.PassengerSession.sanitizeErrorMessage === 'function') {
        safeMsg = window.PassengerSession.sanitizeErrorMessage(message, code === 'NO_ACTIVE_TRIP' ? OT_I18N.no_active_trip_body : OT_I18N.invalid_bus_body);
    }

    if(code === 'NO_ACTIVE_TRIP'){
        otErrorIconGlyph.className = 'bi bi-exclamation-triangle';
        otErrorTitle.textContent = OT_I18N.no_active_trip;
        otErrorText.textContent = safeMsg || OT_I18N.no_active_trip_body;
    }else{
        otErrorIconGlyph.className = 'bi bi-qr-code';
        otErrorTitle.textContent = OT_I18N.invalid_bus;
        otErrorText.textContent = safeMsg || OT_I18N.invalid_bus_body;
    }

    otError.style.display = 'block';
}

function showChoicesOnly(){
    /* No scanned trip (PWA reopen, browser history, direct link): the landing
       still must offer the One-Time/Regular choice, so the choice card is
       shown with a clear "scan the QR" trip state instead of an error that
       hides both options. */
    otLoading.style.display = 'none';
    otError.style.display = 'none';

    document.getElementById('otTripBus').textContent = '—';
    document.getElementById('otTripRoute').textContent = OT_I18N.scan_to_see;
    document.getElementById('otTripDriver').textContent = '—';
    document.getElementById('otTripNo').textContent = '—';
    document.getElementById('otTripBadge').textContent = OT_I18N.no_scanned_trip;

    otTrip.style.display = 'block';
}

async function loadTrip(){
    showLoading();

    if(!OT_BUS){
        showChoicesOnly();
        return;
    }

    try{
        const res = await fetch('/passenger/one-time/trip', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({bus: OT_BUS})
        });
        const data = await res.json();

        if(data && data.success && data.data){
            showTrip(data.data);
        }else{
            showError(data?.code || 'ERROR', data?.message || '');
        }
    }catch(err){
        showError('SERVER_ERROR', OT_I18N.load_failed);
    }
}

document.getElementById('otRetryBtn')?.addEventListener('click', loadTrip);

loadTrip();
</script>

</body>
</html>

<?php

// The controller passes the bus validated by scan.php; the view never reads
// the URL directly.
$oneTimeBus = trim((string) ($bus ?? ""));

$oneTimeLandingUrl = "/passenger/one-time/landing";

$oneTimeResumeTrip = isset($resumeTrip) && is_array($resumeTrip) ? $resumeTrip : null;
$oneTimeResumeTripId = $oneTimeResumeTrip ? (int) ($oneTimeResumeTrip["id"] ?? 0) : 0;
$oneTimeResumeMethod = $oneTimeResumeTrip ? strtolower((string) ($oneTimeResumeTrip["payment_method"] ?? "")) : "";
$oneTimeResumeStatus = $oneTimeResumeTrip ? strtolower((string) ($oneTimeResumeTrip["payment_status"] ?? "")) : "";
$oneTimeResumeDeclaredId = $oneTimeResumeTrip ? trim((string) ($oneTimeResumeTrip["declared_id_number"] ?? "")) : "";
$oneTimeResumeDeclaredStatus = $oneTimeResumeTrip ? strtolower((string) ($oneTimeResumeTrip["declared_id_status"] ?? "")) : "";
$oneTimeResumeName = $oneTimeResumeTrip ? trim((string) ($oneTimeResumeTrip["passenger_name"] ?? "")) : "";
$oneTimeResumeBoarding = $oneTimeResumeTrip ? trim((string) ($oneTimeResumeTrip["boarding_point"] ?? "")) : "";
$oneTimeResumeDestination = $oneTimeResumeTrip ? trim((string) ($oneTimeResumeTrip["destination"] ?? "")) : "";
$oneTimeResumeFare = $oneTimeResumeTrip ? (float) ($oneTimeResumeTrip["fare"] ?? 0) : 0.0;

// The controller rebuilds the companion-aware party total for a resumed
// receipt; the primary row's fare alone would drop the companions.
if (isset($oneTimeResumePartyFare) && (float) $oneTimeResumePartyFare > 0) {
    $oneTimeResumeFare = (float) $oneTimeResumePartyFare;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title>R-Connect — One-Time Boarding</title>
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
    padding-bottom:140px;
}
.ot-wrap{
    width:95%;
    max-width:650px;
    margin:16px auto 0;
}
.ot-card{
    background:#fef8f3;
    border:1.5px solid #c6d48c;
    border-radius:20px;
    padding:16px;
    box-shadow:0 6px 20px rgba(0,0,0,.12);
    margin-bottom:14px;
}
.ot-section-title{
    font-size:16px;
    font-weight:700;
    color:#24513c;
    margin:0;
}
.ot-section-sub{
    font-size:11.5px;
    color:#7b8a74;
    margin-top:3px;
    line-height:1.45;
}
.ot-label{
    display:flex;
    align-items:center;
    gap:6px;
    font-size:13px;
    font-weight:600;
    color:#47613b;
    margin-bottom:6px;
}
.ot-input{
    width:100%;
    box-sizing:border-box;
    padding:12px;
    border:1px solid #c6d48c;
    border-radius:12px;
    font-size:16px;
    min-height:48px;
    background:#fff;
    font-family:'Poppins', sans-serif;
    color:#24513c;
    outline:none;
}
.ot-input:focus{
    border-color:#47613b;
    box-shadow:0 0 0 3px rgba(71,97,59,.12);
}
.ot-error{
    display:none;
    color:#dc3545;
    font-size:12px;
    margin-top:6px;
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
.ot-primary-btn:disabled{
    opacity:.55;
    cursor:not-allowed;
    box-shadow:none;
}
.ot-secondary-btn{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    width:100%;
    box-sizing:border-box;
    min-height:48px;
    border:1.5px solid #c6d48c;
    border-radius:12px;
    background:#fff;
    color:#24513c;
    font-family:'Poppins', sans-serif;
    font-size:14px;
    font-weight:600;
    padding:12px 16px;
    touch-action:manipulation;
    cursor:pointer;
}
.ot-trip-strip{
    display:flex;
    align-items:center;
    gap:12px;
    background:#fcf5dd;
    border:1px solid #c6d48c;
    border-radius:14px;
    padding:12px 14px;
    margin-bottom:14px;
}
.ot-trip-strip-icon{
    width:38px;
    height:38px;
    flex-shrink:0;
    border-radius:12px;
    background:#47613b;
    display:flex;
    align-items:center;
    justify-content:center;
}
.ot-trip-strip-icon i{
    color:#fff;
    font-size:17px;
}
.ot-trip-strip-main{
    min-width:0;
    flex:1;
}
.ot-trip-strip-title{
    font-size:13px;
    font-weight:700;
    color:#24513c;
    line-height:1.3;
}
.ot-trip-strip-sub{
    font-size:11px;
    color:#7b8a74;
    margin-top:2px;
    line-height:1.35;
}
.ot-step-track{
    height:6px;
    background:#e3eabb;
    border-radius:999px;
    margin-top:8px;
    overflow:hidden;
}
.ot-step-bar{
    height:100%;
    width:20%;
    background:linear-gradient(90deg,#47613b,#5d8550);
    border-radius:999px;
    transition:width .25s ease;
}
.discount-card{
    border:1.5px solid #c6d48c;
    border-radius:14px;
    padding:12px 8px;
    text-align:center;
    background:#fcf5dd;
    cursor:pointer;
    transition:.2s;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:6px;
    min-width:0;
}
.discount-card i{
    width:42px;
    height:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:10px;
    background:#fff;
    border:1.5px solid #c6d48c;
    color:#47613b;
    font-size:18px;
}
.discount-card span{
    font-size:12px;
    line-height:1.25;
}
.discount-card.active{
    background:#4b794d;
    color:#fff;
    border-color:#4b794d;
}
.discount-card.active i{
    background:#4b794d;
    border-color:#fff;
    color:#fff;
}
.ot-stop{
    display:flex;
    align-items:center;
    gap:12px;
    width:100%;
    box-sizing:border-box;
    background:#fff;
    border:1.5px solid #c6d48c;
    border-radius:14px;
    padding:12px 14px;
    min-height:52px;
    margin-top:10px;
    cursor:pointer;
    transition:.2s;
    -webkit-tap-highlight-color:transparent;
    text-align:left;
}
.ot-stop-index{
    width:26px;
    height:26px;
    flex-shrink:0;
    border-radius:50%;
    background:#eef4e6;
    border:1px solid #d5e4c6;
    color:#47613b;
    font-size:11px;
    font-weight:700;
    display:flex;
    align-items:center;
    justify-content:center;
}
.ot-stop-name{
    flex:1;
    min-width:0;
    font-size:14px;
    font-weight:600;
    color:#24513c;
    line-height:1.3;
    overflow-wrap:anywhere;
}
.ot-stop-check{
    font-size:16px;
    color:#47613b;
    opacity:0;
    flex-shrink:0;
}
.ot-stop.active{
    background:#4b794d;
    border-color:#4b794d;
}
.ot-stop.active .ot-stop-name{
    color:#fff;
}
.ot-stop.active .ot-stop-index{
    background:rgba(255,255,255,.2);
    border-color:rgba(255,255,255,.4);
    color:#fff;
}
.ot-stop.active .ot-stop-check{
    opacity:1;
    color:#fff;
}
.payment-option{
    flex:1 1 0%;
    min-width:0;
    border:1px solid #c6d48c;
    background:#fff;
    color:#24513c;
    border-radius:14px;
    padding:10px 6px;
    cursor:pointer;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:6px;
    font-size:13px;
    font-weight:600;
    min-height:52px;
    transition:.2s;
    font-family:'Poppins', sans-serif;
}
.payment-option i{
    font-size:19px;
}
.payment-option.active{
    background:#47613b;
    color:#fff;
    border-color:#47613b;
    box-shadow:0 3px 10px rgba(71,97,59,.22);
}
.payment-option:active{
    transform:translateY(1px);
}
.ot-summary-row{
    display:flex;
    align-items:flex-start;
    gap:12px;
    padding:11px 0;
    border-top:1px solid #eef2ec;
}
.ot-summary-row:first-child{
    border-top:none;
}
.ot-summary-row i{
    font-size:13px;
    color:#83966a;
    flex-shrink:0;
    width:20px;
    text-align:center;
    margin-top:3px;
}
.ot-summary-label{
    font-size:11px;
    color:#96a394;
    flex:0 0 78px;
    padding-top:3px;
}
.ot-summary-value{
    flex:1;
    min-width:0;
    text-align:right;
    font-size:14px;
    font-weight:700;
    color:#24513c;
    line-height:1.35;
    overflow-wrap:anywhere;
    word-break:break-word;
}
.ot-fare-card{
    background:linear-gradient(135deg,#47613b,#5b774d);
    color:#fff;
    border-radius:14px;
    padding:12px 14px;
    display:flex;
    flex-wrap:wrap;
    gap:4px 12px;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 4px 12px rgba(71,97,59,.15);
    min-height:48px;
}
.ot-fare-label{
    font-size:11px;
    opacity:.85;
    text-transform:uppercase;
    letter-spacing:1px;
    white-space:nowrap;
}
.ot-fare-value{
    font-size:16px;
    font-weight:700;
    line-height:1.25;
    min-width:0;
    flex:1 1 auto;
    text-align:right;
    overflow-wrap:anywhere;
}
.ot-success-icon{
    width:64px;
    height:64px;
    margin:0 auto;
    border-radius:50%;
    background:linear-gradient(135deg,#47613b,#6a8f5a);
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 4px 12px rgba(71,97,59,.3);
}
.ot-success-icon i{
    font-size:30px;
    color:#fff;
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
.ot-overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.4);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:9999;
    padding:16px;
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
.ot-section-head{
    display:flex;
    align-items:center;
    gap:12px;
}
.ot-section-head > div:last-child{
    min-width:0;
}
.ot-section-badge{
    width:44px;
    height:44px;
    flex-shrink:0;
    border-radius:15px;
    background:linear-gradient(135deg,#47613b,#6a8f5a);
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 5px 12px rgba(71,97,59,.25);
}
.ot-section-badge i{
    color:#fff;
    font-size:19px;
}
.ot-hint{
    display:flex;
    align-items:flex-start;
    gap:7px;
    font-size:11px;
    color:#8a9a86;
    line-height:1.45;
    margin-top:10px;
}
.ot-hint i{
    color:#83966a;
    font-size:12px;
    margin-top:1px;
    flex-shrink:0;
}
.ot-agreement-text{
    margin-top:14px;
    max-height:min(62vh, 460px);
    overflow-y:auto;
    -webkit-overflow-scrolling:touch;
    overscroll-behavior:contain;
    border:1px solid #e3eabb;
    border-radius:12px;
    background:#fbfdf8;
    padding:12px 14px;
}
.ot-agreement-text p{
    margin:0 0 10px;
    font-size:12px;
    color:#55634f;
    line-height:1.55;
}
.ot-agreement-text p:last-child{
    margin-bottom:0;
}

.ot-field{
    display:flex;
    align-items:center;
    border:1.5px solid #d7e0b4;
    border-radius:14px;
    background:#fff;
    overflow:hidden;
    transition:border-color .2s, box-shadow .2s;
}
.ot-field:focus-within{
    border-color:#47613b;
    box-shadow:0 0 0 3px rgba(71,97,59,.12);
}
.ot-field-icon{
    flex:0 0 50px;
    height:52px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#faf9e5;
    border-right:1.5px solid #ece7c6;
    color:#47613b;
    font-size:15px;
}
.ot-input-plain{
    border:none !important;
    box-shadow:none !important;
    background:transparent !important;
    border-radius:0 !important;
}
.ot-input-plain:focus{
    border:none;
    box-shadow:none;
}

.discount-card{
    position:relative;
    padding:15px 8px 13px;
    background:#fff;
    border-color:#d7e0b4;
}
.discount-card span{
    font-weight:600;
    color:#24513c;
}
.discount-card.active{
    background:linear-gradient(135deg,#47613b,#5d8550);
    border-color:#47613b;
    box-shadow:0 8px 18px rgba(71,97,59,.28);
    transform:translateY(-1px);
}
.discount-card.active span{
    color:#fff;
}
.discount-card i.dc-check{
    position:absolute;
    top:9px;
    right:9px;
    width:auto;
    height:auto;
    display:block;
    background:none;
    border:none;
    border-radius:0;
    font-size:13px;
    color:#fff;
    opacity:0;
    transform:scale(.5);
    transition:.2s;
}
.discount-card.active i.dc-check{
    opacity:1;
    transform:scale(1);
}

.ot-trip-strip{
    background:linear-gradient(135deg,#fdf8e3,#eef4e6);
    border:1.5px solid #d7e0b4;
    border-radius:18px;
    box-shadow:0 6px 16px rgba(36,81,60,.08);
}
.ot-trip-strip-icon{
    width:42px;
    height:42px;
    border-radius:14px;
    background:linear-gradient(135deg,#47613b,#6a8f5a);
    box-shadow:0 4px 10px rgba(71,97,59,.25);
}
.ot-trip-strip-title{
    font-size:14px;
}
#otStripBadge{
    display:inline-flex;
    align-items:center;
    gap:5px;
    background:#fff;
    color:#2f6b43;
    border:1px solid #d5e4c6;
    padding:5px 11px;
    border-radius:999px;
    font-size:10.5px;
    font-weight:700;
    white-space:nowrap;
    flex-shrink:0;
    box-shadow:0 2px 6px rgba(36,81,60,.06);
}
#otStripBadge::before{
    content:'';
    width:6px;
    height:6px;
    border-radius:50%;
    background:#5aa943;
    flex-shrink:0;
}

#otStepper{
    padding:14px 16px 12px;
}
.ot-step-meta{
    display:flex;
    justify-content:space-between;
    gap:10px;
    font-size:11px;
    color:#8a9a86;
    font-weight:600;
}
.ot-step-name{
    color:#47613b;
    font-weight:700;
}
.ot-step-dots{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-top:12px;
    padding:0 4px;
}
.ot-dot{
    width:24px;
    height:24px;
    border-radius:50%;
    background:#f1f4e8;
    border:2px solid #d7e0b4;
    color:#96a394;
    font-size:10px;
    font-weight:700;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:.25s;
}
.ot-dot.done{
    background:#47613b;
    border-color:#47613b;
    color:#fff;
}
.ot-dot.current{
    background:#fff;
    border-color:#47613b;
    color:#47613b;
    box-shadow:0 0 0 4px rgba(71,97,59,.14);
}
.ot-step-track{
    height:5px;
    background:#eef2e4;
}
.ot-step-bar{
    background:linear-gradient(90deg,#47613b,#8aa36a);
}

#otBoardingList{
    position:relative;
}
#otBoardingList .ot-stop{
    position:relative;
    border:none;
    background:transparent;
    border-radius:14px;
    margin-top:0;
    padding:9px 10px 9px 4px;
    min-height:56px;
}
#otBoardingList .ot-stop::before{
    content:'';
    position:absolute;
    left:18px;
    top:0;
    bottom:0;
    width:2px;
    background:#e3eabb;
}
#otBoardingList .ot-stop:first-child::before{
    top:50%;
}
#otBoardingList .ot-stop:last-child::before{
    bottom:50%;
}
#otBoardingList .ot-stop-index{
    position:relative;
    z-index:2;
    width:30px;
    height:30px;
    background:#fff;
    border:2px solid #c6d48c;
    transition:.2s;
}
#otBoardingList .ot-stop.active{
    background:#f1f6e8;
    box-shadow:inset 0 0 0 1.5px #47613b;
}
#otBoardingList .ot-stop.active .ot-stop-name{
    color:#24513c;
}
#otBoardingList .ot-stop.active .ot-stop-index{
    background:#47613b;
    border-color:#47613b;
    color:#fff;
}
#otBoardingList .ot-stop.active .ot-stop-check{
    color:#47613b;
}
#otBoardingList .ot-stop:active{
    background:#f7faf1;
}

#otBoardingPill{
    margin-top:16px;
    background:#eef4e6;
    border:1.5px solid #d5e4c6;
    border-radius:14px;
    padding:10px 12px;
}
.ot-pill-label{
    display:block;
    font-size:9.5px;
    font-weight:700;
    letter-spacing:.8px;
    text-transform:uppercase;
    color:#7b8a74;
    margin-bottom:2px;
}

.ot-fare-card{
    background:linear-gradient(135deg,#3d5533,#5d8550);
    border-radius:18px;
    padding:16px;
    display:block;
    box-shadow:0 10px 22px rgba(61,85,51,.28);
}
.ot-fare-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}
.ot-fare-label{
    display:flex;
    align-items:center;
    gap:6px;
    font-size:11px;
    opacity:.9;
}
.ot-fare-chip{
    font-size:10px;
    font-weight:700;
    letter-spacing:.5px;
    text-transform:uppercase;
    background:rgba(255,255,255,.16);
    border:1px solid rgba(255,255,255,.25);
    border-radius:999px;
    padding:4px 10px;
    white-space:nowrap;
}
.ot-fare-value{
    font-size:26px;
    font-weight:800;
    text-align:left;
    margin-top:6px;
    letter-spacing:.3px;
}
.ot-fare-note{
    font-size:10.5px;
    opacity:.8;
    margin-top:6px;
    line-height:1.4;
}

#otPaymentOptions{
    gap:10px !important;
    flex-direction:column;
    flex-wrap:nowrap;
}
.payment-option{
    flex-direction:row;
    justify-content:flex-start;
    align-items:center;
    text-align:left;
    gap:12px;
    padding:13px 14px;
    border-radius:16px;
    border:1.5px solid #d7e0b4;
    min-height:66px;
    width:100%;
}
.payment-option .po-icon{
    width:42px;
    height:42px;
    flex-shrink:0;
    border-radius:13px;
    background:#f1f6e8;
    border:1px solid #d5e4c6;
    display:flex;
    align-items:center;
    justify-content:center;
}
.payment-option .po-icon i{
    font-size:18px;
    color:#47613b;
}
.payment-option .po-text{
    min-width:0;
    flex:1;
}
.payment-option .po-title{
    display:block;
    font-size:14px;
    font-weight:700;
    color:#24513c;
    line-height:1.2;
}
.payment-option .po-desc{
    display:block;
    font-size:10.5px;
    color:#8a9a86;
    font-weight:500;
    margin-top:2px;
    line-height:1.35;
}
.payment-option .po-check{
    font-size:17px !important;
    margin-left:auto;
    color:#47613b;
    opacity:0;
    flex-shrink:0;
    transition:.2s;
}
.payment-option.active{
    background:linear-gradient(135deg,#47613b,#5d8550);
    border-color:#47613b;
    box-shadow:0 8px 18px rgba(71,97,59,.28);
}
.payment-option.active .po-icon{
    background:rgba(255,255,255,.16);
    border-color:rgba(255,255,255,.35);
}
.payment-option.active .po-icon i{
    color:#fff;
}
.payment-option.active .po-title{
    color:#fff;
}
.payment-option.active .po-desc{
    color:rgba(255,255,255,.82);
}
.payment-option.active .po-check{
    opacity:1;
    color:#fff;
}

.ot-summary-card{
    background:#fff;
    border:1.5px solid #eef2ec;
    border-radius:18px;
    overflow:hidden;
    margin-top:14px;
}
.ot-summary-route{
    display:flex;
    align-items:center;
    gap:8px;
    background:linear-gradient(135deg,#fdf8e3,#eef4e6);
    border-bottom:1px dashed #d7e0b4;
    padding:12px 14px;
}
.ot-summary-route i{
    color:#47613b;
    font-size:14px;
}
.ot-summary-route-text{
    flex:1;
    min-width:0;
    font-size:13px;
    font-weight:700;
    color:#24513c;
    line-height:1.3;
    overflow-wrap:anywhere;
}
.ot-summary-bus{
    font-size:10.5px;
    font-weight:700;
    background:#fff;
    border:1px solid #d5e4c6;
    border-radius:999px;
    padding:4px 10px;
    color:#2f6b43;
    white-space:nowrap;
    flex-shrink:0;
}
.ot-summary-body{
    padding:2px 14px;
}
.ot-summary-body .ot-summary-row{
    padding:12px 0;
}
.ot-summary-footer{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    background:#f8faf7;
    border-top:1px dashed #d7e0b4;
    padding:12px 14px;
}
.ot-summary-footer-label{
    display:block;
    font-size:10px;
    font-weight:700;
    letter-spacing:.6px;
    text-transform:uppercase;
    color:#96a394;
}
.ot-summary-footer-value{
    display:block;
    font-size:18px;
    font-weight:800;
    color:#24513c;
    line-height:1.2;
}
.ot-summary-footer-value.ot-summary-footer-pay{
    font-size:13px;
    padding-top:4px;
}

.ot-primary-btn{
    border-radius:14px;
    box-shadow:0 6px 16px rgba(71,97,59,.28);
    transition:transform .15s ease, box-shadow .15s ease, filter .15s ease;
}
.ot-primary-btn:active{
    transform:translateY(1px) scale(.99);
    filter:brightness(.96);
}
.ot-secondary-btn{
    border-radius:14px;
    background:#fff;
    transition:transform .15s ease, background .15s ease;
}
.ot-secondary-btn:active{
    transform:translateY(1px);
    background:#f7faf1;
}
.ot-btn-row{
    display:flex;
    gap:10px;
    margin-top:18px;
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

.ot-receipt-wrap{
    background:#fff;
    border:1.5px solid #eef2ec;
    border-radius:18px;
    padding:4px 14px;
    margin-top:16px;
    text-align:left;
}
.ot-receipt-head{
    display:flex;
    align-items:center;
    gap:8px;
    padding:12px 0;
    border-bottom:1px dashed #e3eabb;
    font-size:11px;
    font-weight:700;
    letter-spacing:.6px;
    text-transform:uppercase;
    color:#7b8a74;
}
.ot-receipt-head i{
    color:#47613b;
    font-size:13px;
}

#otError > div:first-child{
    box-shadow:0 6px 16px rgba(183,121,31,.18);
}

#otMethodSwitch{
    margin-top:16px;
    text-align:left;
}

.ot-summary-value-flex{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
    row-gap:4px;
}
.ot-pay-chip{
    display:inline-flex;
    align-items:center;
    padding:3px 10px;
    border-radius:999px;
    font-size:9.5px;
    font-weight:800;
    letter-spacing:.6px;
    text-transform:uppercase;
    white-space:nowrap;
    flex-shrink:0;
}
.ot-pay-chip.pending{
    background:#fff4d6;
    border:1px solid #eecb6f;
    color:#b7791f;
}
.ot-pay-chip.paid{
    background:#e8f5e9;
    border:1px solid #b7e4c7;
    color:#2e7d32;
}
.ot-pay-chip.rejected{
    background:#fdecea;
    border:1px solid #f0b4a8;
    color:#c0392b;
}
.ot-pay-chip.self-declared{
    background:#eef4e6;
    border:1px solid #d5e4c6;
    color:#2f6b43;
}

.ot-receipt-wrap{
    border:none;
    box-shadow:0 2px 10px rgba(36,81,60,.06);
}

.ot-done-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    margin-top:16px;
    padding:9px 26px;
    min-height:42px;
    border:1.5px solid #d6e0bc;
    border-radius:999px;
    background:#fff;
    color:#47613b;
    font-family:'Poppins', sans-serif;
    font-size:12.5px;
    font-weight:600;
    cursor:pointer;
    touch-action:manipulation;
    transition:transform .15s ease, background .15s ease;
}
.ot-done-btn:active{
    transform:translateY(1px);
    background:#f7faf1;
}
.ot-method-label{
    display:flex;
    align-items:center;
    gap:6px;
    font-size:11px;
    font-weight:700;
    letter-spacing:.5px;
    text-transform:uppercase;
    color:#7b8a74;
    margin-bottom:8px;
}
.ot-method-options{
    display:flex;
    gap:6px;
    background:#f1f4e8;
    border:1px solid #e3eabb;
    border-radius:16px;
    padding:4px;
}
.ot-method-btn{
    flex:1;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    border:none;
    background:transparent;
    border-radius:13px;
    padding:11px 8px;
    min-height:46px;
    font-family:'Poppins', sans-serif;
    font-size:12px;
    font-weight:700;
    color:#7b8a74;
    cursor:pointer;
    transition:.2s;
    -webkit-tap-highlight-color:transparent;
}
.ot-method-btn i{
    font-size:15px;
    color:#83966a;
}
.ot-method-btn.active{
    background:#fff;
    color:#24513c;
    box-shadow:0 4px 12px rgba(36,81,60,.14);
}
.ot-method-btn.active i{
    color:#47613b;
}
.ot-method-btn:disabled{
    opacity:.55;
    cursor:wait;
}

.ot-summary-fare-note{
    display:block;
    font-size:10px;
    font-weight:600;
    color:#5d8550;
    margin-top:2px;
}

.ot-fare-breakdown{
    margin-top:8px;
    padding-top:8px;
    border-top:1px dashed rgba(255,255,255,.28);
    font-size:11px;
    line-height:1.45;
    color:rgba(255,255,255,.92);
}

#otDestinationList .ot-stop{
    border:1.5px solid #d7e0b4;
    background:#fff;
    margin-top:8px;
    padding:11px 12px;
    min-height:54px;
}
#otDestinationList .ot-stop:active{
    background:#f7faf1;
}
#otDestinationList .ot-stop.active{
    background:#f1f6e8;
    border-color:#47613b;
    box-shadow:inset 0 0 0 1px #47613b;
}
#otDestinationList .ot-stop.active .ot-stop-name{
    color:#24513c;
}
#otDestinationList .ot-stop.active .ot-stop-index{
    background:#47613b;
    border-color:#47613b;
    color:#fff;
}
#otDestinationList .ot-stop.active .ot-stop-check{
    color:#47613b;
    opacity:1;
}

#otPaymentOptions{
    display:grid !important;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px !important;
}
#otPaymentOptions .payment-option{
    position:relative;
    flex-direction:column;
    justify-content:flex-start;
    align-items:center;
    text-align:center;
    gap:6px;
    padding:14px 10px 12px;
    min-height:104px;
}
#otPaymentOptions .payment-option .po-text{
    display:flex;
    flex-direction:column;
    align-items:center;
    width:100%;
}
#otPaymentOptions .payment-option .po-desc{
    font-size:10px;
    line-height:1.35;
}
#otPaymentOptions .payment-option .po-check{
    position:absolute;
    top:9px;
    right:9px;
    margin:0;
    font-size:15px !important;
}
</style>
</head>

<body>

<?php include __DIR__ . "/../layout/header.php"; ?>

<div class="ot-wrap">

    <div id="otTripStrip" class="ot-trip-strip" style="display:none;flex-direction:column;align-items:stretch;gap:6px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div class="ot-trip-strip-icon">
                <i class="bi bi-bus-front"></i>
            </div>
            <div class="ot-trip-strip-main">
                <div id="otStripRoute" class="ot-trip-strip-title">—</div>
                <div id="otStripBus" class="ot-trip-strip-sub">—</div>
            </div>
        </div>
        <div style="display:flex;flex-direction:row;align-items:center;justify-content:space-between;gap:8px;width:100%;">
            <span id="otStripBadge">—</span>
            <div style="display:block;">
                <?php $passengerLangAlign = 'flex-start'; include __DIR__ . "/../layout/language.php"; ?>
            </div>
        </div>
    </div>

    <div id="otStepper" class="ot-card" style="display:none;">
        <div class="ot-step-meta">
            <span id="otStepCount"><?= htmlspecialchars(str_replace(':count', '1', passenger_t('ot.step_of', 'Step :count of 5')), ENT_QUOTES, 'UTF-8') ?></span>
            <span id="otStepName" class="ot-step-name"><?= htmlspecialchars(passenger_t('ot.step.details', 'Your details'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="ot-step-dots">
            <span class="ot-dot" data-step="1">1</span>
            <span class="ot-dot" data-step="2">2</span>
            <span class="ot-dot" data-step="3">3</span>
            <span class="ot-dot" data-step="4">4</span>
            <span class="ot-dot" data-step="5">5</span>
        </div>
        <div class="ot-step-track">
            <div id="otStepBar" class="ot-step-bar"></div>
        </div>
    </div>

    <div id="otLoading" class="ot-card" style="text-align:center;padding:34px 22px;">
        <div class="ot-spinner"></div>
        <div style="font-size:13px;color:#7b8a74;margin-top:14px;font-weight:600;">
            <?= htmlspecialchars(passenger_t('ot.preparing', 'Preparing your boarding...'), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="ot-skeleton">
            <span class="ot-skeleton-line" style="width:72%;"></span>
            <span class="ot-skeleton-line" style="width:92%;"></span>
            <span class="ot-skeleton-line" style="width:58%;"></span>
        </div>
    </div>

    <div id="otError" class="ot-card ot-fadeup" style="display:none;padding:26px 20px;text-align:center;">
        <div style="width:58px;height:58px;margin:0 auto;border-radius:18px;display:flex;align-items:center;justify-content:center;background:#fff4d6;border:1.5px solid #eecb6f;">
            <i class="bi bi-exclamation-triangle" style="font-size:25px;color:#b7791f;"></i>
        </div>
        <div id="otErrorTitle" style="font-size:19px;font-weight:700;color:#24513c;margin-top:14px;"><?= htmlspecialchars(passenger_t('ot.no_active_trip', 'No Active Trip'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="otErrorText" style="font-size:13px;color:#6c757d;margin-top:6px;line-height:1.55;">
            <?= htmlspecialchars(passenger_t('ot.no_active_trip_body', 'This bus currently has no active trip. Please try again later or contact the driver.'), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <button type="button" id="otRetryBtn" class="ot-primary-btn" style="margin-top:20px;">
            <i class="bi bi-arrow-clockwise"></i> <?= htmlspecialchars(passenger_t('ot.try_again', 'Try Again'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <a class="ot-secondary-btn" style="margin-top:10px;" href="<?= htmlspecialchars(
            $oneTimeLandingUrl,
            ENT_QUOTES,
        ) ?>">
            <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(passenger_t('ot.back_to_trip', 'Back to trip'), ENT_QUOTES, 'UTF-8') ?>
        </a>
    </div>

    <div id="step-agreement" class="ot-card ot-fadeup" style="display:none;">
        <div class="ot-section-head">
            <div class="ot-section-badge"><i class="bi bi-shield-check"></i></div>
            <div>
                <div class="ot-section-title"><?= htmlspecialchars(passenger_t('ot.agreement_title', 'One-Time Passenger Agreement and Privacy Notice'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="ot-section-sub"><?= htmlspecialchars(passenger_t('ot.agreement_sub', 'Please read and agree before continuing.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <div class="ot-agreement-text">
            <p><?= htmlspecialchars(passenger_t('ot.agreement_p1', 'By continuing, I confirm that the boarding point and destination information I provide are true and accurate. I understand that this information is used to properly record my trip, determine the applicable fare, and support transportation and payment records.'), ENT_QUOTES, 'UTF-8') ?></p>
            <p><?= htmlspecialchars(passenger_t('ot.agreement_p2', 'I understand that the information I provide will be processed only for legitimate and necessary purposes related to the R-CONNECT transportation service, subject to the applicable provisions of the Data Privacy Act of 2012 (Republic Act No. 10173).'), ENT_QUOTES, 'UTF-8') ?></p>
            <p><?= htmlspecialchars(passenger_t('ot.agreement_p3', 'My personal information will be handled with appropriate privacy and security measures and will not be used for unrelated purposes without a lawful basis or applicable consent.'), ENT_QUOTES, 'UTF-8') ?></p>
            <p><?= htmlspecialchars(passenger_t('ot.agreement_p4', 'I understand that providing false or inaccurate boarding or destination information may result in an incorrect fare or inaccurate trip record.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <label style="display:flex;align-items:flex-start;gap:8px;margin-top:12px;font-size:12.5px;color:#47613b;line-height:1.45;cursor:pointer;">
            <input type="checkbox" id="otAgreementCheck" style="margin-top:2px;flex-shrink:0;" disabled>
            <span><?= htmlspecialchars(passenger_t('ot.agreement_check', 'I have read and agree to the One-Time Passenger Agreement and Privacy Notice.'), ENT_QUOTES, 'UTF-8') ?></span>
        </label>
        <div id="otAgreementScrollHint" class="ot-hint" style="margin-top:8px;">
            <i class="bi bi-arrow-down-circle"></i>
            <span><?= htmlspecialchars(passenger_t('ot.agreement_scroll_hint', 'Scroll to the end of the agreement to enable the checkbox.'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div id="otAgreementError" class="ot-error" style="display:none;"><?= htmlspecialchars(passenger_t('ot.agreement_error', 'Please tick the agreement to continue.'), ENT_QUOTES, 'UTF-8') ?></div>

        <button type="button" id="otAgreementNext" class="ot-primary-btn" style="margin-top:16px;" disabled>
            <?= htmlspecialchars(passenger_t('ot.continue', 'Continue'), ENT_QUOTES, 'UTF-8') ?> <i class="bi bi-arrow-right"></i>
        </button>
    </div>

    <div id="step-details" class="ot-card ot-fadeup" style="display:none;">
        <div class="ot-section-head">
            <div class="ot-section-badge"><i class="bi bi-person-vcard"></i></div>
            <div>
                <div class="ot-section-title"><?= htmlspecialchars(passenger_t('ot.details_title', 'Your details'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="ot-section-sub"><?= htmlspecialchars(passenger_t('ot.details_sub', 'No account needed — just enough to board.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <div style="margin-top:18px;">
            <label class="ot-label" for="otFullName">
                <i class="bi bi-person-fill"></i> <?= htmlspecialchars(passenger_t('ot.full_name', 'Full Name'), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <div class="ot-field">
                <span class="ot-field-icon"><i class="bi bi-person-fill"></i></span>
                <input type="text" id="otFullName" class="ot-input ot-input-plain" placeholder="Juan Dela Cruz" autocomplete="name" minlength="2" maxlength="100">
            </div>
            <div id="otNameError" class="ot-error"><?= htmlspecialchars(passenger_t('ot.full_name_error', 'Please enter your full name.'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div style="margin-top:16px;">
            <label class="ot-label">
                <i class="bi bi-tag-fill"></i> <?= htmlspecialchars(passenger_t('ot.passenger_type', 'Passenger Type'), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:8px;">
                <label class="discount-card active">
                    <input type="radio" name="otPassengerType" value="regular" hidden checked>
                    <i class="bi bi-person"></i>
                    <span><?= htmlspecialchars(passenger_t('ot.type.regular', 'Regular'), ENT_QUOTES, 'UTF-8') ?></span>
                    <i class="bi bi-check-circle-fill dc-check"></i>
                </label>
                <label class="discount-card">
                    <input type="radio" name="otPassengerType" value="student" hidden>
                    <i class="bi bi-mortarboard-fill"></i>
                    <span><?= htmlspecialchars(passenger_t('ot.type.student', 'Student'), ENT_QUOTES, 'UTF-8') ?></span>
                    <i class="bi bi-check-circle-fill dc-check"></i>
                </label>
                <label class="discount-card">
                    <input type="radio" name="otPassengerType" value="senior" hidden>
                    <i class="bi bi-person-badge-fill"></i>
                    <span><?= htmlspecialchars(passenger_t('ot.type.senior', 'Senior Citizen'), ENT_QUOTES, 'UTF-8') ?></span>
                    <i class="bi bi-check-circle-fill dc-check"></i>
                </label>
                <label class="discount-card">
                    <input type="radio" name="otPassengerType" value="pwd" hidden>
                    <i class="bi bi-universal-access"></i>
                    <span><?= htmlspecialchars(passenger_t('ot.type.pwd', 'PWD'), ENT_QUOTES, 'UTF-8') ?></span>
                    <i class="bi bi-check-circle-fill dc-check"></i>
                </label>
                <label class="discount-card">
                    <input type="radio" name="otPassengerType" value="special" hidden>
                    <i class="bi bi-heart-pulse-fill"></i>
                    <span><?= htmlspecialchars(passenger_t('ot.type.special', 'Teacher / Nurse'), ENT_QUOTES, 'UTF-8') ?></span>
                    <i class="bi bi-check-circle-fill dc-check"></i>
                </label>
            </div>
            <div id="otTypeError" class="ot-error"><?= htmlspecialchars(passenger_t('ot.type_error', 'Please select a passenger type.'), ENT_QUOTES, 'UTF-8') ?></div>

            <div id="otIdWrap" style="display:none;margin-top:14px;">
                <label class="ot-label" for="otDeclaredId">
                    <i class="bi bi-card-text"></i> <span id="otIdLabel"><?= htmlspecialchars(passenger_t('ot.id.student', 'Student ID Number'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <div class="ot-field">
                    <span class="ot-field-icon"><i class="bi bi-card-text"></i></span>
                    <input type="text" id="otDeclaredId" class="ot-input ot-input-plain" placeholder="<?= htmlspecialchars(passenger_t('ot.id_placeholder', 'Enter your ID number'), ENT_QUOTES, 'UTF-8') ?>" minlength="3" maxlength="50" autocomplete="off">
                </div>
                <div id="otIdError" class="ot-error"><?= htmlspecialchars(passenger_t('ot.id_error', 'Please enter your ID number.'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="ot-hint">
                    <i class="bi bi-info-circle-fill"></i>
                    <span><?= htmlspecialchars(passenger_t('ot.id_hint', 'Your ID number is self-declared for accountability and may be checked by an inspector during the trip.'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

        </div>

        <div style="margin-top:16px;">
            <label class="ot-label" for="otContact">
                <i class="bi bi-telephone-fill"></i> <?= htmlspecialchars(passenger_t('ot.contact', 'Contact Number'), ENT_QUOTES, 'UTF-8') ?> <span style="font-weight:400;color:#96a394;"><?= htmlspecialchars(passenger_t('ot.optional', '(optional)'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
            <div class="ot-field">
                <span class="ot-field-icon"><i class="bi bi-telephone-fill"></i></span>
                <input type="tel" id="otContact" class="ot-input ot-input-plain" placeholder="09123456789" autocomplete="tel" inputmode="tel" maxlength="14">
            </div>
        </div>

        <div id="otCompanionBlock" style="margin-top:18px;border-top:1px dashed #dde5d4;padding-top:14px;">
            <label class="ot-label"><i class="bi bi-people-fill"></i> <?= htmlspecialchars(passenger_t('ot.companions', 'Traveling companions'), ENT_QUOTES, 'UTF-8') ?> <span style="font-weight:400;color:#96a394;"><?= htmlspecialchars(passenger_t('ot.optional', '(optional)'), ENT_QUOTES, 'UTF-8') ?></span></label>
            <div class="ot-hint" style="margin-top:6px;">
                <i class="bi bi-info-circle-fill"></i>
                <span><?= htmlspecialchars(passenger_t('ot.companions_hint', 'Add a child or senior riding together from the same boarding point — they get their own ticket and fare.'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div id="otCompanionList" style="margin-top:12px;display:flex;flex-direction:column;gap:8px;"></div>

            <div id="otCompanionEntry" style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">
                <div class="ot-field" style="background:#fff;">
                    <span class="ot-field-icon"><i class="bi bi-person-fill"></i></span>
                    <input type="text" id="otCompanionName" class="ot-input ot-input-plain" placeholder="<?= htmlspecialchars(passenger_t('ot.companion_name', "Companion's full name"), ENT_QUOTES, 'UTF-8') ?>" maxlength="150" autocomplete="off">
                </div>
                <select id="otCompanionType" style="width:100%;box-sizing:border-box;border:1px solid #d3ddcb;border-radius:12px;padding:11px 12px;background:#fff;color:#24513c;font-family:'Poppins',sans-serif;font-size:13px;font-weight:700;outline:none;">
                    <option value="child"><?= htmlspecialchars(passenger_t('ot.type.child', 'Child'), ENT_QUOTES, 'UTF-8') ?> &middot; Discounted</option>
                    <option value="senior"><?= htmlspecialchars(passenger_t('ot.type.senior_short', 'Senior Citizen'), ENT_QUOTES, 'UTF-8') ?> &middot; Discounted</option>
                    <option value="pwd">PWD &middot; Discounted</option>
                    <option value="other"><?= htmlspecialchars(passenger_t('ot.type.regular', 'Regular'), ENT_QUOTES, 'UTF-8') ?> &middot; Standard</option>
                </select>
            </div>

            <div id="otCompanionIdWrap" style="display:none;margin-top:12px;">
                <div class="ot-field" style="background:#fff;">
                    <span class="ot-field-icon"><i class="bi bi-card-text"></i></span>
                    <input type="text" id="otCompanionId" class="ot-input ot-input-plain" placeholder="<?= htmlspecialchars(passenger_t('ot.companion_id_placeholder', 'Senior Citizen ID number'), ENT_QUOTES, 'UTF-8') ?>" maxlength="50" autocomplete="off">
                </div>
                <div class="ot-hint" style="margin-top:6px;">
                    <i class="bi bi-info-circle-fill"></i>
                    <span><?= htmlspecialchars(passenger_t('ot.companion_id_hint', 'Optional — Senior Citizen fare applies from the declared type. The ID number is kept for on-board verification.'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <div style="margin-top:10px;font-size:11px;color:#7b8a74;">Press Enter or finish these fields to save your companion.</div>
            <div id="otCompanionSuccess" role="status" style="display:none;margin-top:8px;font-size:12px;color:#24513c;"></div>
            <div id="otCompanionError" class="ot-error" style="display:none;"></div>
        </div>

        <button type="button" id="otDetailsNext" class="ot-primary-btn" style="margin-top:20px;">
            <?= htmlspecialchars(passenger_t('ot.continue', 'Continue'), ENT_QUOTES, 'UTF-8') ?> <i class="bi bi-arrow-right"></i>
        </button>
    </div>

    <div id="step-boarding" class="ot-card ot-fadeup" style="display:none;">
        <div class="ot-section-head">
            <div class="ot-section-badge"><i class="bi bi-geo-alt"></i></div>
            <div>
                <div class="ot-section-title"><?= htmlspecialchars(passenger_t('ot.boarding_title', 'Select Boarding Point'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="ot-section-sub"><?= htmlspecialchars(passenger_t('ot.boarding_sub', "Based on the bus's current location."), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
        <div id="otBoardingAuto" style="margin-top:14px;padding:14px;border:1px solid #cfe3d2;border-radius:14px;background:#f3faf4;">
            <div class="ot-label" style="margin:0;">
                <i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars(passenger_t('ot.boarding_auto_label', 'Boarding point'), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div id="otBoardingAutoName" style="margin-top:6px;font-size:15px;font-weight:700;color:#24513c;overflow-wrap:anywhere;">—</div>
            <div id="otBoardingAutoMeta" style="margin-top:4px;font-size:11.5px;color:#6b8e6b;">—</div>
        </div>
        <div id="otBoardingError" class="ot-error"><?= htmlspecialchars(passenger_t('ot.boarding_error', 'We could not determine your boarding point. Please retry.'), ENT_QUOTES, 'UTF-8') ?></div>
        <button type="button" id="otBoardingRetry" class="ot-secondary-btn" style="display:none;width:100%;margin-top:10px;">
            <i class="bi bi-arrow-clockwise"></i> <?= htmlspecialchars(passenger_t('ot.boarding_retry', 'Retry'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <div class="ot-btn-row">
            <button type="button" id="otBoardingBack" class="ot-secondary-btn" style="flex:1;">
                <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(passenger_t('ot.back', 'Back'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" id="otBoardingNext" class="ot-primary-btn" style="flex:2;">
                <?= htmlspecialchars(passenger_t('ot.continue', 'Continue'), ENT_QUOTES, 'UTF-8') ?> <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </div>

    <div id="step-destination" class="ot-card ot-fadeup" style="display:none;">
        <div class="ot-section-head">
            <div class="ot-section-badge"><i class="bi bi-flag"></i></div>
            <div>
                <div class="ot-section-title"><?= htmlspecialchars(passenger_t('ot.destination_title', 'Where are you going?'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="ot-section-sub"><?= htmlspecialchars(passenger_t('ot.destination_sub', 'Choose a stop that remains ahead of the bus.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <div id="otBoardingPill">
            <span class="ot-pill-label"><?= htmlspecialchars(passenger_t('ot.boarding_at', 'Boarding at'), ENT_QUOTES, 'UTF-8') ?></span>
            <span style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:#2f6b43;font-weight:700;">
                <i class="bi bi-geo-alt-fill" style="font-size:13px;flex-shrink:0;"></i>
                <span id="otBoardingPillText" style="min-width:0;overflow-wrap:anywhere;">—</span>
            </span>
        </div>

        <div style="margin-top:16px;">
            <label class="ot-label">
                <i class="bi bi-flag-fill"></i> <?= htmlspecialchars(passenger_t('ot.destination', 'Destination'), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <div class="ot-field" style="margin-top:12px;">
                <span class="ot-field-icon"><i class="bi bi-search"></i></span>
                <input type="text" id="otDestinationSearch" class="ot-input ot-input-plain" placeholder="<?= htmlspecialchars(passenger_t('ot.search_destination', 'Search destination...'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
            </div>
            <div id="otDestinationList" style="margin-top:10px;"></div>
            <div id="otDestinationError" class="ot-error"><?= htmlspecialchars(passenger_t('ot.destination_error', 'Please select your destination.'), ENT_QUOTES, 'UTF-8') ?></div>
            <label style="display:flex;align-items:flex-start;gap:8px;margin-top:10px;font-size:12.5px;color:#47613b;line-height:1.45;cursor:pointer;">
                <input type="checkbox" id="otDestinationAgreement" style="margin-top:2px;flex-shrink:0;">
                <span><?= htmlspecialchars(passenger_t('ot.destination_agreement', 'I understand that if I get off before reaching my selected destination, I will still pay the full fare.'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
            <div id="otDestinationAgreementError" class="ot-error" style="display:none;"><?= htmlspecialchars(passenger_t('ot.destination_agreement_error', 'Please tick the agreement to continue.'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div id="otDestinationEmpty" class="ot-error" style="display:none;">
            <?= htmlspecialchars(passenger_t('ot.destination_empty', 'No destinations remain ahead of the bus for this trip.'), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div class="ot-btn-row">
            <button type="button" id="otDestinationBack" class="ot-secondary-btn" style="flex:1;">
                <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(passenger_t('ot.back', 'Back'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" id="otDestinationNext" class="ot-primary-btn" style="flex:2;">
                <?= htmlspecialchars(passenger_t('ot.continue', 'Continue'), ENT_QUOTES, 'UTF-8') ?> <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </div>

    <div id="step-payment" class="ot-card ot-fadeup" style="display:none;">
        <div class="ot-section-head">
            <div class="ot-section-badge"><i class="bi bi-wallet2"></i></div>
            <div>
                <div class="ot-section-title"><?= htmlspecialchars(passenger_t('ot.payment_title', 'How would you like to pay?'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="ot-section-sub"><?= htmlspecialchars(passenger_t('ot.payment_sub', 'Same fare rules as regular passengers.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <div class="ot-fare-card" style="margin-top:16px;">
            <div class="ot-fare-top">
                <span class="ot-fare-label"><i class="bi bi-receipt"></i> <?= htmlspecialchars(passenger_t('ot.total_fare', 'Total Fare'), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="ot-fare-chip"><?= htmlspecialchars(passenger_t('ot.this_trip', 'This trip'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div id="otFareValue" class="ot-fare-value">--</div>
            <div id="otFareBreakdown" class="ot-fare-breakdown" style="display:none;"></div>
            <div id="otPartyPaymentInstructions" role="status" style="margin-top:10px;font-size:12px;color:#5c6f57;line-height:1.5;">Select Cash or Online Payment to pay the total fare.</div>
            <div class="ot-fare-note"><?= htmlspecialchars(passenger_t('ot.fare_note', "Based on this trip's route, your boarding point, destination and passenger type."), ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div id="otPaymentOptions" style="margin-top:16px;">
            <button type="button" class="payment-option" data-method="cash">
                <span class="po-icon"><i class="bi bi-cash-stack"></i></span>
                <span class="po-text">
                    <span class="po-title"><?= htmlspecialchars(passenger_t('ot.pay.cash', 'Cash'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="po-desc"><?= htmlspecialchars(passenger_t('ot.pay.cash_desc', 'Hand your fare to the driver during the trip.'), ENT_QUOTES, 'UTF-8') ?></span>
                </span>
                <i class="bi bi-check-circle-fill po-check"></i>
            </button>
            <button type="button" class="payment-option" data-method="gcash">
                <span class="po-icon"><i class="bi bi-wallet2"></i></span>
                <span class="po-text">
                    <span class="po-title"><?= htmlspecialchars(passenger_t('ot.pay.online', 'Online Payment'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="po-desc"><?= htmlspecialchars(passenger_t('ot.pay.online_desc', 'Pay online with a scannable QR Ph code.'), ENT_QUOTES, 'UTF-8') ?></span>
                </span>
                <i class="bi bi-check-circle-fill po-check"></i>
            </button>
        </div>
        <div id="otPaymentError" class="ot-error"><?= htmlspecialchars(passenger_t('ot.payment_error', 'Please choose how you would like to pay.'), ENT_QUOTES, 'UTF-8') ?></div>

        <div class="ot-btn-row">
            <button type="button" id="otPaymentBack" class="ot-secondary-btn" style="flex:1;">
                <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(passenger_t('ot.back', 'Back'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" id="otPaymentNext" class="ot-primary-btn" style="flex:2;">
                <?= htmlspecialchars(passenger_t('ot.continue', 'Continue'), ENT_QUOTES, 'UTF-8') ?> <i class="bi bi-arrow-right"></i>
            </button>
        </div>
    </div>

    <div id="step-summary" class="ot-card ot-fadeup" style="display:none;">
        <div class="ot-section-head">
            <div class="ot-section-badge"><i class="bi bi-clipboard-check"></i></div>
            <div>
                <div class="ot-section-title"><?= htmlspecialchars(passenger_t('ot.summary_title', 'Trip Summary'), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="ot-section-sub"><?= htmlspecialchars(passenger_t('ot.summary_sub', 'Please check your details before boarding.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <div class="ot-summary-card">
            <div class="ot-summary-route">
                <i class="bi bi-signpost-split-fill"></i>
                <span id="otSummaryRoute" class="ot-summary-route-text">—</span>
                <span id="otSummaryBus" class="ot-summary-bus">—</span>
            </div>

            <div class="ot-summary-body">
                <div class="ot-summary-row">
                    <i class="bi bi-person-fill"></i>
                    <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.passenger', 'Passenger'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span id="otSummaryName" class="ot-summary-value">—</span>
                </div>
                <div class="ot-summary-row">
                    <i class="bi bi-person-vcard"></i>
                    <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.type_label', 'Type'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span id="otSummaryType" class="ot-summary-value">—</span>
                </div>
                <div class="ot-summary-row" id="otSummaryIdRow" style="display:none;">
                    <i class="bi bi-card-text"></i>
                    <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.id_number', 'ID Number'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="ot-summary-value ot-summary-value-flex">
                        <span id="otSummaryId">—</span>
                        <span class="ot-pay-chip self-declared" id="otSummaryIdStatus"><?= htmlspecialchars(passenger_t('ot.self_declared', 'Self-Declared'), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                </div>
                <div class="ot-summary-row">
                    <i class="bi bi-geo-alt"></i>
                    <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.boarding', 'Boarding'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span id="otSummaryBoarding" class="ot-summary-value">—</span>
                </div>
                <div class="ot-summary-row">
                    <i class="bi bi-flag"></i>
                    <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.destination', 'Destination'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span id="otSummaryDestination" class="ot-summary-value">—</span>
                </div>
                <div class="ot-summary-row" id="otSummaryCompanionRow" style="display:none;">
                    <i class="bi bi-people-fill"></i>
                    <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.companions', 'Companions'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span id="otSummaryCompanions" class="ot-summary-value">—</span>
                </div>
            </div>

            <div class="ot-summary-footer">
                <div>
                    <span class="ot-summary-footer-label"><?= htmlspecialchars(passenger_t('ot.fare', 'Fare'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span id="otSummaryFare" class="ot-summary-footer-value">—</span>
                    <span id="otSummaryFareNote" class="ot-summary-fare-note" style="display:none;"></span>
                </div>
                <div style="text-align:right;">
                    <span class="ot-summary-footer-label"><?= htmlspecialchars(passenger_t('ot.payment', 'Payment'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span id="otSummaryPayment" class="ot-summary-footer-value ot-summary-footer-pay">—</span>
                </div>
            </div>
        </div>

        <button type="button" id="otConfirmBtn" class="ot-primary-btn" style="margin-top:20px;">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars(passenger_t('ot.confirm_continue', 'Confirm & Continue'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <button type="button" id="otSummaryBack" class="ot-secondary-btn" style="margin-top:10px;">
            <i class="bi bi-arrow-left"></i> <?= htmlspecialchars(passenger_t('ot.back', 'Back'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

    <div id="step-success" class="ot-card ot-fadeup" style="display:none;text-align:center;">
        <div class="ot-success-icon">
            <i class="bi bi-check-lg"></i>
        </div>
        <div id="otSuccessTitle" style="font-size:20px;font-weight:700;color:#24513c;margin-top:14px;"><?= htmlspecialchars(passenger_t('ot.success_title', "You're on board!"), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="otSuccessText" style="font-size:12.5px;color:#6c757d;margin-top:6px;line-height:1.55;">
            <?= htmlspecialchars(passenger_t('ot.success_text', 'The driver can now see you on this trip.'), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div id="otDoneSummary" class="ot-receipt-wrap">
            <div class="ot-receipt-head">
                <i class="bi bi-ticket-perforated-fill"></i> <?= htmlspecialchars(passenger_t('ot.receipt', 'Boarding Receipt'), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="ot-summary-row">
                <i class="bi bi-person-fill"></i>
                <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.passenger', 'Passenger'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="otDoneName" class="ot-summary-value">—</span>
            </div>
            <div class="ot-summary-row">
                <i class="bi bi-geo-alt"></i>
                <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.boarding', 'Boarding'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="otDoneBoarding" class="ot-summary-value">—</span>
            </div>
            <div class="ot-summary-row">
                <i class="bi bi-flag"></i>
                <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.destination', 'Destination'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="otDoneDestination" class="ot-summary-value">—</span>
            </div>
            <div class="ot-summary-row">
                <i class="bi bi-cash-coin"></i>
                <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.fare', 'Fare'), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="ot-summary-value ot-summary-value-flex">
                    <span id="otDoneFare">—</span>
                    <span id="otPayStatusChip" class="ot-pay-chip pending" style="display:none;"><?= htmlspecialchars(passenger_t('ot.pending', 'Pending'), ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </div>
            <div class="ot-summary-row">
                <i class="bi bi-credit-card"></i>
                <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.payment', 'Payment'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="otDonePayment" class="ot-summary-value">—</span>
            </div>
            <div class="ot-summary-row" id="otDoneIdRow" style="display:none;">
                <i class="bi bi-card-text"></i>
                <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.id_number', 'ID Number'), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="ot-summary-value ot-summary-value-flex">
                    <span id="otDoneId">—</span>
                    <span class="ot-pay-chip self-declared" id="otDoneIdStatus"><?= htmlspecialchars(passenger_t('ot.self_declared', 'Self-Declared'), ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </div>
            <div class="ot-summary-row" id="otDoneCompanionRow" style="display:none;">
                <i class="bi bi-people-fill"></i>
                <span class="ot-summary-label"><?= htmlspecialchars(passenger_t('ot.companions', 'Companions'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="otDoneCompanions" class="ot-summary-value">—</span>
            </div>
        </div>

        <div id="otMethodSwitch" style="display:none;">
            <div class="ot-method-label"><i class="bi bi-arrow-left-right"></i> <?= htmlspecialchars(passenger_t('ot.payment_method', 'Payment method'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="ot-method-options">
                <button type="button" class="ot-method-btn" data-method="cash">
                    <i class="bi bi-cash-stack"></i> <?= htmlspecialchars(passenger_t('ot.pay.cash', 'Cash'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" class="ot-method-btn" data-method="gcash">
                    <i class="bi bi-wallet2"></i> <?= htmlspecialchars(passenger_t('ot.pay.online', 'Online Payment'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>

        <div id="otPaymentPanelWrap" style="display:none;margin-top:14px;text-align:left;">
            <div id="otPaymentPanel"></div>
        </div>

        <div style="text-align:center;">
            <button type="button" id="otDoneBtn" class="ot-done-btn" style="display:none;">
                <i class="bi bi-check-lg"></i> <?= htmlspecialchars(passenger_t('ot.done', 'Done'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
        <div id="otDoneNote" style="display:none;font-size:12px;color:#7b8a74;margin-top:12px;line-height:1.5;">
            <?= htmlspecialchars(passenger_t('ot.done_note', 'You can now take your seat. Please prepare your fare — the driver will collect it during the trip.'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

</div>

<div id="otModal" class="ot-overlay">
    <div class="ot-card" style="width:100%;max-width:380px;padding:24px 20px;text-align:center;margin:0;animation:fadeUp .25s ease;">
        <div id="otModalIconWrap" style="width:54px;height:54px;margin:0 auto;border-radius:16px;display:flex;align-items:center;justify-content:center;background:#fff4d6;border:1.5px solid #eecb6f;">
            <i id="otModalIcon" class="bi bi-exclamation-triangle" style="font-size:24px;color:#b7791f;"></i>
        </div>
        <div id="otModalTitle" style="font-size:18px;font-weight:700;color:#24513c;margin-top:12px;"><?= htmlspecialchars(passenger_t('ot.modal_title', 'Something went wrong'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="otModalText" style="font-size:13px;color:#6c757d;margin-top:6px;line-height:1.5;"><?= htmlspecialchars(passenger_t('ot.modal_text', 'Please try again.'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="otModalActions" style="margin-top:18px;display:flex;flex-direction:column;gap:10px;"></div>
    </div>
</div>

<!-- Destination proximity alerts (non-blocking banners) -->
<div id="otDestinationNearBanner" style="display:none;position:fixed;left:12px;right:12px;top:12px;z-index:10040;background:#fffdf5;border:1.5px solid #f0dca8;border-radius:14px;padding:12px 14px;box-shadow:0 6px 20px rgba(0,0,0,.16);font-family:'Poppins',sans-serif;">
    <div style="display:flex;align-items:flex-start;gap:8px;">
        <i class="bi bi-geo-alt-fill" style="color:#c0812b;font-size:16px;flex-shrink:0;margin-top:1px;"></i>
        <div style="min-width:0;flex:1;">
            <div style="font-size:13px;font-weight:800;color:#7a5a17;"><?= htmlspecialchars(passenger_t('dest_alert.near_title', 'Destination Nearby'), ENT_QUOTES, 'UTF-8') ?></div>
            <div style="font-size:12px;color:#7b8a74;margin-top:2px;"><?= htmlspecialchars(passenger_t('dest_alert.near_lead', 'Your destination is nearby.'), ENT_QUOTES, 'UTF-8') ?></div>
            <div style="font-size:12px;color:#7b8a74;margin-top:2px;"><?= htmlspecialchars(passenger_t('dest_alert.near_body', 'Please prepare to get off at:'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="otDestinationNearName" style="font-size:14px;font-weight:700;color:#24513c;margin-top:2px;overflow-wrap:anywhere;"></div>
        </div>
        <button type="button" aria-label="Close" onclick="document.getElementById('otDestinationNearBanner').style.display='none'" style="border:0;background:transparent;color:#b09a63;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0;">&times;</button>
    </div>
</div>

<div id="otDestinationArrivalBanner" style="display:none;position:fixed;left:12px;right:12px;top:12px;z-index:10041;background:#eef8ef;border:1.5px solid #b7e4c7;border-radius:14px;padding:12px 14px;box-shadow:0 6px 20px rgba(0,0,0,.16);font-family:'Poppins',sans-serif;">
    <div style="display:flex;align-items:flex-start;gap:8px;">
        <i class="bi bi-check-circle-fill" style="color:#2f6b43;font-size:16px;flex-shrink:0;margin-top:1px;"></i>
        <div style="min-width:0;flex:1;">
            <div style="font-size:13px;font-weight:800;color:#2f6b43;"><?= htmlspecialchars(passenger_t('dest_alert.reached_title', 'Destination Reached'), ENT_QUOTES, 'UTF-8') ?></div>
            <div style="font-size:12px;color:#7b8a74;margin-top:2px;"><?= htmlspecialchars(passenger_t('dest_alert.reached_body', 'You are now near:'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="otDestinationArrivalName" style="font-size:14px;font-weight:700;color:#24513c;margin-top:2px;overflow-wrap:anywhere;"></div>
            <div style="font-size:12px;color:#7b8a74;margin-top:4px;"><?= htmlspecialchars(passenger_t('dest_alert.reached_hint', 'Please prepare to get off.'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <button type="button" aria-label="Close" onclick="document.getElementById('otDestinationArrivalBanner').style.display='none'" style="border:0;background:transparent;color:#7fae8f;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0;">&times;</button>
    </div>
</div>

<!-- Missed destination -> continuation journey -->
<div id="otContinuationOverlay" style="display:none;position:fixed;inset:0;z-index:10050;background:rgba(24,34,22,.55);font-family:'Poppins',sans-serif;">
    <div style="position:absolute;left:0;right:0;bottom:0;max-height:92vh;overflow:auto;background:#ffffff;border-radius:20px 20px 0 0;padding:18px 16px 24px;box-shadow:0 -8px 30px rgba(0,0,0,.25);">

        <div style="display:flex;align-items:center;gap:9px;">
            <span style="width:34px;height:34px;border-radius:50%;background:#fdecec;color:#a93226;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-flag-fill" style="font-size:15px;"></i>
            </span>
            <div style="min-width:0;">
                <div style="font-size:17px;font-weight:800;color:#24513c;line-height:1.2;"><?= htmlspecialchars(passenger_t('home.destination_passed_title', 'Destination Passed'), ENT_QUOTES, 'UTF-8') ?></div>
                <div style="font-size:11px;color:#7b8a74;margin-top:1px;"><?= htmlspecialchars(passenger_t('home.continue_your_journey', 'Continue Your Journey'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <div id="otContinuationPassedText" style="margin-top:12px;font-size:14px;font-weight:700;color:#a93226;overflow-wrap:anywhere;"></div>
        <div style="margin-top:6px;font-size:12.5px;color:#7b8a74;line-height:1.5;">
            <?= htmlspecialchars(passenger_t('home.destination_passed_hint', 'If you still need to continue your journey, set a new destination from your current location.'), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div style="margin-top:14px;background:#f4f8ef;border:1px solid #dce8d2;border-radius:12px;padding:10px 12px;">
            <div style="font-size:10px;font-weight:700;color:#6b8e6b;letter-spacing:.5px;text-transform:uppercase;"><?= htmlspecialchars(passenger_t('home.new_boarding_point', 'New Boarding Point'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="otContinuationBoardingPoint" style="font-size:16px;font-weight:800;color:#24513c;margin-top:2px;overflow-wrap:anywhere;">&mdash;</div>
        </div>

        <div style="margin-top:14px;font-size:12px;font-weight:700;color:#24513c;"><?= htmlspecialchars(passenger_t('home.choose_new_destination', 'Choose your new destination'), ENT_QUOTES, 'UTF-8') ?></div>
        <div id="otContinuationDestinations" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;"></div>
        <div id="otContinuationNoStops" style="display:none;margin-top:8px;font-size:12.5px;color:#7b8a74;"><?= htmlspecialchars(passenger_t('home.no_remaining_destinations', 'No stops remain ahead on this route.'), ENT_QUOTES, 'UTF-8') ?></div>

        <div id="otContinuationSummary" style="display:none;margin-top:16px;border:1px solid #eef2ec;border-radius:12px;padding:12px;">
            <div style="font-size:11px;font-weight:700;color:#7b8a74;letter-spacing:.5px;text-transform:uppercase;"><?= htmlspecialchars(passenger_t('home.payment_summary', 'Payment Summary'), ENT_QUOTES, 'UTF-8') ?></div>
            <div style="display:flex;justify-content:space-between;gap:10px;margin-top:8px;font-size:13px;color:#24513c;">
                <span><?= htmlspecialchars(passenger_t('home.previous_outstanding', 'Previous Outstanding'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="otContinuationPrevOutstanding" style="font-weight:700;">&#8369;0.00</span>
            </div>
            <div style="display:flex;justify-content:space-between;gap:10px;margin-top:6px;font-size:13px;color:#24513c;">
                <span><?= htmlspecialchars(passenger_t('home.continuation_fare', 'Continuation Fare'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="otContinuationFare" style="font-weight:700;">&mdash;</span>
            </div>
            <div style="border-top:1px dashed #dde5d4;margin-top:8px;padding-top:8px;display:flex;justify-content:space-between;gap:10px;font-size:14px;font-weight:800;color:#24513c;">
                <span><?= htmlspecialchars(passenger_t('home.total_due', 'TOTAL DUE'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="otContinuationTotalDue">&mdash;</span>
            </div>
        </div>

        <div id="otContinuationError" style="display:none;margin-top:10px;font-size:12.5px;font-weight:600;color:#a93226;"></div>

        <button type="button" id="otContinuationSubmitBtn" disabled style="margin-top:16px;width:100%;border:0;border-radius:12px;padding:14px;background:#47613b;color:#ffffff;font-family:'Poppins',sans-serif;font-size:14px;font-weight:800;opacity:.55;">
            <?= htmlspecialchars(passenger_t('home.continue_journey', 'Continue Journey'), ENT_QUOTES, 'UTF-8') ?>
        </button>

    </div>
</div>

<script>
window.PASSENGER_DEST_ALERT_STRINGS = {
    nearTitle: <?= json_encode(passenger_t('dest_alert.near_title', 'Destination Nearby'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    nearLead: <?= json_encode(passenger_t('dest_alert.near_lead', 'Your destination is nearby.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    nearBody: <?= json_encode(passenger_t('dest_alert.near_body', 'Please prepare to get off at:'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    reachedTitle: <?= json_encode(passenger_t('dest_alert.reached_title', 'Destination Reached'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    reachedBody: <?= json_encode(passenger_t('dest_alert.reached_body', 'The bus is now near your destination:'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    reachedHint: <?= json_encode(passenger_t('dest_alert.reached_hint', 'Please prepare to get off.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passedTitle: <?= json_encode(passenger_t('dest_alert.passed_title', 'Destination May Have Been Passed'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passedBody: <?= json_encode(passenger_t('dest_alert.passed_body', 'Your selected destination was:'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passedLead: <?= json_encode(passenger_t('dest_alert.passed_lead', 'The bus may have passed your selected destination.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    passedHint: <?= json_encode(passenger_t('dest_alert.passed_hint', 'Please notify the driver if you still need to get off.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    notifyNear: <?= json_encode(passenger_t('dest_alert.notify_near', 'Your destination is nearby.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    notifyReached: <?= json_encode(passenger_t('dest_alert.notify_reached', 'You are near your destination.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    notifyPassed: <?= json_encode(passenger_t('dest_alert.notify_passed', 'Your destination may have been passed.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    continueLabel: <?= json_encode(passenger_t('home.continue_journey', 'Continue Journey'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    dismissLabel: <?= json_encode(passenger_t('dest_alert.dismiss', 'Dismiss'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    unknownDestination: <?= json_encode(passenger_t('dest_alert.unknown_destination', 'Your selected destination'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
</script>
<script src="/assets/js/passenger/destination-alerts.js"></script>

<script>
const OT_BUS = <?= json_encode($oneTimeBus, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const OT_HOME_URL = '/passenger/home';
const OT_RESUME_TRIP_ID = <?= (int) $oneTimeResumeTripId ?>;
const OT_RESUME_METHOD = <?= json_encode(
    $oneTimeResumeMethod,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>;
const OT_RESUME_STATUS = <?= json_encode(
    $oneTimeResumeStatus,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>;
const OT_RESUME_DECLARED_ID = <?= json_encode(
    $oneTimeResumeDeclaredId,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>;
const OT_RESUME_DECLARED_STATUS = <?= json_encode(
    $oneTimeResumeDeclaredStatus,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?>;
const OT_RESUME_NAME = <?= json_encode($oneTimeResumeName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const OT_RESUME_BOARDING = <?= json_encode($oneTimeResumeBoarding, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const OT_RESUME_DESTINATION = <?= json_encode($oneTimeResumeDestination, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const OT_RESUME_FARE = <?= (float) $oneTimeResumeFare ?>;

const OT_I18N = <?= json_encode([
    'stepOf' => passenger_t('ot.step_of', 'Step :count of 5'),
    'step_details' => passenger_t('ot.step.details', 'Your details'),
    'step_boarding' => passenger_t('ot.step.boarding', 'Boarding point'),
    'step_destination' => passenger_t('ot.step.destination', 'Destination'),
    'step_payment' => passenger_t('ot.step.payment', 'Payment'),
    'step_confirm' => passenger_t('ot.step.confirm', 'Confirm'),
    'bus' => passenger_t('ot.bus', 'Bus'),
    'driver' => passenger_t('ot.driver', 'Driver'),
    'invalidBus' => passenger_t('ot.invalid_bus', 'Invalid Bus QR'),
    'invalidBusBody' => passenger_t('ot.invalid_bus_body', 'We could not identify this bus. Please scan the Bus QR again.'),
    'noActiveTrip' => passenger_t('ot.no_active_trip', 'No Active Trip'),
    'noActiveTripBody' => passenger_t('ot.no_active_trip_body', 'This bus currently has no active trip. Please try again later or contact the driver.'),
    'noStops' => passenger_t('ot.no_stops', 'No Stops Available'),
    'noStopsBody' => passenger_t('ot.no_stops_body', 'No stops are available for this trip. Please contact the driver.'),
    'loadTripFailed' => passenger_t('ot.load_trip_failed', 'We could not load your trip. Please check your connection and try again.'),
    'localStop' => passenger_t('ot.local_stop', 'Local / Current Stop'),
    'boardingRetry' => passenger_t('ot.boarding_retry', 'Retry'),
    'boardingUnavailable' => passenger_t('ot.boarding_error', 'We could not determine your boarding point. Please retry.'),
    'boardingSimulated' => passenger_t('ot.boarding_source_simulated', 'Simulated bus position'),
    'boardingLiveGps' => passenger_t('ot.boarding_source_gps', 'Live bus GPS'),
    'boardingLocked' => passenger_t('ot.boarding_locked', 'Boarding point locked at scan'),
    'idNumber' => passenger_t('ot.id_number', 'ID Number'),
    'idPlaceholder' => passenger_t('ot.id_placeholder', 'Enter your ID number'),
    'destinationError' => passenger_t('ot.destination_error', 'Please select your destination.'),
    'destinationBehind' => passenger_t('ot.destination_behind', 'That stop is behind your boarding point. Please choose a stop ahead of your boarding point.'),
    'computing' => passenger_t('ot.computing', 'Computing...'),
    'unavailable' => passenger_t('ot.unavailable', 'Unavailable'),
    'fareUnavailable' => passenger_t('ot.fare_unavailable', 'Fare unavailable'),
    'fareUnavailableBody' => passenger_t('ot.fare_unavailable_body', 'We could not compute the fare for this trip. Please choose a different destination or contact the driver.'),
    'backToDestination' => passenger_t('ot.back_to_destination', 'Back to destination'),
    'baseFare' => passenger_t('ot.base_fare', 'Base fare'),
    'fareWord' => passenger_t('ot.fare_word', 'fare'),
    'saved' => passenger_t('ot.saved', 'saved'),
    'discountNote' => passenger_t('ot.discount_note', 'discount'),
    'boardingNow' => passenger_t('ot.boarding_now', 'Boarding...'),
    'confirmContinue' => passenger_t('ot.confirm_continue', 'Confirm & Continue'),
    'connectionProblem' => passenger_t('ot.connection_problem', 'Connection problem'),
    'connectionSubmit' => passenger_t('ot.connection_submit', 'We could not reach the server. Please check your connection and tap Confirm again.'),
    'ok' => passenger_t('ot.ok', 'OK'),
    'scanAgain' => passenger_t('ot.scan_again', 'Scan again'),
    'chooseAgain' => passenger_t('ot.choose_again', 'Choose again'),
    'editDetails' => passenger_t('ot.edit_details', 'Edit details'),
    'tripInactive' => passenger_t('ot.trip_inactive', 'Trip no longer active'),
    'tripInactiveSuffix' => passenger_t('ot.trip_inactive_suffix', 'Please scan the Bus QR again.'),
    'invalidBoarding' => passenger_t('ot.invalid_boarding', 'Boarding point invalid'),
    'invalidDestination' => passenger_t('ot.invalid_destination', 'Destination invalid'),
    'checkDetails' => passenger_t('ot.check_details', 'Check your details'),
    'choosePayment' => passenger_t('ot.choose_payment', 'Choose a payment method'),
    'modalTitle' => passenger_t('ot.modal_title', 'Something went wrong'),
    'modalText' => passenger_t('ot.modal_text', 'Please try again.'),
    'receiptPaymentTitle' => passenger_t('ot.receipt_payment_title', 'Receipt & Payment'),
    'receiptTitle' => passenger_t('ot.receipt_title', 'Receipt'),
    'receiptText' => passenger_t('ot.receipt_text', "You are added to the driver's trip. The driver can now see you on the passenger list."),
    'receiptPaymentText' => passenger_t('ot.receipt_payment_text', 'Scan or download the QR Ph code below to pay with any bank or e-wallet app.'),
    'resumeText' => passenger_t('ot.resume_text', 'Your one-time boarding is attached to this trip.'),
    'verified' => passenger_t('ot.verified', 'Verified'),
    'selfDeclared' => passenger_t('ot.self_declared', 'Self-Declared'),
    'pending' => passenger_t('ot.pending', 'Pending'),
    'paid' => passenger_t('ot.paid', 'Paid'),
    'rejected' => passenger_t('ot.rejected', 'Rejected'),
    'doneNotePending' => passenger_t('ot.done_note_pending', 'The driver will collect your fare during the trip. This receipt updates once the driver confirms your payment.'),
    'cannotChangePayment' => passenger_t('ot.cannot_change_payment', 'Cannot change payment method'),
    'changePaymentFailed' => passenger_t('ot.change_payment_failed', 'We could not change your payment method. Please check your connection and try again.'),
    'loadingPayment' => passenger_t('ot.loading_payment', 'Loading your payment details...'),
    'paymentLoadFailed' => passenger_t('ot.payment_load_failed', 'We could not load your payment details. Please check your connection and try again.'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const TYPE_LABELS = {
    regular: <?= json_encode(passenger_t('ot.type.regular', 'Regular'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    student: <?= json_encode(passenger_t('ot.type.student', 'Student'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    senior: <?= json_encode(passenger_t('ot.type.senior', 'Senior Citizen'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    pwd: <?= json_encode(passenger_t('ot.type.pwd', 'PWD'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    special: <?= json_encode(passenger_t('ot.type.special', 'Teacher / Nurse'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    other: <?= json_encode(passenger_t('ot.type.other', 'Other'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};

const ID_LABELS = {
    student: <?= json_encode(passenger_t('ot.id.student', 'Student ID Number'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    senior: <?= json_encode(passenger_t('ot.id.senior', 'Senior Citizen ID Number'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    pwd: <?= json_encode(passenger_t('ot.id.pwd', 'PWD ID Number'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    special: <?= json_encode(passenger_t('ot.id.special', 'Teacher / Nurse ID Number'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};

function requiresId(type){
    return Object.prototype.hasOwnProperty.call(ID_LABELS, type);
}

const PAYMENT_LABELS = {
    cash: <?= json_encode(passenger_t('ot.pay.cash', 'Cash'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    gcash: <?= json_encode(passenger_t('ot.pay.online', 'Online Payment'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};

const state = {
    busNo: '',
    plateNumber: '',
    driverName: '',
    trip: null,
    stops: [],
    fullName: '',
    passengerType: 'regular',
    declaredIdNumber: '',
    contactNumber: '',
    boardingId: 0,
    boardingName: '',
    boardingOrder: 0,
    boardingError: null,
    busPosition: null,
    destinations: [],
    destinationId: 0,
    destinationName: '',
    destinationOrder: 0,
    fare: 0,
    fareBase: 0,
    fareDiscount: 0,
    fareColumn: '',
    partyTotal: 0,
    partyLines: [],
    paymentMethod: '',
    latitude: null,
    longitude: null,
    accuracy: null,
    submissionToken: '',
    submitting: false
};

let receiptTripId = 0;
let receiptToken = '';
let methodSwitching = false;
let payStatusTimer = null;
let destinationSearchTerm = '';

const STEP_META = {
    'details':     { count: 1, name: OT_I18N.step_details },
    'boarding':    { count: 2, name: OT_I18N.step_boarding },
    'destination': { count: 3, name: OT_I18N.step_destination },
    'payment':     { count: 4, name: OT_I18N.step_payment },
    'summary':     { count: 5, name: OT_I18N.step_confirm }
};

function el(id){
    return document.getElementById(id);
}

function showState(name){
    el('otLoading').style.display = name === 'loading' ? 'block' : 'none';
    el('otError').style.display = name === 'error' ? 'block' : 'none';
    el('otStepper').style.display = name === 'form' ? 'block' : 'none';
    el('otTripStrip').style.display = (name === 'form') ? 'flex' : 'none';

    ['agreement','details','boarding','destination','payment','summary','success'].forEach(function(step){
        const node = el('step-' + step);
        if(node){ node.style.display = 'none'; }
    });
}

// While the destination step is open the remaining-ahead list follows the
// bus: stops it passes drop out without the passenger doing anything.
const OT_DESTINATION_REFRESH_MS = 45000;
let otDestinationRefreshTimer = null;

function syncDestinationAutoRefresh(step){
    if(step === 'destination'){
        if(otDestinationRefreshTimer === null && typeof setInterval === 'function'){
            otDestinationRefreshTimer = setInterval(function(){
                refreshTripInfo();
            }, OT_DESTINATION_REFRESH_MS);
        }
        return;
    }

    if(otDestinationRefreshTimer !== null){
        clearInterval(otDestinationRefreshTimer);
        otDestinationRefreshTimer = null;
    }
}

function goToStep(step){
    ['agreement','details','boarding','destination','payment','summary','success'].forEach(function(name){
        const node = el('step-' + name);
        if(node){ node.style.display = name === step ? 'block' : 'none'; }
    });

    syncDestinationAutoRefresh(step);

    const meta = STEP_META[step];
    if(meta){
        el('otStepper').style.display = 'block';
        el('otStepCount').textContent = OT_I18N.stepOf.replace(':count', meta.count);
        el('otStepName').textContent = meta.name;
        el('otStepBar').style.width = (meta.count * 20) + '%';

        document.querySelectorAll('#otStepper .ot-dot').forEach(function(dot, index){
            dot.classList.toggle('done', index + 1 < meta.count);
            dot.classList.toggle('current', index + 1 === meta.count);
        });
    }else{
        el('otStepper').style.display = 'none';
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function populateTripStrip(){
    const direction = state.trip?.direction || state.trip?.route_name || '—';

    el('otStripRoute').textContent = direction;
    el('otStripBus').textContent = OT_I18N.bus + ' ' + (state.busNo || '—') + (state.driverName ? ' • ' + OT_I18N.driver + ': ' + state.driverName : '');
    el('otStripBadge').textContent = state.trip?.status || 'Active';
}

async function init(){
    showState('loading');
    requestGeolocation();

    if(!OT_BUS){
        showError({
            code: 'INVALID_BUS',
            message: OT_I18N.invalidBusBody
        });
        return;
    }

    try{
        const res = await fetch('/passenger/one-time/trip', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({bus: OT_BUS})
        });
        const data = await res.json();

        if(!data || !data.success || !data.data){
            showError(data || {});
            return;
        }

        state.trip = data.data.trip;
        state.busNo = data.data.bus?.bus_id || OT_BUS;
        state.plateNumber = data.data.bus?.plate_number || '';
        state.driverName = data.data.driver?.driver_name || '';

        populateTripStrip();

        // The server boot already decided resume vs new trip (OT_RESUME_TRIP_ID
        // comes from the live-filtered lookup). A stored local trip is never
        // used to reopen a receipt the server did not confirm: when the boot
        // resolves a new trip, the stale cache is removed so a completed trip
        // (or another passenger's stored trip) can never resurface.
        if(OT_RESUME_TRIP_ID <= 0){
            try{ localStorage.removeItem(otStorageKey('oneTimeTrip', 'rconnect_ot_trip')); }catch(e){}
        }

        // Stops come from the public one-time trip endpoint above; calling the
        // account-protected /passenger/api/destinations here fails with
        // "auth" before the passenger has registered.
        await loadStops(data.data.stops);
        applyTripBoarding(data.data);

        showState('form');
        resetAgreementGate();
        goToStep('agreement');
    }catch(err){
        if(err && err.code === 'NO_ROUTE_STOPS'){
            showError({
                code: 'NO_ROUTE_STOPS',
                message: OT_I18N.noStopsBody
            });
        }else{
            showError({
                code: 'SERVER_ERROR',
                message: OT_I18N.loadTripFailed
            });
        }
    }
}

function showError(data){
    el('otLoading').style.display = 'none';
    el('otStepper').style.display = 'none';
    el('otTripStrip').style.display = 'none';

    const code = data?.code || 'ERROR';

    if(code === 'NO_ACTIVE_TRIP' || code === 'TRIP_CHANGED'){
        el('otErrorTitle').textContent = OT_I18N.noActiveTrip;
        el('otErrorText').textContent = data?.message || OT_I18N.noActiveTripBody;
    }else if(code === 'NO_ROUTE_STOPS'){
        el('otErrorTitle').textContent = OT_I18N.noStops;
        el('otErrorText').textContent = data?.message || OT_I18N.noStopsBody;
    }else{
        el('otErrorTitle').textContent = OT_I18N.invalidBus;
        el('otErrorText').textContent = data?.message || OT_I18N.invalidBusBody;
    }

    el('otError').style.display = 'block';
}

async function loadStops(stops){
    const list = Array.isArray(stops) ? stops : [];

    if(list.length < 1){
        const error = new Error('no-stops');
        error.code = 'NO_ROUTE_STOPS';
        throw error;
    }

    state.stops = list.map(function(stop){
        return {
            id: Number(stop.id),
            destination: String(stop.destination),
            display_order: Number(stop.display_order)
        };
    });
}

// Applies the bus-location-derived boarding choice and the remaining-ahead
// destination list from the trip-info payload. The boarding point is fixed;
// when the payload carries a boarding error the step shows it with a retry.
function applyTripBoarding(data){
    state.busPosition = data.bus_position || null;
    state.boardingError = data.boarding_error || null;
    state.destinations = (Array.isArray(data.destinations) ? data.destinations : []).map(function(stop){
        return {
            id: Number(stop.id),
            destination: String(stop.destination),
            display_order: Number(stop.display_order),
            is_boarding: stop.is_boarding === true,
            same_as_boarding: stop.same_as_boarding === true
        };
    });

    applyBoarding(data.boarding || null);
}

async function refreshTripInfo(){
    try{
        const res = await fetch('/passenger/one-time/trip', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({bus: OT_BUS})
        });
        const data = await res.json();

        if(!data || !data.success || !data.data){
            return false;
        }

        applyTripBoarding(data.data);
        return true;
    }catch(err){
        return false;
    }
}

function boardingMetaText(){
    const position = state.busPosition || {};
    const parts = [];

    if(position.locked){
        parts.push(OT_I18N.boardingLocked);
    }
    if(position.source === 'simulation'){
        parts.push(OT_I18N.boardingSimulated);
    }else if(position.source){
        parts.push(OT_I18N.boardingLiveGps);
    }

    if(position.freshness){ parts.push(String(position.freshness)); }
    if(position.age_seconds !== null && position.age_seconds !== undefined){
        parts.push(Number(position.age_seconds) + 's');
    }
    if(position.matched_distance_km !== null && position.matched_distance_km !== undefined){
        parts.push(Math.round(Number(position.matched_distance_km) * 1000) + ' m');
    }

    return parts.length ? parts.join(' • ') : '—';
}

function renderBoardingCard(){
    const auto = el('otBoardingAuto');
    const next = el('otBoardingNext');
    const errorEl = el('otBoardingError');
    const retry = el('otBoardingRetry');

    if(!auto){ return; }

    const error = state.boardingError;

    if(error || state.boardingId <= 0){
        auto.style.display = 'none';
        errorEl.textContent = (error && error.message) ? error.message : OT_I18N.boardingUnavailable;
        errorEl.style.display = 'block';
        if(retry){ retry.style.display = ''; }
        if(next){ next.disabled = true; next.style.opacity = '0.55'; next.style.cursor = 'not-allowed'; }
        return;
    }

    auto.style.display = 'block';
    errorEl.style.display = 'none';
    if(retry){ retry.style.display = 'none'; }
    if(next){ next.disabled = false; next.style.opacity = ''; next.style.cursor = ''; }

    el('otBoardingAutoName').textContent = state.boardingName || '—';
    el('otBoardingAutoMeta').textContent = boardingMetaText();
    el('otBoardingPillText').textContent = state.boardingName || '—';
}

function applyBoarding(boarding){
    state.boardingId = 0;
    state.boardingName = '';
    state.boardingOrder = 0;
    state.destinationId = 0;
    state.destinationName = '';
    state.destinationOrder = 0;
    state.fare = 0;

    if(boarding){
        state.boardingId = Number(boarding.id) || 0;
        state.boardingName = String(boarding.destination || '');
        state.boardingOrder = Number(boarding.display_order) || 0;
    }

    destinationSearchTerm = '';
    const destinationSearch = el('otDestinationSearch');
    if(destinationSearch){ destinationSearch.value = ''; }

    renderBoardingCard();
    renderDestinationOptions();
}

function renderDestinationOptions(){
    const list = el('otDestinationList');
    list.innerHTML = '';

    // Only the stops the server says remain ahead of the bus (after the
    // matched boarding stop, in the trip's display order). The boarding stop
    // itself is only a destination through the end-of-trip same-stop fallback.
    const term = destinationSearchTerm.trim().toLowerCase();
    const valid = (Array.isArray(state.destinations) ? state.destinations : [])
        .filter(function(stop){
            return term === '' || String(stop.destination).toLowerCase().indexOf(term) !== -1;
        })
        .sort(function(a, b){
            return Number(b.display_order) - Number(a.display_order);
        });

    if(!valid.some(function(stop){ return stop.id === state.destinationId; })){
        state.destinationId = 0;
        state.destinationName = '';
        state.destinationOrder = 0;
    }

    // End-of-trip fallback: when the bus is at the last stop the only entry
    // is the boarding stop itself (same-stop, minimum fare). Preselect it so
    // the form does not dead-end on a blocked Continue.
    if(!state.destinationId){
        const sameStop = valid.find(function(stop){ return stop.same_as_boarding; });
        if(sameStop){
            state.destinationId = sameStop.id;
            state.destinationName = sameStop.destination;
            state.destinationOrder = sameStop.display_order;
        }
    }

    valid.forEach(function(stop, index){
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ot-stop';
        button.dataset.id = String(stop.id);
        if(Number(stop.id) === state.destinationId){
            button.classList.toggle('active', true);
        }

        const order = document.createElement('span');
        order.className = 'ot-stop-index';
        order.textContent = String(index + 1);

        const name = document.createElement('span');
        name.className = 'ot-stop-name';
        name.textContent = stop.destination + ((stop.is_boarding || stop.same_as_boarding) ? ' (' + (OT_I18N.localStop || 'Local / Current Stop') + ')' : '');

        const check = document.createElement('i');
        check.className = 'bi bi-check-circle-fill ot-stop-check';

        button.appendChild(order);
        button.appendChild(name);
        button.appendChild(check);

        button.addEventListener('click', function(){
            selectDestination(stop);
        });

        list.appendChild(button);
    });

    if(state.destinationId){
        list.querySelectorAll('.ot-stop').forEach(function(node){
            node.classList.toggle('active', Number(node.dataset.id) === state.destinationId);
        });
    }

    destinationListEmpty = valid.length === 0;
    el('otDestinationEmpty').style.display = destinationListEmpty ? 'block' : 'none';
    syncDestinationNextState();
}

let destinationListEmpty = true;

function syncDestinationNextState(){
    const button = el('otDestinationNext');
    if(!button){ return; }

    const agree = el('otDestinationAgreement');
    const canContinue = !destinationListEmpty && !(agree && !agree.checked);

    button.disabled = !canContinue;
    button.style.opacity = canContinue ? '' : '0.55';
    button.style.cursor = canContinue ? '' : 'not-allowed';
}

function syncDeclaredIdField(){
    const wrap = el('otIdWrap');
    const label = el('otIdLabel');
    const input = el('otDeclaredId');
    const error = el('otIdError');

    if(!wrap || !label || !input){ return; }

    const required = requiresId(state.passengerType);
    wrap.style.display = required ? 'block' : 'none';
    if(error){ error.style.display = 'none'; }

    if(!required){
        input.value = '';
        state.declaredIdNumber = '';
        return;
    }

    const idLabel = ID_LABELS[state.passengerType] || OT_I18N.idNumber;
    label.textContent = idLabel;

    const placeholders = {
        student: 'e.g., 2024-12345 or 2021-00123-TG-0',
        senior: 'e.g., OSCA-12345 or 12-3456',
        pwd: 'e.g., PWD-00123 or 04-1234-567',
        special: 'e.g., PRC-0123456 or DEPED-2023-01',
        other: 'e.g., ID-123456'
    };
    input.placeholder = placeholders[state.passengerType] || OT_I18N.idPlaceholder || 'e.g., 2024-12345';
}

document.querySelectorAll('input[name="otPassengerType"]').forEach(function(input){
    input.addEventListener('change', function(){
        document.querySelectorAll('input[name="otPassengerType"]').forEach(function(radio){
            radio.closest('.discount-card').classList.toggle('active', radio.checked);
        });
        state.passengerType = input.value;
        state.fare = 0;
        state.fareBase = 0;
        state.fareDiscount = 0;
        el('otTypeError').style.display = 'none';
        syncDeclaredIdField();
    });
});

el('otDetailsNext').addEventListener('click', function(){
    if (String(el('otCompanionName').value || '').trim() && !otAddCompanion()) { return; }
    let name = el('otFullName').value.trim().replace(/\s+/g, ' ');
    el('otFullName').value = name;

    const nameRegex = /^[\p{L}\p{M}][\p{L}\p{M} .\-'’]{0,98}[\p{L}\p{M}.]$/u;
    if(name.length < 2 || name.length > 100 || !nameRegex.test(name) || /\d/.test(name)){
        el('otNameError').textContent = /\d/.test(name)
            ? 'Full name must not contain numbers.'
            : 'Please enter a valid full name (letters, spaces, periods, hyphens, and apostrophes only).';
        el('otNameError').style.display = 'block';
        el('otFullName').focus();
        return;
    }

    // Require at least two words (first and last name)
    const words = name.split(/\s+/).filter(Boolean);
    if(words.length < 2){
        el('otNameError').textContent = 'Please enter your full name (both first and last name).';
        el('otNameError').style.display = 'block';
        el('otFullName').focus();
        return;
    }

    // Ensure at least one word has 2+ characters
    const hasLongWord = words.some(function(w){ return w.length >= 2; });
    if(!hasLongWord){
        el('otNameError').textContent = 'Please enter a valid real name.';
        el('otNameError').style.display = 'block';
        el('otFullName').focus();
        return;
    }

    // Reject 3+ repeated letters (e.g. 'aaaa', 'zzzzz')
    if(/([\p{L}])\1{2,}/iu.test(name)){
        el('otNameError').textContent = 'Please enter a valid real name without repeated letters.';
        el('otNameError').style.display = 'block';
        el('otFullName').focus();
        return;
    }

    // Reject 5+ consecutive consonants (e.g. 'asdfghjk', 'sdfghjk')
    if(/[bcdfghjklmnpqrstvwxz]{5,}/i.test(name)){
        el('otNameError').textContent = 'Please enter a valid real name.';
        el('otNameError').style.display = 'block';
        el('otFullName').focus();
        return;
    }

    // Reject common keyboard mash patterns
    const mashPattern = /(?:asdf|sdfg|dfgh|fghj|ghjk|hjkl|qwerty|werty|ertyu|rtyui|tyuio|yuiop|zxcv|xcvb|cvbn|vbnm|qazw|wsxe|edcr)/i;
    if(mashPattern.test(name)){
        el('otNameError').textContent = 'Please enter a valid real name.';
        el('otNameError').style.display = 'block';
        el('otFullName').focus();
        return;
    }

    // Reject common dummy words
    const dummyNames = ['test', 'testing', 'dummy', 'sample', 'fake', 'asdf', 'qwerty', 'random', 'admin', 'unknown', 'none', 'null', 'na'];
    const lowerWords = words.map(function(w){ return w.toLowerCase().replace(/^[.\-'’]+|[.\-'’]+$/g, ''); });
    const hasDummy = lowerWords.some(function(lw){ return dummyNames.indexOf(lw) !== -1; });
    if(hasDummy){
        el('otNameError').textContent = 'Please enter a valid real name.';
        el('otNameError').style.display = 'block';
        el('otFullName').focus();
        return;
    }

    // Reject identical words (e.g. 'Test Test' or 'Juan Juan')
    if(lowerWords.length >= 2 && lowerWords.every(function(w){ return w === lowerWords[0]; })){
        el('otNameError').textContent = 'Please enter your full name (both first and last name).';
        el('otNameError').style.display = 'block';
        el('otFullName').focus();
        return;
    }

    // Check for vowels in each word (excluding accepted abbreviations like Ma, St, Sr, Jr, Ng)
    const acceptedVowelless = ['ma', 'st', 'sr', 'jr', 'dr', 'mr', 'ms', 'mrs', 'ng', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'];
    let wordsValid = true;
    for(let i = 0; i < words.length; i++){
        const parts = words[i].split(/[\-\'\’\.]+/);
        for(let j = 0; j < parts.length; j++){
            const clean = parts[j].toLowerCase();
            if(clean.length < 2) continue;
            if(acceptedVowelless.indexOf(clean) !== -1) continue;
            if(!/[aeiouyáéíóúâêîôûñ]/i.test(clean)){
                wordsValid = false;
                break;
            }
        }
        if(!wordsValid) break;
    }
    if(!wordsValid){
        el('otNameError').textContent = 'Please enter a valid real name.';
        el('otNameError').style.display = 'block';
        el('otFullName').focus();
        return;
    }
    el('otNameError').style.display = 'none';

    const checked = document.querySelector('input[name="otPassengerType"]:checked');
    if(!checked){
        el('otTypeError').style.display = 'block';
        return;
    }
    el('otTypeError').style.display = 'none';

    const declaredId = requiresId(checked.value) ? el('otDeclaredId').value.trim() : '';
    if(requiresId(checked.value)){
        const idLabel = ID_LABELS[checked.value] || 'ID number';
        if(declaredId.length < 3 || declaredId.length > 50 || !/^[A-Za-z0-9][A-Za-z0-9 .\-\/]*$/.test(declaredId)){
            el('otIdError').textContent = 'Please enter a valid ' + idLabel + ' (3-50 letters, numbers, hyphens, slashes, or periods).';
            el('otIdError').style.display = 'block';
            el('otDeclaredId').focus();
            return;
        }

        const digitMatches = declaredId.match(/\d/g);
        const digitCount = digitMatches ? digitMatches.length : 0;
        if(digitCount === 0){
            el('otIdError').textContent = idLabel + ' must contain numeric digits (e.g., 2024-12345 or OSCA-12345). Letters alone are not accepted.';
            el('otIdError').style.display = 'block';
            el('otDeclaredId').focus();
            return;
        }
        if(digitCount < 3){
            el('otIdError').textContent = idLabel + ' must contain at least 3 numeric digits.';
            el('otIdError').style.display = 'block';
            el('otDeclaredId').focus();
            return;
        }

        const digitsOnly = declaredId.replace(/\D/g, '');
        const lettersOnly = declaredId.replace(/[^A-Za-z]/g, '');
        if(/^(\d)\1{3,}$/.test(digitsOnly) || (lettersOnly === '' && ['123456', '1234567', '12345678', '123456789', '0123456', '987654321'].indexOf(digitsOnly) !== -1)){
            el('otIdError').textContent = 'Please enter a valid, authentic ' + idLabel + '.';
            el('otIdError').style.display = 'block';
            el('otDeclaredId').focus();
            return;
        }

        if(/(?:asdf|ghjk|qwerty|zxcv)/i.test(declaredId)){
            el('otIdError').textContent = 'Please enter a valid ' + idLabel + ' without random keyboard letters.';
            el('otIdError').style.display = 'block';
            el('otDeclaredId').focus();
            return;
        }

        const lowerId = declaredId.toLowerCase();
        const dummyIds = ['student', 'senior', 'pwd', 'special', 'id', 'id number', 'none', 'na', 'n/a', 'test', 'sample', 'asdf', 'qwerty', 'letters', 'random'];
        if(dummyIds.indexOf(lowerId) !== -1){
            el('otIdError').textContent = 'Please enter your actual ' + idLabel + '.';
            el('otIdError').style.display = 'block';
            el('otDeclaredId').focus();
            return;
        }
    }
    el('otIdError').style.display = 'none';

    let contact = el('otContact').value.trim().replace(/[\s\-()]/g, '');
    if(contact.length > 0){
        const phMobilePattern = /^(?:09|\+639|639)\d{9}$/;
        if(!phMobilePattern.test(contact) || /^09(\d)\1{8}$/.test(contact)){
            let contactErr = el('otContactError');
            if(!contactErr){
                contactErr = document.createElement('div');
                contactErr.id = 'otContactError';
                contactErr.className = 'ot-error';
                el('otContact').closest('.ot-field').after(contactErr);
            }
            contactErr.textContent = 'Please enter a valid Philippine mobile number (e.g., 09123456789).';
            contactErr.style.display = 'block';
            el('otContact').focus();
            return;
        }
        if(el('otContactError')) el('otContactError').style.display = 'none';
    }

    state.fullName = name;
    state.passengerType = checked.value;
    state.declaredIdNumber = declaredId;
    state.contactNumber = contact;

    goToStep('boarding');
});

el('otBoardingBack').addEventListener('click', function(){
    goToStep('details');
});

el('otBoardingNext').addEventListener('click', async function(){
    if(state.boardingId <= 0 || state.boardingError){
        renderBoardingCard();
        return;
    }

    // Refresh the bus position before choosing a destination so the
    // remaining-ahead list reflects the latest location.
    await refreshTripInfo();

    if(state.boardingId <= 0 || state.boardingError){
        renderBoardingCard();
        return;
    }

    renderDestinationOptions();
    goToStep('destination');
});

el('otBoardingRetry').addEventListener('click', async function(){
    const retry = el('otBoardingRetry');
    if(retry){ retry.disabled = true; }

    const ok = await refreshTripInfo();

    if(retry){ retry.disabled = false; }

    if(!ok){
        el('otBoardingError').textContent = OT_I18N.boardingUnavailable;
        el('otBoardingError').style.display = 'block';
        if(retry){ retry.style.display = ''; }
        return;
    }

    renderBoardingCard();
});

el('otDestinationBack').addEventListener('click', function(){
    goToStep('boarding');
});

function selectDestination(stop){
    state.destinationId = stop.id;
    state.destinationName = stop.destination;
    state.destinationOrder = stop.display_order;
    state.fare = 0;
    state.fareBase = 0;
    state.fareDiscount = 0;

    el('otDestinationError').style.display = 'none';

    document.querySelectorAll('#otDestinationList .ot-stop').forEach(function(node){
        node.classList.toggle('active', Number(node.dataset.id) === stop.id);
    });
}

el('otDestinationNext').addEventListener('click', async function(){
    if(state.boardingId <= 0 || state.destinationId <= 0){
        el('otDestinationError').textContent = OT_I18N.destinationError;
        el('otDestinationError').style.display = 'block';
        return;
    }

    el('otDestinationError').style.display = 'none';

    const destinationAgreement = el('otDestinationAgreement');
    if(destinationAgreement && !destinationAgreement.checked){
        el('otDestinationAgreementError').style.display = 'block';
        return;
    }
    el('otDestinationAgreementError').style.display = 'none';

    el('otPaymentNext').disabled = true;
    goToStep('payment');
    el('otFareValue').textContent = OT_I18N.computing;

    const ok = await previewFare();
    el('otPaymentNext').disabled = false;

    if(!ok){
        el('otFareValue').textContent = OT_I18N.unavailable;
        const safeFareError = (window.PassengerSession && typeof window.PassengerSession.sanitizeErrorMessage === 'function')
            ? window.PassengerSession.sanitizeErrorMessage(otQuoteError, OT_I18N.fareUnavailableBody)
            : (otQuoteError || OT_I18N.fareUnavailableBody);
        showModal(
            OT_I18N.fareUnavailable,
            safeFareError,
            [{ label: OT_I18N.backToDestination, primary: true, onClick: function(){ hideModal(); goToStep('destination'); } }]
        );
    }
});

let otQuoteSequence = 0;
let otQuoteError = '';
async function previewFare(){
    const requestId = ++otQuoteSequence;
    if(!state.boardingId || !state.destinationId){
        return false;
    }

    try{
        const res = await fetch('/passenger/one-time/fare', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                bus_no: state.busNo,
                trip_no: String(state.trip?.id || ''),
                passenger_type: state.passengerType,
                full_name: state.fullName,
                boarding_id: state.boardingId,
                destination_id: state.destinationId,
                companions: (state.companions && Array.isArray(state.companions)) ? state.companions : []
            })
        });

        const data = await res.json();
        if (requestId !== otQuoteSequence) { return false; }

        if(data && data.success && data.data && Number(data.data.fare) > 0){
            state.fare = Number(data.data.fare);
            state.fareBase = Number(data.data.base_fare || 0);
            state.fareDiscount = Number(data.data.discount_amount || 0);
            state.fareColumn = data.data.fare_column || '';
            state.partyTotal = Number(data.data.party_total || data.data.fare || 0);
            state.partyLines = (data.data.breakdown && Array.isArray(data.data.breakdown.lines)) ? data.data.breakdown.lines : [];
            otQuoteError = '';
            renderFare();
            return true;
        }

        if(data && typeof data.message === 'string' && data.message.trim() !== ''){
            otQuoteError = data.message;
        }
    }catch(err){
        otQuoteError = '';
    }

    if (requestId !== otQuoteSequence) { return false; }
    state.fare = 0;
    state.fareBase = 0;
    state.fareDiscount = 0;
    state.partyTotal = 0;
    state.partyLines = [];
    renderFare('Unavailable');
    return false;
}

async function recalcPartyFare(){
    if(!state.boardingId || !state.destinationId){ return; }
    const ok = await previewFare();
    if(ok){ renderFare(); }
}

function otTypeFareNote(type){
    const t = String(type || '').toLowerCase();
    return (['child','senior','senior citizen','student','pwd','special'].indexOf(t) !== -1)
        ? (OT_I18N.discountedFare || 'Discounted fare')
        : (OT_I18N.standardFare || 'Standard fare');
}

function otTypeShort(type){
    const t = String(type || '').toLowerCase();
    if(t === 'child'){ return OT_I18N.typeChild || 'Child'; }
    if(t === 'senior' || t === 'senior citizen'){ return TYPE_LABELS.senior || 'Senior Citizen'; }
    if(t === 'other' || t === 'regular'){ return TYPE_LABELS.regular || 'Regular'; }
    if(t === 'pwd'){ return 'PWD'; }
    return String(type || '');
}

function renderFare(fallback){
    const hasParty = (state.companions && state.companions.length > 0) && state.partyTotal > 0;
    const displayTotal = hasParty ? state.partyTotal : state.fare;
    const value = fallback || formatPeso(displayTotal);
    el('otFareValue').textContent = value;
    el('otSummaryFare').textContent = value;
    el('otDoneFare').textContent = value;
    const paymentInstructions = el('otPartyPaymentInstructions');
    if (paymentInstructions) {
        paymentInstructions.textContent = state.paymentMethod === 'gcash'
            ? 'Online Payment (PayMongo QRPh) · Payment pending. After confirming your trip, scan and pay the displayed QRPh code. Payment is confirmed after the provider settles it.'
            : state.paymentMethod === 'cash' ? 'Cash · Payment pending. Pay the total fare to the driver; payment stays pending until confirmed.' : 'Select Cash or Online Payment to pay the total fare.';
    }

    const breakdown = el('otFareBreakdown');
    if(!breakdown){ return; }
    if(fallback){
        breakdown.style.display = 'none';
        return;
    }

    const lines = Array.isArray(state.partyLines) ? state.partyLines : [];
    if(lines.length <= 1){
        const isDiscounted = ['student','senior','pwd','special'].indexOf(state.passengerType) !== -1;
        if(!isDiscounted){
            breakdown.style.display = 'none';
            return;
        }
        const label = TYPE_LABELS[state.passengerType] || state.passengerType;
        breakdown.textContent =
            OT_I18N.baseFare + ' ' + formatPeso(state.fareBase) +
            '  •  ' + label + ' ' + OT_I18N.fareWord + ' ' + formatPeso(state.fare) +
            (state.fareDiscount > 0 ? '  •  ' + OT_I18N.saved + ' ' + formatPeso(state.fareDiscount) : '');
        breakdown.style.display = 'block';
        return;
    }

    breakdown.innerHTML = '';
    lines.forEach(function(line, idx){
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;align-items:flex-start;gap:8px;margin-top:' + (idx === 0 ? '0' : '6px') + ';';

        const left = document.createElement('div');
        left.style.cssText = 'flex:1;min-width:0;';
        const nm = document.createElement('div');
        nm.style.cssText = 'font-size:12.5px;font-weight:700;color:#24513c;overflow-wrap:anywhere;';
        nm.textContent = line.name || 'Traveller';
        left.appendChild(nm);

        const typeLabel = line.type_label || otTypeShort(line.type);
        const route = (line.boarding_point || line.destination)
            ? (String(line.boarding_point || '') + (line.destination ? '  \u2192  ' + String(line.destination) : ''))
            : '';
        const meta = document.createElement('div');
        meta.style.cssText = 'font-size:11px;color:#7b8a74;margin-top:1px;';
        meta.textContent = (line.role === 'primary' ? typeLabel + '  \u2022  ' + (OT_I18N.you || 'You') : typeLabel)
            + (route ? '  \u2022  ' + route : '');
        left.appendChild(meta);

        const base = Number(line.base_fare) || 0;
        const discount = Number(line.discount_amount) || 0;
        if(base > 0){
            const fareMeta = document.createElement('div');
            fareMeta.style.cssText = 'font-size:11px;color:#7b8a74;margin-top:1px;';
            fareMeta.textContent = 'Base ' + formatPeso(base) + '  \u2022  Discount ' + formatPeso(discount);
            left.appendChild(fareMeta);
        }

        const extras = Array.isArray(line.extra_charges) ? line.extra_charges : [];
        extras.forEach(function(extra){
            const ex = document.createElement('div');
            ex.style.cssText = 'font-size:11px;color:#7b8a74;margin-top:1px;';
            ex.textContent = (extra.label || 'Extra charge') + '  ' + formatPeso(extra.amount);
            left.appendChild(ex);
        });

        const right = document.createElement('div');
        right.style.cssText = 'flex-shrink:0;font-size:12.5px;font-weight:800;color:#24513c;';
        right.textContent = formatPeso(line.final_fare);

        row.appendChild(left);
        row.appendChild(right);
        breakdown.appendChild(row);
    });

    const totalRow = document.createElement('div');
    totalRow.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:7px;border-top:1px dashed #dbe4d2;font-size:12.5px;font-weight:800;color:#24513c;';
    const totalLabel = document.createElement('span');
    totalLabel.textContent = OT_I18N.partyTotal || 'Party total';
    const totalValue = document.createElement('span');
    totalValue.textContent = formatPeso(state.partyTotal);
    totalRow.appendChild(totalLabel);
    totalRow.appendChild(totalValue);
    breakdown.appendChild(totalRow);

    breakdown.style.display = 'block';
}

function formatPeso(amount){
    return '₱' + Number(amount || 0).toFixed(2);
}

document.getElementById('otDestinationAgreement')?.addEventListener('change', function(){
    if(this.checked){
        el('otDestinationAgreementError').style.display = 'none';
    }
    syncDestinationNextState();
});

function syncAgreementButton(){
    const check = el('otAgreementCheck');
    const button = el('otAgreementNext');
    if(!button){ return; }

    const canContinue = !!(check && check.checked && !check.disabled);

    button.disabled = !canContinue;
    button.style.opacity = canContinue ? '' : '0.55';
    button.style.cursor = canContinue ? '' : 'not-allowed';
}

function resetAgreementGate(){
    const check = el('otAgreementCheck');
    const text = document.querySelector('.ot-agreement-text');
    const hint = el('otAgreementScrollHint');

    if(check){ check.checked = false; check.disabled = true; }
    if(text){ text.scrollTop = 0; }
    if(hint){ hint.style.display = 'flex'; }

    const error = el('otAgreementError');
    if(error){ error.style.display = 'none'; }

    syncAgreementButton();

    // Content shorter than the box: nothing to scroll, allow agreeing.
    if(text && text.scrollHeight <= text.clientHeight + 8){
        if(check){ check.disabled = false; }
        if(hint){ hint.style.display = 'none'; }
    }
}

(function(){
    const text = document.querySelector('.ot-agreement-text');
    if(!text){ return; }

    text.addEventListener('scroll', function(){
        if(text.scrollTop + text.clientHeight >= text.scrollHeight - 8){
            const check = el('otAgreementCheck');
            if(check){ check.disabled = false; }
            const hint = el('otAgreementScrollHint');
            if(hint){ hint.style.display = 'none'; }
        }
    }, {passive:true});
})();

document.getElementById('otAgreementCheck')?.addEventListener('change', function(){
    if(this.checked){
        el('otAgreementError').style.display = 'none';
    }
    syncAgreementButton();
});

document.getElementById('otAgreementNext')?.addEventListener('click', function(){
    const check = el('otAgreementCheck');
    if(!check || !check.checked){
        el('otAgreementError').style.display = 'block';
        return;
    }
    el('otAgreementError').style.display = 'none';
    goToStep('details');
});

syncAgreementButton();

document.getElementById('otDestinationSearch')?.addEventListener('input', function(){
    destinationSearchTerm = this.value || '';
    renderDestinationOptions();
});

document.querySelectorAll('#otPaymentOptions .payment-option').forEach(function(button){
    button.addEventListener('click', function(){
        state.paymentMethod = button.dataset.method || '';
        renderFare();
        document.querySelectorAll('#otPaymentOptions .payment-option').forEach(function(node){
            node.classList.toggle('active', node === button);
        });
        el('otPaymentError').style.display = 'none';
    });
});

el('otPaymentBack').addEventListener('click', function(){
    goToStep('destination');
});

el('otPaymentNext').addEventListener('click', function(){
    if(['cash','gcash'].indexOf(state.paymentMethod) === -1){
        el('otPaymentError').style.display = 'block';
        return;
    }
    el('otPaymentError').style.display = 'none';

    renderSummary();
    goToStep('summary');
});

function renderSummary(){
    el('otSummaryName').textContent = state.fullName || '—';
    el('otSummaryType').textContent = TYPE_LABELS[state.passengerType] || state.passengerType;

    const summaryIdRow = el('otSummaryIdRow');
    if(summaryIdRow){
        const hasDeclaredId = !!(state.declaredIdNumber && state.declaredIdNumber.length);
        summaryIdRow.style.display = hasDeclaredId ? 'flex' : 'none';
        if(hasDeclaredId){
            el('otSummaryId').textContent = state.declaredIdNumber;
        }
    }

    el('otSummaryBoarding').textContent = state.boardingName || '—';
    el('otSummaryDestination').textContent = state.destinationName || '—';
    el('otSummaryRoute').textContent = state.trip?.direction || state.trip?.route_name || '—';
    el('otSummaryBus').textContent = OT_I18N.bus + ' ' + (state.busNo || '—');
    const hasParty = (state.companions && state.companions.length > 0) && state.partyTotal > 0;
    el('otSummaryFare').textContent = formatPeso(hasParty ? state.partyTotal : state.fare);
    el('otSummaryPayment').textContent = PAYMENT_LABELS[state.paymentMethod] || '—';

    const fareNote = el('otSummaryFareNote');
    if(fareNote){
        if(state.fareDiscount > 0){
            fareNote.textContent = (TYPE_LABELS[state.passengerType] || state.passengerType) + ' ' + OT_I18N.discountNote + ' −' + formatPeso(state.fareDiscount);
            fareNote.style.display = 'block';
        }else{
            fareNote.style.display = 'none';
        }
    }

    const companionCount = (state.companions && state.companions.length) || 0;
    const summaryCompRow = el('otSummaryCompanionRow');
    if(summaryCompRow){
        if(companionCount){
            summaryCompRow.style.display = 'flex';
            el('otSummaryCompanions').textContent = companionCount + (companionCount === 1 ? ' companion' : ' companions');
        }else{
            summaryCompRow.style.display = 'none';
        }
    }
}

el('otSummaryBack').addEventListener('click', function(){
    goToStep('payment');
});

// =========================
// One-Time companions (child/senior/other riding together)
// =========================
if(!state.companions){ state.companions = []; }

function otAddCompanion(){
    const err = el('otCompanionError');
    const nameInput = el('otCompanionName');
    const typeInput = el('otCompanionType');
    const idInput = el('otCompanionId');
    let name = String(nameInput.value || '').trim().replace(/\s+/g, ' ').replace(/\w\S*/g, function(w){
        return w.charAt(0).toUpperCase() + w.substr(1).toLowerCase();
    });
    let type = typeInput.value || 'child';
    if(['child','senior','pwd','other'].indexOf(type) === -1){ type = 'child'; }
    const declared = type === 'senior' ? String(idInput.value || '').trim() : '';
    if (declared && (!/^[A-Za-z0-9][A-Za-z0-9 .\-\/]*$/.test(declared) || (declared.match(/\d/g) || []).length < 3 || declared.length > 50)) {
        err.textContent = 'Enter a valid Senior Citizen ID number or leave the optional field empty.';
        err.style.display = 'block'; return false;
    }

    if(name.length < 2 || name.length > 100 || !/^[\p{L}\p{M} .'\-]+$/u.test(name)){
        err.textContent = "Enter the companion's full name.";
        err.style.display = 'block';
        nameInput.focus();
        return false;
    }
    if(state.companions.some(function(c){ return String(c.full_name || '').toLowerCase() === name.toLowerCase(); })){
        err.textContent = name + ' is already in the list.';
        err.style.display = 'block';
        return false;
    }
    if(state.companions.length >= 5){
        err.textContent = 'A maximum of 5 companions is allowed per trip.';
        err.style.display = 'block';
        return false;
    }

    state.companions.push({ full_name: name, companion_type: type, declared_id_number: declared });
    err.style.display = 'none';
    nameInput.value = '';
    idInput.value = '';
    otRenderCompanions();
    renderSummary();
    recalcPartyFare();
    const confirmation = el('otCompanionSuccess');
    confirmation.textContent = name + ' added. ' + (state.companions.length + 1) + ' passengers in this party.';
    confirmation.style.display = 'block';
    return true;
}

function otRemoveCompanion(idx){
    if(idx >= 0 && idx < state.companions.length){
        state.companions.splice(idx, 1);
        otRenderCompanions();
        renderSummary();
        recalcPartyFare();
    }
}

function otRenderCompanions(){
    const list = el('otCompanionList');
    const err = el('otCompanionError');
    if(!list){ return; }
    list.innerHTML = '';
    if(err){ err.style.display = 'none'; }
    state.companions.forEach(function(c, idx){
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;align-items:center;gap:8px;background:#f6f8f2;border:1px solid #e2e9da;border-radius:11px;padding:8px 11px;';
        const tag = document.createElement('span');
        tag.style.cssText = 'flex-shrink:0;font-size:10px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:#24513c;background:#e5eadd;border-radius:7px;padding:3px 7px;';
        tag.textContent = otTypeShort(c.companion_type || 'child');
        const name = document.createElement('span');
        name.style.cssText = 'flex:1;min-width:0;font-size:12.5px;font-weight:700;color:#24513c;overflow-wrap:anywhere;';
        name.textContent = String(c.full_name || '');
        if(c.companion_type === 'senior' && c.declared_id_number){
            const idChip = document.createElement('span');
            idChip.style.cssText = 'flex-shrink:0;font-size:9.5px;font-weight:800;color:#2f6b43;background:#dff0e2;border-radius:7px;padding:3px 7px;';
            idChip.textContent = 'Senior ID';
            row.appendChild(idChip);
        }
        const rem = document.createElement('button');
        rem.type = 'button';
        rem.style.cssText = 'flex-shrink:0;border:0;background:transparent;color:#a93226;font-size:15px;cursor:pointer;line-height:1;';
        rem.textContent = '\u00D7';
        rem.setAttribute('aria-label', 'Remove ' + name.textContent);
        rem.addEventListener('click', function(){ otRemoveCompanion(idx); });
        row.appendChild(tag); row.appendChild(name); row.appendChild(rem);
        list.appendChild(row);
    });
}

el('otCompanionName').addEventListener('keydown', function(e){
    if(e.key === 'Enter'){ e.preventDefault(); otAddCompanion(); }
});
el('otCompanionId').addEventListener('keydown', function(e){
    if(e.key === 'Enter'){ e.preventDefault(); otAddCompanion(); }
});
el('otCompanionBlock').addEventListener('focusout', function(e){
    if (e.relatedTarget && (el('otCompanionEntry').contains(e.relatedTarget) || el('otCompanionIdWrap').contains(e.relatedTarget))) { return; }
    if (String(el('otCompanionName').value || '').trim()) { otAddCompanion(); }
});
el('otCompanionType').addEventListener('change', function(){
    if(this.value === 'senior'){
        el('otCompanionIdWrap').style.display = 'block';
    }else{
        el('otCompanionIdWrap').style.display = 'none';
    }
});

el('otConfirmBtn').addEventListener('click', async function(){
    if(state.submitting){
        return;
    }

    ensureSubmissionToken();

    state.submitting = true;
    const button = el('otConfirmBtn');
    button.disabled = true;
    button.innerHTML = '<span class="ot-spinner" style="width:16px;height:16px;border-width:2px;border-top-color:#fff;margin:0;"></span> ' + OT_I18N.boardingNow;

    try{
        const res = await fetch('/passenger/one-time/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                bus_no: state.busNo,
                trip_no: String(state.trip?.id || ''),
                full_name: state.fullName,
                passenger_type: state.passengerType,
                declared_id_number: state.declaredIdNumber,
                contact_number: state.contactNumber,
                boarding_id: state.boardingId,
                destination_id: state.destinationId,
                latitude: state.latitude,
                longitude: state.longitude,
                accuracy: state.accuracy,
                payment_method: state.paymentMethod,
                submission_token: state.submissionToken,
                companions: (state.companions && Array.isArray(state.companions)) ? state.companions : []
            })
        });

        const data = await res.json();
        state.submitting = false;
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + OT_I18N.confirmContinue;

        if(data && data.success && data.data){
            renderSuccess(data);
            return;
        }

        handleSubmitError(data || {});
    }catch(err){
        state.submitting = false;
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + OT_I18N.confirmContinue;
        showModal(
            OT_I18N.connectionProblem,
            OT_I18N.connectionSubmit,
            [{ label: OT_I18N.ok, primary: true, onClick: hideModal }]
        );
    }
});

function ensureSubmissionToken(){
    if(state.submissionToken){
        return;
    }

    let token = '';

    if(window.crypto && typeof window.crypto.randomUUID === 'function'){
        token = window.crypto.randomUUID();
    }else{
        token = 'ot-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    }

    state.submissionToken = token;
}

function handleSubmitError(data){
    const code = data.code || 'ERROR';
    const message = data.message || OT_I18N.modalTitle + '. ' + OT_I18N.modalText;

    if(code === 'ALREADY_LOGGED_IN'){
        window.location.href = OT_HOME_URL;
        return;
    }

    if(code === 'NO_ACTIVE_TRIP' || code === 'TRIP_CHANGED'){
        showModal(
            OT_I18N.tripInactive,
            message + ' ' + OT_I18N.tripInactiveSuffix,
            [{ label: OT_I18N.scanAgain, primary: true, onClick: function(){ window.location.reload(); } }]
        );
        return;
    }

    if(code === 'INVALID_BOARDING'){
        showModal(OT_I18N.invalidBoarding, message, [{ label: OT_I18N.chooseAgain, primary: true, onClick: function(){ hideModal(); goToStep('boarding'); } }]);
        return;
    }

    if(code === 'INVALID_DESTINATION' || code === 'NO_FARE'){
        showModal(OT_I18N.invalidDestination, message, [{ label: OT_I18N.chooseAgain, primary: true, onClick: function(){ hideModal(); goToStep('destination'); } }]);
        return;
    }

    if(code === 'INVALID_NAME' || code === 'INVALID_PASSENGER_TYPE'){
        showModal(OT_I18N.checkDetails, message, [{ label: OT_I18N.editDetails, primary: true, onClick: function(){ hideModal(); goToStep('details'); } }]);
        return;
    }

    if(code === 'INVALID_PAYMENT_METHOD'){
        showModal(OT_I18N.choosePayment, message, [{ label: OT_I18N.chooseAgain, primary: true, onClick: function(){ hideModal(); goToStep('payment'); } }]);
        return;
    }

    showModal(OT_I18N.modalTitle, message, [{ label: OT_I18N.ok, primary: true, onClick: hideModal }]);
}

function renderSuccess(response){
    const data = response.data || {};
    const method = (data.payment_method || state.paymentMethod || '').toLowerCase();
    const fare = Number(data.fare || state.fare || 0);

    receiptTripId = Number(data.trip_id || 0);
    rememberReceiptToken(receiptTripId, state.submissionToken);

    el('otDoneName').textContent = data.passenger_name || state.fullName || '—';
    el('otDoneBoarding').textContent = data.boarding_point || state.boardingName || '—';
    el('otDoneDestination').textContent = data.destination || state.destinationName || '—';
    el('otDoneFare').textContent = formatPeso(fare);
    el('otDonePayment').textContent = PAYMENT_LABELS[method] || method || '—';

    const companions = Array.isArray(data.companions) ? data.companions : [];
    const doneCompRow = el('otDoneCompanionRow');
    if(doneCompRow){
        if(companions.length){
            doneCompRow.style.display = 'flex';
            el('otDoneCompanions').textContent = companions.length + (companions.length === 1 ? ' companion' : ' companions');
        }else{
            doneCompRow.style.display = 'none';
        }
    }
    if(data.party_fare){
        el('otDoneFare').textContent = formatPeso(Number(data.party_fare) || fare);
    }

    const declaredId = (data.declared_id_number || '').toString().trim();
    const doneIdRow = el('otDoneIdRow');
    if(doneIdRow){
        doneIdRow.style.display = declaredId ? 'flex' : 'none';
        if(declaredId){
            el('otDoneId').textContent = declaredId;
            const doneIdChip = el('otDoneIdStatus');
            if(doneIdChip){
                doneIdChip.textContent = (data.declared_id_status === 'verified') ? OT_I18N.verified : OT_I18N.selfDeclared;
            }
        }
    }

    el('otMethodSwitch').style.display = 'block';
    el('otDoneBtn').style.display = 'inline-flex';

    goToStep('success');
    setReceiptMethod(method === 'gcash' ? 'gcash' : 'cash');

    rememberOneTimeTrip({
        trip_id: data.trip_no || data.trip_id,
        trip_no: data.trip_no,
        passenger_trip_id: data.trip_id,
        passenger_id: data.passenger_id,
        passenger_name: data.passenger_name || state.fullName,
        bus_no: data.bus_no || state.busNo,
        boarding_point: data.boarding_point || state.boardingName,
        destination: data.destination || state.destinationName,
        fare: fare,
        payment_method: method,
        payment_status: data.payment_status || 'pending',
        declared_id_number: declaredId,
        declared_id_status: data.declared_id_status,
        submission_token: state.submissionToken
    });

    // Monitor the destination (proximity/passed alerts + continuation) while
    // the receipt is open.
    startOneTimeTracking();
}

function rememberReceiptToken(tripId, token){
    token = String(token || '');
    if(!tripId || !token){
        return;
    }
    receiptToken = token;
    try{ window.sessionStorage.setItem('ot_receipt_token_' + tripId, token); }catch(e){}
}
function currentReceiptToken(){
    if(receiptToken){
        return receiptToken;
    }
    if(!receiptTripId){
        return '';
    }
    try{ receiptToken = window.sessionStorage.getItem('ot_receipt_token_' + receiptTripId) || ''; }catch(e){ receiptToken = ''; }
    return receiptToken;
}
function receiptTokenQuery(){
    const token = currentReceiptToken();
    return token ? ('&t=' + encodeURIComponent(token)) : '';
}
function setDoneButtonState(enabled){
    const button = el('otDoneBtn');
    if(!button){ return; }

    button.disabled = !enabled;
    button.style.opacity = enabled ? '' : '0.55';
    button.style.cursor = enabled ? 'pointer' : 'not-allowed';
}

function setReceiptMethod(method){
    method = method === 'gcash' ? 'gcash' : 'cash';

    el('otDonePayment').textContent = PAYMENT_LABELS[method] || method;

    document.querySelectorAll('#otMethodSwitch .ot-method-btn').forEach(function(node){
        node.classList.toggle('active', node.dataset.method === method);
    });

    if(method === 'gcash'){
        el('otSuccessTitle').textContent = OT_I18N.receiptPaymentTitle;
        el('otSuccessText').textContent = OT_I18N.receiptPaymentText;
        el('otDoneNote').style.display = 'none';

        // Online payment: the receipt is only done once the fare is paid.
        setDoneButtonState(false);
        loadPaymentPanel(receiptTripId);
    }else{
        el('otSuccessTitle').textContent = OT_I18N.receiptTitle;
        el('otSuccessText').textContent = OT_I18N.receiptText;
        el('otPaymentPanelWrap').style.display = 'none';
        el('otPaymentPanel').innerHTML = '';
        el('otDoneNote').textContent = OT_I18N.doneNote;
        el('otDoneNote').style.display = 'block';

        // Cash is collected by the driver, so the passenger may proceed.
        setDoneButtonState(true);
    }

    startReceiptPolling();
}

function currentReceiptMethod(){
    const active = document.querySelector('#otMethodSwitch .ot-method-btn.active');
    if(active && active.dataset.method){
        return active.dataset.method;
    }
    if(OT_RESUME_METHOD === 'gcash' || OT_RESUME_METHOD === 'cash'){
        return OT_RESUME_METHOD;
    }
    return (state.paymentMethod || '').toLowerCase();
}

function applyReceiptPaymentStatus(status){
    const chip = el('otPayStatusChip');
    if(!chip){
        return;
    }

    const method = currentReceiptMethod();

    if(status === 'paid'){
        chip.className = 'ot-pay-chip paid';
        chip.textContent = OT_I18N.paid;
        chip.style.display = 'inline-flex';

        el('otDonePayment').textContent = (PAYMENT_LABELS[method] || OT_I18N.paid) + ' • ' + OT_I18N.paid;
        el('otDoneNote').textContent = method === 'gcash' ? OT_I18N.doneNotePaid : OT_I18N.doneNote;
        el('otDoneNote').style.display = 'none';

        el('otMethodSwitch').style.display = 'none';
        el('otDoneBtn').style.display = 'inline-flex';
        setDoneButtonState(true);

        if(payStatusTimer){
            clearInterval(payStatusTimer);
            payStatusTimer = null;
        }
        return;
    }

    if(status === 'rejected'){
        chip.className = 'ot-pay-chip rejected';
        chip.textContent = OT_I18N.rejected;
        chip.style.display = 'inline-flex';
        setDoneButtonState(method !== 'gcash');
        return;
    }

    chip.className = 'ot-pay-chip pending';
    chip.textContent = OT_I18N.pending;
    chip.style.display = 'inline-flex';

    setDoneButtonState(method !== 'gcash');

    if(method === 'cash'){
        el('otDoneNote').textContent = OT_I18N.doneNotePending;
        el('otDoneNote').style.display = 'block';
    }else{
        el('otDoneNote').style.display = 'none';
    }
}

async function refreshReceiptPayment(){
    if(!receiptTripId){
        return;
    }

    try{
        const res = await fetch(
            '/passenger/paymongo/status',
            {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({trip_id: receiptTripId, token: receiptToken})
            }
        );
        const data = await res.json();

        if(data && data.success){
            applyReceiptPaymentStatus(String(data.payment_status || '').toLowerCase());
        }
    }catch(err){

    }
}

function startReceiptPolling(){
    if(payStatusTimer){
        clearInterval(payStatusTimer);
        payStatusTimer = null;
    }

    if(!receiptTripId){
        return;
    }

    refreshReceiptPayment();
    payStatusTimer = setInterval(refreshReceiptPayment, 5000);
}

async function switchPaymentMethod(method){
    if(methodSwitching || !receiptTripId){
        return;
    }

    methodSwitching = true;

    document.querySelectorAll('#otMethodSwitch .ot-method-btn').forEach(function(node){
        node.disabled = true;
    });

    try{
        const res = await fetch('/passenger/one-time/payment-method', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                trip_id: receiptTripId,
                payment_method: method,
                token: currentReceiptToken()
            })
        });

        const data = await res.json();

        if(data && data.success){
            setReceiptMethod(data.payment_method || method);
        }else{
            showModal(
                OT_I18N.cannotChangePayment,
                data?.message || OT_I18N.modalText,
                [{ label: OT_I18N.ok, primary: true, onClick: hideModal }]
            );
        }
    }catch(err){
        showModal(
            OT_I18N.connectionProblem,
            OT_I18N.changePaymentFailed,
            [{ label: OT_I18N.ok, primary: true, onClick: hideModal }]
        );
    }finally{
        methodSwitching = false;

        document.querySelectorAll('#otMethodSwitch .ot-method-btn').forEach(function(node){
            node.disabled = false;
        });
    }
}

document.querySelectorAll('#otMethodSwitch .ot-method-btn').forEach(function(node){
    node.addEventListener('click', function(){
        const method = node.dataset.method || '';
        if(!method || node.classList.contains('active')){
            return;
        }
        switchPaymentMethod(method);
    });
});

async function loadPaymentPanel(tripId){
    const wrap = el('otPaymentPanelWrap');
    const panel = el('otPaymentPanel');

    if(!wrap || !panel || !tripId){
        return;
    }

    wrap.style.display = 'block';
    panel.innerHTML =
        '<div style="text-align:center;padding:24px 12px;">' +
            '<div class="ot-spinner"></div>' +
            '<div style="font-size:12px;color:#7b8a74;margin-top:12px;font-weight:600;">' + OT_I18N.loadingPayment + '</div>' +
        '</div>';

    try{
        const res = await fetch('/passenger/paymongo/modal', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: tripId, compact: 1, token: receiptToken})
        });
        const html = await res.text();

        panel.innerHTML = html;

        panel.querySelectorAll('script').forEach(function(old){
            const script = document.createElement('script');
            if(old.src){
                script.src = old.src;
            }else{
                script.textContent = old.textContent;
            }
            old.parentNode.replaceChild(script, old);
        });
    }catch(err){
        panel.innerHTML =
            '<div style="padding:16px;color:#a93226;font-size:12px;line-height:1.5;">' +
                OT_I18N.paymentLoadFailed +
            '</div>';
    }
}

function showResumeReceipt(){
    el('otLoading').style.display = 'none';
    el('otError').style.display = 'none';
    el('otTripStrip').style.display = 'none';
    el('otStepper').style.display = 'none';

    ['details','boarding','destination','payment','summary'].forEach(function(step){
        const node = el('step-' + step);
        if(node){ node.style.display = 'none'; }
    });

    el('step-success').style.display = 'block';
    el('otSuccessTitle').textContent = OT_RESUME_METHOD === 'gcash' ? OT_I18N.receiptPaymentTitle : OT_I18N.receiptTitle;
    el('otSuccessText').textContent = OT_I18N.resumeText;
    el('otDoneNote').style.display = 'none';

    // Rebuild the receipt from the saved trip so a refresh before the driver
    // confirms payment still shows the full receipt with the passenger's
    // chosen payment method.
    el('otDoneName').textContent = OT_RESUME_NAME || '—';
    el('otDoneBoarding').textContent = OT_RESUME_BOARDING || '—';
    el('otDoneDestination').textContent = OT_RESUME_DESTINATION || '—';
    el('otDoneFare').textContent = formatPeso(OT_RESUME_FARE);
    el('otDonePayment').textContent = PAYMENT_LABELS[OT_RESUME_METHOD] || OT_RESUME_METHOD || '—';
    el('otDoneSummary').style.display = 'block';

    const resumeIdRow = el('otDoneIdRow');
    if(resumeIdRow){
        resumeIdRow.style.display = OT_RESUME_DECLARED_ID ? 'flex' : 'none';
        if(OT_RESUME_DECLARED_ID){
            el('otDoneId').textContent = OT_RESUME_DECLARED_ID;
            const resumeIdChip = el('otDoneIdStatus');
            if(resumeIdChip){
                resumeIdChip.textContent = (OT_RESUME_DECLARED_STATUS === 'verified') ? OT_I18N.verified : OT_I18N.selfDeclared;
            }
        }
    }

    receiptTripId = Number(OT_RESUME_TRIP_ID);
    currentReceiptToken();

    // Reopening the receipt must resume destination monitoring and reopen the
    // continuation screen when the destination was already missed.
    startOneTimeTracking();

    const isPaid = OT_RESUME_STATUS === 'paid';

    el('otMethodSwitch').style.display = isPaid ? 'none' : 'block';
    el('otDoneBtn').style.display = 'inline-flex';

    if(isPaid){

        applyReceiptPaymentStatus('paid');
        return;
    }

    setReceiptMethod(OT_RESUME_METHOD === 'gcash' ? 'gcash' : 'cash');

    rememberOneTimeTrip({
        trip_id: OT_RESUME_TRIP_ID,
        trip_no: state.trip ? (state.trip.trip_number || state.trip.id) : null,
        passenger_trip_id: OT_RESUME_TRIP_ID,
        passenger_name: OT_RESUME_NAME,
        boarding_point: OT_RESUME_BOARDING,
        destination: OT_RESUME_DESTINATION,
        fare: OT_RESUME_FARE,
        payment_method: OT_RESUME_METHOD,
        payment_status: OT_RESUME_STATUS,
        declared_id_number: OT_RESUME_DECLARED_ID,
        declared_id_status: OT_RESUME_DECLARED_STATUS
    });
}

function otStorageKey(name, fallback){
    try {
        if (window.PassengerStorage && window.PassengerStorage.KEYS && window.PassengerStorage.KEYS[name]) {
            return window.PassengerStorage.KEYS[name];
        }
    } catch(e) {}
    return fallback;
}

function rememberOneTimeTrip(tripData){
    if(!tripData) return;
    try {
        /* Tag the cached trip with its owning passenger so another passenger's
           session on this browser can never resume it. */
        const stored = Object.assign({}, tripData);
        if(stored.owner_passenger_id === undefined || stored.owner_passenger_id === null){
            stored.owner_passenger_id = stored.passenger_id || null;
        }
        localStorage.setItem(otStorageKey('oneTimeTrip', 'rconnect_ot_trip'), JSON.stringify(stored));
        let history = [];
        try {
            const rawHist = localStorage.getItem(otStorageKey('oneTimeTripHistory', 'rconnect_ot_trip_history'));
            if(rawHist) history = JSON.parse(rawHist);
            if(!Array.isArray(history)) history = [];
        } catch(e) { history = []; }
        const pId = tripData.passenger_trip_id || tripData.trip_id;
        const existingIdx = history.findIndex(function(h){ return (h.passenger_trip_id || h.trip_id) === pId; });
        if(existingIdx >= 0){
            history[existingIdx] = Object.assign({}, history[existingIdx], tripData);
        } else {
            history.unshift(tripData);
        }
        localStorage.setItem(otStorageKey('oneTimeTripHistory', 'rconnect_ot_trip_history'), JSON.stringify(history.slice(0, 20)));
    } catch(err){}
}

/* Receipt resume is decided by the server boot (OT_RESUME_TRIP_ID from the
   live-filtered lookup). The local trip cache is display/payment-recovery
   data only and never reopens a receipt on its own. */

el('otDoneBtn').addEventListener('click', function(){
    el('otDoneBtn').style.display = 'none';
    el('otDoneNote').style.display = 'block';
});

function requestGeolocation(){
    if(!navigator.geolocation){
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(position){
            state.latitude = position.coords.latitude;
            state.longitude = position.coords.longitude;
            state.accuracy = position.coords.accuracy;
        },
        function(){

        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
}

function showModal(title, text, actions){
    el('otModalTitle').textContent = title;
    el('otModalText').textContent = text;

    const wrap = el('otModalActions');
    wrap.innerHTML = '';

    (actions || []).forEach(function(action){
        const button = document.createElement('button');
        button.type = 'button';
        button.className = action.primary ? 'ot-primary-btn' : 'ot-secondary-btn';
        button.textContent = action.label;
        button.addEventListener('click', action.onClick || hideModal);
        wrap.appendChild(button);
    });

    el('otModal').style.display = 'flex';
}

function hideModal(){
    el('otModal').style.display = 'none';
}

el('otModal').addEventListener('click', function(event){
    if(event.target === el('otModal')){
        hideModal();
    }
});

el('otRetryBtn').addEventListener('click', init);

// =========================
// DESTINATION ALERTS + CONTINUATION JOURNEY (one-time)
// =========================
const OT_CONTINUATION_STRINGS = {
    voiceNear: <?= json_encode(passenger_t('home.voice_near', 'You are near your destination. Please prepare to get off.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    voiceArrival: <?= json_encode(passenger_t('home.voice_arrival', 'You have arrived at your destination.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    youPassed: <?= json_encode(passenger_t('home.you_passed', 'You passed :destination.', ['destination' => '__DEST__']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    continueLabel: <?= json_encode(passenger_t('home.continue_journey', 'Continue Journey'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    continueStarted: <?= json_encode(passenger_t('home.continuation_started', 'Your continuation journey has started.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    continueFailed: <?= json_encode(passenger_t('home.continuation_failed', 'We could not start your continuation journey. Please try again.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    chooseFirst: <?= json_encode(passenger_t('home.continuation_choose_first', 'Please choose your new destination first.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    starting: <?= json_encode(passenger_t('home.continuation_starting', 'Starting...'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    startedTitle: <?= json_encode(passenger_t('home.continue_your_journey', 'Continue Your Journey'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};

const OT_CONTINUATION_TOKEN_KEY = otStorageKey('continuationToken', 'rconnect_continuation_token');
let otContinuationData = null;
let otContinuationSelectedId = null;
let otContinuationSelectedFare = null;
let otLocationWatchId = null;
let otLocationPollId = null;
let otBusAlertPollId = null;
let otLastLocationSentAt = 0;
let otDestinationBannerTimer = null;

if (window.PassengerSession) {
    window.PassengerSession.onInvalidate(function() {
        if (typeof otStopLocationTracking === 'function') {
            try { otStopLocationTracking(); } catch(e) {}
        }
        if (payStatusTimer) {
            clearInterval(payStatusTimer);
            payStatusTimer = null;
        }
    });
}

function otSpeak(text){
    try{
        if(!text || !('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined'){
            return;
        }
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-US';
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(utterance);
    }catch(err){
        // Voice is best effort; the banner is the guaranteed channel.
    }
}

function otShowBanner(kind, destinationName){
    const banner = el(kind === 'arrival' ? 'otDestinationArrivalBanner' : 'otDestinationNearBanner');
    const nameEl = el(kind === 'arrival' ? 'otDestinationArrivalName' : 'otDestinationNearName');
    if(!banner){ return; }

    if(nameEl){ nameEl.textContent = destinationName || ''; }

    banner.style.display = 'block';
    clearTimeout(otDestinationBannerTimer);
    otDestinationBannerTimer = setTimeout(function(){
        banner.style.display = 'none';
    }, kind === 'arrival' ? 10000 : 8000);
}

function otSyncDestinationAlertTrip(tripId){
    if(!window.RConnectDestinationAlerts){ return; }

    if(tripId){
        window.RConnectDestinationAlerts.setTrip(Number(tripId));
    }else{
        window.RConnectDestinationAlerts.reset();
    }
}

// Destination alerts are shared with the logged-in homepage; this page
// supplies the existing banner/voice experience and the continuation screen.
if(window.RConnectDestinationAlerts){
    window.RConnectDestinationAlerts.configure({
        strings: window.PASSENGER_DEST_ALERT_STRINGS || null,

        getTripId: function(){
            return receiptTripId || null;
        },

        showBanner: function(kind, name){
            otShowBanner(kind, name);
        },

        speak: function(kind, name){
            if(kind === 'near'){
                otSpeak(OT_CONTINUATION_STRINGS.voiceNear);
            }else if(kind === 'arrival'){
                otSpeak(OT_CONTINUATION_STRINGS.voiceArrival);
            }else if(kind === 'passed'){
                otSpeak(
                    OT_CONTINUATION_STRINGS.youPassed.replace('__DEST__', name || '')
                );
            }
        },

        onContinueJourney: function(){
            otCheckContinuationState();
        }
    });
}

function otHandleDestinationAlert(alert, fix){
    if(!window.RConnectDestinationAlerts){ return; }

    window.RConnectDestinationAlerts.handleAlert(
        (alert && typeof alert === 'object') ? alert : null,
        (fix && typeof fix === 'object') ? fix : null
    );
}

// The bus fix is the movement source. A stale or foreign bus still carries
// diagnostic coordinates, so it is passed to the panel even when unusable.
function otDestinationAlertFixFromBus(data){
    const bus = (data && data.bus_location) || null;

    if(bus && bus.latitude !== undefined && bus.latitude !== null){
        return {
            latitude: bus.latitude,
            longitude: bus.longitude,
            accuracy: null,
            source: 'driver'
        };
    }

    return null;
}

// Passenger coordinates are display-only fallback when no bus fix exists.
function otDestinationAlertFixFromResponse(data, position){
    const busFix = otDestinationAlertFixFromBus(data);
    if(busFix){ return busFix; }

    if(position && position.coords){
        return {
            latitude: (data && data.latitude) ?? position.coords.latitude,
            longitude: (data && data.longitude) ?? position.coords.longitude,
            accuracy: (data && data.accuracy) ?? position.coords.accuracy ?? null,
            source: 'passenger'
        };
    }

    return null;
}

function otGetOrCreateContinuationToken(){
    let token = '';
    try{ token = localStorage.getItem(OT_CONTINUATION_TOKEN_KEY) || ''; }catch(err){}

    if(!token){
        token = 'cont_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
        try{ localStorage.setItem(OT_CONTINUATION_TOKEN_KEY, token); }catch(err){}
    }

    return token;
}

function otClearContinuationToken(){
    try{ localStorage.removeItem(OT_CONTINUATION_TOKEN_KEY); }catch(err){}
}

function otSendLocation(position){
    if(!receiptTripId || !position || !position.coords){
        return;
    }

    const now = Date.now();
    if(now - otLastLocationSentAt < 3000){
        return;
    }
    otLastLocationSentAt = now;

    fetch('/passenger/trip/location', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy ?? null
        })
    }).then(function(res){
        return res.ok ? res.json() : null;
    }).then(function(data){
        if(data){
            // The same response also feeds the developer diagnostic panel,
            // so it is handled even when no alert fired. Movement is
            // measured from the bus GPS when it is available.
            otHandleDestinationAlert(
                data.destination_alert || null,
                otDestinationAlertFixFromResponse(data, position)
            );
        }
    }).catch(function(){
        // Location tracking is best effort on the receipt page.
    });
}

// Destination movement comes from the bus GPS (existing Rconnect-Drive
// pipeline), so alerts keep working when this phone's location is disabled.
function otStartBusAlertPolling(){
    if(otBusAlertPollId !== null){
        clearInterval(otBusAlertPollId);
        otBusAlertPollId = null;
    }

    otRefreshBusDestinationAlert();

    otBusAlertPollId = setInterval(function(){
        if(!receiptTripId){
            stopOneTimeTracking();
            return;
        }
        otRefreshBusDestinationAlert();
    }, 10000);
}

async function otRefreshBusDestinationAlert(){
    try{
        const res = await fetch('/passenger/trip/bus-location', {
            headers: {'Accept': 'application/json'}
        });

        if(!res.ok){ return; }

        const data = await res.json().catch(function(){ return null; });

        if(!data || data.success !== true || data.has_active_trip === false){ return; }

        otHandleDestinationAlert(
            data.destination_alert || null,
            otDestinationAlertFixFromBus(data)
        );
    }catch(err){
        // Best effort: the location post still evaluates the bus position.
    }
}

function startOneTimeTracking(){
    otCheckContinuationState();

    if(!receiptTripId){
        return;
    }

    // Alert state is keyed to the active trip: a new trip resets it and
    // hides any warning left from the previous trip.
    otSyncDestinationAlertTrip(receiptTripId);

    // Bus-location alerts do not need this phone's GPS.
    otStartBusAlertPolling();

    if(!navigator.geolocation){
        return;
    }

    // One watcher only: clear any previous watcher (and its polling
    // companion) before starting its replacement.
    if(otLocationWatchId !== null){
        try{ navigator.geolocation.clearWatch(otLocationWatchId); }catch(err){}
        otLocationWatchId = null;
    }
    if(otLocationPollId !== null){
        clearInterval(otLocationPollId);
        otLocationPollId = null;
    }

    otLocationWatchId = navigator.geolocation.watchPosition(otSendLocation, null, {
        enableHighAccuracy: true,
        maximumAge: 30000,
        timeout: 15000
    });

    otLocationPollId = setInterval(function(){
        if(!receiptTripId){
            stopOneTimeTracking();
            return;
        }
        navigator.geolocation.getCurrentPosition(otSendLocation, null, {
            enableHighAccuracy: true,
            maximumAge: 2000,
            timeout: 10000
        });
    }, 3000);
}

function stopOneTimeTracking(){
    if(otLocationWatchId !== null){
        try{ navigator.geolocation.clearWatch(otLocationWatchId); }catch(err){}
        otLocationWatchId = null;
    }
    if(otLocationPollId !== null){
        clearInterval(otLocationPollId);
        otLocationPollId = null;
    }
    if(otBusAlertPollId !== null){
        clearInterval(otBusAlertPollId);
        otBusAlertPollId = null;
    }
    otLastLocationSentAt = 0;
    otSyncDestinationAlertTrip(null);
}

async function otCheckContinuationState(){
    if(!receiptTripId){
        return;
    }

    try{
        const res = await fetch('/passenger/one-time/continuation', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({trip_id: receiptTripId, token: currentReceiptToken()})
        });
        const data = await res.json();

        if(data && data.success && data.needs_continuation){
            otOpenContinuationScreen(data);
        }
    }catch(err){
        // The screen can be reopened from the passed alert or next load.
    }
}

function otOpenContinuationScreen(data){
    otContinuationData = data || {};
    otContinuationSelectedId = null;
    otContinuationSelectedFare = null;

    const overlay = el('otContinuationOverlay');
    if(!overlay){ return; }

    const missed = otContinuationData.missed_destination || '';

    const passedEl = el('otContinuationPassedText');
    if(passedEl){
        passedEl.textContent = OT_CONTINUATION_STRINGS.youPassed.replace('__DEST__', missed);
    }

    const boardingEl = el('otContinuationBoardingPoint');
    if(boardingEl){
        boardingEl.textContent = otContinuationData.new_boarding_point || missed || '—';
    }

    const prev = Number(otContinuationData.previous_outstanding || 0);
    const prevEl = el('otContinuationPrevOutstanding');
    if(prevEl){ prevEl.textContent = formatPeso(prev); }

    const summary = el('otContinuationSummary');
    if(summary){ summary.style.display = 'block'; }

    const fareEl = el('otContinuationFare');
    if(fareEl){ fareEl.textContent = '—'; }
    const totalEl = el('otContinuationTotalDue');
    if(totalEl){ totalEl.textContent = '—'; }

    otRenderContinuationDestinations();

    const errorEl = el('otContinuationError');
    if(errorEl){ errorEl.style.display = 'none'; errorEl.textContent = ''; }

    const submitBtn = el('otContinuationSubmitBtn');
    if(submitBtn){
        submitBtn.disabled = true;
        submitBtn.style.opacity = '.55';
        submitBtn.textContent = OT_CONTINUATION_STRINGS.continueLabel;
    }

    overlay.style.display = 'block';
}

function otRenderContinuationDestinations(){
    const container = el('otContinuationDestinations');
    const noStops = el('otContinuationNoStops');
    if(!container){ return; }

    container.innerHTML = '';

    const destinations = Array.isArray(otContinuationData && otContinuationData.destinations)
        ? otContinuationData.destinations
        : [];

    if(noStops){ noStops.style.display = destinations.length ? 'none' : 'block'; }

    destinations.forEach(function(stop){
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.destinationId = String(stop.id);
        button.style.cssText = 'border:1.5px solid #dde5d4;background:#f8faf7;color:#24513c;border-radius:999px;padding:9px 14px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;touch-action:manipulation;';
        button.textContent = stop.destination;

        const fareTag = document.createElement('span');
        fareTag.style.cssText = 'display:block;font-size:10.5px;font-weight:600;color:#7b8a74;margin-top:1px;';
        fareTag.textContent = formatPeso(Number(stop.fare || 0));
        button.appendChild(fareTag);

        button.addEventListener('click', function(){
            otSelectContinuationDestination(stop, button);
        });

        container.appendChild(button);
    });
}

function otSelectContinuationDestination(stop, button){
    otContinuationSelectedId = Number(stop.id);
    otContinuationSelectedFare = Number(stop.fare || 0);

    document.querySelectorAll('#otContinuationDestinations button').forEach(function(node){
        node.style.borderColor = '#dde5d4';
        node.style.background = '#f8faf7';
        node.style.color = '#24513c';
    });

    if(button){
        button.style.borderColor = '#47613b';
        button.style.background = '#eef4e6';
        button.style.color = '#1f4d2e';
    }

    const fareEl = el('otContinuationFare');
    if(fareEl){ fareEl.textContent = formatPeso(otContinuationSelectedFare); }

    const prev = Number((otContinuationData && otContinuationData.previous_outstanding) || 0);
    const totalEl = el('otContinuationTotalDue');
    if(totalEl){ totalEl.textContent = formatPeso(prev + otContinuationSelectedFare); }

    const submitBtn = el('otContinuationSubmitBtn');
    if(submitBtn){
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
    }
}

async function otSubmitContinuation(){
    const submitBtn = el('otContinuationSubmitBtn');
    const errorEl = el('otContinuationError');

    if(!otContinuationSelectedId){
        if(errorEl){
            errorEl.textContent = OT_CONTINUATION_STRINGS.chooseFirst;
            errorEl.style.display = 'block';
        }
        return;
    }

    if(submitBtn){
        submitBtn.disabled = true;
        submitBtn.style.opacity = '.55';
        submitBtn.textContent = OT_CONTINUATION_STRINGS.starting;
    }
    if(errorEl){ errorEl.style.display = 'none'; errorEl.textContent = ''; }

    try{
        const token = otGetOrCreateContinuationToken();
        const res = await fetch('/passenger/one-time/continue', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                new_destination_id: otContinuationSelectedId,
                continuation_token: token,
                trip_id: receiptTripId,
                token: currentReceiptToken()
            })
        });
        const data = await res.json();

        if(data && data.success){
            otClearContinuationToken();

            const overlay = el('otContinuationOverlay');
            if(overlay){ overlay.style.display = 'none'; }

            showModal(
                OT_CONTINUATION_STRINGS.startedTitle,
                OT_CONTINUATION_STRINGS.continueStarted,
                [{ label: OT_I18N.ok, primary: true, onClick: hideModal }]
            );
            return;
        }

        if(errorEl){
            errorEl.textContent = (data && data.message) ? data.message : OT_CONTINUATION_STRINGS.continueFailed;
            errorEl.style.display = 'block';
        }
    }catch(err){
        if(errorEl){
            errorEl.textContent = OT_CONTINUATION_STRINGS.continueFailed;
            errorEl.style.display = 'block';
        }
    }finally{
        if(submitBtn){
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.textContent = OT_CONTINUATION_STRINGS.continueLabel;
        }
    }
}

el('otContinuationSubmitBtn').addEventListener('click', otSubmitContinuation);

if(OT_RESUME_TRIP_ID > 0){
    showResumeReceipt();
}else{
    init();
}
</script>

</body>
</html>

<?php
declare(strict_types = 1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

try {


    if (!Auth::check()) {
        header('Location: /public/login/index.php');
        exit;
    }

    $user = Auth::user();
    $role = strtolower(trim((string)($user['role'] ?? $_SESSION['role'] ?? '')));

    if (!$user || !in_array($role, ['admin', 'super_admin'], true)) {
        header('Location: /public/login/index.php');
        exit;
    }

} catch (Throwable $fatalError) {

    error_log("Dashboard Fatal Error: " . $fatalError->getMessage());

    die("A system error occurred while loading the dashboard.");
}


$validToken = require __DIR__ . '/../../config/access_token.php';
$token = $validToken;

if (($_GET['token'] ?? '') !== $validToken) {
    $_SESSION['admin_access_token'] = $validToken;
    setcookie('admin_token', $validToken, [
    'expires' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    ]);
}


include __DIR__ . '/layout/_header.php';
include __DIR__ . '/layout/_style.php';

?>

<style>
    @media(max-width:991px) {
        .header { left: 0 !important; padding: 0 10px !important; }
        #mainContent { padding: 10px !important; margin-top: 60px !important; }
        #mainContent > div[style*="padding:24px"] {
            padding: 14px 14px 72px !important;
            border-radius: 14px !important;
            border-left: none !important;
            border-right: none !important;
        }
        #mainContent h3.fw-bold { font-size: 1.15rem !important; }
        #mainContent h4.fw-bold { font-size: 1rem !important; }
        #mainContent h5.fw-bold { font-size: 0.9rem !important; }
        #mainContent h6.fw-semibold { font-size: 0.75rem !important; }
        #mainContent .badge { font-size: 0.55rem !important; }
        #mainContent .bi { font-size: 1rem !important; }
        #mainContent .card { border-radius: 14px !important; }
        #mainContent .card-body { padding: 12px !important; }
        #mainContent [class*="col-"] { padding-left: 6px !important; padding-right: 6px !important; }
        #mainContent .row { margin-left: -6px !important; margin-right: -6px !important; }
        #mainContent .btn-group .btn { font-size: 0.7rem !important; padding: 4px 6px !important; }
        #mainContent p { font-size: 0.75rem !important; }
        .header-left input { width: 100% !important; max-width: 200px !important; }
    }

    #dashFooterRow .dash-panel {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
    }

    #dashFooterRow .attention-list,
    #dashFooterRow .activity-list {
        flex: 1 1 auto;
        min-height: 150px;
    }

    #dashFooterRow .attention-empty,
    #dashFooterRow .activity-empty {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    #dashOpsRow .dash-panel {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
    }

    #dashOpsRow .ops-list,
    #dashOpsRow #fuelEfficiencyBody {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-height: 150px;
    }

    #dashOpsRow .ops-loading,
    #dashOpsRow .ops-empty,
    #dashOpsRow .fuel-loading,
    #dashOpsRow .fuel-empty {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
</style>
<body>

    <style>
        #dashLoadingOverlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 999999; background: #fdfbf6; display: flex; align-items: center; justify-content: center; transition: opacity .25s ease; }
        #dashLoadingOverlay.dash-hide { opacity: 0; pointer-events: none; }
        .dash-loader { text-align: center; }
        .dash-spinner { width: 46px; height: 46px; margin: 0 auto 14px; border: 4px solid #ece7d8; border-top-color: #2f6f4f; border-radius: 50%; animation: dashSpin .8s linear infinite; }
        @keyframes dashSpin { to { transform: rotate(360deg); } }
        .dash-loader-text { font-size: .95rem; font-weight: 600; color: #2f4f2f; }
        .dash-loader-sub { font-size: .75rem; color: #94a3b8; margin-top: 4px; }
    </style>
    <div id="dashLoadingOverlay">
        <div class="dash-loader">
            <div class="dash-spinner"></div>
            <div class="dash-loader-text">Loading dashboard...</div>
            <div class="dash-loader-sub">Preparing all data...</div>
        </div>
    </div>
    <script>
        (function () {
            window.__dashLoads = 0;
            window.__dashStart = 0;

            window.__dashLoadBegin = function () {
                window.__dashLoads = (window.__dashLoads || 0) + 1;
                if (!window.__dashStart) window.__dashStart = Date.now();
            };

            window.__dashLoadEnd = function () {
                window.__dashLoads = Math.max(0, (window.__dashLoads || 0) - 1);
                if (window.__dashLoads > 0) return;
                var wait = Math.max(0, 500 - (Date.now() - window.__dashStart));
                setTimeout(window.__dashHide, wait);
            };

            window.__dashHide = function () {
                var ov = document.getElementById("dashLoadingOverlay");
                if (!ov) return;
                ov.classList.add("dash-hide");
                setTimeout(function () { ov.style.display = "none"; }, 280);
            };

            setTimeout(window.__dashHide, 15000);
        })();
    </script>

<?php include __DIR__ . '/../../assets/layout/navbaradmin.php'; ?>

<div class="main-content" id="mainContent">

    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px 24px 84px;">

<?php include __DIR__ . '/sections/page-header.php'; ?>
<?php include __DIR__ . '/sections/quick-filters.php'; ?>
<?php include __DIR__ . '/../../components/cards.php'; ?>

<div class="row g-3 mb-4" id="dashOpsRow">
    <div class="col-12 col-lg-6 d-flex">
        <div class="w-100 h-100 d-flex"><?php include __DIR__ . '/sections/operational-summary.php'; ?></div>
</div>
<div class="col-12 col-lg-6 d-flex">
    <div class="w-100 h-100 d-flex"><?php include __DIR__ . '/sections/fuel-efficiency.php'; ?></div>
</div>
</div>

<?php include __DIR__ . '/sections/trip-activity-trend.php'; ?>

<?php include __DIR__ . '/../../components/driverPerformanceChart.php'; ?>

<div class="row g-3 mb-4" id="dashFooterRow">
    <div class="col-12 col-lg-6 d-flex">
        <div class="w-100 h-100 d-flex"><?php include __DIR__ . '/sections/needs-attention.php'; ?></div>
</div>
<div class="col-12 col-lg-6 d-flex">
    <div class="w-100 h-100 d-flex"><?php include __DIR__ . '/sections/recent-activity.php'; ?></div>
</div>
</div>

</div>

</div>

<?php
include __DIR__ . '/layout/_scripts.php';
include __DIR__ . '/layout/_footer.php';
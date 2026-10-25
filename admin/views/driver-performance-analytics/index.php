<?php
declare(strict_types = 1);

require_once __DIR__ . '/../../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

$user = Auth::user();
if (!in_array($user['role'], ['admin', 'super_admin'])) {
    header('Location: /admin/');
    exit;
}

// Always serve fresh analytics markup/scripts so fixes are never masked by a
// heuristically cached page. Purely a transport header; the page renders the
// same content.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);

require ROOT_PATH . '/admin/views/driver-performance-analytics/layout/header.php';
require ROOT_PATH . '/admin/views/driver-performance-analytics/layout/styles.php';
?>

<body>

    <style>
        #dashLoadingOverlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 999999; background: #fdfbf6; display: flex; align-items: center; justify-content: center; transition: opacity .25s ease; }
        #dashLoadingOverlay.dash-hide { opacity: 0; pointer-events: none; }
        .dash-loader { text-align: center; }
        .dash-spinner { width: 46px; height: 46px; margin: 0 auto 14px; border: 4px solid #ece7d8; border-top-color: #2f6f4f; border-radius: 50%; animation: dashSpin .8s linear infinite; }
        @keyframes dashSpin { to { transform: rotate(360deg); } }
        .dash-loader-text { font-size: .95rem; font-weight: 600; color: #2f4f2f; }
        .dash-loader-sub { font-size: .75rem; color: #94a3b8; margin-top: 4px; }
        @media print { #dashLoadingOverlay { display: none !important; } }
    </style>
    <div id="dashLoadingOverlay">
        <div class="dash-loader">
            <div class="dash-spinner"></div>
            <div class="dash-loader-text">Loading analytics...</div>
            <div class="dash-loader-sub">Preparing all data...</div>
        </div>
    </div>
    <script>
        (function () {
            window.__dashLoads = 0;
            window.__dashStart = 0;

            window.__dashLoadBegin = function () {
                window.__dashLoads = (window.__dashLoads || 0) + 1;
                if (window.__dashLoads === 1) {
                    window.__dashStart = Date.now();
                    var ov = document.getElementById("dashLoadingOverlay");
                    if (ov) {
                        ov.style.display = "flex";
                        ov.classList.remove("dash-hide");
                    }
                }
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

<?php require ROOT_PATH . '/admin/assets/layout/navbaradmin.php'; ?>

<div class="main-content" id="mainContent">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">

<?php require ROOT_PATH . '/admin/views/driver-performance-analytics/sections/performance-analytics.php'; ?>

</div>
</div>

<?php
require ROOT_PATH . '/admin/views/driver-performance-analytics/layout/scripts.php';
require ROOT_PATH . '/admin/views/driver-performance-analytics/layout/footer.php';

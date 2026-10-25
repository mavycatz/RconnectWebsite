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
if (!in_array(strtolower((string)($user['role'] ?? '')), ['admin', 'super_admin'], true)) {
    header('Location: /admin/');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);

require ROOT_PATH . '/admin/views/admin/layout/header.php';
require ROOT_PATH . '/admin/views/admin/layout/styles.php';
?>

<body>

<style>
    .sconf-head { max-width: 1440px; margin: 0 auto 18px; }
    .sconf-head h1 { font-family: 'Poppins', sans-serif; font-size: 1.35rem; font-weight: 800; color: #295738; margin: 0 0 2px; }
    .sconf-head p { font-family: 'Poppins', sans-serif; margin: 0; font-size: .82rem; color: #8a9a8a; font-weight: 500; }
    .sconf-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin: 16px 0 18px; }
    .sconf-tab { font-family: 'Poppins', sans-serif; display: inline-flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #e5e0a4; border-radius: 20px; padding: 9px 18px; font-size: .8rem; font-weight: 700; color: #6f6d75; cursor: pointer; transition: all .18s ease; }
    .sconf-tab:hover { border-color: #295738; color: #295738; }
    .sconf-tab.active { background: #295738; border-color: #295738; color: #fff; }
    .sconf-pane { max-width: 1440px; margin: 0 auto; }
</style>

<?php require ROOT_PATH . '/admin/assets/layout/navbaradmin.php'; ?>

<div class="main-content" id="mainContent">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">

        <div class="sconf-head">
            <h1>System Configuration</h1>
            <p>Operational reference data shared by the admin, finance, and operations modules. Employee and account management lives in Role Management.</p>
            <div class="sconf-tabs" id="sconfTabs">
                <button type="button" class="sconf-tab active" data-sconf-tab="fare"><i class="bi bi-cash-coin"></i> Fare Matrix</button>
                <button type="button" class="sconf-tab" data-sconf-tab="vehicle"><i class="bi bi-bus-front"></i> Vehicle &amp; Fuel Configuration</button>
            </div>
        </div>

        <section class="sconf-pane" id="sconfPaneFare">
            <?php require ROOT_PATH . '/admin/views/admin/sections/routes-cabanatuan-rizal.php'; ?>
        </section>

        <section class="sconf-pane" id="sconfPaneVehicle" style="display:none;">
            <?php require ROOT_PATH . '/admin/views/admin/sections/system-configuration.php'; ?>
        </section>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/public/login/custom-alert.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/system-configuration-filters.js?v=<?= time() ?>"></script>
<script>
    (function () {
        'use strict';

        var tabs = document.querySelectorAll('.sconf-tab');
        var panes = {
            fare: document.getElementById('sconfPaneFare'),
            vehicle: document.getElementById('sconfPaneVehicle')
        };

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-sconf-tab');
                tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
                Object.keys(panes).forEach(function (key) {
                    if (panes[key]) {
                        panes[key].style.display = key === target ? '' : 'none';
                    }
                });
            });
        });
    })();
</script>

<div id="customAlertOverlay" class="custom-alert-overlay">
    <div class="custom-alert-card">
        <div class="custom-alert-header">
            <div class="custom-alert-icon"></div>
            <div class="custom-alert-title"></div>
        </div>
        <div class="custom-alert-body">
            <div class="custom-alert-message"></div>
            <div class="custom-alert-actions">
                <button class="custom-alert-btn"></button>
                <button class="custom-alert-btn-cancel"></button>
            </div>
        </div>
    </div>
</div>

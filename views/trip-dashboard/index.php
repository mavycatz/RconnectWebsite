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

include __DIR__ . '/layout/_header.php';
include __DIR__ . '/layout/_styles.php';
?>
</head>
<body>

<?php include __DIR__ . '/../../assets/layout/navbaradmin.php'; ?>

<div class="main-content" id="mainContent">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
        <div class="container-fluid p-0">

<?php include __DIR__ . '/sections/page-header.php'; ?>
<?php include __DIR__ . '/sections/trip-stats.php'; ?>
<?php include __DIR__ . '/sections/map-section.php'; ?>
<?php include __DIR__ . '/sections/fleet-activity-section.php'; ?>
<?php include __DIR__ . '/sections/trip-overview.php'; ?>

</div>
</div>
</div>

<div class="modal fade" id="busPassengerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:20px;border:2px solid #cdd9b5;background:#fef8f3;">
            <div class="modal-header" style="border-bottom:2px solid #cdd9b5;padding:16px 20px;">
                <h5 class="modal-title" style="font-weight:700;color:#295738;"><i class="bi bi-bus-front"></i> Bus Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <div style="text-align:center;padding:40px;color:#6b7280;">
                    <div class="spinner-border" style="color:#295738;" role="status"></div>
                    <div style="margin-top:12px;font-weight:600;">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/layout/_scripts.php';
?>
</body>
</html>
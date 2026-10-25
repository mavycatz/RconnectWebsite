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

<body>

<?php include __DIR__ . '/../../assets/layout/navbaradmin.php'; ?>

<div class="main-content" id="mainContent">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
        <div class="container-fluid p-0">

<?php include __DIR__ . '/sections/page-title.php'; ?>
<?php include __DIR__ . '/sections/log-stats.php'; ?>
<?php include __DIR__ . '/sections/search-filter.php'; ?>
<?php include __DIR__ . '/sections/log-entries.php'; ?>

</div>
</div>
</div>

<?php include __DIR__ . '/layout/_footer.php'; ?>
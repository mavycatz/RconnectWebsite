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

define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);

require ROOT_PATH . '/admin/views/finances/layout/header.php';
require ROOT_PATH . '/admin/views/finances/layout/styles.php';
?>

<body>

<?php require ROOT_PATH . '/admin/assets/layout/navbaradmin.php'; ?>

<div class="main-content">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">

<?php require ROOT_PATH . '/admin/views/finances/sections/overview-cards.php'; ?>
<?php require ROOT_PATH . '/admin/views/finances/sections/filtersearch.php'; ?>

<div class="row g-4 mt-3">

    <div class="col-lg-6">
        <?php require ROOT_PATH . '/admin/views/finances/sections/recent-transactions.php'; ?>
    </div>

    <div class="col-lg-6">
        <?php require ROOT_PATH . '/admin/views/finances/sections/category-breakdown.php'; ?>
    </div>

    </div>

    <div class="row g-4 mt-3">

        <div class="col-12">
        <?php require ROOT_PATH . '/admin/views/finances/sections/fuel-logs.php'; ?>
    </div>

    </div>

    </div>
    </div>

<?php
require ROOT_PATH . '/admin/views/finances/layout/scripts.php';
require ROOT_PATH . '/admin/views/finances/layout/footer.php';
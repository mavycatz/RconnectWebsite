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
?>

<?php require ROOT_PATH . '/admin/views/system/layout/header.php'; ?>
<?php require ROOT_PATH . '/admin/views/system/layout/styles.php'; ?>

<body>

<?php require ROOT_PATH . '/admin/assets/layout/navbaradmin.php'; ?>

<div class="main-content" id="mainContent">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:20px;padding:20px;">

        <?php require ROOT_PATH . '/admin/views/system/sections/header.php'; ?>

        <?php require ROOT_PATH . '/admin/views/system/sections/monitoring-grid.php'; ?>

        <?php require ROOT_PATH . '/admin/views/system/sections/search.php'; ?>

        <?php require ROOT_PATH . '/admin/views/system/sections/log-cards.php'; ?>

    </div>
    </div>

<?php require ROOT_PATH . '/admin/views/system/layout/scripts.php'; ?>
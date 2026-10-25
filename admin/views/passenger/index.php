<?php
declare(strict_types = 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

$user = Auth::user();

if (!in_array($user['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: /admin/');
    exit;
}

define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);
?>
<?php require ROOT_PATH . '/admin/views/passenger/layout/header.php'; ?>
<?php require ROOT_PATH . '/admin/views/passenger/layout/styles.php'; ?>

<body class="passenger-management-page">

<?php require __DIR__ . '/layout/navbar.php'; ?>

<div class="main-content" id="mainContent">
    <div class="rmg-shell">
        <section class="rmg passenger-management" id="passengerManagementSection">

            <?php require ROOT_PATH . '/admin/views/passenger/sections/stats.php'; ?>
            <?php require ROOT_PATH . '/admin/views/passenger/sections/passenger-cards.php'; ?>
            <?php require ROOT_PATH . '/admin/views/passenger/sections/manifest-import.php'; ?>
            <?php require ROOT_PATH . '/admin/views/passenger/sections/modals.php'; ?>
            <?php require ROOT_PATH . '/admin/views/passenger/sections/discounts.php'; ?>

        </section>
        </div>
        </div>

<?php require ROOT_PATH . '/admin/views/passenger/layout/scripts.php'; ?>

<?php require __DIR__ . '/../layout/custom-alert.php'; ?>
<script src="/public/login/custom-alert.js?v=<?= time() ?>"></script>
</body>
</html>

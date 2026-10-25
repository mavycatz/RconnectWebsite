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

<body>

<?php
// navbaradmin.php is itself a full HTML document. Embedding it as-is leaves
// this page with a nested <head>/<body>, duplicate CDN loads, and a blocking
// <script> before the main content — which is what made the page paint raw
// before the design. Keep only its body markup and its style overrides so the
// page stays one valid, already-styled document.
ob_start();
require ROOT_PATH . '/admin/assets/layout/navbaradmin.php';
$navbarHtml = ob_get_clean();

$navbarStyles = '';
if (preg_match_all('~<style\b[^>]*>.*?</style>~is', $navbarHtml, $styleMatches)) {
    $navbarStyles = implode("\n", $styleMatches[0]);
}

$navbarBody = $navbarHtml;
if (preg_match('~<body\b[^>]*>(.*)</body>~is', $navbarHtml, $bodyMatch)) {
    $navbarBody = $bodyMatch[1];
}

echo $navbarStyles;
echo $navbarBody;
?>

<div class="main-content" id="mainContent">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
        <div class="container-fluid p-0">

            <?php require ROOT_PATH . '/admin/views/passenger/sections/stats.php'; ?>
            <?php require ROOT_PATH . '/admin/views/passenger/sections/passenger-cards.php'; ?>
            <?php require ROOT_PATH . '/admin/views/passenger/sections/manifest-import.php'; ?>
            <?php require ROOT_PATH . '/admin/views/passenger/sections/modals.php'; ?>
            <?php require ROOT_PATH . '/admin/views/passenger/sections/discounts.php'; ?>

        </div>
        </div>
        </div>

<?php require ROOT_PATH . '/admin/views/passenger/layout/scripts.php'; ?>

<script src="/public/login/custom-alert.js?v=<?= time() ?>"></script>

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

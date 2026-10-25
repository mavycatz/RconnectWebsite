<?php
if (!defined('APP_LOADED')) exit('No direct script access allowed');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$tk = $_GET['token'] ?? '';
$fu = function(string $page, string $query = '') use ($tk): string {
    $url = $tk ? "/finance/$page/$tk" : "/finance/$page.php";
    return $query ? $url . '?' . $query : $url;
};
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">
<?php require_once __DIR__ . '/../layout/navbarfinance.php'; ?>

<div class="main-content" style="font-family:'Poppins',sans-serif;background:#eceed9;padding:20px;">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
        <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>
        <?php require __DIR__ . '/_header.php'; ?>
        <?php require __DIR__ . '/_content.php'; ?>
        <?php require __DIR__ . '/_aibutton.php'; ?>


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

    </div>
</div>

<script src="/public/login/custom-alert.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/finance/ajax/dashboard.js"></script>

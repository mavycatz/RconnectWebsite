<?php
if(!defined('APP_LOADED')) exit('No direct script access allowed');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">
<link rel="stylesheet" href="/finance/assets/css/financialreportmodals.css?v=3">

<?php require_once __DIR__ . '/../layout/navbarfinance.php'; ?>

<div class="main-content" id="mainContent" style="font-family:'Poppins',sans-serif;background:#eceed9;padding:24px;">

    <div class="fr-page-shell">

        <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>
        <?php require __DIR__ . '/_header.php'; ?>
        <?php require __DIR__ . '/_financial_actions.php'; ?>
        <?php require __DIR__ . '/_contents.php'; ?>

    </div>

</div>


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

<?php require __DIR__ . '/_analytics.php'; ?>
<?php require __DIR__ . '/ai_reports_modal.php'; ?>
<?php require __DIR__ . '/_aibutton.php'; ?>
<script src="/public/login/custom-alert.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/finance/assets/js/financialreportmodals.js?v=1" defer></script>

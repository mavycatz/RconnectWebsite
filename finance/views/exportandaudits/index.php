<?php header('Cache-Control: no-store, no-cache, must-revalidate'); header('Pragma: no-cache'); ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">
<?php if (!defined('APP_LOADED')) exit('No direct script access allowed'); ?>
<div id="auditUser" 
     data-username="<?php echo htmlspecialchars(($_SESSION['user']['username'] ?? $_SESSION['user']['full_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System')); ?>"
     data-role="<?php echo htmlspecialchars(($_SESSION['user']['role'] ?? $_SESSION['role'] ?? 'Unknown')); ?>"
     style="display:none;"></div>

<?php require_once __DIR__ . '/../layout/navbarfinance.php'; ?>

<style>
@media (max-width: 768px) {
    .ea-grid-2,
    .ea-grid-3 {
        grid-template-columns: 1fr !important;
    }
    .ea-header-row {
        flex-wrap: wrap;
        gap: 10px;
    }
    .ea-header-row input,
    .ea-header-row select {
        max-width: 100%;
    }
    .ea-filter-row {
        flex-wrap: wrap;
    }
}
</style>

<div class="main-content" id="mainContent"
     style="font-family:'Poppins',sans-serif;background:#eceed9;padding:20px;min-height:100vh;">

  <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">

    <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">

    <div>
        <h1 style="font-weight:800;font-size:1.4rem;color:#295738;margin:0 0 2px;">
            Exports and Audits
        </h1>
        <p style="font-weight:500;font-size:0.82rem;color:#8a9a8a;margin:0;">
            Export reports and review system audit logs
        </p>
        <?php
        if (!class_exists(\Finance\Services\DateFilterService::class)) {
            require_once __DIR__ . '/../../services/DateFilterService.php';
        }
        $financePeriodResolved = \Finance\Services\DateFilterService::normalizeRequest($_GET);
        $financePeriodConfig = \Finance\Services\DateFilterService::config(
            $financePeriodResolved['period'],
            $financePeriodResolved['from'],
            $financePeriodResolved['to']
        );
        ?>
    </div>

    <div class="ea-header-row" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <?php require __DIR__ . '/../layout/_period_filter.php'; ?>
    </div>

</div>

    
    <ul class="nav nav-tabs" id="exportTabs" role="tablist"
        style="border-bottom:2px solid #cdd9b5;gap:4px;">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-exports" data-bs-toggle="tab" data-bs-target="#panel-exports"
                style="color:#57694d;font-weight:600;border:2px solid #cdd9b5;border-bottom:none;border-radius:12px 12px 0 0;padding:8px 20px;font-size:0.85rem;">
          <i class="bi bi-download me-1"></i> Exports
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-audits" data-bs-toggle="tab" data-bs-target="#panel-audits"
                style="color:#57694d;font-weight:600;border:2px solid #cdd9b5;border-bottom:none;border-radius:12px 12px 0 0;padding:8px 20px;font-size:0.85rem;">
          <i class="bi bi-clock-history me-1"></i> Audits
        </button>
      </li>
      <?php if (\App\Auth\Auth::role() === 'finance_manager'): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-trail" data-bs-toggle="tab" data-bs-target="#panel-trail"
                style="color:#57694d;font-weight:600;border:2px solid #cdd9b5;border-bottom:none;border-radius:12px 12px 0 0;padding:8px 20px;font-size:0.85rem;">
          <i class="bi bi-journal-text me-1"></i> Audit Trail
        </button>
      </li>
      <?php endif; ?>

    </ul>

    
    <div class="tab-content" style="background:#fffdf6;border:2px solid #cdd9b5;border-top:none;border-radius:0 0 16px 16px;padding:20px;">

      <div class="tab-pane fade show active" id="panel-exports">
        <?php require __DIR__ . '/_exports.php'; ?>
        <div style="margin-top:22px;">
          <?php require __DIR__ . '/_ereports.php'; ?>
        </div>
        <div style="margin-top:22px;">
          <?php require __DIR__ . '/_preports.php'; ?>
        </div>
        <div style="margin-top:22px;">
          <?php require __DIR__ . '/_exportlog.php'; ?>
        </div>
      </div>

      <div class="tab-pane fade" id="panel-audits">
        <?php require __DIR__ . '/_areports.php'; ?>
      </div>

      <?php if (\App\Auth\Auth::role() === 'finance_manager'): ?>
      <div class="tab-pane fade" id="panel-trail">
        <?php require __DIR__ . '/_audittrail.php'; ?>
      </div>
      <?php endif; ?>

    </div>

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

<?php require __DIR__ . '/_aibutton.php'; ?>
<script src="/public/login/custom-alert.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/finance/ajax/dashboard.js"></script>

<script>
window.__eaDateFilter = {
    period: <?= json_encode($financePeriodResolved['period']) ?>,
    from: <?= json_encode($financePeriodResolved['from']) ?>,
    to: <?= json_encode($financePeriodResolved['to']) ?>,
    start: <?= json_encode($financePeriodResolved['start']) ?>,
    end: <?= json_encode($financePeriodResolved['end']) ?>
};
document.addEventListener("financedatefilter:change", function (event) {
    var d = event.detail || {};
    window.__eaDateFilter = {
        period: d.period || "monthly",
        from: d.from || "",
        to: d.to || "",
        start: d.start || "",
        end: d.end || ""
    };
    if (typeof loadExportReports === "function") loadExportReports();
    if (typeof loadAudits === "function") loadAudits();
    if (typeof loadFeed === "function") {
        feedPage = 1;
        loadFeed();
    }
    if (typeof loadEL === "function") {
        elPage = 1;
        loadEL();
    }
    if (typeof loadAT === "function") {
        atPage = 1;
        loadAT();
    }
    if (typeof loadPdfCardData === "function") loadPdfCardData();
    if (typeof loadExcelCardData === "function") loadExcelCardData();
});
</script>
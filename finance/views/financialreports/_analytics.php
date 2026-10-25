<?php
require_once __DIR__ . '/../../middleware/csrf.php';
$financialAnalyticsCsrf = \Finance\Middleware\Csrf::generate();
?>
<link rel="stylesheet" href="/finance/assets/css/financialperformance.css?v=8">
<div class="modal fade fin-report-modal fpr-admin-preview" id="financialReportPreview" tabindex="-1" aria-labelledby="fprTitle" aria-hidden="true" data-bs-backdrop="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content fpr-preview-shell">
            <div class="modal-header">
                <div class="fpr-admin-header">
                    <h5><i class="bi bi-robot me-2" aria-hidden="true"></i><span id="fprTitle">Financial Report</span></h5>
                    <div id="fprCategoryBadge" class="fpr-admin-meta">Finance Department</div>
                    <div id="fprPeriod" class="fpr-admin-meta">Preparing the reporting period…</div>
                    <div id="fprSnapshotMeta" class="fpr-admin-meta"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close generated report preview"></button>
            </div>
             <div class="modal-body fpr-admin-body">
<section id="financialPerformance" class="fpr" data-csrf="<?= htmlspecialchars($financialAnalyticsCsrf, ENT_QUOTES) ?>" aria-labelledby="fprTitle">
    <div class="fpr-status" id="fprStatus" role="status" aria-live="polite">Loading verified financial data…</div>
    <div class="fpr-error" id="fprError" role="alert" hidden></div>
    <div class="fpr-empty" id="fprEmpty" hidden>No financial records match this reporting period and driver/bus selection.</div>
    <button type="button" id="fprReload" class="fpr-button mb-3" hidden>Retry loading report</button>
    <div id="fprResults" hidden>
        <section class="fpr-panel fpr-overview-section"><h3>Financial Overview</h3><div class="fpr-cards" id="fprCards"></div></section>
        <div class="fpr-grid">
            <section class="fpr-panel" id="fprProfitPanel"><h3>Financial Performance</h3><div id="fprProfit"></div></section>
            <section class="fpr-panel fpr-findings-panel"><h3>Executive Findings</h3><ul id="fprInsights"></ul></section>
        </div>
        <section class="fpr-panel" id="fprSummariesPanel">
            <div class="fpr-section-head"><h3>Financial Summaries</h3><div class="fpr-tabs" role="group" aria-label="Summary granularity">
                <button type="button" class="active" data-fpr-bucket="daily" aria-pressed="true">Daily</button>
                <button type="button" data-fpr-bucket="weekly" aria-pressed="false">Weekly</button>
                <button type="button" data-fpr-bucket="monthly" aria-pressed="false">Monthly</button>
                <button type="button" data-fpr-bucket="yearly" aria-pressed="false">Yearly</button>
            </div></div>
            <p class="fpr-muted">All buckets are restricted to the selected range. Weeks start Monday; partial periods remain clearly scoped to these dates.</p>
            <div id="fprSummaries" class="fpr-table-wrap"></div>
        </section>
        <div class="fpr-grid" id="fprCharts"></div>
        <section class="fpr-panel" id="fprBusesPanel"><div class="fpr-section-head"><h3>Bus financial performance</h3><select id="fprBusSort" aria-label="Bus ranking order"><option value="high">Highest revenue first</option><option value="low">Lowest revenue first</option></select></div><p class="fpr-muted">Rankings cover buses with recorded financial activity. Unassigned records are shown separately, not attributed using current assignments.</p><div id="fprBuses" class="fpr-table-wrap"></div></section>
        <section class="fpr-panel" id="fprDriversPanel"><h3>Driver financial performance</h3><div id="fprDrivers" class="fpr-table-wrap"></div></section>
        <section class="fpr-panel" id="fprCategoriesPanel"><h3>Expense categories</h3><div id="fprCategories" class="fpr-table-wrap"></div></section>
        <section class="fpr-panel" id="fprPaymentsPanel"><h3>Fare collection and passenger revenue</h3><div id="fprPayments" class="fpr-table-wrap"></div></section>
        <section class="fpr-panel" id="fprRecordsPanel">
            <div class="fpr-section-head"><h3>Detailed Financial Records</h3><div class="fpr-actions"><input id="fprRecordSearch" type="search" placeholder="Search references, driver, bus…" aria-label="Search financial records"></div></div>
            <div id="fprRecords" class="fpr-table-wrap"></div>
            <div class="fpr-pagination"><button type="button" id="fprPrev" class="fpr-button">Previous</button><span id="fprPageInfo"></span><button type="button" id="fprNext" class="fpr-button">Next</button></div>
        </section>
        <section class="fpr-panel fpr-analysis-panel" id="fprNarrativePanel" hidden><h3>AI Financial Analysis</h3><div id="fprNarrative" class="fpr-narrative"></div></section>
        <details class="fpr-panel"><summary>Data sources, reconciliation, and reporting methodology</summary><ul id="fprNotes"></ul></details>
    </div>
</section>
            </div>
            <div class="modal-footer">
                <button type="button" class="fpr-button" data-bs-dismiss="modal">Close</button>
                <button type="button" id="fprPdf" class="fpr-button fpr-primary" disabled><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Export PDF</button>
                <button type="button" id="fprCsv" class="fpr-button" disabled><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Export CSV</button>
            </div>
        </div>
    </div>
</div>
<script src="/finance/assets/js/financialperformance.js?v=9" defer></script>

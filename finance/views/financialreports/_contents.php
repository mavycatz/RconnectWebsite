<style>
.fin-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 3fr);
    gap: 24px;
    align-items: stretch;
    margin-top: 24px;
    --fr-panel-height: max(720px, calc(100vh - 120px));
}
.fin-report-grid {
    display: grid;
    grid-template-columns: minmax(0, 2.2fr) minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}
#financialReportContent {
    overflow-wrap: anywhere;
    min-width: 0;
    height: var(--fr-panel-height);
    display: flex;
    flex-direction: column;
}
#financialReportContent > .fr-report {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    height: auto;
}
#financialReportContent > .fr-placeholder {
    flex: 1 1 auto;
    height: auto;
    margin: 0;
}
#financialReportContent .fr-report .fr-report-body {
    flex: 1 1 auto;
    min-height: 0;
    max-height: none;
    overflow-y: auto;
}
#financialReportContent .fr-report > .fr-report-head,
#financialReportContent .fr-report > .fr-exports {
    flex: none;
}
#financialReportContent * {
    max-width: 100%;
    box-sizing: border-box;
}
#reportsListContainer.fr-sidebar-list {
    height: var(--fr-panel-height);
    max-height: var(--fr-panel-height);
    overflow-y: auto;
}

.fr-report {
    background: #fff;
    border-radius: 16px;
    border: 2px solid #e7e3af;
    padding: 24px;
}
.fr-report-head {
    margin-bottom: 16px;
}
.fr-badge {
    display: inline-block;
    background: #edf7ee;
    color: #05590c;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    margin-bottom: 10px;
}
.fr-title {
    color: #295738;
    font-weight: 800;
    margin: 0 0 6px;
    font-size: 1.5rem;
    line-height: 1.2;
}
.fr-meta {
    font-size: 0.82rem;
    color: #888;
    margin: 2px 0;
}
.fr-meta b {
    color: #57694d;
    font-weight: 600;
}
.fr-exports {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin: 4px 0 18px;
}
.fr-export-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 15px;
    border: 1.5px solid #cdd9b5;
    border-radius: 10px;
    background: #fff;
    color: #57694d;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
    font-family: inherit;
}
.fr-export-btn:hover {
    border-color: #295738;
    background: #f0f7f0;
    transform: translateY(-1px);
}
.fr-content {
    font-size: 0.92rem;
    line-height: 1.8;
    color: #333;
    margin-top: 6px;
}
.fr-content h3 {
    color: #295738;
    font-weight: 800;
    font-size: 1.02rem;
    margin: 20px 0 8px;
    padding-bottom: 4px;
    border-bottom: 2px solid #e7e3af;
}
.fr-content p {
    margin: 6px 0;
    color: #444;
}
.fr-content ul,
.fr-content ol {
    margin: 6px 0;
    padding-left: 20px;
    color: #444;
}
.fr-content li {
    margin: 3px 0;
    line-height: 1.6;
}

.fr-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2px;
    background: #e7e3af;
    border: 1px solid #e7e3af;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 18px;
}
.fr-metric {
    background: #fff;
    padding: 16px 18px;
}
.fr-metric .lbl {
    font-size: 0.7rem;
    font-weight: 700;
    color: #8a9a8a;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 6px;
}
.fr-metric .val {
    font-size: 1.5rem;
    font-weight: 800;
    color: #295738;
    line-height: 1.1;
    word-break: break-word;
}
.fr-metric .exp {
    font-size: 0.72rem;
    color: #9aa39a;
    margin-top: 6px;
    line-height: 1.4;
}
.fr-metric.exp .val {
    color: #c62828;
}
.fr-metric.net .val {
    color: #1b7a3a;
}
.fr-metric.mgn .val {
    color: #0d47a1;
}

.fr-section {
    background: #fff;
    border: 1px solid #e7e3af;
    border-radius: 14px;
    padding: 18px;
    margin-bottom: 16px;
}
.fr-section > h3 {
    color: #295738;
    font-weight: 800;
    font-size: 1.02rem;
    margin: 0 0 12px;
}
.fr-progress-row {
    margin-bottom: 12px;
}
.fr-progress-row:last-child {
    margin-bottom: 0;
}
.fr-progress-top {
    display: flex;
    justify-content: space-between;
    font-size: 0.82rem;
    margin-bottom: 5px;
    gap: 10px;
}
.fr-progress-top .nm {
    color: #444;
    font-weight: 600;
}
.fr-progress-top .pc {
    color: #295738;
    font-weight: 700;
    white-space: nowrap;
}
.fr-bar {
    height: 8px;
    background: #eef1e6;
    border-radius: 999px;
    overflow: hidden;
}
.fr-bar > i {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, #295738, #7da453);
    border-radius: 999px;
}
.fr-pay-donut {
    position: relative;
    height: 230px;
    max-width: 280px;
    margin: 0 auto 14px;
}

.fr-chart {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #f1f1f1;
    position: relative;
    height: 300px;
}

/* Page styles kept inline so they render even when the external stylesheet
   is served from an older cache. */
.fr-page-shell .fr-sidebar-list { min-width: 0; background: #fffdf8; border: 1px solid #e5e0a4; border-radius: 16px; padding: 16px; max-height: calc(100vh - 250px); overflow-y: auto; box-shadow: 0 2px 10px rgba(41, 87, 56, .05); }
.fr-sidebar-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-bottom: 10px; margin-bottom: 10px; border-bottom: 1px dashed #e7e3af; }
.fr-sidebar-head h6 { color: #295738; font-weight: 800; margin: 0; font-size: .9rem; display: flex; align-items: center; gap: 7px; }
.fr-sidebar-head h6 i { color: #7da453; }
.fr-sidebar-toggle { border: 1px solid #d7e3cd; background: #fff; font-size: .68rem; font-weight: 600; color: #57694d; cursor: pointer; padding: 4px 10px; border-radius: 8px; transition: .15s; white-space: nowrap; }
.fr-sidebar-toggle:hover { background: #eef2dc; border-color: #a8bd96; color: #295738; }
.fr-history-count { display: inline-flex; align-items: center; gap: 5px; background: #f9f4da; border: 1px solid #e7e3af; color: #6b7a63; border-radius: 999px; padding: 2px 10px; font-size: .68rem; font-weight: 700; margin-bottom: 10px; }
.fr-history-item { position: relative; padding: 12px 13px 11px; margin-bottom: 9px; background: #fff; border: 1px solid #e3e8d8; border-left: 4px solid #e7e3af; border-radius: 12px; cursor: pointer; transition: .18s; box-shadow: 0 1px 3px rgba(41, 87, 56, .05); }
.fr-history-item:hover { background: #f9faf4; border-color: #a8bd96; border-left-color: #7da453; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(41, 87, 56, .10); }
.fr-history-item.active-report { background: linear-gradient(180deg, #eef7ec, #e3f0e0); border-color: #a8bd96; border-left: 4px solid #295738; box-shadow: 0 3px 9px rgba(41, 87, 56, .12); }
.fr-history-title { font-weight: 700; color: #295738; font-size: .83rem; line-height: 1.35; padding-right: 26px; }
.fr-history-period { display: inline-flex; align-items: center; gap: 5px; background: #f6f8ec; border: 1px solid #e5e0a4; color: #57694d; border-radius: 999px; padding: 2px 9px; font-size: .65rem; font-weight: 600; margin-top: 6px; }
.fr-history-period i { font-size: .68rem; color: #8a9a8a; }
.fr-history-meta { font-size: .68rem; color: #8a9a8a; margin-top: 5px; line-height: 1.5; }
.fr-current-badge { display: inline-flex; align-items: center; gap: 4px; margin-top: 7px; background: #295738; color: #fff; border-radius: 999px; padding: 3px 10px; font-size: .62rem; font-weight: 700; letter-spacing: .2px; }
.fr-history-archive { position: absolute; top: 9px; right: 9px; width: 26px; height: 26px; border-radius: 8px; border: 1px solid transparent; background: transparent; display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; color: #b9c0b0; cursor: pointer; transition: .15s; }
.fr-history-archive:hover { color: #dc3545; background: #fdf0ef; border-color: #f5c9c4; }
.fr-history-archive.restore { color: #295738; }
.fr-history-archive.restore:hover { color: #1f6b3a; background: #eaf6ea; border-color: #c3e0bd; }
.fr-history-empty { text-align: center; padding: 24px 14px; color: #aab4a5; font-size: .8rem; }
.fr-history-empty i { display: block; font-size: 1.6rem; color: #d3d9c8; margin-bottom: 8px; }

.fr-page-shell .fr-export-btn { background: #fff; color: #295738; border: 1px solid #cdd9b5; font-size: .8rem; font-weight: 600; border-radius: 10px; height: 42px; padding: 0 16px; }
.fr-page-shell .fr-export-btn:hover { background: #f0f7f0; border-color: #295738; }
.fr-page-shell .fr-export-btn.fr-export-primary { background: #fff; color: #295738; border-color: #cdd9b5; }
.fr-page-shell .action-btn.fr-action-primary { background: #fff; color: #57694d; border: 1.5px solid #cdd9b5; box-shadow: none; }
.fr-page-shell .action-btn.fr-action-primary:hover { background: #f0f7f0; }

.fr-page-shell .fr-report-body { max-height: calc(100vh - 340px); overflow-y: auto; padding-right: 6px; }
.fr-report-sections { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; }
.fr-report-sections .fr-sec-span { grid-column: 1 / -1; }
.fr-sec { background: #fff; border: 1px solid #e7e3af; border-radius: 14px; padding: 14px 16px; min-width: 0; }
.fr-sec > h3 { color: #295738; font-weight: 800; font-size: .92rem; margin: 0 0 10px; display: flex; align-items: center; gap: 7px; }
.fr-sec > h3 i { color: #7da453; font-size: .95rem; }
.fr-sec p { font-size: .82rem; line-height: 1.7; color: #444; margin: 0 0 8px; overflow-wrap: anywhere; }
.fr-sec p:last-child { margin-bottom: 0; }
.fr-sec ul { margin: 0; padding-left: 18px; }
.fr-sec li { font-size: .82rem; line-height: 1.7; color: #444; margin-bottom: 4px; }
.fr-sec .fr-sec-empty { color: #a5ada0; font-style: italic; font-size: .78rem; margin: 0; }
.fr-sec-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 10px; }
.fr-sec-metric { background: #f9faf5; border: 1px solid #e7e3af; border-radius: 10px; padding: 9px 11px; }
.fr-sec-metric .lbl { font-size: .62rem; font-weight: 700; color: #8a9a8a; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 3px; }
.fr-sec-metric .val { font-size: .95rem; font-weight: 800; color: #295738; line-height: 1.2; }
.fr-sec-metric.exp .val { color: #c62828; }
.fr-sec-metric.net .val { color: #1b7a3a; }
.fr-sec-metric.mgn .val { color: #0d47a1; }
.fr-sec-chart { position: relative; height: 190px; margin: 2px 0 12px; }
.fr-mini-row { margin-bottom: 9px; }
.fr-mini-row:last-child { margin-bottom: 0; }
.fr-mini-top { display: flex; justify-content: space-between; gap: 10px; font-size: .76rem; margin-bottom: 4px; }
.fr-mini-top .nm { color: #444; font-weight: 600; }
.fr-mini-top .pc { color: #295738; font-weight: 700; white-space: nowrap; }
.fr-mini-bar { height: 7px; background: #eef1e6; border-radius: 999px; overflow: hidden; }
.fr-mini-bar > i { display: block; height: 100%; background: linear-gradient(90deg, #295738, #7da453); border-radius: 999px; }

@media (max-width: 768px) {
    .fin-main-grid {
        grid-template-columns: 1fr;
    }
    .fin-report-grid {
        grid-template-columns: 1fr;
    }
    .fin-modal-grid {
        grid-template-columns: 1fr;
    }
    .fr-summary {
        grid-template-columns: repeat(2, 1fr);
    }
    .fr-title {
        font-size: 1.3rem;
    }
}

@media (max-width: 992px) {
    .fr-report-sections { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .fin-main-grid {
        --fr-panel-height: auto;
    }
    #financialReportContent {
        height: auto;
    }
    #financialReportContent .fr-report .fr-report-body {
        max-height: none;
        overflow: visible;
        padding-right: 0;
    }
    .fr-page-shell .fr-sidebar-list,
    #reportsListContainer.fr-sidebar-list {
        height: auto;
        max-height: none;
        overflow-y: visible;
    }
}
</style>
<div id="auditUser" style="display:none;"></div>
<div class="fin-main-grid">

    <div id="reportsListContainer" class="fr-sidebar-list"></div>

    <div id="financialReportContent">
        <div class="fr-placeholder">
            <span class="fr-placeholder-icon"><i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i></span>
            <h3>Your financial report workspace</h3>
            <p>Select a saved report or generate a category report to get started.</p>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
let chartInstance = null;
let loadedReports = [];
let showingArchived = false;
let expenseChartInstance = null;
let currentReportText = "";
let currentReportId = 0;
let currentReportParsed = null;
let currentReportHasSnapshot = false;

function getFilterParams() {
    const f = {};
    const form = document.getElementById('financialFilter');
    for (const key of ['from', 'to', 'category', 'search', 'driver', 'bus']) {
        const field = form && form.elements.namedItem(key);
        if (field && field.value) f[key] = field.value;
    }
    return f;
}

function hasActiveFilters() {
    const f = getFilterParams();
    return "from" in f || "to" in f || "category" in f || "search" in f;
}

function buildFilterQuery() {
    const f = getFilterParams();
    const p = new URLSearchParams();
    if (f.from) p.append("from", f.from);
    if (f.to) p.append("to", f.to);
    if (f.category) p.append("category", f.category);
    if (f.search) p.append("search", f.search);
    if (f.driver) p.append("driver", f.driver);
    if (f.bus) p.append("bus", f.bus);
    return p.toString();
}

async function loadAIReports() {
    try {
        const r = await fetch("/finance/api/get_ai_reports.php");
        const d = await r.json();
        if (!d.success || !Array.isArray(d.reports)) throw Error("No reports");
        loadedReports = d.reports;
        renderReportCards(d.reports);
        if (d.reports.length) loadReportContent(d.reports[0]);
    } catch (e) {
        console.error(e);
    }
}

function renderReportCards(reports) {
    const c = document.getElementById("financialReportContent");
    if (!c) return;
    let html =
        '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">';
    reports.forEach(function (r) {
        const title = r.report_title || r.title || "Report";
        const type = r.report_type || "Financial";
        const date = r.generated_at || "";
        const summary = r.summary || "";
        html +=
            "<div onclick='loadReportContent(" +
            JSON.stringify(r).replace(/'/g, "&#39;") +
            ')\' style="background:#fff;border-radius:16px;border:2px solid #e7e3af;padding:18px;cursor:pointer;transition:0.3s;" onmouseover="this.style.borderColor=\'#295738\'" onmouseout="this.style.borderColor=\'#e7e3af\'">';
        html +=
            '<div style="background:#edf7ee;color:#05590c;padding:4px 12px;border-radius:999px;font-size:0.75rem;font-weight:700;display:inline-block;margin-bottom:10px;">' +
            type +
            "</div>";
        html += '<div style="font-weight:700;color:#05590c;margin-bottom:8px;">' + title + "</div>";
        html += '<div style="color:#888;font-size:0.8rem;">' + date + "</div>";
        html += "</div>";
    });
    html += "</div>";
    c.innerHTML = html;
}

function loadReportContent(report) {
    if (typeof report === "number" || typeof report === "string") {
        fetch("/finance/api/get_ai_report_details.php?id=" + report)
            .then(function (r) {
                return r.json();
            })
            .then(function (d) {
                renderFinancialReport(d);
            });
        return;
    }
    renderFinancialReport({ success: true, report: report });
}

function renderFinancialReport(data) {
    const container = document.getElementById("financialReportContent");
    if (!container) return;
    let raw = {};
    if (data.report) raw = data.report;
    else raw = data;
    let parsed = {};
    try {
        parsed =
            typeof raw.report_content === "string"
                ? JSON.parse(raw.report_content)
                : raw.report_content || {};
    } catch (e) {}
    const report = { ...raw, ...parsed };
    const fin = report.financial_data || {};
    const title = (report.title || report.report_title || "").toLowerCase();

    let filteredEvidence = {};
    if (title.includes("expense") || title.includes("cost")) {
        filteredEvidence = { expense_categories: [], cost_per_bus: [] };
    } else if (title.includes("revenue")) {
        filteredEvidence = { remittance_logs: [] };
    }

    container.innerHTML =
        '<div class="fin-report-grid">' +
        "<div>" +
        renderHeader(report) +
        renderStatsGrid(report, fin) +
        renderChartCard() +
        renderSummaryCard(report) +
        renderAnalysis(report.analysis || []) +
        "</div>" +
        "<div>" +
        renderBulletCard("Insights", report.insights || [], "#05590c") +
        renderBulletCard("Risks", report.risks || [], "#c62828") +
        renderBulletCard("Recommendations", report.recommendations || [], "#0d47a1") +
        "</div>" +
        "</div>";
    renderSmartChart(report, fin);
}

function renderHeader(r) {
    return (
        '<div style="background:#fff;border-radius:16px;border:2px solid #e7e3af;padding:18px;margin-bottom:16px;"><div style="color:#888;font-weight:700;margin-bottom:8px;">' +
        (r.category || "Financial") +
        '</div><h2 style="color:#05590c;font-weight:900;margin:0 0 8px 0;">' +
        cleanText(r.title || r.report_title || "Report") +
        '</h2><div style="color:#666;font-size:0.85rem;"><strong>Generated:</strong> ' +
        (r.generated_at || "-") +
        "</div></div>"
    );
}

function renderStatsGrid(report, data) {
    const stats = [
        ["Revenue", formatCurrency(data.total_revenue)],
        ["Expenses", formatCurrency(data.total_expenses)],
        ["Net Profit", formatCurrency(data.net_profit || data.total_revenue - data.total_expenses)],
    ];
    return (
        '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px;">' +
        stats
            .map(function (s) {
                return (
                    '<div style="background:#fff;border-radius:12px;border:2px solid #e7e3af;padding:14px;"><div style="color:#777;font-weight:700;margin-bottom:6px;font-size:0.85rem;">' +
                    s[0] +
                    '</div><div style="font-size:1.5rem;font-weight:900;color:#05590c;">' +
                    s[1] +
                    "</div></div>"
                );
            })
            .join("") +
        "</div>"
    );
}

function renderChartCard() {
    return '<div style="background:#fff;border-radius:16px;border:2px solid #e7e3af;padding:18px;margin-bottom:16px;"><h4 style="color:#05590c;font-weight:800;margin:0 0 16px 0;">Financial Overview</h4><div style="position:relative;height:320px;"><canvas id="financialChart"></canvas></div></div>';
}

function renderSummaryCard(r) {
    return (
        '<div style="background:#fff;border-radius:16px;border:2px solid #e7e3af;padding:18px;margin-bottom:16px;"><h4 style="color:#05590c;font-weight:800;margin:0 0 12px 0;">Summary</h4><div style="color:#444;line-height:1.8;">' +
        cleanText(r.summary || "") +
        "</div></div>"
    );
}

function renderAnalysis(paragraphs) {
    if (!paragraphs.length) return "";
    return (
        '<div style="background:#fff;border-radius:16px;border:2px solid #e7e3af;padding:18px;margin-bottom:16px;"><h4 style="color:#05590c;font-weight:800;margin:0 0 16px 0;">Analysis</h4>' +
        paragraphs
            .map(function (p) {
                return '<p style="line-height:1.8;color:#444;">' + cleanText(p) + "</p>";
            })
            .join("") +
        "</div>"
    );
}

function renderBulletCard(title, items, color) {
    if (!items.length) return "";
    return (
        '<div style="background:#fff;border-radius:16px;border:2px solid #e7e3af;padding:18px;margin-bottom:16px;"><h4 style="color:' +
        color +
        ';font-weight:800;margin:0 0 12px 0;">' +
        title +
        '</h4><ul style="margin:0;padding-left:18px;line-height:1.8;color:#444;">' +
        items
            .map(function (i) {
                return '<li style="margin-bottom:6px;">' + cleanText(i) + "</li>";
            })
            .join("") +
        "</ul></div>"
    );
}

function renderSmartChart(report, data) {
    if (typeof Chart === "undefined") return;
    const canvas = document.getElementById("financialChart");
    if (!canvas) return;
    if (chartInstance) chartInstance.destroy();
    const title = (report.title || "").toLowerCase();
    let labels = ["Revenue", "Expenses", "Net Profit"];
    let values = [
        data.total_revenue || 0,
        data.total_expenses || 0,
        data.net_profit || data.total_revenue - data.total_expenses || 0,
    ];
    if (title.includes("expense") || title.includes("cost")) {
        const fc = data.fixed_costs || {};
        labels = ["Fuel", "Amortization", "Insurance", "GPS", "OPTR", "Toll", "Other"];
        values = [
            data.fuel_cost || 0,
            fc.amortization || 0,
            fc.insurance || 0,
            fc.gps || 0,
            fc.optr || 0,
            data.toll_fees || 0,
            data.other_cost || 0,
        ];
    }
    chartInstance = new Chart(canvas.getContext("2d"), {
        type: "bar",
        data: {
            labels: labels,
            datasets: [
                {
                    label: "Amount",
                    data: values,
                    backgroundColor: ["#295738", "#7da453", "#b6bb5f", "#e3eabb"],
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    });
}

function renderExpenseChart(data) {
    const canvas = document.getElementById("expenseChart");
    if (!canvas) return;
    if (expenseChartInstance) expenseChartInstance.destroy();
    const catMap = {};
    (data.expenses || []).forEach(function (e) {
        const cat = e.category || "Other";
        catMap[cat] = (catMap[cat] || 0) + Number(e.amount);
    });
    const labels = Object.keys(catMap);
    const values = Object.values(catMap);
    if (!labels.length) return;
    expenseChartInstance = new Chart(canvas.getContext("2d"), {
        type: "doughnut",
        data: {
            labels: labels,
            datasets: [
                {
                    data: values,
                    backgroundColor: [
                        "#295738",
                        "#7da453",
                        "#b6bb5f",
                        "#e3eabb",
                        "#ffde59",
                        "#d4a373",
                    ],
                    borderWidth: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: "right",
                    labels: { font: { size: 11, family: "Poppins" }, padding: 12 },
                },
            },
        },
    });
}

async function loadFilteredExpenses() {
    const q = buildFilterQuery();
    if (!q) return;
    try {
        const r = await fetch(
            "/finance/api/financial_reports_api.php?action=get_filtered_expenses&" + q,
        );
        const d = await r.json();
        if (!d.success) return;
        const c = document.getElementById("financialReportContent");
        if (!c) return;
        let html =
            '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;align-items:start;">';
        html +=
            '<div style="background:#fff;border-radius:16px;border:2px solid #e7e3af;padding:20px;">';
        html +=
            '<h4 style="color:#295738;font-weight:800;margin:0 0 16px 0;font-size:1rem;">Filtered Expenses</h4>';
        html +=
            '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px;">';
        html +=
            '<div style="background:#faf9e5;border-radius:10px;padding:14px;"><div style="font-size:0.78rem;color:#888;margin-bottom:2px;">Total</div><div style="font-size:1.3rem;font-weight:800;color:#295738;">₱' +
            Number(d.total).toLocaleString() +
            "</div></div>";
        html +=
            '<div style="background:#faf9e5;border-radius:10px;padding:14px;"><div style="font-size:0.78rem;color:#888;margin-bottom:2px;">Entries</div><div style="font-size:1.3rem;font-weight:800;color:#295738;">' +
            d.count +
            "</div></div>";
        html += "</div>";
        if (d.expenses.length) {
            html +=
                '<div style="max-height:360px;overflow:auto;border:1px solid #f1f1f1;border-radius:8px;">';
            html += '<table style="width:100%;font-size:0.82rem;border-collapse:collapse;">';
            html +=
                '<thead><tr style="background:#faf9e5;position:sticky;top:0;"><th style="padding:8px 10px;text-align:left;font-weight:700;color:#295738;">Category</th><th style="padding:8px 10px;text-align:right;font-weight:700;color:#295738;">Amount</th><th style="padding:8px 10px;text-align:left;font-weight:700;color:#295738;">Date</th></tr></thead><tbody>';
            d.expenses.forEach(function (e) {
                html +=
                    '<tr style="border-bottom:1px solid #f5f5f5;"><td style="padding:8px 10px;"><span style="background:#edf7ee;color:#05590c;padding:2px 8px;border-radius:999px;font-size:0.75rem;font-weight:600;">' +
                    (e.category || "-") +
                    '</span></td><td style="padding:8px 10px;text-align:right;font-weight:700;">₱' +
                    Number(e.amount).toLocaleString() +
                    '</td><td style="padding:8px 10px;color:#666;">' +
                    (e.expense_date || "-") +
                    "</td></tr>";
            });
            html += "</tbody></table></div>";
        } else {
            html +=
                '<div style="text-align:center;padding:40px 20px;color:#bbb;"><i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:12px;color:#ddd;"></i><div style="font-weight:600;font-size:0.95rem;color:#999;margin-bottom:4px;">No expenses found</div><div style="font-size:0.82rem;color:#bbb;">Try adjusting your filter or date range</div></div>';
        }
        html += "</div>";
        html +=
            '<div style="background:#fff;border-radius:16px;border:2px solid #e7e3af;padding:20px;"><h4 style="color:#295738;font-weight:800;margin:0 0 12px 0;font-size:1rem;">Expense Breakdown</h4>' +
            (d.expenses.length
                ? '<div style="position:relative;height:280px;"><canvas id="expenseChart"></canvas></div>'
                : '<div style="text-align:center;padding:40px;color:#ddd;font-size:2rem;"><i class="bi bi-pie-chart"></i><div style="font-size:0.85rem;color:#bbb;margin-top:8px;font-weight:500;">No data to display</div></div>') +
            "</div>";
        html += "</div>";
        c.innerHTML = html;
        renderExpenseChart(d);
        const s = document.getElementById("reportsListContainer");
        if (s) {
            s.innerHTML =
                '<h6 style="color:#295738;font-weight:800;margin:0 0 8px 0;font-size:0.85rem;">Filter Summary</h6><div style="font-size:0.8rem;color:#666;line-height:1.8;">' +
                (d.filter_category
                    ? '<div><span style="font-weight:600;">Category:</span> ' +
                      d.filter_category +
                      "</div>"
                    : "") +
                (d.filter_from
                    ? '<div><span style="font-weight:600;">From:</span> ' + d.filter_from + "</div>"
                    : "") +
                (d.filter_to
                    ? '<div><span style="font-weight:600;">To:</span> ' + d.filter_to + "</div>"
                    : "") +
                '<div style="margin-top:8px;padding-top:8px;border-top:1px solid #f1f1f1;"><span style="font-weight:600;">Total:</span> ₱' +
                Number(d.total).toLocaleString() +
                '</div><div><span style="font-weight:600;">Entries:</span> ' +
                d.count +
                "</div></div>";
        }
    } catch (e) {}
}

function frEsc(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
}

function reportPeriodLabel(from, to) {
    const fmt = (s) => {
        const t = String(s || "").slice(0, 10);
        if (!t) return "";
        const d = new Date(t + "T00:00:00");
        return isNaN(d.getTime()) ? t : d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
    };
    const a = fmt(from);
    const b = fmt(to);
    if (!a && !b) return "";
    if (a && b && String(from).slice(0, 10) === String(to).slice(0, 10)) return a;
    return (a || "Start") + " – " + (b || "Today");
}

function isReportHeading(line) {
    if (line.length >= 90) return false;
    if (/[.!?₱]/.test(line)) return false;
    return /^(?:executive|overview|summary|introduction|key|financial|cash|profit|revenue|income|fare|collection|payment|expense|cost|operating|fleet|bus|driver|route|risk|trend|insight|recommendation|conclusion)/i.test(line);
}

function classifyReportHeading(line) {
    const s = line.toLowerCase();
    if (/recommend|action|conclusion/.test(s)) return "recommendations";
    if (/risk|trend|insight|warning|concern|challenge/.test(s)) return "trends";
    if (/bus|fleet|driver|route|trip/.test(s)) return "bus";
    if (/expense|cost|operating|spending/.test(s)) return "expenses";
    if (/revenue|income|fare|collection|payment|receivable/.test(s)) return "revenue";
    return "overview";
}

function splitReportSections(text) {
    const buckets = { overview: [], revenue: [], expenses: [], bus: [], trends: [], recommendations: [] };
    let current = "overview";
    String(text || "").split("\n").forEach(function (line) {
        const t = line.trim();
        if (!t) return;
        if (isReportHeading(t)) {
            current = classifyReportHeading(t);
            return;
        }
        buckets[current].push(t);
    });
    return buckets;
}

function reportParagraphs(lines) {
    let html = "";
    let bullets = [];
    const flush = () => {
        if (bullets.length) {
            html += "<ul>" + bullets.map(b => "<li>" + frEsc(b) + "</li>").join("") + "</ul>";
            bullets = [];
        }
    };
    lines.forEach(function (line) {
        const t = String(line).trim();
        if (!t) return;
        if (/^[-•]\s+/.test(t)) {
            bullets.push(t.replace(/^[-•]\s+/, ""));
        } else {
            flush();
            html += "<p>" + frEsc(t) + "</p>";
        }
    });
    flush();
    return html;
}

function frMiniRow(name, value, pct) {
    const pctNum = parseFloat(pct) || 0;
    return (
        '<div class="fr-mini-row"><div class="fr-mini-top"><span class="nm">' +
        frEsc(name) +
        '</span><span class="pc">' +
        frEsc(value) +
        '</span></div><div class="fr-mini-bar"><i style="width:' +
        pctNum +
        '%"></i></div></div>'
    );
}

let frPreviewChartInstance = null;

function parseMoneyValue(value) {
    const n = parseFloat(String(value == null ? "" : value).replace(/[^0-9.\-]/g, ""));
    return isNaN(n) ? 0 : n;
}

function renderPreviewChart(parsed) {
    const canvas = document.getElementById("frPreviewChart");
    if (!canvas || typeof Chart === "undefined") return;
    if (frPreviewChartInstance) {
        try {
            frPreviewChartInstance.destroy();
        } catch (e) {}
        frPreviewChartInstance = null;
    }
    const values = [
        parseMoneyValue(parsed.revenue),
        parseMoneyValue(parsed.expenses),
        parseMoneyValue(parsed.netProfit),
    ];
    if (!values.some((v) => v !== 0)) return;
    frPreviewChartInstance = new Chart(canvas.getContext("2d"), {
        type: "bar",
        data: {
            labels: ["Revenue", "Expenses", "Net Profit"],
            datasets: [
                {
                    label: "Amount",
                    data: values,
                    backgroundColor: ["#295738", "#c62828", "#7da453"],
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    });
}

function renderReportSections(parsed, text) {
    const buckets = splitReportSections(text);
    const metrics = [];
    if (parsed.revenue) metrics.push(["rev", "Revenue", parsed.revenue]);
    if (parsed.expenses) metrics.push(["exp", "Expenses", parsed.expenses]);
    if (parsed.netProfit) metrics.push(["net", "Net Profit", parsed.netProfit]);
    if (parsed.margin) metrics.push(["mgn", "Margin", parsed.margin]);
    const metricsHtml = metrics.length
        ? '<div class="fr-sec-metrics">' +
          metrics
              .map(
                  (m) =>
                      '<div class="fr-sec-metric ' +
                      m[0] +
                      '"><div class="lbl">' +
                      m[1] +
                      '</div><div class="val">' +
                      frEsc(m[2]) +
                      "</div></div>",
              )
              .join("") +
          "</div>"
        : "";
    const chartHtml = metrics.length
        ? '<div class="fr-sec-chart"><canvas id="frPreviewChart" aria-label="Revenue, expenses, and net profit chart"></canvas></div>'
        : "";
    const payments = parsed.payments.length
        ? parsed.payments
              .map((p) => frMiniRow(p.method, p.amt + (p.txns ? " · " + p.txns + " txns" : "") + " · " + p.pct, p.pct))
              .join("")
        : "";
    const categories = parsed.expenseCats.length
        ? parsed.expenseCats.map((c) => frMiniRow(c.cat, c.amt + " · " + c.pct, c.pct)).join("")
        : "";
    const sec = (icon, title, body, span) =>
        '<section class="fr-sec' +
        (span ? " fr-sec-span" : "") +
        '"><h3><i class="bi ' +
        icon +
        '"></i>' +
        title +
        "</h3>" +
        (body || '<p class="fr-sec-empty">No details were recorded for this section.</p>') +
        "</section>";

    return (
        '<div class="fr-report-body"><div class="fr-report-sections">' +
        sec("bi-clipboard-data", "Overview", metricsHtml + chartHtml + reportParagraphs(buckets.overview), true) +
        sec("bi-cash-coin", "Revenue", reportParagraphs(buckets.revenue) + payments) +
        sec("bi-wallet2", "Expenses", reportParagraphs(buckets.expenses) + categories) +
        sec("bi-bus-front", "Bus-Specific Financial Activity", reportParagraphs(buckets.bus)) +
        sec("bi-graph-up-arrow", "Trends and Risks", reportParagraphs(buckets.trends)) +
        sec("bi-lightbulb", "Recommendations", reportParagraphs(buckets.recommendations), true) +
        "</div></div>"
    );
}

async function loadReportsList(filterQuery, archived, autoLoadFirst) {
    try {
        let url = "/finance/api/financial_reports_api.php?action=get_reports";
        const params = new URLSearchParams();
        if (filterQuery) {
            new URLSearchParams(filterQuery).forEach((value, key) => params.append(key, value));
        }
        if (archived) params.append("archived", "1");
        const qs = params.toString();
        if (qs) url += "&" + qs;
        const r = await fetch(url);
        const d = await r.json();
        const container = document.getElementById("reportsListContainer");
        if (!container) return;

        if (autoLoadFirst && d.reports && d.reports.length) {
            loadReportById(d.reports[0].id, false);
        } else if (autoLoadFirst) {
            const mc = document.getElementById("financialReportContent");
            if (mc)
                mc.innerHTML =
                    '<div class="fr-placeholder"><span class="fr-placeholder-icon"><i class="bi bi-inbox" aria-hidden="true"></i></span><h3>No saved reports yet</h3><p>Generate a report to review its category-specific analysis and financial records.</p></div>';
        }

        let html =
            '<div class="fr-sidebar-head"><h6><i class="bi bi-clock-history"></i>' +
            (archived ? "Archived Reports" : "Reports History") +
            '</h6><button type="button" class="fr-sidebar-toggle" onclick="toggleArchived()">' +
            (archived ? "Active reports" : "Archived") +
            "</button></div>";

        if (!d.reports || !d.reports.length) {
            html +=
                '<div class="fr-history-empty"><i class="bi bi-inbox"></i>' +
                (archived ? "No archived reports" : "No reports yet") +
                "</div>";
            container.innerHTML = html;
            return;
        }

        html +=
            '<div class="fr-history-count"><i class="bi bi-collection"></i>' +
            d.reports.length +
            " report(s)</div>";
        d.reports.forEach(function (r) {
            const period = reportPeriodLabel(r.period_from, r.period_to);
            html +=
                '<div data-report-id="' +
                r.id +
                '" class="fr-history-item" onclick="loadReportById(' +
                r.id +
                ')">';
            html += '<div class="fr-history-title">' + frEsc(r.title || "Report") + "</div>";
            if (period) {
                html +=
                    '<div><span class="fr-history-period"><i class="bi bi-calendar3"></i>' +
                    frEsc(period) +
                    "</span></div>";
            }
            html +=
                '<div class="fr-history-meta"><i class="bi bi-tag"></i> ' +
                frEsc(r.report_category || "Report") +
                " &middot; " +
                frEsc(formatReportDate(r.generated_at)) +
                "</div>";
            html +=
                '<span class="fr-current-badge" hidden><i class="bi bi-eye-fill"></i>Currently Showing</span>';
            if (archived) {
                html +=
                    '<button type="button" class="fr-history-archive restore" title="Restore report" onclick="event.stopPropagation();unarchiveReport(' +
                    r.id +
                    ')"><i class="bi bi-arrow-counterclockwise"></i></button>';
            } else {
                html +=
                    '<button type="button" class="fr-history-archive" title="Archive report" onclick="event.stopPropagation();archiveReport(' +
                    r.id +
                    ')"><i class="bi bi-archive"></i></button>';
            }
            html += "</div>";
        });
        container.innerHTML = html;
    } catch (e) {}
}

function toggleArchived() {
    showingArchived = !showingArchived;
    const q = buildFilterQuery();
    loadReportsList(q, showingArchived);
}

async function archiveReport(id) {
    await fetch("/finance/api/financial_reports_api.php?action=archive_report&id=" + id);
    loadReportsList(buildFilterQuery(), showingArchived);
}

async function unarchiveReport(id) {
    await fetch("/finance/api/financial_reports_api.php?action=unarchive_report&id=" + id);
    loadReportsList(buildFilterQuery(), showingArchived);
}

async function loadReportById(id, openPreview = false, suppliedReport = null) {
    try {
        let d;
        if (suppliedReport) {
            d = {success: true, report: suppliedReport, has_financial_snapshot: true};
        } else {
            const r = await fetch("/finance/api/financial_reports_api.php?action=get_report&id=" + id);
            d = await window.FinanceFinancialPerformance.readJson(r);
            if (!r.ok || !d.success) throw new Error(d.message || 'Could not open the saved report.');
        }
        const c = document.getElementById("financialReportContent");
        if (!c) return;

        currentReportText = d.report.report_data || "";
        currentReportId = id;
        currentReportHasSnapshot = !!d.has_financial_snapshot;
        const reportTitle = d.report.title || "Report";
        const reportCategory = d.report.report_category || "Financial";
        const reportDate = d.report.generated_at || "";
        const generatedBy = d.report.generated_by || "";
        const generatedByRole = d.report.generated_by_role || "";
        const safeText = cleanText(currentReportText).replace(/</g, "&lt;").replace(/>/g, "&gt;");
        const parsed = parseReportData(safeText);
        currentReportParsed = parsed;

        c.innerHTML =
            '<div class="fr-report">' +
            '<div class="fr-report-head">' +
            '<span class="fr-badge">' +
            frEsc(reportCategory) +
            "</span>" +
            '<h1 class="fr-title">' +
            frEsc(reportTitle) +
            "</h1>" +
            '<div class="fr-meta">Reporting period: <b>' +
            frEsc(reportPeriodLabel(d.period_from, d.period_to) || "All recorded data") +
            "</b></div>" +
            '<div class="fr-meta">Generated: <b>' +
            frEsc(formatReportDate(reportDate)) +
            "</b></div>" +
            (generatedBy
                ? '<div class="fr-meta">Generated by: <b>' +
                  frEsc(generatedBy) +
                  (generatedByRole ? " (" + frEsc(generatedByRole) + ")" : "") +
                  "</b></div>"
                : "") +
            "</div>" +
            '<div class="fr-exports">' +
            '<button type="button" class="fr-export-btn" onclick="downloadReportText()"><i class="bi bi-file-earmark-text"></i> TXT</button>' +
            '<button type="button" class="fr-export-btn" onclick="downloadReportPdf()"><i class="bi bi-file-pdf"></i> PDF</button>' +
            '<button type="button" class="fr-export-btn" onclick="downloadReportExcel()"><i class="bi bi-file-earmark-excel"></i> Excel</button>' +
            (currentReportHasSnapshot ? '<button type="button" class="fr-export-btn" onclick="previewSelectedFinancialReport()"><i class="bi bi-bar-chart"></i> Charts and Financial Records</button>' : '') +
            "</div>" +
            renderReportSections(parsed, currentReportText) +
            "</div>";

        renderPreviewChart(parsed);

        document.querySelectorAll(".fr-history-item").forEach(function (el) {
            el.classList.remove("active-report");
            const badge = el.querySelector(".fr-current-badge");
            if (badge) badge.hidden = true;
        });
        const active = document.querySelector('.fr-history-item[data-report-id="' + id + '"]');
        if (active) {
            active.classList.add("active-report");
            const badge = active.querySelector(".fr-current-badge");
            if (badge) badge.hidden = false;
            active.scrollIntoView({ block: "nearest" });
        }
        if (openPreview) previewSelectedFinancialReport();
    } catch (e) {
        showAlert('error', 'Report unavailable', e.message || 'Could not open the saved report.');
    }
}

function previewSelectedFinancialReport() {
    if (!currentReportId) return;
    if (currentReportHasSnapshot && window.FinanceFinancialPerformance) {
        window.FinanceFinancialPerformance.openSaved(currentReportId);
        return;
    }
    document.getElementById('reportPreviewTitle').textContent = 'Generated Financial Report';
    document.getElementById('reportPreviewContent').textContent = currentReportText;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('reportPreviewModal')).show();
}

function renderReportChart() {
    if (typeof Chart === "undefined") return;
    if (window._frChart) {
        try {
            window._frChart.destroy();
        } catch (e) {}
        window._frChart = null;
    }

    const r = currentReportParsed || {
        payments: [],
        expenseCats: [],
        revenue: "",
        expenses: "",
        netProfit: "",
    };
    const num = function (v) {
        return parseFloat(String(v || "0").replace(/[^\d.\-]/g, "")) || 0;
    };

    const donut = document.getElementById("revDonut");
    if (donut && r.payments && r.payments.length >= 2) {
        window._frChart = new Chart(donut.getContext("2d"), {
            type: "doughnut",
            data: {
                labels: r.payments.map(function (p) {
                    return p.method;
                }),
                datasets: [
                    {
                        data: r.payments.map(function (p) {
                            return num(p.amt);
                        }),
                        backgroundColor: ["#295738", "#7da453", "#b6bb5f", "#e3eabb", "#d4a373"],
                        borderWidth: 2,
                        borderColor: "#ffffff",
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "62%",
                plugins: {
                    legend: {
                        position: "right",
                        labels: {
                            font: { size: 11, family: "Poppins" },
                            padding: 10,
                            boxWidth: 12,
                        },
                    },
                },
            },
        });
        return;
    }

    const canvas = document.getElementById("reportChart");
    if (!canvas) return;

    let data, type, opts;
    if (r.expenseCats && r.expenseCats.length) {
        type = "bar";
        data = {
            labels: r.expenseCats.map(function (c) {
                return c.cat;
            }),
            datasets: [
                {
                    label: "Amount (₱)",
                    data: r.expenseCats.map(function (c) {
                        return num(c.amt);
                    }),
                    backgroundColor: ["#295738", "#7da453", "#b6bb5f", "#e3eabb"],
                    borderRadius: 6,
                },
            ],
        };
        opts = {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { font: { size: 10 } } } },
        };
    } else if (r.revenue || r.expenses) {
        type = "bar";
        data = {
            labels: ["Revenue", "Expenses", "Net Profit"],
            datasets: [
                {
                    data: [num(r.revenue), num(r.expenses), num(r.netProfit)],
                    backgroundColor: ["#295738", "#c62828", "#7da453"],
                    borderRadius: 6,
                },
            ],
        };
        opts = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { font: { size: 10 } } } },
        };
    } else {
        return;
    }

    window._frChart = new Chart(canvas.getContext("2d"), { type: type, data: data, options: opts });
}

function downloadReportText() {
    if (!currentReportText) return;
    const blob = new Blob([currentReportText], { type: "text/plain;charset=utf-8" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "financial_report_" + currentReportId + ".txt";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(a.href);
    if (typeof auditLog === "function") {
        auditLog(
            "Finance",
            "Export",
            "Downloaded report #" + currentReportId + " as TXT",
            "" + currentReportId,
            null,
            { id: currentReportId, format: "TXT" },
        );
    }
}

async function downloadReportPdf() {
    if (!currentReportId) return;
    try {
        await window.FinanceFinancialPerformance.downloadSavedPdf(currentReportId);
    } catch (error) {
        showAlert('error', 'PDF export failed', error.message || 'Could not download this report.');
        return;
    }
    if (typeof auditLog === "function") {
        auditLog(
            "Finance",
            "Export",
            "Downloaded report #" + currentReportId + " as PDF",
            "" + currentReportId,
            null,
            { id: currentReportId, format: "PDF" },
        );
    }
}

function printReport(text) {
    if (!text) return;
    const w = window.open("", "_blank");
    if (!w) {
        alert("Please allow popups for this site to print reports.");
        return;
    }
    const styled = text
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\n{2,}/g, "\n\n")
        .replace(/^(.+?)(?:\n[-=]+\n)?/gm, function (match) {
            return match;
        });

    w.document.write(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Financial Report - RPMVFMPC</title>',
    );
    w.document.write("<style>");
    w.document.write("@page { margin: 20mm 15mm; }");
    w.document.write("* { box-sizing: border-box; }");
    w.document.write(
        'body { font-family: "Segoe UI", Arial, sans-serif; color: #222; max-width: 800px; margin: 0 auto; padding: 0; line-height: 1.7; }',
    );
    w.document.write(
        ".report-header { text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 3px solid #295738; }",
    );
    w.document.write(
        ".report-header .coop-name { font-size: 22px; font-weight: 800; color: #295738; margin: 0; letter-spacing: -0.5px; }",
    );
    w.document.write(
        ".report-header .report-title { font-size: 16px; font-weight: 600; color: #57694d; margin: 4px 0; }",
    );
    w.document.write(
        ".report-header .report-meta { font-size: 11px; color: #888; margin: 2px 0; }",
    );
    w.document.write(
        ".report-header .divider { width: 60px; height: 3px; background: #b6bb5f; margin: 12px auto; border-radius: 2px; }",
    );
    w.document.write(".report-content { font-size: 12px; color: #333; }");
    w.document.write(
        ".report-content h1 { font-size: 18px; color: #295738; margin: 24px 0 12px 0; padding-bottom: 6px; border-bottom: 2px solid #e7e3af; }",
    );
    w.document.write(
        ".report-content h2 { font-size: 15px; color: #57694d; margin: 18px 0 8px 0; }",
    );
    w.document.write(
        ".report-content h3 { font-size: 13px; color: #295738; margin: 14px 0 6px 0; }",
    );
    w.document.write(".report-content p { margin: 6px 0; }");
    w.document.write(
        ".report-content table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 11px; }",
    );
    w.document.write(
        ".report-content td, .report-content th { border: 1px solid #e0e0e0; padding: 6px 10px; text-align: left; }",
    );
    w.document.write(
        ".report-content th { background: #f0f7f0; color: #295738; font-weight: 700; }",
    );
    w.document.write(".report-content tr:nth-child(even) { background: #fafafa; }");
    w.document.write(
        ".report-content ul, .report-content ol { margin: 6px 0; padding-left: 20px; }",
    );
    w.document.write(".report-content li { margin: 3px 0; }");
    w.document.write(".report-content strong { color: #295738; }");
    w.document.write(
        ".report-footer { margin-top: 30px; padding-top: 15px; border-top: 2px solid #e7e3af; font-size: 10px; color: #999; text-align: center; }",
    );
    w.document.write("@media print { body { padding: 0; } }");
    w.document.write("</style></head><body>");

    w.document.write('<div class="report-header">');
    w.document.write('<div class="coop-name">RPMVFMPC</div>');
    w.document.write('<div class="report-title">Financial Performance Report</div>');
    w.document.write('<div class="divider"></div>');
    w.document.write(
        '<div class="report-meta">Rizal Public Market Vendors and Farmers Multi Purpose Cooperative</div>',
    );
    w.document.write(
        '<div class="report-meta">Generated: ' +
            new Date().toLocaleDateString("en-US", {
                year: "numeric",
                month: "long",
                day: "numeric",
            }) +
            "</div>",
    );
    w.document.write("</div>");

    w.document.write('<div class="report-content">');

    const lines = styled.split("\n");
    let inTable = false;
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const trimmed = line.trim();

        if (!trimmed) {
            w.document.write("<br>");
            continue;
        }

        const nextLine = lines[i + 1] || "";
        const nextTrimmed = nextLine.trim();

        if (trimmed.endsWith(":") && trimmed.length < 40) {
            w.document.write("<h2>" + trimmed + "</h2>");
            continue;
        }

        if (
            trimmed.length < 40 &&
            !trimmed.includes("  ") &&
            !trimmed.startsWith("-") &&
            !trimmed.startsWith("•") &&
            !trimmed.match(/^\d+\./)
        ) {
            if (nextTrimmed.match(/^[-]{3,}$/) || nextTrimmed.match(/^[=]{3,}$/)) {
                w.document.write("<h1>" + trimmed + "</h1>");
                i++;
                continue;
            }
            if (trimmed.includes("|")) {
                if (!inTable) {
                    w.document.write("<table>");
                    inTable = true;
                }
                const cells = trimmed
                    .split("|")
                    .map((c) => c.trim())
                    .filter((c) => c);
                if (cells.length > 1) {
                    w.document.write("<tr><th>" + cells.join("</th><th>") + "</th></tr>");
                }
                continue;
            }
        }

        if (trimmed.includes("|")) {
            if (!inTable) {
                w.document.write("<table>");
                inTable = true;
            }
            const cells = trimmed
                .split("|")
                .map((c) => c.trim())
                .filter((c) => c);
            if (cells.length > 1) {
                w.document.write("<tr><td>" + cells.join("</td><td>") + "</td></tr>");
            }
            continue;
        } else if (inTable) {
            w.document.write("</table>");
            inTable = false;
        }

        if (trimmed.match(/^\d+\.\s/)) {
            w.document.write("<p><strong>" + trimmed.replace(/^(\d+\.\s)/, "$1") + "</strong></p>");
            continue;
        }

        if (trimmed.startsWith("- ") || trimmed.startsWith("• ")) {
            w.document.write("<li>" + trimmed.substring(2) + "</li>");
            continue;
        }

        if (trimmed.length > 40) {
            w.document.write("<p>" + trimmed + "</p>");
        } else {
            w.document.write('<p style="font-weight:600;">' + trimmed + "</p>");
        }
    }
    if (inTable) w.document.write("</table>");

    w.document.write("</div>");

    w.document.write('<div class="report-footer">');
    w.document.write("RPMVFMPC &mdash; Financial Report &mdash; Confidential");
    w.document.write("</div>");

    w.document.write("</body></html>");
    w.document.close();
    setTimeout(function () {
        w.print();
    }, 800);
}

function downloadReportExcel() {
    if (!currentReportId) return;
    window.open(
        "/finance/api/financial_reports_api.php?action=export_report_excel&id=" + currentReportId,
        "_blank",
    );
    if (typeof auditLog === "function") {
        auditLog(
            "Finance",
            "Export",
            "Downloaded report #" + currentReportId + " as Excel",
            "" + currentReportId,
            null,
            { id: currentReportId, format: "Excel" },
        );
    }
}

function cleanText(t) {
    if (!t) return "";
    return String(t)
        .replace(/[*#_~`>]+/g, "")
        .replace(/^\s*[-=]{3,}\s*$/gm, "")
        .replace(/\|/g, "")
        .replace(/\[.*?\]\(.*?\)/g, "")
        .replace(/\n{3,}/g, "\n\n")
        .trim();
}

function formatReportContent(text) {
    if (!text) return "";
    var lines = text.split("\n");
    var html = "";
    var i = 0;

    function isHeader(line) {
        return (
            /^[A-Z][A-Za-z\s]{2,60}$/.test(line.trim()) &&
            line.trim().length < 60 &&
            !line.trim().includes("  ")
        );
    }

    function isTableRow(line) {
        var parts = line.trim().split(/\s{3,}/);
        return parts.length >= 2;
    }

    while (i < lines.length) {
        var line = lines[i];
        var trimmed = line.trim();
        var nextLine = i + 1 < lines.length ? lines[i + 1].trim() : "";

        if (!trimmed) {
            i++;
            continue;
        }

        if (
            isHeader(line) &&
            (!nextLine || isHeader(lines[i + 1]) || nextLine === "" || isTableRow(lines[i + 1]))
        ) {
            html +=
                '<h3 style="color:#295738;font-weight:800;font-size:1.05rem;margin:20px 0 8px 0;padding-bottom:4px;border-bottom:2px solid #e7e3af;">' +
                trimmed +
                "</h3>";
            i++;
            continue;
        }

        if (isTableRow(line)) {
            html +=
                '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;margin:8px 0;font-size:0.85rem;">';
            while (i < lines.length) {
                var cur = lines[i].trim();
                if (!cur) {
                    i++;
                    continue;
                }
                var cells = cur.split(/\s{3,}/);
                if (cells.length < 2) break;
                var isHead = false;
                if (
                    html.indexOf("<tr") === -1 ||
                    /^(Revenue|Expense|Category|Amount|Bus|Driver|Route|Passenger|Trip|Financial|KPI|Metric|Total)/.test(
                        cells[0],
                    )
                ) {
                    html += '<tr style="background:#faf9e5;">';
                    for (var c = 0; c < cells.length; c++) {
                        html +=
                            '<td style="padding:6px 10px;border:1px solid #e7e3af;font-weight:600;color:#295738;">' +
                            cells[c].trim() +
                            "</td>";
                    }
                } else {
                    html += "<tr>";
                    for (var c = 0; c < cells.length; c++) {
                        html +=
                            '<td style="padding:6px 10px;border:1px solid #e7e3af;color:#444;">' +
                            cells[c].trim() +
                            "</td>";
                    }
                }
                html += "</tr>";
                i++;
            }
            html += "</table></div>";
            continue;
        }

        if (/^[-•]\s/.test(trimmed)) {
            html +=
                '<li style="margin:4px 0 4px 18px;color:#444;line-height:1.6;">' +
                trimmed.replace(/^[-•]\s+/, "") +
                "</li>";
            i++;
            continue;
        }

        if (/^\d+[.)]\s/.test(trimmed)) {
            html +=
                '<p style="margin:6px 0;color:#444;line-height:1.6;padding-left:18px;"><strong>' +
                trimmed.replace(/^(\d+[.)]\s)/, "$1") +
                "</strong></p>";
            i++;
            continue;
        }

        html += '<p style="margin:6px 0;color:#444;line-height:1.7;">' + trimmed + "</p>";
        i++;
    }

    return html;
}

function formatReportDate(s) {
    if (!s) return "";
    var d = new Date(String(s).replace(" ", "T"));
    if (isNaN(d.getTime())) return s;
    var date = d.toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" });
    var time = d.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit" });
    return date + " • " + time;
}

function parseReportData(text) {
    var r = { revenue: "", expenses: "", netProfit: "", margin: "", expenseCats: [], payments: [] };
    var lines = (text || "").split("\n");
    for (var i = 0; i < lines.length; i++) {
        var line = lines[i].trim();
        var m;
        if ((m = line.match(/Total Revenue[:\s]*₱?\s*([\d,]+(?:\.\d{1,2})?)/i)))
            r.revenue = "₱" + m[1];
        if ((m = line.match(/Total Expenses?[:\s]*₱?\s*([\d,]+(?:\.\d{1,2})?)/i)))
            r.expenses = "₱" + m[1];
        if ((m = line.match(/Net (?:Profit|Income)[:\s]*₱?\s*([-\d,]+(?:\.\d{1,2})?)/i)))
            r.netProfit = "₱" + m[1];
        if ((m = line.match(/Profit Margin[:\s]*([\d.]+%)/i))) r.margin = m[1];
        if ((m = line.match(/^(.+?)\s{2,}(₱[\d,]+\.\d{2})\s{2,}([\d.]+%)/))) {
            r.expenseCats.push({ cat: m[1].trim(), amt: m[2], pct: m[3] });
        } else if ((m = line.match(/^(.+?)\s*[:\-]?\s*(₱[\d,]+\.\d{2})\s*\(([\d.]+%)\)/))) {
            r.expenseCats.push({ cat: m[1].trim(), amt: m[2], pct: m[3] });
        }
        if (
            (m = line.match(
                /^(Cash|GCash|Gcash|Other|Card|Online|Cash on Hand)\b[\s:/-]*₱?\s*([\d,]+(?:\.\d{2})?)\s{2,}([\d,]+)\s*transactions?\s{2,}([\d.]+%)/i,
            ))
        ) {
            r.payments.push({
                method: m[1].trim(),
                amt: "₱" + m[2],
                txns: m[3].replace(/,/g, ""),
                pct: m[4],
            });
        } else if (
            (m = line.match(
                /^(Cash|GCash|Gcash|Other|Card|Online|Cash on Hand)\b[\s:/-]*(₱[\d,]+(?:\.\d{2})?)\s*\(([\d,]+)\s*transactions?,?\s*([\d.]+%)\)/i,
            ))
        ) {
            r.payments.push({
                method: m[1].trim(),
                amt: m[2],
                txns: m[3].replace(/,/g, ""),
                pct: m[4],
            });
        }
    }
    return r;
}

function frMetric(cls, lbl, val, exp, neg) {
    return (
        '<div class="fr-metric ' +
        cls +
        '">' +
        '<div class="lbl">' +
        lbl +
        "</div>" +
        '<div class="val"' +
        (neg ? ' style="color:#c62828;"' : "") +
        ">" +
        val +
        "</div>" +
        '<div class="exp">' +
        exp +
        "</div>" +
        "</div>"
    );
}

function extractReportSummary(text) {
    if (!text) return "";
    var r = parseReportData(text);
    var html = "";

    if (r.revenue || r.expenses || r.netProfit || r.margin) {
        html += '<div class="fr-summary">';
        if (r.revenue) html += frMetric("rev", "Revenue", r.revenue, "Total income");
        if (r.expenses) html += frMetric("exp", "Expenses", r.expenses, "Total expenses");
        if (r.netProfit) {
            var neg = /^₱-/.test(r.netProfit) || r.netProfit === "₱0" || r.netProfit === "₱0.00";
            html += frMetric("net", "Net Profit", r.netProfit, "Revenue &minus; expenses", neg);
        }
        if (r.margin) html += frMetric("mgn", "Margin", r.margin, "Net profit &divide; revenue");
        html += "</div>";
    }

    if (r.payments.length >= 2) {
        html +=
            '<div class="fr-section"><h3><i class="bi bi-pie-chart me-1"></i>Revenue &amp; Payment Breakdown</h3>';
        html += '<div class="fr-pay-donut"><canvas id="revDonut"></canvas></div>';
        r.payments.forEach(function (p) {
            var pctNum = parseFloat(p.pct) || 0;
            html +=
                '<div class="fr-progress-row"><div class="fr-progress-top"><span class="nm">' +
                p.method +
                '</span><span class="pc">' +
                p.amt +
                (p.txns ? " &middot; " + p.txns + " txns" : "") +
                " &middot; " +
                p.pct +
                '</span></div><div class="fr-bar"><i style="width:' +
                pctNum +
                '%"></i></div></div>';
        });
        html += "</div>";
    }

    if (r.expenseCats.length) {
        html +=
            '<div class="fr-section"><h3><i class="bi bi-bar-chart me-1"></i>Expense Breakdown</h3>';
        r.expenseCats.forEach(function (c) {
            var pctNum = parseFloat(c.pct) || 0;
            html +=
                '<div class="fr-progress-row"><div class="fr-progress-top"><span class="nm">' +
                c.cat +
                '</span><span class="pc">' +
                c.amt +
                " &middot; " +
                c.pct +
                '</span></div><div class="fr-bar"><i style="width:' +
                pctNum +
                '%"></i></div></div>';
        });
        html += "</div>";
    }

    return html;
}

function formatCurrency(v) {
    return "₱" + Number(v || 0).toLocaleString();
}

function auditLog(module, act, description, recordId, prev, newVals, status) {
    var fd = new FormData();
    fd.append("module", module);
    fd.append("log_action", act);
    fd.append("description", description || "");
    fd.append("record_id", recordId || "");
    fd.append("previous_values", prev ? JSON.stringify(prev) : "");
    fd.append("new_values", newVals ? JSON.stringify(newVals) : "");
    fd.append("status", status || "Success");
    fetch("/finance/api/exportandaudits_api.php?action=audit_trail_log", {
        method: "POST",
        body: fd,
        keepalive: true,
    }).catch(function () {});
}

document.addEventListener("DOMContentLoaded", function () {
    if (hasActiveFilters()) {
        loadReportsList(buildFilterQuery(), false, true);
    } else {
        loadReportsList("", false, true);
    }
});
</script>

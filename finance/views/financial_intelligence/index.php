<?php if (!defined('APP_LOADED')) exit('No direct script access allowed'); ?>
<?php header('Cache-Control: no-store, no-cache, must-revalidate'); ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">

<?php require_once __DIR__ . '/../layout/navbarfinance.php'; ?>

<style>
:root {
    --fi-green: #05590c;
    --fi-green-2: #198754;
    --fi-green-3: #81c784;
    --fi-gold: #d9a441;
    --fi-red: #c62828;
    --fi-cream: #faf9e5;
    --fi-cream-2: #fffdf8;
    --fi-border: #cdd9b5;
    --fi-ink: #2e4836;
    --fi-muted: #8a9a8a;
}
.fi-shell {
    font-family: 'Poppins', sans-serif;
    background: #eceed9;
    padding: 16px;
}
.fi-page {
    background: #fef8f3;
    border: 1px solid #aaa8b1;
    border-radius: 24px;
    padding: 14px;
    box-shadow: 0 12px 32px rgba(46, 72, 54, 0.08);
}
.fi-card {
    background: var(--fi-cream-2);
    border: 1px solid var(--fi-border);
    border-radius: 16px;
    padding: 14px;
    margin-bottom: 12px;
    box-shadow: 0 8px 24px rgba(46, 72, 54, 0.05);
}
.fi-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap; }
.fi-page-head { display: flex; align-items: flex-start; gap: 12px; }
.fi-title { font-size: 1.35rem; font-weight: 700; color: #295738; margin: 0; }
.fi-sub { color: var(--fi-muted); font-size: 0.82rem; margin: 4px 0 0; }
.fi-status-badge {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.72rem; font-weight: 600; border-radius: 999px; padding: 4px 12px;
}
.fi-status-badge.estimate { background: #fdf3d7; color: #8a6d1a; border: 1px solid #e8d49a; }
.fi-status-badge.actual { background: #e8f5e9; color: #1b5e20; border: 1px solid #b7dfb9; }
.fi-filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(118px, 1fr));
    gap: 8px;
    align-items: end;
}
.fi-field label { display: block; font-size: 0.7rem; font-weight: 600; color: var(--fi-muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.03em; }
.fi-field select, .fi-field input {
    width: 100%; border: 1px solid var(--fi-border); border-radius: 10px;
    background: #fffdf4; color: var(--fi-ink); font-family: 'Poppins', sans-serif;
    font-size: 0.8rem; padding: 8px 10px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.fi-field select:focus, .fi-field input:focus {
    border-color: #2e4836;
    box-shadow: 0 0 0 3px rgba(46, 72, 54, 0.10);
    outline: none;
}
.fi-field input[type="number"] { min-width: 90px; }
.fi-btn {
    border: 1px solid #2e4836; border-radius: 10px; background: #2e4836;
    color: #fff; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 0.8rem;
    padding: 9px 16px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 7px;
    transition: background 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}
.fi-btn:hover { background: #3c6e4f; box-shadow: 0 6px 14px rgba(46, 72, 54, 0.18); }
.fi-btn.secondary { background: #f9f4da; color: var(--fi-ink); border-color: var(--fi-border); }
.fi-btn.secondary:hover { background: #f1ecc9; box-shadow: none; }
.fi-btn[disabled], .fi-btn[disabled]:hover { opacity: 0.55; cursor: not-allowed; box-shadow: none; background: #2e4836; }
.fi-btn.secondary[disabled], .fi-btn.secondary[disabled]:hover { background: #f9f4da; color: var(--fi-ink); opacity: 0.6; }
.fi-spinner {
    display: inline-block; width: 13px; height: 13px; margin-right: 7px; vertical-align: -2px;
    border: 2px solid rgba(46, 72, 54, 0.25); border-top-color: #2e4836; border-radius: 50%;
    animation: fiSpin 0.7s linear infinite;
}
@keyframes fiSpin { to { transform: rotate(360deg); } }
.fi-retry {
    margin-top: 8px; border: 1px solid var(--fi-red); background: #fff; color: var(--fi-red);
    border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 0.74rem; font-weight: 600;
    padding: 5px 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
}
.fi-retry:hover { background: #fdecea; }
.fi-summary-chips { display: inline-flex; gap: 6px; flex-wrap: wrap; margin-left: 8px; vertical-align: middle; }
.fi-chip {
    background: #fdf8e3; border: 1px solid #e8d49a; color: #8a6d1a;
    border-radius: 999px; padding: 2px 9px; font-size: 0.68rem; font-weight: 600;
}
.fi-summary-note { color: var(--fi-muted); font-weight: 600; }
.fi-summary-note.error { color: var(--fi-red); }
.fi-filter-error { margin-top: 8px; display: none; }
.fi-method { margin: 0; }
.fi-method summary { cursor: pointer; font-weight: 600; color: var(--fi-ink); font-size: 0.78rem; }
.fi-method .fi-inline-note { margin-top: 6px; }
.fi-period-summary {
    display: inline-block;
    font-size: 0.76rem; color: #295738; font-weight: 600; margin-top: 10px;
    background: #eef4ee; border: 1px solid #d7e5d8; border-radius: 999px; padding: 5px 14px;
}
.fi-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
.fi-tab {
    border: 1px solid #e3dfa2; background: linear-gradient(180deg, #fffdf4, #faf9e5);
    color: var(--fi-ink); border-radius: 999px; font-family: 'Poppins', sans-serif;
    font-size: 0.76rem; font-weight: 600; padding: 8px 15px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 7px;
    transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
}
.fi-tab i { color: #7d9a7d; transition: color 0.15s ease; }
.fi-tab:hover { background: #f4f2d8; }
.fi-tab.active { background: #2e4836; border-color: #2e4836; color: #fff; box-shadow: 0 4px 12px rgba(46, 72, 54, 0.22); }
.fi-tab.active i { color: #cfe3cf; }
.fi-section { display: none; }
.fi-section.active { display: block; animation: fiFade 0.18s ease; }
@keyframes fiFade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
.fi-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 14px;
    align-items: stretch;
}
.fi-kpi {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
    border: 1.5px solid #e3eabb;
    border-radius: 16px;
    padding: 15px 16px 12px;
    position: relative;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.fi-kpi:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
.fi-kpi-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
.fi-kpi-head h6 {
    font-size: 10px; font-weight: 700; color: #8a9389; margin: 4px 0 3px;
    text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.3;
}
.fi-kpi-chip {
    width: 28px; height: 28px; border-radius: 9px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #eef4e6; color: #2e4836; font-size: 0.85rem; flex: 0 0 auto;
}
.fi-kpi-value {
    font-size: clamp(20px, 1.65vw, 26px); font-weight: 800; color: #2e4836;
    margin: 0; line-height: 1.1; font-variant-numeric: tabular-nums;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.fi-kpi-value.down { color: var(--fi-red); }
.fi-kpi-desc { font-size: 10.5px; color: #8a9389; margin-top: 4px; line-height: 1.3; flex: 1 1 auto; }
.fi-kpi-foot {
    display: flex; justify-content: space-between; align-items: center; gap: 8px;
    margin-top: 12px; padding-top: 10px; border-top: 1px solid #eef0e6;
}
.fi-kpi-period {
    display: inline-flex; align-items: center; height: 20px; font-size: 10px; font-weight: 700;
    color: #6b8e6b; background: #f4f7ee; border-radius: 6px; padding: 0 7px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%;
}
.fi-kpi-trend { font-size: 11px; font-weight: 600; color: #8a9389; white-space: nowrap; }
.fi-kpi-trend.up { color: #16a34a; }
.fi-kpi-trend.down { color: #dc2626; }
.fi-kpi-trend.flat { color: #8a9389; }
.fi-kpi-tag {
    display: inline-block; font-size: 0.58rem; font-weight: 700;
    border-radius: 999px; padding: 1px 7px; margin-left: 5px; vertical-align: middle;
}
.fi-kpi-tag.actual { background: #e8f5e9; color: #1b5e20; border: 1px solid #b7dfb9; }
.fi-kpi-tag.estimate { background: #fdf3d7; color: #8a6d1a; border: 1px solid #e8d49a; }
@media (max-width: 1100px) {
    .fi-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 575px) {
    .fi-kpi-grid { grid-template-columns: 1fr; }
}
.fi-grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px; align-items: start; }
.fi-chart-card { background: #fff; border: 1px solid var(--fi-border); border-radius: 14px; padding: 12px; box-shadow: 0 4px 14px rgba(46, 72, 54, 0.05); }
.fi-chart-card h3 { font-size: 0.88rem; font-weight: 700; color: var(--fi-ink); margin: 0 0 2px; }
.fi-chart-card h3::before {
    content: "";
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--fi-green-2);
    margin-right: 8px;
    vertical-align: middle;
}
.fi-chart-card .chart-sub { font-size: 0.7rem; color: var(--fi-muted); margin-bottom: 8px; }
.fi-chart-box { position: relative; height: 200px; }
.fi-chart-box.tall { height: 250px; }
.fi-chart-box-compact { height: 190px; }
.fi-overview-panels { align-items: stretch; }
.fi-overview-panels > * { height: 100%; }
.fi-skeleton {
    display: flex; flex-direction: column; gap: 10px; padding: 16px;
    background: #fff; border: 1px solid var(--fi-border); border-radius: 14px;
}
.fi-skeleton-bar {
    height: 14px; border-radius: 6px;
    background: linear-gradient(90deg, #eef1e9, #e1e8d7, #eef1e9);
    background-size: 200% 100%;
    animation: fiShimmer 1.3s linear infinite;
}
.fi-skeleton-bar.tall { height: 110px; }
@keyframes fiShimmer {
    from { background-position: 200% 0; }
    to { background-position: -200% 0; }
}
.fi-empty, .fi-error, .fi-loading {
    padding: 18px; border-radius: 12px; font-size: 0.82rem; text-align: center;
}
.fi-empty { background: #f7f6e9; color: var(--fi-muted); }
.fi-error { background: #fdecea; color: var(--fi-red); border: 1px solid #f5c6c2; }
.fi-notice { background: #fff8e6; color: #8a6d1f; border: 1px solid #f0e0b0; }
.fi-partial-note {
    padding: 10px 14px; border-radius: 10px; font-size: 0.76rem; text-align: left;
    background: #fff8e6; color: #8a6d1f; border: 1px solid #f0e0b0; margin-bottom: 12px;
}
.fi-loading { background: #f2f6ee; color: var(--fi-ink); display: flex; align-items: center; justify-content: center; gap: 4px; }
.fi-empty.compact, .fi-error.compact, .fi-loading.compact { padding: 12px; font-size: 0.78rem; }
.fi-table-wrap { overflow-x: auto; }
.fi-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.fi-table th {
    text-align: left; padding: 7px 9px; background: linear-gradient(180deg, #f7f8ec, #eef3e2); color: var(--fi-ink);
    font-weight: 600; border-bottom: 1px solid var(--fi-border); white-space: nowrap;
}
.fi-table th[data-sort] { cursor: pointer; user-select: none; }
.fi-table th[data-sort]::after { content: ' \2195'; color: var(--fi-muted); font-size: 0.7rem; }
.fi-table td { padding: 6px 9px; border-bottom: 1px solid #eef1e2; color: #33453a; white-space: nowrap; }
.fi-table tbody tr:nth-child(even) td { background: #fcfcf5; }
.fi-table td.num, .fi-table th.num { text-align: right; }
.fi-table td.num { font-variant-numeric: tabular-nums; }
.fi-table tr:hover td { background: #f8f7ea; }
.fi-badge { display: inline-block; font-size: 0.66rem; font-weight: 600; border-radius: 999px; padding: 2px 9px; }
.fi-badge.ok { background: #e8f5e9; color: #1b5e20; }
.fi-badge.warn { background: #fdf3d7; color: #8a6d1a; }
.fi-badge.bad { background: #fdecea; color: var(--fi-red); }
.fi-badge.info { background: #e3f2fd; color: #1565c0; }
.fi-findings { list-style: none; margin: 0; padding: 0; }
.fi-findings li {
    display: flex; gap: 10px; padding: 9px 10px; border-bottom: 1px dashed #e3e8d3;
    font-size: 0.8rem; color: #33453a;
}
.fi-findings li:last-child { border-bottom: none; }
.fi-findings i { color: var(--fi-green-2); margin-top: 2px; }
.fi-notes { font-size: 0.72rem; color: var(--fi-muted); line-height: 1.5; margin-top: 8px; }
.fi-notes li { margin-bottom: 3px; }
.fi-notes summary { cursor: pointer; font-weight: 600; color: var(--fi-ink); font-size: 0.72rem; }
.fi-notes ul { margin: 6px 0 0; padding-left: 18px; }
.fi-paymix { display: flex; flex-direction: column; gap: 12px; margin-top: 12px; }
.fi-paymix-row { display: grid; grid-template-columns: auto 1fr; gap: 10px; align-items: center; }
.fi-paymix-icon {
    width: 34px; height: 34px; border-radius: 10px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #eef4e6; color: #2e4836; font-size: 0.95rem; flex: 0 0 auto;
}
.fi-paymix-icon.qrph { background: #fdf6e3; color: #8a6d1a; }
.fi-paymix-main { min-width: 0; }
.fi-paymix-head { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; }
.fi-paymix-label { font-size: 0.8rem; font-weight: 600; color: var(--fi-ink); }
.fi-paymix-value { font-size: 0.9rem; font-weight: 700; color: #1b5e20; font-variant-numeric: tabular-nums; white-space: nowrap; }
.fi-paymix-value em { font-style: normal; font-weight: 600; color: var(--fi-muted); margin-left: 6px; font-size: 0.72rem; }
.fi-paymix-bar { margin-top: 6px; height: 7px; border-radius: 999px; background: #eef1e2; overflow: hidden; }
.fi-paymix-bar span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--fi-green-2), var(--fi-green-3)); }
.fi-paymix-bar.qrph span { background: linear-gradient(90deg, #d9a441, #e8c979); }
.fi-paymix-bar.neutral span { background: linear-gradient(90deg, #78909c, #b0bec5); }
.fi-paymix-total {
    display: flex; justify-content: space-between; align-items: baseline; gap: 10px;
    border-top: 1px dashed var(--fi-border); padding-top: 10px;
    font-size: 0.78rem; color: var(--fi-muted); font-weight: 600;
}
.fi-paymix-total b { font-size: 0.98rem; color: #2e4836; font-variant-numeric: tabular-nums; }
.fi-route-meta { font-size: 0.72rem; color: var(--fi-muted); margin-top: 6px; }
.fi-route-meta b { color: var(--fi-ink); font-weight: 700; }
.fi-inline-note { font-size: 0.72rem; color: var(--fi-muted); margin-top: 8px; }
.fi-whatif-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; align-items: end; }
.fi-hint { font-size: 0.7rem; color: var(--fi-muted); margin-top: 6px; }
.fi-disclaimer {
    margin-top: 12px; font-size: 0.72rem; color: #8a6d1a; background: #fdf8e3;
    border: 1px dashed #e8d49a; border-radius: 10px; padding: 8px 12px;
}
.fi-range-pill { font-size: 0.68rem; color: var(--fi-muted); margin-left: 6px; }
@media (max-width: 720px) {
    .fi-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .fi-filter-grid .fi-field:last-child { grid-column: 1 / -1; }
}
@media (max-width: 640px) {
    .fi-chart-box { height: 180px; }
}
</style>

<div class="main-content" id="mainContent">
    <div class="fi-shell">
        <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>

        <div class="fi-page">
    <div class="fi-card">
        <div class="fi-header">
            <div class="fi-page-head">
                <div>
                    <h1 class="fi-title">Financial Intelligence &amp; Analysis</h1>
                    <p class="fi-sub">Profit, passengers, fares, fuel, remittances and forecasts from recorded data.</p>
                </div>
            </div>
            <span class="fi-status-badge actual" id="fiCompletenessBadge"><i class="bi bi-check2-circle"></i> Actual records</span>
        </div>

        <div class="fi-filter-grid" style="margin-top: 14px;">
            <div class="fi-field">
                <label for="fiPeriod">Period</label>
                <select id="fiPeriod"></select>
            </div>
            <div class="fi-field" id="fiYearField">
                <label for="fiYear">Year</label>
                <select id="fiYear"></select>
            </div>
            <div class="fi-field" id="fiQuarterField" hidden>
                <label for="fiQuarter">Quarter</label>
                <select id="fiQuarter">
                    <option value="1">Q1 (Jan–Mar)</option>
                    <option value="2">Q2 (Apr–Jun)</option>
                    <option value="3">Q3 (Jul–Sep)</option>
                    <option value="4">Q4 (Oct–Dec)</option>
                </select>
            </div>
            <div class="fi-field" id="fiFromField" hidden>
                <label for="fiFrom">From</label>
                <input type="date" id="fiFrom">
            </div>
            <div class="fi-field" id="fiToField" hidden>
                <label for="fiTo">To</label>
                <input type="date" id="fiTo">
            </div>
            <div class="fi-field">
                <label for="fiRoute">Route</label>
                <select id="fiRoute"><option value="">All routes</option></select>
            </div>
            <div class="fi-field">
                <label for="fiBus">Bus</label>
                <select id="fiBus"><option value="">All buses</option></select>
            </div>
            <div class="fi-field">
                <label for="fiPassengerType">Passenger</label>
                <select id="fiPassengerType"><option value="">All types</option></select>
            </div>
            <div class="fi-field">
                <label>&nbsp;</label>
                <button type="button" class="fi-btn secondary" id="fiReset"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            </div>
        </div>
        <div class="fi-error fi-filter-error" id="fiFilterError"></div>
        <div class="fi-period-summary" id="fiPeriodSummary">Loading period…</div>
        <div class="fi-inline-note">Filters apply automatically. Select a period or route and the dashboard refreshes.</div>
    </div>

    <div class="fi-tabs" role="tablist">
        <button type="button" class="fi-tab active" data-tab="overview"><i class="bi bi-speedometer2"></i> Overview</button>
        <button type="button" class="fi-tab" data-tab="passengers"><i class="bi bi-people-fill"></i> Passenger Types</button>
        <button type="button" class="fi-tab" data-tab="fare"><i class="bi bi-cash-coin"></i> Fare Matrix</button>
        <button type="button" class="fi-tab" data-tab="fuel"><i class="bi bi-fuel-pump-fill"></i> Fuel Efficiency</button>
        <button type="button" class="fi-tab" data-tab="fleet"><i class="bi bi-signpost-split"></i> Routes &amp; Fleet</button>
        <button type="button" class="fi-tab" data-tab="remittance"><i class="bi bi-cash-stack"></i> Remittance</button>
        <button type="button" class="fi-tab" data-tab="forecast"><i class="bi bi-graph-up-arrow"></i> Forecast</button>
        <button type="button" class="fi-tab" data-tab="whatif"><i class="bi bi-sliders"></i> What-If</button>
    </div>

    <div class="fi-section active" id="fiTab-overview">
        <div id="fiOverviewStatus"></div>
        <div id="fiOverviewBody" hidden>
            <div class="fi-kpi-grid" id="fiOverviewKpis"></div>
            <div class="fi-inline-note" id="fiScopeNotes"></div>
            <div class="fi-grid-2 fi-overview-panels" style="margin-top: 4px;">
                <div class="fi-chart-card">
                    <h3>Collections vs Expenses</h3>
                    <div class="chart-sub" id="fiOverviewSeriesSub"></div>
                    <div class="fi-chart-box fi-chart-box-compact"><canvas id="fiChartTrend"></canvas></div>
                    <div class="fi-empty" id="fiChartTrendEmpty" hidden></div>
                </div>
                <div class="fi-chart-card">
                    <h3>Cash vs QRPh Collections</h3>
                    <div class="chart-sub" id="fiPaymentSub">Collections by recorded payment method.</div>
                    <div id="fiPaymentBreakdown"></div>
                </div>
            </div>
            <div class="fi-chart-card" style="margin-top: 12px;">
                <h3>Route Performance</h3>
                <div class="chart-sub">Trips, collections, attributable costs and estimated profit per route.</div>
                <div class="fi-inline-note" id="fiRouteMeta"></div>
                <div class="fi-table-wrap" id="fiOverviewRoutes"></div>
                <div class="fi-notes" id="fiOverviewNotes"></div>
            </div>
        </div>
    </div>

    <div class="fi-section" id="fiTab-passengers">
        <div id="fiPassengersStatus"></div>
        <div id="fiPassengersBody" hidden>
            <div class="fi-kpi-grid" id="fiPassengerKpis"></div>
            <div class="fi-grid-2">
                <div class="fi-chart-card">
                    <h3>Collection Share by Passenger Type</h3>
                    <div class="chart-sub">Share of collections by passenger type.</div>
                    <div class="fi-chart-box"><canvas id="fiChartPaxDoughnut"></canvas></div>
                    <div class="fi-empty" id="fiChartPaxDoughnutEmpty" hidden></div>
                </div>
                <div class="fi-chart-card">
                    <h3>Trips by Passenger Type</h3>
                    <div class="chart-sub">Recorded trips by passenger type.</div>
                    <div class="fi-chart-box"><canvas id="fiChartPaxTrips"></canvas></div>
                    <div class="fi-empty" id="fiChartPaxTripsEmpty" hidden></div>
                </div>
            </div>
            <div class="fi-grid-2" style="margin-top: 14px;">
                <div class="fi-chart-card">
                    <h3>Monthly Collections by Type</h3>
                    <div class="chart-sub">Monthly collections by passenger type.</div>
                    <div class="fi-chart-box"><canvas id="fiChartPaxMonthly"></canvas></div>
                    <div class="fi-empty" id="fiChartPaxMonthlyEmpty" hidden></div>
                </div>
                <div class="fi-chart-card">
                    <h3>Passenger Category Distribution by Route</h3>
                    <div class="chart-sub">Trips per passenger type by route.</div>
                    <div class="fi-chart-box"><canvas id="fiChartPaxRoutes"></canvas></div>
                    <div class="fi-empty" id="fiChartPaxRoutesEmpty" hidden></div>
                </div>
            </div>
            <div class="fi-grid-2" style="margin-top: 14px;">
                <div class="fi-chart-card">
                    <h3>Average Realized Fare by Passenger Type</h3>
                    <div class="chart-sub">Average realized fare by passenger type.</div>
                    <div class="fi-chart-box"><canvas id="fiChartPaxAvgFare"></canvas></div>
                    <div class="fi-empty" id="fiChartPaxAvgFareEmpty" hidden></div>
                </div>
                <details class="fi-card fi-method" style="margin: 0;">
                    <summary>Category definitions and estimated discounts</summary>
                    <div class="fi-inline-note">
                        Regular = standard fare. Student, Senior and PWD = discounted fare. Special = teacher/nurse/voucher fare. Baggage is a separate fare category, never a passenger.<br>
                        Estimated discount impact compares each category against the Regular average realized fare.
                    </div>
                </details>
            </div>
            <div class="fi-card" style="margin-top: 14px;">
                <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Passenger Type Detail</h3>
                <div class="fi-table-wrap" id="fiPassengerTable"></div>
                <div class="fi-inline-note" id="fiPassengerDiscountNote"></div>
            </div>
        </div>
    </div>

    <div class="fi-section" id="fiTab-fare">
        <div id="fiFareStatus"></div>
        <div id="fiFareBody" hidden>
            <div class="fi-kpi-grid" id="fiFareKpis"></div>
            <div class="fi-grid-2">
                <div class="fi-chart-card">
                    <h3>Actual vs Expected Collections at the Current Matrix</h3>
                    <div class="chart-sub">Actual vs matrix-expected collections.</div>
                    <div class="fi-chart-box"><canvas id="fiChartFare"></canvas></div>
                    <div class="fi-empty" id="fiChartFareEmpty" hidden></div>
                </div>
                <div class="fi-card" style="margin: 0;">
                    <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Recorded Change Around the Latest Matrix Update</h3>
                    <div id="fiFareChangeTracking"></div>
                </div>
            </div>
            <div class="fi-card" style="margin-top: 14px;">
                <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Matrix Alignment by Route</h3>
                <div class="chart-sub">Actual vs expected per route (positive = below matrix).</div>
                <div class="fi-table-wrap" id="fiFareAppliedTable"></div>
            </div>
            <div class="fi-card" style="margin-top: 14px;">
                <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Current Fare Matrix</h3>
                <div class="fi-table-wrap" id="fiFareSegments"></div>
                <div class="fi-inline-note" id="fiFareAppliedNote"></div>
            </div>
        </div>
    </div>

    <div class="fi-section" id="fiTab-fuel">
        <div id="fiFuelStatus"></div>
        <div id="fiFuelBody" hidden>
            <div class="fi-kpi-grid" id="fiFuelKpis"></div>
            <div class="fi-inline-note" id="fiFuelScope"></div>
            <div class="fi-grid-2">
                <div class="fi-chart-card">
                    <h3>Diesel Price vs Purchased Liters</h3>
                    <div class="chart-sub">Monthly price per liter vs purchased volume.</div>
                    <div class="fi-chart-box"><canvas id="fiChartFuelPrice"></canvas></div>
                    <div class="fi-empty" id="fiChartFuelPriceEmpty" hidden></div>
                </div>
                <div class="fi-chart-card">
                    <h3>Fuel Cost by Bus</h3>
                    <div class="chart-sub">Purchase cost per bus.</div>
                    <div class="fi-chart-box"><canvas id="fiChartFuelBus"></canvas></div>
                    <div class="fi-empty" id="fiChartFuelBusEmpty" hidden></div>
                </div>
            </div>
            <div class="fi-grid-2" style="margin-top: 14px;">
                <div class="fi-chart-card">
                    <h3>Efficiency by Bus (KPL)</h3>
                    <div class="chart-sub">Average km per liter per bus.</div>
                    <div class="fi-chart-box"><canvas id="fiChartFuelKpl"></canvas></div>
                    <div class="fi-empty" id="fiChartFuelKplEmpty" hidden></div>
                </div>
                <div class="fi-chart-card">
                    <h3>Fuel Cost by Route</h3>
                    <div class="chart-sub">Fuel cost and liters per route.</div>
                    <div class="fi-chart-box"><canvas id="fiChartFuelRoute"></canvas></div>
                    <div class="fi-empty" id="fiChartFuelRouteEmpty" hidden></div>
                </div>
            </div>
            <div class="fi-grid-2" style="margin-top: 14px;">
                <div class="fi-chart-card">
                    <h3>Monthly Fuel Cost per Kilometer and per Trip</h3>
                    <div class="chart-sub">Monthly cost per km and per trip.</div>
                    <div class="fi-chart-box"><canvas id="fiChartFuelCostKm"></canvas></div>
                    <div class="fi-empty" id="fiChartFuelCostKmEmpty" hidden></div>
                </div>
                <details class="fi-card fi-method" style="margin: 0;">
                    <summary>How fuel is counted</summary>
                    <div class="fi-inline-note">
                        Purchases are counted once per bus and receipt. Recorded fuel expenses take priority; otherwise receipts are added once. Efficiency uses trip odometer distance.
                    </div>
                </details>
            </div>
            <div class="fi-grid-2" style="margin-top: 14px;">
                <div class="fi-card" style="margin:0;">
                    <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Bus Fuel Detail</h3>
                    <div class="fi-table-wrap" id="fiFuelBusTable"></div>
                </div>
                <div class="fi-card" style="margin:0;">
                    <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Historical Diesel Prices</h3>
                    <div class="fi-table-wrap" id="fiFuelPriceTable"></div>
                    <div class="fi-inline-note" id="fiFuelPolicy"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="fi-section" id="fiTab-fleet">
        <div id="fiFleetStatus"></div>
        <div id="fiFleetBody" hidden>
            <div class="fi-kpi-grid" id="fiFleetKpis"></div>
            <div class="fi-grid-2">
                <div class="fi-chart-card">
                    <h3>Collections by Route</h3>
                    <div class="chart-sub">Collections per route.</div>
                    <div class="fi-chart-box"><canvas id="fiChartFleetCollections"></canvas></div>
                    <div class="fi-empty" id="fiChartFleetCollectionsEmpty" hidden></div>
                </div>
                <div class="fi-chart-card">
                    <h3>Estimated Margin by Route</h3>
                    <div class="chart-sub">Estimated margin per route.</div>
                    <div class="fi-chart-box"><canvas id="fiChartFleetMargin"></canvas></div>
                    <div class="fi-empty" id="fiChartFleetMarginEmpty" hidden></div>
                </div>
            </div>
            <div class="fi-grid-2" style="margin-top: 14px;">
                <div class="fi-chart-card">
                    <h3>Attributable Cost Composition by Route</h3>
                    <div class="chart-sub">Fuel, maintenance and other costs per route.</div>
                    <div class="fi-chart-box"><canvas id="fiChartFleetCostMix"></canvas></div>
                    <div class="fi-empty" id="fiChartFleetCostMixEmpty" hidden></div>
                </div>
                <details class="fi-card fi-method" style="margin: 0;">
                    <summary>How costs are attributed to routes</summary>
                    <div class="fi-inline-note">
                        Costs are charged only when the recorded bus or trip reference matches exactly one route; ambiguous matches stay unattributed. Routes without cost records show "—", never unprofitable. Each direction stays its own route; spacing and case variants are normalized.
                    </div>
                </details>
            </div>
            <div class="fi-card" style="margin-top: 14px;">
                <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Route Detail (sortable)</h3>
                <div class="fi-table-wrap" id="fiFleetRoutesTable"></div>
            </div>
            <div class="fi-card">
                <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Bus Detail (sortable)</h3>
                <div class="fi-table-wrap" id="fiFleetBusesTable"></div>
                <div class="fi-notes" id="fiFleetNotes"></div>
            </div>
        </div>
    </div>

    <div class="fi-section" id="fiTab-remittance">
        <div id="fiRemittanceStatus"></div>
        <div id="fiRemittanceBody" hidden>
            <div class="fi-kpi-grid" id="fiRemittanceKpis"></div>
            <div class="fi-inline-note" id="fiRemitScope"></div>
            <div class="fi-grid-2">
                <div class="fi-chart-card">
                    <h3>Expected vs Actual Remittances</h3>
                    <div class="chart-sub">Expected vs actual remittances by month.</div>
                    <div class="fi-chart-box"><canvas id="fiChartRemitMonthly"></canvas></div>
                    <div class="fi-empty" id="fiChartRemitMonthlyEmpty" hidden></div>
                </div>
                <div class="fi-chart-card">
                    <h3>Remittance Status Breakdown</h3>
                    <div class="chart-sub">Remitted cash by status.</div>
                    <div class="fi-chart-box"><canvas id="fiChartRemitStatus"></canvas></div>
                    <div class="fi-empty" id="fiChartRemitStatusEmpty" hidden></div>
                </div>
            </div>
            <div class="fi-card" style="margin-top: 14px;">
                <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Discrepancy Cases</h3>
                <div class="fi-table-wrap" id="fiRemitCases"></div>
                <div class="fi-notes" id="fiRemitNotes"></div>
            </div>
        </div>
    </div>

    <div class="fi-section" id="fiTab-forecast">
        <div id="fiForecastStatus"></div>
        <div id="fiForecastBody" hidden>
            <div class="fi-field" style="max-width: 220px; margin-bottom: 12px;">
                <label for="fiHorizon">Forecast horizon</label>
                <select id="fiHorizon">
                    <option value="7d">Next 7 days</option>
                    <option value="30d" selected>Next 30 days</option>
                    <option value="90d">Next 90 days</option>
                </select>
            </div>
            <div class="fi-kpi-grid" id="fiForecastKpis"></div>
            <div class="fi-inline-note" id="fiForecastScope"></div>
            <div class="fi-card" style="margin-top: 0;">
                <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Actual History and Forecast Band</h3>
                <div class="chart-sub">Actual passengers and forecast band.</div>
                <div class="fi-chart-box tall"><canvas id="fiChartForecast"></canvas></div>
                <div class="fi-empty" id="fiChartForecastEmpty" hidden></div>
                <div class="fi-inline-note" id="fiForecastRange"></div>
            </div>
            <div class="fi-grid-2">
                <div class="fi-card" style="margin:0;">
                    <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Assumptions</h3>
                    <div class="fi-notes" id="fiForecastAssumptions"></div>
                </div>
                <div class="fi-card" style="margin:0;">
                    <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Model and Uncertainty</h3>
                    <div id="fiForecastModel"></div>
                    <div class="fi-disclaimer" id="fiForecastDisclaimer"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="fi-section" id="fiTab-whatif">
        <div class="fi-card">
            <h3 style="font-size:0.95rem;font-weight:700;color:var(--fi-ink);margin:0 0 4px;">What-If Scenario Builder</h3>
            <p class="fi-sub">Test proposed fare, volume, fuel and expense changes against the actual baseline. Nothing is written to the live fare matrix or any other record.</p>
            <div class="fi-whatif-form" style="margin-top: 12px;">
                <div class="fi-field">
                    <label for="fiWiFare">Fare change (%)</label>
                    <input type="number" id="fiWiFare" value="0" step="0.5" min="-90" max="200">
                </div>
                <div class="fi-field">
                    <label for="fiWiVolume">Passenger volume change (%)</label>
                    <input type="number" id="fiWiVolume" value="0" step="0.5" min="-90" max="200">
                </div>
                <div class="fi-field">
                    <label for="fiWiFuel">Fuel price change (%)</label>
                    <input type="number" id="fiWiFuel" value="0" step="0.5" min="-90" max="200">
                </div>
                <div class="fi-field">
                    <label for="fiWiExpense">Other expense change (%)</label>
                    <input type="number" id="fiWiExpense" value="0" step="0.5" min="-90" max="200">
                </div>
                <div class="fi-field">
                    <label for="fiWiRoute">Route-specific fare change</label>
                    <select id="fiWiRoute"><option value="">No route override</option></select>
                </div>
                <div class="fi-field">
                    <label for="fiWiRoutePct">Route fare change (%)</label>
                    <input type="number" id="fiWiRoutePct" value="0" step="0.5" min="-90" max="200">
                </div>
                <div class="fi-field">
                    <label>&nbsp;</label>
                    <button type="button" class="fi-btn" id="fiWiRun"><i class="bi bi-calculator"></i> Run scenario</button>
                </div>
            </div>
            <div class="fi-hint">Route override applies to the selected route; other routes use the global fare change.</div>
        </div>
        <div id="fiWhatIfStatus"></div>
        <div id="fiWhatIfBody" hidden>
            <div class="fi-kpi-grid" id="fiWhatIfKpis"></div>
            <div class="fi-grid-2">
                <div class="fi-chart-card">
                    <h3>Baseline vs Projected</h3>
                    <div class="chart-sub">Baseline vs projected scenario.</div>
                    <div class="fi-chart-box"><canvas id="fiChartWhatIf"></canvas></div>
                    <div class="fi-empty" id="fiChartWhatIfEmpty" hidden></div>
                </div>
                <div class="fi-card" style="margin:0;">
                    <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Impact Summary</h3>
                    <div id="fiWhatIfSummary"></div>
                    <div class="fi-notes" id="fiWhatIfAssumptions"></div>
                    <div class="fi-disclaimer" id="fiWhatIfDisclaimer"></div>
                </div>
            </div>
            <div class="fi-card" style="margin-top: 14px;">
                <h3 style="font-size:0.88rem;font-weight:700;color:var(--fi-ink);margin:0 0 8px;">Route-Level Projection</h3>
                <div class="fi-table-wrap" id="fiWhatIfRoutes"></div>
            </div>
        </div>
    </div>

        </div><!-- /.fi-page -->
        <?php require __DIR__ . '/_aibutton.php'; ?>
    </div><!-- /.fi-shell -->
</div>

<script>
(function () {
    "use strict";

    var API = "/finance/api/analytics/financial_intelligence.php";
    var COLORS = {
        green: "#05590c",
        soft: "#198754",
        light: "#81c784",
        gold: "#d9a441",
        red: "#c62828",
        grey: "#8a9a8a",
        blue: "#1565c0"
    };
    var state = {
        tab: "overview",
        filters: { period: "today", year: String(new Date().getFullYear()), quarter: "1", from: "", to: "", route: "", bus: "", passenger_type: "" },
        horizon: "30d",
        cache: {},
        charts: {},
        rendered: {},
        seq: {},
        tables: {},
        tableSort: {},
        optionsLoaded: false,
        pending: 0
    };

    function $(id) { return document.getElementById(id); }
    function esc(value) {
        return String(value === null || value === undefined ? "" : value).replace(/[&<>"']/g, function (ch) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch];
        });
    }
    function peso(value) {
        var amount = Number(value || 0);
        var sign = amount < 0 ? "-" : "";
        return sign + "\u20b1" + Math.abs(amount).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function whole(value) { return Number(value || 0).toLocaleString("en-PH"); }
    function pct(value) { return value === null || value === undefined ? "\u2014" : Number(value).toFixed(1) + "%"; }
    function delta(value) { return Number(value || 0) >= 0 ? "\u25b2 " : "\u25bc "; }
    function multi(value, digits) { return Number(value || 0).toLocaleString("en-PH", { minimumFractionDigits: digits, maximumFractionDigits: digits }); }

    function signature() {
        var f = state.filters;
        return [f.period, f.year, f.quarter, f.from, f.to, f.route, f.bus, f.passenger_type, state.horizon].join("|");
    }

    function params() {
        var f = state.filters;
        var query = new URLSearchParams();
        query.set("period", f.period);
        if (f.year) query.set("year", f.year);
        if (f.period === "quarter") query.set("quarter", f.quarter);
        if (f.period === "custom") { query.set("from", f.from); query.set("to", f.to); }
        if (f.route) query.set("route", f.route);
        if (f.bus) query.set("bus", f.bus);
        if (f.passenger_type) query.set("passenger_type", f.passenger_type);
        return query;
    }

    var REQUEST_TIMEOUT_MS = 70000;

    /* Per-section request tracking. Every load captures a token at issue
       time; only the newest token for the active filter signature may render
       or clear loading. A superseded response is dropped so an older filter
       selection can never overwrite a newer one. */
    var SECTION_UI = {
        overview: { display: "Overview" },
        passengers: { display: "Passengers" },
        fare: { display: "Fare" },
        fuel: { display: "Fuel" },
        fleet: { display: "Fleet" },
        remittance: { display: "Remittance" },
        forecast: { display: "Forecast" },
        whatif: { display: "WhatIf" }
    };

    function trackSection(section, message) {
        var token = {
            section: section,
            display: SECTION_UI[section] ? SECTION_UI[section].display : section,
            seq: (state.seq[section] = (state.seq[section] || 0) + 1),
            key: signature()
        };
        showStatus(token.display, "loading", message);
        return token;
    }

    function isCurrent(token) {
        return !!token && state.seq[token.section] === token.seq && token.key === signature();
    }

    function partialMessage(payload) {
        var names = (payload && payload.source_errors ? payload.source_errors : [])
            .map(function (entry) { return entry && entry.source ? entry.source : ""; })
            .filter(function (name) { return name !== ""; });
        return "Partial data: " + (names.length ? names.join(", ") : "some sources") +
            " could not be read for this selection. Shown values exclude the failed source.";
    }

    function markLoaded(token, payload) {
        if (!isCurrent(token)) { return false; }
        var data = payload && payload.data ? payload.data : null;
        if (data && data.partial === true) {
            showStatus(token.display, "partial", partialMessage(data));
        } else {
            showStatus(token.display, null);
        }
        return true;
    }

    function updateBusy() {
        var busy = state.pending > 0;
        var node = $("fiReset");
        if (node) { node.disabled = busy; }
    }

    function request(section, extra, force) {
        var query = params();
        query.set("section", section);
        if (extra) { Object.keys(extra).forEach(function (key) { query.set(key, extra[key]); }); }
        var key = query.toString();
        if (!force && state.cache[key]) { return Promise.resolve(state.cache[key]); }

        var config = { headers: { Accept: "application/json" }, credentials: "same-origin" };
        var timer = null;
        if (typeof AbortController === "function") {
            var controller = new AbortController();
            config.signal = controller.signal;
            timer = setTimeout(function () { controller.abort(); }, REQUEST_TIMEOUT_MS);
        }

        state.pending += 1;
        updateBusy();
        return fetch(API + "?" + key, config)
            .then(function (response) {
                return response.text().then(function (body) {
                    var payload;
                    try { payload = JSON.parse(body); }
                    catch (e) { throw new Error("The financial intelligence service returned an invalid response (HTTP " + response.status + ")."); }
                    if (!response.ok || !payload.success) {
                        throw new Error(payload && payload.message ? payload.message : "Request failed.");
                    }
                    state.cache[key] = payload;
                    return payload;
                });
            })
            .catch(function (error) {
                if (error && error.name === "AbortError") {
                    throw new Error("The request timed out. Please retry.");
                }
                throw error;
            })
            .finally(function () {
                if (timer !== null) { clearTimeout(timer); }
                state.pending = Math.max(0, state.pending - 1);
                updateBusy();
            });
    }

    function setStatus(target, type, message, section) {
        var node = $(target);
        if (!node) return;
        if (!type) { node.innerHTML = ""; return; }
        if (type === "loading") {
            node.innerHTML = '<div class="fi-skeleton" role="status" aria-live="polite" aria-label="' + esc(message) + '">' +
                '<span class="fi-skeleton-bar" style="width:38%"></span>' +
                '<span class="fi-skeleton-bar" style="width:72%"></span>' +
                '<span class="fi-skeleton-bar" style="width:56%"></span>' +
                '<span class="fi-skeleton-bar tall"></span>' +
                "</div>";
            return;
        }
        if (type === "error") {
            var retry = section
                ? '<br><button type="button" class="fi-retry" data-retry="' + esc(section) + '"><i class="bi bi-arrow-clockwise"></i> Retry</button>'
                : "";
            node.innerHTML = '<div class="fi-error">' + esc(message) + retry + "</div>";
            return;
        }
        if (type === "notice") {
            node.innerHTML = '<div class="fi-empty fi-notice"><i class="bi bi-info-circle"></i> ' + esc(message) + "</div>";
            return;
        }
        if (type === "partial") {
            node.innerHTML = '<div class="fi-partial-note"><i class="bi bi-exclamation-triangle"></i> ' + esc(message) + "</div>";
            return;
        }
        node.innerHTML = '<div class="fi-empty">' + esc(message) + "</div>";
    }

    function showStatus(section, type, message) {
        setStatus("fi" + section + "Status", type, message, section.toLowerCase());
        var body = $("fi" + section + "Body");
        if (body) body.hidden = type !== null && type !== "partial";
    }

    function chart(canvasId, config) {
        var canvas = $(canvasId);
        if (!canvas || typeof Chart === "undefined") return null;
        if (state.charts[canvasId]) { state.charts[canvasId].destroy(); }
        state.charts[canvasId] = new Chart(canvas.getContext("2d"), config);
        return state.charts[canvasId];
    }

    function chartOrEmpty(canvasId, hasData, config, message) {
        var canvas = $(canvasId);
        if (!canvas) return null;
        var empty = $(canvasId + "Empty");
        var box = canvas.parentNode || null;
        if (!hasData) {
            if (state.charts[canvasId]) {
                state.charts[canvasId].destroy();
                delete state.charts[canvasId];
            }
            canvas.hidden = true;
            if (box) { box.hidden = true; }
            if (empty) {
                empty.hidden = false;
                empty.textContent = message || "No records for the selected filters.";
            }
            return null;
        }
        canvas.hidden = false;
        if (box) { box.hidden = false; }
        if (empty) {
            empty.hidden = true;
            empty.textContent = "";
        }
        return chart(canvasId, config);
    }

    function baseScales(currency) {
        return {
            y: {
                beginAtZero: true,
                grid: { color: "rgba(0,0,0,0.05)" },
                ticks: {
                    color: COLORS.grey, font: { size: 10 },
                    callback: function (value) { return currency ? "\u20b1" + whole(value) : whole(value); }
                }
            },
            x: { grid: { display: false }, ticks: { color: COLORS.grey, font: { size: 10 }, maxRotation: 0, autoSkip: true, autoSkipPadding: 10 } }
        };
    }

    function lineConfig(labels, datasets, currency) {
        return {
            type: "line",
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                plugins: { legend: { labels: { color: "#33453a", font: { size: 10 }, usePointStyle: true, boxWidth: 8 } } },
                scales: baseScales(currency !== false)
            }
        };
    }

    function barConfig(labels, datasets, currency) {
        return {
            type: "bar",
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: "#33453a", font: { size: 10 }, usePointStyle: true, boxWidth: 8 } } },
                scales: baseScales(currency !== false)
            }
        };
    }

    function doughnutConfig(labels, values, colors) {
        return {
            type: "doughnut",
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: "#fffdf8" }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: "58%",
                plugins: {
                    legend: { position: "bottom", labels: { color: "#33453a", font: { size: 10 }, usePointStyle: true, boxWidth: 8 } },
                    tooltip: { callbacks: { label: function (item) { return item.label + ": " + peso(item.raw); } } }
                }
            }
        };
    }

    function kpi(label, value, sub, tone, tag) {
        var badge = tag
            ? ' <span class="fi-kpi-tag ' + (tag === "estimate" ? "estimate" : "actual") + '">' + (tag === "estimate" ? "Estimate" : "Actual") + "</span>"
            : "";
        return '<div class="fi-kpi"><div class="fi-kpi-head"><h6>' + esc(label) + badge + '</h6></div>' +
            '<p class="fi-kpi-value' + (tone === "down" ? " down" : "") + '">' + value + "</p>" +
            (sub ? '<div class="fi-kpi-desc">' + sub + "</div>" : "") + "</div>";
    }

    /* Profit-Overview-styled card: label, value, description and a footer with
       the active period and the percentage change. The change is shown only
       when a valid comparison exists; a missing previous window is stated
       instead of a fabricated percentage. */
    function kpiTrendHtml(trendPct, previousAvailable) {
        if (!previousAvailable) {
            return '<span class="fi-kpi-trend">No previous data</span>';
        }
        if (trendPct === null || trendPct === undefined) {
            return "";
        }
        var value = Number(trendPct);
        if (Math.abs(value) < 0.05) {
            return '<span class="fi-kpi-trend flat">0%</span>';
        }
        return '<span class="fi-kpi-trend ' + (value > 0 ? "up" : "down") + '">' +
            (value > 0 ? "\u25b2 " : "\u25bc ") + Math.abs(value).toFixed(1) + "%</span>";
    }

    function kpiCard(label, value, desc, tone, tag, trendPct, previousAvailable, icon, periodLabel) {
        var badge = tag
            ? ' <span class="fi-kpi-tag ' + (tag === "estimate" ? "estimate" : "actual") + '">' + (tag === "estimate" ? "Estimate" : "Actual") + "</span>"
            : "";
        var chip = icon ? '<span class="fi-kpi-chip"><i class="bi ' + esc(icon) + '"></i></span>' : "";
        return '<div class="fi-kpi">' +
            '<div class="fi-kpi-head"><h6>' + esc(label) + badge + "</h6>" + chip + "</div>" +
            '<p class="fi-kpi-value' + (tone === "down" ? " down" : "") + '">' + value + "</p>" +
            '<div class="fi-kpi-desc">' + desc + "</div>" +
            '<div class="fi-kpi-foot"><span class="fi-kpi-period">' + esc(periodLabel) + "</span>" +
            kpiTrendHtml(trendPct, previousAvailable) + "</div>" +
            "</div>";
    }

    /* States, for a KPI footer, which period the value covers and which
       period it is compared against. The comparison is dropped when the
       period label already spells it out (for example "2026 vs 2025"). */
    function kpiComparisonText(period, previousAvailable, periodLabel) {
        if (!period || !period.previous_from || !period.previous_to) {
            return "";
        }
        if (/ vs /i.test(String(periodLabel || ""))) {
            return "";
        }
        if (previousAvailable === false) {
            return "no comparable previous data";
        }
        var from = String(period.previous_from);
        var to = String(period.previous_to);
        var short;
        if (from === to) {
            short = from;
        } else if (from.slice(5) === "01-01" && to.slice(5) === "12-31") {
            short = from.slice(0, 4);
        } else if (from.slice(0, 7) === to.slice(0, 7)) {
            short = from.slice(0, 7);
        } else {
            short = from + " \u2192 " + to;
        }
        return "vs " + short;
    }

    function changeText(value, suffix) {
        if (value === null || value === undefined) return "\u2014";
        return '<span class="' + (Number(value) >= 0 ? "up" : "down") + '">' + delta(value) + Math.abs(Number(value)).toFixed(1) + (suffix || "%") + "</span>";
    }

    /* ---------------- generic sortable tables ---------------- */

    function methodNotes(targetId, notes) {
        var host = $(targetId);
        if (!host) return;
        if (!notes || !notes.length) { host.innerHTML = ""; return; }
        host.innerHTML = '<details class="fi-notes"><summary>Method notes</summary><ul>' +
            notes.map(function (note) { return "<li>" + esc(note) + "</li>"; }).join("") + "</ul></details>";
    }

    function scopeMessages(scope) {
        var entries = scope && scope.not_applicable ? scope.not_applicable : [];
        return entries.map(function (entry) { return entry && entry.message ? String(entry.message) : ""; })
            .filter(function (message) { return message !== ""; });
    }

    function renderScope(targetId, scope) {
        var host = $(targetId);
        if (!host) return;
        host.innerHTML = scopeMessages(scope).map(function (message) {
            return '<div><i class="bi bi-info-circle"></i> ' + esc(message) + "</div>";
        }).join("");
    }

    function dataTable(key, columns, rows) {
        state.tables[key] = { columns: columns, rows: rows || [] };
        renderTable(key);
    }

    function renderTable(key) {
        var spec = state.tables[key];
        var host = $(key);
        if (!spec || !host) return;
        if (!spec.rows.length) {
            host.innerHTML = '<div class="fi-empty">No records for the selected filters.</div>';
            return;
        }
        var sort = state.tableSort[key];
        var rows = spec.rows.slice();
        if (sort) {
            rows.sort(function (a, b) {
                var av = sortValue(a, sort.key);
                var bv = sortValue(b, sort.key);
                if (typeof av === "number" && typeof bv === "number") return sort.dir === "asc" ? av - bv : bv - av;
                return sort.dir === "asc" ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av));
            });
        }
        function sortValue(row, colKey) {
            var column = spec.columns.filter(function (c) { return c.key === colKey; })[0];
            var value = row[colKey];
            return column && column.type === "text" ? String(value === null || value === undefined ? "" : value) : Number(value || 0);
        }
        var head = spec.columns.map(function (column) {
            return '<th class="' + (column.num ? "num" : "") + '" data-sort="' + esc(column.key) + '" data-table="' + esc(key) + '">' + esc(column.label) + "</th>";
        }).join("");
        var body = rows.map(function (row) {
            return "<tr>" + spec.columns.map(function (column) {
                var content;
                if (typeof column.render === "function") content = column.render(row);
                else {
                    var value = row[column.key];
                    if (column.type === "money") content = peso(value);
                    else if (column.type === "pct") content = pct(value);
                    else if (column.type === "number") content = value === null || value === undefined ? "\u2014" : multi(value, column.digits === undefined ? 0 : column.digits);
                    else content = esc(value);
                }
                return '<td class="' + (column.num ? "num" : "") + '">' + content + "</td>";
            }).join("") + "</tr>";
        }).join("");
        host.innerHTML = '<table class="fi-table"><thead><tr>' + head + "</tr></thead><tbody>" + body + "</tbody></table>";
    }

    /* ---------------- options and filters ---------------- */

    function fillSelect(node, items, placeholder) {
        var html = placeholder ? '<option value="">' + esc(placeholder) + "</option>" : "";
        items.forEach(function (item) {
            html += '<option value="' + esc(item.value) + '">' + esc(item.label) + "</option>";
        });
        node.innerHTML = html;
    }

    function syncPeriodFields() {
        var period = state.filters.period;
        $("fiQuarterField").hidden = period !== "quarter";
        $("fiYearField").hidden = !["quarter", "h1", "h2", "yearly", "yoy"].includes(period);
        $("fiFromField").hidden = period !== "custom";
        $("fiToField").hidden = period !== "custom";
    }

    function loadOptions() {
        if (state.optionsLoaded) return Promise.resolve();
        return request("options").then(function (payload) {
            var data = payload.data;
            fillSelect($("fiPeriod"), data.periods.map(function (p) { return { value: p.value, label: p.label }; }), null);
            fillSelect($("fiYear"), data.years.map(function (y) { return { value: String(y), label: String(y) }; }), null);
            fillSelect($("fiRoute"), data.routes.map(function (r) { return { value: r, label: r }; }), "All routes");
            fillSelect($("fiBus"), data.buses.map(function (b) { return { value: b, label: b }; }), "All buses");
            fillSelect($("fiPassengerType"), data.passenger_types.map(function (t) { return { value: t, label: t }; }), "All types");
            fillSelect($("fiWiRoute"), data.fare_routes.map(function (r) { return { value: r, label: r }; }), "No route override");
            $("fiPeriod").value = state.filters.period;
            $("fiYear").value = state.filters.year;
            state.optionsLoaded = true;
            syncPeriodFields();
        });
    }

    function syncUrlState() {
        var query = params();
        query.delete("year");
        window.history.replaceState(null, "", window.location.pathname + "?" + query.toString());
    }

    function selectedLabel(selectId) {
        var select = $(selectId);
        if (!select || !select.options || select.selectedIndex < 0) return "";
        var option = select.options[select.selectedIndex];
        return option && option.textContent ? option.textContent : "";
    }

    function filterChips(filters) {
        var chips = [];
        if (filters.route) chips.push('<span class="fi-chip">Route: ' + esc(filters.route) + "</span>");
        if (filters.bus) chips.push('<span class="fi-chip">Bus: ' + esc(filters.bus) + "</span>");
        if (filters.passenger_type) chips.push('<span class="fi-chip">Passenger: ' + esc(filters.passenger_type) + "</span>");
        return chips.length ? '<span class="fi-summary-chips">' + chips.join("") + "</span>" : "";
    }

    function filterErrorMessage(message) {
        var node = $("fiFilterError");
        if (!node) return;
        if (!message) {
            node.style.display = "none";
            node.textContent = "";
            return;
        }
        node.style.display = "block";
        node.textContent = message;
    }

    function collectFilterControls() {
        var period = $("fiPeriod").value;
        var from = $("fiFrom").value;
        var to = $("fiTo").value;
        if (period === "custom") {
            if (!from || !to) {
                filterErrorMessage("Choose both a custom start and end date before applying.");
                return false;
            }
            if (from > to) {
                filterErrorMessage("The custom start date must be on or before the end date.");
                return false;
            }
        }
        filterErrorMessage("");
        state.filters.period = period;
        state.filters.year = $("fiYear").value;
        state.filters.quarter = $("fiQuarter").value;
        state.filters.from = from;
        state.filters.to = to;
        state.filters.route = $("fiRoute").value;
        state.filters.bus = $("fiBus").value;
        state.filters.passenger_type = $("fiPassengerType").value;
        return true;
    }

    function applyFilters() {
        state.cache = {};
        state.rendered = {};
        /* Invalidate every in-flight section request: responses issued under
           the previous filter state can no longer render or clear loading. */
        Object.keys(SECTION_UI).forEach(function (section) {
            state.seq[section] = (state.seq[section] || 0) + 1;
        });
        syncUrlState();
        showPendingRange();
        loadTab(state.tab, true);
    }

    function resetFilters() {
        state.filters.period = "today";
        state.filters.year = String(new Date().getFullYear());
        state.filters.quarter = "1";
        state.filters.from = "";
        state.filters.to = "";
        state.filters.route = "";
        state.filters.bus = "";
        state.filters.passenger_type = "";
        $("fiPeriod").value = state.filters.period;
        $("fiYear").value = state.filters.year;
        $("fiQuarter").value = state.filters.quarter;
        $("fiFrom").value = "";
        $("fiTo").value = "";
        $("fiRoute").value = "";
        $("fiBus").value = "";
        $("fiPassengerType").value = "";
        filterErrorMessage("");
        syncPeriodFields();
        applyFilters();
    }

    function showPendingRange() {
        var label = selectedLabel("fiPeriod") || "selected period";
        $("fiPeriodSummary").innerHTML =
            '<span class="fi-summary-note"><span class="fi-spinner"></span>Loading ' + esc(label) + "\u2026</span>" + filterChips(state.filters);
    }

    function failSection(section, error) {
        showStatus(section, "error", error.message);
        state.rendered[signature() + "::" + section.toLowerCase()] = false;
        $("fiPeriodSummary").innerHTML =
            '<span class="fi-summary-note error">Filters applied \u2014 data could not be loaded.</span>' + filterChips(state.filters);
    }

    function showPeriodLabel(period, available) {
        var label = period && period.label ? period.label : "Selected period";
        var coverage = available === false ? ' <span class="fi-range-pill">no comparable previous window</span>' : "";
        var previous = period && period.previous_from
            ? " \u00b7 previous " + period.previous_from + " to " + period.previous_to
            : "";
        var filters = {
            route: (period && period.route) || state.filters.route,
            bus: (period && period.bus) || state.filters.bus,
            passenger_type: (period && period.passenger_type) || state.filters.passenger_type
        };
        $("fiPeriodSummary").innerHTML = esc(label) + " \u00b7 " + esc((period && period.from) || "") + " to " + esc((period && period.to) || "") + esc(previous) + filterChips(filters) + coverage;
    }

    function updateCompleteness(estimated) {
        var badge = $("fiCompletenessBadge");
        if (estimated) {
            badge.className = "fi-status-badge estimate";
            badge.innerHTML = '<i class="bi bi-exclamation-circle"></i> Estimated \u2014 incomplete expense records';
        } else {
            badge.className = "fi-status-badge actual";
            badge.innerHTML = '<i class="bi bi-check2-circle"></i> Actual records';
        }
    }

    /* ---------------- overview ---------------- */

    function renderOverview(data) {
        var actual = data.actual;
        var change = data.change || {};
        var previousAvailable = data.previous_available === true;
        showPeriodLabel(data.period, previousAvailable);
        updateCompleteness(data.expense_completeness.estimated);

        var fuelShare = Number(actual.expenses) > 0
            ? multi(actual.fuel / actual.expenses * 100, 1) + "% of recorded expenses is fuel."
            : "No recorded expenses for this period.";
        var profitNegative = actual.margin_after_configured !== null && Number(actual.margin_after_configured) < 0;
        var periodLabel = (data.period && data.period.label) ? data.period.label : "Selected period";
        var comparisonNote = kpiComparisonText(data.period, previousAvailable, periodLabel);
        /* Each card states the period it covers and the period it is compared
           against, so a value or trend is never ambiguous. */
        var kpiFooter = periodLabel + (comparisonNote ? " \u00b7 " + comparisonNote : "");

        $("fiOverviewKpis").innerHTML =
            kpiCard("Fare Collections", peso(actual.collections), "Baggage " + peso(actual.baggage_collections) + " recorded separately.", "", "actual", change.collections_pct, previousAvailable, "bi-cash-coin", kpiFooter) +
            kpiCard("Recorded Expenses", peso(actual.expenses), fuelShare, "", "actual", change.expenses_pct, previousAvailable, "bi-receipt", kpiFooter) +
            kpiCard("Estimated Operating Profit", peso(actual.profit_after_configured), "Collections minus recorded expenses and " + peso(actual.configured_costs) + " in configured estimates.", profitNegative ? "down" : "", "estimate", change.profit_after_configured_pct, previousAvailable, "bi-graph-up-arrow", kpiFooter) +
            kpiCard("Completed Trips", whole(actual.trips), whole(actual.passengers) + " passengers recorded.", "", "actual", null, previousAvailable, "bi-people", kpiFooter);

        renderScope("fiScopeNotes", data.scope);

        var daily = data.series.daily || [];
        var trendHasData = daily.some(function (d) { return Number(d.collections) > 0 || Number(d.expenses) > 0; });
        $("fiOverviewSeriesSub").textContent = "Daily fare collections vs recorded expenses.";
        chartOrEmpty("fiChartTrend", trendHasData, lineConfig(
            daily.map(function (d) { return d.key; }),
            [
                { label: "Collections", data: daily.map(function (d) { return d.collections; }), borderColor: COLORS.green, backgroundColor: "rgba(25,135,84,0.12)", fill: true, tension: 0.35, pointRadius: 2 },
                { label: "Expenses", data: daily.map(function (d) { return d.expenses; }), borderColor: COLORS.red, backgroundColor: "rgba(198,40,40,0.08)", fill: true, tension: 0.35, pointRadius: 2 }
            ], true), "No collections or recorded expenses for this selection.");

        renderPaymentBreakdown(data.payment_methods || []);

        dataTable("fiOverviewRoutes", [
            { key: "route", label: "Route", type: "text" },
            { key: "trips", label: "Trips", type: "number", num: true },
            { key: "collections", label: "Collections", type: "money", num: true },
            { key: "expenses", label: "Expenses", type: "money", num: true },
            { key: "profit", label: "Estimated profit", num: true, render: function (row) { return row.cost_complete ? peso(row.profit) : '<span class="fi-badge warn">incomplete costs</span>'; } }
        ], data.by_route || []);

        var byRoute = data.by_route || [];
        var routeTrips = byRoute.reduce(function (sum, row) { return sum + Number(row.trips || 0); }, 0);
        var routeCollections = byRoute.reduce(function (sum, row) { return sum + Number(row.collections || 0); }, 0);
        $("fiRouteMeta").innerHTML = byRoute.length
            ? "<b>" + whole(byRoute.length) + "</b> route(s) \u00b7 <b>" + whole(routeTrips) + "</b> trips \u00b7 <b>" + peso(routeCollections) + "</b> route-attributed collections"
            : "";

        methodNotes("fiOverviewNotes", (data.expense_completeness.notes || []).concat(data.notes || []));
    }

    /* Compact payment breakdown: only the recorded methods are shown; a
       method with no records is never invented. */
    function paymentMethodLabel(method) {
        var value = String(method || "").toLowerCase();
        if (value === "cash") return "Cash";
        if (value === "gcash" || value === "qrph" || value === "online" || value === "paymongo") return "QRPh (Online)";
        return method || "Unspecified";
    }

    function paymentMethodKind(method) {
        var value = String(method || "").toLowerCase();
        if (value === "cash") return "cash";
        if (value === "gcash" || value === "qrph" || value === "online" || value === "paymongo") return "qrph";
        return "neutral";
    }

    function paymentMethodIcon(method) {
        var kind = paymentMethodKind(method);
        if (kind === "cash") return "bi-cash-coin";
        if (kind === "qrph") return "bi-qr-code";
        return "bi-credit-card-2-front";
    }

    function renderPaymentBreakdown(paymentMix) {
        var host = $("fiPaymentBreakdown");
        if (!host) return;

        var total = paymentMix.reduce(function (sum, row) { return sum + Number(row.collections || 0); }, 0);
        $("fiPaymentSub").textContent = paymentMix.length
            ? "Sums to " + peso(total) + " of fare collections."
            : "Collections by recorded payment method.";

        if (!paymentMix.length) {
            host.innerHTML = '<div class="fi-empty compact">No collections by payment method for this selection.</div>';
            return;
        }

        host.innerHTML = '<div class="fi-paymix">' + paymentMix.map(function (row) {
            var amount = Number(row.collections || 0);
            var share = total > 0 ? amount / total * 100 : 0;
            var kind = paymentMethodKind(row.method);
            return '<div class="fi-paymix-row">' +
                '<span class="fi-paymix-icon ' + kind + '"><i class="bi ' + paymentMethodIcon(row.method) + '"></i></span>' +
                '<div class="fi-paymix-main">' +
                    '<div class="fi-paymix-head"><span class="fi-paymix-label">' + esc(paymentMethodLabel(row.method)) + "</span>" +
                    '<span class="fi-paymix-value">' + peso(amount) + "<em>" + share.toFixed(1) + "%</em></span></div>" +
                    '<div class="fi-paymix-bar ' + kind + '"><span style="width:' + Math.max(0, Math.min(100, share)).toFixed(1) + '%"></span></div>' +
                "</div>" +
                "</div>";
        }).join("") +
        '<div class="fi-paymix-total"><span>Total fare collections</span><b>' + peso(total) + "</b></div>" +
        "</div>";
    }

    function loadOverview(force) {
        var token = trackSection("overview", "Preparing financial overview\u2026");
        return request("overview", null, force).then(function (payload) {
            if (!markLoaded(token, payload)) { return; }
            renderOverview(payload.data);
        }).catch(function (error) {
            if (!isCurrent(token)) { return; }
            failSection("Overview", error);
        });
    }

    /* ---------------- passengers ---------------- */

    function renderPassengers(data) {
        showPeriodLabel(data.period, data.previous_available);
        var types = data.types || [];
        var baggage = data.baggage;
        var totalTrips = types.reduce(function (sum, t) { return sum + t.trips; }, 0);

        $("fiPassengerKpis").innerHTML =
            kpi("Passenger categories", whole(types.length), "Excluding baggage") +
            kpi("Completed passenger trips", whole(totalTrips), "Within the selected period") +
            kpi("Baggage &amp; extra fares", peso(baggage.collections), whole(baggage.trips) + " recorded entries") +
            kpi("Estimated discount impact", peso(data.discount_summary.total_estimated_discount), "Versus Regular average fare");

        var palette = [COLORS.green, COLORS.soft, COLORS.light, COLORS.gold, COLORS.red, "#8d6e63", "#78909c"];
        chartOrEmpty("fiChartPaxDoughnut", types.length > 0, doughnutConfig(
            types.map(function (t) { return t.type; }),
            types.map(function (t) { return t.collections; }),
            palette.slice(0, types.length)), "No passenger-type collections for this selection.");

        chartOrEmpty("fiChartPaxTrips", totalTrips > 0, barConfig(
            types.map(function (t) { return t.type; }),
            [{ label: "Completed trips", data: types.map(function (t) { return t.trips; }), backgroundColor: COLORS.soft, borderRadius: 5 }],
            false), "No completed passenger trips for this selection.");

        var months = data.monthly || [];
        var typeNames = types.map(function (t) { return t.type; });
        chartOrEmpty("fiChartPaxMonthly", months.length > 0, lineConfig(
            months.map(function (m) { return m.month; }),
            typeNames.map(function (name, index) {
                return {
                    label: name,
                    data: months.map(function (m) { return m.types[name] || 0; }),
                    borderColor: palette[index % palette.length],
                    backgroundColor: "rgba(25,135,84,0.06)",
                    tension: 0.35,
                    pointRadius: 2
                };
            }), true), "No monthly passenger collections for this selection.");

        var routes = data.by_route || [];
        chartOrEmpty("fiChartPaxRoutes", routes.length > 0, {
            type: "bar",
            data: {
                labels: routes.map(function (r) { return r.route; }),
                datasets: typeNames.map(function (name, index) {
                    return {
                        label: name,
                        data: routes.map(function (r) { return r.types[name] || 0; }),
                        backgroundColor: palette[index % palette.length],
                        stack: "pax",
                        borderRadius: 3
                    };
                })
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: "#33453a", font: { size: 10 }, usePointStyle: true, boxWidth: 8 } } },
                scales: Object.assign(baseScales(false), { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, grid: { color: "rgba(0,0,0,0.05)" } } })
            }
        }, "No passenger distribution by route for this selection.");

        chartOrEmpty("fiChartPaxAvgFare", types.length > 0, barConfig(
            types.map(function (t) { return t.type; }),
            [{
                label: "Average realized fare",
                data: types.map(function (t) { return t.avg_fare; }),
                backgroundColor: COLORS.gold,
                borderRadius: 5
            }], true), "No realized fares for this selection.");

        dataTable("fiPassengerTable", [
            { key: "type", label: "Passenger type", type: "text" },
            { key: "trips", label: "Trips", type: "number", num: true },
            { key: "collections", label: "Collections", type: "money", num: true },
            { key: "avg_fare", label: "Average realized fare", type: "money", num: true },
            { key: "share_pct", label: "Share", type: "pct", num: true },
            { key: "discount_impact_estimate", label: "Estimated discount impact", type: "money", num: true }
        ], types.concat([{
            type: baggage.category,
            trips: baggage.trips,
            collections: baggage.collections,
            avg_fare: baggage.avg_fare,
            share_pct: null,
            discount_impact_estimate: null
        }]));

        $("fiPassengerDiscountNote").textContent = "Estimated foregone fare compared with the Regular average.";
        $("fiPassengerDiscountNote").title = data.discount_summary.note;
    }

    function loadPassengers(force) {
        var token = trackSection("passengers", "Computing passenger type analysis\u2026");
        return request("passengers", null, force).then(function (payload) {
            if (!markLoaded(token, payload)) { return; }
            renderPassengers(payload.data);
        }).catch(function (error) {
            if (!isCurrent(token)) { return; }
            failSection("Passengers", error);
        });
    }

    /* ---------------- fare ---------------- */

    function renderFare(data) {
        showPeriodLabel(data.period, data.previous_available);
        var applied = data.applied;
        var tracking = data.change_tracking;

        $("fiFareKpis").innerHTML =
            kpi("Fare matrix segments", whole(data.segment_count), data.routes.length + " route(s)") +
            kpi("Matrix match coverage", applied.coverage_pct === null ? "\u2014" : multi(applied.coverage_pct, 1) + "%", applied.matched_rows + " of " + applied.total_rows + " recorded fares") +
            kpi("Expected at current matrix", peso(applied.expected_collections), "Estimate for matched trips") +
            kpi("Actual matched collections", peso(applied.actual_collections), "Recorded fares for the same trips") +
            kpi("Alignment variance", peso(applied.variance), applied.variance >= 0 ? "Collections below matrix" : "Collections above matrix") +
            kpi("Matrix last updated", esc(tracking.last_updated ? tracking.last_updated : "\u2014"), tracking.observable ? "Before/after comparison available" : "Change not observable in this range");

        var routes = applied.by_route || [];
        chartOrEmpty("fiChartFare", routes.length > 0, barConfig(
            routes.map(function (r) { return r.route; }),
            [
                { label: "Actual collections", data: routes.map(function (r) { return r.actual_collections; }), backgroundColor: COLORS.green, borderRadius: 5 },
                { label: "Expected at matrix", data: routes.map(function (r) { return r.expected_collections; }), backgroundColor: COLORS.gold, borderRadius: 5 }
            ], true), "No matrix-matched trips for this selection.");

        if (!tracking.observable) {
            $("fiFareChangeTracking").innerHTML = '<div class="fi-empty">' + esc(tracking.basis || "Not observable in this range.") + "</div>";
        } else {
            var before = tracking.before || {};
            var after = tracking.after || {};
            $("fiFareChangeTracking").innerHTML =
                '<table class="fi-table"><thead><tr><th>Window</th><th class="num">Collections</th><th class="num">Average realized fare</th><th class="num">Passengers</th></tr></thead><tbody>' +
                "<tr><td>Before update<br><small>" + esc(before.from) + " to " + esc(before.to) + "</small></td><td class='num'>" + peso(before.collections) + "</td><td class='num'>" + (before.avg_fare === null ? "\u2014" : peso(before.avg_fare)) + "</td><td class='num'>" + whole(before.passengers) + "</td></tr>" +
                "<tr><td>After update<br><small>" + esc(after.from) + " to " + esc(after.to) + "</small></td><td class='num'>" + peso(after.collections) + "</td><td class='num'>" + (after.avg_fare === null ? "\u2014" : peso(after.avg_fare)) + "</td><td class='num'>" + whole(after.passengers) + "</td></tr>" +
                "</tbody></table>" +
                '<div class="fi-inline-note">Average realized fare change: <b>' + (tracking.avg_fare_change === null ? "\u2014" : peso(tracking.avg_fare_change)) + "</b>. " + esc(tracking.note || "") + "</div>";
        }

        dataTable("fiFareSegments", [
            { key: "route_name", label: "Route", type: "text" },
            { key: "destination", label: "Destination", type: "text" },
            { key: "regular_fare", label: "Regular", type: "money", num: true },
            { key: "student_fare", label: "Student / Senior / PWD", type: "money", num: true },
            { key: "special_fare", label: "Special", type: "money", num: true },
            { key: "display_order", label: "Stop order", type: "number", num: true }
        ], data.segments || []);

        dataTable("fiFareAppliedTable", [
            { key: "route", label: "Route", type: "text" },
            { key: "matched_rows", label: "Matched fares", type: "number", num: true },
            { key: "actual_collections", label: "Actual collections", type: "money", num: true },
            { key: "expected_collections", label: "Expected at matrix", type: "money", num: true },
            { key: "variance", label: "Variance", type: "money", num: true },
            { key: "variance_pct", label: "Variance %", type: "pct", num: true }
        ], applied.by_route || []);

        $("fiFareAppliedNote").textContent = "Matched trip routes only; unmatched trips are excluded.";
        $("fiFareAppliedNote").title = applied.note;
    }

    function loadFare(force) {
        var token = trackSection("fare", "Comparing fare matrix with recorded collections\u2026");
        return request("fare", null, force).then(function (payload) {
            if (!markLoaded(token, payload)) { return; }
            renderFare(payload.data);
        }).catch(function (error) {
            if (!isCurrent(token)) { return; }
            failSection("Fare", error);
        });
    }

    /* ---------------- fuel ---------------- */

    function renderFuel(data) {
        showPeriodLabel(data.period, data.previous_available);
        var summary = data.summary;
        var dieselPricePerLiter = data.latest_price ? Number(data.latest_price.price_per_liter) : null;
        var hasDieselPrice = dieselPricePerLiter !== null && dieselPricePerLiter > 0;
        var measuredKpl = summary.avg_kpl === null || summary.avg_kpl === undefined ? null : Number(summary.avg_kpl);
        var costPerKm = hasDieselPrice && measuredKpl !== null && measuredKpl > 0
            ? dieselPricePerLiter / measuredKpl
            : null;
        var purchaseLiters = Number(summary.liters || 0);
        var purchaseCount = Number(summary.purchases || 0);
        var purchaseCost = hasDieselPrice ? purchaseLiters * dieselPricePerLiter : Number(summary.purchase_cost || 0);
        var noPurchases = purchaseLiters <= 0 && purchaseCount <= 0;
        renderScope("fiFuelScope", data.scope);
        if (noPurchases && Number(summary.trips_measured || 0) <= 0) {
            var window = data.data_window || {};
            var periodFrom = data.period && data.period.from ? data.period.from : "";
            var periodTo = data.period && data.period.to ? data.period.to : "";
            var windowNote = window.from && window.to
                ? " Recorded fuel data covers " + esc(window.from) + " to " + esc(window.to) + "."
                : " No fuel records exist in the database yet.";
            $("fiFuelScope").innerHTML += '<div><i class="bi bi-exclamation-circle"></i> No fuel records found in this period (' + esc(periodFrom) + " to " + esc(periodTo) + ")." + windowNote + "</div>";
        }
        $("fiFuelKpis").innerHTML =
            kpi("Purchased fuel", multi(summary.liters, 2) + " L", noPurchases ? "No purchases recorded for this period" : whole(summary.purchases) + " purchase records") +
            kpi("Fuel purchase cost", peso(purchaseCost), noPurchases ? "No purchases recorded for this period" : multi(purchaseLiters, 2) + " L" + (hasDieselPrice ? " \u00d7 diesel " + peso(dieselPricePerLiter) + " / L" : "")) +
            kpi("Cost per kilometer", costPerKm === null ? "\u2014" : peso(costPerKm), costPerKm === null ? (measuredKpl !== null && measuredKpl > 0 ? "No diesel price recorded" : "No measured km/L for this period") : peso(dieselPricePerLiter) + " / L \u00f7 " + multi(measuredKpl, 2) + " km/L") +
            kpi("Average efficiency", summary.avg_kpl === null ? "\u2014" : multi(summary.avg_kpl, 2) + " km/L", summary.avg_kpl === null ? "No odometer records for this period" : "From odometer records") +
            kpi("Latest diesel price", data.latest_price ? peso(data.latest_price.price_per_liter) + " / L" : "\u2014", data.latest_price ? esc(data.latest_price.updated_at) : "No price record");

        var monthly = data.monthly || [];
        chartOrEmpty("fiChartFuelPrice", monthly.length > 0, {
            type: "line",
            data: {
                labels: monthly.map(function (m) { return m.month; }),
                datasets: [
                    { label: "Average price / L", data: monthly.map(function (m) { return m.avg_price; }), borderColor: COLORS.green, backgroundColor: "rgba(25,135,84,0.10)", tension: 0.35, pointRadius: 2, yAxisID: "y" },
                    { label: "Purchased liters", data: monthly.map(function (m) { return m.liters; }), borderColor: COLORS.gold, backgroundColor: "rgba(217,164,65,0.10)", tension: 0.35, pointRadius: 2, yAxisID: "y1" }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                plugins: { legend: { labels: { color: "#33453a", font: { size: 10 }, usePointStyle: true, boxWidth: 8 } } },
                scales: {
                    y: { beginAtZero: true, position: "left", grid: { color: "rgba(0,0,0,0.05)" }, ticks: { color: COLORS.grey, font: { size: 10 } } },
                    y1: { beginAtZero: true, position: "right", grid: { display: false }, ticks: { color: COLORS.grey, font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { color: COLORS.grey, font: { size: 10 } } }
                }
            }
        }, "No fuel purchases for this selection.");

        var buses = data.by_bus || [];
        chartOrEmpty("fiChartFuelBus", buses.length > 0, barConfig(
            buses.map(function (b) { return b.bus; }),
            [{ label: "Fuel cost", data: buses.map(function (b) { return b.cost; }), backgroundColor: COLORS.soft, borderRadius: 5 }],
            true), "No fuel cost by bus for this selection.");

        var kplBuses = buses.filter(function (b) { return b.avg_kpl !== null; });
        chartOrEmpty("fiChartFuelKpl", kplBuses.length > 0, barConfig(
            kplBuses.map(function (b) { return b.bus; }),
            [{ label: "km per liter", data: kplBuses.map(function (b) { return b.avg_kpl; }), backgroundColor: COLORS.light, borderRadius: 5 }],
            false), "No odometer efficiency records for this selection.");

        var routes = data.by_route.routes || [];
        chartOrEmpty("fiChartFuelRoute", routes.length > 0, barConfig(
            routes.map(function (r) { return r.route; }),
            [{ label: "Fuel cost", data: routes.map(function (r) { return r.cost; }), backgroundColor: COLORS.gold, borderRadius: 5 }],
            true), "No attributable fuel cost by route for this selection.");

        chartOrEmpty("fiChartFuelCostKm", monthly.length > 0, {
            type: "line",
            data: {
                labels: monthly.map(function (m) { return m.month; }),
                datasets: [
                    { label: "Cost per km", data: monthly.map(function (m) { return m.cost_per_km; }), borderColor: COLORS.green, backgroundColor: "rgba(25,135,84,0.10)", tension: 0.35, pointRadius: 2, yAxisID: "y" },
                    { label: "Cost per trip", data: monthly.map(function (m) { return m.cost_per_trip; }), borderColor: COLORS.gold, backgroundColor: "rgba(217,164,65,0.10)", tension: 0.35, pointRadius: 2, yAxisID: "y1" }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                plugins: { legend: { labels: { color: "#33453a", font: { size: 10 }, usePointStyle: true, boxWidth: 8 } } },
                scales: {
                    y: {
                        beginAtZero: true, position: "left", grid: { color: "rgba(0,0,0,0.05)" },
                        ticks: { color: COLORS.grey, font: { size: 10 }, callback: function (value) { return "\u20b1" + whole(value); } }
                    },
                    y1: {
                        beginAtZero: true, position: "right", grid: { display: false },
                        ticks: { color: COLORS.grey, font: { size: 10 }, callback: function (value) { return "\u20b1" + whole(value); } }
                    },
                    x: { grid: { display: false }, ticks: { color: COLORS.grey, font: { size: 10 } } }
                }
            }
        }, "No monthly fuel metrics for this selection.");

        dataTable("fiFuelBusTable", [
            { key: "bus", label: "Bus", type: "text" },
            { key: "liters", label: "Liters", type: "number", num: true, digits: 2 },
            { key: "cost", label: "Cost", type: "money", num: true },
            { key: "avg_kpl", label: "KPL", type: "number", num: true, digits: 2 },
            { key: "cost_per_km", label: "Cost / km", type: "money", num: true },
            { key: "cost_per_liter", label: "Cost / L", type: "money", num: true },
            { key: "purchases", label: "Purchases", type: "number", num: true }
        ], buses);

        dataTable("fiFuelPriceTable", [
            { key: "fuel_type", label: "Fuel", type: "text" },
            { key: "price_per_liter", label: "Price / L", type: "money", num: true },
            { key: "updated_at", label: "Recorded", type: "text" }
        ], (data.prices || []).slice().reverse());

        var policy = data.double_counting.policy;
        if (data.by_route.unattributed.cost > 0) {
            policy += " Unattributed purchases this period: " + peso(data.by_route.unattributed.cost) + ".";
        }
        $("fiFuelPolicy").textContent = "Fuel is counted once: recorded expenses first, otherwise actual receipts.";
        $("fiFuelPolicy").title = policy;
    }

    function loadFuel(force) {
        var token = trackSection("fuel", "Analyzing fuel costs and efficiency\u2026");
        return request("fuel", null, force).then(function (payload) {
            if (!markLoaded(token, payload)) { return; }
            renderFuel(payload.data);
        }).catch(function (error) {
            if (!isCurrent(token)) { return; }
            failSection("Fuel", error);
        });
    }

    /* ---------------- fleet ---------------- */

    function renderFleet(data) {
        showPeriodLabel(data.period, data.previous_available);
        var routes = data.routes || [];
        var eligible = routes.filter(function (r) { return r.ranking_eligible; });
        var topCost = routes.slice().sort(function (a, b) { return (b.attributable_expenses || 0) - (a.attributable_expenses || 0); })[0];
        var lowMargin = eligible.slice().sort(function (a, b) { return (a.estimated_margin || 0) - (b.estimated_margin || 0); })[0];

        $("fiFleetKpis").innerHTML =
            kpi("Routes with activity", whole(routes.length), eligible.length + " with complete cost records") +
            kpi("Highest attributable cost", topCost ? peso(topCost.attributable_expenses) : "\u2014", topCost ? esc(topCost.route) : "No route records") +
            kpi("Lowest estimated margin", lowMargin ? pct(lowMargin.estimated_margin) : "\u2014", lowMargin ? esc(lowMargin.route) : "Not enough cost records") +
            kpi("Unattributed collections", peso(data.unattributed.collections), whole(data.unattributed.trips) + " trip rows without a route match");

        chartOrEmpty("fiChartFleetCollections", routes.length > 0, {
            type: "bar",
            data: {
                labels: routes.map(function (r) { return r.route; }),
                datasets: [{ label: "Collections", data: routes.map(function (r) { return r.collections; }), backgroundColor: COLORS.green, borderRadius: 5 }]
            },
            options: {
                indexAxis: "y", responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: "rgba(0,0,0,0.05)" }, ticks: { color: COLORS.grey, font: { size: 10 }, callback: function (v) { return "\u20b1" + whole(v); } } },
                    y: { grid: { display: false }, ticks: { color: COLORS.grey, font: { size: 10 } } }
                }
            }
        }, "No route collections for this selection.");

        chartOrEmpty("fiChartFleetMargin", eligible.length > 0, barConfig(
            eligible.map(function (r) { return r.route; }),
            [{
                label: "Estimated margin %",
                data: eligible.map(function (r) { return r.estimated_margin; }),
                backgroundColor: eligible.map(function (r) { return r.estimated_margin < 0 ? COLORS.red : COLORS.soft; }),
                borderRadius: 5
            }], false), "No routes with complete cost records for margin analysis.");

        chartOrEmpty("fiChartFleetCostMix", routes.length > 0, {
            type: "bar",
            data: {
                labels: routes.map(function (r) { return r.route; }),
                datasets: [
                    { label: "Fuel", data: routes.map(function (r) { return r.fuel_cost; }), backgroundColor: COLORS.green, stack: "cost", borderRadius: 3 },
                    { label: "Maintenance", data: routes.map(function (r) { return r.maintenance_cost; }), backgroundColor: COLORS.gold, stack: "cost", borderRadius: 3 },
                    {
                        label: "Other",
                        data: routes.map(function (r) {
                            return Math.max(0, Number(r.attributable_expenses || 0) - Number(r.fuel_cost || 0) - Number(r.maintenance_cost || 0));
                        }),
                        backgroundColor: COLORS.light, stack: "cost", borderRadius: 3
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: "#33453a", font: { size: 10 }, usePointStyle: true, boxWidth: 8 } } },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: { color: COLORS.grey, font: { size: 10 } } },
                    y: {
                        stacked: true, beginAtZero: true, grid: { color: "rgba(0,0,0,0.05)" },
                        ticks: { color: COLORS.grey, font: { size: 10 }, callback: function (value) { return "\u20b1" + whole(value); } }
                    }
                }
            }
        }, "No route cost records for this selection.");

        dataTable("fiFleetRoutesTable", [
            { key: "route", label: "Route", type: "text" },
            { key: "trips", label: "Trips", type: "number", num: true },
            { key: "passengers", label: "Passengers", type: "number", num: true },
            { key: "collections", label: "Collections", type: "money", num: true },
            { key: "fuel_cost", label: "Fuel cost", type: "money", num: true },
            { key: "maintenance_cost", label: "Maintenance", type: "money", num: true },
            { key: "attributable_expenses", label: "Attributable costs", type: "money", num: true },
            { key: "cost_per_trip", label: "Cost / trip", type: "money", num: true },
            { key: "estimated_profit", label: "Estimated profit", num: true, render: function (row) { return row.cost_complete ? peso(row.estimated_profit) : '<span class="fi-badge warn">incomplete costs</span>'; } },
            { key: "estimated_margin", label: "Estimated margin", num: true, render: function (row) { return row.cost_complete ? pct(row.estimated_margin) : "\u2014"; } }
        ], routes);

        dataTable("fiFleetBusesTable", [
            { key: "bus", label: "Bus", type: "text" },
            { key: "trips", label: "Trips", type: "number", num: true },
            { key: "passengers", label: "Passengers", type: "number", num: true },
            { key: "collections", label: "Collections", type: "money", num: true },
            { key: "fuel_cost", label: "Fuel cost", type: "money", num: true },
            { key: "attributable_expenses", label: "Attributable costs", type: "money", num: true },
            { key: "cost_per_trip", label: "Cost / trip", type: "money", num: true },
            { key: "estimated_profit", label: "Estimated profit", num: true, render: function (row) { return row.cost_complete ? peso(row.estimated_profit) : '<span class="fi-badge warn">incomplete costs</span>'; } },
            { key: "estimated_margin", label: "Estimated margin", num: true, render: function (row) { return row.cost_complete ? pct(row.estimated_margin) : "\u2014"; } }
        ], data.buses || []);

        methodNotes("fiFleetNotes", scopeMessages(data.scope).concat(data.notes || []));
    }

    function loadFleet(force) {
        var token = trackSection("fleet", "Ranking routes and buses\u2026");
        return request("fleet", null, force).then(function (payload) {
            if (!markLoaded(token, payload)) { return; }
            renderFleet(payload.data);
        }).catch(function (error) {
            if (!isCurrent(token)) { return; }
            failSection("Fleet", error);
        });
    }

    /* ---------------- remittance ---------------- */

    function renderRemittance(data) {
        showPeriodLabel(data.period, data.previous_available);
        renderScope("fiRemitScope", data.scope);
        var summary = data.summary;
        $("fiRemittanceKpis").innerHTML =
            kpi("Expected cash", peso(summary.expected_cash), whole(summary.remittance_count) + " remittance records") +
            kpi("Actual remitted cash", peso(summary.actual_cash), summary.remittance_rate === null ? "No expectation" : multi(summary.remittance_rate, 1) + "% of expected") +
            kpi("Shortages", peso(summary.shortage), "Open exposure " + peso(summary.outstanding_amount)) +
            kpi("Overages", peso(summary.overage), "Not treated as revenue") +
            kpi("Unresolved discrepancies", whole(summary.unresolved_cases), peso(summary.unresolved_amount) + " outstanding") +
            kpi("Recovered amounts", peso(summary.recovered_amount), "Posted cash/payroll recoveries");

        var monthly = data.monthly || [];
        chartOrEmpty("fiChartRemitMonthly", monthly.length > 0, barConfig(
            monthly.map(function (m) { return m.month; }),
            [
                { label: "Expected cash", data: monthly.map(function (m) { return m.expected; }), backgroundColor: COLORS.grey, borderRadius: 5 },
                { label: "Actual cash", data: monthly.map(function (m) { return m.actual; }), backgroundColor: COLORS.green, borderRadius: 5 }
            ], true), "No remittance records for this selection.");

        var statuses = data.statuses || [];
        chartOrEmpty("fiChartRemitStatus", statuses.length > 0, doughnutConfig(
            statuses.map(function (s) { return s.status; }),
            statuses.map(function (s) { return s.amount; }),
            [COLORS.green, COLORS.gold, COLORS.red, COLORS.soft, COLORS.light]), "No remittance status records for this selection.");

        dataTable("fiRemitCases", [
            { key: "case_ref", label: "Case", type: "text" },
            { key: "driver_id", label: "Driver", type: "text" },
            { key: "business_date", label: "Business date", type: "text" },
            { key: "case_type", label: "Type", type: "text" },
            { key: "status", label: "Status", type: "text" },
            { key: "expected", label: "Expected", type: "money", num: true },
            { key: "actual", label: "Actual", type: "money", num: true },
            { key: "discrepancy", label: "Discrepancy", type: "money", num: true },
            { key: "recovered", label: "Recovered", type: "money", num: true },
            { key: "outstanding", label: "Outstanding", type: "money", num: true },
            { key: "open", label: "State", render: function (row) { return row.open ? '<span class="fi-badge bad">open</span>' : '<span class="fi-badge ok">resolved</span>'; } }
        ], (data.discrepancies && data.discrepancies.cases) || []);

        methodNotes("fiRemitNotes", data.notes || []);
    }

    function loadRemittance(force) {
        var token = trackSection("remittance", "Reconciling remittances and discrepancies\u2026");
        return request("remittance", null, force).then(function (payload) {
            if (!markLoaded(token, payload)) { return; }
            renderRemittance(payload.data);
        }).catch(function (error) {
            if (!isCurrent(token)) { return; }
            failSection("Remittance", error);
        });
    }

    /* ---------------- forecast ---------------- */

    function renderForecast(data) {
        showPeriodLabel(data.period, data.previous_available);
        if (!data.available) {
            /* Insufficient history is a data limitation, not an empty result:
               state it explicitly instead of presenting an empty dataset. */
            showStatus("Forecast", "notice", data.message || "Forecast unavailable for the selected filters.");
            return;
        }
        showStatus("Forecast", null);
        renderScope("fiForecastScope", data.scope);
        $("fiForecastKpis").innerHTML =
            kpi("Forecast passengers", whole(data.passengers.forecast_total), whole(data.passengers.range_low) + " \u2013 " + whole(data.passengers.range_high) + " range") +
            kpi("Forecast collections", peso(data.collections.forecast_total), peso(data.collections.range_low) + " \u2013 " + peso(data.collections.range_high)) +
            kpi("Forecast fuel cost", peso(data.fuel.cost_estimate), data.fuel.available ? multi(data.fuel.liters, 2) + " L projected" : "Fuel estimate unavailable") +
            kpi("Forecast other expenses", peso(data.other_expenses.forecast_total), "Daily run-rate basis") +
            kpi("Estimated operating profit", peso(data.profit.estimate), peso(data.profit.range_low) + " \u2013 " + peso(data.profit.range_high), data.profit.estimate < 0 ? "down" : "up") +
            kpi("Uncertainty", esc(data.uncertainty.level), "Forecast, not actual");

        var labels = [];
        var historical = [];
        var forecast = [];
        var low = [];
        var high = [];
        (data.history || []).forEach(function (row) {
            labels.push(String(row.date).slice(5));
            historical.push(Number(row.passengers) || 0);
            forecast.push(null);
            low.push(null);
            high.push(null);
        });
        (data.forecast_points || []).forEach(function (row) {
            labels.push(String(row.date).slice(5));
            historical.push(null);
            forecast.push(Number(row.value) || 0);
            low.push(row.lower === null || row.lower === undefined ? null : Number(row.lower));
            high.push(row.upper === null || row.upper === undefined ? null : Number(row.upper));
        });
        chartOrEmpty("fiChartForecast", labels.length > 0, lineConfig(labels, [
            { label: "Upper range", data: high, borderWidth: 0, backgroundColor: "rgba(217,164,65,0.16)", pointRadius: 0, fill: "+1" },
            { label: "Lower range", data: low, borderWidth: 0, backgroundColor: "rgba(217,164,65,0.16)", pointRadius: 0, fill: false },
            { label: "Actual history", data: historical, borderColor: COLORS.green, backgroundColor: "rgba(25,135,84,0.10)", tension: 0.35, pointRadius: 1.5, fill: true },
            { label: "Forecast", data: forecast, borderColor: COLORS.gold, backgroundColor: "rgba(217,164,65,0.08)", borderDash: [6, 4], tension: 0.35, pointRadius: 2 }
        ], false), "No history or forecast points for this selection.");

        $("fiForecastRange").textContent = "History " + (data.basis.history_from || "\u2014") + " to " + (data.basis.history_to || "\u2014") +
            " \u00b7 " + data.horizon + " horizon \u00b7 average realized fare " + peso(data.basis.avg_realized_fare);

        methodNotes("fiForecastAssumptions", data.assumptions || []);

        var model = data.model || {};
        $("fiForecastModel").innerHTML =
            '<div class="fi-inline-note"><b>Model:</b> ' + esc(model.name || "\u2014") + "<br><b>Computed:</b> " + esc(model.computed_at || "\u2014") +
            "<br><b>Reliability:</b> " + esc((model.reliability && (model.reliability.label || model.reliability.level)) || "\u2014") + "</div>";
        $("fiForecastDisclaimer").textContent = data.disclaimer;
    }

    function loadForecast(force) {
        var token = trackSection("forecast", "Building forecast from historical records\u2026");
        return request("forecast", { horizon: state.horizon }, force).then(function (payload) {
            if (!markLoaded(token, payload)) { return; }
            renderForecast(payload.data);
        }).catch(function (error) {
            if (!isCurrent(token)) { return; }
            failSection("Forecast", error);
        });
    }

    /* ---------------- what-if ---------------- */

    function runWhatIf() {
        var route = $("fiWiRoute").value;
        var routePct = Number($("fiWiRoutePct").value || 0);
        var extra = {
            fare_change_pct: $("fiWiFare").value,
            volume_change_pct: $("fiWiVolume").value,
            fuel_price_change_pct: $("fiWiFuel").value,
            other_expense_change_pct: $("fiWiExpense").value
        };
        if (route && routePct !== 0) {
            var changes = {};
            changes[route] = routePct;
            extra.route_fare_changes = JSON.stringify(changes);
        }
        var token = trackSection("whatif", "Projecting scenario\u2026");
        var runButton = $("fiWiRun");
        if (runButton) { runButton.disabled = true; }
        request("whatif", extra, true).then(function (payload) {
            if (!markLoaded(token, payload)) { return; }
            renderWhatIf(payload.data);
        }).catch(function (error) {
            if (!isCurrent(token)) { return; }
            failSection("WhatIf", error);
        }).finally(function () {
            if (runButton) { runButton.disabled = false; }
        });
    }

    function renderWhatIf(data) {
        showPeriodLabel(data.period, data.previous_available);
        var diff = data.difference;
        $("fiWhatIfKpis").innerHTML =
            kpi("Projected collections", peso(data.projected.collections), peso(diff.collections) + " vs baseline", "", "estimate") +
            kpi("Projected expenses", peso(data.projected.expenses), peso(diff.expenses) + " vs baseline", "", "estimate") +
            kpi("Projected operating profit", peso(data.projected.profit), peso(diff.profit) + " vs baseline", data.projected.profit < 0 ? "down" : "up", "estimate") +
            kpi("Projected margin", pct(data.projected.margin), "Baseline " + pct(data.baseline.margin), "", "estimate");

        chartOrEmpty("fiChartWhatIf", Number(data.baseline.collections) > 0 || Number(data.baseline.expenses) > 0, barConfig(
            ["Collections", "Expenses", "Operating profit"],
            [
                { label: "Actual baseline", data: [data.baseline.collections, data.baseline.expenses, data.baseline.profit], backgroundColor: COLORS.green, borderRadius: 5 },
                { label: "Scenario projection", data: [data.projected.collections, data.projected.expenses, data.projected.profit], backgroundColor: COLORS.gold, borderRadius: 5 }
            ], true), "No recorded baseline for this selection.");

        $("fiWhatIfSummary").innerHTML =
            '<div class="fi-inline-note">' +
            "Scenario: fare " + data.scenario.fare_change_pct + "%, volume " + data.scenario.volume_change_pct + "%, fuel " + data.scenario.fuel_price_change_pct +
            "%, other expenses " + data.scenario.other_expense_change_pct + "%" +
            (Object.keys(data.scenario.route_fare_changes || {}).length ? ", route overrides applied" : "") + "." +
            "<br><b>Profit impact:</b> " + peso(diff.profit) + " versus the actual baseline." +
            "</div>";
        methodNotes("fiWhatIfAssumptions", data.assumptions || []);
        $("fiWhatIfDisclaimer").textContent = data.disclaimer;

        dataTable("fiWhatIfRoutes", [
            { key: "route", label: "Route", type: "text" },
            { key: "actual_collections", label: "Actual collections", type: "money", num: true },
            { key: "fare_change_pct", label: "Fare change %", type: "number", num: true, digits: 2 },
            { key: "projected_collections", label: "Projected collections", type: "money", num: true },
            { key: "difference", label: "Difference", type: "money", num: true }
        ], data.by_route || []);
    }

    /* ---------------- tabs ---------------- */

    var LOADERS = {
        overview: loadOverview,
        passengers: loadPassengers,
        fare: loadFare,
        fuel: loadFuel,
        fleet: loadFleet,
        remittance: loadRemittance,
        forecast: loadForecast
    };

    function loadTab(tab, force) {
        if (tab === "whatif") {
            if (force || !state.rendered[signature() + "::whatif"]) {
                state.rendered[signature() + "::whatif"] = true;
                runWhatIf();
            }
            return;
        }
        var loader = LOADERS[tab];
        if (!loader) return;
        var key = signature() + "::" + tab;
        if (!force && state.rendered[key] && !force) {
            return;
        }
        state.rendered[key] = true;
        loader(force);
    }

    function retrySection(target) {
        if (target === "options") {
            state.optionsLoaded = false;
            $("fiPeriodSummary").innerHTML = "Loading period\u2026";
            return loadOptions().then(function () {
                activateTab(state.tab || "overview");
            });
        }
        state.rendered[signature() + "::" + target] = false;
        loadTab(target, true);
        return Promise.resolve();
    }

    function activateTab(tab) {
        state.tab = tab;
        document.querySelectorAll(".fi-tab").forEach(function (btn) {
            btn.classList.toggle("active", btn.getAttribute("data-tab") === tab);
        });
        document.querySelectorAll(".fi-section").forEach(function (section) {
            section.classList.toggle("active", section.id === "fiTab-" + tab);
        });
        loadTab(tab, false);
    }

    document.querySelectorAll(".fi-tab").forEach(function (btn) {
        btn.addEventListener("click", function () { activateTab(btn.getAttribute("data-tab")); });
    });

    document.addEventListener("click", function (event) {
        var retry = event.target.closest("[data-retry]");
        if (retry) {
            retry.disabled = true;
            retrySection(retry.getAttribute("data-retry")).catch(function () {});
            return;
        }

        var header = event.target.closest("th[data-sort]");
        if (!header) return;
        var key = header.getAttribute("data-table");
        var column = header.getAttribute("data-sort");
        var current = state.tableSort[key];
        state.tableSort[key] = {
            key: column,
            dir: current && current.key === column && current.dir === "desc" ? "asc" : "desc"
        };
        renderTable(key);
    });

    var FILTER_DEBOUNCE_MS = 350;
    var filterDebounceTimer = null;

    // Automatic filters: every control change re-applies the filters after a
    // short debounce so rapid changes collapse into one request. Reset stays
    // instant.
    function scheduleAutoApply() {
        if (filterDebounceTimer !== null) {
            clearTimeout(filterDebounceTimer);
        }
        filterDebounceTimer = setTimeout(function () {
            filterDebounceTimer = null;
            if (!collectFilterControls()) {
                /* An invalid custom range keeps the previous results; say so
                   instead of leaving stale data looking freshly applied. */
                $("fiPeriodSummary").innerHTML =
                    '<span class="fi-summary-note error">Filters were not applied \u2014 fix the custom date range and they will apply automatically.</span>' +
                    filterChips(state.filters);
                return;
            }
            applyFilters();
        }, FILTER_DEBOUNCE_MS);
    }

    $("fiReset").addEventListener("click", function () {
        if (filterDebounceTimer !== null) {
            clearTimeout(filterDebounceTimer);
            filterDebounceTimer = null;
        }
        resetFilters();
    });

    ["fiPeriod", "fiYear", "fiQuarter", "fiFrom", "fiTo", "fiRoute", "fiBus", "fiPassengerType"].forEach(function (id) {
        var node = $(id);
        if (!node) { return; }
        node.addEventListener("change", function () {
            if (id === "fiPeriod") {
                state.filters.period = $("fiPeriod").value;
                syncPeriodFields();
            }
            scheduleAutoApply();
        });
    });

    $("fiHorizon").addEventListener("change", function () {
        state.horizon = $("fiHorizon").value;
        loadForecast(true);
    });

    $("fiWiRun").addEventListener("click", runWhatIf);

    (function init() {
        var query = new URLSearchParams(window.location.search);
        if (query.get("period")) state.filters.period = query.get("period");
        if (query.get("year")) state.filters.year = query.get("year");
        if (query.get("quarter")) state.filters.quarter = query.get("quarter");
        if (query.get("from")) state.filters.from = query.get("from");
        if (query.get("to")) state.filters.to = query.get("to");
        if (query.get("route")) state.filters.route = query.get("route");
        if (query.get("bus")) state.filters.bus = query.get("bus");
        if (query.get("passenger_type")) state.filters.passenger_type = query.get("passenger_type");
        loadOptions().then(function () {
            $("fiQuarter").value = state.filters.quarter;
            $("fiFrom").value = state.filters.from;
            $("fiTo").value = state.filters.to;
            $("fiRoute").value = state.filters.route;
            $("fiBus").value = state.filters.bus;
            $("fiPassengerType").value = state.filters.passenger_type;
            activateTab(state.tab || "overview");
        }).catch(function (error) {
            setStatus("fiOverviewStatus", "error", "Could not load filter options: " + error.message, "options");
            $("fiPeriodSummary").innerHTML = '<span class="fi-summary-note error">Filter options could not be loaded.</span>';
        });
    })();

    window.FinanceIntelligence = {
        state: state,
        peso: peso,
        renderOverview: renderOverview,
        renderPassengers: renderPassengers,
        renderFare: renderFare,
        renderFuel: renderFuel,
        renderFleet: renderFleet,
        renderRemittance: renderRemittance,
        renderForecast: renderForecast,
        renderWhatIf: renderWhatIf,
        renderTable: renderTable,
        retrySection: retrySection,
        activateTab: activateTab,
        applyFilters: applyFilters
    };
})();
</script>

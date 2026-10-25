<?php header('Cache-Control: no-store, no-cache, must-revalidate'); header('Pragma: no-cache'); ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">
<?php require_once __DIR__ . '/../layout/navbarfinance.php'; ?>
<style>
#mainContent {
    --fad-dark-green: #2e4836;
    --fad-skel-bg: linear-gradient(90deg, #eceed9 25%, #f9f4da 50%, #eceed9 75%);
}
.fad-header {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px 14px;
    margin-bottom: 18px;
}
.fad-title {
    font-weight: 800;
    font-size: 1.45rem;
    color: #2e4836;
    margin: 0;
    letter-spacing: -0.01em;
    line-height: 1.2;
}
.fad-header-right {
    display: flex;
    justify-content: flex-end;
    margin-left: auto;
    min-width: 0;
}
.fad-sub {
    font-weight: 500;
    font-size: 0.82rem;
    color: #8a9a8a;
    margin: 0;
}
.fad-nodata-banner {
    display: none;
    background: #f9f4da;
    border: 2px solid #e7e3af;
    border-radius: 14px;
    padding: 14px 16px;
    margin-bottom: 18px;
    font-weight: 600;
    color: #2e4836;
    font-size: 0.9rem;
}
.fad-nodata-banner i {
    color: #b9b23d;
    margin-right: 8px;
}
.fad-error-banner {
    display: none;
    align-items: center;
    gap: 12px;
    background: #fdf1f1;
    border: 2px solid #e7b9b9;
    border-radius: 14px;
    padding: 13px 16px;
    margin-bottom: 18px;
    font-weight: 600;
    color: #a83232;
    font-size: 0.88rem;
    flex-wrap: wrap;
}
.fad-error-banner i {
    color: #a83232;
    font-size: 1.05rem;
}
.fad-error-text {
    flex: 1 1 240px;
    min-width: 0;
}
.fad-error-sub {
    font-weight: 500;
    font-size: 0.78rem;
    color: #b06a6a;
    margin-top: 2px;
}
.fad-error-retry {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #a83232;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s ease;
}
.fad-error-retry:hover {
    background: #8f2a2a;
}
.fad-error-retry:disabled {
    opacity: 0.6;
    cursor: default;
}

.fad-top-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 360px);
    gap: 16px;
    margin-bottom: 20px;
    align-items: stretch;
}
.fad-kpi-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    align-items: stretch;
}
.fad-kpi {
    min-width: 0;
    background: #ffffff;
    border: 1px solid #cdd9b5;
    border-radius: 16px;
    padding: 16px 16px;
    box-shadow: 0 1px 3px rgba(46, 72, 54, 0.05);
    display: flex;
    align-items: center;
    gap: 12px;
    transition: border-color 0.15s ease;
}
.fad-kpi-icon {
    flex: 0 0 44px;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #eceed9;
    color: #2e4836;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.fad-kpi-content {
    flex: 1 1 auto;
    min-width: 0;
}
.fad-kpi-label {
    font-size: 0.68rem;
    font-weight: 600;
    color: #8a9a8a;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin: 0 0 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.fad-kpi-value {
    font-size: clamp(1rem, 1.1vw, 1.3rem);
    font-weight: 800;
    color: #2e4836;
    margin: 0;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
    word-break: break-word;
}
.fad-kpi-sub {
    font-size: 0.7rem;
    font-weight: 500;
    color: #a3b3a0;
    margin: 3px 0 0;
    line-height: 1.35;
    overflow-wrap: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.fad-kpi.kpi-net {
    border-color: #2f6f4f;
    background: linear-gradient(180deg, #ffffff 0%, #f3f8ec 100%);
    box-shadow: 0 2px 8px rgba(46, 72, 54, 0.1);
}
.fad-kpi.kpi-net .fad-kpi-icon {
    background: #dcebce;
    color: #0b6b2f;
}
.fad-kpi.kpi-net .fad-kpi-value {
    color: #0b6b2f;
}
.fad-ai-card {
    background: linear-gradient(180deg, #ffffff 0%, #f3f8ec 100%);
    border: 1px solid #2f6f4f;
    border-radius: 16px;
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}
.fad-ai-head {
    font-weight: 800;
    color: #0b6b2f;
    font-size: 0.92rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.fad-ai-head i:first-child {
    font-size: 1.05rem;
    color: #b3913a;
}
.fad-ai-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border: 1px solid #2f6f4f;
    background: #fff;
    color: #0b6b2f;
    font-size: 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.2s ease;
    flex-shrink: 0;
}
.fad-ai-btn:hover {
    background: #dcebce;
}
.fad-ai-btn:disabled {
    opacity: 0.6;
    cursor: default;
}
.fad-ai-body {
    flex: 1 1 auto;
    min-height: 88px;
    font-size: 0.78rem;
    line-height: 1.5;
    color: #3d4940;
    white-space: pre-line;
    overflow: auto;
    font-weight: 500;
}
.fad-ai-placeholder {
    color: #a3b3a0;
    font-style: italic;
}
.fad-ai-loading {
    color: #8a9a8a;
}
.fad-ai-error {
    color: #a83232;
    font-weight: 600;
}

.fad-charts-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.fad-chart-lg {
    grid-column: span 4;
}
.fad-chart-med {
    grid-column: span 2;
}
.fad-chart-sm {
    grid-column: span 1;
}
.fad-chart-card {
    background: #fff;
    border: 1px solid #cdd9b5;
    border-radius: 16px;
    padding: 18px;
    min-width: 0;
}
.fad-chart-title {
    font-weight: 600;
    color: #2e4836;
    margin-bottom: 12px;
    font-size: 0.92rem;
}
.fad-chart-title i {
    color: #2f6f4f;
    margin-right: 6px;
}
.fad-chart-wrap {
    position: relative;
    min-height: 240px;
    width: 100%;
}
.fad-chart-lg .fad-chart-wrap {
    min-height: 320px;
}
.fad-chart-med .fad-chart-wrap {
    min-height: 280px;
}
.fad-chart-sm .fad-chart-wrap {
    min-height: 240px;
}
.fad-chart-wrap canvas {
    display: block;
    max-width: 100%;
}
.fad-chart-empty {
    display: none;
    position: absolute;
    inset: 0;
    background: rgba(254, 248, 243, 0.88);
    color: #8a9a8a;
    font-weight: 600;
    font-size: 0.85rem;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}

.fad-table-card {
    background: #fff;
    border: 1px solid #cdd9b5;
    border-radius: 16px;
    padding: 18px;
    min-width: 0;
}
.fad-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}
.fad-table-sub {
    font-size: 0.78rem;
    color: #8a9a8a;
    font-weight: 500;
}
.fad-table-actions {
    display: flex;
    gap: 8px;
}
.fad-sort-btn {
    border: 1px solid #cdd9b5;
    background: #f9f4da;
    color: #2e4836;
    font-weight: 600;
    font-size: 0.78rem;
    padding: 7px 13px;
    border-radius: 9px;
    cursor: pointer;
    transition: 0.2s ease;
}
.fad-sort-btn:hover {
    background: #eceed9;
}
.fad-sort-btn.active {
    background: #2e4836;
    color: #fff;
    border-color: #2e4836;
}
.fad-table-wrap {
    overflow: auto;
    max-height: 620px;
    border: 1px solid #e7e3af;
    border-radius: 12px;
}
.fad-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
    background: #fff;
    min-width: 1080px;
}
.fad-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #faf9e5;
    color: #05590c;
    font-weight: 700;
    font-size: 0.74rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 11px 14px;
    text-align: left;
    border-bottom: 2px solid #e7e3af;
    white-space: nowrap;
}
.fad-table thead th.fad-th-num {
    text-align: right;
}
.fad-table td {
    padding: 10px 14px;
    border-bottom: 1px solid #f0ecdc;
    color: #3d4940;
    vertical-align: middle;
}
.fad-table td.fad-td-num {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.fad-table tbody tr:hover {
    background: #fbf9ec;
}
.fad-operator-cell {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}
.fad-ophoto {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #cdd9b5;
    background: #eceed9;
    flex-shrink: 0;
}
.fad-ophoto-fallback {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #e3ebbc;
    color: #2e4836;
    font-weight: 700;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.fad-oname {
    font-weight: 600;
    color: #2e4836;
    white-space: nowrap;
}
.fad-oid {
    font-size: 0.72rem;
    color: #a3b3a0;
}
.fad-status {
    display: inline-block;
    padding: 4px 11px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
    line-height: 1.3;
}
.fad-status.ok {
    background: #d4edda;
    color: #0b6b2f;
}
.fad-status.warn {
    background: #fff3cd;
    color: #8a6d1a;
}
.fad-status.danger {
    background: #fce8e8;
    color: #a83232;
}
.fad-status.neutral {
    background: #eceed9;
    color: #5f7b64;
}
.fad-table-loading {
    text-align: center;
    color: #8a9a8a;
    font-weight: 600;
}
.fad-table-error {
    text-align: center;
    color: #a83232;
    font-weight: 600;
}
.fad-table-empty {
    text-align: center;
    color: #8a9a8a;
    font-weight: 600;
    padding: 24px 12px !important;
}

.fad-content {
    position: relative;
    min-width: 0;
}
.fad-loading-banner {
    display: none;
    align-items: center;
    gap: 14px;
    background: #fef8f3;
    border: 1px solid #cdd9b5;
    border-left: 4px solid var(--fad-dark-green, #2e4836);
    border-radius: 14px;
    padding: 13px 18px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(46, 72, 54, 0.08);
}
.fad-content.is-loading .fad-loading-banner {
    display: flex;
}
.fad-spinner {
    flex: 0 0 26px;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 3px solid #cdd9b5;
    border-top-color: var(--fad-dark-green, #2e4836);
    animation: fadSpin 0.8s linear infinite;
}
.fad-loading-text {
    min-width: 0;
}
.fad-loading-title {
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--fad-dark-green, #2e4836);
    line-height: 1.3;
}
.fad-loading-sub {
    font-weight: 500;
    font-size: 0.76rem;
    color: #5f7b64;
    line-height: 1.35;
}
@keyframes fadSpin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes fadShimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
.fad-skel {
    background: var(--fad-skel-bg);
    background-size: 200% 100%;
    border-radius: 6px;
    animation: fadShimmer 1.4s ease-in-out infinite;
}
.fad-content.is-loading .fad-kpi-value,
.fad-content.is-loading .fad-kpi-sub {
    color: transparent;
    background: var(--fad-skel-bg);
    background-size: 200% 100%;
    border-radius: 6px;
    animation: fadShimmer 1.4s ease-in-out infinite;
}
.fad-content.is-loading .fad-kpi-icon {
    opacity: 0.55;
}
.fad-chart-skeleton {
    display: none;
    position: absolute;
    inset: 0;
    border-radius: 10px;
    background: var(--fad-skel-bg);
    background-size: 200% 100%;
    animation: fadShimmer 1.4s ease-in-out infinite;
}
.fad-content.is-loading .fad-chart-skeleton {
    display: block;
}
.fad-content.is-loading .fad-chart-wrap canvas {
    visibility: hidden;
}
.fad-content.is-loading .fad-chart-empty {
    display: none !important;
}
.fad-skeleton-row td {
    padding: 14px;
}
.fad-skel-num {
    height: 12px;
    width: 72%;
    margin-left: auto;
}
.fad-skel-operator {
    height: 34px;
    width: 170px;
}
.fad-skel-badge {
    height: 18px;
    width: 84px;
    border-radius: 50px;
}

@keyframes fadReveal {
    from {
        opacity: 0.35;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.fad-content.fad-loaded {
    animation: fadReveal 0.2s ease-out;
}

@media (prefers-reduced-motion: reduce) {
    .fad-spinner,
    .fad-skel,
    .fad-chart-skeleton,
    .fad-content.is-loading .fad-kpi-value,
    .fad-content.is-loading .fad-kpi-sub {
        animation: none;
    }
    .fad-content.fad-loaded {
        animation: none;
    }
}

@media (max-width: 1300px) {
    .fad-chart-sm {
        grid-column: span 2;
    }
    .fad-top-grid {
        grid-template-columns: minmax(0, 1fr) minmax(0, 330px);
    }
}
@media (max-width: 1200px) {
    .fad-top-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 900px) {
    .fad-chart-lg,
    .fad-chart-med,
    .fad-chart-sm {
        grid-column: span 4;
    }
    .fad-kpi-row {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .fad-header-right {
        flex-basis: 100%;
    }
}
@media (max-width: 520px) {
    .fad-kpi-row {
        grid-template-columns: 1fr;
    }
    .fad-header-right {
        justify-content: flex-start;
    }
}
</style>
<div class="main-content" id="mainContent" style="font-family:'Poppins', sans-serif; background:#eceed9; padding:20px;">

    
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;min-width:0;">

        <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>

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
            <div class="fad-header">
                <h1 class="fad-title">Finance Analytics</h1>
                <div class="fad-header-right">
                    <?php require __DIR__ . '/../layout/_period_filter.php'; ?>
                </div>
            </div>

            <div class="fad-content" id="fadContent">
                <div class="fad-loading-banner" id="fadLoadingBanner" role="status" aria-live="polite">
                    <div class="fad-spinner" aria-hidden="true"></div>
                    <div class="fad-loading-text">
                        <div class="fad-loading-title" id="fadLoadingTitle">Loading analytics&hellip;</div>
                        <div class="fad-loading-sub" id="fadLoadingSub">Please wait while we prepare the financial data.</div>
                    </div>
                </div>

                <div class="fad-nodata-banner" id="fadNoDataBanner">
                    <i class="bi bi-info-circle"></i> No trip, fuel, remittance, or expense data recorded for the selected period.
                </div>

                <div class="fad-error-banner" id="fadErrorBanner" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div class="fad-error-text">
                        <div id="fadErrorTitle">Unable to load analytics data</div>
                        <div class="fad-error-sub" id="fadErrorSub"></div>
                    </div>
                    <button type="button" class="fad-error-retry" id="fadErrorRetry">
                        <i class="bi bi-arrow-clockwise"></i> Retry
                    </button>
                </div>

                <div class="fad-top-grid">
                <?php require __DIR__ . '/sections/_kpis.php'; ?>
                <?php require __DIR__ . '/sections/_ai.php'; ?>
                </div>
                <?php require __DIR__ . '/sections/_charts.php'; ?>
                <?php require __DIR__ . '/sections/_operator_table.php'; ?>
            </div>

    </div>
</div>

<script>
(function () {
    "use strict";

    var API = "/finance/api/analytics/analytics_dashboard.php";
    var initialConfig = (function () {
        var node = document.querySelector("[data-fin-config]");
        if (!node) return {};
        try {
            return JSON.parse(node.textContent || "{}");
        } catch (e) {
            return {};
        }
    })();
    var state = {
        period: initialConfig.selected || "monthly",
        from: initialConfig.from || "",
        to: initialConfig.to || "",
    };
    var sortMode = "gross";
    var sortDir = -1;
    var driversCache = [];
    var charts = {};
    var AI_API = "/finance/api/analytics/ai_insight.php";
    var aiReqId = 0;
    var lastPayload = null;
    var loadReqId = 0;
    var lastLoadFailed = false;
    var periodLabels = {};
    (initialConfig.options || []).forEach(function (option) {
        periodLabels[option.value] = option.label;
    });

    var els = {
        content: document.getElementById("fadContent"),
        loadingTitle: document.getElementById("fadLoadingTitle"),
        loadingSub: document.getElementById("fadLoadingSub"),
        noData: document.getElementById("fadNoDataBanner"),
        errorBanner: document.getElementById("fadErrorBanner"),
        errorTitle: document.getElementById("fadErrorTitle"),
        errorSub: document.getElementById("fadErrorSub"),
        errorRetry: document.getElementById("fadErrorRetry"),
        tbody: document.getElementById("fadOperatorTbody"),
        sortGross: document.getElementById("fadSortGross"),
        sortNet: document.getElementById("fadSortNet"),
    };

    function fmtMoney(v) {
        var n = Number(v) || 0;
        return (
            "\u20B1" +
            n.toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        );
    }
    function fmtMoneyShort(v) {
        var n = Number(v) || 0;
        return "\u20B1" + n.toLocaleString("en-PH", { maximumFractionDigits: 0 });
    }
    function fmtNum(v) {
        return Number(v || 0).toLocaleString("en-PH");
    }
    function dash() {
        return '<span style="color:#a3b3a0;">&ndash;</span>';
    }

    function buildUrl() {
        var p = new URLSearchParams({ period: state.period });
        if (state.period === "custom" && state.from && state.to) {
            p.set("from", state.from);
            p.set("to", state.to);
        }
        return API + "?" + p.toString();
    }

    function periodName(p) {
        return periodLabels[p] || (p ? p.charAt(0).toUpperCase() + p.slice(1) : "");
    }

    function applyPeriodSummary(summary) {
        if (
            window.FinanceDateFilter &&
            typeof window.FinanceDateFilter.setSummary === "function"
        ) {
            window.FinanceDateFilter.setSummary(summary);
        }
    }

    function destroyChart(id) {
        if (charts[id]) {
            try {
                charts[id].destroy();
            } catch (e) {}
            charts[id] = null;
        }
    }

    function toggleEmpty(id, hasData) {
        var el = document.getElementById(id);
        if (el) el.style.display = hasData ? "none" : "flex";
    }

    function seriesDatasets(series, formatter) {
        var days = Object.keys(series || {}).sort();
        return {
            labels: days,
            values: days.map(function (d) {
                return formatter ? formatter(series[d]) : series[d];
            }),
        };
    }

    var chartPoppins = "'Poppins', sans-serif";
    var chartColors = { tick: "#8a9a8a", grid: "rgba(46,72,54,0.07)", border: "#cdd9b5" };

    function hexToRgba(hex, alpha) {
        var h = String(hex || "").replace("#", "");
        if (h.length === 3)
            h = h.charAt(0) + h.charAt(0) + h.charAt(1) + h.charAt(1) + h.charAt(2) + h.charAt(2);
        if (!/^[0-9a-fA-F]{6}$/.test(h)) return "rgba(46,72,54," + alpha + ")";
        var n = parseInt(h, 16);
        return (
            "rgba(" +
            ((n >> 16) & 255) +
            "," +
            ((n >> 8) & 255) +
            "," +
            (n & 255) +
            "," +
            alpha +
            ")"
        );
    }

    function makeLine(id, labels, values, label, color, fill) {
        destroyChart(id);
        var ctx = document.getElementById(id);
        if (!ctx) return;
        charts[id] = new Chart(ctx, {
            type: "line",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: label,
                        data: values,
                        borderColor: color,
                        backgroundColor: hexToRgba(color, 0.16),
                        fill: !!fill,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointRadius: 1.5,
                        pointHoverRadius: 5,
                        pointHitRadius: 10,
                        pointBackgroundColor: color,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 8, right: 8, bottom: 4, left: 4 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "rgba(46,72,54,0.92)",
                        padding: 10,
                        cornerRadius: 8,
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: chartColors.tick,
                            font: { family: chartPoppins, size: 11 },
                            maxTicksLimit: 10,
                            maxRotation: 0,
                        },
                        border: { color: chartColors.border },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: chartColors.grid },
                        ticks: {
                            color: chartColors.tick,
                            font: { family: chartPoppins, size: 11 },
                            maxTicksLimit: 6,
                        },
                        border: { display: false },
                    },
                },
            },
        });
    }

    function makeBar(id, labels, values, label, color, horizontal) {
        destroyChart(id);
        var ctx = document.getElementById(id);
        if (!ctx) return;
        charts[id] = new Chart(ctx, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: label,
                        data: values,
                        backgroundColor: color,
                        borderColor: color,
                        borderWidth: 1,
                        borderRadius: 6,
                        maxBarThickness: 26,
                    },
                ],
            },
            options: {
                indexAxis: horizontal ? "y" : "x",
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 8, right: 8, bottom: 4, left: 4 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "rgba(46,72,54,0.92)",
                        padding: 10,
                        cornerRadius: 8,
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: chartColors.tick,
                            font: { family: chartPoppins, size: 11 },
                            maxRotation: 45,
                        },
                        border: { color: chartColors.border },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: chartColors.grid },
                        ticks: {
                            color: chartColors.tick,
                            font: { family: chartPoppins, size: 11 },
                            maxTicksLimit: 6,
                        },
                        border: { display: false },
                    },
                },
            },
        });
    }

    function makeDoughnut(id, labels, values, colors) {
        destroyChart(id);
        var ctx = document.getElementById(id);
        if (!ctx) return;
        charts[id] = new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: labels,
                datasets: [
                    {
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: "#fef8f3",
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 8, right: 8, bottom: 4, left: 8 } },
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            color: "#5f7b64",
                            font: { family: chartPoppins, size: 11 },
                            boxWidth: 12,
                            padding: 14,
                        },
                    },
                    tooltip: {
                        backgroundColor: "rgba(46,72,54,0.92)",
                        padding: 10,
                        cornerRadius: 8,
                    },
                },
            },
        });
    }

    function renderKpis(d) {
        var fin = d.financial || {};
        var rem = d.remittance || {};
        var drivers = d.drivers || [];
        var disc = d.discrepancies || { cases: 0, amount: 0 };
        var discCases = Number(disc.cases) || 0;
        var discAmount = Number(disc.amount) || 0;

        document.getElementById("fadKpiGross").textContent = fmtMoney(fin.gross_income);
        document.getElementById("fadKpiGrossSub").textContent =
            "Gross collections \u00B7 " + fmtNum(drivers.length) + " operators";
        document.getElementById("fadKpiNet").textContent = fmtMoney(fin.net_income);
        document.getElementById("fadKpiNetSub").textContent =
            "Gross \u2212 fuel \u2212 other expenses";
        document.getElementById("fadKpiFuel").textContent = fmtMoney(fin.fuel);
        document.getElementById("fadKpiFuelSub").textContent = "Fuel logs within period";
        document.getElementById("fadKpiExpenses").textContent = fmtMoney(fin.other_expenses);
        document.getElementById("fadKpiExpensesSub").textContent =
            "Fixed operating costs (prorated)";
        document.getElementById("fadKpiRemitRate").textContent =
            (Number(rem.remittance_rate) || 0) + "%";
        document.getElementById("fadKpiRemitSub").textContent =
            fmtMoney(rem.remitted_amount) + " of " + fmtMoney(rem.paid_expected) + " paid expected";
        document.getElementById("fadKpiDisc").textContent = String(discCases);
        document.getElementById("fadKpiDiscSub").textContent =
            discAmount > 0 ? "Variance " + fmtMoney(discAmount) : "No discrepancies in period";
    }

    function trendFact(vals) {
        var nums = (vals || [])
            .map(function (n) {
                return Number(n);
            })
            .filter(function (n) {
                return isFinite(n);
            });
        if (nums.length < 2) return { last: 0, prev: 0 };
        var last = nums[nums.length - 1];
        var prev = nums[nums.length - 2];
        return { last: Math.round(last * 100) / 100, prev: Math.round(prev * 100) / 100 };
    }

    function buildAiSummary(d) {
        var chartsD = d.charts || {};
        var fin = d.financial || {};
        var rem = d.remittance || {};
        var disc = d.discrepancies || { cases: 0, amount: 0 };

        var fareSeries = chartsD.fare_over_time || {};
        var fareKeys = Object.keys(fareSeries).sort();
        var fareVals = fareKeys.map(function (k) {
            return Number(fareSeries[k]) || 0;
        });

        var fuelSeries = chartsD.fuel_over_time || {};
        var fuelKeys = Object.keys(fuelSeries).sort();
        var fuelVals = fuelKeys.map(function (k) {
            return Number((fuelSeries[k] || {}).cost) || 0;
        });

        var allDays = Array.from(new Set(fareKeys.concat(fuelKeys))).sort();
        var periodStart = new Date(String((d.range || {}).start || "").slice(0, 10));
        var periodEnd = new Date(String((d.range || {}).end || "").slice(0, 10));
        var numDays = Math.max(1, Math.round((periodEnd - periodStart) / 86400000) + 1);
        var fixedPerDay = numDays > 0 ? (Number(fin.other_expenses) || 0) / numDays : 0;
        var netVals = allDays.map(function (day) {
            var fare = Number(fareSeries[day]) || 0;
            var fuel = Number((fuelSeries[day] || {}).cost) || 0;
            return Math.round((fare - fuel - fixedPerDay) * 100) / 100;
        });

        var drivers = d.drivers || [];
        var remitStatus = { remitted: 0, discrepancy: 0, no_remit: 0 };
        drivers.forEach(function (r) {
            var st = String(r.remittance_status || "");
            if (st === "Remitted" || st === "History" || st === "No Activity")
                remitStatus.remitted++;
            else if (st === "Discrepancy") remitStatus.discrepancy++;
            else if (st === "No Remit") remitStatus.no_remit++;
        });
        var top =
            drivers.slice().sort(function (a, b) {
                return (Number(b.gross_income) || 0) - (Number(a.gross_income) || 0);
            })[0] || {};

        return {
            period: d.period || "",
            range: d.range || {},
            financial: {
                gross_income: fin.gross_income,
                net_income: fin.net_income,
                fuel: fin.fuel,
                other_expenses: fin.other_expenses,
            },
            remittance: {
                remittance_rate: rem.remittance_rate,
                remitted_amount: rem.remitted_amount,
                paid_expected: rem.paid_expected,
            },
            disc: { cases: disc.cases, amount: disc.amount },
            rev: trendFact(fareVals),
            fuel: trendFact(fuelVals),
            net: trendFact(netVals),
            operators: {
                total: drivers.length,
                remitted: remitStatus.remitted,
                discrepancy: remitStatus.discrepancy,
                no_remit: remitStatus.no_remit,
            },
            top_operator: { name: top.name || "", gross: top.gross_income },
        };
    }

    function setAiText(cls, text) {
        var body = document.getElementById("fadAiInsight");
        if (body) {
            body.className = "fad-ai-body" + (cls ? " " + cls : "");
            body.textContent = text;
        }
    }

    function renderTableSkeleton() {
        if (!els.tbody) return;
        var cells =
            '<td><div class="fad-skel fad-skel-operator"></div></td>' +
            '<td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>' +
            '<td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>' +
            '<td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>' +
            '<td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>' +
            '<td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>' +
            '<td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>' +
            '<td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>' +
            '<td><div class="fad-skel fad-skel-badge"></div></td>' +
            '<td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>';
        var html = "";
        for (var i = 0; i < 6; i++) html += '<tr class="fad-skeleton-row">' + cells + "</tr>";
        els.tbody.innerHTML = html;
    }

    function setControlsDisabled(on) {
        document
            .querySelectorAll(".fin-period-btn, .fin-date-input, .fin-custom-apply")
            .forEach(function (b) {
                b.disabled = on;
            });
    }

    function setLoading(on, period) {
        if (!els.content) return;
        if (on) {
            els.content.classList.add("is-loading");
            els.content.classList.remove("fad-loaded");
            els.content.setAttribute("aria-busy", "true");
            if (els.loadingTitle)
                els.loadingTitle.textContent =
                    "Loading " + String(periodName(period)).toLowerCase() + " analytics\u2026";
                if (els.loadingSub)
                    els.loadingSub.textContent = "Please wait while we prepare the financial data.";
                if (els.noData) els.noData.style.display = "none";
            hideLoadError();
            renderTableSkeleton();
            setAiText("fad-ai-placeholder", "Analyzing this period...");
        } else {
            els.content.classList.remove("is-loading");
            els.content.removeAttribute("aria-busy");
        }
        setControlsDisabled(on);
    }

    function revealContent() {
        if (!els.content) return;
        els.content.classList.remove("fad-loaded");
        void els.content.offsetWidth;
        els.content.classList.add("fad-loaded");
    }

    function renderKpisEmpty() {
        [
            "fadKpiGross",
            "fadKpiNet",
            "fadKpiFuel",
            "fadKpiExpenses",
            "fadKpiRemitRate",
            "fadKpiDisc",
        ].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = "\u2013";
        });
        [
            "fadKpiGrossSub",
            "fadKpiNetSub",
            "fadKpiFuelSub",
            "fadKpiExpensesSub",
            "fadKpiRemitSub",
            "fadKpiDiscSub",
        ].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = "\u2013";
        });
    }

    function renderChartsEmpty() {
        [
            "fadRevenueChart",
            "fadFuelChart",
            "fadNetIncomeChart",
            "fadExpenseChart",
            "fadRemittanceChart",
            "fadPerBusTripsChart",
            "fadPerBusFareChart",
            "fadPassengerTypesChart",
        ].forEach(destroyChart);
        [
            "fadEmptyRevenue",
            "fadEmptyFuel",
            "fadEmptyNet",
            "fadEmptyExpense",
            "fadEmptyRemittance",
            "fadEmptyBusTrips",
            "fadEmptyBusFare",
            "fadEmptyPassengerTypes",
        ].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.style.display = "none";
        });
    }

    function hideLoadError() {
        if (els.errorBanner) els.errorBanner.style.display = "none";
    }

    function showLoadError(message) {
        lastLoadFailed = true;
        driversCache = [];
        if (els.errorBanner) {
            if (els.errorTitle) els.errorTitle.textContent = "Unable to load analytics data";
            if (els.errorSub)
                els.errorSub.textContent = message || "Please check your connection and try again.";
            els.errorBanner.style.display = "flex";
        }
        if (els.noData) els.noData.style.display = "none";
        renderKpisEmpty();
        renderChartsEmpty();
        if (els.tbody)
            els.tbody.innerHTML =
                '<tr><td colspan="10" class="fad-table-error">Unable to load analytics data. ' +
                $esc(message || "") +
                "</td></tr>";
        setAiText("fad-ai-error", "AI analysis unavailable until analytics data loads.");
        lastPayload = null;
    }

    async function requestAI() {
        var payload = lastPayload;
        var btn = document.getElementById("fadAiBtn");
        if (!payload) return;
        if (els.content && els.content.classList.contains("is-loading")) return;
        var summary = buildAiSummary(payload);
        var hasData =
            Number((summary.financial || {}).gross_income) > 0 ||
            Number(summary.operators.total) > 0 ||
            Number((summary.financial || {}).fuel) > 0;
        if (!hasData) {
            setAiText("", "Not enough data for this period to generate an analysis.");
            return;
        }
        var id = ++aiReqId;
        if (btn) btn.disabled = true;
        setAiText("fad-ai-loading", "Analyzing figures for this period...");
        try {
            var res = await fetch(AI_API, {
                method: "POST",
                headers: { "Content-Type": "application/json", Accept: "application/json" },
                body: JSON.stringify(summary),
            });
            var data = await res.json();
            if (id !== aiReqId) return;
            if (data && data.success) {
                setAiText("", data.analysis || "AI returned no analysis.");
            } else {
                setAiText(
                    "fad-ai-error",
                    "AI analysis unavailable: " +
                        ((data && (data.message || data.error)) || "unknown error"),
                );
            }
        } catch (e) {
            if (id !== aiReqId) return;
            setAiText("fad-ai-error", "Unable to reach the AI service. Please try again.");
        } finally {
            if (id === aiReqId && btn) btn.disabled = false;
        }
    }

    function renderCharts(d) {
        var chartsD = d.charts || {};
        var fin = d.financial || {};

        var rev = seriesDatasets(chartsD.fare_over_time, function (v) {
            return Number(v) || 0;
        });
        makeLine("fadRevenueChart", rev.labels, rev.values, "Revenue", "#2f6f4f", true);
        toggleEmpty("fadEmptyRevenue", rev.values.length > 0);

        var fuelSeries = chartsD.fuel_over_time || {};
        var fuelDays = Object.keys(fuelSeries).sort();
        var fuelVals = fuelDays.map(function (d2) {
            return Number((fuelSeries[d2] || {}).cost) || 0;
        });
        makeLine("fadFuelChart", fuelDays, fuelVals, "Fuel Cost", "#b3913a", true);
        toggleEmpty("fadEmptyFuel", fuelVals.length > 0);

        var exp = fin.expenses_breakdown || {};
        var expLabels = ["Fuel", "Amortization", "Insurance", "GPS", "OPTR"];
        var expVals = [exp.fuel, exp.amortization, exp.insurance, exp.gps, exp.optr].map(
            function (v) {
                return Number(v) || 0;
            },
        );
        var expHas = expVals.some(function (v) {
            return v > 0;
        });
        makeDoughnut("fadExpenseChart", expLabels, expVals, [
            "#b3913a",
            "#2f6f4f",
            "#4e8d68",
            "#8a9a8a",
            "#e0b34a",
        ]);
        toggleEmpty("fadEmptyExpense", expHas);

        var netDays = rev.labels.filter(function (d3) {
            return fuelDays.indexOf(d3) !== -1;
        });
        var allDays = Array.from(new Set(rev.labels.concat(fuelDays))).sort();
        var periodStart = new Date(String((d.range || {}).start || "").slice(0, 10));
        var periodEnd = new Date(String((d.range || {}).end || "").slice(0, 10));
        var numDays = Math.max(1, Math.round((periodEnd - periodStart) / 86400000) + 1);
        var fixedPerDay = numDays > 0 ? (Number(fin.other_expenses) || 0) / numDays : 0;
        var netVals = allDays.map(function (d4) {
            var fare = Number(
                rev.labels.indexOf(d4) !== -1 ? rev.values[rev.labels.indexOf(d4)] || 0 : 0,
            );
            var fuel = Number(fuelSeries[d4] ? fuelSeries[d4].cost || 0 : 0);
            return Math.round((fare - fuel - fixedPerDay) * 100) / 100;
        });
        makeLine("fadNetIncomeChart", allDays, netVals, "Net Income", "#0b6b2f", true);
        toggleEmpty(
            "fadEmptyNet",
            netVals.some(function (v) {
                return v !== 0;
            }),
        );

        var remCounts = {};
        (d.drivers || []).forEach(function (r) {
            var st = r.remittance_status || "Unknown";
            remCounts[st] = (remCounts[st] || 0) + 1;
        });
        var remLabels = Object.keys(remCounts);
        var remVals = remLabels.map(function (k) {
            return remCounts[k];
        });
        var remColors = ["#2f6f4f", "#b3913a", "#a83232", "#8a9a8a", "#4e8d68", "#d6c56a"];
        makeDoughnut(
            "fadRemittanceChart",
            remLabels.length ? remLabels : ["No data"],
            remVals.length ? remVals : [1],
            remColors,
        );
        toggleEmpty("fadEmptyRemittance", remVals.length > 0);

        var perBus = chartsD.per_bus || [];
        var busLabels = perBus.map(function (b) {
            return b.bus;
        });
        var busTripVals = perBus.map(function (b) {
            return Number(b.trips) || 0;
        });
        var busFareVals = perBus.map(function (b) {
            return Number(b.gross_income) || 0;
        });
        makeBar("fadPerBusTripsChart", busLabels, busTripVals, "Trips", "#4e8d68");
        toggleEmpty("fadEmptyBusTrips", busTripVals.length > 0);
        makeBar("fadPerBusFareChart", busLabels, busFareVals, "Gross", "#2f6f4f");
        toggleEmpty("fadEmptyBusFare", busFareVals.length > 0);

        var ptSeries = chartsD.passenger_types || {};
        var ptLabels = Object.keys(ptSeries);
        var ptVals = ptLabels.map(function (k) {
            return Number(ptSeries[k]) || 0;
        });
        var ptPalette = ["#2f6f4f", "#b3913a", "#4e8d68", "#8a9a8a", "#d6c56a", "#e0b34a"];
        var ptColors = ptLabels.map(function (k, i) {
            return ptPalette[i % ptPalette.length];
        });
        makeBar("fadPassengerTypesChart", ptLabels, ptVals, "Passengers", ptColors, true);
        toggleEmpty("fadEmptyPassengerTypes", ptVals.length > 0);
    }

    function statusClass(st) {
        if (["Remitted", "History", "No Activity"].indexOf(st) !== -1) return "ok";
        if (st === "Discrepancy") return "danger";
        if (st === "No Remit") return "warn";
        return "neutral";
    }

    function operatorCell(r) {
        var photo = r.photo
            ? '<img class="fad-ophoto" src="' +
              (r.photo.indexOf("/") === 0 ? r.photo : "/" + r.photo) +
              "?t=" +
              Date.now() +
              '" alt="">'
            : '<div class="fad-ophoto-fallback">' +
              (r.name || "?").charAt(0).toUpperCase() +
              "</div>";
        return (
            '<td><div class="fad-operator-cell">' +
            photo +
            '<div><div class="fad-oname">' +
            $esc(r.name) +
            '</div><div class="fad-oid">' +
            $esc(r.driver_id) +
            "</div></div></div></td>"
        );
    }

    function renderTable() {
        if (els.content && els.content.classList.contains("is-loading")) return;
        if (lastLoadFailed) {
            els.tbody.innerHTML =
                '<tr><td colspan="10" class="fad-table-error">Unable to load analytics data. Use Retry to try again.</td></tr>';
            return;
        }
        var list = driversCache.slice();
        if (sortMode === "gross") {
            list.sort(function (a, b) {
                return ((Number(b.gross_income) || 0) - (Number(a.gross_income) || 0)) * sortDir;
            });
        } else {
            list.sort(function (a, b) {
                return ((Number(b.net_income) || 0) - (Number(a.net_income) || 0)) * sortDir;
            });
        }
        els.sortGross.classList.toggle("active", sortMode === "gross");
        els.sortNet.classList.toggle("active", sortMode === "net");
        els.sortGross.querySelector("i").className =
            "bi " + (sortMode === "gross" && sortDir === -1 ? "bi-sort-down" : "bi-sort-up");
        els.sortNet.querySelector("i").className =
            "bi " + (sortMode === "net" && sortDir === -1 ? "bi-sort-down" : "bi-sort-up");

        if (!list.length) {
            els.tbody.innerHTML =
                '<tr><td colspan="10" class="fad-table-empty">No operator activity recorded for the selected period.</td></tr>';
            return;
        }
        var html = list
            .map(function (r) {
                var fare = r.fare || {};
                var st = r.remittance_status || "Unknown";
                var exp = r.fixed_costs || {};
                var expTotal = Number(exp.total) || 0;
                var expBreak = [];
                if (Number(exp.amortization) || 0)
                    expBreak.push("Amort " + fmtMoneyShort(exp.amortization));
                if (Number(exp.insurance) || 0)
                    expBreak.push("Ins " + fmtMoneyShort(exp.insurance));
                if (Number(exp.gps) || 0) expBreak.push("GPS " + fmtMoneyShort(exp.gps));
                if (Number(exp.optr) || 0) expBreak.push("OPTR " + fmtMoneyShort(exp.optr));
                var expected = Number(r.remitted_expected) || 0;
                var actualV = Number(r.remitted_actual) || 0;
                return (
                    "<tr>" +
                    operatorCell(r) +
                    '<td class="fad-td-num"><strong>' +
                    fmtMoney(r.gross_income) +
                    "</strong></td>" +
                    '<td class="fad-td-num">' +
                    fmtMoney(r.gas ? r.gas.amount : 0) +
                    "</td>" +
                    '<td class="fad-td-num">' +
                    fmtMoney(expTotal) +
                    "</td>" +
                    '<td class="fad-td-num"><strong>' +
                    fmtMoney(r.net_income) +
                    "</strong></td>" +
                    '<td class="fad-td-num">' +
                    fmtNum(r.trips ? r.trips.total : 0) +
                    "</td>" +
                    '<td class="fad-td-num">' +
                    fmtNum(r.passengers ? r.passengers.total : 0) +
                    "</td>" +
                    '<td class="fad-td-num">' +
                    fmtMoneyShort(fare.passenger) +
                    " / " +
                    fmtMoneyShort(fare.other) +
                    "</td>" +
                    '<td><span class="fad-status ' +
                    statusClass(st) +
                    '">' +
                    $esc(st) +
                    "</span>" +
                    (Number(r.remitted_variance)
                        ? '<div style="font-size:0.72rem;color:#8a9a8a;margin-top:2px;">var ' +
                          fmtMoney(r.remitted_variance) +
                          "</div>"
                        : expBreak.length
                          ? '<div style="font-size:0.7rem;color:#a3b3a0;margin-top:2px;" title="' +
                            $esc(expBreak.join(", ")) +
                            '">' +
                            $esc(expBreak.join(" \u00B7 ")) +
                            "</div>"
                          : "") +
                    "</td>" +
                    '<td class="fad-td-num">' +
                    (expected || actualV
                        ? fmtMoney(expected) + " / " + fmtMoney(actualV)
                        : dash()) +
                    "</td>" +
                    "</tr>"
                );
            })
            .join("");
        els.tbody.innerHTML = html;
    }

    function $esc(s) {
        var div = document.createElement("div");
        div.appendChild(document.createTextNode(String(s == null ? "" : s)));
        return div.innerHTML;
    }

    function applyEmptyBanner(hasData) {
        if (els.noData) els.noData.style.display = hasData ? "none" : "flex";
    }

    async function load() {
        var reqId = ++loadReqId;
        setLoading(true, state.period);
        try {
            var res = await fetch(buildUrl(), {
                headers: { Accept: "application/json" },
                cache: "no-store",
            });
            if (reqId !== loadReqId) return;
            if (!res.ok) {
                showLoadError("The server responded with status " + res.status + ".");
                return;
            }
            var payload;
            try {
                payload = await res.json();
            } catch (parseError) {
                showLoadError("The server returned an invalid response.");
                return;
            }
            if (reqId !== loadReqId) return;
            if (!payload || payload.success !== true) {
                showLoadError(
                    payload && payload.message ? payload.message : "Failed to load analytics.",
                );
                return;
            }
            hideLoadError();
            lastLoadFailed = false;
            setLoading(false);
            if (payload.summary) applyPeriodSummary(payload.summary);
            var fin = payload.financial || {};
            var hasData =
                Number(fin.gross_income) > 0 ||
                (payload.drivers || []).length > 0 ||
                Number(fin.fuel) > 0;
            applyEmptyBanner(
                hasData ||
                    (payload.drivers || []).some(function (r) {
                        return (r.trips || {}).total > 0;
                    }),
            );
            driversCache = (payload.drivers || []).slice();
            lastPayload = payload;
            renderKpis(payload);
            renderCharts(payload);
            renderTable();
            revealContent();
            requestAI();
        } catch (e) {
            if (reqId !== loadReqId) return;
            showLoadError("Failed to load analytics data.");
        } finally {
            if (reqId === loadReqId) {
                setLoading(false);
            }
        }
    }

    document.addEventListener("financedatefilter:change", function (event) {
        var detail = event.detail || {};
        state.period = detail.period || state.period;
        state.from = detail.from || "";
        state.to = detail.to || "";
        load();
    });

    if (els.errorRetry) {
        els.errorRetry.addEventListener("click", function () {
            if (els.content && els.content.classList.contains("is-loading")) return;
            load();
        });
    }

    var aiBtn = document.getElementById("fadAiBtn");
    if (aiBtn) aiBtn.addEventListener("click", requestAI);

    els.sortGross.addEventListener("click", function () {
        if (sortMode === "gross") sortDir = sortDir === -1 ? 1 : -1;
        else {
            sortMode = "gross";
            sortDir = -1;
        }
        renderTable();
    });
    els.sortNet.addEventListener("click", function () {
        if (sortMode === "net") sortDir = sortDir === -1 ? 1 : -1;
        else {
            sortMode = "net";
            sortDir = -1;
        }
        renderTable();
    });

    load();
})();
</script>
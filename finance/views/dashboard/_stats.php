<style>
.fin-stats-wrap {
    margin-bottom: 22px;
}
.fin-stats-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.fin-stats-title {
    font-size: 13px;
    font-weight: 800;
    color: #295738;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}

.fin-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}
@media (max-width: 1100px) {
    .fin-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575px) {
    .fin-stat-grid {
        grid-template-columns: 1fr;
    }
}

.fin-card {
    background: #fff;
    border: 1.5px solid #e3eabb;
    border-radius: 16px;
    padding: 16px 18px 14px;
    display: flex;
    flex-direction: column;
    transition: 0.2s;
    cursor: pointer;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    position: relative;
    min-width: 0;
}
.fin-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.fin-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.fin-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
    background: #eef4e6;
    color: #2e7d32;
}
.fin-label {
    font-size: 10px;
    font-weight: 700;
    color: #8a9389;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin: 4px 0 3px;
    display: flex;
    align-items: center;
    gap: 5px;
    line-height: 1.3;
}
.fin-value {
    font-size: clamp(21px, 1.7vw, 27px);
    font-weight: 800;
    color: #2e4836;
    line-height: 1.1;
    letter-spacing: -0.01em;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.fin-sub {
    font-size: 10.5px;
    color: #8a9389;
    margin-top: 4px;
    line-height: 1.3;
    min-height: 14px;
}
.fin-spark {
    height: 36px;
    margin-top: 10px;
    padding-top: 0;
    position: relative;
}
.fin-spark canvas {
    width: 100% !important;
    height: 36px !important;
}
.fin-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: auto;
    padding-top: 9px;
    border-top: 1px solid #eef0e6;
}
.fin-period {
    display: inline-flex;
    align-items: center;
    height: 20px;
    font-size: 10px;
    font-weight: 700;
    color: #6b8e6b;
    background: #f4f7ee;
    border-radius: 6px;
    padding: 0 7px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 60%;
}
.fin-trend {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10.5px;
    font-weight: 600;
    color: #8a9389;
    line-height: 1.2;
    min-width: 0;
}
.fin-trend .fin-trend-badge {
    display: inline-flex;
    align-items: center;
    height: 18px;
    padding: 0 6px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 800;
}
.fin-trend .fin-trend-badge.up {
    background: #e7f4ea;
    color: #16a34a;
}
.fin-trend .fin-trend-badge.down {
    background: #fdecea;
    color: #dc2626;
}
.fin-trend .fin-trend-label {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.fin-info-dot {
    cursor: help;
    color: #b6c08a;
    font-size: 11px;
}

.fin-skeleton {
    display: inline-block;
    width: 70%;
    height: 1.05em;
    border-radius: 6px;
    background: linear-gradient(90deg, #eef1e9 25%, #e1e8d7 37%, #eef1e9 63%);
    background-size: 400% 100%;
    animation: finShimmer 1.3s ease-in-out infinite;
    vertical-align: middle;
}
@keyframes finShimmer {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: 0 0;
    }
}
.fin-value.is-loading {
    color: transparent;
}
.fin-value.is-empty {
    color: #b0b9a8;
}
.fin-sub.loading-msg {
    color: #9aa39a;
    font-style: italic;
}
.fin-sub.error-msg {
    color: #c0392b;
    font-weight: 600;
}

body.fin-no-scroll {
    overflow: hidden !important;
}

.fin-detail-modal {
    position: fixed;
    inset: 0;
    background: rgba(20, 30, 25, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1090;
    padding: 24px;
}
.fin-detail-modal.open {
    display: flex;
}
.fin-detail-box {
    background: #fff;
    border-radius: 20px;
    width: 88vw;
    max-width: 1300px;
    height: 88vh;
    max-height: 88vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.3);
    border: 1.5px solid #cfe0f4;
}
.fin-detail-header {
    flex: 0 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 16px 22px;
    border-bottom: 1px solid #eef0e6;
    background: #f7faf4;
}
.fin-detail-eyebrow {
    font-size: 11px;
    font-weight: 700;
    color: #6b8e6b;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}
.fin-detail-title {
    font-size: 20px;
    font-weight: 800;
    color: #1c3a52;
    margin-top: 2px;
}
.fin-detail-close {
    border: none;
    background: rgba(41, 87, 56, 0.1);
    width: 34px;
    height: 34px;
    border-radius: 10px;
    font-size: 22px;
    cursor: pointer;
    color: #2e4836;
    flex-shrink: 0;
    line-height: 1;
}
.fin-detail-close:hover {
    background: rgba(41, 87, 56, 0.2);
}

.fin-detail-scroll {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.fin-section h4 {
    font-size: 12.5px;
    font-weight: 800;
    color: #295738;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 0 10px;
    display: flex;
    align-items: center;
    gap: 7px;
}
.fin-section h4 .fin-sec-ico {
    color: #7aa37a;
}

.fin-sum-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr 1fr;
    gap: 12px;
    align-items: stretch;
}
@media (max-width: 720px) {
    .fin-sum-grid {
        grid-template-columns: 1fr 1fr;
    }
}
.fin-sum-main {
    background: linear-gradient(135deg, #f4faf5, #eaf4ec);
    border: 1px solid #cde8d2;
    border-radius: 14px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.fin-sum-value {
    font-size: 30px;
    font-weight: 800;
    color: #2e4836;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}
.fin-sum-label {
    font-size: 11px;
    color: #6b8e6b;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 6px;
}
.fin-sum-cell {
    background: #f7faf4;
    border: 1px solid #e3eabb;
    border-radius: 14px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.fin-sum-cell-v {
    font-size: 18px;
    font-weight: 800;
    color: #2e4836;
    font-variant-numeric: tabular-nums;
}
.fin-sum-cell-l {
    font-size: 10.5px;
    color: #8a9389;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-top: 4px;
}
.fin-sum-cell-v.pos {
    color: #16a34a;
}
.fin-sum-cell-v.neg {
    color: #dc2626;
}

.fin-stat-grid2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}
.fin-stat-cell {
    background: #f7faf4;
    border: 1px solid #e3eabb;
    border-radius: 12px;
    padding: 12px 14px;
}
.fin-stat-cell .v {
    font-size: 17px;
    font-weight: 800;
    color: #2e4836;
    font-variant-numeric: tabular-nums;
}
.fin-stat-cell .l {
    font-size: 10.5px;
    color: #8a9389;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-top: 3px;
}

.fin-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.fin-data-table th,
.fin-data-table td {
    padding: 9px 12px;
    text-align: right;
    border-bottom: 1px solid #eef0e6;
    white-space: nowrap;
}
.fin-data-table th:first-child,
.fin-data-table td:first-child {
    text-align: left;
}
.fin-data-table thead th {
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #8a9389;
    font-weight: 800;
    background: #f7faf4;
}
.fin-data-table tbody tr:hover {
    background: #f4faf5;
}
.fin-data-table td.neg {
    color: #dc2626;
}
.fin-data-table .fin-total-row td {
    font-weight: 800;
    color: #1c3a52;
    border-top: 2px solid #cfe0f4;
    border-bottom: none;
    background: #f2f7fd;
}

.fin-chart-wrap {
    background: #f7faf4;
    border: 1px solid #e3eabb;
    border-radius: 14px;
    padding: 14px;
    height: 340px;
}
.fin-chart-wrap canvas {
    width: 100% !important;
    height: 100% !important;
}

.fin-insights {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.fin-insights li {
    background: #f7faf4;
    border: 1px solid #e3eabb;
    border-left: 3px solid #7aa37a;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    line-height: 1.5;
    color: #2e4836;
}
.fin-insights li b {
    color: #1c3a52;
}

.fin-ai-body {
    font-size: 13.5px;
    line-height: 1.7;
    color: #2e4836;
    background: #f2f7fd;
    border: 1px solid #cfe0f4;
    border-radius: 12px;
    padding: 14px 16px;
    min-height: 60px;
}
.fin-ai-body h1,
.fin-ai-body h2,
.fin-ai-body h3,
.fin-ai-body h4 {
    font-size: 15px;
    margin: 10px 0 4px;
    color: #1c3a52;
}
.fin-ai-body h1 {
    font-size: 17px;
}
.fin-ai-body h2 {
    font-size: 16px;
}
.fin-ai-body p {
    margin: 6px 0;
}
.fin-ai-body ul,
.fin-ai-body ol {
    margin: 6px 0;
    padding-left: 22px;
}
.fin-ai-body li {
    margin: 4px 0;
}
.fin-ai-body strong {
    font-weight: 700;
    color: #1c3a52;
}
.fin-ai-body em {
    font-style: italic;
}
.fin-ai-body code {
    background: rgba(41, 87, 56, 0.08);
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 12px;
}
.fin-ai-body pre {
    background: rgba(41, 87, 56, 0.08);
    padding: 10px;
    border-radius: 8px;
    overflow-x: auto;
    margin: 8px 0;
}
.fin-ai-body pre code {
    background: none;
    padding: 0;
}
.fin-ai-body .fin-md-table {
    overflow-x: auto;
    margin: 8px 0;
}
.fin-ai-body table {
    border-collapse: collapse;
    font-size: 12.5px;
    min-width: 100%;
}
.fin-ai-body th,
.fin-ai-body td {
    border: 1px solid #b9cfe8;
    padding: 6px 10px;
    text-align: left;
}
.fin-ai-body th {
    background: rgba(41, 87, 56, 0.12);
    font-weight: 700;
}
.fin-detail-error {
    background: #fdecea;
    border: 1px solid #f5c6c2;
    color: #c0392b;
    border-radius: 12px;
    padding: 14px 16px;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.fin-retry-btn {
    border: none;
    background: #c0392b;
    color: #fff;
    padding: 7px 14px;
    border-radius: 9px;
    cursor: pointer;
    font-weight: 700;
    font-size: 12px;
}
.fin-retry-btn:hover {
    background: #a93226;
}
.fin-no-data {
    color: #7a8280;
    font-size: 13px;
    padding: 18px 0;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.fin-no-data i {
    font-size: 1.6rem;
    color: #c9d0c0;
}
</style>

<div class="fin-stats-wrap">
    <div class="fin-stats-head">
    </div>

    <div class="fin-stat-grid">
        <div class="fin-card" data-type="net" data-metric="net">
            <div class="fin-card-top">
                <div class="fin-label">Estimated Profit <span class="fin-info-dot" title="Gross income less fuel and fixed costs">ⓘ</span></div>
                <div class="fin-icon"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="fin-value" id="finNet">₱0.00</div>
            <div class="fin-sub" id="finNetSub">After fixed costs</div>
            <div class="fin-spark"><canvas id="finNetSpark"></canvas></div>
            <div class="fin-foot">
                <span class="fin-period" data-fin-period>—</span>
                <div class="fin-trend" id="finNetTrend"></div>
            </div>
        </div>

        <div class="fin-card" data-type="pending" data-metric="pending">
            <div class="fin-card-top">
                <div class="fin-label">Pending Remittances</div>
                <div class="fin-icon"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="fin-value" id="finPending">₱0.00</div>
            <div class="fin-sub" id="finPendingSub">Awaiting remittance</div>
            <div class="fin-spark"><canvas id="finPendingSpark"></canvas></div>
            <div class="fin-foot">
                <span class="fin-period" data-fin-period>—</span>
                <div class="fin-trend" id="finPendingTrend"></div>
            </div>
        </div>

        <div class="fin-card" data-type="completed" data-metric="completed">
            <div class="fin-card-top">
                <div class="fin-label">Completed Remittances</div>
                <div class="fin-icon"><i class="bi bi-check2-circle"></i></div>
            </div>
            <div class="fin-value" id="finCompleted">₱0.00</div>
            <div class="fin-sub" id="finCompletedSub">Finalized remittances</div>
            <div class="fin-spark"><canvas id="finCompletedSpark"></canvas></div>
            <div class="fin-foot">
                <span class="fin-period" data-fin-period>—</span>
                <div class="fin-trend" id="finCompletedTrend"></div>
            </div>
        </div>

        <div class="fin-card" data-type="fuel" data-metric="fuel">
            <div class="fin-card-top">
                <div class="fin-label">Average Fuel Consumption <span class="fin-info-dot" title="Fleet km/L from odometer distance and actual liters consumed">ⓘ</span></div>
                <div class="fin-icon"><i class="bi bi-fuel-pump"></i></div>
            </div>
            <div class="fin-value" id="finFuelEff">—</div>
            <div class="fin-sub" id="finFuelEffSub">ODO distance / actual liters (km/L)</div>
            <div class="fin-spark"><canvas id="finFuelEffSpark"></canvas></div>
            <div class="fin-foot">
                <span class="fin-period" data-fin-period>—</span>
                <div class="fin-trend" id="finFuelEffTrend"></div>
            </div>
        </div>
    </div>
</div>


<div class="fin-detail-modal" id="finDetailModal">
    <div class="fin-detail-box">
        <div class="fin-detail-header">
            <div>
                <div class="fin-detail-eyebrow" id="finDetailEyebrow"></div>
                <div class="fin-detail-title" id="finDetailTitle"></div>
            </div>
            <button class="fin-detail-close" id="finDetailClose" aria-label="Close">&times;</button>
        </div>

        <div class="fin-detail-scroll">
            <section class="fin-section">
                <h4><span class="fin-sec-ico"><i class="bi bi-bar-chart"></i></span> Summary</h4>
                <div id="finDetailSummary"></div>
            </section>

            <section class="fin-section">
                <h4><span class="fin-sec-ico"><i class="bi bi-graph-up"></i></span> Visualization</h4>
                <div class="fin-chart-wrap"><canvas id="finDetailChart"></canvas></div>
            </section>

            <section class="fin-section" id="finDetailBreakdownSection">
                <h4 id="finDetailBreakdownTitle"></h4>
                <div id="finDetailBreakdown"></div>
            </section>

            <section class="fin-section">
                <h4><span class="fin-sec-ico"><i class="bi bi-lightbulb"></i></span> Key Insights</h4>
                <ul class="fin-insights" id="finDetailInsights"></ul>
            </section>

            <section class="fin-section">
                <h4><span class="fin-sec-ico"><i class="bi bi-stars"></i></span> AI Summary</h4>
                <div class="fin-ai-body" id="finDetailAi"></div>
            </section>
        </div>
    </div>
</div>

<script>
let finCurrentRange = (function () {
    const r = document.getElementById("filterRange");
    return (r && r.value) || "year";
})();
let finCurrentPeriod = "monthly";
let finQueryDates = { from: "", to: "" };

/* Adopt the shared filter query (canonical period + legacy fields) so the KPI
   cards and every detail modal use the same selected range as the rest of the
   dashboard. Falls back to the hidden legacy fields when no query is given. */
function finApplyFilterQuery(query) {
    if (!query) return;
    window.__financeFilterQuery = query;
    try {
        const params = new URLSearchParams(query);
        const range = params.get("range");
        if (range) finCurrentRange = range;
        const period = params.get("period");
        if (period) finCurrentPeriod = period;
        finQueryDates = {
            from: params.get("from") || params.get("fromDate") || "",
            to: params.get("to") || params.get("toDate") || "",
        };
    } catch (e) {
        /* keep the previous state on a malformed query */
    }
}

let finActiveRequest = null;
let finDetailChart = null;
let finDetailAbort = null;
let finDetailMetric = null;

const FIN_LOADING_MSG = {
    today: "Loading daily data...",
    month: "Loading monthly data...",
    year: "Loading yearly data...",
    custom: "Loading selected range...",
};
const FIN_PERIOD_LABEL = {
    today: "yesterday",
    month: "previous month",
    year: "previous year",
    custom: "previous period",
};

function finMoney(n) {
    n = Number(n || 0);
    return "₱" + n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function finNum(n) {
    n = Number(n || 0);
    return n.toLocaleString("en-US");
}
function finPctStr(cur, prev) {
    const c = Number(cur || 0),
        p = Number(prev || 0);
    if (p === 0) return c === 0 ? "0.00%" : "—";
    return (((c - p) / Math.abs(p)) * 100).toFixed(2) + "%";
}
function finDiff(cur, prev) {
    return Number(cur || 0) - Number(prev || 0);
}
function finEscape(s) {
    return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}
function finPeriodText(range) {
    const now = new Date();
    const M = [
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
    ];
    const fmt = function (iso) {
        const parts = String(iso || "").split("-");
        if (parts.length !== 3) return "";
        return M[Number(parts[1]) - 1] + " " + Number(parts[2]) + ", " + parts[0];
    };
    if (range === "custom" || finCurrentPeriod === "quarterly" || finCurrentPeriod === "semi_annually") {
        if (finQueryDates.from && finQueryDates.to) {
            return finQueryDates.from === finQueryDates.to
                ? fmt(finQueryDates.from)
                : fmt(finQueryDates.from) + " – " + fmt(finQueryDates.to);
        }
        return "Selected period";
    }
    if (range === "today")
        return M[now.getMonth()] + " " + now.getDate() + ", " + now.getFullYear();
    if (range === "year") return String(now.getFullYear());
    if (range === "month") return M[now.getMonth()] + " " + now.getFullYear();
    return "Selected period";
}
function finCompareText(range) {
    return (
        { today: "vs previous day", month: "vs previous month", year: "vs previous year" }[range] ||
        "vs previous period"
    );
}

const FIN_CARD_IDS = ["finPending", "finCompleted", "finNet"];
const FIN_TREND_IDS = ["finPendingTrend", "finCompletedTrend", "finNetTrend", "finFuelEffTrend"];
const finSparks = {};

function finCaptureDesc() {
    document.querySelectorAll(".fin-card .fin-sub").forEach((d) => {
        if (d.dataset.orig === undefined) d.dataset.orig = d.textContent;
    });
}
function finRenderSkeletons(range) {
    finCaptureDesc();
    FIN_CARD_IDS.forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.innerHTML = '<span class="fin-skeleton"></span>';
            el.classList.add("is-loading");
            el.classList.remove("is-empty");
        }
    });
    FIN_TREND_IDS.forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = "";
            el.className = "fin-trend";
        }
    });
    document.querySelectorAll(".fin-card .fin-sub").forEach((d) => {
        d.textContent = FIN_LOADING_MSG[range] || "Loading data...";
        d.classList.add("loading-msg");
        d.classList.remove("error-msg");
    });
}
function finRenderError() {
    document.querySelectorAll(".fin-card .fin-value").forEach((el) => {
        el.classList.remove("is-loading");
        el.textContent = "—";
    });
    document.querySelectorAll(".fin-card .fin-sub").forEach((d) => {
        d.textContent = "Couldn't load data. Select a period to retry.";
        d.classList.add("error-msg");
        d.classList.remove("loading-msg");
    });
}
function finSetTrend(id, cur, prev, label) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = "fin-trend";
    if (prev === null || prev === undefined || prev === 0 || cur === null || cur === undefined) {
        el.innerHTML = "";
        return;
    }
    const pct = ((Number(cur) - Number(prev)) / Math.abs(Number(prev))) * 100;
    const up = pct >= 0;
    const arrow = up ? "▲" : "▼";
    el.innerHTML =
        '<span class="fin-trend-badge ' +
        (up ? "up" : "down") +
        '">' +
        arrow +
        " " +
        Math.abs(pct).toFixed(1) +
        "%</span>" +
        '<span class="fin-trend-label">vs ' +
        label +
        "</span>";
}
function finSetPeriodChips() {
    const text = finPeriodText(finCurrentRange);
    document.querySelectorAll(".fin-card [data-fin-period]").forEach((el) => {
        el.textContent = text;
    });
}
function finPrevPhrase() {
    return (
        { today: "yesterday", month: "the previous month", year: "the previous year" }[
            finCurrentRange
        ] || "the previous period"
    );
}
function finSetValue(id, val, quiet) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = val;
        el.classList.remove("is-loading", "is-empty");
        if (quiet) el.classList.add("is-empty");
    }
}
function finTrendSentence(subject, cur, prev) {
    const c = Number(cur || 0),
        p = Number(prev || 0);
    if (p === 0) {
        return subject + " has no comparable previous-period value.";
    }
    const pct = ((c - p) / Math.abs(p)) * 100;
    if (Math.abs(pct) < 0.05) {
        return subject + " is unchanged compared with " + finPrevPhrase() + ".";
    }
    return (
        subject +
        " " +
        (pct > 0 ? "increased " : "decreased ") +
        Math.abs(pct).toFixed(1) +
        "% compared with " +
        finPrevPhrase() +
        "."
    );
}
function finSparkline(canvasId, values, color) {
    const el = document.getElementById(canvasId);
    if (!el) return;
    if (finSparks[canvasId]) {
        finSparks[canvasId].destroy();
        delete finSparks[canvasId];
    }
    if (typeof Chart === "undefined") return;
    const arr = (values || []).map((v) => Number(v) || 0);
    if (arr.length < 2 || !arr.some((v) => v !== 0)) return;
    if (window.__hideChartNoData) window.__hideChartNoData(canvasId);
    finSparks[canvasId] = new Chart(el, {
        type: "line",
        data: {
            labels: arr.map((_, i) => String(i)),
            datasets: [
                {
                    data: arr,
                    borderColor: color,
                    backgroundColor: color + "1f",
                    borderWidth: 1.6,
                    pointRadius: 0,
                    tension: 0.4,
                    fill: true,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false } },
            layout: { padding: 0 },
        },
    });
}
function finRenderFinance(cur, prev, summary) {
    const label = FIN_PERIOD_LABEL[finCurrentRange];
    finSetPeriodChips();
    const s = summary || {};
    const pending = s.pending || {};
    const completed = s.completed || {};
    const series = s.series || {};

    window.__setAiPageData &&
        window.__setAiPageData("finance_stats", {
            range: finCurrentRange,
            current: cur,
            previous: prev,
            remittance: s,
        });

    const pendingAmount = Number(pending.amount || 0);
    finSetValue("finPending", finMoney(pendingAmount), pendingAmount <= 0);
    finSetTrend("finPendingTrend", pendingAmount, pending.previous_amount, label);
    finSparkline("finPendingSpark", series.pending || [], "#2e7d32");
    const pCount = Number(pending.count || 0);
    const pDrivers = Number(pending.drivers || 0);
    const pSub = document.getElementById("finPendingSub");
    if (pSub) {
        pSub.textContent =
            pCount > 0 || pDrivers > 0
                ? finNum(pCount) +
                  " pending trip" +
                  (pCount === 1 ? "" : "s") +
                  " • " +
                  finNum(pDrivers) +
                  " driver" +
                  (pDrivers === 1 ? "" : "s")
                : pSub.dataset.orig || "Awaiting remittance";
        pSub.classList.remove("loading-msg", "error-msg");
    }

    const completedAmount = Number(completed.amount || 0);
    finSetValue("finCompleted", finMoney(completedAmount), completedAmount <= 0);
    finSetTrend("finCompletedTrend", completedAmount, completed.previous_amount, label);
    finSparkline("finCompletedSpark", series.completed || [], "#2e7d32");
    const cCount = Number(completed.count || 0);
    const cSub = document.getElementById("finCompletedSub");
    if (cSub) {
        cSub.textContent =
            cCount > 0
                ? finNum(cCount) + " finalized remittance" + (cCount === 1 ? "" : "s")
                : cSub.dataset.orig || "Finalized remittances";
        cSub.classList.remove("loading-msg", "error-msg");
    }

    const netIncome = Number(cur.net_income ?? 0);
    const grossIncome = Number(cur.gross_income ?? cur.earnings ?? 0);
    finSetValue("finNet", finMoney(netIncome), netIncome === 0 && grossIncome === 0);
    finSetTrend("finNetTrend", netIncome, prev.net_income, label);
    finSparkline("finNetSpark", series.profit || [], "#2e7d32");
    const netSub = document.getElementById("finNetSub");
    if (netSub) {
        netSub.textContent = "After fixed costs";
        netSub.classList.remove("loading-msg", "error-msg");
    }

    const fuelEff = Number(cur.fuel_efficiency || cur.avg_kpl || 0);
    const prevFuelEff = Number(prev.fuel_efficiency || prev.avg_kpl || 0);
    const hasFuelEff = isFinite(fuelEff) && fuelEff > 0;
    finSetValue("finFuelEff", hasFuelEff ? fuelEff.toFixed(2) + " km/L" : "—", !hasFuelEff);
    finSetTrend("finFuelEffTrend", fuelEff, prevFuelEff, label);
    finSparkline("finFuelEffSpark", series.fuel || [], "#2e7d32");
    const fuelSub = document.getElementById("finFuelEffSub");
    if (fuelSub) {
        const fuelLiters = Number(cur.total_fuel_consumed || cur.total_liters || 0);
        fuelSub.textContent =
            fuelLiters > 0
                ? finNum(fuelLiters.toFixed(1)) + " L consumed · ODO/actual-liters km/L"
                : "ODO distance / actual liters (km/L)";
        fuelSub.classList.remove("loading-msg", "error-msg");
    }
}

const FIN_META = {
    gross: { title: "Gross Income", kind: "peso", icon: "bi-cash-stack" },
    net: { title: "Estimated Profit", kind: "peso", icon: "bi-wallet2" },
    trips: { title: "Total Trips", kind: "count", icon: "bi-bus-front" },
    pax: { title: "Total Passengers", kind: "count", icon: "bi-people" },
    fuel: { title: "Average Fuel Consumption", kind: "kpl", icon: "bi-fuel-pump" },
    buses: { title: "Active Buses", kind: "count", icon: "bi-truck" },
    pending: { title: "Pending Remittances", kind: "peso", icon: "bi-hourglass-split" },
    completed: { title: "Completed Remittances", kind: "peso", icon: "bi-check2-circle" },
};

function finFmt(metric, v) {
    if (FIN_META[metric].kind === "peso") return finMoney(v);
    if (FIN_META[metric].kind === "liters")
        return Number(v || 0) > 0 ? Number(v).toFixed(2) + " L" : "N/A";
    if (FIN_META[metric].kind === "kpl")
        return Number(v || 0) > 0 ? Number(v).toFixed(2) + " km/L" : "N/A";
    return finNum(v);
}

function finOpenDetail(metric) {
    if (metric === "pending" || metric === "completed") {
        finOpenRemitDetail(metric);
        return;
    }
    finDetailMetric = metric;
    const meta = FIN_META[metric];
    document.getElementById("finDetailEyebrow").textContent =
        "Detail · " + finPeriodText(finCurrentRange);
    document.getElementById("finDetailTitle").textContent = meta.title;
    document.getElementById("finDetailSummary").innerHTML =
        '<div class="fin-sum-grid"><div class="fin-sum-main"><div class="fin-sum-value"><span class="fin-skeleton"></span></div><div class="fin-sum-label">Loading…</div></div>' +
        '<div class="fin-sum-cell"><div class="fin-sum-cell-v"><span class="fin-skeleton"></span></div><div class="fin-sum-cell-l">Previous</div></div>' +
        '<div class="fin-sum-cell"><div class="fin-sum-cell-v"><span class="fin-skeleton"></span></div><div class="fin-sum-cell-l">Difference</div></div>' +
        '<div class="fin-sum-cell"><div class="fin-sum-cell-v"><span class="fin-skeleton"></span></div><div class="fin-sum-cell-l">Change</div></div></div>';
    document.getElementById("finDetailBreakdownTitle").textContent = "Breakdown";
    document.getElementById("finDetailBreakdown").innerHTML =
        '<span class="fin-skeleton"></span> <span class="fin-skeleton"></span>';
    document.getElementById("finDetailInsights").innerHTML =
        '<li><span class="fin-skeleton"></span> <span class="fin-skeleton"></span></li>';
    document.getElementById("finDetailAi").textContent = "Loading detailed statistics…";
    document.getElementById("finDetailModal").classList.add("open");
    document.body.classList.add("fin-no-scroll");
    if (finDetailChart) {
        finDetailChart.destroy();
        finDetailChart = null;
    }
    finLoadDetail(metric);
}

function finCloseDetail() {
    document.getElementById("finDetailModal").classList.remove("open");
    document.body.classList.remove("fin-no-scroll");
    if (finDetailAbort) {
        finDetailAbort.abort();
        finDetailAbort = null;
    }
}

function finReadFilter() {
    /* The shared panel query is authoritative; it carries the canonical
       period plus the legacy fields every dashboard endpoint understands. */
    if (window.__financeFilterQuery) return window.__financeFilterQuery;

    const r = document.getElementById("filterRange");
    if (!r) return "";
    const val = r.value;
    let q = "range=" + val + "&";
    if (val === "custom") {
        const f = document.getElementById("filterFromDate"),
            t = document.getElementById("filterToDate");
        if (f && f.value) q += "fromDate=" + f.value + "&";
        if (t && t.value) q += "toDate=" + t.value + "&";
    } else if (val === "today") {
        const f = document.getElementById("filterFromDate"),
            t = document.getElementById("filterToDate");
        const d = new Date();
        const pad2 = (n) => String(n).padStart(2, "0");
        const fallback = d.getFullYear() + "-" + pad2(d.getMonth() + 1) + "-" + pad2(d.getDate());
        const fromDate = f && /^\d{4}-\d{2}-\d{2}$/.test(f.value || "") ? f.value : fallback;
        const toDate = t && /^\d{4}-\d{2}-\d{2}$/.test(t.value || "") ? t.value : fallback;
        q += "fromDate=" + fromDate + "&toDate=" + toDate + "&";
        q +=
            "day=" +
            Number(fromDate.slice(8, 10)) +
            "&month=" +
            Number(fromDate.slice(5, 7)) +
            "&year=" +
            fromDate.slice(0, 4) +
            "&";
    } else {
        const y = document.getElementById("filterYear"),
            m = document.getElementById("filterMonth");
        if (y && y.value) q += "year=" + y.value + "&";
        if (val === "month" && m && m.value) q += "month=" + m.value + "&";
    }
    return q.replace(/&$/, "");
}

function finLoadDetail(metric) {
    if (metric === "pending" || metric === "completed") {
        finRenderRemitDetail(metric);
        return;
    }
    let url = "/admin/api/dashboard/stat-detail.php?metric=" + encodeURIComponent(metric);
    const q = finReadFilter();
    if (q) {
        url += "&" + q;
    } else {
        url += "&range=" + finCurrentRange;
    }

    if (finDetailAbort) finDetailAbort.abort();
    finDetailAbort = new AbortController();

    fetch(url, { signal: finDetailAbort.signal })
        .then((r) => r.json())
        .then((res) => {
            finDetailAbort = null;
            if (!res || !res.success) throw new Error(res && res.message ? res.message : "Failed");
            finRenderDetail(metric, res);
        })
        .catch((err) => {
            finDetailAbort = null;
            if (err.name === "AbortError") return;
            document.getElementById("finDetailSummary").innerHTML = "";
            document.getElementById("finDetailBreakdown").innerHTML = "";
            document.getElementById("finDetailInsights").innerHTML = "";
            document.getElementById("finDetailAi").innerHTML =
                '<div class="fin-detail-error"><span>Could not load detailed statistics.</span>' +
                '<button class="fin-retry-btn" id="finRetryBtn">Retry</button></div>';
            const rb = document.getElementById("finRetryBtn");
            if (rb) rb.addEventListener("click", () => finLoadDetail(metric));
        });
}

function finRenderDetail(metric, res) {
    const cur = res.current || {};
    const prev = res.previous || {};
    if (
        metric !== "buses" &&
        !(Number(cur.value) > 0) &&
        !Number(cur.total_trips || 0) &&
        !Number(cur.total_passengers || 0)
    ) {
        finRenderNoData();
        return;
    }
    finRenderSummary(metric, cur, prev);
    finRenderBreakdown(metric, cur, prev, res.breakdown_current, res.breakdown_previous);
    finRenderInsights(metric, cur, prev, res.breakdown_current, res.breakdown_previous);
    finRenderChart(metric, cur, prev, res.breakdown_current);
    finRequestAi(metric, cur, prev, res.breakdown_current, res.breakdown_previous);
}

function finOpenRemitDetail(metric) {
    finDetailMetric = metric;
    const meta = FIN_META[metric];
    document.getElementById("finDetailEyebrow").textContent =
        "Detail · " + finPeriodText(finCurrentRange);
    document.getElementById("finDetailTitle").textContent = meta.title;
    document.getElementById("finDetailModal").classList.add("open");
    document.body.classList.add("fin-no-scroll");
    if (finDetailChart) {
        finDetailChart.destroy();
        finDetailChart = null;
    }
    finRenderRemitDetail(metric);
}

function finRenderRemitDetail(metric) {
    const meta = FIN_META[metric];
    const s = window.__finSummary || {};
    const block = s[metric] || {};
    const series = (s.series || {})[metric] || [];
    const labels = s.labels || [];
    const isPending = metric === "pending";

    const amount = Number(block.amount || 0);
    const previous = Number(isPending ? block.previous_amount : block.previous_amount) || 0;
    const count = Number(block.count || 0);
    const previousCount = Number(isPending ? block.previous_count : block.previous_count) || 0;
    const diff = amount - previous;
    const pct = previous === 0 ? null : (diff / Math.abs(previous)) * 100;

    document.getElementById("finDetailSummary").innerHTML =
        '<div class="fin-sum-grid">' +
        '<div class="fin-sum-main"><div class="fin-sum-value">' +
        finMoney(amount) +
        '</div><div class="fin-sum-label">Current · ' +
        finPeriodText(finCurrentRange) +
        "</div></div>" +
        '<div class="fin-sum-cell"><div class="fin-sum-cell-v">' +
        finMoney(previous) +
        '</div><div class="fin-sum-cell-l">Previous</div></div>' +
        '<div class="fin-sum-cell"><div class="fin-sum-cell-v ' +
        (diff >= 0 ? "pos" : "neg") +
        '">' +
        (diff >= 0 ? "+" : "") +
        finMoney(diff) +
        '</div><div class="fin-sum-cell-l">Difference</div></div>' +
        '<div class="fin-sum-cell"><div class="fin-sum-cell-v ' +
        ((pct || 0) >= 0 ? "pos" : "neg") +
        '">' +
        (pct === null ? "—" : (pct >= 0 ? "+" : "") + pct.toFixed(1) + "%") +
        '</div><div class="fin-sum-cell-l">' +
        finCompareText(finCurrentRange) +
        "</div></div>" +
        "</div>";

    document.getElementById("finDetailBreakdownTitle").textContent = isPending
        ? "Drivers with pending remittances"
        : "Drivers with completed remittances";

    const drivers = (s.drivers || [])
        .filter((d) => (isPending ? Number(d.pending) > 0 : Number(d.completed) > 0))
        .sort((a, b) =>
            isPending
                ? Number(b.pending_amount) - Number(a.pending_amount)
                : Number(b.completed_amount) - Number(a.completed_amount),
        )
        .slice(0, 8);

    if (drivers.length === 0) {
        document.getElementById("finDetailBreakdown").innerHTML =
            '<div class="fin-no-data"><i class="bi bi-inbox"></i>No ' +
            (isPending ? "pending" : "completed") +
            " remittances for this period.</div>";
    } else {
        let rows = "";
        drivers.forEach((d) => {
            const rowAmount = isPending ? d.pending_amount : d.completed_amount;
            const rowCount = isPending ? d.pending : d.completed;
            rows +=
                "<tr><td>" +
                finEscape(d.name) +
                "</td><td>" +
                finEscape(d.driver_id) +
                "</td><td>" +
                finNum(rowCount) +
                "</td><td>" +
                finMoney(rowAmount) +
                "</td><td>" +
                finEscape(d.status || "") +
                "</td></tr>";
        });
        document.getElementById("finDetailBreakdown").innerHTML =
            '<table class="fin-data-table"><thead><tr><th>Driver</th><th>ID</th><th>Count</th><th>Amount</th><th>Status</th></tr></thead><tbody>' +
            rows +
            "</tbody></table>";
    }

    const insightItems = [
        finNum(count) +
            " " +
            (isPending ? "pending" : "completed") +
            " remittance" +
            (count === 1 ? "" : "s") +
            " totaling <b>" +
            finMoney(amount) +
            "</b> for the selected period.",
        "Previous period: <b>" +
            finMoney(previous) +
            "</b> across <b>" +
            finNum(previousCount) +
            "</b> record" +
            (previousCount === 1 ? "" : "s") +
            ".",
        finTrendSentence(meta.title, amount, previous),
    ];
    document.getElementById("finDetailInsights").innerHTML = insightItems
        .map((i) => "<li>" + i + "</li>")
        .join("");

    const ctx = document.getElementById("finDetailChart");
    if (finDetailChart) {
        finDetailChart.destroy();
        finDetailChart = null;
    }
    const hasSeries = series.some((v) => Number(v) !== 0);
    if (typeof Chart !== "undefined" && hasSeries) {
        if (window.__hideChartNoData) window.__hideChartNoData("finDetailChart");
        const color = isPending ? "#b45309" : "#2e7d32";
        finDetailChart = new Chart(ctx, {
            type: "line",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: meta.title,
                        data: series,
                        borderColor: color,
                        backgroundColor: color + "22",
                        borderWidth: 2,
                        pointRadius: 2,
                        tension: 0.35,
                        fill: true,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text:
                            meta.title +
                            " — " +
                            (s.range ? s.range.label : finPeriodText(finCurrentRange)),
                    },
                    tooltip: {
                        callbacks: {
                            label: (c) =>
                                "₱" +
                                Number(c.parsed.y || 0).toLocaleString("en-US", {
                                    minimumFractionDigits: 2,
                                }),
                        },
                    },
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#eef0e6" } },
                    x: { grid: { display: false } },
                },
            },
        });
    } else {
        if (window.__showChartNoData)
            window.__showChartNoData(
                "finDetailChart",
                "No data available for the selected period.",
            );
    }

    document.getElementById("finDetailAi").textContent =
        finTrendSentence(meta.title, amount, previous) +
        " " +
        finNum(count) +
        " " +
        (isPending ? "pending" : "completed") +
        " record" +
        (count === 1 ? "" : "s") +
        " totaling " +
        finMoney(amount) +
        " for " +
        finPeriodText(finCurrentRange) +
        ".";
}

function finRenderNoData() {
    document.getElementById("finDetailSummary").innerHTML =
        '<div class="fin-no-data"><i class="bi bi-inbox"></i>No data available for the selected period.</div>';
    document.getElementById("finDetailBreakdownTitle").textContent = "";
    document.getElementById("finDetailBreakdown").innerHTML = "";
    document.getElementById("finDetailInsights").innerHTML = "";
    document.getElementById("finDetailAi").innerHTML = "";
    if (finDetailChart) {
        finDetailChart.destroy();
        finDetailChart = null;
    }
    const ctx = document.getElementById("finDetailChart");
    if (ctx && typeof __showChartNoData === "function")
        __showChartNoData(ctx, "No data available for the selected period.");
}

function finRenderSummary(metric, cur, prev) {
    const v = finFmt(metric, cur.value);
    const pv = finFmt(metric, prev.value);
    const d = finDiff(cur.value, prev.value);
    const pc = finPctStr(cur.value, prev.value);
    const dStr = (d >= 0 ? "+" : "") + finFmt(metric, d);
    const dCls = d >= 0 ? "pos" : "neg";
    const pcCls = pc === "—" ? "" : d >= 0 ? "pos" : "neg";
    document.getElementById("finDetailSummary").innerHTML =
        '<div class="fin-sum-grid">' +
        '<div class="fin-sum-main"><div class="fin-sum-value">' +
        v +
        '</div><div class="fin-sum-label">Current · ' +
        finPeriodText(finCurrentRange) +
        "</div></div>" +
        '<div class="fin-sum-cell"><div class="fin-sum-cell-v">' +
        pv +
        '</div><div class="fin-sum-cell-l">Previous</div></div>' +
        '<div class="fin-sum-cell"><div class="fin-sum-cell-v ' +
        dCls +
        '">' +
        dStr +
        '</div><div class="fin-sum-cell-l">Difference</div></div>' +
        '<div class="fin-sum-cell"><div class="fin-sum-cell-v ' +
        pcCls +
        '">' +
        (pc === "—" ? "—" : (d >= 0 ? "+" : "") + pc) +
        '</div><div class="fin-sum-cell-l">' +
        finCompareText(finCurrentRange) +
        "</div></div>" +
        "</div>";
}

function finStatGrid(cells) {
    return (
        '<div class="fin-stat-grid2">' +
        cells
            .map(
                (c) =>
                    '<div class="fin-stat-cell"><div class="v">' +
                    c.v +
                    '</div><div class="l">' +
                    c.l +
                    "</div></div>",
            )
            .join("") +
        "</div>"
    );
}

function finRenderBreakdown(metric, cur, prev, bdCur, bdPrev) {
    const titleEl = document.getElementById("finDetailBreakdownTitle");
    const body = document.getElementById("finDetailBreakdown");

    if (metric === "gross") {
        titleEl.textContent = "Revenue Breakdown";
        body.innerHTML = finStatGrid([
            { l: "Fare Revenue", v: finMoney(cur.value) },
            { l: "Total Trips", v: finNum(cur.total_trips) },
            { l: "Total Passengers", v: finNum(cur.total_passengers) },
            { l: "Avg Fare / Trip", v: finMoney(cur.avg_fare_per_trip) },
            { l: "Avg Revenue / Passenger", v: finMoney(cur.avg_revenue_per_passenger) },
            { l: "Avg Passengers / Trip", v: finNum(cur.avg_passengers_per_trip) },
        ]);
    } else if (metric === "net") {
        titleEl.textContent = "Financial Breakdown";
        const rows = [
            ["Gross Income", cur.gross, false],
            ["Fuel (refuel transactions)", cur.fuel, true],
            ["Other Expenses", cur.other, true],
            ["Amortization", cur.amortization, true],
            ["Insurance", cur.insurance, true],
            ["GPS", cur.gps, true],
            ["OPTR", cur.optr, true],
            ["Net Income", cur.value, false],
        ];
        let html =
            '<table class="fin-data-table"><thead><tr><th>Financial Item</th><th>Amount</th></tr></thead><tbody>';
        rows.forEach((r, i) => {
            const cls =
                i === rows.length - 1
                    ? ' style="font-weight:800;color:#1565c0;"'
                    : r[2]
                      ? ' class="neg"'
                      : "";
            html +=
                "<tr><td" +
                (i === rows.length - 1 ? ' style="font-weight:800;color:#1c3a52;"' : "") +
                ">" +
                r[0] +
                "</td><td" +
                cls +
                ">" +
                finMoney(r[1]) +
                "</td></tr>";
        });
        html += "</tbody></table>";
        body.innerHTML = html;
    } else if (metric === "trips") {
        titleEl.textContent = "Operational Breakdown";
        body.innerHTML = finStatGrid([
            { l: "Avg Trips / Active Bus", v: finNum(cur.avg_trips_per_active_bus) },
            { l: "Avg Passengers / Trip", v: finNum(cur.avg_passengers_per_trip) },
            { l: "Revenue / Trip", v: finMoney(cur.revenue_per_trip) },
            { l: "Total Passengers", v: finNum(cur.total_passengers) },
            { l: "Active Buses", v: finNum(cur.active_buses) },
        ]);
    } else if (metric === "fuel") {
        titleEl.textContent = "Fuel Consumption & Cost Breakdown";
        const fLiters = Number(cur.total_fuel_consumed || cur.total_liters || 0);
        const fTrips = Number(cur.fuel_trips_count || 0);
        const avgLiters =
            cur.avg_fuel_consumed_per_trip > 0
                ? Number(cur.avg_fuel_consumed_per_trip).toFixed(2) + " L"
                : cur.avg_liters_per_trip > 0
                  ? Number(cur.avg_liters_per_trip).toFixed(2) + " L"
                  : fTrips > 0 && fLiters > 0
                    ? (fLiters / fTrips).toFixed(2) + " L"
                    : "N/A";
        const estCost = Number(
            cur.total_estimated_fuel_cost || cur.total_fuel_cost || cur.fuel || 0,
        );
        const actCost = Number(cur.total_actual_fuel_cost || 0);
        body.innerHTML = finStatGrid([
            { l: "Avg Fuel Consumed / Trip", v: avgLiters },
            { l: "Total Fuel Consumed", v: fLiters > 0 ? finNum(fLiters.toFixed(2)) + " L" : "—" },
            { l: "Trips with Fuel Records", v: finNum(fTrips) },
            { l: "Total Est. Fuel Cost", v: estCost > 0 ? finMoney(estCost) : "—" },
            { l: "Total Actual Refuel Cost", v: actCost > 0 ? finMoney(actCost) : "—" },
            {
                l: "Fleet Consumption",
                v:
                    cur.avg_kpl > 0
                        ? Number(cur.avg_kpl).toFixed(2) + " km/L"
                        : cur.avg_l_per_100km > 0
                          ? (100 / Number(cur.avg_l_per_100km)).toFixed(2) + " km/L"
                          : "—",
            },
        ]);
    } else if (metric === "buses") {
        titleEl.textContent = "Fleet Breakdown";
        body.innerHTML = finStatGrid([
            { l: "Fleet Utilization", v: cur.utilization + "%" },
            { l: "Total Buses", v: finNum(cur.total_buses) },
            { l: "Trips / Active Bus", v: finNum(cur.trips_per_active_bus) },
            { l: "Passengers / Active Bus", v: finNum(cur.passengers_per_active_bus) },
        ]);
    } else if (metric === "pax") {
        titleEl.textContent = "Passenger Type Breakdown";
        const curMap = {},
            prevMap = {};
        (bdCur || []).forEach((r) => (curMap[r.label] = Number(r.total) || 0));
        (bdPrev || []).forEach((r) => (prevMap[r.label] = Number(r.total) || 0));
        const types = Array.from(new Set([...Object.keys(curMap), ...Object.keys(prevMap)]));
        types.sort((a, b) => (curMap[b] || 0) - (curMap[a] || 0));
        let totalCur = 0,
            totalPrev = 0,
            rows = "";
        types.forEach((t) => {
            const c = curMap[t] || 0,
                p = prevMap[t] || 0,
                d = c - p;
            const pc = finPctStr(c, p);
            totalCur += c;
            totalPrev += p;
            const dCls = d < 0 ? "neg" : "";
            rows +=
                "<tr><td>" +
                finEscape(t) +
                "</td><td>" +
                finNum(c) +
                "</td><td>" +
                finNum(p) +
                "</td>" +
                '<td class="' +
                dCls +
                '">' +
                (d >= 0 ? "+" : "") +
                finNum(d) +
                "</td>" +
                '<td class="' +
                dCls +
                '">' +
                (pc === "—" ? "—" : (d >= 0 ? "+" : "") + pc) +
                "</td></tr>";
        });
        rows +=
            '<tr class="fin-total-row"><td>Total</td><td>' +
            finNum(totalCur) +
            "</td><td>" +
            finNum(totalPrev) +
            "</td>" +
            "<td>" +
            (totalCur - totalPrev >= 0 ? "+" : "") +
            finNum(totalCur - totalPrev) +
            "</td><td></td></tr>";
        body.innerHTML =
            '<table class="fin-data-table"><thead><tr><th>Passenger Type</th><th>Current</th><th>Previous</th><th>Change</th><th>% Change</th></tr></thead><tbody>' +
            rows +
            "</tbody></table>";
    }
}

function finRenderInsights(metric, cur, prev, bdCur, bdPrev) {
    const items = [];
    const add = (html) => items.push(html);
    if (metric === "gross") {
        add(
            "Fare revenue for the period is <b>" +
                finMoney(cur.value) +
                "</b> across <b>" +
                finNum(cur.total_trips) +
                "</b> trips.",
        );
        add(
            "Average fare per trip is <b>" +
                finMoney(cur.avg_fare_per_trip) +
                "</b> with <b>" +
                finNum(cur.avg_passengers_per_trip) +
                "</b> passengers per trip on average.",
        );
        const d = finDiff(cur.value, prev.value);
        add(
            "Compared to the " +
                finCompareText(finCurrentRange) +
                ", gross income " +
                (d >= 0 ? "rose" : "fell") +
                " by <b>" +
                finMoney(Math.abs(d)) +
                "</b> (" +
                finPctStr(cur.value, prev.value) +
                ").",
        );
    } else if (metric === "net") {
        const fuelPct = cur.gross > 0 ? (cur.fuel / cur.gross) * 100 : 0;
        add("Net income is <b>" + finMoney(cur.value) + "</b> after fuel and fixed driver costs.");
        add(
            "Fuel from refuel transactions consumed <b>" +
                finMoney(cur.fuel) +
                "</b> (" +
                fuelPct.toFixed(1) +
                "% of gross); fixed costs totaled <b>" +
                finMoney(cur.fixed_total) +
                "</b>.",
        );
        add(
            "Profit margin is <b>" +
                (cur.gross > 0 ? ((cur.value / cur.gross) * 100).toFixed(1) : "0.0") +
                "%</b> of gross income.",
        );
    } else if (metric === "trips") {
        add(
            "Recorded <b>" +
                finNum(cur.value) +
                "</b> trips with <b>" +
                finNum(cur.avg_passengers_per_trip) +
                "</b> passengers per trip on average.",
        );
        add(
            "Each active bus averaged <b>" +
                finNum(cur.avg_trips_per_active_bus) +
                "</b> trips, generating <b>" +
                finMoney(cur.revenue_per_trip) +
                "</b> per trip.",
        );
    } else if (metric === "fuel") {
        const avg =
            cur.avg_fuel_consumed_per_trip > 0
                ? Number(cur.avg_fuel_consumed_per_trip).toFixed(2) + " L"
                : cur.avg_liters_per_trip > 0
                  ? Number(cur.avg_liters_per_trip).toFixed(2) + " L"
                  : "N/A";
        const tripsWithFuel = finNum(cur.fuel_trips_count || 0);
        add(
            "Average fuel consumed per logged trip is <b>" +
                avg +
                "</b> across <b>" +
                tripsWithFuel +
                "</b> trips with valid fuel records.",
        );
        if (Number(cur.total_fuel_consumed || cur.total_liters || 0) > 0) {
            add(
                "Total fuel consumed recorded is <b>" +
                    finNum(Number(cur.total_fuel_consumed || cur.total_liters).toFixed(2)) +
                    " L</b> for the selected period.",
            );
        }
        if (Number(cur.total_estimated_fuel_cost || cur.total_fuel_cost || 0) > 0) {
            add(
                "Total estimated fuel cost is <b>" +
                    finMoney(cur.total_estimated_fuel_cost || cur.total_fuel_cost) +
                    "</b> based on current fuel prices.",
            );
        }
        if (Number(cur.avg_kpl || 0) > 0) {
            add(
                "Fleet average fuel consumption is <b>" +
                    Number(cur.avg_kpl).toFixed(2) +
                    " km/L</b> from odometer distance and actual liters consumed.",
            );
        }
        const prevAvg = prev ? (prev.avg_fuel_consumed_per_trip ?? prev.avg_liters_per_trip) : null;
        const curAvg = cur.avg_fuel_consumed_per_trip ?? cur.avg_liters_per_trip;
        if (prevAvg > 0 && curAvg > 0) {
            const d = finDiff(curAvg, prevAvg);
            add(
                "Compared to the " +
                    finCompareText(finCurrentRange) +
                    ", average fuel consumed per trip " +
                    (d >= 0 ? "increased" : "decreased") +
                    " by <b>" +
                    Math.abs(d).toFixed(2) +
                    " L</b> (" +
                    finPctStr(curAvg, prevAvg) +
                    ").",
            );
        }
    } else if (metric === "buses") {
        add(
            "<b>" +
                finNum(cur.value) +
                "</b> of <b>" +
                finNum(cur.total_buses) +
                "</b> buses are active (" +
                cur.utilization +
                "% utilization).",
        );
        add(
            "Active buses averaged <b>" +
                finNum(cur.trips_per_active_bus) +
                "</b> trips and <b>" +
                finNum(cur.passengers_per_active_bus) +
                "</b> passengers each.",
        );
    } else if (metric === "pax") {
        const curMap = {};
        (bdCur || []).forEach((r) => (curMap[r.label] = Number(r.total) || 0));
        const top = Object.keys(curMap).sort((a, b) => curMap[b] - curMap[a])[0];
        const total = Object.values(curMap).reduce((a, b) => a + b, 0);
        add(
            "Total passengers served: <b>" +
                finNum(cur.value) +
                "</b> (" +
                finNum(cur.total_trips) +
                " trips, avg " +
                finNum(cur.avg_passengers_per_trip) +
                " per trip).",
        );
        if (top)
            add(
                "Largest segment: <b>" +
                    finEscape(top) +
                    "</b> with <b>" +
                    finNum(curMap[top]) +
                    "</b> passengers (" +
                    (total > 0 ? ((curMap[top] / total) * 100).toFixed(1) : "0.0") +
                    "% of total).",
            );
        add(
            "All passenger types stored in the system are listed below, including <b>Other</b> and uncategorized records.",
        );
    }
    document.getElementById("finDetailInsights").innerHTML = items
        .map((i) => "<li>" + i + "</li>")
        .join("");
}

function finRenderChart(metric, cur, prev, bdCur) {
    const ctx = document.getElementById("finDetailChart");
    if (finDetailChart) {
        finDetailChart.destroy();
        finDetailChart = null;
    }
    if (typeof Chart === "undefined") return;

    let config;
    if (metric === "pax") {
        const curMap = {};
        (bdCur || []).forEach((r) => (curMap[r.label] = Number(r.total) || 0));
        const labels = Object.keys(curMap).sort((a, b) => curMap[b] - curMap[a]);
        config = {
            type: "bar",
            data: {
                labels,
                datasets: [
                    {
                        label: "Passengers",
                        data: labels.map((l) => curMap[l]),
                        backgroundColor: "#295738",
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: "Passengers by Type (Current)" },
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: "#eef0e6" } },
                    y: { grid: { display: false } },
                },
            },
        };
    } else if (metric === "net") {
        const labels = [
            "Gross",
            "Fuel",
            "Other",
            "Amortization",
            "Insurance",
            "GPS",
            "OPTR",
            "Net",
        ];
        const data = [
            cur.gross,
            -Number(cur.fuel || 0),
            -Number(cur.other || 0),
            -Number(cur.amortization || 0),
            -Number(cur.insurance || 0),
            -Number(cur.gps || 0),
            -Number(cur.optr || 0),
            cur.value,
        ];
        const colors = [
            "#2e7d32",
            "#e65100",
            "#f9a825",
            "#5e35b1",
            "#00838f",
            "#558b2f",
            "#c2185b",
            "#1565c0",
        ];
        config = {
            type: "bar",
            data: {
                labels,
                datasets: [{ label: "Amount", data, backgroundColor: colors, borderRadius: 6 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: "Net Income Breakdown (deductions shown negative)",
                    },
                    tooltip: {
                        callbacks: {
                            label: (c) =>
                                "₱" +
                                Number(c.parsed.y).toLocaleString("en-US", {
                                    minimumFractionDigits: 2,
                                }),
                        },
                    },
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#eef0e6" } },
                    x: { grid: { display: false } },
                },
            },
        };
    } else {
        const kind = FIN_META[metric].kind;
        const labels = ["Current", "Previous"];
        const data = [cur.value, prev.value];
        config = {
            type: "bar",
            data: {
                labels,
                datasets: [
                    {
                        label: FIN_META[metric].title,
                        data,
                        backgroundColor: ["#295738", "#b6c08a"],
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: FIN_META[metric].title + " — Current vs Previous",
                    },
                    tooltip: {
                        callbacks: {
                            label: (c) =>
                                (kind === "peso" ? "₱" : "") +
                                Number(c.parsed.y).toLocaleString("en-US", {
                                    minimumFractionDigits: kind === "count" ? 0 : 2,
                                }) +
                                (kind === "liters" ? " L" : kind === "kpl" ? " km/L" : ""),
                        },
                    },
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: "#eef0e6" } },
                    x: { grid: { display: false } },
                },
            },
        };
    }
    finDetailChart = new Chart(ctx, config);

    const chartDataArr =
        config.data && config.data.datasets && config.data.datasets[0]
            ? config.data.datasets[0].data || []
            : [];
    const hasFinData = chartDataArr.some((v) => Math.abs(Number(v) || 0) > 0);
    if (hasFinData) {
        if (window.__hideChartNoData) window.__hideChartNoData("finDetailChart");
    } else {
        if (window.__showChartNoData)
            window.__showChartNoData(
                "finDetailChart",
                "No data available for the selected period.",
            );
    }
}

function finAiPrompt(metric, cur, prev, bdCur, bdPrev) {
    const period = finPeriodText(finCurrentRange);
    const cmp = finCompareText(finCurrentRange);
    if (metric === "gross") {
        return (
            "Give a concise financial summary of Gross Income (total fare revenue) for " +
            period +
            ". Gross income is " +
            finMoney(cur.value) +
            " (" +
            cmp +
            ": " +
            finMoney(prev.value) +
            "). There were " +
            finNum(cur.total_trips) +
            " trips and " +
            finNum(cur.total_passengers) +
            " passengers, with an average fare of " +
            finMoney(cur.avg_fare_per_trip) +
            " per trip. Use only these figures."
        );
    }
    if (metric === "net") {
        return (
            "Explain the Net Income for " +
            period +
            " using ONLY these validated figures. Gross income " +
            finMoney(cur.gross) +
            ", fuel from refuel transactions " +
            finMoney(cur.fuel) +
            ", other expenses " +
            finMoney(cur.other) +
            ", amortization " +
            finMoney(cur.amortization) +
            ", insurance " +
            finMoney(cur.insurance) +
            ", GPS " +
            finMoney(cur.gps) +
            ", OPTR " +
            finMoney(cur.optr) +
            ", total fixed costs " +
            finMoney(cur.fixed_total) +
            " = Net income " +
            finMoney(cur.value) +
            ". Was it profitable and what mainly reduced profit?"
        );
    }
    if (metric === "trips") {
        return (
            "Summarize Total Trips for " +
            period +
            ". Trips: " +
            finNum(cur.value) +
            " (" +
            cmp +
            ": " +
            finNum(prev.value) +
            "). Average " +
            finNum(cur.avg_passengers_per_trip) +
            " passengers per trip, " +
            finNum(cur.avg_trips_per_active_bus) +
            " trips per active bus, revenue per trip " +
            finMoney(cur.revenue_per_trip) +
            ". What does this imply for operations?"
        );
    }
    if (metric === "fuel") {
        const avg =
            cur.avg_fuel_consumed_per_trip > 0
                ? Number(cur.avg_fuel_consumed_per_trip).toFixed(2) + " L"
                : cur.avg_liters_per_trip > 0
                  ? Number(cur.avg_liters_per_trip).toFixed(2) + " L"
                  : "N/A";
        return (
            "Summarize Average Fuel Consumed / Trip for " +
            period +
            ". Average fuel consumed per trip: " +
            avg +
            " across " +
            finNum(cur.fuel_trips_count || 0) +
            " trips with valid fuel records. Total fuel consumed: " +
            (cur.total_fuel_consumed || cur.total_liters || 0) +
            " L. Fleet average fuel consumption: " +
            (Number(cur.avg_kpl || 0) > 0 ? Number(cur.avg_kpl).toFixed(2) + " km/L" : "N/A") +
            ". Estimated fuel cost: " +
            finMoney(cur.total_estimated_fuel_cost || cur.total_fuel_cost || 0) +
            ". Explain operational fuel efficiency."
        );
    }
    if (metric === "buses") {
        return (
            "Summarize Active Buses for " +
            period +
            ". Active buses: " +
            finNum(cur.value) +
            " of " +
            finNum(cur.total_buses) +
            " total (" +
            cur.utilization +
            "% utilization). Each active bus averaged " +
            finNum(cur.trips_per_active_bus) +
            " trips and " +
            finNum(cur.passengers_per_active_bus) +
            " passengers. Assess fleet utilization."
        );
    }
    if (metric === "pax") {
        const curMap = {},
            prevMap = {};
        (bdCur || []).forEach((r) => (curMap[r.label] = Number(r.total) || 0));
        (bdPrev || []).forEach((r) => (prevMap[r.label] = Number(r.total) || 0));
        const parts = Object.keys(curMap)
            .sort((a, b) => curMap[b] - curMap[a])
            .map((t) => t + ": " + finNum(curMap[t]) + " (prev " + finNum(prevMap[t] || 0) + ")");
        return (
            "Summarize passenger ridership for " +
            period +
            " using ONLY these passenger-type counts (total passengers " +
            finNum(cur.value) +
            "): " +
            parts.join("; ") +
            ". Which segments grew or shrank versus the " +
            cmp +
            "?"
        );
    }
    return "Summarize the " + FIN_META[metric].title + " for " + period + ".";
}

async function finRequestAi(metric, cur, prev, bdCur, bdPrev) {
    const box = document.getElementById("finDetailAi");
    box.textContent = "Asking Riza for a summary…";
    const prompt = finAiPrompt(metric, cur, prev, bdCur, bdPrev);
    try {
        const res = await fetch("/finance/api/ai/chat.php", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ session_id: null, message: prompt }),
        });
        const data = await res.json();
        const reply = ((data && (data.reply || data.response)) || "").trim();
        if (reply) {
            box.innerHTML =
                typeof renderMarkdown === "function"
                    ? renderMarkdown(reply)
                    : finEscape(reply).replace(/\n/g, "<br>");
        } else {
            throw new Error("empty");
        }
    } catch (e) {
        box.innerHTML = finFallbackMd(metric, cur, prev, bdCur, bdPrev);
    }
}

function finFallbackMd(metric, cur, prev, bdCur, bdPrev) {
    let md = "**" + FIN_META[metric].title + " — " + finPeriodText(finCurrentRange) + "**\n\n";
    if (metric === "net") {
        md += "- Gross Income: " + finMoney(cur.gross) + "\n";
        md += "- Fuel (refuel transactions): " + finMoney(cur.fuel) + "\n";
        md += "- Other Expenses: " + finMoney(cur.other) + "\n";
        md += "- Amortization: " + finMoney(cur.amortization) + "\n";
        md += "- Insurance: " + finMoney(cur.insurance) + "\n";
        md += "- GPS: " + finMoney(cur.gps) + "\n";
        md += "- OPTR: " + finMoney(cur.optr) + "\n";
        md += "- **Net Income: " + finMoney(cur.value) + "**\n";
    } else if (metric === "fuel") {
        const avg =
            cur.avg_fuel_consumed_per_trip > 0
                ? Number(cur.avg_fuel_consumed_per_trip).toFixed(2) + " L"
                : cur.avg_liters_per_trip > 0
                  ? Number(cur.avg_liters_per_trip).toFixed(2) + " L"
                  : "N/A";
        md += "- Average Fuel Consumed / Trip: **" + avg + "**\n";
        md +=
            "- Total Fuel Consumed: " +
            (cur.total_fuel_consumed || cur.total_liters
                ? finNum(Number(cur.total_fuel_consumed || cur.total_liters).toFixed(2)) + " L"
                : "—") +
            "\n";
        md += "- Trips with Fuel Records: " + finNum(cur.fuel_trips_count || 0) + "\n";
        if (cur.total_estimated_fuel_cost || cur.total_fuel_cost)
            md +=
                "- Total Estimated Fuel Cost: " +
                finMoney(cur.total_estimated_fuel_cost || cur.total_fuel_cost) +
                "\n";
        const fleetKpl =
            cur.avg_kpl > 0
                ? Number(cur.avg_kpl)
                : cur.avg_l_per_100km > 0
                  ? 100 / Number(cur.avg_l_per_100km)
                  : 0;
        if (fleetKpl > 0)
            md += "- Average Fuel Consumption: " + fleetKpl.toFixed(2) + " km/L\n";
        const prevAvg = prev ? (prev.avg_fuel_consumed_per_trip ?? prev.avg_liters_per_trip) : null;
        const curAvg = cur.avg_fuel_consumed_per_trip ?? cur.avg_liters_per_trip;
        if (prevAvg > 0 && curAvg > 0) {
            md +=
                "- Change vs " +
                finCompareText(finCurrentRange) +
                ": " +
                finPctStr(curAvg, prevAvg) +
                "\n";
        }
    } else if (metric === "pax") {
        const curMap = {};
        (bdCur || []).forEach((r) => (curMap[r.label] = Number(r.total) || 0));
        md += "Total passengers: **" + finNum(cur.value) + "**\n\n";
        md += "| Type | Current |\n|---|---|\n";
        Object.keys(curMap)
            .sort((a, b) => curMap[b] - curMap[a])
            .forEach((t) => {
                md += "| " + t + " | " + finNum(curMap[t]) + " |\n";
            });
    } else {
        md += "- Current: " + finFmt(metric, cur.value) + "\n";
        md += "- Previous: " + finFmt(metric, prev.value) + "\n";
        md +=
            "- Change: " +
            finPctStr(cur.value, prev.value) +
            " " +
            finCompareText(finCurrentRange) +
            "\n";
    }
    return typeof renderMarkdown === "function"
        ? renderMarkdown(md)
        : md.replace(/\n/g, "<br>").replace(/\*\*/g, "<b>").replace(/\*/g, "</b>");
}

function loadFinanceStats(filterQuery) {
    finApplyFilterQuery(filterQuery);
    if (!filterQuery) {
        const hr = document.getElementById("filterRange");
        if (hr && hr.value) finCurrentRange = hr.value;
    }
    const range = finCurrentRange;
    finRenderSkeletons(range);
    if (finActiveRequest) {
        finActiveRequest.abort();
    }
    const ac = new AbortController();
    finActiveRequest = ac;

    const q = finReadFilter();
    const query = q || "range=" + range;
    const statsUrl = "/admin/api/dashboard/stats.php?" + query;
    const summaryUrl = "/finance/api/dashboard/finance_summary.php?" + query;

    Promise.all([
        fetch(statsUrl, { signal: ac.signal })
            .then((res) => res.json())
            .catch((err) => ({ __error: err.name === "AbortError" ? "abort" : "stats" })),
        fetch(summaryUrl, { signal: ac.signal })
            .then((res) => res.json())
            .catch((err) => ({ __error: err.name === "AbortError" ? "abort" : "summary" })),
    ])
        .then(([stats, summary]) => {
            if (finActiveRequest !== ac) return;

            if ((stats && stats.__error === "abort") || (summary && summary.__error === "abort"))
                return;

            const statsOk = stats && stats.success;
            const summaryOk = summary && summary.success;

            if (!statsOk && !summaryOk) {
                console.error("Finance stats API error:", stats, summary);
                finRenderError();
                return;
            }
            if (!statsOk) {
                console.error("Finance stats API error:", stats);
            }
            if (!summaryOk) {
                console.error("Finance summary API error:", summary);
            }

            window.__finSummary = summaryOk ? summary : null;
            finRenderFinance(
                statsOk ? stats.data || {} : {},
                statsOk ? stats.previous || {} : {},
                summaryOk ? summary : {},
            );
            if (!summaryOk) {
                finSetValue("finPending", "—", true);
                finSetValue("finCompleted", "—", true);
            }
        })
        .catch((err) => {
            if (err.name === "AbortError") return;
            if (finActiveRequest !== ac) return;
            console.error("Finance stats fetch failed:", err);
            finRenderError();
        });
}

window.__finSetFuelKpi = function (data) {
    data = data || {};
    finSetPeriodChips();
    var consumption = Number(data.consumption);
    if (!(consumption > 0) && Number(data.kpl) > 0) {
        consumption = Number(data.kpl);
    }
    var previous = Number(data.previous_consumption);
    if (!(previous > 0) && Number(data.previous_kpl) > 0) {
        previous = Number(data.previous_kpl);
    }
    var hasConsumption = isFinite(consumption) && consumption > 0;
    finSetValue(
        "finFuelEff",
        hasConsumption ? consumption.toFixed(2) + " km/L" : "—",
        !hasConsumption,
    );
    if (hasConsumption) {
        finSetTrend("finFuelEffTrend", consumption, previous, FIN_PERIOD_LABEL[finCurrentRange]);
    } else {
        const trendEl = document.getElementById("finFuelEffTrend");
        if (trendEl) {
            trendEl.textContent = "";
            trendEl.className = "fin-trend";
        }
    }
    finSparkline("finFuelEffSpark", data.series || [], "#2e7d32");
    const sub = document.getElementById("finFuelEffSub");
    if (sub) {
        sub.textContent = data.error
            ? "Fuel analytics unavailable"
            : Number(data.liters || 0) > 0
              ? finNum(Number(data.liters).toFixed(1)) + " L consumed · ODO/actual-liters km/L"
              : "ODO distance / actual liters (km/L)";
        sub.classList.remove("loading-msg", "error-msg");
    }
};

document.querySelectorAll(".fin-card[data-metric]").forEach((card) => {
    card.addEventListener("click", () => {
        finOpenDetail(card.dataset.metric);
    });
});
document.getElementById("finDetailClose").addEventListener("click", finCloseDetail);
document.getElementById("finDetailModal").addEventListener("click", (e) => {
    if (e.target.id === "finDetailModal") finCloseDetail();
});
document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") finCloseDetail();
});

document.addEventListener("DOMContentLoaded", () => {
    if (window.applyFilters && typeof window.applyFilters === "function") {
        window.applyFilters();
    } else if (!window.__financeFilterQuery) {
        loadFinanceStats();
    }
});

window.loadFinanceStats = loadFinanceStats;
</script>

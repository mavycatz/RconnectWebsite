<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.card canvas {
    max-width: 100% !important;
    display: block;
}

.chart-fixed-height {
    position: relative;
}

.di-gran-btn {
    background: transparent;
    border: none;
    font-size: 0.68rem;
    font-weight: 600;
    color: #7d8b78;
    padding: 3px 11px;
    border-radius: 18px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.di-gran-btn:hover {
    color: #295738;
}

.di-gran-active {
    background: #295738;
    color: #f9f4da;
}

.di-gran-active:hover {
    background: #295738;
    color: #f9f4da;
}

.di-kpi-card {
    background: #f9f4da;
    border: 1px solid #cdd9b5;
    border-radius: 12px;
    padding: 14px 16px;
    height: 100%;
}

.di-kpi-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #2f4f2f;
    letter-spacing: 0.02em;
    opacity: 0.8;
}

.di-kpi-value {
    font-size: 1.55rem;
    font-weight: 700;
    color: #295738;
    line-height: 1.15;
}

.di-kpi-sub {
    font-size: 0.68rem;
    color: #94a3b8;
    font-weight: 500;
}

.di-best {
    font-size: 0.66rem;
    color: #6b9b72;
    font-weight: 600;
    margin-top: 6px;
}

.di-table th {
    font-size: 0.66rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #7d8b78;
    font-weight: 700;
    padding: 6px 8px;
    border-bottom: 1px solid #cdd9b5;
    white-space: nowrap;
}

.di-table td {
    font-size: 0.72rem;
    color: #2f4f2f;
    padding: 6px 8px;
    border-bottom: 1px solid #e8eed7;
    white-space: nowrap;
}

.di-table tr.di-best-row {
    background: #eef3da;
}

.di-table tr.di-best-row td:first-child::after {
    content: " ★";
    color: #c4b417;
}

.top-driver {
    background: #f9f4da !important;
    border: 1px solid #c4b417 !important;
}

.ai-box {
    border: 1px solid #cdd9b5;
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 10px;
    background: #f9f4da;
}

.dp-loading {
    background: linear-gradient(180deg, #f9f4da 0%, #fef8f3 100%);
    border: 1px dashed #cdd9b5;
    border-radius: 14px;
    padding: 20px;
}

.dp-loading-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
}

.dp-loader {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid #cdd9b5;
    border-top-color: #295738;
    animation: dp-spin 0.9s linear infinite;
}

@keyframes dp-spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.dp-loading-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #295738;
}

.dp-loading-sub {
    font-size: 0.78rem;
    color: #94a3b8;
}

.dp-skeleton-block {
    background: #fff;
    border: 1px solid #cdd9b5;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 14px;
}

.sk {
    position: relative;
    overflow: hidden;
    background: #ece7d2;
    border-radius: 8px;
}

.sk::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 40%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.65), transparent);
    animation: dp-shimmer 1.3s infinite;
}

@keyframes dp-shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(260%);
    }
}

.sk-line {
    height: 12px;
}
.sk-bar-sm {
    height: 8px;
}
.sk-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
}
.sk-ai {
    height: 64px;
    border-radius: 10px;
}
</style>

<style>
@media (max-width: 768px) {
    #driverPerformanceContainer {
        padding: 14px !important;
    }
    #driverPerformanceContainer .col-md-7,
    #driverPerformanceContainer .col-md-5,
    #driverPerformanceContainer .col-md-6 {
        width: 100% !important;
    }
    #driverPerformanceContainer .card[style*="height:420px"] {
        height: auto !important;
        min-height: 320px;
    }
}
</style>
<div class="container mb-4" id="driverPerformanceContainer"
style="width:100%;margin:0 auto;background:linear-gradient(180deg,#ffffff 0%,#fef8f3 100%);border:1px solid #cdd9b5;border-radius:14px;padding:28px;box-shadow:0 8px 20px rgba(0,0,0,0.05);">

<div class="d-flex justify-content-between align-items-center mb-4">
<div>
<h4 class="fw-bold mb-1" style="color:#295738; font-size:1.15rem;">
<i class="bi bi-trophy-fill text-success me-2"></i>Driver Performance Analytics</h4>
<small style="color:#94a3b8; font-size:0.8rem;">Compare driver performance based on trips, passengers, and earnings</small>
</div>

<div class="d-inline-flex align-items-center gap-1" style="background:#f9f4da;border:1px solid #cdd9b5;padding:5px 14px;border-radius:20px;font-size:0.7rem;font-weight:600;color:#295738;">
<i class="bi bi-activity" style="font-size:0.75rem;"></i> Live Analytics
</div>
</div>

<div id="driverPerformanceLoading" class="row g-4">

<div class="col-md-7">
<div class="card p-3 shadow-sm"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:420px;">
<div class="d-flex align-items-center gap-2 mb-2">
<h6 class="fw-semibold mb-0" style="font-size:0.85rem; color:#2f4f2f;"><i class="bi bi-bar-chart-fill text-success me-2"></i>Trips vs Earnings</h6>
<div class="dp-loader ms-auto"></div>
</div>
<div class="sk" style="height:330px;border-radius:10px;"></div>
</div>
</div>

<div class="col-md-5">
<div class="card p-3 shadow-sm"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:420px;">
<div class="d-flex justify-content-between align-items-center mb-3">
<h6 class="fw-semibold mb-0" style="font-size:0.85rem; color:#2f4f2f;"><i class="bi bi-list-ol text-success me-2"></i>Driver Ranking</h6>
<div class="d-inline-flex align-items-center gap-2" style="font-size:0.75rem;font-weight:600;color:#198754;">
<div class="dp-loader"></div> Loading...
</div>
</div>

<div class="d-flex align-items-center gap-3 mb-3">
<div class="sk sk-avatar"></div>
<div class="flex-grow-1"><div class="sk sk-bar-sm mb-2" style="width:55%;"></div><div class="sk sk-bar-sm" style="width:35%;"></div></div>
</div>
<div class="d-flex align-items-center gap-3 mb-3">
<div class="sk sk-avatar"></div>
<div class="flex-grow-1"><div class="sk sk-bar-sm mb-2" style="width:60%;"></div><div class="sk sk-bar-sm" style="width:30%;"></div></div>
</div>
<div class="d-flex align-items-center gap-3 mb-3">
<div class="sk sk-avatar"></div>
<div class="flex-grow-1"><div class="sk sk-bar-sm mb-2" style="width:50%;"></div><div class="sk sk-bar-sm" style="width:40%;"></div></div>
</div>
<div class="d-flex align-items-center gap-3">
<div class="sk sk-avatar"></div>
<div class="flex-grow-1"><div class="sk sk-bar-sm mb-2" style="width:45%;"></div><div class="sk sk-bar-sm" style="width:35%;"></div></div>
</div>
</div>
</div>

</div>

<div class="row g-4" id="driverPerformanceBody" style="display:none;">

<div class="col-md-7">
<div class="card p-3 shadow-sm"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:420px;">
<h6 class="fw-semibold mb-2" style="font-size:0.85rem; color:#2f4f2f;"><i class="bi bi-bar-chart-fill text-success me-2"></i>Trips vs Earnings</h6>
<div style="position:relative;height:350px;">
<canvas id="performanceChart"></canvas>
</div>
</div>
</div>

<div class="col-md-5">
<div id="rankingPanel" class="p-3"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:420px;overflow:auto;">

<div class="d-flex justify-content-between align-items-center mb-3">
<h6 class="fw-semibold mb-0" style="font-size:0.85rem; color:#2f4f2f;">
<i class="bi bi-list-ol text-success me-2"></i>Driver Ranking
</h6>

<select id="limitSelect" 
class="form-select form-select-sm"
style="width:auto;border:1px solid #cdd9b5;border-radius:8px;background:white;font-weight:500;padding:2px 8px;font-size:0.75rem;">
<option value="5">Top 5</option>
<option value="10">Top 10</option>
<option value="20">Top 20</option>
</select>

</div>

<div id="driverList" class="d-flex flex-column gap-2"></div>

</div>

<div id="aiPanel" class="card p-3 shadow-sm"
style="display:none;background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:420px;overflow:auto;">

<div class="d-flex justify-content-between align-items-center mb-2">
<h6 class="fw-semibold mb-0" style="font-size:0.85rem; color:#295738;"><i class="bi bi-cpu-fill text-success me-2"></i>Driver Insights</h6>

<button id="backToRanking" 
class="btn btn-sm d-inline-flex align-items-center gap-1"
style="border:1px solid #dee4ba;border-radius:10px;font-size:0.75rem;">
<i class="bi bi-arrow-left"></i> Back
</button>

</div>

<div id="aiContent"></div>

<div id="aiLoadingState" style="display:none;padding:14px 0;">
<div class="d-flex align-items-center gap-2 mb-2">
<div class="dp-loader"></div>
<div style="font-size:0.85rem;font-weight:600;color:#295738;">Analyzing driver performance...</div>
</div>
<div class="sk sk-ai mb-2"></div>
<div class="sk sk-ai"></div>
</div>

</div>

</div>
</div>

<div id="driverInsightsSection" style="display:none;margin-top:28px;">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="gap:10px;">
<div>
<h5 class="fw-bold mb-1" style="color:#295738; font-size:1.02rem;">
<i class="bi bi-clipboard-data-fill text-success me-2"></i>Driver Insights
</h5>
<small style="color:#94a3b8; font-size:0.78rem;"><span id="diRangeLabel"></span></small>
</div>
<div class="d-flex align-items-center gap-2 flex-wrap">
<div class="d-inline-flex align-items-center gap-1" style="background:#f9f4da;border:1px solid #cdd9b5;padding:5px 14px;border-radius:20px;font-size:0.7rem;font-weight:600;color:#295738;">
<i class="bi bi-activity" style="font-size:0.75rem;"></i> <span id="diFleetStatus">Loading Insights...</span>
</div>
<button type="button" id="diClearDriver" style="display:none;border:1px solid #cdd9b5;border-radius:20px;background:#fff;color:#295738;font-size:0.7rem;font-weight:600;padding:5px 12px;">
<i class="bi bi-x-lg" style="font-size:0.65rem;"></i> Clear driver
</button>
</div>
</div>

<div id="diEmpty" style="display:none;background:#fff;border:1px dashed #cdd9b5;border-radius:14px;padding:26px 16px;text-align:center;color:#a3ae9f;font-size:0.8rem;font-weight:500;">
No driver activity for the selected period.
</div>

<div id="diFleetKpis" class="row g-3 mb-3"></div>

<div class="row g-4">
<div class="col-12 col-lg-7" id="diTrendWrap">
<div class="card p-3 shadow-sm chart-fixed-height"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:360px;">
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap:8px;">
<h6 class="fw-semibold mb-0" style="font-size:0.85rem; color:#2f4f2f;"><i class="bi bi-graph-up-arrow text-success me-2"></i>Fleet Performance Trend</h6>
<div class="d-inline-flex" style="background:#fff;border:1px solid #cdd9b5;border-radius:20px;padding:2px;">
<button type="button" class="di-gran-btn di-gran-active" data-gran="day">Daily</button>
<button type="button" class="di-gran-btn" data-gran="week">Weekly</button>
<button type="button" class="di-gran-btn" data-gran="month">Monthly</button>
</div>
</div>
<div style="position:relative;height:290px;">
<canvas id="fleetTrendChart"></canvas>
</div>
</div>
</div>

<div class="col-12 col-lg-5" id="diTableWrap">
<div class="card p-3 shadow-sm"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:360px;overflow:auto;">
<h6 class="fw-semibold mb-2" style="font-size:0.85rem; color:#2f4f2f;"><i class="bi bi-list-ol text-success me-2"></i>Driver Comparison</h6>
<div id="diDriverTable"></div>
</div>
</div>
</div>

</div>

<div id="driverMetricsSection" style="display:none;margin-top:32px;">

<div class="row g-4 mb-3" id="driverInsightPanelsRow" style="display:none;">

<div class="col-md-6 col-xl-4">
<div class="card p-3 shadow-sm"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:280px;">
<h6 class="fw-semibold mb-1" style="font-size:0.8rem; color:#2f4f2f;"><i class="bi bi-check2-circle text-success me-2"></i>Trip Completion</h6>
<div style="position:relative;height:150px;">
<canvas id="diDriverTripDonut"></canvas>
</div>
<div id="diDriverTripNote" class="text-center" style="font-size:0.72rem;color:#777;"></div>
</div>
</div>

<div class="col-md-6 col-xl-4">
<div class="card p-3 shadow-sm"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:280px;">
<h6 class="fw-semibold mb-1" style="font-size:0.8rem; color:#2f4f2f;"><i class="bi bi-calendar2-check text-success me-2"></i>Attendance</h6>
<div style="position:relative;height:180px;">
<canvas id="diDriverAttChart"></canvas>
</div>
</div>
</div>

<div class="col-md-6 col-xl-4">
<div class="card p-3 shadow-sm"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:280px;">
<h6 class="fw-semibold mb-1" style="font-size:0.8rem; color:#2f4f2f;"><i class="bi bi-cash-coin text-success me-2"></i>Operating Costs</h6>
<div style="position:relative;height:180px;">
<canvas id="diDriverCostChart"></canvas>
</div>
</div>
</div>

<div class="row g-4">

<div class="col-12">
<div class="card shadow-sm chart-fixed-height"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:400px;padding:12px 16px;">
<h6 class="fw-semibold mb-1" style="font-size:0.85rem; color:#2f4f2f;"><i class="bi bi-people-fill text-success me-2"></i>Passenger & Other Distribution</h6>
<div style="position:relative;height:340px;">
<canvas id="passengerChartType"></canvas>
</div>
</div>
</div>

</div>
</div>
</div>
<script>
let driverStats = [];

let bubbleChart = null;
let tripsChart = null;
let passengerChartType = null;
let passengerChartSpecial = null;

let tripCompletionChart = null;
let attendanceChart = null;
let fuelChart = null;
let costChart = null;
let fleetTrendChart = null;

let driverInsightsData = null;
let currentInsightDriver = null;
let diGranularity = "day";
let diDiGranularity = "day";
let diLoadSeq = 0;
let driverInsightActive = null;

let selectedDrivers = [];
let rankingLimit = 5;
let lastDriverDetailId = null;

const imageCache = {};

let analyticsLoading = false;
let rankingLoading = false;
let aiLoading = false;
let analyticsError = null;
let aiError = null;
let analyticsReady = false;
let aiReady = false;

function safeNumber(v) {
    const n = Number(v);

    return isNaN(n) ? 0 : n;
}

function calculatePerformance(driver) {
    return safeNumber(driver.performance);
}

const bubbleImagePlugin = {
    id: "bubbleImage",

    afterDatasetsDraw(chart) {
        const ctx = chart.ctx;

        chart.data.datasets.forEach((dataset, datasetIndex) => {
            const meta = chart.getDatasetMeta(datasetIndex);

            meta.data.forEach((point) => {
                const d = dataset.driver;

                if (!d) return;

                if (!d.photo) {
                    const radius = point.options.radius || 10;

                    const size = Math.max(14, radius * 1.35);
                    ctx.save();
                    ctx.beginPath();
                    ctx.arc(point.x, point.y, size / 2, 0, Math.PI * 2);
                    ctx.fillStyle = "#295738";
                    ctx.fill();
                    ctx.fillStyle = "#fff";
                    ctx.font = "bold " + size * 0.5 + "px Poppins, sans-serif";
                    ctx.textAlign = "center";
                    ctx.textBaseline = "middle";
                    ctx.fillText("I", point.x, point.y);
                    ctx.restore();
                    return;
                }

                let img = imageCache[d.photo];

                if (!img) {
                    img = new Image();
                    img.onerror = function () {
                        this._broken = true;
                    };

                    img.src = d.photo;

                    imageCache[d.photo] = img;
                }

                if (!img.complete || img._broken) return;

                const radius = point.options.radius || 10;

                const size = Math.max(14, radius * 1.35);

                ctx.save();

                ctx.globalAlpha = 0.6;

                ctx.beginPath();

                ctx.arc(point.x, point.y, size / 2, 0, Math.PI * 2);

                ctx.closePath();

                ctx.clip();

                ctx.drawImage(img, point.x - size / 2, point.y - size / 2, size, size);

                ctx.restore();

                ctx.beginPath();

                ctx.arc(point.x, point.y, size / 2, 0, Math.PI * 2);

                ctx.strokeStyle = "rgba(255,255,255,0.85)";

                ctx.lineWidth = 1.5;

                ctx.stroke();
            });
        });
    },
};

function initBubbleChart() {
    const canvas = document.getElementById("performanceChart");

    if (!canvas) return;

    if (bubbleChart) {
        bubbleChart.destroy();
    }

    bubbleChart = new Chart(canvas, {
        type: "bubble",

        data: {
            datasets: [],
        },

        plugins: [bubbleImagePlugin],

        options: {
            responsive: true,

            maintainAspectRatio: false,

            layout: {
                padding: {
                    top: 18,
                    right: 18,
                    bottom: 18,
                    left: 14,
                },
            },

            animation: {
                duration: 700,
            },

            events: ["mousemove", "mouseout", "click", "touchstart", "touchmove"],

            interaction: {
                mode: "nearest",
                intersect: false,
                axis: "xy",
            },

            elements: {
                point: {
                    hitRadius: 20,

                    hoverRadius: 20,

                    clip: false,

                    radius: (ctx) => {
                        const raw = ctx.raw || {};

                        return Math.max(10, Math.min(raw.r || 8, 26));
                    },
                },
            },

            plugins: {
                legend: {
                    display: false,
                },

                tooltip: {
                    enabled: true,

                    backgroundColor: "#ffffff",

                    titleColor: "#2f4f2f",

                    bodyColor: "#555",

                    borderColor: "#dee4ba",

                    borderWidth: 1,

                    padding: 12,

                    cornerRadius: 10,

                    callbacks: {
                        label: (ctx) => {
                            const d = ctx.dataset.driver;

                            return [
                                `Driver: ${d.name}`,

                                `Trips: ${safeNumber(d.trips)}`,

                                `Passengers: ${safeNumber(d.passengers)}`,

                                `Revenue/Trip: ₱${safeNumber(d.performance).toFixed(2)}`,

                                `Income: ₱${safeNumber(d.income).toFixed(2)}`,

                                `Archived Trips: ${safeNumber(d.archived_trips)}`,
                            ];
                        },
                    },
                },
            },

            scales: {
                x: {
                    grace: "15%",
                    title: {
                        display: true,
                        text: "Trips",
                    },
                    grid: {
                        color: "rgba(0,0,0,0.05)",
                    },
                    ticks: {
                        precision: 0,
                    },
                },

                y: {
                    grace: "15%",
                    title: {
                        display: true,
                        text: "Revenue Per Trip",
                    },
                    grid: {
                        color: "rgba(0,0,0,0.05)",
                    },
                    ticks: {
                        callback: (v) => `₱${safeNumber(v).toFixed(0)}`,
                    },
                },
            },

            onClick: (evt) => {
                if (!bubbleChart) return;

                const points = bubbleChart.getElementsAtEventForMode(
                    evt,
                    "nearest",
                    { intersect: true },
                    true,
                );

                if (!points.length) return;

                const clicked = bubbleChart.data.datasets[points[0].datasetIndex].driver;

                if (!clicked) return;

                openDriverMetrics(clicked, true);
            },
        },
    });
}

function showAnalyticsError() {
    analyticsLoading = false;
    analyticsReady = false;
    analyticsError = "The analytics could not be loaded right now. Please try again.";

    var el = document.getElementById("driverPerformanceLoading");
    if (!el) return;

    el.innerHTML = `
<div class="col-md-7">
<div class="card p-3 shadow-sm"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:420px;">
<div class="d-flex align-items-center gap-2 mb-2">
<h6 class="fw-semibold mb-0" style="font-size:0.85rem; color:#2f4f2f;"><i class="bi bi-bar-chart-fill text-success me-2"></i>Trips vs Earnings</h6>
</div>
<div class="d-flex flex-column align-items-center justify-content-center" style="height:330px;">
<div style="font-size:40px;margin-bottom:12px;">⚠️</div>
<div class="dp-loading-sub" style="font-size:0.85rem;color:#2f4f2f;text-align:center;max-width:80%;">${analyticsError}</div>
<button type="button" class="btn btn-sm mt-3"
style="border:1px solid #198754;border-radius:8px;font-size:0.75rem;color:#198754;"
onclick="loadDriverStats('')">Retry</button>
</div>
</div>
</div>

<div class="col-md-5">
<div class="card p-3 shadow-sm"
style="background:#f9f4da;border:1px solid #cdd9b5;border-radius:12px;height:420px;">
<div class="d-flex justify-content-between align-items-center mb-3">
<h6 class="fw-semibold mb-0" style="font-size:0.85rem; color:#2f4f2f;"><i class="bi bi-list-ol text-success me-2"></i>Driver Ranking</h6>
</div>
<div class="d-flex flex-column align-items-center justify-content-center text-muted" style="height:330px;">
<i class="bi bi-exclamation-circle text-muted" style="font-size:32px;margin-bottom:10px;"></i>
<span style="font-size:0.8rem;">No ranking data available</span>
</div>
</div>
</div>
`;
}

function showAnalyticsReady() {
    analyticsLoading = false;
    analyticsReady = true;
    analyticsError = null;

    var loading = document.getElementById("driverPerformanceLoading");
    if (loading) loading.style.display = "none";

    var body = document.getElementById("driverPerformanceBody");
    if (body) body.style.display = "";

    if (bubbleChart) {
        setTimeout(() => {
            bubbleChart.resize();
        }, 60);
    }
}

function loadDriverStats(filterQuery) {
    window.__dpsReq = (window.__dpsReq || 0) + 1;
    var req = window.__dpsReq;

    var url = "/admin/api/dashboard/driver-performance.php";
    if (filterQuery) url += "?" + filterQuery;

    analyticsLoading = true;
    rankingLoading = true;
    analyticsReady = false;
    analyticsError = null;

    var loading = document.getElementById("driverPerformanceLoading");
    if (loading) loading.style.display = "";
    var body = document.getElementById("driverPerformanceBody");
    if (body) body.style.display = "none";

    if (window.__dashLoadBegin) window.__dashLoadBegin();
    fetch(url, { cache: "no-store" })
        .then(async (r) => {
            const text = await r.text();

            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("Invalid JSON:", text);

                return {
                    success: false,
                };
            }
        })

        .then((res) => {
            if (req !== window.__dpsReq) return;

            if (!res.success) {
                console.error(res.error || res.message);

                showAnalyticsError();

                return;
            }

            const rows = Array.isArray(res.data) ? res.data : [];

            window.__setAiPageData && window.__setAiPageData("driver_performance", rows);

            driverStats = rows
                .map((d) => ({
                    ...d,

                    id: safeNumber(d.id),

                    driver_id: String(d.driver_id || ""),

                    name: String(d.name || d.full_name || "Unknown Driver"),

                    photo: d.photo && d.photo !== "null" ? d.photo : "",

                    income: safeNumber(d.income),

                    passengers: safeNumber(d.passengers),

                    trips: safeNumber(d.trips),

                    performance: safeNumber(d.performance),

                    cash: safeNumber(d.cash_payments),

                    gcash: safeNumber(d.gcash_payments),

                    hybrid: safeNumber(d.hybrid_payments),

                    net_income: safeNumber(d.net_income),
                }))

                .filter((d) => d.driver_id);

            driverStats.sort((a, b) => {
                if (b.income !== a.income) {
                    return b.income - a.income;
                }

                if (b.performance !== a.performance) {
                    return b.performance - a.performance;
                }

                return b.trips - a.trips;
            });

            selectedDrivers = [];

            renderDriverList();

            updateBubbleChart(driverStats);

            rankingLoading = false;
            showAnalyticsReady();

            /* Keep an open driver detail on the newly selected period. */
            if (lastDriverDetailId) {
                const stillOpen = driverStats.find(
                    (d) => String(d.driver_id) === String(lastDriverDetailId),
                );
                if (stillOpen) {
                    openDriverMetrics(stillOpen, false);
                }
            }
        })

        .catch((err) => {
            if (req !== window.__dpsReq) return;

            console.error("loadDriverStats failed:", err);

            showAnalyticsError();
        })
        .finally(() => {
            if (window.__dashLoadEnd) window.__dashLoadEnd();
        });
}

/* Load the clicked driver's detail analytics for the SAME selected period as
   the dashboard. Reused by the bubble chart, the ranking list, and the refresh
   that runs whenever the date filter changes. */
function openDriverMetrics(driver, scroll) {
    if (!driver) return;
    lastDriverDetailId = String(driver.driver_id || "");
    if (!lastDriverDetailId) return;

    const base =
        driverStats.find((x) => String(x.driver_id) === lastDriverDetailId) || {};
    const mergedBase = { ...base, ...driver };

    const aiContent = document.getElementById("aiContent");
    if (aiContent) {
        aiContent.innerHTML = `
<div class="text-center py-4 text-muted">
Loading driver analytics...
</div>
`;
    }

    let url =
        "/admin/api/dashboard/driver-metrics.php?driver_id=" +
        encodeURIComponent(lastDriverDetailId);
    if (window.__financeFilterQuery) {
        url += "&" + window.__financeFilterQuery;
    }

    fetch(url)
        .then(async (r) => {
            const text = await r.text();

            try {
                return JSON.parse(text);
            } catch (e) {
                console.error(text);

                return { success: false };
            }
        })

        .then((d) => {
            if (!d.success) {
                selectDriverInsight(mergedBase);
                showAIAnalytics(mergedBase, true);

                return;
            }

            const merged = { ...mergedBase, ...d };

            showDriverMetrics(merged);
            selectDriverInsight(merged);
            showAIAnalytics(merged);

            if (scroll) {
                document.getElementById("driverMetricsSection")?.scrollIntoView({
                    behavior: "smooth",
                    block: "start",
                });
            }
        })

        .catch((err) => {
            console.error("Driver metrics failed:", err);

            selectDriverInsight(mergedBase);
            showAIAnalytics(mergedBase, true);
        });
}

function updateBubbleChart(drivers) {
    if (!bubbleChart) return;

    bubbleChart.data.datasets = drivers.map((d) => ({
        label: d.name,

        driver: d,

        data: [
            {
                x: safeNumber(d.trips),

                y: safeNumber(d.performance),

                r: Math.max(10, Math.min(Math.sqrt(safeNumber(d.passengers)) * 2, 26)),
            },
        ],

        backgroundColor: "rgba(25,135,84,0.25)",

        borderColor: "#198754",

        borderWidth: 1.5,
    }));

    bubbleChart.update();

    const hasBubbleData = drivers.some(
        (d) => safeNumber(d.trips) > 0 || safeNumber(d.performance) > 0,
    );
    if (hasBubbleData) {
        if (window.__hideChartNoData) window.__hideChartNoData("performanceChart");
    } else {
        bubbleChart.data.datasets = [];
        bubbleChart.update();
        if (window.__showChartNoData)
            window.__showChartNoData(
                "performanceChart",
                "No driver activity for the selected period.",
            );
    }
}

function renderDriverList() {
    const el = document.getElementById("driverList");

    if (!el) return;

    el.innerHTML = "";

    const hasActivity = driverStats.some(
        (d) => safeNumber(d.trips) > 0 || safeNumber(d.income) > 0,
    );

    if (driverStats.length === 0 || !hasActivity) {
        el.innerHTML = `
    <div style="text-align:center;padding:22px 14px;color:#a3ae9f;font-size:0.78rem;font-weight:500;">No driver activity for the selected period.</div>`;
        return;
    }

    driverStats.slice(0, rankingLimit).forEach((d, i) => {
        const isTop = i === 0;

        el.innerHTML += `

<div class="driver-row"

style="
background:#ffffff;
border:1px solid #cdd9b5;
border-radius:10px;
padding:10px 12px;
display:flex;
justify-content:space-between;
align-items:center;
gap:12px;
cursor:pointer;
box-shadow:${isTop ? "0 2px 8px rgba(0,0,0,0.06)" : "none"};
position:relative;
"

data-index="${i}"

>

${
    isTop
        ? `
<div style="
position:absolute;
top:0;
left:0;
height:100%;
width:4px;
background:#dee4ba;
border-top-left-radius:10px;
border-bottom-left-radius:10px;
"></div>
`
        : ""
}

<div style="
display:flex;
align-items:center;
gap:10px;
">

<img src="${d.photo || "https://via.placeholder.com/40"}"

style="
width:38px;
height:38px;
border-radius:50%;
object-fit:cover;
border:1px solid #cdd9b5;
">

<div>

<div style="
font-weight:600;
color:#2f4f2f;
font-size:14px;
">
${i + 1}. ${d.name}
</div>

<div style="
font-size:12px;
color:#777;
">
₱${safeNumber(d.performance).toFixed(2)} per trip
</div>

</div>

</div>

<div style="
display:flex;
align-items:center;
gap:10px;
">

<span style="
font-size:13px;
font-weight:500;
color:#2f4f2f;
">
${safeNumber(d.trips)} trips
</span>

<input
type="checkbox"
class="driverCheck"
value="${d.driver_id}"
onclick="event.stopPropagation();"
>

</div>

</div>

`;
    });

    document.querySelectorAll(".driver-row").forEach((row) => {
        row.onclick = () => {
            const index = parseInt(row.dataset.index, 10);

            const driver = driverStats[index];

            if (!driver) return;

            document.getElementById("rankingPanel").style.display = "none";

            document.getElementById("aiPanel").style.display = "block";

            openDriverMetrics(driver, false);
        };
    });

    document.querySelectorAll(".driverCheck").forEach((cb) => {
        cb.onchange = () => {
            const checked = [...document.querySelectorAll(".driverCheck:checked")];

            if (checked.length > 5) {
                cb.checked = false;

                return;
            }

            selectedDrivers = checked
                .map((c) => driverStats.find((d) => String(d.driver_id) === String(c.value)))
                .filter(Boolean);

            if (selectedDrivers.length >= 2) {
                updateBubbleChart(selectedDrivers);
            } else {
                updateBubbleChart(driverStats);
            }
        };
    });
}

function showDriverMetrics(driver) {
    var section = document.getElementById("driverMetricsSection");
    if (!section) return;
    section.style.display = "block";

    if (passengerChartType) {
        passengerChartType.destroy();
        passengerChartType = null;
    }

    requestAnimationFrame(function () {
        try {
            renderTripsChart(driver);
        } catch (e) {
            console.error("Trips chart error:", e);
        }
        try {
            renderPassengerCharts(driver);
        } catch (e) {
            console.error("Passenger chart error:", e);
        }
        try {
            renderDriverPanels(driver);
        } catch (e) {
            console.error("Driver insight panels error:", e);
        }
    });
}

function renderTripsChart(driver) {
    const canvas = document.getElementById("tripsChart");

    if (!canvas) return;

    if (tripsChart) {
        tripsChart.destroy();
        tripsChart = null;
    }

var labels = Array.isArray(driver.daily_labels) ? driver.daily_labels : [];

    var trips = Array.isArray(driver.daily_trips)
        ? driver.daily_trips.map(function (v) {
              return Math.round(Number(v) || 0);
          })
        : [];

    var earnings = Array.isArray(driver.daily_earnings)
        ? driver.daily_earnings.map(function (v) {
              return Math.round(Number(v) || 0);
          })
        : [];

    if (
        !labels.length &&
        Array.isArray(driver.daily) &&
        driver.daily.length
    ) {
        labels = driver.daily.map(function (d) {
            return String(d.day || "").slice(0, 10);
        });
        trips = driver.daily.map(function (d) {
            return Math.round(Number(d.trips) || 0);
        });
        earnings = driver.daily.map(function (d) {
            return Math.round(Number(d.fare) || 0);
        });
    }

    if (labels.length && diDiGranularity !== "day") {
        var bucketed = diBucketSeries(labels, trips, earnings, diDiGranularity);
        labels = bucketed.labels;
        trips = bucketed.trips;
        earnings = bucketed.fare;
    }

    try {
        tripsChart = new Chart(canvas, {
            type: "line",

            data: {
                labels: labels.length ? labels : ["No Data"],

                datasets: labels.length
                    ? [
                          {
                              label: "Trips",
                              data: trips,
                              tension: 0.45,
                              borderWidth: 3,
                              pointRadius: 0,
                              borderColor: "#2f4f2f",
                              backgroundColor: "rgba(222,228,186,0.45)",
                              fill: true,
                          },

                          {
                              label: "Earnings",
                              data: earnings,
                              tension: 0.45,
                              borderWidth: 3,
                              pointRadius: 0,
                              borderColor: "#6b7d3a",
                              fill: false,
                              yAxisID: "y1",
                          },
                      ]
                    : [],
            },

            options: {
                responsive: true,

                maintainAspectRatio: false,

                layout: {
                    padding: {
                        top: 10,
                        bottom: 10,
                        left: 10,
                        right: 10,
                    },
                },

                interaction: {
                    mode: "index",
                    intersect: false,
                },

                plugins: {
                    legend: {
                        display: false,
                    },

                    tooltip: {
                        mode: "nearest",
                        intersect: false,
                    },
                },

                scales: labels.length
                    ? {
                          y: {
                              beginAtZero: true,
                              ticks: {
                                  padding: 8,
                                  font: { size: 11 },
                              },
                          },

                          y1: {
                              position: "right",
                              beginAtZero: true,
                              grid: {
                                  drawOnChartArea: false,
                              },
                              ticks: {
                                  padding: 8,
                                  font: { size: 11 },
                              },
                          },

                          x: {
                              ticks: {
                                  padding: 8,
                                  font: { size: 11 },
                              },
                          },
                      }
                    : {},
            },
        });
    } catch (e) {
        console.error("Trips chart error:", e);
        tripsChart = null;
    }

    if (!labels.length && window.__showChartNoData) {
        window.__showChartNoData("tripsChart", "No data available for this period.");
    } else if (labels.length && window.__hideChartNoData) {
        window.__hideChartNoData("tripsChart");
    }
}

function renderPassengerCharts(driver) {
    const canvas = document.getElementById("passengerChartType");

    if (!canvas) return;

    if (passengerChartType) {
        passengerChartType.destroy();
    }

    let level = "payment";

    function getData() {
        if (level === "payment") {
            return {
                title: "Payment Methods",

                labels: ["Cash", "GCash", "Hybrid"],

                data: [
                    safeNumber(driver.cash),

                    safeNumber(driver.gcash),

                    safeNumber(driver.hybrid),
                ],

                colors: ["#dee4ba", "#dee4ba", "#dee4ba"],
            };
        }

        if (level === "type") {
            return {
                title: "Passenger & Other Distribution",

                labels: ["Passenger", "Other (Baggage/Luggage/etc.)"],

                data: [safeNumber(driver.passenger_count), safeNumber(driver.other_count)],

                colors: ["#c5d9b2", "#d9b2b2"],
            };
        }

        if (level === "discount") {
            return {
                title: "Discount Type",

                labels: ["Student", "Senior", "PWD", "Nurse", "Teacher"],

                data: [
                    safeNumber(driver.students),

                    safeNumber(driver.seniors),

                    safeNumber(driver.pwd),

                    safeNumber(driver.nurses),

                    safeNumber(driver.teachers),
                ],

                colors: ["#c5d9b2", "#b2c9a8", "#d9ceb2", "#cbb2d9", "#b2c2d9"],
            };
        }
    }

    function render() {
        const d = getData();

        const hasPassengerTypeData = (d.data || []).some((v) => Number(v) > 0);

        if (passengerChartType) {
            passengerChartType.data.labels = d.labels;
            passengerChartType.data.datasets[0].data = d.data;
            passengerChartType.data.datasets[0].backgroundColor = d.colors;
            passengerChartType.data.datasets[0].borderColor = d.colors;
            passengerChartType.options.plugins.title.text = d.title;
            passengerChartType.update();
            if (hasPassengerTypeData) {
                if (window.__hideChartNoData) window.__hideChartNoData("passengerChartType");
            } else {
                if (window.__showChartNoData)
                    window.__showChartNoData(
                        "passengerChartType",
                        "No data available for this period.",
                    );
            }
            return;
        }

        passengerChartType = new Chart(canvas, {
            type: "bar",

            data: {
                labels: d.labels,

                datasets: [
                    {
                        data: d.data,

                        backgroundColor: d.colors,

                        borderColor: d.colors,

                        borderWidth: 1.5,

                        borderRadius: 8,
                    },
                ],
            },

            options: {
                responsive: true,

                maintainAspectRatio: false,

                layout: {
                    padding: {
                        top: 10,
                        bottom: 5,
                        left: 5,
                        right: 5,
                    },
                },

                plugins: {
                    legend: {
                        display: false,
                    },

                    title: {
                        display: true,
                        text: d.title,
                        color: "#2f4f2f",
                        font: { size: 12 },
                        padding: { bottom: 8 },
                    },
                },

                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            padding: 6,
                            font: { size: 11 },
                        },
                    },
                    x: {
                        ticks: {
                            padding: 6,
                            font: { size: 10 },
                        },
                    },
                },

                onClick: (evt) => {
                    const chart = passengerChartType;

                    const points = chart.getElementsAtEventForMode(
                        evt,
                        "nearest",
                        { intersect: true },
                        true,
                    );

                    if (!points.length) return;

                    const idx = points[0].index;

                    if (level === "payment") {
                        level = "type";
                        render();
                        return;
                    }

                    if (level === "type") {
                        const label = chart.data.labels[idx];
                        if (label === "Other (Baggage/Luggage/etc.)") {
                            level = "payment";
                            render();
                            return;
                        }
                        level = "discount";
                        render();
                        return;
                    }

                    level = "payment";
                    render();
                },
            },
        });

        if (hasPassengerTypeData) {
            if (window.__hideChartNoData) window.__hideChartNoData("passengerChartType");
        } else {
            if (window.__showChartNoData)
                window.__showChartNoData(
                    "passengerChartType",
                    "No data available for this period.",
                );
        }
    }

    render();
}

function clampSentences(text) {
    const trimmed = text.replace(/\s+/g, " ").trim();

    const sentences = trimmed.match(/[^.!?]+[.!?]+/g) || [];

    if (sentences.length <= 2) return trimmed;

    return sentences.slice(0, 2).join(" ");
}

const insightCardsHTML = `
<div class="ai-insight-card ai-insight-card-green">

<div class="ai-insight-head">
<i class="bi bi-lightbulb"></i> Summary
</div>

<div
class="ai-insight-text"
data-section="summary"
></div>

</div>

<div class="ai-insight-card ai-insight-card-cream">

<div class="ai-insight-head">
<i class="bi bi-graph-up-arrow"></i> Strengths
</div>

<div
class="ai-insight-text"
data-section="strengths"
></div>

</div>

<div class="ai-insight-card ai-insight-card-cream">

<div class="ai-insight-head">
<i class="bi bi-exclamation-triangle"></i> Watch Outs
</div>

<div
class="ai-insight-text"
data-section="watch_outs"
></div>

</div>

<div class="ai-insight-card ai-insight-card-green">

<div class="ai-insight-head">
<i class="bi bi-check-circle"></i> Recommendation
</div>

<div
class="ai-insight-text"
data-section="recommendation"
></div>

</div>
`;

function renderInsightCards(analysis) {
    const grid = document.getElementById("aiInsightGrid");

    if (!grid) return;

    grid.innerHTML = insightCardsHTML;

    grid.querySelectorAll(".ai-insight-text").forEach((el) => {
        const section = el.getAttribute("data-section");

        const raw = ((analysis || {})[section] || "").trim();

        el.textContent = raw ? clampSentences(raw) : "No insight available";
    });
}

function showAIAnalytics(driver, noData) {
    aiLoading = true;
    aiReady = false;
    aiError = null;

    var aiState = document.getElementById("aiLoadingState");
    if (aiState) aiState.style.display = "block";

    document.getElementById("rankingPanel").style.display = "none";

    document.getElementById("aiPanel").style.display = "block";

    if (noData) {
        aiLoading = false;
        aiReady = false;
        var aiState = document.getElementById("aiLoadingState");
        if (aiState) aiState.style.display = "none";

        document.getElementById("aiContent").innerHTML = `
<div class="text-center py-5">
<div style="font-size:48px;margin-bottom:16px;">📭</div>
<h6 style="color:#2f4f2f;font-weight:600;">No Data Available</h6>
<p class="text-muted small">This driver has no trip records yet.</p>
</div>
`;

        return;
    }

    const name = driver.name || "Driver";

    const photo = driver.photo || "https://via.placeholder.com/70";

    const perf = safeNumber(driver.performance);

    const trips = safeNumber(driver.trips);

    const passengers = safeNumber(driver.passengers);

    const income = safeNumber(driver.income);

    const cash = safeNumber(driver.cash);

    const gcash = safeNumber(driver.gcash);

    const hybrid = safeNumber(driver.hybrid);

    let performanceLevel = "Low";

    if (perf >= 500) {
        performanceLevel = "Excellent";
    } else if (perf >= 250) {
        performanceLevel = "Good";
    } else if (perf >= 100) {
        performanceLevel = "Average";
    }

    document.getElementById("aiContent").innerHTML = `

<style>

.analytics-wrapper{
background:#fff;
border:1px solid #e3ddc4;
border-radius:18px;
padding:16px;
}

.analytics-top{
display:flex;
align-items:center;
justify-content:space-between;
gap:12px;
margin-bottom:16px;
flex-wrap:wrap;
}

.analytics-profile{
display:flex;
align-items:center;
gap:12px;
}

.analytics-avatar{
width:58px;
height:58px;
border-radius:50%;
object-fit:cover;
border:2px solid #dee4ba;
}

.analytics-name{
font-size:18px;
font-weight:700;
color:#2f4f2f;
line-height:1.1;
}

.analytics-sub{
font-size:12px;
color:#777;
margin-top:2px;
}

.analytics-status{
padding:8px 14px;
border-radius:12px;
background:#f7f7ef;
border:1px solid #e3ddc4;
font-size:13px;
font-weight:600;
color:#2f4f2f;
}

.analytics-grid{
display:grid;
grid-template-columns:repeat(3,1fr);
gap:10px;
margin-bottom:16px;
}

.analytics-card{
background:#fafaf5;
border:1px solid #e3ddc4;
border-radius:14px;
padding:12px;
}

.analytics-label{
font-size:11px;
font-weight:600;
color:#777;
text-transform:uppercase;
margin-bottom:5px;
}

.analytics-value{
font-size:22px;
font-weight:700;
color:#2f4f2f;
line-height:1;
}

.analytics-mini{
font-size:11px;
color:#888;
margin-top:4px;
}

.analytics-bars{
display:flex;
flex-direction:column;
gap:8px;
margin-bottom:16px;
}

.analytics-bar-item{
background:#fafaf5;
border:1px solid #e3ddc4;
border-radius:12px;
padding:10px;
}

.analytics-bar-top{
display:flex;
justify-content:space-between;
font-size:12px;
font-weight:600;
margin-bottom:6px;
color:#555;
}

.analytics-bar-track{
height:7px;
background:#e3ddc4;
border-radius:20px;
overflow:hidden;
}

.analytics-bar-fill{
height:100%;
border-radius:20px;
background:#7da453;
}

.analytics-ai-box{
background:#fafaf5;
border:1px solid #e3ddc4;
border-radius:16px;
padding:14px;
}

.analytics-ai-title{
display:flex;
align-items:center;
gap:8px;
font-size:15px;
font-weight:700;
color:#2f4f2f;
margin-bottom:10px;
}

.ai-insight-grid{
display:grid;
grid-template-columns:repeat(2,1fr);
gap:10px;
}

.ai-insight-card{
border-radius:12px;
padding:12px;
border:1px solid #dde5c8;
}

.ai-insight-card-green{
background:#f0f9f1;
}

.ai-insight-card-cream{
background:#f9f4da;
border-color:#efe4c0;
}

.ai-insight-head{
display:flex;
align-items:center;
gap:6px;
font-size:13px;
font-weight:700;
color:#2f4f2f;
margin-bottom:6px;
}

.ai-insight-head i{
font-size:14px;
color:#7da453;
}

.ai-insight-card-cream .ai-insight-head{
color:#6b5d1e;
}

.ai-insight-card-cream .ai-insight-head i{
color:#b89a3d;
}

.ai-insight-text{
font-size:13px;
line-height:1.55;
color:#444;
overflow-wrap:break-word;
}

.ai-insight-error{
grid-column:1/-1;
color:#dc3545;
font-weight:600;
font-size:14px;
}

.ai-loading-row{
grid-column:1/-1;
}

.ai-loading{
display:flex;
align-items:center;
gap:10px;
font-size:14px;
font-weight:500;
color:#6b7d3a;
}

.ai-loader{
width:16px;
height:16px;
border-radius:50%;
border:2px solid #dce8b2;
border-top:2px solid #7da453;
animation:spinAI 0.8s linear infinite;
}

@keyframes spinAI{
0%{
transform:rotate(0deg);
}
100%{
transform:rotate(360deg);
}
}

@media(max-width:768px){

.analytics-grid{
grid-template-columns:repeat(2,1fr);
}

}

@media(max-width:480px){

.ai-insight-grid{
grid-template-columns:1fr;
}

}

</style>

<div class="analytics-wrapper">

<div class="analytics-top">

<div class="analytics-profile">

<img
src="${photo}"
class="analytics-avatar"
>

<div>

<div class="analytics-name">
${name}
</div>

<div class="analytics-sub">
Driver Performance Overview
</div>

</div>

</div>

<div class="analytics-status">
${performanceLevel}
</div>

</div>

<div class="analytics-grid">

<div class="analytics-card">

<div class="analytics-label">
Revenue / Trip
</div>

<div class="analytics-value">
₱${perf.toFixed(2)}
</div>

<div class="analytics-mini">
Efficiency
</div>

</div>

<div class="analytics-card">

<div class="analytics-label">
Total Revenue
</div>

<div class="analytics-value">
₱${income.toLocaleString()}
</div>

<div class="analytics-mini">
Gross Income
</div>

</div>

<div class="analytics-card">

<div class="analytics-label">
Trips
</div>

<div class="analytics-value">
${trips}
</div>

<div class="analytics-mini">
Completed
</div>

</div>

<div class="analytics-card">

<div class="analytics-label">
Passengers
</div>

<div class="analytics-value">
${passengers}
</div>

<div class="analytics-mini">
Served
</div>

</div>



</div>

<div class="analytics-bars">

<div class="analytics-bar-item">

<div class="analytics-bar-top">
<span>Cash</span>
<span>${cash}</span>
</div>

<div class="analytics-bar-track">
<div
class="analytics-bar-fill"
style="width:${Math.min(100, cash * 5)}%;"
></div>
</div>

</div>

<div class="analytics-bar-item">

<div class="analytics-bar-top">
<span>GCash</span>
<span>${gcash}</span>
</div>

<div class="analytics-bar-track">
<div
class="analytics-bar-fill"
style="width:${Math.min(100, gcash * 5)}%;"
></div>
</div>

</div>

<div class="analytics-bar-item">

<div class="analytics-bar-top">
<span>Hybrid</span>
<span>${hybrid}</span>
</div>

<div class="analytics-bar-track">
<div
class="analytics-bar-fill"
style="width:${Math.min(100, hybrid * 5)}%;"
></div>
</div>

</div>

</div>

<div class="analytics-ai-box">

<div class="analytics-ai-title">
<i class="bi bi-cpu-fill text-success me-1"></i> AI Operational Analysis
</div>

<div
class="ai-insight-grid"
id="aiInsightGrid"
>

</div>

</div>

</div>

`;

    fetch("/admin/api/dashboard/ai-driver-analysis.php", {
        method: "POST",

        headers: {
            "Content-Type": "application/json",
        },

        body: JSON.stringify({
            name: name,

            performance: perf,

            income: income,

            trips: trips,

            passengers: passengers,

            cash: cash,

            gcash: gcash,

            hybrid: hybrid,
        }),
    })
        .then(async (r) => {
            const text = await r.text();

            try {
                return JSON.parse(text);
            } catch (e) {
                console.error(text);

                return {
                    success: false,
                };
            }
        })

        .then((ai) => {
            const grid = document.getElementById("aiInsightGrid");

            aiLoading = false;

            const aiState = document.getElementById("aiLoadingState");
            if (aiState) aiState.style.display = "none";

            if (!grid) return;

            if (!ai.success) {
                aiReady = false;
                aiError = "Unable to generate analysis.";

                grid.innerHTML = `
<div class="ai-insight-error">
Unable to generate analysis.
</div>
`;

                return;
            }

            aiReady = true;
            aiError = null;

            renderInsightCards(ai.analysis);
        })

        .catch((err) => {
            console.error(err);

            aiLoading = false;
            aiReady = false;
            aiError = "AI service unavailable.";

            const aiState = document.getElementById("aiLoadingState");
            if (aiState) aiState.style.display = "none";

            const grid = document.getElementById("aiInsightGrid");

            if (grid) {
                grid.innerHTML = `
<div class="ai-insight-error">
AI service unavailable.
</div>
`;
            }
        });
}

function bindBack() {
    const btn = document.getElementById("backToRanking");

    if (!btn) return;

    btn.onclick = () => {
        document.getElementById("aiPanel").style.display = "none";

        document.getElementById("rankingPanel").style.display = "block";
    };
}

function bindLimitControl() {
    const select = document.getElementById("limitSelect");

    if (!select) return;

    select.onchange = () => {
        rankingLimit = safeNumber(select.value);

        renderDriverList();
    };
}

/* ============================ Driver Insights (finance) ============================ */

function diEsc(v) {
    return String(v == null ? "" : v)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

function diMoney(v) {
    const n = safeNumber(v);
    return n.toLocaleString("en-PH", { maximumFractionDigits: 0 });
}

function diNum(v, d) {
    const n = safeNumber(v);
    const digits = d == null ? 0 : d;
    return n.toLocaleString("en-PH", {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    });
}

let diCenterText = {
    id: "diCenterText",
    afterDraw(chart, args, opts) {
        if (opts.text === undefined || opts.text === "") return;
        const ctx = chart.ctx;
        const area = chart.chartArea;
        ctx.save();
        ctx.font = opts.font || "bold 14px Poppins, sans-serif";
        ctx.fillStyle = opts.color || "#295738";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        const cx = (area.left + area.right) / 2;
        const cy = (area.top + area.bottom) / 2;
        ctx.fillText(String(opts.text), cx, cy - (opts.sub ? 6 : 0));
        if (opts.sub) {
            ctx.font = "10px Poppins, sans-serif";
            ctx.fillStyle = "#94a3b8";
            ctx.fillText(String(opts.sub), cx, cy + 12);
        }
        ctx.restore();
    },
};

try {
    if (typeof Chart !== "undefined") Chart.register(diCenterText);
} catch (e) {}

const DI_MONTHS = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec",
];

function diFmtLabel(key, gran) {
    const keyStr = String(key);
    if (gran === "month") {
        const p = keyStr.split("-");
        if (p.length >= 2) {
            const m = DI_MONTHS[parseInt(p[1], 10) - 1] || p[1];
            const y = parseInt(p[0], 10);
            const nowY = new Date().getFullYear();
            return m + (y !== nowY ? " '" + String(y).slice(2) : "");
        }
        return keyStr;
    }
    const d = new Date(keyStr.slice(0, 10) + "T00:00:00");
    if (isNaN(d.getTime())) return keyStr;
    return DI_MONTHS[d.getMonth()] + " " + d.getDate();
}

function diBucketSeries(keys, trips, fare, gran) {
    if (gran !== "week" && gran !== "month") {
        return {
            labels: (keys || []).map(function (k) {
                return diFmtLabel(k, "day");
            }),
            trips: (trips || []).map(function (v) {
                return Math.round(Number(v) || 0);
            }),
            fare: (fare || []).map(function (v) {
                return Math.round(Number(v) || 0);
            }),
        };
    }
    const buckets = {};
    const order = [];
    const allKeys = keys || [];
    for (let i = 0; i < allKeys.length; i += 1) {
        let b;
        const k = String(allKeys[i]);
        if (gran === "month") {
            b = k.slice(0, 7);
        } else {
            const d = new Date(k.slice(0, 10) + "T00:00:00");
            const dow = (d.getDay() + 6) % 7;
            const ws = new Date(d.getFullYear(), d.getMonth(), d.getDate() - dow);
            b =
                ws.getFullYear() +
                "-" +
                String(ws.getMonth() + 1).padStart(2, "0") +
                "-" +
                String(ws.getDate()).padStart(2, "0");
        }
        if (!(b in buckets)) {
            buckets[b] = { trips: 0, fare: 0 };
            order.push(b);
        }
        buckets[b].trips += Number(trips[i]) || 0;
        buckets[b].fare += Number(fare[i]) || 0;
    }
    return {
        labels: order.map(function (o) {
            return diFmtLabel(o, gran);
        }),
        trips: order.map(function (o) {
            return Math.round(buckets[o].trips);
        }),
        fare: order.map(function (o) {
            return Math.round(buckets[o].fare);
        }),
    };
}

function diDestroyCharts() {
    [tripCompletionChart, attendanceChart, fuelChart, costChart].forEach(function (c) {
        try {
            if (c) c.destroy();
        } catch (e) {}
    });
    tripCompletionChart = null;
    attendanceChart = null;
    fuelChart = null;
    costChart = null;
}

function loadDriverInsights(filterQuery) {
    const seq = ++diLoadSeq;
    driverInsightsData = null;

    var status = document.getElementById("diFleetStatus");
    if (status) status.innerText = "Loading Insights...";

    var url = "/finance/api/dashboard/driver_insights.php";
    if (filterQuery) url += "?" + filterQuery;

    fetch(url, { cache: "no-store" })
        .then(async function (r) {
            const text = await r.text();

            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("Invalid JSON:", text);

                return { success: false };
            }
        })

        .then(function (res) {
            if (seq !== diLoadSeq) return;

            if (!res || !res.success) {
                console.error(res && (res.message || res.error));

                if (status) status.innerText = "Failed to load";
                var empty = document.getElementById("diEmpty");
                if (empty) {
                    empty.style.display = "";
                    empty.innerText = "Unable to load driver insights for the selected period.";
                }

                return;
            }

            driverInsightsData = res;

            if (driverInsightActive) {
                const still = (res.drivers || []).find(function (r) {
                    return String(r.driver_id) === String(driverInsightActive.driver_id);
                });
                driverInsightActive = still || null;

                var section = document.getElementById("driverInsightsSection");
                if (section) section.style.display = "";
            }

            renderFleetInsights(res);
        })

        .catch(function (err) {
            if (seq !== diLoadSeq) return;
            console.error("loadDriverInsights failed:", err);
            if (status) status.innerText = "Failed to load";
        });
}

function diBestNote(b, fmt) {
    if (!b || b.value === undefined || b.value === null || b.value === "") return "";
    return (
        '<div class="di-best"><i class="bi bi-trophy-fill" style="font-size:0.6rem;"></i> Best: ' +
        diEsc(b.name) +
        " (" +
        fmt(b.value) +
        ")</div>"
    );
}

function buildKpis(fleet) {
    const f = fleet || {};
    const b = f.best || {};
    const trips = f.trips || {};
    const att = f.attendance || {};
    const fuel = f.fuel || {};
    const fin = f.financial || {};
    const occ = f.occupancy || {};
    const pct1 = function (v) {
        return diNum(v, 1) + "%";
    };
    const cards = [
        {
            label: "Trip Completion",
            value: trips.completion_rate != null ? pct1(trips.completion_rate) : "\u2014",
            sub:
                (trips.completed || 0) +
                " / " +
                (trips.total || 0) +
                " trips \u00b7 " +
                (trips.cancelled || 0) +
                " cancelled",
            best: diBestNote(b.completion, pct1),
        },
        {
            label: "Attendance",
            value: att.rate != null ? pct1(att.rate) : "\u2014",
            sub: (att.present || 0) + " / " + (att.scheduled || 0) + " duty days",
            best: diBestNote(b.attendance, pct1),
        },
        {
            label: "Net Income",
            value: "\u20b1" + diMoney(fin.net_income),
            sub: "On \u20b1" + diMoney(fin.gross_income) + " gross",
            best: diBestNote(b.net, function (v) {
                return "\u20b1" + diMoney(v);
            }),
        },
        {
            label: "Fleet Occupancy",
            value: occ.value != null ? pct1(occ.value) : "\u2014",
            sub:
                (occ.capacity || 0) > 0
                    ? (occ.occupied || 0) + " / " + (occ.capacity || 0) + " seats"
                    : "Capacity not tracked",
            best: "",
        },
    ];

    return cards
        .map(function (c) {
            const fs = c.value.length > 9 ? "1.2rem" : "1.55rem";
            return (
                '<div class="col-6 col-lg">' +
                '<div class="di-kpi-card">' +
                '<div class="di-kpi-label">' +
                c.label +
                "</div>" +
                '<div class="di-kpi-value" style="font-size:' +
                fs +
                '">' +
                c.value +
                "</div>" +
                '<div class="di-kpi-sub">' +
                c.sub +
                "</div>" +
                c.best +
                "</div></div>"
            );
        })
        .join("");
}

function renderFleetInsights(res) {
    const range = res.range || {};
    var rl = document.getElementById("diRangeLabel");
    if (rl)
        rl.innerText =
            range.label || "From " + (range.from || "") + " to " + (range.to || "");

    renderDiSection();
}

function diDriverHasRecords(d) {
    if (!d) return false;
    const trips = d.trips || {};
    const att = d.attendance || {};
    const fuel = d.fuel || {};
    const costs = d.costs || {};
    if ((trips.total || 0) > 0) return true;
    if ((att.present || 0) + (att.absent || 0) + (att.scheduled || 0) > 0) return true;
    if ((fuel.distance || 0) > 0 || (fuel.liters || 0) > 0) return true;
    if ((costs.gross_income || 0) > 0 || (costs.net_income || 0) > 0) return true;
    if (Array.isArray(d.daily) && d.daily.length) return true;
    return false;
}

function renderDiSection() {
    if (!driverInsightsData) return;

    var empty = document.getElementById("diEmpty");
    if (!empty) return;

    let hasContent;
    let emptyMsg = "No data available for the selected period.";
    if (driverInsightActive) {
        hasContent = diDriverHasRecords(driverInsightActive);
        emptyMsg =
            "No records for " +
            (driverInsightActive.name || "this driver") +
            " in the selected period.";
    } else {
        hasContent = (driverInsightsData.fleet.active || 0) > 0;
        emptyMsg = "No driver activity for the selected period.";
    }

    renderDiHeader();

    var kpis = document.getElementById("diFleetKpis");
    var trendWrap = document.getElementById("diTrendWrap");
    var tableWrap = document.getElementById("diTableWrap");

    if (!hasContent) {
        empty.style.display = "";
        empty.innerText = emptyMsg;
        if (kpis) kpis.innerHTML = "";
        if (trendWrap) trendWrap.style.display = "none";
        if (tableWrap) tableWrap.style.display = "none";
        renderDiTrend();
        renderDiTable();

        return;
    }

    empty.style.display = "none";
    if (kpis) renderDiKpis();
    if (trendWrap) trendWrap.style.display = "";
    if (tableWrap) tableWrap.style.display = "";
    renderDiTrend();
    renderDiTable();
}

function renderDiHeader() {
    var status = document.getElementById("diFleetStatus");
    var clearBtn = document.getElementById("diClearDriver");
    var trendTitle = document.querySelector("#diTrendWrap h6");
    var tableTitle = document.querySelector("#diTableWrap h6");

    if (!driverInsightsData) return;

    if (driverInsightActive) {
        if (status)
            status.innerText =
                "Viewing: " + (driverInsightActive.name || "Selected driver");
        if (clearBtn) clearBtn.style.display = "";
        if (trendTitle)
            trendTitle.innerHTML =
                '<i class="bi bi-graph-up-arrow text-success me-2"></i>Trips & Earnings Trend';
        if (tableTitle)
            tableTitle.innerHTML =
                '<i class="bi bi-list-ul text-success me-2"></i>Driver Summary';
    } else {
        if (status)
            status.innerText =
                (driverInsightsData.fleet.active || 0) +
                " active \u00b7 " +
                (driverInsightsData.fleet.drivers || 0) +
                " drivers";
        if (clearBtn) clearBtn.style.display = "none";
        if (trendTitle)
            trendTitle.innerHTML =
                '<i class="bi bi-graph-up-arrow text-success me-2"></i>Fleet Performance Trend';
        if (tableTitle)
            tableTitle.innerHTML =
                '<i class="bi bi-list-ol text-success me-2"></i>Driver Comparison';
    }
}

function renderDiKpis() {
    var kpis = document.getElementById("diFleetKpis");
    if (!kpis) return;

    if (driverInsightActive) {
        kpis.innerHTML = buildDriverKpis(driverInsightActive);
    } else {
        kpis.innerHTML = buildKpis(driverInsightsData.fleet || {});
    }
}

function buildDriverKpis(d) {
    const trips = d.trips || {};
    const att = d.attendance || {};
    const fuel = d.fuel || {};
    const costs = d.costs || {};
    const pct1 = function (v) {
        return diNum(v, 1) + "%";
    };
    const totalCosts = Math.max(costs.fixed_total || 0, 0) + Math.max(costs.fuel_cost || 0, 0);
    const cards = [
        {
            label: "Trip Completion",
            value:
                trips.completion_rate != null ? pct1(trips.completion_rate) : "\u2014",
            sub:
                (trips.completed || 0) +
                " / " +
                (trips.total || 0) +
                " trips \u00b7 " +
                (trips.cancelled || 0) +
                " cancelled",
        },
        {
            label: "Attendance",
            value: att.rate != null ? pct1(att.rate) : "\u2014",
            sub: (att.present || 0) + " / " + (att.scheduled || 0) + " duty days",
        },
        {
            label: "Operating Costs",
            value: "\u20b1" + diMoney(totalCosts),
            sub:
                "Fixed \u20b1" +
                diMoney(costs.fixed_total || 0) +
                " \u00b7 Fuel \u20b1" +
                diMoney(costs.fuel_cost || 0),
        },
        {
            label: "Net Income",
            value: "\u20b1" + diMoney(costs.net_income || 0),
            sub: "On \u20b1" + diMoney(costs.gross_income || 0) + " gross",
        },
    ];

    return cards
        .map(function (c) {
            const fs = c.value.length > 9 ? "1.2rem" : "1.55rem";
            return (
                '<div class="col-6 col-lg">' +
                '<div class="di-kpi-card">' +
                '<div class="di-kpi-label">' +
                c.label +
                "</div>" +
                '<div class="di-kpi-value" style="font-size:' +
                fs +
                '">' +
                c.value +
                "</div>" +
                '<div class="di-kpi-sub">' +
                c.sub +
                "</div>" +
                "</div></div>"
            );
        })
        .join("");
}

function renderDiTrend() {
    if (driverInsightActive) renderDriverTrendChart(driverInsightActive);
    else renderFleetTrendChart();
}

function diBuildTrendChart(canvas, bucketed) {
    const hasData = bucketed.labels.length > 0;
    let chart = null;
    try {
        chart = new Chart(canvas, {
            type: "line",
            data: {
                labels: hasData ? bucketed.labels : ["No Data"],
                datasets: hasData
                    ? [
                          {
                              label: "Trips",
                              data: bucketed.trips,
                              tension: 0.45,
                              borderWidth: 3,
                              pointRadius: 0,
                              borderColor: "#2f4f2f",
                              backgroundColor: "rgba(222,228,186,0.45)",
                              fill: true,
                          },
                          {
                              label: "Fare",
                              data: bucketed.fare,
                              tension: 0.45,
                              borderWidth: 3,
                              pointRadius: 0,
                              borderColor: "#6b7d3a",
                              fill: false,
                              yAxisID: "y1",
                          },
                      ]
                    : [],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 10, bottom: 10, left: 10, right: 10 } },
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: "nearest",
                        intersect: false,
                        callbacks: {
                            label: function (c) {
                                return (
                                    c.dataset.label +
                                    ": " +
                                    (c.dataset.yAxisID === "y1"
                                        ? "\u20b1" + diNum(c.parsed.y, 0)
                                        : diNum(c.parsed.y, 0))
                                );
                            },
                        },
                    },
                },
                scales: hasData
                    ? {
                          y: {
                              beginAtZero: true,
                              ticks: { padding: 8, font: { size: 11 }, precision: 0 },
                          },
                          y1: {
                              position: "right",
                              beginAtZero: true,
                              grid: { drawOnChartArea: false },
                              ticks: {
                                  padding: 8,
                                  font: { size: 11 },
                                  callback: function (v) {
                                      return "\u20b1" + diNum(v, 0);
                                  },
                              },
                          },
                          x: { ticks: { padding: 8, font: { size: 11 } } },
                      }
                    : {},
            },
        });
    } catch (e) {
        console.error("Trend chart error:", e);
        chart = null;
    }

    return { chart: chart, hasData: hasData };
}

function diNoDataToggle(canvasId, hasData, emptyMsg) {
    if (hasData) {
        if (window.__hideChartNoData) window.__hideChartNoData(canvasId);
    } else {
        if (window.__showChartNoData) window.__showChartNoData(canvasId, emptyMsg);
    }
}

function renderFleetTrendChart() {
    var canvas = document.getElementById("fleetTrendChart");
    if (!canvas) return;

    if (fleetTrendChart) {
        fleetTrendChart.destroy();
        fleetTrendChart = null;
    }
    if (!driverInsightsData) return;

    const t = driverInsightsData.trend || {};
    const bucketed = diBucketSeries(
        Array.isArray(t.keys) ? t.keys : [],
        Array.isArray(t.trips) ? t.trips : [],
        Array.isArray(t.fare) ? t.fare : [],
        diGranularity,
    );

    const out = diBuildTrendChart(canvas, bucketed);
    fleetTrendChart = out.chart;
    diNoDataToggle("fleetTrendChart", out.hasData, "No fleet activity for this period.");
}

function renderDriverTrendChart(d) {
    var canvas = document.getElementById("fleetTrendChart");
    if (!canvas) return;

    if (fleetTrendChart) {
        fleetTrendChart.destroy();
        fleetTrendChart = null;
    }

    const daily = Array.isArray(d.daily) ? d.daily : [];
    const keys = daily.map(function (r) {
        return String(r.day || "").slice(0, 10);
    });
    const tripsA = daily.map(function (r) {
        return Number(r.trips) || 0;
    });
    const fareA = daily.map(function (r) {
        return Number(r.fare) || 0;
    });

    const bucketed = diBucketSeries(keys, tripsA, fareA, diGranularity);
    const out = diBuildTrendChart(canvas, bucketed);
    fleetTrendChart = out.chart;
    diNoDataToggle(
        "fleetTrendChart",
        out.hasData,
        "No trips & earnings for this driver in the selected period.",
    );
}

function renderDriverSummaryTable(d) {
    var el = document.getElementById("diDriverTable");
    if (!el) return;

    const trips = d.trips || {};
    const att = d.attendance || {};
    const fuel = d.fuel || {};
    const costs = d.costs || {};
    const occ = d.occupancy || {};
    const pass = d.passengers || {};
    const occStr =
        !occ.capacity_unavailable && occ.value != null
            ? diNum(occ.value, 1) + "% (" + (occ.occupied || 0) + " / " + (occ.capacity || 0) + ")"
            : "\u2014";

    const rows = [
        ["Gross Income", "\u20b1" + diMoney(costs.gross_income || 0)],
        ["Fixed Costs", "\u20b1" + diMoney(costs.fixed_total || 0)],
        ["Fuel Cost", "\u20b1" + diMoney(costs.fuel_cost || 0)],
        ["Net Income", "\u20b1" + diMoney(costs.net_income || 0)],
        ["Net Margin", costs.net_margin != null ? diNum(costs.net_margin, 1) + "%" : "\u2014"],
        ["Total Trips", String(trips.total || 0)],
        [
            "Trip Completion",
            trips.total > 0 ? diNum(trips.completion_rate, 1) + "%" : "\u2014",
        ],
        ["Cancelled / Pending", (trips.cancelled || 0) + " / " + (trips.pending || 0)],
        [
            "Duty Days (Present / Absent)",
            (att.present || 0) + " / " + (att.absent || 0),
        ],
        ["Attendance Rate", att.rate != null ? diNum(att.rate, 1) + "%" : "\u2014"],
        ["Distance Covered", diNum(fuel.distance || 0, 0) + " km"],
        ["Fuel Consumed", diNum(fuel.liters || fuel.consumed || 0, 1) + " L"],
        [
            "Fuel Cost per Liter",
            fuel.cost_per_liter != null ? "\u20b1" + diNum(fuel.cost_per_liter, 2) : "\u2014",
        ],
        [
            "Passengers (Regular / Special)",
            (pass.regular || 0) + " / " + (pass.special || 0),
        ],
        ["Fleet Occupancy", occStr],
    ];

    let html =
        '<table class="table table-borderless di-table w-100" style="min-width:300px;margin-bottom:0;"><tbody>';
    html += rows
        .map(function (r) {
            return (
                '<tr><td style="color:#7d8b78;font-weight:600;">' +
                r[0] +
                '</td><td class="text-end" style="font-weight:600;">' +
                r[1] +
                "</td></tr>"
            );
        })
        .join("");
    html += "</tbody></table>";

    el.innerHTML = html;
}

function renderDiTable() {
    if (driverInsightActive) renderDriverSummaryTable(driverInsightActive);
    else renderDriverComparisonTable();
}

function selectDriverInsight(driver) {
    if (!driver) return;

    var section = document.getElementById("driverInsightsSection");
    if (section) section.style.display = "";

    if (!driverInsightsData || !Array.isArray(driverInsightsData.drivers)) {
        driverInsightActive = {
            driver_id: String(driver.driver_id || ""),
            name: String(driver.name || driver.full_name || "Selected driver"),
        };

        return;
    }

    const found = driverInsightsData.drivers.find(function (r) {
        return String(r.driver_id) === String(driver.driver_id);
    });
    driverInsightActive = found || {
        driver_id: String(driver.driver_id || ""),
        name: String(driver.name || driver.full_name || "Selected driver"),
    };

    renderDiSection();
}

function clearDriverInsight() {
    driverInsightActive = null;

    var sec = document.getElementById("driverInsightsSection");
    if (sec) sec.style.display = "none";

    var panelsRow = document.getElementById("driverInsightPanelsRow");
    if (panelsRow) panelsRow.style.display = "none";
}

function renderDriverComparisonTable() {
    var el = document.getElementById("diDriverTable");
    if (!el) return;

    if (!driverInsightsData) {
        el.innerHTML = '<div class="text-muted" style="font-size:0.72rem;">Driver data not loaded yet.</div>';

        return;
    }

    const rows = (driverInsightsData.drivers || []).filter(function (r) {
        return !r.no_activity;
    });
    if (!rows.length) {
        el.innerHTML =
            '<div class="text-muted" style="font-size:0.72rem;">No driver activity for this period.</div>';

        return;
    }

    const top = rows.slice(0, 8);
    const bestNetId =
        driverInsightsData.fleet &&
        driverInsightsData.fleet.best &&
        driverInsightsData.fleet.best.net
            ? String(driverInsightsData.fleet.best.net.driver_id)
            : null;

    let html =
        '<table class="table table-borderless di-table w-100" style="min-width:560px;margin-bottom:0;"><thead><tr><th>Driver</th><th>Trips</th><th>Compl.</th><th>Att.</th><th>Fuel</th><th>Net</th></tr></thead><tbody>';

    html += top
        .map(function (r) {
            const att =
                r.attendance && r.attendance.rate != null
                    ? diNum(r.attendance.rate, 1) + "%"
                    : "\u2014";
            const fuelCost = r.fuel ? r.fuel.amount : 0;
            const cls =
                bestNetId === String(r.driver_id)
                    ? ' class="di-best-row" style="cursor:pointer;"'
                    : ' style="cursor:pointer;"';
            return (
                '<tr' +
                cls +
                ' data-did="' +
                diEsc(r.driver_id) +
                '"><td><strong>' +
                diEsc(r.name) +
                "</strong></td><td>" +
                (r.trips ? r.trips.total : 0) +
                "</td><td>" +
                (r.trips ? diNum(r.trips.completion_rate, 1) : "0") +
                "%</td><td>" +
                att +
                "</td><td>\u20b1" +
                diMoney(fuelCost) +
                "</td><td>\u20b1" +
                diMoney(r.costs ? r.costs.net_income : 0) +
                "</td></tr>"
            );
        })
        .join("");
    html += "</tbody></table>";

    el.innerHTML = html;

    el.querySelectorAll("tr[data-did]").forEach(function (tr) {
        tr.addEventListener("click", function () {
            const did = tr.getAttribute("data-did");
            const dr = (driverInsightsData.drivers || []).find(function (x) {
                return String(x.driver_id) === did;
            });
            if (!dr) return;

            renderDriverMetricsFromInsight(dr);

            var sec = document.getElementById("driverMetricsSection");
            if (sec) {
                sec.style.display = "block";
                sec.scrollIntoView({ behavior: "smooth", block: "start" });
            }
        });
    });
}

function renderDriverPanels(driver) {
    var row = document.getElementById("driverInsightPanelsRow");
    if (!row || !driver) return;

    currentInsightDriver = driver;

    const found = driverInsightsData && Array.isArray(driverInsightsData.drivers)
        ? driverInsightsData.drivers.find(function (r) {
              return String(r.driver_id) === String(driver.driver_id);
          })
        : null;
    if (!found) {
        row.style.display = "none";
        return;
    }
    if (!diDriverHasRecords(found)) {
        row.style.display = "none";
        return;
    }
    row.style.display = "";
    diDestroyCharts();

    const trips = found.trips || {};
    const att = found.attendance || {};
    const fuel = found.fuel || {};
    const costs = found.costs || {};
    const fleet = (driverInsightsData && driverInsightsData.fleet) || {};

    /* Trip completion donut */
    var tCanvas = document.getElementById("diDriverTripDonut");
    if (tCanvas) {
        const donutData = [
            { v: trips.completed || 0, c: "#2f4f2f" },
            { v: trips.cancelled || 0, c: "#d9b2a0" },
            { v: trips.pending || 0, c: "#d9ceb2" },
        ].filter(function (x) {
            return x.v > 0;
        });
        const donutLabels = ["Completed", "Cancelled", "Pending"].slice(
            0,
            donutData.length,
        );
        tripCompletionChart = new Chart(tCanvas, {
            type: "doughnut",
            data: {
                labels: donutLabels,
                datasets: [
                    {
                        data: donutData.map(function (x) {
                            return x.v;
                        }),
                        backgroundColor: donutData.map(function (x) {
                            return x.c;
                        }),
                        borderWidth: 2,
                        borderColor: "#f9f4da",
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "68%",
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: { boxWidth: 9, boxHeight: 9, font: { size: 9 }, color: "#2f4f2f" },
                    },
                    diCenterText: {
                        text: trips.completion_rate != null ? diNum(trips.completion_rate, 0) + "%" : "\u2014",
                        sub: (trips.total || 0) + " trips",
                        font: "bold 15px Poppins, sans-serif",
                    },
                },
            },
        });
        var note = document.getElementById("diDriverTripNote");
        if (note)
            note.innerText =
                (trips.completed || 0) +
                " completed \u00b7 " +
                (trips.cancelled || 0) +
                " cancelled \u00b7 " +
                (trips.pending || 0) +
                " pending";
    }

    /* Attendance bars */
    var aCanvas = document.getElementById("diDriverAttChart");
    if (aCanvas) {
        attendanceChart = new Chart(aCanvas, {
            type: "bar",
            data: {
                labels: ["Present", "Absent"],
                datasets: [
                    {
                        data: [att.present || 0, att.absent || 0],
                        backgroundColor: ["#2f4f2f", "#d9b2a0"],
                        borderWidth: 0,
                        borderRadius: 8,
                        maxBarThickness: 34,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (c) {
                                return c.label + ": " + diNum(c.parsed.y, 0);
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 10 } },
                        grid: { color: "rgba(0,0,0,0.05)" },
                    },
                    x: { ticks: { font: { size: 10 } }, grid: { display: false } },
                },
            },
        });
    }

    /* Operating costs */
    var cCanvas = document.getElementById("diDriverCostChart");
    if (cCanvas) {
        costChart = new Chart(cCanvas, {
            type: "doughnut",
            data: {
                labels: ["Fixed Costs", "Fuel Cost"],
                datasets: [
                    {
                        data: [Math.max(costs.fixed_total || 0, 0), Math.max(costs.fuel_cost || 0, 0)],
                        backgroundColor: ["#c5d9b2", "#d9b2a0"],
                        borderWidth: 2,
                        borderColor: "#f9f4da",
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "68%",
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: { boxWidth: 9, boxHeight: 9, font: { size: 9 }, color: "#2f4f2f" },
                    },
                    diCenterText: {
                        text: "\u20b1" + diMoney(costs.net_income || 0),
                        sub:
                            costs.net_margin != null
                                ? diNum(costs.net_margin, 0) + "% margin"
                                : "net income",
                        font: "bold 12px Poppins, sans-serif",
                    },
                },
            },
        });
    }
}

function renderDriverMetricsFromInsight(dr) {
    currentInsightDriver = dr;
    renderDriverPanels(dr);
    selectDriverInsight(dr);
    requestAnimationFrame(function () {
        try {
            renderTripsChart(dr);
        } catch (e) {}
        try {
            if (passengerChartType) {
                passengerChartType.destroy();
                passengerChartType = null;
            }
            const canvas = document.getElementById("passengerChartType");
            if (!canvas) return;
            const p = dr.passengers || {};
            const data = [p.regular || 0, p.special || 0];
            if (data[0] === 0 && data[1] === 0) {
                if (window.__showChartNoData)
                    window.__showChartNoData("passengerChartType", "No passenger data for this period.");
                return;
            }
            passengerChartType = new Chart(canvas, {
                type: "bar",
                data: {
                    labels: ["Regular", "Special"],
                    datasets: [
                        {
                            data: data,
                            backgroundColor: ["#c5d9b2", "#d9b2a2"],
                            borderColor: ["#c5d9b2", "#d9b2a2"],
                            borderWidth: 1.5,
                            borderRadius: 8,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } } },
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                    },
                },
            });
        } catch (e) {
            console.error("Insight passenger chart error:", e);
        }
    });
}

function bindDiGranularity() {
    document.querySelectorAll(".di-gran-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const gran = btn.getAttribute("data-gran");
            const inFleet = btn.closest("#driverInsightsSection") !== null;

            if (inFleet) {
                diGranularity = gran;
                document
                    .querySelectorAll("#driverInsightsSection .di-gran-btn")
                    .forEach(function (b) {
                        b.classList.toggle("di-gran-active", b.getAttribute("data-gran") === gran);
                    });
                renderDiTrend();
            } else {
                diDiGranularity = gran;
                document
                    .querySelectorAll("#driverMetricsSection .di-gran-btn")
                    .forEach(function (b) {
                        b.classList.toggle("di-gran-active", b.getAttribute("data-gran") === gran);
                    });
                if (currentInsightDriver) renderTripsChart(currentInsightDriver);
            }
        });
    });
}

window.loadDriverInsights = loadDriverInsights;
window.renderDriverPanels = renderDriverPanels;
window.selectDriverInsight = selectDriverInsight;
window.clearDriverInsight = clearDriverInsight;

window.loadDriverStats = loadDriverStats;

document.addEventListener("DOMContentLoaded", () => {
    initBubbleChart();

    loadDriverStats();

    bindBack();

    bindLimitControl();

    bindDiGranularity();

    var clearBtn = document.getElementById("diClearDriver");
    if (clearBtn) clearBtn.addEventListener("click", clearDriverInsight);

    loadDriverInsights(window.__financeFilterQuery || "");
});
</script>
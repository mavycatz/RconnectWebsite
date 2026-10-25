<div class="fin-panel" id="fuelConsumptionPanel">
    <div class="fin-panel-head">
        <div>
            <h2 class="fin-panel-title"><i class="bi bi-fuel-pump"></i> All Fuel Consumption</h2>
            <p class="fin-panel-sub">All fuel consumption and estimated cost for the selected period</p>
        </div>
        <div class="fin-panel-meta">
            <span class="fin-pill" id="fconPricePill"></span>
        </div>
    </div>

    <div class="fcon-kpis">
        <div class="fcon-kpi">
            <div class="fcon-kpi-label">Total Fuel Consumed</div>
            <div class="fcon-kpi-value" id="fconLiters">0.0 L</div>
            <div class="fcon-kpi-sub" id="fconLitersSub">Liters for the period</div>
        </div>
        <div class="fcon-kpi">
            <div class="fcon-kpi-label">Estimated Fuel Cost</div>
            <div class="fcon-kpi-value" id="fconCost">₱0.00</div>
            <div class="fcon-kpi-sub" id="fconCostSub">Based on current fuel price</div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="fcon-chart-card">
                <h3 class="fcon-chart-title">Fuel Cost &amp; Consumption Trend</h3>
                <div class="fcon-chart-wrap">
                    <canvas id="fconTrendChart"></canvas>
                    <div class="fcon-empty" id="fconTrendEmpty" hidden>No data available for the selected period.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#fuelConsumptionPanel .fin-panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
#fuelConsumptionPanel .fin-panel-title {
    font-size: 1rem;
    font-weight: 700;
    color: #2e4836;
    margin: 0 0 2px;
}
#fuelConsumptionPanel .fin-panel-title i {
    color: #295738;
    margin-right: 6px;
}
#fuelConsumptionPanel .fin-panel-sub {
    font-size: 0.72rem;
    color: #8a9389;
    margin: 0;
}
#fuelConsumptionPanel .fin-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 4px 12px;
    font-size: 0.7rem;
    font-weight: 700;
    background: #f9f4da;
    color: #2e4836;
    border: 1px solid #cdd9b5;
}
#fuelConsumptionPanel .fin-pill:empty {
    display: none;
}

.fcon-kpis {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
@media (max-width: 767px) {
    .fcon-kpis {
        grid-template-columns: 1fr;
    }
}
.fcon-kpi {
    background: #fdfbf6;
    border: 1px solid #e3eabb;
    border-radius: 14px;
    padding: 12px 14px;
}
.fcon-kpi-label {
    font-size: 0.64rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #8a9389;
}
.fcon-kpi-value {
    font-size: clamp(16px, 1.4vw, 22px);
    font-weight: 800;
    color: #2e4836;
    margin-top: 4px;
    font-variant-numeric: tabular-nums;
}
.fcon-kpi-sub {
    font-size: 0.68rem;
    color: #8a9389;
    margin-top: 3px;
}

.fcon-chart-card {
    background: #fdfbf6;
    border: 1px solid #e3eabb;
    border-radius: 14px;
    padding: 12px;
    height: 100%;
}
.fcon-chart-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #2e4836;
    margin: 0 0 8px;
}
.fcon-chart-wrap {
    position: relative;
    height: 280px;
}
.fcon-chart-wrap canvas {
    width: 100% !important;
    height: 100% !important;
}
.fcon-empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    font-weight: 600;
    color: #8a9389;
    background: #fdfbf6;
    border-radius: 10px;
    text-align: center;
    padding: 0 14px;
}
@media (max-width: 575px) {
    .fcon-chart-wrap {
        height: 220px;
    }
}
</style>

<script>
(function () {
    "use strict";

    var trendChart = null;
    var requestSeq = 0;

    function money(value) {
        return (
            "₱" +
            Number(value || 0).toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
        );
    }

    function showEmpty(id, show) {
        var el = document.getElementById(id);
        if (el) {
            el.hidden = !show;
        }
    }

    function renderTrend(trend) {
        var canvas = document.getElementById("fconTrendChart");
        var labels = trend && trend.labels ? trend.labels : [];
        var costs = trend && trend.estimated_cost ? trend.estimated_cost : [];
        var liters = trend && trend.consumed_liters ? trend.consumed_liters : [];
        var hasData =
            costs.some(function (v) {
                return Number(v) > 0;
            }) ||
            liters.some(function (v) {
                return Number(v) > 0;
            });

        showEmpty("fconTrendEmpty", !hasData);
        if (!hasData) {
            if (trendChart) {
                trendChart.destroy();
                trendChart = null;
            }
            return;
        }

        var datasets = [
            {
                label: "Estimated Fuel Cost",
                data: costs,
                borderColor: "#295738",
                backgroundColor: "rgba(41, 87, 56, 0.12)",
                borderWidth: 2.2,
                pointRadius: 2,
                tension: 0.35,
                fill: true,
                yAxisID: "y",
            },
            {
                label: "Liters Consumed",
                data: liters,
                borderColor: "#c4b417",
                backgroundColor: "transparent",
                borderWidth: 1.8,
                pointRadius: 2,
                tension: 0.35,
                fill: false,
                yAxisID: "y1",
            },
        ];

        if (trendChart) {
            trendChart.data.labels = labels;
            trendChart.data.datasets = datasets;
            trendChart.update();
        } else {
            trendChart = new Chart(canvas, {
                type: "line",
                data: { labels: labels, datasets: datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: "index", intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                boxWidth: 12,
                                boxHeight: 12,
                                usePointStyle: true,
                                font: { size: 10 },
                                color: "#2e4836",
                            },
                        },
                        tooltip: {
                            backgroundColor: "#2e4836",
                            padding: 10,
                            callbacks: {
                                label: function (ctx) {
                                    if (ctx.dataset.yAxisID === "y1") {
                                        return (
                                            ctx.dataset.label +
                                            ": " +
                                            Number(ctx.parsed.y || 0).toLocaleString("en-US", {
                                                maximumFractionDigits: 1,
                                            }) +
                                            " L"
                                        );
                                    }
                                    return ctx.dataset.label + ": " + money(ctx.parsed.y);
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: "#8a9389",
                                font: { size: 10 },
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 10,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: "rgba(205, 217, 181, 0.45)" },
                            ticks: {
                                color: "#8a9389",
                                font: { size: 10 },
                                callback: function (v) {
                                    var n = Number(v);
                                    return (
                                        "₱" +
                                        (Math.abs(n) >= 1000 ? (n / 1000).toFixed(0) + "k" : n)
                                    );
                                },
                            },
                        },
                        y1: {
                            beginAtZero: true,
                            position: "right",
                            grid: { drawOnChartArea: false },
                            ticks: {
                                color: "#8a9389",
                                font: { size: 10 },
                                callback: function (v) {
                                    return Number(v).toLocaleString("en-US") + " L";
                                },
                            },
                        },
                    },
                },
            });
        }

        if (window.__hideChartNoData) {
            window.__hideChartNoData("fconTrendChart");
        }
    }

    function setKpiText(id, text) {
        var el = document.getElementById(id);
        if (el) {
            el.textContent = text;
        }
    }

    function render(data) {
        var summary = (data && data.summary) || {};
        var trend = (data && data.fuel_trend) || {};

        var liters = Number(summary.total_liters || summary.total_fuel_consumed || 0);
        var cost = Number(summary.total_estimated_fuel_cost || summary.total_fuel_cost || 0);

        setKpiText(
            "fconLiters",
            liters.toLocaleString("en-US", { maximumFractionDigits: 1 }) + " L",
        );
        setKpiText("fconCost", money(cost));
        setKpiText(
            "fconLitersSub",
            Number(summary.fuel_trips_count || 0) +
                " logged trip" +
                (Number(summary.fuel_trips_count || 0) === 1 ? "" : "s"),
        );
        setKpiText(
            "fconCostSub",
            Number(summary.default_price || 0) > 0
                ? "At ₱" + Number(summary.default_price).toFixed(2) + " / L"
                : "Based on current fuel price",
        );

        var pricePill = document.getElementById("fconPricePill");
        if (pricePill) {
            pricePill.textContent =
                Number(summary.default_price || 0) > 0
                    ? "Diesel ₱" + Number(summary.default_price).toFixed(2) + " / L"
                    : "";
        }

        renderTrend(trend);
    }

    function renderError() {
        showEmpty("fconTrendEmpty", true);
        var trendEmpty = document.getElementById("fconTrendEmpty");
        if (trendEmpty) {
            trendEmpty.textContent = "Couldn't load fuel analytics. Select a period to retry.";
        }
        if (trendChart) {
            trendChart.destroy();
            trendChart = null;
        }
        setKpiText("fconLiters", "—");
        setKpiText("fconCost", "—");
    }

    function loadFuelConsumption(query) {
        var requestId = ++requestSeq;
        var q = query || "";

        if (window.__dashLoadBegin) window.__dashLoadBegin();

        fetch("/finance/api/dashboard/fuel_analytics.php?" + q, { cache: "no-store" })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (requestId !== requestSeq) {
                    return;
                }
                if (!data || !data.success) {
                    renderError();
                    return;
                }
                render(data);
            })
            .catch(function () {
                if (requestId !== requestSeq) {
                    return;
                }
                renderError();
            })
            .finally(function () {
                if (window.__dashLoadEnd) window.__dashLoadEnd();
            });
    }

    window.loadFuelConsumption = loadFuelConsumption;

    document.addEventListener("DOMContentLoaded", function () {
        if (!window.__financeFilterQuery) {
            loadFuelConsumption("range=year");
        }
    });
})();
</script>

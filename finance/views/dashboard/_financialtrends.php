<div class="fin-panel" id="financialTrendsPanel">
    <div class="fin-panel-head">
        <div>
            <h2 class="fin-panel-title"><i class="bi bi-graph-up-arrow"></i> Financial Trends &amp; Comparison</h2>
            <p class="fin-panel-sub" id="ftSub">Estimated profit and remittance movement for the selected period</p>
        </div>
        <div class="fin-panel-meta" id="ftMeta"></div>
    </div>

    <div class="fin-chart-wrap fin-chart-md">
        <canvas id="ftChart"></canvas>
        <div class="fin-chart-empty" id="ftEmpty" hidden>No data available for the selected period.</div>
    </div>
</div>

<style>
#financialTrendsPanel .fin-panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
#financialTrendsPanel .fin-panel-title {
    font-size: 1rem;
    font-weight: 700;
    color: #2e4836;
    margin: 0 0 2px;
}
#financialTrendsPanel .fin-panel-title i {
    color: #295738;
    margin-right: 6px;
}
#financialTrendsPanel .fin-panel-sub {
    font-size: 0.72rem;
    color: #8a9389;
    margin: 0;
}
#financialTrendsPanel .fin-panel-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
#financialTrendsPanel .fin-pill {
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
#financialTrendsPanel .fin-pill.is-up {
    color: #1c7c3f;
    border-color: #bfe3c6;
    background: #eef8f0;
}
#financialTrendsPanel .fin-pill.is-down {
    color: #b3261e;
    border-color: #f0c8c4;
    background: #fdf1f0;
}
#financialTrendsPanel {
    display: flex;
    flex-direction: column;
    height: 100%;
}
#financialTrendsPanel .fin-chart-wrap {
    position: relative;
    flex: 1 1 auto;
    min-height: 260px;
    background: #fdfbf6;
    border: 1px solid #e3eabb;
    border-radius: 14px;
    padding: 12px;
}
#financialTrendsPanel .fin-chart-wrap canvas {
    width: 100% !important;
    height: 100% !important;
}
#financialTrendsPanel .fin-chart-empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    color: #8a9389;
    background: #fdfbf6;
    border-radius: 14px;
}
@media (max-width: 575px) {
    #financialTrendsPanel .fin-chart-wrap {
        min-height: 200px;
    }
}
</style>

<script>
(function () {
    "use strict";

    var chart = null;
    var requestSeq = 0;

    function pctLabel(percent) {
        if (percent === null || percent === undefined) {
            return "";
        }
        var pct = Number(percent);
        var up = pct >= 0;
        var cls = up ? "is-up" : "is-down";
        var arrow = up ? "↑" : "↓";
        return (
            '<span class="fin-pill ' +
            cls +
            '">' +
            arrow +
            " " +
            Math.abs(pct).toFixed(1) +
            "% vs previous</span>"
        );
    }

    function metaPills(data) {
        var meta = document.getElementById("ftMeta");
        if (!meta) {
            return;
        }
        meta.innerHTML = "";

        var profit = data.profit || {};
        var completed = data.completed || {};
        var pending = data.pending || {};

        var parts = [];
        parts.push('<span class="fin-pill">Profit ' + formatMoney(profit.current) + "</span>");
        var pp = pctLabel(profit.percent, false);
        if (pp) {
            parts.push(pp);
        }
        parts.push('<span class="fin-pill">Completed ' + formatMoney(completed.amount) + "</span>");
        parts.push('<span class="fin-pill">Pending ' + formatMoney(pending.amount) + "</span>");
        meta.innerHTML = parts.join("");
    }

    function formatMoney(value) {
        return (
            "₱" +
            Number(value || 0).toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
        );
    }

    function showEmpty(show, message) {
        var empty = document.getElementById("ftEmpty");
        var canvas = document.getElementById("ftChart");
        if (empty) {
            empty.hidden = !show;
            if (message) {
                empty.textContent = message;
            }
        }
        if (canvas) {
            canvas.style.visibility = show ? "hidden" : "visible";
        }
    }

    function render(data) {
        var labels = data.labels || [];
        var series = data.series || {};
        var profit = series.profit || [];
        var profitPrev = series.profit_previous || [];
        var completed = series.completed || [];
        var pending = series.pending || [];

        var hasData = profit.concat(completed, pending).some(function (v) {
            return Number(v) !== 0;
        });

        showEmpty(!hasData);
        metaPills(data);

        if (!hasData) {
            if (chart) {
                chart.destroy();
                chart = null;
            }
            return;
        }

        var canvas = document.getElementById("ftChart");
        if (!canvas) {
            return;
        }

        var datasets = [
            {
                label: "Estimated Profit",
                data: profit,
                borderColor: "#295738",
                backgroundColor: "rgba(41, 87, 56, 0.10)",
                pointBackgroundColor: "#295738",
                pointRadius: 2,
                borderWidth: 2.4,
                tension: 0.35,
                fill: true,
            },
            {
                label: "Completed Remittances",
                data: completed,
                borderColor: "#2e7d32",
                backgroundColor: "transparent",
                pointRadius: 2,
                borderWidth: 1.8,
                tension: 0.35,
                fill: false,
            },
            {
                label: "Pending Remittances",
                data: pending,
                borderColor: "#c4b417",
                backgroundColor: "transparent",
                pointRadius: 2,
                borderWidth: 1.8,
                tension: 0.35,
                fill: false,
            },
        ];

        if (
            profitPrev.some(function (v) {
                return Number(v) !== 0;
            })
        ) {
            datasets.push({
                label: "Previous Period Profit",
                data: profitPrev,
                borderColor: "#9aa89a",
                backgroundColor: "transparent",
                borderDash: [6, 4],
                pointRadius: 0,
                borderWidth: 1.6,
                tension: 0.35,
                fill: false,
            });
        }

        if (chart) {
            chart.data.labels = labels;
            chart.data.datasets = datasets;
            chart.update();
        } else {
            chart = new Chart(canvas, {
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
                                font: { size: 11, weight: "600" },
                                color: "#2e4836",
                            },
                        },
                        tooltip: {
                            backgroundColor: "#2e4836",
                            padding: 10,
                            callbacks: {
                                label: function (ctx) {
                                    return (
                                        ctx.dataset.label +
                                        ": ₱" +
                                        Number(ctx.parsed.y || 0).toLocaleString("en-US", {
                                            minimumFractionDigits: 2,
                                        })
                                    );
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
                                maxTicksLimit: 12,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: "rgba(205, 217, 181, 0.45)" },
                            ticks: {
                                color: "#8a9389",
                                font: { size: 10 },
                                callback: function (value) {
                                    var n = Number(value);
                                    if (Math.abs(n) >= 1000000) {
                                        return "₱" + (n / 1000000).toFixed(1) + "M";
                                    }
                                    if (Math.abs(n) >= 1000) {
                                        return "₱" + (n / 1000).toFixed(0) + "k";
                                    }
                                    return "₱" + n;
                                },
                            },
                        },
                    },
                },
            });
        }

        if (window.__hideChartNoData) {
            window.__hideChartNoData("ftChart");
        }
    }

    function renderError() {
        if (chart) {
            chart.destroy();
            chart = null;
        }
        showEmpty(true, "Couldn't load financial trends. Select a period to retry.");
        var meta = document.getElementById("ftMeta");
        if (meta) {
            meta.innerHTML = "";
        }
    }

    function loadFinancialTrends(query) {
        var requestId = ++requestSeq;

        var sub = document.getElementById("ftSub");
        var rangeLabel = "";
        try {
            var match = /(^|[?&])range=([^&]+)/.exec(query || "");
            if (match) {
                rangeLabel = decodeURIComponent(match[2]);
            }
        } catch (e) {}

        if (sub) {
            sub.textContent =
                rangeLabel === "today"
                    ? "Estimated profit and remittance movement for today"
                    : "Estimated profit and remittance movement for the selected period";
        }

        if (window.__dashLoadBegin) window.__dashLoadBegin();

        fetch("/finance/api/dashboard/finance_summary.php?" + (query || ""), { cache: "no-store" })
            .then(function (res) {
                return res.json();
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

    window.loadFinancialTrends = loadFinancialTrends;

    document.addEventListener("DOMContentLoaded", function () {
        if (!window.__financeFilterQuery) {
            loadFinancialTrends("range=year");
        }
    });
})();
</script>

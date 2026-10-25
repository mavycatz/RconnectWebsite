<div class="dash-panel" id="tripTrendPanel">
    <div class="dash-panel-head">
        <div>
            <h2 class="dash-panel-title"><i class="bi bi-activity"></i> Trip Activity Trend</h2>
            <p class="dash-panel-sub" id="tripTrendSub">Trips recorded over the selected period</p>
        </div>
        <div class="dash-panel-meta" id="tripTrendMeta"></div>
    </div>

    <div class="dash-chart-wrap">
        <canvas id="tripTrendChart"></canvas>
        <div class="dash-chart-empty" id="tripTrendEmpty" hidden>No trip activity for this period.</div>
    </div>
</div>

<style>
    .dash-panel {
        background: #fdfbf6;
        border: 1px solid #cdd9b5;
        border-radius: 18px;
        padding: 18px 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 5px rgba(46, 72, 54, 0.06);
    }

    .dash-panel-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .dash-panel-title {
        font-size: 1rem;
        font-weight: 700;
        color: #2e4836;
        margin: 0 0 2px;
    }

    .dash-panel-title i {
        color: #295738;
        margin-right: 6px;
    }

    .dash-panel-sub {
        font-size: 0.72rem;
        color: #8a9389;
        margin: 0;
    }

    .dash-panel-meta {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .dash-pill {
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

    .dash-pill.is-up {
        color: #1c7c3f;
        border-color: #bfe3c6;
        background: #eef8f0;
    }

    .dash-pill.is-down {
        color: #b3261e;
        border-color: #f0c8c4;
        background: #fdf1f0;
    }

    .dash-chart-wrap {
        position: relative;
        height: 300px;
    }

    .dash-chart-empty {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 600;
        color: #8a9389;
        background: #fdfbf6;
    }

    @media(max-width:575px) {
        .dash-chart-wrap {
            height: 240px;
        }
    }
</style>

<script>
    (function () {
        "use strict";

        var chart = null;
        var requestSeq = 0;

        function periodLabel(query) {
            var match = /(^|[?&])range=([^&]+)/.exec(query || "");
            var range = match ? decodeURIComponent(match[2]) : "today";

            if (range === "year") {
                return "Monthly activity for the selected year";
            }
            if (range === "month") {
                return "Daily activity for the selected month";
            }
            if (range === "week") {
                return "Daily activity for the last 7 days";
            }
            if (range === "custom") {
                return "Activity for the selected date range";
            }

            return "Hourly activity for today";
        }

        function metaPill(total, previous) {
            var meta = document.getElementById("tripTrendMeta");
            if (!meta) {
                return;
            }

            meta.innerHTML = "";

            var totalPill = document.createElement("span");
            totalPill.className = "dash-pill";
            totalPill.textContent = Number(total || 0).toLocaleString() + " trips";
            meta.appendChild(totalPill);

            if (Number(previous) <= 0) {
                return;
            }

            var pct = ((Number(total) - Number(previous)) / Number(previous)) * 100;
            var deltaPill = document.createElement("span");
            deltaPill.className = "dash-pill " + (pct >= 0 ? "is-up" : "is-down");
            deltaPill.textContent = (pct >= 0 ? "↑ " : "↓ ") + Math.abs(pct).toFixed(1) + "% vs previous";
            meta.appendChild(deltaPill);
        }

        function ensureChart() {
            if (chart) {
                return chart;
            }

            var canvas = document.getElementById("tripTrendChart");
            if (!canvas) {
                return null;
            }

            chart = new Chart(canvas, {
                type: "line",
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 600, easing: "easeOutQuart" },
                    interaction: { mode: "index", intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                boxWidth: 12,
                                boxHeight: 12,
                                usePointStyle: true,
                                font: { size: 11, weight: "600" },
                                color: "#2e4836"
                            }
                        },
                        tooltip: {
                            backgroundColor: "#2e4836",
                            titleFont: { size: 11 },
                            bodyFont: { size: 11 },
                            padding: 10
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: "#8a9389",
                                font: { size: 10 },
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 12
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: "rgba(205, 217, 181, 0.45)" },
                            ticks: {
                                color: "#8a9389",
                                font: { size: 10 },
                                precision: 0,
                                callback: function (value) {
                                    return Number.isInteger(value) ? value : null;
                                }
                            }
                        }
                    }
                }
            });

            return chart;
        }

        function showEmpty(show) {
            var empty = document.getElementById("tripTrendEmpty");
            var canvas = document.getElementById("tripTrendChart");

            if (empty) {
                empty.hidden = !show;
            }
            if (canvas) {
                canvas.style.visibility = show ? "hidden" : "visible";
            }
        }

        function render(data) {
            var buckets = data.buckets || [];
            var totals = data.totals || {};
            var previousTotal = Number(totals.previous || 0);

            var labels = buckets.map(function (bucket) { return bucket.label; });
            var current = buckets.map(function (bucket) { return Number(bucket.current || 0); });
            var previous = buckets.map(function (bucket) { return Number(bucket.previous || 0); });

            var hasData = current.some(function (value) { return value > 0; })
            || (previousTotal > 0 && previous.some(function (value) { return value > 0; }));

            showEmpty(!hasData);

            if (!hasData) {
                metaPill(0, previousTotal);
                return;
            }

            var instance = ensureChart();
            if (!instance) {
                return;
            }

            instance.data.labels = labels;
            instance.data.datasets = [
            {
                label: "Trips",
                data: current,
                borderColor: "#295738",
                backgroundColor: "rgba(205, 217, 181, 0.45)",
                pointBackgroundColor: "#295738",
                pointRadius: 2,
                pointHoverRadius: 4,
                borderWidth: 2.5,
                tension: 0.35,
                fill: true
            }
            ];

            if (previousTotal > 0) {
                instance.data.datasets.push({
                    label: "Previous period",
                    data: previous,
                    borderColor: "#c4b417",
                    backgroundColor: "transparent",
                    borderDash: [6, 4],
                    pointRadius: 0,
                    pointHoverRadius: 3,
                    borderWidth: 1.8,
                    tension: 0.35,
                    fill: false
                });
            }

            instance.update();
            metaPill(totals.current, previousTotal);
        }

        function renderError() {
            showEmpty(true);

            var empty = document.getElementById("tripTrendEmpty");
            if (empty) {
                empty.textContent = "Couldn't load trip activity. Select a period to retry.";
            }

            metaPill(0, 0);
        }

        function loadTripActivityTrend(query) {
            var requestId = ++requestSeq;
            var sub = document.getElementById("tripTrendSub");

            if (sub) {
                sub.textContent = periodLabel(query);
            }

            if (window.__dashLoadBegin) {
                window.__dashLoadBegin();
            }

            fetch("/admin/api/dashboard/trip-activity-trend.php?" + (query || ""), { cache: "no-store" })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (requestId !== requestSeq) {
                    return;
                }

                if (!data || !data.success) {
                    renderError();
                    return;
                }

                var empty = document.getElementById("tripTrendEmpty");
                if (empty) {
                    empty.textContent = "No trip activity for this period.";
                }

                render(data);
            })
            .catch (function () {
                if (requestId !== requestSeq) {
                    return;
                }
                renderError();
            })
            .finally(function () {
                if (window.__dashLoadEnd) {
                    window.__dashLoadEnd();
                }
            });
        }

        window.loadTripActivityTrend = loadTripActivityTrend;

        document.addEventListener("DOMContentLoaded", function () {
            if (!window.adminFilterQuery) {
                loadTripActivityTrend("");
            }
        });
    })();
</script>

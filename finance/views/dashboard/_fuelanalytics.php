<style>
.fna-wrap {
    margin: 26px 0 6px;
}
.fna-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    gap: 10px;
    flex-wrap: wrap;
}
.fna-title {
    font-size: 13px;
    font-weight: 800;
    color: #295738;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}
.fna-link {
    font-size: 12px;
    font-weight: 700;
    color: #2563eb;
    text-decoration: none;
    white-space: nowrap;
}
.fna-link:hover {
    text-decoration: underline;
}

.fna-kpi {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 13px;
    padding: 16px;
    border-radius: 16px;
    transition: 0.2s;
}
.fna-kpi:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.fna-kpi .fna-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
    margin-top: 2px;
    flex-shrink: 0;
}
.fna-kpi .fna-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}
.fna-kpi .fna-label {
    font-size: 10px;
    font-weight: 700;
    color: #8a9389;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.fna-kpi .fna-value {
    font-size: clamp(10.5px, 0.9vw, 16px);
    font-weight: 800;
    color: #2e4836;
    line-height: 1.15;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.fna-kpi .fna-sub {
    font-size: 10px;
    color: #a0a6a0;
    margin-top: 3px;
    line-height: 1.25;
}
.fna-kpi.is-empty .fna-value {
    color: #b0b9a8;
}
.fna-kpi.error .fna-value {
    color: #c0392b;
}

#fnaKpiGrid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}
@media (max-width: 1100px) {
    #fnaKpiGrid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 480px) {
    #fnaKpiGrid {
        grid-template-columns: 1fr;
    }
}

.fna-chart-card {
    background: #fff;
    border: 1.5px solid #e3eabb;
    border-radius: 16px;
    padding: 14px 16px;
    height: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.fna-chart-card h4 {
    font-size: 11px;
    font-weight: 800;
    color: #295738;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin: 0 0 10px;
}
.fna-chart-box {
    position: relative;
    width: 100%;
    flex: 1 1 auto;
    min-height: 260px;
    overflow: auto;
}
.fna-chart-box canvas {
    width: 100% !important;
    height: 100% !important;
}
.fna-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 200px;
    color: #7a8280;
    font-size: 13px;
    font-weight: 500;
    gap: 8px;
}
.fna-empty i {
    font-size: 1.6rem;
    color: #c9d0c0;
}
</style>

<div class="fna-wrap">
    <div class="fna-head">
        <div class="fna-title">Fuel Expense Analytics</div>
        <a class="fna-link" href="<?= htmlspecialchars($finUrl('fuel-cost-analytics')) ?>">View Full Fuel Report <i class="bi bi-box-arrow-up-right"></i></a>
    </div>

    <div id="fnaKpiGrid">
        <div class="fna-kpi" style="background:#f4faf5;border:1.5px solid #cde8d2;">
            <div class="fna-icon" style="background:#e8f5e9;color:#2e7d32;"><i class="bi bi-fuel-pump"></i></div>
            <div class="fna-info">
                <div class="fna-label">Total Fuel Expense</div>
                <div class="fna-value" id="fnaTotal">₱0.00</div>
                <div class="fna-sub">All recorded fuel purchases</div>
            </div>
        </div>
        <div class="fna-kpi" style="background:#fef8f3;border:1.5px solid #e9dfc5;">
            <div class="fna-icon" style="background:#faf0dc;color:#b7791f;"><i class="bi bi-receipt-cutoff"></i></div>
            <div class="fna-info">
                <div class="fna-label">Avg Expense / Trip</div>
                <div class="fna-value" id="fnaAvgTrip">₱0.00</div>
                <div class="fna-sub">Total fuel cost per round trip</div>
            </div>
        </div>
        <div class="fna-kpi" style="background:#f2f7fd;border:1.5px solid #cfe0f4;">
            <div class="fna-icon" style="background:#e3f2fd;color:#1565c0;"><i class="bi bi-bus-front"></i></div>
            <div class="fna-info">
                <div class="fna-label">Avg Cost / Bus</div>
                <div class="fna-value" id="fnaBusAvg">₱0.00</div>
                <div class="fna-sub">Fuel cost averaged per bus</div>
            </div>
        </div>
        <div class="fna-kpi" style="background:#f3f2f7;border:1.5px solid #dddbe9;">
            <div class="fna-icon" style="background:#ece9f5;color:#5b3fa8;"><i class="bi bi-droplet-half"></i></div>
            <div class="fna-info">
                <div class="fna-label">Total Fuel Used</div>
                <div class="fna-value" id="fnaLiters">0.0 L</div>
                <div class="fna-sub">Combined liters across buses</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6 d-flex">
            <div class="fna-chart-card">
                <h4><i class="bi bi-bar-chart-fill me-1"></i> Fuel Cost per Bus</h4>
                <div class="fna-chart-box" id="fnaBarBox">
                    <div class="fna-empty"><i class="bi bi-fuel-pump"></i>Loading fuel data...</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6 d-flex">
            <div class="fna-chart-card">
                <h4><i class="bi bi-graph-up-arrow me-1"></i> Daily Fuel Cost Trend</h4>
                <div class="fna-chart-box" id="fnaTrendBox">
                    <div class="fna-empty"><i class="bi bi-graph-up-arrow"></i>Loading fuel data...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function fnaMoney(n) {
        return (
            "₱" +
            Number(n || 0).toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
        );
    }
    function fnaLit(n) {
        return (
            Number(n || 0).toLocaleString("en-US", {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1,
            }) + " L"
        );
    }

    var fnaBarChart = null;
    var fnaTrendChart = null;

    function fnaSet(id, val, quiet) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = val;
        el.classList.remove("is-empty", "error");
        if (quiet) el.classList.add("is-empty");
    }

    function fnaRenderBar(rows) {
        var box = document.getElementById("fnaBarBox");
        if (!box) return;
        var list = (rows || [])
            .filter(function (r) {
                return Number(r.total || r.cost || 0) > 0;
            })
            .sort(function (a, b) {
                return Number(b.total || b.cost) - Number(a.total || a.cost);
            });
        if (!list.length) {
            box.innerHTML =
                '<div class="fna-empty"><i class="bi bi-inbox"></i>No fuel cost data available</div>';
            return;
        }
        var shown = list.slice(0, 10);
        box.style.height = shown.length * 42 + 24 + "px";
        box.innerHTML = '<canvas id="fnaBarChart"></canvas>';
        var ctx = document.getElementById("fnaBarChart").getContext("2d");
        if (fnaBarChart) fnaBarChart.destroy();
        fnaBarChart = new Chart(ctx, {
            type: "bar",
            data: {
                labels: shown.map(function (r) {
                    return r.bus_id || "Unknown";
                }),
                datasets: [
                    {
                        label: "Fuel Cost",
                        data: shown.map(function (r) {
                            return Number(r.total || r.cost || 0);
                        }),
                        backgroundColor: "#198754",
                        borderRadius: 6,
                        barThickness: 20,
                    },
                ],
            },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { right: 70 } },
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: "rgba(0,0,0,0.06)" },
                        ticks: {
                            callback: function (v) {
                                return "₱" + Number(v).toLocaleString();
                            },
                            color: "#666",
                            font: { size: 11 },
                        },
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: "#2e4836", font: { size: 11, weight: "600" } },
                    },
                },
            },
        });
    }

    function fnaRenderTrend(days) {
        var box = document.getElementById("fnaTrendBox");
        if (!box) return;
        var data = (days || []).filter(function (d) {
            return d && d.day;
        });
        if (!data.length) {
            box.innerHTML =
                '<div class="fna-empty"><i class="bi bi-inbox"></i>No daily fuel data available</div>';
            return;
        }
        box.style.height = "";
        box.innerHTML = '<canvas id="fnaTrendChart"></canvas>';
        var ctx = document.getElementById("fnaTrendChart").getContext("2d");
        if (fnaTrendChart) fnaTrendChart.destroy();
        fnaTrendChart = new Chart(ctx, {
            type: "line",
            data: {
                labels: data.map(function (r) {
                    return r.day;
                }),
                datasets: [
                    {
                        label: "Fuel Cost (₱)",
                        data: data.map(function (r) {
                            return parseFloat(r.total) || 0;
                        }),
                        borderColor: "#05590c",
                        backgroundColor: "rgba(5,89,12,0.10)",
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: "#05590c",
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: "rgba(0,0,0,0.06)" },
                        ticks: {
                            callback: function (v) {
                                return "₱" + Number(v).toLocaleString();
                            },
                            color: "#666",
                            font: { size: 11 },
                        },
                    },
                    x: {
                        ticks: { color: "#666", font: { size: 10 }, maxTicksLimit: 10 },
                        grid: { display: false },
                    },
                },
            },
        });
    }

    function fnaLoadFuel() {
        fetch("/finance/api/analytics/fuel_cost.php")
            .then(function (r) {
                return r.json();
            })
            .then(function (d) {
                if (!d.success) throw new Error((d && d.message) || "Failed to load fuel data");

                var total = Number(d.total_fuel_all_time || d.total_fuel_month || 0);
                var buses = (d.fuel_per_bus || []).filter(function (b) {
                    return Number(b.total || 0) > 0;
                });
                var busAvg = buses.length ? total / buses.length : 0;

                var hasFuel =
                    total > 0 ||
                    (d.fuel_per_bus || []).some(function (b) {
                        return Number(b.total || 0) > 0;
                    }) ||
                    (d.daily_trend || []).length > 0;

                if (!hasFuel) {
                    fnaSet("fnaTotal", "₱0.00", true);
                    fnaSet("fnaAvgTrip", "₱0.00", true);
                    fnaSet("fnaBusAvg", "₱0.00", true);
                    fnaSet("fnaLiters", "0.0 L", true);
                } else {
                    fnaSet("fnaTotal", fnaMoney(d.total_fuel_all_time));
                    fnaSet("fnaAvgTrip", fnaMoney(d.avg_cost_per_trip));
                    fnaSet("fnaBusAvg", fnaMoney(busAvg));
                    var liters = (d.fuel_per_bus || []).reduce(function (s, b) {
                        return s + (Number(b.liters) || 0);
                    }, 0);
                    fnaSet("fnaLiters", fnaLit(liters));
                }

                fnaRenderBar(d.fuel_per_bus);
                fnaRenderTrend(d.daily_trend);

                window.__setAiPageData &&
                    window.__setAiPageData("fuel_analytics", {
                        total_fuel_cost: d.total_fuel_all_time,
                        avg_cost_per_trip: d.avg_cost_per_trip,
                        fuel_per_bus: d.fuel_per_bus,
                        daily_trend: d.daily_trend,
                    });
            })
            .catch(function (e) {
                console.error("Fuel analytics load failed:", e);
                ["fnaTotal", "fnaAvgTrip", "fnaBusAvg", "fnaLiters"].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) {
                        el.textContent = "—";
                        el.classList.add("error");
                    }
                });
                var bar = document.getElementById("fnaBarBox");
                if (bar)
                    bar.innerHTML =
                        '<div class="fna-empty"><i class="bi bi-exclamation-triangle-fill"></i>Could not load fuel analytics</div>';
                var trend = document.getElementById("fnaTrendBox");
                if (trend)
                    trend.innerHTML =
                        '<div class="fna-empty"><i class="bi bi-exclamation-triangle-fill"></i>Could not load fuel analytics</div>';
            });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", fnaLoadFuel);
    } else {
        fnaLoadFuel();
    }
})();
</script>
<div class="chart-card">
    <div class="chart-card-title">
        <i class="bi bi-graph-up-arrow me-1"></i> Daily Estimated Fuel Cost Trend (30 days)
    </div>
    <canvas id="dailyFuelChart"></canvas>
</div>

<script>
async function loadDailyFuelTrend() {
    try {
        const r = await fetch("/finance/api/analytics/fuel_cost.php");
        const d = await r.json();
        if (!d.success || !d.daily_trend || !d.daily_trend.length) {
            document.getElementById("dailyFuelChart").parentElement.innerHTML =
                '<div style="text-align:center;padding:40px 20px;color:#666;font-weight:600;">No daily fuel data available.</div>';
            return;
        }
        new Chart(document.getElementById("dailyFuelChart"), {
            type: "line",
            data: {
                labels: d.daily_trend.map(function (r) {
                    return r.day;
                }),
                datasets: [
                    {
                        label: "Estimated Fuel Cost (₱)",
                        data: d.daily_trend.map(function (r) {
                            return parseFloat(r.estimated_cost ?? r.total);
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
                                return "₱" + v.toLocaleString();
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
    } catch (e) {
        console.error("Daily Fuel Error:", e);
    }
}
loadDailyFuelTrend();
</script>

<div class="card">
    <div class="card-title"><i class="bi bi-cash-stack me-1"></i> Revenue per Bus</div>
    <div style="flex:1;display:flex;min-height:280px;">
        <canvas id="revenueBusChart" style="width:100%;height:100%;"></canvas>
    </div>
</div>

<script>
async function loadRevenueBus() {
    try {
        const r = await fetch("/finance/api/analytics/fleet_performance.php");
        const d = await r.json();
        if (!d.success || !d.revenue_per_bus || !d.revenue_per_bus.length) {
            document.getElementById("revenueBusChart").parentElement.innerHTML =
                '<div style="text-align:center;padding:40px;color:#8a9a8a;font-weight:600;">No revenue data available.</div>';
            return;
        }
        new Chart(document.getElementById("revenueBusChart"), {
            type: "bar",
            data: {
                labels: d.revenue_per_bus.map(function (r) {
                    return r.bus_id;
                }),
                datasets: [
                    {
                        label: "Revenue (₱)",
                        data: d.revenue_per_bus.map(function (r) {
                            return parseFloat(r.revenue);
                        }),
                        backgroundColor: "#05590c",
                        borderRadius: 6,
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
                            color: "#8a9a8a",
                            font: { size: 11 },
                        },
                    },
                    x: {
                        ticks: { color: "#8a9a8a", font: { size: 10 } },
                        grid: { display: false },
                    },
                },
            },
        });
    } catch (e) {
        console.error("Revenue Bus Error:", e);
    }
}
loadRevenueBus();
</script>

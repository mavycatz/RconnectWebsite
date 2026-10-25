<div class="chart-card">
    <div class="chart-card-title">
        <i class="bi bi-bar-chart-fill me-1"></i> Estimated Fuel Cost by Bus
    </div>
    <canvas id="fuelPerBusChart"></canvas>
</div>

<script>
async function loadFuelPerBus() {
    try {
        const r = await fetch("/finance/api/analytics/fuel_cost.php");
        const d = await r.json();
        if (!d.success || !d.fuel_per_bus || !d.fuel_per_bus.length) {
            document.getElementById("fuelPerBusChart").parentElement.innerHTML =
                '<div style="text-align:center;padding:40px 20px;color:#666;font-weight:600;">No fuel data available.</div>';
            return;
        }
        new Chart(document.getElementById("fuelPerBusChart"), {
            type: "bar",
            data: {
                labels: d.fuel_per_bus.map(function (r) {
                    return r.bus || r.bus_id || "Unknown";
                }),
                datasets: [
                    {
                        label: "Estimated Fuel Cost (₱)",
                        data: d.fuel_per_bus.map(function (r) {
                            return parseFloat(r.estimated_cost ?? r.total);
                        }),
                        backgroundColor: "#198754",
                        borderRadius: 8,
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
                    x: { ticks: { color: "#666", font: { size: 10 } }, grid: { display: false } },
                },
            },
        });
    } catch (e) {
        console.error("Fuel Per Bus Error:", e);
    }
}
loadFuelPerBus();
</script>

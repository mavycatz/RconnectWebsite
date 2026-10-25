<div id="fuelStatsContainer" style="display:flex;gap:20px;margin-bottom:25px;flex-wrap:wrap;">
    <div style="flex:1 1 220px;min-width:0;">
        <div class="stat-card p-3" style="display:flex;align-items:center;justify-content:center;gap:8px;color:#05590c;font-weight:600;font-size:0.9rem;">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            Loading...
        </div>
    </div>
</div>

<script>
async function loadFuelStats() {
    try {
        const r = await fetch("/finance/api/analytics/fuel_cost.php");
        const d = await r.json();
        if (!d.success) throw new Error(d.message);

        var cards = [
            {
                img: "/assets/images/finance/revenueOverview/OrevenueTotal.png",
                label: "Total Estimated Fuel Cost",
                value:
                    "₱" +
                    Number(d.total_estimated_fuel_cost ?? d.total_fuel_all_time).toLocaleString(
                        undefined,
                        { minimumFractionDigits: 2, maximumFractionDigits: 2 },
                    ),
            },
            {
                img: "/assets/images/finance/revenueOverview/OrevenueAvg.png",
                label: "Average Fuel Consumed",
                value:
                    Number(d.avg_fuel_consumed) > 0
                        ? Number(d.avg_fuel_consumed).toFixed(2) + " L"
                        : d.avg_fuel_consumed !== undefined
                          ? "N/A"
                          : d.avg_cost_per_trip
                            ? "₱" + Number(d.avg_cost_per_trip).toLocaleString()
                            : "N/A",
            },
            {
                img: "/assets/images/finance/revenueOverview/OrevenueBus.png",
                label: "Highest Fuel Bus",
                value: d.top_fuel_bus,
            },
            {
                img: "/assets/images/finance/revenueOverview/OrevenuePeak.png",
                label: "Diesel / Liter",
                value:
                    "₱" +
                    Number(d.diesel_price).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }),
            },
        ];

        var html = "";
        cards.forEach(function (c) {
            html +=
                '<div style="flex:1 1 220px;min-width:0;">' +
                '<div class="stat-card p-3" style="height:130px;">' +
                '<h6 style="font-size:15px;margin-bottom:8px;color:#05590c;font-weight:700;">' +
                c.label +
                "</h6>" +
                '<div style="display:flex;align-items:center;gap:12px;">' +
                '<img src="' +
                c.img +
                '" style="width:48px;height:48px;">' +
                '<h3 style="font-size:18px;margin:0;color:#000 !important;font-weight:700;">' +
                c.value +
                "</h3>" +
                "</div>" +
                "</div>" +
                "</div>";
        });

        document.getElementById("fuelStatsContainer").innerHTML = html;
    } catch (e) {
        console.error("Fuel Stats Error:", e);
        document.getElementById("fuelStatsContainer").innerHTML =
            '<div style="flex:1;"><div class="stat-card p-3" style="display:flex;align-items:center;justify-content:center;gap:8px;color:#c62828;font-weight:600;font-size:0.9rem;">' +
            '<i class="bi bi-exclamation-triangle-fill"></i> Failed to load fuel analytics.</div></div>';
    }
}
loadFuelStats();
</script>

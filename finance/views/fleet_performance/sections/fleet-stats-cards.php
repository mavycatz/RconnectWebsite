<style>
.stats-row {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.stats-row .card {
    flex: 1 1 180px;
    min-width: 0;
    max-height: 600px;
    cursor: pointer;
    transition: 0.3s ease;
    background: #faf9e5;
    border: 2px solid #e7e3af;
    border-radius: 14px;
    padding: 14px 16px;
    font-weight: bold;
    position: relative;
}
.stats-row .card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.15);
}
.stats-row h6 {
    font-size: 13px;
    margin-bottom: 6px;
    color: #05590c;
}
.stats-row h3 {
    font-size: 16px;
    margin: 0;
    color: #000 !important;
}
.stats-row img {
    width: 36px;
    height: 36px;
}
</style>

<div id="fleetStatsContainer" class="stats-row">
    <div class="card d-flex align-items-center justify-content-center" style="gap:8px;color:#05590c;">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        Loading...
    </div>
</div>

<script>
async function loadFleetStats() {
    try {
        const r = await fetch("/finance/api/analytics/fleet_performance.php");
        const d = await r.json();
        if (!d.success) throw new Error(d.message);

        var items = [
            {
                img: "/assets/images/finance/revenueOverview/OrevenueBus.png",
                label: "Active Buses",
                value: d.active_buses,
            },
            {
                img: "/assets/images/finance/revenueOverview/OrevenueTotal.png",
                label: "Trips This Month",
                value: d.month_trips,
            },
            {
                img: "/assets/images/finance/revenueOverview/OrevenueAvg.png",
                label: "Avg Passengers/Trip",
                value: d.avg_passengers_per_trip,
            },
            {
                img: "/assets/images/finance/revenueOverview/OrevenuePeak.png",
                label: "Top Route",
                value: d.top_route,
            },
        ];

        var html = "";
        items.forEach(function (c) {
            html +=
                '<div class="card">' +
                '<h6 class="fw-bold">' +
                c.label +
                "</h6>" +
                '<div class="d-flex align-items-center gap-2">' +
                '<img src="' +
                c.img +
                '">' +
                '<h3 class="fw-bold">' +
                c.value +
                "</h3>" +
                "</div>" +
                "</div>";
        });

        document.getElementById("fleetStatsContainer").innerHTML = html;
    } catch (e) {
        console.error(e);
        document.getElementById("fleetStatsContainer").innerHTML =
            '<div class="card d-flex align-items-center justify-content-center" style="gap:8px;color:#c62828;">' +
            '<i class="bi bi-exclamation-triangle-fill"></i> Failed to load.</div>';
    }
}
loadFleetStats();
</script>

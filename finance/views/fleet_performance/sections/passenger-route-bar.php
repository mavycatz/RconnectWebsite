<div class="card">
    <div class="card-title"><i class="bi bi-people-fill me-1"></i> Passenger Volume per Route</div>
    <div style="flex:1;display:flex;min-height:280px;">
        <canvas id="passengerRouteChart" style="width:100%;height:100%;"></canvas>
    </div>
</div>

<div id="routeDetailModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.4);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:20px;max-width:520px;width:90%;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,0.15);position:relative;">
        <button onclick="closeRouteModal()" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:1.6rem;cursor:pointer;color:#999;line-height:1;">&times;</button>
        <h3 id="routeModalTitle" style="color:#05590c;font-weight:700;font-size:1.15rem;margin-bottom:20px;"></h3>
        <div style="display:flex;gap:24px;">
            <div style="flex:1;">
                <div style="font-weight:700;color:#05590c;font-size:0.85rem;margin-bottom:8px;"><i class="bi bi-geo-alt-fill me-1"></i> Top Boarding Points</div>
                <div id="routeBoardingList" style="font-size:0.82rem;color:#555;"></div>
            </div>
            <div style="flex:1;">
                <div style="font-weight:700;color:#05590c;font-size:0.85rem;margin-bottom:8px;"><i class="bi bi-geo me-1"></i> Top Destinations</div>
                <div id="routeDestList" style="font-size:0.82rem;color:#555;"></div>
            </div>
        </div>
        <div id="routeDetailLoader" style="text-align:center;padding:20px;color:#8a9a8a;display:none;"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...</div>
    </div>
</div>

<script>
function closeRouteModal() {
    document.getElementById("routeDetailModal").style.display = "none";
}
document.getElementById("routeDetailModal")?.addEventListener("click", function (e) {
    if (e.target === this) closeRouteModal();
});

async function showRouteDetail(routeName) {
    var modal = document.getElementById("routeDetailModal");
    var title = document.getElementById("routeModalTitle");
    var boardList = document.getElementById("routeBoardingList");
    var destList = document.getElementById("routeDestList");
    var loader = document.getElementById("routeDetailLoader");

    title.innerHTML = '<i class="bi bi-signpost-2 me-1"></i> ' + routeName;
    boardList.innerHTML = "";
    destList.innerHTML = "";
    loader.style.display = "block";
    modal.style.display = "flex";

    try {
        var r = await fetch(
            "/finance/api/analytics/fleet_performance.php?route=" + encodeURIComponent(routeName),
        );
        var d = await r.json();
        loader.style.display = "none";
        if (!d.success) throw new Error(d.message);

        if (d.boarding_points && d.boarding_points.length) {
            boardList.innerHTML = d.boarding_points
                .map(function (b) {
                    return (
                        '<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #e7e3af;"><span>' +
                        b.boarding_point +
                        '</span><span style="font-weight:700;color:#05590c;">' +
                        b.cnt +
                        "</span></div>"
                    );
                })
                .join("");
        } else {
            boardList.innerHTML = '<div style="color:#999;">No data</div>';
        }

        if (d.destinations && d.destinations.length) {
            destList.innerHTML = d.destinations
                .map(function (dd) {
                    return (
                        '<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #e7e3af;"><span>' +
                        dd.destination +
                        '</span><span style="font-weight:700;color:#05590c;">' +
                        dd.cnt +
                        "</span></div>"
                    );
                })
                .join("");
        } else {
            destList.innerHTML = '<div style="color:#999;">No data</div>';
        }
    } catch (e) {
        loader.style.display = "none";
        boardList.innerHTML = '<div style="color:#c62828;">Failed to load.</div>';
    }
}

async function loadPassengerRoute() {
    try {
        const r = await fetch("/finance/api/analytics/fleet_performance.php");
        const d = await r.json();
        if (!d.success || !d.passenger_per_route || !d.passenger_per_route.length) {
            document.getElementById("passengerRouteChart").parentElement.innerHTML =
                '<div style="text-align:center;padding:40px;color:#8a9a8a;font-weight:600;">No route data available.</div>';
            return;
        }
        var chart = new Chart(document.getElementById("passengerRouteChart"), {
            type: "bar",
            data: {
                labels: d.passenger_per_route.map(function (r) {
                    return r.route_name;
                }),
                datasets: [
                    {
                        label: "Passengers",
                        data: d.passenger_per_route.map(function (r) {
                            return parseInt(r.passengers);
                        }),
                        backgroundColor: "#198754",
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: function (e, active) {
                    if (active && active.length) {
                        var route = chart.data.labels[active[0].index];
                        showRouteDetail(route);
                    }
                },
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: "rgba(0,0,0,0.06)" },
                        ticks: { color: "#8a9a8a", font: { size: 11 } },
                    },
                    x: {
                        ticks: { color: "#8a9a8a", font: { size: 10 } },
                        grid: { display: false },
                    },
                },
            },
        });
    } catch (e) {
        console.error("Passenger Route Error:", e);
    }
}
loadPassengerRoute();
</script>

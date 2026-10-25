<?php header('Cache-Control: no-store, no-cache, must-revalidate'); header('Pragma: no-cache'); ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">
<?php require_once __DIR__ . '/../layout/navbarfinance.php'; ?>
<div class="main-content" id="mainContent" style="font-family:'Poppins',sans-serif;background:#eceed9;padding:20px;">
    <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:16px;padding:20px;">

        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
<div>
    <h1 style="font-weight:800;font-size:1.4rem;color:#295738;margin:0 0 2px;">
        Fleet &amp; Route Performance
    </h1>
    <p style="font-weight:500;font-size:0.82rem;color:#8a9a8a;margin:0;">
        Monitor fleet efficiency and route performance metrics
    </p>
</div>
        </div>

        <?php require __DIR__ . '/sections/fleet-stats-cards.php'; ?>

        <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
            <div style="background:#faf9e5;border:2px solid #e7e3af;border-radius:16px;padding:18px;flex:1;min-width:300px;max-height:600px;">
                <div style="font-weight:600;color:#05590c;margin-bottom:10px;font-size:14px;">
                    <i class="bi bi-people-fill me-1"></i> Passenger Volume per Route
                </div>
                <canvas id="passengerRouteChart" style="height:250px !important;"></canvas>
            </div>
            <div style="background:#faf9e5;border:2px solid #e7e3af;border-radius:16px;padding:18px;flex:1;min-width:300px;max-height:600px;">
                <div style="font-weight:600;color:#05590c;margin-bottom:10px;font-size:14px;">
                    <i class="bi bi-cash-stack me-1"></i> Revenue per Bus
                </div>
                <canvas id="revenueBusChart" style="height:250px !important;"></canvas>
            </div>
        </div>

        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div style="background:#faf9e5;border:2px solid #e7e3af;border-radius:16px;padding:18px;flex:1;min-width:300px;max-height:600px;">
                <div style="font-weight:600;color:#05590c;margin-bottom:10px;font-size:14px;">
                    <i class="bi bi-table me-1"></i> Trip Completion Rate by Bus
                </div>
                <div style="overflow:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.82rem;background:white;border:1px solid #e7e3af;border-radius:10px;overflow:hidden;">
                        <thead>
                            <tr style="background:#faf9e5;">
                                <th style="padding:8px 12px;text-align:left;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Bus</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Total</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Completed</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Rate</th>
                            </tr>
                        </thead>
                        <tbody id="completionTableBody">
                            <tr><td colspan="4" style="padding:14px;text-align:center;color:#8a9a8a;font-weight:600;">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                    </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="background:#faf9e5;border:2px solid #e7e3af;border-radius:16px;padding:18px;flex:1;min-width:300px;max-height:600px;">
                <div style="font-weight:600;color:#05590c;margin-bottom:10px;font-size:14px;">
                    <i class="bi bi-people-fill me-1"></i> Rider Type Composition
                </div>
                <canvas id="riderTypeChart" style="height:250px !important;"></canvas>
            </div>
        </div>

        <div style="margin-top:16px;">
            <div style="background:#faf9e5;border:2px solid #e7e3af;border-radius:16px;padding:18px;width:100%;height:380px;">
                <div style="font-weight:600;color:#05590c;margin-bottom:10px;font-size:14px;text-align:center;">
                    <i class="bi bi-credit-card me-1"></i> Payment Method
                </div>
                <canvas id="paymentMethodChart" style="height:320px !important;"></canvas>
            </div>
        </div>

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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
function closeRouteModal() {
    document.getElementById("routeDetailModal").style.display = "none";
}
var routeModal = document.getElementById("routeDetailModal");
if (routeModal)
    routeModal.addEventListener("click", function (e) {
        if (e.target === this) closeRouteModal();
    });

async function showRouteDetail(routeName) {
    var modal = document.getElementById("routeDetailModal");
    document.getElementById("routeModalTitle").innerHTML =
        '<i class="bi bi-signpost-2 me-1"></i> ' + routeName;
    document.getElementById("routeBoardingList").innerHTML = "";
    document.getElementById("routeDestList").innerHTML = "";
    document.getElementById("routeDetailLoader").style.display = "block";
    modal.style.display = "flex";
    try {
        var r = await fetch(
            "/finance/api/analytics/fleet_performance.php?route=" + encodeURIComponent(routeName),
        );
        var d = await r.json();
        document.getElementById("routeDetailLoader").style.display = "none";
        if (!d.success) throw new Error(d.message);
        if (d.boarding_points && d.boarding_points.length) {
            document.getElementById("routeBoardingList").innerHTML = d.boarding_points
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
            document.getElementById("routeBoardingList").innerHTML =
                '<div style="color:#999;">No data</div>';
        }
        if (d.destinations && d.destinations.length) {
            document.getElementById("routeDestList").innerHTML = d.destinations
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
            document.getElementById("routeDestList").innerHTML =
                '<div style="color:#999;">No data</div>';
        }
    } catch (e) {
        document.getElementById("routeDetailLoader").style.display = "none";
        document.getElementById("routeBoardingList").innerHTML =
            '<div style="color:#c62828;">Failed to load.</div>';
    }
}

async function loadPassengerRoute() {
    try {
        const r = await fetch("/finance/api/analytics/fleet_performance.php");
        const d = await r.json();
        if (!d.success || !d.passenger_per_route || !d.passenger_per_route.length) {
            document.getElementById("passengerRouteChart").parentElement.innerHTML =
                '<div style="text-align:center;padding:40px;color:#8a9a8a;font-weight:600;">No route data.</div>';
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
                        borderRadius: 8,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: function (e, active) {
                    if (active && active.length) {
                        showRouteDetail(chart.data.labels[active[0].index]);
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
        console.error(e);
    }
}

async function loadRevenueBus() {
    try {
        const r = await fetch("/finance/api/analytics/fleet_performance.php");
        const d = await r.json();
        if (!d.success || !d.revenue_per_bus || !d.revenue_per_bus.length) {
            document.getElementById("revenueBusChart").parentElement.innerHTML =
                '<div style="text-align:center;padding:40px;color:#8a9a8a;font-weight:600;">No revenue data.</div>';
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
        console.error(e);
    }
}

async function loadTripCompletion() {
    try {
        const r = await fetch("/finance/api/analytics/fleet_performance.php");
        const d = await r.json();
        if (!d.success) throw new Error(d.message);
        const tbody = document.getElementById("completionTableBody");
        if (!d.trip_completion || !d.trip_completion.length) {
            tbody.innerHTML =
                '<tr><td colspan="4" style="padding:20px;text-align:center;color:#8a9a8a;font-weight:600;">No trip data.</td></tr>';
            return;
        }
        tbody.innerHTML = "";
        d.trip_completion.forEach(function (t) {
            var rate =
                t.total_trips > 0 ? Math.round((t.completed_trips / t.total_trips) * 100) : 0;
            var color = rate >= 80 ? "#05590c" : rate >= 50 ? "#d9a441" : "#c62828";
            tbody.innerHTML +=
                '<tr style="border-bottom:1px solid #e7e3af;">' +
                '<td style="padding:10px 12px;color:#05590c;font-weight:600;">' +
                (t.bus_id || "-") +
                "</td>" +
                '<td style="padding:10px 12px;text-align:center;color:#8a9a8a;">' +
                t.total_trips +
                "</td>" +
                '<td style="padding:10px 12px;text-align:center;color:#8a9a8a;">' +
                t.completed_trips +
                "</td>" +
                '<td style="padding:10px 12px;text-align:center;font-weight:700;color:' +
                color +
                ';">' +
                rate +
                "%</td></tr>";
        });
    } catch (e) {
        console.error(e);
    }
}

async function loadRiderComposition() {
    try {
        const r = await fetch("/finance/api/analytics/fleet_performance.php");
        const d = await r.json();
        if (!d.success) throw new Error(d.message);
        var colors = ["#05590c", "#198754", "#81c784", "#d9a441", "#c62828"];
        if (d.passenger_types && d.passenger_types.length) {
            new Chart(document.getElementById("riderTypeChart"), {
                type: "doughnut",
                data: {
                    labels: d.passenger_types.map(function (t) {
                        return t.passenger_type;
                    }),
                    datasets: [
                        {
                            data: d.passenger_types.map(function (t) {
                                return parseInt(t.count);
                            }),
                            backgroundColor: colors.slice(0, d.passenger_types.length),
                            borderWidth: 2,
                            borderColor: "#faf9e5",
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: "60%",
                    plugins: {
                        legend: {
                            position: "bottom",
                            labels: {
                                color: "#8a9a8a",
                                font: { size: 10 },
                                padding: 8,
                                usePointStyle: true,
                                pointStyle: "circle",
                            },
                        },
                    },
                },
            });
        } else {
            document.getElementById("riderTypeChart").parentElement.innerHTML =
                '<div style="text-align:center;padding:20px;color:#8a9a8a;font-weight:600;">No rider data.</div>';
        }
        if (d.payment_methods && d.payment_methods.length) {
            new Chart(document.getElementById("paymentMethodChart"), {
                type: "bar",
                data: {
                    labels: d.payment_methods.map(function (m) {
                        return m.payment_method;
                    }),
                    datasets: [
                        {
                            label: "Transactions",
                            data: d.payment_methods.map(function (m) {
                                return parseInt(m.count);
                            }),
                            backgroundColor: colors.slice(0, d.payment_methods.length),
                            borderRadius: 6,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: "y",
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: {
                            grid: { display: false },
                            ticks: { font: { size: 11, weight: "bold" }, color: "#05590c" },
                        },
                    },
                },
            });
        } else {
            document.getElementById("paymentMethodChart").parentElement.innerHTML =
                '<div style="text-align:center;padding:20px;color:#8a9a8a;font-weight:600;">No payment data.</div>';
        }
    } catch (e) {
        console.error(e);
    }
}

loadPassengerRoute();
loadRevenueBus();
loadTripCompletion();
loadRiderComposition();
</script>

<?php require __DIR__ . '/_aibutton.php'; ?>


<div id="customAlertOverlay" class="custom-alert-overlay">
  <div class="custom-alert-card">
    <div class="custom-alert-header">
      <div class="custom-alert-icon"></div>
      <div class="custom-alert-title"></div>
    </div>
    <div class="custom-alert-body">
      <div class="custom-alert-message"></div>
      <div class="custom-alert-actions">
        <button class="custom-alert-btn"></button>
        <button class="custom-alert-btn-cancel"></button>
      </div>
    </div>
  </div>
</div>
<script src="/public/login/custom-alert.js"></script>

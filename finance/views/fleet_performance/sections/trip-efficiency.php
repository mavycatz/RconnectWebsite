<div class="chart-card" style="margin-bottom:20px;">
    <div class="chart-card-title">
        <i class="bi bi-check-circle-fill me-1"></i> Overall Trip Completion
    </div>
    <div style="text-align:center;padding:10px 0 20px;">
        <div style="position:relative;display:inline-block;">
            <canvas id="completionGauge" style="max-height:200px;max-width:200px;"></canvas>
            <div id="completionPercentLabel" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:1.6rem;font-weight:800;color:#05590c;"></div>
        </div>
    </div>
</div>

<div class="chart-card">
    <div class="chart-card-title">
        <i class="bi bi-trophy-fill me-1"></i> Top Performing Buses
    </div>
    <div style="overflow:auto;max-height:260px;">
        <table style="width:100%;border-collapse:collapse;font-size:0.8rem;background:#fff;border:1px solid #e7e3af;border-radius:12px;overflow:hidden;">
            <thead>
                <tr style="background:#faf9e5;">
                    <th style="padding:8px 12px;text-align:left;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Bus</th>
                    <th style="padding:8px 12px;text-align:center;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Trips</th>
                    <th style="padding:8px 12px;text-align:center;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Completed</th>
                    <th style="padding:8px 12px;text-align:center;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Rate</th>
                </tr>
            </thead>
            <tbody id="efficiencyBody">
                <tr><td colspan="4" style="padding:16px;text-align:center;color:#666;font-weight:600;">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
async function loadTripEfficiency() {
    try {
        const r = await fetch("/finance/api/analytics/fleet_performance.php");
        const d = await r.json();
        if (!d.success) throw new Error(d.message);

        var total = 0,
            completed = 0;
        if (d.trip_completion && d.trip_completion.length) {
            d.trip_completion.forEach(function (t) {
                total += parseInt(t.total_trips) || 0;
                completed += parseInt(t.completed_trips) || 0;
            });
        }
        var rate = total > 0 ? Math.round((completed / total) * 100) : 0;

        document.getElementById("completionPercentLabel").textContent = rate + "%";

        new Chart(document.getElementById("completionGauge"), {
            type: "doughnut",
            data: {
                labels: ["Completed", "Remaining"],
                datasets: [
                    {
                        data: [rate, 100 - rate],
                        backgroundColor:
                            rate >= 80 ? "#05590c" : rate >= 50 ? "#d9a441" : "#c62828",
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "75%",
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
            },
        });

        var tbody = document.getElementById("efficiencyBody");
        if (!d.trip_completion || !d.trip_completion.length) {
            tbody.innerHTML =
                '<tr><td colspan="4" style="padding:16px;text-align:center;color:#666;font-weight:600;">No data.</td></tr>';
            return;
        }
        var sorted = d.trip_completion.slice().sort(function (a, b) {
            var rA = a.total_trips > 0 ? a.completed_trips / a.total_trips : 0;
            var rB = b.total_trips > 0 ? b.completed_trips / b.total_trips : 0;
            return rB - rA;
        });
        tbody.innerHTML = "";
        sorted.slice(0, 6).forEach(function (t) {
            var r = t.total_trips > 0 ? Math.round((t.completed_trips / t.total_trips) * 100) : 0;
            var color = r >= 80 ? "#05590c" : r >= 50 ? "#d9a441" : "#c62828";
            tbody.innerHTML +=
                '<tr style="border-bottom:1px solid #e7e3af;">' +
                '<td style="padding:8px 12px;color:#05590c;font-weight:600;">' +
                (t.bus_id || "-") +
                "</td>" +
                '<td style="padding:8px 12px;text-align:center;color:#666;">' +
                t.total_trips +
                "</td>" +
                '<td style="padding:8px 12px;text-align:center;color:#666;">' +
                t.completed_trips +
                "</td>" +
                '<td style="padding:8px 12px;text-align:center;font-weight:700;color:' +
                color +
                ';">' +
                r +
                "%</td></tr>";
        });
    } catch (e) {
        console.error("Efficiency Error:", e);
    }
}
loadTripEfficiency();
</script>
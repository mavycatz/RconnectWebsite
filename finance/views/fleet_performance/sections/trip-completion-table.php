<div class="card">
    <div class="card-title"><i class="bi bi-table me-1"></i> Trip Completion Rate by Bus</div>
    <div style="flex:1;overflow:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.82rem;background:white;border:1px solid #e7e3af;border-radius:12px;overflow:hidden;">
            <thead>
                <tr style="background:#faf9e5;">
                    <th style="padding:10px 12px;text-align:left;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Bus</th>
                    <th style="padding:10px 12px;text-align:center;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Total</th>
                    <th style="padding:10px 12px;text-align:center;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Completed</th>
                    <th style="padding:10px 12px;text-align:center;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Rate</th>
                </tr>
            </thead>
            <tbody id="completionTableBody">
                <tr><td colspan="4" style="padding:20px;text-align:center;color:#8a9a8a;font-weight:600;">Loading completion data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
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
        console.error("Completion Error:", e);
    }
}
loadTripCompletion();
</script>

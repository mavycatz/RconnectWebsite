<div class="chart-card">
    <div class="chart-card-title">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> Cost Anomalies & Alerts
    </div>
    <div style="flex:1;overflow:auto;min-height:0;">
        <table style="width:100%;border-collapse:collapse;font-size:0.82rem;background:#fff;border:1px solid #e7e3af;border-radius:12px;overflow:hidden;">
            <thead>
                <tr style="background:#faf9e5;">
                    <th style="padding:10px 14px;text-align:left;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Bus</th>
                    <th style="padding:10px 14px;text-align:left;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Category</th>
                    <th style="padding:10px 14px;text-align:right;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Amount</th>
                    <th style="padding:10px 14px;text-align:left;font-weight:700;color:#05590c;border-bottom:2px solid #e7e3af;">Description</th>
                </tr>
            </thead>
            <tbody id="anomalyTableBody">
                <tr><td colspan="4" style="padding:24px;text-align:center;color:#666;font-weight:600;font-size:0.85rem;">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading anomalies...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
async function loadAnomalies() {
    try {
        const r = await fetch("/finance/api/analytics/fuel_cost.php");
        const d = await r.json();
        if (!d.success) throw new Error(d.message);
        const tbody = document.getElementById("anomalyTableBody");
        if (!d.anomalies || !d.anomalies.length) {
            tbody.innerHTML =
                '<tr><td colspan="4" style="padding:24px;text-align:center;color:#666;font-weight:600;font-size:0.85rem;">No anomalies detected.</td></tr>';
            return;
        }
        tbody.innerHTML = "";
        d.anomalies.forEach(function (a) {
            var isHigh = a.cost_level === "high";
            var badge = isHigh
                ? '<span style="background:#c62828;color:#fff;padding:2px 10px;border-radius:999px;font-size:0.65rem;font-weight:700;margin-left:6px;vertical-align:middle;">HIGH</span>'
                : '<span style="background:#d9a441;color:#fff;padding:2px 10px;border-radius:999px;font-size:0.65rem;font-weight:700;margin-left:6px;vertical-align:middle;">ALERT</span>';
            var rowBg = isHigh ? "background:#fff5f5;" : "";
            tbody.innerHTML +=
                '<tr style="border-bottom:1px solid #e7e3af;' +
                rowBg +
                '">' +
                '<td style="padding:10px 14px;color:#05590c;font-weight:600;">' +
                (a.bus_id || "-") +
                " " +
                badge +
                "</td>" +
                '<td style="padding:10px 14px;color:#666;">' +
                (a.category || "-") +
                "</td>" +
                '<td style="padding:10px 14px;text-align:right;font-weight:700;color:#05590c;">₱' +
                Number(a.amount).toLocaleString() +
                "</td>" +
                '<td style="padding:10px 14px;color:#666;">' +
                (a.description || "-") +
                "</td></tr>";
        });
    } catch (e) {
        console.error("Anomalies Error:", e);
    }
}
loadAnomalies();
</script>

<div class="chart-card">
    <div class="chart-card-title">
        <i class="bi bi-pie-chart-fill me-1"></i> Expense Breakdown
    </div>
    <canvas id="expensePieChart" style="max-height:320px;"></canvas>
</div>

<script>
async function loadExpenseBreakdown() {
    try {
        const r = await fetch("/finance/api/analytics/fuel_cost.php");
        const d = await r.json();
        if (!d.success || !d.expense_breakdown || !d.expense_breakdown.length) {
            document.getElementById("expensePieChart").parentElement.innerHTML =
                '<div style="text-align:center;padding:40px 20px;color:#666;font-weight:600;">No expense data available.</div>';
            return;
        }
        var colors = ["#05590c", "#198754", "#81c784", "#ffde59", "#b7c9a8", "#c62828", "#e7e3af"];
        new Chart(document.getElementById("expensePieChart"), {
            type: "doughnut",
            data: {
                labels: d.expense_breakdown.map(function (r) {
                    return r.category;
                }),
                datasets: [
                    {
                        data: d.expense_breakdown.map(function (r) {
                            return parseFloat(r.total);
                        }),
                        backgroundColor: colors.slice(0, d.expense_breakdown.length),
                        borderWidth: 2,
                        borderColor: "#faf9e5",
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "62%",
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            color: "#666",
                            font: { size: 11 },
                            padding: 14,
                            usePointStyle: true,
                            pointStyle: "circle",
                        },
                    },
                },
            },
        });
    } catch (e) {
        console.error("Expense Breakdown Error:", e);
    }
}
loadExpenseBreakdown();
</script>

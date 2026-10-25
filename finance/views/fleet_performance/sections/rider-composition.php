<div class="card mb-3">
    <div class="card-title"><i class="bi bi-people-fill me-1"></i> Rider Type Composition</div>
    <div style="flex:1;display:flex;min-height:200px;">
        <canvas id="riderTypeChart" style="width:100%;height:100%;"></canvas>
    </div>
</div>

<div class="card">
    <div class="card-title"><i class="bi bi-credit-card me-1"></i> Payment Method Distribution</div>
    <div style="flex:1;display:flex;min-height:200px;">
        <canvas id="paymentMethodChart" style="width:100%;height:100%;"></canvas>
    </div>
</div>

<script>
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
                                padding: 12,
                                usePointStyle: true,
                                pointStyle: "circle",
                            },
                        },
                    },
                },
            });
        } else {
            document.getElementById("riderTypeChart").parentElement.innerHTML =
                '<div style="text-align:center;padding:30px;color:#8a9a8a;font-weight:600;">No rider data.</div>';
        }

        if (d.payment_methods && d.payment_methods.length) {
            new Chart(document.getElementById("paymentMethodChart"), {
                type: "doughnut",
                data: {
                    labels: d.payment_methods.map(function (m) {
                        return m.payment_method;
                    }),
                    datasets: [
                        {
                            data: d.payment_methods.map(function (m) {
                                return parseInt(m.count);
                            }),
                            backgroundColor: colors.slice(0, d.payment_methods.length),
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
                                padding: 12,
                                usePointStyle: true,
                                pointStyle: "circle",
                            },
                        },
                    },
                },
            });
        } else {
            document.getElementById("paymentMethodChart").parentElement.innerHTML =
                '<div style="text-align:center;padding:30px;color:#8a9a8a;font-weight:600;">No payment data.</div>';
        }
    } catch (e) {
        console.error("Rider Composition Error:", e);
    }
}
loadRiderComposition();
</script>
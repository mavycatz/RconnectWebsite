<?php if (!defined('APP_LOADED')) exit; ?>
<div class="dashboard-card mb-4">
    <canvas id="revenueChart"></canvas>
</div>

<script>
const sampleData = {
    bus: {
        labels: ["Bus 101", "Bus 102", "Bus 103", "Bus 104"],
        values: [240000, 310000, 190000, 280000],
    },
    route: { labels: ["Route A", "Route B", "Route C"], values: [420000, 350000, 475000] },
    station: { labels: ["Station 1", "Station 2", "Station 3"], values: [390000, 460000, 395000] },
    hour: { labels: ["5AM", "6AM", "7AM", "8AM", "9AM"], values: [9800, 8700, 9200, 7600, 8100] },
};

const revenueChart = new Chart(document.getElementById("revenueChart"), {
    type: "bar",
    data: {
        labels: sampleData.bus.labels,
        datasets: [{ data: sampleData.bus.values, backgroundColor: "#198754" }],
    },
    options: { responsive: true, plugins: { legend: { display: false } } },
});

window.updateRevenueChart = function (type) {
    revenueChart.data.labels = sampleData[type].labels;
    revenueChart.data.datasets[0].data = sampleData[type].values;
    revenueChart.update();
};
</script>
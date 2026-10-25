document.addEventListener("DOMContentLoaded", function () {

    let chart;

    function loadDashboard() {
        fetch('/api/finance/dashboard')
            .then(response => response.json())
            .then(data => {

                document.getElementById('netProfit').innerText =
                    '₱' + data.net_profit.toLocaleString();

                document.getElementById('grossIncome').innerText =
                    '₱' + data.gross_income.toLocaleString();

                document.getElementById('totalExpenses').innerText =
                    '₱' + data.total_expenses.toLocaleString();

                document.getElementById('activeBuses').innerText =
                    data.active_buses;

                document.getElementById('totalRT').innerText =
                    data.total_round_trips;

                if (data.net_profit < 0) {
                    document.getElementById('netProfit').classList.add('text-warning');
                }

                if (chart) {
                    chart.destroy();
                }

                const ctx = document.getElementById('financeChart').getContext('2d');

                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.chart.labels,
                        datasets: [
                            {
                                label: 'Income',
                                data: data.chart.income,
                                borderWidth: 2
                            },
                            {
                                label: 'Expenses',
                                data: data.chart.expenses,
                                borderWidth: 2
                            }
                        ]
                    }
                });

            });
    }

    loadDashboard();

});
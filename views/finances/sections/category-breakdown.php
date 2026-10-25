<div class="finance-card h-100 d-flex flex-column">

    <div class="finance-title" style="font-size:1.4rem;">
        Category Breakdown
    </div>

    <div style="
        flex:1;
        display:flex;
        min-height:350px;
        position:relative;
    ">

        <canvas id="financePie"
                style="
                    width:100% !important;
                    height:100% !important;
                ">
        </canvas>

    </div>

</div>

<script>

    let financePieChart = null;

    async function loadFinancePieChart()
    {
        try {


            const form = document.getElementById('financeFilterForm');

            const formData = new FormData(form);

            const params = new URLSearchParams(formData);


            const response = await fetch(
            '/admin/api/Finances/FinanceOverviewAPI.php?' + params.toString()
            );

            const result = await response.json();

            if (!result.success) {

                throw new Error(result.message || 'Failed to load chart.');
            }

            const data = result.data;


            const chartLabels = [

            'Operations',
            'Maintenance',
            'Cash Revenue',
            'GCash Revenue',
            'Remittance'

            ];

            const chartValues = [

            Number(data.operationsExpenses || 0),

            Number(data.extraExpenses || 0),

            Number(data.cashRevenue || 0),

            Number(data.gcashRevenue || 0),

            Number(data.totalRemittance || 0)

            ];


            if (financePieChart) {

                financePieChart.destroy();
            }


            financePieChart = new Chart(
            document.getElementById('financePie'),
            {
                type: 'doughnut',

                data: {

                    labels: chartLabels,

                    datasets: [{

                        data: chartValues,

                        backgroundColor: [

                        '#096d11',
                        '#ffde59',
                        '#295738',
                        '#d4af37',
                        '#6ba368'

                        ],

                        borderWidth: 0

                    }]
                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    cutout: '65%',

                    plugins: {

                        legend: {

                            position: 'bottom',

                            labels: {

                                color: '#295738',

                                font: {

                                    weight: '600'
                                },

                                padding: 18
                            }
                        },

                        tooltip: {

                            callbacks: {

                                label: function (context) {

                                    let value = context.raw || 0;

                                    return context.label + ': ₱' +
                                    Number(value).toLocaleString();
                                }
                            }
                        }
                    }
                }
            }
            );

        } catch (error) {

            console.error(error);

        }
    }


    loadFinancePieChart();


    document.getElementById('financeFilterForm')
    .addEventListener('submit', function (e) {

        e.preventDefault();

        loadFinanceOverview();

        loadFinancePieChart();

        loadRecentTransactions();

    });

</script>
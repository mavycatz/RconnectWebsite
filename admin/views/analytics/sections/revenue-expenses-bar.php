
<div class="analytics-card d-flex flex-column">

    <div style="
        text-align:center;
        font-size:1.3rem;
        font-weight:700;
        color:#295738;
        margin-bottom:20px;
    ">
        Earnings vs. Expenses
    </div>


    <div
        id="earningsSummary"
        style="
            display:flex;
            justify-content:center;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:16px;
        ">
    </div>


    <div style="
        flex:1;
        display:flex;
        min-height:350px;
    ">
        <canvas
            id="earningsExpensesChart"
            style="
                width:100% !important;
                height:100% !important;
            ">
        </canvas>
    </div>

</div>



<script>

    async function loadEarningsExpenses() {

        try {

            const response =
            await fetch(
            '/admin/api/analytics/earnings_expenses.php'
            );

            if (!response.ok) {

                throw new Error(
                'Failed to load earnings analytics'
                );

            }

            const data =
            await response.json();


            if (!data.success) {

                throw new Error(
                data.message
                );

            }



            const totalEarnings =
            data.earnings.reduce(
            (a, b) =>a+b,
            0
            );

            const totalExpenses =
            data.expenses.reduce(
            (a, b) =>a+b,
            0
            );


            document.getElementById(
            'earningsSummary'
            ).innerHTML = `

            <div style="
                background:#faf9e5;
                border:2px solid #e5e0a4;
                border-radius:999px;
                padding:8px 14px;
                font-size:.85rem;
                font-weight:600;
                color:#295738;
            ">
                Earnings:
                ₱${totalEarnings.toLocaleString()}
            </div>

            <div style="
                background:#faf9e5;
                border:2px solid #e5e0a4;
                border-radius:999px;
                padding:8px 14px;
                font-size:.85rem;
                font-weight:600;
                color:#295738;
            ">
                Expenses:
                ₱${totalExpenses.toLocaleString()}
            </div>

        `;



            new Chart(

            document.getElementById(
            'earningsExpensesChart'
            ),

            {

                type:'bar',

                data: {

                    labels:data.labels,

                    datasets:[

                    {

                        label:'Earnings',

                        data:data.earnings,

                        backgroundColor:'#295738'

                    },

                    {

                        label:'Expenses',

                        data:data.expenses,

                        backgroundColor:'#ffde59'

                    }

                    ]

                },

                options: {

                    responsive:true,

                    maintainAspectRatio:false,

                    plugins: {

                        legend: {

                            labels: {

                                color:'#295738',

                                font: {
                                    weight:'600'
                                }

                            }

                        }

                    },

                    scales: {

                        y: {

                            beginAtZero:true,

                            ticks: {
                                callback:function (value) {

                                    return (
                                    '₱'
                                    +
                                    value.toLocaleString()
                                    );

                                }
                            }

                        }

                    }

                }

            }

            );

        } catch (error) {

            console.error(
            'Earnings Analytics Error:',
            error
            );

        }

    }


    loadEarningsExpenses();

</script>
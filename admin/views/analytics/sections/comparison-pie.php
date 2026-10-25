<div class="analytics-card d-flex flex-column">

    <div style="
        text-align:center;
        font-size:1.3rem;
        font-weight:700;
        color:#295738;
        margin-bottom:20px;
    ">
        Trip Distribution Comparison
    </div>


    <div
        id="tripDistributionSummary"
        style="
            display:flex;
            justify-content:center;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:18px;
        ">
    </div>


    <div style="
        flex:1;
        display:flex;
        min-height:350px;
    ">

        <canvas
            id="comparisonPie"
            style="
                width:100% !important;
                height:100% !important;
            ">
        </canvas>

    </div>

</div>



<script>

    async function loadTripDistributionComparison() {

        try {

            const response =
            await fetch(
            '/admin/api/analytics/trip_distribution.php'
            );

            if (!response.ok) {

                throw new Error(
                'Failed to load trip distribution'
                );

            }

            const data =
            await response.json();


            if (!data.success) {

                throw new Error(
                data.message
                );

            }



            const distribution =
            data.distribution || {};



            if (
            Object.keys(distribution).length === 0
            ) {

                document.getElementById(
                'tripDistributionSummary'
                ).innerHTML = `

                <div style="
                    color:#6c757d;
                    font-weight:600;
                ">
                    No trip distribution data available.
                </div>

            `;

                return;

            }



            const labels =
            Object.keys(distribution);

            const values =
            Object.values(distribution);



            const summary =
            document.getElementById(
            'tripDistributionSummary'
            );

            summary.innerHTML = '';


            labels.forEach((label, index) => {

                summary.innerHTML += `

                <div style="
                    background:#faf9e5;
                    border:2px solid #e5e0a4;
                    border-radius:999px;
                    padding:8px 14px;
                    font-size:.85rem;
                    font-weight:600;
                    color:#295738;
                ">
                    ${label}: ${values[index]}
                </div>

            `;

            });



            new Chart(

            document.getElementById(
            'comparisonPie'
            ),

            {

                type:'doughnut',

                data: {

                    labels:labels,

                    datasets:[{

                        data:values,

                        backgroundColor:[

                        '#295738',
                        '#ffde59',
                        '#7ca982',
                        '#b7c9a8',
                        '#d6e2c3'

                        ],

                        borderWidth:0

                    }]

                },

                options: {

                    responsive:true,

                    maintainAspectRatio:false,

                    cutout:'65%',

                    plugins: {

                        legend: {

                            position:'bottom',

                            labels: {

                                color:'#295738',

                                font: {
                                    weight:'600'
                                }

                            }

                        }

                    }

                }

            }

            );

        } catch (error) {

            console.error(
            'Trip Distribution Error:',
            error
            );

        }

    }


    loadTripDistributionComparison();

</script>
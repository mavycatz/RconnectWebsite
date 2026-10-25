<div class="analytics-card d-flex flex-column">

    <div style="
        text-align:center;
        font-size:1.6rem;
        font-weight:700;
        color:#295738;
        margin-bottom:18px;
    ">
        Passenger Type Distribution
    </div>


    <div
        id="passengerTypeSummary"
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
        min-height:350px;
        position:relative;
    ">
        <canvas
            id="passengerChart"
            style="
                width:100%;
                height:100%;
            ">
        </canvas>
    </div>

</div>

<script>

    async function loadPassengerDistribution() {

        try {

            const response =
            await fetch(
            '/admin/api/analytics/passenger_distribution.php'
            );



            if (!response.ok) {

                const errorText =
                await response.text();

                console.error(
                'HTTP ERROR:',
                response.status,
                errorText
                );

                return;

            }



            const data =
            await response.json();


            console.log(
            'Passenger Distribution:',
            data
            );



            if (!data.success) {

                console.error(
                data.message
                );

                return;

            }



            const passengerCounts =
            data.distribution || {};



            if (
            Object.keys(passengerCounts).length === 0
            ) {

                document.getElementById(
                'passengerTypeSummary'
                ).innerHTML = `

                <div style="
                    color:#6c757d;
                    font-weight:600;
                ">
                    No passenger data available.
                </div>

            `;

                return;

            }



            const labels =
            Object.keys(passengerCounts);

            const values =
            Object.values(passengerCounts);



            const summaryContainer =
            document.getElementById(
            'passengerTypeSummary'
            );

            summaryContainer.innerHTML = '';


            labels.forEach((label, index) => {

                summaryContainer.innerHTML += `

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
            'passengerChart'
            ),

            {

                type:'bar',

                data: {

                    labels:labels,

                    datasets:[{

                        label:'Passengers',

                        data:values,

                        backgroundColor:[
                        '#295738',
                        '#ffde59',
                        '#7ca982',
                        '#b7c9a8',
                        '#d6e2c3',
                        '#6a994e'
                        ],

                        borderRadius:6

                    }]

                },

                options: {

                    indexAxis:'y',

                    responsive:true,

                    maintainAspectRatio:false,

                    plugins: {

                        legend: {
                            display:false
                        },

                        tooltip: {
                            callbacks: {
                                label:function (context) {

                                    return (
                                    context.raw
                                    .toLocaleString()
                                    +
                                    ' passengers'
                                    );

                                }
                            }
                        }

                    },

                    scales: {

                        x: {

                            beginAtZero:true,

                            ticks: {
                                color:'#295738'
                            },

                            grid: {
                                color:'#e5e0a4'
                            }

                        },

                        y: {

                            ticks: {
                                color:'#295738',
                                font: {
                                    weight:'600'
                                }
                            },

                            grid: {
                                display:false
                            }

                        }

                    }

                }

            }

            );

        } catch (error) {

            console.error(
            'Passenger Distribution Error:',
            error
            );

        }

    }


    loadPassengerDistribution();

</script>
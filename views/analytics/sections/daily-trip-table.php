<div class="analytics-card d-flex flex-column">

    <div style="
        text-align:center;
        font-size:1.8rem;
        font-weight:700;
        color:#295738;
        margin-bottom:18px;
    ">
        Daily Trip Summary
    </div>


    <div style="
        flex:1;
        overflow:auto;
    ">

        <table style="
            width:100%;
            border-collapse:collapse;
            font-size:0.95rem;
            background:white;
            border:2px solid #e5e0a4;
            border-radius:12px;
            overflow:hidden;
        ">

            <thead>

                <tr style="
                    background:#faf9e5;
                    border-bottom:2px solid #e5e0a4;
                ">

                    <th style="
                        padding:14px 16px;
                        text-align:left;
                        font-weight:700;
                        color:#295738;
                        border-right:1px solid #e5e0a4;
                    ">
                        Day
                    </th>

                    <th style="
                        padding:14px 16px;
                        text-align:right;
                        font-weight:700;
                        color:#295738;
                    ">
                        Trips
                    </th>

                </tr>

            </thead>


            <tbody id="dailyTripTableBody">

                <tr>

                    <td colspan="2" style="
                        padding:20px;
                        text-align:center;
                        color:#295738;
                        font-weight:600;
                    ">
                        Loading trip summary...
                    </td>

                </tr>

            </tbody>

        </table>

    </div>

</div>



<script>

    async function loadDailyTripSummary() {

        try {

            const response =
            await fetch(
            '/admin/api/analytics/daily_trip_summary.php'
            );

            if (!response.ok) {

                throw new Error(
                'Failed to load trip summary'
                );

            }

            const data =
            await response.json();


            if (!data.success) {

                throw new Error(
                data.message
                );

            }



            const tbody =
            document.getElementById(
            'dailyTripTableBody'
            );

            tbody.innerHTML = '';



            const days =
            data.days;

            const maxTrips =
            data.max_trips;



            Object.entries(days).forEach(

            ([day, trip]) => {

                tbody.innerHTML += `

                    <tr style="
                        border-bottom:1px solid #e5e0a4;
                    ">

                        <td style="
                            padding:12px 16px;
                            color:#295738;
                            border-right:1px solid #e5e0a4;
                        ">
                            ${day}
                        </td>

                        <td style="
                            padding:12px 16px;
                            text-align:right;
                            font-weight:600;
                            color:${
                                trip === maxTrips
                                ? '#096d11'
                                : '#295738'
                            };
                        ">
                            ${trip}
                            ${
                                trip === maxTrips
                                ? '⭐'
                                : ''
                            }
                        </td>

                    </tr>

                `;

            }

            );



            tbody.innerHTML += `

            <tr style="
                background:#eceed9;
                border-top:2px solid #e5e0a4;
            ">

                <td style="
                    padding:14px 16px;
                    font-weight:700;
                    color:#295738;
                    border-right:1px solid #e5e0a4;
                ">
                    Total
                </td>

                <td style="
                    padding:14px 16px;
                    text-align:right;
                    font-weight:700;
                    color:#096d11;
                    font-size:1.1rem;
                ">
                    ${data.total_trips}
                </td>

            </tr>

        `;

        } catch (error) {

            console.error(
            'Daily Trip Summary Error:',
            error
            );

        }

    }


    loadDailyTripSummary();

</script>
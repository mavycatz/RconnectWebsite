<div class="finance-card h-100">

    <div class="finance-title"
         style="font-size:1.4rem;margin-bottom:12px;">
        Fuel Logs Breakdown
    </div>

    <div id="fuelLogsContent">
        <div id="fuelLogsSummary"></div>
        <div id="fuelLogsTable"></div>
    </div>

</div>

<script>

    async function loadFuelLogs()
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

                throw new Error(result.message || 'Failed to load fuel logs.');
            }

            const data = result.data;

            const fuelLogs = data.fuelLogs || {};

            const perBus = fuelLogs.perBus || [];


            document.getElementById('fuelLogsSummary').innerHTML = `

            <div style="
                display:flex;
                gap:10px;
                margin-bottom:12px;
                flex-wrap:wrap;
            ">

                <div style="
                    flex:1;
                    background:#faf9e5;
                    border:1px solid #e5e0a4;
                    border-radius:10px;
                    padding:8px 10px;
                    text-align:center;
                ">

                    <div style="
                        font-size:.8rem;
                        color:#6c757d;
                    ">
                        Total Cost
                    </div>

                    <div style="
                        font-weight:700;
                        color:#096d11;
                    ">
                        ₱${Number(fuelLogs.totalFuelCost || 0).toLocaleString()}
                    </div>

                </div>

                <div style="
                    flex:1;
                    background:#faf9e5;
                    border:1px solid #e5e0a4;
                    border-radius:10px;
                    padding:8px 10px;
                    text-align:center;
                ">

                    <div style="
                        font-size:.8rem;
                        color:#6c757d;
                    ">
                        Total Liters
                    </div>

                    <div style="
                        font-weight:700;
                        color:#295738;
                    ">
                        ${Number(fuelLogs.totalLiters || 0).toLocaleString()} L
                    </div>

                </div>

                <div style="
                    flex:1;
                    background:#faf9e5;
                    border:1px solid #e5e0a4;
                    border-radius:10px;
                    padding:8px 10px;
                    text-align:center;
                ">

                    <div style="
                        font-size:.8rem;
                        color:#6c757d;
                    ">
                        Log Entries
                    </div>

                    <div style="
                        font-weight:700;
                        color:#295738;
                    ">
                        ${Number(fuelLogs.logCount || 0)}
                    </div>

                </div>

            </div>

        `;


            if (perBus.length === 0) {

                document.getElementById('fuelLogsTable').innerHTML = `

                <div style="
                    padding:20px;
                    text-align:center;
                    color:#295738;
                    font-weight:600;
                ">
                    No fuel logs found.
                </div>

            `;

                return;
            }


            let html = `

            <table style="
                width:100%;
                border-collapse:collapse;
            ">

            <thead>

                <tr style="
                    border-bottom:2px solid #e5e0a4;
                    font-size:.8rem;
                    color:#6c757d;
                    text-transform:uppercase;
                ">

                    <th style="padding:8px 12px;text-align:left;">Bus</th>
                    <th style="padding:8px 12px;text-align:right;">Liters</th>
                    <th style="padding:8px 12px;text-align:right;">Cost</th>
                    <th style="padding:8px 12px;text-align:right;">Avg ₱/L</th>
                    <th style="padding:8px 12px;text-align:right;">Logs</th>

                </tr>

            </thead>

            <tbody>

        `;

            perBus.forEach(bus => {

                html += `

                <tr style="
                    border-bottom:1px solid #e5e0a4;
                ">

                    <td style="
                        padding:12px;
                        font-weight:600;
                        color:#295738;
                    ">
                        ${bus.bus_id}
                    </td>

                    <td style="
                        padding:12px;
                        text-align:right;
                        color:#295738;
                    ">
                        ${Number(bus.total_liters || 0).toLocaleString()}
                    </td>

                    <td style="
                        padding:12px;
                        text-align:right;
                        font-weight:600;
                        color:#b02a37;
                    ">
                        ₱${Number(bus.total_cost || 0).toLocaleString()}
                    </td>

                    <td style="
                        padding:12px;
                        text-align:right;
                        color:#6c757d;
                    ">
                        ₱${Number(bus.avg_price_per_liter || 0).toFixed(2)}
                    </td>

                    <td style="
                        padding:12px;
                        text-align:right;
                        color:#6c757d;
                    ">
                        ${Number(bus.log_count || 0)}
                    </td>

                </tr>

            `;
            });

            html += `</tbody></table>`;

            document.getElementById('fuelLogsTable').innerHTML = html;

        } catch (error) {

            console.error(error);

            document.getElementById('fuelLogsTable').innerHTML = `

            <div class="alert alert-danger">
                Failed to load fuel logs.
            </div>

        `;
        }
    }

    loadFuelLogs();

    document.getElementById('financeFilterForm')
    .addEventListener('submit', function (e) {

        e.preventDefault();

        loadFuelLogs();

    });
</script>

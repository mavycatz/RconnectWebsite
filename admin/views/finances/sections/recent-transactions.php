<div class="finance-card h-100">

    <div class="finance-title" 
         style="font-size:1.4rem;margin-bottom:12px;">

        Recent Transactions

    </div>

    <div id="transactionSummary"></div>

    <div id="transactionTable"></div>

</div>

<script>

    async function loadRecentTransactions()
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

                throw new Error(result.message || 'Failed to load transactions.');
            }

            const data = result.data;


            const transactions = data.transactions || [];


            const total = transactions.reduce((sum, t) => {
                return sum + Number(t.amount || 0);
            }, 0);

            const count = transactions.length;

            const average = count > 0 ? total / count : 0;


            document.getElementById('transactionSummary').innerHTML = `

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
                        Total
                    </div>

                    <div style="
                        font-weight:700;
                        color:#096d11;
                    ">
                        ₱${Number(total).toLocaleString()}
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
                        Transactions
                    </div>

                    <div style="
                        font-weight:700;
                        color:#295738;
                    ">
                        ${count}
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
                        Average
                    </div>

                    <div style="
                        font-weight:700;
                        color:#295738;
                    ">
                        ₱${Number(average).toLocaleString()}
                    </div>

                </div>

            </div>

        `;


            if (transactions.length === 0) {

                document.getElementById('transactionTable').innerHTML = `

                <div style="
                    padding:20px;
                    text-align:center;
                    color:#295738;
                    font-weight:600;
                ">
                    No transactions available.
                </div>

            `;

                return;
            }


            let html = `
        
            <table style="
                width:100%;
                border-collapse:collapse;
            ">
        
        `;

            transactions.forEach(t => {

                const isIncome = t.type === 'Income';

                const percent = total > 0
                ? (Number(t.amount) / total) * 100
                : 0;

                html += `

            <tr style="
                border-bottom:1px solid #e5e0a4;
            ">

                <td style="padding:12px;">

                    <!-- TOP -->
                    <div style="
                        display:flex;
                        justify-content:space-between;
                        align-items:center;
                    ">

                        <div>

                            <div style="
                                font-weight:600;
                                color:#295738;
                            ">
                                ${t.name}
                            </div>

                            <div style="
                                font-size:.8rem;
                                color:#6c757d;
                            ">
                                ${t.category}
                            </div>

                        </div>

                        <div style="
                            font-weight:700;
                            color:${isIncome ? '#198754' : '#b02a37'};
                        ">

                            ${isIncome ? '+' : '-'}

                            ₱${Number(t.amount).toLocaleString()}

                        </div>

                    </div>

                    <!-- BREAKDOWN -->
                    <div style="
                        margin-top:10px;
                        background:#faf9e5;
                        border:1px dashed #dee4ba;
                        border-radius:10px;
                        padding:10px;
                        font-size:.75rem;
                        color:#555;
                    ">

                        <div style="
                            display:flex;
                            justify-content:space-between;
                        ">
                            <span>Category</span>
                            <span>${t.category}</span>
                        </div>

                        <div style="
                            display:flex;
                            justify-content:space-between;
                        ">
                            <span>Type</span>
                            <span>${t.type}</span>
                        </div>

                        <div style="
                            display:flex;
                            justify-content:space-between;
                        ">
                            <span>Share</span>
                            <span>${percent.toFixed(1)}%</span>
                        </div>

                        <div style="
                            display:flex;
                            justify-content:space-between;
                        ">
                            <span>Total Transactions</span>
                            <span>${count}</span>
                        </div>

                        <hr style="
                            margin:6px 0;
                            border-color:#e5e0a4;
                        ">

                        <div style="
                            display:flex;
                            justify-content:space-between;
                            font-weight:600;
                            color:#295738;
                        ">

                            <span>Total</span>

                            <span>
                                ₱${Number(t.amount).toLocaleString()}
                            </span>

                        </div>

                    </div>

                </td>

            </tr>

            `;
            });

            html += `</table>`;

            document.getElementById('transactionTable').innerHTML = html;

        } catch (error) {

            console.error(error);

            document.getElementById('transactionTable').innerHTML = `

            <div class="alert alert-danger">

                Failed to load transactions.

            </div>

        `;
        }
    }


    loadRecentTransactions();


    document.getElementById('financeFilterForm')
    .addEventListener('submit', function (e) {

        e.preventDefault();

        loadFinanceOverview();

        loadFinancePieChart();

        loadRecentTransactions();

    });

</script>
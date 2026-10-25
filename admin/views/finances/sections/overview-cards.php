<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<div class="d-flex justify-content-between align-items-center mb-3">

    <div>
        <h1 style="font-weight:800; font-size:1.4rem; color:#295738; margin:0 0 2px;">
        Finance Overview
        </h1>
        <p style="font-weight:500; font-size:0.82rem; color:#8a9a8a; margin:0;">
        Track revenue, expenses, and overall financial performance.
        </p>
    </div>

</div>

<div class="row g-3 mb-4" id="financeCards">

    <div class="col-12">
        <div style="
            background:#fef8f3;
            border:2px solid #e7e3af;
            border-radius:16px;
            padding:20px;
            text-align:center;
            color:#295738;
            font-weight:600;
        ">
            Loading finance overview...
        </div>
    </div>

</div>

<div class="modal fade" id="financeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:20px;border:none;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#dee4ba,#c5d9b2);color:#295738;padding:22px 28px;display:flex;align-items:center;gap:16px;">
                <div style="width:44px;height:44px;border-radius:12px;background:rgba(41,87,56,0.1);display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#295738;" id="financeModalIcon">&#8291;</div>
                <div style="flex:1;">
                    <div id="financeModalTitle" style="font-size:1.15rem;font-weight:700;color:#295738;"></div>
                    <div id="financeModalValue" style="font-size:0.82rem;color:#6c757d;margin-top:2px;"></div>
                </div>
                <div id="financeModalBadge" style="background:rgba(41,87,56,0.1);border-radius:10px;padding:6px 14px;font-size:0.78rem;font-weight:600;white-space:nowrap;color:#295738;"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="opacity:0.6;font-size:0.85rem;filter:none;"></button>
            </div>
            <div class="p-4" id="financeModalBody" style="background:#fcf9f0;"></div>
        </div>
    </div>
</div>

<script>

    var financeDetails = {};

    async function loadFinanceOverview()
    {
        const container = document.getElementById('financeCards');

        try {

            const financeForm = document.getElementById('financeFilterForm');

            let params = new URLSearchParams();

            if (financeForm) {

                const formData = new FormData(financeForm);

                params = new URLSearchParams(formData);
            }

            const controller = new AbortController();
            const timeout = setTimeout(function () { controller.abort(); }, 10000);

            const response = await fetch(
            '/admin/api/Finances/FinanceOverviewAPI.php?' + params.toString(),
            { signal: controller.signal }
            );
            clearTimeout(timeout);

            if (!response.ok) {
                throw new Error('HTTP Error: ' + response.status);
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Failed to load finance data.');
            }

            const data = result.data;

            renderFinanceCards(data);

        } catch (error) {

            console.error('Finance Cards Error:', error);

            container.innerHTML =
            '<div class="col-12">' +
            '<div style="background:#fef8f3;border:2px solid #e7e3af;border-radius:16px;padding:20px;text-align:center;color:#295738;font-weight:600;">' +
            'Finance data unavailable.' +
            '</div>' +
            '</div>';
        }
    }

    function renderFinanceCards(data)
    {
        var container = document.getElementById('financeCards');
        if (!container) return;

        var totalRev = Number(data.totalRevenue || 0);
        var totalExp = Number(data.totalExpenses || 0);
        var netProf = Number(data.netProfit || 0);
        var fuelLogs = data.fuelLogs || {};
        var fuelCost = Number(fuelLogs.totalFuelCost || 0);
        var fuelLitrs = Number(fuelLogs.totalLiters || 0);
        var fuelCount = Number(fuelLogs.logCount || 0);
        var extraExp = Number(data.extraExpenses || 0);
        var opExp = Number(data.operationsExpenses || 0);
        var totalRemit = Number(data.totalRemittance || 0);
        var totalPass = Number(data.totalPassengers || 0);
        var totalTrips = Number(data.totalTrips || 0);

        var fuelExpPct = totalExp > 0 ? Math.round((fuelCost / totalExp) * 100) : 0;

        financeDetails = {
            totalRevenue: {
                title: 'Total Revenue',
                value: '₱' + totalRev.toLocaleString(),
                description: 'Total fare revenue collected from passenger trips across all payment methods, including both Cash and GCash transactions.',
                formula: 'SUM(fare) FROM passenger_trips',
                breakdown:
                '<div style="display:flex;flex-direction:column;gap:8px;">' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Cash Revenue</span><span style="font-weight:600;color:#295738;">₱' + Number(data.cashRevenue || 0).toLocaleString() + '</span></div>' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">GCash Revenue</span><span style="font-weight:600;color:#295738;">₱' + Number(data.gcashRevenue || 0).toLocaleString() + '</span></div>' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Total Passengers</span><span style="font-weight:600;color:#295738;">' + totalPass.toLocaleString() + '</span></div>' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Average Fare</span><span style="font-weight:600;color:#295738;">₱' + Number(data.averageFare || 0).toFixed(2) + '</span></div>' +
                '<hr style="margin:4px 0;border-color:#e7e3af;">' +
                '<div style="display:flex;justify-content:space-between;font-weight:700;color:#295738;"><span>Total Revenue</span><span>₱' + totalRev.toLocaleString() + '</span></div>' +
                '</div>'
            },
            totalExpenses: {
                title: 'Total Expenses',
                value: '₱' + totalExp.toLocaleString(),
                description: 'Total operational expenses including fuel costs, driver commissions, maintenance, toll fees, insurance, and miscellaneous expenses.',
                formula: 'Operations Expenses + Extra Expenses',
                breakdown:
                '<div style="display:flex;flex-direction:column;gap:8px;">' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Fuel & Operations</span><span style="font-weight:600;color:#b02a37;">₱' + opExp.toLocaleString() + '</span></div>' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Maintenance & Toll</span><span style="font-weight:600;color:#b02a37;">₱' + extraExp.toLocaleString() + '</span></div>' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Fuel Logs Cost</span><span style="font-weight:600;color:#b02a37;">₱' + fuelCost.toLocaleString() + '</span></div>' +
                '<hr style="margin:4px 0;border-color:#e7e3af;">' +
                '<div style="display:flex;justify-content:space-between;font-weight:700;color:#295738;"><span>Total Expenses</span><span>₱' + totalExp.toLocaleString() + '</span></div>' +
                '</div>'
            },
            netProfit: {
                title: 'Net Profit',
                value: '₱' + netProf.toLocaleString(),
                description: 'Net earnings after deducting all operational expenses from total collected revenue across the selected period.',
                formula: 'Total Revenue - Total Expenses',
                breakdown:
                '<div style="display:flex;flex-direction:column;gap:8px;">' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Total Revenue</span><span style="font-weight:600;color:#096d11;">₱' + totalRev.toLocaleString() + '</span></div>' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Total Expenses</span><span style="font-weight:600;color:#b02a37;">₱' + totalExp.toLocaleString() + '</span></div>' +
                '<hr style="margin:4px 0;border-color:#e7e3af;">' +
                '<div style="display:flex;justify-content:space-between;font-weight:700;color:' + (netProf >= 0 ? '#096d11' : '#b02a37') + ';"><span>Net Profit</span><span>' + (netProf >= 0 ? '' : '-') + '₱' + Math.abs(netProf).toLocaleString() + '</span></div>' +
                '</div>'
            },
            fuelCost: {
                title: 'Fuel Cost',
                value: '₱' + fuelCost.toLocaleString(),
                description: 'Total fuel expenses tracked through granular fuel log entries recorded per bus by dispatchers and mobile drivers.',
                formula: 'SUM(cost) FROM fuel_logs',
                breakdown:
                '<div style="display:flex;flex-direction:column;gap:8px;">' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Total Cost</span><span style="font-weight:600;color:#b02a37;">₱' + fuelCost.toLocaleString() + '</span></div>' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Total Liters</span><span style="font-weight:600;color:#295738;">' + fuelLitrs.toLocaleString() + ' L</span></div>' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Log Entries</span><span style="font-weight:600;color:#295738;">' + fuelCount.toLocaleString() + '</span></div>' +
                '<div style="display:flex;justify-content:space-between;"><span style="color:#6c757d;">Avg Price / L</span><span style="font-weight:600;color:#295738;">₱' + (fuelLitrs > 0 ? (fuelCost / fuelLitrs).toFixed(2) : '0.00') + '</span></div>' +
                '<hr style="margin:4px 0;border-color:#e7e3af;">' +
                '<div style="display:flex;justify-content:space-between;font-weight:700;color:#295738;"><span>% of Expenses</span><span>' + fuelExpPct + '%</span></div>' +
                '</div>'
            }
        };

        var cards = [
        { label:'Total Revenue', val:'₱' + totalRev.toLocaleString(), desc:'Total fare revenue collected from passenger trips', icon:'bi-cash-stack', key:'totalRevenue' },
        { label:'Total Expenses', val:'₱' + totalExp.toLocaleString(), desc:'Operational expenses including fuel and maintenance', icon:'bi-cart-check-fill', key:'totalExpenses' },
        { label:'Net Profit', val:'₱' + netProf.toLocaleString(), desc:'Net earnings after deducting all expenses', icon:'bi-graph-up-arrow', key:'netProfit' },
        { label:'Fuel Cost', val:'₱' + fuelCost.toLocaleString(), desc:'Fuel tracked from ' + fuelCount + ' log entries across buses', icon:'bi-fuel-pump-fill', key:'fuelCost' }
        ];

        var html = '';

        for (var i = 0; i < cards.length; i++) {
            var c = cards[i];

            html +=
            '<div class="col-lg-3 col-md-6 mb-3">' +
            '<div onclick="openFinanceModal(\'' + c.key + '\')" style="background:#fef8f3;border:2px solid #e7e3af;border-radius:18px;padding:20px 20px 18px;box-shadow:0 4px 12px rgba(0,0,0,0.04);cursor:pointer;transition:all .2s ease;height:100%;display:flex;flex-direction:column;" onmouseover="this.style.transform=\'translateY(-3px)\';this.style.boxShadow=\'0 6px 20px rgba(0,0,0,0.08)\';this.style.borderColor=\'#cdd9b5\'" onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.04)\';this.style.borderColor=\'#e7e3af\'">' +
            '<div class="d-flex align-items-center gap-2 mb-2">' +
            '<div style="width:32px;height:32px;border-radius:10px;background:rgba(41,87,56,0.1);display:flex;align-items:center;justify-content:center;"><i class="bi ' + c.icon + '" style="color:#295738;font-size:0.95rem;"></i></div>' +
            '<div style="font-weight:700;font-size:0.9rem;color:#295738;">' + c.label + '</div>' +
            '</div>' +
            '<div style="font-size:.76rem;color:#8a9a8a;margin-bottom:12px;line-height:1.3;flex-shrink:0;">' + c.desc + '</div>' +
            '<div style="flex:1;display:flex;align-items:flex-end;">' +
            '<span style="font-size:2rem;font-weight:700;color:#295738;line-height:1;">' + c.val + '</span>' +
            '</div>' +
            '</div>' +
            '</div>';
        }

        container.innerHTML = html;
    }

    function openFinanceModal(type)
    {
        var details = financeDetails[type];
        if (!details) return;

        var iconMap = {
            totalRevenue:'<i class="bi bi-cash-stack"></i>',
            totalExpenses:'<i class="bi bi-cart-check-fill"></i>',
            netProfit:'<i class="bi bi-graph-up-arrow"></i>',
            fuelCost:'<i class="bi bi-fuel-pump-fill"></i>'
        };

        document.getElementById('financeModalIcon').innerHTML = iconMap[type] || '';
        document.getElementById('financeModalTitle').innerHTML = details.title;
        document.getElementById('financeModalValue').innerHTML = details.description;
        document.getElementById('financeModalBadge').innerHTML = details.value;

        var bodyEl = document.getElementById('financeModalBody');
        if (!bodyEl) return;

        bodyEl.innerHTML =
        '<div style="display:flex;flex-direction:column;gap:14px;">' +

        '<div style="background:#fef8f3;border:2px solid #e7e3af;border-radius:16px;overflow:hidden;">' +
        '<div style="background:linear-gradient(135deg,#dee4ba,#c5d9b2);padding:10px 16px;font-size:0.75rem;font-weight:700;color:#295738;text-transform:uppercase;letter-spacing:0.5px;">Description</div>' +
        '<div style="padding:14px 16px;font-size:0.88rem;color:#444;line-height:1.7;">' + details.description + '</div>' +
        '</div>' +

        '<div style="background:#fef8f3;border:2px solid #e7e3af;border-radius:16px;overflow:hidden;">' +
        '<div style="background:linear-gradient(135deg,#eceed9,#dee4ba);padding:10px 16px;font-size:0.75rem;font-weight:700;color:#295738;text-transform:uppercase;letter-spacing:0.5px;">Formula</div>' +
        '<div style="padding:14px 16px;">' +
        '<div style="background:#f4f0dc;border:1px solid #e7e3af;border-radius:10px;padding:12px 14px;font-family:\'Courier New\',monospace;font-size:0.85rem;font-weight:600;color:#295738;text-align:center;">' + details.formula + '</div>' +
        '</div>' +
        '</div>' +

        '<div style="background:#fef8f3;border:2px solid #e7e3af;border-radius:16px;overflow:hidden;">' +
        '<div style="background:linear-gradient(135deg,#cdd9b5,#dee4ba);padding:10px 16px;font-size:0.75rem;font-weight:700;color:#295738;text-transform:uppercase;letter-spacing:0.5px;">Computation Breakdown</div>' +
        '<div style="padding:14px 16px;">' +
        '<div style="background:#f4f0dc;border:1px solid #e7e3af;border-radius:12px;padding:14px;">' +
        details.breakdown +
        '</div>' +
        '</div>' +
        '</div>' +

        '</div>';

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var modalEl = document.getElementById('financeModal');
            var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadFinanceOverview();
    });

    var financeForm = document.getElementById('financeFilterForm');

    if (financeForm) {
        financeForm.addEventListener('submit', function (e) {
            e.preventDefault();
            loadFinanceOverview();
            if (typeof loadFinancePieChart === 'function') {
                loadFinancePieChart();
            }
            if (typeof loadRecentTransactions === 'function') {
                loadRecentTransactions();
            }
            if (typeof loadFuelLogs === 'function') {
                loadFuelLogs();
            }
        });
    }

</script>

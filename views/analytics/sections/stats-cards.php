<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<div class="d-flex justify-content-between align-items-center mb-3">

    <div>
        <h1 style="font-weight:800; font-size:1.4rem; color:#295738; margin:0 0 2px;">
        Analytics Overview
        </h1>
        <p style="font-weight:500; font-size:0.82rem; color:#8a9a8a; margin:0;">
        View key insights, performance trends, and operational analytics.
        </p>
    </div>

</div>

<div class="row g-3 mb-4" id="analytics-container">

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
            Loading analytics...
        </div>
    </div>

</div>

<div class="modal fade" id="analyticsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:20px;border:none;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#dee4ba,#c5d9b2);color:#295738;padding:22px 28px;display:flex;align-items:center;gap:16px;">
                <div style="width:44px;height:44px;border-radius:12px;background:rgba(41,87,56,0.1);display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#295738;" id="analyticsModalIcon">&#8291;</div>
                <div style="flex:1;">
                    <div id="analyticsModalTitle" style="font-size:1.15rem;font-weight:700;color:#295738;"></div>
                    <div id="analyticsModalValue" style="font-size:0.82rem;color:#6c757d;margin-top:2px;"></div>
                </div>
                <div id="analyticsModalBadge" style="background:rgba(41,87,56,0.1);border-radius:10px;padding:6px 14px;font-size:0.78rem;font-weight:600;white-space:nowrap;color:#295738;"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="opacity:0.6;font-size:0.85rem;filter:none;"></button>
            </div>
            <div class="p-4" id="analyticsModalBody" style="background:#fcf9f0;"></div>
        </div>
    </div>
</div>

<script>

    var analyticsDetails = {};

    function renderStatCards(data) {

        var container = document.getElementById('analytics-container');
        if (!container) return;

        analyticsDetails = data.details || {};

        analyticsDetails._values = {
            profit_margin: data.profit_margin,
            passengers_per_trip: data.passengers_per_trip,
            fuel_efficiency: data.fuel_efficiency,
            fleet_utilization: data.fleet_utilization
        };

        var cards = [
        { label:"Profit Margin", val:data.profit_margin, desc:"Profit retained from total earnings", icon:"bi-cash-stack", key:"profit_margin", unit:"%" },
        { label:"Passengers / Trip", val:data.passengers_per_trip, desc:"Average passengers per recorded trip", icon:"bi-people-fill", key:"passengers_per_trip", unit:"" },
        { label:"Fuel Efficiency", val:data.fuel_efficiency, desc:"Estimated distance per liter of fuel", icon:"bi-fuel-pump-fill", key:"fuel_efficiency", unit:"km/L" },
        { label:"Fleet Utilization", val:data.fleet_utilization, desc:"Percentage of fleet currently active", icon:"bi-bus-front-fill", key:"fleet_utilization", unit:"%" }
        ];

        var html = '';

        for (var i = 0; i < cards.length; i++) {
            var c = cards[i];
            var pct = Math.min(Number(c.val), 100);
            var greens = ['#295738', '#3a7d4a', '#5a9e6a', '#7dbd8c'];
            html +=
            '<div class="col-lg-3 col-md-6 mb-3">' +
            '<div onclick="openAnalyticsModal(\'' + c.key + '\')" style="background:#fef8f3;border:2px solid #e7e3af;border-radius:18px;padding:20px 20px 18px;box-shadow:0 4px 12px rgba(0,0,0,0.04);cursor:pointer;transition:all .2s ease;height:100%;display:flex;flex-direction:column;" onmouseover="this.style.transform=\'translateY(-3px)\';this.style.boxShadow=\'0 6px 20px rgba(0,0,0,0.08)\';this.style.borderColor=\'#cdd9b5\'" onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.04)\';this.style.borderColor=\'#e7e3af\'">' +
            '<div class="d-flex align-items-center gap-2 mb-2">' +
            '<div style="width:32px;height:32px;border-radius:10px;background:rgba(41,87,56,0.1);display:flex;align-items:center;justify-content:center;"><i class="bi ' + c.icon + '" style="color:#295738;font-size:0.95rem;"></i></div>' +
            '<div style="font-weight:700;font-size:0.9rem;color:#295738;">' + c.label + '</div>' +
            '</div>' +
            '<div style="font-size:.76rem;color:#8a9a8a;margin-bottom:12px;line-height:1.3;flex-shrink:0;">' + c.desc + '</div>' +
            '<div style="flex:1;display:flex;align-items:flex-end;justify-content:space-between;">' +
            '<div>' +
            '<span style="font-size:2rem;font-weight:700;color:#295738;line-height:1;">' + c.val + '</span>' +
            '<span style="font-size:0.82rem;font-weight:600;color:#8a9a8a;margin-left:3px;">' + c.unit + '</span>' +
            '</div>' +
            '<div style="width:58px;height:58px;pointer-events:none;flex-shrink:0;"><canvas id="pieChart' + i + '"></canvas></div>' +
            '</div>' +
            '</div>' +
            '</div>';
        }

        container.innerHTML = html;

        for (var i = 0; i < cards.length; i++) {
            var c = cards[i];
            var pct = Math.min(Number(c.val), 100);
            var canvas = document.getElementById('pieChart' + i);
            if (canvas && typeof Chart !== 'undefined') {
                new Chart(canvas, {
                    type:'doughnut',
                    data: { datasets:[{
                            data:[pct, 100 - pct],
                            backgroundColor:['#295738', '#eceed9'],
                            borderWidth:0,
                            hoverOffset:3
                        }] },
                    options: {
                        cutout:'74%',
                        plugins: { legend: { display:false }, tooltip: { enabled:false } },
                        responsive:true,
                        maintainAspectRatio:false
                    }
                });
            }
        }
    }

    function openAnalyticsModal(type) {

        var details = analyticsDetails[type];
        if (!details) return;

        var iconMap = {
            profit_margin: '<i class="bi bi-cash-stack"></i>',
            passengers_per_trip: '<i class="bi bi-people-fill"></i>',
            fuel_efficiency: '<i class="bi bi-fuel-pump-fill"></i>',
            fleet_utilization: '<i class="bi bi-bus-front-fill"></i>'
        };

        var unitMap = { profit_margin:'%', passengers_per_trip:' /trip', fuel_efficiency:' km/L', fleet_utilization:'%' };
        var vals = analyticsDetails._values || {};
        var rawVal = vals[type];

        document.getElementById('analyticsModalIcon').innerHTML = iconMap[type] || '';
        document.getElementById('analyticsModalTitle').innerHTML = details.title;
        document.getElementById('analyticsModalValue').innerHTML = details.description;
        document.getElementById('analyticsModalBadge').innerHTML = rawVal != null ? rawVal + (unitMap[type] || '') : '';

        var bodyEl = document.getElementById('analyticsModalBody');
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
            var modalEl = document.getElementById('analyticsModal');
            var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    async function loadDashboardAnalytics() {

        var container = document.getElementById('analytics-container');
        if (!container) return;

        try {

            var controller = new AbortController();
            var timeout = setTimeout(function () { controller.abort(); }, 10000);

            var response = await fetch('/admin/api/analytics/dashboard_stats.php', {
                signal: controller.signal
            });
            clearTimeout(timeout);

            if (!response.ok) throw new Error('HTTP ' + response.status);

            var data = await response.json();

            if (!data.success) throw new Error(data.message || 'API error');

            renderStatCards(data);

        } catch (e) {

            console.error('Analytics error:', e);

            if (container) {
                container.innerHTML =
                '<div class="col-12">' +
                '<div style="background:#fef8f3;border:2px solid #e7e3af;border-radius:16px;padding:20px;text-align:center;color:#295738;font-weight:600;">' +
                'Analytics data unavailable.' +
                '</div>' +
                '</div>';
            }
        }
    }

    loadDashboardAnalytics();

</script>
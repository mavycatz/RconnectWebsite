<?php
declare(strict_types = 1);
?>

<style>
    .analytics-section,
    .analytics-section * {
        font-family: 'Poppins', sans-serif;
    }

    .analytics-card {
        background: #fffdf8;
        border: 1px solid #e5e0a4;
        border-radius: 16px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .04);
    }

    .analytics-kpi-row {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 1rem;
    }
    @media(max-width:991.98px) {
        .analytics-kpi-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media(max-width:575.98px) {
        .analytics-kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    .analytics-kpi {
        background: linear-gradient(135deg, #ffffff 0%, #f6f8ec 100%);
        border: 1px solid #e5e0a4;
        border-radius: 16px;
        padding: 18px 18px;
        min-width: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: center;
        height: 100%;
    }

    .analytics-kpi .k-label {
        font-size: 11px;
        font-weight: 600;
        color: #8a9a8a;
        text-transform: uppercase;
        letter-spacing: .4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .analytics-kpi .k-value {
        font-size: clamp(1.05rem, 1.5vw, 1.55rem);
        font-weight: 700;
        color: #295738;
        margin-top: 4px;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.6px;
        white-space: nowrap;
    }

    .analytics-kpi .k-sub {
        font-size: .72rem;
        color: #9aa89a;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .analytics-period-btn {
        border: 1px solid #e5e0a4;
        background: #fffdf8;
        color: #687168;
        border-radius: 10px;
        font-size: .82rem;
        font-weight: 500;
        padding: 7px 14px;
        transition: all .15s ease;
    }

    .analytics-period-btn:hover {
        border-color: #a5d6a7;
        color: #295738;
    }

    .analytics-period-btn.active {
        background: #295738;
        border-color: #295738;
        color: #fff;
    }

    .analytics-table-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .analytics-table {
        margin-bottom: 0;
        border-collapse: collapse;
        min-width: 1080px;
    }
    .analytics-table thead tr.group-row th {
        background: #eef3e6;
        text-align: center;
        font-size: .68rem;
        letter-spacing: .6px;
        color: #5c7a63;
        border-bottom: 1px solid #dcd9b0;
        font-weight: 700;
        text-transform: uppercase;
    }
    .grp-fin { background: #f3eee2 !important; color: #7a6f4f !important; }
    .grp-gas { background: #e6f0f7 !important; color: #2f6db0 !important; }
    .grp-net { background: #295738 !important; color: #ffffff !important; }
    .grp-cap { background: #e9f0f4 !important; color: #4f6a78 !important; }

    .th-net, .net-cell { background: #eef6ee !important; color: #295738 !important; font-weight: 700; }

    .analytics-table th {
        background: #f6f8ec;
        color: #2e4836;
        font-size: .74rem;
        text-transform: uppercase;
        letter-spacing: .4px;
        font-weight: 600;
        border-bottom: 1px solid #e5e0a4;
        vertical-align: middle;
        white-space: nowrap;
    }

    .analytics-table td {
        font-size: .85rem;
        color: #2e4836;
        vertical-align: middle;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .analytics-table tbody tr:hover {
        background: #f9fbee;
    }
    .analytics-table tbody tr:hover td.sticky-col,
    .analytics-table tbody tr:hover td.sticky-right {
        background: #f9fbee;
    }

    .group-sep { border-left: 2px solid #d3dcc9 !important; }
    .group-row .grp-perf { border-left: 2px solid #d3dcc9; }
    .group-row .grp-fin { border-left: 2px solid #d3dcc9; }
    .group-row .grp-gas { border-left: 2px solid #d3dcc9; }
    .group-row .grp-net { border-left: 2px solid #d3dcc9; }
    .group-row .grp-cap { border-left: 2px solid #d3dcc9; }

    .sticky-col {
        position: sticky;
        left: 0;
        z-index: 3;
        background: #fffdf8;
    }
    thead .sticky-col,
    thead tr.group-row th.sticky-col { background: #eef3e6; z-index: 4; }

    .sticky-right {
        position: sticky;
        right: 0;
        z-index: 3;
        background: #fffdf8;
        box-shadow: -8px 0 10px -8px rgba(0, 0, 0, .18);
    }
    thead .sticky-right,
    thead tr.group-row th.sticky-right { background: #eef3e6; z-index: 4; }

    .att-pct {
        font-weight: 700;
        font-size: .95rem;
        color: #295738;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
    }
    .att-sub {
        font-size: .72rem;
        color: #9aa89a;
    }

    .analytics-driver-thumb {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e5e0a4;
    }

    .analytics-section-title {
        font-size: .8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: #8a9a8a;
        margin-bottom: 12px;
    }

    .analytics-note {
        font-size: .78rem;
        color: #6f7b70;
        background: #fffdf3;
        border: 1px solid #ece6cf;
        border-left: 3px solid #c4b417;
        border-radius: 10px;
        padding: 8px 12px;
        line-height: 1.5;
    }

    .grp-fin-note {
        font-size: .62rem;
        font-weight: 600;
        text-transform: none;
        letter-spacing: 0;
        color: #b59a00;
        margin-left: 4px;
    }

    .analytics-report-section {
        background: #fffdf8;
        border: 1px solid #ebe7d4;
        border-radius: 14px;
        padding: 14px 18px;
    }

    .analytics-report-section .ar-label {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: #295738;
        margin-bottom: 6px;
    }

    .analytics-report-section .ar-body {
        font-size: .88rem;
        color: #3a4a3c;
        line-height: 1.6;
    }

    .analytics-chart-box {
        background: #fffdf8;
        border: 1px solid #ebe7d4;
        border-radius: 14px;
        padding: 16px;
        height: 100%;
    }

    .ai-report-body { max-height: 72vh; overflow-y: auto; }

    .ai-sec { margin-bottom: 18px; }
    .ai-sec:last-child { margin-bottom: 0; }
    .ai-sec-title {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: #295738;
        margin-bottom: 8px;
        padding-bottom: 4px;
        border-bottom: 1px solid #ece6cf;
    }

    .ai-overview-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
    }
    @media(max-width:575.98px) {
        .ai-overview-grid { grid-template-columns: repeat(2, 1fr); }
    }
    .ai-ov {
        background: #f6f8ec;
        border: 1px solid #e5e0a4;
        border-radius: 12px;
        padding: 10px 12px;
    }
    .ai-ov .ov-label {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .4px;
        color: #8a9a8a;
    }
    .ai-ov .ov-value {
        font-size: 1.15rem;
        font-weight: 700;
        color: #295738;
        line-height: 1.2;
        margin-top: 2px;
    }
    .ai-ov .ov-sub {
        font-size: .72rem;
        color: #9aa89a;
    }

    .ai-fin-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        padding: 5px 2px;
        border-bottom: 1px dashed #ece6cf;
        font-size: .9rem;
    }
    .ai-fin-row .fin-label { color: #3a4a3c; }
    .ai-fin-row .fin-value {
        font-weight: 600;
        color: #2e4836;
        font-variant-numeric: tabular-nums;
    }
    .ai-fin-total {
        font-weight: 700;
        border-bottom: none;
        border-top: 2px solid #d8d2b0;
        margin-top: 2px;
    }
    .ai-fin-total .fin-label,
    .ai-fin-total .fin-value { color: #295738; font-size: 1rem; }
    .ai-fin-net {
        background: #eef6ee;
        border: 1px solid #cfe3cf;
        border-radius: 10px;
        padding: 8px 10px;
        margin-top: 4px;
    }
    .ai-fin-net .fin-label,
    .ai-fin-net .fin-value { color: #295738; font-weight: 700; font-size: 1.05rem; }

    .ai-cap-pct {
        font-size: 1.05rem;
        font-weight: 700;
        color: #295738;
    }
    .ai-cap-bar {
        height: 10px;
        background: #ece6cf;
        border-radius: 999px;
        overflow: hidden;
    }
    .ai-cap-fill {
        height: 100%;
        background: linear-gradient(90deg, #a5d6a7, #295738);
        border-radius: 999px;
    }

    .ai-analysis-block { margin-bottom: 12px; }
    .ai-analysis-block:last-child { margin-bottom: 0; }
    .ai-analysis-block .ab-title {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: #7a6f4f;
        margin-bottom: 3px;
    }
    .ai-analysis-block .ab-body {
        font-size: .9rem;
        color: #3a4a3c;
        line-height: 1.6;
    }
    .ai-analysis-block.summary {
        background: #f6f8ec;
        border-left: 3px solid #c4b417;
        border-radius: 8px;
        padding: 8px 12px;
    }
</style>

<section class="analytics-section" id="performanceAnalyticsSection"
         style="margin-top:28px;background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div class="d-flex align-items-center gap-2">
            <div style="width:42px;height:42px;border-radius:12px;background:#295738;display:flex;align-items:center;justify-content:center;color:#fff;">
                <i class="bi bi-bar-chart-line"></i>
            </div>
            <div>
                <h5 class="mb-0 fw-bold" style="color:#295738;">Driver Performance Analytics</h5>
                <small class="text-muted">Fleet-wide metrics & per-driver breakdown (DB-backed)</small>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2" id="analyticsPeriodBar">
            <button class="analytics-period-btn" data-period="today" onclick="setAnalyticsPeriod('today')">Today</button>
            <button class="analytics-period-btn" data-period="weekly" onclick="setAnalyticsPeriod('weekly')">Weekly</button>
            <button class="analytics-period-btn active" data-period="monthly" onclick="setAnalyticsPeriod('monthly')">Monthly</button>
            <button class="analytics-period-btn" data-period="yearly" onclick="setAnalyticsPeriod('yearly')">Yearly</button>
            <button class="analytics-period-btn" data-period="custom" onclick="setAnalyticsPeriod('custom')">Custom</button>
            <div id="analyticsCustomWrap" style="display:none;" class="d-flex align-items-center gap-2">
                <input type="date" id="analyticsStart" class="form-control form-control-sm" style="border-radius:10px;border-color:#e5e0a4;">
                <span class="text-muted">to</span>
                <input type="date" id="analyticsEnd" class="form-control form-control-sm" style="border-radius:10px;border-color:#e5e0a4;">
                <button class="btn btn-sm text-white" style="background:#295738;border:none;border-radius:10px;" onclick="applyAnalyticsCustom()">Go</button>
            </div>
        </div>
    </div>

    <div id="analyticsRangeLabel" class="small text-muted mb-3"></div>

    <div class="analytics-kpi-row mb-4" id="analyticsKpis">
        <div class="analytics-kpi"><div class="k-label">Total Trips</div><div class="k-value" id="kpiTrips">—</div><div class="k-sub" id="kpiTripsSub"></div></div>
        <div class="analytics-kpi"><div class="k-label">Passengers</div><div class="k-value" id="kpiPassengers">—</div><div class="k-sub" id="kpiPassengersSub"></div></div>
        <div class="analytics-kpi"><div class="k-label">Other Items</div><div class="k-value" id="kpiOther">—</div><div class="k-sub" id="kpiOtherSub"></div></div>
        <div class="analytics-kpi"><div class="k-label">Total Gross Income</div><div class="k-value" id="kpiFare">—</div><div class="k-sub" id="kpiFareSub"></div></div>
        <div class="analytics-kpi"><div class="k-label">Avg Attendance</div><div class="k-value" id="kpiAtt">—</div><div class="k-sub" id="kpiAttSub"></div></div>
    </div>

    <div class="analytics-section-title">Per-Driver Summary</div>
    <div class="analytics-note mb-2">
        Fixed costs (Amortization, Insurance, GPS, OPTR) are <strong>monthly</strong> operating costs per driver
        (₱1,797.14/month total). They are scaled by the number of months in the selected period and, together with
        the driver's fuel cost (Gas Amount), subtracted from Gross Income to get Net Income
        (e.g. Yearly = ₱1,797.14 × 12 = ₱21,565.68, plus fuel).
    </div>
    <div class="analytics-card p-3 mb-4">
        <div class="analytics-table-scroll">
            <table class="table analytics-table mb-0" id="analyticsTable">
                <thead>
                    <tr class="group-row">
                        <th class="sticky-col" rowspan="2">Driver</th>
                        <th class="grp-perf" colspan="4">Performance</th>
                        <th class="grp-fin" colspan="5">Financial <span class="grp-fin-note">(monthly costs, scaled to period)</span></th>
                        <th class="grp-gas" colspan="2">Gas</th>
                        <th class="grp-net" colspan="1">Net Income</th>
                        <th class="grp-cap" colspan="1">Capacity</th>
                        <th class="sticky-right" rowspan="2">AI Report</th>
                    </tr>
                    <tr>
                        <th class="group-sep">Attendance</th>
                        <th>Trips</th>
                        <th>Passengers</th>
                        <th>Other</th>
                        <th class="group-sep">Gross Income</th>
                        <th title="Monthly fixed cost (₱1,098.00/mo), scaled to the selected period">Amortization</th>
                        <th title="Monthly fixed cost (₱192.89/mo), scaled to the selected period">Insurance</th>
                        <th title="Monthly fixed cost (₱6.25/mo), scaled to the selected period">GPS</th>
                        <th title="Monthly fixed cost (₱500.00/mo), scaled to the selected period">OPTR</th>
                        <th class="group-sep">Liters</th>
                        <th>Amount</th>
                        <th class="group-sep th-net">Net Income</th>
                        <th class="group-sep">Occupancy</th>
                    </tr>
                </thead>
                <tbody id="analyticsTableBody">
                    <tr><td colspan="15" class="text-center py-4 text-muted">Loading analytics…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</section>

<div class="modal fade" id="aiDriverReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0" style="border-radius:22px;background:#fffdf8;box-shadow:0 18px 50px rgba(0,0,0,.1);">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div>
                    <h5 class="mb-1 fw-bold" style="color:#295738;"><i class="bi bi-robot me-2" style="color:#295738;"></i>AI Driver Report</h5>
                    <div class="small text-muted" id="aiReportDriver">—</div>
                    <div class="small text-muted" id="aiReportPeriod">—</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 py-3 ai-report-body">
                <div id="aiReportLoading" class="text-center py-5">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size:1.8rem;color:#c4b417;"></i>
                    <p class="mt-3 text-muted mb-0">Generating AI analysis…</p>
                </div>
                <div id="aiReportContent" style="display:none;">
                    <section class="ai-sec">
                        <h6 class="ai-sec-title">Performance Overview</h6>
                        <div class="ai-overview-grid" id="aiOverview"></div>
                    </section>
                    <section class="ai-sec">
                        <h6 class="ai-sec-title">Financial Performance</h6>
                        <div class="ai-fin" id="aiFinancial"></div>
                    </section>
                    <section class="ai-sec">
                        <h6 class="ai-sec-title">Capacity</h6>
                        <div id="aiCapacity"></div>
                    </section>
                    <section class="ai-sec">
                        <h6 class="ai-sec-title">AI Performance Analysis</h6>
                        <div id="aiAnalysis"></div>
                    </section>
                </div>
                <div id="aiReportError" class="alert alert-danger" style="display:none;"></div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-end gap-2">
                <button type="button" class="btn" data-bs-dismiss="modal" style="height:44px;padding:0 22px;border-radius:12px;background:#fff;border:1px solid #dfe5d1;color:#687168;font-weight:600;">Close</button>
                <button type="button" class="btn" id="aiExportExcelBtn" style="height:44px;padding:0 22px;border-radius:12px;border:1px solid #c9d8c9;background:#fff;color:#295738;font-weight:600;"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</button>
                <button type="button" class="btn text-white" id="aiDownloadPdfBtn" style="height:44px;padding:0 22px;border-radius:12px;border:none;background:#295738;font-weight:600;"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.ANALYTICS_PERIOD = { period: 'monthly', start: '', end: '' };

    const ANALYTICS_COLORS = {
        green: '#295738',
        greenLight: '#a5d6a7',
        amber: '#c4b417',
        red: '#b94a48',
        blue: '#3d6fb4',
        gray: '#cfd8cf'
    };

    function setAnalyticsPeriod(period) {
        window.ANALYTICS_PERIOD.period = period;
        if (period !== 'custom') {
            window.ANALYTICS_PERIOD.start = '';
            window.ANALYTICS_PERIOD.end = '';
            document.getElementById('analyticsCustomWrap').style.display = 'none';
        } else {
            document.getElementById('analyticsCustomWrap').style.display = 'flex';
        }
        document.querySelectorAll('#analyticsPeriodBar .analytics-period-btn').forEach(b => {
            b.classList.toggle('active', b.dataset.period === period);
        });
        loadDriverAnalytics();
    }

    function applyAnalyticsCustom() {
        const s = document.getElementById('analyticsStart').value;
        const e = document.getElementById('analyticsEnd').value;
        if (!s || !e) {
            showAlert('error', 'Dates required', 'Please choose both start and end dates.');
            return;
        }
        window.ANALYTICS_PERIOD.start = s;
        window.ANALYTICS_PERIOD.end = e;
        loadDriverAnalytics();
    }

    function analyticsQuery() {
        const p = window.ANALYTICS_PERIOD;
        const q = 'period=' + encodeURIComponent(p.period);
        if (p.period === 'custom') {
            return q + '&start=' + encodeURIComponent(p.start) + '&end=' + encodeURIComponent(p.end);
        }
        return q;
    }

    function fmtNum(n) {
        return (Number(n) || 0).toLocaleString('en-US');
    }
    function fmtLiters(n) {
        return (Number(n) || 0).toFixed(2) + ' L';
    }
    function fmtPeso(n) {
        return '₱' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function pct(v) {
        if (v === null || v === undefined || v === '') return 'N/A';
        return String(v).replace(/%/g, '') + '%';
    }

    async function loadDriverAnalytics() {
        const body = document.getElementById('analyticsTableBody');
        body.innerHTML = '<tr><td colspan="15" class="text-center py-4 text-muted">Loading analytics…</td></tr>';
        try {
            const res = await fetch('/admin/api/Driver/driver-performance-analytics.php?' + analyticsQuery());
            const json = await res.json();
            if (!json.success) {
                body.innerHTML = '<tr><td colspan="15" class="text-center py-4 text-danger">Failed to load analytics.</td></tr>';
                return;
            }
            renderAnalyticsKpis(json);
            renderAnalyticsTable(json.drivers || []);
            const r = json.range || {};
            document.getElementById('analyticsRangeLabel').textContent =
            'Period: ' + (json.period === 'custom' ? (r.start + ' to ' + r.end) : (json.period.charAt(0).toUpperCase() + json.period.slice(1)));
        } catch (err) {
            console.error(err);
            body.innerHTML = '<tr><td colspan="15" class="text-center py-4 text-danger">Something went wrong.</td></tr>';
        }
    }

    function renderAnalyticsKpis(json) {
        const drivers = json.drivers || [];
        let trips = 0, passengers = 0, other = 0, fare = 0, present = 0, scheduled = 0, occPass = 0, cap = 0;
        drivers.forEach(d => {
            trips += d.trips.total;
            passengers += d.passengers.total;
            other += d.other.records;
            fare += (d.gross_income !== undefined ? d.gross_income : d.fare.total);
            present += d.attendance.present;
            scheduled += d.attendance.scheduled;
            occPass += d.capacity.occupied_passenger;
            cap += d.capacity.vehicle_capacity;
        });
        const attRate = scheduled > 0 ? ((present / scheduled) * 100).toFixed(1) : '0.0';
        document.getElementById('kpiTrips').textContent = fmtNum(trips);
        document.getElementById('kpiTripsSub').textContent = drivers.length + ' active drivers';
        document.getElementById('kpiPassengers').textContent = fmtNum(passengers);
        document.getElementById('kpiPassengersSub').textContent = 'people served';
        document.getElementById('kpiOther').textContent = fmtNum(other);
        document.getElementById('kpiOtherSub').textContent = 'cargo records';
        document.getElementById('kpiFare').textContent = fmtPeso(fare);
        document.getElementById('kpiFareSub').textContent = 'total collected';
        document.getElementById('kpiAtt').textContent = pct(attRate);
        document.getElementById('kpiAttSub').textContent = 'Average driver attendance';
    }

    function renderAnalyticsTable(drivers) {
        const body = document.getElementById('analyticsTableBody');
        if (!drivers.length) {
            body.innerHTML = '<tr><td colspan="15" class="text-center py-4 text-muted">No driver data for this period.</td></tr>';
            return;
        }
        body.innerHTML = '';
        drivers.forEach(d => {
            const tr = document.createElement('tr');
            const photo = (d.photo && d.photo !== 'null') ? d.photo : '/assets/images/admin/driversinfo/drivericon.png';
            const photoSrc = photo.startsWith('/') ? photo : ('/uploads/drivers/photos/' + photo);
            const fc = d.fixed_costs || { amortization: 0, insurance: 0, gps: 0, optr: 0 };
            tr.innerHTML =
            '<td class="sticky-col"><div class="d-flex align-items-center gap-2">' +
            '<img src="' + photoSrc + '" class="analytics-driver-thumb" onerror="this.onerror=null;this.src=\'/assets/images/admin/driversinfo/drivericon.png\';">' +
            '<div><div style="font-weight:600;">' + (d.name || 'Unknown') + '</div>' +
            '<div class="small text-muted">' + (d.driver_id || '') + '</div></div>' +
            '</div></td>' +
            '<td><div class="att-pct">' + pct(d.attendance.rate) + '</div><div class="att-sub">' + d.attendance.present + ' / ' + d.attendance.scheduled + ' days</div></td>' +
            '<td>' + fmtNum(d.trips.total) + '</td>' +
            '<td>' + fmtNum(d.passengers.total) + '</td>' +
            '<td>' + fmtNum(d.other.records) + '</td>' +
            '<td>' + fmtPeso(d.gross_income !== undefined ? d.gross_income : d.fare.total) + '</td>' +
            '<td>' + fmtPeso(fc.amortization) + '</td>' +
            '<td>' + fmtPeso(fc.insurance) + '</td>' +
            '<td>' + fmtPeso(fc.gps) + '</td>' +
            '<td>' + fmtPeso(fc.optr) + '</td>' +
            '<td>' + fmtLiters(d.gas ? d.gas.liters : 0) + '</td>' +
            '<td>' + fmtPeso(d.gas ? d.gas.amount : 0) + '</td>' +
            '<td class="net-cell">' + fmtPeso(d.net_income !== undefined ? d.net_income : 0) + '</td>' +
            '<td>' + (d.capacity.occupancy == null ? 'N/A' : pct(d.capacity.occupancy)) + '</td>' +
            '<td class="sticky-right"><button class="btn btn-sm" onclick="openAiReport(\'' + d.driver_id + '\')" style="background:#295738;color:#fff;border:none;border-radius:10px;font-weight:600;white-space:nowrap;"><i class="bi bi-robot me-1"></i>AI Report</button></td>';
            body.appendChild(tr);
        });
    }

    let aiReportDriverId = null;

    async function openAiReport(driverId) {
        aiReportDriverId = driverId;
        document.getElementById('aiReportLoading').style.display = 'block';
        document.getElementById('aiReportContent').style.display = 'none';
        document.getElementById('aiReportError').style.display = 'none';

        const periodLabel = (window.ANALYTICS_PERIOD.period === 'custom'
        ? (window.ANALYTICS_PERIOD.start + ' to ' + window.ANALYTICS_PERIOD.end)
        : (window.ANALYTICS_PERIOD.period.charAt(0).toUpperCase() + window.ANALYTICS_PERIOD.period.slice(1)));
        document.getElementById('aiReportDriver').textContent = 'Driver: ' + driverId;
        document.getElementById('aiReportPeriod').textContent = 'Period: ' + periodLabel;

        new bootstrap.Modal(document.getElementById('aiDriverReportModal')).show();

        try {
            const res = await fetch('/admin/api/Driver/ai-driver-report.php?driver_id=' + encodeURIComponent(driverId) + '&' + analyticsQuery());
            const json = await res.json();
            if (!json.success) {
                document.getElementById('aiReportLoading').style.display = 'none';
                const err = document.getElementById('aiReportError');
                err.style.display = 'block';
                err.textContent = json.message || json.error || 'Failed to generate report.';
                return;
            }
            renderAiReport(json);
        } catch (err) {
            console.error(err);
            document.getElementById('aiReportLoading').style.display = 'none';
            const e = document.getElementById('aiReportError');
            e.style.display = 'block';
            e.textContent = 'Something went wrong while generating the report.';
        }
    }

    function ovCell(label, value, sub) {
        return '<div class="ai-ov"><div class="ov-label">' + label + '</div>' +
        '<div class="ov-value">' + value + '</div>' +
        (sub ? '<div class="ov-sub">' + sub + '</div>' : '') + '</div>';
    }
    function finRow(label, value) {
        return '<div class="ai-fin-row"><span class="fin-label">' + label + '</span><span class="fin-value">' + value + '</span></div>';
    }

    function renderAiReport(json) {
        document.getElementById('aiReportLoading').style.display = 'none';
        document.getElementById('aiReportContent').style.display = 'block';

        const m = json.metrics || {};
        const a = json.analysis || {};

        const fc = m.fixed_costs || { amortization: 0, insurance: 0, gps: 0, optr: 0, total: 0 };
        const gross = (m.gross_income !== undefined ? m.gross_income : (m.fare ? m.fare.total : 0));
        const net = (m.net_income !== undefined ? m.net_income : 0);
        const gas = m.gas || { liters: 0, amount: 0 };

        document.getElementById('aiReportDriver').textContent =
        'Driver: ' + (m.name || 'Unknown') + (m.driver_id ? ' (' + m.driver_id + ')' : '');

        const att = m.attendance || { present: 0, scheduled: 0, rate: '0%' };
        const tr = m.trips || { total: 0 };
        const pa = m.passengers || { total: 0 };
        const ot = m.other || { records: 0 };
        document.getElementById('aiOverview').innerHTML =
        ovCell('Attendance', att.present + ' / ' + att.scheduled, pct(att.rate)) +
        ovCell('Trips', fmtNum(tr.total), '') +
        ovCell('Passengers', fmtNum(pa.total), '') +
        ovCell('Other Items', fmtNum(ot.records), '');

        let fin = '';
        fin += finRow('Gross Income', fmtPeso(gross));
        fin += finRow('Amortization', fmtPeso(fc.amortization));
        fin += finRow('Insurance', fmtPeso(fc.insurance));
        fin += finRow('GPS', fmtPeso(fc.gps));
        fin += finRow('OPTR', fmtPeso(fc.optr));
        fin += finRow('Gas', fmtLiters(gas.liters) + ' &middot; ' + fmtPeso(gas.amount));
        fin += '<div class="ai-fin-row ai-fin-total"><span class="fin-label">Total Expenses</span><span class="fin-value">' + fmtPeso(fc.total + gas.amount) + '</span></div>';
        fin += '<div class="ai-fin-row ai-fin-net"><span class="fin-label">Net Income</span><span class="fin-value">' + fmtPeso(net) + '</span></div>';
        document.getElementById('aiFinancial').innerHTML = fin;

        const occ = (m.capacity && m.capacity.occupancy != null) ? m.capacity.occupancy : null;
        const occVal = (occ == null) ? 'N/A' : pct(occ);
        const occNum = (occ == null) ? 0 : Math.min(100, parseFloat(occ));
        document.getElementById('aiCapacity').innerHTML =
        '<div class="d-flex align-items-center gap-2 mb-1"><span class="ai-cap-pct">' + occVal + '</span><span class="small text-muted">occupancy</span></div>' +
        '<div class="ai-cap-bar"><div class="ai-cap-fill" style="width:' + occNum + '%"></div></div>';

        const blocks = [
        ['summary', 'Summary', a.summary],
        ['strengths', 'Strengths', a.strengths],
        ['watch_outs', 'Watch Outs', a.watch_outs],
        ['key_findings', 'Key Findings', a.key_findings],
        ['recommendation', 'Recommendation', a.recommendation],
        ];
        document.getElementById('aiAnalysis').innerHTML = blocks.map(b =>
        '<div class="ai-analysis-block ' + b[0] + '"><div class="ab-title">' + b[1] + '</div><div class="ab-body">' + (b[2] || '—') + '</div></div>'
        ).join('');
    }

    function aiExportUrl(format) {
        return '/admin/api/Driver/driver-report-export.php?format=' + format + '&driver_id=' + encodeURIComponent(aiReportDriverId) + '&' + analyticsQuery();
    }

    document.getElementById('aiDownloadPdfBtn').addEventListener('click', function () {
        if (!aiReportDriverId) return;
        this.disabled = true;
        fetch(aiExportUrl('pdf'))
        .then(r => r.json())
        .then(j => {
            if (j.success && j.url) {
                window.open(j.url, '_blank');
            } else {
                alert(j.message || j.error || 'Failed to generate PDF.');
            }
        })
        .catch (() => alert('Failed to generate PDF.'))
        .finally(() => { this.disabled = false; });
    });
    document.getElementById('aiExportExcelBtn').addEventListener('click', function () {
        if (!aiReportDriverId) return;
        this.disabled = true;
        fetch(aiExportUrl('excel'))
        .then(r => r.json())
        .then(j => {
            if (j.success && j.url) {
                window.open(j.url, '_blank');
            } else {
                alert(j.message || j.error || 'Failed to generate Excel.');
            }
        })
        .catch (() => alert('Failed to generate Excel.'))
        .finally(() => { this.disabled = false; });
    });

    document.addEventListener('DOMContentLoaded', loadDriverAnalytics);
</script>

<style>
    :root {
        --accent: #2e7d32;
        --cream-bg: #fef8f3;
        --card-border: #e5e0a4;
        --input-border: #dce3ce;
        --text-dark: #2e4836;
        --radius-card: 1rem;
        --radius-input: 12px;
        --touch-min: 44px;
    }

    .schedule-section h5 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-dark);
    }
    .schedule-section .info-banner {
        background: linear-gradient(135deg, #fff9dc 0%, #fffbe6 100%);
        border: 1px solid #e5e0a4;
        border-radius: 14px;
        font-size: .95rem;
        box-shadow: 0 3px 10px rgba(46,125,50,.05);
    }

    .schedule-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem 1rem;
        background: #fff;
        border: 1px solid #eee;
        border-radius: var(--radius-card);
        text-align: center;
        min-height: 200px;
    }
    .schedule-state .state-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
    .schedule-state .state-text { font-size: 1rem; color: #687168; margin-bottom: 1rem; }
    .schedule-state .state-retry {
        min-height: var(--touch-min);
        border-radius: var(--radius-input);
        border: none;
        background: var(--accent);
        color: #fff; font-weight: 600;
        padding: 10px 24px; cursor: pointer;
    }
    .spinner-custom {
        display: inline-block;
        width: 1.5rem; height: 1.5rem;
        border: 3px solid #e0e0e0;
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .schedule-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 16px;
        border: 1px solid #d7d1a3;
        background: linear-gradient(180deg, #fffdf8 0%, #fffefb 100%);
        box-shadow: 0 6px 18px rgba(41,87,56,.08);
        width: 100%;
    }
    .schedule-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
        table-layout: auto;
    }
    .schedule-table thead th {
        background: linear-gradient(90deg, #f5f1d6 0%, #f9f4da 100%);
        color: #2e4836;
        padding: 12px 10px;
        font-weight: 700;
        text-align: left;
        white-space: nowrap;
        font-size: 0.78rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        border-bottom: 2px solid #d7d1a3;
    }
    .schedule-table tbody tr { border-bottom: 1px solid #f0ecdc; transition: all 0.15s ease; }
    .schedule-table tbody tr:hover { background: linear-gradient(90deg, #faf6e3 0%, #f5f0df 100%); }
    .schedule-table tbody td { padding: 8px 8px; text-align: left; vertical-align: middle; color: #2e4836; line-height: 1.4; }
    .schedule-table tbody td:last-child { text-align: center; white-space: nowrap; }
    .schedule-table     /* removed */ .xchip{
        display: inline-flex; align-items: center;
        padding: 4px 10px; border-radius: 999px;
        font-size: 0.8rem; font-weight: 600;
        border: 1px solid #e5e0a4; background: #fffdf8;
        color: #2e4836; white-space: nowrap;
        box-shadow: 0 2px 6px rgba(0,0,0,.05);
    }
    
    .badge-available { background: linear-gradient(135deg, #f1f8e9 0%, #dcedc8 100%); border: 2px solid #a5d6a7; color: #1b5e20; }
    .badge-ontrip { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #81c784; color: #0d4713; }
    .badge-onbreak { background: linear-gradient(135deg, #fffde7 0%, #fff9c4 100%); border: 2px solid #ffd54f; color: #ef6c00; }
    .badge-offduty { background: linear-gradient(135deg, #f5f5f5 0%, #eeeeee 100%); border: 2px solid #bdbdbd; color: #424242; }
    .badge-status {
        display: inline-block; padding: 6px 12px; border-radius: 999px;
        font-size: 0.8rem; font-weight: 700; white-space: nowrap;
        box-shadow: 0 2px 6px rgba(0,0,0,.08);
    }
    .badge-active { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); color: #1b5e20; border: 1px solid #a5d6a7; }
    .badge-inactive { background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); color: #616161; border: 1px solid #bdbdbd; }
    .schedule-table .btn-view {
        min-height: 32px; border-radius: 8px; border: none;
        background: var(--accent); color: #fff; font-weight: 600;
        padding: 5px 14px; font-size: 0.75rem; cursor: pointer;
    }
    .schedule-table .btn-view:hover { opacity: 0.85; }

    .schedule-pagination {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 16px; background: #f9f4da;
        border-top: 1px solid #e5e0a4; font-size: 0.85rem; color: #2e4836;
        flex-wrap: wrap; gap: 8px;
    }
    .schedule-pagination .btn-link-pag {
        background: none; border: none; color: var(--accent);
        font-weight: 600; cursor: pointer; padding: 6px 12px;
        min-height: var(--touch-min); font-size: 0.85rem;
    }
    .schedule-pagination .btn-link-pag:hover { text-decoration: underline; }
    .schedule-pagination .btn-link-pag:disabled { color: #bbb; cursor: default; text-decoration: none; }

    .schedule-cards-mobile { display: none; }

    @media(max-width:767.98px) {
        .schedule-table-wrap { display: none; }
        .schedule-cards-mobile { display: flex; flex-direction: column; gap: 0.75rem; }
        .schedule-section .btn, .schedule-section button,
        .schedule-section input, .schedule-section select { min-height: var(--touch-min); }
    }@media(max-width:575.98px) {
        .schedule-pagination { flex-direction: column; text-align: center; }
    }

    .schedule-modal .modal-content {
        border-radius: 16px; border: none; background: var(--cream-bg);
    }
    .schedule-modal .modal-header {
        background: var(--accent); color: #fff;
        border-radius: 16px 16px 0 0; border: none; padding: 1rem 1.25rem;
    }
    .schedule-modal .modal-header .btn-close { filter: brightness(0) invert(1); }
    .schedule-modal .modal-body { padding: 1.5rem; }
    .schedule-modal .modal-footer {
        border: none; padding: 0 1.5rem 1.5rem;
        display: flex; gap: 0.75rem; justify-content: stretch;
    }
    .schedule-modal .info-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;
        margin-bottom: 1.25rem;
    }
    .schedule-modal .info-item {
        background: #fff; border: 1px solid #eee;
        border-radius: 10px; padding: 0.6rem 0.9rem;
    }
    .schedule-modal .info-item .i-label {
        font-size: 0.65rem; text-transform: uppercase;
        letter-spacing: 0.03em; color: #888; margin-bottom: 0.1rem;
    }
    .schedule-modal .info-item .i-value {
        font-size: 0.9rem; font-weight: 600; color: #222;
    }
    .schedule-modal .badge-status {
        display: inline-block; padding: 2px 10px; border-radius: 20px;
        font-size: 0.75rem; font-weight: 600;
    }
    .schedule-modal .badge-active { background: #e8f5e9; color: var(--accent); }
    .schedule-modal .badge-inactive { background: #fbe9e7; color: #c62828; }
    .schedule-modal .form-label {
        font-weight: 600; color: var(--text-dark);
        font-size: 0.85rem; margin-bottom: 0.3rem;
    }
    .schedule-modal .form-control, .schedule-modal .form-select {
        height: 46px; border-radius: var(--radius-input);
        border: 1px solid var(--input-border); font-size: 0.88rem;
    }
    .schedule-modal .form-control:focus, .schedule-modal .form-select:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.12);
    }
    .schedule-modal .btn-cancel {
        height: 46px; border-radius: var(--radius-input);
        background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        border: 2px solid #dee2e6;
        color: #495057; font-weight: 600; flex: 1;
        box-shadow: 0 2px 6px rgba(0,0,0,.05);
    }
    .schedule-modal .btn-save {
        height: 46px; border-radius: var(--radius-input);
        border: none; background: linear-gradient(135deg, #295738 0%, #2e7d32 100%);
        color: #fff; font-weight: 600; flex: 1;
        box-shadow: 0 4px 10px rgba(46,125,50,.25);
    }
    @media(max-width:575.98px) {
        .schedule-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .schedule-cards-mobile { display: flex; flex-direction: column; gap: 0.75rem; }
        /* removed */ .xchip{
            padding: 3px 7px;
            font-size: 0.72rem;
        }

    @media(max-width:767.98px) {
        .schedule-section .btn, .schedule-section button,
        .schedule-section input, .schedule-section select { min-height: var(--touch-min); }
    }
</style>

        <div class="p-3 p-md-4 mb-3 schedule-section" style="border:1px solid #e8e8e8;border-radius:18px;box-shadow:0 6px 20px rgba(0,0,0,.06);">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h5 class="mb-0" style="color:#295738;"><i class="bi bi-person-check me-2"></i>Driver Assignment & Availability</h5>
        </div>

    <div class="info-banner p-3 mt-2 mb-3" style="background:#faf9e5;border:1px solid #e5e0a4;border-radius:12px;">
        <p class="m-0" style="font-size:.9rem;color:#2e4836;"><i class="bi bi-info-circle me-2" style="color:#295738;"></i>View real driver availability, assignments, current route, and shift based on existing system data.</p>
    </div>



    <div class="row g-3">
        <div class="col-12">

                <div id="scheduleLoading" class="schedule-state" style="display:none;">
                    <div class="spinner-custom state-icon"></div>
                    <div class="state-text">Loading driver assignments...</div>
                </div>

                <div id="scheduleEmpty" class="schedule-state" style="display:none;">
                    <div class="state-icon">👤</div>
                    <div class="state-text">No driver assignments found.</div>
                </div>

                <div id="scheduleError" class="schedule-state" style="display:none;">
                    <div class="state-icon" style="color:#c62828;">⚠️</div>
                    <div class="state-text">Failed to load driver assignments.</div>
                    <button class="state-retry" onclick="loadSchedules()"><i class="bi bi-arrow-clockwise me-2"></i>Retry</button>
                </div>

            <div id="scheduleGrid">
                <div class="schedule-table-wrap mt-3">
                    <table class="schedule-table">
                        <thead>
                                <tr>
                                    <th style=";">Driver</th><th style=";">Status</th><th style=";">Assigned Bus</th><th style=";">Current Route</th><th style=";">Shift</th><th style=";">Action</th>
                                </tr>
                        </thead>
                        <tbody id="scheduleTableBody"></tbody>
                    </table>
                    <div id="schedulePagination" class="schedule-pagination"></div>
                </div>
                <div id="scheduleCardsMobile" class="schedule-cards-mobile mt-3"></div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade schedule-modal" id="scheduleEditModal" tabindex="-1" aria-hidden="true" style="display:none;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-check me-2"></i>Driver Assignment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="info-grid" id="detailInfo"></div>
                <hr style="border-color:var(--card-border);">
                <form id="scheduleEditForm">
                    <input type="hidden" name="bus_id" class="form-bus-id">
                    <div class="mb-3">
                        <label class="form-label">Shift</label>
                        <input type="text" name="schedule" class="form-control form-schedule" placeholder="e.g. 06:00 - 14:00">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Current Route</label>
                            <select name="from_terminal_id" class="form-select form-from-terminal"></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assigned Bus</label>
                            <select name="to_terminal_id" class="form-select form-to-terminal"></select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Driver</label>
                        <select name="driver_id" class="form-select form-driver" required></select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-save" id="saveScheduleBtn"><i class="bi bi-save me-2"></i>Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
    let lookupCache = {};
    let allSchedules = [];
    const PAGE_SIZE = 10;
    let currentPage = 1;

    async function loadDropdowns(selectors) {
        try {
            if (!lookupCache.drivers) {
                const r = await (await fetch('/admin/api/Schedule/ScheduleApi.php?action=get_drivers')).json();
                lookupCache.drivers = r.success ? r.data : [];
            }
            if (!lookupCache.terminals) {
                const r = await (await fetch('/admin/api/Schedule/ScheduleApi.php?action=get_terminals')).json();
                lookupCache.terminals = r.success ? r.data : [];
            }
            selectors.forEach(sel => {
                let html = '<option value="">Select</option>';
                if (sel.classList.contains('form-from-terminal') || sel.classList.contains('form-to-terminal')) {
                    lookupCache.terminals.forEach(t => { html += '<option value="' + t.id + '">' + t.terminal_name + '</option>'; });
                } else if (sel.classList.contains('form-driver')) {
                    lookupCache.drivers.forEach(d => { html += '<option value="' + d.driver_id + '">' + d.full_name + '</option>'; });
                }
                sel.innerHTML = html;
            });
        } catch (e) {
            console.error('Failed to load dropdown data:', e);
        }
    }

    function showState(state) {
        document.getElementById('scheduleLoading').style.display = state === 'loading' ? 'flex' : 'none';
        document.getElementById('scheduleEmpty').style.display = state === 'empty' ? 'flex' : 'none';
        document.getElementById('scheduleError').style.display = state === 'error' ? 'flex' : 'none';
        document.getElementById('scheduleGrid').style.display = state === 'data' ? 'block' : 'none';
    }

    function renderPage(page) {
        currentPage = page;
        const start = (page - 1) * PAGE_SIZE;
        const end = Math.min(start + PAGE_SIZE, allSchedules.length);
        const pageData = allSchedules.slice(start, end);

        const tbody = document.getElementById('scheduleTableBody');
        const cardsContainer = document.getElementById('scheduleCardsMobile');
        let tableHtml = '';
        let cardsHtml = '';

        pageData.forEach(s => {
            const statusClass = (s.status || '').toLowerCase() === 'active' ? 'badge-active' : 'badge-inactive';
            const dataIdx = start + pageData.indexOf(s);

            // Determine status display
            let displayStatus = s.status || 'Not Assigned';
            const st = String(s.status || '').toLowerCase();
            if (st === 'on_trip' || st === 'on trip') displayStatus = 'On Trip';
            else if (st === 'on_break' || st === 'on break') displayStatus = 'On Break';
            else if (st === 'off_duty' || st === 'off duty') displayStatus = 'Off Duty';
            else if (st === 'not_assigned' || st === 'not assigned' || st === '' || !s.status) displayStatus = 'Not Assigned';
            else if (st === 'available') displayStatus = 'Available';

            const assignedBus = (s.plate_number || s.bus_id) ? (s.bus_id ? (s.plate_number ? s.bus_id + ' (' + s.plate_number + ')' : s.bus_id) : s.plate_number) : 'Not Assigned';
            const currentRoute = (s.route_name || s.from_terminal || s.to_terminal) ? (s.route_name || (s.from_terminal && s.to_terminal ? s.from_terminal + ' → ' + s.to_terminal : (s.from_terminal || s.to_terminal))) : 'Not Assigned';
            const shift = s.schedule || 'Not Assigned';

            tableHtml += '<tr>' +
            '<td><div class="fw-semibold" style="word-break:break-word; padding-right:4px;" title="' + (s.full_name || '-') + '">' + (s.full_name || '-') + '</div></td>' +
            '<td><span class="badge-status ' + statusClass + '">' + displayStatus + '</span></td>' +
            '<td><div style="word-break:break-word;" title="' + assignedBus + '">' + assignedBus + '</div></td>' +
            '<td><div style="word-break:break-word; line-height:1.4; white-space:normal;" title="' + currentRoute + '">' + currentRoute + '</div></td>' +
            '<td><div style="white-space:nowrap;" title="' + shift + '">' + shift + '</div></td>' +
            '<td class="text-center"><button class="btn-view viewBtn" data-idx="' + dataIdx + '"><i class="bi bi-eye me-1"></i>View</button></td>' +
            '</tr>';

            cardsHtml += '<div class="schedule-m-card">' +
            '<div class="m-row"><span class="m-label">Driver</span><span class="m-value"><strong>' + (s.full_name || '-') + '</strong></span></div>' +
            '<div class="m-row"><span class="m-label">Status</span><span class="m-value"><span class="badge-status ' + statusClass + '">' + displayStatus + '</span></span></div>' +
            '<div class="m-row"><span class="m-label">Assigned Bus</span><span class="m-value">' + assignedBus + '</span></div>' +
            '<div class="m-row"><span class="m-label">Current Route</span><span class="m-value">' + currentRoute + '</span></div>' +
            '<div class="m-row"><span class="m-label">Shift</span><span class="m-value">' + shift + '</span></div>' +
            '<div class="m-divider"></div>' +
            '<div class="m-actions"><button class="btn-m viewBtn" data-idx="' + dataIdx + '"><i class="bi bi-eye me-2"></i>View Details</button></div>' +
            '</div>';
        });

        tbody.innerHTML = tableHtml;
        cardsContainer.innerHTML = cardsHtml;

                showState('data');
    }

    function openEdit(item) {
        const statusClass = (item.status || '').toLowerCase() === 'active' ? 'badge-active' : 'badge-inactive';
        document.getElementById('detailInfo').innerHTML =
        '<div class="info-item"><div class="i-label">Bus ID</div><div class="i-value">' + (item.bus_id || '-') + '</div></div>' +
        '<div class="info-item"><div class="i-label">Plate Number</div><div class="i-value">' + (item.plate_number || '-') + '</div></div>' +
        '<div class="info-item"><div class="i-label">Route</div><div class="i-value">' + (item.route_name || '-') + '</div></div>' +
        '<div class="info-item"><div class="i-label">Status</div><div class="i-value"><span class="badge-status ' + statusClass + '">' + (item.status || '-') + '</span></div></div>';

        document.querySelector('.form-bus-id').value = item.bus_id || '';
        document.querySelector('.form-schedule').value = item.schedule || '';
        loadDropdowns([document.querySelector('.form-from-terminal'), document.querySelector('.form-to-terminal'), document.querySelector('.form-driver')]).then(() => {
            const fromEl = document.querySelector('.form-from-terminal');
            if (fromEl) fromEl.value = item.from_terminal_id || '';
            const toEl = document.querySelector('.form-to-terminal');
            if (toEl) toEl.value = item.to_terminal_id || '';
            const drvEl = document.querySelector('.form-driver');
            if (drvEl) drvEl.value = item.driver_id || '';
            new bootstrap.Modal(document.getElementById('scheduleEditModal')).show();
        });
    }

    async function loadSchedules() {
        showState('loading');
        try {
            const res = await fetch('/admin/api/Schedule/ScheduleApi.php?action=get_schedules');
            const result = await res.json();
            if (!result.success || !result.data.length) {
                showState('empty');
                return;
            }
            allSchedules = result.data;
            renderPage(1);
        } catch (e) {
            showState('error');
            console.error(e);
        }
    }

    document.getElementById('saveScheduleBtn')?.addEventListener('click', async function () {
        const form = document.getElementById('scheduleEditForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-custom" style="width:1rem;height:1rem;border-width:2px;margin-right:6px;"></span> Saving...';
        try {
            const fd = new FormData(form);
            const res = await fetch('/admin/api/Schedule/ScheduleApi.php?action=update_schedule', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('scheduleEditModal'));
                if (modal) modal.hide();
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                showAlert('success', 'Updated!', 'Schedule updated successfully.');
                loadSchedules();
            } else {
                showAlert('error', 'Failed', result.message || 'Could not save schedule.');
            }
        } catch (err) {
            showAlert('error', 'Error', err.message);
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-2"></i>Save Changes';
    });

    document.addEventListener('click', function (e) {
        const viewBtn = e.target.closest('.viewBtn');
        if (viewBtn) {
            const idx = parseInt(viewBtn.dataset.idx);
            if (!isNaN(idx) && allSchedules[idx]) {
                openEdit(allSchedules[idx]);
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        loadSchedules();
    });
</script>

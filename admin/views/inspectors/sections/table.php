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

    .inspector-table-section h5 { font-size: 1.25rem; font-weight: 700; color: var(--text-dark); }
    .inspector-table-section .info-banner {
        background: #fffbe6; border: 1px solid var(--card-border);
        border-radius: var(--radius-card); font-size: .95rem;
    }

    .inspector-state {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 3rem 1rem; background: #fff; border: 1px solid #eee;
        border-radius: var(--radius-card); text-align: center; min-height: 200px;
    }
    .inspector-state .state-icon { font-size: 2.5rem; margin-bottom: 0.75rem; }
    .inspector-state .state-text { font-size: 1rem; color: #687168; margin-bottom: 1rem; }
    .inspector-state .state-retry {
        min-height: var(--touch-min); border-radius: var(--radius-input);
        border: none; background: var(--accent); color: #fff; font-weight: 600;
        padding: 10px 24px; cursor: pointer;
    }
    .inspector-spinner {
        display: inline-block; width: 1.5rem; height: 1.5rem;
        border: 3px solid #e0e0e0; border-top-color: var(--accent);
        border-radius: 50%; animation: inspin 0.7s linear infinite;
    }
    @keyframes inspin { to { transform: rotate(360deg); } }

    .inspector-table-wrap {
        overflow-x: auto; border-radius: var(--radius-card);
        border: 1px solid #eee; background: #fff;
    }
    .inspector-table {
        width: 100%; border-collapse: collapse; font-size: 0.85rem; min-width: 600px;
    }
    .inspector-table thead th {
        background: var(--accent); color: #fff; padding: 10px 8px; font-weight: 600;
        text-align: center; white-space: nowrap; font-size: 0.78rem; letter-spacing: 0.02em;
    }
    .inspector-table tbody tr { border-bottom: 1px solid #f0f0f0; transition: background 0.12s; }
    .inspector-table tbody tr:hover { background: #f9f9f9; }
    .inspector-table tbody td { padding: 8px 6px; text-align: center; vertical-align: middle; }
    .inspector-table .btn-view {
        min-height: 32px; border-radius: 8px; border: none; background: var(--accent);
        color: #fff; font-weight: 600; padding: 5px 14px; font-size: 0.75rem; cursor: pointer;
    }
    .inspector-table .btn-view:hover { opacity: 0.85; }

    .inspector-pagination {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 16px; background: #fafafa; border-top: 1px solid #eee;
        font-size: 0.85rem; color: #555; flex-wrap: wrap; gap: 8px;
    }
    .inspector-pagination .btn-link-pag {
        background: none; border: none; color: var(--accent); font-weight: 600;
        cursor: pointer; padding: 6px 12px; min-height: var(--touch-min); font-size: 0.85rem;
    }
    .inspector-pagination .btn-link-pag:hover { text-decoration: underline; }
    .inspector-pagination .btn-link-pag:disabled { color: #bbb; cursor: default; text-decoration: none; }

    .inspector-cards-mobile { display: none; }

    @media(max-width:767.98px) {
        .inspector-table-wrap { display: none; }
        .inspector-cards-mobile { display: flex; flex-direction: column; gap: 0.6rem; }
        .inspector-m-card {
            background: #fff; border: 1px solid #e8e8e8;
            border-radius: 12px; padding: 0.7rem;
        }
        .inspector-m-card .m-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 3px 0; font-size: 0.82rem; border-bottom: 1px solid #f5f0df;
        }
        .inspector-m-card .m-row:last-of-type { border-bottom: none; }
        .inspector-m-card .m-row .m-label {
            font-weight: 600; color: var(--text-dark); font-size: 0.7rem;
            text-transform: uppercase; letter-spacing: 0.02em; min-width: 60px;
        }
        .inspector-m-card .m-row .m-value {
            text-align: right; color: #333; flex: 1;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .inspector-m-card .m-divider { margin: 0.4rem 0; border-top: 1px dashed #e0dcc8; }
        .inspector-m-card .m-actions { margin-top: 0.5rem; }
        .inspector-m-card .m-actions .btn-m {
            width: 100%; min-height: var(--touch-min); border-radius: 10px;
            border: none; background: var(--accent); color: #fff;
            font-weight: 600; font-size: 0.85rem; padding: 10px; cursor: pointer;
        }
    }

    @media(max-width:575.98px) {
        .inspector-pagination { flex-direction: column; text-align: center; }
    }

    .inspector-modal .modal-content {
        border-radius: 16px; border: none; background: var(--cream-bg);
    }
    .inspector-modal .modal-header {
        background: var(--accent); color: #fff; border-radius: 16px 16px 0 0;
        border: none; padding: 1rem 1.25rem;
    }
    .inspector-modal .modal-header .btn-close { filter: brightness(0) invert(1); }
    .inspector-modal .modal-body { padding: 1.5rem; }
    .inspector-modal .modal-footer {
        border: none; padding: 0 1.5rem 1.5rem; display: flex; gap: 0.75rem; justify-content: center;
    }
    .inspector-modal .info-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;
    }
    .inspector-modal .info-item {
        background: #fff; border: 1px solid #eee; border-radius: 10px; padding: 0.6rem 0.9rem;
    }
    .inspector-modal .info-item .i-label {
        font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em; color: #888; margin-bottom: 0.1rem;
    }
    .inspector-modal .info-item .i-value { font-size: 0.9rem; font-weight: 600; color: #222; }
    @media(max-width:575.98px) {
        .inspector-modal .info-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="inspector-table-section">
    <h5 class="mt-5 mb-3"><i class="bi bi-calendar-check me-2" style="color:var(--accent);"></i>Inspector Schedule & Assignments</h5>

    <div class="info-banner p-3 mb-3">
        <p class="m-0" style="font-size:.9rem;"><i class="bi bi-info-circle me-2" style="color:var(--accent);"></i>View trip schedules with inspector assignments.</p>
    </div>

    <div class="row g-3">
        <div class="col-12">

            <div id="inspectorLoading" class="inspector-state" style="display:none;">
                <div class="inspector-spinner state-icon"></div>
                <div class="state-text">Loading schedules...</div>
            </div>

            <div id="inspectorEmpty" class="inspector-state" style="display:none;">
                <div class="state-icon">📅</div>
                <div class="state-text">No schedules found.</div>
            </div>

            <div id="inspectorError" class="inspector-state" style="display:none;">
                <div class="state-icon" style="color:#c62828;">⚠️</div>
                <div class="state-text">Failed to load schedules.</div>
                <button class="state-retry" onclick="loadSchedules()"><i class="bi bi-arrow-clockwise me-2"></i>Retry</button>
            </div>

            <div id="inspectorGrid">
                <div class="inspector-table-wrap">
                    <table class="inspector-table">
                        <thead>
                            <tr>
                                <th>Trip No</th><th>Bus</th><th>Route</th><th>Schedule</th><th>Inspectors</th><th>Passengers</th><th style="width:80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="scheduleTableBody"></tbody>
                    </table>
                    <div id="schedulePagination" class="inspector-pagination"></div>
                </div>
                <div id="scheduleCardsMobile" class="inspector-cards-mobile"></div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade inspector-modal" id="scheduleDetailModal" tabindex="-1" aria-hidden="true" style="display:none;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-bus-front me-2"></i>Trip Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="info-grid" id="detailGrid"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" style="height:44px;border-radius:12px;background:#fff;border:1px solid #ddd;color:#666;font-weight:600;padding:0 24px;" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    let allSchedules = [];
    const PAGE_SIZE = 10;
    let currentPage = 1;

    function showState(state) {
        document.getElementById('inspectorLoading').style.display = state === 'loading' ? 'flex' : 'none';
        document.getElementById('inspectorEmpty').style.display = state === 'empty' ? 'flex' : 'none';
        document.getElementById('inspectorError').style.display = state === 'error' ? 'flex' : 'none';
        document.getElementById('inspectorGrid').style.display = state === 'data' ? 'block' : 'none';
    }

    function renderPage(page) {
        currentPage = page;
        const start = (page - 1) * PAGE_SIZE;
        const end = Math.min(start + PAGE_SIZE, allSchedules.length);
        const pageData = allSchedules.slice(start, end);
        const tbody = document.getElementById('scheduleTableBody');
        const cards = document.getElementById('scheduleCardsMobile');
        let th = '', ch = '';
        pageData.forEach((s, i) => {
            const idx = start + i;
            const route = (s.from_terminal || '') + ' - ' + (s.to_terminal || '');
            th += '<tr>' +
            '<td>' + (s.trip_no || '-') + '</td>' +
            '<td>' + (s.plate_number || s.bus_no || '-') + '</td>' +
            '<td>' + (route || '-') + '</td>' +
            '<td>' + (s.schedule || '-') + '</td>' +
            '<td>' + (s.inspectors || '—') + '</td>' +
            '<td>' + (s.passenger_count || 0) + '</td>' +
            '<td><button class="btn-view viewBtn" data-idx="' + idx + '"><i class="bi bi-eye me-1"></i>View</button></td>' +
            '</tr>';
            ch += '<div class="inspector-m-card">' +
            '<div class="m-row"><span class="m-label">Trip</span><span class="m-value"><strong>' + (s.trip_no || '-') + '</strong></span></div>' +
            '<div class="m-row"><span class="m-label">Bus</span><span class="m-value">' + (s.plate_number || s.bus_no || '-') + '</span></div>' +
            '<div class="m-row"><span class="m-label">Route</span><span class="m-value">' + (route || '-') + '</span></div>' +
            '<div class="m-row"><span class="m-label">Inspectors</span><span class="m-value">' + (s.inspectors || '—') + '</span></div>' +
            '<div class="m-divider"></div>' +
            '<div class="m-actions"><button class="btn-m viewBtn" data-idx="' + idx + '"><i class="bi bi-eye me-2"></i>View Details</button></div>' +
            '</div>';
        });
        tbody.innerHTML = th;
        cards.innerHTML = ch;
        const totalPages = Math.ceil(allSchedules.length / PAGE_SIZE);
        const pagEl = document.getElementById('schedulePagination');
        if (allSchedules.length <= PAGE_SIZE) {
            pagEl.innerHTML = '<span>' + allSchedules.length + ' schedule(s)</span>';
        } else {
            pagEl.innerHTML =
            '<span>' + (start + 1) + '-' + end + ' of ' + allSchedules.length + '</span>' +
            '<div>' +
            '<button class="btn-link-pag" onclick="renderPage(' + (page - 1) + ')" ' + (page <= 1 ? 'disabled' : '') + '><i class="bi bi-chevron-left me-1"></i>Previous</button>' +
            '<button class="btn-link-pag" onclick="renderPage(' + (page + 1) + ')" ' + (page >= totalPages ? 'disabled' : '') + '>Next<i class="bi bi-chevron-right ms-1"></i></button>' +
            '</div>';
        }
        showState('data');
    }

    function openDetail(item) {
        const route = (item.from_terminal || '') + ' - ' + (item.to_terminal || '');
        document.getElementById('detailGrid').innerHTML =
        '<div class="info-item"><div class="i-label">Trip No</div><div class="i-value">' + (item.trip_no || '-') + '</div></div>' +
        '<div class="info-item"><div class="i-label">Bus</div><div class="i-value">' + (item.plate_number || item.bus_no || '-') + '</div></div>' +
        '<div class="info-item"><div class="i-label">Bus Type</div><div class="i-value">' + (item.bus_type || '-') + '</div></div>' +
        '<div class="info-item"><div class="i-label">Route</div><div class="i-value">' + (route || '-') + '</div></div>' +
        '<div class="info-item"><div class="i-label">Schedule</div><div class="i-value">' + (item.schedule || '-') + '</div></div>' +
        '<div class="info-item"><div class="i-label">Status</div><div class="i-value">' + (item.status || '-') + '</div></div>' +
        '<div class="info-item"><div class="i-label">Assigned Inspectors</div><div class="i-value">' + (item.inspectors || 'None') + '</div></div>' +
        '<div class="info-item"><div class="i-label">Passenger Count</div><div class="i-value">' + (item.passenger_count || 0) + '</div></div>';
        new bootstrap.Modal(document.getElementById('scheduleDetailModal')).show();
    }

    async function loadSchedules() {
        showState('loading');
        try {
            const res = await fetch('/admin/api/Inspectors/InspectorsApi.php?action=get_schedules');
            const result = await res.json();
            if (!result.success || !result.data.length) { showState('empty'); return; }
            allSchedules = result.data;
            renderPage(1);
        } catch (e) { showState('error'); console.error(e); }
    }

    document.addEventListener('click', function (e) {
        const vBtn = e.target.closest('.viewBtn');
        if (vBtn) {
            const idx = parseInt(vBtn.dataset.idx);
            if (!isNaN(idx) && allSchedules[idx]) openDetail(allSchedules[idx]);
        }
    });

    document.addEventListener('DOMContentLoaded', loadSchedules);
</script>

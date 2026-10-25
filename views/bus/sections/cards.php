<div
    class="p-4 mb-4"
    style="
        border:1px solid #e8e8e8;
        border-radius:18px;
        box-shadow:0 4px 18px rgba(0,0,0,.05);
    "
>

    <div
        class="
            d-flex
            flex-wrap
            justify-content-between
            align-items-center
            gap-3
        "
    >


        <div>

            <div
                class="
                    d-flex
                    align-items-center
                    gap-2
                    mb-1
                "
            >

                <div
                    style="
                        width:42px;
                        height:42px;
                        border-radius:12px;
                        background:
                        linear-gradient(
                            135deg,
                            #dee4ba,
                            #5a9e6a
                        );
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        color:#fff;
                    "
                >
                    <i
                        class="bi bi-qr-code-scan"
                    ></i>
                    </div>

                    <div>

                        <h5
                        class="mb-0 fw-bold"
                        style="
                            color:#295738;
                        "
                    >
                        QR Generation
                        </h5>

                        <small
                        id="busCount"
                        class="text-muted"
                    >
                        Loading buses...
                        </small>

                    </div>

                </div>

            </div>


            <div
            class="
                d-flex
                flex-wrap
                align-items-center
                gap-2
            "
        >


                <div
                class="input-group"
                style="
                    width:350px;
                "
            >

                    <span
                    class="input-group-text"
                    style="
                        background:#fef8f3;
                        border-color:#e7e3af;
                    "
                >
                        <i
                        class="bi bi-search"
                        style="color:#295738;"
                    ></i>
                        </span>

                        <input
                    type="text"
                    id="searchBus"
                    class="form-control"
                    placeholder="Search Bus ID or Plate Number..."
                    style="
                        border-color:#e7e3af;
                    "
                >

                    </div>


                    <button
                class="
                    btn
                    px-3
                    py-2
                "
                style="
                    background:#fef8f3;
                    border:2px solid #e7e3af;
                    color:#295738;
                    border-radius:12px;
                    font-weight:600;
                    font-size:0.85rem;
                    transition:all .2s ease;
                "
                onmouseover="this.style.background='#f4f0dc';this.style.borderColor='#cdd9b5'"
                onmouseout="this.style.background='#fef8f3';this.style.borderColor='#e7e3af'"
                data-bs-toggle="modal"
                data-bs-target="#archivedBusesModal"
            >

                        <i class="bi bi-archive me-1"></i>

                        <span id="archivedCount">Archived</span>

                    </button>


                    <button
                class="
                    btn
                    text-white
                    px-4
                    py-2
                "
                style="
                    background:
                    linear-gradient(
                        90deg,
                        #b2c9a8,
                        #a3c9a3
                    );
                    border:none;
                    border-radius:12px;
                    color:#295738;
                    box-shadow:
                        0 4px 12px
                        rgba(
                            41,
                            87,
                            56,
                            .1
                        );
                "
                data-bs-toggle="modal"
                data-bs-target="#addBusModal"
            >

                        <i
                    class="
                        bi
                        bi-plus-circle
                        me-1
                    "
                ></i>

                Add Bus

                        </button>

                    </div>

                </div>

            </div>

            <div class="row g-3" id="qrCards"></div>

            <div
    class="text-center mt-4"
    id="showMoreContainer"
></div>



                <script>
                    let allBuses = [];
                    let showAll = false;
                    let searchKeyword = "";

                    async function loadQRCards() {

                        const container = document.getElementById("qrCards");

                        container.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-success"></div>
            <div class="mt-2 text-muted">Loading buses...</div>
        </div>
    `;

                        try {

                            const response = await fetch("/admin/ajax/get_qr_buses.php");
                            const result = await response.json();

                            if (!result.success) {

                                container.innerHTML = `
                <div class="alert alert-danger">
                    ${result.message}
                </div>
            `;

                                return;

                            }

                            allBuses = result.data;

                            renderCards();

                        } catch (err) {

                            console.error(err);

                            container.innerHTML = `
            <div class="alert alert-danger">
                Failed to load buses.
            </div>
        `;

                        }

                    }

                    function renderCards() {

                        const container = document.getElementById("qrCards");
                        const showMoreContainer = document.getElementById("showMoreContainer");

                        const filteredBuses = allBuses.filter(bus => {

                            const keyword = searchKeyword.trim().toLowerCase();

                            return (
                            bus.bus_id.toLowerCase().includes(keyword) ||
                            bus.plate_number.toLowerCase().includes(keyword)
                            );

                        });

                        const buses = showAll
                        ? filteredBuses
                        : filteredBuses.slice(0, 4);

                        let html = "";

                        buses.forEach(bus => {

                            const qrStatus = bus.qr_status || "Pending";
                            const isGenerated = qrStatus === "Generated";

                            html += `

<div class="col-xl-3 col-lg-4 col-md-6 mb-3">

<div class="card qr-card h-100">

    <div class="qr-header">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <div class="fw-bold fs-5">
                    <i class="bi bi-bus-front-fill me-2"></i>
                    ${bus.bus_id}
                </div>

                <small class="opacity-75">
                    ${bus.plate_number}
                </small>

            </div>

            <span class="qr-badge ${
                isGenerated
                    ? "generated"
                    : "pending"
            }">

                <i class="bi ${
                    isGenerated
                        ? "bi-check-circle-fill"
                        : "bi-clock-fill"
                } me-1"></i>

                ${qrStatus}

            </span>

        </div>

        ${bus.open_problem_id ? `
        <div class="d-flex justify-content-between align-items-center mt-2"
             style="background:#fbe7e4;border:1px solid #f3cfc9;border-radius:8px;padding:4px 8px;">
            <span style="font-size:.68rem;font-weight:700;color:#a32018;">
                <i class="bi bi-cone-striped me-1"></i>
                ${bus.open_problem_type || "Problem"}&nbsp;&middot;&nbsp;${bus.open_problem_status === "acknowledged" ? "Acknowledged" : "Reported"}
            </span>
            <a href="/admin/inspectors/?monitoring=dispatcher"
               style="font-size:.66rem;font-weight:700;color:#a32018;text-decoration:none;white-space:nowrap;">
                View Problem
            </a>
        </div>
        ` : ""}

    </div>

    <div class="card-body">

        <div class="info-row">

            <i class="bi bi-geo-alt-fill text-success"></i>

            <div>

                <small class="text-muted">Route</small>

                <div class="fw-semibold">

                    ${bus.from_terminal || "-"}

                    <i class="bi bi-arrow-right mx-2"></i>

                    ${bus.to_terminal || "-"}

                </div>

            </div>

        </div>

        <div class="info-row">

            <i class="bi bi-person-fill text-success"></i>

            <div>

                <small class="text-muted">Driver</small>

                <div class="fw-semibold">

                    ${bus.driver || "-"}

                </div>

            </div>

        </div>

        <div class="info-row">

            <i class="bi bi-people-fill text-success"></i>

            <div>

                <small class="text-muted">Capacity</small>

                <div class="fw-semibold">

                    ${bus.seats} Seats • ${bus.standing_capacity} Standing

                </div>

            </div>

        </div>

        <div class="info-row">

            <i class="bi bi-bus-front text-success"></i>

            <div>

                <small class="text-muted">Bus Type</small>

                <div class="fw-semibold">

                    ${bus.bus_type}

                </div>

            </div>

        </div>

        <button

            class="btn ${
                isGenerated
                    ? "btn-view"
                    : "btn-theme"
            } w-100"

            onclick="openQRModal(
                '${bus.bus_id}',
                ${bus.seats},
                ${bus.standing_capacity},
                this
            )"

        >

            <i class="bi ${
                isGenerated
                    ? "bi-eye-fill"
                    : "bi-qr-code-scan"
            } me-2"></i>

            ${
                isGenerated
                    ? "View QR"
                    : "Generate QR"
            }

        </button>

        <div class="d-flex gap-2 mt-2">

            <button class="btn btn-outline-success flex-fill"
                    data-bs-toggle="modal"
                    data-bs-target="#editBusModal"
                    data-bus-id="${bus.bus_id}"
                    data-plate="${bus.plate_number}"
                    data-type="${bus.bus_type}"
                    data-seats="${bus.seats}"
                    data-standing="${bus.standing_capacity}"
                    data-accessible="${bus.accessible_features}"
                    data-from="${bus.from_terminal_id || ''}"
                    data-to="${bus.to_terminal_id || ''}"
                    data-schedule="${bus.schedule || ''}"
                    data-driver="${bus.driver_id || ''}"
                    data-status="${bus.status}">

                <i class="bi bi-pencil-square me-1"></i>

                Edit

            </button>

            <button class="btn btn-archive flex-fill"
                    data-bus-id="${bus.bus_id}"
                    onclick="archiveBus('${bus.bus_id}', this)">

                <i class="bi bi-archive me-1"></i>

                Archive

            </button>

        </div>

    </div>

</div>

</div>

`;

                        });

                        container.innerHTML = html;
    
                        const busCount = document.getElementById("busCount");

                        if (busCount) {

                            busCount.textContent =
                            `${filteredBuses.length} Bus${filteredBuses.length !== 1 ? "es" : ""}`;

                        }

                        if (filteredBuses.length > 4) {

                            showMoreContainer.innerHTML = `

<button
    class="btn btn-outline-success px-4"
    onclick="toggleCards()">

${
    showAll
        ? "Show Less"
        : `Show More (${filteredBuses.length - 4})`
}

</button>

`;

                        } else {

                            showMoreContainer.innerHTML = "";

                        }

                        document.querySelectorAll(".qr-card").forEach(card => {

                            card.addEventListener("mouseenter", () => {

                                card.style.transform = "translateY(-3px)";
                                card.style.boxShadow = "0 12px 24px rgba(41,87,56,.10)";

                            });

                            card.addEventListener("mouseleave", () => {

                                card.style.transform = "";
                                card.style.boxShadow = "";

                            });

                        });

                    }

                    window.toggleCards = function () {

                        showAll = !showAll;

                        renderCards();

                    };
                    document.addEventListener("DOMContentLoaded", loadQRCards);

                    document.addEventListener("DOMContentLoaded", () => {

                        const search = document.getElementById("searchBus");

                        if (search) {

                            search.addEventListener("input", function () {

                                searchKeyword = this.value;
                                showAll = false;

                                renderCards();

                            });

                        }

                    });
                </script>

                <style>

                    .qr-card {

                        background: #fef8f3;
                        border: 2px solid #e7e3af;
                        border-radius: 16px;
                        overflow: hidden;

                        box-shadow:
                        0 3px 8px rgba(41, 87, 56, .04);

                        transition:
                        transform .2s ease,
                        box-shadow .2s ease,
                        border-color .2s ease;

                    }

                    .qr-card:hover {

                        transform: translateY(-2px);

                        border-color: #cdd9b5;

                        box-shadow:
                        0 8px 18px rgba(41, 87, 56, .08);

                    }

                    .qr-header {

                        background: linear-gradient(135deg, #dee4ba, #b2c9a8);
                        color: #295738;

                        padding: 12px 14px;

                        border-bottom: 1px solid rgba(0, 0, 0, .05);

                    }

                    .qr-header .fw-bold {

                        font-size: 1rem;

                    }

                    .qr-header small {

                        font-size: .75rem;

                    }

                    .qr-badge {

                        display: inline-flex;
                        align-items: center;
                        gap: 4px;

                        padding: 3px 8px;
                        border-radius: 16px;

                        font-size: .68rem;
                        font-weight: 600;
                        line-height: 1;

                    }

                    .qr-badge i {

                        font-size: .7rem;

                    }

                    .qr-badge.generated {

                        background: #e5f6e7;
                        color: #295738;
                        border: 1px solid #8fd39b;

                    }

                    .qr-badge.pending {

                        background: #fff8dc;
                        color: #8a6d00;
                        border: 1px solid #edd68b;

                    }

                    .card-body {

                        padding: 16px;
                        background: #fcf9f0;

                    }

                    .info-row {

                        display: flex;
                        align-items: flex-start;
                        gap: 10px;

                        padding: 8px 0;

                        border-bottom: 1px solid #e7e3af;

                    }

                    .info-row:last-of-type {

                        border-bottom: none;

                    }

                    .info-row i {

                        color: #5a9e6a;
                        font-size: 1rem;
                        width: 18px;
                        margin-top: 2px;

                    }

                    .info-row small {

                        font-size: .72rem;

                    }

                    .info-row .fw-semibold {

                        font-size: .87rem;

                    }

                    .btn-theme,
                    .btn-view {

                        height: 40px;

                        border-radius: 8px;

                        font-size: .85rem;

                        font-weight: 600;

                        transition: .2s;

                    }

                    .btn-theme {

                        background: transparent !important;
                        color: #295738 !important;
                        border: 2px solid #295738 !important;
                        font-weight: 600 !important;

                    }

                    .btn-theme:hover {

                        background: #295738 !important;
                        color: #fff !important;

                    }

                    .btn-view {

                        background: transparent !important;
                        color: #1b5e20 !important;
                        border: 2px solid #4caf50 !important;
                        font-weight: 600 !important;

                    }

                    .btn-view:hover {

                        background: #4caf50 !important;
                        color: #fff !important;

                    }

                    .btn-outline-success,
                    .btn-archive {

                        height: 38px;

                        font-size: .82rem;

                        border-radius: 8px;
                        transition: all 0.2s ease;

                    }

                    .btn-outline-success {

                        background: transparent !important;
                        color: #295738 !important;
                        border: 2px solid #cdd9b5 !important;
                        font-weight: 600 !important;

                    }

                    .btn-outline-success:hover {

                        background: #cdd9b5 !important;
                        color: #295738 !important;

                    }

                    .btn-archive {

                        background: transparent !important;
                        color: #8a6d00 !important;
                        border: 2px solid #e7e3af !important;
                        font-weight: 600 !important;

                    }

                    .btn-archive:hover {

                        background: #e7e3af !important;
                        color: #8a6d00 !important;

                    }

                </style>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        loadArchivedCount();
                        const editModal = document.getElementById('editBusModal');
                        if (!editModal) return;

                        editModal.addEventListener('show.bs.modal', function (event) {
                            const btn = event.relatedTarget;
                            document.getElementById('edit_bus_id_hidden').value = btn.getAttribute('data-bus-id');
                            document.getElementById('edit_bus_id').value = btn.getAttribute('data-bus-id');
                            document.getElementById('edit_plate_number').value = btn.getAttribute('data-plate');
                            document.getElementById('edit_bus_type').value = btn.getAttribute('data-type');
                            document.getElementById('edit_seats').value = btn.getAttribute('data-seats');
                            document.getElementById('edit_standing_capacity').value = btn.getAttribute('data-standing');
                            document.getElementById('edit_accessible_features').value = btn.getAttribute('data-accessible');
                            document.getElementById('edit_from_terminal_id').value = btn.getAttribute('data-from');
                            document.getElementById('edit_to_terminal_id').value = btn.getAttribute('data-to');
                            document.getElementById('edit_schedule').value = btn.getAttribute('data-schedule');
                            document.getElementById('edit_driver').value = btn.getAttribute('data-driver');
                            document.getElementById('edit_status').value = btn.getAttribute('data-status');
                        });
                    });

                    async function loadArchivedCount() {
                        try {
                            const res = await fetch('/admin/api/get_archived_buses.php');
                            const data = await res.json();
                            if (data.status === 'success' && data.count > 0) {
                                document.getElementById('archivedCount').textContent = 'Archived (' + data.count + ')';
                            } else if (data.status === 'success') {
                                document.getElementById('archivedCount').textContent = 'Archived';
                            }
                        } catch (e) {}
                    }

                    function showArchivedBuses() {
                        fetch('/admin/api/get_archived_buses.php')
                        .then(r => r.json())
                        .then(data => {
                            if (data.status !== 'success') {
                                showAlert('error', 'Error', data.message);
                                return;
                            }
                            if (data.count === 0) {
                                showAlert('error', 'No Archived Buses', 'There are no archived buses.');
                                return;
                            }

                            let rows = '';
                            data.data.forEach(b => {
                                rows += '<tr style="border-bottom:1px solid #f0ede0;">';
                                rows += '<td style="padding:12px 14px;color:#2e4836;font-weight:600;font-size:0.85rem;">' + b.bus_id + '</td>';
                                rows += '<td style="padding:12px 14px;color:#475569;font-size:0.85rem;">' + b.plate_number + '</td>';
                                rows += '<td style="padding:12px 14px;color:#475569;font-size:0.85rem;">' + b.bus_type + '</td>';
                                rows += '<td style="padding:12px 14px;"><span style="background:#f8d7da;color:#721c24;padding:4px 14px;border-radius:20px;font-size:0.75rem;font-weight:600;display:inline-block;">Archived</span></td>';
                                rows += '</tr>';
                            });

                            let html = '<div style="background:#fef8f3;border-radius:16px;padding:4px;">';
                            html += '<div style="background:#ffffff;border:1px solid #ebe7d4;border-radius:14px;overflow:hidden;">';
                            html += '<table style="width:100%;border-collapse:collapse;">';
                            html += '<thead><tr style="background:linear-gradient(135deg,#f9f4da 0%,#f5edd0 100%);">';
                            html += '<th style="padding:14px 14px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Bus ID</th>';
                            html += '<th style="padding:14px 14px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Plate</th>';
                            html += '<th style="padding:14px 14px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Type</th>';
                            html += '<th style="padding:14px 14px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Status</th>';
                            html += '</tr></thead><tbody>';
                            html += rows;
                            html += '</tbody></table></div></div>';

                            showAlert('success', 'Archived Buses', 'View the table above for archived bus details.');
                        })
                        .catch (function (err) { showAlert('error', 'Error', err.message); });
                    }

                    function archiveBus(busId, btn) {
                        showConfirm('Archive Bus?', 'This will archive bus ' + busId + '. You can restore it later.', async function () {

                            btn.disabled = true;
                            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Archiving...';

                            try {
                                const res = await fetch('/admin/api/archive_bus.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ bus_id: busId })
                                });
                                const raw = await res.text();
                                let data;
                                try { data = JSON.parse(raw); } catch (e) { data = { status: 'error', message: raw }; }

                                if (data.status === 'success') {
                                    showAlert('success', 'Archived', 'Bus ' + busId + ' has been archived.');
                                    location.reload();
                                } else {
                                    showAlert('error', 'Failed', data.message || 'Failed to archive bus');
                                }
                            } catch (err) {
                                showAlert('error', 'Error', err.message);
                            }

                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-archive me-1"></i> Archive';
                        });
                    }
                </script>
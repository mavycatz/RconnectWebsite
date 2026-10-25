<script src="/admin/assets/js/admin-user-validation.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/assets/js/admin-user-validation.js') ?: time() ?>"></script>
<style>
    .inspector-cards-section,
    .inspector-cards-section * {
        font-family: 'Poppins', sans-serif;
    }

    .inspector-card {
        position: relative;
        background: #fffdf8;
        border: 1px solid #e5e0a4;
        border-radius: 17px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .04);
        transition: box-shadow .2s ease, transform .2s ease;
    }
    .inspector-card:hover {
        box-shadow: 0 8px 22px rgba(41, 87, 56, .10);
    }
    .inspector-card-accent {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: #a5d6a7;
        border-radius: 0 0 17px 17px;
    }
    .inspector-menu-btn:hover {
        color: #295738 !important;
        background: #eef5ee !important;
        border-radius: 8px;
    }
    .inspector-info-icon {
        font-size: .78rem;
        color: #295738;
        line-height: 1;
    }
    .inspector-info-label {
        font-size: 11px;
        font-weight: 500;
        color: #8a9a8a;
        letter-spacing: .3px;
    }
    .inspector-info-value {
        font-size: 13px;
        font-weight: 500;
        color: #2e4836;
        margin-top: 1px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .inspector-email-value {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .inspector-action-btn {
        font-size: .78rem;
        font-weight: 500;
        border-radius: 10px;
        padding: 7px 10px;
    }
    .inspector-action-btn.btn-view {
        background: #ffffff;
        border: 1px solid #c9d8c9;
        color: #295738;
    }
    .inspector-action-btn.btn-view:hover {
        background: #eef5ee;
        border-color: #a5d6a7;
    }
    .inspector-action-btn.btn-edit {
        background: #ffffff;
        border: 1px solid #e5e0a4;
        color: #687168;
    }
    .inspector-action-btn.btn-edit:hover {
        background: #faf9e5;
        border-color: #d5cc8e;
    }

    .inspector-search-wrap {
        width: 350px;
    }
    .inspector-status-select {
        width: 175px;
        height: 43px;
        border-radius: 12px;
        border: 1px solid #e5e0a4;
        font-size: .85rem;
        color: #687168;
        background-color: #fffdf8;
    }
    .inspector-search-wrap .form-control {
        border-color: #e5e0a4;
        border-left: none;
        border-radius: 0 12px 12px 0;
        height: 43px;
        font-size: .85rem;
    }
    .inspector-search-wrap .form-control:focus,
    .inspector-status-select:focus {
        border-color: #a5d6a7;
        box-shadow: 0 0 0 .2rem rgba(41, 87, 56, .08);
    }
    .inspector-search-wrap .input-group-text {
        background: #fffdf8;
        border-color: #e5e0a4;
        border-right: none;
        border-radius: 12px 0 0 12px;
        color: #295738;
    }

    .inspector-pagination-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 22px;
        padding: 10px 4px;
        font-size: .8rem;
        color: #8a9a8a;
    }
    .page-controls {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .page-btn {
        min-width: 32px;
        height: 32px;
        border: 1px solid #e5e0a4;
        background: #fffdf8;
        border-radius: 8px;
        color: #687168;
        font-size: .8rem;
        font-weight: 500;
        transition: all .15s ease;
    }
    .page-btn:hover {
        border-color: #a5d6a7;
        color: #295738;
    }
    .page-btn.active {
        background: #295738;
        border-color: #295738;
        color: #ffffff;
    }
    .page-nav {
        min-width: 32px;
        height: 32px;
        border: 1px solid #e5e0a4;
        background: #fffdf8;
        border-radius: 8px;
        color: #295738;
        font-size: .8rem;
    }
    .page-nav:hover:not(.disabled) {
        border-color: #a5d6a7;
        color: #295738;
    }
    .page-nav.disabled {
        opacity: .4;
        cursor: not-allowed;
    }
    .page-ellipsis {
        padding: 0 4px;
        color: #8a9a8a;
    }
    .page-per {
        width: 140px;
        height: 32px;
        font-size: .78rem;
        border-radius: 8px;
        border: 1px solid #e5e0a4;
        color: #687168;
        background-color: #fffdf8;
    }
    .page-per:focus {
        border-color: #a5d6a7;
        box-shadow: 0 0 0 .2rem rgba(41, 87, 56, .08);
    }
    .page-range {
        font-size: .8rem;
        color: #8a9a8a;
    }

    .inspector-view-section-title {
        font-size: .72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: #8a9a8a;
        margin-bottom: 12px;
    }
    .inspector-view-item {
        background: #ffffff;
        border: 1px solid #ebe7d4;
        border-radius: 12px;
        padding: 10px 14px;
        height: 100%;
    }
    .inspector-view-item .i-label {
        font-size: 11px;
        font-weight: 500;
        color: #8a9a8a;
        letter-spacing: .3px;
        margin-bottom: 2px;
    }
    .inspector-view-item .i-value {
        font-size: 13.5px;
        font-weight: 500;
        color: #2e4836;
    }

    @media(max-width:767.98px) {
        .inspector-cards-section { padding: 14px !important; }
        .inspector-cards-section .btn { font-size: .78rem !important; padding: 8px 12px !important; min-height: 44px !important; }
        .inspector-cards-section input, .inspector-cards-section select { font-size: .85rem !important; }
        .inspector-search-wrap { width: 100% !important; }
        .inspector-status-select { width: 100% !important; }
        .inspector-toolbar-actions { width: 100% !important; }
        .inspector-toolbar-actions > * { flex: 1 1 auto !important; min-width: 0 !important; }
        .inspector-pagination-bar { justify-content: center !important; }
        .page-range { width: 100% !important; text-align: center !important; }
        .inspector-pagination-bar .page-per { width: 100% !important; }
        #inspectorPagination .page-controls { justify-content: center !important; flex-wrap: wrap !important; }
    }
</style>

<div class="p-4 mb-4 inspector-cards-section"
     style="border:1px solid #e8e8e8;border-radius:18px;box-shadow:0 4px 18px rgba(0,0,0,.05);">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">


        <div class="d-flex align-items-center gap-2 mb-1">

            <div style="width:42px;height:42px;border-radius:12px;background:#295738;display:flex;align-items:center;justify-content:center;color:#fff;">
                <i class="bi bi-person-badge"></i>
            </div>

            <div>
                <h5 class="mb-0 fw-bold" style="color:#295738;">Inspectors</h5>
                <small id="inspectorCount" class="text-muted">Loading records...</small>
            </div>

        </div>


        <div class="d-flex flex-wrap align-items-center gap-2 inspector-toolbar-actions">


            <div class="input-group inspector-search-wrap">

                <span class="input-group-text">
                    <i class="bi bi-search"></i>
                </span>

                <input
                    type="text"
                    id="inspectorSearch"
                    class="form-control"
                    placeholder="Search by name, ID, terminal, department..."
                    oninput="searchInspectors()"
                >

            </div>


            <select
                id="statusFilter"
                class="form-select inspector-status-select"
                onchange="searchInspectors()"
            >
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>


            <button
                class="btn px-3 py-2"
                style="background:#fffdf8;border:1px solid #e5e0a4;color:#687168;border-radius:12px;"
                onclick="openArchivedInspectors()"
            >
                <i class="bi bi-archive me-1"></i>
                Archived
            </button>


            <button
                class="btn text-white px-4 py-2"
                style="background:#295738;border:none;border-radius:12px;box-shadow:0 4px 12px rgba(41,87,56,.25);"
                data-bs-toggle="modal"
                data-bs-target="#addInspectorModal"
            >
                <i class="bi bi-plus-circle me-1"></i>
                Add Inspector
            </button>

        </div>

    </div>

</div>

<div class="container-fluid px-3">

    <div
        id="inspectorsContainer"
        class="row g-4"
    >
        <div class="text-center py-5">
            Loading inspectors...
        </div>
    </div>

    <div id="inspectorPagination"></div>

</div>


<div
    class="modal fade"
    id="archivedInspectorsModal"
    tabindex="-1"
>

    <div
        class="
            modal-dialog
            modal-xl
            modal-dialog-centered
        "
    >

        <div
            class="modal-content border-0 overflow-hidden"
            style="border-radius:24px; background:#fef8f3; box-shadow:0 18px 50px rgba(0,0,0,.08);"
        >

            <div class="row g-0">

                <div class="col-lg-4 d-none d-lg-block"
                 style="background:#f9f4da; border-right:1px solid #e7e1c9; min-height:600px;">

                    <div class="p-5 h-100 d-flex flex-column justify-content-between">

                        <div>

                            <div style="width:72px;height:72px;border-radius:22px;background:#ffffff;border:1px solid #ebe5ca;display:flex;align-items:center;justify-content:center;">
                                <i class="fa-solid fa-box-archive" style="color:#c4b417; font-size:1.6rem;"></i>
                            </div>

                            <h2 style="margin-top:28px;font-size:2rem;font-weight:700;line-height:1.3;color:#2e4836;">
                Archived Inspectors
                            </h2>

                            <p style="margin-top:18px;font-size:.94rem;line-height:1.9;color:#70796f;">
                View all archived inspectors that have been
                removed from active rotation. You can
                restore them to bring them back.
                            </p>

                        </div>

                        <div class="d-grid gap-3">

                            <div style="background:#ffffff;border:1px solid #ebe5ca;border-radius:18px;padding:18px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:46px;height:46px;border-radius:14px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                                        <i class="fa-solid fa-eye-slash" style="color:#c4b417; font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.9rem;font-weight:700;color:#2e4836;">Removed from Rotation</div>
                                        <div style="font-size:.76rem;color:#7d857c;">Inspectors are hidden from active list</div>
                                    </div>
                                </div>
                            </div>

                            <div style="background:#ffffff;border:1px solid #ebe5ca;border-radius:18px;padding:18px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:46px;height:46px;border-radius:14px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                                        <i class="fa-solid fa-rotate-left" style="color:#c4b417; font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.9rem;font-weight:700;color:#2e4836;">Restorable</div>
                                        <div style="font-size:.76rem;color:#7d857c;">Archived inspectors can be restored anytime</div>
                                    </div>
                                </div>
                            </div>

                            <div style="background:#ffffff;border:1px solid #ebe5ca;border-radius:18px;padding:18px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:46px;height:46px;border-radius:14px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                                        <i class="fa-solid fa-clock" style="color:#c4b417; font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.9rem;font-weight:700;color:#2e4836;">Records Preserved</div>
                                        <div style="font-size:.76rem;color:#7d857c;">All inspection data is kept</div>
                                    </div>
                                </div>
                            </div>

                            <div style="background:#fff9eb;border:1px solid #f3d57a;border-radius:18px;padding:18px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:46px;height:46px;border-radius:14px;background:#ffffff;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                                        <i class="fa-solid fa-trash-can" style="color:#d6a100; font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.9rem;font-weight:700;color:#8b6b00;">2-Year Retention</div>
                                        <div style="font-size:.76rem;color:#7d857c;">Archived records are automatically deleted after 2 years</div>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-lg-8 col-12">

                    <div class="modal-header border-0 px-5 pt-5 pb-0">

                        <div>
                            <h4 style="margin:0;color:#2e4836;font-weight:700;">
                                <i class="fa-solid fa-box-archive me-2" style="color:#8d6e00;"></i>Archived Inspectors
                            </h4>
                            <div style="font-size:.84rem;color:#7b847b;margin-top:4px;">
                                <span id="archivedInspectorCount">Loading archived records...</span>
                            </div>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div style="padding:16px 40px 0;">
                        <div class="input-group">
                            <span class="input-group-text" style="background:white;border-color:#e5e0a4;">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" id="archivedInspectorSearch" class="form-control"
                           placeholder="Search archived inspectors..."
                           style="border-color:#e5e0a4;"
                           oninput="filterArchivedInspectors()">
                        </div>
                    </div>

                    <div class="modal-body px-5 py-4">

                        <div id="archivedInspectorsBody" style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:24px;min-height:300px;">

                            <div style="text-align:center;padding:60px 20px;color:#70796f;">
                                <i class="fa-solid fa-spinner fa-spin" style="font-size:2rem;color:#c4b417;"></i>
                                <p style="margin-top:16px;font-size:.9rem;">Loading archived inspectors...</p>
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer border-0 px-5 pb-5 pt-0">

                        <button type="button" class="btn" data-bs-dismiss="modal"
                    style="height:50px;padding:0 24px;border-radius:14px;background:#ffffff;border:1px solid #dfe5d1;color:#687168;font-weight:600;">
                Close
                        </button>

                    </div>

                </div>

            </div>

        </div>
    </div>
</div>


<div
    class="modal fade"
    id="editInspectorModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div
    class="
        modal-dialog
        modal-dialog-centered
        modal-xl
    "
>

        <div
    class="modal-content border-0 overflow-hidden"
    style="
        border-radius:24px;
        background:#fef8f3;
        box-shadow:0 18px 50px rgba(0,0,0,.08);
    "
>

            <div class="row g-0">


                <div
    class="col-lg-4 d-none d-lg-block"
    style="
        background:#f9f4da;
        border-right:1px solid #e7e1c9;
        min-height:780px;
    "
>

                    <div
    class="
        p-5
        h-100
        d-flex
        flex-column
        justify-content-between
    "
>

                        <div>

                            <div
    style="
        width:72px;
        height:72px;
        border-radius:22px;
        background:#ffffff;
        border:1px solid #ebe5ca;
        display:flex;
        align-items:center;
        justify-content:center;
    "
>

                                <i
    class="fa-solid fa-id-card"
    style="
        color:#c4b417;
        font-size:1.6rem;
    "
></i>

                                </div>

                                <h2
    style="
        margin-top:28px;
        font-size:2rem;
        font-weight:700;
        line-height:1.3;
        color:#2e4836;
    "
>

Inspector Management

                                </h2>

                                <p
    style="
        margin-top:18px;
        font-size:.95rem;
        line-height:1.9;
        color:#6d766d;
    "
>

Register inspectors, assign terminals,
manage credentials, upload documents,
and maintain operational personnel records.

                                </p>

                            </div>


                            <div class="d-grid gap-3">

                                <div
    style="
        background:#ffffff;
        border:1px solid #ebe5ca;
        border-radius:18px;
        padding:18px;
    "
>

                                    <div class="d-flex align-items-center">

                                        <div
    style="
        width:46px;
        height:46px;
        border-radius:14px;
        background:#f9f4da;
        display:flex;
        align-items:center;
        justify-content:center;
        margin-right:14px;
    "
>

                                            <i class="fas fa-id-card" style="color:#c4b417;"></i>

                                        </div>

                                        <div>

                                            <div
    style="
        font-size:.9rem;
        font-weight:700;
        color:#2e4836;
    "
>

Inspector Records

                                            </div>

                                            <div
    style="
        font-size:.76rem;
        color:#7c847c;
    "
>

Personnel profiles and credentials

                                            </div>

                                        </div>

                                    </div>

                                </div>

                                <div
    style="
        background:#ffffff;
        border:1px solid #ebe5ca;
        border-radius:18px;
        padding:18px;
    "
>

                                    <div class="d-flex align-items-center">

                                        <div
    style="
        width:46px;
        height:46px;
        border-radius:14px;
        background:#f9f4da;
        display:flex;
        align-items:center;
        justify-content:center;
        margin-right:14px;
    "
>

                                            <i class="fas fa-building" style="color:#c4b417;"></i>

                                        </div>

                                        <div>

                                            <div
    style="
        font-size:.9rem;
        font-weight:700;
        color:#2e4836;
    "
>

Terminal Assignment

                                            </div>

                                            <div
    style="
        font-size:.76rem;
        color:#7c847c;
    "
>

Assign inspectors to terminals

                                            </div>

                                        </div>

                                    </div>

                                </div>

                                <div
    style="
        background:#ffffff;
        border:1px solid #ebe5ca;
        border-radius:18px;
        padding:18px;
    "
>

                                    <div class="d-flex align-items-center">

                                        <div
    style="
        width:46px;
        height:46px;
        border-radius:14px;
        background:#f9f4da;
        display:flex;
        align-items:center;
        justify-content:center;
        margin-right:14px;
    "
>

                                            <i class="fas fa-file-upload" style="color:#c4b417;"></i>

                                        </div>

                                        <div>

                                            <div
    style="
        font-size:.9rem;
        font-weight:700;
        color:#2e4836;
    "
>

Document Storage

                                            </div>

                                            <div
    style="
        font-size:.76rem;
        color:#7c847c;
    "
>

            Resume and supporting files

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="col-lg-8 col-12">

                        <div
    class="
        modal-header
        border-0
        px-5
        pt-5
        pb-0
    "
>

                            <div>

                                <h4
    style="
        margin:0;
        color:#2e4836;
        font-weight:700;
    "
>

Edit Inspector

                                </h4>

                                <div
    style="
        font-size:.84rem;
        color:#7d867c;
    "
>

Update inspector information

                                </div>

                            </div>

                            <button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

                            </div>

                            <div
    class="
        modal-body
        px-5
        py-4
    "
>

                                <form
    id="editInspectorForm"
    data-uv-context="inspector"
    enctype="multipart/form-data"
>

                                    <input
    type="hidden"
    id="edit_inspector_pk"
    name="inspector_pk"
>

                                    <div
    style="
        background:#ffffff;
        border:1px solid #ebe7d4;
        border-radius:22px;
        padding:28px;
    "
>


                                        <div
    style="
        font-size:.82rem;
        font-weight:700;
        color:#2e4836;
        margin-bottom:14px;
    "
>

Inspector Identity

                                        </div>

                                        <div class="row g-3">

                                            <div class="col-md-6">

                                                <label
            style="
                display:block;
                margin-bottom:8px;
                font-size:.8rem;
                font-weight:600;
                color:#687168;
            "
        >
            Inspector Name
                                                </label>

                                                <input
            type="text"
            id="edit_inspector_name"
            name="inspector_name"
            class="form-control"
            required
            style="
                height:54px;
                border-radius:16px;
                border:1px solid #dce3ce;
                box-shadow:none;
            "
        >

                                            </div>

                                            <div class="col-md-6">

                                                <label
            style="
                display:block;
                margin-bottom:8px;
                font-size:.8rem;
                font-weight:600;
                color:#687168;
            "
        >
            Inspector Identity
                                                </label>

                                                <input
            type="text"
            id="edit_inspector_identity"
            name="inspector_identity"
            class="form-control"
            placeholder="INS-IDENT-001"
            required
            style="
                height:54px;
                border-radius:16px;
                border:1px solid #dce3ce;
                box-shadow:none;
            "
        >

                                            </div>

                                            <div class="col-md-6">

                                                <label
            style="
                display:block;
                margin-bottom:8px;
                font-size:.8rem;
                font-weight:600;
                color:#687168;
            "
        >
            Department
                                                </label>

                                                <input
            type="text"
            id="edit_department"
            name="department"
            class="form-control"
            required
            style="
                height:54px;
                border-radius:16px;
                border:1px solid #dce3ce;
                box-shadow:none;
            "
        >

                                            </div>

                                            <div class="col-md-6">

                                                <label
            style="
                display:block;
                margin-bottom:8px;
                font-size:.8rem;
                font-weight:600;
                color:#687168;
            "
        >
            Experience (Years)
                                                </label>

                                                <input
            type="number"
            id="edit_experience_years"
            name="experience_years"
            class="form-control"
            min="0"
            required
            style="
                height:54px;
                border-radius:16px;
                border:1px solid #dce3ce;
                box-shadow:none;
            "
        >

                                            </div>

                                            <div class="col-md-6">

                                                <label
            style="
                display:block;
                margin-bottom:8px;
                font-size:.8rem;
                font-weight:600;
                color:#687168;
            "
        >
            Operational Assignment
                                                </label>

                                                <input
            type="text"
            id="edit_operational_assignment"
            name="operational_assignment"
            class="form-control"
            placeholder="Field Operations"
            style="
                height:54px;
                border-radius:16px;
                border:1px solid #dce3ce;
                box-shadow:none;
            "
        >

                                            </div>

                                            <div class="col-md-6">

                                                <label
            style="
                display:block;
                margin-bottom:8px;
                font-size:.8rem;
                font-weight:600;
                color:#687168;
            "
        >
            Assigned Terminal
                                                </label>

                                                <select
            id="edit_assigned_routes"
            name="assigned_routes"
            class="form-select"
            style="
                height:54px;
                border-radius:16px;
                border:1px solid #dce3ce;
            "
        >
                                                    <option value="">
                Loading terminals...
                                                    </option>
                                                </select>

                                            </div>

                                        </div>

                                    </div>


                                    <div
    style="
        background:#ffffff;
        border:1px solid #ebe7d4;
        border-radius:22px;
        padding:28px;
        margin-top:20px;
    "
>

                                        <div
    style="
        font-size:.82rem;
        font-weight:700;
        color:#2e4836;
        margin-bottom:14px;
    "
>

Contact Information

                                        </div>

                                        <div class="row g-3">

                                            <div class="col-md-6">

                                                <label
            style="
                display:block;
                margin-bottom:8px;
                font-size:.8rem;
                font-weight:600;
                color:#687168;
            "
        >
            Contact Number
                                                </label>

                                                <input
            type="text"
            id="edit_contact_number"
            name="contact_number"
            class="form-control"
            required
            style="
                height:54px;
                border-radius:16px;
                border:1px solid #dce3ce;
                box-shadow:none;
            "
        >

                                            </div>

                                            <div class="col-md-6">

                                                <label
            style="
                display:block;
                margin-bottom:8px;
                font-size:.8rem;
                font-weight:600;
                color:#687168;
            "
        >
            Email Address
                                                </label>

                                                <input
            type="email"
            id="edit_email"
            name="email"
            class="form-control"
            required
            style="
                height:54px;
                border-radius:16px;
                border:1px solid #dce3ce;
                box-shadow:none;
            "
        >

                                            </div>

                                        </div>

                                    </div>


                                    <div
    style="
        background:#ffffff;
        border:1px solid #ebe7d4;
        border-radius:22px;
        padding:28px;
        margin-top:20px;
    "
>

                                        <div
    style="
        font-size:.82rem;
        font-weight:700;
        color:#2e4836;
        margin-bottom:14px;
    "
>

Account Credentials

                                        </div>

                                        <div
    style="
        background:#f9f4da;
        border:1px solid #ece2ae;
        border-radius:18px;
        padding:20px;
    "
>

                                            <div class="row g-3">

                                                <input
    type="hidden"
    id="edit_account_id"
    name="account_id"
>

                                                <div class="col-md-4">

                                                    <label
    style="
        display:block;
        margin-bottom:8px;
        font-size:.8rem;
        font-weight:600;
        color:#687168;
    "
>
    System Inspector ID
                                                    </label>

                                                    <input
    type="text"
    id="edit_system_inspector_id"
    name="system_inspector_id"
    class="form-control"
    readonly
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
        background:#ffffff;
        font-weight:600;
        color:#2e4836;
    "
>

                                                </div>

                                                <div class="col-md-4">

                                                    <label
    style="
        display:block;
        margin-bottom:8px;
        font-size:.8rem;
        font-weight:600;
        color:#687168;
    "
>
    Role
                                                    </label>

                                                    <select
    id="edit_role"
    name="role"
    class="form-select"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
        box-shadow:none;
    "
>

                                                        <option value="inspector">
    Inspector
                                                        </option>

                                                        <option value="dispatcher">
    Dispatcher
                                                        </option>

                                                        <option value="admin">
    Admin
                                                        </option>

                                                    </select>

                                                </div>

                                                <div class="col-md-4">

                                                    <label
    style="
        display:block;
        margin-bottom:8px;
        font-size:.8rem;
        font-weight:600;
        color:#687168;
    "
>
    Password
                                                    </label>

                                                    <input
    type="password"
    id="edit_password"
    name="password"
    class="form-control"
    placeholder="Leave blank to keep current password"
>

                                                </div>

                                            </div>

                                        </div>

                                    </div>


                                    <div
    style="
        background:#ffffff;
        border:1px solid #ebe7d4;
        border-radius:22px;
        padding:28px;
        margin-top:20px;
    "
>

                                        <div
    style="
        font-size:.82rem;
        font-weight:700;
        color:#2e4836;
        margin-bottom:14px;
    "
>
    Documents & Notes
                                        </div>

                                        <div class="row g-3">


                                            <div class="col-md-4">

                                                <div style="
            background:#f9f4da;
            border:1px solid #ece2ae;
            border-radius:18px;
            padding:18px;
            height:100%;
        ">

                                                    <div style="
                display:flex;
                align-items:center;
                margin-bottom:12px;
            ">

                                                        <img
                    id="currentProfileImage"
                    src=""
                    alt="Inspector Photo"
                    style="
                        width:62px;
                        height:62px;
                        border-radius:50%;
                        object-fit:cover;
                        border:3px solid #e5e0a4;
                        background:#ffffff;
                        margin-right:12px;
                        flex-shrink:0;
                    "
                    onerror="this.onerror=null;this.src='/assets/images/admin/driversinfo/drivericon.png';"
                >

                                                        <div>

                                                            <div style="
                        font-size:.86rem;
                        font-weight:700;
                        color:#2e4836;
                    ">
                        Profile Picture
                                                            </div>

                                                            <div style="
                        font-size:.74rem;
                        color:#7c847c;
                    ">
                        Inspector photo
                                                            </div>

                                                        </div>

                                                    </div>

                                                    <input
                type="file"
                id="edit_profile_picture"
                name="profile_picture"
                class="form-control"
                accept="image/*"
                style="
                    border-radius:14px;
                    border:1px solid #dce3ce;
                "
            >

                                                </div>

                                            </div>


                                            <div class="col-md-4">

                                                <div style="
            background:#f9f4da;
            border:1px solid #ece2ae;
            border-radius:18px;
            padding:18px;
            height:100%;
        ">

                                                    <div style="
                display:flex;
                align-items:center;
                margin-bottom:12px;
            ">

                                                        <div style="
                    width:42px;
                    height:42px;
                    border-radius:12px;
                    background:#ffffff;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    margin-right:12px;
                ">

                                                            <i class="fas fa-file-pdf" style="color:#c4b417;"></i>

                                                        </div>

                                                        <div>

                                                            <div style="
                        font-size:.86rem;
                        font-weight:700;
                        color:#2e4836;
                    ">
                        Resume
                                                            </div>

                                                            <div style="
                        font-size:.74rem;
                        color:#7c847c;
                    ">
                        PDF / DOC / DOCX
                                                            </div>

                                                        </div>

                                                    </div>

                                                    <input
                type="file"
                id="edit_resume_file"
                name="resume_file"
                class="form-control"
                accept=".pdf,.doc,.docx"
                style="
                    border-radius:14px;
                    border:1px solid #dce3ce;
                "
            >

                                                    <div
                id="currentResumePreview"
                class="mt-3"
                style="
                    display:none;
                    background:#fff;
                    border:1px solid #ece2ae;
                    border-radius:14px;
                    padding:10px;
                "
            >

                                                        <div style="
                    font-size:.75rem;
                    color:#7c847c;
                    margin-bottom:8px;
                ">
                    Current Resume
                                                        </div>

                                                        <a
                    id="currentResumeLink"
                    target="_blank"
                    class="btn btn-sm"
                    style="
                        background:#295738;
                        color:#fff;
                        border-radius:12px;
                    "
                >
                                                            <i class="fas fa-file-pdf me-2"></i>
                    View Resume
                                                        </a>

                                                    </div>

                                                </div>

                                            </div>


                                            <div class="col-md-4">

                                                <div style="
            background:#f9f4da;
            border:1px solid #ece2ae;
            border-radius:18px;
            padding:18px;
            height:100%;
        ">

                                                    <div style="
                display:flex;
                align-items:center;
                margin-bottom:12px;
            ">

                                                        <div style="
                    width:42px;
                    height:42px;
                    border-radius:12px;
                    background:#ffffff;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    margin-right:12px;
                ">

                                                            <i class="fas fa-folder-open" style="color:#c4b417;"></i>

                                                        </div>

                                                        <div>

                                                            <div style="
                        font-size:.86rem;
                        font-weight:700;
                        color:#2e4836;
                    ">
                        Supporting Docs
                                                            </div>

                                                            <div style="
                        font-size:.74rem;
                        color:#7c847c;
                    ">
                        Certificates & Records
                                                            </div>

                                                        </div>

                                                    </div>

                                                    <input
                type="file"
                id="edit_supporting_docs"
                name="supporting_docs"
                class="form-control"
                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                style="
                    border-radius:14px;
                    border:1px solid #dce3ce;
                "
            >

                                                    <div
                id="currentDocsPreview"
                class="mt-3"
                style="
                    display:none;
                    background:#fff;
                    border:1px solid #ece2ae;
                    border-radius:14px;
                    padding:10px;
                "
            >

                                                        <div style="
                    font-size:.75rem;
                    color:#7c847c;
                    margin-bottom:8px;
                ">
                    Current Document
                                                        </div>

                                                        <a
                    id="currentDocsLink"
                    target="_blank"
                    class="btn btn-sm"
                    style="
                        background:#295738;
                        color:#fff;
                        border-radius:12px;
                    "
                >
                                                            <i class="fas fa-folder-open me-2"></i>
                    View Document
                                                        </a>

                                                    </div>

                                                </div>

                                            </div>


                                            <div class="col-12">

                                                <label
            style="
                display:block;
                margin-bottom:10px;
                font-size:.82rem;
                font-weight:700;
                color:#2e4836;
            "
        >
            Additional Notes
                                                </label>

        <textarea
            id="edit_additional_notes"
            name="additional_notes"
            class="form-control"
            rows="4"
            placeholder="Enter remarks, certifications, qualifications, or additional information..."
            style="
                border-radius:18px;
                border:1px solid #dce3ce;
                resize:none;
            "
        ></textarea>

                                            </div>

                                        </div>

                                    </div>

                                </form>


                                <div style="
    margin-top:20px;
    background:#f9f4da;
    border:1px solid #ece2ae;
    border-radius:18px;
    padding:18px;
">

                                    <div class="d-flex">

                                        <div style="
    width:42px;
    height:42px;
    border-radius:12px;
    background:#ffffff;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-right:14px;
">

                                            <i class="fas fa-user-check" style="color:#c4b417;"></i>

                                        </div>

                                        <div>

                                            <div style="
    font-size:.86rem;
    font-weight:700;
    color:#2e4836;
">

Inspector Registration

                                            </div>

                                            <div style="
    margin-top:4px;
    font-size:.78rem;
    line-height:1.8;
    color:#737b73;
">

The system will automatically create
the inspector account, generate the
Inspector ID, store uploaded documents,
and optionally send credentials via email.

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <div class="d-flex gap-3 mt-4">

                                    <button
    type="button"
    class="btn flex-fill"
    data-bs-dismiss="modal"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #d7d7d7;
        background:#ffffff;
        color:#5c675c;
        font-weight:600;
    "
>

    Cancel

                                    </button>

                                    <button
    type="button"
    id="updateInspectorBtn"
    class="btn flex-fill"
    style="
        height:54px;
        border:none;
        border-radius:16px;
        background:#295738;
        color:#ffffff;
        font-size:.92rem;
        font-weight:700;
        letter-spacing:.2px;
    "
>

                                        <i class="fas fa-user-edit me-2"></i>

    Update Inspector

                                    </button>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div
    class="modal fade"
    id="archiveInspectorModal"
    tabindex="-1"
    aria-hidden="true"
>

            <div class="modal-dialog modal-dialog-centered">

                <div
            class="modal-content border-0"
            style="border-radius:18px;background:#fffdf8;box-shadow:0 18px 50px rgba(0,0,0,.1);"
        >

                    <div class="modal-body p-4">

                        <div class="d-flex align-items-center gap-3 mb-3">

                            <div style="width:48px;height:48px;border-radius:14px;background:#fdecea;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="bi bi-archive" style="color:#b94a48;font-size:1.2rem;"></i>
                            </div>

                            <div>
                                <h5 class="mb-0 fw-bold" style="color:#2e4836;">Archive Inspector?</h5>
                            </div>

                        </div>

                        <div style="font-size:.9rem;color:#5b655b;">

                            <div class="mb-1">
                                <span style="font-weight:600;color:#2e4836;">Inspector:</span>
                                <span id="archiveInspectorName">—</span>
                            </div>

                            <div class="mb-3">
                                <span style="font-weight:600;color:#2e4836;">ID:</span>
                                <span id="archiveInspectorCode">—</span>
                            </div>

                            <p class="mb-0" style="color:#7d857c;line-height:1.7;">
                        Archiving this inspector will remove them from the active inspector list. Their existing records and history will remain available.
                            </p>

                        </div>

                    </div>

                    <div class="modal-footer border-0 pb-4 pt-0 px-4">

                        <button
                    type="button"
                    class="btn"
                    data-bs-dismiss="modal"
                    style="height:46px;padding:0 24px;border-radius:12px;background:#ffffff;border:1px solid #dfe5d1;color:#687168;font-weight:600;"
                >
                    Cancel
                        </button>

                        <button
                    type="button"
                    class="btn text-white"
                    onclick="confirmArchiveInspector()"
                    style="height:46px;padding:0 24px;border-radius:12px;border:none;background:#b94a48;font-weight:600;"
                >
                            <i class="bi bi-archive me-2"></i>
                    Archive Inspector
                        </button>

                    </div>

                </div>

            </div>

        </div>


        <div
    class="modal fade"
    id="viewInspectorModal"
    tabindex="-1"
    aria-hidden="true"
>

            <div class="modal-dialog modal-dialog-centered modal-lg">

                <div
            class="modal-content border-0"
            style="border-radius:20px;background:#fffdf8;box-shadow:0 18px 50px rgba(0,0,0,.1);overflow:hidden;"
        >

                    <div class="modal-header border-0" style="padding:26px 30px 0;">

                        <div class="d-flex align-items-center gap-3">

                            <img
                        id="viewInspectorPhoto"
                        class="rounded-circle"
                        style="width:76px;height:76px;object-fit:cover;border:3px solid #e5e0a4;flex-shrink:0;"
                        onerror="this.onerror=null;this.src='/assets/images/admin/driversinfo/drivericon.png';"
                    >

                            <div style="min-width:0;flex:1;">
                                <h5 id="viewInspectorName" class="mb-1 fw-semibold" style="color:#2e4836;"></h5>
                                <div id="viewInspectorCode" style="font-size:.8rem;color:#8a9a8a;"></div>
                                <div id="viewInspectorStatus" class="mt-1"></div>
                            </div>

                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                    </div>

                    <div class="modal-body" style="padding:22px 30px 6px;">


                        <div class="inspector-view-section-title">
                            <i class="bi bi-telephone me-1" style="color:#295738;"></i>
                    Contact Information
                        </div>

                        <div class="row g-3 mb-4">

                            <div class="col-md-6">
                                <div class="inspector-view-item">
                                    <div class="i-label">Phone</div>
                                    <div class="i-value" id="viewPhone">—</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="inspector-view-item">
                                    <div class="i-label">Email</div>
                                    <div class="i-value" id="viewEmail" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title=""></div>
                                </div>
                            </div>

                        </div>


                        <div class="inspector-view-section-title">
                            <i class="bi bi-shield-check me-1" style="color:#295738;"></i>
                    Employment Information
                        </div>

                        <div class="row g-3 mb-4">

                            <div class="col-md-6">
                                <div class="inspector-view-item">
                                    <div class="i-label">Department</div>
                                    <div class="i-value" id="viewDepartment">—</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="inspector-view-item">
                                    <div class="i-label">Role</div>
                                    <div class="i-value" id="viewRole">—</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="inspector-view-item">
                                    <div class="i-label">Experience</div>
                                    <div class="i-value" id="viewExperience">—</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="inspector-view-item">
                                    <div class="i-label">Assignment</div>
                                    <div class="i-value" id="viewAssignment">—</div>
                                </div>
                            </div>

                        </div>


                        <div class="inspector-view-section-title">
                            <i class="bi bi-geo-alt me-1" style="color:#295738;"></i>
                    Assignment Information
                        </div>

                        <div class="row g-3 mb-2">

                            <div class="col-md-6">
                                <div class="inspector-view-item">
                                    <div class="i-label">Terminal</div>
                                    <div class="i-value" id="viewTerminal">—</div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="inspector-view-item">
                                    <div class="i-label">Registered</div>
                                    <div class="i-value" id="viewRegistered">—</div>
                                </div>
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer border-0" style="padding:14px 30px 26px;">

                        <button
                    type="button"
                    class="btn"
                    data-bs-dismiss="modal"
                    style="height:44px;padding:0 24px;border-radius:12px;background:#ffffff;border:1px solid #dfe5d1;color:#687168;font-weight:600;"
                >
                    Close
                        </button>

                        <button
                    type="button"
                    class="btn"
                    onclick="editInspector(currentViewInspectorId)"
                    style="height:44px;padding:0 24px;border-radius:12px;border:1px solid #c9d8c9;background:#ffffff;color:#295738;font-weight:600;"
                >
                            <i class="bi bi-pencil me-2"></i>
                    Edit
                        </button>

                    </div>

                </div>

            </div>

        </div>

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />

        <script>
            let allInspectors = [];
            let filteredInspectors = [];
            let page = 1;
            let perPage = 6;
            let pendingArchiveId = null;
            let currentViewInspectorId = null;

            const FALLBACK_PHOTO = '/assets/images/admin/driversinfo/drivericon.png';

            function photoUrl(value) {
                const v = (value === null || value === undefined) ? '' : String(value).trim();
                if (v === '' || v === 'null') {
                    return FALLBACK_PHOTO;
                }
                if (/^(https?:)?\/\//i.test(v) || /^data:/i.test(v)) {
                    return v;
                }
                if (/^\/+/.test(v)) {
                    return v.replace(/^\/+/, '/');
                }
                if (v.indexOf('/') !== -1) {
                    return '/' + v.replace(/^\/+/, '');
                }
                return '/uploads/inspectors/photos/' + v.replace(/^\/+/, '');
            }

            function inspectorPhoto(inspector) {
                return photoUrl(inspector && inspector.profile_picture);
            }

            function displayValuePlain(value, fallback) {
                const v = (value === null || value === undefined) ? '' : String(value).trim();
                return v !== '' ? v : (fallback || 'Not Assigned');
            }

            function displayValue(value) {
                const v = displayValuePlain(value);
                if (v === 'Not Assigned') {
                    return '<span style="color:#a8b3a8;">Not Assigned</span>';
                }
                return v;
            }

            function statusBadge(status) {
                const s = String(status || 'active').toLowerCase();
                if (s === 'inactive') {
                    return '<span class="badge" style="background:#e9ecef;color:#6c757d;font-weight:600;font-size:.72rem;padding:5px 12px;border-radius:20px;">● Inactive</span>';
                }
                if (s === 'archived') {
                    return '<span class="badge" style="background:#fdecea;color:#c0392b;font-weight:600;font-size:.72rem;padding:5px 12px;border-radius:20px;">● Archived</span>';
                }
                return '<span class="badge" style="background:#e8f5e9;color:#2e7d32;font-weight:600;font-size:.72rem;padding:5px 12px;border-radius:20px;">● Active</span>';
            }

            function roleLabel(role) {
                const r = displayValuePlain(role, 'inspector');
                return r.charAt(0).toUpperCase() + r.slice(1);
            }

            function formatDate(dateStr) {
                if (!dateStr) {
                    return '';
                }
                const d = new Date(dateStr);
                if (isNaN(d.getTime())) {
                    return dateStr;
                }
                return d.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
            }

            async function loadInspectors() {

                const container =
                document.getElementById(
                'inspectorsContainer'
                );

                try {

                    container.innerHTML =
                    `
            <div class="col-12 text-center py-4">
                Loading inspectors...
            </div>
        `;

                    const response =
                    await fetch(
                    '/admin/api/Inspectors/GetInspectors.php'
                    );

                    const result =
                    await response.json();

                    if (
                    !result.success
                    ) {
                        throw new Error(
                        result.message ||
                        'Failed to load inspectors'
                        );
                    }

                    allInspectors =
                    result.inspectors || [];

                    filteredInspectors =
                    [...allInspectors];

                    const inspectorCount =
                    document.getElementById(
                    'inspectorCount'
                    );

                    if (
                    inspectorCount
                    ) {
                        inspectorCount.innerText =
                        `${allInspectors.length} record${allInspectors.length !== 1 ? 's' : ''}`;
                    }

                    if (
                    allInspectors.length === 0
                    ) {

                        container.innerHTML =
                        `
                <div class="col-12">

                    <div
                        class="alert text-center"
                        style="background:#fffdf8;border:1px solid #e5e0a4;color:#8a9a8a;border-radius:16px;"
                    >
                        No inspectors found.
                    </div>

                </div>
            `;

                        const paginationEl =
                        document.getElementById(
                        'inspectorPagination'
                        );

                        if (paginationEl) {
                            paginationEl.innerHTML = '';
                        }

                        return;
                    }

                    renderInspectors();

                }
                catch (error) {

                    console.error(
                    'Inspector Load Error:',
                    error
                    );

                    container.innerHTML =
                    `
            <div class="col-12">

                <div
                    class="alert alert-danger text-center"
                    style="border-radius:16px;"
                >
                    Failed to load inspectors.
                </div>

                <div
                    class="text-center small text-muted mt-2"
                >
                    Check browser console
                    for details.
                </div>

            </div>
        `;
                }

            }

            function renderInspectors() {

                const container =
                document.getElementById(
                'inspectorsContainer'
                );

                if (!container) {
                    return;
                }

                container.innerHTML = '';

                const paginationEl =
                document.getElementById(
                'inspectorPagination'
                );

                if (
                filteredInspectors.length === 0
                ) {

                    container.innerHTML =
                    `
            <div class="col-12">

                <div
                    class="alert text-center"
                    style="background:#fffdf8;border:1px solid #e5e0a4;color:#8a9a8a;border-radius:16px;"
                >
                    No inspectors match the current filters.
                </div>

            </div>
        `;

                    if (paginationEl) {
                        paginationEl.innerHTML = '';
                    }

                    return;
                }

                const totalPages =
                Math.max(
                1,
                Math.ceil(
                filteredInspectors.length / perPage
                )
                );

                if (page > totalPages) {
                    page = totalPages;
                }

                const start =
                (page - 1) * perPage;

                const pageInspectors =
                filteredInspectors.slice(
                start,
                start + perPage
                );

                pageInspectors.forEach(inspector => {

                    const photo =
                    inspectorPhoto(inspector);

                    const name =
                    displayValuePlain(
                    inspector.inspector_name,
                    'Unnamed Inspector'
                    );

                    const inspectorCode =
                    displayValuePlain(
                    inspector.inspector_identity || inspector.inspector_id,
                    '—'
                    );

                    const department =
                    displayValue(
                    inspector.department
                    );

                    const role =
                    roleLabel(
                    inspector.role
                    );

                    const experience =
                    displayValuePlain(
                    inspector.experience_years,
                    '0'
                    ) + ' Year' + (Number(inspector.experience_years) === 1 ? '' : 's');

                    const assignment =
                    displayValue(
                    inspector.operational_assignment
                    );

                    const terminalText =
                    [inspector.terminal_name, inspector.terminal_code].filter(Boolean).join(' ');

                    const route =
                    displayValue(
                    terminalText || inspector.assigned_routes
                    );

                    const registered =
                    displayValuePlain(
                    formatDate(inspector.created_at),
                    '—'
                    );

                    const phone =
                    displayValue(
                    inspector.contact_number
                    );

                    const emailRaw =
                    displayValuePlain(
                    inspector.email,
                    ''
                    );

                    const emailHtml =
                    emailRaw !== ''
                    ? '<span class="inspector-email-value" title="' + emailRaw + '">' + emailRaw + '</span>'
                    : '<span style="color:#a8b3a8;">Not Assigned</span>';

                    const card =
                    `
        <div class="col-12 col-md-6 col-xl-4 d-flex">

            <div class="inspector-card w-100 d-flex flex-column">

                <div class="inspector-card-accent"></div>

                <div class="p-3 d-flex align-items-start gap-3">

                    <img
                        src="${photo}"
                        class="rounded-circle"
                        onerror="this.onerror=null;this.src='${FALLBACK_PHOTO}';"
                        style="width:76px;height:76px;object-fit:cover;border:3px solid #e5e0a4;flex-shrink:0;"
                    >

                    <div class="flex-grow-1" style="min-width:0;">

                        <h6
                            class="mb-0 fw-semibold"
                            style="color:#2e4836;font-size:.95rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            title="${name}"
                        >
                            ${name}
                        </h6>

                        <div class="small mt-1" style="color:#8a9a8a;">
                            ${inspectorCode}
                        </div>

                        <div class="mt-1">
                            ${statusBadge(inspector.status)}
                        </div>

                    </div>

                    <div class="dropdown">

                        <button
                            class="btn btn-sm inspector-menu-btn"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            style="background:none;border:none;color:#8a9a8a;font-size:1.1rem;padding:2px 6px;"
                        >
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end" style="border:1px solid #e5e0a4;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.08);font-size:.85rem;">

                            <li>
                                <a class="dropdown-item" href="javascript:void(0)" onclick="openViewInspector(${inspector.inspector_pk})">
                                    <i class="bi bi-eye me-2" style="color:#295738;"></i>View Profile
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item" href="javascript:void(0)" onclick="editInspector(${inspector.inspector_pk})">
                                    <i class="bi bi-pencil me-2" style="color:#295738;"></i>Edit Inspector
                                </a>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <li>
                                <a class="dropdown-item" href="javascript:void(0)" onclick="openArchiveConfirm(${inspector.inspector_pk})" style="color:#b94a48;">
                                    <i class="bi bi-archive me-2"></i>Archive Inspector
                                </a>
                            </li>

                        </ul>

                    </div>

                </div>

                <hr class="mx-3 my-1" style="border-color:#f0ecdc;">

                <div class="px-3 pb-1">

                    <div class="row g-2">

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-diagram-3 inspector-info-icon"></i>
                                <span class="inspector-info-label">Department</span>
                            </div>
                            <div class="inspector-info-value">${department}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-person-badge inspector-info-icon"></i>
                                <span class="inspector-info-label">Role</span>
                            </div>
                            <div class="inspector-info-value">${role}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-star inspector-info-icon"></i>
                                <span class="inspector-info-label">Experience</span>
                            </div>
                            <div class="inspector-info-value">${experience}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-briefcase inspector-info-icon"></i>
                                <span class="inspector-info-label">Assignment</span>
                            </div>
                            <div class="inspector-info-value">${assignment}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-geo-alt inspector-info-icon"></i>
                                <span class="inspector-info-label">Terminal</span>
                            </div>
                            <div class="inspector-info-value" title="${displayValuePlain(terminalText || inspector.assigned_routes)}">${route}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-calendar3 inspector-info-icon"></i>
                                <span class="inspector-info-label">Registered</span>
                            </div>
                            <div class="inspector-info-value">${registered}</div>

                        </div>

                    </div>

                </div>

                <hr class="mx-3 my-1" style="border-color:#f0ecdc;">

                <div class="px-3 py-1 d-flex gap-3">

                    <div class="flex-grow-1" style="min-width:0;">

                        <div class="d-flex align-items-center gap-1 mb-1">
                            <i class="bi bi-telephone inspector-info-icon"></i>
                            <span class="inspector-info-label">Phone</span>
                        </div>
                        <div class="inspector-info-value">${phone}</div>

                    </div>

                    <div class="flex-grow-1" style="min-width:0;">

                        <div class="d-flex align-items-center gap-1 mb-1">
                            <i class="bi bi-envelope inspector-info-icon"></i>
                            <span class="inspector-info-label">Email</span>
                        </div>
                        <div class="inspector-info-value">${emailHtml}</div>

                    </div>

                </div>

                <div class="px-3 pt-2 pb-3 mt-auto">

                    <div class="row g-2">

                        <div class="col-6">

                            <button
                                type="button"
                                class="btn w-100 inspector-action-btn btn-view"
                                onclick="openViewInspector(${inspector.inspector_pk})"
                            >
                                <i class="bi bi-eye me-1"></i>
                                View Profile
                            </button>

                        </div>

                        <div class="col-6">

                            <button
                                type="button"
                                class="btn w-100 inspector-action-btn btn-edit"
                                onclick="editInspector(${inspector.inspector_pk})"
                            >
                                <i class="bi bi-pencil me-1"></i>
                                Edit
                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </div>
        `;

                    container.innerHTML += card;

                });

                renderPagination(start, start + pageInspectors.length, totalPages);

            }

            function paginationItems(total, current) {

                if (total <= 7) {
                    return Array.from({ length: total }, (_, i) => i + 1);
                }

                const set = new Set([1, total]);

                for (let p = Math.max(1, current - 1); p <= Math.min(total, current + 1); p++) {
                    set.add(p);
                }

                const sorted = [...set].sort((a, b) => a - b);

                const out = [];
                let prev = 0;

                sorted.forEach(v => {
                    if (v - prev > 1) {
                        out.push('...');
                    }
                    out.push(v);
                    prev = v;
                });

                return out;
            }

            function renderPagination(start, end, totalPages) {

                const el =
                document.getElementById(
                'inspectorPagination'
                );

                if (!el) {
                    return;
                }

                if (
                filteredInspectors.length === 0
                ) {
                    el.innerHTML = '';
                    return;
                }

                const range =
                `<span class="page-range">Showing ${start + 1}–${end} of ${filteredInspectors.length} inspectors</span>`;

                const items =
                paginationItems(totalPages, page);

                const pages = items.map(p => {
                    if (p === '...') {
                        return '<span class="page-ellipsis">…</span>';
                    }
                    return `<button type="button" class="page-btn${p === page ? ' active' : ''}" onclick="goToPage(${p})">${p}</button>`;
                }).join('');

                const prev =
                `<button type="button" class="page-nav${page <= 1 ? ' disabled' : ''}" onclick="goToPage(${page - 1})" ${page <= 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`;

                const next =
                `<button type="button" class="page-nav${page >= totalPages ? ' disabled' : ''}" onclick="goToPage(${page + 1})" ${page >= totalPages ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`;

                const perPageOptions =
                [6, 9, 12].map(n =>
                `<option value="${n}"${n === perPage ? ' selected' : ''}>${n} per page</option>`
                ).join('');

                const perPageSelect =
                `<select class="form-select page-per" onchange="changePerPage(this.value)" aria-label="Inspectors per page">${perPageOptions}</select>`;

                el.innerHTML =
                `<div class="inspector-pagination-bar">` +
                range +
                `<div class="page-controls">${prev}${pages}${next}</div>` +
                perPageSelect +
                `</div>`;

            }

            function goToPage(p) {

                page = p;
                renderInspectors();

            }

            function changePerPage(value) {

                perPage = parseInt(value, 10);
                page = 1;
                renderInspectors();

            }

            function searchInspectors() {

                const keyword =
                (document.getElementById('inspectorSearch')?.value || '')
                .toLowerCase()
                .trim();

                const status =
                document.getElementById('statusFilter')?.value || '';

                filteredInspectors =
                allInspectors.filter(inspector => {

                    const haystack =
                    [
                    inspector.inspector_name,
                    inspector.inspector_identity,
                    inspector.inspector_id,
                    inspector.terminal_name,
                    inspector.department,
                    inspector.role
                    ]
                    .map(v => (v || '').toLowerCase())
                    .join(' ');

                    const matchesSearch =
                    !keyword ||
                    haystack.includes(keyword);

                    const matchesStatus =
                    !status ||
                    (inspector.status || '').toLowerCase() === status.toLowerCase();

                    return matchesSearch && matchesStatus;

                });

                page = 1;

                renderInspectors();

            }

            function showViewInspector(inspector) {

                currentViewInspectorId =
                inspector.inspector_pk;

                document.getElementById(
                'viewInspectorPhoto'
                ).src =
                inspectorPhoto(inspector);

                document.getElementById(
                'viewInspectorName'
                ).textContent =
                displayValuePlain(
                inspector.inspector_name,
                'Unnamed Inspector'
                );

                document.getElementById(
                'viewInspectorCode'
                ).textContent =
                displayValuePlain(
                inspector.inspector_identity || inspector.inspector_id,
                '—'
                );

                document.getElementById(
                'viewInspectorStatus'
                ).innerHTML =
                statusBadge(inspector.status);

                document.getElementById(
                'viewPhone'
                ).textContent =
                displayValuePlain(
                inspector.contact_number
                );

                document.getElementById(
                'viewEmail'
                ).textContent =
                displayValuePlain(
                inspector.email
                );

                document.getElementById(
                'viewEmail'
                ).title =
                displayValuePlain(
                inspector.email
                );

                document.getElementById(
                'viewDepartment'
                ).textContent =
                displayValuePlain(
                inspector.department
                );

                document.getElementById(
                'viewRole'
                ).textContent =
                roleLabel(
                inspector.role
                );

                document.getElementById(
                'viewExperience'
                ).textContent =
                displayValuePlain(
                inspector.experience_years,
                '0'
                ) + ' Year' + (Number(inspector.experience_years) === 1 ? '' : 's');

                document.getElementById(
                'viewAssignment'
                ).textContent =
                displayValuePlain(
                inspector.operational_assignment
                );

                const terminalText =
                [inspector.terminal_name, inspector.terminal_code].filter(Boolean).join(' ');

                document.getElementById(
                'viewTerminal'
                ).textContent =
                displayValuePlain(
                terminalText || inspector.assigned_routes
                );

                document.getElementById(
                'viewRegistered'
                ).textContent =
                displayValuePlain(
                formatDate(inspector.created_at),
                '—'
                );

                new bootstrap.Modal(
                document.getElementById(
                'viewInspectorModal'
                )
                ).show();

            }

            function openViewInspector(id) {

                const inspector =
                allInspectors.find(
                i => Number(i.inspector_pk) === Number(id)
                );

                if (inspector) {
                    showViewInspector(inspector);
                    return;
                }

                fetch(
                `/admin/api/Inspectors/GetInspector.php?id=${id}`
                )
                .then(r => r.json())
                .then(result => {
                    if (result.success && result.inspector) {
                        showViewInspector(result.inspector);
                    }
                    else {
                        showAlert('error', 'Failed', 'Unable to load inspector profile.');
                    }
                })
                .catch (error => {
                    console.error(error);
                    showAlert('error', 'Error', 'Something went wrong.');
                });

            }

            function openArchiveConfirm(id) {

                const inspector =
                allInspectors.find(
                i => Number(i.inspector_pk) === Number(id)
                ) || {};

                pendingArchiveId = id;

                document.getElementById(
                'archiveInspectorName'
                ).textContent =
                inspector.inspector_name || '—';

                document.getElementById(
                'archiveInspectorCode'
                ).textContent =
                inspector.inspector_identity || inspector.inspector_id || '—';

                new bootstrap.Modal(
                document.getElementById(
                'archiveInspectorModal'
                )
                ).show();

            }

            async function confirmArchiveInspector() {

                const modal =
                bootstrap.Modal.getInstance(
                document.getElementById(
                'archiveInspectorModal'
                )
                );

                if (modal) {
                    modal.hide();
                }

                if (pendingArchiveId === null) {
                    return;
                }

                const id =
                pendingArchiveId;

                pendingArchiveId = null;

                const formData =
                new FormData();

                formData.append(
                'inspector_pk',
                id
                );

                try {

                    const response =
                    await fetch(
                    '/admin/api/Inspectors/ArchiveInspector.php',
                    {
                        method: 'POST',
                        body: formData
                    }
                    );

                    const data =
                    await response.json();

                    if (
                    data.success
                    ) {

                        showAlert('success', 'Archived', 'Inspector archived successfully.');

                        loadInspectors();

                    }
                    else {

                        showAlert('error', 'Failed', 'Unable to archive inspector.');

                    }

                }
                catch (error) {

                    console.error(error);

                    showAlert('error', 'Error', 'Something went wrong.');

                }

            }

            async function openArchivedInspectors() {

                const modal =
                new bootstrap.Modal(
                document.getElementById(
                'archivedInspectorsModal'
                )
                );

                modal.show();

                const container =
                document.getElementById(
                'archivedInspectorsBody'
                );

                const countBadge =
                document.getElementById(
                'archivedInspectorCount'
                );

                const searchInput =
                document.getElementById(
                'archivedInspectorSearch'
                );

                if (searchInput) {
                    searchInput.value = '';
                }

                container.innerHTML =
                '<div style="text-align:center;padding:60px 20px;color:#70796f;">' +
                '<i class="fa-solid fa-spinner fa-spin" style="font-size:2rem;color:#c4b417;"></i>' +
                '<p style="margin-top:16px;font-size:.9rem;">Loading archived inspectors...</p>' +
                '</div>';

                let archived = [];

                try {

                    const res =
                    await fetch(
                    '/admin/api/Inspectors/GetArchivedInspectors.php'
                    );

                    const json =
                    await res.json();

                    if (
                    json.success &&
                    json.inspectors
                    ) {
                        archived = json.inspectors;
                    }

                } catch (e) {
                    console.error(
                    'Failed to load archived inspectors:',
                    e
                    );
                }

                if (countBadge) {

                    countBadge.innerText =
                    `${archived.length} Archived`;

                }

                if (
                archived.length === 0
                ) {

                    container.innerHTML =
                    '<div style="text-align:center;padding:60px 20px;color:#70796f;">' +
                    '<i class="fa-solid fa-box-open" style="font-size:2rem;color:#c4b417;"></i>' +
                    '<p style="margin-top:16px;font-size:.9rem;">No archived inspectors found.</p>' +
                    '</div>';

                    return;
                }

                let tableRows = '';

                archived.forEach(inspector => {

                    const photo =
                    inspectorPhoto(inspector);

                    tableRows += '<tr style="border-bottom:1px solid #f0ede0;">';
                    tableRows += '<td style="padding:12px 16px;display:flex;align-items:center;gap:12px;">';
                    tableRows += '<img src="' + photo + '" onerror="this.onerror=null;this.src=\'' + FALLBACK_PHOTO + '\';" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #e5e0a4;">';
                    tableRows += '<div><div style="font-weight:600;color:#2e4836;font-size:0.85rem;">' + (inspector.inspector_name || '-') + '</div>';
                    tableRows += '<div style="font-size:0.75rem;color:#888;">' + (inspector.inspector_id || '-') + '</div></div>';
                    tableRows += '</td>';
                    tableRows += '<td style="padding:12px 16px;color:#475569;font-size:0.85rem;">' + (inspector.department || '-') + '</td>';
                    tableRows += '<td style="padding:12px 16px;color:#475569;font-size:0.85rem;">' + (inspector.contact_number || '-') + '</td>';
                    tableRows += '<td style="padding:12px 16px;"><span style="background:#f8d7da;color:#721c24;padding:4px 14px;border-radius:20px;font-size:0.75rem;font-weight:600;display:inline-block;">Archived</span></td>';
                    tableRows += '<td style="padding:12px 16px;display:flex;gap:8px;">';
                    tableRows += '<button class="btn btn-sm" onclick="openViewInspector(' + inspector.inspector_pk + ')" style="background:#ffffff;color:#295738;border:1px solid #c9d8c9;border-radius:8px;padding:6px 14px;font-size:0.75rem;font-weight:600;"><i class="fa-solid fa-eye me-1"></i>View</button>';
                    tableRows += '<button class="btn btn-sm" onclick="restoreInspector(' + inspector.inspector_pk + ')" style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:8px;padding:6px 14px;font-size:0.75rem;font-weight:600;"><i class="fa-solid fa-rotate-left me-1"></i>Restore</button>';
                    tableRows += '</td>';
                    tableRows += '</tr>';

                });

                container.innerHTML =
                '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">' +
                '<thead><tr style="background:linear-gradient(135deg,#f9f4da 0%,#f5edd0 100%);">' +
                '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Inspector</th>' +
                '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Department</th>' +
                '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Contact</th>' +
                '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Status</th>' +
                '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Action</th>' +
                '</tr></thead><tbody>' + tableRows + '</tbody></table></div>';

            }

            function filterArchivedInspectors() {

                const keyword =
                (document.getElementById('archivedInspectorSearch')?.value || '')
                .toLowerCase()
                .trim();

                document
                .querySelectorAll(
                '#archivedInspectorsBody tbody tr'
                )
                .forEach(row => {

                    row.style.display =
                    row.innerText.toLowerCase().includes(keyword)
                    ? ''
                    : 'none';

                });

            }

            async function restoreInspector(id) {

                showConfirm('Restore Inspector?', 'This inspector will become active again.', async function () {

                    try {

                        const formData =
                        new FormData();

                        formData.append(
                        'inspector_pk',
                        id
                        );

                        const response =
                        await fetch(
                        '/admin/api/Inspectors/RestoreInspector.php',
                        {
                            method: 'POST',
                            body: formData
                        }
                        );

                        const data =
                        await response.json();

                        if (
                        data.success
                        ) {

                            showAlert('success', 'Inspector Restored', 'The inspector has been restored.');

                            loadInspectors();

                            const archivedModal =
                            bootstrap.Modal
                            .getInstance(
                            document.getElementById(
                            'archivedInspectorsModal'
                            )
                            );

                            if (
                            archivedModal
                            ) {
                                openArchivedInspectors();
                            }

                        }
                        else {

                            showAlert('error', 'Failed', 'Unable to restore inspector.');

                        }

                    }
                    catch (error) {

                        console.error(error);

                        showAlert('error', 'Error', 'Something went wrong.');

                    }

                });

            }

            async function editInspector(id) {

                try {

                    const response =
                    await fetch(
                    `/admin/api/Inspectors/GetInspector.php?id=${id}`
                    );

                    const result =
                    await response.json();

                    if (!result.success) {

                        showAlert('error', 'Error', result.message || 'Unable to load inspector.');

                        return;
                    }

                    const inspector =
                    result.inspector;

                    document.getElementById(
                    'edit_inspector_pk'
                    ).value =
                    inspector.inspector_pk ?? '';

                    if (
                    document.getElementById(
                    'edit_account_id'
                    )
                    ) {

                        document.getElementById(
                        'edit_account_id'
                        ).value =
                        inspector.account_id ?? '';

                    }

                    if (
                    document.getElementById(
                    'edit_system_inspector_id'
                    )
                    ) {

                        document.getElementById(
                        'edit_system_inspector_id'
                        ).value =
                        inspector.inspector_id ?? '';

                    }

                    const roleSelect =
                    document.getElementById(
                    'edit_role'
                    );

                    if (roleSelect) {

                        roleSelect.value =
                        inspector.role
                        ?? 'inspector';

                    }

                    document.getElementById(
                    'edit_inspector_name'
                    ).value =
                    inspector.inspector_name ?? '';

                    document.getElementById(
                    'edit_inspector_identity'
                    ).value =
                    inspector.inspector_identity ?? '';

                    document.getElementById(
                    'edit_department'
                    ).value =
                    inspector.department ?? '';

                    document.getElementById(
                    'edit_experience_years'
                    ).value =
                    inspector.experience_years ?? '';

                    document.getElementById(
                    'edit_operational_assignment'
                    ).value =
                    inspector.operational_assignment ?? '';

                    const terminalSelect =
                    document.getElementById(
                    'edit_assigned_routes'
                    );

                    if (terminalSelect) {

                        const terminalsResponse =
                        await fetch(
                        '/admin/api/Terminals/GetTerminals.php'
                        );

                        const terminals =
                        await terminalsResponse.json();

                        terminalSelect.innerHTML =
                        '<option value="">Select Terminal</option>';

                        terminals.forEach(
                        terminal => {

                            terminalSelect.innerHTML += `

                        <option
                            value="${terminal.id}"
                        >
                            ${terminal.terminal_name}
                            (${terminal.terminal_code})
                        </option>

                    `;

                        }
                        );

                        terminalSelect.value =
                        inspector.assigned_routes ?? '';

                    }

                    document.getElementById(
                    'edit_contact_number'
                    ).value =
                    inspector.contact_number ?? '';

                    document.getElementById(
                    'edit_email'
                    ).value =
                    inspector.email ?? '';

                    document.getElementById(
                    'edit_additional_notes'
                    ).value =
                    inspector.additional_notes ?? '';

                    if (
                    document.getElementById(
                    'edit_password'
                    )
                    ) {

                        document.getElementById(
                        'edit_password'
                        ).value = '';

                    }

                    const profileImage =
                    document.getElementById(
                    'currentProfileImage'
                    );

                    if (
                    profileImage
                    ) {

                        profileImage.onerror =
                        null;

                        profileImage.src =
                        inspectorPhoto(inspector);

                    }

                    const resumePreview =
                    document.getElementById(
                    'currentResumePreview'
                    );

                    const resumeLink =
                    document.getElementById(
                    'currentResumeLink'
                    );

                    if (
                    resumePreview &&
                    resumeLink
                    ) {

                        if (
                        inspector.resume_file
                        ) {

                            resumePreview.style.display =
                            'block';

                            resumeLink.href =
                            '/' +
                            inspector.resume_file;

                        } else {

                            resumePreview.style.display =
                            'none';

                        }

                    }

                    const docsPreview =
                    document.getElementById(
                    'currentDocsPreview'
                    );

                    const docsLink =
                    document.getElementById(
                    'currentDocsLink'
                    );

                    if (
                    docsPreview &&
                    docsLink
                    ) {

                        if (
                        inspector.supporting_docs
                        ) {

                            docsPreview.style.display =
                            'block';

                            docsLink.href =
                            '/' +
                            inspector.supporting_docs;

                        } else {

                            docsPreview.style.display =
                            'none';

                        }

                    }

                    new bootstrap.Modal(
                    document.getElementById(
                    'editInspectorModal'
                    )
                    ).show();

                }
                catch (error) {

                    console.error(error);

                    showAlert('error', 'Error', 'Unable to load inspector details.');

                }

            }

            async function saveInspectorChanges() {

                const form =
                document.getElementById(
                'editInspectorForm'
                );

                if (!form) {
                    return;
                }

                const uvMessage =
                window.AdminUserValidation
                ? AdminUserValidation.checkForm(form)
                : null;

                if (uvMessage) {

                    showAlert(
                    'warning',
                    'Invalid Input',
                    uvMessage
                    );

                    return;

                }

                const formData =
                new FormData(
                form
                );

                try {

                    const response =
                    await fetch(
                    '/admin/api/Inspectors/UpdateInspector.php',
                    {
                        method: 'POST',
                        body: formData
                    }
                    );

                    const responseText =
                    await response.text();

                    let result;

                    try {

                        result =
                        JSON.parse(
                        responseText
                        );

                    } catch (parseError) {

                        console.error(
                        'Invalid JSON returned by PHP'
                        );

                        console.error(
                        responseText
                        );

                        showAlert('error', 'PHP Error', 'UpdateInspector.php is not returning valid JSON. Check Console.');

                        return;

                    }

                    if (
                    result.success
                    ) {

                        showAlert('success', 'Inspector Updated', 'Changes saved successfully.');

                        bootstrap.Modal
                        .getInstance(
                        document.getElementById(
                        'editInspectorModal'
                        )
                        )
                        ?.hide();

                        loadInspectors();

                    }
                    else {

                        showAlert('error', 'Update Failed', result.message || 'Unable to update inspector.');

                    }

                }
                catch (error) {

                    console.error(
                    error
                    );

                    showAlert('error', 'Request Failed', error.message);

                }

            }

            document.getElementById(
            'editInspectorForm'
            )
            .addEventListener(
            'submit',
            function (e) {

                e.preventDefault();

                saveInspectorChanges();

            }
            );

            document
            .addEventListener(
            'click',
            function (e) {

                const button =
                e.target.closest(
                '#updateInspectorBtn'
                );

                if (!button) {
                    return;
                }

                saveInspectorChanges();

            }
            );

            document.addEventListener(
            'DOMContentLoaded',
            loadInspectors
            );

        </script>

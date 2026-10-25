<?php require_once ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/admin/services/Driver/DriverLicenseExpiry.php'; ?>
<script src="/admin/assets/js/admin-user-validation.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/assets/js/admin-user-validation.js') ?: time() ?>"></script>
<style>
    .driver-cards-section,
    .driver-cards-section * {
        font-family: 'Poppins', sans-serif;
    }

    .driver-card {
        position: relative;
        background: #fffdf8;
        border: 1px solid #e5e0a4;
        border-radius: 17px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .04);
        transition: box-shadow .2s ease, transform .2s ease;
    }
    .driver-card:hover {
        box-shadow: 0 8px 22px rgba(41, 87, 56, .10);
    }
    .driver-card-accent {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: #a5d6a7;
        border-radius: 0 0 17px 17px;
    }
    .driver-menu-btn:hover {
        color: #295738 !important;
        background: #eef5ee !important;
        border-radius: 8px;
    }
    .driver-info-icon {
        font-size: .78rem;
        color: #295738;
        line-height: 1;
    }
    .driver-info-label {
        font-size: 11px;
        font-weight: 500;
        color: #8a9a8a;
        letter-spacing: .3px;
    }
    .driver-info-value {
        font-size: 13px;
        font-weight: 500;
        color: #2e4836;
        margin-top: 1px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .driver-email-value {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .driver-action-btn {
        font-size: .78rem;
        font-weight: 500;
        border-radius: 10px;
        padding: 7px 10px;
    }
    .driver-action-btn.btn-view {
        background: #ffffff;
        border: 1px solid #c9d8c9;
        color: #295738;
    }
    .driver-action-btn.btn-view:hover {
        background: #eef5ee;
        border-color: #a5d6a7;
    }
    .driver-action-btn.btn-edit {
        background: #ffffff;
        border: 1px solid #e5e0a4;
        color: #687168;
    }
    .driver-action-btn.btn-edit:hover {
        background: #faf9e5;
        border-color: #d5cc8e;
    }

    .driver-search-wrap {
        width: 350px;
    }
    .driver-status-select {
        width: 175px;
        height: 43px;
        border-radius: 12px;
        border: 1px solid #e5e0a4;
        font-size: .85rem;
        color: #687168;
        background-color: #fffdf8;
    }
    .driver-search-wrap .form-control {
        border-color: #e5e0a4;
        border-left: none;
        border-radius: 0 12px 12px 0;
        height: 43px;
        font-size: .85rem;
    }
    .driver-search-wrap .form-control:focus,
    .driver-status-select:focus {
        border-color: #a5d6a7;
        box-shadow: 0 0 0 .2rem rgba(41, 87, 56, .08);
    }
    .driver-search-wrap .input-group-text {
        background: #fffdf8;
        border-color: #e5e0a4;
        border-right: none;
        border-radius: 12px 0 0 12px;
        color: #295738;
    }

    .driver-pagination-bar {
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

    .driver-view-section-title {
        font-size: .72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: #8a9a8a;
        margin-bottom: 12px;
    }
    .driver-view-item {
        background: #ffffff;
        border: 1px solid #ebe7d4;
        border-radius: 12px;
        padding: 10px 14px;
        height: 100%;
    }
    .driver-view-item .i-label {
        font-size: 11px;
        font-weight: 500;
        color: #8a9a8a;
        letter-spacing: .3px;
        margin-bottom: 2px;
    }
    .driver-view-item .i-value {
        font-size: 13.5px;
        font-weight: 500;
        color: #2e4836;
    }

    @media(max-width:767.98px) {
        .driver-cards-section { padding: 14px !important; }
        .driver-cards-section .btn { font-size: .78rem !important; padding: 8px 12px !important; min-height: 44px !important; }
        .driver-cards-section input, .driver-cards-section select { font-size: .85rem !important; }
        .driver-search-wrap { width: 100% !important; }
        .driver-status-select { width: 100% !important; }
        .driver-toolbar-actions { width: 100% !important; }
        .driver-toolbar-actions > * { flex: 1 1 auto !important; min-width: 0 !important; }
        .driver-pagination-bar { justify-content: center !important; }
        .page-range { width: 100% !important; text-align: center !important; }
        .driver-pagination-bar .page-per { width: 100% !important; }
        #driverPagination .page-controls { justify-content: center !important; flex-wrap: wrap !important; }
    }
</style>

<div class="p-4 mb-4 driver-cards-section"
     style="border:1px solid #e8e8e8;border-radius:18px;box-shadow:0 4px 18px rgba(0,0,0,.05);">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">


        <div class="d-flex align-items-center gap-2 mb-1">

            <div style="width:42px;height:42px;border-radius:12px;background:#295738;display:flex;align-items:center;justify-content:center;color:#fff;">
                <i class="bi bi-person-badge"></i>
            </div>

            <div>
                <h5 class="mb-0 fw-bold" style="color:#295738;">Drivers</h5>
                <small id="driverCount" class="text-muted">Loading records...</small>
            </div>

        </div>


        <div class="d-flex flex-wrap align-items-center gap-2 driver-toolbar-actions">


            <div class="input-group driver-search-wrap">

                <span class="input-group-text">
                    <i class="bi bi-search"></i>
                </span>

                <input
                    type="text"
                    id="driverSearch"
                    class="form-control"
                    placeholder="Search by name, ID, route..."
                    oninput="searchDrivers()"
                >

            </div>


            <select
                id="statusFilter"
                class="form-select driver-status-select"
                onchange="searchDrivers()"
            >
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Pending">Pending</option>
            </select>


            <button
                class="btn px-3 py-2"
                style="background:#fffdf8;border:1px solid #e5e0a4;color:#687168;border-radius:12px;"
                onclick="openArchivedDrivers()"
            >
                <i class="bi bi-archive me-1"></i>
                Archived
            </button>


            <button
                class="btn text-white px-4 py-2"
                style="background:#295738;border:none;border-radius:12px;box-shadow:0 4px 12px rgba(41,87,56,.25);"
                data-bs-toggle="modal"
                data-bs-target="#addDriverModal"
            >
                <i class="bi bi-plus-circle me-1"></i>
                Add Driver
            </button>

        </div>

    </div>

</div>

<div class="container-fluid px-3">

    <div
        id="driverContainer"
        class="row g-4"
    >
        <div class="text-center py-5">
            Loading drivers...
        </div>
    </div>

    <div id="driverPagination"></div>

</div>

<div
    class="modal fade"
    id="archivedDriversModal"
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
                Archived Drivers
                            </h2>

                            <p style="margin-top:18px;font-size:.94rem;line-height:1.9;color:#70796f;">
                View all archived drivers that have been
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
                                        <div style="font-size:.76rem;color:#7d857c;">Drivers are hidden from active list</div>
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
                                        <div style="font-size:.76rem;color:#7d857c;">Archived drivers can be restored anytime</div>
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
                                        <div style="font-size:.76rem;color:#7d857c;">All trip and passenger data is kept</div>
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
                                <i class="fa-solid fa-box-archive me-2" style="color:#8d6e00;"></i>Archived Drivers
                            </h4>
                            <div style="font-size:.84rem;color:#7b847b;margin-top:4px;">
                                <span id="archivedDriverCount">Loading archived records...</span>
                            </div>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div style="padding:16px 40px 0;">
                        <div class="input-group">
                            <span class="input-group-text" style="background:white;border-color:#e5e0a4;">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" id="archivedDriverSearch" class="form-control"
                           placeholder="Search archived drivers..."
                           style="border-color:#e5e0a4;"
                           oninput="filterArchivedDrivers()">
                        </div>
                    </div>

                    <div class="modal-body px-5 py-4">

                        <div id="archivedDriversBody" style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:24px;min-height:300px;">

                            <div style="text-align:center;padding:60px 20px;color:#70796f;">
                                <i class="fa-solid fa-spinner fa-spin" style="font-size:2rem;color:#c4b417;"></i>
                                <p style="margin-top:16px;font-size:.9rem;">Loading archived drivers...</p>
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
    id="editDriverModal"
    data-uv-context="driver"
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
        box-shadow:
            0 18px 50px
            rgba(
                0,
                0,
                0,
                .08
            );
    "
>

            <div class="row g-0">


                <div
    class="col-md-4"
    style="
        background:#f9f4da;
        border-right:
        1px solid #e7e1c9;
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
    class="
        bi
        bi-person-vcard
    "
    style="
        color:#c4b417;
        font-size:1.7rem;
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

Driver Profile Editor

                                </h2>

                                <p
    style="
        margin-top:18px;
        font-size:.94rem;
        line-height:1.9;
        color:#70796f;
    "
>

Modify driver information,
license records,
assigned routes,
bus assignments,
and operational status.

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

                                            <i
    class="
        bi
        bi-person-badge
    "
    style="
        color:#c4b417;
    "
></i>

                                            </div>

                                            <div>

                                                <div
    style="
        font-size:.9rem;
        font-weight:700;
        color:#2e4836;
    "
>

Driver Information

                                                </div>

                                                <div
    style="
        font-size:.76rem;
        color:#7d857c;
    "
>

Manage profile details

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

                                                <i
    class="
        bi
        bi-shield-check
    "
    style="
        color:#c4b417;
    "
></i>

                                                </div>

                                                <div>

                                                    <div
    style="
        font-size:.9rem;
        font-weight:700;
        color:#2e4836;
    "
>

License Records

                                                    </div>

                                                    <div
    style="
        font-size:.76rem;
        color:#7d857c;
    "
>

Maintain compliance

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

                                                    <i
    class="
        bi
        bi-bus-front
    "
    style="
        color:#c4b417;
    "
></i>

                                                    </div>

                                                    <div>

                                                        <div
    style="
        font-size:.9rem;
        font-weight:700;
        color:#2e4836;
    "
>

Fleet Assignment

                                                        </div>

                                                        <div
    style="
        font-size:.76rem;
        color:#7d857c;
    "
>

Manage assigned buses

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>



                                <div class="col-md-8">

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

Edit Driver

                                            </h4>

                                            <div
    style="
        font-size:.84rem;
        color:#7b847b;
        margin-top:4px;
    "
>

Transportation Driver Management

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

                                            <input type="hidden" id="editDriverId">
                                            <input type="hidden" id="editPhotoCurrent">
                                            <input type="hidden" id="editResumeCurrent">
                                            <input type="hidden" id="editDocsCurrent">

                                            <div style="display:flex;flex-direction:column;gap:20px;">

                                                <div style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:28px;">
                                                    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
                                                        <img id="editPhotoPreview" src="" alt="Driver Photo"
             style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid #e5e0a4;background:#f9f4da;"
             onerror="this.onerror=null;this.src='/assets/images/admin/driversinfo/drivericon.png';">
                                                        <div>
                                                            <div style="font-size:.92rem;font-weight:700;color:#2e4836;">Driver Photo</div>
                                                            <div style="font-size:.78rem;color:#7d857c;margin:4px 0 10px;">Upload a new photo (JPG or PNG)</div>
                                                            <input type="file" id="editPhoto" accept="image/*" class="form-control"
                   style="max-width:280px;height:44px;border-radius:12px;border:1px solid #dce3ce;">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:28px;">
                                                    <div style="font-size:.92rem;font-weight:700;color:#2e4836;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #f0ecdc;">Driver Information</div>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Full Name</label>
                                                            <input type="text" id="editFullName" class="form-control" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Driver ID</label>
                                                            <input type="text" id="editDriverCode" readonly class="form-control" style="height:54px;border-radius:16px;background:#f8f9f4;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Username</label>
                                                            <input type="text" id="editUsername" class="form-control" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Password</label>
                                                            <input type="password" id="editPassword" class="form-control" placeholder="Leave blank to keep current" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Email Address</label>
                                                            <input type="email" id="editEmail" class="form-control" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Contact Number</label>
                                                            <input type="text" id="editContact" class="form-control" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:28px;">
                                                    <div style="font-size:.92rem;font-weight:700;color:#2e4836;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #f0ecdc;">License Records</div>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">License Number</label>
                                                            <input type="text" id="editLicenseNumber" class="form-control" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">License Type</label>
                                                            <input type="text" id="editLicenseType" class="form-control" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">License Expiry</label>
                                                            <input type="date" id="editLicenseExpiry" class="form-control" min="<?= htmlspecialchars(driverLicenseExpiryMinimumDate(), ENT_QUOTES, 'UTF-8') ?>" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Experience (Years)</label>
                                                            <input type="number" id="editExperience" class="form-control" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:28px;">
                                                    <div style="font-size:.92rem;font-weight:700;color:#2e4836;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #f0ecdc;">Assignment</div>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Assigned Buses</label>
                                                            <input type="text" id="editAssignedBus" class="form-control" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Routes</label>
                                                            <input type="text" id="editRoutes" class="form-control" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:28px;">
                                                    <div style="font-size:.92rem;font-weight:700;color:#2e4836;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #f0ecdc;">Documents</div>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Resume</label>
                                                            <input type="file" id="editResume" class="form-control" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                            <div id="editResumeCurrentLabel" style="margin-top:8px;font-size:.78rem;color:#7d857c;"></div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Supporting Documents</label>
                                                            <input type="file" id="editDocs" class="form-control" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                            <div id="editDocsCurrentLabel" style="margin-top:8px;font-size:.78rem;color:#7d857c;"></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:28px;">
                                                    <div style="font-size:.92rem;font-weight:700;color:#2e4836;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid #f0ecdc;">Status</div>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Driver Status</label>
                                                            <select id="editStatus" class="form-select" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                                <option value="Active">Active</option>
                                                                <option value="Inactive">Inactive</option>
                                                                <option value="Pending">Pending</option>
                                                                <option value="Archived">Archived</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label style="display:block;margin-bottom:8px;font-size:.8rem;font-weight:600;color:#687168;">Approval Status</label>
                                                            <select id="editApproval" class="form-select" style="height:54px;border-radius:16px;border:1px solid #dce3ce;box-shadow:none;">
                                                                <option value="Pending">Pending</option>
                                                                <option value="Approved">Approved</option>
                                                                <option value="Rejected">Rejected</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>

                                        </div>

                                        <div
    class="
        modal-footer
        border-0
        px-5
        pb-5
    "
>

                                            <button
        type="button"
        class="btn"
        data-bs-dismiss="modal"
        style="
            height:52px;
            padding:0 24px;
            border-radius:14px;
            background:#ffffff;
            border:1px solid #dfe5d1;
            color:#687168;
            font-weight:600;
        "
    >
        Cancel
                                            </button>

                                            <button
        type="button"
        onclick="saveDriver()"
        class="btn"
        style="
            height:52px;
            padding:0 24px;
            border:none;
            border-radius:14px;
            background:#295738;
            color:#ffffff;
            font-weight:700;
        "
    >

                                                <i class="bi bi-floppy me-2"></i>

        Save Changes

                                            </button>

                                        </div>


                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <div
    class="modal fade"
    id="archiveDriverModal"
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
                                            <h5 class="mb-0 fw-bold" style="color:#2e4836;">Archive Driver?</h5>
                                        </div>

                                    </div>

                                    <div style="font-size:.9rem;color:#5b655b;">

                                        <div class="mb-1">
                                            <span style="font-weight:600;color:#2e4836;">Driver:</span>
                                            <span id="archiveDriverName">—</span>
                                        </div>

                                        <div class="mb-3">
                                            <span style="font-weight:600;color:#2e4836;">ID:</span>
                                            <span id="archiveDriverCode">—</span>
                                        </div>

                                        <p class="mb-0" style="color:#7d857c;line-height:1.7;">
                        Archiving this driver will remove them from the active driver list. Their existing records and history will remain available.
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
                    onclick="confirmArchiveDriver()"
                    style="height:46px;padding:0 24px;border-radius:12px;border:none;background:#b94a48;font-weight:600;"
                >
                                        <i class="bi bi-archive me-2"></i>
                    Archive Driver
                                    </button>

                                </div>

                            </div>

                        </div>

                    </div>


                    <div
    class="modal fade"
    id="viewDriverModal"
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
                        id="viewDriverPhoto"
                        class="rounded-circle"
                        style="width:76px;height:76px;object-fit:cover;border:3px solid #e5e0a4;flex-shrink:0;"
                        onerror="this.onerror=null;this.src='/assets/images/admin/driversinfo/drivericon.png';"
                    >

                                        <div style="min-width:0;flex:1;"> <h5 id="viewDriverName" class="mb-1 fw-semibold" style="color:#2e4836;"></h5>
                                            <div id="viewDriverCode" style="font-size:.8rem;color:#8a9a8a;"></div>
                                            <div id="viewDriverStatus" class="mt-1"></div>
                                        </div>

                                    </div>

                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                                </div>

                                <div class="modal-body" style="padding:22px 30px 6px;">


                                    <div class="driver-view-section-title">
                                        <i class="bi bi-telephone me-1" style="color:#295738;"></i>
                    Contact Information
                                    </div>

                                    <div class="row g-3 mb-4">

                                        <div class="col-md-6">
                                            <div class="driver-view-item">
                                                <div class="i-label">Phone</div>
                                                <div class="i-value" id="viewPhone">—</div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="driver-view-item">
                                                <div class="i-label">Email</div>
                                                <div class="i-value" id="viewEmail" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title=""></div>
                                            </div>
                                        </div>

                                    </div>


                                    <div class="driver-view-section-title">
                                        <i class="bi bi-shield-check me-1" style="color:#295738;"></i>
                    Employment Information
                                    </div>

                                    <div class="row g-3 mb-4">

                                        <div class="col-md-6">
                                            <div class="driver-view-item">
                                                <div class="i-label">Experience</div>
                                                <div class="i-value" id="viewExperience">—</div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="driver-view-item">
                                                <div class="i-label">License Type</div>
                                                <div class="i-value" id="viewLicenseType">—</div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="driver-view-item">
                                                <div class="i-label">License Number</div>
                                                <div class="i-value" id="viewLicenseNumber">—</div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="driver-view-item">
                                                <div class="i-label">License Expiration</div>
                                                <div class="i-value" id="viewLicenseExpiry">—</div>
                                            </div>
                                        </div>

                                    </div>


                                    <div class="driver-view-section-title">
                                        <i class="bi bi-bus-front me-1" style="color:#295738;"></i>
                    Assignment Information
                                    </div>

                                    <div class="row g-3 mb-2">

                                        <div class="col-md-6">
                                            <div class="driver-view-item">
                                                <div class="i-label">Assigned Bus</div>
                                                <div class="i-value" id="viewBus">—</div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="driver-view-item">
                                                <div class="i-label">Assigned Route</div>
                                                <div class="i-value" id="viewRoute">—</div>
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
                    onclick="editDriver(currentViewDriverId)"
                    style="height:44px;padding:0 24px;border-radius:12px;border:1px solid #c9d8c9;background:#ffffff;color:#295738;font-weight:600;"
                >
                                        <i class="bi bi-pencil me-2"></i>
                    Edit
                                    </button>

                                </div>

                            </div>

                        </div>

                    </div>

                    <script>
                        let allDrivers = [];
                        let filteredDrivers = [];
                        let page = 1;
                        let perPage = 6;
                        let pendingArchiveId = null;
                        let currentViewDriverId = null;

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
                            return '/uploads/drivers/photos/' + v.replace(/^\/+/, '');
                        }

                        function driverPhoto(driver) {
                            return photoUrl(driver && driver.photo);
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
                            const s = status || 'Active';
                            if (s === 'Inactive') {
                                return '<span class="badge" style="background:#e9ecef;color:#6c757d;font-weight:600;font-size:.72rem;padding:5px 12px;border-radius:20px;">● Inactive</span>';
                            }
                            if (s === 'Pending') {
                                return '<span class="badge" style="background:#fff3cd;color:#b8860b;font-weight:600;font-size:.72rem;padding:5px 12px;border-radius:20px;">● Pending</span>';
                            }
                            return '<span class="badge" style="background:#e8f5e9;color:#2e7d32;font-weight:600;font-size:.72rem;padding:5px 12px;border-radius:20px;">● Active</span>';
                        }

                        function licenseExpiryState(dateStr) {
                            if (!dateStr) {
                                return 'valid';
                            }
                            const expiry = new Date(dateStr);
                            if (isNaN(expiry.getTime())) {
                                return 'valid';
                            }
                            const today = new Date();
                            today.setHours(0, 0, 0, 0);
                            const days = Math.floor((expiry - today) / 86400000);
                            if (days < 0) {
                                return 'expired';
                            }
                            if (days <= 30) {
                                return 'expiring';
                            }
                            return 'valid';
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

                        function expiryHtml(dateStr) {
                            const state = licenseExpiryState(dateStr);
                            const formatted = formatDate(dateStr);
                            if (!formatted) {
                                return '<span style="color:#a8b3a8;">Not Assigned</span>';
                            }
                            if (state === 'expired') {
                                return '<span style="color:#c0392b;font-weight:600;">' + formatted + '</span>' +
                                '<span class="badge ms-1" style="background:#fdecea;color:#c0392b;font-size:.62rem;font-weight:600;vertical-align:middle;">Expired</span>';
                            }
                            if (state === 'expiring') {
                                return '<span style="color:#b8860b;">⚠ ' + formatted + '</span>' +
                                '<span class="badge ms-1" style="background:#fff3cd;color:#b8860b;font-size:.62rem;font-weight:600;vertical-align:middle;">Expiring Soon</span>';
                            }
                            return formatted;
                        }

                        async function loadDrivers() {

                            const container =
                            document.getElementById(
                            'driverContainer'
                            );

                            try {

                                container.innerHTML =
                                `
            <div class="col-12 text-center py-4">
                Loading drivers...
            </div>
        `;

                                const response =
                                await fetch(
                                '/admin/api/Driver/DriverView.php?action=getDrivers'
                                );

                                const result =
                                await response.json();

                                if (
                                !result.success
                                ) {
                                    throw new Error(
                                    result.message ||
                                    'Failed to load drivers'
                                    );
                                }

                                allDrivers =
                                result.data || [];

                                filteredDrivers =
                                [...allDrivers];

                                const driverCount =
                                document.getElementById(
                                'driverCount'
                                );

                                if (
                                driverCount
                                ) {
                                    driverCount.innerText =
                                    `${allDrivers.length} record${allDrivers.length !== 1 ? 's' : ''}`;
                                }

                                if (
                                allDrivers.length === 0
                                ) {

                                    container.innerHTML =
                                    `
                <div class="col-12">

                    <div
                        class="alert text-center"
                        style="background:#fffdf8;border:1px solid #e5e0a4;color:#8a9a8a;border-radius:16px;"
                    >
                        No drivers found.
                    </div>

                </div>
            `;

                                    const paginationEl =
                                    document.getElementById(
                                    'driverPagination'
                                    );

                                    if (paginationEl) {
                                        paginationEl.innerHTML = '';
                                    }

                                    return;
                                }

                                renderDrivers();

                            }
                            catch (error) {

                                console.error(
                                'Driver Load Error:',
                                error
                                );

                                container.innerHTML =
                                `
            <div class="col-12">

                <div
                    class="alert alert-danger text-center"
                    style="border-radius:16px;"
                >
                    Failed to load drivers.
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

                        function renderDrivers() {

                            const container =
                            document.getElementById(
                            'driverContainer'
                            );

                            if (!container) {
                                return;
                            }

                            container.innerHTML = '';

                            const paginationEl =
                            document.getElementById(
                            'driverPagination'
                            );

                            if (
                            filteredDrivers.length === 0
                            ) {

                                container.innerHTML =
                                `
            <div class="col-12">

                <div
                    class="alert text-center"
                    style="background:#fffdf8;border:1px solid #e5e0a4;color:#8a9a8a;border-radius:16px;"
                >
                    No drivers match the current filters.
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
                            filteredDrivers.length / perPage
                            )
                            );

                            if (page > totalPages) {
                                page = totalPages;
                            }

                            const start =
                            (page - 1) * perPage;

                            const pageDrivers =
                            filteredDrivers.slice(
                            start,
                            start + perPage
                            );

                            pageDrivers.forEach(driver => {

                                const photo =
                                driverPhoto(driver);

                                const name =
                                displayValuePlain(
                                driver.full_name,
                                'Unnamed Driver'
                                );

                                const driverId =
                                displayValuePlain(
                                driver.driver_id,
                                '—'
                                );

                                const licenseNo =
                                displayValue(
                                driver.license_number
                                );

                                const licenseType =
                                displayValue(
                                driver.license_type
                                );

                                const experience =
                                displayValuePlain(
                                driver.experience_years,
                                '0'
                                ) + ' Year' + (Number(driver.experience_years) === 1 ? '' : 's');

                                const assignedBus =
                                displayValue(
                                driver.assigned_buses
                                );

                                const route =
                                displayValue(
                                driver.routes
                                );

                                const phone =
                                displayValue(
                                driver.contact_number
                                );

                                const emailRaw =
                                displayValuePlain(
                                driver.email,
                                ''
                                );

                                const emailHtml =
                                emailRaw !== ''
                                ? '<span class="driver-email-value" title="' + emailRaw + '">' + emailRaw + '</span>'
                                : '<span style="color:#a8b3a8;">Not Assigned</span>';

                                const expiry =
                                expiryHtml(
                                driver.license_expiry
                                );

                                const card =
                                `
        <div class="col-12 col-md-6 col-xl-4 d-flex">

            <div class="driver-card w-100 d-flex flex-column">

                <div class="driver-card-accent"></div>

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
                            ${driverId}
                        </div>

                        <div class="mt-1">
                            ${statusBadge(driver.status)}
                        </div>

                    </div>

                    <div class="dropdown">

                        <button
                            class="btn btn-sm driver-menu-btn"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            style="background:none;border:none;color:#8a9a8a;font-size:1.1rem;padding:2px 6px;"
                        >
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end" style="border:1px solid #e5e0a4;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.08);font-size:.85rem;">

                            <li>
                                <a class="dropdown-item" href="javascript:void(0)" onclick="openViewDriver(${driver.id})">
                                    <i class="bi bi-eye me-2" style="color:#295738;"></i>View Profile
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item" href="javascript:void(0)" onclick="editDriver(${driver.id})">
                                    <i class="bi bi-pencil me-2" style="color:#295738;"></i>Edit Driver
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item" href="javascript:void(0)" onclick="viewAssignments(${driver.id})">
                                    <i class="bi bi-calendar-check me-2" style="color:#295738;"></i>View Assignments
                                </a>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <li>
                                <a class="dropdown-item" href="javascript:void(0)" onclick="openArchiveConfirm(${driver.id})" style="color:#b94a48;">
                                    <i class="bi bi-archive me-2"></i>Archive Driver
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
                                <i class="bi bi-person-vcard driver-info-icon"></i>
                                <span class="driver-info-label">License No.</span>
                            </div>
                            <div class="driver-info-value">${licenseNo}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-bus-front driver-info-icon"></i>
                                <span class="driver-info-label">Assigned Bus</span>
                            </div>
                            <div class="driver-info-value">${assignedBus}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-shield-check driver-info-icon"></i>
                                <span class="driver-info-label">License Type</span>
                            </div>
                            <div class="driver-info-value">${licenseType}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-calendar3 driver-info-icon"></i>
                                <span class="driver-info-label">License Expiry</span>
                            </div>
                            <div class="driver-info-value">${expiry}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-star driver-info-icon"></i>
                                <span class="driver-info-label">Experience</span>
                            </div>
                            <div class="driver-info-value">${experience}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-geo-alt driver-info-icon"></i>
                                <span class="driver-info-label">Route</span>
                            </div>
                            <div class="driver-info-value" title="${displayValuePlain(driver.routes)}">${route}</div>

                        </div>

                    </div>

                </div>

                <hr class="mx-3 my-1" style="border-color:#f0ecdc;">

                <div class="px-3 py-1 d-flex gap-3">

                    <div class="flex-grow-1" style="min-width:0;">

                        <div class="d-flex align-items-center gap-1 mb-1">
                            <i class="bi bi-telephone driver-info-icon"></i>
                            <span class="driver-info-label">Phone</span>
                        </div>
                        <div class="driver-info-value">${phone}</div>

                    </div>

                    <div class="flex-grow-1" style="min-width:0;">

                        <div class="d-flex align-items-center gap-1 mb-1">
                            <i class="bi bi-envelope driver-info-icon"></i>
                            <span class="driver-info-label">Email</span>
                        </div>
                        <div class="driver-info-value">${emailHtml}</div>

                    </div>

                </div>

                <div class="px-3 pt-2 pb-3 mt-auto">

                    <div class="row g-2">

                        <div class="col-6">

                            <button
                                type="button"
                                class="btn w-100 driver-action-btn btn-view"
                                onclick="openViewDriver(${driver.id})"
                            >
                                <i class="bi bi-eye me-1"></i>
                                View Profile
                            </button>

                        </div>

                        <div class="col-6">

                            <button
                                type="button"
                                class="btn w-100 driver-action-btn btn-edit"
                                onclick="editDriver(${driver.id})"
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

                            renderPagination(start, start + pageDrivers.length, totalPages);

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
                            'driverPagination'
                            );

                            if (!el) {
                                return;
                            }

                            if (
                            filteredDrivers.length === 0
                            ) {
                                el.innerHTML = '';
                                return;
                            }

                            const range =
                            `<span class="page-range">Showing ${start + 1}–${end} of ${filteredDrivers.length} drivers</span>`;

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
                            `<select class="form-select page-per" onchange="changePerPage(this.value)" aria-label="Drivers per page">${perPageOptions}</select>`;

                            el.innerHTML =
                            `<div class="driver-pagination-bar">` +
                            range +
                            `<div class="page-controls">${prev}${pages}${next}</div>` +
                            perPageSelect +
                            `</div>`;

                        }

                        function goToPage(p) {

                            page = p;
                            renderDrivers();

                        }

                        function changePerPage(value) {

                            perPage = parseInt(value, 10);
                            page = 1;
                            renderDrivers();

                        }

                        function searchDrivers() {

                            const keyword =
                            (document.getElementById('driverSearch')?.value || '')
                            .toLowerCase()
                            .trim();

                            const status =
                            document.getElementById('statusFilter')?.value || '';

                            filteredDrivers =
                            allDrivers.filter(driver => {

                                const haystack =
                                [
                                driver.full_name,
                                driver.driver_id,
                                driver.routes,
                                driver.assigned_buses
                                ]
                                .map(v => (v || '').toLowerCase())
                                .join(' ');

                                const matchesSearch =
                                !keyword ||
                                haystack.includes(keyword);

                                const matchesStatus =
                                !status ||
                                (driver.status || '') === status;

                                return matchesSearch && matchesStatus;

                            });

                            page = 1;

                            renderDrivers();

                        }

                        function viewAssignments(id) {

                            const schedule =
                            document.querySelector(
                            '.schedule-section'
                            );

                            if (schedule) {
                                schedule.scrollIntoView({
                                    behavior: 'smooth'
                                });
                            }

                        }

                        function showViewDriver(driver) {

                            currentViewDriverId =
                            driver.id;

                            document.getElementById(
                            'viewDriverPhoto'
                            ).src =
                            driverPhoto(driver);

                            document.getElementById(
                            'viewDriverName'
                            ).textContent =
                            displayValuePlain(
                            driver.full_name,
                            'Unnamed Driver'
                            );

                            document.getElementById(
                            'viewDriverCode'
                            ).textContent =
                            displayValuePlain(
                            driver.driver_id,
                            '—'
                            );

                            document.getElementById(
                            'viewDriverStatus'
                            ).innerHTML =
                            statusBadge(driver.status);

                            document.getElementById(
                            'viewPhone'
                            ).textContent =
                            displayValuePlain(
                            driver.contact_number
                            );

                            document.getElementById(
                            'viewEmail'
                            ).textContent =
                            displayValuePlain(
                            driver.email
                            );

                            document.getElementById(
                            'viewEmail'
                            ).title =
                            displayValuePlain(
                            driver.email
                            );

                            document.getElementById(
                            'viewExperience'
                            ).textContent =
                            displayValuePlain(
                            driver.experience_years,
                            '0'
                            ) + ' Year' + (Number(driver.experience_years) === 1 ? '' : 's');

                            document.getElementById(
                            'viewLicenseType'
                            ).textContent =
                            displayValuePlain(
                            driver.license_type
                            );

                            document.getElementById(
                            'viewLicenseNumber'
                            ).textContent =
                            displayValuePlain(
                            driver.license_number
                            );

                            document.getElementById(
                            'viewLicenseExpiry'
                            ).innerHTML =
                            expiryHtml(
                            driver.license_expiry
                            );

                            document.getElementById(
                            'viewBus'
                            ).textContent =
                            displayValuePlain(
                            driver.assigned_buses
                            );

                            document.getElementById(
                            'viewRoute'
                            ).textContent =
                            displayValuePlain(
                            driver.routes
                            );

                            new bootstrap.Modal(
                            document.getElementById(
                            'viewDriverModal'
                            )
                            ).show();

                        }

                        function openViewDriver(id) {

                            const driver =
                            allDrivers.find(
                            d => Number(d.id) === Number(id)
                            );

                            if (driver) {
                                showViewDriver(driver);
                                return;
                            }

                            fetch(
                            `/admin/api/Driver/DriverView.php?action=getDriver&id=${id}`
                            )
                            .then(r => r.json())
                            .then(result => {
                                if (result.success && result.data) {
                                    showViewDriver(result.data);
                                }
                                else {
                                    showAlert('error', 'Failed', 'Unable to load driver profile.');
                                }
                            })
                            .catch (error => {
                                console.error(error);
                                showAlert('error', 'Error', 'Something went wrong.');
                            });

                        }

                        function openArchiveConfirm(id) {

                            const driver =
                            allDrivers.find(
                            d => Number(d.id) === Number(id)
                            ) || {};

                            pendingArchiveId = id;

                            document.getElementById(
                            'archiveDriverName'
                            ).textContent =
                            driver.full_name || '—';

                            document.getElementById(
                            'archiveDriverCode'
                            ).textContent =
                            driver.driver_id || '—';

                            new bootstrap.Modal(
                            document.getElementById(
                            'archiveDriverModal'
                            )
                            ).show();

                        }

                        async function confirmArchiveDriver() {

                            const modal =
                            bootstrap.Modal.getInstance(
                            document.getElementById(
                            'archiveDriverModal'
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
                            'id',
                            id
                            );

                            try {

                                const response =
                                await fetch(
                                '/admin/api/Driver/DriverView.php?action=archiveDriver',
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

                                    showAlert('success', 'Archived', 'Driver archived successfully.');

                                    loadDrivers();

                                }
                                else {

                                    showAlert('error', 'Failed', 'Unable to archive driver.');

                                }

                            }
                            catch (error) {

                                console.error(error);

                                showAlert('error', 'Error', 'Something went wrong.');

                            }

                        }

                        async function openArchivedDrivers() {

                            const modal =
                            new bootstrap.Modal(
                            document.getElementById(
                            'archivedDriversModal'
                            )
                            );

                            modal.show();

                            const container =
                            document.getElementById(
                            'archivedDriversBody'
                            );

                            const countBadge =
                            document.getElementById(
                            'archivedDriverCount'
                            );

                            const searchInput =
                            document.getElementById(
                            'archivedDriverSearch'
                            );

                            if (searchInput) {
                                searchInput.value = '';
                            }

                            container.innerHTML =
                            '<div style="text-align:center;padding:60px 20px;color:#70796f;">' +
                            '<i class="fa-solid fa-spinner fa-spin" style="font-size:2rem;color:#c4b417;"></i>' +
                            '<p style="margin-top:16px;font-size:.9rem;">Loading archived drivers...</p>' +
                            '</div>';

                            let archived = [];

                            try {

                                const res =
                                await fetch(
                                '/admin/api/Driver/DriverView.php?action=getArchivedDrivers'
                                );

                                const json =
                                await res.json();

                                if (
                                json.success &&
                                json.data
                                ) {
                                    archived = json.data;
                                }

                            } catch (e) {
                                console.error(
                                'Failed to load archived drivers:',
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
                                '<p style="margin-top:16px;font-size:.9rem;">No archived drivers found.</p>' +
                                '</div>';

                                return;
                            }

                            let tableRows = '';

                            archived.forEach(driver => {

                                const photo =
                                photoUrl(driver.photo);

                                tableRows += '<tr style="border-bottom:1px solid #f0ede0;">';
                                tableRows += '<td style="padding:12px 16px;display:flex;align-items:center;gap:12px;">';
                                tableRows += '<img src="' + photo + '" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #e5e0a4;">';
                                tableRows += '<div><div style="font-weight:600;color:#2e4836;font-size:0.85rem;">' + (driver.full_name || '-') + '</div>';
                                tableRows += '<div style="font-size:0.75rem;color:#888;">' + (driver.driver_id || '-') + '</div></div>';
                                tableRows += '</td>';
                                tableRows += '<td style="padding:12px 16px;color:#475569;font-size:0.85rem;">' + (driver.license_number || 'N/A') + '</td>';
                                tableRows += '<td style="padding:12px 16px;color:#475569;font-size:0.85rem;">' + (driver.contact_number || 'N/A') + '</td>';
                                tableRows += '<td style="padding:12px 16px;"><span style="background:#f8d7da;color:#721c24;padding:4px 14px;border-radius:20px;font-size:0.75rem;font-weight:600;display:inline-block;">Archived</span></td>';
                                tableRows += '<td style="padding:12px 16px;display:flex;gap:8px;">';
                                tableRows += '<button class="btn btn-sm" onclick="openViewDriver(' + driver.id + ')" style="background:#ffffff;color:#295738;border:1px solid #c9d8c9;border-radius:8px;padding:6px 14px;font-size:0.75rem;font-weight:600;"><i class="fa-solid fa-eye me-1"></i>View</button>';
                                tableRows += '<button class="btn btn-sm" onclick="unarchiveDriver(' + driver.id + ')" style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:8px;padding:6px 14px;font-size:0.75rem;font-weight:600;"><i class="fa-solid fa-rotate-left me-1"></i>Restore</button>';
                                tableRows += '</td>';
                                tableRows += '</tr>';

                            });

                            container.innerHTML =
                            '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">' +
                            '<thead><tr style="background:linear-gradient(135deg,#f9f4da 0%,#f5edd0 100%);">' +
                            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Driver</th>' +
                            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">License</th>' +
                            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Contact</th>' +
                            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Status</th>' +
                            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Action</th>' +
                            '</tr></thead><tbody>' + tableRows + '</tbody></table></div>';

                        }

                        function filterArchivedDrivers() {

                            const keyword =
                            (document.getElementById('archivedDriverSearch')?.value || '')
                            .toLowerCase()
                            .trim();

                            document
                            .querySelectorAll(
                            '#archivedDriversBody tbody tr'
                            )
                            .forEach(row => {

                                row.style.display =
                                row.innerText.toLowerCase().includes(keyword)
                                ? ''
                                : 'none';

                            });

                        }

                        async function unarchiveDriver(id) {

                            showConfirm('Restore Driver?', 'This driver will become active again.', async function () {

                                try {

                                    const formData =
                                    new FormData();

                                    formData.append(
                                    'id',
                                    id
                                    );

                                    const response =
                                    await fetch(
                                    '/admin/api/Driver/DriverView.php?action=unarchiveDriver',
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

                                        showAlert('success', 'Driver Restored', 'The driver has been restored.');

                                        loadDrivers();

                                        const archivedModal =
                                        bootstrap.Modal
                                        .getInstance(
                                        document.getElementById(
                                        'archivedDriversModal'
                                        )
                                        );

                                        if (
                                        archivedModal
                                        ) {
                                            openArchivedDrivers();
                                        }

                                    }
                                    else {

                                        showAlert('error', 'Failed', 'Unable to restore driver.');

                                    }

                                }
                                catch (error) {

                                    console.error(error);

                                    showAlert('error', 'Error', 'Something went wrong.');

                                }

                            });

                        }

                        async function editDriver(id) {

                            try {

                                const response =
                                await fetch(
                                `/admin/api/Driver/DriverView.php?action=getDriver&id=${id}`
                                );

                                if (!response.ok) {
                                    throw new Error(
                                    'Server returned ' + response.status
                                    );
                                }

                                const result =
                                await response.json();

                                if (
                                !result.success ||
                                !result.data ||
                                typeof result.data !== 'object'
                                ) {
                                    throw new Error(
                                    result.message ||
                                    'Unable to load driver.'
                                    );
                                }

                                const driver =
                                result.data;

                                document.getElementById(
                                'editDriverId'
                                ).value =
                                driver.id || '';

                                document.getElementById(
                                'editDriverCode'
                                ).value =
                                driver.driver_id || '';

                                document.getElementById(
                                'editFullName'
                                ).value =
                                driver.full_name || '';

                                document.getElementById(
                                'editLicenseNumber'
                                ).value =
                                driver.license_number || '';

                                document.getElementById(
                                'editLicenseType'
                                ).value =
                                driver.license_type || '';

                                document.getElementById(
                                'editLicenseExpiry'
                                ).value =
                                driver.license_expiry || '';

                                document.getElementById(
                                'editExperience'
                                ).value =
                                driver.experience_years || '';

                                document.getElementById(
                                'editAssignedBus'
                                ).value =
                                driver.assigned_buses || '';

                                document.getElementById(
                                'editRoutes'
                                ).value =
                                driver.routes || '';

                                document.getElementById(
                                'editEmail'
                                ).value =
                                driver.email || '';

                                document.getElementById(
                                'editContact'
                                ).value =
                                driver.contact_number || '';

                                document.getElementById(
                                'editStatus'
                                ).value =
                                driver.status || 'Active';

                                const editApproval =
                                document.getElementById(
                                'editApproval'
                                );

                                const approvalValue =
                                driver.approval_status || 'Pending';

                                const approvalOption =
                                Array.from(
                                editApproval.options
                                ).find(
                                opt => opt.value.toLowerCase() === String(approvalValue).toLowerCase()
                                );

                                editApproval.value =
                                approvalOption
                                ? approvalOption.value
                                : 'Pending';

                                document.getElementById(
                                'editUsername'
                                ).value =
                                driver.account_username || '';

                                document.getElementById(
                                'editPassword'
                                ).value =
                                '';

                                const preview =
                                document.getElementById(
                                'editPhotoPreview'
                                );

                                document.getElementById(
                                'editPhotoCurrent'
                                ).value =
                                driver.photo || '';

                                preview.onerror =
                                null;

                                preview.src =
                                photoUrl(driver.photo);

                                document.getElementById(
                                'editPhoto'
                                ).value =
                                '';

                                document.getElementById(
                                'editResumeCurrent'
                                ).value =
                                driver.resume_file || '';

                                document.getElementById(
                                'editDocsCurrent'
                                ).value =
                                driver.supporting_docs || '';

                                document.getElementById(
                                'editResumeCurrentLabel'
                                ).textContent =
                                driver.resume_file
                                ? 'Current: ' + String(driver.resume_file).split('/').pop()
                                : 'No resume uploaded';

                                document.getElementById(
                                'editDocsCurrentLabel'
                                ).textContent =
                                driver.supporting_docs
                                ? 'Current: ' + String(driver.supporting_docs).split('/').pop()
                                : 'No documents uploaded';

                                const modal =
                                new bootstrap.Modal(
                                document.getElementById(
                                'editDriverModal'
                                )
                                );

                                modal.show();

                            }
                            catch (error) {

                                console.error(error);

                                alert(
                                error.message ||
                                'Failed to load driver.'
                                );

                            }

                        }

                        async function saveDriver() {

                            const expiryInput =
                            document.getElementById(
                            'editLicenseExpiry'
                            );

                            if (
                            expiryInput &&
                            expiryInput.value &&
                            expiryInput.min &&
                            expiryInput.value < expiryInput.min
                            ) {

                                showAlert(
                                'warning',
                                'Invalid License Expiry',
                                "Driver's license must be valid for more than 3 days."
                                );

                                return;
                            }

                            const uvMessage =
                            window.AdminUserValidation
                            ? AdminUserValidation.checkScope(
                            document.getElementById(
                            'editDriverModal'
                            )
                            )
                            : null;

                            if (uvMessage) {

                                showAlert(
                                'warning',
                                'Invalid Input',
                                uvMessage
                                );

                                return;
                            }

                            const id =
                            document.getElementById(
                            'editDriverId'
                            ).value;

                            const formData =
                            new FormData();

                            formData.append(
                            'id',
                            id
                            );

                            formData.append(
                            'full_name',
                            document.getElementById(
                            'editFullName'
                            ).value
                            );

                            formData.append(
                            'license_number',
                            document.getElementById(
                            'editLicenseNumber'
                            ).value
                            );

                            formData.append(
                            'license_type',
                            document.getElementById(
                            'editLicenseType'
                            ).value
                            );

                            formData.append(
                            'license_expiry',
                            document.getElementById(
                            'editLicenseExpiry'
                            ).value
                            );

                            formData.append(
                            'experience_years',
                            document.getElementById(
                            'editExperience'
                            ).value
                            );

                            formData.append(
                            'assigned_buses',
                            document.getElementById(
                            'editAssignedBus'
                            ).value
                            );

                            formData.append(
                            'routes',
                            document.getElementById(
                            'editRoutes'
                            ).value
                            );

                            formData.append(
                            'email',
                            document.getElementById(
                            'editEmail'
                            ).value
                            );

                            formData.append(
                            'contact_number',
                            document.getElementById(
                            'editContact'
                            ).value
                            );

                            formData.append(
                            'status',
                            document.getElementById(
                            'editStatus'
                            ).value
                            );

                            formData.append(
                            'approval_status',
                            document.getElementById(
                            'editApproval'
                            ).value
                            );

                            formData.append(
                            'driver_id',
                            document.getElementById(
                            'editDriverCode'
                            ).value
                            );

                            formData.append(
                            'username',
                            document.getElementById(
                            'editUsername'
                            ).value
                            );

                            formData.append(
                            'password',
                            document.getElementById(
                            'editPassword'
                            ).value
                            );

                            formData.append(
                            'photo_path',
                            document.getElementById(
                            'editPhotoCurrent'
                            ).value
                            );

                            const photoInput =
                            document.getElementById(
                            'editPhoto'
                            );

                            if (
                            photoInput.files.length > 0
                            ) {

                                formData.append(
                                'photo',
                                photoInput.files[0]
                                );
                            }

                            formData.append(
                            'resume_path',
                            document.getElementById(
                            'editResumeCurrent'
                            ).value
                            );

                            formData.append(
                            'docs_path',
                            document.getElementById(
                            'editDocsCurrent'
                            ).value
                            );

                            const resumeInput =
                            document.getElementById(
                            'editResume'
                            );

                            if (
                            resumeInput.files.length > 0
                            ) {

                                formData.append(
                                'resume_file',
                                resumeInput.files[0]
                                );
                            }

                            const docsInput =
                            document.getElementById(
                            'editDocs'
                            );

                            if (
                            docsInput.files.length > 0
                            ) {

                                formData.append(
                                'supporting_docs',
                                docsInput.files[0]
                                );
                            }

                            showConfirm('Save Changes?', 'Driver information will be updated.', async function () {

                                try {

                                    const response =
                                    await fetch(
                                    '/admin/api/Driver/DriverView.php?action=updateDriver',
                                    {
                                        method: 'POST',
                                        body: formData
                                    }
                                    );

                                    const result =
                                    await response.json();

                                    if (
                                    result.success
                                    ) {

                                        showAlert('success', 'Driver Updated', 'Driver information has been saved.');

                                        bootstrap.Modal
                                        .getInstance(
                                        document.getElementById(
                                        'editDriverModal'
                                        )
                                        )
                                        ?.hide();

                                        loadDrivers();

                                    }
                                    else {

                                        showAlert('error', 'Update Failed', result.message || 'Unable to update driver.');

                                    }

                                }
                                catch (error) {

                                    console.error(error);

                                    showAlert('error', 'Server Error', 'Something went wrong while updating the driver.');

                                }

                            });

                        }

                        document.addEventListener(
                        'DOMContentLoaded',
                        loadDrivers
                        );

                    </script>

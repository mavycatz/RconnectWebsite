<script src="/admin/assets/js/admin-user-validation.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/assets/js/admin-user-validation.js') ?: time() ?>"></script>
<style>
    .admin-cards-section,
    .admin-cards-section * {
        font-family: 'Poppins', sans-serif;
    }
    .admin-card {
        position: relative;
        background: #fffdf8;
        border: 1px solid #e5e0a4;
        border-radius: 17px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .04);
        transition: box-shadow .2s ease, transform .2s ease;
    }
    .admin-card:hover {
        box-shadow: 0 8px 22px rgba(41, 87, 56, .10);
    }
    .admin-card-accent {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: #a5d6a7;
        border-radius: 0 0 17px 17px;
    }
    .admin-menu-btn:hover {
        color: #295738 !important;
        background: #eef5ee !important;
        border-radius: 8px;
    }
    .admin-info-icon {
        font-size: .78rem;
        color: #295738;
        line-height: 1;
    }
    .admin-info-label {
        font-size: 11px;
        font-weight: 500;
        color: #8a9a8a;
        letter-spacing: .3px;
    }
    .admin-info-value {
        font-size: 13px;
        font-weight: 500;
        color: #2e4836;
        margin-top: 1px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .admin-action-btn {
        font-size: .78rem;
        font-weight: 500;
        border-radius: 10px;
        padding: 7px 10px;
    }
    .admin-action-btn.btn-view {
        background: #ffffff;
        border: 1px solid #c9d8c9;
        color: #295738;
    }
    .admin-action-btn.btn-view:hover {
        background: #eef5ee;
        border-color: #a5d6a7;
    }
    .admin-action-btn.btn-edit {
        background: #ffffff;
        border: 1px solid #e5e0a4;
        color: #687168;
    }
    .admin-action-btn.btn-edit:hover {
        background: #faf9e5;
        border-color: #d5cc8e;
    }
    .admin-search-wrap .input-group-text {
        background: #faf9e5;
        border-color: #e5e0a4;
        color: #295738;
        border-radius: 12px 0 0 12px;
    }
    .admin-search-wrap .form-control {
        border: 1px solid #e5e0a4;
        border-left: none;
        border-radius: 0 12px 12px 0;
        height: 43px;
        font-size: .85rem;
    }
    .admin-search-wrap .form-control:focus {
        border-color: #a5d6a7;
        box-shadow: 0 0 0 .2rem rgba(41, 87, 56, .08);
    }
    @media(max-width:768px) {
        .p-4.mb-4[style*="border:1px solid #e8e8e8"] { padding: 10px !important; }
        .p-4.mb-4[style*="border:1px solid #e8e8e8"] input { font-size: 0.8rem !important; }
        .p-4.mb-4[style*="border:1px solid #e8e8e8"] .input-group { width: 100% !important; }
        .p-4.mb-4[style*="border:1px solid #e8e8e8"] .btn { min-height: 42px !important; font-size: 0.75rem !important; padding: 6px 10px !important; }
        #adminsContainer > div { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
        #adminsContainer .p-4 { padding: 10px !important; }
        #adminsContainer h6 { font-size: 0.8rem !important; }
        #adminsContainer .small { font-size: 0.65rem !important; }
        #adminsContainer .btn { min-height: 38px !important; font-size: 0.7rem !important; padding: 5px 8px !important; }
    }
</style>

<div class="admin-cards-section">
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
            flex-nowrap
            justify-content-between
            align-items-center
            gap-3
        "
    >


            <div style="flex-shrink:0;white-space:nowrap;">

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
                        background:#4a7c59;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        color:#fff;
                    "
                >
                        <i
                        class="bi bi-person-gear"
                    ></i>
                        </div>

                        <div>

                            <h5
                        class="mb-0 fw-bold"
                        style="
                            color:#295738;
                            white-space:nowrap;
                        "
                    >
                        Admin Management
                            </h5>

                            <small
                        id="adminCount"
                        class="text-muted"
                    >
                        Loading administrators...
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
            style="flex-shrink:1;min-width:0;"
        >


                    <div
                class="input-group admin-search-wrap"
                style="
                    width:auto;max-width:350px;flex-shrink:1;min-width:140px;
                "
            >

                        <span
                    class="input-group-text"
                >
                            <i
                        class="bi bi-search"
                    ></i>
                            </span>

                            <input
                    type="text"
                    id="adminSearch"
                    class="form-control"
                    placeholder="Search by name, ID, email..."
                    oninput="searchAdmins()"
                >

                        </div>


                        <button
                type="button"
                class="btn px-3 py-2"
                onclick="openArchivedAdmins()"
                style="
                    background:#f8f9fa;
                    border:1px solid #dee2e6;
                    color:#495057;
                    border-radius:12px;
                "
            >

                            <i
                    class="
                        bi
                        bi-archive
                        me-1
                    "
                ></i>

                Archived

                            </button>


                            <button
                type="button"
                class="
                    btn
                    text-white
                    px-4
                    py-2
                "
                style="
                    background:#4a7c59;
                    border:none;
                    border-radius:50px;
                "
                onclick="var m=document.getElementById('addAdminModal');if(m)new bootstrap.Modal(m).show();"
            >

                                <i
                    class="
                        bi
                        bi-plus-circle
                        me-1
                    "
                ></i>

                Add Admin

                                </button>

                            </div>

                        </div>

                    </div>


                    <div id="adminsContainer" class="row g-4">

                        <div class="text-center text-muted py-5">
        Loading administrators...
                        </div>

                    </div>


                    <div class="text-center mt-4">

                        <button
        class="btn px-4 py-2 d-flex align-items-center gap-2 mx-auto"
        style="
            background:transparent;
            border:none;
            color:#295738;
            font-weight:600;
        "
        id="toggleAdmins"
    >

                            <span>
            View All Administrators
                            </span>

                            <i
            class="bi bi-chevron-down"
            id="adminIcon"
        ></i>

                            </button>

                        </div>

                        <div class="modal fade"
     id="editAdminModal"
     tabindex="-1"
     aria-hidden="true">

                            <div class="modal-dialog modal-dialog-centered modal-xl">

                                <div class="modal-content border-0 overflow-hidden"
     style="
        border-radius:24px;
        background:#fef8f3;
        box-shadow:0 18px 50px rgba(0,0,0,.08);
     ">


                                    <div class="row g-0">
    

                                        <div class="col-lg-4 d-none d-lg-block"
     style="
        background:#f9f4da;
        border-right:1px solid #e7e1c9;
        min-height:780px;
     ">

                                            <div class="p-5 h-100 d-flex flex-column justify-content-between">


                                                <div>

                                                    <div style="
        width:72px;
        height:72px;
        border-radius:22px;
        background:#ffffff;
        border:1px solid #ebe5ca;
        display:flex;
        align-items:center;
        justify-content:center;
    ">

                                                        <i class="fa-solid fa-user-pen"
           style="
                color:#c4b417;
                font-size:1.6rem;
           ">
                                                        </i>

                                                    </div>

                                                    <h2 style="
        margin-top:28px;
        font-size:2rem;
        font-weight:700;
        line-height:1.3;
        color:#2e4836;
    ">

        Edit Administrator

                                                    </h2>

                                                    <p style="
        margin-top:18px;
        font-size:.95rem;
        line-height:1.9;
        color:#6d766d;
    ">

        Update administrator information, modify account
        credentials, manage access permissions, update
        contact details, and maintain system administration
        settings for transportation management operations.

                                                    </p>

                                                </div>

                                                <div class="d-grid gap-3">

                                                    <div style="
    background:#ffffff;
    border:1px solid #ebe5ca;
    border-radius:18px;
    padding:18px;
">

                                                        <div class="d-flex align-items-center">

                                                            <div style="
    width:46px;
    height:46px;
    border-radius:14px;
    background:#f9f4da;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-right:14px;
">

                                                                <i class="fas fa-user-pen"
   style="
      color:#c4b417;
   ">
                                                                </i>

                                                            </div>

                                                            <div>

                                                                <div style="
        font-size:.9rem;
        font-weight:700;
        color:#2e4836;
    ">
        Update Administrator Profile
                                                                </div>

                                                                <div style="
        font-size:.76rem;
        color:#7c847c;
    ">
        Modify administrator information, contact details, and account credentials
                                                                </div>

                                                            </div>

                                                        </div>

                                                    </div>

                                                    <div style="
    background:#ffffff;
    border:1px solid #ebe5ca;
    border-radius:18px;
    padding:18px;
">

                                                        <div class="d-flex align-items-center">

                                                            <div style="
    width:46px;
    height:46px;
    border-radius:14px;
    background:#f9f4da;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-right:14px;
">

                                                                <i class="fas fa-user-shield"
   style="
      color:#c4b417;
   ">
                                                                </i>

                                                            </div>

                                                            <div>

                                                                <div style="
        font-size:.9rem;
        font-weight:700;
        color:#2e4836;
    ">
        Access & Permissions
                                                                </div>

                                                                <div style="
        font-size:.76rem;
        color:#7c847c;
    ">
        Manage administrator roles, access levels, and system privileges
                                                                </div>

                                                            </div>

                                                        </div>

                                                    </div>

                                                    <div style="
    background:#ffffff;
    border:1px solid #ebe5ca;
    border-radius:18px;
    padding:18px;
">

                                                        <div class="d-flex align-items-center">

                                                            <div style="
    width:46px;
    height:46px;
    border-radius:14px;
    background:#f9f4da;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-right:14px;
">

                                                                <i class="fas fa-folder-open"
   style="
      color:#c4b417;
   ">
                                                                </i>

                                                            </div>

                                                            <div>

                                                                <div style="
        font-size:.9rem;
        font-weight:700;
        color:#2e4836;
    ">
        Update Documents
                                                                </div>

                                                                <div style="
        font-size:.76rem;
        color:#7c847c;
    ">
        Replace profile photos, credentials, and supporting administrator records
                                                                </div>

                                                            </div>

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                        </div>


                                        <div class="col-lg-8 col-12">

                                            <div class="modal-header border-0 px-5 pt-5 pb-0">

                                                <div>

                                                    <h4 style="
            margin:0;
            color:#2e4836;
            font-weight:700;
        ">

            Edit Administrator

                                                    </h4>

                                                    <div style="
            font-size:.84rem;
            color:#7d867c;
        ">

            Update administrator information, modify account access, manage permissions, and maintain transportation operations personnel records.

                                                    </div>

                                                </div>

                                                <button
        type="button"
        class="btn-close"
        data-bs-dismiss="modal">
                                                </button>

                                            </div>

                                            <div class="modal-body px-5 py-4">


                                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:22px;
    padding:30px;
">
                                                    <div class="modal-body">

                                                        <form
    id="editAdminForm"
    data-uv-context="admin"
    enctype="multipart/form-data"
>
                                                            <input
    type="hidden"
    id="edit_admin_id"
    name="id"
>
                                                            <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:22px;
    padding:28px;
    margin-bottom:20px;
">

                                                                <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">

                                                                    <img
            id="edit_profile_preview"
            src=""
            alt="Administrator Photo"
            style="
                width:90px;
                height:90px;
                border-radius:50%;
                object-fit:cover;
                border:3px solid #e5e0a4;
                background:#f9f4da;
                flex-shrink:0;
            "
            onerror="this.onerror=null;this.src='/assets/images/admin/driversinfo/drivericon.png';"
        >

                                                                    <div>

                                                                        <div style="font-size:.92rem;font-weight:700;color:#2e4836;">
                Profile Picture
                                                                        </div>

                                                                        <div style="font-size:.78rem;color:#7d857c;margin:4px 0 10px;">
                Upload a new photo (JPG or PNG)
                                                                        </div>

                                                                        <input
                type="file"
                id="edit_profile_picture"
                name="profile_picture"
                class="form-control"
                accept="image/*"
                style="
                    max-width:280px;
                    height:44px;
                    border-radius:12px;
                    border:1px solid #dce3ce;
                "
            >

                                                                    </div>

                                                                </div>

                                                            </div>
                                                            <div class="container-fluid">


                                                                <div>

                                                                    <div style="
    font-size:.82rem;
    font-weight:700;
    color:#2e4836;
    margin-bottom:14px;
">

Administrator Information

                                                                    </div>

                                                                    <div class="row g-3">

                                                                        <div class="col-md-6">

                                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Administrator Name

                                                                            </label>

                                                                            <input
    type="text"
    id="edit_full_name"
    name="full_name"
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

                                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Administrator ID

                                                                            </label>


                                                                            <input
    type="text"
    id="edit_admin_id_display"
    class="form-control"
    readonly
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
        box-shadow:none;
        background:#f8f9fa;
        font-weight:600;
        color:#2e4836;
    "
>

                                                                        </div>

                                                                        <div class="col-md-6">

                                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Administrative Role

                                                                            </label>

                                                                            <select
    id="edit_role"
    name="role"
    class="form-select"
    required
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
        box-shadow:none;
    "
>

                                                                                <option value="">
        Select Role
                                                                                </option>

                                                                                <option value="admin">
        Administrator
                                                                                </option>

                                                                                <option value="super_admin">
        Super Administrator
                                                                                </option>

                                                                            </select>

                                                                        </div>

                                                                        <div class="col-md-6">

                                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Account Status

                                                                            </label>

                                                                            <select
    id="edit_status"
    name="status"
    class="form-select"
    required
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
        box-shadow:none;
    "
>

                                                                                <option value="active">
        Active
                                                                                </option>

                                                                                <option value="inactive">
        Inactive
                                                                                </option>

                                                                                <option value="pending">
        Pending Approval
                                                                                </option>

                                                                            </select>

                                                                        </div>

                                                                    </div>

                                                                </div>

                                                                <hr style="
    margin:28px 0;
    border-color:#ece7d6;
">

                                                                <div>

                                                                    <div style="
    font-size:.82rem;
    font-weight:700;
    color:#2e4836;
    margin-bottom:14px;
">

Administrative Assignment & System Access

                                                                    </div>

                                                                    <div class="row g-3">

                                                                        <div class="col-md-6">

                                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Assigned Department

                                                                            </label>

                                                                            <input
    type="text"
    id="edit_assigned_department"
    name="assigned_department"
    class="form-control"
    placeholder="Operations Management"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
        box-shadow:none;
    "
>

                                                                        </div>

                                                                        <div class="col-md-6">

                                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Shift Status

                                                                            </label>

                                                                            <select
    id="edit_shift_status"
    name="shift_status"
    class="form-select"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
        box-shadow:none;
    "
>

                                                                                <option value="">
    Select Shift
                                                                                </option>

                                                                                <option value="Day Shift">
    Day Shift
                                                                                </option>

                                                                                <option value="Night Shift">
    Night Shift
                                                                                </option>

                                                                                <option value="Flexible">
    Flexible
                                                                                </option>

                                                                            </select>

                                                                        </div>

                                                                        <div class="col-md-6">

                                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

System Access Level

                                                                            </label>

                                                                            <select
    id="edit_access_level"
    name="access_level"
    class="form-select"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
        box-shadow:none;
    "
>

                                                                                <option value="">
    Select Access Level
                                                                                </option>

                                                                                <option value="1">
    Level 1 - Dashboard Access
                                                                                </option>

                                                                                <option value="2">
    Level 2 - Personnel Management
                                                                                </option>

                                                                                <option value="3">
    Level 3 - Operations Management
                                                                                </option>

                                                                                <option value="4">
    Level 4 - System Administration
                                                                                </option>

                                                                                <option value="5">
    Level 5 - Super Administrator
                                                                                </option>

                                                                            </select>

                                                                        </div>

                                                                        <div class="col-md-6">

                                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Assigned Responsibility

                                                                            </label>

                                                                            <input
    type="text"
    id="edit_assigned_responsibility"
    name="assigned_responsibility"
    class="form-control"
    placeholder="Fleet & Route Management"
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

                                                                <hr style="
    margin:28px 0;
    border-color:#ece7d6;
">

                                                                <div>

                                                                    <div style="
    font-size:.82rem;
    font-weight:700;
    color:#2e4836;
    margin-bottom:14px;
">

Contact Information

                                                                    </div>

                                                                    <div class="row g-3">

                                                                        <div class="col-md-6">

                                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Contact Number

                                                                            </label>

                                                                            <input
    type="text"
    id="edit_contact_no"
    name="contact_no"
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

                                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

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

                                                                        <div class="col-12">

                                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Residential Address

                                                                            </label>

<textarea
    id="edit_address"
    name="address"
    class="form-control"
    rows="3"
    placeholder="Enter administrator address..."
    style="
        border-radius:16px;
        border:1px solid #dce3ce;
        resize:none;
        box-shadow:none;
    "
></textarea>

                                                                        </div>

                                                                    </div>

                                                                </div>


                                                                <hr style="
    margin:28px 0;
    border-color:#ece7d6;
">

                                                                <div>

                                                                    <div style="
    font-size:.82rem;
    font-weight:700;
    color:#2e4836;
    margin-bottom:14px;
">

Account Credentials

                                                                    </div>

                                                                    <div style="
    background:#f9f4da;
    border:1px solid #ece2ae;
    border-radius:18px;
    padding:20px;
">

                                                                        <div class="row g-3">

                                                                            <div class="col-md-6">

                                                                                <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Username

                                                                                </label>

                                                                                <input
    type="text"
    id="edit_username"
    name="username"
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

                                                                            <div class="col-md-12">

                                                                                <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

New Password

                                                                                </label>

                                                                                <input
    type="password"
    id="edit_password"
    name="password"
    class="form-control"
    placeholder="Leave blank to keep current password"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
        box-shadow:none;
    "
>

                                                                                <small style="
    color:#7d867c;
    font-size:.75rem;
">
    Leave this field empty if you do not want to change the password.
                                                                                </small>

                                                                            </div>

                                                                        </div>

                                                                    </div>

                                                                </div>


                                                                <hr style="
    margin:28px 0;
    border-color:#ece7d6;
">

                                                                <div>

                                                                    <div style="
    font-size:.82rem;
    font-weight:700;
    color:#2e4836;
    margin-bottom:14px;
">

Administrator Documents & Notes

                                                                    </div>

                                                                    <div class="row g-3">


                                                                        <div class="col-md-6">

                                                                            <div style="
    background:#f9f4da;
    border:1px solid #ece2ae;
    border-radius:18px;
    padding:18px;
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

                                                                                        <i class="fas fa-file-pdf"
   style="
      color:#c4b417;
   ">
                                                                                        </i>

                                                                                    </div>

                                                                                    <div>

                                                                                        <div style="
    font-size:.86rem;
    font-weight:700;
    color:#2e4836;
">
Administrator Resume
                                                                                        </div>

                                                                                        <div style="
    font-size:.74rem;
    color:#7c847c;
">
PDF / DOC / DOCX
                                                                                        </div>

                                                                                    </div>

                                                                                </div>

                                                                                <div
    id="edit_resume_file_preview"
    class="small text-muted mb-2"
></div>

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

                                                                                </div>

                                                                            </div>


                                                                            <div class="col-md-6">

                                                                                <div style="
    background:#f9f4da;
    border:1px solid #ece2ae;
    border-radius:18px;
    padding:18px;
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

                                                                                            <i class="fas fa-folder-open"
   style="
      color:#c4b417;
   ">
                                                                                            </i>

                                                                                        </div>

                                                                                        <div>

                                                                                            <div style="
    font-size:.86rem;
    font-weight:700;
    color:#2e4836;
">
Administrative Documents
                                                                                            </div>

                                                                                            <div style="
    font-size:.74rem;
    color:#7c847c;
">
IDs, Certificates & Records
                                                                                            </div>

                                                                                        </div>

                                                                                    </div>

                                                                                    <div
    id="edit_supporting_docs_preview"
    class="small text-muted mb-2"
></div>

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

                                                                                    </div>

                                                                                </div>


                                                                                <div class="col-12">

                                                                                    <label style="
    font-size:.82rem;
    font-weight:700;
    color:#2e4836;
    margin-bottom:10px;
">

Administrator Notes

                                                                                    </label>

<textarea
    id="edit_bio"
    name="bio"
    class="form-control"
    rows="4"
    placeholder="Enter administrator responsibilities, system permissions, operational assignments, certifications, remarks, or additional information..."
    style="
        border-radius:18px;
        border:1px solid #dce3ce;
        resize:none;
    "
></textarea>

                                                                                </div>

                                                                            </div>

                                                                        </div>


                                                                        <div style="
    margin-top:28px;
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

                                                                                    <i class="fas fa-user-pen"
   style="
      color:#c4b417;
   ">
                                                                                    </i>

                                                                                </div>

                                                                                <div>

                                                                                    <div style="
    font-size:.86rem;
    font-weight:700;
    color:#2e4836;
">

Administrator Account Update

                                                                                    </div>

                                                                                    <div style="
    margin-top:4px;
    font-size:.78rem;
    line-height:1.8;
    color:#737b73;
">

Update administrator information,
modify account credentials, manage
system permissions, replace uploaded
documents, and maintain administrative
access for transportation operations
across the Cabanatuan and Rizal
mini bus network.

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
    id="updateAdminBtn"
    class="btn flex-fill"
    style="
        height:54px;
        border:none;
        border-radius:16px;
        background:#c4b417;
        color:#ffffff;
        font-size:.92rem;
        font-weight:700;
        letter-spacing:.2px;
    "
>

                                                                                <i class="fas fa-save me-2"></i>

    Update Administrator

                                                                            </button>

                                                                        </div>

                                                                    </form>

                                                                </div>

                                                            </div>

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                                <style>
                                    @media(max-width:768px) {
                                        #archivedAdminsModal .modal-dialog.modal-xl { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; }
                                        #archivedAdminsModal .modal-content .container { padding: 12px !important; }
                                        #archivedAdminsModal .modal-content .row > div { width: 100% !important; }
                                        #archivedAdminsModal .modal-content h4, #archivedAdminsModal .modal-content h5 { font-size: 1rem !important; }
                                        #archivedAdminsModal .modal-content input, #archivedAdminsModal .modal-content select, #archivedAdminsModal .modal-content textarea { font-size: 0.8rem !important; padding: 6px 10px !important; }
                                        #archivedAdminsModal .modal-content label { font-size: 0.75rem !important; }
                                        #archivedAdminsModal .modal-content .btn { font-size: 0.75rem !important; padding: 6px 12px !important; }
                                    }
                                </style>

                                <div class="modal fade" id="archivedAdminsModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-xl">
                                        <div class="modal-content border-0 overflow-hidden"
     style="border-radius:24px; background:#fef8f3; box-shadow:0 18px 50px rgba(0,0,0,.08);">

                                            <div class="row g-0">

                                                <div class="col-lg-4 d-none d-lg-block"
     style="background:#f9f4da; border-right:1px solid #e7e1c9; min-height:600px;">

                                                    <div class="p-5 h-100 d-flex flex-column justify-content-between">

                                                        <div>

                                                            <div style="width:72px;height:72px;border-radius:22px;background:#ffffff;border:1px solid #ebe5ca;display:flex;align-items:center;justify-content:center;">
                                                                <i class="fa-solid fa-box-archive" style="color:#c4b417; font-size:1.6rem;"></i>
                                                            </div>

                                                            <h2 style="margin-top:28px;font-size:2rem;font-weight:700;line-height:1.3;color:#2e4836;">
    Archived Administrators
                                                            </h2>

                                                            <p style="margin-top:18px;font-size:.94rem;line-height:1.9;color:#70796f;">
    View all archived administrators that have been
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
                                                                        <div style="font-size:.76rem;color:#7d857c;">Admins are hidden from active list</div>
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
                                                                        <div style="font-size:.76rem;color:#7d857c;">Archived admins can be restored anytime</div>
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
                                                                        <div style="font-size:.76rem;color:#7d857c;">All admin records are kept</div>
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
                                                                <i class="fa-solid fa-box-archive me-2" style="color:#8d6e00;"></i>Archived Administrators
                                                            </h4>
                                                            <div style="font-size:.84rem;color:#7b847b;margin-top:4px;">
                                                                <span id="archivedAdminCount">Loading archived records...</span>
                                                            </div>
                                                        </div>

                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>

                                                    <div class="modal-body px-5 py-4">

                                                        <div id="archivedAdminsBody" style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:24px;min-height:300px;">

                                                            <div style="text-align:center;padding:60px 20px;color:#70796f;">
                                                                <i class="fa-solid fa-spinner fa-spin" style="font-size:2rem;color:#c4b417;"></i>
                                                                <p style="margin-top:16px;font-size:.9rem;">Loading archived administrators...</p>
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

                                <script>
                                    function showAlert(type, title, message, onConfirm) { var overlay = document.getElementById('customAlertOverlay'); if (!overlay)return; var header = overlay.querySelector('.custom-alert-header'); var iconEl = overlay.querySelector('.custom-alert-icon'); var titleEl = overlay.querySelector('.custom-alert-title'); var msgEl = overlay.querySelector('.custom-alert-message'); var btn = overlay.querySelector('.custom-alert-btn'); var btnCancel = overlay.querySelector('.custom-alert-btn-cancel'); var actions = overlay.querySelector('.custom-alert-actions'); header.className = 'custom-alert-header '+type; if (type === 'success') { iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#2e4836" stroke-width="3"/><path d="M16 24l6 6 10-10" stroke="#2e4836" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>'; if (btnCancel)btnCancel.style.display = 'none'; if (actions)actions.style.display = ''; btn.className = 'custom-alert-btn success'; btn.textContent = 'Continue' } else { iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#fff" stroke-width="3"/><path d="M18 18l12 12M30 18l-12 12" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>'; if (btnCancel)btnCancel.style.display = 'none'; if (actions)actions.style.display = ''; btn.className = 'custom-alert-btn error'; btn.textContent = 'Close' } titleEl.textContent = title; msgEl.textContent = message; btn.onclick = function () { overlay.classList.remove('active'); if (typeof onConfirm === 'function')onConfirm() }; overlay.classList.add('active') }
                                    function showConfirm(title, message, onConfirm, onCancel) { var overlay = document.getElementById('customAlertOverlay'); if (!overlay)return; var header = overlay.querySelector('.custom-alert-header'); var iconEl = overlay.querySelector('.custom-alert-icon'); var titleEl = overlay.querySelector('.custom-alert-title'); var msgEl = overlay.querySelector('.custom-alert-message'); var btn = overlay.querySelector('.custom-alert-btn'); var btnCancel = overlay.querySelector('.custom-alert-btn-cancel'); var actions = overlay.querySelector('.custom-alert-actions'); header.className = 'custom-alert-header warning'; iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#92400e" stroke-width="3"/><path d="M24 16v10M24 30v2" stroke="#92400e" stroke-width="3" stroke-linecap="round"/></svg>'; if (actions)actions.style.display = 'flex'; if (btnCancel)btnCancel.style.display = 'block'; titleEl.textContent = title; msgEl.textContent = message; btn.className = 'custom-alert-btn confirm'; btn.textContent = 'Confirm'; btn.onclick = function () { overlay.classList.remove('active'); if (typeof onConfirm === 'function')onConfirm() }; if (btnCancel) { btnCancel.className = 'custom-alert-btn cancel'; btnCancel.textContent = 'Cancel'; btnCancel.onclick = function () { overlay.classList.remove('active'); if (typeof onCancel === 'function')onCancel() } } overlay.classList.add('active') }
                                    let allAdmins = [];
                                    let showAllAdmins = false;
                                    let filteredAdmins = [];

                                    const FALLBACK_PHOTO = '/assets/images/admin/driversinfo/drivericon.png';

                                    function photoUrl(value) {
                                        if (!value || String(value).trim() === '') {
                                            return FALLBACK_PHOTO;
                                        }
                                        const v = String(value).trim();
                                        if (v.startsWith('http://') || v.startsWith('https://') || v.startsWith('/')) {
                                            return v;
                                        }
                                        return '/uploads/admin/' + v;
                                    }

                                    function fileUrl(value) {
                                        if (!value || String(value).trim() === '') {
                                            return '';
                                        }
                                        const v = String(value).trim();
                                        if (v.startsWith('http://') || v.startsWith('https://') || v.startsWith('/')) {
                                            return v;
                                        }
                                        return '/uploads/admin/' + v;
                                    }

                                    function formatRole(role) {
                                        if (!role) return '-';
                                        return String(role)
                                        .replace(/_/g, ' ')
                                        .replace(/\b\w/g, c => c.toUpperCase());
                                    }

                                    function statusBadge(admin) {
                                        const status = (admin.status || '').toLowerCase();
                                        if (status === 'active' || (!status && Number(admin.is_active))) {
                                            return '<span class="badge" style="background:#e9f6e9;color:#2e7d32;font-weight:600;border:1px solid #c3e6cb;">Active</span>';
                                        }
                                        if (status === 'inactive' || (!status && !Number(admin.is_active))) {
                                            return '<span class="badge" style="background:#fdeaea;color:#b94a48;font-weight:600;border:1px solid #f5c6c2;">Inactive</span>';
                                        }
                                        return '<span class="badge" style="background:#faf6e3;color:#8a7414;font-weight:600;border:1px solid #e5dcae;">Pending</span>';
                                    }

                                    document.addEventListener(
                                    'DOMContentLoaded',
                                    () => {

                                        loadAdmins();

                                    }
                                    );

                                    async function loadAdmins()
                                    {
                                        try {

                                            const response =
                                            await fetch(
                                            '/admin/api/Admin/GetAdmin.php'
                                            );

                                            const raw =
                                            await response.text();

                                            const result =
                                            JSON.parse(
                                            raw
                                            );

                                            if (
                                            !result.success
                                            ) {
                                                throw new Error(
                                                result.message ||
                                                'Failed to load admins'
                                                );
                                            }

                                            allAdmins =
                                            result.data || [];

                                            filteredAdmins =
                                            [...allAdmins];

                                            document.getElementById(
                                            'adminCount'
                                            ).textContent =
                                            `${allAdmins.length} administrator(s) found`;

                                            renderAdmins();

                                        } catch (error) {

                                            document.getElementById(
                                            'adminsContainer'
                                            ).innerHTML =
                                            `
        <div
            class="
                col-12
                text-center
                text-danger
                py-4
            "
        >
            Failed to load administrators.
        </div>
        `;

                                            document.getElementById(
                                            'adminCount'
                                            ).textContent =
                                            'Failed to load administrators';
                                        }
                                    }


                                    function renderAdmins()
                                    {
                                        const container =
                                        document.getElementById(
                                        'adminsContainer'
                                        );

                                        container.innerHTML = '';

                                        const adminsToShow =
                                        showAllAdmins
                                        ? filteredAdmins
                                        : filteredAdmins.slice(0, 3);

                                        adminsToShow.forEach(admin => {

                                            const fullName = (
                                            admin.full_name &&
                                            admin.full_name.trim() !== ''
                                            )
                                            ? admin.full_name
                                            : `${admin.first_name ?? ''} ${admin.last_name ?? ''}`.trim();

                                            const adminId = admin.employee_id || `ADM${String(admin.id).padStart(3,'0')}`;
                                            const contactNumber = admin.contact_no || admin.phone || '-';
                                            const email = admin.email || '-';
                                            const department = admin.department || '-';
                                            const shift = admin.shift_status || '-';
                                            const accessLevel = admin.access_level || admin.shift_schedule || '-';

                                            container.innerHTML +=
                                            `
        <div class="col-12 col-md-6 col-xl-4 d-flex">

            <div class="admin-card w-100 d-flex flex-column">

                <div class="admin-card-accent"></div>

                <div class="p-3 d-flex align-items-start gap-3">

                    <img
                        src="${photoUrl(admin.profile_picture || admin.profile_photo)}"
                        class="rounded-circle"
                        onerror="this.onerror=null;this.src='${FALLBACK_PHOTO}';"
                        style="width:76px;height:76px;object-fit:cover;border:3px solid #e5e0a4;flex-shrink:0;"
                    >

                    <div class="flex-grow-1" style="min-width:0;">

                        <h6
                            class="mb-0 fw-semibold"
                            style="color:#2e4836;font-size:.95rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            title="${fullName}"
                        >
                            ${fullName}
                        </h6>

                        <div class="small mt-1" style="color:#8a9a8a;">
                            ${adminId}
                        </div>

                        <div class="mt-1">
                            ${statusBadge(admin)}
                        </div>

                    </div>

                    <div class="dropdown">

                        <button
                            class="btn btn-sm admin-menu-btn"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            style="background:none;border:none;color:#8a9a8a;font-size:1.1rem;padding:2px 6px;"
                        >
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end" style="border:1px solid #e5e0a4;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.08);font-size:.85rem;">

                            <li>
                                <a class="dropdown-item editAdminBtn" href="javascript:void(0)" data-id="${admin.id}">
                                    <i class="bi bi-pencil me-2" style="color:#295738;"></i>Edit Administrator
                                </a>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <li>
                                <a class="dropdown-item archiveAdminBtn" href="javascript:void(0)" data-id="${admin.id}" style="color:#b94a48;">
                                    <i class="bi bi-archive me-2"></i>Archive Administrator
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
                                <i class="bi bi-shield-lock admin-info-icon"></i>
                                <span class="admin-info-label">Role</span>
                            </div>
                            <div class="admin-info-value">${formatRole(admin.role)}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-diagram-3 admin-info-icon"></i>
                                <span class="admin-info-label">Department</span>
                            </div>
                            <div class="admin-info-value">${department}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-clock admin-info-icon"></i>
                                <span class="admin-info-label">Shift</span>
                            </div>
                            <div class="admin-info-value">${shift}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-person-gear admin-info-icon"></i>
                                <span class="admin-info-label">Access Level</span>
                            </div>
                            <div class="admin-info-value">${accessLevel}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-telephone admin-info-icon"></i>
                                <span class="admin-info-label">Contact</span>
                            </div>
                            <div class="admin-info-value">${contactNumber}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-envelope admin-info-icon"></i>
                                <span class="admin-info-label">Email</span>
                            </div>
                            <div class="admin-info-value">${email}</div>

                        </div>

                    </div>

                </div>

                <div class="p-3 mt-auto d-flex gap-2">

                    <button
                        class="btn btn-sm admin-action-btn btn-view flex-fill editAdminBtn"
                        data-id="${admin.id}"
                    >
                        <i class="bi bi-pencil me-1"></i>Edit
                    </button>

                    <button
                        class="btn btn-sm admin-action-btn btn-edit flex-fill archiveAdminBtn"
                        data-id="${admin.id}"
                    >
                        <i class="bi bi-archive me-1"></i>Archive
                    </button>

                </div>

            </div>

        </div>
        `;
                                        });

                                        document.getElementById(
                                        'adminCount'
                                        ).textContent =
                                        `${filteredAdmins.length} administrator(s) found`;

                                        renderAdminToggle();
                                    }

                                    function renderAdminToggle()
                                    {
                                        const button =
                                        document.getElementById(
                                        'toggleAdmins'
                                        );

                                        const icon =
                                        document.getElementById(
                                        'adminIcon'
                                        );

                                        if (
                                        !button ||
                                        !icon
                                        ) {
                                            return;
                                        }

                                        if (
                                        filteredAdmins.length <= 3
                                        ) {

                                            button.style.display =
                                            'none';

                                            return;
                                        }

                                        button.style.display =
                                        'flex';

                                        button.onclick =
                                        function () {

                                            showAllAdmins =
                                            !showAllAdmins;

                                            button.querySelector(
                                            'span'
                                            ).textContent =
                                            showAllAdmins
                                            ? 'Hide Administrators'
                                            : 'View All Administrators';

                                            icon.className =
                                            showAllAdmins
                                            ? 'bi bi-chevron-up'
                                            : 'bi bi-chevron-down';

                                            renderAdmins();
                                        };
                                    }

                                    document.addEventListener(
                                    'click',
                                    function (e) {

                                        const editBtn =
                                        e.target.closest(
                                        '.editAdminBtn'
                                        );

                                        if (!editBtn) {
                                            return;
                                        }

                                        editAdmin(
                                        Number(
                                        editBtn.dataset.id
                                        )
                                        );

                                    }
                                    );

                                    document.addEventListener(
                                    'click',
                                    function (e) {

                                        const archiveBtn =
                                        e.target.closest(
                                        '.archiveAdminBtn'
                                        );

                                        if (!archiveBtn) {
                                            return;
                                        }

                                        archiveAdmin(
                                        Number(
                                        archiveBtn.dataset.id
                                        )
                                        );

                                    }
                                    );

                                    function searchAdmins()
                                    {
                                        const search =
                                        document
                                        .getElementById(
                                        'adminSearch'
                                        )
                                        .value
                                        .toLowerCase()
                                        .trim();

                                        if (!search) {

                                            filteredAdmins =
                                            [...allAdmins];

                                            renderAdmins();

                                            return;
                                        }

                                        filteredAdmins =
                                        allAdmins.filter(admin => {

                                            const fullName =
                                            `${admin.first_name || ''} ${admin.last_name || ''}`
                                            .toLowerCase();

                                            const adminId =
                                            `ADM${String(admin.id).padStart(3,'0')}`
                                            .toLowerCase();

                                            const email =
                                            (admin.email || '')
                                            .toLowerCase();

                                            const department =
                                            (admin.department || '')
                                            .toLowerCase();

                                            return (
                                            fullName.includes(search) ||
                                            adminId.includes(search) ||
                                            email.includes(search) ||
                                            department.includes(search)
                                            );

                                        });

                                        showAllAdmins = false;

                                        renderAdmins();
                                    }

                                    function openAddAdminModal()
                                    {
                                        const modal =
                                        new bootstrap.Modal(
                                        document.getElementById(
                                        'addAdminModal'
                                        )
                                        );

                                        modal.show();
                                    }

                                    let editAdminModal =
                                    new bootstrap.Modal(
                                    document.getElementById(
                                    'editAdminModal'
                                    )
                                    );

                                    async function editAdmin(
                                    adminId
                                    ) {
                                        try {

                                            const response =
                                            await fetch(
                                            `/admin/api/Admin/GetSingleAdmin.php?id=${adminId}`
                                            );

                                            const result =
                                            await response.json();

                                            console.log(
                                            'GetAdmin Response:',
                                            result
                                            );

                                            if (
                                            !result.success
                                            ) {

                                                showAlert('error', 'Failed', result.message || 'Unable to load administrator.');

                                                return;

                                            }

                                            const admin =
                                            result.admin
                                            || result.data;

                                            if (
                                            !admin
                                            ) {

                                                console.error(
                                                'Admin data missing:',
                                                result
                                                );

                                                showAlert('error', 'Data Error', 'Administrator data not found in API response.');

                                                return;

                                            }

                                            document.getElementById(
                                            'edit_admin_id'
                                            ).value =
                                            admin.id ?? '';

                                            document.getElementById(
                                            'edit_admin_id_display'
                                            ).value =
                                            admin.employee_id ?? '';

                                            document.getElementById(
                                            'edit_full_name'
                                            ).value =
                                            admin.full_name?.trim()
                                            ||
                                            `${admin.first_name ?? ''} ${admin.last_name ?? ''}`.trim();

                                            document.getElementById(
                                            'edit_role'
                                            ).value =
                                            admin.role ?? '';

                                            document.getElementById(
                                            'edit_status'
                                            ).value =
                                            admin.status ?? '';

                                            document.getElementById(
                                            'edit_contact_no'
                                            ).value =
                                            admin.contact_no
                                            || admin.phone
                                            || '';

                                            document.getElementById(
                                            'edit_email'
                                            ).value =
                                            admin.email ?? '';

                                            document.getElementById(
                                            'edit_address'
                                            ).value =
                                            admin.address ?? '';

                                            document.getElementById(
                                            'edit_username'
                                            ).value =
                                            admin.username ?? '';

                                            document.getElementById(
                                            'edit_password'
                                            ).value =
                                            '';

                                            document.getElementById(
                                            'edit_assigned_department'
                                            ).value =
                                            admin.department ?? '';

                                            document.getElementById(
                                            'edit_shift_status'
                                            ).value =
                                            admin.shift_status ?? '';

                                            document.getElementById(
                                            'edit_access_level'
                                            ).value =
                                            admin.shift_schedule ?? '';

                                            document.getElementById(
                                            'edit_assigned_responsibility'
                                            ).value =
                                            admin.assigned_area ?? '';

                                            document.getElementById(
                                            'edit_bio'
                                            ).value =
                                            admin.bio ?? '';

                                            const profilePreview =
                                            document.getElementById(
                                            'edit_profile_preview'
                                            );

                                            if (
                                            profilePreview
                                            ) {

                                                profilePreview.onerror =
                                                null;

                                                profilePreview.src =
                                                photoUrl(
                                                admin.profile_picture ||
                                                admin.profile_photo
                                                );

                                            }

                                            document.getElementById(
                                            'edit_resume_file_preview'
                                            ).innerHTML =
                                            admin.resume_file
                                            ? `
                <a
                    href="${fileUrl(admin.resume_file)}"
                    target="_blank"
                >
                    Current Resume
                </a>
            `
                                            : 'No resume uploaded';

                                            document.getElementById(
                                            'edit_supporting_docs_preview'
                                            ).innerHTML =
                                            admin.supporting_docs
                                            ? `
                <a
                    href="${fileUrl(admin.supporting_docs)}"
                    target="_blank"
                >
                    Current Supporting Document
                </a>
            `
                                            : 'No supporting document uploaded';

                                            editAdminModal.show();

                                        } catch (error) {

                                            console.error(
                                            'Edit Admin Error:',
                                            error
                                            );

                                            showAlert('error', 'Error', error.message);

                                        }
                                    }

                                    const editProfilePicture =
                                    document.getElementById(
                                    'edit_profile_picture'
                                    );

                                    if (
                                    editProfilePicture
                                    ) {

                                        editProfilePicture.addEventListener(
                                        'change',
                                        function () {

                                            if (
                                            this.files &&
                                            this.files[0]
                                            ) {

                                                document.getElementById(
                                                'edit_profile_preview'
                                                ).src =
                                                URL.createObjectURL(
                                                this.files[0]
                                                );

                                            }

                                        }
                                        );

                                    }

                                    const updateAdminBtn =
                                    document.getElementById(
                                    'updateAdminBtn'
                                    );

                                    if (
                                    updateAdminBtn
                                    ) {

                                        updateAdminBtn.addEventListener(

                                        'click',

                                        async() => {

                                            const form =
                                            document.getElementById(
                                            'editAdminForm'
                                            );

                                            if (
                                            !form.checkValidity()
                                            ) {

                                                form.reportValidity();

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

                                                '/admin/api/Admin/UpdateAdmin.php',

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

                                                    editAdminModal.hide();

                                                    setTimeout(() => {

                                                        document
                                                        .querySelectorAll(
                                                        '.modal-backdrop'
                                                        )
                                                        .forEach(
                                                        x => x.remove()
                                                        );

                                                        document.body
                                                        .classList
                                                        .remove(
                                                        'modal-open'
                                                        );

                                                    }, 300);

                                                    if (
                                                    typeof loadAdmins
                                                    === 'function'
                                                    ) {

                                                        loadAdmins();

                                                    }

                                                    if (
                                                    typeof loadAdminStats
                                                    === 'function'
                                                    ) {

                                                        loadAdminStats();

                                                    }

                                                    showAlert('success', 'Administrator Updated', result.message || 'Administrator updated successfully.');

                                                } else {

                                                    showAlert('error', 'Update Failed', result.message || 'Unknown error.');

                                                }

                                            } catch (error) {

                                                showAlert('error', 'JavaScript Error', error.message);

                                            }

                                        }

                                        );

                                    }

                                    async function archiveAdmin(
                                    adminId
                                    ) {
                                        showConfirm('Archive Administrator?', 'This administrator will be marked as inactive.', async function () {

                                            try {

                                                const response =
                                                await fetch(

                                                '/admin/api/Admin/ArchiveAdmin.php',

                                                {
                                                    method: 'POST',

                                                    headers: {
                                                        'Content-Type':
                                                        'application/json'
                                                    },

                                                    body:
                                                    JSON.stringify({

                                                        id: adminId

                                                    })

                                                }

                                                );

                                                const data =
                                                await response.json();

                                                if (
                                                data.success
                                                ) {

                                                    showAlert('success', 'Archived', 'Administrator archived successfully.');

                                                    loadAdmins();

                                                    if (
                                                    typeof loadAdminStats
                                                    === 'function'
                                                    ) {
                                                        loadAdminStats();
                                                    }

                                                } else {

                                                    showAlert('error', 'Failed', data.message);

                                                }

                                            } catch (error) {

                                                showAlert('error', 'Error', error.message);

                                            }
                                        });

                                    }

                                    document.getElementById('archivedAdminsModal').addEventListener('show.bs.modal', loadArchivedAdmins);

                                    document.getElementById('archivedAdminsModal').addEventListener('hidden.bs.modal', function () {
                                        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                                        document.body.classList.remove('modal-open');
                                        document.body.style.removeProperty('padding-right');
                                        document.body.style.removeProperty('overflow');
                                    });

                                    function openArchivedAdmins()
                                    {
                                        const el = document.getElementById('archivedAdminsModal');
                                        if (!el) return;
                                        let modal = bootstrap.Modal.getInstance(el);
                                        if (!modal) modal = new bootstrap.Modal(el);
                                        modal.show();
                                    }

                                    async function loadArchivedAdmins()
                                    {
                                        try {

                                            const response =
                                            await fetch(
                                            '/admin/api/Admin/GetArchivedAdmins.php'
                                            );

                                            const raw =
                                            await response.text();

                                            const result =
                                            JSON.parse(
                                            raw
                                            );

                                            if (
                                            !result.success
                                            ) {

                                                showAlert('error', 'Failed', result.message || 'Unable to load archived administrators.');

                                                return;

                                            }

                                            const container =
                                            document.getElementById(
                                            'archivedAdminsBody'
                                            );

                                            if (!container) {

                                                return;

                                            }

                                            if (!result.data || result.data.length === 0) {
                                                container.innerHTML = '<div style="text-align:center;padding:60px 20px;color:#70796f;"><i class="fa-solid fa-box-open" style="font-size:2rem;color:#c4b417;"></i><p style="margin-top:16px;font-size:.9rem;">No archived administrators found.</p></div>';
                                                return;
                                            }

                                            let rows = '';
                                            result.data.forEach(admin => {
                                                var fullName = admin.full_name?.trim() || (admin.first_name || '') + ' ' + (admin.last_name || '') || admin.username || admin.email || '-';
                                                rows += '<tr style="border-bottom:1px solid #f0ede0;">';
                                                rows += '<td style="padding:14px 16px;color:#2e4836;font-weight:600;font-size:0.85rem;">' + (admin.employee_id || 'ID-' + admin.id || '-') + '</td>';
                                                rows += '<td style="padding:14px 16px;color:#475569;font-size:0.85rem;">' + fullName + '</td>';
                                                rows += '<td style="padding:14px 16px;color:#475569;font-size:0.85rem;">' + ((admin.role || '-').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())) + '</td>';
                                                rows += '<td style="padding:14px 16px;color:#475569;font-size:0.85rem;">' + (admin.department || admin.bio || '-') + '</td>';
                                                rows += '<td style="padding:14px 16px;"><span style="background:#f8d7da;color:#721c24;padding:4px 14px;border-radius:20px;font-size:0.75rem;font-weight:600;display:inline-block;">Archived</span></td>';
                                                rows += '<td style="padding:14px 16px;"><button class="restore-admin-btn" data-id="' + admin.id + '" style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:8px;padding:6px 14px;font-size:0.75rem;font-weight:600;"><i class="fa-solid fa-rotate-left me-1"></i>Restore</button></td>';
                                                rows += '</tr>';
                                            });

                                            container.innerHTML = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">' +
                                            '<thead><tr style="background:linear-gradient(135deg,#f9f4da 0%,#f5edd0 100%);">' +
                                            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Admin ID</th>' +
                                            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Name</th>' +
                                            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Role</th>' +
                                            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Department</th>' +
                                            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Status</th>' +
                                            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Action</th>' +
                                            '</tr></thead><tbody>' + rows + '</tbody></table></div>';

                                            document.querySelectorAll('.restore-admin-btn').forEach(btn => {
                                                btn.addEventListener('click', async function () {
                                                    const id = this.getAttribute('data-id');
                                                    showConfirm('Restore Administrator?', 'Set this admin back to Active?', async function () {

                                                        try {
                                                            const fd = new FormData();
                                                            fd.append('id', id);
                                                            const res = await fetch('/admin/api/Admin/RestoreAdmin.php', { method: 'POST', body: fd });
                                                            const data = await res.json();
                                                            if (data.success) {
                                                                showAlert('success', 'Restored', 'Administrator restored successfully.');
                                                                location.reload();
                                                            } else {
                                                                showAlert('error', 'Failed', data.message);
                                                            }
                                                        } catch (err) {
                                                            showAlert('error', 'Error', err.message);
                                                        }
                                                    });
                                                });
                                            });

                                        } catch (error) {

                                            showAlert('error', 'Error', error.message);

                                        }
                                    }

                                    async function restoreAdmin(
                                    adminId
                                    ) {
                                        showConfirm('Restore Administrator?', 'This administrator will be restored to active status.', async function () {

                                            try {

                                                const response =
                                                await fetch(

                                                '/admin/api/Admin/RestoreAdmin.php',

                                                {
                                                    method: 'POST',

                                                    headers: {
                                                        'Content-Type':
                                                        'application/json'
                                                    },

                                                    body:
                                                    JSON.stringify({

                                                        id: adminId

                                                    })

                                                }

                                                );

                                                const raw =
                                                await response.text();

                                                const result =
                                                JSON.parse(
                                                raw
                                                );

                                                if (
                                                result.success
                                                ) {

                                                    showAlert('success', 'Administrator Restored', 'Administrator restored successfully.');

                                                    if (
                                                    typeof loadArchivedAdmins
                                                    === 'function'
                                                    ) {
                                                        loadArchivedAdmins();
                                                    }

                                                    if (
                                                    typeof loadAdmins
                                                    === 'function'
                                                    ) {
                                                        loadAdmins();
                                                    }

                                                    if (
                                                    typeof loadAdminStats
                                                    === 'function'
                                                    ) {
                                                        loadAdminStats();
                                                    }

                                                    setTimeout(() => {

                                                        document
                                                        .querySelectorAll(
                                                        '.modal-backdrop'
                                                        )
                                                        .forEach(
                                                        backdrop =>
                                                        backdrop.remove()
                                                        );

                                                        document.body
                                                        .classList
                                                        .remove(
                                                        'modal-open'
                                                        );

                                                        document.body.style.overflow =
                                                        '';

                                                        document.body.style.paddingRight =
                                                        '';

                                                    }, 200);

                                                } else {
                                                    showAlert('error', 'Restore Failed', result.message || 'Unable to restore administrator.');

                                                }

                                            } catch (error) {

                                                showAlert('error', 'Error', error.message);

                                            }

                                        });

                                    }

                                </script>

                            </div>

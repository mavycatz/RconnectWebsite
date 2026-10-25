<script src="/admin/assets/js/admin-user-validation.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/assets/js/admin-user-validation.js') ?: time() ?>"></script>
<style>
    .finance-cards-section,
    .finance-cards-section * {
        font-family: 'Poppins', sans-serif;
    }
    .finance-card {
        position: relative;
        background: #fffdf8;
        border: 1px solid #e5e0a4;
        border-radius: 17px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .04);
        transition: box-shadow .2s ease, transform .2s ease;
    }
    .finance-card:hover {
        box-shadow: 0 8px 22px rgba(41, 87, 56, .10);
    }
    .finance-card-accent {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: #a5d6a7;
        border-radius: 0 0 17px 17px;
    }
    .finance-menu-btn:hover {
        color: #295738 !important;
        background: #eef5ee !important;
        border-radius: 8px;
    }
    .finance-info-icon {
        font-size: .78rem;
        color: #295738;
        line-height: 1;
    }
    .finance-info-label {
        font-size: 11px;
        font-weight: 500;
        color: #8a9a8a;
        letter-spacing: .3px;
    }
    .finance-info-value {
        font-size: 13px;
        font-weight: 500;
        color: #2e4836;
        margin-top: 1px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .finance-action-btn {
        font-size: .78rem;
        font-weight: 500;
        border-radius: 10px;
        padding: 7px 10px;
    }
    .finance-action-btn.btn-view {
        background: #ffffff;
        border: 1px solid #c9d8c9;
        color: #295738;
    }
    .finance-action-btn.btn-view:hover {
        background: #eef5ee;
        border-color: #a5d6a7;
    }
    .finance-action-btn.btn-edit {
        background: #ffffff;
        border: 1px solid #e5e0a4;
        color: #687168;
    }
    .finance-action-btn.btn-edit:hover {
        background: #faf9e5;
        border-color: #d5cc8e;
    }
    .finance-status-select {
        width: 175px;
        height: 43px;
        border-radius: 12px;
        border: 1px solid #e5e0a4;
        font-size: .85rem;
        color: #687168;
        background-color: #fffdf8;
    }
    .finance-search-wrap {
        width: 350px;
    }
    .finance-search-wrap .form-control {
        border-color: #e5e0a4;
        border-left: none;
        border-radius: 0 12px 12px 0;
        height: 43px;
        font-size: .85rem;
    }
    .finance-search-wrap .form-control:focus,
    .finance-status-select:focus {
        border-color: #a5d6a7;
        box-shadow: 0 0 0 .2rem rgba(41, 87, 56, .08);
    }
    .finance-search-wrap .input-group-text {
        background: #fffdf8;
        border-color: #e5e0a4;
        border-right: none;
        border-radius: 12px 0 0 12px;
        color: #295738;
    }
    @media(max-width:768px) {
        .p-4.mb-4[style*="border:1px solid #e8e8e8"] { padding: 10px !important; }
        .p-4.mb-4[style*="border:1px solid #e8e8e8"] input { font-size: 0.8rem !important; }
        .p-4.mb-4[style*="border:1px solid #e8e8e8"] .input-group { width: 100% !important; }
        .p-4.mb-4[style*="border:1px solid #e8e8e8"] .btn { min-height: 42px !important; font-size: 0.75rem !important; padding: 6px 10px !important; }
        #employeeContainer > div { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }
        #employeeContainer .p-4 { padding: 10px !important; }
        #employeeContainer [style*="width:360px"] { width: 100% !important; max-width: 100% !important; }
        #employeeContainer [style*="width:85px"] { width: 50px !important; height: 50px !important; }
        #employeeContainer h6 { font-size: 0.8rem !important; }
        #employeeContainer .small { font-size: 0.65rem !important; }
        #employeeContainer .btn { min-height: 38px !important; font-size: 0.7rem !important; padding: 5px 8px !important; }
    }
</style>

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
                        class="bi bi-cash-stack"
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
                         Employee Management
                        </h5>

                        <small
                        id="employeeCount"
                        class="text-muted"
                    >
                        Loading Employees...
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
                class="input-group"
                style="
                    width:auto;max-width:350px;flex-shrink:1;min-width:140px;
                "
            >

                    <span
                    class="input-group-text"
                    style="
                        background:#faf9e5;
                        border-color:#e5e0a4;
                    "
                >
                        <i
                        class="bi bi-search"
                    ></i>
                        </span>

                        <input
                    type="text"
                    id="employeeSearch"
                    class="form-control"
                    placeholder="Search by name, employee ID, role, department..."
                    oninput="searchEmployees()"
                    style="
                        border-color:#e5e0a4;
                    "
                >

                    </div>


                    <button
                type="button"
                class="btn px-3 py-2"
                data-bs-toggle="modal" data-bs-target="#archivedEmployeesModal"
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
                data-bs-toggle="modal"
                data-bs-target="#addEmployeeModal"
            >

                            <i
                    class="
                        fas
                        fa-user-plus
                        me-2
                    "
                ></i>

                Add New Employee

                            </button>

                        </div>

                    </div>

                </div>
                <div class="container-fluid px-3">

                    <div
        class="row g-4"
        id="employeeContainer">

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
            id="toggleEmployees">

                            <span>
                View All Employees
                            </span>

                            <i
                class="bi bi-chevron-down"
                id="employeeIcon">
                            </i>

                        </button>

                    </div>

                </div>


                <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
/>

                <div class="modal fade"
     id="editEmployeeModal"
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

                                                <i class="fa-solid fa-coins"
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

        Finance Employee Management

                                            </h2>

                                            <p style="
        margin-top:18px;
        font-size:.95rem;
        line-height:1.9;
        color:#6d766d;
    ">

        Register finance employees, manage department
        assignments, configure account access, upload
        employee documents, and maintain finance
        personnel records across the organization.

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

                                                        <i class="fas fa-id-card"
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
        Employee Records
                                                        </div>

                                                        <div style="
        font-size:.76rem;
        color:#7c847c;
    ">
        Finance personnel profiles and credentials
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

                                                        <i class="fas fa-building"
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
        Finance Operations
                                                        </div>

                                                        <div style="
        font-size:.76rem;
        color:#7c847c;
    ">
        Department assignments and work schedules
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

                                                        <i class="fas fa-file-upload"
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
        Document Storage
                                                        </div>

                                                        <div style="
        font-size:.76rem;
        color:#7c847c;
    ">
        Employee files, resumes and supporting documents
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

        Edit Finance Employee

                                            </h4>

                                            <div style="
        font-size:.84rem;
        color:#7d867c;
    ">

        Update employee information, permissions, schedules, and account settings

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

                                            <form
    id="editEmployeeForm"
    data-uv-context="finance"
    enctype="multipart/form-data"
>

                                                <input
    type="hidden"
    id="edit_id"
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
            alt="Employee Photo"
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

Employee Information

                                                        </div>

                                                        <div class="row g-3">

                                                            <div class="col-md-6">

                                                                <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Employee Name

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

Employee ID

                                                                </label>

                                                                <input
    type="hidden"
    id="edit_employee_hidden_id"
    name="employee_id"
>

                                                                <input
    type="text"
    id="edit_employee_id"
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

Role

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

                                                                    <option value="finance_manager">
        Finance Manager
                                                                    </option>

                                                                    <option value="finance_officer">
        Finance Officer
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

Status

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
        Pending
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

Work Assignment & Access Control

                                                        </div>

                                                        <div class="row g-3">

                                                            <div class="col-md-6">

                                                                <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Assigned Area

                                                                </label>

                                                                <input
    type="text"
    id="edit_assigned_area"
    name="assigned_area"
    class="form-control"
    placeholder="Finance Office"
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

Access Level

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
    Level 1 - Basic Access
                                                                    </option>

                                                                    <option value="2">
    Level 2 - Financial Records
                                                                    </option>

                                                                    <option value="3">
    Level 3 - Finance Management
                                                                    </option>

                                                                    <option value="4">
    Level 4 - Administrative Access
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

Shift Schedule

                                                                </label>

                                                                <input
    type="text"
    id="edit_shift_schedule"
    name="shift_schedule"
    class="form-control"
    placeholder="8:00 AM - 5:00 PM"
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

Address

                                                                </label>

<textarea
    id="edit_address"
    name="address"
    class="form-control"
    rows="3"
    placeholder="Enter employee address..."
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

Employee ID

                                                                    </label>

                                                                    <input
    type="text"
    id="employee_id_display"
    class="form-control"
    readonly
    value="Auto Generated"
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

Employee ID

                                                                                </label>

                                                                                <input
    type="text"
    id="edit_employee_id2"
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
    readonly
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
        background:#ffffff;
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

New Password (Optional)

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

                                                                                <div style="
    margin-top:8px;
    font-size:.75rem;
    color:#7c847c;
">

Only enter a password if you want to change the employee's current password.

                                                                                </div>

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

Documents & Notes

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
    font-size:.86rem;
    font-weight:700;
    color:#2e4836;
    margin-bottom:12px;
">
Resume
                                                                                </div>

                                                                                <a
    id="edit_resume_link"
    href="#"
    target="_blank"
    class="btn btn-sm w-100"
    style="
        background:#fff;
        border:1px solid #dce3ce;
        border-radius:12px;
        margin-bottom:12px;
    "
>
    View Current Resume
                                                                                </a>

                                                                                <input
    type="file"
    id="edit_resume_file"
    name="resume_file"
    class="form-control"
    accept=".pdf,.doc,.docx"
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
    font-size:.86rem;
    font-weight:700;
    color:#2e4836;
    margin-bottom:12px;
">
Supporting Documents
                                                                                </div>

                                                                                <a
    id="edit_docs_link"
    href="#"
    target="_blank"
    class="btn btn-sm w-100"
    style="
        background:#fff;
        border:1px solid #dce3ce;
        border-radius:12px;
        margin-bottom:12px;
    "
>
    View Current Document
                                                                                </a>

                                                                                <input
    type="file"
    id="edit_supporting_docs"
    name="supporting_docs"
    class="form-control"
    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
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

Employee Notes

                                                                            </label>

<textarea
    id="edit_bio"
    name="bio"
    class="form-control"
    rows="4"
    style="
        border-radius:18px;
        border:1px solid #dce3ce;
        resize:none;
    "
></textarea>

                                                                        </div>

                                                                    </div>
                                                                </div>

                                                            </div>

                                                        </div>

                                                    </div>

                                                </form>


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

                                                            <i class="fas fa-chart-line"
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

Finance Department Registration

                                                            </div>

                                                            <div style="
    margin-top:4px;
    font-size:.78rem;
    line-height:1.8;
    color:#737b73;
">

The system will automatically create
employee accounts, generate Employee IDs,
manage access permissions, store uploaded
documents, and support financial reporting
operations across the organization.

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
    id="updateEmployeeBtn"
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

    Update Employee

                                                    </button>
                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>
                </div>

                <script>
                    let allEmployees = [];
                    let showAllEmployees = false;

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
                        return '/uploads/employees/photos/' + v.replace(/^\/+/, '');
                    }

                    function fileUrl(value) {
                        const v = (value === null || value === undefined) ? '' : String(value).trim();
                        if (v === '') {
                            return '';
                        }
                        return '/' + v.replace(/^\/+/, '');
                    }

                    function statusBadge(status) {
                        const s = String(status || '').toLowerCase();
                        if (s === 'active') {
                            return '<span class="badge" style="background:#e9f2e9;color:#295738;font-weight:600;border:1px solid #c9d8c9;">Active</span>';
                        }
                        if (s === 'inactive') {
                            return '<span class="badge" style="background:#fdeaea;color:#b94a48;font-weight:600;border:1px solid #f5c6c2;">Inactive</span>';
                        }
                        return '<span class="badge" style="background:#faf6e3;color:#8a7414;font-weight:600;border:1px solid #e5dcae;">Pending</span>';
                    }

                    document.addEventListener(
                    'DOMContentLoaded',
                    loadEmployees
                    );

                    function searchEmployees()
                    {
                        const keyword =
                        document
                        .getElementById(
                        'employeeSearch'
                        )
                        .value
                        .toLowerCase()
                        .trim();

                        const filtered =
                        allEmployees.filter(
                        employee => {

                            return (

                            (employee.full_name || '')
                            .toLowerCase()
                            .includes(keyword)

                            ||

                            (employee.employee_id || '')
                            .toLowerCase()
                            .includes(keyword)

                            ||

                            (employee.role || '')
                            .toLowerCase()
                            .includes(keyword)

                            ||

                            (employee.email || '')
                            .toLowerCase()
                            .includes(keyword)

                            ||

                            (employee.assigned_area || '')
                            .toLowerCase()
                            .includes(keyword)

                            );

                        }
                        );

                        renderEmployees(
                        filtered
                        );
                    }

                    async function loadEmployees()
                    {
                        try {

                            const response =
                            await fetch(
                            '/admin/api/Finance/getFinance.php?action=getEmployees'
                            );

                            const result =
                            await response.json();

                            if (!result.success) {

                                document.getElementById(
                                'employeeCount'
                                ).innerText =
                                'Failed to load.';

                                console.error(
                                result.message
                                );

                                return;

                            }

                            allEmployees =
                            result.data || [];

                            if (allEmployees.length === 0) {

                                document.getElementById(
                                'employeeCount'
                                ).innerText =
                                'No employees found.';

                            }

                            renderEmployees();

                        } catch (error) {

                            console.error(
                            'Failed to load employees:',
                            error
                            );

                            const count =
                            document.getElementById(
                            'employeeCount'
                            );

                            if (count) {

                                count.innerText =
                                'Error loading.';

                            }

                        }
                    }

                    function formatRole(role)
                    {
                        if (!role) {

                            return '-';

                        }

                        return role
                        .replaceAll('_', ' ')
                        .replace(/\b\w/g, c => c.toUpperCase());
                    }

                    function renderEmployees(
                    employeeList = null
                    )
                    {
                        const container =
                        document.getElementById(
                        'employeeContainer'
                        );

                        if (!container) {

                            return;

                        }

                        container.innerHTML = '';

                        const sourceEmployees =
                        employeeList ||
                        allEmployees;

                        const countEl =
                        document.getElementById(
                        'employeeCount'
                        );

                        if (countEl) {

                            countEl.innerText =
                            sourceEmployees.length +
                            ' Employee' +
                            (sourceEmployees.length !== 1
                            ? 's'
                            : '');

                        }

                        const employees =
                        showAllEmployees
                        ? sourceEmployees
                        : sourceEmployees.slice(0, 3);

                        employees.forEach(employee => {

                            const profilePicture =
                            photoUrl(employee.profile_picture);

                            const employeeName =
                            employee.full_name &&
                            employee.full_name.trim() !== ''
                            ? employee.full_name
                            : '-';

                            const employeeId =
                            employee.employee_id || '-';

                            const contactNumber =
                            employee.contact_no || '-';

                            container.innerHTML += `

        <div class="col-12 col-md-6 col-xl-4 d-flex">

            <div class="finance-card w-100 d-flex flex-column">

                <div class="finance-card-accent"></div>

                <div class="p-3 d-flex align-items-start gap-3">

                    <img
                        src="${profilePicture}"
                        class="rounded-circle"
                        onerror="this.onerror=null;this.src='${FALLBACK_PHOTO}';"
                        style="width:76px;height:76px;object-fit:cover;border:3px solid #e5e0a4;flex-shrink:0;"
                    >

                    <div class="flex-grow-1" style="min-width:0;">

                        <h6
                            class="mb-0 fw-semibold"
                            style="color:#2e4836;font-size:.95rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                            title="${employeeName}"
                        >
                            ${employeeName}
                        </h6>

                        <div class="small mt-1" style="color:#8a9a8a;">
                            ${employeeId}
                        </div>

                        <div class="mt-1">
                            ${statusBadge(employee.status)}
                        </div>

                    </div>

                    <div class="dropdown">

                        <button
                            class="btn btn-sm finance-menu-btn"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            style="background:none;border:none;color:#8a9a8a;font-size:1.1rem;padding:2px 6px;"
                        >
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end" style="border:1px solid #e5e0a4;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.08);font-size:.85rem;">

                            <li>
                                <a class="dropdown-item editEmployeeBtn" href="javascript:void(0)" data-id="${employee.id}">
                                    <i class="bi bi-pencil me-2" style="color:#295738;"></i>Edit Employee
                                </a>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <li>
                                <a class="dropdown-item archiveEmployeeBtn" href="javascript:void(0)" data-id="${employee.id}" style="color:#b94a48;">
                                    <i class="bi bi-archive me-2"></i>Archive Employee
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
                                <i class="bi bi-person-badge finance-info-icon"></i>
                                <span class="finance-info-label">Role</span>
                            </div>
                            <div class="finance-info-value">${formatRole(employee.role)}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-geo-alt finance-info-icon"></i>
                                <span class="finance-info-label">Assigned Area</span>
                            </div>
                            <div class="finance-info-value">${employee.assigned_area || '-'}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-clock finance-info-icon"></i>
                                <span class="finance-info-label">Shift Status</span>
                            </div>
                            <div class="finance-info-value">${employee.shift_status || '-'}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-shield-lock finance-info-icon"></i>
                                <span class="finance-info-label">Access Level</span>
                            </div>
                            <div class="finance-info-value">${employee.access_level || '-'}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-calendar3 finance-info-icon"></i>
                                <span class="finance-info-label">Shift Schedule</span>
                            </div>
                            <div class="finance-info-value">${employee.shift_schedule || '-'}</div>

                        </div>

                        <div class="col-6">

                            <div class="d-flex align-items-center gap-1 mb-1">
                                <i class="bi bi-telephone finance-info-icon"></i>
                                <span class="finance-info-label">Contact</span>
                            </div>
                            <div class="finance-info-value">${contactNumber}</div>

                        </div>

                    </div>

                </div>

                <div class="p-3 mt-auto d-flex gap-2">

                    <button
                        class="btn btn-sm finance-action-btn btn-view flex-fill editEmployeeBtn"
                        data-id="${employee.id}"
                    >
                        <i class="bi bi-pencil me-1"></i>Edit
                    </button>

                    <button
                        class="btn btn-sm finance-action-btn btn-edit flex-fill archiveEmployeeBtn"
                        data-id="${employee.id}"
                    >
                        <i class="bi bi-archive me-1"></i>Archive
                    </button>

                </div>

            </div>

        </div>

        `;

                        });

                        const toggleBtn =
                        document.getElementById(
                        'toggleEmployees'
                        );

                        if (!toggleBtn) {

                            return;

                        }

                        if (sourceEmployees.length <= 3) {

                            toggleBtn.style.display =
                            'none';

                            return;

                        }

                        toggleBtn.style.display =
                        'flex';

                        toggleBtn.querySelector(
                        'span'
                        ).textContent =
                        showAllEmployees
                        ? 'Hide Employees'
                        : 'View All Employees';

                        document.getElementById(
                        'employeeIcon'
                        ).className =
                        showAllEmployees
                        ? 'bi bi-chevron-up'
                        : 'bi bi-chevron-down';
                    }

                    document.addEventListener(
                    'click',
                    function (e) {

                        if (
                        e.target.closest(
                        '#toggleEmployees'
                        )
                        ) {

                            showAllEmployees =
                            !showAllEmployees;

                            renderEmployees();

                        }

                    }
                    );

                    const editProfilePicture =
                    document.getElementById(
                    'edit_profile_picture'
                    );

                    if (editProfilePicture) {

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
                    async function openEditEmployee(
                    id
                    ) {

                        try {

                            const response =
                            await fetch(
                            '/admin/api/Finance/getFinance.php?action=getEmployee&id='
                            + id
                            );

                            const result =
                            await response.json();

                            if (!result.success) {

                                showAlert('error', 'Error', result.message
                                || 'Employee not found'
                                );

                                return;

                            }

                            const employee =
                            result.data;

                            document.getElementById(
                            'edit_id'
                            ).value =
                            employee.id || '';

                            document.getElementById(
                            'edit_employee_id'
                            ).value =
                            employee.employee_id || '';

                            const empIdHidden = document.getElementById('edit_employee_hidden_id');
                            if (empIdHidden) empIdHidden.value = employee.employee_id || '';

                            document.getElementById(
                            'edit_full_name'
                            ).value =
                            employee.full_name || '';

                            document.getElementById(
                            'edit_email'
                            ).value =
                            employee.email || '';

                            document.getElementById(
                            'edit_contact_no'
                            ).value =
                            employee.contact_no || '';

                            document.getElementById(
                            'edit_username'
                            ).value =
                            employee.username || '';

                            document.getElementById(
                            'edit_role'
                            ).value =
                            employee.role || '';

                            document.getElementById(
                            'edit_status'
                            ).value =
                            employee.status || '';

                            document.getElementById(
                            'edit_assigned_area'
                            ).value =
                            employee.assigned_area || '';

                            document.getElementById(
                            'edit_shift_status'
                            ).value =
                            employee.shift_status || '';

                            document.getElementById(
                            'edit_access_level'
                            ).value =
                            employee.access_level || '';

                            document.getElementById(
                            'edit_shift_schedule'
                            ).value =
                            employee.shift_schedule || '';

                            document.getElementById(
                            'edit_bio'
                            ).value =
                            employee.bio || '';

                            document.getElementById(
                            'edit_password'
                            ).value = '';

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
                                employee.profile_picture
                                );

                            }

                            document.getElementById(
                            'edit_resume_link'
                            ).href =
                            fileUrl(
                            employee.resume_file
                            ) || '#';

                            document.getElementById(
                            'edit_docs_link'
                            ).href =
                            fileUrl(
                            employee.supporting_docs
                            ) || '#';

                            const modal =
                            new bootstrap.Modal(

                            document.getElementById(
                            'editEmployeeModal'
                            )

                            );

                            modal.show();

                        } catch (error) {

                            console.error(error);

                            showAlert('error', 'Failed', error.message
                            );

                        }

                    }

                    document.addEventListener(
                    'click',
                    async function (e) {

                        const btn =
                        e.target.closest(
                        '.editEmployeeBtn'
                        );

                        if (!btn) {

                            return;

                        }

                        openEditEmployee(
                        btn.dataset.id
                        );

                    }
                    );

                    document.addEventListener(
                    'click',
                    async function (e) {

                        const btn =
                        e.target.closest(
                        '#updateEmployeeBtn'
                        );

                        if (!btn) {

                            return;

                        }

                        const form =
                        document.getElementById(
                        'editEmployeeForm'
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

                            '/admin/api/Finance/getFinance.php?action=updateEmployee',

                            {

                                method: 'POST',

                                body: formData

                            }

                            );

                            const raw =
                            await response.text();

                            console.log(
                            'Update Response:',
                            raw
                            );

                            const result =
                            JSON.parse(
                            raw
                            );

            

                            if (
                            result.success
                            ) {

                                const modalEl =
                                document.getElementById(
                                'editEmployeeModal'
                                );

                                const modal =
                                bootstrap.Modal
                                .getInstance(
                                modalEl
                                );

                                function afterHidden() {

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

                                    document.body
                                    .style
                                    .overflow = '';

                                    loadEmployees();

                                    showAlert('success', 'Employee Updated', 'Employee information has been updated successfully.');

                                }

                                if (modal) {

                                    modalEl.addEventListener(
                                    'hidden.bs.modal',
                                    afterHidden,
                                    { once: true }
                                    );

                                    modal.hide();

                                } else {

                                    afterHidden();

                                }

                            } else {

                                showAlert('error', 'Update Failed', result.message || 'Unknown error occurred.');

                            }

                        } catch (error) {

                            console.error(error);

                            await showAlert('error', 'JavaScript Error', error.message);

                        }

                    }
                    );

                    document.addEventListener(
                    'click',
                    async function (e) {

                        const btn =
                        e.target.closest(
                        '.archiveEmployeeBtn'
                        );

                        if (!btn) {

                            return;

                        }

                        const id =
                        btn.dataset.id;

                        showConfirm('Archive Employee?', 'This employee will be moved to archived records.', async function () {

                            try {

                                const response =
                                await fetch(

                                '/admin/api/Finance/getFinance.php?action=archiveEmployee',

                                {

                                    method:'POST',

                                    headers: {
                                        'Content-Type':
                                        'application/x-www-form-urlencoded'
                                    },

                                    body:
                                    'id=' + encodeURIComponent(id)

                                }

                                );

                                const result =
                                await response.json();

                                if (result.success) {

                                    showAlert('success', 'Employee Archived', 'Employee has been archived.');

                                    loadEmployees();

                                } else {

                                    showAlert('error', 'Failed', result.message || 'Unable to archive employee.');

                                }

                            } catch (error) {

                                console.error(error);

                            }

                        });

                    });

                    document.addEventListener(
                    'click',
                    async function (e) {

                        const btn =
                        e.target.closest(
                        '.restoreEmployeeBtn'
                        );

                        if (!btn) {

                            return;

                        }

                        const id =
                        btn.dataset.id;

                        try {

                            const response =
                            await fetch(

                            '/admin/api/Finance/getFinance.php?action=restoreEmployee',

                            {

                                method:'POST',

                                headers: {
                                    'Content-Type':
                                    'application/x-www-form-urlencoded'
                                },

                                body:
                                'id=' + encodeURIComponent(id)

                            }

                            );

                            const result =
                            await response.json();

                            if (result.success) {

                                loadArchivedEmployees();

                                loadEmployees();

                                showAlert('success', 'Employee Restored', 'Employee has been restored.');

                            }

                        } catch (error) {

                            console.error(error);

                        }

                    }
                    );


                    async function loadArchivedEmployees()
                    {
                        const body = document.getElementById('archivedEmployeesBody');
                        body.innerHTML = '<p class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</p>';

                        try {

                            const response =
                            await fetch(
                            '/admin/api/Finance/getFinance.php?action=getArchivedEmployees'
                            );

                            const raw =
                            await response.text();

                            const result =
                            JSON.parse(raw);

                            if (!result.success || !result.data || result.data.length === 0) {

                                body.innerHTML =
                                '<p class="text-center py-5 text-muted">No archived employees found.</p>';
                                return;
                            }

                            let html =
                            '<div class="table-responsive"><table class="table table-striped table-hover text-center mb-0">' +
                            '<thead class="table-dark"><tr>' +
                            '<th>Employee</th><th>Role</th><th>Area</th><th>Status</th><th>Action</th>' +
                            '</tr></thead><tbody>';

                            result.data.forEach(employee => {

                                const pic = photoUrl(employee.profile_picture);
                                html += '<tr>' +
                                '<td><img src="' + pic + '" onerror="this.onerror=null;this.src=\'' + FALLBACK_PHOTO + '\';" style="width:32px;height:32px;border-radius:50%;object-fit:cover;margin-right:6px;"> ' +
                                '<strong>' + (employee.full_name || '-') + '</strong><br><small class="text-muted">' + (employee.employee_id || '') + '</small></td>' +
                                '<td>' + (employee.role || '-') + '</td>' +
                                '<td>' + (employee.assigned_area || '-') + '</td>' +
                                '<td><span class="badge bg-danger">Archived</span></td>' +
                                '<td><button class="btn btn-sm btn-success restoreEmployeeArchBtn" data-id="' + employee.id + '">Restore</button></td>' +
                                '</tr>';
                            });

                            body.innerHTML = html;

                            document.querySelectorAll('.restoreEmployeeArchBtn').forEach(btn => {
                                btn.addEventListener('click', async function () {
                                    const id = this.getAttribute('data-id');
                                    showConfirm('Restore Employee?', 'Set this employee back to Active?', async function () {
                                        try {
                                            const fd = new FormData();
                                            fd.append('id', id);
                                            const res = await fetch('/admin/api/Finance/getFinance.php?action=restoreEmployee', { method: 'POST', body: fd });
                                            const data = await res.json();
                                            if (data.success) {
                                                showAlert('success', 'Restored', 'Employee restored successfully.');
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

                            console.error(error);

                            body.innerHTML =
                            '<div style="text-align:center;padding:40px;color:#721c24;">Failed to load data.</div>';

                        }
                    }

                </script>

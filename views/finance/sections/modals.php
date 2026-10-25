<script src="/admin/assets/js/admin-user-validation.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/assets/js/admin-user-validation.js') ?: time() ?>"></script>
<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
/>

<div class="modal fade"
     id="addEmployeeModal"
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

        Add Finance Employee

                            </h4>

                            <div style="
        font-size:.84rem;
        color:#7d867c;
    ">

        Create employee accounts and manage finance department personnel records

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
    id="employeeForm"
    data-uv-context="finance"
    enctype="multipart/form-data"
>

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
    id="full_name"
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
    id="employee_id"
    name="employee_id"
>

                                                    <input
    type="text"
    id="employee_id_display"
    class="form-control"
    placeholder="FIN-0001"
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
    id="role"
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
    id="status"
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
    id="assigned_area"
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
    id="shift_status"
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
    id="access_level"
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
    id="shift_schedule"
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
    id="contact_no"
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
    id="email"
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
    id="address"
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
    id="username"
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

Password

                                                        </label>

                                                        <input
    type="password"
    id="password"
    name="password"
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


                                                <div class="col-md-4">

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

                                                                <i class="fas fa-image"
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
Profile Picture
                                                                </div>

                                                                <div style="
    font-size:.74rem;
    color:#7c847c;
">
Employee photo
                                                                </div>

                                                            </div>

                                                        </div>

                                                        <input
    type="file"
    id="profile_picture"
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
    id="resume_file"
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


                                                <div class="col-md-4">

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
Supporting Documents
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
    id="supporting_docs"
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

Employee Notes

                                                    </label>

<textarea
    id="bio"
    name="bio"
    class="form-control"
    rows="4"
    placeholder="Enter certifications, qualifications, responsibilities, remarks, or additional information..."
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

                                </form>

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
    id="saveEmployeeBtn"
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

                                    <i class="fas fa-user-plus me-2"></i>

    Add Employee

                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</div>

<script>
    function showAlert(type, title, message, onConfirm) { var overlay = document.getElementById('customAlertOverlay'); if (!overlay)return; var header = overlay.querySelector('.custom-alert-header'); var iconEl = overlay.querySelector('.custom-alert-icon'); var titleEl = overlay.querySelector('.custom-alert-title'); var msgEl = overlay.querySelector('.custom-alert-message'); var btn = overlay.querySelector('.custom-alert-btn'); var btnCancel = overlay.querySelector('.custom-alert-btn-cancel'); var actions = overlay.querySelector('.custom-alert-actions'); header.className = 'custom-alert-header '+type; if (type === 'success') { iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#2e4836" stroke-width="3"/><path d="M16 24l6 6 10-10" stroke="#2e4836" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>'; if (btnCancel)btnCancel.style.display = 'none'; if (actions)actions.style.display = ''; btn.className = 'custom-alert-btn success'; btn.textContent = 'Continue' } else { iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#fff" stroke-width="3"/><path d="M18 18l12 12M30 18l-12 12" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>'; if (btnCancel)btnCancel.style.display = 'none'; if (actions)actions.style.display = ''; btn.className = 'custom-alert-btn error'; btn.textContent = 'Close' } titleEl.textContent = title; msgEl.textContent = message; btn.onclick = function () { overlay.classList.remove('active'); if (typeof onConfirm === 'function')onConfirm() }; overlay.classList.add('active') }
    function showConfirm(title, message, onConfirm, onCancel) { var overlay = document.getElementById('customAlertOverlay'); if (!overlay)return; var header = overlay.querySelector('.custom-alert-header'); var iconEl = overlay.querySelector('.custom-alert-icon'); var titleEl = overlay.querySelector('.custom-alert-title'); var msgEl = overlay.querySelector('.custom-alert-message'); var btn = overlay.querySelector('.custom-alert-btn'); var btnCancel = overlay.querySelector('.custom-alert-btn-cancel'); var actions = overlay.querySelector('.custom-alert-actions'); header.className = 'custom-alert-header warning'; iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#92400e" stroke-width="3"/><path d="M24 16v10M24 30v2" stroke="#92400e" stroke-width="3" stroke-linecap="round"/></svg>'; if (actions)actions.style.display = 'flex'; if (btnCancel)btnCancel.style.display = 'block'; titleEl.textContent = title; msgEl.textContent = message; btn.className = 'custom-alert-btn confirm'; btn.textContent = 'Confirm'; btn.onclick = function () { overlay.classList.remove('active'); if (typeof onConfirm === 'function')onConfirm() }; if (btnCancel) { btnCancel.className = 'custom-alert-btn cancel'; btnCancel.textContent = 'Cancel'; btnCancel.onclick = function () { overlay.classList.remove('active'); if (typeof onCancel === 'function')onCancel() } } overlay.classList.add('active') }
    const saveEmployeeBtn =
    document.getElementById(
    'saveEmployeeBtn'
    );

    if (saveEmployeeBtn) {

        saveEmployeeBtn.addEventListener(

        'click',

        async function () {

            const form =
            document.getElementById(
            'employeeForm'
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

            // One submission at a time: another click while the request is
            // still in flight would submit the same account twice.
            if (saveEmployeeBtn.disabled) {

                return;

            }

            const originalLabel =
            saveEmployeeBtn.innerHTML;

            saveEmployeeBtn.disabled = true;

            saveEmployeeBtn.innerHTML = 'Saving...';

            const formData =
            new FormData(
            form
            );

            try {

                const response =
                await fetch(

                '/admin/api/Finance/AddEmployee.php',

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

                    showAlert('error', 'Invalid Server Response', 'Server returned an unexpected response.');

                    return;

                }

                if (
                result.success
                ) {

                    form.reset();

                    const modalEl =
                    document.getElementById(
                    'addEmployeeModal'
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

                        document.body
                        .style
                        .paddingRight = '';

                        if (
                        typeof loadEmployees
                        === 'function'
                        ) {

                            loadEmployees();

                        }

                        if (
                        typeof loadFinanceStats
                        === 'function'
                        ) {

                            loadFinanceStats();

                        }

                        loadNextEmployeeId();

                        if (
                        result.email_sent === false
                        ) {

                            showAlert('success', 'Employee Added', 'Employee account was created. Employee ID: ' + (result.employee_id || 'N/A') + '. Email could not be sent.');

                        } else {

                            showAlert('success', 'Employee Added Successfully', 'Employee account has been created. Employee ID: ' + (result.employee_id || 'N/A') + '. Credentials email sent successfully.');

                        }

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

                    showAlert('error', 'Save Failed', 'Error: ' + (result.message || 'Unknown error occurred.'));

                }

            } catch (error) {

                showAlert('error', 'JavaScript Error', error.message);

            } finally {

                saveEmployeeBtn.disabled = false;

                saveEmployeeBtn.innerHTML = originalLabel;

            }

        }

        );

    }

    async function loadNextEmployeeId()
    {
        try {

            const response =
            await fetch(
            '/admin/api/Finance/GetNextEmployeeId.php'
            );

            const result =
            await response.json();

            if (
            result.success
            ) {

                const displayInput =
                document.getElementById(
                'employee_id_display'
                );

                const hiddenInput =
                document.getElementById(
                'employee_id'
                );

                if (displayInput) {

                    displayInput.value =
                    result.employee_id;

                }

                if (hiddenInput) {

                    hiddenInput.value =
                    result.employee_id;

                }

            }

        } catch (error) {

            console.error(error);

        }
    }

    const employeeModal =
    document.getElementById(
    'addEmployeeModal'
    );

    if (employeeModal) {

        employeeModal.addEventListener(

        'show.bs.modal',

        () => {

            loadNextEmployeeId();

        }

        );

    }

</script>
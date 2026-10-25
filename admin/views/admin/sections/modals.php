<script src="/admin/assets/js/admin-user-validation.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/assets/js/admin-user-validation.js') ?: time() ?>"></script>
<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
/>

<div class="modal fade"
     id="addAdminModal"
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

                                <i class="fa-solid fa-user-shield"
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

        Administrator Management

                            </h2>

                            <p style="
        margin-top:18px;
        font-size:.95rem;
        line-height:1.9;
        color:#6d766d;
    ">

        Manage administrator accounts, monitor system
        operations, oversee driver and inspector records,
        configure route and fleet management access, and
        maintain transportation services for mini bus
        operations across the Cabanatuan and Rizal routes.

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
        Administrator Records
                                        </div>

                                        <div style="
        font-size:.76rem;
        color:#7c847c;
    ">
        Administrator profiles, access permissions, and system credentials
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
        Administrative Operations
                                        </div>

                                        <div style="
        font-size:.76rem;
        color:#7c847c;
    ">
        System administration, personnel oversight, and access management
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
        Administrator Documents
                                        </div>

                                        <div style="
        font-size:.76rem;
        color:#7c847c;
    ">
        Administrator profiles, credentials, and supporting records
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

            Add Administrator

                            </h4>

                            <div style="
            font-size:.84rem;
            color:#7d867c;
        ">

            Create administrator accounts, assign system access, and manage transportation operations personnel.

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
    id="adminForm"
    data-uv-context="admin"
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

Administrator ID

                                                    </label>

                                                    <input
    type="hidden"
    id="admin_id"
    name="admin_id"
>

                                                    <input
    type="text"
    id="admin_id_display"
    class="form-control"
    placeholder="ADM-0001"
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
    id="assigned_department"
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

System Access Level

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
    id="assigned_responsibility"
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

Residential Address

                                                    </label>

<textarea
    id="address"
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

Administrator ID

                                                        </label>

                                                        <input
    type="text"
    id="admin_id_display"
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
    placeholder="Enter secure password"
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

Administrator Documents & Notes

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
Administrator photo
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

Administrator Notes

                                                    </label>

<textarea
    id="bio"
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

                                                    <i class="fas fa-user-shield"
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

Administrator Registration

                                                    </div>

                                                    <div style="
    margin-top:4px;
    font-size:.78rem;
    line-height:1.8;
    color:#737b73;
">

The system will automatically create
administrator accounts, generate Administrator
IDs, assign system permissions, store uploaded
documents, and manage transportation
operations across the Cabanatuan and Rizal
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
    id="saveAdminBtn"
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

                                                <i class="fas fa-user-shield me-2"></i>

    Add Administrator

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



<script>

    const saveAdminBtn =
    document.getElementById(
    'saveAdminBtn'
    );

    if (saveAdminBtn) {

        saveAdminBtn.addEventListener(

        'click',

        async function () {

            const form =
            document.getElementById(
            'adminForm'
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

                '/admin/api/Admin/AddAdmin.php',

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

                    const modalEl =
                    document.getElementById(
                    'addAdminModal'
                    );

                    const modal =
                    bootstrap.Modal
                    .getInstance(
                    modalEl
                    );

                    if (modal) {

                        modal.hide();

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

                        document.body
                        .style
                        .removeProperty(
                        'padding-right'
                        );

                        document.body
                        .style
                        .removeProperty(
                        'overflow'
                        );

                    }, 300);

                    form.reset();

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

                    loadNextAdminId();

                    if (
                    result.email_sent === false
                    ) {

                        showAlert('success', 'Admin Added', 'Admin account was created. Admin ID: ' + (result.admin_id || 'N/A') + '. Email could not be sent.');

                    } else {

                        showAlert('success', 'Admin Added Successfully', 'Admin account created successfully.');

                    }

                } else {

                    showAlert('error', 'Save Failed', 'Save failed.');

                }

            } catch (error) {

                showAlert('error', 'JavaScript Error', error.message);

            }

        }

        );

    }

    async function loadNextAdminId()
    {
        try {

            const response =
            await fetch(
            '/admin/api/Admin/GetNextAdminId.php'
            );

            const result =
            await response.json();

            if (
            result.success
            ) {

                const displayInput =
                document.getElementById(
                'admin_id_display'
                );

                const hiddenInput =
                document.getElementById(
                'admin_id'
                );

                if (displayInput) {

                    displayInput.value =
                    result.admin_id;

                }

                if (hiddenInput) {

                    hiddenInput.value =
                    result.admin_id;

                }

            }

        } catch (error) {

            console.error(error);

        }
    }

    const adminModal =
    document.getElementById(
    'addAdminModal'
    );

    if (adminModal) {

        adminModal.addEventListener(

        'show.bs.modal',

        () => {

            loadNextAdminId();

        }

        );

    }

</script>
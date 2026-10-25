<script src="/admin/assets/js/admin-user-validation.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/assets/js/admin-user-validation.js') ?: time() ?>"></script>
<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
/>

<div class="modal fade"
     id="addInspectorModal"
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

                                <i class="fa-solid fa-id-card"
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

Inspector Management

                            </h2>

                            <p style="
    margin-top:18px;
    font-size:.95rem;
    line-height:1.9;
    color:#6d766d;
">

Register inspectors, assign terminals,
manage credentials, upload documents,
and maintain operational personnel records.

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
Inspector Records
                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7c847c;
">
Personnel profiles and credentials
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
Terminal Assignment
                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7c847c;
">
Assign inspectors to terminals
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
Resume and supporting files
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

Add New Inspector

                            </h4>

                            <div style="
    font-size:.84rem;
    color:#7d867c;
">

Register inspection personnel

                            </div>

                        </div>

                        <button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal">
                        </button>

                    </div>

                    <div class="modal-body px-5 py-4">

                        <form
    id="inspectorForm"
    data-uv-context="inspector"
    enctype="multipart/form-data"
>

                            <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:22px;
    padding:30px;
">
                                <div class="modal-body">

                                    <form
                    id="inspectorForm"
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

Inspector Identity

                                                </div>

                                                <div class="row g-3">

                                                    <div class="col-md-6">

                                                        <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Inspector Name

                                                        </label>

                                                        <input
    type="text"
    id="inspector_name"
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

                                                        <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Inspector Identity

                                                        </label>

                                                        <input
    type="text"
    id="inspector_identity"
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

                                                        <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Department

                                                        </label>

                                                        <input
    type="text"
    id="department"
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

                                                        <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Experience (Years)

                                                        </label>

                                                        <input
    type="number"
    id="experience_years"
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

Operational Assignment

                                                </div>

                                                <div class="row g-3">

                                                    <div class="col-md-6">

                                                        <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Operational Assignment

                                                        </label>

                                                        <input
    type="text"
    id="operational_assignment"
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

                                                        <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

Assigned Terminal

                                                        </label>

                                                        <select
    id="assigned_routes"
    name="assigned_routes"
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
    Select Terminal
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
    id="contact_number"
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

                                                        <div class="col-md-4">

                                                            <label style="
    font-size:.82rem;
    font-weight:600;
    color:#556055;
    margin-bottom:8px;
">

System Inspector ID

                                                            </label>

                                                            <input
    type="text"
    id="generated_inspector_id"
    name="inspector_id"
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

                                                        <div class="col-md-4">

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
Inspector photo
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

Additional Notes

                                                        </label>

<textarea
    id="additional_notes"
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

                                            <i class="fas fa-user-check"
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
    id="saveInspectorBtn"
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

    Add Inspector

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

    const saveInspectorBtn =
    document.getElementById(
    'saveInspectorBtn'
    );

    saveInspectorBtn
    .addEventListener(
    'click',
    async function () {

        const form =
        document.getElementById(
        'inspectorForm'
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
        if (saveInspectorBtn.disabled) {

            return;

        }

        const originalLabel =
        saveInspectorBtn.innerHTML;

        saveInspectorBtn.disabled = true;

        saveInspectorBtn.innerHTML = 'Saving...';

        const formData =
        new FormData(
        form
        );

        try {

            const response =
            await fetch(

            '/admin/api/Inspectors/AddInspector.php',

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
                'addInspectorModal'
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
                typeof loadInspectorStats
                === 'function'
                ) {

                    loadInspectorStats();

                }

                loadNextInspectorId();

                if (
                result.email_sent === false
                ) {

                    showAlert('success', 'Inspector Added', 'Inspector account was created. Inspector ID: ' + (result.inspector_id || 'N/A') + '. Email could not be sent.');

                } else {

                    showAlert('success', 'Inspector Added Successfully', 'Inspector account has been created. Inspector ID: ' + (result.inspector_id || 'N/A') + '. Credentials email sent successfully.');

                }

            } else {

                showAlert('error', 'Save Failed', 'Error: ' + (result.message || 'Unknown error occurred.'));

            }

        } catch (error) {

            showAlert('error', 'Error', error.message || 'Something went wrong.');

        } finally {

            saveInspectorBtn.disabled = false;

            saveInspectorBtn.innerHTML = originalLabel;

        }

    }
    );

    async function loadTerminals() {

        try {

            const response =
            await fetch(
            '/admin/api/Terminals/GetTerminals.php'
            );

            const terminals =
            await response.json();

            const select =
            document.getElementById(
            'assigned_routes'
            );

            if (!select) {

                return;

            }

            select.innerHTML =
            `
            <option value="">
                Select Terminal
            </option>
            `;

            terminals.forEach(
            terminal => {

                select.innerHTML +=
                `
                <option value="${terminal.id}">
                    ${terminal.terminal_name}
                    (${terminal.terminal_code})
                </option>
                `;

            }
            );

        } catch (error) {

            showAlert('error', 'Terminal Load Failed', 'Unable to load terminals at this time.');

        }

    }

    async function loadNextInspectorId() {

        try {
            const role = document.getElementById('role')?.value || 'inspector';

            const response =
            await fetch(
            '/admin/api/Inspectors/GetNextInspectorId.php?role=' + role
            );

            const result =
            await response.json();

            if (
            result.success
            ) {

                document.getElementById(
                'generated_inspector_id'
                ).value =
                result.inspector_id;

            }

        } catch (error) {

            showAlert('error', 'Unable to Load Inspector ID', 'Please refresh the page and try again.');

        }

    }
    const addModalEl = document.getElementById('addInspectorModal');
    if (addModalEl) {
        addModalEl.addEventListener('show.bs.modal', () => {
            loadNextInspectorId();
            loadTerminals();
        });
    }

    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        roleSelect.addEventListener('change', loadNextInspectorId);
    }
</script>
<?php require_once ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/admin/services/Driver/DriverLicenseExpiry.php'; ?>
<script src="/admin/assets/js/admin-user-validation.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/assets/js/admin-user-validation.js') ?: time() ?>"></script>
<style>
    @media(max-width:768px) {
        .modal-dialog.modal-xl { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; }
        .modal-content .container { padding: 12px !important; }
        .modal-content .row > div { width: 100% !important; }
        .modal-content h4, .modal-content h5 { font-size: 1rem !important; }
        .modal-content input, .modal-content select, .modal-content textarea { font-size: 0.8rem !important; padding: 6px 10px !important; }
        .modal-content label { font-size: 0.75rem !important; }
        .modal-content .btn { font-size: 0.75rem !important; padding: 6px 12px !important; }
        .modal-content .table { font-size: 0.7rem !important; }
        .modal-content .table th, .modal-content .table td { padding: 4px 2px !important; }
    }
</style>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
/>

<div class="modal fade"
     id="addDriverModal"
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


                <div class="col-md-4 d-none d-md-block"
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

Driver Registration Center

                            </h2>

                            <p style="
    margin-top:18px;
    font-size:.94rem;
    line-height:1.9;
    color:#70796f;
">

Register transportation operators,
generate driver accounts, upload
required documents and assign buses
through a centralized management system.

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

                                        <i class="fa-solid fa-id-badge"
   style="
      color:#c4b417;
      font-size:1rem;
   ">
                                        </i>

                                    </div>

                                    <div>

                                        <div style="
    font-size:.9rem;
    font-weight:700;
    color:#2e4836;
">

Automatic Credentials

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;
">

Driver IDs and usernames are generated automatically

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

                                        <i class="fa-solid fa-bus"
   style="
      color:#c4b417;
      font-size:1rem;
   ">
                                        </i>

                                    </div>

                                    <div>

                                        <div style="
    font-size:.9rem;
    font-weight:700;
    color:#2e4836;
">

Bus Assignment

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;
">

Assign operators directly to available buses

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

                                        <i class="fa-solid fa-file-shield"
   style="
      color:#c4b417;
      font-size:1rem;
   ">
                                        </i>

                                    </div>

                                    <div>

                                        <div style="
    font-size:.9rem;
    font-weight:700;
    color:#2e4836;
">

Compliance Ready

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;
">

Store licenses, IDs and supporting documents

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

                                        <i class="fa-solid fa-user-check"
   style="
      color:#c4b417;
      font-size:1rem;
   ">
                                        </i>

                                    </div>

                                    <div>

                                        <div style="
    font-size:.9rem;
    font-weight:700;
    color:#2e4836;
">

Fast Onboarding

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;
">

Complete registration in a single workflow

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-md-8 col-12">


                    <div class="modal-header border-0 px-5 pt-5 pb-0">

                        <div>

                            <h4 style="
    margin:0;
    color:#2e4836;
    font-weight:700;
">

Add New Driver

                            </h4>

                            <div style="
    font-size:.84rem;
    color:#7b847b;
    margin-top:4px;
">

Driver onboarding and account registration

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
    id="addDriverForm"
    data-uv-context="driver"
    enctype="multipart/form-data"
>

                            <div class="d-grid gap-4">


                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:22px;
    padding:24px;
">

                                    <h6 style="
    color:#2e4836;
    font-weight:700;
    margin-bottom:20px;
">

Driver Information

                                    </h6>

                                    <div class="row g-3">

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    Full Name
                                            </label>

                                            <input
    type="text"
    name="full_name"
    class="form-control"
    placeholder="Enter driver's full name"
    required
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    Email Address
                                            </label>

                                            <input
    type="email"
    name="email"
    class="form-control"
    placeholder="driver@email.com"
    required
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    Contact Number
                                            </label>

                                            <input
    type="text"
    name="contact_number"
    class="form-control"
    placeholder="09XXXXXXXXX"
    required
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    Password
                                            </label>

                                            <input
    type="password"
    name="password"
    class="form-control"
    placeholder="Create password"
    required
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

                                        </div>

                                    </div>

                                </div>


                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:22px;
    padding:24px;
">

                                    <div class="d-flex justify-content-between align-items-center mb-3">

                                        <h6 style="
    color:#2e4836;
    font-weight:700;
    margin:0;
">

License & Compliance

                                        </h6>

                                        <span style="
    font-size:.75rem;
    color:#8a9389;
">

Required for onboarding

                                        </span>

                                    </div>

                                    <div class="row g-3">

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    License Number
                                            </label>

                                            <input
    type="text"
    name="license_number"
    class="form-control"
    placeholder="N01-12-345678"
    required
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    Expiration Date
                                            </label>

                                            <input
    type="date"
    name="license_expiry"
    class="form-control"
    required
    min="<?= htmlspecialchars(driverLicenseExpiryMinimumDate(), ENT_QUOTES, 'UTF-8') ?>"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    License Type
                                            </label>

                                            <select
    name="license_type"
    class="form-select"
    required
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

                                                <option value="">
    Select License Type
                                                </option>

                                                <option value="Professional">
    Professional
                                                </option>

                                                <option value="Non-Professional">
    Non-Professional
                                                </option>

                                            </select>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    Driving Experience
                                            </label>

                                            <div class="input-group">

                                                <input
    type="number"
    name="experience_years"
    class="form-control"
    min="0"
    placeholder="0"
    required
    style="
        height:54px;
        border-radius:16px 0 0 16px;
        border:1px solid #dce3ce;
    "
>

                                                <span class="input-group-text"
      style="
        border-radius:0 16px 16px 0;
        background:#f9f4da;
        border:1px solid #dce3ce;
      ">
    Years
                                                </span>

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:22px;
    padding:24px;
">

                                    <div class="d-flex justify-content-between align-items-center mb-3">

                                        <h6 style="
    color:#2e4836;
    font-weight:700;
    margin:0;
">

Operational Assignment

                                        </h6>

                                        <span style="
    font-size:.75rem;
    color:#8a9389;
">

Optional assignment

                                        </span>

                                    </div>

                                    <div class="row g-3">

                                        <div class="col-12">

                                            <label class="form-label fw-semibold">
    Assigned Bus
                                            </label>

                                            <select
    id="busSelect"
    name="bus_id"
    class="form-select"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

                                                <option value="">
    Select Bus
                                                </option>

                                            </select>

                                        </div>

                                    </div>

                                </div>


                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:22px;
    padding:24px;
">

                                    <div class="d-flex justify-content-between align-items-center mb-3">

                                        <h6 style="
    color:#2e4836;
    font-weight:700;
    margin:0;
">

Documents

                                        </h6>

                                        <span style="
    font-size:.75rem;
    color:#8a9389;
">

Upload supporting files

                                        </span>

                                    </div>

                                    <div class="row g-4">

                                        <div class="col-md-4">

                                            <label class="form-label fw-semibold">
    Profile Picture
                                            </label>

                                            <input
    type="file"
    name="photo"
    class="form-control"
    accept="image/*"
    style="
        border-radius:16px;
        border:1px solid #dce3ce;
        padding:12px;
    "
>

                                        </div>

                                        <div class="col-md-4">

                                            <label class="form-label fw-semibold">
    Resume
                                            </label>

                                            <input
    type="file"
    name="resume_file"
    class="form-control"
    style="
        border-radius:16px;
        border:1px solid #dce3ce;
        padding:12px;
    "
>

                                        </div>

                                        <div class="col-md-4">

                                            <label class="form-label fw-semibold">
    Supporting Documents
                                            </label>

                                            <input
    type="file"
    name="supporting_docs"
    class="form-control"
    style="
        border-radius:16px;
        border:1px solid #dce3ce;
        padding:12px;
    "
>

                                        </div>

                                    </div>

                                    <div class="mt-4 p-3"
     style="
        background:#faf9e5;
        border:1px solid #ebe5ca;
        border-radius:16px;
     ">

                                        <div style="
    font-size:.82rem;
    color:#70796f;
">

                                            <i class="fa-solid fa-circle-info me-2"></i>

After registration, the system will automatically:

                                            <ul class="mb-0 mt-2">
                                                <li>Create the driver account</li>
                                                <li>Generate Driver ID</li>
                                                <li>Generate Username</li>
                                                <li>Set Approval Status to Approved</li>
                                                <li>Create Bus Assignment (if selected)</li>
                                            </ul>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </form>

                    </div>


                    <div class="modal-footer border-0 px-5 pb-5 pt-0">

                        <button
    type="button"
    class="btn"
    data-bs-dismiss="modal"
    style="
        height:50px;
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
    type="submit"
    form="addDriverForm"
    class="btn"
    style="
        height:52px;
        padding:0 28px;
        border:none;
        border-radius:16px;
        background:#c4b417;
        color:#ffffff;
        font-weight:700;
    "
>

                            <i class="fa-solid fa-user-plus me-2"></i>

Register Driver

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>
</div>
<script>

    document
    .getElementById(
    'addDriverForm'
    )
    .addEventListener(
    'submit',
    async function (e) {

        e.preventDefault();

        const expiryInput =
        this.querySelector(
        '[name="license_expiry"]'
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
        ? AdminUserValidation.checkForm(this)
        : null;

        if (uvMessage) {

            showAlert(
            'warning',
            'Invalid Input',
            uvMessage
            );

            return;
        }

        const submitBtn =
        document.querySelector(
        '[form="addDriverForm"]'
        );

        submitBtn.disabled = true;

        submitBtn.innerHTML =
        'Saving...';

        try {

            const formData =
            new FormData(
            this
            );

            const response =
            await fetch(

            '/admin/api/Driver/driver_accounts.php?action=add_driver',

            {
                method:'POST',
                body:formData
            }

            );

            if (
            !response.ok
            ) {

                const errText =
                await response.text();

                let errMsg;

                try {

                    const errJson =
                    JSON.parse(
                    errText
                    );

                    errMsg =
                    errJson.message
                    || errText;

                } catch (e) {

                    errMsg = errText;
                }

                throw new Error(
                errMsg
                );
            }

            const raw =
            await response.text();

            console.log(
            'RAW RESPONSE:',
            raw
            );

            let result;

            try {

                result =
                JSON.parse(
                raw
                );

            } catch (parseError) {

                console.error(
                'JSON PARSE ERROR:',
                parseError
                );

                showAlert('error', 'Invalid JSON Response', 'Server returned unexpected response.');

                return;

            }

            console.log(
            'RESULT:',
            result
            );

            if (
            result.success
            ) {

                const driverName =
                this.querySelector(
                '[name="full_name"]'
                ).value;

                if (
                result.email_sent === false
                ) {

                    showAlert('success', 'Driver Added', 'Driver account has been created for ' + driverName + '. Email could not be sent.');

                } else {

                    showAlert('success', 'Driver Added Successfully', 'Driver account has been created for ' + driverName + '. Credentials email sent successfully.');

                }

                this.reset();

                const modalEl =
                document.getElementById(
                'addDriverModal'
                );

                const modal =
                bootstrap.Modal
                .getInstance(
                modalEl
                );

                if (modal) {
                    modal.hide();
                }

                if (
                typeof loadDrivers
                === 'function'
                ) {
                    loadDrivers();
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

            } else {

                showAlert('error', 'Failed', result.message || 'Failed to add driver');

            }

        } catch (error) {

            console.error(
            'FETCH ERROR:',
            error
            );

            showAlert('error', 'Javascript Error', error.message || 'An error occurred.');

        }

        submitBtn.disabled = false;

        submitBtn.innerHTML =
        'Register Driver';

    }
    );

    async function loadBuses() {

        try {

            const response =
            await fetch(
            '/admin/api/Bus/buses.php?action=get_all_buses'
            );

            const raw =
            await response.text();

            console.log(
            'BUS RAW:',
            raw
            );

            let result;

            try {

                result =
                JSON.parse(
                raw
                );

            } catch (error) {

                console.error(
                'BUS JSON ERROR:',
                error
                );

                showAlert('error', 'Bus API Error', 'Failed to load bus data.');

                return;

            }

            const select =
            document.getElementById(
            'busSelect'
            );

            if (
            !select
            ) {
                return;
            }

            if (
            !result.success
            ) {

                showAlert('error', 'Bus Loading Failed', result.message || 'Unable to load buses.');

                return;

            }

            const buses =
            result.data ||
            result.buses ||
            [];

            select.innerHTML =
            `
            <option value="">
                Select Bus
            </option>
            `;

            buses.forEach(
            bus => {

                select.innerHTML +=
                `
                <option value="${bus.bus_id}">
                    ${bus.bus_id}
                    (${bus.plate_number})
                </option>
                `;

            }
            );

        } catch (error) {

            console.error(
            'LOAD BUS ERROR:',
            error
            );

            showAlert('error', 'Load Bus Error', error.message);

        }

    }

    document.addEventListener(
    'DOMContentLoaded',
    loadBuses
    );

</script>
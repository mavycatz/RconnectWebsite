<style>
    @media(max-width:768px) {
        .modal-dialog.modal-xl { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; }
        .modal-content .container { padding: 12px !important; }
        .modal-content .row > div { width: 100% !important; }
        .modal-content h4, .modal-content h5 { font-size: 1rem !important; }
        .modal-content input, .modal-content select, .modal-content textarea { font-size: 0.8rem !important; padding: 6px 10px !important; }
        .modal-content label { font-size: 0.75rem !important; }
        .modal-content .btn { font-size: 0.75rem !important; padding: 6px 12px !important; }
    }
</style>



<div class="modal fade"
     id="editBusModal"
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

                                <i class="fa-solid fa-pen-to-square"
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

Edit Fleet Record

                            </h2>

                            <p style="
    margin-top:18px;
    font-size:.94rem;
    line-height:1.9;
    color:#70796f;
">

Update bus information, modify assigned
routes, change driver assignments, and
manage fleet configuration in real time.
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

                                        <i class="fa-solid fa-pen"
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

Edit Details

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;
">

Modify bus ID, plate number and type

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

                                        <i class="fa-solid fa-arrows-rotate"
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

Reassign Routes

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;

">

Update terminal assignments and schedules

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

                                        <i class="fa-solid fa-user-pen"
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

Driver Changes

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;
">

Reassign or update driver information

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

                                        <i class="fa-solid fa-toggle-on"
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

Status Control

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;
">

Change bus status and availability

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

Edit Bus

                            </h4>

                            <div style="
    font-size:.84rem;
    color:#7b847b;
    margin-top:4px;
">

Update bus information and configuration

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
    id="editBusForm"
>

                            <input type="hidden" name="bus_id_hidden" id="edit_bus_id_hidden">

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

Bus Information

                                    </h6>

                                    <div class="row g-3">

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    Bus ID
                                            </label>

                                            <input
    type="text"
    name="bus_id"
    id="edit_bus_id"
    class="form-control"
    placeholder="e.g. BUS-001"
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
    Plate Number
                                            </label>

                                            <input
    type="text"
    name="plate_number"
    id="edit_plate_number"
    class="form-control"
    placeholder="e.g. ABC-1234"
    required
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

                                        </div>

                                        <div class="col-md-4">

                                            <label class="form-label fw-semibold">
    Bus Type
                                            </label>

                                            <select
    name="bus_type"
    id="edit_bus_type"
    class="form-select"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>
                                                <option value="Coach">Coach</option>
                                                <option value="City Bus">City Bus</option>
                                                <option value="Mini Bus">Mini Bus</option>
                                                <option value="Shuttle">Shuttle</option>
                                            </select>

                                        </div>

                                        <div class="col-md-4">

                                            <label class="form-label fw-semibold">
    Seats
                                            </label>

                                            <input
    type="number"
    name="seats"
    id="edit_seats"
    class="form-control"
    placeholder="e.g. 45"
    min="1"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

                                        </div>

                                        <div class="col-md-4">

                                            <label class="form-label fw-semibold">
    Standing Capacity
                                            </label>

                                            <input
    type="number"
    name="standing_capacity"
    id="edit_standing_capacity"
    class="form-control"
    placeholder="e.g. 10"
    min="0"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    Accessible Features
                                            </label>

                                            <select
    name="accessible_features"
    id="edit_accessible_features"
    class="form-select"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>
                                                <option value="None">None</option>
                                                <option value="Priority Seats">Priority Seats</option>
                                                <option value="PWD Accessible">PWD Accessible</option>
                                                <option value="Wheelchair Ramp">Wheelchair Ramp</option>
                                            </select>

                                        </div>

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    Status
                                            </label>

                                            <select
    name="status"
    id="edit_status"
    class="form-select"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>
                                                <option value="Active">Active</option>
                                                <option value="Inactive">Inactive</option>
                                                <option value="Pending QR">Pending QR</option>
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

                                    <h6 style="
    color:#2e4836;
    font-weight:700;
    margin-bottom:20px;
">

Route & Assignment

                                    </h6>

                                    <div class="row g-3">

                                        <div class="col-md-6">

                                            <label class="form-label fw-semibold">
    From Terminal
                                            </label>

                                            <select
    name="from_terminal_id"
    id="edit_from_terminal_id"
    class="form-select"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>
                                                <option value="">Select Origin</option>
    <?php if (isset($terminals)): ?>
    <?php foreach ($terminals as $t): ?>
    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['terminal_name']) ?></option>
    <?php endforeach; ?>
    <?php endif; ?>
</select>

</div>

<div class="col-md-6">

    <label class="form-label fw-semibold">
    To Terminal
    </label>

    <select
    name="to_terminal_id"
    id="edit_to_terminal_id"
    class="form-select"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>
        <option value="">Select Destination</option>
    <?php if (isset($terminals)): ?>
    <?php foreach ($terminals as $t): ?>
    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['terminal_name']) ?></option>
    <?php endforeach; ?>
    <?php endif; ?>
</select>

</div>

<div class="col-md-6">

    <label class="form-label fw-semibold">
    Schedule
    </label>

    <input
    type="text"
    name="schedule"
    id="edit_schedule"
    class="form-control"
    placeholder="e.g. 06:00 AM – 06:00 PM"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>

</div>

<div class="col-md-6">

    <label class="form-label fw-semibold">
    Assign Driver
    </label>

    <select
    name="driver"
    id="edit_driver"
    class="form-select"
    style="
        height:54px;
        border-radius:16px;
        border:1px solid #dce3ce;
    "
>
        <option value="">— Select Available Driver —</option>
    <?php if (isset($availableDrivers)): ?>
    <?php foreach ($availableDrivers as $driver): ?>
    <option value="<?= $driver['driver_id'] ?>"><?= htmlspecialchars($driver['full_name']) ?> (<?= $driver['driver_id'] ?>)</option>
    <?php endforeach; ?>
    <?php endif; ?>
</select>

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
    form="editBusForm"
    class="btn"
    style="
        height:52px;
        padding:0 28px;
        border:none;
        border-radius:16px;
        background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);
        color:#ffffff;
        font-weight:700;
    "
>

        <i class="fa-solid fa-pen-to-square me-2"></i>

Update Bus

    </button>

</div>

</div>

</div>

</div>

</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>

    document
    .getElementById(
    'editBusModal'
    )
    .addEventListener(
    'show.bs.modal',
    async function (event) {

        const btn = event.relatedTarget;
        const busId = btn.getAttribute('data-bus-id');
        if (!busId) return;

        document.getElementById('edit_bus_id_hidden').value = busId;

        try {
            const res = await fetch('/admin/api/get_bus_details.php?bus_id=' + encodeURIComponent(busId));
            const raw = await res.text();
            let result;
            try { result = JSON.parse(raw); } catch (e) {
                console.error('Parse error:', raw);
                return;
            }

            if (result.status !== 'success' || !result.data) {
                console.error('Failed to load bus:', result);
                return;
            }

            const bus = result.data;
            document.getElementById('edit_bus_id').value = bus.bus_id || '';
            document.getElementById('edit_plate_number').value = bus.plate_number || '';
            document.getElementById('edit_bus_type').value = bus.bus_type || '';
            document.getElementById('edit_seats').value = bus.seats || '';
            document.getElementById('edit_standing_capacity').value = bus.standing_capacity || '';
            document.getElementById('edit_accessible_features').value = bus.accessible_features || '';
            document.getElementById('edit_from_terminal_id').value = bus.from_terminal_id || '';
            document.getElementById('edit_to_terminal_id').value = bus.to_terminal_id || '';
            document.getElementById('edit_schedule').value = bus.schedule || '';
            document.getElementById('edit_driver').value = bus.driver_id || '';
            document.getElementById('edit_status').value = bus.status || '';

        } catch (err) {
            console.error('Failed to load bus details:', err);
        }
    }
    );

    document
    .getElementById(
    'editBusForm'
    )
    .addEventListener(
    'submit',
    async function (e) {

        e.preventDefault();

        const submitBtn =
        document.querySelector(
        '[form="editBusForm"]'
        );

        submitBtn.disabled = true;

        submitBtn.innerHTML =
        'Updating...';

        try {

            const formData =
            new FormData(
            this
            );

            const response =
            await fetch(

            '/admin/api/edit_bus.php',

            {
                method:'POST',
                body:formData
            }

            );

            const raw =
            await response.text();

            console.log('RAW RESPONSE:', raw);
            console.log('HTTP Status:', response.status);

            let result;

            try {
                result = JSON.parse(raw);
            } catch (parseError) {
                console.error('JSON PARSE ERROR:', parseError);
                console.error('Raw output was not valid JSON. Possible PHP error or server issue.');

                let detailHtml = '<div style="text-align:left;">';
                detailHtml += '<p style="color:#721c24;font-weight:600;margin-bottom:10px;">The server returned an invalid response. This usually means:</p>';
                detailHtml += '<ul style="margin-bottom:12px;padding-left:20px;">';
                detailHtml += '<li>A PHP error occurred on the server</li>';
                detailHtml += '<li>A database connection issue</li>';
                detailHtml += '<li>A required field is missing or invalid</li>';
                detailHtml += '</ul>';
                detailHtml += '<p style="font-weight:600;margin-bottom:6px;">Raw Response:</p>';
                detailHtml += '<pre style="background:#f5f5f5;padding:12px;border-radius:8px;max-height:200px;overflow:auto;font-size:12px;border:1px solid #ddd;">' + raw + '</pre>';
                detailHtml += '</div>';

                showAlert('error', 'Server Error', 'An unexpected server error occurred. Check console for details.');
                return;
            }

            console.log('RESULT:', result);

            if (result.status === 'success') {
                showAlert('success', 'Bus Updated', 'Bus updated successfully.');
                this.reset();
                bootstrap.Modal.getInstance(document.getElementById('editBusModal'))?.hide();
                location.reload();
            } else {
                let errorMsg = result.message || 'Failed to update bus';
                let errorHtml = '<div style="text-align:left;">';
                errorHtml += '<p style="color:#721c24;margin-bottom:8px;">' + errorMsg + '</p>';
                errorHtml += '<p style="font-size:0.85rem;color:#666;margin-bottom:6px;">Possible causes:</p>';
                errorHtml += '<ul style="font-size:0.85rem;color:#666;padding-left:20px;margin-bottom:0;">';
                errorHtml += '<li>Bus ID or Plate Number already exists</li>';
                errorHtml += '<li>Database constraint violation</li>';
                errorHtml += '<li>Missing required field</li>';
                errorHtml += '</ul>';
                errorHtml += '</div>';

                showAlert('error', 'Update Failed', errorMsg);
            }

        } catch (error) {
            console.error('FETCH ERROR:', error);

            let errorHtml = '<div style="text-align:left;">';
            errorHtml += '<p style="color:#721c24;font-weight:600;margin-bottom:8px;">A network or JavaScript error occurred:</p>';
            errorHtml += '<pre style="background:#f5f5f5;padding:12px;border-radius:8px;font-size:12px;border:1px solid #ddd;max-height:150px;overflow:auto;">' + error.message + '</pre>';
            errorHtml += '<p style="font-size:0.85rem;color:#666;margin-top:10px;">Check the browser console (F12) for more details.</p>';
            errorHtml += '</div>';

            showAlert('error', 'Connection Error', 'A network or JavaScript error occurred. Check console for details.');
        }

        submitBtn.disabled = false;

        submitBtn.innerHTML =
        '<i class="fa-solid fa-pen-to-square me-2"></i> Update Bus';

    }
    );

</script>

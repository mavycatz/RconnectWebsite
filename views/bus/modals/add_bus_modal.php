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

<div class="modal fade" id="addBusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 overflow-hidden"
     style="border-radius:24px; background:#fef8f3; box-shadow:0 18px 50px rgba(0,0,0,.08);">

            <div class="row g-0">

                <div class="col-lg-4 d-none d-lg-block"
     style="background:#f9f4da; border-right:1px solid #e7e1c9; min-height:780px;">

                    <div class="p-5 h-100 d-flex flex-column justify-content-between">

                        <div>

                            <div style="width:72px;height:72px;border-radius:22px;background:#ffffff;border:1px solid #ebe5ca;display:flex;align-items:center;justify-content:center;">
                                <i class="fa-solid fa-bus" style="color:#c4b417; font-size:1.6rem;"></i>
                            </div>

                            <h2 style="margin-top:28px;font-size:2rem;font-weight:700;line-height:1.3;color:#2e4836;">
    Fleet Addition Center
                            </h2>

                            <p style="margin-top:18px;font-size:.94rem;line-height:1.9;color:#70796f;">
    Register new buses, assign drivers and
    routes, configure capacity, and manage
    fleet records through a centralized system.
                            </p>

                        </div>

                        <div class="d-grid gap-3">

                            <div style="background:#ffffff;border:1px solid #ebe5ca;border-radius:18px;padding:18px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:46px;height:46px;border-radius:14px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                                        <i class="fa-solid fa-qrcode" style="color:#c4b417; font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.9rem;font-weight:700;color:#2e4836;">Automatic QR Code</div>
                                        <div style="font-size:.76rem;color:#7d857c;">Each bus receives a unique QR for boarding</div>
                                    </div>
                                </div>
                            </div>

                            <div style="background:#ffffff;border:1px solid #ebe5ca;border-radius:18px;padding:18px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:46px;height:46px;border-radius:14px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                                        <i class="fa-solid fa-route" style="color:#c4b417; font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.9rem;font-weight:700;color:#2e4836;">Route & Terminal</div>
                                        <div style="font-size:.76rem;color:#7d857c;">Assign origin and destination terminals</div>
                                    </div>
                                </div>
                            </div>

                            <div style="background:#ffffff;border:1px solid #ebe5ca;border-radius:18px;padding:18px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:46px;height:46px;border-radius:14px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                                        <i class="fa-solid fa-chair" style="color:#c4b417; font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.9rem;font-weight:700;color:#2e4836;">Capacity Management</div>
                                        <div style="font-size:.76rem;color:#7d857c;">Configure seating and standing limits</div>
                                    </div>
                                </div>
                            </div>

                            <div style="background:#ffffff;border:1px solid #ebe5ca;border-radius:18px;padding:18px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:46px;height:46px;border-radius:14px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                                        <i class="fa-solid fa-user-gear" style="color:#c4b417; font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.9rem;font-weight:700;color:#2e4836;">Driver Assignment</div>
                                        <div style="font-size:.76rem;color:#7d857c;">Assign available drivers directly</div>
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
    Add New Bus
                            </h4>
                            <div style="font-size:.84rem;color:#7b847b;margin-top:4px;">
    Bus registration and fleet configuration
                            </div>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                    </div>

                    <div class="modal-body px-5 py-4">

                        <form id="addBusForm">

                            <div class="d-grid gap-4">

                                <div style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:24px;">

                                    <h6 style="color:#2e4836;font-weight:700;margin-bottom:20px;">
    Bus Information
                                    </h6>

                                    <div class="row g-3">

                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Bus ID</label>
                                            <input type="text" name="bus_id" class="form-control" placeholder="e.g. BUS-001" required
           style="height:54px;border-radius:16px;border:1px solid #dce3ce;">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Plate Number</label>
                                            <input type="text" name="plate_number" class="form-control" placeholder="e.g. ABC-1234" required
           style="height:54px;border-radius:16px;border:1px solid #dce3ce;">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Bus Type</label>
                                            <select name="bus_type" class="form-select"
            style="height:54px;border-radius:16px;border:1px solid #dce3ce;">
                                                <option value="Coach">Coach</option>
                                                <option value="City Bus">City Bus</option>
                                                <option value="Mini Bus">Mini Bus</option>
                                                <option value="Shuttle">Shuttle</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Seats</label>
                                            <input type="number" name="seats" class="form-control" placeholder="e.g. 45" min="1"
           style="height:54px;border-radius:16px;border:1px solid #dce3ce;">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Standing Capacity</label>
                                            <input type="number" name="standing_capacity" class="form-control" placeholder="e.g. 10" min="0"
           style="height:54px;border-radius:16px;border:1px solid #dce3ce;">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Accessible Features</label>
                                            <select name="accessible_features" class="form-select"
            style="height:54px;border-radius:16px;border:1px solid #dce3ce;">
                                                <option value="None">None</option>
                                                <option value="Priority Seats">Priority Seats</option>
                                                <option value="PWD Accessible">PWD Accessible</option>
                                                <option value="Wheelchair Ramp">Wheelchair Ramp</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Status</label>
                                            <select name="status" class="form-select"
            style="height:54px;border-radius:16px;border:1px solid #dce3ce;">
                                                <option value="Active">Active</option>
                                                <option value="Inactive">Inactive</option>
                                                <option value="Pending QR">Pending QR</option>
                                            </select>
                                        </div>

                                    </div>

                                </div>

                                <div style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:24px;">

                                    <h6 style="color:#2e4836;font-weight:700;margin-bottom:20px;">
    Route & Assignment
                                    </h6>

                                    <div class="row g-3">

                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">From Terminal</label>
                                            <select name="from_terminal_id" class="form-select"
            style="height:54px;border-radius:16px;border:1px solid #dce3ce;">
                                                <option value="">Select Origin</option>
        <?php if (isset($terminals)): ?>
        <?php foreach ($terminals as $t): ?>
        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['terminal_name']) ?></option>
        <?php endforeach; ?>
        <?php endif; ?>
    </select>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">To Terminal</label>
        <select name="to_terminal_id" class="form-select"
            style="height:54px;border-radius:16px;border:1px solid #dce3ce;">
            <option value="">Select Destination</option>
        <?php if (isset($terminals)): ?>
        <?php foreach ($terminals as $t): ?>
        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['terminal_name']) ?></option>
        <?php endforeach; ?>
        <?php endif; ?>
    </select>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Schedule</label>
        <input type="text" name="schedule" class="form-control" placeholder="e.g. 06:00 AM – 06:00 PM"
           style="height:54px;border-radius:16px;border:1px solid #dce3ce;">
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Assign Driver</label>
        <select name="driver" class="form-select"
            style="height:54px;border-radius:16px;border:1px solid #dce3ce;">
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

        <button type="button" class="btn" data-bs-dismiss="modal"
        style="height:50px;padding:0 24px;border-radius:14px;background:#ffffff;border:1px solid #dfe5d1;color:#687168;font-weight:600;">
    Cancel
        </button>

        <button type="submit" form="addBusForm" class="btn"
        style="height:52px;padding:0 28px;border:none;border-radius:16px;background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);color:#ffffff;font-weight:700;">
            <i class="fa-solid fa-floppy-disk me-2"></i>
    Save Bus
        </button>

    </div>

    </div>

    </div>

    </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js">    </script>
    <script>
        document.getElementById('addBusForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const submitBtn = document.querySelector('[form="addBusForm"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Saving...';
            try {
                const formData = new FormData(this);
                const response = await fetch('/admin/api/add_bus.php', { method: 'POST', body: formData });
                const raw = await response.text();
                let result;
                try { result = JSON.parse(raw); } catch (parseError) {
                    showAlert('error', 'Invalid JSON Response', 'Server returned an unexpected response.');
                    return;
                }
                if (result.status === 'success') {
                    showAlert('success', 'Bus Added', 'Bus added successfully.');
                    this.reset();
                    bootstrap.Modal.getInstance(document.getElementById('addBusModal'))?.hide();
                    location.reload();
                } else {
                    showAlert('error', 'Failed', result.message || 'Failed to add bus.');
                }
            } catch (error) {
                showAlert('error', 'Error', error.message);
            }
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i> Save Bus';
        });
    </script>

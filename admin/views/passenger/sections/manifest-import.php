<div class="modal fade" id="manifestImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 overflow-hidden"
     style="border-radius:24px; background:#fef8f3; box-shadow:0 18px 50px rgba(0,0,0,.08);">

            <div class="row g-0">

                <div class="col-lg-4 d-none d-lg-block p-4"
     style="background:#f9f4da; border-right:1px solid #e7e1c9; min-height:400px;">

                    <div class="h-100 d-flex flex-column justify-content-center text-center">
                        <div style="width:72px;height:72px;border-radius:50%;background:#ffffff;border:1px solid #cdd9b5;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                            <i class="bi bi-file-earmark-image" style="color:#57694d; font-size:1.8rem;"></i></div>
                        <h2 style="font-size:1.5rem;font-weight:700;color:#2e4836;margin-bottom:8px;">Manifest Sheet Import</h2>
                        <p style="font-size:.85rem;color:#57694d;line-height:1.7;margin-bottom:0;padding:0 10px;">
    Upload a completed offline trip sheet (image, PDF, CSV, or Excel) to extract and review passenger records before saving.
                        </p>
                    </div>

                </div>

                <div class="col-lg-8 col-12">

                    <div class="modal-header border-0 px-4 pt-4 pb-0">
                        <h4 style="margin:0;color:#2e4836;font-weight:700;font-size:1.1rem;">
                            <i class="bi bi-file-earmark-image me-2" style="color:#57694d;"></i>Import Manifest Sheet
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="resetManifestImport()"></button>
                    </div>

                    <div class="modal-body px-4 py-3" style="max-height:70vh; overflow-y:auto;">

                        <div id="mImportUploadStep">
                            <div style="border:2px dashed #cdd9b5; border-radius:14px; padding:22px; background:#faf9e5; text-align:center;">
                                <input type="file" id="manifestFiles" multiple
                   accept=".jpg,.jpeg,.png,.pdf,.csv,.xls,.xlsx,.ods"
                   style="margin:0 auto 4px; max-width:100%;" />
                                <div style="font-size:0.78rem; color:#8a9a8a;">
                Supported: JPG/JPEG/PNG (scanned photo), PDF (scanned document), CSV, Excel (.xls/.xlsx), ODS. Max ~20&nbsp;MB per file.
                                </div>
                            </div>
                            <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                                <button type="button" id="manifestExtractBtn" class="psgr-btn psgr-btn-primary psgr-btn-sm" onclick="extractManifest()">
                                    <i class="bi bi-magic"></i> Extract &amp; Preview
                                </button>
                                <span id="manifestExtractStatus" style="font-size:0.8rem; color:#57694d;"></span>
                            </div>
                        </div>

                        <div id="mImportPreviewStep" style="display:none;">

                            <div id="mImportWarnings"></div>

                            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin:14px 0; padding:14px; border:1px solid #e0e6c8; border-radius:12px; background:#fff;">
                                <div>
                                    <label style="font-size:0.72rem;font-weight:700;color:#57694d;">TRIP DATE</label>
                                    <input type="date" id="mTripDate" class="form-control form-control-sm" />
                                </div>
                                <div>
                                    <label style="font-size:0.72rem;font-weight:700;color:#57694d;">ROUTE</label>
                                    <input type="text" id="mRoute" class="form-control form-control-sm" placeholder="e.g. Manila - Bicol" />
                                </div>
                                <div>
                                    <label style="font-size:0.72rem;font-weight:700;color:#57694d;">BUS NO.</label>
                                    <input type="text" id="mBusNumber" class="form-control form-control-sm" placeholder="Bus plate / no." />
                                </div>
                                <div>
                                    <label style="font-size:0.72rem;font-weight:700;color:#57694d;">DRIVER</label>
                                    <input type="text" id="mDriver" class="form-control form-control-sm" placeholder="Driver full name" />
                                </div>
                                <div>
                                    <label style="font-size:0.72rem;font-weight:700;color:#57694d;">TRIP NO.</label>
                                    <input type="text" id="mTripNumber" class="form-control form-control-sm" placeholder="TRIP-#### (optional)" />
                                </div>
                                <div>
                                    <label style="font-size:0.72rem;font-weight:700;color:#57694d;">PAYMENT</label>
                                    <select id="mPayment" class="form-select form-select-sm">
                                        <option value="cash" selected>Cash</option>
                                        <option value="gcash">GCash</option>
                                        <option value="hybrid">Hybrid</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size:0.72rem;font-weight:700;color:#57694d;">REMARKS</label>
                                    <input type="text" id="mRemarks" class="form-control form-control-sm" placeholder="Optional notes" />
                                </div>
                            </div>

                            <div id="mImportSummary" class="m-summary"></div>

                            <div class="m-table-wrap">
                                <table class="m-table" id="mPreviewTable">
                                    <thead>
                                        <tr>
                                            <th class="m-col-status">Status</th>
                                            <th>#</th>
                                            <th>Passenger Name</th>
                                            <th class="m-col-type">Type</th>
                                            <th>Discount</th>
                                            <th>Student ID</th>
                                            <th>Boarding</th>
                                            <th>Destination</th>
                                            <th>Fare</th>
                                            <th>Payment</th>
                                            <th class="m-col-reasons">Notes / Reasons</th>
                                            <th class="m-col-confirm">Import?</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>

                            <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                                <button type="button" id="manifestConfirmBtn" class="psgr-btn psgr-btn-primary" onclick="confirmManifestImport()">
                                    <i class="bi bi-check2-circle"></i> Import Valid Records
                                </button>
                                <button type="button" class="psgr-btn psgr-btn-outline" onclick="resetManifestImport()">Reset</button>
                                <span id="manifestImportStatus" style="font-size:0.82rem; font-weight:600;"></span>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="button" class="btn" data-bs-dismiss="modal" onclick="resetManifestImport()"
            style="height:48px;border-radius:14px;background:#ffffff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;flex:1;">Cancel</button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .m-summary {
            display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 12px;
        }
        .m-summary .m-chip {
            font-size: 0.78rem; font-weight: 700; padding: 6px 12px; border-radius: 20px;
            border: 1px solid #e0e6c8; background: #f6f4df; color: #2e4836;
        }
        .m-summary .m-chip b { display: block; font-size: 1.05rem; line-height: 1; }
        .m-summary .m-chip.m-valid { border-color: #198754; background: #e8f6ef; color: #0b5d37; }
        .m-summary .m-chip.m-review { border-color: #d97706; background: #fef3c7; color: #92400e; }
        .m-summary .m-chip.m-invalid { border-color: #dc3545; background: #fbe9e7; color: #b02a37; }
        .m-summary .m-chip.m-dup { border-color: #6f42c1; background: #f1e8fb; color: #5b21b6; }

        .m-table-wrap { overflow-x: auto; border: 1px solid #e0e6c8; border-radius: 12px; background: #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, .04); }
        .m-table { width: 100%; min-width: 980px; border-collapse: collapse; font-size: 0.8rem; }
        .m-table th, .m-table td { border: 1px solid #ecefdb; padding: 6px 8px; vertical-align: top; white-space: nowrap; }
        .m-table thead th { background: #f3f0d8; color: #2e4836; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; letter-spacing: .3px; }
        .m-table tbody tr:hover { background: #faf9ef; }
        .m-table input, .m-table select { width: 100%; min-width: 70px; font-size: 0.78rem; border: 1px solid #d9dfc0; border-radius: 6px; padding: 3px 5px; }
        .m-table td.m-edit-col input.m-pname { min-width: 150px; }
        .m-col-status { min-width: 90px; }
        .m-col-type { min-width: 90px; }
        .m-col-reasons { min-width: 200px; white-space: normal !important; }
        .m-col-confirm { min-width: 90px; }

        .m-badge { display: inline-block; font-size: 0.68rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; }
        .m-badge.valid { background: #e8f6ef; color: #0b5d37; border: 1px solid #198754; }
        .m-badge.review { background: #fef3c7; color: #92400e; border: 1px solid #d97706; }
        .m-badge.invalid { background: #fbe9e7; color: #b02a37; border: 1px solid #dc3545; }
        .m-badge.dup { background: #f1e8fb; color: #5b21b6; border: 1px solid #6f42c1; }
        .m-reasons { font-size: 0.72rem; color: #b02a37; line-height: 1.3; white-space: normal; }

        @media(max-width:768px) {
            #manifestImportModal .modal-dialog.modal-xl { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; }
        }
    </style>

    <script>
        var mManifestState = { passengers: [] };

        function openManifestImport() {
            var modal = new bootstrap.Modal(document.getElementById('manifestImportModal'));
            modal.show();
        }
        function closeManifestImport() {
            var el = document.getElementById('manifestImportModal');
            var inst = bootstrap.Modal.getInstance(el);
            if (inst) inst.hide();
        }
        function resetManifestImport() {
            document.getElementById('mImportUploadStep').style.display = 'block';
            document.getElementById('mImportPreviewStep').style.display = 'none';
            document.getElementById('manifestFiles').value = '';
            document.getElementById('manifestExtractStatus').textContent = '';
            document.getElementById('manifestImportStatus').textContent = '';
            document.getElementById('mImportWarnings').innerHTML = '';
            mManifestState.passengers = [];
        }

        function mSetExtractStatus(msg, color) {
            var el = document.getElementById('manifestExtractStatus');
            el.textContent = msg || '';
            el.style.color = color || '#57694d';
        }
        function mSetImportStatus(msg, color) {
            var el = document.getElementById('manifestImportStatus');
            el.textContent = msg || '';
            el.style.color = color || '#2e4836';
        }

        function extractManifest() {
            var fileInput = document.getElementById('manifestFiles');
            var files = fileInput.files;
            if (!files || !files.length) {
                mSetExtractStatus('Please choose at least one file.', '#b02a37');
                return;
            }
            var allowed = ['jpg', 'jpeg', 'png', 'pdf', 'csv', 'xls', 'xlsx', 'ods'];
            for (var i = 0; i < files.length; i++) {
                var ext = files[i].name.split('.').pop().toLowerCase();
                if (allowed.indexOf(ext) === -1) {
                    mSetExtractStatus('Unsupported file type: ' + files[i].name, '#b02a37');
                    return;
                }
                if (files[i].size > 20 * 1024 * 1024) {
                    mSetExtractStatus(files[i].name + ' exceeds 20MB.', '#b02a37');
                    return;
                }
            }

            var btn = document.getElementById('manifestExtractBtn');
            btn.disabled = true;
            mSetExtractStatus('Extracting… this may take a moment for OCR.', '#57694d');

            var fd = new FormData();
            for (var j = 0; j < files.length; j++) fd.append('manifest_files[]', files[j]);

            fetch('/admin/api/Passenger/manifest_process.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                if (!res.success) {
                    mSetExtractStatus(res.message || 'Extraction failed.', '#b02a37');
                    return;
                }
                mSetExtractStatus('');
                renderManifestPreview(res);
            })
            .catch (function (err) {
                btn.disabled = false;
                mSetExtractStatus('Request error: ' + err, '#b02a37');
            });
        }

        function renderManifestPreview(res) {
            var trip = res.trip || {};
            var passengers = Array.isArray(res.passengers) ? res.passengers : [];
            if (!passengers.length) {
                mSetExtractStatus('No passenger rows detected. Please check the file and try again.', '#b02a37');
                return;
            }

            mManifestState.passengers = passengers;
            mManifestState.trip = trip;

            document.getElementById('mTripDate').value = trip.trip_date || '';
            document.getElementById('mRoute').value = trip.route || '';
            document.getElementById('mBusNumber').value = trip.bus_number || '';
            document.getElementById('mDriver').value = trip.driver || '';
            document.getElementById('mTripNumber').value = trip.trip_number || '';
            if (trip.payment_method) document.getElementById('mPayment').value = trip.payment_method;
            document.getElementById('mRemarks').value = trip.remarks || '';

            document.getElementById('mImportWarnings').innerHTML = buildWarningsHtml(res.warnings);
            renderTable();
            updateSummary();

            document.getElementById('mImportUploadStep').style.display = 'none';
            document.getElementById('mImportPreviewStep').style.display = 'block';
        }

        function buildWarningsHtml(warnings) {
            if (!warnings || !warnings.length) return '';
            var html = '<div style="font-size:0.78rem;color:#92400e;background:#fef3c7;border:1px solid #f3d19a;border-radius:10px;padding:8px 12px;margin-bottom:8px;">';
            html += '<b>Warnings:</b><ul style="margin:4px 0 0 16px;">';
            for (var w = 0; w < warnings.length; w++) html += '<li>' + mEsc(warnings[w]) + '</li>';
            html += '</ul></div>';
            return html;
        }

        function mBadge(status, dup) {
            var out = '';
            if (status === 'invalid') out += '<span class="m-badge invalid">🔴 Invalid</span>';
            else if (status === 'review') out += '<span class="m-badge review">🟡 Review</span>';
            else out += '<span class="m-badge valid">🟢 Valid</span>';
            if (dup) out += '<br><span class="m-badge dup">⚪ Dup?</span>';
            return out;
        }

        function renderTable() {
            var tbody = document.querySelector('#mPreviewTable tbody');
            var rows = '';
            var ps = mManifestState.passengers;
            for (var i = 0; i < ps.length; i++) {
                var p = ps[i] || {};
                var status = p.status || 'valid';
                var reasons = Array.isArray(p.reasons) ? p.reasons.slice() : [];
                var dup = !!p.possible_duplicate;
                if (dup) reasons.unshift('Possible duplicate record');
                var waitChecked = (status === 'valid' && !dup) ? 'checked' : '';
                p._confirmed = (p._confirmed === undefined || p._confirmed === null)
                ? waitChecked === 'checked'
                : p._confirmed;
                var disabledChk = (status === 'invalid') ? ' disabled' : '';
                rows +=
                '<tr data-idx="' + i + '">' +
                '<td>' + mBadge(status, dup) + '</td>' +
                '<td>' + (i + 1) + '</td>' +
                '<td class="m-edit-col"><input type="text" class="m-pname form-control" value="' + mEsc(p.passenger_name || '') + '" oninput="mEdit(' + i + ',this,\'passenger_name\')"></td>' +
                '<td><select onchange="mEdit(' + i + ',this,\'passenger_type\')">' + mTypeOptions(p.passenger_type) + '</select></td>' +
                '<td><input type="text" class="form-control" value="' + mEsc(p.discount_type || '') + '" oninput="mEdit(' + i + ',this,\'discount_type\')"></td>' +
                '<td><input type="text" class="form-control" value="' + mEsc(p.student_id || '') + '" oninput="mEdit(' + i + ',this,\'student_id\')"></td>' +
                '<td><input type="text" class="form-control" value="' + mEsc(p.boarding_point || '') + '" oninput="mEdit(' + i + ',this,\'boarding_point\')"></td>' +
                '<td><input type="text" class="form-control" value="' + mEsc(p.destination || '') + '" oninput="mEdit(' + i + ',this,\'destination\')"></td>' +
                '<td><input type="number" step="0.01" class="form-control" value="' + mEsc(p.fare || '') + '" oninput="mEdit(' + i + ',this,\'fare\')"></td>' +
                '<td><select onchange="mEdit(' + i + ',this,\'payment_method\')">' + mPayOptions(p.payment_method) + '</select></td>' +
                '<td class="m-col-reasons">' + (reasons.length ? '<div class="m-reasons">' + reasons.map(mEsc).join('<br>') + '</div>' : '') + '</td>' +
                '<td class="m-col-confirm"><input type="checkbox" class="form-check-input" ' + waitChecked +
                ' onchange="mToggleConfirm(' + i + ',this)"' + disabledChk + '></td>' +
                '</tr>';
            }
            tbody.innerHTML = rows;
        }

        function mTypeOptions(sel) {
            var types = ['Regular', 'Student', 'Senior', 'PWD', 'Nurse', 'Teacher', 'Other'];
            var html = '';
            for (var t = 0; t < types.length; t++) {
                html += '<option value="' + types[t] + '"' + (types[t] === (sel || 'Regular') ? ' selected' : '') + '>' + types[t] + '</option>';
            }
            return html;
        }
        function mPayOptions(sel) {
            var ops = [['cash', 'Cash'], ['gcash', 'GCash'], ['hybrid', 'Hybrid']];
            var html = '';
            for (var p = 0; p < ops.length; p++) {
                html += '<option value="' + ops[p][0] + '"' + (ops[p][0] === (sel || 'cash') ? ' selected' : '') + '>' + ops[p][1] + '</option>';
            }
            return html;
        }

        function mEdit(idx, el, field) {
            var p = mManifestState.passengers[idx];
            p[field] = el.value;
            updateSummary();
        }

        function mToggleConfirm(idx, el) {
            var p = mManifestState.passengers[idx];
            p._confirmed = el.checked;
            updateSummary();
        }

        function computeCounts() {
            var ps = mManifestState.passengers;
            var valid = 0, review = 0, invalid = 0, dup = 0;
            for (var i = 0; i < ps.length; i++) {
                var st = ps[i].status || 'valid';
                var isDup = !!ps[i].possible_duplicate;
                if (isDup) dup++;
                if (st === 'invalid') invalid++;
                else if (st === 'review') review++;
                else valid++;
            }
            return { total: ps.length, valid: valid, review: review, invalid: invalid, dup: dup };
        }

        function updateSummary() {
            var c = computeCounts();
            var confirmable = 0;
            for (var i = 0; i < mManifestState.passengers.length; i++) {
                var p = mManifestState.passengers[i];
                var st = p.status || 'valid';
                var isDup = !!p.possible_duplicate;
                if (st === 'invalid') continue;
                if (st === 'review' || isDup) {
                    if (p._confirmed) confirmable++;
                } else {
                    confirmable++;
                }
            }
            var html =
            '<span class="m-chip">Total <b>' + c.total + '</b></span>' +
            '<span class="m-chip m-valid">Valid <b>' + c.valid + '</b></span>' +
            '<span class="m-chip m-review">Review <b>' + c.review + '</b></span>' +
            '<span class="m-chip m-invalid">Invalid <b>' + c.invalid + '</b></span>' +
            '<span class="m-chip m-dup">Dup? <b>' + c.dup + '</b></span>' +
            '<span class="m-chip">Ready to Import <b>' + confirmable + '</b></span>';
            document.getElementById('mImportSummary').innerHTML = html;
        }

        function confirmManifestImport() {
            var trip = {
                trip_date: document.getElementById('mTripDate').value,
                route: document.getElementById('mRoute').value,
                bus_number: document.getElementById('mBusNumber').value,
                driver: document.getElementById('mDriver').value,
                trip_number: document.getElementById('mTripNumber').value,
                payment_method: document.getElementById('mPayment').value,
                remarks: document.getElementById('mRemarks').value
            };
            var passengers = [];
            var ps = mManifestState.passengers;
            for (var i = 0; i < ps.length; i++) {
                var p = ps[i];
                if (!p._confirmed) continue;
                passengers.push({
                    passenger_name: p.passenger_name,
                    passenger_type: p.passenger_type,
                    discount_type: p.discount_type,
                    student_id: p.student_id,
                    boarding_point: p.boarding_point,
                    destination: p.destination,
                    fare: p.fare,
                    payment_method: p.payment_method,
                    _confirmed: !!p._confirmed
                });
            }
            if (!passengers.length) {
                mSetImportStatus('No records selected for import. Tick the rows to include (invalid rows cannot be imported).', '#b02a37');
                return;
            }

            var btn = document.getElementById('manifestConfirmBtn');
            btn.disabled = true;
            mSetImportStatus('Importing…', '#57694d');

            fetch('/admin/api/Passenger/manifest_confirm.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ trip: trip, passengers: passengers })
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                if (res.success) {
                    mSetImportStatus('✅ ' + (res.message || 'Import complete.'), '#0b5d37');
                    if (res.skipped_reasons && res.skipped_reasons.length) {
                        document.getElementById('mImportWarnings').innerHTML =
                        '<div style="font-size:0.78rem;color:#92400e;background:#fef3c7;border:1px solid #f3d19a;border-radius:10px;padding:8px 12px;margin-top:8px;">' +
                        '<b>Skipped:</b><ul style="margin:4px 0 0 16px;">' + res.skipped_reasons.map(mEsc).map(function (r) { return '<li>'+r+'</li>';}).join('') + '</ul></div>';
                    }
                } else {
                    mSetImportStatus('⚠️ ' + (res.message || 'Import failed.'), '#b02a37');
                }
            })
            .catch (function (err) {
                btn.disabled = false;
                mSetImportStatus('Request error: ' + err, '#b02a37');
            });
        }

        function mEsc(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    </script>

<?php
declare(strict_types = 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

$user = Auth::user();

if (!in_array($user['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: /admin/');
    exit;
}

define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT']);
$passengerPageTitle = 'Manifest Scanner';
?>
<?php require ROOT_PATH . '/admin/views/passenger/layout/header.php'; ?>
<?php require ROOT_PATH . '/admin/views/passenger/layout/styles.php'; ?>

<body class="passenger-management-page">

<?php require __DIR__ . '/layout/navbar.php'; ?>

<div class="main-content" id="mainContent">
    <div class="rmg-shell">
        <section class="rmg passenger-management">

            <header class="rmg-head">
                <div>
                    <h1>Manifest Scanner</h1>
                    <p>Upload a passenger manifest image to extract data via AI.</p>
                </div>
                <a class="rmg-btn" href="<?= htmlspecialchars($adminUrl('passenger'), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-arrow-left"></i> Back to Passengers
                </a>
            </header>

            <div id="uploadSection" class="rmg-detail-section" style="padding:28px 16px;text-align:center;border-style:dashed;">
                <div class="rmg-form-icon" style="margin:0 auto 12px;"><i class="bi bi-camera"></i></div>
                <h5 style="font-weight:600;color:#2e4836;margin-bottom:4px;">Upload Passenger Manifest</h5>
                <p style="font-size:13px;color:#6b7280;margin-bottom:16px;">Take a photo or upload scanned manifest sheets (JPG, PNG, up to 5 images)</p>

                <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:12px;">
                    <label tabindex="0" id="cameraBtn" class="rmg-btn rmg-btn-primary">
                        <i class="bi bi-camera-fill"></i> Take Photo
                        <input type="file" id="cameraInput" name="camera_image" accept="image/*" capture="environment" style="position:absolute;opacity:0;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);">
                    </label>
                    <button type="button" id="selectImageBtn" class="rmg-btn">
                        <i class="bi bi-image"></i> Select Files
                    </button>
                </div>

                <input type="file" id="fileInput" name="manifest_images[]" accept="image/*" multiple style="display:none;">

                <div id="fileInfo" style="font-size:12px;color:#6b7280;margin-bottom:8px;"></div>

                <div id="previewArea" style="display:none;margin-top:8px;">
                    <div id="previewGrid" style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-bottom:12px;"></div>
                    <div>
                        <button id="processBtn" class="rmg-btn rmg-btn-primary">
                            <i class="bi bi-cpu"></i> Process with AI
                        </button>
                        <button id="clearBtn" class="rmg-btn" style="margin-left:8px;">
                            Clear
                        </button>
                    </div>
                </div>
                <div id="processingSpinner" style="display:none;margin-top:20px;">
                    <div class="spinner-border spinner-border-sm" style="color:#47613b;" role="status"></div>
                    <span style="font-size:13px;color:#6b7280;margin-left:8px;">AI is processing the manifest...</span>
                </div>
            </div>

            <div id="resultsSection" style="display:none;margin-top:20px;">

                <div class="rmg-detail-section">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 style="font-weight:600;color:#2e4836;margin:0;font-size:15px;">
                            <i class="bi bi-table"></i> Extracted Data
                        </h5>
                        <div style="display:flex;gap:8px;">
                            <button id="saveBtn" class="rmg-btn rmg-btn-primary">
                                <i class="bi bi-check-lg"></i> Save to Database
                            </button>
                            <button id="resetBtn" class="rmg-btn">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </button>
                        </div>
                    </div>

                    <div id="tripInfoBar" style="background:#f9f4da;border:1px solid #e3eabb;border-radius:12px;padding:12px 16px;margin-bottom:16px;display:none;"></div>

                    <div class="rmg-table-wrap" style="overflow-x:auto;">
                        <table class="rmg-table manifest-extracted-table">
                            <thead>
                                <tr>
                                    <th style="padding:8px 10px;">#</th>
                                    <th style="padding:8px 10px;">Passenger Name</th>
                                    <th style="padding:8px 10px;">Type</th>
                                    <th style="padding:8px 10px;">Student ID</th>
                                    <th style="padding:8px 10px;">Senior/PWD ID</th>
                                    <th style="padding:8px 10px;">Beneficiary ID</th>
                                    <th style="padding:8px 10px;">Coop ID</th>
                                    <th style="padding:8px 10px;">Boarding</th>
                                    <th style="padding:8px 10px;">Destination</th>
                                    <th style="padding:8px 10px;text-align:right;">Fare</th>
                                    <th style="padding:8px 10px;">Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="passengersBody"></tbody>
                        </table>
                    </div>

                    <div id="emptyResult" style="display:none;padding:30px;text-align:center;color:#6b7280;">
                        No passengers extracted. Try a clearer image.
                    </div>

                </div>
            </div>

            <div id="saveResult" style="display:none;margin-top:12px;padding:12px 16px;border-radius:12px;font-size:13px;font-weight:500;"></div>

        </section>
    </div>
</div>

<?php require ROOT_PATH . '/admin/views/passenger/layout/scripts.php'; ?>

<script>
    let selectedFiles = [];

    document.getElementById('selectImageBtn').addEventListener('click', () => document.getElementById('fileInput').click());

    function updatePreviews() {
        const grid = document.getElementById('previewGrid');
        const info = document.getElementById('fileInfo');
        grid.innerHTML = '';
        if (selectedFiles.length === 0) {
            document.getElementById('previewArea').style.display = 'none';
            info.textContent = '';
            return;
        }
        document.getElementById('previewArea').style.display = 'block';
        document.getElementById('resultsSection').style.display = 'none';
        document.getElementById('saveResult').style.display = 'none';
        info.textContent = selectedFiles.length + ' file(s) selected';
        selectedFiles.forEach((file, i) => {
            const wrapper = document.createElement('div');
            wrapper.style.position = 'relative';
            wrapper.style.display = 'inline-block';
            const img = document.createElement('img');
            img.style.height = '100px';
            img.style.borderRadius = '8px';
            img.style.border = '1px solid #e3eabb';
            img.style.objectFit = 'cover';
            const reader = new FileReader();
            reader.onload = e => img.src = e.target.result;
            reader.readAsDataURL(file);
            wrapper.appendChild(img);
            const del = document.createElement('button');
            del.innerHTML = '&times;';
            del.style.position = 'absolute';
            del.style.top = '-6px';
            del.style.right = '-6px';
            del.style.width = '22px';
            del.style.height = '22px';
            del.style.borderRadius = '50%';
            del.style.border = 'none';
            del.style.background = '#dc2626';
            del.style.color = '#fff';
            del.style.fontSize = '14px';
            del.style.cursor = 'pointer';
            del.style.display = 'flex';
            del.style.alignItems = 'center';
            del.style.justifyContent = 'center';
            del.onclick = () => {
                selectedFiles.splice(i, 1);
                updatePreviews();
            };
            wrapper.appendChild(del);
            grid.appendChild(wrapper);
        });
    }

    document.getElementById('cameraInput').addEventListener('change', function (e) {
        if (this.files.length) {
            if (selectedFiles.length < 5) selectedFiles.push(this.files[0]);
            updatePreviews();
        }
        this.value = '';
    });

    document.getElementById('fileInput').addEventListener('change', function (e) {
        const remaining = 5 - selectedFiles.length;
        for (let i = 0; i < Math.min(this.files.length, remaining); i++) {
            selectedFiles.push(this.files[i]);
        }
        updatePreviews();
        this.value = '';
    });

    document.getElementById('clearBtn').addEventListener('click', () => {
        selectedFiles = [];
        updatePreviews();
    });

    document.getElementById('processBtn').addEventListener('click', async function () {
        if (selectedFiles.length === 0) return;

        document.getElementById('processingSpinner').style.display = 'block';
        this.disabled = true;
        document.getElementById('saveResult').style.display = 'none';

        document.getElementById('processingSpinner').innerHTML =
        '<div class="spinner-border spinner-border-sm" style="color:#47613b;" role="status"></div>' +
        '<span style="font-size:13px;color:#6b7280;margin-left:8px;">Processing...</span>';

        let allPassengers = [];
        let lastTrip = null;

        for (let i = 0; i < selectedFiles.length; i++) {
            document.getElementById('processingSpinner').innerHTML =
            '<div class="spinner-border spinner-border-sm" style="color:#47613b;" role="status"></div>' +
            '<span style="font-size:13px;color:#6b7280;margin-left:8px;">Processing image ' + (i + 1) + ' of ' + selectedFiles.length + '...</span>';

            const formData = new FormData();
            formData.append('manifest_images[]', selectedFiles[i]);

            try {
                const res = await fetch('/admin/api/ocr_process.php', {
                    method: 'POST',
                    body: formData
                });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    document.getElementById('processingSpinner').style.display = 'none';
                    this.disabled = false;
                    await Swal.fire({
                        icon: 'error',
                        title: 'Image ' + (i + 1) + ' Failed',
                        text: 'Server returned non-JSON response.',
                        footer: text.substring(0, 200),
                        confirmButtonColor: '#2e4836'
                    });
                    return;
                }

                if (!data.success) {
                    document.getElementById('processingSpinner').style.display = 'none';
                    this.disabled = false;
                    await Swal.fire({
                        icon: 'error',
                        title: 'Image ' + (i + 1) + ' Error',
                        text: data.message || 'Processing failed',
                        confirmButtonColor: '#2e4836'
                    });
                    return;
                }

                if (data.passengers) allPassengers = allPassengers.concat(data.passengers);
                if (data.trip && !lastTrip) lastTrip = data.trip;
            } catch (err) {
                document.getElementById('processingSpinner').style.display = 'none';
                this.disabled = false;
                await Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Image ' + (i + 1) + ': ' + err.message,
                    confirmButtonColor: '#2e4836'
                });
                return;
            }
        }

        document.getElementById('processingSpinner').style.display = 'none';
        this.disabled = false;

        renderResults({
            success: true,
            trip: lastTrip || { trip_date: new Date().toISOString().split('T')[0], payment_method: 'cash' },
            passengers: allPassengers
        });
    });

    function renderResults(data) {
        window._ocrData = data;
        const trip = data.trip || {};
        const passengers = data.passengers || [];

        const infoBar = document.getElementById('tripInfoBar');
        infoBar.style.display = 'flex';
        infoBar.innerHTML = `
        <div style="display:flex;gap:20px;flex-wrap:wrap;width:100%;">
            <div><span style="font-weight:600;color:#2e4836;">Driver:</span> <span style="color:#6b7280;">${trip.drivers_full_name || '—'}</span></div>
            <div><span style="font-weight:600;color:#2e4836;">Driver ID:</span> <span style="color:#6b7280;">${trip.driver_id || '—'}</span></div>
            <div><span style="font-weight:600;color:#2e4836;">Trip Date:</span> <span style="color:#6b7280;">${trip.trip_date || '—'}</span></div>
            <div><span style="font-weight:600;color:#2e4836;">Payment:</span> <span style="color:#6b7280;">${trip.payment_method || 'cash'}</span></div>
            <div><span style="font-weight:600;color:#2e4836;">Passengers:</span> <span style="color:#6b7280;">${passengers.length}</span></div>
            <div><span style="font-weight:600;color:#2e4836;">Total Fare:</span> <span style="color:#6b7280;">₱${passengers.reduce((s, p) => s + (parseFloat(p.fare) || 0), 0).toFixed(2)}</span></div>
        </div>
    `;

        const tbody = document.getElementById('passengersBody');
        tbody.innerHTML = '';

        if (passengers.length === 0) {
            document.getElementById('emptyResult').style.display = 'block';
            document.getElementById('resultsSection').style.display = 'block';
            return;
        }

        document.getElementById('emptyResult').style.display = 'none';

        passengers.forEach((p, i) => {
            const nameSuffix = p.name_generated ? ' <span style="color:#f59e0b;font-size:10px;">(AI)</span>' : '';
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid #f0ede0';
            tr.innerHTML = `
            <td data-label="#">${i + 1}</td>
            <td data-label="Account">${escapeHtml(p.passenger_name || '')}${nameSuffix}</td>
            <td data-label="Type">${escapeHtml(p.passenger_type || '—')}</td>
            <td data-label="Student ID">${escapeHtml(p.student_id || '—')}</td>
            <td data-label="Senior/PWD ID">${escapeHtml(p.senior_pwd_id || '—')}</td>
            <td data-label="Beneficiary ID">${escapeHtml(p.beneficiary_id || '—')}</td>
            <td data-label="Coop ID">${escapeHtml(p.coop_disc_id || '—')}</td>
            <td data-label="Boarding">${escapeHtml(p.boarding_point || '—')}</td>
            <td data-label="Destination">${escapeHtml(p.destination || '—')}</td>
            <td data-label="Fare"><strong>₱${(parseFloat(p.fare) || 0).toFixed(2)}</strong></td>
            <td data-label="Remarks">${escapeHtml(p.remarks || '—')}</td>
        `;
            tbody.appendChild(tr);
        });

        document.getElementById('resultsSection').style.display = 'block';
        document.getElementById('resultsSection').scrollIntoView({ behavior: 'smooth' });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    document.getElementById('saveBtn').addEventListener('click', async function () {
        const data = window._ocrData;
        if (!data || !data.passengers || data.passengers.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Nothing to save', text: 'No passenger data to save.', confirmButtonColor: '#2e4836' });
            return;
        }

        const trip = data.trip || {};
        const passengers = data.passengers || [];
        const totalFare = passengers.reduce((s, p) => s + (parseFloat(p.fare) || 0), 0).toFixed(2);
        const driverName = trip.drivers_full_name || '<em style="color:#e74c3c;">Not detected</em>';
        const driverId = trip.driver_id || '<em style="color:#999;">—</em>';

        let previewHtml = `
        <div style="text-align:left;font-size:13px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;margin-bottom:14px;padding:10px;background:#f9fafb;border-radius:8px;">
                <div><strong>Driver:</strong> ${driverName}</div>
                <div><strong>Driver ID:</strong> ${driverId}</div>
                <div><strong>Trip Date:</strong> ${trip.trip_date || '—'}</div>
                <div><strong>Payment:</strong> ${trip.payment_method || 'cash'}</div>
                <div><strong>Passengers:</strong> ${passengers.length}</div>
                <div><strong>Total Fare:</strong> ₱${totalFare}</div>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#2e4836;color:#fff;">
                    <th style="padding:6px 8px;">#</th>
                    <th style="padding:6px 8px;">Name</th>
                    <th style="padding:6px 8px;">Type</th>
                    <th style="padding:6px 8px;">Route</th>
                    <th style="padding:6px 8px;text-align:right;">Fare</th>
                </tr></thead>
                <tbody>`;

        passengers.slice(0, 20).forEach((p, i) => {
            previewHtml += `<tr style="border-bottom:1px solid #eee;">
            <td style="padding:5px 8px;">${i + 1}</td>
            <td style="padding:5px 8px;">${escapeHtml(p.passenger_name || '')}</td>
            <td style="padding:5px 8px;">${escapeHtml(p.passenger_type || '—')}</td>
            <td style="padding:5px 8px;">${escapeHtml(p.boarding_point || '')} → ${escapeHtml(p.destination || '')}</td>
            <td style="padding:5px 8px;text-align:right;">₱${(parseFloat(p.fare) || 0).toFixed(2)}</td>
        </tr>`;
        });

        if (passengers.length > 20) {
            previewHtml += `<tr><td colspan="5" style="padding:6px;text-align:center;color:#999;">... and ${passengers.length - 20} more</td></tr>`;
        }

        previewHtml += `</tbody></table></div>`;

        const result = await Swal.fire({
            title: 'Preview & Confirm Save',
            html: previewHtml,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-lg"></i> Save to Database',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#00a651',
            cancelButtonColor: '#6b7280',
            width: 700,
            padding: '20px'
        });

        if (!result.isConfirmed) return;

        this.disabled = true;
        this.textContent = 'Saving...';

        try {
            const res = await fetch('/admin/api/ocr_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const resData = await res.json();
            const el = document.getElementById('saveResult');
            el.style.display = 'block';
            if (resData.success) {
                el.style.background = '#e8f5e9';
                el.style.color = '#2e7d32';
                el.style.border = '1px solid #b7e4c7';
                el.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + resData.message;
            } else {
                el.style.background = '#fce4ec';
                el.style.color = '#c62828';
                el.style.border = '1px solid #f48fb1';
                el.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> ' + (resData.message || 'Save failed');
            }
        } catch (err) {
            const el = document.getElementById('saveResult');
            el.style.display = 'block';
            el.style.background = '#fce4ec';
            el.style.color = '#c62828';
            el.style.border = '1px solid #f48fb1';
            el.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> Network error';
        }

        this.disabled = false;
        this.textContent = 'Save to Database';
    });

    document.getElementById('resetBtn').addEventListener('click', () => {
        document.getElementById('resultsSection').style.display = 'none';
        document.getElementById('saveResult').style.display = 'none';
        document.getElementById('previewArea').style.display = 'none';
        document.getElementById('manifestImage').value = '';
        document.getElementById('fileName').textContent = '';
        window._ocrData = null;
    });
</script>

</body>
</html>

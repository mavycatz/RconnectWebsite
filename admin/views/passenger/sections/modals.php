<?php
declare(strict_types = 1);

use App\Core\Database;

if (!isset($adminUrl) || !is_callable($adminUrl)) {
    return;
}

$gsBaseUrl = $adminUrl('passenger-trip-sheet');
$gsToday = date('Y-m-d');

$buses = [];
$drivers = [];
try {
    $db = Database::get();
    $buses = $db->query("SELECT bus_id, driver, route_name FROM buses WHERE COALESCE(status,'') != 'Archived' ORDER BY bus_id ASC")
    ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $drivers = $db->query("SELECT driver_id, full_name FROM drivers WHERE COALESCE(status,'') = 'Active' AND COALESCE(approval_status,'') = 'approved' ORDER BY full_name ASC")
    ->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) {
    $buses = [];
    $drivers = [];
}
?>
<div class="modal fade" id="tripSheetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 overflow-hidden"
     style="border-radius:24px; background:#fef8f3; box-shadow:0 18px 50px rgba(0,0,0,.08);">

            <div class="row g-0">

                <div class="col-lg-4 d-none d-lg-block p-4"
     style="background:#f9f4da; border-right:1px solid #e7e1c9; min-height:360px;">
                    <div class="h-100 d-flex flex-column justify-content-center text-center">
                        <div style="width:72px;height:72px;border-radius:50%;background:#ffffff;border:1px solid #cdd9b5;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                            <i class="bi bi-printer-fill" style="color:#57694d; font-size:1.8rem;"></i>
                        </div>
                        <h2 style="font-size:1.4rem;font-weight:700;color:#2e4836;margin-bottom:8px;">Generate Trip Sheet</h2>
                        <p style="font-size:.85rem;color:#57694d;line-height:1.7;margin-bottom:0;padding:0 10px;">
            Fill in the trip details to pre-print them on the offline A4 passenger manifest.
                        </p>
                    </div>
                </div>

                <div class="col-lg-8 col-12">

                    <div class="modal-header border-0 px-4 pt-4 pb-0">
                        <h4 style="margin:0;color:#2e4836;font-weight:700;font-size:1.1rem;">
                            <i class="bi bi-journal-text me-2" style="color:#57694d;"></i>Trip Sheet Details
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body px-4 py-3">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Route</label>
                                <select id="gsRoute" class="form-select"
                        style="height:50px;border-radius:14px;border:1.5px solid #cdd9b5;background:#ffffff;color:#57694d;font-weight:600;">
                                    <option value="Rizal-Cabanatuan" selected>Rizal → Cabanatuan</option>
                                    <option value="Cabanatuan-Rizal">Cabanatuan → Rizal</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Trip Date</label>
                                <input type="date" id="gsTripDate" class="form-control" value="<?= htmlspecialchars($gsToday, ENT_QUOTES, 'UTF-8') ?>"
                       style="height:50px;border-radius:14px;border:1.5px solid #cdd9b5;background:#ffffff;color:#57694d;font-weight:600;">
</div>
<div class="col-md-6">
    <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Bus No.</label>
    <select id="gsBusNo" class="form-select" onchange="gsAutoFillDriver(this.value)"
                        style="height:50px;border-radius:14px;border:1.5px solid #cdd9b5;background:#ffffff;color:#57694d;font-weight:600;">
        <option value="">Select bus</option>
                    <?php foreach ($buses as $b): ?>
                        <option value="<?= htmlspecialchars((string)$b['bus_id'], ENT_QUOTES, 'UTF-8') ?>"
                            data-driver="<?= htmlspecialchars((string)($b['driver'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string)$b['bus_id'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                </div>
                <div class="col-md-6">
                    <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Driver</label>
                    <select id="gsDriver" class="form-select"
                        style="height:50px;border-radius:14px;border:1.5px solid #cdd9b5;background:#ffffff;color:#57694d;font-weight:600;">
                        <option value="">Select driver</option>
                    <?php foreach ($drivers as $d): ?>
                        <option value="<?= htmlspecialchars((string)$d['full_name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string)$d['full_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                </div>
                <div class="col-md-6">
                    <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Trip No. (optional)</label>
                    <input type="text" id="gsTripNo" class="form-control" placeholder="Optional — leave blank to auto-generate"
                       style="height:50px;border-radius:14px;border:1.5px solid #cdd9b5;background:#ffffff;color:#57694d;font-weight:600;">
                </div>
                <div class="col-md-6">
                    <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Rows (blank)</label>
                    <input type="number" id="gsRows" class="form-control" min="1" max="60" value="30"
                       style="height:50px;border-radius:14px;border:1.5px solid #cdd9b5;background:#ffffff;color:#57694d;font-weight:600;">
                </div>
                </div>

                </div>

                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn" data-bs-dismiss="modal"
                style="height:48px;border-radius:14px;background:#ffffff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;flex:1;">Cancel</button>
                        <button type="button" class="btn text-white" onclick="generateTripSheet()"
                style="height:48px;border-radius:14px;border:none;background:#57694d;font-weight:600;flex:1;font-size:.9rem;">
                            <i class="bi bi-printer-fill me-1"></i>Generate Sheet
                        </button>
                    </div>

                </div>

                </div>
                </div>
                </div>
                </div>

                <script>
function openTripSheetModal() {
    var m = document.getElementById('tripSheetModal');
    if (m) {
        var modal = new bootstrap.Modal(m);
        modal.show();
    }
}

function gsAutoFillDriver(busId) {
    var sel = document.getElementById('gsBusNo');
    var opt = null;
    if (sel && sel.options) {
        for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === busId) { opt = sel.options[i]; break; }
        }
    }
    var assigned = opt ? (opt.getAttribute('data-driver') || '').trim() : '';
    var driverSel = document.getElementById('gsDriver');
    if (!driverSel) return;
    if (!assigned) { driverSel.value = ''; return; }
    var found = false;
    for (var j = 0; j < driverSel.options.length; j++) {
        if (driverSel.options[j].value.toLowerCase() === assigned.toLowerCase()) {
            driverSel.value = driverSel.options[j].value;
            found = true;
            break;
        }
    }
    if (!found) driverSel.value = '';
}

function generateTripSheet() {
    var base = <?= json_encode($gsBaseUrl) ?>;
    var params = [];
    var route = (document.getElementById('gsRoute').value || '').trim();
    var date = (document.getElementById('gsTripDate').value || '').trim();
    var bus = (document.getElementById('gsBusNo').value || '').trim();
    var driver = (document.getElementById('gsDriver').value || '').trim();
    var tripNo = (document.getElementById('gsTripNo').value || '').trim();
    var rows = (document.getElementById('gsRows').value || '').trim();

    if (route) params.push('trip_route=' + encodeURIComponent(route));
    if (date) params.push('trip_date=' + encodeURIComponent(date));
    if (bus) params.push('bus_number=' + encodeURIComponent(bus));
    if (driver) params.push('driver=' + encodeURIComponent(driver));
    if (tripNo) params.push('trip_number=' + encodeURIComponent(tripNo));
    if (rows && !isNaN(rows)) params.push('rows=' + parseInt(rows, 10));

    var url = base + (params.length ? '?' + params.join('&') : '');
    var m = document.getElementById('tripSheetModal');
    if (m) {
        var modal = bootstrap.Modal.getInstance(m);
        if (modal) modal.hide();
    }
    window.location.href = url;
}
</script>

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
$passengerPageTitle = 'Baggage Management';
?>
<?php require ROOT_PATH . '/admin/views/passenger/layout/header.php'; ?>
<?php require ROOT_PATH . '/admin/views/passenger/layout/styles.php'; ?>

<body class="passenger-management-page">

<?php require __DIR__ . '/layout/navbar.php'; ?>

<style>
    @layer passenger-legacy {
    .bag-wrap { font-family:'Poppins', sans-serif; color:#2e4836; }
    .bag-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; margin-bottom:14px; }
    .bag-head h1 { font-size:1.4rem; font-weight:800; color:#295738; margin:0 0 2px; }
    .bag-head p { margin:0; font-size:.82rem; color:#8a9a8a; font-weight:500; }
    .bag-count { display:inline-flex; align-items:center; gap:6px; background:#f4f1e6; border:1px solid #e5e0a4; border-radius:20px; padding:7px 14px; font-size:.72rem; font-weight:600; color:#6b7a63; }
    .bag-btn { display:inline-flex; align-items:center; gap:6px; border-radius:20px; font-size:.78rem; font-weight:600; padding:8px 16px; cursor:pointer; border:1px solid #d8d2b6; background:#fff; color:#295738; text-decoration:none; }
    .bag-btn:hover { background:#f4f1e6; }
    .bag-btn-primary { background:#295738; border-color:#295738; color:#fff; }
    .bag-btn-primary:hover { background:#1e4229; }
    .bag-filters { display:flex; gap:8px; flex-wrap:wrap; align-items:center; background:#fff; border:1px solid #e5e0a4; border-radius:16px; padding:10px 12px; margin-bottom:12px; }
    .bag-filters input, .bag-filters select { border:1px solid #e5e0a4; border-radius:10px; padding:7px 10px; font-size:.76rem; color:#2e4836; background:#fffdf8; outline:none; }
    .bag-filters input[type="text"] { min-width:200px; }
    .bag-note { margin-bottom:10px; border-radius:10px; padding:8px 12px; font-size:.74rem; background:#f6f8ec; border:1px solid #e5e0a4; color:#7a6f12; }
    .bag-note.error { background:#fbe9e7; border-color:#eccac5; color:#b94a48; }
    .bag-card { background:#fffdf8; border:1px solid #e5e0a4; border-radius:16px; overflow:hidden; }
    .bag-scroll { overflow-x:auto; }
    table.bag-table { width:100%; border-collapse:collapse; font-size:.74rem; min-width:1000px; }
    .bag-table th { text-align:left; padding:9px 10px; background:#f6f8ec; color:#2e4836; font-size:.62rem; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; border-bottom:1px solid #e5e0a4; }
    .bag-table td { padding:8px 10px; border-bottom:1px solid #f0ede0; color:#3a4a3c; vertical-align:top; }
    .bag-badge { display:inline-flex; align-items:center; gap:4px; border-radius:20px; padding:3px 9px; font-size:.62rem; font-weight:700; border:1px solid; white-space:nowrap; }
    .bag-badge.paid { color:#2e7d32; background:#eaf6ea; border-color:#c7e3c7; }
    .bag-badge.pending { color:#9a6b12; background:#fdf5e3; border-color:#ecd9ab; }
    .bag-badge.other { color:#5b6472; background:#eef1f5; border-color:#d7dde5; }
    .bag-badge.rejected { color:#b94a48; background:#fbe9e7; border-color:#eccac5; }
    .bag-state { text-align:center; padding:44px 18px; color:#8a9a8a; font-size:.82rem; }
    .bag-state i { display:block; font-size:2rem; color:#cdd9b5; margin-bottom:8px; }
    .bag-pag { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; padding:12px 14px; font-size:.72rem; color:#8a9a8a; }
    .bag-pag button { border:1px solid #e5e0a4; background:#fff; color:#295738; border-radius:10px; padding:6px 12px; font-size:.72rem; font-weight:700; cursor:pointer; }
    .bag-pag button:disabled { opacity:.45; cursor:not-allowed; }
    .bag-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px 14px; }
    .bag-field { display:flex; flex-direction:column; gap:4px; }
    .bag-field.full { grid-column:1 / -1; }
    .bag-field label { font-size:.62rem; font-weight:700; color:#8a9a8a; text-transform:uppercase; letter-spacing:.05em; }
    .bag-field input, .bag-field select { border:1px solid #e5e0a4; border-radius:10px; padding:8px 10px; font-size:.78rem; background:#fffdf8; color:#2e4836; outline:none; width:100%; }
    .bag-detail-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:8px 18px; font-size:.76rem; }
    .bag-detail-grid .k { font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:#8a9a8a; }
    .bag-detail-grid .v { font-weight:600; color:#2e4836; overflow-wrap:anywhere; }
    @media (max-width:575px) { .bag-form-grid, .bag-detail-grid { grid-template-columns:1fr; } }
    }
</style>

<div class="main-content" id="mainContent">
    <div class="rmg-shell">
        <section class="bag-wrap rmg">

            <header class="bag-head rmg-head">
                <div>
                    <h1>Baggage Management</h1>
                    <p>Cargo and luggage records (boxes, cartons, sacks, and other permitted items) linked to their passenger records and trips.</p>
                </div>
                <div class="rmg-head-actions">
                    <span class="bag-count rmg-count" id="bagCount"><i class="bi bi-box-seam"></i> —</span>
                    <a class="bag-btn" href="<?= $adminUrl('passenger') ?>"><i class="bi bi-arrow-left"></i> Passenger Management</a>
                    <button type="button" class="bag-btn bag-btn-primary" id="bagAddBtn"><i class="bi bi-plus-lg"></i> Add baggage</button>
                </div>
            </header>

            <div class="bag-filters rmg-filters">
                <div class="rmg-search"><i class="bi bi-search"></i><input type="text" id="bagSearch" placeholder="Search description, trip, bus, driver..."></div>
                <div class="rmg-filter rmg-date-filter"><label>Registered</label><div class="rmg-date-range">
                    <span class="rmg-date-wrap"><input type="date" id="bagDateFrom" aria-label="From date"></span>
                    <span class="rmg-date-sep">–</span>
                    <span class="rmg-date-wrap"><input type="date" id="bagDateTo" aria-label="To date"></span>
                </div></div>
                <div class="rmg-filter"><label for="bagTrip">Trip</label><input type="text" id="bagTrip" placeholder="Trip no." style="max-width:100px;"></div>
                <div class="rmg-filter"><label for="bagCategory">Category</label><select id="bagCategory"><option value="">All categories</option></select></div>
                <div class="rmg-filter"><label for="bagPayment">Payment</label>
                <select id="bagPayment">
                    <option value="">All payment statuses</option>
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="rejected">Rejected</option>
                    <option value="Other">Other (unpaid cargo)</option>
                </select>
                </div>
                <div class="rmg-filter"><label for="bagPerPage">Per page</label>
                    <select id="bagPerPage">
                        <option value="15" selected>15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <button type="button" class="rmg-btn rmg-btn-sm rmg-btn-clear" id="bagClear"><i class="bi bi-x-circle"></i> Clear filters</button>
            </div>

            <div id="bagNote" class="bag-note" style="display:none;"></div>

            <div class="rmg-list">
                <div class="bag-scroll rmg-table-wrap">
                    <table class="bag-table rmg-table">
                        <thead>
                            <tr>
                                <th>Baggage ID</th>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Passenger / Record</th>
                                <th>Trip · Bus · Driver</th>
                                <th>Route</th>
                                <th>Spaces</th>
                                <th>Fare</th>
                                <th>Payment</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="bagBody">
                            <tr><td colspan="11"><div class="bag-state"><i class="bi bi-hourglass-split"></i>Loading baggage records...</div></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="bag-pag rmg-pagination" id="bagPag"></div>
            </div>

        </section>
    </div>
</div>

<div class="modal fade" id="bagDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border:1px solid #e5e0a4;border-radius:20px;background:#fffdf8;">
            <div class="modal-header" style="border-bottom:1px solid #f0ede0;padding:14px 20px;background:#fef8f3;border-radius:18px 18px 0 0;">
                <div>
                    <h5 class="modal-title" id="bagDetailTitle" style="font-size:.95rem;font-weight:700;color:#295738;">Baggage record</h5>
                    <div id="bagDetailSub" style="font-size:.72rem;color:#8a9a8a;">—</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:16px 20px;" id="bagDetailBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="bagFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border:1px solid #e5e0a4;border-radius:20px;background:#fffdf8;">
            <div class="modal-header" style="border-bottom:1px solid #f0ede0;padding:14px 20px;background:#fef8f3;border-radius:18px 18px 0 0;">
                <h5 class="modal-title" id="bagFormTitle" style="font-size:.95rem;font-weight:700;color:#295738;">Add baggage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:16px 20px;">
                <div class="bag-form-grid">
                    <div class="bag-field full"><label>Item description *</label><input type="text" id="bagFDescription" placeholder="e.g. Isang kahon ng machiato"></div>
                    <div class="bag-field"><label>Category</label><select id="bagFCategory"></select></div>
                    <div class="bag-field"><label>Quantity (occupied spaces) *</label><input type="number" id="bagFSpaces" min="1" max="50" value="1"></div>
                    <div class="bag-field full"><label>Route name (as used in the fare table) *</label><input type="text" id="bagFRoute" placeholder="e.g. Cabanatuan-Rizal"></div>
                    <div class="bag-field"><label>Boarding point *</label><input type="text" id="bagFBoarding"></div>
                    <div class="bag-field"><label>Destination *</label><input type="text" id="bagFDestination"></div>
                    <div class="bag-field"><label>Trip no.</label><input type="text" id="bagFTrip"></div>
                    <div class="bag-field"><label>Bus</label><input type="text" id="bagFBus"></div>
                    <div class="bag-field"><label>Driver ID</label><input type="text" id="bagFDriver"></div>
                </div>
                <div class="bag-note" id="bagFormError" style="display:none;margin-top:12px;"></div>
                <div class="bag-note" style="margin-top:12px;">The fare is computed with the existing rule: the route's regular segment fare for boarding → destination, multiplied by the occupied spaces.</div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0ede0;padding:12px 20px;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="bag-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="bag-btn bag-btn-primary" id="bagFSave"><i class="bi bi-check2"></i> Save baggage</button>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/admin/views/passenger/layout/scripts.php'; ?>

<?php require __DIR__ . '/../layout/custom-alert.php'; ?>
<script src="/public/login/custom-alert.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/passenger-baggage.js?v=<?= time() ?>"></script>

</body>
</html>

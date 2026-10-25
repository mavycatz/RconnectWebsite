<header class="rmg-head">

    <div>
        <h1 id="passengerDirectoryTitle">Passenger Management</h1>
        <p>
            Manage passenger accounts, registrations, boarding activity, and passenger records.
        </p>
    </div>

    <div class="rmg-head-actions">
        <span class="rmg-count" id="psgrCount"><i class="bi bi-people"></i> —</span>
        <button type="button" class="rmg-btn" id="archivedBtn" onclick="showArchived()"><i class="bi bi-archive"></i> Archived</button>
        <button type="button" class="rmg-btn" onclick="openDiscounts()"><i class="bi bi-tags"></i> Discounts</button>
        <button type="button" class="rmg-btn" onclick="loadPassengers()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        <button type="button" class="rmg-btn" onclick="exportPassengers()"><i class="bi bi-filetype-csv"></i> Export CSV</button>
        <div class="psgr-dropdown">
            <button type="button" class="rmg-btn" onclick="toggleImportTripsMenu()"><i class="bi bi-upload"></i> Import Trips <i class="bi bi-chevron-down"></i></button>
            <div class="psgr-dropdown-menu" id="menuImportTrips">
                <button type="button" class="psgr-dropdown-item" onclick="openImportModal()"><i class="bi bi-filetype-csv"></i> CSV Trip History</button>
                <button type="button" class="psgr-dropdown-item" onclick="openManifestImport()"><i class="bi bi-file-earmark-image"></i> Manifest Sheet</button>
            </div>
        </div>
        <a class="rmg-btn" href="<?= $adminUrl('passenger-baggage') ?>">
            <i class="bi bi-box-seam"></i> Baggage
        </a>
        <button type="button" class="rmg-btn rmg-btn-primary" onclick="openTripSheetModal()">
            <i class="bi bi-printer-fill"></i> Generate Trip Sheet
        </button>
    </div>

</header>

<style>
    @layer passenger-legacy {
    .psgr-stat-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; margin-bottom:10px; }
    @media (max-width: 991px) { .psgr-stat-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575px) { .psgr-stat-grid { grid-template-columns:1fr; } }
    .psgr-stat-card { position:relative; display:flex; justify-content:space-between; align-items:center; gap:10px; background:#fef8f3; border:1px solid #cdd9b5; border-radius:16px; padding:12px 14px; cursor:pointer; transition:box-shadow .15s, transform .15s, border-color .15s; overflow:hidden; }
    .psgr-stat-card:hover { border-color:#57694d; box-shadow:0 8px 20px rgba(46,72,54,.10); transform:translateY(-1px); }
    .psgr-stat-card:focus-visible { outline:3px solid rgba(87,105,77,.28); outline-offset:1px; }
    .psgr-stat-card.active { border-color:#2e4836; box-shadow:0 0 0 3px rgba(87,105,77,.18); background:#fff; }
    .psgr-stat-accent { position:absolute; top:0; left:0; width:4px; height:100%; }
    .psgr-stat-label { font-size:.68rem; font-weight:700; letter-spacing:.4px; color:#6c757d; text-transform:uppercase; }
    .psgr-stat-value { font-size:1.5rem; font-weight:800; color:#295738; line-height:1.1; margin:3px 0 1px; }
    .psgr-stat-sub { font-size:.66rem; color:#8a9a8a; }
    .psgr-stat-icon { width:46px; height:46px; border-radius:12px; background:#f9f4da; border:1px solid #e7e1c9; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .psgr-stat-icon i { font-size:1.3rem; color:#295738; }
    .psgr-mini-stats { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
    .psgr-mini { display:inline-flex; align-items:center; gap:7px; background:#faf9e5; border:1px solid #e0dcc0; border-radius:999px; padding:5px 13px; font-size:.72rem; color:#57694d; font-weight:600; }
    .psgr-mini b { color:#295738; font-weight:800; }
    .psgr-mini.clickable { cursor:pointer; transition:background .15s, border-color .15s; }
    .psgr-mini.clickable:hover { background:#f0edd0; border-color:#cdd9b5; }
    .psgr-mini.active { background:#57694d; border-color:#57694d; color:#fef8f3; }
    .psgr-mini.active b { color:#fef8f3; }
    }
</style>

<div class="psgr-stat-grid rmg-summary">
    <div class="psgr-stat-card rmg-sum-card" id="statCardTotal" data-filter="total" role="button" tabindex="0"
         onclick="psgrApplyStatFilter('total')"
         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();psgrApplyStatFilter('total');}">
        <div class="psgr-stat-accent" style="background:#81c784;"></div>
        <div>
            <div class="psgr-stat-label rmg-sum-label">Total Passengers</div>
            <div class="psgr-stat-value rmg-sum-value" id="totalPassengers">&mdash;</div>
            <div class="psgr-stat-sub">Registered passengers</div>
        </div>
        <div class="psgr-stat-icon rmg-sum-icon"><i class="bi bi-people-fill"></i></div>
    </div>

    <div class="psgr-stat-card rmg-sum-card tone-pending" id="statCardPending" data-filter="pending_verification" role="button" tabindex="0"
         onclick="psgrApplyStatFilter('pending_verification')"
         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();psgrApplyStatFilter('pending_verification');}">
        <div class="psgr-stat-accent" style="background:#cdb56a;"></div>
        <div>
            <div class="psgr-stat-label rmg-sum-label">Pending Verification</div>
            <div class="psgr-stat-value rmg-sum-value" id="pendingCount">&mdash;</div>
            <div class="psgr-stat-sub">Awaiting approval</div>
        </div>
        <div class="psgr-stat-icon rmg-sum-icon"><i class="bi bi-hourglass-split"></i></div>
    </div>

    <div class="psgr-stat-card rmg-sum-card tone-active" id="statCardVerified" data-filter="verified" role="button" tabindex="0"
         onclick="psgrApplyStatFilter('verified')"
         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();psgrApplyStatFilter('verified');}">
        <div class="psgr-stat-accent" style="background:#7fa8d9;"></div>
        <div>
            <div class="psgr-stat-label rmg-sum-label">Verified Passengers</div>
            <div class="psgr-stat-value rmg-sum-value" id="verifiedCount">&mdash;</div>
            <div class="psgr-stat-sub">Discount verification approved</div>
        </div>
        <div class="psgr-stat-icon rmg-sum-icon"><i class="bi bi-patch-check-fill"></i></div>
    </div>

    <div class="psgr-stat-card rmg-sum-card" id="statCardBoarded" data-filter="boarded_today" role="button" tabindex="0"
         onclick="psgrApplyStatFilter('boarded_today')"
         onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();psgrApplyStatFilter('boarded_today');}">
        <div class="psgr-stat-accent" style="background:#6f9e4f;"></div>
        <div>
            <div class="psgr-stat-label rmg-sum-label">Boarded Today</div>
            <div class="psgr-stat-value rmg-sum-value" id="boardedToday">&mdash;</div>
            <div class="psgr-stat-sub">Passengers boarded today</div>
        </div>
        <div class="psgr-stat-icon rmg-sum-icon"><i class="bi bi-bus-front"></i></div>
    </div>
</div>

<div class="psgr-mini-stats">
    <button type="button" class="psgr-mini clickable" data-filter="active" onclick="psgrApplyStatFilter('active')"><i class="bi bi-person-check-fill"></i> Active <b id="activeCount">&mdash;</b></button>
    <button type="button" class="psgr-mini clickable" data-filter="new_today" onclick="psgrApplyStatFilter('new_today')"><i class="bi bi-person-plus-fill"></i> New Today <b id="newToday">&mdash;</b></button>
    <button type="button" class="psgr-mini clickable" data-filter="trip_records" onclick="psgrApplyStatFilter('trip_records')"><i class="bi bi-journal-text"></i> Trip Records <b id="tripRecordsCount">&mdash;</b></button>
    <button type="button" class="psgr-mini clickable" data-filter="blocked" onclick="psgrApplyStatFilter('blocked')"><i class="bi bi-shield-exclamation"></i> Blocked <b id="blockedCount">&mdash;</b></button>
    <button type="button" class="psgr-mini clickable" data-filter="archived" onclick="psgrApplyStatFilter('archived')"><i class="bi bi-archive"></i> Archived <b id="archivedCount">&mdash;</b></button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        fetch('/admin/api/Passenger/getPassengerStats.php?action=getStats')
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (!result.success || !result.data) return;
            var d = result.data;
            function set(id, value) {
                var el = document.getElementById(id);
                if (el) { el.textContent = Number(value || 0).toLocaleString('en-PH'); }
            }
            set('totalPassengers', d.total_passengers ?? 0);
            set('pendingCount', d.pending_verification ?? d.pending ?? 0);
            set('verifiedCount', d.verified ?? 0);
            set('boardedToday', d.boarded_today ?? 0);
            set('activeCount', d.active ?? 0);
            set('newToday', d.new_today ?? 0);
            set('tripRecordsCount', d.trip_records ?? 0);
            set('blockedCount', d.blocked ?? 0);
            set('archivedCount', d.archived ?? 0);
            if (typeof psgrUpdateStatCards === 'function') psgrUpdateStatCards();
        })
        .catch(function (err) { console.error('Passenger stats error:', err); });
    });
</script>

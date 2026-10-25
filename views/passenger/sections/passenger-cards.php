<style>
    .psgr * { font-family: 'Inter', 'Poppins', sans-serif; }
    .psgr-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; }
    .psgr-header h2 { font-size: 1.4rem; font-weight: 800; color: #2e4836; margin: 0; }
    .psgr-header h2 i { color: #57694d; margin-right: 8px; }
    .psgr-header-right { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .psgr-filterbar { margin-bottom: 14px; }
    .psgr-search { display: flex; flex-wrap: wrap; gap: 8px; padding: 14px 16px; background: #fef8f3; border: 1px solid #cdd9b5; border-radius: 14px; align-items: center; }
    .psgr-search input, .psgr-search select, .psgr-more-filters input, .psgr-more-filters select { height: 42px; border-radius: 12px; border: 1.5px solid #cdd9b5; font-size: 0.85rem; padding: 0 14px; background: #fff; color: #2e4836; outline: none; transition: border 0.2s; }
    .psgr-search input:focus, .psgr-search select:focus, .psgr-more-filters input:focus, .psgr-more-filters select:focus { border-color: #57694d; box-shadow: 0 0 0 3px rgba(87, 105, 77, .12); }
    .psgr-search input { min-width: 160px; flex: 1 1 200px; }
    .psgr-search select { flex: 0 0 auto; min-width: 150px; max-width: 100%; }
    .psgr-search input[type="date"] { min-width: 120px; width: 140px; }
    .psgr-search input::placeholder { color: #a0b898; }
    .psgr-more-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; margin-top: 8px; background: #fff; border: 1.5px dashed #cdd9b5; border-radius: 14px; }
    .psgr-more-filters input[type="date"] { width: 150px; }
    .psgr-more-filters input[type="number"] { width: 160px; }
    .psgr-filter-count { display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: #57694d; color: #fef8f3; font-size: 0.65rem; font-weight: 700; }
    .psgr-quick { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; }
    .psgr-quick-label { font-size: 0.72rem; font-weight: 700; color: #8aa09a; text-transform: uppercase; letter-spacing: 0.5px; margin-right: 2px; }
    .psgr-quick-btn { border: 1.5px solid #cdd9b5; background: #fff; color: #57694d; border-radius: 999px; font-size: 0.76rem; font-weight: 600; padding: 6px 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s; }
    .psgr-quick-btn:hover { background: #f9f4da; border-color: #57694d; }
    .psgr-quick-btn.active { background: #57694d; border-color: #57694d; color: #fef8f3; }
    .psgr-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .psgr-chip { display: inline-flex; align-items: center; gap: 6px; background: #eef3e8; border: 1px solid #cdd9b5; color: #2e4836; border-radius: 999px; font-size: 0.74rem; font-weight: 600; padding: 4px 6px 4px 12px; }
    .psgr-chip button { border: none; background: none; color: #7b8a74; cursor: pointer; font-size: 1rem; line-height: 1; padding: 0 4px; }
    .psgr-chip button:hover { color: #c62828; }
    .psgr-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .psgr-table-scroll .psgr-table { min-width: 1150px; }
    .psgr-table th, .psgr-table td { white-space: nowrap; }
    .psgr-table th.psgr-col-name, .psgr-table td.psgr-col-name { white-space: normal; }
    .psgr-name { font-weight: 700; color: #2e4836; font-size: 0.88rem; line-height: 1.3; overflow-wrap: anywhere; }
    .psgr-email { font-size: 0.72rem; color: #8aa09a; margin-top: 2px; max-width: 230px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .psgr-cell-id { font-weight: 700; color: #2e4836; font-size: 0.86rem; }
    .psgr-cell-trips { font-weight: 700; color: #2e4836; font-size: 0.86rem; text-align: center; }
    .psgr-cell-date { color: #57694d; font-size: 0.8rem; }
    .psgr-cell-contact { color: #57694d; }
    .psgr-act-badge { display: inline-block; font-size: 0.7rem; padding: 3px 9px; border-radius: 8px; font-weight: 600; white-space: nowrap; }
    .psgr-money-due { font-weight: 700; color: #c62828; white-space: nowrap; }
    .psgr-money-zero { font-weight: 600; color: #198754; white-space: nowrap; }
    .psgr-money-na { color: #8a9a8a; font-style: italic; font-weight: 600; white-space: nowrap; }
    .psgr-pag-info { display: flex; flex-direction: column; gap: 2px; }
    .psgr-pag-page { font-weight: 700; color: #2e4836; font-size: 0.8rem; }
    .psgr-pag-range { color: #8a9a8a; font-size: 0.74rem; }
    .psgr-verify { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; white-space: nowrap; }
    .psgr-verify-verified { background: #e8f0e0; color: #2e4836; }
    .psgr-verify-pending { background: #f9f4da; color: #8a7a3a; }
    .psgr-verify-rejected { background: #fbe8e8; color: #b34040; }
    .psgr-verify-expired { background: #eef0ec; color: #7a8a7a; }
    .psgr-btn { height: 44px; border-radius: 12px; border: none; font-weight: 600; font-size: 0.82rem; padding: 0 18px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s; }
    .psgr-btn-primary { background: #57694d; color: #fef8f3; }
    .psgr-btn-primary:hover { background: #2e4836; }
    .psgr-btn-outline { background: #f9f4da; border: 1.5px solid #cdd9b5; color: #2e4836; }
    .psgr-btn-outline:hover { background: #cdd9b5; border-color: #57694d; }
    .psgr-btn-sm { height: 38px; font-size: 0.78rem; padding: 0 14px; border-radius: 10px; }
    .status-opt { cursor: pointer; background: #fff; border: 1.5px solid #e8eee8; border-radius: 14px; padding: 11px 14px; display: flex; align-items: center; gap: 12px; transition: all .15s; }
    .status-opt:hover { border-color: #cdd9b5; }
    .status-opt.selected { border-color: #2e7d32; background: #f0f7f0; box-shadow: 0 2px 8px rgba(46, 125, 50, .08); }
    .status-opt-check { width: 22px; height: 22px; border-radius: 50%; border: 1.5px solid #cdd9b5; background: #fff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: .7rem; flex-shrink: 0; }
    .status-opt-check i { opacity: 0; }
    .status-opt.selected .status-opt-check { background: #2e7d32; border-color: #2e7d32; }
    .status-opt.selected .status-opt-check i { opacity: 1; }
    .edit-input-wrap { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #cdd9b5; border-radius: 14px; padding: 0 14px; transition: border-color .15s, box-shadow .15s; }
    .edit-input-wrap:focus-within { border-color: #2e7d32; box-shadow: 0 0 0 3px rgba(46, 125, 50, .12); }
    .edit-input-wrap.err { border-color: #c62828; }
    .edit-input-wrap.err:focus-within { box-shadow: 0 0 0 3px rgba(198, 40, 40, .12); }
    .edit-input-wrap>i { color: #8aa09a; font-size: .9rem; flex-shrink: 0; }
    .edit-input-wrap input, .edit-input-wrap select { flex: 1; border: none; outline: none; background: transparent; height: 48px; font-size: .9rem; color: #2e4836; box-shadow: none!important; min-width: 0; }
    .edit-input-wrap select { cursor: pointer; padding-right: 6px; }
    .edit-err { display: none; color: #c62828; font-size: .72rem; margin-top: 5px; }
    .psgr-card { background: #fff; border-radius: 18px; border: 1px solid #cdd9b5; box-shadow: 0 3px 14px rgba(0, 0, 0, .05); overflow: hidden; }
    .psgr-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    .psgr-table thead { background: #f9f4da; border-bottom: 2px solid #cdd9b5; }
    .psgr-table thead th { padding: 14px 12px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.6px; color: #57694d; text-align: left; cursor: pointer; user-select: none; white-space: nowrap; }
    .psgr-table thead th:hover { color: #2e4836; }
    .psgr-table thead th i { font-size: 0.65rem; margin-left: 2px; opacity: 0.5; }
    .psgr-table tbody tr { border-bottom: 1px solid #eef3e8; transition: background 0.15s; }
    .psgr-table tbody tr:last-child { border-bottom: none; }
    .psgr-table tbody tr:hover { background: #f9fbf5; }
    .psgr-table tbody td { padding: 12px; vertical-align: middle; }
    .psgr-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; }
    .psgr-badge::before { content: ''; width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
    .psgr-badge-active { background: #e8f0e0; color: #2e4836; }
    .psgr-badge-active::before { background: #57694d; }
    .psgr-badge-pending { background: #f9f4da; color: #8a7a3a; }
    .psgr-badge-pending::before { background: #cdb56a; }
    .psgr-badge-suspended { background: #fef0d5; color: #b8860b; }
    .psgr-badge-suspended::before { background: #e6a817; }
    .psgr-badge-blocked { background: #fbe8e8; color: #b34040; }
    .psgr-badge-blocked::before { background: #d46868; }
    .psgr-badge-archived { background: #eef0ec; color: #7a8a7a; }
    .psgr-badge-archived::before { background: #a0b0a0; }
    .psgr-act-btn { width: 36px; height: 36px; border-radius: 10px; border: 1px solid #cdd9b5; background: #fff; color: #57694d; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.15s; font-size: 1rem; }
    .psgr-act-btn:hover { border-color: #57694d; color: #2e4836; background: #f9f4da; }
    .psgr-pag { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-top: 1px solid #cdd9b5; background: #f9f4da; font-size: 0.85rem; color: #57694d; flex-wrap: wrap; gap: 8px; }
    .psgr-pag button { background: none; border: none; color: #2e4836; font-weight: 600; cursor: pointer; padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; }
    .psgr-pag button:hover { background: #cdd9b5; }
    .psgr-pag button:disabled { color: #a0b898; cursor: default; background: none; }
    .psgr-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 56px 20px; text-align: center; color: #8aa09a; background: #fef8f3; border: 1px solid #cdd9b5; border-radius: 18px; }
    .psgr-dropdown { position: relative; display: inline-block; }
    .psgr-dropdown-menu { position: absolute; right: 0; top: 100%; z-index: 50; background: #fff; border: 1px solid #cdd9b5; border-radius: 14px; box-shadow: 0 10px 30px rgba(0, 0, 0, .08); min-width: 210px; display: none; padding: 8px; margin-top: 6px; }
    .psgr-dropdown-menu.show { display: block; }
    .psgr-dropdown-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; font-size: 0.82rem; color: #2e4836; border-radius: 10px; cursor: pointer; border: none; background: none; width: 100%; text-align: left; }
    .psgr-dropdown-item:hover { background: #f9f4da; color: #2e4836; }
    .trip-donut { width: 84px; height: 84px; border-radius: 50%; position: relative; flex-shrink: 0; }
    .trip-donut::after { content: ''; position: absolute; inset: 13px; background: #fdfbf4; border-radius: 50%; box-shadow: inset 0 0 0 1px #ede8cd; }
    .trip-table tbody tr { border-bottom: 1px solid #f0edd0; transition: background 0.15s; }
    .trip-table tbody tr:last-child { border-bottom: none; }
    .trip-table tbody tr:hover { background: #f9fbf5; }
    .trip-filter input, .trip-filter select { height: 38px; }
    @media(max-width:767px) { .modal-dialog.modal-xl { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; } }
    .psgr-dropdown-item i { width: 18px; color: #57694d; }
    .discount-tag { display: inline-block; background: #f9f4da; color: #57694d; padding: 3px 10px; border-radius: 8px; font-size: 0.72rem; font-weight: 600; border: 1px solid #cdd9b5; }
    @media(max-width:768px) { .psgr-search input { min-width: 140px; } .psgr-table { font-size: 0.76rem; } .psgr-table thead th, .psgr-table tbody td { padding: 8px 5px; } }
    .psgr-panel { background:#fef8f3; border-left:1px solid #cdd9b5; }
    .psgr-panel-head { background:linear-gradient(135deg,#47613b,#5d8550); color:#fef8f3; padding:16px 18px; align-items:flex-start; }
    .psgr-panel-avatar { width:52px; height:52px; border-radius:50%; background:rgba(255,255,255,.18); border:2px solid rgba(255,255,255,.55); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.1rem; flex-shrink:0; }
    .psgr-panel-name { font-weight:800; font-size:1rem; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:250px; }
    .psgr-panel-meta { font-size:.72rem; opacity:.9; font-weight:600; margin-top:2px; }
    .psgr-panel-head .btn-close { filter:invert(1) grayscale(1); opacity:.85; }
    .psgr-panel-tabs { display:flex; gap:4px; padding:10px 14px 0; background:#fef8f3; border-bottom:1px solid #e7e1c9; }
    .psgr-panel-tab { border:none; background:transparent; color:#57694d; font-weight:600; font-size:.78rem; padding:8px 12px; border-radius:10px 10px 0 0; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
    .psgr-panel-tab:hover { color:#2e4836; }
    .psgr-panel-tab.active { background:#fff; border:1px solid #e7e1c9; border-bottom-color:#fff; margin-bottom:-1px; color:#2e4836; }
    .psgr-panel-body { padding:16px; }
    .psgr-panel-state { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; padding:52px 14px; color:#57694d; font-size:.82rem; text-align:center; }
    .psgr-panel-foot { display:flex; flex-wrap:wrap; gap:8px; padding:12px 16px; border-top:1px solid #e7e1c9; background:#fff; }
    .psgr-panel-card { background:#fff; border:1px solid #e7e1c9; border-radius:14px; margin-bottom:12px; overflow:hidden; }
    .psgr-panel-card-head { background:#f9f4da; padding:8px 14px; font-size:.7rem; font-weight:700; color:#57694d; text-transform:uppercase; letter-spacing:.4px; }
    .psgr-panel-row { display:flex; justify-content:space-between; gap:12px; padding:8px 14px; border-bottom:1px solid #f2efdb; font-size:.8rem; }
    .psgr-panel-row:last-child { border-bottom:none; }
    .psgr-panel-row span:first-child { color:#8a9a8a; flex-shrink:0; }
    .psgr-panel-row span:last-child { color:#2e4836; font-weight:600; text-align:right; word-break:break-word; }
    .psgr-panel-trip { display:flex; justify-content:space-between; gap:10px; padding:10px 14px; border-bottom:1px solid #f2efdb; font-size:.78rem; }
    .psgr-panel-trip:last-child { border-bottom:none; }
    .psgr-panel-note { font-size:.72rem; color:#8a9a8a; padding:2px 2px 10px; }
</style>

<div class="psgr">
    <div class="psgr-header">
        <h2><i class="bi bi-people-fill"></i>Passenger Management</h2>
        <div class="psgr-header-right">
            <button class="psgr-btn psgr-btn-outline psgr-btn-sm" id="archivedBtn" onclick="showArchived()"><i class="bi bi-archive"></i>Archived</button>
            <button class="psgr-btn psgr-btn-outline psgr-btn-sm" onclick="openDiscounts()"><i class="bi bi-tags-fill"></i>Discounts</button>
            <button class="psgr-btn psgr-btn-outline psgr-btn-sm" onclick="exportPassengers()"><i class="bi bi-download"></i>Export</button>
            <div class="psgr-dropdown">
                <button class="psgr-btn psgr-btn-outline psgr-btn-sm" onclick="toggleImportTripsMenu()" style="gap:4px;"><i class="bi bi-upload"></i>Import Trips <i class="bi bi-chevron-down" style="font-size:0.65rem;margin-left:2px;"></i></button>
                <div class="psgr-dropdown-menu" id="menuImportTrips">
                    <button class="psgr-dropdown-item" onclick="openImportModal()"><i class="bi bi-filetype-csv"></i>CSV Trip History<div style="font-size:0.68rem;color:#8aa09a;margin-top:2px;">Import trip records from CSV files</div></button>
                    <button class="psgr-dropdown-item" onclick="openManifestImport()"><i class="bi bi-file-earmark-image"></i>Manifest Sheet<div style="font-size:0.68rem;color:#8aa09a;margin-top:2px;">Import offline trip sheets via OCR (image, PDF, Excel)</div></button>
                </div>
            </div>
            <span style="font-size:0.85rem;color:#57694d;font-weight:500;" id="psgrCount"></span>
        </div>
    </div>

    <div class="psgr-filterbar">
        <div class="psgr-search">
            <input type="text" id="searchInput" placeholder="Search by ID, name, email, contact..." oninput="psgrSearchInput()">
            <select id="filterVerification" onchange="loadPassengers()"><option value="">All Verification</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
            <select id="filterPayment" onchange="loadPassengers()"><option value="">All Payments</option><option value="with_balance">With Balance Due</option><option value="fully_paid">Fully Paid</option></select>
            <select id="filterStatus" onchange="loadPassengers()"><option value="">All Status</option><option value="Active">Active</option><option value="Pending">Pending</option><option value="Suspended">Suspended</option><option value="Blocked">Blocked</option><option value="Archived">Archived</option></select>
            <button type="button" class="psgr-btn psgr-btn-outline psgr-btn-sm" id="moreFiltersBtn" onclick="psgrToggleMoreFilters()"><i class="bi bi-sliders"></i> More Filters <span class="psgr-filter-count" id="moreFiltersCount" style="display:none;">0</span></button>
            <button type="button" class="psgr-btn psgr-btn-outline psgr-btn-sm" onclick="clearFilters()"><i class="bi bi-x-circle"></i> Clear All</button>
        </div>
        <div class="psgr-more-filters" id="psgrMoreFilters" style="display:none;">
            <select id="filterDiscount" onchange="loadPassengers()"><option value="">All Passenger Types</option><option value="Regular">Regular</option><option value="Student">Student</option><option value="Senior">Senior</option><option value="PWD">PWD</option><option value="Other">Other</option></select>
            <select id="filterTripActivity" onchange="loadPassengers()"><option value="">All Trip Activity</option><option value="has">Has Trips</option><option value="none">No Trips</option></select>
            <select id="filterAccount" onchange="loadPassengers()"><option value="">All Account Types</option><option value="has_account">Has Account</option><option value="walkin">Walk-in</option><option value="no_account">No Account</option><option value="other">Other</option></select>
            <input type="date" id="filterDateFrom" onchange="loadPassengers()" title="Registered from">
            <input type="date" id="filterDateTo" onchange="loadPassengers()" title="Registered to">
            <input type="number" id="filterBalanceMin" min="0" step="0.01" placeholder="Min balance (₱)" oninput="psgrBalanceInput()">
            <input type="number" id="filterBalanceMax" min="0" step="0.01" placeholder="Max balance (₱)" oninput="psgrBalanceInput()">
        </div>
        <div class="psgr-quick">
            <span class="psgr-quick-label">Quick filters</span>
            <button type="button" class="psgr-quick-btn" data-quick="with_balance" onclick="psgrQuick('with_balance')"><i class="bi bi-cash-coin"></i> With Balance Due</button>
            <button type="button" class="psgr-quick-btn" data-quick="pending_verification" onclick="psgrQuick('pending_verification')"><i class="bi bi-patch-check"></i> Pending Verification</button>
            <button type="button" class="psgr-quick-btn" data-quick="recent" onclick="psgrQuick('recent')"><i class="bi bi-clock-history"></i> Recently Registered</button>
            <button type="button" class="psgr-quick-btn" data-quick="blocked" onclick="psgrQuick('blocked')"><i class="bi bi-shield-exclamation"></i> Blocked Accounts</button>
        </div>
        <input type="hidden" id="filterBoardedToday" value="">
        <div class="psgr-chips" id="psgrChips" style="display:none;"></div>
    </div>

    <div id="psgrState" class="psgr-state" style="display:none;"><div class="spinner-border" style="width:2.5rem;height:2.5rem;color:#57694d;"></div><p class="mt-3" style="color:#2e4836;">Loading passengers...</p></div>
    <div id="psgrEmpty" class="psgr-state" style="display:none;"><i class="bi bi-inbox" style="font-size:3rem;color:#cdd9b5;"></i><p class="mt-3" style="color:#57694d;">No passengers found.</p><button type="button" class="psgr-btn psgr-btn-outline psgr-btn-sm" onclick="clearFilters()" style="margin-top:4px;"><i class="bi bi-x-circle"></i> Clear All Filters</button></div>

    <div id="psgrTableWrap" class="psgr-card" style="display:none;">
        <div class="psgr-table-scroll">
        <table class="psgr-table">
            <colgroup>
                <col style="width:78px;">
                <col style="width:240px;">
                <col style="width:140px;">
                <col style="width:120px;">
                <col style="width:122px;">
                <col style="width:120px;">
                <col style="width:76px;">
                <col style="width:130px;">
                <col style="width:112px;">
                <col style="width:116px;">
                <col style="width:56px;">
            </colgroup>
            <thead>
                <tr>
                    <th data-sort="p.id" onclick="sortBy('p.id')">ID <i class="bi bi-arrow-down-up"></i></th>
                    <th class="psgr-col-name" data-sort="p.full_name" onclick="sortBy('p.full_name')">Passenger <i class="bi bi-arrow-down-up"></i></th>
                    <th>Contact</th>
                    <th data-sort="p.discount_type" onclick="sortBy('p.discount_type')">Type <i class="bi bi-arrow-down-up"></i></th>
                    <th data-sort="p.discount_status" onclick="sortBy('p.discount_status')">Verification <i class="bi bi-arrow-down-up"></i></th>
                    <th>Account</th>
                    <th data-sort="trip_count" onclick="sortBy('trip_count')">Trips <i class="bi bi-arrow-down-up"></i></th>
                    <th data-sort="balance" onclick="sortBy('balance')">Balance <i class="bi bi-arrow-down-up"></i></th>
                    <th data-sort="p.created_at" onclick="sortBy('p.created_at')">Joined <i class="bi bi-arrow-down-up"></i></th>
                    <th data-sort="p.status" onclick="sortBy('p.status')">Status <i class="bi bi-arrow-down-up"></i></th>
                    <th style="width:56px;"></th>
                </tr>
            </thead>
            <tbody id="psgrBody"></tbody>
        </table>
        </div>
        <div id="psgrPag" class="psgr-pag"></div>
    </div>
</div>

<div class="offcanvas offcanvas-end psgr-panel" tabindex="-1" id="psgrDetailsPanel" aria-labelledby="psgrPanelTitle" style="width:min(470px,100vw);">
    <div class="offcanvas-header psgr-panel-head">
        <div style="display:flex;align-items:center;gap:12px;min-width:0;">
            <div id="panelAvatar" class="psgr-panel-avatar">P</div>
            <div style="min-width:0;">
                <div id="psgrPanelTitle" class="psgr-panel-name">Passenger</div>
                <div id="panelMeta" class="psgr-panel-meta">&mdash;</div>
                <div id="panelBadges" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;"></div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="psgr-panel-tabs">
        <button type="button" class="psgr-panel-tab active" data-tab="profile" onclick="psgrPanelTab('profile')"><i class="bi bi-person"></i> Profile</button>
        <button type="button" class="psgr-panel-tab" data-tab="trips" onclick="psgrPanelTab('trips')"><i class="bi bi-bus-front"></i> Trips</button>
        <button type="button" class="psgr-panel-tab" data-tab="payments" onclick="psgrPanelTab('payments')"><i class="bi bi-cash-coin"></i> Payments</button>
    </div>
    <div class="offcanvas-body psgr-panel-body">
        <div id="panelState" class="psgr-panel-state" style="display:none;">
            <div class="spinner-border" style="width:2rem;height:2rem;color:#57694d;"></div>
            <p style="margin:0;">Loading passenger details...</p>
        </div>
        <div id="panelError" class="psgr-panel-state" style="display:none;">
            <i class="bi bi-exclamation-triangle" style="font-size:2rem;color:#d46868;"></i>
            <p style="margin:0;">Could not load this passenger's details.</p>
        </div>
        <div id="panelContent" style="display:none;">
            <div id="panelTabProfile"></div>
            <div id="panelTabTrips" style="display:none;"></div>
            <div id="panelTabPayments" style="display:none;"></div>
        </div>
    </div>
    <div class="psgr-panel-foot" id="panelActions"></div>
</div>

<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 overflow-hidden" style="border-radius:24px;background:#fef8f3;box-shadow:0 18px 50px rgba(0,0,0,.08);">

            <div class="row g-0">

                <div class="col-lg-4 d-none d-lg-block" style="background:linear-gradient(180deg,#f9f4da 0%,#f0edd0 100%);border-right:1px solid #e7e1c9;min-height:430px;">
                    <div class="p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div style="width:64px;height:64px;border-radius:20px;background:#ffffff;border:1px solid #cdd9b5;display:flex;align-items:center;justify-content:center;">
                                <i class="bi bi-shield-check" style="color:#57694d;font-size:1.4rem;"></i>
                            </div>
                            <h2 style="margin-top:18px;font-size:1.35rem;font-weight:700;line-height:1.3;color:#2e4836;">Update Status</h2>
                            <p style="margin-top:8px;font-size:.8rem;line-height:1.7;color:#57694d;">Change the account status and provide a reason for the update. All changes are recorded.</p>
                        </div>

                        <div class="d-grid gap-2">
                            <div style="background:#ffffff;border:1px solid #cdd9b5;border-radius:14px;padding:14px;">
                                <div class="d-flex align-items-center" style="margin-bottom:10px;">
                                    <div style="width:38px;height:38px;border-radius:11px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:10px;">
                                        <i class="bi bi-shield-exclamation" style="color:#57694d;font-size:.95rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.8rem;font-weight:700;color:#2e4836;">Status Options</div>
                                        <div style="font-size:.68rem;color:#8aa09a;">Pick one of five statuses</div>
                                    </div>
                                </div>
                                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                    <span style="padding:3px 10px;border-radius:20px;font-size:.64rem;font-weight:600;background:#e8f6ef;color:#198754;">Active</span>
                                    <span style="padding:3px 10px;border-radius:20px;font-size:.64rem;font-weight:600;background:#fff8e1;color:#f57f17;">Pending</span>
                                    <span style="padding:3px 10px;border-radius:20px;font-size:.64rem;font-weight:600;background:#fff3e0;color:#e65100;">Suspended</span>
                                    <span style="padding:3px 10px;border-radius:20px;font-size:.64rem;font-weight:600;background:#fbe9e7;color:#c62828;">Blocked</span>
                                    <span style="padding:3px 10px;border-radius:20px;font-size:.64rem;font-weight:600;background:#eeeeee;color:#616161;">Archived</span>
                                </div>
                            </div>
                            <div style="background:#ffffff;border:1px solid #cdd9b5;border-radius:14px;padding:14px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:38px;height:38px;border-radius:11px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:10px;">
                                        <i class="bi bi-clock-history" style="color:#57694d;font-size:.95rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.8rem;font-weight:700;color:#2e4836;">Audit Trail</div>
                                        <div style="font-size:.68rem;color:#8aa09a;">Every change is logged with timestamps</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-12">
                    <div class="modal-header border-0 px-4 pt-4 pb-0">
                        <h4 style="margin:0;color:#2e4836;font-weight:700;font-size:1.1rem;">
                            <i class="bi bi-shield-check me-2" style="color:#57694d;"></i>Update Status
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body px-4 py-3">
                        <input type="hidden" id="statusPid">
                        <input type="hidden" id="statusNewStatus">

                        <div style="display:flex;align-items:center;gap:12px;background:#ffffff;border:1px solid #cdd9b5;border-radius:16px;padding:13px 16px;margin-bottom:16px;">
                            <div id="statusPAvatar" style="width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#295738,#7da453);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;flex-shrink:0;">AA</div>
                            <div style="flex:1;min-width:0;">
                                <div id="statusPName" style="font-size:.95rem;font-weight:700;color:#2e4836;">Passenger</div>
                                <div id="statusPId" style="font-size:.72rem;color:#6b7d3a;font-weight:600;">Passenger ID: —</div>
                            </div>
                            <span id="statusPCurr"></span>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label style="display:block;margin-bottom:8px;font-size:.85rem;font-weight:600;color:#57694d;">New Status</label>
                            <div class="d-grid gap-2">
                                <div class="status-opt" data-status="Active" onclick="pickStatus('Active')">
                                    <div style="width:34px;height:34px;border-radius:10px;background:#f0f7f0;border:1px solid #cdd9b5;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-person-check" style="color:#2e7d32;"></i></div>
                                    <div style="flex:1;min-width:0;"><div style="font-size:.82rem;font-weight:700;color:#2e4836;">Active</div><div style="font-size:.7rem;color:#8a9a8a;">Full access to transportation services</div></div>
                                    <div class="status-opt-check"><i class="bi bi-check-lg"></i></div>
                                </div>
                                <div class="status-opt" data-status="Pending" onclick="pickStatus('Pending')">
                                    <div style="width:34px;height:34px;border-radius:10px;background:#fff8e1;border:1px solid #ffe082;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-hourglass-split" style="color:#f57f17;"></i></div>
                                    <div style="flex:1;min-width:0;"><div style="font-size:.82rem;font-weight:700;color:#2e4836;">Pending</div><div style="font-size:.7rem;color:#8a9a8a;">Account is under review</div></div>
                                    <div class="status-opt-check"><i class="bi bi-check-lg"></i></div>
                                </div>
                                <div class="status-opt" data-status="Suspended" onclick="pickStatus('Suspended')">
                                    <div style="width:34px;height:34px;border-radius:10px;background:#fff3e0;border:1px solid #ffcc80;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-pause-circle" style="color:#e65100;"></i></div>
                                    <div style="flex:1;min-width:0;"><div style="font-size:.82rem;font-weight:700;color:#2e4836;">Suspended</div><div style="font-size:.7rem;color:#8a9a8a;">Temporarily restricted access</div></div>
                                    <div class="status-opt-check"><i class="bi bi-check-lg"></i></div>
                                </div>
                                <div class="status-opt" data-status="Blocked" onclick="pickStatus('Blocked')">
                                    <div style="width:34px;height:34px;border-radius:10px;background:#fbe9e7;border:1px solid #ef9a9a;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-shield-exclamation" style="color:#c62828;"></i></div>
                                    <div style="flex:1;min-width:0;"><div style="font-size:.82rem;font-weight:700;color:#2e4836;">Blocked</div><div style="font-size:.7rem;color:#8a9a8a;">Locked for 30 days</div></div>
                                    <div class="status-opt-check"><i class="bi bi-check-lg"></i></div>
                                </div>
                                <div class="status-opt" data-status="Archived" onclick="pickStatus('Archived')">
                                    <div style="width:34px;height:34px;border-radius:10px;background:#eeeeee;border:1px solid #cccccc;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-archive" style="color:#616161;"></i></div>
                                    <div style="flex:1;min-width:0;"><div style="font-size:.82rem;font-weight:700;color:#2e4836;">Archived</div><div style="font-size:.7rem;color:#8a9a8a;">Account is archived</div></div>
                                    <div class="status-opt-check"><i class="bi bi-check-lg"></i></div>
                                </div>
                            </div>
                            <div id="statusBlockWarn" style="display:none;background:#fbe9e7;border:1px solid #ef9a9a;border-radius:12px;padding:10px 14px;font-size:.78rem;color:#c62828;margin-top:12px;"><i class="bi bi-exclamation-triangle me-1"></i>Blocked accounts will be locked for 30 days.</div>
                        </div>

                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <label style="display:block;font-size:.85rem;font-weight:600;color:#57694d;">Reason <span style="font-weight:400;color:#8aa09a;">(optional)</span></label>
                                <span id="statusReasonCount" style="font-size:.72rem;color:#8aa09a;">0 / 255</span>
                            </div>
        <textarea id="statusReason" maxlength="255" style="width:100%;height:80px;border-radius:16px;border:1px solid #cdd9b5;padding:12px 16px;font-size:.9rem;color:#2e4836;resize:none;" placeholder="Enter reason for this status change..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="button" class="btn" data-bs-dismiss="modal" style="height:46px;border-radius:14px;background:#ffffff;border:1px solid #cdd9b5;color:#57694d;font-weight:600;flex:1;">Cancel</button>
                        <button type="button" id="saveStatusBtn" class="btn text-white" style="height:46px;border-radius:14px;border:none;background:#57694d;font-weight:600;flex:1;font-size:.9rem;"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 overflow-hidden" style="border-radius:24px;background:#fef8f3;box-shadow:0 18px 50px rgba(0,0,0,.08);">

            <div class="row g-0">

                <div class="col-lg-4 d-none d-lg-block" style="background:linear-gradient(180deg,#f9f4da 0%,#f0edd0 100%);border-right:1px solid #e7e1c9;min-height:430px;">
                    <div class="p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div style="width:64px;height:64px;border-radius:20px;background:#ffffff;border:1px solid #cdd9b5;display:flex;align-items:center;justify-content:center;">
                                <i class="bi bi-pencil-square" style="color:#57694d;font-size:1.4rem;"></i>
                            </div>
                            <h2 style="margin-top:18px;font-size:1.35rem;font-weight:700;line-height:1.3;color:#2e4836;">Edit Passenger</h2>
                            <p style="margin-top:8px;font-size:.8rem;line-height:1.7;color:#57694d;">Update personal information, modify discount type, and manage discount verification status.</p>
                        </div>

                        <div class="d-grid gap-2">
                            <div style="background:#ffffff;border:1px solid #cdd9b5;border-radius:14px;padding:14px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:38px;height:38px;border-radius:11px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:10px;">
                                        <i class="bi bi-person" style="color:#57694d;font-size:.95rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.8rem;font-weight:700;color:#2e4836;">Personal Details</div>
                                        <div style="font-size:.68rem;color:#8aa09a;">Name, email, and contact number</div>
                                    </div>
                                </div>
                            </div>
                            <div style="background:#ffffff;border:1px solid #cdd9b5;border-radius:14px;padding:14px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:38px;height:38px;border-radius:11px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:10px;">
                                        <i class="bi bi-tags" style="color:#57694d;font-size:.95rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.8rem;font-weight:700;color:#2e4836;">Discount Settings</div>
                                        <div style="font-size:.68rem;color:#8aa09a;">Discount type and verification status</div>
                                    </div>
                                </div>
                            </div>
                            <div style="background:#ffffff;border:1px solid #cdd9b5;border-radius:14px;padding:14px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:38px;height:38px;border-radius:11px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:10px;">
                                        <i class="bi bi-shield-check" style="color:#57694d;font-size:.95rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.8rem;font-weight:700;color:#2e4836;">Audit Trail</div>
                                        <div style="font-size:.68rem;color:#8aa09a;">All changes are saved and recorded</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-12">
                    <div class="modal-header border-0 px-4 pt-4 pb-0">
                        <h4 style="margin:0;color:#2e4836;font-weight:700;font-size:1.1rem;">
                            <i class="bi bi-pencil-square me-2" style="color:#57694d;"></i>Edit Passenger
                        </h4>
                        <button type="button" class="btn-close" onclick="tryEditClose()"></button>
                    </div>
                    <div class="modal-body px-4 py-3">
                        <input type="hidden" id="editPid">

                        <div style="display:flex;align-items:center;gap:12px;background:#ffffff;border:1px solid #cdd9b5;border-radius:16px;padding:13px 16px;margin-bottom:16px;">
                            <div id="editPAvatar" style="width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#295738,#7da453);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;flex-shrink:0;">AA</div>
                            <div style="flex:1;min-width:0;">
                                <div id="editPName" style="font-size:.95rem;font-weight:700;color:#2e4836;">Passenger</div>
                                <div id="editPId" style="font-size:.72rem;color:#6b7d3a;font-weight:600;">Passenger ID: —</div>
                            </div>
                            <span id="editPCurr"></span>
                        </div>

                        <div style="background:#fff;border:1px solid #e8eee8;border-radius:16px;overflow:hidden;margin-bottom:16px;">
                            <div style="background:linear-gradient(135deg,#e8f5e9,#f1f8e9);padding:9px 16px;border-bottom:1px solid #e8eee8;">
                                <span style="font-weight:700;color:#2e4836;font-size:.8rem;"><i class="bi bi-person me-2" style="color:#2e7d32;"></i>Personal Information</span>
                            </div>
                            <div style="padding:13px 16px;">
                                <div style="margin-bottom:12px;">
                                    <label style="display:block;margin-bottom:6px;font-size:.78rem;font-weight:600;color:#57694d;">Full Name</label>
                                    <div class="edit-input-wrap"><i class="bi bi-person"></i><input id="editName" placeholder="Enter full name"></div>
                                    <div class="edit-err" id="editErrName"><i class="bi bi-exclamation-circle me-1"></i>Full name is required.</div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label style="display:block;margin-bottom:6px;font-size:.78rem;font-weight:600;color:#57694d;">Email Address</label>
                                        <div class="edit-input-wrap"><i class="bi bi-envelope"></i><input id="editEmail" type="email" placeholder="Enter email address"></div>
                                        <div class="edit-err" id="editErrEmail"><i class="bi bi-exclamation-circle me-1"></i>Enter a valid email address.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="display:block;margin-bottom:6px;font-size:.78rem;font-weight:600;color:#57694d;">Contact Number</label>
                                        <div class="edit-input-wrap"><i class="bi bi-telephone"></i><input id="editContact" placeholder="Enter contact number"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="background:#fff;border:1px solid #e8eee8;border-radius:16px;overflow:hidden;margin-bottom:12px;">
                            <div style="background:linear-gradient(135deg,#e8f5e9,#f1f8e9);padding:9px 16px;border-bottom:1px solid #e8eee8;">
                                <span style="font-weight:700;color:#2e4836;font-size:.8rem;"><i class="bi bi-tags me-2" style="color:#2e7d32;"></i>Discount Management</span>
                            </div>
                            <div style="padding:13px 16px;">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label style="display:block;margin-bottom:6px;font-size:.78rem;font-weight:600;color:#57694d;">Discount Type</label>
                                        <div class="edit-input-wrap"><i class="bi bi-tag"></i>
                                            <select id="editDiscType">
                                                <option value="">Regular</option>
                                                <option value="Student">Student</option>
                                                <option value="Senior">Senior Citizen</option>
                                                <option value="PWD">PWD</option>
                                                <option value="Voucher">Voucher Code</option>
                                                <option value="Promo">Promo</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label style="display:block;margin-bottom:6px;font-size:.78rem;font-weight:600;color:#57694d;">Discount Verification</label>
                                        <div class="edit-input-wrap"><i class="bi bi-patch-check"></i>
                                            <select id="editDiscStatus">
                                                <option value="Pending">Pending</option>
                                                <option value="Verified">Verified</option>
                                                <option value="Rejected">Rejected</option>
                                                <option value="Expired">Expired</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div id="editDiscHint" style="margin-top:10px;font-size:.74rem;color:#57694d;background:#f9f4da;border:1px solid #e7e1c9;border-radius:10px;padding:8px 12px;display:none;"></div>
                            </div>
                        </div>

                        <div id="editDocRow" style="display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #e8eee8;border-radius:14px;padding:11px 14px;margin-bottom:16px;"></div>
                        <input type="file" id="editDocInput" accept=".png,.jpg,.jpeg,.gif,.webp,.pdf" style="display:none;">
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="button" class="btn" onclick="tryEditClose()" style="height:46px;border-radius:14px;background:#ffffff;border:1px solid #cdd9b5;color:#57694d;font-weight:600;flex:1;"><i class="bi bi-x-lg me-1"></i>Cancel</button>
                        <button type="button" id="saveEditBtn" class="btn text-white" style="height:46px;border-radius:14px;border:none;background:#57694d;font-weight:600;flex:1;font-size:.9rem;"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tripsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 overflow-hidden" style="border-radius:24px;background:#fef8f3;box-shadow:0 18px 50px rgba(0,0,0,.08);">

            <div class="row g-0">

                <div class="col-lg-4 d-none d-lg-block" style="background:linear-gradient(180deg,#f9f4da 0%,#f0edd0 100%);border-right:1px solid #e7e1c9;">
                    <div class="p-4 h-100 d-flex flex-column">

                        <div style="text-align:center;padding-top:6px;">
                            <div id="tripSideAvatar" style="width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,#295738,#7da453);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.9rem;font-weight:700;margin:0 auto 12px;border:3px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.12);">P</div>
                            <div style="font-size:.66rem;font-weight:600;color:#8a9a8a;letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px;">Passenger</div>
                            <div id="tripSideName" style="font-size:1.15rem;font-weight:700;color:#2e4836;margin:0;">Passenger</div>
                            <div id="tripSideId" style="font-size:.78rem;color:#6b7d3a;font-weight:600;margin:3px 0 10px;">—</div>
                            <div id="tripSideDisc" style="margin-bottom:16px;"></div>
                        </div>

                        <div style="background:#fff;border-radius:14px;padding:12px 14px;border:1px solid #e0dcc0;margin-bottom:14px;">
                            <div style="font-size:.66rem;font-weight:700;color:#57694d;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px;">Contact Information</div>
                            <div id="tripSideEmail" style="font-size:.78rem;color:#57694d;word-break:break-all;"><i class="bi bi-envelope me-1" style="color:#8aa09a;"></i>—</div>
                            <div id="tripSidePhone" style="font-size:.78rem;color:#57694d;margin-top:5px;"><i class="bi bi-telephone me-1" style="color:#8aa09a;"></i>—</div>
                        </div>

                        <div style="background:#fff;border-radius:14px;padding:14px;border:1px solid #e0dcc0;margin-bottom:14px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;font-size:.66rem;font-weight:700;color:#57694d;text-transform:uppercase;letter-spacing:.4px;">
                                <span><i class="bi bi-bus-front me-1" style="color:#7da453;"></i>Trip Overview</span>
                                <button type="button" onclick="loadTrips()" title="Refresh" style="border:none;background:none;color:#6b7d3a;font-size:.8rem;padding:0;"><i class="bi bi-arrow-clockwise"></i></button>
                            </div>
                            <div style="display:flex;gap:10px;margin-top:10px;">
                                <div style="flex:1;background:#f5f9f0;border:1px solid #e3ecd0;border-radius:12px;padding:10px;text-align:center;">
                                    <div style="font-size:.6rem;font-weight:600;color:#8a9a8a;text-transform:uppercase;">Total Trips</div>
                                    <div id="tripSideTrips" style="font-size:1.25rem;font-weight:700;color:#295738;">0</div>
                                </div>
                                <div style="flex:1;background:#fdf9ec;border:1px solid #efe4c0;border-radius:12px;padding:10px;text-align:center;">
                                    <div style="font-size:.6rem;font-weight:600;color:#8a9a8a;text-transform:uppercase;">Total Spent</div>
                                    <div id="tripSideSpent" style="font-size:1.05rem;font-weight:700;color:#6b5d1e;">₱0.00</div>
                                </div>
                            </div>
                        </div>

                        <div style="background:#fff;border-radius:14px;padding:14px;border:1px solid #e0dcc0;margin-bottom:14px;">
                            <div style="font-size:.66rem;font-weight:700;color:#57694d;text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px;">Payment Breakdown</div>
                            <div style="display:flex;align-items:center;gap:14px;">
                                <div id="tripDonut" class="trip-donut" style="background:#f0edd0;"></div>
                                <div id="tripDonutLegend" style="flex:1;font-size:.74rem;"></div>
                            </div>
                        </div>

                        <div onclick="openFullProfile()" style="background:#f0f7f0;border:1px solid #cdd9b5;border-radius:14px;padding:12px 14px;margin-top:auto;cursor:pointer;display:flex;align-items:center;gap:10px;transition:background .15s;">
                            <div style="width:34px;height:34px;border-radius:50%;background:#fff;border:1px solid #cdd9b5;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-person-lines-fill" style="color:#57694d;"></i></div>
                            <div>
                                <div style="font-size:.76rem;font-weight:700;color:#2e4836;">Full Passenger Profile</div>
                                <div style="font-size:.66rem;color:#8a9a8a;line-height:1.5;">View complete passenger info, account details and uploaded documents</div>
                            </div>
                            <i class="bi bi-chevron-right ms-auto" style="color:#7da453;"></i>
                        </div>

                    </div>
                </div>

                <div class="col-lg-8 col-12">

                    <div class="modal-header border-0 px-4 pt-4 pb-0">
                        <div>
                            <h4 style="margin:0;color:#2e4836;font-weight:700;font-size:1.1rem;"><i class="bi bi-person-vcard me-2" style="color:#57694d;"></i>Passenger Profile</h4>
                            <div id="tripSubHead" style="font-size:.8rem;color:#8a9a8a;margin-top:2px;">Trip History</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body px-4 py-3">

                        <div class="trip-filter" style="background:#fff;border:1px solid #e0dcc0;border-radius:14px;padding:12px;margin-bottom:14px;">
                            <div class="row g-2 align-items-end">
                                <div class="col-lg-2 col-md-4 col-6">
                                    <label style="font-size:.66rem;font-weight:600;color:#57694d;margin-bottom:4px;"><i class="bi bi-calendar3 me-1"></i>From</label>
                                    <input type="date" id="fTripFrom" class="form-control form-control-sm" onchange="applyTripFilters()" style="border-radius:10px;border:1px solid #cdd9b5;font-size:.76rem;">
                                </div>
                                <div class="col-lg-2 col-md-4 col-6">
                                    <label style="font-size:.66rem;font-weight:600;color:#57694d;margin-bottom:4px;"><i class="bi bi-calendar3 me-1"></i>To</label>
                                    <input type="date" id="fTripTo" class="form-control form-control-sm" onchange="applyTripFilters()" style="border-radius:10px;border:1px solid #cdd9b5;font-size:.76rem;">
                                </div>
                                <div class="col-lg-2 col-md-4 col-6">
                                    <label style="font-size:.66rem;font-weight:600;color:#57694d;margin-bottom:4px;"><i class="bi bi-signpost-split me-1"></i>Route</label>
                                    <select id="fTripRoute" class="form-control form-control-sm" onchange="applyTripFilters()" style="border-radius:10px;border:1px solid #cdd9b5;font-size:.76rem;"><option value="">All Routes</option></select>
                                </div>
                                <div class="col-lg-2 col-md-4 col-6">
                                    <label style="font-size:.66rem;font-weight:600;color:#57694d;margin-bottom:4px;"><i class="bi bi-credit-card me-1"></i>Payment</label>
                                    <select id="fTripPay" class="form-control form-control-sm" onchange="applyTripFilters()" style="border-radius:10px;border:1px solid #cdd9b5;font-size:.76rem;"><option value="">All Payments</option><option value="cash">Cash</option><option value="gcash">GCash</option><option value="hybrid">Hybrid</option></select>
                                </div>
                                <div class="col-lg-3 col-md-6 col-12">
                                    <label style="font-size:.66rem;font-weight:600;color:#57694d;margin-bottom:4px;"><i class="bi bi-search me-1"></i>Search</label>
                                    <input type="text" id="fTripSearch" placeholder="Trip ID, driver, or location" onkeyup="applyTripFilters()" class="form-control form-control-sm" style="border-radius:10px;border:1px solid #cdd9b5;font-size:.76rem;">
                                </div>
                                <div class="col-lg-1 col-md-4 col-6">
                                    <button class="btn btn-sm w-100" onclick="resetTripFilters()" style="border-radius:10px;background:#f9f4da;border:1px solid #e0dcc0;color:#57694d;font-weight:600;font-size:.74rem;"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                                </div>
                            </div>
                        </div>

                        <div id="tripState" style="display:none;text-align:center;padding:44px 0;"><div class="spinner-border" style="width:2.2rem;height:2.2rem;color:#57694d;"></div><p class="mt-2" style="color:#57694d;font-size:.82rem;">Loading trips...</p></div>
                        <div id="tripEmpty" style="display:none;text-align:center;padding:44px 0;"><i class="bi bi-inbox" style="font-size:2.6rem;color:#cdd9b5;"></i><p class="mt-2" style="color:#57694d;font-size:.82rem;">No trips found.</p></div>

                        <div id="tripTableWrap" style="display:none;background:#fff;border:1px solid #e0dcc0;border-radius:14px;overflow:hidden;">
                            <div class="table-responsive">
                                <table class="table trip-table mb-0" style="font-size:.78rem;">
                                    <thead style="background:#f0f7f0;">
                                        <tr>
                                            <th style="color:#2e4836;font-weight:700;padding:10px 14px;white-space:nowrap;">Trip ID</th>
                                            <th style="color:#2e4836;font-weight:700;white-space:nowrap;">Driver</th>
                                            <th style="color:#2e4836;font-weight:700;">From</th>
                                            <th style="color:#2e4836;font-weight:700;">To</th>
                                            <th style="color:#2e4836;font-weight:700;">Fare</th>
                                            <th style="color:#2e4836;font-weight:700;">Payment</th>
                                            <th style="color:#2e4836;font-weight:700;white-space:nowrap;">Boarded</th>
                                            <th style="color:#2e4836;font-weight:700;white-space:nowrap;">Dropped</th>
                                            <th style="width:46px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tripBody"></tbody>
                                </table>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-top:1px solid #eef0e0;background:#fdfbf4;flex-wrap:wrap;gap:8px;">
                                <div style="font-size:.74rem;color:#57694d;" id="tripPageInfo">Showing 0 of 0 trips</div>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <select id="tripPageSize" onchange="tripPage=1;renderTripTable()" style="border-radius:10px;border:1px solid #cdd9b5;font-size:.74rem;padding:5px 8px;color:#57694d;background:#fff;">
                                        <option value="5">5 per page</option><option value="10" selected>10 per page</option><option value="20">20 per page</option>
                                    </select>
                                    <button class="btn btn-sm" id="tripPrev" onclick="tripGo(-1)" style="border-radius:10px;background:#fff;border:1px solid #cdd9b5;color:#57694d;font-weight:600;font-size:.74rem;"><i class="bi bi-chevron-left"></i> Prev</button>
                                    <button class="btn btn-sm" id="tripNext" onclick="tripGo(1)" style="border-radius:10px;background:#fff;border:1px solid #cdd9b5;color:#57694d;font-weight:600;font-size:.74rem;">Next <i class="bi bi-chevron-right"></i></button>
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
    function psgrAlert(type, title, message, onConfirm) { var overlay = document.getElementById('customAlertOverlay'); if (!overlay)return; var header = overlay.querySelector('.custom-alert-header'); var iconEl = overlay.querySelector('.custom-alert-icon'); var titleEl = overlay.querySelector('.custom-alert-title'); var msgEl = overlay.querySelector('.custom-alert-message'); var btn = overlay.querySelector('.custom-alert-btn'); var btnCancel = overlay.querySelector('.custom-alert-btn-cancel'); var actions = overlay.querySelector('.custom-alert-actions'); header.className = 'custom-alert-header '+type; if (type === 'success') { iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#2e4836" stroke-width="3"/><path d="M16 24l6 6 10-10" stroke="#2e4836" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>'; if (btnCancel)btnCancel.style.display = 'none'; if (actions)actions.style.display = ''; btn.className = 'custom-alert-btn success'; btn.textContent = 'Continue' } else { iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#fff" stroke-width="3"/><path d="M18 18l12 12M30 18l-12 12" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>'; if (btnCancel)btnCancel.style.display = 'none'; if (actions)actions.style.display = ''; btn.className = 'custom-alert-btn error'; btn.textContent = 'Close' } titleEl.textContent = title; msgEl.textContent = message; btn.onclick = function () { overlay.classList.remove('active'); if (typeof onConfirm === 'function')onConfirm() }; overlay.classList.add('active') }
    function psgrConfirm(title, message, onConfirm, onCancel) { var overlay = document.getElementById('customAlertOverlay'); if (!overlay)return; var header = overlay.querySelector('.custom-alert-header'); var iconEl = overlay.querySelector('.custom-alert-icon'); var titleEl = overlay.querySelector('.custom-alert-title'); var msgEl = overlay.querySelector('.custom-alert-message'); var btn = overlay.querySelector('.custom-alert-btn'); var btnCancel = overlay.querySelector('.custom-alert-btn-cancel'); var actions = overlay.querySelector('.custom-alert-actions'); header.className = 'custom-alert-header warning'; iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#92400e" stroke-width="3"/><path d="M24 16v10M24 30v2" stroke="#92400e" stroke-width="3" stroke-linecap="round"/></svg>'; if (actions)actions.style.display = 'flex'; if (btnCancel)btnCancel.style.display = 'block'; titleEl.textContent = title; msgEl.textContent = message; btn.className = 'custom-alert-btn confirm'; btn.textContent = 'Confirm'; btn.onclick = function () { overlay.classList.remove('active'); if (typeof onConfirm === 'function')onConfirm() }; if (btnCancel) { btnCancel.className = 'custom-alert-btn cancel'; btnCancel.textContent = 'Cancel'; btnCancel.onclick = function () { overlay.classList.remove('active'); if (typeof onCancel === 'function')onCancel() } } overlay.classList.add('active') }
    var psgrData = [], psgrPage = 1, PSGR_SIZE = 15, psgrSortBy = 'p.full_name', psgrSortDir = 'ASC', psgrArchived = false;
    var psgrSearchTimer = null;

    function psgrEscape(v) {
        return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function psgrMoney(v) {
        return '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function psgrFilterDefs() {
        return [
            ['search', 'Search', 'searchInput'],
            ['status', 'Status', 'filterStatus'],
            ['discount_type', 'Type', 'filterDiscount'],
            ['payment', 'Payment', 'filterPayment'],
            ['trip_activity', 'Trips', 'filterTripActivity'],
            ['verification', 'Verification', 'filterVerification'],
            ['account', 'Account', 'filterAccount'],
            ['date_from', 'Joined from', 'filterDateFrom'],
            ['date_to', 'Joined to', 'filterDateTo'],
            ['balance_min', 'Balance ≥', 'filterBalanceMin'],
            ['balance_max', 'Balance ≤', 'filterBalanceMax'],
            ['boarded_today', 'Boarded', 'filterBoardedToday']
        ];
    }

    function psgrQueryParams(action) {
        var p = new URLSearchParams();
        p.set('action', action || 'get_passengers');
        var values = {
            search: 'searchInput', status: 'filterStatus', account: 'filterAccount',
            discount_type: 'filterDiscount', verification: 'filterVerification',
            date_from: 'filterDateFrom', date_to: 'filterDateTo',
            payment: 'filterPayment', trip_activity: 'filterTripActivity',
            balance_min: 'filterBalanceMin', balance_max: 'filterBalanceMax',
            boarded_today: 'filterBoardedToday'
        };
        Object.keys(values).forEach(function (key) {
            var el = document.getElementById(values[key]);
            if (el && String(el.value).trim() !== '') { p.set(key, el.value); }
        });
        p.set('sort_by', psgrSortBy);
        p.set('sort_dir', psgrSortDir);
        return p;
    }

    function psgrSearchInput() {
        clearTimeout(psgrSearchTimer);
        psgrSearchTimer = setTimeout(loadPassengers, 300);
    }

    function psgrBalanceInput() {
        clearTimeout(psgrSearchTimer);
        psgrSearchTimer = setTimeout(loadPassengers, 400);
    }

    function psgrVerifyBadge(p) {
        if (p.source !== 'live') return '<span style="color:#8aa09a;">&mdash;</span>';
        var v = p.verification || 'Pending';
        var cls = v === 'Verified' ? 'psgr-verify-verified'
            : v === 'Rejected' ? 'psgr-verify-rejected'
            : v === 'Expired' ? 'psgr-verify-expired' : 'psgr-verify-pending';
        return '<span class="psgr-verify ' + cls + '">' + psgrEscape(v) + '</span>';
    }

    function psgrBalanceCell(p) {
        var state = p.payment_state || '';
        if (p.source !== 'live' || state === 'imported') {
            return '<span class="psgr-money-na" title="Imported history rows have no live payment records; this balance cannot be determined.">Unknown</span>';
        }
        var bal = Number(p.outstanding_balance || 0);
        if (state === 'with_balance' || bal > 0) {
            return '<span class="psgr-money-due" title="Confirmed outstanding balance from completed, unpaid trips.">' + psgrMoney(bal) + '</span>';
        }
        if (state === 'unconfirmed') {
            return '<span class="psgr-money-na" title="Live trips exist but no completed payment record confirms a zero balance.">Unconfirmed</span>';
        }
        return '<span class="psgr-money-zero" title="No outstanding balance on completed trips.">' + psgrMoney(0) + '</span>';
    }

    function psgrUpdateQuick() {
        document.querySelectorAll('.psgr-quick-btn').forEach(function (btn) {
            btn.classList.toggle('active', psgrQuickActive(btn.getAttribute('data-quick')));
        });
    }

    function psgrQuickActive(kind) {
        if (kind === 'with_balance') return document.getElementById('filterPayment').value === 'with_balance';
        if (kind === 'pending_verification') return document.getElementById('filterVerification').value === 'pending';
        if (kind === 'recent') return !!document.getElementById('filterDateFrom').value && psgrRecentDate() === document.getElementById('filterDateFrom').value;
        if (kind === 'blocked') return document.getElementById('filterStatus').value === 'Blocked';
        return false;
    }

    function psgrRecentDate() {
        var d = new Date(); d.setDate(d.getDate() - 30);
        var m = String(d.getMonth() + 1).padStart(2, '0'), day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function psgrQuick(kind) {
        if (kind === 'with_balance') {
            var pay = document.getElementById('filterPayment');
            pay.value = pay.value === 'with_balance' ? '' : 'with_balance';
        } else if (kind === 'pending_verification') {
            var ver = document.getElementById('filterVerification');
            ver.value = ver.value === 'pending' ? '' : 'pending';
        } else if (kind === 'recent') {
            var from = document.getElementById('filterDateFrom');
            from.value = from.value === psgrRecentDate() ? '' : psgrRecentDate();
            psgrToggleMoreFilters(true);
        } else if (kind === 'blocked') {
            var status = document.getElementById('filterStatus');
            status.value = status.value === 'Blocked' ? '' : 'Blocked';
        }
        loadPassengers();
    }

    function psgrToggleMoreFilters(forceOpen) {
        var panel = document.getElementById('psgrMoreFilters');
        var show = forceOpen === true ? true : panel.style.display === 'none';
        panel.style.display = show ? 'flex' : 'none';
        document.getElementById('moreFiltersBtn').classList.toggle('psgr-btn-primary', show);
        document.getElementById('moreFiltersBtn').classList.toggle('psgr-btn-outline', !show);
    }

    function psgrActiveMoreCount() {
        return ['filterDiscount', 'filterTripActivity', 'filterAccount', 'filterDateFrom', 'filterDateTo', 'filterBalanceMin', 'filterBalanceMax']
            .filter(function (id) { var el = document.getElementById(id); return el && String(el.value).trim() !== ''; }).length;
    }

    function psgrUpdateMoreCount() {
        var count = psgrActiveMoreCount(), badge = document.getElementById('moreFiltersCount');
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline-flex' : 'none';
    }

    function psgrUpdateSortIcons() {
        document.querySelectorAll('.psgr-table thead th[data-sort]').forEach(function (th) {
            var icon = th.querySelector('i');
            if (!icon) return;
            var active = th.getAttribute('data-sort') === psgrSortBy;
            icon.className = 'bi ' + (active ? (psgrSortDir === 'ASC' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up');
            icon.style.opacity = active ? '1' : '0.5';
        });
    }

    function psgrRenderChips() {
        var labels = {
            with_balance: 'With Balance Due', fully_paid: 'Fully Paid',
            has: 'Has Trips', none: 'No Trips',
            pending: 'Pending', approved: 'Approved', rejected: 'Rejected',
            has_account: 'Has Account', walkin: 'Walk-in', no_account: 'No Account', other: 'Other',
            '1': 'Today'
        };
        var html = '';
        psgrFilterDefs().forEach(function (d) {
            var el = document.getElementById(d[2]);
            if (!el || String(el.value).trim() === '') return;
            var value = labels[el.value] || el.value;
            if (d[0] === 'balance_min' || d[0] === 'balance_max') value = '₱' + value;
            html += '<span class="psgr-chip">' + d[1] + ': ' + psgrEscape(value) +
                ' <button type="button" onclick="psgrRemoveFilter(\'' + d[0] + '\')" aria-label="Remove filter">&times;</button></span>';
        });
        var wrap = document.getElementById('psgrChips');
        wrap.innerHTML = html;
        wrap.style.display = html ? 'flex' : 'none';
    }

    function psgrRemoveFilter(key) {
        var def = psgrFilterDefs().filter(function (d) { return d[0] === key; })[0];
        if (!def) return;
        var el = document.getElementById(def[2]);
        if (el) el.value = '';
        loadPassengers();
    }

    function psgrUpdateArchivedBtn() {
        var btn = document.getElementById('archivedBtn');
        if (btn) { btn.classList.toggle('psgr-btn-primary', psgrArchived); btn.classList.toggle('psgr-btn-outline', !psgrArchived); }
    }

    // Summary cards and quick pills act as one-click filters.
    function psgrApplyStatFilter(kind) {
        var status = document.getElementById('filterStatus');
        var ver = document.getElementById('filterVerification');
        var pay = document.getElementById('filterPayment');
        var boarded = document.getElementById('filterBoardedToday');

        if (kind === 'total') { clearFilters(); return; }

        if (kind === 'pending_verification') {
            ver.value = ver.value === 'pending' ? '' : 'pending';
        } else if (kind === 'with_balance') {
            pay.value = pay.value === 'with_balance' ? '' : 'with_balance';
        } else if (kind === 'boarded_today') {
            boarded.value = boarded.value === '1' ? '' : '1';
        } else if (kind === 'active') {
            status.value = status.value === 'Active' ? '' : 'Active';
        } else if (kind === 'blocked') {
            status.value = status.value === 'Blocked' ? '' : 'Blocked';
        } else if (kind === 'archived') {
            status.value = status.value === 'Archived' ? '' : 'Archived';
        }
        loadPassengers();
    }

    function psgrUpdateStatCards() {
        var ver = document.getElementById('filterVerification');
        var pay = document.getElementById('filterPayment');
        var boarded = document.getElementById('filterBoardedToday');
        var status = document.getElementById('filterStatus');
        var active = {
            pending_verification: !!(ver && ver.value === 'pending'),
            with_balance: !!(pay && pay.value === 'with_balance'),
            boarded_today: !!(boarded && boarded.value === '1'),
            active: !!(status && status.value === 'Active'),
            blocked: !!(status && status.value === 'Blocked'),
            archived: !!(status && status.value === 'Archived')
        };
        var anything = false;
        document.querySelectorAll('.psgr-stat-card[data-filter], .psgr-mini[data-filter]').forEach(function (el) {
            var kind = el.getAttribute('data-filter');
            var on = kind === 'total' ? false : !!active[kind];
            if (on) anything = true;
            el.classList.toggle('active', on);
        });
        var totalCard = document.querySelector('.psgr-stat-card[data-filter="total"]');
        if (totalCard) totalCard.classList.toggle('active', !anything);
    }

    function psgrBadge(s) {
        var m = { active:'psgr-badge-active', pending:'psgr-badge-pending', suspended:'psgr-badge-suspended', blocked:'psgr-badge-blocked', archived:'psgr-badge-archived' };
        return '<span class="psgr-badge '+(m[(s||'active').toLowerCase()]||'psgr-badge-active')+'">'+(s||'Active')+'</span>';
    }

    var psgrLoadSeq = 0;

    function psgrShowEmpty(kind) {
        var el = document.getElementById('psgrEmpty');
        if (kind === 'error') {
            el.innerHTML = '<i class="bi bi-exclamation-triangle" style="font-size:2.6rem;color:#d46868;"></i>' +
                '<p class="mt-3" style="color:#57694d;">Could not load passengers. Please try again.</p>' +
                '<button type="button" class="psgr-btn psgr-btn-outline psgr-btn-sm" onclick="loadPassengers()" style="margin-top:4px;"><i class="bi bi-arrow-clockwise"></i> Retry</button>';
        } else {
            el.innerHTML = '<i class="bi bi-inbox" style="font-size:3rem;color:#cdd9b5;"></i>' +
                '<p class="mt-3" style="color:#57694d;">No passengers found.</p>' +
                '<button type="button" class="psgr-btn psgr-btn-outline psgr-btn-sm" onclick="clearFilters()" style="margin-top:4px;"><i class="bi bi-x-circle"></i> Clear All Filters</button>';
        }
        document.getElementById('psgrTableWrap').style.display = 'none';
        document.getElementById('psgrBody').innerHTML = '';
        el.style.display = 'flex';
    }

    function loadPassengers() {
        // Ignore stale responses so a slow earlier request can never overwrite
        // a newer render (the cause of "No passengers found" over visible rows).
        var seq = ++psgrLoadSeq;
        document.getElementById('psgrState').style.display = 'flex';
        document.getElementById('psgrEmpty').style.display = 'none';
        document.getElementById('psgrTableWrap').style.display = 'none';
        psgrArchived = document.getElementById('filterStatus').value === 'Archived';
        var p = psgrQueryParams('get_passengers');
        if (psgrArchived) p.set('archived', '1');
        fetch('/admin/api/Passenger/PassengerApi.php?'+p.toString()).then(function (r) {
            if (!r.ok) throw new Error('HTTP '+r.status);
            return r.json();
        }).then(function (r) {
            if (seq !== psgrLoadSeq) return;
            document.getElementById('psgrState').style.display = 'none';
            if (!r.success||!r.data||!r.data.length) {
                psgrData = []; psgrPage = 1;
                document.getElementById('psgrCount').textContent = '0 passengers';
                psgrShowEmpty('empty');
            } else {
                psgrData = r.data; psgrPage = 1;
                document.getElementById('psgrEmpty').style.display = 'none';
                document.getElementById('psgrCount').textContent = psgrData.length+' passenger'+(psgrData.length!== 1?'s':'')+(psgrArchived?' (archived)':'');
                psgrRender();
            }
            psgrRenderChips(); psgrUpdateQuick(); psgrUpdateMoreCount(); psgrUpdateArchivedBtn(); psgrUpdateSortIcons(); psgrUpdateStatCards();
        }).catch (function () {
            if (seq !== psgrLoadSeq) return;
            document.getElementById('psgrState').style.display = 'none';
            psgrShowEmpty('error');
        });
    }

    function psgrRender() {
        var start = (psgrPage-1)*PSGR_SIZE, end = Math.min(start+PSGR_SIZE, psgrData.length), page = psgrData.slice(start, end), h = '';
        page.forEach(function (p, i) {
            var idx = start+i;
            var acct = p.account_label || ((p.full_name||'').toLowerCase().indexOf('walk-in') !== -1 ? 'Walk-in'
            : (p.record_type||'') === 'Other' ? 'Other'
            : (p.source === 'imported' ? 'No Account' : 'Has Account'));
            var acctStyle = acct === 'Walk-in' ? 'background:#e8e8e8;color:#666;'
            : acct === 'Other' ? 'background:#e7e9fb;color:#3b46a0;'
            : acct === 'No Account' ? 'background:#fef3c7;color:#92400e;'
            : 'background:#e8f6ef;color:#198754;';
            var trips = (p.total_trips != null) ? p.total_trips : ((p.trip_count||0)+(p.history_trip_count||0));
            h+= '<tr><td class="psgr-cell-id">'+(p.id||'—')+'</td>'+
            '<td class="psgr-col-name"><div class="psgr-name">'+(p.full_name||'—')+'</div><div class="psgr-email" title="'+psgrEscape(p.email||'')+'">'+psgrEscape(p.email||'')+'</div></td>'+
            '<td class="psgr-cell-contact">'+(p.phone||'—')+'</td>'+
            '<td><span class="discount-tag">'+(p.discount_type||'Regular')+'</span></td>'+
            '<td>'+psgrVerifyBadge(p)+'</td>'+
            '<td><span class="psgr-act-badge" style="'+acctStyle+'">'+acct+'</span></td>'+
            '<td class="psgr-cell-trips">'+trips+'</td>'+
            '<td>'+psgrBalanceCell(p)+'</td>'+
            '<td class="psgr-cell-date">'+(p.registered_date||'—')+'</td>'+
            '<td>'+psgrBadge(p.status)+'</td>'+
            '<td><div class="psgr-dropdown">'+
            '<button class="psgr-act-btn" onclick="toggleMenu('+idx+')"><i class="bi bi-three-dots-vertical"></i></button>'+
            '<div class="psgr-dropdown-menu" id="menu'+idx+'">'+
            '<button class="psgr-dropdown-item" onclick="viewProfile('+idx+')"><i class="bi bi-eye"></i>View Profile</button>'+
            '<button class="psgr-dropdown-item" onclick="editPassenger('+idx+')"><i class="bi bi-pencil"></i>Edit Info</button>'+
            '<button class="psgr-dropdown-item" onclick="viewTrips('+idx+')"><i class="bi bi-bus-front"></i>Trip History</button>'+
            '<button class="psgr-dropdown-item" onclick="openStatus('+idx+')"><i class="bi bi-shield"></i>Change Status</button>'+
            (psgrArchived?'<button class="psgr-dropdown-item" onclick="restorePassenger('+idx+')"><i class="bi bi-arrow-counterclockwise"></i>Restore</button>':'')+
            '</div></div></td></tr>';
        });
        document.getElementById('psgrBody').innerHTML = h;
        document.getElementById('psgrEmpty').style.display = 'none';
        document.getElementById('psgrState').style.display = 'none';
        document.getElementById('psgrTableWrap').style.display = 'block';
        var tp = Math.max(1, Math.ceil(psgrData.length/PSGR_SIZE)), el = document.getElementById('psgrPag');
        el.innerHTML = '<div class="psgr-pag-info">'+
        '<span class="psgr-pag-page">Page '+psgrPage+' of '+tp+'</span>'+
        '<span class="psgr-pag-range">Matching records: '+psgrData.length+' &middot; showing '+(start+1)+'–'+end+'</span></div><div>'+
        '<button onclick="psgrGo('+(psgrPage-1)+')" '+(psgrPage<= 1?'disabled':'')+'><i class="bi bi-chevron-left"></i> Prev</button>'+
        '<button onclick="psgrGo('+(psgrPage+1)+')" '+(psgrPage>= tp?'disabled':'')+'>Next <i class="bi bi-chevron-right"></i></button></div>';
    }

    function toggleMenu(i) { var m = document.getElementById('menu'+i); m.classList.toggle('show');}
    function toggleImportTripsMenu() { var m = document.getElementById('menuImportTrips'); m.classList.toggle('show');}
    function psgrGo(p) { psgrPage = p; psgrRender();}
    function sortBy(col) { if (psgrSortBy === col) { psgrSortDir = psgrSortDir === 'ASC'?'DESC':'ASC';} else { psgrSortBy = col; psgrSortDir = 'DESC';} loadPassengers();}
    function clearFilters() {
        psgrFilterDefs().forEach(function (d) { var el = document.getElementById(d[2]); if (el) el.value = ''; });
        psgrArchived = false;
        loadPassengers();
    }
    function showArchived() {
        var status = document.getElementById('filterStatus');
        status.value = status.value === 'Archived' ? '' : 'Archived';
        loadPassengers();
    }

    var profIdx = 0;

    // ------------------------------------------------------------------
    // Passenger details side panel
    // ------------------------------------------------------------------
    var panelTrips = [], panelDocPath = '';

    function psgrClosePanel() {
        var el = document.getElementById('psgrDetailsPanel');
        var inst = bootstrap.Offcanvas.getInstance(el);
        if (inst) inst.hide();
    }

    function psgrPanelTab(name) {
        ['profile', 'trips', 'payments'].forEach(function (tab) {
            var section = document.getElementById('panelTab' + tab.charAt(0).toUpperCase() + tab.slice(1));
            if (section) section.style.display = tab === name ? 'block' : 'none';
        });
        document.querySelectorAll('.psgr-panel-tab').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-tab') === name);
        });
    }

    function psgrOpenPanelDoc() {
        if (panelDocPath) window.open(panelDocPath, '_blank');
    }

    function tripMethodLabel(method) {
        var m = String(method || '').toLowerCase();
        var labels = { cash: 'Cash', gcash: 'GCash', hybrid: 'Hybrid', qrph: 'QR Ph', online: 'Online', 'non-cash': 'Non-Cash', other: 'Other' };
        return labels[m] || (m ? m.charAt(0).toUpperCase() + m.slice(1) : '—');
    }

    function panelValue(v) {
        return (v === null || v === undefined || String(v).trim() === '')
            ? '<span style="color:#b8c4b0;font-weight:400;">Not provided</span>'
            : psgrEscape(v);
    }

    function panelRow(label, value) {
        return '<div class="psgr-panel-row"><span>' + label + '</span><span>' + value + '</span></div>';
    }

    function panelCard(title, icon, body) {
        return '<div class="psgr-panel-card"><div class="psgr-panel-card-head"><i class="bi ' + icon + ' me-1"></i>' + title + '</div>' + body + '</div>';
    }

    function viewProfile(idx) {
        var p = psgrData[idx]; if (!p) return;
        profIdx = idx;
        panelTrips = [];
        panelDocPath = '';

        var initials = (p.full_name || 'P').split(' ').map(function (s) { return s[0]; }).join('').toUpperCase().slice(0, 2) || 'P';
        document.getElementById('panelAvatar').textContent = initials;
        document.getElementById('psgrPanelTitle').textContent = p.full_name || 'Passenger';
        document.getElementById('panelMeta').textContent = 'Passenger ID: ' + String(p.id) + (p.phone ? ' • ' + p.phone : '');
        document.getElementById('panelBadges').innerHTML = psgrBadge(p.status) +
            (p.source === 'live' ? ' ' + psgrVerifyBadge(p) : ' <span class="psgr-verify psgr-verify-expired">Imported</span>');

        document.getElementById('panelState').style.display = 'flex';
        document.getElementById('panelError').style.display = 'none';
        document.getElementById('panelContent').style.display = 'none';
        document.getElementById('panelActions').innerHTML = '';
        psgrPanelTab('profile');
        bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('psgrDetailsPanel')).show();

        var detailRequest = (p.source === 'live')
            ? fetch('/admin/api/Passenger/PassengerApi.php?action=get_passenger_detail&id=' + encodeURIComponent(p.id))
                .then(function (r) { return r.json(); }).catch(function () { return null; })
            : Promise.resolve(null);
        var tripsRequest = fetch('/admin/api/Passenger/PassengerApi.php?action=get_passenger_trips&id=' + encodeURIComponent(p.id))
            .then(function (r) { return r.json(); }).catch(function () { return null; });

        Promise.all([detailRequest, tripsRequest]).then(function (results) {
            var detail = (results[0] && results[0].success) ? results[0].data : null;
            var trips = (results[1] && results[1].success && Array.isArray(results[1].data)) ? results[1].data : [];
            panelTrips = trips;
            renderPanel(p, detail || p, trips);
            document.getElementById('panelState').style.display = 'none';
            document.getElementById('panelContent').style.display = 'block';
        }).catch(function () {
            document.getElementById('panelState').style.display = 'none';
            document.getElementById('panelError').style.display = 'flex';
        });
    }

    function renderPanel(p, d, trips) {
        var isLive = (p.source === 'live');

        var docPath = d.discount_id || p.discount_id || '';
        if (docPath && docPath.indexOf('/uploads/') !== 0) docPath = '/uploads/discount_ids/' + docPath;
        panelDocPath = docPath;

        // Profile + verification
        var joined = d.created_at || p.registered_date || '';
        var profileBody =
            panelRow('Email', panelValue(d.email || p.email)) +
            panelRow('Contact', panelValue(d.phone || p.phone)) +
            panelRow('Account Type', panelValue(p.account_label)) +
            panelRow('Date Joined', panelValue(joined ? String(joined).slice(0, 10) : '')) +
            panelRow('Last Login', panelValue(d.last_login || p.last_login));
        if (d.status_reason) profileBody += panelRow('Status Reason', panelValue(d.status_reason));
        if (d.blocked_until) profileBody += panelRow('Blocked Until', panelValue(d.blocked_until));

        var verifyBody =
            panelRow('Passenger Type', panelValue(d.discount_type || p.discount_type || 'Regular')) +
            panelRow('Verification', isLive ? psgrVerifyBadge(p) : '<span style="color:#8a9a8a;">—</span>') +
            panelRow('Verified At', panelValue(d.verified_at || p.verified_at));
        if (docPath) verifyBody += panelRow('Discount Document', '<a href="#" onclick="psgrOpenPanelDoc();return false;" style="color:#57694d;">View Document</a>');

        document.getElementById('panelTabProfile').innerHTML =
            panelCard('Profile', 'bi-person', profileBody) +
            panelCard('Account & Verification', 'bi-shield-check', verifyBody);

        // Trips
        var tripsHtml = '';
        if (!trips.length) {
            tripsHtml = '<div class="psgr-panel-state" style="padding:26px 10px;"><i class="bi bi-inbox" style="font-size:2rem;color:#cdd9b5;"></i><p style="margin:0;">No trips recorded yet.</p></div>';
        } else {
            trips.slice(0, 8).forEach(function (t) {
                var route = (t.boarding_point || '—') + ' → ' + (t.destination || '—');
                tripsHtml += '<div class="psgr-panel-trip">' +
                    '<div style="min-width:0;"><div style="font-weight:700;color:#2e4836;">' + psgrEscape(route) + '</div>' +
                    '<div style="color:#8a9a8a;font-size:.7rem;margin-top:2px;">' + psgrEscape(tripDateStr(t.boarding_time)) + '</div></div>' +
                    '<div style="text-align:right;flex-shrink:0;"><div style="font-weight:700;color:#295738;">' + tripMoney(t.fare) + '</div>' +
                    '<div style="margin-top:2px;">' + tripBadge(t.payment_method) + '</div></div></div>';
            });
        }
        document.getElementById('panelTabTrips').innerHTML =
            panelCard('Recent Trips (' + trips.length + ')', 'bi-bus-front', tripsHtml) +
            (trips.length > 8 ? '<div class="psgr-panel-note">Showing the 8 most recent trips. Open the full history for all records.</div>' : '') +
            '<button type="button" class="psgr-btn psgr-btn-outline psgr-btn-sm" style="width:100%;height:42px;" onclick="psgrClosePanel();viewTrips(profIdx);"><i class="bi bi-clock-history"></i> Open Full Trip History</button>';

        // Payments (from actual trip payment records; imported rows are labelled, not assumed paid)
        var totalFare = 0, paidFare = 0, paidTrips = 0;
        var methodCounts = {};
        trips.forEach(function (t) {
            var fare = Number(t.fare || 0);
            totalFare += fare;
            if (String(t.payment_status || '').toLowerCase() === 'paid') { paidFare += fare; paidTrips++; }
            var method = String(t.payment_method || 'cash').toLowerCase();
            methodCounts[method] = (methodCounts[method] || 0) + 1;
        });
        var outstanding = isLive ? Number(p.outstanding_balance || 0) : null;
        var payState = p.payment_state || '';

        var payBody =
            panelRow('Outstanding Balance', outstanding === null
                ? '<span style="color:#8a9a8a;">—</span>'
                : (payState === 'unconfirmed'
                    ? '<span style="color:#8a9a8a;font-style:italic;" title="No completed payment record confirms a zero balance.">Unconfirmed</span>'
                    : (outstanding > 0 ? '<span style="color:#c62828;">' + psgrMoney(outstanding) + '</span>' : '<span style="color:#198754;">' + psgrMoney(0) + '</span>'))) +
            panelRow('Recorded Fares', psgrMoney(totalFare)) +
            panelRow('Payments Recorded', psgrMoney(paidFare) + (outstanding === null ? '' : ' · ' + paidTrips + ' paid trip' + (paidTrips === 1 ? '' : 's')));

        var historyHtml = '';
        if (!trips.length) {
            historyHtml = '<div class="psgr-panel-state" style="padding:22px 10px;"><p style="margin:0;">No payment records yet.</p></div>';
        } else {
            trips.slice(0, 8).forEach(function (t) {
                var status = String(t.payment_status || '').toLowerCase();
                var statusHtml = status === 'paid' ? '<span class="psgr-verify psgr-verify-verified">Paid</span>'
                    : status === 'pending' ? '<span class="psgr-verify psgr-verify-pending">Pending</span>'
                    : status === 'rejected' ? '<span class="psgr-verify psgr-verify-rejected">Rejected</span>'
                    : status === 'no fare' ? '<span class="psgr-verify psgr-verify-expired">No Fare</span>'
                    : '<span style="color:#8a9a8a;">—</span>';
                historyHtml += '<div class="psgr-panel-trip">' +
                    '<div style="min-width:0;"><div style="font-weight:700;color:#2e4836;">' + psgrEscape(t.trip_no || '—') + '</div>' +
                    '<div style="color:#8a9a8a;font-size:.7rem;margin-top:2px;">' + psgrEscape(tripDateStr(t.boarding_time)) + ' • ' + psgrEscape(tripMethodLabel(t.payment_method)) + '</div></div>' +
                    '<div style="text-align:right;flex-shrink:0;"><div style="font-weight:700;color:#295738;">' + tripMoney(t.fare) + '</div>' +
                    '<div style="margin-top:2px;">' + statusHtml + '</div></div></div>';
            });
        }
        var payNote = isLive ? '' : '<div class="psgr-panel-note">Imported trip history is not part of the live balance ledger.</div>';
        document.getElementById('panelTabPayments').innerHTML =
            panelCard('Payment Summary', 'bi-cash-coin', payBody) +
            panelCard('Recent Payments', 'bi-receipt', historyHtml) + payNote;

        // Account actions
        var actions =
            '<button type="button" class="psgr-btn psgr-btn-primary psgr-btn-sm" style="flex:1 1 140px;" onclick="psgrClosePanel();editPassenger(profIdx);"><i class="bi bi-pencil"></i> Edit Info</button>' +
            '<button type="button" class="psgr-btn psgr-btn-outline psgr-btn-sm" style="flex:1 1 140px;" onclick="psgrClosePanel();openStatus(profIdx);"><i class="bi bi-shield"></i> Change Status</button>' +
            '<button type="button" class="psgr-btn psgr-btn-outline psgr-btn-sm" style="flex:1 1 140px;" onclick="psgrClosePanel();viewTrips(profIdx);"><i class="bi bi-bus-front"></i> Trip History</button>';
        if (docPath) {
            actions += '<button type="button" class="psgr-btn psgr-btn-outline psgr-btn-sm" style="flex:1 1 140px;" onclick="psgrOpenPanelDoc()"><i class="bi bi-folder2-open"></i> Documents</button>';
        }
        if (psgrArchived) {
            actions += '<button type="button" class="psgr-btn psgr-btn-outline psgr-btn-sm" style="flex:1 1 140px;" onclick="psgrClosePanel();restorePassenger(profIdx);"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>';
        }
        document.getElementById('panelActions').innerHTML = actions;
    }

    function openTripsFromProfile() {
        psgrClosePanel();
        viewTrips(profIdx);
    }

    function viewUploadedDocs() {
        var p = psgrData[profIdx]; if (!p) return;
        var path = p.discount_id || '';
        if (path) {
            if (path.indexOf('/uploads/') !== 0) path = '/uploads/discount_ids/' + path;
            window.open(path, '_blank');
            return;
        }
        psgrAlert('error', 'No Document', 'No discount ID document is uploaded for this passenger.');
    }

    var editIdx = 0, editBase = {};

    function editPassenger(idx) { var p = psgrData[idx]; if (!p)return;
        editIdx = idx;
        document.getElementById('editPid').value = p.id;
        var n = (p.full_name||'P').split(' ').map(function (s) { return s[0] }).join('').toUpperCase().slice(0, 2)||'P';
        document.getElementById('editPAvatar').textContent = n;
        document.getElementById('editPName').textContent = p.full_name||'Passenger';
        document.getElementById('editPId').textContent = 'Passenger ID: '+String(p.id);
        document.getElementById('editPCurr').innerHTML = psgrBadge(p.status);
        document.getElementById('editName').value = p.full_name||'';
        document.getElementById('editEmail').value = p.email||'';
        document.getElementById('editContact').value = p.phone||'';
        document.getElementById('editDiscType').value = p.discount_type||'';
        document.getElementById('editDiscStatus').value = p.discount_status||'Pending';
        ['editName', 'editEmail'].forEach(function (i) {
            document.getElementById(i).closest('.edit-input-wrap').classList.remove('err');
        });
        document.getElementById('editErrName').style.display = 'none';
        document.getElementById('editErrEmail').style.display = 'none';
        editBase = { name:document.getElementById('editName').value, email:document.getElementById('editEmail').value, contact:document.getElementById('editContact').value, type:document.getElementById('editDiscType').value, status:document.getElementById('editDiscStatus').value };
        updateDiscHint();
        updateEditDocRow();
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    function editDirty() {
        return editBase.name!== document.getElementById('editName').value||
        editBase.email!== document.getElementById('editEmail').value||
        editBase.contact!== document.getElementById('editContact').value||
        editBase.type!== document.getElementById('editDiscType').value||
        editBase.status!== document.getElementById('editDiscStatus').value;
    }

    function tryEditClose() {
        var m = bootstrap.Modal.getInstance(document.getElementById('editModal'));
        if (!m)return;
        if (editDirty()) {
            psgrConfirm('Discard Changes?', 'You have unsaved changes. Discard them?', function () { m.hide();});
        } else m.hide();
    }

    function updateDiscHint() {
        var type = document.getElementById('editDiscType').value;
        var hint = document.getElementById('editDiscHint');
        if (type === '') {
            document.getElementById('editDiscStatus').disabled = true;
            document.getElementById('editDiscStatus').value = 'Verified';
            hint.style.display = 'block';
            hint.textContent = 'No verification needed for regular passengers.';
        } else {
            document.getElementById('editDiscStatus').disabled = false;
            hint.style.display = 'block';
            hint.textContent = editDiscHintText();
        }
    }

    function editDiscHintText() {
        var s = document.getElementById('editDiscStatus').value;
        var m = { Pending:'Pending — discount documents are awaiting verification.', Verified:'Verified — discount documents have been approved.', Rejected:'Rejected — submitted documents were not approved.', Expired:'Expired — verification is no longer valid.' };
        return m[s]||'';
    }

    function updateEditDocRow() {
        var p = psgrData[editIdx]; if (!p) return;
        var path = p.discount_id||'';
        if (path&&path.indexOf('/uploads/')!== 0)path = '/uploads/discount_ids/'+path;
        var hasDoc = !!path;
        document.getElementById('editDocRow').innerHTML =
        '<div style="width:34px;height:34px;border-radius:10px;background:#f0f7f0;border:1px solid #cdd9b5;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-ticket-perforated" style="color:#57694d;"></i></div>'+
        '<div style="flex:1;min-width:0;"><div style="font-size:.64rem;font-weight:600;color:#8a9a8a;text-transform:uppercase;letter-spacing:.3px;">Discount ID</div>'+
        '<div style="font-size:.78rem;font-weight:600;color:#2e4836;margin-top:2px;word-break:break-all;">'+(hasDoc?(p.discount_id.split('/').pop()||'Uploaded'):'No Discount ID Uploaded')+'</div></div>'+
        (hasDoc
        ?'<button type="button" class="psgr-btn psgr-btn-outline psgr-btn-sm" onclick="window.open(\''+path+'\',\'_blank\')" style="flex-shrink:0;"><i class="bi bi-eye"></i>View Document</button>'
        :'<button type="button" id="editDocUpBtn" class="psgr-btn psgr-btn-primary psgr-btn-sm" onclick="document.getElementById(\'editDocInput\').click()" style="flex-shrink:0;"><i class="bi bi-upload"></i>Upload Document</button>');
    }

    document.getElementById('editDiscType')?.addEventListener('change', updateDiscHint);
    document.getElementById('editDiscStatus')?.addEventListener('change', updateDiscHint);

    document.getElementById('editDocInput')?.addEventListener('change', function () {
        var input = this;
        var f = input.files&&input.files[0]; if (!f) return;
        var p = psgrData[editIdx]; if (!p) return;
        if (String(p.id).indexOf('HIST-') === 0) { psgrAlert('error', 'Not Allowed', 'This is an imported history record. Upload the document on the live passenger record instead.'); input.value = ''; return;}
        var btn = document.getElementById('editDocUpBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';}
        var fd = new FormData();
        fd.append('id', String(p.id).replace(/[^0-9]/g, ''));
        fd.append('document', f);
        fetch('/admin/api/Passenger/PassengerApi.php?action=upload_discount_doc', { method:'POST', body:fd })
        .then(function (r) { return r.json() })
        .then(function (r) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-upload"></i>Upload Document';}
            input.value = '';
            if (r&&r.success) { p.discount_id = r.path; psgrAlert('success', 'Uploaded', 'Discount document uploaded.'); updateEditDocRow();}
            else psgrAlert('error', 'Upload Failed', (r&&r.message)||'Please try again.');
        })
        .catch (function () {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-upload"></i>Upload Document';}
            input.value = '';
            psgrAlert('error', 'Error', 'Could not upload the document.');
        });
    });

    var tripIdx = 0, tripAll = [], tripFiltered = [], tripPage = 1, tripOpen = {};

    function tripMoney(v, fixed) {
        var n = Number(v||0);
        if (n%1 === 0 && !fixed) return '₱'+n.toLocaleString('en-PH');
        return '₱'+n.toLocaleString('en-PH', { minimumFractionDigits:2, maximumFractionDigits:2 });
    }

    function tripDateStr(d) {
        if (!d) return '—';
        var s = String(d).replace('T', ' ');
        return s.length>16?s.slice(0, 16):s;
    }

    function tripBadge(pm) {
        var p = String(pm||'').toLowerCase();
        var m = { cash:['#e8f6ef', '#198754', 'Cash'], gcash:['#e8f0fe', '#1d4ed8', 'GCash'], hybrid:['#f5eefb', '#7c3aed', 'Hybrid'] };
        var b = m[p]||['#f1f1f1', '#666', String(pm||'—').charAt(0).toUpperCase()+String(pm||'').slice(1)];
        return '<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:.66rem;font-weight:600;background:'+b[0]+';color:'+b[1]+';white-space:nowrap;">'+b[2]+'</span>';
    }

    function viewTrips(idx) {
        var p = psgrData[idx]; if (!p) return;
        tripIdx = idx; tripAll = []; tripFiltered = []; tripPage = 1; tripOpen = {};
        document.getElementById('tripSideAvatar').textContent = (p.full_name||'P').trim().charAt(0).toUpperCase()||'P';
        document.getElementById('tripSideName').textContent = p.full_name||'Passenger';
        document.getElementById('tripSideId').textContent = /^\d+$/.test(String(p.id))?'PAS-'+String(p.id).padStart(5, '0'):String(p.id);
        document.getElementById('tripSideDisc').innerHTML = '<span class="discount-tag">'+(p.discount_type||'Regular')+'</span>';
        document.getElementById('tripSideEmail').innerHTML = '<i class="bi bi-envelope me-1" style="color:#8aa09a;"></i>'+(p.email||'—');
        document.getElementById('tripSidePhone').innerHTML = '<i class="bi bi-telephone me-1" style="color:#8aa09a;"></i>'+(p.phone||'—');
        document.getElementById('tripSubHead').textContent = 'Trip History — '+(p.full_name||'')+' (0 trips)';
        new bootstrap.Modal(document.getElementById('tripsModal')).show();
        loadTrips();
    }

    function loadTrips() {
        var p = psgrData[tripIdx]; if (!p) return;
        document.getElementById('tripState').style.display = 'block';
        document.getElementById('tripEmpty').style.display = 'none';
        document.getElementById('tripTableWrap').style.display = 'none';
        fetch('/admin/api/Passenger/PassengerApi.php?action=get_passenger_trips&id='+encodeURIComponent(p.id))
        .then(function (r) { return r.json() })
        .then(function (r) {
            document.getElementById('tripState').style.display = 'none';
            if (!r.success || !r.data) { document.getElementById('tripEmpty').style.display = 'block'; document.getElementById('tripEmpty').innerHTML = '<i class="bi bi-inbox" style="font-size:2.6rem;color:#cdd9b5;"></i><p class="mt-2" style="color:#57694d;font-size:.82rem;">No trips found.</p>'; return; }
            tripAll = r.data;
            renderTripsSide(p);
            buildTripRoutes();
            applyTripFilters();
        })
        .catch (function () {
            document.getElementById('tripState').style.display = 'none';
            document.getElementById('tripEmpty').style.display = 'block';
            document.getElementById('tripEmpty').innerHTML = '<i class="bi bi-exclamation-triangle" style="font-size:2.2rem;color:#d46868;"></i><p class="mt-2" style="color:#57694d;font-size:.82rem;">Failed to load trips.</p>';
        });
    }

    function renderTripsSide(p) {
        var n = tripAll.length;
        document.getElementById('tripSideTrips').textContent = n;
        var spent = tripAll.reduce(function (a, t) { return a+Number(t.fare||0) }, 0);
        document.getElementById('tripSideSpent').textContent = tripMoney(spent, true);
        var counts = { cash:0, gcash:0, hybrid:0 };
        tripAll.forEach(function (t) { var k = String(t.payment_method||'cash').toLowerCase(); if (counts[k]!== undefined) counts[k]++;});
        var colors = { cash:'#6f9e4f', gcash:'#4c7fb8', hybrid:'#b89a3d' };
        var cfg = [['cash', 'Cash'], ['gcash', 'GCash'], ['hybrid', 'Hybrid']];
        var stops = [], acc = 0, legend = '';
        cfg.forEach(function (c) {
            var cnt = counts[c[0]]||0, pct = n?Math.round(cnt/n*100):0;
            stops.push(colors[c[0]]+' '+acc+'% '+(acc+pct)+'%');
            acc+= pct;
            legend+= '<div style="display:flex;align-items:center;gap:7px;margin-bottom:5px;">'+
            '<span style="width:9px;height:9px;border-radius:3px;background:'+colors[c[0]]+';flex-shrink:0;"></span>'+
            '<span style="flex:1;color:#57694d;">'+c[1]+'</span>'+
            '<span style="font-weight:600;color:#2e4836;">'+cnt+'</span>'+
            '<span style="font-size:.66rem;color:#8a9a8a;width:34px;text-align:right;">'+pct+'%</span></div>';
        });
        document.getElementById('tripDonut').style.background = n?'conic-gradient('+stops.join(',')+')':'#f0edd0';
        document.getElementById('tripDonutLegend').innerHTML = legend;
        document.getElementById('tripSubHead').textContent = 'Trip History — '+(p.full_name||'')+' ('+n+' trip'+(n === 1?'':'s')+')';
    }

    function buildTripRoutes() {
        var seen = {}, opts = ['<option value="">All Routes</option>'];
        tripAll.forEach(function (t) {
            var key = (t.boarding_point||'—')+' → '+(t.destination||'—');
            if (!seen[key]) { seen[key] = 1; opts.push('<option value="'+key.replace(/"/g, '&quot;')+'">'+key+'</option>');}
        });
        document.getElementById('fTripRoute').innerHTML = opts.join('');
    }

    function applyTripFilters() {
        var from = document.getElementById('fTripFrom').value;
        var to = document.getElementById('fTripTo').value;
        var route = document.getElementById('fTripRoute').value;
        var pay = document.getElementById('fTripPay').value;
        var q = document.getElementById('fTripSearch').value.trim().toLowerCase();
        tripFiltered = tripAll.filter(function (t) {
            var date = String(t.boarding_time||'').slice(0, 10);
            if (from && date<from) return false;
            if (to && date>to) return false;
            if (route && (t.boarding_point||'—')+' → '+(t.destination||'—')!== route) return false;
            if (pay && String(t.payment_method||'').toLowerCase()!== pay) return false;
            if (q) {
                var hay = [t.trip_no, t.driver_name, t.boarding_point, t.destination, t.bus_no].join(' ').toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        });
        tripPage = 1;
        renderTripTable();
    }

    function renderTripTable() {
        var size = Number(document.getElementById('tripPageSize').value||10);
        var total = tripFiltered.length;
        var pages = Math.max(1, Math.ceil(total/size));
        if (tripPage>pages) tripPage = pages;
        var start = (tripPage-1)*size, end = Math.min(start+size, total);
        if (!total) {
            document.getElementById('tripEmpty').style.display = 'block';
            document.getElementById('tripEmpty').innerHTML = '<i class="bi bi-funnel" style="font-size:2.2rem;color:#cdd9b5;"></i><p class="mt-2" style="color:#57694d;font-size:.82rem;">No trips match the filters.</p>';
            document.getElementById('tripTableWrap').style.display = 'none';
            return;
        }
        document.getElementById('tripEmpty').style.display = 'none';
        document.getElementById('tripTableWrap').style.display = 'block';
        var rows = tripFiltered.slice(start, end), h = '';
        rows.forEach(function (t, i) {
            var gi = start+i, route = (t.boarding_point||'—')+' → '+(t.destination||'—');
            h+= '<tr>'+
            '<td style="padding:10px 14px;font-weight:700;color:#2e4836;white-space:nowrap;">'+(t.trip_no||'—')+'</td>'+
            '<td style="color:#57694d;">'+(t.driver_name||'—')+'</td>'+
            '<td style="color:#57694d;">'+(t.boarding_point||'—')+'</td>'+
            '<td style="color:#57694d;">'+(t.destination||'—')+'</td>'+
            '<td style="font-weight:700;color:#295738;white-space:nowrap;">'+tripMoney(t.fare)+'</td>'+
            '<td>'+tripBadge(t.payment_method)+'</td>'+
            '<td style="color:#57694d;font-size:.74rem;white-space:nowrap;">'+tripDateStr(t.boarding_time)+'</td>'+
            '<td style="color:#57694d;font-size:.74rem;white-space:nowrap;">'+tripDateStr(t.dropoff_time)+'</td>'+
            '<td><div class="psgr-dropdown">'+
            '<button class="psgr-act-btn" onclick="toggleTripMenu(\''+gi+'\')" style="width:32px;height:32px;"><i class="bi bi-three-dots-vertical"></i></button>'+
            '<div class="psgr-dropdown-menu" id="tripMenu'+gi+'">'+
            '<button class="psgr-dropdown-item" onclick="toggleTripDetail('+gi+')"><i class="bi bi-eye"></i>'+(tripOpen[gi]?'Hide Details':'View Details')+'</button>'+
            '</div></div></td></tr>';
            if (tripOpen[gi]) {
                h+= '<tr style="background:#fdfbf4;"><td colspan="9" style="padding:10px 14px;">'+
                '<div style="display:flex;flex-wrap:wrap;gap:6px 22px;font-size:.74rem;color:#57694d;">'+
                '<span><b style="color:#2e4836;">Bus:</b> '+(t.bus_no||'—')+'</span>'+
                '<span><b style="color:#2e4836;">Trip ID:</b> '+(t.trip_no||'—')+'</span>'+
                '<span><b style="color:#2e4836;">Route:</b> '+route+'</span>'+
                '<span><b style="color:#2e4836;">Fare:</b> '+tripMoney(t.fare)+'</span>'+
                '<span><b style="color:#2e4836;">Boarded:</b> '+tripDateStr(t.boarding_time)+'</span>'+
                '<span><b style="color:#2e4836;">Dropped:</b> '+tripDateStr(t.dropoff_time)+'</span></div></td></tr>';
            }
        });
        document.getElementById('tripBody').innerHTML = h;
        document.getElementById('tripPageInfo').textContent = 'Showing '+(start+1)+'–'+end+' of '+total+' trip'+(total === 1?'':'s');
        document.getElementById('tripPrev').disabled = tripPage<= 1;
        document.getElementById('tripNext').disabled = tripPage>= pages;
    }

    function toggleTripMenu(gi) { var m = document.getElementById('tripMenu'+gi); if (m) m.classList.toggle('show');}
    function tripGo(d) { tripPage+= d; renderTripTable();}
    function toggleTripDetail(gi) { tripOpen[gi] = !tripOpen[gi]; renderTripTable();}
    function resetTripFilters() { ['fTripFrom', 'fTripTo', 'fTripRoute', 'fTripPay', 'fTripSearch'].forEach(function (i) { document.getElementById(i).value = '';}); applyTripFilters();}
    function openFullProfile() { bootstrap.Modal.getInstance(document.getElementById('tripsModal')).hide(); viewProfile(tripIdx);}

    function openStatus(idx) { var p = psgrData[idx]; if (!p)return;
        document.getElementById('statusPid').value = p.id;
        var n = (p.full_name||'P').split(' ').map(function (s) { return s[0] }).join('').toUpperCase().slice(0, 2)||'P';
        document.getElementById('statusPAvatar').textContent = n;
        document.getElementById('statusPName').textContent = p.full_name||'Passenger';
        document.getElementById('statusPId').textContent = 'Passenger ID: '+String(p.id);
        document.getElementById('statusPCurr').innerHTML = psgrBadge(p.status);
        document.getElementById('statusReason').value = '';
        document.getElementById('statusReasonCount').textContent = '0 / 255';
        pickStatus(p.status||'Active');
        new bootstrap.Modal(document.getElementById('statusModal')).show();
    }

    function pickStatus(s) {
        document.getElementById('statusNewStatus').value = s;
        document.querySelectorAll('#statusModal .status-opt').forEach(function (el) {
            el.classList.toggle('selected', el.getAttribute('data-status') === s);
        });
        document.getElementById('statusBlockWarn').style.display = s === 'Blocked'?'block':'none';
    }

    document.getElementById('statusReason')?.addEventListener('input', function () {
        document.getElementById('statusReasonCount').textContent = this.value.length+' / 255';
    });

    function restorePassenger(idx) { var p = psgrData[idx]; if (!p)return;
        psgrConfirm('Restore Passenger?', p.full_name, function () {
            if (String(p.id).indexOf('HIST-') === 0) { psgrAlert('error', 'Cannot Restore', 'This is a history record. Restore the live passenger instead.'); return;}
            var fd = new FormData(); fd.append('id', String(p.id).replace(/[^0-9]/g, '')); fd.append('status', 'Active'); fd.append('reason', 'Restored from archive');
            fetch('/admin/api/Passenger/PassengerApi.php?action=update_status', { method:'POST', body:fd }).then(function (r) { return r.json() }).then(function (r) {
                if (r.success) { psgrAlert('success', 'Restored', ''); loadPassengers();} else psgrAlert('error', 'Failed', r.message);
            }).catch (function (e) { psgrAlert('error', 'Error', e.message);});
        });
    }

    function exportPassengers() {
        var p = psgrQueryParams('export_passengers');
        if (psgrArchived) p.set('archived', '1');
        window.location.href = '/admin/api/Passenger/PassengerApi.php?' + p.toString();
    }

    document.getElementById('saveStatusBtn')?.addEventListener('click', function () {
        var id = document.getElementById('statusPid').value, s = document.getElementById('statusNewStatus').value, r = document.getElementById('statusReason').value;
        if (String(id).indexOf('HIST-') === 0) { psgrAlert('error', 'Cannot Update', 'This is a history record. Update the live passenger instead.'); return;}
        var btn = this; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        var fd = new FormData(); fd.append('id', id.replace(/[^0-9]/g, '')); fd.append('status', s); fd.append('reason', r);
        fetch('/admin/api/Passenger/PassengerApi.php?action=update_status', { method:'POST', body:fd }).then(function (r) { return r.text();}).then(function (t) { try { var r = JSON.parse(t); if (r.success) { bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide(); psgrAlert('success', r.message, ''); loadPassengers();}
                else psgrAlert('error', 'Failed', r.message);} catch (e) { psgrAlert('error', 'Invalid Response', t.substring(0, 500));} }).catch (function (e) { psgrAlert('error', 'Network Error', e.message);}).finally(function () { btn.disabled = false; btn.innerHTML = 'Save';});
    });

    document.getElementById('saveEditBtn')?.addEventListener('click', function () {
        var name = document.getElementById('editName').value.trim();
        var email = document.getElementById('editEmail').value.trim();
        var nameWrap = document.getElementById('editName').closest('.edit-input-wrap');
        var emailWrap = document.getElementById('editEmail').closest('.edit-input-wrap');
        var ok = true;
        nameWrap.classList.remove('err'); emailWrap.classList.remove('err');
        document.getElementById('editErrName').style.display = 'none';
        document.getElementById('editErrEmail').style.display = 'none';
        if (!name) { nameWrap.classList.add('err'); document.getElementById('editErrName').style.display = 'block'; ok = false;}
        if (!email) { emailWrap.classList.add('err'); document.getElementById('editErrEmail').style.display = 'block'; ok = false;}
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { emailWrap.classList.add('err'); document.getElementById('editErrEmail').style.display = 'block'; ok = false;}
        if (!ok)return;
        var id = document.getElementById('editPid').value;
        if (String(id).indexOf('HIST-') === 0) { psgrAlert('error', 'Cannot Edit', 'This is a history record. Edit the live passenger instead.'); return;}
        var btn = this; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        var fd = new FormData();
        fd.append('id', id.replace(/[^0-9]/g, ''));
        fd.append('full_name', name);
        fd.append('email', email);
        fd.append('phone', document.getElementById('editContact').value.trim());
        fd.append('discount_type', document.getElementById('editDiscType').value);
        fd.append('discount_status', document.getElementById('editDiscStatus').value);
        fetch('/admin/api/Passenger/PassengerApi.php?action=update_passenger', { method:'POST', body:fd }).then(function (r) { return r.text();}).then(function (t) { try { var r = JSON.parse(t); if (r.success) {
                    var p = psgrData[editIdx];
                    if (p) { p.full_name = name; p.email = email; p.phone = document.getElementById('editContact').value.trim(); p.discount_type = document.getElementById('editDiscType').value; p.discount_status = document.getElementById('editDiscStatus').value;}
                    editBase = { name:document.getElementById('editName').value, email:document.getElementById('editEmail').value, contact:document.getElementById('editContact').value, type:document.getElementById('editDiscType').value, status:document.getElementById('editDiscStatus').value };
                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide(); psgrAlert('success', r.message, ''); loadPassengers();}
                else psgrAlert('error', 'Failed', r.message);} catch (e) { psgrAlert('error', 'Invalid Response', t.substring(0, 500));} }).catch (function (e) { psgrAlert('error', 'Network Error', e.message);}).finally(function () { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Save Changes';});
    });

    document.addEventListener('click', function (e) { if (!e.target.closest('.psgr-dropdown')) { document.querySelectorAll('.psgr-dropdown-menu.show').forEach(function (m) { m.classList.remove('show');});} });
    document.addEventListener('DOMContentLoaded', loadPassengers);


    function openImportModal() {
        var modal = new bootstrap.Modal(document.getElementById('importTripModal'));
        modal.show();
    }

    function handleImportFile(fileInput) {
        var files = fileInput.files;
        if (!files || !files.length) return;
        for (var i = 0; i < files.length; i++) {
            if (!files[i].name.toLowerCase().endsWith('.csv')) {
                psgrAlert('error', 'Invalid File', '"' + files[i].name + '" is not a CSV file.');
                fileInput.value = '';
                return;
            }
        }
        var knownColumns = {
            'passenger_name': 'Required',
            'passenger_type': 'Optional',
            'student_id': 'Optional',
            'boarding_point': 'Required',
            'destination': 'Required',
            'drivers_full_name': 'Required',
            'bus_number': 'Optional',
            'trip_id': 'Optional',
            'fare': 'Required',
            'payment_method': 'Optional',
            'date_booked': 'Optional',
            'trip_date': 'Optional',
            'senior_pwd_id': 'Optional',
            'beneficiary_id': 'Optional',
            'coop_disc_id': 'Optional',
            'remarks': 'Optional',
            'time_of_departure': 'Optional',
            'time_of_arrival': 'Optional'
        };
        var fileListHtml = '<div style="margin-bottom:10px;font-size:0.82rem;color:#2e4836;font-weight:600;">' + files.length + ' file(s) selected:</div>';
        var totalRowsAll = 0;
        var validRowsAll = 0;
        var previewDone = false;
        for (var f = 0; f < files.length; f++) {
            (function (file) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    var text = e.target.result;
                    var lines = text.split('\n');
                    var dataLines = lines.length - 1;
                    totalRowsAll += dataLines;
                    if (f === 0) {
                        var headers = lines[0].split(',').map(function (h) { return h.trim().replace(/^"(.*)"$/, '$1'); });
                        var colMapHtml = '<div style="font-size:0.78rem;font-weight:600;color:#57694d;margin-bottom:6px;">Column Mapping:</div>';
                        colMapHtml += '<div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px;">';
                        var foundAny = false;
                        for (var col in knownColumns) {
                            var found = headers.indexOf(col) !== -1;
                            if (found) foundAny = true;
                            var bg = found ? '#e8f6ef' : '#fef3c7';
                            var border = found ? '#198754' : '#d97706';
                            var label = found ? '✓' : '✗';
                            colMapHtml += '<span style="font-size:0.72rem;padding:3px 8px;border-radius:6px;background:' + bg + ';border:1px solid ' + border + ';color:#333;">' +
                            label + ' ' + col + ' <span style="color:#888;">(' + knownColumns[col] + ')</span></span>';
                        }
                        colMapHtml += '</div>';
                        var missingRequired = [];
                        for (var col in knownColumns) {
                            if (knownColumns[col] === 'Required' && headers.indexOf(col) === -1) {
                                missingRequired.push(col);
                            }
                        }
                        if (missingRequired.length) {
                            colMapHtml += '<div style="font-size:0.78rem;color:#c62828;font-weight:600;margin-bottom:8px;padding:6px 10px;background:#fbe9e7;border-radius:6px;">' +
                            'Missing required column(s): ' + missingRequired.join(', ') + '</div>';
                        } else if (!foundAny) {
                            colMapHtml += '<div style="font-size:0.78rem;color:#c62828;font-weight:600;margin-bottom:8px;">No known columns found — check CSV headers</div>';
                        } else {
                            colMapHtml += '<div style="font-size:0.78rem;color:#198754;font-weight:600;margin-bottom:8px;">All required columns found ✓</div>';
                        }
                        var previewRows = [];
                        var validCount = 0;
                        var invalidCount = 0;
                        var maxPreview = dataLines;
                        for (var r = 1; r <= maxPreview; r++) {
                            var cols = lines[r] ? lines[r].split(',').map(function (c) { return c.trim().replace(/^"(.*)"$/, '$1'); }) : [];
                            if (cols.length < 2) continue;
                            var rowErrors = [];
                            for (var ri = 0; ri < missingRequired.length; ri++) {
                                var idx = headers.indexOf(missingRequired[ri]);
                                if (idx === -1 || idx >= cols.length || !cols[idx]) {
                                    rowErrors.push(missingRequired[ri]);
                                }
                            }
                            var tdIdx = headers.indexOf('time_of_departure');
                            if (tdIdx !== -1 && cols[tdIdx] && !/^\d{1,2}:\d{2}(:\d{2})?$/.test(cols[tdIdx])) {
                                rowErrors.push('time_of_departure');
                            }
                            var taIdx = headers.indexOf('time_of_arrival');
                            if (taIdx !== -1 && cols[taIdx] && !/^\d{1,2}:\d{2}(:\d{2})?$/.test(cols[taIdx])) {
                                rowErrors.push('time_of_arrival');
                            }
                            if (!rowErrors.length) { validCount++; } else { invalidCount++; }
                            previewRows.push({ cols: cols, errors: rowErrors });
                        }
                        for (var rf = 0; rf < files.length; rf++) { validRowsAll += validCount; }
                        var tableHtml = '<div style="font-size:0.78rem;font-weight:600;color:#57694d;margin-bottom:4px;">Preview (first ' + maxPreview + ' rows of ' + dataLines + '):</div>';
                        tableHtml += '<div style="max-height:220px;overflow-y:auto;border:1px solid #cdd9b5;border-radius:8px;">';
                        tableHtml += '<table class="table table-sm mb-0" style="font-size:0.75rem;"><thead style="background:#f0f7f0;position:sticky;top:0;"><tr>';
                        for (var h = 0; h < headers.length; h++) {
                            var isReq = knownColumns[headers[h]] === 'Required';
                            tableHtml += '<th style="' + (isReq ? 'font-weight:700;color:#2e4836;' : 'font-weight:400;color:#888;') + '">' + headers[h] + '</th>';
                        }
                        tableHtml += '<th style="width:70px;">Status</th></tr></thead><tbody>';
                        for (var p = 0; p < previewRows.length; p++) {
                            var row = previewRows[p];
                            var isRowValid = !row.errors.length;
                            tableHtml += '<tr style="background:' + (isRowValid ? '#fafff7' : '#fff8f8') + ';">';
                            for (var c = 0; c < row.cols.length; c++) {
                                var isEmpty = !row.cols[c];
                                var colName = headers[c] || '';
                                var isRequired = knownColumns[colName] === 'Required';
                                tableHtml += '<td style="' + (isEmpty && isRequired ? 'color:#c62828;font-weight:600;' : isEmpty ? 'color:#bbb;' : '') + '">' + (row.cols[c] || '—') + '</td>';
                            }
                            tableHtml += '<td style="text-align:center;">' +
                            (isRowValid
                            ? '<span style="color:#198754;font-weight:600;font-size:0.7rem;">✓ Valid</span>'
                            : '<span style="color:#c62828;font-weight:600;font-size:0.7rem;" title="Missing: ' + row.errors.join(', ') + '">✗ Invalid</span>') +
                            '</td></tr>';
                        }
                        tableHtml += '</tbody></table></div>';
                        tableHtml += '<div style="display:flex;gap:12px;margin-top:8px;font-size:0.78rem;">' +
                        '<span style="color:#198754;font-weight:600;">✓ ' + validCount + ' valid</span>' +
                        (invalidCount ? '<span style="color:#c62828;font-weight:600;">✗ ' + invalidCount + ' invalid</span>' : '') +
                        '<span style="color:#888;">— ' + dataLines + ' total rows in this file</span></div>';
                        document.getElementById('importPreview').innerHTML = colMapHtml + tableHtml;
                        previewDone = true;
                    }
                    var fileValidCount = 0;
                    var fileLines = lines.length - 1;
                    var reqIdx = [];
                    var firstHeaders = null;
                    if (f === 0) {
                        firstHeaders = lines[0].split(',').map(function (h) { return h.trim().replace(/^"(.*)"$/, '$1'); });
                    } else {
                    }
                    fileListHtml += '<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:#ffffff;border:1px solid #cdd9b5;border-radius:8px;margin-bottom:4px;font-size:0.8rem;">' +
                    '<span style="color:#2e4836;font-weight:500;">' + file.name + '</span>' +
                    '<span style="color:#8aa09a;">' + dataLines + ' row(s)</span></div>';
                    document.getElementById('importFileList').innerHTML = fileListHtml;
                    document.getElementById('importTotalRows').textContent = totalRowsAll + ' total row(s) across ' + files.length + ' file(s)';
                    document.getElementById('importConfirmBtn').style.display = 'inline-block';
                    document.getElementById('importFileLabel').textContent = files.length + ' file(s) selected';
                };
                reader.readAsText(file);
            })(files[f]);
        }
    }

    function confirmImport() {
        var fileInput = document.getElementById('importFileInput');
        var files = fileInput.files;
        if (!files || !files.length) return;
        var btn = document.getElementById('importConfirmBtn');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';
        var fd = new FormData();
        for (var i = 0; i < files.length; i++) {
            fd.append('csv_files[]', files[i]);
        }
        fetch('/admin/api/Passenger/importTripHistory.php', { method:'POST', body:fd })
        .then(function (r) { return r.json();})
        .then(function (r) {
            if (r.success) {
                psgrAlert('success', 'Import Complete', r.message);
                bootstrap.Modal.getInstance(document.getElementById('importTripModal')).hide();
                setTimeout(function () { location.reload();}, 1600);
            } else {
                psgrAlert('error', 'Import Failed', r.message);
            }
        })
        .catch (function (e) { psgrAlert('error', 'Network Error', e.message);})
        .finally(function () { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Confirm Import';});
    }

    function resetImportModal() {
        document.getElementById('importFileInput').value = '';
        document.getElementById('importFileList').innerHTML = '';
        document.getElementById('importPreview').innerHTML = '<p class="text-muted mb-0">Select CSV file(s) to preview</p>';
        document.getElementById('importTotalRows').textContent = '';
        document.getElementById('importConfirmBtn').style.display = 'none';
        document.getElementById('importFileLabel').textContent = 'Choose CSV file';
    }
</script>

<style>
    @media(max-width:768px) {
        #importTripModal .modal-dialog.modal-lg { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; }
    }
</style>

<div class="modal fade" id="importTripModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 overflow-hidden"
     style="border-radius:24px; background:#fef8f3; box-shadow:0 18px 50px rgba(0,0,0,.08);">

            <div class="row g-0">

                <div class="col-lg-4 d-none d-lg-block p-4"
     style="background:#f9f4da; border-right:1px solid #e7e1c9; min-height:400px;">

                    <div class="h-100 d-flex flex-column justify-content-center text-center">
                        <div style="width:72px;height:72px;border-radius:50%;background:#ffffff;border:1px solid #cdd9b5;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                            <i class="bi bi-upload" style="color:#57694d; font-size:1.8rem;"></i>
                        </div>
                        <h2 style="font-size:1.5rem;font-weight:700;color:#2e4836;margin-bottom:8px;">CSV Trip History Import</h2>
                        <p style="font-size:.85rem;color:#57694d;line-height:1.7;margin-bottom:0;padding:0 10px;">
    Upload a CSV file containing passenger trip records. Preview the data before importing to ensure accuracy.
                        </p>
                    </div>

                </div>

                <div class="col-lg-8 col-12">

                    <div class="modal-header border-0 px-4 pt-4 pb-0">
                        <h4 style="margin:0;color:#2e4836;font-weight:700;font-size:1.1rem;">
                            <i class="bi bi-upload me-2" style="color:#57694d;"></i>Import Trip History (CSV)
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="resetImportModal()"></button>
                    </div>

                    <div class="modal-body px-4 py-3">

                        <div class="mb-3">
                            <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Select CSV File</label>
                            <div>
                                <input type="file" id="importFileInput" accept=".csv" multiple onchange="handleImportFile(this)" style="display:none;">
                                <button class="btn" onclick="document.getElementById('importFileInput').click()"
                    style="height:50px;padding:0 24px;border-radius:14px;background:#ffffff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;">
                                    <i class="bi bi-folder2-open me-2"></i><span id="importFileLabel">Choose CSV file</span>
                                </button>
                            </div>
                            <div style="font-size:.8rem;color:#8aa09a;margin-top:6px;">Upload one or more CSV files containing passenger trip records. CSV must have columns: passenger_name, boarding_point, destination, drivers_full_name, fare (required) and passenger_type, bus_number, payment_method, trip_date, time_of_departure, time_of_arrival (optional).</div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span style="font-size:.85rem;font-weight:600;color:#57694d;">Preview</span>
                                <small class="text-muted" id="importTotalRows"></small>
                            </div>
                            <div id="importFileList" style="margin-bottom:6px;"></div>
                            <div id="importPreview" style="background:#ffffff;border:1.5px solid #cdd9b5;border-radius:14px;padding:14px;max-height:250px;overflow-y:auto;">
                                <p class="text-muted mb-0">Select CSV file(s) to preview</p>
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="button" class="btn" data-bs-dismiss="modal" onclick="resetImportModal()"
            style="height:48px;border-radius:14px;background:#ffffff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;flex:1;">Cancel</button>
                            <button type="button" id="importConfirmBtn" class="btn text-white" onclick="confirmImport()"
            style="display:none;height:48px;border-radius:14px;border:none;background:#57694d;font-weight:600;flex:1;font-size:.9rem;">
                                <i class="bi bi-check2 me-1"></i>Confirm Import
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>



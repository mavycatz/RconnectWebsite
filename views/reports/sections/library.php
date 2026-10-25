<?php
declare(strict_types = 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/reports/report_types.php';

$rgenRegistry = rgen_registry();
$rgenGroups = rgen_type_groups();
$rgenPeriodModes = rgen_period_modes();

$rgenGrouped = [];
foreach ($rgenRegistry as $key => $def) {
    $group = $rgenGroups[$key] ?? 'Reports';
    $rgenGrouped[$group][$key] = $def;
}
?>
<style>
    .rlib, .rlib * { font-family: 'Poppins', sans-serif; box-sizing: border-box; }
    .rlib { max-width: 1440px; margin: 0 auto; color: #2e4836; }

    .rlib-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 14px; }
    .rlib-head h1 { font-size: 1.4rem; font-weight: 800; color: #295738; margin: 0 0 2px; }
    .rlib-head p { margin: 0; font-size: .82rem; color: #8a9a8a; font-weight: 500; }
    .rlib-head-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .rlib-count { display: inline-flex; align-items: center; gap: 6px; background: #f4f1e6; border: 1px solid #e5e0a4; border-radius: 20px; padding: 7px 14px; font-size: .72rem; font-weight: 600; color: #6b7a63; }

    .rlib-btn { display: inline-flex; align-items: center; gap: 6px; border-radius: 20px; font-size: .78rem; font-weight: 600; padding: 8px 16px; cursor: pointer; transition: all .18s ease; border: 1px solid #d8d2b6; background: #fff; color: #295738; text-decoration: none; }
    .rlib-btn:hover { background: #f4f1e6; }
    .rlib-btn:disabled { opacity: .5; cursor: not-allowed; }
    .rlib-btn-primary { background: #295738; border-color: #295738; color: #fff; }
    .rlib-btn-primary:hover { background: #1e4229; }
    .rlib-btn-danger { border-color: #e7b9b4; color: #b94a48; }
    .rlib-btn-danger:hover { background: #fbe9e7; }

    .rlib-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
    .rlib-tab { display: inline-flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #e5e0a4; border-radius: 20px; padding: 8px 18px; font-size: .8rem; font-weight: 700; color: #6f6d75; cursor: pointer; transition: all .18s ease; }
    .rlib-tab:hover { border-color: #295738; color: #295738; }
    .rlib-tab.active { background: #295738; border-color: #295738; color: #fff; }
    .rlib-tab .rlib-badge { display: inline-flex; min-width: 20px; justify-content: center; background: rgba(0,0,0,.08); border-radius: 10px; padding: 1px 7px; font-size: .68rem; }
    .rlib-tab.active .rlib-badge { background: rgba(255,255,255,.22); }

    .rlib-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; background: #fff; border: 1px solid #e5e0a4; border-radius: 16px; padding: 10px 12px; margin-bottom: 10px; }
    .rlib-filters .rlib-search { position: relative; flex: 1 1 220px; min-width: 180px; }
    .rlib-filters .rlib-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: .78rem; }
    .rlib-filters .rlib-search input { width: 100%; border: 1px solid #e5e0a4; border-radius: 20px; padding: 7px 14px 7px 32px; font-size: .78rem; outline: none; }
    .rlib-filters select, .rlib-filters input[type="date"] { border: 1px solid #e5e0a4; border-radius: 10px; padding: 7px 10px; font-size: .76rem; color: #2e4836; background: #fffdf8; outline: none; }
    .rlib-filters label { font-size: .68rem; font-weight: 600; color: #8a9a8a; text-transform: uppercase; letter-spacing: .04em; }
    .rlib-filter-group { display: flex; align-items: center; gap: 6px; }
    .rlib-chipbar { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
    .rlib-chip { display: inline-flex; align-items: center; gap: 6px; background: #e8f2e6; border: 1px solid #cdd9b5; color: #295738; border-radius: 20px; padding: 3px 10px; font-size: .68rem; font-weight: 600; }

    .rlib-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; }
    .rlib-card { background: #fffdf8; border: 1px solid #e5e0a4; border-radius: 16px; padding: 16px 18px; display: flex; flex-direction: column; gap: 8px; box-shadow: 0 2px 8px rgba(41,87,56,.05); transition: transform .18s ease, box-shadow .18s ease; }
    .rlib-card:hover { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(41,87,56,.12); }
    .rlib-card-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
    .rlib-card-title { font-size: .92rem; font-weight: 700; color: #295738; line-height: 1.3; overflow-wrap: anywhere; }
    .rlib-status { display: inline-flex; align-items: center; gap: 4px; border-radius: 20px; padding: 3px 10px; font-size: .64rem; font-weight: 700; white-space: nowrap; border: 1px solid; flex-shrink: 0; }
    .rlib-status.is-available { color: #2e7d32; background: #eaf6ea; border-color: #c7e3c7; }
    .rlib-status.is-archived { color: #7a6f4f; background: #f3eee2; border-color: #e2d9be; }
    .rlib-status.is-missing { color: #b94a48; background: #fbe9e7; border-color: #eccac5; }
    .rlib-card-desc { font-size: .76rem; color: #6b786d; line-height: 1.5; }
    .rlib-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px 12px; font-size: .7rem; color: #6b786d; }
    .rlib-meta b { color: #2e4836; font-weight: 600; }
    .rlib-type-chip { display: inline-flex; align-items: center; gap: 5px; background: #f4f1e6; border: 1px solid #e5e0a4; color: #6b7a63; border-radius: 12px; padding: 2px 8px; font-size: .64rem; font-weight: 700; }
    .rlib-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: auto; padding-top: 8px; border-top: 1px solid #f0ede0; }

    .rlib-empty { grid-column: 1 / -1; text-align: center; padding: 54px 20px; background: #fffdf8; border: 1px dashed #cdd9b5; border-radius: 16px; }
    .rlib-empty i { font-size: 2.4rem; color: #cdd9b5; display: block; margin-bottom: 10px; }
    .rlib-empty h6 { font-weight: 700; color: #295738; margin: 0 0 4px; }
    .rlib-empty p { font-size: .8rem; color: #94a3b8; margin: 0; }

    .rlib-preview-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 18px; font-size: .8rem; margin-bottom: 12px; }
    .rlib-preview-meta .k { font-size: .64rem; text-transform: uppercase; letter-spacing: .05em; color: #8a9a8a; }
    .rlib-preview-meta .v { font-weight: 600; color: #2e4836; overflow-wrap: anywhere; }
    .rlib-preview-frame { width: 100%; height: 60vh; border: 1px solid #e5e0a4; border-radius: 12px; background: #fff; }
    .rlib-note { background: #f6f8ec; border: 1px solid #e5e0a4; border-radius: 10px; padding: 10px 12px; font-size: .76rem; color: #7a6f12; }
    @media (max-width: 575px) { .rlib-meta, .rlib-preview-meta { grid-template-columns: 1fr; } }

    .rgen-step { background: #fff; border: 1px solid #e5e0a4; border-radius: 16px; padding: 14px 16px; margin-bottom: 12px; }
    .rgen-step-head { display: flex; align-items: center; gap: 8px; font-size: .8rem; font-weight: 800; color: #295738; margin-bottom: 10px; text-transform: uppercase; letter-spacing: .05em; }
    .rgen-step-no { display: inline-flex; width: 22px; height: 22px; border-radius: 50%; background: #295738; color: #fff; align-items: center; justify-content: center; font-size: .7rem; flex-shrink: 0; }
    .rgen-desc { font-size: .76rem; color: #6b786d; margin-top: 6px; line-height: 1.55; }
    .rgen-filters { display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: flex-end; }
    .rgen-field { display: flex; flex-direction: column; gap: 4px; }
    .rgen-field label { font-size: .62rem; font-weight: 700; color: #8a9a8a; text-transform: uppercase; letter-spacing: .05em; }
    .rgen-field select, .rgen-field input { border: 1px solid #e5e0a4; border-radius: 10px; padding: 7px 10px; font-size: .78rem; background: #fffdf8; color: #2e4836; outline: none; min-width: 160px; }
    .rgen-field-wide { flex: 1 1 100%; }
    .rgen-seg { display: flex; gap: 4px; flex-wrap: wrap; }
    .rgen-seg button { border: 1px solid #e5e0a4; background: #fff; color: #295738; border-radius: 16px; padding: 6px 12px; font-size: .72rem; font-weight: 700; cursor: pointer; }
    .rgen-seg button.active { background: #295738; border-color: #295738; color: #fff; }
    .rgen-dates { display: flex; gap: 6px; align-items: center; margin-top: 8px; font-size: .72rem; color: #8a9a8a; }
    .rgen-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
    .rgen-error { margin-top: 8px; background: #fbe9e7; border: 1px solid #eccac5; color: #b94a48; border-radius: 10px; padding: 8px 12px; font-size: .74rem; }
    .rgen-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 18px; font-size: .74rem; margin-bottom: 12px; }
    .rgen-meta .k { font-size: .6rem; text-transform: uppercase; letter-spacing: .05em; color: #8a9a8a; }
    .rgen-meta .v { font-weight: 600; color: #2e4836; overflow-wrap: anywhere; }
    .rgen-kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; margin-bottom: 12px; }
    .rgen-kpi { background: #fef8f3; border: 1px solid #e5e0a4; border-radius: 12px; padding: 10px 12px; }
    .rgen-kpi .k { font-size: .6rem; text-transform: uppercase; letter-spacing: .05em; color: #8a9a8a; }
    .rgen-kpi .v { font-size: .95rem; font-weight: 800; color: #295738; margin-top: 2px; }
    .rgen-kpi .h { font-size: .62rem; color: #7c847c; margin-top: 3px; }
    .rgen-block { margin-bottom: 12px; }
    .rgen-block-title { font-size: .68rem; font-weight: 800; color: #295738; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
    .rgen-findings { margin: 0; padding-left: 18px; font-size: .76rem; color: #3a4a3c; line-height: 1.6; }
    .rgen-table-wrap { overflow-x: auto; border: 1px solid #f0ede0; border-radius: 12px; }
    table.rgen-table { width: 100%; border-collapse: collapse; font-size: .72rem; min-width: 640px; }
    .rgen-table th { text-align: left; padding: 7px 9px; background: #f6f8ec; color: #2e4836; font-size: .62rem; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; border-bottom: 1px solid #e5e0a4; }
    .rgen-table td { padding: 6px 9px; border-bottom: 1px solid #f0ede0; color: #3a4a3c; vertical-align: top; }
    .rgen-note-line { font-size: .68rem; color: #8a9a8a; margin-top: 6px; }
    .rgen-compare { width: 100%; border-collapse: collapse; font-size: .72rem; margin-bottom: 12px; }
    .rgen-compare th, .rgen-compare td { border-bottom: 1px solid #f0ede0; padding: 5px 8px; text-align: left; }
    .rgen-compare th { font-size: .62rem; text-transform: uppercase; color: #8a9a8a; }
    .rgen-compare td.num, .rgen-compare th.num { text-align: right; }
    @media (max-width: 575px) { .rgen-meta { grid-template-columns: 1fr; } }
</style>

<section class="rlib" id="reportsLibrary">

    <header class="rlib-head">
        <div>
            <h1>Reports Center</h1>
            <p>Browse, preview, and manage every generated report in one place.</p>
        </div>
        <div class="rlib-head-actions">
            <span class="rlib-count" id="rlibCount"><i class="bi bi-files"></i> —</span>
            <button type="button" class="rlib-btn rlib-btn-primary" id="rlibGenerateBtn"><i class="bi bi-plus-lg"></i> Generate New Report</button>
        </div>
    </header>

    <div class="rlib-tabs" id="rlibTabs">
        <button type="button" class="rlib-tab" data-scope="recent">Recent Reports <span class="rlib-badge" id="rlibTabRecent">0</span></button>
        <button type="button" class="rlib-tab active" data-scope="all">All Reports <span class="rlib-badge" id="rlibTabAll">0</span></button>
        <button type="button" class="rlib-tab" data-scope="archived">Archived Reports <span class="rlib-badge" id="rlibTabArchived">0</span></button>
    </div>

    <div class="rlib-filters">
        <div class="rlib-search">
            <i class="bi bi-search"></i>
            <input type="text" id="rlibSearch" placeholder="Search title, type, driver, bus, description...">
        </div>
        <div class="rlib-filter-group">
            <label for="rlibType">Type</label>
            <select id="rlibType"><option value="">All types</option></select>
        </div>
        <div class="rlib-filter-group">
            <label for="rlibPeriod">Period</label>
            <select id="rlibPeriod">
                <option value="">All periods</option>
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="semi_annually">Semi-Annual</option>
                <option value="annually">Annual</option>
                <option value="custom">Custom range</option>
            </select>
        </div>
        <div class="rlib-filter-group" id="rlibPeriodCustom" style="display:none;">
            <input type="date" id="rlibPeriodStart" aria-label="Period from">
            <span style="color:#94a3b8;font-size:.7rem;">to</span>
            <input type="date" id="rlibPeriodEnd" aria-label="Period to">
        </div>
        <div class="rlib-filter-group">
            <label for="rlibGenFrom">Generated</label>
            <input type="date" id="rlibGenFrom" aria-label="Generated from">
            <span style="color:#94a3b8;font-size:.7rem;">to</span>
            <input type="date" id="rlibGenTo" aria-label="Generated to">
        </div>
        <div class="rlib-filter-group">
            <label for="rlibFormat">Format</label>
            <select id="rlibFormat">
                <option value="">All formats</option>
                <option value="PDF">PDF</option>
                <option value="XLSX">XLSX</option>
                <option value="CSV">CSV</option>
            </select>
        </div>
        <div class="rlib-filter-group">
            <label for="rlibStatus">Status</label>
            <select id="rlibStatus">
                <option value="">All statuses</option>
                <option value="available">Available</option>
                <option value="archived">Archived</option>
            </select>
        </div>
        <button type="button" class="rlib-btn" id="rlibClear"><i class="bi bi-x-circle"></i> Clear Filters</button>
    </div>
    <div class="rlib-chipbar" id="rlibActiveFilters"></div>

    <div class="rlib-list" id="rlibList">
        <div class="rlib-empty"><i class="bi bi-hourglass-split"></i><h6>Loading reports...</h6><p>Fetching the report library.</p></div>
    </div>
</section>

<div class="modal fade" id="rlibPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border:1px solid #e5e0a4;border-radius:20px;background:#fffdf8;">
            <div class="modal-header" style="border-bottom:1px solid #f0ede0;padding:14px 20px;background:#fef8f3;border-radius:18px 18px 0 0;">
                <div>
                    <h5 class="modal-title" style="font-size:.95rem;font-weight:700;color:#295738;" id="rlibPreviewTitle">Report</h5>
                    <div style="font-size:.72rem;color:#8a9a8a;" id="rlibPreviewSub">—</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:16px 20px;" id="rlibPreviewBody"></div>
            <div class="modal-footer" style="border-top:1px solid #f0ede0;padding:12px 20px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;" id="rlibPreviewFooter"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="rlibGenerateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border:1px solid #e5e0a4;border-radius:20px;background:#fffdf8;">
            <div class="modal-header" style="border-bottom:1px solid #f0ede0;padding:14px 20px;background:#fef8f3;border-radius:18px 18px 0 0;">
                <div>
                    <h5 class="modal-title" style="font-size:.95rem;font-weight:700;color:#295738;"><i class="bi bi-plus-lg me-1"></i> Generate New Report</h5>
                    <div style="font-size:.72rem;color:#8a9a8a;">Configure filters, preview the real data, then generate.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:16px 20px;">

                <section class="rgen-step">
                    <div class="rgen-step-head"><span class="rgen-step-no">1</span> Report</div>
                    <label style="font-size:.62rem;font-weight:700;color:#8a9a8a;text-transform:uppercase;letter-spacing:.05em;">Report type</label>
                    <select id="rlibReportType" class="form-select mt-1" style="border-radius:12px;border:1px solid #e5e0a4;font-size:.82rem;max-width:520px;">
                        <?php foreach ($rgenGrouped as $groupName => $types): ?>
                            <optgroup label="<?= htmlspecialchars((string)$groupName, ENT_QUOTES) ?>">
                                <?php foreach ($types as $typeKey => $typeDef): ?>
                                    <option value="<?= htmlspecialchars((string)$typeKey, ENT_QUOTES) ?>"><?= htmlspecialchars((string)$typeDef['label'], ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <div class="rgen-desc" id="rlibGenerateDesc"></div>
                </section>

                <section class="rgen-step">
                    <div class="rgen-step-head"><span class="rgen-step-no">2</span> Filters</div>
                    <div class="rgen-filters" id="rgenFilterControls"></div>
                    <div class="rgen-actions">
                        <button type="button" class="rlib-btn rlib-btn-primary" id="rgenApplyBtn"><i class="bi bi-funnel"></i> Apply Filters</button>
                        <button type="button" class="rlib-btn" id="rgenResetBtn"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                    </div>
                    <div class="rgen-error" id="rgenFilterError" style="display:none;"></div>
                    <div class="rlib-chipbar" id="rgenAppliedChips" style="margin-top:10px;margin-bottom:0;"></div>
                </section>

                <section class="rgen-step">
                    <div class="rgen-step-head"><span class="rgen-step-no">3</span> Preview</div>
                    <div id="rgenPreviewBody">
                        <div class="rlib-empty" style="padding:32px 16px;">
                            <i class="bi bi-eye"></i>
                            <h6>No preview yet</h6>
                            <p>Choose a report type and filters, then press Apply Filters to preview the matching records.</p>
                        </div>
                    </div>
                </section>

                <section class="rgen-step">
                    <div class="rgen-step-head"><span class="rgen-step-no">4</span> Generate &amp; export</div>
                    <div class="rgen-actions" style="margin-top:0;">
                        <button type="button" class="rlib-btn rlib-btn-primary" id="rlibGenerateSubmit"><i class="bi bi-file-earmark-pdf"></i> Generate PDF Report</button>
                        <button type="button" class="rlib-btn" id="rgenCsvBtn"><i class="bi bi-filetype-csv"></i> Export CSV</button>
                        <button type="button" class="rlib-btn" id="rgenPrintBtn"><i class="bi bi-printer"></i> Print preview</button>
                    </div>
                    <div class="rlib-note mt-3" id="rlibGenerateNote" style="display:none;"></div>
                </section>

            </div>
        </div>
    </div>
</div>

<script>
    window.RGEN_REGISTRY = <?= json_encode($rgenRegistry, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    window.RGEN_GROUPS = <?= json_encode($rgenGroups, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.RGEN_PERIOD_MODES = <?= json_encode($rgenPeriodModes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<script>
    (function () {
        'use strict';

        var RLIB = { scope: 'all', items: [], typesLoaded: false, busy: false };
        var RGEN = {
            registry: window.RGEN_REGISTRY || {},
            periodModes: window.RGEN_PERIOD_MODES || {},
            options: {},
            optionsLoaded: false,
            appliedKey: null,
            lastPreview: null,
            busy: false,
            filters: { period: 'monthly', start: '', end: '', driver_id: '', bus_no: '', status: '', search: '', route: '' }
        };

        function esc(v) {
            return String(v === null || v === undefined ? '' : v)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
        function el(id) { return document.getElementById(id); }

        function fmtManila(dt) {
            var s = String(dt || '');
            var p = s.split(' ');
            if (p.length < 2) return fmtDateOnly(s);
            var d = p[0].split('-'), t = p[1].split(':');
            var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            var m = parseInt(d[1], 10), day = parseInt(d[2], 10);
            if (!m || !day) return s;
            var hh = parseInt(t[0], 10);
            var ampm = hh >= 12 ? 'PM' : 'AM';
            var h12 = ((hh + 11) % 12) + 1;
            return months[m - 1] + ' ' + day + ', ' + d[0] + ' · ' + h12 + ':' + (t[1] || '00') + ' ' + ampm + ' (PHT)';
        }
        function fmtDateOnly(dt) {
            var s = String(dt || '').slice(0, 10);
            var d = s.split('-');
            if (d.length !== 3) return s || '—';
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var m = parseInt(d[1], 10), day = parseInt(d[2], 10);
            if (!m || !day) return s;
            return months[m - 1] + ' ' + day + ', ' + d[0];
        }
        function fmtMoney(v) {
            var n = Number(v || 0);
            if (isNaN(n)) return String(v);
            return '₱' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        function fmtCell(v, type) {
            var raw = String(v === null || v === undefined ? '' : v).trim();
            if (raw === '' || raw.indexOf('0000-00-00') === 0) return '—';
            if (type === 'money') return fmtMoney(raw);
            if (type === 'date') return fmtDateOnly(raw);
            if (type === 'datetime') return fmtManila(raw);
            return raw;
        }
        function fmtSize(bytes) {
            if (bytes === null || bytes === undefined || isNaN(bytes)) return '—';
            var b = Number(bytes);
            if (b < 1024) return b + ' B';
            if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
            return (b / 1048576).toFixed(2) + ' MB';
        }
        function fileUrl(item) {
            if (!item.file) return '';
            return '/' + (item.archived ? 'archived_reports/' : 'generated_reports/') + item.file;
        }

        function activeFilters() {
            var f = [];
            var q = el('rlibSearch').value.trim();
            if (q) f.push(['q', 'Search: "' + q + '"']);
            var t = el('rlibType').value; if (t) f.push(['type', 'Type: ' + t]);
            var p = el('rlibPeriod').value; if (p) {
                var label = el('rlibPeriod').selectedOptions[0].textContent;
                if (p === 'custom') {
                    label += ' (' + (el('rlibPeriodStart').value || '…') + ' to ' + (el('rlibPeriodEnd').value || '…') + ')';
                }
                f.push(['period', 'Period: ' + label]);
            }
            var gf = el('rlibGenFrom').value, gt = el('rlibGenTo').value;
            if (gf || gt) f.push(['gen', 'Generated: ' + (gf || '…') + ' to ' + (gt || '…')]);
            var fm = el('rlibFormat').value; if (fm) f.push(['format', 'Format: ' + fm]);
            var st = el('rlibStatus').value; if (st) f.push(['status', 'Status: ' + st]);
            return f;
        }

        function query() {
            var p = new URLSearchParams();
            p.set('scope', RLIB.scope);
            var q = el('rlibSearch').value.trim(); if (q) p.set('q', q);
            var t = el('rlibType').value; if (t) p.set('type', t);
            var per = el('rlibPeriod').value; if (per) p.set('period_mode', per);
            if (per === 'custom') {
                p.set('period_start', el('rlibPeriodStart').value || '');
                p.set('period_end', el('rlibPeriodEnd').value || '');
            }
            p.set('gen_from', el('rlibGenFrom').value || '');
            p.set('gen_to', el('rlibGenTo').value || '');
            var fm = el('rlibFormat').value; if (fm) p.set('format', fm);
            var st = el('rlibStatus').value; if (st) p.set('status', st);
            return p.toString();
        }

        function renderChips() {
            var chips = activeFilters();
            var wrap = el('rlibActiveFilters');
            if (!chips.length) { wrap.innerHTML = ''; return; }
            wrap.innerHTML = chips.map(function (c) {
                return '<span class="rlib-chip"><i class="bi bi-funnel-fill"></i> ' + esc(c[1]) + '</span>';
            }).join('');
        }

        function cardActions(item) {
            var html = '';
            html += '<button type="button" class="rlib-btn" data-action="view" data-id="' + esc(item.id) + '"><i class="bi bi-eye"></i> View</button>';
            if (item.file) {
                html += '<a class="rlib-btn" href="' + esc(fileUrl(item)) + '" target="_blank" rel="noopener"><i class="bi bi-download"></i> Download</a>';
                html += '<button type="button" class="rlib-btn" data-action="print" data-id="' + esc(item.id) + '"><i class="bi bi-printer"></i> Print</button>';
            }
            if (item.file && !item.archived) {
                html += '<button type="button" class="rlib-btn" data-action="archive" data-id="' + esc(item.id) + '"><i class="bi bi-archive"></i> Archive</button>';
            }
            if (item.file && item.archived) {
                html += '<button type="button" class="rlib-btn" data-action="restore" data-id="' + esc(item.id) + '"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>';
            }
            return html;
        }

        function renderCards(items) {
            var list = el('rlibList');
            if (!items.length) {
                list.innerHTML = '<div class="rlib-empty"><i class="bi bi-inbox"></i><h6>No reports match your filters</h6><p>Try a different search, type, or date range, or clear the filters.</p></div>';
                return;
            }
            list.innerHTML = items.map(function (item) {
                var statusClass = item.status === 'Archived' ? 'is-archived' : 'is-available';
                var period = item.period && item.period.label ? item.period.label : 'Period not recorded';
                var freq = item.frequency ? ' · ' + esc(item.frequency) : '';
                return '<article class="rlib-card">' +
                    '<div class="rlib-card-top">' +
                    '<div class="rlib-card-title">' + esc(item.title) + '</div>' +
                    '<span class="rlib-status ' + statusClass + '">' + esc(item.status) + '</span>' +
                    '</div>' +
                    '<div><span class="rlib-type-chip"><i class="bi bi-tag"></i> ' + esc(item.type) + '</span></div>' +
                    '<div class="rlib-card-desc">' + esc(item.description) + '</div>' +
                    '<div class="rlib-meta">' +
                    '<div><span class="k">Covered period</span><br><b>' + esc(period) + '</b>' + freq + '</div>' +
                    '<div><span class="k">Generated</span><br><b>' + esc(fmtManila(item.generated_at)) + '</b></div>' +
                    '<div><span class="k">Generated by</span><br><b>' + esc(item.generated_by || 'System process') + '</b></div>' +
                    '<div><span class="k">File</span><br><b>' + esc(item.format) + '</b> · ' + esc(fmtSize(item.size_bytes)) + '</div>' +
                    '</div>' +
                    '<div class="rlib-actions">' + cardActions(item) + '</div>' +
                    '</article>';
            }).join('');
        }

        function loadLibrary() {
            var list = el('rlibList');
            list.innerHTML = '<div class="rlib-empty"><i class="bi bi-hourglass-split"></i><h6>Loading reports...</h6><p>Fetching the report library.</p></div>';
            fetch('/admin/api/reports/library.php?' + query(), { cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) throw new Error(res.message || 'Failed to load reports.');
                    RLIB.items = res.data || [];
                    el('rlibCount').innerHTML = '<i class="bi bi-files"></i> ' + res.total + ' report' + (res.total === 1 ? '' : 's');
                    el('rlibTabRecent').textContent = res.counts.recent;
                    el('rlibTabAll').textContent = res.counts.all;
                    el('rlibTabArchived').textContent = res.counts.archived;
                    if (!RLIB.typesLoaded) {
                        var typeSel = el('rlibType');
                        (res.types || []).forEach(function (t) {
                            var o = document.createElement('option');
                            o.value = t; o.textContent = t;
                            typeSel.appendChild(o);
                        });
                        RLIB.typesLoaded = true;
                    }
                    renderChips();
                    renderCards(RLIB.items);
                })
                .catch(function (err) {
                    console.error(err);
                    list.innerHTML = '<div class="rlib-empty"><i class="bi bi-exclamation-circle" style="color:#b94a48;"></i><h6>Could not load reports</h6><p>' + esc(err.message || 'Please try again.') + '</p></div>';
                });
        }

        function findItem(id) {
            for (var i = 0; i < RLIB.items.length; i++) {
                if (RLIB.items[i].id === id) return RLIB.items[i];
            }
            return null;
        }

        function previewRow(label, value) {
            return '<div><span class="k">' + esc(label) + '</span><br><span class="v">' + esc(value) + '</span></div>';
        }

        function openPreview(item) {
            el('rlibPreviewTitle').textContent = item.title;
            el('rlibPreviewSub').textContent = item.type + ' · ' + item.status;

            var period = item.period && item.period.label ? item.period.label : 'Not recorded';
            var body = '<div class="rlib-preview-meta">' +
                previewRow('Report purpose', item.description) +
                previewRow('Covered period', period + (item.frequency ? ' · ' + item.frequency : '')) +
                previewRow('Generated', fmtManila(item.generated_at)) +
                previewRow('Generated by', item.generated_by || 'System process') +
                previewRow('Format', item.format) +
                previewRow('File size', fmtSize(item.size_bytes)) +
                '</div>';

            if (item.file) {
                var url = fileUrl(item);
                if (item.format === 'PDF') {
                    body += '<iframe class="rlib-preview-frame" src="' + esc(url) + '" title="' + esc(item.title) + '"></iframe>';
                } else {
                    body += '<div class="rlib-note">This ' + esc(item.format) + ' file (' + esc(fmtSize(item.size_bytes)) + ') can be downloaded or opened in a new tab. Inline preview is available for PDF reports.</div>';
                }
            } else {
                body += '<div class="rlib-note">This report is stored as a database record. Downloadable file preview is not available for it.</div>';
            }
            el('rlibPreviewBody').innerHTML = body;

            var footer = '';
            if (item.file) {
                footer += '<a class="rlib-btn" href="' + esc(fileUrl(item)) + '" target="_blank" rel="noopener"><i class="bi bi-download"></i> Download</a>';
                footer += '<button type="button" class="rlib-btn" id="rlibPreviewPrint"><i class="bi bi-printer"></i> Print</button>';
            }
            footer += '<button type="button" class="rlib-btn rlib-btn-primary" data-bs-dismiss="modal">Close</button>';
            el('rlibPreviewFooter').innerHTML = footer;

            var printBtn = el('rlibPreviewPrint');
            if (printBtn) printBtn.addEventListener('click', function () { printItem(item); });

            bootstrap.Modal.getOrCreateInstance(el('rlibPreviewModal')).show();
        }

        function printItem(item) {
            if (item.file) {
                window.open(fileUrl(item), '_blank', 'noopener');
                return;
            }
            var w = window.open('', '_blank', 'width=900,height=1000');
            if (!w) { alert('Please allow pop-ups to print the report.'); return; }
            w.document.write('<!doctype html><html><head><title>' + esc(item.title) + '</title><style>body{font-family:Poppins,Arial,sans-serif;color:#2e4836;padding:26px;}h1{font-size:20px;color:#295738;} .m{color:#6f7d6f;font-size:12px;margin-bottom:14px;}</style></head><body>');
            w.document.write('<h1>' + esc(item.title) + '</h1><div class="m">' + esc(item.type) + '</div>');
            w.document.write('<p>' + esc(item.description) + '</p>');
            w.document.write('<p><b>Covered period:</b> ' + esc(item.period && item.period.label ? item.period.label : 'Not recorded') + '</p>');
            w.document.write('<p><b>Generated:</b> ' + esc(fmtManila(item.generated_at)) + '</p>');
            w.document.close();
            w.focus();
            setTimeout(function () { w.print(); }, 250);
        }

        function archiveItem(item) {
            if (!confirm('Archive "' + item.title + '"? The file is kept and can be restored later.')) return;
            fetch('/admin/api/reports/archive_file.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file: item.file })
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (!res.success) throw new Error(res.message || 'Archive failed.');
                loadLibrary();
            }).catch(function (err) { alert(err.message || 'Archive failed.'); });
        }

        function restoreItem(item) {
            if (!confirm('Restore "' + item.title + '" to the active library?')) return;
            fetch('/admin/api/reports/RestoreArchivedReportAPI.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file: item.file })
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (!res.success) throw new Error(res.message || 'Restore failed.');
                loadLibrary();
            }).catch(function (err) { alert(err.message || 'Restore failed.'); });
        }

        /* ------------------------------------------------------------------
         * Generate report configuration
         * ------------------------------------------------------------------ */

        function rgenType() { return el('rlibReportType').value; }
        function rgenDef() { return RGEN.registry[rgenType()] || null; }

        function rgenDefaults() {
            RGEN.filters = { period: 'monthly', start: '', end: '', driver_id: '', bus_no: '', status: '', search: '', route: '' };
        }

        function rgenLoadOptions(cb) {
            if (RGEN.optionsLoaded) { cb(); return; }
            fetch('/admin/api/reports/report-options.php', { cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    RGEN.options = (res && res.success) ? res : {};
                    RGEN.optionsLoaded = true;
                    cb();
                })
                .catch(function () {
                    RGEN.options = {};
                    RGEN.optionsLoaded = true;
                    cb();
                });
        }

        function rgenSelectField(id, label, options, value, placeholder) {
            var html = '<div class="rgen-field"><label for="' + id + '">' + esc(label) + '</label><select id="' + id + '">';
            html += '<option value="">' + esc(placeholder) + '</option>';
            (options || []).forEach(function (o) {
                var val = typeof o === 'string' ? o : String(o.id);
                var name = typeof o === 'string' ? o : String(o.name || o.id);
                html += '<option value="' + esc(val) + '"' + (String(value) === String(val) ? ' selected' : '') + '>' + esc(name) + '</option>';
            });
            html += '</select></div>';
            return html;
        }

        function rgenRenderFilters() {
            var def = rgenDef() || {};
            var list = def.filters || [];
            var opts = RGEN.options || {};
            var html = '';

            if (list.indexOf('period') !== -1) {
                html += '<div class="rgen-field rgen-field-wide"><label>Reporting period</label><div class="rgen-seg" id="rgenPeriodSeg">';
                Object.keys(RGEN.periodModes).forEach(function (mode) {
                    html += '<button type="button" data-mode="' + esc(mode) + '" class="' + (RGEN.filters.period === mode ? 'active' : '') + '">' + esc(RGEN.periodModes[mode]) + '</button>';
                });
                html += '</div>';
                html += '<div class="rgen-dates" id="rgenCustomDates" style="' + (RGEN.filters.period === 'custom' ? 'display:flex;' : 'display:none;') + '">';
                html += '<input type="date" id="rgenStartDate" value="' + esc(RGEN.filters.start) + '" aria-label="Start Date">';
                html += '<span>to</span>';
                html += '<input type="date" id="rgenEndDate" value="' + esc(RGEN.filters.end) + '" aria-label="End Date">';
                html += '</div></div>';
            }

            if (list.indexOf('driver') !== -1) {
                html += rgenSelectField('rgenDriver', 'Driver', opts.drivers || [], RGEN.filters.driver_id, 'All drivers');
            }
            if (list.indexOf('bus') !== -1) {
                html += rgenSelectField('rgenBus', 'Bus', opts.buses || [], RGEN.filters.bus_no, 'All buses');
            }
            if (list.indexOf('status') !== -1) {
                var statuses = (def.status_options || (opts.statuses && opts.statuses[rgenType()]) || []);
                if (statuses.length) {
                    html += rgenSelectField('rgenStatus', 'Status', statuses, RGEN.filters.status, 'All statuses');
                } else {
                    html += '<div class="rgen-field"><label for="rgenStatus">Status</label><input type="text" id="rgenStatus" value="' + esc(RGEN.filters.status) + '" placeholder="Any status"></div>';
                }
            }
            if (list.indexOf('search') !== -1) {
                html += '<div class="rgen-field"><label for="rgenSearch">Search</label><input type="text" id="rgenSearch" value="' + esc(RGEN.filters.search) + '" placeholder="Name, route, code..."></div>';
            }
            if (list.indexOf('route') !== -1) {
                var routes = opts.routes || [];
                if (routes.length) {
                    html += rgenSelectField('rgenRoute', 'Route', routes, RGEN.filters.route, 'All routes');
                } else {
                    html += '<div class="rgen-field"><label for="rgenRoute">Route</label><input type="text" id="rgenRoute" value="' + esc(RGEN.filters.route) + '" placeholder="Route name"></div>';
                }
            }

            el('rgenFilterControls').innerHTML = html;
        }

        function rgenReadControls() {
            var seg = el('rgenPeriodSeg');
            if (seg) {
                var active = seg.querySelector('button.active');
                if (active) RGEN.filters.period = active.dataset.mode;
            }
            var ids = { rgenStartDate: 'start', rgenEndDate: 'end', rgenDriver: 'driver_id', rgenBus: 'bus_no', rgenStatus: 'status', rgenSearch: 'search', rgenRoute: 'route' };
            Object.keys(ids).forEach(function (id) {
                var node = el(id);
                if (node) RGEN.filters[ids[id]] = node.value;
            });
        }

        function rgenCollectPayload() {
            var def = rgenDef() || {};
            var list = def.filters || [];
            var payload = { report_type: rgenType() };
            if (list.indexOf('period') !== -1) {
                payload.period = RGEN.filters.period;
                if (RGEN.filters.period === 'custom') {
                    payload.start = RGEN.filters.start;
                    payload.end = RGEN.filters.end;
                }
            }
            if (list.indexOf('driver') !== -1) payload.driver_id = RGEN.filters.driver_id;
            if (list.indexOf('bus') !== -1) payload.bus_no = RGEN.filters.bus_no;
            if (list.indexOf('status') !== -1) payload.status = RGEN.filters.status;
            if (list.indexOf('search') !== -1) payload.search = RGEN.filters.search;
            if (list.indexOf('route') !== -1) payload.route = RGEN.filters.route;
            return payload;
        }

        function rgenValidate() {
            var def = rgenDef() || {};
            var list = def.filters || [];
            if (list.indexOf('period') !== -1 && RGEN.filters.period === 'custom') {
                if (!RGEN.filters.start || !RGEN.filters.end) return 'Select both a Start Date and an End Date for the custom range.';
                if (RGEN.filters.start > RGEN.filters.end) return 'Start Date must not be after End Date.';
            }
            if ((def.requires || []).indexOf('driver') !== -1 && !RGEN.filters.driver_id) {
                return 'Select a driver for this report.';
            }
            return '';
        }

        function rgenShowError(msg) {
            var box = el('rgenFilterError');
            if (!msg) { box.style.display = 'none'; box.textContent = ''; return; }
            box.textContent = msg;
            box.style.display = 'block';
        }

        function rgenChipsFromServer(applied) {
            var wrap = el('rgenAppliedChips');
            if (!applied || !applied.length) { wrap.innerHTML = ''; return; }
            wrap.innerHTML = applied.map(function (f) {
                return '<span class="rlib-chip"><i class="bi bi-funnel-fill"></i> ' + esc(f.label) + ': ' + esc(f.value) + '</span>';
            }).join('');
        }

        function rgenKpisHtml(kpis) {
            if (!kpis || !kpis.length) return '';
            return '<div class="rgen-kpis">' + kpis.map(function (k) {
                return '<div class="rgen-kpi"><div class="k">' + esc(k.label) + '</div><div class="v">' + esc(k.value) + '</div>' +
                    (k.hint ? '<div class="h">' + esc(k.hint) + '</div>' : '') + '</div>';
            }).join('') + '</div>';
        }

        function rgenTableHtml(data) {
            var cols = data.columns || [];
            var rows = data.records || [];
            if (!cols.length) return '';
            var head = cols.map(function (c) { return '<th>' + esc(c.label || c.key) + '</th>'; }).join('');
            var body = rows.map(function (row) {
                return '<tr>' + cols.map(function (c) {
                    return '<td>' + esc(fmtCell(row[c.key], c.type)) + '</td>';
                }).join('') + '</tr>';
            }).join('');
            if (!rows.length) {
                body = '<tr><td colspan="' + cols.length + '" style="text-align:center;color:#8a9a8a;">No records matched the filters.</td></tr>';
            }
            return '<div class="rgen-table-wrap"><table class="rgen-table"><thead><tr>' + head + '</tr></thead><tbody>' + body + '</tbody></table></div>';
        }

        function rgenComparisonHtml(comparison) {
            if (!comparison || !comparison.entries || !comparison.entries.length) return '';
            var rows = comparison.entries.map(function (e) {
                var delta = e.delta_pct === null || e.delta_pct === undefined ? 'n/a' : ((e.delta_pct > 0 ? '+' : '') + e.delta_pct + '%');
                return '<tr><td>' + esc(e.label) + '</td><td class="num">' + esc(e.current) + '</td><td class="num">' + esc(e.previous) + '</td><td class="num">' + esc(delta) + '</td></tr>';
            }).join('');
            return '<div class="rgen-block"><div class="rgen-block-title">Comparison</div><div style="font-size:.68rem;color:#8a9a8a;margin-bottom:4px;">' + esc(comparison.label) + '</div>' +
                '<table class="rgen-compare"><thead><tr><th>Measure</th><th class="num">Current</th><th class="num">Previous</th><th class="num">Change</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
        }

        function rgenDistributionsHtml(distributions) {
            if (!distributions || !distributions.length) return '';
            var html = '<div class="rgen-block"><div class="rgen-block-title">Breakdowns</div>';
            distributions.forEach(function (group) {
                var total = 0;
                Object.keys(group.counts || {}).forEach(function (k) { total += Number(group.counts[k]); });
                html += '<div style="font-size:.68rem;font-weight:700;color:#6b786d;margin:4px 0 2px;">' + esc(group.label) + '</div>';
                html += '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px;">' + Object.keys(group.counts || {}).map(function (value) {
                    var count = Number(group.counts[value]);
                    var pct = total > 0 ? Math.round((count / total) * 100) : 0;
                    return '<span class="rlib-chip">' + esc(value) + ': ' + count + ' (' + pct + '%)</span>';
                }).join('') + '</div>';
            });
            return html + '</div>';
        }

        function rgenTrendHtml(trends) {
            if (!trends || !trends.length || !trends[0].points) return '';
            var points = trends[0].points;
            var days = Object.keys(points);
            if (!days.length) return '';
            var peakDay = days[0];
            days.forEach(function (d) { if (points[d].count > points[peakDay].count) peakDay = d; });
            return '<div class="rgen-block"><div class="rgen-block-title">Trend</div>' +
                '<div style="font-size:.74rem;color:#3a4a3c;">' + days.length + ' day(s) with activity; peak on ' + esc(fmtDateOnly(peakDay)) + ' (' + points[peakDay].count + ' records).</div></div>';
        }

        function rgenRenderPreview(data) {
            RGEN.lastPreview = data;
            var host = el('rgenPreviewBody');
            var applied = data.applied_filters || [];
            rgenChipsFromServer(applied);

            var per = data.period;
            var rangeLabel = per ? (per.start_date + ' to ' + per.end_date) : '—';
            var preview = data.preview || {};
            var recordsLabel = String(data.record_count) + (preview.truncated ? ' (showing ' + preview.shown + ')' : '');

            var html = '<div class="rgen-meta">' +
                '<div><span class="k">Report</span><br><span class="v">' + esc(data.title) + '</span></div>' +
                '<div><span class="k">Covered period</span><br><span class="v">' + esc(per ? per.label : 'All records') + '</span></div>' +
                '<div><span class="k">Covered dates</span><br><span class="v">' + esc(rangeLabel) + '</span></div>' +
                '<div><span class="k">Generated</span><br><span class="v">' + esc(fmtManila(data.generated_at)) + '</span></div>' +
                '<div><span class="k">Generated by</span><br><span class="v">' + esc(data.generated_by || 'System process') + '</span></div>' +
                '<div><span class="k">Records</span><br><span class="v">' + esc(recordsLabel) + '</span></div>' +
                '</div>';

            if (!data.record_count) {
                html += '<div class="rlib-empty" style="padding:32px 16px;"><i class="bi bi-inbox"></i><h6>No records matched the filters</h6><p>Try a different period, driver, bus, status, or search, or reset the filters.</p></div>';
                host.innerHTML = html;
                return;
            }

            html += rgenKpisHtml(data.kpis);
            html += rgenComparisonHtml(data.comparison);
            html += rgenDistributionsHtml(data.distributions);
            html += rgenTrendHtml(data.trends);

            if (data.findings && data.findings.length) {
                html += '<div class="rgen-block"><div class="rgen-block-title">Findings</div><ul class="rgen-findings">' +
                    data.findings.map(function (f) { return '<li>' + esc(f) + '</li>'; }).join('') + '</ul></div>';
            }

            html += rgenTableHtml(data);
            html += '<div class="rgen-note-line">' + (preview.truncated
                ? 'Showing the first ' + preview.shown + ' of ' + data.record_count + ' records. Generate or export CSV for the full set.'
                : 'Showing all ' + data.record_count + ' record(s).') + '</div>';

            host.innerHTML = html;
        }

        function rgenSetBusy(busy) {
            RGEN.busy = busy;
            ['rgenApplyBtn', 'rgenResetBtn', 'rlibGenerateSubmit', 'rgenCsvBtn', 'rgenPrintBtn'].forEach(function (id) {
                var node = el(id);
                if (node) node.disabled = busy;
            });
            var submit = el('rlibGenerateSubmit');
            if (submit) {
                if (busy) {
                    submit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Working...';
                } else {
                    submit.innerHTML = '<i class="bi bi-file-earmark-pdf"></i> Generate PDF Report';
                }
            }
        }

        function rgenLoadPreview(cb) {
            rgenReadControls();
            var error = rgenValidate();
            if (error) {
                rgenShowError(error);
                if (cb) cb(new Error(error));
                return;
            }
            rgenShowError('');
            rgenSetBusy(true);
            var payload = rgenCollectPayload();
            fetch('/admin/api/reports/GenerateReportPreviewAPI.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function (r) { return r.json().then(function (res) { return { ok: r.ok, res: res }; }); })
                .then(function (out) {
                    if (!out.res.success) throw new Error(out.res.message || 'Could not build the preview.');
                    rgenRenderPreview(out.res.data);
                    RGEN.appliedKey = JSON.stringify(payload);
                    if (cb) cb(null, out.res.data);
                })
                .catch(function (err) {
                    var host = el('rgenPreviewBody');
                    host.innerHTML = '<div class="rlib-empty" style="padding:32px 16px;"><i class="bi bi-exclamation-circle" style="color:#b94a48;"></i><h6>Could not load the preview</h6><p>' + esc(err.message || 'Please try again.') + '</p></div>';
                    if (cb) cb(err);
                })
                .finally(function () { rgenSetBusy(false); });
        }

        function rgenEnsurePreview(cb) {
            rgenReadControls();
            var payload = rgenCollectPayload();
            var error = rgenValidate();
            if (error) { rgenShowError(error); return; }
            if (RGEN.lastPreview && RGEN.appliedKey === JSON.stringify(payload)) { cb(); return; }
            rgenLoadPreview(function (err) { if (!err) cb(); });
        }

        function rgenGenerate() {
            if (RGEN.busy) return;
            rgenEnsurePreview(function () {
                if (RGEN.busy) return;
                rgenSetBusy(true);
                var note = el('rlibGenerateNote');
                note.style.display = 'none';
                var payload = rgenCollectPayload();
                fetch('/admin/api/reports/GenerateReportAPI.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res.success) throw new Error(res.message || 'Generation failed.');
                    note.style.display = 'block';
                    note.style.background = '#f6f8ec';
                    note.style.borderColor = '#e5e0a4';
                    note.style.color = '#7a6f12';
                    note.innerHTML = 'Generated "' + esc(res.data.title) + '" (' + res.data.record_count + ' records).' +
                        (res.data.pdf_url ? ' <a href="' + esc(res.data.pdf_url) + '" target="_blank" rel="noopener">View generated report</a>.' : '');
                    loadLibrary();
                }).catch(function (err) {
                    note.style.display = 'block';
                    note.style.background = '#fbe9e7';
                    note.style.borderColor = '#eccac5';
                    note.style.color = '#b94a48';
                    note.textContent = err.message || 'Generation failed.';
                }).finally(function () { rgenSetBusy(false); });
            });
        }

        function rgenExportCsv() {
            if (RGEN.busy) return;
            rgenEnsurePreview(function () {
                if (RGEN.busy) return;
                rgenSetBusy(true);
                var note = el('rlibGenerateNote');
                fetch('/admin/api/reports/GenerateReportExportAPI.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(rgenCollectPayload())
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res.success) throw new Error(res.message || 'Export failed.');
                    note.style.display = 'block';
                    note.style.background = '#f6f8ec';
                    note.style.borderColor = '#e5e0a4';
                    note.style.color = '#7a6f12';
                    note.innerHTML = 'CSV exported (' + res.data.record_count + ' records). <a href="' + esc(res.data.url) + '" target="_blank" rel="noopener">Download CSV</a>.';
                    loadLibrary();
                }).catch(function (err) {
                    note.style.display = 'block';
                    note.style.background = '#fbe9e7';
                    note.style.borderColor = '#eccac5';
                    note.style.color = '#b94a48';
                    note.textContent = err.message || 'Export failed.';
                }).finally(function () { rgenSetBusy(false); });
            });
        }

        function rgenPrintPreview() {
            if (RGEN.busy) return;
            rgenEnsurePreview(function () {
                var d = RGEN.lastPreview;
                if (!d) return;
                var w = window.open('', '_blank', 'width=1000,height=1000');
                if (!w) { alert('Please allow pop-ups to print the preview.'); return; }
                var per = d.period;
                var rows = (d.records || []).map(function (row) {
                    return '<tr>' + d.columns.map(function (c) { return '<td>' + esc(fmtCell(row[c.key], c.type)) + '</td>'; }).join('') + '</tr>';
                }).join('');
                var kpis = (d.kpis || []).map(function (k) {
                    return '<span class="kpi"><b>' + esc(k.label) + '</b>: ' + esc(k.value) + (k.hint ? ' <i>(' + esc(k.hint) + ')</i>' : '') + '</span>';
                }).join(' ');
                var findings = (d.findings || []).map(function (f) { return '<li>' + esc(f) + '</li>'; }).join('');
                w.document.write('<!doctype html><html><head><title>' + esc(d.title) + '</title><style>' +
                    'body{font-family:Poppins,Arial,sans-serif;color:#2e4836;padding:24px;font-size:12px;}' +
                    'h1{font-size:20px;color:#295738;margin:0 0 4px;} table{width:100%;border-collapse:collapse;font-size:10px;margin-top:10px;}' +
                    'th,td{border:1px solid #ccc;padding:4px 6px;text-align:left;} th{background:#f6f8ec;}' +
                    '.meta{font-size:11px;color:#555;margin:2px 0;} .kpi{display:inline-block;border:1px solid #ccc;border-radius:6px;padding:6px 8px;margin:2px;font-size:11px;}' +
                    '@page{margin:16mm;} ul{margin:8px 0 0 18px;padding:0;}' +
                    '</style></head><body>');
                w.document.write('<h1>' + esc(d.title) + '</h1>');
                w.document.write('<div class="meta">Covered period: ' + esc(per ? per.label : 'All records') + ' · ' + esc(per ? (per.start_date + ' to ' + per.end_date) : '') + '</div>');
                w.document.write('<div class="meta">Generated: ' + esc(fmtManila(d.generated_at)) + ' · Generated by: ' + esc(d.generated_by || 'System process') + ' · Records: ' + d.record_count + '</div>');
                (d.applied_filters || []).forEach(function (f) { w.document.write('<div class="meta">' + esc(f.label) + ': ' + esc(f.value) + '</div>'); });
                if (kpis) w.document.write('<div style="margin-top:8px;">' + kpis + '</div>');
                if (findings) w.document.write('<div style="margin-top:8px;"><b>Findings</b><ul>' + findings + '</ul></div>');
                w.document.write('<table><thead><tr>' + d.columns.map(function (c) { return '<th>' + esc(c.label || c.key) + '</th>'; }).join('') + '</tr></thead><tbody>' + rows + '</tbody></table>');
                w.document.write('</body></html>');
                w.document.close();
                w.focus();
                setTimeout(function () { w.print(); }, 300);
            });
        }

        function rgenReset() {
            rgenDefaults();
            rgenRenderFilters();
            rgenShowError('');
            RGEN.lastPreview = null;
            RGEN.appliedKey = null;
            rgenChipsFromServer([]);
            var note = el('rlibGenerateNote');
            if (note) note.style.display = 'none';
            rgenLoadPreview();
        }

        function rgenOnTypeChange() {
            rgenDefaults();
            rgenRenderFilters();
            rgenShowError('');
            RGEN.lastPreview = null;
            RGEN.appliedKey = null;
            rgenChipsFromServer([]);
            var def = rgenDef() || {};
            el('rlibGenerateDesc').textContent = def.description || '';
            var note = el('rlibGenerateNote');
            if (note) note.style.display = 'none';
            el('rgenPreviewBody').innerHTML = '<div class="rlib-empty" style="padding:32px 16px;"><i class="bi bi-eye"></i><h6>No preview yet</h6><p>Choose filters, then press Apply Filters to preview the matching records.</p></div>';
        }

        document.addEventListener('DOMContentLoaded', function () {
            el('rlibTabs').addEventListener('click', function (e) {
                var tab = e.target.closest('.rlib-tab');
                if (!tab) return;
                RLIB.scope = tab.dataset.scope;
                document.querySelectorAll('#rlibTabs .rlib-tab').forEach(function (t) {
                    t.classList.toggle('active', t === tab);
                });
                loadLibrary();
            });

            var debounce = null;
            el('rlibSearch').addEventListener('input', function () {
                clearTimeout(debounce);
                debounce = setTimeout(loadLibrary, 250);
            });
            ['rlibType', 'rlibPeriod', 'rlibFormat', 'rlibStatus', 'rlibGenFrom', 'rlibGenTo'].forEach(function (id) {
                el(id).addEventListener('change', function () {
                    el('rlibPeriodCustom').style.display = el('rlibPeriod').value === 'custom' ? 'flex' : 'none';
                    loadLibrary();
                });
            });
            ['rlibPeriodStart', 'rlibPeriodEnd'].forEach(function (id) {
                el(id).addEventListener('change', loadLibrary);
            });
            el('rlibClear').addEventListener('click', function () {
                el('rlibSearch').value = '';
                ['rlibType', 'rlibPeriod', 'rlibFormat', 'rlibStatus', 'rlibGenFrom', 'rlibGenTo', 'rlibPeriodStart', 'rlibPeriodEnd'].forEach(function (id) { el(id).value = ''; });
                el('rlibPeriodCustom').style.display = 'none';
                loadLibrary();
            });
            el('rlibList').addEventListener('click', function (e) {
                var btn = e.target.closest('[data-action]');
                if (!btn) return;
                var item = findItem(btn.dataset.id);
                if (!item) return;
                if (btn.dataset.action === 'view') openPreview(item);
                else if (btn.dataset.action === 'print') printItem(item);
                else if (btn.dataset.action === 'archive') archiveItem(item);
                else if (btn.dataset.action === 'restore') restoreItem(item);
            });

            el('rlibGenerateBtn').addEventListener('click', function () {
                el('rlibGenerateNote').style.display = 'none';
                rgenLoadOptions(function () {
                    rgenOnTypeChange();
                    bootstrap.Modal.getOrCreateInstance(el('rlibGenerateModal')).show();
                });
            });
            el('rlibReportType').addEventListener('change', rgenOnTypeChange);
            el('rgenApplyBtn').addEventListener('click', function () { rgenLoadPreview(); });
            el('rgenResetBtn').addEventListener('click', rgenReset);
            el('rlibGenerateSubmit').addEventListener('click', rgenGenerate);
            el('rgenCsvBtn').addEventListener('click', rgenExportCsv);
            el('rgenPrintBtn').addEventListener('click', rgenPrintPreview);

            el('rgenFilterControls').addEventListener('click', function (e) {
                var btn = e.target.closest('#rgenPeriodSeg button');
                if (!btn) return;
                rgenReadControls();
                RGEN.filters.period = btn.dataset.mode;
                el('rgenPeriodSeg').querySelectorAll('button').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });
                el('rgenCustomDates').style.display = RGEN.filters.period === 'custom' ? 'flex' : 'none';
            });
            el('rgenFilterControls').addEventListener('change', rgenReadControls);
            el('rgenFilterControls').addEventListener('input', rgenReadControls);

            rgenDefaults();
            rgenRenderFilters();
            var initialDef = rgenDef() || {};
            el('rlibGenerateDesc').textContent = initialDef.description || '';

            loadLibrary();
        });
    })();
</script>

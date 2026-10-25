<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
require_once __DIR__ . '/../layout/navbarfinance.php';

$discRole = $_SESSION['user']['role'] ?? '';
$discCsrf = \Finance\Middleware\Csrf::generate();
$discCanEditRules = in_array($discRole, ['finance_manager', 'admin', 'super_admin'], true);

if (!function_exists('discStatusClass')) {
    function discStatusClass(string $status): string
    {
        switch ($status) {
            case 'Open':
                return 'rh-badge-gray';
            case 'Under Investigation':
            case 'Awaiting Driver Response':
            case 'Awaiting Recovery':
                return 'rh-badge-amber';
            case 'Findings Submitted':
            case 'Awaiting Approval':
                return 'rh-badge-blue';
            case 'Pending Manager Approval':
                return 'rh-badge-violet';
            case 'Returned for Further Investigation':
                return 'rh-badge-red';
            case 'Resolved':
                return 'rh-badge-green';
            case 'Closed':
            default:
                return 'rh-badge-gray';
        }
    }
}

if (!function_exists('discTypeClass')) {
    function discTypeClass(string $type): string
    {
        switch ($type) {
            case 'Cash Shortage':
                return 'dm-type-shortage';
            case 'QR Ph Payment Mismatch':
                return 'dm-type-qrph';
            case 'System or Fare Error':
                return 'dm-type-system';
            case 'Overdue Remittance':
                return 'dm-type-overdue';
            case 'Excess Remittance':
                return 'dm-type-excess';
            default:
                return 'dm-type-system';
        }
    }
}
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">

<style>
/* ===== Main page — follows the Remittance History design (finance/views/history) ===== */
.rh-main { font-family: "Poppins", sans-serif; background: #eceed9; min-height: 100vh; padding: 20px; }
.rh-card { background: #fef8f3; border: 1px solid #cdd9b5; border-radius: 18px; padding: 22px; box-shadow: 0 2px 10px rgba(0, 0, 0, .04); }
.rh-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; }
.rh-title { font-weight: 800; font-size: 1.35rem; color: #295738; margin: 0 0 2px; }
.rh-subtitle { font-weight: 500; font-size: .8rem; color: #8a9a8a; margin: 0; }
.rh-header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.rh-btn { display: inline-flex; align-items: center; gap: 7px; border-radius: 10px; padding: 9px 16px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid transparent; transition: background .15s; }
.rh-btn-pdf { background: #295738; color: #fff; }
.rh-btn-pdf:hover { background: #1f4229; color: #fff; }
.rh-btn-excel { background: #eef4e7; color: #295738; border-color: #d7e3cd; }
.rh-btn-excel:hover { background: #e2eede; color: #295738; }
.rh-btn-muted { background: #fcfbf4; color: #295738; border-color: #d7e3cd; }
.rh-btn-muted:hover { background: #eef2dc; }

.rh-cards { display: flex; gap: 12px; margin-bottom: 14px; }
.rh-card-item { flex: 1; min-width: 0; background: #fcfbf4; border: 1px solid #d7e3cd; border-radius: 14px; padding: 11px 14px; display: flex; align-items: center; gap: 10px; }
.rh-card-item > div { min-width: 0; }
.rh-card-dark { background: #295738; border-color: #295738; }
.rh-card-icon { width: 32px; height: 32px; border-radius: 9px; background: #eef2dc; color: #295738; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
.rh-card-icon-red { background: #fde8e6; color: #b3261e; }
.rh-card-dark .rh-card-icon { background: rgba(255, 255, 255, .14); color: #fff; }
.rh-card-label { font-size: 10.5px; font-weight: 600; color: #295738; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rh-card-value { font-size: 18px; font-weight: 700; color: #295738; line-height: 1.25; font-variant-numeric: tabular-nums; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rh-card-sub { font-size: 10.5px; color: #8a9a8a; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rh-value-red { color: #dc2626; }
.rh-card-dark .rh-card-label { color: rgba(255, 255, 255, .75); }
.rh-card-dark .rh-card-value { color: #fff; }
.rh-card-dark .rh-card-sub { color: rgba(255, 255, 255, .6); }
.dm-value-red { color: #b3261e; }
.dm-value-green { color: #1f6b3a; }
.dm-value-amber { color: #92400e; }

.rh-filter { display: flex; gap: 10px; align-items: center; background: #fcfbf4; border: 1px solid #e8e4c9; border-radius: 14px; padding: 12px 14px; margin-bottom: 18px; flex-wrap: wrap; }
.rh-search { position: relative; flex: 0 1 240px; min-width: 180px; display: flex; align-items: center; gap: 8px; background: #fff; border: 1.5px solid #cdd9b5; border-radius: 10px; padding: 0 10px; transition: border-color .15s, box-shadow .15s; }
.rh-search:focus-within { border-color: #b9cfa8; box-shadow: 0 0 0 3px rgba(107, 141, 107, .10); }
.rh-search-icon { color: #6b8d6b; font-size: 13px; pointer-events: none; }
.rh-search input { width: 100%; box-sizing: border-box; padding: 9px 0; border: none; background: transparent; font-size: 13px; color: #295738; outline: none; font-family: inherit; }
.rh-select { border: 1.5px solid #cdd9b5; border-radius: 10px; background: #fff; padding: 9px 10px; font-size: 13px; font-weight: 600; color: #295738; outline: none; font-family: inherit; cursor: pointer; }
.rh-daterange { display: flex; align-items: center; gap: 6px; color: #295738; font-size: 13px; background: #fff; border: 1px solid #cdd9b5; border-radius: 10px; padding: 6px 10px; white-space: nowrap; }
.rh-daterange .rh-daterange-icon { color: #8a9a8a; font-size: 13px; }
.rh-daterange-sep { color: #8a9a8a; }
.rh-daterange input { border: none; outline: none; font-size: 12.5px; color: #295738; font-family: inherit; background: transparent; width: 118px; cursor: pointer; }
.rh-filter-actions { display: flex; gap: 8px; margin-left: auto; }

.rh-table-wrap { background: #fff; border: 1px solid #cdd9b5; border-radius: 14px; overflow: hidden; margin-bottom: 14px; }
.rh-table-meta { display: flex; align-items: center; justify-content: flex-start; padding: 9px 14px; background: #fcfbf4; border-bottom: 1px solid #eef2e6; }
.rh-filter-count { font-size: 12px; color: #8a9a8a; display: flex; align-items: center; gap: 5px; white-space: nowrap; }
.rh-table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 960px; }
.rh-table thead th { background: #f0f4e2; color: #295738; font-size: 10.5px; text-transform: uppercase; letter-spacing: .4px; padding: 11px 12px; text-align: left; font-weight: 700; white-space: nowrap; }
.rh-table tbody td { padding: 11px 12px; border-bottom: 1px solid #f1ecdc; vertical-align: middle; color: #374151; }
.rh-table tbody tr:last-child td { border-bottom: none; }
.rh-table tbody tr:hover { background: #fbf9ef; }
.rh-num { text-align: left; font-variant-numeric: tabular-nums; white-space: nowrap; }
.rh-id { color: #6b7280; font-weight: 600; white-space: nowrap; }
.rh-date { font-weight: 600; color: #295738; white-space: nowrap; }
.rh-time { color: #8a9a8a; font-size: 11.5px; white-space: nowrap; display: block; }
.rh-driver-name { font-weight: 600; color: #295738; display: block; }
.rh-driver-id { color: #8a9a8a; font-size: 11px; display: block; }
.rh-amt-neutral { font-weight: 600; color: #374151; }
.rh-amt-green { color: #1f6b3a; font-weight: 600; }
.rh-amt-red { color: #b3261e; font-weight: 600; }
.rh-amt-amber { color: #8a5a10; font-weight: 600; }
.rh-amt-gray { color: #6b7280; font-weight: 600; }

.rh-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
.rh-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.rh-badge-green { background: #e3f0e0; color: #1f6b3a; border: 1px solid #c3e0bd; }
.rh-badge-green .rh-dot { background: #4f9e6a; }
.rh-badge-red { background: #fde8e6; color: #b3261e; border: 1px solid #f5c9c4; }
.rh-badge-red .rh-dot { background: #d9534f; }
.rh-badge-amber { background: #fdf0dd; color: #8a5a10; border: 1px solid #f5ddb4; }
.rh-badge-amber .rh-dot { background: #d99a2b; }
.rh-badge-gray { background: #f4f2e9; color: #6b7280; border: 1px solid #e8e4d2; }
.rh-badge-gray .rh-dot { background: #9ca3af; }
.rh-badge-blue { background: #e0f2fe; color: #075985; border: 1px solid #b9e0f7; }
.rh-badge-blue .rh-dot { background: #4f9ac9; }
.rh-badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #d8d2f5; }
.rh-badge-violet .rh-dot { background: #8b6fd0; }

.rh-actions { display: flex; gap: 7px; justify-content: flex-start; }
.rh-action { width: 32px; height: 32px; border-radius: 9px; border: 1px solid #d7e3cd; background: #fcfbf4; color: #295738; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px; transition: background .15s, color .15s, border-color .15s; }
.rh-action:hover { background: #295738; color: #fff; border-color: #295738; }

.rh-pagination { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 14px; flex-wrap: wrap; }
.rh-page-info { font-size: 12px; color: #6b7280; }
.rh-page-controls { display: flex; align-items: center; gap: 5px; }
.rh-page-btn { border: 1px solid #d7e3cd; background: #fcfbf4; color: #295738; border-radius: 8px; padding: 5px 11px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; transition: background .15s; }
.rh-page-btn:hover:not(:disabled) { background: #eef2dc; }
.rh-page-btn:disabled { opacity: .45; cursor: default; }
.rh-page-size { display: flex; align-items: center; gap: 7px; font-size: 12px; color: #6b7280; }
.rh-page-size select { border: 1px solid #cdd9b5; border-radius: 8px; padding: 5px 8px; font-size: 12px; font-family: inherit; color: #295738; cursor: pointer; }
.rh-empty { padding: 36px 24px; text-align: center; color: #8a9a8a; font-size: .84rem; }
.rh-table tbody tr.rh-row-overdue td { background: #fffaf0; }
.rh-table tbody tr.rh-row-overdue:hover td { background: #fff5df; }

/* Discrepancy content chips, using the History table's badge sizing. */
.dm-type { display: inline-flex; align-items: center; padding: 4px 11px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; border: 1px solid transparent; }
.dm-type-shortage { background: #fde8e6; color: #b3261e; border-color: #f5c9c4; }
.dm-type-qrph { background: #e0f2fe; color: #075985; border-color: #b9e0f7; }
.dm-type-system { background: #f4f2e9; color: #6b7280; border-color: #e8e4d2; }
.dm-type-overdue { background: #fdf0dd; color: #8a5a10; border-color: #f5ddb4; }
.dm-type-excess { background: #ede9fe; color: #5b21b6; border-color: #d8d2f5; }
.dm-highlights { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.dm-input { border: 1.5px solid #cdd9b5; border-radius: 10px; padding: 8px 12px; font-size: 12.5px; color: #295738; font-family: inherit; background: #fff; box-sizing: border-box; }

@media (max-width: 992px) {
    .rh-cards { flex-wrap: wrap; }
    .rh-card-item { flex: 1 1 calc(50% - 7px); }
}
@media (max-width: 700px) {
    .rh-main { padding: 12px; }
    .rh-card { padding: 14px; }
    .rh-filter { flex-direction: column; align-items: stretch; }
    .rh-search, .rh-select, .rh-daterange { width: 100%; }
    .rh-filter-actions { margin-left: 0; }
    .rh-header { flex-direction: column; align-items: flex-start; }
    .rh-header-actions { width: 100%; flex-wrap: wrap; }
}
@media (max-width: 576px) {
    .rh-card-item { flex: 1 1 100%; }
}

/* =========================================================================
   Reference modal design — copied from admin/views/role-management
   (admin/assets/css/role-management.css) so the discrepancy workspace modal
   matches the Role Management modal exactly (same shell, header, buttons,
   fields, sections, history, scrolling and responsive rules).
   ========================================================================= */
.rmg-btn { display: inline-flex; align-items: center; gap: 6px; border-radius: 20px; font-size: .78rem; font-weight: 600; padding: 8px 16px; cursor: pointer; transition: all .18s ease; border: 1px solid #d8d2b6; background: #fff; color: #295738; text-decoration: none; }
.rmg-btn:hover { background: #f4f1e6; }
.rmg-btn:disabled { opacity: .5; cursor: not-allowed; }
.rmg-btn-primary { background: #295738; border-color: #295738; color: #fff; }
.rmg-btn-primary:hover { background: #1e4229; }
.rmg-btn-danger { border-color: #e7b9b4; color: #b94a48; }
.rmg-btn-danger:hover { background: #fbe9e7; }
.rmg-btn-sm { padding: 6px 12px; font-size: .72rem; }

.rmg-avatar { width: 38px; height: 38px; border-radius: 50%; background: #e8f2e6; color: #295738; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .8rem; overflow: hidden; flex-shrink: 0; border: 1px solid #cdd9b5; }
.rmg-type-chip { display: inline-flex; align-items: center; gap: 5px; background: #f4f1e6; border: 1px solid #e5e0a4; color: #6b7a63; border-radius: 12px; padding: 2px 8px; font-size: .64rem; font-weight: 700; white-space: nowrap; }

.rmg-detail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px 18px; margin-bottom: 12px; }
.rmg-field { background: #fafbf5; border: 1px solid #f0f2e8; border-radius: 11px; padding: 8px 11px; }
.rmg-field .k { font-size: .6rem; text-transform: uppercase; letter-spacing: .05em; color: #8a9a8a; margin-bottom: 2px; }
.rmg-field .v { font-size: .8rem; font-weight: 600; color: #2e4836; overflow-wrap: anywhere; }
.rmg-missing { color: #a4b0a2; font-weight: 500; font-style: italic; }

.rmg-detail-modal { border: 1px solid #dde5cf; border-radius: 22px; background: #fffdf8; box-shadow: 0 18px 50px rgba(41,87,56,.18); overflow: hidden; }
.rmg-detail-header { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; padding: 24px 24px 22px; border: 0; background: linear-gradient(135deg, #e7f0de 0%, #fdf6ee 60%, #fef8f3 100%); border-bottom: 1px solid #e5e0a4; overflow: hidden; }
.rmg-detail-header::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 5px; background: linear-gradient(90deg, #295738, #6f9c5a 55%, #c4b417); }
.rmg-detail-header::after { content: ''; position: absolute; width: 190px; height: 190px; right: -60px; top: -90px; border-radius: 50%; background: radial-gradient(circle, rgba(125,164,83,.18) 0%, rgba(125,164,83,0) 70%); pointer-events: none; }
.rmg-detail-identity { display: flex; align-items: center; gap: 15px; min-width: 0; position: relative; z-index: 1; }
.rmg-detail-avatar-wrap { position: relative; flex-shrink: 0; }
.rmg-detail-avatar { width: 62px; height: 62px; font-size: 1.08rem; background: linear-gradient(135deg, #295738, #4f7842); color: #fff; border: 3px solid #fff; box-shadow: 0 8px 18px rgba(41,87,56,.24); }
.rmg-detail-dot { position: absolute; right: 1px; bottom: 3px; width: 15px; height: 15px; border-radius: 50%; border: 3px solid #fff; background: #9aa89a; }
.rmg-detail-avatar-wrap.is-active .rmg-detail-dot { background: #43a047; }
.rmg-detail-avatar-wrap.is-inactive .rmg-detail-dot { background: #b3a06a; }
.rmg-detail-avatar-wrap.is-pending .rmg-detail-dot { background: #e0a92c; }
.rmg-detail-avatar-wrap.is-rejected .rmg-detail-dot { background: #cc5147; }
.rmg-detail-who { min-width: 0; }
.rmg-detail-kicker { font-size: .61rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #63815f; margin-bottom: 3px; }
.rmg-detail-header .modal-title { font-size: 1.2rem; font-weight: 800; color: #234d31; margin: 0; line-height: 1.25; overflow-wrap: anywhere; }
.rmg-detail-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 7px; }
.rmg-detail-chips .rmg-type-chip { background: #fff; }
.rmg-detail-modal .btn-close { flex-shrink: 0; margin-top: 2px; }
.rmg-detail-body { padding: 18px 24px 4px; background: #fffdf8; }
.rmg-detail-section { background: #fff; border: 1px solid #eef1e4; border-radius: 16px; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(41,87,56,.04); }
.rmg-section-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px dashed #e9eddc; }
.rmg-section-icon { width: 34px; height: 34px; flex-shrink: 0; display: grid; place-items: center; border-radius: 10px; background: #eef5e7; color: #295738; font-size: .95rem; }
.rmg-section-icon.tone-account { background: #fdf5e3; color: #9a6b12; }
.rmg-section-icon.tone-role { background: #eef1f5; color: #4f637a; }
.rmg-section-icon.tone-status { background: #eaf6ea; color: #2e7d32; }
.rmg-section-icon.tone-docs { background: #f3eee2; color: #7a6f4f; }
.rmg-section-title { font-size: .84rem; font-weight: 800; color: #295738; }
.rmg-detail-section .rmg-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 12px; margin-bottom: 0; }
.rmg-status-banner { display: flex; align-items: center; gap: 9px; border-radius: 12px; padding: 10px 13px; margin-bottom: 12px; font-size: .83rem; font-weight: 700; border: 1px solid; }
.rmg-status-banner.is-active { background: #eaf6ea; border-color: #c7e3c7; color: #2e7d32; }
.rmg-status-banner.is-inactive { background: #f3eee2; border-color: #e2d9be; color: #7a6f4f; }
.rmg-status-banner.is-pending { background: #fdf5e3; border-color: #ecd9ab; color: #9a6b12; }
.rmg-status-banner.is-rejected { background: #fbe9e7; border-color: #eccac5; color: #b94a48; }
.rmg-status-banner.is-other { background: #eef1f5; border-color: #d7dde5; color: #5b6472; }
.rmg-status-banner .rmg-status-dot { width: 10px; height: 10px; border-radius: 50%; background: currentColor; box-shadow: 0 0 0 4px rgba(255,255,255,.85); }
.rmg-detail-note { font-size: .72rem; color: #6b7a63; background: #f6f8ec; border: 1px solid #e5e0a4; border-radius: 10px; padding: 8px 10px; margin-bottom: 12px; }
.rmg-docs { display: flex; gap: 12px; flex-wrap: wrap; font-size: .74rem; margin-bottom: 12px; }
.rmg-docs a { color: #295738; font-weight: 600; }
.rmg-detail-body .rmg-docs { gap: 8px; margin-bottom: 0; }
.rmg-detail-body .rmg-docs a { display: inline-flex; align-items: center; gap: 6px; background: #f6f8ec; border: 1px solid #e5e0a4; border-radius: 10px; padding: 7px 12px; font-size: .74rem; transition: background .15s ease; }
.rmg-detail-body .rmg-docs a:hover { background: #eef5e7; }
.rmg-history { border: 1px solid #f0ede0; border-radius: 12px; background: #fff; padding: 10px 12px; margin-bottom: 12px; }
.rmg-history ul { margin: 4px 0 0; padding-left: 16px; }
.rmg-history li { font-size: .72rem; color: #3a4a3c; line-height: 1.6; margin-bottom: 4px; }
.rmg-history-action { display: inline-block; background: #f4f1e6; border: 1px solid #e5e0a4; color: #6b7a63; border-radius: 10px; padding: 1px 7px; font-size: .62rem; font-weight: 700; margin-right: 4px; }
.rmg-history-meta { color: #8a9a8a; font-size: .66rem; }
.rmg-history-empty { font-size: .72rem; color: #8a9a8a; margin-top: 4px; }
.rmg-block-title { font-size: .68rem; font-weight: 800; color: #295738; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
.rmg-detail-body .rmg-history { border: 1px solid #eef1e4; border-radius: 16px; background: #fff; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(41,87,56,.04); }
.rmg-detail-body .rmg-history .rmg-block-title { font-size: .84rem; font-weight: 800; color: #295738; text-transform: none; letter-spacing: 0; }
.rmg-detail-body .rmg-history ul { position: relative; list-style: none; padding-left: 0; }
.rmg-detail-body .rmg-history ul::before { content: ''; position: absolute; left: 4px; top: 6px; bottom: 6px; width: 2px; background: #e3eabb; }
.rmg-detail-body .rmg-history li { position: relative; padding-left: 18px; }
.rmg-detail-body .rmg-history li::before { content: ''; position: absolute; left: 0; top: 7px; width: 10px; height: 10px; border-radius: 50%; background: #7da453; box-shadow: 0 0 0 3px #eef5e7; }
.rmg-detail-footer { border-top: 1px solid #eef1e4; background: #fdfbf4; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.rmg-detail-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.rmg-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 14px; }
.rmg-form-field { display: flex; flex-direction: column; gap: 4px; }
.rmg-form-field.full { grid-column: 1 / -1; }
.rmg-form-field label { font-size: .64rem; font-weight: 700; color: #8a9a8a; text-transform: uppercase; letter-spacing: .05em; }
.rmg-form-field input, .rmg-form-field select, .rmg-form-field textarea { border: 1px solid #e5e0a4; border-radius: 10px; padding: 8px 10px; font-size: .78rem; background: #fffdf8; color: #2e4836; outline: none; width: 100%; }
.rmg-form-modal { border: 1px solid #dde5cf; border-radius: 22px; background: #fffdf8; box-shadow: 0 18px 50px rgba(41,87,56,.18); overflow: hidden; }
.rmg-form-header { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; padding: 20px 22px 18px; border: 0; background: linear-gradient(135deg, #e7f0de 0%, #fdf6ee 60%, #fef8f3 100%); border-bottom: 1px solid #e5e0a4; overflow: hidden; }
.rmg-form-header::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 5px; background: linear-gradient(90deg, #295738, #6f9c5a 55%, #c4b417); }
.rmg-form-header::after { content: ''; position: absolute; width: 170px; height: 170px; right: -50px; top: -80px; border-radius: 50%; background: radial-gradient(circle, rgba(125,164,83,.18) 0%, rgba(125,164,83,0) 70%); pointer-events: none; }
.rmg-form-head { display: flex; align-items: center; gap: 13px; position: relative; z-index: 1; min-width: 0; }
.rmg-form-icon { width: 46px; height: 46px; flex-shrink: 0; display: grid; place-items: center; border-radius: 13px; background: linear-gradient(135deg, #295738, #4f7842); color: #fff; font-size: 1.15rem; box-shadow: 0 8px 18px rgba(41,87,56,.24); }
.rmg-form-kicker { font-size: .61rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #63815f; margin-bottom: 2px; }
.rmg-form-title { font-size: 1.08rem; font-weight: 800; color: #234d31; margin: 0; line-height: 1.25; }
.rmg-form-sub { font-size: .7rem; color: #63815f; font-weight: 600; margin-top: 3px; overflow-wrap: anywhere; }
.rmg-form-header .btn-close { flex-shrink: 0; margin-top: 2px; position: relative; z-index: 1; }
.rmg-form-body { padding: 0 22px 6px; background: #fffdf8; }
.rmg-form-body .rmg-detail-section { margin-bottom: 14px; }
.rmg-form-footer { border-top: 1px solid #eef1e4; background: #fdfbf4; padding: 12px 22px; display: flex; gap: 8px; justify-content: flex-end; }

/* Discrepancy content helpers built on the reference field/section styles */
.dm-control-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)) auto; gap: 10px 14px; align-items: end; margin-bottom: 10px; }
.dm-control-row:last-child { margin-bottom: 0; }
.rmg-field .v.dm-value-red { color: #b3261e; }
.rmg-field .v.dm-value-amber { color: #92400e; }
.rmg-detail-section .rmg-detail-actions { justify-content: flex-end; margin-top: 10px; }
.dm-approval { border: 1px solid #eef1e4; border-radius: 12px; background: #fafbf5; padding: 10px 12px; margin-bottom: 10px; }
.dm-approval:last-child { margin-bottom: 0; }
.dm-approval-head { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: .78rem; color: #2e4836; margin-bottom: 8px; }
.dm-approval-head .rmg-history-meta { margin-left: auto; }
.dm-loading { padding: 48px 24px; text-align: center; color: #8a9a8a; }
.dm-table-scroll { width: 100%; overflow-x: auto; }
.dm-table-sm { width: 100%; border-collapse: collapse; font-size: .74rem; }
.dm-table-sm th { text-align: left; color: #8a9a8a; font-size: .6rem; text-transform: uppercase; letter-spacing: .05em; padding: 7px 8px; border-bottom: 1px solid #eef1e4; }
.dm-table-sm td { padding: 8px; border-bottom: 1px solid #f0f2e8; color: #2e4836; vertical-align: middle; }
.dm-ledger-state { display: inline-block; border-radius: 999px; padding: 3px 10px; font-size: .63rem; font-weight: 700; }
.dm-state-proposed { background: #fdf5e3; color: #9a6b12; }
.dm-state-approved { background: #eef1f5; color: #4f637a; }
.dm-state-posted { background: #eaf6ea; color: #2e7d32; }
.dm-state-rejected { background: #fbe9e7; color: #b94a48; }
.dm-state-voided { background: #f3eee2; color: #7a6f4f; }

@media (max-width: 575px) {
    .rmg-detail-header { padding: 16px; }
    .rmg-detail-avatar { width: 46px; height: 46px; font-size: .9rem; }
    .rmg-detail-header .modal-title { font-size: 1rem; }
    .rmg-detail-body { padding: 14px 14px 2px; }
    .rmg-detail-section .rmg-detail-grid { grid-template-columns: 1fr; }
    .rmg-detail-footer { padding: 12px 14px; }
    .rmg-form-body { padding: 0 14px 4px; }
    .rmg-form-grid, .dm-control-row { grid-template-columns: 1fr; }
}
</style>

<div class="main-content rh-main">
<div class="rh-card">

<?php require_once __DIR__ . '/../layout/_headernav.php'; ?>
<?php require __DIR__ . '/_aibutton.php'; ?>

<div class="rh-header">
    <div>
        <h1 class="rh-title">Discrepancy Investigation</h1>
        <p class="rh-subtitle">Investigate discrepancies, prepare findings, and approve resolutions.</p>
    </div>
    <div class="rh-header-actions">
        <?php if ($discCanEditRules): ?>
            <button type="button" class="rh-btn rh-btn-muted" id="dmBackfillBtn"><i class="bi bi-box-arrow-in-down"></i> Import Existing</button>
            <button type="button" class="rh-btn rh-btn-muted" id="dmRulesBtn"><i class="bi bi-sliders"></i> Authorization Rules</button>
        <?php endif; ?>
        <button type="button" class="rh-btn rh-btn-pdf" id="dmExportPdf"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>
        <button type="button" class="rh-btn rh-btn-excel" id="dmExportExcel"><i class="bi bi-file-earmark-excel"></i> Export Excel</button>
    </div>
</div>

<div class="rh-cards">
    <div class="rh-card-item">
        <div class="rh-card-icon"><i class="bi bi-folder2-open"></i></div>
        <div>
            <small class="rh-card-label">Case Count</small>
            <div class="rh-card-value" id="dmMetricCases"><?= number_format((int) ($metrics['case_count'] ?? 0)) ?></div>
            <small class="rh-card-sub" id="dmMetricOpen"><?= (int) ($metrics['open_cases'] ?? 0) ?> open</small>
        </div>
    </div>
    <div class="rh-card-item">
        <div class="rh-card-icon rh-card-icon-red"><i class="bi bi-exclamation-triangle"></i></div>
        <div>
            <small class="rh-card-label">Original Discrepancy</small>
            <div class="rh-card-value rh-value-red" id="dmMetricOriginal">₱<?= number_format((float) ($metrics['original_discrepancy'] ?? 0), 2) ?></div>
            <small class="rh-card-sub">Immutable shortage</small>
        </div>
    </div>
    <div class="rh-card-item">
        <div class="rh-card-icon"><i class="bi bi-cash-coin"></i></div>
        <div>
            <small class="rh-card-label">Recovered</small>
            <div class="rh-card-value" id="dmMetricRecovered">₱<?= number_format((float) ($metrics['recovered'] ?? 0), 2) ?></div>
            <small class="rh-card-sub">Cash and payroll recoveries</small>
        </div>
    </div>
    <div class="rh-card-item">
        <div class="rh-card-icon"><i class="bi bi-journal-check"></i></div>
        <div>
            <small class="rh-card-label">Approved Adjustments</small>
            <div class="rh-card-value" id="dmMetricAdjustments">₱<?= number_format((float) ($metrics['approved_adjustments'] ?? 0), 2) ?></div>
            <small class="rh-card-sub">Adjustments and waivers</small>
        </div>
    </div>
    <div class="rh-card-item rh-card-dark">
        <div class="rh-card-icon"><i class="bi bi-hourglass-split"></i></div>
        <div>
            <small class="rh-card-label">Outstanding Balance</small>
            <div class="rh-card-value" id="dmMetricOutstanding">₱<?= number_format((float) ($metrics['outstanding_balance'] ?? 0), 2) ?></div>
            <small class="rh-card-sub">From posted ledger entries</small>
        </div>
    </div>
</div>

<div class="dm-highlights">
    <span class="rh-badge rh-badge-amber"><span class="rh-dot"></span>Overdue investigations: <strong id="dmMetricOverdue"><?= (int) ($metrics['overdue_investigations'] ?? 0) ?></strong></span>
    <span class="rh-badge rh-badge-blue"><span class="rh-dot"></span>Pending approvals: <strong id="dmMetricPending"><?= (int) ($metrics['pending_approvals'] ?? 0) ?></strong></span>
    <span class="rh-badge rh-badge-gray" id="dmFilterDescription"><span class="rh-dot"></span>All cases</span>
</div>

<div class="rh-filter">

    <div class="rh-search">
        <i class="bi bi-search rh-search-icon"></i>
        <input type="text" id="fDriver" placeholder="Search driver name or ID...">
    </div>

    <select id="fStatus" class="rh-select" aria-label="Filter by status">
        <option value="">All Status</option>
        <option>Open</option>
        <option>Under Investigation</option>
        <option>Awaiting Driver Response</option>
        <option>Findings Submitted</option>
        <option>Pending Manager Approval</option>
        <option>Returned for Further Investigation</option>
        <option>Awaiting Approval</option>
        <option>Awaiting Recovery</option>
        <option>Resolved</option>
        <option>Closed</option>
    </select>

    <select id="fType" class="rh-select" aria-label="Filter by type">
        <option value="">All Types</option>
        <option>Cash Shortage</option>
        <option>QR Ph Payment Mismatch</option>
        <option>System or Fare Error</option>
        <option>Overdue Remittance</option>
        <option>Excess Remittance</option>
    </select>

    <div class="rh-daterange">
        <i class="bi bi-calendar3 rh-daterange-icon"></i>
        <input type="date" id="fFrom" aria-label="Business date from">
        <span class="rh-daterange-sep">–</span>
        <input type="date" id="fTo" aria-label="Business date to">
    </div>

    <div class="rh-search" style="flex:0 1 190px;">
        <i class="bi bi-person rh-search-icon"></i>
        <input type="text" id="fReviewer" placeholder="Reviewer">
    </div>

    <select id="fAge" class="rh-select" aria-label="Filter by case age">
        <option value="">Any Age</option>
        <option value="0-3">0-3 days</option>
        <option value="4-7">4-7 days</option>
        <option value="8-14">8-14 days</option>
        <option value="15+">15+ days</option>
    </select>

    <select id="fOverdue" class="rh-select" aria-label="Filter by priority">
        <option value="">All Cases</option>
        <option value="1">Overdue investigations</option>
    </select>

    <div class="rh-filter-actions">
        <button type="button" class="rh-btn rh-btn-pdf" id="dmApplyFilters"><i class="bi bi-funnel-fill"></i> Apply</button>
        <button type="button" class="rh-btn rh-btn-muted" id="dmResetFilters"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
    </div>

</div>

<div class="rh-table-wrap">

<div class="rh-table-meta">
    <span class="rh-filter-count" id="dmListCount"><i class="bi bi-list-ul"></i><?= count($list['data'] ?? []) ?> case(s)</span>
</div>

<div class="table-responsive">

<table class="table align-middle mb-0 rh-table">

<thead>
<tr>
    <th>Case</th>
    <th>Driver</th>
    <th>Type</th>
    <th>Status</th>
    <th class="rh-num">Amount</th>
    <th>Reviewer</th>
    <th>Follow-up</th>
    <th>Actions</th>
</tr>
</thead>

<tbody id="dmCaseRows">
    <?php if (empty($list['data'])): ?>
        <tr><td colspan="8" class="rh-empty"><i class="bi bi-inbox" style="font-size:1.4rem;display:block;margin-bottom:6px;"></i>No discrepancy cases match the current filters.</td></tr>
    <?php else: ?>
        <?php foreach ($list['data'] as $case): ?>
            <?php
            $caseShortage = (float) $case['discrepancy_amount'];
            $caseExcess = (float) $case['excess_amount'];
            $caseOutstanding = (float) ($case['outstanding_balance'] ?? 0);
            $caseTerminal = in_array((string) $case['status'], ['Resolved', 'Closed'], true);
            $caseApprovalStage = in_array((string) $case['status'], ['Findings Submitted', 'Pending Manager Approval'], true);
            $caseManager = in_array($discRole, ['finance_manager', 'admin', 'super_admin'], true);
            if ($caseTerminal) {
                $caseActionLabel = 'View details';
                $caseActionIcon = 'bi-eye';
            } elseif ($caseApprovalStage && $caseManager) {
                $caseActionLabel = 'Review findings';
                $caseActionIcon = 'bi-shield-check';
            } else {
                $caseActionLabel = 'Investigate';
                $caseActionIcon = 'bi-search';
            }
            ?>
            <tr class="<?= !empty($case['is_overdue_investigation']) ? 'rh-row-overdue' : '' ?>" data-case-id="<?= (int) $case['id'] ?>">
                <td class="rh-id"><?= htmlspecialchars((string) $case['case_ref'], ENT_QUOTES) ?>
                    <span class="rh-time"><?= htmlspecialchars((string) $case['business_date'], ENT_QUOTES) ?><?= !empty($case['has_pending_approval']) ? ' · approval pending' : '' ?></span>
                </td>
                <td>
                    <span class="rh-driver-name"><?= htmlspecialchars((string) ($case['driver_name'] ?? $case['driver_id']), ENT_QUOTES) ?></span>
                    <span class="rh-driver-id"><?= htmlspecialchars((string) $case['driver_id'], ENT_QUOTES) ?></span>
                </td>
                <td><span class="dm-type <?= discTypeClass((string) $case['case_type']) ?>"><?= htmlspecialchars((string) $case['case_type'], ENT_QUOTES) ?></span></td>
                <td><span class="rh-badge <?= discStatusClass((string) $case['status']) ?>"><span class="rh-dot"></span><?= htmlspecialchars((string) $case['status'], ENT_QUOTES) ?></span></td>
                <td class="rh-num">
                    <?php if ($caseShortage > 0): ?>
                        <span class="rh-amt-red">₱<?= number_format($caseShortage, 2) ?></span>
                        <span class="rh-time">shortage</span>
                    <?php elseif ($caseExcess > 0): ?>
                        <span class="rh-amt-amber">₱<?= number_format($caseExcess, 2) ?></span>
                        <span class="rh-time">excess</span>
                    <?php else: ?>
                        <span class="rh-amt-neutral">—</span>
                    <?php endif; ?>
                    <?php if ($caseOutstanding > 0): ?><span class="rh-time">Outstanding ₱<?= number_format($caseOutstanding, 2) ?></span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) ($case['assigned_reviewer'] ?? 'Unassigned'), ENT_QUOTES) ?></td>
                <td>
                    <span class="rh-date"><?= !empty($case['due_date']) ? htmlspecialchars((string) $case['due_date'], ENT_QUOTES) : '—' ?></span>
                    <span class="rh-time">Age <?= (int) ($case['age_days'] ?? 0) ?>d<?= !empty($case['is_overdue_investigation']) ? ' · overdue' : '' ?></span>
                </td>
                <td>
                    <div class="rh-actions">
                        <button type="button" class="rh-action dm-open-case" data-case-id="<?= (int) $case['id'] ?>" title="<?= htmlspecialchars($caseActionLabel, ENT_QUOTES) ?>">
                            <i class="bi <?= $caseActionIcon ?>"></i>
                        </button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>

</table>

</div>

<div class="rh-pagination">
    <span class="rh-page-info" id="dmPageInfo">Page <?= (int) ($list['page'] ?? 1) ?> of <?= (int) ($list['pages'] ?? 1) ?></span>
    <div class="rh-page-controls">
        <button type="button" class="rh-page-btn" id="dmPrevPage" <?= (($list['page'] ?? 1) <= 1) ? 'disabled' : '' ?>>Prev</button>
        <button type="button" class="rh-page-btn" id="dmNextPage" <?= (($list['page'] ?? 1) >= ($list['pages'] ?? 1)) ? 'disabled' : '' ?>>Next</button>
    </div>
    <div class="rh-page-size">
        <span>Rows per page:</span>
        <select id="dmPerPage">
            <?php foreach ([10, 25, 50] as $size): ?>
                <option value="<?= $size ?>" <?= ((int) ($list['perPage'] ?? 25) === $size) ? 'selected' : '' ?>><?= $size ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

</div>

</div>
</div>

<!-- ======================= CASE WORKSPACE MODAL ======================= -->
<div class="modal fade" id="dmCaseModal" tabindex="-1" aria-labelledby="dmCaseTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rmg-detail-modal">
            <div class="modal-header rmg-detail-header">
                <div class="rmg-detail-identity">
                    <div class="rmg-detail-avatar-wrap" id="dmCaseAvatarWrap">
                        <div class="rmg-avatar rmg-detail-avatar" id="dmCaseAvatar">—</div>
                        <span class="rmg-detail-dot" aria-hidden="true"></span>
                    </div>
                    <div class="rmg-detail-who">
                        <div class="rmg-detail-kicker" id="dmCaseKicker">Discrepancy case</div>
                        <h5 class="modal-title" id="dmCaseTitle">Case</h5>
                        <div id="dmCaseChips" class="rmg-detail-chips"></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-detail-body" id="dmCaseBody">
                <div class="dm-loading"><i class="bi bi-hourglass-split"></i> Loading case...</div>
            </div>
            <div class="modal-footer rmg-detail-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Close</button>
                <div id="dmCaseFooterActions" class="rmg-detail-actions"></div>
            </div>
        </div>
    </div>
</div>

<!-- ======================= AUTHORIZATION RULES MODAL ======================= -->
<div class="modal fade" id="dmRulesModal" tabindex="-1" aria-labelledby="dmRulesTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-sliders"></i></span>
                    <div>
                        <div class="rmg-form-kicker">Finance governance</div>
                        <h5 class="modal-title rmg-form-title" id="dmRulesTitle">Discrepancy authorization rules</h5>
                        <div class="rmg-form-sub">Who may propose and approve each financial action</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <section class="rmg-detail-section">
                    <div class="rmg-section-head">
                        <span class="rmg-section-icon tone-role"><i class="bi bi-shield-lock"></i></span>
                        <div class="rmg-section-title">Rule format</div>
                    </div>
                    <div class="rmg-detail-note">
                        Each action has <code>propose_roles</code> and <code>approve_roles</code> arrays
                        (role names from the users table: <code>finance_officer</code>, <code>finance_manager</code>,
                        <code>admin</code>, <code>super_admin</code>), <code>requires_approval</code>,
                        <code>self_approval</code> (true only when policy explicitly allows it), and
                        <code>propose_amount_limit</code> (0 = unlimited; amounts above the limit may not be proposed).
                        A proposal and its approval must always come from different users unless
                        <code>self_approval</code> is true.
                    </div>
                    <div class="rmg-form-field full">
                        <label for="dmRulesJson">Rules JSON</label>
                        <textarea id="dmRulesJson" rows="16" spellcheck="false" style="font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.72rem;"></textarea>
                    </div>
                </section>
            </div>
            <div class="modal-footer rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn" id="dmRulesReload"><i class="bi bi-arrow-clockwise"></i> Reload defaults</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="dmRulesSave"><i class="bi bi-check2"></i> Save rules</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================= CUSTOM ALERT COMPONENT ======================= -->
<div id="customAlertOverlay" class="custom-alert-overlay">
  <div class="custom-alert-card">
    <div class="custom-alert-header">
      <div class="custom-alert-icon"></div>
      <div class="custom-alert-title"></div>
    </div>
    <div class="custom-alert-body">
      <div class="custom-alert-message"></div>
      <div class="custom-alert-actions">
        <button class="custom-alert-btn"></button>
        <button class="custom-alert-btn-cancel"></button>
      </div>
    </div>
  </div>
</div>

<script src="/public/login/custom-alert.js?v=<?= time() ?>"></script>
<script>
window.DISCREPANCY_APP = {
    csrf: <?= json_encode($discCsrf) ?>,
    endpoint: '/finance/discrepancies.php',
    role: <?= json_encode($discRole) ?>,
    canEditRules: <?= $discCanEditRules ? 'true' : 'false' ?>,
    initial: {
        page: <?= (int) ($list['page'] ?? 1) ?>,
        pages: <?= (int) ($list['pages'] ?? 1) ?>,
        perPage: <?= (int) ($list['perPage'] ?? 25) ?>,
        total: <?= (int) ($list['total'] ?? 0) ?>
    }
};
</script>
<script>
(function () {
    "use strict";

    var APP = window.DISCREPANCY_APP;
    var state = {
        page: APP.initial.page || 1,
        perPage: APP.initial.perPage || 25,
        pages: APP.initial.pages || 1,
        total: APP.initial.total || 0,
        details: null,
        financeUsers: [],
        filtersDescription: "All cases"
    };

    function $(selector) { return document.querySelector(selector); }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? "" : value)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function peso(value) {
        return "₱" + Number(value || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function statusBadge(status) {
        var map = {
            "Open": "rh-badge-gray",
            "Under Investigation": "rh-badge-amber",
            "Awaiting Driver Response": "rh-badge-amber",
            "Findings Submitted": "rh-badge-blue",
            "Pending Manager Approval": "rh-badge-violet",
            "Returned for Further Investigation": "rh-badge-red",
            "Awaiting Approval": "rh-badge-blue",
            "Awaiting Recovery": "rh-badge-amber",
            "Resolved": "rh-badge-green",
            "Closed": "rh-badge-gray"
        };
        return '<span class="rh-badge ' + (map[status] || "rh-badge-gray") + '"><span class="rh-dot"></span>' + escapeHtml(status) + "</span>";
    }

    function typeBadge(type) {
        var map = {
            "Cash Shortage": "dm-type-shortage",
            "QR Ph Payment Mismatch": "dm-type-qrph",
            "System or Fare Error": "dm-type-system",
            "Overdue Remittance": "dm-type-overdue",
            "Excess Remittance": "dm-type-excess"
        };
        return '<span class="dm-type ' + (map[type] || "dm-type-system") + '">' + escapeHtml(type) + "</span>";
    }

    function stateBadge(value) {
        return '<span class="dm-ledger-state dm-state-' + escapeHtml(value) + '">' + escapeHtml(value) + "</span>";
    }

    function filtersFromInputs() {
        return {
            status: $("#fStatus").value,
            type: $("#fType").value,
            driver: $("#fDriver").value.trim(),
            business_from: $("#fFrom").value,
            business_to: $("#fTo").value,
            reviewer: $("#fReviewer").value.trim(),
            age: $("#fAge").value,
            overdue: $("#fOverdue").value
        };
    }

    function filtersToQuery(extra) {
        var filters = filtersFromInputs();
        var params = new URLSearchParams();
        Object.keys(filters).forEach(function (key) {
            if (filters[key] !== "" && filters[key] !== null) {
                params.set(key, filters[key]);
            }
        });
        if (extra) {
            Object.keys(extra).forEach(function (key) { params.set(key, extra[key]); });
        }
        return params;
    }

    function setText(selector, value) {
        var element = $(selector);
        if (element) {
            element.textContent = value;
        }
    }

    function applyMetrics(metrics, description) {
        setText("#dmMetricCases", Number(metrics.case_count || 0).toLocaleString());
        setText("#dmMetricOpen", Number(metrics.open_cases || 0).toLocaleString() + " open");
        setText("#dmMetricOriginal", peso(metrics.original_discrepancy));
        setText("#dmMetricRecovered", peso(metrics.recovered));
        setText("#dmMetricAdjustments", peso(metrics.approved_adjustments));
        setText("#dmMetricOutstanding", peso(metrics.outstanding_balance));
        setText("#dmMetricOverdue", Number(metrics.overdue_investigations || 0).toLocaleString());
        setText("#dmMetricPending", Number(metrics.pending_approvals || 0).toLocaleString());
        if (description) {
            state.filtersDescription = description;
            $("#dmFilterDescription").innerHTML = '<span class="rh-dot"></span>' + escapeHtml(description);
        }
    }

    function reviewerLabel(username) {
        if (!username) { return "Unassigned"; }
        var users = state.financeUsers || [];
        for (var i = 0; i < users.length; i++) {
            if (users[i].username === username) {
                return users[i].display_name || username;
            }
        }
        return username;
    }

    function roleLabel(role) {
        var map = { finance_manager: "Finance Manager", finance_officer: "Finance Officer" };
        return map[role] || role || "";
    }

    function reviewerOptionsHtml(current) {
        var users = state.financeUsers || [];
        if (!users.length) {
            return current
                ? '<option value="' + escapeHtml(current) + '" selected>' + escapeHtml(current) + " (current)</option>"
                : '<option value="" disabled selected>Loading finance users...</option>';
        }
        var known = users.some(function (u) { return u.username === current; });
        var html = "";
        if (current && !known) {
            html += '<option value="' + escapeHtml(current) + '" selected>' + escapeHtml(current) + " (current)</option>";
        }
        html += users.map(function (u) {
            return '<option value="' + escapeHtml(u.username) + '"' + (u.username === current ? " selected" : "") + ">" +
                escapeHtml(u.display_name || u.username) + " — " + escapeHtml(roleLabel(u.role)) + "</option>";
        }).join("");
        return html;
    }

    function loadFinanceUsers() {
        return fetch(APP.endpoint + "?action=finance_users", { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && Array.isArray(data.users)) {
                    state.financeUsers = data.users;
                    var select = document.getElementById("dmAssignReviewer");
                    if (select && state.details) {
                        select.innerHTML = reviewerOptionsHtml(state.details.case.assigned_reviewer);
                    }
                }
            })
            .catch(function () {
                // The reviewer select falls back to the current value.
            });
    }

    function rowHtml(c) {
        var overdue = !!(c.is_overdue_investigation);
        var pending = !!(c.has_pending_approval);
        var classes = overdue ? ' class="rh-row-overdue"' : "";
        var shortage = Number(c.discrepancy_amount || 0);
        var excess = Number(c.excess_amount || 0);
        var outstanding = Number(c.outstanding_balance || 0);

        var amountHtml;
        if (shortage > 0) {
            amountHtml = '<span class="rh-amt-red">' + peso(shortage) + '</span><span class="rh-time">shortage</span>';
        } else if (excess > 0) {
            amountHtml = '<span class="rh-amt-amber">' + peso(excess) + '</span><span class="rh-time">excess</span>';
        } else {
            amountHtml = '<span class="rh-amt-neutral">—</span>';
        }
        if (outstanding > 0) {
            amountHtml += '<span class="rh-time">Outstanding ' + peso(outstanding) + "</span>";
        }

        var closed = c.status === "Resolved" || c.status === "Closed";
        var approvalStage = c.status === "Findings Submitted" || c.status === "Pending Manager Approval";
        var managerRole = ["finance_manager", "admin", "super_admin"].indexOf(APP.role) !== -1;
        var actionLabel = closed ? "View details" : ((approvalStage && managerRole) ? "Review findings" : "Investigate");
        var actionIcon = closed ? "bi-eye" : ((approvalStage && managerRole) ? "bi-shield-check" : "bi-search");

        return "<tr" + classes + ' data-case-id="' + Number(c.id) + '">' +
            '<td class="rh-id">' + escapeHtml(c.case_ref || ("DIS-" + String(c.id).padStart(5, "0"))) +
            '<span class="rh-time">' + escapeHtml(c.business_date || "—") + (pending ? " · approval pending" : "") + "</span></td>" +
            '<td><span class="rh-driver-name">' + escapeHtml(c.driver_name || c.driver_id || "—") + "</span>" +
            '<span class="rh-driver-id">' + escapeHtml(c.driver_id || "") + "</span></td>" +
            "<td>" + typeBadge(c.case_type) + "</td>" +
            "<td>" + statusBadge(c.status) + "</td>" +
            '<td class="rh-num">' + amountHtml + "</td>" +
            "<td>" + escapeHtml(reviewerLabel(c.assigned_reviewer)) + "</td>" +
            '<td><span class="rh-date">' + escapeHtml(c.due_date || "—") + "</span>" +
            '<span class="rh-time">Age ' + Number(c.age_days || 0) + "d" + (overdue ? " · overdue" : "") + "</span></td>" +
            '<td><div class="rh-actions"><button type="button" class="rh-action dm-open-case" data-case-id="' + Number(c.id) + '" title="' + escapeHtml(actionLabel) + '">' +
            '<i class="bi ' + actionIcon + '"></i></button></div></td>' +
            "</tr>";
    }

    function loadCases() {
        var params = filtersToQuery({ page: state.page, per_page: state.perPage });
        fetch(APP.endpoint + "?action=list&" + params.toString(), { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    showAlert("error", "Unable to load cases", data.message || "Please try again.");
                    return;
                }
                applyMetrics(data.metrics, data.filters_description);
                var rows = data.cases || [];
                $("#dmCaseRows").innerHTML = rows.length
                    ? rows.map(rowHtml).join("")
                    : '<tr><td colspan="8" class="rh-empty"><i class="bi bi-inbox" style="font-size:1.4rem;display:block;margin-bottom:6px;"></i>No discrepancy cases match the current filters.</td></tr>';
                $("#dmListCount").innerHTML = '<i class="bi bi-list-ul"></i> ' + Number(data.pagination.total).toLocaleString() + " case(s)";
                state.page = Number(data.pagination.page);
                state.pages = Number(data.pagination.pages);
                state.total = Number(data.pagination.total);
                $("#dmPageInfo").textContent = "Page " + state.page + " of " + state.pages;
                $("#dmPrevPage").disabled = state.page <= 1;
                $("#dmNextPage").disabled = state.page >= state.pages;
            })
            .catch(function () {
                showAlert("error", "Connection Error", "Unable to load discrepancy cases.");
            });
    }

    function postAction(action, payload, onSuccess, isFormData) {
        var body;
        if (isFormData) {
            body = payload;
        } else {
            body = new FormData();
            Object.keys(payload).forEach(function (key) { body.set(key, payload[key]); });
        }
        // Send the token both as a header and as a form field: some hosts do
        // not expose X-CSRF-Token to PHP through getallheaders(), and the
        // middleware accepts either.
        body.set("_csrf_token", APP.csrf);
        return fetch(APP.endpoint + "?action=" + action, {
            method: "POST",
            headers: { "X-CSRF-Token": APP.csrf },
            body: body,
            credentials: "same-origin"
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    showAlert("error", "Action Failed", data.message || "The action could not be completed.");
                    return null;
                }
                if (onSuccess) { onSuccess(data); }
                return data;
            })
            .catch(function () {
                showAlert("error", "Connection Error", "The action could not be completed.");
                return null;
            });
    }

    function initials(name) {
        var parts = String(name || "").trim().split(/\s+/).slice(0, 2);
        return parts.map(function (part) { return part.charAt(0).toUpperCase(); }).join("") || "?";
    }

    function avatarStateClass(status) {
        switch (status) {
            case "Resolved": return "is-active";
            case "Closed": return "is-inactive";
            default: return "is-pending";
        }
    }

    function detailSection(icon, tone, title, body) {
        return '<section class="rmg-detail-section"><div class="rmg-section-head">' +
            '<span class="rmg-section-icon ' + tone + '"><i class="bi ' + icon + '"></i></span>' +
            '<div class="rmg-section-title">' + escapeHtml(title) + "</div></div>" + body + "</section>";
    }

    function detailField(label, value, valueClass) {
        return '<div class="rmg-field"><div class="k">' + escapeHtml(label) + "</div>" +
            '<div class="v' + (valueClass ? " " + valueClass : "") + '">' + value + "</div></div>";
    }

    function renderDetails(details) {
        state.details = details;
        var c = details.case || {};
        var calc = details.calculation || {};
        var totals = details.ledger_totals || {};
        var permissions = details.permissions || {};

        /* Header identity — mirrors the Role Management account detail modal. */
        var driverName = c.driver_name || c.driver_id || "Case";
        $("#dmCaseTitle").textContent = driverName;
        $("#dmCaseKicker").textContent = "Discrepancy case · " + (c.case_ref || "Case");
        $("#dmCaseAvatar").textContent = initials(driverName);
        $("#dmCaseAvatarWrap").className = "rmg-detail-avatar-wrap " + avatarStateClass(c.status);
        $("#dmCaseChips").innerHTML =
            '<span class="rmg-type-chip"><i class="bi bi-tag"></i> ' + escapeHtml(c.case_type || "—") + "</span>" +
            '<span class="rmg-type-chip"><i class="bi bi-flag"></i> ' + escapeHtml(c.status || "—") + "</span>" +
            '<span class="rmg-type-chip"><i class="bi bi-calendar3"></i> ' + escapeHtml(c.business_date || "—") + "</span>";

        var html = "";

        if (details.is_overdue_investigation) {
            html += '<div class="rmg-status-banner is-pending"><span class="rmg-status-dot"></span>' +
                "This investigation is past its due date. Record findings or escalate.</div>";
        }

        /* Case summary — compact two-column reference field grid. */
        html += detailSection("bi-clipboard-data", "tone-role", "Case summary",
            '<div class="rmg-detail-grid">' +
            detailField("Status", escapeHtml(c.status || "—")) +
            detailField("Classification", escapeHtml(c.case_type || "—")) +
            detailField("Assigned reviewer", escapeHtml(c.assigned_reviewer || "Unassigned")) +
            detailField("Case age", Number(details.age_days || 0) + " day(s)") +
            detailField("Due date", escapeHtml(c.due_date || "—")) +
            detailField("Next action", escapeHtml(details.next_action || "—")) +
            detailField("Remittance", escapeHtml((details.remittance && details.remittance.ref) || "—")) +
            detailField("Opened by", escapeHtml(c.opened_by || "—")) +
            "</div>");

        /* Lifecycle actions — status, assignment, and reclassification in compact rows. */
        var transitions = details.allowed_transitions || [];
        var lifecycle = "";
        if (transitions.length) {
            lifecycle += '<div class="dm-control-row">' +
                '<div class="rmg-form-field"><label for="dmTransitionStatus">Move to status</label><select id="dmTransitionStatus">' +
                transitions.map(function (t) { return "<option>" + escapeHtml(t) + "</option>"; }).join("") +
                "</select></div>" +
                '<div class="rmg-form-field"><label for="dmTransitionReason">Reason (required for Resolved, Closed, reopen)</label>' +
                '<input type="text" id="dmTransitionReason" placeholder="Reason for the status change"></div>' +
                '<button type="button" class="rmg-btn rmg-btn-primary" id="dmTransitionBtn"><i class="bi bi-check2-circle"></i> Update</button>' +
                "</div>";
        } else {
            lifecycle += '<div class="rmg-history-empty">No further lifecycle transitions are available.</div>';
        }
        lifecycle += '<div class="dm-control-row">' +
            '<div class="rmg-form-field"><label for="dmAssignReviewer">Assign reviewer</label>' +
            '<select id="dmAssignReviewer">' + reviewerOptionsHtml(c.assigned_reviewer) + "</select></div>" +
            '<div class="rmg-form-field"><label for="dmAssignDueDate">Due date</label>' +
            '<input type="date" id="dmAssignDueDate" value="' + escapeHtml(c.due_date || "") + '"></div>' +
            '<button type="button" class="rmg-btn" id="dmAssignBtn"><i class="bi bi-person-check"></i> Assign</button>' +
            "</div>" +
            '<div class="dm-control-row">' +
            '<div class="rmg-form-field"><label for="dmReclassifyType">Reclassify as</label><select id="dmReclassifyType">' +
            ["Cash Shortage", "QR Ph Payment Mismatch", "System or Fare Error", "Overdue Remittance", "Excess Remittance"]
                .map(function (t) { return '<option' + (t === c.case_type ? " selected" : "") + ">" + escapeHtml(t) + "</option>"; }).join("") +
            "</select></div>" +
            '<div class="rmg-form-field"><label for="dmReclassifyReason">Reclassification reason</label>' +
            '<input type="text" id="dmReclassifyReason" placeholder="Why the type changes"></div>' +
            '<button type="button" class="rmg-btn" id="dmReclassifyBtn"><i class="bi bi-tags"></i> Reclassify</button>' +
            "</div>";
        html += detailSection("bi-signpost-split", "tone-status", "Lifecycle actions", lifecycle);

        /* Amounts & calculation — compact reference field grid. */
        html += detailSection("bi-calculator", "tone-account", "Amounts & calculation",
            '<div class="rmg-detail-grid">' +
            detailField("Original expected", peso(calc.expected_amount)) +
            detailField("Original actual", calc.actual_amount !== null ? peso(calc.actual_amount) : "—") +
            detailField("Shortage (immutable)", peso(calc.shortage), "dm-value-red") +
            detailField("Excess", peso(calc.excess), "dm-value-amber") +
            detailField("Outstanding balance", peso(calc.outstanding_balance)) +
            detailField("Posted entries", String(Number(totals.posted_count || 0))) +
            "</div>" +
            '<div class="rmg-detail-note" style="margin-top:10px;margin-bottom:0;">Derived from the stored expected and actual amounts; resolution activity never rewrites these figures.</div>');

        /* Trips & buses */
        var trips = details.trips || [];
        var tripsBody;
        if (trips.length) {
            tripsBody = '<div class="dm-table-scroll"><table class="dm-table-sm"><thead><tr><th>Trip</th><th>Bus</th><th>Source</th><th>Passengers</th><th>Collection</th><th>Cash</th><th>QR Ph</th></tr></thead><tbody>' +
                trips.map(function (t) {
                    return "<tr><td>" + escapeHtml(t.trip_no || "—") + "</td><td>" + escapeHtml(t.bus_id || "—") + "</td><td>" + escapeHtml(t.source) +
                        "</td><td>" + Number(t.total_passengers || 0) + "</td><td>" + peso(t.total_collection) +
                        "</td><td>" + peso(t.cash_total) + "</td><td>" + peso(t.qrph_total) + "</td></tr>";
                }).join("") + "</tbody></table></div>";
        } else {
            tripsBody = '<div class="rmg-history-empty">No trip records found for this business date.</div>';
        }
        html += detailSection("bi-bus-front", "tone-role", "Related trips & buses", tripsBody);

        /* Payment references */
        var references = details.payment_references || [];
        var referencesBody;
        if (references.length) {
            referencesBody = '<div class="dm-table-scroll"><table class="dm-table-sm"><thead><tr><th>Trip</th><th>Method</th><th>Amount</th><th>PayMongo ID</th><th>GCash/QR Ref</th><th>Source</th></tr></thead><tbody>' +
                references.map(function (r) {
                    return "<tr><td>" + escapeHtml(r.trip_no || "—") + "</td><td>" + escapeHtml(r.payment_method || "—") +
                        "</td><td>" + peso(r.fare) + "</td><td>" + escapeHtml(r.paymongo_payment_id || "—") +
                        "</td><td>" + escapeHtml(r.gcash_reference_no || "—") + "</td><td>" + escapeHtml(r.source) + "</td></tr>";
                }).join("") + "</tbody></table></div>";
        } else {
            referencesBody = '<div class="rmg-history-empty">No non-cash payment references for this business date.</div>';
        }
        html += detailSection("bi-phone", "tone-role", "Payment references", referencesBody);

        /* Findings — officer investigation controls and manager approval actions. */
        var isManager = !!permissions.is_manager;
        var isInvestigator = !!permissions.is_investigator;
        var isTerminal = c.status === "Resolved" || c.status === "Closed";
        var canRequestDriver = (details.allowed_transitions || []).indexOf("Awaiting Driver Response") !== -1;

        var findingsBody = '<div class="rmg-form-field full"><label for="dmFindings">Investigation findings</label>' +
            '<textarea id="dmFindings" rows="4" placeholder="What the investigation established">' + escapeHtml(c.findings || "") + "</textarea></div>" +
            '<div class="rmg-form-grid" style="margin-top:10px;">' +
            '<div class="rmg-form-field"><label for="dmFindingsReason">Recommended resolution</label>' +
            '<input type="text" id="dmFindingsReason" value="' + escapeHtml(c.resolution_reason || "") + '" placeholder="Resolution the officer recommends"></div>' +
            '<div class="rmg-form-field"><label for="dmFindingsRemarks">Remarks / information requested</label>' +
            '<input type="text" id="dmFindingsRemarks" placeholder="Optional remarks for the manager or the driver"></div>' +
            "</div>";

        if (isInvestigator && !isTerminal) {
            findingsBody += '<div class="rmg-detail-actions">' +
                '<button type="button" class="rmg-btn" id="dmFindingsBtn"><i class="bi bi-save"></i> Save draft</button>' +
                (canRequestDriver
                    ? '<button type="button" class="rmg-btn" id="dmRequestExplanationBtn"><i class="bi bi-chat-left-text"></i> Request driver explanation</button>'
                    : "") +
                (permissions.can_submit_findings
                    ? '<button type="button" class="rmg-btn rmg-btn-primary" id="dmSubmitFindingsBtn"><i class="bi bi-send-check"></i> Submit findings for approval</button>'
                    : "") +
                "</div>";
        }

        if (c.findings_submitted_by) {
            findingsBody += '<div class="rmg-detail-note" style="margin-top:10px;margin-bottom:0;">' +
                "Submitted for approval by <strong>" + escapeHtml(c.findings_submitted_by) + "</strong>" +
                (c.findings_submitted_at ? " on " + escapeHtml(c.findings_submitted_at) : "") +
                (permissions.is_submitter ? " — you submitted these findings, so a different manager must decide." : "") +
                "</div>";
        }

        if (permissions.can_start_manager_review) {
            findingsBody += '<div class="rmg-detail-actions">' +
                '<button type="button" class="rmg-btn rmg-btn-primary" id="dmStartReviewBtn"><i class="bi bi-search"></i> Start review</button>' +
                "</div>";
        }

        if (permissions.can_decide) {
            findingsBody += '<div class="dm-approval" style="margin-top:10px;"><div class="dm-approval-head">' +
                "<strong>Approval decision</strong><span class=\"rmg-history-meta\">Finance Manager only</span></div>" +
                '<div class="dm-control-row" style="grid-template-columns:minmax(0,1fr) auto auto auto;margin-bottom:0;">' +
                '<div class="rmg-form-field"><label for="dmDecisionText">Decision reason (required)</label>' +
                '<input type="text" id="dmDecisionText" placeholder="Document the approval, return, or rejection"></div>' +
                '<button type="button" class="rmg-btn rmg-btn-primary dm-decision" data-decision="approved">Approve</button>' +
                '<button type="button" class="rmg-btn dm-decision" data-decision="returned">Return</button>' +
                '<button type="button" class="rmg-btn rmg-btn-danger dm-decision" data-decision="rejected">Reject</button>' +
                "</div></div>";
        } else if (isManager && c.status === "Pending Manager Approval" && permissions.is_submitter) {
            findingsBody += '<div class="rmg-detail-note" style="margin-top:10px;margin-bottom:0;">' +
                "Awaiting an independent Finance Manager decision (you submitted these findings).</div>";
        }

        if (c.manager_decision) {
            findingsBody += '<div class="rmg-detail-note" style="margin-top:10px;margin-bottom:0;">' +
                "Last decision: <strong>" + escapeHtml(c.manager_decision) + "</strong>" +
                (c.manager_decision_by ? " by " + escapeHtml(c.manager_decision_by) : "") +
                (c.manager_decision_at ? " on " + escapeHtml(c.manager_decision_at) : "") +
                (c.manager_decision_reason ? " — " + escapeHtml(c.manager_decision_reason) : "") +
                "</div>";
        }

        html += detailSection("bi-journal-text", "tone-docs", "Investigation findings", findingsBody);

        /* Evidence */
        var evidence = details.evidence || [];
        var evidenceBody = "";
        if (evidence.length) {
            evidenceBody += '<div class="rmg-docs">' + evidence.map(function (e) {
                return '<a href="/finance/' + escapeHtml(e.file_path) + '" target="_blank" rel="noopener">' +
                    '<i class="bi bi-file-earmark"></i> ' + escapeHtml(e.original_name || e.file_path) + "</a>";
            }).join("") + "</div>";
        } else {
            evidenceBody += '<div class="rmg-history-empty">No evidence uploaded yet. PDF, PNG, or JPG up to 5MB.</div>';
        }
        evidenceBody += '<div class="dm-control-row" style="grid-template-columns:minmax(0,1fr) auto;margin-top:10px;">' +
            '<div class="rmg-form-field"><label for="dmEvidenceFile">Upload evidence</label>' +
            '<input type="file" id="dmEvidenceFile" accept=".pdf,.png,.jpg,.jpeg"></div>' +
            '<button type="button" class="rmg-btn" id="dmEvidenceBtn"><i class="bi bi-upload"></i> Upload</button>' +
            "</div>";
        html += detailSection("bi-paperclip", "tone-docs", "Supporting evidence", evidenceBody);

        /* Ledger */
        var ledger = details.ledger || [];
        var ledgerBody = '<div class="rmg-detail-grid">' +
            detailField("Cash received", peso(totals.cash_received)) +
            detailField("Payroll recovery", peso(totals.payroll_recovery)) +
            detailField("Corrections", peso(totals.corrections)) +
            detailField("Adjustments & waivers", peso(totals.adjustments)) +
            detailField("Pending proposals", peso(totals.pending_amount)) +
            "</div>";
        if (ledger.length) {
            ledgerBody += '<div class="dm-table-scroll" style="margin-top:10px;"><table class="dm-table-sm"><thead><tr><th>#</th><th>Instrument</th><th>Class</th><th>Amount</th><th>Effect</th><th>State</th><th>Reference</th><th>Action</th></tr></thead><tbody>' +
                ledger.map(function (entry) {
                    var action = "";
                    if (entry.state === "approved") {
                        action += '<button type="button" class="rmg-btn rmg-btn-sm dm-post-entry" data-entry-id="' + Number(entry.id) + '">Post</button> ';
                    }
                    if (entry.state === "approved" || entry.state === "posted") {
                        action += '<button type="button" class="rmg-btn rmg-btn-sm rmg-btn-danger dm-void-entry" data-entry-id="' + Number(entry.id) + '">Void</button>';
                    }
                    if (!action) { action = "—"; }
                    return "<tr><td>" + Number(entry.id) + "</td><td>" + escapeHtml(entry.entry_type) + "</td><td>" + escapeHtml(entry.recovery_class) +
                        "</td><td>" + peso(entry.amount) + "</td><td>" + escapeHtml(entry.direction) + "</td><td>" + stateBadge(entry.state) +
                        "</td><td>" + escapeHtml(entry.reference || entry.authorization_reference || "—") + "</td><td>" + action + "</td></tr>";
                }).join("") + "</tbody></table></div>";
        } else {
            ledgerBody += '<div class="rmg-history-empty" style="margin-top:10px;">No ledger activity yet.</div>';
        }
        html += detailSection("bi-journal-text", "tone-account", "Recovery & adjustment ledger", ledgerBody);

        /* Propose a resolution */
        var canPropose = !!permissions.can_propose;
        var proposeBody;
        if (!canPropose) {
            proposeBody = '<div class="rmg-history-empty">Your role cannot propose resolutions. Ask a finance officer or manager.</div>';
        } else {
            proposeBody = '<div class="rmg-form-grid">' +
                '<div class="rmg-form-field"><label for="dmEntryType">Instrument</label><select id="dmEntryType">' +
                Object.keys(details.entry_types || {}).map(function (type) { return '<option value="' + escapeHtml(type) + '">' + escapeHtml(type.replace(/_/g, " ")) + "</option>"; }).join("") +
                "</select></div>" +
                '<div class="rmg-form-field"><label for="dmEntryAmount">Amount</label>' +
                '<input type="number" step="0.01" min="0.01" id="dmEntryAmount" placeholder="0.00"></div>' +
                '<div class="rmg-form-field"><label for="dmEntryDirection">Effect</label><select id="dmEntryDirection">' +
                '<option value="reduce">Reduce balance</option><option value="increase">Increase balance</option></select></div>' +
                '<div class="rmg-form-field"><label for="dmEntryReference">Reference (payment/correction/journal)</label>' +
                '<input type="text" id="dmEntryReference" placeholder="OR / COR / ADJ number"></div>' +
                '<div class="rmg-form-field"><label for="dmEntryAuthorization">Authorization reference (payroll recovery)</label>' +
                '<input type="text" id="dmEntryAuthorization" placeholder="Policy/legal authorization"></div>' +
                '<div class="rmg-form-field full"><label for="dmEntryNotes">Notes / reason</label>' +
                '<input type="text" id="dmEntryNotes" placeholder="Explain the proposal"></div>' +
                "</div>" +
                '<div class="rmg-detail-actions"><button type="button" class="rmg-btn rmg-btn-primary" id="dmProposeBtn"><i class="bi bi-send"></i> Propose for approval</button></div>' +
                '<div class="rmg-detail-note" style="margin-top:10px;margin-bottom:0;">No amount is deducted from driver pay automatically. Payroll recoveries require authorization and approval references.</div>';
        }
        html += detailSection("bi-plus-circle", "tone-status", "Propose a resolution", proposeBody);

        /* Approval queue */
        var approvals = details.approvals || [];
        var pending = approvals.filter(function (a) { return a.status === "pending"; });
        var approvalsBody;
        if (pending.length) {
            approvalsBody = pending.map(function (a) {
                var canDecide = !!permissions.can_approve;
                var block = '<div class="dm-approval"><div class="dm-approval-head">' +
                    "<strong>" + escapeHtml(a.action) + "</strong> · " + peso(a.amount) +
                    '<span class="rmg-history-meta">Requested by ' + escapeHtml(a.requested_by || "—") + " · " + escapeHtml(a.requested_at || "") + "</span></div>";
                if (canDecide) {
                    block += '<div class="dm-control-row" style="grid-template-columns:minmax(0,1fr) auto auto;margin-bottom:0;">' +
                        '<div class="rmg-form-field"><label for="dmDecisionReason' + Number(a.id) + '">Decision reason</label>' +
                        '<input type="text" id="dmDecisionReason' + Number(a.id) + '" placeholder="Required for approve and reject"></div>' +
                        '<button type="button" class="rmg-btn rmg-btn-primary dm-decide" data-approval-id="' + Number(a.id) + '" data-decision="approved">Approve</button>' +
                        '<button type="button" class="rmg-btn rmg-btn-danger dm-decide" data-approval-id="' + Number(a.id) + '" data-decision="rejected">Reject</button>' +
                        "</div>";
                } else {
                    block += '<div class="rmg-history-empty">Awaiting a manager decision. Your role cannot approve this action.</div>';
                }
                return block + "</div>";
            }).join("");
        } else {
            approvalsBody = '<div class="rmg-history-empty">No approval requests are waiting.</div>';
        }
        html += detailSection("bi-shield-check", "tone-status", "Approval queue", approvalsBody);

        /* Case history — reference timeline component. */
        var timeline = details.timeline || [];
        var historyHtml = '<div class="rmg-history"><div class="rmg-block-title"><i class="bi bi-clock-history"></i> Case history</div>';
        if (timeline.length) {
            historyHtml += "<ul>" + timeline.map(function (event) {
                return '<li><span class="rmg-history-action">' + escapeHtml(event.title || event.event || "Change") + "</span> " +
                    escapeHtml(event.detail || "") +
                    '<span class="rmg-history-meta"> — ' + escapeHtml(event.actor || "System") + " · " + escapeHtml(event.at || "") + "</span>" +
                    (event.reason ? '<div class="rmg-history-meta">Reason: ' + escapeHtml(event.reason) + "</div>" : "") +
                    "</li>";
            }).join("") + "</ul>";
        } else {
            historyHtml += '<div class="rmg-history-empty">No events recorded yet.</div>';
        }
        historyHtml += "</div>";
        html += historyHtml;

        $("#dmCaseBody").innerHTML = html;
        bindCaseActions();
    }

    function refreshDetails() {
        if (!state.details) { return; }
        var caseId = state.details.case.id;
        fetch(APP.endpoint + "?action=view&case_id=" + caseId, { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    renderDetails(data.details);
                    loadCases();
                }
            });
    }

    function bindCaseActions() {
        var transitionBtn = $("#dmTransitionBtn");
        if (transitionBtn) {
            transitionBtn.addEventListener("click", function () {
                postAction("status", {
                    case_id: state.details.case.id,
                    status: $("#dmTransitionStatus").value,
                    reason: $("#dmTransitionReason").value
                }, function (data) {
                    showAlert("success", "Status Updated", data.message);
                    refreshDetails();
                });
            });
        }

        var assignBtn = $("#dmAssignBtn");
        if (assignBtn) {
            assignBtn.addEventListener("click", function () {
                postAction("assign", {
                    case_id: state.details.case.id,
                    reviewer: $("#dmAssignReviewer").value,
                    due_date: $("#dmAssignDueDate").value
                }, function (data) {
                    showAlert("success", "Reviewer Assigned", data.message);
                    refreshDetails();
                });
            });
        }

        var reclassifyBtn = $("#dmReclassifyBtn");
        if (reclassifyBtn) {
            reclassifyBtn.addEventListener("click", function () {
                postAction("reclassify", {
                    case_id: state.details.case.id,
                    case_type: $("#dmReclassifyType").value,
                    reason: $("#dmReclassifyReason").value
                }, function (data) {
                    showAlert("success", "Case Reclassified", data.message);
                    refreshDetails();
                });
            });
        }

        var findingsBtn = $("#dmFindingsBtn");
        if (findingsBtn) {
            findingsBtn.addEventListener("click", function () {
                postAction("findings", {
                    case_id: state.details.case.id,
                    findings: $("#dmFindings").value,
                    reason: $("#dmFindingsReason").value
                }, function (data) {
                    showAlert("success", "Findings Saved", data.message);
                    refreshDetails();
                });
            });
        }

        var submitFindingsBtn = $("#dmSubmitFindingsBtn");
        if (submitFindingsBtn) {
            submitFindingsBtn.addEventListener("click", function () {
                showConfirm(
                    "Submit Findings for Approval",
                    "Submit the findings and recommended resolution to the Finance Manager for approval? You will not be able to approve them yourself.",
                    function () {
                        postAction("submit_findings", {
                            case_id: state.details.case.id,
                            findings: $("#dmFindings").value,
                            recommendation: $("#dmFindingsReason").value,
                            remarks: $("#dmFindingsRemarks") ? $("#dmFindingsRemarks").value : ""
                        }, function (data) {
                            showAlert("success", "Findings Submitted", data.message);
                            refreshDetails();
                        });
                    }
                );
            });
        }

        var requestExplanationBtn = $("#dmRequestExplanationBtn");
        if (requestExplanationBtn) {
            requestExplanationBtn.addEventListener("click", function () {
                var remarks = $("#dmFindingsRemarks") ? $("#dmFindingsRemarks").value : "";
                showConfirm(
                    "Request Driver Explanation",
                    "Move this case to Awaiting Driver Response and record what information is needed?",
                    function () {
                        postAction("status", {
                            case_id: state.details.case.id,
                            status: "Awaiting Driver Response",
                            reason: remarks || "Driver explanation requested."
                        }, function (data) {
                            showAlert("success", "Driver Response Requested", data.message);
                            refreshDetails();
                        });
                    }
                );
            });
        }

        var startReviewBtn = $("#dmStartReviewBtn");
        if (startReviewBtn) {
            startReviewBtn.addEventListener("click", function () {
                postAction("manager_review", { case_id: state.details.case.id }, function (data) {
                    showAlert("success", "Review Started", data.message);
                    refreshDetails();
                });
            });
        }

        document.querySelectorAll(".dm-decision").forEach(function (button) {
            button.addEventListener("click", function () {
                var decision = button.getAttribute("data-decision");
                var reasonInput = document.getElementById("dmDecisionText");
                var reason = reasonInput ? reasonInput.value : "";
                if (!reason) {
                    showAlert("error", "Decision Reason Required", "Document the reason for the " + decision + " decision.");
                    return;
                }
                showConfirm(
                    "Record Manager Decision",
                    "Record the '" + decision + "' decision for this case? The decision is audited and cannot be undone without reopening.",
                    function () {
                        postAction("manager_decision", {
                            case_id: state.details.case.id,
                            decision: decision,
                            reason: reason
                        }, function (data) {
                            showAlert("success", "Decision Recorded", data.message);
                            refreshDetails();
                        });
                    }
                );
            });
        });

        var evidenceBtn = $("#dmEvidenceBtn");
        if (evidenceBtn) {
            evidenceBtn.addEventListener("click", function () {
                var input = $("#dmEvidenceFile");
                if (!input.files.length) {
                    showAlert("warning", "No File Selected", "Choose a PDF, PNG, or JPG file up to 5MB.");
                    return;
                }
                var form = new FormData();
                form.set("case_id", state.details.case.id);
                form.set("evidence", input.files[0]);
                postAction("evidence", form, function (data) {
                    showAlert("success", "Evidence Uploaded", data.message);
                    refreshDetails();
                }, true);
            });
        }

        var proposeBtn = $("#dmProposeBtn");
        if (proposeBtn) {
            proposeBtn.addEventListener("click", function () {
                postAction("ledger_propose", {
                    case_id: state.details.case.id,
                    entry_type: $("#dmEntryType").value,
                    amount: $("#dmEntryAmount").value,
                    direction: $("#dmEntryDirection").value,
                    reference: $("#dmEntryReference").value,
                    authorization_reference: $("#dmEntryAuthorization").value,
                    notes: $("#dmEntryNotes").value,
                    reason: $("#dmEntryNotes").value
                }, function (data) {
                    showAlert("success", "Proposal Submitted", data.message);
                    refreshDetails();
                });
            });
        }

        document.querySelectorAll(".dm-decide").forEach(function (button) {
            button.addEventListener("click", function () {
                var approvalId = button.getAttribute("data-approval-id");
                var decision = button.getAttribute("data-decision");
                var reasonInput = document.getElementById("dmDecisionReason" + approvalId);
                var reason = reasonInput ? reasonInput.value : "";
                var verb = decision === "approved" ? "approve" : "reject";
                showConfirm(
                    decision === "approved" ? "Approve Resolution" : "Reject Resolution",
                    "Are you sure you want to " + verb + " this resolution? The decision is audited.",
                    function () {
                        postAction("approval_decide", { approval_id: approvalId, decision: decision, reason: reason }, function (data) {
                            showAlert("success", decision === "approved" ? "Approved" : "Rejected", data.message);
                            refreshDetails();
                        });
                    }
                );
            });
        });

        document.querySelectorAll(".dm-post-entry").forEach(function (button) {
            button.addEventListener("click", function () {
                var entryId = button.getAttribute("data-entry-id");
                showConfirm("Post Resolution", "Posting updates the outstanding balance. Continue?", function () {
                    postAction("ledger_post", { entry_id: entryId }, function (data) {
                        showAlert("success", "Posted", data.message);
                        refreshDetails();
                    });
                });
            });
        });

        document.querySelectorAll(".dm-void-entry").forEach(function (button) {
            button.addEventListener("click", function () {
                showVoidPrompt(button.getAttribute("data-entry-id"));
            });
        });
    }

    /* Void needs a reason; the prompt is rendered inside the custom alert. */
    function showVoidPrompt(entryId) {
        showConfirm(
            "Void Ledger Entry",
            '<label style="display:block;font-size:.75rem;font-weight:600;margin-bottom:4px;">Reason (required)</label>' +
            '<input type="text" id="dmVoidReason" class="dm-input" style="width:100%;" placeholder="Why this entry is voided">',
            function () {
                var input = document.getElementById("dmVoidReason");
                var reason = input ? input.value : "";
                if (!reason) {
                    showAlert("error", "Reason Required", "A reason is required to void a ledger entry.");
                    return;
                }
                postAction("ledger_void", { entry_id: entryId, reason: reason }, function (data) {
                    showAlert("success", "Voided", data.message);
                    refreshDetails();
                });
            },
            null,
            { html: true }
        );
    }

    function openCase(caseId) {
        state.details = null;
        $("#dmCaseBody").innerHTML = '<div class="dm-loading"><i class="bi bi-hourglass-split"></i> Loading case...</div>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById("dmCaseModal")).show();
        fetch(APP.endpoint + "?action=view&case_id=" + caseId, { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    showAlert("error", "Unable to Open Case", data.message || "Please try again.");
                    return;
                }
                renderDetails(data.details);
            })
            .catch(function () {
                showAlert("error", "Connection Error", "Unable to load the case.");
            });
    }

    /* ---------- events ---------- */

    document.addEventListener("click", function (event) {
        var openButton = event.target.closest(".dm-open-case");
        if (openButton) {
            openCase(Number(openButton.getAttribute("data-case-id")));
        }
        var row = event.target.closest("#dmCaseRows tr[data-case-id]");
        if (row && !event.target.closest("button")) {
            openCase(Number(row.getAttribute("data-case-id")));
        }
    });

    $("#dmApplyFilters").addEventListener("click", function () {
        state.page = 1;
        loadCases();
    });
    $("#dmResetFilters").addEventListener("click", function () {
        ["fStatus", "fType", "fDriver", "fFrom", "fTo", "fReviewer", "fAge", "fOverdue"].forEach(function (id) {
            document.getElementById(id).value = "";
        });
        state.page = 1;
        loadCases();
    });
    $("#fDriver").addEventListener("keydown", function (event) {
        if (event.key === "Enter") { state.page = 1; loadCases(); }
    });
    $("#fReviewer").addEventListener("keydown", function (event) {
        if (event.key === "Enter") { state.page = 1; loadCases(); }
    });

    $("#dmPrevPage").addEventListener("click", function () {
        if (state.page > 1) { state.page--; loadCases(); }
    });
    $("#dmNextPage").addEventListener("click", function () {
        if (state.page < state.pages) { state.page++; loadCases(); }
    });
    $("#dmPerPage").addEventListener("change", function () {
        state.perPage = Number(this.value);
        state.page = 1;
        loadCases();
    });

    function exportReport(format) {
        var params = filtersToQuery({});
        window.open(APP.endpoint + "?action=" + format + "&" + params.toString(), "_blank");
    }
    $("#dmExportPdf").addEventListener("click", function () { exportReport("export_pdf"); });
    $("#dmExportExcel").addEventListener("click", function () { exportReport("export_excel"); });

    /* ---------- authorization rules ---------- */

    var backfillBtn = $("#dmBackfillBtn");
    if (backfillBtn) {
        backfillBtn.addEventListener("click", function () {
            showConfirm(
                "Import Existing Discrepancies",
                "Create investigation cases for existing shortage, excess, and overdue remittances? " +
                "This is safe to run more than once and never modifies remittance amounts.",
                function () {
                    postAction("backfill", {}, function (data) {
                        showAlert("success", "Import Complete", data.message);
                        state.page = 1;
                        loadCases();
                    });
                }
            );
        });
    }

    var rulesBtn = $("#dmRulesBtn");
    if (rulesBtn) {
        rulesBtn.addEventListener("click", function () {
            bootstrap.Modal.getOrCreateInstance(document.getElementById("dmRulesModal")).show();
            loadRules();
        });
    }

    function loadRules() {
        fetch(APP.endpoint + "?action=rules_get", { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    showAlert("error", "Unable to Load Rules", data.message || "");
                    return;
                }
                $("#dmRulesJson").value = JSON.stringify(data.rules, null, 2);
            })
            .catch(function () {
                showAlert("error", "Connection Error", "Unable to load authorization rules.");
            });
    }

    $("#dmRulesSave").addEventListener("click", function () {
        postAction("rules_save", { rules: $("#dmRulesJson").value }, function (data) {
            showAlert("success", "Rules Saved", data.message);
        });
    });
    $("#dmRulesReload").addEventListener("click", loadRules);

    loadFinanceUsers().then(function () {
        loadCases();
    });
})();
</script>

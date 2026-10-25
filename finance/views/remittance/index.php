<?php header('Cache-Control: no-store, no-cache, must-revalidate'); header('Pragma: no-cache'); ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/public/login/custom-alert.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.rm-main {
    font-family: "Poppins", sans-serif;
    background: #eceed9;
    padding: 20px;
    min-height: 100vh;
}
.rm-main-full {
    font-family: "Poppins", sans-serif;
    background: #eceed9;
    padding: 24px 40px;
    min-height: 100vh;
    margin: 0 !important;
    width: 100% !important;
}
@media (min-width: 1600px) {
    .rm-main-full {
        padding: 32px 64px;
    }
}
.rm-main-full .rm-card {
    padding: 32px 36px;
}
.rm-main-full .rm-spacer {
    gap: 32px;
}

.rm-card {
    background: #fef8f3;
    border: 1px solid #dde0b6;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(41, 87, 56, 0.05);
}
.rm-card-sm {
    background: #fcfbf4;
    border: 1px solid #dde0b6;
    border-radius: 16px;
    padding: 16px;
}
.rm-card-compact {
    background: #fcfbf4;
    border: 1px solid #dde0b6;
    border-radius: 14px;
    padding: 14px 16px;
}
.rm-card-table {
    background: #fff;
    border: 1px solid #dde0b6;
    border-radius: 16px;
    overflow-x: auto;
    box-shadow: 0 1px 3px rgba(41, 87, 56, 0.05);
}
.rm-card-empty {
    background: #fcfbf4;
    border: 1.5px dashed #d4dbb6;
    border-radius: 16px;
    padding: 36px 24px;
    text-align: center;
}

.rm-title {
    font-weight: 800;
    font-size: 20px;
    color: #295738;
    margin: 0;
    letter-spacing: -0.02em;
}
.rm-subtitle {
    font-weight: 500;
    font-size: 12.5px;
    color: #8a9a8a;
    margin: 2px 0 0;
}
.rm-page-section {
    background: #fcfbf4;
    border: 1px solid #ece7d4;
    border-radius: 16px;
    padding: 14px 16px 4px;
    margin-bottom: 14px;
}
.rm-page-section .rm-summary-grid {
    margin-bottom: 12px;
}
.rm-section-heading {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    font-weight: 700;
    color: #295738;
    margin: 0 0 12px;
    letter-spacing: 0.2px;
}
.rm-section-heading i {
    color: #7fa06a;
    font-size: 13px;
}
.rm-section-hint {
    margin-left: auto;
    font-weight: 500;
    color: #9aa79a;
    font-size: 11px;
}
.rm-section-divider {
    height: 1px;
    background: linear-gradient(90deg, rgba(224, 220, 192, 0) 0%, #e0dcc0 18%, #e0dcc0 82%, rgba(224, 220, 192, 0) 100%);
    margin: 2px 0 18px;
}
.rm-panel-section {
    background: #fdfcf7;
    border: 1px solid #f0ebdc;
    border-radius: 16px;
    padding: 16px;
}
.rm-driver-section-head {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    font-weight: 700;
    color: #295738;
    margin: 0 0 12px;
    padding-bottom: 10px;
    border-bottom: 1px dashed #e7e2cf;
}
.rm-driver-section-head i {
    color: #7fa06a;
    font-size: 13px;
}
.rm-stat-label {
    color: #8a9a8a;
    font-weight: 600;
    font-size: 10px;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}
.rm-stat-label-light {
    color: #6f8a72;
    font-weight: 600;
    font-size: 10px;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}
.rm-stat-value {
    font-size: 22px;
    font-weight: 800;
    color: #295738;
    margin-top: 4px;
    line-height: 1.15;
}
.rm-stat-value-light {
    font-size: 22px;
    font-weight: 800;
    color: #295738;
    margin-top: 4px;
    line-height: 1.15;
}
.rm-amt {
    font-weight: 700;
    color: #295738;
}
.rm-section-label {
    font-weight: 700;
    color: #295738;
    margin-bottom: 10px;
    font-size: 12px;
}

.rm-summary-grid {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
}
.rm-summary-card {
    flex: 1;
    min-width: 0;
    background: #fff;
    border: 1px solid #e6e9d5;
    border-radius: 16px;
    padding: 16px 18px;
    box-shadow: 0 1px 3px rgba(41, 87, 56, 0.05);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 12px;
}
.rm-summary-card.rm-summary-dark {
    background: #eef4e7;
    border-color: #d7e3cd;
    box-shadow: none;
}
.rm-summary-top {
    display: flex;
    align-items: center;
    gap: 8px;
}
.rm-summary-top i {
    font-size: 16px;
    color: #6b8d6b;
    line-height: 1;
}
.rm-summary-dark .rm-summary-top i {
    color: #4a7350;
}
.rm-summary-value {
    font-size: 24px;
    font-weight: 800;
    color: #295738;
    line-height: 1.1;
    letter-spacing: -0.01em;
}
.rm-summary-dark .rm-summary-value {
    color: #295738;
}

.rm-spacer {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}
.rm-flex-grow {
    flex: 1;
    min-width: 0;
}
.rm-sidebar {
    width: 270px;
    flex-shrink: 0;
}
.rm-d-flex {
    display: flex;
}
.rm-gap-3 {
    gap: 14px;
}
.rm-flex-wrap {
    flex-wrap: wrap;
}
.rm-justify-between {
    display: flex;
    justify-content: space-between;
}
.rm-mt-3 {
    margin-top: 14px;
}
.rm-mb-4 {
    margin-bottom: 18px;
}
.rm-btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.rm-stat-grid {
    display: flex;
    gap: 14px;
    margin-bottom: 14px;
}
.rm-lower-grid {
    display: flex;
    gap: 16px;
    margin-top: 14px;
}
@media (max-width: 1100px) {
    .rm-lower-grid {
        flex-direction: column;
    }
}

.rm-stat {
    min-width: 0;
    flex: 1;
    background: #fff;
    border: 1px solid #e8e6d8;
    border-radius: 14px;
    padding: 14px 16px;
    box-shadow: 0 1px 3px rgba(41, 87, 56, 0.04);
}
.rm-stat-dark {
    min-width: 0;
    flex: 1;
    background: #eef4e7;
    border: 1px solid #d7e3cd;
    border-radius: 14px;
    padding: 14px 16px;
}

.rm-driver-count {
    color: #8a9a8a;
    display: block;
    margin-bottom: 8px;
    font-size: 11px;
}
.rm-driver-item {
    background: #fff;
    border: 1px solid #e8e6d8;
    border-radius: 12px;
    padding: 10px 12px;
    margin-bottom: 8px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    transition: 0.15s;
    box-shadow: 0 1px 2px rgba(41, 87, 56, 0.03);
}
.rm-driver-item:hover {
    border-color: #c4cf9a;
    background: #f7faf0;
}
.rm-driver-item.rm-driver-active {
    background: #eef4e0;
    border-color: #295738;
    box-shadow: 0 0 0 2px rgba(41, 87, 56, 0.08);
}
.rm-driver-name {
    font-weight: 600;
    color: #295738;
    font-size: 13px;
    line-height: 1.2;
}
.rm-driver-id {
    color: #8a9a8a;
    font-size: 11px;
}
.rm-driver-amount {
    color: #295738;
    font-weight: 600;
    font-size: 11.5px;
    display: block;
    margin-top: 2px;
}
.rm-driver-list-scroll {
    max-height: 430px;
    overflow-y: auto;
    padding-right: 4px;
    scrollbar-width: thin;
}
.rm-driver-list-scroll::-webkit-scrollbar {
    width: 6px;
}
.rm-driver-list-scroll::-webkit-scrollbar-thumb {
    background: #c4cf9a;
    border-radius: 999px;
}
.rm-driver-showmore {
    width: 100%;
    margin-top: 6px;
    background: #f0f4e2;
    border: 1px solid #cdd9b5;
    border-radius: 10px;
    padding: 8px;
    font-size: 11.5px;
    font-weight: 600;
    color: #295738;
    cursor: pointer;
    transition: 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.rm-driver-showmore:hover {
    background: #e6eccf;
}
.rm-driver-showmore[data-expanded="1"] {
    background: #eef4e0;
    border-color: #295738;
}

.rm-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}
.rm-badge-pending {
    background: #fdf0dd;
    color: #8a5a10;
    border: 1px solid #f5ddb4;
}
.rm-badge-completed {
    background: #e3f0e0;
    color: #1f6b3a;
    border: 1px solid #c3e0bd;
}
.rm-badge-shortage {
    background: #fde8e6;
    color: #b3261e;
    border: 1px solid #f5c9c4;
}
.rm-badge-noremit {
    background: #f5edc9;
    color: #7a6510;
    border: 1px solid #e6d89a;
}
.rm-badge-overage {
    background: #fdf0dd;
    color: #8a5a10;
    border: 1px solid #f5ddb4;
}

.rm-detail-header {
    background: #fcfbf4;
    border: 1px solid #dde0b6;
    border-radius: 14px;
    padding: 14px 18px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.rm-detail-name {
    font-size: 15px;
    font-weight: 700;
    color: #295738;
    line-height: 1.2;
    margin: 0;
}
.rm-detail-meta {
    display: flex;
    align-items: center;
    gap: 14px;
}
.rm-detail-summary {
    text-align: right;
}
.rm-detail-label {
    font-size: 11.5px;
    font-weight: 600;
    color: #8a9a8a;
}
.rm-detail-date {
    font-size: 11px;
    color: #8a9a8a;
    display: block;
}

.rm-table {
    width: 100%;
    font-size: 12.5px;
    margin: 0;
    border-collapse: collapse;
    min-width: 760px;
}
.rm-table thead {
    background: #f0f4e2;
}
.rm-table th {
    padding: 11px 14px;
    color: #295738;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    border: none;
    border-bottom: 1px solid #dde0b6;
    white-space: nowrap;
}
.rm-table td {
    padding: 11px 14px;
    vertical-align: middle;
    color: #4a5c4a;
    border-top: 1px solid #f0f0e4;
    background: #fff;
    white-space: nowrap;
}
.rm-table tbody tr:nth-child(even) td {
    background: #fcfbf4;
}
.rm-table tbody tr:hover td {
    background: #f5f7ea;
}
.rm-diff {
    font-weight: 700;
    font-size: 12px;
}
.rm-diff-green {
    color: #1f6b3a;
}
.rm-diff-orange {
    color: #b07a10;
}
.rm-diff-red {
    color: #b3261e;
}

.rm-recon-card {
    background: #fff;
    border: 1px solid #dde0b6;
    border-radius: 14px;
    padding: 14px 16px;
    flex: 1;
    min-width: 0;
    box-shadow: 0 1px 3px rgba(41, 87, 56, 0.04);
}
.rm-recon-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 12.5px;
    color: #4a5c4a;
    border-bottom: 1px dashed #eef0e0;
}
.rm-recon-row:last-of-type {
    border-bottom: none;
}
.rm-recon-row strong {
    color: #295738;
    font-weight: 700;
}
.rm-recon-status {
    margin-top: 8px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
    text-align: center;
}
.rm-recon-balanced {
    background: #e3f0e0;
    color: #1f6b3a;
}
.rm-recon-shortage {
    background: #fde8e6;
    color: #b3261e;
}
.rm-recon-overage {
    background: #fdf0dd;
    color: #8a5a10;
}
.rm-breakdown-card {
    background: #fff;
    border: 1px solid #dde0b6;
    border-radius: 14px;
    padding: 14px 16px;
    flex: 1;
    min-width: 0;
    box-shadow: 0 1px 3px rgba(41, 87, 56, 0.04);
}
.rm-breakdown-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    font-size: 12.5px;
    color: #4a5c4a;
}
.rm-breakdown-row strong {
    color: #295738;
    font-weight: 700;
}
.rm-breakdown-total {
    border-top: 1px dashed #eef0e0;
    margin-top: 6px;
    padding-top: 8px;
}
.rm-breakdown-total strong {
    font-weight: 800;
}
.rm-progress {
    height: 6px;
    background: #f0f4e2;
    border-radius: 999px;
    overflow: hidden;
    margin: 2px 0 8px;
}
.rm-progress-bar {
    height: 100%;
    border-radius: 999px;
}
.rm-progress-cash {
    background: #295738;
}
.rm-progress-gcash {
    background: #a8c38e;
}

.rm-btn-primary {
    background: #d2eace;
    color: #1f5a2a;
    border: 1.5px solid #a8d6a0;
    border-radius: 12px;
    padding: 11px 22px;
    font-size: 13px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    transition: 0.15s;
    letter-spacing: 0.3px;
    min-height: 44px;
    box-shadow: 0 1px 3px rgba(168, 214, 160, 0.3);
}
.rm-btn-primary:hover {
    background: #c3e2bc;
    border-color: #8fc784;
}
.rm-btn-primary:active {
    box-shadow: 0 1px 2px rgba(168, 214, 160, 0.2);
}
.rm-btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    box-shadow: none;
}
.rm-btn-warning {
    background: #fce8d6;
    color: #7a4a28;
    border: 1.5px solid #f0cdaa;
    border-radius: 12px;
    padding: 11px 22px;
    font-size: 13px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    transition: 0.15s;
    letter-spacing: 0.3px;
    min-height: 44px;
}
.rm-btn-warning:hover {
    background: #f7dcc0;
    border-color: #e6b98d;
}
.rm-btn-warning:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.rm-btn-outline {
    background: #fff;
    color: #295738;
    border: 1.5px solid #cdd9b5;
    border-radius: 12px;
    padding: 11px 22px;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    transition: 0.15s;
    min-height: 44px;
}
.rm-btn-outline:hover {
    background: #f2f6eb;
    border-color: #a8c38e;
}
.rm-btn-outline:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.rm-btn-gear {
    background: #fff;
    border: 1.5px solid #e8e6d8;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #295738;
    cursor: pointer;
    transition: 0.15s;
}
.rm-btn-gear:hover {
    background: #f5f9e8;
    border-color: #c4cf9a;
}
.rm-input {
    border-radius: 10px;
    border: 1.5px solid #dde0b6;
    background: #fff;
    padding: 8px 12px;
    font-size: 12px;
    color: #4a5c4a;
}
.rm-input:focus {
    outline: none;
    border-color: #6b8d6b;
    box-shadow: 0 0 0 3px rgba(107, 141, 107, 0.12);
}


.rm-summary-grid {
    flex-wrap: wrap;
}
.rm-stat-grid {
    flex-wrap: wrap;
}

@media (max-width: 992px) {
    .rm-summary-card {
        flex: 1 1 calc(50% - 8px);
    }
    .rm-stat {
        flex: 1 1 calc(50% - 7px);
    }
    .rm-spacer {
        flex-direction: column;
    }
    .rm-sidebar {
        width: 100%;
    }
    .rm-flex-grow {
        width: 100%;
        min-width: 0;
    }
}

@media (max-width: 576px) {
    .rm-summary-card {
        flex: 1 1 100%;
    }
    .rm-page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    .rm-page-actions {
        width: 100%;
    }
    .rm-page-actions .rm-btn-outline,
    .rm-page-actions .rm-btn-gear {
        flex: 1 1 140px;
    }
}

.rm-audit {
    border-top: 1px solid #eef0e0;
    margin-top: 18px;
    padding-top: 14px;
}
.rm-audit-title {
    font-weight: 700;
    color: #295738;
    font-size: 13px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.rm-audit-body {
    font-size: 12.5px;
}
.rm-timeline {
    position: relative;
    margin: 0;
    padding: 0 0 0 18px;
    list-style: none;
}
.rm-timeline::before {
    content: "";
    position: absolute;
    left: 5px;
    top: 4px;
    bottom: 4px;
    width: 2px;
    background: #e0e6c8;
}
.rm-tl-item {
    position: relative;
    padding: 0 0 16px 6px;
}
.rm-tl-item::before {
    content: "";
    position: absolute;
    left: -16px;
    top: 4px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #295738;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px #cdd9b5;
}
.rm-tl-item.rm-tl-failed::before {
    background: #b3261e;
}
.rm-tl-action {
    font-weight: 700;
    color: #295738;
    font-size: 12.5px;
}
.rm-tl-item.rm-tl-failed .rm-tl-action {
    color: #b3261e;
}
.rm-tl-meta {
    color: #8a9a8a;
    font-size: 11px;
    margin-top: 2px;
}
.rm-tl-user {
    font-weight: 600;
    color: #4a5c4a;
}
.rm-tl-desc {
    font-size: 11.5px;
    color: #4a5c4a;
    margin-top: 3px;
}
.rm-tl-change {
    margin-top: 6px;
    background: #fcfbf4;
    border: 1px solid #eef0e0;
    border-radius: 10px;
    padding: 8px 10px;
    font-size: 11.5px;
    color: #4a5c4a;
}
.rm-tl-change div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 2px 0;
}
.rm-tl-change .old {
    color: #b3261e;
}
.rm-tl-change .new {
    color: #1f6b3a;
    font-weight: 600;
    text-align: right;
}
.rm-tl-empty {
    color: #8a9a8a;
    font-size: 12px;
}

@media (max-width: 576px) {
    .rm-tl-change div {
        flex-direction: column;
        gap: 0;
    }
    .rm-tl-change .new {
        text-align: left;
    }
}
</style>
<?php
$isFullView = isset($_GET['full']) && $_GET['full'] === '1';
$fullViewParams = $_GET;
$fullViewParams['full'] = $isFullView ? '0' : '1';
$fullViewQuery = http_build_query($fullViewParams);

$financeCodeMeta = $financeCodeMeta ?? [];

if (!empty($financeCodeMeta) && !empty($financeCodeMeta['has_code']) && empty($financeCodeMeta['expired'])) {
    if (($financeCodeMeta['days_left'] ?? 99) <= 7) {
        $fcChipClass = 'warn';
        $fcChipText = 'Expiring soon';
        $fcStatusClass = ' warn';
        $fcStatusHtml = 'Code status: <strong>Expiring soon</strong>. Active until '
            . htmlspecialchars((string) ($financeCodeMeta['expires_at_label'] ?? ''), ENT_QUOTES)
            . ' (' . (int) ($financeCodeMeta['days_left'] ?? 0) . ' day(s) left).';
    } else {
        $fcChipClass = 'ok';
        $fcChipText = 'Active';
        $fcStatusClass = '';
        $fcStatusHtml = 'Code status: <strong>Active</strong>. Active until '
            . htmlspecialchars((string) ($financeCodeMeta['expires_at_label'] ?? ''), ENT_QUOTES)
            . ' (' . (int) ($financeCodeMeta['days_left'] ?? 0) . ' day(s) left).';
    }
} elseif (!empty($financeCodeMeta) && !empty($financeCodeMeta['has_code']) && !empty($financeCodeMeta['expired'])) {
    $fcChipClass = 'danger';
    $fcChipText = 'Expired';
    $fcStatusClass = ' danger';
    $fcStatusHtml = 'Your Finance Authorization Code expired'
        . (!empty($financeCodeMeta['expires_at_label']) ? ' on <strong>' . htmlspecialchars((string) $financeCodeMeta['expires_at_label'], ENT_QUOTES) . '</strong>' : '')
        . '. Remittance submissions are blocked until you renew it.';
} elseif (!empty($financeCodeMeta) && !empty($financeCodeMeta['legacy_pending'])) {
    $fcChipClass = 'warn';
    $fcChipText = 'Renew code';
    $fcStatusClass = ' warn';
    $fcStatusHtml = 'A legacy Finance Authorization Code is still active. Renew it with a new 6-digit code so it expires every 30 days.';
} else {
    $fcChipClass = 'danger';
    $fcChipText = 'Not set';
    $fcStatusClass = ' danger';
    $fcStatusHtml = 'No Finance Authorization Code is set. Remittance submissions are blocked until you create one.';
}
?>

<?php if (!$isFullView) require_once __DIR__ . '/../layout/navbarfinance.php'; ?>

<div class="<?= $isFullView ? 'rm-main-full' : 'main-content rm-main' ?>">

<?php if (!$isFullView) require_once __DIR__ . '/../layout/_headernav.php'; ?>

<div class="rm-card">


<div class="rm-page-header d-flex justify-content-between align-items-center mb-3">

    <div>
        <h1 class="rm-title" style="font-size:1.35rem;">
            Driver Remittance
        </h1>
        <p class="rm-subtitle">
            Review and verify today's driver remittance records.
        </p>
        <?php
        if (!class_exists(\Finance\Services\DateFilterService::class)) {
            require_once __DIR__ . '/../../services/DateFilterService.php';
        }
        $financePeriodSummary = \Finance\Services\DateFilterService::todaySummary();
        require __DIR__ . '/../layout/_period_summary.php';
        ?>
    </div>

    <div class="rm-page-actions d-flex gap-2">
        <a href="?<?= $fullViewQuery ?>" class="rm-btn-outline" style="text-decoration:none;">
            <i class="bi bi-<?= $isFullView ? 'fullscreen-exit' : 'arrows-fullscreen' ?> me-1"></i> <?= $isFullView ? 'Exit Full View' : 'Full View' ?>
        </a>
        <button type="button" class="rm-btn-gear" onclick="openFinanceCodeModal()">
            <i class="bi bi-gear me-1"></i> Finance Code
            <span id="rmFcChip" class="rm-fc-chip <?= htmlspecialchars($fcChipClass ?? '') ?>"><?= htmlspecialchars($fcChipText ?? '') ?></span>
        </button>
    </div>

</div>


<div class="modal fade" id="financeCodeModal" tabindex="-1">
<div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
<div class="modal-content rm-fcm">

    <div class="rm-fcm-head">
        <div class="rm-fcm-head-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <div>
            <h5 class="rm-fcm-title">Finance Authorization Code</h5>
            <p class="rm-fcm-sub">Secure authorization for remittance approvals</p>
        </div>
        <button type="button" class="rm-fcm-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
    </div>

    <div class="rm-fcm-body">

        <div class="rm-fc-info">
            Your Finance Authorization Code is used to verify and authorize finance transactions, including driver remittance submissions. For security, it expires every 30 days and must be renewed. Never share your code with anyone.
        </div>

        <div class="rm-fc-status<?= $fcStatusClass ?? '' ?>" id="rmFcStatus">
            <?= $fcStatusHtml ?? 'Checking code status...' ?>
        </div>

        <div id="rmFcStepIdentity">
            <div class="rm-fcm-step-head">
                <span class="rm-fcm-step-num">1</span>
                <div>
                    <div class="rm-fcm-step-title">Confirm it&rsquo;s you</div>
                    <div class="rm-fcm-step-sub">Choose one secure method to continue.</div>
                </div>
            </div>

            <div class="rm-fcm-field">
                <span class="rm-fcm-field-icon"><i class="bi bi-lock-fill"></i></span>
                <input type="password" id="rmFcPassword" class="rm-fcm-input" placeholder="Current account password" autocomplete="current-password">
            </div>
            <button type="button" id="rmFcVerifyPassword" class="rm-fcm-primary"><i class="bi bi-person-check-fill me-1"></i> Verify with Password</button>

            <div class="rm-fc-or"><span>or use an email code</span></div>

            <div class="rm-fcm-field">
                <span class="rm-fcm-field-icon"><i class="bi bi-envelope-fill"></i></span>
                <input type="text" id="rmFcOtp" class="rm-fcm-input" placeholder="6-digit email code" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
            </div>
            <div class="rm-fcm-row">
                <button type="button" id="rmFcSendOtp" class="rm-fcm-secondary"><i class="bi bi-send me-1"></i> Send code</button>
                <button type="button" id="rmFcVerifyOtp" class="rm-fcm-primary"><i class="bi bi-shield-check me-1"></i> Verify code</button>
            </div>
        </div>

        <div id="rmFcStepCode" style="display:none;">
            <div class="rm-fcm-step-head">
                <span class="rm-fcm-step-num">2</span>
                <div>
                    <div class="rm-fcm-step-title">Set your new 6-digit code</div>
                    <div class="rm-fcm-step-sub">It expires 30 days after saving, and you will be reminded before it expires.</div>
                </div>
            </div>

            <div class="rm-fcm-field">
                <span class="rm-fcm-field-icon"><i class="bi bi-key-fill"></i></span>
                <input type="text" id="financeCodeInput" class="rm-fcm-input rm-fcm-code" placeholder="Enter 6-digit code" inputmode="numeric" maxlength="6" autocomplete="off">
            </div>
            <div class="rm-fcm-field">
                <span class="rm-fcm-field-icon"><i class="bi bi-key-fill"></i></span>
                <input type="text" id="rmFcConfirmCode" class="rm-fcm-input rm-fcm-code" placeholder="Re-enter 6-digit code" inputmode="numeric" maxlength="6" autocomplete="off">
            </div>
        </div>

    </div>

    <div class="rm-fcm-foot">
        <button type="button" class="rm-fcm-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="saveFinanceCodeBtn" class="rm-fcm-primary" style="display:none;"><i class="bi bi-check2-circle me-1"></i> Save Code</button>
    </div>

</div>
</div>
</div>

<?php

$drivers = $drivers ?? [];
$trips = $data ?? [];

/**
 * =========================
 * GROUP TRIPS
 * =========================
 */
$grouped = [];

foreach ($trips as $row) {

    $grouped[
        ($row['panel_key'] ?? $row['driver_id'])
    ][] = $row;
}

/**
 * =========================
 * GLOBAL TOTALS
 * =========================
 */
$grandTotal = 0;

$grandCash = 0;

$grandGcash = 0;

foreach ($trips as $t) {

    $grandTotal +=
        (float)(
            $t['total_collection']
            ?? 0
        );

    $grandGcash +=
        (float)(
            $t['gcash_total']
            ?? 0
        );

    $grandCash +=
        (float)(
            $t['cash_total']
            ?? 0
        );
}

?>


<div class="rm-page-section">

    <div class="rm-section-heading">
        <i class="bi bi-graph-up-arrow"></i> Overall Daily Collection
        <span class="rm-section-hint">All drivers · <?= htmlspecialchars(date('F j, Y', strtotime($phDate)), ENT_QUOTES) ?></span>
    </div>

    <div class="rm-summary-grid">

    <div class="rm-summary-card">
        <div class="rm-summary-top">
            <i class="bi bi-cash-stack"></i>
            <small class="rm-stat-label">Total Collected Today</small>
        </div>
        <div class="rm-summary-value">₱<?= number_format($grandTotal,2) ?></div>
    </div>

    <div class="rm-summary-card">
        <div class="rm-summary-top">
            <i class="bi bi-cash"></i>
            <small class="rm-stat-label">Cash Payments</small>
        </div>
        <div class="rm-summary-value">₱<?= number_format($grandCash,2) ?></div>
    </div>

    <div class="rm-summary-card">
        <div class="rm-summary-top">
            <i class="bi bi-qr-code"></i>
            <small class="rm-stat-label">QR Ph Payments</small>
        </div>
        <div class="rm-summary-value">₱<?= number_format($grandGcash,2) ?></div>
    </div>

    <div class="rm-summary-card rm-summary-dark">
        <div class="rm-summary-top">
            <i class="bi bi-check2-circle"></i>
            <small class="rm-stat-label-light">Expected Cash to Remit</small>
        </div>
        <div class="rm-summary-value">₱<?= number_format($grandCash,2) ?></div>
    </div>

    </div>

</div>

<div class="rm-section-divider"></div>

<div class="rm-spacer">


<div class="rm-sidebar">

    <div class="rm-card-sm">

        <div class="rm-section-label">Drivers</div>

        <div class="rm-driver-search">
            <i class="bi bi-search"></i>
            <input type="text" id="rmDriverSearch" placeholder="Search driver name or ID..." autocomplete="off">
        </div>

        <select id="rmStatusFilter" class="rm-status-filter" aria-label="Filter drivers by status">
            <option value="All" selected>All statuses</option>
            <option value="Pending">Pending</option>
            <option value="Overdue">Overdue</option>
            <option value="Completed">Completed</option>
        </select>

        <?php 
        $visibleDrivers = $drivers;
        usort($visibleDrivers, function ($a, $b) {
            $sa = !empty($a['submitted']) ? 1 : 0;
            $sb = !empty($b['submitted']) ? 1 : 0;
            return $sa <=> $sb;
        });
        $driverTotalCount = count($visibleDrivers);
        ?>
        <small class="rm-driver-count" id="rmDriverCount">
            <?= $driverTotalCount ?> driver(s)
        </small>

        <div class="rm-driver-list" id="rmDriverList">

        <?php foreach ($visibleDrivers as $driverIdx => $d):

        $isOverdueItem = !empty($d['overdue']);
        $submittedToday = !empty($d['submitted']);
        $panelKey = (string) ($d['panel_key'] ?? $d['driver_id']);
        $itemBusinessDate = (string) ($d['business_date'] ?? date('Y-m-d'));

        if ($isOverdueItem) {
            $status = 'Overdue';
            $badgeClass = 'rm-badge-overdue';
        } elseif ($submittedToday) {
            $status = 'Completed';
            $badgeClass = 'rm-badge-completed';
        } else {
            $status = 'Pending';
            $badgeClass = 'rm-badge-pending';
        }

        ?>

        <div
            class="driver-item rm-driver-item"

            data-driver="<?= htmlspecialchars($panelKey) ?>"

            data-status="<?= $status ?>"

            data-date="<?= htmlspecialchars($itemBusinessDate) ?>"

            data-business-date="<?= htmlspecialchars($itemBusinessDate) ?>"
        >

            <div style="min-width:0;">

                <div class="rm-driver-name">
                    <?= htmlspecialchars($d['driver_name']) ?>
                </div>

                <small class="rm-driver-id">
                    <?= htmlspecialchars($d['driver_id']) ?>
                </small>

                <small class="rm-driver-amount">
                    Collection: ₱<?= number_format($d['total_collection'] ?? 0, 2) ?>
                </small>

                <?php if ($isOverdueItem): ?>
                <small class="rm-driver-amount rm-overdue-note">
                    <i class="bi bi-clock-history me-1"></i><?= htmlspecialchars($itemBusinessDate) ?> · Expected ₱<?= number_format((float) ($d['expected_cash'] ?? 0), 2) ?>
                </small>
                <?php endif; ?>

            </div>

            <span
                class="rm-badge <?= $badgeClass ?>"
                <?php if ($submittedToday && !empty($d['submitted_by'])): ?>
                title="Submitted by: <?= htmlspecialchars($d['submitted_by']) ?>"
                <?php endif; ?>
            >
                <?= $status ?>
            </span>

        </div>

        <?php endforeach; ?>

        </div>

        <?php if ($driverTotalCount > 7): ?>
        <button
            type="button"
            class="rm-driver-showmore"
            id="rmShowMoreBtn"
            data-expanded="0"
        >
            <i class="bi bi-chevron-down"></i>
            <span class="rm-sm-label">Show More</span>
            <span class="rm-sm-count">(<?= $driverTotalCount - 7 ?> more)</span>
        </button>
        <?php endif; ?>

    </div>

</div>


<div class="rm-flex-grow<?= empty($visibleDrivers) ? '' : ' rm-panel-section' ?>">

<?php if (!empty($visibleDrivers)): ?>
<div class="rm-driver-section-head">
    <i class="bi bi-person-badge"></i> Selected Driver's Remittance
</div>
<?php endif; ?>

<?php foreach ($visibleDrivers as $d):

$driverId =
    $d['driver_id'];

$driverName =
    $d['driver_name'];

$panelKey =
    (string) ($d['panel_key'] ?? $driverId);

$businessDate =
    (string) ($d['business_date'] ?? date('Y-m-d'));

$isOverdue =
    !empty($d['overdue']);

$submittedToday =
    !empty($d['submitted']);

$driverTrips =
    $grouped[$panelKey]
    ?? [];

$totalCash = 0;

$totalGcash = 0;

$totalCollection = 0;

$totalExpected = 0;

foreach ($driverTrips as $t) {

    $expected =
        (float)(
            $t['expected_cash']
            ?? $t['cash_total']
            ?? 0
        );

    $totalCollection +=
        (float)(
            $t['total_collection']
            ?? 0
        );

    $totalGcash +=
        (float)(
            $t['gcash_total']
            ?? 0
        );

    $totalCash +=
        (float)(
            $t['cash_total']
            ?? 0
        );

    $totalExpected +=
        $expected;
}

if ($submittedToday) {

    $totalActual = (float) ($d['actual_cash'] ?? $totalExpected);

    $reconDiff = round($totalActual - $totalExpected, 2);

    if ($reconDiff == 0) {
        $reconClass = 'rm-recon-balanced';
        $reconStatusText = 'Balanced';
        $reconDiffText = '₱0.00';
        $reconDiffClass = 'rm-diff-green';
    } elseif ($reconDiff < 0) {
        $reconClass = 'rm-recon-shortage';
        $reconStatusText = 'Shortage: ₱' . number_format(abs($reconDiff), 2);
        $reconDiffText = '-₱' . number_format(abs($reconDiff), 2);
        $reconDiffClass = 'rm-diff-red';
    } else {
        $reconClass = 'rm-recon-overage';
        $reconStatusText = 'Excess: ₱' . number_format($reconDiff, 2);
        $reconDiffText = '+₱' . number_format($reconDiff, 2);
        $reconDiffClass = 'rm-diff-orange';
    }

} else {

    $totalActual = 0.0;

    $reconClass = 'rm-recon-pending';
    $reconStatusText = $isOverdue ? 'Overdue — awaiting late remittance' : 'Awaiting remittance';
    $reconDiffText = '—';
    $reconDiffClass = '';
}

$breakTotal = $totalCollection;

$cashPct = $breakTotal > 0 ? round(($totalCash / $breakTotal) * 100, 1) : 0;

$gcashPct = $breakTotal > 0 ? round(($totalGcash / $breakTotal) * 100, 1) : 0;

?>

<div
    class="driver-panel"

    data-driver="<?= htmlspecialchars($panelKey) ?>"

    data-driver-id="<?= htmlspecialchars($driverId) ?>"

    data-business-date="<?= htmlspecialchars($businessDate) ?>"

    data-overdue="<?= $isOverdue ? '1' : '0' ?>"

    data-trip-count="<?= count($driverTrips) ?>"

    data-driver-name="<?= htmlspecialchars($driverName) ?>"

    data-remit-ref="<?= htmlspecialchars($d['remittance_ref'] ?? '') ?>"

    data-total-expected="<?= number_format($totalExpected, 2, '.', '') ?>"

    style="display:none;"
>


<div class="rm-detail-header">

    <div>
        <div class="rm-detail-name">
            <?= htmlspecialchars($driverName) ?>
        </div>
        <small class="rm-driver-id">
            Driver ID: <?= htmlspecialchars($driverId) ?>
        </small>
    </div>

    <div class="rm-detail-meta">

        <div class="rm-detail-summary">
            <div class="rm-detail-label">Driver's Daily Summary</div>
            <small class="rm-detail-date">
                <?= $businessDate === $phDate
                    ? 'As of today · ' . htmlspecialchars(date('F j, Y', strtotime($businessDate)))
                    : 'As of ' . htmlspecialchars(date('F j, Y', strtotime($businessDate))) . ($isOverdue ? ' · Late processing' : '') ?>
            </small>
        </div>

        <?php if ($isOverdue): ?>
        <span class="rm-badge rm-badge-overdue">Overdue</span>
        <?php elseif ($submittedToday): ?>
        <span
            class="rm-badge rm-badge-completed"
            <?php if (!empty($d['submitted_by'])): ?>
            title="Submitted by: <?= htmlspecialchars($d['submitted_by']) ?>"
            <?php endif; ?>
        >Completed</span>
        <?php else: ?>
        <span class="rm-badge rm-badge-pending">Pending</span>
        <?php endif; ?>

    </div>

</div>


<div class="rm-stat-grid">

    <div class="rm-stat">
        <small class="rm-stat-label">Driver's Total Collection</small>
        <div class="rm-stat-value">₱<?= number_format($totalCollection,2) ?></div>
    </div>

    <div class="rm-stat">
        <small class="rm-stat-label">Cash Collected</small>
        <div class="rm-stat-value">₱<?= number_format($totalCash,2) ?></div>
    </div>

    <div class="rm-stat">
        <small class="rm-stat-label">QR Ph Collected</small>
        <div class="rm-stat-value">₱<?= number_format($totalGcash,2) ?></div>
    </div>

    <div class="rm-stat rm-stat-dark">
        <small class="rm-stat-label-light">Expected Cash to Submit</small>
        <div class="rm-stat-value-light">₱<?= number_format($totalExpected,2) ?></div>
    </div>

</div>

<?php if(empty($driverTrips)): ?>

<div class="rm-card-empty">

    <h6 style="color:#295738;font-weight:700;">
        No Trips Available
    </h6>

    <small style="color:#6b7280;">
        <?= $isOverdue ? 'No trips could be loaded for this business date.' : 'No trips recorded for today.' ?>
    </small>

</div>

<?php else: ?>


<details class="rm-trip-details" <?= $submittedToday ? '' : 'open' ?>>

<summary class="rm-trip-details-summary">
    <span class="rm-trip-details-title"><i class="bi bi-receipt"></i> Trip Details</span>
    <span class="rm-trip-details-count"><?= count($driverTrips) ?> trip<?= count($driverTrips) === 1 ? '' : 's' ?></span>
    <i class="bi bi-chevron-down rm-trip-chev"></i>
</summary>

<div class="rm-trip-table-wrap">

<table class="table mb-0 rm-table">

<thead>

<tr>

<th>Trip #</th>
<th>Bus</th>
<th>Collection</th>
<th>Cash</th>
<th>QR Ph</th>
<th>Expected Cash</th>
<th>Status</th>

</tr>

</thead>

<tbody>

<?php foreach ($driverTrips as $row):

$expected =
    (float)(
        $row['expected_cash']
        ?? $row['cash_total']
        ?? 0
    );

$submitted =
    !empty($row['submitted']);

?>

<tr>

<td><?= htmlspecialchars($row['trip_no']) ?></td>

<td><?= htmlspecialchars($row['bus_no']) ?></td>

<td class="rm-amt">
    ₱<?= number_format($row['total_collection'],2) ?>
</td>

<td class="rm-amt">
    ₱<?= number_format($row['cash_total'],2) ?>
</td>

<td class="rm-amt">
    ₱<?= number_format($row['gcash_total'],2) ?>
</td>

<td class="rm-amt">
    ₱<?= number_format($expected,2) ?>
</td>

<td class="status-cell">
    <?php if ($submitted): ?>
    <span
        class="rm-badge rm-badge-completed"
        <?php if (!empty($row['submitted_by'])): ?>
        title="Submitted by: <?= htmlspecialchars($row['submitted_by']) ?>"
        <?php endif; ?>
    >Remitted</span>
    <?php else: ?>
    <span class="rm-badge rm-badge-pending">Pending</span>
    <?php endif; ?>
</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>

</details>


<div class="rm-lower-grid">

    <div class="rm-recon-card">
        <div class="rm-section-label">Remittance Reconciliation</div>

        <div class="rm-recon-row">
            <span>Expected Cash to Submit</span>
            <strong>₱<?= number_format($totalExpected,2) ?></strong>
        </div>

        <div class="rm-recon-row">
            <span>Actual Cash Remitted</span>
            <input
                type="number"
                step="0.01"
                min="0"
                class="actual-cash-input form-control form-control-sm rm-actual-input"
                data-expected="<?= number_format($totalExpected,2,'.','') ?>"
                value="<?= $submittedToday ? number_format($totalActual,2,'.','') : '' ?>"
                placeholder="0.00"
                aria-label="Actual cash remitted"
                <?= $submittedToday ? 'disabled' : '' ?>
            >
        </div>

        <div class="rm-recon-row">
            <span>Amount Difference</span>
            <strong class="rm-recon-diff <?= $reconDiffClass ?>"><?= $reconDiffText ?></strong>
        </div>

        <div class="rm-recon-status <?= $reconClass ?>"><?= $reconStatusText ?></div>
    </div>

    <div class="rm-breakdown-card">
        <div class="rm-section-label">Payment Breakdown</div>

        <div class="rm-breakdown-row">
            <span>Cash Collected</span>
            <strong>₱<?= number_format($totalCash,2) ?></strong>
        </div>

        <div class="rm-progress">
            <div class="rm-progress-bar rm-progress-cash" style="width:<?= $cashPct ?>%"></div>
        </div>

        <div class="rm-breakdown-row">
            <span>QR Ph Collected</span>
            <strong>₱<?= number_format($totalGcash,2) ?></strong>
        </div>

        <div class="rm-progress">
            <div class="rm-progress-bar rm-progress-gcash" style="width:<?= $gcashPct ?>%"></div>
        </div>

        <div class="rm-breakdown-row rm-breakdown-total">
            <span>Total</span>
            <strong>₱<?= number_format($breakTotal,2) ?></strong>
        </div>
    </div>

</div>


<div class="rm-btn-group rm-mt-3">

<button
    class="submit-remittance rm-btn-primary"
    <?= $submittedToday ? 'disabled' : '' ?>
>
    <i class="bi bi-check-lg"></i> <?= $isOverdue ? 'Process Late Remittance' : 'Submit Remittance' ?>
</button>

<button
    class="download-report rm-btn-outline"
>
    <i class="bi bi-file-earmark-pdf"></i> Download Report (PDF)
</button>

</div>

<?php endif; ?>


<div class="rm-audit" id="rmAudit-<?= htmlspecialchars($driverId) ?>">
    <div class="rm-audit-title">
        <i class="bi bi-activity me-1"></i> Activity
    </div>
    <div class="rm-audit-body">
        <small style="color:#8a9a8a;">Select this driver to view audit activity.</small>
    </div>
</div>

</div>

<?php endforeach; ?>

</div>

</div>

</div>

</div>


<div id="codeModal" style="display:none; position:fixed; inset:0; background:rgba(20,35,22,.42); z-index:9999; align-items:center; justify-content:center; padding:20px;">

<div class="rm-fcm" style="width:100%; max-width:440px;">

    <div class="rm-fcm-head">
        <div class="rm-fcm-head-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <div>
            <h5 class="rm-fcm-title">Authorize Remittance</h5>
            <p class="rm-fcm-sub">Enter your Finance Authorization Code to confirm this submission.</p>
        </div>
        <button type="button" class="rm-fcm-close" onclick="closeCodeModal()" aria-label="Close">&times;</button>
    </div>

    <div class="rm-fcm-body">

        <div id="codeModalSummary" style="margin-bottom:14px;"></div>

        <div class="rm-fcm-step-head">
            <span class="rm-fcm-step-num">1</span>
            <div>
                <div class="rm-fcm-step-title">Enter your 6-digit code</div>
                <div class="rm-fcm-step-sub">The code is verified on the server before anything is saved.</div>
            </div>
        </div>

        <div class="rm-fcm-field">
            <span class="rm-fcm-field-icon"><i class="bi bi-key-fill"></i></span>
            <input type="password" id="financeCode" class="rm-fcm-input rm-fcm-code" placeholder="6-digit code" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
        </div>

    </div>

    <div class="rm-fcm-foot">
        <button type="button" class="rm-fcm-secondary" onclick="closeCodeModal()">Cancel</button>
        <button type="button" id="confirmCode" class="rm-fcm-primary"><i class="bi bi-check2-circle me-1"></i> Confirm Remittance</button>
    </div>

</div>

</div>


<style>
.rm-badge-overdue { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }
.rm-overdue-note { color:#b45309; }
.rm-trip-details { background:#fff; border:1px solid #dde0b6; border-radius:14px; overflow:hidden; margin-bottom:16px; }
.rm-trip-details-summary { cursor:pointer; display:flex; align-items:center; gap:8px; padding:12px 14px; background:#fcfbf4; list-style:none; }
.rm-trip-details-summary::-webkit-details-marker { display:none; }
.rm-trip-details[open] .rm-trip-details-summary { border-bottom:1px solid #eef0e0; }
.rm-trip-details-title { display:flex; align-items:center; gap:7px; font-size:12px; font-weight:700; color:#295738; }
.rm-trip-details-title i { color:#7fa06a; font-size:13px; }
.rm-trip-details-count { background:#f2f7ee; border:1px solid #d7e3cd; color:#4b6b50; border-radius:999px; padding:2px 10px; font-size:10.5px; font-weight:600; }
.rm-trip-chev { margin-left:auto; color:#8a9a8a; font-size:12px; transition:transform .18s ease; }
.rm-trip-details[open] .rm-trip-chev { transform:rotate(180deg); }
.rm-trip-table-wrap { overflow-x:auto; }
.rm-actual-input { max-width:160px; text-align:right; border-radius:10px; border:2px solid #cdd9b5; }
.rm-recon-pending { background:#f3f4f6; color:#4b5563; }
.rm-rv { text-align:left; }
.rm-rv-driver { display:flex; align-items:baseline; justify-content:space-between; gap:8px; border-bottom:1px dashed #e5e7eb; padding-bottom:10px; margin-bottom:10px; }
.rm-rv-name { font-weight:700; color:#295738; font-size:0.95rem; }
.rm-rv-id { font-size:0.72rem; color:#6b7280; font-weight:500; white-space:nowrap; }
.rm-rv-meta { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
.rm-rv-chip { display:inline-flex; align-items:center; gap:5px; background:#f2f7ee; border:1px solid #d7e3cd; color:#295738; border-radius:999px; padding:4px 10px; font-size:0.72rem; font-weight:600; }
.rm-rv-rows { background:#fcfbf4; border:1px solid #e8e4c9; border-radius:12px; padding:10px 12px; }
.rm-rv-row { display:flex; justify-content:space-between; align-items:center; gap:12px; font-size:0.85rem; color:#4b5563; padding:4px 0; }
.rm-rv-row strong { color:#1f2937; font-weight:600; }
.rm-rv-total { border-top:1px solid #e8e4c9; margin-top:6px; padding-top:8px; font-weight:600; }
.rm-rv-green { color:#15803d !important; }
.rm-rv-red { color:#dc2626 !important; }
.rm-rv-orange { color:#b45309 !important; }
.rm-rv-note { margin-top:10px; font-size:0.72rem; color:#8a9a8a; text-align:left; }
.rm-rv-status { margin-top:10px; text-align:center; border-radius:10px; padding:8px 12px; font-weight:700; font-size:0.8rem; }
.rm-rv-status-green { background:#e3f0e0; color:#1f6b3a; }
.rm-rv-status-red { background:#fde8e6; color:#b3261e; }
.rm-rv-status-orange { background:#fef3c7; color:#92400e; }
.rm-driver-search { display:flex; align-items:center; gap:8px; background:#fff; border:1.5px solid #cdd9b5; border-radius:10px; padding:8px 10px; margin-bottom:10px; }
.rm-driver-search i { color:#6b8d6b; }
.rm-driver-search input { border:none; outline:none; width:100%; font-size:13px; font-family:"Poppins",sans-serif; color:#295738; background:transparent; }
.rm-status-filter { width:100%; border:1.5px solid #cdd9b5; border-radius:10px; padding:8px 10px; font-size:13px; font-weight:600; color:#295738; background:#fff; margin-bottom:12px; font-family:"Poppins",sans-serif; cursor:pointer; }
.rm-fc-info { background:#f7f9f2; border:1px solid #e2e9d6; border-left:4px solid #a8c39a; border-radius:12px; padding:10px 12px; font-size:12px; color:#3f5a44; line-height:1.7; margin-bottom:10px; }
.rm-fc-status { background:#fff; border:1px dashed #cdd9b5; border-radius:10px; padding:8px 10px; font-size:12px; color:#4b5563; margin-bottom:14px; line-height:1.6; }
.rm-fc-status.warn { background:#fffbeb; border-color:#fcd34d; color:#92400e; }
.rm-fc-status.danger { background:#fef2f2; border-color:#fca5a5; color:#b91c1c; }
.rm-fc-or { display:flex; align-items:center; gap:10px; color:#9aa79a; font-size:11px; margin:14px 0; }
.rm-fc-or::before, .rm-fc-or::after { content:""; flex:1; height:1px; background:#e2e8d5; }
.rm-fc-chip { display:inline-block; margin-left:6px; padding:2px 8px; border-radius:999px; font-size:10px; font-weight:700; }
.rm-fc-chip.ok { background:#e8f5e9; color:#2e7d32; }
.rm-fc-chip.warn { background:#fef3c7; color:#92400e; }
.rm-fc-chip.danger { background:#fee2e2; color:#b91c1c; }
.rm-fcm { border:none; border-radius:22px; overflow:hidden; background:#fef8f3; box-shadow:0 18px 44px rgba(41,87,56,.16); font-family:"Poppins",sans-serif; }
.rm-fcm-head { position:relative; display:flex; align-items:center; gap:12px; padding:18px 22px; background:#fbf6ee; border-bottom:1px solid #efe8d6; }
.rm-fcm-head-icon { width:44px; height:44px; border-radius:14px; background:#eef4e7; border:1px solid #d7e3cd; color:#4a7350; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
.rm-fcm-title { margin:0; font-size:15px; font-weight:700; letter-spacing:.2px; color:#295738; }
.rm-fcm-sub { margin:2px 0 0; font-size:11px; color:#8a9a8a; }
.rm-fcm-close { position:absolute; top:12px; right:14px; background:#f1eddc; border:none; color:#6b7280; width:28px; height:28px; border-radius:50%; font-size:16px; line-height:1; cursor:pointer; }
.rm-fcm-close:hover { background:#e7e2cc; color:#295738; }
.rm-fcm-body { padding:18px 22px 6px; max-height:66vh; overflow-y:auto; }
.rm-fcm-foot { display:flex; justify-content:flex-end; gap:10px; padding:14px 22px 18px; background:#fbf6ee; border-top:1px solid #efe8d6; }
.rm-fcm-step-head { display:flex; align-items:flex-start; gap:10px; margin:4px 0 10px; }
.rm-fcm-step-num { width:24px; height:24px; border-radius:50%; background:#eef4e7; border:1px solid #d7e3cd; color:#3f6b4a; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
.rm-fcm-step-title { font-size:13px; font-weight:700; color:#295738; line-height:1.3; }
.rm-fcm-step-sub { font-size:11px; color:#8a9a8a; line-height:1.5; }
.rm-fcm-field { display:flex; align-items:stretch; border:1.5px solid #e3e5d4; border-radius:12px; background:#fff; overflow:hidden; margin-bottom:10px; transition:border-color .15s, box-shadow .15s; }
.rm-fcm-field:focus-within { border-color:#b9cfa8; box-shadow:0 0 0 3px rgba(107,141,107,.10); }
.rm-fcm-field-icon { width:42px; display:flex; align-items:center; justify-content:center; background:#faf8ef; color:#5c7a5e; border-right:1px solid #eeecdc; font-size:15px; flex-shrink:0; }
.rm-fcm-input { flex:1; min-width:0; border:none; outline:none; padding:11px 12px; font-size:13px; font-family:"Poppins",sans-serif; color:#3f5a44; background:transparent; }
.rm-fcm-code { text-align:center; letter-spacing:8px; font-weight:700; font-size:16px; color:#295738; padding-left:20px; }
.rm-fcm-primary { border:1.5px solid #a8d6a0; border-radius:12px; padding:10px 18px; font-size:13px; font-weight:700; font-family:"Poppins",sans-serif; color:#1f5a2a; background:#d2eace; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:background .15s, transform .05s; }
.rm-fcm-primary:hover { background:#c3e2bc; }
.rm-fcm-primary:active { transform:translateY(1px); }
.rm-fcm-primary:disabled { opacity:.55; cursor:not-allowed; }
.rm-fcm-secondary { border:1.5px solid #e0dcc8; border-radius:12px; padding:10px 18px; font-size:13px; font-weight:600; font-family:"Poppins",sans-serif; color:#4b5563; background:#fff; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; }
.rm-fcm-secondary:hover { background:#faf8ef; border-color:#cdd9b5; color:#295738; }
#rmFcStepIdentity > .rm-fcm-primary { width:100%; }
.rm-fcm-foot #saveFinanceCodeBtn { min-width:150px; }
.rm-fcm-row { display:flex; gap:8px; }
.rm-fcm-row .rm-fcm-primary, .rm-fcm-row .rm-fcm-secondary { flex:1; }
</style>

<script src="/public/login/custom-alert.js?v=<?= time() ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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

<script>
let selectedDriver = null;

const DRIVER_SHOW_LIMIT = 7;
let driverFilter = "All";
let driverSearch = "";

function driverTotal() {
    let list = document.getElementById("rmDriverList");
    return list ? list.querySelectorAll(".driver-item").length : 0;
}

function driverMatchesSearch(item) {
    if (!driverSearch) return true;
    let name = (item.querySelector(".rm-driver-name")?.textContent || "").toLowerCase();
    let id = (item.querySelector(".rm-driver-id")?.textContent || "").toLowerCase();
    let key = (item.dataset.driver || "").toLowerCase();
    return name.indexOf(driverSearch) !== -1
        || id.indexOf(driverSearch) !== -1
        || key.indexOf(driverSearch) !== -1;
}

function applyDriverVisibility() {
    let list = document.getElementById("rmDriverList");
    if (!list) return;
    let expanded = list.dataset.expanded === "1";
    let visible = 0;

    list.querySelectorAll(".driver-item").forEach(function (item, i) {
        let byFilter = driverFilter === "All" || item.dataset.status === driverFilter;
        let bySearch = driverMatchesSearch(item);
        let byExpand = expanded || i < DRIVER_SHOW_LIMIT || driverSearch !== "";
        let show = byFilter && bySearch && byExpand;
        item.style.display = show ? "flex" : "none";
        if (show) visible++;
    });

    let countEl = document.getElementById("rmDriverCount");
    if (countEl) {
        let total = driverTotal();
        countEl.textContent = visible === total
            ? total + " driver(s)"
            : visible + " of " + total + " driver(s)";
    }
}

function updateShowMoreButton() {
    if (!showMoreBtn) return;
    let list = document.getElementById("rmDriverList");
    let expanded = list.dataset.expanded === "1";
    let label = showMoreBtn.querySelector(".rm-sm-label");
    let count = showMoreBtn.querySelector(".rm-sm-count");
    if (expanded) {
        label.textContent = "Show Less";
        if (count) count.textContent = "";
    } else {
        label.textContent = "Show More";
        if (count) {
            let remaining = driverTotal() - DRIVER_SHOW_LIMIT;
            count.textContent = remaining > 0 ? "(" + remaining + " more)" : "";
        }
    }
}

let showMoreBtn = document.getElementById("rmShowMoreBtn");
if (showMoreBtn) {
    let list = document.getElementById("rmDriverList");
    showMoreBtn.addEventListener("click", function () {
        let expanded = list.dataset.expanded === "1";
        list.dataset.expanded = expanded ? "0" : "1";
        showMoreBtn.dataset.expanded = expanded ? "0" : "1";
        list.classList.toggle("rm-driver-list-scroll", !expanded);
        updateShowMoreButton();
        applyDriverVisibility();
    });
}

applyDriverVisibility();
updateShowMoreButton();

function escapeHtml(s) {
    let d = document.createElement("div");
    d.textContent = s == null ? "" : s;
    return d.innerHTML;
}

function formatAuditDate(s) {
    if (!s) return "";
    let parts = String(s).split(" ");
    let d = new Date(parts[0].replace(/-/g, "/") + " " + (parts[1] || ""));
    if (isNaN(d.getTime())) return s;
    let dateStr = d.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
    });
    let timeStr = d.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit" });
    return dateStr + " · " + timeStr;
}

function loadRemittanceAudit(driverId, ref) {
    let container = document.getElementById("rmAudit-" + driverId);
    if (!container) return;
    let body = container.querySelector(".rm-audit-body");
    if (!body) return;
    body.innerHTML = '<small class="rm-tl-empty">Loading activity...</small>';

    fetch("/finance/remittance.php?action=audit&record_id=" + encodeURIComponent(ref))
        .then(function (r) {
            return r.json();
        })
        .then(function (data) {
            if (data.success && data.data && data.data.length) {
                renderRemittanceAudit(body, data.data);
            } else {
                body.innerHTML = '<small class="rm-tl-empty">No audit activity found.</small>';
            }
        })
        .catch(function () {
            body.innerHTML = '<small class="rm-tl-empty">Unable to load activity.</small>';
        });
}

function renderRemittanceAudit(container, rows) {
    let html = '<ul class="rm-timeline">';
    rows.forEach(function (r) {
        let failed = r.status && r.status !== "Success";
        let prev =
            r.previous_values && typeof r.previous_values === "object" ? r.previous_values : {};
        let nv = r.new_values && typeof r.new_values === "object" ? r.new_values : {};
        let skip = { driver: 1, driver_name: 1, date: 1 };
        let keys = Object.keys(nv).filter(function (k) {
            return !skip[k];
        });

        let changes = "";
        if (keys.length) {
            changes = '<div class="rm-tl-change">';
            keys.forEach(function (k) {
                let oldv = prev[k] !== undefined ? prev[k] : "—";
                let newv = nv[k];
                let dispOld = oldv,
                    dispNew = newv;
                if (
                    /amount|cash|expected|actual|discrepancy/i.test(k) &&
                    !isNaN(parseFloat(oldv))
                ) {
                    dispOld = "₱" + parseFloat(oldv).toFixed(2);
                }
                if (
                    /amount|cash|expected|actual|discrepancy/i.test(k) &&
                    !isNaN(parseFloat(newv))
                ) {
                    dispNew = "₱" + parseFloat(newv).toFixed(2);
                }
                if (typeof dispOld === "object") dispOld = JSON.stringify(dispOld);
                if (typeof dispNew === "object") dispNew = JSON.stringify(dispNew);
                changes +=
                    '<div><span class="old">' +
                    escapeHtml(k) +
                    ": " +
                    escapeHtml(dispOld) +
                    '</span><span class="new">' +
                    escapeHtml(dispNew) +
                    "</span></div>";
            });
            changes += "</div>";
        }

        let meta = '<span class="rm-tl-user">' + escapeHtml(r.username || "System") + "</span>";
        if (r.user_role) meta += " · " + escapeHtml(r.user_role);
        meta += " · " + formatAuditDate(r.created_at);

        html +=
            '<li class="rm-tl-item' +
            (failed ? " rm-tl-failed" : "") +
            '">' +
            '<div class="rm-tl-action">' +
            escapeHtml(r.action) +
            "</div>" +
            '<div class="rm-tl-meta">' +
            meta +
            "</div>" +
            (r.description
                ? '<div class="rm-tl-desc">' + escapeHtml(r.description) + "</div>"
                : "") +
            changes +
            "</li>";
    });
    html += "</ul>";
    container.innerHTML = html;
}

document.querySelectorAll(".driver-item").forEach((item) => {
    item.addEventListener("click", function () {
        let driver = this.dataset.driver;

        selectedDriver = driver;

        document.querySelectorAll(".driver-panel").forEach((panel) => {
            panel.style.display = "none";
        });

        document.querySelectorAll(".driver-item").forEach((i) => {
            i.classList.remove("rm-driver-active");
        });

        this.classList.add("rm-driver-active");

        let panel = document.querySelector(`.driver-panel[data-driver="${driver}"]`);

        if (panel) {
            panel.style.display = "block";

            let ref = panel.dataset.remitRef;

            if (ref) {
                loadRemittanceAudit(driver, ref);
            } else {
                let body = panel.querySelector(".rm-audit-body");

                if (body) {
                    body.innerHTML =
                        '<small class="rm-tl-empty">No remittance recorded for this driver today.</small>';
                }
            }
        }

        if (panel) {
            panel.querySelectorAll(".actual-cash-input,.submit-remittance").forEach((el) => {
                el.disabled = false;
            });

            if (this.dataset.status === "Completed") {
                panel.querySelectorAll(".actual-cash-input,.submit-remittance").forEach((el) => {
                    el.disabled = true;
                });
            } else {
                updatePanelReconciliation(panel);
            }
        }
    });
});

const statusFilterEl = document.getElementById("rmStatusFilter");
if (statusFilterEl) {
    statusFilterEl.addEventListener("change", function () {
        driverFilter = this.value;

        let list = document.getElementById("rmDriverList");
        if (list && list.dataset.expanded !== "1") {
            list.dataset.expanded = "1";
            list.classList.add("rm-driver-list-scroll");
            if (showMoreBtn) {
                showMoreBtn.dataset.expanded = "1";
                updateShowMoreButton();
            }
        }

        applyDriverVisibility();

        let firstVisible = document.querySelector('.driver-item[style*="flex"]');

        if (firstVisible) {
            firstVisible.click();
        }
    });
}

const driverSearchEl = document.getElementById("rmDriverSearch");
if (driverSearchEl) {
    driverSearchEl.addEventListener("input", function () {
        driverSearch = this.value.trim().toLowerCase();
        applyDriverVisibility();
    });
}

let first =
    document.querySelector('[data-status="Pending"]') || document.querySelector(".driver-item");

if (first) {
    first.click();
}

document.querySelectorAll(".actual-cash-input").forEach((input) => {
    input.addEventListener("input", function () {
        updatePanelReconciliation(this.closest(".driver-panel"));
    });
});

function updatePanelReconciliation(panel) {
    if (!panel) return;

    let input = panel.querySelector(".actual-cash-input");
    let expected = parseFloat(panel.dataset.totalExpected || 0);
    let actual = input ? parseFloat(input.value) : NaN;
    let hasActual = !!(input && input.value !== "" && !isNaN(actual));

    let diffEl = panel.querySelector(".rm-recon-diff");
    let statusEl = panel.querySelector(".rm-recon-status");

    if (!hasActual) {
        if (diffEl) {
            diffEl.textContent = "—";
            diffEl.className = "rm-recon-diff";
        }
        if (statusEl) {
            statusEl.textContent = panel.dataset.overdue === "1"
                ? "Overdue — awaiting late remittance"
                : "Awaiting remittance";
            statusEl.className = "rm-recon-status rm-recon-pending";
        }
        return;
    }

    let diff = Math.round((actual - expected) * 100) / 100;
    let absDiff = Math.abs(diff);

    if (diffEl) {
        if (diff === 0) {
            diffEl.textContent = "₱0.00";
            diffEl.className = "rm-recon-diff rm-diff-green";
        } else if (diff < 0) {
            diffEl.textContent = "-₱" + absDiff.toFixed(2);
            diffEl.className = "rm-recon-diff rm-diff-red";
        } else {
            diffEl.textContent = "+₱" + absDiff.toFixed(2);
            diffEl.className = "rm-recon-diff rm-diff-orange";
        }
    }

    if (statusEl) {
        if (diff === 0) {
            statusEl.textContent = "Balanced";
            statusEl.className = "rm-recon-status rm-recon-balanced";
        } else if (diff < 0) {
            statusEl.textContent = "Shortage: ₱" + absDiff.toFixed(2);
            statusEl.className = "rm-recon-status rm-recon-shortage";
        } else {
            statusEl.textContent = "Excess: ₱" + absDiff.toFixed(2);
            statusEl.className = "rm-recon-status rm-recon-overage";
        }
    }
}

function formatPeso(value) {
    return "₱" + Number(value || 0).toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function buildRemittanceReviewHtml(data) {
    let expected = Number(data.expected || 0);
    let actual = Number(data.actual || 0);
    let diff = Math.round((actual - expected) * 100) / 100;
    let trips = Number(data.tripCount || 0);

    let diffLabel;
    let diffClass;
    let diffValue;
    let statusClass;
    if (diff === 0) {
        diffLabel = "Balanced";
        diffClass = "rm-rv-green";
        diffValue = "₱0.00";
        statusClass = "rm-rv-status-green";
    } else if (diff < 0) {
        diffLabel = "Shortage";
        diffClass = "rm-rv-red";
        diffValue = "-" + formatPeso(Math.abs(diff));
        statusClass = "rm-rv-status-red";
    } else {
        diffLabel = "Excess";
        diffClass = "rm-rv-orange";
        diffValue = "+" + formatPeso(Math.abs(diff));
        statusClass = "rm-rv-status-orange";
    }

    return '<div class="rm-rv">' +
        '<div class="rm-rv-driver">' +
        '<span class="rm-rv-name">' + escapeHtml(data.driverName || "") + '</span>' +
        '<span class="rm-rv-id">' + escapeHtml(data.driverId || "") + '</span>' +
        '</div>' +
        '<div class="rm-rv-meta">' +
        '<span class="rm-rv-chip"><i class="bi bi-calendar3"></i> ' + escapeHtml(data.businessDate || "") + '</span>' +
        '<span class="rm-rv-chip"><i class="bi bi-signpost-split"></i> ' + trips + ' trip' + (trips === 1 ? "" : "s") + '</span>' +
        '</div>' +
        '<div class="rm-rv-rows">' +
        '<div class="rm-rv-row"><span>Expected Cash to Submit</span><strong>' + formatPeso(expected) + '</strong></div>' +
        '<div class="rm-rv-row"><span>Actual Cash Remitted</span><strong>' + formatPeso(actual) + '</strong></div>' +
        '<div class="rm-rv-row rm-rv-total"><span>Amount Difference</span><strong class="' + diffClass + '">' + diffValue + '</strong></div>' +
        '</div>' +
        '<div class="rm-rv-status ' + statusClass + '">' + diffLabel + '</div>' +
        '<div class="rm-rv-note"><i class="bi bi-shield-lock me-1"></i>The Finance Authorization Code is required after confirmation.</div>' +
        '</div>';
}

function closeCodeModal() {
    let modal = document.getElementById("codeModal");
    if (modal) modal.style.display = "none";
    let input = document.getElementById("financeCode");
    if (input) input.value = "";
}

function openReviewRemittanceAlert(html, onConfirm) {
    showConfirm("Review Remittance", html, onConfirm, null, { html: true });

    // Compatibility: a server still running an older custom-alert.js prints the
    // markup as text. Detect that and render it as HTML instead.
    let msgEl = document.querySelector("#customAlertOverlay .custom-alert-message");
    if (msgEl && msgEl.querySelector(".rm-rv") === null) {
        msgEl.innerHTML = html;
    }
}

document.querySelectorAll(".submit-remittance").forEach((btn) => {
    btn.addEventListener("click", function () {
        let panel = this.closest(".driver-panel");
        if (!panel) return;

        let input = panel.querySelector(".actual-cash-input");
        let expected = parseFloat(panel.dataset.totalExpected || 0);
        let actual = input ? parseFloat(input.value) : NaN;

        if (!input || input.value === "" || isNaN(actual)) {
            showAlert("error", "Actual Cash Required", "Please enter the actual cash remitted by the driver.");
            return;
        }

        if (actual < 0) {
            showAlert("error", "Invalid Amount", "Actual cash cannot be negative.");
            return;
        }

        let reviewHtml = buildRemittanceReviewHtml({
            driverName: panel.dataset.driverName || "",
            driverId: panel.dataset.driverId || "",
            businessDate: panel.dataset.businessDate || "",
            tripCount: panel.dataset.tripCount || "0",
            expected: expected,
            actual: actual,
        });

        openReviewRemittanceAlert(reviewHtml, function () {
            selectedDriver = panel.dataset.driver;
            document.getElementById("financeCode").value = "";
            document.getElementById("codeModalSummary").innerHTML = reviewHtml;
            document.getElementById("codeModal").style.display = "flex";
        });
    });
});

document.getElementById("codeModal").addEventListener("click", function (e) {
    if (e.target === this) {
        closeCodeModal();
    }
});

document.getElementById("confirmCode").addEventListener("click", async () => {
    let code = document.getElementById("financeCode").value.trim();

    if (!code) {
        showAlert("error", "Finance Code Required", "Please enter your finance authorization code.");
        return;
    }

    let activePanel = document.querySelector(`.driver-panel[data-driver="${selectedDriver}"]`);

    if (!activePanel) {
        showAlert("error", "No Driver Selected", "Please select a driver first.");
        return;
    }

    let actualInput = activePanel.querySelector(".actual-cash-input");
    let actualCash = actualInput ? parseFloat(actualInput.value) : NaN;

    if (!actualInput || actualInput.value === "" || isNaN(actualCash)) {
        showAlert("error", "Actual Cash Required", "Please enter the actual cash remitted by the driver.");
        return;
    }

    try {
        const response = await fetch("/finance/remittance.php?action=confirm", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                code: code,
                driver: activePanel.dataset.driverId || "",
                business_date: activePanel.dataset.businessDate || "",
                actual_cash: actualCash,
            }),
        });

        const data = await response.json();

        if (data.success) {
            document.getElementById("codeModal").style.display = "none";

            var status = data.summary?.status ?? "Completed";
            var msg =
                "Driver: " + (activePanel.dataset.driverId || "") +
                " | Business date: " + (data.summary?.business_date || "") +
                " | Expected Cash to Submit: ₱" + Number(data.summary?.expected_cash || 0).toFixed(2) +
                " | Actual Cash Remitted: ₱" + Number(data.summary?.actual_cash || 0).toFixed(2);

            if (status === "Discrepancy") {
                msg += " | Shortage: ₱" + Number(data.summary?.discrepancy_amount || 0).toFixed(2);
                showAlert("error", "Remittance Submitted (Shortage)", msg, function () {
                    location.reload();
                });
            } else {
                showAlert(
                    "success",
                    data.late ? "Late Remittance Submitted" : "Remittance Submitted",
                    msg,
                    function () {
                        location.reload();
                    }
                );
            }
        } else {
            if (data.code === "finance_code_expired" || data.code === "finance_code_missing") {
                showAlert("error", "Finance Code Renewal Required", data.message || "Please renew your Finance Authorization Code.", function () {
                    openFinanceCodeModal();
                });
            } else {
                showAlert("error", "Submission Failed", data.message || "Unable to process remittance.");
            }
        }
    } catch (error) {
        console.error(error);
        showAlert("error", "System Error", "Unable to process remittance confirmation.");
    }
});

document.querySelectorAll(".download-report").forEach((btn) => {
    btn.addEventListener("click", function () {
        let panel = this.closest(".driver-panel");

        if (!panel) {
            showAlert("error", "No Driver Selected", "Please select a driver first.");
            return;
        }

        let driverId = panel.dataset.driverId || "";
        let businessDate = panel.dataset.businessDate || "";

        window.location.href =
            "/finance/remittance.php?action=download_report&driver=" +
            encodeURIComponent(driverId) +
            "&business_date=" + encodeURIComponent(businessDate);
    });
});

const FINANCE_CODE_STATE = { warned: false };

function financeCodeStatusLabel(status) {
    if (status === "expired") return "Expired — renewal required";
    if (status === "missing") return "Not set";
    if (status === "legacy") return "Renew code";
    if (status === "expiring") return "Expiring soon";
    return "Active";
}

function refreshFinanceCodeStatus(warn) {
    fetch("/finance/remittance.php?action=finance_code_status")
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) return;

            let chip = document.getElementById("rmFcChip");
            let statusEl = document.getElementById("rmFcStatus");
            let expiryText = data.expires_at_label ? " Active until " + data.expires_at_label + "." : "";
            let daysText = (data.days_left !== null && data.days_left !== undefined && !data.expired)
                ? " (" + data.days_left + " day(s) left)" : "";

            if (chip) {
                chip.style.display = "inline-block";
                chip.textContent = financeCodeStatusLabel(data.status);
                chip.className = "rm-fc-chip " + (data.status === "active" ? "ok" : ((data.status === "expiring" || data.status === "legacy") ? "warn" : "danger"));
            }

            if (statusEl) {
                statusEl.className = "rm-fc-status" + (data.status === "active" ? "" : ((data.status === "expiring" || data.status === "legacy") ? " warn" : " danger"));
                if (data.status === "missing") {
                    statusEl.innerHTML = "No Finance Authorization Code is set. Remittance submissions are blocked until you create one.";
                } else if (data.status === "expired") {
                    statusEl.innerHTML = "Your Finance Authorization Code expired" + (data.expires_at_label ? " on <strong>" + escapeHtml(data.expires_at_label) + "</strong>" : "") + ". Remittance submissions are blocked until you renew it.";
                } else if (data.status === "legacy") {
                    statusEl.innerHTML = "A legacy Finance Authorization Code is still active. Renew it with a new 6-digit code so it expires every 30 days.";
                } else {
                    statusEl.innerHTML = "Code status: <strong>" + escapeHtml(financeCodeStatusLabel(data.status)) + "</strong>." + escapeHtml(expiryText + daysText);
                }
            }

            if (warn && !FINANCE_CODE_STATE.warned) {
                FINANCE_CODE_STATE.warned = true;
                if (data.status === "missing") {
                    showAlert("error", "Finance Code Required", "No Finance Authorization Code is set. Set one now to authorize remittance submissions.", function () {
                        openFinanceCodeModal();
                    });
                } else if (data.status === "expired") {
                    showAlert("error", "Finance Code Expired", "Your Finance Authorization Code expired" + (data.expires_at_label ? " on " + data.expires_at_label : "") + ". Renew it to continue authorizing remittance submissions.", function () {
                        openFinanceCodeModal();
                    });
                } else if (data.status === "expiring" || data.status === "legacy") {
                    showAlert("error", "Finance Code Renewal Recommended", "Your Finance Authorization Code expires on " + (data.expires_at_label || "soon") + " (" + (data.days_left || 0) + " day(s) left). Please renew it.", function () {
                        openFinanceCodeModal();
                    });
                }
            }
        })
        .catch(function () {});
}

function financeCodeIdentityStep() {
    let stepIdentity = document.getElementById("rmFcStepIdentity");
    let stepCode = document.getElementById("rmFcStepCode");
    let saveBtn = document.getElementById("saveFinanceCodeBtn");
    if (stepIdentity) stepIdentity.style.display = "block";
    if (stepCode) stepCode.style.display = "none";
    if (saveBtn) saveBtn.style.display = "none";
}

function financeCodeSetupStep() {
    let stepIdentity = document.getElementById("rmFcStepIdentity");
    let stepCode = document.getElementById("rmFcStepCode");
    let saveBtn = document.getElementById("saveFinanceCodeBtn");
    if (stepIdentity) stepIdentity.style.display = "none";
    if (stepCode) stepCode.style.display = "block";
    if (saveBtn) saveBtn.style.display = "inline-flex";

    let codeInput = document.getElementById("financeCodeInput");
    let confirmInput = document.getElementById("rmFcConfirmCode");
    if (codeInput) {
        codeInput.value = "";
        codeInput.focus();
    }
    if (confirmInput) confirmInput.value = "";
}

function openFinanceCodeModal() {
    financeCodeIdentityStep();

    let pw = document.getElementById("rmFcPassword");
    let otp = document.getElementById("rmFcOtp");
    if (pw) pw.value = "";
    if (otp) otp.value = "";

    refreshFinanceCodeStatus(false);

    new bootstrap.Modal(document.getElementById("financeCodeModal")).show();
}

function verifyFinanceCodeIdentity(method, varName, value) {
    let body = new URLSearchParams({ method: method });
    body.append(varName, value);

    fetch("/finance/remittance.php?action=verify_code_identity", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                financeCodeSetupStep();
                showAlert("success", "Identity Verified", "You can now set your new 6-digit Finance Authorization Code.");
            } else {
                showAlert("error", "Verification Failed", res.message || "Please try again.");
            }
        })
        .catch(function () {
            showAlert("error", "Error", "Network error. Please try again.");
        });
}

document.getElementById("rmFcVerifyPassword")?.addEventListener("click", function () {
    let password = document.getElementById("rmFcPassword").value;
    if (!password) {
        showAlert("error", "Password Required", "Please enter your current account password.");
        return;
    }
    verifyFinanceCodeIdentity("password", "password", password);
});

document.getElementById("rmFcSendOtp")?.addEventListener("click", function () {
    let btn = this;
    btn.disabled = true;

    fetch("/finance/remittance.php?action=send_code_otp", { method: "POST" })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            btn.disabled = false;
            if (res.success) {
                showAlert("success", "Verification Code Sent", res.message || "Check your registered email for the code.");
            } else {
                showAlert("error", "Could Not Send Code", res.message || "Please try again.");
            }
        })
        .catch(function () {
            btn.disabled = false;
            showAlert("error", "Error", "Network error. Please try again.");
        });
});

document.getElementById("rmFcVerifyOtp")?.addEventListener("click", function () {
    let otp = document.getElementById("rmFcOtp").value.trim();
    if (!/^\d{6}$/.test(otp)) {
        showAlert("error", "Invalid Code", "Please enter the 6-digit code sent to your email.");
        return;
    }
    verifyFinanceCodeIdentity("otp", "otp", otp);
});

document.getElementById("saveFinanceCodeBtn")?.addEventListener("click", function () {
    let code = document.getElementById("financeCodeInput").value.trim();
    let confirmCode = document.getElementById("rmFcConfirmCode").value.trim();

    if (!/^\d{6}$/.test(code)) {
        showAlert("error", "Invalid Code", "The Finance Authorization Code must be exactly 6 numeric digits.");
        return;
    }
    if (code !== confirmCode) {
        showAlert("error", "Codes Do Not Match", "Please re-enter the same 6-digit code.");
        return;
    }

    let body = new URLSearchParams({ code: code, confirm_code: confirmCode });

    fetch("/finance/remittance.php?action=save_code", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                let modalEl = document.getElementById("financeCodeModal");
                let modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                let expiry = res.expires_at_label || "";
                showAlert("success", "Finance Code Saved", "Your Finance Authorization Code is active until " + expiry + " (30 days). Never share it with anyone.");
                refreshFinanceCodeStatus(false);
            } else if (res.code === "identity_required") {
                financeCodeIdentityStep();
                showAlert("error", "Identity Required", res.message || "Please verify your identity first.");
            } else {
                showAlert("error", "Save Failed", res.message || "Please try again.");
            }
        })
        .catch(function () {
            showAlert("error", "Error", "Network error. Please try again.");
        });
});

refreshFinanceCodeStatus(true);
</script>
<?php
$aiPageData = [
    'date' => date('Y-m-d'),
    'total_drivers' => $driverTotalCount ?? 0,
    'total_system_collection' => (float)($grandTotal ?? 0),
    'total_cash' => (float)($grandCash ?? 0),
    'total_gcash' => (float)($grandGcash ?? 0),
    'drivers' => array_values(array_map(function ($d) {
        return [
            'driver_id' => $d['driver_id'] ?? '',
            'driver_name' => $d['driver_name'] ?? '',
            'status' => !empty($d['submitted']) ? 'Completed' : 'Pending',
            'submitted_by' => $d['submitted_by'] ?? null,
        ];
    }, $visibleDrivers ?? [])),
];
$aiPageFilters = [
    'range' => 'today',
    'fromDate' => date('Y-m-d'),
    'toDate' => date('Y-m-d'),
];
?>
<script>
window.__aiPageData = <?= json_encode($aiPageData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
window.__aiPageFilters = <?= json_encode($aiPageFilters, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php require __DIR__ . '/_aibutton.php'; ?>

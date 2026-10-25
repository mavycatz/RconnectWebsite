<?php header('Cache-Control: no-store, no-cache, must-revalidate'); header('Pragma: no-cache'); ?>
<?php
function fca_bus_label($busId, $label = null) {
    $s = trim((string) (($label !== null && $label !== '') ? $label : $busId));
    $s = trim((string) preg_replace('/\s+/', ' ', $s));
    if ($s === '') { return 'Unassigned Bus'; }
    $s = (string) preg_replace('/^Bus\s*#?\s*[-: ]*/i', '', $s);
    if ($s === '') { return 'Unassigned Bus'; }
    $s = (string) preg_replace('/^BUS/', 'Bus', $s);
    if (preg_match('/\bBus\b/i', $s) === 1) { return $s; }
    return 'Bus-' . $s;
}
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">
<style>
.fca-wrap {
    font-family: "Poppins", sans-serif;
    background: #eceed9;
    padding: 20px;
}
.fca-card {
    background: #fef8f3;
    border: 1px solid #cdd9b5;
    border-radius: 18px;
    padding: 22px;
}

.fca-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
}
.fca-title {
    font-weight: 800;
    font-size: 1.35rem;
    color: #2e4836;
    margin: 0 0 2px;
}
.fca-sub {
    font-weight: 500;
    font-size: 0.82rem;
    color: #8a9a8a;
    margin: 0;
}
.fca-toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px 16px;
    padding: 10px 14px;
    margin-bottom: 18px;
    background: #fffdf6;
    border: 1px solid #e3eabb;
    border-radius: 14px;
}
.fca-toolbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.fca-header-actions {
    display: flex;
    gap: 10px;
}
#fcaAnalyticsPanel {
    position: relative;
}
.fca-filter-loading {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
    background: #fdfbf6;
    cursor: progress;
}
.fca-filter-loading[hidden] {
    display: none;
}
.fca-filter-loading-card {
    text-align: center;
}
.fca-filter-spinner {
    width: 46px;
    height: 46px;
    margin: 0 auto 14px;
    border: 4px solid #ece7d8;
    border-top-color: #2f6f4f;
    border-radius: 50%;
    animation: fca-filter-spin 0.8s linear infinite;
}
@keyframes fca-filter-spin {
    to {
        transform: rotate(360deg);
    }
}
.fca-filter-loading-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2f4f2f;
}
.fca-filter-loading-sub {
    margin: 4px 0 0;
    color: #94a3b8;
    font-size: 0.75rem;
}
.fca-btn-export-pdf {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #2e4836;
    color: #ffffff;
    border: 1px solid #2e4836;
    border-radius: 10px;
    padding: 9px 16px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: 0.2s ease;
}
.fca-btn-export-pdf:hover {
    background: #3c6e4f;
    color: #ffffff;
}
.fca-btn-export-excel {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: #f9f4da;
    color: #2e4836;
    border: 1px solid #cdd9b5;
    border-radius: 10px;
    padding: 9px 16px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: 0.2s ease;
}
.fca-btn-export-excel:hover {
    background: #fef8f3;
    color: #2e4836;
}

.fca-kpi-row {
    display: flex;
    gap: 16px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}
.fca-kpi {
    flex: 1 1 220px;
    min-width: 0;
    background: #ffffff;
    border: 1px solid #cdd9b5;
    border-radius: 16px;
    padding: 16px 18px;
    box-shadow: 0 1px 3px rgba(46, 72, 54, 0.05);
    display: flex;
    align-items: center;
    gap: 14px;
}
.fca-kpi-icon {
    flex: 0 0 auto;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: #eceed9;
    color: #2e4836;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
.fca-kpi-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #8a9a8a;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    margin: 0 0 2px;
}
.fca-kpi-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #2e4836;
    margin: 0;
    line-height: 1.15;
}
.fca-kpi-sub {
    font-size: 0.75rem;
    font-weight: 500;
    color: #a3b3a0;
    margin: 2px 0 0;
}

.fca-charts-row {
    display: flex;
    gap: 16px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.fca-chart-card {
    flex: 1 1 0;
    min-width: 320px;
    background: #ffffff;
    border: 1px solid #cdd9b5;
    border-radius: 16px;
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
}
.fca-chart-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.fca-chart-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #2e4836;
    margin: 0;
}
.fca-period-select {
    border: 1px solid #cdd9b5;
    border-radius: 8px;
    background: #fef8f3;
    font-family: "Poppins", sans-serif;
    font-size: 0.75rem;
    font-weight: 500;
    color: #2e4836;
    padding: 5px 8px;
}
.fca-chart-period {
    font-size: 0.72rem;
    font-weight: 600;
    color: #1b5e20;
    background: #f4faf5;
    border: 1px solid #cdd9b5;
    border-radius: 999px;
    padding: 3px 10px;
    white-space: nowrap;
}
.fca-chart-box {
    height: 210px;
    width: 100%;
    position: relative;
}
.fca-chart-card .fca-chart-box {
    height: auto;
    flex: none;
    min-height: 210px;
}

#fcaLineBox {
    flex: 1 1 auto;
    min-height: 400px;
}

#fcaBarBox {
    max-height: 560px;
    overflow-y: auto;
}
#fcaCompBox {
    max-height: 560px;
    overflow-y: auto;
}
.fca-axis {
    font-size: 10px;
    fill: #8a9a8a;
}
.fca-bar-label {
    font-size: 9.5px;
    font-weight: 600;
    fill: #2e4836;
}
.fca-empty {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #8a9a8a;
    font-size: 0.85rem;
    font-weight: 500;
}
.fca-tip {
    position: absolute;
    pointer-events: none;
    z-index: 50;
    background: #2e4836;
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 500;
    padding: 6px 10px;
    border-radius: 8px;
    white-space: nowrap;
    display: none;
    line-height: 1.5;
}

.fca-panel-recent {
    flex: 0 0 24%;
    min-width: 280px;
    background: #ffffff;
    border: 1px solid #cdd9b5;
    border-radius: 16px;
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
}
.fca-panel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.fca-panel-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #2e4836;
    margin: 0;
}
.fca-view-all {
    font-size: 0.75rem;
    font-weight: 600;
    color: #2563eb;
    text-decoration: none;
    cursor: pointer;
}
.fca-view-all:hover {
    text-decoration: underline;
}
.fca-receipt-slip,
.fca-print-slip {
    font-family: "Courier New", Courier, monospace;
    color: #111;
}
.fca-receipt-slip {
    position: relative;
    background: #ffffff;
    border: 1px solid #d6dfc2;
    border-radius: 4px;
    padding: 14px 16px;
    margin-bottom: 12px;
    box-shadow: 0 1px 4px rgba(46, 72, 54, 0.07);
    overflow: hidden;
}
.fca-receipt-slip .fca-watermark {
    font-size: 0.8rem;
    letter-spacing: 0.1em;
}
.fca-receipt-slip:last-child {
    margin-bottom: 0;
}
.fca-receipt-slip .fca-receipt-shop,
.fca-print-slip .fca-receipt-shop {
    text-align: center;
    font-size: 9.5px;
    font-weight: 400;
    line-height: 13px;
    letter-spacing: 0.3px;
    color: #111;
    text-transform: uppercase;
}
.fca-receipt-slip .fca-receipt-sub,
.fca-print-slip .fca-receipt-sub {
    text-align: center;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 3px;
    color: #111;
    margin: 6px 0 2px;
}
.fca-receipt-slip .fca-receipt-date,
.fca-print-slip .fca-receipt-date {
    text-align: center;
    font-size: 11px;
    color: #333;
    margin: 0 0 10px;
}
.fca-receipt-slip .fca-receipt-dash,
.fca-print-slip .fca-receipt-dash {
    border-top: 1px dashed #111;
    margin: 10px 0;
}
.fca-receipt-slip .fca-receipt-row,
.fca-print-slip .fca-receipt-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    font-size: 11px;
    color: #111;
    margin: 0 0 4px;
}
.fca-receipt-slip .fca-receipt-row > span:first-child,
.fca-print-slip .fca-receipt-row > span:first-child {
    font-size: 11px;
    font-weight: 400;
    color: #111;
}
.fca-receipt-slip .fca-receipt-row > span:last-child,
.fca-print-slip .fca-receipt-row > span:last-child {
    font-size: 11px;
    font-weight: 600;
    color: #111;
}
.fca-receipt-slip .fca-info-row > span:first-child,
.fca-print-slip .fca-info-row > span:first-child {
    font-weight: 600;
    letter-spacing: 1px;
}
.fca-receipt-slip .fca-receipt-total,
.fca-print-slip .fca-receipt-total {
    border-top: none;
    margin: 0 0 4px;
    padding: 0;
}
.fca-receipt-slip .fca-receipt-total > span:last-child,
.fca-print-slip .fca-receipt-total > span:last-child {
    font-size: 16px;
    font-weight: 700;
}
.fca-receipt-slip .fca-receipt-note,
.fca-print-slip .fca-receipt-note {
    text-align: center;
    font-size: 11px;
    font-weight: 400;
    color: #333;
    margin: 0 0 4px;
}
.fca-receipt-slip .fca-receipt-sign,
.fca-print-slip .fca-receipt-sign {
    margin-top: 6px;
    text-align: center;
}
.fca-receipt-slip .fca-sign-line,
.fca-print-slip .fca-sign-line {
    border-top: 1px solid #111;
    width: 70%;
    margin: 0 auto 5px;
}
.fca-receipt-slip .fca-sign-label,
.fca-print-slip .fca-sign-label {
    font-size: 10px;
    font-weight: 700;
    color: #111;
    letter-spacing: 1px;
    margin: 0;
}
.fca-receipt-empty {
    font-size: 0.75rem;
    font-weight: 500;
    color: #8a9a8a;
    padding: 12px 0;
    text-align: center;
}

.fca-preview-paper {
    position: relative;
    width: 25vw;
    max-width: 100%;
    height: 100%;
    background: #ffffff;
    padding: 20px 24px;
    border-radius: 6px;
    box-shadow: 0 2px 12px rgba(46, 72, 54, 0.18);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}
.fca-preview-paper .fca-print-slip {
    margin: auto;
}
.fca-watermark {
    position: absolute;
    inset: 0;
    z-index: 5;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: "Poppins", sans-serif;
    font-weight: 800;
    font-size: 1.7rem;
    letter-spacing: 0.14em;
    color: rgba(190, 70, 70, 0.15);
    transform: rotate(-28deg);
    white-space: nowrap;
}
.fca-print-slip {
    font-family: "Courier New", Courier, monospace;
    color: #111;
}
.fca-receipt-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: transparent;
    color: #2e4836;
    border: 1px solid #2e4836;
    border-radius: 30px;
    padding: 10px 18px;
    font-size: 0.8rem;
    font-weight: 600;
    font-family: "Poppins", sans-serif;
    cursor: pointer;
    transition: 0.2s ease;
}
.fca-receipt-action-btn:hover {
    background: #eceed9;
}
.fca-receipt-close-btn {
    background: transparent;
    color: #111;
    border: 1px solid #111;
    border-radius: 4px;
    padding: 10px 40px;
    font-size: 0.8rem;
    font-weight: 600;
    font-family: "Poppins", sans-serif;
    cursor: pointer;
    transition: 0.2s ease;
}
.fca-receipt-close-btn:hover {
    background: #f5f5f5;
}

@media print {
    body * {
        visibility: hidden !important;
    }
    #fcaPrintReceipt,
    #fcaPrintReceipt * {
        visibility: visible !important;
    }
    #fcaPrintReceipt {
        position: fixed !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
    }
}

.fca-sec-stats {
    display: flex;
    gap: 26px;
    flex-wrap: wrap;
    margin: 0 0 16px;
    padding: 0 4px;
}
.fca-sec-stat {
    font-size: 0.78rem;
    font-weight: 500;
    color: #8a9a8a;
}
.fca-sec-stat b {
    color: #2e4836;
    font-weight: 700;
}

.fca-filter-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    background: #ffffff;
    border: 1px solid #cdd9b5;
    border-radius: 16px;
    padding: 13px 16px;
    margin-bottom: 18px;
}
.fca-filter-bar > div {
    flex: 1 1 0;
    min-width: 120px;
}
.fca-filter-bar .fca-select,
.fca-filter-bar .fca-input {
    width: 100%;
    min-width: 0;
}
.fca-filter-bar .fca-date-row > input {
    flex: 1 1 0;
    width: auto;
    min-width: 0;
}
.fca-clear-btn {
    flex: 0 0 auto;
    align-self: flex-end;
    white-space: nowrap;
    background: #eceed9;
    color: #2e4836;
    border: 1px solid #cdd9b5;
    border-radius: 9px;
    padding: 7px 14px;
    font-size: 0.8rem;
    font-weight: 600;
    font-family: "Poppins", sans-serif;
    cursor: pointer;
    transition: 0.2s ease;
}
.fca-clear-btn:hover {
    background: #dbe4c5;
}
.fca-filter-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #2e4836;
    margin: 0 0 3px;
    display: block;
}
.fca-select,
.fca-input {
    border: 1px solid #cdd9b5;
    border-radius: 9px;
    background: #fef8f3;
    font-family: "Poppins", sans-serif;
    font-size: 0.8rem;
    font-weight: 500;
    color: #2e4836;
    padding: 7px 10px;
    min-width: 150px;
}
.fca-input {
    min-width: 140px;
}

.fca-table-wrap {
    overflow-x: auto;
    margin-bottom: 14px;
}
.fca-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
    background: #ffffff;
}
.fca-table thead th {
    background: #f9f4da;
    color: #2e4836;
    font-weight: 700;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 11px 12px;
    text-align: left;
    border-bottom: 1px solid #cdd9b5;
    white-space: nowrap;
}
.fca-table tbody td {
    padding: 11px 12px;
    border-bottom: 1px solid #e3eabb;
    vertical-align: middle;
}
.fca-table tbody tr:hover {
    background: #f5f8ec;
}
.fca-receipt-cell {
    font-weight: 700;
    color: #2e4836;
    white-space: nowrap;
}
.fca-date-cell {
    font-weight: 600;
    color: #2e4836;
    line-height: 1.4;
}
.fca-time-cell {
    font-size: 0.75rem;
    color: #8a9a8a;
}
.fca-drv-name {
    font-weight: 600;
    color: #2e4836;
}
.fca-drv-id {
    font-size: 0.75rem;
    color: #8a9a8a;
}
.fca-num {
    text-align: right;
    white-space: nowrap;
    font-weight: 500;
}
.fca-cost-cell {
    font-weight: 700;
    color: #2e4836;
}
.fca-view-btn {
    background: #eceed9;
    color: #2e4836;
    border: 1px solid #cdd9b5;
    border-radius: 8px;
    padding: 5px 14px;
    font-size: 0.75rem;
    font-weight: 600;
    font-family: "Poppins", sans-serif;
    cursor: pointer;
    transition: 0.2s ease;
}
.fca-view-btn:hover {
    background: #dbe4c5;
}
.fca-table-empty {
    text-align: center;
    color: #8a9a8a;
    font-weight: 500;
    padding: 26px 12px !important;
}

.fca-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.fca-page-info {
    font-size: 0.78rem;
    font-weight: 500;
    color: #8a9a8a;
}
.fca-page-btns {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.fca-page-btn {
    background: #ffffff;
    color: #2e4836;
    border: 1px solid #cdd9b5;
    border-radius: 8px;
    min-width: 32px;
    padding: 5px 10px;
    font-size: 0.78rem;
    font-weight: 600;
    font-family: "Poppins", sans-serif;
    cursor: pointer;
    transition: 0.2s ease;
}
.fca-page-btn:hover {
    background: #eceed9;
}
.fca-page-btn.active {
    background: #2e4836;
    color: #ffffff;
    border-color: #2e4836;
}
.fca-page-btn:disabled {
    opacity: 0.45;
    cursor: default;
}
.fca-page-size {
    font-size: 0.78rem;
    font-weight: 500;
    color: #8a9a8a;
    display: flex;
    align-items: center;
    gap: 7px;
}

.fca-modal-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #8a9a8a;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin: 0 0 2px;
}
.fca-modal-value {
    font-size: 0.95rem;
    font-weight: 700;
    color: #2e4836;
    margin: 0 0 14px;
}
.fca-va-filter-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: flex-end;
    margin-bottom: 12px;
}

.fca-va-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 2px;
}
.fca-va-icon {
    flex: 0 0 auto;
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: #2e4836;
    color: #f9f4da;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}
.fca-va-header .modal-title {
    font-weight: 800;
    font-size: 1.15rem;
    color: #2e4836;
    margin: 0 0 2px;
}
.fca-va-header small {
    font-weight: 500;
    font-size: 0.8rem;
    color: #8a9a8a;
    display: block;
}
.fca-va-filter-row {
    align-items: center;
}
.fca-va-field {
    position: relative;
    display: flex;
    align-items: center;
    background: #fef8f3;
    border: 1px solid #cdd9b5;
    border-radius: 9px;
    height: 38px;
    min-width: 0;
    transition: 0.2s ease;
}
.fca-va-field:focus-within {
    border-color: #2e4836;
}
.fca-va-field > i {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.85rem;
    color: #8a9a8a;
    pointer-events: none;
}
.fca-va-input {
    flex: 1 1 auto;
    border: none;
    background: transparent;
    outline: none;
    font-family: "Poppins", sans-serif;
    font-size: 0.8rem;
    font-weight: 500;
    color: #2e4836;
    height: 100%;
    padding: 0 10px 0 32px;
    min-width: 120px;
}
.fca-va-search-field .fca-va-input {
    width: 190px;
    min-width: 170px;
}
.fca-va-field select.fca-va-input {
    min-width: 150px;
}
.fca-va-reset-btn,
.fca-va-export-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    height: 38px;
    border-radius: 9px;
    padding: 0 14px;
    font-size: 0.8rem;
    font-weight: 600;
    font-family: "Poppins", sans-serif;
    cursor: pointer;
    transition: 0.2s ease;
    white-space: nowrap;
}
.fca-va-reset-btn {
    background: #ffffff;
    color: #2e4836;
    border: 1px solid #cdd9b5;
}
.fca-va-reset-btn:hover {
    background: #eceed9;
}
.fca-va-export-btn {
    background: #2e4836;
    color: #ffffff;
    border: 1px solid #2e4836;
}
.fca-va-export-btn:hover {
    background: #3c6e4f;
}
.fca-va-bus-badge {
    display: inline-block;
    background: #eceed9;
    color: #2e4836;
    border: 1px solid #cdd9b5;
    border-radius: 20px;
    padding: 3px 11px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}
.fca-va-pagination {
    justify-content: flex-end;
}
.fca-va-page-indicator {
    min-width: 32px;
    padding: 5px 10px;
    text-align: center;
    background: #2e4836;
    color: #ffffff;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
}
.fca-va-footer {
    margin-top: 6px;
    padding: 14px 2px 2px;
    border-top: 1px solid #e3eabb;
    text-align: center;
}
.fca-va-footer p {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 600;
    color: #2e4836;
}
.fca-va-footer small {
    font-size: 0.73rem;
    font-weight: 500;
    color: #8a9a8a;
    display: block;
    margin-top: 2px;
}

@media (max-width: 1199px) {
    .fca-chart-card {
        flex: 1 1 100%;
    }
    .fca-panel-recent {
        flex: 1 1 100%;
    }
}
@media (max-width: 900px) {
    .fca-kpi {
        flex: 1 1 45%;
    }
}
@media (max-width: 640px) {
    .fca-kpi {
        flex: 1 1 100%;
    }
    .fca-chart-box {
        height: 190px;
    }
    #fcaBarBox,
    #fcaCompBox {
        height: auto;
    }
}

#fcaOtpModal .fca-otp-input {
    width: 100%;
    box-sizing: border-box;
    padding: 14px 16px;
    font-size: 24px;
    font-weight: 800;
    text-align: center;
    letter-spacing: 10px;
    border: 2px solid #cdd9b5;
    border-radius: 12px;
    background: #fef8f3;
    color: #2e4836;
    outline: none;
    font-family: "Poppins", sans-serif;
}
#fcaOtpModal .fca-otp-input:focus {
    border-color: #2e4836;
}
#fcaOtpModal .fca-otp-verify {
    width: 100%;
    background: #2e4836;
    color: #ffffff;
    border: none;
    border-radius: 12px;
    padding: 14px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 16px;
    font-family: "Poppins", sans-serif;
}
#fcaOtpModal .fca-otp-verify:hover {
    background: #1f3427;
}
#fcaOtpModal .fca-otp-resend {
    width: 100%;
    background: #f9f4da;
    color: #2e4836;
    border: 2px solid #e3eabb;
    border-radius: 12px;
    padding: 12px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 10px;
    font-family: "Poppins", sans-serif;
}
</style>

<?php require_once __DIR__ . '/../layout/navbarfinance.php'; ?>

<div class="main-content fca-wrap" id="mainContent">
<div class="fca-card" id="fcaAnalyticsPanel">

    <div class="fca-filter-loading" id="fcaFilterLoading" hidden role="status" aria-live="polite" aria-atomic="true">
        <div class="fca-filter-loading-card">
            <div class="fca-filter-spinner" aria-hidden="true"></div>
            <div class="fca-filter-loading-title">Updating fuel analytics…</div>
            <p class="fca-filter-loading-sub">Preparing KPIs, charts, receipts, and transactions for your filters.</p>
        </div>
    </div>

    <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>

    
    <div class="fca-header">
        <div>
            <h1 class="fca-title">Fuel &amp; Trip Cost Analytics</h1>
            <p class="fca-sub">Real-time fuel expense monitoring</p>
        </div>
    </div>

    <div class="fca-toolbar">
        <div class="fca-toolbar-actions">
            <a id="fcaPdfLink" class="fca-btn-export-pdf" href="?action=export_pdf">
                <i class="bi bi-file-earmark-arrow-down"></i> Export PDF
            </a>
            <a id="fcaExcelLink" class="fca-btn-export-excel" href="?action=export_excel">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </a>
        </div>
        <?php require __DIR__ . '/../layout/_period_filter.php'; ?>
    </div>

    
    <div class="fca-kpi-row" id="fcaKpiRow">
        <?php $sum = $summary ?? ['total_estimated_cost' => 0, 'avg_fuel_consumed' => 0, 'avg_price' => 65.55, 'top_bus' => null]; ?>
        <div class="fca-kpi">
            <div class="fca-kpi-icon" style="background:#f4faf5;color:#2e7d32;"><i class="bi bi-cash-stack"></i></div>
            <div style="min-width:0;">
                <p class="fca-kpi-label">Total Estimated Fuel Cost</p>
                <h3 class="fca-kpi-value" id="fcaKpiTotal">₱<?= number_format((float) ($sum['total_estimated_cost'] ?? ($sum['total_cost'] ?? 0)), 2) ?></h3>
                <p class="fca-kpi-sub" id="fcaKpiSub"><?= htmlspecialchars($periodLabel ?? 'Selected Period') ?></p>
            </div>
        </div>
        <div class="fca-kpi" style="background:#fffdfa;border:1.5px solid #cdd9b5;">
            <div class="fca-kpi-icon" style="background:#fef3c7;color:#b45309;"><i class="bi bi-fuel-pump-fill"></i></div>
            <div style="min-width:0;">
                <p class="fca-kpi-label" style="color:#295738;font-weight:700;">Average Fuel Consumption</p>
                <h3 class="fca-kpi-value" id="fcaKpiAvg" style="color:#1b5e20;"><?= ($sum['avg_kpl'] ?? 0) > 0 ? number_format((float) $sum['avg_kpl'], 2) . ' km/L' : 'N/A' ?></h3>
                <p class="fca-kpi-sub" id="fcaKpiAvgSub"><?= ($sum['avg_kpl'] ?? 0) > 0 ? number_format((float) ($sum['total_distance'] ?? 0), 1) . ' km ÷ ' . number_format((float) ($sum['total_fuel_consumed'] ?? 0), 2) . ' L' : 'ODO distance / consumed liters' ?></p>
            </div>
        </div>
        <div class="fca-kpi">
            <div class="fca-kpi-icon" style="background:#e3f2fd;color:#1565c0;"><i class="bi bi-bus-front"></i></div>
            <div style="min-width:0;">
                <p class="fca-kpi-label">Highest Fuel Cost Bus</p>
                <h3 class="fca-kpi-value" id="fcaKpiBus"><?= htmlspecialchars($sum['top_bus']['bus'] ?? 'N/A') ?></h3>
                <p class="fca-kpi-sub" id="fcaKpiBusSub"><?= $sum['top_bus'] ? '₱' . number_format((float) $sum['top_bus']['total'], 2) . ' · ' . htmlspecialchars($periodLabel ?? 'Selected Period') : htmlspecialchars($periodLabel ?? 'Selected Period') ?></p>
            </div>
        </div>
        <div class="fca-kpi">
            <div class="fca-kpi-icon" style="background:#f3f2f7;color:#5b3fa8;"><i class="bi bi-droplet-half"></i></div>
            <div style="min-width:0;">
                <p class="fca-kpi-label">Diesel Price / Liter</p>
                <h3 class="fca-kpi-value" id="fcaKpiPrice">₱<?= number_format((float) ($sum['avg_price'] ?? 65.55), 2) ?> / L</h3>
                <p class="fca-kpi-sub">Current Fuel Rate</p>
            </div>
        </div>
    </div>

    
    <div class="fca-charts-row">
        
        <div class="fca-chart-card" style="flex: 2 1 600px;">
            <div class="fca-chart-head">
                <div>
                    <h3 class="fca-chart-title"><i class="bi bi-graph-up-arrow me-1 text-success"></i> Estimated Fuel Cost Trend</h3>
                    <small style="color:#8a9a8a;font-size:0.72rem;">Temporal trend of estimated fuel cost (₱) based on trip distance, KPL, and diesel price</small>
                </div>
                <span class="fca-chart-period" data-fca-period>&mdash;</span>
            </div>
            <div class="fca-chart-box" id="fcaLineBox"><div class="fca-empty">Loading chart…</div></div>
        </div>

        
        <div class="fca-panel-recent" style="flex: 1 1 300px;">
            <div class="fca-panel-head">
                <h3 class="fca-panel-title">Recent Fuel Receipts</h3>
                <a class="fca-view-all" id="fcaViewAllLink">View All</a>
            </div>
            <div id="fcaRecentList">
                <?php if (!empty($recent)): foreach (array_slice($recent, 0, 1) as $r): ?>
                    <div class="fca-receipt-slip">
                        <div class="fca-watermark">FOR PREVIEW ONLY</div>
                        <p class="fca-receipt-shop">RIZAL PUBLIC MARKET VENDORS AND FARMERS<br>MULTI PURPOSE COOPERATIVE GAS STATION</p>
                        <p class="fca-receipt-sub">FUEL RECEIPT</p>
                        <p class="fca-receipt-date"><?= date('M d, Y', strtotime($r['created_at'])) ?> · <?= date('h:i A', strtotime($r['created_at'])) ?></p>
                        <div class="fca-receipt-dash"></div>
                        <p class="fca-receipt-row"><span>RECEIPT NO</span><span><?= htmlspecialchars($r['receipt_number'] ?? '—') ?></span></p>
                        <p class="fca-receipt-row"><span>BUS</span><span><?= htmlspecialchars($r['bus'] ?? $r['bus_id'] ?? '—') ?><?= !empty($r['plate_number']) ? ' · ' . htmlspecialchars($r['plate_number']) : '' ?></span></p>
                        <p class="fca-receipt-row"><span>DRIVER</span><span><?= htmlspecialchars($r['driver_name'] ?? $r['driver_id'] ?? '—') ?><?= !empty($r['driver_code']) && $r['driver_code'] !== ($r['driver_name'] ?? '') ? ' (' . htmlspecialchars($r['driver_code']) . ')' : '' ?></span></p>
                        <div class="fca-receipt-dash"></div>
                        <p class="fca-receipt-row"><span>Liters</span><span><?= number_format((float) ($r['consumed_liters'] ?? $r['liters']), 2) ?> L</span></p>
                        <p class="fca-receipt-row"><span>Price / Liter</span><span>₱<?= number_format((float) ($r['effective_price'] ?? $r['price_per_liter']), 2) ?></span></p>
                        <div class="fca-receipt-dash"></div>
                        <p class="fca-receipt-row fca-receipt-total"><span>TOTAL</span><span>₱<?= number_format((float) ($r['cost'] > 0 ? $r['cost'] : $r['estimated_cost']), 2) ?></span></p>
                        <div class="fca-receipt-dash"></div>
                        <p class="fca-receipt-note">This serves as proof of fuel purchase.</p>
                        <p class="fca-receipt-note">Thank you</p>
                        <div class="fca-receipt-dash"></div>
                        <div class="fca-receipt-sign">
                            <div class="fca-sign-line"></div>
                            <p class="fca-sign-label">AUTHORIZED BY: <?= htmlspecialchars($signerName ?? 'Finance Staff') ?></p>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <p class="fca-receipt-empty">No recent fuel records.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    
    <div class="fca-charts-row" style="margin-top: 14px;">
        
        <div class="fca-chart-card" style="flex: 1 1 450px; align-self: flex-start;">
            <div class="fca-chart-head">
                <div>
                    <h3 class="fca-chart-title"><i class="bi bi-bar-chart-fill me-1 text-success"></i> Estimated Fuel Cost by Bus</h3>
                    <small style="color:#8a9a8a;font-size:0.72rem;">Total estimated fuel cost per bus (₱), sorted descending</small>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <select id="fcaBarTop" class="fca-period-select" title="Number of buses to show">
                        <option value="10">Top 10</option>
                        <option value="20" selected>Top 20</option>
                        <option value="all">All Buses</option>
                    </select>
                    <span class="fca-chart-period" data-fca-period>&mdash;</span>
                </div>
            </div>
            <div class="fca-chart-box" id="fcaBarBox"><div class="fca-empty">Loading chart…</div></div>
        </div>

        
        <div class="fca-chart-card" style="flex: 1 1 450px; align-self: flex-start;">
            <div class="fca-chart-head">
                <div>
                    <h3 class="fca-chart-title"><i class="bi bi-arrow-left-right me-1 text-success"></i> Estimated vs Actual Fuel Cost</h3>
                    <small style="color:#8a9a8a;font-size:0.72rem;">Estimated consumption cost vs recorded refuel cost per bus</small>
                </div>
                
                <div class="d-flex align-items-center gap-2" style="font-size:0.72rem;">
                    <span style="display:inline-flex;align-items:center;gap:4px;">
                        <span style="width:10px;height:10px;background:#2e4836;border-radius:2px;display:inline-block;"></span>
                        <span style="color:#2e4836;font-weight:600;">Estimated</span>
                    </span>
                    <span style="display:inline-flex;align-items:center;gap:4px;">
                        <span style="width:10px;height:10px;background:#c48b00;border-radius:2px;display:inline-block;"></span>
                        <span style="color:#c48b00;font-weight:600;">Actual</span>
                    </span>
                </div>
                <span class="fca-chart-period" data-fca-period>&mdash;</span>
            </div>
            <div class="fca-chart-box" id="fcaCompBox"><div class="fca-empty">Loading chart…</div></div>
        </div>
    </div>

    
    <div class="fca-sec-stats" id="fcaSecStats">
        <span class="fca-sec-stat">Total Fuel Consumed: <b id="fcaStatLiters"><?= number_format((float) ($summary['total_fuel_consumed'] ?? 0), 2) ?> L</b></span>
        <span class="fca-sec-stat">Valid Trips Recorded: <b id="fcaStatTrips"><?= (int) ($summary['valid_trips_count'] ?? 0) ?></b></span>
        <span class="fca-sec-stat">Total Estimated Fuel Cost: <b id="fcaStatEstCost">₱<?= number_format((float) ($summary['total_estimated_cost'] ?? 0), 2) ?></b></span>
        <span class="fca-sec-stat">Total Actual Refuels: <b id="fcaStatActCost">₱<?= number_format((float) ($summary['total_actual_cost'] ?? 0), 2) ?></b></span>
    </div>

    
    <div class="fca-filter-bar">
        <div>
            <label class="fca-filter-label" for="fcaBusFilter">Bus</label>
            <select id="fcaBusFilter" class="fca-select">
                <option value="">All Buses</option>
                <?php foreach (($buses ?? []) as $b): ?>
                    <option value="<?= htmlspecialchars($b['bus_id']) ?>"><?= htmlspecialchars(fca_bus_label($b['bus_id'], $b['bus_label'] ?? null)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="fca-filter-label" for="fcaDriverFilter">Driver</label>
            <select id="fcaDriverFilter" class="fca-select">
                <option value="">All Drivers</option>
                <?php foreach (($drivers ?? []) as $d): ?>
                    <option value="<?= htmlspecialchars($d['driver_id']) ?>"><?= htmlspecialchars($d['full_name'] ?? $d['driver_id']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="fca-filter-label" for="fcaSearch">Search</label>
            <input type="text" id="fcaSearch" class="fca-input" placeholder="Search receipt number...">
        </div>
        <button type="button" id="fcaClearBtn" class="fca-clear-btn"><i class="bi bi-x-circle me-1"></i>Clear Filters</button>
    </div>

    
    <div class="fca-card" style="margin-bottom:18px;">
        <h3 class="fca-chart-title" style="margin-bottom:14px;"><i class="bi bi-receipt me-1"></i> Fuel Transactions</h3>
        <div class="fca-table-wrap">
            <table class="fca-table">
                <thead>
                    <tr>
                        <th>Receipt #</th><th>Date &amp; Time</th><th>Bus</th><th>Driver</th>
                        <th class="fca-num">Liters</th><th class="fca-num">Price / Liter</th><th class="fca-num">Total Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="fcaTableBody">
                    <?php if (!empty($initialRows)): foreach ($initialRows as $r): ?>
                        <tr data-id="<?= (int) $r['id'] ?>">
                            <td class="fca-receipt-cell"><?= htmlspecialchars($r['receipt_number'] ?? '') ?></td>
                            <td class="fca-date-cell"><?= date('M d, Y', strtotime($r['created_at'])) ?><div class="fca-time-cell"><?= date('h:i A', strtotime($r['created_at'])) ?></div></td>
                            <td><?= htmlspecialchars($r['bus'] ?? $r['bus_id'] ?? '—') ?></td>
                            <td><div class="fca-drv-name"><?= htmlspecialchars($r['driver_name'] ?? $r['driver_id'] ?? '—') ?></div>
                                <?php if (!empty($r['driver_code']) && $r['driver_code'] !== $r['driver_name']): ?><div class="fca-drv-id"><?= htmlspecialchars($r['driver_code']) ?></div><?php endif; ?>
                            </td>
                            <td class="fca-num"><?= number_format((float) $r['liters'], 2) ?> L</td>
                            <td class="fca-num">₱<?= number_format((float) $r['price_per_liter'], 2) ?></td>
                            <td class="fca-num fca-cost-cell">₱<?= number_format((float) $r['cost'], 2) ?></td>
                            <td><button class="fca-view-btn" data-id="<?= (int) $r['id'] ?>">View</button></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8" class="fca-table-empty">No fuel records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="fca-pagination" id="fcaPagination"></div>
    </div>

</div>
</div>

<?php require __DIR__ . '/_aibutton.php'; ?>


<div class="modal fade" id="fcaViewAllModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px;border:1px solid #cdd9b5;font-family:'Poppins',sans-serif;">
            <div class="modal-header" style="border-bottom:1px solid #e3eabb;background:#f9f4da;padding:18px 24px;align-items:center;">
                <div class="fca-va-header">
                    <div class="fca-va-icon"><i class="bi bi-fuel-pump"></i></div>
                    <div>
                        <h5 class="modal-title">All Fuel Receipts</h5>
                        <small>View and manage all recorded fuel receipts.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:18px 24px;">
                <div class="fca-va-filter-row">
                    <div class="fca-va-field fca-va-search-field">
                        <i class="bi bi-search"></i>
                        <input type="text" id="fcaVaSearch" class="fca-va-input" placeholder="Search receipt number...">
                    </div>
                    <div class="fca-va-field">
                        <i class="bi bi-bus-front"></i>
                        <select id="fcaVaBus" class="fca-va-input"><option value="">All Buses</option>
                            <?php foreach (($buses ?? []) as $b): ?><option value="<?= htmlspecialchars($b['bus_id']) ?>"><?= htmlspecialchars(fca_bus_label($b['bus_id'], $b['bus_label'] ?? null)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fca-va-field">
                        <i class="bi bi-person"></i>
                        <select id="fcaVaDriver" class="fca-va-input"><option value="">All Drivers</option>
                            <?php foreach (($drivers ?? []) as $d): ?><option value="<?= htmlspecialchars($d['driver_id']) ?>"><?= htmlspecialchars($d['full_name'] ?? $d['driver_id']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fca-va-field">
                        <i class="bi bi-calendar"></i>
                        <input type="date" id="fcaVaFrom" class="fca-va-input" placeholder="mm/dd/yyyy">
                    </div>
                    <div class="fca-va-field">
                        <i class="bi bi-calendar"></i>
                        <input type="date" id="fcaVaTo" class="fca-va-input" placeholder="mm/dd/yyyy">
                    </div>
                    <button type="button" class="fca-va-reset-btn" id="fcaVaReset"><i class="bi bi-arrow-clockwise"></i>Reset</button>
                    <button type="button" class="fca-va-export-btn" id="fcaVaExportExcel"><i class="bi bi-file-earmark-excel"></i>Export Excel</button>
                </div>
                <div class="fca-table-wrap">
                    <table class="fca-table">
                        <thead><tr>
                            <th>Receipt #</th><th>Date &amp; Time</th><th>Bus</th><th>Driver</th>
                            <th class="fca-num">Liters</th><th class="fca-num">Price / Liter</th><th class="fca-num">Total Cost</th>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody id="fcaVaBody"></tbody>
                    </table>
                </div>
                <div class="fca-pagination fca-va-pagination" id="fcaVaPagination"></div>
                <div class="fca-va-footer">
                    <p>Manage fuel records with ease</p>
                    <small>Keep track of all fuel purchases and expenses for accurate reporting.</small>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="fcaReceiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="width:fit-content;min-width:0;max-width:90vw;height:90vh;max-height:90vh;min-height:0;margin:5vh auto;">
        <div class="modal-content" style="border-radius:16px;border:1px solid #cdd9b5;font-family:'Poppins',sans-serif;display:flex;flex-direction:column;overflow:hidden;width:fit-content;max-width:100%;height:100%;">
            <div class="modal-header" style="border-bottom:1px solid #e3eabb;background:#f9f4da;flex-shrink:0;">
                <h5 class="modal-title" style="font-weight:700;color:#2e4836;">Fuel Receipt Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="display:flex;justify-content:center;background:#eceed9;overflow-y:auto;flex:1 1 auto;min-height:0;padding:0;">
                <div class="fca-preview-paper">
                    <div class="fca-watermark">FOR PREVIEW ONLY</div>
                    <div class="fca-print-slip" id="fcaPrintReceipt"></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e3eabb;justify-content:center;flex-shrink:0;">
                <button type="button" class="fca-receipt-action-btn" id="fcaPrintBtn"><i class="bi bi-printer me-1"></i>Print</button>
                <button type="button" class="fca-receipt-close-btn" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="fcaOtpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content" style="border-radius:24px;border:2px solid #e3eabb;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,0.08);font-family:'Poppins',sans-serif;">
            <div class="modal-header" style="background:#2e4836;padding:24px;border-bottom:none;align-items:flex-start;">
                <div>
                    <h5 class="modal-title" style="margin:0;font-size:20px;font-weight:700;color:#ffffff;">Verify OTP</h5>
                    <small style="color:#d7e2d3;font-size:12px;">Print validation for Fuel Receipt</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <div id="fcaOtpMsg" style="display:none;border-radius:12px;padding:12px 14px;font-size:13px;font-weight:600;margin-bottom:16px;"></div>
                <div style="font-size:12px;color:#6b7280;margin-bottom:6px;font-weight:600;">ONE-TIME PASSWORD</div>
                <p style="color:#6b7280;font-size:13px;line-height:1.7;margin:0 0 14px;">A 6-digit OTP has been sent to your registered email. Enter it below to print the receipt. The code expires in 5 minutes.</p>
                <input type="text" id="fcaOtpInput" class="fca-otp-input" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;">
                <button type="button" class="fca-otp-verify" id="fcaOtpVerifyBtn"><i class="bi bi-printer me-1"></i>Verify &amp; Print</button>
                <button type="button" class="fca-otp-resend" id="fcaOtpSendBtn"><i class="bi bi-envelope me-1"></i>Send OTP / Resend OTP</button>
            </div>
        </div>
    </div>
</div>


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

<script src="/public/login/custom-alert.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/finance/assets/js/financeperiodlabel.js?v=1"></script>

<script>
(function () {
    const ALL = <?= json_encode($allRows ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const TX = <?= json_encode($transactions ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const FALLBACK_PRICE = <?= json_encode(round((float) ($summary['avg_price'] ?? 65.55), 2)) ?>;
    const BUSE = <?= json_encode($buses ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const DRVE = <?= json_encode($drivers ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const FCA_SIGNER = <?= json_encode($signerName ?? 'Finance Staff', JSON_UNESCAPED_UNICODE) ?>;

    const $ = (id) => document.getElementById(id);
    const esc = (s) =>
        String(s ?? "").replace(
            /[&<>"']/g,
            (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c],
        );
    const busLabel = (busId, label) => {
        let s = String(label || busId || "")
            .replace(/\s+/g, " ")
            .trim();
        if (!s) return "Unassigned Bus";
        s = s.replace(/^Bus\s*#?\s*[-: ]*/i, "");
        if (!s) return "Unassigned Bus";
        s = s.replace(/^BUS/, "Bus");
        if (/\bBus\b/i.test(s)) return s;
        return "Bus-" + s;
    };
    const fmtNum = (n) =>
        "₱" +
        Number(n || 0).toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    const fmtLit = (n) =>
        Number(n || 0).toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }) + " L";
    const dayOf = (dt) => (dt || "").slice(0, 10);
    const parseDt = (dt) => {
        if (!dt) return null;
        const d = new Date(String(dt).replace(" ", "T"));
        return isNaN(d.getTime()) ? null : d;
    };
    const FIN_CONFIG = (() => {
        const node = document.querySelector("[data-fin-config]");
        if (!node) return { selected: "monthly", presets: {}, options: [], start: "", end: "" };
        try {
            return JSON.parse(node.textContent || "{}");
        } catch (e) {
            return { selected: "monthly", presets: {}, options: [], start: "", end: "" };
        }
    })();
    const periodLabels = {};
    (FIN_CONFIG.options || []).forEach((option) => {
        periodLabels[option.value] = option.label;
    });
    const periodText = () => periodLabels[state.period] || "";

    function compactPeriodLabel() {
        if (window.FinancePeriodLabel) {
            const text = window.FinancePeriodLabel.period(state.period, state.from, state.to);
            if (text) return text;
        }
        return periodText();
    }

    function updatePeriodLabels() {
        const text = compactPeriodLabel() || "—";
        document.querySelectorAll("[data-fca-period]").forEach((el) => {
            el.textContent = text;
        });
    }
    const fmtDate = (dt) => {
        const d = parseDt(dt);
        return !d
            ? "—"
            : d.toLocaleDateString("en-US", { month: "short", day: "2-digit", year: "numeric" });
    };
    const fmtTime = (dt) => {
        const d = parseDt(dt);
        return !d
            ? "—"
            : d.toLocaleTimeString("en-US", { hour: "2-digit", minute: "2-digit", hour12: true });
    };

    const P = new URLSearchParams(window.location.search);
    const state = {
        bus: P.get("bus") || "",
        driver: P.get("driver") || "",
        search: (P.get("search") || "").toLowerCase(),
        period: FIN_CONFIG.selected || "monthly",
        from: FIN_CONFIG.start || "",
        to: FIN_CONFIG.end || "",
        page: 1,
        perPage: 10,
    };

    function filtered() {
        const { bus, driver, search, from, to } = state;
        return ALL.filter((r) => {
            if (bus && String(r.bus_id ?? "") !== String(bus)) return false;
            if (driver && (r.driver_code || r.driver_id) !== driver) return false;
            if (
                search &&
                !String(r.receipt_number || "")
                    .toLowerCase()
                    .includes(search)
            )
                return false;
            if (from && dayOf(r.created_at) < from) return false;
            if (to && dayOf(r.created_at) > to) return false;
            return true;
        });
    }

    function filteredTx() {
        const { bus, driver, search, from, to } = state;
        return TX.filter((r) => {
            if (bus && String(r.bus_id ?? "") !== String(bus)) return false;
            if (driver && (r.driver_code || r.driver_id) !== driver) return false;
            if (
                search &&
                !String(r.receipt_number || "")
                    .toLowerCase()
                    .includes(search)
            )
                return false;
            if (from && dayOf(r.created_at) < from) return false;
            if (to && dayOf(r.created_at) > to) return false;
            return true;
        });
    }

    function periodPrice() {
        if (FALLBACK_PRICE > 0) {
            return FALLBACK_PRICE;
        }
        let liters = 0,
            cost = 0;
        filteredTx().forEach((r) => {
            liters += Number(r.liters || 0);
            cost += Number(r.cost || 0);
        });
        return liters > 0 ? cost / liters : 65.55;
    }

    function repriced(list) {
        const price = periodPrice();
        return list.map((r) => {
            if (Number(r.liters || 0) > 0) {
                return r;
            }
            return Object.assign({}, r, {
                estimated_cost: Number(r.consumed_liters || 0) * price,
                effective_price: price,
            });
        });
    }

    function renderSummary(list) {
        let validTripsCount = 0;
        let totalConsumedLiters = 0;
        let totalEstimatedCost = 0;
        let totalDistance = 0;
        const byBusCost = {};

        list.forEach((r) => {
            if (!r.is_valid_trip) return;
            validTripsCount += 1;
            const cLit = Number(r.consumed_liters || 0);
            const eCost = Number(r.estimated_cost || 0);
            totalConsumedLiters += cLit;
            totalEstimatedCost += eCost;
            totalDistance += Number(r.distance || 0);

            const k = r.bus || busLabel(r.bus_id);
            byBusCost[k] = (byBusCost[k] || 0) + eCost;
        });

        const avgKpl = totalConsumedLiters > 0 && totalDistance > 0 ? totalDistance / totalConsumedLiters : 0;
        const dieselPrice = FALLBACK_PRICE > 0 ? FALLBACK_PRICE : Number(list[0]?.effective_price || 65.55);

        let top = null;
        for (const k in byBusCost) {
            if (!top || byBusCost[k] > top.total) {
                top = { bus: k, total: byBusCost[k] };
            }
        }

        $("fcaKpiTotal").textContent = fmtNum(totalEstimatedCost);
        $("fcaKpiAvg").textContent = avgKpl > 0 ? avgKpl.toFixed(2) + " km/L" : "N/A";
        const avgSub = $("fcaKpiAvgSub");
        if (avgSub) {
            avgSub.textContent =
                avgKpl > 0
                    ? totalDistance.toFixed(1) + " km ÷ " + totalConsumedLiters.toFixed(2) + " L"
                    : "ODO distance / consumed liters";
        }
        $("fcaKpiBus").textContent = top ? top.bus : "N/A";
        $("fcaKpiBusSub").textContent = top
            ? fmtNum(top.total) + " · " + periodText()
            : periodText();
        $("fcaKpiPrice").textContent = fmtNum(dieselPrice) + " / L";
        $("fcaKpiSub").textContent = periodText();
    }

    function renderSecStats(list) {
        let validTripsCount = 0;
        let totalConsumedLiters = 0;
        let totalEstimatedCost = 0;
        let totalActualCost = 0;

        list.forEach((r) => {
            if (!r.is_valid_trip) return;
            validTripsCount += 1;
            totalConsumedLiters += Number(r.consumed_liters || 0);
            totalEstimatedCost += Number(r.estimated_cost || 0);
        });
        filteredTx().forEach((r) => {
            totalActualCost += Number(r.cost || 0);
        });

        $("fcaStatLiters").textContent = fmtLit(totalConsumedLiters);
        const tripsEl = $("fcaStatTrips");
        if (tripsEl) tripsEl.textContent = validTripsCount;
        const estEl = $("fcaStatEstCost");
        if (estEl) estEl.textContent = fmtNum(totalEstimatedCost);
        const actEl = $("fcaStatActCost");
        if (actEl) actEl.textContent = fmtNum(totalActualCost);
    }

    let fcaBarTop = "20";

    function renderBar(list) {
        const box = $("fcaBarBox");
        if (!box) return;
        const byBus = {};
        list.forEach((r) => {
            if (!r.is_valid_trip) return;
            const k = r.bus || busLabel(r.bus_id);
            if (!byBus[k])
                byBus[k] = {
                    bus: k,
                    bus_id: r.bus_id,
                    total: 0,
                    liters: 0,
                    distance: 0,
                    actual: 0,
                    trips: 0,
                };
            byBus[k].total += Number(r.estimated_cost || 0);
            byBus[k].liters += Number(r.consumed_liters || 0);
            byBus[k].distance += Number(r.distance || 0);
            byBus[k].trips += 1;
        });
        filteredTx().forEach((r) => {
            const k = r.bus || busLabel(r.bus_id);
            if (byBus[k]) byBus[k].actual += Number(r.cost || 0);
        });
        let rows = Object.values(byBus).sort((a, b) => b.total - a.total);
        if (fcaBarTop !== "all") rows = rows.slice(0, parseInt(fcaBarTop, 10) || 20);
        if (!rows.length) {
            box.style.maxHeight = "";
            box.innerHTML =
                '<div class="fca-empty">No estimated fuel cost data available for this period.</div>';
            return;
        }

        const W = box.clientWidth || 480;
        const padL = 72,
            padR = 88,
            padT = 16,
            padB = 22;
        const rowH = 34,
            barH = 20;
        const H = padT + rows.length * rowH + padB;
        const plotW = W - padL - padR;
        const max = Math.max(...rows.map((r) => r.total)) * 1.1 || 1;

        let svg = '<svg width="100%" height="' + H + '" viewBox="0 0 ' + W + " " + H + '">';
        [0, 0.25, 0.5, 0.75, 1].forEach((g) => {
            const x = padL + plotW * g;
            svg +=
                '<line x1="' +
                x +
                '" y1="' +
                padT +
                '" x2="' +
                x +
                '" y2="' +
                (H - padB) +
                '" stroke="#e3eabb" stroke-width="1"/>';
            svg +=
                '<text x="' +
                x +
                '" y="' +
                (padT - 4) +
                '" text-anchor="middle" class="fca-axis">₱' +
                Math.round(max * g).toLocaleString() +
                "</text>";
        });
        rows.forEach((row, i) => {
            const y = padT + i * rowH;
            const by = y + (rowH - barH) / 2;
            const bw = Math.max((row.total / max) * plotW, 2);
            const tip =
                esc(row.bus) +
                " · Estimated Fuel Cost: " +
                fmtNum(row.total) +
                " · Fuel Consumed: " +
                fmtLit(row.liters) +
                (row.distance > 0
                    ? " · Distance: " + Number(row.distance).toFixed(1) + " km"
                    : "") +
                " · Valid Trips: " +
                row.trips +
                (row.actual > 0 ? " · Actual Refuels: " + fmtNum(row.actual) : "") +
                " · Period: " +
                periodText();
            svg +=
                '<rect class="fca-bar" x="' +
                padL +
                '" y="' +
                by +
                '" width="' +
                bw +
                '" height="' +
                barH +
                '" rx="4" fill="#2e4836" data-tip="' +
                tip +
                '" data-bus="' +
                esc(row.bus_id || row.bus) +
                '" style="cursor:pointer"/>';
            svg +=
                '<text x="' +
                (padL - 8) +
                '" y="' +
                (by + barH / 2 + 4) +
                '" text-anchor="end" class="fca-bar-label">' +
                esc(row.bus) +
                "</text>";
            svg +=
                '<text x="' +
                (padL + bw + 8) +
                '" y="' +
                (by + barH / 2 + 4) +
                '" text-anchor="start" class="fca-axis">₱' +
                Math.round(row.total).toLocaleString() +
                "</text>";
        });
        svg += "</svg>";
        box.innerHTML = svg;
    }

    function renderLine(list) {
        const box = $("fcaLineBox");
        if (!box) return;
        const byDay = {};
        list.forEach((r) => {
            if (!r.is_valid_trip) return;
            const d = dayOf(r.created_at);
            if (!d) return;
            byDay[d] = byDay[d] || { cost: 0, liters: 0, actual: 0, trips: 0 };
            byDay[d].cost += Number(r.estimated_cost || 0);
            byDay[d].liters += Number(r.consumed_liters || 0);
            byDay[d].trips += 1;
        });
        filteredTx().forEach((r) => {
            const d = dayOf(r.created_at);
            if (!d) return;
            byDay[d] = byDay[d] || { cost: 0, liters: 0, actual: 0, trips: 0 };
            byDay[d].actual += Number(r.cost || 0);
        });
        const days = Object.entries(byDay).sort((a, b) => (a[0] < b[0] ? -1 : 1));
        if (!days.length) {
            box.innerHTML = '<div class="fca-empty">No daily estimated fuel data available.</div>';
            return;
        }

        const W = box.clientWidth || 480;
        const H = Math.max(box.clientHeight || 0, 400);
        const padL = 56,
            padR = 12,
            padT = 18,
            padB = 30;
        const plotW = W - padL - padR,
            plotH = H - padT - padB;
        const max = Math.max(...days.map((d) => d[1].cost)) * 1.15 || 1;
        const stepX = days.length > 1 ? plotW / (days.length - 1) : 0;
        const pts = days.map((d, i) => {
            const x = padL + i * stepX;
            const y = padT + plotH - (d[1].cost / max) * plotH;
            return {
                x,
                y,
                day: d[0],
                cost: d[1].cost,
                liters: d[1].liters,
                actual: d[1].actual,
                trips: d[1].trips,
            };
        });
        let svg =
            '<svg width="100%" height="100%" viewBox="0 0 ' +
            W +
            " " +
            H +
            '" preserveAspectRatio="none">';
        [0, 0.25, 0.5, 0.75, 1].forEach((g) => {
            const y = padT + plotH * (1 - g);
            svg +=
                '<line x1="' +
                padL +
                '" y1="' +
                y +
                '" x2="' +
                (W - padR) +
                '" y2="' +
                y +
                '" stroke="#e3eabb" stroke-width="1"/>';
            svg +=
                '<text x="' +
                (padL - 6) +
                '" y="' +
                (y + 4) +
                '" text-anchor="end" class="fca-axis">₱' +
                Math.round(max * g).toLocaleString() +
                "</text>";
        });
        const line = pts
            .map((p, i) => (i ? "L" : "M") + p.x.toFixed(1) + " " + p.y.toFixed(1))
            .join(" ");
        const area =
            line +
            " L" +
            pts[pts.length - 1].x.toFixed(1) +
            " " +
            (padT + plotH) +
            " L" +
            pts[0].x.toFixed(1) +
            " " +
            (padT + plotH) +
            " Z";
        svg += '<path d="' + area + '" fill="rgba(46,72,54,0.08)" stroke="none"/>';
        svg +=
            '<path d="' +
            line +
            '" fill="none" stroke="#2e4836" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>';
        const labelStep = Math.ceil(days.length / 6);
        pts.forEach((p, i) => {
            const tip =
                fmtDate(p.day) +
                " · Estimated Cost: " +
                fmtNum(p.cost) +
                " · Fuel Consumed: " +
                fmtLit(p.liters) +
                " · Valid Trips: " +
                p.trips +
                (p.actual > 0 ? " · Actual Refuels: " + fmtNum(p.actual) : "");
            svg +=
                '<circle class="fca-dot" cx="' +
                p.x +
                '" cy="' +
                p.y +
                '" r="4" fill="#2e4836" data-tip="' +
                tip +
                '"/>';
            if (i % labelStep === 0) {
                svg +=
                    '<text x="' +
                    p.x +
                    '" y="' +
                    (padT + plotH + 16) +
                    '" text-anchor="middle" class="fca-axis">' +
                    fmtDate(p.day).slice(0, 6) +
                    "</text>";
            }
        });
        svg += "</svg>";
        box.innerHTML = svg;
    }

    function renderComparison(list) {
        const box = $("fcaCompBox");
        if (!box) return;
        const byBus = {};
        list.forEach((r) => {
            if (!r.is_valid_trip) return;
            const k = r.bus || busLabel(r.bus_id);
            if (!byBus[k])
                byBus[k] = {
                    bus: k,
                    bus_id: r.bus_id,
                    estimated_cost: 0,
                    actual_cost: 0,
                    trips: 0,
                    has_actual: false,
                };
            byBus[k].estimated_cost += Number(r.estimated_cost || 0);
            byBus[k].trips += 1;
        });
        filteredTx().forEach((r) => {
            const k = r.bus || busLabel(r.bus_id);
            if (!byBus[k])
                byBus[k] = {
                    bus: k,
                    bus_id: r.bus_id,
                    estimated_cost: 0,
                    actual_cost: 0,
                    trips: 0,
                    has_actual: false,
                };
            byBus[k].actual_cost += Number(r.cost || 0);
            byBus[k].has_actual = true;
        });

        let rows = Object.values(byBus).sort((a, b) => b.estimated_cost - a.estimated_cost);
        if (fcaBarTop !== "all") rows = rows.slice(0, parseInt(fcaBarTop, 10) || 20);

        if (!rows.length) {
            box.style.maxHeight = "";
            box.innerHTML =
                '<div class="fca-empty">No comparison data available for this period.</div>';
            return;
        }

        const W = box.clientWidth || 480;
        const padL = 72,
            padR = 88,
            padT = 20,
            padB = 22;
        const rowH = 38,
            barH = 11,
            barGap = 3;
        const H = padT + rows.length * rowH + padB;
        const plotW = W - padL - padR;
        const maxVal =
            Math.max(...rows.map((r) => Math.max(r.estimated_cost, r.actual_cost))) * 1.12 || 1;

        let svg = '<svg width="100%" height="' + H + '" viewBox="0 0 ' + W + " " + H + '">';
        [0, 0.25, 0.5, 0.75, 1].forEach((g) => {
            const x = padL + plotW * g;
            svg +=
                '<line x1="' +
                x +
                '" y1="' +
                padT +
                '" x2="' +
                x +
                '" y2="' +
                (H - padB) +
                '" stroke="#e3eabb" stroke-width="1"/>';
            svg +=
                '<text x="' +
                x +
                '" y="' +
                (padT - 5) +
                '" text-anchor="middle" class="fca-axis">₱' +
                Math.round(maxVal * g).toLocaleString() +
                "</text>";
        });

        rows.forEach((row, i) => {
            const y = padT + i * rowH;
            const by1 = y + (rowH - (barH * 2 + barGap)) / 2;
            const by2 = by1 + barH + barGap;
            const bw1 = Math.max((row.estimated_cost / maxVal) * plotW, 2);
            const bw2 = Math.max((row.actual_cost / maxVal) * plotW, row.actual_cost > 0 ? 2 : 0);

            const diff = row.actual_cost - row.estimated_cost;
            const diffSign = diff >= 0 ? "+" : "";
            const diffPct =
                row.estimated_cost > 0 ? Math.round((diff / row.estimated_cost) * 100) : 0;
            const diffStr = row.has_actual
                ? diffSign + fmtNum(diff) + " (" + diffSign + diffPct + "%)"
                : "No actual refuels logged";

            const tipEst =
                esc(row.bus) +
                " · Estimated: " +
                fmtNum(row.estimated_cost) +
                " · Trips: " +
                row.trips;
            const tipAct =
                esc(row.bus) +
                " · Actual: " +
                (row.has_actual ? fmtNum(row.actual_cost) : "None") +
                " · Variance: " +
                diffStr;

            svg +=
                '<rect class="fca-bar" x="' +
                padL +
                '" y="' +
                by1 +
                '" width="' +
                bw1 +
                '" height="' +
                barH +
                '" rx="3" fill="#2e4836" data-tip="' +
                tipEst +
                '" data-bus="' +
                esc(row.bus_id || row.bus) +
                '" style="cursor:pointer;"/>';
            if (row.actual_cost > 0) {
                svg +=
                    '<rect class="fca-bar" x="' +
                    padL +
                    '" y="' +
                    by2 +
                    '" width="' +
                    bw2 +
                    '" height="' +
                    barH +
                    '" rx="3" fill="#c48b00" data-tip="' +
                    tipAct +
                    '" data-bus="' +
                    esc(row.bus_id || row.bus) +
                    '" style="cursor:pointer;"/>';
            }

            svg +=
                '<text x="' +
                (padL - 8) +
                '" y="' +
                (by1 + barH + 1) +
                '" text-anchor="end" class="fca-bar-label">' +
                esc(row.bus) +
                "</text>";
            svg +=
                '<text x="' +
                (padL + Math.max(bw1, bw2) + 8) +
                '" y="' +
                (by1 + barH + 1) +
                '" text-anchor="start" class="fca-axis">₱' +
                Math.round(row.estimated_cost).toLocaleString() +
                "</text>";
        });
        svg += "</svg>";
        box.innerHTML = svg;
    }

    function slipHtml(r, cls) {
        const plate = r.plate_number ? " · " + esc(r.plate_number) : "";
        const drvName = r.driver_name || r.driver_id || "—";
        const drvCode =
            r.driver_code && r.driver_code !== drvName ? " (" + esc(r.driver_code) + ")" : "";
        return (
            '<div class="' +
            cls +
            '">' +
            (cls === "fca-receipt-slip"
                ? '<div class="fca-watermark">FOR PREVIEW ONLY</div>'
                : "") +
            '<p class="fca-receipt-shop">RIZAL PUBLIC MARKET VENDORS AND FARMERS<br>MULTI PURPOSE COOPERATIVE GAS STATION</p>' +
            '<p class="fca-receipt-sub">FUEL RECEIPT</p>' +
            '<p class="fca-receipt-date">' +
            fmtDate(r.created_at) +
            " · " +
            fmtTime(r.created_at) +
            "</p>" +
            '<div class="fca-receipt-dash"></div>' +
            '<p class="fca-receipt-row fca-info-row"><span>RECEIPT NO</span><span>' +
            esc(r.receipt_number || "—") +
            "</span></p>" +
            '<p class="fca-receipt-row fca-info-row"><span>DRIVER</span><span>' +
            esc(drvName) +
            drvCode +
            "</span></p>" +
            '<p class="fca-receipt-row fca-info-row"><span>BUS</span><span>' +
            esc(r.bus || busLabel(r.bus_id) || "—") +
            plate +
            "</span></p>" +
            '<div class="fca-receipt-dash"></div>' +
            '<p class="fca-receipt-row"><span>Liters</span><span>' +
            fmtLit(r.liters) +
            "</span></p>" +
            '<p class="fca-receipt-row"><span>Price / Liter</span><span>' +
            fmtNum(r.price_per_liter) +
            "</span></p>" +
            '<div class="fca-receipt-dash"></div>' +
            '<p class="fca-receipt-row fca-receipt-total"><span>TOTAL</span><span>' +
            fmtNum(r.cost) +
            "</span></p>" +
            '<div class="fca-receipt-dash"></div>' +
            '<p class="fca-receipt-note">This serves as proof of fuel purchase.</p>' +
            '<p class="fca-receipt-note">Thank you</p>' +
            '<div class="fca-receipt-dash"></div>' +
            '<div class="fca-receipt-sign">' +
            '<div class="fca-sign-line"></div>' +
            '<p class="fca-sign-label">AUTHORIZED BY: ' +
            esc(FCA_SIGNER) +
            "</p>" +
            "</div>" +
            "</div>"
        );
    }

    function renderRecent(list) {
        const box = $("fcaRecentList");
        const top = list.slice(0, 1);
        if (!top.length) {
            box.innerHTML = '<p class="fca-receipt-empty">No recent fuel records.</p>';
            return;
        }
        box.innerHTML = top.map((r) => slipHtml(r, "fca-receipt-slip")).join("");
    }

    function renderTable() {
        const list = filteredTx();
        const body = $("fcaTableBody");
        const pages = Math.max(1, Math.ceil(list.length / state.perPage));
        if (state.page > pages) state.page = pages;
        const start = (state.page - 1) * state.perPage;
        const slice = list.slice(start, start + state.perPage);

        if (!slice.length) {
            body.innerHTML =
                '<tr><td colspan="8" class="fca-table-empty">No fuel records found for the selected filters.</td></tr>';
        } else {
            body.innerHTML = slice
                .map(
                    (r) =>
                        '<tr data-id="' +
                        (r.id || "") +
                        '">' +
                        '<td class="fca-receipt-cell">' +
                        esc(r.receipt_number || "") +
                        "</td>" +
                        '<td class="fca-date-cell">' +
                        fmtDate(r.created_at) +
                        '<div class="fca-time-cell">' +
                        fmtTime(r.created_at) +
                        "</div></td>" +
                        "<td>" +
                        esc(r.bus || busLabel(r.bus_id) || "—") +
                        "</td>" +
                        '<td><div class="fca-drv-name">' +
                        esc(r.driver_name || r.driver_id || "—") +
                        "</div>" +
                        (r.driver_code && r.driver_code !== r.driver_name
                            ? '<div class="fca-drv-id">' + esc(r.driver_code) + "</div>"
                            : "") +
                        "</td>" +
                        '<td class="fca-num">' +
                        fmtLit(r.liters) +
                        "</td>" +
                        '<td class="fca-num">' +
                        fmtNum(r.price_per_liter) +
                        "</td>" +
                        '<td class="fca-num fca-cost-cell">' +
                        fmtNum(r.cost) +
                        "</td>" +
                        '<td><button class="fca-view-btn" data-id="' +
                        (r.id || "") +
                        '">View</button></td>' +
                        "</tr>",
                )
                .join("");
        }
        renderPagination(list.length);
    }

    function renderPagination(total) {
        const pg = $("fcaPagination");
        const pages = Math.max(1, Math.ceil(total / state.perPage));
        const start = total ? (state.page - 1) * state.perPage + 1 : 0;
        const end = Math.min(state.page * state.perPage, total);
        let info =
            '<div class="fca-page-info">Showing ' +
            start +
            " to " +
            end +
            " of " +
            total +
            " records</div>";

        let nums = [];
        if (pages <= 7) {
            for (let i = 1; i <= pages; i++) nums.push(i);
        } else {
            nums = [1];
            if (state.page > 3) nums.push("…");
            for (let i = Math.max(2, state.page - 1); i <= Math.min(pages - 1, state.page + 1); i++)
                nums.push(i);
            if (state.page < pages - 2) nums.push("…");
            nums.push(pages);
        }
        const btns = nums
            .map((n) =>
                n === "…"
                    ? '<span class="fca-page-btn" style="background:transparent;border:none;cursor:default;">…</span>'
                    : '<button class="fca-page-btn' +
                      (n === state.page ? " active" : "") +
                      '" data-page="' +
                      n +
                      '">' +
                      n +
                      "</button>",
            )
            .join("");
        const nav =
            '<div class="fca-page-btns">' +
            '<button class="fca-page-btn" data-page="prev"' +
            (state.page <= 1 ? " disabled" : "") +
            ">Previous</button>" +
            btns +
            '<button class="fca-page-btn" data-page="next"' +
            (state.page >= pages ? " disabled" : "") +
            ">Next</button></div>";
        const size =
            '<div class="fca-page-size">Rows per page: <select id="fcaPerPage" class="fca-period-select">' +
            [5, 10, 25, 50]
                .map(
                    (v) =>
                        '<option value="' +
                        v +
                        '"' +
                        (v === state.perPage ? " selected" : "") +
                        ">" +
                        v +
                        "</option>",
                )
                .join("") +
            "</select></div>";
        pg.innerHTML = info + nav + size;
    }

    const vaState = { page: 1, perPage: 10 };

    function vaFiltered() {
        const f = {
            search: $("fcaVaSearch").value.toLowerCase(),
            bus: $("fcaVaBus").value,
            driver: $("fcaVaDriver").value,
            from: $("fcaVaFrom").value,
            to: $("fcaVaTo").value,
        };
        return TX.filter((r) => {
            if (f.bus && (r.bus || r.bus_id) !== f.bus) return false;
            if (f.driver && (r.driver_code || r.driver_id) !== f.driver) return false;
            if (
                f.search &&
                !String(r.receipt_number || "")
                    .toLowerCase()
                    .includes(f.search)
            )
                return false;
            if (f.from && dayOf(r.created_at) < f.from) return false;
            if (f.to && dayOf(r.created_at) > f.to) return false;
            return true;
        });
    }

    function renderVaTable() {
        const rows = vaFiltered();
        const body = $("fcaVaBody");
        const pages = Math.max(1, Math.ceil(rows.length / vaState.perPage));
        if (vaState.page > pages) vaState.page = pages;
        const start = (vaState.page - 1) * vaState.perPage;
        const slice = rows.slice(start, start + vaState.perPage);
        if (!slice.length) {
            body.innerHTML =
                '<tr><td colspan="8" class="fca-table-empty">No fuel records found.</td></tr>';
        } else {
            body.innerHTML = slice
                .map(
                    (r) =>
                        "<tr>" +
                        '<td class="fca-receipt-cell">' +
                        esc(r.receipt_number || "") +
                        "</td>" +
                        '<td class="fca-date-cell">' +
                        fmtDate(r.created_at) +
                        '<div class="fca-time-cell">' +
                        fmtTime(r.created_at) +
                        "</div></td>" +
                        '<td><span class="fca-va-bus-badge">' +
                        esc(r.bus || busLabel(r.bus_id) || "—") +
                        "</span></td>" +
                        '<td><div class="fca-drv-name">' +
                        esc(r.driver_name || r.driver_id || "—") +
                        "</div>" +
                        (r.driver_code && r.driver_code !== r.driver_name
                            ? '<div class="fca-drv-id">' + esc(r.driver_code) + "</div>"
                            : "") +
                        "</td>" +
                        '<td class="fca-num">' +
                        fmtLit(r.liters) +
                        "</td>" +
                        '<td class="fca-num">' +
                        fmtNum(r.price_per_liter) +
                        "</td>" +
                        '<td class="fca-num fca-cost-cell">' +
                        fmtNum(r.cost) +
                        "</td>" +
                        '<td><button class="fca-view-btn" data-id="' +
                        (r.id || "") +
                        '"><i class="bi bi-eye me-1"></i>View</button></td>' +
                        "</tr>",
                )
                .join("");
        }
        renderVaPagination(rows.length);
    }

    function renderVaPagination(total) {
        const pg = $("fcaVaPagination");
        const pages = Math.max(1, Math.ceil(total / vaState.perPage));
        const start = total ? (vaState.page - 1) * vaState.perPage + 1 : 0;
        const end = Math.min(vaState.page * vaState.perPage, total);
        const info =
            '<div class="fca-page-info">Showing ' +
            start +
            " to " +
            end +
            " of " +
            total +
            " receipts</div>";
        const nav =
            '<div class="fca-page-btns">' +
            '<button class="fca-page-btn" data-page="prev"' +
            (vaState.page <= 1 ? " disabled" : "") +
            ">Previous</button>" +
            '<span class="fca-va-page-indicator">' +
            vaState.page +
            "</span>" +
            '<button class="fca-page-btn" data-page="next"' +
            (vaState.page >= pages ? " disabled" : "") +
            ">Next</button>" +
            '<select class="fca-period-select" id="fcaVaPerPage">' +
            [5, 10, 25, 50]
                .map(
                    (v) =>
                        '<option value="' +
                        v +
                        '"' +
                        (v === vaState.perPage ? " selected" : "") +
                        ">" +
                        v +
                        " / page</option>",
                )
                .join("") +
            "</select></div>";
        pg.innerHTML = info + nav;
    }

    function showReceiptPreview(id) {
        const r = TX.find((x) => String(x.id) === String(id));
        if (!r) return;
        state.lastId = id;
        $("fcaPrintReceipt").innerHTML = slipHtml(r, "fca-print-slip");
        bootstrap.Modal.getOrCreateInstance($("fcaReceiptModal")).show();
    }

    function updateExportLinks() {
        const q = new URLSearchParams({
            action: "export_pdf",
            bus: state.bus,
            driver: state.driver,
            search: state.search,
            from: state.from,
            to: state.to,
            period: state.period,
        });
        $("fcaPdfLink").href = "?" + q.toString();
        q.set("action", "export_excel");
        $("fcaExcelLink").href = "?" + q.toString();
    }

    function syncInputs() {
        $("fcaBusFilter").value = state.bus;
        $("fcaDriverFilter").value = state.driver;
        $("fcaSearch").value = state.search;
    }

    function renderAllContent() {
        updatePeriodLabels();
        const list = repriced(filtered());
        renderSummary(list);
        renderSecStats(list);
        renderBar(list);
        renderLine(list);
        renderComparison(list);
        renderRecent(filteredTx());
        renderTable();
        updateExportLinks();
    }

    let filterRenderRequest = 0;

    function renderAll() {
        const requestId = ++filterRenderRequest;
        const panel = $("fcaAnalyticsPanel");
        const loading = $("fcaFilterLoading");
        panel.setAttribute("aria-busy", "true");
        loading.hidden = false;

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                if (requestId !== filterRenderRequest) return;
                try {
                    renderAllContent();
                } finally {
                    requestAnimationFrame(() => {
                        if (requestId !== filterRenderRequest) return;
                        loading.hidden = true;
                        panel.removeAttribute("aria-busy");
                    });
                }
            });
        });
    }

    $("fcaBusFilter").addEventListener("change", (e) => {
        state.bus = e.target.value;
        state.page = 1;
        renderAll();
    });
    $("fcaDriverFilter").addEventListener("change", (e) => {
        state.driver = e.target.value;
        state.page = 1;
        renderAll();
    });
    $("fcaSearch").addEventListener("input", (e) => {
        state.search = e.target.value.toLowerCase();
        state.page = 1;
        renderAll();
    });
    document.addEventListener("financedatefilter:change", (e) => {
        const detail = e.detail || {};
        state.period = detail.period || state.period;
        state.from = detail.from || detail.start || "";
        state.to = detail.to || detail.end || "";
        state.page = 1;
        renderAll();
    });
    $("fcaBarTop").addEventListener("change", (e) => {
        fcaBarTop = e.target.value;
        renderBar(filtered());
        renderComparison(filtered());
    });
    $("fcaBarBox").addEventListener("click", (e) => {
        const bar = e.target.closest(".fca-bar");
        if (!bar) return;
        const bus = bar.dataset.bus;
        if (!bus) return;
        state.bus = bus;
        state.page = 1;
        syncInputs();
        renderAll();
    });
    const compBox = $("fcaCompBox");
    if (compBox) {
        compBox.addEventListener("click", (e) => {
            const bar = e.target.closest(".fca-bar");
            if (!bar) return;
            const bus = bar.dataset.bus;
            if (!bus) return;
            state.bus = bus;
            state.page = 1;
            syncInputs();
            renderAll();
        });
    }
    $("fcaClearBtn").addEventListener("click", () => {
        state.bus = "";
        state.driver = "";
        state.search = "";
        state.page = 1;
        const defaultPeriodBtn = document.querySelector('.fin-period-btn[data-period="today"]');
        if (defaultPeriodBtn) defaultPeriodBtn.click();
        syncInputs();
        renderAll();
    });

    $("fcaPagination").addEventListener("click", (e) => {
        const btn = e.target.closest(".fca-page-btn");
        if (!btn || btn.disabled) return;
        const p = btn.dataset.page;
        const pages = Math.max(1, Math.ceil(filtered().length / state.perPage));
        if (p === "prev") state.page = Math.max(1, state.page - 1);
        else if (p === "next") state.page = Math.min(pages, state.page + 1);
        else state.page = Number(p);
        renderTable();
    });
    $("fcaPagination").addEventListener("change", (e) => {
        if (e.target.id !== "fcaPerPage") return;
        state.perPage = Number(e.target.value);
        state.page = 1;
        renderTable();
    });

    $("fcaTableBody").addEventListener("click", (e) => {
        const btn = e.target.closest(".fca-view-btn");
        if (btn) showReceiptPreview(btn.dataset.id);
    });

    function fcaOtpMsgBox(type, text) {
        const msg = $("fcaOtpMsg");
        msg.style.display = "block";
        msg.style.background = type === "success" ? "#dcfce7" : "#fee2e2";
        msg.style.color = type === "success" ? "#166534" : "#dc2626";
        msg.style.border = type === "success" ? "1px solid #bbf7d0" : "1px solid #fecaca";
        msg.textContent = text;
    }

    $("fcaPrintBtn").addEventListener("click", () => {
        $("fcaOtpInput").value = "";
        $("fcaOtpMsg").style.display = "none";
        $("fcaOtpSendBtn").textContent = "Send OTP / Resend OTP";
        bootstrap.Modal.getOrCreateInstance($("fcaOtpModal")).show();
    });
    $("fcaOtpSendBtn").addEventListener("click", async () => {
        const btn = $("fcaOtpSendBtn");
        btn.disabled = true;
        btn.textContent = "Sending...";
        try {
            const res = await fetch("?action=request_otp", { method: "POST" });
            const j = await res.json();
            if (j.success) {
                fcaOtpMsgBox("success", j.message || "OTP sent to your email (valid 5 minutes).");
                btn.textContent = "Resend OTP";
            } else {
                fcaOtpMsgBox("error", j.message || "Failed to send OTP.");
                btn.textContent = "Send OTP / Resend OTP";
            }
        } catch {
            fcaOtpMsgBox("error", "Could not reach the server.");
            btn.textContent = "Send OTP / Resend OTP";
        }
        btn.disabled = false;
    });
    $("fcaOtpVerifyBtn").addEventListener("click", async () => {
        const code = $("fcaOtpInput").value.trim();
        if (!/^\d{6}$/.test(code)) {
            fcaOtpMsgBox("error", "Enter the 6-digit OTP.");
            return;
        }
        try {
            const res = await fetch("?action=verify_otp", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ otp: code, fuel_log_id: state.lastId ?? null }),
            });
            const j = await res.json();
            if (j.success) {
                bootstrap.Modal.getInstance($("fcaOtpModal"))?.hide();
                window.print();
            } else {
                fcaOtpMsgBox("error", j.message || "Invalid OTP.");
            }
        } catch {
            fcaOtpMsgBox("error", "Could not reach the server.");
        }
    });

    $("fcaViewAllLink").addEventListener("click", () => {
        renderVaTable();
        bootstrap.Modal.getOrCreateInstance($("fcaViewAllModal")).show();
    });
    $("fcaVaBody").addEventListener("click", (e) => {
        const btn = e.target.closest(".fca-view-btn");
        if (btn) {
            bootstrap.Modal.getInstance($("fcaViewAllModal"))?.hide();
            showReceiptPreview(btn.dataset.id);
        }
    });
    ["fcaVaSearch", "fcaVaBus", "fcaVaDriver", "fcaVaFrom", "fcaVaTo"].forEach((id) => {
        $(id).addEventListener("input", () => {
            vaState.page = 1;
            renderVaTable();
        });
        $(id).addEventListener("change", () => {
            vaState.page = 1;
            renderVaTable();
        });
    });

    $("fcaVaReset").addEventListener("click", () => {
        $("fcaVaSearch").value = "";
        $("fcaVaBus").value = "";
        $("fcaVaDriver").value = "";
        $("fcaVaFrom").value = "";
        $("fcaVaTo").value = "";
        vaState.page = 1;
        vaState.perPage = 10;
        renderVaTable();
    });

    $("fcaVaPagination").addEventListener("click", (e) => {
        const btn = e.target.closest(".fca-page-btn");
        if (!btn || btn.disabled) return;
        const p = btn.dataset.page;
        const pages = Math.max(1, Math.ceil(vaFiltered().length / vaState.perPage));
        if (p === "prev") vaState.page = Math.max(1, vaState.page - 1);
        else if (p === "next") vaState.page = Math.min(pages, vaState.page + 1);
        else vaState.page = Number(p);
        renderVaTable();
    });
    $("fcaVaPagination").addEventListener("change", (e) => {
        if (e.target.id !== "fcaVaPerPage") return;
        vaState.perPage = Number(e.target.value);
        vaState.page = 1;
        renderVaTable();
    });

    $("fcaVaExportExcel").addEventListener("click", () => {
        const q = new URLSearchParams({
            action: "export_excel",
            search: $("fcaVaSearch").value.trim(),
            bus: $("fcaVaBus").value,
            driver: $("fcaVaDriver").value,
            from: $("fcaVaFrom").value,
            to: $("fcaVaTo").value,
        });
        q.set("period", $("fcaVaFrom").value || $("fcaVaTo").value ? "custom" : state.period);
        location.href = "?" + q.toString();
    });

    document.addEventListener("mouseover", (e) => {
        const el = e.target.closest(".fca-bar, .fca-dot");
        if (!el) return;
        const tip =
            document.getElementById("fcaChartTip") ||
            (() => {
                const t = document.createElement("div");
                t.id = "fcaChartTip";
                t.className = "fca-tip";
                document.body.appendChild(t);
                return t;
            })();
        tip.textContent = el.dataset.tip;
        tip.style.display = "block";
        const box = el.closest(".fca-chart-box");
        const rect = box.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        tip.style.left = x + 12 + "px";
        tip.style.top = y - 10 + "px";
    });
    document.addEventListener("mouseout", (e) => {
        if (e.target.closest(".fca-bar, .fca-dot")) {
            const tip = document.getElementById("fcaChartTip");
            if (tip) tip.style.display = "none";
        }
    });

    syncInputs();
    renderAll();
    let resizeT;
    window.addEventListener("resize", () => {
        clearTimeout(resizeT);
        resizeT = setTimeout(() => {
            renderBar(filtered());
            renderLine(filtered());
            renderComparison(filtered());
        }, 150);
    });
})();
</script>

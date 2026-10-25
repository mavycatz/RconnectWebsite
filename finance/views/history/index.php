<?php header('Cache-Control: no-store, no-cache, must-revalidate'); header('Pragma: no-cache');
require_once __DIR__ . '/../layout/navbarfinance.php'; ?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">

<style>
.rh-main {
    font-family: "Poppins", sans-serif;
    background: #eceed9;
    min-height: 100vh;
    padding: 20px;
}
.rh-card {
    background: #fef8f3;
    border: 1px solid #cdd9b5;
    border-radius: 18px;
    padding: 22px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.rh-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}
.rh-title {
    font-weight: 800;
    font-size: 1.35rem;
    color: #295738;
    margin: 0 0 2px;
}
.rh-subtitle {
    font-weight: 500;
    font-size: 0.8rem;
    color: #8a9a8a;
    margin: 0;
}
.rh-header-actions {
    display: flex;
    gap: 10px;
}
.rh-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    border-radius: 10px;
    padding: 9px 16px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: 1px solid transparent;
    transition: background 0.15s;
}
.rh-btn-pdf {
    background: #295738;
    color: #fff;
}
.rh-btn-pdf:hover {
    background: #1f4229;
    color: #fff;
}
.rh-btn-excel {
    background: #eef4e7;
    color: #295738;
    border-color: #d7e3cd;
}
.rh-btn-excel:hover {
    background: #e2eede;
    color: #295738;
}

.rh-cards {
    display: flex;
    gap: 14px;
    margin-bottom: 16px;
}
.rh-card-item {
    flex: 1;
    min-width: 0;
    background: #fcfbf4;
    border: 1px solid #d7e3cd;
    border-radius: 14px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.rh-card-dark {
    background: #295738;
    border-color: #295738;
}
.rh-card-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #eef2dc;
    color: #295738;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    flex-shrink: 0;
}
.rh-card-icon-red {
    background: #fde8e6;
    color: #b3261e;
}
.rh-card-dark .rh-card-icon {
    background: rgba(255, 255, 255, 0.14);
    color: #fff;
}
.rh-card-label {
    font-size: 11px;
    font-weight: 600;
    color: #295738;
    display: block;
}
.rh-card-value {
    font-size: 21px;
    font-weight: 700;
    color: #295738;
    line-height: 1.25;
    font-variant-numeric: tabular-nums;
}
.rh-card-sub {
    font-size: 11px;
    color: #8a9a8a;
    display: block;
}
.rh-value-red {
    color: #dc2626;
}
.rh-card-dark .rh-card-label {
    color: rgba(255, 255, 255, 0.75);
}
.rh-card-dark .rh-card-value {
    color: #fff;
}
.rh-card-dark .rh-card-sub {
    color: rgba(255, 255, 255, 0.6);
}

.rh-filter {
    display: flex;
    gap: 10px;
    align-items: center;
    background: #fcfbf4;
    border: 1px solid #e8e4c9;
    border-radius: 14px;
    padding: 12px 14px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}
.rh-search {
    position: relative;
    flex: 0 1 270px;
    min-width: 200px;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1.5px solid #cdd9b5;
    border-radius: 10px;
    padding: 0 10px;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.rh-search:focus-within {
    border-color: #b9cfa8;
    box-shadow: 0 0 0 3px rgba(107, 141, 107, 0.10);
}
.rh-search-icon {
    color: #6b8d6b;
    font-size: 13px;
    pointer-events: none;
}
.rh-search input {
    width: 100%;
    box-sizing: border-box;
    padding: 9px 0;
    border: none;
    background: transparent;
    font-size: 13px;
    color: #295738;
    outline: none;
    font-family: inherit;
}
.rh-select {
    border: 1.5px solid #cdd9b5;
    border-radius: 10px;
    background: #fff;
    padding: 9px 10px;
    font-size: 13px;
    font-weight: 600;
    color: #295738;
    outline: none;
    font-family: inherit;
    cursor: pointer;
}
.rh-daterange {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #295738;
    font-size: 13px;
    background: #fff;
    border: 1px solid #cdd9b5;
    border-radius: 10px;
    padding: 6px 10px;
    white-space: nowrap;
}
.rh-daterange .rh-daterange-icon {
    color: #8a9a8a;
    font-size: 13px;
}
.rh-daterange-sep {
    color: #8a9a8a;
}
.rh-daterange input {
    border: none;
    outline: none;
    font-size: 12.5px;
    color: #295738;
    font-family: inherit;
    background: transparent;
    width: 112px;
    cursor: pointer;
}

.rh-table-wrap {
    background: #fff;
    border: 1px solid #cdd9b5;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 14px;
}
.rh-table-meta {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding: 9px 14px;
    background: #fcfbf4;
    border-bottom: 1px solid #eef2e6;
}
.rh-filter-count {
    font-size: 12px;
    color: #8a9a8a;
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.rh-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
    min-width: 960px;
}
.rh-table thead th {
    background: #f0f4e2;
    color: #295738;
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 11px 12px;
    text-align: left;
    font-weight: 700;
    white-space: nowrap;
}
.rh-table tbody td {
    padding: 11px 12px;
    border-bottom: 1px solid #f1ecdc;
    vertical-align: middle;
    color: #374151;
}
.rh-table tbody tr:last-child td {
    border-bottom: none;
}
.rh-table tbody tr:hover {
    background: #fbf9ef;
}
.rh-num {
    text-align: left;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.rh-sortable {
    cursor: pointer;
    user-select: none;
}
.rh-sortable:hover {
    background: #e4ecd8;
}
.rh-sort-icon {
    font-size: 9px;
    opacity: 0.55;
    margin-left: 3px;
}
.rh-sort-active .rh-sort-icon {
    opacity: 1;
    color: #295738;
}
.rh-id {
    color: #6b7280;
    font-weight: 600;
    white-space: nowrap;
}
.rh-datetime span {
    display: block;
}
.rh-date {
    font-weight: 600;
    color: #295738;
    white-space: nowrap;
}
.rh-time {
    color: #8a9a8a;
    font-size: 11.5px;
    white-space: nowrap;
}
.rh-driver-name {
    font-weight: 600;
    color: #295738;
    display: block;
}
.rh-driver-id {
    color: #8a9a8a;
    font-size: 11px;
    display: block;
}
.rh-amt-neutral {
    font-weight: 600;
    color: #374151;
}
.rh-amt-green {
    color: #1f6b3a;
    font-weight: 600;
}
.rh-amt-red {
    color: #b3261e;
    font-weight: 600;
}
.rh-amt-amber {
    color: #8a5a10;
    font-weight: 600;
}
.rh-amt-gray {
    color: #6b7280;
    font-weight: 600;
}

.rh-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 11px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 600;
    white-space: nowrap;
}
.rh-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.rh-badge-green {
    background: #e3f0e0;
    color: #1f6b3a;
    border: 1px solid #c3e0bd;
}
.rh-badge-green .rh-dot {
    background: #4f9e6a;
}
.rh-badge-red {
    background: #fde8e6;
    color: #b3261e;
    border: 1px solid #f5c9c4;
}
.rh-badge-red .rh-dot {
    background: #d9534f;
}
.rh-badge-amber {
    background: #fdf0dd;
    color: #8a5a10;
    border: 1px solid #f5ddb4;
}
.rh-badge-amber .rh-dot {
    background: #d99a2b;
}
.rh-badge-gray {
    background: #f4f2e9;
    color: #6b7280;
    border: 1px solid #e8e4d2;
}
.rh-badge-gray .rh-dot {
    background: #9ca3af;
}

.rh-actions {
    display: flex;
    gap: 7px;
}
.rh-action {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    border: 1px solid #d7e3cd;
    background: #fcfbf4;
    color: #295738;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 14px;
    transition:
        background 0.15s,
        color 0.15s,
        border-color 0.15s;
}
.rh-action:hover {
    background: #295738;
    color: #fff;
    border-color: #295738;
}

.rh-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 14px;
    flex-wrap: wrap;
}
.rh-page-info {
    font-size: 12px;
    color: #6b7280;
}
.rh-page-controls {
    display: flex;
    align-items: center;
    gap: 5px;
}
.rh-page-btn {
    border: 1px solid #d7e3cd;
    background: #fcfbf4;
    color: #295738;
    border-radius: 8px;
    padding: 5px 11px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.15s;
}
.rh-page-btn:hover:not(:disabled) {
    background: #eef2dc;
}
.rh-page-btn.active {
    background: #295738;
    color: #fff;
    border-color: #295738;
}
.rh-page-btn:disabled {
    opacity: 0.45;
    cursor: default;
}
.rh-page-ellipsis {
    color: #8a9a8a;
    font-size: 12px;
    padding: 0 3px;
}
.rh-page-size {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    color: #6b7280;
}
.rh-page-size select {
    border: 1px solid #cdd9b5;
    border-radius: 8px;
    padding: 5px 8px;
    font-size: 12px;
    font-family: inherit;
    color: #295738;
    cursor: pointer;
}

.rh-legend {
    display: flex;
    flex-wrap: nowrap;
    gap: 32px;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    margin-top: 14px;
    background: transparent;
    border: 1px solid #cdd9b5;
    border-radius: 10px;
    overflow-x: auto;
}
.rh-legend-item {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 11.5px;
    color: #6b7280;
}
.rh-legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.rh-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    font-family: "Poppins", sans-serif;
    padding: 20px;
}
.rh-modal-box {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18);
    display: flex;
    flex-direction: column;
}
.rh-modal-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #eef2dc;
    background: #fef8f3;
}
.rh-modal-title {
    font-weight: 700;
    font-size: 16px;
    color: #295738;
    margin: 0;
}
.rh-modal-sub {
    font-size: 11.5px;
    color: #8a9a8a;
    margin-top: 1px;
}
.rh-modal-close {
    background: none;
    border: none;
    font-size: 22px;
    color: #6b7280;
    cursor: pointer;
    line-height: 1;
    padding: 0;
}
.rh-modal-body {
    overflow-y: auto;
    padding: 18px 20px;
}

.rh-cards {
    flex-wrap: wrap;
}
.rh-header-actions {
    flex-wrap: wrap;
}

@media (max-width: 992px) {
    .rh-card-item {
        flex: 1 1 calc(50% - 7px);
    }
}

@media (max-width: 576px) {
    .rh-card-item {
        flex: 1 1 100%;
    }
}
</style>

<div class="main-content rh-main">

<div class="rh-card">

<?php require_once __DIR__ . '/../layout/_headernav.php'; ?>


<div class="rh-header">
    <div>
        <h1 class="rh-title">Remittance History</h1>
        <p class="rh-subtitle">Finance Remittance Archive and Audit Logs</p>
        <?php
        if (!class_exists(\Finance\Services\DateFilterService::class)) {
            require_once __DIR__ . '/../../services/DateFilterService.php';
        }
        $financePeriodResolved = \Finance\Services\DateFilterService::normalizeRequest($_GET, null, \Finance\Services\DateFilterService::TODAY);
        $financePeriodConfig = \Finance\Services\DateFilterService::config(
            $financePeriodResolved['period'],
            $financePeriodResolved['from'],
            $financePeriodResolved['to']
        );
        $financeExportQuery = 'period=' . rawurlencode($financePeriodResolved['period']);
        if ($financePeriodResolved['period'] === \Finance\Services\DateFilterService::CUSTOM) {
            $financeExportQuery .= '&from=' . rawurlencode($financePeriodResolved['start']);
            $financeExportQuery .= '&to=' . rawurlencode($financePeriodResolved['end']);
        }
        require __DIR__ . '/../layout/_period_summary.php';
        ?>
    </div>
    <div class="rh-header-actions">
        <a id="rhPdfLink" href="/finance/history.php?action=export_pdf&amp;<?= htmlspecialchars($financeExportQuery, ENT_QUOTES) ?>" class="rh-btn rh-btn-pdf">
            <i class="bi bi-file-earmark-pdf"></i> Export PDF
        </a>
        <a id="rhExcelLink" href="/finance/history.php?action=export_excel&amp;<?= htmlspecialchars($financeExportQuery, ENT_QUOTES) ?>" class="rh-btn rh-btn-excel">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </a>
    </div>
</div>


<div class="rh-cards">

    <div class="rh-card-item">
        <div class="rh-card-icon"><i class="bi bi-receipt"></i></div>
        <div>
            <small class="rh-card-label">Total Remittances</small>
            <div class="rh-card-value" id="rhCardTotal"><?= number_format($totals['total_remittances'] ?? 0) ?></div>
            <small class="rh-card-sub">Records in selected period</small>
        </div>
    </div>

    <div class="rh-card-item">
        <div class="rh-card-icon"><i class="bi bi-cash-coin"></i></div>
        <div>
            <small class="rh-card-label">Total Remitted Cash</small>
            <div class="rh-card-value" id="rhCardCash">₱<?= number_format($totals['total_cash'] ?? 0, 2) ?></div>
            <small class="rh-card-sub">Total cash collected</small>
        </div>
    </div>

    <div class="rh-card-item">
        <div class="rh-card-icon rh-card-icon-red"><i class="bi bi-exclamation-triangle"></i></div>
        <div>
            <small class="rh-card-label">Total Discrepancies</small>
            <div class="rh-card-value rh-value-red" id="rhCardDisc">₱<?= number_format($totals['total_discrepancies'] ?? 0, 2) ?></div>
            <small class="rh-card-sub">Requires attention</small>
        </div>
    </div>

    <div class="rh-card-item rh-card-dark">
        <div class="rh-card-icon"><i class="bi bi-check-circle"></i></div>
        <div>
            <small class="rh-card-label">Completed Remittances</small>
            <div class="rh-card-value" id="rhCardCompleted"><?= number_format($totals['completed'] ?? 0) ?></div>
            <small class="rh-card-sub">Successfully verified</small>
        </div>
    </div>

</div>


<style>
.rh-filter .fin-period-filter {
    margin-left: auto;
}
.rh-filter .fin-period-btn {
    padding: 4px 8px;
    font-size: 0.68rem;
}
.rh-filter .fin-period-btn i {
    font-size: 0.64rem;
}
</style>

<div class="rh-filter">

    <div class="rh-search">
        <i class="bi bi-search rh-search-icon"></i>
        <input type="text" id="searchInput" placeholder="Search driver, bus, status...">
    </div>

    <select id="statusFilter" class="rh-select">
        <option value="">All Status</option>
        <option value="Completed">Completed</option>
        <option value="Discrepancy">Discrepancy</option>
        <option value="No Remit">No Remit</option>
        <option value="Pending">Pending</option>
    </select>

    <?php
    $financePeriodHideSummary = true;
    require __DIR__ . '/../layout/_period_filter.php';
    ?>

</div>


<div class="rh-table-wrap">

<div class="rh-table-meta">
    <span class="rh-filter-count" id="filterCount"><i class="bi bi-list-ul"></i><?= count($history) ?> record(s)</span>
</div>

<div class="table-responsive">

<table class="table align-middle mb-0 rh-table">

<thead>
<tr>
    <th class="rh-th-id">ID</th>
    <th class="rh-sortable" data-sort="date">Business Date<span class="rh-sort-icon"></span></th>
    <th class="rh-sortable" data-sort="driver">Driver<span class="rh-sort-icon"></span></th>
    <th>Bus</th>
    <th class="rh-sortable rh-num" data-sort="expected">Expected<span class="rh-sort-icon"></span></th>
    <th class="rh-sortable rh-num" data-sort="actual">Actual<span class="rh-sort-icon"></span></th>
    <th class="rh-sortable rh-num" data-sort="discrepancy">Discrepancy<span class="rh-sort-icon"></span></th>
    <th>Status</th>
    <th>Verified By</th>
    <th>Actions</th>
</tr>
</thead>

<tbody id="historyTable">

<?php foreach ($history as $row):

    $status = $row['status'] ?? 'Pending';
    $statusKey = strtolower($status);

    $badgeClass = 'rh-badge-gray';
    $actualClass = 'rh-amt-neutral';
    $discClass = 'rh-amt-neutral';

    if ($statusKey === 'completed') {
        $badgeClass = 'rh-badge-green';
        $actualClass = 'rh-amt-green';
        $discClass = 'rh-amt-green';
    } elseif ($statusKey === 'discrepancy') {
        $badgeClass = 'rh-badge-red';
        $actualClass = 'rh-amt-red';
        $discClass = 'rh-amt-red';
    } elseif ($statusKey === 'no remit') {
        $badgeClass = 'rh-badge-amber';
        $actualClass = 'rh-amt-amber';
        $discClass = 'rh-amt-amber';
    } elseif ($statusKey === 'pending') {
        $actualClass = 'rh-amt-gray';
        $discClass = 'rh-amt-gray';
    }

    $created = strtotime($row['created_at'] ?? 'now');
    $businessDate = (string) ($row['business_date'] ?? date('Y-m-d', $created));
    $businessTs = strtotime($businessDate) ?: $created;
    $mergedCount = (int) ($row['consolidated_count'] ?? 1);

?>

<tr class="rh-row"
    data-date="<?= htmlspecialchars($businessDate, ENT_QUOTES) ?>"
    data-status="<?= htmlspecialchars($status, ENT_QUOTES) ?>"
    data-driver="<?= htmlspecialchars(strtolower($row['full_name'] ?? ''), ENT_QUOTES) ?>"
    data-expected="<?= (float)($row['expected_cash'] ?? 0) ?>"
    data-actual="<?= (float)($row['actual_cash'] ?? 0) ?>"
    data-discrepancy="<?= (float)($row['discrepancy_amount'] ?? 0) ?>">

    <td class="rh-id"<?= $mergedCount > 1 ? ' title="Consolidated from ' . $mergedCount . ' duplicate records"' : '' ?>>RM-<?= str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT) ?></td>

    <td class="rh-datetime">
        <span class="rh-date"><?= date('M d, Y', $businessTs) ?></span>
        <span class="rh-time"><?= date('h:i A', $created) ?></span>
    </td>

    <td>
        <span class="rh-driver-name"><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></span>
        <span class="rh-driver-id"><?= htmlspecialchars($row['driver_id'] ?? '') ?></span>
    </td>

    <td><?= htmlspecialchars($row['bus_no'] ?? 'N/A') ?></td>

    <td class="rh-num">₱<?= number_format((float)($row['expected_cash'] ?? 0), 2) ?></td>
    <td class="rh-num <?= $actualClass ?>">₱<?= number_format((float)($row['actual_cash'] ?? 0), 2) ?></td>
    <td class="rh-num <?= $discClass ?>">₱<?= number_format((float)($row['discrepancy_amount'] ?? 0), 2) ?></td>

    <td>
        <span class="rh-badge <?= $badgeClass ?>">
            <span class="rh-dot"></span><?= htmlspecialchars($status) ?>
        </span>
    </td>

    <td><?= htmlspecialchars($row['confirmed_by'] ?? 'Finance Manager') ?></td>

    <td>
        <div class="rh-actions">
            <button type="button" class="rh-action view-remittance-btn" data-id="<?= $row['id'] ?>" title="View Remittance Details">
                <i class="bi bi-eye"></i>
            </button>
            <button type="button" class="rh-action print-otp-btn" data-id="<?= $row['id'] ?>" data-driver="<?= htmlspecialchars($row['full_name'] ?? '') ?>" title="Print Remittance Record">
                <i class="bi bi-printer"></i>
            </button>
        </div>
    </td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<div class="rh-pagination">
    <span class="rh-page-info" id="paginationInfo">Showing 0 to 0 of 0 records</span>
    <div class="rh-page-controls" id="paginationControls"></div>
    <div class="rh-page-size">
        <span>Rows per page:</span>
        <select id="rowsPerPage">
            <option value="10" selected>10</option>
            <option value="25">25</option>
            <option value="50">50</option>
        </select>
    </div>
</div>

</div>


<div class="rh-legend">
    <div class="rh-legend-item"><span class="rh-legend-dot" style="background:#16a34a;"></span>Completed – Matches expected</div>
    <div class="rh-legend-item"><span class="rh-legend-dot" style="background:#dc2626;"></span>Discrepancy – Amount mismatch</div>
    <div class="rh-legend-item"><span class="rh-legend-dot" style="background:#d97706;"></span>No Remit – No submission</div>
    <div class="rh-legend-item"><span class="rh-legend-dot" style="background:#9ca3af;"></span>Pending – Awaiting verification</div>
</div>
</div>

</div>


<div id="remittanceModal" class="rh-modal">
    <div class="rh-modal-box">
        <div class="rh-modal-head">
            <div>
                <div class="rh-modal-title">Remittance Details</div>
                <div class="rh-modal-sub">Finance remittance record</div>
            </div>
            <button id="closeModal" class="rh-modal-close" type="button">&times;</button>
        </div>
        <div id="modalContent" class="rh-modal-body"></div>
    </div>
</div>


<div id="otpModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;justify-content:center;align-items:center;font-family:'Poppins',sans-serif;">
    <div style="width:95%;max-width:420px;background:#fef8f3;border-radius:20px;overflow:hidden;border:2px solid #cdd9b5;box-shadow:0 8px 24px rgba(0,0,0,0.15);">
        <div style="background:#295738;padding:18px 20px;color:white;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-weight:700;font-size:16px;">
                    Verify to Print Receipt
                </div>
                <div id="otpModalSub" style="font-size:12px;color:#d7e2d3;margin-top:2px;">
                    Receipt #--
                </div>
            </div>
            <button id="closeOtpModal" type="button" style="background:none;border:none;color:white;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div style="padding:20px;">

            <div id="otpMsg" style="display:none;border-radius:12px;padding:12px 14px;font-size:13px;font-weight:600;margin-bottom:14px;"></div>

            <p style="color:#6b7280;font-size:13px;line-height:1.7;margin:0 0 14px;">
                A 6-digit one-time password is required to print the receipt.
                Click <strong>Send OTP</strong> to receive the code at your registered email.
                The code expires in 5 minutes.
            </p>

            <button
                id="sendOtpBtn"
                type="button"
                style="
                    width:100%;
                    background:#295738;
                    color:white;
                    border:none;
                    border-radius:12px;
                    padding:13px;
                    font-size:14px;
                    font-weight:700;
                    cursor:pointer;
                "
            >
                Send OTP
            </button>

            <input
                id="otpInput"
                type="text"
                maxlength="6"
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="&bull; &bull; &bull; &bull; &bull; &bull;"
                style="
                    display:none;
                    width:100%;
                    box-sizing:border-box;
                    margin-top:12px;
                    padding:13px;
                    font-size:22px;
                    font-weight:800;
                    text-align:center;
                    letter-spacing:10px;
                    border:2px solid #cdd9b5;
                    border-radius:12px;
                    background:white;
                    color:#295738;
                    outline:none;
                "
            >

            <button
                id="verifyPrintBtn"
                type="button"
                style="
                    display:none;
                    width:100%;
                    margin-top:12px;
                    background:#fcfbf4;
                    color:#295738;
                    border:2px solid #d7e3cd;
                    border-radius:12px;
                    padding:12px;
                    font-size:14px;
                    font-weight:700;
                    cursor:pointer;
                "
            >
                Verify &amp; Print
            </button>

        </div>
    </div>
</div>


<iframe id="receiptIframe" style="position:fixed;left:-9999px;width:1px;height:1px;border:0;" title="receipt-print-frame"></iframe>


<script>
const searchInput = document.getElementById("searchInput");
const statusFilter = document.getElementById("statusFilter");
const tbody = document.getElementById("historyTable");
const rowsPerPageSelect = document.getElementById("rowsPerPage");
const rows = Array.from(document.querySelectorAll(".rh-row"));
const sortHeaders = Array.from(document.querySelectorAll(".rh-sortable"));

const FIN_CONFIG = (() => {
    const node = document.querySelector("[data-fin-config]");
    if (!node) {
        return { selected: "monthly", start: "", end: "" };
    }
    try {
        return JSON.parse(node.textContent || "{}");
    } catch (e) {
        return { selected: "monthly", start: "", end: "" };
    }
})();
const finState = {
    period: FIN_CONFIG.selected || "monthly",
    from: FIN_CONFIG.start || "",
    to: FIN_CONFIG.end || "",
};

let sortKey = null;
let sortDir = 1;
let currentPage = 1;
let rowsPerPage = parseInt(rowsPerPageSelect.value, 10) || 10;

function getFilteredRows() {
    const search = searchInput.value.trim().toLowerCase();
    const status = statusFilter.value.toLowerCase();
    const from = finState.from;
    const to = finState.to;

    return rows.filter((row) => {
        if (status && (row.dataset.status || "").toLowerCase() !== status) return false;
        if (search && !row.textContent.toLowerCase().includes(search)) return false;
        if (from || to) {
            const d = row.dataset.date || "";
            if (from && d < from) return false;
            if (to && d > to) return false;
        }
        return true;
    });
}

function fmtCardMoney(value) {
    return "₱" + Number(value || 0).toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function renderCards(filtered) {
    let cash = 0;
    let discrepancies = 0;
    let completed = 0;
    filtered.forEach((row) => {
        cash += parseFloat(row.dataset.actual) || 0;
        discrepancies += parseFloat(row.dataset.discrepancy) || 0;
        if ((row.dataset.status || "").toLowerCase() === "completed") completed++;
    });

    const totalEl = document.getElementById("rhCardTotal");
    const cashEl = document.getElementById("rhCardCash");
    const discEl = document.getElementById("rhCardDisc");
    const completedEl = document.getElementById("rhCardCompleted");
    if (totalEl) totalEl.textContent = filtered.length.toLocaleString("en-PH");
    if (cashEl) cashEl.textContent = fmtCardMoney(cash);
    if (discEl) discEl.textContent = fmtCardMoney(discrepancies);
    if (completedEl) completedEl.textContent = completed.toLocaleString("en-PH");
}

function updateHistoryExportLinks(detail) {
    const period = (detail && detail.period) || finState.period || "monthly";
    const params = new URLSearchParams({ action: "export_pdf", period: period });
    if (period === "custom") {
        const from = (detail && detail.start) || finState.from;
        const to = (detail && detail.end) || finState.to;
        if (from) params.set("from", from);
        if (to) params.set("to", to);
    }
    const pdf = document.getElementById("rhPdfLink");
    const excel = document.getElementById("rhExcelLink");
    if (pdf) pdf.href = "/finance/history.php?" + params.toString();
    params.set("action", "export_excel");
    if (excel) excel.href = "/finance/history.php?" + params.toString();
}

function sortRows(list) {
    if (!sortKey) return list;
    const copy = list.slice();
    const dir = sortDir;
    copy.sort((a, b) => {
        if (sortKey === "date" || sortKey === "driver") {
            return (a.dataset[sortKey] || "").localeCompare(b.dataset[sortKey] || "") * dir;
        }
        const va = parseFloat(a.dataset[sortKey]) || 0;
        const vb = parseFloat(b.dataset[sortKey]) || 0;
        return (va - vb) * dir;
    });
    return copy;
}

function pageList(total) {
    const pages = [];
    const add = (n) => {
        if (!pages.includes(n)) pages.push(n);
    };
    if (total <= 7) {
        for (let i = 1; i <= total; i++) add(i);
    } else {
        add(1);
        if (currentPage > 3) pages.push("...");
        for (let i = Math.max(2, currentPage - 1); i <= Math.min(total - 1, currentPage + 1); i++)
            add(i);
        if (currentPage < total - 2) pages.push("...");
        add(total);
    }
    return pages;
}

function renderPagination(totalPages) {
    const ctrl = document.getElementById("paginationControls");
    ctrl.innerHTML = "";

    const prev = document.createElement("button");
    prev.className = "rh-page-btn";
    prev.textContent = "Previous";
    prev.disabled = currentPage === 1;
    prev.onclick = () => {
        if (currentPage > 1) {
            currentPage--;
            renderTable();
        }
    };
    ctrl.appendChild(prev);

    pageList(totalPages).forEach((p) => {
        if (p === "...") {
            const s = document.createElement("span");
            s.className = "rh-page-ellipsis";
            s.textContent = "...";
            ctrl.appendChild(s);
            return;
        }
        const b = document.createElement("button");
        b.className = "rh-page-btn" + (p === currentPage ? " active" : "");
        b.textContent = p;
        b.onclick = () => {
            currentPage = p;
            renderTable();
        };
        ctrl.appendChild(b);
    });

    const next = document.createElement("button");
    next.className = "rh-page-btn";
    next.textContent = "Next";
    next.disabled = currentPage === totalPages;
    next.onclick = () => {
        if (currentPage < totalPages) {
            currentPage++;
            renderTable();
        }
    };
    ctrl.appendChild(next);
}

function renderTable() {
    const filtered = sortRows(getFilteredRows());
    renderCards(filtered);
    const total = filtered.length;
    const countEl = document.getElementById("filterCount");
    if (countEl) countEl.innerHTML = '<i class="bi bi-list-ul"></i>' + total + " record(s)";
    const totalPages = Math.max(1, Math.ceil(total / rowsPerPage));
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const start = (currentPage - 1) * rowsPerPage;
    const end = Math.min(total, start + rowsPerPage);
    const pageSet = new Set(filtered.slice(start, end));

    rows.forEach((r) => {
        r.style.display = pageSet.has(r) ? "" : "none";
    });

    let emptyRow = tbody.querySelector(".rh-empty");
    if (total === 0) {
        if (!emptyRow) {
            emptyRow = document.createElement("tr");
            emptyRow.className = "rh-empty";
            emptyRow.innerHTML =
                '<td colspan="10" style="text-align:center;padding:28px;color:#8a9a8a;font-size:13px;">No remittance records match the current filters.</td>';
            tbody.appendChild(emptyRow);
        }
    } else if (emptyRow) {
        emptyRow.remove();
    }

    document.getElementById("paginationInfo").textContent =
        total === 0
            ? "Showing 0 to 0 of 0 records"
            : "Showing " + (start + 1) + " to " + end + " of " + total + " records";

    renderPagination(totalPages);
}

function updateSortIcons() {
    sortHeaders.forEach((th) => {
        const span = th.querySelector(".rh-sort-icon");
        if (!span) return;
        if (th.dataset.sort === sortKey) {
            th.classList.add("rh-sort-active");
            span.textContent = sortDir === 1 ? "▲" : "▼";
        } else {
            th.classList.remove("rh-sort-active");
            span.textContent = "";
        }
    });
}

sortHeaders.forEach((th) => {
    th.addEventListener("click", () => {
        const key = th.dataset.sort;
        if (sortKey === key) {
            sortDir = -sortDir;
        } else {
            sortKey = key;
            sortDir = 1;
        }
        currentPage = 1;
updateSortIcons();
updateHistoryExportLinks();
renderTable();
    });
});

searchInput.addEventListener("input", () => {
    currentPage = 1;
    renderTable();
});
statusFilter.addEventListener("change", () => {
    currentPage = 1;
    renderTable();
});
document.addEventListener("financedatefilter:change", (event) => {
    const detail = event.detail || {};
    finState.period = detail.period || finState.period;
    finState.from = detail.start || "";
    finState.to = detail.end || "";
    updateHistoryExportLinks(detail);
    currentPage = 1;
    renderTable();
});

rowsPerPageSelect.addEventListener("change", () => {
    rowsPerPage = parseInt(rowsPerPageSelect.value, 10) || 10;
    currentPage = 1;
    renderTable();
});

updateSortIcons();
renderTable();

document.querySelectorAll(".view-remittance-btn").forEach((button) => {
    button.addEventListener("click", async function () {
        const id = this.dataset.id;
        try {
            const response = await fetch("/finance/history.php?action=view&id=" + id);
            const html = await response.text();
            document.getElementById("modalContent").innerHTML = html;
            document.getElementById("remittanceModal").style.display = "flex";
        } catch (error) {
            console.error(error);
            alert("Unable to load remittance.");
        }
    });
});

document.getElementById("closeModal").addEventListener("click", function () {
    document.getElementById("remittanceModal").style.display = "none";
});
</script>


<script>
let currentOtpId = 0;

const otpModal = document.getElementById("otpModal");
const otpMsg = document.getElementById("otpMsg");

function showOtpMessage(type, text) {
    otpMsg.style.display = "block";
    otpMsg.style.background = type === "success" ? "#e3f0e0" : "#fde8e6";
    otpMsg.style.color = type === "success" ? "#166534" : "#dc2626";
    otpMsg.style.border = type === "success" ? "1px solid #bbf7d0" : "1px solid #fecaca";
    otpMsg.textContent = text;
}

function openOtpModal(id, driverLabel) {
    currentOtpId = parseInt(id, 10) || 0;
    document.getElementById("otpModalSub").textContent =
        "Receipt #" + currentOtpId + (driverLabel ? " - " + driverLabel : "");
    otpMsg.style.display = "none";
    document.getElementById("otpInput").style.display = "none";
    document.getElementById("verifyPrintBtn").style.display = "none";
    const sendBtn = document.getElementById("sendOtpBtn");
    sendBtn.style.display = "block";
    sendBtn.disabled = false;
    sendBtn.textContent = "Send OTP";
    document.getElementById("otpInput").value = "";
    otpModal.style.display = "flex";
}

function closeOtpModal() {
    otpModal.style.display = "none";
}

document.getElementById("closeOtpModal").addEventListener("click", closeOtpModal);

document.getElementById("sendOtpBtn").addEventListener("click", async function () {
    const btn = this;
    btn.disabled = true;
    btn.textContent = "Sending...";
    try {
        const response = await fetch("/finance/history.php?action=request_otp", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "id=" + currentOtpId,
        });
        const data = await response.json();
        if (data.success) {
            showOtpMessage("success", data.message);
            document.getElementById("otpInput").style.display = "block";
            document.getElementById("verifyPrintBtn").style.display = "block";
            document.getElementById("otpInput").focus();
        } else {
            showOtpMessage("error", data.message);
            btn.style.display = "block";
            btn.textContent = "Resend OTP";
        }
    } catch (error) {
        console.error(error);
        showOtpMessage("error", "Unable to send the OTP. Please try again.");
        btn.style.display = "block";
        btn.textContent = "Resend OTP";
    }
    btn.disabled = false;
});

function submitPrintForm() {
    const otp = document.getElementById("otpInput").value.trim();
    if (!/^\d{6}$/.test(otp)) {
        showOtpMessage("error", "Please enter the 6-digit code sent to your email.");
        return;
    }
    const btn = document.getElementById("verifyPrintBtn");
    btn.disabled = true;
    btn.textContent = "Verifying...";

    fetch("/finance/history.php?action=print", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id=" + currentOtpId + "&otp=" + encodeURIComponent(otp),
    })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.success) {
                showOtpMessage("success", "OTP verified. Opening the print dialog...");
                document.getElementById("otpInput").style.display = "none";
                btn.style.display = "none";
                document.getElementById("sendOtpBtn").style.display = "none";

                const iframe = document.getElementById("receiptIframe");
                iframe.srcdoc = data.html;
                iframe.onload = function () {
                    try {
                        iframe.contentWindow.focus();
                        iframe.contentWindow.print();
                    } catch (error) {
                        console.error(error);
                    }
                };
            } else {
                showOtpMessage("error", data.message);
                btn.disabled = false;
                btn.textContent = "Verify & Print";
            }
        })
        .catch(function (error) {
            console.error(error);
            showOtpMessage("error", "Unable to verify the OTP. Please try again.");
            btn.disabled = false;
            btn.textContent = "Verify & Print";
        });
}

document.getElementById("verifyPrintBtn").addEventListener("click", submitPrintForm);

document.getElementById("otpInput").addEventListener("keydown", function (event) {
    if (event.key === "Enter") {
        event.preventDefault();
        submitPrintForm();
    }
});

document.querySelectorAll(".print-otp-btn").forEach((button) => {
    button.addEventListener("click", function () {
        openOtpModal(this.dataset.id, this.dataset.driver);
    });
});

otpModal.addEventListener("click", function (event) {
    if (event.target === otpModal) closeOtpModal();
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") closeOtpModal();
});
</script>

<?php require __DIR__ . '/_aibutton.php'; ?>


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

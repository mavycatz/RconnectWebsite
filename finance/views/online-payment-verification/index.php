<?php header('Cache-Control: no-store, no-cache, must-revalidate'); header('Pragma: no-cache'); ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/views/layout/navbarfinance.php'; ?>

<?php
function opv_dt($dt) { return $dt ? date('M d, Y', strtotime($dt)) : '—'; }
function opv_tm($dt) { return $dt ? date('h:i A', strtotime($dt)) : '—'; }
function opv_fmt($amt) { return '₱' . number_format((float) $amt, 2); }

$summary = $summary ?? [];
$summary += [
    'pending_count' => 0, 'pending_amount' => 0,
    'approved_count' => 0, 'approved_amount' => 0,
    'rejected_count' => 0, 'rejected_amount' => 0,
    'total_count' => 0, 'total_amount' => 0,
];
$donut = $summary['donut'] ?? [];
$bar = $summary['bar'] ?? [];
$hasRef = $hasRef ?? false;
$today = $today ?? date('Y-m-d');
$filterBus = $filterBus ?? '';
$activeTab = $_GET['tab'] ?? 'pending';
$activeTab = in_array($activeTab, ['pending', 'resubmissions', 'history'], true) ? $activeTab : 'pending';

$totalCount = (int) $summary['total_count'];
$donutC = 2 * M_PI * 40;
$donutOffset = 0;
$barMax = 0;
foreach ($bar as $b) { $barMax = max($barMax, (float) $b['amount']); }
function opv_bar_h($amt, $max) { return $max > 0 ? max(5, round($amt / $max * 110)) : 5; }
?>

<style>
.opv-wrap {
    font-family: "Poppins", sans-serif;
    background: #eceed9;
    padding: 20px;
    min-height: 100vh;
}
.opv-card {
    background: #fef8f3;
    border: 2px solid #cdd9b5;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
.opv-title {
    font-weight: 800;
    font-size: 1.4rem;
    color: #2e4836;
    margin: 0 0 2px;
}
.opv-subtitle {
    font-weight: 500;
    font-size: 0.82rem;
    color: #8a9a8a;
    margin: 0;
}
.opv-btn-pdf {
    background: #2e4836;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    font-family: "Poppins", sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: 0.2s;
}
.opv-btn-pdf:hover {
    background: #3d5c49;
    color: #fff;
}
.opv-btn-xls {
    background: #f9f4da;
    color: #2e4836;
    border: 1px solid #cdd9b5;
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    font-family: "Poppins", sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: 0.2s;
}
.opv-btn-xls:hover {
    background: #f4edd0;
}
.opv-card-sum {
    flex: 1;
    min-width: 190px;
    background: #fff;
    border: 1.5px solid #e3eabb;
    border-radius: 16px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.opv-card-sum-dark {
    flex: 1;
    min-width: 190px;
    background: #2e4836;
    border: 1.5px solid #2e4836;
    border-radius: 16px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.opv-card-ico {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    flex-shrink: 0;
}
.opv-card-label {
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}
.opv-card-count {
    font-size: 22px;
    font-weight: 700;
    color: #2e4836;
    line-height: 1.15;
}
.opv-card-amt {
    font-size: 12.5px;
    font-weight: 500;
    color: #6b7280;
}
.opv-card-dark-label {
    font-size: 11px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
    letter-spacing: 0.3px;
    text-transform: uppercase;
}
.opv-card-dark-count {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    line-height: 1.15;
}
.opv-card-dark-amt {
    font-size: 12.5px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.7);
}
.opv-chart-card {
    background: #fff;
    border: 1.5px solid #e3eabb;
    border-radius: 16px;
    padding: 14px 16px;
}
.opv-chart-title {
    font-size: 12.5px;
    font-weight: 700;
    color: #2e4836;
    margin: 0 0 8px;
}
.opv-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    color: #6b7280;
}
.opv-legend-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
}
.opv-legend-count {
    font-weight: 700;
    color: #2e4836;
}
.opv-filter {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    background: #fff;
    border: 1.5px solid #cdd9b5;
    border-radius: 14px;
    padding: 10px 14px;
}
.opv-select,
.opv-date {
    background: #fef8f3;
    border: 1.5px solid #cdd9b5;
    border-radius: 10px;
    padding: 7px 12px;
    font-size: 12px;
    font-family: "Poppins", sans-serif;
    outline: none;
    color: #2e4836;
}
.opv-btn-clear {
    background: #f9f4da;
    border: 1px solid #cdd9b5;
    border-radius: 10px;
    padding: 7px 14px;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    font-family: "Poppins", sans-serif;
    color: #2e4836;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: 0.2s;
}
.opv-btn-clear:hover {
    background: #f4edd0;
}
.opv-tabbar {
    display: flex;
    gap: 0;
    margin: 0 0 14px;
    border-bottom: 2px solid #e3eabb;
}
.opv-tab {
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    font-family: "Poppins", sans-serif;
    transition: 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 7px;
}
.opv-tab:hover {
    color: #2e4836;
}
.opv-tab.active {
    color: #2e4836;
    font-weight: 600;
    border-bottom-color: #2e4836;
}
.opv-tab-badge {
    background: #dc2626;
    color: #fff;
    font-size: 9.5px;
    font-weight: 700;
    border-radius: 999px;
    padding: 1px 7px;
    line-height: 1.5;
}
.opv-table-card {
    background: #fff;
    border: 1.5px solid #e3eabb;
    border-radius: 16px;
    overflow: hidden;
}
.opv-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 1000px;
}
.opv-table thead {
    background: #f9f4da;
}
.opv-table thead th {
    padding: 9px 12px;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #2e4836;
    font-weight: 700;
    text-align: left;
    white-space: nowrap;
}
.opv-table tbody td {
    padding: 9px 12px;
    border-bottom: 1px solid #f0ede0;
    vertical-align: middle;
}
.opv-table tbody tr:hover {
    background: #fbfaf3;
}
.opv-table .num {
    text-align: right;
    font-variant-numeric: tabular-nums;
}
.opv-table .ctr {
    text-align: center;
}
.opv-passenger {
    font-weight: 600;
    color: #2e4836;
    white-space: nowrap;
}
.opv-driver {
    color: #2563eb;
    font-weight: 500;
    white-space: nowrap;
}
.opv-route {
    font-size: 11px;
    color: #8a9a8a;
}
.opv-route .arrow {
    color: #cdd9b5;
}
.opv-amt {
    font-weight: 600;
    color: #2e4836;
    white-space: nowrap;
}
.opv-amt-gcash {
    color: #16a34a;
    font-weight: 600;
    white-space: nowrap;
}
.opv-dt {
    line-height: 1.3;
    white-space: nowrap;
}
.opv-dt .t {
    font-size: 11px;
    color: #8a9a8a;
}
.opv-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 600;
    white-space: nowrap;
}
.opv-badge-gcash {
    background: #e8f5e9;
    color: #16a34a;
}
.opv-badge-hybrid {
    background: #e8f0fe;
    color: #3b82f6;
}
.opv-badge-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}
.opv-badge-status .dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}
.opv-badge-approved {
    background: #e8f5e9;
    color: #16a34a;
}
.opv-badge-approved .dot {
    background: #16a34a;
}
.opv-badge-rejected {
    background: #fee2e2;
    color: #dc2626;
}
.opv-badge-rejected .dot {
    background: #dc2626;
}
.opv-badge-pending {
    background: #fef9c3;
    color: #b45309;
}
.opv-badge-pending .dot {
    background: #eab308;
}
.opv-badge-resub {
    background: #dbeafe;
    color: #2563eb;
    font-size: 9.5px;
    padding: 1px 8px;
    border-radius: 999px;
    font-weight: 700;
}
.opv-btn-proof {
    background: #f9f4da;
    border: 1px solid #cdd9b5;
    border-radius: 8px;
    font-size: 11px;
    color: #2e4836;
    padding: 4px 10px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: 0.2s;
    font-family: "Poppins", sans-serif;
}
.opv-btn-proof:hover {
    background: #f4edd0;
}
.opv-btn-approve {
    background: #2e4836;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 10.5px;
    font-weight: 600;
    padding: 5px 13px;
    margin-right: 4px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: 0.2s;
    font-family: "Poppins", sans-serif;
}
.opv-btn-approve:hover {
    background: #3d5c49;
}
.opv-btn-reject {
    background: #dc2626;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 10.5px;
    font-weight: 600;
    padding: 5px 13px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: 0.2s;
    font-family: "Poppins", sans-serif;
}
.opv-btn-reject:hover {
    background: #b91c1c;
}
.opv-empty {
    padding: 36px 20px;
    text-align: center;
}
.opv-empty .ico {
    font-size: 30px;
    margin-bottom: 6px;
    opacity: 0.45;
}
.opv-empty .t {
    font-weight: 600;
    color: #2e4836;
    font-size: 13.5px;
}
.opv-empty .s {
    font-size: 11.5px;
    color: #6b7280;
    margin-top: 3px;
}
.opv-pager {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px 14px;
    border-top: 1px solid #f0ede0;
    background: #fff;
    border-radius: 0 0 16px 16px;
}
.opv-pager-info {
    font-size: 11px;
    color: #6b7280;
}
.opv-pager-pages {
    display: flex;
    align-items: center;
    gap: 4px;
}
.opv-page-btn {
    background: #fff;
    border: 1px solid #cdd9b5;
    border-radius: 8px;
    min-width: 28px;
    height: 28px;
    font-size: 11px;
    font-weight: 600;
    color: #2e4836;
    cursor: pointer;
    font-family: "Poppins", sans-serif;
    transition: 0.15s;
}
.opv-page-btn:hover {
    background: #f4edd0;
}
.opv-page-btn.active {
    background: #2e4836;
    color: #fff;
    border-color: #2e4836;
}
.opv-page-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.opv-perpage {
    font-size: 11px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
}
.opv-legend {
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
.opv-legend-item {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 11.5px;
    color: #6b7280;
    white-space: nowrap;
}
.opv-legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.opv-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}
.opv-modal.show {
    display: flex;
}
.opv-modal-card {
    background: #fef8f3;
    border: 2px solid #cdd9b5;
    border-radius: 20px;
    padding: 20px;
    max-width: 480px;
    width: 92%;
    position: relative;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    max-height: 90vh;
    overflow-y: auto;
}
.opv-modal-close {
    position: absolute;
    top: 8px;
    right: 12px;
    background: none;
    border: none;
    font-size: 22px;
    color: #888;
    cursor: pointer;
    line-height: 1;
}
.opv-modal-title {
    font-size: 14px;
    font-weight: 700;
    color: #2e4836;
    margin-bottom: 12px;
}
.opv-summary {
    background: #f9f4da;
    border: 1px solid #e3eabb;
    border-radius: 12px;
    padding: 10px 14px;
    margin-bottom: 12px;
    font-size: 12px;
}
.opv-summary .row {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 3px 0;
}
.opv-summary .k {
    color: #6b7280;
}
.opv-summary .v {
    font-weight: 600;
    color: #2e4836;
    text-align: right;
}
.opv-form-label {
    font-size: 11px;
    font-weight: 600;
    color: #2e4836;
    margin-bottom: 4px;
    display: block;
}
.opv-textarea {
    width: 100%;
    border: 1.5px solid #cdd9b5;
    border-radius: 10px;
    padding: 8px 10px;
    font-size: 12px;
    font-family: "Poppins", sans-serif;
    outline: none;
    resize: vertical;
    background: #fff;
    color: #2e4836;
}
.opv-btn-ghost {
    background: #f9f4da;
    border: 1px solid #cdd9b5;
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    font-family: "Poppins", sans-serif;
    color: #2e4836;
}
.opv-btn-ghost:hover {
    background: #f4edd0;
}
.opv-btn-danger {
    background: #dc2626;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    font-family: "Poppins", sans-serif;
    transition: 0.2s;
}
.opv-btn-danger:hover {
    background: #b91c1c;
}
.opv-btn-danger:disabled {
    background: #e8b4b4;
    cursor: not-allowed;
}
.opv-btn-primary {
    background: #2e4836;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    font-family: "Poppins", sans-serif;
    transition: 0.2s;
}
.opv-btn-primary:hover {
    background: #3d5c49;
}
.opv-proof-img-wrap {
    max-height: 48vh;
    overflow-y: auto;
    border-radius: 10px;
    border: 1px solid #e3eabb;
    background: #fff;
    padding: 4px;
    margin-bottom: 12px;
}
.opv-proof-img {
    max-width: 100%;
    border-radius: 8px;
    display: block;
}
</style>

<div class="opv-wrap main-content">
<div class="opv-card">

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/views/layout/_headernav.php'; ?>


<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="opv-title">Online Payment Verification</h1>
        <p class="opv-subtitle">Verify GCash and Hybrid passenger payments.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="opv-btn-pdf opv-export-link" href="/finance/online_payment_verification.php?action=export_pdf&amp;tab=<?= $activeTab ?>">
            <i class="bi bi-file-earmark-pdf"></i> Export PDF
        </a>
        <a class="opv-btn-xls opv-export-link" href="/finance/online_payment_verification.php?action=export_excel&amp;tab=<?= $activeTab ?>">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </a>
    </div>
</div>


<div class="d-flex gap-3 mb-4 flex-wrap">
    <div class="opv-card-sum">
        <div class="opv-card-ico" style="background:#fef9c3;color:#b45309;"><i class="bi bi-clock"></i></div>
        <div>
            <div class="opv-card-label">Pending</div>
            <div class="opv-card-count"><?= $summary['pending_count'] ?></div>
            <div class="opv-card-amt"><?= opv_fmt($summary['pending_amount']) ?></div>
        </div>
    </div>
    <div class="opv-card-sum">
        <div class="opv-card-ico" style="background:#e8f5e9;color:#16a34a;"><i class="bi bi-check-circle"></i></div>
        <div>
            <div class="opv-card-label">Approved</div>
            <div class="opv-card-count" style="color:#16a34a;"><?= $summary['approved_count'] ?></div>
            <div class="opv-card-amt"><?= opv_fmt($summary['approved_amount']) ?></div>
        </div>
    </div>
    <div class="opv-card-sum">
        <div class="opv-card-ico" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-x-circle"></i></div>
        <div>
            <div class="opv-card-label">Rejected</div>
            <div class="opv-card-count" style="color:#dc2626;"><?= $summary['rejected_count'] ?></div>
            <div class="opv-card-amt"><?= opv_fmt($summary['rejected_amount']) ?></div>
        </div>
    </div>
    <div class="opv-card-sum-dark">
        <div class="opv-card-ico" style="background:rgba(255,255,255,.14);color:#fff;"><i class="bi bi-wallet2"></i></div>
        <div>
            <div class="opv-card-dark-label">Total Processed</div>
            <div class="opv-card-dark-count"><?= $summary['approved_count'] + $summary['rejected_count'] ?></div>
            <div class="opv-card-dark-amt"><?= opv_fmt($summary['approved_amount'] + $summary['rejected_amount']) ?></div>
        </div>
    </div>
</div>


<div class="d-flex gap-3 mb-4 flex-wrap">
    <div class="opv-chart-card flex-fill" style="min-width:330px;">
        <div class="opv-chart-title"><i class="bi bi-pie-chart" style="color:#2e4836;"></i> Payment Status Overview</div>
        <div class="d-flex align-items-center justify-content-center gap-4" style="height:170px;">
            <svg width="120" height="120" viewBox="0 0 120 120">
                <?php if ($totalCount > 0): ?>
                    <?php foreach ($donut as $seg): ?>
                        <?php
                        $len = $seg['count'] > 0 ? ($seg['count'] / $totalCount * $donutC) : 0;
                        ?>
                        <circle cx="60" cy="60" r="40" fill="none" stroke="<?= $seg['color'] ?>" stroke-width="16"
                                stroke-dasharray="<?= max($len, 0.5) ?> <?= $donutC ?>"
                                stroke-dashoffset="<?= -$donutOffset ?>"
                                transform="rotate(-90 60 60)"></circle>
                        <?php $donutOffset += $len; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <circle cx="60" cy="60" r="40" fill="none" stroke="#e3eabb" stroke-width="16"
                            stroke-dasharray="<?= $donutC ?> <?= $donutC ?>" transform="rotate(-90 60 60)"></circle>
                <?php endif; ?>
                <text x="60" y="57" text-anchor="middle" font-size="20" font-weight="700" fill="#2e4836" font-family="Poppins"><?= $totalCount ?></text>
                <text x="60" y="74" text-anchor="middle" font-size="8.5" fill="#8a9a8a" font-family="Poppins">Total</text>
            </svg>
            <div class="d-flex flex-column" style="gap:7px;">
                <?php foreach ($donut as $seg): ?>
                <div class="opv-legend-item">
                    <span class="opv-legend-dot" style="background:<?= $seg['color'] ?>;"></span>
                    <span><?= $seg['label'] ?></span>
                    <span class="opv-legend-count"><?= $seg['count'] ?> (<?= $seg['pct'] ?>%)</span>
                    <span style="color:#8a9a8a;"><?= opv_fmt($seg['amount']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="opv-chart-card flex-fill" style="min-width:330px;">
        <div class="opv-chart-title"><i class="bi bi-bar-chart" style="color:#2e4836;"></i> Payment Amount Overview</div>
        <svg width="100%" height="170" viewBox="0 0 320 180" preserveAspectRatio="xMidYMid meet">
            <?php for ($i = 0; $i <= 4; $i++): ?>
                <line x1="20" y1="<?= 160 - $i * 27.5 ?>" x2="300" y2="<?= 160 - $i * 27.5 ?>" stroke="#e3eabb" stroke-width="1"></line>
            <?php endfor; ?>
            <?php
            $bx = [40, 140, 240];
            $idx = 0;
            foreach ($bar as $b):
                $h = opv_bar_h((float) $b['amount'], $barMax);
                $x = $bx[$idx];
            ?>
                <rect x="<?= $x ?>" y="<?= 160 - $h ?>" width="36" height="<?= $h ?>" rx="5" fill="<?= $b['color'] ?>" opacity="0.9"></rect>
                <text x="<?= $x + 18 ?>" y="<?= 160 - $h - 6 ?>" text-anchor="middle" font-size="9.5" font-weight="600" fill="#2e4836" font-family="Poppins"><?= opv_fmt($b['amount']) ?></text>
                <text x="<?= $x + 18 ?>" y="176" text-anchor="middle" font-size="10" fill="#8a9a8a" font-family="Poppins"><?= $b['label'] ?></text>
            <?php
                $idx++;
            endforeach; ?>
        </svg>
    </div>
</div>


<div class="opv-filter mb-3">
    <i class="bi bi-funnel" style="color:#2e4836;font-size:13px;"></i>
    <span style="font-size:12px;font-weight:600;color:#2e4836;">Filters</span>
    <select id="opvFilterBus" class="opv-select" style="min-width:170px;">
        <option value="">All Buses (On Trip)</option>
        <?php foreach ($activeBuses as $b): ?>
        <option value="<?= htmlspecialchars($b['bus_no']) ?>" <?= $filterBus === $b['bus_no'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($b['bus_no']) ?> — <?= htmlspecialchars($b['driver_name'] ?? 'No Driver') ?>
        </option>
        <?php endforeach; ?>
    </select>
    <button id="opvClearFilters" class="opv-btn-clear"><i class="bi bi-arrow-counterclockwise"></i> Clear Filters</button>
    <input type="date" id="opvFilterDate" class="opv-date ms-auto" value="<?= htmlspecialchars($filterDate) ?>" title="Filter by date (empty = all dates)">
</div>


<div class="opv-tabbar">
    <button class="opv-tab active" data-tab="pending">Pending <?= $summary['pending_count'] ? '(' . $summary['pending_count'] . ')' : '' ?></button>
    <button class="opv-tab" data-tab="resubmissions">Resubmissions <?= count($resubmissions) ? '(' . count($resubmissions) . ')' : '' ?>
        <?php if (count($resubmissions) > 0): ?><span class="opv-tab-badge"><?= count($resubmissions) ?></span><?php endif; ?>
    </button>
    <button class="opv-tab" data-tab="history">History</button>
</div>

<?php
function opv_proof_btn($row, $label = '')
{
    if (!empty($row['gcash_screenshot'])) {
        return '<button type="button" class="opv-btn-proof opv-proof-btn" data-id="' . (int) $row['id'] . '" data-type="new" title="View payment proof"><i class="bi bi-image"></i>' . $label . '</button>';
    }
    return '<span style="color:#c8c8c8;font-size:12px;">—</span>';
}

function opv_route_txt($row)
{
    $bp = htmlspecialchars($row['boarding_point'] ?? '');
    $dest = htmlspecialchars($row['destination'] ?? '');
    return $bp ? ($dest ? $bp . ' <span class="arrow">→</span> ' . $dest : $bp) : ($dest ?: '—');
}

function opv_method_badge($row)
{
    $m = strtolower($row['payment_method'] ?? '');
    $cls = $m === 'hybrid' ? 'opv-badge-hybrid' : 'opv-badge-gcash';
    return '<span class="opv-badge ' . $cls . '">' . htmlspecialchars(ucfirst($row['payment_method'] ?? 'GCash')) . '</span>';
}
?>


<div id="opv-tab-pending" class="opv-tab-content">
<div class="opv-table-card">
<?php if (empty($pending)): ?>
<div class="opv-empty">
    <div class="ico"><i class="bi bi-check2-circle"></i></div>
    <div class="t">All Clear</div>
    <div class="s">No pending GCash payments to verify.</div>
</div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="opv-table">
    <thead>
    <tr>
        <th>Passenger</th><th>Bus</th><th>Driver</th><th>Route</th>
        <th class="num">Fare</th><th class="num">GCash Amount</th><th class="ctr">Method</th>
        <th class="ctr">Proof</th><th>Time Submitted</th><th class="ctr">Action</th>
    </tr>
    </thead>
    <tbody id="opv-tbody-pending">
    <?php foreach ($pending as $p): ?>
    <tr class="opv-row"
        data-id="<?= (int) $p['id'] ?>"
        data-bus="<?= htmlspecialchars($p['bus_id'] ?? '') ?>"
        data-date="<?= !empty($p['boarding_time']) ? date('Y-m-d', strtotime($p['boarding_time'])) : '' ?>"
        data-name="<?= htmlspecialchars($p['name'] ?? 'Unknown') ?>"
        data-fare="<?= (float) ($p['fare'] ?? 0) ?>"
        data-gcash="<?= (float) ($p['gcash_amount'] ?? 0) ?>"
        data-method="<?= htmlspecialchars($p['payment_method'] ?? 'gcash') ?>"
        data-ref="<?= $hasRef ? htmlspecialchars((string) ($p['gcash_reference_no'] ?? '')) : '' ?>"
        data-proof="<?= !empty($p['gcash_screenshot']) ? 1 : 0 ?>"
        data-route="<?= htmlspecialchars(trim(strip_tags(opv_route_txt($p)))) ?>"
        data-datetime="<?= htmlspecialchars((string) ($p['boarding_time'] ?? '')) ?>"
        style="display:none;">
        <td class="opv-passenger"><?= htmlspecialchars($p['name'] ?? 'Unknown') ?></td>
        <td style="white-space:nowrap;"><?= htmlspecialchars($p['bus_id']) ?></td>
        <td class="opv-driver"><?= htmlspecialchars($p['driver_id'] ?? '-') ?></td>
        <td class="opv-route"><?= opv_route_txt($p) ?></td>
        <td class="num opv-amt"><?= opv_fmt($p['fare'] ?? 0) ?></td>
        <td class="num opv-amt-gcash"><?= opv_fmt($p['gcash_amount'] ?? 0) ?></td>
        <td class="ctr"><?= opv_method_badge($p) ?></td>
        <td class="ctr"><?= opv_proof_btn($p) ?></td>
        <td class="opv-dt"><?= opv_dt($p['boarding_time'] ?? '') ?><span class="t"><br><?= opv_tm($p['boarding_time'] ?? '') ?></span></td>
        <td class="ctr" style="white-space:nowrap;">
            <button type="button" class="opv-btn-approve opv-approve-btn" data-id="<?= (int) $p['id'] ?>"><i class="bi bi-check-lg"></i> Approve</button>
            <button type="button" class="opv-btn-reject opv-reject-btn" data-id="<?= (int) $p['id'] ?>"><i class="bi bi-x-lg"></i> Reject</button>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<div class="opv-pager">
    <span class="opv-pager-info" id="opv-pager-info-pending">Showing 0 to 0 of 0 records</span>
    <div class="opv-pager-pages" id="opv-pager-pages-pending"></div>
    <label class="opv-perpage">Rows per page:
        <select class="opv-select opv-perpage-select" data-tab="pending" style="padding:4px 8px;font-size:11px;">
            <option value="5">5</option><option value="10" selected>10</option><option value="25">25</option><option value="50">50</option>
        </select>
    </label>
</div>
<?php endif; ?>
</div>
</div>


<div id="opv-tab-resubmissions" class="opv-tab-content" style="display:none;">
<div class="opv-table-card">
<?php if (empty($resubmissions)): ?>
<div class="opv-empty">
    <div class="ico"><i class="bi bi-arrow-repeat"></i></div>
    <div class="t">No Resubmissions</div>
    <div class="s">No passengers have re-uploaded their payment proofs.</div>
</div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="opv-table">
    <thead>
    <tr>
        <th>Passenger</th><th>Bus</th><th>Route</th><th class="num">Fare</th>
        <th class="num">GCash (New)</th><th class="num">Original GCash</th>
        <th>Previous Reason</th><th class="ctr">New Proof</th><th class="ctr">Original Proof</th>
        <th>Time Resubmitted</th><th class="ctr">Action</th>
    </tr>
    </thead>
    <tbody id="opv-tbody-resubmissions">
    <?php foreach ($resubmissions as $p): ?>
    <tr class="opv-row"
        data-id="<?= (int) $p['id'] ?>"
        data-bus="<?= htmlspecialchars($p['bus_id'] ?? '') ?>"
        data-date="<?= !empty($p['resubmitted_at']) ? date('Y-m-d', strtotime($p['resubmitted_at'])) : '' ?>"
        data-name="<?= htmlspecialchars($p['name'] ?? 'Unknown') ?>"
        data-fare="<?= (float) ($p['fare'] ?? 0) ?>"
        data-gcash="<?= (float) ($p['gcash_amount'] ?? 0) ?>"
        data-method="<?= htmlspecialchars($p['payment_method'] ?? 'gcash') ?>"
        data-ref="<?= $hasRef ? htmlspecialchars((string) ($p['gcash_reference_no'] ?? '')) : '' ?>"
        data-proof="<?= !empty($p['gcash_screenshot']) ? 1 : 0 ?>"
        data-route="<?= htmlspecialchars(trim(strip_tags(opv_route_txt($p)))) ?>"
        data-datetime="<?= htmlspecialchars((string) ($p['resubmitted_at'] ?? '')) ?>"
        style="display:none;">
        <td class="opv-passenger"><?= htmlspecialchars($p['name'] ?? 'Unknown') ?> <span class="opv-badge-resub">Resubmitted</span></td>
        <td style="white-space:nowrap;"><?= htmlspecialchars($p['bus_id']) ?></td>
        <td class="opv-route"><?= opv_route_txt($p) ?></td>
        <td class="num opv-amt"><?= opv_fmt($p['fare'] ?? 0) ?></td>
        <td class="num opv-amt-gcash"><?= opv_fmt($p['gcash_amount'] ?? 0) ?></td>
        <td class="num" style="color:#8a9a8a;"><?= !empty($p['original_gcash_amount']) ? opv_fmt($p['original_gcash_amount']) : '—' ?></td>
        <td style="font-size:11px;color:#b45309;"><?= htmlspecialchars((string) ($p['previous_rejection_reason'] ?? '')) ?: '—' ?></td>
        <td class="ctr"><?= opv_proof_btn($p) ?></td>
        <td class="ctr">
            <?php if (!empty($p['original_gcash_screenshot'])): ?>
            <button type="button" class="opv-btn-proof opv-proof-btn" data-id="<?= (int) $p['id'] ?>" data-type="original" title="View original proof"><i class="bi bi-clock-history"></i></button>
            <?php else: ?>
            <span style="color:#c8c8c8;font-size:12px;">—</span>
            <?php endif; ?>
        </td>
        <td class="opv-dt"><?= opv_dt($p['resubmitted_at'] ?? '') ?><span class="t"><br><?= opv_tm($p['resubmitted_at'] ?? '') ?></span></td>
        <td class="ctr" style="white-space:nowrap;">
            <button type="button" class="opv-btn-approve opv-approve-btn" data-id="<?= (int) $p['id'] ?>"><i class="bi bi-check-lg"></i> Approve</button>
            <button type="button" class="opv-btn-reject opv-reject-btn" data-id="<?= (int) $p['id'] ?>"><i class="bi bi-x-lg"></i> Reject</button>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<div class="opv-pager">
    <span class="opv-pager-info" id="opv-pager-info-resubmissions">Showing 0 to 0 of 0 records</span>
    <div class="opv-pager-pages" id="opv-pager-pages-resubmissions"></div>
    <label class="opv-perpage">Rows per page:
        <select class="opv-select opv-perpage-select" data-tab="resubmissions" style="padding:4px 8px;font-size:11px;">
            <option value="5">5</option><option value="10" selected>10</option><option value="25">25</option><option value="50">50</option>
        </select>
    </label>
</div>
<?php endif; ?>
</div>
</div>


<div id="opv-tab-history" class="opv-tab-content" style="display:none;">
<?php if (empty($verified)): ?>
<div class="opv-table-card opv-empty">
    <div class="ico"><i class="bi bi-clock-history"></i></div>
    <div class="t">No History</div>
    <div class="s">No payments have been processed yet.</div>
</div>
<?php else: ?>
<div class="opv-table-card">
<div style="overflow-x:auto;">
<table class="opv-table" style="min-width:800px;">
    <thead>
    <tr>
        <th>Passenger</th><th>Bus</th><th class="num">Fare</th><th class="num">GCash</th><th class="ctr">Method</th>
        <th class="ctr">Status</th><th>Verified By</th><th>Date</th><th class="ctr">Proof</th>
    </tr>
    </thead>
    <tbody id="opv-tbody-history">
    <?php foreach ($verified as $v): ?>
    <?php $isRejected = strtolower($v['payment_status'] ?? '') === 'rejected'; ?>
    <tr class="opv-row"
        data-id="<?= (int) $v['id'] ?>"
        data-bus="<?= htmlspecialchars($v['bus_id'] ?? '') ?>"
        data-date="<?= !empty($v['verified_at']) ? date('Y-m-d', strtotime($v['verified_at'])) : '' ?>"
        data-name="<?= htmlspecialchars($v['name'] ?? 'Unknown') ?>"
        data-fare="<?= (float) ($v['fare'] ?? 0) ?>"
        data-gcash="<?= (float) ($v['gcash_amount'] ?? 0) ?>"
        data-method="<?= htmlspecialchars($v['payment_method'] ?? 'gcash') ?>"
        data-ref="<?= $hasRef ? htmlspecialchars((string) ($v['gcash_reference_no'] ?? '')) : '' ?>"
        data-proof="<?= !empty($v['gcash_screenshot']) ? 1 : 0 ?>"
        data-route="<?= htmlspecialchars(trim(strip_tags(opv_route_txt($v)))) ?>"
        data-datetime="<?= htmlspecialchars((string) ($v['verified_at'] ?? '')) ?>"
        style="display:none;">
        <td class="opv-passenger"><?= htmlspecialchars($v['name'] ?? 'Unknown') ?></td>
        <td style="white-space:nowrap;"><?= htmlspecialchars($v['bus_id']) ?></td>
        <td class="num opv-amt"><?= opv_fmt($v['fare'] ?? 0) ?></td>
        <td class="num opv-amt-gcash"><?= opv_fmt($v['gcash_amount'] ?? 0) ?></td>
        <td class="ctr"><?= opv_method_badge($v) ?></td>
        <td class="ctr">
            <?php if ($isRejected): ?>
            <span class="opv-badge-status opv-badge-rejected"><span class="dot"></span>Rejected</span>
            <?php if (!empty($v['rejection_reason'])): ?><div style="font-size:10px;color:#b45309;margin-top:2px;"><?= htmlspecialchars($v['rejection_reason']) ?></div><?php endif; ?>
            <?php else: ?>
            <span class="opv-badge-status opv-badge-approved"><span class="dot"></span>Approved</span>
            <?php endif; ?>
        </td>
        <td style="white-space:nowrap;"><?= htmlspecialchars($v['verified_by'] ?? '-') ?></td>
        <td class="opv-dt"><?= opv_dt($v['verified_at'] ?? '') ?><span class="t"><br><?= opv_tm($v['verified_at'] ?? '') ?></span></td>
        <td class="ctr"><?= opv_proof_btn($v) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<div class="opv-pager">
    <span class="opv-pager-info" id="opv-pager-info-history">Showing 0 to 0 of 0 records</span>
    <div class="opv-pager-pages" id="opv-pager-pages-history"></div>
    <label class="opv-perpage">Rows per page:
        <select class="opv-select opv-perpage-select" data-tab="history" style="padding:4px 8px;font-size:11px;">
            <option value="5">5</option><option value="10" selected>10</option><option value="25">25</option><option value="50">50</option>
        </select>
    </label>
</div>
</div>
<?php endif; ?>
</div>


<div class="opv-legend">
    <div class="opv-legend-item"><span class="opv-legend-dot" style="background:#16a34a;"></span>Approved – Payment verified</div>
    <div class="opv-legend-item"><span class="opv-legend-dot" style="background:#eab308;"></span>Pending – Awaiting verification</div>
    <div class="opv-legend-item"><span class="opv-legend-dot" style="background:#dc2626;"></span>Rejected – Not accepted</div>
    <div class="opv-legend-item"><span class="opv-legend-dot" style="background:#3b82f6;"></span>Resubmission – New proof submitted</div>
</div>

</div>
</div>

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


<div id="opvProofModal" class="opv-modal">
    <div class="opv-modal-card">
        <button type="button" class="opv-modal-close opv-close-modal" data-target="opvProofModal">&times;</button>
        <div class="opv-modal-title"><i class="bi bi-image" style="color:#2e4836;"></i> Payment Proof</div>
        <div class="opv-summary">
            <div class="row"><span class="k">Passenger Name</span><span class="v" id="opvProofName">—</span></div>
            <div class="row" id="opvProofRefRow" style="display:none;"><span class="k">Reference Number</span><span class="v" id="opvProofRef">—</span></div>
            <div class="row"><span class="k">Submitted Amount</span><span class="v" id="opvProofAmt">—</span></div>
            <div class="row"><span class="k">Submission Date &amp; Time</span><span class="v" id="opvProofDt">—</span></div>
        </div>
        <div class="opv-proof-img-wrap"><img id="opvProofImg" class="opv-proof-img" src="" alt="Payment proof"></div>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="opv-btn-ghost opv-close-modal" data-target="opvProofModal">Close</button>
            <button type="button" class="opv-btn-approve" id="opvProofApprove" style="margin:0;"><i class="bi bi-check-lg"></i> Approve Payment</button>
            <button type="button" class="opv-btn-reject" id="opvProofReject"><i class="bi bi-x-lg"></i> Reject Payment</button>
        </div>
    </div>
</div>


<div id="opvApproveModal" class="opv-modal">
    <div class="opv-modal-card">
        <button type="button" class="opv-modal-close opv-close-modal" data-target="opvApproveModal">&times;</button>
        <div class="opv-modal-title"><i class="bi bi-check-circle" style="color:#16a34a;"></i> Approve Payment?</div>
        <div class="opv-summary">
            <div class="row"><span class="k">Passenger</span><span class="v" id="opvAppName">—</span></div>
            <div class="row"><span class="k">Fare</span><span class="v" id="opvAppFare">—</span></div>
            <div class="row"><span class="k">Submitted GCash</span><span class="v" id="opvAppGcash">—</span></div>
            <div class="row"><span class="k">Payment Method</span><span class="v" id="opvAppMethod">—</span></div>
        </div>
        <div style="font-size:12px;color:#6b7280;margin-bottom:14px;">Confirm that the submitted payment has been verified.</div>
        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="opv-btn-ghost opv-close-modal" data-target="opvApproveModal">Cancel</button>
            <button type="button" class="opv-btn-primary" id="opvConfirmApprove"><i class="bi bi-check-lg"></i> Confirm Approval</button>
        </div>
    </div>
</div>


<div id="opvRejectModal" class="opv-modal">
    <div class="opv-modal-card">
        <button type="button" class="opv-modal-close opv-close-modal" data-target="opvRejectModal">&times;</button>
        <div class="opv-modal-title"><i class="bi bi-x-circle" style="color:#dc2626;"></i> Reject Payment</div>
        <div class="opv-summary">
            <div class="row"><span class="k">Passenger</span><span class="v" id="opvRejName">—</span></div>
            <div class="row"><span class="k">Fare</span><span class="v" id="opvRejFare">—</span></div>
            <div class="row"><span class="k">Submitted GCash</span><span class="v" id="opvRejGcash">—</span></div>
            <div class="row"><span class="k">Payment Method</span><span class="v" id="opvRejMethod">—</span></div>
        </div>
        <label class="opv-form-label">Rejection Reason *</label>
        <select id="opvRejReason" class="opv-select" style="width:100%;margin-bottom:10px;">
            <option value="">Select a reason...</option>
            <option value="Invalid Proof">Invalid Proof</option>
            <option value="Incorrect Amount">Incorrect Amount</option>
            <option value="Duplicate Payment">Duplicate Payment</option>
            <option value="Unclear Screenshot">Unclear Screenshot</option>
            <option value="Reference Number Not Found">Reference Number Not Found</option>
            <option value="Other">Other</option>
        </select>
        <label class="opv-form-label">Additional Notes</label>
        <textarea id="opvRejNotes" class="opv-textarea" rows="3" placeholder="Optional notes..."></textarea>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="opv-btn-ghost opv-close-modal" data-target="opvRejectModal">Cancel</button>
            <button type="button" class="opv-btn-danger" id="opvConfirmReject" disabled><i class="bi bi-x-lg"></i> Reject Payment</button>
        </div>
    </div>
</div>

<script src="/public/login/custom-alert.js"></script>
<script>
(function () {
    "use strict";

    var state = {
        tab: "pending",
        bus: document.getElementById("opvFilterBus").value,
        date: new URLSearchParams(window.location.search).get("date") || "",
        page: 1,
        perPage: 10,
        currentId: null,
        proofType: "new",
    };

    var fmtr = new Intl.NumberFormat("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    function peso(v) {
        return "₱" + fmtr.format(Number(v) || 0);
    }

    function filterQuery() {
        var qs = "?tab=" + encodeURIComponent(state.tab);
        if (state.bus) qs += "&bus=" + encodeURIComponent(state.bus);
        if (state.date) qs += "&date=" + encodeURIComponent(state.date);
        return qs;
    }

    function reloadWithFilters() {
        window.location.href = "/finance/online_payment_verification.php" + filterQuery();
    }

    function rowsFor(tab) {
        return Array.prototype.slice.call(
            document.querySelectorAll("#opv-tbody-" + tab + " .opv-row"),
        );
    }

    function rowById(id) {
        var tabs = ["pending", "resubmissions", "history"];
        for (var i = 0; i < tabs.length; i++) {
            var rows = rowsFor(tabs[i]);
            for (var j = 0; j < rows.length; j++) {
                if (Number(rows[j].dataset.id) === Number(id)) return rows[j];
            }
        }
        return null;
    }

    function visibleRows(tab) {
        return rowsFor(tab).filter(function (r) {
            if (state.bus && r.dataset.bus !== state.bus) return false;
            if (state.date && r.dataset.date && r.dataset.date !== state.date) return false;
            return true;
        });
    }

    function renderPagination(tab) {
        var rows = visibleRows(tab);
        var total = rows.length;
        var perPage = state.perPage;
        var pages = Math.max(1, Math.ceil(total / perPage));
        if (state.page > pages) state.page = pages;

        var start = total === 0 ? 0 : (state.page - 1) * perPage + 1;
        var end = Math.min(total, state.page * perPage);

        rows.forEach(function (r, i) {
            var on = i >= (state.page - 1) * perPage && i < state.page * perPage;
            r.style.display = on ? "" : "none";
        });

        var info = document.getElementById("opv-pager-info-" + tab);
        if (info)
            info.textContent = "Showing " + start + " to " + end + " of " + total + " records";

        var pagesEl = document.getElementById("opv-pager-pages-" + tab);
        if (!pagesEl) return;
        var html = "";
        html +=
            '<button type="button" class="opv-page-btn" data-tab="' +
            tab +
            '" data-page="' +
            (state.page - 1) +
            '"' +
            (state.page <= 1 ? " disabled" : "") +
            '><i class="bi bi-chevron-left"></i></button>';
        for (var p = 1; p <= pages; p++) {
            html +=
                '<button type="button" class="opv-page-btn' +
                (p === state.page ? " active" : "") +
                '" data-tab="' +
                tab +
                '" data-page="' +
                p +
                '">' +
                p +
                "</button>";
        }
        html +=
            '<button type="button" class="opv-page-btn" data-tab="' +
            tab +
            '" data-page="' +
            (state.page + 1) +
            '"' +
            (state.page >= pages ? " disabled" : "") +
            '><i class="bi bi-chevron-right"></i></button>';
        pagesEl.innerHTML = html;
    }

    function applyFilters(tab) {
        state.page = 1;
        renderPagination(tab);
    }

    function switchTab(tab) {
        state.tab = tab;
        document.querySelectorAll(".opv-tab").forEach(function (b) {
            b.classList.toggle("active", b.dataset.tab === tab);
        });
        document.querySelectorAll(".opv-tab-content").forEach(function (c) {
            c.style.display = c.id === "opv-tab-" + tab ? "block" : "none";
        });
        document.querySelectorAll(".opv-export-link").forEach(function (a) {
            a.href =
                "/finance/online_payment_verification.php?action=" +
                (a.classList.contains("opv-btn-pdf") ? "export_pdf" : "export_excel") +
                "&tab=" +
                tab +
                (state.bus ? "&bus=" + encodeURIComponent(state.bus) : "") +
                (state.date ? "&date=" + encodeURIComponent(state.date) : "");
        });
        renderPagination(tab);
    }

    function openModal(id) {
        document.getElementById(id).classList.add("show");
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove("show");
    }

    function fillModal(row, prefix) {
        document.getElementById(prefix + "Name").textContent = row.dataset.name || "—";
        document.getElementById(prefix + "Fare").textContent = peso(row.dataset.fare);
        document.getElementById(prefix + "Gcash").textContent = peso(row.dataset.gcash);
        document.getElementById(prefix + "Method").textContent = (
            row.dataset.method || "gcash"
        ).toUpperCase();
    }

    function openProof(row, type) {
        state.currentId = Number(row.dataset.id);
        state.proofType = type === "original" ? "original" : "new";
        document.getElementById("opvProofName").textContent = row.dataset.name || "—";
        var ref = row.dataset.ref || "";
        var refRow = document.getElementById("opvProofRefRow");
        refRow.style.display = ref ? "flex" : "none";
        document.getElementById("opvProofRef").textContent = ref;
        document.getElementById("opvProofAmt").textContent = peso(row.dataset.gcash);
        document.getElementById("opvProofDt").textContent = row.dataset.datetime || "—";
        document.getElementById("opvProofImg").src =
            "/finance/online_payment_verification.php?action=screenshot&id=" +
            state.currentId +
            (state.proofType === "original" ? "&type=original" : "") +
            "&t=" +
            Date.now();
        openModal("opvProofModal");
    }

    function openApprove(row) {
        state.currentId = Number(row.dataset.id);
        fillModal(row, "opvApp");
        openModal("opvApproveModal");
    }

    function openReject(row) {
        state.currentId = Number(row.dataset.id);
        fillModal(row, "opvRej");
        document.getElementById("opvRejReason").value = "";
        document.getElementById("opvRejNotes").value = "";
        document.getElementById("opvConfirmReject").disabled = true;
        openModal("opvRejectModal");
    }

    function submitVerify(action, extra) {
        var body = new URLSearchParams({ id: state.currentId, action: action });
        if (extra)
            Object.keys(extra).forEach(function (k) {
                body.append(k, extra[k]);
            });
        fetch("/finance/online_payment_verification.php?action=verify", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body,
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                if (data.success) {
                    showAlert("success", "Done", data.message, function () {
                        location.reload();
                    });
                } else {
                    showAlert("error", "Failed", data.message);
                }
            })
            .catch(function () {
                showAlert("error", "System Error", "Unable to process verification.");
            });
    }

    document.querySelectorAll(".opv-tab").forEach(function (btn) {
        btn.addEventListener("click", function () {
            switchTab(this.dataset.tab);
        });
    });

    document.getElementById("opvFilterBus").addEventListener("change", function () {
        state.bus = this.value;
        applyFilters(state.tab);
        reloadWithFilters();
    });
    document.getElementById("opvFilterDate").addEventListener("change", function () {
        state.date = this.value;
        applyFilters(state.tab);
        reloadWithFilters();
    });
    document.getElementById("opvClearFilters").addEventListener("click", function () {
        state.bus = "";
        state.date = "";
        document.getElementById("opvFilterDate").value = "";
        document.getElementById("opvFilterBus").value = "";
        applyFilters(state.tab);
        window.location.href =
            "/finance/online_payment_verification.php?tab=" + encodeURIComponent(state.tab);
    });

    document.addEventListener("click", function (e) {
        var btn = e.target.closest(".opv-page-btn");
        if (btn && !btn.disabled) {
            state.page = Number(btn.dataset.page);
            renderPagination(btn.dataset.tab);
        }
    });
    document.querySelectorAll(".opv-perpage-select").forEach(function (sel) {
        sel.addEventListener("change", function () {
            state.perPage = Number(this.value);
            state.page = 1;
            renderPagination(this.dataset.tab);
        });
    });

    document.querySelectorAll(".opv-proof-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var row = rowById(this.dataset.id);
            if (row) openProof(row, this.dataset.type);
        });
    });
    document.getElementById("opvProofApprove").addEventListener("click", function () {
        var row = rowById(state.currentId);
        if (row) {
            closeModal("opvProofModal");
            openApprove(row);
        }
    });
    document.getElementById("opvProofReject").addEventListener("click", function () {
        var row = rowById(state.currentId);
        if (row) {
            closeModal("opvProofModal");
            openReject(row);
        }
    });

    document.querySelectorAll(".opv-approve-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var row = rowById(this.dataset.id);
            if (row) openApprove(row);
        });
    });
    document.getElementById("opvConfirmApprove").addEventListener("click", function () {
        submitVerify("approve");
    });

    document.querySelectorAll(".opv-reject-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var row = rowById(this.dataset.id);
            if (row) openReject(row);
        });
    });
    document.getElementById("opvRejReason").addEventListener("change", function () {
        document.getElementById("opvConfirmReject").disabled = !this.value;
    });
    document.getElementById("opvConfirmReject").addEventListener("click", function () {
        submitVerify("reject", {
            rejection_reason: document.getElementById("opvRejReason").value,
            rejection_notes: document.getElementById("opvRejNotes").value,
        });
    });

    document.querySelectorAll(".opv-close-modal").forEach(function (btn) {
        btn.addEventListener("click", function () {
            closeModal(this.dataset.target);
        });
    });
    document.querySelectorAll(".opv-modal").forEach(function (m) {
        m.addEventListener("click", function (e) {
            if (e.target === this) this.classList.remove("show");
        });
    });

    var activeTab = document.querySelector(".opv-tab.active");
    switchTab(activeTab ? activeTab.dataset.tab : "pending");
    document.getElementById("opvFilterDate").value = state.date;
})();
</script>

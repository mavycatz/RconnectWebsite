<?php
$r = $remittance ?? [];
$status = $r['status'] ?? 'Pending';
$statusKey = strtolower($status);
$discrepancy = (float)($r['discrepancy_amount'] ?? 0);
$expected = (float)($r['expected_cash'] ?? 0);
$actual = (float)($r['actual_cash'] ?? 0);
$totalCollection = (float)($r['total_collection'] ?? 0);
$gcash = round($totalCollection - $actual, 2);
$showGcash = $totalCollection > $actual;
$verifiedBy = $r['confirmed_by'] ?? 'Finance Manager';
$verifiedAt = $r['reviewed_at'] ?? $r['created_at'] ?? null;
$created = strtotime($r['created_at'] ?? 'now');
$businessDate = (string) ($r['business_date'] ?? date('Y-m-d', $created));
$businessTs = strtotime($businessDate) ?: $created;
$trips = $r['trips'] ?? [];

$badgeClass = 'rh-badge-gray';
if ($statusKey === 'completed') $badgeClass = 'rh-badge-green';
elseif ($statusKey === 'discrepancy') $badgeClass = 'rh-badge-red';
elseif ($statusKey === 'no remit') $badgeClass = 'rh-badge-amber';

$discrepancyClass = 'rhd-amt-neutral';
if ($statusKey === 'completed') $discrepancyClass = 'rhd-amt-green';
elseif ($statusKey === 'discrepancy') $discrepancyClass = 'rhd-amt-red';
elseif ($statusKey === 'no remit') $discrepancyClass = 'rhd-amt-amber';
?>
<style>
.rhd {
    font-family: "Poppins", sans-serif;
    color: #374151;
    font-size: 13px;
}
.rhd-sec {
    margin-bottom: 18px;
}
.rhd-sec:last-child {
    margin-bottom: 0;
}
.rhd-sec-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #2e4836;
    margin-bottom: 12px;
    padding-bottom: 7px;
    border-bottom: 1px solid #f1ecdc;
}
.rhd-sec-title i {
    color: #8a9a8a;
}
.rhd-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 16px;
}
.rhd-grid .rhd-full {
    grid-column: 1 / -1;
}
.rhd-label {
    font-size: 11px;
    color: #8a9a8a;
    display: block;
    margin-bottom: 2px;
}
.rhd-val {
    font-weight: 600;
    color: #2e4836;
}
.rhd-val-muted {
    font-weight: 500;
    color: #6b7280;
}
.rhd-amt {
    font-weight: 700;
    color: #374151;
    font-variant-numeric: tabular-nums;
}
.rhd-amt-green {
    color: #16a34a;
}
.rhd-amt-red {
    color: #dc2626;
}
.rhd-amt-amber {
    color: #d97706;
}
.rhd-shortage {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #b91c1c;
    border-radius: 10px;
    padding: 10px 14px;
    font-weight: 700;
    font-size: 13px;
    margin-top: 16px;
}
.rhd-verified {
    font-size: 11.5px;
    color: #8a9a8a;
    margin-top: 4px;
}
</style>

<div class="rhd">

    <div class="rhd-sec">
        <div class="rhd-sec-title"><i class="bi bi-person"></i> Driver Information</div>
        <div class="rhd-grid">
            <div>
                <span class="rhd-label">Driver Name</span>
                <span class="rhd-val"><?= htmlspecialchars($r['full_name'] ?? 'N/A') ?></span>
            </div>
            <div>
                <span class="rhd-label">Driver ID</span>
                <span class="rhd-val-muted"><?= htmlspecialchars($r['driver_id'] ?? '') ?></span>
            </div>
            <div>
                <span class="rhd-label">Bus</span>
                <span class="rhd-val-muted"><?= htmlspecialchars($r['bus_no'] ?? 'N/A') ?></span>
            </div>
            <div>
                <span class="rhd-label">Business Date</span>
                <span class="rhd-val-muted"><?= date('M d, Y', $businessTs) ?></span>
            </div>
        </div>
    </div>

    <div class="rhd-sec">
        <div class="rhd-sec-title"><i class="bi bi-cash-coin"></i> Remittance Summary</div>
        <div class="rhd-grid">
            <div>
                <span class="rhd-label">Total Collection</span>
                <span class="rhd-amt">₱<?= number_format($totalCollection, 2) ?></span>
            </div>
            <div>
                <span class="rhd-label">Cash</span>
                <span class="rhd-amt">₱<?= number_format($actual, 2) ?></span>
            </div>
            <?php if ($showGcash): ?>
            <div>
                <span class="rhd-label">GCash</span>
                <span class="rhd-amt">₱<?= number_format($gcash, 2) ?></span>
            </div>
            <?php endif; ?>
            <div>
                <span class="rhd-label">Expected Remittance</span>
                <span class="rhd-amt">₱<?= number_format($expected, 2) ?></span>
            </div>
            <div>
                <span class="rhd-label">Actual Remittance</span>
                <span class="rhd-amt <?= ($statusKey === 'discrepancy' || $statusKey === 'no remit') ? 'rhd-amt-' . ($statusKey === 'discrepancy' ? 'red' : 'amber') : '' ?>">₱<?= number_format($actual, 2) ?></span>
            </div>
            <div>
                <span class="rhd-label">Discrepancy</span>
                <span class="rhd-amt <?= $discrepancyClass ?>">₱<?= number_format($discrepancy, 2) ?></span>
            </div>
        </div>
    </div>

    <div class="rhd-sec">
        <div class="rhd-sec-title"><i class="bi bi-shield-check"></i> Verification Information</div>
        <div class="rhd-grid">
            <div>
                <span class="rhd-label">Status</span>
                <span class="rh-badge <?= $badgeClass ?>">
                    <span class="rh-dot"></span><?= htmlspecialchars($status) ?>
                </span>
            </div>
            <div>
                <span class="rhd-label">Verified By</span>
                <span class="rhd-val-muted"><?= htmlspecialchars($verifiedBy) ?></span>
            </div>
            <div class="rhd-full">
                <span class="rhd-label">Verification Date and Time</span>
                <span class="rhd-val-muted"><?= $verifiedAt ? date('M d, Y h:i A', strtotime($verifiedAt)) : 'Not yet verified' ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($trips)): ?>
    <div class="rhd-sec">
        <div class="rhd-sec-title"><i class="bi bi-receipt"></i> Trips Covered (<?= count($trips) ?>)</div>
        <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:#f7f5e8;color:#2e4836;">
                    <th style="text-align:left;padding:6px 8px;border:1px solid #eee7d0;">Trip #</th>
                    <th style="text-align:left;padding:6px 8px;border:1px solid #eee7d0;">Bus</th>
                    <th style="text-align:right;padding:6px 8px;border:1px solid #eee7d0;">Collection</th>
                    <th style="text-align:right;padding:6px 8px;border:1px solid #eee7d0;">Cash</th>
                    <th style="text-align:right;padding:6px 8px;border:1px solid #eee7d0;">QR Ph</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($trips as $trip): ?>
                <tr>
                    <td style="padding:6px 8px;border:1px solid #f1ecdc;"><?= htmlspecialchars((string) ($trip['trip_no'] ?? '')) ?></td>
                    <td style="padding:6px 8px;border:1px solid #f1ecdc;"><?= htmlspecialchars((string) ($trip['bus_no'] ?? '')) ?></td>
                    <td style="padding:6px 8px;border:1px solid #f1ecdc;text-align:right;">₱<?= number_format((float) ($trip['total_collection'] ?? 0), 2) ?></td>
                    <td style="padding:6px 8px;border:1px solid #f1ecdc;text-align:right;">₱<?= number_format((float) ($trip['cash_total'] ?? 0), 2) ?></td>
                    <td style="padding:6px 8px;border:1px solid #f1ecdc;text-align:right;">₱<?= number_format((float) ($trip['gcash_total'] ?? 0), 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($discrepancy > 0): ?>
    <div class="rhd-shortage">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Shortage: ₱<?= number_format($discrepancy, 2) ?>
    </div>
    <?php endif; ?>

</div>

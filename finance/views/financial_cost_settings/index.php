<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/../../middleware/csrf.php';

use Finance\Middleware\Csrf;

$csrfToken = Csrf::generate();

$icons = [
    'amortization' => 'bi-cash-stack',
    'insurance' => 'bi-shield-check',
    'gps' => 'bi-geo-alt-fill',
    'optr' => 'bi-receipt',
    'gas' => 'bi-fuel-pump-fill',
];

$descriptions = [
    'amortization' => 'Allocation of bus acquisition costs used in driver net income calculations. Charged as a fixed amount per month.',
    'insurance' => 'Allocated insurance costs associated with bus operations, deducted from each driver\'s net income.',
    'gps' => 'GPS tracking expenses allocated according to the existing financial calculation rules.',
    'optr' => 'Other operating expenses, such as applicable permits and regulatory fees.',
    'gas' => 'Fuel cost calculated from actual consumption and the applicable price per liter from Fuel Prices. Recorded fuel costs take precedence when available.',
];

$unitPhrases = [
    'PHP/month' => 'per month',
    'PHP/day' => 'per day',
    'PHP/trip' => 'per trip',
    '% of gross' => 'of gross income',
    'PHP/liter' => 'per liter',
];

$fieldLabels = [
    'amount' => 'Amount',
    'rate' => 'Rate',
    'calc_method' => 'Calculation method',
    'unit' => 'Unit',
    'effective_date' => 'Effective date',
    'status' => 'Status',
    'notes' => 'Notes',
    'price_per_liter' => 'Price / liter (₱)',
];

$fmtValue = function (string $field, $value) use ($methodLabels): string {
    if ($field === 'price_per_liter') {
        return number_format((float) $value, 2) . ' ₱';
    }
    if ($field === 'amount' || $field === 'rate') {
        return number_format((float) $value, 2);
    }
    if ($field === 'calc_method') {
        return $methodLabels[(string) $value] ?? (string) $value;
    }
    return (string) $value;
};

$currentValue = function (string $key, array $row) use ($fuelPrice, $unitPhrases): string {
    if ($key === 'gas') {
        return 'Actual consumption × ₱' . number_format((float) $fuelPrice, 2) . ' / liter';
    }
    if ((string) $row['calc_method'] === 'percent_gross') {
        return number_format((float) $row['rate'], 2) . '% of gross income';
    }
    $unit = (string) $row['unit'];
    return '₱' . number_format((float) $row['amount'], 2) . ' ' . ($unitPhrases[$unit] ?? $unit);
};

/* Data handed to the editor modal. */
$jsCosts = [];
foreach ($settings as $key => $row) {
    $jsCosts[$key] = [
        'label' => (string) $row['label'],
        'description' => (string) ($descriptions[$key] ?? ''),
        'amount' => number_format((float) $row['amount'], 2, '.', ''),
        'rate' => number_format((float) $row['rate'], 2, '.', ''),
        'method' => (string) $row['calc_method'],
        'effective_date' => (string) $row['effective_date'],
        'status' => (string) $row['status'],
        'notes' => (string) ($row['notes'] ?? ''),
        'is_gas' => $key === 'gas',
    ];
}

$jsFuel = [];
foreach ($fuelPrices as $fuelRow) {
    $jsFuel[(string) $fuelRow['id']] = [
        'label' => (string) $fuelRow['fuel_type'],
        'price' => number_format((float) $fuelRow['price_per_liter'], 2, '.', ''),
        'updated_at' => (string) ($fuelRow['updated_at'] ?? ''),
    ];
}

/* Pre-format the audit trail for filtering, pagination and expandable details. */
$auditRows = [];
foreach ($audit as $entry) {
    $prev = is_array($entry['previous_values'] ?? null) ? $entry['previous_values'] : [];
    $next = is_array($entry['new_values'] ?? null) ? $entry['new_values'] : [];
    $reason = (string) ($next['reason'] ?? '');

    $changes = [];
    foreach ($fieldLabels as $field => $label) {
        if (!array_key_exists($field, $next)) {
            continue;
        }
        $old = $fmtValue($field, $prev[$field] ?? '');
        $new = $fmtValue($field, $next[$field]);
        if ($old === $new) {
            continue;
        }
        $changes[] = ['label' => $label, 'old' => $old, 'new' => $new];
    }

    $recordId = (string) ($entry['record_id'] ?? '');
    if (str_starts_with($recordId, 'COST-')) {
        $k = strtolower(substr($recordId, 5));
        $settingName = (string) ($settings[$k]['label'] ?? strtoupper($k));
    } elseif (str_starts_with($recordId, 'FUEL-')) {
        $fuelType = trim((string) ($next['fuel_type'] ?? ''));
        $settingName = $fuelType !== '' ? $fuelType . ' price' : 'Fuel price #' . substr($recordId, 5);
    } else {
        $settingName = $recordId !== '' ? $recordId : 'Cost settings';
    }

    $searchParts = [$settingName, (string) ($entry['username'] ?? ''), (string) ($entry['user_role'] ?? ''), $reason];
    foreach ($changes as $change) {
        $searchParts[] = $change['label'] . ' ' . $change['old'] . ' ' . $change['new'];
    }

    $auditRows[] = [
        'created_at' => (string) ($entry['created_at'] ?? ''),
        'date' => substr((string) ($entry['created_at'] ?? ''), 0, 10),
        'setting' => $settingName,
        'username' => (string) ($entry['username'] ?? 'System'),
        'role' => (string) ($entry['user_role'] ?? ''),
        'reason' => $reason,
        'changes' => $changes,
        'search' => strtolower(implode(' ', $searchParts)),
    ];
}
?>
<?php include __DIR__ . '/../layout/navbarfinance.php'; ?>

<style>
.fcs-wrap { font-family: 'Poppins', sans-serif; }
.fcs-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.fcs-title { font-weight: 800; font-size: 1.3rem; color: #295738; margin: 0 0 2px; }
.fcs-subtitle { font-size: 0.8rem; color: #8a9a8a; margin: 0; }
.fcs-chip { display: inline-flex; align-items: center; gap: 7px; background: #f9f4da; border: 1px solid #e5e0a4; color: #2e4836; border-radius: 999px; padding: 6px 14px; font-size: 0.78rem; font-weight: 600; }
.fcs-chip i { color: #295738; }
.fcs-banner { background: #eef2dc; border: 1px solid #cdd9b5; border-left: 4px solid #295738; border-radius: 12px; padding: 12px 16px; font-size: 0.78rem; color: #3a4a3a; line-height: 1.55; margin-bottom: 18px; }
.fcs-banner strong { color: #295738; }
.fcs-panel { background: #fff; border: 1px solid #e7e3af; border-radius: 16px; padding: 18px; box-shadow: 0 3px 8px rgba(0, 0, 0, 0.04); margin-bottom: 18px; }
.fcs-panel-head { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.fcs-panel-head h5 { font-weight: 700; color: #295738; font-size: 0.98rem; margin: 0 0 3px; }
.fcs-panel-head p { font-size: 0.75rem; color: #8a9a8a; margin: 0; }
.fcs-table { width: 100%; border-collapse: collapse; font-size: 0.76rem; color: #2e4836; }
.fcs-table th { text-align: left; background: #f9f4da; color: #6f7d6f; font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 9px 10px; border-bottom: 1px solid #e5e0a4; white-space: nowrap; }
.fcs-table td { padding: 10px; border-bottom: 1px solid #f1ecdc; vertical-align: top; }
.fcs-table tbody tr:last-child td { border-bottom: none; }
.fcs-table tbody tr:hover td { background: #fbf9ef; }
.fcs-cost-name { display: flex; gap: 10px; align-items: flex-start; min-width: 260px; }
.fcs-cost-icon { width: 34px; height: 34px; border-radius: 10px; background: #e8f6ef; color: #2e4836; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
.fcs-cost-label { font-weight: 700; color: #295738; font-size: 0.82rem; }
.fcs-cost-desc { font-size: 0.7rem; color: #8a9a8a; line-height: 1.45; margin-top: 2px; }
.fcs-value { font-weight: 700; color: #2e4836; white-space: nowrap; }
.fcs-value-sub { display: block; font-size: 0.66rem; font-weight: 400; color: #8a9a8a; margin-top: 2px; }
.fcs-status { display: inline-block; font-size: 0.64rem; font-weight: 700; border-radius: 999px; padding: 3px 10px; letter-spacing: 0.4px; }
.fcs-status.active { background: #dcfce7; color: #166534; }
.fcs-status.inactive { background: #f3f4f6; color: #6b7280; }
.fcs-muted { color: #8a9a8a; }
.fcs-readonly { background: #fdf6dd; border: 1px solid #e5e0a4; border-radius: 10px; padding: 10px 14px; font-size: 0.76rem; color: #7a6a12; display: flex; align-items: center; gap: 8px; }
.fcs-field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.fcs-field label { font-size: 0.72rem; font-weight: 600; color: #6b7280; }
.fcs-field input, .fcs-field select, .fcs-field textarea { width: 100%; box-sizing: border-box; border: 1.5px solid #cdd9b5; border-radius: 10px; background: #fef8f3; color: #2e4836; padding: 9px 11px; font-size: 0.8rem; font-family: inherit; outline: none; }
.fcs-field input:focus, .fcs-field select:focus, .fcs-field textarea:focus { border-color: #9db88b; box-shadow: 0 0 0 3px rgba(41, 87, 56, 0.1); }
.fcs-field textarea { min-height: 64px; resize: vertical; }
/* =========================================================================
   Reference modal design — copied from admin/views/role-management
   (admin/assets/css/role-management.css) so the cost settings modals match
   the Role Management modal exactly (same shell, header, sections, fields,
   buttons, footer and responsive rules).
   ========================================================================= */
.rmg-btn { display: inline-flex; align-items: center; gap: 6px; border-radius: 20px; font-size: .78rem; font-weight: 600; padding: 8px 16px; cursor: pointer; transition: all .18s ease; border: 1px solid #d8d2b6; background: #fff; color: #295738; text-decoration: none; }
.rmg-btn:hover { background: #f4f1e6; }
.rmg-btn:disabled { opacity: .5; cursor: not-allowed; }
.rmg-btn-primary { background: #295738; border-color: #295738; color: #fff; }
.rmg-btn-primary:hover { background: #1e4229; }
.rmg-btn-sm { padding: 6px 12px; font-size: .72rem; }

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
.rmg-form-body { padding: 16px 22px 6px; background: #fffdf8; }
.rmg-form-footer { border-top: 1px solid #eef1e4; background: #fdfbf4; padding: 12px 22px; display: flex; gap: 8px; justify-content: flex-end; }

.rmg-detail-section { background: #fff; border: 1px solid #eef1e4; border-radius: 16px; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(41,87,56,.04); }
.rmg-section-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px dashed #e9eddc; }
.rmg-section-icon { width: 34px; height: 34px; flex-shrink: 0; display: grid; place-items: center; border-radius: 10px; background: #eef5e7; color: #295738; font-size: .95rem; }
.rmg-section-icon.tone-role { background: #eef1f5; color: #4f637a; }
.rmg-section-icon.tone-account { background: #fdf5e3; color: #9a6b12; }
.rmg-section-icon.tone-docs { background: #f3eee2; color: #7a6f4f; }
.rmg-section-title { font-size: .84rem; font-weight: 800; color: #295738; }

.rmg-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 14px; }
.rmg-form-field { display: flex; flex-direction: column; gap: 4px; }
.rmg-form-field.full { grid-column: 1 / -1; }
.rmg-form-field label { font-size: .64rem; font-weight: 700; color: #8a9a8a; text-transform: uppercase; letter-spacing: .05em; }
.rmg-form-field input, .rmg-form-field select, .rmg-form-field textarea { border: 1px solid #e5e0a4; border-radius: 10px; padding: 8px 10px; font-size: .78rem; background: #fffdf8; color: #2e4836; outline: none; width: 100%; box-sizing: border-box; font-family: inherit; }
.rmg-form-field textarea { min-height: 64px; resize: vertical; }
.rmg-form-field input:focus, .rmg-form-field select:focus, .rmg-form-field textarea:focus { border-color: #9db88b; box-shadow: 0 0 0 3px rgba(41,87,56,.1); }
.rmg-field-hint { font-size: .62rem; color: #8a9a8a; margin-top: 6px; line-height: 1.45; }
.rmg-error { margin-bottom: 12px; background: #fbe9e7; border: 1px solid #eccac5; color: #b94a48; border-radius: 10px; padding: 8px 12px; font-size: .74rem; }
.rmg-note { margin-bottom: 14px; background: #f6f8ec; border: 1px solid #e5e0a4; border-radius: 10px; padding: 9px 12px; font-size: .72rem; color: #6b7a63; line-height: 1.5; }
.rmg-note strong { color: #295738; }
.fcs-inline-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.fcs-inline-row input { flex: 1 1 200px; min-width: 0; border: 1px solid #e5e0a4; border-radius: 10px; padding: 8px 10px; font-size: .78rem; background: #fffdf8; color: #2e4836; outline: none; font-family: inherit; box-sizing: border-box; }
.fcs-inline-row input:focus { border-color: #9db88b; box-shadow: 0 0 0 3px rgba(41,87,56,.1); }
.fcs-audit-controls { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; margin-bottom: 12px; }
.fcs-audit-controls .fcs-field { margin-bottom: 0; }
.fcs-audit-controls input { min-width: 130px; }
.fcs-audit-search { flex: 1 1 220px; }
.fcs-pager { display: flex; align-items: center; justify-content: flex-end; gap: 10px; margin-top: 10px; font-size: 0.74rem; color: #6b7280; }
.fcs-pager button { background: #fff; border: 1px solid #cdd9b5; color: #2e4836; border-radius: 8px; padding: 5px 12px; font-size: 0.72rem; font-weight: 600; cursor: pointer; font-family: inherit; }
.fcs-pager button:disabled { opacity: 0.5; cursor: not-allowed; }
.fcs-change { display: block; white-space: nowrap; }
.fcs-old { color: #b3261e; text-decoration: line-through; }
.fcs-new { color: #166534; font-weight: 600; }
.fcs-details { margin-top: 6px; }
.fcs-details summary { cursor: pointer; font-size: 0.68rem; font-weight: 600; color: #57694d; outline: none; }
.fcs-details-body { margin-top: 6px; display: flex; flex-direction: column; gap: 4px; }
.fcs-empty { text-align: center; color: #8a9a8a; padding: 24px; font-size: 0.78rem; }
.fcs-change-list { display: flex; flex-direction: column; gap: 10px; }
.fcs-change-group { background: #fff; border: 1px solid #eef1e4; border-radius: 16px; padding: 12px 14px; box-shadow: 0 1px 3px rgba(41,87,56,.04); }
.fcs-change-group h6 { margin: 0 0 8px; padding-bottom: 8px; border-bottom: 1px dashed #e9eddc; font-size: .82rem; font-weight: 800; color: #295738; }
.fcs-change-line { display: flex; flex-wrap: wrap; gap: 6px; align-items: baseline; font-size: .76rem; color: #4b5a4b; margin-bottom: 4px; }
.fcs-change-line:last-child { margin-bottom: 0; }
.fcs-change-field { font-weight: 700; color: #8a9a8a; min-width: 130px; text-transform: uppercase; font-size: .64rem; letter-spacing: .04em; }
.fcs-modal-note { font-size: .72rem; color: #6b7a63; margin: 0 0 12px; }
.fcs-factor-status { font-size: .62rem; font-weight: 700; border-radius: 999px; padding: 3px 10px; white-space: nowrap; }
.fcs-factor-status.pending { background: #f4f1e6; color: #7a857a; border: 1px solid #e5e0a4; }
.fcs-factor-status.ok { background: #eaf6ea; color: #2e7d32; border: 1px solid #c7e3c7; }
.fcs-factor-status.fail { background: #fbe9e7; color: #b94a48; border: 1px solid #eccac5; }
@media (max-width: 575px) {
    .rmg-form-grid { grid-template-columns: 1fr; }
    .rmg-form-header { padding: 16px; }
    .rmg-form-body { padding: 14px 14px 4px; }
    .rmg-form-footer { padding: 12px 14px; }
    .fcs-audit-controls { flex-direction: column; align-items: stretch; }
}
</style>

<div class="main-content" id="mainContent" style="font-family:'Poppins', sans-serif; background:#eceed9; padding:20px;">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
        <div class="container-fluid p-0">
            <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>

            <div class="fcs-wrap" style="margin-top:16px;">

                <div class="fcs-header">
                    <div>
                        <h1 class="fcs-title"><i class="bi bi-calculator-fill me-2" style="color:#295738;"></i>Financial Cost Settings</h1>
                        <p class="fcs-subtitle">Configure the driver deductions used by Financial Performance. Each cost has one reviewable edit form — only fields that apply to the selected calculation method are shown.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="fcs-chip"><i class="bi bi-person-badge"></i><?= htmlspecialchars($roleLabel ?? '') ?></span>
                        <span class="fcs-chip"><i class="bi bi-cash-coin"></i>₱<?= number_format($monthlyTotal, 2) ?> / driver / month</span>
                        <span class="fcs-chip"><i class="bi bi-fuel-pump"></i>Fuel ₱<?= number_format($fuelPrice, 2) ?> / liter</span>
                    </div>
                </div>

                <div class="fcs-banner">
                    <strong>How deductions are applied:</strong>
                    Driver Net Income = Gross Income − Fixed Costs (amortization + insurance + GPS + OPTR, scaled to the selected period)
                    − Gas. Gas uses the <strong>actual recorded fuel cost</strong> when available; otherwise it is computed from
                    <strong>actual liters consumed × the applicable price per liter from Fuel Prices</strong>. An <strong>Inactive</strong> setting is not applied —
                    calculations fall back to the built-in default value for that cost. Changes apply to future calculations only and every change is audit-logged.
                </div>

                <div class="fcs-panel">
                    <div class="fcs-panel-head">
                        <div>
                            <h5><i class="bi bi-sliders me-2" style="color:#295738;"></i>Cost Settings</h5>
                            <p>One row per deduction. Use Edit to review or change a cost — you will see a before-and-after summary before anything is saved.</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="fcs-table">
                            <thead>
                                <tr>
                                    <th>Cost</th>
                                    <th>Current Value</th>
                                    <th>Calculation Method</th>
                                    <th>Effective Date</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <?php if ($canEdit): ?><th style="text-align:right;">Action</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($settings as $key => $row): ?>
                                    <?php $isGas = ($key === 'gas'); ?>
                                    <tr>
                                        <td>
                                            <div class="fcs-cost-name">
                                                <div class="fcs-cost-icon"><i class="bi <?= htmlspecialchars($icons[$key] ?? 'bi-gear') ?>"></i></div>
                                                <div>
                                                    <div class="fcs-cost-label"><?= htmlspecialchars($row['label']) ?></div>
                                                    <div class="fcs-cost-desc"><?= htmlspecialchars($descriptions[$key] ?? '') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="fcs-value">
                                            <?= htmlspecialchars($currentValue($key, $row)) ?>
                                            <?php if ($isGas && !empty($applicableFuel)): ?>
                                                <span class="fcs-value-sub"><?= htmlspecialchars($applicableFuel['fuel_type']) ?> · from Fuel Prices</span>
                                            <?php elseif (!$isGas): ?>
                                                <span class="fcs-value-sub"><?= htmlspecialchars($unitPhrases[(string) $row['unit']] ?? (string) $row['unit']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($methodLabels[(string) $row['calc_method']] ?? (string) $row['calc_method']) ?></td>
                                        <td class="fcs-muted"><?= htmlspecialchars((string) $row['effective_date']) ?></td>
                                        <td>
                                            <span class="fcs-status <?= strtolower((string) $row['status']) === 'active' ? 'active' : 'inactive' ?>">
                                                <?= htmlspecialchars(strtoupper((string) $row['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="fcs-muted">
                                            <?php if (!empty($row['updated_at'])): ?>
                                                <?= htmlspecialchars((string) $row['updated_at']) ?><?= !empty($row['updated_by']) ? '<br>by ' . htmlspecialchars((string) $row['updated_by']) : '' ?>
                                            <?php else: ?>
                                                Default value
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($canEdit): ?>
                                            <td style="text-align:right;">
                                                <button type="button" class="rmg-btn rmg-btn-sm" data-edit-cost="<?= htmlspecialchars($key) ?>">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="fcs-panel">
                    <div class="fcs-panel-head">
                        <div>
                            <h5><i class="bi bi-fuel-pump-fill me-2" style="color:#295738;"></i>Fuel Prices</h5>
                            <p>Live prices used for fuel cost computations. The Gas deduction reads the applicable price from here — values are saved to the existing fuel price records and every change is audit-logged.</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="fcs-table">
                            <thead>
                                <tr>
                                    <th>Fuel Type</th>
                                    <th>Price per Liter</th>
                                    <th>Used For</th>
                                    <th>Last Updated</th>
                                    <?php if ($canEdit): ?><th style="text-align:right;">Action</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($fuelPrices)): ?>
                                    <tr><td colspan="<?= $canEdit ? 5 : 4 ?>" class="fcs-empty">No fuel prices found yet. Fuel prices appear here once records exist in the fuel price database.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($fuelPrices as $fuelRow): ?>
                                        <?php $inUse = !empty($applicableFuel) && (int) $applicableFuel['id'] === (int) $fuelRow['id']; ?>
                                        <tr>
                                            <td style="font-weight:700;color:#295738;">
                                                <i class="bi bi-fuel-pump me-2" style="color:#57694d;"></i><?= htmlspecialchars($fuelRow['fuel_type']) ?>
                                            </td>
                                            <td class="fcs-value">₱<?= number_format((float) $fuelRow['price_per_liter'], 2) ?></td>
                                            <td>
                                                <?php if ($inUse): ?>
                                                    <span class="fcs-status active">GAS DEDUCTION</span>
                                                <?php else: ?>
                                                    <span class="fcs-muted">Reference</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fcs-muted"><?= !empty($fuelRow['updated_at']) ? htmlspecialchars((string) $fuelRow['updated_at']) : '—' ?></td>
                                            <?php if ($canEdit): ?>
                                                <td style="text-align:right;">
                                                    <button type="button" class="rmg-btn rmg-btn-sm" data-edit-fuel="<?= (int) $fuelRow['id'] ?>">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!$canEdit): ?>
                    <div class="fcs-readonly">
                        <i class="bi bi-lock-fill"></i>
                        View only — only a Finance Manager or Administrator can change financial cost settings.
                    </div>
                    <div style="height:18px;"></div>
                <?php endif; ?>

                <div class="fcs-panel">
                    <div class="fcs-panel-head">
                        <div>
                            <h5><i class="bi bi-clock-history me-2"></i>Change History (Audit Trail)</h5>
                            <p>Every saved change records the editor, timestamp, previous → new values and the reason.</p>
                        </div>
                    </div>

                    <div class="fcs-audit-controls">
                        <div class="fcs-field fcs-audit-search">
                            <label for="fcsAuditSearch">Search</label>
                            <input type="text" id="fcsAuditSearch" placeholder="Setting, editor, reason or value...">
                        </div>
                        <div class="fcs-field">
                            <label for="fcsAuditFrom">From</label>
                            <input type="date" id="fcsAuditFrom">
                        </div>
                        <div class="fcs-field">
                            <label for="fcsAuditTo">To</label>
                            <input type="date" id="fcsAuditTo">
                        </div>
                        <div class="fcs-field">
                            <label for="fcsAuditClear">&nbsp;</label>
                            <button type="button" id="fcsAuditClear" class="rmg-btn"><i class="bi bi-x-circle"></i> Clear</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="fcs-table" id="fcsAuditTable">
                            <thead>
                                <tr>
                                    <th>Date &amp; Time</th>
                                    <th>Setting</th>
                                    <th>Previous → New</th>
                                    <th>Editor</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($auditRows)): ?>
                                    <tr id="fcsAuditEmpty"><td colspan="5" class="fcs-empty">No changes recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($auditRows as $entry): ?>
                                        <tr class="fcs-audit-row" data-search="<?= htmlspecialchars($entry['search'], ENT_QUOTES) ?>" data-date="<?= htmlspecialchars($entry['date'], ENT_QUOTES) ?>">
                                            <td class="fcs-muted" style="white-space:nowrap;"><?= htmlspecialchars($entry['created_at']) ?></td>
                                            <td style="white-space:nowrap;font-weight:600;"><?= htmlspecialchars($entry['setting']) ?></td>
                                            <td style="min-width:260px;">
                                                <?php if (empty($entry['changes'])): ?>
                                                    <span class="fcs-muted">—</span>
                                                <?php else: ?>
                                                    <?php $first = $entry['changes'][0]; ?>
                                                    <span class="fcs-change">
                                                        <?= htmlspecialchars($first['label']) ?>:
                                                        <span class="fcs-old"><?= htmlspecialchars($first['old']) ?></span>
                                                        &rarr;
                                                        <span class="fcs-new"><?= htmlspecialchars($first['new']) ?></span>
                                                    </span>
                                                    <?php if (count($entry['changes']) > 1): ?>
                                                        <details class="fcs-details">
                                                            <summary>View all <?= count($entry['changes']) ?> field changes</summary>
                                                            <div class="fcs-details-body">
                                                                <?php foreach ($entry['changes'] as $change): ?>
                                                                    <span class="fcs-change">
                                                                        <?= htmlspecialchars($change['label']) ?>:
                                                                        <span class="fcs-old"><?= htmlspecialchars($change['old']) ?></span>
                                                                        &rarr;
                                                                        <span class="fcs-new"><?= htmlspecialchars($change['new']) ?></span>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </details>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space:nowrap;">
                                                <?= htmlspecialchars($entry['username']) ?>
                                                <?php if ($entry['role'] !== ''): ?><span class="fcs-value-sub"><?= htmlspecialchars($entry['role']) ?></span><?php endif; ?>
                                            </td>
                                            <td style="max-width:260px;overflow-wrap:break-word;"><?= htmlspecialchars($entry['reason'] !== '' ? $entry['reason'] : '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="fcs-pager">
                        <span id="fcsAuditInfo"></span>
                        <button type="button" id="fcsAuditPrev"><i class="bi bi-chevron-left"></i> Prev</button>
                        <button type="button" id="fcsAuditNext">Next <i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="/public/login/custom-alert.css">
<script src="/public/login/custom-alert.js"></script>

<div class="modal fade" id="fcsEditModal" tabindex="-1" aria-labelledby="fcsEditTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-sliders"></i></span>
                    <div>
                        <div class="rmg-form-kicker">Cost settings</div>
                        <h5 class="modal-title rmg-form-title" id="fcsEditTitle">Edit setting</h5>
                        <div class="rmg-form-sub">A before-and-after summary is shown before anything is saved.</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <div class="rmg-note" id="fcsEditDesc"></div>
                <div class="rmg-error" id="fcsEditError" hidden></div>
                <div id="fcsEditFields"></div>
                <div class="rmg-detail-section">
                    <div class="rmg-section-head">
                        <span class="rmg-section-icon tone-docs"><i class="bi bi-chat-left-text"></i></span>
                        <div class="rmg-section-title">Reason for this change</div>
                    </div>
                    <div class="rmg-form-field">
                        <textarea id="fcsEditReason" maxlength="500" placeholder="e.g. Updated insurance premium for the 2026 policy renewal"></textarea>
                        <div class="rmg-field-hint">Recorded in the audit trail with your name, the timestamp and the before-and-after values.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="fcsEditReviewBtn">
                    <i class="bi bi-search"></i> Review Changes
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="fcsVerifyModal" tabindex="-1" aria-labelledby="fcsVerifyTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-shield-lock"></i></span>
                    <div>
                        <div class="rmg-form-kicker">Secure save</div>
                        <h5 class="modal-title rmg-form-title" id="fcsVerifyTitle">Review &amp; Confirm Changes</h5>
                        <div class="rmg-form-sub">Password + registered email verification required before saving.</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close review and confirm"></button>
            </div>

            <div class="modal-body rmg-form-body">
                <div id="fcsChangeSummary"></div>

                <div class="rmg-detail-section">
                    <div class="rmg-section-head">
                        <span class="rmg-section-icon"><i class="bi bi-1-circle"></i></span>
                        <div class="rmg-section-title">Confirm your account password</div>
                        <span id="fcsPasswordStatus" class="fcs-factor-status pending" style="margin-left:auto;">Pending</span>
                    </div>
                    <div class="fcs-inline-row">
                        <input type="password" id="fcsPassword" autocomplete="current-password" placeholder="Current account password">
                        <button type="button" id="fcsVerifyPasswordBtn" class="rmg-btn">Verify password</button>
                    </div>
                    <div class="rmg-field-hint">Your password is compared against the stored hash on the server and is never stored or logged.</div>
                </div>

                <div class="rmg-detail-section">
                    <div class="rmg-section-head">
                        <span class="rmg-section-icon tone-account"><i class="bi bi-2-circle"></i></span>
                        <div class="rmg-section-title">Verify your registered email</div>
                        <span id="fcsOtpStatus" class="fcs-factor-status pending" style="margin-left:auto;">Pending</span>
                    </div>
                    <div class="fcs-inline-row">
                        <input type="text" id="fcsOtp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="6-digit email code">
                        <button type="button" id="fcsSendOtpBtn" class="rmg-btn">Send code</button>
                        <button type="button" id="fcsVerifyOtpBtn" class="rmg-btn">Verify code</button>
                    </div>
                    <div class="rmg-field-hint" id="fcsOtpHint">A 6-digit code is sent to the email address registered on your account. It expires after 5 minutes.</div>
                </div>
            </div>

            <div class="modal-footer rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="fcsConfirmSaveBtn" disabled>
                    <i class="bi bi-check-lg"></i> Confirm &amp; Save Changes
                </button>
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

<script>
(function () {
    "use strict";

    var CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
    var CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
    var ENDPOINT = "/finance/api/financial_cost_settings_api.php";

    var COSTS = <?= json_encode($jsCosts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var FUEL = <?= json_encode($jsFuel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var METHOD_LABELS = <?= json_encode($methodLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var METHOD_ORDER = ["fixed_monthly", "fixed_daily", "per_trip", "percent_gross"];
    var AMOUNT_LABELS = {
        fixed_monthly: "Amount per month (₱)",
        fixed_daily: "Amount per day (₱)",
        per_trip: "Amount per trip (₱)"
    };
    var MAX_AMOUNT = 10000000;
    var MAX_FUEL_PRICE = 1000;

    function esc(value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (c) {
            return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c];
        });
    }

    function alertError(title, message) {
        showAlert("error", title, message || "");
    }

    function alertSuccess(title, message, onConfirm) {
        showAlert("success", title, message || "", onConfirm);
    }

    function post(action, data) {
        var fd = new FormData();
        fd.append("action", action);
        fd.append("_csrf_token", CSRF_TOKEN);
        Object.keys(data || {}).forEach(function (key) {
            fd.append(key, data[key]);
        });

        return fetch(ENDPOINT, {
            method: "POST",
            headers: { "X-CSRF-Token": CSRF_TOKEN },
            body: fd,
        }).then(function (r) {
            return r.json().then(function (body) {
                return { ok: r.ok, data: body };
            });
        });
    }

    function methodLabel(method) {
        return METHOD_LABELS[method] || method;
    }

    /* =========================================================
     * Audit history — search, date filter and pagination
     * ========================================================= */

    var AUDIT_PAGE_SIZE = 10;
    var auditPage = 1;
    var auditRows = Array.prototype.slice.call(document.querySelectorAll(".fcs-audit-row"));
    var auditSearch = document.getElementById("fcsAuditSearch");
    var auditFrom = document.getElementById("fcsAuditFrom");
    var auditTo = document.getElementById("fcsAuditTo");
    var auditClear = document.getElementById("fcsAuditClear");
    var auditPrev = document.getElementById("fcsAuditPrev");
    var auditNext = document.getElementById("fcsAuditNext");
    var auditInfo = document.getElementById("fcsAuditInfo");

    function auditFilteredRows() {
        var q = (auditSearch && auditSearch.value ? auditSearch.value : "").trim().toLowerCase();
        var from = auditFrom ? auditFrom.value : "";
        var to = auditTo ? auditTo.value : "";

        return auditRows.filter(function (row) {
            var search = row.getAttribute("data-search") || "";
            if (q && search.indexOf(q) === -1) {
                return false;
            }
            var date = row.getAttribute("data-date") || "";
            if (from && date < from) {
                return false;
            }
            if (to && date > to) {
                return false;
            }
            return true;
        });
    }

    function auditRender() {
        var filtered = auditFilteredRows();
        var totalPages = Math.max(1, Math.ceil(filtered.length / AUDIT_PAGE_SIZE));
        if (auditPage > totalPages) {
            auditPage = totalPages;
        }

        auditRows.forEach(function (row) { row.style.display = "none"; });
        filtered.slice((auditPage - 1) * AUDIT_PAGE_SIZE, auditPage * AUDIT_PAGE_SIZE).forEach(function (row) {
            row.style.display = "";
        });

        if (auditInfo) {
            auditInfo.textContent = filtered.length === 0
                ? "No matching changes"
                : "Showing " + ((auditPage - 1) * AUDIT_PAGE_SIZE + 1) + "–" + Math.min(auditPage * AUDIT_PAGE_SIZE, filtered.length) + " of " + filtered.length + " changes";
        }
        if (auditPrev) auditPrev.disabled = auditPage <= 1;
        if (auditNext) auditNext.disabled = auditPage >= totalPages;
    }

    if (auditSearch) {
        auditSearch.addEventListener("input", function () { auditPage = 1; auditRender(); });
    }
    if (auditFrom) {
        auditFrom.addEventListener("change", function () { auditPage = 1; auditRender(); });
    }
    if (auditTo) {
        auditTo.addEventListener("change", function () { auditPage = 1; auditRender(); });
    }
    if (auditClear) {
        auditClear.addEventListener("click", function () {
            if (auditSearch) auditSearch.value = "";
            if (auditFrom) auditFrom.value = "";
            if (auditTo) auditTo.value = "";
            auditPage = 1;
            auditRender();
        });
    }
    if (auditPrev) {
        auditPrev.addEventListener("click", function () { auditPage = Math.max(1, auditPage - 1); auditRender(); });
    }
    if (auditNext) {
        auditNext.addEventListener("click", function () { auditPage += 1; auditRender(); });
    }
    auditRender();

    if (!CAN_EDIT) {
        return;
    }

    /* =========================================================
     * Edit modal — one compact form per setting / fuel price
     * ========================================================= */

    var editModalEl = document.getElementById("fcsEditModal");
    var editTitle = document.getElementById("fcsEditTitle");
    var editDesc = document.getElementById("fcsEditDesc");
    var editFields = document.getElementById("fcsEditFields");
    var editReason = document.getElementById("fcsEditReason");
    var editReviewBtn = document.getElementById("fcsEditReviewBtn");
    var editError = document.getElementById("fcsEditError");

    function showEditError(message) {
        if (!editError) return;
        editError.textContent = message;
        editError.hidden = false;
    }

    function hideEditError() {
        if (!editError) return;
        editError.hidden = true;
        editError.textContent = "";
    }

    var verifyModalEl = document.getElementById("fcsVerifyModal");
    var confirmBtn = document.getElementById("fcsConfirmSaveBtn");

    var currentItem = null;
    var draft = null;
    var saving = false;
    var saveSucceeded = false;

    function costFieldHtml(key, values) {
        var config = COSTS[key];
        var isGas = !!config.is_gas;
        var method = values && values.calc_method ? values.calc_method : config.method;
        var html = '<div class="rmg-detail-section">'
            + '<div class="rmg-section-head"><span class="rmg-section-icon"><i class="bi bi-sliders"></i></span>'
            + '<div class="rmg-section-title">Cost configuration</div></div>';

        if (isGas) {
            html += '<div class="rmg-note"><i class="bi bi-fuel-pump-fill me-1"></i>The fuel price is managed in <strong>Fuel Prices</strong>. '
                + 'This cost uses actual recorded fuel consumption × the applicable price per liter.</div>';
        }

        html += '<div class="rmg-form-grid">';

        if (!isGas) {
            html += '<div class="rmg-form-field full"><label for="fcsEditMethod">Calculation method</label><select id="fcsEditMethod">';
            METHOD_ORDER.forEach(function (m) {
                html += '<option value="' + esc(m) + '"' + (m === method ? " selected" : "") + ">" + esc(methodLabel(m)) + "</option>";
            });
            html += "</select></div>";
            html += '<div class="rmg-form-field" id="fcsAmountField">'
                + '<label for="fcsEditAmount" id="fcsAmountLabel">' + esc(AMOUNT_LABELS[method] || "Amount (₱)") + "</label>"
                + '<input type="number" step="0.01" min="0" id="fcsEditAmount" value="' + esc(values ? values.amount : config.amount) + '">'
                + "</div>";
            html += '<div class="rmg-form-field" id="fcsRateField">'
                + '<label for="fcsEditRate">Rate (% of gross income)</label>'
                + '<input type="number" step="0.01" min="0" max="100" id="fcsEditRate" value="' + esc(values ? values.rate : config.rate) + '">'
                + "</div>";
        }

        html += '<div class="rmg-form-field"><label for="fcsEditDate">Effective date</label>'
            + '<input type="date" id="fcsEditDate" value="' + esc(values ? values.effective_date : config.effective_date) + '"></div>'
            + '<div class="rmg-form-field"><label for="fcsEditStatus">Status</label><select id="fcsEditStatus">'
            + '<option value="Active"' + ((values ? values.status : config.status) === "Active" ? " selected" : "") + ">Active</option>"
            + '<option value="Inactive"' + ((values ? values.status : config.status) === "Inactive" ? " selected" : "") + ">Inactive</option>"
            + "</select></div>";

        html += '<div class="rmg-form-field full"><label for="fcsEditNotes">Notes (optional)</label>'
            + '<input type="text" id="fcsEditNotes" maxlength="255" value="' + esc(values ? values.notes : config.notes) + '" placeholder="Shown to finance staff in this page only"></div>';

        html += "</div></div>";

        return html;
    }

    function fuelFieldHtml(id, values) {
        var config = FUEL[id];
        return '<div class="rmg-detail-section">'
            + '<div class="rmg-section-head"><span class="rmg-section-icon"><i class="bi bi-fuel-pump-fill"></i></span>'
            + '<div class="rmg-section-title">Fuel price</div></div>'
            + '<div class="rmg-form-grid"><div class="rmg-form-field full">'
            + '<label for="fcsEditPrice">Price per liter (₱)</label>'
            + '<input type="number" step="0.01" min="0.01" max="' + MAX_FUEL_PRICE + '" id="fcsEditPrice" value="' + esc(values ? values.price : config.price) + '">'
            + "</div></div>"
            + '<div class="rmg-field-hint">Last updated: ' + esc(config.updated_at || "—") + "</div>"
            + "</div>";
    }

    function syncCostFields() {
        var methodSelect = document.getElementById("fcsEditMethod");
        var amountField = document.getElementById("fcsAmountField");
        var rateField = document.getElementById("fcsRateField");
        if (!methodSelect || !amountField || !rateField) {
            return;
        }
        var method = methodSelect.value;
        var isPercent = method === "percent_gross";
        amountField.style.display = isPercent ? "none" : "";
        rateField.style.display = isPercent ? "" : "none";
        var amountLabel = document.getElementById("fcsAmountLabel");
        if (amountLabel) {
            amountLabel.textContent = AMOUNT_LABELS[method] || "Amount (₱)";
        }
    }

    function openEditor(type, key, values) {
        currentItem = { type: type, key: key };

        if (type === "fuel") {
            editTitle.textContent = "Edit " + FUEL[key].label + " price";
            editDesc.textContent = "Fuel prices are stored once and shared across the system. The Gas deduction always uses the latest applicable price, and older records are not rewritten.";
            editFields.innerHTML = fuelFieldHtml(key, values);
        } else {
            editTitle.textContent = "Edit " + COSTS[key].label;
            editDesc.textContent = COSTS[key].description;
            editFields.innerHTML = costFieldHtml(key, values);
            var methodSelect = document.getElementById("fcsEditMethod");
            if (methodSelect) {
                methodSelect.addEventListener("change", syncCostFields);
                syncCostFields();
            }
        }

        if (editReason) {
            editReason.value = values && values.reason ? values.reason : "";
        }

        hideEditError();
        editFields.querySelectorAll("input, select, textarea").forEach(function (el) {
            el.addEventListener("input", hideEditError);
            el.addEventListener("change", hideEditError);
        });

        bootstrap.Modal.getOrCreateInstance(editModalEl).show();
        window.setTimeout(function () {
            var first = editFields.querySelector("input, select, textarea");
            if (first) first.focus();
        }, 300);
    }

    document.querySelectorAll("[data-edit-cost]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var key = this.getAttribute("data-edit-cost");
            openEditor("cost", key, null);
        });
    });

    document.querySelectorAll("[data-edit-fuel]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var id = this.getAttribute("data-edit-fuel");
            openEditor("fuel", id, null);
        });
    });

    function collectDraft() {
        var reason = (editReason && editReason.value ? editReason.value : "").trim();
        if (reason.length < 5) {
            return { error: "Please provide a reason for the change (at least 5 characters).", focus: "reason" };
        }

        if (currentItem.type === "fuel") {
            var priceInput = document.getElementById("fcsEditPrice");
            var price = parseFloat(priceInput.value);
            if (isNaN(price) || price <= 0 || price > MAX_FUEL_PRICE) {
                return { error: "Fuel price must be between 0.01 and " + MAX_FUEL_PRICE.toLocaleString() + "." };
            }
            var fuelPrices = {};
            fuelPrices[currentItem.key] = price.toFixed(2);
            return {
                reason: reason,
                values: { price: priceInput.value, reason: reason },
                body: { settings: JSON.stringify({}), fuel_prices: JSON.stringify(fuelPrices) }
            };
        }

        var config = COSTS[currentItem.key];
        var isGas = !!config.is_gas;
        var row = {};
        var values = {};

        if (!isGas) {
            var method = document.getElementById("fcsEditMethod").value;
            row.calc_method = method;
            values.calc_method = method;

            if (method === "percent_gross") {
                var rateInput = document.getElementById("fcsEditRate");
                var rate = parseFloat(rateInput.value);
                if (isNaN(rate) || rate < 0 || rate > 100) {
                    return { error: "Rate must be between 0 and 100% of gross income." };
                }
                row.rate = rate.toFixed(2);
                values.rate = rateInput.value;

                var amountInput = document.getElementById("fcsEditAmount");
                if (amountInput) values.amount = amountInput.value;
            } else {
                var amountInput2 = document.getElementById("fcsEditAmount");
                var amount = parseFloat(amountInput2.value);
                if (isNaN(amount) || amount < 0 || amount > MAX_AMOUNT) {
                    return { error: "Amount must be between 0 and " + MAX_AMOUNT.toLocaleString() + "." };
                }
                row.amount = amount.toFixed(2);
                values.amount = amountInput2.value;

                var rateInput2 = document.getElementById("fcsEditRate");
                if (rateInput2) values.rate = rateInput2.value;
            }
        }

        var dateInput = document.getElementById("fcsEditDate");
        var effectiveDate = dateInput ? dateInput.value : "";
        if (!/^\d{4}-\d{2}-\d{2}$/.test(effectiveDate)) {
            return { error: "Effective date is required (YYYY-MM-DD)." };
        }
        row.effective_date = effectiveDate;
        values.effective_date = effectiveDate;

        var statusInput = document.getElementById("fcsEditStatus");
        row.status = statusInput ? statusInput.value : "Active";
        values.status = row.status;

        var notesInput = document.getElementById("fcsEditNotes");
        row.notes = notesInput ? notesInput.value : "";
        values.notes = row.notes;

        var settings = {};
        settings[currentItem.key] = row;
        values.reason = reason;

        return {
            reason: reason,
            values: values,
            body: { settings: JSON.stringify(settings), fuel_prices: JSON.stringify({}) }
        };
    }

    var FIELD_LABELS = {
        amount: "Amount",
        rate: "Rate",
        calc_method: "Calculation method",
        unit: "Unit",
        effective_date: "Effective date",
        status: "Status",
        notes: "Notes",
        price_per_liter: "Price per liter (₱)"
    };

    function fieldValue(field, value) {
        if (field === "amount" || field === "rate" || field === "price_per_liter") {
            var n = Number(value);
            return isNaN(n) ? String(value == null ? "" : value) : n.toFixed(2);
        }
        if (field === "calc_method") {
            return methodLabel(String(value));
        }
        return String(value == null || value === "" ? "—" : value);
    }

    function renderChangeSummary(changes, reason) {
        var html = '<p class="fcs-modal-note">Review each change below, then verify your identity. Your account password and a code sent to your registered email are required before anything is saved.</p>';
        html += '<div class="fcs-change-list">';
        changes.forEach(function (change) {
            html += '<div class="fcs-change-group"><h6>' + esc(change.label) + "</h6>";
            Object.keys(change.fields).forEach(function (field) {
                var pair = change.fields[field];
                html += '<div class="fcs-change-line"><span class="fcs-change-field">'
                    + esc(FIELD_LABELS[field] || field)
                    + '</span><span class="fcs-old">'
                    + esc(fieldValue(field, pair.old))
                    + "</span><span>&rarr;</span><span class=\"fcs-new\">"
                    + esc(fieldValue(field, pair.new))
                    + "</span></div>";
            });
            html += "</div>";
        });
        html += "</div>";
        html += '<p class="fcs-modal-note" style="margin-top:12px;"><strong>Reason:</strong> ' + esc(reason) + "</p>";
        document.getElementById("fcsChangeSummary").innerHTML = html;
    }

    function setFactorStatus(id, ok) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = ok ? "Verified" : "Pending";
        el.className = "fcs-factor-status " + (ok ? "ok" : "pending");
    }

    function applyVerificationState(state) {
        if (!state) return;
        setFactorStatus("fcsPasswordStatus", !!state.password);
        setFactorStatus("fcsOtpStatus", !!state.otp);
        if (state.email_masked) {
            var hint = document.getElementById("fcsOtpHint");
            if (hint) {
                hint.textContent = "Verification codes are sent to " + state.email_masked + ". The code expires after 5 minutes.";
            }
        }
        if (confirmBtn) {
            confirmBtn.disabled = !state.ready || saving;
        }
    }

    function refreshVerificationState() {
        return fetch(ENDPOINT + "?action=verify_status", { credentials: "same-origin" })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.success) {
                    applyVerificationState(d.state);
                }
                return d;
            })
            .catch(function () { return null; });
    }

    if (editReviewBtn) {
        editReviewBtn.addEventListener("click", function () {
            var result = collectDraft();
            if (result.error) {
                showEditError(result.error);
                if (result.focus === "reason" && editReason) {
                    editReason.focus();
                }
                return;
            }

            var original = editReviewBtn.innerHTML;
            editReviewBtn.disabled = true;
            editReviewBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Reviewing...';

            post("preview", result.body)
                .then(function (res) {
                    if (!res.data || !res.data.success) {
                        throw new Error((res.data && res.data.message) || "Unable to review the changes.");
                    }
                    if (!res.data.changes || !res.data.changes.length) {
                        alertSuccess("No changes", "Nothing was changed, so there is nothing to save.", function () {});
                        return;
                    }

                    draft = {
                        type: currentItem.type,
                        key: currentItem.key,
                        body: result.body,
                        reason: result.reason,
                        values: result.values
                    };

                    renderChangeSummary(res.data.changes, result.reason);
                    var passwordInput = document.getElementById("fcsPassword");
                    var otpInput = document.getElementById("fcsOtp");
                    if (passwordInput) passwordInput.value = "";
                    if (otpInput) otpInput.value = "";
                    setFactorStatus("fcsPasswordStatus", false);
                    setFactorStatus("fcsOtpStatus", false);

                    bootstrap.Modal.getOrCreateInstance(editModalEl).hide();
                    bootstrap.Modal.getOrCreateInstance(verifyModalEl).show();
                    refreshVerificationState();
                })
                .catch(function (e) {
                    alertError("Unable to review", e.message || "Please try again.");
                })
                .finally(function () {
                    editReviewBtn.disabled = false;
                    editReviewBtn.innerHTML = original;
                });
        });
    }

    /* If the review modal is dismissed without saving, restore the edit form
       with the user's changes so nothing is lost. */
    if (verifyModalEl) {
        verifyModalEl.addEventListener("hidden.bs.modal", function () {
            if (saveSucceeded) {
                draft = null;
                saveSucceeded = false;
                return;
            }
            if (draft) {
                var restore = draft;
                window.setTimeout(function () {
                    openEditor(restore.type, restore.key, restore.values);
                }, 200);
            }
        });
    }

    var verifyPasswordBtn = document.getElementById("fcsVerifyPasswordBtn");
    if (verifyPasswordBtn) {
        verifyPasswordBtn.addEventListener("click", function () {
            var value = document.getElementById("fcsPassword").value;
            if (!value) {
                alertError("Password required", "Enter your account password to verify your identity.");
                return;
            }
            var btn = this;
            btn.disabled = true;
            post("verify_password", { password: value })
                .then(function (res) {
                    if (res.data && res.data.state) {
                        applyVerificationState(res.data.state);
                    }
                    if (res.data && res.data.success) {
                        document.getElementById("fcsPassword").value = "";
                    } else {
                        alertError("Verification failed", (res.data && res.data.message) || "Incorrect account password.");
                    }
                })
                .catch(function () {
                    alertError("Verification failed", "Could not verify your password right now. Please try again.");
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    var sendOtpBtn = document.getElementById("fcsSendOtpBtn");
    if (sendOtpBtn) {
        sendOtpBtn.addEventListener("click", function () {
            var btn = this;
            btn.disabled = true;
            post("send_otp", {})
                .then(function (res) {
                    if (res.data && res.data.success) {
                        if (res.data.state) {
                            applyVerificationState(res.data.state);
                        }
                        var hint = document.getElementById("fcsOtpHint");
                        if (hint) {
                            hint.textContent = "A 6-digit code was sent to " + (res.data.email_masked || "your registered email") + ". It expires after 5 minutes.";
                        }
                    } else {
                        alertError("Could not send code", (res.data && res.data.message) || "Please try again.");
                    }
                })
                .catch(function () {
                    alertError("Could not send code", "Could not reach the server. Please try again.");
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    var verifyOtpBtn = document.getElementById("fcsVerifyOtpBtn");
    if (verifyOtpBtn) {
        verifyOtpBtn.addEventListener("click", function () {
            var value = document.getElementById("fcsOtp").value.trim();
            if (!value) {
                alertError("Code required", "Enter the 6-digit code sent to your registered email.");
                return;
            }
            var btn = this;
            btn.disabled = true;
            post("verify_otp", { otp: value })
                .then(function (res) {
                    if (res.data && res.data.state) {
                        applyVerificationState(res.data.state);
                    }
                    if (res.data && res.data.success) {
                        document.getElementById("fcsOtp").value = "";
                    } else {
                        alertError("Verification failed", (res.data && res.data.message) || "Incorrect verification code.");
                    }
                })
                .catch(function () {
                    alertError("Verification failed", "Could not verify the code right now. Please try again.");
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener("click", function () {
            if (saving) {
                return;
            }
            if (!draft) {
                alertError("Nothing to save", "Please edit a setting first.");
                return;
            }

            saving = true;
            var original = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

            post("save", { settings: draft.body.settings, fuel_prices: draft.body.fuel_prices, reason: draft.reason })
                .then(function (res) {
                    if (!res.data || !res.data.success) {
                        throw new Error((res.data && res.data.message) || "Unable to save the settings.");
                    }
                    saveSucceeded = true;
                    bootstrap.Modal.getOrCreateInstance(verifyModalEl).hide();
                    alertSuccess("Changes saved", res.data.message, function () {
                        window.location.reload();
                    });
                })
                .catch(function (e) {
                    // Keep the review modal and the unsaved edits so the user
                    // can retry; verification may have expired.
                    alertError("Save failed", e.message || "Please try again.");
                    refreshVerificationState();
                })
                .finally(function () {
                    saving = false;
                    confirmBtn.innerHTML = original;
                    refreshVerificationState();
                });
        });
    }
})();
</script>

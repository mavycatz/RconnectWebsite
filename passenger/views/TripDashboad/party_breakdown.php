<?php if (!empty($partyBreakdown['lines'])): ?>
<div style="margin:8px 0 10px;">
    <div style="font-size:10px;font-weight:700;letter-spacing:.5px;color:#7b8a74;text-transform:uppercase;">
        <?= htmlspecialchars(passenger_t('trips.party_breakdown', 'Travelling Party'), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php foreach ($partyBreakdown['lines'] as $line): ?>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;padding:6px 0;border-bottom:1px solid #e6ecdf;font-size:11.5px;">
        <div style="flex:1;min-width:0;overflow-wrap:anywhere;">
            <div style="font-weight:700;color:#24513c;">
                <?= htmlspecialchars((string) ($line['name'] ?? 'Traveller'), ENT_QUOTES, 'UTF-8') ?>
                &middot; <?= htmlspecialchars((string) ($line['type_label'] ?? 'Regular'), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="color:#7b8a74;">
                <?= htmlspecialchars((string) ($line['boarding_point'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                &rarr; <?= htmlspecialchars((string) ($line['destination'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div style="color:#7b8a74;">
                <?= htmlspecialchars(passenger_t('ot.base_fare', 'Base fare'), ENT_QUOTES, 'UTF-8') ?> ₱<?= number_format((float) ($line['base_fare'] ?? 0), 2) ?>
                &middot; <?= htmlspecialchars(passenger_t('trips.discount_amount', 'Discount'), ENT_QUOTES, 'UTF-8') ?> ₱<?= number_format((float) ($line['discount_amount'] ?? 0), 2) ?>
            </div>
        </div>
        <strong style="flex-shrink:0;color:#24513c;">₱<?= number_format((float) ($line['final_fare'] ?? 0), 2) ?></strong>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

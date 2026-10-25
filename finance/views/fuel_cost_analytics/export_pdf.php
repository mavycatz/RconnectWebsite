<?php
if (!isset($rows)) { $rows = []; }
if (!isset($rangeLabel)) { $rangeLabel = 'All dates'; }

$fmt = fn ($amt) => '₱' . number_format((float) $amt, 2);
$ltf = fn ($l) => number_format((float) $l, 2) . ' L';
$dtf = fn ($dt) => $dt ? date('M d, Y', strtotime($dt)) : '—';
$tmf = fn ($dt) => $dt ? date('h:i A', strtotime($dt)) : '—';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 11px;
    color: #1f2937;
}
h1 {
    font-size: 18px;
    color: #2e4836;
    margin: 0 0 2px;
}
.sub {
    font-size: 11px;
    color: #6b7280;
    margin-bottom: 14px;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th {
    background: #e3eabb;
    color: #2e4836;
    padding: 6px 8px;
    text-align: left;
    font-size: 10px;
    text-transform: uppercase;
}
td {
    padding: 6px 8px;
    border-bottom: 1px solid #e5e7eb;
}
.num {
    text-align: right;
}
.tot {
    margin-top: 12px;
    font-size: 12px;
    font-weight: bold;
    color: #2e4836;
}
</style>
</head>
<body>
    <h1>Fuel &amp; Trip Cost Analytics</h1>
    <div class="sub">Real-time fuel expense monitoring · <?= htmlspecialchars($rangeLabel) ?> · Generated <?= date('M d, Y h:i A') ?></div>
    <table>
        <thead>
            <tr>
                <th>Receipt #</th><th>Date</th><th>Time</th><th>Bus</th><th>Driver</th>
                <th class="num">Liters</th><th class="num">Price / Liter</th><th class="num">Total Cost</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['receipt_number'] ?? '') ?></td>
                <td><?= $dtf($r['created_at'] ?? null) ?></td>
                <td><?= $tmf($r['created_at'] ?? null) ?></td>
                <td><?= htmlspecialchars($r['bus'] ?? $r['bus_id'] ?? '—') ?></td>
                <td>
                    <?= htmlspecialchars($r['driver_name'] ?? $r['driver_id'] ?? '—') ?>
                    <?php if (!empty($r['driver_code']) && ($r['driver_code'] ?? '') !== ($r['driver_name'] ?? '')): ?>
                        (<?= htmlspecialchars($r['driver_code']) ?>)
                    <?php endif; ?>
                </td>
                <td class="num"><?= $ltf($r['liters'] ?? 0) ?></td>
                <td class="num"><?= $fmt($r['price_per_liter'] ?? 0) ?></td>
                <td class="num"><?= $fmt($r['cost'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (!empty($rows)): ?>
        <div class="tot"><?= count($rows) ?> record(s) · Total Cost <?= $fmt(array_sum(array_map(fn (array $r): float => (float) ($r['cost'] ?? 0), $rows))) ?></div>
    <?php else: ?>
        <p style="color:#6b7280;margin-top:16px;">No fuel records found for the selected filters.</p>
    <?php endif; ?>
</body>
</html>

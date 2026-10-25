<?php
if (!isset($rows)) { $rows = []; }
if (!isset($rangeLabel)) { $rangeLabel = 'All dates'; }

$fmt = fn ($amt) => '₱' . number_format((float) $amt, 2);
$ltf = fn ($l) => number_format((float) $l, 2) . ' L';
$dtf = fn ($dt) => $dt ? date('M d, Y h:i A', strtotime($dt)) : '—';
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="utf-8">

<style>
table {
    border-collapse: collapse;
}
td,
th {
    border: 1px solid #ccc;
    padding: 4px 8px;
    font-size: 11px;
}
th {
    background: #e3eabb;
    font-weight: bold;
}
</style>
</head>
<body>
    <h2>Fuel &amp; Trip Cost Analytics</h2>
    <p>Real-time fuel expense monitoring · <?= htmlspecialchars($rangeLabel) ?> · Generated <?= date('M d, Y h:i A') ?></p>
    <table>
        <thead>
            <tr>
                <th>Receipt #</th><th>Date &amp; Time</th><th>Bus</th><th>Driver</th>
                <th>Liters</th><th>Price / Liter</th><th>Total Cost</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['receipt_number'] ?? '') ?></td>
                <td><?= $dtf($r['created_at'] ?? null) ?></td>
                <td><?= htmlspecialchars($r['bus'] ?? $r['bus_id'] ?? '—') ?></td>
                <td>
                    <?= htmlspecialchars($r['driver_name'] ?? $r['driver_id'] ?? '—') ?>
                    <?php if (!empty($r['driver_code']) && ($r['driver_code'] ?? '') !== ($r['driver_name'] ?? '')): ?>
                        (<?= htmlspecialchars($r['driver_code']) ?>)
                    <?php endif; ?>
                </td>
                <td><?= $ltf($r['liters'] ?? 0) ?></td>
                <td><?= $fmt($r['price_per_liter'] ?? 0) ?></td>
                <td><?= $fmt($r['cost'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
            <tr><td colspan="7">No fuel records found for the selected filters.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>

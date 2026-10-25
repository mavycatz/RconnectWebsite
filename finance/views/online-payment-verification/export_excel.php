<?php
if (!isset($rows)) { $rows = []; }
if (!isset($tabLabel)) { $tabLabel = 'Pending'; }

$fmt = fn ($amt) => '₱' . number_format((float) $amt, 2);
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
    <h2>Online Payment Verification — <?= htmlspecialchars($tabLabel) ?></h2>
    <table>
        <thead>
            <tr>
                <th>Passenger</th><th>Bus</th><th>Driver</th><th>Route</th>
                <th>Fare</th><th>GCash</th><th>Method</th><th>Status</th><th>Time</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php
            $status = strtolower($r['payment_status'] ?? 'pending');
            $label = $status === 'paid' ? 'Approved' : ($status === 'rejected' ? 'Rejected' : 'Pending');
            $time = $r['resubmitted_at'] ?? ($r['verified_at'] ?? ($r['boarding_time'] ?? null));
            $bp = htmlspecialchars($r['boarding_point'] ?? '');
            $dest = htmlspecialchars($r['destination'] ?? '');
            $route = $bp ? ($dest ? $bp . ' → ' . $dest : $bp) : ($dest ?: '—');
            ?>
            <tr>
                <td><?= htmlspecialchars($r['name'] ?? 'Unknown') ?></td>
                <td><?= htmlspecialchars($r['bus_id'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['driver_id'] ?? '-') ?></td>
                <td><?= $route ?></td>
                <td><?= $fmt($r['fare'] ?? 0) ?></td>
                <td><?= $fmt($r['gcash_amount'] ?? 0) ?></td>
                <td><?= htmlspecialchars(ucfirst($r['payment_method'] ?? 'GCash')) ?></td>
                <td><?= $label ?></td>
                <td><?= $dtf($time) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (isset($totalAmount)): ?>
        <p style="font-size:12px;font-weight:bold;color:#2e4836;">Total Amount: <?= $fmt($totalAmount) ?></p>
    <?php endif; ?>
</body>
</html>

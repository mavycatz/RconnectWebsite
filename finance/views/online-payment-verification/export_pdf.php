<?php
if (!isset($rows)) { $rows = []; }
if (!isset($tabLabel)) { $tabLabel = 'Pending'; }

$fmt = fn ($amt) => '₱' . number_format((float) $amt, 2);
$dtf = fn ($dt) => $dt ? date('M d, Y h:i A', strtotime($dt)) : '—';
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
.ctr {
    text-align: center;
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
    <h1>Online Payment Verification — <?= htmlspecialchars($tabLabel) ?></h1>
    <div class="sub">Generated <?= date('M d, Y h:i A') ?></div>
    <table>
        <thead>
            <tr>
                <th>Passenger</th><th>Bus</th><th>Driver</th><th>Route</th>
                <th class="num">Fare</th><th class="num">GCash</th><th class="ctr">Method</th>
                <th class="ctr">Status</th><th>Time</th>
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
                <td class="num"><?= $fmt($r['fare'] ?? 0) ?></td>
                <td class="num"><?= $fmt($r['gcash_amount'] ?? 0) ?></td>
                <td class="ctr"><?= htmlspecialchars(ucfirst($r['payment_method'] ?? 'GCash')) ?></td>
                <td class="ctr"><?= $label ?></td>
                <td><?= $dtf($time) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (isset($totalAmount)): ?>
        <p class="tot">Total Amount: <?= $fmt($totalAmount) ?></p>
    <?php endif; ?>
    <?php if (empty($rows)): ?>
        <p style="color:#6b7280;margin-top:16px;">No records found for this tab.</p>
    <?php endif; ?>
</body>
</html>

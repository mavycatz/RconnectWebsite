<?php
if (!isset($data)) { $data = []; }
if (!isset($rangeLabel)) { $rangeLabel = 'All dates'; }

$fmt = fn ($amt) => '₱' . number_format((float) $amt, 2);
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
</style>
</head>
<body>
    <h1>Remittance History</h1>
    <div class="sub">Finance Remittance Archive and Audit Logs · <?= htmlspecialchars($rangeLabel) ?> · Generated <?= date('M d, Y h:i A') ?></div>
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Date</th><th>Time</th><th>Driver</th><th>Bus</th>
                <th class="num">Expected</th><th class="num">Actual</th><th class="num">Discrepancy</th>
                <th>Status</th><th>Verified By</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data as $row): ?>
            <tr>
                <td>#<?= (int) ($row['id'] ?? 0) ?></td>
                <td><?= $dtf($row['created_at'] ?? null) ?></td>
                <td><?= $tmf($row['created_at'] ?? null) ?></td>
                <td><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($row['bus_no'] ?? 'N/A') ?></td>
                <td class="num"><?= $fmt($row['expected_cash'] ?? 0) ?></td>
                <td class="num"><?= $fmt($row['actual_cash'] ?? 0) ?></td>
                <td class="num"><?= $fmt($row['discrepancy_amount'] ?? 0) ?></td>
                <td><?= htmlspecialchars($row['status'] ?? 'Pending') ?></td>
                <td><?= htmlspecialchars($row['confirmed_by'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($data)): ?>
            <tr><td colspan="10">No remittance records found for the selected period.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>

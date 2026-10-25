<?php
if (!isset($data)) { $data = []; }
if (!isset($rangeLabel)) { $rangeLabel = 'All dates'; }

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
    <h2>Remittance History</h2>
    <p>Finance Remittance Archive and Audit Logs · <?= htmlspecialchars($rangeLabel) ?> · Generated <?= date('M d, Y h:i A') ?></p>
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Date &amp; Time</th><th>Driver</th><th>Bus</th>
                <th>Expected</th><th>Actual</th><th>Discrepancy</th><th>Status</th><th>Verified By</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data as $row): ?>
            <tr>
                <td>#<?= (int) ($row['id'] ?? 0) ?></td>
                <td><?= $dtf($row['created_at'] ?? null) ?></td>
                <td><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($row['bus_no'] ?? 'N/A') ?></td>
                <td><?= $fmt($row['expected_cash'] ?? 0) ?></td>
                <td><?= $fmt($row['actual_cash'] ?? 0) ?></td>
                <td><?= $fmt($row['discrepancy_amount'] ?? 0) ?></td>
                <td><?= htmlspecialchars($row['status'] ?? 'Pending') ?></td>
                <td><?= htmlspecialchars($row['confirmed_by'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($data)): ?>
            <tr><td colspan="9">No remittance records found for the selected period.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>

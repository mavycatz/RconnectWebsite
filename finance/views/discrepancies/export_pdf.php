<?php
/**
 * Discrepancy investigation report markup.
 *
 * Rendered by DiscrepancyController::exportPdf() through Dompdf and by
 * print() inline. Expects $context from the controller:
 *   summary, rows, description, generated_at.
 */
$summary = $context['summary'] ?? [];
$rows = $context['rows'] ?? [];
$description = $context['description'] ?? 'All cases';
$generatedAt = $context['generated_at'] ?? date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Discrepancy Investigation Report</title>
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #295738; font-size: 10px; margin: 18px; }
    h1 { font-size: 18px; margin: 0 0 2px; color: #1f4a2e; }
    .sub { color: #6b7c6b; font-size: 10px; margin-bottom: 12px; }
    .meta { color: #6b7c6b; font-size: 9px; margin-bottom: 14px; }
    .cards { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 14px; }
    .cards td { background: #f6f8f1; border: 1px solid #dde0b6; border-radius: 6px; padding: 8px 10px; width: 20%; }
    .cards .label { color: #6b7c6b; font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; }
    .cards .value { font-size: 13px; font-weight: bold; color: #1f4a2e; margin-top: 3px; }
    table.cases { width: 100%; border-collapse: collapse; }
    table.cases th { background: #295738; color: #fff; text-align: left; font-size: 8.5px; padding: 5px 6px; }
    table.cases td { border-bottom: 1px solid #e8ecd8; padding: 5px 6px; font-size: 9px; }
    table.cases tr:nth-child(even) td { background: #fafbf6; }
    .right { text-align: right; }
    .status { font-weight: bold; }
    .footer { margin-top: 14px; color: #8a9a8a; font-size: 8.5px; }
</style>
</head>
<body>
    <h1>Discrepancy Investigation Report</h1>
    <div class="sub">R-Connect Finance &mdash; case workload and financial exposure</div>
    <div class="meta">
        Filters: <?= htmlspecialchars($description, ENT_QUOTES) ?><br>
        Generated: <?= htmlspecialchars($generatedAt, ENT_QUOTES) ?><br>
        Cases listed: <?= count($rows) ?>
    </div>

    <table class="cards">
        <tr>
            <td>
                <div class="label">Case Count</div>
                <div class="value"><?= (int) ($summary['case_count'] ?? 0) ?></div>
            </td>
            <td>
                <div class="label">Original Discrepancy</div>
                <div class="value">&#8369;<?= number_format((float) ($summary['original_discrepancy'] ?? 0), 2) ?></div>
            </td>
            <td>
                <div class="label">Recovered</div>
                <div class="value">&#8369;<?= number_format((float) ($summary['recovered'] ?? 0), 2) ?></div>
            </td>
            <td>
                <div class="label">Approved Adjustments</div>
                <div class="value">&#8369;<?= number_format((float) ($summary['approved_adjustments'] ?? 0), 2) ?></div>
            </td>
            <td>
                <div class="label">Outstanding Balance</div>
                <div class="value">&#8369;<?= number_format((float) ($summary['outstanding_balance'] ?? 0), 2) ?></div>
            </td>
        </tr>
    </table>

    <table class="cases">
        <thead>
            <tr>
                <th>Case</th>
                <th>Business Date</th>
                <th>Driver</th>
                <th>Type</th>
                <th>Status</th>
                <th class="right">Expected</th>
                <th class="right">Actual</th>
                <th class="right">Shortage</th>
                <th class="right">Excess</th>
                <th class="right">Outstanding</th>
                <th>Reviewer</th>
                <th class="right">Age</th>
                <th>Due Date</th>
                <th>Next Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="14">No cases match the selected filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($row['case_ref'] ?? ''), ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars((string) ($row['business_date'] ?? ''), ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars((string) ($row['driver_name'] ?? $row['driver_id'] ?? ''), ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars((string) ($row['case_type'] ?? ''), ENT_QUOTES) ?></td>
                    <td class="status"><?= htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES) ?></td>
                    <td class="right">&#8369;<?= number_format((float) ($row['expected_amount'] ?? 0), 2) ?></td>
                    <td class="right">
                        <?= $row['actual_amount'] !== null ? '&#8369;' . number_format((float) $row['actual_amount'], 2) : '&mdash;' ?>
                    </td>
                    <td class="right">&#8369;<?= number_format((float) ($row['discrepancy_amount'] ?? 0), 2) ?></td>
                    <td class="right">&#8369;<?= number_format((float) ($row['excess_amount'] ?? 0), 2) ?></td>
                    <td class="right">&#8369;<?= number_format((float) ($row['outstanding_balance'] ?? 0), 2) ?></td>
                    <td><?= htmlspecialchars((string) ($row['assigned_reviewer'] ?? 'Unassigned'), ENT_QUOTES) ?></td>
                    <td class="right"><?= (int) ($row['age_days'] ?? 0) ?>d</td>
                    <td><?= htmlspecialchars((string) ($row['due_date'] ?? ''), ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars((string) ($row['next_action'] ?? ''), ENT_QUOTES) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        Amounts are computed server-side from stored case, remittance, and ledger records.
        Original discrepancy amounts are immutable; recovered and adjustment figures come
        only from approved and posted ledger entries.
    </div>
</body>
</html>

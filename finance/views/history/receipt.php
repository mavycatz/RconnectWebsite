<?php

$receipt =
    $receipt ?? [];

$submittedBy =
    $receipt['submitted_by']
    ?? $receipt['confirmed_by']
    ?? 'N/A';

$remittedBy =
    $receipt['remitted_by']
    ?? $receipt['full_name']
    ?? 'N/A';

$reviewedBy =
    $receipt['reviewed_by']
    ?? 'N/A';

$reviewedAt =
    $receipt['reviewed_at']
    ?? 'N/A';

$driverName =
    $receipt['full_name']
    ?? $receipt['remitted_by']
    ?? 'N/A';

$busNo =
    $receipt['bus_no']
    ?? 'N/A';

$expectedCash =
    $receipt['expected_cash']
    ?? $receipt['total_collection']
    ?? null;

$actualCash =
    $receipt['actual_cash']
    ?? null;

$discrepancy =
    $receipt['discrepancy_amount']
    ?? null;

$totalPassengers =
    $receipt['total_passengers']
    ?? null;

$status =
    $receipt['status']
    ?? 'Pending';

$createdAt =
    $receipt['created_at']
    ?? null;

function moneyValue($amount): string
{
    return $amount === null
        ? 'N/A'
        : '₱' . number_format((float) $amount, 2);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Remittance Receipt
</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Courier New", Courier, monospace;
    background: #d9d9d9;
    padding: 30px 12px;
    font-size: 13px;
    color: #000;
}

.receipt {
    width: 300px;
    max-width: 100%;
    margin: 0 auto;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.zigzag {
    height: 14px;
    background:
        linear-gradient(45deg, transparent 33.333%, #fff 33.333%, #fff 66.667%, transparent 66.667%),
        linear-gradient(-45deg, transparent 33.333%, #fff 33.333%, #fff 66.667%, transparent 66.667%);
    background-size: 18px 14px;
    background-repeat: repeat-x;
    background-position: 0 0;
}

.inner {
    padding: 0 16px;
}

.center {
    text-align: center;
}

h1 {
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 1px;
}

h2 {
    font-size: 13px;
    font-weight: 700;
}

.company {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 3px;
}

.small {
    font-size: 11px;
}

.row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 4px 0;
}

.divider {
    border-top: 1px dashed #000;
    margin: 8px 0;
}

.divider-solid {
    border-top: 1px solid #000;
    margin: 8px 0;
}

.total {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-weight: 700;
    font-size: 14px;
}

.discrepancy-row {
    font-weight: 700;
}

.status-line {
    text-align: center;
    border: 1px solid #000;
    padding: 4px;
    font-weight: 700;
    letter-spacing: 2px;
    margin-top: 6px;
}

.footer-note {
    text-align: center;
    font-size: 10px;
    margin: 10px 0 0;
}

.signature {
    margin-top: 28px;
    text-align: center;
    font-size: 11px;
}

.signature .line {
    border-top: 1px solid #000;
    width: 180px;
    margin: 0 auto 4px;
    padding-top: 4px;
}

@media print {
    body {
        background: #fff;
        padding: 0;
    }

    .receipt {
        box-shadow: none;
    }
}
</style>

</head>

<body onload="window.print()">

<div class="receipt">

    <div class="zigzag"></div>

    <div class="inner">

        
        <div class="center" style="margin-top:10px;">

            <div class="company">
                R-CONNECT
            </div>

            <div class="small">
                Rizal Transportation Management System
            </div>

            <div class="small" style="margin-top:2px;">
                Finance Department
            </div>

        </div>

        <div class="divider-solid"></div>

        
        <div class="center">

            <h2>REMITTANCE RECEIPT</h2>

            <div class="small">
                No. <?= htmlspecialchars((string)($receipt['id'] ?? 'N/A')) ?>
            </div>

        </div>

        <div class="divider"></div>

        
        <div class="row">
            <span>DRIVER:</span>
            <span><?= htmlspecialchars($driverName) ?></span>
        </div>

        <div class="row">
            <span>BUS NO.:</span>
            <span><?= htmlspecialchars($busNo) ?></span>
        </div>

        <div class="row">
            <span>TRIP NO.:</span>
            <span><?= htmlspecialchars((string)($receipt['trip_no'] ?? 'N/A')) ?></span>
        </div>

        <div class="row">
            <span>PASSENGERS:</span>
            <span><?= $totalPassengers === null ? 'N/A' : (int) $totalPassengers ?></span>
        </div>

        <div class="row">
            <span>DATE:</span>
            <span><?= htmlspecialchars($createdAt ?? 'N/A') ?></span>
        </div>

        <div class="divider"></div>

        
        <div class="row">
            <span>EXPECTED CASH:</span>
            <span><?= moneyValue($expectedCash) ?></span>
        </div>

        <div class="row">
            <span>ACTUAL CASH:</span>
            <span><?= moneyValue($actualCash) ?></span>
        </div>

        <div class="row discrepancy-row">
            <span>DISCREPANCY:</span>
            <span><?= moneyValue($discrepancy) ?></span>
        </div>

        <div class="divider"></div>

        
        <div class="row">
            <span>SUBMITTED BY:</span>
            <span><?= htmlspecialchars($submittedBy) ?></span>
        </div>

        <div class="row">
            <span>REMITTED BY:</span>
            <span><?= htmlspecialchars($remittedBy) ?></span>
        </div>

        <div class="row">
            <span>REVIEWED BY:</span>
            <span><?= htmlspecialchars($reviewedBy) ?></span>
        </div>

        <div class="row">
            <span>REVIEWED AT:</span>
            <span><?= htmlspecialchars($reviewedAt) ?></span>
        </div>

        <div class="divider"></div>

        
        <div class="status-line">
            <?= htmlspecialchars($status) ?>
        </div>

        
        <div class="signature">

            <div class="line">
                Authorized Signature
            </div>

        </div>

        
        <div class="footer-note">
            This is a system-generated receipt. No signature required.
        </div>

        <div class="zigzag" style="margin-top:14px;"></div>

    </div>

</div>

</body>
</html>

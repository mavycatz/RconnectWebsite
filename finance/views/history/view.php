<?php

$remittance =
    $remittance ?? [];

$submittedBy =
    $remittance['submitted_by']
    ?? $remittance['confirmed_by']
    ?? 'N/A';

$remittedBy =
    $remittance['remitted_by']
    ?? $remittance['full_name']
    ?? 'N/A';

$reviewedBy =
    $remittance['reviewed_by']
    ?? 'N/A';

$reviewedAt =
    $remittance['reviewed_at']
    ?? 'N/A';

$status =
    $remittance['status']
    ?? 'Pending';

$driverName =
    $remittance['full_name']
    ?? $remittance['remitted_by']
    ?? 'N/A';

$busNo =
    $remittance['bus_no']
    ?? 'N/A';

$expectedCash =
    $remittance['expected_cash']
    ?? $remittance['total_collection']
    ?? null;

$actualCash =
    $remittance['actual_cash']
    ?? null;

$discrepancy =
    $remittance['discrepancy_amount']
    ?? null;

$totalPassengers =
    $remittance['total_passengers']
    ?? null;

$createdAt =
    $remittance['created_at']
    ?? null;

function receiptMoney($amount): string
{
    return $amount === null
        ? 'N/A'
        : '₱' . number_format((float) $amount, 2);
}

?>

<div
    style="
        position:relative;
        font-family:'Courier New',Courier,monospace;
        font-size:13px;
        color:#000;
        -webkit-user-select:none;
        -moz-user-select:none;
        user-select:none;
    "
>

    <div
        style="
            width:100%;
            height:18px;
            background:
                linear-gradient(45deg, transparent 33.333%, #ffffff 33.333%, #ffffff 66.667%, transparent 66.667%),
                linear-gradient(-45deg, transparent 33.333%, #ffffff 33.333%, #ffffff 66.667%, transparent 66.667%);
            background-size:22px 18px;
            background-repeat:repeat-x;
            background-position:0 0;
            border-bottom:1px solid #000;
        "
    ></div>

    <div style="padding:14px 16px 16px;">

        
        <div style="text-align:center;">

            <div style="font-size:15px;font-weight:700;letter-spacing:3px;">
                R-CONNECT
            </div>

            <div style="font-size:10px;">
                Rizal Transportation Management System
            </div>

            <div style="font-size:10px;margin-top:2px;">
                Finance Department
            </div>

        </div>

        <div style="border-top:1px solid #000;margin:10px 0;"></div>

        
        <div style="text-align:center;">

            <div style="font-size:13px;font-weight:700;">
                REMITTANCE RECEIPT
            </div>

            <div style="font-size:11px;">
                No. <?= htmlspecialchars((string)($remittance['id'] ?? 'N/A')) ?>
            </div>

        </div>

        <div style="border-top:1px dashed #000;margin:10px 0;"></div>

        
        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;">
            <span>DRIVER:</span>
            <span style="text-align:right;"><?= htmlspecialchars($driverName) ?></span>
        </div>

        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;">
            <span>BUS NO.:</span>
            <span><?= htmlspecialchars($busNo) ?></span>
        </div>

        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;">
            <span>TRIP NO.:</span>
            <span><?= htmlspecialchars((string)($remittance['trip_no'] ?? 'N/A')) ?></span>
        </div>

        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;">
            <span>PASSENGERS:</span>
            <span><?= $totalPassengers === null ? 'N/A' : (int) $totalPassengers ?></span>
        </div>

        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;">
            <span>DATE:</span>
            <span><?= htmlspecialchars($createdAt ?? 'N/A') ?></span>
        </div>

        <div style="border-top:1px dashed #000;margin:10px 0;"></div>

        
        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;">
            <span>EXPECTED CASH:</span>
            <span><?= receiptMoney($expectedCash) ?></span>
        </div>

        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;">
            <span>ACTUAL CASH:</span>
            <span><?= receiptMoney($actualCash) ?></span>
        </div>

        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;font-weight:700;">
            <span>DISCREPANCY:</span>
            <span><?= receiptMoney($discrepancy) ?></span>
        </div>

        <div style="border-top:1px dashed #000;margin:10px 0;"></div>

        
        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;">
            <span>SUBMITTED BY:</span>
            <span><?= htmlspecialchars($submittedBy) ?></span>
        </div>

        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;">
            <span>REMITTED BY:</span>
            <span><?= htmlspecialchars($remittedBy) ?></span>
        </div>

        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;">
            <span>REVIEWED BY:</span>
            <span><?= htmlspecialchars($reviewedBy) ?></span>
        </div>

        <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;">
            <span>REVIEWED AT:</span>
            <span><?= htmlspecialchars($reviewedAt) ?></span>
        </div>

        <div style="border-top:1px dashed #000;margin:10px 0;"></div>

        
        <div
            style="
                text-align:center;
                border:1px solid #000;
                padding:4px;
                font-weight:700;
                letter-spacing:2px;
            "
        >
            <?= htmlspecialchars($status) ?>
        </div>

        
        <button
            type="button"
            onclick="openOtpModal(<?= (int)($remittance['id'] ?? 0) ?>, '<?= htmlspecialchars((string)($remittance['full_name'] ?? ''), ENT_QUOTES) ?>')"
            style="
                width:100%;
                margin-top:14px;
                background:#2e4836;
                color:#ffffff;
                border:none;
                cursor:pointer;
                padding:12px;
                border-radius:10px;
                font-size:13px;
                font-weight:700;
                font-family:inherit;
                letter-spacing:1px;
            "
        >
            PRINT RECEIPT
        </button>

        <div style="text-align:center;font-size:10px;margin-top:10px;">
            System-generated preview. Not an official document.
        </div>

        <div
            style="
                width:100%;
                height:18px;
                background:
                    linear-gradient(45deg, transparent 33.333%, #ffffff 33.333%, #ffffff 66.667%, transparent 66.667%),
                    linear-gradient(-45deg, transparent 33.333%, #ffffff 33.333%, #ffffff 66.667%, transparent 66.667%);
                background-size:22px 18px;
                background-repeat:repeat-x;
                margin-top:14px;
            "
        ></div>

    </div>

</div>

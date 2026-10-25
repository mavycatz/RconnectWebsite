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

?>

<div
    style="
        background:#fef8f3;
        border:2px solid #e3eabb;
        border-radius:24px;
        overflow:hidden;
        box-shadow:0 6px 18px rgba(0,0,0,0.06);
        font-family:Poppins,sans-serif;
    "
>

    
    <div
        style="
            background:#2e4836;
            padding:20px 24px;
            display:flex;
            justify-content:space-between;
            align-items:center;
        "
    >

        <div>

            <div
                style="
                    color:white;
                    font-size:24px;
                    font-weight:800;
                    line-height:1;
                "
            >
                Remittance Receipt
            </div>

            <div
                style="
                    color:#d7e2d3;
                    margin-top:6px;
                    font-size:13px;
                "
            >
                Finance Transaction Details
            </div>

        </div>

        <div
            style="
                background:#f9f4da;
                color:#2e4836;
                padding:8px 16px;
                border-radius:999px;
                font-size:13px;
                font-weight:700;
            "
        >
            #<?= htmlspecialchars((string)($remittance['id'] ?? '')) ?>
        </div>

    </div>

    
    <div
        style="
            padding:24px;
        "
    >

        
        <div
            style="
                display:grid;
                grid-template-columns:1fr 1fr;
                gap:16px;
                margin-bottom:18px;
            "
        >

            <div
                style="
                    background:white;
                    border:2px solid #e3eabb;
                    border-radius:18px;
                    padding:16px;
                "
            >

                <div
                    style="
                        font-size:12px;
                        color:#7b8a7f;
                        font-weight:600;
                        margin-bottom:6px;
                    "
                >
                    DRIVER
                </div>

                <div
                    style="
                        color:#2e4836;
                        font-size:18px;
                        font-weight:800;
                    "
                >
                    <?= htmlspecialchars((string)$remittedBy) ?>
                </div>

            </div>

            <div
                style="
                    background:white;
                    border:2px solid #e3eabb;
                    border-radius:18px;
                    padding:16px;
                "
            >

                <div
                    style="
                        font-size:12px;
                        color:#7b8a7f;
                        font-weight:600;
                        margin-bottom:6px;
                    "
                >
                    BUS NUMBER
                </div>

                <div
                    style="
                        color:#2e4836;
                        font-size:18px;
                        font-weight:800;
                    "
                >
                    <?= htmlspecialchars((string)($remittance['bus_no'] ?? 'N/A')) ?>
                </div>

            </div>

        </div>

        
        <div
            style="
                display:grid;
                grid-template-columns:1fr 1fr 1fr;
                gap:16px;
                margin-bottom:18px;
            "
        >

            
            <div
                style="
                    background:#fff;
                    border:2px solid #e3eabb;
                    border-radius:18px;
                    padding:18px;
                "
            >

                <div
                    style="
                        font-size:12px;
                        color:#7b8a7f;
                        font-weight:600;
                        margin-bottom:8px;
                    "
                >
                    EXPECTED
                </div>

                <div
                    style="
                        color:#2e4836;
                        font-size:24px;
                        font-weight:800;
                    "
                >
                    ₱<?= number_format((float)($remittance['expected_cash'] ?? 0), 2) ?>
                </div>

            </div>

            
            <div
                style="
                    background:#fff;
                    border:2px solid #e3eabb;
                    border-radius:18px;
                    padding:18px;
                "
            >

                <div
                    style="
                        font-size:12px;
                        color:#7b8a7f;
                        font-weight:600;
                        margin-bottom:8px;
                    "
                >
                    ACTUAL
                </div>

                <div
                    style="
                        color:#2e4836;
                        font-size:24px;
                        font-weight:800;
                    "
                >
                    ₱<?= number_format((float)($remittance['actual_cash'] ?? 0), 2) ?>
                </div>

            </div>

            
            <div
                style="
                    background:#fff;
                    border:2px solid #e3eabb;
                    border-radius:18px;
                    padding:18px;
                "
            >

                <div
                    style="
                        font-size:12px;
                        color:#7b8a7f;
                        font-weight:600;
                        margin-bottom:8px;
                    "
                >
                    DISCREPANCY
                </div>

                <div
                    style="
                        color:#dc2626;
                        font-size:24px;
                        font-weight:800;
                    "
                >
                    ₱<?= number_format((float)($remittance['discrepancy_amount'] ?? 0), 2) ?>
                </div>

            </div>

        </div>

        
        <div
            style="
                display:grid;
                grid-template-columns:1fr 1fr 1fr;
                gap:16px;
                margin-bottom:18px;
            "
        >

            <div
                style="
                    background:white;
                    border:2px solid #e3eabb;
                    border-radius:18px;
                    padding:14px 16px;
                "
            >
                <div style="font-size:12px;color:#7b8a7f;font-weight:600;margin-bottom:6px;">
                    TRIP NO
                </div>
                <div style="color:#2e4836;font-size:15px;font-weight:700;">
                    <?= htmlspecialchars((string)($remittance['trip_no'] ?? 'N/A')) ?>
                </div>
            </div>

            <div
                style="
                    background:white;
                    border:2px solid #e3eabb;
                    border-radius:18px;
                    padding:14px 16px;
                "
            >
                <div style="font-size:12px;color:#7b8a7f;font-weight:600;margin-bottom:6px;">
                    TOTAL PASSENGERS
                </div>
                <div style="color:#2e4836;font-size:15px;font-weight:700;">
                    <?= (int)($remittance['total_passengers'] ?? 0) ?>
                </div>
            </div>

            <div
                style="
                    background:white;
                    border:2px solid #e3eabb;
                    border-radius:18px;
                    padding:14px 16px;
                "
            >
                <div style="font-size:12px;color:#7b8a7f;font-weight:600;margin-bottom:6px;">
                    DATE SUBMITTED
                </div>
                <div style="color:#2e4836;font-size:15px;font-weight:700;">
                    <?= htmlspecialchars((string)($remittance['created_at'] ?? 'N/A')) ?>
                </div>
            </div>

        </div>

        
        <div
            style="
                display:grid;
                grid-template-columns:1fr 1fr 1fr;
                gap:16px;
                margin-bottom:18px;
            "
        >

            <div
                style="
                    background:white;
                    border:2px solid #e3eabb;
                    border-radius:18px;
                    padding:14px 16px;
                "
            >
                <div style="font-size:12px;color:#7b8a7f;font-weight:600;margin-bottom:6px;">
                    SUBMITTED BY
                </div>
                <div style="color:#2e4836;font-size:15px;font-weight:700;">
                    <?= htmlspecialchars((string)$submittedBy) ?>
                </div>
            </div>

            <div
                style="
                    background:white;
                    border:2px solid #e3eabb;
                    border-radius:18px;
                    padding:14px 16px;
                "
            >
                <div style="font-size:12px;color:#7b8a7f;font-weight:600;margin-bottom:6px;">
                    REVIEWED BY
                </div>
                <div style="color:#2e4836;font-size:15px;font-weight:700;">
                    <?= htmlspecialchars((string)$reviewedBy) ?>
                </div>
            </div>

            <div
                style="
                    background:white;
                    border:2px solid #e3eabb;
                    border-radius:18px;
                    padding:14px 16px;
                "
            >
                <div style="font-size:12px;color:#7b8a7f;font-weight:600;margin-bottom:6px;">
                    REVIEWED AT
                </div>
                <div style="color:#2e4836;font-size:15px;font-weight:700;">
                    <?= htmlspecialchars((string)$reviewedAt) ?>
                </div>
            </div>

        </div>

        
        <div
            style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                background:white;
                border:2px solid #e3eabb;
                border-radius:18px;
                padding:16px 18px;
            "
        >

            <div>

                <div
                    style="
                        font-size:12px;
                        color:#7b8a7f;
                        font-weight:600;
                        margin-bottom:6px;
                    "
                >
                    REMITTED BY
                </div>

                <div
                    style="
                        color:#2e4836;
                        font-size:15px;
                        font-weight:700;
                    "
                >
                    <?= htmlspecialchars((string)$remittedBy) ?>
                </div>

            </div>

            <div
                style="
                    display:flex;
                    align-items:center;
                    gap:12px;
                "
            >

                <button
                    type="button"
                    onclick="openOtpModal(<?= (int)($remittance['id'] ?? 0) ?>, '<?= htmlspecialchars((string)($remittance['full_name'] ?? ''), ENT_QUOTES) ?>')"
                    style="
                        display:inline-flex;
                        align-items:center;
                        gap:6px;
                        background:#2e4836;
                        color:#ffffff;
                        border:none;
                        cursor:pointer;
                        padding:10px 18px;
                        border-radius:999px;
                        font-size:13px;
                        font-weight:700;
                        font-family:inherit;
                    "
                >
                    <i class="bi bi-printer"></i>
                    Print Receipt
                </button>

                <span
                    style="
                        display:inline-block;
                        padding:10px 18px;
                        border-radius:999px;
                        font-size:13px;
                        font-weight:800;
                        background:
                            <?= strtolower((string)$status) === 'completed'
                                ? '#dcfce7'
                                : '#fee2e2' ?>;
                        color:
                            <?= strtolower((string)$status) === 'completed'
                                ? '#166534'
                                : '#dc2626' ?>;
                    "
                >
                    <?= htmlspecialchars((string)$status) ?>
                </span>

            </div>

        </div>

    </div>

</div>

<?php
$isActive = !empty($profileUser['is_active']);
$isVerified = !empty($profileUser['email_verified']);
$isLocked = !empty($profileUser['permanently_locked']);
$failedAttempts = isset($profileUser['failed_attempts']) ? (int)$profileUser['failed_attempts'] : 0;
$lockCount = isset($profileUser['lock_count']) ? (int)$profileUser['lock_count'] : 0;
?>

<div class="card"
     style="
        background:linear-gradient(145deg,#ffffff,#f6f3d9);
        border:1px solid #e5e0a4;
        border-radius:28px;
        box-shadow:0 15px 35px rgba(0,0,0,0.08);
        padding:30px;
     ">

    <h5 style="font-weight:700;color:#295738;margin-bottom:1px;">
        Account Security Status
    </h5>

    
    <div style="display:flex;justify-content:space-between;margin-bottom:14px;">
        <span style="color:#555;">Account Active</span>
        <span style="
            padding:4px 14px;
            border-radius:20px;
            font-size:0.75rem;
            font-weight:600;
            background:<?= $isActive ? '#28a745' : '#dc3545' ?>;
            color:white;
        ">
            <?= $isActive ? 'ACTIVE' : 'INACTIVE' ?>
        </span>
    </div>

    
    <div style="display:flex;justify-content:space-between;margin-bottom:14px;">
        <span style="color:#555;">Email Verified</span>
        <span style="
            padding:4px 14px;
            border-radius:20px;
            font-size:0.75rem;
            font-weight:600;
            background:<?= $isVerified ? '#17a2b8' : '#ffc107' ?>;
            color:white;
        ">
            <?= $isVerified ? 'VERIFIED' : 'UNVERIFIED' ?>
        </span>
    </div>

    
    <div style="display:flex;justify-content:space-between;margin-bottom:14px;">
        <span style="color:#555;">Failed Login Attempts</span>
        <span style="
            font-weight:600;
            color:<?= $failedAttempts > 3 ? '#dc3545' : '#295738' ?>;
        ">
            <?= $failedAttempts ?>
        </span>
    </div>

    
    <div style="display:flex;justify-content:space-between;margin-bottom:14px;">
        <span style="color:#555;">Times Locked</span>
        <span style="font-weight:600;color:#295738;">
            <?= $lockCount ?>
        </span>
    </div>

    
    <div style="margin-bottom:14px;">
        <div style="color:#555;">Temporary Lock Until</div>
        <div style="font-weight:600;color:#295738;">
            <?=
                !empty($profileUser['lock_until'])
                    ? date('F d, Y h:i A', strtotime($profileUser['lock_until']))
                    : 'Not Locked';
            ?>
        </div>
    </div>

    
    <div style="display:flex;justify-content:space-between;">
        <span style="color:#555;">Permanent Lock</span>
        <span style="
            padding:4px 14px;
            border-radius:20px;
            font-size:0.75rem;
            font-weight:600;
            background:<?= $isLocked ? '#dc3545' : '#28a745' ?>;
            color:white;
        ">
            <?= $isLocked ? 'LOCKED' : 'SAFE' ?>
        </span>
    </div>

</div>
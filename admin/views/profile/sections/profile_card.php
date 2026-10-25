<?php
$role = $profileUser['role'] ?? 'finance_manager';

$roleGradient = $role === 'admin'
? 'linear-gradient(135deg,#295738,#1e3d28)'
: 'linear-gradient(135deg,#b8e405,#7fbf00)';

$statusColor = !empty($profileUser['is_active'])
? '#28a745'
: '#dc3545';

$fullName = trim(($profileUser['first_name'] ?? '') . ' ' . ($profileUser['last_name'] ?? ''));
$displayName = $fullName !== '' ? $fullName : ($profileUser['username'] ?? 'Unknown User');

$photo = !empty($profileUser['profile_photo'])
? '/uploads/' . htmlspecialchars($profileUser['profile_photo'])
: null;
?>

<div class="card"
     style="
        background:linear-gradient(145deg,#ffffff,#f6f3d9);
        border:1px solid #e5e0a4;
        border-radius:28px;
        box-shadow:0 15px 35px rgba(0,0,0,0.08);
        padding:35px;
        margin-bottom:20px;
        position:relative;
        overflow:hidden;
     ">

    <div style="
        position:absolute;
        width:160px;
        height:160px;
        background:<?= $roleGradient ?>;
        opacity:0.08;
        border-radius:50%;
        top:-40px;
        right:-40px;
    "></div>

<div class="text-center position-relative">

    <div style="
            width:120px;
            height:120px;
            margin:0 auto 18px auto;
            border-radius:50%;
            padding:4px;
            background:<?= $roleGradient ?>;
            display:flex;
            align-items:center;
            justify-content:center;
        ">

            <?php if ($photo): ?>
                <img src="<?= $photo ?>"
                     style="
                        width:100%;
                        height:100%;
                        border-radius:50%;
                        object-fit:cover;
                        background:#fff;
                     ">
            <?php else: ?>
                <div style="
                    width:100%;
                    height:100%;
                    border-radius:50%;
                    background:#fff;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    font-size:42px;
                    font-weight:700;
                    color:#295738;
                    letter-spacing:2px;
                ">
                    <?= strtoupper(substr($displayName, 0, 1)); ?>
                </div>
            <?php endif; ?>

        </div>

        <h4 style="font-weight:700;color:#295738;margin-bottom:6px;">
            <?= htmlspecialchars($displayName); ?>
        </h4>

        <span style="
            display:inline-block;
            padding:6px 20px;
            border-radius:30px;
            font-size:0.8rem;
            font-weight:600;
            letter-spacing:1px;
            background:<?= $roleGradient ?>;
            color:white;
            box-shadow:0 4px 10px rgba(0,0,0,0.1);
        ">
            <?= strtoupper($role); ?>
        </span>

        <div style="margin-top:12px;font-size:0.85rem;">
            <span style="
                display:inline-block;
                width:10px;
                height:10px;
                border-radius:50%;
                background:<?= $statusColor ?>;
                margin-right:6px;
            "></span>
            <?= !empty($profileUser['is_active']) ? 'Active Account' : 'Inactive Account'; ?>
        </div>

        </div>

        <hr style="margin:25px 0;opacity:0.2;">

        <div style="font-size:0.9rem;line-height:1.7;">

            <div style="margin-bottom:12px;">
                <strong style="color:#295738;">Email</strong><br>
            <?= htmlspecialchars($profileUser['email'] ?? 'Not Available'); ?>
        </div>

        <div style="margin-bottom:12px;">
            <strong style="color:#295738;">Phone</strong><br>
            <?= htmlspecialchars($profileUser['phone'] ?? 'Not Provided'); ?>
        </div>

        <div style="margin-bottom:12px;">
            <strong style="color:#295738;">Department</strong><br>
            <?= htmlspecialchars($profileUser['department'] ?? 'N/A'); ?>
        </div>

        <div style="margin-bottom:12px;">
            <strong style="color:#295738;">Address</strong><br>
            <?= htmlspecialchars($profileUser['address'] ?? 'Not Provided'); ?>
        </div>

        <div style="margin-bottom:12px;">
            <strong style="color:#295738;">Bio</strong><br>
            <?= nl2br(htmlspecialchars($profileUser['bio'] ?? 'No bio added yet.')); ?>
        </div>

        <div>
            <strong style="color:#295738;">Member Since</strong><br>
            <?=
!empty($profileUser['created_at'])
? date('F d, Y', strtotime($profileUser['created_at']))
: 'Not Available';
            ?>
        </div>

        </div>

        </div>
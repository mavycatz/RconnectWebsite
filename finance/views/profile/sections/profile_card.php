<?php
$role = $profileUser['role'] ?? 'finance_manager';
$roleLabel = $role === 'finance_manager' ? 'Finance Manager' : ($role === 'finance_officer' ? 'Finance Officer' : $role);
$roleGradient = 'linear-gradient(135deg,#198754,#2dc16b)';

$statusColor = !empty($profileUser['is_active'])
    ? '#198754'
    : '#dc3545';

$fullName = trim(($profileUser['first_name'] ?? '') . ' ' . ($profileUser['last_name'] ?? ''));
$displayName = $fullName !== '' ? $fullName : ($profileUser['username'] ?? 'Unknown User');

$photo = !empty($profileUser['profile_photo'])
    ? '/uploads/' . htmlspecialchars($profileUser['profile_photo'])
    : null;
?>

<div class="card"
     style="
        background:#fff;
        border:1.5px solid #d0e6d4;
        border-radius:20px;
        box-shadow:0 4px 16px rgba(0,0,0,0.04);
        padding:30px 24px;
        margin-bottom:20px;
        position:relative;
        overflow:hidden;
     ">

    <div style="
        position:absolute;
        width:200px;
        height:200px;
        background:linear-gradient(135deg,#198754,#2dc16b);
        opacity:0.04;
        border-radius:50%;
        top:-60px;
        right:-60px;
    "></div>

    <div class="text-center position-relative">

        <div style="
            width:110px;
            height:110px;
            margin:0 auto 16px auto;
            border-radius:50%;
            padding:3px;
            background:<?= $roleGradient ?>;
            display:flex;
            align-items:center;
            justify-content:center;
        ">

            <?php if ($photo): ?>
                <img src="<?= $photo ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;background:#fff;">
            <?php else: ?>
                <div style="width:100%;height:100%;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:38px;font-weight:700;color:#198754;">
                    <?= strtoupper(substr($displayName, 0, 1)); ?>
                </div>
            <?php endif; ?>

        </div>

        <h4 style="font-weight:700;color:#2f4f2f;margin-bottom:4px;font-size:1.15rem;">
            <?= htmlspecialchars($displayName); ?>
        </h4>

        <span style="
            display:inline-block;
            padding:4px 16px;
            border-radius:20px;
            font-size:0.75rem;
            font-weight:600;
            background:#e8f6ef;
            color:#198754;
        ">
            <i class="bi bi-patch-check-fill me-1" style="font-size:0.7rem;"></i> <?= $roleLabel ?>
        </span>

        <div style="margin-top:10px;font-size:0.8rem;color:#6b7280;">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $statusColor ?>;margin-right:6px;"></span>
            <?= !empty($profileUser['is_active']) ? 'Active' : 'Inactive' ?>
        </div>

    </div>

    <hr style="margin:20px 0;border-color:#e8f6ef;">

    <div style="font-size:0.85rem;line-height:1.8;color:#555;">

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <i class="bi bi-envelope-fill" style="color:#198754;font-size:0.8rem;"></i>
            <span><?= htmlspecialchars($profileUser['email'] ?? 'Not Available'); ?></span>
        </div>

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <i class="bi bi-telephone-fill" style="color:#198754;font-size:0.8rem;"></i>
            <span><?= htmlspecialchars($profileUser['phone'] ?? 'Not Provided'); ?></span>
        </div>

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <i class="bi bi-building" style="color:#198754;font-size:0.8rem;"></i>
            <span><?= htmlspecialchars($profileUser['department'] ?? 'N/A'); ?></span>
        </div>

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <i class="bi bi-geo-alt-fill" style="color:#198754;font-size:0.8rem;"></i>
            <span><?= htmlspecialchars($profileUser['address'] ?? 'Not Provided'); ?></span>
        </div>

        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:10px;">
            <i class="bi bi-chat-quote-fill" style="color:#198754;font-size:0.8rem;margin-top:3px;"></i>
            <span><?= nl2br(htmlspecialchars($profileUser['bio'] ?? 'No bio added yet.')); ?></span>
        </div>

        <div style="display:flex;align-items:center;gap:8px;">
            <i class="bi bi-calendar-check-fill" style="color:#198754;font-size:0.8rem;"></i>
            <span>Member since <?= !empty($profileUser['created_at']) ? date('F Y', strtotime($profileUser['created_at'])) : 'N/A'; ?></span>
        </div>

    </div>

</div>
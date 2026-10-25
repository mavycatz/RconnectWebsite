<?php
$roleLabels = [
'super_admin' => 'Super Admin',
'admin' => 'Admin',
'finance_manager' => 'Finance Manager',
'finance_officer' => 'Finance Officer',
'inspector' => 'Inspector',
'driver' => 'Driver',
];
$displayRole = $roleLabels[$profileUser['role'] ?? ''] ?? ucfirst($profileUser['role'] ?? 'User');
$lastLogin = !empty($profileUser['last_login']) && $profileUser['last_login'] !== '0000-00-00 00:00:00'
? date('M j, Y g:i A', strtotime($profileUser['last_login']))
: (!empty($profileUser['updated_at']) ? date('M j, Y g:i A', strtotime($profileUser['updated_at'])) : 'First login');
?>

<div style="background:#faf9e5;border:1px solid #e5e0a4;border-radius:20px;padding:20px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">

    <div class="d-flex align-items-center mb-3">
        <div style="width:50px;height:50px;border-radius:50%;background:#295738;color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.2rem;">
            <?= strtoupper(substr($profileUser['username'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="ms-3">
            <div style="font-weight:600;"><?= htmlspecialchars($displayRole) ?></div>
<small class="text-muted"><?= htmlspecialchars($displayRole) ?> Access</small>
 </div>
 </div>

 <hr>

 <p style="font-size:0.85rem;margin-bottom:6px;">
     <i class="bi bi-person me-1"></i> Username: <?= htmlspecialchars($profileUser['username'] ?? 'N/A') ?>
    </p>

    <p style="font-size:0.85rem;margin-bottom:6px;">
        <i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($profileUser['email'] ?? 'N/A') ?>
    </p>

    <p style="font-size:0.85rem;">
        <i class="bi bi-clock-history me-1"></i> Last Login: <?= htmlspecialchars($lastLogin) ?>
    </p>

    </div>

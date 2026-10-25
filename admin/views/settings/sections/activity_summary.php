<?php
$totalUsers = 0;
$activeSessions = 0;
$pendingUsers = 0;
try {
    $totalUsers = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $activeSessions = (int) $db->query("SELECT COUNT(*) FROM sessions WHERE last_activity > " . (time() - 900))->fetchColumn();
    $pendingUsers = (int) $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending' OR is_active = 0")->fetchColumn();
} catch (\Throwable $e) { }
?>

<div style="background:#faf9e5;border:1px solid #e5e0a4;border-radius:20px;padding:20px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">

    <h6 style="font-weight:700;color:#295738;">Activity Summary</h6>
    <hr>

    <div class="d-flex justify-content-between mb-2">
        <span>Total Users</span>
        <strong><?= number_format($totalUsers) ?></strong>
</div>

<div class="d-flex justify-content-between mb-2">
    <span>Active Sessions (15m)</span>
    <strong><?= number_format($activeSessions) ?></strong>
</div>

<div class="d-flex justify-content-between">
    <span>Inactive / Pending</span>
    <strong><?= number_format($pendingUsers) ?></strong>
</div>

</div>

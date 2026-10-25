<?php
$dbStatus = 'Unknown';
$dbPct = 0;
try {
    $db->query("SELECT 1");
    $dbStatus = 'Connected';
    $dbPct = 100;
} catch (\Throwable $e) {
    $dbStatus = 'Disconnected';
    $dbPct = 0;
}

$diskFree = @disk_free_space(__DIR__);
$diskTotal = @disk_total_space(__DIR__);
$diskPct = ($diskTotal > 0) ? round(($diskTotal - $diskFree) / $diskTotal * 100) : 0;

$serverLoad = @sys_getloadavg();
$loadPct = $serverLoad ? min(100, round($serverLoad[0] * 10)) : 0;
$loadColor = $loadPct < 50 ? 'bg-success' : ($loadPct < 80 ? 'bg-warning' : 'bg-danger');
?>

<div style="background:#faf9e5;border:1px solid #e5e0a4;border-radius:20px;padding:20px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">

    <h6 style="font-weight:700;color:#295738;">System Health</h6>
    <hr>

    <div class="mb-2">
        <small>Database: <?= htmlspecialchars($dbStatus) ?></small>
<div class="progress" style="height:8px;">
    <div class="progress-bar <?= $dbPct === 100 ? 'bg-success' : 'bg-danger' ?>" style="width:<?= $dbPct ?>%"></div>
</div>
</div>

<div class="mb-2">
    <small>Server Load</small>
    <div class="progress" style="height:8px;">
        <div class="progress-bar <?= $loadColor ?>" style="width:<?= $loadPct ?>%"></div>
</div>
</div>

<div>
    <small>Storage Usage (<?= $diskTotal ? round($diskTotal / 1073741824, 1) . ' GB' : 'N/A' ?>)</small>
<div class="progress" style="height:8px;">
    <div class="progress-bar <?= $diskPct < 50 ? 'bg-success' : ($diskPct < 80 ? 'bg-warning' : 'bg-danger') ?>" style="width:<?= $diskPct ?>%"></div>
</div>
</div>

</div>

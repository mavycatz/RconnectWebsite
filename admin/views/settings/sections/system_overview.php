<div style="background:#faf9e5;border:1px solid #e5e0a4;border-radius:20px;padding:20px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">

    <h6 style="font-weight:700;color:#295738;">System Overview</h6>
    <hr>

    <p style="font-size:0.9rem;margin-bottom:8px;">
        <strong>System Name:</strong> <?= htmlspecialchars($settings['system_name']) ?>
    </p>

    <p style="font-size:0.9rem;margin-bottom:8px;">
        <strong>PHP Version:</strong> <?= htmlspecialchars(PHP_VERSION) ?>
    </p>

    <p style="font-size:0.9rem;">
        <strong>Server:</strong> <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') ?>
    </p>

    </div>

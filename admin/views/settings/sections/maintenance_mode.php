<div style="background:#faf9e5;border:1px solid #e5e0a4;border-radius:20px;padding:20px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">

    <h6 style="font-weight:700;color:#295738;">Maintenance Mode</h6>
    <hr>

    <p style="font-size:0.8rem;color:#6c757d;margin-bottom:10px;">
        While enabled, only Super Admins can use the admin panel; every other role is sent to the maintenance page.
    </p>

    <p style="font-size:0.9rem;margin-bottom:12px;">
        Status:
        <strong style="color:<?= $settings['maintenance_mode'] === '1' ? '#b02a37' : '#295738' ?>;">
            <?= $settings['maintenance_mode'] === '1' ? 'Enabled' : 'Disabled' ?>
        </strong>
    </p>

    <form method="post">
        <input type="hidden" name="action" value="toggle_maintenance">
        <button type="submit" class="btn btn-sm" style="background:<?= $settings['maintenance_mode'] === '1' ? '#b7e4c7' : '#fff3b0' ?>;color:#1b4332;border:1px solid <?= $settings['maintenance_mode'] === '1' ? '#95d5b2' : '#ffe066' ?>;border-radius:50px;font-weight:600;">
            <i class="bi bi-tools me-1"></i>
            <?= $settings['maintenance_mode'] === '1' ? 'Disable Maintenance Mode' : 'Enable Maintenance Mode' ?>
        </button>
    </form>

</div>

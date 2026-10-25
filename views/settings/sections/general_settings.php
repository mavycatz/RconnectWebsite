<div style="background:#ffffff;border-radius:20px;padding:25px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">

    <h5 style="font-weight:700;color:#295738;">General Settings</h5>
    <hr>

    <form method="post">
        <input type="hidden" name="action" value="save_general">

        <div class="mb-3">
            <label class="form-label">System Name</label>
            <input type="text" class="form-control" name="system_name" value="<?= htmlspecialchars($settings['system_name']) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Support Email</label>
    <input type="email" class="form-control" name="support_email" value="<?= htmlspecialchars($settings['support_email']) ?>">
</div>

<div class="mb-3">
    <label class="form-label">Timezone</label>
    <select class="form-select" name="timezone">
        <option value="Asia/Manila" <?= $settings['timezone'] === 'Asia/Manila' ? 'selected' : '' ?>>Asia/Manila</option>
<option value="UTC" <?= $settings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
<option value="America/New_York" <?= $settings['timezone'] === 'America/New_York' ? 'selected' : '' ?>>America/New_York</option>
</select>
</div>

<button type="submit" class="btn" style="background:linear-gradient(90deg,#b8e405,#295738);color:white;border:none;">
            Save General Settings
</button>
</form>

</div>

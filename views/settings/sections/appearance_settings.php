<div style="background:#ffffff;border-radius:20px;padding:25px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">

    <h5 style="font-weight:700;color:#f0ad4e;">Appearance</h5>
    <hr>

    <form method="post">
        <input type="hidden" name="action" value="save_appearance">

        <div class="mb-3">
            <label class="form-label">Theme Mode</label>
            <select class="form-select" name="theme_mode">
                <option value="Light Mode" <?= $settings['theme_mode'] === 'Light Mode' ? 'selected' : '' ?>>Light Mode</option>
<option value="Dark Mode" <?= $settings['theme_mode'] === 'Dark Mode' ? 'selected' : '' ?>>Dark Mode</option>
<option value="System Default" <?= $settings['theme_mode'] === 'System Default' ? 'selected' : '' ?>>System Default</option>
</select>
</div>

<div class="mb-3">
    <label class="form-label">Primary Color</label>
    <input type="color" class="form-control form-control-color" name="primary_color" value="<?= htmlspecialchars($settings['primary_color']) ?>">
</div>

<button type="submit" class="btn btn-warning text-dark">
            Save Appearance
</button>
</form>

</div>

<div style="background:#faf9e5;border:1px solid #e5e0a4;border-radius:20px;padding:20px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">

    <h6 style="font-weight:700;color:#295738;">Quick Preferences</h6>
    <hr>

    <form method="post">
        <input type="hidden" name="action" value="save_preferences">

        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="enable_notifications" id="pref_notif" value="1" <?= $preferences['enable_notifications'] === '1' ? 'checked' : '' ?>>
<label class="form-check-label" for="pref_notif">Enable Notifications</label>
</div>

<div class="form-check form-switch mb-2">
    <input class="form-check-input" type="checkbox" name="auto_backup" id="pref_backup" value="1" <?= $preferences['auto_backup'] === '1' ? 'checked' : '' ?>>
<label class="form-check-label" for="pref_backup">Auto Backup</label>
</div>

<div class="form-check form-switch mb-2">
    <input class="form-check-input" type="checkbox" id="pref_maintenance" value="1" disabled <?= $settings['maintenance_mode'] === '1' ? 'checked' : '' ?>>
<label class="form-check-label" for="pref_maintenance">Maintenance Mode (global)</label>
</div>

<button type="submit" class="btn btn-sm mt-2" style="background:#295738;color:white;border:none;border-radius:50px;">
            Save Preferences
</button>
</form>

</div>

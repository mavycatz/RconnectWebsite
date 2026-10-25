<div style="background:#ffffff;border-radius:20px;padding:25px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">

    <h5 style="font-weight:700;color:#295738;">General Settings</h5>
    <hr>

    <form method="post">
        <input type="hidden" name="action" value="save_general">

        <div class="mb-3">
            <label class="form-label">System Name</label>
            <input type="text" class="form-control" name="system_name" value="<?= htmlspecialchars($settings['system_name']) ?>">
            <small class="text-muted">Shown on generated reports (for example the driver AI report).</small>
        </div>

        <button type="submit" class="btn" style="background:linear-gradient(90deg,#b8e405,#295738);color:white;border:none;">
            Save General Settings
        </button>
    </form>

</div>

<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
use App\Auth\Auth;
use App\Core\Database;

$sessionUser = Auth::user();
$role = $sessionUser['role'] ?? '';
$isManager = $role === 'finance_manager';
$userId = $sessionUser['id'] ?? 0;

$roleMap = ['finance_manager' => 'Finance Manager', 'finance_officer' => 'Finance Officer'];
$roleLabel = $roleMap[$role] ?? $role;

$db = Database::get();
$prefs = [];
if ($userId) {
    $stmt = $db->prepare("SELECT pref_key, pref_value FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) {
        $prefs[$row['pref_key']] = $row['pref_value'];
    }
}
$theme = $prefs['finance_theme'] ?? 'Light';
$period = $prefs['finance_period'] ?? 'Monthly';
?>

<?php include __DIR__ . '/../layout/navbarfinance.php'; ?>

<div class="main-content" id="mainContent">
<div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
<div class="container-fluid p-0">
    <?php require_once __DIR__ . '/../layout/_headernav.php'; ?>
    <?php include __DIR__ . '/layout/header.php'; ?>
    <?php include __DIR__ . '/layout/style.php'; ?>

<div class="container-fluid px-2 px-lg-3">
<div class="row g-4">

<div class="col-12">
<div style="background:#fff;border-radius:20px;padding:24px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
        <div style="width:44px;height:44px;border-radius:12px;background:#e8f6ef;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-person-circle" style="font-size:20px;color:#2e4836;"></i>
        </div>
        <div>
            <h5 style="font-weight:700;color:#295738;margin:0 0 2px;font-size:1rem;">Account</h5>
            <small style="color:#6b7280;font-size:0.78rem;"><?= htmlspecialchars($roleLabel) ?> — <?= htmlspecialchars($sessionUser['username'] ?? '') ?></small>
        </div>
    </div>
    <hr style="margin:0 0 16px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div style="background:#f9f4da;border-radius:12px;padding:14px;">
            <small style="color:#888;font-size:0.72rem;">Username</small>
            <div style="font-weight:600;color:#295738;font-size:0.9rem;margin-top:2px;"><?= htmlspecialchars($sessionUser['username'] ?? '-') ?></div>
        </div>
        <div style="background:#f9f4da;border-radius:12px;padding:14px;">
            <small style="color:#888;font-size:0.72rem;">Role</small>
            <div style="font-weight:600;color:#295738;font-size:0.9rem;margin-top:2px;"><?= htmlspecialchars($roleLabel) ?></div>
        </div>
    </div>
</div>
</div>

<div class="col-12">
<div style="background:#fff;border-radius:20px;padding:24px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
        <div style="width:44px;height:44px;border-radius:12px;background:#e8f6ef;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-palette" style="font-size:20px;color:#2e4836;"></i>
        </div>
        <div>
            <h5 style="font-weight:700;color:#295738;margin:0 0 2px;font-size:1rem;">Display</h5>
            <small style="color:#6b7280;font-size:0.78rem;">Customize your finance dashboard appearance.</small>
        </div>
    </div>
    <hr style="margin:0 0 16px;">
    <div class="mb-3">
        <label style="font-size:0.85rem;font-weight:500;color:#333;display:block;margin-bottom:6px;">Theme</label>
        <select id="settingTheme" class="form-select" style="max-width:220px;border-radius:10px;border:2px solid #cdd9b5;font-size:0.85rem;padding:8px 12px;">
            <option value="Light" <?= $theme === 'Light' ? 'selected' : '' ?>>Light</option>
            <option value="Dark" <?= $theme === 'Dark' ? 'selected' : '' ?>>Dark</option>
        </select>
    </div>
    <button class="btn save-setting-btn" data-key="finance_theme" data-select="settingTheme" style="background:#2e4836;color:#fff;border:none;border-radius:10px;padding:8px 20px;font-size:0.82rem;font-weight:500;cursor:pointer;">Save</button>
</div>
</div>

<?php if ($isManager): ?>
<div class="col-12">
<div style="background:#fff;border-radius:20px;padding:24px;box-shadow:0 3px 8px rgba(0,0,0,0.05);">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
        <div style="width:44px;height:44px;border-radius:12px;background:#e8f6ef;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-calendar-range" style="font-size:20px;color:#2e4836;"></i>
        </div>
        <div>
            <h5 style="font-weight:700;color:#295738;margin:0 0 2px;font-size:1rem;">Reporting Period</h5>
            <small style="color:#6b7280;font-size:0.78rem;">Set the default period for financial reports and analytics.</small>
        </div>
    </div>
    <hr style="margin:0 0 16px;">
    <div class="mb-3">
        <label style="font-size:0.85rem;font-weight:500;color:#333;display:block;margin-bottom:6px;">Default Period</label>
        <select id="settingPeriod" class="form-select" style="max-width:220px;border-radius:10px;border:2px solid #cdd9b5;font-size:0.85rem;padding:8px 12px;">
            <option value="Monthly" <?= $period === 'Monthly' ? 'selected' : '' ?>>Monthly</option>
            <option value="Quarterly" <?= $period === 'Quarterly' ? 'selected' : '' ?>>Quarterly</option>
            <option value="Yearly" <?= $period === 'Yearly' ? 'selected' : '' ?>>Yearly</option>
        </select>
    </div>
    <button class="btn save-setting-btn" data-key="finance_period" data-select="settingPeriod" style="background:#2e4836;color:#fff;border:none;border-radius:10px;padding:8px 20px;font-size:0.82rem;font-weight:500;cursor:pointer;">Save</button>
</div>
</div>
<?php endif; ?>

</div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
</div>
</div>

<script>
document.querySelectorAll(".save-setting-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
        var key = this.dataset.key;
        var selectId = this.dataset.select;
        var value = document.getElementById(selectId).value;
        var originalText = this.innerHTML;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
        this.disabled = true;
        fetch("/finance/api/settings_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "key=" + encodeURIComponent(key) + "&value=" + encodeURIComponent(value),
        })
            .then(function (r) {
                return r.json();
            })
            .then(
                function (d) {
                    if (d.success) {
                        this.innerHTML = '<i class="bi bi-check me-1"></i> Saved';
                        setTimeout(
                            function () {
                                this.innerHTML = originalText;
                                this.disabled = false;
                            }.bind(this),
                            1500,
                        );
                    } else {
                        this.innerHTML = '<i class="bi bi-x me-1"></i> Failed';
                        this.disabled = false;
                    }
                }.bind(this),
            )
            .catch(
                function () {
                    this.innerHTML = '<i class="bi bi-x me-1"></i> Error';
                    this.disabled = false;
                }.bind(this),
            );
    });
});
</script>

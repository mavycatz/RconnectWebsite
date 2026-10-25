<?php
declare(strict_types = 1);

require_once __DIR__ . '/../../../app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;
use App\Core\Database;

Session::start();

if (!Auth::check()) {
    header('Location: /public/login/index.php');
    exit;
}

$sessionUser = Auth::user();

if (!$sessionUser || !isset($sessionUser['username'])) {
    header('Location: /public/login/index.php');
    exit;
}

$db = Database::get();

$stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$sessionUser['username']]);
$profileUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profileUser) {
    header('Location: /public/login/index.php');
    exit;
}

$getSetting = function (string $key, string $default = '') use ($db): string {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['setting_value'] : $default;
    } catch (\Throwable $e) {
        return $default;
    }
};

$setSetting = function (string $key, string $value) use ($db): void {
    try {
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    } catch (\Throwable $e) { }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_general') {
        $setSetting('system_name', $_POST['system_name'] ?? '');
    } elseif ($action === 'toggle_maintenance') {
        $current = $getSetting('maintenance_mode', '0');
        $setSetting('maintenance_mode', $current === '1' ? '0' : '1');
    }

    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $redirect");
    exit;
}

$userRole = Auth::role();
$registry = require __DIR__ . '/sections/_registry.php';

$filterSections = function (array $sections) use ($userRole): array {
    return array_filter($sections, function (array $section) use ($userRole): bool {
        return $section['enabled'] && in_array($userRole, $section['allowedRoles'], true);
    });
};

$leftSections = $filterSections($registry['left_column']);
$rightSections = $filterSections($registry['right_column']);

/* Only settings with real consumers are loaded:
   - system_name: read by admin/api/Driver/ai-driver-report.php;
   - maintenance_mode: enforced by app/Core/Bootstrap.php. */
$settings = [
    'system_name' => $getSetting('system_name', 'Bus Transport System'),
    'maintenance_mode' => $getSetting('maintenance_mode', '0'),
];
?>

<?php include __DIR__ . '/../../assets/layout/navbaradmin.php'; ?>

<div class="main-content" id="mainContent">
    <div style="background:#fef8f3;border:1px solid #aaa8b1;border-radius:24px;padding:24px;">
        <div class="container-fluid p-0">

<?php include __DIR__ . '/layout/header.php'; ?>
<?php include __DIR__ . '/layout/style.php'; ?>

<div class="container-fluid px-2 px-lg-3">

    <div class="row g-4 align-items-start">

        <div class="col-12 col-lg-4">
            <div class="d-flex flex-column" style="gap:20px;">

        <?php foreach ($leftSections as $section): ?>
            <?php include $section['file']; ?>
        <?php endforeach; ?>

    </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="d-flex flex-column" style="gap:20px;">

                <?php foreach ($rightSections as $section): ?>
                    <div>
                        <?php include $section['file']; ?>
                    </div>
                <?php endforeach; ?>

            </div>
            </div>

            </div>

            </div>

<?php include __DIR__ . '/layout/footer.php'; ?>

</div>
</div>
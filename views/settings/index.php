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

$getPref = function (string $key, string $default = '') use ($db, $profileUser): string {
    try {
        $stmt = $db->prepare("SELECT pref_value FROM user_preferences WHERE user_id = ? AND pref_key = ?");
        $stmt->execute([$profileUser['id'], $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['pref_value'] : $default;
    } catch (\Throwable $e) {
        return $default;
    }
};

$setPref = function (string $key, string $value) use ($db, $profileUser): void {
    try {
        $stmt = $db->prepare("INSERT INTO user_preferences (user_id, pref_key, pref_value)
            VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value)");
        $stmt->execute([$profileUser['id'], $key, $value]);
    } catch (\Throwable $e) { }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_general') {
        $setSetting('system_name', $_POST['system_name'] ?? '');
        $setSetting('support_email', $_POST['support_email'] ?? '');
        $setSetting('timezone', $_POST['timezone'] ?? '');
    } elseif ($action === 'save_security') {
        $setSetting('password_expiry_days', $_POST['password_expiry_days'] ?? '90');
        $setSetting('login_attempt_limit', $_POST['login_attempt_limit'] ?? '5');
        $setSetting('enable_2fa', isset($_POST['enable_2fa']) ? '1' : '0');
    } elseif ($action === 'save_appearance') {
        $setSetting('theme_mode', $_POST['theme_mode'] ?? 'Light Mode');
        $setSetting('primary_color', $_POST['primary_color'] ?? '#295738');
    } elseif ($action === 'save_preferences') {
        $setPref('enable_notifications', isset($_POST['enable_notifications']) ? '1' : '0');
        $setPref('auto_backup', isset($_POST['auto_backup']) ? '1' : '0');
    } elseif ($action === 'toggle_maintenance') {
        $current = $getSetting('maintenance_mode', '0');
        $setSetting('maintenance_mode', $current === '1' ? '0' : '1');
    } elseif ($action === 'restart_system') {
        $setSetting('system_restart_requested', date('Y-m-d H:i:s'));
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    } elseif ($action === 'backup_now') {
        $backupDir = __DIR__ . '/../../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_{$timestamp}.sql";
        $filepath = $backupDir . '/' . $filename;

        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $sql = "-- RConnect TMS Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $sql .= "\nDROP TABLE IF EXISTS `$table`;\n";
            $sql .= $create['Create Table'] . ";\n\n";

            $rows = $db->query("SELECT * FROM `$table`");
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $cols = array_map(fn($c) => "`$c`", array_keys($row));
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $db->quote($v), array_values($row));
                $sql .= "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
            }
            $sql .= "\n";
        }

        file_put_contents($filepath, $sql);
        $setSetting('last_backup', $timestamp);
        $setSetting('last_backup_file', $filename);
    } elseif ($action === 'save_facebook') {
        $setSetting('facebook_page_id', $_POST['facebook_page_id'] ?? '');
        $setSetting('facebook_page_access_token', $_POST['facebook_page_access_token'] ?? '');
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

$settings = [
'system_name' => $getSetting('system_name', 'Bus Transport System'),
'support_email' => $getSetting('support_email', ''),
'timezone' => $getSetting('timezone', 'Asia/Manila'),
'environment' => $getSetting('environment', 'Production'),
'app_version' => $getSetting('app_version', 'v1.0.0'),
'password_expiry_days' => $getSetting('password_expiry_days', '90'),
'login_attempt_limit' => $getSetting('login_attempt_limit', '5'),
'enable_2fa' => $getSetting('enable_2fa', '0'),
'theme_mode' => $getSetting('theme_mode', 'Light Mode'),
'primary_color' => $getSetting('primary_color', '#295738'),
'maintenance_mode' => $getSetting('maintenance_mode', '0'),
];

$preferences = [
'enable_notifications' => $getPref('enable_notifications', '1'),
'auto_backup' => $getPref('auto_backup', '1'),
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
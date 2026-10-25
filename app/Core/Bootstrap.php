<?php

declare(strict_types=1);

header("Cache-Control: no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

/*
|--------------------------------------------------------------------------
| Application clock
|--------------------------------------------------------------------------
|
| Every PHP-side timestamp helper (date(), time(), strtotime(), DateTime) must
| read Philippine time, the same basis the driver GPS pipeline writes on and
| the same basis App\Core\Database pins onto its MySQL session. Without this,
| PHP dates sit on the server's default zone while SQL NOW() sits on +08:00,
| and the two disagree by however far the host is from Manila.
|
| Set here as well as in Database::applySessionTimezone() because scripts reach
| the app through more than one entry point, and a date computed before the
| connection is opened must already be on the right clock.
|
*/

require_once __DIR__ . '/../config/database.php';

if (date_default_timezone_get() !== APP_TIMEZONE) {
    date_default_timezone_set(APP_TIMEZONE);
}

ini_set('display_errors', '1');

error_reporting(E_ALL);

spl_autoload_register(function ($class) {

    $prefix = 'App\\';

    $base_dir =
        dirname(__DIR__) . '/';

    if (
        strncmp(
            $prefix,
            $class,
            strlen($prefix)
        ) !== 0
    ) {
        return;
    }

    $relative_class =
        substr(
            $class,
            strlen($prefix)
        );

    $parts =
        explode(
            '\\',
            $relative_class
        );

    /*
    |--------------------------------------------------------------------------
    | CUSTOM FOLDER MAP
    |--------------------------------------------------------------------------
    */

    $map = [

        'Auth'   => 'auth',

        'Core'   => 'Core',

        'Models' => 'Models'
    ];

    if (
        isset($map[$parts[0]])
    ) {

        $parts[0] =
            $map[$parts[0]];
    }

    $file =
        $base_dir .
        implode('/', $parts) .
        '.php';

    if (
        file_exists($file)
    ) {

        require_once $file;

    } else {

        die(
            'Autoload failed: ' .
            $file
        );
    }
});

use App\Auth\Session;

if (
    class_exists(
        Session::class,
        true
    )
) {

    Session::start();

} else {

    die(
        'Session class not found.'
    );
}

/* Ensure system_settings and user_preferences tables exist */
try {
    $db = \App\Core\Database::tryGet();
    if ($db !== null) {
        $db->exec("CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS user_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            pref_key VARCHAR(100) NOT NULL,
            pref_value TEXT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_pref (user_id, pref_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $db->query("SELECT COUNT(*) FROM system_settings");
        if ((int)$stmt->fetchColumn() === 0) {
            $db->exec("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
                ('system_name', 'Bus Transport System'),
                ('support_email', 'support@bustransport.com'),
                ('timezone', 'Asia/Manila'),
                ('environment', 'Production'),
                ('app_version', 'v1.0.0'),
                ('password_expiry_days', '90'),
                ('login_attempt_limit', '5'),
                ('enable_2fa', '0'),
                ('theme_mode', 'Light Mode'),
                ('primary_color', '#295738'),
                ('maintenance_mode', '0'),
                ('session_timeout', '900')");
        }

        $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME NULL DEFAULT NULL AFTER updated_at");
    }
} catch (\Throwable $e) {}

/* Maintenance mode check */
try {
    $db = \App\Core\Database::tryGet();
    if ($db !== null) {
        $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $maintenanceMode = $row ? $row['setting_value'] : '0';

        if ($maintenanceMode === '1') {
            $user = \App\Auth\Auth::user();
            $role = $user['role'] ?? '';
            if ($role !== 'super_admin') {
                $maintenancePage = '/admin/views/maintenance.php';
                $loginPage = '/public/login/index.php';
                $requestUri = $_SERVER['REQUEST_URI'] ?? '';
                if (!str_contains($requestUri, $maintenancePage) && !str_contains($requestUri, $loginPage)) {
                    header('Location: ' . $maintenancePage);
                    exit;
                }
            }
        }
    }
} catch (\Throwable $e) {}
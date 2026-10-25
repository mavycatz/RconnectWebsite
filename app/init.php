<?php
declare(strict_types=1);

if(!defined('APP_LOADED')){
    define('APP_LOADED', true);
}
/* Disable browser cache */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: no-cache, private", false);
header("Pragma: no-cache");
header("Expires: 0");

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/Core/Bootstrap.php';
require_once __DIR__ . '/auth/Session.php';
require_once __DIR__ . '/auth/Auth.php';
require_once __DIR__ . '/auth/RememberAuth.php';

use App\Auth\Session;
use App\Auth\RememberAuth;
use App\Core\Database;

/* Ensure sessions table exists */
try {
    $db = Database::get();
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(255) PRIMARY KEY,
        payload LONGTEXT NULL,
        last_activity INT NULL,
        user_id INT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        INDEX idx_last_activity (last_activity),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER last_activity");
    $db->exec("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) NULL AFTER user_id");
    $db->exec("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS user_agent TEXT NULL AFTER ip_address");
} catch (\Throwable $e) {}

/* Ensure system_settings table exists */
try {
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
} catch (\Throwable $e) {}

Session::start();

/* =========================
   AUTO LOGIN FROM COOKIE
========================= */
RememberAuth::loginFromCookie();

/* =========================
   FINANCE MODULE — WARN ON MISSING SECRETS
========================= */
if (defined('FINANCE_MODULE') && FINANCE_MODULE) {
    $envPath = __DIR__ . '/../finance/config/.env';
    $hasOpenAi = (bool)getenv('FINANCE_OPENAI_KEY');
    if (!$hasOpenAi && !file_exists($envPath)) {
        error_log('Finance: FINANCE_OPENAI_KEY not set and no .env file found at ' . $envPath);
    }
}

/* =========================
   FINANCE DEBUG MODE
========================= */
if (!defined('FINANCE_DEBUG')) {
    $env = getenv('APP_ENV') ?: 'production';
    define('FINANCE_DEBUG', $env === 'development' || $env === 'local');
}

if (!FINANCE_DEBUG) {
    ini_set('display_errors', '0');
    error_reporting(0);
}
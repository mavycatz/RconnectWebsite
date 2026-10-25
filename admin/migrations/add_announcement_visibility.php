<?php
declare(strict_types=1);

/**
 * Additive migration for the announcements audience model.
 *
 * Adds:
 *   - visibility   ENUM('public','private') NOT NULL DEFAULT 'public'
 *   - target_roles VARCHAR(255) NULL   (comma-separated role keys, private posts only)
 *
 * Safe to re-run: each column is only added when missing. Existing rows keep
 * the 'public' default, so no previously visible announcement becomes hidden.
 *
 * Run:  php admin/migrations/add_announcement_visibility.php
 */

require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/add_announcement_author_role.php';

use App\Core\Database;

function admin_ensure_announcement_visibility(\PDO $db): void
{
    $visibility = $db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite' ? "VARCHAR(16) NOT NULL DEFAULT 'public'" : "ENUM('public','private') NOT NULL DEFAULT 'public'";
    foreach (['visibility' => $visibility, 'target_roles' => 'VARCHAR(255) NULL DEFAULT NULL'] as $column => $definition) {
        if (admin_announcement_has_column($db, $column)) { continue; }
        if ($db->inTransaction()) { throw new \RuntimeException('Apply php admin/migrations/add_announcement_visibility.php before posting announcements.'); }
        try { $db->exec("ALTER TABLE announcements ADD COLUMN $column $definition"); }
        catch (\Throwable $e) {
            if (admin_announcement_has_column($db, $column)) { continue; }
            throw new \RuntimeException('Announcement audience schema upgrade required. Apply php admin/migrations/add_announcement_visibility.php with database ALTER permission.', 0, $e);
        }
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    admin_ensure_announcement_visibility(Database::get());
    echo "Announcement audience columns ready.\n";
}

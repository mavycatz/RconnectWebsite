<?php
declare(strict_types=1);

/**
 * Additive migration: store the announcement author's role for attribution.
 *
 * Adds:
 *   - author_role VARCHAR(50) NULL   (role key at creation time, e.g. admin, driver)
 *
 * Safe to re-run: the column is only added when missing. Legacy rows keep
 * NULL until the classification migration fills what it can infer.
 *
 * Run:  php admin/migrations/add_announcement_author_role.php
 */

require_once __DIR__ . '/../../app/Core/Database.php';

use App\Core\Database;

function admin_announcement_has_column(\PDO $db, string $column): bool
{
    if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        foreach ($db->query('PRAGMA table_info(announcements)')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if ($row['name'] === $column) { return true; }
        }
        return false;
    }
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(['announcements', $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function admin_ensure_announcement_author_role(\PDO $db): void
{
    if (admin_announcement_has_column($db, 'author_role')) { return; }
    if ($db->inTransaction()) { throw new \RuntimeException('Apply php admin/migrations/add_announcement_author_role.php before posting announcements.'); }
    try {
        $db->exec('ALTER TABLE announcements ADD COLUMN author_role VARCHAR(50) NULL DEFAULT NULL');
    } catch (\Throwable $e) {
        if (admin_announcement_has_column($db, 'author_role')) { return; }
        throw new \RuntimeException('Announcement schema upgrade required. Apply php admin/migrations/add_announcement_author_role.php with database ALTER permission.', 0, $e);
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    admin_ensure_announcement_author_role(Database::get());
    echo "announcements.author_role ready.\n";
}

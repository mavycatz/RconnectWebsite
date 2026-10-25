<?php declare(strict_types=1);
require_once __DIR__ . '/../services/RoleManagement/ArchiveLifecycle.php';
require_once __DIR__ . '/../../app/Core/Database.php';
use Admin\Services\RoleManagement\ArchiveLifecycle;
use Admin\Services\RoleManagement\ArchiveMetadata;

function admin_reconcile_legacy_archives(PDO $db): int
{
    $changed = 0;
    $users = $db->query("SELECT id, role FROM users WHERE LOWER(status) = 'inactive'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        $role = str_replace(' ', '_', strtolower((string) $user['role']));
        $source = in_array($role, ['admin', 'super_admin'], true) ? 'admin'
            : (in_array($role, ['finance_officer', 'finance_manager'], true) ? 'finance' : null);
        if ($source === null || ArchiveMetadata::details($db, $source, (string) $user['id']) === null) { continue; }
        ArchiveLifecycle::ensureArchivedStatus($db, 'users');
        $stmt = $db->prepare("UPDATE users SET status='Archived' WHERE id=? AND LOWER(status)='inactive'");
        $stmt->execute([$user['id']]); $changed += $stmt->rowCount();
    }
    return $changed;
}
if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    echo admin_reconcile_legacy_archives(App\Core\Database::get()) . " evidenced legacy user archives reconciled.\n";
}

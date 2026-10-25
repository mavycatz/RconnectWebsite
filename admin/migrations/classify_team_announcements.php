<?php
declare(strict_types=1);

/**
 * Classify legacy announcements into the two feed categories.
 *
 *   Public Updates = visibility 'public'   (official admin System Announcement rows)
 *   Team Feed      = visibility 'private'  (operational driver/inspector posts)
 *
 * A row is operational when it was NOT written by the admin composer:
 *   - type is anything other than 'System Announcement', or
 *   - bus_id / driver_name / recording_url carries a value.
 *
 * Operational rows are moved to the Team Feed and given the team-wide role
 * audience when they have none, so the internal roles (and the mobile apps)
 * keep seeing them. Only rows still on the default public visibility or
 * missing an author role are touched, so the script is idempotent.
 *
 * Usage:
 *   php admin/migrations/classify_team_announcements.php --dry-run   Show what would change
 *   php admin/migrations/classify_team_announcements.php              Apply the classification
 */

require_once __DIR__ . '/../../app/Core/Database.php';

use App\Core\Database;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$db = Database::get();

$teamRoles = 'super_admin,admin,finance_manager,finance_officer,inspector,driver,dispatcher';

$operational = "
    (
        (type IS NOT NULL AND type <> 'System Announcement')
        OR (bus_id IS NOT NULL AND bus_id <> '')
        OR (driver_name IS NOT NULL AND driver_name <> '')
        OR (recording_url IS NOT NULL AND recording_url <> '')
    )
";

$counts = [
    'author_role_inspector' => (int)$db->query("SELECT COUNT(*) FROM announcements WHERE author_role IS NULL AND type = 'Inspector Report'")->fetchColumn(),
    'author_role_driver'    => (int)$db->query("SELECT COUNT(*) FROM announcements WHERE author_role IS NULL AND type <> 'Inspector Report' AND driver_name IS NOT NULL AND driver_name <> ''")->fetchColumn(),
    'author_role_admin'     => (int)$db->query("SELECT COUNT(*) FROM announcements a JOIN users u ON u.username = a.reported_by WHERE a.author_role IS NULL AND a.type = 'System Announcement'")->fetchColumn(),
    'team_candidates'       => (int)$db->query("SELECT COUNT(*) FROM announcements WHERE (visibility = 'public' OR visibility IS NULL) AND {$operational}")->fetchColumn(),
];

echo "Classification plan:\n";
echo "  inspector author_role to fill: {$counts['author_role_inspector']}\n";
echo "  driver author_role to fill:    {$counts['author_role_driver']}\n";
echo "  admin author_role to fill:     {$counts['author_role_admin']}\n";
echo "  operational rows to move to Team Feed: {$counts['team_candidates']}\n";

if ($dryRun) {
    echo "Dry run: nothing changed.\n";
    exit(0);
}

$db->exec("UPDATE announcements SET author_role = 'inspector' WHERE author_role IS NULL AND type = 'Inspector Report'");
$db->exec("UPDATE announcements SET author_role = 'driver' WHERE author_role IS NULL AND type <> 'Inspector Report' AND driver_name IS NOT NULL AND driver_name <> ''");
$db->exec("UPDATE announcements a JOIN users u ON u.username = a.reported_by SET a.author_role = u.role WHERE a.author_role IS NULL AND a.type = 'System Announcement'");

$moved = $db->exec("
    UPDATE announcements
    SET visibility = 'private',
        target_roles = CASE
            WHEN target_roles IS NULL OR target_roles = '' THEN '{$teamRoles}'
            ELSE target_roles
        END
    WHERE (visibility = 'public' OR visibility IS NULL)
      AND {$operational}
");

echo "Updated {$moved} operational row(s) into the Team Feed.\n";
echo "Done.\n";

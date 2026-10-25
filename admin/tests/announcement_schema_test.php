<?php declare(strict_types=1);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require_once __DIR__ . '/../migrations/add_announcement_author_role.php';
require_once __DIR__ . '/../migrations/add_announcement_visibility.php';
$checks = 0;
function check(bool $ok, string $message): void {
    global $checks; if (!$ok) { throw new RuntimeException($message); } $checks++;
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE announcements(id INTEGER PRIMARY KEY, title TEXT); INSERT INTO announcements VALUES(1,"Existing")');
admin_ensure_announcement_author_role($db);
check(admin_announcement_has_column($db, 'author_role'), 'Missing author column repaired');
check($db->query('SELECT author_role FROM announcements WHERE id=1')->fetchColumn() === null, 'Legacy author stays unknown');
admin_ensure_announcement_visibility($db);
check($db->query('SELECT visibility FROM announcements WHERE id=1')->fetchColumn() === 'public', 'Legacy audience retained');
admin_ensure_announcement_author_role($db); admin_ensure_announcement_visibility($db);
check(count($db->query('PRAGMA table_info(announcements)')->fetchAll()) === 5, 'Repeated readiness creates no duplicate columns');
check($db->query('SELECT title FROM announcements WHERE id=1')->fetchColumn() === 'Existing', 'Existing record untouched');
class DeniedAnnouncementPDO extends PDO {
    public function exec(string $statement): int|false {
        if (str_starts_with($statement, 'ALTER')) { throw new PDOException('ALTER denied'); }
        return parent::exec($statement);
    }
}
$denied = new DeniedAnnouncementPDO('sqlite::memory:');
$denied->exec('CREATE TABLE announcements(id INTEGER PRIMARY KEY)');
try { admin_ensure_announcement_author_role($denied); throw new LogicException('Expected denied upgrade'); }
catch (RuntimeException $e) { check(str_contains($e->getMessage(), 'admin/migrations/add_announcement_author_role.php'), 'Permission failure identifies existing migration'); }
check((int) $denied->query('SELECT COUNT(*) FROM announcements')->fetchColumn() === 0, 'Failed readiness makes no post');
echo "$checks announcement schema checks passed.\n";

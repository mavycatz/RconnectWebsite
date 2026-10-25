<?php declare(strict_types=1);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Auth/Auth.php';
require_once __DIR__ . '/../Controllers/Announcement/AnnouncementController.php';
class AnnouncementFixtureController extends Admin\Controllers\Announcement\AnnouncementController {
    public bool $facebookSucceeds = true;
    public int $externalCalls = 0;
    protected function postToFacebook(string $title, string $message, ?string $imagePath, int $announcementId): array {
        $this->externalCalls++;
        return ['facebook_post_id' => $this->facebookSucceeds ? 'fb-fixture' : null,
            'error' => $this->facebookSucceeds ? null : 'Fixture provider failure', 'error_type' => null];
    }
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
(new ReflectionProperty(App\Core\Database::class, 'instance'))->setValue(null, $db);
$db->exec("CREATE TABLE announcements(id INTEGER PRIMARY KEY AUTOINCREMENT,type TEXT,title TEXT,message TEXT,
    image_path TEXT,priority TEXT,status TEXT,reported_by TEXT,created_at TEXT,post_to_facebook INT);");
$_SESSION['user'] = ['id' => 7, 'role' => 'admin', 'username' => 'Fixture Admin', 'logged_in' => true];
$checks = 0;
function check(bool $ok, string $message): void { global $checks; if (!$ok) { throw new RuntimeException($message); } $checks++; }
$controller = new AnnouncementFixtureController();
$first = $controller->save(['title' => 'Public notice', 'message' => 'Message', 'visibility' => 'public',
    'publish_date' => '2026-10-13 08:00:00', 'image_path' => '/uploads/fixture.jpg', 'author_role' => 'super_admin']);
$stored = $db->query('SELECT * FROM announcements WHERE id=1')->fetch(PDO::FETCH_ASSOC);
check($first['success'] && $stored['author_role'] === 'admin', 'Missing schema upgraded and author comes from session');
check($stored['created_at'] === '2026-10-13 08:00:00' && $stored['image_path'] === '/uploads/fixture.jpg', 'Date and image preserved');
check($stored['visibility'] === 'public' && $stored['target_roles'] === null, 'Public audience retained');
$private = $controller->save(['title' => 'Private notice', 'message' => 'Internal', 'visibility' => 'private', 'target_roles' => ['driver', 'inspector']]);
$stored = $db->query('SELECT * FROM announcements WHERE id=2')->fetch(PDO::FETCH_ASSOC);
check($private['success'] && $stored['target_roles'] === 'driver,inspector', 'Private target roles retained');
$external = $controller->save(['title' => 'External notice', 'message' => 'Message', 'post_to_fb' => true]);
check($external['facebook_post_id'] === 'fb-fixture' && $controller->externalCalls === 1, 'Optional provider success contract preserved');
$controller->facebookSucceeds = false;
$failedExternal = $controller->save(['title' => 'External failure', 'message' => 'Message', 'post_to_fb' => true]);
check($failedExternal['success'] && $failedExternal['facebook_error'] === 'Fixture provider failure', 'Provider failure retains one successful local post');
check((int) $db->query('SELECT COUNT(*) FROM announcements')->fetchColumn() === 4, 'Each submission creates one local announcement');
try { $controller->save(['title' => 'Invalid', 'visibility' => 'private', 'target_roles' => []]); throw new LogicException('Expected audience validation'); }
catch (InvalidArgumentException $e) { check((int) $db->query('SELECT COUNT(*) FROM announcements')->fetchColumn() === 4, 'Invalid audience has no insert or publication'); }
echo "$checks announcement posting checks passed.\n";

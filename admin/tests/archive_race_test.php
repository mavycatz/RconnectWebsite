<?php declare(strict_types=1);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require_once __DIR__ . '/../services/RoleManagement/ArchiveLifecycle.php';
use Admin\Services\RoleManagement\ArchiveLifecycle;

if (($argv[1] ?? '') === 'worker') {
    $db = new PDO('sqlite:' . $argv[2]); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $db->exec('PRAGMA busy_timeout=5000');
    try {
        echo json_encode(ArchiveLifecycle::transition($db, ['id'=>9,'username'=>'Race Admin','role'=>'super_admin'], 'bus', '1', 'archive', 'Concurrent archive fixture'));
    } catch (PDOException $e) {
        if (!str_contains($e->getMessage(), 'locked')) { throw $e; }
        echo json_encode(['success'=>false,'transient_lock'=>true]);
    }
    exit;
}
$directory = 'C:\Users\MAVHIL~1\AppData\Local\Temp\opencode';
if (!is_dir($directory)) { throw new RuntimeException('Approved temporary directory missing.'); }
$file = tempnam($directory, 'archive_race_');
try {
    $db = new PDO('sqlite:' . $file); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE buses(id INT PRIMARY KEY,bus_id TEXT,status TEXT,operational_status TEXT,qr_token TEXT);
        INSERT INTO buses VALUES(1,'BUS-1','Active','Maintenance','original');
        CREATE TABLE audit_trail(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INT,username TEXT,user_role TEXT,module TEXT,action TEXT,record_id TEXT,
        description TEXT,previous_values TEXT,new_values TEXT,ip_address TEXT,user_agent TEXT,status TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $processes = [];
    for ($i=0; $i<2; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $file], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Could not start isolated race worker.'); }
        fclose($pipes[0]); $processes[] = [$process,$pipes];
    }
    $success = 0;
    foreach ($processes as [$process,$pipes]) {
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException('Race worker failed: ' . $error); }
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        if (!empty($result['success'])) { $success++; }
    }
    if ($success < 1 || (int)$db->query('SELECT COUNT(*) FROM audit_trail')->fetchColumn() !== 1
        || $db->query('SELECT status FROM buses WHERE id=1')->fetchColumn() !== 'Archived'
        || $db->query('SELECT qr_token FROM buses WHERE id=1')->fetchColumn() !== 'original') {
        throw new RuntimeException('Concurrent archive duplicated history or changed original record.');
    }
    echo "Concurrent archive workers preserve one transition/event and the original bus/QR.\n";
} finally { $db = null; @unlink($file); }

<?php
declare(strict_types=1);

error_reporting(0);
ini_set('display_errors', '0');
ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

if (!Auth::check()) {
    send_json(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user = Auth::user();
if (!isset($user['role']) || !in_array(strtolower($user['role']), ['admin', 'super_admin'])) {
    send_json(['success' => false, 'message' => 'Forbidden'], 403);
}

function send_json($data, int $code = 200): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    if ($code !== 200) {
        http_response_code($code);
    }
    echo json_encode($data);
    exit;
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/driver-perf-metrics-core.php';

use App\Core\Database;

try {
    $db = Database::get();
    $driverId = trim((string)($_GET['driver_id'] ?? ''));
    if (!$driverId) {
        send_json(['success' => false, 'message' => 'Driver ID missing']);
    }
    [$start, $end, $period] = dpm_period_range($_GET);
    $data = dpm_compute($db, $driverId, $start, $end);
    if (isset($data['error'])) {
        send_json(['success' => false, 'message' => $data['error']]);
    }
    $data['period'] = $period;
    $data['range'] = ['start' => $start, 'end' => $end];
    send_json(['success' => true] + $data);
} catch (Throwable $e) {
    send_json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
}

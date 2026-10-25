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
require_once __DIR__ . '/driver-ai-core.php';

use App\Core\Database;

try {
    $db = Database::get();
    $driverId = trim((string)($_GET['driver_id'] ?? ''));
    if (!$driverId) {
        send_json(['success' => false, 'message' => 'Driver ID missing']);
    }
    [$start, $end, $period] = dpm_period_range($_GET);
    $result = dpm_build_analysis($db, $driverId, $start, $end, $period);
    if (isset($result['error'])) {
        send_json(['success' => false, 'message' => $result['error']]);
    }

    $actor = Auth::user() ?: [];
    $generatedBy = (string)($actor['username'] ?? $actor['name'] ?? 'admin');
    $generatedByRole = (string)($actor['role'] ?? '');
    $generatedAt = date('Y-m-d H:i:s');
    $periodLabel = function_exists('dpm_period_label') ? dpm_period_label($period, $start, $end) : ucfirst($period);
    $driverName = (string)($result['metrics']['name'] ?? $driverId);

    $systemName = 'R-Connect Transport Management System';
    try {
        $sysStmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'system_name' LIMIT 1");
        $sysVal = $sysStmt ? $sysStmt->fetchColumn() : null;
        if (is_string($sysVal) && trim($sysVal) !== '') {
            $systemName = trim($sysVal);
        }
    } catch (Throwable $e) {
        // keep default system name
    }

    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/app/services/AuditService.php';
        \AuditService::log(
            'Driver Performance',
            'AI Report Generated',
            "Generated AI performance report for {$driverName} ({$driverId}) | {$periodLabel}",
            $driverId,
            null,
            ['period' => $period, 'start' => $start, 'end' => $end, 'generated_by' => $generatedBy],
            'Success'
        );
    } catch (Throwable $e) {
        error_log('Audit log error (AI report): ' . $e->getMessage());
    }

    send_json([
        'success' => true,
        'analysis' => $result['analysis'],
        'metrics' => $result['metrics'],
        'period' => $period,
        'period_label' => $periodLabel,
        'range' => ['start' => $start, 'end' => $end],
        'generated_by' => $generatedBy,
        'generated_by_role' => $generatedByRole,
        'generated_at' => $generatedAt,
        'system' => $systemName,
    ]);
} catch (Throwable $e) {
    send_json(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
}

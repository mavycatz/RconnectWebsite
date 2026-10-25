<?php declare(strict_types=1);

/**
 * Generate Report endpoint.
 *
 * Validates and authorizes the request server-side, prevents duplicate
 * concurrent submissions for the same configuration, builds the filtered
 * report, stores the PDF under generated_reports/<category>/, and audits
 * the action.
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/reports/_library.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/reports/report_types.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/Controllers/GenerateReportController.php';

use Admin\Controllers\GenerateReportController;
use Finance\Models\AuditTrailModel;

date_default_timezone_set('Asia/Manila');

$user = rlib_guard();

function rgen_api_audit(string $action, string $description, array $user, array $extra = [], string $status = 'Success'): void
{
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';
        $audit = new AuditTrailModel();
        $audit->log(
            'Reports',
            $action,
            $description,
            isset($user['id']) ? (int)$user['id'] : null,
            $user['username'] ?? null,
            $user['role'] ?? null,
            null,
            $extra,
            $status
        );
    } catch (Throwable $ignore) {
    }
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST ?: $_GET;
}

$reportType = trim((string)($input['report_type'] ?? ''));
if ($reportType === '') {
    rlib_respond(['success' => false, 'message' => 'Report type is required.'], 400);
}

try {
    $normalized = rgen_normalize_filters($reportType, $input);
} catch (InvalidArgumentException $e) {
    rlib_respond(['success' => false, 'message' => $e->getMessage()], 400);
}

$lockKey = sha1(json_encode([$reportType, $normalized]));
$lockDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/report_locks';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0777, true);
}
$lockPath = $lockDir . '/report_' . $lockKey . '.lock';
$lockHandle = @fopen($lockPath, 'c');
$locked = $lockHandle !== false && @flock($lockHandle, LOCK_EX | LOCK_NB);

if (!$locked) {
    if ($lockHandle !== false) {
        @fclose($lockHandle);
    }
    rlib_respond([
        'success' => false,
        'message' => 'A report with these settings is already being generated. Please wait for it to finish.',
    ], 409);
}

$released = false;
$releaseLock = static function () use (&$released, $lockHandle): void {
    if (!$released && $lockHandle !== false) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
        $released = true;
    }
};

try {
    $controller = new GenerateReportController();
    $result = $controller->generate($reportType, $input, $user);

    rgen_api_audit(
        'Generate',
        'Generated report: ' . $result['title'] . ' (' . $result['record_count'] . ' records)',
        $user,
        [
            'report_type' => $reportType,
            'filters' => $result['applied_filters'],
            'file' => $result['pdf_url'],
        ]
    );

    $releaseLock();
    rlib_respond(['success' => true, 'data' => $result]);
} catch (InvalidArgumentException $e) {
    $releaseLock();
    rlib_respond(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('GenerateReportAPI: ' . $e->getMessage());
    rgen_api_audit('Generate', 'Failed to generate report: ' . $e->getMessage(), $user, ['report_type' => $reportType], 'Failed');
    $releaseLock();
    rlib_respond(['success' => false, 'message' => 'Report generation failed: ' . $e->getMessage()], 500);
}

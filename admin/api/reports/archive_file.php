<?php
declare(strict_types=1);

/**
 * Archive a single generated report file.
 *
 * Moves /generated_reports/<relative> to /archived_reports/<relative> without
 * deleting anything, then records the action in the audit trail. Restore is
 * handled by the existing RestoreArchivedReportAPI.
 */

date_default_timezone_set('Asia/Manila');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/auth/Session.php';
\App\Auth\Session::start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';

use Finance\Models\AuditTrailModel;

header('Content-Type: application/json');

$user = $_SESSION['user'] ?? null;
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
    exit;
}
if (!in_array(strtolower((string)($user['role'] ?? '')), ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $relative = trim((string)($input['file'] ?? ''));

    if ($relative === '' || strpos($relative, '..') !== false || strpos($relative, "\0") !== false) {
        throw new Exception('Invalid report file.');
    }
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    if (stripos($relative, 'generated_reports/') === 0 || stripos($relative, 'archived_reports/') === 0) {
        $relative = preg_replace('#^(generated|archived)_reports/#i', '', $relative);
    }

    $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'xlsx', 'xls', 'csv'], true)) {
        throw new Exception('Unsupported file type.');
    }

    $source = $_SERVER['DOCUMENT_ROOT'] . '/generated_reports/' . $relative;
    $target = $_SERVER['DOCUMENT_ROOT'] . '/archived_reports/' . $relative;

    if (!is_file($source)) {
        throw new Exception('Report file not found.');
    }

    $targetDir = dirname($target);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new Exception('Could not prepare the archive folder.');
    }

    if (!rename($source, $target)) {
        throw new Exception('Could not archive the report file.');
    }

    $audit = new AuditTrailModel();
    $audit->log(
        'Reports',
        'Archive',
        'Archived report: ' . basename($relative),
        $_SESSION['user']['id'] ?? null,
        $_SESSION['user']['username'] ?? null,
        $_SESSION['user']['role'] ?? null
    );

    echo json_encode(['success' => true, 'file' => $relative]);
} catch (Throwable $e) {
    try {
        $audit = new AuditTrailModel();
        $audit->log(
            'Reports',
            'Archive',
            'Failed to archive report: ' . $e->getMessage(),
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null,
            null,
            null,
            null,
            'Failed'
        );
    } catch (Throwable $ignore) {
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

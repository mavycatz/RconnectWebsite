<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/auth/Session.php';
\App\Auth\Session::start();

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/ArchiveReportsController.php';

use Admin\Controllers\ArchiveReportsController;
use Finance\Models\AuditTrailModel;

header('Content-Type: application/json');

try {

    $controller = new ArchiveReportsController();
    $response = $controller->archive();

    $audit = new AuditTrailModel();
    $audit->log('Reports', 'Archive', 'Reports archived',
        $_SESSION['user']['id'] ?? null,
        $_SESSION['user']['username'] ?? null,
        $_SESSION['user']['role'] ?? null
    );

    echo json_encode($response);
    exit;

} catch (Throwable $e) {

    try {
        $audit = new AuditTrailModel();
        $audit->log('Reports', 'Archive', 'Failed to archive reports: ' . $e->getMessage(),
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null,
            null, null, 'Failed'
        );
    } catch (Throwable $ignore) {}

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

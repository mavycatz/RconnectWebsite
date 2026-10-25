<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/auth/Session.php';
\App\Auth\Session::start();

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/Controllers/PrintLogsController.php';

use Admin\Controllers\PrintLogsController;
use Finance\Models\AuditTrailModel;

try {

    $generatePdf = (bool) ($_GET['generate_pdf'] ?? false);
    $period = trim($_GET['period'] ?? 'month');
    $controller = new PrintLogsController();
    $result = $controller->getLogs($generatePdf, $period);

    $audit = new AuditTrailModel();
    $audit->log('Reports', 'View', 'Viewed print logs',
        $_SESSION['user']['id'] ?? null,
        $_SESSION['user']['username'] ?? null,
        $_SESSION['user']['role'] ?? null
    );

    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;

} catch (Throwable $e) {

    try {
        $audit = new AuditTrailModel();
        $audit->log('Reports', 'View', 'Failed to view print logs: ' . $e->getMessage(),
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null,
            null, null, 'Failed'
        );
    } catch (Throwable $ignore) {}

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}
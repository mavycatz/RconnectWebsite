<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/auth/Session.php';
\App\Auth\Session::start();

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/ArchiveReportsModel.php';

use Admin\Models\ArchiveReportsModel;
use Finance\Models\AuditTrailModel;

header('Content-Type: application/json');

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$file = $data['file'] ?? '';

$model = new ArchiveReportsModel();
$result = $model->restoreFile($file);

$audit = new AuditTrailModel();
if ($result) {
    $audit->log('Reports', 'Restore', 'Restored archived report: ' . basename($file),
        $_SESSION['user']['id'] ?? null,
        $_SESSION['user']['username'] ?? null,
        $_SESSION['user']['role'] ?? null
    );
} else {
    $audit->log('Reports', 'Restore', 'Failed to restore archived report: ' . basename($file),
        $_SESSION['user']['id'] ?? null,
        $_SESSION['user']['username'] ?? null,
        $_SESSION['user']['role'] ?? null,
        null, null, 'Failed'
    );
}

echo json_encode([
    'success' => $result
]);

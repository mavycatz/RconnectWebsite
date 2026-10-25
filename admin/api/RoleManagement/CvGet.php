<?php declare(strict_types=1);

/**
 * Reads the stored CV profile for a subject (used to prefill the review UI).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/RoleManagement/CvProfileModel.php';

use Admin\Models\RoleManagement\CvProfileModel;

rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$subjectType = strtolower(trim((string)($_GET['subject_type'] ?? '')));
$subjectRef = trim((string)($_GET['subject_ref'] ?? ''));

if (!in_array($subjectType, CvProfileModel::allowedTypes(), true) || $subjectRef === '') {
    rmg_fail(400, 'A valid CV subject is required.');
}

try {
    $model = new CvProfileModel();
    $row = null;
    try {
        $row = $model->get($subjectType, $subjectRef);
    } catch (Throwable $e) {
        // Table not created yet: report "no CV data" instead of failing.
        $row = null;
    }
    echo json_encode(['success' => true, 'data' => $row]);
} catch (Throwable $e) {
    error_log('CvGet failed: ' . $e->getMessage());
    rmg_fail(500, 'Could not load the CV data.');
}

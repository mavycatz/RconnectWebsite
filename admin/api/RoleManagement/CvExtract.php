<?php declare(strict_types=1);

/**
 * CV/resume extraction endpoint.
 *
 * Accepts a fresh resume upload (multipart) or a previously stored path,
 * extracts the document text, and returns a draft education/experience/skills
 * structure for review. Never writes account data; failures degrade to a
 * warning so manual entry stays possible.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/UploadService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/CvExtractionService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/RoleManagement/CvProfileModel.php';

use Admin\Models\RoleManagement\CvProfileModel;
use Admin\Services\RoleManagement\CvExtractionService;
use Admin\Services\RoleManagement\UploadService;

$actor = rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (!function_exists('http_response_code') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        rmg_fail(405, 'POST is required for CV extraction.');
    }

    $subjectType = strtolower(trim((string)($_POST['subject_type'] ?? '')));
    $subjectRef = trim((string)($_POST['subject_ref'] ?? ''));

    if (!in_array($subjectType, CvProfileModel::allowedTypes(), true) || $subjectRef === '') {
        rmg_fail(400, 'A valid CV subject is required.');
    }

    $resumePath = '';
    $absolutePath = null;
    $extension = '';

    if (!empty($_FILES['resume']['name'])) {
        try {
            $resumePath = UploadService::store($_FILES['resume'], 'cv/' . $subjectType, 'cv');
        } catch (\RuntimeException $e) {
            rmg_fail(400, $e->getMessage());
        }
        $absolutePath = UploadService::resolvePath($resumePath);
        $extension = strtolower((string)pathinfo((string)$_FILES['resume']['name'], PATHINFO_EXTENSION));
    } elseif (!empty($_POST['resume_path'])) {
        $resumePath = UploadService::normalizePath((string)$_POST['resume_path']);
        $absolutePath = UploadService::resolvePath($resumePath);
        $extension = strtolower((string)pathinfo($resumePath, PATHINFO_EXTENSION));
    } else {
        rmg_fail(400, 'Choose a PDF, DOC, or DOCX file first.');
    }

    if ($absolutePath === null) {
        rmg_fail(400, 'The uploaded file could not be located.');
    }

    $service = new CvExtractionService();
    $result = $service->run($absolutePath, $extension);

    rmg_audit(
        'CV Extract',
        'Extracted CV text for ' . $subjectType . ' #' . $subjectRef,
        $actor,
        ['file' => $resumePath, 'warning' => $result['warning']],
        $result['warning'] === null ? 'Success' : 'Partial',
        $subjectRef
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'resume_path' => $resumePath,
            'resume_text' => mb_substr($result['text'], 0, 40000),
            'education' => $result['education'],
            'experience' => $result['experience'],
            'skills' => $result['skills'],
            'warning' => $result['warning'],
        ],
    ]);
} catch (Throwable $e) {
    error_log('CvExtract failed: ' . $e->getMessage());
    rmg_fail(500, 'CV extraction failed. You can still enter the details manually.');
}

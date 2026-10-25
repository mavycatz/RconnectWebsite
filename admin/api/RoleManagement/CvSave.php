<?php declare(strict_types=1);

/**
 * Saves reviewed CV data for a subject and updates the account's resume path.
 * Admin/super_admin only; audited.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/RoleManagement/_auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/RoleManagement/CvProfileModel.php';

use Admin\Models\RoleManagement\CvProfileModel;

$actor = rmg_actor();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rmg_fail(405, 'POST is required to save CV data.');
    }

    $input = rmg_input();
    $subjectType = strtolower(trim((string)($input['subject_type'] ?? '')));
    $subjectRef = trim((string)($input['subject_ref'] ?? ''));

    if (!in_array($subjectType, CvProfileModel::allowedTypes(), true) || $subjectRef === '') {
        rmg_fail(400, 'A valid CV subject is required.');
    }

    require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
    $db = \App\Core\Database::get();

    // Verify the source account exists before storing anything.
    $exists = false;
    switch ($subjectType) {
        case 'driver':
            $stmt = $db->prepare('SELECT id FROM drivers WHERE id = ? LIMIT 1');
            $stmt->execute([$subjectRef]);
            $exists = (bool)$stmt->fetchColumn();
            break;
        case 'inspector':
        case 'dispatcher':
            $stmt = $db->prepare('SELECT inspector_pk FROM inspectors WHERE inspector_pk = ? LIMIT 1');
            $stmt->execute([$subjectRef]);
            $exists = (bool)$stmt->fetchColumn();
            break;
        case 'finance':
            $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND LOWER(role) IN ('finance_officer','finance_manager') LIMIT 1");
            $stmt->execute([$subjectRef]);
            $exists = (bool)$stmt->fetchColumn();
            break;
        case 'admin':
            $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND LOWER(role) IN ('admin','super_admin') LIMIT 1");
            $stmt->execute([$subjectRef]);
            $exists = (bool)$stmt->fetchColumn();
            break;
    }
    if (!$exists) {
        rmg_fail(404, 'The selected account no longer exists.');
    }

    $model = new CvProfileModel();
    $model->ensureTable();

    $actorName = (string)($actor['user']['username'] ?? $actor['user']['name'] ?? 'admin');
    $row = $model->save($subjectType, $subjectRef, [
        'resume_file' => (string)($input['resume_file'] ?? ''),
        'resume_text' => (string)($input['resume_text'] ?? ''),
        'education' => $input['education'] ?? [],
        'experience' => $input['experience'] ?? [],
        'skills' => $input['skills'] ?? [],
    ], $actorName);

    // Persist the resume path to the role's existing resume column.
    $resumePath = trim((string)($input['resume_file'] ?? ''));
    if ($resumePath !== '') {
        $normalized = '/' . ltrim(str_replace('\\', '/', $resumePath), '/');
        try {
            switch ($subjectType) {
                case 'driver':
                    $stmt = $db->prepare('UPDATE drivers SET resume_file = ? WHERE id = ?');
                    $stmt->execute([$normalized, $subjectRef]);
                    break;
                case 'inspector':
                case 'dispatcher':
                    $stmt = $db->prepare('UPDATE inspectors SET resume_file = ? WHERE inspector_pk = ?');
                    $stmt->execute([$normalized, $subjectRef]);
                    break;
                case 'finance':
                case 'admin':
                    $stmt = $db->prepare('UPDATE users SET resume_file = ? WHERE id = ?');
                    $stmt->execute([$normalized, $subjectRef]);
                    break;
            }
        } catch (Throwable $e) {
            error_log('CvSave resume path update failed: ' . $e->getMessage());
        }
    }

    rmg_audit(
        'CV Save',
        'Saved CV data for ' . $subjectType . ' #' . $subjectRef,
        $actor,
        ['file' => $resumePath, 'education' => count($row['education'] ?? []), 'experience' => count($row['experience'] ?? []), 'skills' => count($row['skills'] ?? [])],
        'Success',
        $subjectRef
    );

    echo json_encode(['success' => true, 'data' => $row]);
} catch (InvalidArgumentException $e) {
    rmg_fail(400, $e->getMessage());
} catch (Throwable $e) {
    error_log('CvSave failed: ' . $e->getMessage());
    rmg_fail(500, 'Could not save the CV data.');
}

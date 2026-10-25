<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/Controllers/Announcement/AnnouncementController.php';

use App\Auth\Auth;
use App\Auth\Session;
use Admin\Controllers\Announcement\AnnouncementController;

header('Content-Type: application/json');

Session::start();

/* ---- Auth check ---- */
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated.']);
    exit;
}

$user = Auth::user();
if (!in_array($user['role'] ?? null, ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

try {

    $id          = (int)($_POST['id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $message     = trim($_POST['message'] ?? '');
    $visibility  = strtolower(trim((string)($_POST['visibility'] ?? 'public')));
    $targetRoles = $_POST['target_roles'] ?? [];
    $removeImage = !empty($_POST['remove_image']);

    /* ---- Validate ---- */
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid announcement ID.']);
        exit;
    }
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Title is required.']);
        exit;
    }
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Announcement content is required.']);
        exit;
    }

    /* ---- Validate audience before uploading the image ---- */
    try {
        [$visibility, $targetRoles] = AnnouncementController::normalizeAudience($visibility, $targetRoles);
    } catch (\InvalidArgumentException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    /* ---- Image upload (same rules as save.php) ---- */
    $imagePath = null;

    if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image type. Allowed: JPG, PNG, GIF, WebP.']);
            exit;
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg',
        };

        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/announcements/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'announcement_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
            exit;
        }

        $imagePath = '/uploads/announcements/' . $fileName;
    }

    /* ---- Update ---- */
    $controller = new AnnouncementController();
    $result     = $controller->update($id, [
        'title'        => $title,
        'message'      => $message,
        'visibility'   => $visibility,
        'target_roles' => $targetRoles,
        'image_path'   => $imagePath,
        'remove_image' => $removeImage,
    ]);

    if (empty($result['success'])) {
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to update.']);
        exit;
    }

    echo json_encode([
        'success'         => true,
        'announcement_id' => $result['announcement_id'],
        'image_path'      => $result['image_path'],
        'message'         => 'Announcement updated successfully.',
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

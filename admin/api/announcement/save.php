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

    $title       = trim($_POST['title'] ?? '');
    $message     = trim($_POST['message'] ?? '');
    $postToFb    = !empty($_POST['post_to_facebook']);
    /* Publish timestamp is generated server-side. */
    $publishDate = date('Y-m-d H:i:s');
    $visibility  = strtolower(trim((string)($_POST['visibility'] ?? 'public')));
    $targetRoles = $_POST['target_roles'] ?? [];

    /* ---- Validate ---- */
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

    /* ---- Image upload ---- */
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

        $ext      = match ($mime) {
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

        $fileName  = 'announcement_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath  = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
            exit;
        }

        $imagePath = '/uploads/announcements/' . $fileName;
    }

    /* ---- Save ---- */
    $controller = new AnnouncementController();
    $result     = $controller->save([
        'title'         => $title,
        'message'       => $message,
        'image_path'    => $imagePath,
        'publish_date'  => $publishDate,
        'post_to_fb'    => $postToFb,
        'visibility'    => $visibility,
        'target_roles'  => $targetRoles,
    ]);

    $fbErrorType = $result['facebook_error_type'] ?? null;
    $friendlyMsg = $result['facebook_error'];
    if ($fbErrorType === 'token_expired') {
        $friendlyMsg = 'Your Facebook access token has expired. Go to Announcement Settings to update it.';
    }

    echo json_encode([
        'success'             => true,
        'announcement_id'     => $result['announcement_id'],
        'facebook_post_id'    => $result['facebook_post_id'],
        'facebook_error'      => $result['facebook_error'],
        'facebook_error_type' => $fbErrorType,
        'facebook_error_code'    => $result['facebook_error_code'],
        'facebook_error_subcode' => $result['facebook_error_subcode'],
        'message'             => $result['facebook_error']
            ? "Announcement saved. Facebook posting failed: {$friendlyMsg}"
            : ($postToFb ? 'Announcement published successfully. Facebook post created.' : 'Announcement published successfully.'),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

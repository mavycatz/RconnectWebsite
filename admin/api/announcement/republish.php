<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/Controllers/Announcement/AnnouncementController.php';

use App\Auth\Auth;
use App\Auth\Session;
use Admin\Controllers\Announcement\AnnouncementController;

header('Content-Type: application/json');

Session::start();

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

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit;
    }

    $controller = new AnnouncementController();
    $fbResult   = $controller->repostToFacebook($id);

    $fbErrorType = $fbResult['error_type'] ?? null;
    $friendlyMsg = $fbResult['error'] ?? '';
    if ($fbErrorType === 'token_expired') {
        $friendlyMsg = 'Your Facebook access token has expired. Go to Announcement Settings to update it.';
    }

    echo json_encode([
        'success'          => $fbResult['success'],
        'facebook_post_id' => $fbResult['facebook_post_id'] ?? null,
        'facebook_error'   => $fbResult['error'] ?? null,
        'facebook_error_type' => $fbErrorType,
        'facebook_error_code'    => $fbResult['error_code'] ?? null,
        'facebook_error_subcode' => $fbResult['error_subcode'] ?? null,
        'message'          => $fbResult['success']
            ? 'Facebook post created successfully.'
            : "Facebook posting failed: {$friendlyMsg}",
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

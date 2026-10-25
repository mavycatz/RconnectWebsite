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

    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        exit;
    }

    $controller = new AnnouncementController();

    if ($controller->delete($id)) {
        echo json_encode(['success' => true, 'message' => 'Announcement deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Announcement not found.']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

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

try {

    $controller = new AnnouncementController();
    $list       = $controller->list();

    echo json_encode([
        'success' => true,
        'data'    => $list,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

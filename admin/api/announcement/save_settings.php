<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Auth\Auth;
use App\Auth\Session;
use App\Core\Database;

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

    $pageId  = trim($_POST['facebook_page_id'] ?? '');
    $token   = trim($_POST['facebook_page_access_token'] ?? '');

    $db = Database::get();

    $upsert = function (string $key, string $value) use ($db): void {
        $s = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $s->execute([$key, $value]);
    };

    $upsert('facebook_page_id', $pageId);
    $upsert('facebook_page_access_token', $token);

    echo json_encode(['success' => true, 'message' => 'Facebook settings saved.']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

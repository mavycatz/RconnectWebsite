<?php
require_once __DIR__ . '/../app/Core/Bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

use App\Auth\Auth;
use App\Models\Notification;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$notificationModel = new Notification();
$result = $notificationModel->getToday(Auth::userId());

$items = array_map(function ($n) {
    return [
        'id' => (int) $n['id'],
        'title' => $n['title'],
        'description' => $n['description'],
        'type' => $n['type'],
        'reference_type' => $n['reference_type'] ?? null,
        'reference_id' => isset($n['reference_id']) ? (int) $n['reference_id'] : null,
        'created_at' => $n['created_at'],
        'is_read' => (bool) $n['is_read'],
    ];
}, $result['items']);

echo json_encode([
    'success' => true,
    'count' => $result['unread'],
    'data' => $items,
]);

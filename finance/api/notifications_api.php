<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once __DIR__ . '/../middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/../models/AuditTrailModel.php';

use App\Core\Database;
use Finance\Models\AuditTrailModel;

setCorsHeaders();

$currentUserId = null;
$currentUsername = 'System';
$currentUserRole = '';

try {
    $sid = session_id();
    if ($sid) {
        $st = Database::get()->prepare("SELECT user_id FROM sessions WHERE id = ?");
        $st->execute([$sid]);
        $row = $st->fetch();
        if ($row && $row['user_id']) {
            $us = Database::get()->prepare("SELECT id, username, role FROM users WHERE id = ?");
            $us->execute([$row['user_id']]);
            $u = $us->fetch();
            if ($u) {
                $currentUserId = (int)$u['id'];
                $currentUsername = $u['username'] ?? 'User';
                $currentUserRole = $u['role'] ?? '';
            }
        }
    }
} catch (\Throwable $e) {}

if (empty($currentUserRole)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}

$auditModel = new AuditTrailModel();
$auditModel->initTables();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $auditModel->getRecentUnread($currentUserRole, 20);

    $items = array_map(function ($r) {
        $actionLabel = ucfirst(strtolower($r['action']));
        $type = $r['status'] === 'Success' ? 'success'
              : ($r['status'] === 'Failed' ? 'error' : 'info');

        return [
            'id' => (int) $r['id'],
            'title' => $r['module'] . ': ' . $actionLabel,
            'description' => $r['description'] ?? '',
            'type' => $type,
            'created_at' => $r['created_at'],
            'is_read' => (bool) $r['is_read'],
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'count' => count($items),
        'data' => $items,
    ]);
    exit;
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $auditModel->markAllRead($currentUserRole);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);

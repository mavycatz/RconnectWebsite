<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/services/AuditService.php';

use App\Auth\Session;

header('Content-Type: application/json');

Session::start();

if (!\App\Auth\Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$module = trim($_POST['module'] ?? '');
$action = trim($_POST['action'] ?? '');
$description = trim($_POST['description'] ?? '');

if (empty($module) || empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'module and action are required']);
    exit;
}

try {
    AuditService::log($module, $action, $description);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

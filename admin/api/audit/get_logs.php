<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/models/AuditTrailModel.php';

use App\Auth\Session;
use Finance\Models\AuditTrailModel;

header('Content-Type: application/json');

Session::start();

if (!\App\Auth\Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}

try {
    $model = new AuditTrailModel();

    $result = $model->getTrails(
        search: $_GET['search'] ?? null,
        module: $_GET['module'] ?? null,
        action: $_GET['action'] ?? null,
        username: $_GET['username'] ?? null,
        userRole: $_GET['user_role'] ?? null,
        status: $_GET['status'] ?? null,
        dateFrom: $_GET['date_from'] ?? null,
        dateTo: $_GET['date_to'] ?? null,
        sort: $_GET['sort'] ?? 'DESC',
        page: (int)($_GET['page'] ?? 1),
        perPage: (int)($_GET['per_page'] ?? 50)
    );

    echo json_encode(['success' => true, 'data' => $result]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

<?php

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Bootstrap.php';

use App\Auth\Session;
use App\Auth\Auth;

Session::start();

function dashboard_json(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json');
    echo json_encode($payload);
}

function dashboard_deny(int $status): void
{
    http_response_code($status);
    dashboard_json(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!Auth::check()) {
    dashboard_deny(403);
}

$dashboardUser = Auth::user() ?? [];
$dashboardRole = strtolower(trim((string)($dashboardUser['role'] ?? $_SESSION['role'] ?? '')));

if (!in_array($dashboardRole, ['admin', 'super_admin'], true)) {
    dashboard_deny(403);
}

function dashboard_request_filters(): array
{
    $filters = [];

    foreach (['bus', 'route', 'driver', 'category', 'day', 'month', 'year', 'fromDate', 'toDate', 'fromTime', 'toTime'] as $key) {
        $value = $_GET[$key] ?? null;

        if (is_string($value) && trim($value) !== '') {
            $filters[$key] = trim($value);
        }
    }

    return $filters;
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/DashboardModel.php';

use App\Core\Database;
use Admin\Models\DashboardModel;

try {
    $filters = dashboard_request_filters();
    $model = new DashboardModel(Database::get());
    $attention = $model->needsAttention($filters);

    dashboard_json([
        'success' => true,
        'total'   => (int)($attention['total'] ?? 0),
        'items'   => $attention['items'] ?? [],
    ]);
} catch (\Throwable $e) {
    dashboard_json([
        'success' => false,
        'message' => 'Unable to load attention items.',
    ]);
}

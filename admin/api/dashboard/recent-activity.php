<?php

declare(strict_types=1);

require_once __DIR__ . '/context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/DashboardModel.php';

use App\Core\Database;
use Admin\Models\DashboardModel;

try {
    $filters = dashboard_request_filters();
    $model = new DashboardModel(Database::get());
    $entries = $model->recentSystemActivity(8, $filters);

    dashboard_json([
        'success' => true,
        'entries' => $entries,
    ]);
} catch (\Throwable $e) {
    dashboard_json([
        'success' => false,
        'message' => 'Unable to load recent activity.',
    ]);
}

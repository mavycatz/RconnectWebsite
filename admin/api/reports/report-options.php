<?php declare(strict_types=1);

/**
 * Filter option lists for the Generate Report configuration: drivers, buses,
 * routes, and the status values that actually exist per report type.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/reports/_library.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/reports/report_types.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/GenerateReportModel.php';

use Admin\Models\GenerateReportModel;

rlib_guard();

try {
    $model = new GenerateReportModel();
    $statuses = [];

    foreach (rgen_registry() as $type => $def) {
        if (!empty($def['status_options'])) {
            $statuses[$type] = array_values($def['status_options']);
        } elseif (!empty($def['status_source']['table']) && !empty($def['status_source']['column'])) {
            $statuses[$type] = $model->distinctValues(
                (string)$def['status_source']['table'],
                (string)$def['status_source']['column']
            );
        }
    }

    rlib_respond([
        'success' => true,
        'drivers' => $model->driverOptions(),
        'buses' => $model->busOptions(),
        'routes' => $model->distinctValues('route_stops', 'route_name'),
        'statuses' => $statuses,
    ]);
} catch (Throwable $e) {
    error_log('report-options: ' . $e->getMessage());
    rlib_respond(['success' => false, 'message' => 'Could not load filter options.'], 500);
}

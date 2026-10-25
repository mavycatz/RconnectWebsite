<?php declare(strict_types=1);

/**
 * Generate Report preview endpoint.
 *
 * Returns the filtered data, computed summary, applied filters, and a bounded
 * row set for the selected report type. It never creates a report file.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/reports/_library.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/api/reports/report_builder.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/ReportCenterPresentationService.php';

$user = rlib_guard();

function rgen_preview_input(): array
{
    $raw = file_get_contents('php://input');
    $json = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($json)) {
        return $json;
    }
    if (!empty($_POST)) {
        return $_POST;
    }
    return $_GET;
}

const RGEN_PREVIEW_LIMIT = 100;

try {
    $input = rgen_preview_input();
    $type = trim((string)($input['report_type'] ?? ''));
    if ($type === '') {
        rlib_respond(['success' => false, 'message' => 'Report type is required.'], 400);
    }

    $report = rgen_build_report($type, $input, $user, true);

    $report = \Admin\Services\ReportCenterPresentationService::preview(
        \Admin\Services\ReportCenterPresentationService::prepare($report), RGEN_PREVIEW_LIMIT
    );

    rlib_respond(['success' => true, 'data' => $report]);
} catch (InvalidArgumentException $e) {
    rlib_respond(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log('GenerateReportPreviewAPI: ' . $e->getMessage());
    rlib_respond(['success' => false, 'message' => 'Could not build the preview: ' . $e->getMessage()], 500);
}

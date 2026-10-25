<?php
declare(strict_types=1);

// Reporting APIs must return JSON even if application initialization exits.
ob_start();
ini_set('display_errors', '0');
set_time_limit(120);
ini_set('memory_limit', '512M');
require_once __DIR__ . '/../services/ReportingFailureService.php';
$financeReportingInitialized = false;
$financeReportingEmergencyMemory = str_repeat(' ', 65536);
function financeReportingError(string $message, int $status = 503, ?string $code = null): void
{
    while (ob_get_level()) ob_end_clean();
    http_response_code($status);
    header_remove('Location');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success' => false, 'message' => $message, 'error_code' => $code], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
register_shutdown_function(static function () use (&$financeReportingInitialized, &$financeReportingEmergencyMemory): void {
    $financeReportingEmergencyMemory = null;
    $error = error_get_last();
    $failure = \Finance\Services\ReportingFailureService::classify($financeReportingInitialized, $error, headers_list());
    if ($failure !== null) {
        if ($failure['status'] === 401 || $failure['code'] === 'MAINTENANCE_MODE') {
            financeReportingError($failure['message'], $failure['status'], $failure['code']);
        }
        $reference = 'FIN-' . bin2hex(random_bytes(4));
        $detail = json_encode(['reference' => $reference, 'code' => $failure['code'], 'action' => $_GET['action'] ?? $_POST['action'] ?? '', 'error' => $error, 'time' => date('c')], JSON_INVALID_UTF8_SUBSTITUTE);
        error_log('Finance reporting: ' . $detail);
        $logDirectory = __DIR__ . '/../../storage/logs';
        if (is_dir($logDirectory) && is_writable($logDirectory)) error_log($detail . PHP_EOL, 3, $logDirectory . '/finance_reporting.log');
        financeReportingError($failure['message'] . ' Reference: ' . $reference . '.', $failure['status'], $failure['code']);
    }
});
try {
    require_once __DIR__ . '/../../app/Core/Database.php';
    if (\App\Core\Database::tryGet() === null) {
        $financeReportingInitialized = true;
        financeReportingError('The Finance database is unavailable. Please retry or contact Finance support to check the database connection.');
    }
    require_once __DIR__ . '/../../app/init.php';
    $financeReportingInitialized = true;
    ini_set('display_errors', '0');
    ini_set('display_errors', '0');
    // Remove notices emitted by the application's initialization chain.
    ob_clean();
} catch (Throwable $e) {
    $financeReportingInitialized = true;
    error_log('Finance reporting bootstrap: ' . $e->getMessage());
    financeReportingError('The reporting service could not initialize. Please contact Finance support.', 500);
}

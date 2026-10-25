<?php
declare(strict_types=1);
namespace Finance\Services;

final class ReportingFailureService
{
    public static function classify(bool $initialized, ?array $error, array $headers): ?array
    {
        $fatal = $error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
        if ($fatal) {
            $detail = (string) ($error['message'] ?? '');
            if (stripos($detail, 'Maximum execution time') !== false) {
                return ['status' => 504, 'code' => 'REPORTING_TIMEOUT', 'message' => 'The report exceeded the server time limit. Try a shorter reporting period.'];
            }
            if (stripos($detail, 'Allowed memory size') !== false) {
                return ['status' => 503, 'code' => 'REPORTING_MEMORY_LIMIT', 'message' => 'The selected report exceeds the server memory limit. Try a shorter reporting period.'];
            }
            return ['status' => 500, 'code' => 'REPORTING_PHP_ERROR', 'message' => 'A PHP error prevented this report from completing.'];
        }
        if ($initialized) return null;
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') !== 0) continue;
            if (stripos($header, 'maintenance') !== false) {
                return ['status' => 503, 'code' => 'MAINTENANCE_MODE', 'message' => 'The system is in maintenance mode. Please try again later.'];
            }
            return ['status' => 401, 'code' => 'FINANCE_SESSION_EXPIRED', 'message' => 'Your Finance session has expired. Sign in again, then retry the report.'];
        }
        return ['status' => 500, 'code' => 'REPORTING_STARTUP_EXIT', 'message' => 'Application startup stopped before the reporting service was ready.'];
    }
}

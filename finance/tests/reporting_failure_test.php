<?php
declare(strict_types=1);
require_once __DIR__ . '/../services/ReportingFailureService.php';
use Finance\Services\ReportingFailureService;

$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    $checks++;
};
$expired = ReportingFailureService::classify(false, null, ['Location: /portal-key?portal=finance']);
$check($expired['status'] === 401 && $expired['code'] === 'FINANCE_SESSION_EXPIRED', 'Session redirect becomes HTTP 401, not a generic server failure');
$warning = ['type' => E_WARNING, 'message' => 'Prior harmless notice'];
$check(ReportingFailureService::classify(false, $warning, ['Location: /public/login/index.php'])['status'] === 401, 'Earlier notices do not conceal login redirects');
$check(ReportingFailureService::classify(false, null, ['Location: /admin/views/maintenance.php'])['code'] === 'MAINTENANCE_MODE', 'Maintenance remains a distinct unavailable state');
$timeout = ['type' => E_ERROR, 'message' => 'Maximum execution time of 30 seconds exceeded'];
$check(ReportingFailureService::classify(true, $timeout, [])['status'] === 504, 'PHP timeout is distinct from other runtime errors');
$memory = ['type' => E_ERROR, 'message' => 'Allowed memory size of 134217728 bytes exhausted'];
$check(ReportingFailureService::classify(true, $memory, [])['code'] === 'REPORTING_MEMORY_LIMIT', 'Memory exhaustion is actionable');
$check(ReportingFailureService::classify(true, ['type' => E_ERROR, 'message' => 'Missing dependency'], [])['code'] === 'REPORTING_PHP_ERROR', 'Dependency fatal errors remain identifiable');
$check(ReportingFailureService::classify(true, $warning, []) === null, 'Successful requests are not overwritten by an old warning');
$check(ReportingFailureService::classify(false, null, [])['code'] === 'REPORTING_STARTUP_EXIT', 'Interrupted initialization is distinguished from timeout');
echo "$checks reporting failure checks passed.\n";

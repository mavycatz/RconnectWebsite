<?php

declare(strict_types=1);

ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/../../services/RevenueOverviewService.php';

use App\Core\Database;
use App\Auth\Auth;
use Finance\Services\RevenueOverviewService;

validateFinanceRequest('standard', false);
setRequestTimeout(30);

function ro_json(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

if (!Auth::check()) {
    ro_json(['success' => false, 'message' => 'Unauthenticated'], 401);
    exit;
}

$roRole = strtolower(trim((string) Auth::role()));
if (!in_array($roRole, ['finance_manager', 'finance_officer', 'admin', 'super_admin'], true)) {
    ro_json(['success' => false, 'message' => 'Forbidden'], 403);
    exit;
}

$roAllowedSections = ['kpis', 'drivers', 'route', 'location', 'time'];
$roSection = strtolower(trim((string) ($_GET['section'] ?? 'kpis')));
if (!in_array($roSection, $roAllowedSections, true)) {
    ro_json(['success' => false, 'message' => 'Unknown section.'], 400);
    exit;
}

try {
    $db = Database::tryGet();
    if ($db === null) {
        throw new \RuntimeException('Database connection unavailable.');
    }

    $service = new RevenueOverviewService($db);

    try {
        $service->ensureIndexes();
    } catch (\Throwable $e) {
    }

    $filters = [];
    foreach (['period', 'from', 'to', 'bus', 'route'] as $key) {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $filters[$key] = (string) $_GET[$key];
        }
    }

    $period = $service->resolvePeriod($filters);

    try {
        $data = $service->getSection($roSection, $filters, $period);
    } catch (\Throwable $e) {
        $data = ['available' => false];
    }

    ro_json([
        'success' => true,
        'section' => $roSection,
        'period' => $period['period'],
        'summary' => \Finance\Services\DateFilterService::summary(
            (string) $period['period'],
            (string) $period['from'],
            (string) $period['to']
        ),
        'range' => [
            'from' => $period['from'],
            'to' => $period['to'],
            'previous_from' => $period['previous_from'],
            'previous_to' => $period['previous_to'],
        ],
        'data' => $data,
    ]);
} catch (\Throwable $e) {
    ro_json(['success' => false, 'message' => $e->getMessage()], 500);
}

<?php

declare(strict_types=1);

ob_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/middleware/validate_request.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/Core/Database.php';
require_once __DIR__ . '/../../services/FinancialIntelligenceService.php';

use App\Auth\Auth;
use App\Core\Database;
use Finance\Services\FinancialIntelligenceService;

validateFinanceRequest('standard', false);
setRequestTimeout(60);

function fi_json(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!Auth::check()) {
    fi_json(['success' => false, 'message' => 'Unauthenticated'], 401);
}

$role = strtolower(trim((string) Auth::role()));
if (!in_array($role, ['finance_manager', 'finance_officer'], true)) {
    fi_json(['success' => false, 'message' => 'Forbidden'], 403);
}

$section = strtolower(trim((string) ($_GET['section'] ?? 'overview')));
if (!in_array($section, FinancialIntelligenceService::sections(), true)) {
    fi_json(['success' => false, 'message' => 'Unknown financial intelligence section.'], 400);
}

try {
    $db = Database::tryGet();
    if ($db === null) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $service = new FinancialIntelligenceService($db);

    if ($section === 'options') {
        fi_json(['success' => true, 'section' => 'options', 'data' => $service->options()]);
    }

    try {
        $filters = FinancialIntelligenceService::resolveFilters($_GET);
    } catch (InvalidArgumentException $e) {
        fi_json(['success' => false, 'message' => $e->getMessage()], 422);
    }

    $params = [];
    foreach (['horizon', 'fare_change_pct', 'volume_change_pct', 'fuel_price_change_pct', 'other_expense_change_pct'] as $key) {
        if (isset($_GET[$key]) && $_GET[$key] !== '') {
            $params[$key] = (string) $_GET[$key];
        }
    }
    if (isset($_GET['route_fare_changes']) && $_GET['route_fare_changes'] !== '') {
        $decoded = json_decode((string) $_GET['route_fare_changes'], true);
        if (is_array($decoded)) {
            $changes = [];
            foreach ($decoded as $route => $pct) {
                if (is_string($route) && $route !== '' && is_numeric($pct)) {
                    $changes[$route] = (float) $pct;
                }
            }
            $params['route_fare_changes'] = $changes;
        }
    }

    $data = $service->section($section, $filters, $params);

    fi_json([
        'success' => true,
        'section' => $section,
        'period' => [
            'period' => $filters['period'],
            'label' => $filters['label'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'previous_from' => $filters['previous_from'],
            'previous_to' => $filters['previous_to'],
            'route' => $filters['route'],
            'bus' => $filters['bus'],
            'passenger_type' => $filters['passenger_type'],
        ],
        'data' => $data,
    ]);
} catch (InvalidArgumentException $e) {
    fi_json(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('financial_intelligence endpoint failed: ' . $e->getMessage());
    fi_json(['success' => false, 'message' => 'Unable to compute financial intelligence right now. Please retry.'], 500);
}

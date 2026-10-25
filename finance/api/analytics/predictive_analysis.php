<?php

declare(strict_types=1);

/*
 | Finance Predictive Analysis endpoint.
 |
 | Computes passenger demand, peak demand period and fuel requirement
 | forecasts from stored R-Connect data for the finance portal. Exposes no
 | data to non-finance roles and never returns placeholder predictions:
 | thin history yields data_quality.insufficient = true instead.
 */

require_once __DIR__ . '/../_reporting_bootstrap.php';
require_once __DIR__ . '/../../middleware/validate_request.php';
require_once __DIR__ . '/../../services/PredictiveAnalysisService.php';

use App\Auth\Auth;
use App\Core\Database;
use Finance\Services\PredictiveAnalysisService;

validateFinanceRequest('standard', false);
setRequestTimeout(60);

if (!function_exists('fpa_send_json')) {
    function fpa_send_json(array $data, int $code = 200): void
    {
        while (ob_get_level()) ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        if ($code !== 200) {
            http_response_code($code);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        exit;
    }
}

/* ---- Role gate: Finance Manager / Finance Officer only ---- */
if (!Auth::check()) {
    fpa_send_json(['success' => false, 'message' => 'Unauthenticated'], 401);
}

$role = strtolower(trim((string) Auth::role()));
if (!in_array($role, ['finance_manager', 'finance_officer'], true)) {
    fpa_send_json(['success' => false, 'message' => 'Forbidden'], 403);
}
// Forecast calculations are read-only; do not block other Finance requests.
session_write_close();

try {
    if (Database::tryGet() === null) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $service = new PredictiveAnalysisService();
    $payload = $service->build([
        'period' => $_GET['period'] ?? '',
        'horizon' => $_GET['horizon'] ?? '',
        'from' => $_GET['from'] ?? null,
        'to' => $_GET['to'] ?? null,
        'route' => $_GET['route'] ?? '',
        'bus' => $_GET['bus'] ?? '',
        'time_period' => $_GET['time_period'] ?? 'all',
    ]);

    fpa_send_json($payload);
} catch (\InvalidArgumentException $e) {
    fpa_send_json(['success' => false, 'message' => $e->getMessage()], 422);
} catch (\Throwable $e) {
    error_log('predictive_analysis endpoint failed: ' . $e->getMessage());
    fpa_send_json(['success' => false, 'message' => 'Unable to compute predictions right now. Please retry or contact Finance support.'], 500);
}

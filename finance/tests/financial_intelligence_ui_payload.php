<?php
declare(strict_types=1);

/**
 * Dumps real FinancialIntelligenceService payloads for the UI test.
 * CLI helper used by financial_intelligence_ui_test.cjs.
 */
require_once __DIR__ . '/FinancialIntelligenceFixture.php';
require_once __DIR__ . '/../services/FinancialIntelligenceService.php';

use Finance\Services\FinancialIntelligenceService;

date_default_timezone_set('Asia/Manila');

$db = FinancialIntelligenceFixture::pdo();
$service = new FinancialIntelligenceService($db);
$filters = FinancialIntelligenceService::resolveFilters(['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31']);
$filters['label'] = 'October 2026';

$predictive = [
    'summary' => [
        'passengers' => ['forecast_total' => 70, 'range_low' => 50, 'range_high' => 90, 'period_label' => 'Next 7 days'],
    ],
    'fuel' => ['available' => true, 'estimated_liters' => 100.0, 'avg_price' => 15.0],
    'model' => ['name' => 'Holt Linear', 'computed_at' => '2026-10-13 08:00:00', 'history_from' => '2026-08-01', 'history_to' => '2026-10-12', 'reliability' => ['label' => 'Good']],
    'forecast' => [
        'history' => [
            ['date' => '2026-10-10', 'passengers' => 60, 'trips' => 4],
            ['date' => '2026-10-11', 'passengers' => 62, 'trips' => 4],
            ['date' => '2026-10-12', 'passengers' => 64, 'trips' => 4],
        ],
        'points' => [
            ['date' => '2026-10-13', 'value' => 66, 'lower' => 55, 'upper' => 78],
            ['date' => '2026-10-14', 'value' => 68, 'lower' => 56, 'upper' => 80],
        ],
    ],
];

echo json_encode([
    'overview' => $service->overview($filters),
    'passengers' => $service->passengers($filters),
    'fare' => $service->fare($filters),
    'fuel' => $service->fuel($filters),
    'fleet' => $service->fleet($filters),
    'remittance' => $service->remittance($filters),
    'forecast' => $service->composeForecast($filters, '7d', $predictive),
    'whatif' => $service->whatIf($filters, [
        'fare_change_pct' => 10,
        'volume_change_pct' => 5,
        'fuel_price_change_pct' => 20,
        'other_expense_change_pct' => -10,
    ]),
    'whatif_route' => $service->whatIf($filters, [
        'route_fare_changes' => ['Cabanatuan-Rizal' => 20],
    ]),
], JSON_UNESCAPED_UNICODE);

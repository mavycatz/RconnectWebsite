<?php
declare(strict_types=1);
require_once __DIR__ . '/../services/PredictiveAnalysisService.php';

use Finance\Models\PredictiveAnalysisModel;
use Finance\Services\PredictiveAnalysisService;

date_default_timezone_set('Asia/Manila');
$model = new class extends PredictiveAnalysisModel {
    public function __construct() {}
};
$service = new PredictiveAnalysisService($model);
$today = new DateTimeImmutable('2026-10-05');
$daily = [];
for ($day = $today->modify('-90 days'); $day < $today; $day = $day->modify('+1 day')) {
    $daily[$day->format('Y-m-d')] = ['passengers' => 20, 'trips' => 2];
}
$checks = 0;
$check = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    $checks++;
};
$default = $service->buildFromData(['daily' => $daily], ['horizon' => '30d'], [], $today);
$check($default['success'] && !$default['data_quality']['unavailable'], 'Early-month forecasting must use sufficient trailing history');
$check($default['model']['history_from'] === '2026-07-07' && $default['model']['history_to'] === '2026-10-04', 'Default uses 90 completed days independently of forecast horizon');
$custom = $service->buildFromData(['daily' => $daily], ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30', 'horizon' => '7d'], [], $today);
$check($custom['model']['history_from'] === '2026-09-01' && $custom['model']['history_to'] === '2026-09-30', 'Explicit history range is preserved');
$normalized = $service->buildFromData(['daily' => $daily], ['period' => '7d', 'date_period' => 'custom', 'history_start' => '2026-09-01', 'history_end' => '2026-09-30'], [], $today);
$check($normalized['model']['history_from'] === $custom['model']['history_from'] && $normalized['model']['history_to'] === $custom['model']['history_to'], 'Second normalization retains fetched-data scope');
$thin = $service->buildFromData(['daily' => $daily], ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-04'], [], $today);
$check($thin['data_quality']['unavailable'], 'Explicit insufficient ranges do not fabricate forecasts or expand scope');
echo "$checks predictive analysis regression checks passed.\n";

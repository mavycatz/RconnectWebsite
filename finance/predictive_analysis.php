<?php

$controllerFile = __DIR__ . '/controllers/PredictiveAnalysisController.php';
if (!is_file($controllerFile)) {
    error_log('[PredictiveAnalysis] Missing controller file: ' . $controllerFile);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Predictive Analysis deployment error: controller file not found.';
    exit;
}

require_once __DIR__ . '/../app/init.php';
require_once $controllerFile;

(new \Finance\Controllers\PredictiveAnalysisController())->index();

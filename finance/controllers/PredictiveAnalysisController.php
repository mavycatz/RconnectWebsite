<?php

namespace Finance\Controllers;

use App\Auth\Auth;

class PredictiveAnalysisController
{
    /** View files this page needs; missing ones are reported instead of a blank 500. */
    private const VIEW_FILES = [
        'index.php',
        'sections/filters.php',
        'sections/summary-cards.php',
        'sections/passenger-forecast.php',
        'sections/demand-by-time.php',
        'sections/fuel-cost-forecast.php',
        'sections/fuel-forecast.php',
        'sections/model-info.php',
        'sections/insights.php',
    ];

    public function index()
    {
        if (!in_array(Auth::role(), ['finance_manager', 'finance_officer'])) {
            header('Location: /public/login/index.php');
            exit;
        }

        $viewDir = __DIR__ . '/../views/predictive_analysis';
        $missing = [];
        foreach (self::VIEW_FILES as $relative) {
            if (!is_file($viewDir . '/' . $relative)) {
                $missing[] = $relative;
            }
        }

        if ($missing) {
            error_log('[PredictiveAnalysis] Missing view files: ' . implode(', ', $missing));
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Predictive Analysis deployment error: missing view files: ' . implode(', ', $missing);
            exit;
        }

        require $viewDir . '/index.php';
    }
}

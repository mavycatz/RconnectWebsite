<?php

namespace Finance\Controllers;

use App\Auth\Auth;

class PredictiveAnalysisController
{
    public function index()
    {
        if (!in_array(Auth::role(), ['finance_manager', 'finance_officer'])) {
            header('Location: /public/login/index.php');
            exit;
        }
        require __DIR__ . '/../views/predictive_analysis/index.php';
    }
}

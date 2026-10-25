<?php

namespace Finance\Controllers;

use App\Auth\Auth;

class FinancialIntelligenceController
{
    public function index()
    {
        if (!in_array(Auth::role(), ['finance_manager', 'finance_officer'])) {
            header('Location: /public/login/index.php');
            exit;
        }

        require __DIR__ . '/../views/financial_intelligence/index.php';
    }
}

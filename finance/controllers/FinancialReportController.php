<?php

namespace Finance\Controllers;

require_once __DIR__ . '/../services/FinancialReportAIService.php';
require_once __DIR__ . '/../models/FinancialReportModel.php';

use Finance\Services\FinancialReportAIService;
use Finance\Models\FinancialReportModel;

class FinancialReportController
{
    private $financialModel;
    private $aiService;

    public function __construct()
    {
        $this->financialModel = new FinancialReportModel();
        $this->aiService = new FinancialReportAIService();
    }

    public function index()
    {
        try{

            $overview = $this->financialModel->getFinancialOverview();
            $expenseBreakdown = $this->financialModel->getExpenseBreakdown();
            $expenseTrends = $this->financialModel->getExpenseTrends();
            $costPerBus = $this->financialModel->getCostPerBus();

            $netProfit = ($overview['total_revenue'] ?? 0) - ($overview['total_expenses'] ?? 0);
            $overview['net_profit'] = $netProfit;

            $data = [
                'overview' => $overview,
                'expenseBreakdown' => $expenseBreakdown,
                'expenseTrends' => $expenseTrends,
                'costPerBus' => $costPerBus
            ];

            extract($data);

            require_once __DIR__ . '/../views/financialreports.php';

        }catch(\Exception $e){
            echo '<pre style="background:#fff1f1;color:#cc0000;border:2px solid #ffb3b3;padding:20px;border-radius:12px;margin:20px;font-size:12px;">';
            echo "INDEX METHOD ERROR\n\nMESSAGE:\n" . $e->getMessage();
            echo "\n\nFILE:\n" . $e->getFile() . "\n\nLINE:\n" . $e->getLine();
            echo '</pre>';
        }
    }

    public function generateAIAnalysis()
    {
        try{

            header('Content-Type: application/json');

            $overview = $this->financialModel->getFinancialOverview();
            $expenseBreakdown = $this->financialModel->getExpenseBreakdown();

            $netProfit = ($overview['total_revenue'] ?? 0) - ($overview['total_expenses'] ?? 0);
            $overview['net_profit'] = $netProfit;

            $analysis = $this->aiService->generateAnalysis($overview, $expenseBreakdown);

            echo json_encode([
                'success' => true,
                'analysis' => $analysis,
                'data' => $overview
            ]);

        }catch(\Exception $e){
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
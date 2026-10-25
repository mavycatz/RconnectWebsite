<?php

require_once
'../services/AIReportService.php';

class ReportController
{
    public function generateAIReport()
    {

        $conn = new mysqli(
            "localhost",
            "root",
            "",
            "YOUR_DATABASE_NAME"
        );

        $sql = "

        SELECT
            SUM(fare) AS total_revenue,
            COUNT(*) AS total_passengers
        FROM passenger_trips
        WHERE payment_status = 'paid'

        ";

        $result =
            $conn->query($sql);

        $row =
            $result->fetch_assoc();

        $totalRevenue =
            (float)(
                $row['total_revenue']
                ?? 0
            );

        $totalPassengers =
            (int)(
                $row['total_passengers']
                ?? 0
            );

        $totalExpenses =
            25500;

        $netProfit =
            $totalRevenue -
            $totalExpenses;

        $reportData = "

Transportation Report

Total Revenue: ₱" .
number_format($totalRevenue, 2) . "

Total Expenses: ₱" .
number_format($totalExpenses, 2) . "

Net Profit: ₱" .
number_format($netProfit, 2) . "

Total Passengers: " .
$totalPassengers . "

Generate:
1. Operational Analysis
2. Key Insights
3. Recommendations

Keep response concise and professional.

";

        $ai =
            new AIReportService();

        $analysis =
            $ai->generateAnalysis(
                $reportData
            );

        echo json_encode([

            'success' => true,

            'analysis' =>
                $analysis
        ]);
    }
}
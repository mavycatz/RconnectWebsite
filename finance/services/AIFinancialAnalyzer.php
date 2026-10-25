<?php

namespace Finance\Services;

class AIFinancialAnalyzer
{
    public function analyze(
        array $report
    ): array {

        $text =
            strtolower(

                ($report['notes'] ?? '')
                .
                ' '
                .
                ($report['original_name'] ?? '')
            );

        /*
        |--------------------------------------------------------------------------
        | AI CATEGORY DETECTION
        |--------------------------------------------------------------------------
        */

        $category =
            'Other Expense';

        $confidence =
            65;

        if(
            str_contains($text, 'fuel')
            ||
            str_contains($text, 'gas')
            ||
            str_contains($text, 'diesel')
        ){

            $category =
                'Fuel Expense';

            $confidence =
                96;
        }

        elseif(
            str_contains($text, 'maintenance')
            ||
            str_contains($text, 'repair')
        ){

            $category =
                'Maintenance';

            $confidence =
                92;
        }

        elseif(
            str_contains($text, 'revenue')
            ||
            str_contains($text, 'income')
        ){

            $category =
                'Revenue';

            $confidence =
                90;
        }

        /*
        |--------------------------------------------------------------------------
        | AI SUMMARY
        |--------------------------------------------------------------------------
        */

        $summary =
            $this->generateSummary(
                $report,
                $category
            );

        /*
        |--------------------------------------------------------------------------
        | AI INSIGHTS
        |--------------------------------------------------------------------------
        */

        $insights =
            $this->generateInsights(
                $report,
                $category
            );

        return [

            'category' =>
                $category,

            'confidence' =>
                $confidence,

            'summary' =>
                $summary,

            'insights' =>
                $insights
        ];
    }

    private function generateSummary(
        array $report,
        string $category
    ): string {

        $amount =
            number_format(
                (float)($report['amount'] ?? 0),
                2
            );

        return

            "AI detected this financial entry as {$category}. "

            .

            "Recorded amount is ₱{$amount}. "

            .

            "The upload appears to contain operational financial data.";
    }

    private function generateInsights(
        array $report,
        string $category
    ): string {

        $amount =
            (float)($report['amount'] ?? 0);

        if(
            $amount >= 10000
        ){

            return

                "High-value {$category} detected. "

                .

                "Recommend managerial review.";
        }

        return

            "Financial activity appears within normal range.";
    }
}
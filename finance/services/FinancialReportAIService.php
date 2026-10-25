<?php

namespace Finance\Services;

require_once __DIR__ . '/FinancialReportPresentationService.php';

$aiConfig_finReport = require __DIR__
    . '/../config/ai.php';

class FinancialReportAIService
{
    private $apiKey;
    private $companyName = 'RPMVFMPC';
    private $reportingPeriod = '';

    public function __construct()
    {
        global $aiConfig_finReport;
        $this->apiKey = $aiConfig_finReport['api_key'];
    }

    public function suggestCategory(string $description, float $amount): string
    {
        $prompt = <<<PROMPT
You are a financial data classifier. Given an expense description and amount, return ONLY the single best category name from this list:
Fuel, Toll, Maintenance, Salaries, Terminal Fees, Insurance, Permits, Admin, Utilities, Marketing, Other

Rules:
- Return ONLY the category name, nothing else.
- If the description is ambiguous or doesn't clearly match any category, return "Other".
- Base your decision on the description text and amount.

Description: {$description}
Amount: ₱{$amount}
PROMPT;

        $data = [
            "model" => $GLOBALS['aiConfig_finReport']['model'],
            "messages" => [
                ["role" => "system", "content" => "You are a classifier. Return only the category name, no explanation."],
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => 0.1,
            "max_tokens" => 20
        ];

        $ch = curl_init($GLOBALS['aiConfig_finReport']['api_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return 'Other';
        }

        $result = json_decode($response, true);
        $content = $result['choices'][0]['message']['content'] ?? 'Other';
        $content = trim($content);

        $valid = ['Fuel', 'Toll', 'Maintenance', 'Salaries', 'Terminal Fees', 'Insurance', 'Permits', 'Admin', 'Utilities', 'Marketing', 'Other'];
        if (!in_array($content, $valid)) {
            return 'Other';
        }

        return $content;
    }

    public function generateSnapshotAnalysis(array $snapshot, string $reportType, string $description): string
    {
        if (!$this->apiKey || !function_exists('curl_init')) {
            throw new \RuntimeException('AI analysis is unavailable. Check the Finance AI configuration and PHP cURL, then retry.');
        }
        $totals = $snapshot['totals'];
        $overview = ['total_revenue' => $totals['revenue'], 'total_expenses' => $totals['expenses'], 'net_profit' => $totals['profit']];
        $breakdown = array_map(static fn($row) => [
            'category' => $row['name'], 'total' => $row['expenses'],
            'count' => count(array_filter($snapshot['records'], static fn($record) => $record['kind'] === 'expense' && $record['category'] === $row['name'])),
        ], $snapshot['breakdowns']['categories']);
        $aggregated = $overview + [
            'fuel_cost' => $totals['fuel'], 'passenger_count' => $totals['passengers'], 'trip_count' => $totals['trips'],
            'avg_fare' => $totals['passengers'] > 0 ? $totals['passenger_revenue'] / $totals['passengers'] : 0,
            'data_basis' => $snapshot['notes'],
            'verified_snapshot' => array_intersect_key($snapshot, array_flip(['filters', 'generated_at', 'totals', 'previous', 'growth', 'series', 'breakdowns', 'insights', 'notes'])),
        ];
        $scoped = FinancialReportPresentationService::apply($snapshot, $reportType);
        $scope = $scoped['presentation'];
        $verified = &$aggregated['verified_snapshot'];
        $verified['report_type'] = $reportType;
        $breakdowns = $scope['tables'];
        if (in_array('expenses', $scope['charts'], true)) $breakdowns[] = 'categories';
        $verified['breakdowns'] = array_intersect_key($verified['breakdowns'], array_flip($breakdowns));
        $trendBucket = array_intersect($scope['charts'], ['comparison', 'performance']) ? 'monthly' : $scope['bucket'];
        $verified['series'] = in_array($scope['bucket'], $scope['tables'], true) || $trendBucket === 'monthly' ? [$trendBucket => $verified['series'][$trendBucket]] : [];
        $cardKeys = ['revenue', 'expenses', 'profit', 'fuel', 'operational_costs', 'passenger_revenue', 'trips'];
        $keys = array_values(array_intersect_key($cardKeys, array_flip($scope['cards'])));
        if (in_array(5, $scope['cards'], true)) $keys[] = 'passengers';
        if ($reportType === 'drivers' || $reportType === 'full') $keys[] = 'collections';
        if (in_array(2, $scope['cards'], true)) $keys[] = 'margin';
        $verified['totals'] = array_intersect_key($verified['totals'], array_flip($keys));
        if (!$scope['profit']) unset($verified['previous']);
        $verified['insights'] = $scoped['insights'];
        unset($verified);
        // Long reporting ranges use monthly/yearly trends in the AI prompt;
        // the complete daily/weekly series remain in the saved preview and PDF.
        if (count($snapshot['series']['daily'] ?? []) > 31) {
            unset($aggregated['verified_snapshot']['series']['daily']);
        }
        if (count($snapshot['series']['weekly'] ?? []) > 26) {
            unset($aggregated['verified_snapshot']['series']['weekly']);
        }
        $content = $this->generateAnalysis($overview, $breakdown, $reportType, $description, $aggregated, $snapshot['filters']['from'], $snapshot['filters']['to'], 45);
        if (preg_match('/^AI (?:Service|returned)/i', $content) || trim($content) === '') {
            error_log('Financial report AI: ' . $content);
            throw new \RuntimeException('AI analysis could not be completed. Please retry generating the report.');
        }
        return $content;
    }

    public function generateAnalysis($financialData, $expenseBreakdown = [], $reportType = 'full', $userDescription = '', array $aggregated = [], $dateFrom = null, $dateTo = null, int $timeout = 180)
    {
        $revenue = number_format($financialData['total_revenue'] ?? 0, 2);
        $expenses = number_format($financialData['total_expenses'] ?? 0, 2);
        $net = number_format($financialData['net_profit'] ?? 0, 2);

        // Derive reporting period from filter dates or data
        if ($dateFrom && $dateTo) {
            $this->reportingPeriod = date('F j, Y', strtotime($dateFrom)) . ' - ' . date('F j, Y', strtotime($dateTo));
        } elseif ($dateFrom) {
            $this->reportingPeriod = 'From ' . date('F j, Y', strtotime($dateFrom));
        } elseif ($dateTo) {
            $this->reportingPeriod = 'Until ' . date('F j, Y', strtotime($dateTo));
        } elseif (!empty($aggregated['historical_monthly'])) {
            $months = array_column($aggregated['historical_monthly'], 'month');
            $this->reportingPeriod = min($months) . ' - ' . max($months);
        } else {
            $this->reportingPeriod = date('F Y');
        }

        $expenseTotal = array_sum(array_column($expenseBreakdown, 'total'));
        $expenseDetail = '';
        if (!empty($expenseBreakdown)) {
            foreach ($expenseBreakdown as $cat) {
                $pct = $expenseTotal > 0 ? round(($cat['total'] / $expenseTotal) * 100, 2) : 0;
                $expenseDetail .= "- {$cat['category']}: ₱" . number_format($cat['total'], 2) . " ({$pct}%, {$cat['count']} entries)\n";
            }
        }

        // Build rich data summary from aggregated
        $richData = '';
        if (!empty($aggregated)) {
            $richData .= "\n## DETAILED OPERATIONAL DATA\n\n";
            $richData .= "- Total Passengers: " . ($aggregated['passenger_count'] ?? 'N/A') . "\n";
            $richData .= "- Total Trips: " . ($aggregated['trip_count'] ?? 'N/A') . "\n";
            $richData .= "- Average Fare per Passenger: ₱" . number_format($aggregated['avg_fare'] ?? 0, 2) . "\n";
            $richData .= "- Total Cash Collected: ₱" . number_format($aggregated['total_cash'] ?? 0, 2) . "\n";
            $richData .= "- Total GCash Collected: ₱" . number_format($aggregated['total_gcash'] ?? 0, 2) . "\n";
            $richData .= "- Total Discrepancy: ₱" . number_format($aggregated['total_discrepancy'] ?? 0, 2) . "\n";
            $richData .= "- Fuel Cost: ₱" . number_format($aggregated['fuel_cost'] ?? 0, 2) . "\n";

            if (!empty($aggregated['fuel_logs'])) {
                $fl = $aggregated['fuel_logs'];
                $richData .= "- Fuel Records (fuel_logs per-trip ODO/KPL + fuel_refuels refuel transactions): " . number_format($fl['liters'] ?? 0, 2) . " liters, ₱" . number_format($fl['cost'] ?? 0, 2) . ", {$fl['logs']} refuel entries\n";
            }

            if (!empty($aggregated['fixed_costs'])) {
                $fc = $aggregated['fixed_costs'];
                $act = $aggregated['active_drivers'] ?? 0;
                $richData .= "- Fixed Costs ($act active drivers): Amortization ₱" . number_format($fc['amortization'], 2) . ", Insurance ₱" . number_format($fc['insurance'], 2) . ", GPS ₱" . number_format($fc['gps'], 2) . ", OPTR ₱" . number_format($fc['optr'], 2) . ", Total ₱" . number_format($fc['total'], 2) . "\n";
            }
            $richData .= "- Total Buses: " . ($aggregated['total_buses'] ?? 'N/A') . "\n";
            $richData .= "- Total Drivers: " . (($aggregated['driver_stats']['total'] ?? 0)) . "\n";
            $richData .= "- Active Drivers: " . (($aggregated['driver_stats']['active'] ?? 0)) . "\n";
            $richData .= "- Completed Dispatches: " . ($aggregated['completed_dispatches'] ?? 'N/A') . "\n";

            if (!empty($aggregated['payment_methods'])) {
                $richData .= "\nPayment Method Breakdown:\n";
                foreach ($aggregated['payment_methods'] as $pm) {
                    $richData .= "- {$pm['payment_method']}: {$pm['total']} transactions, ₱" . number_format($pm['revenue'], 2) . "\n";
                }
            }

            if (!empty($aggregated['routes'])) {
                $richData .= "\nTop Routes:\n";
                foreach ($aggregated['routes'] as $rt) {
                    $richData .= "- {$rt['route_name']}: {$rt['trips']} trips, ₱" . number_format($rt['revenue'], 2) . "\n";
                }
            }

            if (!empty($aggregated['remittance_summary'])) {
                $richData .= "\nRemittance Summary:\n";
                foreach ($aggregated['remittance_summary'] as $rem) {
                    $richData .= "- {$rem['status']}: {$rem['total']} entries, expected ₱" . number_format($rem['expected'], 2) . ", actual ₱" . number_format($rem['actual'], 2) . ", discrepancy ₱" . number_format($rem['discrepancy'], 2) . "\n";
                }
            }

            if (!empty($aggregated['bus_trip_summary'])) {
                $bts = $aggregated['bus_trip_summary'];
                $richData .= "\nBus Trip Summary: {$bts['total_trips']} trips, {$bts['total_passengers']} passengers, ₱" . number_format($bts['total_revenue'], 2) . " revenue, avg fuel cost ₱" . number_format($bts['avg_fuel_cost'], 2) . "\n";
            }
        }

        $userNote = '';
        if (!empty($userDescription)) {
            $userNote = "\nUser's specific request: {$userDescription}";
        }

        // Determine available data per type
        $hasRevenue = ($aggregated['total_revenue'] ?? 0) > 0 || ($aggregated['trip_count'] ?? 0) > 0;
        $hasExpenses = $expenseTotal > 0;
        $hasTrips = ($aggregated['trip_count'] ?? 0) > 0;
        $hasBuses = ($aggregated['total_buses'] ?? 0) > 0;
        $hasDrivers = ($aggregated['driver_stats']['total'] ?? 0) > 0;
        $hasRoutes = !empty($aggregated['routes']);

        // Build type-specific sections — ONLY the requested type, nothing else
        $sections = '';
        if ($reportType === 'full') {
            if ($hasRevenue) $sections .= "- Revenue Analysis (total revenue, payment methods, routes, passenger counts)\n";
            if ($hasExpenses) $sections .= "- Expense Analysis (operating expenses breakdown, largest expenses, cost-saving opportunities)\n";
            if ($hasBuses) $sections .= "- Fleet Performance (each bus performance, best/worst performing)\n";
            if ($hasRoutes) $sections .= "- Route Performance (route trips, passengers, revenue, most/least profitable)\n";
            if ($hasDrivers && $hasTrips) $sections .= "- Driver Performance (trips, revenue, outstanding performers)\n";
            $sections .= "- Financial Summary (revenue, expenses, net profit, profit margin)\n";
            $sections .= "- Key Performance Indicators\n";
            $sections .= "- Recommendations\n- Conclusion\n";
        } else {
            switch ($reportType) {
                case 'revenue': $sections = "- Revenue Analysis: total revenue, payment methods, routes, average fare, passenger counts\n"; break;
                case 'expenses': $sections = "- Expense Analysis: operating expenses breakdown by category, largest expenses, cost-saving opportunities\n"; break;
                case 'fleet': $sections = "- Fleet Performance: each bus ID, revenue, cost, trips, best/worst performer\n"; break;
                case 'routes': $sections = "- Route Performance: route, trips, passengers, revenue, optimization suggestions\n"; break;
                case 'drivers': $sections = "- Driver Performance: driver, trips, revenue, outstanding performers\n"; break;
                case 'summary': $sections = "- Financial Summary: revenue, expenses, net profit, profit margin, financial health\n"; break;
                case 'risk': $sections = "- Risk Assessment: operational, financial, revenue risks\n- Key Performance Indicators\n"; break;
            }
        }

        // Pass ALL data as structured JSON (like AIAnalyticsService does)
        $jsonEncoded = json_encode($aggregated, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        $jsonData = is_string($jsonEncoded) ? $jsonEncoded : 'No data available from database queries.';

        $today = date('F j, Y');

        $reportPrompt = <<<PROMPT
CURRENT DATE: {$today}
COMPANY: {$this->companyName}
REPORTING PERIOD: {$this->reportingPeriod}

DATABASE RECORDS (actual data from all tables):
{$jsonData}

{$userNote}

REQUESTED SECTIONS:
{$sections}

INSTRUCTIONS:
You MUST analyze EVERY section of the JSON data above. For each section that has data, include it in the report.

For each of the REQUESTED SECTIONS listed above, find the relevant data in the JSON and write about it:

- Revenue Analysis: Use passengers.fare, passenger_trip_history.fare, remittances.actual_cash, bus_trips.total_revenue. Report total revenue, avg fare, payment method breakdown from payment_methods, and route revenue from routes.

- Expense Analysis: Use expense_breakdown (category, total, count), fuel_logs (liters, cost, logs), fuel_cost, trip_expenses (toll_fees, other_cost), and fixed_costs (amortization, insurance, gps, optr, total). Report largest expenses and percentages. Include the fixed-cost items (Amortization, Insurance, GPS, OPTR) scaled to the reporting period and fuel from fuel logs as separate line items.

- Fleet Performance: Use buses array (bus_id, plate_number, trips, revenue, total_cost, fin_cost). Report each bus performance.

- Route Performance: Use routes array (route_name, trips, revenue, passengers). Report top routes.

- Driver Performance: Use drivers array (driver, trips, revenue, discrepancy). Report top drivers.

- Financial Summary: Use total_revenue, total_expenses, net_profit, fuel_cost.

- Key Performance Indicators: Use data to calculate profit margin, avg fare, cost per bus, etc.

- Use historical_monthly for trend data, historical_passenger_types for passenger mix, fuel_prices for fuel costs.

- Use remittance_summary for remittance status breakdown.

- Use trip_expenses for additional cost context.

Base every statement on actual data. Never invent. Never write N/A. If a data field is zero or empty, skip it. Use ₱ with commas. Plain paragraphs and bullet points only. No --- === *** markdown.
PROMPT;

        if (!empty($aggregated['data_basis'])) {
            $reportPrompt = "Write a concise {$reportType} financial report for RPMVFMPC covering {$this->reportingPeriod}. Include ONLY this selected category; do not add unrelated financial sections or repeat the same totals in multiple sections. Use clear headings, short paragraphs and bullets. Target 250–400 words; address these custom instructions: {$userDescription}\nThe verified snapshot is authoritative. Remittances are settlement, not additional revenue. Do not add estimated fixed costs or duplicate fuel costs. Report only recorded costs. Identify unassigned attribution. Discuss category-specific trends, risks and recommendations. Do not invent missing figures or route data. Amounts use PHP pesos.\n";
            $reportPrompt .= json_encode($aggregated['verified_snapshot'] ?? $aggregated, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        }

        global $aiConfig_finReport;

        $url = $aiConfig_finReport['api_url'];

        $fixedDocLine = '- fixed_costs: per-driver fixed costs scaled to the reporting period with amortization (1098.00), insurance (192.89), gps (6.25), optr (500.00), total';
        if (empty($aggregated['data_basis'])) try {
            $fixedBases = \App\Core\FinancialCosts::monthlyBases();
            $fixedDocLine = sprintf(
                '- fixed_costs: per-driver fixed costs scaled to the reporting period with amortization (%.2f), insurance (%.2f), gps (%.2f), optr (%.2f), total (editable in Financial Cost Settings)',
                $fixedBases['amortization'],
                $fixedBases['insurance'],
                $fixedBases['gps'],
                $fixedBases['optr']
            );
        } catch (\Throwable $e) {
            // Keep the legacy documentation line when settings are unavailable.
        }

        $data = [
            "model" => $aiConfig_finReport['model'],
            "messages" => [
                [
                    "role" => "system",
                    "content" => "You are a Senior Finance and Operations Manager for a public mini bus transportation company. You analyze actual database records and generate professional financial reports.

DATABASE TABLES AVAILABLE:
- passenger_trips: current trip records with fare, payment_method, payment_status, boarding_point, destination, driver_id, cash_amount, gcash_amount, discrepancy_amount, boarding_time
- passenger_trip_history: archived historical trips with passenger_name, passenger_type, boarding_point, destination, fare, payment_method, drivers_full_name, trip_date, date_booked, student_id, senior_pwd_id, beneficiary_id, coop_disc_id
- expenses: operational expenses with category (fuel, driver_salary, toll, insurance, misc), amount, bus_id, created_at
- financial_expenses: imported expense records with category, amount, expense_date, bus_id, driver_id, description
- remittances: driver remittance records with expected_cash, actual_cash, discrepancy_amount, status
- buses: fleet information with bus_id, plate_number, driver, route_name, status
- drivers: driver records with full_name, status, license_number
- bus_trips: scheduled trips with total_passengers, total_revenue, fuel_cost, trip_date, status
- dispatches: dispatch records with bus_id, driver_name, route, passenger_count, status, trip_date
- routes: route definitions with route_name
- trip_expenses: per-trip costs with toll_fees, other_cost
- fuel_prices: fuel type and price_per_liter
- fuel_logs: one odometer/KPL record per completed trip (liters/cost are zero on these rows)
- fuel_refuels: actual refuel transactions with liters, total_cost, price_per_liter, refuel_odo, created_at
{$fixedDocLine}

RULES:
- Analyze ALL data provided. Do not ignore any table.
- The passenger_trip_history table contains important historical trends - use it.
- Never write N/A or empty values. If a field has no data, skip mentioning it.
- Base every number on actual data. Never invent or fabricate figures.
- Use plain text format: section headers like 'Revenue Analysis', then paragraphs and bullet points.
- Do not use markdown symbols like ## ** --- === ___ or dividers.
- Highlight discrepancies, top performers, risks, and cost-saving opportunities.
- Write in formal business tone suitable for executives."
                ],
                [
                    "role" => "user",
                    "content" => $reportPrompt
                ]
            ],
            "temperature" => 0.5,
            "max_tokens" => 8192
        ];

        if (!empty($aggregated['data_basis'])) {
            $data['messages'][0]['content'] = 'You are a senior transportation finance analyst. Use only the supplied verified snapshot and its accounting notes. Write a concise executive report in plain text with section headings and bullets, no Markdown tables or decorative dividers.';
            $data['max_tokens'] = 1200;
            $data['temperature'] = 0.3;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $timeout));
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            return "AI Service Error: " . $curlError;
        }
        if ($httpCode !== 200) {
            return "AI Service returned HTTP {$httpCode}. Response: " . substr($response, 0, 500);
        }

        $result = json_decode($response, true);
        if (!$result) {
            return "AI Service: Invalid JSON response.";
        }
        if (isset($result['error'])) {
            return "AI Service Error: " . ($result['error']['message'] ?? json_encode($result['error']));
        }

        $content = $result['choices'][0]['message']['content'] ?? '';
        if (!is_string($content) || trim($content) === '') return 'AI returned empty response.';

        // Prepend company header if not present
        if (strpos($content, $this->companyName) === false) {
            $header = "Company: {$this->companyName}\n";
            $header .= "Report: Financial Performance Report\n";
            $header .= "Period: {$this->reportingPeriod}\n";
            $header .= "Generated: " . date('F j, Y') . "\n";
            $header .= str_repeat('-', 50) . "\n\n";
            $content = $header . $content;
        }

        // Strip markdown/ASCII table formatting artifacts from AI output
        $content = preg_replace('/[*#_~`>]+/', '', $content);
        $content = preg_replace('/^\s*[-=]{3,}\s*$/m', '', $content);
        $content = preg_replace('/[|]/', '', $content);
        $content = preg_replace('/\[.*?\]\(.*?\)/', '', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        // Strip lines containing N/A (but not "N/A" in the middle of valid data)
        $lines = explode("\n", $content);
        $filtered = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Skip lines that are ONLY "N/A" or "N/A" as value with surrounding spaces
            if (preg_match('/^\s*(?:N\/A|\-)\s*$/', $trimmed)) continue;
            // Skip table-like rows where most values are N/A or 0.00
            if (preg_match('/\bN\/A\b/', $trimmed) && !preg_match('/[A-Za-z]{3,}/', $trimmed)) continue;
            $filtered[] = $line;
        }
        $content = implode("\n", $filtered);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        // Fix AI-generated dates — the model often uses its training cutoff date instead of real dates
        $today = date('F j, Y');
        $content = preg_replace('/Date Generated:.*/i', 'Date Generated: ' . $today, $content);
        $content = preg_replace('/Generated:.*/i', 'Generated: ' . $today, $content);
        $content = preg_replace('/Period:.*/i', 'Period: ' . $this->reportingPeriod, $content);

        return trim($content);
    }
}

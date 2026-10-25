<?php
/*
|--------------------------------------------------------------------------
| Finance Analytics — AI Quick Insight endpoint
|--------------------------------------------------------------------------
| Reads the verified Finance Analytics dashboard figures (the same numbers
| the page shows), asks the configured OpenAI-compatible model for a short
| finance analysis, and returns plain text for the AI card beside the KPIs.
*/

declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app/init.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/middleware/validate_request.php';

use App\Auth\Auth;

validateFinanceRequest('standard', false);
setRequestTimeout(90);

function ai_send_json(array $data, int $code = 200): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    if ($code !== 200) {
        http_response_code($code);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---- Role gate: Finance Manager / Finance Officer only ---- */
if (!Auth::check()) {
    ai_send_json(['success' => false, 'message' => 'Unauthenticated'], 401);
}
$role = strtolower(trim((string) Auth::role()));
if (!in_array($role, ['finance_manager', 'finance_officer'], true)) {
    ai_send_json(['success' => false, 'message' => 'Forbidden'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    ai_send_json(['success' => false, 'message' => 'No input received'], 400);
}

$aiConfig = require __DIR__ . '/../../config/ai.php';

if (empty($aiConfig['api_key'])) {
    ai_send_json(['success' => false, 'message' => 'AI is not configured for this portal.']);
}

/* ---- Compact, verified data (mirrors the visible dashboard figures) ---- */
$fin = (array) ($input['financial'] ?? []);
$rem = (array) ($input['remittance'] ?? []);
$disc = (array) ($input['disc'] ?? []);
$rev = (array) ($input['rev'] ?? []);
$fuel = (array) ($input['fuel'] ?? []);
$net = (array) ($input['net'] ?? []);
$ops = (array) ($input['operators'] ?? []);
$top = (array) ($input['top_operator'] ?? []);
$range = (array) ($input['range'] ?? []);

$period = trim((string) ($input['period'] ?? ''));
$fmtRange = trim((string) (($range['start'] ?? '') . ' -> ' . ($range['end'] ?? '')));
if ($fmtRange === ' -> ') {
    $fmtRange = '';
}

$pct = function ($v) {
    $last = (float) ($v['last'] ?? 0);
    $prev = (float) ($v['prev'] ?? 0);
    if ($prev == 0) {
        return null;
    }
    return round((($last - $prev) / abs($prev)) * 100, 1);
};

$revPct = $pct($rev);
$fuelPct = $pct($fuel);
$netPct = $pct($net);

$prompt = "
You are Riza, the finance analyst for this transport cooperative.

Below is the VERIFIED data shown right now on the cooperative's Finance Analytics dashboard for the selected period. Treat these numbers as authoritative — never invent, estimate, or guess any figure. Use the numbers, percentages, and PHP peso (\"PGP\" below) formatting you are given; use the peso sign only if a value in the data is already in pesos.

PERIOD: {$period} {$fmtRange}

GROSS INCOME: " . number_format((float)($fin['gross_income'] ?? 0), 2) . "
NET INCOME: " . number_format((float)($fin['net_income'] ?? 0), 2) . "
FUEL COST: " . number_format((float)($fin['fuel'] ?? 0), 2) . "
OTHER EXPENSES: " . number_format((float)($fin['other_expenses'] ?? 0), 2) . "

REMITTANCE RATE: " . ((float)($rem['remittance_rate'] ?? 0)) . "% (remitted " . number_format((float)($rem['remitted_amount'] ?? 0), 2) . " of " . number_format((float)($rem['paid_expected'] ?? 0), 2) . ")
DISCREPANCIES: " . (int)($disc['cases'] ?? 0) . " case(s), total " . number_format((float)($disc['amount'] ?? 0), 2) . "

REVENUE TREND (last vs previous point):
last " . (float)($rev['last'] ?? 0) . ", prev " . (float)($rev['prev'] ?? 0) . ($revPct !== null ? (', change ' . $revPct . '%') : ', no prior value') . "

FUEL TREND (last vs previous point):
last " . (float)($fuel['last'] ?? 0) . ", prev " . (float)($fuel['prev'] ?? 0) . ($fuelPct !== null ? (', change ' . $fuelPct . '%') : ', no prior value') . "

NET INCOME TREND (last vs previous point):
last " . (float)($net['last'] ?? 0) . ", prev " . (float)($net['prev'] ?? 0) . ($netPct !== null ? (', change ' . $netPct . '%') : ', no prior value') . "

OPERATORS: " . (int)($ops['total'] ?? 0) . " active, " . (int)($ops['remitted'] ?? 0) . " remitted, " . (int)($ops['discrepancy'] ?? 0) . " with discrepancy, " . (int)($ops['no_remit'] ?? 0) . " no remittance
TOP OPERATOR: " . trim((string) ($top['name'] ?? 'n/a')) . " with " . number_format((float)($top['gross'] ?? 0), 2) . "

Write ONE short plain-text analysis, 4 to 6 sentences, no headings, no bullet points, no markdown.
Cover, in order of importance:
1. Overall performance for the period.
2. The single most noticeable trend (revenue, fuel, or net income) and roughly why.
3. Remittance health and whether discrepancies are a concern.
4. One practical, data-backed recommendation.

Use plain sentences and natural percentages. If a figure is missing, skip it instead of guessing.
";

$payload = [
    'model' => $aiConfig['model'],
    'messages' => [
        ['role' => 'system', 'content' => 'You are a concise financial analyst for a Philippine transport cooperative. Respond in plain text only.'],
        ['role' => 'user', 'content' => $prompt],
    ],
    'temperature' => 0.4,
    'max_tokens' => 260,
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $aiConfig['api_url'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 80,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $aiConfig['api_key'],
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error !== '') {
    ai_send_json(['success' => false, 'error' => $error]);
}

$data = json_decode((string) $response, true);

if (!isset($data['choices'][0]['message']['content'])) {
    ai_send_json([
        'success' => false,
        'error' => $data['error']['message'] ?? 'Unknown AI API error',
    ]);
}

$analysis = trim((string) $data['choices'][0]['message']['content']);
$analysis = trim(preg_replace('/[ \t]+/', ' ', $analysis));
$analysis = preg_replace("/\n{3,}/", "\n\n", $analysis);

ai_send_json(['success' => true, 'analysis' => $analysis]);
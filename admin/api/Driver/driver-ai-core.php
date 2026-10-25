<?php
declare(strict_types=1);

use App\Core\Database;

function dpm_peso(float $v): string
{
    return '₱' . number_format($v, 2);
}

function dpm_build_analysis(PDO $db, string $driverId, string $start, string $end, string $period): array
{
    $m = dpm_compute($db, $driverId, $start, $end, $period);
    if (isset($m['error'])) {
        return ['error' => $m['error']];
    }

    $att = $m['attendance'];
    $tr = $m['trips'];
    $pa = $m['passengers'];
    $ot = $m['other'];
    $fa = $m['fare'];
    $ca = $m['capacity'];

    $periodLabel = ucfirst($period);
    if ($period === 'custom') {
        $periodLabel = $start . ' to ' . $end;
    }

    $attRate = ($att['rate'] === null || $att['rate'] === '')
        ? 'not recorded'
        : trim((string)$att['rate'], '%') . '%';
    $occPct = ($ca['occupancy'] === null) ? 'N/A' : trim((string)$ca['occupancy'], '%');
    $faGross = $m['gross_income'] ?? $fa['total'];
    $fc = $m['fixed_costs'] ?? dpm_fixed_costs(dpm_period_months($period, $start, $end));
    $net = $m['net_income'] ?? round($faGross - $fc['total'], 2);
    $topRoutesLine = '';
    if (!empty($m['top_routes']) && is_array($m['top_routes'])) {
        $parts = [];
        foreach ($m['top_routes'] as $rt => $cnt) {
            $parts[] = $rt . ' (' . $cnt . ')';
        }
        $topRoutesLine = "Top routes (boarding -> destination): " . implode('; ', $parts) . ".\n";
    }

    $gasLine = (isset($m['gas']) && ($m['gas']['amount'] > 0 || $m['gas']['liters'] > 0))
        ? dpm_peso($m['gas']['amount']) . " (" . number_format($m['gas']['liters'], 2) . " L)"
        : 'undetermined';
    $prompt = "
You are a transport operations analytics AI helping an ADMIN understand a single driver's performance for a Philippine public transport system.

CRITICAL RULES:
- Base every statement ONLY on the verified numbers below. Do NOT invent figures.
- PASSENGER vs OTHER: passengers are people (type=passenger); OTHER items are non-passenger cargo (type=other) charged at regular fare x occupied spaces.
- 'Other items' is a COUNT of cargo records; 'occupied_spaces' is how many vehicle spaces those cargo items used. Do not conflate the two.
- If a metric is zero or undetermined from the data, say it is undetermined rather than guessing.
- Do NOT mention cancellations, pending trips, reasons for absence, reasons for low performance, cost-management claims, or operational explanations unless that exact data is explicitly provided above. Describe observed data factually (e.g., \"recorded 4 trips during the period\") instead of inventing causes or reasons.
- Respond with ONLY a valid JSON object with exactly these five keys:
{
  \"summary\": \"1-2 sentences on overall performance for the period\",
  \"strengths\": \"1-2 sentences on what the driver does well, based only on the data\",
  \"watch_outs\": \"1-2 sentences on observed risks/gaps based ONLY on the data provided (attendance, low occupancy, other-item share). Do NOT mention cancellations or pending trips.\",
  \"key_findings\": \"2-3 sentences covering attendance, trips, passengers, gross income vs fixed costs and gas, net income, occupancy, and relevant route/activity context\",
  \"recommendation\": \"one practical, data-supported suggestion. Do NOT set arbitrary numeric targets (e.g., 'increase attendance to 50%') unless such a target is supplied in the data.\"
}
- Each value: 1-2 short sentences, no markdown, no bullets, no code fences, no repeats across sections.

Driver: {$m['name']} ({$m['driver_id']})
Period: {$periodLabel}

Attendance (from driver check-in records): present {$att['present']} day(s), absent {$att['absent']} day(s), expected workdays {$att['scheduled']}, attendance rate {$attRate}.
Trips: total {$tr['total']}.
Passengers (people): total {$pa['total']}, regular {$pa['regular']}, special/discounted {$pa['special']}.
Other (cargo) items: {$ot['records']} records using {$ot['occupied_spaces']} occupied space(s).
Gross Income (actual fares from passenger + Other/Baggage): " . dpm_peso($faGross) . ".
        Fixed Costs (MONTHLY per driver, scaled to {$fc['months']} month(s) for this period): Amortization " . dpm_peso($fc['amortization']) . ", Insurance " . dpm_peso($fc['insurance']) . ", GPS " . dpm_peso($fc['gps']) . ", OPTR " . dpm_peso($fc['optr']) . ", Total Fixed Costs " . dpm_peso($fc['total']) . " (monthly base " . dpm_peso($fc['monthly_total']) . ").
Gas (fuel): {$gasLine}.
Net Income: " . dpm_peso($net) . " (Gross Income minus Total Fixed Costs minus Gas Amount).
Capacity: vehicle capacity (seats) {$ca['vehicle_capacity']}, passenger-occupied {$ca['occupied_passenger']}, other-occupied {$ca['occupied_other']}, occupancy rate {$occPct}%.
{$topRoutesLine}";

    require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/config/Env.php';
    $apiUrl = \Finance\Config\Env::get('FINANCE_OPENAI_URL', 'https://api.openai.com/v1/chat/completions');
    $model = \Finance\Config\Env::get('FINANCE_OPENAI_MODEL', 'gpt-4o-mini');
    $apiKey = \Finance\Config\Env::get('FINANCE_OPENAI_KEY', '');

    if (!$apiKey) {
        return ['error' => 'OpenAI API key is not configured'];
    }

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a professional transport operations analytics AI.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.5,
        'max_tokens' => 400,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        return ['error' => $error];
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['choices'][0]['message']['content'])) {
        return ['error' => 'Invalid AI response'];
    }

    $analysis = trim(preg_replace('/[ \t]+/', ' ', trim($data['choices'][0]['message']['content'])));
    $keys = ['summary', 'strengths', 'watch_outs', 'key_findings', 'recommendation'];
    $normalize = function ($text) {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[*_`]/', '', (string)$text)));
    };
    $sections = ['summary' => '', 'strengths' => '', 'watch_outs' => '', 'key_findings' => '', 'recommendation' => ''];

    $decoded = json_decode($analysis, true);
    if (is_array($decoded) && array_intersect($keys, array_keys($decoded))) {
        foreach ($keys as $k) {
            $sections[$k] = isset($decoded[$k]) && is_string($decoded[$k]) ? $normalize($decoded[$k]) : '';
        }
    } elseif (preg_match('/\{.*\}/s', $analysis, $jm) && is_array($embedded = json_decode($jm[0], true)) && array_intersect($keys, array_keys($embedded))) {
        foreach ($keys as $k) {
            $sections[$k] = isset($embedded[$k]) && is_string($embedded[$k]) ? $normalize($embedded[$k]) : '';
        }
    } elseif (preg_match('/Summary\s*:\s*(.*?)Strengths\s*:\s*(.*?)Watch\s*Outs\s*:\s*(.*?)Key\s*Findings\s*:\s*(.*?)Recommendation\s*:\s*(.*?)$/is', $analysis, $lm)) {
        $sections = [
            'summary' => $normalize($lm[1]),
            'strengths' => $normalize($lm[2]),
            'watch_outs' => $normalize($lm[3]),
            'key_findings' => $normalize($lm[4]),
            'recommendation' => $normalize($lm[5]),
        ];
    } else {
        $sections['summary'] = $normalize($analysis);
    }

    return ['analysis' => $sections, 'metrics' => $m, 'period' => $period, 'start' => $start, 'end' => $end];
}

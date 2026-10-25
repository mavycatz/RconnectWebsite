<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';

header('Content-Type: application/json');

ini_set('display_errors', 0);

try {

$input = json_decode(
    file_get_contents("php://input"),
    true
);

if(!$input){

    echo json_encode([
        "success"=>false,
        "message"=>"No input received"
    ]);

    exit;
}

/* =====================================================
   DATA
===================================================== */

$maxTrips =
    (int)($input['max_trips'] ?? 0);

$minTrips =
    (int)($input['min_trips'] ?? 0);

$averageTrips =
    (int)($input['average_trips'] ?? 0);

$growthRate =
    (float)($input['growth_rate'] ?? 0);

$trendStatus =
    trim($input['trend_status'] ?? 'stable');

$trendValue =
    (float)($input['trend_value'] ?? 0);

$dailyData =
    $input['daily_data'] ?? [];

$totalPassengers =
    (int)($input['total_passengers'] ?? 0);

$cash =
    (int)($input['cash'] ?? 0);

$gcash =
    (int)($input['gcash'] ?? 0);

$hybrid =
    (int)($input['hybrid'] ?? 0);

$regular =
    (int)($input['regular'] ?? 0);

$special =
    (int)($input['special'] ?? 0);

$students =
    (int)($input['students'] ?? 0);

$seniors =
    (int)($input['seniors'] ?? 0);

$pwd =
    (int)($input['pwd'] ?? 0);

$nurses =
    (int)($input['nurses'] ?? 0);

$teachers =
    (int)($input['teachers'] ?? 0);

$windowDays =
    (int)($input['window_days'] ?? 0);

$totalTrips =
    (int)($input['total_trips'] ?? 0);

/* =====================================================
   PROMPT
===================================================== */

$prompt = "

You are a transportation analytics assistant for a Philippine public transport dashboard.

Important context about this bus:

- The bus runs on a FIXED schedule with departures every 5-15 minutes.
- Trip counts stay roughly the same every day because the schedule is fixed.
- Small changes in trip numbers are normal and are NOT a sign of low demand or a problem.
- PASSENGER counts are the real measure of demand. They change from day to day while the trips stay fixed.

Explain the data below in plain, easy-to-understand language.

Write a short response with these labeled sections:

Summary: one or two simple sentences about how activity is going.

Strengths: one or two sentences on what is going well.

Watch Outs: one or two sentences on any decline, inconsistency, or risk.

Recommendation: one practical suggestion for the operator.

Rules:

- Use short, simple sentences. No jargon.
- Keep every section brief.
- Do not use bullet points or numbering.
- Do not repeat the same idea in different sections.
- Do not exaggerate.
- Use numbers and percentages naturally.
- Do NOT treat trip count changes as problems. Trips are fixed by the bus schedule, so keep trip comments neutral.
- Base Watch Outs and Recommendation on PASSENGER demand, payment mix, and passenger types.
- If the passenger totals look much larger than the trip window, focus on percentages (payment mix and passenger type mix) instead of comparing totals directly.

Data:

Peak Trips: {$maxTrips}

Lowest Trips: {$minTrips}

Average Trips: {$averageTrips}

Growth Rate: {$growthRate}%

Trend Status: {$trendStatus}

Trend Value: {$trendValue}

Total Passengers: {$totalPassengers}

Payment Mix - Cash: {$cash}, GCash: {$gcash}, Hybrid: {$hybrid}

Passenger Types - Regular: {$regular}, Special: {$special} (Students: {$students}, Seniors: {$seniors}, PWD: {$pwd}, Nurses: {$nurses}, Teachers: {$teachers})

Visible Window Days: {$windowDays}

Total Trips In Window: {$totalTrips}

";

/* =====================================================
   OPENAI REQUEST
===================================================== */

require_once $_SERVER['DOCUMENT_ROOT'] . '/finance/config/Env.php';

$apiUrl =
    \Finance\Config\Env::get(
        'FINANCE_OPENAI_URL',
        'https://api.openai.com/v1/chat/completions'
    );

$model =
    \Finance\Config\Env::get(
        'FINANCE_OPENAI_MODEL',
        'gpt-4o-mini'
    );

$payload = [

    "model" => $model,

    "messages" => [

        [
            "role" => "system",
            "content" =>
            "You are a professional transport operations analytics AI."
        ],

        [
            "role" => "user",
            "content" => $prompt
        ]

    ],

    "temperature" => 0.5,

    "max_tokens" => 220
];

/* =====================================================
   API KEY
===================================================== */

$apiKey =
    \Finance\Config\Env::get(
        'FINANCE_OPENAI_KEY',
        ''
    );

if(!$apiKey){

    echo json_encode([

        "success" => false,

        "error" =>
        "OpenAI API key is not configured"

    ]);

    exit;
}

/* =====================================================
   CURL
===================================================== */

$ch = curl_init();

curl_setopt_array($ch,[

    CURLOPT_URL =>
    $apiUrl,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_POST => true,

    CURLOPT_HTTPHEADER => [

        "Content-Type: application/json",

        "Authorization: Bearer ".$apiKey

    ],

    CURLOPT_POSTFIELDS =>
    json_encode($payload)

]);

$response = curl_exec($ch);

$error = curl_error($ch);

curl_close($ch);

/* =====================================================
   CURL ERROR
===================================================== */

if($error){

    echo json_encode([

        "success"=>false,

        "error"=>$error

    ]);

    exit;
}

/* =====================================================
   RESPONSE
===================================================== */

$data = json_decode($response, true);

if(
    !isset(
        $data['choices'][0]['message']['content']
    )
){

    $aiError =
        $data['error']['message']
        ?? 'Unknown OpenAI API error';

    echo json_encode([

        "success"=>false,

        "error"=>$aiError,

        "full_response"=>$data

    ]);

    exit;
}

$analysis = trim(
    $data['choices'][0]['message']['content']
);

/* =====================================================
   CLEAN TEXT
===================================================== */

$analysis = trim(
    preg_replace(
        '/[ \t]+/',
        ' ',
        $analysis
    )
);

$analysis = preg_replace(
    "/\n{3,}/",
    "\n\n",
    $analysis
);

$analysis = trim($analysis);

/* =====================================================
   OUTPUT
===================================================== */

echo json_encode([

    "success"=>true,

    "analysis"=>$analysis

]);

} catch(Throwable $e){

echo json_encode([

    "success"=>false,

    "error"=>$e->getMessage(),

    "line"=>$e->getLine()

]);

}
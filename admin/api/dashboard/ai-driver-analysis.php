<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';

header('Content-Type: application/json');

try {

$input = json_decode(
    file_get_contents("php://input"),
    true
);

if(!$input){

    echo json_encode([
        "success" => false,
        "message" => "No input received"
    ]);

    exit;
}

/* =====================================================
   DRIVER DATA
===================================================== */

$name =
    trim($input['name'] ?? 'Driver');

$performance =
    (float)($input['performance'] ?? 0);

$income =
    (float)($input['income'] ?? 0);

$trips =
    (int)($input['trips'] ?? 0);

$passengers =
    (int)($input['passengers'] ?? 0);

$cash =
    (int)($input['cash'] ?? 0);

$gcash =
    (int)($input['gcash'] ?? 0);

$hybrid =
    (int)($input['hybrid'] ?? 0);

/* =====================================================
   AI PROMPT
===================================================== */

$prompt = "

You are an AI helper that explains driver performance for a Philippine public transport system.

Important context:

- All drivers run the same FIXED bus schedule with departures every 5-15 minutes.
- Trip counts are similar across drivers because the schedule is fixed, so trip numbers are NOT the main difference between drivers.
- The real differences between drivers show in how many PASSENGERS they serve, the revenue they collect, and their payment mix.

Respond with ONLY a valid JSON object using exactly these four keys:

{
  \"summary\": \"one or two simple sentences about how this driver is doing\",
  \"strengths\": \"one or two sentences on what the driver does well\",
  \"watch_outs\": \"one or two sentences on what can be improved\",
  \"recommendation\": \"one practical suggestion that uses the driver's actual numbers\"
}

Rules:

- Every value must be 1-2 short, simple sentences. No jargon.
- Do not use bullet points, numbering, or markdown formatting.
- Do not wrap the JSON in code fences, backticks, or any other extra text.
- Do not repeat the same idea in different sections.
- Do not exaggerate praise.
- Do not sound robotic or generic.
- Reference the driver's real numbers below so the analysis feels specific.
- Do NOT treat trip count differences as a performance issue. Trips are fixed by the schedule, so keep trip comments neutral.
- Focus watch_outs and recommendation on passengers served, revenue, and payment mix.

Driver Name: {$name}

Performance Score: {$performance}
Total Revenue: ₱{$income}
Trips Completed: {$trips}
Passengers Served: {$passengers}

Cash Payments: {$cash}
GCash Payments: {$gcash}
Hybrid Payments: {$hybrid}

";

/* =====================================================
   OPENAI PAYLOAD
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

    "max_tokens" => 350
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

$httpCode =
    curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

/* =====================================================
   CURL ERROR
===================================================== */

if($error){

    echo json_encode([
        "success" => false,
        "error" => $error
    ]);

    exit;
}

/* =====================================================
   API RESPONSE
===================================================== */

$data = json_decode($response, true);

if(
    !$data ||
    !isset($data['choices'][0]['message']['content'])
){

    echo json_encode([

        "success" => false,

        "error" =>
        "Invalid AI response",

        "response" =>
        $data,

        "http_code" =>
        $httpCode
    ]);

    exit;
}

$analysis = trim(
    $data['choices'][0]['message']['content']
);

/* =====================================================
   CLEAN OUTPUT
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
   STRUCTURED SECTIONS
===================================================== */

$sections = [
    "summary" => "",
    "strengths" => "",
    "watch_outs" => "",
    "recommendation" => ""
];

$keys = [
    "summary",
    "strengths",
    "watch_outs",
    "recommendation"
];

$normalize = function($text){

    $text = trim((string)$text);

    return trim(
        preg_replace(
            '/\s+/',
            ' ',
            preg_replace(
                '/[*_`]/',
                '',
                $text
            )
        )
    );

};

$fillSections = function($source) use ($keys, $normalize){

    $filled = [];

    foreach($keys as $key){

        $filled[$key] =
            isset($source[$key]) &&
            is_string($source[$key])
                ? $normalize($source[$key])
                : "";

    }

    return $filled;

};

$decoded = json_decode($analysis, true);

if(
    is_array($decoded) &&
    array_intersect($keys, array_keys($decoded))
){

    $sections = $fillSections($decoded);

} else if(
    preg_match('/\{.*\}/s', $analysis, $jsonMatch) &&
    is_array($embedded = json_decode($jsonMatch[0], true)) &&
    array_intersect($keys, array_keys($embedded))
){

    $sections = $fillSections($embedded);

} else if(
    preg_match(
        '/Summary\s*:\s*(.*?)' .
        'Strengths\s*:\s*(.*?)' .
        'Watch\s*Outs\s*:\s*(.*?)' .
        'Recommendation\s*:\s*(.*?)$/is',
        $analysis,
        $labelMatch
    )
){

    $sections = [
        "summary" => $normalize($labelMatch[1]),
        "strengths" => $normalize($labelMatch[2]),
        "watch_outs" => $normalize($labelMatch[3]),
        "recommendation" => $normalize($labelMatch[4])
    ];

} else {

    $sections["summary"] = $normalize($analysis);

}

/* =====================================================
   RESPONSE
===================================================== */

echo json_encode([

    "success" => true,

    "analysis" => $sections

]);

} catch(Throwable $e){

echo json_encode([

    "success" => false,

    "error" => $e->getMessage(),

    "line" => $e->getLine()

]);

}
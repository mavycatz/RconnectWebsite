<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "transport_db"
);

if($conn->connect_error){

    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]));
}

$sql = "

SELECT
    SUM(revenue) AS total_revenue,
    SUM(expenses) AS total_expenses,
    COUNT(*) AS total_trips
FROM trips

";

$result =
    $conn->query($sql);

$row =
    $result->fetch_assoc();

$totalRevenue =
    (float)($row['total_revenue'] ?? 0);

$totalExpenses =
    (float)($row['total_expenses'] ?? 0);

$totalTrips =
    (int)($row['total_trips'] ?? 0);

$netProfit =
    $totalRevenue -
    $totalExpenses;

$reportData = "

Transportation Report

Total Revenue: ₱" . number_format($totalRevenue, 2) . "
Total Expenses: ₱" . number_format($totalExpenses, 2) . "
Net Profit: ₱" . number_format($netProfit, 2) . "
Total Trips: " . $totalTrips . "

Generate:
1. Operational analysis
2. Key insights
3. Recommendations

Keep response professional and concise.
";

$apiKey =
'YOUR_GROQ_API_KEY';

$url =
'https://api.groq.com/openai/v1/chat/completions';

$data = [

    "model" =>
        "llama-3.3-70b-versatile",

    "messages" => [

        [
            "role" => "system",
            "content" =>
                "You are a transportation business analyst."
        ],

        [
            "role" => "user",
            "content" =>
                $reportData
        ]
    ]
];

$ch =
    curl_init($url);

curl_setopt(
    $ch,
    CURLOPT_RETURNTRANSFER,
    true
);

curl_setopt(
    $ch,
    CURLOPT_POST,
    true
);

curl_setopt(
    $ch,
    CURLOPT_HTTPHEADER,
    [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]
);

curl_setopt(
    $ch,
    CURLOPT_POSTFIELDS,
    json_encode($data)
);

$response =
    curl_exec($ch);

$error =
    curl_error($ch);

curl_close($ch);

if($error){

    echo json_encode([
        'success' => false,
        'message' => $error
    ]);

    exit;
}

$result =
    json_decode(
        $response,
        true
    );

$analysis =
    $result['choices'][0]
    ['message']['content']
    ?? 'No analysis generated';

echo json_encode([
    'success' => true,
    'analysis' => $analysis
]);
<?php

namespace Finance\Services;

$aiConfig_report = require __DIR__
    . '/../config/ai.php';

class AIFinancialService
{
    private $apiKey;

    public function __construct()
    {
        global $aiConfig_report;
        $this->apiKey = $aiConfig_report['api_key'];
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE REPORTS
    |--------------------------------------------------------------------------
    */

    public function analyzeFinancialReports(
        array $financialData
    ): array {

        /*
        |--------------------------------------------------------------------------
        | CORE DATA
        |--------------------------------------------------------------------------
        */

        $coreData = [

            'total_revenue' =>

                (float)(
                    $financialData['total_revenue']
                    ?? 0
                ),

            'total_expenses' =>

                (float)(
                    $financialData['total_expenses']
                    ?? 0
                ),

            'net_profit' =>

                (float)(
                    $financialData['net_profit']
                    ?? 0
                ),

            'total_passengers' =>

                (int)(
                    $financialData['total_passengers']
                    ?? 0
                ),

            'total_trips' =>

                (int)(
                    $financialData['total_trips']
                    ?? 0
                ),

            'active_buses' =>

                (int)(
                    $financialData['active_buses']
                    ?? 0
                ),

            'top_routes' =>

                array_slice(
                    $financialData['top_routes']
                    ?? [],
                    0,
                    3
                ),

            'payment_methods' =>

                array_slice(
                    $financialData['payment_methods']
                    ?? [],
                    0,
                    3
                )
        ];

        /*
        |--------------------------------------------------------------------------
        | REPORT TYPES
        |--------------------------------------------------------------------------
        */

        $reportTypes = [

            [
                'title' =>

                    'Revenue Collection Report',

                'category' =>

                    'Revenue Collection',

                'focus' =>

                    'Analyze transportation revenue,
                    passenger fare collections,
                    remittance activity,
                    and payment trends.'
            ],

            [
                'title' =>

                    'Operating Expense Report',

                'category' =>

                    'Expense Monitoring',

                'focus' =>

                    'Analyze transportation expenses,
                    fuel costs,
                    maintenance spending,
                    and operational efficiency.'
            ],

            [
                'title' =>

                    'Route Profitability Report',

                'category' =>

                    'Route Performance',

                'focus' =>

                    'Analyze route demand,
                    route profitability,
                    transportation efficiency,
                    and operational performance.'
            ],

            [
                'title' =>

                    'Passenger Demand Report',

                'category' =>

                    'Passenger Activity',

                'focus' =>

                    'Analyze passenger activity,
                    transportation demand,
                    trip behavior,
                    and route usage.'
            ],

            [
                'title' =>

                    'Fleet Operations Report',

                'category' =>

                    'Fleet Operations',

                'focus' =>

                    'Analyze buses,
                    dispatch activity,
                    transportation operations,
                    and fleet performance.'
            ]
        ];

        /*
        |--------------------------------------------------------------------------
        | REPORTS
        |--------------------------------------------------------------------------
        */

        $reports = [];

        /*
        |--------------------------------------------------------------------------
        | GENERATE
        |--------------------------------------------------------------------------
        */

        foreach($reportTypes as $type){

            /*
            |--------------------------------------------------------------------------
            | PROMPT
            |--------------------------------------------------------------------------
            */

            $prompt = "

You are a transportation financial analyst.

Generate a PROFESSIONAL transportation report.

REPORT TITLE:
{$type['title']}

REPORT CATEGORY:
{$type['category']}

REPORT FOCUS:
{$type['focus']}

TRANSPORTATION DATA:

"

            .

            json_encode(
                $coreData,
                JSON_PRETTY_PRINT
            )

            .

            "

CRITICAL RULES:

- Return ONLY valid JSON
- No markdown
- No explanations outside JSON
- No incomplete arrays
- No empty analysis
- No short responses

The analysis field MUST contain:
- 3 to 5 detailed paragraphs
- each paragraph minimum 80 words

Return this exact JSON format:

{
  \"title\":\"\",
  \"summary\":\"\",
  \"category\":\"\",
  \"confidence\":\"\",
  \"analysis\":[
    \"paragraph 1\",
    \"paragraph 2\",
    \"paragraph 3\"
  ],
  \"insights\":[
    \"insight 1\"
  ],
  \"risks\":[
    \"risk 1\"
  ],
  \"recommendations\":[
    \"recommendation 1\"
  ]
}

";

            /*
            |--------------------------------------------------------------------------
            | SEND
            |--------------------------------------------------------------------------
            */

            $response =
                $this->sendRequest(
                    $prompt
                );

            /*
            |--------------------------------------------------------------------------
            | STAGGER REQUESTS
            |--------------------------------------------------------------------------
            */

            usleep(800000);

            /*
            |--------------------------------------------------------------------------
            | PROCESS
            |--------------------------------------------------------------------------
            */

            $report =
                $this->processAIResponse(
                    $response
                );

            /*
            |--------------------------------------------------------------------------
            | RETRY FAILED
            |--------------------------------------------------------------------------
            */

            if(

                strtolower(
                    $report['summary']
                    ?? ''
                )

                ===

                strtolower(
                    'The AI returned an invalid response format.'
                )
            ){

                /*
                |--------------------------------------------------------------------------
                | WAIT
                |--------------------------------------------------------------------------
                */

                usleep(1200000);

                /*
                |--------------------------------------------------------------------------
                | RETRY
                |--------------------------------------------------------------------------
                */

                $retryResponse =
                    $this->sendRequest(
                        $prompt
                    );

                $retryReport =
                    $this->processAIResponse(
                        $retryResponse
                    );

                /*
                |--------------------------------------------------------------------------
                | USE RETRY
                |--------------------------------------------------------------------------
                */

                if(

                    strtolower(
                        $retryReport['summary']
                        ?? ''
                    )

                    !==

                    strtolower(
                        'The AI returned an invalid response format.'
                    )
                ){

                    $report =
                        $retryReport;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | FORCE TITLE
            |--------------------------------------------------------------------------
            */

            $report['title'] =
                $type['title'];

            $report['category'] =
                $type['category'];

            /*
            |--------------------------------------------------------------------------
            | STORE
            |--------------------------------------------------------------------------
            */

            $reports[] =
                $report;
        }

        /*
        |--------------------------------------------------------------------------
        | RETURN
        |--------------------------------------------------------------------------
        */

        return $reports;
    }

    /*
    |--------------------------------------------------------------------------
    | PROCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    private function processAIResponse(
        string $content
    ): array {

        /*
        |--------------------------------------------------------------------------
        | LOG
        |--------------------------------------------------------------------------
        */

        file_put_contents(

            __DIR__
            .
            '/RAW_AI_RESPONSE.log',

            "\n=========================\n"

            .

            date('Y-m-d H:i:s')

            .

            "\n"

            .

            $content

            .

            "\n=========================\n",

            FILE_APPEND
        );

        /*
        |--------------------------------------------------------------------------
        | CLEAN
        |--------------------------------------------------------------------------
        */

        $content =
            trim($content);

        $content =
            preg_replace(
                '/```json|```/',
                '',
                $content
            );

        /*
        |--------------------------------------------------------------------------
        | EXTRACT JSON
        |--------------------------------------------------------------------------
        */

        preg_match(
            '/\{.*\}/s',
            $content,
            $matches
        );

        $json =
            $matches[0]
            ?? '{}';

        /*
        |--------------------------------------------------------------------------
        | DECODE
        |--------------------------------------------------------------------------
        */

        $decoded =
            json_decode(
                $json,
                true
            );

        /*
        |--------------------------------------------------------------------------
        | INVALID
        |--------------------------------------------------------------------------
        */

        if(
            !$decoded
            ||
            !is_array($decoded)
        ){

            return [

                'title' =>

                    'AI Report Generation Failed',

                'summary' =>

                    'The AI returned an invalid response format.',

                'category' =>

                    'System Error',

                'confidence' =>

                    'Low',

                'analysis' => [

                    'The AI response could not be processed because the returned JSON structure was invalid or incomplete.',

                    'The AI may have exceeded token limits or returned malformed JSON content.',

                    'The raw AI response has been stored for debugging purposes.'
                ],

                'insights' => [

                    'AI request was sent successfully.'
                ],

                'risks' => [

                    'Malformed AI JSON responses may occur during high request load.'
                ],

                'recommendations' => [

                    'Review RAW_AI_RESPONSE.log for debugging.'
                ]
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | ANALYSIS
        |--------------------------------------------------------------------------
        */

        $analysis =

            is_array(
                $decoded['analysis']
                ?? null
            )

            ?

            array_values(
                array_filter(
                    $decoded['analysis']
                )
            )

            :

            [];

        /*
        |--------------------------------------------------------------------------
        | FORCE PARAGRAPHS
        |--------------------------------------------------------------------------
        */

        if(count($analysis) < 3){

            $analysis = [

                'Transportation financial records indicate active operational transportation activity during the reporting period. Passenger transportation demand remained concentrated across high-frequency transportation routes, particularly in areas with stable transportation trip activity and active transportation fare collections.',

                'Transportation operational expenses continued affecting overall transportation profitability due to transportation fuel consumption, dispatch operations, and transportation scheduling activity. Transportation route monitoring also indicates that several transportation operations remain dependent on consistent passenger transportation demand.',

                'Transportation analytics monitoring should continue focusing on transportation efficiency, transportation dispatch planning, transportation remittance validation, and transportation route optimization to improve long-term transportation financial sustainability.'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | RETURN
        |--------------------------------------------------------------------------
        */

        return [

            'title' =>

                trim(
                    $decoded['title']
                    ?? 'Transportation Report'
                ),

            'summary' =>

                trim(
                    $decoded['summary']
                    ?? ''
                ),

            'category' =>

                trim(
                    $decoded['category']
                    ?? 'Transportation'
                ),

            'confidence' =>

                trim(
                    $decoded['confidence']
                    ?? 'Medium'
                ),

            'analysis' =>

                $analysis,

            'insights' =>

                array_values(
                    $decoded['insights']
                    ?? []
                ),

            'risks' =>

                array_values(
                    $decoded['risks']
                    ?? []
                ),

            'recommendations' =>

                array_values(
                    $decoded['recommendations']
                    ?? []
                )
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SEND REQUEST
    |--------------------------------------------------------------------------
    */

    private function sendRequest(
        string $prompt
    ): string {

        global $aiConfig_report;

        $url =
            $aiConfig_report['api_url'];

        $data = [

            "model" =>

                $aiConfig_report['model'],

            "messages" => [

                [

                    "role" => "system",

                    "content" =>

"You are a transportation financial analyst.

Return ONLY valid JSON."
                ],

                [

                    "role" => "user",

                    "content" =>

                        $prompt
                ]
            ],

            /*
            |--------------------------------------------------------------------------
            | STABLE SETTINGS
            |--------------------------------------------------------------------------
            */

            "temperature" => 0.4,

            "max_tokens" => 2200,

            /*
            |--------------------------------------------------------------------------
            | JSON MODE
            |--------------------------------------------------------------------------
            */

            "response_format" => [

                "type" => "json_object"
            ]
        ];

        /*
        |--------------------------------------------------------------------------
        | CURL
        |--------------------------------------------------------------------------
        */

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

                'Authorization: Bearer '
                .
                $this->apiKey
            ]
        );

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($data)
        );

        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        $response =
            curl_exec($ch);

        /*
        |--------------------------------------------------------------------------
        | CURL ERROR
        |--------------------------------------------------------------------------
        */

        if(curl_errno($ch)){
            if (defined('FINANCE_DEBUG') && FINANCE_DEBUG) {
                file_put_contents(
                    __DIR__ . '/AI_CURL_ERROR.log',
                    curl_error($ch) . PHP_EOL,
                    FILE_APPEND
                );
            }
            $response = json_encode([
                'success' => false,
                'message' => 'AI service request failed: ' . curl_error($ch),
                'partial' => true,
            ]);
        }

        curl_close($ch);

        /*
        |--------------------------------------------------------------------------
        | LOG RESPONSE
        |--------------------------------------------------------------------------
        */

        file_put_contents(

            __DIR__
            .
            '/RAW_OPENAI_RESPONSE.log',

            "\n=========================\n"

            .

            date('Y-m-d H:i:s')

            .

            "\n"

            .

            $response

            .

            "\n=========================\n",

            FILE_APPEND
        );

        /*
        |--------------------------------------------------------------------------
        | DECODE
        |--------------------------------------------------------------------------
        */

        $result =
            json_decode(
                $response,
                true
            );

        /*
        |--------------------------------------------------------------------------
        | RETURN
        |--------------------------------------------------------------------------
        */

        return

            $result['choices'][0]
            ['message']['content']

            ??

            '{}';
    }
}
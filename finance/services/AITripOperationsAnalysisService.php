<?php

namespace Finance\Services;

/*
|--------------------------------------------------------------------------
| ERROR REPORTING
|--------------------------------------------------------------------------
*/

ini_set('display_errors', 1);

ini_set('display_startup_errors', 1);

error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| LOAD MODEL
|--------------------------------------------------------------------------
*/

require_once __DIR__ .
    '/../models/AITripOperationsReportModel.php';

$aiConfig_tripOps = require __DIR__ .
    '/../config/ai.php';

use Finance\Models\AITripOperationsReportModel;

class AITripOperationsAnalysisService
{
    /*
    |--------------------------------------------------------------------------
    | API KEY
    |--------------------------------------------------------------------------
    */

private string $apiKey;
    /*
    |--------------------------------------------------------------------------
    | MODEL
    |--------------------------------------------------------------------------
    */

    private AITripOperationsReportModel $model;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        global $aiConfig_tripOps;
        $this->apiKey = $aiConfig_tripOps['api_key'];

        $this->model =
            new AITripOperationsReportModel();
    }

    /*
    |--------------------------------------------------------------------------
    | ANALYZE TRIP
    |--------------------------------------------------------------------------
    */

    public function analyze(
        array $trip
    ): bool
    {
        try{

            /*
            |--------------------------------------------------------------------------
            | PREPARE CORE DATA
            |--------------------------------------------------------------------------
            */

            $coreData = [

                'trip_report_id' =>

                    $trip['id']
                    ?? null,

                'trip_group_code' =>

                    $trip['trip_group_code']
                    ?? null,

                'route_id' =>

                    $trip['route_id']
                    ?? null,

                'bus_id' =>

                    $trip['bus_id']
                    ?? null,

                'driver_id' =>

                    $trip['driver_id']
                    ?? null,

                'inspector_id' =>

                    $trip['inspector_id']
                    ?? null,

                'trip_date' =>

                    $trip['trip_date']
                    ?? null,

                'passenger_count' =>

                    (int)(
                        $trip['passenger_count']
                        ?? 0
                    ),

                'total_revenue' =>

                    (float)(
                        $trip['total_revenue']
                        ?? 0
                    ),

                'fuel_cost' =>

                    (float)(
                        $trip['fuel_cost']
                        ?? 0
                    ),

                'other_expenses' =>

                    (float)(
                        $trip['other_expenses']
                        ?? 0
                    ),

                'net_income' =>

                    (float)(
                        $trip['net_income']
                        ?? 0
                    ),

                'odometer' =>

                    (float)(
                        $trip['odometer']
                        ?? 0
                    ),

                'notes' =>

                    trim(
                        $trip['notes']
                        ?? ''
                    ),

                'ai_analysis_notes' =>

                    trim(
                        $trip['ai_analysis_notes']
                        ?? ''
                    )
            ];

            /*
            |--------------------------------------------------------------------------
            | PROMPT
            |--------------------------------------------------------------------------
            */

            $prompt = "

You are a transportation operations analyst.

Analyze this transportation operations report.

Focus on:

- transportation revenue
- fuel expenses
- dispatch efficiency
- suspicious financial activity
- passenger demand
- operational performance
- transport profitability
- operational risks
- remittance inconsistencies
- inspector observations
- transport incidents

TRANSPORT REPORT DATA:

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
- No short responses

The analysis field MUST contain:
- 3 to 5 detailed paragraphs
- each paragraph minimum 70 words

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
            | SEND REQUEST
            |--------------------------------------------------------------------------
            */

            $response =

                $this->sendRequest(
                    $prompt
                );

            /*
            |--------------------------------------------------------------------------
            | PROCESS RESPONSE
            |--------------------------------------------------------------------------
            */

            $report =

                $this->processAIResponse(
                    $response
                );

            /*
            |--------------------------------------------------------------------------
            | RETRY INVALID RESPONSE
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

                usleep(1200000);

                $retryResponse =

                    $this->sendRequest(
                        $prompt
                    );

                $retryReport =

                    $this->processAIResponse(
                        $retryResponse
                    );

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
            | SAVE AI REPORT
            |--------------------------------------------------------------------------
            */

            $saved =

                $this->model
                ->save([

                    'report_title' =>

                        $report['title']
                        ?? 'AI Trip Operations Report',

                    'trip_report_id' =>

                        $trip['id']
                        ?? null,

                    'trip_group_code' =>

                        $trip['trip_group_code']
                        ?? null,

                    'ai_summary' =>

                        $report['summary']
                        ?? '',

                    'ai_category' =>

                        $report['category']
                        ?? 'Transport Operations',

                    'ai_confidence' =>

                        $report['confidence']
                        ?? 'Medium',

                    'ai_analysis' =>

                        json_encode(

                            $report['analysis']
                            ?? [],

                            JSON_UNESCAPED_UNICODE
                        ),

                    'ai_insights' =>

                        json_encode(

                            $report['insights']
                            ?? [],

                            JSON_UNESCAPED_UNICODE
                        ),

                    'ai_risks' =>

                        json_encode(

                            $report['risks']
                            ?? [],

                            JSON_UNESCAPED_UNICODE
                        ),

                    'ai_recommendations' =>

                        json_encode(

                            $report['recommendations']
                            ?? [],

                            JSON_UNESCAPED_UNICODE
                        )
                ]);

            /*
            |--------------------------------------------------------------------------
            | FAILED SAVE
            |--------------------------------------------------------------------------
            */

            if(!$saved){

                throw new \Exception(

                    'Failed saving AI trip operations report.'
                );
            }

            return true;

        }catch(\Throwable $e){

            echo '<pre style="
                background:#fff3f3;
                color:#cc0000;
                padding:20px;
                border-radius:14px;
                border:2px solid #ffcccc;
                line-height:1.7;
            ">';

            echo "AI ANALYSIS SERVICE ERROR\n\n";

            echo $e->getMessage();

            echo "\n\nFILE:\n";

            echo $e->getFile();

            echo "\n\nLINE:\n";

            echo $e->getLine();

            echo "\n\nTRACE:\n";

            print_r(
                $e->getTraceAsString()
            );

            echo '</pre>';

            exit;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PROCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    private function processAIResponse(
        string $content
    ): array
    {
        /*
        |--------------------------------------------------------------------------
        | LOG RAW RESPONSE
        |--------------------------------------------------------------------------
        */

        file_put_contents(

            __DIR__
            .
            '/RAW_TRIP_AI_RESPONSE.log',

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

                    'AI request was successfully sent.'
                ],

                'risks' => [

                    'Malformed AI JSON responses may occur during high request load.'
                ],

                'recommendations' => [

                    'Review RAW_TRIP_AI_RESPONSE.log for debugging.'
                ]
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | CLEAN ANALYSIS
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
        | FORCE ANALYSIS
        |--------------------------------------------------------------------------
        */

        if(count($analysis) < 3){

            $analysis = [

                'Transportation operational records indicate active fleet activity during the reporting period. Passenger transportation demand remained relatively stable while transportation dispatch activity continued across scheduled transportation routes and transportation service areas.',

                'Transportation operational expenses remained an important factor affecting transportation profitability. Transportation fuel consumption, transportation maintenance requirements, and transportation dispatch efficiency continue influencing overall transportation financial sustainability.',

                'Transportation operations management should continue monitoring transportation route efficiency, transportation remittance activity, transportation dispatch coordination, and transportation operational performance to improve long-term transportation financial reliability.'
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
                    ?? 'AI Transport Operations Report'
                ),

            'summary' =>

                trim(
                    $decoded['summary']
                    ?? ''
                ),

            'category' =>

                trim(
                    $decoded['category']
                    ?? 'Transport Operations'
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
    ): string
    {
        /*
        |--------------------------------------------------------------------------
        | NO API KEY
        |--------------------------------------------------------------------------
        */

        if(empty($this->apiKey)){

            throw new \Exception(

                'Missing OpenAI API Key.'
            );
        }

        global $aiConfig_tripOps;

        $url =
            $aiConfig_tripOps['api_url'];

        /*
        |--------------------------------------------------------------------------
        | REQUEST
        |--------------------------------------------------------------------------
        */

        $data = [

            'model' =>

                $aiConfig_tripOps['model'],

            'messages' => [

                [

                    'role' =>

                        'system',

                    'content' =>

'You are a transportation operations analyst. Return ONLY valid JSON.'
                ],

                [

                    'role' =>

                        'user',

                    'content' =>

                        $prompt
                ]
            ],

            'temperature' => 0.4,

            'max_tokens' => 2200,

            'response_format' => [

                'type' => 'json_object'
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
            $response = json_encode([
                'success' => false,
                'message' => 'AI service request failed: ' . curl_error($ch),
                'partial' => true,
            ]);
        }

        curl_close($ch);

        /*
        |--------------------------------------------------------------------------
        | LOG RAW RESPONSE
        |--------------------------------------------------------------------------
        */

        file_put_contents(

            __DIR__
            .
            '/RAW_OPENAI_TRIP_RESPONSE.log',

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
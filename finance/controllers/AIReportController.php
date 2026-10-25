<?php

namespace Finance\Controllers\AI;

require_once __DIR__
    . '/../../services/AI/AIAnalyticsService.php';

require_once __DIR__
    . '/../../services/AI/AISystemKnowledgeService.php';

require_once __DIR__
    . '/../../models/AI/AIAnalyticsModel.php';

require_once __DIR__
    . '/../../models/AI/AIChatHistoryModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/auth/Auth.php';

use Finance\Services\AI\AIAnalyticsService;
use Finance\Services\AI\AISystemKnowledgeService;
use Finance\Models\AI\AIAnalyticsModel;
use Finance\Models\AI\AIChatHistoryModel;
use App\Auth\Auth;

class AIChatController
{
    private AIAnalyticsService
        $aiService;

    private AIAnalyticsModel
        $analyticsModel;

    private AIChatHistoryModel
        $chatHistoryModel;

    private AISystemKnowledgeService
        $systemKnowledge;

    public function __construct()
    {
        /*
        |--------------------------------------------------------------------------
        | SERVICES
        |--------------------------------------------------------------------------
        */

        $this->aiService =
            new AIAnalyticsService();

        $this->systemKnowledge =
            new AISystemKnowledgeService();

        /*
        |--------------------------------------------------------------------------
        | MODELS
        |--------------------------------------------------------------------------
        */

        $this->analyticsModel =
            new AIAnalyticsModel();

        $this->chatHistoryModel =
            new AIChatHistoryModel();
    }

    /*
    |--------------------------------------------------------------------------
    | AI CHAT
    |--------------------------------------------------------------------------
    */

    public function chat(

        $sessionId,
        $message

    ){

        try{

            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            $message =
                trim($message);

            if(empty($message)){

                return [

                    'success' => false,

                    'content' =>

                        'Please enter a message.'

                ];
            }

            /*
            |--------------------------------------------------------------------------
            | SYSTEM KNOWLEDGE / GUIDANCE
            |--------------------------------------------------------------------------
            |
            | Detected BEFORE any database work: guidance keywords
            | (remittance, revenue, fuel...) overlap data keywords, so
            | "where/how" questions must be answered from system
            | knowledge without touching the database.
            |
            */

            $guide =
                $this->systemKnowledge
                    ->detectGuidance(
                        $message
                    );

            /*
            |--------------------------------------------------------------------------
            | PURE GUIDANCE: NO DATABASE, NO AI
            |--------------------------------------------------------------------------
            */

            if(
                !empty($guide)
                &&
                !$this->systemKnowledge
                    ->isDataQuestion(
                        $message
                    )
                &&
                !$this->isGreeting($message)
            ){

                $content =
                    $this->systemKnowledge
                        ->buildAnswer(
                            $guide
                        );

                $result =
                    $this->aiService
                        ->saveGuidanceMessage(

                            $sessionId,

                            Auth::userId(),

                            $message,

                            $content
                        );

                return [

                    'success' => true,

                    'session_id' =>

                        $result['session_id']
                        ?? null,

                    'title' =>
                        'System Guide',

                    'type' =>
                        'system_guidance',

                    'content' =>

                        $result['content']
                        ?? $content
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | SMART DATA ROUTER
            |--------------------------------------------------------------------------
            */

            $transportationData =

                $this->getRelevantData(
                    $sessionId,
                    $message
                );

            /*
            |--------------------------------------------------------------------------
            | GENERATE AI RESPONSE
            |--------------------------------------------------------------------------
            */

            $result =

                $this->aiService
                    ->generateAIResponse(

                        $sessionId,

                        $message,

                        $transportationData

                    );

            /*
            |--------------------------------------------------------------------------
            | APPEND NAVIGATION ACTION (DATA + GUIDANCE)
            |--------------------------------------------------------------------------
            */

            if(!empty($guide)){

                $page =
                    $guide['page'];

                if(
                    AISystemKnowledgeService
                        ::canAccess($page)
                ){

                    $result['content'] .=

                        "\n\n"
                        .
                        $this->systemKnowledge
                            ->navButton($page);

                    if(
                        !empty($page['managerOnly'])
                        &&
                        Auth::role() !== 'finance_manager'
                    ){

                        $result['content'] .=

                            "\n\n"
                            .
                            'Note: The **generate/export** action on this page requires a **Finance Manager** account.';
                    }

                }else{

                    $result['content'] .=

                        "\n\n"
                        .
                        '**Note:** '
                        .
                        $page['name']
                        .
                        ' is available under the Finance module, but your current account does not have permission to access that section.';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | RETURN RESPONSE
            |--------------------------------------------------------------------------
            */

            return [

                'success' => true,

                'session_id' =>

                    $result['session_id']
                    ?? null,

                'title' =>

                    $result['title']
                    ?? 'AI Transportation Analytics',

                'type' =>

                    $result['type']
                    ?? 'general_chat',

                'content' =>

                    $result['content']
                    ?? 'No AI response generated.'

            ];

        }catch(\Throwable $e){

            return [

                'success' => false,

                'content' =>

                    'AI system error: '

                    .

                    $e->getMessage()

            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SMART DATA ROUTER
    |--------------------------------------------------------------------------
    */

    private function getRelevantData(
        $sessionId,
        $message
    ){

        /*
        |--------------------------------------------------------------------------
        | DIRECT DATA KEYWORDS
        |--------------------------------------------------------------------------
        */

        $dataKeys =
            $this->detectDataKeys(
                $message
            );

        if(!empty($dataKeys)){

            return
                $this->analyticsModel
                    ->getAIAnalyticsData(
                        $dataKeys
                    );
        }

        /*
        |--------------------------------------------------------------------------
        | GREETING / CONVERSATIONAL
        |--------------------------------------------------------------------------
        */

        if($this->isGreeting($message)){

            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | FOLLOW-UP: REUSE PREVIOUS CONVERSATION TOPIC
        |--------------------------------------------------------------------------
        */

        $historyKeys =
            $this->detectFollowUpKeys(
                $sessionId,
                $message
            );

        if(!empty($historyKeys)){

            return
                $this->analyticsModel
                    ->getAIAnalyticsData(
                        $historyKeys
                    );
        }

        /*
        |--------------------------------------------------------------------------
        | UNKNOWN INTENT: NO DATABASE QUERY
        |--------------------------------------------------------------------------
        */

        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT DATA KEYWORDS
    |--------------------------------------------------------------------------
    */

    private function detectDataKeys(
        $message
    ){

        $text =
            ' '
            .
            strtolower(
                preg_replace(
                    '/[^a-z0-9\s]/i',
                    ' ',
                    $message
                )
            )
            .
            ' ';

        $topics = [

            'driver' => [
                'driver', 'drivers', 'performance ranking',
                'top performer', 'best driver', 'highest'
            ],

            'revenue' => [
                'revenue', 'income', 'earned', 'earnings',
                'profit', 'gross', 'total sales'
            ],

            'trip' => [
                'trip', 'trips', 'rides', 'operational'
            ],

            'passenger' => [
                'passenger', 'passengers', 'riders', 'commuters'
            ],

            'payment' => [
                'payment', 'payments', 'cash', 'gcash',
                'collectibles', 'collectible'
            ],

            'route' => [
                'route', 'routes', 'destination',
                'boarding', 'origin'
            ],

            'bus' => [
                'bus', 'buses', 'vehicle', 'vehicles'
            ],

            'fare' => [
                'fare', 'fares', 'ticket price'
            ],

            'remittance' => [
                'remittance', 'remittances', 'remitted',
                'remit'
            ],

            'expense' => [
                'expense', 'expenses', 'fuel', 'cost',
                'spending', 'operating cost'
            ],

            'dispatch' => [
                'dispatch', 'dispatches', 'dispatched'
            ],

            'report' => [
                'report', 'reports', 'analytics',
                'summary', 'overview'
            ],

            'compare' => [
                'compare', 'comparison', 'versus', 'vs'
            ],

            'discount' => [
                'discount', 'discounts', 'senior',
                'pwd', 'student'
            ],

            'history' => [
                'historical', 'history', 'trend', 'trends',
                'past', 'previous', 'last year', 'last month',
                'yearly', 'monthly', 'annually',
                '2020', '2021', '2022', '2023',
                '2024', '2025', '2026', '2027',
                'year', 'years', 'month', 'months'
            ]
        ];

        $topicKeys = [

            'driver' => [
                'driver_performance',
                'driver_summary'
            ],

            'revenue' => [
                'trip_summary',
                'monthly_breakdown',
                'historical_yearly',
                'historical_monthly',
                'payment_summary'
            ],

            'trip' => [
                'trip_summary',
                'latest_trips',
                'historical_yearly',
                'historical_monthly',
                'historical_daily',
                'bus_trip_summary'
            ],

            'passenger' => [
                'passenger_count',
                'historical_passenger_types',
                'historical_summary',
                'trip_summary',
                'historical_daily',
                'historical_monthly'
            ],

            'payment' => [
                'payment_summary',
                'payment_status',
                'historical_payment_methods'
            ],

            'route' => [
                'route_summary',
                'historical_routes_yearly'
            ],

            'bus' => [
                'bus_summary',
                'bus_trip_summary',
                'historical_bus_yearly',
                'latest_bus_trips'
            ],

            'fare' => [
                'trip_summary',
                'historical_summary',
                'historical_passenger_types'
            ],

            'remittance' => [
                'remittance_summary',
                'latest_remittances'
            ],

            'expense' => [
                'expense_summary',
                'fuel_prices'
            ],

            'dispatch' => [
                'latest_dispatches'
            ],

            'report' => [
                'latest_financial_reports'
            ],

            'compare' => [
                'driver_performance',
                'historical_yearly',
                'historical_monthly'
            ],

            'discount' => [
                'historical_discounts'
            ],

            'history' => [
                'historical_yearly',
                'historical_monthly',
                'historical_daily',
                'historical_bus_yearly',
                'historical_routes_yearly'
            ]
        ];

        $matched = [];

        foreach($topics as $topic => $keywords){

            foreach($keywords as $keyword){

                if(
                    strpos(
                        $text,
                        ' '
                        .
                        $keyword
                        .
                        ' '
                    ) !== false
                ){

                    $matched[] =
                        $topic;

                    break;
                }
            }
        }

        if(empty($matched)){

            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | BUILD UNIQUE KEY LIST
        |--------------------------------------------------------------------------
        */

        $keys = [];

        foreach($matched as $topic){

            foreach($topicKeys[$topic] as $key){

                if(!in_array($key, $keys, true)){

                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT GREETING / CONVERSATIONAL
    |--------------------------------------------------------------------------
    */

    private function isGreeting(
        $message
    ){

        $text =
            strtolower(
                preg_replace(
                    '/[^a-z0-9\s]/i',
                    ' ',
                    $message
                )
            );

        return
            preg_match(
                '/
                    \b(hello|hi|hey|greetings)\b
                    |
                    \bgood\s*(morning|afternoon|evening|day|night)\b
                    |
                    \bhow\s+are\s+you\b
                    |
                    \bhow\s+is\s+it\s+going\b
                    |
                    \bwho\s+are\s+you\b
                    |
                    \bwhat\s+can\s+you\s+do\b
                    |
                    \bwhat\s+do\s+you\s+do\b
                    |
                    \bthank\s*you\b
                    |
                    \bthanks?\b
                    |
                    \bgoodbye\b
                    |
                    \bb?ye\b
                    |
                    \b(kumusta|kamusta|musta|salamat)\b
                /ix',
                $text
            ) === 1;
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT FOLLOW-UP KEYS FROM SESSION HISTORY
    |--------------------------------------------------------------------------
    */

    private function detectFollowUpKeys(
        $sessionId,
        $message
    ){

        $recent =
            $this->chatHistoryModel
                ->getChats(
                    $sessionId,
                    6
                );

        if(empty($recent)){

            return [];
        }

        $context =
            $message;

        foreach($recent as $chat){

            $context .=
                ' '
                .
                ($chat['user_message'] ?? '');
        }

        return
            $this->detectDataKeys(
                $context
            );
    }
}
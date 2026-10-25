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

    /*
    |--------------------------------------------------------------------------
    | DATA TOPIC -> FINANCE PAGE MAP
    |--------------------------------------------------------------------------
    |
    | Maps the smart data router topics to the real Finance pages that
    | cover them. Used for follow-up resolution and the conversation
    | topic sent to the AI. Topics without a page are left unmapped.
    |
    */

    private array $topicPageMap = [
        'remittance' => 'remittance',
        'revenue' => 'revenue',
        'expense' => 'fuel',
        'payment' => 'online-payment-verification',
        'report' => 'financial-reports',
        'bus' => 'fleet-performance',
        'route' => 'fleet-performance'
    ];

    /*
    |--------------------------------------------------------------------------
    | DATA TOPIC LABELS
    |--------------------------------------------------------------------------
    */

    private array $topicLabels = [
        'driver' => 'Driver Performance',
        'revenue' => 'Revenue Analysis',
        'trip' => 'Trip Overview',
        'passenger' => 'Passenger Statistics',
        'payment' => 'Payment Analysis',
        'route' => 'Route Analysis',
        'bus' => 'Bus Performance',
        'fare' => 'Fare Information',
        'remittance' => 'Remittance Report',
        'expense' => 'Fuel Costs',
        'dispatch' => 'Dispatch Report',
        'report' => 'Operations Report',
        'compare' => 'Performance Comparison',
        'discount' => 'Passenger Discounts',
        'history' => 'Historical Data'
    ];

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
        $message,
        $pageContext = null

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
            | REPEAT REQUEST: "AGAIN"
            |--------------------------------------------------------------------------
            |
            | Repeats the immediately previous meaningful answer from
            | the conversation. Handled before any guidance detection so
            | it can never fall back to an older topic or a new query.
            |
            */

            $repeat =
                $this->detectRepeatRequest(
                    $sessionId,
                    $message
                );

            if(!empty($repeat)){

                return $repeat;
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

            $isFollowUp =
                false;

            if(empty($guide)){

                $guide =
                    $this->detectFollowUpGuidance(
                        $sessionId,
                        $message
                    );

                $isFollowUp =
                    !empty($guide);
            }

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

                    $isFollowUp
                        ?
                        $this->systemKnowledge
                            ->buildFollowUpAnswer(
                                $message,
                                $guide
                            )
                        :
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
                    $message,
                    $pageContext
                );

            /*
            |--------------------------------------------------------------------------
            | GENERATE AI RESPONSE
            |--------------------------------------------------------------------------
            */

            $topicContext =
                $this->getTopicContext(
                    $sessionId,
                    $message
                );

            $result =

                $this->aiService
                    ->generateAIResponse(

                        $sessionId,

                        $message,

                        $transportationData,

                        $topicContext,

                        $pageContext

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
        $message,
        $pageContext = null
    ){

        /*
        |--------------------------------------------------------------------------
        | PAGE FILTERS -> SAME BUSINESS LOGIC AS THE PAGE
        |--------------------------------------------------------------------------
        |
        | Forward the current page's date/period filters to the analytics
        | model so the database answers use exactly the same period and
        | aggregation the page shows. This keeps the AI's fallback figures
        | (e.g. driver trip counts) consistent with the widget on screen.
        |
        */

        $filters =
            $pageContext
            ?
            (array)($pageContext['filters'] ?? [])
            :
            [];

        $filters = array_filter($filters, function($v){
            return $v !== '' && $v !== null;
        });

        /*
        |--------------------------------------------------------------------------
        | EXPLICIT PERIOD IN THE MESSAGE ("today", "2023", "March 2023")
        |--------------------------------------------------------------------------
        |
        | When the user names a specific period in the question, the database
        | fallback must use that period with the SAME business logic as the
        | page (driver_performance is routed through the shared
        | DriverPerformanceService). "today" uses the application timezone
        | (Asia/Manila), matching the page definition.
        |
        */

        $filters = $this->resolveMessagePeriod($message, $filters);

        /*
        |--------------------------------------------------------------------------
        | DIRECT DATA KEYWORDS
        |--------------------------------------------------------------------------
        |
        | A qualifier-only message ("what about 2023?", "and 2025?") has
        | year/period words but no topic of its own; it must NOT start a
        | generic history query. It falls through to the follow-up merge
        | so the previous topic's keys are reused.
        |
        */

        $dataKeys =
            $this->detectDataKeys(
                $message
            );

        /*
        |--------------------------------------------------------------------------
        | PERIOD FOLLOW-UP ON A DRIVER TOPIC KEEPS driver_performance
        |--------------------------------------------------------------------------
        |
        | "How about 2023?" right after a driver question would otherwise only
        | fetch the generic history keys. driver_performance is the canonical
        | page-logic source for the resolved period, so re-add it when the
        | recent conversation was discussing drivers.
        |
        */

        $hasExplicitPeriod =
            (bool)preg_match('/\b(20\d{2})\b/', $message)
            ||
            stripos($message, 'today') !== false
            ||
            stripos($message, 'this month') !== false
            ||
            stripos($message, 'current month') !== false
            ||
            stripos($message, 'this year') !== false
            ||
            stripos($message, 'current year') !== false;

        if(
            $hasExplicitPeriod
            &&
            !in_array('driver_performance', $dataKeys, true)
        ){

            $prevDidDiscussDrivers =
                false;

            $recent =
                $this->chatHistoryModel
                    ->getChats(
                        $sessionId,
                        3
                    );

            foreach($recent as $chat){

                $prevKeys =
                    $this->detectDataKeys(
                        $chat['user_message']
                        ?? ''
                    );

                if(
                    in_array(
                        'driver_performance',
                        $prevKeys,
                        true
                    )
                ){

                    $prevDidDiscussDrivers =
                        true;

                    break;
                }
            }

            if($prevDidDiscussDrivers){

                $dataKeys[] =
                    'driver_performance';
            }
        }

        if(
            !empty($dataKeys)
            &&
            !$this->isQualifierOnly($dataKeys)
        ){

            return
                $this->analyticsModel
                    ->getAIAnalyticsData(
                        $dataKeys,
                        $filters
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
                $message,
                $dataKeys
            );

        if(!empty($historyKeys)){

            return
                $this->analyticsModel
                    ->getAIAnalyticsData(
                        $historyKeys,
                        $filters
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
    | RESOLVE EXPLICIT PERIOD FROM THE MESSAGE
    |--------------------------------------------------------------------------
    |
    | Maps explicit period words/values in the user message onto the same
    | filter keys the page uses (month, year, fromDate, toDate, range).
    | Explicit "today" / "this month" / "this year" / a year like "2023"
    | override the page period for the DATABASE fallback query, so the AI
    | answers "highest trips today" or "high driver in 2023" with the same
    | business logic the page applies to that period - never an all-time or
    | different dataset. "today" is resolved in Asia/Manila (application TZ).
    |
    */

    private function resolveMessagePeriod($message, array $filters): array
    {
        if (!is_string($message) || trim($message) === '') {
            return $filters;
        }

        date_default_timezone_set('Asia/Manila');

        $text =
            ' '
            .
            strtolower(
                preg_replace(
                    '/[^a-z0-9\/\-\s]/i',
                    ' ',
                    $message
                )
            )
            .
            ' ';

        $text = preg_replace('/\s+/', ' ', $text);

        $today = date('Y-m-d');

        /*
        | TODAY
        */

        if (strpos($text, ' today ') !== false) {
            if (
                !empty($filters['fromDate'])
                &&
                !empty($filters['toDate'])
            ){
                return $filters;
            }

            unset($filters['month'], $filters['year']);
            $filters['range']    = 'today';
            $filters['fromDate'] = $today;
            $filters['toDate']   = $today;

            return $filters;
        }

        /*
        | THIS MONTH / THIS YEAR
        */

        if (
            strpos($text, ' this month ') !== false
            ||
            strpos($text, ' current month ') !== false
        ){
            unset($filters['fromDate'], $filters['toDate']);
            $filters['range'] = 'month';
            $filters['month'] = (string)date('n');
            $filters['year']  = (string)date('Y');

            return $filters;
        }

        if (
            strpos($text, ' this year ') !== false
            ||
            strpos($text, ' current year ') !== false
        ){
            unset($filters['fromDate'], $filters['toDate'], $filters['month']);
            $filters['range'] = 'year';
            $filters['year']  = (string)date('Y');

            return $filters;
        }

        /*
        | EXPLICIT YEAR ("2023", "March 2023")
        */

        if (preg_match('/\b(20\d{2})\b/', $text, $m)) {
            $year = (int)$m[1];

            $months = [
                'january' => 1, 'jan' => 1,
                'february' => 2, 'feb' => 2,
                'march' => 3, 'mar' => 3,
                'april' => 4, 'apr' => 4,
                'may' => 5,
                'june' => 6, 'jun' => 6,
                'july' => 7, 'jul' => 7,
                'august' => 8, 'aug' => 8,
                'september' => 9, 'sep' => 9, 'sept' => 9,
                'october' => 10, 'oct' => 10,
                'november' => 11, 'nov' => 11,
                'december' => 12, 'dec' => 12
            ];

            $month = null;

            foreach ($months as $name => $mn) {
                if (strpos($text, ' ' . $name . ' ') !== false) {
                    $month = $mn;
                    break;
                }
            }

            unset($filters['fromDate'], $filters['toDate']);

            if ($month !== null) {
                $filters['range'] = 'month';
                $filters['month'] = (string)$month;
                $filters['year']  = (string)$year;
            } else {
                $filters['range'] = 'year';
                unset($filters['month']);
                $filters['year'] = (string)$year;
            }

            return $filters;
        }

        return $filters;
    }

    /*
    |--------------------------------------------------------------------------
    | IS QUALIFIER-ONLY MESSAGE
    |--------------------------------------------------------------------------
    |
    | True when the only matched data topic is 'history', meaning the
    | message carries a year/month/period qualifier ("what about 2023?",
    | "and 2025?", "what about August?") but no new topic of its own.
    |
    */

    private function isQualifierOnly(
        array $keys
    ): bool
    {
        return
            count($keys) === 1
            &&
            ($keys[0] ?? '') === 'history';
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
                'driver_performance',
                'trip_summary',
                'monthly_breakdown',
                'payment_summary',
                'payment_status',
                'route_summary',
                'remittance_summary',
                'latest_remittances',
                'expense_summary',
                'bus_summary',
                'latest_dispatches'
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
        $message,
        array $currentKeys = []
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

        /*
        |--------------------------------------------------------------------------
        | QUALIFIER-ONLY: "WHAT ABOUT 2023?" -> PREVIOUS TOPIC + PERIOD
        |--------------------------------------------------------------------------
        |
        | Reuse the previous user message's topic keys (e.g. driver
        | performance) and merge the year/period history keys, so the AI
        | answers "Who was the highest performing driver in 2023?"
        | instead of a generic yearly summary.
        |
        */

        if($this->isQualifierOnly($currentKeys)){

            foreach($recent as $chat){

                $keys =
                    $this->detectDataKeys(
                        $chat['user_message']
                        ?? ''
                    );

                if(!empty($keys)){

                    foreach($currentKeys as $key){

                        if(
                            !in_array(
                                $key,
                                $keys,
                                true
                            )
                        ){

                            $keys[] = $key;
                        }
                    }

                    return $keys;
                }
            }

            return $currentKeys;
        }

        /*
        |--------------------------------------------------------------------------
        | NO TOPIC OF ITS OWN: "HOW MUCH?" -> PREVIOUS TOPIC
        |--------------------------------------------------------------------------
        */

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

    /*
    |--------------------------------------------------------------------------
    | DETECT REPEAT REQUEST ("AGAIN")
    |--------------------------------------------------------------------------
    |
    | "again" repeats the immediately previous meaningful answer from
    | the chat history - never an older topic, never a new database
    | query. The repeat is intentionally NOT stored back into the
    | history, so a later "what about 2023?" still merges against the
    | ORIGINAL exchange, and "again again" repeats the same original
    | answer.
    |
    */

    private function detectRepeatRequest(
        $sessionId,
        $message
    ){

        if(
            empty($sessionId)
            ||
            !$this->systemKnowledge
                ->isRepeatRequest(
                    $message
                )
        ){

            return null;
        }

        $recent =
            $this->chatHistoryModel
                ->getChats(
                    $sessionId,
                    1
                );

        if(
            empty($recent)
            ||
            empty(
                trim(
                    $recent[0]['ai_response']
                    ?? ''
                )
            )
        ){

            return null;
        }

        return [

            'success' => true,

            'session_id' =>
                $recent[0]['session_id']
                ?? null,

            'title' =>
                'System Guide',

            'type' =>
                'system_guidance',

            'content' =>
                $recent[0]['ai_response']
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT FOLLOW-UP GUIDANCE FROM SESSION HISTORY
    |--------------------------------------------------------------------------
    |
    | "Can you give me a link?" after "Where can I see the remittance?"
    | refers to the Remittance page. Reference-style messages that carry
    | no new topic and no data request are resolved against the most
    | recent guidance topic in the conversation, so Riza never asks
    | "which link?" when the destination is obvious.
    |
    */

    private function detectFollowUpGuidance(
        $sessionId,
        $message
    ){

        /*
        |--------------------------------------------------------------------------
        | MUST REFER BACK TO A PREVIOUS TOPIC
        |--------------------------------------------------------------------------
        */

        if(
            !$this->systemKnowledge
                ->isFollowUpReference(
                    $message
                )
        ){

            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | DATA / GREETING / NEW-TOPIC MESSAGES ARE NOT FOLLOW-UPS
        |--------------------------------------------------------------------------
        */

        if(
            $this->systemKnowledge
                ->isDataQuestion(
                    $message
                )
        ){

            return null;
        }

        if($this->isGreeting($message)){

            return null;
        }

        if(!empty($this->detectDataKeys($message))){

            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | EXPLICIT NEW DESTINATION OVERRIDES THE PREVIOUS TOPIC
        |--------------------------------------------------------------------------
        |
        | "What about on dashboard?" after a Remittance discussion names
        | a NEW destination. The answer must be built for the Dashboard,
        | not the previous topic. Only reaches this point when the
        | message carries no data topic of its own.
        |
        */

        $explicit =
            $this->systemKnowledge
                ->detectExplicitFeature(
                    $message
                );

        if(!empty($explicit)){

            return [
                'key' =>
                    $explicit['key'],
                'intent' =>
                    'where',
                'page' =>
                    $explicit['page']
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | "WHAT ABOUT X" WITH NO REFERENCE IS A NEW TOPIC, NOT A FOLLOW-UP
        |--------------------------------------------------------------------------
        |
        | "What about the schedule?" does not name a Finance feature and
        | does not refer back with a pronoun. It is a new question for
        | the database router, not a reference to the previous topic.
        | "What about it?" keeps the pronoun and stays a follow-up.
        |
        */

        $text = ' '
            . strtolower(
                preg_replace(
                    '/[^a-z0-9\s]/i',
                    ' ',
                    $message
                )
            )
            . ' ';

        if(
            preg_match(
                '/\b(what about|how about)\b/',
                $text
            )
            &&
            !preg_match(
                '/\b(this|that|it|one|here|there|them|those|these)\b/',
                $text
            )
        ){

            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | MOST RECENT GUIDANCE TOPIC IN THIS CONVERSATION
        |--------------------------------------------------------------------------
        */

        $recent =
            $this->chatHistoryModel
                ->getChats(
                    $sessionId,
                    6
                );

        foreach($recent as $chat){

            $guide =
                $this->systemKnowledge
                    ->detectGuidance(
                        $chat['user_message']
                        ?? ''
                    );

            if(empty($guide)){

                $feature =
                    $this->systemKnowledge
                        ->detectExplicitFeature(
                            $chat['user_message']
                            ?? ''
                        );

                if(!empty($feature)){

                    $guide = [
                        'key' =>
                            $feature['key'],
                        'intent' =>
                            'where',
                        'page' =>
                            $feature['page']
                    ];
                }
            }

            if(!empty($guide)){

                return $guide;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | FALLBACK: MOST RECENT DATA TOPIC THAT MAPS TO A PAGE
        |--------------------------------------------------------------------------
        |
        | "How much did we remit this month?" followed by "Can you give
        | me a link?" still clearly refers to Remittance, even though the
        | previous message was a database question.
        |
        */

        $lastMessage =
            trim(
                $recent[0]['user_message']
                ?? ''
            );

        if($lastMessage !== ''){

            $topics =
                $this->detectDataKeys(
                    $lastMessage
                );

            foreach($topics as $topic){

                if(!isset($this->topicPageMap[$topic])){

                    continue;
                }

                $pages =
                    $this->systemKnowledge
                        ->getPages();

                if(
                    isset(
                        $pages[
                            $this->topicPageMap[$topic]
                        ]
                    )
                ){

                    return [
                        'key' =>
                            $this->topicPageMap[$topic],
                        'intent' =>
                            'where',
                        'page' =>
                            $pages[
                                $this->topicPageMap[$topic]
                            ]
                    ];
                }
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | GET CONVERSATION TOPIC CONTEXT
    |--------------------------------------------------------------------------
    |
    | A lightweight "what are we talking about right now" block passed to
    | the AI alongside the message history. Resolves the current message
    | first (guidance page, then data topic), then scans the recent
    | conversation for the last established topic. Lets the AI resolve
    | "here", "this page", "it", "one" and period references naturally.
    |
    */

    private function getTopicContext(
        $sessionId,
        $message
    ){

        $resolve = function($text){

            $guide =
                $this->systemKnowledge
                    ->detectGuidance(
                        $text
                    );

            if(!empty($guide)){

                return $guide['page'];
            }

            $feature =
                $this->systemKnowledge
                    ->detectExplicitFeature(
                        $text
                    );

            if(!empty($feature)){

                return $feature['page'];
            }

            $keys =
                $this->detectDataKeys(
                    $text
                );

            foreach($keys as $topic){

                if(
                    isset(
                        $this->topicPageMap[$topic]
                    )
                ){

                    $pages =
                        $this->systemKnowledge
                            ->getPages();

                    if(
                        isset(
                            $pages[
                                $this->topicPageMap[$topic]
                            ]
                        )
                    ){

                        return
                            $pages[
                                $this->topicPageMap[$topic]
                            ];
                    }
                }
            }

            foreach($keys as $topic){

                if(
                    isset(
                        $this->topicLabels[$topic]
                    )
                ){

                    return [
                        'name' =>
                            $this->topicLabels[$topic],
                        'menu' => null,
                        'route' => null
                    ];
                }
            }

            return null;
        };

        /*
        |--------------------------------------------------------------------------
        | RESOLVE CURRENT MESSAGE
        |--------------------------------------------------------------------------
        |
        | A qualifier-only message ("what about 2023?") carries no topic
        | of its own; skip it and let the history scan below find the
        | actual topic (e.g. Driver Performance), not "Historical Data".
        |
        */

        $currentKeys =
            $this->detectDataKeys(
                $message
            );

        $page =
            $this->isQualifierOnly($currentKeys)
                ?
                null
                :
                $resolve($message);

        $year = null;

        if(
            preg_match(
                '/\b(20\d{2})\b/',
                $message,
                $yearMatch
            )
        ){

            $year = $yearMatch[1];
        }

        /*
        |--------------------------------------------------------------------------
        | SCAN RECENT HISTORY FOR THE LAST ESTABLISHED TOPIC
        |--------------------------------------------------------------------------
        */

        if($page === null){

            $recent =
                $this->chatHistoryModel
                    ->getChats(
                        $sessionId,
                        6
                    );

            foreach($recent as $chat){

                $page =
                    $resolve(
                        $chat['user_message']
                        ?? ''
                    );

                if($page !== null){

                    break;
                }
            }
        }

        if($page === null && $year === null){

            return null;
        }

        return [
            'page' => $page,
            'year' => $year
        ];
    }
}
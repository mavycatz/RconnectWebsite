<?php

namespace Finance\Services\AI;

require_once __DIR__
    . '/../../models/AI/AIReportHistoryModel.php';

require_once __DIR__
    . '/../../models/AI/AIChatHistoryModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/auth/Auth.php';

$aiConfig_analytics = require __DIR__
    . '/../../config/ai.php';

use Finance\Models\AI\AIReportHistoryModel;
use Finance\Models\AI\AIChatHistoryModel;
use App\Auth\Auth;

class AIAnalyticsService
{
    private string $apiKey;

    private AIReportHistoryModel
        $historyModel;

    private AIChatHistoryModel
        $chatHistoryModel;

    public function __construct()
    {
        global $aiConfig_analytics;
        $this->apiKey = $aiConfig_analytics['api_key'];

        $this->historyModel =
            new AIReportHistoryModel();

        $this->chatHistoryModel =
            new AIChatHistoryModel();
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE AI RESPONSE
    |--------------------------------------------------------------------------
    */

    public function generateAIResponse(

        $sessionId,
        $userPrompt,
        $transportationData = [],
        $conversationContext = null,
        $pageContext = null

    ){

        /*
        |--------------------------------------------------------------------------
        | USER
        |--------------------------------------------------------------------------
        */

        $userId =
            Auth::userId();

        /*
        |--------------------------------------------------------------------------
        | TYPES
        |--------------------------------------------------------------------------
        */

        $reportType =
            $this->detectReportType(
                $userPrompt
            );

        $chatType =
            $this->detectChatType(
                $userPrompt
            );

        $reportTitle =
            substr(
                trim($userPrompt),
                0,
                100
            );

        /*
        |--------------------------------------------------------------------------
        | ANALYTICS DATA
        |--------------------------------------------------------------------------
        */

        $transportationData =
            $this->prepareAnalyticsData(
                $transportationData
            );

        /*
        |--------------------------------------------------------------------------
        | REPORT REQUEST
        |--------------------------------------------------------------------------
        */

        $isReportRequest =
            $this->shouldSaveReport(
                $userPrompt
            );

        /*
        |--------------------------------------------------------------------------
        | RESPONSE STYLE
        |--------------------------------------------------------------------------
        */

        if($isReportRequest){

            $responseStyle = "

Generate a SHORT summarized professional transportation report.

RULES:

- only 1 short summary paragraph
- concise and professional
- focus only on key insights
- avoid markdown
- avoid tables
- avoid JSON formatting
- end by asking if the user needs:
  PDF report,
  Excel report,
  or detailed report

";

        }else{

            $responseStyle = "

Respond as Riza, the cooperative's internal transportation analyst — professional, friendly, clear, and concise.

RULES:

- Answer the question directly — no introductions, no fluff
- Format responses with proper Markdown:
  - use headings (## Section) for separate sections
  - use bullet lists (- item) with each item on its own line
  - use numbered lists (1. item) for ordered steps
  - use **bold** for key figures, driver names, and important values
  - use tables only when comparing several values side by side
  - separate paragraphs and sections with blank lines
- Always express money in Philippine Pesos with the ₱ symbol (e.g., ₱15,851.00)
- Use the database data to back up your answer
- If the question is simple, give a short answer
- If the question is analytical, explain the meaning conversationally
- If the user asks about a specific year, rank and compare only within that year's data
- If the user challenges an answer, re-check the data and give the corrected result from the actual records
- If the user asks about trends, point out what changed and why
- NEVER ask unnecessary follow-up questions
- Never open with greetings or close with filler like \"If you need more information, feel free to ask!\"
- If the user might benefit from a detailed breakdown, offer: \"Would you like a full report or data export?\"

";
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE SESSION
        |--------------------------------------------------------------------------
        */

        $isNewSession =
            $this->ensureSession(
                $sessionId,
                $userId
            );

/*
|--------------------------------------------------------------------------
| SMART ANALYTICS CONTEXT
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| REDUCE HUGE DATASETS
|--------------------------------------------------------------------------
*/

$optimizedData = [];

try {
    $liveKeys = [
        'latest_trips', 'trip_summary', 'monthly_breakdown',
        'payment_summary', 'payment_status',
        'route_summary', 'driver_performance',
        'remittance_summary', 'latest_remittances',
        'expense_summary',
        'bus_trip_summary', 'latest_bus_trips',
        'bus_summary',
        'latest_dispatches',
        'driver_summary', 'passenger_count'
    ];
    $historicalKeys = [
        'historical_summary',
        'historical_yearly',
        'historical_monthly',
        'historical_daily',
        'historical_passenger_types',
        'historical_discounts',
        'historical_bus_yearly',
        'historical_routes_yearly',
        'historical_payment_methods',
        'historical_recent',
    ];

    $allKeys = array_merge($historicalKeys, $liveKeys);
    $optimizedData = [];
    foreach ($allKeys as $key) {
        $val = $transportationData[$key] ?? [];
        $optimizedData[$key] = is_array($val) ? $val : $val;
    }
} catch (\Throwable $e) {
    $optimizedData = ['error' => 'Data loading issue: ' . $e->getMessage()];
}

        /*
        |--------------------------------------------------------------------------
        | PRIVATE USER MEMORY
        |--------------------------------------------------------------------------
        |
        | Fetched before the response section below so the "no data"
        | instruction can tell whether this is the first message of the
        | conversation (Riza only introduces herself once).
        |
        */

        $sessionChats =

            $this->chatHistoryModel
                ->getChats(
                    $sessionId,
                    20
                );

        $isFirstMessage =

            empty($sessionChats);

$analyticsSection = '';

$hasAnyData = false;

foreach($optimizedData as $val){

    if(!empty($val)){

        $hasAnyData = true;

        break;
    }
}

if($hasAnyData){

    $analyticsSection =
        json_encode(
            $optimizedData,
            JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE
        );

}else{

    $isConversational =
        $this->isConversationalMessage(
            $userPrompt
        );

    if($isConversational){

        $introRule =
            $isFirstMessage
                ?
                    "This is the first message of the conversation — "
                    .
                    "if the user greets you or asks who you are "
                    .
                    "or what you can do, introduce yourself briefly "
                    .
                    "as Riza and list what you can help with."
                :
                    "This is a conversational message inside an ongoing "
                    .
                    "conversation — respond with a brief friendly "
                    .
                    "greeting and ask what the user would like to know. "
                    .
                    "Do NOT re-introduce yourself, do NOT repeat your "
                    .
                    "capabilities, and do NOT add filler.";

    }else{

        $introRule =
            $isFirstMessage
                ?
                    "This is the first message of the conversation — "
                    .
                    "if the user greets you or asks who you are "
                    .
                    "or what you can do, introduce yourself briefly "
                    .
                    "as Riza and list what you can help with."
                :
                    "Do NOT introduce yourself again, do NOT repeat "
                    .
                    "your capabilities, and do NOT open with greetings "
                    .
                    "or close with filler — answer directly. "
                    .
                    "If the request is genuinely unclear, ask one short "
                    .
                    "clarifying question.";
    }

    $analyticsSection =
        "(No database records were queried for this message. "
        .
        $introRule
        .
        " Do not claim or invent any data.)";
}

        /*
        |--------------------------------------------------------------------------
        | CURRENT PAGE CONTEXT & DATA
        |--------------------------------------------------------------------------
        |
        | The page the user is looking at right now carries real, verified
        | data (widgets, tables, filters). This is the SINGLE MOST TRUSTED
        | source: the AI must answer from it first and never contradict or
        | invent figures alongside it. The database section below is only a
        | fallback for questions the current page cannot answer.
        |
        */

        $pageContextText = '';

        $pageFilters = $pageContext['filters'] ?? [];

        if(!empty($pageContext)){
            $lines = [];

            $pageName = $pageContext['name'] ?? '';
            $pageUrl  = $pageContext['url'] ?? '';

            if($pageName !== ''){
                $lines[] = 'CURRENT PAGE: ' . $pageName;
            }

            if(
                $pageUrl !== ''
                &&
                !empty($pageContext['data'])
            ){
                $lines[] = 'Page location: ' . $pageUrl;
            }

            if(!empty($pageFilters)){
                $desc = [];
                foreach(['range','month','year','fromDate','toDate'] as $k){
                    if(array_key_exists($k, $pageFilters) && $pageFilters[$k] !== '' && $pageFilters[$k] !== null){
                        $desc[] = $k . '=' . $pageFilters[$k];
                    }
                }
                if($desc){
                    $lines[] = 'Page filters applied: ' . implode(' ', $desc);
                }
            }

            if(!empty($pageContext['data'])){
                $lines[] = '';
                $lines[] = 'VERIFIED DATA SHOWN ON THIS PAGE:';
                $lines[] = json_encode(
                    $pageContext['data'],
                    JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE
                );
            }

            if(!empty($pageContext['data'])){
                $lines[] = '';
                $lines[] = 'RULES FOR THE DATA ABOVE:';
                $lines[] = '- The figures above ARE the numbers displayed on this page right now. Treat them as the authoritative, verified values.';
                $lines[] = '- Answer this question using ONLY the page data above whenever it contains the relevant information.';
                $lines[] = '- Prefer the page data over the DATABASE RECORDS section below. When they disagree, use the page data and do NOT silently pick one value — explain which section the value came from.';
                $lines[] = '- Never invent, estimate, extrapolate, or guess any figure. If the page data and database data do not cover what is asked, say you do not have enough current data.';
            }

            $lines[] = '';

            $pageContextText = implode("\n", $lines);
        }

        /*
        |--------------------------------------------------------------------------
        | GLOBAL KNOWLEDGE
        |--------------------------------------------------------------------------
        */

$globalKnowledge =

    $this->chatHistoryModel
        ->getGlobalKnowledge(
            15
        );

/*
|--------------------------------------------------------------------------
| BUILD KNOWLEDGE MEMORY
|--------------------------------------------------------------------------
*/

$globalMemoryText = '';

foreach(

    $globalKnowledge

    as

    $memory
){

    $globalMemoryText .= "

Category:
" . $memory['category'] . "

Keyword:
" . $memory['keyword'] . "

Insight:
" . $memory['insight'] . "

";
}
/*
|--------------------------------------------------------------------------
| MERGE MEMORY
|--------------------------------------------------------------------------
*/

$previousChats =
    $sessionChats;

/*
|--------------------------------------------------------------------------
| BUILD MEMORY
|--------------------------------------------------------------------------
*/

$conversationMemory = '';

foreach($previousChats as $chat){

    $userMessage =
        trim(
            strip_tags(
                $chat['user_message']
                ?? ''
            )
        );

    $aiResponse =
        trim(
            strip_tags(
                $chat['ai_response']
                ?? ''
            )
        );

    $aiResponse =
        mb_substr(
            $aiResponse,
            0,
            300
        );

    $conversationMemory .= "

Previous Discussion

User:
{$userMessage}

AI:
{$aiResponse}

";
}

/*
|--------------------------------------------------------------------------
| USERNAME
|--------------------------------------------------------------------------
*/

$username =

    \App\Auth\Auth::username()

    ?? 'User';

/*
|--------------------------------------------------------------------------
| CONVERSATION TOPIC CONTEXT
|--------------------------------------------------------------------------
*/

$conversationTopicText = '';

if(!empty($conversationContext)){

    $page = $conversationContext['page'] ?? null;

    $year = $conversationContext['year'] ?? null;

    $topicLines = [];

    $topicLines[] =
        'CURRENT CONVERSATION TOPIC:';

    if(!empty($page)){

        $topicLines[] =
            'Topic: ' . ($page['name'] ?? '');

        if(!empty($page['menu'])){

            $topicLines[] =
                'Section: ' . $page['menu'];
        }

        if(!empty($page['menu'])){

            $topicLines[] =
                'Last Navigation Target: '
                . $page['menu'];
        }
    }

    if(!empty($year)){

        $topicLines[] =
            'Referenced period: ' . $year;
    }

    $topicLines[] = '';

    $topicLines[] =
        'Context words in the user\'s message such as "here", '
        .
        '"this page", "this section", "it", "one", "that", '
        .
        '"there", "them" refer to the CURRENT CONVERSATION TOPIC '
        .
        'above — NOT to anything else.';

    $topicLines[] = '';

    $topicLines[] =
        'Never invent or reveal URLs or page addresses in your '
        .
        'answer; navigation is always provided by the system as '
        .
        'buttons. If the user asks "where" or for a link, point '
        .
        'them to the Section named above and the navigation button.';

    $topicLines[] = '';

    $conversationTopicText =
        implode("\n", $topicLines);
}

/*
|--------------------------------------------------------------------------
| DETERMINISTIC TOP-PERFORMER VERDICT
|--------------------------------------------------------------------------
|
| When the user explicitly asks for a driver ranking ("highest / top /
| best / most / least performing driver", "top driver", "who earned the
| most", ...), the system computes the actual winner programmatically
| from the DB driver_performance rows (the same authoritative dataset
| the dashboard uses) and handed it to the model as a VERIFIED VERDICT.
| The model must adopt it instead of scanning the JSON and guessing —
| it can only show the winner's ranking summary, never a different name.
|
*/

try {

    $perfRows =
        (is_array($optimizedData['driver_performance'] ?? null))
            ? $optimizedData['driver_performance']
            : [];

    $driverLeaderboard =
        $this->buildDriverLeaderboard($perfRows);

    $metricWord = trim((string)($this->detectTopMetric($userPrompt)));

    $rankVerdict = $this->buildTopPerformerVerdict($metricWord, $perfRows);
} catch (Throwable $e) {

    $driverLeaderboard = '';
    $rankVerdict = '';
}

/*
|--------------------------------------------------------------------------
| PROMPT
|--------------------------------------------------------------------------
*/

$prompt = "

CURRENT DATE: " . date('F j, Y') . "
CURRENT USER: {$username}

{$pageContextText}

{$driverLeaderboard}

{$rankVerdict}

DATABASE RECORDS (actual data from the database):

{$analyticsSection}

CONVERSATION HISTORY:

{$conversationMemory}

{$conversationTopicText}

{$globalMemoryText}

USER REQUEST:

{$userPrompt}
";
        /*
        |--------------------------------------------------------------------------
        | NO-DATA GUARD (DETERMINISTIC)
        |--------------------------------------------------------------------------
        |
        | When the question needs data and the relevant dataset came back
        | empty (no records for the period, or the named driver has zero
        | trips in that period), reply with an explicit "no data" message
        | instead of letting the model guess. The model is never consulted
        | when there is nothing to answer from.
        |
        */

        $noDataReply =
            $this->buildNoDataReply(
                $userPrompt,
                $optimizedData,
                $pageContext
            );

        /*
        |--------------------------------------------------------------------------
        | AI RESPONSE
        |--------------------------------------------------------------------------
        */

        if($noDataReply !== ''){

            $content =
                $noDataReply;

        }else{

            $content =
                $this->sendRequest(
                    $prompt,
                    $responseStyle
                );

            /*
            |--------------------------------------------------------------------------
            | INVALID RESPONSE
            |--------------------------------------------------------------------------
            */

            if(

                empty($content)

                ||

                is_array($content)

            ){

                $content =
                    'The AI could not generate a response.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CLEAN RESPONSE
        |--------------------------------------------------------------------------
        */

        $content = trim($content);
/*
|--------------------------------------------------------------------------
| SAVE CHAT
|--------------------------------------------------------------------------
*/

$this->chatHistoryModel
    ->saveChat(

        $sessionId,

        $userId,

        $userPrompt,

        $content,

        $chatType,

        null
    );

/*
|--------------------------------------------------------------------------
| GLOBAL KNOWLEDGE
|--------------------------------------------------------------------------
*/

$this->extractGlobalKnowledge(

    $userPrompt,

    $content,

    $chatType
);

/*
|--------------------------------------------------------------------------
| CONVERSATION TITLE
|--------------------------------------------------------------------------
*/

$this->updateConversationTitle(

    $sessionId,

    $userPrompt,

    $isNewSession
);



        /*
        |--------------------------------------------------------------------------
        | RETURN
        |--------------------------------------------------------------------------
        */

        return [

            'session_id' =>
                $sessionId,

            'title' =>
                $reportTitle,

            'type' =>
                $reportType,

            'content' =>
                $content,

            'is_report' =>
                $isReportRequest
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ENSURE SESSION
    |--------------------------------------------------------------------------
    */

    private function ensureSession(
        &$sessionId,
        $userId
    ){

        $isNewSession =
            false;

        if(

            $sessionId === null

            ||

            $sessionId === ''

            ||

            !$this->chatHistoryModel
                ->sessionExists(
                    $sessionId
                )
        ){

            $isNewSession =
                true;

            $sessionId =

                $this->chatHistoryModel
                    ->createSession(

                        'New Conversation',

                        $userId
                    );
        }

        return $isNewSession;
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE SYSTEM GUIDANCE MESSAGE
    |--------------------------------------------------------------------------
    |
    | Stores a deterministic system-knowledge answer ("where is it",
    | "how do I", "what is it") the same way AI answers are stored:
    | session, chat history, and conversation title.
    |
    */

    public function saveGuidanceMessage(
        $sessionId,
        $userId,
        $userPrompt,
        $content
    ){

        $isNewSession =
            $this->ensureSession(
                $sessionId,
                $userId
            );

        $this->chatHistoryModel
            ->saveChat(

                $sessionId,

                $userId,

                $userPrompt,

                $content,

                'system_guidance',

                null
            );

        $this->updateConversationTitle(
            $sessionId,
            $userPrompt,
            $isNewSession
        );

        return [
            'session_id' => $sessionId,
            'content' => $content
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PREPARE DATA
    |--------------------------------------------------------------------------
    */

    private function prepareAnalyticsData(
        $data
    ){

        $liveKeys = [
            'latest_trips', 'trip_summary', 'monthly_breakdown',
            'payment_summary', 'payment_status',
            'route_summary', 'driver_performance',
            'remittance_summary', 'latest_remittances',
            'expense_summary',
            'bus_summary',
            'latest_dispatches',
        'fuel_prices', 'driver_summary', 'passenger_count'
        ];
        $historicalKeys = [
            'historical_summary',
            'historical_yearly',
            'historical_monthly',
            'historical_daily',
            'historical_passenger_types',
            'historical_discounts',
            'historical_bus_yearly',
            'historical_routes_yearly',
            'historical_payment_methods',
            'historical_recent',
        ];

        $result = [];
        foreach ($liveKeys as $k) { $result[$k] = $data[$k] ?? []; }
        foreach ($historicalKeys as $k) { $result[$k] = $data[$k] ?? []; }
        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | NO-DATA REPLY (DETERMINISTIC)
    |--------------------------------------------------------------------------
    |
    | Returns a concrete "no data" message when the user's question needs
    | records that came back empty for the selected period, so Riza says
    | there is no data instead of presenting invented figures. Returns ''
    | when the model should answer normally.
    |
    */

    private function buildNoDataReply(
        $userPrompt,
        $optimizedData,
        $pageContext
    ){

        if(
            $this->isConversationalMessage($userPrompt)
        ){
            return '';
        }

        /*
        |--------------------------------------------------------------------------
        | TOPIC + METRIC WORDS
        |--------------------------------------------------------------------------
        |
        | Only guard questions that clearly ask about data/metrics; pure
        | chit-chat and non-data questions keep using the model.
        |
        */

        $topicWords = [
            'driver', 'trip', 'passenger', 'revenue', 'payment',
            'remittance', 'expense', 'fuel', 'route', 'bus', 'fare',
            'dispatch', 'discount', 'discrepancy', 'report',
            'summary', 'performance', 'collection'
        ];

        $metricWords = [
            'highest', 'top', 'lowest', 'most', 'least', 'best',
            'how many', 'how much', 'compare', 'comparison', 'rank',
            'list', 'total', 'count', 'number of', 'amount',
            'trips', 'revenue', 'income', 'earn', 'fare', 'pax',
            'passenger', 'loss', 'profit', 'today', 'month', 'year'
        ];

        $hasTopic = false;

        foreach($topicWords as $w){

            if(stripos($userPrompt, $w) !== false){

                $hasTopic = true;
                break;
            }
        }

        if(!$hasTopic){

            return '';
        }

        $hasMetric = false;

        foreach($metricWords as $w){

            if(stripos($userPrompt, $w) !== false){

                $hasMetric = true;
                break;
            }
        }

        if(!$hasMetric){

            return '';
        }

        $period =
            $this->describePeriod(
                $userPrompt,
                $pageContext
            );

        /*
        |--------------------------------------------------------------------------
        | EVERYTHING EMPTY
        |--------------------------------------------------------------------------
        */

        $allEmpty = true;

        foreach($optimizedData as $rows){

            if(!empty($rows)){

                $allEmpty = false;
                break;
            }
        }

        $pageHasData =
            !empty(
                $pageContext['data']
                ?? []
            );

        if(
            $allEmpty
            &&
            !$pageHasData
        ){

            $topic = 'records';

            foreach($topicWords as $w){

                if(stripos($userPrompt, $w) !== false){

                    $topic = $w;
                    break;
                }
            }

            return
                'There is no data to display for this question — '
                .
                'no ' . $topic . ' records were found '
                .
                $period
                .
                '. I am not showing any figures because there '
                .
                'is nothing in the data to show.';
        }

        /*
        |--------------------------------------------------------------------------
        | DRIVER-LEVEL CHECKS
        |--------------------------------------------------------------------------
        */

        $isDriverQuestion =
            stripos($userPrompt, 'driver') !== false
            ||
            stripos($userPrompt, 'trip') !== false;

        if(!$isDriverQuestion){

            return '';
        }

        $driverRows =
            $optimizedData['driver_performance']
            ?? [];

        $driverRows =
            is_array($driverRows)
                ? $driverRows
                : [];

        $knownNames = [];

        foreach($driverRows as $row){

            if(!is_array($row)){

                continue;
            }

            $n =
                trim(
                    (string)(
                        $row['driver']
                        ?? $row['name']
                        ?? ''
                    )
                );

            if($n !== ''){

                $knownNames[
                    ]
                    = $n;
            }
        }

        $this->collectDriverNamesFromData(
            $pageContext['data']
            ?? [],
            $knownNames
        );

        /*
        |--------------------------------------------------------------------------
        | SPECIFIC DRIVER REQUESTED BUT HAS NO TRIPS IN THE PERIOD
        |--------------------------------------------------------------------------
        */

        $requested = '';

        foreach($knownNames as $n){

            if($this->nameMentionedIn($userPrompt, $n)){

                $requested = $n;
                break;
            }
        }

        if($requested !== ''){

            $hasPositiveRecord = false;

            foreach($driverRows as $row){

                if(!is_array($row)){

                    continue;
                }

                $rn =
                    trim(
                        (string)(
                            $row['driver']
                            ?? $row['name']
                            ?? ''
                        )
                    );

                if(
                    $rn !== ''
                    &&
                    strcasecmp($rn, $requested) === 0
                    &&
                    (int)($row['trips'] ?? 0) > 0
                ){

                    $hasPositiveRecord = true;
                    break;
                }
            }

            if(!$hasPositiveRecord){

                return
                    'There are no recorded trips for **'
                    .
                    $requested
                    .
                    '** '
                    .
                    $period
                    .
                    ' — that driver has no trip data in the '
                    .
                    'selected period, so there is nothing to '
                    .
                    'display.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | NO TRIPS AT ALL FOR THE PERIOD
        |--------------------------------------------------------------------------
        */

        if(!empty($driverRows)){

            $allZero = true;

            foreach($driverRows as $row){

                if(!is_array($row)){

                    continue;
                }

                if((int)($row['trips'] ?? 0) > 0){

                    $allZero = false;
                    break;
                }
            }

            if($allZero){

                return
                    'No trips were recorded '
                    .
                    $period
                    .
                    ' — every driver has zero trip records, so '
                    .
                    'there is no data to display.';
            }
        }

        return '';
    }

    /*
    |--------------------------------------------------------------------------
    | COLLECT DRIVER NAMES FROM PAGE DATA
    |--------------------------------------------------------------------------
    |
    | Recursively gathers recognised names/driver identifiers from the
    | verified page data so a follow-up like "what about jose today?" can
    | be matched against real drivers (never against guessed words).
    |
    */

    private function collectDriverNamesFromData(
        $data,
        array &$names
    ){

        if(!is_array($data)){

            return;
        }

        foreach($data as $key => $value){

            if(is_array($value)){

                $this->collectDriverNamesFromData(
                    $value,
                    $names
                );

                continue;
            }

            if(
                is_string($key)
                &&
                is_string($value)
            ){

                $keyLower =
                    strtolower($key);

                if(
                    in_array(
                        $keyLower,
                        [
                            'name', 'driver',
                            'driver_name', 'full_name',
                            'driver_id'
                        ],
                        true
                    )
                ){

                    $t =
                        trim($value);

                    if(
                        $t !== ''
                        &&
                        strlen($t) <= 120
                    ){

                        if(
                            !in_array($t, $names, true)
                        ){

                            $names[
                                ]
                                = $t;
                        }
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | NAME MENTIONED IN THE MESSAGE
    |--------------------------------------------------------------------------
    */

    private function nameMentionedIn(
        $prompt,
        $name
    ){

        $promptLower =
            ' '
            .
            strtolower($prompt)
            .
            ' ';

        $nameLower =
            strtolower($name);

        if(strpos($promptLower, $nameLower) !== false){

            return true;
        }

        $words =
            preg_split(
                '/\s+/',
                trim($nameLower)
            );

        $significant = [];

        foreach($words as $w){

            if(strlen($w) >= 3){

                $significant[ ]
                    = $w;
            }
        }

        if(empty($significant)){

            return false;
        }

        $hits = 0;

        foreach($significant as $w){

            if(
                strpos($promptLower, $w) !== false
            ){

                $hits++;
            }
        }

        $threshold =
            count($significant) >= 2
                ? 2
                : 1;

        return $hits >= $threshold;
    }

    /*
    |--------------------------------------------------------------------------
    | DESCRIBE THE SELECTED PERIOD
    |--------------------------------------------------------------------------
    */

    private function describePeriod(
        $userPrompt,
        $pageContext
    ){

        if(
            stripos($userPrompt, 'today') !== false
        ){

            $now =
                new \DateTime(
                    'now',
                    new \DateTimeZone('Asia/Manila')
                );

            return
                'today ('
                .
                $now->format('F j, Y')
                .
                ')';
        }

        $filters =
            (array)(
                $pageContext['filters']
                ?? []
            );

        $parts = [];

        if(
            !empty($filters['fromDate'])
            &&
            !empty($filters['toDate'])
        ){

            $parts[ ]
                = $filters['fromDate']
                . ' to '
                . $filters['toDate'];
        }

        if(!empty($filters['month'])){

            $m =
                (int)$filters['month'];

            $monthName =
                ($m >= 1 && $m <= 12)
                    ? date('F', mktime(0, 0, 0, $m, 1, 2000))
                    : (string)$m;

            $parts[ ]
                = $monthName;
        }

        if(!empty($filters['year'])){

            $parts[ ]
                = (string)$filters['year'];
        }

        if(!empty($filters['range'])){

            $parts[ ]
                = (string)$filters['range'];
        }

        if(empty($parts)){

            return
                'for the selected period';
        }

        return
            'for '
            .
            implode(' ', $parts);
    }

    /*
    |--------------------------------------------------------------------------
    | REPORT TYPE
    |--------------------------------------------------------------------------
    */

    private function detectReportType(
        $prompt
    ){

        $prompt =
            strtolower($prompt);

        if(
            str_contains($prompt,'financial')
            ||
            str_contains($prompt,'revenue')
        ){
            return 'financial_report';
        }

        if(
            str_contains($prompt,'route')
        ){
            return 'route_analysis';
        }

        if(
            str_contains($prompt,'driver')
        ){
            return 'driver_report';
        }

        return 'general_report';
    }

    /*
    |--------------------------------------------------------------------------
    | CHAT TYPE
    |--------------------------------------------------------------------------
    */

    private function detectChatType(
        $prompt
    ){

        $prompt =
            strtolower($prompt);

        if(
            str_contains($prompt,'forecast')
        ){
            return 'forecasting';
        }

        return 'general_chat';
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE REPORT?
    |--------------------------------------------------------------------------
    */

    private function shouldSaveReport(
        $prompt
    ){

        $prompt =
            strtolower($prompt);

        return

            str_contains(
                $prompt,
                'report'
            )

            ||

            str_contains(
                $prompt,
                'analytics'
            )

            ||

            str_contains(
                $prompt,
                'summary'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SEND REQUEST
    |--------------------------------------------------------------------------
    */

    private function sendRequest(
        $prompt,
        $responseStyle = ''
    ){

        if(empty($this->apiKey)){

            return
                'OpenAI API key missing.';
        }

        global $aiConfig_analytics;

        $url =
            $aiConfig_analytics['api_url'];

        $schema = "DATABASE SCHEMA (all tables):

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL, `type` enum('Traffic Delay','Unpaid Passenger','Inspector Report','Trip Update','System Announcement') NOT NULL, `title` varchar(255) NOT NULL, `message` text NOT NULL, `bus_id` varchar(20) DEFAULT NULL, `driver_name` varchar(100) DEFAULT NULL, `reported_by` varchar(100) DEFAULT NULL, `priority` enum('Low','Normal','High','Critical') DEFAULT 'Normal', `created_at` timestamp NULL DEFAULT current_timestamp(), `status` enum('Active','Resolved') DEFAULT 'Active', `recording_url` varchar(255) DEFAULT NULL
);
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL, `audit_type` varchar(100) NOT NULL, `performed_by` varchar(255) NOT NULL, `status` varchar(50) NOT NULL DEFAULT 'pending', `financial_snapshot` longtext DEFAULT NULL, `generated_at` timestamp NULL DEFAULT current_timestamp()
);
CREATE TABLE `buses` (
  `id` int(11) NOT NULL, `bus_id` varchar(20) NOT NULL, `plate_number` varchar(20) NOT NULL, `bus_type` varchar(50) DEFAULT NULL, `seats` int(11) DEFAULT NULL, `standing_capacity` int(11) DEFAULT NULL, `accessible_features` varchar(100) DEFAULT NULL, `driver` varchar(100) DEFAULT NULL, `from_terminal_id` int(11) DEFAULT NULL, `to_terminal_id` int(11) DEFAULT NULL, `route_name` varchar(100) DEFAULT NULL, `terminal` varchar(100) DEFAULT NULL, `schedule` varchar(50) DEFAULT NULL, `status` varchar(20) DEFAULT NULL, `qr_code` varchar(255) DEFAULT NULL, `qr_data` text DEFAULT NULL, `qr_status` enum('Pending','Generated') NOT NULL DEFAULT 'Pending'
);
CREATE TABLE `bus_inspections` (
  `id` int(11) NOT NULL, `bus_id` varchar(50) NOT NULL, `inspector_id` varchar(50) NOT NULL, `result` enum('Passed','Failed') NOT NULL, `remarks` text DEFAULT NULL, `issues` text DEFAULT NULL, `inspection_date` datetime DEFAULT current_timestamp()
);
CREATE TABLE `bus_trips` (
  `id` int(11) NOT NULL, `bus_id` varchar(20) NOT NULL, `driver_id` varchar(20) NOT NULL, `route_name` varchar(100) DEFAULT NULL, `from_terminal_id` int(11) DEFAULT NULL, `to_terminal_id` int(11) DEFAULT NULL, `trip_date` date DEFAULT NULL, `trip_number` int(11) DEFAULT NULL, `departure_time` time DEFAULT NULL, `arrival_time` time DEFAULT NULL, `total_passengers` int(11) DEFAULT 0, `duration_minutes` int(11) DEFAULT NULL, `dispatcher_id` varchar(50) DEFAULT NULL, `total_revenue` decimal(10,2) DEFAULT 0.00, `status` enum('Scheduled','Departed','Completed','Cancelled') DEFAULT 'Scheduled', `created_at` timestamp NULL DEFAULT current_timestamp(), `fuel_cost` decimal(10,2) DEFAULT NULL, `trip_type` enum('Regular','Special') NOT NULL DEFAULT 'Regular', `submitted` tinyint(1) DEFAULT 0
);
CREATE TABLE `dispatches` (
  `id` int(11) NOT NULL, `bus_id` varchar(50) NOT NULL, `driver_name` varchar(255) NOT NULL, `route` varchar(255) NOT NULL, `trip_date` date NOT NULL, `trip_number` int(11) NOT NULL, `time_out` time DEFAULT NULL, `time_in` time DEFAULT NULL, `passenger_count` int(11) DEFAULT NULL, `remarks` text DEFAULT NULL, `dispatcher_id` varchar(50) NOT NULL, `status` enum('Waiting','Dispatched','Returned','Delayed','Cancelled') DEFAULT 'Waiting', `created_at` timestamp NULL DEFAULT current_timestamp(), `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
);
CREATE TABLE `drivers` (
  `id` int(11) NOT NULL, `account_id` int(11) DEFAULT NULL, `driver_id` varchar(20) NOT NULL, `full_name` varchar(100) NOT NULL, `status` enum('Active','Inactive','Archived') NOT NULL DEFAULT 'Active', `license_number` varchar(50) NOT NULL, `license_type` varchar(50) DEFAULT NULL, `license_expiry` date DEFAULT NULL, `experience_years` int(11) DEFAULT 0, `assigned_buses` varchar(255) DEFAULT NULL, `routes` varchar(255) DEFAULT NULL, `email` varchar(100) DEFAULT NULL, `contact_number` varchar(20) DEFAULT NULL, `resume_file` varchar(255) DEFAULT NULL, `supporting_docs` varchar(255) DEFAULT NULL, `created_at` timestamp NULL DEFAULT current_timestamp(), `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), `photo` varchar(255) DEFAULT NULL, `approval_status` enum('pending','approved','rejected') DEFAULT 'pending', `approved_at` datetime DEFAULT NULL, `google_sub` varchar(255) DEFAULT NULL, `token` varchar(255) DEFAULT NULL, `archived_at` datetime DEFAULT NULL, `first_login` tinyint(1) NOT NULL DEFAULT 1
);
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL, `bus_id` varchar(50) DEFAULT NULL, `trip_id` int(11) DEFAULT NULL, `category` enum('fuel','driver_salary','maintenance','toll','insurance','misc') NOT NULL, `amount` decimal(10,2) NOT NULL, `description` text DEFAULT NULL, `created_at` datetime DEFAULT current_timestamp(), `predicted_cost` decimal(10,2) DEFAULT NULL, `variance` decimal(10,2) DEFAULT NULL, `cost_level` enum('low','normal','high') DEFAULT 'normal', `anomaly_flag` tinyint(1) DEFAULT 0
);
CREATE TABLE `fare_pricing` (
  `id` int(11) NOT NULL, `route_name` varchar(150) NOT NULL, `destination` varchar(150) NOT NULL, `regular_fare` decimal(10,2) NOT NULL, `student_fare` decimal(10,2) NOT NULL, `special_fare` decimal(10,2) NOT NULL, `created_at` timestamp NULL DEFAULT current_timestamp(), `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), `display_order` int(11) NOT NULL DEFAULT 0
);
CREATE TABLE `financial_reports` (
  `id` int(11) NOT NULL, `title` varchar(255) NOT NULL, `report_data` longtext NOT NULL, `archived` tinyint(1) NOT NULL DEFAULT 0, `archived_at` datetime DEFAULT NULL, `report_category` varchar(100) DEFAULT NULL, `generated_at` timestamp NULL DEFAULT current_timestamp()
);
CREATE TABLE `financial_uploads` (
  `id` int(11) NOT NULL, `category` varchar(100) DEFAULT NULL, `transaction_date` date DEFAULT NULL, `amount` decimal(12,2) DEFAULT NULL, `bus_driver` varchar(255) DEFAULT NULL, `notes` text DEFAULT NULL, `import_mode` varchar(50) DEFAULT NULL, `data_source` varchar(100) DEFAULT NULL, `original_name` varchar(255) DEFAULT NULL, `stored_name` varchar(255) DEFAULT NULL, `file_path` text DEFAULT NULL, `file_type` varchar(50) DEFAULT NULL, `processing_status` varchar(50) DEFAULT 'pending', `ai_summary` longtext DEFAULT NULL, `created_at` timestamp NULL DEFAULT current_timestamp(), `ai_category` varchar(100) DEFAULT NULL, `ai_confidence` decimal(5,2) DEFAULT NULL, `ai_insights` longtext DEFAULT NULL, `ai_processed_at` timestamp NULL DEFAULT NULL
);
CREATE TABLE `fuel_prices` (
  `id` int(11) NOT NULL, `fuel_type` varchar(50) NOT NULL, `price_per_liter` decimal(10,2) NOT NULL, `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
);
CREATE TABLE `passengers` (
  `id` int(11) NOT NULL, `full_name` varchar(100) DEFAULT NULL, `email` varchar(100) DEFAULT NULL, `phone` varchar(20) DEFAULT NULL, `password` varchar(255) DEFAULT NULL, `discount_type` varchar(50) DEFAULT NULL, `student_id` varchar(50) DEFAULT NULL, `id_image` varchar(255) DEFAULT NULL, `discount_id` varchar(255) DEFAULT NULL, `discount_status` varchar(50) DEFAULT 'pending', `latitude` decimal(10,8) DEFAULT NULL, `longitude` decimal(11,8) DEFAULT NULL, `created_at` timestamp NULL DEFAULT current_timestamp(), `date_of_birth` date DEFAULT NULL, `status` enum('Active','Pending','Suspended','Blocked','Archived') NOT NULL DEFAULT 'Pending', `status_reason` text DEFAULT NULL, `status_updated_at` timestamp NULL DEFAULT NULL, `status_updated_by` int(11) DEFAULT NULL, `last_login` datetime DEFAULT NULL, `failed_login_attempts` int(11) DEFAULT 0, `blocked_until` datetime DEFAULT NULL, `verified_at` datetime DEFAULT NULL, `discount_percent` int(11) DEFAULT NULL, `voucher_description` text DEFAULT NULL, `privacy_accepted_at` timestamp NULL DEFAULT NULL
);
CREATE TABLE `passenger_trips` (
  `id` int(11) NOT NULL, `passenger_id` int(11) NOT NULL, `inspector_id` varchar(50) DEFAULT NULL, `bus_no` varchar(50) NOT NULL, `trip_no` varchar(50) DEFAULT NULL, `driver_id` varchar(20) DEFAULT NULL, `boarding_point` varchar(100) DEFAULT NULL, `destination` varchar(100) DEFAULT NULL, `destination_id` int(11) DEFAULT NULL, `latitude` decimal(10,8) DEFAULT NULL, `longitude` decimal(11,8) DEFAULT NULL, `fare` decimal(10,2) DEFAULT 0.00, `payment_method` enum('cash','gcash','hybrid') DEFAULT 'cash', `payment_status` enum('pending','ongoing','paid','rejected') NOT NULL DEFAULT 'pending', `verified_at` datetime DEFAULT NULL, `verified_by` varchar(100) DEFAULT NULL, `cash_amount` decimal(10,2) DEFAULT 0.00, `gcash_amount` decimal(10,2) DEFAULT 0.00, `gcash_screenshot` varchar(255) DEFAULT NULL, `boarding_time` timestamp NULL DEFAULT current_timestamp(), `dropoff_time` timestamp NULL DEFAULT NULL, `archived_status` enum('no','yes') DEFAULT 'no', `remittance_status` enum('Pending','Remitted','Discrepancy') DEFAULT 'Pending', `remitted_by` varchar(100) DEFAULT NULL, `updated_by` varchar(100) DEFAULT NULL, `remitted_at` datetime DEFAULT NULL, `expected_cash` decimal(10,2) DEFAULT 0.00, `actual_cash` decimal(10,2) DEFAULT 0.00, `discrepancy_amount` decimal(10,2) DEFAULT 0.00, `finance_note` text DEFAULT NULL
);
CREATE TABLE `passenger_trip_history` (
  `history_id` int(11) NOT NULL, `passenger_name` varchar(255) NOT NULL, `passenger_type` varchar(100) NOT NULL, `student_id` varchar(100) DEFAULT NULL, `boarding_point` varchar(255) NOT NULL, `destination` varchar(255) NOT NULL, `drivers_full_name` varchar(100) NOT NULL, `driver_id` varchar(20) DEFAULT NULL, `trip_id` varchar(100) DEFAULT NULL, `fare` decimal(10,2) DEFAULT 0.00, `payment_method` enum('cash','gcash','hybrid') DEFAULT 'cash', `date_booked` datetime DEFAULT current_timestamp(), `trip_date` date DEFAULT NULL, `archived_at` timestamp NULL DEFAULT current_timestamp(), `senior_pwd_id` varchar(100) DEFAULT NULL, `beneficiary_id` varchar(100) DEFAULT NULL, `coop_disc_id` varchar(100) DEFAULT NULL, `remarks` text DEFAULT NULL, `archived` tinyint(1) DEFAULT 0
);
CREATE TABLE `remittances` (
  `id` int(11) NOT NULL, `driver_id` varchar(50) DEFAULT NULL, `bus_no` varchar(50) DEFAULT NULL, `trip_no` varchar(50) DEFAULT NULL, `total_passengers` int(11) DEFAULT 0, `expected_cash` decimal(10,2) DEFAULT 0.00, `actual_cash` decimal(10,2) DEFAULT 0.00, `discrepancy_amount` decimal(10,2) DEFAULT 0.00, `status` enum('Pending','Completed','Discrepancy') DEFAULT 'Pending', `finance_code` varchar(100) DEFAULT NULL, `confirmed_by` varchar(100) DEFAULT NULL, `confirmed_at` datetime DEFAULT NULL, `created_at` timestamp NULL DEFAULT current_timestamp()
);
CREATE TABLE `remittance_logs` (
  `id` int(11) NOT NULL, `driver_id` varchar(20) DEFAULT NULL, `bus_no` varchar(50) DEFAULT NULL, `total_collection` decimal(10,2) DEFAULT NULL, `expected_cash` decimal(10,2) DEFAULT NULL, `actual_cash` decimal(10,2) DEFAULT NULL, `status` varchar(20) DEFAULT NULL, `submitted_by` varchar(50) DEFAULT NULL, `verified_at` timestamp NULL DEFAULT current_timestamp(), `created_at` timestamp NULL DEFAULT current_timestamp(), `discrepancy_amount` decimal(10,2) DEFAULT 0.00, `finance_code` varchar(100) DEFAULT NULL, `confirmed_by` varchar(100) DEFAULT NULL, `trip_no` varchar(100) DEFAULT NULL, `total_passengers` int(11) DEFAULT 0
);
CREATE TABLE `routes` (
  `id` int(11) NOT NULL, `route_name` varchar(100) NOT NULL, `from_terminal` int(11) NOT NULL, `to_terminal` int(11) NOT NULL
);
CREATE TABLE `terminals` (
  `id` int(11) NOT NULL, `terminal_code` varchar(20) DEFAULT NULL, `terminal_name` varchar(100) NOT NULL, `city` varchar(50) DEFAULT NULL, `province` varchar(50) DEFAULT NULL, `latitude` decimal(10,8) DEFAULT NULL, `longitude` decimal(11,8) DEFAULT NULL, `status` enum('Active','Inactive') DEFAULT 'Active'
);
CREATE TABLE `trip_expenses` (
  `id` int(11) NOT NULL, `trip_id` int(11) NOT NULL, `toll_fees` decimal(10,2) DEFAULT 0.00, `maintenance_cost` decimal(10,2) DEFAULT 0.00, `other_cost` decimal(10,2) DEFAULT 0.00, `recorded_by` varchar(100) DEFAULT NULL, `created_at` timestamp NULL DEFAULT current_timestamp()
);
";

        $systemMessage =
            "You are Riza, the dedicated AI transportation assistant of the Rizal Public Market Vendors Farmers Multi-Purpose Cooperative, based in Nueva Ecija, Philippines.\n"
            . "\n"
            . "You are built into the cooperative's Transportation Management System (TMS). You are NOT a generic chatbot — you are an internal transportation analyst who helps cooperative staff understand their transportation operations and data.\n"
            . "\n"
            . "PURPOSE:\n"
            . "Help users understand driver performance, bus performance, trip records, passenger volume, passenger types, revenue, fare information, route activity, transportation operations, financial transportation data, historical performance, yearly comparisons, operational trends, and transportation reports. Base every answer on the ACTUAL data provided. Never invent or fabricate drivers, routes, buses, financial figures, policies, or organizational information that is not present in the data.\n"
            . "\n"
            . "PERSONALITY:\n"
            . "- Professional, friendly, clear, helpful, concise, data-focused, and practical — suitable for cooperative staff.\n"
            . "- Sound like an internal transportation analyst assisting the cooperative, not a generic assistant.\n"
            . "- Answer the question naturally and directly. Never open with canned phrases like \"Hello! How can I assist you today?\" or close with filler like \"If you need more information, feel free to ask!\" or \"I hope this helps!\".\n"
            . "- Use Philippine Peso (PHP/₱) for all monetary values, e.g., ₱15,851.00.\n"
            . "- When the user only sends a greeting or a conversational message (e.g., \"Hello\", \"Hi\", \"Who are you?\", \"What can you do?\", \"Thank you\") with no database question, respond conversationally as Riza. Introduce yourself and list what you can help with ONLY when this is the first message of the conversation or the user explicitly asks who you are. In every later message answer directly — never re-introduce yourself, never repeat your capabilities, and never add filler. Never invent figures, never mention empty data sections, and never act as if a database query was performed.\n"
            . "\n"
            . "ANALYTICAL RULES:\n"
            . "- For questions about a specific year (e.g., \"Who was the highest performing driver in 2023?\"), ALWAYS filter the records to that year first, then group by the relevant entity (driver, bus, route), calculate the metrics, compare within that year only, and report the actual result for that year. Never answer a year-specific question with overall or all-time rankings.\n"
            . "- Use the historical_* records for anything in the past: historical_yearly, historical_monthly, historical_daily, and historical_bus_yearly contain per-period data you can filter by year. For driver rankings in any period (including a past year), use driver_performance — it comes from the same business query and date filters as the dashboard, so it already includes the archived trips inside that period. Never use a separate, all-time archive driver dataset.\n"
            . "- When the user challenges an answer (e.g., \"I think that is wrong\"), do not blindly agree or apologize. Re-check the available data, re-verify the calculation, and explain the corrected result based on the actual records. Only change your answer if the data supports it.\n"
            . "\n"
            . "Below is the COMPLETE DATABASE SCHEMA. Every table and column listed here exists in the database.\n"
            . "The user message will include ACTUAL DATABASE RECORDS (sample data) from these tables.\n"
            . "\n"
            . "When analyzing data, follow these rules:\n"
            . "\n"
            . "1. Always analyze the data that is provided to you. Never assume or invent information that is not present.\n"
            . "\n"
            . "2. If detailed passenger records are provided, first determine whether the dataset is small or large.\n"
            . "\n"
            . "3. If the dataset contains many passenger records, DO NOT attempt to describe every individual passenger. Instead, automatically summarize the information by: counting totals, calculating averages, calculating percentages, calculating minimum and maximum values, grouping similar records, identifying trends, identifying unusual values or anomalies, identifying the top or bottom performers.\n"
            . "\n"
            . "4. When passenger_trips data is provided, you can analyze passenger activity, revenue, payments, drivers, remittances, locations, and time trends if the user asks about them.\n"
            . "\n"
            . "5. When passenger_trip_history is provided, you can analyze passenger type distribution, fare averages, and destination trends if relevant to the question.\n"
            . "\n"
            . "6. If a complete answer cannot be given because some information is missing, explicitly NAME what data is absent (e.g., 'no passenger_trips data for this period', 'driver_performance is empty', 'no historical records'). Give the user a clear warning so they know what data gaps exist.\n"
            . "\n"
            . "7. Never refuse to answer simply because some fields are missing. Give the best possible answer using available data, but always flag what is missing.\n"
            . "\n"
            . "8. If raw records are too numerous, summarize instead of listing every record.\n"
            . "\n"
            . "9. Only mention individual passengers, drivers, or trips when they are statistical outliers, have unusually high discrepancies, or are specifically requested.\n"
            . "\n"
            . "10. Never fabricate totals, names, statistics, or financial values.\n"
            . "10a. If the user message includes a CURRENT PAGE DATA section, that data is the authoritative, verified source showing what is on screen right now (including the page's filters and date range). Answer from it first and never contradict it. When the page data and the DATABASE RECORDS section disagree, use the page data, and explicitly say which section the value came from (never silently pick one).\n"
            . "10b. If a question cannot be answered from the CURRENT PAGE DATA or the DATABASE RECORDS provided (the data is absent, out of range, or the filters exclude it), say clearly that you do not have enough current data to answer accurately — never guess, estimate, or mix different date ranges or datasets.\n"
            . "10c. When the prompt contains a VERIFIED TOP-PERFORMER VERDICT section, that verdict is authoritative and computed by the system directly from driver_performance (the same dataset as the dashboard). You MUST name exactly the driver listed there as the highest/top/best performer for that metric. Show only the figures that sit in that verdict or in the other verified driver_performance rows. Never substitute a different driver, never estimate, and never reuse an older or all-time driver statistic for a period-specific question.\n"
            . "10d. Remittance answers MUST be short and easy to read. Use the remittance_summary (status totals) and latest_remittances (grouped as ONE row per driver, with completed_count / discrepancy_count / total amounts) to present a compact list: one line per driver with their name, amount, and status. Then one line for the day's totals. NEVER paste the individual remittance_logs rows one-by-one, NEVER repeat the same driver in multiple entries, NEVER dump a full receipt-style block (expected / actual / discrepancy / confirmed by / created at) for each log entry, and NEVER restate the summary if the page data already shows it. Finish without filler.\n"
            . "10e. When the prompt contains a VERIFIED DRIVER LEADERBOARD section, that section is the ONLY source you may use for any driver figure you state (trips, revenue, passengers, per-trip) — it is computed by the system from the same driver_performance dataset the dashboard uses. Copy the figures verbatim (top per metric and the compact per-driver lines). Never invent, derive, mix, or round differently, never attribute a figure to the wrong driver, and never repeat a driver number that is absent from that section.\n"
            . "10f. CONVERSATION HISTORY and CURRENT PAGE DATA are context only — never authoritative. Every number you state (driver stats, trip counts, remittance totals, revenue) MUST be recomputed from the latest DATABASE RECORDS section. Never echo a figure from an earlier answer in the conversation history, even if it looks familiar, and never reuse it for a NEW date range.\n"
            . "10g. Report answers (anything they call a 'report', 'daily report', 'summary', or 'analytics') must be compact: answer ONLY the period and topic asked, present the requested numbers from DATABASE RECORDS as a short bullet list (top drivers, totals, remittance status), and STOP. Do NOT add extra sections, do NOT offer to generate a PDF/Excel/download or 'a more detailed report', do NOT mix year-to-date totals into a daily answer, and do NOT repeat the same line twice. One scope, one answer.\n"
            . "10h. Whenever you name a highest/top-performing driver, ALWAYS state the exact period that ranking is for ('for today, September 5, 2026', 'for September 2026', 'for the full year'), using the same period the question asked for. Never present a month/year ranking as a daily ranking, and vice versa.\n"
            . "\n"
            . "11. If enough information is available, calculate statistics automatically rather than expecting them to be precomputed.\n"
            . "\n"
            . "12. The data below is organized as: HISTORICAL DATA first (past months/years), then CURRENT DATA (recent activity). When asked about ANY past month, date, or year — the HISTORICAL DATA section contains the records you need.\n"
            . "13. Never say 'no data available' for a past month without checking the HISTORICAL section first.\n"
            . "14. The CURRENT DATA section only contains recent operational records (last few days). It does NOT contain past months.\n"
            . "15. When the user asks about a month like 'May 2026' or any date in the past, use the HISTORICAL DATA section.\n"
            . "16. Use clean Markdown formatting — headings (e.g., ## Section), **bold** for key figures and names, bullet lists (- item) with each item on its own line, numbered lists for steps, and tables when comparing values. Separate paragraphs with blank lines. NEVER use ---, ===, ***, ___, or markdown-style dividers.\n"
            . "\n"
            . "If the user asks in Tagalog, Ilocano, or Bisaya, answer in their language.\n"
            . "\n"
            . "Your goal is to give helpful, accurate answers in a natural conversational tone. Be direct — answer the question without unnecessary structure. Only provide detailed reports, sections, or formal write-ups when the user explicitly asks for one (e.g., 'give me a report', 'summarize', 'analytics').\n"
            . "\n\nDATABASE SCHEMA:\n"
            . $schema
            . "\n\nRESPONSE STYLE:\n"
            . $responseStyle;

        $payload = [

            "model" =>
                $aiConfig_analytics['model'],

            "messages" => [

                [

                    "role" => "system",

                    "content" =>
                        $systemMessage
                ],

                [

                    "role" => "user",

                    "content" =>
                        $prompt
                ]
            ],

            "temperature" => 0.3,

            "max_tokens" => 4000
        ];

        $ch =
            curl_init($url);

        curl_setopt_array($ch,[

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_SSL_VERIFYPEER => false,

            CURLOPT_SSL_VERIFYHOST => false,

            CURLOPT_HTTPHEADER => [

                'Content-Type: application/json',

                'Authorization: Bearer '
                . trim($this->apiKey)

            ],

            CURLOPT_POSTFIELDS =>

                json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE),

            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,

        ]);

        $response =
            curl_exec($ch);

        $curlError =
            curl_error($ch);

        curl_close($ch);

        if($curlError){

            return
                json_encode([
                    'success' => false,
                    'message' => 'AI service request failed: ' . $curlError,
                    'partial' => true,
                ]);
        }

        $result =
            json_decode(
                $response,
                true
            );

        if(
            isset(
                $result['choices'][0]
                ['message']['content']
            )
        ){

            return trim(

                $result['choices'][0]
                ['message']['content']
            );
        }

        return
            'Unexpected AI response.';
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE CONVERSATION TITLE
    |--------------------------------------------------------------------------
    */

    private function updateConversationTitle(

        $sessionId,
        $userPrompt,
        $isNewSession

    ){

        /*
        |--------------------------------------------------------------------------
        | SKIP GREETINGS / CONVERSATIONAL MESSAGES
        |--------------------------------------------------------------------------
        */

        if($this->isConversationalMessage($userPrompt)){

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | GENERATE SHORT TITLE
        |--------------------------------------------------------------------------
        */

        $title =
            $this->generateConversationTitle(
                $userPrompt
            );

        if($title === ''){

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ONLY RENAME WHEN THE CURRENT TITLE IS GENERIC
        |--------------------------------------------------------------------------
        */

        $session =
            $this->chatHistoryModel
                ->getSession(
                    $sessionId
                );

        $currentTitle =
            trim(
                $session['session_title']
                ?? ''
            );

        if(

            $isNewSession

            ||

            $currentTitle === ''

            ||

            strtolower($currentTitle)
                === 'new conversation'

            ||

            $this->isConversationalMessage(
                $currentTitle
            )
        ){

            $this->chatHistoryModel
                ->renameSession(
                    $sessionId,
                    $title
                );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | IS CONVERSATIONAL MESSAGE
    |--------------------------------------------------------------------------
    */

    private function isConversationalMessage(
        $text
    ){

        $t =
            ' '
            .
            strtolower(
                preg_replace(
                    '/[^a-z0-9\s]/i',
                    ' ',
                    $text
                )
            )
            .
            ' ';

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
                    \bwhats\s+up\b
                    |
                    \bwhat\s+is\s+up\b
                    |
                    \bsup\b
                    |
                    \bthank\s*you\b
                    |
                    \bthanks?\b
                    |
                    \bgoodbye\b
                    |
                    \bb?ye\b
                    |
                    \b(kumusta|kamusta|musta|salamat|okay|ok|got it|sure|alright|fine|understood|noted|nice|cool|awesome)\b
                /ix',
                $t
            ) === 1;
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE CONVERSATION TITLE
    |--------------------------------------------------------------------------
    */

    private function generateConversationTitle(
        $prompt
    ){

        $original =
            trim($prompt);

        $text =
            ' '
            .
            strtolower(
                preg_replace(
                    '/[^a-z0-9\s]/i',
                    ' ',
                    $original
                )
            )
            .
            ' ';

        /*
        |--------------------------------------------------------------------------
        | SHORT MESSAGES: USE AS-IS (e.g. suggestion chips)
        |--------------------------------------------------------------------------
        */

        $words =
            preg_split(
                '/\s+/',
                trim($text)
            );

        if(count($words) <= 4){

            return
                mb_substr(
                    ucwords(
                        strtolower($original)
                    ),
                    0,
                    40
                );
        }

        /*
        |--------------------------------------------------------------------------
        | YEAR
        |--------------------------------------------------------------------------
        */

        $year = '';

        if(
            preg_match(
                '/\b(20\d{2})\b/',
                $text,
                $ym
            )
        ){
            $year = $ym[1];
        }

        /*
        |--------------------------------------------------------------------------
        | MONTH / MONTHLY
        |--------------------------------------------------------------------------
        */

        $monthly =
            preg_match(
                '/\b(this month|last month|monthly|jan(uary)?|feb(ruary)?|mar(ch)?|apr(il)?|may|jun(e)?|jul(y)?|aug(ust)?|sep(tember)?|oct(ober)?|nov(ember)?|dec(ember)?)\b/i',
                $text
            ) === 1;

        /*
        |--------------------------------------------------------------------------
        | TOPIC
        |--------------------------------------------------------------------------
        */

        $base = '';

        if(
            preg_match(
                '/\b(highest performing driver|best performing driver|top performing driver|top driver|best driver)\b/',
                $text
            )
        ){

            $base =
                'Highest Performing Driver';

        }elseif(
            preg_match(
                '/\b(driver|drivers)\b/',
                $text
            )
        ){

            $base =
                'Driver Performance';

        }elseif(
            preg_match(
                '/\b(revenue|income|earned|earnings|profit|gross|sales)\b/',
                $text
            )
        ){

            $base =
                'Revenue Analysis';

        }elseif(
            preg_match(
                '/\b(passenger|passengers|riders|commuters)\b/',
                $text
            )
        ){

            $base =
                'Passenger Statistics';

        }elseif(
            preg_match(
                '/\b(fuel|expense|expenses|cost|costs|spending)\b/',
                $text
            )
        ){

            $base =
                'Fuel Costs';

        }elseif(
            preg_match(
                '/\b(remittance|remittances|remitted)\b/',
                $text
            )
        ){

            $base =
                'Remittance Report';

        }elseif(
            preg_match(
                '/\b(payment|payments|cash|gcash|collectible|collectibles)\b/',
                $text
            )
        ){

            $base =
                'Payment Analysis';

        }elseif(
            preg_match(
                '/\b(route|routes|destination|boarding)\b/',
                $text
            )
        ){

            $base =
                'Route Analysis';

        }elseif(
            preg_match(
                '/\b(bus|buses|vehicle|vehicles)\b/',
                $text
            )
        ){

            $base =
                'Bus Performance';

        }elseif(
            preg_match(
                '/\b(trip|trips|rides|operational)\b/',
                $text
            )
        ){

            $base =
                'Trip Overview';

        }elseif(
            preg_match(
                '/\b(discount|discounts|senior|pwd|student)\b/',
                $text
            )
        ){

            $base =
                'Passenger Discounts';

        }elseif(
            preg_match(
                '/\b(dispatch|dispatches|dispatched)\b/',
                $text
            )
        ){

            $base =
                'Dispatch Report';

        }elseif(
            preg_match(
                '/\b(compare|comparison|versus)\b/',
                $text
            )
        ){

            $base =
                'Performance Comparison';

        }elseif(
            preg_match(
                '/\b(report|reports|analytics|summary|overview)\b/',
                $text
            )
        ){

            $base =
                'Operations Report';
        }

        /*
        |--------------------------------------------------------------------------
        | FALLBACK: FIRST FEW WORDS
        |--------------------------------------------------------------------------
        */

        if($base === ''){

            $base =
                implode(
                    ' ',
                    array_slice(
                        $words,
                        0,
                        4
                    )
                );
        }

        /*
        |--------------------------------------------------------------------------
        | COMPOSE
        |--------------------------------------------------------------------------
        */

        $title =
            ($year !== '' ? $year . ' ' : '')
            .
            ($monthly ? 'Monthly ' : '')
            .
            $base;

        return
            mb_substr(
                trim($title),
                0,
                40
            );
    }

/*
|--------------------------------------------------------------------------
| EXTRACT GLOBAL KNOWLEDGE
|--------------------------------------------------------------------------
*/

private function extractGlobalKnowledge(

    $userPrompt,

    $aiResponse,

    $chatType

){

    /*
    |--------------------------------------------------------------------------
    | COMBINED TEXT
    |--------------------------------------------------------------------------
    */

    $text =

        strtolower(

            $userPrompt
            .
            ' '
            .
            strip_tags($aiResponse)
        );

    /*
    |--------------------------------------------------------------------------
    | CATEGORY
    |--------------------------------------------------------------------------
    */

    $category =
        'general';

    /*
    |--------------------------------------------------------------------------
    | KEYWORD
    |--------------------------------------------------------------------------
    */

    $keyword =
        'transportation';

    /*
    |--------------------------------------------------------------------------
    | DETECT ROUTES
    |--------------------------------------------------------------------------
    */

    if(

        preg_match(

            '/route\s+([a-zA-Z0-9]+)/i',

            $text,

            $matches
        )
    ){

        $keyword =
            'Route '
            .
            $matches[1];

        $category =
            'route_issue';
    }

    /*
    |--------------------------------------------------------------------------
    | PASSENGERS
    |--------------------------------------------------------------------------
    */

    if(

        str_contains(
            $text,
            'passenger'
        )
    ){

        $category =
            'passenger_analytics';
    }

    /*
    |--------------------------------------------------------------------------
    | REVENUE
    |--------------------------------------------------------------------------
    */

    if(

        str_contains(
            $text,
            'revenue'
        )

        ||

        str_contains(
            $text,
            'income'
        )
    ){

        $category =
            'revenue_analysis';
    }

    /*
    |--------------------------------------------------------------------------
    | DRIVERS
    |--------------------------------------------------------------------------
    */

    if(

        str_contains(
            $text,
            'driver'
        )
    ){

        $category =
            'driver_management';
    }

    /*
    |--------------------------------------------------------------------------
    | SHORT INSIGHT
    |--------------------------------------------------------------------------
    */

    $insight =
        mb_substr(

            strip_tags($aiResponse),

            0,

            500
        );

    /*
    |--------------------------------------------------------------------------
    | SAVE
    |--------------------------------------------------------------------------
    */

    $this->chatHistoryModel
        ->saveGlobalKnowledge(

            $category,

            $keyword,

            $insight,

            null
        );
}

/*
|--------------------------------------------------------------------------
| DETECT TOP-PERFORMER METRIC
|--------------------------------------------------------------------------
|
| Returns the canonical driver_performance metric column a ranking
| question is asking about, or '' when the message is not a driver
| ranking request. Only triggers on explicit ranking intent so casual
| "how many trips" or "driver performance" questions are unaffected.
|
*/

private function detectTopMetric(string $text): string
{
    if (trim($text) === '') {
        return '';
    }

    $lower =
        ' '
        .
        strtolower(
            preg_replace('/[^a-z0-9\s]/i', ' ', $text)
        )
        .
        ' ';

    $isRanking =
        preg_match(
            '/\b(highest|top|best|leading|number\s+one|most|least'
            . '|who\s+.*?(earned|collected|remitted))'
            . '|top\s+performing\s+driver|highest\s+performing\s+driver'
            . '|best\s+driver/i',
            $lower
        );

    if (!$isRanking) {
        return '';
    }

    if (preg_match('/\b(trips?|rides?|trip\s+count)\b/', $lower)) {
        return 'trips';
    }

    if (preg_match('/\b(revenue|income|earn(ed|ings)?|collected|sales|profit)\b/', $lower)) {
        return 'revenue';
    }

    if (preg_match('/\b(passeng(er|ers)|riders|commuters|pax)\b/', $lower)) {
        return 'passengers';
    }

    if (preg_match('/\b(performance|payout|per\s+trip)\b/', $lower)) {
        return 'performance';
    }

    return 'revenue';
}

/*
|--------------------------------------------------------------------------
| BUILD VERIFIED TOP-PERFORMER VERDICT
|--------------------------------------------------------------------------
|
| Given the detected metric and the DB driver_performance rows (the same
| authoritative query the dashboard runs), compute the actual winner in
| PHP and return a prompt fragment the model must adopt verbatim. When no
| metric was detected, or no non-zero rows exist for the period, returns
| a fragment that forbids naming any driver so the model never guesses.
|
*/

private function buildTopPerformerVerdict(
    string $metricWord,
    array $perfRows
): string
{
    if ($metricWord === '') {
        return '';
    }

    $nonZero = [];

    foreach ($perfRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rev     = (float)($row['revenue'] ?? $row['income'] ?? 0);
        $trips   = (float)($row['trips'] ?? 0);
        $pax     = (float)($row['passengers'] ?? 0);
        $perf    = $trips > 0 ? $rev / $trips : 0.0;

        $byMetric = [
            'revenue'     => $rev,
            'trips'       => $trips,
            'passengers'  => $pax,
            'performance' => $perf,
        ];

        $v = (float)($byMetric[$metricWord] ?? 0);
        if ($v > 0) {
            $nonZero[] = [
                'driver'   => (string)($row['driver'] ?? ''),
                'driver_id'=> (string)($row['driver_id'] ?? ''),
                'value'    => $v,
                'row'      => $row,
            ];
        }
    }

    if (empty($nonZero)) {
        return
            "\nVERIFIED TOP-PERFORMER VERDICT (computed by the system from "
            . "driver_performance, not the model):\n"
            . "- None of the drivers have a non-zero value for metric '"
            . $metricWord . "' in the selected period, so there is NO top "
            . "performer to report for that metric right now. Do not name "
            . "anyone as the best on this metric.";
    }

    $best = null;

    foreach ($nonZero as $cand) {
        if ($best === null || $cand['value'] > $best['value']) {
            $best = $cand;
        }
    }

    return
        "\nVERIFIED TOP-PERFORMER VERDICT (computed by the system from "
        . "driver_performance, not the model):\n"
        . "- The top driver for the metric '"
        . $metricWord
        . "' is **"
        . $best['driver']
        . '** (driver_id: '
        . $best['driver_id']
        . ') with a value of '
        . ($metricWord === 'trips' || $metricWord === 'passengers'
            ? number_format((float)$best['value'], 0) . ' in \'' . $metricWord . '\''
            : (($metricWord === 'revenue' ? '₱' : '') . number_format((float)$best['value'], 2)))
        . ".\n"
        . "- Report exactly this driver as the top performer, and show "
        . "only a short comparison from the OTHER verified driver_performance "
        . "rows. Never pick a different name and never invent figures.\n"
        . "- VERIFIED VERDICT:\n"
        . json_encode($best['row'], JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
}

/*
|--------------------------------------------------------------------------
| BUILD VERIFIED DRIVER LEADERBOARD
|--------------------------------------------------------------------------
|
| Deterministic per-driver snapshot injected into EVERY analytics prompt
| that has driver_performance rows, so the model never has to read the
| raw JSON to state a driver's trips / revenue / passengers (that is
| where hallucinated or misaligned figures come from). The top driver
| for each metric and the compact per-driver figures are both computed
| in PHP from the exact rows the dashboard query returns.
|
*/

private function buildDriverLeaderboard(array $perfRows): string
{
    $rows = [];

    foreach ($perfRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $trips   = (int)($row['trips'] ?? 0);
        $revenue = (float)($row['revenue'] ?? $row['income'] ?? 0);
        $pax     = (int)($row['passengers'] ?? 0);

        if ($trips === 0 && $revenue == 0.0 && $pax === 0) {
            continue;
        }

        $rows[] = [
            'driver'      => (string)($row['driver'] ?? ''),
            'driver_id'   => (string)($row['driver_id'] ?? ''),
            'trips'       => $trips,
            'revenue'     => $revenue,
            'passengers'  => $pax,
            'performance' => $trips > 0 ? round($revenue / $trips, 2) : 0.0,
        ];
    }

    if (empty($rows)) {
        return '';
    }

    usort($rows, function ($a, $b) {
        return $b['revenue'] <=> $a['revenue'];
    });

    $best = [
        'revenue'     => null,
        'trips'       => null,
        'passengers'  => null,
        'performance' => null,
    ];

    foreach ($rows as $r) {
        foreach (['revenue', 'trips', 'passengers', 'performance'] as $m) {
            $v = $r[$m] > 0 ? (float)$r[$m] : -1;
            if ($best[$m] === null || $v > $best[$m]['value']) {
                $best[$m] = ['driver' => $r['driver'], 'value' => $v];
            }
        }
    }

    $lines = [];

    $lines[] =
        "\nVERIFIED DRIVER LEADERBOARD (computed by the system from "
        . "driver_performance, the same dataset as the dashboard; authoritative). "
        . "State driver numbers ONLY from this section:";

    if ($best['revenue'] !== null) {
        $lines[] =
            '- Top revenue: **' . $best['revenue']['driver']
            . '** — ₱' . number_format($best['revenue']['value'], 2);
    }
    if ($best['trips'] !== null) {
        $lines[] =
            '- Top trips: **' . $best['trips']['driver']
            . '** — ' . number_format($best['trips']['value'], 0) . ' trips';
    }
    if ($best['passengers'] !== null) {
        $lines[] =
            '- Top passengers: **' . $best['passengers']['driver']
            . '** — ' . number_format($best['passengers']['value'], 0)
            . ' passengers';
    }
    if ($best['performance'] !== null) {
        $lines[] =
            '- Top per-trip revenue: **' . $best['performance']['driver']
            . '** — ₱' . number_format($best['performance']['value'], 2)
            . '/trip';
    }

    $lines[] =
        'Compact verified figures (driver | trips | revenue | passengers | per-trip):';

    foreach (array_slice($rows, 0, 10) as $r) {
        $lines[] =
            '- ' . $r['driver']
            . ' (id: ' . $r['driver_id'] . '): '
            . $r['trips'] . ' trips | ₱'
            . number_format($r['revenue'], 2)
            . ' | ' . $r['passengers']
            . ' passengers | ₱' . number_format($r['performance'], 2) . '/trip';
    }

    return implode("\n", $lines) . "\n";
}

}

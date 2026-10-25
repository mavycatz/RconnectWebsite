<?php

namespace Finance\Services\AI;

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/auth/Auth.php';

use App\Auth\Auth;

/*
|--------------------------------------------------------------------------
| SYSTEM KNOWLEDGE SERVICE
|--------------------------------------------------------------------------
|
| This service is Riza's source of knowledge about the actual Finance
| module. Every page, route, menu label, workflow step, and permission
| below was taken from the existing Finance implementation:
|
|   - pages:   finance/views/* (page titles and workflows)
|   - routes:  finance/routes/*, finance/{page}.php entry files
|   - menu:    finance/views/layout/navbarfinance.php
|   - roles:   finance/index.php, finance/controllers/* role checks,
|              finance/middleware/auth.php
|
| Database questions are handled separately (AIAnalyticsModel). This
| service only answers "where is it / how do I / what is it" questions
| and produces navigation buttons to real pages.
*/

class AISystemKnowledgeService
{
    /*
    |--------------------------------------------------------------------------
    | PAGE REGISTRY
    |--------------------------------------------------------------------------
    */

    public function getPages(): array
    {
        return [

            'remittance' => [
                'name' => 'Remittance',
                'menu' => 'Finance → Remittance',
                'route' => '/finance/remittance.php',
                'navLabel' => 'Open Remittance',
                'action' => 'Submit a Remittance',
                'description' => "Review and verify today's driver remittance records.",
                'features' => [
                    'Review driver remittance records with Pending / Completed / All status filters.',
                    'Enter the actual cash remitted for each trip.',
                    'Submit remittances using the finance authorization code.',
                    'Mark a driver as no-show when they did not remit.',
                    'Download a CSV report of the remittance records.'
                ],
                'steps' => [
                    'Choose a driver from the list on the left (Pending / Completed / All filters are available).',
                    'Enter the **actual cash remitted** for each trip in the Actual Remittance column.',
                    'Click **Submit Remittance**.',
                    'Enter the **finance authorization code** and click **Confirm Remittance**.',
                    'Use **Mark No-Show** when a driver with trips did not remit, or **Download Report** to export a CSV.'
                ],
                'keywords' => [
                    'remit', 'remittance', 'remittances',
                    'remitted', 'submit remittance'
                ],
                'roles' => ['finance_manager', 'finance_officer']
            ],

            'history' => [
                'name' => 'Remittance History',
                'menu' => 'Finance → Remittance History',
                'route' => '/finance/history.php',
                'navLabel' => 'Open Remittance History',
                'action' => 'Check Remittance History',
                'description' => 'View past remittance submissions, trip details, receipts, and OTP verification records.',
                'features' => [
                    'Filter past remittances by date or driver.',
                    'Open a record to view trip details, the receipt, and OTP verification.'
                ],
                'steps' => [
                    'Use the filters to find a specific date or driver.',
                    'Open a record to view trip details, the receipt, or OTP verification.'
                ],
                'keywords' => [
                    'remittance history', 'history of remittance',
                    'remittance records', 'past remittances',
                    'submission history'
                ],
                'roles' => ['finance_manager', 'finance_officer']
            ],

            'discrepancies' => [
                'name' => 'Discrepancy Monitoring',
                'menu' => 'Finance → Discrepancies',
                'route' => '/finance/discrepancies.php',
                'navLabel' => 'View Discrepancies',
                'action' => 'Check Discrepancies',
                'description' => 'Monitor remittance shortages and overages (expected vs. actual cash) across drivers and trips.',
                'features' => [
                    'Review remittance shortages and overages (expected vs actual cash).',
                    'Open a record to see the driver, trip, and amounts involved.'
                ],
                'steps' => [
                    'Review the shortage and overage records.',
                    'Open a record to see the driver, trip, and amounts involved.'
                ],
                'keywords' => [
                    'discrepancy', 'discrepancies',
                    'shortage', 'overage', 'missing cash'
                ],
                'roles' => ['finance_manager', 'finance_officer']
            ],

            'online-payment-verification' => [
                'name' => 'Online Payment Verification',
                'menu' => 'Finance → Online Payment Verification',
                'route' => '/finance/online_payment_verification.php',
                'navLabel' => 'Verify Online Payments',
                'action' => 'Verify an Online Payment',
                'description' => 'Verify GCash and other online payment records submitted by passengers.',
                'features' => [
                    'Review GCash and online payment records.',
                    'Confirm or reject payments based on the provided proof.'
                ],
                'steps' => [
                    'Review the payment record.',
                    'Confirm or reject the payment based on the provided proof.'
                ],
                'keywords' => [
                    'online payment', 'online payments',
                    'gcash verification', 'payment verification',
                    'verify payment', 'payment records'
                ],
                'roles' => ['finance_manager', 'finance_officer']
            ],

            'revenue' => [
                'name' => 'Profit Overview',
                'menu' => 'Finance → Profit Overview',
                'route' => '/finance/revenueOverview.php',
                'navLabel' => 'Open Profit Overview',
                'action' => 'View Profit Overview',
                'description' => 'Revenue and profit analysis with route profitability, bus performance, location insights, and time-based trends.',
                'features' => [
                    'Filter revenue by period, route, or bus.',
                    'View revenue stats, charts, route profitability, and time-based trends.'
                ],
                'steps' => [
                    'Use the filters to view revenue by period, route, or bus.',
                    'Review the revenue stats, main chart, and insights.'
                ],
                'keywords' => [
                    'revenue', 'profit overview', 'revenue overview',
                    'profit', 'income'
                ],
                'roles' => ['finance_manager', 'finance_officer']
            ],

            'fuel' => [
                'name' => 'Fuel & Trip Cost Analytics',
                'menu' => 'Finance → Fuel Cost',
                'route' => '/finance/fuel_cost_analytics.php',
                'navLabel' => 'View Fuel Costs',
                'action' => 'Check Fuel Costs',
                'description' => 'Fuel and trip cost analytics: daily fuel trends, fuel per bus, expense breakdown, and anomalies.',
                'features' => [
                    'Review daily fuel trends and fuel per bus.',
                    'Check expense breakdowns and flagged anomalies.'
                ],
                'steps' => [
                    'Review the daily fuel trend and fuel-per-bus charts.',
                    'Check the expense breakdown and anomaly tables.'
                ],
                'keywords' => [
                    'fuel', 'fuel cost', 'fuel costs', 'fuel price'
                ],
                'roles' => ['finance_manager', 'finance_officer']
            ],

            'financial-reports' => [
                'name' => 'Financial Reports',
                'menu' => 'Finance → Financial Reports',
                'route' => '/finance/financialreports.php',
                'navLabel' => 'Open Financial Reports',
                'action' => 'Generate a Financial Report',
                'description' => 'Create, view, and manage financial reports for the cooperative.',
                'features' => [
                    'Choose a report type and generate financial reports.',
                    'View and manage the generated reports.'
                ],
                'steps' => [
                    'Choose the report type.',
                    'Click the **Generate** action to create the report.'
                ],
                'keywords' => [
                    'financial report', 'financial reports',
                    'generate report'
                ],
                'roles' => ['finance_manager', 'finance_officer'],
                'managerOnly' => true
            ],

            'fleet-performance' => [
                'name' => 'Fleet & Route Performance',
                'menu' => 'Finance → Fleet Performance',
                'route' => '/finance/fleet_performance.php',
                'navLabel' => 'Open Fleet Performance',
                'action' => 'Check Fleet Performance',
                'description' => 'Fleet and route performance: trip completion, efficiency, passenger route distribution, and revenue per bus.',
                'features' => [
                    'Review fleet stats and trip completion.',
                    'Check rider composition and revenue per bus.'
                ],
                'steps' => [
                    'Review the fleet stats cards and trip completion table.',
                    'Check rider composition and revenue per bus.'
                ],
                'keywords' => [
                    'fleet', 'fleet performance', 'route performance'
                ],
                'roles' => ['finance_manager', 'finance_officer']
            ],

            'exports-audits' => [
                'name' => 'Exports and Audits',
                'menu' => 'Finance → Exports and Audits',
                'route' => '/finance/exportandaudits.php',
                'navLabel' => 'Open Exports and Audits',
                'action' => 'Export a Report',
                'description' => 'Export financial reports and view the audit trail of financial activity.',
                'features' => [
                    'Choose an export type: reports, logs, or audit trail.',
                    'Generate exports and review the audit records.'
                ],
                'steps' => [
                    'Choose the export type (reports, logs, or audit trail).',
                    'Generate the export or review the audit records.'
                ],
                'keywords' => [
                    'export', 'exports', 'audit', 'audits',
                    'audit trail'
                ],
                'roles' => ['finance_manager', 'finance_officer'],
                'managerOnly' => true
            ],

            'documentation' => [
                'name' => 'Documentation',
                'menu' => 'Finance → Documentation',
                'route' => '/finance/documentation.php',
                'navLabel' => 'Open Documentation',
                'action' => 'Read the Documentation',
                'description' => 'User documentation and guides for the Finance module.',
                'features' => [
                    'Browse the available guides and articles for the Finance module.'
                ],
                'steps' => [
                    'Browse the available guides and articles.'
                ],
                'keywords' => [
                    'documentation', 'manual', 'user guide',
                    'help guide'
                ],
                'roles' => ['finance_manager', 'finance_officer']
            ],

            'dashboard' => [
                'name' => 'Finance Dashboard',
                'menu' => 'Finance → Dashboard',
                'route' => '/finance/dashboard.php',
                'navLabel' => 'Open Finance Dashboard',
                'action' => 'View the Dashboard',
                'description' => 'The main Finance dashboard with revenue, costs, driver performance, and recent alerts at a glance.',
                'features' => [
                    'See gross income, expenses, and net profit at a glance.',
                    'Check top performing drivers and recent alerts.',
                    'Review revenue and cost charts, driver performance, and monthly cost tables.'
                ],
                'keywords' => [
                    'dashboard', 'finance home', 'main dashboard',
                    'admin dashboard', 'finance overview', 'overview'
                ],
                'steps' => [
                    'Review the summary stats and charts.',
                    'Check driver performance and recent alerts.'
                ],
                'roles' => ['finance_manager', 'finance_officer']
            ],

            'download-reports' => [
                'name' => 'Download Reports',
                'menu' => 'Finance → Download Reports',
                'route' => '/finance/downloadreports.php',
                'navLabel' => 'Open Download Reports',
                'action' => 'Download a Report',
                'description' => 'Download generated financial report files.',
                'features' => [
                    'Download generated financial report files.'
                ],
                'steps' => [
                    'Choose the report file to download.'
                ],
                'keywords' => [
                    'download report', 'download reports'
                ],
                'roles' => ['finance_manager', 'finance_officer']
            ]
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | IS DATA QUESTION
    |--------------------------------------------------------------------------
    |
    | True when the message asks for numbers, totals, or recorded
    | values. Used to separate "where/how" guidance from database
    | questions even when the same keyword appears in both.
    |
    */

    public function isDataQuestion($message): bool
    {
        $text = ' '
            . strtolower(
                preg_replace(
                    '/[^a-z0-9\s]/i',
                    ' ',
                    $message
                )
            )
            . ' ';

        return
            preg_match(
                '/\b(how much|how many|was|were|total|amount|sum|count|percentage|average|value|number of|records)\b/',
                $text
            ) === 1;
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT GUIDANCE QUESTION
    |--------------------------------------------------------------------------
    |
    | Returns ['key', 'intent', 'page'] when the message asks where a
    | feature is, how to use it, or what it is. Returns null for data
    | questions (which are handled by the database router).
    |
    */

    public function detectGuidance($message): ?array
    {
        $text = ' '
            . strtolower(
                preg_replace(
                    '/[^a-z0-9\s]/i',
                    ' ',
                    $message
                )
            )
            . ' ';

        /*
        |--------------------------------------------------------------------------
        | INTENT
        |--------------------------------------------------------------------------
        */

        $intent = null;

        $patterns = [
            'where' => '/\b(where|which page|which section|where is)\b/',
            'how' => '/\b(how do i|how do you|how can i|how to|steps|step by step|walk me through|guide me|instructions)\b/',
            'go' => '/\b(open|go to|take me to|navigate|bring me to|show me where)\b/',
            'want' => '/\b(i want to|i need to|i would like to)\b/',
            'what' => '/\b(tell me about|explain|meaning of|purpose of|what does|what do|what is|what are|what\'?s|describe)\b/'
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text)) {
                $intent = $key;
                break;
            }
        }

        if ($intent === null) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | DATA QUESTIONS MUST NOT BECOME GUIDANCE
        |--------------------------------------------------------------------------
        |
        | "What was our revenue in 2026?" and "How much did we remit?"
        | are database questions. Only the "what" intent is ambiguous,
        | so block it when the message asks for numbers or totals.
        | "where / how / go" intents are unambiguous guidance signals.
        |
        */

        if (
            $intent === 'what'
            &&
            $this->isDataQuestion($message)
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | FEATURE MATCH
        |--------------------------------------------------------------------------
        */

        foreach ($this->getPages() as $key => $page) {
            foreach ($page['keywords'] as $keyword) {
                if (
                    strpos(
                        $text,
                        ' '
                        . $keyword
                        . ' '
                    ) !== false
                ) {
                    return [
                        'key' => $key,
                        'intent' => $intent,
                        'page' => $page
                    ];
                }
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | IS REPEAT REQUEST
    |--------------------------------------------------------------------------
    |
    | "again", "repeat that", "same thing" ask Riza to repeat the
    | immediately previous meaningful answer. Only the whole message
    | counts: "again" buried inside a longer question ("When can I
    | remit again?") is NOT a repeat request.
    |
    */

    public function isRepeatRequest($message): bool
    {
        $text = strtolower(
            preg_replace(
                '/[^a-z\s]/i',
                ' ',
                $message
            )
        );

        $text = trim(
            preg_replace(
                '/\s+/',
                ' ',
                $text
            )
        );

        return preg_match(
            '/^(again|repeat|repeat that|repeat it|repeat this|can you repeat that|please repeat that|tell me again|show that again|show it again|say that again|say it again|can you show that again|do that again|do it again|same thing|same again|once more|one more time)$/',
            $text
        ) === 1;
    }

    /*
    |--------------------------------------------------------------------------
    | DETECT EXPLICIT FEATURE (TOPIC SWITCH)
    |--------------------------------------------------------------------------
    |
    | True when the message names a specific Finance destination even
    | without an instruction verb ("what about on dashboard?", "how
    | about fuel costs?"). Used to detect a topic change: the answer
    | must be built for the NEW page, not the previous topic.
    |
    | Page iteration order matches the registry, so more specific
    | keywords (e.g. "revenue overview") win over generic ones
    | (e.g. "overview" -> dashboard).
    |
    */

    public function detectExplicitFeature($message): ?array
    {
        $text = ' '
            . strtolower(
                preg_replace(
                    '/[^a-z0-9\s]/i',
                    ' ',
                    $message
                )
            )
            . ' ';

        foreach ($this->getPages() as $key => $page) {
            foreach ($page['keywords'] as $keyword) {
                if (
                    strpos(
                        $text,
                        ' '
                        . $keyword
                        . ' '
                    ) !== false
                ) {
                    return [
                        'key' => $key,
                        'page' => $page
                    ];
                }
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | IS FOLLOW-UP REFERENCE
    |--------------------------------------------------------------------------
    |
    | True when the message refers back to something already discussed
    | ("a link", "it", "one", "that", "open it") instead of introducing
    | a new topic. Used to resolve follow-ups like "Can you give me a
    | link?" against the previous guidance topic.
    |
    */

    public function isFollowUpReference($message): bool
    {
        $text = ' '
            . strtolower(
                preg_replace(
                    '/[^a-z0-9\s]/i',
                    ' ',
                    $message
                )
            )
            . ' ';

        return
            preg_match(
                '/\b(link|url|address|it|one|that|this|these|those|them|there|here|same|open it|take me|go there|show me|this page|this section|this report|this record|what about|how about)\b/',
                $text
            ) === 1;
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD FOLLOW-UP ANSWER
    |--------------------------------------------------------------------------
    |
    | Short answer for a message that refers back to the previous topic.
    | "Can you give me a link?" -> "Sure. You can open the Remittance
    | page here: [Open Remittance →]". "How do I submit one?" -> the
    | full step-by-step instructions for that page.
    |
    */

    public function buildFollowUpAnswer(string $message, array $guide): string
    {
        $page = $guide['page'];
        $name = $page['name'];

        /*
        |--------------------------------------------------------------------------
        | NO PERMISSION
        |--------------------------------------------------------------------------
        */

        if (!self::canAccess($page)) {
            return '**'
                . $name
                . "**\n\n"
                . $name
                . ' is available under the Finance module, but your current account does not have permission to access that section.';
        }

        $text = ' '
            . strtolower(
                preg_replace(
                    '/[^a-z0-9\s]/i',
                    ' ',
                    $message
                )
            )
            . ' ';

        /*
        |--------------------------------------------------------------------------
        | "HOW DO I SUBMIT ONE?" -> STEP-BY-STEP
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/\b(how do i|how to|steps|step by step|instructions|submit)\b/',
                $text
            )
        ) {

            return $this->buildAnswer([
                'key' => $guide['key'],
                'intent' => 'how',
                'page' => $page
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | "WHAT CAN I DO HERE?" -> ACTUAL PAGE CAPABILITIES
        |--------------------------------------------------------------------------
        |
        | Resolves "here / this page / this one" to the previous topic
        | and lists what the real Finance page allows, never invented.
        |
        */

        if (
            preg_match(
                '/\b(what can i do|what can i see|what can i find|what does this|what does it|what is this|what is it|what is there|what is on|what about|how about|tell me about|capabilit|this page|this section|this report|this record)\b/',
                $text
            )
        ) {

            $lines = [
                '**' . $name . '**',
                '',
                'On this page you can:'
            ];

            foreach ($page['features'] ?? [] as $feature) {
                $lines[] = '';
                $lines[] = '- ' . $feature;
            }

            $lines[] = '';
            $lines[] = $this->navButton($page);

            return implode("\n", $lines);
        }

        /*
        |--------------------------------------------------------------------------
        | "CAN YOU GIVE ME A LINK?" -> LINK ONLY
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/\b(link|url|address)\b/',
                $text
            )
        ) {

            return 'Sure. You can open the **'
                . $name
                . '** page here:'
                . "\n\n"
                . $this->navButton($page);
        }

        /*
        |--------------------------------------------------------------------------
        | GENERIC REFERENCE ("OPEN IT", "TAKE ME THERE")
        |--------------------------------------------------------------------------
        */

        return 'You can open it here:'
            . "\n\n"
            . $this->navButton($page);
    }

    /*
    |--------------------------------------------------------------------------
    | PERMISSION CHECK
    |--------------------------------------------------------------------------
    */

    public static function canAccess(array $page): bool
    {
        $role = Auth::role();

        if (empty($role)) {
            return false;
        }

        return in_array(
            $role,
            $page['roles'] ?? [],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NAVIGATION BUTTON (MARKDOWN LINK)
    |--------------------------------------------------------------------------
    */

    public function navButton(array $page): string
    {
        return '['
            . $page['navLabel']
            . ' →]('
            . $page['route']
            . ')';
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD GUIDANCE ANSWER
    |--------------------------------------------------------------------------
    */

    public function buildAnswer(array $guide): string
    {
        $page = $guide['page'];
        $intent = $guide['intent'];
        $name = $page['name'];
        $menu = $page['menu'];

        /*
        |--------------------------------------------------------------------------
        | NO PERMISSION
        |--------------------------------------------------------------------------
        */

        if (!self::canAccess($page)) {
            return '**'
                . $name
                . "**\n\n"
                . $name
                . ' is available under the Finance module, but your current account does not have permission to access that section.';
        }

        $lines = [];

        /*
        |--------------------------------------------------------------------------
        | WHERE / GO
        |--------------------------------------------------------------------------
        |
        | Navigation requests stay short: page name, location, button.
        | No extra explanation unless the user asks how to do something.
        |
        */

        if ($intent === 'where' || $intent === 'go') {

            $lines[] = '**'
                . $name
                . '**';
            $lines[] = '';
            $lines[] = 'You can find **'
                . $name
                . '** under **'
                . $menu
                . '**.';
            $lines[] = '';
            $lines[] = $this->navButton($page);

        /*
        |--------------------------------------------------------------------------
        | HOW / WANT
        |--------------------------------------------------------------------------
        */

        } elseif ($intent === 'how' || $intent === 'want') {

            $lines[] = '**How to '
                . $page['action']
                . '**';
            $lines[] = '';
            $lines[] = 'You can do this under **'
                . $menu
                . '**.';
            $lines[] = '';
            $lines[] = '1. Open **Finance** from the main navigation.';
            $lines[] = '2. Select **'
                . $name
                . '** from the menu.';

            $stepNumber = 3;

            foreach ($page['steps'] as $step) {
                $lines[] = $stepNumber
                    . '. '
                    . $step;
                $stepNumber++;
            }

            $lines[] = '';
            $lines[] = $this->navButton($page);

        /*
        |--------------------------------------------------------------------------
        | WHAT IS
        |--------------------------------------------------------------------------
        */

        } else {

            $lines[] = '**'
                . $name
                . '**';
            $lines[] = '';
            $lines[] = $page['description']
                . ' It is available under **'
                . $menu
                . '**.';
            $lines[] = '';
            $lines[] = 'On this page you can:';

            foreach ($page['features'] ?? [] as $feature) {
                $lines[] = '';
                $lines[] = '- ' . $feature;
            }

            $lines[] = '';
            $lines[] = $this->navButton($page);
        }

        /*
        |--------------------------------------------------------------------------
        | MANAGER-ONLY NOTE
        |--------------------------------------------------------------------------
        */

        if (
            !empty($page['managerOnly'])
            &&
            Auth::role() !== 'finance_manager'
        ) {
            $lines[] = '';
            $lines[] = 'Note: The **generate/export** action on this page requires a **Finance Manager** account.';
        }

        return implode("\n", $lines);
    }
}

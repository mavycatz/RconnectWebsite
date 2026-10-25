<?php

declare(strict_types=1);

namespace Admin\Controllers;

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/PrintLogsModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/LogPDFServices.php';

use Admin\Models\PrintLogsModel;
use Admin\Services\LogPDFService;

class PrintLogsController
{
    /*
    |--------------------------------------------------------------------------
    | MODEL
    |--------------------------------------------------------------------------
    */

    private PrintLogsModel $model;

    /*
    |--------------------------------------------------------------------------
    | PDF SERVICE
    |--------------------------------------------------------------------------
    */

    private LogPDFService $pdfService;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        $this->model =
            new PrintLogsModel();

        $this->pdfService =
            new LogPDFService();
    }

    /*
    |--------------------------------------------------------------------------
    | GET LOGS
    |--------------------------------------------------------------------------
    */

    public function getLogs(
        bool $generatePdf = false,
        string $period = 'month'
    ): array
    {
        try{

            /*
            |--------------------------------------------------------------------------
            | FETCH LOGS
            |--------------------------------------------------------------------------
            */

            $logs =
                $this->model->getLogs($period);

            /*
            |--------------------------------------------------------------------------
            | COUNTS
            |--------------------------------------------------------------------------
            */

            $securityCount = 0;
            $loginCount = 0;
            $sessionCount = 0;
            $auditCount = 0;

            foreach($logs as $log){
                switch($log['source']){
                    case 'security':
                        $securityCount++;
                        break;
                    case 'login':
                        $loginCount++;
                        break;
                    case 'session':
                        $sessionCount++;
                        break;
                    case 'audit':
                        $auditCount++;
                        break;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

            $response = [

                'success' => true,

                'logs'    => $logs,

                'security_count' => $securityCount,

                'login_count'    => $loginCount,

                'session_count'  => $sessionCount,
                'audit_count'    => $auditCount,

                'period'  => $period,
                'pdf'     => null
            ];

            /*
            |--------------------------------------------------------------------------
            | PDF (ONLY WHEN EXPLICITLY REQUESTED)
            |--------------------------------------------------------------------------
            */

            if($generatePdf){

                $response['pdf'] =

                    $this->generateReportPdf($logs, $period);
            }

            return $response;

        }catch(\Throwable $e){

            /*
            |--------------------------------------------------------------------------
            | ERROR RESPONSE
            |--------------------------------------------------------------------------
            */

            return [

                'success' => false,

                'message' => $e->getMessage()
            ];
        }
    }

    private function generateReportPdf(
        array $logs,
        string $period = 'month'
    ): string {

        /*
        |--------------------------------------------------------------------------
        | BUILD STRUCTURED REPORT FROM LOG DATA
        |--------------------------------------------------------------------------
        */

        $securityLogs = array_filter($logs, fn($l) =>
            ($l['source'] ?? '') === 'security'
        );

        $loginLogs = array_filter($logs, fn($l) =>
            ($l['source'] ?? '') === 'login'
        );

        $sessionLogs = array_filter($logs, fn($l) =>
            ($l['source'] ?? '') === 'session'
        );

        $auditLogs = array_filter($logs, fn($l) =>
            ($l['source'] ?? '') === 'audit'
        );

        $periodLabel = 'This Month';

        switch($period){
            case 'today':    $periodLabel = 'Today'; break;
            case 'week':     $periodLabel = 'This Week'; break;
            case 'month':    $periodLabel = 'This Month'; break;
            case '6months':  $periodLabel = 'Last 6 Months'; break;
        }

        /*
        |--------------------------------------------------------------------------
        | USER ACTIVITY ANALYSIS
        |--------------------------------------------------------------------------
        */

        $userActivity = [];
        $unrecognizedIps = [];
        $knownUsers = [];

        foreach($logs as $l){
            $user = $l['printed_by'] ?? 'Unknown';
            $ip = $l['ip'] ?? '';

            if(!isset($userActivity[$user])){
                $userActivity[$user] = 0;
            }
            $userActivity[$user]++;

            if($user !== 'Unknown' && $user !== 'User ID: Unknown'){
                $knownUsers[$ip] = $user;
            }
        }

        arsort($userActivity);
        $topUsers = array_slice($userActivity, 0, 5, true);

        foreach($logs as $l){
            $ip = $l['ip'] ?? '';
            $user = $l['printed_by'] ?? 'Unknown';
            if($ip !== '' && !isset($knownUsers[$ip]) && ($user === 'Unknown' || $user === 'User ID: Unknown')){
                $unrecognizedIps[$ip] = ($unrecognizedIps[$ip] ?? 0) + 1;
            }
        }

        arsort($unrecognizedIps);

        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        $summary = sprintf(
            'System activity report covering %d security events, %d login attempts, %d active sessions, and %d audit logs. Period: %s.',
            count($securityLogs),
            count($loginLogs),
            count($sessionLogs),
            count($auditLogs),
            $periodLabel
        );

        /*
        |--------------------------------------------------------------------------
        | ANALYSIS
        |--------------------------------------------------------------------------
        */

        $analysis = [];

        if(!empty($securityLogs)){
            $analysis[] = sprintf(
                '%d security events were logged, indicating authentication-related activity across the system.',
                count($securityLogs)
            );

            $eventTypes = [];
            foreach($securityLogs as $sl){
                $event = $sl['report'] ?? 'Unknown';
                if(!isset($eventTypes[$event])){
                    $eventTypes[$event] = 0;
                }
                $eventTypes[$event]++;
            }
            arsort($eventTypes);
            $topEvent = array_key_first($eventTypes);
            $analysis[] = sprintf(
                'Most common security event: "%s" (%d occurrences).',
                $topEvent,
                $eventTypes[$topEvent]
            );
        }

        if(!empty($loginLogs)){
            $successCount = count(array_filter($loginLogs, fn($l) =>
                $l['status'] === 'Success'
            ));
            $failedCount = count(array_filter($loginLogs, fn($l) =>
                $l['status'] === 'Failed'
            ));
            $analysis[] = sprintf(
                '%d login attempts recorded: %d successful, %d failed.',
                count($loginLogs),
                $successCount,
                $failedCount
            );

            if($failedCount > 0){
                $loginIps = [];
                foreach($loginLogs as $ll){
                    if($ll['status'] === 'Failed' && ($ll['ip'] ?? '') !== ''){
                        $ip = $ll['ip'];
                        if(!isset($loginIps[$ip])){
                            $loginIps[$ip] = 0;
                        }
                        $loginIps[$ip]++;
                    }
                }
                arsort($loginIps);
                $topFailedIp = array_key_first($loginIps);
                if($topFailedIp !== null){
                    $analysis[] = sprintf(
                        'IP address "%s" had the most failed login attempts (%d).',
                        $topFailedIp,
                        $loginIps[$topFailedIp]
                    );
                }
            }
        }

        if(!empty($sessionLogs)){
            $analysis[] = sprintf(
                '%d active sessions were detected across the system.',
                count($sessionLogs)
            );
        }

        if(!empty($auditLogs)){
            $analysis[] = sprintf(
                '%d audit log entries were recorded across the system.',
                count($auditLogs)
            );

            $moduleActivity = [];
            foreach($auditLogs as $al){
                $module = explode(' - ', ($al['report'] ?? ''))[0] ?? 'Unknown';
                if(!isset($moduleActivity[$module])){
                    $moduleActivity[$module] = 0;
                }
                $moduleActivity[$module]++;
            }
            arsort($moduleActivity);
            $topModule = array_key_first($moduleActivity);
            if($topModule !== null){
                $analysis[] = sprintf(
                    'Most active audit module: "%s" (%d entries).',
                    $topModule,
                    $moduleActivity[$topModule]
                );
            }
        }

        if(!empty($unrecognizedIps)){
            $analysis[] = sprintf(
                '%d unique IP addresses were detected without an associated user account, indicating potential external or unauthenticated access.',
                count($unrecognizedIps)
            );
        }

        if(!empty($topUsers)){
            $topUser = array_key_first($topUsers);
            $analysis[] = sprintf(
                'Most active user: "%s" (%d activities logged).',
                $topUser,
                $topUsers[$topUser]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | INSIGHTS
        |--------------------------------------------------------------------------
        */

        $insights = [];

        $totalIps = count(array_unique(array_column($logs, 'ip')));

        if(!empty($loginLogs)){
            $loginRate = count($loginLogs) > 0
                ? round((count(array_filter($loginLogs, fn($l) =>
                    $l['status'] === 'Success'
                )) / count($loginLogs)) * 100)
                : 0;
            $insights[] = sprintf(
                'Login success rate: %d%% across %d total attempts.',
                $loginRate,
                count($loginLogs)
            );
        }

        $insights[] = sprintf(
            'Total unique IP addresses tracked: %d.',
            $totalIps
        );

        if(!empty($unrecognizedIps)){
            $percentUnrecognized = $totalIps > 0
                ? round((count($unrecognizedIps) / $totalIps) * 100)
                : 0;
            $insights[] = sprintf(
                '%d%% of unique IPs (%d of %d) have no linked user — these may be scanners, brute-force bots, or misconfigured clients.',
                $percentUnrecognized,
                count($unrecognizedIps),
                $totalIps
            );
        }

        if(!empty($topUsers)){
            $userEntries = [];
            foreach($topUsers as $u => $count){
                $userEntries[] = sprintf('%s (%d)', $u, $count);
            }
            $insights[] = sprintf(
                'Top active users: %s.',
                implode('; ', $userEntries)
            );
        }

        if(!empty($securityLogs)){
            $insights[] = sprintf(
                'Security events represent %d%% of all logged activity.',
                count($logs) > 0
                    ? round((count($securityLogs) / count($logs)) * 100)
                    : 0
            );
        }

        $allIps = array_column($logs, 'ip');
        $ipUserMap = [];
        foreach($logs as $l){
            $ip = $l['ip'] ?? '';
            $user = $l['printed_by'] ?? 'Unknown';
            if($ip !== ''){
                if(!isset($ipUserMap[$ip])){
                    $ipUserMap[$ip] = [];
                }
                if(!in_array($user, $ipUserMap[$ip])){
                    $ipUserMap[$ip][] = $user;
                }
            }
        }
        $sharedIps = array_filter($ipUserMap, fn($users) => count($users) > 1);
        if(!empty($sharedIps)){
            $insights[] = sprintf(
                '%d IP addresses are shared by multiple users — possible proxy or NAT environment.',
                count($sharedIps)
            );
        }

        /*
        |--------------------------------------------------------------------------
        | RECOMMENDATIONS
        |--------------------------------------------------------------------------
        */

        $recommendations = [];

        $hasFailedLogins = !empty(array_filter($loginLogs, fn($l) =>
            strpos($l['report'] ?? '', 'Failed') !== false
        ));

        if($hasFailedLogins){
            $failedCount = count(array_filter($loginLogs, fn($l) =>
                $l['status'] === 'Failed'
            ));
            $recommendations[] = sprintf(
                'Investigate %d failed login attempts and consider additional authentication measures such as rate-limiting or MFA.',
                $failedCount
            );
        }

        if(count($sessionLogs) > 20){
            $recommendations[] =
                'High number of active sessions — review session timeout and concurrency policies.';
        }

        if(!empty($unrecognizedIps)){
            $topUnrecIp = array_key_first($unrecognizedIps);
            $recommendations[] = sprintf(
                '%d unrecognized IPs detected (top: %s). Consider reviewing firewall rules and blocking known malicious ranges.',
                count($unrecognizedIps),
                $topUnrecIp
            );
        }

        if(!empty($securityLogs) && count($securityLogs) > count($loginLogs) * 2){
            $recommendations[] =
                'Security events outnumber login attempts significantly — investigate possible unauthorized access attempts or misconfigured services.';
        }

        $ipLogTotal = [];
        foreach($logs as $l){
            $ip = $l['ip'] ?? '';
            if($ip !== ''){
                if(!isset($ipLogTotal[$ip])){
                    $ipLogTotal[$ip] = 0;
                }
                $ipLogTotal[$ip]++;
            }
        }
        arsort($ipLogTotal);
        $topIp = array_key_first($ipLogTotal);
        if($topIp !== null && ($ipLogTotal[$topIp] > 10)){
            $recommendations[] = sprintf(
                'IP "%s" accounts for %d log entries — monitor this address for suspicious patterns.',
                $topIp,
                $ipLogTotal[$topIp]
            );
        }

        $unrecognizedOnly = array_filter($unrecognizedIps, fn($count, $ip) => $count >= 5, ARRAY_FILTER_USE_BOTH);
        if(!empty($unrecognizedOnly)){
            $recommendations[] = sprintf(
                '%d unrecognized IPs generated 5+ events each — consider blocking or further investigation.',
                count($unrecognizedOnly)
            );
        }

        $report = [
            'summary'         => $summary,
            'analysis'        => $analysis,
            'insights'        => $insights,
            'recommendations' => $recommendations
        ];

        /*
        |--------------------------------------------------------------------------
        | GENERATE PDF
        |--------------------------------------------------------------------------
        */

        return $this->pdfService->generate(
            'Print Logs Report',
            $report
        );
    }
}
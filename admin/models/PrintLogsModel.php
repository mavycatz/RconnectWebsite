<?php

declare(strict_types=1);

namespace Admin\Models;

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Database.php';

use App\Core\Database;
use PDO;

class PrintLogsModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db =
            Database::get();
    }

    public function getLogs(
        string $period = 'month'
    ): array
    {
        $logs = [];

        $since = $this->computeSince($period);

        $params = [];

        $timeFilter = '';

        if ($since !== null) {

            $timeFilter = 'WHERE created_at >= :since';

            $params[':since'] = $since;
        }

        /*
        |--------------------------------------------------------------------------
        | SECURITY LOGS
        |--------------------------------------------------------------------------
        */

        $securitySql = "

            SELECT

                user_id,

                event,

                ip_address,

                created_at

            FROM security_logs

        ";

        if ($timeFilter) {

            $securitySql .= "\n            $timeFilter";
        }

        $securitySql .= "\n
                ORDER BY created_at DESC

                LIMIT 40
        ";

        $securityStmt =

            $this->db->prepare($securitySql);

        $securityStmt->execute($params);

        while(
            $row =
                $securityStmt->fetch(PDO::FETCH_ASSOC)
        ){

            $logs[] = [

                'source' => 'security',

                'report' =>

                    'Security Event - '
                    .
                    ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            $row['event']
                        )
                    ),

                'printed_by' =>

                    'User ID: '
                    .
                    (
                        $row['user_id']
                        ??
                        'Unknown'
                    ),

                'date' =>

                    date(

                        'F d, Y h:i A',

                        strtotime(
                            $row['created_at']
                        )
                    ),

                'status' =>

                    'Security Log',

                'ip' =>

                    $row['ip_address']
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | LOGIN ATTEMPTS
        |--------------------------------------------------------------------------
        */

        $loginSql = "

            SELECT

                user_id,

                ip_address,

                success,

                created_at

            FROM login_attempts

        ";

        if ($timeFilter) {

            $loginSql .= "\n            $timeFilter";
        }

        $loginSql .= "\n
                ORDER BY created_at DESC

                LIMIT 40
        ";

        $loginStmt =

            $this->db->prepare($loginSql);

        $loginStmt->execute($params);

        while(
            $row =
                $loginStmt->fetch(PDO::FETCH_ASSOC)
        ){

            $logs[] = [

                'source' => 'login',

                'report' =>

                    $row['success']
                    ?
                    'Successful Login Attempt'
                    :
                    'Failed Login Attempt',

                'printed_by' =>

                    'User ID: '
                    .
                    (
                        $row['user_id']
                        ??
                        'Unknown'
                    ),

                'date' =>

                    date(

                        'F d, Y h:i A',

                        strtotime(
                            $row['created_at']
                        )
                    ),

                'status' =>

                    $row['success']
                    ?
                    'Success'
                    :
                    'Failed',

                'ip' =>

                    $row['ip_address']
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | SESSIONS
        |--------------------------------------------------------------------------
        */

        $sessionSql = "

            SELECT

                user_id,

                ip_address,

                user_agent,

                last_activity

            FROM sessions

            WHERE user_id IS NOT NULL

        ";

        $sessionParams = [];

        if ($since !== null) {

            $sessionSql .= "
                AND last_activity >= :since_session
            ";

            $sessionParams[':since_session'] = $since;
        }

        $sessionSql .= "
                ORDER BY last_activity DESC

                LIMIT 40
        ";

        $sessionStmt =

            $this->db->prepare($sessionSql);

        $sessionStmt->execute($sessionParams);

        while(
            $row =
                $sessionStmt->fetch(PDO::FETCH_ASSOC)
        ){

            $logs[] = [

                'source' => 'session',

                'report' =>

                    'Active Session Activity',

                'printed_by' =>

                    'User ID: '
                    .
                    (
                        $row['user_id']
                        ??
                        'Unknown'
                    ),

                'date' =>

                    date(

                        'F d, Y h:i A',

                        $row['last_activity']
                    ),

                'status' =>

                    'Session',

                'ip' =>

                    $row['ip_address']
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | AUDIT LOGS
        |--------------------------------------------------------------------------
        */

        $auditSql = "

            SELECT

                module,

                action,

                username,

                ip_address,

                status,

                created_at

            FROM audit_trail

        ";

        $auditParams = [];

        if ($since !== null) {

            $auditSql .= "
                WHERE created_at >= :since_audit
            ";

            $auditParams[':since_audit'] = $since;
        }

        $auditSql .= "
                ORDER BY created_at DESC

                LIMIT 40
        ";

        $auditStmt =

            $this->db->prepare($auditSql);

        $auditStmt->execute($auditParams);

        while(
            $row =
                $auditStmt->fetch(PDO::FETCH_ASSOC)
        ){

            $logs[] = [

                'source' => 'audit',

                'report' =>

                    ucfirst($row['module'])
                    .
                    ' - '
                    .
                    ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            $row['action']
                        )
                    ),

                'printed_by' =>

                    $row['username']
                    ??
                    'Unknown',

                'date' =>

                    date(

                        'F d, Y h:i A',

                        strtotime(
                            $row['created_at']
                        )
                    ),

                'status' =>

                    $row['status']
                    ??
                    'Success',

                'ip' =>

                    $row['ip_address']
                    ??
                    'System'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | SORT ALL LOGS
        |--------------------------------------------------------------------------
        */

        usort(

            $logs,

            function($a, $b){

                return

                    strtotime($b['date'])

                    -

                    strtotime($a['date']);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | LIMIT
        |--------------------------------------------------------------------------
        */

        return array_slice(
            $logs,
            0,
            100
        );
    }

    private function computeSince(
        string $period
    ): ?string {

        switch ($period) {

            case 'today':

                return date('Y-m-d 00:00:00');

            case 'week':

                return date(
                    'Y-m-d H:i:s',
                    strtotime('-7 days')
                );

            case 'month':

                return date(
                    'Y-m-d H:i:s',
                    strtotime('-30 days')
                );

            case '6months':

                return date(
                    'Y-m-d H:i:s',
                    strtotime('-180 days')
                );

            default:

                return date(
                    'Y-m-d H:i:s',
                    strtotime('-30 days')
                );
        }
    }
}

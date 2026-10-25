<?php

error_reporting(E_ALL);

ini_set(
    'display_errors',
    1
);

if(!defined('APP_LOADED')){
    define('APP_LOADED', true);
}

require_once __DIR__ .
'/../../app/init.php';

require_once __DIR__ .
'/../controllers/AIReportController.php';

use Finance\Controllers\AIReportController;

try{

    file_put_contents(

        __DIR__ .
        '/cron_test.log',

        date('Y-m-d H:i:s')
        .
        ' | CRON STARTED'
        .
        PHP_EOL,

        FILE_APPEND
    );

    $controller =
        new AIReportController();

    $controller
        ->generateDailyReports();

    file_put_contents(

        __DIR__ .
        '/cron_test.log',

        date('Y-m-d H:i:s')
        .
        ' | REPORT GENERATED'
        .
        PHP_EOL,

        FILE_APPEND
    );

}catch(Throwable $e){

    file_put_contents(

        __DIR__ .
        '/cron_error.log',

        date('Y-m-d H:i:s')
        .
        ' | ERROR: '
        .
        $e->getMessage()
        .
        PHP_EOL,

        FILE_APPEND
    );
}
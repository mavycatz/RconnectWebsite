<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

if(!defined('APP_LOADED')){
    define('APP_LOADED', true);
}

require_once __DIR__ .
'/../../app/init.php';

require_once __DIR__ .
'/../middleware/validate_request.php';

require_once __DIR__ .
'/../controllers/AIReportController.php';

use Finance\Controllers\AIReportController;

header(
    'Content-Type: application/json'
);

$controller =
    new AIReportController();

$controller->getLatestReports();
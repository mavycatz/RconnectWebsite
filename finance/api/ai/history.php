<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

header(
    'Content-Type: application/json'
);

define('APP_LOADED', true);

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/init.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/finance/middleware/validate_request.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/finance/models/AI/AIReportHistoryModel.php';

validateFinanceRequest('ai', false);
setRequestTimeout(120);

use Finance\Models\AI\AIReportHistoryModel;

try{

    $model =
        new AIReportHistoryModel();

    $reports =
        $model->getReports();

    echo json_encode([

        'success' => true,

        'reports' =>
            $reports

    ]);

}catch(Throwable $e){

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()

    ]);
}
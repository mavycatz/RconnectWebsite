<?php

header(
    'Content-Type: application/json'
);

ini_set('display_errors', 1);

error_reporting(E_ALL);

define('APP_LOADED', true);

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/init.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/finance/middleware/validate_request.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/finance/models/AI/AIChatHistoryModel.php';

validateFinanceRequest('ai', false);
setRequestTimeout(30);

try{

    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

    $userId =

        \App\Auth\Auth::userId();

    /*
    |--------------------------------------------------------------------------
    | MODEL
    |--------------------------------------------------------------------------
    */

    $model =
        new \Finance\Models\AI\AIChatHistoryModel();

    /*
    |--------------------------------------------------------------------------
    | USER SESSIONS
    |--------------------------------------------------------------------------
    */

    $sessions =
        $model->getSessions(
            $userId
        );

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'sessions' => $sessions

    ]);

}catch(Throwable $e){

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage(),

        'file' =>
            $e->getFile(),

        'line' =>
            $e->getLine()

    ]);
}

<?php

header(
    'Content-Type: application/json'
);

ini_set('display_errors', 1);

error_reporting(E_ALL);

define('APP_LOADED', true);

/*
|--------------------------------------------------------------------------
| LOAD CORE
|--------------------------------------------------------------------------
*/

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/init.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/finance/middleware/validate_request.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/finance/models/AI/AIChatHistoryModel.php';

use Finance\Models\AI\AIChatHistoryModel;

validateFinanceRequest('ai', true, true);
setRequestTimeout(30);

/*
|--------------------------------------------------------------------------
| RAW INPUT
|--------------------------------------------------------------------------
*/

$rawInput =
    file_get_contents(
        "php://input"
    );

/*
|--------------------------------------------------------------------------
| DECODE JSON
|--------------------------------------------------------------------------
*/

$data =
    json_decode(
        $rawInput,
        true
    );

/*
|--------------------------------------------------------------------------
| DEBUG
|--------------------------------------------------------------------------
*/

if(json_last_error() !== JSON_ERROR_NONE){

    echo json_encode([

        'success' => false,

        'message' => 'Invalid JSON.',

        'raw_input' => $rawInput
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| SESSION ID
|--------------------------------------------------------------------------
*/

$sessionId =

    $data['session_id']
    ?? null;

/*
|--------------------------------------------------------------------------
| VALIDATE
|--------------------------------------------------------------------------
*/

if(

    empty($sessionId)
){

    echo json_encode([

        'success' => false,

        'message' => 'Session ID missing.',

        'received_data' => $data
    ]);

    exit;
}

try{

    /*
    |--------------------------------------------------------------------------
    | MODEL
    |--------------------------------------------------------------------------
    */

    $chatModel =
        new AIChatHistoryModel();

    /*
    |--------------------------------------------------------------------------
    | DELETE SESSION
    |--------------------------------------------------------------------------
    */

    $chatModel->deleteSession(
        $sessionId
    );

    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'deleted_session_id' =>
            $sessionId
    ]);

}catch(Exception $e){

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage()
    ]);
}
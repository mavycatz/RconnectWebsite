<?php

/*
|--------------------------------------------------------------------------
| ERRORS
|--------------------------------------------------------------------------
*/

ini_set('display_errors', 1);

error_reporting(E_ALL);

/*
|--------------------------------------------------------------------------
| APP
|--------------------------------------------------------------------------
*/

define('APP_LOADED', true);

/*
|--------------------------------------------------------------------------
| JSON
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| INIT
|--------------------------------------------------------------------------
*/

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/init.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/finance/middleware/validate_request.php';

validateFinanceRequest('ai', false);
setRequestTimeout(120);

/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/auth/Session.php';

require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/auth/Auth.php';

/*
|--------------------------------------------------------------------------
| START SESSION USING YOUR SYSTEM
|--------------------------------------------------------------------------
*/

\App\Auth\Session::start();

/*
|--------------------------------------------------------------------------
| CONTROLLER
|--------------------------------------------------------------------------
*/

require_once $_SERVER['DOCUMENT_ROOT']
    . '/finance/controllers/AI/AIChatController.php';

use App\Auth\Auth;
use Finance\Controllers\AI\AIChatController;

try{

    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

    $userId =
        Auth::userId();

    /*
    |--------------------------------------------------------------------------
    | DEBUG USER
    |--------------------------------------------------------------------------
    */

    if(!$userId){

        echo json_encode([

            'success' => false,

            'message' =>
                'User session missing.',

            'session' =>
                $_SESSION ?? [],

            'cookies' =>
                $_COOKIE ?? [],

            'session_id' =>
                session_id()

        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | RAW INPUT
    |--------------------------------------------------------------------------
    */

    $raw =
        file_get_contents(
            'php://input'
        );

    /*
    |--------------------------------------------------------------------------
    | JSON
    |--------------------------------------------------------------------------
    */

    $data =
        json_decode(
            $raw,
            true
        );

    if(!$data){

        throw new Exception(
            'Invalid JSON input.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MESSAGE
    |--------------------------------------------------------------------------
    */

    $message =
        trim(
            $data['message']
            ?? ''
        );

    /*
    |--------------------------------------------------------------------------
    | SESSION ID
    |--------------------------------------------------------------------------
    */

    $sessionId =

        isset($data['session_id'])

        &&

        $data['session_id']

        ?

        (int)$data['session_id']

        :

        null;

    /*
    |--------------------------------------------------------------------------
    | PAGE CONTEXT (CURRENT PAGE DATA + FILTERS)
    |--------------------------------------------------------------------------
    */

    $pageContext =

        isset($data['page_context'])

        &&

        is_array($data['page_context'])

        ?

        $data['page_context']

        :

        null;

    /*
    |--------------------------------------------------------------------------
    | VALIDATE
    |--------------------------------------------------------------------------
    */

    if(empty($message)){

        throw new Exception(
            'Message missing.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CONTROLLER
    |--------------------------------------------------------------------------
    */

    $controller =
        new AIChatController();

    /*
    |--------------------------------------------------------------------------
    | AI CHAT
    |--------------------------------------------------------------------------
    */

    $result =
        $controller->chat(

            $sessionId,

            $message,

            $pageContext

        );

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        'success' => true,

        'reply' =>

            $result['content']
            ?? 'No AI response.',

        'session_id' =>

            $result['session_id']
            ?? null

    ]);

}catch(Throwable $e){

    $logFile = __DIR__.DIRECTORY_SEPARATOR.'ai_chat_error.log';

    if(is_writable($logFile) || is_writable(__DIR__)){

        @file_put_contents(
            $logFile,
            date('Y-m-d H:i:s')
            .' | '.$e->getMessage()
            .' | '.$e->getFile().':'.$e->getLine()
            .PHP_EOL,
            FILE_APPEND
        );
    }

    echo json_encode([

        'success' => false,

        'message' =>
            'Something went wrong processing that request. Please try again.'

    ]);
}

<?php

namespace Finance\Services;

require_once __DIR__
    . '/../models/AI/AIChatHistoryModel.php';

require_once __DIR__
    . '/AIFinancialService.php';

use Finance\Models\AI\AIChatHistoryModel;

class AIConversationService
{
    private $chatModel;

    private $aiService;

    public function __construct()
    {
        $this->chatModel =
            new AIChatHistoryModel();

        $this->aiService =
            new AIFinancialService();
    }

    /*
    |--------------------------------------------------------------------------
    | SEND MESSAGE
    |--------------------------------------------------------------------------
    */

    public function sendMessage(

        $sessionId,
        $message,
        $generatedBy = null

    ): array {

        /*
        |--------------------------------------------------------------------------
        | VALIDATE SESSION
        |--------------------------------------------------------------------------
        */

        if(
            !$this->chatModel
                ->sessionExists(
                    $sessionId
                )
        ){

            return [

                'success' => false,

                'message' =>
                    'Invalid session.'
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | LOAD HISTORY
        |--------------------------------------------------------------------------
        */

        $history =
            $this->chatModel
                ->getChats(
                    $sessionId
                );

        /*
        |--------------------------------------------------------------------------
        | BUILD MEMORY CONTEXT
        |--------------------------------------------------------------------------
        */

        $memory =
            $this->buildConversationMemory(
                $history
            );

        /*
        |--------------------------------------------------------------------------
        | CREATE AI PROMPT
        |--------------------------------------------------------------------------
        */

        $prompt = "

You are an advanced transportation
finance and operations AI assistant.

You are helping manage:

- transportation finances
- operational analytics
- fuel costs
- route performance
- driver management
- passenger demand
- maintenance operations

Previous conversation memory:

{$memory}

User message:

{$message}

Provide:

- clear explanations
- operational insights
- financial recommendations
- transportation analysis

";

        /*
        |--------------------------------------------------------------------------
        | SEND TO AI
        |--------------------------------------------------------------------------
        */

        $response =
            $this->aiService
                ->analyzeFinancialReport([

                    'conversation_message' =>
                        $prompt
                ]);

        /*
        |--------------------------------------------------------------------------
        | FORMAT AI RESPONSE
        |--------------------------------------------------------------------------
        */

        $aiMessage =

            $response['summary']
            ?? 'AI generated no response.';

        /*
        |--------------------------------------------------------------------------
        | ADD INSIGHTS
        |--------------------------------------------------------------------------
        */

        if(
            !empty(
                $response['insights']
            )
        ){

            $aiMessage .=

                "\n\nINSIGHTS:\n- "

                .

                implode(

                    "\n- ",

                    $response['insights']
                );
        }

        /*
        |--------------------------------------------------------------------------
        | ADD RECOMMENDATIONS
        |--------------------------------------------------------------------------
        */

        if(
            !empty(
                $response['recommendations']
            )
        ){

            $aiMessage .=

                "\n\nRECOMMENDATIONS:\n- "

                .

                implode(

                    "\n- ",

                    $response['recommendations']
                );
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE CHAT
        |--------------------------------------------------------------------------
        */

        $this->chatModel
            ->saveChat(

                $sessionId,

                $message,

                $aiMessage,

                'financial_ai_chat',

                $generatedBy
            );

        /*
        |--------------------------------------------------------------------------
        | RETURN RESPONSE
        |--------------------------------------------------------------------------
        */

        return [

            'success' => true,

            'response' =>
                $aiMessage
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD MEMORY
    |--------------------------------------------------------------------------
    */

    private function buildConversationMemory(
        array $history
    ): string {

        if(
            empty($history)
        ){

            return
                'No previous conversation.';
        }

        /*
        |--------------------------------------------------------------------------
        | LIMIT MEMORY
        |--------------------------------------------------------------------------
        */

        $history =
            array_slice(
                $history,
                -10
            );

        $memory = '';

        foreach($history as $chat){

            $memory .=

                "\nUSER:\n"

                .

                $chat['user_message']

                .

                "\n\nAI:\n"

                .

                $chat['ai_response']

                .

                "\n\n";
        }

        return $memory;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE NEW SESSION
    |--------------------------------------------------------------------------
    */

    public function createSession(

        $title,
        $generatedBy = null

    ){

        return
            $this->chatModel
                ->createSession(

                    $title,

                    $generatedBy
                );
    }

    /*
    |--------------------------------------------------------------------------
    | GET SESSION CHATS
    |--------------------------------------------------------------------------
    */

    public function getChats(
        $sessionId
    ){

        return
            $this->chatModel
                ->getChats(
                    $sessionId
                );
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL SESSIONS
    |--------------------------------------------------------------------------
    */

    public function getSessions()
    {

        return
            $this->chatModel
                ->getSessions();
    }
}
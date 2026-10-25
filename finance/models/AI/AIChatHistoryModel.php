<?php

namespace Finance\Models\AI;

require_once __DIR__
    . '/../../../app/Core/Database.php';

use App\Core\Database;
use PDO;

class AIChatHistoryModel
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn =
            Database::get();
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE SESSION
    |--------------------------------------------------------------------------
    */

public function createSession(

    $title,

    $userId = null,

    $generatedBy = null

)
{

        $stmt =
            $this->conn->prepare("

                INSERT INTO ai_chat_sessions (

                    session_title,
                    user_id,
                    generated_by

                )

                VALUES (

                    :session_title,
                    :user_id,
                    :generated_by

                )

            ");

        $stmt->execute([

            ':session_title' =>
                $title,
                
            ':user_id' => 
                $userId,

            ':generated_by' =>
                $generatedBy

        ]);

        return
            $this->conn->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL SESSIONS
    |--------------------------------------------------------------------------
    */

public function getSessions(
    $userId
){

    $stmt =
        $this->conn->prepare("

            SELECT *

            FROM ai_chat_sessions

            WHERE user_id = :user_id

            ORDER BY id DESC

        ");

    $stmt->execute([

        ':user_id' =>
            $userId

    ]);

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}



    /*
    |--------------------------------------------------------------------------
    | GET SINGLE SESSION
    |--------------------------------------------------------------------------
    */

    public function getSession(
        $sessionId
    ){

        $stmt =
            $this->conn->prepare("

                SELECT *

                FROM ai_chat_sessions

                WHERE id = :id

                LIMIT 1

            ");

        $stmt->execute([

            ':id' =>
                $sessionId

        ]);

        return
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE CHAT
    |--------------------------------------------------------------------------
    */

public function saveChat(

    $sessionId,

    $userId,

    $userMessage,

    $aiResponse,

    $chatType = 'general_chat',

    $generatedBy = null

)


{

$stmt =
    $this->conn->prepare("

        INSERT INTO ai_chats (

            session_id,
            user_id,
            user_message,
            ai_response,
            chat_type,
            generated_by

        )

        VALUES (

            :session_id,
            :user_id,
            :user_message,
            :ai_response,
            :chat_type,
            :generated_by

        )

    ");


return $stmt->execute([

    ':session_id' =>
        $sessionId,

    ':user_id' =>
        $userId,

    ':user_message' =>
        $userMessage,

    ':ai_response' =>
        $aiResponse,

    ':chat_type' =>
        $chatType,

    ':generated_by' =>
        $generatedBy

]);

    }

    /*
    |--------------------------------------------------------------------------
    | GET CHATS BY SESSION
    |--------------------------------------------------------------------------
    */

    public function getChats(
        $sessionId,
        $limit = 20
    ){

        $limit =
            (int)$limit;

        $stmt =
            $this->conn->prepare("

                SELECT *

                FROM ai_chats

                WHERE session_id = :session_id

                ORDER BY id DESC

                LIMIT {$limit}

            ");

        $stmt->execute([

            ':session_id' =>
                $sessionId

        ]);

        return array_reverse(

            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GLOBAL MEMORY
    |--------------------------------------------------------------------------
    */

    public function getGlobalMemory(
        $limit = 50
    ){

        $limit =
            (int)$limit;

        $stmt =
            $this->conn->prepare("

                SELECT *

                FROM ai_chats

                ORDER BY id DESC

                LIMIT {$limit}

            ");

        $stmt->execute();

        return array_reverse(

            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH RELATED MEMORY
    |--------------------------------------------------------------------------
    */

    public function searchRelatedMemory(

        $keyword,
        $limit = 15

    ){

        /*
        |--------------------------------------------------------------------------
        | CLEAN INPUTS
        |--------------------------------------------------------------------------
        */

        $limit =
            (int)$limit;

        $search =
            '%'
            .
            trim($keyword)
            .
            '%';

        /*
        |--------------------------------------------------------------------------
        | QUERY
        |--------------------------------------------------------------------------
        */

        $sql = "

            SELECT *

            FROM ai_chats

            WHERE

                user_message LIKE :search1

                OR

                ai_response LIKE :search2

            ORDER BY id DESC

            LIMIT {$limit}

        ";

        $stmt =
            $this->conn->prepare(
                $sql
            );

        /*
        |--------------------------------------------------------------------------
        | BIND VALUES
        |--------------------------------------------------------------------------
        */

        $stmt->bindValue(

            ':search1',

            $search,

            PDO::PARAM_STR
        );

        $stmt->bindValue(

            ':search2',

            $search,

            PDO::PARAM_STR
        );

        /*
        |--------------------------------------------------------------------------
        | EXECUTE
        |--------------------------------------------------------------------------
        */

        $stmt->execute();

        /*
        |--------------------------------------------------------------------------
        | RETURN
        |--------------------------------------------------------------------------
        */

        return array_reverse(

            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET SMART MEMORY
    |--------------------------------------------------------------------------
    */

    public function getSmartMemory(

        $sessionId,
        $userPrompt

    ){

        /*
        |--------------------------------------------------------------------------
        | SESSION MEMORY
        |--------------------------------------------------------------------------
        */

        $sessionChats =
            $this->getChats(
                $sessionId,
                15
            );

        /*
        |--------------------------------------------------------------------------
        | GLOBAL MEMORY
        |--------------------------------------------------------------------------
        */

        $globalChats =
            $this->getGlobalMemory(
                30
            );

        /*
        |--------------------------------------------------------------------------
        | KEYWORD SEARCH
        |--------------------------------------------------------------------------
        */

        $keywords =
            explode(
                ' ',
                strtolower($userPrompt)
            );

        $relatedChats = [];

        foreach($keywords as $word){

            if(strlen($word) < 4){
                continue;
            }

            $results =
                $this->searchRelatedMemory(
                    $word,
                    5
                );

            $relatedChats = array_merge(

                $relatedChats,

                $results
            );
        }

        /*
        |--------------------------------------------------------------------------
        | MERGE MEMORY
        |--------------------------------------------------------------------------
        */

        $memory = array_merge(

            $relatedChats,

            $globalChats,

            $sessionChats
        );

        /*
        |--------------------------------------------------------------------------
        | REMOVE DUPLICATES
        |--------------------------------------------------------------------------
        */

        $unique = [];

        foreach($memory as $item){

            if(empty($item['id'])){
                continue;
            }

            $unique[
                $item['id']
            ] = $item;
        }

        /*
        |--------------------------------------------------------------------------
        | SORT MEMORY
        |--------------------------------------------------------------------------
        */

        usort(

            $unique,

            function($a,$b){

                return
                    $a['id']
                    <=>
                    $b['id'];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | LIMIT FINAL MEMORY
        |--------------------------------------------------------------------------
        */

        return array_slice(
            $unique,
            -50
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET RECENT CHATS
    |--------------------------------------------------------------------------
    */

    public function getRecentChats()
    {
        $stmt =
            $this->conn->prepare("

                SELECT *

                FROM ai_chats

                ORDER BY id DESC

                LIMIT 50

            ");

        $stmt->execute();

        return
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE SESSION
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | RENAME SESSION
    |--------------------------------------------------------------------------
    */

    public function renameSession(

        $sessionId,
        $title

    ){

        $stmt =
            $this->conn->prepare("

                UPDATE ai_chat_sessions

                SET
                    session_title = :title

                WHERE id = :id

            ");

        return $stmt->execute([

            ':title' =>
                $title,

            ':id' =>
                $sessionId

        ]);
    }

/*
|--------------------------------------------------------------------------
| SESSION EXISTS
|--------------------------------------------------------------------------
*/

public function sessionExists(
    $sessionId
){

    /*
    |--------------------------------------------------------------------------
    | SQL
    |--------------------------------------------------------------------------
    */

    $sql = "

        SELECT COUNT(*) as total

        FROM ai_chat_sessions

        WHERE id = :id

    ";

    /*
    |--------------------------------------------------------------------------
    | PREPARE
    |--------------------------------------------------------------------------
    */

    $stmt =
        $this->conn->prepare(
            $sql
        );

    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    $stmt->execute([

        ':id' =>
            (int)$sessionId

    ]);

    /*
    |--------------------------------------------------------------------------
    | RESULT
    |--------------------------------------------------------------------------
    */

    $result =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        (int)$result['total'] > 0;
}

    /*
    |--------------------------------------------------------------------------
    | GET TOTAL SESSIONS
    |--------------------------------------------------------------------------
    */

    public function getTotalSessions()
    {
        $stmt =
            $this->conn->prepare("

                SELECT COUNT(*) as total

                FROM ai_chat_sessions

            ");

        $stmt->execute();

        return
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );
    }

    /*
    |--------------------------------------------------------------------------
    | GET TOTAL CHATS
    |--------------------------------------------------------------------------
    */

    public function getTotalChats()
    {
        $stmt =
            $this->conn->prepare("

                SELECT COUNT(*) as total

                FROM ai_chats

            ");

        $stmt->execute();

        return
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );
    }
    
/*
|--------------------------------------------------------------------------
| SAVE GLOBAL KNOWLEDGE
|--------------------------------------------------------------------------
*/

public function saveGlobalKnowledge(

    $category,

    $keyword,

    $insight,

    $sourceChatId = null

){

    /*
    |--------------------------------------------------------------------------
    | EMPTY
    |--------------------------------------------------------------------------
    */

    if(

        empty(trim($insight))
    ){

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | SQL
    |--------------------------------------------------------------------------
    */

    $stmt =
        $this->conn->prepare("

            INSERT INTO ai_global_memory (

                category,
                keyword,
                insight,
                source_chat_id

            )

            VALUES (

                :category,
                :keyword,
                :insight,
                :source_chat_id

            )

        ");

    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    return $stmt->execute([

        ':category' =>
            $category,

        ':keyword' =>
            $keyword,

        ':insight' =>
            $insight,

        ':source_chat_id' =>
            $sourceChatId
    ]);
}

/*
|--------------------------------------------------------------------------
| GET GLOBAL KNOWLEDGE
|--------------------------------------------------------------------------
*/

public function getGlobalKnowledge(

    $limit = 20

){

    /*
    |--------------------------------------------------------------------------
    | CLEAN LIMIT
    |--------------------------------------------------------------------------
    */

    $limit =
        (int)$limit;

    /*
    |--------------------------------------------------------------------------
    | SQL
    |--------------------------------------------------------------------------
    */

    $sql = "

        SELECT *

        FROM ai_global_memory

        ORDER BY id DESC

        LIMIT {$limit}

    ";

    /*
    |--------------------------------------------------------------------------
    | PREPARE
    |--------------------------------------------------------------------------
    */

    $stmt =
        $this->conn->prepare(
            $sql
        );

    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    $stmt->execute();

    /*
    |--------------------------------------------------------------------------
    | RETURN
    |--------------------------------------------------------------------------
    */

    return
        array_reverse(

            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
        );
}
/*
|--------------------------------------------------------------------------
| DELETE SESSION
|--------------------------------------------------------------------------
*/

public function deleteSession($sessionId)
{

    /*
    |--------------------------------------------------------------------------
    | DELETE CHAT HISTORY
    |--------------------------------------------------------------------------
    */

    $sql = "

        DELETE FROM ai_chats
        WHERE session_id = :session_id

    ";

    $stmt =
        $this->conn->prepare(
            $sql
        );

    $stmt->execute([

        ':session_id' =>
            $sessionId
    ]);

    /*
    |--------------------------------------------------------------------------
    | DELETE SESSION
    |--------------------------------------------------------------------------
    */

    $sql = "

        DELETE FROM ai_chat_sessions
        WHERE id = :session_id

    ";

    $stmt =
        $this->conn->prepare(
            $sql
        );

    $stmt->execute([

        ':session_id' =>
            $sessionId
    ]);

    return true;
}
}

<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

use App\Core\Database;

header(
    'Content-Type: application/json'
);

try{

    $conn =
        Database::get();

    $stmt =
        $conn->prepare(
            "
            SELECT

                id,
                terminal_code,
                terminal_name

            FROM terminals

            WHERE status = 'Active'

            ORDER BY terminal_name ASC
            "
        );

    $stmt->execute();

    echo json_encode(
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
    );

}catch(Throwable $e){

    http_response_code(
        500
    );

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()

    ]);

}
<?php

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

                i.*,

                ia.inspector_id,
                ia.role,
                ia.status,

                t.id AS terminal_id,
                t.terminal_code,
                t.terminal_name

            FROM inspectors i

            INNER JOIN inspector_accounts ia

                ON ia.account_id =
                   i.account_id

            LEFT JOIN terminals t

                ON t.id =
                   i.assigned_routes

            WHERE

                ia.status = 'Archived'

            ORDER BY

                i.inspector_pk DESC
            "
        );

    $stmt->execute();

    echo json_encode([

        'success' => true,

        'inspectors' =>

            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )

    ]);

}catch(Throwable $e){

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()

    ]);

}
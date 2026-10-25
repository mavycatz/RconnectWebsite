<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Core/Bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

use App\Core\Database;
use Finance\Models\AuditTrailModel;

header(
    'Content-Type: application/json'
);

try{

    $inspectorPk =

        (int)(
            $_POST['inspector_pk']
            ?? 0
        );

    $reason = trim((string)($_POST['reason'] ?? ''));

    if(
        !$inspectorPk
    ){

        throw new Exception(
            'Inspector ID missing.'
        );

    }

    if ($reason === '') {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'A reason for reactivating this account is required.'
        ]);

        exit;

    }

    require_once $_SERVER['DOCUMENT_ROOT']
    . '/admin/api/RoleManagement/_security_guard.php';

    rmg_guard_require(
        'restore',
        'inspector',
        (string)$inspectorPk
    );

    $conn =
        Database::get();

    $stmt =
        $conn->prepare(
            "
            UPDATE inspector_accounts ia

            INNER JOIN inspectors i

                ON i.account_id =
                   ia.account_id

            SET

                ia.status =
                    'active'

            WHERE

                i.inspector_pk =
                    :inspector_pk
            "
        );

    $stmt->execute([

        ':inspector_pk' =>
            $inspectorPk

    ]);

    $audit = new AuditTrailModel();
    $audit->log('Inspectors', 'Restore',
        'Restored inspector #' . $inspectorPk . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
        $_SESSION['user']['id'] ?? null,
        $_SESSION['user']['username'] ?? null,
        $_SESSION['user']['role'] ?? null,
        (string)$inspectorPk,
        null,
        ['reason' => $reason],
        'Success'
    );

    echo json_encode([

        'success' => true,

        'message' =>
            'Inspector restored successfully.'

    ]);

}catch(Throwable $e){

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()

    ]);

}
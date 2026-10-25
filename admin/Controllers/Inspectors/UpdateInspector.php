<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Validation/UserValidation.php';

use Finance\Models\AuditTrailModel;

class UpdateInspectorController
{
    private UpdateInspectorService $service;

    public function __construct(
        UpdateInspectorService $service
    ){
        $this->service = $service;
    }

    public function updateInspector(): void
    {
        $validationError =
            uv_validate_inspector_update($_POST);

        if ($validationError !== null) {

            http_response_code(400);

            echo json_encode([

                'success' => false,

                'message' => $validationError

            ]);

            return;
        }

        $reason = trim((string)($_POST['reason'] ?? ''));

        $success =
            $this->service
                ->updateInspector(
                    $_POST,
                    $_FILES
                );

        $audit = new AuditTrailModel();
        $audit->log('Inspectors', 'Update',
            'Updated inspector: ' . ($_POST['inspector_name'] ?? $_POST['full_name'] ?? $_POST['inspector_pk'] ?? '')
                . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null,
            (string)($_POST['inspector_pk'] ?? ''),
            null,
            ['reason' => $reason],
            'Success'
        );

        echo json_encode([
            'success' => $success
        ]);
    }
}
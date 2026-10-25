<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

use Finance\Models\AuditTrailModel;

class ArchiveInspectorController
{
    private ArchiveInspectorService $service;

    public function __construct(
        ArchiveInspectorService $service
    ){
        $this->service = $service;
    }

    public function archiveInspector(): void
    {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A reason for deactivating this account is required.']);
            return;
        }

        $id =
            (int)(
                $_POST['inspector_pk']
                ?? 0
            );

        $success =
            $this->service
                ->archiveInspector(
                    $id
                );

        $audit = new AuditTrailModel();
        $audit->log('Inspectors', 'Archive',
            'Archived inspector #' . $id . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null,
            (string)$id,
            null,
            ['reason' => $reason],
            'Success'
        );

        echo json_encode([
            'success' => $success
        ]);
    }
    
}

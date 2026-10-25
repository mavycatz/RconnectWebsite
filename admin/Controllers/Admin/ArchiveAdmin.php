<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

use Finance\Models\AuditTrailModel;

class ArchiveAdminController
{
    private ArchiveAdminService $service;

    public function __construct(
        ArchiveAdminService $service
    ){
        $this->service = $service;
    }

    public function archiveAdmin(): void
    {
        try{

            $data = json_decode(
                file_get_contents('php://input'),
                true
            );

            $id = (int)$data['id'];
            $reason = trim((string)($data['reason'] ?? ''));
            if ($reason === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'A reason for deactivating this account is required.']);
                return;
            }

            $success = $this->service->archiveAdmin($id);

            $audit = new AuditTrailModel();
            $audit->log('Admin', 'Archive',
                'Archived admin #' . $id . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
                $_SESSION['user']['id'] ?? null,
                $_SESSION['user']['username'] ?? null,
                $_SESSION['user']['role'] ?? null,
                (string)$id,
                null,
                ['reason' => $reason],
                'Success'
            );

            echo json_encode(['success' => $success]);

        }catch(Throwable $e){

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

        }
    }
}
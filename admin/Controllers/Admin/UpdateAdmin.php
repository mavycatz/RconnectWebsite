<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Validation/UserValidation.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/RoleManagement/UploadService.php';

use Finance\Models\AuditTrailModel;

use Admin\Services\RoleManagement\UploadService;

class UpdateAdminController
{
    private UpdateAdminService $service;

    public function __construct(
        UpdateAdminService $service
    ){
        $this->service = $service;
    }

    public function updateAdmin(): void
    {
        try{

            $validationError =
                uv_validate_admin_update($_POST);

            if ($validationError !== null) {

                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => $validationError
                ]);

                return;
            }

            $reason = trim((string)($_POST['reason'] ?? ''));

            try {
                $profilePicture = null;
                $resumeFile = null;
                $supportingDocs = null;
                if (!empty($_FILES['profile_picture']['name'])) {
                    $profilePicture = UploadService::store($_FILES['profile_picture'], 'admins/photos', 'document');
                }
                if (!empty($_FILES['resume_file']['name'])) {
                    $resumeFile = UploadService::store($_FILES['resume_file'], 'cv/admin', 'cv');
                }
                if (!empty($_FILES['supporting_docs']['name'])) {
                    $supportingDocs = UploadService::store($_FILES['supporting_docs'], 'admins/documents', 'document');
                }
            } catch (\RuntimeException $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                return;
            }

            $success = $this->service->updateAdmin($_POST, $profilePicture, $resumeFile, $supportingDocs);

            $audit = new AuditTrailModel();
            $audit->log('Admin', 'Update',
                'Updated admin ID: ' . ($_POST['id'] ?? '') . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
                $_SESSION['user']['id'] ?? null,
                $_SESSION['user']['username'] ?? null,
                $_SESSION['user']['role'] ?? null,
                (string)($_POST['id'] ?? ''),
                null,
                ['reason' => $reason],
                'Success'
            );

            echo json_encode([
                'success' => $success,
                'message' => $success
                    ? 'Administrator updated successfully.'
                    : 'Failed to update administrator.'
            ]);

        }catch(Throwable $e){

            error_log(
                'UpdateAdmin controller error: ' . $e->getMessage()
            );

            // A generic message only: the original exception can carry SQL
            // detail or a filesystem path.
            echo json_encode([
                'success' => false,
                'message' => 'Unable to update the administrator account.'
            ]);

        }
    }
}
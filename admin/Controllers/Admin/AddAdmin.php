<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Models/Notification.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Validation/UserValidation.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/RoleManagement/UploadService.php';

use Finance\Models\AuditTrailModel;

use App\Models\Notification;

use Admin\Services\RoleManagement\UploadService;

class AddAdminController
{
    private AddAdminService $service;

    public function __construct(
        AddAdminService $service
    ){
        $this->service = $service;
    }

    public function addAdmin(): void
    {
        try{

            $validationError =
                uv_validate_admin_create($_POST);

            if ($validationError !== null) {

                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => $validationError
                ]);

                return;
            }

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

            $result = $this->service->addAdmin($_POST, $profilePicture, $resumeFile, $supportingDocs);

            $audit = new AuditTrailModel();
            $audit->log('Admin', 'Add',
                'Added ' . ($_POST['role'] ?? 'admin') . ' account: ' . ($result['admin_id'] ?? '') . ' (' . ($_POST['username'] ?? '') . ')',
                $_SESSION['user']['id'] ?? null,
                $_SESSION['user']['username'] ?? null,
                $_SESSION['user']['role'] ?? null,
                (string)($result['admin_id'] ?? ''),
                null,
                ['user_id' => $result['admin_id'] ?? '', 'role' => $_POST['role'] ?? 'admin'],
                'Success'
            );

            if(!empty($result['success']) && !empty($result['user_id'])){

                try{

                    $notifier = new Notification();

                    $notifier->createForAdminRoles(
                        'New Administrator Added',
                        'Administrator ' . ($_POST['username'] ?? '') . ' (' . ($result['admin_id'] ?? '') . ') was added.',
                        'info',
                        'admin_user',
                        (int)$result['user_id']
                    );

                }catch(Throwable $e){

                    error_log(
                        'Admin add notification failed: '
                        . $e->getMessage()
                    );

                }
            }

            echo json_encode($result);

        }catch(Throwable $e){

            error_log(
                'AddAdmin controller error: ' . $e->getMessage()
            );

            // A generic message only: the original exception can carry SQL
            // detail or a filesystem path.
            echo json_encode([
                'success' => false,
                'message' => 'Unable to create the administrator account.'
            ]);

        }
    }
}
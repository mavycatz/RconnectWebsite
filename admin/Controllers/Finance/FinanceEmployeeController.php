<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Validation/UserValidation.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/RoleManagement/UploadService.php';

use Finance\Models\AuditTrailModel;

use Admin\Services\RoleManagement\UploadService;

class FinanceEmployeeController
{
    private FinanceEmployeeService $service;

    public function __construct(
        FinanceEmployeeService $service
    ){
        $this->service = $service;
    }

    public function getEmployees(): void
    {
        echo json_encode([
            'success' => true,
            'data' => $this->service->getEmployees()
        ]);
    }

    public function getEmployee(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        echo json_encode([
            'success' => true,
            'data' => $this->service->getEmployee($id)
        ]);
    }

    public function addEmployee(): void
    {
        try{

            $validationError =
                uv_validate_finance_create($_POST);

            if ($validationError !== null) {

                http_response_code(400);

                echo json_encode([
                    'success' => false,
                    'message' => $validationError
                ]);

                return;
            }

            $result = $this->service->addEmployee($_POST, $_FILES);

            $audit = new AuditTrailModel();
            $audit->log('Finance', 'Add', 'Added finance employee: ' . ($_POST['full_name'] ?? $_POST['username'] ?? ''),
                $_SESSION['user']['id'] ?? null,
                $_SESSION['user']['username'] ?? null,
                $_SESSION['user']['role'] ?? null
            );

            echo json_encode($result);

        }catch(Throwable $e){
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }

    public function updateEmployee(): void
    {
        try{

            $validationError =
                uv_validate_finance_update($_POST);

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
                if (!empty($_FILES['profile_picture']['name'])) {
                    $_POST['profile_picture_new'] = UploadService::store($_FILES['profile_picture'], 'employees/photos', 'document');
                }
                if (!empty($_FILES['resume_file']['name'])) {
                    $_POST['resume_new'] = UploadService::store($_FILES['resume_file'], 'cv/finance', 'cv');
                }
                if (!empty($_FILES['supporting_docs']['name'])) {
                    $_POST['supporting_docs_new'] = UploadService::store($_FILES['supporting_docs'], 'employees/documents', 'document');
                }
            } catch (\RuntimeException $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                return;
            }

            $success = $this->service->updateEmployee($_POST);

            $audit = new AuditTrailModel();
            $audit->log('Finance', 'Update',
                'Updated finance employee ID: ' . ($_POST['id'] ?? '') . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
                $_SESSION['user']['id'] ?? null,
                $_SESSION['user']['username'] ?? null,
                $_SESSION['user']['role'] ?? null,
                (string)($_POST['id'] ?? ''),
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

    public function archiveEmployee(): void
    {
        try{

            $id = (int)($_POST['id'] ?? 0);
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($reason === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'A reason for deactivating this account is required.']);
                return;
            }

            $success = $this->service->archiveEmployee($id);

            $audit = new AuditTrailModel();
            $audit->log('Finance', 'Archive',
                'Archived finance employee #' . $id . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
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

    public function restoreEmployee(): void
    {
        try{

            $id = (int)($_POST['id'] ?? 0);
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($reason === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'A reason for reactivating this account is required.']);
                return;
            }

            $success = $this->service->restoreEmployee($id);

            $audit = new AuditTrailModel();
            $audit->log('Finance', 'Restore',
                'Restored finance employee #' . $id . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
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

    public function getArchivedEmployees(): void
    {
        echo json_encode([
            'success' => true,
            'data' => $this->service->getArchivedEmployees()
        ]);
    }
}
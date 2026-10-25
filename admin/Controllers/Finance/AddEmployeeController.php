<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Models/Notification.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Validation/UserValidation.php';

use Finance\Models\AuditTrailModel;

use App\Models\Notification;

class AddEmployeeController
{
    private AddEmployeeService $service;

    public function __construct(
        AddEmployeeService $service
    ){
        $this->service = $service;
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

            // The account is committed at this point. The audit trail and the
            // admin notification are post-commit side effects: a failure in
            // either must never turn an already-created account into a
            // "failed" response.
            if(!empty($result['success'])){

                try{

                    $audit = new AuditTrailModel();

                    $audit->log('Finance', 'Add',
                        'Added finance employee: ' . ($result['employee_id'] ?? '') . ' (' . ($_POST['full_name'] ?? $_POST['username'] ?? '') . ')',
                        $_SESSION['user']['id'] ?? null,
                        $_SESSION['user']['username'] ?? null,
                        $_SESSION['user']['role'] ?? null,
                        (string)($result['employee_id'] ?? ''),
                        null,
                        ['user_id' => $result['employee_id'] ?? '', 'role' => $_POST['role'] ?? 'finance_officer'],
                        'Success'
                    );

                }catch(Throwable $e){

                    error_log(
                        'Finance add audit failed: '
                        . $e->getMessage()
                    );

                }

                if(!empty($result['user_id'])){

                    try{

                        $notifier = new Notification();

                        $notifier->createForAdminRoles(
                            'New Finance Employee Added',
                            'Finance employee ' . ($_POST['full_name'] ?? '') . ' (' . ($result['employee_id'] ?? '') . ') was added.',
                            'info',
                            'finance_user',
                            (int)$result['user_id']
                        );

                    }catch(Throwable $e){

                        error_log(
                            'Finance add notification failed: '
                            . $e->getMessage()
                        );

                    }
                }
            }

            echo json_encode($result);

        }catch(Throwable $e){

            error_log(
                'AddEmployee controller error: '
                . $e->getMessage()
            );

            // A generic message only: the original exception can carry SQL
            // detail or a filesystem path.
            echo json_encode([
                'success' => false,
                'message' => 'Unable to create the finance account. Please try again.'
            ]);

        }
    }
}
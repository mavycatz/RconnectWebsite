<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Models/Notification.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Validation/UserValidation.php';

use Finance\Models\AuditTrailModel;

use App\Models\Notification;

class AddInspectorController
{
    private AddInspectorService $service;

    public function __construct(
        AddInspectorService $service
    ){
        $this->service = $service;
    }

    public function addInspector(): void
    {
        try{

            $validationError =
                uv_validate_inspector_create($_POST);

            if ($validationError !== null) {

                http_response_code(400);

                echo json_encode([

                    'success' => false,

                    'message' => $validationError

                ]);

                return;
            }

            $result =
                $this->service
                    ->addInspector(
                        $_POST,
                        $_FILES
                    );

            // The account is committed at this point. The audit trail and the
            // admin notification are post-commit side effects: a failure in
            // either must never turn an already-created account into a
            // "failed" response.
            if(!empty($result['success'])){

                try{

                    $audit = new AuditTrailModel();

                    $audit->log('Inspectors', 'Add',
                        'Added ' . (($_POST['role'] ?? 'inspector') === 'dispatcher' ? 'dispatcher' : 'inspector') . ' account: ' . ($result['inspector_id'] ?? '') . ' (' . ($_POST['inspector_name'] ?? $_POST['full_name'] ?? '') . ')',
                        $_SESSION['user']['id'] ?? null,
                        $_SESSION['user']['username'] ?? null,
                        $_SESSION['user']['role'] ?? null,
                        (string)($result['inspector_id'] ?? ''),
                        null,
                        ['user_id' => $result['inspector_id'] ?? '', 'role' => $_POST['role'] ?? 'inspector'],
                        'Success'
                    );

                }catch(Throwable $e){

                    error_log(
                        'Inspector add audit failed: '
                        . $e->getMessage()
                    );

                }

                if(!empty($result['inspector_pk'])){

                    try{

                        $isDispatcher =
                            ($_POST['role'] ?? 'inspector') === 'dispatcher';

                        $notifier = new Notification();

                        $notifier->createForAdminRoles(
                            $isDispatcher ? 'New Dispatcher Added' : 'New Inspector Added',
                            ($isDispatcher ? 'Dispatcher ' : 'Inspector ')
                            . ($_POST['inspector_name'] ?? '')
                            . ' (' . ($result['inspector_id'] ?? '') . ') was added.',
                            'info',
                            $isDispatcher ? 'dispatcher_user' : 'inspector_user',
                            (int)$result['inspector_pk']
                        );

                    }catch(Throwable $e){

                        error_log(
                            'Inspector add notification failed: '
                            . $e->getMessage()
                        );

                    }
                }
            }

            echo json_encode(
                $result
            );

        }catch(Throwable $e){

            error_log(
                'AddInspector controller error: '
                . $e->getMessage()
            );

            // A generic message only: the original exception can carry SQL
            // detail or a filesystem path.
            echo json_encode([

                'success' => false,

                'message' =>
                    'Unable to create the inspector account. Please try again.'

            ]);

        }
    }
}

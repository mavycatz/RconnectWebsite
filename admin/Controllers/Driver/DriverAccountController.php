<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Driver/DriverAccountService.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Driver/DriverLicenseExpiry.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Validation/UserValidation.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/app/Models/Notification.php';

use Finance\Models\AuditTrailModel;

use App\Models\Notification;

class DriverAccountController
{
    private DriverAccountService $driverAccountService;

    public function __construct(
        DriverAccountService $driverAccountService
    ){
        $this->driverAccountService =
            $driverAccountService;
    }

    public function addDriver(): void
    {
        try{

            if (!driverLicenseExpiryIsValid(
                $_POST['license_expiry'] ?? ''
            )) {

                http_response_code(
                    400
                );

                echo json_encode([

                    'success' =>
                        false,

                    'message' =>
                        driverLicenseExpiryErrorMessage()

                ]);

                return;
            }

            $validationError =
                uv_validate_driver_create($_POST);

            if ($validationError !== null) {

                http_response_code(
                    400
                );

                echo json_encode([

                    'success' =>
                        false,

                    'message' =>
                        $validationError

                ]);

                return;
            }

            $result =
                $this->driverAccountService
                    ->addDriver(
                        $_POST,
                        $_FILES
                    );

            $driverId =
                $result['driver_pk'];

            $emailSent =
                $result['email_sent'];

            $audit = new AuditTrailModel();
            $audit->log('Drivers', 'Add',
                'Added driver account: ' . ($result['assigned_user_id'] ?? '') . ' (' . ($_POST['full_name'] ?? '') . ')',
                $_SESSION['user']['id'] ?? null,
                $_SESSION['user']['username'] ?? null,
                $_SESSION['user']['role'] ?? null,
                (string)($result['assigned_user_id'] ?? ''),
                null,
                ['user_id' => $result['assigned_user_id'] ?? '', 'role' => 'driver'],
                'Success'
            );

            try{

                $notifier = new Notification();

                $notifier->createForAdminRoles(
                    'New Driver Added',
                    'Driver ' . ($_POST['full_name'] ?? '') . ' was added.',
                    'info',
                    'driver_user',
                    (int)$driverId
                );

            }catch(Throwable $e){

                error_log(
                    'Driver add notification failed: '
                    . $e->getMessage()
                );

            }

            echo json_encode([

                'success' =>
                    true,

                'driver_id' =>
                    $driverId,

                'assigned_user_id' =>
                    $result['assigned_user_id'] ?? '',

                'email_sent' =>
                    $emailSent,

                'message' =>
                    'Driver added successfully'

            ]);

        }catch(Throwable $e){

            http_response_code(
                500
            );

            echo json_encode([

                'success' =>
                    false,

                'message' =>
                    $e->getMessage(),

                'file' =>
                    $e->getFile(),

                'line' =>
                    $e->getLine()

            ]);
        }
    }
}
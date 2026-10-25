<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/finance/models/AuditTrailModel.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Driver/DriverLicenseExpiry.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/Validation/UserValidation.php';

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/services/RoleManagement/UploadService.php';

use Finance\Models\AuditTrailModel;

use Admin\Services\RoleManagement\UploadService;

class DriverController
{
    private $driverService;

    public function __construct(
        DriverService $driverService
    ){
        $this->driverService =
            $driverService;
    }

    public function getDrivers()
    {
        echo json_encode([
            'success' => true,
            'data' => $this->driverService
                ->getDrivers()
        ]);
    }

    public function getDriver()
    {
        echo json_encode([
            'success' => true,
            'data' => $this->driverService
                ->getDriver($_GET['id'])
        ]);
    }

    public function addDriver()
    {
        if (!driverLicenseExpiryIsValid($_POST['license_expiry'] ?? '')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => driverLicenseExpiryErrorMessage()]);
            return;
        }

        $validationError = uv_validate_driver_create($_POST);

        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $validationError]);
            return;
        }

        $result = $this->driverService->addDriver($_POST);
        $audit = new AuditTrailModel();
        $audit->log('Drivers', 'Add', 'Added new driver: ' . ($_POST['full_name'] ?? $_POST['driver_id'] ?? ''),
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null
        );
        echo json_encode(['success' => $result]);
    }

    public function updateDriver()
    {
        if (!driverLicenseExpiryIsValid($_POST['license_expiry'] ?? '')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => driverLicenseExpiryErrorMessage()]);
            return;
        }

        $validationError = uv_validate_driver_update($_POST);

        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $validationError]);
            return;
        }

        $reason = trim((string)($_POST['reason'] ?? ''));

        try {
            if (!empty($_FILES['photo']['name'])) {
                $_POST['photo_new'] = UploadService::store($_FILES['photo'], 'drivers/photos', 'document');
            }
            if (!empty($_FILES['resume_file']['name'])) {
                $_POST['resume_new'] = UploadService::store($_FILES['resume_file'], 'cv/driver', 'cv');
            }
            if (!empty($_FILES['supporting_docs']['name'])) {
                $_POST['supporting_docs_new'] = UploadService::store($_FILES['supporting_docs'], 'drivers/documents', 'document');
            }
        } catch (\RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            return;
        }

        $result = $this->driverService->updateDriver($_POST['id'], $_POST);
        $audit = new AuditTrailModel();
        $audit->log('Drivers', 'Update',
            'Updated driver ID: ' . $_POST['id'] . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null,
            (string)($_POST['id'] ?? ''),
            null,
            ['reason' => $reason],
            'Success'
        );
        echo json_encode(['success' => $result]);
    }

    public function getArchivedDrivers()
    {
        echo json_encode([
            'success' => true,
            'data' => $this->driverService
                ->getArchivedDrivers()
        ]);
    }

    public function archiveDriver()
    {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A reason for deactivating this account is required.']);
            return;
        }

        $result = $this->driverService->archiveDriver($_POST['id']);
        $audit = new AuditTrailModel();
        $audit->log('Drivers', 'Archive',
            'Archived driver ID: ' . $_POST['id'] . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null,
            (string)($_POST['id'] ?? ''),
            null,
            ['reason' => $reason],
            'Success'
        );
        echo json_encode(['success' => $result]);
    }

    public function unarchiveDriver()
    {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A reason for reactivating this account is required.']);
            return;
        }

        $result = $this->driverService->unarchiveDriver($_POST['id']);
        $audit = new AuditTrailModel();
        $audit->log('Drivers', 'Restore',
            'Restored driver ID: ' . $_POST['id'] . ' by ' . ($_SESSION['user']['username'] ?? 'unknown') . ' — Reason: ' . $reason,
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null,
            (string)($_POST['id'] ?? ''),
            null,
            ['reason' => $reason],
            'Success'
        );
        echo json_encode(['success' => $result]);
    }

    public function approveDriver()
    {
        $result = $this->driverService->approveDriver($_POST['id']);
        $audit = new AuditTrailModel();
        $audit->log('Drivers', 'Approve', 'Approved driver ID: ' . $_POST['id'],
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null
        );
        echo json_encode(['success' => $result]);
    }

    public function rejectDriver()
    {
        $result = $this->driverService->rejectDriver($_POST['id']);
        $audit = new AuditTrailModel();
        $audit->log('Drivers', 'Reject', 'Rejected driver ID: ' . $_POST['id'],
            $_SESSION['user']['id'] ?? null,
            $_SESSION['user']['username'] ?? null,
            $_SESSION['user']['role'] ?? null
        );
        echo json_encode(['success' => $result]);
    }
}
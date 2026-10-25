<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../../finance/models/AuditTrailModel.php';

use Finance\Models\AuditTrailModel;

class AuditService
{
    public static function log(
        string $module,
        string $action,
        ?string $description = null,
        ?string $recordId = null,
        ?array $previousValues = null,
        ?array $newValues = null,
        string $status = 'Success'
    ): void {
        $model = new AuditTrailModel();
        $model->initTables();

        $userId = $_SESSION['user']['id'] ?? null;
        $username = $_SESSION['user']['username'] ?? null;
        $userRole = $_SESSION['user']['role'] ?? null;

        $model->log(
            $module,
            $action,
            $description,
            $userId,
            $username,
            $userRole,
            $recordId,
            $previousValues,
            $newValues,
            $status
        );
    }
}

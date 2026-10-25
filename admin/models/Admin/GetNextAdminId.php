<?php

class GetNextAdminIdModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function getNextId(): string
    {
        require_once $_SERVER['DOCUMENT_ROOT']
        . '/admin/services/RoleManagement/UserIdService.php';

        $service =
            new \Admin\Services\RoleManagement\UserIdService(
                $this->conn
            );

        return $service->preview('admin');
    }
}

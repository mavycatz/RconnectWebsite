<?php

class AdminService
{
    private AdminModel $model;

    public function __construct(
        AdminModel $model
    ){
        $this->model = $model;
    }

    public function getAdmins(): array
    {
        return $this->model
            ->getAdmins();
    }
    public function getAdminById(
    int $id
): ?array
{
    return
        $this->model
            ->getAdminById(
                $id
            );
}
}
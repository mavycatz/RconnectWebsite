<?php

class AdminService
{
    private AdminModel $model;

    public function __construct(AdminModel $model)
    {
        $this->model = $model;
    }

    public function getAdminStats(): array
    {
        return $this->model->getAdminStats();
    }
}
<?php

class RestoreAdminService
{
    private RestoreAdminModel $model;

    public function __construct(
        RestoreAdminModel $model
    ){
        $this->model = $model;
    }

    public function restoreAdmin(
        int $id
    ): bool
    {
        return
            $this->model
                ->restoreAdmin(
                    $id
                );
    }
}
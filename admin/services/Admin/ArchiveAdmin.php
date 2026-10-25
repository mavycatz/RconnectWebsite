<?php

class ArchiveAdminService
{
    private ArchiveAdminModel $model;

    public function __construct(
        ArchiveAdminModel $model
    ){
        $this->model = $model;
    }

    public function archiveAdmin(
        int $id
    ): bool
    {
        return
            $this->model
                ->archiveAdmin(
                    $id
                );
    }
}
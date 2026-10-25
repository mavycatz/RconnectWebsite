<?php

class GetArchivedAdminsService
{
    private GetArchivedAdminsModel $model;

    public function __construct(
        GetArchivedAdminsModel $model
    ){
        $this->model = $model;
    }

    public function getArchivedAdmins(): array
    {
        return
            $this->model
                ->getArchivedAdmins();
    }
}
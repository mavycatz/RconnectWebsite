<?php

class GetNextAdminIdService
{
    private GetNextAdminIdModel $model;

    public function __construct(
        GetNextAdminIdModel $model
    ){
        $this->model = $model;
    }

    public function getNextId(): string
    {
        return
            $this->model
                ->getNextId();
    }
}

<?php

class GetInspectorsService
{
    private GetInspectorsModel $model;

    public function __construct(
        GetInspectorsModel $model
    ){
        $this->model = $model;
    }

    public function getInspectors(): array
    {
        return
            $this->model
                ->getInspectors();
    }
    
    
}
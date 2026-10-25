<?php

class GetInspectorService
{
    private GetInspectorsModel $model;

    public function __construct(
        GetInspectorsModel $model
    ){
        $this->model = $model;
    }

    public function getInspector(
        int $id
    ): ?array
    {
        return
            $this->model
                ->getInspector(
                    $id
                );
    }
}
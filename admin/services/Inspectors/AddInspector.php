<?php

class AddInspectorService
{
    private AddInspectorModel $model;

    public function __construct(
        AddInspectorModel $model
    ){
        $this->model = $model;
    }

public function addInspector(
    array $data,
    array $files
): array
{
    return $this->model
        ->addInspector(
            $data,
            $files
        );
}

}
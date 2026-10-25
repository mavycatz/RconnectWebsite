<?php

class UpdateInspectorService
{
    private UpdateInspectorModel $model;

    public function __construct(
        UpdateInspectorModel $model
    ){
        $this->model = $model;
    }

    public function updateInspector(
        array $data,
        array $files

    ): bool
    {
        return
            $this->model
                ->updateInspector(
                    $data,
                    $files

                );
    }
}
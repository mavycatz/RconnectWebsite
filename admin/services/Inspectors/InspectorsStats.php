<?php

class InspectorService
{
    private InspectorModel $model;

    public function __construct(
        InspectorModel $model
    ){
        $this->model = $model;
    }

    public function getStats(): array
    {
        return $this->model
            ->getStats();
    }
}
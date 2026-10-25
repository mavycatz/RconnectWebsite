<?php

class ArchiveInspectorService
{
    private ArchiveInspectorModel $model;

    public function __construct(
        ArchiveInspectorModel $model
    ){
        $this->model = $model;
    }

    public function archiveInspector(
        int $id
    ): bool
    {
        return
            $this->model
                ->archiveInspector(
                    $id
                );
    }
}
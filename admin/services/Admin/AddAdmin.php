<?php

class AddAdminService
{
    private AddAdminModel $model;

    public function __construct(
        AddAdminModel $model
    ){
        $this->model = $model;
    }

    public function addAdmin(
        array $data,
        ?string $profilePicture = null,
        ?string $resumeFile = null,
        ?string $supportingDocs = null
    ): array
    {
        return
            $this->model
                ->addAdmin(
                    $data,
                    $profilePicture,
                    $resumeFile,
                    $supportingDocs
                );
    }
}
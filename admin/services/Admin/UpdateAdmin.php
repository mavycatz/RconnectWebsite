<?php

class UpdateAdminService
{
    private UpdateAdminModel $model;

    public function __construct(
        UpdateAdminModel $model
    ){
        $this->model = $model;
    }

    public function updateAdmin(
        array $data,
        ?string $profilePicture = null,
        ?string $resumeFile = null,
        ?string $supportingDocs = null
    ): bool
    {
        return
            $this->model
                ->updateAdmin(
                    $data,
                    $profilePicture,
                    $resumeFile,
                    $supportingDocs
                );
    }
}
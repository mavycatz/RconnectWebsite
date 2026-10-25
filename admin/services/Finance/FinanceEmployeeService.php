<?php

class FinanceEmployeeService
{
    private FinanceEmployeeModel $model;

    public function __construct(
        FinanceEmployeeModel $model
    ){
        $this->model = $model;
    }

    public function getEmployees(): array
    {
        return $this->model
            ->getEmployees();
    }

    public function addEmployee(
        array $post,
        array $files
    ): array {

        $profilePicture = null;
        $resumeFile     = null;
        $supportingDocs = null;

        $uploadDir =
            $_SERVER['DOCUMENT_ROOT']
            . '/uploads/employees/';

        if(
            !is_dir($uploadDir)
        ){

            mkdir(
                $uploadDir,
                0777,
                true
            );

        }

        // =========================
        // PROFILE PICTURE
        // =========================

        if(
            isset(
                $files['profile_picture']
            )
            &&
            $files['profile_picture']['error']
            === 0
        ){

            $fileName =
                uniqid()
                . '_profile_'
                . basename(
                    $files['profile_picture']['name']
                );

            move_uploaded_file(

                $files['profile_picture']['tmp_name'],

                $uploadDir
                . $fileName

            );

            $profilePicture =
                '/uploads/employees/'
                . $fileName;

        }

        // =========================
        // RESUME FILE
        // =========================

        if(
            isset(
                $files['resume_file']
            )
            &&
            $files['resume_file']['error']
            === 0
        ){

            $fileName =
                uniqid()
                . '_resume_'
                . basename(
                    $files['resume_file']['name']
                );

            move_uploaded_file(

                $files['resume_file']['tmp_name'],

                $uploadDir
                . $fileName

            );

            $resumeFile =
                '/uploads/employees/'
                . $fileName;

        }

        // =========================
        // SUPPORTING DOCUMENTS
        // =========================

        if(
            isset(
                $files['supporting_docs']
            )
            &&
            $files['supporting_docs']['error']
            === 0
        ){

            $fileName =
                uniqid()
                . '_doc_'
                . basename(
                    $files['supporting_docs']['name']
                );

            move_uploaded_file(

                $files['supporting_docs']['tmp_name'],

                $uploadDir
                . $fileName

            );

            $supportingDocs =
                '/uploads/employees/'
                . $fileName;

        }

        return $this->model
            ->addEmployee(

                $post,

                $profilePicture,

                $resumeFile,

                $supportingDocs

            );

    }
    
    public function getEmployee(
    int $id
): ?array {

    return $this->model
        ->getEmployee($id);

}

public function updateEmployee(
    array $data
): bool {

    return $this->model
        ->updateEmployee($data);

}

public function archiveEmployee(
    int $id
): bool {

    return $this->model
        ->archiveEmployee($id);

}

public function restoreEmployee(
    int $id
): bool {

    return $this->model
        ->restoreEmployee($id);

}

public function getArchivedEmployees(): array
{
    return $this->model
        ->getArchivedEmployees();
}
}
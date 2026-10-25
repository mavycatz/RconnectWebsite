<?php

class AddEmployeeService
{
    private AddEmployeeModel $model;

    public function __construct(
        AddEmployeeModel $model
    ){
        $this->model = $model;
    }

    public function addEmployee(
        array $post,
        array $files
    ): array {

        $baseDir =
            $_SERVER['DOCUMENT_ROOT']
            . '/uploads/employees/';

        $photoDir =
            $baseDir
            . 'photos/';

        $resumeDir =
            $baseDir
            . 'resumes/';

        $documentDir =
            $baseDir
            . 'documents/';

        foreach([
            $baseDir,
            $photoDir,
            $resumeDir,
            $documentDir
        ] as $dir){

            if(
                !is_dir($dir)
            ){

                mkdir(
                    $dir,
                    0777,
                    true
                );

            }

        }

        $profilePicture =
            null;

        $resumeFile =
            null;

        $supportingDocs =
            null;

        /*
        |--------------------------------------------------------------------------
        | Profile Picture
        |--------------------------------------------------------------------------
        */

        if(
            !empty(
                $files['profile_picture']['name']
            )
        ){

            $filename =
                time()
                . '_'
                . basename(
                    $files['profile_picture']['name']
                );

            move_uploaded_file(
                $files['profile_picture']['tmp_name'],
                $photoDir . $filename
            );

            $profilePicture =
                '/uploads/employees/photos/'
                . $filename;

        }

        /*
        |--------------------------------------------------------------------------
        | Resume File
        |--------------------------------------------------------------------------
        */

        if(
            !empty(
                $files['resume_file']['name']
            )
        ){

            $filename =
                time()
                . '_'
                . basename(
                    $files['resume_file']['name']
                );

            move_uploaded_file(
                $files['resume_file']['tmp_name'],
                $resumeDir . $filename
            );

            $resumeFile =
                '/uploads/employees/resumes/'
                . $filename;

        }

        /*
        |--------------------------------------------------------------------------
        | Supporting Documents
        |--------------------------------------------------------------------------
        */

        if(
            !empty(
                $files['supporting_docs']['name']
            )
        ){

            $filename =
                time()
                . '_'
                . basename(
                    $files['supporting_docs']['name']
                );

            move_uploaded_file(
                $files['supporting_docs']['tmp_name'],
                $documentDir . $filename
            );

            $supportingDocs =
                '/uploads/employees/documents/'
                . $filename;

        }

        return $this->model->addEmployee(

            $post,

            $profilePicture,

            $resumeFile,

            $supportingDocs

        );
    }
}
<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/PersonnelFields.php';

class UpdateInspectorModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function updateInspector(
        array $data, 
         array $files
    ): bool
    {
        $this->conn->beginTransaction();

        try{

            \Admin\Services\RoleManagement\PersonnelFields::ensureColumns($this->conn);

            $extraSet = '';
            $extraParams = [];
            foreach (['birth_date', 'hired_date'] as $dateColumn) {
                if (array_key_exists($dateColumn, $data) && \Admin\Services\RoleManagement\PersonnelFields::hasColumn($this->conn, 'inspectors', $dateColumn)) {
                    $extraSet .= ",\n                        " . $dateColumn . " = :" . $dateColumn;
                    $extraParams[':' . $dateColumn] = \Admin\Services\RoleManagement\PersonnelFields::normalizeDate($data[$dateColumn]);
                }
            }

            // Structured employment / personal fields: only written when
            // supplied, so legacy partial forms never blank them.
            [$typedSet, $typedParams] = \Admin\Services\RoleManagement\PersonnelFields::extraUpdate($this->conn, 'inspectors', $data);
            $extraSet .= $typedSet;
            foreach ($typedParams as $typedKey => $typedValue) {
                $extraParams[$typedKey] = $typedValue;
            }

            // =========================
            // UPDATE INSPECTOR DETAILS
            // =========================

            $stmt =
                $this->conn->prepare(
                    "
                    UPDATE inspectors

                    SET

                        inspector_name =
                            :inspector_name,

                        inspector_identity =
                            :inspector_identity,

                        department =
                            :department,

                        experience_years =
                            :experience_years,

                        operational_assignment =
                            :operational_assignment,

                        assigned_routes =
                            :assigned_routes,

                        contact_number =
                            :contact_number,

                        email =
                            :email,

                        additional_notes =
                            :additional_notes{$extraSet}

                    WHERE

                        inspector_pk =
                            :inspector_pk
                    "
                );

            $stmt->execute([

                ':inspector_pk' =>
                    $data['inspector_pk'],

                ':inspector_name' =>
                    trim(
                        $data['inspector_name']
                    ),

                ':inspector_identity' =>
                    trim(
                        $data['inspector_identity']
                    ),

                ':department' =>
                    trim(
                        $data['department']
                    ),

                ':experience_years' =>
                    $data['experience_years'],

                ':operational_assignment' =>
                    trim(
                        $data['operational_assignment']
                    ),

                ':assigned_routes' =>
                    $data['assigned_routes'],

                ':contact_number' =>
                    trim(
                        $data['contact_number']
                    ),

                ':email' =>
                    trim(
                        $data['email']
                    ),

                ':additional_notes' =>
                    trim(
                        $data['additional_notes']
                        ?? ''
                    )

            ] + $extraParams);

            // =========================
            // UPDATE ACCOUNT ROLE AND PASSWORD
            // =========================

            if(
                !empty(
                    $data['account_id']
                )
            ){

                $password =
                    trim(
                        $data['password']
                        ?? ''
                    );

                $setClause =
                    'role = :role';

                $params = [

                    ':role' =>
                        $data['role']
                        ?? 'inspector',

                    ':account_id' =>
                        $data['account_id']
                ];

                if(
                    $password !== ''
                ){

                    $setClause .=
                        ', password = :password';

                    $params[':password'] =
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );
                }

                $stmt =
                    $this->conn->prepare(
                        "
                        UPDATE inspector_accounts

                        SET
                            {$setClause}

                        WHERE
                            account_id =
                                :account_id
                        "
                    );

                $stmt->execute($params);

            }
            
// =========================
// FILE UPLOADS
// =========================

$profilePicture = null;
$resumeFile     = null;
$supportingDocs = null;

$basePath =
    $_SERVER['DOCUMENT_ROOT']
    . '/uploads/inspectors/';

if(
    !is_dir($basePath . 'photos')
){
    mkdir(
        $basePath . 'photos',
        0777,
        true
    );
}

if(
    !is_dir($basePath . 'resumes')
){
    mkdir(
        $basePath . 'resumes',
        0777,
        true
    );
}

if(
    !is_dir($basePath . 'documents')
){
    mkdir(
        $basePath . 'documents',
        0777,
        true
    );
}

if(
    isset($files['profile_picture'])
    &&
    $files['profile_picture']['error'] === 0
){

    $fileName =
        uniqid()
        . '_photo_'
        . basename(
            $files['profile_picture']['name']
        );

    move_uploaded_file(

        $files['profile_picture']['tmp_name'],

        $basePath .
        'photos/' .
        $fileName

    );

    $profilePicture =
        'uploads/inspectors/photos/'
        . $fileName;
}

if(
    isset($files['resume_file'])
    &&
    $files['resume_file']['error'] === 0
){

    $fileName =
        uniqid()
        . '_resume_'
        . basename(
            $files['resume_file']['name']
        );

    move_uploaded_file(

        $files['resume_file']['tmp_name'],

        $basePath .
        'resumes/' .
        $fileName

    );

    $resumeFile =
        'uploads/inspectors/resumes/'
        . $fileName;
}

if(
    isset($files['supporting_docs'])
    &&
    $files['supporting_docs']['error'] === 0
){

    $fileName =
        uniqid()
        . '_doc_'
        . basename(
            $files['supporting_docs']['name']
        );

    move_uploaded_file(

        $files['supporting_docs']['tmp_name'],

        $basePath .
        'documents/' .
        $fileName

    );

    $supportingDocs =
        'uploads/inspectors/documents/'
        . $fileName;
}
// =========================
// UPDATE FILES
// =========================

if(
    $profilePicture
)
{
    $stmt =
        $this->conn->prepare(
            "
            UPDATE inspectors

            SET
                profile_picture =
                    :profile_picture

            WHERE
                inspector_pk =
                    :inspector_pk
            "
        );

    $stmt->execute([

        ':profile_picture' =>
            $profilePicture,

        ':inspector_pk' =>
            $data['inspector_pk']

    ]);
}

if(
    $resumeFile
)
{
    $stmt =
        $this->conn->prepare(
            "
            UPDATE inspectors

            SET
                resume_file =
                    :resume_file

            WHERE
                inspector_pk =
                    :inspector_pk
            "
        );

    $stmt->execute([

        ':resume_file' =>
            $resumeFile,

        ':inspector_pk' =>
            $data['inspector_pk']

    ]);
}

if(
    $supportingDocs
)
{
    $stmt =
        $this->conn->prepare(
            "
            UPDATE inspectors

            SET
                supporting_docs =
                    :supporting_docs

            WHERE
                inspector_pk =
                    :inspector_pk
            "
        );

    $stmt->execute([

        ':supporting_docs' =>
            $supportingDocs,

        ':inspector_pk' =>
            $data['inspector_pk']

    ]);
}

            // =========================
            // COMMIT
            // =========================

            $this->conn->commit();

            return true;

        }catch(Throwable $e){

            if(
                $this->conn->inTransaction()
            ){
                $this->conn->rollBack();
            }

            throw $e;

        }
    }
}
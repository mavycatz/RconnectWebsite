<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/PersonnelFields.php';

class AddInspectorModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function addInspector(
        array $data,
        array $files
    ): array
    {
        \Admin\Services\RoleManagement\PersonnelFields::ensureColumns($this->conn);

        require_once $_SERVER['DOCUMENT_ROOT']
        . '/admin/services/RoleManagement/UserIdService.php';

        \Admin\Services\RoleManagement\UserIdService::ensureSchema($this->conn);

$this->conn->beginTransaction();

        try{

            // =========================
            // INSPECTOR ID
            // =========================

            $inspectorId = trim($data['inspector_id'] ?? '');

            if (!$inspectorId) {
                require_once $_SERVER['DOCUMENT_ROOT']
                . '/admin/services/RoleManagement/UserIdService.php';

                $userIdService = new \Admin\Services\RoleManagement\UserIdService($this->conn);

                $scope = (($data['role'] ?? 'inspector') === 'dispatcher')
                    ? 'dispatcher'
                    : 'inspector';

                $inspectorId = $userIdService->allocateAvailable($scope);
            }

// =========================
// UPLOAD PATHS
// =========================

$basePath =
    $_SERVER['DOCUMENT_ROOT']
    . '/uploads/inspectors/';

$profilePicture = null;
$resumePath     = null;
$docsPath       = null;

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

// =========================
// PROFILE PHOTO
// =========================

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

// =========================
// RESUME
// =========================

if(
    isset($files['resume_file'])
    &&
    $files['resume_file']['error'] === 0
){

    $resumeName =
        uniqid()
        . '_resume_'
        . basename(
            $files['resume_file']['name']
        );

    move_uploaded_file(

        $files['resume_file']['tmp_name'],

        $basePath .
        'resumes/' .
        $resumeName

    );

    $resumePath =
        'uploads/inspectors/resumes/'
        . $resumeName;
}

// =========================
// SUPPORTING DOCUMENTS
// =========================

if(
    isset($files['supporting_docs'])
    &&
    $files['supporting_docs']['error'] === 0
){

    $docName =
        uniqid()
        . '_doc_'
        . basename(
            $files['supporting_docs']['name']
        );

    move_uploaded_file(

        $files['supporting_docs']['tmp_name'],

        $basePath .
        'documents/' .
        $docName

    );

    $docsPath =
        'uploads/inspectors/documents/'
        . $docName;
}
            // =========================
            // DUPLICATE GUARD
            // =========================

            // Validation runs before the service, so two overlapping
            // submissions can both pass it. Re-check inside the transaction
            // right before the INSERTs so a duplicate can never be committed.
            $duplicateStmt =
                $this->conn->prepare(
                    "
                    SELECT 1
                    FROM inspector_accounts
                    WHERE inspector_id = :inspector_id
                    LIMIT 1
                    "
                );

            $duplicateStmt->execute([
                ':inspector_id' =>
                    $inspectorId
            ]);

            if(
                $duplicateStmt->fetchColumn()
            ){

                $this->conn->rollBack();

                return [

                    'success' => false,

                    'message' =>
                        'Inspector ID is already in use.',

                    'email_sent' => false,

                    'inspector_id' =>
                        $inspectorId,

                    'assigned_user_id' =>
                        $inspectorId,

                    'inspector_pk' => 0

                ];

            }

            $duplicateStmt =
                $this->conn->prepare(
                    "
                    SELECT 1
                    FROM inspectors
                    WHERE LOWER(email) = LOWER(:email)
                    LIMIT 1
                    "
                );

            $duplicateStmt->execute([
                ':email' =>
                    trim(
                        $data['email']
                    )
            ]);

            if(
                $duplicateStmt->fetchColumn()
            ){

                $this->conn->rollBack();

                return [

                    'success' => false,

                    'message' =>
                        'Email is already registered to another inspector.',

                    'email_sent' => false,

                    'inspector_id' =>
                        $inspectorId,

                    'assigned_user_id' =>
                        $inspectorId,

                    'inspector_pk' => 0

                ];

            }

            // =========================
            // INSERT ACCOUNT
            // =========================

            $stmt =
                $this->conn->prepare(
                    "
                    INSERT INTO
                    inspector_accounts(

                        inspector_id,
                        password,
                        role,
                        status

                    )

                    VALUES(

                        :inspector_id,
                        :password,
                        :role,
                        :status

                    )
                    "
                );

            $stmt->execute([

                ':inspector_id' =>
                    $inspectorId,

                ':password' =>
                    password_hash(
                        $data['password'],
                        PASSWORD_DEFAULT
                    ),

                ':role' =>
                    $data['role']
                    ?? 'inspector',

                ':status' =>
                    strtolower((string)($data['status'] ?? 'active')) === 'inactive'
                        ? 'inactive'
                        : 'active'

            ]);

            $accountId =
                $this->conn->lastInsertId();

            // =========================
            // INSERT INSPECTOR
            // =========================

            $data['created_by'] =
                $_SESSION['user']['username'] ?? null;

            [$extraCols, $extraPlaceholders, $extraParams] =
                \Admin\Services\RoleManagement\PersonnelFields::extraInsert(
                    $this->conn,
                    'inspectors',
                    $data
                );

            $stmt =
                $this->conn->prepare(
                    "
INSERT INTO inspectors(

    account_id,
    inspector_identity,
    inspector_name,
    department,
    experience_years,
    operational_assignment,
    assigned_routes,
    contact_number,
    email,

    resume_file,
    supporting_docs,
    profile_picture,

    additional_notes,
    birth_date,
    hired_date{$extraCols}

)

VALUES(

    :account_id,
    :inspector_identity,
    :inspector_name,
    :department,
    :experience_years,
    :operational_assignment,
    :assigned_routes,
    :contact_number,
    :email,

    :resume_file,
    :supporting_docs,
    :profile_picture,

    :additional_notes,
    :birth_date,
    :hired_date{$extraPlaceholders}

)
                    "
                );

$stmt->execute([

    ':account_id' =>
        $accountId,

    ':inspector_identity' =>
        trim(
            $data['inspector_identity']
        ),

    ':inspector_name' =>
        trim(
            $data['inspector_name']
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
        trim(
            $data['assigned_routes']
        ),

    ':contact_number' =>
        trim(
            $data['contact_number']
        ),

    ':email' =>
        trim(
            $data['email']
        ),

    ':resume_file' =>
        $resumePath,

    ':supporting_docs' =>
        $docsPath,

    ':profile_picture' =>
        $profilePicture,

    ':additional_notes' =>
        trim(
            $data['additional_notes']
            ?? ''
        ),

    ':birth_date' =>
        \Admin\Services\RoleManagement\PersonnelFields::normalizeDate($data['birth_date'] ?? null),

    ':hired_date' =>
        \Admin\Services\RoleManagement\PersonnelFields::normalizeDate($data['hired_date'] ?? null)

] + $extraParams);
$inspectorPk =
    (int)$this->conn->lastInsertId();

// =========================
// COMMIT
// =========================

$this->conn->commit();
// =========================
// SEND EMAIL
// =========================

$emailSent = true;

try{

    require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/Core/MailerService.php';

    $mailer =
        new \App\Core\MailerService();

    $emailSent =
        $mailer->sendInspectorCredentials(

            $data['email'],

            $data['inspector_name'],

            $inspectorId,

            $data['password'],

            $data['role'] ?? 'inspector'

        );

}catch(Throwable $e){

    $emailSent = false;

}

return [

    'success' => true,

    'email_sent' =>
        $emailSent,

    'inspector_id' =>
        $inspectorId,

    'assigned_user_id' =>
        $inspectorId,

    'inspector_pk' =>
        $inspectorPk

];

}catch(Throwable $e){

    if(
        $this->conn->inTransaction()
    ){

        $this->conn->rollBack();

    }

    // A race can still reach the INSERTs after the duplicate guards (for
    // example two overlapping submissions). When the database rejects a row
    // as a duplicate, report it cleanly instead of surfacing a raw driver
    // error.
    if(
        $e instanceof PDOException
        && (
            $e->getCode() === '23000'
            || ($e->errorInfo[1] ?? 0) === 1062
        )
    ){

        return [

            'success' => false,

            'message' =>
                'An inspector account with this ID or email already exists.',

            'email_sent' => false,

            'inspector_id' =>
                $inspectorId ?? '',

            'assigned_user_id' =>
                $inspectorId ?? '',

            'inspector_pk' => 0

        ];

    }

    throw $e;

}

    }
}
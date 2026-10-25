<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/PersonnelFields.php';

class DriverAccountModel
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function addDriver(
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
            // GENERATE DRIVER ID
            // =========================

            require_once $_SERVER['DOCUMENT_ROOT']
            . '/admin/services/RoleManagement/UserIdService.php';

            $userIdService =
                new \Admin\Services\RoleManagement\UserIdService(
                    $this->conn
                );

            $driverId =
                $userIdService->allocateAvailable(
                    'driver'
                );

            // =========================
            // DUPLICATE GUARD
            // =========================

            // Validation runs before the service, so two overlapping
            // submissions can both pass it. Re-check inside the transaction
            // right before the INSERT so a duplicate can never be committed.
            $duplicateStmt =
                $this->conn->prepare(
                    "
                    SELECT 1
                    FROM drivers
                    WHERE LOWER(email) = LOWER(:email)
                    LIMIT 1
                    "
                );

            $duplicateStmt->execute([
                ':email' =>
                    trim((string)$data['email'])
            ]);

            if(
                $duplicateStmt->fetchColumn()
            ){

                $this->conn->rollBack();

                return [

                    'success' => false,

                    'message' =>
                        'Email is already registered to another driver.',

                    'email_sent' => false,

                    'assigned_user_id' =>
                        $driverId,

                    'driver_pk' => 0

                ];

            }

            // =========================
            // USERNAME
            // =========================

            $firstName =
                strtolower(
                    explode(
                        ' ',
                        trim(
                            $data['full_name']
                        )
                    )[0]
                );

            $username =
                $firstName .
                '_' .
                strtolower(
                    str_replace(
                        '-',
                        '',
                        $driverId
                    )
                );

// =========================
// UPLOAD PATHS
// =========================

$basePath =
    $_SERVER['DOCUMENT_ROOT'] .
    '/uploads/drivers/';

$photoPath =
    null;

$resumePath =
    null;

$docsPath =
    null;

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
            // PHOTO
            // =========================

            if(
                isset(
                    $files['photo']
                ) &&
                $files['photo']['error']
                === 0
            ){

                $photoName =
                    uniqid() . '_photo_' .
                    basename(
                        $files['photo']['name']
                    );

                move_uploaded_file(
                    $files['photo']['tmp_name'],
                    $basePath .
                    'photos/' .
                    $photoName
                );

                $photoPath =
                    'uploads/drivers/photos/' .
                    $photoName;
            }

            // =========================
            // RESUME
            // =========================

            if(
                isset(
                    $files['resume_file']
                ) &&
                $files['resume_file']['error']
                === 0
            ){

                $resumeName =
                    uniqid() . '_resume_' .
                    basename(
                        $files['resume_file']['name']
                    );

                move_uploaded_file(
                    $files['resume_file']['tmp_name'],
                    $basePath .
                    'resumes/' .
                    $resumeName
                );

                $resumePath =
                    'uploads/drivers/resumes/' .
                    $resumeName;
            }

            // =========================
            // SUPPORTING DOCS
            // =========================

            if(
                isset(
                    $files['supporting_docs']
                ) &&
                $files['supporting_docs']['error']
                === 0
            ){

                $docName =
                    uniqid() . '_doc_' .
                    basename(
                        $files['supporting_docs']['name']
                    );

                move_uploaded_file(
                    $files['supporting_docs']['tmp_name'],
                    $basePath .
                    'documents/' .
                    $docName
                );

                $docsPath =
                    'uploads/drivers/documents/' .
                    $docName;
            }

            // =========================
            // INSERT DRIVER
            // =========================

            $data['created_by'] =
                $_SESSION['user']['username'] ?? null;

            [$extraCols, $extraPlaceholders, $extraParams] =
                \Admin\Services\RoleManagement\PersonnelFields::extraInsert(
                    $this->conn,
                    'drivers',
                    $data
                );

            $stmt =
                $this->conn->prepare(
                    "
                    INSERT INTO drivers(

                        driver_id,
                        full_name,
                        status,

                        license_number,
                        license_type,
                        license_expiry,
                        experience_years,

                        assigned_buses,

                        email,
                        contact_number,

                        resume_file,
                        supporting_docs,
                        photo,

                        approval_status,
                        approved_at,

                        birth_date,
                        hired_date{$extraCols}

                    )

                    VALUES(

                        :driver_id,
                        :full_name,
                        :status,

                        :license_number,
                        :license_type,
                        :license_expiry,
                        :experience_years,

                        :assigned_buses,

                        :email,
                        :contact_number,

                        :resume_file,
                        :supporting_docs,
                        :photo,

                        'approved',
                        NOW(),

                        :birth_date,
                        :hired_date{$extraPlaceholders}

                    )
                    "
                );

$stmt->execute([

    ':driver_id' =>
        $driverId,

    ':full_name' =>
        $data['full_name'],

    ':status' =>
        strtolower((string)($data['status'] ?? 'active')) === 'inactive'
            ? 'Inactive'
            : 'Active',

    ':license_number' =>
        $data['license_number'],

    ':license_type' =>
        $data['license_type'],

    ':license_expiry' =>
        $data['license_expiry'],

    ':experience_years' =>
        $data['experience_years'],

    ':assigned_buses' =>
        $data['bus_id'] ?? null,

    ':email' =>
        $data['email'],

    ':contact_number' =>
        $data['contact_number'],

    ':resume_file' =>
        $resumePath,

    ':supporting_docs' =>
        $docsPath,

    ':photo' =>
        $photoPath,

    ':birth_date' =>
        \Admin\Services\RoleManagement\PersonnelFields::normalizeDate($data['birth_date'] ?? null),

    ':hired_date' =>
        \Admin\Services\RoleManagement\PersonnelFields::normalizeDate($data['hired_date'] ?? null)

] + $extraParams);
$driverPk =
    (int)$this->conn->lastInsertId();

            // =========================
            // INSERT ACCOUNT
            // =========================

            $stmt =
                $this->conn->prepare(
                    "
                    INSERT INTO
                    driver_accounts(

                        driver_id,
                        username,
                        password

                    )

                    VALUES(

                        :driver_id,
                        :username,
                        :password

                    )
                    "
                );

            $stmt->execute([

                ':driver_id' =>
                    $driverId,

                ':username' =>
                    $username,

                ':password' =>
                    password_hash(
                        $data['password'],
                        PASSWORD_DEFAULT
                    )

            ]);

            // =========================
            // ASSIGN BUS
            // =========================

            if(
                !empty(
                    $data['bus_id']
                )
            ){

                $stmt =
                    $this->conn->prepare(
                        "
                        INSERT INTO
                        driver_bus_assignment(

                            driver_id,
                            bus_id

                        )

                        VALUES(

                            :driver_id,
                            :bus_id

                        )
                        "
                    );

                $stmt->execute([

                    ':driver_id' =>
                        $driverId,

                    ':bus_id' =>
                        $data['bus_id']

                ]);
            }

// =========================
// COMMIT
// =========================

$this->conn->commit();

// =========================
// SEND EMAIL (non-fatal)
// =========================

$emailSent = true;

try{

    require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/Core/MailerService.php';

    $mailer =
        new \App\Core\MailerService();

    $emailSent =
        $mailer->sendDriverCredentials(

            $data['email'],

            $data['full_name'],

            $driverId,

            $username,

            $data['password']

        );

}catch(Throwable $e){

    $emailSent = false;

    error_log(
        'Driver email send failed: '
        . $e->getMessage()
    );

}

// =========================
// SUCCESS
// =========================

return [
    'driver_pk' => $driverPk,
    'assigned_user_id' => $driverId,
    'email_sent' => $emailSent
];

}catch(Exception $e){

    if(
        $this->conn->inTransaction()
    ){

        $this->conn->rollBack();

    }

    // A race can still reach the INSERT after the duplicate guard. When the
    // database rejects a duplicate row, report it cleanly instead of
    // surfacing a raw driver error.
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
                'An account with this ID or email already exists. Please try again.',

            'email_sent' => false,

            'assigned_user_id' =>
                isset($driverId) ? $driverId : '',

            'driver_pk' => 0

        ];

    }

    throw $e;

}
    }
}
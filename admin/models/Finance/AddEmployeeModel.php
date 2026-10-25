<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/PersonnelFields.php';

class AddEmployeeModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function addEmployee(
        array $data,
        ?string $profilePicture = null,
        ?string $resumeFile = null,
        ?string $supportingDocs = null
    ): array {

        \Admin\Services\RoleManagement\PersonnelFields::ensureColumns($this->conn);

        require_once $_SERVER['DOCUMENT_ROOT']
        . '/admin/services/RoleManagement/UserIdService.php';

        \Admin\Services\RoleManagement\UserIdService::ensureSchema($this->conn);

$this->conn->beginTransaction();

        try{

            // =========================
            // GENERATE EMPLOYEE ID
            // =========================

            require_once $_SERVER['DOCUMENT_ROOT']
            . '/admin/services/RoleManagement/UserIdService.php';

            $userIdService =
                new \Admin\Services\RoleManagement\UserIdService(
                    $this->conn
                );

            $employeeId =
                $userIdService->allocateAvailable(
                    \Admin\Services\RoleManagement\UserIdService::scopeForRole(
                        (string)(
                            $data['role']
                            ?? 'finance_officer'
                        )
                    ) ?: 'finance'
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
                    FROM users
                    WHERE LOWER(username) = LOWER(:username)
                    OR LOWER(email) = LOWER(:email)
                    LIMIT 1
                    "
                );

            $duplicateStmt->execute([
                ':username' =>
                    trim(
                        $data['username']
                    ),
                ':email' =>
                    trim(
                        $data['email']
                    ),
            ]);

            if(
                $duplicateStmt->fetchColumn()
            ){

                $this->conn->rollBack();

                return [

                    'success' => false,

                    'message' =>
                        'An account with this username or email already exists.',

                    'email_sent' => false,

                    'email_error' => null,

                    'assigned_user_id' =>
                        $employeeId

                ];

            }

            // =========================
            // INSERT USER
            // =========================

            $data['created_by'] =
                $_SESSION['user']['username'] ?? null;

            [$extraCols, $extraPlaceholders, $extraParams] =
                \Admin\Services\RoleManagement\PersonnelFields::extraInsert(
                    $this->conn,
                    'users',
                    $data
                );

            $sql = "
                INSERT INTO users (

                    employee_id,
                    full_name,
                    username,
                    email,
                    password,

                    role,
                    department,
                    status,

                    contact_no,
                    address,
                    bio,

                    profile_picture,
                    resume_file,
                    supporting_docs,

                    assigned_area,
                    shift_status,
                    access_level,
                    shift_schedule,

                       is_active,
                       email_verified,
                    birth_date,
                    hired_date{$extraCols}
   
                   )
                   VALUES (

                    :employee_id,
                    :full_name,
                    :username,
                    :email,
                    :password,

                    :role,
                    :department,
                    :status,

                    :contact_no,
                    :address,
                    :bio,

                    :profile_picture,
                    :resume_file,
                    :supporting_docs,

                    :assigned_area,
                    :shift_status,
                    :access_level,
                    :shift_schedule,

                       :is_active,
                       :email_verified,
                    :birth_date,
                    :hired_date{$extraPlaceholders}
   
                   )
               ";

            $stmt =
                $this->conn->prepare(
                    $sql
                );

            $stmt->execute([

                ':employee_id' =>
                    $employeeId,

                ':full_name' =>
                    trim(
                        $data['full_name']
                    ),

                ':username' =>
                    trim(
                        $data['username']
                    ),

                ':email' =>
                    trim(
                        $data['email']
                    ),

                ':password' =>
                    password_hash(
                        $data['password'],
                        PASSWORD_DEFAULT
                    ),

                ':role' =>
                    $data['role']
                    ?? 'finance_officer',

                ':department' =>
                    'Finance',

                ':status' =>
                    $data['status']
                    ?? 'active',

                ':contact_no' =>
                    trim(
                        $data['contact_no']
                        ?? ''
                    ),

                ':address' =>
                    trim(
                        $data['address']
                        ?? ''
                    ),

                ':bio' =>
                    trim(
                        $data['bio']
                        ?? ''
                    ),

                ':profile_picture' =>
                    $profilePicture,

                ':resume_file' =>
                    $resumeFile,

                ':supporting_docs' =>
                    $supportingDocs,

                ':assigned_area' =>
                    trim(
                        $data['assigned_area']
                        ?? ''
                    ),

                ':shift_status' =>
                    trim(
                        $data['shift_status']
                        ?? ''
                    ),

                ':access_level' =>
                    $data['access_level']
                    ?? 1,

                ':shift_schedule' =>
                    trim(
                        $data['shift_schedule']
                        ?? ''
                    ),

                ':is_active' => 1,

                ':email_verified' => 0,

                ':birth_date' =>
                    \Admin\Services\RoleManagement\PersonnelFields::normalizeDate($data['birth_date'] ?? null),

                ':hired_date' =>
                    \Admin\Services\RoleManagement\PersonnelFields::normalizeDate($data['hired_date'] ?? null)

            ] + $extraParams);

// =========================
// COMMIT
// =========================

$this->conn->commit();

// =========================
// SEND EMAIL
// =========================

$emailSent  = true;
$emailError = null;

try{

    require_once $_SERVER['DOCUMENT_ROOT']
    . '/app/Core/MailerService.php';

    $mailer =
        new \App\Core\MailerService();

    $emailSent =
        $mailer->sendEmployeeCredentials(

            trim(
                $data['email']
            ),

            trim(
                $data['full_name']
            ),

            $employeeId,

            trim(
                $data['username']
            ),

            $data['password'],

            $data['role']
            ?? 'finance_officer'

        );

}catch(Throwable $e){

    $emailSent = false;

    $emailError =
        $e->getMessage();

}

// =========================
// RETURN RESULT
// =========================

return [

    'success' => true,

    'employee_id' =>
        $employeeId,

    'assigned_user_id' =>
        $employeeId,

    'user_id' =>
        (int)$this->conn->lastInsertId(),

    'email_sent' =>
        $emailSent,

    'email_error' =>
        $emailError

];

}catch(Throwable $e){

    if(
        $this->conn->inTransaction()
    ){

        $this->conn->rollBack();

    }

    // A race can still reach the INSERT after the duplicate guard (for
    // example two overlapping submissions). When the database rejects the
    // row as a duplicate, report it cleanly instead of surfacing a raw
    // driver error.
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
                'An account with this username or email already exists.',

            'email_sent' => false,

            'email_error' => null,

            'assigned_user_id' =>
                $employeeId ?? ''

        ];

    }

    throw $e;

}

}
}
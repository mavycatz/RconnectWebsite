<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/PersonnelFields.php';

class FinanceEmployeeModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function getEmployees(): array
    {
        $stmt = $this->conn->prepare("

            SELECT

                id,

                COALESCE(
                    employee_id,
                    CONCAT(
                        'FIN-',
                        LPAD(id, 4, '0')
                    )
                ) AS employee_id,

                COALESCE(
                    NULLIF(full_name, ''),
                    CONCAT(
                        COALESCE(first_name, ''),
                        ' ',
                        COALESCE(last_name, '')
                    )
                ) AS full_name,

                username,
                email,

                COALESCE(
                    NULLIF(contact_no, ''),
                    phone
                ) AS contact_no,

                COALESCE(
                    NULLIF(profile_picture, ''),
                    profile_photo
                ) AS profile_picture,

                assigned_area,
                shift_status,
                access_level,
                shift_schedule,

                COALESCE(
                    NULLIF(status, ''),
                    'pending'
                ) AS status,

                role,

                department,
                address,
                bio

            FROM users

            WHERE role IN (

                'finance_manager',
                'finance_officer'

            )

            AND status <> 'inactive'

            ORDER BY id DESC

        ");

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function getEmployee(
        int $id
    ): ?array {

        $stmt =
            $this->conn->prepare("

                SELECT
                    id,
                    COALESCE(
                        employee_id,
                        CONCAT('FIN-', LPAD(id, 4, '0'))
                    ) AS employee_id,
                    COALESCE(
                        NULLIF(full_name, ''),
                        CONCAT(
                            COALESCE(first_name, ''),
                            ' ',
                            COALESCE(last_name, '')
                        )
                    ) AS full_name,
                    username,
                    email,
                    COALESCE(
                        NULLIF(contact_no, ''),
                        phone
                    ) AS contact_no,
                    COALESCE(
                        NULLIF(profile_picture, ''),
                        profile_photo
                    ) AS profile_picture,
                    assigned_area,
                    shift_status,
                    access_level,
                    shift_schedule,
                    COALESCE(
                        NULLIF(status, ''),
                        'pending'
                    ) AS status,
                    role,
                    department,
                    address,
                    bio,
                    resume_file,
                    supporting_docs,
                    created_at,
                    updated_at

                FROM users

                WHERE id = ?

                LIMIT 1

            ");

        $stmt->execute([
            $id
        ]);

        return $stmt->fetch(
            PDO::FETCH_ASSOC
        ) ?: null;
    }

    public function updateEmployee(
    array $data
): bool {

    \Admin\Services\RoleManagement\PersonnelFields::ensureColumns($this->conn);

    $sql = "

        UPDATE users

        SET

            full_name      = ?,
            username       = ?,
            email          = ?,
            contact_no     = ?,

            role           = ?,
            status         = ?,

            assigned_area  = ?,
            shift_status   = ?,
            access_level   = ?,
            shift_schedule = ?,

            address        = ?,
            bio            = ?

    ";

    $params = [

        trim(
            $data['full_name']
            ?? ''
        ),

        trim(
            $data['username']
            ?? ''
        ),

        trim(
            $data['email']
            ?? ''
        ),

        trim(
            $data['contact_no']
            ?? ''
        ),

        trim(
            $data['role']
            ?? ''
        ),

        trim(
            $data['status']
            ?? ''
        ),

        trim(
            $data['assigned_area']
            ?? ''
        ),

        trim(
            $data['shift_status']
            ?? ''
        ),

        (int)(
            $data['access_level']
            ?? 1
        ),

        trim(
            $data['shift_schedule']
            ?? ''
        ),

        trim(
            $data['address']
            ?? ''
        ),

        trim(
            $data['bio']
            ?? ''
        )

    ];

    // Structured employment / personal fields: only written when supplied,
    // so legacy partial forms never blank them.
    [$typedSet, $typedParams] = \Admin\Services\RoleManagement\PersonnelFields::extraUpdate($this->conn, 'users', $data);
    foreach ($typedParams as $typedKey => $typedValue) {
        $sql .= ', ' . substr((string)$typedKey, 1) . ' = ?';
        $params[] = $typedValue;
    }
    if (\Admin\Services\RoleManagement\PersonnelFields::hasColumn($this->conn, 'users', 'updated_at')) {
        $sql .= ', updated_at = NOW()';
    }

    // Update password only if entered

    if(
        !empty(
            $data['password']
        )
    ){

        $sql .= ",

            password = ?

        ";

        $params[] =
            password_hash(

                $data['password'],

                PASSWORD_DEFAULT

            );

    }

    // New uploads only: a missing file field must not blank an existing path.
    if (!empty($data['profile_picture_new'])) {
        $sql .= ", profile_picture = ?";
        $params[] = $data['profile_picture_new'];
    }
    if (!empty($data['resume_new'])) {
        $sql .= ", resume_file = ?";
        $params[] = $data['resume_new'];
    }
    if (!empty($data['supporting_docs_new'])) {
        $sql .= ", supporting_docs = ?";
        $params[] = $data['supporting_docs_new'];
    }

    foreach (['birth_date', 'hired_date'] as $dateColumn) {
        if (array_key_exists($dateColumn, $data) && \Admin\Services\RoleManagement\PersonnelFields::hasColumn($this->conn, 'users', $dateColumn)) {
            $sql .= ", " . $dateColumn . " = ?";
            $params[] = \Admin\Services\RoleManagement\PersonnelFields::normalizeDate($data[$dateColumn]);
        }
    }

    $sql .= "

        WHERE id = ?

    ";

    $params[] =
        (int)$data['id'];

    $stmt =
        $this->conn->prepare(
            $sql
        );

    return $stmt->execute(
        $params
    );

}
    public function archiveEmployee(
        int $id
    ): bool {

        $stmt =
            $this->conn->prepare("

                UPDATE users

                SET

                    status = 'inactive',
                    is_active = 0

                WHERE id = ?

            ");

        return $stmt->execute([
            $id
        ]);
    }

    public function restoreEmployee(
        int $id
    ): bool {

        $stmt =
            $this->conn->prepare("

                UPDATE users

                SET

                    status = 'active',
                    is_active = 1

                WHERE id = ?

            ");

        return $stmt->execute([
            $id
        ]);
    }

    public function getArchivedEmployees(): array
    {
        $stmt =
            $this->conn->prepare("

                SELECT

                    id,

                    employee_id,
                    full_name,
                    username,
                    email,
                    contact_no,
                    profile_picture,

                    assigned_area,
                    shift_status,
                    access_level,
                    shift_schedule,

                    status,
                    role,

                    department,
                    address,
                    bio

                FROM users

                WHERE status = 'inactive'

                ORDER BY id DESC

            ");

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }
}
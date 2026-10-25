<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/PersonnelFields.php';

class UpdateAdminModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function updateAdmin(
        array $data,
        ?string $profilePicture = null,
        ?string $resumeFile = null,
        ?string $supportingDocs = null
    ): bool
    {
        \Admin\Services\RoleManagement\PersonnelFields::ensureColumns($this->conn);

        $roleConfig = require $_SERVER['DOCUMENT_ROOT']
            . '/admin/config/admin_roles.php';

        $role = strtolower(
            trim(
                (string) (
                    $data['role']
                    ?? ''
                )
            )
        );

        // Checked before any database work: a role that was not submitted leaves
        // the existing role untouched rather than resetting it to 'admin', and a
        // submitted role must be on the allow-list. The API guard has already
        // authorized this value; this is the last check before the write.
        if(
            $role !== ''
            && !in_array(
                $role,
                $roleConfig['assignable'],
                true
            )
        ){
            throw new Exception(
                'Unsupported role.'
            );
        }

        $id =
            (int)(
                $data['id']
                ?? 0
            );

        if(
            $id <= 0
        ){
            throw new Exception(
                'Invalid administrator ID.'
            );
        }

        $check =
            $this->conn->prepare(
                "
                SELECT id
                FROM users
                WHERE id = ?
                LIMIT 1
                "
            );

        $check->execute([
            $id
        ]);

        if(
            !$check->fetch()
        ){
            throw new Exception(
                'Administrator not found.'
            );
        }

        // A role that was not supplied leaves the existing role untouched,
        // rather than resetting it to 'admin'.
        $set = [
            'full_name = :full_name',
            'username = :username',
            'email = :email',
            'status = :status',
        ];

        $params = [
            ':full_name' => trim((string)($data['full_name'] ?? '')),
            ':username' => trim((string)($data['username'] ?? '')),
            ':email' => trim((string)($data['email'] ?? '')),
            ':status' => (string)($data['status'] ?? 'active'),
        ];

        if ($role !== '') {
            array_unshift($set, 'role = :role');
            $params[':role'] = $role;
        }

        // Optional columns are only written when the form actually supplied
        // them: a missing field must not blank an existing value. Both the old
        // form names and the unified Role Management names are accepted.
        $optional = [
            'department' => $data['assigned_department'] ?? ($data['department'] ?? null),
            'assigned_area' => $data['assigned_responsibility'] ?? ($data['assigned_area'] ?? null),
            'access_level' => $data['access_level'] ?? null,
            'shift_status' => $data['shift_status'] ?? null,
            'shift_schedule' => $data['shift_schedule'] ?? null,
            'contact_no' => $data['contact_no'] ?? null,
            'address' => $data['address'] ?? null,
            'bio' => $data['bio'] ?? null,
        ];
        foreach ($optional as $column => $value) {
            if ($value === null) {
                continue;
            }
            $set[] = $column . ' = :' . $column;
            $params[':' . $column] = $column === 'access_level'
                ? (string)(int)$value
                : trim((string)$value);
        }

        foreach (['profile_picture' => $profilePicture, 'resume_file' => $resumeFile, 'supporting_docs' => $supportingDocs] as $column => $path) {
            if ($path !== null && $path !== '') {
                $set[] = $column . ' = :' . $column;
                $params[':' . $column] = $path;
            }
        }

        foreach (['birth_date', 'hired_date'] as $dateColumn) {
            if (array_key_exists($dateColumn, $data) && \Admin\Services\RoleManagement\PersonnelFields::hasColumn($this->conn, 'users', $dateColumn)) {
                $set[] = $dateColumn . ' = :' . $dateColumn;
                $params[':' . $dateColumn] = \Admin\Services\RoleManagement\PersonnelFields::normalizeDate($data[$dateColumn]);
            }
        }

        // Structured employment / personal fields: only written when supplied,
        // so legacy partial forms never blank them.
        [$typedSet, $typedParams] = \Admin\Services\RoleManagement\PersonnelFields::extraUpdate($this->conn, 'users', $data);
        if ($typedSet !== '') {
            $set[] = ltrim($typedSet, ', ');
            foreach ($typedParams as $typedKey => $typedValue) {
                $params[$typedKey] = $typedValue;
            }
        }

        $sql = 'UPDATE users SET ' . implode(', ', $set);

        if (!empty($data['password'])) {
            $sql .= ', password = :password';
            $params[':password'] = password_hash(
                (string)$data['password'],
                PASSWORD_DEFAULT
            );
        }

        $sql .= ' WHERE id = :id';
        $params[':id'] = $id;

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        // The account was confirmed to exist above and PDO is in exception mode,
        // so reaching here means the statement ran without error.
        //
        // rowCount() cannot be used as the success test: the connection does not
        // set MYSQL_ATTR_FOUND_ROWS, so MySQL reports the number of CHANGED rows.
        // Saving an administrator without altering anything therefore returns 0
        // and was reported as "Failed to update administrator".
        return true;
    }
}
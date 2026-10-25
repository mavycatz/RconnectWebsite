<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/services/RoleManagement/PersonnelFields.php';

class DriverModel
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getAllDrivers()
    {
        $sql = "
            SELECT *
            FROM drivers
            WHERE status != 'Archived'
            ORDER BY created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function getDriverById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM drivers
            WHERE id = ?
        ");

        $stmt->execute([
            $id
        ]);

        return $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }

    public function updateDriver(
        $id,
        $data
    ){
        \Admin\Services\RoleManagement\PersonnelFields::ensureColumns($this->conn);

        $set = [
            'full_name = ?',
            'status = ?',
            'license_number = ?',
            'license_type = ?',
            'license_expiry = ?',
            'experience_years = ?',
            'assigned_buses = ?',
            'routes = ?',
            'email = ?',
            'contact_number = ?',
        ];

        $params = [
            $data['full_name'],
            $data['status'],
            $data['license_number'],
            $data['license_type'],
            $data['license_expiry'],
            $data['experience_years'],
            $data['assigned_buses'],
            $data['routes'],
            $data['email'],
            $data['contact_number'],
        ];

        // New uploads and explicit path changes only: a missing field must not
        // blank an existing document.
        if (!empty($data['photo_new'])) {
            $set[] = 'photo = ?';
            $params[] = $data['photo_new'];
        } elseif (!empty($data['photo'])) {
            $set[] = 'photo = ?';
            $params[] = $data['photo'];
        }
        if (!empty($data['resume_new'])) {
            $set[] = 'resume_file = ?';
            $params[] = $data['resume_new'];
        }
        if (!empty($data['supporting_docs_new'])) {
            $set[] = 'supporting_docs = ?';
            $params[] = $data['supporting_docs_new'];
        }

        foreach (['birth_date', 'hired_date'] as $dateColumn) {
            if (array_key_exists($dateColumn, $data) && \Admin\Services\RoleManagement\PersonnelFields::hasColumn($this->conn, 'drivers', $dateColumn)) {
                $set[] = $dateColumn . ' = ?';
                $params[] = \Admin\Services\RoleManagement\PersonnelFields::normalizeDate($data[$dateColumn]);
            }
        }

        // Structured employment / personal fields: only written when supplied,
        // so legacy partial forms never blank them.
        [$extraSet, $extraParams] = \Admin\Services\RoleManagement\PersonnelFields::extraUpdate($this->conn, 'drivers', $data);
        foreach ($extraParams as $extraKey => $extraValue) {
            $set[] = substr((string)$extraKey, 1) . ' = ?';
            $params[] = $extraValue;
        }
        if (\Admin\Services\RoleManagement\PersonnelFields::hasColumn($this->conn, 'drivers', 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }

        $params[] = $id;

        $stmt = $this->conn->prepare(
            'UPDATE drivers SET ' . implode(', ', $set) . ' WHERE id = ?'
        );

        return $stmt->execute($params);
    }

    public function getArchivedDrivers()
    {
        $sql = "
            SELECT *
            FROM drivers
            WHERE status = 'Archived'
            ORDER BY created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function archiveDriver($id)
    {
        $stmt = $this->conn->prepare("
            UPDATE drivers
            SET status = 'Archived'
            WHERE id = ?
        ");

        return $stmt->execute([
            $id
        ]);
    }
    
    public function unarchiveDriver($id)
{
    $stmt = $this->conn->prepare("
        UPDATE drivers
        SET status = 'Active'
        WHERE id = ?
    ");

    return $stmt->execute([
        $id
    ]);
}

    public function approveDriver($id)
    {
        $stmt = $this->conn->prepare("
            UPDATE drivers
            SET
                approval_status = 'Approved',
                approved_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([
            $id
        ]);
    }

    public function rejectDriver($id)
    {
        $stmt = $this->conn->prepare("
            UPDATE drivers
            SET
                approval_status = 'Rejected'
            WHERE id = ?
        ");

        return $stmt->execute([
            $id
        ]);
    }
}
<?php

class GetArchivedAdminsModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function getArchivedAdmins(): array
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT *
                FROM users
                WHERE role IN ('admin', 'super_admin')
                AND is_active = 0
                ORDER BY id DESC
                "
            );

        $stmt->execute();

        return
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }
}
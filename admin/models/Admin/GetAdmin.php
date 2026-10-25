<?php

class AdminModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function getAdmins(): array
    {
        $sql = "
            SELECT *
            FROM users
            WHERE role IN ('admin', 'super_admin')
            AND is_active = 1
            ORDER BY id DESC
        ";

        $stmt =
            $this->conn->prepare(
                $sql
            );

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function getAdminById(
        int $id
    ): ?array
    {
        $sql = "
            SELECT *
            FROM users
            WHERE id = ?
            LIMIT 1
        ";

        $stmt =
            $this->conn->prepare(
                $sql
            );

        $stmt->execute([
            $id
        ]);

        $admin =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        return
            $admin
            ?: null;
    }
}
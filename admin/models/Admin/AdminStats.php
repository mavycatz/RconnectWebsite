<?php

class AdminModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function getAdminStats(): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_admins,

                SUM(
                    CASE
                        WHEN status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active_admins,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive_admins

            FROM users
            WHERE role IN ('admin', 'super_admin')
        ";

        $stmt =
            $this->conn->prepare(
                $sql
            );

        $stmt->execute();

        $stats =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        return [
            'total_admins' => (int) (
                $stats['total_admins']
                ?? 0
            ),

            'active_admins' => (int) (
                $stats['active_admins']
                ?? 0
            ),

            'inactive_admins' => (int) (
                $stats['inactive_admins']
                ?? 0
            ),

            'pending_admins' => 0
        ];
    }
}
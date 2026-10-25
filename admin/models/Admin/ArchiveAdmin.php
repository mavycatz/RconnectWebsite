<?php

class ArchiveAdminModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function archiveAdmin(
        int $id
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                UPDATE users
                SET
                    is_active = 0,
                    status = 'inactive'
                WHERE id = ?
                "
            );

        $stmt->execute([
            $id
        ]);

        return
            $stmt->rowCount() > 0;
    }
}
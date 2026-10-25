<?php

class RestoreAdminModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function restoreAdmin(
        int $id
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                UPDATE users
                SET

                    is_active = 1,
                    status = 'active'

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
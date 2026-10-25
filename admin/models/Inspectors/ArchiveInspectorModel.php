<?php

class ArchiveInspectorModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function archiveInspector(
        int $inspectorPk
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                UPDATE inspector_accounts ia

                INNER JOIN inspectors i

                    ON i.account_id =
                       ia.account_id

                SET
                    ia.status = 'archived'

                WHERE
                    i.inspector_pk = :id
                "
            );

        return $stmt->execute([

            ':id' =>
                $inspectorPk

        ]);
    }
}
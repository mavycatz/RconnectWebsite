<?php

class GetInspectorsModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

public function getInspectors(): array
{
    $stmt =
        $this->conn->prepare(
            "
            SELECT

                i.*,

                ia.inspector_id,
                ia.role,
                ia.status,
                ia.created_at,

                t.id AS terminal_id,
                t.terminal_code,
                t.terminal_name,
                t.city,
                t.province

            FROM inspectors i

            LEFT JOIN inspector_accounts ia
                ON ia.account_id =
                   i.account_id

            LEFT JOIN terminals t
                ON t.id =
                   i.assigned_routes

            WHERE
                ia.status <> 'Archived'

            ORDER BY
                i.inspector_pk DESC
            "
        );

    $stmt->execute();

    return
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}

    public function getInspector(
    int $id
): ?array
{
    $stmt =
        $this->conn->prepare(
            "
            SELECT

                i.*,

                ia.inspector_id,
                ia.role,
                ia.status,

                t.id AS terminal_id,
                t.terminal_code,
                t.terminal_name

            FROM inspectors i

            LEFT JOIN inspector_accounts ia
                ON ia.account_id =
                   i.account_id

            LEFT JOIN terminals t
                ON t.id =
                   i.assigned_routes

            WHERE
                i.inspector_pk = :id

            LIMIT 1
            "
        );

    $stmt->execute([

        ':id' => $id

    ]);

    return
        $stmt->fetch(
            PDO::FETCH_ASSOC
        )
        ?: null;
}
    
}
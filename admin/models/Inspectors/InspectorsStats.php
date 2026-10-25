<?php

class InspectorModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function getStats(): array
    {
        $sql = "
            SELECT
                COUNT(i.inspector_pk) AS total,

                SUM(
                    CASE
                        WHEN ia.status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active,

                SUM(
                    CASE
                        WHEN ia.role = 'dispatcher'
                        THEN 1
                        ELSE 0
                    END
                ) AS total_dispatchers,

                SUM(
                    CASE
                        WHEN ia.role = 'dispatcher'
                         AND ia.status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active_dispatchers

            FROM inspectors i

            LEFT JOIN inspector_accounts ia
                ON ia.account_id = i.account_id
        ";

        $stmt =
            $this->conn->prepare(
                $sql
            );

        $stmt->execute();

        return
            $stmt->fetch(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }
    
}
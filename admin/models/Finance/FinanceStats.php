<?php

class FinanceDepartmentModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function getFinanceStats(): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_employees,

                SUM(
                    CASE
                        WHEN status = 'active'
                        THEN 1
                        ELSE 0
                    END
                ) AS active_employees,

                SUM(
                    CASE
                        WHEN status = 'inactive'
                        THEN 1
                        ELSE 0
                    END
                ) AS inactive_employees,

                SUM(
                    CASE
                        WHEN status = 'pending'
                        THEN 1
                        ELSE 0
                    END
                ) AS pending_employees

            FROM users

            WHERE role IN (
                'finance_manager',
                'finance_officer'
            )
        ";

        $stmt =
            $this->conn
                ->prepare($sql);

        $stmt->execute();

        return
            $stmt->fetch(
                PDO::FETCH_ASSOC
            ) ?: [
                'total_employees'    => 0,
                'active_employees'   => 0,
                'inactive_employees' => 0,
                'pending_employees'  => 0
            ];
    }
}
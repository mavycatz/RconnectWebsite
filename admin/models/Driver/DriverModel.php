<?php

class DriverModel
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getTotalDrivers(): int
    {
        $sql = "
            SELECT COUNT(*) AS total
            FROM drivers
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['total'] ?? 0);
    }

public function getDriverStats(): array
{
    $sql = "
        SELECT

            COUNT(*) AS total_drivers,

            COUNT(
                CASE
                    WHEN status = 'Active'
                    THEN 1
                END
            ) AS active_drivers,

            COUNT(
                CASE
                    WHEN status = 'Inactive'
                    THEN 1
                END
            ) AS inactive_drivers,

            COUNT(
                CASE
                    WHEN approval_status = 'approved'
                    THEN 1
                END
            ) AS approved_drivers,

            COUNT(
                CASE
                    WHEN approval_status = 'pending'
                    THEN 1
                END
            ) AS pending_drivers

        FROM drivers
    ";

    $stmt = $this->conn->prepare($sql);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'total_drivers'    => (int)($result['total_drivers'] ?? 0),
        'active_drivers'   => (int)($result['active_drivers'] ?? 0),
        'inactive_drivers' => (int)($result['inactive_drivers'] ?? 0),
        'approved_drivers' => (int)($result['approved_drivers'] ?? 0),
        'pending_drivers'  => (int)($result['pending_drivers'] ?? 0)
    ];
}
}
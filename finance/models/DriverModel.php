<?php

namespace Finance\Models;

require_once __DIR__ .
    '/../../app/Core/Database.php';

use App\Core\Database;

class DriverModel
{
    /*
    |--------------------------------------------------------------------------
    | GET ALL DRIVERS
    |--------------------------------------------------------------------------
    */

    public function getAllDrivers(): array
    {
        $db =
            Database::get();

        $sql = "

            SELECT
                id,
                full_name,
                status

            FROM drivers

            ORDER BY full_name ASC

        ";

        $stmt =
            $db->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET DRIVERS BY STATUS
    |--------------------------------------------------------------------------
    */

    public function getDriversByStatus(
        string $status
    ): array
    {
        $db =
            Database::get();

        $sql = "

            SELECT
                id,
                full_name

            FROM drivers

            WHERE status = :status

            ORDER BY full_name ASC

        ";

        $stmt =
            $db->prepare($sql);

        $stmt->bindValue(
            ':status',
            $status
        );

        $stmt->execute();

        return $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );
    }
}
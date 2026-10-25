<?php

namespace Finance\Models;

require_once __DIR__ .
    '/../../app/Core/Database.php';

use App\Core\Database;
use PDO;
use PDOException;

class AITripOperationsReportModel
{
    private PDO $db;

    private const TABLE =
        'ai_trip_operations_reports';

    public function __construct()
    {
        $this->db =
            Database::get();

        $this->db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL REPORTS
    |--------------------------------------------------------------------------
    */

    public function getAll(): array
    {
        try{

            $sql = "

                SELECT *

                FROM
                " . self::TABLE . "

                ORDER BY
                generated_at DESC

            ";

            return

                $this->db
                ->query($sql)
                ->fetchAll(PDO::FETCH_ASSOC);

        }catch(PDOException $e){

            error_log($e->getMessage());

            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET REPORT BY ID
    |--------------------------------------------------------------------------
    */

    public function getById(
        int $id
    ): ?array
    {
        try{

            $sql = "

                SELECT *

                FROM
                " . self::TABLE . "

                WHERE
                id = :id

                LIMIT 1

            ";

            $stmt =
                $this->db
                ->prepare($sql);

            $stmt->execute([

                ':id' => $id
            ]);

            $report =
                $stmt->fetch(PDO::FETCH_ASSOC);

            return
                $report
                ?: null;

        }catch(PDOException $e){

            error_log($e->getMessage());

            return null;
        }
    }
}
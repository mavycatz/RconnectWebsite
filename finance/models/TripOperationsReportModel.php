<?php

namespace Finance\Models;

/*
|--------------------------------------------------------------------------
| LOAD DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ .
    '/../../app/Core/Database.php';

use App\Core\Database;
use PDO;
use PDOException;

class TripOperationsReportModel
{
    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    private const TABLE =
        'trip_operations_reports';

    /*
    |--------------------------------------------------------------------------
    | DATABASE
    |--------------------------------------------------------------------------
    */

    private PDO $db;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        $this->db =
            Database::get();

        /*
        |--------------------------------------------------------------------------
        | PDO ERROR MODE
        |--------------------------------------------------------------------------
        */

        $this->db->setAttribute(

            PDO::ATTR_ERRMODE,

            PDO::ERRMODE_EXCEPTION
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE REPORT
    |--------------------------------------------------------------------------
    */

    public function save(
        array $data
    ): bool
    {
        try{

            /*
            |--------------------------------------------------------------------------
            | SQL
            |--------------------------------------------------------------------------
            */

            $sql = "

                INSERT INTO
                " . self::TABLE . " (

                    trip_group_code,

                    route_id,
                    bus_id,
                    driver_id,
                    inspector_id,

                    trip_date,

                    passenger_count,

                    total_revenue,
                    fuel_cost,
                    other_expenses,
                    net_income,

                    odometer,

                    notes,

                    ai_analysis_notes,

                    evidence_files,

                    created_at

                )

                VALUES (

                    :trip_group_code,

                    :route_id,
                    :bus_id,
                    :driver_id,
                    :inspector_id,

                    :trip_date,

                    :passenger_count,

                    :total_revenue,
                    :fuel_cost,
                    :other_expenses,
                    :net_income,

                    :odometer,

                    :notes,

                    :ai_analysis_notes,

                    :evidence_files,

                    :created_at

                )

            ";

            /*
            |--------------------------------------------------------------------------
            | PREPARE
            |--------------------------------------------------------------------------
            */

            $stmt =
                $this->db
                ->prepare($sql);

            /*
            |--------------------------------------------------------------------------
            | EXECUTE
            |--------------------------------------------------------------------------
            */

            $saved =
                $stmt->execute([

                    ':trip_group_code' =>

                        $data['trip_group_code']
                        ?? null,

                    ':route_id' =>

                        $data['route_id']
                        ?? null,

                    ':bus_id' =>

                        $data['bus_id']
                        ?? null,

                    ':driver_id' =>

                        $data['driver_id']
                        ?? null,

                    ':inspector_id' =>

                        $data['inspector_id']
                        ?? null,

                    ':trip_date' =>

                        $data['trip_date']
                        ?? null,

                    ':passenger_count' =>

                        $data['passenger_count']
                        ?? 0,

                    ':total_revenue' =>

                        $data['total_revenue']
                        ?? 0,

                    ':fuel_cost' =>

                        $data['fuel_cost']
                        ?? 0,

                    ':other_expenses' =>

                        $data['other_expenses']
                        ?? 0,

                    ':net_income' =>

                        $data['net_income']
                        ?? 0,

                    ':odometer' =>

                        $data['odometer']
                        ?? 0,

                    ':notes' =>

                        $data['notes']
                        ?? null,

                    ':ai_analysis_notes' =>

                        $data['ai_analysis_notes']
                        ?? null,

                    ':evidence_files' =>

                        $data['evidence_files']
                        ?? null,

                    ':created_at' =>

                        $data['created_at']
                        ?? date(
                            'Y-m-d H:i:s'
                        )
                ]);

            /*
            |--------------------------------------------------------------------------
            | FAILED SAVE
            |--------------------------------------------------------------------------
            */

            if(!$saved){

                $error =
                    $stmt->errorInfo();

                error_log(

                    '[TripOperationsReportModel] SQL EXECUTION FAILED: '

                    .

                    print_r(
                        $error,
                        true
                    )
                );

                return false;
            }

            return true;

        }catch(PDOException $e){

            /*
            |--------------------------------------------------------------------------
            | LOG DATABASE ERROR
            |--------------------------------------------------------------------------
            */

            error_log(

                '[TripOperationsReportModel PDO ERROR] '

                .

                $e->getMessage()

                .

                ' | FILE: '

                .

                $e->getFile()

                .

                ' | LINE: '

                .

                $e->getLine()
            );

            return false;

        }catch(\Throwable $e){

            /*
            |--------------------------------------------------------------------------
            | GENERAL ERROR
            |--------------------------------------------------------------------------
            */

            error_log(

                '[TripOperationsReportModel GENERAL ERROR] '

                .

                $e->getMessage()

                .

                ' | FILE: '

                .

                $e->getFile()

                .

                ' | LINE: '

                .

                $e->getLine()
            );

            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET LAST INSERT ID
    |--------------------------------------------------------------------------
    */

    public function getLastInsertId(): string
    {
        return
            $this->db
            ->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | GET REPORTS BY GROUP
    |--------------------------------------------------------------------------
    */

    public function getByGroupCode(
        string $groupCode
    ): array
    {
        try{

            $sql = "

                SELECT *

                FROM
                " . self::TABLE . "

                WHERE
                trip_group_code = :group_code

                ORDER BY
                trip_date DESC

            ";

            $stmt =
                $this->db
                ->prepare($sql);

            $stmt->execute([

                ':group_code' =>
                    $groupCode
            ]);

            return
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );

        }catch(PDOException $e){

            error_log(

                '[TripOperationsReportModel GET GROUP ERROR] '

                .

                $e->getMessage()
            );

            return [];
        }
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
                created_at DESC

            ";

            $stmt =
                $this->db
                ->query($sql);

            return
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );

        }catch(PDOException $e){

            error_log(

                '[TripOperationsReportModel GET ALL ERROR] '

                .

                $e->getMessage()
            );

            return [];
        }
    }
}
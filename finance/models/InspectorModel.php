<?php

namespace Finance\Models;

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ .
    '/../../app/Core/Database.php';

use App\Core\Database;

class InspectorModel
{
    /*
    |--------------------------------------------------------------------------
    | GET ALL INSPECTORS
    |--------------------------------------------------------------------------
    */

    public function getAllInspectors(): array
    {
        try{

            $db =
                Database::get();

            $sql = "

                SELECT

                    inspector_pk,
                    account_id,
                    inspector_identity,
                    inspector_name,
                    department,
                    experience_years,
                    operational_assignment,
                    assigned_routes,
                    contact_number,
                    email,
                    profile_picture,
                    additional_notes

                FROM inspectors

                ORDER BY inspector_name ASC

            ";

            $stmt =
                $db->prepare($sql);

            $stmt->execute();

            return $stmt->fetchAll(
                \PDO::FETCH_ASSOC
            );

        }catch(\Exception $e){

            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET INSPECTOR BY ID
    |--------------------------------------------------------------------------
    */

    public function getInspectorById(
        int $id
    ): ?array
    {
        try{

            $db =
                Database::get();

            $sql = "

                SELECT *

                FROM inspectors

                WHERE inspector_pk = :id

                LIMIT 1

            ";

            $stmt =
                $db->prepare($sql);

            $stmt->bindValue(

                ':id',

                $id,

                \PDO::PARAM_INT
            );

            $stmt->execute();

            $result =
                $stmt->fetch(
                    \PDO::FETCH_ASSOC
                );

            return $result ?: null;

        }catch(\Exception $e){

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET INSPECTORS BY DEPARTMENT
    |--------------------------------------------------------------------------
    */

    public function getInspectorsByDepartment(
        string $department
    ): array
    {
        try{

            $db =
                Database::get();

            $sql = "

                SELECT

                    inspector_pk,
                    inspector_identity,
                    inspector_name,
                    department,
                    operational_assignment,
                    contact_number,
                    email

                FROM inspectors

                WHERE department = :department

                ORDER BY inspector_name ASC

            ";

            $stmt =
                $db->prepare($sql);

            $stmt->bindValue(

                ':department',

                $department
            );

            $stmt->execute();

            return $stmt->fetchAll(
                \PDO::FETCH_ASSOC
            );

        }catch(\Exception $e){

            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET INSPECTORS BY ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    public function getInspectorsByAssignment(
        string $assignment
    ): array
    {
        try{

            $db =
                Database::get();

            $sql = "

                SELECT

                    inspector_pk,
                    inspector_identity,
                    inspector_name,
                    operational_assignment,
                    assigned_routes,
                    contact_number

                FROM inspectors

                WHERE operational_assignment = :assignment

                ORDER BY inspector_name ASC

            ";

            $stmt =
                $db->prepare($sql);

            $stmt->bindValue(

                ':assignment',

                $assignment
            );

            $stmt->execute();

            return $stmt->fetchAll(
                \PDO::FETCH_ASSOC
            );

        }catch(\Exception $e){

            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH INSPECTORS
    |--------------------------------------------------------------------------
    */

    public function searchInspectors(
        string $keyword
    ): array
    {
        try{

            $db =
                Database::get();

            $sql = "

                SELECT

                    inspector_pk,
                    inspector_identity,
                    inspector_name,
                    department,
                    operational_assignment,
                    contact_number,
                    email

                FROM inspectors

                WHERE

                    inspector_name LIKE :keyword

                    OR inspector_identity LIKE :keyword

                    OR department LIKE :keyword

                    OR assigned_routes LIKE :keyword

                    OR operational_assignment LIKE :keyword

                ORDER BY inspector_name ASC

            ";

            $stmt =
                $db->prepare($sql);

            $stmt->bindValue(

                ':keyword',

                '%' . $keyword . '%'
            );

            $stmt->execute();

            return $stmt->fetchAll(
                \PDO::FETCH_ASSOC
            );

        }catch(\Exception $e){

            return [];
        }
    }
}
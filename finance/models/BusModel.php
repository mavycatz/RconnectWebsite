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

class BusModel
{
    /*
    |--------------------------------------------------------------------------
    | GET ALL BUSES
    |--------------------------------------------------------------------------
    */

    public function getAllBuses(): array
    {
        try{

            $db =
                Database::get();

            $sql = "

                SELECT

                    id,
                    bus_id,
                    plate_number,
                    bus_type,
                    seats,
                    standing_capacity,
                    accessible_features,
                    driver,
                    from_terminal_id,
                    to_terminal_id,
                    route_name,
                    terminal,
                    schedule,
                    status,
                    qr_code,
                    qr_data

                FROM buses

                ORDER BY plate_number ASC

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
    | GET ACTIVE / OPERATIONAL BUSES
    |--------------------------------------------------------------------------
    */

    public function getOperationalBuses(): array
    {
        try{

            $db =
                Database::get();

            $sql = "

                SELECT

                    id,
                    bus_id,
                    plate_number,
                    bus_type,
                    seats,
                    standing_capacity,
                    accessible_features,
                    driver,
                    from_terminal_id,
                    to_terminal_id,
                    route_name,
                    terminal,
                    schedule,
                    status,
                    qr_code,
                    qr_data

                FROM buses

                WHERE LOWER(status) = 'active'
                    OR LOWER(status) = 'operational'

                ORDER BY plate_number ASC

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
    | GET BUS BY ID
    |--------------------------------------------------------------------------
    */

    public function getBusById(
        int $id
    ): ?array
    {
        try{

            $db =
                Database::get();

            $sql = "

                SELECT *

                FROM buses

                WHERE id = :id

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
    | SEARCH BUSES
    |--------------------------------------------------------------------------
    */

    public function searchBuses(
        string $keyword
    ): array
    {
        try{

            $db =
                Database::get();

            $sql = "

                SELECT

                    id,
                    bus_id,
                    plate_number,
                    bus_type,
                    route_name,
                    terminal,
                    status

                FROM buses

                WHERE

                    plate_number LIKE :keyword

                    OR bus_id LIKE :keyword

                    OR route_name LIKE :keyword

                    OR terminal LIKE :keyword

                ORDER BY plate_number ASC

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
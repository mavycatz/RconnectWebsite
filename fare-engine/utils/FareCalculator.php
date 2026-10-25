<?php

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';

use App\Core\Database;

class FareCalculator
{
    const MINIMUM_FARE = 15;

    public static function getFarePricingByStopId(
        $stopId,
        $routeName = null
    ){
        $conn = Database::get();

        $sql = "
            SELECT
                id,
                route_name,
                destination,
                regular_fare,
                student_fare,
                special_fare
            FROM fare_pricing
            WHERE id = ?
        ";

        $params = [$stopId];

        if($routeName){
            $sql .= " AND route_name = ?";
            $params[] = $routeName;
        }

        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }

    public static function getStopIdByName(
        $stopName,
        $routeName
    ){
        $conn = Database::get();

        $stmt = $conn->prepare("
            SELECT id
            FROM fare_pricing
            WHERE LOWER(TRIM(destination)) = LOWER(TRIM(?))
            AND route_name = ?
            LIMIT 1
        ");

        $stmt->execute([
            $stopName,
            $routeName
        ]);

        $id = $stmt->fetchColumn();

        if($id){
            return (int)$id;
        }

        $stmt = $conn->prepare("
            SELECT id
            FROM fare_pricing
            WHERE LOWER(TRIM(destination)) LIKE LOWER(TRIM(?))
            AND route_name = ?
            LIMIT 1
        ");

        $stmt->execute([
            '%' . trim($stopName) . '%',
            $routeName
        ]);

        $id = $stmt->fetchColumn();

        return $id
            ? (int)$id
            : null;
    }

    public static function resolveFareColumn(
        $discountType
    ){
        $type = strtolower(
            trim($discountType)
        );

        switch($type){

            case 'student':
            case 'senior':
            case 'pwd':
                return 'student_fare';

            case 'special':
            case 'teacher':
            case 'nurse':
                return 'special_fare';

            default:
                return 'regular_fare';
        }
    }

    /**
     * Fare for a boarding->destination segment for a given fare column,
     * using already-resolved fare_pricing rows. Keeps the reporting logic
     * (base vs discounted) identical to what the app charges.
     */
    public static function segmentFare(
        array $boardingStop,
        array $destinationStop,
        string $column
    ): float {
        $boardingFare = (float) ($boardingStop[$column] ?? self::MINIMUM_FARE);
        $destinationFare = (float) ($destinationStop[$column] ?? self::MINIMUM_FARE);

        if ((int) ($boardingStop['display_order'] ?? 0) === 1) {
            return round($destinationFare, 2);
        }

        return round(max($destinationFare - $boardingFare, (float) self::MINIMUM_FARE), 2);
    }

    public static function computeFare(
        $boardingStopId,
        $destinationStopId,
        $routeName,
        $discountType = 'regular'
    ){
        $boardingStop = self::getFarePricingByStopId(
            $boardingStopId,
            $routeName
        );

        if(!$boardingStop){

            return [
                'success' => false,
                'fare' => self::MINIMUM_FARE,
                'message' =>
                    'Boarding stop not found in fare_pricing'
            ];
        }

        $destinationStop = self::getFarePricingByStopId(
            $destinationStopId,
            $routeName
        );

        if(!$destinationStop){

            return [
                'success' => false,
                'fare' => self::MINIMUM_FARE,
                'message' =>
                    'Destination stop not found in fare_pricing'
            ];
        }

        if(
            $boardingStop['route_name']
            !==
            $destinationStop['route_name']
        ){
            return [
                'success' => false,
                'fare' => 0,
                'message' =>
                    'Route mismatch: boarding and destination belong to different routes'
            ];
        }

        $column = self::resolveFareColumn(
            $discountType
        );

        $boardingFare =
            (float)$boardingStop[$column];

        $destinationFare =
            (float)$destinationStop[$column];

        $computedFare = abs(
            $destinationFare -
            $boardingFare
        );

        $fare = max(
            self::MINIMUM_FARE,
            round($computedFare, 2)
        );

        return [
            'success' => true,
            'fare' => $fare,
            'boarding_fare' => $boardingFare,
            'destination_fare' => $destinationFare,
            'computed_fare' => $computedFare,
            'column' => $column,
            'route_name' =>
                $boardingStop['route_name'],
            'boarding_stop' =>
                $boardingStop['destination'],
            'destination_stop' =>
                $destinationStop['destination']
        ];
    }

    public static function computeFareByStopNames(
        $boardingName,
        $destinationName,
        $routeName,
        $discountType = 'regular'
    ){
        $boardingId = self::getStopIdByName(
            $boardingName,
            $routeName
        );

        if(!$boardingId){

            return [
                'success' => false,
                'fare' => self::MINIMUM_FARE,
                'message' =>
                    'Boarding stop not found: ' . $boardingName
            ];
        }

        $destinationId = self::getStopIdByName(
            $destinationName,
            $routeName
        );

        if(!$destinationId){

            return [
                'success' => false,
                'fare' => self::MINIMUM_FARE,
                'message' =>
                    'Destination stop not found: ' . $destinationName
            ];
        }

        return self::computeFare(
            $boardingId,
            $destinationId,
            $routeName,
            $discountType
        );
    }
}

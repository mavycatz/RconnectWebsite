<?php

class FarePricingModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function getFarePricing(): array
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT *
                FROM fare_pricing
                ORDER BY route_name ASC,
                         display_order ASC
                "
            );

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function getFarePricingByRoute(
        string $routeName
    ): array
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT *
                FROM fare_pricing
                WHERE route_name = :route_name
                ORDER BY display_order ASC
                "
            );

        $stmt->execute([

            ':route_name' =>
                $routeName

        ]);

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function addFare(
        array $data
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                INSERT INTO fare_pricing
                (
                    route_name,
                    destination,
                    regular_fare,
                    student_fare,
                    special_fare,
                    display_order
                )
                VALUES
                (
                    :route_name,
                    :destination,
                    :regular_fare,
                    :student_fare,
                    :special_fare,
                    :display_order
                )
                "
            );

        return $stmt->execute([

            ':route_name' =>
                $data['route_name'],

            ':destination' =>
                $data['destination'],

            ':regular_fare' =>
                $data['regular_fare'],

            ':student_fare' =>
                $data['student_fare'],

            ':special_fare' =>
                $data['special_fare'],

            ':display_order' =>
                $data['display_order']

        ]);
    }

    public function updateFare(
        array $data
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                UPDATE fare_pricing
                SET

                    destination = :destination,
                    regular_fare = :regular_fare,
                    student_fare = :student_fare,
                    special_fare = :special_fare,
                    display_order = :display_order

                WHERE id = :id
                "
            );

        return $stmt->execute([

            ':destination' =>
                $data['destination'],

            ':regular_fare' =>
                $data['regular_fare'],

            ':student_fare' =>
                $data['student_fare'],

            ':special_fare' =>
                $data['special_fare'],

            ':display_order' =>
                $data['display_order'],

            ':id' =>
                $data['id']

        ]);
    }

    public function deleteFare(
        int $id
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                DELETE FROM fare_pricing
                WHERE id = ?
                "
            );

        return $stmt->execute([
            $id
        ]);
    }

    public function saveFarePricing(
        array $fares,
        string $routeName
    ): bool
    {
        $this->conn->beginTransaction();

        try{

            $existingIds =
                [];

            foreach(
                $this->getFarePricingByRoute(
                    $routeName
                ) as $existing
            ){

                $existingIds[] =
                    (int)$existing['id'];

            }

            $submittedIds =
                [];

            foreach(
                $fares as $index => $fare
            ){

                $displayOrder =
                    $index + 1;

                $id =
                    isset($fare['id'])
                    && $fare['id'] !== ''
                    && $fare['id'] !== null
                    ? (int)$fare['id']
                    : 0;

                if(
                    $id > 0
                    && in_array(
                        $id,
                        $existingIds,
                        true
                    )
                ){

                    $submittedIds[] =
                        $id;

                    $this->updateFare([

                        'id' =>
                            $id,

                        'destination' =>
                            $fare['destination'],

                        'regular_fare' =>
                            $fare['regular_fare'],

                        'student_fare' =>
                            $fare['student_fare'],

                        'special_fare' =>
                            $fare['special_fare'],

                        'display_order' =>
                            $displayOrder

                    ]);

                    continue;

                }

                $this->addFare([

                    'route_name' =>
                        $routeName,

                    'destination' =>
                        $fare['destination'],

                    'regular_fare' =>
                        $fare['regular_fare'],

                    'student_fare' =>
                        $fare['student_fare'],

                    'special_fare' =>
                        $fare['special_fare'],

                    'display_order' =>
                        $displayOrder

                ]);

            }

            foreach(
                array_diff(
                    $existingIds,
                    $submittedIds
                ) as $removedId
            ){

                $this->deleteFare(
                    (int)$removedId
                );

            }

            $this->conn->commit();

            return true;

        }catch(Throwable $e){

            $this->conn->rollBack();

            throw $e;

        }
    }
}
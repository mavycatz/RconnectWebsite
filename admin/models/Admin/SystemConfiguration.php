<?php

class SystemConfigurationModel
{
    private PDO $conn;

    public function __construct(
        PDO $conn
    ){
        $this->conn = $conn;
    }

    public function getBusTypes(): array
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT

                    bus_type,

                    MAX(seats)
                    AS seats,

                    MAX(
                        standing_capacity
                    )
                    AS standing_capacity

                FROM buses

                GROUP BY bus_type

                ORDER BY bus_type ASC
                "
            );

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function getFuelPrices(): array
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT *
                FROM fuel_prices
                ORDER BY fuel_type ASC
                "
            );

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function getFuelPrice(
        int $id
    ): array|false
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT *
                FROM fuel_prices
                WHERE id = ?
                "
            );

        $stmt->execute([
            $id
        ]);

        return $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }

    public function findFuelPriceByName(
        string $name,
        int $excludeId = 0
    ): array|false
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT *
                FROM fuel_prices
                WHERE
                    LOWER(fuel_type) =
                    LOWER(:fuel_type)
                    AND id <> :exclude_id
                LIMIT 1
                "
            );

        $stmt->execute([

            ':fuel_type' =>
                trim($name),

            ':exclude_id' =>
                $excludeId

        ]);

        return $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }

    public function updateFuelPrice(
        array $data
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                UPDATE fuel_prices
                SET

                    fuel_type =
                    :fuel_type,

                    price_per_liter =
                    :price

                WHERE id = :id
                "
            );

        return $stmt->execute([

            ':id' =>
                $data['id'],

            ':fuel_type' =>
                $data['fuel_type'],

            ':price' =>
                $data['price_per_liter']

        ]);
    }

    public function getViolationCategories(): array
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT *
                FROM violation_categories
                ORDER BY category_name ASC
                "
            );

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function addViolationCategory(
        string $name
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                INSERT INTO
                violation_categories
                (
                    category_name
                )
                VALUES
                (
                    ?
                )
                "
            );

        return $stmt->execute([
            $name
        ]);
    }

    public function deleteViolationCategory(
        int $id
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                DELETE FROM
                violation_categories
                WHERE id = ?
                "
            );

        return $stmt->execute([
            $id
        ]);
    }

    public function getViolations(): array
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT

                    v.*,

                    vc.category_name

                FROM violations v

                LEFT JOIN
                violation_categories vc

                ON vc.id =
                v.category_id

                ORDER BY
                vc.category_name ASC,
                v.violation_name ASC
                "
            );

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function addViolation(
        array $data
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                INSERT INTO
                violations
                (
                    category_id,
                    violation_name,
                    severity
                )
                VALUES
                (
                    :category_id,
                    :violation_name,
                    :severity
                )
                "
            );

        return $stmt->execute([

            ':category_id' =>
                $data['category_id'],

            ':violation_name' =>
                $data['violation_name'],

            ':severity' =>
                $data['severity']

        ]);
    }

    public function updateViolation(
        array $data
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                UPDATE violations
                SET

                    category_id =
                    :category_id,

                    violation_name =
                    :violation_name,

                    severity =
                    :severity

                WHERE id = :id
                "
            );

        return $stmt->execute([

            ':id' =>
                $data['id'],

            ':category_id' =>
                $data['category_id'],

            ':violation_name' =>
                $data['violation_name'],

            ':severity' =>
                $data['severity']

        ]);
    }

    public function deleteViolation(
        int $id
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                DELETE FROM
                violations
                WHERE id = ?
                "
            );

        return $stmt->execute([
            $id
        ]);
    }
    
    public function saveFuelPrices(
    array $prices
): bool
{
    $this->conn->beginTransaction();

    try{

        foreach(
            $prices as $price
        ){

            $this->updateFuelPrice(
                $price
            );

        }

        $this->conn->commit();

        return true;

    }catch(Throwable $e){

        $this->conn->rollBack();

        throw $e;

    }
}

public function updateViolationCategory(
    array $data
): bool
{
    $stmt =
        $this->conn->prepare(
            "
            UPDATE
                violation_categories
            SET
                category_name =
                :category_name
            WHERE
                id = :id
            "
        );

    return $stmt->execute([

        ':id' =>
            $data['id'],

        ':category_name' =>
            $data['category_name']

    ]);
}

public function getViolation(
    int $id
): array|false
{
    $stmt =
        $this->conn->prepare(
            "
            SELECT *
            FROM violations
            WHERE id = ?
            "
        );

    $stmt->execute([
        $id
    ]);

    return $stmt->fetch(
        PDO::FETCH_ASSOC
    );
}

public function getViolationCategory(
    int $id
): array|false
{
    $stmt =
        $this->conn->prepare(
            "
            SELECT *
            FROM violation_categories
            WHERE id = ?
            "
        );

    $stmt->execute([
        $id
    ]);

    return $stmt->fetch(
        PDO::FETCH_ASSOC
    );
}

public function addFuelPrice(
    array $data
): bool
{
    $stmt =
        $this->conn->prepare(
            "
            INSERT INTO fuel_prices
            (
                fuel_type,
                price_per_liter
            )
            VALUES
            (
                :fuel_type,
                :price_per_liter
            )
            "
        );

    return $stmt->execute([

        ':fuel_type' =>
            $data['fuel_type'],

        ':price_per_liter' =>
            $data['price_per_liter']

    ]);
}

    public function deleteFuelPrice(
        int $id
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                DELETE FROM fuel_prices
                WHERE id = ?
                "
            );

        return $stmt->execute([
            $id
        ]);
    }

    public function getVehicleTypes(): array
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT
                    id,
                    vehicle_name,
                    seats,
                    standing_capacity
                FROM vehicle_types
                ORDER BY vehicle_name ASC
                "
            );

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function getVehicleType(
        int $id
    ): array|false
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT
                    id,
                    vehicle_name,
                    seats,
                    standing_capacity
                FROM vehicle_types
                WHERE id = ?
                "
            );

        $stmt->execute([
            $id
        ]);

        return $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }

    public function findVehicleTypeByName(
        string $name,
        int $excludeId = 0
    ): array|false
    {
        $stmt =
            $this->conn->prepare(
                "
                SELECT
                    id,
                    vehicle_name,
                    seats,
                    standing_capacity
                FROM vehicle_types
                WHERE
                    LOWER(vehicle_name) =
                    LOWER(:vehicle_name)
                    AND id <> :exclude_id
                LIMIT 1
                "
            );

        $stmt->execute([

            ':vehicle_name' =>
                trim($name),

            ':exclude_id' =>
                $excludeId

        ]);

        return $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }

    public function addVehicleType(
        array $data
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                INSERT INTO vehicle_types
                (
                    vehicle_name,
                    seats,
                    standing_capacity
                )
                VALUES
                (
                    :vehicle_name,
                    :seats,
                    :standing_capacity
                )
                "
            );

        return $stmt->execute([

            ':vehicle_name' =>
                $data['vehicle_name'],

            ':seats' =>
                $data['seats'],

            ':standing_capacity' =>
                $data['standing_capacity']

        ]);
    }

    public function updateVehicleType(
        array $data
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                UPDATE vehicle_types
                SET
                    vehicle_name = :vehicle_name,
                    seats = :seats,
                    standing_capacity = :standing_capacity
                WHERE id = :id
                "
            );

        return $stmt->execute([

            ':id' =>
                $data['id'],

            ':vehicle_name' =>
                $data['vehicle_name'],

            ':seats' =>
                $data['seats'],

            ':standing_capacity' =>
                $data['standing_capacity']

        ]);
    }

    public function deleteVehicleType(
        int $id
    ): bool
    {
        $stmt =
            $this->conn->prepare(
                "
                DELETE FROM vehicle_types
                WHERE id = ?
                "
            );

        return $stmt->execute([
            $id
        ]);
    }
}
<?php

class DriverService
{
    private DriverModel $driverModel;

    public function __construct(
        DriverModel $driverModel
    ){
        $this->driverModel =
            $driverModel;
    }

    public function getTotalDrivers(): int
    {
        return $this->driverModel
            ->getTotalDrivers();
    }

    public function getDriverStats(): array
    {
        return $this->driverModel
            ->getDriverStats();
    }
}
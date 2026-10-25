<?php

namespace Finance\Services;

require_once __DIR__ .
    '/../models/DriverModel.php';

use Finance\Models\DriverModel;

class DriverService
{
    private DriverModel $driverModel;

    public function __construct()
    {
        $this->driverModel =
            new DriverModel();
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL DRIVERS
    |--------------------------------------------------------------------------
    */

    public function getAllDrivers(): array
    {
        return $this
            ->driverModel
            ->getAllDrivers();
    }

    /*
    |--------------------------------------------------------------------------
    | GET ACTIVE DRIVERS
    |--------------------------------------------------------------------------
    */

    public function getActiveDrivers(): array
    {
        return $this
            ->driverModel
            ->getDriversByStatus(
                'active'
            );
    }
}
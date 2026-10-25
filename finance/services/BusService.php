<?php

namespace Finance\Services;

require_once __DIR__ .
    '/../models/BusModel.php';

use Finance\Models\BusModel;

class BusService
{
    private BusModel $busModel;

    public function __construct()
    {
        $this->busModel =
            new BusModel();
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL BUSES
    |--------------------------------------------------------------------------
    */

    public function getAllBuses(): array
    {
        return $this
            ->busModel
            ->getAllBuses();
    }

    /*
    |--------------------------------------------------------------------------
    | GET ACTIVE BUSES
    |--------------------------------------------------------------------------
    */

    public function getOperationalBuses(): array
    {
        return $this
            ->busModel
            ->getOperationalBuses();
    }
}
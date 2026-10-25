<?php

namespace Finance\Services;

require_once __DIR__ .
    '/../models/RouteModel.php';

use Finance\Models\RouteModel;

class RouteService
{
    /*
    |--------------------------------------------------------------------------
    | PROPERTIES
    |--------------------------------------------------------------------------
    */

    private RouteModel $routeModel;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        $this->routeModel =
            new RouteModel();
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL ROUTES
    |--------------------------------------------------------------------------
    */

    public function getAllRoutes(): array
    {
        return $this
            ->routeModel
            ->getAllRoutes();
    }

    /*
    |--------------------------------------------------------------------------
    | GET ACTIVE ROUTES
    |--------------------------------------------------------------------------
    */

    public function getActiveRoutes(): array
    {
        return $this
            ->routeModel
            ->getRoutesByStatus(
                'active'
            );
    }
}
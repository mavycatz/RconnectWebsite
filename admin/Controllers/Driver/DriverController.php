<?php

class DriverController
{
    private DriverService $driverService;

    public function __construct(
        DriverService $driverService
    ){
        $this->driverService =
            $driverService;
    }

    public function getTotalDrivers(): void
    {
        $total =
            $this->driverService
                ->getTotalDrivers();

        echo json_encode([
            'success' => true,
            'total'   => $total
        ]);
    }

    public function getDriverStats(): void
    {
        $stats =
            $this->driverService
                ->getDriverStats();

        echo json_encode([
            'success' => true,
            'data'    => $stats
        ]);
    }
}
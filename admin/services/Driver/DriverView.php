<?php

class DriverService
{
    private $driverModel;

    public function __construct(
        DriverModel $driverModel
    ){
        $this->driverModel =
            $driverModel;
    }

    public function getDrivers()
    {
        return $this->driverModel
            ->getAllDrivers();
    }

    public function getDriver($id)
    {
        return $this->driverModel
            ->getDriverById($id);
    }

    public function addDriver($data)
    {
        return $this->driverModel
            ->addDriver($data);
    }

    public function updateDriver($id, $data)
    {
        return $this->driverModel
            ->updateDriver(
                $id,
                $data
            );
    }

    public function getArchivedDrivers()
    {
        return $this->driverModel
            ->getArchivedDrivers();
    }

    public function archiveDriver($id)
    {
        return $this->driverModel
            ->archiveDriver($id);
    }

    public function approveDriver($id)
    {
        return $this->driverModel
            ->approveDriver($id);
    }

    public function rejectDriver($id)
    {
        return $this->driverModel
            ->rejectDriver($id);
    }
}
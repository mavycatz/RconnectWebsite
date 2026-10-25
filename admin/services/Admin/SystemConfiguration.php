<?php

class SystemConfigurationService
{
    private SystemConfigurationModel $model;

    public function __construct(
        SystemConfigurationModel $model
    ){
        $this->model = $model;
    }

    public function getBusTypes(): array
    {
        return
            $this->model
                ->getBusTypes();
    }

    public function getFuelPrices(): array
    {
        return
            $this->model
                ->getFuelPrices();
    }

    public function updateFuelPrice(
        array $data
    ): bool
    {
        return
            $this->model
                ->updateFuelPrice(
                    $data
                );
    }

    public function saveFuelPrices(
        array $prices
    ): bool
    {
        return
            $this->model
                ->saveFuelPrices(
                    $prices
                );
    }

    public function addFuelPrice(
        array $data
    ): bool
    {
        return
            $this->model
                ->addFuelPrice(
                    $data
                );
    }

    public function deleteFuelPrice(
        int $id
    ): bool
    {
        return
            $this->model
                ->deleteFuelPrice(
                    $id
                );
    }

    public function getVehicleTypes(): array
    {
        return
            $this->model
                ->getVehicleTypes();
    }

    public function getVehicleType(
        int $id
    ): array|false
    {
        return
            $this->model
                ->getVehicleType(
                    $id
                );
    }

    public function findVehicleTypeByName(
        string $name,
        int $excludeId = 0
    ): array|false
    {
        return
            $this->model
                ->findVehicleTypeByName(
                    $name,
                    $excludeId
                );
    }

    public function addVehicleType(
        array $data
    ): bool
    {
        return
            $this->model
                ->addVehicleType(
                    $data
                );
    }

    public function updateVehicleType(
        array $data
    ): bool
    {
        return
            $this->model
                ->updateVehicleType(
                    $data
                );
    }

    public function deleteVehicleType(
        int $id
    ): bool
    {
        return
            $this->model
                ->deleteVehicleType(
                    $id
                );
    }

    public function getViolationCategories(): array
    {
        return
            $this->model
                ->getViolationCategories();
    }

    public function getViolationCategory(
        int $id
    ): array|false
    {
        return
            $this->model
                ->getViolationCategory(
                    $id
                );
    }

    public function addViolationCategory(
        string $name
    ): bool
    {
        return
            $this->model
                ->addViolationCategory(
                    $name
                );
    }

    public function updateViolationCategory(
        array $data
    ): bool
    {
        return
            $this->model
                ->updateViolationCategory(
                    $data
                );
    }

    public function deleteViolationCategory(
        int $id
    ): bool
    {
        return
            $this->model
                ->deleteViolationCategory(
                    $id
                );
    }

    public function getViolations(): array
    {
        return
            $this->model
                ->getViolations();
    }

    public function getViolation(
        int $id
    ): array|false
    {
        return
            $this->model
                ->getViolation(
                    $id
                );
    }

    public function addViolation(
        array $data
    ): bool
    {
        return
            $this->model
                ->addViolation(
                    $data
                );
    }

    public function updateViolation(
        array $data
    ): bool
    {
        return
            $this->model
                ->updateViolation(
                    $data
                );
    }

    public function deleteViolation(
        int $id
    ): bool
    {
        return
            $this->model
                ->deleteViolation(
                    $id
                );
    }
}
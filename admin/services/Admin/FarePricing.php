<?php

class FarePricingService
{
    private FarePricingModel $model;

    public function __construct(
        FarePricingModel $model
    ){
        $this->model = $model;
    }

    public function getFarePricing(): array
    {
        return
            $this->model
                ->getFarePricing();
    }

    public function getFarePricingByRoute(
        string $routeName
    ): array
    {
        return
            $this->model
                ->getFarePricingByRoute(
                    $routeName
                );
    }

    public function addFare(
        array $data
    ): bool
    {
        return
            $this->model
                ->addFare(
                    $data
                );
    }

    public function updateFare(
        array $data
    ): bool
    {
        return
            $this->model
                ->updateFare(
                    $data
                );
    }

    public function deleteFare(
        int $id
    ): bool
    {
        return
            $this->model
                ->deleteFare(
                    $id
                );
    }

    public function saveFarePricing(
        array $fares,
        string $routeName
    ): bool
    {
        return
            $this->model
                ->saveFarePricing(
                    $fares,
                    $routeName
                );
    }
}
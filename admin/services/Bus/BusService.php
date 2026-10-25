<?php

class BusService
{
    private BusModel $busModel;

    public function __construct(
        BusModel $busModel
    ){
        $this->busModel =
            $busModel;
    }

    public function getAllBuses(): array
    {
        return
            $this->busModel
                ->getAllBuses();
    }

    public function getAssignableBuses(): array
    {
        return
            $this->busModel
                ->getAssignableBuses();
    }

    public function getBus(
        string $bus_id
    ): ?array
    {
        return
            $this->busModel
                ->getBus($bus_id);
    }

    public function addBus(
        array $data
    ): bool
    {
        return
            $this->busModel
                ->addBus($data);
    }

    public function editBus(
        string $bus_id,
        array $data
    ): bool
    {
        return
            $this->busModel
                ->editBus(
                    $bus_id,
                    $data
                );
    }

    public function getStats(): array
    {
        return
            $this->busModel
                ->getStats();
    }
}

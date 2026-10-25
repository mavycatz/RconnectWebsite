<?php

class BusController
{
    private BusService $busService;

    public function __construct(
        BusService $busService
    ){
        $this->busService =
            $busService;
    }

    public function getAllBuses(): void
    {
        echo json_encode([

            'success' => true,

            'data' =>
                $this->busService
                    ->getAllBuses()

        ]);
    }

    public function getAssignableBuses(): void
    {
        echo json_encode([

            'success' => true,

            'data' =>
                $this->busService
                    ->getAssignableBuses()

        ]);
    }

    public function getBus(
        string $bus_id
    ): void
    {
        $bus =
            $this->busService
                ->getBus($bus_id);

        if($bus){

            echo json_encode([
                'success' => true,
                'data' => $bus
            ]);

        }else{

            echo json_encode([
                'success' => false,
                'message' =>
                    'Bus not found'
            ]);

        }
    }

    public function addBus(
        array $data
    ): void
    {
        try{

            $result =
                $this->busService
                    ->addBus($data);

            echo json_encode([
                'success' => $result
            ]);

        }catch(\Throwable $e){

            echo json_encode([
                'success' => false,
                'message' =>
                    $e->getMessage()
            ]);

        }
    }

    public function editBus(
        string $bus_id,
        array $data
    ): void
    {
        try{

            $result =
                $this->busService
                    ->editBus(
                        $bus_id,
                        $data
                    );

            echo json_encode([
                'success' => $result
            ]);

        }catch(\Throwable $e){

            echo json_encode([
                'success' => false,
                'message' =>
                    $e->getMessage()
            ]);

        }
    }

    public function getStats(): void
    {
        echo json_encode([

            'success' => true,

            'data' =>
                $this->busService
                    ->getStats()

        ]);
    }
}

<?php

class FarePricingController
{
    private FarePricingService $service;

    public function __construct(
        FarePricingService $service
    ){
        $this->service = $service;
    }

    public function getFarePricing(): void
    {
        try{

            $routeName =
                $_GET['route']
                ?? '';

            $data =

                !empty(
                    $routeName
                )

                ?

                $this->service
                    ->getFarePricingByRoute(
                        $routeName
                    )

                :

                $this->service
                    ->getFarePricing();

            echo json_encode([

                'success' => true,

                'data' => $data

            ]);

        }catch(Throwable $e){

            echo json_encode([

                'success' => false,

                'message' =>
                    $e->getMessage()

            ]);

        }
    }

    public function addFare(): void
    {
        try{

            $success =
                $this->service
                    ->addFare(
                        $_POST
                    );

            echo json_encode([

                'success' =>
                    $success,

                'message' =>
                    $success
                    ? 'Fare added successfully.'
                    : 'Failed to add fare.'

            ]);

        }catch(Throwable $e){

            echo json_encode([

                'success' => false,

                'message' =>
                    $e->getMessage()

            ]);

        }
    }

    public function updateFare(): void
    {
        try{

            $success =
                $this->service
                    ->updateFare(
                        $_POST
                    );

            echo json_encode([

                'success' =>
                    $success,

                'message' =>
                    $success
                    ? 'Fare updated successfully.'
                    : 'Failed to update fare.'

            ]);

        }catch(Throwable $e){

            echo json_encode([

                'success' => false,

                'message' =>
                    $e->getMessage()

            ]);

        }
    }

    public function deleteFare(): void
    {
        try{

            $payload =
                json_decode(
                    file_get_contents(
                        'php://input'
                    ),
                    true
                );

            $id =
                (int)(
                    is_array($payload)
                    && isset($payload['id'])
                    ? $payload['id']
                    : ($_POST['id'] ?? 0)
                );

            if(
                $id <= 0
            ){

                throw new Exception(
                    'Invalid fare ID.'
                );

            }

            $success =
                $this->service
                    ->deleteFare(
                        $id
                    );

            echo json_encode([

                'success' =>
                    $success,

                'message' =>
                    $success
                    ? 'Fare deleted successfully.'
                    : 'Failed to delete fare.'

            ]);

        }catch(Throwable $e){

            echo json_encode([

                'success' => false,

                'message' =>
                    $e->getMessage()

            ]);

        }
    }

    public function saveFarePricing(): void
    {
        try{

            $payload =
                json_decode(

                    file_get_contents(
                        'php://input'
                    ),

                    true

                );

            if(
                !$payload
            ){

                throw new Exception(
                    'Invalid payload.'
                );

            }

            $fares =
                $payload['fares']
                ?? [];

            $routeName =
                $payload['route_name']
                ?? '';

            if(
                empty(
                    $routeName
                )
            ){

                throw new Exception(
                    'Route name is required.'
                );

            }

            $success =
                $this->service
                    ->saveFarePricing(

                        $fares,

                        $routeName

                    );

            echo json_encode([

                'success' =>
                    $success,

                'message' =>
                    $success
                    ? 'Fare pricing saved successfully.'
                    : 'Failed to save fare pricing.'

            ]);

        }catch(Throwable $e){

            echo json_encode([

                'success' => false,

                'message' =>
                    $e->getMessage()

            ]);

        }
    }
}
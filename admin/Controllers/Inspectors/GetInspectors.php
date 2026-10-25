<?php

class GetInspectorsController
{
    private GetInspectorsService $service;

    public function __construct(
        GetInspectorsService $service
    ){
        $this->service = $service;
    }

    public function getInspectors(): void
    {
        echo json_encode([

            'success' => true,

            'inspectors' =>
                $this->service
                    ->getInspectors()

        ]);
    }
}
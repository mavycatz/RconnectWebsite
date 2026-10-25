<?php

class InspectorController
{
    private InspectorService $service;

    public function __construct(
        InspectorService $service
    ){
        $this->service = $service;
    }

    public function getStats(): void
    {
        echo json_encode([
            'success' => true,
            'data' =>
                $this->service
                    ->getStats()
        ]);
    }
    
    
}
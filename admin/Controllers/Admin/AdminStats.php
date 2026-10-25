<?php

class AdminController
{
    private AdminService $service;

    public function __construct(
        AdminService $service
    ){
        $this->service = $service;
    }

    public function getAdminStats(): void
    {
        try{

            $stats =
                $this->service
                    ->getAdminStats();

            echo json_encode([
                'success' => true,
                'data'    => $stats
            ]);

        }catch(Throwable $e){

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
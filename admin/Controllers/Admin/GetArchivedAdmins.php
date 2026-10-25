<?php

class GetArchivedAdminsController
{
    private GetArchivedAdminsService $service;

    public function __construct(
        GetArchivedAdminsService $service
    ){
        $this->service = $service;
    }

    public function getArchivedAdmins(): void
    {
        try{

            $admins =
                $this->service
                    ->getArchivedAdmins();

            echo json_encode([

                'success' => true,

                'data' => $admins

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
<?php

class AdminController
{
    private AdminService $service;

    public function __construct(
        AdminService $service
    ){
        $this->service = $service;
    }

    public function getAdmins(): void
    {
        try{

            $admins =
                $this->service
                    ->getAdmins();

            echo json_encode([
                'success' => true,
                'data' => $admins
            ]);

        }catch(Throwable $e){

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

        }
    }

    public function getAdmin(): void
    {
        try{

            $id =
                (int)(
                    $_GET['id']
                    ?? 0
                );

            $admin =
                $this->service
                    ->getAdminById(
                        $id
                    );

            if(
                !$admin
            ){

                echo json_encode([

                    'success' => false,

                    'message' =>
                        'Administrator not found.'

                ]);

                return;

            }

            echo json_encode([

                'success' => true,

                'admin' => $admin

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
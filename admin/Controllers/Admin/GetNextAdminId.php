<?php

class GetNextAdminIdController
{
    private GetNextAdminIdService $service;

    public function __construct(
        GetNextAdminIdService $service
    ){
        $this->service = $service;
    }

    public function getNextId(): void
    {
        try {
            $adminId =
                $this->service
                    ->getNextId();

            echo json_encode([
                'success' => true,
                'admin_id' => $adminId
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

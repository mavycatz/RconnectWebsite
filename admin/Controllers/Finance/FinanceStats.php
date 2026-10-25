<?php

class FinanceDepartmentController
{
    private FinanceDepartmentService $service;

    public function __construct(
        FinanceDepartmentService $service
    ){
        $this->service = $service;
    }

    public function getStats(): void
    {
        try {

            $stats =
                $this->service
                    ->getFinanceStats();

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);

        } catch(Throwable $e){

            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

        }
    }
}
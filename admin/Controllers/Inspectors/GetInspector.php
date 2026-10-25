<?php

class GetInspectorController
{
    private GetInspectorService $service;

    public function __construct(
        GetInspectorService $service
    ){
        $this->service = $service;
    }

    public function getInspector(): void
    {
        $id =
            (int)(
                $_GET['id']
                ?? 0
            );

        if($id <= 0){

            echo json_encode([

                'success' => false,

                'message' =>
                    'Invalid Inspector ID'

            ]);

            return;

        }

        $inspector =
            $this->service
                ->getInspector(
                    $id
                );

        echo json_encode([

            'success' => true,

            'inspector' =>
                $inspector

        ]);
    }
}
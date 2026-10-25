<?php

require_once $_SERVER['DOCUMENT_ROOT']
. '/admin/models/Driver/DriverAccountModel.php';

class DriverAccountService
{
    private DriverAccountModel $driverAccountModel;

    public function __construct(
        DriverAccountModel $driverAccountModel
    ){
        $this->driverAccountModel =
            $driverAccountModel;
    }

    public function addDriver(
        array $data,
        array $files
    ): array
    {
        return
            $this->driverAccountModel
                ->addDriver(
                    $data,
                    $files
                );
    }
}
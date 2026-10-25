<?php

class FinanceDepartmentService
{
    private FinanceDepartmentModel $model;

    public function __construct(
        FinanceDepartmentModel $model
    ){
        $this->model = $model;
    }

    public function getFinanceStats(): array
    {
        return $this->model->getFinanceStats();
    }
}
<?php declare(strict_types=1);

namespace Admin\Controllers;

require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/models/FinanceModel.php';

use Admin\Models\FinanceModel;

class FinanceController
{
    private FinanceModel $financeModel;

    public function __construct()
    {
        $this->financeModel = new FinanceModel();
    }

    public function getFinanceOverview(): array
    {
        return $this->financeModel->getFinanceOverview();
    }
}
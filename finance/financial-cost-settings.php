<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/controllers/FinancialCostSettingsController.php';

use Finance\Controllers\FinancialCostSettingsController;

(new FinancialCostSettingsController())->index();
